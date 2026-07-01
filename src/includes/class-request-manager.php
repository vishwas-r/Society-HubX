<?php
/**
 * Class: Request Manager
 * Processes resident requests (Add, Edit, Delete).
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Request_Manager {

	private $db;
    private $modules = array();

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
        
        // Register AJAX Actions for Approvals
        add_action( 'wp_ajax_SNESTX51_approve_request', array( $this, 'handle_ajax_approve' ) );
        add_action( 'wp_ajax_SNESTX51_reject_request', array( $this, 'handle_ajax_reject' ) );
        add_action( 'wp_ajax_SNESTX51_bulk_process_requests', array( $this, 'handle_bulk_process' ) );

        // Self-Heal Schema
        if ( is_admin() ) {
            $this->db->verify_column( 'requests', 'flat_no', 'varchar(50) NOT NULL' );
            $this->db->verify_column( 'requests', 'approvals', 'TEXT' ); // Store approval log
        }
	}

    /**
     * Get Resident with Secretary role.
     */
    public function get_secretary() {
        $residents = $this->db->get( 'residents', array( 'load_relations' => true ) );
        foreach ( $residents as $r ) {
            $roles = isset( $r['roles'] ) ? ( is_array( $r['roles'] ) ? $r['roles'] : explode( ',', $r['roles'] ) ) : array();
            foreach ( $roles as $role ) {
                if ( stripos( $role, 'Secretary' ) !== false ) {
                    return $r;
                }
            }
        }
        return null;
    }

    /**
     * Get Resident with Treasurer role.
     */
    public function get_treasurer() {
        $residents = $this->db->get( 'residents', array( 'load_relations' => true ) );
        foreach ( $residents as $r ) {
            $roles = isset( $r['roles'] ) ? ( is_array( $r['roles'] ) ? $r['roles'] : explode( ',', $r['roles'] ) ) : array();
            foreach ( $roles as $role ) {
                if ( stripos( $role, 'Treasurer' ) !== false ) {
                    return $r;
                }
            }
        }
        return null;
    }

    /**
     * AJAX: Approve Request
     */
    public function handle_ajax_approve() {
        check_ajax_referer( 'SNESTX51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $request_id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
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
        check_ajax_referer( 'SNESTX51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $request_id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';
        
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
        check_ajax_referer( 'SNESTX51_request_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( ['message' => 'Unauthorized'], 403 );

        $ids = isset($_POST['ids']) ? array_map('sanitize_text_field', wp_unslash($_POST['ids'])) : [];
        $action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : ''; // 'approve' or 'reject'
        $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

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
        // Normalize flat_no to display name if it looks like an ID
        if ( ! empty( $flat_no ) && ( strpos( $flat_no, 'flat_' ) === 0 || is_numeric( $flat_no ) ) ) {
            $flat_no = $this->db->get_flat_display_name( $flat_no );
        }

        // Also normalize within payload for consistency in detail view
        if ( isset( $payload['flat_no'] ) && ( strpos( $payload['flat_no'], 'flat_' ) === 0 || is_numeric( $payload['flat_no'] ) ) ) {
            $payload['flat_no'] = $this->db->get_flat_display_name( $payload['flat_no'] );
        }

        $data = array(
            'module'       => $module_slug,
            'block'        => $payload['block'] ?? '',
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
		// error_log("SNESTX51 Debug: approve_request called for ID: $request_id"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		$requests = $this->db->get( 'requests' );
		$target_request = null;

		foreach ( $requests as $index => $req ) {
			if ( isset( $req['id'] ) && $req['id'] === $request_id ) {
				$target_request = $req;
				break;
			}
		}

		// Fallback: If not found by Request ID, try finding ANY pending request for this Entity ID
		if ( ! $target_request ) {
			foreach ( $requests as $req ) {
                $status = $req['status'] ?? 'pending';
				if ( isset($req['entity_id']) && $req['entity_id'] === $request_id && strpos($status, 'pending') === 0 ) {
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
		$payload     = is_array($target_request['payload'] ?? null) ? $target_request['payload'] : json_decode( $target_request['payload'], true );
		
        $module_instance = apply_filters( 'SNESTX51_get_module_' . $module_slug, null );

        if ( ! empty( $target_request['entity_id'] ) ) {
            $payload['id'] = $target_request['entity_id'];
        }

        if ( ! $module_instance || ! ( $module_instance instanceof SNESTX51_Module ) ) {
             return new WP_Error( 'no_module', "Module handler for '$module_slug' not found." );
        }

        // 3. Multi-stage Approval Logic for Finance & Expenses (and potentially Accounts)
        $current_user_id = get_current_user_id();
        $is_secretary = false;
        $is_treasurer = false;
        
        $residents = $this->db->get( 'residents', array( 'load_relations' => true ) );
        foreach ( $residents as $res_obj ) {
            if ( isset( $res_obj['wp_user_id'] ) && (int)$res_obj['wp_user_id'] === (int)$current_user_id ) {
                $roles = isset( $res_obj['roles'] ) ? ( is_array( $res_obj['roles'] ) ? $res_obj['roles'] : explode( ',', $res_obj['roles'] ) ) : array();
                foreach ( $roles as $role ) {
                    if ( stripos( $role, 'Secretary' ) !== false ) $is_secretary = true;
                    if ( stripos( $role, 'Treasurer' ) !== false ) $is_treasurer = true;
                }
                break;
            }
        }

        // Admin fallback - if admin is approving, bypass multi-stage
        if ( current_user_can( 'manage_options' ) ) {
            $is_secretary = true; $is_treasurer = true;
        }

        $status = $target_request['status'] ?? 'pending';

        // Check stages for finance/expenses/accounts if multi-stage is applicable
        if ( in_array( $module_slug, array( 'finance', 'expenses', 'accounts' ) ) ) {
            // IF ADMIN (manage_options), bypass the stages and proceed to final approval
            if ( current_user_can( 'manage_options' ) ) {
                $status = 'pending_treasurer'; // Force state to final stage for immediate execution
            } else {
                // Logic: pending -> pending_secretary -> pending_treasurer -> approved
                
                // Stage 1: Secretary Approval (Initial)
                if ( $status === 'pending' || $status === 'pending_secretary' ) {
                    // If not Secretary, block. 
                    if ( ! $is_secretary ) return new WP_Error( 'unauthorized', 'Only Secretary can perform initial approval.' );
                    
                    // If NOT Treasurer too, just move to next stage and return.
                    if ( ! $is_treasurer ) {
                        $update_data = array(
                            'status' => 'pending_treasurer',
                            'approvals' => ($target_request['approvals'] ?? '') . "\nSecretary Approved at " . current_time('mysql') . " by User ID " . $current_user_id
                        );
                        $this->db->update( 'requests', $update_data, array( 'id' => $target_request['id'] ) );
                        
                        // Sync expense status
                        if ( $module_slug === 'expenses' && ! empty( $target_request['entity_id'] ) ) {
                            $this->db->update( 'expenses', array( 'status' => 'pending_treasurer' ), array( 'id' => $target_request['entity_id'] ) );
                        }

                        $this->log_audit( 'request_sec_approved', $module_slug, $target_request['id'], "Secretary approved. Moving to Treasurer." );
                        return true; 
                    }
                    
                    // If IS Treasurer (Admin), fall through to Stage 2 check immediately
                    $status = 'pending_treasurer'; 
                }

                // Stage 2: Treasurer Approval (Final)
                if ( $status === 'pending_treasurer' ) {
                    if ( ! $is_treasurer ) return new WP_Error( 'unauthorized', 'Only Treasurer can perform final approval.' );
                    // All clear - proceed to execution below
                }
            }
        }

        // Logic for invoice payment approval
        if ( $module_slug === 'accounts' ) {
            
            // Re-map request payload variables for perform_record_payment format
            $payment_data = array(
                'request_id' => $target_request['id'],
                'invoice_id' => $payload['invoice_id'] ?? ($target_request['entity_id'] ?? ''),
                'amount'     => $payload['amount'] ?? 0,
                'method'     => $payload['method'] ?? 'Manual',
                'reference'  => $payload['reference'] ?? '',
                'date'       => $payload['date'] ?? current_time('Y-m-d'),
                'flat_no'    => $payload['flat_no'] ?? ($target_request['flat_no'] ?? ''),
                'block'      => $payload['block'] ?? ($target_request['block'] ?? '')
            );
            
            // Try using the module instance first
            if ( $module_instance && method_exists( $module_instance, 'perform_record_payment' ) ) {
                $payment_result = $module_instance->perform_record_payment( $payment_data );
            } else {
                // Fallback direct instantiation if filter failed
                require_once SNESTX51_PLUGIN_DIR . 'modules/finance/class-account-manager.php';
                $am = new SNESTX51_Account_Manager();
                $payment_result = $am->perform_record_payment( $payment_data );
            }
            
            if ( is_wp_error( $payment_result ) ) {
                return $payment_result;
            }
            
            $result = true;
        } else {
            $result = $module_instance->execute_request( $action, $payload );
        }

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
            if ( class_exists('Society_NestX') && !empty($target_request['created_by']) ) {
                $snestx = Society_NestX::get_instance();
                if ( isset($snestx->notifications) ) {
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
                    $time_formatted = gmdate('d M Y, h:i A', strtotime($update_data['processed_at']));

                    // Enhancement: Extract category/type for better notification title
                    $request_desc = ucfirst(str_replace('_', ' ', $action));
                    if (!empty($payload['category'])) {
                        $request_desc = $payload['category'];
                    } elseif ($module_slug === 'general') {
                        $request_desc = 'General Request';
                    }

                    $snestx->notifications->trigger('request_approved', $target_request['created_by'], [
                        'resident_name' => $resident_name,
                        'request_type'  => $request_desc . " (#" . substr($target_request['id'], -6) . ")",
                        'admin_name'    => $admin_name,
                        'time'          => $time_formatted,
                        'details'       => ($module_slug === 'accounts') ? "Your payment of ₹" . ($payload['amount']??'') . " has been approved." : "Your request for " . $request_desc . " was approved."
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
		$payload = is_array($target_request['payload'] ?? null) ? $target_request['payload'] : json_decode( $target_request['payload'], true );

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
            if ( class_exists('Society_NestX') && !empty($target_request['created_by']) ) {
                $snestx = Society_NestX::get_instance();
                if ( isset($snestx->notifications) ) {
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
                    $time_formatted = gmdate('d M Y, h:i A', strtotime($update_data['processed_at']));

                    // Enhancement: Extract category/type for better notification title
                    $request_desc = ucfirst(str_replace('_', ' ', $action));
                    if (!empty($payload['category'])) {
                        $request_desc = $payload['category'];
                    } elseif ($module_slug === 'general') {
                        $request_desc = 'General Request';
                    }

                    $snestx->notifications->trigger('request_rejected', $target_request['created_by'], [
                        'resident_name' => $resident_name,
                        'request_type'  => $request_desc . " (#" . substr($target_request['id'], -6) . ")",
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
    public function get_original_data( $request, $load_relations = false ) {
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

        $all = $this->db->get( $target_table, array( 'load_relations' => $load_relations ) );
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
    public function get_unified_data( $module_slug, $main_table, $history_table = '', $load_relations = false ) {
        $active  = $this->db->get( $main_table, array( 'load_relations' => $load_relations ) );
        $pending = array_filter( $this->db->get( 'requests', array( 'load_relations' => $load_relations ) ), function($r) use ($module_slug) {
            return ($r['module'] === $module_slug || $r['entity_type'] === $module_slug) && $r['status'] === 'pending';
        });

        // Attach original data for pending edits
        $pending = array_map( function( $r ) use ( $load_relations ) {
            if ( $r['request_type'] === 'edit' ) {
                $r['original_data'] = $this->get_original_data( $r, $load_relations );
            }
            return $r;
        }, $pending );

        $archived = $history_table ? $this->db->get( $history_table, array( 'load_relations' => $load_relations ) ) : array();

        return array(
            'active'   => $active,
            'pending'  => array_values($pending),
            'archived' => $archived
        );
    }
}
