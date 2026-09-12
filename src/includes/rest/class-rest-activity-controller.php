<?php
/**
 * Class: REST Activity Controller
 * Endpoints for society activity logs.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Activity_Controller extends WP_REST_Controller {

	protected $namespace = 'namma-society/v1';
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
		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
		$logs = $db->get( 'activity_logs' );

		if ( empty( $logs ) ) {
			return rest_ensure_response( array() );
		}

		return rest_ensure_response( $logs );
	}

	public function get_items_permissions_check( $request ) {
		if ( ! NAMMASOCIETY51_Plugin::get_instance()->rbac->has_capability( get_current_user_id(), 'settings_manage' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view activity logs.', 'namma-society' ), array( 'status' => 403 ) );
		}
		return true;
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Activity_Controller' ) && ! class_exists( 'SHUBX51_REST_Activity_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Activity_Controller', 'SHUBX51_REST_Activity_Controller' );
}
