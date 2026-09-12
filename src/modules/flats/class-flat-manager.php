<?php
/**
 * Module: Flat Manager
 * Handles Flats/Units Master Data (Block, Number, Parking).
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Flat_Manager {

	private $db;

	public function __construct() {
		$this->db = new NAMMASOCIETY51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		
		// AJAX
		add_action( 'wp_ajax_nammasociety51_add_flat', array( $this, 'handle_add_flat' ) );
		add_action( 'wp_ajax_nammasociety51_edit_flat', array( $this, 'handle_edit_flat' ) );
		add_action( 'wp_ajax_nammasociety51_get_flat', array( $this, 'handle_get_flat' ) );
		add_action( 'wp_ajax_nammasociety51_delete_flat', array( $this, 'handle_delete_flat' ) );
		add_action( 'wp_ajax_nammasociety51_restore_flat', array( $this, 'handle_restore_flat' ) );
		add_action( 'wp_ajax_nammasociety51_hard_delete_flat', array( $this, 'handle_hard_delete_flat' ) );

		add_action( 'admin_post_nammasociety51_add_flat', array( $this, 'handle_add_flat' ) );
		add_action( 'admin_post_nammasociety51_edit_flat', array( $this, 'handle_edit_flat' ) );
		add_action( 'admin_post_nammasociety51_delete_flat', array( $this, 'handle_delete_flat' ) );
		add_action( 'admin_post_nammasociety51_restore_flat', array( $this, 'handle_restore_flat' ) );
		add_action( 'admin_post_nammasociety51_bulk_import_flats', array( $this, 'handle_bulk_import' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'nammasociety51-settings',
			'Society Units / Flats',
			'Flats & Units',
			'read', // Granular check inside render_page
			'nammasociety51-flats',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle Single Add.
	 */
	public function handle_add_flat() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'nammasociety51_add_flat_nonce' );
		} else {
			if ( ! check_admin_referer( 'nammasociety51_add_flat_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$post_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
		$res = $this->process_add_flat( $post_data );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => 'Flat added successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-flats&success=1' ) );
		exit;
	}

	/**
	 * Handle Get Single Flat via AJAX.
	 */
	public function handle_get_flat() {
		check_ajax_referer( 'nammasociety51_add_flat_nonce' );
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_view' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$flat_id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		if ( empty( $flat_id ) ) {
			wp_send_json_error( array( 'message' => 'Flat ID missing' ), 400 );
		}

		$flats = $this->db->get( 'flats', array( 'id' => $flat_id ) );
		if ( ! empty( $flats ) ) {
			wp_send_json_success( $flats[0] );
		}
		wp_send_json_error( array( 'message' => 'Flat not found' ), 404 );
	}

	/**
	 * Handle Edit.
	 */
	public function handle_hard_delete_flat() {
		check_ajax_referer( 'nammasociety51_hard_delete_flat_nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! (new NAMMASOCIETY51_RBAC_Manager())->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
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
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'nammasociety51_add_flat_nonce' ) ) {
				wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
				exit;
			}
		} else {
			if ( ! check_admin_referer( 'nammasociety51_add_flat_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$data = array(
			'block'          => isset( $_POST['block'] ) ? sanitize_text_field( wp_unslash( $_POST['block'] ) ) : '',
			'flat_number'    => isset( $_POST['flat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_number'] ) ) : '',
			'floor'          => isset( $_POST['floor'] ) ? sanitize_text_field( wp_unslash( $_POST['floor'] ) ) : '',
			'sq_foot'        => isset( $_POST['sq_foot'] ) ? floatval( wp_unslash( $_POST['sq_foot'] ) ) : 0,
			'parking_slot'   => isset( $_POST['parking_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['parking_slot'] ) ) : '',
			'type'           => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
			'status'         => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '',
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

		$res = $this->db->update( 'flats', $data, array( 'id' => $where_id ) );

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

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-flats&success=1&msg=Updated' ) );
		exit;
	}

	public function handle_delete_flat() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'nammasociety51_delete_flat_nonce' );
		} else {
			if ( ! check_admin_referer( 'nammasociety51_delete_flat_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$res = $this->db->update( 'flats', array( 'status' => 'archived' ), array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => 'Flat archived successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-flats&status=deleted' ) );
		exit;
	}

	public function handle_restore_flat() {
		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'nammasociety51_add_flat_nonce' );
		} else {
			if ( ! check_admin_referer( 'nammasociety51_add_flat_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
			}
			wp_die( 'Unauthorized' );
		}

		$id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		$res = $this->db->update( 'flats', array( 'status' => 'vacant' ), array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => 'Flat restored successfully' ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-flats&success=1' ) );
		exit;
	}

	/**
	 * Handle Bulk Import.
	 */
	public function handle_bulk_import() {
		if ( ! check_admin_referer( 'nammasociety51_bulk_import_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'flats_manage' ) ) {
			wp_die( 'Unauthorized' );
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

		wp_safe_redirect( admin_url( 'admin.php?page=nammasociety51-flats&imported=' . $count . '&errors=' . $errors ) );
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
        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'flats_view' ) ) {
            wp_die( 'You do not have permission to view flats.' );
        }
		NAMMASOCIETY51_Admin_App::render_view('flats');
	}
}

