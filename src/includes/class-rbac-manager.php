<?php
/**
 * Class: RBAC Manager
 * Handles Granular Role-Based Access Control.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_RBAC_Manager {

	private $db;
	private $roles_table;

	public function __construct() {
		global $wpdb;
		$this->db = new SNESTX51_DB_Router();
		$this->roles_table = "{$wpdb->prefix}society_nestx_roles";
	}

	/**
	 * Check if a user has a specific capability.
	 */
	public function has_capability( $user_id, $capability ) {
		// WordPress Super Admin always has access
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$user_roles = $this->get_user_roles( $user_id );
		if ( empty( $user_roles ) ) {
			return false;
		}

		foreach ( $user_roles as $role_id ) {
			if ( $this->role_has_capability( $role_id, $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get roles assigned to a user.
	 */
	public function get_user_roles( $user_id ) {
		$residents = $this->db->get( 'residents', array( 'where' => array( 'wp_user_id' => $user_id ), 'load_relations' => true ) );
		if ( empty( $residents ) ) {
			return array();
		}

		$roles = $residents[0]['roles'] ?? array();
		if ( is_string( $roles ) ) {
			// Fallback if relations didn't load but CSV column exists
			$roles = array_filter( explode( ',', $roles ) );
		}
		
		return (array) $roles;
	}

	/**
	 * Check if a role has a capability.
	 */
	private function role_has_capability( $role_id, $capability ) {
		$role = $this->get_role( $role_id );
		if ( ! $role ) {
			return false;
		}

		$caps = is_string($role['capabilities']) ? json_decode( $role['capabilities'], true ) : $role['capabilities'];
		return is_array( $caps ) && in_array( $capability, $caps );
	}

	/**
	 * Get role definition.
	 */
	public function get_role( $role_id ) {
		$roles = $this->db->get( 'roles', array( 'id' => $role_id ) );
		return !empty($roles) ? $roles[0] : null;
	}

	/**
	 * Get all roles.
	 */
	public function get_all_roles() {
		return $this->db->get( 'roles' );
	}

	/**
	 * Create/Update Role.
	 */
	public function save_role( $role_id, $name, $capabilities, $is_system = 0 ) {
		$data = array(
			'name'         => $name,
			'capabilities' => json_encode( $capabilities ),
			'is_system'    => $is_system,
			'updated_at'   => current_time( 'mysql' )
		);

		// Sync with WordPress Roles
		$wp_role_id = 'SNESTX_' . sanitize_title( $role_id );
		if ( ! get_role( $wp_role_id ) ) {
			add_role( $wp_role_id, 'SNESTX: ' . $name, array( 'read' => true ) );
		}

		$existing = $this->get_role( $role_id );
		if ( $existing ) {
			return $this->db->update( 'roles', $data, array( 'id' => $role_id ) );
		} else {
			$data['id'] = $role_id;
			$data['created_at'] = current_time( 'mysql' );
			return $this->db->insert( 'roles', $data );
		}
	}

	/**
	 * List of all available capabilities in the system.
	 */
	public static function get_available_capabilities() {
		return array(
			'dashboard_view'   => 'View Executive Dashboard',
			'residents_view'   => 'View Residents List',
			'residents_manage' => 'Add/Edit/Delete Residents',
			'flats_view'       => 'View Flats & Units',
			'flats_manage'     => 'Manage Flats & Units',
			'facilities_view'  => 'View Facilities & Bookings',
			'facilities_manage'=> 'Manage Facilities & Bookings',
			'finance_view'     => 'View Financial Reports',
			'finance_manage'   => 'Manage Invoices & Payments',
			'notices_view'     => 'View Society Notices',
			'notices_manage'   => 'Manage Society Notices',
			'rules_view'       => 'View Society Rules',
			'rules_manage'     => 'Manage Rules & Violations',
			'staff_view'       => 'View Support Staff',
			'staff_manage'     => 'Manage Support Staff',
			'vehicles_view'    => 'View Vehicle Registry',
			'vehicles_manage'  => 'Manage Vehicle Registry',
			'polls_view'       => 'View Society Polls',
			'polls_manage'     => 'Manage Society Polls',
			'settings_manage'  => 'Manage Plugin Settings'
		);
	}

	public function delete_role( $role_id ) {
		return $this->db->delete( 'roles', array( 'id' => $role_id ) );
	}
}
