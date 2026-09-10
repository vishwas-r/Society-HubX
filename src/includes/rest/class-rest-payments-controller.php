<?php
/**
 * REST API Controller for Payments (State Hash & Webhooks)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Payments_Controller {
	
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		// 1. State Hash Endpoint (Polling)
		register_rest_route( 'society-hubx/v1', '/state-hash', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_state_hash' ),
			'permission_callback' => array( $this, 'check_frontend_auth' )
		) );
		
		// 2. Dashboard Partial Data Endpoint (For JS re-render)
		register_rest_route( 'society-hubx/v1', '/dashboard-data', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_dashboard_data' ),
			'permission_callback' => array( $this, 'check_frontend_auth' )
		) );

		// 3. Registered Gateways List (For Mobile / Frontend checkout)
		register_rest_route( 'society-hubx/v1', '/payments/gateways', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_gateways' ),
			'permission_callback' => array( $this, 'check_frontend_auth' ),
		) );

		// 4. Create Order / Checkout Session
		register_rest_route( 'society-hubx/v1', '/payments/create-order', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'create_order' ),
			'permission_callback' => array( $this, 'check_frontend_auth' ),
			'args'     => array(
				'invoice_id' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		// 5. Webhook Ingress (Gateway Integration)
		register_rest_route( 'society-hubx/v1', '/webhooks/(?P<gateway>[a-zA-Z0-9-]+)', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'handle_webhook' ),
			'permission_callback' => array( $this, 'webhook_permissions_check' ),
		) );
	}
	
	public function check_frontend_auth() {
		if ( ! current_user_can( 'read' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to access this endpoint.', 'society-hubx' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * List available payment gateways.
	 */
	public function get_gateways() {
		$gateways = SHUBX51_Payment_Service::get_gateways();
		$active   = SHUBX51_Payment_Service::get_active_gateway();
		$active_id = $active ? $active->get_id() : '';

		$list = array();
		foreach ( $gateways as $g ) {
			if ( $g->is_available() ) {
				$list[] = array(
					'id'          => $g->get_id(),
					'title'       => $g->get_title(),
					'description' => $g->get_description(),
					'is_default'  => ( $g->get_id() === $active_id ),
				);
			}
		}

		return rest_ensure_response( array(
			'success'  => true,
			'gateways' => $list,
		) );
	}

	/**
	 * Initiate checkout order for an invoice.
	 */
	public function create_order( WP_REST_Request $request ) {
		$invoice_id = sanitize_text_field( $request->get_param( 'invoice_id' ) );
		$gateway_id = sanitize_key( $request->get_param( 'gateway_id' ) ?? '' );

		$db = new SHUBX51_DB_Router();
		$invoice = $db->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			return new WP_Error( 'invoice_not_found', __( 'Invoice not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		// Calculate outstanding balance
		$amount_due = floatval( $invoice['amount'] ?? 0 );
		$payments = isset( $invoice['payments'] ) && is_array( $invoice['payments'] ) ? $invoice['payments'] : array();
		$paid_so_far = 0;
		foreach ( $payments as $p ) {
			$paid_so_far += floatval( $p['amount'] ?? 0 );
		}
		$balance = max( 0, $amount_due - $paid_so_far );

		if ( $balance <= 0 || strtolower( $invoice['status'] ?? '' ) === 'paid' ) {
			return new WP_Error( 'already_paid', __( 'This invoice is already fully paid.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		// Customer Details
		$current_user = wp_get_current_user();
		$customer = array(
			'name'        => $current_user->display_name,
			'email'       => $current_user->user_email,
			'phone'       => get_user_meta( $current_user->ID, 'phone', true ) ?: '',
			'flat_number' => $invoice['flat_number'] ?? '',
		);

		$result = SHUBX51_Payment_Service::create_order( $invoice_id, $balance, $customer, $gateway_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success' => true,
			'order'   => $result,
		) );
	}

	public function webhook_permissions_check( WP_REST_Request $request ) {
		$gateway_id = sanitize_key( $request->get_param( 'gateway' ) );
		$gateway = SHUBX51_Payment_Service::get_gateway( $gateway_id );

		if ( $gateway && method_exists( $gateway, 'verify_webhook_permission' ) ) {
			return $gateway->verify_webhook_permission( $request );
		}
		
		// Fallback for legacy / standalone hooks
		return apply_filters( "shubx51_webhook_permissions_check_{$gateway_id}", false, $request );
	}

	public function get_state_hash( $request ) {
		return rest_ensure_response( array(
			'hash' => SHUBX51_Payment_Service::get_state_hash(),
			'timestamp' => current_time('mysql')
		) );
	}
	
	public function get_dashboard_data( $request ) {
		$user_id = get_current_user_id();
		$dashboard = new SHUBX51_Frontend_Dashboard();
		$data = $dashboard->get_dashboard_data( $user_id );
		
		// We only need accounts & expenses data for the sync
		return rest_ensure_response( array(
			'success' => true,
			'accounts' => array(
				'invoices' => $data['invoices'] ?? [],
				'pending_payment_requests' => $data['pending_payment_requests'] ?? []
			),
			'expenses' => array(
				'current_balance' => $data['current_balance'] ?? [],
				'monthly_summary' => $data['monthly_summary'] ?? [],
				'detailed_expenses' => $data['detailed_expenses'] ?? [],
				'expenseChartData' => $data['expenseChartData'] ?? []
			),
			'paymentHistory' => $data['paymentHistory'] ?? []
		) );
	}

	public function handle_webhook( WP_REST_Request $request ) {
		$gateway_id = sanitize_key( $request->get_param( 'gateway' ) );
		$gateway = SHUBX51_Payment_Service::get_gateway( $gateway_id );

		if ( $gateway && method_exists( $gateway, 'handle_webhook' ) ) {
			return $gateway->handle_webhook( $request );
		}

		// Trigger action for external gateway addons
		do_action( "shubx51_handle_webhook_{$gateway_id}", $request );
		
		return rest_ensure_response( array( 'status' => 'received' ) );
	}
}
