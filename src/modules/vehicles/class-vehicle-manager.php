<?php
/**
 * Module: Vehicle Registry
 * Handles Society Vehicles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Vehicle_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_sgvx51_add_vehicle', array( $this, 'handle_add_vehicle' ) );
		add_action( 'wp_ajax_sgvx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ) );
		add_action( 'wp_ajax_sgvx51_delete_vehicle', array( $this, 'handle_delete_vehicle' ) );
		add_action( 'wp_ajax_sgvx51_restore_vehicle', array( $this, 'handle_restore_vehicle' ) );

		add_action( 'admin_post_sgvx51_add_vehicle', array( $this, 'handle_add_vehicle' ) );
		add_action( 'admin_post_sgvx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ) );
		add_action( 'admin_post_sgvx51_delete_vehicle', array( $this, 'handle_delete_vehicle' ) );
		add_action( 'admin_post_sgvx51_restore_vehicle', array( $this, 'handle_restore_vehicle' ) );
		add_action( 'admin_post_sgvx51_approve_vehicle', array( $this, 'handle_approve_vehicle' ) );
        
        // Register Module
        add_filter( 'sgvx51_get_module_vehicles', array( $this, 'get_instance' ) );
        add_filter( 'sgvx51_get_module_vehicle', array( $this, 'get_instance' ) ); // Singular alias
	}

    /**
     * Singleton accessor for the filter to return this instance.
     * Note: Since the constructor is called in main plugin, $this is already instantiated.
     * But the filter needs to return THIS object.
     * Since we don't have a static instance in this class pattern (it was 'new SGVX51_Vehicle_Manager' in main),
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
		SGVX51_DB_Schema::create_tables();

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
			'sgvx51-settings',
			'Vehicle Registry',
			'Vehicles',
			'manage_options',
			'sgvx51-vehicles',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_add_vehicle_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_add_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }

        $payload = array_merge($_POST, ['id' => uniqid('veh_')]);
        
        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_save_vehicle( $payload, false );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Vehicle added successfully']);
                exit;
            }
        } else {
           $payload['status'] = 'pending';
           $this->perform_save_vehicle( $payload, false );

           require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
           $rm = new SGVX51_Request_Manager();
           $res = $rm->create_request( 'vehicles', 'add', $payload, $payload['id'] );
           if ( wp_doing_ajax() ) {
               if ( is_wp_error( $res ) ) {
                   wp_send_json_error(['message' => $res->get_error_message()]);
               }
               wp_send_json_success(['message' => 'Vehicle added and submitted for approval']);
               exit;
           }
       }
		
		wp_redirect( admin_url( 'admin.php?page=sgvx51-vehicles&success=Added' ) );
		exit;
	}

	public function handle_edit_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_add_vehicle_nonce' );
        } else {
            // Accept either the admin/add nonce or the frontend edit token
            $nonce_ok = false;
            if ( ! empty( $_POST['_wpnonce'] ) ) {
                if ( wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_add_vehicle_nonce' ) || wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_edit_vehicle_action' ) ) {
                    $nonce_ok = true;
                }
            }
            if ( ! $nonce_ok && ! empty( $_POST['sgvx51_edit_vehicle_token'] ) && wp_verify_nonce( $_POST['sgvx51_edit_vehicle_token'], 'sgvx51_edit_vehicle_action' ) ) {
                $nonce_ok = true;
            }
            if ( ! $nonce_ok ) wp_die( 'Security check failed' );
        }

		$id = isset($_POST['vehicle_id']) ? sanitize_text_field( $_POST['vehicle_id'] ) : '';
        
        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_save_vehicle( $_POST, true );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Vehicle updated successfully']);
                exit;
            }
        } else {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $res = $rm->create_request( 'vehicles', 'edit', $_POST, $id );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Update request submitted for approval']);
                exit;
            }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-vehicles&success=Updated' ) );
		exit;
	}



	public function handle_delete_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_delete_vehicle_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_delete_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }
		
		$id = isset($_POST['id']) ? sanitize_text_field( $_POST['id'] ) : (isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '');
        
        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
             $res = $this->perform_delete_vehicle( ['id' => $id] );
             if ( wp_doing_ajax() ) {
                 if ( is_wp_error( $res ) ) {
                     wp_send_json_error(['message' => $res->get_error_message()]);
                 }
                 wp_send_json_success(['message' => 'Vehicle archived successfully']);
                 exit;
             }
        } else {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $res = $rm->create_request( 'vehicles', 'delete', ['id' => $id], $id );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Delete request submitted for approval']);
                exit;
            }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-vehicles&deleted=1' ) );
		exit;
	}

	public function handle_restore_vehicle() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_add_vehicle_nonce' ); // Reusing add nonce
        } else {
		    if ( ! check_admin_referer( 'sgvx51_add_vehicle_nonce' ) ) wp_die( 'Security check failed' );
        }
		
		$id = isset($_POST['id']) ? sanitize_text_field( $_POST['id'] ) : (isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '');
        
        if ( current_user_can( 'manage_options' ) ) {
            $this->db->update( 'vehicles', array( 'status' => 'approved' ), array( 'id' => $id ) );

            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $rm->log_audit('vehicle_restored', 'vehicles', $id, "Vehicle ID: $id");

            if ( wp_doing_ajax() ) {
                 wp_send_json_success(['message' => 'Vehicle restored successfully']);
                 exit;
             }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-vehicles&restored=1' ) );
		exit;
	}

	public function handle_approve_vehicle() {
		// Use the same nonce as add/edit for now or generic custom one? 
		// Simpler to rely on generic admin nonce for actions if not form.
		if ( ! current_user_can( 'manage_options' ) ) wp_die('Unauthorized');
		
		$id = sanitize_text_field( $_GET['id'] );
		$this->db->update( 'vehicles', array( 'status' => 'approved' ), array( 'id' => $id ) );
		wp_redirect( admin_url( 'admin.php?page=sgvx51-vehicles&approved=1' ) );
		exit;
	}

	public function render_page() {
        $rm = new SGVX51_Request_Manager();
        $unified = $rm->get_unified_data( 'vehicles', 'vehicles' );
        
        $flats = $this->db->get('flats');
        $residents = $this->db->get('residents');

        // Sort flats naturally
        usort($flats, function($a, $b) {
            return strnatcmp($a['id'] ?? '', $b['id'] ?? '');
        });

		SGVX51_Admin_App::render_view('vehicles', [
            'vehicles' => $unified['active'],
            'pending'  => $unified['pending'],
            'history'  => array_filter($unified['active'], function($v){ return ($v['status'] ?? '') === 'archived'; }),
            'flats'    => $flats,
            'residents'=> $residents
        ]);
	}
}
