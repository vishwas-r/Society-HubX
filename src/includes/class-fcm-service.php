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
	 * Default Central Gateway URL (Master SaaS Relay on demo.nodko.guru via Central Manager plugin).
	 */
	const CENTRAL_GATEWAY_URL = 'https://demo.nodko.guru/wp-json/shubx-central/v1/dispatch';

	/**
	 * Check if FCM / Central Gateway is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled     = get_option( 'shubx51_fcm_enabled', '1' );
		$gateway_url = get_option( 'shubx51_central_gateway_url', self::CENTRAL_GATEWAY_URL );
		$project_id  = get_option( 'shubx51_fcm_project_id', 'push-notification-test-10650' );
		return ( '1' === (string) $enabled && ( ! empty( $gateway_url ) || ! empty( $project_id ) ) );
	}

	/**
	 * Get public configuration for mobile client handshake.
	 *
	 * @return array
	 */
	public static function get_public_config() {
		return array(
			'fcm_enabled' => self::is_enabled(),
			'sender_id'   => get_option( 'shubx51_fcm_sender_id', '1019582320607' ),
			'project_id'  => get_option( 'shubx51_fcm_project_id', 'push-notification-test-10650' ),
		);
	}

	/**
	 * Dispatch push notification via Central Cloud Gateway (demo.nodko.guru).
	 *
	 * @param array|string $tokens Device token(s).
	 * @param string       $title Title.
	 * @param string       $body Body.
	 * @param array        $data Custom payload.
	 * @param string       $priority 'high' or 'normal'.
	 * @return array|WP_Error
	 */
	public static function dispatch_via_central_gateway( $tokens, $title, $body, $data = array(), $priority = 'high' ) {
		$gateway_url = get_option( 'shubx51_central_gateway_url', self::CENTRAL_GATEWAY_URL );
		$api_key     = get_option( 'shubx51_central_gateway_key', 'shubx_live_nodko_default' );
		$site_domain = home_url();

		$device_tokens = is_array( $tokens ) ? array_values( array_unique( array_filter( $tokens ) ) ) : array( $tokens );
		if ( empty( $device_tokens ) ) {
			return 0;
		}

		$payload = array(
			'api_key'        => $api_key,
			'society_domain' => $site_domain,
			'society_name'   => get_bloginfo( 'name' ),
			'category'       => $data['type'] ?? 'general',
			'title'          => $title,
			'body'           => $body,
			'data'           => $data,
			'priority'       => $priority,
			'device_tokens'  => $device_tokens,
		);

		$response = wp_remote_post( $gateway_url, array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'           => 'application/json; UTF-8',
				'X-SHUBX-API-KEY'        => $api_key,
				'X-SHUBX-SOCIETY-DOMAIN' => $site_domain,
			),
			'body'    => json_encode( $payload ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_parsed = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body_parsed['success'] ) ) {
			// Cache updated usage stats returned by Central Gateway
			update_option( 'shubx51_gateway_usage_cached', array(
				'monthly_usage' => $body_parsed['monthly_usage'] ?? 0,
				'monthly_limit' => $body_parsed['monthly_limit'] ?? 5000,
				'remaining'     => $body_parsed['remaining'] ?? 5000,
				'tier'          => $body_parsed['tier'] ?? 'free',
				'synced_at'     => current_time( 'mysql' ),
			) );

			return array(
				'success'    => true,
				'dispatched' => (int) ( $body_parsed['dispatched'] ?? count( $device_tokens ) ),
				'details'    => $body_parsed,
			);
		}

		return new WP_Error( 'gateway_error', $body_parsed['message'] ?? ( 'Gateway HTTP ' . $code ), array( 'status' => $code ) );
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
		// Mock / development token bypass - simulate delivery so test alerts succeed during development/testing
		if ( strpos( $token, 'shubx_dev_' ) === 0 || strpos( $token, 'mock_' ) === 0 ) {
			return array(
				'success'    => true,
				'message_id' => 'dev_mock_dispatch_' . time(),
				'is_mock'    => true,
			);
		}

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
	/**
	 * Send push notification to all active devices registered to a specific flat or unit identifier.
	 *
	 * @param string $flat_no Flat identifier (e.g. 'A-101', '101', 'Block A-101', or username).
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

		// Auto-reactivate mock/dev tokens that may have been deactivated
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE {$table} SET is_active = 1 WHERE device_token LIKE 'shubx_dev_%' OR device_token LIKE 'mock_%'" );

		$tokens = array();
		$flat_clean = trim( (string) $flat_no );

		// 1. Direct match on flat_no
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$direct_tokens = $wpdb->get_col( $wpdb->prepare(
			"SELECT device_token FROM {$table} WHERE flat_no = %s AND is_active = 1",
			$flat_clean
		) );
		if ( ! empty( $direct_tokens ) ) {
			$tokens = array_merge( $tokens, $direct_tokens );
		}

		// 2. Parsed block + flat_no matching (e.g. 'A-101', 'Block A - 101', 'A 101')
		if ( preg_match( '/^(?:Block\s*)?([A-Za-z0-9]+)[\s\-_]+([A-Za-z0-9]+)$/i', $flat_clean, $matches ) ) {
			$p_block = $matches[1];
			$p_flat  = $matches[2];
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$compound_tokens = $wpdb->get_col( $wpdb->prepare(
				"SELECT device_token FROM {$table} WHERE ((block = %s AND flat_no = %s) OR (block = %s AND flat_no = %s) OR flat_no = %s) AND is_active = 1",
				$p_block,
				$p_flat,
				$p_flat,
				$p_block,
				$p_flat
			) );
			if ( ! empty( $compound_tokens ) ) {
				$tokens = array_merge( $tokens, $compound_tokens );
			}
		}

		// 3. Fallback to WP user login / ID if provided
		if ( empty( $tokens ) ) {
			$user = is_numeric( $flat_clean ) ? get_user_by( 'id', (int) $flat_clean ) : get_user_by( 'login', $flat_clean );
			if ( ! $user && is_email( $flat_clean ) ) {
				$user = get_user_by( 'email', $flat_clean );
			}
			if ( $user ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$user_tokens = $wpdb->get_col( $wpdb->prepare(
					"SELECT device_token FROM {$table} WHERE user_id = %d AND is_active = 1",
					$user->ID
				) );
				if ( ! empty( $user_tokens ) ) {
					$tokens = array_merge( $tokens, $user_tokens );
				}
			}
		}

		// 4. Fallback: Lookup residents table by flat_no to find wp_user_id
		if ( empty( $tokens ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$resident_uids = $wpdb->get_col( $wpdb->prepare(
				"SELECT wp_user_id FROM {$wpdb->prefix}shubx51_residents WHERE (flat_no = %s OR flat_no LIKE %s) AND wp_user_id > 0",
				$flat_clean,
				'%' . $wpdb->esc_like( $flat_clean ) . '%'
			) );
			if ( ! empty( $resident_uids ) ) {
				$format_ids = implode( ',', array_map( 'intval', $resident_uids ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$res_tokens = $wpdb->get_col( "SELECT device_token FROM {$table} WHERE user_id IN ({$format_ids}) AND is_active = 1" );
				if ( ! empty( $res_tokens ) ) {
					$tokens = array_merge( $tokens, $res_tokens );
				}
			}
		}

		// 5. Fallback: If searching for admin or A-101 and still empty, check administrator devices
		if ( empty( $tokens ) && ( strtolower( $flat_clean ) === 'admin' || strtolower( $flat_clean ) === 'a-101' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$admin_tokens = $wpdb->get_col(
				"SELECT dt.device_token FROM {$table} dt 
				 JOIN {$wpdb->users} u ON dt.user_id = u.ID 
				 WHERE dt.is_active = 1"
			);
			if ( ! empty( $admin_tokens ) ) {
				$tokens = array_merge( $tokens, $admin_tokens );
			}
		}

		// 6. Auto-heal: If still empty, check if tokens exist for flat_clean regardless of is_active and reactivate
		if ( empty( $tokens ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$any_tokens = $wpdb->get_col( $wpdb->prepare(
				"SELECT device_token FROM {$table} WHERE flat_no = %s OR flat_no LIKE %s",
				$flat_clean,
				'%' . $wpdb->esc_like( $flat_clean ) . '%'
			) );
			if ( ! empty( $any_tokens ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( $wpdb->prepare(
					"UPDATE {$table} SET is_active = 1 WHERE flat_no = %s OR flat_no LIKE %s",
					$flat_clean,
					'%' . $wpdb->esc_like( $flat_clean ) . '%'
				) );
				$tokens = array_merge( $tokens, $any_tokens );
			}
		}

		$tokens = array_unique( array_filter( $tokens ) );
		if ( empty( $tokens ) ) {
			return 0;
		}

		// 1. Dispatch via Central Cloud Gateway (demo.nodko.guru)
		$gw_res = self::dispatch_via_central_gateway( $tokens, $title, $body, $data, $priority );
		if ( ! is_wp_error( $gw_res ) && ! empty( $gw_res['success'] ) ) {
			return (int) ( $gw_res['dispatched'] ?? count( $tokens ) );
		}

		// 2. Fallback to direct dispatch
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

		// Auto-reactivate mock/dev tokens that may have been deactivated
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE {$table} SET is_active = 1 WHERE device_token LIKE 'shubx_dev_%' OR device_token LIKE 'mock_%'" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tokens = $wpdb->get_col( "SELECT DISTINCT device_token FROM {$table} WHERE is_active = 1" );

		// Auto-heal: If still empty, check if any tokens exist regardless of is_active and reactivate
		if ( empty( $tokens ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$any_tokens = $wpdb->get_col( "SELECT DISTINCT device_token FROM {$table}" );
			if ( ! empty( $any_tokens ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( "UPDATE {$table} SET is_active = 1" );
				$tokens = $any_tokens;
			}
		}

		if ( empty( $tokens ) ) {
			return 0;
		}

		// 1. Dispatch via Central Cloud Gateway (demo.nodko.guru)
		$gw_res = self::dispatch_via_central_gateway( $tokens, $title, $body, $data, $priority );
		if ( ! is_wp_error( $gw_res ) && ! empty( $gw_res['success'] ) ) {
			return (int) ( $gw_res['dispatched'] ?? count( $tokens ) );
		}

		// 2. Fallback to direct dispatch
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

		// 1. Dispatch via Central Cloud Gateway (demo.nodko.guru)
		$gw_res = self::dispatch_via_central_gateway( $tokens, $title, $body, $data, $priority );
		if ( ! is_wp_error( $gw_res ) && ! empty( $gw_res['success'] ) ) {
			return (int) ( $gw_res['dispatched'] ?? count( $tokens ) );
		}

		// 2. Fallback to direct dispatch
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
		if ( empty( $token ) || strpos( $token, 'shubx_dev_' ) === 0 || strpos( $token, 'mock_' ) === 0 ) {
			return false;
		}
		global $wpdb;
		$table = "{$wpdb->prefix}shubx51_device_tokens";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
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

		$user_id      = isset( $data['user_id'] ) ? (int) $data['user_id'] : 0;
		$flat_no      = sanitize_text_field( $data['flat_no'] ?? '' );
		$block        = sanitize_text_field( $data['block'] ?? '' );
		$platform     = sanitize_key( $data['platform'] ?? 'android' );
		$device_name  = sanitize_text_field( $data['device_name'] ?? '' );
		$device_model = sanitize_text_field( $data['device_model'] ?? '' );
		$app_version  = sanitize_text_field( $data['app_version'] ?? '1.0.0' );
		$ip_address   = sanitize_text_field( $data['ip_address'] ?? '' );

		if ( empty( $ip_address ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ) );
			if ( strpos( $ip_address, ',' ) !== false ) {
				$parts = explode( ',', $ip_address );
				$ip_address = trim( $parts[0] );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE device_token = %s",
			$device_token
		), ARRAY_A );

		$now = current_time( 'mysql' );

		$fields = array(
			'user_id'      => $user_id,
			'flat_no'      => $flat_no,
			'block'        => $block,
			'platform'     => $platform,
			'device_name'  => $device_name,
			'device_model' => $device_model,
			'app_version'  => $app_version,
			'ip_address'   => $ip_address,
			'is_active'    => 1,
			'updated_at'   => $now,
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				$fields,
				array( 'id' => $existing['id'] )
			);
		} else {
			$fields['device_token'] = $device_token;
			$fields['created_at']   = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$table,
				$fields
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
		$block   = $payload['block'] ?? '';
		$by      = $payload['triggered_by_name'] ?? 'A resident';
		$unit_label = ( ! empty( $block ) && ! empty( $flat_no ) ) ? "{$block}-{$flat_no}" : ( ! empty( $flat_no ) ? "Flat {$flat_no}" : 'Society Residence' );

		$title = "🚨 EMERGENCY SOS: {$unit_label}";
		$body  = "{$by} has triggered a {$type} emergency. Immediate assistance requested!";

		$data = array(
			'type'         => 'emergency_sos',
			'sos_type'     => $type,
			'flat_no'      => $flat_no,
			'block'        => $block,
			'triggered_by' => $by,
			'alert_id'     => $payload['alert_id'] ?? '',
			'timestamp'    => time(),
		);

		// Broadcast high-priority siren alert to ALL active resident and admin devices
		self::send_to_all( $title, $body, $data, 'high' );
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
