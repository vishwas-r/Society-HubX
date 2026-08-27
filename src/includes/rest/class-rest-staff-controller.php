<?php
/**
 * Class: REST Staff Controller
 * Endpoints for managing society staff, daily help, attendance, and concerns.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Staff_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'staff';

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
					'permission_callback' => array( $this, 'manage_staff_permissions_check' ),
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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'manage_staff_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'manage_staff_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/attendance',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_attendance' ),
					'permission_callback' => array( $this, 'manage_staff_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/biometric-sync',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_biometric_sync' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/concerns',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_concern' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);
	}

	/**
	 * Get staff items.
	 */
	public function get_items( $request ) {
		$db = SHUBX51_Plugin::get_instance()->db;
		$staff = $db->get( 'daily_help' );

		if ( empty( $staff ) ) {
			return rest_ensure_response( array() );
		}

		$privileged = SHUBX51_Plugin::get_instance()->rbac->has_capability( get_current_user_id(), 'staff_manage' );
		
		foreach ( $staff as &$s ) {
			if ( ! $privileged ) {
				$s['phone'] = SHUBX51_Privacy_Manager::mask_data( $s['phone'] ?? '' );
			}
		}

		return rest_ensure_response( $staff );
	}

	/**
	 * Get single staff item.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = SHUBX51_Plugin::get_instance()->db;
		$staff = $db->get( 'daily_help', array( 'id' => $id ) );

		if ( empty( $staff ) ) {
			return new WP_Error( 'rest_staff_not_found', __( 'Staff member not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$s = $staff[0];
		$privileged = SHUBX51_Plugin::get_instance()->rbac->has_capability( get_current_user_id(), 'staff_manage' );
		if ( ! $privileged ) {
			$s['phone'] = SHUBX51_Privacy_Manager::mask_data( $s['phone'] ?? '' );
		}

		return rest_ensure_response( $s );
	}

	/**
	 * Create staff.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		if ( empty( $name ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Staff name is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$photo = isset( $params['profile_photo'] ) ? esc_url_raw( $params['profile_photo'] ) : ( isset( $params['photo_url'] ) ? esc_url_raw( $params['photo_url'] ) : '' );
		$data = array(
			'id'            => uniqid( 'staff_' ),
			'name'          => $name,
			'role'          => isset( $params['role'] ) ? sanitize_text_field( $params['role'] ) : 'Security',
			'category'      => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'Support Staff',
			'phone'         => isset( $params['phone'] ) ? sanitize_text_field( $params['phone'] ) : '',
			'profile_photo' => $photo,
			'flats_served'  => isset( $params['flats_served'] ) ? sanitize_text_field( $params['flats_served'] ) : '',
			'status'        => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'active',
			'created_at'    => current_time( 'mysql' ),
		);

		$db = SHUBX51_Plugin::get_instance()->db;
		$result = $db->insert( 'daily_help', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'staff' => $data ), 201 );
	}

	/**
	 * Update staff.
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = SHUBX51_Plugin::get_instance()->db;
		$existing = $db->get( 'daily_help', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_staff_not_found', __( 'Staff member not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( $params['name'] );
		}
		if ( isset( $params['role'] ) ) {
			$data['role'] = sanitize_text_field( $params['role'] );
		}
		if ( isset( $params['phone'] ) ) {
			$data['phone'] = sanitize_text_field( $params['phone'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['rating'] ) ) {
			$data['rating'] = floatval( $params['rating'] );
		}

		$result = $db->update( 'daily_help', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Staff member updated successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Delete staff.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = SHUBX51_Plugin::get_instance()->db;
		$result = $db->update( 'daily_help', array( 'status' => 'inactive' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Staff member archived successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Mark attendance.
	 */
	public function mark_attendance( $request ) {
		$params = $request->get_json_params();
		$staff_id = isset( $params['staff_id'] ) ? sanitize_text_field( $params['staff_id'] ) : '';
		$status   = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'present';

		if ( empty( $staff_id ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Staff ID is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'staff_id'   => $staff_id,
			'date'       => isset( $params['date'] ) ? sanitize_text_field( $params['date'] ) : gmdate( 'Y-m-d' ),
			'status'     => $status,
			'time_in'    => ( $status === 'present' ) ? gmdate( 'H:i:s' ) : null,
			'marked_by'  => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);

		$db = SHUBX51_Plugin::get_instance()->db;
		$result = $db->insert( 'staff_attendance', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Attendance marked successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Biometric Hardware Sync.
	 */
	public function handle_biometric_sync( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params['staff_id'] ) || empty( $params['status'] ) ) {
			return new WP_Error( 'missing_params', __( 'staff_id and status are required', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'staff_id'   => sanitize_text_field( $params['staff_id'] ),
			'date'       => ! empty( $params['date'] ) ? sanitize_text_field( $params['date'] ) : gmdate( 'Y-m-d' ),
			'status'     => sanitize_text_field( $params['status'] ),
			'time_in'    => ( $params['status'] === 'present' ) ? gmdate( 'H:i:s' ) : null,
			'marked_by'  => 0, // 0 = biometric device
			'created_at' => current_time( 'mysql' ),
		);

		$db = SHUBX51_Plugin::get_instance()->db;
		$result = $db->insert( 'staff_attendance', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => 'Attendance synced' ) );
	}

	/**
	 * Log staff concern.
	 */
	public function create_concern( $request ) {
		$params = $request->get_json_params();
		$staff_id = isset( $params['staff_id'] ) ? sanitize_text_field( $params['staff_id'] ) : '';
		$notes    = isset( $params['notes'] ) ? sanitize_textarea_field( $params['notes'] ) : '';

		if ( empty( $staff_id ) || empty( $notes ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Staff ID and notes are required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'staff_id'    => $staff_id,
			'flat_no'     => isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '',
			'notes'       => $notes,
			'reported_by' => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);

		$db = SHUBX51_Plugin::get_instance()->db;
		$result = $db->insert( 'staff_concerns', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Concern reported successfully.', 'society-hubx' ) ), 201 );
	}

	public function get_items_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		return $rbac->has_capability( get_current_user_id(), 'staff_view' ) || current_user_can( 'manage_options' );
	}

	public function manage_staff_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = SHUBX51_Plugin::get_instance()->rbac;
		return $rbac->has_capability( get_current_user_id(), 'staff_manage' ) || current_user_can( 'manage_options' );
	}

	public function user_logged_in_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}
}
