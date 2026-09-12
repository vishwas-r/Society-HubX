<?php
/**
 * Class: AJAX Handler
 * Handles AJAX endpoints for module configuration and other dynamic requests.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_AJAX_Handler {

	public function __construct() {
		add_action( 'wp_ajax_nammasociety51_get_module_config', array( $this, 'handle_get_module_config' ) );
		add_action( 'wp_ajax_nammasociety51_get_receipt', array( $this, 'handle_get_receipt' ) );
		add_action( 'wp_ajax_nammasociety51_approve_request', array( $this, 'handle_approve_request' ) );
		add_action( 'wp_ajax_nammasociety51_reject_request', array( $this, 'handle_reject_request' ) );
		add_action( 'wp_ajax_nammasociety51_bulk_process_requests', array( $this, 'handle_bulk_process_requests' ) );
		
		// Notifications
		add_action( 'wp_ajax_nammasociety51_get_channel_config', array( $this, 'handle_get_channel_config' ) );
		add_action( 'wp_ajax_nammasociety51_save_channel_config', array( $this, 'handle_save_channel_config' ) );
		add_action( 'wp_ajax_nammasociety51_toggle_channel', array( $this, 'handle_toggle_channel' ) );
		add_action( 'wp_ajax_nammasociety51_update_event_mapping', array( $this, 'handle_update_event_mapping' ) );
		add_action( 'wp_ajax_nammasociety51_get_template', array( $this, 'handle_get_template' ) );
		add_action( 'wp_ajax_nammasociety51_save_template', array( $this, 'handle_save_template' ) );

		// Registered Devices & Push Telemetry
		add_action( 'wp_ajax_nammasociety51_get_registered_devices', array( $this, 'handle_get_registered_devices' ) );
		add_action( 'wp_ajax_nammasociety51_delete_registered_device', array( $this, 'handle_delete_registered_device' ) );
		add_action( 'wp_ajax_nammasociety51_ping_device', array( $this, 'handle_ping_device' ) );
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

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
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

		// Aggressive buffer cleaning to avoid JSON corruption from notices or whitespaces
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

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
					'nonce'              => wp_create_nonce( 'nammasociety51_resident_nonce' ),
					'deleteNonce'        => wp_create_nonce( 'nammasociety51_delete_resident_nonce' ),
					'deleteHistoryNonce' => wp_create_nonce( 'nammasociety51_delete_history_nonce' ),
					'restoreNonce'       => wp_create_nonce( 'nammasociety51_restore_resident_nonce' ),
					'moveToHistoryNonce' => wp_create_nonce( 'nammasociety51_move_to_history_nonce' ),
				);
				break;

			case 'facilities':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_facility_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_delete_facility_nonce' ),
				);
				break;

			case 'notices':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_notice_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_delete_notice_nonce' ),
				);
				break;

			case 'documents':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_document_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_document_nonce' ),
				);
				break;

			case 'expenses':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_nonce' ),
				);
				break;

			case 'accounts':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_account_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_delete_invoice_nonce' ),
				);
				break;

			case 'vehicles':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_vehicle_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_delete_vehicle_nonce' ),
				);
				break;

			case 'flats':
                $config['nonce'] = wp_create_nonce('nammasociety51_add_flat_nonce');
                $config['deleteNonce'] = wp_create_nonce('nammasociety51_delete_flat_nonce');
                $config['hardDeleteNonce'] = wp_create_nonce('nammasociety51_hard_delete_flat_nonce');
                break;

			case 'staff':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_staff_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_delete_staff_nonce' ),
				);
				break;

			case 'rules':
				$config = array(
					'nonce' => wp_create_nonce( 'nammasociety51_rule_nonce' ),
				);
				break;

			case 'assets':
				$config = array(
					'nonce'        => wp_create_nonce( 'nammasociety51_asset_action' ),
					'deleteNonce'  => wp_create_nonce( 'nammasociety51_delete_asset_nonce' ),
					'restoreNonce' => wp_create_nonce( 'nammasociety51_restore_asset_nonce' ),
				);
				break;

			case 'polls':
				$config = array(
					'nonce'       => wp_create_nonce( 'nammasociety51_poll_action' ),
					'voteNonce'   => wp_create_nonce( 'nammasociety51_vote_nonce' ),
					'deleteNonce' => wp_create_nonce( 'nammasociety51_poll_action' ),
				);
				break;

			case 'requests':
				$config = array(
					'nonce' => wp_create_nonce( 'nammasociety51_request_action' ),
				);
				break;

			case 'notifications':
				$config = array(
					'nonce' => wp_create_nonce( 'nammasociety51_request_action' ),
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
			if ( wp_verify_nonce( $nonce, 'nammasociety51_frontend_nonce' ) || wp_verify_nonce( $nonce, 'nammasociety51_nonce' ) || wp_verify_nonce( $nonce, 'nammasociety51_admin_nonce' ) || wp_verify_nonce( $nonce, 'nammasociety51_receipt_nonce' ) ) {
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
		$user_id = $current_user->ID;
		$is_admin = current_user_can( 'manage_options' );

		// Get invoice data
		$plugin = NAMMASOCIETY51_Plugin::get_instance();
		$invoice = $plugin->db->get_invoice( $invoice_id );

		if ( ! $invoice ) {
			wp_send_json_error( array( 'message' => 'Invoice not found' ), 404 );
		}

		// Permission check: Residents can only view their own invoices, admins can view all
		if ( ! $is_admin ) {
			// Get resident ID for current user
			$resident = $plugin->db->get_resident_by_wp_id( $user_id );
			if ( ! $resident ) {
				wp_send_json_error( array( 'message' => 'Resident not found' ), 403 );
			}
			// Check if resident's flat_no matches invoice's flat_no
			if ( $resident['flat_no'] !== $invoice['flat_no'] ) {
				wp_send_json_error( array( 'message' => 'Not authorized to view this receipt' ), 403 );
			}
		}

		// Generate receipt data using Receipt Manager
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-receipt-manager.php';
		$receipt_mgr = new NAMMASOCIETY51_Receipt_Manager();
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
		check_ajax_referer( 'nammasociety51_request_action' );

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'requests_manage' ) && ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Missing ID' ), 400 );
		}

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$res = $rm->approve_request( $id );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'Request approved successfully' ) );
	}

	/**
	 * AJAX: Reject Request
	 */
	public function handle_reject_request() {
		check_ajax_referer( 'nammasociety51_request_action' );

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'requests_manage' ) && ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$note = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Missing ID' ), 400 );
		}

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		$res = $rm->reject_request( $id, $note );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => 'Request rejected successfully' ) );
	}

	/**
	 * AJAX: Bulk Process Requests
	 */
	public function handle_bulk_process_requests() {
		check_ajax_referer( 'nammasociety51_request_action' );

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'requests_manage' ) && ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}

		$ids = isset( $_POST['ids'] ) ? map_deep( wp_unslash( $_POST['ids'] ), 'sanitize_text_field' ) : array();
		$action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No items selected' ), 400 );
		}

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new NAMMASOCIETY51_Request_Manager();
		
		$count = 0;
		foreach ( $ids as $id ) {
			if ( $action === 'approve' ) {
				$res = $rm->approve_request( $id );
			} else {
				$res = $rm->reject_request( $id, $note );
			}
			if ( ! is_wp_error( $res ) ) {
				$count++;
			}
		}

		wp_send_json_success( array( 'message' => "$count items processed successfully" ) );
	}

	/**
	 * AJAX: Get Channel Config
	 */
	public function handle_get_channel_config() {
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$slug = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
		$channels = $db->get('notification_channels');

		if ( $slug === 'push' ) {
			wp_send_json_success( array(
				'project_id'   => get_option( 'nammasociety51_fcm_project_id', '' ),
				'client_email' => get_option( 'nammasociety51_fcm_client_email', '' ),
				'sender_id'    => get_option( 'nammasociety51_fcm_sender_id', '' ),
				'private_key'  => get_option( 'nammasociety51_fcm_private_key', '' ),
				'has_key'      => ! empty( get_option( 'nammasociety51_fcm_private_key', '' ) ),
				'is_enabled'   => get_option( 'nammasociety51_fcm_enabled', '0' ),
			) );
		}

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
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$slug = isset( $_POST['channel_slug'] ) ? sanitize_key( wp_unslash( $_POST['channel_slug'] ) ) : '';
		$config = isset( $_POST['config'] ) ? map_deep( wp_unslash( $_POST['config'] ), 'sanitize_text_field' ) : [];
		
		$db = NAMMASOCIETY51_Plugin::get_instance()->db;

		if ( $slug === 'push' ) {
			if ( isset( $config['project_id'] ) ) {
				update_option( 'nammasociety51_fcm_project_id', sanitize_text_field( $config['project_id'] ) );
			}
			if ( isset( $config['client_email'] ) ) {
				update_option( 'nammasociety51_fcm_client_email', sanitize_email( $config['client_email'] ) );
			}
			if ( isset( $config['sender_id'] ) ) {
				update_option( 'nammasociety51_fcm_sender_id', sanitize_text_field( $config['sender_id'] ) );
			}
			if ( ! empty( $_POST['config']['private_key'] ) ) {
				update_option( 'nammasociety51_fcm_private_key', trim( wp_unslash( $_POST['config']['private_key'] ) ) );
			}
			$config_to_save = $config;
			unset( $config_to_save['private_key'] );
			$db->update( 'notification_channels', array( 'config' => json_encode( $config_to_save ) ), array( 'channel_slug' => $slug ) );
			delete_transient( 'nammasociety51_fcm_access_token' );
			wp_send_json_success( array( 'message' => esc_html__( 'Push notification settings saved successfully.', 'namma-society' ) ) );
		}

		$updated = $db->update('notification_channels', ['config' => json_encode($config)], ['channel_slug' => $slug]);

		if(is_wp_error($updated)) wp_send_json_error(['message' => $updated->get_error_message()]);
		wp_send_json_success(['message' => 'Settings saved']);
	}

	/**
	 * AJAX: Toggle Channel
	 */
	public function handle_toggle_channel() {
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$slug = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$active = isset( $_POST['active'] ) ? intval( wp_unslash( $_POST['active'] ) ) : 0;

		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
		$db->update('notification_channels', ['is_active' => $active], ['channel_slug' => $slug]);
		if ( $slug === 'push' ) {
			update_option( 'nammasociety51_fcm_enabled', $active ? '1' : '0' );
		}
		wp_send_json_success();
	}

	/**
	 * AJAX: Update Event Mapping
	 */
	public function handle_update_event_mapping() {
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$slug = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
		$channel = isset( $_POST['channel'] ) ? sanitize_key( wp_unslash( $_POST['channel'] ) ) : '';
		$enabled = isset( $_POST['enabled'] ) ? intval( wp_unslash( $_POST['enabled'] ) ) : 0;

		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
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
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
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
		check_ajax_referer( 'nammasociety51_request_action' );
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new NAMMASOCIETY51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'settings_manage' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( ['message' => 'Unauthorized'], 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$content = isset( $_POST['content'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content'] ) ) : '';
		
		$db = NAMMASOCIETY51_Plugin::get_instance()->db;
		
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

	/**
	 * AJAX: Get Registered Devices and Push Telemetry.
	 */
	public function handle_get_registered_devices() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'namma-society' ) ), 403 );
		}

		global $wpdb;
		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-db-schema.php';
		NAMMASOCIETY51_DB_Schema::upgrade_device_tokens_table();

		$table = "{$wpdb->prefix}nammasociety51_device_tokens";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$devices = $wpdb->get_results(
			"SELECT dt.*, u.display_name, u.user_login, u.user_email 
			 FROM {$table} dt 
			 LEFT JOIN {$wpdb->users} u ON dt.user_id = u.ID 
			 ORDER BY dt.updated_at DESC, dt.id DESC LIMIT 100",
			ARRAY_A
		);

		// Aggregate Telemetry KPIs
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$kpi_stats = $wpdb->get_row(
			"SELECT 
				COUNT(*) as total_devices,
				SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_devices,
				COALESCE(SUM(total_pushes_sent), 0) as total_pushes,
				COUNT(DISTINCT NULLIF(society_id, '')) as total_societies
			 FROM {$table}",
			ARRAY_A
		);

		$total_devices   = (int) ( $kpi_stats['total_devices'] ?? 0 );
		$active_devices  = (int) ( $kpi_stats['active_devices'] ?? 0 );
		$total_pushes    = (int) ( $kpi_stats['total_pushes'] ?? 0 );
		$total_societies = (int) ( $kpi_stats['total_societies'] ?? 1 );
		if ( 0 === $total_societies && $total_devices > 0 ) {
			$total_societies = 1;
		}

		$formatted = array();
		if ( ! empty( $devices ) ) {
			foreach ( $devices as $row ) {
				$token = $row['device_token'] ?? '';
				$tok_preview = ! empty( $token ) ? ( substr( $token, 0, 10 ) . '...' . substr( $token, -8 ) ) : 'N/A';

				// Determine resident flat / unit
				$flat_display = trim( ( $row['block'] ? $row['block'] . ' - ' : '' ) . ( $row['flat_no'] ?: '' ) );
				if ( empty( $flat_display ) ) {
					$flat_display = __( 'General / Unassigned', 'namma-society' );
				}

				$last_seen = ! empty( $row['updated_at'] ) && $row['updated_at'] !== '1970-01-01 00:00:01' 
					? human_time_diff( strtotime( $row['updated_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'namma-society' )
					: __( 'Never', 'namma-society' );

				$last_dispatched = ! empty( $row['last_dispatched_at'] )
					? human_time_diff( strtotime( $row['last_dispatched_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'namma-society' )
					: __( 'None yet', 'namma-society' );

				$formatted[] = array(
					'id'                   => (int) $row['id'],
					'user_id'              => (int) $row['user_id'],
					'display_name'         => $row['display_name'] ?: ( $row['user_login'] ?: __( 'Resident Device', 'namma-society' ) ),
					'user_login'           => $row['user_login'] ?: '',
					'user_email'           => $row['user_email'] ?: '',
					'society_id'           => ! empty( $row['society_id'] ) ? $row['society_id'] : '1',
					'society_name'         => ! empty( $row['society_name'] ) ? $row['society_name'] : get_bloginfo( 'name' ),
					'block'                => $row['block'] ?: '',
					'flat_no'              => $row['flat_no'] ?: '',
					'unit_display'         => $flat_display,
					'platform'             => strtolower( $row['platform'] ?: 'android' ),
					'device_name'          => $row['device_name'] ?: 'Mobile Device',
					'device_model'         => $row['device_model'] ?: '',
					'app_version'          => $row['app_version'] ?: '1.0.0',
					'ip_address'           => $row['ip_address'] ?: '',
					'is_active'            => (int) ( $row['is_active'] ?? 1 ),
					'total_pushes_sent'    => (int) ( $row['total_pushes_sent'] ?? 0 ),
					'last_dispatched_at'   => $row['last_dispatched_at'] ?: '',
					'last_dispatched_diff' => $last_dispatched,
					'last_push_status'     => $row['last_push_status'] ?: 'active',
					'last_push_title'      => $row['last_push_title'] ?: '',
					'last_seen'            => $last_seen,
					'token_preview'        => $tok_preview,
					'created_at'           => $row['created_at'] ?: '',
				);
			}
		}

		wp_send_json_success( array(
			'devices' => $formatted,
			'kpi'     => array(
				'total_devices'   => $total_devices,
				'active_devices'  => $active_devices,
				'total_pushes'    => $total_pushes,
				'total_societies' => $total_societies,
			),
		) );
	}

	/**
	 * AJAX: Delete / Unregister a Device Token.
	 */
	public function handle_delete_registered_device() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'namma-society' ) ), 403 );
		}

		$device_id = isset( $_POST['device_id'] ) ? (int) $_POST['device_id'] : 0;
		if ( $device_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'namma-society' ) ), 400 );
		}

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-fcm-service.php';
		$deleted = NAMMASOCIETY51_FCM_Service::delete_device( $device_id );

		if ( false === $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete device token.', 'namma-society' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Device removed successfully.', 'namma-society' ) ) );
	}

	/**
	 * AJAX: Send Diagnostic Ping to a Specific Registered Device.
	 */
	public function handle_ping_device() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'namma-society' ) ), 403 );
		}

		$device_id = isset( $_POST['device_id'] ) ? (int) $_POST['device_id'] : 0;
		if ( $device_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid device ID.', 'namma-society' ) ), 400 );
		}

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';

		require_once NAMMASOCIETY51_PLUGIN_DIR . 'includes/class-fcm-service.php';
		$res = NAMMASOCIETY51_FCM_Service::ping_single_device( $device_id, $title, $body );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Test ping dispatched to device successfully!', 'namma-society' ),
			'result'  => $res,
		) );
	}
}

