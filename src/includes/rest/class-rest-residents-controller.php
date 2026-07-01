<?php
/**
 * Class: REST Residents Controller
 * Endpoints for managing society residents.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Residents_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 */
	protected $namespace = 'society-hubx/v1';

	/**
	 * Route base.
	 */
	protected $rest_base = 'residents';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( 'SHUBX51_REST_Manager', 'check_permission' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\w-]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( 'SHUBX51_REST_Manager', 'check_permission' ),
			),
		) );
	}

	/**
	 * Get a list of residents.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$db = Society_HubX::get_instance()->db;
		$residents = $db->get( 'residents' );

		if ( empty( $residents ) ) {
			return rest_ensure_response( array() );
		}

		// Apply DPDP masking
		$privileged = Society_HubX::get_instance()->rbac->check_capability( get_current_user_id(), 'view_pii' );
		
		foreach ( $residents as &$resident ) {
			if ( ! $privileged ) {
				$resident['phone'] = SHUBX51_Privacy_Manager::mask_data( $resident['phone'] );
				$resident['email'] = SHUBX51_Privacy_Manager::mask_data( $resident['email'] );
			}
		}

		return rest_ensure_response( $residents );
	}

	/**
	 * Get a single resident.
	 */
	public function get_item( $request ) {
		$id = $request->get_param( 'id' );
		$db = Society_HubX::get_instance()->db;
		
		$resident = $db->get_row( 'residents', $id );

		if ( ! $resident ) {
			return new WP_Error( 'rest_resident_not_found', __( 'Resident not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		// Apply masking if not committee
		$privileged = Society_HubX::get_instance()->rbac->check_capability( get_current_user_id(), 'view_pii' );
		if ( ! $privileged ) {
			$resident['phone'] = SHUBX51_Privacy_Manager::mask_data( $resident['phone'] );
			$resident['email'] = SHUBX51_Privacy_Manager::mask_data( $resident['email'] );
		}

		return rest_ensure_response( $resident );
	}

	/**
	 * Create a new resident.
	 */
	public function create_item( $request ) {
		$params = $request->get_params();
		$resident_manager = new SHUBX51_Resident_Manager();
		
		// Use the existing manager to handle business logic
		$result = $resident_manager->add_resident( $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'id' => $result ) );
	}

	/**
	 * Permissions check for creating a resident.
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! Society_HubX::get_instance()->rbac->check_capability( get_current_user_id(), 'manage_residents' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage residents.', 'society-hubx' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
