<?php
/**
 * Payment Service acting as the Single Source of Truth for all payment/invoice mutations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Payment_Service {
	
	public static function process_payment( $invoice_id, $amount, $method, $reference = '', $date = '', $notes = '' ) {
		$db = new NAMMASOCIETY51_DB_Router();
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
		do_action( 'nammasociety51_payment_processed', $invoice_id, $new_payment['id'], $amount );

		return $new_payment;
	}

	/**
	 * Registered gateway instances implementing NAMMASOCIETY51_Payment_Gateway_Interface.
	 *
	 * @var array<string, NAMMASOCIETY51_Payment_Gateway_Interface>
	 */
	private static $gateways = array();

	/**
	 * Register a payment gateway addon.
	 *
	 * @param NAMMASOCIETY51_Payment_Gateway_Interface $gateway
	 */
	public static function register_gateway( NAMMASOCIETY51_Payment_Gateway_Interface $gateway ) {
		$id = sanitize_key( $gateway->get_id() );
		self::$gateways[ $id ] = $gateway;

		// Automatically bind webhook verification and handling filters
		add_filter( "nammasociety51_webhook_permissions_check_{$id}", array( $gateway, 'verify_webhook_permission' ), 10, 2 );
		add_action( "nammasociety51_handle_webhook_{$id}", array( $gateway, 'handle_webhook' ), 10, 1 );
	}

	/**
	 * Get registered gateway by ID.
	 *
	 * @param string $id
	 * @return NAMMASOCIETY51_Payment_Gateway_Interface|null
	 */
	public static function get_gateway( string $id ) {
		$id = sanitize_key( $id );
		return isset( self::$gateways[ $id ] ) ? self::$gateways[ $id ] : null;
	}

	/**
	 * Get all registered gateways.
	 *
	 * @return array<string, NAMMASOCIETY51_Payment_Gateway_Interface>
	 */
	public static function get_gateways(): array {
		return self::$gateways;
	}

	/**
	 * Get the currently active/preferred payment gateway.
	 *
	 * @return NAMMASOCIETY51_Payment_Gateway_Interface|null
	 */
	public static function get_active_gateway() {
		$configured_id = get_option( 'nammasociety51_active_payment_gateway', '' );
		if ( ! empty( $configured_id ) && isset( self::$gateways[ $configured_id ] ) && self::$gateways[ $configured_id ]->is_available() ) {
			return self::$gateways[ $configured_id ];
		}

		// Fallback: return the first available gateway
		foreach ( self::$gateways as $gateway ) {
			if ( $gateway->is_available() ) {
				return $gateway;
			}
		}

		return null;
	}

	/**
	 * Initiate checkout / create order via active or designated gateway.
	 *
	 * @param int|string $invoice_id
	 * @param float      $amount
	 * @param array      $customer_details
	 * @param string     $gateway_id Optional specific gateway.
	 * @return array|WP_Error
	 */
	public static function create_order( $invoice_id, float $amount, array $customer_details = array(), string $gateway_id = '' ) {
		$gateway = ! empty( $gateway_id ) ? self::get_gateway( $gateway_id ) : self::get_active_gateway();
		if ( ! $gateway || ! $gateway->is_available() ) {
			return new WP_Error( 'gateway_unavailable', __( 'No payment gateway is currently available. Please contact the administrator.', 'namma-society' ) );
		}

		return $gateway->create_order( $invoice_id, $amount, $customer_details );
	}

	public static function init() {
		// Allow external addons to register themselves
		do_action( 'nammasociety51_register_payment_gateways', __CLASS__ );
        
		// Register AJAX endpoint for Admin polling
		add_action( 'wp_ajax_nammasociety51_poll_state_hash', array( __CLASS__, 'ajax_poll_state_hash' ) );
	}

    public static function ajax_poll_state_hash() {
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        wp_send_json_success([ 'hash' => self::get_state_hash() ]);
    }

	public static function update_state_hash() {
		// Store a precise microtime hash in a transient
		set_transient( 'nammasociety51_payment_state_hash', microtime(true), WEEK_IN_SECONDS );
	}

	public static function get_state_hash() {
		$hash = get_transient( 'nammasociety51_payment_state_hash' );
		if ( ! $hash ) {
			$hash = microtime(true);
			set_transient( 'nammasociety51_payment_state_hash', $hash, WEEK_IN_SECONDS );
		}
		return $hash;
	}
}

NAMMASOCIETY51_Payment_Service::init();

