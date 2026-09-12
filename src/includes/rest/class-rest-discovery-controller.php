<?php
/**
 * Class: REST Discovery Controller
 * Public endpoint for mobile apps to discover apartment branding, modules, and configuration.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_REST_Discovery_Controller extends WP_REST_Controller {

	/**
	 * Namespace for the API.
	 *
	 * @var string
	 */
	protected $namespace = 'namma-society/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'discovery';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_discovery_info' ),
					'permission_callback' => '__return_true', // Public discovery endpoint
				),
			)
		);
	}

	/**
	 * Get society discovery metadata.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_discovery_info( $request ) {
		$site_icon_id = get_option( 'site_icon' );
		$site_icon_url = $site_icon_id ? wp_get_attachment_image_url( $site_icon_id, 'full' ) : '';
		$society_logo = get_option( 'nammasociety51_society_logo', $site_icon_url );

		$society_name = get_option( 'nammasociety51_society_name', get_bloginfo( 'name' ) );
		if ( empty( $society_name ) ) {
			$society_name = get_bloginfo( 'name' );
		}

		$palette_map = array(
			'orange'  => '#ea580c',
			'indigo'  => '#4f46e5',
			'emerald' => '#059669',
			'ocean'   => '#0284c7',
			'rose'    => '#e11d48',
		);
		$color_palette = get_option( 'nammasociety51_color_palette', 'orange' );
		$primary_color = isset( $palette_map[ $color_palette ] ) ? $palette_map[ $color_palette ] : get_option( 'nammasociety51_primary_color', '#ea580c' );
		$default_theme = get_option( 'nammasociety51_default_theme', 'light' );

		$discovery_data = array(
			'status'           => 'active',
			'app_name'         => 'Namma Society',
			'api_version'      => '1.0.0',
			'wp_version'       => get_bloginfo( 'version' ),
			'site_url'         => get_site_url(),
			'rest_url'         => get_rest_url( null, 'namma-society/v1/' ),
			'society'          => array(
				'name'          => $society_name,
				'tagline'       => get_bloginfo( 'description' ) ? get_bloginfo( 'description' ) : 'Society Management Made Simpler',
				'logo_url'      => $society_logo ? $society_logo : null,
				'address_line1' => get_option( 'nammasociety51_society_address_line1', '' ),
				'address_line2' => get_option( 'nammasociety51_society_address_line2', '' ),
				'city'          => get_option( 'nammasociety51_society_city', '' ),
				'pincode'       => get_option( 'nammasociety51_society_pincode', '' ),
				'contact_phone' => get_option( 'nammasociety51_society_contact', '' ),
				'emergency_num' => get_option( 'nammasociety51_society_emergency', get_option( 'nammasociety51_society_contact', '' ) ),
				'color_palette' => $color_palette,
				'primary_color' => $primary_color,
				'default_theme' => $default_theme,
				'currency'      => get_option( 'nammasociety51_currency_symbol', '₹' ),
				'currency_code' => get_option( 'nammasociety51_currency_code', 'INR' ),
			),
			'payment_config'   => array(
				'upi_id'       => get_option( 'nammasociety51_bank_upi', '' ),
				'bank_name'    => get_option( 'nammasociety51_bank_name', '' ),
				'qr_code_url'  => get_option( 'nammasociety51_bank_qr', '' ),
			),
			'features'         => class_exists( 'NAMMASOCIETY51_Module_Registry' ) ? NAMMASOCIETY51_Module_Registry::get_features_map() : array(
				'flats'         => true,
				'residents'     => true,
				'visitors'      => true,
				'vehicles'      => true,
				'documents'     => true,
				'facilities'    => true,
				'finance'       => true,
				'assets'        => true,
				'notices'       => true,
				'polls'         => true,
				'staff'         => true,
				'rules'         => true,
				'helpdesk'      => true,
			),
			'auth'             => array(
				'login_endpoint' => get_rest_url( null, 'namma-society/v1/auth/login' ),
				'me_endpoint'    => get_rest_url( null, 'namma-society/v1/auth/me' ),
			),
		);

		return rest_ensure_response( $discovery_data );
	}
}

