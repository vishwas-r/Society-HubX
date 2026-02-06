<?php
/**
 * Module: Resident Manager
 * Handles the "Residents" table and WP User Sync.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Resident_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		// AJAX Actions
		add_action( 'wp_ajax_sgvx51_add_resident', array( $this, 'handle_add_resident' ) );
		add_action( 'wp_ajax_sgvx51_edit_resident', array( $this, 'handle_edit_resident' ) );
		add_action( 'wp_ajax_sgvx51_delete_resident', array( $this, 'handle_delete_resident' ) );
		add_action( 'wp_ajax_sgvx51_restore_resident', array( $this, 'handle_restore_resident' ) );
		add_action( 'wp_ajax_sgvx51_delete_history', array( $this, 'handle_delete_history' ) );

		// Legacy Admin Post Actions (optional cleanup if no longer used)
		add_action( 'admin_post_sgvx51_add_resident', array( $this, 'handle_add_resident' ) );
		add_action( 'admin_post_sgvx51_edit_resident', array( $this, 'handle_edit_resident' ) );
		add_action( 'admin_post_sgvx51_delete_resident', array( $this, 'handle_delete_resident' ) );
		add_action( 'admin_post_sgvx51_restore_resident', array( $this, 'handle_restore_resident' ) );
		add_action( 'admin_post_sgvx51_delete_history', array( $this, 'handle_delete_history' ) );
		add_action( 'admin_post_sgvx51_bulk_import_residents', array( $this, 'handle_bulk_import' ) );


		// Self-Heal Schema (Ensure columns exist)
		if ( is_admin() ) {
			$this->db->verify_column( 'residents', 'roles', 'TEXT NOT NULL' );
			$this->db->verify_column( 'residents', 'wp_user_id', 'BIGINT(20) DEFAULT 0 NOT NULL' );
            $this->db->verify_column( 'residents', 'relation', 'VARCHAR(50) DEFAULT "" NOT NULL' );
            $this->db->verify_column( 'residents', 'age', 'VARCHAR(10) DEFAULT "" NOT NULL' );
		}
        
        // Register Module
        add_filter( 'sgvx51_get_module_residents', array( $this, 'get_instance' ) );
        add_filter( 'sgvx51_get_module_family', array( $this, 'get_instance' ) ); // Handle Family Requests
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
        $id = $payload['id'] ?? '';
        $all = $this->db->get('residents');
        $exists = false;
        foreach($all as $r) { if(($r['id']??'') === $id) { $exists = true; break; } }

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
			'sgvx51-settings',
			'Residents Directory',
			'Residents',
			'manage_options',
			'sgvx51-residents',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle Single Add.
	 */
	public function handle_add_resident() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_resident_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_resident_nonce' ) ) wp_die( 'Security check failed' );
        }
	
    // IF ADMIN: Immediate
   if ( current_user_can( 'manage_options' ) ) {
       $_POST['status'] = 'approved';
       $res = $this->process_add_resident( $_POST );
       
       if ( wp_doing_ajax() ) {
           if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
           wp_send_json_success(['message' => 'Resident added successfully']);
       }
   } else {
       $_POST['status'] = 'pending';
       $_POST['id'] = uniqid('res_');
       $this->process_add_resident( $_POST );

       require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
       $rm = new SGVX51_Request_Manager();
       $res = $rm->create_request( 'residents', 'add', $_POST, $_POST['id'] );
       
       if ( wp_doing_ajax() ) {
           if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
           wp_send_json_success(['message' => 'Resident added and submitted for approval']);
       }
   }

	wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=added' ) );
	exit;
}

	public function handle_edit_resident() {
		if ( wp_doing_ajax() ) {
            if ( !isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sgvx51_resident_nonce') ) {
                wp_send_json_error(['message' => 'Nonce verification failed'], 403);
                exit;
            }
        } else {
		    if ( ! check_admin_referer( 'sgvx51_resident_nonce' ) ) wp_die( 'Security check failed' );
        }

    $id = isset($_POST['resident_id']) ? sanitize_text_field($_POST['resident_id']) : '';
    
    // IF ADMIN: Immediate
    if ( current_user_can( 'manage_options' ) ) {
        $res = $this->perform_edit_resident( $_POST );
        
        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) {
                wp_send_json_error(['message' => $res->get_error_message()]);
            } else {
                wp_send_json_success(['message' => 'Resident updated successfully']);
            }
            exit;
        }
    } else {
        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $res = $rm->create_request( 'residents', 'edit', $_POST, $id );

        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $res ) ) {
                wp_send_json_error(['message' => $res->get_error_message()]);
            } else {
                wp_send_json_success(['message' => 'Update request submitted for approval']);
            }
            exit;
        }
    }

	wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=updated' ) );
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
                if ( isset($r['id']) && $r['id'] === $resident_id ) {
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
            'age'           => isset($data['age']) ? sanitize_text_field( $data['age'] ) : ($existing_resident['age'] ?? ''),
			'roles'         => (isset($data['roles']) && is_array($data['roles'])) ? json_encode(array_map('sanitize_text_field', $data['roles'])) : ($existing_resident['roles'] ?? ''),
		);

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
                 update_user_meta( $user->ID, 'sgvx51_flat_no', $update_data['flat_no'] );
             }
        }

		return $this->db->update('residents', $update_data, ['id' => $resident_id]);
        return false;
    }

	/**
	 * Delete Resident & Archive to History.
	 */
	public function handle_delete_resident() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_delete_resident_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_delete_resident_nonce' ) ) wp_die( 'Security check failed' );
        }

		$resident_id = sanitize_text_field( $_POST['resident_id'] );
        
        // IF ADMIN: Action is immediate.
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_delete_resident(['resident_id' => $resident_id]);
            
            if ( wp_doing_ajax() ) {
                if ( is_wp_error($res) ) wp_send_json_error(['message' => $res->get_error_message()]);
                if ( $res === false ) wp_send_json_error(['message' => 'Resident not found']);
                wp_send_json_success(['message' => 'Resident archived successfully']);
            }

            $status = !is_wp_error($res) && $res ? 'deleted' : 'error';
            wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=' . $status ) );
            exit;
        }

        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $rm->create_request( 'residents', 'delete', ['resident_id' => $resident_id], $resident_id );

        if ( wp_doing_ajax() ) {
            wp_send_json_success(['message' => 'Request archived successfully']);
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=deleted' ) );
		exit;
	}

	/**
	 * Restore Resident from Archive.
	 */
	public function handle_restore_resident() {
    if ( wp_doing_ajax() ) {
        check_ajax_referer( 'sgvx51_restore_resident_nonce' );
    } else {
	    if ( ! check_admin_referer( 'sgvx51_restore_resident_nonce' ) ) wp_die( 'Security check failed' );
    }

	$resident_id = sanitize_text_field( $_POST['resident_id'] );
		$history = $this->db->get( 'resident_history' );
		$to_restore = null;

		foreach ( $history as $h ) {
			if ( $h['id'] === $resident_id ) {
				$to_restore = $h;
				break;
			}
		}

		if ( $to_restore ) {
			unset($to_restore['vacated_at']); // Remove archive timestamp
			$this->db->insert('residents', $to_restore);
			$this->db->delete('resident_history', ['id' => $resident_id]);

        // Log Audit
        $rm = new SGVX51_Request_Manager();
        $rm->log_audit('resident_restored', 'residents', $resident_id, "Resident: " . ($to_restore['name'] ?? 'Unknown'));

            if ( wp_doing_ajax() ) {
                wp_send_json_success(['message' => 'Resident restored successfully']);
            }
		} else {
            if ( wp_doing_ajax() ) {
                wp_send_json_error(['message' => 'History record not found']);
            }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=restored' ) );
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
			$archive_res = $this->archive_to_history( $to_archive );
            if ( is_wp_error($archive_res) ) return $archive_res;
            
			return $this->db->delete('residents', ['id' => $to_archive['id']]);
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
    if ( wp_doing_ajax() ) {
        check_ajax_referer( 'sgvx51_delete_history_nonce' );
    } else {
	    if ( ! check_admin_referer( 'sgvx51_delete_history_nonce' ) ) wp_die( 'Security check failed' );
    }

	$history_id = sanitize_text_field( $_POST['history_id'] );
		$history = $this->db->get( 'resident_history' );
		$updated = false;
		
		$res = $this->db->delete('resident_history', ['id' => $history_id]);

        if ( wp_doing_ajax() ) {
            if ($res) wp_send_json_success(['message' => 'Resident permanently deleted']);
            wp_send_json_error(['message' => 'Delete failed']);
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=history_deleted' ) );
		exit;
	}

	/**
	 * Handle Bulk Import.
	 */
	public function handle_bulk_import() {
		if ( ! check_admin_referer( 'sgvx51_bulk_import_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$csv_data = trim( $_POST['csv_data'] );
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

		wp_redirect( admin_url( 'admin.php?page=sgvx51-residents&status=imported&count=' . $count . '&errors=' . $errors ) );
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
            'age'           => isset($post_data['age']) ? sanitize_text_field( $post_data['age'] ) : '',
			'roles'         => (isset($post_data['roles']) && is_array($post_data['roles'])) ? json_encode(array_map('sanitize_text_field', $post_data['roles'])) : '',
		'status'        => isset($post_data['status']) ? $post_data['status'] : 'approved',
		'wp_user_id'    => '', 
		'id'            => isset($post_data['id']) ? $post_data['id'] : uniqid('res_'), 
		'created_at'    => current_time( 'mysql' ),
	) );

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
				update_user_meta( $user->ID, 'sgvx51_flat_no', $data['flat_no'] );
			}
		}

		// 2. Insert into DB (Local/Sheet).
		return $this->db->insert( 'residents', $data );
	}

	public function render_page() {
		// Pass Residents and Flats for the form dropdown
        $rm = new SGVX51_Request_Manager();
        $unified = $rm->get_unified_data( 'residents', 'residents', 'resident_history' );
        $flats = $this->db->get('flats');
		
		SGVX51_Admin_App::render_view('residents', [
			'residents' => $unified['active'], 
            'pending'   => $unified['pending'],
            'history'   => $unified['archived'],
			'flats'     => $flats
		]);
	}

	/**
	 * Move resident to history log.
	 */
}
