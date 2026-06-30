<?php
/**
 * Plugin Name: Society GoVernX
 * Plugin URI:  https://www.vishwas.me
 * Description: A premium, comprehensive society management system featuring automated maintenance, facility bookings, digital document vault, and resident community engagement.
 * Version:     1.0.3
 * Author:      Vishwas R
 * Author URI:  https://www.vishwas.me
 * Text Domain: society-governx
 * License:     GPL-2.0+
 * Tags:        society management, apartment management, resident portal, maintenance tracking, facility booking
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'SGVX51_VERSION', '1.0.3' );
define( 'SGVX51_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SGVX51_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SGVX51_PREFIX', 'sgvx51' );

/**
 * Main Plugin Class.
 */
final class Society_GoVernX {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Database Router Instance.
	 *
	 * @var SGVX51_DB_Router
	 */
	public $db = null;

	/**
	 * Notification Dispatcher Instance.
	 *
	 * @var SGVX51_Notification_Dispatcher
	 */
	public $notifications = null;

	/**
	 * RBAC Manager Instance.
	 *
	 * @var SGVX51_RBAC_Manager
	 */
	public $rbac = null;

	/**
	 * Privacy Manager Instance.
	 *
	 * @var SGVX51_Privacy_Manager
	 */
	public $privacy = null;

	/**
	 * REST Manager Instance.
	 *
	 * @var SGVX51_REST_Manager
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
		require_once SGVX51_PLUGIN_DIR . 'includes/interface-module.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-google-api-handler.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-db-router.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-drive-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-media-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-log-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-background-worker.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-privacy-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-rest-manager.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-data-migrator.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/class-payment-service.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';
		
		// Notifications
		require_once SGVX51_PLUGIN_DIR . 'includes/notifications/interface-notification-provider.php';
		require_once SGVX51_PLUGIN_DIR . 'includes/notifications/class-notification-dispatcher.php';
		
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-app.php';
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-requests.php';
		require_once SGVX51_PLUGIN_DIR . 'admin/class-ajax-handler.php';
		require_once SGVX51_PLUGIN_DIR . 'admin/class-admin-ui-helper.php';
		
		// Initialize
		$this->db = new SGVX51_DB_Router();
		$this->notifications = new SGVX51_Notification_Dispatcher( $this->db );
		new SGVX51_Log_Manager( $this->db );
		new SGVX51_Background_Worker();
		$this->rbac = new SGVX51_RBAC_Manager();
		$this->privacy = new SGVX51_Privacy_Manager();
		$this->rest    = new SGVX51_REST_Manager();

		// Version Check / Database Initialization
		$this->maybe_update_db();

		// Initialize Admin Settings
		if ( is_admin() ) {
			new SGVX51_Admin_Settings();
			new SGVX51_Admin_Requests();
			new SGVX51_AJAX_Handler();
			
			// Data Portability (Export/Import)
			require_once SGVX51_PLUGIN_DIR . 'admin/class-data-portability.php';
			new SGVX51_Data_Portability();
		}
		
						
		// Load Modules
		require_once SGVX51_PLUGIN_DIR . 'modules/flats/class-flat-manager.php'; // New Master Data
		new SGVX51_Flat_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/residents/class-resident-manager.php';
		new SGVX51_Resident_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/vehicles/class-vehicle-manager.php';
		new SGVX51_Vehicle_Manager();
		
		require_once SGVX51_PLUGIN_DIR . 'modules/documents/class-document-manager.php';
		new SGVX51_Document_Manager();
		
		require_once SGVX51_PLUGIN_DIR . 'modules/facilities/class-facility-manager.php';
		new SGVX51_Facility_Manager();
		
		require_once SGVX51_PLUGIN_DIR . 'modules/finance/class-expense-manager.php';
		new SGVX51_Expense_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
		new SGVX51_Account_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/assets/class-asset-manager.php';
		new SGVX51_Asset_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/notices/class-notice-board.php';
		new SGVX51_Notice_Board();

		require_once SGVX51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		new SGVX51_Ledger_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/democracy/class-poll-manager.php';
		new SGVX51_Poll_Manager();

        require_once SGVX51_PLUGIN_DIR . 'modules/staff/class-staff-manager.php';
		new SGVX51_Staff_Manager();

        require_once SGVX51_PLUGIN_DIR . 'modules/class-general-request-manager.php';
        new SGVX51_General_Request_Manager();

		require_once SGVX51_PLUGIN_DIR . 'modules/rules/class-rule-manager.php';
		new SGVX51_Rule_Manager();

		// Frontend
		require_once SGVX51_PLUGIN_DIR . 'includes/class-frontend-dashboard.php';
		new SGVX51_Frontend_Dashboard();

		// Register// 2. Initialization & Activation
		register_activation_hook( __FILE__, array( 'SGVX51_DB_Schema', 'create_tables' ) );
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
		if ( function_exists('as_next_scheduled_action') && !as_next_scheduled_action('sgvx51_daily_log_purge') ) {
			as_schedule_recurring_action( strtotime('midnight'), DAY_IN_SECONDS, 'sgvx51_daily_log_purge' );
		}
	}

	/**
	 * Run DB Update if version changes.
	 */
	public function maybe_update_db() {
		if ( get_option( 'sgvx51_version' ) !== SGVX51_VERSION ) {
			// Ensure tables are created first
			SGVX51_DB_Schema::create_tables();
			update_option( 'sgvx51_version', SGVX51_VERSION );
		}

		// Ensure data migration is triggered after tables exist
		if ( is_admin() && get_option( 'sgvx51_storage_migrated' ) !== SGVX51_VERSION ) {
			// Double check dependencies are loaded
			if ( class_exists( 'SGVX51_Data_Migrator' ) ) {
				SGVX51_Data_Migrator::run_all();
				update_option( 'sgvx51_storage_migrated', SGVX51_VERSION );
			}
		}
	}

