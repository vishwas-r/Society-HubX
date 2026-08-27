<?php
/**
 * Class: REST Auth Controller
 * Mobile & client authentication, user profile hydration, and session validation.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Auth_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'society-hubx/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'auth';

	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/login',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/me',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_current_user_profile' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_current_user_profile' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/logout',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'logout' ),
					'permission_callback' => array( $this, 'user_logged_in_check' ),
				),
			)
		);
	}

	/**
	 * Handle Login.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function login( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$username = isset( $params['username'] ) ? sanitize_text_field( $params['username'] ) : '';
		$password = isset( $params['password'] ) ? $params['password'] : '';

		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'rest_invalid_credentials', __( 'Username/Email and password are required.', 'society-hubx' ), array( 'status' => 400 ) );
		}

		// Support login by email or username
		$user = is_email( $username ) ? get_user_by( 'email', $username ) : get_user_by( 'login', $username );

		if ( ! $user ) {
			// Check if phone number was entered
			$db = new SHUBX51_DB_Router();
			$residents = $db->get( 'residents', array( 'phone' => $username ) );
			if ( ! empty( $residents ) && ! empty( $residents[0]['wp_user_id'] ) ) {
				$user = get_user_by( 'id', (int) $residents[0]['wp_user_id'] );
			}
		}

		if ( ! $user ) {
			return new WP_Error( 'rest_user_not_found', __( 'No user account found with the provided credentials.', 'society-hubx' ), array( 'status' => 404 ) );
		}

		$authenticated = wp_authenticate( $user->user_login, $password );

		if ( is_wp_error( $authenticated ) ) {
			return new WP_Error( 'rest_auth_failed', __( 'Invalid username or password.', 'society-hubx' ), array( 'status' => 401 ) );
		}

		// Generate authentication token for mobile API (URL-safe Base64)
		$token_payload = array(
			'user_id'   => $user->ID,
			'username'  => $user->user_login,
			'issued_at' => time(),
			'expires'   => time() + ( 30 * DAY_IN_SECONDS ),
		);
		$payload_encoded = rtrim( strtr( base64_encode( json_encode( $token_payload ) ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $payload_encoded, wp_salt( 'auth' ) );
		$auth_token = $payload_encoded . '.' . $signature;

		// Set auth cookie for session compatibility
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		$profile_data = $this->build_user_profile( $user->ID );
		$profile_data['token'] = $auth_token;

		return new WP_REST_Response( array( 'success' => true, 'data' => $profile_data ), 200 );
	}

	/**
	 * Get Current User Profile.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_current_user_profile( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		$profile_data = $this->build_user_profile( $user_id );
		return rest_ensure_response( array( 'success' => true, 'data' => $profile_data ) );
	}

	/**
	 * Update current user profile and linked resident record.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_current_user_profile( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'rest_not_logged_in', __( 'You must be logged in to update your profile.', 'society-hubx' ), array( 'status' => 401 ) );
		}

		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$userdata = array( 'ID' => $user_id );
		if ( isset( $params['display_name'] ) ) {
			$userdata['display_name'] = sanitize_text_field( $params['display_name'] );
		}
		if ( isset( $params['email'] ) && is_email( $params['email'] ) ) {
			$userdata['user_email'] = sanitize_email( $params['email'] );
		}
		if ( count( $userdata ) > 1 ) {
			wp_update_user( $userdata );
		}

		// Update corresponding resident record if exists
		$db = new SHUBX51_DB_Router();
		$resident = $db->get_resident_by_wp_id( $user_id );
		if ( $resident && ! empty( $resident['id'] ) ) {
			$res_update = array();
			if ( isset( $params['display_name'] ) || isset( $params['name'] ) ) {
				$res_update['name'] = sanitize_text_field( $params['display_name'] ?? $params['name'] );
			}
			if ( isset( $params['phone'] ) ) {
				$res_update['phone'] = sanitize_text_field( $params['phone'] );
			}
			if ( isset( $params['email'] ) ) {
				$res_update['email'] = sanitize_email( $params['email'] );
			}
			if ( isset( $params['emergency_contact'] ) ) {
				$res_update['emergency_contact'] = sanitize_text_field( $params['emergency_contact'] );
			}
			if ( ! empty( $res_update ) ) {
				$db->update( 'residents', $res_update, array( 'id' => $resident['id'] ) );
			}
		}

		$profile_data = $this->build_user_profile( $user_id );
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Profile updated successfully.', 'society-hubx' ), 'data' => $profile_data ) );
	}

	/**
	 * Build comprehensive profile payload.
	 *
	 * @param int $user_id WordPress User ID.
	 * @return array
	 */
	private function build_user_profile( $user_id ) {
		$user = get_userdata( $user_id );
		$db = new SHUBX51_DB_Router();
		$rbac = new SHUBX51_RBAC_Manager();

		$resident = $db->get_resident_by_wp_id( $user_id );
		$is_admin = current_user_can( 'manage_options' ) || $rbac->has_capability( $user_id, 'residents_manage' );

		$capabilities = array(
			'can_manage_flats'      => $rbac->has_capability( $user_id, 'flats_manage' ) || $is_admin,
			'can_manage_residents'  => $rbac->has_capability( $user_id, 'residents_manage' ) || $is_admin,
			'can_manage_finance'    => $rbac->has_capability( $user_id, 'finance_manage' ) || $is_admin,
			'can_view_finance'      => $rbac->has_capability( $user_id, 'finance_view' ) || $is_admin,
			'can_manage_facilities' => $rbac->has_capability( $user_id, 'facilities_manage' ) || $is_admin,
			'can_manage_staff'      => $rbac->has_capability( $user_id, 'staff_manage' ) || $is_admin,
			'can_manage_notices'    => $rbac->has_capability( $user_id, 'notices_manage' ) || $is_admin,
			'can_manage_polls'      => $rbac->has_capability( $user_id, 'polls_manage' ) || $is_admin,
			'can_manage_rules'      => $rbac->has_capability( $user_id, 'rules_manage' ) || $is_admin,
			'can_manage_documents'  => $rbac->has_capability( $user_id, 'documents_manage' ) || $is_admin,
			'can_manage_assets'     => $rbac->has_capability( $user_id, 'assets_manage' ) || $is_admin,
			'can_manage_requests'   => $rbac->has_capability( $user_id, 'requests_manage' ) || $is_admin,
		);

		$flat_no = $resident['flat_no'] ?? '';
		$block = $resident['block'] ?? '';
		$unit_type = $resident['type'] ?? ( $is_admin ? 'Admin' : 'Resident' );

		// Check maintenance balance if resident
		$balance = 0.00;
		if ( ! empty( $flat_no ) ) {
			$invoices = $db->get( 'invoices', array( 'flat_no' => $flat_no ) );
			foreach ( $invoices as $inv ) {
				if ( ( $inv['status'] ?? '' ) !== 'Paid' && ( $inv['status'] ?? '' ) !== 'paid' ) {
					$due = floatval( $inv['amount'] ?? 0 ) - floatval( $inv['total_paid'] ?? ( $inv['paid_amount'] ?? 0 ) );
					if ( $due > 0 ) {
						$balance += $due;
					}
				}
			}
		}

		return array(
			'user' => array(
				'id'           => $user_id,
				'username'     => $user ? $user->user_login : '',
				'display_name' => $user ? $user->display_name : '',
				'email'        => $user ? $user->user_email : '',
				'avatar_url'   => get_avatar_url( $user_id ),
				'roles'        => $user ? (array) $user->roles : array(),
				'is_admin'     => $is_admin,
			),
			'resident' => array(
				'id'                  => $resident['id'] ?? null,
				'name'                => $resident['name'] ?? ( $user ? $user->display_name : '' ),
				'flat_no'             => $flat_no,
				'block'               => $block,
				'type'                => $unit_type,
				'phone'               => $resident['phone'] ?? '',
				'status'              => $resident['status'] ?? 'active',
				'maintenance_balance' => $balance,
			),
			'capabilities' => $capabilities,
			'nonce'        => wp_create_nonce( 'wp_rest' ),
		);
	}

	/**
	 * Logout endpoint.
	 */
	public function logout( $request ) {
		wp_logout();
		return rest_ensure_response( array( 'success' => true, 'message' => __( 'Logged out successfully.', 'society-hubx' ) ) );
	}

	public function user_logged_in_check( $request ) {
		return SHUBX51_REST_Manager::authenticate_request( $request );
	}
}
