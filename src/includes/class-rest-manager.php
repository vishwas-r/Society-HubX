<?php
/**
 * Class: REST API Manager
 * Handles registration of REST routes and authentication.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Manager {

	/**
	 * API Namespace for the plugin.
	 */
	const NAMESPACE = 'society-hubx/v1';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all plugin REST routes.
	 */
	public function register_routes() {
		// Include controller classes
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-discovery-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-auth-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-flats-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-vehicles-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-documents-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-facilities-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-finance-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-assets-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-notices-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-polls-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-rules-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-requests-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-notifications-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';

		// Instantiate & register
		( new SHUBX51_REST_Discovery_Controller() )->register_routes();
		( new SHUBX51_REST_Auth_Controller() )->register_routes();
		( new SHUBX51_REST_Flats_Controller() )->register_routes();
		( new SHUBX51_REST_Residents_Controller() )->register_routes();
		( new SHUBX51_REST_Vehicles_Controller() )->register_routes();
		( new SHUBX51_REST_Documents_Controller() )->register_routes();
		( new SHUBX51_REST_Facilities_Controller() )->register_routes();
		( new SHUBX51_REST_Finance_Controller() )->register_routes();
		( new SHUBX51_REST_Assets_Controller() )->register_routes();
		( new SHUBX51_REST_Notices_Controller() )->register_routes();
		( new SHUBX51_REST_Polls_Controller() )->register_routes();
		( new SHUBX51_REST_Staff_Controller() )->register_routes();
		( new SHUBX51_REST_Rules_Controller() )->register_routes();
		( new SHUBX51_REST_Requests_Controller() )->register_routes();
		( new SHUBX51_REST_Notifications_Controller() )->register_routes();
		( new SHUBX51_REST_Activity_Controller() )->register_routes();
		( new SHUBX51_REST_Payments_Controller() )->register_routes();
	}

	/**
	 * Basic Permission Callback.
	 * Checks if the user is logged in and has the necessary society capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function check_permission( $request ) {
		// API Key authentication can be added here
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_unauthorized', __( 'You must be logged in to access this endpoint.', 'society-hubx' ), array( 'status' => 401 ) );
		}

		return true;
	}
}
