<?php
/**
 * Module: Document Manager
 * Handles the "Document Vault".
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Document_Manager {

	private $drive;
	private $db;

	public function __construct() {
		$this->drive = new SGVX51_Drive_Manager();
		$this->db    = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_upload_doc', array( $this, 'handle_upload' ) );
		add_action( 'admin_post_sgvx51_approve_doc', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_sgvx51_reject_doc', array( $this, 'handle_reject' ) );
		add_action( 'admin_post_sgvx51_delete_doc', array( $this, 'handle_delete' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Document Vault',
			'Documents',
			'manage_options',
			'sgvx51-documents',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Admin Direct Upload (Legacy/Direct)
	 */
	public function handle_upload() {
		if ( ! check_admin_referer( 'sgvx51_upload_doc_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$flat_no = sanitize_text_field( $_POST['flat_no'] );

		if ( empty( $flat_no ) ) {
			// DEBUG: Print all POST vars to see why it's missing
			wp_die( 'Error: Flat Number is empty. POST Data: ' . print_r($_POST, true) );
		}
		
		if ( ! empty( $_FILES['doc_file'] ) ) {
			$res = $this->drive->upload_file( $flat_no, $_FILES['doc_file'] );
			
			if ( is_wp_error( $res ) ) {
                wp_redirect( add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'error' => urlencode($res->get_error_message()) ), admin_url('admin.php') ) );
			} else {
                wp_redirect( add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'success' => '1' ), admin_url('admin.php') ) );
			}
			exit;
		}
	}

	public function handle_approve() {
		$this->update_status( 'approved' );
	}

	public function handle_reject() {
        // If status was deletion_pending, we set back to approved.
        // If status was pending (upload), we set to rejected (or delete?).
        // For now, let's just use 'rejected' status for uploads, and 'approved' for deletion cancellations.
        
        if ( ! check_admin_referer( 'sgvx51_doc_action' ) ) wp_die( 'Security check failed' );
		
		$doc_id = sanitize_text_field( $_GET['doc_id'] );
		$docs = $this->db->get( 'documents' );
        $new_status = 'rejected';

		foreach ( $docs as $d ) {
			if ( $d['id'] === $doc_id ) {
                if($d['status'] === 'deletion_pending') {
                    $new_status = 'approved'; // Cancel deletion = Approved again
                }
				break;
			}
		}
        
		$this->update_status( $new_status );
	}

	private function update_status( $status ) {
		if ( ! check_admin_referer( 'sgvx51_doc_action' ) ) wp_die( 'Security check failed' );
		
		$doc_id = sanitize_text_field( $_GET['doc_id'] );
		$docs = $this->db->get( 'documents' );
		
		foreach ( $docs as $i => $d ) {
			if ( $d['id'] === $doc_id ) {
				$this->db->update( 'documents', array( 'status' => $status ), array( 'id' => $doc_id ) );
				break;
			}
		}
		
		wp_redirect( admin_url( 'admin.php?page=sgvx51-documents&updated=1' ) );
		exit;
	}

	public function handle_delete() {
		if ( ! check_admin_referer( 'sgvx51_delete_doc_nonce' ) ) wp_die( 'Security check failed' );
		
		$flat_no = sanitize_text_field( $_GET['flat'] );
		$file_name = sanitize_text_field( $_GET['file'] );
		$doc_id = isset($_GET['doc_id']) ? sanitize_text_field( $_GET['doc_id'] ) : '';

		// 1. Soft Delete: Do NOT delete physical file.
		// $res = $this->drive->delete_file( $flat_no, $file_name ); 

		// 2. Mark Metadata as Deleted
		if ( $doc_id ) {
			$this->db->update( 'documents', array( 'status' => 'deleted' ), array( 'id' => $doc_id ) );
		}

        wp_redirect( add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'deleted' => '1' ), admin_url('admin.php') ) );
		exit;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('documents');
	}
}
