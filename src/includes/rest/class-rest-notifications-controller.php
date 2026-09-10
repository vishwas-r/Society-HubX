<?php
/**
 * Class: REST Notifications Controller
 * Endpoints for In-App resident & admin notifications.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Notifications_Controller extends WP_REST_Controller {

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
		$db = new SHUBX51_DB_Router();
		$notifs = $db->get( 'notifications', array( 'where' => array( 'recipient_id' => $user_id ) ) );

		if ( empty( $notifs ) ) {
			return rest_ensure_response( array() );
		}

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
		$db = new SHUBX51_DB_Router();

		$result = $db->update(
			'notifications',
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			),
			array(
				'id'           => $id,
				'recipient_id' => $user_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Notification marked as read.', 'society-hubx' ) ) );
	}

	/**
	 * Mark all notifications as read.
	 */
	public function mark_all_read( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = "{$wpdb->prefix}shubx51_notifications";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_read = 1, read_at = %s WHERE recipient_id = %d AND is_read = 0",
				current_time( 'mysql' ),
				$user_id
			)
		);

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'All notifications marked as read.', 'society-hubx' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}

	/**
	 * GET /notifications/config
	 * Public endpoint returning society FCM sender ID & project ID.
	 */
	public function get_notification_config() {
		if ( ! class_exists( 'SHUBX51_FCM_Service' ) ) {
			return rest_ensure_response( array(
				'success'     => true,
				'fcm_enabled' => false,
				'sender_id'   => '',
				'project_id'  => '',
			) );
		}

		$config = SHUBX51_FCM_Service::get_public_config();
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
			return new WP_Error( 'rest_invalid_token', __( 'Device token is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$flat_no = sanitize_text_field( $params['flat_no'] ?? '' );

		// If user is logged in, attempt to resolve flat_no from resident profile if not provided
		if ( empty( $flat_no ) && $user_id > 0 ) {
			$db = new SHUBX51_DB_Router();
			$resident = $db->get_row_by_field( 'residents', 'wp_user_id', $user_id );
			if ( $resident && ! empty( $resident['flat_no'] ) ) {
				$flat_no = $resident['flat_no'];
			}
		}

		if ( ! class_exists( 'SHUBX51_FCM_Service' ) ) {
			return new WP_Error( 'fcm_service_unavailable', __( 'FCM Service not available.', 'society-hubx' ), array( 'status' => 500 ) );
		}

		$registered = SHUBX51_FCM_Service::register_token( array(
			'user_id'      => $user_id,
			'flat_no'      => $flat_no,
			'device_token' => $token,
			'platform'     => sanitize_key( $params['platform'] ?? 'android' ),
			'device_name'  => sanitize_text_field( $params['device_name'] ?? '' ),
			'app_version'  => sanitize_text_field( $params['app_version'] ?? '1.0.0' ),
		) );

		if ( ! $registered ) {
			return new WP_Error( 'token_registration_failed', __( 'Could not register device token.', 'society-hubx' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Device token registered successfully.', 'society-hubx' ),
			'flat_no' => $flat_no,
		) );
	}

	/**
	 * POST /notifications/test-push
	 * Dispatches a test notification to verify credentials.
	 */
	public function send_test_push( $request ) {
		if ( ! class_exists( 'SHUBX51_FCM_Service' ) ) {
			return new WP_Error( 'fcm_not_loaded', __( 'FCM service not loaded.', 'society-hubx' ), array( 'status' => 500 ) );
		}

		if ( ! SHUBX51_FCM_Service::is_enabled() ) {
			return new WP_Error( 'fcm_disabled', __( 'FCM is not enabled or credentials are missing.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$params = $request->get_json_params();
		$target_flat = sanitize_text_field( $params['flat_no'] ?? '' );
		$target_token = sanitize_text_field( $params['device_token'] ?? '' );

		$title = __( '🧪 Society HubX Test Alert', 'society-hubx' );
		$body  = sprintf( __( 'Test notification from %s. Push notifications are working!', 'society-hubx' ), get_bloginfo( 'name' ) );
		$data  = array(
			'type'      => 'test_ping',
			'timestamp' => time(),
		);

		if ( ! empty( $target_token ) ) {
			$res = SHUBX51_FCM_Service::send_notification( $target_token, $title, $body, $data, 'high' );
		} elseif ( ! empty( $target_flat ) ) {
			$sent = SHUBX51_FCM_Service::send_to_flat( $target_flat, $title, $body, $data, 'high' );
			$res = ( $sent > 0 ) ? array( 'success' => true, 'sent' => $sent ) : new WP_Error( 'no_devices_found', __( 'No active registered devices for this flat.', 'society-hubx' ) );
		} else {
			$sent = SHUBX51_FCM_Service::send_to_all( $title, $body, $data, 'high' );
			$res = ( $sent > 0 ) ? array( 'success' => true, 'sent' => $sent ) : new WP_Error( 'no_devices_found', __( 'No active registered devices found in society.', 'society-hubx' ) );
		}

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Test notification dispatched successfully!', 'society-hubx' ),
			'details' => $res,
		) );
	}

	public function token_registration_check( $request ) {
		// Token registration is allowed for authenticated users, or clients presenting flat info
		SHUBX51_REST_Manager::authenticate_request( $request );
		return true;
	}

	public function admin_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		return current_user_can( 'manage_options' );
	}
}
