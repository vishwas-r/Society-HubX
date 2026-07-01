<?php
/**
 * Class: DB Router
 * Handles data operations (CRUD) for the Society Management System.
 * Implements "Hybrid Storage":
 * - Reads always from Local JSON (fast).
 * - Writes go to Google Sheets (if connected) then Update Local.
 * - If Offline, Writes go directly to Local JSON.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_DB_Router {
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
            'residents'               => $wpdb->prefix . 'society_nestx_residents',
            'flats'                   => $wpdb->prefix . 'society_nestx_flats',
            'daily_help'              => $wpdb->prefix . 'society_nestx_daily_help',
            'invoices'                => $wpdb->prefix . 'society_nestx_invoices',
            'transactions'            => $wpdb->prefix . 'society_nestx_transactions',
            'notices'                 => $wpdb->prefix . 'society_nestx_notices',
            'amenities'               => $wpdb->prefix . 'society_nestx_amenities',
            'bookings'                => $wpdb->prefix . 'society_nestx_bookings',
            'vehicles'                => $wpdb->prefix . 'society_nestx_vehicles',
            'visitors'                => $wpdb->prefix . 'society_nestx_visitors',
            'complaints'              => $wpdb->prefix . 'society_nestx_complaints',
            'suggestions'             => $wpdb->prefix . 'society_nestx_suggestions',
            'polls'                   => $wpdb->prefix . 'society_nestx_polls',
            'poll_options'            => $wpdb->prefix . 'society_nestx_poll_options',
            'poll_votes'              => $wpdb->prefix . 'society_nestx_poll_votes',
            'documents'               => $wpdb->prefix . 'society_nestx_documents',
            'events'                  => $wpdb->prefix . 'society_nestx_events',
            'vendors'                 => $wpdb->prefix . 'society_nestx_vendors',
            'staff'                   => $wpdb->prefix . 'society_nestx_staff',
            'settings'                => $wpdb->prefix . 'society_nestx_settings',
            'requests'                => $wpdb->prefix . 'society_nestx_requests',
            'audit_logs'              => $wpdb->prefix . 'society_nestx_audit_logs',
            'roles'                   => $wpdb->prefix . 'society_nestx_roles',
            'staff_flats'             => $wpdb->prefix . 'society_nestx_staff_flats',
            'resident_role_map'       => $wpdb->prefix . 'society_nestx_resident_role_map',
            'payments'                => $wpdb->prefix . 'society_nestx_payments',
        );
		return $tables[ $slug ] ?? $wpdb->prefix . 'society_nestx_' . $slug;
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
			error_log( 'SNESTX51 DB Error (get_mysql ' . $table . '): ' . $this->wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
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
