<?php
/**
 * Module: Document Manager
 * Handles the "Document Vault".
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Document_Manager implements SGVX51_Module {

	private $drive;
	private $db;

	public function __construct() {
		$this->drive = new SGVX51_Drive_Manager();
		$this->db    = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_upload_doc', array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_sgvx51_upload_doc', array( $this, 'handle_upload' ) );
		
		add_action( 'admin_post_sgvx51_approve_doc', array( $this, 'handle_approve' ) );
		add_action( 'wp_ajax_sgvx51_approve_doc', array( $this, 'handle_approve' ) );

		add_action( 'admin_post_sgvx51_reject_doc', array( $this, 'handle_reject' ) );
		add_action( 'wp_ajax_sgvx51_reject_doc', array( $this, 'handle_reject' ) );

		add_action( 'admin_post_sgvx51_delete_doc', array( $this, 'handle_delete' ) );
		add_action( 'wp_ajax_sgvx51_delete_doc', array( $this, 'handle_delete' ) );

        // Module Registration
        add_filter( 'sgvx51_get_module_documents', array( $this, 'get_instance' ) );
	}

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'documents';
    }

    /**
     * Execute Request (The actual DB Logic for Status Updates)
     */
    public function execute_request( $action, $payload ) {
        $payload = (array) $payload;
        $doc_id = $payload['id'] ?? '';
        
        if ( empty( $doc_id ) ) return new WP_Error( 'missing_id', 'Document ID missing' );

        if ( $action === 'add' || $action === 'edit' || $action === 'upload' ) {
            return $this->db->update( 'documents', array( 'status' => 'approved' ), array( 'id' => $doc_id ) );
        } elseif ( $action === 'delete' ) {
            return $this->db->update( 'documents', array( 'status' => 'deleted' ), array( 'id' => $doc_id ) );
        }

        return new WP_Error( 'invalid_action', 'Unknown action: ' . $action );
    }

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Document Vault',
			'Documents',
			'manage_options',
			'sgvx51-documents',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Unified Upload Handler (Admin & Resident)
	 */
	public function handle_upload() {
		if ( ! is_user_logged_in() ) wp_die('Authentication required');
		
		// Use check_ajax_referer for both Admin & Frontend AJAX
		check_ajax_referer( 'sgvx51_document_nonce', '_wpnonce' );

		$user_id = get_current_user_id();
		$is_admin = current_user_can('manage_options');
		
		// 1. Determine Flat Number
		$flat_no = isset( $_POST['flat_no'] ) ? sanitize_text_field( $_POST['flat_no'] ) : '';
		
		if ( empty( $flat_no ) ) {
			// Try to find resident flat for this user
			$residents = $this->db->get( 'residents' );
			foreach ( $residents as $r ) {
				if ( (isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id) || (isset($r['wp_id']) && (int)$r['wp_id'] === $user_id) ) {
					$flat_no = $r['flat_no'];
					break;
				}
			}
		}

		if ( empty( $flat_no ) ) {
			if ( defined('DOING_AJAX') && DOING_AJAX ) wp_send_json_error(['message' => 'No Flat associated with your account']);
			wp_die( 'Error: No Flat associated with your account.' );
		}
		
		// 2. Handle File Upload
		if ( ! empty( $_FILES['doc_file'] ) ) {
			$res = $this->drive->upload_file( $flat_no, $_FILES['doc_file'] );
			
			if ( is_wp_error( $res ) ) {
				if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) wp_send_json_error( array( 'message' => $res->get_error_message() ) );
                wp_redirect( add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'error' => urlencode($res->get_error_message()) ), admin_url('admin.php') ) );
                exit;
			} else {
                // Insert Metadata
                $doc_id = uniqid('doc_');
				$title = isset($_POST['doc_name']) ? sanitize_text_field($_POST['doc_name']) : $_FILES['doc_file']['name'];
				
                $new_doc = array(
                    'id'          => $doc_id,
                    'flat_no'     => $flat_no,
                    'title'       => $title,
                    'category'    => $is_admin ? 'Admin Upload' : 'Resident Upload',
                    'file_path'   => $res,
                    'uploaded_by' => $user_id,
                    'status'      => $is_admin ? 'approved' : 'pending',
                    'created_at'  => current_time( 'mysql' ),
                );
                $this->db->insert( 'documents', $new_doc );

                // 3. Create Approval Request if Resident
                if ( ! $is_admin ) {
                    $rm = new SGVX51_Request_Manager();
                    $rm->create_request( 'documents', 'upload', $new_doc, $doc_id, 'documents', $flat_no );
                }

                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    wp_send_json_success( array( 'message' => 'Upload successful' ) );
                } else {
                    $redirect = $is_admin 
						? add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'success' => '1' ), admin_url('admin.php') )
						: wp_get_referer();
                    wp_redirect( $redirect );
                }
                exit;
			}
		}
		wp_die('No file uploaded');
	}

	public function handle_approve() {
        $request_id = isset( $_REQUEST['request_id'] ) ? sanitize_text_field( $_REQUEST['request_id'] ) : '';
        if ( ! empty( $request_id ) ) {
            $rm = new SGVX51_Request_Manager();
            $res = $rm->approve_request( $request_id );
            
            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
                wp_send_json_success( ['message' => 'Document approved'] );
            } else {
                wp_redirect( admin_url( 'admin.php?page=sgvx51-documents&updated=1' ) );
            }
            exit;
        }
		$this->update_status( 'approved' );
	}

	public function handle_reject() {
        $request_id = isset( $_REQUEST['request_id'] ) ? sanitize_text_field( $_REQUEST['request_id'] ) : '';
        $note = isset( $_REQUEST['admin_note'] ) ? sanitize_textarea_field( $_REQUEST['admin_note'] ) : '';

        if ( ! empty( $request_id ) ) {
            $rm = new SGVX51_Request_Manager();
            $res = $rm->reject_request( $request_id, $note );

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
                wp_send_json_success( ['message' => 'Document rejected'] );
            } else {
                wp_redirect( admin_url( 'admin.php?page=sgvx51-documents&updated=1' ) );
            }
            exit;
        }
        
		$doc_id = isset( $_REQUEST['doc_id'] ) ? sanitize_text_field( $_REQUEST['doc_id'] ) : '';
		$docs = $this->db->get( 'documents' );
        $new_status = 'rejected';

		foreach ( $docs as $d ) {
			if ( $d['id'] === $doc_id ) {
                if($d['status'] === 'deletion_pending') {
                    $new_status = 'approved'; 
                }
				break;
			}
		}
        
		$this->update_status( $new_status );
	}

	private function update_status( $status ) {
		// Use check_ajax_referer if AJAX, else check_admin_referer
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			check_ajax_referer( 'sgvx51_document_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_doc_action' ) ) wp_die( 'Security check failed' );
		}
		
		$doc_id = isset( $_REQUEST['doc_id'] ) ? sanitize_text_field( $_REQUEST['doc_id'] ) : '';
		if ( ! $doc_id ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) wp_send_json_error( array( 'message' => 'Missing Document ID' ) );
			wp_die( 'Missing Document ID' );
		}

        // 1. Synchronize with Request Manager if a pending request exists
        if ( in_array( $status, array( 'approved', 'rejected' ) ) ) {
            $rm = new SGVX51_Request_Manager();
            // Passing doc_id here works because RM has a fallback to search by entity_id
            $res = ( $status === 'approved' ) ? $rm->approve_request( $doc_id ) : $rm->reject_request( $doc_id );
            
            if ( ! is_wp_error( $res ) ) {
                // Request Manager handled DB update, audit logs, and notifications
                if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                    wp_send_json_success( array( 'message' => 'Document processed and request synced.' ) );
                } else {
                    wp_redirect( admin_url( 'admin.php?page=sgvx51-documents&updated=1' ) );
                }
                exit;
            }
        }

		$this->db->update( 'documents', array( 'status' => $status ), array( 'id' => $doc_id ) );

        // Manual Notification Trigger (Backup for direct admin actions)
        if ( class_exists('Society_GoVernX') ) {
            $docs = $this->db->get('documents');
            $doc = null;
            foreach($docs as $d) { if($d['id'] === $doc_id) { $doc = $d; break; } }
            
            if ( $doc && !empty($doc['uploaded_by']) && in_array($status, ['approved', 'rejected']) ) {
                $sgvx = Society_GoVernX::get_instance();
                $event = ($status === 'approved') ? 'request_approved' : 'request_rejected';
                
                $admin_user = wp_get_current_user();
                $admin_name = $admin_user ? $admin_user->display_name : 'Admin';

                $sgvx->notifications->trigger($event, $doc['uploaded_by'], [
                    'resident_name' => 'Resident', // Logic to fetch name if needed
                    'request_type'  => 'Document',
                    'admin_name'    => $admin_name,
                    'time'          => current_time('d M Y, h:i A'),
                    'details'       => "Document '" . $doc['title'] . "' was " . $status . "."
                ]);
            }
        }

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success( array( 'message' => 'Document status updated to ' . $status ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-documents&updated=1' ) );
		}
		exit;
	}

	public function handle_delete() {
		// Nonce check: JS uses Config.nonce (sgvx51_document_nonce)
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			check_ajax_referer( 'sgvx51_document_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_delete_doc_nonce' ) ) wp_die( 'Security check failed' );
		}
		
		$flat_no = isset( $_REQUEST['flat'] ) ? sanitize_text_field( $_REQUEST['flat'] ) : '';
		$doc_id = isset( $_REQUEST['doc_id'] ) ? sanitize_text_field( $_REQUEST['doc_id'] ) : '';

		if ( $doc_id ) {
			$this->db->update( 'documents', array( 'status' => 'deleted' ), array( 'id' => $doc_id ) );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			wp_send_json_success( array( 'message' => 'Document marked for deletion' ) );
		} else {
			wp_redirect( add_query_arg( array( 'page' => 'sgvx51-documents', 'flat' => $flat_no, 'deleted' => '1' ), admin_url('admin.php') ) );
		}
		exit;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('documents');
	}
}
