<?php
/**
 * Module: Staff Manager
 * Handles Maintenance Staff & Daily Help.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Staff_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ), 200 );

		// AJAX
		add_action( 'wp_ajax_sgvx51_add_staff', array( $this, 'handle_add_staff' ) );
		add_action( 'wp_ajax_sgvx51_edit_staff', array( $this, 'handle_edit_staff' ) );
		add_action( 'wp_ajax_sgvx51_delete_staff', array( $this, 'handle_delete_staff' ) );
		add_action( 'wp_ajax_sgvx51_restore_staff', array( $this, 'handle_restore_staff' ) );

		add_action( 'admin_post_sgvx51_add_staff', array( $this, 'handle_add_staff' ) );
		add_action( 'admin_post_sgvx51_edit_staff', array( $this, 'handle_edit_staff' ) );
		add_action( 'admin_post_sgvx51_delete_staff', array( $this, 'handle_delete_staff' ) );
		add_action( 'admin_post_sgvx51_restore_staff', array( $this, 'handle_restore_staff' ) );

		// Self-Heal Schema (Ensure columns exist)
		if ( is_admin() ) {
			$this->db->verify_column( 'daily_help', 'sex', 'varchar(10) DEFAULT "" NOT NULL' );
			$this->db->verify_column( 'daily_help', 'visiting_hours', 'varchar(50) DEFAULT "" NOT NULL' );
			$this->db->verify_column( 'daily_help', 'created_by', 'bigint(20) DEFAULT 0 NOT NULL' );
            $this->db->verify_column( 'daily_help', 'flat_no', 'varchar(50) DEFAULT "" NOT NULL' ); // Legacy flat link
		}

        // Register Module
        add_filter( 'sgvx51_get_module_daily_help', array( $this, 'get_instance' ) );
	}

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'daily_help';
    }

    public function execute_request( $action, $payload ) {
    $payload = (array) $payload;
    if ( $action === 'add' ) {
        $id = $payload['id'] ?? '';
        $all = $this->db->get('daily_help');
        $exists = false;
        foreach($all as $s) { if(($s['id']??'') === $id) { $exists = true; break; } }

        if($exists) {
            return $this->db->update('daily_help', ['status' => 'approved'], ['id' => $id]);
        } else {
            return $this->perform_add_staff( $payload );
        }
    } elseif ( $action === 'edit' ) {
        return $this->perform_edit_staff( $payload );
    } elseif ( $action === 'delete' ) {
        return $this->perform_delete_staff( $payload );
    }
    return new WP_Error( 'invalid_action', 'Unknown action: ' . $action );
}

    private function perform_add_staff( $data ) {
		$db_data = array(
			'name'           => sanitize_text_field( $data['name'] ),
			'role'           => sanitize_text_field( $data['role'] ),
			'phone'          => sanitize_text_field( $data['phone'] ),
			'sex'            => sanitize_text_field( $data['sex'] ),
			'visiting_hours' => sanitize_text_field( $data['visiting_hours'] ),
            'flats_served'   => isset($data['flats_served']) ? $data['flats_served'] : '[]',
			'created_at'     => current_time( 'mysql' ),
            'id'             => isset($data['id']) ? $data['id'] : uniqid('staff_'),
            'status'         => isset($data['status']) ? $data['status'] : 'approved',
            'flat_no'        => isset($data['flat_no']) ? sanitize_text_field($data['flat_no']) : ''
		);
		return $this->db->insert( 'daily_help', $db_data );
    }

    private function perform_edit_staff( $data ) {
        $id = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : (isset($data['id']) ? $data['id'] : '');
        if(!$id) return new WP_Error('missing_id', 'Staff ID Missing');

        // Fetch Existing
        $existing = [];
        $all = $this->db->get('daily_help');
        foreach($all as $s) { if(isset($s['id']) && $s['id'] === $id) { $existing = $s; break; } }

        if ( empty( $existing ) ) {
            return new WP_Error( 'not_found', 'Staff member not found for update.' );
        }

        $update_data = array(
            'name'           => isset($data['name']) ? sanitize_text_field( $data['name'] ) : ($existing['name'] ?? ''),
            'role'           => isset($data['role']) ? sanitize_text_field( $data['role'] ) : ($existing['role'] ?? ''),
            'phone'          => isset($data['phone']) ? sanitize_text_field( $data['phone'] ) : ($existing['phone'] ?? ''),
            'sex'            => isset($data['sex']) ? sanitize_text_field( $data['sex'] ) : ($existing['sex'] ?? ''),
            'visiting_hours' => isset($data['visiting_hours']) ? sanitize_text_field( $data['visiting_hours'] ) : ($existing['visiting_hours'] ?? ''),
            // Preserve other fields
            'flats_served'   => isset($data['flats_served']) ? $data['flats_served'] : ($existing['flats_served'] ?? '[]'),
            'status'         => $existing['status'] ?? 'approved',
            'created_by'     => $existing['created_by'] ?? '',
            'flat_no'        => isset($data['flat_no']) ? sanitize_text_field($data['flat_no']) : ($existing['flat_no'] ?? '')
        );
        return $this->db->update( 'daily_help', $update_data, ['id' => $id] );
    }

    private function perform_delete_staff( $data ) {
        $id = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : (isset($data['id']) ? $data['id'] : '');
        if(!$id) return new WP_Error('missing_id', 'Staff ID Missing');
        return $this->db->update( 'daily_help', ['status' => 'archived'], ['id' => $id] );
    }

    public function handle_restore_staff() {
        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_staff_nonce' );
        } else {
            if ( ! check_admin_referer( 'sgvx51_staff_nonce' ) ) wp_die( 'Security check failed' );
        }
        
        $id = isset($_POST['staff_id']) ? sanitize_text_field( $_POST['staff_id'] ) : (isset($_GET['staff_id']) ? sanitize_text_field($_GET['staff_id']) : '');
        
        if ( current_user_can( 'manage_options' ) ) {
             $this->db->update( 'daily_help', array( 'status' => 'approved' ), array( 'id' => $id ) );
             
             require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
             $rm = new SGVX51_Request_Manager();
             $rm->log_audit('staff_restored', 'daily_help', $id, "Staff ID: $id");

             if ( wp_doing_ajax() ) {
                 wp_send_json_success(['message' => 'Staff member restored successfully']);
                 exit;
             }
        }

        wp_redirect( admin_url( 'admin.php?page=sgvx51-staff&status=updated' ) );
        exit;
    }

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Staff & Help',
			'Staff & Help',
			'manage_options',
			'sgvx51-staff',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
        $rm = new SGVX51_Request_Manager();
        $unified = $rm->get_unified_data( 'daily_help', 'daily_help' );
        $flats = $this->db->get('flats');
		
		SGVX51_Admin_App::render_view('staff', [
			'staff'   => $unified['active'],
            'pending' => $unified['pending'],
            'archived'=> array_filter($unified['active'], function($s){ return isset($s['status']) && $s['status'] === 'archived'; }),
            'flats'   => $flats
		]);
	}

	public function handle_add_staff() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_staff_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_staff_nonce' ) ) wp_die( 'Security check failed' );
        }

        $_POST['id'] = uniqid('staff_'); 
        
        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_add_staff( $_POST );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff added successfully']);
                exit;
            }
        } else {
           $_POST['status'] = 'pending';
           $this->perform_add_staff( $_POST );

           require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
           $rm = new SGVX51_Request_Manager();
           $res = $rm->create_request( 'daily_help', 'add', $_POST, $_POST['id'] );
           if ( wp_doing_ajax() ) {
               if ( is_wp_error( $res ) ) {
                   wp_send_json_error(['message' => $res->get_error_message()]);
               }
               wp_send_json_success(['message' => 'Staff added and submitted for approval']);
               exit;
           }
       }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-staff&status=added' ) );
		exit;
	}

	public function handle_edit_staff() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_staff_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_staff_nonce' ) ) wp_die( 'Security check failed' );
        }
        
        $id = sanitize_text_field( $_POST['staff_id'] );

        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_edit_staff( $_POST );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff updated successfully']);
                exit;
            }
        } else {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $res = $rm->create_request( 'daily_help', 'edit', $_POST, $id );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Update request submitted for approval']);
                exit;
            }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-staff&status=updated' ) );
		exit;
	}

	public function handle_delete_staff() {
		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_staff_nonce' );
        } else {
		    if ( ! check_admin_referer( 'sgvx51_staff_nonce' ) ) wp_die( 'Security check failed' );
        }
        
        $id = sanitize_text_field( $_POST['staff_id'] );

        // IF ADMIN: Immediate
        if ( current_user_can( 'manage_options' ) ) {
            $res = $this->perform_delete_staff( ['id' => $id] );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff record deleted']);
                exit;
            }
        } else {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $res = $rm->create_request( 'daily_help', 'delete', ['staff_id' => $id, 'id' => $id], $id );
            if ( wp_doing_ajax() ) {
                if ( is_wp_error( $res ) ) wp_send_json_error(['message' => $res->get_error_message()]);
                wp_send_json_success(['message' => 'Deletion request submitted for approval']);
            }
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-staff&status=deleted' ) );
		exit;
	}
}
