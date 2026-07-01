<?php
/**
 * Class: Google API Handler
 * Handles OAuth2 Authentication and API Requests via REST.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Google_API_Handler {

	/**
	 * API Scopes required for the plugin.
	 * Drive: File management.
	 * Spreadsheets: Database operations.
	 */
	const SCOPES = array(
		'https://www.googleapis.com/auth/drive',
		'https://www.googleapis.com/auth/spreadsheets',
	);

	/**
	 * Get the OAuth2 Authorization URL.
	 */
	public static function get_auth_url() {
		$client_id = get_option( 'shubx51_google_client_id' );
		if ( ! $client_id ) {
			return false;
		}

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => admin_url( 'admin.php?page=shubx51-settings' ),
			'scope'         => implode( ' ', self::SCOPES ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
		);

		return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( $params );
	}

	/**
	 * Exchange Auth Code for Access Token.
	 *
	 * @param string $code The authorization code from the callback.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function exchange_code_for_token( $code ) {
		$client_id     = get_option( 'shubx51_google_client_id' );
		$client_secret = get_option( 'shubx51_google_client_secret' );

		if ( ! $client_id || ! $client_secret ) {
			return new WP_Error( 'missing_creds', 'Client ID or Secret missing.' );
		}

		$body = array(
			'code'          => $code,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri'  => admin_url( 'admin.php?page=shubx51-settings' ),
			'grant_type'    => 'authorization_code',
		);

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'auth_error', $data['error_description'] ?? 'Unknown error' );
		}

		self::save_token_data( $data );

		return true;
	}

	/**
	 * Refresh the Access Token using the Refresh Token.
	 *
	 * @return string|bool Access token or false.
	 */
	public static function refresh_access_token() {
		$refresh_token = get_option( 'shubx51_google_refresh_token' );
		$client_id     = get_option( 'shubx51_google_client_id' );
		$client_secret = get_option( 'shubx51_google_client_secret' );

		if ( ! $refresh_token || ! $client_id || ! $client_secret ) {
			return false;
		}

		$body = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		);

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['access_token'] ) ) {
			self::save_token_data( $data );
			return $data['access_token'];
		}

		return false;
	}

	/**
	 * Save token data to database.
	 */
	private static function save_token_data( $data ) {
		if ( isset( $data['access_token'] ) ) {
			update_option( 'shubx51_google_access_token', $data['access_token'] );
			update_option( 'shubx51_token_expires_at', time() + $data['expires_in'] );
		}
		if ( isset( $data['refresh_token'] ) ) {
			update_option( 'shubx51_google_refresh_token', $data['refresh_token'] );
		}
	}

	/**
	 * Get a valid Access Token (Refreshes if needed).
	 */
	public static function get_valid_token() {
		$token      = get_option( 'shubx51_google_access_token' );
		$expires_at = get_option( 'shubx51_token_expires_at' );

		if ( $token && $expires_at > time() + 30 ) {
			return $token;
		}

		return self::refresh_access_token();
	}

	/**
	 * Make an Authenticated GET Request.
	 */
	public static function api_request( $endpoint, $method = 'GET', $body = null ) {
		$token = self::get_valid_token();
		if ( ! $token ) {
			return new WP_Error( 'auth_required', 'Authentication required. Please connect Google Account.' );
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'method'  => $method,
		);

		if ( $body ) {
			$args['body'] = json_encode( $body );
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'api_error', 'API Error ' . $code, wp_remote_retrieve_body( $response ) );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
