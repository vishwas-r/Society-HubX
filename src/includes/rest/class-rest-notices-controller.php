<?php
/**
 * Class: REST Notices Controller
 * Endpoints for managing society notice board.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Notices_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'notices';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'notices_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'notices_manage_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'notices_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/pin',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle_pin' ),
					'permission_callback' => array( $this, 'notices_manage_check' ),
				),
			)
		);
	}

	/**
	 * List notices.
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$notices = $db->get( 'notices' );

		if ( empty( $notices ) ) {
			return rest_ensure_response( array() );
		}

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'notices_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_type = $resident['type'] ?? 'Owner';
			$notices = array_filter(
				$notices,
				function( $n ) use ( $user_type ) {
					$aud = $n['audience'] ?? 'all';
					if ( $aud === 'all' ) {
						return true;
					}
					if ( $aud === 'owners' && strcasecmp( $user_type, 'Owner' ) === 0 ) {
						return true;
					}
					if ( $aud === 'tenants' && strcasecmp( $user_type, 'Tenant' ) === 0 ) {
						return true;
					}
					return false;
				}
			);
		}

		// Sort pinned to top
		usort(
			$notices,
			function( $a, $b ) {
				$pin_a = intval( $a['pinned'] ?? 0 );
				$pin_b = intval( $b['pinned'] ?? 0 );
				if ( $pin_a !== $pin_b ) {
					return $pin_b - $pin_a;
				}
				return strcmp( $b['created_at'] ?? '', $a['created_at'] ?? '' );
			}
		);

		return rest_ensure_response( array_values( $notices ) );
	}

	/**
	 * Get single notice.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$notices = $db->get( 'notices', array( 'id' => $id ) );

		if ( empty( $notices ) ) {
			return new WP_Error( 'rest_notice_not_found', __( 'Notice not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $notices[0] );
	}

	/**
	 * Create notice.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$title   = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		$content = isset( $params['content'] ) ? wp_kses_post( $params['content'] ) : '';

		if ( empty( $title ) || empty( $content ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Title and content are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'          => uniqid( 'ntc_' ),
			'title'       => $title,
			'content'     => $content,
			'category'    => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'General',
			'audience'    => isset( $params['audience'] ) ? sanitize_text_field( $params['audience'] ) : 'all',
			'urgency'     => isset( $params['urgency'] ) ? sanitize_text_field( $params['urgency'] ) : 'normal',
			'pinned'      => isset( $params['pinned'] ) ? intval( $params['pinned'] ) : 0,
			'expiry_date' => isset( $params['expiry_date'] ) ? sanitize_text_field( $params['expiry_date'] ) : '0000-00-00',
			'created_by'  => get_current_user_id(),
			'created_at'  => current_time( 'mysql' ),
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'notices', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'notice' => $data ), 201 );
	}

	/**
	 * Update notice.
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'notices', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_notice_not_found', __( 'Notice not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['title'] ) ) {
			$data['title'] = sanitize_text_field( $params['title'] );
		}
		if ( isset( $params['content'] ) ) {
			$data['content'] = wp_kses_post( $params['content'] );
		}
		if ( isset( $params['category'] ) ) {
			$data['category'] = sanitize_text_field( $params['category'] );
		}
		if ( isset( $params['audience'] ) ) {
			$data['audience'] = sanitize_text_field( $params['audience'] );
		}
		if ( isset( $params['urgency'] ) ) {
			$data['urgency'] = sanitize_text_field( $params['urgency'] );
		}
		if ( isset( $params['pinned'] ) ) {
			$data['pinned'] = intval( $params['pinned'] );
		}

		$result = $db->update( 'notices', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Notice updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete notice.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'notices', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Notice deleted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Toggle pinned status.
	 */
	public function toggle_pin( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'notices', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_notice_not_found', __( 'Notice not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$new_pin = empty( $existing[0]['pinned'] ) ? 1 : 0;
		$result = $db->update( 'notices', array( 'pinned' => $new_pin ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'pinned' => $new_pin ) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function notices_manage_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'notices_manage' ) || current_user_can( 'manage_options' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Notices_Controller' ) && ! class_exists( 'SHUBX51_REST_Notices_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Notices_Controller', 'SHUBX51_REST_Notices_Controller' );
}
