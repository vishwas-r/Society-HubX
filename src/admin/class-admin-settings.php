<?php
/**
 * Class: Admin Settings
 * Renders the Plugin Settings Page.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Admin_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_setup_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
		add_action( 'admin_post_sgvx51_migrate_json', array( $this, 'handle_migrate_json' ) );
		add_action( 'admin_post_sgvx51_export_json', array( $this, 'handle_export_json' ) );
		add_action( 'admin_post_sgvx51_reset_db', array( $this, 'handle_reset_db' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'in_admin_footer', array( $this, 'render_storage_mode_indicator' ) );
	}

	public function render_storage_mode_indicator() {
		$mode = get_option( 'sgvx51_storage_mode', 'mysql' );
		$color = $mode === 'mysql' ? '#10b981' : '#f59e0b'; // Green for MySQL, Amber for JSON
		echo '<div style="position:fixed; bottom:10px; right:10px; background:white; border:1px solid #ccc; padding:5px 10px; border-radius:4px; font-size:11px; z-index:99999; box-shadow:0 2px 5px rgba(0,0,0,0.1); display:flex; items-center; gap:5px;">
			<span style="width:8px; height:8px; border-radius:50%; background:' . $color . '; display:inline-block;"></span>
			<strong>Storage:</strong> ' . strtoupper( $mode ) . '
		</div>';
	}

	public function register_settings_page() {
		add_menu_page(
			'Society GoVernX',
			'Society GoVernX',
			'manage_options',
			'sgvx51-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-building',
			25
		);

		// Rename first subcommand to "Dashboard" to better reflect the new UI
		add_submenu_page(
			'sgvx51-settings',
			'Society Dashboard',
			'Dashboard',
			'manage_options',
			'sgvx51-settings',
			array( $this, 'render_settings_page' )
		);

		// Global Settings Page
		add_submenu_page(
			'sgvx51-settings',
			'Society Settings',
			'Settings',
			'manage_options',
			'sgvx51-global-settings',
			array( $this, 'render_global_settings_page' )
		);

		// Democracy (Polls) Page
		add_submenu_page(
			'sgvx51_poll_manager',
			'Digital Democracy',
			'Democracy',
			'manage_options',
			'sgvx51-polls',
			array( $this, 'render_polls_page' )
		);

		// Notifications Page
		add_submenu_page(
			'sgvx51-settings',
			'Notification Center',
			'Notifications',
			'manage_options',
			'sgvx51-notifications',
			array( $this, 'render_notifications_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		// Use the official hook check for the settings page
		if ( strpos( $hook, 'sgvx51-global-settings' ) !== false ) {
			wp_enqueue_media();
			wp_enqueue_script( 
				'sgvx51-admin-settings', 
				SGVX51_PLUGIN_URL . 'assets/js/admin-settings.js', 
				array( 'jquery', 'media-views' ), 
				SGVX51_VERSION, 
				false // Load in header so switchSettingsTab is defined early
			);
		}
	}

	public function register_settings() {
		// General Options
		register_setting( 'sgvx51_options_group', 'sgvx51_google_client_id' );
		register_setting( 'sgvx51_options_group', 'sgvx51_google_client_secret' );
		register_setting( 'sgvx51_options_group', 'sgvx51_sync_frequency' );
		register_setting( 'sgvx51_options_group', 'sgvx51_maintenance_amount' );
		register_setting( 'sgvx51_options_group', 'sgvx51_opening_bank' );
		register_setting( 'sgvx51_options_group', 'sgvx51_opening_cash' );

		// Society Details
		register_setting( 'sgvx51_options_group', 'sgvx51_society_name' );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_address_line1' );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_address_line2' );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_city' );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_pincode' );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_contact' );

		// Bank Details
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_name' );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_account' );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_ifsc' );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_upi' );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_qr' );
		register_setting( 'sgvx51_options_group', 'sgvx51_storage_mode' );

        // Approval Settings (manual/auto)
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_family' );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_help' );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_vehicle' );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_facility' );
	}

	public function handle_setup_actions() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// 1. Setup Wizard (Google Sync)
		if ( isset( $_POST['sgvx51_action'] ) && check_admin_referer( 'sgvx51_setup', 'sgvx51_nonce' ) ) {
			require_once SGVX51_PLUGIN_DIR . 'includes/class-setup-wizard.php';
			if ( 'run_setup' === $_POST['sgvx51_action'] || 'run_setup_offline' === $_POST['sgvx51_action'] ) {
				$results = SGVX51_Setup_Wizard::run_setup();
				add_settings_error( 'sgvx51_messages', 'sgvx51_setup_result', is_array( $results ) ? implode('<br>', $results) : $results, 'success' );
			}
		}
	}

	/**
	 * Handle MySQL Migration via admin-post.php.
	 */
	public function handle_migrate_json() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_migrate_nonce' );

		// Capture current mode to restore it later
		$original_mode = get_option( 'sgvx51_storage_mode', 'json' );

		$db = new SGVX51_DB_Router();
		$tables = SGVX51_DB_Router::TABLES;
		$stats = array();

		// Ensure tables exist
		require_once SGVX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		SGVX51_DB_Schema::create_tables();

		// Force MySQL mode temporarily so DB_Router uses MySQL for inserts
		update_option( 'sgvx51_storage_mode', 'mysql' );

		foreach ( $tables as $table ) {
			// 0. Handle Settings Sync (meta.json -> wp_options)
			if ( $table === 'meta' ) {
				$json_file = $db->get_data_dir() . 'meta.json';
				if ( file_exists( $json_file ) ) {
					$meta_data = json_decode( file_get_contents( $json_file ), true ) ?: array();
					$count_opts = 0;
					foreach ( $meta_data as $m ) {
						if ( isset( $m['key'], $m['value'] ) && strpos( $m['key'], 'sgvx51_' ) === 0 ) {
							update_option( $m['key'], $m['value'] );
							$count_opts++;
						}
					}
					$stats[] = "settings: $count_opts synced";
				}
				continue;
			}

			$json_file = $db->get_data_dir() . $table . '.json';
			if ( ! file_exists( $json_file ) ) continue;

			$data = json_decode( file_get_contents( $json_file ), true ) ?: array();
			$count = 0;

			foreach ( $data as $row ) {
				// 1. Data Transformation
				$original_id = $row['id'] ?? null;
				
				// We now preserve original IDs (string or numeric) because the new schema 
				// uses varchar(50) for primary keys in almost all tables.
				// This preserves relationships (e.g., Poll ID in Votes).

				// Special mapping for vehicles
				if ( $table === 'vehicles' && isset($row['number']) && !isset($row['plate_no']) ) {
					$row['plate_no'] = $row['number'];
				}

				// 2. Duplication Check
				if ( $this->record_exists( $table, $row, $original_id ) ) continue;

				$res = $db->insert( $table, $row );
				if ( ! is_wp_error( $res ) ) {
					$count++;
				} else {
					// Log error for debugging if needed
					// error_log('Migration Error (' . $table . '): ' . $res->get_error_message());
				}
			}
			$stats[] = "$table: $count imported";
		}

		// Restore original mode as requested (Migration shouldn't force change permanent mode)
		update_option( 'sgvx51_storage_mode', $original_mode );

		wp_redirect( admin_url( 'admin.php?page=sgvx51-global-settings&tab=database&migration_done=1&stats=' . urlencode(implode('|', $stats)) ) );
		exit;
	}

	/**
	 * Handle Export: MySQL -> JSON (Sync Back)
	 */
	public function handle_export_json() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_export_nonce' );

		$db = new SGVX51_DB_Router();
		$tables = SGVX51_DB_Router::TABLES;
		$stats = array();

		foreach ( $tables as $table ) {
			// 0. Handle Settings Sync (wp_options -> meta.json)
			if ( $table === 'meta' ) {
				$options_to_sync = array(
					'sgvx51_society_name', 'sgvx51_society_address_line1', 'sgvx51_society_address_line2',
					'sgvx51_society_city', 'sgvx51_society_pincode', 'sgvx51_society_contact',
					'sgvx51_bank_name', 'sgvx51_bank_account', 'sgvx51_bank_ifsc', 'sgvx51_bank_upi', 'sgvx51_bank_qr',
					'sgvx51_maintenance_amount', 'sgvx51_opening_bank', 'sgvx51_opening_cash', 'sgvx51_sync_frequency'
				);
				
				$meta_data = array();
				foreach ( $options_to_sync as $opt ) {
					$meta_data[] = array(
						'key' => $opt, 
						'value' => get_option( $opt, '' ), 
						'updated_at' => current_time( 'mysql' )
					);
				}
				
				$json_file = $db->get_data_dir() . 'meta.json';
				file_put_contents( $json_file, json_encode( $meta_data, JSON_PRETTY_PRINT ) );
				
				$stats[] = "settings: " . count( $meta_data ) . " exported";
				continue;
			}

			$data = $db->get_mysql( $table );
			$count = count( $data );
			
			// Overwrite JSON file with MySQL data
			$json_file = $db->get_data_dir() . $table . '.json';
			file_put_contents( $json_file, json_encode( $data, JSON_PRETTY_PRINT ) );
			
			$stats[] = "$table: $count exported";
		}

		wp_redirect( admin_url( 'admin.php?page=sgvx51-global-settings&tab=database&export_done=1&stats=' . urlencode(implode('|', $stats)) ) );
		exit;
	}

	/**
	 * Handle Database Reset via admin-post.php.
	 */
	public function handle_reset_db() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_reset_nonce' );

		require_once SGVX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		
        if ( isset($_POST['reset_type']) && $_POST['reset_type'] === 'json' ) {
            SGVX51_DB_Schema::reset_json();
            $msg = 'reset_json_done';
        } else {
            SGVX51_DB_Schema::reset_mysql();
            $msg = 'reset_mysql_done';
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-global-settings&tab=database&' . $msg . '=1' ) );
		exit;
	}

	private function record_exists( $table, $row, $original_id = null ) {
		global $wpdb;
		$sql_table = $wpdb->prefix . 'society_governx_' . $table;
		
		// 1. Check by ID if available (and if the table uses this ID type)
		if ( $original_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE id = %s", $original_id ) );
			if ( $exists ) return true;
		}

		// 2. Check by unique combinations if ID is stripped/missing
		if ( in_array( $table, array( 'residents', 'resident_history' ) ) && isset($row['flat_no'], $row['name']) ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE flat_no = %s AND name = %s", $row['flat_no'], $row['name'] ) );
		}
		
		if ( $table === 'vehicles' && isset($row['plate_no']) ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE plate_no = %s", $row['plate_no'] ) );
		}

		if ( $table === 'flats' && isset($row['id']) ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE id = %s", $row['id'] ) );
		}

		if ( $table === 'votes' && isset($row['poll_id'], $row['flat_no'], $row['user_id']) ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE poll_id = %s AND flat_no = %s AND user_id = %d", $row['poll_id'], $row['flat_no'], $row['user_id'] ) );
		}

		return false;
	}

	/**
	 * Handle the return from Google OAuth.
	 */
	public function handle_oauth_callback() {
		if ( isset( $_GET['page'] ) && 'sgvx51-settings' === $_GET['page'] && isset( $_GET['code'] ) ) {
			// Verify nonce or capability here if possible, but Google callbacks are standard.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$code = sanitize_text_field( $_GET['code'] );
			$result = SGVX51_Google_API_Handler::exchange_code_for_token( $code );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'sgvx51_messages', 'sgvx51_auth_error', 'Auth Failed: ' . $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'sgvx51_messages', 'sgvx51_auth_success', 'Successfully connected to Google!', 'success' );
				// Redirect to remove 'code' from URL.
				wp_redirect( admin_url( 'admin.php?page=sgvx51-settings&success=1' ) );
				exit;
			}
		}
	}

	public function render_settings_page() {
		// Default to Dashboard view
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
		SGVX51_Admin_App::render_view('dashboard');
	}

	public function render_global_settings_page() {
		echo '<div id="sgvx51-app-root"></div>'; // Placeholder for JS if needed, but we use PHP views
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'settings' );
	}

    public function render_polls_page() {
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'polls' );
    }

	public function render_notifications_page() {
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'notifications' );
	}
}
