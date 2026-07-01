<?php
/**
 * Class: REST Staff Controller
 * Endpoints for managing society staff and daily help.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_REST_Staff_Controller extends WP_REST_Controller {

	protected $namespace = 'society-nestx/v1';
	protected $rest_base = 'staff';

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
		$staff = $db->get( 'daily_help' );

		if ( empty( $staff ) ) {
			return rest_ensure_response( array() );
		}

		// Apply DPDP masking
		$privileged = Society_NestX::get_instance()->rbac->check_capability( get_current_user_id(), 'view_pii' );
		
		foreach ( $staff as &$s ) {
			if ( ! $privileged ) {
				$s['phone'] = SNESTX51_Privacy_Manager::mask_data( $s['phone'] );
			}
		}

		return rest_ensure_response( $staff );
	}
}
