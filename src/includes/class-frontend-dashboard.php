<?php
/**
 * Class: Frontend Dashboard
 * Handles the Resident Dashboard Shortcode.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Frontend_Dashboard {

	private $db;
	private $drive;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
		$this->drive = new SGVX51_Drive_Manager();
		
		add_shortcode( 'society_govern_x_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'society_govern_x_notices', array( $this, 'render_notices' ) );
		add_shortcode( 'society_govern_x_directory', array( $this, 'render_directory' ) );
		// Form Handlers
		add_action( 'admin_post_sgvx51_update_profile', array( $this, 'handle_profile_update' ) );
		add_action( 'admin_post_sgvx51_frontend_upload_doc', array( $this, 'handle_doc_upload' ) );
		add_action( 'admin_post_sgvx51_frontend_delete_doc', array( $this, 'handle_doc_delete' ) );
		add_action( 'admin_post_sgvx51_add_family', array( $this, 'handle_add_family' ) );
		add_action( 'admin_post_sgvx51_add_daily_help', array( $this, 'handle_add_daily_help' ) );
		add_action( 'admin_post_sgvx51_add_vehicle_frontend', array( $this, 'handle_add_vehicle_frontend' ) );
		add_action( 'admin_post_sgvx51_update_utilities', array( $this, 'handle_update_utilities' ) );
		add_action( 'admin_post_sgvx51_request_delete_doc', array( $this, 'handle_request_delete_doc' ) );
		
		add_action( 'admin_post_sgvx51_edit_family', array( $this, 'handle_edit_family' ) );
		add_action( 'admin_post_sgvx51_edit_daily_help', array( $this, 'handle_edit_daily_help' ) );
		add_action( 'admin_post_sgvx51_edit_vehicle_frontend', array( $this, 'handle_edit_vehicle' ) );
		// Compatibility: accept older action name so frontend posts with `sgvx51_edit_vehicle` also work
		add_action( 'admin_post_sgvx51_edit_vehicle', array( $this, 'handle_edit_vehicle' ), 1 );

        // Delete Handlers
        add_action( 'admin_post_sgvx51_delete_family', array( $this, 'handle_delete_family' ) );
        add_action( 'admin_post_sgvx51_delete_daily_help', array( $this, 'handle_delete_daily_help' ) );
        add_action( 'admin_post_sgvx51_delete_vehicle_frontend', array( $this, 'handle_delete_vehicle_frontend' ) );
		
		add_filter( 'template_include', array( $this, 'load_page_template' ) );
	}

	/**
	 * Handle Profile Update (Frontend)
	 */
	public function handle_update_profile() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'sgvx51_update_profile_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$user_id = get_current_user_id();
		$phone = sanitize_text_field( $_POST['phone'] );
		$email = sanitize_email( $_POST['email'] );
		$blood_group = sanitize_text_field( $_POST['blood_group'] );
		
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
			
			$where = array( 'id' => $target_resident['id'] );
			$this->db->update( 'residents', $update_data, $where );
		}

		// 3. Redirect Back
		wp_redirect( wp_get_referer() . '?profile_updated=1' );
		exit;
	}

	/**
	 * Handle Document Upload (Frontend)
	 */
	public function handle_doc_upload() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'sgvx51_upload_doc_nonce' ) ) {
			wp_die( 'Security check failed' );
		}

		$user_id = get_current_user_id();
		
		// 1. Find Resident & Flat No
		$residents = $this->db->get( 'residents' );
		$flat_no = '';
		
		foreach ( $residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$flat_no = $r['flat_no'];
				break;
			}
		}

		if ( empty( $flat_no ) ) {
			wp_die( 'Error: No Flat associated with your account.' );
		}

		// 2. Handle Upload
		$doc_name = sanitize_text_field( $_POST['doc_name'] );
		$file = $_FILES['doc_file'];

		if ( $file['error'] !== 0 ) {
			wp_die( 'File upload error.' );
		}
		
		// Upload to Drive/Local
		$folder_id = $this->drive->ensure_flat_folder( $flat_no );
		if ( is_wp_error( $folder_id ) ) {
			wp_die( 'Could not create/find folder: ' . $folder_id->get_error_message() );
		}

		// Fix: Use upload_to_folder which accepts ($folder_id, $file_array)
		// Returns URL on success or WP_Error
		$upload_result = $this->drive->upload_to_folder( $folder_id, $file );
		
		if ( is_wp_error( $upload_result ) ) {
			wp_die( 'Upload Failed: ' . $upload_result->get_error_message() );
		}

		// Update Metadata
		$all_docs = $this->db->get( 'documents' );
		
		// upload_to_folder returns the Web Link (URL) directly
		$file_url = $upload_result;
		
		// For ID, we can generate a unique one or use what Drive might return if we refactor. 
		// Local/Drive Manager currently doesn't return ID easily in unified way, so let's use uniqid for our Metadata DB.
		$doc_id = uniqid('doc_');

		$new_doc = array(
			'id'         => $doc_id,
			'name'       => $doc_name,
			'flat_no'    => $flat_no,
			'file_id'    => $doc_id, // Virtual ID
			'url'        => $file_url,
			'status'     => 'pending',
			'created_by' => $user_id,
			'created_at' => current_time( 'mysql' ),
		);

		$this->db->insert( 'documents', $new_doc );

		wp_redirect( wp_get_referer() . '?doc_uploaded=1&pending=1' );
		exit;
	}

	/**
	 * Render the Dashboard.
	 */
	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="sgvx51-alert alert alert-warning">Please log in to view your Society Dashboard.</div>';
		}

		$user_id = get_current_user_id();
		
		// 1. Fetch Resident Profile.
		// Optimized: In a real DB we'd use SQL. Here we filter the JSON.
		$all_residents = $this->db->get( 'residents' );
		$resident = null;
		foreach ( $all_residents as $r ) {
			if ( isset( $r['wp_user_id'] ) && (int) $r['wp_user_id'] === $user_id ) {
				$resident = $r;
				break;
			}
		}

		if ( ! $resident ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="sgvx51-alert alert alert-info">
							<h4>Welcome Admin!</h4>
							<p>Your account is not linked to a specific Resident Profile (Flat), so you cannot view the Resident Dashboard.</p>
							<a href="' . admin_url( 'admin.php?page=sgvx51-settings' ) . '" class="btn btn-primary">Go to Admin Settings</a>
						</div>';
			}
			return '<div class="sgvx51-alert alert alert-danger">No Resident Profile linked to your account. Please contact the Society Admin.</div>';
		}

		// Success Message
        if ( isset( $_GET['request_submitted'] ) ) {
            echo '<div class="container-fluid mt-3"><div class="alert alert-info alert-dismissible fade show">Request submitted for admin approval. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>';
        }
		if ( isset( $_GET['profile_updated'] ) ) {
			echo '<div class="container-fluid mt-3"><div class="alert alert-success alert-dismissible fade show">Profile updated successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>';
		}

		// 2. Fetch Module Data.
		$all_flats = $this->db->get( 'flats' );
		$my_flat = null;
		foreach($all_flats as $f) { if($f['id'] === $resident['flat_no']) { $my_flat = $f; break; } }

		// $all_vehicles = $this->db->get( 'vehicles' );
		// $my_vehicles = array_filter($all_vehicles, function($v) use ($resident) { return $v['flat_no'] === $resident['flat_no']; });
        $my_vehicles = $this->get_my_vehicles( $resident['flat_no'] );

		$ledger_mgr = new SGVX51_Ledger_Manager();
        $summary_month = isset($_GET['summary_month']) ? sanitize_text_field($_GET['summary_month']) : date('Y-m');
       
	// Fetch pending payment requests for this resident
	$all_requests = $this->db->get('requests');
	$pending_payment_requests = array_filter($all_requests, function($r) use ($resident) {
		return ($r['module'] === 'accounts' || $r['entity_type'] === 'accounts') 
			&& $r['status'] === 'pending'
			&& isset($r['payload']);
	});

		$data = array(
			'resident'   => $resident,
			'flat'       => $my_flat,
			'vehicles'   => $my_vehicles,
			'family'     => $this->get_my_family( $resident['flat_no'] ),
			'daily_help' => $this->get_my_daily_help( $resident['flat_no'] ),
			'notices'    => $this->get_my_notices( $resident['type'] ),
			'my_docs'    => $this->get_my_documents( $resident['flat_no'] ),
			'facilities' => $this->db->get( 'facilities' ),
			'my_bookings'=> $this->get_my_bookings( $resident['flat_no'] ), // Using Flat No as ID for now
			'expenses'   => $this->db->get( 'expenses' ), // Summary only
			'invoices'   => $this->get_my_invoices( $resident['flat_no'] ),
			'detailed_expenses' => $this->get_expenses_filtered(),
            'current_balance'   => $ledger_mgr->get_current_balance(),
            'monthly_summary'   => $ledger_mgr->get_monthly_summary($summary_month),
            'summary_month'     => $summary_month,
			'directory'         => $this->get_directory_data(),
			'pending_payment_requests' => $pending_payment_requests,
		);


		// Prepare Chart Data
		$expense_chart_data = [];
		if ( ! empty( $data['detailed_expenses'] ) ) {
			foreach ( $data['detailed_expenses'] as $ex ) {
				$month_key = date('M Y', strtotime($ex['date']));
				if ( ! isset( $expense_chart_data[$month_key] ) ) $expense_chart_data[$month_key] = 0;
				$expense_chart_data[$month_key] += (float) $ex['amount'];
			}
		}
        // Limit to last 12 months
        $expense_chart_data = array_slice($expense_chart_data, -12, null, true);

        // Resident Payment History
        $payment_history = [];
        if ( ! empty( $data['invoices'] ) ) {
            foreach ( $data['invoices'] as $inv ) {
                $month_key = date('M Y', strtotime($inv['month']));
                if (!isset($payment_history[$month_key])) $payment_history[$month_key] = 0;
                
                if ( ! empty( $inv['payments'] ) ) {
                    $payments = is_string( $inv['payments'] ) ? json_decode( $inv['payments'], true ) : $inv['payments'];
                    if ( is_array( $payments ) ) {
                        foreach ( $payments as $p ) {
                            $payment_history[$month_key] += (float) $p['amount'];
                        }
                    }
                }
            }
        }
        $payment_history = array_slice($payment_history, -12, null, true);

		// 3. Render Template.
         wp_enqueue_script( 'sgvx51-frontend-js', SGVX51_PLUGIN_URL . 'assets/js/frontend.js', array('sgvx51-html2canvas'), SGVX51_VERSION, true );
         wp_enqueue_script( 'sgvx51-dashboard-js', SGVX51_PLUGIN_URL . 'assets/js/sgvx-dashboard.js', array('jquery', 'sgvx51-canvasjs'), current_time('U'), true );
         
         // Localize Data for Dashboard
         wp_localize_script( 'sgvx51-dashboard-js', 'sgvxDashboardData', array(
            'expenseChartData' => $expense_chart_data,
            'paymentHistory'   => $payment_history,
            'nonce'            => wp_create_nonce('sgvx51_facility_nonce')
         ));
        
		ob_start();
		include SGVX51_PLUGIN_DIR . 'templates/dashboard.php';
		return ob_get_clean();
	}

	private function get_my_notices( $type ) {
		$all = $this->db->get( 'notices' );
		$my_notices = array();
		
		foreach ( $all as $n ) {
			// Filter by Audience
			if ( $n['audience'] === 'All' ) {
				$my_notices[] = $n;
			} elseif ( $n['audience'] === 'Owners' && strtolower( $type ) === 'owner' ) {
				$my_notices[] = $n;
			} elseif ( $n['audience'] === 'Tenants' && strtolower( $type ) === 'tenant' ) {
				$my_notices[] = $n;
			}
		}
		
		// Sort by Date DESC
		usort( $my_notices, function($a, $b) {
			return strtotime($b['created_at']) - strtotime($a['created_at']);
		});
		
		return $my_notices;
	}

	private function get_my_documents( $flat_no ) {
		// 1. Fetch Managed Docs (from DB Metadata)
		$all_meta_docs = $this->db->get( 'documents' );
		$my_docs = array();
		$known_urls = array(); // Track ALL DB entries for this flat, even if filtered out

		foreach ( $all_meta_docs as $doc ) {
			if ( isset($doc['flat_no']) && $doc['flat_no'] === $flat_no ) {
				$known_urls[] = $doc['url']; // Mark as known
				
				// User Rule: "pending one should not be listed for resident" (Unless rejected)
				if ( isset($doc['status']) && $doc['status'] === 'pending' ) {
					continue;
				}
				if ( ($doc['status']??'') !== 'deleted' ) {
					$my_docs[] = $doc;
				}
			}
		}

		// 2. Fetch Physical Files (Admin Uploads / Legacy)
		// These are files directly in the folder but not in our metadata DB.
		$folder_id = $this->drive->ensure_flat_folder( $flat_no );
		if ( ! is_wp_error( $folder_id ) ) {
			$raw_files = $this->drive->list_files( $folder_id );
			
			if ( ! empty( $raw_files ) ) {
				foreach ( $raw_files as $f ) {
					// Check if this URL is already in our metadata list (using robust decoding)
					$is_known = false;
					foreach($known_urls as $kurl) {
						if(urldecode($kurl) === urldecode($f['url'])) {
							$is_known = true; break;
						}
					}

					if ( ! $is_known ) {
						// It's a legacy/admin file (truly unknown to DB). Add it.
						$my_docs[] = array(
							'id'      => $f['id'],
							'name'    => $f['name'],
							'url'     => $f['url'],
							'status'  => 'approved', // Implicitly approved
							'flat_no' => $flat_no
						);
					}
				}
			}
		}

		// Sort: By Name
		usort( $my_docs, function($a, $b) {
			return strnatcmp( $a['name'], $b['name'] );
		});

		return $my_docs;
	}

	private function get_my_bookings( $flat_no ) {
		$all = $this->db->get( 'bookings' );
		$mine = array();
		foreach ( $all as $b ) {
			if ( $b['resident_id'] === $flat_no ) {
				$mine[] = $b;
			}
		}
		// Sort Newest First
		return array_reverse( $mine );
	}

	private function get_my_family( $flat_no ) {
		// 1. Approved Residents
		$all = $this->db->get( 'residents' );
		$mine = array();
		foreach ( $all as $r ) {
			if ( $r['flat_no'] === $flat_no && isset($r['type']) && $r['type'] === 'family' ) {
				$mine[] = array_merge($r, ['status' => 'approved']);
			}
		}

		// 2. Pending Requests
		$requests = $this->db->get('requests');
        $approved_ids = array_column( $mine, 'id' );

		foreach($requests as $req) {
			if($req['entity_type'] === 'family' && $req['flat_no'] === $flat_no && in_array($req['status'], ['pending', 'rejected'])) {
				$payload = json_decode($req['payload'], true);
				if($payload) {
					// Add dummy ID if not present in payload, referencing request
                    // Use entity_id (the original ID) if available, else request ID
                    $payload_id = $req['entity_id'] ?: ($payload['id'] ?? '');

					$item = array_merge($payload, [
                        'id' => $payload_id, // Ensure ID is preserved for matching
						'status' => $req['status'],
						'request_id' => $req['id']
					]);

                    // Check if this is an update to an existing member (Edit)
                    // If entity_id matches an existing approved member, REPLACE it
                    $found = false;
                    foreach($mine as $k => $existing) {
                        if(isset($existing['id']) && $existing['id'] == $req['entity_id']) {
                             $mine[$k] = $item; // Replace with pending/rejected version
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

			if ($req['entity_type'] === 'family' && $req['flat_no'] === $flat_no && $req['status'] === 'pending' && $req['request_type'] === 'delete') {
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

	private function get_my_daily_help( $flat_no ) {
		// 1. Approved Staff
		$all = $this->db->get( 'daily_help' );
		$mine = array();
		foreach ( $all as $h ) {
            // Check legacy flat string or JSON array
            $served = [];
            if(isset($h['flats_served'])) {
                 $val = $h['flats_served'];
                 if (is_array($val)) {
                     $served = $val;
                 } else {
                     $decoded = json_decode($val, true);
                     $served = is_array($decoded) ? $decoded : explode(',', $val);
                 }
            }
            // Fallback for resident-added staff who have direct flat_no
            if(isset($h['flat_no']) && $h['flat_no'] === $flat_no) {
                $served[] = $flat_no;
            }

			if ( in_array($flat_no, $served) ) {
				$mine[] = array_merge($h, ['status' => 'approved']);
			}
		}

        // 2. Pending Requests
        // 2. Pending Requests
        $requests = $this->db->get('requests');
        foreach($requests as $req) {
            if($req['entity_type'] === 'daily_help' && $req['flat_no'] === $flat_no && in_array($req['status'], ['pending', 'rejected'])) {
                
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
                    $payload = json_decode($req['payload'], true);
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
		if ( ! check_admin_referer( 'sgvx51_add_family_nonce' ) ) wp_die( 'Security check failed' );
		
		$flat_no = $this->get_my_flat_number();
		if(!$flat_no) wp_die('Flat not found for user.');

		$name = sanitize_text_field( $_POST['name'] );
		$relation = sanitize_text_field( $_POST['relation'] );
		$age = sanitize_text_field( $_POST['age'] );
		$blood_group = sanitize_text_field( $_POST['blood_group'] );
		$phone = sanitize_text_field( $_POST['phone'] );

        $payload = array(
			'name'    => $name,
			'flat_no' => $flat_no,
			'type'    => 'family',
			'relation'=> $relation,
			'age'     => $age,
			'blood_group' => $blood_group,
			'phone'   => $phone, 
            'created_at' => current_time('mysql')
        );

        $payload['status'] = 'pending';
        $payload['id'] = uniqid('res_');
        $this->db->insert('residents', $payload);

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'family',
            'request_type' => 'add',
            'entity_id' => $payload['id'],
            'payload' => json_encode($payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_family', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?family_added=1' );
        } else {
		    wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_add_daily_help() {
        if ( ! check_admin_referer( 'sgvx51_add_help_nonce' ) ) wp_die( 'Security check failed' );
        
        $flat_no = $this->get_my_flat_number(); 
        if(!$flat_no) wp_die('Flat not found.');

        $name = sanitize_text_field( $_POST['name'] );
        $role = sanitize_text_field( $_POST['role'] );
        $phone = sanitize_text_field( $_POST['phone'] );
        $sex = sanitize_text_field( $_POST['sex'] );
        $hours = sanitize_text_field( $_POST['visiting_hours'] );

        // Map flat number to the flats_served JSON array
        $flats_served = json_encode([$flat_no]);

        $payload = array(
            'name'           => $name,
            'role'           => $role,
            'phone'          => $phone,
            'sex'            => $sex,
            'visiting_hours' => $hours,
            'flats_served'   => $flats_served,
            'flat_no'        => $flat_no, 
            'created_at'     => current_time('mysql'),
            'created_by'     => get_current_user_id()
        );

        $payload['status'] = 'pending';
        $payload['id'] = uniqid('help_');
        $this->db->insert('daily_help', $payload);

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'daily_help',
            'request_type' => 'add',
            'entity_id' => $payload['id'], 
            'payload' => json_encode($payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_help', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?help_added=1' );
        } else {
            wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
    }

	public function handle_add_vehicle_frontend() {
		if ( ! check_admin_referer( 'sgvx51_add_vehicle_frontend_nonce' ) ) wp_die( 'Security check failed' );
		
		$flat_no = $this->get_my_flat_number();
		
		$number = sanitize_text_field( $_POST['number'] );
		$type = sanitize_text_field( $_POST['type'] );
		$brand = sanitize_text_field( $_POST['brand'] );
		$model = sanitize_text_field( $_POST['model'] );

        $payload = array(
			'number'  => $number,
			'flat_no' => $flat_no,
			'type'    => $type,
			'brand'   => $brand,
			'model'   => $model,
			'status'  => 'approved', // Will be approved upon request acceptance
			'created_at' => current_time('mysql')
		);

        $payload['status'] = 'pending';
        $payload['id'] = uniqid('veh_');
        $this->db->insert('vehicles', $payload);

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'vehicle',
            'request_type' => 'add',
            'entity_id' => $payload['id'], 
            'payload' => json_encode($payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_vehicle', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?vehicle_added=1' );
        } else {
		    wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_edit_family() {
        // Verify nonce: accept either `_wpnonce` or `_wpnonce_edit_family` for resident frontend submissions.
        $nonce_ok = false;
        if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_edit_family_nonce' ) ) {
            $nonce_ok = true;
        }
        if ( ! $nonce_ok && ! empty( $_POST['_wpnonce_edit_family'] ) && wp_verify_nonce( $_POST['_wpnonce_edit_family'], 'sgvx51_edit_family_nonce' ) ) {
            $nonce_ok = true;
        }
        if ( ! $nonce_ok ) {
            wp_die( 'Security check failed' );
        }
		
		$flat_no = $this->get_my_flat_number();
		$id = sanitize_text_field( $_POST['member_id'] );
		
        // Payload with proposed changes
        $update_payload = array(
            'name'       => sanitize_text_field( $_POST['name'] ),
            'relation'   => sanitize_text_field( $_POST['relation'] ),
            'age'        => sanitize_text_field( $_POST['age'] ),
            'blood_group'=> sanitize_text_field( $_POST['blood_group'] ),
            'phone'      => sanitize_text_field( $_POST['phone'] )
        );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'family',
            'request_type' => 'edit',
            'entity_id' => $id,
            'payload' => json_encode($update_payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_family', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?family_updated=1' );
        } else {
		    wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_edit_daily_help() {
		if ( ! check_admin_referer( 'sgvx51_edit_help_nonce' ) ) wp_die( 'Security check failed' );
		
		$flat_no = $this->get_my_flat_number();
		$id = sanitize_text_field( $_POST['help_id'] );
		
        $update_payload = array(
            'name'          => sanitize_text_field( $_POST['name'] ),
            'role'          => sanitize_text_field( $_POST['role'] ),
            'phone'         => sanitize_text_field( $_POST['phone'] ),
            'sex'           => sanitize_text_field( $_POST['sex'] ),
            'visiting_hours'=> sanitize_text_field( $_POST['visiting_hours'] )
        );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'daily_help',
            'request_type' => 'edit',
            'entity_id' => $id,
            'payload' => json_encode($update_payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_help', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?help_updated=1' );
        } else {
		    wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

	public function handle_edit_vehicle() {
        // Use explicit nonce verification to avoid conflict with other forms
        $nonce_ok = false;
        if ( ! empty( $_POST['sgvx51_edit_vehicle_token'] ) && wp_verify_nonce( $_POST['sgvx51_edit_vehicle_token'], 'sgvx51_edit_vehicle_action' ) ) {
            $nonce_ok = true;
        }
        // Also accept canonical _wpnonce if JS did not set the custom token
        if ( ! $nonce_ok && ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_edit_vehicle_action' ) ) {
            $nonce_ok = true;
        }
        if ( ! $nonce_ok ) {
            wp_die( 'Security check failed (Token Error)' );
        }
		
		$flat_no = $this->get_my_flat_number();
		$id = sanitize_text_field( $_POST['vehicle_id'] );
		
        $update_payload = array(
            'number' => sanitize_text_field( $_POST['number'] ),
            'type'   => sanitize_text_field( $_POST['type'] ),
            'brand'  => sanitize_text_field( $_POST['brand'] ),
            'model'  => sanitize_text_field( $_POST['model'] ),
            'flat_no' => $flat_no
        );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'vehicle',
            'request_type' => 'edit',
            'entity_id' => $id,
            'payload' => json_encode($update_payload),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_vehicle', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?vehicle_updated=1' );
        } else {
		    wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
		exit;
	}

    public function handle_delete_family() {
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_delete_family_nonce' ) ) wp_die( 'Security check failed' );

        $flat_no = $this->get_my_flat_number();
        $id = sanitize_text_field( $_POST['id'] );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'family',
            'request_type' => 'delete',
            'entity_id' => $id,
            'payload' => json_encode([]),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_family', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?family_deleted=1' );
        } else {
            wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
    }

    public function handle_delete_daily_help() {
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_delete_help_nonce' ) ) wp_die( 'Security check failed' );

        $flat_no = $this->get_my_flat_number();
        $id = sanitize_text_field( $_POST['id'] );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'daily_help',
            'request_type' => 'delete',
            'entity_id' => $id,
            'payload' => json_encode([]),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_help', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?help_deleted=1' );
        } else {
            wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
    }

    public function handle_delete_vehicle_frontend() {
        if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sgvx51_delete_vehicle_frontend_nonce' ) ) wp_die( 'Security check failed' );

        $flat_no = $this->get_my_flat_number();
        $id = sanitize_text_field( $_POST['id'] );

        $request_data = array(
            'id' => uniqid('req_'),
            'flat_no' => $flat_no,
            'entity_type' => 'vehicle',
            'request_type' => 'delete',
            'entity_id' => $id,
            'payload' => json_encode([]),
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );
        $this->db->insert('requests', $request_data);

        // Check for Auto-Approval
        if (get_option('sgvx51_approval_vehicle', 'manual') === 'auto') {
            $rm = new SGVX51_Request_Manager();
            $rm->approve_request($request_data['id']);
            wp_redirect( wp_get_referer() . '?vehicle_deleted=1' );
        } else {
            wp_redirect( wp_get_referer() . '?request_submitted=1' );
        }
        exit;
    }

	public function handle_request_delete_doc() {
		if ( ! check_admin_referer( 'sgvx51_delete_doc_nonce' ) ) wp_die( 'Security check failed' );
		
		$doc_id = sanitize_text_field( $_GET['doc_id'] );
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

		wp_redirect( wp_get_referer() . '?doc_deletion_requested=1' );
		exit;
	}

	private function get_my_flat_number() {
		$user_id = get_current_user_id();
		$residents = $this->db->get('residents');
		foreach($residents as $r) {
			if(isset($r['wp_user_id']) && (int)$r['wp_user_id'] === $user_id) return $r['flat_no'];
		}
		return null;
	}

	private function get_my_invoices( $flat_no ) {
		$all = $this->db->get( 'invoices' );
		$mine = array();
		foreach ( $all as $inv ) {
			if ( $inv['flat_no'] === $flat_no ) {
				$mine[] = $inv;
			}
		}
		// Sort Newest First
		usort($mine, function($a, $b) {
			return strtotime($b['created_at']) - strtotime($a['created_at']);
		});
		return $mine;
	}

	private function get_expenses_filtered() {
		$year = isset($_GET['ex_year']) ? sanitize_text_field($_GET['ex_year']) : date('Y');
		$month = isset($_GET['ex_month']) ? sanitize_text_field($_GET['ex_month']) : '';
		
		// If filtering by custom range, we might need all years or specific logic.
		// For now, simpler optimization: fetch selected year only.
		$expenses = $this->db->get('expenses');
		
		// Debug: Log expenses
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'SGVX51 Frontend Expenses: Mode=' . $this->db->get_mode() . ', Table=' . $this->db->get_table_name_debug('expenses') . ', Total=' . count($expenses) );
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

			// Year Filter (only if explicitly set)
			if ( isset($_GET['ex_year']) && date('Y', $e_time) !== $year ) {
				$match = false;
			}

			// Month Filter
			if ( $month !== '' && date('m', $e_time) !== $month ) {
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
		$bank_name = get_option('sgvx51_bank_name', 'HDFC Bank');
		$acct_no   = get_option('sgvx51_bank_account', '501000123456');
		$ifsc      = get_option('sgvx51_bank_ifsc', 'HDFC0001234');
		$upi       = get_option('sgvx51_bank_upi', 'society@bank');
		$qr_url    = get_option('sgvx51_bank_qr');
		?>
		<div class="modal fade" id="sgvx51PaymentModal" tabindex="-1">
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
									<input type="date" name="date" id="confirm-date" class="form-control shadow-none rounded-3" value="<?php echo date('Y-m-d'); ?>" required>
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
		$search = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
		
		$all = $this->db->get( 'notices' );
		// Filter by Search
		if ( $search ) {
			$all = array_filter( $all, function($n) use ($search) {
				return stripos($n['title'], $search) !== false || stripos($n['content'], $search) !== false;
			});
		}
		
		ob_start();
		?>
		<div class="sgvx51-notices-app">
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
								<span class="badge bg-light text-secondary border border-light fw-normal"><?php echo date( 'd M Y', strtotime( $n['created_at'] ) ); ?></span>
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

		$search = isset( $_GET['dq'] ) ? sanitize_text_field( $_GET['dq'] ) : '';
		$filter_type = isset( $_GET['dtype'] ) ? sanitize_text_field( $_GET['dtype'] ) : ''; // Owner, Tenant
		
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
		<div class="sgvx51-directory-app">
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
                                        <div>
                                            <h5 class="fw-bold text-dark m-0"><?php echo esc_html( $r['name'] ); ?></h5>
                                            <span class="badge <?php echo strtolower($r['type']) === 'owner' ? 'bg-success-subtle text-success' : 'bg-primary-subtle text-primary'; ?> rounded-pill mt-2" style="font-size: 0.7rem;">
                                                <?php echo esc_html( ucfirst( $r['type'] ) ); ?>
                                            </span>
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
                                            <div class="small fw-bold text-secondary text-uppercase mb-2" style="font-size: 0.65rem; letter-spacing: 0.05em;">Vehicles</div>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach($my_vehicles as $mv): ?>
                                                    <span class="badge bg-light text-dark border border-light rounded px-2 py-1 fw-medium font-monospace" style="font-size: 0.75rem;">
                                                        <i class="bi bi-car-front-fill me-1"></i>
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

	private function get_my_vehicles( $flat_no ) {
        // 1. Approved
        $all = $this->db->get( 'vehicles' );
        $mine = array();
        foreach ( $all as $v ) {
            if ( $v['flat_no'] === $flat_no ) {
                $status = isset($v['status']) ? $v['status'] : 'approved';
                // Hide archived from resident
                if ( $status === 'archived' ) continue;
                
                $v['status'] = $status;
                $mine[] = $v;
            }
        }

        // 2. Pending Requests
        $requests = $this->db->get('requests');
        foreach($requests as $req) {
            if(in_array($req['entity_type'], ['vehicle', 'vehicles']) && $req['flat_no'] === $flat_no && in_array($req['status'], ['pending', 'rejected'])) {
                
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
                    $payload = json_decode($req['payload'], true);
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

    /**
     * Get Directory Data
     */
    private function get_directory_data() {
        $flats = $this->db->get('flats');
        $residents = $this->db->get('residents');
        $vehicles = $this->db->get('vehicles');
        $daily_help = $this->db->get('daily_help');
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
                    'all_names' => []
                ];
            }
            
            // Increment Count
            $flat_analyzed[$fno]['count']++;
            $flat_analyzed[$fno]['occupied'] = true;
            $flat_analyzed[$fno]['all_names'][] = $r['name'];
            
            // Identify Owner
            if(stripos($r['type'] ?? '', 'owner') !== false) {
                $flat_analyzed[$fno]['owner'] = $r['name'];
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
                        'name' => $h['name'],
                        'role' => $h['role'],
                        'phone' => isset($h['phone']) ? $h['phone'] : '',
                        'visiting_hours' => isset($h['visiting_hours']) ? $h['visiting_hours'] : ''
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
                }
            }
        }

        // 4. Index Invoices (Maintenance & Adhoc)
        $flat_invoices = [];
        $current_month = date('Y-m'); // e.g. 2024-03
        
        foreach($invoices as $inv) {
            $fno = $inv['flat_no'];
            if(!isset($flat_invoices[$fno])) {
                $flat_invoices[$fno] = ['maintenance' => 'Paid', 'adhoc' => 'None'];
            }
            
            // Maintenance Check (with defensive check for type field)
            $inv_type = $inv['type'] ?? 'maintenance';
            if($inv_type === 'maintenance' && $inv['month'] === $current_month) {
                if($inv['status'] === 'unpaid' || $inv['status'] === 'pending') {
                    $flat_invoices[$fno]['maintenance'] = 'Pending';
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
                'all_names' => $res_data['all_names'] ?? [],
                'members' => $res_data['count'],
                'parking' => $f['parking_slot'],
                'parking_status' => $f['parking_status'],
                'vehicles' => $my_vehicles,
                'help'    => $my_help,
                'maintenance_status' => $inv_data['maintenance'],
                'adhoc_status' => $inv_data['adhoc']
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
                    'all_names' => $data['all_names'] ?? [],
                    'members' => $data['count'],
                    'parking' => '', 
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
	 * Load Custom Page Template.
	 */
	public function load_page_template( $template ) {
		if ( is_page( 'resident-dashboard' ) || is_page( 'society-app' ) ) {
			$new_template = SGVX51_PLUGIN_DIR . 'templates/page-society-app.php';
			if ( file_exists( $new_template ) ) {
				return $new_template;
			}
		}
		return $template;
	}
}
