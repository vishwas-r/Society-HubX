<?php
/**
 * REST API Controller for Visitor Management (VMS) & Gate Passes.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Visitors_Controller extends WP_REST_Controller {

	protected $namespace = 'society-hubx/v1';
	protected $rest_base = 'visitors';

	public function register_routes() {
		// 1. Visitor Passes (Pre-approved guest passes)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/passes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_passes' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_pass' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/passes/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'revoke_pass' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 2. Gate Visitor Logs (Live check-ins for the flat)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_visitor_logs' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 3. Instant Resident Gate Response (Allow / Deny / Leave at Gate)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/action',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'respond_visitor_entry' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);
	}

	public function check_auth( $request = null ) {
		$auth = SHUBX51_REST_Manager::authenticate_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		return true;
	}

	/**
	 * Get resident's flat identifier.
	 */
	private function get_current_user_flat() {
		$user_id = get_current_user_id();
		$db = new SHUBX51_DB_Router();
		$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );
		return $resident ? ( $resident['flat_no'] ?? '' ) : '';
	}

	/**
	 * GET /visitors/passes
	 */
	public function get_passes( WP_REST_Request $request ) {
		$db = new SHUBX51_DB_Router();
		$is_admin = current_user_can( 'manage_options' );
		$user_flat = $this->get_current_user_flat();

		$args = array(
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'where'   => array(),
		);

		if ( ! $is_admin && ! empty( $user_flat ) ) {
			$args['where']['flat_no'] = $user_flat;
		}

		$status = sanitize_text_field( $request->get_param( 'status' ) );
		if ( ! empty( $status ) ) {
			$args['where']['status'] = $status;
		}

		$passes = $db->get( 'visitor_passes', $args );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $passes ? $passes : array(),
			'passes'  => $passes ? $passes : array(),
		) );
	}

	/**
	 * POST /visitors/passes
	 */
	public function create_pass( WP_REST_Request $request ) {
		$visitor_name = sanitize_text_field( $request->get_param( 'visitor_name' ) );
		$phone = sanitize_text_field( $request->get_param( 'phone' ) ?? '' );
		$purpose = sanitize_text_field( $request->get_param( 'purpose' ) ?? 'Guest' );
		$valid_from = sanitize_text_field( $request->get_param( 'valid_from' ) ?? current_time( 'mysql' ) );
		$valid_until = sanitize_text_field( $request->get_param( 'valid_until' ) ?? date( 'Y-m-d 23:59:59', strtotime( '+1 day' ) ) );

		if ( empty( $visitor_name ) ) {
			return new WP_Error( 'missing_params', __( 'Visitor name is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$db = new SHUBX51_DB_Router();
		$user_id = get_current_user_id();
		$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );

		$flat_no = $resident ? ( $resident['flat_no'] ?? '' ) : sanitize_text_field( $request->get_param( 'flat_no' ) ?? '' );
		$block = $resident ? ( $resident['block'] ?? '' ) : '';

		// Generate cryptographically secure 6-digit OTP passcode
		$pass_code = (string) wp_rand( 100000, 999999 );
		$pass_id = uniqid( 'pass_' );

		$pass_data = array(
			'id'           => $pass_id,
			'resident_id'  => (string) ( $resident ? $resident['id'] : $user_id ),
			'block'        => $block,
			'flat_no'      => $flat_no,
			'visitor_name' => $visitor_name,
			'phone'        => $phone,
			'pass_code'    => $pass_code,
			'purpose'      => $purpose,
			'valid_from'   => $valid_from,
			'valid_until'  => $valid_until,
			'status'       => 'valid',
			'created_at'   => current_time( 'mysql' ),
		);

		$result = $db->insert( 'visitor_passes', $pass_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Visitor gate pass generated successfully.', 'society-hubx' ),
			'data'    => $pass_data,
			'pass'    => $pass_data,
		) );
	}

	/**
	 * DELETE /visitors/passes/:id
	 */
	public function revoke_pass( WP_REST_Request $request ) {
		$pass_id = sanitize_key( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();

		$pass = $db->get_row_by_field( 'visitor_passes', 'id', $pass_id );
		if ( ! $pass ) {
			return new WP_Error( 'not_found', __( 'Visitor pass not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$db->update( 'visitor_passes', array( 'status' => 'revoked' ), array( 'id' => $pass_id ) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Pass revoked successfully.', 'society-hubx' ),
		) );
	}

	/**
	 * GET /visitors/logs
	 */
	public function get_visitor_logs( WP_REST_Request $request ) {
		$db = new SHUBX51_DB_Router();
		$is_admin = current_user_can( 'manage_options' );
		$user_flat = $this->get_current_user_flat();

		$args = array(
			'orderby' => 'check_in',
			'order'   => 'DESC',
			'limit'   => 50,
			'where'   => array(),
		);

		if ( ! $is_admin && ! empty( $user_flat ) ) {
			$args['where']['flat_no'] = $user_flat;
		}

		$logs = $db->get( 'visitors', $args );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $logs ? $logs : array(),
			'logs'    => $logs ? $logs : array(),
		) );
	}

	/**
	 * POST /visitors/:id/action
	 * Quick resident actions: allow, deny, leave_at_gate
	 */
	public function respond_visitor_entry( WP_REST_Request $request ) {
		$visitor_id = sanitize_key( $request->get_param( 'id' ) );
		$action = sanitize_key( $request->get_param( 'action' ) ?? 'allow' ); // 'allow', 'deny', 'leave_at_gate'

		$db = new SHUBX51_DB_Router();
		$visitor = $db->get_row_by_field( 'visitors', 'id', $visitor_id );

		if ( ! $visitor ) {
			return new WP_Error( 'not_found', __( 'Visitor entry not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$status_map = array(
			'allow'         => 'approved',
			'deny'          => 'denied',
			'leave_at_gate' => 'parcel_at_gate',
		);
		$new_status = $status_map[ $action ] ?? 'approved';

		$db->update( 'visitors', array( 'status' => $new_status ), array( 'id' => $visitor_id ) );

		// Log guard audit entry
		$db->insert( 'guard_logs', array(
			'guard_user_id' => get_current_user_id(),
			'gate_id'       => $visitor['gate_id'] ?? 'Main Gate',
			'action'        => 'resident_' . $action,
			'target_type'   => 'visitor',
			'target_id'     => $visitor_id,
			'details'       => sprintf( 'Resident responded: %s for %s (%s)', strtoupper( $action ), $visitor['visitor_name'], $visitor['flat_no'] ),
			'created_at'    => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array(
			'success'    => true,
			'new_status' => $new_status,
			'message'    => sprintf( __( 'Visitor entry marked as %s.', 'society-hubx' ), $new_status ),
		) );
	}
}
