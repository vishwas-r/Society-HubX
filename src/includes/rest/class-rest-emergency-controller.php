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

		// 2. Emergency Alerts Incident Feed & Forensics
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alerts',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_emergency_alerts' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 3. Acknowledge Alert (Admin / Guard)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alerts/(?P<id>[\w-]+)/acknowledge',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'acknowledge_alert' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 4. Resolve Alert (Admin / Guard)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alerts/(?P<id>[\w-]+)/resolve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resolve_alert' ),
					'permission_callback' => array( $this, 'check_auth' ),
				),
			)
		);

		// 5. Emergency Contacts Directory
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

	public function check_auth( $request = null ) {
		$auth = SHUBX51_REST_Manager::authenticate_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		return true;
	}

	/**
	 * POST /emergency/sos
	 * Records full forensic evidence of emergency trigger and notifies all channels.
	 */
	public function trigger_sos( WP_REST_Request $request ) {
		$type  = sanitize_key( $request->get_param( 'type' ) ?? 'medical' ); // 'medical', 'fire', 'lift', 'security'
		$notes = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();
		$db      = new SHUBX51_DB_Router();
		$resident = $user_id ? $db->get_row_by_field( 'residents', 'wp_user_id', $user_id ) : null;

		// Dynamically resolve Block & Flat (Zero hardcoding)
		$block   = sanitize_text_field( $request->get_param( 'block' ) ?? ( $resident['block'] ?? '' ) );
		$flat_no = sanitize_text_field( $request->get_param( 'flat_no' ) ?? ( $resident['flat_no'] ?? '' ) );

		if ( empty( $block ) && ! empty( $resident['block'] ) ) {
			$block = $resident['block'];
		}
		if ( empty( $flat_no ) && ! empty( $resident['flat_no'] ) ) {
			$flat_no = $resident['flat_no'];
		}

		$unit_label = trim( ( $block ? $block . '-' : '' ) . ( $flat_no ? $flat_no : ( current_user_can( 'manage_options' ) ? 'Management Desk' : 'Resident Unit' ) ) );
		$phone = ! empty( $resident['phone'] ) ? $resident['phone'] : ( $user ? $user->user_email : 'N/A' );
		$name  = $user && ! empty( $user->display_name ) ? $user->display_name : ( $resident['name'] ?? 'Resident' );

		// Capture device forensics & Client IP
		$ip_address = sanitize_text_field(
			$_SERVER['HTTP_CF_CONNECTING_IP'] ?? ( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '' )[0] ) ?: ( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' )
		);
		$device_name  = sanitize_text_field( $request->get_param( 'device_name' ) ?? 'Mobile Device' );
		$device_model = sanitize_text_field( $request->get_param( 'device_model' ) ?? 'Android' );
		$platform     = sanitize_key( $request->get_param( 'platform' ) ?? 'android' );
		$app_version  = sanitize_text_field( $request->get_param( 'app_version' ) ?? '1.0.0' );

		$alert_id = 'SOS-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false ) );
		$now = current_time( 'mysql' );

		// 1. Permanently store forensic audit record in emergency_alerts table
		$alert_record = array(
			'alert_id'      => $alert_id,
			'user_id'       => $user_id,
			'username'      => $user ? $user->user_login : 'anonymous',
			'resident_name' => $name,
			'block'         => $block,
			'flat_no'       => $flat_no,
			'phone'         => $phone,
			'email'         => $user ? $user->user_email : '',
			'sos_type'      => strtoupper( $type ),
			'notes'         => $notes,
			'device_name'   => $device_name,
			'device_model'  => $device_model,
			'platform'      => $platform,
			'app_version'   => $app_version,
			'ip_address'    => $ip_address,
			'status'        => 'active',
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$db->insert( 'emergency_alerts', $alert_record );

		// 2. Log to Guard Audit Logs for immediate visibility on Security Guard Console
		$db->insert( 'guard_logs', array(
			'guard_user_id' => 0, // Automated System Alarm
			'gate_id'       => 'All Gates',
			'action'        => 'emergency_sos_' . $type,
			'target_type'   => 'resident',
			'target_id'     => (string) $user_id,
			'details'       => sprintf( 'EMERGENCY SOS (%s) [%s] by %s (%s, %s, IP: %s, Device: %s). %s', strtoupper( $type ), $alert_id, $unit_label, $name, $phone, $ip_address, $device_name, $notes ),
			'created_at'    => $now,
		) );

		// 3. Log to System Activity Logs for Activity Hub audit
		$db->insert( 'activity_logs', array(
			'user_id'    => $user_id,
			'action'     => 'emergency_sos',
			'target'     => $unit_label,
			'details'    => sprintf( 'Emergency %s alert triggered by %s (%s). Alert ID: %s', strtoupper( $type ), $name, $unit_label, $alert_id ),
			'ip_address' => $ip_address,
			'created_at' => $now,
		) );

		// 4. In-App Notifications for sender, all administrators, and all residents
		$alert_title = sprintf( '🚨 EMERGENCY SOS: %s (%s)', $unit_label, strtoupper( $type ) );
		$alert_msg   = sprintf( '%s (%s, Phone: %s) triggered a %s emergency alarm. %s', $name, $unit_label, $phone, strtoupper( $type ), $notes ? 'Note: ' . $notes : 'Immediate response requested!' );

		// Sender confirmation
		if ( $user_id > 0 ) {
			$db->insert( 'inapp_notifications', array(
				'user_id'    => $user_id,
				'event_slug' => 'emergency_sos',
				'title'      => '🚨 SOS Broadcast Confirmed',
				'content'    => sprintf( 'Your emergency %s alert (%s) has been broadcast to security teams and residents.', strtoupper( $type ), $alert_id ),
				'is_read'    => 0,
				'created_at' => $now,
			) );
		}

		// Notify all admins
		$admin_users = get_users( array( 'role' => 'administrator' ) );
		$notified_uids = array( $user_id );
		foreach ( $admin_users as $adm ) {
			if ( (int) $adm->ID !== (int) $user_id ) {
				$notified_uids[] = (int) $adm->ID;
				$db->insert( 'inapp_notifications', array(
					'user_id'    => (int) $adm->ID,
					'event_slug' => 'emergency_sos',
					'title'      => $alert_title,
					'content'    => $alert_msg,
					'is_read'    => 0,
					'created_at' => $now,
				) );
			}
		}

		// Notify all residents
		$all_residents = $db->get( 'residents' );
		foreach ( $all_residents as $res_item ) {
			$r_uid = (int) ( $res_item['wp_user_id'] ?? 0 );
			if ( $r_uid > 0 && ! in_array( $r_uid, $notified_uids, true ) ) {
				$notified_uids[] = $r_uid;
				$db->insert( 'inapp_notifications', array(
					'user_id'    => $r_uid,
					'event_slug' => 'emergency_sos',
					'title'      => $alert_title,
					'content'    => $alert_msg,
					'is_read'    => 0,
					'created_at' => $now,
				) );
			}
		}

		// 5. Post urgent Emergency Notice on Notice Board
		$db->insert( 'notices', array(
			'title'      => $alert_title,
			'content'    => sprintf( '<p><strong>%s</strong></p><p>Resident: %s<br>Unit: %s<br>Phone: %s<br>Alert ID: <code>%s</code></p><p>%s</p>', esc_html( $alert_msg ), esc_html( $name ), esc_html( $unit_label ), esc_html( $phone ), esc_html( $alert_id ), esc_html( $notes ) ),
			'category'   => 'Security & Emergency',
			'urgency'    => 'emergency',
			'target'     => 'all',
			'author_id'  => $user_id,
			'status'     => 'published',
			'created_at' => $now,
		) );

		// 6. Dispatch Action for Push Notification / SMS / Siren
		do_action( 'shubx51_emergency_sos_triggered', array(
			'alert_id'           => $alert_id,
			'type'               => $type,
			'flat_no'            => $flat_no,
			'block'              => $block,
			'unit_label'         => $unit_label,
			'resident_name'      => $name,
			'triggered_by_name'  => $name,
			'phone'              => $phone,
			'device_name'        => $device_name,
			'ip_address'         => $ip_address,
			'notes'              => $notes,
			'timestamp'          => $now,
		) );

		return rest_ensure_response( array(
			'success'    => true,
			'status'     => 'active',
			'alert_id'   => $alert_id,
			'message'    => sprintf( __( 'Emergency %s alert (%s) broadcast to Security Desk and Emergency Teams.', 'society-hubx' ), strtoupper( $type ), $alert_id ),
			'alert_info' => array(
				'alert_id'    => $alert_id,
				'type'        => $type,
				'unit'        => $unit_label,
				'resident'    => $name,
				'phone'       => $phone,
				'device_name' => $device_name,
				'ip_address'  => $ip_address,
				'timestamp'   => $now,
			),
		) );
	}

	/**
	 * GET /emergency/alerts
	 * Returns emergency alerts list with forensic metadata.
	 */
	public function get_emergency_alerts( WP_REST_Request $request ) {
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? 'all' );
		$db = new SHUBX51_DB_Router();

		$args = array();
		if ( ! empty( $status ) && 'all' !== $status ) {
			$args['where'] = array( 'status' => $status );
		}

		$alerts = $db->get( 'emergency_alerts', $args );
		if ( empty( $alerts ) ) {
			$alerts = array();
		}

		usort( $alerts, function( $a, $b ) {
			return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
		} );

		return rest_ensure_response( $alerts );
	}

	/**
	 * POST /emergency/alerts/(?P<id>[\w-]+)/acknowledge
	 */
	public function acknowledge_alert( WP_REST_Request $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$user = wp_get_current_user();
		$user_id = get_current_user_id();
		$db = new SHUBX51_DB_Router();

		$now = current_time( 'mysql' );
		$result = $db->update(
			'emergency_alerts',
			array(
				'status'                => 'acknowledged',
				'acknowledged_by'       => $user_id,
				'acknowledged_by_name'  => $user ? $user->display_name : 'Admin',
				'acknowledged_at'       => $now,
				'updated_at'            => $now,
			),
			array( 'alert_id' => $id )
		);

		if ( ! $result ) {
			// Try by numerical id
			$db->update(
				'emergency_alerts',
				array(
					'status'                => 'acknowledged',
					'acknowledged_by'       => $user_id,
					'acknowledged_by_name'  => $user ? $user->display_name : 'Admin',
					'acknowledged_at'       => $now,
					'updated_at'            => $now,
				),
				array( 'id' => $id )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Emergency alert acknowledged by responders.', 'society-hubx' ),
		) );
	}

	/**
	 * POST /emergency/alerts/(?P<id>[\w-]+)/resolve
	 */
	public function resolve_alert( WP_REST_Request $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$user = wp_get_current_user();
		$user_id = get_current_user_id();
		$db = new SHUBX51_DB_Router();

		$now = current_time( 'mysql' );
		$result = $db->update(
			'emergency_alerts',
			array(
				'status'           => 'resolved',
				'resolved_by'      => $user_id,
				'resolved_by_name' => $user ? $user->display_name : 'Admin',
				'resolved_at'      => $now,
				'updated_at'       => $now,
			),
			array( 'alert_id' => $id )
		);

		if ( ! $result ) {
			$db->update(
				'emergency_alerts',
				array(
					'status'           => 'resolved',
					'resolved_by'      => $user_id,
					'resolved_by_name' => $user ? $user->display_name : 'Admin',
					'resolved_at'      => $now,
					'updated_at'       => $now,
				),
				array( 'id' => $id )
			);
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Emergency incident marked as resolved.', 'society-hubx' ),
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
