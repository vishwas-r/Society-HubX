<?php
/**
 * Class: DB Router
 * Handles data operations (CRUD) for the Society Management System.
 * Implements "Hybrid Storage":
 * - Reads always from Local JSON (fast).
 * - Writes go to Google Sheets (if connected) then Update Local.
 * - If Offline, Writes go directly to Local JSON.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_DB_Router {
	/**
	 * List of all database table slugs for hybrid storage and data portability.
	 */
	const TABLES = array(
		'residents',
		'flats',
		'daily_help',
		'invoices',
		'transactions',
		'notices',
		'amenities',
		'bookings',
		'vehicles',
		'visitors',
		'complaints',
		'suggestions',
		'polls',
		'poll_options',
		'poll_votes',
		'documents',
		'events',
		'vendors',
		'staff',
		'settings',
		'requests',
		'audit_logs',
		'roles',
		'staff_flats',
		'resident_role_map',
		'resident_flat_map',
		'payments',
	);

	/**
	 * WordPress Database Instance.
	 *
	 * @var wpdb
	 */
	public $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Map Slug to Table Name.
	 */
	public function get_table_name( $slug ) {
		global $wpdb;
		$tables = array(
            'residents'               => $wpdb->prefix . 'society_hubx_residents',
            'flats'                   => $wpdb->prefix . 'society_hubx_flats',
            'daily_help'              => $wpdb->prefix . 'society_hubx_daily_help',
            'invoices'                => $wpdb->prefix . 'society_hubx_invoices',
            'transactions'            => $wpdb->prefix . 'society_hubx_transactions',
            'notices'                 => $wpdb->prefix . 'society_hubx_notices',
            'amenities'               => $wpdb->prefix . 'society_hubx_amenities',
            'bookings'                => $wpdb->prefix . 'society_hubx_bookings',
            'vehicles'                => $wpdb->prefix . 'society_hubx_vehicles',
            'visitors'                => $wpdb->prefix . 'society_hubx_visitors',
            'complaints'              => $wpdb->prefix . 'society_hubx_complaints',
            'suggestions'             => $wpdb->prefix . 'society_hubx_suggestions',
            'polls'                   => $wpdb->prefix . 'society_hubx_polls',
            'poll_options'            => $wpdb->prefix . 'society_hubx_poll_options',
            'poll_votes'              => $wpdb->prefix . 'society_hubx_poll_votes',
            'documents'               => $wpdb->prefix . 'society_hubx_documents',
            'events'                  => $wpdb->prefix . 'society_hubx_events',
            'vendors'                 => $wpdb->prefix . 'society_hubx_vendors',
            'staff'                   => $wpdb->prefix . 'society_hubx_staff',
            'settings'                => $wpdb->prefix . 'society_hubx_settings',
            'requests'                => $wpdb->prefix . 'society_hubx_requests',
            'audit_logs'              => $wpdb->prefix . 'society_hubx_audit_logs',
            'roles'                   => $wpdb->prefix . 'society_hubx_roles',
            'staff_flats'             => $wpdb->prefix . 'society_hubx_staff_flats',
            'resident_role_map'       => $wpdb->prefix . 'society_hubx_resident_role_map',
            'resident_flat_map'       => $wpdb->prefix . 'society_hubx_resident_flat_map',
            'payments'                => $wpdb->prefix . 'society_hubx_payments',
        );
		return $tables[ $slug ] ?? $wpdb->prefix . 'society_hubx_' . $slug;
	}

	/**
	 * GET Data (Read)
	 * 
	 * @param string $table Table name/slug.
	 * @param array  $args  Optional arguments: 'where' (assoc array), 'limit', 'offset', 'orderby', 'order', 'load_relations'.
	 */
	public function get( $table, $args = array() ) {
		return $this->get_mysql( $table, $args );
	}

	/**
	 * Get a single row by ID
	 */
	public function get_row( $table, $id ) {
		$results = $this->get( $table, array( 'where' => array( 'id' => $id ) ) );
		return ! empty( $results ) ? $results[0] : false;
	}

	/**
	 * GET Rows from MySQL
	 */
	public function get_mysql( $table, $args = array() ) {
		$sql_table = $this->get_table_name( $table );
		$query = "SELECT * FROM " . $sql_table;
		
		$where_clauses = array();
		$values = array();

		// 1. WHERE Clause
		if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
			foreach ( $args['where'] as $col => $val ) {
				$where_clauses[] = "`$col` = %s";
				$values[] = $val;
			}
		}

		if ( ! empty( $where_clauses ) ) {
			$query .= " WHERE " . implode( ' AND ', $where_clauses );
		}

		// 2. ORDER BY
		if ( ! empty( $args['orderby'] ) ) {
			$col = preg_replace( '/[^a-zA-Z0-9_]/', '', $args['orderby'] );
			$order = ( ! empty( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ) ? 'DESC' : 'ASC';
			if ( $col ) {
				$query .= " ORDER BY `$col` $order";
			}
		}

		// 3. LIMIT & OFFSET
		if ( isset( $args['limit'] ) ) {
			$query .= " LIMIT %d";
			$values[] = intval( $args['limit'] );
		}

		if ( isset( $args['offset'] ) ) {
			$query .= " OFFSET %d";
			$values[] = intval( $args['offset'] );
		}

		// Prepare Query
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built dynamically.
			$query = $this->wpdb->prepare( $query, $values );
		}
		
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL built dynamically.
		$results = $this->wpdb->get_results( $query, ARRAY_A );
		
		if ( $this->wpdb->last_error ) {
			error_log( 'SHUBX51 DB Error (get_mysql ' . $table . '): ' . $this->wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			return array();
		}
		
		if ( ! $results ) {
			return array();
		}

		// Handle JSON fields (Enterprise: Preserving logic for DB-stored JSON)
		foreach ( $results as $key => $row ) {
			if ( isset( $row['options'] ) ) {
				$results[ $key ]['options'] = json_decode( $row['options'], true ) ?: array();
			}

			if ( isset( $row['payments'] ) && is_string( $row['payments'] ) ) {
				$results[ $key ]['payments'] = json_decode( $row['payments'], true ) ?: array();
			}

			if ( isset( $row['payload'] ) && is_string( $row['payload'] ) ) {
				$results[ $key ]['payload'] = json_decode( $row['payload'], true ) ?: array();
			}
			
			// RELATIONAL LOADING (New Relational Engine)
			if ( ! empty( $args['load_relations'] ) ) {
				$id = $row['id'] ?? '';
				if ( ! $id ) continue;

				// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table names in SQL statements cannot be prepared.
				if ( $table === 'residents' ) {
					$results[ $key ]['roles'] = $this->wpdb->get_col( $this->wpdb->prepare( 
						"SELECT role_id FROM " . $this->get_table_name('resident_role_map') . " WHERE resident_id = %s", $id 
					) );
					$results[ $key ]['flat_ids'] = $this->wpdb->get_col( $this->wpdb->prepare(
						"SELECT flat_id FROM " . $this->get_table_name('resident_flat_map') . " WHERE resident_id = %s ORDER BY is_primary DESC", $id
					) );
				}
				
				if ( $table === 'daily_help' || $table === 'staff' ) {
					$results[ $key ]['flats_served'] = $this->wpdb->get_col( $this->wpdb->prepare( 
						"SELECT flat_id FROM " . $this->get_table_name('staff_flats') . " WHERE staff_id = %s", $id 
					) );
				}

				if ( $table === 'invoices' ) {
					$results[ $key ]['payments'] = $this->wpdb->get_results( $this->wpdb->prepare( 
						"SELECT * FROM " . $this->get_table_name('payments') . " WHERE invoice_id = %s", $id 
					), ARRAY_A );
				}
				// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
		}

		return $results;
	}

	/**
	 * INSERT Row
	 */
	public function insert( $table, $data ) {
		return $this->insert_mysql( $table, $data );
	}

	private function insert_mysql( $table, $data ) {
		$sql_table = $this->get_table_name( $table );
		
		// 1. Filter out columns that don't exist in the table
		$valid_columns = $this->get_columns( $sql_table );
		if ( ! empty( $valid_columns ) ) {
			$data = array_intersect_key( $data, array_flip( $valid_columns ) );
		}

		// 2. Handle special types
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$data[ $key ] = json_encode( $val );
			}
		}

		$result = $this->wpdb->insert( $sql_table, $data );
		if ( false === $result ) return new WP_Error( 'db_error', $this->wpdb->last_error );
		
		return $this->wpdb->insert_id ?: true;
	}

	/**
	 * Helper: Get all columns of a table.
	 */
	public function get_columns( $table_name ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema DESCRIBE query requires dynamic table name.
		$columns = $this->wpdb->get_col( "DESCRIBE $table_name" );
		return $columns ?: array();
	}

	/**
	 * Verify and Create Column if missing (Self-Healing).
	 */
	public function verify_column( $table, $column, $definition ) {
		$sql_table = $this->get_table_name( $table );
		$columns = $this->get_columns( $sql_table );
		
		if ( ! in_array( $column, $columns ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema ALTER query requires dynamic table, column, and definition strings.
			$this->wpdb->query( "ALTER TABLE $sql_table ADD $column $definition" );
		}
	}

	/**
	 * UPDATE Row
	 */
	public function update( $table, $data, $where ) {
		return $this->update_mysql( $table, $data, $where );
	}

	public function update_mysql( $table, $data, $where ) {
		$sql_table = $this->get_table_name( $table );
		
		$valid_columns = $this->get_columns( $sql_table );
		if ( ! empty( $valid_columns ) ) {
			$data = array_intersect_key( $data, array_flip( $valid_columns ) );
		}

		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$data[ $key ] = json_encode( $val );
			}
		}

		$result = $this->wpdb->update( $sql_table, $data, $where );
		if ( false === $result ) {
			return new WP_Error( 'db_error', $this->wpdb->last_error );
		}
		
		return $result;
	}

	/**
	 * Save Relational Mappings (Helper)
	 */
	public function save_relations( $table, $main_id_col, $main_id_val, $rel_id_col, $rel_id_vals ) {
		$mapping_table = $this->get_table_name( $table );
		
		// 1. Clear existing
		$this->wpdb->delete( $mapping_table, array( $main_id_col => $main_id_val ) );
		
		// 2. Insert new
		if ( ! is_array( $rel_id_vals ) ) return;
		
		foreach ( $rel_id_vals as $rel_id ) {
			if ( empty( $rel_id ) ) continue;
			$this->wpdb->insert( $mapping_table, array(
				$main_id_col => $main_id_val,
				$rel_id_col  => $rel_id
			) );
		}
	}

	/**
	 * DELETE Row
	 */
	public function delete( $table, $where ) {
		return $this->delete_mysql( $table, $where );
	}

	private function delete_mysql( $table, $where ) {
		$sql_table = $this->get_table_name( $table );
		$result = $this->wpdb->delete( $sql_table, $where );
		if ( false === $result ) return new WP_Error( 'db_error', $this->wpdb->last_error );
		return true;
	}

	/**
	 * Get a single invoice by ID
	 */
	public function get_invoice( $invoice_id ) {
		$results = $this->get( 'invoices', array( 'where' => array( 'id' => $invoice_id ) ) );
		return ! empty( $results ) ? $results[0] : false;
	}

	/**
	 * Get resident by WordPress user ID
	 */
	public function get_resident_by_wp_id( $wp_id ) {
		$results = $this->get( 'residents', array( 'where' => array( 'wp_user_id' => $wp_id ) ) );
		return ! empty( $results ) ? $results[0] : false;
	}

	/**
	 * Get all flat IDs assigned to a resident.
	 *
	 * @param string $resident_id Resident ID.
	 * @return array Array of flat IDs, primary first.
	 */
	public function get_resident_flats( $resident_id ) {
		$map_table = $this->get_table_name( 'resident_flat_map' );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic table name via get_table_name.
		$flat_ids = $this->wpdb->get_col( $this->wpdb->prepare(
			"SELECT flat_id FROM {$map_table} WHERE resident_id = %s ORDER BY is_primary DESC",
			$resident_id
		) );
		return $flat_ids ?: array();
	}

	/**
	 * Save flat assignments for a resident.
	 * Replaces all existing entries. Also updates residents.flat_no to the primary flat.
	 *
	 * @param string $resident_id    Resident ID.
	 * @param array  $flat_ids       Array of flat IDs to assign.
	 * @param string $primary_flat_id Primary flat ID (defaults to first element).
	 */
	public function save_resident_flats( $resident_id, array $flat_ids, $primary_flat_id = '' ) {
		$map_table = $this->get_table_name( 'resident_flat_map' );

		// 1. Clear existing
		$this->wpdb->delete( $map_table, array( 'resident_id' => $resident_id ) );

		if ( empty( $flat_ids ) ) {
			return;
		}

		// 2. Determine primary
		if ( empty( $primary_flat_id ) || ! in_array( $primary_flat_id, $flat_ids, true ) ) {
			$primary_flat_id = $flat_ids[0];
		}

		// 3. Insert rows
		foreach ( $flat_ids as $flat_id ) {
			if ( empty( $flat_id ) ) continue;
			$this->wpdb->insert( $map_table, array(
				'resident_id' => $resident_id,
				'flat_id'     => $flat_id,
				'is_primary'  => ( $flat_id === $primary_flat_id ) ? 1 : 0,
			) );
		}

		// 4. Keep residents.flat_no in sync with primary flat
		$this->update( 'residents', array( 'flat_no' => $primary_flat_id ), array( 'id' => $resident_id ) );
	}

	/**
	 * Get a formatted flat name (e.g. A-101) by Flat ID.
	 */
	public function get_flat_display_name( $flat_id ) {
		if ( empty( $flat_id ) ) return 'N/A';
		
		$flats = $this->get( 'flats', array( 'where' => array( 'id' => $flat_id ) ) );
		if ( ! empty( $flats ) ) {
			$f = $flats[0];
			$block = ! empty( $f['block'] ) ? $f['block'] : '';
			$num = ! empty( $f['flat_number'] ) ? $f['flat_number'] : $f['id'];
			
			if ( ! empty( $block ) ) {
				return $block . '-' . $num;
			}
			return $num;
		}
		
		return $flat_id;
	}
}
