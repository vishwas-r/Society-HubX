<?php
/**
 * Module: Account Manager
 * Handles Resident Invoices, Payments, and Maintenance Dues.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Account_Manager implements SNESTX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		
		// Handlers
		add_action( 'admin_post_SNESTX51_generate_invoices', array( $this, 'handle_generate_invoices' ) );
		add_action( 'admin_post_SNESTX51_record_payment', array( $this, 'handle_record_payment' ) );
		add_action( 'admin_post_SNESTX51_edit_invoice', array( $this, 'handle_edit_invoice' ) );
		add_action( 'admin_post_SNESTX51_delete_invoice', array( $this, 'handle_delete_invoice' ) );
		add_action( 'admin_post_SNESTX51_delete_payment', array( $this, 'handle_delete_payment' ) );
		add_action( 'admin_post_SNESTX51_print_receipt', array( $this, 'handle_print_receipt' ) );

		// AJAX for Residents
		add_action( 'wp_ajax_SNESTX51_submit_payment_request', array( $this, 'handle_submit_payment_request' ) );

		// Register Module
		add_filter( 'SNESTX51_get_module_accounts', array( $this, 'get_instance' ) );
		add_filter( 'SNESTX51_get_module_account', array( $this, 'get_instance' ) );
	}

	public function get_instance() {
		return $this;
	}

	public function get_module_slug() {
		return 'accounts';
	}

	/**
	 * Execute approved request from Request Manager.
	 */
	public function execute_request( $action, $payload ) {
		if ( $action === 'record_payment' ) {
			// Reuse the internal logic
			return $this->perform_record_payment( $payload );
		}
		return new WP_Error( 'invalid_action', 'Unknown action' );
	}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Society Accounts',
			'Accounts',
			'read', // Granular check inside render_page
			'snestx51-accounts',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render Admin View.
	 */
	public function render_page() {
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_view' ) ) {
            wp_die( 'You do not have permission to view accounts.' );
        }
		SNESTX51_Admin_App::render_view('accounts');
	}

	/**
	 * Generate Monthly Invoices (Bulk or Single)
	 */
	public function handle_generate_invoices() {
		if ( ! check_admin_referer( 'SNESTX51_account_action' ) ) wp_die( 'Security check failed' );
        
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) ) wp_die( 'Unauthorized' );

		$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : ''; // YYYY-MM
		$amount = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
		$due_date = isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '';
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'maintenance';

		if ( ! $month || ! $amount ) wp_die( 'Invalid Data' );

        // Check if job already running
        $job_key = "SNESTX51_job_bulk_invoice_{$month}_{$type}";
        $existing_job = get_option( $job_key );
        if ( $existing_job && $existing_job['status'] === 'running' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&error=job_running' ) );
            exit;
        }

        // Enterprise Upgrade: Background Processing
        if ( class_exists('SNESTX51_Background_Worker') ) {
            $worker = new SNESTX51_Background_Worker();
            if ( $worker->is_available() ) {
                $worker->schedule_bulk_invoices( $month, $amount, $type );
                wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=scheduled' ) );
                exit;
            }
        }
        
        // Fallback to synchronous if worker missing or unavailable
        $this->perform_bulk_invoice_generation( $month, $amount, $type, $due_date, $description );
        wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=generated' ) );
		exit;
	}

    /**
     * Logic for bulk generation, now extractable for background processing.
     */
    public function perform_bulk_invoice_generation( $month, $amount, $type, $due_date = '', $description = '' ) {
        $residents = $this->db->get( 'residents' );
        $invoices = $this->db->get( 'invoices', array( 'where' => array( 'month' => $month, 'type' => $type ) ) );
        
        $generated_count = 0;
        $prefix = str_replace( '-', '', $month ); // 2024-05 -> 202405
        
        // Generate Safe ID limits
        $all_invoices = $this->db->get( 'invoices' ); 
        $max_seq = 0;
        foreach ( $all_invoices as $inv ) {
            if ( strpos( $inv['id'], $prefix ) === 0 ) {
                $seq = intval( substr( $inv['id'], 6 ) );
                if ( $seq > $max_seq ) $max_seq = $seq;
            }
        }
        $next_seq = $max_seq + 1;

        foreach ( $residents as $r ) {
            // Check if already generated for this month and type
            $exists = false;
            foreach ( $invoices as $inv ) {
                if ( (string)$inv['flat_no'] === (string)$r['flat_no'] && (string)($inv['block'] ?? '') === (string)($r['block'] ?? '') ) {
                    $exists = true; break;
                }
            }
            
            if ( ! $exists ) {
                $new_id = $prefix . str_pad( $next_seq, 3, '0', STR_PAD_LEFT );
                $data = array(
                    'id'            => $new_id,
                    'block'         => $r['block'] ?? '',
                    'flat_no'       => $r['flat_no'],
                    'resident_name' => $r['name'] ?? '',
                    'month'         => $month,
                    'amount'        => $amount,
                    'total_paid'    => 0,
                    'status'        => 'unpaid',
                    'type'          => $type,
                    'due_date'      => $due_date,
                    'description'   => $description ? $description : ucfirst( $type ) . ' for ' . $month,
                    'created_at'    => current_time( 'mysql' ),
                    'payments'      => '[]'
                );
                
                $this->db->insert( 'invoices', $data );
                $generated_count++;
                $next_seq++;

                // Sync: Immediately update pending payment status
                $my_invoices = $this->db->get('invoices', array('where'=>array('flat_no' => $r['flat_no'])));
                // We update total dues on the resident dashboard calculation directly
            }
        }
        return $generated_count;
    }

	/**
	 * Record a Payment manually.
	 */
	public function handle_record_payment() {
		if ( ! check_admin_referer( 'SNESTX51_account_action' ) ) wp_die( 'Security check failed' );
		
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) ) wp_die( 'Unauthorized' );

        $invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';
        
        // 1. Synchronize with Request Manager if a pending request exists
        if ( ! empty( $invoice_id ) && $invoice_id !== 'Total Outstanding' ) {
            require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SNESTX51_Request_Manager();
            $sync_res = $rm->approve_request( $invoice_id );
            
            if ( ! is_wp_error( $sync_res ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=payment_recorded' ) );
                exit;
            }
        }

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_POST is unslashed and sanitized recursively.
		$res = $this->perform_record_payment( map_deep( wp_unslash( $_POST ), 'sanitize_text_field' ) );
		
		if ( is_wp_error( $res ) ) {
			wp_die( esc_html( $res->get_error_message() ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=payment_recorded' ) );
		exit;
	}

	/**
	 * Internal logic to record a payment.
	 * Can be called by admin_post or by execute_request (approval).
	 */
	public function perform_record_payment( $data ) {
		$invoice_id = sanitize_text_field( $data['invoice_id'] ?? '' );
		$amount_remaining = floatval( $data['amount'] ?? 0 );
		$method = sanitize_text_field( $data['method'] ?? 'UPI' );
		$ref = sanitize_text_field( $data['reference'] ?? '-' );
		$date = sanitize_text_field( $data['date'] ?? gmdate('Y-m-d') );
        $flat_no = sanitize_text_field( $data['flat_no'] ?? '' );
        $block = sanitize_text_field( $data['block'] ?? '' );
        $request_id = sanitize_text_field( $data['request_id'] ?? '' );
        
        // Debug Log
        error_log("SNESTX51 Payment: Processing Payment for Flat: $block-$flat_no, Amount: $amount_remaining, Inv: $invoice_id"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

		$affected_invoices = [];

        // CASE 1: Specific Invoice Payment
		if ( $invoice_id && $invoice_id !== 'Total Outstanding' ) {
            $invoices = $this->db->get( 'invoices', array( 'where' => array( 'id' => $invoice_id ), 'load_relations' => true ) );
            if ( ! empty( $invoices ) ) {
                $inv = $invoices[0];
                $flat_no = $inv['flat_no'];
                $block = $inv['block'] ?? '';
                $this->apply_payment_to_invoice_record( $inv, $amount_remaining, $date, $method, $ref, $request_id );
                $affected_invoices[] = $inv;
            }
        } 
        // CASE 2: General Payment (Total Outstanding) - FIFO Logic
        elseif ( $flat_no ) {
            // New Scalable Fetch: Only unpaid invoices for THIS flat
            $where = array( 'flat_no' => $flat_no );
            if ( ! empty( $block ) ) $where['block'] = $block;

            $my_invoices = $this->db->get( 'invoices', array( 
                'where'   => $where,
                'orderby' => 'month',
                'order'   => 'ASC',
                'load_relations' => true
            ));
            
            // Filter out already PAID invoices in PHP (since DB Router currently only does equality)
            $unpaid_invoices = array_filter( $my_invoices, function($inv) {
                return strtolower(trim($inv['status'] ?? '')) !== 'paid';
            });

            error_log("SNESTX51 Payment: Found " . count($unpaid_invoices) . " unpaid invoices for FIFO."); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

            foreach ( $unpaid_invoices as $inv ) {
                if ( $amount_remaining <= 0 ) break;

                // Calculate remaining balance for this invoice (Relational)
                $already_paid = 0;
                if ( ! empty( $inv['payments'] ) && is_array( $inv['payments'] ) ) {
                    foreach ( $inv['payments'] as $p ) $already_paid += (float)($p['amount'] ?? 0);
                }
                $inv_balance = (float)($inv['amount'] ?? 0) - $already_paid;

                if ( $inv_balance <= 0.01 ) { 
                    continue; 
                }

                $payment_towards_this_inv = min( $amount_remaining, $inv_balance );
                
                if($payment_towards_this_inv > 0) {
                         $this->apply_payment_to_invoice_record( $inv, $payment_towards_this_inv, $date, $method, $ref, $request_id );
                         $affected_invoices[] = $inv;
                     $amount_remaining -= $payment_towards_this_inv;
                     
                     error_log("SNESTX51 Payment: Applied $payment_towards_this_inv to Invoice " . $inv['id']); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
                }
            }
        }

        // Save all affected invoices (Status + Aggregate Data updates)
        error_log("SNESTX51 Payment: Saving status for " . count($affected_invoices) . " affected invoices."); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
        foreach ( $affected_invoices as $inv ) {
            $this->db->update( 'invoices', array( 
                'status'     => $inv['status'],
                'total_paid' => $inv['total_paid'] ?? 0,
                'payments'   => $inv['payments'] ?? array()
            ), array( 'id' => $inv['id'] ) );
        }

		// 2. Update Resident's Maintenance Balance
		if ( $flat_no ) {
            $amount_total = floatval( $data['amount'] ?? 0 );
            $residents = $this->db->get( 'residents' );
            foreach ( $residents as $j => $res ) {
                $r_flat = (string)($res['flat_no'] ?? '');
                $r_block = (string)($res['block'] ?? '');

                if ( $r_flat === (string)$flat_no && ( empty($block) || $r_block === (string)$block ) ) {
                    $current_balance = floatval( $res['maintenance_balance'] ?? 0 );
                    $new_balance = max( 0, $current_balance - $amount_total );
                    $this->db->update( 'residents', ['maintenance_balance' => $new_balance], ['id' => $res['id']] );
                    
                    // 3. Financial Ledger Integration
                    // We don't have a direct 'ledger' table, ledger is a virtual view of Invoices (Payments) and Expenses.
                    // However, we can ensure the payment is recorded in the 'payments' table (already done in apply_payment_to_invoice_record)
                    // and that it has all necessary metadata for the Ledger Manager to pick it up.
                    break;
                }
            }
        }
			
        return true;
	}

    /**
     * Helper to append a payment record to an invoice array.
     */
    private function apply_payment_to_invoice_record( &$invoice, $amount, $date, $method, $ref, $request_id = '' ) {
        $txn_id = uniqid('txn_');
        
        $payment_data = array(
            'id'          => $txn_id,
            'invoice_id'  => $invoice['id'],
            'amount'      => $amount,
            'date'        => $date,
            'method'      => $method,
            'reference'   => $ref,
            'recorded_by' => get_current_user_id(),
            'request_id'  => $request_id,
            'metadata'    => json_encode( array( 'account_type' => ( strtolower($method) === 'cash' ) ? 'cash' : 'bank' ) )
        );

        $this->db->insert( 'payments', $payment_data );

        // Update the invoice object in memory (Aggregate Data)
        $invoice['total_paid'] = (float)($invoice['total_paid'] ?? 0) + $amount;
        $payments_json = is_array($invoice['payments'] ?? null) ? $invoice['payments'] : (json_decode($invoice['payments'] ?? '[]', true) ?: array());
        
        $payments_json[] = array(
            'id'         => $txn_id,
            'request_id' => $request_id,
            'amount'     => $amount,
            'date'       => $date,
            'method'     => $method,
            'reference'  => $ref
        );
        $invoice['payments'] = $payments_json;
        
        if ( $invoice['total_paid'] >= floatval( $invoice['amount'] ) ) {
            $invoice['status'] = 'paid';
        } elseif ( $invoice['total_paid'] > 0 ) {
            $invoice['status'] = 'partial';
        } else {
            $invoice['status'] = 'unpaid';
        }
    }

	/**
	 * Handle Payment Request from Resident (Frontend)
	 */
	public function handle_submit_payment_request() {
		check_ajax_referer( 'SNESTX51_frontend_nonce' );
		
		$invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';
		$amount = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
		$method = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '';
		$ref = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		
		if ( ! $amount ) {
			wp_send_json_error( ['message' => 'Please fill in the amount paid.'] );
		}

		// Fetch Resident Info for better logging/display
		$user_id = get_current_user_id();
		$residents = $this->db->get('residents');
		$resident = null;
		foreach($residents as $r) {
			if(isset($r['wp_user_id']) && (int)$r['wp_user_id'] === $user_id) { 
				$resident = $r; break; 
			}
		}

		$payload = [
			'invoice_id' => $invoice_id,
			'amount'     => $amount,
			'method'     => $method,
			'reference'  => $ref,
			'date'       => $date,
			'resident_id'=> $user_id,
			'block'      => $resident ? ($resident['block'] ?? '') : '',
			'flat_no'    => $resident ? $resident['flat_no'] : 'Unknown',
			'resident_name' => $resident ? $resident['name'] : 'Unknown'
		];

		$rm = new SNESTX51_Request_Manager();
		$res = $rm->create_request( 'accounts', 'record_payment', $payload, $invoice_id );

        if ( ! is_wp_error( $res ) ) {
            // Update the request status to 'pending_secretary' explicitly
            // because create_request defaults to 'pending'
            $this->db->update( 'requests', array( 'status' => 'pending_secretary' ), array( 'id' => $res ) );
        }

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( ['message' => $res->get_error_message()] );
		}

		wp_send_json_success( ['message' => 'Payment confirmation sent to admin for verification.'] );
	}



	public function handle_delete_payment() {
		if ( ! check_admin_referer( 'SNESTX51_account_action' ) ) wp_die( 'Security check failed' );

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) ) wp_die( 'Unauthorized' );

		$invoice_id = isset( $_GET['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice_id'] ) ) : '';
		$txn_id = isset( $_GET['txn_id'] ) ? sanitize_text_field( wp_unslash( $_GET['txn_id'] ) ) : '';
		
        // 1. Delete from payments table
        $this->db->delete( 'payments', array( 'id' => $txn_id ) );

        // 2. Fetch invoice and recalculate status
        $invoices = $this->db->get( 'invoices', array( 'where' => array( 'id' => $invoice_id ), 'load_relations' => true ) );
        if ( ! empty( $invoices ) ) {
            $inv = $invoices[0];
            $paid = 0;
            if ( ! empty( $inv['payments'] ) ) {
                foreach ( $inv['payments'] as $p ) $paid += floatval( $p['amount'] );
            }
            
            $status = ( $paid >= floatval( $inv['amount'] ) ) ? 'paid' : ( ( $paid > 0 ) ? 'partial' : 'unpaid' );
            $this->db->update( 'invoices', array( 'status' => $status ), array( 'id' => $invoice_id ) );
            
            wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=updated' ) );
        } else {
            wp_die( 'Invoice not found' );
        }
		exit;
	}

	public function handle_edit_invoice() {
		if ( ! check_admin_referer( 'SNESTX51_account_action' ) ) wp_die( 'Security check failed' );

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) ) wp_die( 'Unauthorized' );

		$id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';
		$data = array(
			'description' => isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '',
			'amount' => isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0,
			'due_date' => isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '',
		);

		$invoices = $this->db->get( 'invoices', array( 'where' => array( 'id' => $id ), 'load_relations' => true ) );

		if ( ! empty( $invoices ) ) {
            $inv = $invoices[0];
			$paid = 0;
			if ( ! empty( $inv['payments'] ) ) {
				foreach ( $inv['payments'] as $p ) $paid += floatval( $p['amount'] );
			}
			
			$status = ( $paid >= $data['amount'] ) ? 'paid' : ( ( $paid > 0 ) ? 'partial' : 'unpaid' );
            
            $update_data = $data;
            $update_data['status'] = $status;
            
			$this->db->update( 'invoices', $update_data, array( 'id' => $id ) );
			
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=updated' ) );
		} else {
			wp_die( 'Invoice not found' );
		}
		exit;
	}

	public function handle_delete_invoice() {
		if ( ! check_admin_referer( 'SNESTX51_delete_invoice_nonce' ) ) wp_die( 'Security check failed' );

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) ) wp_die( 'Unauthorized' );

		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$this->db->delete( 'invoices', array( 'id' => $id ) );

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&success=deleted' ) );
		exit;
	}

	public function handle_print_receipt() {
		if ( ! check_admin_referer( 'SNESTX51_print_receipt_nonce' ) ) wp_die( 'Security check failed' );

		$invoice_id = isset( $_GET['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice_id'] ) ) : '';
		$invoices = $this->db->get( 'invoices' );
		$inv = null;

		foreach ( $invoices as $i ) {
			if ( $i['id'] === $invoice_id ) {
				$inv = $i;
				break;
			}
		}

		if ( ! $inv ) wp_die( 'Invoice not found' );

		// Load Template directly
		include SNESTX51_PLUGIN_DIR . 'templates/print-receipt.php';
		exit;
	}
}
