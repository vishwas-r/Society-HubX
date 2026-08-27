<?php
/**
 * REST API: Settings Controller
 * Handles reading and updating society global configuration and system status.
 *
 * @package SHUBX51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_REST_Settings_Controller {

	/**
	 * Base route.
	 */
	const BASE = 'settings';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			SHUBX51_REST_Manager::NAMESPACE,
			'/' . self::BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'permissions_check_manage' ),
				),
			)
		);
	}

	/**
	 * Permission check: Logged in user with admin/staff capabilities.
	 */
	public function permissions_check( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_unauthorized', __( 'You must be logged in to view settings.', 'society-hubx' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Permission check for updating: Must have settings_manage or manage_options.
	 */
	public function permissions_check_manage( $request ) {
		SHUBX51_REST_Manager::authenticate_request( $request );
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_unauthorized', __( 'You must be logged in to update settings.', 'society-hubx' ), array( 'status' => 401 ) );
		}

		$user_id = get_current_user_id();
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$rbac = new SHUBX51_RBAC_Manager();
		if ( $rbac->has_capability( $user_id, 'settings_manage' ) || $rbac->has_capability( $user_id, 'dashboard_view' ) ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'You do not have permission to manage society settings.', 'society-hubx' ), array( 'status' => 403 ) );
	}

	/**
	 * Get current settings and system diagnostics.
	 */
	public function get_settings( $request ) {
		$db = SHUBX51_Plugin::get_instance()->db;

		$flats      = $db->get( 'flats' ) ?: array();
		$residents  = $db->get( 'residents' ) ?: array();
		$staff      = $db->get( 'staff' ) ?: array();
		$vehicles   = $db->get( 'vehicles' ) ?: array();
		$facilities = $db->get( 'facilities' ) ?: array();

		$data = array(
			'society_name'          => get_option( 'shubx51_society_name', 'My Society' ),
			'society_address_line1' => get_option( 'shubx51_society_address_line1', '' ),
			'society_address_line2' => get_option( 'shubx51_society_address_line2', '' ),
			'society_city'          => get_option( 'shubx51_society_city', '' ),
			'society_pincode'       => get_option( 'shubx51_society_pincode', '' ),
			'society_contact'       => get_option( 'shubx51_society_contact', '' ),
			'color_palette'         => get_option( 'shubx51_color_palette', 'orange' ),
			'default_theme'         => get_option( 'shubx51_default_theme', 'light' ),

			'bank_name'             => get_option( 'shubx51_bank_name', '' ),
			'bank_account'          => get_option( 'shubx51_bank_account', '' ),
			'bank_ifsc'             => get_option( 'shubx51_bank_ifsc', '' ),
			'bank_upi'              => get_option( 'shubx51_bank_upi', '' ),
			'bank_qr'               => get_option( 'shubx51_bank_qr', '' ),
			'maintenance_amount'    => floatval( get_option( 'shubx51_maintenance_amount', 0 ) ),
			'opening_bank'          => floatval( get_option( 'shubx51_opening_bank', 0 ) ),
			'opening_cash'          => floatval( get_option( 'shubx51_opening_cash', 0 ) ),

			'approval_family'       => get_option( 'shubx51_approval_family', 'manual' ),
			'approval_help'         => get_option( 'shubx51_approval_help', 'manual' ),
			'approval_vehicle'      => get_option( 'shubx51_approval_vehicle', 'manual' ),
			'approval_facility'     => get_option( 'shubx51_approval_facility', 'manual' ),

			'enable_audit'          => get_option( 'shubx51_enable_audit', '1' ),
			'log_retention'         => intval( get_option( 'shubx51_log_retention', 90 ) ),
			'privacy_masking'       => get_option( 'shubx51_privacy_masking', '0' ),

			'system_info'           => array(
				'plugin_version'   => defined( 'SHUBX51_VERSION' ) ? SHUBX51_VERSION : '1.0.0',
				'wp_version'       => get_bloginfo( 'version' ),
				'php_version'      => phpversion(),
				'db_storage'       => get_option( 'shubx51_storage_engine', 'json' ),
				'total_flats'      => count( $flats ),
				'total_residents'  => count( $residents ),
				'total_staff'      => count( $staff ),
				'total_vehicles'   => count( $vehicles ),
				'total_facilities' => count( $facilities ),
			),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $data,
			)
		);
	}

	/**
	 * Update society settings.
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params() ?: $request->get_params();

		// Text Fields
		$text_fields = array(
			'society_name'          => 'shubx51_society_name',
			'society_address_line1' => 'shubx51_society_address_line1',
			'society_address_line2' => 'shubx51_society_address_line2',
			'society_city'          => 'shubx51_society_city',
			'society_pincode'       => 'shubx51_society_pincode',
			'society_contact'       => 'shubx51_society_contact',
			'bank_name'             => 'shubx51_bank_name',
			'bank_account'          => 'shubx51_bank_account',
			'bank_ifsc'             => 'shubx51_bank_ifsc',
			'bank_upi'              => 'shubx51_bank_upi',
			'bank_qr'               => 'shubx51_bank_qr',
			'approval_family'       => 'shubx51_approval_family',
			'approval_help'         => 'shubx51_approval_help',
			'approval_vehicle'      => 'shubx51_approval_vehicle',
			'approval_facility'     => 'shubx51_approval_facility',
			'enable_audit'          => 'shubx51_enable_audit',
			'privacy_masking'       => 'shubx51_privacy_masking',
		);

		foreach ( $text_fields as $key => $option_name ) {
			if ( isset( $params[ $key ] ) ) {
				update_option( $option_name, sanitize_text_field( wp_unslash( $params[ $key ] ) ) );
			}
		}

		// Numeric Fields
		if ( isset( $params['maintenance_amount'] ) ) {
			update_option( 'shubx51_maintenance_amount', floatval( $params['maintenance_amount'] ) );
		}
		if ( isset( $params['opening_bank'] ) ) {
			update_option( 'shubx51_opening_bank', floatval( $params['opening_bank'] ) );
		}
		if ( isset( $params['opening_cash'] ) ) {
			update_option( 'shubx51_opening_cash', floatval( $params['opening_cash'] ) );
		}
		if ( isset( $params['log_retention'] ) ) {
			update_option( 'shubx51_log_retention', intval( $params['log_retention'] ) );
		}

		// Palette & Theme
		if ( isset( $params['color_palette'] ) ) {
			$allowed_palettes = array( 'orange', 'indigo', 'emerald', 'ocean', 'rose' );
			$palette = sanitize_key( $params['color_palette'] );
			if ( in_array( $palette, $allowed_palettes, true ) ) {
				update_option( 'shubx51_color_palette', $palette );
			}
		}
		if ( isset( $params['default_theme'] ) ) {
			$allowed_themes = array( 'light', 'dark', 'system' );
			$theme = sanitize_key( $params['default_theme'] );
			if ( in_array( $theme, $allowed_themes, true ) ) {
				update_option( 'shubx51_default_theme', $theme );
			}
		}

		return $this->get_settings( $request );
	}
}
