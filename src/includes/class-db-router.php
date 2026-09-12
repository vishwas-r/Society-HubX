<?php
/**
 * Class: DB Router
 * Handles data operations (CRUD) for the Society Management System.
 * Implements "Hybrid Storage":
 * - Reads always from Local JSON (fast).
 * - Writes go to Google Sheets (if connected) then Update Local.
 * - If Offline, Writes go directly to Local JSON.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_DB_Router {
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
		'visitor_passes',
		'guard_logs',
		'helpdesk_tickets',
		'ticket_replies',
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
		'device_tokens',
		'inapp_notifications',
		'emergency_alerts',
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
            'residents'               => $wpdb->prefix . 'nammasociety51_residents',
            'flats'                   => $wpdb->prefix . 'nammasociety51_flats',
            'daily_help'              => $wpdb->prefix . 'nammasociety51_daily_help',
            'invoices'                => $wpdb->prefix . 'nammasociety51_invoices',
            'transactions'            => $wpdb->prefix . 'nammasociety51_transactions',
            'notices'                 => $wpdb->prefix . 'nammasociety51_notices',
            'amenities'               => $wpdb->prefix . 'nammasociety51_amenities',
            'bookings'                => $wpdb->prefix . 'nammasociety51_bookings',
            'vehicles'                => $wpdb->prefix . 'nammasociety51_vehicles',
            'visitors'                => $wpdb->prefix . 'nammasociety51_visitors',
            'visitor_passes'          => $wpdb->prefix . 'nammasociety51_visitor_passes',
            'guard_logs'              => $wpdb->prefix . 'nammasociety51_guard_logs',
            'helpdesk_tickets'        => $wpdb->prefix . 'nammasociety51_helpdesk_tickets',
            'ticket_replies'          => $wpdb->prefix . 'nammasociety51_ticket_replies',
            'complaints'              => $wpdb->prefix . 'nammasociety51_complaints',
            'suggestions'             => $wpdb->prefix . 'nammasociety51_suggestions',
            'polls'                   => $wpdb->prefix . 'nammasociety51_polls',
            'poll_options'            => $wpdb->prefix . 'nammasociety51_poll_options',
            'poll_votes'              => $wpdb->prefix . 'nammasociety51_poll_votes',
            'documents'               => $wpdb->prefix . 'nammasociety51_documents',
            'events'                  => $wpdb->prefix . 'nammasociety51_events',
            'vendors'                 => $wpdb->prefix . 'nammasociety51_vendors',
            'staff'                   => $wpdb->prefix . 'nammasociety51_staff',
            'settings'                => $wpdb->prefix . 'nammasociety51_settings',
            'requests'                => $wpdb->prefix . 'nammasociety51_requests',
            'audit_logs'              => $wpdb->prefix . 'nammasociety51_audit_logs',
            'roles'                   => $wpdb->prefix . 'nammasociety51_roles',
            'staff_flats'             => $wpdb->prefix . 'nammasociety51_staff_flats',
            'resident_role_map'       => $wpdb->prefix . 'nammasociety51_resident_role_map',
            'resident_flat_map'       => $wpdb->prefix . 'nammasociety51_resident_flat_map',
            'payments'                => $wpdb->prefix . 'nammasociety51_payments',
            'device_tokens'           => $wpdb->prefix . 'nammasociety51_device_tokens',
            'inapp_notifications'     => $wpdb->prefix . 'nammasociety51_inapp_notifications',
            'emergency_alerts'        => $wpdb->prefix . 'nammasociety51_emergency_alerts',
        );
		return $tables[ $slug ] ?? $wpdb->prefix . 'nammasociety51_' . $slug;
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
	 * Get a single row by field and value
	 *
	 * @param string $table Table name/slug.
	 * @param string $field Column name.
	 * @param mixed  $value Value to match.
	 * @return array|false
	 */
	public function get_row_by_field( $table, $field, $value ) {
		$results = $this->get( $table, array(
			'where' => array( $field => $value ),
			'limit' => 1,
		) );
		return ! empty( $results ) ? $results[0] : false;
	}

	/**
	 * Get paginated records from a table with metadata.
	 *
	 * @param string $table Table name/slug.
	 * @param array  $args Query parameters (page, per_page, where, orderby, order).
	 * @return array Array with 'items', 'total', 'total_pages', 'current_page', 'per_page'.
	 */
	public function get_paginated( $table, $args = array() ) {
		$page     = isset( $args['page'] ) ? max( 1, intval( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 1, intval( $args['per_page'] ) ) ) : 25;
		$offset   = ( $page - 1 ) * $per_page;

		// Calculate total count
		$count_args = $args;
		unset( $count_args['limit'], $count_args['offset'], $count_args['page'], $count_args['per_page'], $count_args['orderby'], $count_args['order'] );
		$total_records = $this->count( $table, $count_args );

		$args['limit']  = $per_page;
		$args['offset'] = $offset;

		$items = $this->get_mysql( $table, $args );

		return array(
			'items'        => $items ? $items : array(),
			'total'        => $total_records,
			'total_pages'  => ( $total_records > 0 ) ? (int) ceil( $total_records / $per_page ) : 0,
			'current_page' => $page,
			'per_page'     => $per_page,
		);
	}

	/**
	 * Count matching rows in a table.
	 *
	 * @param string $table Table name/slug.
	 * @param array  $args Where filter arguments.
	 * @return int
	 */
	public function count( $table, $args = array() ) {
		$sql_table = $this->get_table_name( $table );
		$query = "SELECT COUNT(*) FROM " . $sql_table;
		$where_clauses = array();
		$values = array();

		if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
			foreach ( $args['where'] as $col => $val ) {
				$clean_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $col );
				if ( is_array( $val ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $val ), '%s' ) );
					$where_clauses[] = "`$clean_col` IN ($placeholders)";
					foreach ( $val as $item ) {
						$values[] = $item;
					}
				} else {
					$where_clauses[] = "`$clean_col` = %s";
					$values[] = $val;
				}
			}
		}

		if ( ! empty( $where_clauses ) ) {
			$query .= " WHERE " . implode( ' AND ', $where_clauses );
		}

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL prepared with dynamic values.
			$query = $this->wpdb->prepare( $query, $values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built dynamically.
		return (int) $this->wpdb->get_var( $query );
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
				// Automatically expand flat_no / flat_id strings to all possible identifiers for query robustness
				if ( ( $col === 'flat_no' || $col === 'flat_id' ) && is_string( $val ) && ! empty( $val ) ) {
					$val = $this->get_flat_identifiers( $val );
				}

				if ( is_array( $val ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $val ), '%s' ) );
					$where_clauses[] = "`$col` IN ($placeholders)";
					foreach ( $val as $item ) {
						$values[] = $item;
					}
				} else {
					$where_clauses[] = "`$col` = %s";
					$values[] = $val;
				}
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
			error_log( 'NAMMASOCIETY51 DB Error (get_mysql ' . $table . '): ' . $this->wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
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
		// phpcs:disable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from get_table_name(); value is prepared via %s.
		$flat_ids = $this->wpdb->get_col( $this->wpdb->prepare(
			"SELECT flat_id FROM {$map_table} WHERE resident_id = %s ORDER BY is_primary DESC",
			$resident_id
		) );
		// phpcs:enable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
	 * Get all possible database representations (identifiers) of a flat.
	 *
	 * @param string $flat_id Flat ID/number (e.g., 'flat_A_101', 'A-101', '101').
	 * @return array Array of matching flat representations.
	 */
	public function get_flat_identifiers( $flat_id ) {
		if ( empty( $flat_id ) ) {
			return array();
		}

		$ids = array( $flat_id );

		// 1. Try to find the flat by ID
		// phpcs:disable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from get_table_name(); value is prepared.
		$flat = $this->wpdb->get_row( $this->wpdb->prepare(
			"SELECT * FROM " . $this->get_table_name( 'flats' ) . " WHERE id = %s",
			$flat_id
		), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// 2. If not found by ID, try parsing hyphenated name e.g. "A-101"
		if ( ! $flat && strpos( $flat_id, '-' ) !== false ) {
			$parts = explode( '-', $flat_id, 2 );
			$block = trim( $parts[0] );
			$num = trim( $parts[1] );
			// phpcs:disable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from get_table_name(); values prepared.
			$flat = $this->wpdb->get_row( $this->wpdb->prepare(
				"SELECT * FROM " . $this->get_table_name( 'flats' ) . " WHERE block = %s AND flat_number = %s",
				$block,
				$num
			), ARRAY_A );
			// phpcs:enable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		// 3. If still not found, try searching by flat_number = $flat_id
		if ( ! $flat ) {
			// phpcs:disable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from get_table_name(); value prepared.
			$flat = $this->wpdb->get_row( $this->wpdb->prepare(
				"SELECT * FROM " . $this->get_table_name( 'flats' ) . " WHERE flat_number = %s",
				$flat_id
			), ARRAY_A );
			// phpcs:enable WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		if ( $flat ) {
			$ids[] = $flat['id'];
			if ( ! empty( $flat['flat_number'] ) ) {
				$ids[] = $flat['flat_number'];
				if ( ! empty( $flat['block'] ) ) {
					$ids[] = $flat['block'] . '-' . $flat['flat_number'];
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
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

