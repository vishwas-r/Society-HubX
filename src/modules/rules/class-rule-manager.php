<?php
/**
 * Module: Rule Manager
 * Handles Society Rules & Regulations, Acknowledgments, and Violations.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema and query helper logic uses dynamic tables and custom queries.


class SHUBX51_Rule_Manager implements SHUBX51_Module {

	private $db;
	private $media;
	private $notifications;

	public function __construct() {
		$this->db = new SHUBX51_DB_Router();
		$this->media = new SHUBX51_Media_Manager();
		
		// Initialize notifications with error handling
		try {
			$plugin_instance = Society_HubX::get_instance();
			if ( $plugin_instance && isset($plugin_instance->notifications) ) {
				$this->notifications = $plugin_instance->notifications;
			} else {
				error_log('SHUBX51_Rule_Manager: Notifications object not found in plugin instance'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
				$this->notifications = null;
			}
		} catch ( Exception $e ) {
			error_log('SHUBX51_Rule_Manager: Error initializing notifications - ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			$this->notifications = null;
		}
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		
		// Admin AJAX Actions
		add_action( 'wp_ajax_shubx51_add_rule', array( $this, 'handle_add_rule' ) );
		add_action( 'wp_ajax_shubx51_edit_rule', array( $this, 'handle_edit_rule' ) );
		add_action( 'wp_ajax_shubx51_delete_rule', array( $this, 'handle_delete_rule' ) );
		add_action( 'wp_ajax_shubx51_publish_rule', array( $this, 'handle_publish_rule' ) );
		add_action( 'wp_ajax_shubx51_get_version_history', array( $this, 'handle_get_version_history' ) );
		add_action( 'wp_ajax_shubx51_restore_version', array( $this, 'handle_restore_version' ) );
		add_action( 'wp_ajax_shubx51_add_violation', array( $this, 'handle_submit_violation' ) );
		add_action( 'wp_ajax_shubx51_resolve_violation', array( $this, 'handle_resolve_violation' ) );
		add_action( 'wp_ajax_shubx51_send_acknowledgment_reminders', array( $this, 'handle_send_reminders' ) );
		add_action( 'wp_ajax_shubx51_manage_category', array( $this, 'handle_manage_category' ) );
		
		// Resident AJAX Actions
		add_action( 'wp_ajax_shubx51_acknowledge_rule', array( $this, 'handle_acknowledge_rule' ) );
		add_action( 'wp_ajax_shubx51_appeal_violation', array( $this, 'handle_appeal_violation' ) );
		add_action( 'wp_ajax_shubx51_get_pending_acknowledgments', array( $this, 'handle_get_pending_acknowledgments' ) );
		add_action( 'wp_ajax_shubx51_search_rules', array( $this, 'handle_search_rules' ) );
		
		// Scheduled Actions
		add_action( 'shubx51_daily_acknowledgment_reminders', array( $this, 'send_daily_reminders' ) );
		if ( function_exists('as_next_scheduled_action') && !as_next_scheduled_action('shubx51_daily_acknowledgment_reminders') ) {
			as_schedule_recurring_action( strtotime('09:00:00'), DAY_IN_SECONDS, 'shubx51_daily_acknowledgment_reminders' );
		}
		
		// Register Module
		add_filter( 'shubx51_get_module_rules', array( $this, 'get_instance' ) );
	}

	public function get_instance() {
		return $this;
	}

	public function get_module_slug() {
		return 'rules';
	}

	/**
	 * Execute a request (add, edit, delete).
	 * Required by SHUBX51_Module interface.
	 * 
	 * @param string $action  The action to perform (add, edit, delete)
	 * @param array  $payload The data associated with the request
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function execute_request( $action, $payload ) {
		// Rules module doesn't use the standard request approval workflow
		// Instead, it has its own direct CRUD operations via AJAX
		// This method is here to satisfy the interface requirement
		
		switch ( $action ) {
			case 'add':
			case 'edit':
				// Rules are managed directly through handle_add_rule/handle_edit_rule
				// Not through the request system
				return new WP_Error('unsupported', 'Rules module uses direct CRUD operations');
				
			case 'delete':
				// Rules are archived, not deleted through request system
				return new WP_Error('unsupported', 'Rules module uses direct archival operations');
				
			default:
				return new WP_Error('invalid_action', 'Invalid action');
		}
	}


	public function register_menu() {
		add_submenu_page(
			'shubx51-settings',
			'Rules & Regulations',
			'Rules',
			'read', // Granular check inside render_page
			'shubx51-rules',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add / Edit Rule
	 */
	public function handle_add_rule() {
		ob_start(); // Capture any stray output so it doesn't corrupt the JSON response
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) && !empty($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : uniqid('rule_');
		$is_edit = isset($_POST['rule_id']) && !empty($_POST['rule_id']);
		
		// Sanitize inputs
		$title = isset($_POST['title']) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title($title);
		$content = isset($_POST['content']) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$category = isset($_POST['category']) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'general';
		$priority = isset($_POST['priority']) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'medium';
		$tags = isset($_POST['tags']) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
		$status = isset($_POST['status']) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'draft';
		$effective_date = isset($_POST['effective_date']) && !empty($_POST['effective_date']) ? sanitize_text_field( wp_unslash( $_POST['effective_date'] ) ) : null;
		$expiry_date = isset($_POST['expiry_date']) && !empty($_POST['expiry_date']) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : null;
		$requires_acknowledgment = isset($_POST['requires_acknowledgment']) ? 1 : 0;
		$acknowledgment_deadline = isset($_POST['acknowledgment_deadline']) && !empty($_POST['acknowledgment_deadline']) ? sanitize_text_field( wp_unslash( $_POST['acknowledgment_deadline'] ) ) : null;
		$fine_amount = isset($_POST['fine_amount']) ? floatval( wp_unslash( $_POST['fine_amount'] ) ) : 0.00;
		
		// Validation
		if ( empty($title) || empty($content) ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => 'Title and content are required' ), 400 );
		}
		
		// Check slug uniqueness
		$existing_rule = $this->get_rule_by_slug($slug);
		if ( $existing_rule && $existing_rule['id'] !== $rule_id ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => 'A rule with this slug already exists. Please use a different title.' ), 400 );
		}
		
		$data = array(
			'title' => $title,
			'slug' => $slug,
			'content' => $content,
			'category' => $category,
			'priority' => $priority,
			'tags' => $tags,
			'status' => $status,
			'effective_date' => $effective_date,
			'expiry_date' => $expiry_date,
			'requires_acknowledgment' => $requires_acknowledgment,
			'acknowledgment_deadline' => $acknowledgment_deadline,
			'fine_amount' => $fine_amount,
			'updated_at' => current_time('mysql'),
			'updated_by' => get_current_user_id()
		);
		
		if ( $is_edit ) {
			// Get existing rule for version tracking
			$existing = $this->db->get( 'rules', array( 'where' => array( 'id' => $rule_id ) ) );
			if ( !empty($existing) ) {
				$old_rule = $existing[0];
				
				// Increment version if any significant field changed
				$should_increment_version = (
					$old_rule['content'] !== $content ||
					$old_rule['title'] !== $title ||
					$old_rule['requires_acknowledgment'] != $requires_acknowledgment ||
					$old_rule['acknowledgment_deadline'] !== $acknowledgment_deadline ||
					$old_rule['category'] !== $category ||
					$old_rule['priority'] !== $priority
				);
				
				if ( $should_increment_version ) {
					// Save version history
					$this->save_version($rule_id, $old_rule, 'Rule updated');
					$data['version'] = intval($old_rule['version']) + 1;
					error_log("SHUBX51_Rule_Manager: Incrementing rule version from {$old_rule['version']} to {$data['version']}"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
				}
			}
			
			$result = $this->db->update('rules', $data, array( 'id' => $rule_id ));
		} else {
			$data['id'] = $rule_id;
			$data['created_by'] = get_current_user_id();
			$data['created_at'] = current_time('mysql');
			$data['version'] = 1;
			
			$result = $this->db->insert('rules', $data);
			
			// Save initial version only if insert succeeded
			if ( $result && ! is_wp_error( $result ) ) {
				$this->save_version($rule_id, $data, 'Initial version');
			}
		}
		
		if ( is_wp_error( $result ) ) {
			ob_end_clean();
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		// If status is 'published', send notifications
		if ( $status === 'published' ) {
			error_log("SHUBX51_Rule_Manager: Rule saved with published status, sending notifications..."); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			try {
				$rules = $this->db->get( 'rules', array( 'where' => array( 'id' => $rule_id ) ) );
				if ( !empty($rules) ) {
					$this->send_rule_published_notifications($rules[0]);
				}
			} catch ( Exception $e ) {
				error_log('SHUBX51_Rule_Manager: Failed to send notif after save - ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			}
		}
		
		ob_end_clean();
		wp_send_json_success( array( 
			'message' => $is_edit ? 'Rule updated successfully' : 'Rule created successfully',
			'rule_id' => $rule_id
		) );
	}

	public function handle_edit_rule() {
		$this->handle_add_rule(); // Uses same logic
	}

	public function handle_delete_rule() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		
		if ( empty($rule_id) ) {
			wp_send_json_error( array( 'message' => 'Rule ID required' ), 400 );
		}
		
		// Archive instead of hard delete
		$result = $this->db->update('rules', array( 'status' => 'archived' ), array( 'id' => $rule_id ));
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		wp_send_json_success( array( 'message' => 'Rule archived successfully' ) );
	}

	public function handle_publish_rule() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		
		if ( empty($rule_id) ) {
			wp_send_json_error( array( 'message' => 'Rule ID required' ), 400 );
		}
		
		$result = $this->db->update('rules', array( 
			'status' => 'published',
			'published_at' => current_time('mysql')
		), array( 'id' => $rule_id ));
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		error_log("SHUBX51_Rule_Manager: Rule published successfully, attempting to send notifications..."); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		
		// Send notifications to residents
		try {
			// Get rule details for notification
			$rules = $this->db->get( 'rules', array( 'where' => array( 'id' => $rule_id ) ) );
			error_log("SHUBX51_Rule_Manager: Fetched rule for notifications: " . (!empty($rules) ? 'found' : 'not found')); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			
			if ( !empty($rules) ) {
				$rule = $rules[0];
				error_log("SHUBX51_Rule_Manager: Calling send_rule_published_notifications for rule: {$rule['title']}"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
				
				// Send notifications to all residents
				$this->send_rule_published_notifications($rule);
			}
		} catch ( Exception $e ) {
			error_log('SHUBX51_Rule_Manager: Failed to send notifications after publishing rule - ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			// Don't fail the publish operation - continue
		}
		
		wp_send_json_success( array( 'message' => 'Rule published successfully' ) );
	}

	/**
	 * Version Control
	 */
	private function save_version($rule_id, $rule_data, $change_summary = '') {
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rule_versions";

		// Silently skip if table does not exist yet (avoids corrupting AJAX JSON response)
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $table_exists ) {
			error_log( 'SHUBX51_Rule_Manager: rule_versions table missing, skipping save_version.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			return false;
		}
		
		$version_data = array(
			'rule_id'        => $rule_id,
			'version'        => isset($rule_data['version']) ? intval($rule_data['version']) : 1,
			'title'          => $rule_data['title'],
			'content'        => $rule_data['content'],
			'change_summary' => $change_summary,
			'changed_by'     => get_current_user_id(),
			'changed_at'     => current_time('mysql')
		);
		
		$wpdb->suppress_errors( true );
		$result = $wpdb->insert( $table, $version_data );
		$wpdb->suppress_errors( false );
		
		return $result;
	}

	public function handle_get_version_history() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		
		if ( empty($rule_id) ) {
			wp_send_json_error( array( 'message' => 'Rule ID required' ), 400 );
		}
		
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rule_versions";
		$versions = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM $table WHERE rule_id = %s ORDER BY version DESC",
			$rule_id
		), ARRAY_A);
		
		wp_send_json_success( array( 'versions' => $versions ) );
	}

	public function handle_restore_version() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$version = isset($_POST['version']) ? intval( wp_unslash( $_POST['version'] ) ) : 0;
		
		if ( empty($rule_id) || $version <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid parameters' ), 400 );
		}
		
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rule_versions";
		$version_data = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE rule_id = %s AND version = %d",
			$rule_id, $version
		), ARRAY_A);
		
		if ( !$version_data ) {
			wp_send_json_error( array( 'message' => 'Version not found' ), 404 );
		}
		
		// Get current rule for new version save
		$current = $this->db->get( 'rules', array( 'where' => array( 'id' => $rule_id ) ) );
		if ( !empty($current) ) {
			$this->save_version($rule_id, $current[0], 'Saved before restore');
		}
		
		// Restore version
		$result = $this->db->update('rules', array(
			'title' => $version_data['title'],
			'content' => $version_data['content'],
			'updated_at' => current_time('mysql'),
			'updated_by' => get_current_user_id()
		), array( 'id' => $rule_id ));
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		wp_send_json_success( array( 'message' => 'Version restored successfully' ) );
	}

	/**
	 * Acknowledgments
	 */
	public function handle_acknowledge_rule() {
		check_ajax_referer( 'shubx51_frontend_nonce', '_wpnonce' );
		
		if ( ! is_user_logged_in() ) {
			error_log('SHUBX51_Rule_Manager: Acknowledgment failed - user not logged in'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'Please login to acknowledge' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$signature_data = isset($_POST['signature']) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		
		error_log("SHUBX51_Rule_Manager: Acknowledge attempt - Rule ID: {$rule_id}, User: " . get_current_user_id()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		
		if ( empty($rule_id) ) {
			error_log('SHUBX51_Rule_Manager: Acknowledgment failed - rule_id is empty'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'Rule ID required' ), 400 );
		}
		
		// Get resident details
		$user_id = get_current_user_id();
		$flat_no = get_user_meta($user_id, 'shubx51_flat_no', true);
		$residents = $this->db->get( 'residents', array( 'where' => array( 'wp_user_id' => $user_id ) ) );
		$resident_id = !empty($residents) ? $residents[0]['id'] : '';
		
		error_log("SHUBX51_Rule_Manager: User ID: {$user_id}, Flat: {$flat_no}, Resident ID: {$resident_id}"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		
		if ( empty($flat_no) ) {
			error_log('SHUBX51_Rule_Manager: Acknowledgment failed - flat_no is empty'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'Flat number not found for your account' ), 400 );
		}
		
		// Get rule version - use direct query to avoid caching
		global $wpdb;
		$rules_table = "{$wpdb->prefix}society_hubx_rules";
		$rule = $wpdb->get_row($wpdb->prepare(
			"SELECT version, title FROM $rules_table WHERE id = %s",
			$rule_id
		), ARRAY_A);
		
		if ( empty($rule) ) {
			error_log('SHUBX51_Rule_Manager: Acknowledgment failed - rule not found'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'Rule not found' ), 404 );
		}
		$rule_version = $rule['version'];
		
		error_log("SHUBX51_Rule_Manager: Rule version: {$rule_version} (Rule: {$rule['title']})"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		
		// Save acknowledgment
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rule_acknowledgments";
		
		// Check if already acknowledged
		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE rule_id = %s AND rule_version = %d AND resident_id = %s",
			$rule_id, $rule_version, $resident_id
		));
		
		error_log("SHUBX51_Rule_Manager: Existing acknowledgments count: {$existing}"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		
		if ( $existing > 0 ) {
			error_log('SHUBX51_Rule_Manager: Acknowledgment failed - already acknowledged this version'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'You have already acknowledged this rule' ), 400 );
		}
		
		$ack_data = array(
			'rule_id' => $rule_id,
			'rule_version' => $rule_version,
			'resident_id' => $resident_id,
			'flat_no' => $flat_no,
			'acknowledged_at' => current_time('mysql'),
			'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
			'signature_data' => $signature_data
		);
		
		try {
			$result = $wpdb->insert($table, $ack_data);
			
			if ( $result === false ) {
				error_log('SHUBX51_Rule_Manager: Failed to insert acknowledgment - ' . $wpdb->last_error); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
				wp_send_json_error( array( 'message' => 'Failed to save acknowledgment: ' . $wpdb->last_error ), 500 );
			}
		} catch ( Exception $e ) {
			error_log('SHUBX51_Rule_Manager: Exception while saving acknowledgment - ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
			wp_send_json_error( array( 'message' => 'Error saving acknowledgment' ), 500 );
		}
		
		wp_send_json_success( array( 'message' => 'Rule acknowledged successfully' ) );
	}

	public function handle_get_pending_acknowledgments() {
		check_ajax_referer( 'shubx51_frontend_nonce', '_wpnonce' );
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please login' ), 403 );
		}
		
		$user_id = get_current_user_id();
		$residents = $this->db->get( 'residents', array( 'where' => array( 'wp_user_id' => $user_id ) ) );
		$resident_id = !empty($residents) ? $residents[0]['id'] : '';
		
		$pending_rules = $this->get_pending_acknowledgments($resident_id);
		
		wp_send_json_success( array( 'pending_rules' => $pending_rules ) );
	}

	private function get_pending_acknowledgments($resident_id) {
		global $wpdb;
		$rules_table = "{$wpdb->prefix}society_hubx_rules";
		$acks_table = "{$wpdb->prefix}society_hubx_rule_acknowledgments";
		
		$sql = "SELECT r.* FROM $rules_table r
				WHERE r.status = 'published' 
				AND r.requires_acknowledgment = 1
				AND NOT EXISTS (
					SELECT 1 FROM $acks_table a 
					WHERE a.rule_id = r.id 
					AND a.rule_version = r.version 
					AND a.resident_id = %s
				)
				ORDER BY r.effective_date DESC";
		
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is dynamic and cannot be prepared.
		return $wpdb->get_results($wpdb->prepare($sql, $resident_id), ARRAY_A);
	}

	/**
	 * Violations
	 */
	public function handle_submit_violation() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$rule_id = isset($_POST['rule_id']) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$flat_no = isset($_POST['flat_no']) ? sanitize_text_field( wp_unslash( $_POST['flat_no'] ) ) : '';
		$violation_date = isset($_POST['violation_date']) ? sanitize_text_field( wp_unslash( $_POST['violation_date'] ) ) : current_time('mysql');
		$description = isset($_POST['description']) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$fine_amount = isset($_POST['fine_amount']) ? floatval( wp_unslash( $_POST['fine_amount'] ) ) : 0;
		
		if ( empty($rule_id) || empty($flat_no) ) {
			wp_send_json_error( array( 'message' => 'Rule and flat are required' ), 400 );
		}
		
		// Handle evidence upload
		$evidence_urls = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated and processed securely.
		if ( !empty($_FILES['evidence']) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated and processed securely.
			$files = $_FILES['evidence'];
			for ( $i = 0; $i < count($files['name']); $i++ ) {
				if ( $files['error'][$i] === UPLOAD_ERR_OK ) {
					$file = array(
						'name' => sanitize_file_name($files['name'][$i]),
						'type' => $files['type'][$i],
						'tmp_name' => $files['tmp_name'][$i],
						'error' => $files['error'][$i],
						'size' => $files['size'][$i]
					);
					$url = $this->media->upload_profile_photo($file, $flat_no, 'violation_evidence', 'rules');
					if ( $url ) {
						$evidence_urls[] = $url;
					}
				}
			}
		}
		
		// Get resident ID
		$residents = $this->db->get( 'residents', array( 'where' => array( 'flat_no' => $flat_no ) ) );
		$resident_id = !empty($residents) ? $residents[0]['id'] : '';
		
		$violation_id = uniqid('violation_');
		$violation_data = array(
			'id' => $violation_id,
			'rule_id' => $rule_id,
			'flat_no' => $flat_no,
			'resident_id' => $resident_id,
			'violation_date' => $violation_date,
			'description' => $description,
			'evidence_urls' => json_encode($evidence_urls),
			'fine_amount' => $fine_amount,
			'status' => 'pending',
			'payment_status' => 'unpaid',
			'reported_by' => get_current_user_id(),
			'created_at' => current_time('mysql')
		);
		
		$result = $this->db->insert('rule_violations', $violation_data);
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		// Send notifications
		$this->send_violation_notifications($violation_id, $flat_no);
		
		wp_send_json_success( array( 'message' => 'Violation reported successfully' ) );
	}

	public function handle_resolve_violation() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$violation_id = isset($_POST['violation_id']) ? sanitize_text_field( wp_unslash( $_POST['violation_id'] ) ) : '';
		$status = isset($_POST['status']) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'resolved';
		$admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ) ) : '';
		
		if ( empty($violation_id) ) {
			wp_send_json_error( array( 'message' => 'Violation ID required' ), 400 );
		}
		
		$result = $this->db->update('rule_violations', array(
			'status' => $status,
			'admin_notes' => $admin_notes,
			'resolved_at' => current_time('mysql'),
			'resolved_by' => get_current_user_id()
		), array( 'id' => $violation_id ));
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		// Send notification
		$violations = $this->db->get( 'rule_violations', array( 'where' => array( 'id' => $violation_id ) ) );
		if ( !empty($violations) ) {
			$this->send_violation_resolved_notifications($violations[0]);
		}
		
		wp_send_json_success( array( 'message' => 'Violation updated successfully' ) );
	}

	public function handle_appeal_violation() {
		check_ajax_referer( 'shubx51_frontend_nonce', '_wpnonce' );
		
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => 'Please login' ), 403 );
		}
		
		$violation_id = isset($_POST['violation_id']) ? sanitize_text_field( wp_unslash( $_POST['violation_id'] ) ) : '';
		$appeal_reason = isset($_POST['appeal_reason']) ? sanitize_textarea_field( wp_unslash( $_POST['appeal_reason'] ) ) : '';
		
		if ( empty($violation_id) || empty($appeal_reason) ) {
			wp_send_json_error( array( 'message' => 'Violation ID and reason required' ), 400 );
		}
		
		$result = $this->db->update('rule_violations', array(
			'appeal_reason' => $appeal_reason,
			'appeal_status' => 'pending'
		), array( 'id' => $violation_id ));
		
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		
		wp_send_json_success( array( 'message' => 'Appeal submitted successfully' ) );
	}

	/**
	 * Category Management
	 */
	public function handle_manage_category() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
        $rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'rules_manage' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$action = isset($_POST['category_action']) ? sanitize_text_field( wp_unslash( $_POST['category_action'] ) ) : '';
		$category_id = isset($_POST['category_id']) ? sanitize_text_field( wp_unslash( $_POST['category_id'] ) ) : '';
		
		if ( $action === 'add' || $action === 'edit' ) {
			$name = isset($_POST['name']) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$slug = isset($_POST['slug']) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : sanitize_title($name);
			$description = isset($_POST['description']) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
			$icon = isset($_POST['icon']) ? sanitize_text_field( wp_unslash( $_POST['icon'] ) ) : 'bi-file-text';
			$color = isset($_POST['color']) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#6c757d';
			$display_order = isset($_POST['display_order']) ? intval( wp_unslash( $_POST['display_order'] ) ) : 0;
			
			if ( empty($name) ) {
				wp_send_json_error( array( 'message' => 'Category name required' ), 400 );
			}
			
			$data = array(
				'name' => $name,
				'slug' => $slug,
				'description' => $description,
				'icon' => $icon,
				'color' => $color,
				'display_order' => $display_order
			);
			
			if ( $action === 'edit' && !empty($category_id) ) {
				$result = $this->db->update('rule_categories', $data, array( 'id' => $category_id ));
			} else {
				$data['id'] = empty($category_id) ? uniqid('cat_') : $category_id;
				$data['created_at'] = current_time('mysql');
				$result = $this->db->insert('rule_categories', $data);
			}
			
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
			}
			
			wp_send_json_success( array( 'message' => $action === 'edit' ? 'Category updated' : 'Category added' ) );
		}
		
	if ( $action === 'delete' && !empty($category_id) ) {
		// Get category details to find its slug
		$category = $this->db->get( 'rule_categories', array( 'where' => array( 'id' => $category_id ) ) );
		
		if ( empty($category) ) {
			wp_send_json_error( array( 'message' => 'Category not found' ), 404 );
		}
		
		$category_slug = $category[0]['slug'];
		
		// DEBUG: Log what we're checking
		
		// Check if any ACTIVE rules are using this category (exclude archived)
		global $wpdb;
		$rules_table = "{$wpdb->prefix}society_hubx_rules";
		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM $rules_table WHERE category = %s AND status != 'archived'",
			$category_slug
		));
		
		if ( $count > 0 ) {
			wp_send_json_error( array( 
				'message' => "Cannot delete category. {$count} " . ($count == 1 ? 'rule is' : 'rules are') . " currently using this category. Please reassign or delete those rules first." 
			), 400 );
		}
		
		// Hard delete the category
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rule_categories";
		$result = $wpdb->delete($table, array('id' => $category_id));
		
		if ( $result === false ) {
			wp_send_json_error( array( 'message' => 'Failed to delete category' ), 500 );
		}
		
		wp_send_json_success( array( 'message' => 'Category deleted successfully' ) );
	}
}

	/**
	* Search Rules
	*/
	public function handle_search_rules() {
		check_ajax_referer( 'shubx51_frontend_nonce', '_wpnonce' );
		
		$query = isset($_POST['query']) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
		$category = isset($_POST['category']) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rules";
		$sql = "SELECT * FROM $table WHERE status = 'published'";
		
		if ( !empty($query) ) {
			$sql .= $wpdb->prepare(" AND (title LIKE %s OR content LIKE %s OR tags LIKE %s)", 
				'%' . $wpdb->esc_like($query) . '%',
				'%' . $wpdb->esc_like($query) . '%',
				'%' . $wpdb->esc_like($query) . '%'
			);
		}
		
		if ( !empty($category) ) {
			$sql .= $wpdb->prepare(" AND category = %s", $category);
		}
		
		$sql .= " ORDER BY effective_date DESC";
		
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL built dynamically with prepare.
		$results = $wpdb->get_results($sql, ARRAY_A);
		
		wp_send_json_success( array( 'results' => $results ) );
	}

	/**
	 * Notifications
	 */
	private function send_rule_published_notifications($rule) {
        // Enterprise Upgrade: Defer to Background Worker
        if ( class_exists('SHUBX51_Background_Worker') ) {
            $worker = new SHUBX51_Background_Worker();
            $worker->schedule_notification_blast( 'rule_published', array(
                'title'    => $rule['title'],
                'deadline' => $rule['acknowledgment_deadline'] ? gmdate('M d, Y', strtotime($rule['acknowledgment_deadline'])) : 'N/A'
            ));
            return;
        }

		// Fallback (Synchronous)
		if ( !$this->notifications ) return;
		$residents = $this->db->get( 'residents', array( 'where' => array( 'status' => 'approved' ) ) );
		if ( empty($residents) ) return;

		foreach ( $residents as $resident ) {
			if ( empty($resident['wp_user_id']) ) continue;
			
			$this->notifications->trigger( 'rule_published', $resident['wp_user_id'], array(
				'resident_name' => $resident['name'],
				'title'         => $rule['title'],
				'deadline'      => $rule['acknowledgment_deadline'] ? gmdate('M d, Y', strtotime($rule['acknowledgment_deadline'])) : 'N/A'
			), true );
		}
	}

	private function send_violation_notifications($violation_id, $flat_no) {
		$violations = $this->db->get( 'rule_violations', array( 'where' => array( 'id' => $violation_id ) ) );
		if ( empty($violations) ) return;
		
		$violation = $violations[0];
		$residents = $this->db->get( 'residents', array( 'where' => array( 'flat_no' => $flat_no, 'status' => 'approved' ) ) );
		
		foreach ( $residents as $resident ) {
			if ( empty($resident['wp_user_id']) ) continue;
			
			$rules = $this->db->get( 'rules', array( 'where' => array( 'id' => $violation['rule_id'] ) ) );
			$rule_title = !empty($rules) ? $rules[0]['title'] : 'Unknown Rule';
			
			$placeholders = array(
				'{resident_name}' => $resident['name'],
				'{flat_no}' => $flat_no,
				'{rule_title}' => $rule_title,
				'{amount}' => number_format($violation['fine_amount'], 2),
				'{date}' => gmdate('M d, Y', strtotime($violation['violation_date']))
			);
			
			$this->notifications->dispatch('violation_reported', $resident['wp_user_id'], $placeholders);
		}
	}

	private function send_violation_resolved_notifications($violation) {
		$residents = $this->db->get( 'residents', array( 'where' => array( 'flat_no' => $violation['flat_no'], 'status' => 'approved' ) ) );
		
		foreach ( $residents as $resident ) {
			if ( empty($resident['wp_user_id']) ) continue;
			
			$placeholders = array(
				'{resident_name}' => $resident['name'],
				'{date}' => gmdate('M d, Y', strtotime($violation['violation_date'])),
				'{status}' => $violation['status'],
				'{notes}' => $violation['admin_notes']
			);
			
			$this->notifications->dispatch('violation_resolved', $resident['wp_user_id'], $placeholders);
		}
	}

	public function send_daily_reminders() {
		// Get residents with pending acknowledgments
		$residents = $this->db->get( 'residents', array( 'where' => array( 'status' => 'approved' ) ) );
		
		foreach ( $residents as $resident ) {
			$pending = $this->get_pending_acknowledgments($resident['id']);
			
			if ( !empty($pending) && !empty($resident['wp_user_id']) ) {
				// Calculate nearest deadline
				$deadlines = array_filter(array_column($pending, 'acknowledgment_deadline'));
				$nearest_deadline = !empty($deadlines) ? min($deadlines) : null;
				
				$placeholders = array(
					'{resident_name}' => $resident['name'],
					'{count}' => count($pending),
					'{deadline}' => $nearest_deadline ? gmdate('M d, Y', strtotime($nearest_deadline)) : 'Soon'
				);
				
				$this->notifications->dispatch('acknowledgment_reminder', $resident['wp_user_id'], $placeholders);
			}
		}
	}

	public function handle_send_reminders() {
		check_ajax_referer( 'shubx51_rule_nonce', '_wpnonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
		
		$this->send_daily_reminders();
		
		wp_send_json_success( array( 'message' => 'Reminders sent successfully' ) );
	}

	/**
	 * Helper Methods
	 */
	private function get_rule_by_slug($slug) {
		// Only check non-archived rules for slug uniqueness
		global $wpdb;
		$table = "{$wpdb->prefix}society_hubx_rules";
		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM $table WHERE slug = %s AND status != 'archived' LIMIT 1",
			$slug
		), ARRAY_A);
	}

	public function render_page() {
        $rbac = new SHUBX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'rules_view' ) ) {
            wp_die( 'You do not have permission to view society rules.' );
        }

		$rules = $this->db->get('rules');
		$categories = $this->db->get('rule_categories'); 
		$violations = $this->db->get('rule_violations');
		
		// Get acknowledgment stats
		global $wpdb;
		$acks_table = "{$wpdb->prefix}society_hubx_rule_acknowledgments";
		$total_acks = $wpdb->get_var("SELECT COUNT(*) FROM $acks_table");
		
		SHUBX51_Admin_App::render_view('rules', [
			'rules' => $rules,
			'categories' => $categories,
			'violations' => $violations,
			'total_acknowledgments' => $total_acks
		]);
	}
}
