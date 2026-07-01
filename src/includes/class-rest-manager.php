<?php
/**
 * Class: REST API Manager
 * Handles registration of REST routes and authentication.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_REST_Manager {

	/**
	 * API Namespace for the plugin.
	 */
	const NAMESPACE = 'society-nestx/v1';

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
		// Residents Controller
		$resident_controller = new SNESTX51_REST_Residents_Controller();
		$resident_controller->register_routes();

		// Staff Controller
		$staff_controller = new SNESTX51_REST_Staff_Controller();
		$staff_controller->register_routes();

		// Activity Controller
		$activity_controller = new SNESTX51_REST_Activity_Controller();
		$activity_controller->register_routes();

		// Payments Controller (Webhooks & Polling)
		$payments_controller = new SNESTX51_REST_Payments_Controller();
		$payments_controller->register_routes();
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
			return new WP_Error( 'rest_unauthorized', __( 'You must be logged in to access this endpoint.', 'society-nestx' ), array( 'status' => 401 ) );
		}

		return true;
	}
}
