<?php
/**
 * Class: REST Documents Controller
 * Endpoints for managing society document vault.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Documents_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'documents';

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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
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
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)/restore',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_item' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get a list of documents.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$db = new SHUBX51_DB_Router();
		$documents = $db->get( 'documents' );

		if ( empty( $documents ) ) {
			return rest_ensure_response( array() );
		}

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'documents_manage' ) || current_user_can( 'manage_options' );

		$resident = $db->get_resident_by_wp_id( $user_id );
		$user_flat = $resident['flat_no'] ?? '';

		$filtered = array_filter(
			$documents,
			function( $doc ) use ( $is_admin, $user_id, $user_flat ) {
				$status = $doc['status'] ?? '';
				if ( $status === 'deleted' && ! $is_admin ) {
					return false;
				}

				$level = $doc['access_level'] ?? 'public';
				if ( $is_admin || $level === 'public' ) {
					return true;
				}
				if ( $level === 'owner_only' && ( (int) ( $doc['uploaded_by'] ?? 0 ) === $user_id || ( $doc['flat_no'] ?? '' ) === $user_flat ) ) {
					return true;
				}
				return false;
			}
		);

		return rest_ensure_response( array_values( $filtered ) );
	}

	/**
	 * Get single document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$docs = $db->get( 'documents', array( 'id' => $id ) );

		if ( empty( $docs ) ) {
			return new WP_Error( 'rest_doc_not_found', __( 'Document not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $docs[0] );
	}

	/**
	 * Create / Upload document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();

		$title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
		if ( empty( $title ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Document title is required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'documents_manage' ) || current_user_can( 'manage_options' );

		$db = new SHUBX51_DB_Router();
		$resident = $db->get_resident_by_wp_id( $user_id );
		$flat_no = $is_admin && isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : ( $resident['flat_no'] ?? '' );

		$file_url = isset( $params['file_url'] ) ? esc_url_raw( $params['file_url'] ) : ( isset( $params['file_path'] ) ? esc_url_raw( $params['file_path'] ) : '' );
		$data = array(
			'id'           => uniqid( 'doc_' ),
			'block'        => $resident['block'] ?? '',
			'title'        => $title,
			'category'     => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'general',
			'flat_no'      => $flat_no,
			'file_path'    => $file_url,
			'access_level' => isset( $params['access_level'] ) ? sanitize_text_field( $params['access_level'] ) : 'public',
			'status'       => $is_admin ? 'approved' : 'pending',
			'uploaded_by'  => $user_id,
			'created_at'   => current_time( 'mysql' ),
		);

		$result = $db->insert( 'documents', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $is_admin ) {
			require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
			$rm = new SHUBX51_Request_Manager();
			$rm->create_request( 'documents', 'upload', $data, $data['id'] );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'document' => $data ), 201 );
	}

	/**
	 * Update document metadata.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'documents', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_doc_not_found', __( 'Document not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['title'] ) ) {
			$data['title'] = sanitize_text_field( $params['title'] );
		}
		if ( isset( $params['category'] ) ) {
			$data['category'] = sanitize_text_field( $params['category'] );
		}
		if ( isset( $params['access_level'] ) ) {
			$data['access_level'] = sanitize_text_field( $params['access_level'] );
		}

		$result = $db->update( 'documents', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Document updated successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Delete a document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'documents', array( 'id' => $id ) );

		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_doc_not_found', __( 'Document not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'documents_manage' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin && (int) $existing[0]['uploaded_by'] !== $user_id ) {
			return new WP_Error( 'rest_forbidden', __( 'Unauthorized to delete this document.', 'society-hubx' ), array( 'status' => 403 ) );
		}

		$result = $db->update( 'documents', array( 'status' => 'deleted' ), array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Document deleted successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Restore a deleted document.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$result = $db->update( 'documents', array( 'status' => 'approved' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Document restored successfully.', 'society-hubx' ) ) );
	}

	public function get_items_permissions_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}

	public function get_item_permissions_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}

	public function create_item_permissions_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}

	public function update_item_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		if ( $rbac->has_capability( $user_id, 'documents_manage' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$docs = $db->get( 'documents', array( 'id' => $id ) );
		if ( ! empty( $docs ) && (int) $docs[0]['uploaded_by'] === $user_id ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Unauthorized to update this document.', 'society-hubx' ), array( 'status' => 403 ) );
	}

	public function delete_item_permissions_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	public function admin_permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$rbac = new SHUBX51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'documents_manage' ) || current_user_can( 'manage_options' );
	}
}
