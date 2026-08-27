<?php
/**
 * Class: REST API Manager
 * Handles registration of REST routes and authentication.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Manager {

	/**
	 * API Namespace for the plugin.
	 */
	const NAMESPACE = 'society-hubx/v1';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'determine_current_user', array( $this, 'determine_current_user_from_token' ), 20 );
		add_filter( 'rest_authentication_errors', array( $this, 'rest_auth_fallback' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Validate mobile auth token and set current user.
	 *
	 * @param int|bool $user_id Current determined user ID.
	 * @return int|bool
	 */
	public function determine_current_user_from_token( $user_id ) {
		if ( $user_id ) {
			return $user_id;
		}

		$token_user_id = self::get_user_id_from_token();
		if ( $token_user_id ) {
			wp_set_current_user( $token_user_id );
			return $token_user_id;
		}

		return $user_id;
	}

	/**
	 * Secondary REST Auth Fallback Hook.
	 *
	 * @param WP_Error|null|bool $result
	 * @return WP_Error|null|bool
	 */
	public function rest_auth_fallback( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( ! is_user_logged_in() ) {
			$token_user_id = self::get_user_id_from_token();
			if ( $token_user_id ) {
				wp_set_current_user( $token_user_id );
			}
		}

		return $result;
	}

	/**
	 * Authenticate incoming REST request and set current user.
	 *
	 * @param WP_REST_Request|null $request
	 * @return bool|WP_Error
	 */
	public static function authenticate_request( $request = null ) {
		if ( is_user_logged_in() && get_current_user_id() > 0 ) {
			return true;
		}

		$auth_result = self::get_user_id_from_token( $request, true );
		if ( is_int( $auth_result ) && $auth_result > 0 ) {
			wp_set_current_user( $auth_result );
			return true;
		}

		$reason = is_string( $auth_result ) ? $auth_result : __( 'Please log in to continue.', 'society-hubx' );
		return new WP_Error( 'rest_not_logged_in', $reason, array( 'status' => 401 ) );
	}

	/**
	 * Extract user ID from token in request, headers, or query params.
	 *
	 * @param WP_REST_Request|null $request
	 * @param bool $return_reason
	 * @return int|bool|string
	 */
	public static function get_user_id_from_token( $request = null, $return_reason = false ) {
		$auth_header = '';
		$source = 'none';

		// 1. Check WP_REST_Request object directly
		if ( $request instanceof WP_REST_Request ) {
			$auth_header = $request->get_header( 'authorization' );
			if ( ! empty( $auth_header ) ) {
				$source = 'request_authorization_header';
			} else {
				$auth_header = $request->get_header( 'x-shubx-auth' );
				if ( ! empty( $auth_header ) ) {
					$source = 'request_x_shubx_auth_header';
				} else {
					$auth_header = $request->get_header( 'x-society-token' );
					if ( ! empty( $auth_header ) ) {
						$source = 'request_x_society_token_header';
					} else {
						$auth_header = $request->get_param( 'shubx_token' );
						if ( ! empty( $auth_header ) ) {
							$source = 'request_param_shubx_token';
						}
					}
				}
			}
		}

		// 2. Check getallheaders() (Handles Apache / LiteSpeed / FastCGI)
		if ( empty( $auth_header ) && function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			if ( is_array( $headers ) ) {
				foreach ( $headers as $key => $value ) {
					$lower_key = strtolower( $key );
					if ( in_array( $lower_key, array( 'authorization', 'x-shubx-auth', 'x-society-token' ), true ) ) {
						$auth_header = sanitize_text_field( $value );
						$source = "getallheaders_{$lower_key}";
						break;
					}
				}
			}
		}

		// 3. Check $_SERVER variables
		if ( empty( $auth_header ) ) {
			if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
				$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
				$source = 'server_http_authorization';
			} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
				$auth_header = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
				$source = 'server_redirect_http_authorization';
			} elseif ( ! empty( $_SERVER['HTTP_X_SHUBX_AUTH'] ) ) {
				$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SHUBX_AUTH'] ) );
				$source = 'server_http_x_shubx_auth';
			} elseif ( ! empty( $_SERVER['HTTP_X_SOCIETY_TOKEN'] ) ) {
				$auth_header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SOCIETY_TOKEN'] ) );
				$source = 'server_http_x_society_token';
			}
		}

		// 4. Fallback to $_REQUEST
		if ( empty( $auth_header ) && ! empty( $_REQUEST['shubx_token'] ) ) {
			$auth_header = sanitize_text_field( wp_unslash( $_REQUEST['shubx_token'] ) );
			$source = 'request_global_shubx_token';
		}

		if ( empty( $auth_header ) ) {
			error_log( '[SHUBX Mobile Auth] No authentication token received.' );
			return $return_reason ? __( 'No authentication token provided.', 'society-hubx' ) : false;
		}

		$token = trim( str_ireplace( 'Bearer ', '', $auth_header ) );
		$token = trim( $token, "\"' \t\n\r\0\x0B" );
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			error_log( "[SHUBX Mobile Auth] Malformed token from [{$source}]. Expected 2 parts separated by dot." );
			return $return_reason ? __( 'Malformed authentication token.', 'society-hubx' ) : false;
		}

		$payload_encoded = $parts[0];
		$signature = $parts[1];

		// Check 1: Verify HMAC directly on encoded payload
		$expected_signature = hash_hmac( 'sha256', $payload_encoded, wp_salt( 'auth' ) );
		$is_valid = hash_equals( $expected_signature, $signature );

		// Check 2: Backward compatibility for legacy raw JSON HMAC
		if ( ! $is_valid ) {
			$raw_payload = base64_decode( strtr( $payload_encoded, '-_', '+/' ) );
			if ( $raw_payload ) {
				$legacy_sig = hash_hmac( 'sha256', $raw_payload, wp_salt( 'auth' ) );
				$is_valid = hash_equals( $legacy_sig, $signature );
			}
		}

		if ( ! $is_valid ) {
			error_log( "[SHUBX Mobile Auth] Token signature mismatch from [{$source}]." );
			return $return_reason ? __( 'Invalid authentication token signature.', 'society-hubx' ) : false;
		}

		// Decode URL-safe Base64 payload
		$payload_json = base64_decode( strtr( $payload_encoded, '-_', '+/' ) );
		$payload = json_decode( $payload_json, true );
		if ( empty( $payload['user_id'] ) || empty( $payload['expires'] ) ) {
			error_log( "[SHUBX Mobile Auth] Corrupt token payload from [{$source}]." );
			return $return_reason ? __( 'Corrupt authentication token payload.', 'society-hubx' ) : false;
		}

		if ( time() > intval( $payload['expires'] ) ) {
			error_log( "[SHUBX Mobile Auth] Token expired for user {$payload['user_id']} at {$payload['expires']} (current: " . time() . ')' );
			return $return_reason ? __( 'Authentication token has expired.', 'society-hubx' ) : false;
		}

		error_log( "[SHUBX Mobile Auth] Successfully authenticated user {$payload['user_id']} ({$payload['username']}) via [{$source}]." );
		return (int) $payload['user_id'];
	}

	/**
	 * Register all plugin REST routes.
	 */
	public function register_routes() {
		// Include controller classes
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-discovery-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-auth-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-flats-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-residents-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-vehicles-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-documents-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-facilities-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-finance-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-assets-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-notices-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-polls-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-staff-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-rules-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-requests-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-notifications-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-activity-controller.php';
		require_once SHUBX51_PLUGIN_DIR . 'includes/rest/class-rest-payments-controller.php';

		// Instantiate & register
		( new SHUBX51_REST_Discovery_Controller() )->register_routes();
		( new SHUBX51_REST_Auth_Controller() )->register_routes();
		( new SHUBX51_REST_Flats_Controller() )->register_routes();
		( new SHUBX51_REST_Residents_Controller() )->register_routes();
		( new SHUBX51_REST_Vehicles_Controller() )->register_routes();
		( new SHUBX51_REST_Documents_Controller() )->register_routes();
		( new SHUBX51_REST_Facilities_Controller() )->register_routes();
		( new SHUBX51_REST_Finance_Controller() )->register_routes();
		( new SHUBX51_REST_Assets_Controller() )->register_routes();
		( new SHUBX51_REST_Notices_Controller() )->register_routes();
		( new SHUBX51_REST_Polls_Controller() )->register_routes();
		( new SHUBX51_REST_Staff_Controller() )->register_routes();
		( new SHUBX51_REST_Rules_Controller() )->register_routes();
		( new SHUBX51_REST_Requests_Controller() )->register_routes();
		( new SHUBX51_REST_Notifications_Controller() )->register_routes();
		( new SHUBX51_REST_Activity_Controller() )->register_routes();
		( new SHUBX51_REST_Payments_Controller() )->register_routes();
	}

	/**
	 * Basic Permission Callback.
	 * Checks if the user is logged in and has the necessary society capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public static function check_permission( $request ) {
		// API Key authentication can be added here
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_unauthorized', __( 'You must be logged in to access this endpoint.', 'society-hubx' ), array( 'status' => 401 ) );
		}

		return true;
	}
}
