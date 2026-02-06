<?php
/**
 * Module: Asset Manager
 * Handles Society Assets (Inventory, Generators, Gym Equipment).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Asset_Manager {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_add_asset', array( $this, 'handle_add_asset' ) );
		add_action( 'admin_post_sgvx51_edit_asset', array( $this, 'handle_edit_asset' ) );
		add_action( 'admin_post_sgvx51_delete_asset', array( $this, 'handle_delete_asset' ) );
		add_action( 'admin_post_sgvx51_restore_asset', array( $this, 'handle_restore_asset' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Asset Registry',
			'Assets',
			'manage_options',
			'sgvx51-assets',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_asset() {
		if ( ! check_admin_referer( 'sgvx51_asset_action' ) ) {
			wp_die( 'Security check failed' );
		}

		$data = array(
			'name'            => sanitize_text_field( $_POST['name'] ),
			'purchase_date'   => sanitize_text_field( $_POST['purchase_date'] ),
			'warranty_expiry' => sanitize_text_field( $_POST['warranty_expiry'] ),
			'amc_provider'    => sanitize_text_field( $_POST['amc_provider'] ),
			'amc_phone'       => sanitize_text_field( $_POST['amc_phone'] ),
			'status'          => sanitize_text_field( $_POST['status'] ),
			'category'        => sanitize_text_field( $_POST['category'] ),
			'value'           => floatval( $_POST['value'] ),
			'description'     => sanitize_textarea_field( $_POST['description'] ),
			'id'              => uniqid('ast_'), // Ensure ID
		);

		$this->db->insert( 'assets', $data );
		wp_redirect( admin_url( 'admin.php?page=sgvx51-assets&success=1' ) );
		exit;
	}

	public function handle_edit_asset() {
		if ( ! check_admin_referer( 'sgvx51_asset_action' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = sanitize_text_field( $_POST['asset_id'] );
		$data = array(
			'name'            => sanitize_text_field( $_POST['name'] ),
			'purchase_date'   => sanitize_text_field( $_POST['purchase_date'] ),
			'warranty_expiry' => sanitize_text_field( $_POST['warranty_expiry'] ),
			'amc_provider'    => sanitize_text_field( $_POST['amc_provider'] ),
			'amc_phone'       => sanitize_text_field( $_POST['amc_phone'] ),
			'status'          => sanitize_text_field( $_POST['status'] ),
			'category'        => sanitize_text_field( $_POST['category'] ),
			'value'           => floatval( $_POST['value'] ),
			'description'     => sanitize_textarea_field( $_POST['description'] ),
		);

		$this->db->update( 'assets', $data, array( 'id' => $id ) );
		wp_redirect( admin_url( 'admin.php?page=sgvx51-assets&success=1' ) );
		exit;
	}

	public function handle_delete_asset() {
		if ( ! check_admin_referer( 'sgvx51_delete_asset_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = sanitize_text_field( $_GET['id'] );
		$this->db->update( 'assets', array( 'status' => 'Archived' ), array( 'id' => $id ) );

		wp_redirect( admin_url( 'admin.php?page=sgvx51-assets&status=archived' ) );
		exit;
	}

	public function handle_restore_asset() {
		if ( ! check_admin_referer( 'sgvx51_restore_asset_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = sanitize_text_field( $_GET['id'] );
		$this->db->update( 'assets', array( 'status' => 'Active' ), array( 'id' => $id ) );

		wp_redirect( admin_url( 'admin.php?page=sgvx51-assets&success=1' ) );
		exit;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('assets');
	}
}
