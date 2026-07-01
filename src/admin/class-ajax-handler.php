<?php
/**
 * Class: AJAX Handler
 * Handles AJAX endpoints for module configuration and other dynamic requests.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_AJAX_Handler {

	public function __construct() {
		add_action( 'wp_ajax_SNESTX51_get_module_config', array( $this, 'handle_get_module_config' ) );
		add_action( 'wp_ajax_SNESTX51_get_receipt', array( $this, 'handle_get_receipt' ) );
		add_action( 'wp_ajax_SNESTX51_approve_request', array( $this, 'handle_approve_request' ) );
		add_action( 'wp_ajax_SNESTX51_reject_request', array( $this, 'handle_reject_request' ) );
		add_action( 'wp_ajax_SNESTX51_bulk_process_requests', array( $this, 'handle_bulk_process_requests' ) );
		
		// Notifications
		add_action( 'wp_ajax_SNESTX51_get_channel_config', array( $this, 'handle_get_channel_config' ) );
		add_action( 'wp_ajax_SNESTX51_save_channel_config', array( $this, 'handle_save_channel_config' ) );
		add_action( 'wp_ajax_SNESTX51_toggle_channel', array( $this, 'handle_toggle_channel' ) );
		add_action( 'wp_ajax_SNESTX51_update_event_mapping', array( $this, 'handle_update_event_mapping' ) );
		add_action( 'wp_ajax_SNESTX51_get_template', array( $this, 'handle_get_template' ) );
		add_action( 'wp_ajax_SNESTX51_save_template', array( $this, 'handle_save_template' ) );
	}

	/**
	 * Handle AJAX request to get module configuration (nonces, etc.)
	 * serving public nonce data bootstrapping for authenticated user.
	 */
	public function handle_get_module_config() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Serving configuration bootstrapping data for authenticated session.
		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated' ), 401 );
		}

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		// Nonces and module config are allowed for any logged-in user who can access the dashboard.
		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Serving configuration bootstrapping data for authenticated session.
		$module = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';

		if ( empty( $module ) ) {
			wp_send_json_error( array( 'message' => 'Module parameter missing' ), 400 );
		}

		$config = $this->get_module_config( $module );

		if ( $config ) {
			wp_send_json_success( $config );
		} else {
			wp_send_json_error( array( 'message' => 'Module config not found' ), 404 );
		}
	}

	/**
	 * Get configuration for a specific module
	 *
	 * @param string $module The module name (residents, facilities, etc.)
	 * @return array|false Configuration array or false if module not found
	 */
	private function get_module_config( $module ) {
		$config = array();

		switch ( $module ) {
			case 'residents':
				$config = array(
					'nonce'              => wp_create_nonce( 'SNESTX51_resident_nonce' ),
					'deleteNonce'        => wp_create_nonce( 'SNESTX51_delete_resident_nonce' ),
					'deleteHistoryNonce' => wp_create_nonce( 'SNESTX51_delete_history_nonce' ),
					'restoreNonce'       => wp_create_nonce( 'SNESTX51_restore_resident_nonce' ),
					'moveToHistoryNonce' => wp_create_nonce( 'SNESTX51_move_to_history_nonce' ),
				);
				break;

			case 'facilities':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_facility_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_delete_facility_nonce' ),
				);
				break;

			case 'notices':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_notice_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_delete_notice_nonce' ),
				);
				break;

			case 'documents':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_document_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_document_nonce' ),
				);
				break;

			case 'expenses':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_nonce' ),
				);
				break;

			case 'accounts':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_account_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_delete_invoice_nonce' ),
				);
				break;

			case 'vehicles':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_vehicle_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_delete_vehicle_nonce' ),
				);
				break;

			case 'flats':
                $config['nonce'] = wp_create_nonce('SNESTX51_add_flat_nonce');
                $config['deleteNonce'] = wp_create_nonce('SNESTX51_delete_flat_nonce');
                $config['hardDeleteNonce'] = wp_create_nonce('SNESTX51_hard_delete_flat_nonce');
                break;

			case 'staff':
				$config = array(
					'nonce'       => wp_create_nonce( 'SNESTX51_staff_nonce' ),
					'deleteNonce' => wp_create_nonce( 'SNESTX51_delete_staff_nonce' ),
				);
				break;


			default:
				return false;
		}

		return $config;
	}

	/**
	 * Handle AJAX request to get receipt data
	 * This endpoint is available to logged-in users (residents can only view their own receipts)
	 */
	public function handle_get_receipt() {
		// Verify nonce (Check both Frontend and Admin nonces)
		$verified = false;
		if ( isset( $_POST['nonce'] ) ) {
			$nonce = sanitize_key( wp_unslash( $_POST['nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'SNESTX51_frontend_nonce' ) ) {
				$verified = true;
			} elseif ( wp_verify_nonce( $nonce, 'SNESTX51_nonce' ) ) { // Admin Context
				$verified = true;
			}
		}

		if ( ! $verified ) {
			wp_send_json_error( array( 'message' => 'Nonce verification failed' ), 403 );
		}

		// Verify user is logged in
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Not authenticated' ), 401 );
		}

		$invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';

		if ( empty( $invoice_id ) ) {
			wp_send_json_error( array( 'message' => 'Invoice ID missing' ), 400 );
		}

		// Get current user
		$current_user = wp_get_current_user();
		$is_admin = current_user_can( 'manage_options' );

		// Get invoice data
		$plugin = Society_NestX::get_instance();
		$invoice = $plugin->db->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			wp_send_json_error( array( 'message' => 'Invoice not found' ), 404 );
		}

		// Permission check: Residents can only view their own invoices, admins can view all
		if ( ! $is_admin ) {
			// Get resident ID for current user
			$resident = $plugin->db->get_resident_by_wp_id( $current_user->ID );
			if ( ! $resident ) {
				wp_send_json_error( array( 'message' => 'Resident not found' ), 403 );
			}
			// Check if resident's flat_no matches invoice's flat_no
			if ( $resident['flat_no'] !== $invoice['flat_no'] ) {
				wp_send_json_error( array( 'message' => 'Not authorized to view this receipt' ), 403 );
			}
		}

		// Generate receipt data using Receipt Manager
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		$receipt_mgr = new SNESTX51_Receipt_Manager();
		$receipt_data = $receipt_mgr->prepare_receipt_data( $invoice );

		if ( $receipt_data ) {
			wp_send_json_success( $receipt_data );
		} else {
			wp_send_json_error( array( 'message' => 'Error generating receipt' ), 500 );
		}
	}

	/**
	 * AJAX: Approve Request
	 */
	public function handle_approve_request() {
		check_ajax_referer( 'SNESTX51_request_action' );

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if (!$id) wp_send_json_error(['message' => 'Missing ID'], 400);

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SNESTX51_Request_Manager();
		$res = $rm->approve_request( $id );

		if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
		wp_send_json_success( ['message' => 'Request approved successfully'] );
	}

	/**
	 * AJAX: Reject Request
	 */
	public function handle_reject_request() {
		check_ajax_referer( 'SNESTX51_request_action' );

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';
		if (!$id) wp_send_json_error(['message' => 'Missing ID'], 400);

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SNESTX51_Request_Manager();
		$res = $rm->reject_request( $id, $note );

		if ( is_wp_error( $res ) ) wp_send_json_error( ['message' => $res->get_error_message()] );
		wp_send_json_success( ['message' => 'Request rejected successfully'] );
	}

	/**
	 * AJAX: Bulk Process Requests
	 */
	public function handle_bulk_process_requests() {
		check_ajax_referer( 'SNESTX51_request_action' );

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$ids = isset( $_POST['ids'] ) ? map_deep( wp_unslash( $_POST['ids'] ), 'sanitize_text_field' ) : [];
		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( empty($ids) ) wp_send_json_error(['message' => 'No items selected'], 400);

		require_once SNESTX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SNESTX51_Request_Manager();
		
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $action === 'approve' ) {
				$res = $rm->approve_request( $id );
			} else {
				$res = $rm->reject_request( $id, $note );
			}
			if ( ! is_wp_error( $res ) ) $count++;
		}

		wp_send_json_success( ['message' => "$count items processed successfully"] );
	}

	/**
	 * AJAX: Get Channel Config
	 */
	public function handle_get_channel_config() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$slug = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$db = Society_NestX::get_instance()->db;
		$channels = $db->get('notification_channels');

		foreach($channels as $c) {
			if($c['channel_slug'] === $slug) {
				wp_send_json_success(json_decode($c['config'], true));
			}
		}
		wp_send_json_error(['message' => 'Channel not found']);
	}

	/**
	 * AJAX: Save Channel Config
	 */
	public function handle_save_channel_config() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$slug = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
		$config = isset( $_POST['config'] ) ? map_deep( wp_unslash( $_POST['config'] ), 'sanitize_text_field' ) : [];
		
		$db = Society_NestX::get_instance()->db;
		$updated = $db->update('notification_channels', ['config' => json_encode($config)], ['channel_slug' => $slug]);

		if(is_wp_error($updated)) wp_send_json_error(['message' => $updated->get_error_message()]);
		wp_send_json_success(['message' => 'Settings saved']);
	}

	/**
	 * AJAX: Toggle Channel
	 */
	public function handle_toggle_channel() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$slug = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$active = isset( $_POST['active'] ) ? intval( wp_unslash( $_POST['active'] ) ) : 0;

		$db = Society_NestX::get_instance()->db;
		$db->update('notification_channels', ['is_active' => $active], ['channel_slug' => $slug]);
		wp_send_json_success();
	}

	/**
	 * AJAX: Update Event Mapping
	 */
	public function handle_update_event_mapping() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$slug = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? intval( wp_unslash( $_POST['enabled'] ) ) : 0;

		$db = Society_NestX::get_instance()->db;
		$events = $db->get('notification_events');
		
		foreach($events as $e) {
			if($e['event_slug'] === $slug) {
				$channels = array_filter(explode(',', $e['default_channels']));
				if($enabled) {
					if(!in_array($channel, $channels)) $channels[] = $channel;
				} else {
					$channels = array_diff($channels, [$channel]);
				}
				
				$db->update('notification_events', ['default_channels' => implode(',', $channels)], ['event_slug' => $slug]);
				wp_send_json_success();
			}
		}
		wp_send_json_error(['message' => 'Event not found']);
	}

	/**
	 * AJAX: Get Notification Template
	 */
	public function handle_get_template() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$db = Society_NestX::get_instance()->db;
		$templates = $db->get('notification_templates');

		foreach($templates as $t) {
			if($t['id'] == $id) {
				wp_send_json_success($t);
			}
		}
		wp_send_json_error(['message' => 'Template not found']);
	}

	/**
	 * AJAX: Save Notification Template
	 */
	public function handle_save_template() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SNESTX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}
		check_ajax_referer( 'SNESTX51_request_action' );

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		
		$db = Society_NestX::get_instance()->db;
		
		// Get current version to increment
		$current = null;
		$templates = $db->get('notification_templates');
		foreach($templates as $t) if($t['id'] == $id) $current = $t;

		if(!$current) wp_send_json_error(['message' => 'Template not found']);

		$updated = $db->update('notification_templates', [
			'subject' => $subject,
			'content' => $content,
			'version' => (int)$current['version'] + 1
		], ['id' => $id]);

		if(is_wp_error($updated)) wp_send_json_error(['message' => $updated->get_error_message()]);
		wp_send_json_success(['message' => 'Template updated']);
	}
}
