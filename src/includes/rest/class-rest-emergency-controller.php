<?php
/**
 * REST API Controller for Emergency SOS & Security Alarms.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Emergency_Controller extends WP_REST_Controller {

	protected $namespace = 'society-hubx/v1';
	protected $rest_base = 'emergency';

	public function register_routes() {
		// 1. Trigger Emergency SOS Panic Alert
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/sos',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'trigger_sos' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 2. Emergency Contacts Directory
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/contacts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_contacts' ),
					'permission_callback' => '__return_true', // Publicly readable for emergencies
				),
			)
		);
	}

	public function check_auth() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_unauthorized', __( 'Please log in to continue.', 'society-hubx' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * POST /emergency/sos
	 */
	public function trigger_sos( WP_REST_Request $request ) {
		$type = sanitize_key( $request->get_param( 'type' ) ?? 'medical' ); // 'medical', 'fire', 'lift', 'security'
		$notes = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );

		$user_id = get_current_user_id();
		$user = wp_get_current_user();
		$db = new SHUBX51_DB_Router();
		$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );

		$flat_no = $resident ? ( $resident['flat_no'] ?? '' ) : ( get_user_meta( $user_id, 'flat_no', true ) ?: 'Unknown' );
		$block = $resident ? ( $resident['block'] ?? '' ) : '';
		$unit_label = trim( ( $block ? $block . '-' : '' ) . $flat_no );
		$phone = $resident ? ( $resident['phone'] ?? '' ) : ( $user->user_email );

		// 1. Log to Guard Audit Logs for immediate visibility on Security Guard App
		$db->insert( 'guard_logs', array(
			'guard_user_id' => 0, // Automated System Alarm
			'gate_id'       => 'All Gates',
			'action'        => 'emergency_sos_' . $type,
			'target_type'   => 'resident',
			'target_id'     => (string) $user_id,
			'details'       => sprintf( 'EMERGENCY SOS (%s) triggered by Unit %s (%s, %s). %s', strtoupper( $type ), $unit_label, $user->display_name, $phone, $notes ),
			'created_at'    => current_time( 'mysql' ),
		) );

		// 2. Dispatch Action for Push Notification / SMS / Siren
		do_action( 'shubx51_emergency_sos_triggered', array(
			'type'          => $type,
			'flat_no'       => $flat_no,
			'block'         => $block,
			'resident_name' => $user->display_name,
			'phone'         => $phone,
			'notes'         => $notes,
			'timestamp'     => current_time( 'mysql' ),
		) );

		return rest_ensure_response( array(
			'success'    => true,
			'status'     => 'dispatched',
			'message'    => sprintf( __( 'Emergency %s alert broadcast to Security Desk and Emergency Team.', 'society-hubx' ), strtoupper( $type ) ),
			'alert_info' => array(
				'type'      => $type,
				'unit'      => $unit_label,
				'timestamp' => current_time( 'mysql' ),
			),
		) );
	}

	/**
	 * GET /emergency/contacts
	 */
	public function get_contacts() {
		$gate_phone = get_option( 'shubx51_society_contact', '' );
		$emergency_phone = get_option( 'shubx51_society_emergency', $gate_phone );

		$contacts = array(
			array(
				'title'       => __( 'Security Main Gate', 'society-hubx' ),
				'phone'       => $gate_phone,
				'icon'        => 'shield-fill-check',
				'is_internal' => true,
			),
			array(
				'title'       => __( 'Society Estate Manager', 'society-hubx' ),
				'phone'       => $emergency_phone,
				'icon'        => 'person-badge',
				'is_internal' => true,
			),
			array(
				'title'       => __( 'National Emergency Helpline', 'society-hubx' ),
				'phone'       => '112',
				'icon'        => 'telephone-fill',
				'is_internal' => false,
			),
			array(
				'title'       => __( 'Ambulance', 'society-hubx' ),
				'phone'       => '108',
				'icon'        => 'heart-pulse-fill',
				'is_internal' => false,
			),
			array(
				'title'       => __( 'Fire Station', 'society-hubx' ),
				'phone'       => '101',
				'icon'        => 'fire',
				'is_internal' => false,
			),
			array(
				'title'       => __( 'Police Control Room', 'society-hubx' ),
				'phone'       => '100',
				'icon'        => 'shield-lock-fill',
				'is_internal' => false,
			),
		);

		return rest_ensure_response( array(
			'success'  => true,
			'contacts' => $contacts,
		) );
	}
}
