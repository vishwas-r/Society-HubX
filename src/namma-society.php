<?php
/*
 * Plugin Name:       Namma Society – Apartment Management Portal (ನಮ್ಮ ಸೊಸೈಟಿ)
 * Plugin URI:        https://github.com/vishwas-r/namma-society
 * Description:       A premium, comprehensive society management system featuring automated maintenance, facility bookings, digital document vault, and resident community engagement.
 * Version:           1.0.6
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Vishwas R
 * Author URI:        https://www.vishwas.me
 * Text Domain:       namma-society
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define Constants.
define( 'NAMMASOCIETY51_VERSION', '1.0.7' );
define( 'NAMMASOCIETY51_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NAMMASOCIETY51_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NAMMASOCIETY51_PREFIX', 'NAMMASOCIETY51' );

/**
 * Main Plugin Class.
 */
final class NAMMASOCIETY51_Plugin {
	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Database Router Instance.
	 *
	 * @var NAMMASOCIETY51_DB_Router
	 */
	public $db = null;

	/**
	 * Notification Dispatcher Instance.
	 *
	 * @var NAMMASOCIETY51_Notification_Dispatcher
	 */
	public $notifications = null;

	/**
	 * RBAC Manager Instance.
	 *
	 * @var NAMMASOCIETY51_RBAC_Manager
	 */
	public $rbac = null;

	/**
	 * Privacy Manager Instance.
	 *
	 * @var NAMMASOCIETY51_Privacy_Manager
	 */
	public $privacy = null;

