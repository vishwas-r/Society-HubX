<?php
/**
 * Class: REST Finance Controller
 * Endpoints for managing Invoices, Payments, and Expenses.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Finance_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'society-hubx/v1';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Invoices Routes
		register_rest_route(
			$this->namespace,
			'/invoices',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_invoices' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/invoices/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_invoice' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/invoices/generate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_invoices' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		// Payments Routes
		register_rest_route(
			$this->namespace,
			'/payments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_payments' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_payment' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/payments/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_payment' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		// Expenses Routes
		register_rest_route(
			$this->namespace,
			'/expenses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_expenses' ),
					'permission_callback' => array( $this, 'finance_view_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_expense' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/expenses/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_expense' ),
					'permission_callback' => array( $this, 'finance_view_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_expense' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_expense' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/expenses/(?P<id>[\w-]+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve_expense' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);
	}

	/**
	 * List invoices.
	 */
	public function get_invoices( $request ) {
		$db = new SHUBX51_DB_Router();
		$invoices = $db->get( 'invoices' );

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'finance_view' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$invoices = array_filter(
				$invoices,
				function( $inv ) use ( $user_flat ) {
					return isset( $inv['flat_no'] ) && $inv['flat_no'] === $user_flat;
				}
			);
			$invoices = array_values( $invoices );
		}

		return rest_ensure_response( $invoices ? $invoices : array() );
	}

	/**
	 * Get single invoice.
	 */
	public function get_invoice( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'id' => $id ) );

		if ( empty( $invoices ) ) {
			return new WP_Error( 'rest_invoice_not_found', __( 'Invoice not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $invoices[0] );
	}

	/**
	 * Batch generate monthly invoices.
	 */
	public function generate_invoices( $request ) {
		$params = $request->get_json_params();
		$month = isset( $params['month'] ) ? sanitize_text_field( $params['month'] ) : gmdate( 'F Y' );
		$amount = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;
		$due_date = isset( $params['due_date'] ) ? sanitize_text_field( $params['due_date'] ) : gmdate( 'Y-m-d', strtotime( '+15 days' ) );

		$account_mgr = new SHUBX51_Account_Manager();
		$count = $account_mgr->generate_monthly_invoices( $month, $amount, $due_date );

		return rest_ensure_response( array( 'success' => true, 'message' => sprintf( __( '%d invoices generated successfully.', 'society-hubx' ), $count ) ) );
	}

	/**
	 * Get Payments.
	 */
	public function get_payments( $request ) {
		$db = new SHUBX51_DB_Router();
		$payments = $db->get( 'payments' );

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'finance_view' ) || current_user_can( 'manage_options' );

		if ( ! $is_admin ) {
			$resident = $db->get_resident_by_wp_id( $user_id );
			$user_flat = $resident['flat_no'] ?? '';
			$payments = array_filter(
				$payments,
				function( $p ) use ( $user_flat, $user_id ) {
					return ( isset( $p['flat_no'] ) && $p['flat_no'] === $user_flat ) || ( isset( $p['paid_by'] ) && (int) $p['paid_by'] === $user_id );
				}
			);
			$payments = array_values( $payments );
		}

		return rest_ensure_response( $payments ? $payments : array() );
	}

	/**
	 * Create Payment (Admin or Resident Payment Request).
	 */
	public function create_payment( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$invoice_id = isset( $params['invoice_id'] ) ? sanitize_text_field( $params['invoice_id'] ) : '';
		$amount     = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;

		if ( empty( $invoice_id ) || $amount <= 0 ) {
			return new WP_Error( 'rest_invalid_params', __( 'Valid invoice ID and amount are required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new SHUBX51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'finance_manage' ) || current_user_can( 'manage_options' );

		$db = new SHUBX51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'id' => $invoice_id ) );
		if ( empty( $invoices ) ) {
			return new WP_Error( 'rest_invoice_not_found', __( 'Invoice not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$invoice = $invoices[0];
		$payment_data = array(
			'id'           => uniqid( 'pay_' ),
			'invoice_id'   => $invoice_id,
			'flat_no'      => $invoice['flat_no'],
			'amount'       => $amount,
			'payment_date' => isset( $params['payment_date'] ) ? sanitize_text_field( $params['payment_date'] ) : gmdate( 'Y-m-d' ),
			'method'       => isset( $params['method'] ) ? sanitize_text_field( $params['method'] ) : 'UPI',
			'reference_no' => isset( $params['reference_no'] ) ? sanitize_text_field( $params['reference_no'] ) : '',
			'receipt_no'   => 'REC-' . strtoupper( uniqid() ),
			'status'       => $is_admin ? 'Completed' : 'pending_approval',
			'paid_by'      => $user_id,
			'created_at'   => current_time( 'mysql' ),
		);

		$result = $db->insert( 'payments', $payment_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( $is_admin ) {
			// Update invoice balance
			$new_paid = floatval( $invoice['paid_amount'] ?? 0 ) + $amount;
			$new_status = ( $new_paid >= floatval( $invoice['amount'] ?? 0 ) ) ? 'Paid' : 'Partially Paid';
			$db->update( 'invoices', array( 'paid_amount' => $new_paid, 'status' => $new_status ), array( 'id' => $invoice_id ) );
		} else {
			require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
			$rm = new SHUBX51_Request_Manager();
			$rm->create_request( 'finance', 'submit_payment_request', $payment_data, $payment_data['id'] );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $payment_data['id'], 'payment' => $payment_data ), 201 );
	}

	/**
	 * Delete Payment.
	 */
	public function delete_payment( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$result = $db->delete( 'payments', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Payment deleted successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Get Expenses.
	 */
	public function get_expenses( $request ) {
		$db = new SHUBX51_DB_Router();
		$expenses = $db->get( 'expenses' );
		return rest_ensure_response( $expenses ? $expenses : array() );
	}

	/**
	 * Get single expense.
	 */
	public function get_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$expenses = $db->get( 'expenses', array( 'id' => $id ) );

		if ( empty( $expenses ) ) {
			return new WP_Error( 'rest_expense_not_found', __( 'Expense not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $expenses[0] );
	}

	/**
	 * Create expense.
	 */
	public function create_expense( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$category = isset( $params['category'] ) ? sanitize_text_field( $params['category'] ) : '';
		$amount   = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;

		if ( empty( $category ) || $amount <= 0 ) {
			return new WP_Error( 'rest_invalid_params', __( 'Category and amount are required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		$data = array(
			'id'           => uniqid( 'expense_' ),
			'category'     => $category,
			'description'  => isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : '',
			'amount'       => $amount,
			'date'         => isset( $params['date'] ) ? sanitize_text_field( $params['date'] ) : gmdate( 'Y-m-d' ),
			'payee'        => isset( $params['payee'] ) ? sanitize_text_field( $params['payee'] ) : '',
			'added_by'     => get_current_user_id(),
			'receipt_url'  => isset( $params['receipt_url'] ) ? esc_url_raw( $params['receipt_url'] ) : '',
			'status'       => 'approved',
			'account_type' => isset( $params['account_type'] ) ? sanitize_text_field( $params['account_type'] ) : 'Society General Account',
		);

		$db = new SHUBX51_DB_Router();
		$result = $db->insert( 'expenses', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'expense' => $data ), 201 );
	}

	/**
	 * Update expense.
	 */
	public function update_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new SHUBX51_DB_Router();
		$existing = $db->get( 'expenses', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_expense_not_found', __( 'Expense not found.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['category'] ) ) {
			$data['category'] = sanitize_text_field( $params['category'] );
		}
		if ( isset( $params['description'] ) ) {
			$data['description'] = sanitize_text_field( $params['description'] );
		}
		if ( isset( $params['amount'] ) ) {
			$data['amount'] = floatval( $params['amount'] );
		}
		if ( isset( $params['payee'] ) ) {
			$data['payee'] = sanitize_text_field( $params['payee'] );
		}

		$result = $db->update( 'expenses', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense updated successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Delete / Archive expense.
	 */
	public function delete_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$result = $db->update( 'expenses', array( 'status' => 'archived' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense deleted successfully.', 'society-hubx' ) ) );
	}

	/**
	 * Approve expense.
	 */
	public function approve_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new SHUBX51_DB_Router();
		$result = $db->update( 'expenses', array( 'status' => 'approved' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense approved successfully.', 'society-hubx' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return is_user_logged_in();
	}

	public function finance_view_check( $request ) {
		$rbac = new SHUBX51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'finance_view' ) || current_user_can( 'manage_options' );
	}

	public function finance_manage_check( $request ) {
		$rbac = new SHUBX51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'finance_manage' ) || current_user_can( 'manage_options' );
	}
}