	/**
	 * Localization and setup.
	 */
	public function on_plugins_loaded() {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Textdomain load implementation is standard fallback.
		load_plugin_textdomain( 'society-governx', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
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
			return admin_url( 'admin.php?page=sgvx51-settings' );
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
				return admin_url( 'admin.php?page=sgvx51-settings' );
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
        wp_enqueue_script( 'sgvx51-core', SGVX51_PLUGIN_URL . 'assets/js/sgvx-core.js', array('jquery'), SGVX51_VERSION, true );

        // Only load on our plugin pages to avoid breaking global WP Admin
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page query parameter read-only check.
        $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        // Only load on our plugin pages to avoid breaking global WP Admin
        if ( empty($page) || strpos($page, 'sgvx51') === false ) {
            return;
        }

        // 0. WP Reset Shim (Load first to clear the path)
        //wp_enqueue_style( 'sgvx51_wp_reset_shim', SGVX51_PLUGIN_URL . 'assets/css/wp-reset-shim.css', array(), SGVX51_VERSION );

		// 1. Google Fonts (Inter) - Local
        wp_enqueue_style( 'sgvx51_fonts', SGVX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), SGVX51_VERSION );

		// 2. Bootstrap 5 (Local) - Load late to naturally override WP styles
		wp_enqueue_style( 'sgvx51_bootstrap_css', SGVX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_script( 'sgvx51_bootstrap_js', SGVX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.0', true );

        // 3. Bootstrap Icons (Local)
        wp_enqueue_style( 'sgvx51_bootstrap_icons', SGVX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('sgvx51_bootstrap_css'), '1.11.3' );

        // 4. Custom Admin Styles (Final Overrides)
		wp_enqueue_style( 'sgvx51_admin_layout', SGVX51_PLUGIN_URL . 'assets/css/admin-layout.css', array('sgvx51_bootstrap_css', 'sgvx51_bootstrap_icons'), SGVX51_VERSION );
		wp_enqueue_style( 'sgvx51_admin_premium', SGVX51_PLUGIN_URL . 'assets/css/admin-premium.css', array('sgvx51_bootstrap_css', 'sgvx51_admin_layout'), SGVX51_VERSION );

		// 5. Receipt Styling
		wp_enqueue_style( 'sgvx51_receipt_css', SGVX51_PLUGIN_URL . 'assets/css/receipt.css', array(), SGVX51_VERSION );

		// Core utilities
        wp_enqueue_script( 'sgvx51-core', SGVX51_PLUGIN_URL . 'assets/js/sgvx-core.js', array('jquery'), SGVX51_VERSION, true );
        
        // Toast notification system (extracted from core)
        wp_enqueue_script( 'sgvx51-toast', SGVX51_PLUGIN_URL . 'assets/js/sgvx-toast.js', array('jquery'), SGVX51_VERSION, true );
        
        // Centralized AJAX handler with loading states
        wp_enqueue_script( 'sgvx51-ajax', SGVX51_PLUGIN_URL . 'assets/js/sgvx-ajax.js', array('jquery', 'sgvx51-toast'), SGVX51_VERSION, true );

		// Google API (placeholder)
		if ( $page === 'sgvx51-google-drive' ) {
			$SGVX51_Google_API_Handler = new SGVX51_Google_API_Handler();
			$SGVX51_Google_API_Handler->enqueue_google_api_scripts();
		}

		// Inline setup for ajaxurl
		wp_add_inline_script( 'sgvx51-core', "
            var ajaxurl = '" . esc_url_raw( admin_url( 'admin-ajax.php' ) ) . "';
            var sgvx51_nonce = '" . esc_js( wp_create_nonce( 'sgvx51_admin_nonce' ) ) . "';
            var sgvx51_admin_nonce = '" . esc_js( wp_create_nonce( 'sgvx51_admin_nonce' ) ) . "';
        " );

		// Libraries (CanvasJS for charts, html2canvas for screenshots)
        wp_enqueue_script( 'sgvx51-html2canvas', SGVX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
        wp_enqueue_script( 'sgvx51-fuse', SGVX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
		wp_enqueue_script( 'sgvx51-canvasjs', SGVX51_PLUGIN_URL . 'assets/js/lib/canvasjs.min.js', array(), '2.0.8', true );
        wp_enqueue_script( 'sgvx51-search-init', SGVX51_PLUGIN_URL . 'assets/js/sgvx-search-init.js', array('sgvx51-fuse'), SGVX51_VERSION, true );
        wp_enqueue_script( 'sgvx51-admin-app', SGVX51_PLUGIN_URL . 'assets/js/admin-app.js', array('jquery', 'sgvx51-core', 'sgvx51-toast', 'sgvx51-ajax', 'sgvx51-html2canvas', 'sgvx51-search-init'), SGVX51_VERSION, true );

        // Standard Nonces for global actions
        wp_localize_script( 'sgvx51-admin-app', 'sgvx51_vars', array(
            'request_nonce' => wp_create_nonce( 'sgvx51_request_action' )
        ));
        // Add global JS variable for convenience
        wp_add_inline_script( 'sgvx51-admin-app', 'var sgvx51RequestNonce = "' . wp_create_nonce( 'sgvx51_request_action' ) . '";', 'before' );

        // Residents View Specific JS
        if ( $page === 'sgvx51-residents' ) {
            wp_enqueue_script( 'sgvx51-residents-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-residents.js', array('jquery'), time(), true );
            
            $residents = $this->db->get('residents');
            $flat_owners = array();
            if(!empty($residents)) {
                foreach($residents as $r) {
                    if(isset($r['type']) && strtolower($r['type']) === 'owner') {
                        $flat_owners[$r['flat_no']] = array('name' => $r['name'], 'id' => $r['id'] ?? '');
                    }
                }
            }

            // Note: Nonces are now fetched dynamically via AJAX in sgvx-residents.js
            // wp_localize_script is no longer needed since we fetch config at runtime
        }

		// Facilities View Specific JS
		if ( $page === 'sgvx51-facilities' ) {
			wp_enqueue_script( 'sgvx51-facilities-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-facilities.js', array('jquery', 'sgvx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

        // Flats View Specific JS
        if ( $page === 'sgvx51-flats' ) {
            wp_enqueue_script( 'sgvx51-flats-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-flats.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Vehicles View Specific JS
        if ( $page === 'sgvx51-vehicles' ) {
            wp_enqueue_script( 'sgvx51-vehicles-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-vehicles.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Staff View Specific JS
        if ( $page === 'sgvx51-staff' ) {
            wp_enqueue_script( 'sgvx51-staff-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-staff.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

		// Notices View Specific JS
		if ( $page === 'sgvx51-notices' ) {
			wp_enqueue_script( 'sgvx51-notices-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-notices.js', array('jquery', 'sgvx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Documents View Specific JS
		if ( $page === 'sgvx51-documents' ) {
			wp_enqueue_script( 'sgvx51-documents-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-documents.js', array('jquery', 'sgvx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Expenses View Specific JS
		if ( $page === 'sgvx51-expenses' ) {
			wp_enqueue_script( 'sgvx51-expenses-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-expenses.js', array('jquery', 'sgvx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Accounts View Specific JS (Invoices & Ledger)
		if ( $page === 'sgvx51-accounts' ) {
			wp_enqueue_script( 'sgvx51-accounts-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-accounts.js', array('jquery', 'sgvx51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Notifications View Specific JS (Now also on Settings for Communication tab)
		if ( in_array($page, ['sgvx51-activity-hub', 'sgvx51-global-settings']) ) {
			wp_enqueue_script( 'sgvx51-notifications-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-notifications.js', array('jquery', 'sgvx51-admin-app'), time(), true );
		}
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
		// Bootstrap 5 & jQuery (if needed, but we use Tailwind mostly - NOW MIGRATED TO BOOTSTRAP)
        wp_enqueue_style( 'sgvx51-bootstrap', SGVX51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_script( 'sgvx51-bootstrap', SGVX51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.0', true );

        // 0. Bootstrap Icons (Local)
        wp_enqueue_style( 'sgvx51-bootstrap-icons', SGVX51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('sgvx51-bootstrap'), '1.11.3' );

        // Custom Frontend CSS (Tailwind Replacement)
        wp_enqueue_style( 'sgvx51-frontend-css', SGVX51_PLUGIN_URL . 'assets/css/sgvx-frontend.css', array('sgvx51-bootstrap', 'sgvx51-fonts', 'sgvx51-bootstrap-icons'), SGVX51_VERSION );
        
        // Receipt CSS
        wp_enqueue_style( 'sgvx51-receipt-css', SGVX51_PLUGIN_URL . 'assets/css/receipt.css', array('sgvx51-bootstrap'), SGVX51_VERSION );
        
        // Fonts
        wp_enqueue_style( 'sgvx51-fonts', SGVX51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 1. CanvasJS for Charts (Local)
		wp_enqueue_script( 'sgvx51-canvasjs', SGVX51_PLUGIN_URL . 'assets/js/lib/canvasjs.min.js', array(), '1.0.0', true );

		// HTML2Canvas for receipt screenshots
		wp_enqueue_script( 'sgvx51-html2canvas', SGVX51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );

        // Search functionality
        wp_enqueue_script( 'sgvx51-fuse', SGVX51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
        wp_enqueue_script( 'sgvx51-search-init', SGVX51_PLUGIN_URL . 'assets/js/sgvx-search-init.js', array('sgvx51-fuse'), time(), true );

        // Core utilities
        wp_enqueue_script( 'sgvx51-core', SGVX51_PLUGIN_URL . 'assets/js/sgvx-core.js', array('jquery'), SGVX51_VERSION, true );
        
        // Toast notification system
        wp_enqueue_script( 'sgvx51-toast', SGVX51_PLUGIN_URL . 'assets/js/sgvx-toast.js', array('jquery'), SGVX51_VERSION, true );
        
        // Centralized AJAX handler
        wp_enqueue_script( 'sgvx51-ajax', SGVX51_PLUGIN_URL . 'assets/js/sgvx-ajax.js', array('jquery', 'sgvx51-toast'), SGVX51_VERSION, true );

        // Main dashboard script (depends on core, toast, ajax)
        wp_enqueue_script( 'sgvx51-dashboard-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-dashboard.js', array('jquery', 'sgvx51-core', 'sgvx51-toast', 'sgvx51-ajax', 'sgvx51-bootstrap', 'sgvx51-canvasjs', 'sgvx51-search-init'), SGVX51_VERSION, true );
        
        // Only load module-specific scripts if user is logged in
        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'sgvx51-documents-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-documents.js', array('jquery', 'sgvx51-core', 'sgvx51-toast', 'sgvx51-ajax', 'sgvx51-bootstrap'), SGVX51_VERSION, true );
        }

		// Localize AJAX URL for frontend (needed for resident login)
		wp_localize_script( 'sgvx51-bootstrap', 'sgvx51_frontend', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'version' => SGVX51_VERSION
		) );
		// Ensure global ajaxurl fallback for legacy fetch in resident-login.php
		wp_add_inline_script( 'sgvx51-bootstrap', 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";', 'before' );
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
	 * 2. OR if the page contains string '[Society_GoVernX_dashboard]' - AUTO APPLY.
	 */
	public function load_page_template( $template ) {
		global $post;
		$target = get_page_template_slug();

		// 1. Check if manually selected
		if ( $target === 'society-app.php' ) {
			return SGVX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		// 2. Auto-detect Shortcode (Fallback if user can't select template)
		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'Society_GoVernX_dashboard' ) ) {
			 return SGVX51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		return $template;
	}
}

/**
 * Initialize the plugin.
 */
function sgvx51_init() {
	return Society_GoVernX::get_instance();
}
add_action( 'plugins_loaded', 'sgvx51_init' );
