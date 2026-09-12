<?php
/**
 * Interface for Society-GovernX Payment Gateways.
 *
 * All external payment gateway plugins (e.g., Razorpay, Cashfree, Stripe) must implement this interface.
 * Core plugin contains ZERO gateway SDKs or hardcoded credentials.
 *
 * @package NammaSociety
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NAMMASOCIETY51_Payment_Gateway_Interface {

	/**
	 * Unique gateway slug (e.g. 'razorpay', 'cashfree', 'stripe').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable title for UI dropdowns and labels.
	 *
	 * @return string
	 */
	public function get_title(): string;

	/**
	 * Human-readable description.
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Check if gateway is configured and enabled.
	 *
	 * @return bool
	 */
	public function is_available(): bool;

	/**
	 * Initiate payment / create gateway order or checkout session.
	 *
	 * @param int|string $invoice_id
	 * @param float      $amount
	 * @param array      $customer_details Array with 'name', 'email', 'phone', 'flat_number'.
	 * @return array|WP_Error Array with order_id, redirect_url or sdk_payload, or WP_Error on failure.
	 */
	public function create_order( $invoice_id, float $amount, array $customer_details = array() );

	/**
	 * Verify payment signature from frontend callback or redirect.
	 *
	 * @param array $payload
	 * @return bool
	 */
	public function verify_payment( array $payload ): bool;

	/**
	 * Check webhook signature and authentication.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function verify_webhook_permission( WP_REST_Request $request );

	/**
	 * Handle incoming asynchronous webhook payload from the payment provider.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_webhook( WP_REST_Request $request );
}
