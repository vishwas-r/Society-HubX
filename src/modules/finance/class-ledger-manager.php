<?php
/**
 * Module: Ledger Manager
 * Aggregates Financial Data (Invoices & Expenses) for Audit.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Ledger_Manager {

	private $db;

	public function __construct() {
		$this->db = new NAMMASOCIETY51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_nammasociety51_reconcile_balance', array( $this, 'handle_reconcile_balance' ) );
	}

	    public function handle_reconcile_balance() {
        if ( ! check_admin_referer( 'nammasociety51_reconcile_nonce' ) ) wp_die( 'Security check failed' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        $year = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';
        $bank = isset( $_POST['actual_bank'] ) ? floatval( wp_unslash( $_POST['actual_bank'] ) ) : 0;
        $cash = isset( $_POST['actual_cash'] ) ? floatval( wp_unslash( $_POST['actual_cash'] ) ) : 0;
        $opening_bank = isset( $_POST['opening_bank'] ) ? floatval( wp_unslash( $_POST['opening_bank'] ) ) : 0;
        $opening_cash = isset( $_POST['opening_cash'] ) ? floatval( wp_unslash( $_POST['opening_cash'] ) ) : 0;

        update_option( 'nammasociety51_actual_bank_' . $year, $bank );
        update_option( 'nammasociety51_actual_cash_' . $year, $cash );
        update_option( 'nammasociety51_opening_bank_' . $year, $opening_bank );
        update_option( 'nammasociety51_opening_cash_' . $year, $opening_cash );

        wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-accounts&tab=ledger&year=' . $year . '&success=reconciled' ) );
        exit;
    }

	public function register_menu() {
		add_submenu_page(
			'nammasociety51-settings',
			'Audit Ledger',
			'Audit Ledger',
			'manage_options',
			'nammasociety51-accounts&tab=ledger',
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
								'entity'      => 'Flat ' . NAMMASOCIETY51_DB_Router::format_flat_display( $inv['block'] ?? '', $inv['flat_no'] ),
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
							'entity'      => 'Flat ' . NAMMASOCIETY51_DB_Router::format_flat_display( $inv['block'] ?? '', $inv['flat_no'] ),
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
							'entity'      => 'Flat ' . NAMMASOCIETY51_DB_Router::format_flat_display( $p_payload['block'] ?? '', $p_payload['flat_no'] ?? 'Unknown' ),
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

		$opening_bank = floatval( get_option( 'nammasociety51_opening_bank_' . $year, get_option( 'nammasociety51_opening_bank', 0 ) ) );
		$opening_cash = floatval( get_option( 'nammasociety51_opening_cash_' . $year, get_option( 'nammasociety51_opening_cash', 0 ) ) );
		
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
		NAMMASOCIETY51_Admin_App::render_view('ledger');
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
            $b = trim(preg_replace('/^(block[\s_-]*)+/i', '', (string)($f['block'] ?? '')));
            $n = trim((string)($f['flat_number'] ?? ($f['id'] ?? '')));
            if (!$n) continue;
            
            $key = strtoupper($b . '-' . $n);
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
            $flat_invoices = array_filter($invoices, function($inv) use ($flat_id, $f, $block) {
                $i_flat = (string)($inv['flat_no'] ?? '');
                $i_block = trim(preg_replace('/^(block[\s_-]*)+/i', '', (string)($inv['block'] ?? '')));
                $clean_b = trim(preg_replace('/^(block[\s_-]*)+/i', '', (string)$block));
                $matches_flat = ($i_flat === (string)$flat_id || $i_flat === (string)$f['id']);
                $matches_block = (empty($clean_b) || empty($i_block) || strcasecmp($clean_b, $i_block) === 0);
                return $matches_flat && $matches_block && ($inv['type'] ?? '') === 'maintenance';
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

    /**
     * Get Master Chart of Accounts.
     */
    public function get_chart_of_accounts() {
        global $wpdb;
        $table = "{$wpdb->prefix}nammasociety51_chart_of_accounts";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY account_code ASC", ARRAY_A );
        return $rows ?: array();
    }

    /**
     * Get Journal Entries / Vouchers.
     *
     * @param string|null $year
     * @param string|null $account_code
     * @return array
     */
    public function get_journal_entries( $year = null, $account_code = null ) {
        global $wpdb;
        $table = "{$wpdb->prefix}nammasociety51_journal_entries";

        $where = array( '1=1' );
        $params = array();

        if ( ! empty( $year ) ) {
            $where[] = 'YEAR(date) = %d';
            $params[] = intval( $year );
        }

        if ( ! empty( $account_code ) ) {
            $where[] = 'account_code = %s';
            $params[] = sanitize_text_field( $account_code );
        }

        $where_sql = implode( ' AND ', $where );
        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY date DESC, id DESC";

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $query = $wpdb->prepare( $query, $params );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $rows = $wpdb->get_results( $query, ARRAY_A );
        return $rows ?: array();
    }

    /**
     * Generate Trial Balance.
     *
     * @param string|null $year
     * @return array
     */
    public function get_trial_balance( $year = null ) {
        if ( ! $year ) {
            $year = wp_date( 'Y' );
        }

        global $wpdb;
        $coa = $this->get_chart_of_accounts();
        $je_table = "{$wpdb->prefix}nammasociety51_journal_entries";

        $accounts_tb = array();
        $total_debit = 0.00;
        $total_credit = 0.00;

        foreach ( $coa as $acc ) {
            $code = $acc['account_code'];
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $totals = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT SUM(debit) as debits, SUM(credit) as credits FROM {$je_table} WHERE account_code = %s AND YEAR(date) = %d",
                    $code,
                    $year
                ),
                ARRAY_A
            );

            $debits = floatval( $totals['debits'] ?? 0 );
            $credits = floatval( $totals['credits'] ?? 0 );

            // If account has no journal entries yet, compute sensible defaults from live subsystem tables
            if ( $debits == 0 && $credits == 0 ) {
                if ( $code === '1010' ) {
                    $debits = floatval( get_option( 'nammasociety51_opening_cash_' . $year, 0 ) );
                } elseif ( $code === '1020' ) {
                    $debits = floatval( get_option( 'nammasociety51_opening_bank_' . $year, 0 ) );
                } elseif ( $code === '1040' ) {
                    // Sundry Debtors: Unpaid invoice amounts
                    $invoices = $this->db->get( 'invoices' );
                    $unpaid = 0;
                    foreach ( $invoices as $inv ) {
                        if ( wp_date( 'Y', strtotime( $inv['month'] ?? '' ) ) == $year && ( $inv['status'] ?? '' ) !== 'paid' ) {
                            $unpaid += floatval( $inv['amount'] ?? 0 ) - floatval( $inv['total_paid'] ?? 0 );
                        }
                    }
                    $debits = max( 0, $unpaid );
                } elseif ( $code === '4010' ) {
                    // Total Maintenance Demanded
                    $invoices = $this->db->get( 'invoices' );
                    $income = 0;
                    foreach ( $invoices as $inv ) {
                        if ( wp_date( 'Y', strtotime( $inv['month'] ?? '' ) ) == $year ) {
                            $income += floatval( $inv['base_amount'] ?? $inv['amount'] );
                        }
                    }
                    $credits = $income;
                }
            }

            $accounts_tb[] = array(
                'account_code' => $code,
                'account_name' => $acc['account_name'],
                'account_type' => $acc['account_type'],
                'debit'        => $debits,
                'credit'       => $credits,
            );

            $total_debit += $debits;
            $total_credit += $credits;
        }

        return array(
            'year'         => $year,
            'accounts'     => $accounts_tb,
            'total_debit'  => round( $total_debit, 2 ),
            'total_credit' => round( $total_credit, 2 ),
            'is_balanced'  => ( abs( $total_debit - $total_credit ) < 0.05 ),
        );
    }

    /**
     * Generate Profit & Loss Statement (Income & Expenditure).
     *
     * @param string|null $year
     * @return array
     */
    public function get_profit_and_loss( $year = null ) {
        if ( ! $year ) {
            $year = wp_date( 'Y' );
        }

        global $wpdb;
        $coa = $this->get_chart_of_accounts();
        $je_table = "{$wpdb->prefix}nammasociety51_journal_entries";

        $incomes = array();
        $expenses = array();
        $total_income = 0.00;
        $total_expense = 0.00;

        // Fallback approved expenses for categories
        $all_expenses = $this->db->get( 'expenses' );
        $cat_expenses = array();
        foreach ( $all_expenses as $ex ) {
            if ( ( $ex['status'] ?? '' ) === 'approved' && wp_date( 'Y', strtotime( $ex['date'] ?? '' ) ) == $year ) {
                $c = strtolower( trim( $ex['category'] ?? 'repairs' ) );
                $cat_expenses[ $c ] = ( $cat_expenses[ $c ] ?? 0 ) + floatval( $ex['amount'] );
            }
        }

        foreach ( $coa as $acc ) {
            $code = $acc['account_code'];
            $type = $acc['account_type'];

            if ( $type === 'Income' || strpos( $code, '4' ) === 0 ) {
                // Income: Credit - Debit
                $totals = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT (SUM(credit) - SUM(debit)) as net FROM {$je_table} WHERE account_code = %s AND YEAR(date) = %d",
                        $code,
                        $year
                    ),
                    ARRAY_A
                );
                $amt = floatval( $totals['net'] ?? 0 );

                // Fallback to invoices if no journal entry
                if ( $amt == 0 && $code === '4010' ) {
                    $invoices = $this->db->get( 'invoices' );
                    foreach ( $invoices as $inv ) {
                        if ( wp_date( 'Y', strtotime( $inv['month'] ?? '' ) ) == $year ) {
                            $amt += floatval( $inv['base_amount'] ?? $inv['amount'] );
                        }
                    }
                }

                $incomes[] = array(
                    'account_code' => $code,
                    'account_name' => $acc['account_name'],
                    'amount'       => $amt,
                );
                $total_income += $amt;
            } elseif ( $type === 'Expense' || strpos( $code, '5' ) === 0 ) {
                // Expense: Debit - Credit
                $totals = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT (SUM(debit) - SUM(credit)) as net FROM {$je_table} WHERE account_code = %s AND YEAR(date) = %d",
                        $code,
                        $year
                    ),
                    ARRAY_A
                );
                $amt = floatval( $totals['net'] ?? 0 );

                // Fallback to categorized expenses table if journal empty
                if ( $amt == 0 ) {
                    if ( $code === '5010' ) $amt = $cat_expenses['electricity'] ?? 0;
                    elseif ( $code === '5020' ) $amt = $cat_expenses['water'] ?? 0;
                    elseif ( $code === '5030' ) $amt = $cat_expenses['security'] ?? 0;
                    elseif ( $code === '5040' ) $amt = $cat_expenses['housekeeping'] ?? 0;
                    elseif ( $code === '5050' ) $amt = $cat_expenses['lift'] ?? 0;
                    elseif ( $code === '5060' ) $amt = $cat_expenses['diesel'] ?? ( $cat_expenses['fuel'] ?? 0 );
                    elseif ( $code === '5090' ) $amt = $cat_expenses['repairs'] ?? ( $cat_expenses['maintenance'] ?? 0 );
                }

                $expenses[] = array(
                    'account_code' => $code,
                    'account_name' => $acc['account_name'],
                    'amount'       => $amt,
                );
                $total_expense += $amt;
            }
        }

        $net_surplus = $total_income - $total_expense;

        return array(
            'year'          => $year,
            'incomes'       => $incomes,
            'total_income'  => round( $total_income, 2 ),
            'expenses'      => $expenses,
            'total_expense' => round( $total_expense, 2 ),
            'net_surplus'   => round( $net_surplus, 2 ),
            'is_surplus'    => ( $net_surplus >= 0 ),
        );
    }

    /**
     * Generate Balance Sheet (Statement of Financial Affairs).
     *
     * @param string|null $year
     * @return array
     */
    public function get_balance_sheet( $year = null ) {
        if ( ! $year ) {
            $year = wp_date( 'Y' );
        }

        $pl = $this->get_profit_and_loss( $year );
        $net_surplus = $pl['net_surplus'];

        // Live Balances
        $opening_bank = floatval( get_option( 'nammasociety51_opening_bank_' . $year, 0 ) );
        $opening_cash = floatval( get_option( 'nammasociety51_opening_cash_' . $year, 0 ) );
        $actual_bank = floatval( get_option( 'nammasociety51_actual_bank_' . $year, $opening_bank ) );
        $actual_cash = floatval( get_option( 'nammasociety51_actual_cash_' . $year, $opening_cash ) );

        // Sundry Debtors (Resident dues)
        $invoices = $this->db->get( 'invoices', array( 'load_relations' => true ) );
        $sundry_debtors = 0.00;
        $gst_liability = 0.00;

        foreach ( $invoices as $inv ) {
            if ( wp_date( 'Y', strtotime( $inv['month'] ?? '' ) ) == $year ) {
                $paid = floatval( $inv['total_paid'] ?? 0 );
                $due = floatval( $inv['amount'] ?? 0 );
                if ( $due > $paid ) {
                    $sundry_debtors += ( $due - $paid );
                }
                $gst_liability += floatval( $inv['cgst_amount'] ?? 0 ) + floatval( $inv['sgst_amount'] ?? 0 );
            }
        }

        $sinking_fund_fd = floatval( get_option( 'nammasociety51_sinking_fund_fd', 500000 ) );

        // ASSETS
        $assets = array(
            array( 'code' => '1010', 'name' => 'Cash in Hand', 'amount' => $actual_cash ),
            array( 'code' => '1020', 'name' => 'Bank Operating Account', 'amount' => $actual_bank ),
            array( 'code' => '1030', 'name' => 'Sinking Fund Fixed Deposit', 'amount' => $sinking_fund_fd ),
            array( 'code' => '1040', 'name' => 'Sundry Debtors (Resident Dues)', 'amount' => $sundry_debtors ),
        );
        $total_assets = array_sum( array_column( $assets, 'amount' ) );

        // LIABILITIES
        $sinking_reserve = $sinking_fund_fd; // Matched reserve
        $sundry_creditors = 0.00;

        $liabilities = array(
            array( 'code' => '2010', 'name' => 'Sinking Fund Reserve', 'amount' => $sinking_reserve ),
            array( 'code' => '2020', 'name' => 'Sundry Creditors (Vendor Payables)', 'amount' => $sundry_creditors ),
            array( 'code' => '2050', 'name' => 'GST Output Liability (18%)', 'amount' => $gst_liability ),
        );
        $total_liabilities = array_sum( array_column( $liabilities, 'amount' ) );

        // EQUITY / CAPITAL FUND
        $general_reserve = max( 0, $total_assets - $total_liabilities - $net_surplus );
        $equity = array(
            array( 'code' => '3010', 'name' => 'General Reserves & Surplus B/F', 'amount' => $general_reserve ),
            array( 'code' => 'PL-CURR', 'name' => 'Current Year Net Surplus / (Deficit)', 'amount' => $net_surplus ),
        );
        $total_equity = array_sum( array_column( $equity, 'amount' ) );

        $total_liab_equity = $total_liabilities + $total_equity;

        return array(
            'year'                    => $year,
            'assets'                  => $assets,
            'total_assets'            => round( $total_assets, 2 ),
            'liabilities'             => $liabilities,
            'total_liabilities'       => round( $total_liabilities, 2 ),
            'equity'                  => $equity,
            'total_equity'            => round( $total_equity, 2 ),
            'total_liab_and_equity'   => round( $total_liab_equity, 2 ),
            'is_balanced'             => ( abs( $total_assets - $total_liab_equity ) < 1.00 ),
        );
    }
}

