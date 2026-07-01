<?php
/**
 * Module: Expense Manager
 * Handles Expenses and Receipts.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Expense_Manager {

	private $db;
	private $drive;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		$this->drive = new SNESTX51_Drive_Manager();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_SNESTX51_add_expense', array( $this, 'handle_add_expense' ) );
		add_action( 'admin_post_SNESTX51_edit_expense', array( $this, 'handle_edit_expense' ) );
		add_action( 'admin_post_SNESTX51_delete_expense', array( $this, 'handle_delete_expense' ) );
		add_action( 'admin_post_SNESTX51_approve_expense', array( $this, 'handle_approve_expense' ) );
		
		// AJAX Handlers
		add_action( 'wp_ajax_SNESTX51_add_expense', array( $this, 'handle_add_expense_ajax' ) );
		add_action( 'wp_ajax_SNESTX51_edit_expense', array( $this, 'handle_edit_expense_ajax' ) );
		add_action( 'wp_ajax_SNESTX51_delete_expense', array( $this, 'handle_delete_expense_ajax' ) );
		add_action( 'wp_ajax_SNESTX51_approve_expense', array( $this, 'handle_approve_expense_ajax' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Accounts & Expenses',
			'Expenses',
			'manage_options',
			'snestx51-expenses',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_expense() {
		if ( ! check_admin_referer( 'SNESTX51_add_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$data = array(
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
			'amount' => isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0,
			'date' => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'payee' => isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '',
			'added_by'    => get_current_user_id(),
			'receipt_url' => '',
			'status'      => 'pending_secretary',
            'account_type' => isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : '',
		);

		$year = gmdate( 'Y', strtotime( $data['date'] ) );
		$table = 'expenses';

		if ( isset( $_FILES['receipt_file']['size'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_to_folder.
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

		$result = $this->db->insert( $table, $data );

		if ( ! is_wp_error( $result ) ) {
            // Create a request for approval
            require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SNESTX51_Request_Manager();
            $req_id = $rm->create_request( 'expenses', 'add_expense', $data, $result );
            
            if ( ! is_wp_error( $req_id ) ) {
                $this->db->update( 'requests', array( 'status' => 'pending_secretary' ), array( 'id' => $req_id ) );
            }
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&year=' . $year . '&success=1' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		}
		exit;
	}

	public function handle_edit_expense() {
		if ( ! check_admin_referer( 'SNESTX51_edit_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = isset( $_POST['expense_id'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_id'] ) ) : '';
		$data = array(
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
			'amount' => isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0,
			'date' => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
			'payee' => isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '',
            'account_type' => isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : '',
		);

		$year = gmdate( 'Y', strtotime( $data['date'] ) );
		$table = 'expenses';

		if ( isset( $_FILES['receipt_file']['size'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_to_folder.
				$url = $this->drive->upload_to_folder( $folder, $_FILES['receipt_file'] );
				if ( ! is_wp_error( $url ) ) {
					$data['receipt_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

		$result = $this->db->update( $table, $data, array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&year=' . $year . '&success=1' ) );
		}
		exit;
	}

	public function handle_delete_expense() {
		if ( ! check_admin_referer( 'SNESTX51_delete_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
		
		if ( empty($date) ) {
			wp_die('Expense date is required for deletion.');
		}

		$year = gmdate( 'Y', strtotime( $date ) );
		$table = 'expenses';

		$this->db->delete( $table, array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&year=' . $year . '&deleted=1' ) );
		exit;
	}

	public function handle_approve_expense() {
		if ( ! check_admin_referer( 'SNESTX51_approve_expense_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_POST['expense_id'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_id'] ) ) : '';
		$date = isset( $_POST['expense_date'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_date'] ) ) : '';

		$year = gmdate( 'Y', strtotime( $date ) );

        // Direct approval is now replaced by multi-stage workflow if requested via Admin UI
        require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SNESTX51_Request_Manager();
        $result = $rm->approve_request( $id );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&error=' . urlencode( $result->get_error_message() ) ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-expenses&year=' . $year . '&approved=1' ) );
		}
		exit;
	}

	public function render_page() {
		SNESTX51_Admin_App::render_view('expenses');
	}
	
	/**
	 * AJAX Handler for Adding Expense
	 */
	public function handle_add_expense_ajax() {
		check_ajax_referer( 'SNESTX51_add_expense_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$data = array(
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
			'amount'      => floatval( $_POST['amount'] ?? 0 ),
			'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : gmdate('Y-m-d'),
			'payee' => isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '',
			'added_by'    => get_current_user_id(),
			'receipt_url' => '',
			'status'      => 'pending_secretary',
			'account_type' => isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : '',
		);
		
		// Validate required fields
		if ( empty($data['category']) || empty($data['description']) || $data['amount'] <= 0 ) {
			wp_send_json_error( array( 'message' => 'Please fill all required fields' ), 400 );
		}
		
		$table = 'expenses';
		
		if ( isset( $_FILES['receipt_file']['size'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_to_folder.
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
            require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SNESTX51_Request_Manager();
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
		check_ajax_referer( 'SNESTX51_edit_expense_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = isset( $_POST['expense_id'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_id'] ) ) : '';
		if ( empty($id) ) {
			wp_send_json_error( array( 'message' => 'Invalid expense ID' ), 400 );
		}
		
		$data = array(
			'category' => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'description' => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
			'amount'      => floatval( $_POST['amount'] ?? 0 ),
			'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : gmdate('Y-m-d'),
			'payee' => isset( $_POST['payee'] ) ? sanitize_text_field( wp_unslash( $_POST['payee'] ) ) : '',
			'account_type' => isset( $_POST['account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['account_type'] ) ) : '',
		);
		
		$table = 'expenses';
		
		if ( isset( $_FILES['receipt_file']['size'] ) && $_FILES['receipt_file']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Receipts' );
			if ( ! is_wp_error( $folder ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_to_folder.
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
		check_ajax_referer( 'SNESTX51_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
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
		check_ajax_referer( 'SNESTX51_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = isset( $_POST['expense_id'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_id'] ) ) : '';
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
