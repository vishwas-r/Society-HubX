<?php
/**
 * Module: Vehicle Registry
 * Handles Society Vehicles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Vehicle_Manager implements SHUBX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SHUBX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_shubx51_add_vehicle', array( $this, 'handle_add_vehicle' ) );
		add_action( 'wp_ajax_shubx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ) );
		add_action( 'wp_ajax_shubx51_delete_vehicle', array( $this, 'handle_delete_vehicle' ) );
		add_action( 'wp_ajax_shubx51_restore_vehicle', array( $this, 'handle_restore_vehicle' ) );

		add_action( 'admin_post_shubx51_add_vehicle', array( $this, 'handle_add_vehicle' ) );
		add_action( 'admin_post_shubx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ) );
		add_action( 'admin_post_shubx51_delete_vehicle', array( $this, 'handle_delete_vehicle' ) );
		add_action( 'admin_post_shubx51_restore_vehicle', array( $this, 'handle_restore_vehicle' ) );
		add_action( 'admin_post_shubx51_approve_vehicle', array( $this, 'handle_approve_vehicle' ) );
        
        // Register Module
        add_filter( 'shubx51_get_module_vehicles', array( $this, 'get_instance' ) );
        add_filter( 'shubx51_get_module_vehicle', array( $this, 'get_instance' ) ); // Singular alias
	}

    /**
     * Singleton accessor for the filter to return this instance.
     * Note: Since the constructor is called in main plugin, $this is already instantiated.
     * But the filter needs to return THIS object.
     * Since we don't have a static instance in this class pattern (it was 'new SHUBX51_Vehicle_Manager' in main),
     * we need to capture it.
     * 
     * Actually, the main plugin file treats these as 'new Class()'.
     * To make the filter work, we need to add the filter inside the constructor using $this.
     */
    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'vehicles';
    }

    /**
     * Execute Request (The actual DB Logic)
     */
    public function execute_request( $action, $payload ) {
        // Ensure standard fields
        $payload = (array) $payload; // ensure array
        
        // Route by Action
        if ( $action === 'add' ) {
            $id = $payload['id'] ?? '';
            $all = $this->db->get('vehicles');
            $exists = false;
            foreach($all as $v) { if(($v['id']??'') === $id) { $exists = true; break; } }
            
            if($exists) {
                return $this->db->update('vehicles', ['status' => 'approved'], ['id' => $id]);
            } else {
                $payload['status'] = 'approved';
                return $this->perform_save_vehicle( $payload, false );
            }
        } elseif ( $action === 'edit' ) {
             return $this->perform_save_vehicle( $payload, true );
        } elseif ( $action === 'delete' ) {
             return $this->perform_delete_vehicle( $payload );
        }
        
        return new WP_Error( 'invalid_action', 'Unknown action: ' . $action );
    }

    private function perform_delete_vehicle( $payload ) {
        if ( ! isset( $payload['id'] ) ) return new WP_Error( 'missing_id', 'Vehicle ID missing' );
        // Soft Delete (Archive)
        return $this->db->update( 'vehicles', array( 'status' => 'archived' ), array( 'id' => $payload['id'] ) );
    }

	private function perform_save_vehicle( $data, $is_update ) {
		// Ensure DB Schema is up to date
		SHUBX51_DB_Schema::create_tables();

        // Extract Data safely
		$number  = isset($data['number']) ? sanitize_text_field( $data['number'] ) : '';
		$type    = isset($data['type']) ? sanitize_text_field( $data['type'] ) : '';
		$sticker = isset($data['sticker']) ? sanitize_text_field( $data['sticker'] ) : '';
		$status  = isset($data['status'] ) ? sanitize_text_field( $data['status'] ) : 'approved'; 
		$flat_no = isset($data['flat_no']) ? sanitize_text_field( $data['flat_no']) : '';
        $owner_name = isset($data['owner_name']) ? sanitize_text_field( $data['owner_name'] ) : '';
        $brand   = isset($data['brand']) ? sanitize_text_field( $data['brand'] ) : '';
        $model   = isset($data['model']) ? sanitize_text_field( $data['model'] ) : '';

		// Auto-fetch Owner Name if missing
		if ( empty( $owner_name ) && !empty( $flat_no ) ) {
			$residents = $this->db->get('residents');
			foreach ( $residents as $r ) {
				if ( $r['flat_no'] === $flat_no && strtolower($r['type']) === 'owner' ) {
					$owner_name = $r['name'];
					break;
				}
			}
		}

		$db_data = array(
			'number'  => $number,
			'plate_no'=> $number, 
			'type'    => $type,
			'brand'   => $brand,
			'model'   => $model,
			'sticker' => $sticker,
			'status'  => $status,
			'owner_name' => $owner_name
		);
		
		if($flat_no) $db_data['flat_no'] = $flat_no;

        $id = isset($data['id']) ? $data['id'] : (isset($data['vehicle_id']) ? $data['vehicle_id'] : '');

		if ( $is_update && $id ) {
            // Fetch Existing by ID to verify it exists
            $existing = [];
            $all = $this->db->get('vehicles');
            foreach($all as $v) { if(isset($v['id']) && $v['id'] === $id) { $existing = $v; break; } }

            // If found, merge and update
            if ( ! empty( $existing ) ) {
                $db_data = array(
                    'number'  => $number ?: ($existing['number'] ?? ''),
                    'plate_no'=> $number ?: ($existing['number'] ?? ''),
                    'type'    => $type ?: ($existing['type'] ?? ''),
                    'brand'   => $brand ?: ($existing['brand'] ?? ''),
                    'model'   => $model ?: ($existing['model'] ?? ''),
                    'sticker' => $sticker ?: ($existing['sticker'] ?? ''),
                    'status'  => 'approved', // Reset to approved upon edit approval or admin edit
                    'owner_name' => $owner_name ?: ($existing['owner_name'] ?? ''),
                    'flat_no'    => $flat_no ?: ($existing['flat_no'] ?? '')
                );
                $db_data['id'] = $id;

                // Update by ID
                $this->db->update( 'vehicles', $db_data, array( 'id' => $id ) );
                return true;
            } else {
                // ID not found: this is actually a new vehicle, insert it
                $db_data['id'] = $id;
                if(!isset($db_data['flat_no'])) $db_data['flat_no'] = ''; 
                return $this->db->insert( 'vehicles', $db_data );
            }
		} else {
			// Insert new
			if(!$id) $id = uniqid('veh_');
			$db_data['id'] = $id;
			if(!isset($db_data['flat_no'])) $db_data['flat_no'] = ''; 
			return $this->db->insert( 'vehicles', $db_data );
		}
    }

	public function register_menu() {
		add_submenu_page(
			'shubx51-settings',
			'Vehicle Registry',
			'Vehicles',
			'read', // Granular check in render_page
			'shubx51-vehicles',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_vehicle() {
		if ( wp_doing_ajax() ) {
            // Start buffering to catch any PHP warnings/notices
            ob_start();
            check_ajax_referer( 'shubx51_add_vehicle_nonce' );
        } else {
		    if ( ! check_admin_referer( 'shubx51_add_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }

        $payload = $_POST;
        if ( !isset($payload['id']) || empty($payload['id']) ) {
            $payload['id'] = uniqid('veh_');
        }
        
        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'vehicles_manage' );

        // IF ADMIN or has vehicles_manage: Immediate Add
        if ( $has_manage ) {
            $res = $this->perform_save_vehicle( $payload, false );
            if ( wp_doing_ajax() ) {
                $debug_output = ob_get_clean(); // Capture any premature output
                if ( ! empty( $debug_output ) ) error_log( 'SHUBX Vehicle Add Output: ' . $debug_output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
                
                // Aggressive Clean: Wipe all buffers
                while ( ob_get_level() > 0 ) { ob_end_clean(); }

                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Vehicle added successfully']);
                exit;
            }
        } else {
           $payload['status'] = 'pending';
           $this->perform_save_vehicle( $payload, false );

            $rm = new SHUBX51_Request_Manager();
           $res = $rm->create_request( 'vehicles', 'add', $payload, $payload['id'] );
           if ( wp_doing_ajax() ) {
               $debug_output = ob_get_clean();
               if ( ! empty( $debug_output ) ) error_log( 'SHUBX Vehicle Add Request Output: ' . $debug_output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

               // Aggressive Clean
               while ( ob_get_level() > 0 ) { ob_end_clean(); }

               if ( is_wp_error( $res ) ) {
                   wp_send_json_error(['message' => $res->get_error_message()]);
               }
               wp_send_json_success(['message' => 'Vehicle added and submitted for approval']);
               exit;
           }
       }
		
		wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&success=Added' ) );
		exit;
	}

	public function handle_edit_vehicle() {
		if ( wp_doing_ajax() ) {
            ob_start(); // Start buffering
            check_ajax_referer( 'shubx51_add_vehicle_nonce' );
        } else {
            // Accept either the admin/add nonce or the frontend edit token
            $nonce_ok = false;
            if ( ! empty( $_POST['_wpnonce'] ) ) {
                if ( wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_add_vehicle_nonce' ) || wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_edit_vehicle_action' ) ) {
                    $nonce_ok = true;
                }
            }
            if ( ! $nonce_ok && ! empty( $_POST['shubx51_edit_vehicle_token'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['shubx51_edit_vehicle_token'] ) ), 'shubx51_edit_vehicle_action' ) ) {
                $nonce_ok = true;
            }
            if ( ! $nonce_ok ) wp_die( 'Security check failed' );
        }

		$id = isset($_POST['vehicle_id']) ? sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : '';
        
        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'vehicles_manage' );

        // IF ADMIN or has vehicles_manage: Immediate
        if ( $has_manage ) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $sync_res = $rm->approve_request( $id );
            
            if ( ! is_wp_error( $sync_res ) ) {
                if ( wp_doing_ajax() ) {
                    ob_get_clean(); // Discard normal buffer
                    // Aggressive Clean
                    while ( ob_get_level() > 0 ) { ob_end_clean(); }
                    wp_send_json_success(['message' => 'Vehicle updated and request synchronized']);
                } else {
                    wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&success=Updated' ) );
                }
                exit;
            }

            $res = $this->perform_save_vehicle( $_POST, true );

            if ( wp_doing_ajax() ) {
                // Aggressive Clean
                while ( ob_get_level() > 0 ) { ob_end_clean(); }

                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Vehicle updated successfully']);
                exit;
            }
        } else {
            $rm = new SHUBX51_Request_Manager();
            $res = $rm->create_request( 'vehicles', 'edit', $_POST, $id );
            if ( wp_doing_ajax() ) {
                $debug_output = ob_get_clean();
                if ( ! empty( $debug_output ) ) error_log( 'SHUBX Vehicle Edit Request Output: ' . $debug_output ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

                // Aggressive Clean
                while ( ob_get_level() > 0 ) { ob_end_clean(); }

                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Update request submitted for approval']);
                exit;
            }
        }

		wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&success=Updated' ) );
		exit;
	}



	public function handle_delete_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_delete_vehicle_nonce' );
        } else {
		    if ( ! check_admin_referer( 'shubx51_delete_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }
		
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : (isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '');
        
        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'vehicles_manage' );

        // IF ADMIN or has vehicles_manage: Immediate
        if ( $has_manage ) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $sync_res = $rm->approve_request( $id );
            
            if ( ! is_wp_error( $sync_res ) ) {
                if ( wp_doing_ajax() ) {
                    wp_send_json_success(['message' => 'Vehicle archived and request synchronized']);
                } else {
                    wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&deleted=1' ) );
                }
                exit;
            }

             $res = $this->perform_delete_vehicle( ['id' => $id] );
        } else {
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $res = $rm->create_request( 'vehicles', 'delete', ['id' => $id], $id );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Delete request submitted for approval']);
                exit;
            }
        }

		wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&deleted=1' ) );
		exit;
	}

	public function handle_restore_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_add_vehicle_nonce' ); // Reusing add nonce
        } else {
		    if ( ! check_admin_referer( 'shubx51_add_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }
		
		$id = isset($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : (isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '');
        
        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'vehicles_manage' );

        if ( $has_manage ) {
            $this->db->update( 'vehicles', array( 'status' => 'approved' ), array( 'id' => $id ) );

            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $rm->log_audit('vehicle_restored', 'vehicles', $id, "Vehicle ID: $id");

            if ( wp_doing_ajax() ) {
                 wp_send_json_success(['message' => 'Vehicle restored successfully']);
                 exit;
             }
        }

		wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&restored=1' ) );
		exit;
	}

	public function handle_approve_vehicle() {
		// Use the same nonce as add/edit for now or generic custom one? 
		// Simpler to rely on generic admin nonce for actions if not form.
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'vehicles_manage' ) ) wp_die('Unauthorized');
		
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin action link redirect verification.
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$this->db->update( 'vehicles', array( 'status' => 'approved' ), array( 'id' => $id ) );
		wp_safe_redirect( admin_url( 'admin.php?page=shubx51-vehicles&approved=1' ) );
		exit;
	}

	public function render_page() {
        $rbac = new SHUBX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'vehicles_view' ) ) {
            wp_die( 'You do not have permission to view the Vehicle Registry.' );
        }

        $rm = new SHUBX51_Request_Manager();
        $unified = $rm->get_unified_data( 'vehicles', 'vehicles' );
        
        $flats = $this->db->get('flats');
        $residents = $this->db->get('residents');

        // Sort flats naturally
        usort($flats, function($a, $b) {
            return strnatcmp($a['id'] ?? '', $b['id'] ?? '');
        });

		SHUBX51_Admin_App::render_view('vehicles', [
            'vehicles' => $unified['active'],
            'pending'  => $unified['pending'],
            'history'  => array_filter($unified['active'], function($v){ return ($v['status'] ?? '') === 'archived'; }),
            'flats'    => $flats,
            'residents'=> $residents
        ]);
	}
}
