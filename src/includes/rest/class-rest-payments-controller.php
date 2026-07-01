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

		// 3. Webhook Ingress (Gateway Integration)
		register_rest_route( 'society-hubx/v1', '/webhooks/(?P<gateway>[a-zA-Z0-9-]+)', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'handle_webhook' ),
			'permission_callback' => array( $this, 'webhook_permissions_check' )
		) );
	}
	
	public function check_frontend_auth() {
		return is_user_logged_in();
	}

	public function webhook_permissions_check( WP_REST_Request $request ) {
		$gateway = sanitize_text_field( $request->get_param( 'gateway' ) );
		
		// Delegate webhook signature verification and permissions check to the specific gateway addon.
		// Addon plugins must filter this value to true (after validating signatures) or return false.
		return apply_filters( "shubx51_webhook_permissions_check_{$gateway}", false, $request );
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
		$gateway = sanitize_text_field( $request->get_param( 'gateway' ) );
		
		// Trigger action for the gateway addon to handle webhook payload processing and record the payment
		do_action( "shubx51_handle_webhook_{$gateway}", $request );
		
		return rest_ensure_response( array( 'status' => 'received' ) );
	}
}
