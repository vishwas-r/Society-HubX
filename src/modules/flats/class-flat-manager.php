<?php
/**
 * Module: Flat Manager
 * Handles Flats/Units Master Data (Block, Number, Parking).
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Flat_Manager {

	private $db;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		
		// AJAX
		add_action( 'wp_ajax_SNESTX51_add_flat', array( $this, 'handle_add_flat' ) );
		add_action( 'wp_ajax_SNESTX51_edit_flat', array( $this, 'handle_edit_flat' ) );
		add_action( 'wp_ajax_SNESTX51_delete_flat', array( $this, 'handle_delete_flat' ) );
		add_action( 'wp_ajax_SNESTX51_restore_flat', array( $this, 'handle_restore_flat' ) );
		add_action( 'wp_ajax_SNESTX51_hard_delete_flat', array( $this, 'handle_hard_delete_flat' ) );

		add_action( 'admin_post_SNESTX51_add_flat', array( $this, 'handle_add_flat' ) );
		add_action( 'admin_post_SNESTX51_edit_flat', array( $this, 'handle_edit_flat' ) );
		add_action( 'admin_post_SNESTX51_delete_flat', array( $this, 'handle_delete_flat' ) );
		add_action( 'admin_post_SNESTX51_restore_flat', array( $this, 'handle_restore_flat' ) );
		add_action( 'admin_post_SNESTX51_bulk_import_flats', array( $this, 'handle_bulk_import' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Society Units / Flats',
			'Flats & Units',
			'read', // Granular check inside render_page
			'snestx51-flats',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle Single Add.
	 */
	public function handle_add_flat() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'SNESTX51_add_flat_nonce' );
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_add_flat_nonce' ) ) wp_die( 'Security check failed' );
        }

		$res = $this->process_add_flat( $_POST );

        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }
            wp_send_json_success( array( 'message' => 'Flat added successfully' ) );
            exit;
        }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-flats&success=1' ) );
		exit;
	}

	/**
	 * Handle Edit.
	 */
	public function handle_hard_delete_flat() {
		check_ajax_referer( 'SNESTX51_hard_delete_flat_nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! (new SNESTX51_RBAC_Manager())->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$flat_id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$res = $this->db->delete( 'flats', array( 'id' => $flat_id ) );

		if ( $res ) {
			wp_send_json_success( array( 'message' => 'Flat permanently deleted' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Failed to delete flat' ) );
		}
	}

	public function handle_edit_flat() {
		if ( wp_doing_ajax() ) {
            if ( !isset($_POST['_wpnonce']) || !wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'SNESTX51_add_flat_nonce') ) {
                wp_send_json_error(['message' => 'Nonce verification failed'], 403);
                exit;
            }
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_add_flat_nonce' ) ) wp_die( 'Security check failed' );
        }

		$data = array(
			'block' => isset( $_POST['block'] ) ? sanitize_text_field( wp_unslash( $_POST['block'] ) ) : '',
			'flat_number' => isset( $_POST['flat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_number'] ) ) : '',
			'floor' => isset( $_POST['floor'] ) ? sanitize_text_field( wp_unslash( $_POST['floor'] ) ) : '',
			'sq_foot' => isset( $_POST['sq_foot'] ) ? floatval( wp_unslash( $_POST['sq_foot'] ) ) : 0,
			'parking_slot' => isset( $_POST['parking_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['parking_slot'] ) ) : '',
			'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
			'status' => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
			'parking_status' => isset( $_POST['parking_status'] ) ? sanitize_text_field( wp_unslash( $_POST['parking_status'] ) ) : '',
		);

		// Determine the original ID (hidden field) and the new ID (based on edited values)
		$original_id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$new_id = $data['block'] . '-' . $data['flat_number'];

		// If the user changed block/flat_number, set the new id value in data so it updates the record
		if ( ! empty( $original_id ) && $original_id !== $new_id ) {
			$data['id'] = $new_id;
		}

		$where_id = ! empty( $original_id ) ? $original_id : $new_id;

		error_log( 'SNESTX51 handle_edit_flat: Attempting to update flat. original_id=' . $original_id . ', new_id=' . $new_id . ', where_id=' . $where_id . ', data=' . json_encode( $data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

		$res = $this->db->update( 'flats', $data, array( 'id' => $where_id ) );

		error_log( 'SNESTX51 handle_edit_flat: Update result: ' . json_encode( $res ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) wp_die( 'Unauthorized' );

	    if ( wp_doing_ajax() ) {
	        if ( is_wp_error( $res ) ) {
	            wp_send_json_error( array( 'message' => $res->get_error_message() ) );
	        } else {
	            $rows = is_int( $res ) ? $res : null;
	            if ( is_int( $rows ) && $rows === 0 ) {
	                wp_send_json_success( array( 'message' => 'No changes detected', 'rows_affected' => 0 ) );
	            } else {
	                wp_send_json_success( array( 'message' => 'Flat updated successfully', 'rows_affected' => $rows ) );
	            }
	        }
	        exit;
	    }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-flats&success=1&msg=Updated' ) );
		exit;
	}

	public function handle_delete_flat() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'SNESTX51_delete_flat_nonce' );
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_delete_flat_nonce' ) ) wp_die( 'Security check failed' );
        }

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) wp_die( 'Unauthorized' );

		$id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$res = $this->db->update( 'flats', ['status' => 'archived'], array( 'id' => $id ) );

        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }
            wp_send_json_success( array( 'message' => 'Flat archived successfully' ) );
            exit;
        }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-flats&status=deleted' ) );
		exit;
	}

	public function handle_restore_flat() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'SNESTX51_add_flat_nonce' );
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_add_flat_nonce' ) ) wp_die( 'Security check failed' );
        }

		$id = isset($_POST['flat_id']) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$res = $this->db->update( 'flats', array( 'status' => 'vacant' ), array( 'id' => $id ) );

        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) {
                wp_send_json_error( array( 'message' => $res->get_error_message() ) );
            }
            wp_send_json_success( array( 'message' => 'Flat restored successfully' ) );
            exit;
        }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-flats&success=1' ) );
		exit;
	}

	/**
	 * Handle Bulk Import.
	 */
	public function handle_bulk_import() {
		if ( ! check_admin_referer( 'SNESTX51_bulk_import_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSV text data parsed and columns sanitized individually.
		$csv_data = isset( $_POST['csv_data'] ) ? trim( wp_unslash( $_POST['csv_data'] ) ) : '';
		$rows = explode( "\n", $csv_data );
		$count = 0;
		$errors = 0;

		foreach ( $rows as $row ) {
			// Format: Block, FlatNo, Type, Floor, Parking, SqFoot
			$cols = str_getcsv( trim( $row ) );
			if ( count( $cols ) < 2 ) continue;

			$p = array(
				'block'        => sanitize_text_field( $cols[0] ),
				'flat_number'  => sanitize_text_field( $cols[1] ),
				'type'         => isset( $cols[2] ) ? sanitize_text_field( $cols[2] ) : '2BHK',
				'floor'        => isset( $cols[3] ) ? sanitize_text_field( $cols[3] ) : '',
				'parking_slot' => isset( $cols[4] ) ? sanitize_text_field( $cols[4] ) : '',
				'sq_foot'      => isset( $cols[5] ) ? floatval( $cols[5] ) : 0.00,
			);

			$res = $this->process_add_flat( $p );
			if ( is_wp_error( $res ) ) {
				$errors++;
			} else {
				$count++;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-flats&imported=' . $count . '&errors=' . $errors ) );
		exit;
	}

	/**
	 * Core Logic to Add Flat.
	 */
	private function process_add_flat( $post_data ) {
		$data = array(
			'block'        => $post_data['block'],
			'flat_number'  => $post_data['flat_number'],
			'floor'        => isset($post_data['floor']) ? $post_data['floor'] : '',
			'sq_foot'      => isset($post_data['sq_foot']) ? floatval( $post_data['sq_foot'] ) : 0.00,
			'parking_slot' => isset($post_data['parking_slot']) ? $post_data['parking_slot'] : '',
			'type'         => isset($post_data['type']) ? $post_data['type'] : '2BHK',
			'status'       => isset($post_data['status']) ? sanitize_text_field($post_data['status']) : 'vacant',
			'parking_status'=> isset($post_data['parking_status']) ? sanitize_text_field($post_data['parking_status']) : 'vacant',
		);

		// Generate Unique Display Key
		$data['id'] = $data['block'] . '-' . $data['flat_number']; // e.g. A-101

		// Check duplicate
		$existing = $this->db->get( 'flats' );
		foreach ( $existing as $f ) {
			if ( $f['id'] === $data['id'] ) {
				return new WP_Error('duplicate', 'Flat exists');
			}
		}

		return $this->db->insert( 'flats', $data );
	}

	public function render_page() {
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'flats_view' ) ) {
            wp_die( 'You do not have permission to view flats.' );
        }
		SNESTX51_Admin_App::render_view('flats');
	}
}
