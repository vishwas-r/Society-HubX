<?php
/**
 * Class: Admin Settings
 * Renders the Plugin Settings Page.
 *
 * @package Society_GoVernX
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
		add_action( 'admin_post_sgvx51_reset_db', array( $this, 'handle_reset_db' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_sgvx51_setup_action', array( $this, 'handle_setup_actions' ) );
		add_action( 'admin_post_sgvx51_relaunch_wizard', array( $this, 'handle_relaunch_wizard' ) );
		add_action( 'admin_post_sgvx51_save_role', array( $this, 'handle_save_role' ) );
		add_action( 'admin_post_sgvx51_delete_role', array( $this, 'handle_delete_role' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup' ) );
	}

	public function maybe_redirect_to_setup() {
		if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) return;
		if ( ! current_user_can( 'manage_options' ) ) return;

		$is_setup = get_option( 'sgvx51_is_setup_complete' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page query parameter read-only check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! $is_setup && $page !== 'sgvx51-setup' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-setup' ) );
			exit;
		}
	}


	public function register_settings_page() {
		add_menu_page(
			'Society GoVernX',
			'Society GoVernX',
			'read', // RBAC checked in render functions
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
			'read', // RBAC checked in render functions
			'sgvx51-settings',
			array( $this, 'render_settings_page' )
		);

		// Activity Hub (Repurposed from Notifications)
		add_submenu_page(
			'sgvx51-settings',
			'Activity Hub',
			'Activity Hub',
			'read', // RBAC checked in render functions
			'sgvx51-activity-hub',
			array( $this, 'render_activity_hub_page' )
		);

		// Global Settings Page
		add_submenu_page(
			'sgvx51-settings',
			'Society Settings',
			'Settings',
			'read', // RBAC checked in render functions
			'sgvx51-global-settings',
			array( $this, 'render_global_settings_page' )
		);

		// Democracy (Polls) Page
		add_submenu_page(
			'sgvx51-settings',
			'Digital Democracy',
			'Democracy',
			'read', // RBAC checked in render_polls_page
			'sgvx51-polls',
			array( $this, 'render_polls_page' )
		);

		// Roles & Permissions Page
		add_submenu_page(
			'sgvx51-settings',
			'Roles & Permissions',
			'Roles & CRM',
			'manage_options', // Keep this restricted to WP Admins for safety
			'sgvx51-roles',
			array( $this, 'render_roles_page' )
		);

		// Hidden Setup Page
		add_submenu_page(
			null,
			'Society Setup',
			'Setup',
			'manage_options',
			'sgvx51-setup',
			array( $this, 'render_setup_page' )
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
		register_setting( 'sgvx51_options_group', 'sgvx51_google_client_id', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_google_client_secret', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_sync_frequency', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_maintenance_amount', array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_opening_bank', array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_opening_cash', array( 'sanitize_callback' => 'floatval' ) );

		// Society Details
		register_setting( 'sgvx51_options_group', 'sgvx51_society_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_address_line1', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_address_line2', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_city', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_pincode', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_society_contact', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Bank Details
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_account', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_ifsc', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_upi', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_bank_qr', array( 'sanitize_callback' => 'sanitize_text_field' ) );

        // Approval Settings (manual/auto)
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_family', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_help', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_vehicle', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_approval_facility', array( 'sanitize_callback' => 'sanitize_text_field' ) );

		// Log Governance
		register_setting( 'sgvx51_options_group', 'sgvx51_enable_audit', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_log_retention', array( 'sanitize_callback' => 'intval' ) );
		
		// Privacy & DPDP
		register_setting( 'sgvx51_options_group', 'sgvx51_privacy_masking', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'sgvx51_options_group', 'sgvx51_privacy_export_notice', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	}

	public function handle_setup_actions() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		// 1. Setup Wizard Steps
		if ( isset( $_POST['sgvx51_setup_step'] ) && check_admin_referer( 'sgvx51_setup_nonce' ) ) {
			require_once SGVX51_PLUGIN_DIR . 'includes/class-setup-wizard.php';
			$step = sanitize_text_field( wp_unslash( $_POST['sgvx51_setup_step'] ) );
			$results = SGVX51_Setup_Wizard::save_step( $step, wp_unslash( $_POST ) );
			
			if ( $step === 'finalize' ) {
				wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-settings&setup_complete=1' ) );
			} else {
				$next_step = 1;
				if ( $step === 'identity' ) $next_step = 2;
				elseif ( $step === 'property' ) $next_step = 3;
				elseif ( $step === 'financials' ) $next_step = 4;
				
				wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-setup&step=' . $next_step ) );
			}
			exit;
		}

		// 2. Legacy Setup Wizard (Google Sync)
		if ( isset( $_POST['sgvx51_action'] ) && check_admin_referer( 'sgvx51_setup', 'sgvx51_nonce' ) ) {
			require_once SGVX51_PLUGIN_DIR . 'includes/class-setup-wizard.php';
			if ( 'run_setup' === $_POST['sgvx51_action'] || 'run_setup_offline' === $_POST['sgvx51_action'] ) {
				// $results = SGVX51_Setup_Wizard::run_setup();
				// add_settings_error( 'sgvx51_messages', 'sgvx51_setup_result', is_array( $results ) ? implode('<br>', $results) : $results, 'success' );
			}
		}
	}


	/**
	 * Handle Database Reset via admin-post.php.
	 */
	public function handle_reset_db() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_reset_nonce' );

		require_once SGVX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		
		SGVX51_DB_Schema::reset_mysql();
		$msg = 'reset_mysql_done';

		wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-global-settings&tab=database&' . $msg . '=1' ) );
		exit;
	}

	public function handle_relaunch_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_relaunch_nonce' );

		update_option( 'sgvx51_is_setup_complete', false );
		wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-setup' ) );
		exit;
	}

	public function handle_save_role() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_role_nonce' );

		$rbac = new SGVX51_RBAC_Manager();
		$role_id = isset( $_POST['role_id'] ) ? sanitize_text_field( wp_unslash( $_POST['role_id'] ) ) : '';
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$capabilities = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['capabilities'] ) ) : array();

		// For new roles, generate a slug-like ID
		if ( empty( $role_id ) ) {
			$role_id = sanitize_title( $name );
		}

		$rbac->save_role( $role_id, $name, $capabilities );

		wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-roles&success=role_saved' ) );
		exit;
	}

	public function handle_delete_role() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		check_admin_referer( 'sgvx51_role_nonce' );

		$role_id = isset( $_POST['role_id'] ) ? sanitize_text_field( wp_unslash( $_POST['role_id'] ) ) : '';
		$rbac = new SGVX51_RBAC_Manager();
		$rbac->delete_role( $role_id );

		wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-roles&success=role_deleted' ) );
		exit;
	}

	private function record_exists( $table, $row, $original_id = null ) {
		global $wpdb;
		$sql_table = $wpdb->prefix . 'society_governx_' . $table;
		
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema check query requires dynamic table name.
		// 1. Check by ID if available (and if the table uses this ID type)
		if ( $original_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $sql_table WHERE id = %s", $original_id ) );
			if ( $exists ) {
				return true;
			}
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
		// phpcs:enable

		return false;
	}

	/**
	 * Handle the return from Google OAuth.
	 */
	public function handle_oauth_callback() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External Google OAuth callback check.
		if ( isset( $_GET['page'] ) && 'sgvx51-settings' === sanitize_key( wp_unslash( $_GET['page'] ) ) && isset( $_GET['code'] ) ) {
			// Verify nonce or capability here if possible, but Google callbacks are standard.
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External Google OAuth callback.
			$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$result = SGVX51_Google_API_Handler::exchange_code_for_token( $code );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'sgvx51_messages', 'sgvx51_auth_error', 'Auth Failed: ' . $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'sgvx51_messages', 'sgvx51_auth_success', 'Successfully connected to Google!', 'success' );
				// Redirect to remove 'code' from URL.
				wp_safe_redirect( admin_url( 'admin.php?page=sgvx51-settings&success=1' ) );
				exit;
			}
		}
	}

	public function render_settings_page() {
		$rbac = new SGVX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'dashboard_view' ) ) {
			wp_die( 'You do not have permission to view the Society Dashboard.' );
		}
		// Default to Dashboard view
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
		SGVX51_Admin_App::render_view('dashboard');
	}

	public function render_global_settings_page() {
		$rbac = new SGVX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) ) {
			wp_die( 'You do not have permission to manage Society Settings.' );
		}
		echo '<div id="sgvx51-app-root"></div>'; // Placeholder for JS if needed, but we use PHP views
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'settings' );
	}

    public function render_polls_page() {
        $rbac = new SGVX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'polls_view' ) ) {
            wp_die( 'You do not have permission to access Digital Democracy.' );
        }
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'polls' );
    }

	public function render_activity_hub_page() {
		$rbac = new SGVX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'dashboard_view' ) ) {
			wp_die( 'You do not have permission to access the Activity Hub.' );
		}
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'activity-hub' );
	}

	public function render_roles_page() {
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'roles' );
	}

	public function render_setup_page() {
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
        SGVX51_Admin_App::render_view( 'setup' );
	}
}
