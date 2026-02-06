<?php
/**
 * Module: Notice Board
 * Handles Public/Private Notices.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Notice_Board {

	private $db;
	private $drive;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		$this->drive = new SGVX51_Drive_Manager();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_add_notice', array( $this, 'handle_add_notice' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Notice Board',
			'Notices',
			'manage_options',
			'sgvx51-notices',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_notice() {
		if ( ! check_admin_referer( 'sgvx51_add_notice_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$data = array(
			'title'          => sanitize_text_field( $_POST['title'] ),
			'content'        => sanitize_textarea_field( $_POST['content'] ),
			'audience'       => sanitize_text_field( $_POST['audience'] ),
			'expiry_date'    => sanitize_text_field( $_POST['expiry_date'] ),
			'attachment_url' => '',
			'created_at'     => current_time( 'mysql' ),
		);

		// Handle Attachment
		if ( ! empty( $_FILES['attachment'] ) && $_FILES['attachment']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Notices' );
			if ( ! is_wp_error( $folder ) ) {
				$url = $this->drive->upload_to_folder( $folder, $_FILES['attachment'] );
				if ( ! is_wp_error( $url ) ) {
					$data['attachment_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

		$this->db->insert( 'notices', $data );
		wp_redirect( admin_url( 'admin.php?page=sgvx51-notices&success=1' ) );
		exit;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('notices');
	}
}
