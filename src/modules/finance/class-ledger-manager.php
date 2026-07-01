<?php
/**
 * Module: Ledger Manager
 * Aggregates Financial Data (Invoices & Expenses) for Audit.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Ledger_Manager {

	private $db;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_SNESTX51_reconcile_balance', array( $this, 'handle_reconcile_balance' ) );
	}

	    public function handle_reconcile_balance() {
        if ( ! check_admin_referer( 'SNESTX51_reconcile_nonce' ) ) wp_die( 'Security check failed' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $year = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';
        $bank = isset( $_POST['actual_bank'] ) ? floatval( wp_unslash( $_POST['actual_bank'] ) ) : 0;
        $cash = isset( $_POST['actual_cash'] ) ? floatval( wp_unslash( $_POST['actual_cash'] ) ) : 0;
        $opening_bank = isset( $_POST['opening_bank'] ) ? floatval( wp_unslash( $_POST['opening_bank'] ) ) : 0;
        $opening_cash = isset( $_POST['opening_cash'] ) ? floatval( wp_unslash( $_POST['opening_cash'] ) ) : 0;

        update_option( 'SNESTX51_actual_bank_' . $year, $bank );
        update_option( 'SNESTX51_actual_cash_' . $year, $cash );
        update_option( 'SNESTX51_opening_bank_' . $year, $opening_bank );
        update_option( 'SNESTX51_opening_cash_' . $year, $opening_cash );

        wp_safe_redirect( admin_url( 'admin.php?page=snestx51-accounts&tab=ledger&year=' . $year . '&success=reconciled' ) );
        exit;
    }

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Audit Ledger',
			'Audit Ledger',
			'manage_options',
			'snestx51-accounts&tab=ledger',
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
				if ( gmdate( 'Y', strtotime( $ex['date'] ) ) == $year ) {
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
		$invoices = $this->db->get( 'invoices', array( 'load_relations' => true ) );
		if ( ! empty( $invoices ) ) {
			foreach ( $invoices as $inv ) {
				$entries_added = false;

				// Priority 1: Check `payments` relation (Partial payments or multiple entries)
				if ( ! empty( $inv['payments'] ) && is_array( $inv['payments'] ) ) {
					foreach ( $inv['payments'] as $pay ) {
						// De-duplicate: If this came from a Request, skip calculating here
						// We will count the request separately to keep the ledger consolidated.
						if ( ! empty( $pay['request_id'] ) ) continue;

						if ( gmdate( 'Y', strtotime( $pay['date'] ) ) == $year ) {
							$entries[] = array(
								'date'        => $pay['date'],
								'type'        => 'Credit',
								'description' => 'Payment for ' . $inv['description'] . ' (' . $inv['month'] . ')',
								'amount'      => floatval( $pay['amount'] ),
								'bank_balance' => 0,
								'cash_balance' => 0,
								'ref_id'      => isset($pay['id']) ? strtoupper($pay['id']) : 'PAY-' . ($inv['block'] ?? '') . '-' . $inv['flat_no'],
								'entity'      => 'Flat ' . ($inv['block'] ? $inv['block'] . '-' : '') . $inv['flat_no'],
								'account_type'=> $pay['account_type'] ?? ( (strtolower($pay['method'] ?? '') === 'cash') ? 'cash' : 'bank' )
							);
							$entries_added = true;
						}
					}
				}

				// Priority 2: Fallback to Main Table Columns (Imported Data or Simple Paid Status)
				// If NO entries were added from JSON, but status is PAID, use the main row data.
				if ( ! $entries_added && (strtolower($inv['status'] ?? '') === 'paid') ) {
					$pay_date = !empty($inv['payment_date']) && $inv['payment_date'] !== '0000-00-00 00:00:00' 
								? gmdate('Y-m-d', strtotime($inv['payment_date'])) 
								: ($inv['created_at'] ? gmdate('Y-m-d', strtotime($inv['created_at'])) : gmdate('Y-m-d'));
					
					// Filter by Year
					if ( gmdate( 'Y', strtotime( $pay_date ) ) == $year ) {
						$entries[] = array(
							'date'        => $pay_date,
							'type'        => 'Credit',
							'description' => 'Payment for ' . $inv['description'] . ' (' . $inv['month'] . ')',
							'amount'      => floatval( $inv['amount'] ), // Full Amount
							'bank_balance' => 0,
							'cash_balance' => 0,
							'ref_id'      => !empty($inv['payment_ref']) ? $inv['payment_ref'] : 'PAY-' . ($inv['block'] ?? '') . '-' . $inv['flat_no'],
							'entity'      => 'Flat ' . ($inv['block'] ? $inv['block'] . '-' : '') . $inv['flat_no'],
							'account_type'=> 'bank' // Default to bank for imported bulk data
						);
					}
				}
			}
		}

		// 3. Get Pending Payment Requests (Credit - Pending)
		$all_requests = $this->db->get( 'requests' );
		if ( ! empty( $all_requests ) ) {
			foreach ( $all_requests as $req ) {
				$module = $req['module'] ?? ($req['entity_type'] ?? '');
				$status = $req['status'] ?? 'pending';
				$is_pending = in_array( $status, array( 'pending', 'pending_secretary', 'pending_treasurer' ) );
				$is_approved = ($status === 'approved');

				if ( ($module === 'accounts' || $module === 'finance') && ($is_pending || $is_approved) && ($req['request_type'] === 'record_payment') ) {
					$p_payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode( $req['payload'], true );
					if ( ! empty( $p_payload ) && gmdate( 'Y', strtotime( $p_payload['date'] ?? '' ) ) == $year ) {
						$entries[] = array(
							'date'        => $p_payload['date'] ?? gmdate('Y-m-d'),
							'type'        => 'Credit',
							'description' => 'Payment for ' . ($p_payload['invoice_id'] ?? 'Maintenance') . ($is_pending ? ' (' . ucfirst(str_replace('pending_', '', $status)) . ' Verification)' : ''),
							'amount'      => floatval( $p_payload['amount'] ?? 0 ),
							'bank_balance' => 0,
							'cash_balance' => 0,
							'ref_id'      => ($is_pending ? 'PENDING-' : 'APR-') . substr($req['id'], -4),
							'entity'      => 'Flat ' . ($p_payload['block'] ? $p_payload['block'] . '-' : '') . ($p_payload['flat_no'] ?? 'Unknown'),
							'account_type'=> $p_payload['account_type'] ?? ( (strtolower($p_payload['method'] ?? '') === 'cash') ? 'cash' : 'bank' ),
							'is_pending'  => $is_pending
						);
					}
				}
			}
		}

		// 4. Sort Chronologically
		usort( $entries, function( $a, $b ) {
			return strtotime( $a['date'] ) - strtotime( $b['date'] );
		});

		$opening_bank = floatval( get_option( 'SNESTX51_opening_bank_' . $year, get_option( 'SNESTX51_opening_bank', 0 ) ) );
		$opening_cash = floatval( get_option( 'SNESTX51_opening_cash_' . $year, get_option( 'SNESTX51_opening_cash', 0 ) ) );
		
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
			if ( ! empty( $entry['is_pending'] ) ) {
				$entry['bank_balance'] = $bank_bal;
				$entry['cash_balance'] = $cash_bal;
				continue;
			}

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
		SNESTX51_Admin_App::render_view('ledger');
	}

    /**
     * Get Current Overall Balance Breakdown
     */
    public function get_current_balance() {
        $year = gmdate('Y');
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
        if(!$month) $month = gmdate('Y-m');
        
        $all_flats = $this->db->get('flats');
        $invoices = $this->db->get('invoices', array( 'load_relations' => true ));
        
        // De-duplicate flats by block + number
        $flats = [];
        $seen = [];
        foreach($all_flats as $f) {
            $b = trim(strtoupper($f['block'] ?? ''));
            $n = trim(strtoupper($f['flat_number'] ?? ($f['id'] ?? '')));
            if (!$n) continue;
            
            $key = $b . '-' . $n;
            if (!isset($seen[$key])) {
                $flats[] = [
                    'id'          => $f['id'],
                    'block'       => $b,
                    'flat_number' => $n,
                    'resident_name'=> $f['resident_name'] ?? ''
                ];
                $seen[$key] = true;
            }
        }

        $summary = [];
        foreach($flats as $f) {
            $flat_id = $f['flat_number'];
            $block = $f['block'];
            
            $current_month_status = 'unpaid';
            $paid_amt = 0;
            $due_amt = 0;
            $unpaid_months = [];
            $has_previous_unpaid = false;

            // Sort all invoices by month to detect previous ones
            $flat_invoices = array_filter($invoices, function($inv) use ($flat_id, $block) {
                return (string)($inv['flat_no'] ?? '') === (string)$flat_id 
                    && (string)($inv['block'] ?? '') === (string)$block
                    && ($inv['type'] ?? '') === 'maintenance';
            });
            
            usort($flat_invoices, function($a, $b) { return strcmp($a['month'], $b['month']); });

            foreach($flat_invoices as $inv) {
                $inv_month = $inv['month'] ?? '';
                $inv_status = strtolower($inv['status'] ?? 'unpaid');
                $inv_due = (float)($inv['amount'] ?? 0);
                $inv_paid = 0;

                if (!empty($inv['payments']) && is_array($inv['payments'])) {
                    foreach($inv['payments'] as $p) $inv_paid += (float)($p['amount'] ?? 0);
                }
                
                // Fallback for Imported Data
                if($inv_paid == 0 && $inv_status === 'paid') $inv_paid = $inv_due;

                $is_fully_paid = ($inv_paid >= $inv_due && $inv_due > 0);

                if ($inv_month === $month) {
                    $current_month_status = $inv_status;
                    $paid_amt = $inv_paid;
                    $due_amt = $inv_due;
                }

                if (!$is_fully_paid) {
                    $unpaid_months[] = gmdate('M Y', strtotime($inv_month));
                    if ($inv_month < $month) {
                        $has_previous_unpaid = true;
                    }
                }
            }

            // Determine Status Group for UI
            $status_type = 'paid';
            if (empty($unpaid_months)) {
                $status_type = 'paid';
            } elseif ($current_month_status === 'unpaid') {
                $status_type = $has_previous_unpaid ? 'chronic' : 'danger';
            } else {
                $status_type = 'warning';
            }

            $summary[] = [
                'flat_no'             => $flat_id,
                'block'               => $block,
                'resident'            => $f['resident_name'] ?: 'Vacant',
                'status'              => $current_month_status,
                'paid'                => $paid_amt,
                'due'                 => $due_amt,
                'unpaid_months'       => $unpaid_months,
                'has_previous_unpaid' => $has_previous_unpaid,
                'status_type'         => $status_type
            ];
        }

        // Sort by Block then Flat No
        usort($summary, function($a, $b) { 
            if ($a['block'] !== $b['block']) return strcmp($a['block'], $b['block']);
            return strnatcmp($a['flat_no'], $b['flat_no']); 
        });
        
        return $summary;
    }
}
