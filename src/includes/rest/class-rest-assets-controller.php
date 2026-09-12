<?php
/**
 * Class: REST Assets Controller
 * Endpoints for managing society assets registry.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Assets_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'assets';

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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * List assets.
	 */
	public function get_items( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$assets = $db->get( 'assets' );
		return rest_ensure_response( $assets ? $assets : array() );
	}

	/**
	 * Get single asset.
	 */
	public function get_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$assets = $db->get( 'assets', array( 'id' => $id ) );

		if ( empty( $assets ) ) {
			return new WP_Error( 'rest_asset_not_found', __( 'Asset not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $assets[0] );
	}

	/**
	 * Create asset.
	 */
	public function create_item( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
		if ( empty( $name ) ) {
			return new WP_Error( 'rest_invalid_params', __( 'Asset name is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'              => uniqid( 'ast_' ),
			'name'            => $name,
			'purchase_date'   => isset( $params['purchase_date'] ) ? sanitize_text_field( $params['purchase_date'] ) : '0000-00-00',
			'warranty_expiry' => isset( $params['warranty_expiry'] ) ? sanitize_text_field( $params['warranty_expiry'] ) : '0000-00-00',
			'amc_provider'    => isset( $params['amc_provider'] ) ? sanitize_text_field( $params['amc_provider'] ) : '',
			'amc_phone'       => isset( $params['amc_phone'] ) ? sanitize_text_field( $params['amc_phone'] ) : '',
			'status'          => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'Active',
			'category'        => isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : 'General',
			'value'           => isset( $params['value'] ) ? floatval( $params['value'] ) : 0.00,
			'description'     => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
		);

		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->insert( 'assets', $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'asset' => $data ), 201 );
	}

	/**
	 * Update asset.
	 */
	public function update_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'assets', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_asset_not_found', __( 'Asset not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['name'] ) ) {
			$data['name'] = sanitize_text_field( $params['name'] );
		}
		if ( isset( $params['purchase_date'] ) ) {
			$data['purchase_date'] = sanitize_text_field( $params['purchase_date'] );
		}
		if ( isset( $params['warranty_expiry'] ) ) {
			$data['warranty_expiry'] = sanitize_text_field( $params['warranty_expiry'] );
		}
		if ( isset( $params['amc_provider'] ) ) {
			$data['amc_provider'] = sanitize_text_field( $params['amc_provider'] );
		}
		if ( isset( $params['amc_phone'] ) ) {
			$data['amc_phone'] = sanitize_text_field( $params['amc_phone'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['category'] ) ) {
			$data['category'] = sanitize_text_field( $params['category'] );
		}
		if ( isset( $params['value'] ) ) {
			$data['value'] = floatval( $params['value'] );
		}
		if ( isset( $params['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $params['description'] );
		}

		$result = $db->update( 'assets', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Asset updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete asset.
	 */
	public function delete_item( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update( 'assets', array( 'status' => 'Archived' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Asset archived successfully.', 'namma-society' ) ) );
	}

	public function get_items_permissions_check( $request ) {
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'assets_view' ) || current_user_can( 'manage_options' );
	}

	public function create_item_permissions_check( $request ) {
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'assets_manage' ) || current_user_can( 'manage_options' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_REST_Assets_Controller' ) && ! class_exists( 'SHUBX51_REST_Assets_Controller', false ) ) {
	class_alias( 'NAMMASOCIETY51_REST_Assets_Controller', 'SHUBX51_REST_Assets_Controller' );
}
