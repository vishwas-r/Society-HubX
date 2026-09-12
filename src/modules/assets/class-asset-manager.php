<?php
/**
 * Module: Asset Manager
 * Handles Society Assets (Inventory, Generators, Gym Equipment).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Asset_Manager implements NAMMASOCIETY51_Module {

	private $db;

	public function __construct() {
		$this->db = new NAMMASOCIETY51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_nammasociety51_add_asset', array( $this, 'handle_add_asset' ) );
		add_action( 'wp_ajax_nammasociety51_edit_asset', array( $this, 'handle_edit_asset' ) );
		add_action( 'wp_ajax_nammasociety51_delete_asset', array( $this, 'handle_delete_asset' ) );
		add_action( 'wp_ajax_nammasociety51_restore_asset', array( $this, 'handle_restore_asset' ) );
		add_action( 'wp_ajax_nammasociety51_get_asset', array( $this, 'handle_get_asset' ) );

		// Form POST Actions
		add_action( 'admin_post_nammasociety51_add_asset', array( $this, 'handle_add_asset' ) );
		add_action( 'admin_post_nammasociety51_edit_asset', array( $this, 'handle_edit_asset' ) );
		add_action( 'admin_post_nammasociety51_delete_asset', array( $this, 'handle_delete_asset' ) );
		add_action( 'admin_post_nammasociety51_restore_asset', array( $this, 'handle_restore_asset' ) );

		// Module Registration
		add_filter( 'nammasociety51_get_module_assets', array( $this, 'get_instance' ) );
	}

	public function get_instance() {
		return $this;
	}

	public function get_module_slug() {
		return 'assets';
	}

	public function execute_request( $action, $payload ) {
		return true;
	}

	public function register_menu() {
		add_submenu_page(
			'nammasociety51-settings',
			'Asset Registry',
			'Assets',
			'read',
			'nammasociety51-assets',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_asset() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_asset_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_asset_action' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$data = array(
			'name'            => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'purchase_date'   => isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '0000-00-00',
			'warranty_expiry' => isset( $_POST['warranty_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['warranty_expiry'] ) ) : '0000-00-00',
			'amc_provider'    => isset( $_POST['amc_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_provider'] ) ) : '',
			'amc_phone'       => isset( $_POST['amc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_phone'] ) ) : '',
			'status'          => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Active',
			'category'        => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'value'           => isset( $_POST['value'] ) ? floatval( wp_unslash( $_POST['value'] ) ) : 0,
			'description'     => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'id'              => uniqid( 'ast_' ),
		);

		if ( empty( $data['name'] ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Asset name is required' ), 400 );
			}
			wp_die( 'Asset name is required' );
		}

		$res = $this->db->insert( 'assets', $data );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Asset added successfully', 'id' => $data['id'] ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-assets&success=1' ) );
		exit;
	}

	public function handle_edit_asset() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_asset_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_asset_action' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : ( isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '' );
		if ( empty( $id ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Asset ID missing' ), 400 );
			}
			wp_die( 'Asset ID missing' );
		}

		$data = array(
			'name'            => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'purchase_date'   => isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '0000-00-00',
			'warranty_expiry' => isset( $_POST['warranty_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['warranty_expiry'] ) ) : '0000-00-00',
			'amc_provider'    => isset( $_POST['amc_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_provider'] ) ) : '',
			'amc_phone'       => isset( $_POST['amc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_phone'] ) ) : '',
			'status'          => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'category'        => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'value'           => isset( $_POST['value'] ) ? floatval( wp_unslash( $_POST['value'] ) ) : 0,
			'description'     => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		$res = $this->db->update( 'assets', $data, array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Asset updated successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-assets&success=1' ) );
		exit;
	}

	public function handle_delete_asset() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_delete_asset_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_delete_asset_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : ( isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : '' );
		$res = $this->db->update( 'assets', array( 'status' => 'Archived' ), array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Asset archived successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-assets&status=archived' ) );
		exit;
	}

	public function handle_restore_asset() {
		if ( wp_doing_ajax() ) {
			$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'nammasociety51_restore_asset_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_restore_asset_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_manage' ) && ! current_user_can( 'manage_options' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : ( isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : '' );
		$res = $this->db->update( 'assets', array( 'status' => 'Active' ), array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ), 500 );
			}
			wp_send_json_success( array( 'message' => 'Asset restored successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-assets&success=1' ) );
		exit;
	}

	public function handle_get_asset() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'nammasociety51_asset_action' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) && ! wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_view' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : ( isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : '' );
		$assets = $this->db->get( 'assets', array( 'id' => $id ) );

		if ( ! empty( $assets ) ) {
			wp_send_json_success( $assets[0] );
		}

		wp_send_json_error( array( 'message' => 'Asset not found' ), 404 );
	}

	public function render_page() {
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'assets_view' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to view assets.' );
		}
		NAMMASOCIETY51_Admin_App::render_view( 'assets' );
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_Asset_Manager' ) && ! class_exists( 'SHUBX51_Asset_Manager', false ) ) {
	class_alias( 'NAMMASOCIETY51_Asset_Manager', 'SHUBX51_Asset_Manager' );
}
