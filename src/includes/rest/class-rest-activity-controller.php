<?php
/**
 * Class: REST Activity Controller
 * Endpoints for society activity logs.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_REST_Activity_Controller extends WP_REST_Controller {

	protected $namespace = 'society-nestx/v1';
	protected $rest_base = 'activity';

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( 'SNESTX51_REST_Manager', 'check_permission' ),
			),
		) );
	}

	public function get_items( $request ) {
		$db = Society_NestX::get_instance()->db;
		$logs = $db->get( 'activity_logs' );

		if ( empty( $logs ) ) {
			return rest_ensure_response( array() );
		}

		return rest_ensure_response( $logs );
	}
}
