<?php
/**
 * Class: REST Finance Controller
 * Endpoints for managing Invoices, Payments, and Expenses.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Finance_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'namma-society/v1';

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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_invoice' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
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
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_invoice' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_invoice' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
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

		register_rest_route(
			$this->namespace,
			'/invoices/adhoc',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_adhoc_invoices' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/invoices/(?P<id>[\w-]+)/record-payment',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'record_invoice_payment' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		// Finance Overview, Ledger & Reconciliation Routes
		register_rest_route(
			$this->namespace,
			'/finance/overview',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_finance_overview' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/finance/ledger',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_finance_ledger' ),
					'permission_callback' => array( $this, 'finance_view_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/finance/reconcile',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reconcile_funds' ),
					'permission_callback' => array( $this, 'finance_manage_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/finance/monthly-summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_monthly_summary' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
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
		$db = new NAMMASOCIETY51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'load_relations' => true ) );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
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
		$db = new NAMMASOCIETY51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'id' => $id ) );

		if ( empty( $invoices ) ) {
			return new WP_Error( 'rest_invoice_not_found', __( 'Invoice not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $invoices[0] );
	}

	/**
	 * Create single custom invoice.
	 */
	public function create_invoice( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$flat_no = isset( $params['flat_no'] ) ? sanitize_text_field( $params['flat_no'] ) : '';
		$amount  = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;
		$month   = isset( $params['month'] ) ? sanitize_text_field( $params['month'] ) : gmdate( 'F Y' );

		if ( empty( $flat_no ) || $amount <= 0 ) {
			return new WP_Error( 'rest_invalid_params', __( 'Flat number and valid amount are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$data = array(
			'id'            => uniqid( 'inv_' ),
			'block'         => isset( $params['block'] ) ? sanitize_text_field( $params['block'] ) : '',
			'flat_no'       => $flat_no,
			'resident_name' => isset( $params['resident_name'] ) ? sanitize_text_field( $params['resident_name'] ) : '',
			'amount'        => $amount,
			'total_paid'    => isset( $params['total_paid'] ) ? floatval( $params['total_paid'] ) : 0.00,
			'month'         => $month,
			'type'          => isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : 'maintenance',
			'status'        => isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'unpaid',
			'due_date'      => isset( $params['due_date'] ) ? sanitize_text_field( $params['due_date'] ) : gmdate( 'Y-m-d', strtotime( '+15 days' ) ),
			'description'   => isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '',
			'payment_ref'   => isset( $params['payment_ref'] ) ? sanitize_text_field( $params['payment_ref'] ) : '',
			'payments'      => '[]',
			'created_at'    => current_time( 'mysql' ),
		);

		$result = $db->insert( 'invoices', $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $data['id'], 'invoice' => $data ), 201 );
	}

	/**
	 * Update invoice.
	 */
	public function update_invoice( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'invoices', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_invoice_not_found', __( 'Invoice not found.', 'namma-society' ), array( 'status' => 404 ) );
		}

		$data = array();
		if ( isset( $params['amount'] ) ) {
			$data['amount'] = floatval( $params['amount'] );
		}
		if ( isset( $params['total_paid'] ) ) {
			$data['total_paid'] = floatval( $params['total_paid'] );
		}
		if ( isset( $params['status'] ) ) {
			$data['status'] = sanitize_text_field( $params['status'] );
		}
		if ( isset( $params['due_date'] ) ) {
			$data['due_date'] = sanitize_text_field( $params['due_date'] );
		}
		if ( isset( $params['type'] ) ) {
			$data['type'] = sanitize_text_field( $params['type'] );
		}
		if ( isset( $params['month'] ) ) {
			$data['month'] = sanitize_text_field( $params['month'] );
		}
		if ( isset( $params['description'] ) ) {
			$data['description'] = sanitize_textarea_field( $params['description'] );
		}
		if ( isset( $params['payment_ref'] ) ) {
			$data['payment_ref'] = sanitize_text_field( $params['payment_ref'] );
		}
		if ( isset( $params['resident_name'] ) ) {
			$data['resident_name'] = sanitize_text_field( $params['resident_name'] );
		}
		if ( isset( $params['block'] ) ) {
			$data['block'] = sanitize_text_field( $params['block'] );
		}
		if ( isset( $params['flat_no'] ) ) {
			$data['flat_no'] = sanitize_text_field( $params['flat_no'] );
		}

		$result = $db->update( 'invoices', $data, array( 'id' => $id ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Invoice updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete invoice.
	 */
	public function delete_invoice( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'invoices', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Invoice deleted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Batch generate monthly invoices.
	 */
	public function generate_invoices( $request ) {
		$params = $request->get_json_params();
		$month = isset( $params['month'] ) ? sanitize_text_field( $params['month'] ) : gmdate( 'F Y' );
		$amount = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;
		$due_date = isset( $params['due_date'] ) ? sanitize_text_field( $params['due_date'] ) : gmdate( 'Y-m-d', strtotime( '+15 days' ) );

		$account_mgr = new NAMMASOCIETY51_Account_Manager();
		$count = $account_mgr->generate_monthly_invoices( $month, $amount, $due_date );

		return rest_ensure_response( array( 'success' => true, 'message' => sprintf( __( '%d invoices generated successfully.', 'namma-society' ), $count ) ) );
	}

	/**
	 * Get Payments.
	 */
	public function get_payments( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$payments = $db->get( 'payments' );

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
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
			return new WP_Error( 'rest_invalid_params', __( 'Valid invoice ID and amount are required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		$is_admin = $rbac->has_capability( $user_id, 'finance_manage' ) || current_user_can( 'manage_options' );

		$db = new NAMMASOCIETY51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'id' => $invoice_id ) );
		if ( empty( $invoices ) ) {
			return new WP_Error( 'rest_invoice_not_found', __( 'Invoice not found.', 'namma-society' ), array( 'status' => 404 ) );
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
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
			$rm = new NAMMASOCIETY51_Request_Manager();
			$rm->create_request( 'finance', 'submit_payment_request', $payment_data, $payment_data['id'] );
		}

		return new WP_REST_Response( array( 'success' => true, 'id' => $payment_data['id'], 'payment' => $payment_data ), 201 );
	}

	/**
	 * Delete Payment.
	 */
	public function delete_payment( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->delete( 'payments', array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Payment deleted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Get Expenses.
	 */
	public function get_expenses( $request ) {
		$db = new NAMMASOCIETY51_DB_Router();
		$expenses = $db->get( 'expenses' );
		return rest_ensure_response( $expenses ? $expenses : array() );
	}

	/**
	 * Get single expense.
	 */
	public function get_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$expenses = $db->get( 'expenses', array( 'id' => $id ) );

		if ( empty( $expenses ) ) {
			return new WP_Error( 'rest_expense_not_found', __( 'Expense not found.', 'namma-society' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'rest_invalid_params', __( 'Category and amount are required.', 'namma-society' ), array( 'status' => 400 ) );
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

		$db = new NAMMASOCIETY51_DB_Router();
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

		$db = new NAMMASOCIETY51_DB_Router();
		$existing = $db->get( 'expenses', array( 'id' => $id ) );
		if ( empty( $existing ) ) {
			return new WP_Error( 'rest_expense_not_found', __( 'Expense not found.', 'namma-society' ), array( 'status' => 404 ) );
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

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense updated successfully.', 'namma-society' ) ) );
	}

	/**
	 * Delete / Archive expense.
	 */
	public function delete_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update( 'expenses', array( 'status' => 'archived' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense deleted successfully.', 'namma-society' ) ) );
	}

	/**
	 * Approve expense.
	 */
	public function approve_expense( $request ) {
		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$db = new NAMMASOCIETY51_DB_Router();
		$result = $db->update( 'expenses', array( 'status' => 'approved' ), array( 'id' => $id ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Expense approved successfully.', 'namma-society' ) ) );
	}

	/**
	 * Get aggregated Finance Overview KPIs & Charts.
	 */
	public function get_finance_overview( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_Ledger_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		}

		$selected_year = sanitize_text_field( $request->get_param( 'year' ) );
		if ( empty( $selected_year ) ) {
			$selected_year = wp_date( 'Y' );
		}

		$db = new NAMMASOCIETY51_DB_Router();
		$invoices = $db->get( 'invoices', array( 'load_relations' => true ) );
		$ledger_mgr = new NAMMASOCIETY51_Ledger_Manager();
		$ledger_entries = $ledger_mgr->get_ledger_entries( $selected_year );

		$total_credit = 0;
		$total_debit = 0;
		$opening_bank = floatval( get_option( 'nammasociety51_opening_bank_' . $selected_year, 0 ) );
		$opening_cash = floatval( get_option( 'nammasociety51_opening_cash_' . $selected_year, 0 ) );

		foreach ( $ledger_entries as $e ) {
			if ( ( $e['type'] ?? '' ) === 'Credit' ) {
				$total_credit += floatval( $e['amount'] ?? 0 );
			} elseif ( ( $e['type'] ?? '' ) === 'Debit' ) {
				$total_debit += floatval( $e['amount'] ?? 0 );
			}
		}

		$last_entry = ! empty( $ledger_entries ) ? end( $ledger_entries ) : null;
		$net_balance = ( floatval( $last_entry['bank_balance'] ?? 0 ) ) + ( floatval( $last_entry['cash_balance'] ?? 0 ) );

		$actual_bank = floatval( get_option( 'nammasociety51_actual_bank_' . $selected_year, 0 ) );
		$actual_cash = floatval( get_option( 'nammasociety51_actual_cash_' . $selected_year, 0 ) );
		$actual_total = $actual_bank + $actual_cash;
		$variance = $actual_total - $net_balance;

		// Demand vs Collected
		$total_demand = 0;
		$total_collected = 0;
		$paid_count = 0;
		$unpaid_count = 0;
		$partial_count = 0;

		foreach ( $invoices as $inv ) {
			if ( ( $inv['status'] ?? '' ) === 'pending_total' ) {
				continue;
			}
			$inv_year = wp_date( 'Y', strtotime( $inv['month'] ?? $inv['created_at'] ?? '' ) );
			if ( $inv_year == $selected_year ) {
				$total_demand += floatval( $inv['amount'] ?? 0 );
				$collected_this = 0;
				if ( ! empty( $inv['payments'] ) && is_array( $inv['payments'] ) ) {
					foreach ( $inv['payments'] as $p ) {
						if ( wp_date( 'Y', strtotime( $p['date'] ?? '' ) ) == $selected_year ) {
							$collected_this += floatval( $p['amount'] ?? 0 );
						}
					}
				}
				if ( $collected_this == 0 && strtolower( $inv['status'] ?? '' ) === 'paid' ) {
					$collected_this = floatval( $inv['amount'] ?? 0 );
				}
				$total_collected += $collected_this;

				$inv_status = strtolower( $inv['status'] ?? 'unpaid' );
				if ( $inv_status === 'paid' ) {
					$paid_count++;
				} elseif ( $inv_status === 'partial' || $inv_status === 'partially paid' ) {
					$partial_count++;
				} else {
					$unpaid_count++;
				}
			}
		}

		$collection_pct = ( $total_demand > 0 ) ? round( ( $total_collected / $total_demand ) * 100 ) : 0;

		// Monthly cash flow
		$monthly_data = array();
		foreach ( $ledger_entries as $entry ) {
			$month = wp_date( 'M Y', strtotime( $entry['date'] ?? '' ) );
			if ( ! isset( $monthly_data[ $month ] ) ) {
				$monthly_data[ $month ] = array( 'income' => 0, 'expense' => 0, 'net' => 0 );
			}
			if ( ( $entry['type'] ?? '' ) === 'Credit' ) {
				$monthly_data[ $month ]['income'] += floatval( $entry['amount'] ?? 0 );
			} elseif ( ( $entry['type'] ?? '' ) === 'Debit' ) {
				$monthly_data[ $month ]['expense'] += floatval( $entry['amount'] ?? 0 );
			}
		}
		foreach ( $monthly_data as $m => &$m_val ) {
			$m_val['net'] = $m_val['income'] - $m_val['expense'];
		}
		unset( $m_val );

		// Category data
		$category_data = array();
		foreach ( $ledger_entries as $e ) {
			if ( ( $e['type'] ?? '' ) === 'Debit' ) {
				$cat = ! empty( $e['category'] ) ? $e['category'] : 'Others';
				if ( ! isset( $category_data[ $cat ] ) ) {
					$category_data[ $cat ] = 0;
				}
				$category_data[ $cat ] += floatval( $e['amount'] ?? 0 );
			}
		}

		$live_bal = $ledger_mgr->get_current_balance();

		return rest_ensure_response( array(
			'year'            => $selected_year,
			'total_credit'    => $total_credit,
			'total_debit'     => $total_debit,
			'total_demand'    => $total_demand,
			'total_collected' => $total_collected,
			'collection_pct'  => $collection_pct,
			'net_balance'     => $net_balance,
			'live_balance'    => $live_bal['total'] ?? $net_balance,
			'actual_bank'     => $actual_bank,
			'actual_cash'     => $actual_cash,
			'actual_total'    => $actual_total,
			'variance'        => $variance,
			'monthly_data'    => $monthly_data,
			'collection_data' => array(
				'paid'    => $paid_count,
				'unpaid'  => $unpaid_count,
				'partial' => $partial_count,
			),
			'category_data'   => $category_data,
		) );
	}

	/**
	 * Get double-entry Money Flow Ledger.
	 */
	public function get_finance_ledger( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_Ledger_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		}

		$selected_year = sanitize_text_field( $request->get_param( 'year' ) );
		if ( empty( $selected_year ) ) {
			$selected_year = wp_date( 'Y' );
		}

		$ledger_mgr = new NAMMASOCIETY51_Ledger_Manager();
		$entries = $ledger_mgr->get_ledger_entries( $selected_year );

		return rest_ensure_response( $entries ? $entries : array() );
	}

	/**
	 * Get monthly flat maintenance summary (web transparency parity).
	 */
	public function get_monthly_summary( $request ) {
		$month = $request->get_param( 'month' );
		if ( ! $month ) {
			$month = gmdate( 'Y-m' );
		}

		if ( ! class_exists( 'NAMMASOCIETY51_Ledger_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		}

		$ledger = new NAMMASOCIETY51_Ledger_Manager();
		$summary = $ledger->get_monthly_summary( $month );
		return rest_ensure_response( $summary ? $summary : array() );
	}

	/**
	 * Reconcile physical funds with system balances.
	 */
	public function reconcile_funds( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_Ledger_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-ledger-manager.php';
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$year = isset( $params['year'] ) ? sanitize_text_field( $params['year'] ) : wp_date( 'Y' );
		$bank = isset( $params['actual_bank'] ) ? floatval( $params['actual_bank'] ) : 0;
		$cash = isset( $params['actual_cash'] ) ? floatval( $params['actual_cash'] ) : 0;
		$opening_bank = isset( $params['opening_bank'] ) ? floatval( $params['opening_bank'] ) : null;
		$opening_cash = isset( $params['opening_cash'] ) ? floatval( $params['opening_cash'] ) : null;

		update_option( 'nammasociety51_actual_bank_' . $year, $bank );
		update_option( 'nammasociety51_actual_cash_' . $year, $cash );
		if ( $opening_bank !== null ) {
			update_option( 'nammasociety51_opening_bank_' . $year, $opening_bank );
		}
		if ( $opening_cash !== null ) {
			update_option( 'nammasociety51_opening_cash_' . $year, $opening_cash );
		}

		$ledger_mgr = new NAMMASOCIETY51_Ledger_Manager();
		$ledger_entries = $ledger_mgr->get_ledger_entries( $year );
		$last_entry = ! empty( $ledger_entries ) ? end( $ledger_entries ) : null;
		$net_balance = ( floatval( $last_entry['bank_balance'] ?? 0 ) ) + ( floatval( $last_entry['cash_balance'] ?? 0 ) );
		$actual_total = $bank + $cash;
		$variance = $actual_total - $net_balance;

		return rest_ensure_response( array(
			'success'      => true,
			'message'      => __( 'Funds reconciled successfully.', 'namma-society' ),
			'actual_bank'  => $bank,
			'actual_cash'  => $cash,
			'actual_total' => $actual_total,
			'net_balance'  => $net_balance,
			'variance'     => $variance,
		) );
	}

	/**
	 * Generate Ad-hoc collection invoices across all active flats.
	 */
	public function generate_adhoc_invoices( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_Account_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$month = isset( $params['month'] ) ? sanitize_text_field( $params['month'] ) : wp_date( 'Y-m' );
		$amount = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;
		$description = isset( $params['description'] ) ? sanitize_text_field( $params['description'] ) : 'Ad-hoc Collection';
		$due_date = isset( $params['due_date'] ) ? sanitize_text_field( $params['due_date'] ) : wp_date( 'Y-m-d', strtotime( '+7 days' ) );

		if ( $amount <= 0 ) {
			return new WP_Error( 'rest_invalid_amount', __( 'Valid ad-hoc amount is required.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$account_mgr = new NAMMASOCIETY51_Account_Manager();
		$count = $account_mgr->perform_bulk_invoice_generation( $month, $amount, 'adhoc', $due_date, $description );

		return rest_ensure_response( array(
			'success' => true,
			'count'   => $count,
			'message' => sprintf( __( '%d ad-hoc invoices created successfully.', 'namma-society' ), $count ),
		) );
	}

	/**
	 * Record an offline payment on an invoice.
	 */
	public function record_invoice_payment( $request ) {
		if ( ! class_exists( 'NAMMASOCIETY51_Account_Manager' ) ) {
			require_once NAMMASOCIETY51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
		}

		$id = sanitize_text_field( $request->get_param( 'id' ) );
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$amount = isset( $params['amount'] ) ? floatval( $params['amount'] ) : 0.00;
		$method = isset( $params['method'] ) ? sanitize_text_field( $params['method'] ) : 'UPI';
		$date = isset( $params['date'] ) ? sanitize_text_field( $params['date'] ) : wp_date( 'Y-m-d' );
		$reference = isset( $params['reference'] ) ? sanitize_text_field( $params['reference'] ) : '-';

		if ( $amount <= 0 ) {
			return new WP_Error( 'rest_invalid_amount', __( 'Payment amount must be greater than zero.', 'namma-society' ), array( 'status' => 400 ) );
		}

		$account_mgr = new NAMMASOCIETY51_Account_Manager();
		$res = $account_mgr->perform_record_payment( array(
			'invoice_id' => $id,
			'amount'     => $amount,
			'method'     => $method,
			'date'       => $date,
			'reference'  => $reference,
		) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Payment recorded successfully.', 'namma-society' ),
		) );
	}

	public function user_logged_in_check( $request ) {
		return NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
	}

	public function finance_view_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'finance_view' ) || current_user_can( 'manage_options' );
	}

	public function finance_manage_check( $request ) {
		NAMMASOCIETY51_REST_Manager::authenticate_request( $request );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		return $rbac->has_capability( get_current_user_id(), 'finance_manage' ) || current_user_can( 'manage_options' );
	}
}

