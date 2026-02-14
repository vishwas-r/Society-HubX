<?php
/**
 * Class: Request Manager
 * Processes resident requests (Add, Edit, Delete).
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Request_Manager {

	private $db;
    private $modules = array();

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
        
        // Register AJAX Actions for Approvals
        add_action( 'wp_ajax_sgvx51_approve_request', array( $this, 'handle_ajax_approve' ) );
        add_action( 'wp_ajax_sgvx51_reject_request', array( $this, 'handle_ajax_reject' ) );
        add_action( 'wp_ajax_sgvx51_bulk_process_requests', array( $this, 'handle_bulk_process' ) );

        // Self-Heal Schema
        if ( is_admin() ) {
            $this->db->verify_column( 'requests', 'flat_no', 'varchar(50) NOT NULL' );
        }
	}

    /**
     * AJAX: Approve Request
     */
    public function handle_ajax_approve() {
        check_ajax_referer( 'sgvx51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $request_id = sanitize_text_field( $_POST['id'] );
        $result = $this->approve_request( $request_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( ['message' => $result->get_error_message()] );
        }

        wp_send_json_success( ['message' => 'Request approved successfully'] );
    }

    /**
     * AJAX: Reject Request
     */
    public function handle_ajax_reject() {
        check_ajax_referer( 'sgvx51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $request_id = sanitize_text_field( $_POST['id'] );
        $note = sanitize_textarea_field( $_POST['admin_note'] ?? '' );
        
        $result = $this->reject_request( $request_id, $note );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( ['message' => $result->get_error_message()] );
        }

        wp_send_json_success( ['message' => 'Request rejected successfully'] );
    }

    /**
     * AJAX: Bulk Process
     */
    public function handle_bulk_process() {
        check_ajax_referer( 'sgvx51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $ids = isset($_POST['ids']) ? array_map('sanitize_text_field', $_POST['ids']) : [];
        $action = sanitize_key($_POST['bulk_action']); // 'approve' or 'reject'
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        if(empty($ids)) wp_send_json_error(['message' => 'No items selected']);

        $success = 0;
        $failed = 0;

        foreach($ids as $id) {
            if($action === 'approve') {
                $res = $this->approve_request($id);
            } else {
                $res = $this->reject_request($id, $note);
            }

            if(is_wp_error($res)) {
                $failed++;
            } else {
                $success++;
            }
        }

        wp_send_json_success([
            'message' => "Successfully processed $success items. $failed failed.",
            'success_count' => $success,
            'failed_count' => $failed
        ]);
    }

    /**
     * Create a new request.
     */
    public function create_request( $module_slug, $action, $payload, $entity_id = '', $entity_type = '', $flat_no = '' ) {
        // 1. Validation 
        if(empty($module_slug) || empty($action)) return new WP_Error('invalid_args', 'Module and Action required');

        // Extract flat_no from payload if not provided
        if ( empty( $flat_no ) && isset( $payload['flat_no'] ) ) {
            $flat_no = $payload['flat_no'];
        }

        // 2. Prepare Data
        $data = array(
            'module'       => $module_slug,
            'flat_no'      => $flat_no,
            'request_type' => $action,
            'entity_type'  => !empty($entity_type) ? $entity_type : $module_slug, 
            'entity_id'    => $entity_id,
            'payload'      => json_encode( $payload ),
            'status'       => 'pending',
            'created_at'   => current_time( 'mysql' ),
            'created_by'   => get_current_user_id()
        );

        $data['id'] = uniqid('req_');
        
        $res = $this->db->insert( 'requests', $data );
        if($res && !is_wp_error($res)) {
            $this->log_audit('request_created', $module_slug, $data['id'], "Action: $action, Created by: " . $data['created_by']);
            return $data['id']; // Return the custom request ID
        }
        return $res;
    }

	/**
	 * Process a request by its ID.
	 */
	public function approve_request( $request_id ) {
		// error_log("SGVX51 Debug: approve_request called for ID: $request_id");
		$requests = $this->db->get( 'requests' );
		$target_request = null;

		foreach ( $requests as $index => $req ) {
			if ( isset( $req['id'] ) && $req['id'] === $request_id ) {
				$target_request = $req;
				break;
			}
		}

		// Fallback: If not found by Request ID, try finding a pending request for this Entity ID
		if ( ! $target_request ) {
			foreach ( $requests as $req ) {
				if ( isset($req['entity_id']) && $req['entity_id'] === $request_id && ($req['status'] ?? 'pending') === 'pending' ) {
					$target_request = $req;
					break;
				}
			}
		}

		if ( ! $target_request ) {
			return new WP_Error( 'not_found', 'Request not found.' );
		}

        $module_slug = !empty($target_request['module']) ? $target_request['module'] : ($target_request['entity_type'] ?? '');
        if ($module_slug === 'family') $module_slug = 'residents';
        if (in_array($module_slug, ['staff', 'staffs'])) $module_slug = 'daily_help';

		$action      = $target_request['request_type']; 
		$payload     = json_decode( $target_request['payload'], true );
		
        $module_instance = apply_filters( 'sgvx51_get_module_' . $module_slug, null );

        if ( ! empty( $target_request['entity_id'] ) ) {
            $payload['id'] = $target_request['entity_id'];
        }

        if ( ! $module_instance || ! ( $module_instance instanceof SGVX51_Module ) ) {
             return new WP_Error( 'no_module', "Module handler for '$module_slug' not found." );
        }

        $result = $module_instance->execute_request( $action, $payload );

		if ( ! is_wp_error( $result ) && $result !== false ) {
			$actual_request_id = $target_request['id'];
			$update_data = array(
				'status'       => 'approved',
				'processed_at' => current_time( 'mysql' ),
				'processed_by' => get_current_user_id(),
			);
			$this->db->update( 'requests', $update_data, array( 'id' => $actual_request_id ) );

            // Robust Fallback: Ensure the main table status is updated to 'approved' (or 'archived' for delete)
            if ( ! empty( $target_request['entity_id'] ) ) {
                $table_map = array(
                    'vehicles'   => 'vehicles',
                    'vehicle'    => 'vehicles',
                    'residents'  => 'residents',
                    'family'     => 'residents',
                    'staff'      => 'daily_help',
                    'staffs'     => 'daily_help',
                    'daily_help' => 'daily_help',
                    'documents'  => 'documents',
                );
                $target_table = $table_map[$module_slug] ?? '';
                if ( $target_table ) {
                    $new_status = ($action === 'delete') ? 'archived' : 'approved';
                    $this->db->update( $target_table, array( 'status' => $new_status ), array( 'id' => $target_request['entity_id'] ) );
                }
            }
            
            // If this was a deletion, mark any OTHER pending requests for this entity as 'processed' (stale)
            if ($action === 'delete' && !empty($target_request['entity_id'])) {
                $all_requests = $this->db->get('requests');
                foreach($all_requests as $other_req) {
                    if (isset($other_req['id']) && $other_req['id'] !== $actual_request_id && 
                        isset($other_req['entity_id']) && $other_req['entity_id'] === $target_request['entity_id'] && 
                        ($other_req['status'] ?? '') === 'pending') {
                        
                        $this->db->update('requests', [
                            'status' => 'cancelled',
                            'admin_note' => 'Auto-cancelled because the record was deleted.',
                            'processed_at' => current_time('mysql'),
                            'processed_by' => get_current_user_id()
                        ], ['id' => $other_req['id']]);
                    }
                }
            }

            $this->log_audit('request_approved', $module_slug, $actual_request_id, "Action: $action, Approved by: " . $update_data['processed_by']);

            // Trigger Resident Notification
            if ( class_exists('Society_GoVernX') && !empty($target_request['created_by']) ) {
                $sgvx = Society_GoVernX::get_instance();
                if ( isset($sgvx->notifications) ) {
                    $resident_name = 'Resident';
                    $residents = $this->db->get('residents');
                    foreach($residents as $res_obj) {
                        if(isset($res_obj['wp_user_id']) && (int)$res_obj['wp_user_id'] === (int)$target_request['created_by']) {
                            $resident_name = $res_obj['name'];
                            break;
                        }
                    }

                    $admin_user = get_userdata( $update_data['processed_by'] );
                    $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
                    $time_formatted = date('d M Y, h:i A', strtotime($update_data['processed_at']));

                    $sgvx->notifications->trigger('request_approved', $target_request['created_by'], [
                        'resident_name' => $resident_name,
                        'request_type'  => ucfirst(str_replace('_', ' ', $action)),
                        'admin_name'    => $admin_name,
                        'time'          => $time_formatted,
                        'details'       => "Your request for " . $module_slug . " was approved."
                    ], true, $update_data['processed_by']);
                }
            }

			return true;
		}

		return $result;
	}

	/**
	 * Reject a request.
	 */
	public function reject_request( $request_id, $note = '' ) {
		$requests = $this->db->get( 'requests' );
		$target_request = null;

		foreach ( $requests as $req ) {
			if ( isset( $req['id'] ) && $req['id'] === $request_id ) {
				$target_request = $req;
				break;
			}
		}

		if ( ! $target_request ) {
			// Try finding by entity_id
			foreach ( $requests as $req ) {
				if ( isset($req['entity_id']) && $req['entity_id'] === $request_id && ($req['status'] ?? 'pending') === 'pending' ) {
					$target_request = $req;
					break;
				}
			}
		}

		if ( ! $target_request ) return new WP_Error( 'not_found', 'Request not found' );

		$actual_request_id = $target_request['id'];
		$module_slug = !empty($target_request['module']) ? $target_request['module'] : ($target_request['entity_type'] ?? '');
		$action = $target_request['request_type'];
		$entity_id = $target_request['entity_id'] ?? '';

		// Status Update Logic: If it was an 'add' or 'upload' request, update the record status to 'rejected' in the module table 
		// so it remains visible to the resident as rejected, rather than disappearing.
		if ( in_array( $action, array( 'add', 'upload' ) ) && ! empty( $entity_id ) ) {
			$table_map = array(
				'vehicles'   => 'vehicles',
				'vehicle'    => 'vehicles',
				'residents'  => 'residents',
				'family'     => 'residents',
				'staff'      => 'daily_help',
				'staffs'     => 'daily_help',
				'daily_help' => 'daily_help',
				'facilities' => 'bookings',
				'documents'  => 'documents'
			);

			$table_name = $table_map[$module_slug] ?? '';
			if ( $table_name ) {
				$new_status = ($module_slug === 'documents' || $action === 'reject_delete') ? 'rejected' : 'rejected'; 
                // Mostly status 'rejected' is fine for all
				$this->db->update( $table_name, array( 'status' => 'rejected' ), array( 'id' => $entity_id ) );
			}
		} elseif ( $action === 'book' && $module_slug === 'facilities' && ! empty( $entity_id ) ) {
			$this->db->update( 'bookings', array( 'status' => 'rejected' ), array( 'id' => $entity_id ) );
		}

		$update_data = array(
			'status'       => 'rejected',
			'admin_note'   => $note,
			'processed_at' => current_time( 'mysql' ),
			'processed_by' => get_current_user_id(),
		);

		$res = $this->db->update( 'requests', $update_data, array( 'id' => $actual_request_id ) );

		if($res && !is_wp_error($res)) {
			$this->log_audit('request_rejected', $module_slug, $actual_request_id, "Action: $action, Target: $entity_id, Note: $note, Rejected by: " . $update_data['processed_by']);

            // Trigger Resident Notification
            if ( class_exists('Society_GoVernX') && !empty($target_request['created_by']) ) {
                $sgvx = Society_GoVernX::get_instance();
                if ( isset($sgvx->notifications) ) {
                    $resident_name = 'Resident';
                    $residents = $this->db->get('residents');
                    foreach($residents as $res_obj) {
                        if(isset($res_obj['wp_user_id']) && (int)$res_obj['wp_user_id'] === (int)$target_request['created_by']) {
                            $resident_name = $res_obj['name'];
                            break;
                        }
                    }

                    $admin_user = get_userdata( $update_data['processed_by'] );
                    $admin_name = $admin_user ? $admin_user->display_name : 'Admin';
                    $time_formatted = date('d M Y, h:i A', strtotime($update_data['processed_at']));

                    $sgvx->notifications->trigger('request_rejected', $target_request['created_by'], [
                        'resident_name' => $resident_name,
                        'request_type'  => ucfirst(str_replace('_', ' ', $action)),
                        'admin_name'    => $admin_name,
                        'time'          => $time_formatted,
                        'admin_note'    => $note ?: 'No specific reason provided.'
                    ], true, $update_data['processed_by']);
                }
            }
		}

		return $res;
	}

    /**
     * Log an audit event.
     */
    public function log_audit( $action, $entity_type, $entity_id, $details = '' ) {
        $log_data = array(
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'details'     => is_array($details) ? json_encode($details) : (string)$details,
            'created_at'  => current_time( 'mysql' )
        );
        return $this->db->insert( 'audit_logs', $log_data );
    }

    /**
     * Get original data for an entity associated with a request.
     */
    public function get_original_data( $request ) {
        $module = !empty($request['module']) ? $request['module'] : ($request['entity_type'] ?? '');
        $entity_id = $request['entity_id'] ?? '';

        if ( empty( $entity_id ) ) {
            return null;
        }

        $table_map = array(
            'vehicles'   => 'vehicles',
            'vehicle'    => 'vehicles',
            'residents'  => 'residents',
            'family'     => 'residents',
            'staff'      => 'daily_help',
            'staffs'     => 'daily_help',
            'daily_help' => 'daily_help',
        );

        $target_table = $table_map[$module] ?? '';
        if ( ! $target_table ) {
            return null;
        }

        $all = $this->db->get( $target_table );
        foreach ( $all as $row ) {
            if ( isset( $row['id'] ) && $row['id'] === $entity_id ) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Unified method to get data for a module (Active + Pending + Archived)
     */
    public function get_unified_data( $module_slug, $main_table, $history_table = '' ) {
        $active  = $this->db->get( $main_table );
        $pending = array_filter( $this->db->get( 'requests' ), function($r) use ($module_slug) {
            return ($r['module'] === $module_slug || $r['entity_type'] === $module_slug) && $r['status'] === 'pending';
        });

        // Attach original data for pending edits
        $pending = array_map( function( $r ) {
            if ( $r['request_type'] === 'edit' ) {
                $r['original_data'] = $this->get_original_data( $r );
            }
            return $r;
        }, $pending );

        $archived = $history_table ? $this->db->get( $history_table ) : array();

        return array(
            'active'   => $active,
            'pending'  => array_values($pending),
            'archived' => $archived
        );
    }
}
