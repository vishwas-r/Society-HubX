<?php
/**
 * Module: Asset Manager
 * Handles Society Assets (Inventory, Generators, Gym Equipment).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Asset_Manager {

	private $db;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_SNESTX51_add_asset', array( $this, 'handle_add_asset' ) );
		add_action( 'admin_post_SNESTX51_edit_asset', array( $this, 'handle_edit_asset' ) );
		add_action( 'admin_post_SNESTX51_delete_asset', array( $this, 'handle_delete_asset' ) );
		add_action( 'admin_post_SNESTX51_restore_asset', array( $this, 'handle_restore_asset' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Asset Registry',
			'Assets',
			'manage_options',
			'snestx51-assets',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_asset() {
		if ( ! check_admin_referer( 'SNESTX51_asset_action' ) ) {
			wp_die( 'Security check failed' );
		}

		$data = array(
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'purchase_date' => isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '',
			'warranty_expiry' => isset( $_POST['warranty_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['warranty_expiry'] ) ) : '',
			'amc_provider' => isset( $_POST['amc_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_provider'] ) ) : '',
			'amc_phone' => isset( $_POST['amc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_phone'] ) ) : '',
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'value' => isset( $_POST['value'] ) ? floatval( wp_unslash( $_POST['value'] ) ) : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'id'              => uniqid('ast_'), // Ensure ID
		);

		$this->db->insert( 'assets', $data );
		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-assets&success=1' ) );
		exit;
	}

	public function handle_edit_asset() {
		if ( ! check_admin_referer( 'SNESTX51_asset_action' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = isset( $_POST['asset_id'] ) ? sanitize_text_field( wp_unslash( $_POST['asset_id'] ) ) : '';
		$data = array(
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'purchase_date' => isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '',
			'warranty_expiry' => isset( $_POST['warranty_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['warranty_expiry'] ) ) : '',
			'amc_provider' => isset( $_POST['amc_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_provider'] ) ) : '',
			'amc_phone' => isset( $_POST['amc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['amc_phone'] ) ) : '',
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'value' => isset( $_POST['value'] ) ? floatval( wp_unslash( $_POST['value'] ) ) : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
		);

		$this->db->update( 'assets', $data, array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-assets&success=1' ) );
		exit;
	}

	public function handle_delete_asset() {
		if ( ! check_admin_referer( 'SNESTX51_delete_asset_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$this->db->update( 'assets', array( 'status' => 'Archived' ), array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-assets&status=archived' ) );
		exit;
	}

	public function handle_restore_asset() {
		if ( ! check_admin_referer( 'SNESTX51_restore_asset_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$this->db->update( 'assets', array( 'status' => 'Active' ), array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-assets&success=1' ) );
		exit;
	}

	public function render_page() {
		SNESTX51_Admin_App::render_view('assets');
	}
}
