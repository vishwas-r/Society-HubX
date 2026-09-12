<?php
/**
 * Class: REST Vehicles Controller
 * Endpoints for managing society vehicle registry.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Vehicles_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'namma-society/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'vehicles';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get a collection of vehicles.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$vehicles = $db->get( 'vehicles' );

		if ( empty( $vehicles ) ) {
			return rest_ensure_response( array() );
		}

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'vehicles_view' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			// Resident only sees vehicles belonging to their flat
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$vehicles = array_filter(
				$vehicles,
				function( $v ) use ( $user_flat ) {
					return isset( $v['flat_no'] ) && $v['flat_no'] === $user_flat;
				}
			);
			$vehicles = array_values( $vehicles );
		}

		return rest_ensure_response( $vehicles );
	}

	/**
	 * Get a single vehicle.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$vehicles = $db->get( 'vehicles', array( 'id' => $id ) );

		if ( empty( $vehicles ) ) {
			return new WP_Error( 'rest_vehicle_not_found', __( 'Vehicle not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $vehicles[0] );
	}

	/**
	 * Create a vehicle.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$number = isset( $params['vehicle_number'] ) ? sanitize_text_field( $params['vehicle_number'] ) : ( isset( $params['number'] ) ? sanitize_text_field( $params['number'] ) : '' );
		$flat_no = isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '';

		if ( empty( $number ) || empty( $flat_no ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Vehicle number and flat number are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'vehicles_manage' ) || current_user_can( 'manage_options' );

		$data = array(
			'id'             => uniqid( 'veh_' ),
			'plate_no'       => $number,
			'number'         => $number,
			'flat_no'        => $flat_no,
			'type'           => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '4W',
			'brand'          => isset( $params['brand'] ) ? sanitize_text_field( $params['brand'] ) : ( isset( $params['make_model'] ) ? sanitize_text_field( $params['make_model'] ) : '' ),
			'model'          => isset( $params['model'] ) ? sanitize_text_field( $params['model'] ) : '',
			'sticker'        => isset( $params['sticker'] ) ? sanitize_text_field( $params['sticker'] ) : ( isset( $params['parking_slot'] ) ? sanitize_text_field( $params['parking_slot'] ) : '' ),
			'owner_name'     => isset( $params['owner_name'] ) ? sanitize_text_field( $params['owner_name'] ) : '',
			'status'         => $is_admin ? 'approved' : 'pending',
			'created_at'     => current_time( 'mysql' ),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'vehicles', $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $is_admin ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
			$rm = new NAMMASOCIETY51_Request_Manager();
			$rm->create_request( 'vehicles', 'add', $data, $data['id'] );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'vehicle' => $data ), 201 );
	}

	/**
	 * Update a vehicle.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'vehicles', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_vehicle_not_found', __( 'Vehicle not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['type'] ) ) {
			$data['type'] = sanitize_text_field( $params['type'] );
		}
		if ( isset( $params['make_model'] ) ) {
			$data['make_model'] = sanitize_text_field( $params['make_model'] );
		}
		if ( isset( $params['parking_slot'] ) ) {
			$data['parking_slot'] = sanitize_text_field( $params['parking_slot'] );
		}
		if ( isset( $params['status'] ) && current_user_can( 'manage_options' ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}

		$result = $db->update( 'vehicles', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Vehicle updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete / Archive a vehicle.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'vehicles', array( 'id' => $id ) );

		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_vehicle_not_found', __( 'Vehicle not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$result = $db->update( 'vehicles', array( 'status' => 'archived' ), array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Vehicle archived successfully.', 'namma-society' ) ) );
	}

	/**
	 * Permission check for reading vehicles.
	 */
	public function get_items_permissions_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	/**
	 * Permission check for reading single vehicle.
	 */
	public function get_item_permissions_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( $rbac->has_capability( $user_id, 'vehicles_view' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$vehicles = $db->get( 'vehicles', array( 'id' => $id ) );
		if ( ! empty( $vehicles ) ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			if ( $resident && ( $resident['flat_no'] ?? '' ) === ( $vehicles[0]['flat_no'] ?? '' ) ) {
				return true;
			}
		}

		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this vehicle.', 'namma-society' ), array( 'status' => 403 ) );
	}

	/**
	 * Permission check for creating a vehicle.
	 */
	public function create_item_permissions_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	/**
	 * Permission check for updating a vehicle.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}

	/**
	 * Permission check for deleting a vehicle.
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->get_item_permissions_check( $request );
	}
}

