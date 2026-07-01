<?php
/**
 * Class: REST Activity Controller
 * Endpoints for society activity logs.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Activity_Controller extends WP_REST_Controller {

	protected $namespace = 'society-hubx/v1';
	protected $rest_base = 'activity';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			),
		) );
	}

	public function get_items( $request ) {
		$db = Society_HubX::get_instance()->db;
		$logs = $db->get( 'activity_logs' );

		if ( empty( $logs ) ) {
			return rest_ensure_response( array() );
		}

		return rest_ensure_response( $logs );
	}

	public function get_items_permissions_check( $request ) {
		if ( ! Society_HubX::get_instance()->rbac->has_capability( get_current_user_id(), 'settings_manage' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view activity logs.', 'society-hubx' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
