<?php
/**
 * Class: REST Staff Controller
 * Endpoints for managing society staff and daily help.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Staff_Controller extends WP_REST_Controller {

	protected $namespace = 'society-hubx/v1';
	protected $rest_base = 'staff';

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
		$staff = $db->get( 'daily_help' );

		if ( empty( $staff ) ) {
			return rest_ensure_response( array() );
		}

		// Apply DPDP masking
		$privileged = Society_HubX::get_instance()->rbac->has_capability( get_current_user_id(), 'staff_manage' );
		
		foreach ( $staff as &$s ) {
			if ( ! $privileged ) {
				$s['phone'] = SHUBX51_Privacy_Manager::mask_data( $s['phone'] );
			}
		}

		return rest_ensure_response( $staff );
	}

	public function get_items_permissions_check( $request ) {
		if ( ! Society_HubX::get_instance()->rbac->has_capability( get_current_user_id(), 'staff_view' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view staff.', 'society-hubx' ), array( 'status' => 403 ) );
		}
		return true;
	}
}
