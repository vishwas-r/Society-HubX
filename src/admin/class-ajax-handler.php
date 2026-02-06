<?php
/**
 * Class: AJAX Handler
 * Handles AJAX endpoints for module configuration and other dynamic requests.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_AJAX_Handler {

	public function __construct() {
		add_action( 'wp_ajax_sgvx51_get_module_config', array( $this, 'handle_get_module_config' ) );
		add_action( 'wp_ajax_sgvx51_get_receipt', array( $this, 'handle_get_receipt' ) );
		add_action( 'wp_ajax_sgvx51_approve_request', array( $this, 'handle_approve_request' ) );
		add_action( 'wp_ajax_sgvx51_reject_request', array( $this, 'handle_reject_request' ) );
		add_action( 'wp_ajax_sgvx51_bulk_process_requests', array( $this, 'handle_bulk_process_requests' ) );
	}

	/**
	 * Handle AJAX request to get module configuration (nonces, etc.)
	 * No nonce verification needed since this only serves public nonce data
	 */
	public function handle_get_module_config() {
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated' ), 401 );
		}

		// Verify user has permission to access admin pages
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		$module = isset( $_POST['module'] ) ? sanitize_key( $_POST['module'] ) : '';

		if ( empty( $module ) ) {
			wp_send_json_error( array( 'message' => 'Module parameter missing' ), 400 );
		}

		$config = $this->get_module_config( $module );

		if ( $config ) {
			wp_send_json_success( $config );
		} else {
			wp_send_json_error( array( 'message' => 'Module config not found' ), 404 );
		}
	}

	/**
	 * Get configuration for a specific module
	 *
	 * @param string $module The module name (residents, facilities, etc.)
	 * @return array|false Configuration array or false if module not found
	 */
	private function get_module_config( $module ) {
		$config = array();

		switch ( $module ) {
			case 'residents':
				$config = array(
					'nonce'              => wp_create_nonce( 'sgvx51_resident_nonce' ),
					'deleteNonce'        => wp_create_nonce( 'sgvx51_delete_resident_nonce' ),
					'deleteHistoryNonce' => wp_create_nonce( 'sgvx51_delete_history_nonce' ),
					'restoreNonce'       => wp_create_nonce( 'sgvx51_restore_resident_nonce' ),
				);
				break;

			case 'facilities':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_facility_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_facility_nonce' ),
				);
				break;

			case 'notices':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_notice_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_notice_nonce' ),
				);
				break;

			case 'documents':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_document_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_document_nonce' ),
				);
				break;

			case 'expenses':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_nonce' ),
				);
				break;

			case 'accounts':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_account_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_invoice_nonce' ),
				);
				break;

			case 'vehicles':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_vehicle_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_vehicle_nonce' ),
				);
				break;

			case 'flats':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_flat_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_flat_nonce' ),
				);
				break;

			case 'staff':
				$config = array(
					'nonce'       => wp_create_nonce( 'sgvx51_staff_nonce' ),
					'deleteNonce' => wp_create_nonce( 'sgvx51_delete_staff_nonce' ),
				);
				break;


			default:
				return false;
		}

		return $config;
	}

	/**
	 * Handle AJAX request to get receipt data
	 * This endpoint is available to logged-in users (residents can only view their own receipts)
	 */
	public function handle_get_receipt() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sgvx51_facility_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
		}

		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated' ), 401 );
		}

		$invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( $_POST['invoice_id'] ) : '';

		if ( empty( $invoice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invoice ID missing' ), 400 );
		}

		// Get current user
		$current_user = wp_get_current_user();
		$is_admin = current_user_can( 'manage_options' );

		// Get invoice data
		$plugin = Society_Govern_X::get_instance();
		$invoice = $plugin->db->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			wp_send_json_error( array( 'message' => 'Invoice not found' ), 404 );
		}

		// Permission check: Residents can only view their own invoices, admins can view all
		if ( ! $is_admin ) {
			// Get resident ID for current user
			$resident = $plugin->db->get_resident_by_wp_id( $current_user->ID );
			if ( ! $resident ) {
				wp_send_json_error( array( 'message' => 'Resident not found' ), 403 );
			}
			// Check if resident's flat_no matches invoice's flat_no
			if ( $resident['flat_no'] !== $invoice['flat_no'] ) {
				wp_send_json_error( array( 'message' => 'Not authorized to view this receipt' ), 403 );
			}
		}

		// Generate receipt data using Receipt Manager
		require_once SGVX51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		$receipt_mgr = new SGVX51_Receipt_Manager();
		$receipt_data = $receipt_mgr->prepare_receipt_data( $invoice );

		if ( $receipt_data ) {
			wp_send_json_success( $receipt_data );
		} else {
			wp_send_json_error( array( 'message' => 'Error generating receipt' ), 500 );
		}
	}

	/**
	 * AJAX: Approve Request
	 */
	public function handle_approve_request() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		
		$id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
		if (!$id) wp_send_json_error(['message' => 'Missing ID'], 400);

		require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SGVX51_Request_Manager();
		$res = $rm->approve_request( $id );

		if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
		wp_send_json_success( ['message' => 'Request approved successfully'] );
	}

	/**
	 * AJAX: Reject Request
	 */
	public function handle_reject_request() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

		$id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
		$note = isset($_POST['admin_note']) ? sanitize_textarea_field($_POST['admin_note']) : '';
		if (!$id) wp_send_json_error(['message' => 'Missing ID'], 400);

		require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SGVX51_Request_Manager();
		$res = $rm->reject_request( $id, $note );

		if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
		wp_send_json_success( ['message' => 'Request rejected successfully'] );
	}

	/**
	 * AJAX: Bulk Process Requests
	 */
	public function handle_bulk_process_requests() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

		$ids = isset($_POST['ids'] ) ? (array)$_POST['ids'] : [];
		$action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
		$note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

		if ( empty($ids) ) wp_send_json_error(['message' => 'No items selected'], 400);

		require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SGVX51_Request_Manager();
		
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $action === 'approve' ) {
				$res = $rm->approve_request( $id );
			} else {
				$res = $rm->reject_request( $id, $note );
			}
			if ( ! is_wp_error( $res ) ) $count++;
		}

		wp_send_json_success( ['message' => "$count items processed successfully"] );
	}
}