	/**
	 * REST Manager Instance.
	 *
	 * @var NAMMASOCIETY51_REST_Manager
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
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-module-registry.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/interface-module.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-google-api-handler.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-db-router.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-db-schema.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-drive-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-media-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-log-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-background-worker.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-privacy-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rest-manager.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-data-migrator.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/interface-payment-gateway.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-payment-service.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-visitors-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-helpdesk-controller.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/rest/class-rest-emergency-controller.php';
		
		// Notifications
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/notifications/interface-notification-provider.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/notifications/class-notification-dispatcher.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-fcm-service.php';
		
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-admin-settings.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-admin-app.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-admin-requests.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-ajax-handler.php';
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-admin-ui-helper.php';
		
		// Initialize
		$this->db = new NAMMASOCIETY51_DB_Router();
		$this->notifications = new NAMMASOCIETY51_Notification_Dispatcher( $this->db );
		NAMMASOCIETY51_FCM_Service::init();
		new NAMMASOCIETY51_Log_Manager( $this->db );
		new NAMMASOCIETY51_Background_Worker();
		$this->rbac = new NAMMASOCIETY51_RBAC_Manager();
		$this->privacy = new NAMMASOCIETY51_Privacy_Manager();
		$this->rest    = new NAMMASOCIETY51_REST_Manager();

		// Version Check / Database Initialization
		$this->maybe_update_db();

		// Initialize Admin Settings
		if ( is_admin() ) {
			new NAMMASOCIETY51_Admin_Settings();
			new NAMMASOCIETY51_Admin_Requests();
			new NAMMASOCIETY51_AJAX_Handler();
			
			// Data Portability (Export/Import)
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'admin/class-data-portability.php';
			new NAMMASOCIETY51_Data_Portability();
		}
		
		// Load Core Modules (Locked)
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/flats/class-flat-manager.php';
		new NAMMASOCIETY51_Flat_Manager();

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/residents/class-resident-manager.php';
		new NAMMASOCIETY51_Resident_Manager();

		// Load Conditional Modules (Can be toggled in Settings > Modules)
		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'vehicles' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/vehicles/class-vehicle-manager.php';
			new NAMMASOCIETY51_Vehicle_Manager();
		}
		
		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'documents' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/documents/class-document-manager.php';
			new NAMMASOCIETY51_Document_Manager();
		}
		
		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'facilities' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/facilities/class-facility-manager.php';
			new NAMMASOCIETY51_Facility_Manager();
		}
		
		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'finance' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-expense-manager.php';
			new NAMMASOCIETY51_Expense_Manager();

			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
			new NAMMASOCIETY51_Account_Manager();

			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-tally-exporter.php';

			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
			new NAMMASOCIETY51_Ledger_Manager();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'assets' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/assets/class-asset-manager.php';
			new NAMMASOCIETY51_Asset_Manager();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'notices' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/notices/class-notice-board.php';
			new NAMMASOCIETY51_Notice_Board();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'polls' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/democracy/class-poll-manager.php';
			new NAMMASOCIETY51_Poll_Manager();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'staff' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/staff/class-staff-manager.php';
			new NAMMASOCIETY51_Staff_Manager();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'helpdesk' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/class-general-request-manager.php';
			new NAMMASOCIETY51_General_Request_Manager();
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/helpdesk/class-helpdesk-manager.php';
			new NAMMASOCIETY51_Helpdesk_Manager();
		}

		if ( NAMMASOCIETY51_Module_Registry::is_enabled( 'rules' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/rules/class-rule-manager.php';
			new NAMMASOCIETY51_Rule_Manager();
		}

		// Frontend
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-frontend-dashboard.php';
		new NAMMASOCIETY51_Frontend_Dashboard();

		// Register// 2. Initialization & Activation
		register_activation_hook( __FILE__, array( 'NAMMASOCIETY51_DB_Schema', 'create_tables' ) );
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
		if ( function_exists('as_next_scheduled_action') && !as_next_scheduled_action('nammasociety51_daily_log_purge') ) {
			as_schedule_recurring_action( strtotime('midnight'), DAY_IN_SECONDS, 'nammasociety51_daily_log_purge' );
		}

	}

	/**
	 * Run DB Update if version changes.
	 */
	public function maybe_update_db() {
		if ( get_option( 'nammasociety51_version' ) !== NAMMASOCIETY51_VERSION ) {
			// Ensure tables are created first
			NAMMASOCIETY51_DB_Schema::create_tables();
			update_option( 'nammasociety51_version', NAMMASOCIETY51_VERSION );
		}

		// Ensure data migration is triggered after tables exist
		if ( is_admin() && get_option( 'nammasociety51_storage_migrated' ) !== NAMMASOCIETY51_VERSION ) {
			// Double check dependencies are loaded
			if ( class_exists( 'NAMMASOCIETY51_Data_Migrator' ) ) {
				NAMMASOCIETY51_Data_Migrator::run_all();
				update_option( 'nammasociety51_storage_migrated', NAMMASOCIETY51_VERSION );
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
			return admin_url( 'admin.php?page=nammasociety51-settings' );
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
				return admin_url( 'admin.php?page=nammasociety51-settings' );
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
        wp_enqueue_script( 'nammasociety51-core', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-core.js', array('jquery'), NAMMASOCIETY51_VERSION, true );

        // Only load on our plugin pages to avoid breaking global WP Admin
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page query parameter read-only check.
        $page = isset($_GET['page']) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

        // Only load on our plugin pages to avoid breaking global WP Admin
        if ( empty($page) || strpos($page, 'nammasociety51') === false ) {
            return;
        }

        // 0. WP Reset Shim (Load first to clear the path)
        //wp_enqueue_style( 'nammasociety51_wp_reset_shim', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/wp-reset-shim.css', array(), NAMMASOCIETY51_VERSION );

		// 1. Google Fonts (Inter) - Local
        wp_enqueue_style( 'nammasociety51_fonts', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), NAMMASOCIETY51_VERSION );

		// 2. Bootstrap 5 (Local) - Load late to naturally override WP styles
		wp_enqueue_style( 'nammasociety51_bootstrap_css', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.8' );
		wp_enqueue_script( 'nammasociety51_bootstrap_js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.8', true );

        // 3. Bootstrap Icons (Local)
        wp_enqueue_style( 'nammasociety51_bootstrap_icons', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('nammasociety51_bootstrap_css'), '1.11.3' );

        // 4. Custom Admin Styles (Final Overrides)
		wp_enqueue_style( 'nammasociety51_admin_premium', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/admin-premium.css', array('nammasociety51_bootstrap_css'), NAMMASOCIETY51_VERSION );
		wp_enqueue_style( 'nammasociety51_admin_layout', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/admin-layout.css', array('nammasociety51_bootstrap_css', 'nammasociety51_bootstrap_icons', 'nammasociety51_admin_premium'), NAMMASOCIETY51_VERSION );

		// 5. Receipt Styling
		wp_enqueue_style( 'nammasociety51_receipt_css', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/receipt.css', array(), NAMMASOCIETY51_VERSION );

		// Core utilities
        wp_enqueue_script( 'nammasociety51-core', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-core.js', array('jquery'), NAMMASOCIETY51_VERSION, true );
        
        // Toast notification system (extracted from core)
        wp_enqueue_script( 'nammasociety51-toast', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-toast.js', array('jquery'), NAMMASOCIETY51_VERSION, true );
        
        // Centralized AJAX handler with loading states
        wp_enqueue_script( 'nammasociety51-ajax', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-ajax.js', array('jquery', 'nammasociety51-toast'), NAMMASOCIETY51_VERSION, true );

		// Google API (placeholder)
		if ( $page === 'nammasociety51-google-drive' ) {
			$nammasociety51_Google_API_Handler = new NAMMASOCIETY51_Google_API_Handler();
			$nammasociety51_Google_API_Handler->enqueue_google_api_scripts();
		}

		// Inline setup for ajaxurl
		wp_add_inline_script( 'nammasociety51-core', "
            var ajaxurl = '" . esc_js( admin_url( 'admin-ajax.php' ) ) . "';
            var nammasociety51_nonce = '" . esc_js( wp_create_nonce( 'nammasociety51_admin_nonce' ) ) . "';
            var nammasociety51_admin_nonce = '" . esc_js( wp_create_nonce( 'nammasociety51_admin_nonce' ) ) . "';
        " );

		// Libraries (Chart.js for charts, html2canvas for screenshots)
        wp_enqueue_script( 'nammasociety51-html2canvas', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );
        wp_enqueue_script( 'nammasociety51-fuse', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
		wp_enqueue_script( 'nammasociety51-chartjs', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.5.1', true );
        wp_enqueue_script( 'nammasociety51-search-init', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-search-init.js', array('nammasociety51-fuse'), NAMMASOCIETY51_VERSION, true );
        wp_enqueue_script( 'nammasociety51-admin-app', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/admin-app.js', array('jquery', 'nammasociety51-core', 'nammasociety51-toast', 'nammasociety51-ajax', 'nammasociety51-html2canvas', 'nammasociety51-search-init'), NAMMASOCIETY51_VERSION, true );

        // Standard Nonces for global actions
        wp_localize_script( 'nammasociety51-admin-app', 'nammasociety51_vars', array(
            'request_nonce' => wp_create_nonce( 'nammasociety51_request_action' )
        ));
        // Add global JS variable for convenience
        wp_add_inline_script( 'nammasociety51-admin-app', 'var nammasociety51RequestNonce = "' . wp_create_nonce( 'nammasociety51_request_action' ) . '";', 'before' );

        // Residents View Specific JS
        if ( $page === 'nammasociety51-residents' ) {
            wp_enqueue_script( 'nammasociety51-residents-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-residents.js', array('jquery'), time(), true );
            
            $residents = $this->db->get('residents');
            $flat_owners = array();
            if(!empty($residents)) {
                foreach($residents as $r) {
                    if(isset($r['type']) && strtolower($r['type']) === 'owner') {
                        $flat_owners[$r['flat_no']] = array('name' => $r['name'], 'id' => $r['id'] ?? '');
                    }
                }
            }

            // Note: Nonces are now fetched dynamically via AJAX in nammasociety-residents.js
            // wp_localize_script is no longer needed since we fetch config at runtime
        }

		// Facilities View Specific JS
		if ( $page === 'nammasociety51-facilities' ) {
			wp_enqueue_script( 'nammasociety51-facilities-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-facilities.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

        // Flats View Specific JS
        if ( $page === 'nammasociety51-flats' ) {
            wp_enqueue_script( 'nammasociety51-flats-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-flats.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Vehicles View Specific JS
        if ( $page === 'nammasociety51-vehicles' ) {
            wp_enqueue_script( 'nammasociety51-vehicles-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-vehicles.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Staff View Specific JS
        if ( $page === 'nammasociety51-staff' ) {
            wp_enqueue_script( 'nammasociety51-staff-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-staff.js', array('jquery'), time(), true );
            // Config fetched dynamically via AJAX
        }

        // Helpdesk View Specific JS
        if ( $page === 'nammasociety51-helpdesk' ) {
            wp_enqueue_script( 'nammasociety51-helpdesk-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-helpdesk.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
            wp_localize_script( 'nammasociety51-helpdesk-js', 'nammasociety51HelpdeskVars', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'nammasociety51_helpdesk_nonce' ),
            ) );
        }

		// Rules View Specific JS
		if ( $page === 'nammasociety51-rules' ) {
			wp_enqueue_script( 'nammasociety51-rules-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-rules.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
		}

		// Notices View Specific JS
		if ( $page === 'nammasociety51-notices' ) {
			wp_enqueue_script( 'nammasociety51-notices-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-notices.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Documents View Specific JS
		if ( $page === 'nammasociety51-documents' ) {
			wp_enqueue_script( 'nammasociety51-documents-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-documents.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Expenses View Specific JS
		if ( $page === 'nammasociety51-expenses' ) {
			wp_enqueue_script( 'nammasociety51-expenses-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-expenses.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
			// Config fetched dynamically via AJAX
		}

		// Accounts View Specific JS (Invoices & Ledger)
		if ( $page === 'nammasociety51-accounts' ) {
			wp_enqueue_script( 'nammasociety51-accounts-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-accounts.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
			wp_add_inline_script( 'nammasociety51-accounts-js', 'var nammasociety51AccountNonce = "' . wp_create_nonce( 'nammasociety51_account_action' ) . '";', 'before' );
		}

		// Notifications View Specific JS (Now also on Settings for Communication tab)
		if ( in_array($page, ['nammasociety51-activity-hub', 'nammasociety51-global-settings']) ) {
			wp_enqueue_script( 'nammasociety51-notifications-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-notifications.js', array('jquery', 'nammasociety51-admin-app'), time(), true );
		wp_localize_script( 'nammasociety51-notifications-js', 'nammasociety51NotificationsVars', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'nammasociety51_request_action' ),
			) );
		}

		// Views JS for roles, requests, polls, activity-hub, expenses, documents, assets
		if ( in_array( $page, array( 'nammasociety51-roles', 'nammasociety51-requests', 'nammasociety51-polls', 'nammasociety51-activity-hub', 'nammasociety51-expenses', 'nammasociety51-documents', 'nammasociety51-assets' ) ) ) {
			wp_enqueue_script( 'nammasociety-views', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-views.js', array( 'jquery', 'nammasociety51-admin-app' ), NAMMASOCIETY51_VERSION, true );
			wp_localize_script( 'nammasociety-views', 'nammasociety51ViewsConfig', array(
				'adminPostUrl' => admin_url( 'admin-post.php' ),
				'roleNonce'    => wp_create_nonce( 'nammasociety51_role_nonce' ),
			) );
		}

		// Resident Form JS (image preview, multi-flat selector, type toggle)
		if ( $page === 'nammasociety51-residents' ) {
			wp_enqueue_script( 'nammasociety51-resident-form-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-resident-form.js', array( 'jquery', 'nammasociety51-admin-app' ), NAMMASOCIETY51_VERSION, true );
		}
	}

	/**
	 * Enqueue Frontend Assets.
	 */
	public function enqueue_frontend_assets() {
        // 0. Fonts (Local Inter)
        wp_enqueue_style( 'nammasociety51-fonts', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 1. Bootstrap 5 (Local)
        wp_enqueue_style( 'nammasociety51-bootstrap', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/bootstrap.min.css', array(), '5.3.8' );
		wp_enqueue_script( 'nammasociety51-bootstrap', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.8', true );

        // 2. Bootstrap Icons (Local)
        wp_enqueue_style( 'nammasociety51-bootstrap-icons', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/lib/bootstrap-icons.min.css', array('nammasociety51-bootstrap'), '1.11.3' );

        // 3. Custom Frontend CSS (Loaded after Bootstrap to ensure override)
        wp_enqueue_style( 'nammasociety51-frontend-css', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/nammasociety-frontend.css', array('nammasociety51-bootstrap', 'nammasociety51-fonts', 'nammasociety51-bootstrap-icons'), NAMMASOCIETY51_VERSION );
        
        // 4. Receipt CSS
        wp_enqueue_style( 'nammasociety51-receipt-css', NAMMASOCIETY51_PLUGIN_URL . 'assets/css/receipt.css', array('nammasociety51-bootstrap'), NAMMASOCIETY51_VERSION );

		// 1. Chart.js for Charts (Local)
		wp_enqueue_script( 'nammasociety51-chartjs', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/chart.umd.min.js', array(), '4.5.1', true );

		// HTML2Canvas for receipt screenshots
		wp_enqueue_script( 'nammasociety51-html2canvas', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/html2canvas.min.js', array(), '1.4.1', true );

        // Search functionality
        wp_enqueue_script( 'nammasociety51-fuse', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/lib/fuse.min.js', array(), '7.1.0', true );
        wp_enqueue_script( 'nammasociety51-search-init', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-search-init.js', array('nammasociety51-fuse'), time(), true );

        // Core utilities
        wp_enqueue_script( 'nammasociety51-core', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-core.js', array('jquery'), NAMMASOCIETY51_VERSION, true );
        
        // Toast notification system
        wp_enqueue_script( 'nammasociety51-toast', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-toast.js', array('jquery'), NAMMASOCIETY51_VERSION, true );
        
        // Centralized AJAX handler
        wp_enqueue_script( 'nammasociety51-ajax', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-ajax.js', array('jquery', 'nammasociety51-toast'), NAMMASOCIETY51_VERSION, true );

        // Main dashboard script (depends on core, toast, ajax)
        wp_enqueue_script( 'nammasociety51-dashboard-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-dashboard.js', array('jquery', 'nammasociety51-core', 'nammasociety51-toast', 'nammasociety51-ajax', 'nammasociety51-bootstrap', 'nammasociety51-chartjs', 'nammasociety51-search-init'), NAMMASOCIETY51_VERSION, true );
        
        // Only load module-specific scripts if user is logged in
        if ( is_user_logged_in() ) {
            wp_enqueue_script( 'nammasociety51-documents-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-documents.js', array('jquery', 'nammasociety51-core', 'nammasociety51-toast', 'nammasociety51-ajax', 'nammasociety51-bootstrap'), NAMMASOCIETY51_VERSION, true );
            // Resident form interactive logic (profile edit modal in dashboard)
            wp_enqueue_script( 'nammasociety51-resident-form-js', NAMMASOCIETY51_PLUGIN_URL . 'assets/js/nammasociety-resident-form.js', array( 'jquery', 'nammasociety51-dashboard-js' ), NAMMASOCIETY51_VERSION, true );
        }

		// Localize AJAX URL for frontend (needed for resident login)
		wp_localize_script( 'nammasociety51-bootstrap', 'nammasociety51_frontend', array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'version' => NAMMASOCIETY51_VERSION
		) );
		// Ensure global ajaxurl and nammasociety51_nonce fallbacks for legacy/inline scripts
		wp_add_inline_script( 'nammasociety51-bootstrap', '
			var ajaxurl = "' . esc_js( admin_url( 'admin-ajax.php' ) ) . '";
			var nammasociety51_nonce = "' . esc_js( wp_create_nonce( 'nammasociety51_frontend_nonce' ) ) . '";
		', 'before' );
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
	 * 2. OR if the page contains string '[nammasociety51_dashboard]' - AUTO APPLY.
	 */
	public function load_page_template( $template ) {
		global $post;
		$target = get_page_template_slug();

		// 1. Check if manually selected
		if ( $target === 'society-app.php' ) {
			return NAMMASOCIETY51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		// 2. Auto-detect Shortcode (Fallback if user can't select template)
		if ( isset( $post->post_content ) && has_shortcode( $post->post_content, 'nammasociety51_dashboard' ) ) {
			 return NAMMASOCIETY51_PLUGIN_DIR . 'templates/page-society-app.php';
		}

		return $template;
	}

	/**
	 * Seed resident_flat_map table for existing residents.
	 */
	public function seed_resident_flat_map() {
		$table_name = $this->db->get_table_name( 'resident_flat_map' );
		
		// Check if we've already done this migration
		if ( get_option( 'nammasociety51_flat_map_seeded' ) === NAMMASOCIETY51_VERSION ) {
			return;
		}

		$residents = $this->db->get( 'residents' );
		if ( empty( $residents ) ) {
			update_option( 'nammasociety51_flat_map_seeded', NAMMASOCIETY51_VERSION );
			return;
		}

		foreach ( $residents as $r ) {
			$resident_id = $r['id'] ?? '';
			$flat_no = $r['flat_no'] ?? '';
			if ( empty( $resident_id ) || empty( $flat_no ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL -- Table name is fully controlled via get_table_name(); value is prepared via %s placeholder.
			$exists = $this->db->wpdb->get_var( $this->db->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE resident_id = %s",
				$resident_id
			) );
			// phpcs:enable WordPress.DB.PreparedSQL

			if ( ! $exists ) {
				// Map it as primary
				$this->db->wpdb->insert( $table_name, array(
					'resident_id' => $resident_id,
					'flat_id'     => $flat_no,
					'is_primary'  => 1
				) );
			}
		}

		update_option( 'nammasociety51_flat_map_seeded', NAMMASOCIETY51_VERSION );
	}
}

/**
 * Initialize the plugin.
 */
function nammasociety51_init() {
	return NAMMASOCIETY51_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'nammasociety51_init' );

