<?php
/**
 * Class: FCM Service
 * Pure PHP Firebase Cloud Messaging (FCM HTTP v1) Dispatcher.
 * Zero external Composer dependencies: Uses native PHP openssl_sign() for RS256 JWT auth.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_FCM_Service {

	/**
	 * Initialize event listeners.
	 */
	public static function init() {
		add_action( 'shubx51_emergency_sos_triggered', array( __CLASS__, 'on_emergency_sos' ), 10, 1 );
		add_action( 'shubx51_visitor_entry_requested', array( __CLASS__, 'on_visitor_entry' ), 10, 1 );
		add_action( 'shubx51_helpdesk_ticket_updated', array( __CLASS__, 'on_helpdesk_event' ), 10, 3 );
		add_action( 'shubx51_notice_published', array( __CLASS__, 'on_notice_published' ), 10, 2 );
		add_action( 'shubx51_invoice_generated', array( __CLASS__, 'on_invoice_generated' ), 10, 2 );
	}

	/**
	 * Check if FCM is enabled and properly configured.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled     = get_option( 'shubx51_fcm_enabled', '0' );
		$project_id  = get_option( 'shubx51_fcm_project_id', '' );
		$private_key = get_option( 'shubx51_fcm_private_key', '' );
		return ( '1' === (string) $enabled && ! empty( $project_id ) && ! empty( $private_key ) );
	}

	/**
	 * Get public configuration for mobile client handshake.
	 *
	 * @return array
	 */
	public static function get_public_config() {
		return array(
			'fcm_enabled' => self::is_enabled(),
			'sender_id'   => get_option( 'shubx51_fcm_sender_id', '' ),
			'project_id'  => get_option( 'shubx51_fcm_project_id', '' ),
		);
	}

	/**
	 * Obtain short-lived Google OAuth2 Access Token using RS256 JWT Assertion.
	 * Caches token in transient for 55 minutes.
	 *
	 * @return string|WP_Error
	 */
	public static function get_access_token() {
		$cached_token = get_transient( 'shubx51_fcm_access_token' );
		if ( $cached_token ) {
			return $cached_token;
		}

		$client_email = get_option( 'shubx51_fcm_client_email', '' );
		$private_key  = get_option( 'shubx51_fcm_private_key', '' );

		if ( empty( $client_email ) || empty( $private_key ) ) {
			return new WP_Error( 'fcm_not_configured', __( 'Firebase credentials are not configured.', 'society-hubx' ) );
		}

		$now         = time();
		$jwt_header  = rtrim( strtr( base64_encode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ), '+/', '-_' ), '=' );
		$jwt_payload = rtrim( strtr( base64_encode( json_encode( array(
			'iss'   => $client_email,
			'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		) ) ), '+/', '-_' ), '=' );

		$signature = '';
		$signed    = openssl_sign( "{$jwt_header}.{$jwt_payload}", $signature, $private_key, 'SHA256' );

		if ( ! $signed ) {
			return new WP_Error( 'fcm_sign_failed', __( 'Failed to sign JWT with the provided RSA private key.', 'society-hubx' ) );
		}

		$jwt_signature = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
		$jwt_assertion = "{$jwt_header}.{$jwt_payload}.{$jwt_signature}";

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt_assertion,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$error_desc = isset( $body['error_description'] ) ? $body['error_description'] : ( $body['error'] ?? 'Unknown OAuth2 error' );
			return new WP_Error( 'fcm_oauth_failed', sprintf( __( 'Google OAuth2 failed: %s', 'society-hubx' ), $error_desc ) );
		}

		set_transient( 'shubx51_fcm_access_token', $body['access_token'], 55 * MINUTE_IN_SECONDS );
		return $body['access_token'];
	}

	/**
	 * Dispatch a single FCM notification message.
	 *
	 * @param string $token Device registration token.
	 * @param string $title Notification title.
	 * @param string $body Notification body.
	 * @param array  $data Custom payload data.
	 * @param string $priority 'high' or 'normal'.
	 * @return array|WP_Error
	 */
	public static function send_notification( $token, $title, $body, $data = array(), $priority = 'high' ) {
		$project_id = get_option( 'shubx51_fcm_project_id', '' );
		if ( empty( $project_id ) ) {
			return new WP_Error( 'fcm_no_project', __( 'Firebase Project ID missing.', 'society-hubx' ) );
		}

		$access_token = self::get_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

		$data_str_array = array();
		foreach ( $data as $k => $v ) {
			$data_str_array[ (string) $k ] = is_array( $v ) ? json_encode( $v ) : (string) $v;
		}

		$is_emergency = ( ( $data['type'] ?? '' ) === 'emergency_sos' );

		$message = array(
			'token'        => $token,
			'notification' => array(
				'title' => $title,
				'body'  => $body,
			),
			'data'         => $data_str_array,
			'android'      => array(
				'priority'     => ( 'high' === $priority ) ? 'HIGH' : 'NORMAL',
				'notification' => array(
					'sound'        => $is_emergency ? 'alarm_sound' : 'default',
					'channel_id'   => $is_emergency ? 'emergency_channel' : 'general_alerts',
					'click_action' => 'OPEN_SOCIETY_HUBX',
				),
			),
			'apns'         => array(
				'payload' => array(
					'aps' => array(
						'sound' => $is_emergency ? 'alarm.wav' : 'default',
						'badge' => 1,
					),
				),
			),
		);

		$response = wp_remote_post( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json; UTF-8',
			),
			'body'    => json_encode( array( 'message' => $message ) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$resp_code = wp_remote_retrieve_response_code( $response );
		$resp_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $resp_code >= 200 && $resp_code < 300 ) {
			return array(
				'success'    => true,
				'message_id' => $resp_body['name'] ?? '',
			);
		}

		// Handle expired or invalid token (deactivate automatically)
		if ( 404 === $resp_code || ( isset( $resp_body['error']['details'] ) && false !== strpos( json_encode( $resp_body['error'] ), 'UNREGISTERED' ) ) ) {
			self::deactivate_token( $token );
		}

		$error_msg = $resp_body['error']['message'] ?? ( 'HTTP ' . $resp_code );
		return new WP_Error( 'fcm_dispatch_failed', $error_msg, array( 'status' => $resp_code ) );
	}

	/**
	 * Send push notification to all active devices registered to a specific flat.
	 *
	 * @param string $flat_no Flat identifier.
	 * @param string $title Title.
	 * @param string $body Body.
	 * @param array  $data Custom payload data.
	 * @param string $priority Priority.
	 * @return int Number of messages successfully dispatched.
	 */
	public static function send_to_flat( $flat_no, $title, $body, $data = array(), $priority = 'high' ) {
		if ( ! self::is_enabled() || empty( $flat_no ) ) {
			return 0;
		}

		global $wpdb;
		$table = "{$wpdb->prefix}shubx51_device_tokens";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tokens = $wpdb->get_col( $wpdb->prepare(
			"SELECT device_token FROM {$table} WHERE flat_no = %s AND is_active = 1",
			$flat_no
		) );

		if ( empty( $tokens ) ) {
			return 0;
		}

		$sent = 0;
		foreach ( $tokens as $token ) {
			$res = self::send_notification( $token, $title, $body, $data, $priority );
			if ( ! is_wp_error( $res ) && ! empty( $res['success'] ) ) {
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Send broadcast push notification to all active resident devices.
	 *
	 * @param string $title Title.
	 * @param string $body Body.
	 * @param array  $data Custom payload data.
	 * @param string $priority Priority.
	 * @return int Number of sent notifications.
	 */
	public static function send_to_all( $title, $body, $data = array(), $priority = 'normal' ) {
		if ( ! self::is_enabled() ) {
			return 0;
		}

		global $wpdb;
		$table = "{$wpdb->prefix}shubx51_device_tokens";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tokens = $wpdb->get_col( "SELECT DISTINCT device_token FROM {$table} WHERE is_active = 1" );

		if ( empty( $tokens ) ) {
			return 0;
		}

		$sent = 0;
		foreach ( $tokens as $token ) {
			$res = self::send_notification( $token, $title, $body, $data, $priority );
			if ( ! is_wp_error( $res ) && ! empty( $res['success'] ) ) {
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Send push notification to users holding specific roles (e.g. security guards, committee).
	 *
	 * @param array  $target_roles Array of role slugs.
	 * @param string $title Title.
	 * @param string $body Body.
	 * @param array  $data Custom payload data.
	 * @param string $priority Priority.
	 * @return int Number of dispatched messages.
	 */
	public static function send_to_roles( $target_roles, $title, $body, $data = array(), $priority = 'high' ) {
		if ( ! self::is_enabled() || empty( $target_roles ) ) {
			return 0;
		}

		global $wpdb;
		$db = new SHUBX51_DB_Router();
		$residents = $db->get( 'residents', array( 'status' => 'approved' ) );

		$user_ids = array();
		foreach ( $residents as $res ) {
			$roles_str = $res['roles'] ?? '';
			$res_roles = array_map( 'trim', explode( ',', $roles_str ) );
			$res_roles[] = $res['type'] ?? '';

			$matched = array_intersect( $target_roles, $res_roles );
			if ( ! empty( $matched ) && ! empty( $res['wp_user_id'] ) ) {
				$user_ids[] = (int) $res['wp_user_id'];
			}
		}

		if ( empty( $user_ids ) ) {
			return 0;
		}

		$table = "{$wpdb->prefix}shubx51_device_tokens";
		$in_sql = implode( ',', array_map( 'intval', array_unique( $user_ids ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tokens = $wpdb->get_col( "SELECT device_token FROM {$table} WHERE user_id IN ({$in_sql}) AND is_active = 1" );

		if ( empty( $tokens ) ) {
			return 0;
		}

		$sent = 0;
		foreach ( $tokens as $token ) {
			$res = self::send_notification( $token, $title, $body, $data, $priority );
			if ( ! is_wp_error( $res ) && ! empty( $res['success'] ) ) {
				$sent++;
			}
		}

		return $sent;
	}

	/**
	 * Deactivate an expired or unregistered device token.
	 *
	 * @param string $token Device token.
	 */
	public static function deactivate_token( $token ) {
		global $wpdb;
		$table = "{$wpdb->prefix}shubx51_device_tokens";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ),
			array( 'device_token' => $token )
		);
	}

	/**
	 * Upsert a device token registration for a resident / flat.
	 *
	 * @param array $data Registration parameters.
	 * @return bool
	 */
	public static function register_token( $data ) {
		global $wpdb;
		$table = "{$wpdb->prefix}shubx51_device_tokens";

		$device_token = sanitize_text_field( $data['device_token'] ?? '' );
		if ( empty( $device_token ) ) {
			return false;
		}

		$user_id     = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$flat_no     = sanitize_text_field( $data['flat_no'] ?? '' );
		$platform    = sanitize_key( $data['platform'] ?? 'android' );
		$device_name = sanitize_text_field( $data['device_name'] ?? '' );
		$app_version = sanitize_text_field( $data['app_version'] ?? '1.0.0' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE device_token = %s",
			$device_token
		), ARRAY_A );

		$now = current_time( 'mysql' );

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array(
					'user_id'     => $user_id,
					'flat_no'     => $flat_no,
					'platform'    => $platform,
					'device_name' => $device_name,
					'app_version' => $app_version,
					'is_active'   => 1,
					'updated_at'  => $now,
				),
				array( 'id' => $existing['id'] )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				array(
					'user_id'      => $user_id,
					'flat_no'      => $flat_no,
					'device_token' => $device_token,
					'platform'     => $platform,
					'device_name'  => $device_name,
					'app_version'  => $app_version,
					'is_active'    => 1,
					'created_at'   => $now,
					'updated_at'   => $now,
				)
			);
		}

		return true;
	}

	// =========================================================================
	// Event Hook Listeners
	// =========================================================================

	/**
	 * Hook: Emergency SOS Triggered.
	 *
	 * @param array $payload SOS event payload.
	 */
	public static function on_emergency_sos( $payload ) {
		$type    = $payload['type'] ?? 'EMERGENCY';
		$flat_no = $payload['flat_no'] ?? '';
		$by      = $payload['triggered_by_name'] ?? 'A resident';

		$title = "🚨 EMERGENCY SOS: Flat {$flat_no}";
		$body  = "{$by} has triggered a {$type} emergency. Immediate assistance requested!";

		$data = array(
			'type'         => 'emergency_sos',
			'sos_type'     => $type,
			'flat_no'      => $flat_no,
			'triggered_by' => $by,
		);

		// Send high-priority siren alert to guards and committee members
		self::send_to_roles( array( 'Security Guard', 'Security', 'Admin', 'President', 'Secretary' ), $title, $body, $data, 'high' );
	}

	/**
	 * Hook: Visitor Entry Requested at Security Gate.
	 *
	 * @param array $visitor_log Visitor check-in record.
	 */
	public static function on_visitor_entry( $visitor_log ) {
		$flat_no      = $visitor_log['flat_no'] ?? '';
		$visitor_name = $visitor_log['name'] ?? 'Visitor';
		$purpose      = $visitor_log['purpose'] ?? 'General';
		$gate_id      = $visitor_log['gate_id'] ?? 'Main Gate';

		if ( empty( $flat_no ) ) {
			return;
		}

		$title = "🚪 Visitor at {$gate_id}: {$visitor_name}";
		$body  = "{$visitor_name} ({$purpose}) has arrived for your flat {$flat_no}. Tap to Approve or Deny.";

		$data = array(
			'type'         => 'visitor_entry',
			'log_id'       => $visitor_log['id'] ?? '',
			'visitor_name' => $visitor_name,
			'flat_no'      => $flat_no,
		);

		self::send_to_flat( $flat_no, $title, $body, $data, 'high' );
	}

	/**
	 * Hook: Helpdesk Ticket Updated.
	 *
	 * @param string $ticket_id Ticket ID.
	 * @param string $action Action (e.g. 'reply', 'assign', 'resolve').
	 * @param array  $extra Additional context.
	 */
	public static function on_helpdesk_event( $ticket_id, $action, $extra = array() ) {
		$flat_no = $extra['flat_no'] ?? '';
		$subject = $extra['subject'] ?? "Ticket #{$ticket_id}";

		if ( empty( $flat_no ) ) {
			return;
		}

		$title = "🛠️ Helpdesk Update: {$subject}";
		$body  = "Your support ticket has been updated: " . ( $extra['message'] ?? 'New activity recorded.' );

		$data = array(
			'type'      => 'helpdesk_ticket',
			'ticket_id' => $ticket_id,
			'action'    => $action,
		);

		self::send_to_flat( $flat_no, $title, $body, $data, 'normal' );
	}

	/**
	 * Hook: Notice Published.
	 *
	 * @param string $notice_id Notice ID.
	 * @param array  $notice Notice data.
	 */
	public static function on_notice_published( $notice_id, $notice ) {
		$title_text = $notice['title'] ?? 'Notice Board Announcement';
		$summary    = wp_trim_words( wp_strip_all_tags( $notice['content'] ?? '' ), 20 );

		$title = "📢 Notice: {$title_text}";
		$body  = $summary;

		$data = array(
			'type'      => 'notice_board',
			'notice_id' => $notice_id,
		);

		self::send_to_all( $title, $body, $data, 'normal' );
	}

	/**
	 * Hook: Maintenance Invoice Generated.
	 *
	 * @param string $invoice_id Invoice ID.
	 * @param array  $invoice Invoice record.
	 */
	public static function on_invoice_generated( $invoice_id, $invoice ) {
		$flat_no = $invoice['flat_no'] ?? '';
		$amount  = $invoice['amount'] ?? 0;

		if ( empty( $flat_no ) ) {
			return;
		}

		$title = '💳 New Maintenance Invoice';
		$body  = "Maintenance bill of ₹{$amount} has been generated for Flat {$flat_no}.";

		$data = array(
			'type'       => 'maintenance_bill',
			'invoice_id' => $invoice_id,
			'amount'     => (string) $amount,
		);

		self::send_to_flat( $flat_no, $title, $body, $data, 'normal' );
	}
}
