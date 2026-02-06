<?php
/**
 * Module: Ledger Manager
 * Aggregates Financial Data (Invoices & Expenses) for Audit.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Ledger_Manager {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_reconcile_balance', array( $this, 'handle_reconcile_balance' ) );
	}

	    public function handle_reconcile_balance() {
        if ( ! check_admin_referer( 'sgvx51_reconcile_nonce' ) ) wp_die( 'Security check failed' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $year = sanitize_text_field( $_POST['year'] );
        $bank = floatval( $_POST['actual_bank'] );
        $cash = floatval( $_POST['actual_cash'] );
        $opening_bank = floatval( $_POST['opening_bank'] );
        $opening_cash = floatval( $_POST['opening_cash'] );

        update_option( 'sgvx51_actual_bank_' . $year, $bank );
        update_option( 'sgvx51_actual_cash_' . $year, $cash );
        update_option( 'sgvx51_opening_bank_' . $year, $opening_bank );
        update_option( 'sgvx51_opening_cash_' . $year, $opening_cash );

        wp_redirect( admin_url( 'admin.php?page=sgvx51-accounts&tab=ledger&year=' . $year . '&success=reconciled' ) );
        exit;
    }

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Audit Ledger',
			'Audit Ledger',
			'manage_options',
			'sgvx51-accounts&tab=ledger',
			'' // No title, just a link
		);
	}

	/**
	 * Get Unified Ledger Entries
	 * Returns sorted list of Income (Verified) and Expense (Verified).
	 */
	public function get_ledger_entries( $year ) {
		$entries = array();

		// 1. Get Expenses (Debit) - ONLY Approved
		$all_expenses = $this->db->get( 'expenses' );
		if ( ! empty( $all_expenses ) ) {
			foreach ( $all_expenses as $ex ) {
				// Filter by Year
				if ( date( 'Y', strtotime( $ex['date'] ) ) == $year ) {
					if ( isset( $ex['status'] ) && $ex['status'] === 'approved' ) {
						$entries[] = array(
							'date'        => $ex['date'],
							'type'        => 'Debit',
							'description' => $ex['description'] . ' (' . $ex['category'] . ')',
							'amount'      => floatval( $ex['amount'] ),
							'bank_balance'=> 0,
							'cash_balance'=> 0,
							'ref_id'      => 'EXP-' . substr($ex['id'], -4),
							'entity'      => $ex['payee'] ?? 'Unknown',
							'account_type'=> $ex['account_type'] ?? 'bank'
						);
					}
				}
			}
		}

		// 2. Get Invoices (Credit) - Actual Payments Received
		$invoices = $this->db->get( 'invoices' );
		if ( ! empty( $invoices ) ) {
			foreach ( $invoices as $inv ) {
				if ( ! empty( $inv['payments'] ) ) {
					// Payments might be JSON string from DB, parse if needed
					$payments_list = is_string( $inv['payments'] ) ? json_decode( $inv['payments'], true ) : $inv['payments'];
					if ( ! is_array( $payments_list ) ) $payments_list = array();
					foreach ( $payments_list as $pay ) {
						// Filter by Year of Payment
						if ( date( 'Y', strtotime( $pay['date'] ) ) == $year ) {
							$entries[] = array(
								'date'        => $pay['date'],
								'type'        => 'Credit',
								'description' => 'Payment for ' . $inv['description'] . ' (' . $inv['month'] . ')',
								'amount'      => floatval( $pay['amount'] ),
								'bank_balance' => 0,
								'cash_balance' => 0,
								'ref_id'      => isset($pay['id']) ? strtoupper($pay['id']) : 'PAY-' . $inv['flat_no'],
                                'entity'      => 'Flat ' . $inv['flat_no'],
                                'account_type'=> $pay['account_type'] ?? ( (strtolower($pay['method'] ?? '') === 'cash') ? 'cash' : 'bank' )
							);
						}
					}
				}
			}
		}

		// 3. Sort Chronologically
		usort( $entries, function( $a, $b ) {
			return strtotime( $a['date'] ) - strtotime( $b['date'] );
		});

		        // Initial Balances (Opening Balances)
        $opening_bank = floatval( get_option( 'sgvx51_opening_bank_' . $year, get_option( 'sgvx51_opening_bank', 0 ) ) );
        $opening_cash = floatval( get_option( 'sgvx51_opening_cash_' . $year, get_option( 'sgvx51_opening_cash', 0 ) ) );
        
        // Prepend Opening Balance Entry
        array_unshift($entries, array(
            'date'         => $year . '-01-01',
            'type'         => 'Opening',
            'description'  => 'Opening Balance for ' . $year,
            'amount'       => 0,
            'bank_balance' => $opening_bank,
            'cash_balance' => $opening_cash,
            'ref_id'       => 'START-' . $year,
            'entity'       => 'System'
        ));

		$bank_bal = $opening_bank; 
		$cash_bal = $opening_cash; 
        $first = true;
		foreach ( $entries as &$entry ) {
            if($first) { $first = false; continue; }
            
            $acc = $entry['account_type'] ?? 'bank';
			if ( $entry['type'] === 'Credit' ) {
				if($acc === 'cash') $cash_bal += $entry['amount']; else $bank_bal += $entry['amount'];
			} else {
				if($acc === 'cash') $cash_bal -= $entry['amount']; else $bank_bal -= $entry['amount'];
			}
			$entry['bank_balance'] = $bank_bal;
			$entry['cash_balance'] = $cash_bal;
		}

		return $entries;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('ledger');
	}

    /**
     * Get Current Overall Balance Breakdown
     */
    public function get_current_balance() {
        $year = date('Y');
        $entries = $this->get_ledger_entries($year);
        if(empty($entries)) return ['bank' => 0, 'cash' => 0, 'total' => 0];
        
        $last = end($entries);
        return [
            'bank'  => $last['bank_balance'],
            'cash'  => $last['cash_balance'],
            'total' => $last['bank_balance'] + $last['cash_balance']
        ];
    }

    /**
     * Get Monthly Payment Summary for Transparency
     */
    public function get_monthly_summary($month = null) {
        if(!$month) $month = date('Y-m');
        
        $flats = $this->db->get('flats');
        $invoices = $this->db->get('invoices');
        
        $summary = [];
        foreach($flats as $f) {
            $status = 'unpaid';
            $paid_amt = 0;
            $due_amt = 0;

            // Match invoice by flat ID (A-101, etc.)
            $flat_id = $f['id'] ?? ($f['flat_no'] ?? 'N/A');

            foreach($invoices as $inv) {
                if(isset($inv['flat_no']) && $inv['flat_no'] === $flat_id && isset($inv['month']) && $inv['month'] === $month && ($inv['type'] ?? '') === 'maintenance') {
                    $status = $inv['status'] ?? 'unpaid';
                    $due_amt = $inv['amount'] ?? 0;
                    if (!empty($inv['payments'])) {
                        // Payments might be JSON string from DB, parse if needed
                        $payments_list = is_string( $inv['payments'] ) ? json_decode( $inv['payments'], true ) : $inv['payments'];
                        if ( is_array( $payments_list ) ) {
                            foreach($payments_list as $p) $paid_amt += ($p['amount'] ?? 0);
                        }
                    }
                    break;
                }
            }

            $summary[] = [
                'flat_no'   => $flat_id,
                'resident'  => ($f['resident_name'] ?? '') ?: 'Vacant',
                'status'    => $status,
                'paid'      => $paid_amt,
                'due'       => $due_amt
            ];
        }

        // Sort by Flat No
        usort($summary, function($a, $b) { return strnatcmp($a['flat_no'], $b['flat_no']); });
        
        return $summary;
    }
}
