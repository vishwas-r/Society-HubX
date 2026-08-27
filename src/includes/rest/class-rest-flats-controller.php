<?php
/**
 * Class: REST Flats Controller
 * Endpoints for managing society flats and units.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Flats_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'society-hubx/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'flats';

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
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)',
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/restore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get a collection of flats.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$db = new SHUBX51_DB_Router();
		$flats = $db->get( 'flats' );

		if ( empty( $flats ) ) {
			return rest_ensure_response( array() );
		}

		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			$flats = array_filter(
				$flats,
				function( $item ) use ( $status ) {
					return isset( $item['status'] ) && $item['status'] === $status;
				}
			);
			$flats = array_values( $flats );
		}

		return rest_ensure_response( $flats );
	}

	/**
	 * Get a single flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$flats = $db->get( 'flats', array( 'id' => $id ) );

		if ( empty( $flats ) ) {
			return new WP_Error( 'rest_flat_not_found', __( 'Flat not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $flats[0] );
	}

	/**
	 * Create a flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$block       = isset( $params['block'] ) ? sanitize_text_field( $params['block'] ) : '';
		$flat_number = isset( $params['flat_number'] ) ? sanitize_text_field( $params['flat_number'] ) : '';

		if ( empty( $block ) || empty( $flat_number ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Block and flat number are required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'             => $block . '-' . $flat_number,
			'block'          => $block,
			'flat_number'    => $flat_number,
			'floor'          => isset( $params['floor'] ) ? sanitize_text_field( $params['floor'] ) : '',
			'sq_foot'        => isset( $params['sq_foot'] ) ? floatval( $params['sq_foot'] ) : 0,
			'parking_slot'   => isset( $params['parking_slot'] ) ? sanitize_text_field( $params['parking_slot'] ) : '',
			'type'           => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '2BHK',
			'status'         => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'vacant',
			'parking_status' => isset( $params['parking_status'] ) ? sanitize_text_field( $params['parking_status'] ) : 'Available',
		);

		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'flats', array( 'id' => $data['id'] ) );
		if ( ! empty( $existing ) ) {
			return new WP_Error( 'rest_flat_exists', __( 'Flat already exists.', 'society-hubx' ), array( 'status' => 409 ) );
		}

		$result = $db->insert( 'flats', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'flat' => $data ), 201 );
	}

	/**
	 * Update a flat.
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

		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'flats', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_flat_not_found', __( 'Flat not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['floor'] ) ) {
			$data['floor'] = sanitize_text_field( $params['floor'] );
		}
		if ( isset( $params['sq_foot'] ) ) {
			$data['sq_foot'] = floatval( $params['sq_foot'] );
		}
		if ( isset( $params['parking_slot'] ) ) {
			$data['parking_slot'] = sanitize_text_field( $params['parking_slot'] );
		}
		if ( isset( $params['type'] ) ) {
			$data['type'] = sanitize_text_field( $params['type'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['parking_status'] ) ) {
			$data['parking_status'] = sanitize_text_field( $params['parking_status'] );
		}

		$result = $db->update( 'flats', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Flat updated successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Archive / Soft-delete a flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'flats', array( 'id' => $id ) );

		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_flat_not_found', __( 'Flat not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$hard = $request->get_param( 'force' );
		if ( $hard ) {
			$result = $db->delete( 'flats', array( 'id' => $id ) );
		} else {
			$result = $db->update( 'flats', array( 'status' => 'archived' ), array( 'id' => $id ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Flat removed successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Restore an archived flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$result = $db->update( 'flats', array( 'status' => 'vacant' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Flat restored successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Permission check for reading flats.
	 */
	public function get_items_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = new SHUBX51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'flats_view' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for reading single flat.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Permission check for creating flats.
	 */
	public function create_item_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = new SHUBX51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'flats_manage' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for updating flats.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}

	/**
	 * Permission check for deleting flats.
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}
}
