<?php
/**
 * Payment Service acting as the Single Source of Truth for all payment/invoice mutations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Payment_Service {
	
	public static function process_payment( $invoice_id, $amount, $method, $reference = '', $date = '', $notes = '' ) {
		$db = new SNESTX51_DB_Router();
		$invoice = $db->get_invoice( $invoice_id );
		
		if ( ! $invoice ) {
			return new WP_Error( 'not_found', 'Invoice not found.' );
		}

		$amount = floatval( $amount );
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', 'Amount must be greater than zero.' );
		}

		$date = empty( $date ) ? current_time( 'mysql' ) : $date;
		
		// Map existing payments
		$payments = isset($invoice['payments']) && is_array($invoice['payments']) ? $invoice['payments'] : [];
		
		// Fallback for legacy imported data: if paid but no payments array
		$paid_so_far = 0;
		if ( empty($payments) && strtolower($invoice['status'] ?? '') === 'paid' ) {
			$payments[] = [
				'id' => uniqid('pay_leg_'),
				'amount' => floatval($invoice['amount']),
				'method' => $invoice['payment_mode'] ?? 'Unknown',
				'date' => $invoice['payment_date'] ?? $invoice['created_at'],
				'reference' => $invoice['payment_ref'] ?? '',
				'notes' => 'Legacy imported payment'
			];
		}
		
		foreach($payments as $p) {
			$paid_so_far += floatval($p['amount']);
		}

		$new_payment = [
			'id' => uniqid('pay_'),
			'amount' => $amount,
			'method' => sanitize_text_field($method),
			'date' => sanitize_text_field($date),
			'reference' => sanitize_text_field($reference),
			'notes' => sanitize_textarea_field($notes)
		];
		
		$payments[] = $new_payment;
		$total_paid = $paid_so_far + $amount;
		$invoice_total = floatval($invoice['amount']);
		
		// Determine new status
		$new_status = 'partial';
		if ( $total_paid >= $invoice_total ) {
			$new_status = 'paid';
		}
		
		// Update Invoice
		$update_data = [
			'payments' => $payments,
			'status' => $new_status,
			'updated_at' => current_time( 'mysql' )
		];
		
		// For legacy fields, update the last payment info if it becomes fully paid
		if ( $new_status === 'paid' ) {
			$update_data['payment_mode'] = $method;
			$update_data['payment_date'] = $date;
			$update_data['payment_ref'] = $reference;
		}

		$updated = $db->update( 'invoices', $invoice_id, $update_data );
		if ( ! $updated ) {
			return new WP_Error( 'db_error', 'Failed to update invoice.' );
		}

		// Insert into the `payments` table
		$db->insert( 'payments', array(
			'id'          => $new_payment['id'],
			'invoice_id'  => $invoice_id,
			'amount'      => $amount,
			'date'        => $date,
			'method'      => $method,
			'reference'   => $reference,
			'recorded_by' => get_current_user_id() ?: 0,
			'metadata'    => json_encode( array( 'account_type' => ( strtolower($method) === 'cash' ) ? 'cash' : 'bank' ) )
		) );

		// Ledger Entry is dynamically derived from the payments table during Audit display.
		// No need to insert into a separate ledger table.

		// Update State Hash for Real-Time Sync
		self::update_state_hash();

		// Fire Action for webhooks/notifications
		do_action( 'SNESTX51_payment_processed', $invoice_id, $new_payment['id'], $amount );

		return $new_payment;
	}

	public static function init() {
		// Example: Hook into payment gateways if needed.
		// add_action( 'stripe_payment_success', array( __CLASS__, 'process_payment' ), 10, 4 );
        
        // Register AJAX endpoint for Admin polling
        add_action('wp_ajax_SNESTX51_poll_state_hash', array( __CLASS__, 'ajax_poll_state_hash' ));
	}

    public static function ajax_poll_state_hash() {
        wp_send_json_success([ 'hash' => self::get_state_hash() ]);
    }

	public static function update_state_hash() {
		// Store a precise microtime hash in a transient
		set_transient( 'SNESTX51_payment_state_hash', microtime(true), WEEK_IN_SECONDS );
	}

	public static function get_state_hash() {
		$hash = get_transient( 'SNESTX51_payment_state_hash' );
		if ( ! $hash ) {
			$hash = microtime(true);
			set_transient( 'SNESTX51_payment_state_hash', $hash, WEEK_IN_SECONDS );
		}
		return $hash;
	}
}

SNESTX51_Payment_Service::init();
