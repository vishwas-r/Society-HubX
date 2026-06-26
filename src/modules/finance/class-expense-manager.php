<?php
/**
 * Module: Expense Manager
 * Handles Expenses and Receipts.
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Expense_Manager {

	private $db;
	private $drive;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		$this->drive = new SGVX51_Drive_Manager();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_add_expense', array( $this, 'handle_add_expense' ) );
		add_action( 'admin_post_sgvx51_edit_expense', array( $this, 'handle_edit_expense' ) );
		add_action( 'admin_post_sgvx51_delete_expense', array( $this, 'handle_delete_expense' ) );
		add_action( 'admin_post_sgvx51_approve_expense', array( $this, 'handle_approve_expense' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_sgvx51_add_expense', array( $this, 'handle_add_expense_ajax' ) );
		add_action( 'wp_ajax_sgvx51_edit_expense', array( $this, 'handle_edit_expense_ajax' ) );
		add_action( 'wp_ajax_sgvx51_delete_expense', array( $this, 'handle_delete_expense_ajax' ) );
		add_action( 'wp_ajax_sgvx51_approve_expense', array( $this, 'handle_approve_expense_ajax' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Accounts & Expenses',
			'Expenses',
			'manage_options',
			'sgvx51-expenses',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_expense() {
		if ( ! check_admin_referer( 'sgvx51_add_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$data = array(
			'category'    => sanitize_text_field( $_POST['category'] ),
			'description' => sanitize_text_field( $_POST['description'] ),
			'amount'      => floatval( $_POST['amount'] ),
			'date'        => sanitize_text_field( $_POST['date'] ),
			'payee'       => sanitize_text_field( $_POST['payee'] ),
			'added_by'    => get_current_user_id(),
			'receipt_url' => '',
			'status'      => 'pending_secretary',
            'account_type'=> sanitize_text_field( $_POST['account_type'] ?? 'bank' ),
		);

		$year = date( 'Y', strtotime( $data['date'] ) );
		$table = 'expenses';

		// Handle Receipt Upload
		if ( ! empty( $_FILES['receipt_file'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

		$result = $this->db->insert( $table, $data );

		if ( ! is_wp_error( $result ) ) {
            // Create a request for approval
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $req_id = $rm->create_request( 'expenses', 'add_expense', $data, $result );
            
            if ( ! is_wp_error( $req_id ) ) {
                $this->db->update( 'requests', array( 'status' => 'pending_secretary' ), array( 'id' => $req_id ) );
            }
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&year=' . $year . '&success=1' ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		}
		exit;
	}

	public function handle_edit_expense() {
		if ( ! check_admin_referer( 'sgvx51_edit_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = sanitize_text_field( $_POST['expense_id'] );
		$data = array(
			'category'    => sanitize_text_field( $_POST['category'] ),
			'description' => sanitize_text_field( $_POST['description'] ),
			'amount'      => floatval( $_POST['amount'] ),
			'date'        => sanitize_text_field( $_POST['date'] ),
			'payee'       => sanitize_text_field( $_POST['payee'] ),
            'account_type'=> sanitize_text_field( $_POST['account_type'] ?? 'bank' ),
		);

		$year = date( 'Y', strtotime( $data['date'] ) );
		$table = 'expenses';

		// Handle Receipt Upload
		if ( ! empty( $_FILES['receipt_file'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

		$result = $this->db->update( $table, $data, array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&year=' . $year . '&success=1' ) );
		}
		exit;
	}

	public function handle_delete_expense() {
		if ( ! check_admin_referer( 'sgvx51_delete_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = sanitize_text_field( $_GET['id'] );
		$date = sanitize_text_field( $_GET['date'] );
		
		if ( empty($date) ) {
			wp_die('Expense date is required for deletion.');
		}

		$year = date( 'Y', strtotime( $date ) );
		$table = 'expenses';

		$this->db->delete( $table, array( 'id' => $id ) );

		wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&year=' . $year . '&deleted=1' ) );
		exit;
	}

	public function handle_approve_expense() {
		if ( ! check_admin_referer( 'sgvx51_approve_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$id = sanitize_text_field( $_POST['expense_id'] );
		$date = sanitize_text_field( $_POST['expense_date'] );

		$year = date( 'Y', strtotime( $date ) );

        // Direct approval is now replaced by multi-stage workflow if requested via Admin UI
        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $result = $rm->approve_request( $id );

		if ( is_wp_error( $result ) ) {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-expenses&year=' . $year . '&approved=1' ) );
		}
		exit;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('expenses');
	}
	
	/**
	 * AJAX Handler for Adding Expense
	 */
	public function handle_add_expense_ajax() {
		check_ajax_referer( 'sgvx51_add_expense_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$data = array(
			'category'    => sanitize_text_field( $_POST['category'] ?? '' ),
			'description' => sanitize_text_field( $_POST['description'] ?? '' ),
			'amount'      => floatval( $_POST['amount'] ?? 0 ),
			'date'        => sanitize_text_field( $_POST['date'] ?? date('Y-m-d') ),
			'payee'       => sanitize_text_field( $_POST['payee'] ?? '' ),
			'added_by'    => get_current_user_id(),
			'receipt_url' => '',
			'status'      => 'pending_secretary',
			'account_type'=> sanitize_text_field( $_POST['account_type'] ?? 'bank' ),
		);
		
		// Validate required fields
		if ( empty($data['category']) || empty($data['description']) || $data['amount'] <= 0 ) {
			wp_send_json_error( array( 'message' => 'Please fill all required fields' ), 400 );
		}
		
		$table = 'expenses';
		
		// Handle Receipt Upload
		if ( ! empty( $_FILES['receipt_file'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}
		
		// Generate unique ID for the expense
		$data['id'] = uniqid('expense_');
		
		$result = $this->db->insert( $table, $data );
		
		if ( ! is_wp_error( $result ) ) {
            // Create a request for approval
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $req_id = $rm->create_request( 'expenses', 'add_expense', $data, $result );

            if ( ! is_wp_error( $req_id ) ) {
                $this->db->update( 'requests', array( 'status' => 'pending_secretary' ), array( 'id' => $req_id ) );
            }

			wp_send_json_success( array( 
				'message' => 'Expense added successfully and sent for approval',
				'id' => $result
			) );
		} else {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
	}
	
	/**
	 * AJAX Handler for Editing Expense
	 */
	public function handle_edit_expense_ajax() {
		check_ajax_referer( 'sgvx51_edit_expense_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = sanitize_text_field( $_POST['expense_id'] ?? '' );
		if ( empty($id) ) {
			wp_send_json_error( array( 'message' => 'Invalid expense ID' ), 400 );
		}
		
		$data = array(
			'category'    => sanitize_text_field( $_POST['category'] ?? '' ),
			'description' => sanitize_text_field( $_POST['description'] ?? '' ),
			'amount'      => floatval( $_POST['amount'] ?? 0 ),
			'date'        => sanitize_text_field( $_POST['date'] ?? date('Y-m-d') ),
			'payee'       => sanitize_text_field( $_POST['payee'] ?? '' ),
			'account_type'=> sanitize_text_field( $_POST['account_type'] ?? 'bank' ),
		);
		
		$table = 'expenses';
		
		// Handle Receipt Upload
		if ( ! empty( $_FILES['receipt_file'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}
		
		$result = $this->db->update( $table, $data, array( 'id' => $id ) );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		} else {
			wp_send_json_success( array( 'message' => 'Expense updated successfully' ) );
		}
	}
	
	/**
	 * AJAX Handler for Deleting Expense
	 */
	public function handle_delete_expense_ajax() {
		check_ajax_referer( 'sgvx51_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = sanitize_text_field( $_POST['id'] ?? '' );
		if ( empty($id) ) {
			wp_send_json_error( array( 'message' => 'Invalid expense ID' ), 400 );
		}
		
		$table = 'expenses';
		$result = $this->db->update( $table, array( 'status' => 'archived' ), array( 'id' => $id ) );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		} else {
			wp_send_json_success( array( 'message' => 'Expense deleted successfully' ) );
		}
	}
	
	/**
	 * AJAX Handler for Approving Expense
	 */
	public function handle_approve_expense_ajax() {
		check_ajax_referer( 'sgvx51_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = sanitize_text_field( $_POST['expense_id'] ?? '' );
		if ( empty($id) ) {
			wp_send_json_error( array( 'message' => 'Invalid expense ID' ), 400 );
		}
		
		$table = 'expenses';
		$result = $this->db->update( $table, array( 'status' => 'approved' ), array( 'id' => $id ) );
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		} else {
			wp_send_json_success( array( 'message' => 'Expense approved successfully' ) );
		}
	}
}
