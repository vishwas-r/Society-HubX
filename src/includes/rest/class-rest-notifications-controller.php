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
		return is_user_logged_in();
	}
}
