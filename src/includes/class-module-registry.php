<?php
/**
 * Class: Module Registry
 * Manages society modules, active states, and feature flags.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Module_Registry {

	/**
	 * Option key for stored module settings.
	 */
	const OPTION_KEY = 'nammasociety51_active_modules';

	/**
	 * Get the full dictionary of all recognized modules.
	 *
	 * @return array
	 */
	public static function get_all_modules() {
		return array(
			'flats'       => array(
				'slug'        => 'flats',
				'name'        => __( 'Flats & Units', 'namma-society' ),
				'description' => __( 'Unit & Block Master, square footage, floor planning, and occupancy status.', 'namma-society' ),
				'icon'        => 'bi-building',
				'locked'      => true, // Core master data cannot be disabled
				'category'    => 'core',
			),
			'residents'   => array(
				'slug'        => 'residents',
				'name'        => __( 'Resident Directory', 'namma-society' ),
				'description' => __( 'Resident profiles, ownership mapping, family members, and roles.', 'namma-society' ),
				'icon'        => 'bi-people-fill',
				'locked'      => true, // Core master data cannot be disabled
				'category'    => 'core',
			),
			'visitors'    => array(
				'slug'        => 'visitors',
				'name'        => __( 'Visitor Management (VMS)', 'namma-society' ),
				'description' => __( 'Gate passes, security guard log, guest pre-approvals, delivery, and cab entries.', 'namma-society' ),
				'icon'        => 'bi-shield-check',
				'locked'      => false,
				'category'    => 'security',
			),
			'finance'     => array(
				'slug'        => 'finance',
				'name'        => __( 'Accounts & Finance', 'namma-society' ),
				'description' => __( 'Monthly maintenance invoicing, expenses ledger, dues tracking, and payment receipts.', 'namma-society' ),
				'icon'        => 'bi-cash-coin',
				'locked'      => false,
				'category'    => 'operations',
			),
			'facilities'  => array(
				'slug'        => 'facilities',
				'name'        => __( 'Facilities & Bookings', 'namma-society' ),
				'description' => __( 'Clubhouse, tennis court, swimming pool reservations, and slot availability calendar.', 'namma-society' ),
				'icon'        => 'bi-calendar-check',
				'locked'      => false,
				'category'    => 'community',
			),
			'staff'       => array(
				'slug'        => 'staff',
				'name'        => __( 'Daily Help & Staff', 'namma-society' ),
				'description' => __( 'Domestic maids, cooks, maintenance staff directory, attendance, and flat associations.', 'namma-society' ),
				'icon'        => 'bi-person-badge',
				'locked'      => false,
				'category'    => 'security',
			),
			'helpdesk'    => array(
				'slug'        => 'helpdesk',
				'name'        => __( 'Helpdesk & Requests', 'namma-society' ),
				'description' => __( 'Resident service requests, maintenance complaints, technician assignment, and SLA tracking.', 'namma-society' ),
				'icon'        => 'bi-tools',
				'locked'      => false,
				'category'    => 'operations',
			),
			'rules'       => array(
				'slug'        => 'rules',
				'name'        => __( 'Rules & Compliance', 'namma-society' ),
				'description' => __( 'Society bylaws, versioned rules, digital acknowledgments, and violation penalty management.', 'namma-society' ),
				'icon'        => 'bi-file-earmark-ruled',
				'locked'      => false,
				'category'    => 'governance',
			),
			'documents'   => array(
				'slug'        => 'documents',
				'name'        => __( 'Document Vault', 'namma-society' ),
				'description' => __( 'Secure repository for society registration docs, meeting minutes, and flat papers.', 'namma-society' ),
				'icon'        => 'bi-folder2-open',
				'locked'      => false,
				'category'    => 'governance',
			),
			'notices'     => array(
				'slug'        => 'notices',
				'name'        => __( 'Notice Board', 'namma-society' ),
				'description' => __( 'Official broadcasts, circulars, urgent announcements, and event notifications.', 'namma-society' ),
				'icon'        => 'bi-megaphone',
				'locked'      => false,
				'category'    => 'community',
			),
			'polls'       => array(
				'slug'        => 'polls',
				'name'        => __( 'Digital Democracy & Polls', 'namma-society' ),
				'description' => __( 'Online voting, AGM resolutions, community opinion polls, and decision tracking.', 'namma-society' ),
				'icon'        => 'bi-ui-checks-grid',
				'locked'      => false,
				'category'    => 'governance',
			),
			'vehicles'    => array(
				'slug'        => 'vehicles',
				'name'        => __( 'Vehicles & Parking', 'namma-society' ),
				'description' => __( 'Resident vehicle registration, parking slot tracking, and parking pass stickers.', 'namma-society' ),
				'icon'        => 'bi-car-front',
				'locked'      => false,
				'category'    => 'operations',
			),
			'assets'      => array(
				'slug'        => 'assets',
				'name'        => __( 'Asset Management', 'namma-society' ),
				'description' => __( 'Infrastructure assets, lifts, generators, pumps, warranties, and AMC maintenance contracts.', 'namma-society' ),
				'icon'        => 'bi-box-seam',
				'locked'      => false,
				'category'    => 'operations',
			),
		);
	}

	/**
	 * Check if a specific module is enabled.
	 *
	 * @param string $slug Module identifier.
	 * @return bool
	 */
	public static function is_enabled( $slug ) {
		$all = self::get_all_modules();
		if ( ! isset( $all[ $slug ] ) ) {
			return false;
		}

		// Core locked modules are always active
		if ( ! empty( $all[ $slug ]['locked'] ) ) {
			return true;
		}

		$active = get_option( self::OPTION_KEY, null );

		// If never set before, default all to enabled
		if ( $active === null ) {
			return true;
		}

		return ! empty( $active[ $slug ] );
	}

	/**
	 * Get list of all currently active module slugs.
	 *
	 * @return array
	 */
	public static function get_active_slugs() {
		$slugs = array();
		foreach ( array_keys( self::get_all_modules() ) as $slug ) {
			if ( self::is_enabled( $slug ) ) {
				$slugs[] = $slug;
			}
		}
		return $slugs;
	}

	/**
	 * Set the active status of a single module.
	 *
	 * @param string $slug Module slug.
	 * @param bool   $enabled True to enable, false to disable.
	 * @return bool
	 */
	public static function set_module_status( $slug, $enabled ) {
		$all = self::get_all_modules();
		if ( ! isset( $all[ $slug ] ) ) {
			return false;
		}

		// Cannot disable locked core modules
		if ( ! empty( $all[ $slug ]['locked'] ) && ! $enabled ) {
			return false;
		}

		$active = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $active ) ) {
			$active = array();
		}

		$active[ $slug ] = (bool) $enabled;
		return update_option( self::OPTION_KEY, $active );
	}

	/**
	 * Set active modules as an associative array: [slug => bool].
	 *
	 * @param array $module_states Map of slug => boolean.
	 * @return bool
	 */
	public static function set_active_modules( array $module_states ) {
		$all = self::get_all_modules();
		$clean = array();

		foreach ( $all as $slug => $meta ) {
			if ( ! empty( $meta['locked'] ) ) {
				$clean[ $slug ] = true;
			} else {
				$clean[ $slug ] = ! empty( $module_states[ $slug ] );
			}
		}

		return update_option( self::OPTION_KEY, $clean );
	}

	/**
	 * Build a feature flags map for client consumption (/discovery endpoint).
	 *
	 * @return array [ slug => boolean ]
	 */
	public static function get_features_map() {
		$features = array();
		foreach ( array_keys( self::get_all_modules() ) as $slug ) {
			$features[ $slug ] = self::is_enabled( $slug );
		}
		return $features;
	}
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_Module_Registry' ) && ! class_exists( 'SHUBX51_Module_Registry', false ) ) {
	class_alias( 'NAMMASOCIETY51_Module_Registry', 'SHUBX51_Module_Registry' );
}
