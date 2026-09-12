<?php
/**
 * Class: REST Notifications Controller
 * Endpoints for In-App resident & admin notifications.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Notifications_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'notifications';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inapp',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_inapp_notifications' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inapp/(?P<id>[\w-]+)/read',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_as_read' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inapp/read-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'mark_all_read' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		// Linked Devices for Current User
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/linked-devices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_linked_devices' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		// Push Notification Config Handshake (Public)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/config',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notification_config' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Device Registration Endpoint
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/register-token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_device_token' ),
					'permission_callback' => array( $this, 'token_registration_check' ),
				),
			)
		);

		// Test Push Notification Endpoint (Admin only)
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/test-push',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_test_push' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get in-app notifications for current user.
	 */
	public function get_inapp_notifications( $request ) {
		$user_id = get_current_user_id();
		$db = new NAMMASOCIETY51_DB_Router();
		$notifs = $db->get( 'inapp_notifications', array( 'where' => array( 'user_id' => $user_id ) ) );

		if ( empty( $notifs ) ) {
			return rest_ensure_response( array() );
		}

		// Normalize fields so both 'content' and 'message' are populated for React Native UI
		foreach ( $notifs as &$n ) {
			if ( ! isset( $n['message'] ) && isset( $n['content'] ) ) {
				$n['message'] = $n['content'];
			}
			if ( ! isset( $n['content'] ) && isset( $n['message'] ) ) {
				$n['content'] = $n['message'];
			}
			$n['is_read'] = (int) ( $n['is_read'] ?? 0 );
		}
		unset( $n );

		usort(
			$notifs,
			function( $a, $b ) {
				return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
			}
		);

		return rest_ensure_response( $notifs );
	}

	/**
	 * Mark single notification as read.
	 */
	public function mark_as_read( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$user_id = get_current_user_id();
		$db = new NAMMASOCIETY51_DB_Router();

		$result = $db->update(
			'inapp_notifications',
			array(
				'is_read' => 1,
			),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Notification marked as read.', 'namma-society' ) ) );
	}

	/**
	 * Mark all notifications as read.
	 */
	public function mark_all_read( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = "{$wpdb->prefix}nammasociety51_inapp_notifications";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_read = 1 WHERE user_id = %d AND is_read = 0",
				$user_id
			)
		);

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'All notifications marked as read.', 'namma-society' ) ) );
	}

	/**
	 * Get list of linked devices for current user (or society if admin).
	 */
	public function get_linked_devices( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = "{$wpdb->prefix}nammasociety51_device_tokens";

		if ( current_user_can( 'manage_options' ) ) {
			// Admins can see all registered devices in the society
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$devices = $wpdb->get_results(
				"SELECT dt.*, u.display_name, u.user_login 
				 FROM {$table} dt 
				 LEFT JOIN {$wpdb->users} u ON dt.user_id = u.ID 
				 ORDER BY dt.updated_at DESC LIMIT 100",
				ARRAY_A
			);
		} else {
			// Regular residents only see their own linked devices
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$devices = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT dt.*, u.display_name, u.user_login 
					 FROM {$table} dt 
					 LEFT JOIN {$wpdb->users} u ON dt.user_id = u.ID 
					 WHERE dt.user_id = %d ORDER BY dt.updated_at DESC",
					$user_id
				),
				ARRAY_A
			);
		}

		// Mask full token for security, leave preview
		$clean_devices = array_map( function( $d ) {
			$tok = $d['device_token'] ?? '';
			return array(
				'id'                 => $d['id'] ?? '',
				'user_id'            => $d['user_id'] ?? 0,
				'display_name'       => $d['display_name'] ?? '',
				'user_login'         => $d['user_login'] ?? '',
				'society_id'         => $d['society_id'] ?? '1',
				'society_name'       => $d['society_name'] ?? '',
				'device_name'        => $d['device_name'] ?? 'Mobile Device',
				'device_model'       => $d['device_model'] ?? '',
				'platform'           => $d['platform'] ?? 'android',
				'app_version'        => $d['app_version'] ?? '1.0.0',
				'ip_address'         => $d['ip_address'] ?? '',
				'block'              => $d['block'] ?? '',
				'flat_no'            => $d['flat_no'] ?? '',
				'is_active'          => (int) ( $d['is_active'] ?? 1 ),
				'total_pushes_sent'  => (int) ( $d['total_pushes_sent'] ?? 0 ),
				'last_dispatched_at' => $d['last_dispatched_at'] ?? '',
				'last_push_status'   => $d['last_push_status'] ?? 'active',
				'last_push_title'    => $d['last_push_title'] ?? '',
				'created_at'         => $d['created_at'] ?? '',
				'updated_at'         => $d['updated_at'] ?? '',
				'token_preview'      => ! empty( $tok ) ? ( substr( $tok, 0, 10 ) . '...' . substr( $tok, -6 ) ) : '',
			);
		}, $devices ?: array() );

		return rest_ensure_response( $clean_devices );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	/**
	 * GET /notifications/config
	 * Public endpoint returning society FCM sender ID & project ID.
	 */
	public function get_notification_config() {
		if ( ! class_exists( 'NAMMASOCIETY51_FCM_Service' ) ) {
			return rest_ensure_response( array(
				'success'     => true,
				'fcm_enabled' => false,
				'sender_id'   => '',
				'project_id'  => '',
			) );
		}

		$config = NAMMASOCIETY51_FCM_Service::get_public_config();
		return rest_ensure_response( array(
			'success'     => true,
			'fcm_enabled' => (bool) $config['fcm_enabled'],
			'sender_id'   => (string) $config['sender_id'],
			'project_id'  => (string) $config['project_id'],
		) );
	}

	/**
	 * POST /notifications/register-token
	 * Registers or updates a device token.
	 */
	public function register_device_token( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$token = sanitize_text_field( $params['device_token'] ?? '' );
		if ( empty( $token ) ) {
			return new WP_Error( 'rest_invalid_token', __( 'Device token is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$user_id      = get_current_user_id();
		$flat_no      = sanitize_text_field( $params['flat_no'] ?? '' );
		$block        = sanitize_text_field( $params['block'] ?? '' );
		$society_id   = sanitize_text_field( $params['society_id'] ?? '' );
		$society_name = sanitize_text_field( $params['society_name'] ?? '' );

		// If user is logged in, attempt to resolve unit info from resident profile if not provided
		if ( ( empty( $flat_no ) || empty( $block ) ) && $user_id > 0 ) {
			$db = new NAMMASOCIETY51_DB_Router();
			$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );
			if ( $resident ) {
				if ( empty( $flat_no ) && ! empty( $resident['flat_no'] ) ) {
					$flat_no = $resident['flat_no'];
				}
				if ( empty( $block ) && ! empty( $resident['block'] ) ) {
					$block = $resident['block'];
				}
			}
		}

		$client_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( strpos( $client_ip, ',' ) !== false ) {
			$parts = explode( ',', $client_ip );
			$client_ip = trim( $parts[0] );
		}

		if ( ! class_exists( 'NAMMASOCIETY51_FCM_Service' ) ) {
			return new WP_Error( 'fcm_service_unavailable', __( 'FCM Service not available.', 'namma-society' ), array( 'status' => 500 ) );
		}

		$registered = NAMMASOCIETY51_FCM_Service::register_token( array(
			'user_id'      => $user_id,
			'society_id'   => $society_id,
			'society_name' => $society_name,
			'flat_no'      => $flat_no,
			'block'        => $block,
			'device_token' => $token,
			'platform'     => sanitize_key( $params['platform'] ?? 'android' ),
			'device_name'  => sanitize_text_field( $params['device_name'] ?? '' ),
			'device_model' => sanitize_text_field( $params['device_model'] ?? '' ),
			'app_version'  => sanitize_text_field( $params['app_version'] ?? '1.0.0' ),
			'ip_address'   => $client_ip,
		) );

		if ( ! $registered ) {
			return new WP_Error( 'token_registration_failed', __( 'Could not register device token.', 'namma-society' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Device token registered successfully.', 'namma-society' ),
			'flat_no' => $flat_no,
			'block'   => $block,
		) );
	}

	/**
	 * POST /notifications/test-push
	 * Dispatches a test notification to verify credentials.
	 */
	public function send_test_push( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_FCM_Service' ) ) {
			return new WP_Error( 'fcm_not_loaded', __( 'FCM service not loaded.', 'namma-society' ), array( 'status' => 500 ) );
		}

		if ( ! NAMMASOCIETY51_FCM_Service::is_enabled() ) {
			return new WP_Error( 'fcm_disabled', __( 'FCM is not enabled or credentials are missing.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$params = $request->get_json_params();
		$target_flat = sanitize_text_field( $params['flat_no'] ?? '' );
		$target_token = sanitize_text_field( $params['device_token'] ?? '' );

		$title = __( '🧪 Namma Society Test Alert', 'namma-society' );
		$body  = sprintf( __( 'Test notification from %s. Push notifications are working!', 'namma-society' ), get_bloginfo( 'name' ) );
		$data  = array(
			'type'      => 'test_ping',
			'timestamp' => time(),
		);

		// 1. Always record in-app notification first
		$user_id = get_current_user_id();
		$target_uids = array();
		if ( $user_id > 0 ) {
			$target_uids[] = $user_id;
		}

		$db = new NAMMASOCIETY51_DB_Router();
		if ( ! empty( $target_flat ) ) {
			$residents = $db->get( 'residents', array( 'where' => array( 'flat_no' => $target_flat ) ) );
			if ( ! empty( $residents ) ) {
				foreach ( $residents as $r ) {
					if ( ! empty( $r['wp_user_id'] ) ) {
						$target_uids[] = (int) $r['wp_user_id'];
					}
				}
			}
		}

		$target_uids = array_unique( array_filter( $target_uids ) );
		foreach ( $target_uids as $uid ) {
			$db->insert( 'inapp_notifications', array(
				'id'         => wp_generate_uuid4(),
				'user_id'    => $uid,
				'title'      => $title,
				'content'    => $body,
				'type'       => 'test_ping',
				'is_read'    => 0,
				'action_url' => '',
				'created_at' => current_time( 'mysql' ),
			) );
		}

		// 2. Dispatch push notification
		if ( ! empty( $target_token ) ) {
			$res = NAMMASOCIETY51_FCM_Service::send_notification( $target_token, $title, $body, $data, 'high' );
			$sent = ( ! is_wp_error( $res ) && ! empty( $res['success'] ) ) ? 1 : 0;
		} elseif ( ! empty( $target_flat ) ) {
			$sent = NAMMASOCIETY51_FCM_Service::send_to_flat( $target_flat, $title, $body, $data, 'high' );
		} else {
			$sent = NAMMASOCIETY51_FCM_Service::send_to_all( $title, $body, $data, 'high' );
		}

		if ( $sent > 0 ) {
			return rest_ensure_response( array(
				'success'    => true,
				'message'    => sprintf( __( 'Test push dispatched successfully to %d device(s)! Also logged to In-App Notifications.', 'namma-society' ), $sent ),
				'dispatched' => $sent,
			) );
		}

		return rest_ensure_response( array(
			'success'    => true,
			'message'    => sprintf( __( 'Test alert recorded to In-App Notifications! (Note: 0 active push devices registered for "%s" - open the mobile app to register device token).', 'namma-society' ), ! empty( $target_flat ) ? $target_flat : __( 'all', 'namma-society' ) ),
			'dispatched' => 0,
			'warning'    => true,
		) );
	}

	public function token_registration_check( $request ) {
		// Token registration is allowed for authenticated users, or clients presenting flat info
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		return true;
	}

	public function admin_permissions_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		return current_user_can( 'manage_options' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Notifications_Controller' ) && ! class_exists( 'SHUBX51_REST_Notifications_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Notifications_Controller', 'SHUBX51_REST_Notifications_Controller' );
}
