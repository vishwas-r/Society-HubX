<?php
/*
 * Plugin Name:       SocietyNestX – Society Management Portal
 * Plugin URI:        https://github.com/vishwas-r/Society-GovernX
 * Description:       A premium, comprehensive society management system featuring automated maintenance, facility bookings, digital document vault, and resident community engagement.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Vishwas R
 * Author URI:        https://www.vishwas.me
 * Text Domain:       society-nestx
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'SNESTX51_VERSION', '1.0.3' );
define( 'SNESTX51_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNESTX51_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SNESTX51_PREFIX', 'SNESTX51' );

/**
 * Main Plugin Class.
 */
final class Society_NestX {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Database Router Instance.
	 *
	 * @var SNESTX51_DB_Router
	 */
	public $db = null;

	/**
	 * Notification Dispatcher Instance.
	 *
	 * @var SNESTX51_Notification_Dispatcher
	 */
	public $notifications = null;

	/**
	 * RBAC Manager Instance.
	 *
	 * @var SNESTX51_RBAC_Manager
	 */
	public $rbac = null;

	/**
	 * Privacy Manager Instance.
	 *
	 * @var SNESTX51_Privacy_Manager
	 */
	public $privacy = null;

	/**
	 * REST Manager Instance.
	 *
	 * @var SNESTX51_REST_Manager
	 */
	public $rest = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		self::$instance = $this;
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core Classes
		require_once SNESTX51_PLUGIN_DIR . 'includes/interface-module.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-google-api-handler.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-db-router.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-drive-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-media-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-log-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-background-worker.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-privacy-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rest-manager.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-data-migrator.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-payment-service.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';
		
		// Notifications
		require_once SNESTX51_PLUGIN_DIR . 'includes/notifications/interface-notification-provider.php';
		require_once SNESTX51_PLUGIN_DIR . 'includes/notifications/class-notification-dispatcher.php';
		
		require_once SNESTX51_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once SNESTX51_PLUGIN_DIR . 'admin/class-admin-app.php';
		require_once SNESTX51_PLUGIN_DIR . 'admin/class-admin-requests.php';
		require_once SNESTX51_PLUGIN_DIR . 'admin/class-ajax-handler.php';
		require_once SNESTX51_PLUGIN_DIR . 'admin/class-admin-ui-helper.php';
		
		// Initialize
		$this->db = new SNESTX51_DB_Router();
		$this->notifications = new SNESTX51_Notification_Dispatcher( $this->db );
		new SNESTX51_Log_Manager( $this->db );
		new SNESTX51_Background_Worker();
		$this->rbac = new SNESTX51_RBAC_Manager();
		$this->privacy = new SNESTX51_Privacy_Manager();
		$this->rest    = new SNESTX51_REST_Manager();

		// Version Check / Database Initialization
		$this->maybe_update_db();

		// Initialize Admin Settings
		if ( is_admin() ) {
			new SNESTX51_Admin_Settings();
			new SNESTX51_Admin_Requests();
			new SNESTX51_AJAX_Handler();
			
			// Data Portability (Export/Import)
			require_once SNESTX51_PLUGIN_DIR . 'admin/class-data-portability.php';
			new SNESTX51_Data_Portability();
		}
		
						
		// Load Modules
		require_once SNESTX51_PLUGIN_DIR . 'modules/flats/class-flat-manager.php'; // New Master Data
		new SNESTX51_Flat_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/residents/class-resident-manager.php';
		new SNESTX51_Resident_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/vehicles/class-vehicle-manager.php';
		new SNESTX51_Vehicle_Manager();
		
		require_once SNESTX51_PLUGIN_DIR . 'modules/documents/class-document-manager.php';
		new SNESTX51_Document_Manager();
		
		require_once SNESTX51_PLUGIN_DIR . 'modules/facilities/class-facility-manager.php';
		new SNESTX51_Facility_Manager();
		
		require_once SNESTX51_PLUGIN_DIR . 'modules/finance/class-expense-manager.php';
		new SNESTX51_Expense_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
		new SNESTX51_Account_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/assets/class-asset-manager.php';
		new SNESTX51_Asset_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/notices/class-notice-board.php';
		new SNESTX51_Notice_Board();

