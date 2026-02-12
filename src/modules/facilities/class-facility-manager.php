<?php
/**
 * Module: Facility Manager
 * Handles Facilities (Clubhouse, etc.) and Bookings.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Facility_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_sgvx51_add_facility', array( $this, 'handle_add_facility' ) );
		add_action( 'admin_post_sgvx51_edit_facility', array( $this, 'handle_edit_facility' ) );
		add_action( 'admin_post_sgvx51_delete_facility', array( $this, 'handle_delete_facility' ) );
		add_action( 'admin_post_sgvx51_book_facility', array( $this, 'handle_book_facility' ) );
		add_action( 'admin_post_sgvx51_edit_booking', array( $this, 'handle_edit_booking' ) );
		add_action( 'admin_post_sgvx51_delete_booking', array( $this, 'handle_delete_booking' ) );

        // AJAX
        add_action( 'wp_ajax_sgvx51_add_facility', array( $this, 'handle_add_facility' ) );
        add_action( 'wp_ajax_sgvx51_edit_facility', array( $this, 'handle_edit_facility' ) );
        add_action( 'wp_ajax_sgvx51_delete_facility', array( $this, 'handle_delete_facility' ) );
        add_action( 'wp_ajax_sgvx51_book_facility', array( $this, 'handle_book_facility' ) );
        add_action( 'wp_ajax_sgvx51_edit_booking', array( $this, 'handle_edit_booking' ) );
        add_action( 'wp_ajax_sgvx51_delete_booking', array( $this, 'handle_delete_booking' ) );

        // Module Registration
        add_filter( 'sgvx51_get_module_facilities', array( $this, 'get_instance' ) );
	}

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'facilities';
    }

    public function execute_request( $action, $payload ) {
        $payload = (array) $payload;
        if ( $action === 'book' ) {
            $id = $payload['id'] ?? '';
            return $this->db->update( 'bookings', array( 'status' => 'confirmed' ), array( 'id' => $id ) );
        }
        return new WP_Error( 'invalid_action', 'Unknown action' );
    }

	public function register_menu() {
		add_submenu_page(
			'sgvx51-settings',
			'Facilities & Bookings',
			'Facilities',
			'manage_options',
			'sgvx51-facilities',
			array( $this, 'render_page' )
		);
	}

	public function handle_add_facility() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'sgvx51_facility_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_facility_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$data = array(
			'name'          => sanitize_text_field( $_POST['name'] ),
			'rate'          => floatval( $_POST['rate'] ), // Renamed for consistency with view input
			'rate_unit'     => sanitize_text_field( $_POST['rate_unit'] ), // New Field
			'max_hours'     => intval( $_POST['max_hours'] ),
			'rules'         => sanitize_textarea_field( $_POST['rules'] ),
			'status'        => 'active',
			'id'            => uniqid('fac_'), // Unique ID for referencing
		);

		$result = $this->db->insert( 'facilities', $data );
        
        if ( wp_doing_ajax() ) {
            if ( is_wp_error( $result ) ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            } else {
                wp_send_json_success( array( 'message' => 'Facility added successfully' ) );
            }
            exit;
        }

		if ( is_wp_error( $result ) ) {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&error=' . urlencode( $result->get_error_message() ) ) );
		} else {
			wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&success=1' ) );
		}
		exit;
	}

	public function handle_edit_facility() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'sgvx51_facility_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_facility_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$id = sanitize_text_field( $_POST['facility_id'] );
		$data = array(
			'name'          => sanitize_text_field( $_POST['name'] ),
			'rate'          => floatval( $_POST['rate'] ),
			'rate_unit'     => sanitize_text_field( $_POST['rate_unit'] ),
			'max_hours'     => intval( $_POST['max_hours'] ),
			'rules'         => sanitize_textarea_field( $_POST['rules'] ),
		);

		$facilities = $this->db->get( 'facilities' );
		$target_id = null;
		
		// Resolve ID if mixed
		foreach ( $facilities as $f ) {
			if ( (isset($f['id']) && $f['id'] === $id) || $f['name'] === $id ) {
				$target_id = isset($f['id']) ? $f['id'] : $id;
				break;
			}
		}

		if ($target_id) {
			$this->db->update( 'facilities', $data, array( 'id' => $target_id ) );
            if ( wp_doing_ajax() ) {
                wp_send_json_success( array( 'message' => 'Facility updated successfully' ) );
                exit;
            }
			wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&success=1&msg=Updated' ) );
		} else {
            if ( wp_doing_ajax() ) {
                wp_send_json_error( array( 'message' => 'Facility not found' ) );
                exit;
            }
			wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&error=Facility not found' ) );
		}
		exit;
	}

	public function handle_delete_facility() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'sgvx51_delete_facility_nonce' );
        } else {
            if ( ! check_admin_referer( 'sgvx51_delete_facility_nonce' ) ) {
                wp_die( 'Security check failed' );
            }
        }

		$id = isset($_POST['id']) ? sanitize_text_field( $_POST['id'] ) : (isset($_GET['id']) ? sanitize_text_field( $_GET['id'] ) : '');
		$this->db->delete( 'facilities', array( 'id' => $id ) );

        if ( wp_doing_ajax() ) {
            wp_send_json_success( array( 'message' => 'Facility deleted successfully' ) );
            exit;
        }

		wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&deleted=1' ) );
		exit;
	}

	public function handle_book_facility() {
		if ( ! is_user_logged_in() ) wp_die( 'Login Required' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'sgvx51_facility_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_facility_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$facility_id = sanitize_text_field( $_POST['facility_id'] );
		$start_time  = sanitize_text_field( $_POST['start_time'] ); // datetime-local format
		$end_time    = sanitize_text_field( $_POST['end_time'] );
		$resident_id = sanitize_text_field( $_POST['resident_id'] );

		// 1. Validation: Overlap Check
		if ( $this->check_overlap( $facility_id, $start_time, $end_time ) ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_error( array( 'message' => 'Slot already booked!' ) );
                exit;
            }
			wp_redirect( wp_get_referer() . '&error=' . urlencode( 'Slot already booked!' ) );
			exit;
		}

		// 2. Calculate Amount
		$duration_seconds = strtotime( $end_time ) - strtotime( $start_time );
		$duration_hours   = max( 1, ceil( $duration_seconds / 3600 ) ); // Min 1 hour
		$facilities       = $this->db->get( 'facilities' );
		$rate             = 0;
		foreach ( $facilities as $f ) {
			if ( $f['id'] === $facility_id ) {
				$rate = floatval( $f['rate'] );
				break;
			}
		}
		$amount = $rate * $duration_hours;

		$data = array(
			'id'          => uniqid( 'bk_' ),
			'facility_id' => $facility_id,
			'resident_id' => $resident_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'status'      => 'pending', // Default to pending
			'amount'      => $amount,
			'created_at'  => current_time( 'mysql' ),
		);

		$result = $this->db->insert( 'bookings', $data );

        if ( is_wp_error( $result ) ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                exit;
            }
            wp_redirect( wp_get_referer() . '&error=' . urlencode( $result->get_error_message() ) );
            exit;
        }

        // Create Approval Request
        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $request_id = $rm->create_request( 'facilities', 'book', $data, $data['id'] );

        // Check for Auto-Approval
        $approval_mode = get_option( 'sgvx51_approval_facility', 'manual' );
        $is_admin_booking = current_user_can( 'manage_options' );

        if ( $approval_mode === 'auto' || $is_admin_booking ) {
            $rm->approve_request( $request_id );
            if ( wp_doing_ajax() ) {
                wp_send_json_success( array( 'message' => 'Facility booked successfully' ) );
                exit;
            }
        } else {
            if ( wp_doing_ajax() ) {
                wp_send_json_success( array( 'message' => 'Booking request submitted for approval' ) );
                exit;
            }
        }

		$referer = wp_get_referer();
		if ( strpos( $referer, 'wp-admin' ) !== false && current_user_can( 'manage_options' ) ) {
			$redirect_url = admin_url( 'admin.php?page=sgvx51-facilities&success=1&msg=booked' );
		} else {
            $msg = ( $approval_mode === 'auto' || $is_admin_booking ) ? 'booking_success' : 'request_submitted';
			$redirect_url = add_query_arg( $msg, '1', $referer );
		}
		wp_redirect( $redirect_url );
		exit;
	}

	public function handle_edit_booking() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'sgvx51_facility_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_facility_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$id          = sanitize_text_field( $_POST['booking_id'] );

        // 1. Synchronize with Request Manager if a pending request exists
        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $sync_res = $rm->approve_request( $id );
        
        if ( ! is_wp_error( $sync_res ) ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_success(['message' => 'Booking updated and request synchronized']);
            } else {
                wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&success=1&msg=updated' ) );
            }
            exit;
        }

		$facility_id = sanitize_text_field( $_POST['facility_id'] );
		$start_time  = sanitize_text_field( $_POST['start_time'] );
		$end_time    = sanitize_text_field( $_POST['end_time'] );
		$resident_id = sanitize_text_field( $_POST['resident_id'] );
		$status      = sanitize_text_field( $_POST['status'] ?? 'confirmed' );

		// Overlap Check (Exclude self)
		if ( $this->check_overlap( $facility_id, $start_time, $end_time, $id ) ) {
			if ( wp_doing_ajax() ) {
				wp_send_json_error( array( 'message' => 'Slot already booked!' ) );
				exit;
			}
			wp_redirect( wp_get_referer() . '&error=' . urlencode( 'Slot already booked!' ) );
			exit;
		}

		// Recalculate Amount
		$duration_seconds = strtotime( $end_time ) - strtotime( $start_time );
		$duration_hours   = max( 1, ceil( $duration_seconds / 3600 ) );
		$facilities       = $this->db->get( 'facilities' );
		$rate             = 0;
		foreach ( $facilities as $f ) {
			if ( $f['id'] === $facility_id ) {
				$rate = floatval( $f['rate'] );
				break;
			}
		}
		$amount = $rate * $duration_hours;

		$data = array(
			'facility_id' => $facility_id,
			'resident_id' => $resident_id,
			'start_time'  => $start_time,
			'end_time'    => $end_time,
			'status'      => $status,
			'amount'      => $amount,
		);

		$this->db->update( 'bookings', $data, array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			wp_send_json_success( array( 'message' => 'Booking updated successfully' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&success=1&msg=updated' ) );
		exit;
	}

	public function handle_delete_booking() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		if ( wp_doing_ajax() ) {
			check_ajax_referer( 'sgvx51_facility_nonce', '_wpnonce' );
		} else {
			if ( ! check_admin_referer( 'sgvx51_facility_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}

		$id = sanitize_text_field( $_POST['id'] );

        // 1. Synchronize with Request Manager if a pending request exists
        require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SGVX51_Request_Manager();
        $sync_res = $rm->approve_request( $id );
        
        if ( ! is_wp_error( $sync_res ) ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_success(['message' => 'Booking deleted and request synchronized']);
            } else {
                wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&deleted=1' ) );
            }
            exit;
        }

		$this->db->delete( 'bookings', array( 'id' => $id ) );

		if ( wp_doing_ajax() ) {
			wp_send_json_success( array( 'message' => 'Booking deleted successfully' ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=sgvx51-facilities&deleted=1' ) );
		exit;
	}

	private function check_overlap( $facility_id, $start, $end, $exclude_id = '' ) {
		$bookings = $this->db->get( 'bookings' );
		foreach ( $bookings as $b ) {
			if ( ! empty( $exclude_id ) && $b['id'] === $exclude_id ) {
				continue;
			}
			if ( $b['facility_id'] !== $facility_id || in_array( $b['status'], ['cancelled', 'rejected'] ) ) {
				continue;
			}
			// Simple Time Overlap Logic
			if ( ( $start >= $b['start_time'] && $start < $b['end_time'] ) ||
				 ( $end > $b['start_time'] && $end <= $b['end_time'] ) ||
				 ( $start <= $b['start_time'] && $end >= $b['end_time'] ) ) {
				return true;
			}
		}
		return false;
	}

	public function render_page() {
		SGVX51_Admin_App::render_view('facilities');
	}
}
