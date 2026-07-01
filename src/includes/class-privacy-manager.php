<?php
/**
 * Class: Privacy Manager
 * Handles DPDP/GDPR Compliance (Data Export & Erasure).
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Privacy_Manager {

	private $db;

	public function __construct() {
		$this->db = new SHUBX51_DB_Router();
		
		// Register WP Privacy Hooks
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}

	/**
	 * Register Data Exporters.
	 */
	public function register_exporters( $exporters ) {
		$exporters['society-hubx'] = array(
			'exporter_friendly_name' => __( 'Society HubX Data', 'society-hubx' ),
			'callback'               => array( $this, 'export_society_data' ),
		);
		return $exporters;
	}

	/**
	 * Register Data Erasers.
	 */
	public function register_erasers( $erasers ) {
		$erasers['society-hubx'] = array(
			'eraser_friendly_name' => __( 'Society HubX Data', 'society-hubx' ),
			'callback'             => array( $this, 'erase_society_data' ),
		);
		return $erasers;
	}

	/**
	 * Core Exporter Logic.
	 */
	public function export_society_data( $email_address, $page = 1 ) {
		$data_to_export = array();
		
		// Find Resident by Email
		$residents = $this->db->get( 'residents', array( 'email' => $email_address ) );
		foreach ( $residents as $resident ) {
			$item_id = "resident-{$resident['id']}";
			$data = array(
				array( 'name' => __( 'Name', 'society-hubx' ), 'value' => $resident['name'] ),
				array( 'name' => __( 'Flat No', 'society-hubx' ), 'value' => $resident['flat_no'] ),
				array( 'name' => __( 'Phone', 'society-hubx' ), 'value' => $resident['phone'] ),
				array( 'name' => __( 'Type', 'society-hubx' ), 'value' => $resident['type'] ),
				array( 'name' => __( 'DOB', 'society-hubx' ), 'value' => $resident['dob'] ?? '' ),
			);

			$data_to_export[] = array(
				'group_id'    => 'society-hubx-residents',
				'group_label' => __( 'Society Residents', 'society-hubx' ),
				'item_id'     => $item_id,
				'data'        => $data,
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Core Eraser Logic (Anonymization).
	 */
	public function erase_society_data( $email_address, $page = 1 ) {
		$residents = $this->db->get( 'residents', array( 'email' => $email_address ) );
		$items_removed = false;
		$items_retained = false;
		$messages = array();

		foreach ( $residents as $resident ) {
			$anon_data = array(
				'name'          => __( 'Anonymized', 'society-hubx' ),
				'email'         => '',
				'phone'         => '0000000000',
				'profile_photo' => '',
				'status'        => 'archived',
			);
			
			$this->db->update( 'residents', $anon_data, array( 'id' => $resident['id'] ) );
			$items_removed = true;
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}

	/**
	 * Helper: Mask PII for UI Display.
	 */
	public static function mask_data( $data, $type = 'phone' ) {
		if ( ! get_option( 'shubx51_privacy_masking', 1 ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return $data;
		}
		// If current user has high privileges, don't mask
		$shubx = Society_HubX::get_instance();
		if ( $shubx->rbac->has_capability( get_current_user_id(), 'settings_manage' ) ) {
			return $data;
		}

		if ( $type === 'phone' ) {
			return substr( $data, 0, 3 ) . 'XXXX' . substr( $data, -3 );
		}
		
		if ( $type === 'email' ) {
			$parts = explode( '@', $data );
			$name = $parts[0];
			$domain = $parts[1] ?? '';
			return substr( $name, 0, 1 ) . '***' . '@' . $domain;
		}

		return $data;
	}
}
