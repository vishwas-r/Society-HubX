<?php
/**
 * Class: Privacy Manager
 * Handles DPDP/GDPR Compliance (Data Export & Erasure).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Privacy_Manager {

	private $db;

	public function __construct() {
		$this->db = new NAMMASOCIETY51_DB_Router();
		
		// Register WP Privacy Hooks
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_erasers' ) );
	}

	/**
	 * Register Data Exporters.
	 */
	public function register_exporters( $exporters ) {
		$exporters['namma-society'] = array(
			'exporter_friendly_name' => __( 'Namma Society Data', 'namma-society' ),
			'callback'               => array( $this, 'export_society_data' ),
		);
		return $exporters;
	}

	/**
	 * Register Data Erasers.
	 */
	public function register_erasers( $erasers ) {
		$erasers['namma-society'] = array(
			'eraser_friendly_name' => __( 'Namma Society Data', 'namma-society' ),
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
				array( 'name' => __( 'Name', 'namma-society' ), 'value' => $resident['name'] ),
				array( 'name' => __( 'Flat No', 'namma-society' ), 'value' => $resident['flat_no'] ),
				array( 'name' => __( 'Phone', 'namma-society' ), 'value' => $resident['phone'] ),
				array( 'name' => __( 'Type', 'namma-society' ), 'value' => $resident['type'] ),
				array( 'name' => __( 'DOB', 'namma-society' ), 'value' => $resident['dob'] ?? '' ),
			);

			$data_to_export[] = array(
				'group_id'    => 'namma-society-residents',
				'group_label' => __( 'Society Residents', 'namma-society' ),
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
				'name'          => __( 'Anonymized', 'namma-society' ),
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
		if ( ! get_option( 'nammasociety51_privacy_masking', 1 ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return $data;
		}
		// If current user has high privileges, don't mask
		$nammasociety = NAMMASOCIETY51_Plugin::get_instance();
		if ( $nammasociety->rbac->has_capability( get_current_user_id(), 'settings_manage' ) ) {
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

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_Privacy_Manager' ) && ! class_exists( 'SHUBX51_Privacy_Manager', false ) ) {
	class_alias( 'NAMMASOCIETY51_Privacy_Manager', 'SHUBX51_Privacy_Manager' );
}
