<?php
/**
 * Module: Resident Manager
 * Handles the "Residents" table and WP User Sync.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Resident_Manager implements SNESTX51_Module {

	private $db;
	private $drive;
	private $media;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		$this->drive = new SNESTX51_Drive_Manager();
		$this->media = new SNESTX51_Media_Manager();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		// AJAX Actions
		add_action( 'wp_ajax_SNESTX51_add_resident', array( $this, 'handle_add_resident' ) );
		add_action( 'wp_ajax_SNESTX51_edit_resident', array( $this, 'handle_edit_resident' ) );
		add_action( 'wp_ajax_SNESTX51_delete_resident', array( $this, 'handle_delete_resident' ) );
		add_action( 'wp_ajax_SNESTX51_restore_resident', array( $this, 'handle_restore_resident' ) );
		add_action( 'wp_ajax_SNESTX51_move_to_history', array( $this, 'handle_move_to_history' ) );
		add_action( 'wp_ajax_SNESTX51_delete_history', array( $this, 'handle_delete_history' ) );

		// Legacy Admin Post Actions (optional cleanup if no longer used)
		add_action( 'admin_post_SNESTX51_add_resident', array( $this, 'handle_add_resident' ) );
		add_action( 'admin_post_SNESTX51_edit_resident', array( $this, 'handle_edit_resident' ) );
		add_action( 'admin_post_SNESTX51_delete_resident', array( $this, 'handle_delete_resident' ) );
		add_action( 'admin_post_SNESTX51_restore_resident', array( $this, 'handle_restore_resident' ) );
		add_action( 'admin_post_SNESTX51_move_to_history', array( $this, 'handle_move_to_history' ) );
		add_action( 'admin_post_SNESTX51_delete_history', array( $this, 'handle_delete_history' ) );
		add_action( 'admin_post_SNESTX51_bulk_import_residents', array( $this, 'handle_bulk_import' ) );


		// Self-Heal Schema (Ensure columns exist)
		if ( is_admin() ) {
			$this->db->verify_column( 'residents', 'roles', 'TEXT NOT NULL' );
			$this->db->verify_column( 'residents', 'wp_user_id', 'BIGINT(20) DEFAULT 0 NOT NULL' );
            $this->db->verify_column( 'residents', 'relation', 'VARCHAR(50) DEFAULT "" NOT NULL' );
            $this->db->verify_column( 'residents', 'dob', 'DATE DEFAULT NULL' );
            $this->db->verify_column( 'residents', 'blood_group', 'VARCHAR(10) DEFAULT "" NOT NULL' );
		}
        
        // Register Module
        add_filter( 'SNESTX51_get_module_residents', array( $this, 'get_instance' ) );
        add_filter( 'SNESTX51_get_module_family', array( $this, 'get_instance' ) ); // Handle Family Requests
	}

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'residents';
    }

    public function execute_request( $action, $payload ) {
    $payload = (array) $payload;
    if ( $action === 'add' ) {
        $id = $payload['resident_id'] ?? ($payload['id'] ?? '');
        $all = $this->db->get('residents');
        $exists = false;
        
        if ( ! empty( $id ) ) {
            foreach($all as $r) { 
                if( isset($r['id']) && $r['id'] == $id ) { 
                    $exists = true; 
                    break; 
                } 
            }
        }

        if($exists) {
            return $this->db->update('residents', ['status' => 'approved'], ['id' => $id]);
        } else {
            return $this->process_add_resident( $payload );
        }
    } elseif ( $action === 'edit' ) {
        return $this->perform_edit_resident( $payload );
    } elseif ( $action === 'delete' ) {
        return $this->perform_delete_resident( $payload );
    }
    return new WP_Error( 'invalid_action', 'Unknown action' );
}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Residents Directory',
			'Residents',
			'read', // Granular check in render_page
			'snestx51-residents',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle Single Add.
	 */
	public function handle_add_resident() {
		if ( wp_doing_ajax() ) {
            ob_start();
            check_ajax_referer( 'SNESTX51_resident_nonce' );
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_resident_nonce' ) ) wp_die( 'Security check failed' );
        }
	
    // IF ADMIN: Immediate
   $rbac = Society_NestX::get_instance()->rbac;
   if ( $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) {
       $_POST['status'] = 'approved';
       $res = $this->process_add_resident( $_POST );
       
       if ( wp_doing_ajax() ) {
           // Aggressive Clean
           while ( ob_get_level() > 0 ) { ob_end_clean(); }

           if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
           wp_send_json_success(['message' => 'Resident added successfully']);
           exit;
       }
   } else {
       $_POST['status'] = 'pending';
       $_POST['id'] = uniqid('res_');
       $this->process_add_resident( $_POST );

       require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
       $rm = new SNESTX51_Request_Manager();
       $sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
       $res = $rm->create_request( 'residents', 'add', $sanitized_post, $sanitized_post['id'], 'residents', $sanitized_post['flat_no'] );
       
        if ( wp_doing_ajax() ) {
           $debug = ob_get_clean();
           if(!empty($debug)) error_log('SNESTX Resident Add Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
           
           // Aggressive Clean
           while ( ob_get_level() > 0 ) { ob_end_clean(); }
           
           if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
           wp_send_json_success(['message' => 'Resident added and submitted for approval']);
       }
   }

	wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=added' ) );
	exit;
}

	public function handle_edit_resident() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed immediately below.
		if ( wp_doing_ajax() ) {
            ob_start();
            $nonce = isset($_POST['_wpnonce']) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce($nonce, 'SNESTX51_resident_nonce') && ! wp_verify_nonce($nonce, 'SNESTX51_frontend_nonce') ) {
                ob_get_clean(); // Clean before error
                // Aggressive Clean
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                error_log("SNESTX51 Error: Nonce verification failed for edit_resident"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
                wp_send_json_error(['message' => 'Nonce verification failed'], 403);
                exit;
            }
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_resident_nonce' ) ) wp_die( 'Security check failed' );
        }
    
    $id = isset($_POST['resident_id']) ? sanitize_text_field( wp_unslash( $_POST['resident_id'] ) ) : '';
    $flat_no = isset($_POST['flat_no']) ? sanitize_text_field( wp_unslash( $_POST['flat_no'] ) ) : '';
    $name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

    // Track the WP User ID for the profile being edited (usually the current user)
    if ( ! isset( $_POST['wp_user_id'] ) ) {
        $_POST['wp_user_id'] = get_current_user_id();
    }

    // Handle Photo Upload (for both Admin and Resident requests)
    error_log("SNESTX51 Debug: handle_edit_resident called. _FILES: " . (isset($_FILES['profile_photo']) ? 'Found' : 'Missing')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
    
    if ( ! empty( $_FILES['profile_photo']['name'] ) ) {
        $photo_url = $this->handle_photo_upload($flat_no, $name);
        if ( is_wp_error( $photo_url ) ) {
            error_log("SNESTX51 Error: Photo upload failed: " . $photo_url->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
            if ( wp_doing_ajax() ) {
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                wp_send_json_error(['message' => 'Photo upload failed: ' . $photo_url->get_error_message()]);
                exit;
            }
        } else {
            $_POST['profile_photo'] = $photo_url;
            error_log("SNESTX51 Debug: Photo uploaded successfully to: " . $photo_url); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
        }
    }
    
    // IF ADMIN OR RESIDENT EDITING OWN BASIC PROFILE: Immediate
    $rbac = Society_NestX::get_instance()->rbac;
    $is_admin = $rbac->has_capability( get_current_user_id(), 'residents_manage' );
    
    // Check if it's a self-profile edit (basic details only)
    $is_self_edit = false;
    $current_wp_user_id = get_current_user_id();
    $target_resident = $this->db->get_resident_by_wp_id( $current_wp_user_id );
    
    if ( $target_resident && $target_resident['id'] === $id ) {
        $is_self_edit = true;
    }

    if ( $is_admin || $is_self_edit ) {
        // Security: If not admin, strip sensitive fields from the direct update
        if ( ! $is_admin ) {
            $sensitive_fields = array( 'flat_no', 'type', 'role', 'roles', 'status', 'maintenance_balance' );
            foreach ( $sensitive_fields as $field ) {
                unset( $_POST[$field] );
            }
        }

        // 1. Synchronize with Request Manager if a pending request exists
        require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SNESTX51_Request_Manager();
        $sync_res = $rm->approve_request( $id );
        
        if ( ! is_wp_error( $sync_res ) ) {
            // Request Manager handled the update via perform_edit_resident (in execute_request)
            if ( wp_doing_ajax() ) {
                ob_get_clean();
                // Aggressive Clean
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                wp_send_json_success(['message' => 'Profile updated and request synchronized']);
            } else {
                wp_safe_redirect( admin_url( 'admin.php?page=snestx-profile&status=updated' ) ); // Contextual redirect might be needed
            }
            exit;
        }

        $res = $this->perform_edit_resident( $_POST );

        if ( wp_doing_ajax() ) {
            // Aggressive Clean
            while ( ob_get_level() > 0 ) { ob_end_clean(); }

            if ( is_wp_error( $res ) ) {
                wp_send_json_error(['message' => $res->get_error_message()]);
            }
            wp_send_json_success(['message' => 'Profile updated successfully']);
            exit;
        }
    } else {
        require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SNESTX51_Request_Manager();
        $sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
        $res = $rm->create_request( 'residents', 'edit', $sanitized_post, $id, 'residents', $sanitized_post['flat_no'] ?? '' );

        if ( wp_doing_ajax() ) {
            $debug = ob_get_clean();
            if(!empty($debug)) error_log('SNESTX Resident Edit Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

            // Aggressive Clean
            while ( ob_get_level() > 0 ) { ob_end_clean(); }

            if ( is_wp_error( $res ) ) {
                wp_send_json_error(['message' => $res->get_error_message()]);
            } else {
                wp_send_json_success(['message' => 'Update request submitted for approval']);
            }
            exit;
        }
    }

	wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=updated' ) );
	exit;
}
    private function perform_edit_resident( $data ) {
		$original_flat_no = isset($data['original_flat_no']) ? sanitize_text_field( $data['original_flat_no'] ) : '';
		$flat_no = isset($data['flat_no']) ? sanitize_text_field( $data['flat_no'] ) : '';
        $original_name = isset( $data['original_name'] ) ? sanitize_text_field( $data['original_name'] ) : '';
        $resident_id = isset($data['resident_id']) ? sanitize_text_field($data['resident_id']) : (isset($data['id']) ? sanitize_text_field($data['id']) : '');

        // 0. Fetch Existing Data to prevent overwriting with empty values
        $existing_resident = [];
        $all_residents = $this->db->get( 'residents' );
        
        // Strategy 1: Find by ID
        if ( ! empty( $resident_id ) ) {
            foreach ( $all_residents as $r ) {
                if ( isset($r['id']) && $r['id'] == $resident_id ) {
                    $existing_resident = $r;
                    break;
                }
            }
        } 
        
        // Strategy 2: Find by Name/Flat (Legacy Fallback)
        if ( empty( $existing_resident ) && !empty($original_flat_no) && !empty($original_name) ) {
             foreach ( $all_residents as $r ) {
                if ( $r['flat_no'] === $original_flat_no && $r['name'] === $original_name ) {
                    $existing_resident = $r;
                    $resident_id = $r['id']; // Ensure we have ID for update
                    break;
                }
            }
        }

        // Strategy 3: Find by WP User ID (Fallback)
        $look_up_user_id = ! empty( $data['wp_user_id'] ) ? intval( $data['wp_user_id'] ) : ( is_user_logged_in() && ! current_user_can('manage_options') ? get_current_user_id() : 0 );
        
        if ( empty( $existing_resident ) && $look_up_user_id > 0 ) {
            foreach ( $all_residents as $r ) {
                if ( isset($r['wp_user_id']) && (int)$r['wp_user_id'] === $look_up_user_id ) {
                    $existing_resident = $r;
                    $resident_id = $r['id'] ?? $resident_id;
                    break;
                }
            }
        }

        if ( empty( $existing_resident ) ) {
            return new WP_Error( 'not_found', 'Resident not found for update.' );
        }

		$update_data = array(
			'flat_no'       => !empty($flat_no) ? $flat_no : ($existing_resident['flat_no'] ?? ''),
			'name'          => isset($data['name']) ? sanitize_text_field( $data['name'] ) : ($existing_resident['name'] ?? ''),
			'email'         => isset($data['email']) ? sanitize_email( $data['email'] ) : ($existing_resident['email'] ?? ''),
			'phone'         => isset($data['phone']) ? sanitize_text_field( $data['phone'] ) : ($existing_resident['phone'] ?? ''),
			'type'          => isset($data['type']) ? sanitize_text_field( $data['type'] ) : ($existing_resident['type'] ?? 'owner'), // Preserve Type
			'members_count' => isset($data['members_count']) ? intval( $data['members_count'] ) : ($existing_resident['members_count'] ?? 1),
			'blood_group'   => isset($data['blood_group']) ? sanitize_text_field( $data['blood_group'] ) : ($existing_resident['blood_group'] ?? ''),
			'relation'      => isset($data['relation']) ? sanitize_text_field( $data['relation'] ) : ($existing_resident['relation'] ?? ''),
			'dob'           => isset($data['dob']) ? sanitize_text_field( $data['dob'] ) : ($existing_resident['dob'] ?? ''),
            'status'        => 'approved', // Reset to approved upon edit approval or admin edit
		);

        // Relational Roles (Multi-Role Support)
        $roles = isset($data['role']) ? $data['role'] : (isset($data['roles']) ? $data['roles'] : array());
        if ( ! is_array( $roles ) ) {
            $roles = array_filter( explode( ',', (string)$roles ) );
        }
        
        $this->db->save_relations( 'resident_role_map', 'resident_id', $resident_id, 'role_id', $roles );

        // Update CSV column for legacy views
        $update_data['roles'] = implode( ',', $roles );

        // Handle Photo Upload
        // Priority 1: Already uploaded (e.g. from handle_edit_resident or approved request)
        if ( ! empty( $data['profile_photo'] ) && ! is_wp_error( $data['profile_photo'] ) ) {
            $update_data['profile_photo'] = sanitize_text_field( $data['profile_photo'] );
        } else {
            // Priority 2: Upload now (e.g. direct admin edit where handle_edit_resident didn't run or failed)
            // Only try if a file is actually present
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed in handle_edit_resident caller method.
            if ( ! empty( $_FILES['profile_photo']['name'] ) ) {
                $photo_url = $this->handle_photo_upload($update_data['flat_no'], $update_data['name']);
                if ( $photo_url && ! is_wp_error( $photo_url ) ) {
                    $update_data['profile_photo'] = $photo_url;
                }
            }
        }

		// 1. Maintain 1 owner/tenant per flat rule during edits
		if ( in_array( $update_data['type'], array( 'owner', 'tenant' ) ) ) {
			foreach ( $all_residents as $i => $r ) {
				$is_self = false;
				if ( isset($r['id']) && $r['id'] == $resident_id ) {
					$is_self = true;
				}

				if ( ! $is_self && 
					 $r['flat_no'] === $update_data['flat_no'] && 
					 strtolower($r['type']) === strtolower($update_data['type']) ) {
					
					$this->archive_to_history( $r );
					if ( isset($r['id']) ) {
						$this->db->delete('residents', ['id' => $r['id']]);
					}
				}
			}
		}

		// FIX: Ensure WP User Linkage
        // If email changed or link missing, re-link
        if ( !empty($update_data['email']) ) {
             $user = get_user_by( 'email', $update_data['email'] );
             if ( ! $user ) {
                 // Create User if not exists
                $password = wp_generate_password();
                $user_id = wp_create_user( $update_data['email'], $password, $update_data['email'] );
                if ( ! is_wp_error( $user_id ) ) {
                    $user = get_user_by( 'id', $user_id );
                    $user->set_role( 'subscriber' );
                }
             }
             
             if ( $user && ! is_wp_error( $user ) ) {
                 $update_data['wp_user_id'] = $user->ID;
                 update_user_meta( $user->ID, 'SNESTX51_flat_no', $update_data['flat_no'] );

                 // Sync Profile Changes to WP User
                 $wp_user_data = [
                     'ID'           => $user->ID,
                     'display_name' => $update_data['name'],
                     'user_email'   => $update_data['email'],
                     'first_name'   => $update_data['name'], // Simplified, or split by space
                 ];
                 wp_update_user($wp_user_data);

                 // Sync Society Roles to WP Roles
                 $this->sync_wp_user_roles( $user->ID, $resident_id );
             }
        }

		return $this->db->update('residents', $update_data, ['id' => $resident_id]);
    }

	/**
	 * Delete Resident & Archive to History.
	 */
	public function handle_delete_resident() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'SNESTX51_delete_resident_nonce' );
        } else {
		    if ( ! check_admin_referer( 'SNESTX51_delete_resident_nonce' ) ) wp_die( 'Security check failed' );
        }

		$resident_id = isset( $_POST['resident_id'] ) ? sanitize_text_field( wp_unslash( $_POST['resident_id'] ) ) : '';
        
        $rbac = Society_NestX::get_instance()->rbac;
        if ( $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) {
            $res = $this->perform_delete_resident(['resident_id' => $resident_id]);
        } else {
            require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SNESTX51_Request_Manager();
            $rm->create_request( 'residents', 'delete', ['resident_id' => $resident_id], $resident_id, 'residents' );
        }

        if ( wp_doing_ajax() ) {
            // Aggressive Clean
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            wp_send_json_success(['message' => 'Resident archived successfully']);
        }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=archived' ) );
		exit;
	}

    /**
     * Move from Residents to History (Permanent Delete from Residents)
     */
    public function handle_move_to_history() {
        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'SNESTX51_move_to_history_nonce' );
        } else {
            if ( ! check_admin_referer( 'SNESTX51_move_to_history_nonce' ) ) wp_die( 'Security check failed' );
        }

        $rbac = Society_NestX::get_instance()->rbac;
        if ( ! $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) wp_die('Unauthorized');

        $resident_id = isset( $_POST['resident_id'] ) ? sanitize_text_field( wp_unslash( $_POST['resident_id'] ) ) : '';
        $residents = $this->db->get( 'residents' );
        $to_archive = null;

        foreach ( $residents as $r ) {
            if ( $r['id'] === $resident_id ) {
                $to_archive = $r;
                break;
            }
        }

        if ( $to_archive ) {
            $this->archive_to_history( $to_archive );
            $this->db->delete('residents', ['id' => $resident_id]);

            if ( wp_doing_ajax() ) {
                while ( ob_get_level() > 0 ) { ob_end_clean(); }
                wp_send_json_success(['message' => 'Resident moved to history']);
            }
        }

        wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=permanently_deleted' ) );
        exit;
    }

	/**
	 * Restore Resident from Archive.
	 */
	public function handle_restore_resident() {
        $rbac = Society_NestX::get_instance()->rbac;
        if ( ! $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) wp_die( 'Unauthorized' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'SNESTX51_restore_resident_nonce' );
		} else {
			if ( ! check_admin_referer( 'SNESTX51_restore_resident_nonce' ) ) wp_die( 'Security check failed' );
		}

		$resident_id = isset( $_POST['resident_id'] ) ? sanitize_text_field( wp_unslash( $_POST['resident_id'] ) ) : '';
		$to_restore = null;
		$source = '';

		// Stage 1: Check if resident is in main table but archived (status flip logic)
		$residents = $this->db->get( 'residents' );
		foreach ( $residents as $r ) {
			if ( $r['id'] === $resident_id && $r['status'] === 'archived' ) {
				$to_restore = $r;
				$source = 'residents';
				break;
			}
		}

		// Stage 2: Fallback to history table (table move logic)
		if ( ! $to_restore ) {
			$history = $this->db->get( 'resident_history' );
			foreach ( $history as $h ) {
				if ( $h['id'] === $resident_id ) {
					$to_restore = $h;
					$source = 'history';
					break;
				}
			}
		}

		if ( $to_restore ) {
			if ( $source === 'residents' ) {
				// Simply flip status back to approved
				$this->db->update('residents', ['status' => 'approved'], ['id' => $resident_id]);
			} else {
				// Move back from history to residents
				unset($to_restore['vacated_at']); // Remove archive timestamp
				$this->db->insert('residents', $to_restore);
				$this->db->delete('resident_history', ['id' => $resident_id]);
			}

			// Log Audit
			$name = $to_restore['name'] ?? 'Unknown';
			require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
			$rm = new SNESTX51_Request_Manager();
			$rm->log_audit('resident_restored', 'residents', $resident_id, "Resident: $name (source: $source)");

			if ( wp_doing_ajax() ) {
				while ( ob_get_level() > 0 ) { ob_end_clean(); }
				wp_send_json_success(['message' => 'Resident restored successfully']);
			}
		} else {
			if ( wp_doing_ajax() ) {
				while ( ob_get_level() > 0 ) { ob_end_clean(); }
				wp_send_json_error(['message' => 'Resident record not found in Archived status or History log']);
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=restored' ) );
		exit;
	}

    private function perform_delete_resident( $data ) {
        $resident_id = isset($data['resident_id']) ? sanitize_text_field( $data['resident_id'] ) : (isset($data['id']) ? sanitize_text_field($data['id']) : '');
        if(!$resident_id) return new WP_Error('missing_id', 'ID Missing');

		$residents = $this->db->get( 'residents' );
		$to_archive = null;

		$clean_id = trim((string)$resident_id);
		foreach ( $residents as $i => $r ) {
			if ( trim((string)$r['id']) == $clean_id ) {
				$to_archive = $r;
				break; 
			}
		}

		if ( $to_archive ) {
			// Instead of deleting, we now update status to archived per user request
            // History move only happens on "Permanent Delete"
			return $this->db->update('residents', ['status' => 'archived'], ['id' => $to_archive['id']]);
		}
        return false;
    }

	private function archive_to_history( $resident ) {
		$resident['vacated_at'] = current_time( 'mysql' );
		// Ensure unique ID if coming from legacy
		if(!isset($resident['id']) || empty($resident['id'])) $resident['id'] = uniqid('hist_');
		return $this->db->insert('resident_history', $resident);
	}

	/**
	 * Permanently Delete from History Archive.
	 */
	public function handle_delete_history() {
    $rbac = Society_NestX::get_instance()->rbac;
    if ( ! $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) wp_die( 'Unauthorized' );

    if ( wp_doing_ajax() ) {
        check_ajax_referer( 'SNESTX51_delete_history_nonce' );
    } else {
	    if ( ! check_admin_referer( 'SNESTX51_delete_history_nonce' ) ) wp_die( 'Security check failed' );
    }

	$history_id = isset( $_POST['history_id'] ) ? sanitize_text_field( wp_unslash( $_POST['history_id'] ) ) : '';
		$history = $this->db->get( 'resident_history' );
		$updated = false;
		
		$res = $this->db->delete('resident_history', ['id' => $history_id]);

        if ( wp_doing_ajax() ) {
            // Aggressive Clean
            while ( ob_get_level() > 0 ) { ob_end_clean(); }
            if ($res) wp_send_json_success(['message' => 'Resident permanently deleted']);
            wp_send_json_error(['message' => 'Delete failed']);
        }

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=history_deleted' ) );
		exit;
	}

	/**
	 * Handle Bulk Import.
	 */
	public function handle_bulk_import() {
        $rbac = Society_NestX::get_instance()->rbac;
        if ( ! $rbac->has_capability( get_current_user_id(), 'residents_manage' ) ) wp_die( 'Unauthorized' );

		if ( ! check_admin_referer( 'SNESTX51_bulk_import_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSV text data parsed and columns sanitized individually.
		$csv_data = isset( $_POST['csv_data'] ) ? trim( wp_unslash( $_POST['csv_data'] ) ) : '';
		$rows = explode( "\n", $csv_data );
		$count = 0;
		$errors = 0;

		foreach ( $rows as $row ) {
			// Format: FlatNo, Name, Email, Phone, Type, Members
			$cols = str_getcsv( trim( $row ) );
			if ( count( $cols ) < 2 ) continue; // Skip empty/invalid

			$p = array(
				'flat_no'       => sanitize_text_field( $cols[0] ),
				'name'          => sanitize_text_field( $cols[1] ),
				'email'         => isset( $cols[2] ) ? sanitize_email( $cols[2] ) : '',
				'phone'         => isset( $cols[3] ) ? sanitize_text_field( $cols[3] ) : '',
				'type'          => isset( $cols[4] ) ? sanitize_text_field( $cols[4] ) : 'owner',
				'members_count' => isset( $cols[5] ) ? intval( $cols[5] ) : 1,
			);

			$res = $this->process_add_resident( $p );
			if ( is_wp_error( $res ) ) {
				$errors++;
			} else {
				$count++;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-residents&status=imported&count=' . $count . '&errors=' . $errors ) );
		exit;
	}

	/**
	 * Core Logic to Add Resident.
	 */
	private function process_add_resident( $post_data ) {
		$data = array(
			'flat_no'       => $post_data['flat_no'],
			'name'          => $post_data['name'],
		);

		// Handle Photo Upload
        if ( ! empty( $post_data['profile_photo'] ) ) {
            $data['profile_photo'] = sanitize_text_field( $post_data['profile_photo'] );
        } else {
            $photo_url = $this->handle_photo_upload($data['flat_no'], $data['name']);
            if ( $photo_url && ! is_wp_error( $photo_url ) ) {
                $data['profile_photo'] = $photo_url;
            }
        }

		// 0. Server-Side Validation: Flat Existence
		$flats = $this->db->get( 'flats' );
		$valid_flat = false;
		foreach ( $flats as $f ) {
			if ( $f['id'] === $data['flat_no'] ) {
				$valid_flat = true;
				break;
			}
		}

		if ( ! $valid_flat && ! empty( $data['flat_no'] ) ) {
			return new WP_Error( 'invalid_flat', 'Flat ' . $data['flat_no'] . ' does not exist.' );
		}

		$data = array_merge( $data, array(
			'email'         => isset($post_data['email']) ? $post_data['email'] : '',
			'phone'         => isset($post_data['phone']) ? $post_data['phone'] : '',
			'type'          => isset($post_data['type']) ? $post_data['type'] : 'owner',
			'members_count' => isset($post_data['members_count']) ? intval( $post_data['members_count'] ) : 1,
			'blood_group'   => isset($post_data['blood_group']) ? sanitize_text_field( $post_data['blood_group'] ) : '',
			'relation'      => isset($post_data['relation']) ? sanitize_text_field( $post_data['relation'] ) : '',
			'dob'           => isset($post_data['dob']) ? sanitize_text_field( $post_data['dob'] ) : '',
		'status'        => isset($post_data['status']) ? $post_data['status'] : 'approved',
		'wp_user_id'    => '', 
		'id'            => isset($post_data['id']) ? $post_data['id'] : uniqid('res_'), 
		'created_at'    => current_time( 'mysql' ),
	) );

        // Add Relational Roles (Multi-Role Support)
        $roles = isset($post_data['role']) ? $post_data['role'] : (isset($post_data['roles']) ? $post_data['roles'] : array());
        if ( ! is_array( $roles ) ) {
            $roles = array_filter( explode( ',', (string)$roles ) );
        }
        
        $this->db->save_relations( 'resident_role_map', 'resident_id', $data['id'], 'role_id', $roles );
        $data['roles'] = implode( ',', $roles );

		// 0.1 Auto-Archive existing occupant (Owner/Tenant) if new one added for same flat.
		if ( in_array( $data['type'], array( 'owner', 'tenant' ) ) ) {
			$residents = $this->db->get( 'residents' );
			foreach ( $residents as $i => $r ) {
				if ( $r['flat_no'] === $data['flat_no'] && strtolower($r['type']) === strtolower($data['type']) ) {
					// Found existing owner/tenant. Archive them first.
					$this->archive_to_history( $r );
					if(isset($r['id'])) {
						$this->db->delete('residents', ['id' => $r['id']]);
					}
					break;
				}
			}
		}

		// 1. Create WP User (if email provided).
		if ( $data['email'] ) {
			$user = get_user_by( 'email', $data['email'] );
			if ( ! $user ) {
				$password = wp_generate_password();
				$user_id = wp_create_user( $data['email'], $password, $data['email'] ); 
				if ( ! is_wp_error( $user_id ) ) {
					$user = get_user_by( 'id', $user_id );
					$user->set_role( 'subscriber' ); 
				}
			}
			
			if ( $user && ! is_wp_error( $user ) ) {
				$data['wp_user_id'] = $user->ID;
				update_user_meta( $user->ID, 'SNESTX51_flat_no', $data['flat_no'] );

                // Sync Roles immediately
                $this->sync_wp_user_roles( $user->ID, $data['id'] );
			}
		}

		// 2. Insert into DB (Local/Sheet).
		return $this->db->insert( 'residents', $data );
	}

	public function render_page() {
        $rbac = Society_NestX::get_instance()->rbac;
        if ( ! $rbac->has_capability( get_current_user_id(), 'residents_view' ) ) {
            wp_die( 'You do not have permission to view the Residents Directory.' );
        }
		// Pass Residents and Flats for the form dropdown
        require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SNESTX51_Request_Manager();
        $unified = $rm->get_unified_data( 'residents', 'residents', 'resident_history', true ); // Added load_relations
        $flats = $this->db->get('flats');
		
		SNESTX51_Admin_App::render_view('residents', [
			'residents' => $unified['active'], 
            'pending'   => $unified['pending'],
            'history'   => $unified['archived'],
			'flats'     => $flats
		]);
	}

	/**
	 * Sync Society Roles to WordPress User Roles.
	 */
	public function sync_wp_user_roles( $user_id, $resident_id ) {
		$rbac = Society_NestX::get_instance()->rbac;
		$society_roles = $this->db->get_mysql( 'resident_role_map', array( 'where' => array( 'resident_id' => $resident_id ) ) );
		
		$wp_user = new WP_User( $user_id );
		if ( ! $wp_user->exists() ) return;

		// 1. Remove all existing SNESTX roles from user
		$current_wp_roles = $wp_user->roles;
		foreach ( $current_wp_roles as $role_slug ) {
			if ( strpos( $role_slug, 'SNESTX_' ) === 0 ) {
				$wp_user->remove_role( $role_slug );
			}
		}

		// 2. Add new roles based on society mapping
		foreach ( $society_roles as $map ) {
			$role_id = $map['role_id'];
			$wp_role_id = 'SNESTX_' . sanitize_title( $role_id );
            
            // Ensure role exists in WP (double check)
            if ( ! get_role( $wp_role_id ) ) {
                $role_def = $rbac->get_role( $role_id );
                $role_name = $role_def ? $role_def['name'] : ucfirst( str_replace( '_', ' ', $role_id ) );
                add_role( $wp_role_id, 'SNESTX: ' . $role_name, array( 'read' => true ) );
            }

			$wp_user->add_role( $wp_role_id );
		}
        
        // Ensure 'subscriber' is present if no other roles (for frontend access)
        if ( empty( $wp_user->roles ) ) {
            $wp_user->add_role( 'subscriber' );
        }
	}

	/**
	 * Helper to handle photo upload.
	 */
	private function handle_photo_upload( $flat_no = '', $name = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in caller handlers.
		if ( isset( $_FILES['profile_photo']['size'] ) && $_FILES['profile_photo']['size'] > 0 ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- $_FILES is validated inside upload_profile_photo.
			return $this->media->upload_profile_photo( $_FILES['profile_photo'], $flat_no, $name, 'residents' );
		}
		return null;
	}
}
