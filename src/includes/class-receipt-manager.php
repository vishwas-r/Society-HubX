<?php
/**
 * Class: Receipt Manager
 * Handles receipt generation and numbering for payments.
 * Receipt format: snestx-YYYYMMXXX (Year, Month, Auto-incremented number)
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Receipt_Manager {
	private $db;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
	}

	/**
	 * Generate Receipt Number for given invoice
	 * Format: snestx-YYYYMMXXX
	 * YYYY = Year, MM = Month, XXX = Auto-incremented (001-999)
	 *
	 * @param string $invoice_id Invoice ID
	 * @param string $month Month in format YYYY-MM
	 * @return string Receipt number
	 */
	public function get_receipt_number( $invoice_id, $month ) {
		// Check if receipt number already exists for this invoice
		$receipts = $this->db->get( 'receipts' );
		
		foreach ( $receipts as $receipt ) {
			if ( strval($receipt['invoice_id']) === strval($invoice_id) ) {
				return $receipt['receipt_number'];
			}
		}

		// Generate new receipt number
		$year_month = str_replace( '-', '', substr( $month, 0, 7 ) ); // YYYYMM
		$year = substr( $year_month, 0, 4 );
		$month_str = substr( $year_month, 4, 2 );

		// Find highest receipt number for this year-month
		$highest = 0;
		foreach ( $receipts as $receipt ) {
			$num = $receipt['receipt_number'];
			if ( strpos( $num, 'snestx-' . $year . $month_str ) === 0 ) {
				$seq = intval( substr( $num, -3 ) );
				if ( $seq > $highest ) {
					$highest = $seq;
				}
			}
		}

		// Increment sequence
		$next_seq = str_pad( $highest + 1, 3, '0', STR_PAD_LEFT );
		$receipt_number = 'snestx-' . $year . $month_str . $next_seq;

		// Store in receipts table
		$receipt_data = array(
			'id'              => uniqid( 'receipt_' ),
			'invoice_id'      => $invoice_id,
			'receipt_number'  => $receipt_number,
			'generated_date'  => current_time( 'mysql' ),
		);

		$this->db->insert( 'receipts', $receipt_data );

		return $receipt_number;
	}

	/**
	 * Get Receipt Number (retrieve or generate)
	 *
	 * @param array $invoice Invoice data
	 * @return string Receipt number
	 */
	public function get_or_generate_receipt_number( $invoice ) {
		$invoice_id = $invoice['id'];
		$month = $invoice['month'] ?? gmdate( 'Y-m' );

		return $this->get_receipt_number( $invoice_id, $month );
	}

	/**
	 * Get Receipt Data for display
	 *
	 * @param array $invoice Invoice data
	 * @param bool $include_payments Include payment details
	 * @return array Receipt data
	 */
	public function prepare_receipt_data( $invoice, $include_payments = true ) {
		$receipt_number = $this->get_or_generate_receipt_number( $invoice );
		
		$payments = array();
		$total_paid = 0;

		if ( $include_payments ) {
			$payment_list = $invoice['payments'] ?? array();

			if ( ! empty( $payment_list ) && is_array( $payment_list ) ) {
				foreach ( $payment_list as $payment ) {
					$amount = floatval( $payment['amount'] ?? 0 );
					$total_paid += $amount;
					$payments[] = array(
						'date'   => $payment['date'] ?? $payment['timestamp'] ?? current_time( 'Y-m-d' ),
						'amount' => $amount,
						'method' => $payment['method'] ?? 'Bank Transfer',
						'ref'    => $payment['reference'] ?? $payment['ref'] ?? $payment['txn_id'] ?? 'N/A',
					);
				}
			} elseif ( strtolower( trim( $invoice['status'] ?? '' ) ) === 'paid' ) {
				// Fallback for legacy "Paid" status without explicit payment rows (Imported Data)
				$total_paid = floatval( $invoice['amount'] ?? 0 );
				$payments[] = array(
					'date'   => !empty($invoice['payment_date'] ?? '') && $invoice['payment_date'] !== '0000-00-00 00:00:00' 
								? gmdate('Y-m-d', strtotime($invoice['payment_date'])) 
								: ($invoice['created_at'] ? gmdate('Y-m-d', strtotime($invoice['created_at'])) : gmdate('Y-m-d')),
					'amount' => $total_paid,
					'method' => $invoice['payment_mode'] ?? 'Recorded',
					'ref'    => $invoice['payment_ref'] ?? 'Legacy Record',
				);
			}
		}

		// Sort payments by date (newest first)
		usort( $payments, function( $a, $b ) {
			return strtotime( $b['date'] ) - strtotime( $a['date'] );
		});

		$invoice_amount = floatval( $invoice['amount'] ?? 0 );
		$balance_due = max( 0, $invoice_amount - $total_paid );

		return array(
			'receipt_number'   => $receipt_number,
			'invoice_id'       => $invoice['id'],
			'date'             => $invoice['created_at'] ?? current_time( 'Y-m-d H:i:s' ),
			'flat_no'          => $invoice['flat_no'] ?? 'N/A',
			'resident_name'    => $invoice['resident_name'] ?? 'Resident',
			'description'      => $invoice['description'] ?? 'Society Maintenance',
			'invoice_month'    => $invoice['month'] ?? gmdate( 'Y-m' ),
			'due_date'         => $invoice['due_date'] ?? '',
			'invoice_amount'   => $invoice_amount,
			'total_paid'       => $total_paid,
			'balance_due'      => $balance_due,
			'status'           => $this->get_payment_status( $invoice_amount, $total_paid ),
			'payments'         => $payments,
			'society_name'     => get_option( 'SNESTX51_society_name', 'Society' ),
		);
	}

	/**
	 * Get payment status
	 *
	 * @param float $invoice_amount Invoice amount
	 * @param float $total_paid Total paid
	 * @return string Status (paid|partial|unpaid)
	 */
	private function get_payment_status( $invoice_amount, $total_paid ) {
		if ( $total_paid >= $invoice_amount ) {
			return 'paid';
		} elseif ( $total_paid > 0 ) {
			return 'partial';
		}
		return 'unpaid';
	}
}
