<?php
/**
 * Class: DB Router
 * Handles data operations (CRUD) for the Society Management System.
 * Implements "Hybrid Storage":
 * - Reads always from Local JSON (fast).
 * - Writes go to Google Sheets (if connected) then Update Local.
 * - If Offline, Writes go directly to Local JSON.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_DB_Router {

	/**
	 * Path to the local data directory.
	 */
	private $data_dir;

	/**
	 * WordPress Database Instance.
	 */
	private $wpdb;

	/**
	 * List of supported "Tables" (Files/Modules).
	 */
	const TABLES = array( 'meta', 'flats', 'residents', 'vehicles', 'facilities', 'bookings', 'assets', 'notices', 'expenses', 'documents', 'daily_help', 'invoices', 'receipts', 'polls', 'votes', 'resident_history', 'requests', 'rules', 'rule_versions', 'rule_acknowledgments', 'rule_violations', 'rule_categories', 'audit_logs', 'notification_channels', 'notification_events', 'notification_templates', 'notification_preferences', 'notification_logs', 'inapp_notifications' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
		
		$uploads = wp_upload_dir();
		$this->data_dir = $uploads['basedir'] . '/society-govern-x/data/';

		if ( ! file_exists( $this->data_dir ) ) {
			wp_mkdir_p( $this->data_dir );
		}
	}

	/**
	 * Get Current Storage Mode.
	 */
	public function get_mode() {
		$mode = get_option( 'sgvx51_storage_mode', 'mysql' );
		// Ensure mode is never empty string
		return ! empty( $mode ) ? $mode : 'mysql';
	}

	/**
	 * Map Slug to Table Name.
	 */
	private function get_table_name( $slug ) {
		return $this->wpdb->prefix . 'society_governx_' . $slug;
	}

	public function get_table_name_debug( $slug ) {
		return $this->get_table_name( $slug );
	}

	public function get_data_dir() {
		return $this->data_dir;
	}

	/**
	 * GET Data (Read)
	 * 
	 * @param string $table Table name/slug.
	 * @param array  $args  Optional arguments: 'where' (assoc array), 'limit', 'offset', 'orderby', 'order'.
	 */
	public function get( $table, $args = array() ) {
		if ( $this->get_mode() === 'mysql' ) {
			return $this->get_mysql( $table, $args );
		}
		return $this->get_json( $table, $args );
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
				// Simple equality check: col = val
				$where_clauses[] = "`$col` = %s";
				$values[] = $val;
			}
		}

		if ( ! empty( $where_clauses ) ) {
			$query .= " WHERE " . implode( ' AND ', $where_clauses );
		}

		// 2. ORDER BY
		if ( ! empty( $args['orderby'] ) ) {
			// Whitelist check or simple sanitization needed? 
			// For now, strict escaping.
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
			$query = $this->wpdb->prepare( $query, $values );
		} else {
            // Keep query as string if no placeholders
        }
		
		// Log the query being executed
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// error_log( 'SGVX51 Executing Query: ' . $query );
		}
		
		$results = $this->wpdb->get_results( $query, ARRAY_A );
		
		// Handle potential query errors
		if ( $this->wpdb->last_error ) {
			error_log( 'SGVX51 DB Error (get_mysql ' . $table . '): ' . $this->wpdb->last_error . ' | Query: ' . $query );
			return array();
		}
		
		if ( ! $results ) {
			return array();
		}

		// Handle JSON fields
		foreach ( $results as $key => $row ) {
			if ( isset( $row['options'] ) ) {
				$results[ $key ]['options'] = json_decode( $row['options'], true ) ?: array();
			}
			if ( $table === 'daily_help' && isset( $row['flats_served'] ) ) {
				$results[ $key ]['flats_served'] = json_decode( $row['flats_served'], true ) ?: array();
			}
		}

		return $results;
	}

	private function get_json( $table, $args = array() ) {
		$file = $this->data_dir . $table . '.json';
		if ( file_exists( $file ) ) {
			$content = file_get_contents( $file );
			$data = json_decode( $content, true ) ?: array();
			
			// Standardize output to match MySQL (decode JSON strings)
			foreach($data as $k => $row) {
				if ( $table === 'polls' && isset( $row['options'] ) && is_string( $row['options'] ) ) {
					$data[ $k ]['options'] = json_decode( $row['options'], true ) ?: array();
				}
				if ( $table === 'invoices' && isset( $row['payments'] ) && is_string( $row['payments'] ) ) {
					$data[ $k ]['payments'] = json_decode( $row['payments'], true ) ?: array();
				}
			}

            // 1. Filter (Where)
            if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
                $data = array_filter( $data, function( $row ) use ( $args ) {
                    foreach ( $args['where'] as $col => $val ) {
                        if ( ! isset( $row[ $col ] ) || $row[ $col ] != $val ) {
                            return false;
                        }
                    }
                    return true;
                });
                $data = array_values( $data ); // Reindex
            }

            // 2. Sort
            if ( ! empty( $args['orderby'] ) ) {
                $col = $args['orderby'];
                $order = ( ! empty( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ) ? SORT_DESC : SORT_ASC;
                
                usort( $data, function( $a, $b ) use ( $col, $order ) {
                    $valA = $a[ $col ] ?? '';
                    $valB = $b[ $col ] ?? '';
                    if ( $valA == $valB ) return 0;
                    return ( $valA < $valB ) ? ( $order === SORT_ASC ? -1 : 1 ) : ( $order === SORT_ASC ? 1 : -1 );
                });
            }

            // 3. Limit/Offset
            if ( isset( $args['limit'] ) || isset( $args['offset'] ) ) {
                $offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
                $limit  = isset( $args['limit'] ) ? intval( $args['limit'] ) : null;
                $data = array_slice( $data, $offset, $limit );
            }

			return $data;
		}
		return array();
	}

	/**
	 * INSERT Row
	 */
	public function insert( $table, $data ) {
		if ( $this->get_mode() === 'mysql' ) {
			return $this->insert_mysql( $table, $data );
		}
		return $this->insert_json( $table, $data );
	}

	private function insert_json( $table, $data ) {
		if ( ! isset( $data['id'] ) ) $data['id'] = uniqid();
		
		$current_data = $this->get_json( $table );
		$current_data[] = $data;
		
		file_put_contents( $this->data_dir . $table . '.json', json_encode( $current_data, JSON_PRETTY_PRINT ) );
		return true;
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
		$columns = $this->wpdb->get_col( "DESCRIBE $table_name" );
		return $columns ?: array();
	}

	/**
	 * Verify and Create Column if missing (Self-Healing).
	 */
	public function verify_column( $table, $column, $definition ) {
		if ( $this->get_mode() !== 'mysql' ) return;

		$sql_table = $this->get_table_name( $table );
		$columns = $this->get_columns( $sql_table );
		
		if ( ! in_array( $column, $columns ) ) {
			$this->wpdb->query( "ALTER TABLE $sql_table ADD $column $definition" );
		}
	}

	/**
	 * UPDATE Row
	 */
	public function update( $table, $data, $where ) {
		if ( $this->get_mode() === 'mysql' ) {
			return $this->update_mysql( $table, $data, $where );
		}
		return $this->update_json( $table, $data, $where );
	}

	private function update_json( $table, $data, $where ) {
		$current_data = $this->get_json( $table );
		$updated = false;

		foreach ( $current_data as $key => $row ) {
			if ( ( isset( $row['id'] ) && $row['id'] == $where['id'] ) || 
				 ( isset( $row['__backendId'] ) && $row['__backendId'] == $where['id'] ) ) {
				$current_data[ $key ] = array_merge( $row, $data );
				$updated = true;
				break; 
			}
		}

		if ( $updated ) {
			file_put_contents( $this->data_dir . $table . '.json', json_encode( $current_data, JSON_PRETTY_PRINT ) );
		}
		return true;
	}

	private function update_mysql( $table, $data, $where ) {
		$sql_table = $this->get_table_name( $table );
		
		// 1. Filter out columns that don't exist in the table (Robustness Fix)
		$valid_columns = $this->get_columns( $sql_table );
		if ( ! empty( $valid_columns ) ) {
			$data = array_intersect_key( $data, array_flip( $valid_columns ) );
		}

		// 2. Handle special types (JSON Encode arrays)
		foreach ( $data as $key => $val ) {
			if ( is_array( $val ) ) {
				$data[ $key ] = json_encode( $val );
			}
		}

		// Log the update operation
		error_log( 'SGVX51 UPDATE Query: Table=' . $sql_table . ', Data=' . json_encode( $data ) . ', Where=' . json_encode( $where ) );

		$result = $this->wpdb->update( $sql_table, $data, $where );
		if ( false === $result ) {
			error_log( 'SGVX51 UPDATE Error: ' . $this->wpdb->last_error );
			return new WP_Error( 'db_error', $this->wpdb->last_error );
		}
		
		error_log( 'SGVX51 UPDATE Success: ' . $result . ' rows affected' );
		// Return the number of rows affected so callers can detect "no change" (0)
		return $result;
	}

	/**
	 * DELETE Row
	 */
	public function delete( $table, $where ) {
		if ( $this->get_mode() === 'mysql' ) {
			return $this->delete_mysql( $table, $where );
		}
		return $this->delete_json( $table, $where );
	}

	private function delete_json( $table, $where ) {
		$current_data = $this->get_json( $table );
		foreach ( $current_data as $key => $row ) {
			if ( ( isset( $row['id'] ) && $row['id'] == $where['id'] ) || 
				 ( isset( $row['__backendId'] ) && $row['__backendId'] == $where['id'] ) ) {
				unset( $current_data[ $key ] );
				break;
			}
		}
		file_put_contents( $this->data_dir . $table . '.json', json_encode( array_values( $current_data ), JSON_PRETTY_PRINT ) );
		return true;
	}

	private function delete_mysql( $table, $where ) {
		$sql_table = $this->get_table_name( $table );
		$result = $this->wpdb->delete( $sql_table, $where );
		if ( false === $result ) return new WP_Error( 'db_error', $this->wpdb->last_error );
		return true;
	}

	/**
	 * Get a single invoice by ID
	 *
	 * @param string $invoice_id The invoice ID
	 * @return array|false Invoice data or false if not found
	 */
	public function get_invoice( $invoice_id ) {
		$invoices = $this->get( 'invoices' );
		if ( is_wp_error( $invoices ) ) {
			return false;
		}

		if ( ! is_array( $invoices ) ) {
			return false;
		}

		foreach ( $invoices as $invoice ) {
			if ( isset( $invoice['id'] ) && $invoice['id'] === $invoice_id ) {
				return $invoice;
			}
		}

		return false;
	}

	/**
	 * Get resident by WordPress user ID
	 *
	 * @param int $wp_id The WordPress user ID
	 * @return array|false Resident data or false if not found
	 */
	public function get_resident_by_wp_id( $wp_id ) {
		$residents = $this->get( 'residents' );
		if ( is_wp_error( $residents ) ) {
			return false;
		}

		if ( ! is_array( $residents ) ) {
			return false;
		}

		foreach ( $residents as $resident ) {
			if ( isset( $resident['wp_user_id'] ) && (int) $resident['wp_user_id'] === (int) $wp_id ) {
				return $resident;
			}
		}

		return false;
	}

	/**
	 * Get a formatted flat name (e.g. A-101) by Flat ID.
	 *
	 * @param string $flat_id The Internal Flat ID.
	 * @return string Formatted flat name or the ID if not found.
	 */
	public function get_flat_display_name( $flat_id ) {
		if ( empty( $flat_id ) ) return 'N/A';
		
		$flats = $this->get( 'flats' );
		foreach ( $flats as $f ) {
			if ( $f['id'] === $flat_id ) {
				$block = ! empty( $f['block'] ) ? $f['block'] : '';
				$num = ! empty( $f['flat_number'] ) ? $f['flat_number'] : $f['id'];
				
				if ( ! empty( $block ) ) {
					return $block . '-' . $num;
				}
				return $num;
			}
		}
		
		return $flat_id;
	}
}
