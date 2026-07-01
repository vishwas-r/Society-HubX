<?php
/**
 * Class: Frontend Dashboard
 * Handles the Resident Dashboard Shortcode.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Frontend_Dashboard {

	private $db;
	private $drive;
	private $media;

	public function __construct() {
		$this->db = new SHUBX51_DB_Router();
		$this->drive = new SHUBX51_Drive_Manager();
		$this->media = new SHUBX51_Media_Manager();
		
		add_shortcode( 'society_hubx_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'society_hubx_notices', array( $this, 'render_notices' ) );
		add_shortcode( 'society_hubx_directory', array( $this, 'render_directory' ) );
		// Form Handlers
		add_action( 'admin_post_shubx51_update_profile', array( $this, 'handle_profile_update' ) );
		add_action( 'admin_post_shubx51_frontend_upload_doc', array( $this, 'handle_doc_upload' ) );
		add_action( 'admin_post_shubx51_frontend_delete_doc', array( $this, 'handle_doc_delete' ) );
		add_action( 'admin_post_shubx51_add_family', array( $this, 'handle_add_family' ) );
		add_action( 'admin_post_shubx51_add_daily_help', array( $this, 'handle_add_daily_help' ) );
		add_action( 'admin_post_shubx51_add_vehicle_frontend', array( $this, 'handle_add_vehicle_frontend' ) );
		add_action( 'admin_post_shubx51_update_utilities', array( $this, 'handle_update_utilities' ) );
		add_action( 'admin_post_shubx51_request_delete_doc', array( $this, 'handle_request_delete_doc' ) );
		
		add_action( 'admin_post_shubx51_edit_family', array( $this, 'handle_edit_family' ) );
		add_action( 'admin_post_shubx51_edit_daily_help', array( $this, 'handle_edit_daily_help' ) );
		add_action( 'admin_post_shubx51_edit_vehicle_frontend', array( $this, 'handle_edit_vehicle' ) );
		// Compatibility: accept older action name so frontend posts with `shubx51_edit_vehicle` also work
		add_action( 'admin_post_shubx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ), 1 );

        // Delete Handlers
        add_action( 'admin_post_shubx51_delete_family', array( $this, 'handle_delete_family' ) );
        add_action( 'admin_post_shubx51_delete_daily_help', array( $this, 'handle_delete_daily_help' ) );
		add_action( 'admin_post_shubx51_delete_vehicle_frontend', array( $this, 'handle_delete_vehicle_frontend' ) );
        
        // AJAX Handlers for Resident Dashboard
        add_action( 'wp_ajax_shubx51_add_vehicle_frontend', array( $this, 'handle_add_vehicle_frontend' ) );
        add_action( 'wp_ajax_shubx51_edit_vehicle_frontend', array( $this, 'handle_edit_vehicle' ) ); 
        add_action( 'wp_ajax_shubx51_delete_vehicle_frontend', array( $this, 'handle_delete_vehicle_frontend' ) );

        add_action( 'wp_ajax_shubx51_add_family_frontend', array( $this, 'handle_add_family' ) );
        add_action( 'wp_ajax_shubx51_add_family', array( $this, 'handle_add_family' ) ); // Fix: Alias for frontend value
        add_action( 'wp_ajax_shubx51_edit_family_frontend', array( $this, 'handle_edit_family' ) );
        add_action( 'wp_ajax_shubx51_edit_family', array( $this, 'handle_edit_family' ) ); // Fix: Alias for frontend value
        add_action( 'wp_ajax_shubx51_delete_family_frontend', array( $this, 'handle_delete_family' ) );

        add_action( 'wp_ajax_shubx51_add_daily_help', array( $this, 'handle_add_daily_help' ) );
        add_action( 'wp_ajax_shubx51_edit_help_frontend', array( $this, 'handle_edit_daily_help' ) );
        add_action( 'wp_ajax_shubx51_delete_daily_help_frontend', array( $this, 'handle_delete_daily_help' ) );
		
        // Payment Submission Handler
        add_action( 'wp_ajax_shubx51_submit_payment_request', array( $this, 'handle_submit_payment' ) );

		// Switch Flat Handler
		add_action( 'wp_ajax_shubx51_switch_flat', array( $this, 'handle_switch_flat' ) );

		// Login & Access Control
		add_action( 'wp_ajax_shubx51_resident_login', array( $this, 'handle_resident_login' ) );
		add_action( 'wp_ajax_nopriv_shubx51_resident_login', array( $this, 'handle_resident_login' ) );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_residents' ) );
	}

	/**
	 * Handle Profile Update (Frontend)
	 */
	public function handle_profile_update() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'shubx51_update_profile_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$user_id = get_current_user_id();
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$blood_group = isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '';
		
		// 1. Find Resident
		$residents = $this->db->get( 'residents' );
		$target_resident = null;
		
		foreach ( $residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$target_resident = $r;
				break;
			}
		}

		// 2. Update Data
		if ( $target_resident && isset($target_resident['id']) ) {
			$update_data = array(
				'phone'       => $phone,
				'email'       => $email,
				'blood_group' => $blood_group
			);

			// Handle Photo Upload
			if ( isset( $_FILES['profile_photo']['size'] ) && $_FILES['profile_photo']['size'] > 0 ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_profile_photo.
				$photo_url = $this->media->upload_profile_photo( 
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_profile_photo.
					$_FILES['profile_photo'], 
					$target_resident['flat_no'], 
					$target_resident['name'] 
				);
				if ( ! is_wp_error( $photo_url ) ) {
					$update_data['profile_photo'] = $photo_url;
				}
			}
			
			$where = array( 'id' => $target_resident['id'] );
			$this->db->update( 'residents', $update_data, $where );
		}

		// 3. Redirect Back
		wp_safe_redirect( wp_get_referer() . '?profile_updated=1' );
		exit;
	}

	/**
	 * Handle Payment Confirmation (Frontend)
	 */
	public function handle_submit_payment() {
		check_ajax_referer( 'shubx51_admin_nonce', '_ajax_nonce' );
		if ( ! is_user_logged_in() ) wp_send_json_error( ['message' => 'Unauthorized'] );

		$user_id = get_current_user_id();
		$invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';
		$amount = floatval( $_POST['amount'] ?? 0 );
		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time('Y-m-d');
		$method = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : '';

		if ( empty($invoice_id) || $amount <= 0 || empty($reference) ) {
			wp_send_json_error( ['message' => 'Missing valid payment details.'] );
		}

		// Find Resident
		$residents = $this->db->get( 'residents' );
		$resident = null;
		foreach ( $residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$resident = $r;
				break;
			}
		}

		if ( ! $resident ) wp_send_json_error( ['message' => 'Resident profile not found.'] );

		$req_manager = new SHUBX51_Request_Manager();
		$payload = [
			'category'   => 'Payment Confirmation',
			'invoice_id' => $invoice_id,
			'amount'     => $amount,
			'reference'  => $reference,
			'date'       => $date,
			'method'     => $method,
		];

		$res = $req_manager->create_request( 'accounts', 'payment', $payload, $invoice_id, 'invoices', $resident['flat_no'] );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( ['message' => $res->get_error_message()] );
		}

		wp_send_json_success( ['message' => 'Payment confirmation submitted for review.'] );
	}

	/**
	 * Handle Switch Flat Context (Frontend AJAX)
	 */
	public function handle_switch_flat() {
		// Verify frontend nonce
		check_ajax_referer( 'shubx51_frontend_nonce', '_ajax_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$flat_id = isset( $_POST['flat_id'] ) ? sanitize_text_field( wp_unslash( $_POST['flat_id'] ) ) : '';
		if ( empty( $flat_id ) ) {
			wp_send_json_error( array( 'message' => 'No flat ID provided.' ) );
		}

		$user_id = get_current_user_id();
		$residents = $this->db->get( 'residents' );
		$resident = null;
		foreach ( $residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$resident = $r;
				break;
			}
		}

		if ( ! $resident ) {
			wp_send_json_error( array( 'message' => 'Resident profile not found.' ) );
		}

		// Get all flats for this resident
		$flat_ids = $this->db->get_resident_flats( $resident['id'] );
		if ( empty( $flat_ids ) && ! empty( $resident['flat_no'] ) ) {
			$flat_ids[] = $resident['flat_no'];
		}

		// Validate requested flat belongs to resident
		if ( ! in_array( $flat_id, $flat_ids, true ) ) {
			wp_send_json_error( array( 'message' => 'You do not have access to this flat.' ) );
		}

		// Set in session
		if ( ! session_id() ) {
			session_start();
		}
		$_SESSION['shubx51_active_flat_id'] = $flat_id;

		wp_send_json_success( array( 'message' => 'Switched to flat context successfully.' ) );
	}

	/**
	 * Handle Document Upload (Frontend)
	 */
	public function handle_doc_upload() {
		// Handled by Document_Manager::handle_upload now. 
		// We keep this method if needed for other logic, but removed action hooks to avoid conflict.
	}

	/**
	 * Render the Dashboard.
	 */
	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			$society_info = array(
				'name'     => get_option( 'shubx51_society_name', 'Our Society' ),
				'address1' => get_option( 'shubx51_society_address_line1', '' ),
				'address2' => get_option( 'shubx51_society_address_line2', '' ),
				'city'     => get_option( 'shubx51_society_city', '' ),
				'contact'  => get_option( 'shubx51_society_contact', '' )
			);
			ob_start();
			include SHUBX51_PLUGIN_DIR . 'templates/resident-login.php';
			return ob_get_clean();
		}

		$user_id = get_current_user_id();
		$data = $this->get_dashboard_data( $user_id );
		
		if ( is_wp_error($data) ) {
			if ( $data->get_error_code() === 'no_resident' ) {
				if ( current_user_can( 'manage_options' ) ) {
					return '<div class="shubx51-alert alert alert-info">
								<h4>Welcome Admin!</h4>
								<p>Your account is not linked to a specific Resident Profile (Flat), so you cannot view the Resident Dashboard.</p>
								<a href="' . esc_url( admin_url( 'admin.php?page=shubx51-settings' ) ) . '" class="btn btn-primary">Go to Admin Settings</a>
							</div>';
				}
				return '<div class="shubx51-alert alert alert-danger">No Resident Profile linked to your account. Please contact the Society Admin.</div>';
			}
		}

		$resident = $data['resident'];
		$expense_chart_data = $data['expenseChartData'];
		$payment_history = $data['paymentHistory'];
		$my_requests = $data['my_requests'];
		$my_flat = $data['flat'];
		$my_vehicles = $data['vehicles'];
		$pending_payment_requests = $data['pending_payment_requests'];
		$summary_month = $data['summary_month'];

		// 4. Render Template.
         
         wp_enqueue_script( 'shubx51-dashboard-js', SHUBX51_PLUGIN_URL . 'assets/js/shubx-dashboard.js', array('jquery', 'shubx51-chartjs', 'shubx51-html2canvas'), current_time('U'), true );
         
         // Localize Data for Dashboard
         wp_localize_script( 'shubx51-dashboard-js', 'SHUBXDashboardData', array(
            'expenseChartData' => $expense_chart_data,
            'paymentHistory'   => $payment_history,
            'resident'         => $resident, // Pass resident data
            'my_requests'      => array_values($my_requests), // Pass resident requests
            'nonce'            => wp_create_nonce('shubx51_frontend_nonce'),
            'rest_url'         => esc_url_raw( rest_url( 'society-hubx/v1/' ) ),
            'rest_nonce'       => wp_create_nonce( 'wp_rest' ),
            'my_flats'         => $data['my_flats'] ?? array(),
            'active_flat_no'   => $data['active_flat_no'] ?? ''
         ));
        
		ob_start();
		include SHUBX51_PLUGIN_DIR . 'templates/dashboard.php';
		return ob_get_clean();
	}

	public function get_dashboard_data( $user_id ) {
		// 1. Fetch Resident Profile.
		$all_residents = $this->db->get( 'residents' );
		$resident = null;
		foreach ( $all_residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$resident = $r;
				break;
			}
		}

		if ( ! $resident ) {
			return new WP_Error('no_resident', 'No resident profile linked to your account.');
		}

		// Load all flats owned by this resident
		$my_flat_ids = $this->db->get_resident_flats( $resident['id'] );
		// If map is empty, default to their primary flat_no
		if ( empty( $my_flat_ids ) && ! empty( $resident['flat_no'] ) ) {
			$my_flat_ids[] = $resident['flat_no'];
		}

		$all_flats = $this->db->get( 'flats' );
		$my_flats = array();
		foreach ( $my_flat_ids as $fid ) {
			$found_flat = null;
			foreach ( $all_flats as $f ) {
				if ( $f['id'] === $fid ) {
					$found_flat = $f;
					break;
				}
			}
			$my_flats[] = array(
				'id'          => $fid,
				'flat_number' => $found_flat ? ( ! empty( $found_flat['flat_number'] ) ? $found_flat['flat_number'] : $found_flat['id'] ) : $fid,
				'block'       => $found_flat ? ( $found_flat['block'] ?? '' ) : ''
			);
		}

		// 2. Determine Active Flat Context
		$active_flat_info = $this->get_my_flat_info();
		$active_flat_no = $active_flat_info ? $active_flat_info['flat_no'] : $resident['flat_no'];
		$active_block = $active_flat_info ? $active_flat_info['block'] : ($resident['block'] ?? '');

		$my_flat = null;
		foreach($all_flats as $f) {
            if((string)$f['id'] === (string)$active_flat_no) {
                $my_flat = $f;
				break;
            }
        }

        $my_vehicles = $this->get_my_vehicles( $active_flat_no, $active_block );

		$ledger_mgr = new SHUBX51_Ledger_Manager();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
        $summary_month = isset($_GET['summary_month']) ? sanitize_text_field( wp_unslash( $_GET['summary_month'] ) ) : gmdate('Y-m');
       
		// Fetch relevant payment requests for this resident (Pending + Approved)
		$all_requests = $this->db->get('requests');
		$pending_payment_requests = array_filter($all_requests, function($r) use ($active_flat_no, $active_block) {
			$status = $r['status'] ?? 'pending';
			$is_relevant = in_array( $status, array( 'pending', 'pending_secretary', 'pending_treasurer', 'approved' ) );
			return (($r['module'] ?? '') === 'accounts' || ($r['entity_type'] ?? '') === 'accounts') 
				&& $is_relevant
				&& isset($r['payload'])
				&& (string)($r['flat_no'] ?? '') === (string)$active_flat_no
                && (string)($r['block'] ?? '') === (string)$active_block;
		});

		$my_requests = array_filter($all_requests, function($r) use ($active_flat_no, $active_block, $user_id) {
			return ((string)$r['flat_no'] === (string)$active_flat_no && (string)($r['block'] ?? '') === (string)$active_block) || ((int)$r['created_by'] === (int)$user_id);
		});
		
		// Sort Newest First
		usort($my_requests, function($a, $b) {
			return strtotime($b['created_at']) - strtotime($a['created_at']);
		});

		$data = array(
			'resident'   => $resident,
			'flat'       => $my_flat,
			'vehicles'   => $my_vehicles,
			'family'     => $this->get_my_family( $active_flat_no, $active_block ),
			'daily_help' => $this->get_my_daily_help( $active_flat_no, $active_block ),
			'notices'    => $this->get_my_notices( $resident['type'] ),
			'my_docs'    => $this->get_my_documents( $active_flat_no, $active_block ),
			'facilities' => $this->db->get( 'facilities' ),
            'assets'     => $this->db->get( 'assets' ),
			'my_bookings'=> $this->get_my_bookings( $active_flat_no, $active_block ),
			'expenses'   => $this->db->get( 'expenses' ),
			'invoices'   => $this->get_my_invoices( $active_flat_no, $active_block ),
			'detailed_expenses' => $this->get_expenses_filtered(),
            'current_balance'   => $ledger_mgr->get_current_balance(),
            'monthly_summary'   => $ledger_mgr->get_monthly_summary($summary_month),
            'summary_month'     => $summary_month,
			'directory'         => $this->get_directory_data(),
			'pending_payment_requests' => $pending_payment_requests,
            'my_requests'       => $my_requests,
            'notifications'     => $this->get_my_notifications( $user_id ),
			'my_flats'          => $my_flats,
			'active_flat_no'    => $active_flat_no,
		);

		// Prepare Chart Data
		$expense_chart_data = [];
		if ( ! empty( $data['detailed_expenses'] ) ) {
			foreach ( $data['detailed_expenses'] as $ex ) {
				$month_key = gmdate('M Y', strtotime($ex['date']));
				if ( ! isset( $expense_chart_data[$month_key] ) ) $expense_chart_data[$month_key] = 0;
				$expense_chart_data[$month_key] += (float) $ex['amount'];
			}
		}
        $expense_chart_data = array_slice($expense_chart_data, -12, null, true);

       $payment_history = [];
       if ( ! empty( $data['invoices'] ) ) {
           foreach ( $data['invoices'] as $inv ) {
               $has_explicit_payments = false;
               if ( ! empty( $inv['payments'] ) && is_array( $inv['payments'] ) ) {
                   $has_explicit_payments = true;
                   foreach ( $inv['payments'] as $p ) {
                       $p_date = ! empty( $p['date'] ) ? $p['date'] : substr( $inv['created_at'], 0, 10 );
                       if ( ! isset( $payment_history[ $p_date ] ) ) $payment_history[ $p_date ] = 0;
                       $payment_history[ $p_date ] += (float) $p['amount'];
                   }
               }
               
               if ( ! $has_explicit_payments && ( strtolower( $inv['status'] ?? '' ) === 'paid' ) ) {
                   $p_date = gmdate( 'Y-m-d', strtotime( $inv['month'] . '-01' ) );
                   if ( ! isset( $payment_history[ $p_date ] ) ) $payment_history[ $p_date ] = 0;
                   $payment_history[ $p_date ] += (float) $inv['amount'];
               }
           }
       }
       
       ksort( $payment_history );
       
       $chart_payments = [];
       foreach ( $payment_history as $date_str => $amount ) {
           $chart_payments[] = array(
               'x'     => strtotime( $date_str ) * 1000,
               'y'     => $amount,
               'label' => gmdate( 'd M Y', strtotime( $date_str ) )
           );
       }
       $payment_history = array_slice( $chart_payments, -20 );

		$data['expenseChartData'] = $expense_chart_data;
		$data['paymentHistory']   = $payment_history;

		return $data;
	}

	private function get_my_notices( $type ) {
		$all = $this->db->get( 'notices' );
		$my_notices = array();
		
		foreach ( $all as $n ) {
			// Filter by Audience
			$audience = $n['audience'] ?? 'All';

			if ( $audience === 'All' ) {
				$my_notices[] = $n;
			} elseif ( $audience === 'Owners' && strtolower( $type ) === 'owner' ) {
				$my_notices[] = $n;
			} elseif ( $audience === 'Tenants' && strtolower( $type ) === 'tenant' ) {
				$my_notices[] = $n;
			}
		}
		
		// Sort by Date DESC
		usort( $my_notices, function($a, $b) {
			return strtotime($b['created_at']) - strtotime($a['created_at']);
		});
		
		return $my_notices;
	}

	private function get_my_documents( $flat_no, $block = '' ) {
		// 1. Fetch Managed Docs (from DB Metadata) - OPTIMIZED
		$where = array( 'flat_no' => $flat_no );
        if ( $block ) $where['block'] = $block;

		$all_meta_docs = $this->db->get( 'documents', array( 'where' => $where ) );
		
		$my_docs = array();
		$known_paths = array(); // Track ALL DB entries for this flat

		foreach ( $all_meta_docs as $doc ) {
			$path = $doc['file_path'] ?? ($doc['url'] ?? '');
			$known_paths[] = $path; // Mark as known
			
			// User Rule: "pending one should not be listed for resident" (Unless rejected)
			// REVISED: Show pending but with restricted view in template.
			if ( ($doc['status']??'') !== 'deleted' ) {
				// Normalize for template
				$doc['name'] = $doc['title'] ?? ($doc['name'] ?? 'Unnamed');
				$doc['url']  = $doc['file_path'] ?? ($doc['url'] ?? '#');
				$my_docs[] = $doc;
			}
		}

		// 2. Fetch Physical Files (Admin Uploads / Legacy)
        // Note: For physical files, we might still use A-101 format for folders if not migrated
		$folder_id = $this->drive->ensure_flat_folder( ($block ? $block . '-' : '') . $flat_no );
		if ( ! is_wp_error( $folder_id ) ) {
			$raw_files = $this->drive->list_files( $folder_id );
			
			if ( ! empty( $raw_files ) ) {
				foreach ( $raw_files as $f ) {
					$f_url = $f['url'] ?? '';
					$is_known = false;
					foreach($known_paths as $kpath) {
						if(urldecode($kpath) === urldecode($f_url)) {
							$is_known = true; break;
						}
					}

					if ( ! $is_known ) {
						$my_docs[] = array(
							'id'      => $f['id'],
							'name'    => $f['name'],
							'url'     => $f['url'],
							'status'  => 'approved',
                            'block'   => $block,
							'flat_no' => $flat_no
						);
					}
				}
			}
		}

		usort( $my_docs, function($a, $b) {
			return strnatcmp( $a['name'], $b['name'] );
		});

		return $my_docs;
	}

	private function get_my_bookings( $flat_no, $block = '' ) {
		$args = array(
			'where' => array( 'flat_no' => $flat_no )
		);
        if ( $block ) $args['where']['block'] = $block;

		$mine = $this->db->get( 'bookings', $args );
		
		// Sort Newest First
		return array_reverse( $mine );
	}

	private function get_my_family( $flat_no, $block = '' ) {
		// 1. Approved Residents (only)
	$all = $this->db->get( 'residents' );
	$mine = array();
	foreach ( $all as $r ) {
		if ( (string)$r['flat_no'] === (string)$flat_no && (string)($r['block'] ?? '') === (string)$block && isset($r['type']) && $r['type'] === 'family' ) {
            $status = $r['status'] ?? 'approved';
            if ($status === 'approved' || $status === 'deletion_pending' || $status === 'pending') {
			    $mine[] = $r;
            }
		}
	}

		// 2. Pending Requests
		$requests = $this->db->get('requests');
        $approved_ids = array_column( $mine, 'id' );

		foreach($requests as $req) {
            $e_type = $req['entity_type'] ?? '';
            $is_family_req = ($e_type === 'family');
            
            $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'] ?? '{}', true);
            if ( ! $payload ) continue;
            $req_flat_no = !empty($req['flat_no']) ? $req['flat_no'] : ($payload['flat_no'] ?? '');
            $req_block = !empty($req['block']) ? $req['block'] : ($payload['block'] ?? '');

			if($is_family_req && (string)$req_flat_no === (string)$flat_no && (string)$req_block === (string)$block && in_array($req['status'], ['pending', 'rejected'])) {
				if($payload && $req['request_type'] !== 'delete') {
                    $payload_id = $req['entity_id'] ?: ($payload['id'] ?? '');

					$item = array_merge($payload, [
                        'id' => $payload_id,
						'status' => $req['status'],
						'request_id' => $req['id']
					]);

                    // Deduplicate
                    $found = false;
                    foreach($mine as $k => $existing) {
                        if(isset($existing['id']) && $existing['id'] == $payload_id) {
                             $item['original_data'] = $existing; // Keep original for comparison
                             $item['request_type'] = $req['request_type'];
                             $item['is_pending'] = true;
                             $mine[$k] = $item; 
                             $found = true;
                             break;
                        }
                    }

                    // If not found (Add new), append
                    if(!$found) {
                        $mine[] = $item;
                    }
				}
			}

			if ($is_family_req && $req_flat_no === $flat_no && $req['status'] === 'pending' && $req['request_type'] === 'delete') {
                // Handle Pending Deletion
                foreach($mine as $k => $existing) {
                    if(isset($existing['id']) && $existing['id'] == $req['entity_id']) {
                            $mine[$k]['status'] = 'deletion_pending';
                            break;
                    }
                }
            }
		}
		
		return $mine;
	}

	private function get_my_daily_help( $flat_no, $block = '' ) {
		// 1. Approved help
		$all_help = $this->db->get( 'daily_help' );
		$mine = [];
		foreach ( $all_help as $h ) {
			if ( ! empty($h['flat_no']) ) {
                $h_flats = is_string($h['flat_no']) ? explode( ',', $h['flat_no'] ) : (is_array($h['flat_no']) ? $h['flat_no'] : []);
                foreach($h_flats as $f) {
                    $f = trim($f);
                    // Match Block-Flat specifically
                    if ( (string)$f === (string)$flat_no || (string)$f === (string)($block . '-' . $flat_no) ) {
                        $mine[] = $h; break;
                    }
                }
            }
		}

        // 2. Pending Requests
        $requests = $this->db->get('requests');
        foreach($requests as $req) {
            if(($req['entity_type'] === 'daily_help') && (string)$req['flat_no'] === (string)$flat_no && (string)($req['block'] ?? '') === (string)$block && in_array($req['status'], ['pending', 'rejected'])) {
                
                if($req['request_type'] === 'delete' && $req['status'] === 'pending') {
                     // Handle Pending Deletion
                     foreach($mine as $k => $existing) {
                        $existing_id = $existing['id'] ?? '';
                        if($existing_id && $existing_id == $req['entity_id']) {
                             $mine[$k]['status'] = 'deletion_pending';
                             break;
                        }
                    }
                } else if ($req['request_type'] !== 'delete') {
                    // Add/Edit
                        $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'] ?? '{}', true);
                    if ( ! $payload ) continue;
                        if($payload) {
                        $payload_id = $req['entity_id'] ?: ($payload['id'] ?? '');

                        $item = array_merge($payload, [
                            'id' => $payload_id,
                            'status' => $req['status'],
                            'request_id' => $req['id']
                        ]);

                        // Deduplicate / Replace for Edit
                        $found = false;
                        foreach($mine as $k => $existing) {
                            if(isset($existing['id']) && $existing['id'] == $req['entity_id']) {
                                $item['original_data'] = $existing;
                                $item['request_type'] = $req['request_type'];
                                $item['is_pending'] = true;
                                $mine[$k] = $item;
                                $found = true;
                                break;
                            }
                        }

                        if(!$found) {
                            $mine[] = $item;
                        }
                    }
                }
            }
        }

		return $mine;
	}

	// --- Form Handlers ---

	public function handle_add_family() {

		if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_add_family_nonce' );
        } else {
            // Robust Nonce Check
            $nonce_ok = false;
            if ( ! empty( $_POST['_wpnonce_add_family'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_add_family'] ) ), 'shubx51_add_family_nonce' ) ) {
                $nonce_ok = true;
            } elseif ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_add_family_nonce' ) ) {
                $nonce_ok = true;
            } elseif ( check_admin_referer( 'shubx51_add_family_nonce' ) ) {
                $nonce_ok = true;
            }

		    if ( ! $nonce_ok ) {
                error_log("SHUBX51 Debug: Nonce verification failed for add_family"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
                wp_die( 'Security check failed' );
            }
        }
		
		$flat_no = $this->get_my_flat_number();
        error_log("SHUBX51 Debug: Found Flat No: " . $flat_no); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

		if(!$flat_no) {
            if(wp_doing_ajax()) wp_send_json_error('Flat not found');
            wp_die('Flat not found for user.');
        }

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$relation = isset( $_POST['relation'] ) ? sanitize_text_field( wp_unslash( $_POST['relation'] ) ) : '';
		$dob = isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '';
		$blood_group = isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        // Handle Photo Upload
        $photo_url = '';
        if ( isset( $_FILES['profile_photo']['size'] ) && $_FILES['profile_photo']['size'] > 0 ) {
            $url = $this->media->upload_profile_photo( 
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_profile_photo.
                $_FILES['profile_photo'], 
                $flat_no, 
                $name 
            );
            if ( ! is_wp_error( $url ) ) {
                $photo_url = $url;
            }
        }

        $payload = array(
			'name'    => $name,
			'flat_no' => $flat_no,
			'type'    => 'family',
			'relation'=> $relation,
			'dob'     => $dob,
			'blood_group' => $blood_group,
			'profile_photo' => $photo_url,
			'phone'   => $phone, 
            'created_at' => current_time('mysql')
        );

        // Double-Write: Insert Pending Record + Create Request
        $new_id = uniqid('res_');
        $payload['id'] = $new_id;
        $payload['status'] = 'pending';
        
        // Insert into DB as pending
        $this->db->insert('residents', $payload);

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        $request_id = $rm->create_request( 'residents', 'add', $payload, $new_id, 'family', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            error_log("SHUBX51 Error: Request creation failed: " . $request_id->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
            if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
            wp_die( esc_html( $request_id->get_error_message() ) );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_family', 'manual') === 'auto') {
            $rm->approve_request($request_id);
            if(wp_doing_ajax()) wp_send_json_success(['message' => 'Family member added successfully']);
            wp_safe_redirect( wp_get_referer() . '?family_added=1' );
        } else {
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Request submitted for approval']);
		    wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_add_daily_help() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed immediately below.
        if ( wp_doing_ajax() ) {
            // JS sends _wpnonce from the form
            $nonce = isset($_POST['_wpnonce']) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'shubx51_add_help_nonce' ) ) {
                wp_send_json_error( 'Security check failed' );
            }
        } else {
            if ( ! empty( $_POST['_wpnonce_add_help'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_add_help'] ) ), 'shubx51_add_help_nonce' ) ) {
                // Success
            } else if ( ! check_admin_referer( 'shubx51_add_help_nonce' ) ) {
                 wp_die( 'Security check failed' );
            }
        }
        
        $flat_no = $this->get_my_flat_number(); 
        if(!$flat_no) {
             if(wp_doing_ajax()) wp_send_json_error('Flat not found');
            wp_die('Flat not found.');
        }

        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
        $phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $sex = isset( $_POST['sex'] ) ? sanitize_text_field( wp_unslash( $_POST['sex'] ) ) : '';
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $doc_url = '';
        if ( ! empty( $_FILES['doc_file']['name'] ) ) {
            // Handle file upload if present (usually requires FormData)
             require_once( ABSPATH . 'wp-admin/includes/file.php' );
             $uploaded = wp_handle_upload( $_FILES['doc_file'], array( 'test_form' => false ) );
             if ( ! isset( $uploaded['error'] ) ) {
                 $doc_url = $uploaded['url'];
             }
        }

        $formatted_flat = is_array($flat_no) ? $flat_no : [$flat_no];

        $payload = array(
            'name'  => $name,
            'role'  => $role,
            'phone' => $phone,
            'sex'   => $sex,
            'category' => $category,
            'flats_served' => json_encode($formatted_flat),
            'flat_no' => $flat_no, // Primary flat
            'status' => 'pending',
            'doc_url' => $doc_url,
            'created_at' => current_time('mysql')
        );

        // Double-Write: Insert Pending Record + Create Request
        $new_id = uniqid('help_');
        $payload['id'] = $new_id;
        
        // Insert into DB as pending (payload already has status=pending)
        $this->db->insert('daily_help', $payload);

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        $request_id = $rm->create_request( 'daily_help', 'add', $payload, $new_id, 'daily_help', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
            wp_die( esc_html( $request_id->get_error_message() ) );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_help', 'manual') === 'auto') {
            $rm->approve_request($request_id);
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Help added successfully']);
            wp_safe_redirect( wp_get_referer() . '?help_added=1' );
        } else {
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Request submitted for approval']);
            wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
	}

	public function handle_add_vehicle_frontend() {
		// Use request nonce or standard nonce
		if ( wp_doing_ajax() ) {
			$nonce = isset($_POST['_wpnonce']) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'shubx51_add_vehicle_frontend_nonce' ) ) {
				wp_send_json_error( 'Security check failed' );
			}
		} else {
			if ( ! empty( $_POST['_wpnonce_add_vehicle_frontend'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_add_vehicle_frontend'] ) ), 'shubx51_add_vehicle_frontend_nonce' ) ) {
				// OK
			} else if ( ! check_admin_referer( 'shubx51_add_vehicle_frontend_nonce' ) ) {
				wp_die( 'Security check failed' );
			}
		}
		
		$flat_no = $this->get_my_flat_number();
		if ( empty( $flat_no ) ) {
			wp_send_json_error( 'Resident not found' );
		}
		
		$number = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$brand = isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : '';
		$model = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

        $payload = array(
			'number'   => $number,
			'plate_no' => $number, // Ensure plate_no is set
			'flat_no'  => $flat_no,
			'type'     => $type,
			'brand'    => $brand,
			'model'    => $model,
			'status'   => 'pending',
			'created_at' => current_time('mysql')
		);

        // Double-Write: Insert Pending Record + Create Request
        $new_id = uniqid('veh_');
        $payload['id'] = $new_id;

        // Insert into DB as pending (payload already has status=pending)
        $this->db->insert('vehicles', $payload);

        $rm = new SHUBX51_Request_Manager();
        $request_id = $rm->create_request( 'vehicles', 'add', $payload, $new_id, 'vehicle', $flat_no );

        if ( is_wp_error( $request_id ) ) {
            wp_send_json_error( $request_id->get_error_message() );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_vehicle', 'manual') === 'auto') {
            $rm->approve_request($request_id);
             wp_send_json_success( ['message' => 'Vehicle added successfully', 'auto_approved' => true] );
        } else {
             wp_send_json_success( ['message' => 'Vehicle request submitted for approval', 'auto_approved' => false] );
        }
		exit;
	}

	public function handle_edit_family() {
        
        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_edit_family_nonce' );
        } else {
             // Verify nonce: accept either `_wpnonce` or `_wpnonce_edit_family`
            $nonce_ok = false;
            if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_edit_family_nonce' ) ) {
                $nonce_ok = true;
            } elseif ( ! empty( $_POST['_wpnonce_edit_family'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_edit_family'] ) ), 'shubx51_edit_family_nonce' ) ) {
                $nonce_ok = true;
            } elseif ( check_admin_referer( 'shubx51_edit_family_nonce' ) ) {
                $nonce_ok = true;
            }

            if ( ! $nonce_ok ) {
                error_log("SHUBX51 Debug: Nonce verification failed for edit_family"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
                wp_die( 'Security check failed' );
            }
        }
		
        try {
            $flat_no = $this->get_my_flat_number();
            $id = isset( $_POST['member_id'] ) ? sanitize_text_field( wp_unslash( $_POST['member_id'] ) ) : ( isset( $_POST['resident_id'] ) ? sanitize_text_field( wp_unslash( $_POST['resident_id'] ) ) : '' );
            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            
            // Handle Photo Upload
           $photo_url = '';
           if ( isset( $_FILES['profile_photo']['size'] ) && $_FILES['profile_photo']['size'] > 0 ) {
            $url = $this->media->upload_profile_photo( 
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated within upload_profile_photo.
                $_FILES['profile_photo'], 
                $flat_no, 
                $name 
            );
            if ( ! is_wp_error( $url ) ) {
                $photo_url = $url;
            }
        }

            // Payload with proposed changes
            $update_payload = array(
                'name'       => $name,
                'relation' => isset( $_POST['relation'] ) ? sanitize_text_field( wp_unslash( $_POST['relation'] ) ) : '',
                'dob' => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '',
                'blood_group' => isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '',
                'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
                'flat_no'    => $flat_no
            );

            if ( $photo_url ) {
                $update_payload['profile_photo'] = $photo_url;
            }

            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            
            // Only update status to pending in main table, don't overwrite data yet!
            $this->db->update('residents', ['status' => 'pending'], ['id' => $id]);

            $request_id = $rm->create_request( 'residents', 'edit', $update_payload, $id, 'family', $flat_no );

            if ( is_wp_error( $request_id ) ) {
                if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
                wp_die( 'Request Creation Failed: ' . esc_html( $request_id->get_error_message() ) );
            }

            // Check for Auto-Approval
            if (get_option('shubx51_approval_family', 'manual') === 'auto') {
                $res = $rm->approve_request($request_id);
                if ( is_wp_error( $res ) ) {
                     if(wp_doing_ajax()) wp_send_json_error( $res->get_error_message() );
                    wp_die( 'Auto-Approval Failed: ' . esc_html( $res->get_error_message() ) );
                }
                if(wp_doing_ajax()) wp_send_json_success(['message' => 'Family member updated successfully']);
                wp_safe_redirect( wp_get_referer() . '?family_updated=1' );
            } else {
                 if(wp_doing_ajax()) wp_send_json_success(['message' => 'Update request submitted']);
                wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
            }
            exit;
        } catch ( Exception $e ) {
             if(wp_doing_ajax()) wp_send_json_error( $e->getMessage() );
            wp_die( 'Fatal Error in handle_edit_family: ' . esc_html( $e->getMessage() ) );
        }
    }

	public function handle_edit_daily_help() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed immediately below.
        if ( wp_doing_ajax() ) {
            $nonce = isset($_POST['_wpnonce']) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'shubx51_edit_help_nonce' ) ) {
                wp_send_json_error( 'Security check failed' );
            }
        } else {
            if ( ! empty( $_POST['_wpnonce_edit_help'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce_edit_help'] ) ), 'shubx51_edit_help_nonce' ) ) {
                // Success
            } else if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_edit_help_nonce' ) ) {
                // Success
            } else if ( ! check_admin_referer( 'shubx51_edit_help_nonce' ) ) {
                 wp_die( 'Security check failed' );
            }
        }
		
		$flat_no = $this->get_my_flat_number();
		$id = isset( $_POST['help_id'] ) ? sanitize_text_field( wp_unslash( $_POST['help_id'] ) ) : '';
		
        $category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
        $doc_url = isset( $_POST['document_url'] ) ? sanitize_text_field( wp_unslash( $_POST['document_url'] ) ) : ''; // retain existing if no new upload
        
        if ( ! empty( $_FILES['doc_file']['name'] ) ) {
             require_once( ABSPATH . 'wp-admin/includes/file.php' );
             $uploaded = wp_handle_upload( $_FILES['doc_file'], array( 'test_form' => false ) );
             if ( ! isset( $uploaded['error'] ) ) {
                 $doc_url = $uploaded['url'];
             } else {
                  if(wp_doing_ajax()) wp_send_json_error( $uploaded['error'] );
             }
        }
		
        $update_payload = array(
            'name' => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'role' => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '',
            'category'       => $category,
            'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
            'sex' => isset( $_POST['sex'] ) ? sanitize_text_field( wp_unslash( $_POST['sex'] ) ) : '',
            'visiting_hours' => isset( $_POST['visiting_hours'] ) ? sanitize_text_field( wp_unslash( $_POST['visiting_hours'] ) ) : '',
            'document_url'   => $doc_url,
            'flat_no'        => $flat_no // Important: include flat_no for administrative context
        );

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        
        // Only update status to pending in main table
        $this->db->update('daily_help', ['status' => 'pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'daily_help', 'edit', $update_payload, $id, 'daily_help', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
            wp_die( esc_html( $request_id->get_error_message() ) );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_help', 'manual') === 'auto') {
            $rm->approve_request($request_id);
            if(wp_doing_ajax()) wp_send_json_success(['message' => 'Help updated successfully']);
            wp_safe_redirect( wp_get_referer() . '?help_updated=1' );
        } else {
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Update request submitted']);
		    wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_edit_vehicle() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed immediately below.
        if ( wp_doing_ajax() ) {
            $nonce = isset($_POST['_wpnonce']) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'shubx51_edit_vehicle_action' ) ) {
                wp_send_json_error( 'Security check failed' );
            }
        } else {
            // Use explicit nonce verification to avoid conflict with other forms
            $nonce_ok = false;
            if ( ! empty( $_POST['shubx51_edit_vehicle_token'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['shubx51_edit_vehicle_token'] ) ), 'shubx51_edit_vehicle_action' ) ) {
                $nonce_ok = true;
            }
            // Also accept canonical _wpnonce if JS did not set the custom token
            if ( ! $nonce_ok && ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_edit_vehicle_action' ) ) {
                $nonce_ok = true;
            }
            if ( ! $nonce_ok ) {
                 // Try standard check as fallback
                 if(check_admin_referer('shubx51_edit_vehicle_action')) {
                     $nonce_ok = true;
                 }
            }
            
            if(!$nonce_ok) wp_die( 'Security check failed' );
        }
		
		$flat_no = $this->get_my_flat_number();
		$id = isset( $_POST['vehicle_id'] ) ? sanitize_text_field( wp_unslash( $_POST['vehicle_id'] ) ) : '';
        if(empty($id) && !empty($_POST['id'])) $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		
        $update_payload = array(
            'id'     => $id, // Critical: pass ID to ensure update
            'number' => isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '',
            'plate_no' => isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '', // Ensure plate_no is synced
            'type' => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '',
            'brand' => isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : '',
            'model' => isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '',
            'flat_no' => $flat_no
        );

        $rm = new SHUBX51_Request_Manager();
        
        // Only update status to pending in main table
        $this->db->update('vehicles', ['status' => 'pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'vehicles', 'edit', $update_payload, $id, 'vehicle', $flat_no );

        if ( is_wp_error( $request_id ) ) {
            wp_send_json_error( $request_id->get_error_message() );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_vehicle', 'manual') === 'auto') {
            $rm->approve_request($request_id);
            wp_send_json_success( ['message' => 'Vehicle updated successfully'] );
        } else {
            wp_send_json_success( ['message' => 'Update request submitted'] );
        }
		exit;
	}

    public function handle_delete_family() {
        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_delete_family_nonce' );
        } else {
             if ( ! check_admin_referer( 'shubx51_delete_family_nonce' ) ) wp_die( 'Security check failed' );
        }

		$flat_no = $this->get_my_flat_number();
		$id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        
        // Double-Write: Mark as deletion_pending
        $this->db->update('residents', ['status' => 'deletion_pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'residents', 'delete', ['id' => $id], $id, 'family', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
            wp_die( esc_html( $request_id->get_error_message() ) );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_family', 'manual') === 'auto') {
            $rm->approve_request($request_id);
            if(wp_doing_ajax()) wp_send_json_success(['message' => 'Family member removed successfully']);
            wp_safe_redirect( wp_get_referer() . '?deleted=1' );
        } else {
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Deletion request submitted']);
		    wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

    public function handle_delete_daily_help() {
        if ( wp_doing_ajax() ) {
            check_ajax_referer( 'shubx51_delete_help_nonce' );
        } else {
            if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_delete_help_nonce' ) ) wp_die( 'Security check failed' );
        }

        $flat_no = $this->get_my_flat_number();
        $id = isset( $_REQUEST['id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['id'] ) ) : '';

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        
        // Double-Write: Mark as deletion_pending
        $this->db->update('daily_help', ['status' => 'deletion_pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'daily_help', 'delete', ['id' => $id], $id, 'daily_help', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            if(wp_doing_ajax()) wp_send_json_error( $request_id->get_error_message() );
            wp_die( esc_html( $request_id->get_error_message() ) );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_help', 'manual') === 'auto') {
            $rm->approve_request($request_id);
            if(wp_doing_ajax()) wp_send_json_success(['message' => 'Help deleted successfully']);
            wp_safe_redirect( wp_get_referer() . '?help_deleted=1' );
        } else {
             if(wp_doing_ajax()) wp_send_json_success(['message' => 'Deletion request submitted']);
            wp_safe_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
    }

    public function handle_delete_family_frontend() {
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_delete_family_nonce' ) ) {
             wp_send_json_error( 'Security check failed' ); 
        }

        $flat_no = $this->get_my_flat_number();
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        
        // Double-Write: Mark as deletion_pending
        // Note: For family, we update the main resident record status
        $this->db->update('residents', ['status' => 'deletion_pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'residents', 'delete', ['id' => $id], $id, 'family', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            wp_send_json_error( $request_id->get_error_message() );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_family', 'manual') === 'auto') {
            $rm->approve_request($request_id);
             wp_send_json_success( ['message' => 'Family member removed successfully'] );
        } else {
             wp_send_json_success( ['message' => 'Deletion request submitted'] );
        }
        exit;
    }

    public function handle_delete_vehicle_frontend() {
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'shubx51_delete_vehicle_frontend_nonce' ) ) {
             // For AJAX calls, sometimes it might come as distinct param or in header? Standard POST param is _wpnonce.
             wp_send_json_error( 'Security check failed' ); 
        }

        $flat_no = $this->get_my_flat_number();
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

        $rm = new SHUBX51_Request_Manager();
        
        // Double-Write: Mark as deletion_pending
        $this->db->update('vehicles', ['status' => 'deletion_pending'], ['id' => $id]);

        $request_id = $rm->create_request( 'vehicles', 'delete', ['id' => $id], $id, 'vehicle', $flat_no );

         if ( is_wp_error( $request_id ) ) {
            wp_send_json_error( $request_id->get_error_message() );
        }

        // Check for Auto-Approval
        if (get_option('shubx51_approval_vehicle', 'manual') === 'auto') {
            $rm->approve_request($request_id);
             wp_send_json_success( ['message' => 'Vehicle deleted successfully'] );
        } else {
             wp_send_json_success( ['message' => 'Delete request submitted'] );
        }
        exit;
    }

	public function handle_request_delete_doc() {
		if ( ! check_admin_referer( 'shubx51_delete_doc_nonce' ) ) wp_die( 'Security check failed' );
		
		$doc_id = isset( $_GET['doc_id'] ) ? sanitize_text_field( wp_unslash( $_GET['doc_id'] ) ) : '';
		$flat_no = $this->get_my_flat_number();

		$all = $this->db->get('documents');
		$target_index = -1;
		foreach($all as $i => $d) {
			if(isset($d['id']) && $d['id'] === $doc_id && $d['flat_no'] === $flat_no) {
				$target_index = $i;
				break;
			}
		}

		if($target_index > -1 && isset($all[$target_index]['id'])) {
			// Use Case: If status is 'pending' (approval), and they delete it, it should probably just be deleted?
			
			if($all[$target_index]['status'] === 'pending') {
				// Physical Delete logic
				$this->db->delete( 'documents', array( 'id' => $all[$target_index]['id'] ) );
			} else {
				// Mark as deletion pending
				$update_data = array( 'status' => 'deletion_pending' );
				$this->db->update( 'documents', $update_data, array( 'id' => $all[$target_index]['id'] ) );
			}
		}

		wp_safe_redirect( wp_get_referer() . '?doc_deletion_requested=1' );
		exit;
	}

	private function get_my_flat_info() {
		$user_id = get_current_user_id();
		$residents = $this->db->get('residents');
		$target_resident = null;
		foreach($residents as $r) {
			if(isset($r['wp_user_id']) && (int)$r['wp_user_id'] === $user_id) {
                $target_resident = $r;
                break;
            }
		}

		if ( ! $target_resident ) {
			return null;
		}

		$flat_ids = $this->db->get_resident_flats( $target_resident['id'] );

		// Determine which flat is active
		$active_flat_id = isset( $_SESSION['shubx51_active_flat_id'] ) ? $_SESSION['shubx51_active_flat_id'] : '';

		// Validate active flat belongs to the user
		if ( empty( $active_flat_id ) || ! in_array( $active_flat_id, $flat_ids, true ) ) {
			$active_flat_id = ! empty( $flat_ids ) ? $flat_ids[0] : $target_resident['flat_no'];
		}

		// Save validation back to session
		if ( ! headers_sent() ) {
			if ( ! session_id() ) {
				session_start();
			}
			$_SESSION['shubx51_active_flat_id'] = $active_flat_id;
		}

		// Fetch flat details
		$flats = $this->db->get( 'flats', array( 'where' => array( 'id' => $active_flat_id ) ) );
		$flat = ! empty( $flats ) ? $flats[0] : null;

		return array(
			'block'   => $flat ? ( $flat['block'] ?? '' ) : ( $target_resident['block'] ?? '' ),
			'flat_no' => $active_flat_id
		);
	}

	public function get_my_flat_number() {
		$info = $this->get_my_flat_info();
		return $info ? $info['flat_no'] : null;
	}

	public function get_my_invoices( $flat_no, $block = '' ) {
        $where = array( 'flat_no' => $flat_no );
        if ( $block ) $where['block'] = $block;

		return $this->db->get( 'invoices', array(
			'where'          => $where,
			'orderby'        => 'month',
			'order'          => 'DESC',
			'load_relations' => true
		));
	}

	private function get_expenses_filtered() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
		$year = isset($_GET['ex_year']) ? sanitize_text_field( wp_unslash( $_GET['ex_year'] ) ) : gmdate('Y');
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
		$month = isset($_GET['ex_month']) ? sanitize_text_field( wp_unslash( $_GET['ex_month'] ) ) : '';
		
		// If filtering by custom range, we might need all years or specific logic.
		// For now, simpler optimization: fetch selected year only.
		$expenses = $this->db->get('expenses');
		
		// Debug: Log expenses
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'SHUBX51 Frontend Expenses: Total=' . count($expenses) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		}
		
		if ( empty( $expenses ) ) return [];

		$filtered = [];
		foreach ( $expenses as $e ) {
			// Skip archived expenses - but include pending and approved
			if (isset($e['status']) && $e['status'] === 'archived') {
				continue;
			}
			
			$e_time = strtotime($e['date']);
			$match = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
			if ( isset($_GET['ex_year']) && gmdate('Y', $e_time) !== $year ) {
				$match = false;
			}

			// Month Filter
			if ( $month !== '' && gmdate('m', $e_time) !== $month ) {
				$match = false;
			}
			// Custom Date logic could go here if parameters are passed
			
			if ( $match ) $filtered[] = $e;
		}
		
		usort($filtered, function($a, $b) {
			return strtotime($b['date']) - strtotime($a['date']);
		});
		
		return $filtered;
	}

	/**
	 * Helper: Render Bank Info Modal
	 * Called by frontend template footer.
	 */
	public static function render_payment_modal() {
		$bank_name = get_option('shubx51_bank_name', 'HDFC Bank');
		$acct_no   = get_option('shubx51_bank_account', '501000123456');
		$ifsc      = get_option('shubx51_bank_ifsc', 'HDFC0001234');
		$upi       = get_option('shubx51_bank_upi', 'society@bank');
		$qr_url    = get_option('shubx51_bank_qr');
		?>
		<div class="modal fade" id="SHUBX51PaymentModal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Make Payment</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body text-center">
						<p class="text-muted">Please scan the QR Code or use the Bank Details below.</p>
						<div class="border p-3 rounded mb-3 bg-light">
							<?php if($qr_url): ?>
								<img src="<?php echo esc_url($qr_url); ?>" style="width:150px; height:150px; margin:0 auto; display:block; object-contain:contain;">
							<?php else: ?>
								<!-- Default Placeholder if no QR -->
								<div style="width:150px; height:150px; background:#eee; margin:0 auto; display:flex; align-items:center; justify-content:center;">
									<span class="dashicons dashicons-grid-view" style="font-size:48px; width:48px; height:48px;"></span>
								</div>
							<?php endif; ?>
							<small class="d-block mt-2"><strong>UPI ID:</strong> <?php echo esc_html($upi); ?></small>
						</div>
						<ul class="list-group text-start">
							<li class="list-group-item"><strong>Bank:</strong> <?php echo esc_html($bank_name); ?></li>
							<li class="list-group-item"><strong>Acct No:</strong> <?php echo esc_html($acct_no); ?></li>
							<li class="list-group-item"><strong>IFSC:</strong> <?php echo esc_html($ifsc); ?></li>
						</ul>
					</div>
						<hr class="my-4 opacity-10">
						
						<form id="payment-confirmation-form" class="text-start">
							<h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
								<i class="bi bi-shield-check text-success"></i>
								Payment Confirmation
							</h6>
							<input type="hidden" name="invoice_id" id="confirm-invoice-id">
							<div class="row g-3">
								<div class="col-md-6">
									<label class="form-label small fw-bold text-secondary">Amount Paid (₹)</label>
									<input type="number" name="amount" id="confirm-amount" class="form-control shadow-none rounded-3" required>
								</div>
								<div class="col-md-6">
									<label class="form-label small fw-bold text-secondary">Payment Date</label>
									<input type="date" name="date" id="confirm-date" class="form-control shadow-none rounded-3" value="<?php echo esc_attr( gmdate('Y-m-d') ); ?>" required>
								</div>
								<div class="col-md-6">
									<label class="form-label small fw-bold text-secondary">Method</label>
									<select name="method" class="form-select shadow-none rounded-3">
										<option value="UPI">UPI / GPay / PhonePe</option>
										<option value="Bank Transfer">Bank Transfer (NEFT/IMPS)</option>
										<option value="Cash">Cash Deposit</option>
									</select>
								</div>
								<div class="col-md-6">
									<label class="form-label small fw-bold text-secondary">Ref / Txn ID</label>
									<input type="text" name="reference" class="form-control shadow-none rounded-3" placeholder="UTR Number" required>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer border-top-0 bg-light">
						<button type="button" class="btn btn-light text-secondary px-4 fw-medium border-0" data-bs-dismiss="modal">Close</button>
						<button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" id="btn-confirm-payment">Submit Confirmation</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
	/**
	 * Render Public Notices Board (Searchable).
	 */
	public function render_notices( $atts ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
		$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		
		$all = $this->db->get( 'notices' );
		// Filter by Search
		if ( $search ) {
			$all = array_filter( $all, function($n) use ($search) {
				return stripos($n['title'], $search) !== false || stripos($n['content'], $search) !== false;
			});
		}
		
		ob_start();
		?>
		<div class="shubx51-notices-app">
			<form method="get" class="mb-4 d-flex gap-3">
				<input type="text" name="q" class="form-control rounded-3 shadow-none border-light" placeholder="Search Notices..." value="<?php echo esc_attr($search); ?>">
				<button class="btn btn-primary px-4 rounded-3 fw-bold">Search</button>
			</form>
			
			<?php if ( empty( $all ) ) : ?>
				<div class="alert alert-info border-0 rounded-3 p-4 text-center">
                    <p class="m-0 fw-bold">No notices found.</p>
                </div>
			<?php else : ?>
				<div class="d-flex flex-column gap-3">
					<?php foreach ( array_reverse( $all ) as $n ) : ?>
						<div class="bg-white rounded-3 shadow-sm border border-light p-4 position-relative overflow-d-none ps-5">
                            <!-- Accent -->
                            <div class="position-absolute start-0 top-0 bottom-0 bg-warning" style="width: 4px;"></div>
                            
							<div class="d-flex justify-content-between align-items-start mb-2">
								<h5 class="fw-bold text-dark m-0"><?php echo esc_html( $n['title'] ); ?></h5>
								<span class="badge bg-light text-secondary border border-light fw-normal"><?php echo esc_html( gmdate( 'd M Y', strtotime( $n['created_at'] ) ) ); ?></span>
							</div>
							<p class="text-secondary mb-3 pb-3 border-bottom border-light"><?php echo esc_html( $n['content'] ); ?></p>
							
                            <?php if ( ! empty( $n['attachment_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $n['attachment_url'] ); ?>" target="_blank" class="btn btn-light btn-sm text-primary fw-bold rounded-3 d-inline-flex align-items-center gap-2 px-3 py-2">
                                    <i class="bi bi-paperclip"></i>
                                    View Attachment
                                </a>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Residents Directory (Searchable).
	 * Protected: Only for logged-in users.
	 */
	public function render_directory( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-amber-700 font-medium">Please log in to search the directory.</div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
		$search = isset( $_GET['dq'] ) ? sanitize_text_field( wp_unslash( $_GET['dq'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Page filter read-only query parameter.
		$filter_type = isset( $_GET['dtype'] ) ? sanitize_text_field( wp_unslash( $_GET['dtype'] ) ) : ''; // Owner, Tenant
		
		$residents = $this->db->get( 'residents' );
		$vehicles  = $this->db->get( 'vehicles' );
		$results = array();

		// Filter Logic
		foreach ( $residents as $r ) {
			$match = true;

			// 1. Text Search
			if ( $search ) {
				$text_match = false;
				if ( stripos( $r['name'], $search ) !== false || stripos( $r['flat_no'], $search ) !== false ) {
					$text_match = true;
				}
				// Vehicle Search
				if ( ! $text_match ) {
					foreach ( $vehicles as $v ) {
						if ( $v['flat_no'] === $r['flat_no'] && stripos( $v['number'], $search ) !== false ) {
							$text_match = true;
							break;
						}
					}
				}
				if ( ! $text_match ) {
					$match = false;
				}
			}

			// 2. Type Filter
			if ( $match && $filter_type ) {
				if ( strtolower( $r['type'] ) !== strtolower( $filter_type ) ) {
					$match = false;
				}
			}

			if ( $match ) {
				$results[] = $r;
			}
		}

		// Sort by Flat No
		usort( $results, function($a, $b) {
			return strnatcmp( $a['flat_no'], $b['flat_no'] );
		});

		ob_start();
		?>
		<div class="shubx51-directory-app">
			<form method="get" class="mb-4 bg-white p-4 rounded-3 shadow-sm border border-light">
                <div class="row g-3">
    				<div class="col-md-6">
    					<input type="text" name="dq" class="form-control rounded-3 border-light shadow-none" placeholder="Search by Name, Flat, or Vehicle..." value="<?php echo esc_attr($search); ?>">
    				</div>
    				<div class="col-md-3">
    					<select name="dtype" class="form-select rounded-3 border-light shadow-none bg-white">
    						<option value="">All Types</option>
    						<option value="Owner" <?php selected( $filter_type, 'Owner' ); ?>>Owners</option>
    						<option value="Tenant" <?php selected( $filter_type, 'Tenant' ); ?>>Tenants</option>
    					</select>
    				</div>
    				<div class="col-md-3">
    					<button class="btn btn-dark w-100 rounded-3 fw-bold px-4">Search Directory</button>
    				</div>
                </div>
			</form>
			
			<?php if ( empty( $results ) ) : ?>
				<div class="alert alert-info border-0 rounded-3 p-4 text-center">
                    <p class="m-0 fw-bold">No residents found matching your criteria.</p>
                </div>
			<?php else : ?>
				<div class="row g-4">
					<?php foreach ( $results as $r ) : ?>
						<div class="col-md-6">
                            <div class="card border-light shadow-sm rounded-3 h-100 transition-all">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <!-- Floating DP / Avatar -->
                                            <div class="position-relative">
                                                <?php 
                                                    $dp_url = !empty($r['owner_photo']) ? $r['owner_photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($r['owner'] ?? $r['name']) . '&background=random&color=fff';
                                                ?>
                                                <img src="<?php echo esc_url($dp_url); ?>" class="rounded-circle border border-white shadow-sm" style="width: 52px; height: 52px; object-fit: cover;">
                                                <div class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 12px; height: 12px;" title="Occupied"></div>
                                            </div>
                                            <div>
                                                <h5 class="fw-bold text-dark m-0"><?php echo esc_html( $r['owner'] ?? $r['name'] ); ?></h5>
                                                <span class="badge <?php echo strtolower($r['status'] ?? '') === 'occupied' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'; ?> rounded-pill mt-1" style="font-size: 0.65rem;">
                                                    <?php echo esc_html( $r['status'] ?? 'Occupied' ); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="fs-4 fw-bold text-light"><?php echo esc_html( $r['flat_no'] ); ?></div>
                                    </div>
                                    
                                    <div class="d-flex flex-column gap-2 mb-4">
                                        <?php if(!empty($r['phone'])): ?>
                                            <div class="d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-telephone text-primary"></i>
                                                <a href="tel:<?php echo esc_attr($r['phone']); ?>" class="text-decoration-none text-secondary"><?php echo esc_html( $r['phone'] ); ?></a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if(!empty($r['email'])): ?>
                                            <div class="d-flex align-items-center gap-2 text-secondary small">
                                                <i class="bi bi-envelope text-primary"></i>
                                                <?php echo esc_html( $r['email'] ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                        $my_vehicles = array_filter($vehicles, function($v) use ($r){ return $v['flat_no'] === $r['flat_no']; });
                                        if(!empty($my_vehicles)): 
                                    ?>
                                        <div class="pt-3 border-top border-light">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="small fw-bold text-secondary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em;">Details</div>
                                                <!-- Help Staff Tooltip Icons -->
                                                <?php if(!empty($r['help'])): ?>
                                                    <div class="d-flex gap-1">
                                                        <?php foreach($r['help'] as $h): ?>
                                                            <div class="rounded-circle bg-light border border-white d-flex align-items-center justify-content-center" 
                                                                 style="width: 24px; height: 24px; cursor: help;"
                                                                 data-bs-toggle="tooltip" 
                                                                 data-bs-placement="top"
                                                                 title="<?php echo esc_attr($h['name'] . ' (' . $h['role'] . ')'); ?>">
                                                                <i class="bi bi-person-badge text-primary" style="font-size: 0.6rem;"></i>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach($r['vehicles'] as $mv): ?>
                                                    <span class="badge bg-light text-dark border border-light rounded px-2 py-1 fw-medium font-monospace" 
                                                          style="font-size: 0.75rem; cursor: help;"
                                                          data-bs-toggle="tooltip"
                                                          title="<?php echo esc_attr(($mv['brand'] ? $mv['brand'] . ' ' : '') . $mv['type']); ?>">
                                                        <i class="bi bi-car-front-fill me-1 text-primary"></i>
                                                        <?php echo esc_html($mv['number']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_my_vehicles( $flat_no, $block = '' ) {
        // 1. Approved
        $all = $this->db->get( 'vehicles' );
        $mine = array();
        foreach ( $all as $v ) {
            if ( (string)$v['flat_no'] === (string)$flat_no && (string)($v['block'] ?? '') === (string)$block ) {
                $status = isset($v['status']) ? $v['status'] : 'approved';
                // Include approved and deletion_pending
                if($status === 'approved' || $status === 'deletion_pending' || $status === 'pending') {
                    $mine[] = array_merge($v, ['status' => $status]);
                }
            }
        }

        // 2. Pending Requests
        $requests = $this->db->get('requests');
        foreach($requests as $req) {
            if(in_array($req['entity_type'], ['vehicle', 'vehicles']) && (string)$req['flat_no'] === (string)$flat_no && (string)($req['block'] ?? '') === (string)$block && in_array($req['status'], ['pending', 'rejected'])) {
                
                if($req['request_type'] === 'delete' && $req['status'] === 'pending') {
                     // Handle Pending Deletion
                     foreach($mine as $k => $existing) {
                        if(isset($existing['id']) && $existing['id'] == $req['entity_id'] || (isset($existing['id']) && $existing['id'] == ($req['id'] ?? ''))) {
                             $mine[$k]['status'] = 'deletion_pending';
                             break;
                        }
                    }
                } else if ($req['request_type'] !== 'delete') {
                    // Add/Edit
                    $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'] ?? '{}', true);
                    if ( ! $payload ) continue;
                    if($payload) {
                        $payload_id = $req['entity_id'] ?: ($payload['id'] ?? '');

                        $item = array_merge($payload, [
                            'id' => $payload_id,
                            'status' => $req['status'],
                            'request_id' => $req['id']
                        ]);

                        // Deduplicate / Replace for Edit
                        $found = false;
                        foreach($mine as $k => $existing) {
                            if(isset($existing['id']) && $existing['id'] == $req['entity_id']) {
                                $item['original_data'] = $existing;
                                $item['request_type'] = $req['request_type'];
                                $item['is_pending'] = true;
                                $mine[$k] = $item;
                                $found = true;
                                break;
                            }
                        }

                        if(!$found) {
                            $mine[] = $item;
                        }
                    }
                }
            }
        }
        return $mine;
    }

	private function get_my_notifications( $user_id ) {
		$all_notifs = $this->db->get( 'inapp_notifications' );
		$my_notifs = [];
		foreach ( $all_notifs as $n ) {
			if ( (int)$n['user_id'] === (int)$user_id ) {
				$my_notifs[] = $n;
			}
		}
		// Sort by Date DESC
		usort($my_notifs, function($a, $b) {
			return strtotime($b['created_at']) - strtotime($a['created_at']);
		});
		return $my_notifs;
	}

    /**
     * Get Directory Data
     */
    private function get_directory_data() {
        $flats = $this->db->get('flats');
        $residents = $this->db->get('residents');
        $vehicles = $this->db->get('vehicles');
        $daily_help = $this->db->get('daily_help', array('load_relations' => true));
        $invoices = $this->db->get('invoices');
        
        // Ensure arrays
        if(!is_array($flats)) $flats = [];
        if(!is_array($residents)) $residents = [];
        if(!is_array($vehicles)) $vehicles = [];
        if(!is_array($daily_help)) $daily_help = [];
        if(!is_array($invoices)) $invoices = [];

        // 1. Analyze Residents (Owners, Member Counts)
        $flat_analyzed = []; // [owner_name, count, is_occupied]
        
        foreach($residents as $r) {
            $fno = $r['flat_no'];
            if(!isset($flat_analyzed[$fno])) {
                $flat_analyzed[$fno] = [
                    'owner' => 'Unoccupied', 
                    'count' => 0, 
                    'occupied' => false,
                    'all_names' => [],
                    'email' => ''
                ];
            }
            
            // Increment Count
            // Initial State for Flat
            $flat_analyzed[$fno]['occupied'] = true;
            $flat_analyzed[$fno]['all_names'][] = $r['name'];
            
            // Identify Main Owner
            $is_owner = stripos($r['type'] ?? '', 'owner') !== false;
            if($is_owner && !isset($flat_analyzed[$fno]['owner_found'])) {
                $flat_analyzed[$fno]['owner'] = $r['name'];
                $flat_analyzed[$fno]['email'] = $r['email'] ?? '';
                $flat_analyzed[$fno]['owner_photo'] = $r['profile_photo'] ?? '';
                $flat_analyzed[$fno]['owner_found'] = true;
            } else {
                // Store as Family Member (or co-owner)
                if (!isset($flat_analyzed[$fno]['family'])) $flat_analyzed[$fno]['family'] = [];
                $flat_analyzed[$fno]['family'][] = [
                    'name'     => $r['name'],
                    'relation' => $r['relation'] ?? ($is_owner ? 'Co-Owner' : 'Member'),
                    'photo'    => $r['profile_photo'] ?? '',
                    'type'     => $r['type'] ?? ''
                ];
                // Increment Family Member Count
                $flat_analyzed[$fno]['count']++;
            }
        }

        // 2. Index Vehicles (Detailed)
        $flat_vehicles = [];
        foreach($vehicles as $v) {
            if(!isset($v['status']) || $v['status'] !== 'archived') {
                $flat_vehicles[$v['flat_no']][] = [
                    'number' => $v['number'],
                    'type'   => $v['type'],
                    'brand'  => isset($v['brand']) ? $v['brand'] : '',
                    'model'  => isset($v['model']) ? $v['model'] : '',
                    'sticker'=> isset($v['sticker']) ? $v['sticker'] : '',
                ];
            }
        }

        // 3. Index Help (Detailed)
        $flat_help = [];
        foreach($daily_help as $h) {
            // Skip archived help
            if(isset($h['status']) && $h['status'] === 'archived') {
                continue;
            }
            
            $raw_served = isset($h['flats_served']) ? $h['flats_served'] : '';
            if (is_array($raw_served)) {
                $served = $raw_served;
            } else {
                $decoded = json_decode($raw_served, true);
                $served = is_array($decoded) ? $decoded : explode(',', $raw_served);
            }
            foreach($served as $f) {
                $f = trim($f);
                if($f) {
                    $help_data = [
                        'name'  => $h['name'],
                        'role'  => $h['role'],
                        'phone' => isset($h['phone']) ? $h['phone'] : '',
                        'visiting_hours' => isset($h['visiting_hours']) ? $h['visiting_hours'] : '',
                        'photo' => isset($h['profile_photo']) ? $h['profile_photo'] : ''
                    ];
                    
                    // Index by the original flat designator
                    $flat_help[$f][] = $help_data;
                    
                    // Also index by numeric-only version (e.g., "A-101" -> "101")
                    if(preg_match('/([A-Z]+)-?(\d+)/i', $f, $matches)) {
                        $number_only = $matches[2];
                        if($number_only !== $f) {
                            $flat_help[$number_only][] = $help_data;
                        }
                    }
                    // Handle "101" -> "A-101" if block is known? 
                    // Better to just ensure directory lookup tries both.
                }
            }
        }

        // 4. Index Invoices (Maintenance & Adhoc)
        $flat_invoices = [];
        $current_month = gmdate('Y-m'); // e.g. 2024-03
        
        foreach($invoices as $inv) {
            $fno = $inv['flat_no'];
            if(!isset($flat_invoices[$fno])) {
                $flat_invoices[$fno] = ['maintenance' => 'Paid', 'adhoc' => 'None'];
            }
            
            // Maintenance Check (Unpaid from last 60 days)
            $inv_type = $inv['type'] ?? 'maintenance';
            if($inv_type === 'maintenance') {
                $inv_time = strtotime($inv['created_at'] ?? $inv['month'] . '-01');
                $sixty_days_ago = time() - (60 * 24 * 60 * 60);
                if($inv_time > $sixty_days_ago) {
                    if($inv['status'] === 'unpaid' || $inv['status'] === 'pending') {
                        $flat_invoices[$fno]['maintenance'] = 'Pending';
                    }
                }
            }
            
            // Adhoc Check (Any unpaid adhoc)
            if($inv_type !== 'maintenance' && ($inv['status'] === 'unpaid' || $inv['status'] === 'pending')) {
                $flat_invoices[$fno]['adhoc'] = 'Pending';
            }
        }

        // 5. Build Final Directory
        $directory = [];
        $processed_keys = []; 
        
        foreach($flats as $f) {
            $fid = $f['id']; 
            $fnum = $f['flat_number'];
            
            $processed_keys[$fid] = true;
            $processed_keys[$fnum] = true;
            
            // Resident Data Match
            $res_data = null;
            if(isset($flat_analyzed[$fid])) $res_data = $flat_analyzed[$fid];
            elseif(isset($flat_analyzed[$fnum])) {
                $res_data = $flat_analyzed[$fnum];
                $processed_keys[$fnum] = true; 
            }
            if(!$res_data) $res_data = ['owner' => 'Unoccupied', 'count' => 0, 'occupied' => false];
            
            // Invoice Data Match
            $inv_data = ['maintenance' => 'Paid', 'adhoc' => 'None'];
            if(isset($flat_invoices[$fid])) $inv_data = $flat_invoices[$fid];
            elseif(isset($flat_invoices[$fnum])) $inv_data = $flat_invoices[$fnum];
            
            
            // Vehicle & Help Match - try multiple keys
            $my_vehicles = isset($flat_vehicles[$fid]) ? $flat_vehicles[$fid] : (isset($flat_vehicles[$fnum]) ? $flat_vehicles[$fnum] : []);
            
            // For help, try multiple format combinations
            $my_help = [];
            
            // Try 1: Direct flat ID match
            if(isset($flat_help[$fid])) {
                $my_help = $flat_help[$fid];
            } 
            // Try 2: Direct flat number match
            elseif(isset($flat_help[$fnum])) {
                $my_help = $flat_help[$fnum];
            } 
            // Try 3: Block-Number format (A-101)
            else {
                $block_flat = $f['block'] . '-' . $fnum;
                if(isset($flat_help[$block_flat])) {
                    $my_help = $flat_help[$block_flat];
                } else {
                    // Try 4: Check if fid or fnum contains block prefix and try without it
                    // In case fid is "A-101" and maid is registered for "101"
                    if(isset($f['block']) && !empty($f['block'])) {
                        $just_number = str_replace($f['block'] . '-', '', $fid);
                        if(isset($flat_help[$just_number])) {
                            $my_help = $flat_help[$just_number];
                        }
                    }
                }
            }

            $directory[] = [
                'flat_no' => $fnum,
                'block'   => $f['block'],
                'floor'   => $f['floor'],
                'status'  => $res_data['occupied'] ? 'Occupied' : 'Vacant',
                'owner'   => $res_data['owner'],
                'owner_photo' => $res_data['owner_photo'] ?? '',
                'email'   => $res_data['email'] ?? '',
                'all_names' => $res_data['all_names'] ?? [],
                'family'    => $res_data['family'] ?? [],
                'members'   => $res_data['count'],
                'parking'   => $f['parking_slot'],
                'parking_status' => $f['parking_status'],
                'vehicles' => $my_vehicles,
                'help'    => $my_help,
                'maintenance_status' => $inv_data['maintenance'],
                'adhoc_status' => $inv_data['adhoc'],
                'finance_status' => ($inv_data['maintenance'] === 'Pending' || $inv_data['adhoc'] === 'Pending') ? 'unpaid' : 'paid'
            ];
        }
        
        // Add Flats found in Residents but missing in Flats Master
        foreach($flat_analyzed as $key => $data) {
            if(!isset($processed_keys[$key])) {
                $inv_data = isset($flat_invoices[$key]) ? $flat_invoices[$key] : ['maintenance' => 'Paid', 'adhoc' => 'None'];
                
                $directory[] = [
                    'flat_no' => $key,
                    'block'   => '?',
                    'floor'   => '?',
                    'status'  => 'Occupied',
                    'owner'   => $data['owner'],
                    'email'   => $data['email'] ?? '',
                    'all_names' => $data['all_names'] ?? [],
                    'family'    => $data['family'] ?? [],
                    'members'   => $data['count'],
                    'parking'   => '', 
                    'vehicles' => isset($flat_vehicles[$key]) ? $flat_vehicles[$key] : [],
                    'help'    => isset($flat_help[$key]) ? $flat_help[$key] : [],
                    'maintenance_status' => $inv_data['maintenance'],
                    'adhoc_status' => $inv_data['adhoc']
                ];
            }
        }
        
        // Sort
        usort($directory, function($a, $b) {
            return strnatcmp($a['flat_no'], $b['flat_no']);
        });

        return $directory;
    }

	/**
	 * Hide Admin Bar for Residents.
	 */
	public function hide_admin_bar_for_residents( $show ) {
		if ( current_user_can( 'subscriber' ) || current_user_can( 'resident' ) ) {
			return false;
		}
		return $show;
	}
	/**
	 * Handle Resident Login AJAX.
	 */
	public function handle_resident_login() {
		check_ajax_referer( 'shubx51_login_nonce', 'login_nonce' );

		$creds = array(
			'user_login' => isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '',
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password input should not be sanitized to prevent character loss.
			'user_password' => isset( $_POST['user_pass'] ) ? wp_unslash( $_POST['user_pass'] ) : '',
			'remember'      => isset( $_POST['remember'] )
		);

		$user = wp_signon( $creds, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => $user->get_error_message() ) );
		}
		
		// Determine redirect URL
		$redirect_url = home_url( '/resident-dashboard/' ); // Default
		if ( user_can( $user, 'manage_options' ) ) {
			$redirect_url = admin_url( 'admin.php?page=shubx51-settings' );
		}

		wp_send_json_success( array( 
			'message' => 'Login successful',
			'redirect_url' => $redirect_url
		) );
	}
}
