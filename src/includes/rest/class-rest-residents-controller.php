<?php
/**
 * Class: REST Residents Controller
 * Endpoints for managing society residents.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Residents_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'residents';

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/restore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/family',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_family_items' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_family_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get a list of residents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$db = SHUBX51_Plugin::get_instance()->db;
		$residents = $db->get( 'residents', array( 'load_relations' => true ) );

		if ( empty( $residents ) ) {
			return rest_ensure_response( array() );
		}

		$privileged = SHUBX51_Plugin::get_instance()->rbac->has_capability( get_current_user_id(), 'residents_manage' );

		foreach ( $residents as &$resident ) {
			if ( ! $privileged ) {
				$resident['phone'] = SHUBX51_Privacy_Manager::mask_data( $resident['phone'] ?? '' );
				$resident['email'] = SHUBX51_Privacy_Manager::mask_data( $resident['email'] ?? '' );
			}
		}

		return rest_ensure_response( $residents );
	}

	/**
	 * Get a single resident.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = $request->get_param( 'id' );
		$db = SHUBX51_Plugin::get_instance()->db;
		$resident = $db->get_row( 'residents', $id );

		if ( ! $resident ) {
			return new WP_Error( 'rest_resident_not_found', __( 'Resident not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$privileged = SHUBX51_Plugin::get_instance()->rbac->has_capability( get_current_user_id(), 'residents_manage' );
		if ( ! $privileged && intval( $resident['wp_user_id'] ?? 0 ) !== get_current_user_id() ) {
			$resident['phone'] = SHUBX51_Privacy_Manager::mask_data( $resident['phone'] ?? '' );
			$resident['email'] = SHUBX51_Privacy_Manager::mask_data( $resident['email'] ?? '' );
		}

		return rest_ensure_response( $resident );
	}

	/**
	 * Create a new resident.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$resident_manager = new SHUBX51_Resident_Manager();
		$result = $resident_manager->add_resident( $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $result ), 201 );
	}

	/**
	 * Update an existing resident.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id = $request->get_param( 'id' );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = SHUBX51_Plugin::get_instance()->db;
		$existing = $db->get_row( 'residents', $id );
		if ( ! $existing ) {
			return new WP_Error( 'rest_resident_not_found', __( 'Resident not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( $params['name'] );
		}
		if ( isset( $params['phone'] ) ) {
			$data['phone'] = sanitize_text_field( $params['phone'] );
		}
		if ( isset( $params['email'] ) ) {
			$data['email'] = sanitize_email( $params['email'] );
		}
		if ( isset( $params['type'] ) ) {
			$data['type'] = sanitize_text_field( $params['type'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['emergency_contact'] ) ) {
			$data['emergency_contact'] = sanitize_text_field( $params['emergency_contact'] );
		}

		$result = $db->update( 'residents', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Resident updated successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Archive / Delete a resident.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id = $request->get_param( 'id' );
		$resident_manager = new SHUBX51_Resident_Manager();
		$result = $resident_manager->archive_resident( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Resident archived successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Restore resident from history.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		$id = $request->get_param( 'id' );
		$resident_manager = new SHUBX51_Resident_Manager();
		$result = $resident_manager->restore_resident( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Resident restored successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Get family members for a resident flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_family_items( $request ) {
		$id = $request->get_param( 'id' );
		$db = SHUBX51_Plugin::get_instance()->db;
		$resident = $db->get_row( 'residents', $id );

		if ( ! $resident ) {
			return new WP_Error( 'rest_resident_not_found', __( 'Resident not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$flat_no = $resident['flat_no'] ?? '';
		$family = $db->get( 'family_members', array( 'where' => array( 'flat_no' => $flat_no ) ) );

		return rest_ensure_response( $family ? $family : array() );
	}

	/**
	 * Add family member for a resident flat.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_family_item( $request ) {
		$id = $request->get_param( 'id' );
		$db = SHUBX51_Plugin::get_instance()->db;
		$resident = $db->get_row( 'residents', $id );

		if ( ! $resident ) {
			return new WP_Error( 'rest_resident_not_found', __( 'Resident not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$name     = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		$relation = isset( $params['relation'] ) ? sanitize_text_field( $params['relation'] ) : '';

		if ( empty( $name ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Family member name is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'          => uniqid( 'fam_' ),
			'flat_no'     => $resident['flat_no'],
			'name'        => $name,
			'relation'    => $relation,
			'phone'       => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '',
			'email'       => isset( $params['email'] ) ? sanitize_email( $params['email'] ) : '',
			'created_at'  => current_time( 'mysql' ),
		);

		$result = $db->insert( 'family_members', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'] ), 201 );
	}

	/**
	 * Permissions check for viewing residents.
	 */
	public function get_items_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		return $rbac->has_capability( get_current_user_id(), 'residents_view' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Permissions check for viewing a single resident.
	 */
	public function get_item_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		if ( $rbac->has_capability( $user_id, 'residents_view' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		$id = $request->get_param( 'id' );
		$resident = SHUBX51_Plugin::get_instance()->db->get_row( 'residents', $id );
		if ( $resident && intval( $resident['wp_user_id'] ?? 0 ) === $user_id ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this resident record.', 'society-hubx' ), array( 'status' => 403 ) );
	}

	/**
	 * Permissions check for creating/managing residents.
	 */
	public function create_item_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		return $rbac->has_capability( get_current_user_id(), 'residents_manage' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Permissions check for updating a resident.
	 */
	public function update_item_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		if ( $rbac->has_capability( $user_id, 'residents_manage' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		$id = $request->get_param( 'id' );
		$resident = SHUBX51_Plugin::get_instance()->db->get_row( 'residents', $id );
		if ( $resident && intval( $resident['wp_user_id'] ?? 0 ) === $user_id ) {
			return true;
		}
		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to update this resident.', 'society-hubx' ), array( 'status' => 403 ) );
	}

	/**
	 * Permissions check for deleting a resident.
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->create_item_permissions_check( $request );
	}
}
