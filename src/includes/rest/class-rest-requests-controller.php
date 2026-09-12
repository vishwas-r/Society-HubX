<?php
/**
 * Class: REST Requests Controller
 * Endpoints for Request Approval Workflow (Resident Submissions, Admin Approvals).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Requests_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'requests';

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
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_process' ),
					'permission_callback' => array( $this, 'requests_manage_check' ),
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
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'requests_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_item' ),
					'permission_callback' => array( $this, 'requests_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject_item' ),
					'permission_callback' => array( $this, 'requests_manage_check' ),
				),
			)
		);
	}

	/**
	 * List approval requests.
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$requests = $db->get( 'requests' );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'requests_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$requests = array_filter(
				$requests,
				function( $r ) use ( $user_flat, $user_id ) {
					return ( isset( $r['flat_no'] ) && $r['flat_no'] === $user_flat ) || ( isset( $r['requested_by'] ) && (int) $r['requested_by'] === $user_id );
				}
			);
			$requests = array_values( $requests );
		}

		return rest_ensure_response( $requests ? $requests : array() );
	}

	/**
	 * Get single request details.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$requests = $db->get( 'requests', array( 'id' => $id ) );

		if ( empty( $requests ) ) {
			return new WP_Error( 'rest_request_not_found', __( 'Request not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $requests[0] );
	}

	/**
	 * Approve request.
	 */
	public function approve_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$result = $rm->approve_request( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Request approved successfully.', 'namma-society' ) ) );
	}

	/**
	 * Reject request.
	 */
	public function reject_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		$note = isset( $params['admin_note'] ) ? sanitize_textarea_field( $params['admin_note'] ) : '';

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$result = $rm->reject_request( $id, $note );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Request rejected successfully.', 'namma-society' ) ) );
	}

	/**
	 * Bulk process requests.
	 */
	public function bulk_process( $request ) {
		$params = $request->get_json_params();
		$ids = isset( $params['ids'] ) ? (array) $params['ids'] : array();
		$action = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : 'approve';
		$note = isset( $params['admin_note'] ) ? sanitize_textarea_field( $params['admin_note'] ) : '';

		if ( empty( $ids ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Request IDs are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$count = 0;

		foreach ( $ids as $id ) {
			$clean_id = sanitize_text_field( $id );
			if ( $action === 'approve' ) {
				$res = $rm->approve_request( $clean_id );
			} else {
				$res = $rm->reject_request( $clean_id, $note );
			}
			if ( ! is_wp_error( $res ) ) {
				$count++;
			}
		}

		return rest_ensure_response( array( 'success' => true, 'processed_count' => $count ) );
	}

	/**
	 * Create resident submission request.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$module       = isset( $params['module'] ) ? sanitize_text_field( $params['module'] ) : 'residents';
		$request_type = isset( $params['request_type'] ) ? sanitize_text_field( $params['request_type'] ) : 'general_request';
		$payload      = isset( $params['payload'] ) ? (array) $params['payload'] : array();
		$entity_id    = isset( $params['entity_id'] ) ? sanitize_text_field( $params['entity_id'] ) : uniqid();
		$flat_no      = isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '';

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$req_id = $rm->create_request( $module, $request_type, $payload, $entity_id, $flat_no );

		if ( is_wp_error( $req_id ) ) {
			return $req_id;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $req_id, 'message' => __( 'Request submitted successfully.', 'namma-society' ) ), 201 );
	}

	/**
	 * Delete approval request.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'requests', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Request deleted successfully.', 'namma-society' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function requests_manage_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'requests_manage' ) || $rbac->has_capability( get_current_user_id(), 'finance_manage' ) || current_user_can( 'manage_options' );
	}
}

