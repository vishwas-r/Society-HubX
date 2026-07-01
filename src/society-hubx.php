<?php
/*
 * Plugin Name:       Society HubX – Society Management Portal
 * Plugin URI:        https://github.com/vishwas-r/Society-GovernX
 * Description:       A premium, comprehensive society management system featuring automated maintenance, facility bookings, digital document vault, and resident community engagement.
 * Version:           1.0.4
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Vishwas R
 * Author URI:        https://www.vishwas.me
 * Text Domain:       society-hubx
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'SHUBX51_VERSION', '1.0.4' );
define( 'SHUBX51_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHUBX51_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHUBX51_PREFIX', 'SHUBX51' );

/**
 * Main Plugin Class.
 */
final class Society_HubX {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Database Router Instance.
	 *
	 * @var SHUBX51_DB_Router
	 */
	public $db = null;

	/**
	 * Notification Dispatcher Instance.
	 *
	 * @var SHUBX51_Notification_Dispatcher
	 */
	public $notifications = null;

	/**
	 * RBAC Manager Instance.
	 *
	 * @var SHUBX51_RBAC_Manager
	 */
	public $rbac = null;

	/**
	 * Privacy Manager Instance.
	 *
	 * @var SHUBX51_Privacy_Manager
	 */
	public $privacy = null;

	/**
	 * REST Manager Instance.
	 *
	 * @var SHUBX51_REST_Manager
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
		require_once SHUBX51_PLUGIN_DIR . 'includes/interface-module.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-google-api-handler.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-db-router.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-drive-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-media-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-log-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-background-worker.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-privacy-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-rest-manager.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-data-migrator.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-payment-service.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';
		
		// Notifications
		require_once SHUBX51_PLUGIN_DIR . 'includes/notifications/interface-notification-provider.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/notifications/class-notification-dispatcher.php';
		
		require_once SHUBX51_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once SHUBX51_PLUGIN_DIR . 'admin/class-admin-app.php';
		require_once SHUBX51_PLUGIN_DIR . 'admin/class-admin-requests.php';
		require_once SHUBX51_PLUGIN_DIR . 'admin/class-ajax-handler.php';
		require_once SHUBX51_PLUGIN_DIR . 'admin/class-admin-ui-helper.php';
		
		// Initialize
		$this->db = new SHUBX51_DB_Router();
		$this->notifications = new SHUBX51_Notification_Dispatcher( $this->db );
		new SHUBX51_Log_Manager( $this->db );
		new SHUBX51_Background_Worker();
		$this->rbac = new SHUBX51_RBAC_Manager();
		$this->privacy = new SHUBX51_Privacy_Manager();
		$this->rest    = new SHUBX51_REST_Manager();

		// Version Check / Database Initialization
		$this->maybe_update_db();

		// Initialize Admin Settings
		if ( is_admin() ) {
			new SHUBX51_Admin_Settings();
			new SHUBX51_Admin_Requests();
			new SHUBX51_AJAX_Handler();
			
			// Data Portability (Export/Import)
			require_once SHUBX51_PLUGIN_DIR . 'admin/class-data-portability.php';
			new SHUBX51_Data_Portability();
		}
		
						
		// Load Modules
		require_once SHUBX51_PLUGIN_DIR . 'modules/flats/class-flat-manager.php'; // New Master Data
		new SHUBX51_Flat_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/residents/class-resident-manager.php';
		new SHUBX51_Resident_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/vehicles/class-vehicle-manager.php';
		new SHUBX51_Vehicle_Manager();
		
		require_once SHUBX51_PLUGIN_DIR . 'modules/documents/class-document-manager.php';
		new SHUBX51_Document_Manager();
		
		require_once SHUBX51_PLUGIN_DIR . 'modules/facilities/class-facility-manager.php';
		new SHUBX51_Facility_Manager();
		
		require_once SHUBX51_PLUGIN_DIR . 'modules/finance/class-expense-manager.php';
		new SHUBX51_Expense_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
		new SHUBX51_Account_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/assets/class-asset-manager.php';
		new SHUBX51_Asset_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/notices/class-notice-board.php';
		new SHUBX51_Notice_Board();

		require_once SHUBX51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		new SHUBX51_Ledger_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/democracy/class-poll-manager.php';
		new SHUBX51_Poll_Manager();

        require_once SHUBX51_PLUGIN_DIR . 'modules/staff/class-staff-manager.php';
		new SHUBX51_Staff_Manager();

        require_once SHUBX51_PLUGIN_DIR . 'modules/class-general-request-manager.php';
        new SHUBX51_General_Request_Manager();

		require_once SHUBX51_PLUGIN_DIR . 'modules/rules/class-rule-manager.php';
		new SHUBX51_Rule_Manager();

		// Frontend
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-frontend-dashboard.php';
		new SHUBX51_Frontend_Dashboard();

		// Register// 2. Initialization & Activation
		register_activation_hook( __FILE__, array( 'SHUBX51_DB_Schema', 'create_tables' ) );
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
		if ( function_exists('as_next_scheduled_action') && !as_next_scheduled_action('shubx51_daily_log_purge') ) {
			as_schedule_recurring_action( strtotime('midnight'), DAY_IN_SECONDS, 'shubx51_daily_log_purge' );
		}

		// 7. Session Bootstrap (for flat-switcher state)
		add_action( 'init', function() {
			if ( ! session_id() && ! headers_sent() ) {
				session_start();
			}
		}, 1 );
	}

	/**
	 * Run DB Update if version changes.
	 */
	public function maybe_update_db() {
		if ( get_option( 'shubx51_version' ) !== SHUBX51_VERSION ) {
			// Ensure tables are created first
			SHUBX51_DB_Schema::create_tables();
			update_option( 'shubx51_version', SHUBX51_VERSION );
		}

		// Ensure data migration is triggered after tables exist
		if ( is_admin() && get_option( 'shubx51_storage_migrated' ) !== SHUBX51_VERSION ) {
			// Double check dependencies are loaded
			if ( class_exists( 'SHUBX51_Data_Migrator' ) ) {
				SHUBX51_Data_Migrator::run_all();
				update_option( 'shubx51_storage_migrated', SHUBX51_VERSION );
			}
		}

		// Seed resident_flat_map for any existing residents not yet seeded
		add_action( 'admin_init', array( $this, 'seed_resident_flat_map' ), 20 );
	}

