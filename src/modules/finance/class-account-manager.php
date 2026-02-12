<?php
/**
 * Module: Account Manager
 * Handles Resident Invoices, Payments, and Maintenance Dues.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Account_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		
		// Handlers
		add_action( 'admin_post_sgvx51_generate_invoices', array( $this, 'handle_generate_invoices' ) );
		add_action( 'admin_post_sgvx51_record_payment', array( $this, 'handle_record_payment' ) );
		add_action( 'admin_post_sgvx51_edit_invoice', array( $this, 'handle_edit_invoice' ) );
		add_action( 'admin_post_sgvx51_delete_invoice', array( $this, 'handle_delete_invoice' ) );
		add_action( 'admin_post_sgvx51_delete_payment', array( $this, 'handle_delete_payment' ) );
		add_action( 'admin_post_sgvx51_print_receipt', array( $this, 'handle_print_receipt' ) );

		// AJAX for Residents
		add_action( 'wp_ajax_sgvx51_submit_payment_request', array( $this, 'handle_submit_payment_request' ) );

		// Register Module
		add_filter( 'sgvx51_get_module_accounts', array( $this, 'get_instance' ) );
		add_filter( 'sgvx51_get_module_account', array( $this, 'get_instance' ) );
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
			'sgvx51-settings',
			'Society Accounts',
			'Accounts',
			'manage_options',
			'sgvx51-accounts',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render Admin View.
	 */
	public function render_page() {
		SGVX51_Admin_App::render_view('accounts');
	}

	/**
	 * Generate Monthly Invoices (Bulk or Single)
	 */
	public function handle_generate_invoices() {
		if ( ! check_admin_referer( 'sgvx51_account_action' ) ) wp_die( 'Security check failed' );

		$month = sanitize_text_field( $_POST['month'] ); // YYYY-MM
		$amount = floatval( $_POST['amount'] );
		$due_date = sanitize_text_field( $_POST['due_date'] );
		$description = sanitize_text_field( $_POST['description'] );
        $type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : 'maintenance';

		// If Maintenance, update the default amount setting if requested or just silently (let's just update it if it's the standard maintenance)
        if ( $type === 'maintenance' && $amount > 0 ) {
            update_option( 'sgvx51_maintenance_amount', $amount );
        }

		if ( ! $month || ! $amount ) wp_die( 'Invalid Data' );

		$residents = $this->db->get( 'residents' ); // Only active residents?
		$invoices = $this->db->get( 'invoices' );
		
		$generated_count = 0;
        
        // Calculate Next Sequence for YYYYMM
        $prefix = str_replace('-', '', $month); // 2024-05 -> 202405
        $max_seq = 0;
        foreach($invoices as $inv) {
            // Check if ID starts with prefix
            if(strpos($inv['id'], $prefix) === 0) {
                $seq = intval(substr($inv['id'], 6));
                if($seq > $max_seq) $max_seq = $seq;
            }
        }
        $next_seq = $max_seq + 1;

		foreach ( $residents as $r ) {
			// Skip if invoice already exists for this Flat + Month + Type
			$exists = false;
			foreach($invoices as $inv) {
				if( $inv['flat_no'] === $r['flat_no'] && $inv['month'] === $month && $inv['type'] === $type && $inv['description'] === $description ) {
					$exists = true; break;
				}
			}
			
			if ( ! $exists ) {
                $new_id = $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);
                
				$invoices[] = array(
					'id'            => $new_id,
					'flat_no'       => $r['flat_no'],
					'resident_name' => $r['name'],
					'month'         => $month,
					'amount'        => $amount,
					'due_date'      => $due_date,
					'status'        => 'unpaid', // unpaid, paid, partial
					'type'          => $type,
					'description'   => $description,
					'created_at'    => current_time('mysql'),
					'payments'      => []
				);
				
				// Encode payments for DB
				$save_data = $invoices[count($invoices)-1]; // Get the one we just made
				$save_data['payments'] = json_encode($save_data['payments']);
				
				$this->db->insert('invoices', $save_data);

				$generated_count++;
                $next_seq++;
			}
		}

		// file_put_contents removed
		
		wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=generated&count=' . $generated_count ) );
		exit;
	}

	/**
	 * Record a Payment manually.
	 */
	public function handle_record_payment() {
		if ( ! check_admin_referer( 'sgvx51_account_action' ) ) wp_die( 'Security check failed' );
		
        $invoice_id = sanitize_text_field( $_POST['invoice_id'] ?? '' );
        
        // 1. Synchronize with Request Manager if a pending request exists
        if ( ! empty( $invoice_id ) && $invoice_id !== 'Total Outstanding' ) {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $sync_res = $rm->approve_request( $invoice_id );
            
            if ( ! is_wp_error( $sync_res ) ) {
                wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=payment_recorded' ) );
                exit;
            }
        }

		$res = $this->perform_record_payment( $_POST );
		
		if ( is_wp_error( $res ) ) {
			wp_die( $res->get_error_message() );
		}

		wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=payment_recorded' ) );
		exit;
	}

	/**
	 * Internal logic to record a payment.
	 * Can be called by admin_post or by execute_request (approval).
	 */
	private function perform_record_payment( $data ) {
		$invoice_id = sanitize_text_field( $data['invoice_id'] ?? '' );
		$amount_remaining = floatval( $data['amount'] ?? 0 );
		$method = sanitize_text_field( $data['method'] ?? 'UPI' );
		$ref = sanitize_text_field( $data['reference'] ?? '-' );
		$date = sanitize_text_field( $data['date'] ?? date('Y-m-d') );
        $flat_no = sanitize_text_field( $data['flat_no'] ?? '' );
        
        // Debug Log
        error_log("SGVX51 Payment: Processing Payment for Flat: $flat_no, Amount: $amount_remaining, Inv: $invoice_id");

		$affected_invoices = [];

        // CASE 1: Specific Invoice Payment
		if ( $invoice_id && $invoice_id !== 'Total Outstanding' ) {
            $invoices = $this->db->get( 'invoices', array( 'where' => array( 'id' => $invoice_id ) ) );
            if ( ! empty( $invoices ) ) {
                $inv = $invoices[0];
                $flat_no = $inv['flat_no'];
                $this->apply_payment_to_invoice_record( $inv, $amount_remaining, $date, $method, $ref );
                $affected_invoices[] = $inv;
            }
        } 
        // CASE 2: General Payment (Total Outstanding) - FIFO Logic
        elseif ( $flat_no ) {
            // New Scalable Fetch: Only unpaid invoices for THIS flat
            $my_invoices = $this->db->get( 'invoices', array( 
                'where'   => array( 'flat_no' => $flat_no ),
                'orderby' => 'month',
                'order'   => 'ASC'
            ));
            
            // Filter out already PAID invoices in PHP (since DB Router currently only does equality)
            $unpaid_invoices = array_filter( $my_invoices, function($inv) {
                return strtolower(trim($inv['status'] ?? '')) !== 'paid';
            });

            error_log("SGVX51 Payment: Found " . count($unpaid_invoices) . " unpaid invoices for FIFO.");

            foreach ( $unpaid_invoices as $inv ) {
                if ( $amount_remaining <= 0 ) break;

                // Calculate remaining balance for this invoice
                $already_paid = 0;
                $payments = isset($inv['payments']) ? (is_string($inv['payments']) ? json_decode($inv['payments'], true) : $inv['payments']) : array();
                if ( is_array($payments) ) {
                    foreach ( $payments as $p ) $already_paid += (float)($p['amount'] ?? 0);
                }
                $inv_balance = (float)($inv['amount'] ?? 0) - $already_paid;

                if ( $inv_balance <= 0.01 ) { 
                    continue; 
                }

                $payment_towards_this_inv = min( $amount_remaining, $inv_balance );
                
                if($payment_towards_this_inv > 0) {
                     $this->apply_payment_to_invoice_record( $inv, $payment_towards_this_inv, $date, $method, $ref );
                     $affected_invoices[] = $inv;
                     $amount_remaining -= $payment_towards_this_inv;
                     
                     error_log("SGVX51 Payment: Applied $payment_towards_this_inv to Invoice " . $inv['id']);
                }
            }
        }

        // Save all affected invoices
        error_log("SGVX51 Payment: Saving " . count($affected_invoices) . " affected invoices.");
        foreach ( $affected_invoices as $inv ) {
            $save_data = $inv;
            if ( is_array( $save_data['payments'] ) ) {
                $save_data['payments'] = json_encode( $save_data['payments'] );
            }
            $this->db->update( 'invoices', $save_data, ['id' => $inv['id']] );
        }

		// 2. Update Resident's Maintenance Balance
		if ( $flat_no ) {
            $amount_total = floatval( $data['amount'] ?? 0 );
            $residents = $this->db->get( 'residents' );
            foreach ( $residents as $j => $res ) {
                if ( (string)($res['flat_no'] ?? $res['id']) === (string)$flat_no ) {
                    $current_balance = floatval( $res['maintenance_balance'] ?? 0 );
                    $new_balance = max( 0, $current_balance - $amount_total );
                    $this->db->update( 'residents', ['maintenance_balance' => $new_balance], ['id' => $res['id']] );
                    break;
                }
            }
        }
			
        return true;
	}

    /**
     * Helper to append a payment record to an invoice array.
     */
    private function apply_payment_to_invoice_record( &$invoice, $amount, $date, $method, $ref ) {
        if ( ! isset( $invoice['payments'] ) ) {
            $invoice['payments'] = [];
        } elseif ( is_string( $invoice['payments'] ) ) {
            $invoice['payments'] = json_decode( $invoice['payments'], true ) ?: [];
        }

        $invoice['payments'][] = [
            'id' => uniqid('txn_'),
            'amount' => $amount,
            'date' => $date,
            'method' => $method,
            'reference' => $ref,
            'recorded_by' => get_current_user_id(),
            'account_type' => (strtolower($method) === 'cash') ? 'cash' : 'bank'
        ];

        // Recalculate Status
        $paid = 0;
        foreach( $invoice['payments'] as $p ) {
            $paid += floatval( $p['amount'] ?? 0 );
        }
        
        if ( $paid >= floatval( $invoice['amount'] ) ) {
            $invoice['status'] = 'paid';
        } elseif ( $paid > 0 ) {
            $invoice['status'] = 'partial';
        }
    }

	/**
	 * Handle Payment Request from Resident (Frontend)
	 */
	public function handle_submit_payment_request() {
		check_ajax_referer( 'sgvx51_frontend_nonce' );
		
		$invoice_id = sanitize_text_field( $_POST['invoice_id'] );
		$amount = floatval( $_POST['amount'] );
		$method = sanitize_text_field( $_POST['method'] );
		$ref = sanitize_text_field( $_POST['reference'] );
		$date = sanitize_text_field( $_POST['date'] );
		
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
			'flat_no'    => $resident ? $resident['flat_no'] : 'Unknown',
			'resident_name' => $resident ? $resident['name'] : 'Unknown'
		];

		$rm = new SGVX51_Request_Manager();
		$res = $rm->create_request( 'accounts', 'record_payment', $payload, $invoice_id );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( ['message' => $res->get_error_message()] );
		}

		wp_send_json_success( ['message' => 'Payment confirmation sent to admin for verification.'] );
	}



	public function handle_delete_payment() {
		if ( ! check_admin_referer( 'sgvx51_account_action' ) ) wp_die( 'Security check failed' );

		$invoice_id = sanitize_text_field( $_GET['invoice_id'] );
		$txn_id = sanitize_text_field( $_GET['txn_id'] );
		
		$invoices = $this->db->get( 'invoices' );
		$target_index = -1;
		
		foreach($invoices as $i => $inv) {
			if($inv['id'] === $invoice_id) {
				$target_index = $i;
				break;
			}
		}

		if ( $target_index > -1 ) {
			$payments = isset($invoices[$target_index]['payments']) ? $invoices[$target_index]['payments'] : [];
			if ( is_string($payments) ) $payments = json_decode($payments, true);
			if ( !is_array($payments) ) $payments = [];
			
			// Filter out the payment
			$new_payments = [];
			foreach($payments as $p) {
				if( ($p['id'] ?? '') !== $txn_id ) {
					$new_payments[] = $p;
				}
			}
			
			// Recalc Status
			$paid = 0;
			foreach($new_payments as $p) $paid += floatval($p['amount']);
			
			$invoices[$target_index]['status'] = ($paid >= floatval($invoices[$target_index]['amount'])) ? 'paid' : (($paid > 0) ? 'partial' : 'unpaid');
			
			$save_data = $invoices[$target_index];
			$save_data['payments'] = json_encode($new_payments);
			
			$this->db->update('invoices', $save_data, ['id' => $invoice_id]);
			
			wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=updated' ) );
		} else {
			wp_die('Invoice not found');
		}
		exit;
	}

	public function handle_edit_invoice() {
		if ( ! check_admin_referer( 'sgvx51_account_action' ) ) wp_die( 'Security check failed' );

		$id = sanitize_text_field( $_POST['invoice_id'] );
		$data = array(
			'description' => sanitize_text_field( $_POST['description'] ),
			'amount'      => floatval( $_POST['amount'] ),
			'due_date'    => sanitize_text_field( $_POST['due_date'] ),
		);

		// We need to fetch the existing invoice to preserve other fields?
		// db->update merges? No, db->update usually needs full row or updates specific fields.
		// WPDB update updates only specified fields. SGVX51_DB_Router update implementation?
		// Looking at DB Router usage in other files, it seems to take $data and $where.
		// However, the DB Router works with JSON files in this plugin?
		// Wait, SGVX51_DB_Router::update($table, $data, $where)
		// If it's a JSON file based "DB" (which `get` and `insert` usages suggest simple array manipulation), 
		// `update` likely finds the row by $where and merges $data.
		// Let's assume standard behavior: Update specific fields.
		
		// ACTUALLY, checking previous code:
		// $this->db->update( 'vehicles', $data, array( 'id' => $id ) );
		// It seems to work like WPDB.
		
		// One catch: Invoices are in a single `invoices` array in JSON? 
		// If so, I need to be careful not to overwrite the whole record if DB Router replaces it.
		// Let's look at `handle_record_payment` implementation I just viewed.
		// It fetches ALL invoices, loops, updates specific one, then calls `$this->db->update('invoices', $update_data, ['id' => ...])`
		// Wait, `handle_record_payment` does: $this->db->update('invoices', $update_data, ['id' => $invoice_id]);
		// And $update_data is the FULL modified array.
		// So `db->update` might be replacing the entire row if it finds it?
		// OR `db->update` logic in this specific plugin might be "Update row where ID matches with these fields".
		
		// To be safe, I will fetch, modify, and update like `handle_record_payment` does.
		
		$invoices = $this->db->get( 'invoices' );
		$target_index = -1;
		foreach($invoices as $i => $inv) {
			if($inv['id'] === $id) {
				$target_index = $i;
				break;
			}
		}

		if($target_index > -1) {
			$invoices[$target_index]['description'] = $data['description'];
			$invoices[$target_index]['amount'] = $data['amount'];
			$invoices[$target_index]['due_date'] = $data['due_date'];
			
			// Recalculate status if amount changed?
			$paid = 0;
			$payments = isset($invoices[$target_index]['payments']) ? $invoices[$target_index]['payments'] : [];
			// Payments might be JSON string if fetched directly from file but `db->get` usually decodes?
			// `handle_record_payment` does `$inv['payments'] = json_encode(...)` before saving.
			// `db->get` returns array.
			// Let's trust `db->get` returns array of arrays.
			// If `payments` is a string in the array, we might need to decode.
			// Checking `handle_record_payment`: `$invoices[$i]['payments'][] = ...` implies it acts as array.
			
			if( is_string($payments) ) $payments = json_decode($payments, true);
			if( !is_array($payments) ) $payments = [];

			foreach($payments as $p) $paid += floatval($p['amount']);
			
			if ( $paid >= $data['amount'] ) {
				$invoices[$target_index]['status'] = 'paid';
			} elseif ( $paid > 0 ) {
				$invoices[$target_index]['status'] = 'partial';
			} else {
				$invoices[$target_index]['status'] = 'unpaid';
			}

            // DB update expects the full row? Or partial?
            // `handle_record_payment` passes the FULL row `$update_data`.
            // So I should pass the full row.
            
            // Re-encode payments if needed?
            // `handle_record_payment` does: `$update_data['payments'] = json_encode($update_data['payments']);`
            // So I must do the same.
            
            $save_data = $invoices[$target_index];
            $save_data['payments'] = json_encode($payments);
            
			$this->db->update('invoices', $save_data, ['id' => $id]);
			
			wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=updated' ) );
		} else {
			wp_die('Invoice not found');
		}
		exit;
	}

	public function handle_delete_invoice() {
		if ( ! check_admin_referer( 'sgvx51_delete_invoice_nonce' ) ) wp_die( 'Security check failed' );

		$id = sanitize_text_field( $_GET['id'] );
		$this->db->delete( 'invoices', array( 'id' => $id ) );

		wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&success=deleted' ) );
		exit;
	}

	public function handle_print_receipt() {
		if ( ! check_admin_referer( 'sgvx51_print_receipt_nonce' ) ) wp_die( 'Security check failed' );

		$invoice_id = sanitize_text_field( $_GET['invoice_id'] );
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
		include SGVX51_PLUGIN_DIR . 'templates/print-receipt.php';
		exit;
	}
}
