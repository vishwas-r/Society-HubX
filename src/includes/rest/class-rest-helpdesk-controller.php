<?php
/**
 * REST API Controller for Helpdesk, Complaints, & Service Tickets.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Helpdesk_Controller extends WP_REST_Controller {

	protected $namespace = 'society-hubx/v1';
	protected $rest_base = 'helpdesk';

	public function register_routes() {
		// 1. Tickets List & Create
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tickets',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tickets' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_ticket' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 2. Ticket Detail
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tickets/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ticket' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 3. Ticket Conversation Replies
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tickets/(?P<id>[a-zA-Z0-9_-]+)/reply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reply_ticket' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 4. Ticket Rating & Review (Resident)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tickets/(?P<id>[a-zA-Z0-9_-]+)/rate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rate_ticket' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 5. Verify Closure OTP (Technician / Staff verification)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/tickets/(?P<id>[a-zA-Z0-9_-]+)/close',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'close_ticket_with_otp' ),
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

	private function get_current_user_flat() {
		$user_id = get_current_user_id();
		$db = new SHUBX51_DB_Router();
		$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );
		return $resident ? ( $resident['flat_no'] ?? '' ) : '';
	}

	/**
	 * GET /helpdesk/tickets
	 */
	public function get_tickets( WP_REST_Request $request ) {
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

		$category = sanitize_text_field( $request->get_param( 'category' ) );
		if ( ! empty( $category ) ) {
			$args['where']['category'] = $category;
		}

		$tickets = $db->get( 'helpdesk_tickets', $args );

		return rest_ensure_response( array(
			'success' => true,
			'tickets' => $tickets ? $tickets : array(),
		) );
	}

	/**
	 * POST /helpdesk/tickets
	 */
	public function create_ticket( WP_REST_Request $request ) {
		$category = sanitize_text_field( $request->get_param( 'category' ) ?? 'general' );
		$subject = sanitize_text_field( $request->get_param( 'subject' ) );
		$description = sanitize_textarea_field( $request->get_param( 'description' ) ?? '' );
		$priority = sanitize_text_field( $request->get_param( 'priority' ) ?? 'medium' );
		$photos = $request->get_param( 'photos' );

		if ( empty( $subject ) ) {
			return new WP_Error( 'missing_subject', __( 'Subject is required for creating a ticket.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$db = new SHUBX51_DB_Router();
		$user_id = get_current_user_id();
		$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );

		$flat_no = $resident ? ( $resident['flat_no'] ?? '' ) : sanitize_text_field( $request->get_param( 'flat_no' ) ?? '' );
		$block = $resident ? ( $resident['block'] ?? '' ) : '';

		// Calculate SLA due date
		$sla_hours = ( $priority === 'urgent' ) ? 4 : ( ( $priority === 'high' ) ? 12 : 48 );
		$sla_due_date = date( 'Y-m-d H:i:s', strtotime( "+{$sla_hours} hours" ) );

		// 4-digit closure OTP
		$closure_otp = (string) wp_rand( 1000, 9999 );
		$ticket_id = uniqid( 'tkt_' );
		$ticket_number = 'TKT-' . date( 'Ym' ) . '-' . strtoupper( substr( md5( uniqid( '', true ) ), 0, 4 ) );

		$ticket_data = array(
			'id'            => $ticket_id,
			'ticket_number' => $ticket_number,
			'block'         => $block,
			'flat_no'       => $flat_no,
			'resident_id'   => (string) ( $resident ? $resident['id'] : $user_id ),
			'category'      => $category,
			'subcategory'   => sanitize_text_field( $request->get_param( 'subcategory' ) ?? '' ),
			'priority'      => $priority,
			'status'        => 'open',
			'subject'       => $subject,
			'description'   => $description,
			'photos'        => is_array( $photos ) ? wp_json_encode( $photos ) : ( $photos ?: '[]' ),
			'assigned_to'   => 0,
			'sla_due_date'  => $sla_due_date,
			'closure_otp'   => $closure_otp,
			'rating'        => 0,
			'feedback'      => '',
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$result = $db->insert( 'helpdesk_tickets', $ticket_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Service ticket raised successfully.', 'society-hubx' ),
			'ticket'  => $ticket_data,
		) );
	}

	/**
	 * GET /helpdesk/tickets/:id
	 */
	public function get_ticket( WP_REST_Request $request ) {
		$ticket_id = sanitize_key( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();

		$ticket = $db->get_row_by_field( 'helpdesk_tickets', 'id', $ticket_id );
		if ( ! $ticket ) {
			$ticket = $db->get_row_by_field( 'helpdesk_tickets', 'ticket_number', $ticket_id );
		}

		if ( ! $ticket ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		// Fetch replies
		$replies = $db->get( 'ticket_replies', array(
			'where'   => array( 'ticket_id' => $ticket['id'] ),
			'orderby' => 'created_at',
			'order'   => 'ASC',
		) );

		return rest_ensure_response( array(
			'success' => true,
			'ticket'  => $ticket,
			'replies' => $replies ? $replies : array(),
		) );
	}

	/**
	 * POST /helpdesk/tickets/:id/reply
	 */
	public function reply_ticket( WP_REST_Request $request ) {
		$ticket_id = sanitize_key( $request->get_param( 'id' ) );
		$message = sanitize_textarea_field( $request->get_param( 'message' ) );

		if ( empty( $message ) ) {
			return new WP_Error( 'missing_message', __( 'Reply message cannot be empty.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$db = new SHUBX51_DB_Router();
		$ticket = $db->get_row_by_field( 'helpdesk_tickets', 'id', $ticket_id );
		if ( ! $ticket ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$reply_data = array(
			'ticket_id'        => $ticket['id'],
			'user_id'          => get_current_user_id(),
			'message'          => $message,
			'attachments'      => '[]',
			'is_internal_note' => 0,
			'created_at'       => current_time( 'mysql' ),
		);

		$db->insert( 'ticket_replies', $reply_data );
		$db->update( 'helpdesk_tickets', array( 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $ticket['id'] ) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Reply posted successfully.', 'society-hubx' ),
			'reply'   => $reply_data,
		) );
	}

	/**
	 * POST /helpdesk/tickets/:id/rate
	 */
	public function rate_ticket( WP_REST_Request $request ) {
		$ticket_id = sanitize_key( $request->get_param( 'id' ) );
		$rating = min( 5, max( 1, intval( $request->get_param( 'rating' ) ) ) );
		$feedback = sanitize_textarea_field( $request->get_param( 'feedback' ) ?? '' );

		$db = new SHUBX51_DB_Router();
		$ticket = $db->get_row_by_field( 'helpdesk_tickets', 'id', $ticket_id );
		if ( ! $ticket ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$db->update( 'helpdesk_tickets', array(
			'rating'   => $rating,
			'feedback' => $feedback,
		), array( 'id' => $ticket['id'] ) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Feedback submitted successfully.', 'society-hubx' ),
		) );
	}

	/**
	 * POST /helpdesk/tickets/:id/close
	 * Technician submits closure OTP given by resident upon job completion.
	 */
	public function close_ticket_with_otp( WP_REST_Request $request ) {
		$ticket_id = sanitize_key( $request->get_param( 'id' ) );
		$entered_otp = sanitize_text_field( $request->get_param( 'otp' ) );

		$db = new SHUBX51_DB_Router();
		$ticket = $db->get_row_by_field( 'helpdesk_tickets', 'id', $ticket_id );
		if ( ! $ticket ) {
			return new WP_Error( 'not_found', __( 'Ticket not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		if ( empty( $entered_otp ) || ! hash_equals( (string) $ticket['closure_otp'], (string) $entered_otp ) ) {
			return new WP_Error( 'invalid_otp', __( 'Invalid closure OTP. Ask resident for the 4-digit code.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$db->update( 'helpdesk_tickets', array(
			'status'      => 'resolved',
			'resolved_at' => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		), array( 'id' => $ticket['id'] ) );

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Ticket marked as resolved successfully via OTP confirmation.', 'society-hubx' ),
		) );
	}
}