	/**
	 * Localization and setup.
	 */
	public function on_plugins_loaded() {
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
			return admin_url( 'admin.php?page=shubx51-settings' );
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
				return admin_url( 'admin.php?page=shubx51-settings' );
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
        wp_enqueue_script( 'shubx51-core', SHUBX51_PLUGIN_URL . 'assets/js/shubx-core.js', array('jquery'), SHUBX51_VERSION, true );

        // Only load on our plugin pages to avoid breaking global WP Admin
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page query parameter read-only check.
        $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        // Only load on our plugin pages to avoid breaking global WP Admin
        if ( empty($page) || strpos($page, 'shubx51') === false ) {
            return;
        }

        // 0. WP Reset Shim (Load first to clear the path)
        //wp_enqueue_style( 'shubx51_wp_reset_shim', SHUBX51_PLUGIN_URL . 'assets/css/wp-reset-shim.css', array(), SHUBX51_VERSION );

		// 1. Google Fonts (Inter) - Local
        wp_enqueue_style( 'shubx51_fonts', SHUBX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), SHUBX51_VERSION );

		// 2. Bootstrap 5 (Local) - Load late to naturally override WP styles
		wp_enqueue_style( 'shubx51_bootstrap_css', SHUBX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_script( 'shubx51_bootstrap_js', SHUBX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.0', true );

        // 3. Bootstrap Icons (Local)
        wp_enqueue_style( 'shubx51_bootstrap_icons', SHUBX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('shubx51_bootstrap_css'), '1.11.3' );

        // 4. Custom Admin Styles (Final Overrides)
		wp_enqueue_style( 'shubx51_admin_layout', SHUBX51_PLUGIN_URL . 'assets/css/admin-layout.css', array('shubx51_bootstrap_css', 'shubx51_bootstrap_icons'), SHUBX51_VERSION );
		wp_enqueue_style( 'shubx51_admin_premium', SHUBX51_PLUGIN_URL . 'assets/css/admin-premium.css', array('shubx51_bootstrap_css', 'shubx51_admin_layout'), SHUBX51_VERSION );

		// 5. Receipt Styling
		wp_enqueue_style( 'shubx51_receipt_css', SHUBX51_PLUGIN_URL . 'assets/css/receipt.css', array(), SHUBX51_VERSION );

		// Core utilities
        wp_enqueue_script( 'shubx51-core', SHUBX51_PLUGIN_URL . 'assets/js/shubx-core.js', array('jquery'), SHUBX51_VERSION, true );
        
        // Toast notification system (extracted from core)
        wp_enqueue_script( 'shubx51-toast', SHUBX51_PLUGIN_URL . 'assets/js/shubx-toast.js', array('jquery'), SHUBX51_VERSION, true );
        
        // Centralized AJAX handler with loading states
        wp_enqueue_script( 'shubx51-ajax', SHUBX51_PLUGIN_URL . 'assets/js/shubx-ajax.js', array('jquery', 'shubx51-toast'), SHUBX51_VERSION, true );

		// Google API (placeholder)
		if ( $page === 'shubx51-google-drive' ) {
			$shubx51_Google_API_Handler = new SHUBX51_Google_API_Handler();
			$shubx51_Google_API_Handler->enqueue_google_api_scripts();
		}

		// Inline setup for ajaxurl
		wp_add_inline_script( 'shubx51-core', "
            var ajaxurl = '" . esc_url( admin_url( 'admin-ajax.php' ) ) . "';
            var shubx51_nonce = '" . esc_js( wp_create_nonce( 'shubx51_admin_nonce' ) ) . "';
            var shubx51_admin_nonce = '" . esc_js( wp_create_nonce( 'shubx51_admin_nonce' ) ) . "';
        " );

		// Libraries (Chart.js for charts, html2canvas for screenshots)
        wp_enqueue_script( 'shubx51-html2canvas', SHUBX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
        wp_enqueue_script( 'shubx51-fuse', SHUBX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
		wp_enqueue_script( 'shubx51-chartjs', SHUBX51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.4.3', true );
        wp_enqueue_script( 'shubx51-search-init', SHUBX51_PLUGIN_URL . 'assets/js/shubx-search-init.js', array('shubx51-fuse'), SHUBX51_VERSION, true );
        wp_enqueue_script( 'shubx51-admin-app', SHUBX51_PLUGIN_URL . 'assets/js/admin-app.js', array('jquery', 'shubx51-core', 'shubx51-toast', 'shubx51-ajax', 'shubx51-html2canvas', 'shubx51-search-init'), SHUBX51_VERSION, true );

        // Standard Nonces for global actions
        wp_localize_script( 'shubx51-admin-app', 'shubx51_vars', array(
            'request_nonce' => wp_create_nonce( 'shubx51_request_action' )
        ));
        // Add global JS variable for convenience
        wp_add_inline_script( 'shubx51-admin-app', 'var shubx51RequestNonce = "' . wp_create_nonce( 'shubx51_request_action' ) . '";', 'before' );

        // Residents View Specific JS
        if ( $page === 'shubx51-residents' ) {
            wp_enqueue_script( 'shubx51-residents-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-residents.js', array('jquery'), time(), true );
            
            $residents = $this->db->get('residents');
            $flat_owners = array();
            if(!empty($residents)) {
                foreach($residents as $r) {
                    if(isset($r['type']) && strtolower($r['type']) === 'owner') {
                        $flat_owners[$r['flat_no']] = array('name' => $r['name'], 'id' => $r['id'] ?? '');
                    }
                }
            }

            // Note: Nonces are now fetched dynamically via AJAX in shubx-residents.js
            // wp_localize_script is no longer needed since we fetch config at runtime
        }

		// Facilities View Specific JS
		if ( $page === 'shubx51-facilities' ) {
			wp_enqueue_script( 'shubx51-facilities-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-facilities.js', array('jquery', 'shubx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

        // Flats View Specific JS
        if ( $page === 'shubx51-flats' ) {
            wp_enqueue_script( 'shubx51-flats-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-flats.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Vehicles View Specific JS
        if ( $page === 'shubx51-vehicles' ) {
            wp_enqueue_script( 'shubx51-vehicles-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-vehicles.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Staff View Specific JS
        if ( $page === 'shubx51-staff' ) {
            wp_enqueue_script( 'shubx51-staff-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-staff.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

		// Rules View Specific JS
		if ( $page === 'shubx51-rules' ) {
			wp_enqueue_script( 'shubx51-rules-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-rules.js', array('jquery', 'shubx51-admin-app'), time(), true );
		}

		// Notices View Specific JS
		if ( $page === 'shubx51-notices' ) {
			wp_enqueue_script( 'shubx51-notices-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-notices.js', array('jquery', 'shubx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Documents View Specific JS
		if ( $page === 'shubx51-documents' ) {
			wp_enqueue_script( 'shubx51-documents-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-documents.js', array('jquery', 'shubx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Expenses View Specific JS
		if ( $page === 'shubx51-expenses' ) {
			wp_enqueue_script( 'shubx51-expenses-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-expenses.js', array('jquery', 'shubx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Accounts View Specific JS (Invoices & Ledger)
		if ( $page === 'shubx51-accounts' ) {
			wp_enqueue_script( 'shubx51-accounts-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-accounts.js', array('jquery', 'shubx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Notifications View Specific JS (Now also on Settings for Communication tab)
		if ( in_array($page, ['shubx51-activity-hub', 'shubx51-global-settings']) ) {
			wp_enqueue_script( 'shubx51-notifications-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-notifications.js', array('jquery', 'shubx51-admin-app'), time(), true );
		}
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
		// Bootstrap 5 & jQuery (if needed, but we use Tailwind mostly - NOW MIGRATED TO BOOTSTRAP)
        wp_enqueue_style( 'shubx51-bootstrap', SHUBX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.3' );
		wp_enqueue_script( 'shubx51-bootstrap', SHUBX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.3', true );

        // 0. Bootstrap Icons (Local)
        wp_enqueue_style( 'shubx51-bootstrap-icons', SHUBX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('shubx51-bootstrap'), '1.11.3' );

        // Custom Frontend CSS (Tailwind Replacement)
        wp_enqueue_style( 'shubx51-frontend-css', SHUBX51_PLUGIN_URL . 'assets/css/shubx-frontend.css', array('shubx51-bootstrap', 'shubx51-fonts', 'shubx51-bootstrap-icons'), SHUBX51_VERSION );
        
        // Receipt CSS
        wp_enqueue_style( 'shubx51-receipt-css', SHUBX51_PLUGIN_URL . 'assets/css/receipt.css', array('shubx51-bootstrap'), SHUBX51_VERSION );
        
        // Fonts
        wp_enqueue_style( 'shubx51-fonts', SHUBX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 1. Chart.js for Charts (Local)
		wp_enqueue_script( 'shubx51-chartjs', SHUBX51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.4.3', true );

		// HTML2Canvas for receipt screenshots
		wp_enqueue_script( 'shubx51-html2canvas', SHUBX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );

        // Search functionality
        wp_enqueue_script( 'shubx51-fuse', SHUBX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
        wp_enqueue_script( 'shubx51-search-init', SHUBX51_PLUGIN_URL . 'assets/js/shubx-search-init.js', array('shubx51-fuse'), time(), true );

        // Core utilities
        wp_enqueue_script( 'shubx51-core', SHUBX51_PLUGIN_URL . 'assets/js/shubx-core.js', array('jquery'), SHUBX51_VERSION, true );
        
        // Toast notification system
        wp_enqueue_script( 'shubx51-toast', SHUBX51_PLUGIN_URL . 'assets/js/shubx-toast.js', array('jquery'), SHUBX51_VERSION, true );
        
        // Centralized AJAX handler
        wp_enqueue_script( 'shubx51-ajax', SHUBX51_PLUGIN_URL . 'assets/js/shubx-ajax.js', array('jquery', 'shubx51-toast'), SHUBX51_VERSION, true );

        // Main dashboard script (depends on core, toast, ajax)
        wp_enqueue_script( 'shubx51-dashboard-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-dashboard.js', array('jquery', 'shubx51-core', 'shubx51-toast', 'shubx51-ajax', 'shubx51-bootstrap', 'shubx51-chartjs', 'shubx51-search-init'), SHUBX51_VERSION, true );
        
        // Only load module-specific scripts if user is logged in
        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'shubx51-documents-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-documents.js', array('jquery', 'shubx51-core', 'shubx51-toast', 'shubx51-ajax', 'shubx51-bootstrap'), SHUBX51_VERSION, true );
        }

		// Localize AJAX URL for frontend (needed for resident login)
		wp_localize_script( 'shubx51-bootstrap', 'shubx51_frontend', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'version' => SHUBX51_VERSION
		) );
		// Ensure global ajaxurl fallback for legacy fetch in resident-login.php
		wp_add_inline_script( 'shubx51-bootstrap', 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";', 'before' );
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
	 * 2. OR if the page contains string '[society_hubx_dashboard]' - AUTO APPLY.
	 */
	public function load_page_template( $template ) {
		global $post;
		$target = get_page_template_slug();

		// 1. Check if manually selected
		if ( $target === 'society-app.php' ) {
			return SHUBX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		// 2. Auto-detect Shortcode (Fallback if user can't select template)
		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'society_hubx_dashboard' ) ) {
			 return SHUBX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		return $template;
	}

	/**
	 * Seed resident_flat_map table for existing residents.
	 */
	public function seed_resident_flat_map() {
		$table_name = $this->db->get_table_name( 'resident_flat_map' );
		
		// Check if we've already done this migration
		if ( get_option( 'shubx51_flat_map_seeded' ) === SHUBX51_VERSION ) {
			return;
		}

		$residents = $this->db->get( 'residents' );
		if ( empty( $residents ) ) {
			update_option( 'shubx51_flat_map_seeded', SHUBX51_VERSION );
			return;
		}

		foreach ( $residents as $r ) {
			$resident_id = $r['id'] ?? '';
			$flat_no = $r['flat_no'] ?? '';
			if ( empty( $resident_id ) || empty( $flat_no ) ) {
				continue;
			}

			// Check if already mapped
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is fully controlled and sanitized.
			$exists = $this->db->wpdb->get_var( $this->db->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE resident_id = %s",
				$resident_id
			) );

			if ( ! $exists ) {
				// Map it as primary
				$this->db->wpdb->insert( $table_name, array(
					'resident_id' => $resident_id,
					'flat_id'     => $flat_no,
					'is_primary'  => 1
				) );
			}
		}

		update_option( 'shubx51_flat_map_seeded', SHUBX51_VERSION );
	}
}

/**
 * Initialize the plugin.
 */
function shubx51_init() {
	return Society_HubX::get_instance();
}
add_action( 'plugins_loaded', 'shubx51_init' );