		require_once SNESTX51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		new SNESTX51_Ledger_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/democracy/class-poll-manager.php';
		new SNESTX51_Poll_Manager();

        require_once SNESTX51_PLUGIN_DIR . 'modules/staff/class-staff-manager.php';
		new SNESTX51_Staff_Manager();

        require_once SNESTX51_PLUGIN_DIR . 'modules/class-general-request-manager.php';
        new SNESTX51_General_Request_Manager();

		require_once SNESTX51_PLUGIN_DIR . 'modules/rules/class-rule-manager.php';
		new SNESTX51_Rule_Manager();

		// Frontend
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-frontend-dashboard.php';
		new SNESTX51_Frontend_Dashboard();

		// Register// 2. Initialization & Activation
		register_activation_hook( __FILE__, array( 'SNESTX51_DB_Schema', 'create_tables' ) );
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ), 9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		

		// 3. User Sync Filters
		add_filter( 'theme_page_templates', array( $this, 'register_page_templates' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_page_template' ) );

		// 4. Access Control
		add_action( 'admin_init', array( $this, 'redirect_residents_from_admin' ) );

		// 5. Version Check / Migration
		add_action( 'admin_init', array( $this, 'maybe_update_db' ) );

		// 6. Maintenance Tasks
		if ( function_exists('as_next_scheduled_action') && !as_next_scheduled_action('SNESTX51_daily_log_purge') ) {
			as_schedule_recurring_action( strtotime('midnight'), DAY_IN_SECONDS, 'SNESTX51_daily_log_purge' );
		}
	}

	/**
	 * Run DB Update if version changes.
	 */
	public function maybe_update_db() {
		if ( get_option( 'SNESTX51_version' ) !== SNESTX51_VERSION ) {
			// Ensure tables are created first
			SNESTX51_DB_Schema::create_tables();
			update_option( 'SNESTX51_version', SNESTX51_VERSION );
		}

		// Ensure data migration is triggered after tables exist
		if ( is_admin() && get_option( 'SNESTX51_storage_migrated' ) !== SNESTX51_VERSION ) {
			// Double check dependencies are loaded
			if ( class_exists( 'SNESTX51_Data_Migrator' ) ) {
				SNESTX51_Data_Migrator::run_all();
				update_option( 'SNESTX51_storage_migrated', SNESTX51_VERSION );
			}
		}
	}

	/**
	 * Localization and setup.
	 */
	public function on_plugins_loaded() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Textdomain load implementation is standard fallback.
		load_plugin_textdomain( 'society-nestx', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
		// Redirect after Login
		add_filter( 'login_redirect', array( $this, 'custom_login_redirect' ), 10, 3 );
	}

	/**
	 * Custom Login Redirect.
	 */
	public function custom_login_redirect( $redirect_to, $request, $user ) {
		if ( ! $user || is_wp_error( $user ) ) return $redirect_to;

		// 1. Administrators go to settings
		if ( in_array( 'administrator', (array)$user->roles ) ) {
			return admin_url( 'admin.php?page=snestx51-settings' );
		}

		// 2. Management Roles (Secretary, Treasurer, etc.) go to their respective admin tools
		if ( isset( $this->rbac ) ) {
			// If they have any management capability, let them stay in admin
			$has_mgmt = false;
			$caps = array( 'dashboard_view', 'finance_manage', 'residents_manage', 'polls_manage' );
			foreach ( $caps as $cap ) {
				if ( $this->rbac->has_capability( $user->ID, $cap ) ) {
					$has_mgmt = true;
					break;
				}
			}

			if ( $has_mgmt ) {
				return admin_url( 'admin.php?page=snestx51-settings' );
			}
		}

		// 3. Normal Residents go to frontend dashboard
		$page = get_page_by_path( 'resident-dashboard' );
		if ( $page ) {
			return get_permalink( $page->ID );
		}
		
		return home_url( '/resident-dashboard/' );
	}

	/**
	 * Redirect Residents from Admin.
	 */
	public function redirect_residents_from_admin() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( ! is_user_logged_in() ) return;

		$user_id = get_current_user_id();

		// 1. Administrators are exempt
		if ( current_user_can( 'administrator' ) ) return;

		// 2. Management Roles are exempt
		if ( isset( $this->rbac ) ) {
			$caps = array( 'dashboard_view', 'finance_manage', 'residents_manage', 'polls_manage' );
			foreach ( $caps as $cap ) {
				if ( $this->rbac->has_capability( $user_id, $cap ) ) {
					return; // Allow access
				}
			}
		}

		// 3. Regular Residents/Subscribers are redirected
		if ( current_user_can( 'subscriber' ) || current_user_can( 'resident' ) ) {
			wp_safe_redirect( home_url( '/resident-dashboard/' ) );
			exit;
		}
	}

	/**
	 * Enqueue Admin Assets.
	 */
	public function enqueue_admin_assets() {
        // 0. Core Utilities (Shared)
        wp_enqueue_script( 'snestx51-core', SNESTX51_PLUGIN_URL . 'assets/js/snestx-core.js', array('jquery'), SNESTX51_VERSION, true );

        // Only load on our plugin pages to avoid breaking global WP Admin
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page query parameter read-only check.
        $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        // Only load on our plugin pages to avoid breaking global WP Admin
        if ( empty($page) || strpos($page, 'snestx51') === false ) {
            return;
        }

        // 0. WP Reset Shim (Load first to clear the path)
        //wp_enqueue_style( 'SNESTX51_wp_reset_shim', SNESTX51_PLUGIN_URL . 'assets/css/wp-reset-shim.css', array(), SNESTX51_VERSION );

		// 1. Google Fonts (Inter) - Local
        wp_enqueue_style( 'SNESTX51_fonts', SNESTX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), SNESTX51_VERSION );

		// 2. Bootstrap 5 (Local) - Load late to naturally override WP styles
		wp_enqueue_style( 'SNESTX51_bootstrap_css', SNESTX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_script( 'SNESTX51_bootstrap_js', SNESTX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.0', true );

        // 3. Bootstrap Icons (Local)
        wp_enqueue_style( 'SNESTX51_bootstrap_icons', SNESTX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('SNESTX51_bootstrap_css'), '1.11.3' );

        // 4. Custom Admin Styles (Final Overrides)
		wp_enqueue_style( 'SNESTX51_admin_layout', SNESTX51_PLUGIN_URL . 'assets/css/admin-layout.css', array('SNESTX51_bootstrap_css', 'SNESTX51_bootstrap_icons'), SNESTX51_VERSION );
		wp_enqueue_style( 'SNESTX51_admin_premium', SNESTX51_PLUGIN_URL . 'assets/css/admin-premium.css', array('SNESTX51_bootstrap_css', 'SNESTX51_admin_layout'), SNESTX51_VERSION );

		// 5. Receipt Styling
		wp_enqueue_style( 'SNESTX51_receipt_css', SNESTX51_PLUGIN_URL . 'assets/css/receipt.css', array(), SNESTX51_VERSION );

		// Core utilities
        wp_enqueue_script( 'snestx51-core', SNESTX51_PLUGIN_URL . 'assets/js/snestx-core.js', array('jquery'), SNESTX51_VERSION, true );
        
        // Toast notification system (extracted from core)
        wp_enqueue_script( 'snestx51-toast', SNESTX51_PLUGIN_URL . 'assets/js/snestx-toast.js', array('jquery'), SNESTX51_VERSION, true );
        
        // Centralized AJAX handler with loading states
        wp_enqueue_script( 'snestx51-ajax', SNESTX51_PLUGIN_URL . 'assets/js/snestx-ajax.js', array('jquery', 'snestx51-toast'), SNESTX51_VERSION, true );

		// Google API (placeholder)
		if ( $page === 'snestx51-google-drive' ) {
			$snestx51_Google_API_Handler = new SNESTX51_Google_API_Handler();
			$snestx51_Google_API_Handler->enqueue_google_api_scripts();
		}

		// Inline setup for ajaxurl
		wp_add_inline_script( 'snestx51-core', "
            var ajaxurl = '" . esc_url_raw( admin_url( 'admin-ajax.php' ) ) . "';
            var snestx51_nonce = '" . esc_js( wp_create_nonce( 'snestx51_admin_nonce' ) ) . "';
            var snestx51_admin_nonce = '" . esc_js( wp_create_nonce( 'snestx51_admin_nonce' ) ) . "';
        " );

		// Libraries (Chart.js for charts, html2canvas for screenshots)
        wp_enqueue_script( 'snestx51-html2canvas', SNESTX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
        wp_enqueue_script( 'snestx51-fuse', SNESTX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
		wp_enqueue_script( 'snestx51-chartjs', SNESTX51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.4.3', true );
        wp_enqueue_script( 'snestx51-search-init', SNESTX51_PLUGIN_URL . 'assets/js/snestx-search-init.js', array('snestx51-fuse'), SNESTX51_VERSION, true );
        wp_enqueue_script( 'snestx51-admin-app', SNESTX51_PLUGIN_URL . 'assets/js/admin-app.js', array('jquery', 'snestx51-core', 'snestx51-toast', 'snestx51-ajax', 'snestx51-html2canvas', 'snestx51-search-init'), SNESTX51_VERSION, true );

        // Standard Nonces for global actions
        wp_localize_script( 'snestx51-admin-app', 'snestx51_vars', array(
            'request_nonce' => wp_create_nonce( 'snestx51_request_action' )
        ));
        // Add global JS variable for convenience
        wp_add_inline_script( 'snestx51-admin-app', 'var snestx51RequestNonce = "' . wp_create_nonce( 'snestx51_request_action' ) . '";', 'before' );

        // Residents View Specific JS
        if ( $page === 'snestx51-residents' ) {
            wp_enqueue_script( 'snestx51-residents-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-residents.js', array('jquery'), time(), true );
            
            $residents = $this->db->get('residents');
            $flat_owners = array();
            if(!empty($residents)) {
                foreach($residents as $r) {
                    if(isset($r['type']) && strtolower($r['type']) === 'owner') {
                        $flat_owners[$r['flat_no']] = array('name' => $r['name'], 'id' => $r['id'] ?? '');
                    }
                }
            }

            // Note: Nonces are now fetched dynamically via AJAX in snestx-residents.js
            // wp_localize_script is no longer needed since we fetch config at runtime
        }

		// Facilities View Specific JS
		if ( $page === 'snestx51-facilities' ) {
			wp_enqueue_script( 'snestx51-facilities-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-facilities.js', array('jquery', 'snestx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

        // Flats View Specific JS
        if ( $page === 'snestx51-flats' ) {
            wp_enqueue_script( 'snestx51-flats-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-flats.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Vehicles View Specific JS
        if ( $page === 'snestx51-vehicles' ) {
            wp_enqueue_script( 'snestx51-vehicles-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-vehicles.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Staff View Specific JS
        if ( $page === 'snestx51-staff' ) {
            wp_enqueue_script( 'snestx51-staff-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-staff.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

		// Notices View Specific JS
		if ( $page === 'snestx51-notices' ) {
			wp_enqueue_script( 'snestx51-notices-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-notices.js', array('jquery', 'snestx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Documents View Specific JS
		if ( $page === 'snestx51-documents' ) {
			wp_enqueue_script( 'snestx51-documents-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-documents.js', array('jquery', 'snestx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Expenses View Specific JS
		if ( $page === 'snestx51-expenses' ) {
			wp_enqueue_script( 'snestx51-expenses-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-expenses.js', array('jquery', 'snestx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Accounts View Specific JS (Invoices & Ledger)
		if ( $page === 'snestx51-accounts' ) {
			wp_enqueue_script( 'snestx51-accounts-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-accounts.js', array('jquery', 'snestx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Notifications View Specific JS (Now also on Settings for Communication tab)
		if ( in_array($page, ['snestx51-activity-hub', 'snestx51-global-settings']) ) {
			wp_enqueue_script( 'snestx51-notifications-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-notifications.js', array('jquery', 'snestx51-admin-app'), time(), true );
		}
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
		// Bootstrap 5 & jQuery (if needed, but we use Tailwind mostly - NOW MIGRATED TO BOOTSTRAP)
        wp_enqueue_style( 'snestx51-bootstrap', SNESTX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.3' );
		wp_enqueue_script( 'snestx51-bootstrap', SNESTX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.3', true );

        // 0. Bootstrap Icons (Local)
        wp_enqueue_style( 'snestx51-bootstrap-icons', SNESTX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('snestx51-bootstrap'), '1.11.3' );

        // Custom Frontend CSS (Tailwind Replacement)
        wp_enqueue_style( 'snestx51-frontend-css', SNESTX51_PLUGIN_URL . 'assets/css/snestx-frontend.css', array('snestx51-bootstrap', 'snestx51-fonts', 'snestx51-bootstrap-icons'), SNESTX51_VERSION );
        
        // Receipt CSS
        wp_enqueue_style( 'snestx51-receipt-css', SNESTX51_PLUGIN_URL . 'assets/css/receipt.css', array('snestx51-bootstrap'), SNESTX51_VERSION );
        
        // Fonts
        wp_enqueue_style( 'snestx51-fonts', SNESTX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 1. Chart.js for Charts (Local)
		wp_enqueue_script( 'snestx51-chartjs', SNESTX51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.4.3', true );

		// HTML2Canvas for receipt screenshots
		wp_enqueue_script( 'snestx51-html2canvas', SNESTX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );

        // Search functionality
        wp_enqueue_script( 'snestx51-fuse', SNESTX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
        wp_enqueue_script( 'snestx51-search-init', SNESTX51_PLUGIN_URL . 'assets/js/snestx-search-init.js', array('snestx51-fuse'), time(), true );

        // Core utilities
        wp_enqueue_script( 'snestx51-core', SNESTX51_PLUGIN_URL . 'assets/js/snestx-core.js', array('jquery'), SNESTX51_VERSION, true );
        
        // Toast notification system
        wp_enqueue_script( 'snestx51-toast', SNESTX51_PLUGIN_URL . 'assets/js/snestx-toast.js', array('jquery'), SNESTX51_VERSION, true );
        
        // Centralized AJAX handler
        wp_enqueue_script( 'snestx51-ajax', SNESTX51_PLUGIN_URL . 'assets/js/snestx-ajax.js', array('jquery', 'snestx51-toast'), SNESTX51_VERSION, true );

        // Main dashboard script (depends on core, toast, ajax)
        wp_enqueue_script( 'snestx51-dashboard-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-dashboard.js', array('jquery', 'snestx51-core', 'snestx51-toast', 'snestx51-ajax', 'snestx51-bootstrap', 'snestx51-chartjs', 'snestx51-search-init'), SNESTX51_VERSION, true );
        
        // Only load module-specific scripts if user is logged in
        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'snestx51-documents-js', SNESTX51_PLUGIN_URL . 'assets/js/snestx-documents.js', array('jquery', 'snestx51-core', 'snestx51-toast', 'snestx51-ajax', 'snestx51-bootstrap'), SNESTX51_VERSION, true );
        }

		// Localize AJAX URL for frontend (needed for resident login)
		wp_localize_script( 'snestx51-bootstrap', 'snestx51_frontend', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'version' => SNESTX51_VERSION
		) );
		// Ensure global ajaxurl fallback for legacy fetch in resident-login.php
		wp_add_inline_script( 'snestx51-bootstrap', 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";', 'before' );
	}

	/**
	 * Register Custom Page Templates.
	 */
	public function register_page_templates( $templates, $theme = null, $post = null, $post_type = null ) {
		// Key must look like a file to be selected
		$templates['society-app.php'] = 'Society App (Full Width)';
		return $templates;
	}

	/**
	 * Load Custom Page Templates.
	 * Logic:
	 * 1. If explicit template is selected in dropdown.
	 * 2. OR if the page contains string '[society_nestx_dashboard]' - AUTO APPLY.
	 */
	public function load_page_template( $template ) {
		global $post;
		$target = get_page_template_slug();

		// 1. Check if manually selected
		if ( $target === 'society-app.php' ) {
			return SNESTX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		// 2. Auto-detect Shortcode (Fallback if user can't select template)
		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'society_nestx_dashboard' ) ) {
			 return SNESTX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		return $template;
	}
}

/**
 * Initialize the plugin.
 */
function SNESTX51_init() {
	return Society_NestX::get_instance();
}
add_action( 'plugins_loaded', 'SNESTX51_init' );
