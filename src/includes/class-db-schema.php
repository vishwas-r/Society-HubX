<?php
/**
 * Class: DB Schema
 * Defines the SQL table structures and handles creation/updates via dbDelta.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Core database schema setup routines require direct DDL queries.


class SNESTX51_DB_Schema {

	/**
	 * Create or update all plugin tables.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = array();

		// 1. Flats Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_flats (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_number varchar(20) DEFAULT '' NOT NULL,
			floor varchar(10) DEFAULT '' NOT NULL,
			sq_foot decimal(10,2) DEFAULT 0.00 NOT NULL,
			status varchar(20) DEFAULT 'unoccupied' NOT NULL,
			parking_slot varchar(50) DEFAULT '' NOT NULL,
			parking_status varchar(20) DEFAULT '' NOT NULL,
			type varchar(20) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 2. Residents Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_residents (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			profile_photo text NOT NULL,
			email varchar(100) DEFAULT '' NOT NULL,
			phone varchar(20) DEFAULT '' NOT NULL,
			type varchar(20) DEFAULT 'owner' NOT NULL,
			relation varchar(50) DEFAULT '' NOT NULL,
			dob DATE DEFAULT NULL,
			wp_user_id bigint(20) DEFAULT 0 NOT NULL,
			members_count int(5) DEFAULT 1 NOT NULL,
			blood_group varchar(5) DEFAULT '' NOT NULL,
			roles text NOT NULL, 
			status varchar(20) DEFAULT 'pending' NOT NULL,
			maintenance_balance decimal(15,2) DEFAULT 0.00 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no),
			KEY wp_user_id (wp_user_id),
			KEY status (status)
		) $charset_collate;";

		// 3. Resident History Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_resident_history (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			email varchar(100) DEFAULT '' NOT NULL,
			phone varchar(20) DEFAULT '' NOT NULL,
			type varchar(20) DEFAULT 'owner' NOT NULL,
			relation varchar(50) DEFAULT '' NOT NULL,
			dob DATE DEFAULT NULL,
			wp_user_id bigint(20) DEFAULT 0 NOT NULL,
			members_count int(5) DEFAULT 1 NOT NULL,
			blood_group varchar(5) DEFAULT '' NOT NULL,
			roles text NOT NULL, 
			vacated_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 4. Expenses Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_expenses (
			id varchar(50) NOT NULL,
			title varchar(255) DEFAULT '' NOT NULL,
			amount decimal(15,2) NOT NULL,
			category varchar(50) NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			payment_method varchar(20) DEFAULT 'cash' NOT NULL,
			account_type varchar(20) DEFAULT 'bank' NOT NULL,
			payee varchar(255) DEFAULT '' NOT NULL,
			added_by bigint(20) DEFAULT 0 NOT NULL,
			description text NOT NULL,
			date date NOT NULL,
			receipt_url text NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 5. Assets Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_assets (
			id varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			value decimal(15,2) DEFAULT 0 NOT NULL,
			category varchar(50) DEFAULT '' NOT NULL,
			description text NOT NULL,
			purchase_date date DEFAULT '0000-00-00' NOT NULL,
			warranty_expiry date DEFAULT '0000-00-00' NOT NULL,
			amc_provider varchar(255) DEFAULT '' NOT NULL,
			amc_phone varchar(20) DEFAULT '' NOT NULL,
			status varchar(50) DEFAULT 'Active' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 6. Notices Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notices (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			content longtext NOT NULL,
			urgency varchar(20) DEFAULT 'info' NOT NULL,
			audience varchar(50) DEFAULT 'All' NOT NULL,
			status varchar(20) DEFAULT 'published' NOT NULL,
			is_pinned tinyint(1) DEFAULT 0 NOT NULL,
			expiry_date date DEFAULT NULL,
			attachment_url text DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY urgency (urgency)
		) $charset_collate;";

		// 7. Invoices Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_invoices (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			resident_name varchar(255) DEFAULT '' NOT NULL,
			amount decimal(15,2) NOT NULL,
			total_paid decimal(15,2) DEFAULT 0.00 NOT NULL,
			month varchar(20) NOT NULL,
			type varchar(50) DEFAULT 'maintenance' NOT NULL,
			status varchar(20) DEFAULT 'unpaid' NOT NULL,
			due_date date DEFAULT '0000-00-00' NOT NULL,
			payment_date datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			payment_ref varchar(100) DEFAULT '' NOT NULL,
			payments longtext NOT NULL, 
			description text NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no),
			KEY status (status),
			KEY month (month)
		) $charset_collate;";

		// 8. Receipts Table (for tracking receipt numbers)
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_receipts (
			id varchar(50) NOT NULL,
			invoice_id varchar(50) NOT NULL,
			receipt_number varchar(50) NOT NULL,
			generated_date datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY invoice_id (invoice_id),
			UNIQUE KEY receipt_number (receipt_number)
		) $charset_collate;";

		// 9. Polls Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_polls (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			description text NOT NULL,
			options text NOT NULL,
			status varchar(20) DEFAULT 'open' NOT NULL,
			expiry datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 10. Votes Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_votes (
			id int(11) NOT NULL AUTO_INCREMENT,
			block varchar(20) DEFAULT '' NOT NULL,
			poll_id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			user_id bigint(20) NOT NULL,
			`option` varchar(255) NOT NULL,
			voted_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY poll_id (poll_id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 11. Vehicles Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_vehicles (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			type varchar(20) NOT NULL,
			plate_no varchar(20) NOT NULL,
			owner_name varchar(255) DEFAULT '' NOT NULL,
			number varchar(20) DEFAULT '' NOT NULL,
			brand varchar(50) DEFAULT '' NOT NULL,
			model varchar(50) DEFAULT '' NOT NULL,
			sticker varchar(20) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 12. Facilities Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_facilities (
			id varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			rate decimal(10,2) DEFAULT 0 NOT NULL,
			rate_unit varchar(20) DEFAULT 'Hour' NOT NULL,
			max_hours int(5) DEFAULT 0 NOT NULL,
            booking_required tinyint(1) DEFAULT 1 NOT NULL,
			rules text NOT NULL,
			status varchar(20) DEFAULT 'active' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 12. Bookings Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_bookings (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			facility_id varchar(50) NOT NULL,
			resident_id varchar(50) NOT NULL,
			start_time datetime NOT NULL,
			end_time datetime NOT NULL,
			status varchar(20) DEFAULT 'confirmed' NOT NULL,
			amount decimal(10,2) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY facility_id (facility_id),
			KEY resident_id (resident_id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 13. Daily Help Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_daily_help (
			id varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			role varchar(50) DEFAULT '' NOT NULL,
			category varchar(50) DEFAULT 'Support Staff' NOT NULL,
			phone varchar(20) DEFAULT '' NOT NULL,
			flats_served text NOT NULL,
			profile_photo text NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 14. Rules Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_rules (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			slug varchar(255) NOT NULL,
			content longtext NOT NULL,
			category varchar(50) DEFAULT 'general' NOT NULL,
			priority varchar(20) DEFAULT 'medium' NOT NULL,
			tags text NOT NULL,
			status varchar(20) DEFAULT 'draft' NOT NULL,
			effective_date date DEFAULT NULL,
			expiry_date date DEFAULT NULL,
			requires_acknowledgment tinyint(1) DEFAULT 1 NOT NULL,
			acknowledgment_deadline date DEFAULT NULL,
			fine_amount decimal(10,2) DEFAULT 0.00 NOT NULL,
			parent_id varchar(50) DEFAULT '' NOT NULL,
			version int(11) DEFAULT 1 NOT NULL,
			created_by bigint(20) NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			updated_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			updated_by bigint(20) DEFAULT 0 NOT NULL,
			published_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY category (category),
			KEY effective_date (effective_date),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		// 15. Rule Versions Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_rule_versions (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			rule_id varchar(50) NOT NULL,
			version int(11) NOT NULL,
			title varchar(255) NOT NULL,
			content longtext NOT NULL,
			change_summary text NOT NULL,
			changed_by bigint(20) NOT NULL,
			changed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY version (version)
		) $charset_collate;";

		// 16. Rule Acknowledgments Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_rule_acknowledgments (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			block varchar(20) DEFAULT '' NOT NULL,
			rule_id varchar(50) NOT NULL,
			rule_version int(11) NOT NULL,
			resident_id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			acknowledged_at datetime NOT NULL,
			ip_address varchar(45) DEFAULT '' NOT NULL,
			user_agent text NOT NULL,
			signature_data text NOT NULL,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY resident_id (resident_id),
			KEY flat_no (flat_no),
			UNIQUE KEY unique_acknowledgment (rule_id, rule_version, resident_id)
		) $charset_collate;";

		// 17. Rule Violations Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_rule_violations (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			rule_id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			resident_id varchar(50) DEFAULT '' NOT NULL,
			violation_date datetime NOT NULL,
			description text NOT NULL,
			evidence_urls text NOT NULL,
			fine_amount decimal(10,2) DEFAULT 0.00 NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			appeal_reason text NOT NULL,
			appeal_status varchar(20) DEFAULT '' NOT NULL,
			payment_status varchar(20) DEFAULT 'unpaid' NOT NULL,
			payment_date datetime DEFAULT NULL,
			payment_ref varchar(100) DEFAULT '' NOT NULL,
			reported_by bigint(20) NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			resolved_at datetime DEFAULT NULL,
			resolved_by bigint(20) DEFAULT 0 NOT NULL,
			admin_notes text NOT NULL,
			PRIMARY KEY  (id),
			KEY rule_id (rule_id),
			KEY flat_no (flat_no),
			KEY status (status),
			KEY payment_status (payment_status)
		) $charset_collate;";

		// 18. Rule Categories Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_rule_categories (
			id varchar(50) NOT NULL,
			name varchar(100) NOT NULL,
			slug varchar(100) NOT NULL,
			description text NOT NULL,
			icon varchar(50) DEFAULT 'bi-file-text' NOT NULL,
			color varchar(20) DEFAULT '#6c757d' NOT NULL,
			display_order int(11) DEFAULT 0 NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		// 19. Documents Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_documents (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_no varchar(50) DEFAULT '' NOT NULL,
			title varchar(255) NOT NULL,
			category varchar(50) DEFAULT 'other' NOT NULL,
			file_path text NOT NULL,
			drive_id varchar(100) DEFAULT '' NOT NULL,
			access_level varchar(20) DEFAULT 'admin' NOT NULL,
			uploaded_by bigint(20) NOT NULL,
			status varchar(20) DEFAULT 'approved' NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no),
			KEY status (status)
		) $charset_collate;";

		// 15. Requests Table (Audit Trail)
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_requests (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			module varchar(50) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			entity_type varchar(50) NOT NULL,
			request_type varchar(20) NOT NULL,
			entity_id varchar(50) DEFAULT '' NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			admin_note text NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			created_by bigint(20) DEFAULT 0 NOT NULL,
			processed_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			processed_by bigint(20) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no),
			KEY status (status),
			KEY module (module)
		) $charset_collate;";

		// 16. Audit Logs
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_audit_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			action varchar(100) NOT NULL,
			entity_type varchar(50) NOT NULL,
			entity_id varchar(50) NOT NULL,
			details text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// 17. Meta Table (Key-Value Store)
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_meta (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			meta_key varchar(255) NOT NULL,
			meta_value longtext NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY meta_key (meta_key)
		) $charset_collate;";

		// 18. Notification Channels
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notification_channels (
			channel_slug varchar(20) NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			config longtext NOT NULL,
			PRIMARY KEY  (channel_slug)
		) $charset_collate;";

		// 19. Notification Events
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notification_events (
			event_slug varchar(50) NOT NULL,
			module varchar(20) NOT NULL,
			default_channels varchar(255) NOT NULL,
			PRIMARY KEY  (event_slug)
		) $charset_collate;";

		// 20. Notification Templates
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notification_templates (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_slug varchar(50) NOT NULL,
			channel varchar(20) NOT NULL,
			subject varchar(255) DEFAULT '',
			content longtext NOT NULL,
			version int(11) DEFAULT 1 NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY event_slug (event_slug),
			KEY channel (channel)
		) $charset_collate;";

		// 21. Notification Preferences
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notification_preferences (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			event_slug varchar(50) NOT NULL,
			channel varchar(20) NOT NULL,
			is_enabled tinyint(1) DEFAULT 1 NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY event_channel (event_slug, channel)
		) $charset_collate;";

		// 22. Notification Logs
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_notification_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			event_slug varchar(50) NOT NULL,
			channel varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			cost decimal(10,4) DEFAULT 0.0000 NOT NULL,
			actor_id bigint(20) DEFAULT 0 NOT NULL,
			payload longtext,
			response text,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY actor_id (actor_id),
			KEY event_slug (event_slug)
		) $charset_collate;";

		// 23. In-App Notifications
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_inapp_notifications (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			event_slug varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			content text NOT NULL,
			is_read tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_read (is_read)
		) $charset_collate;";

		// 24. Custom Roles Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_roles (
			id varchar(50) NOT NULL,
			name varchar(100) NOT NULL,
			capabilities longtext NOT NULL,
			is_system tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			updated_at datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 25. Staff-Flat Mapping Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_staff_flats (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			staff_id varchar(50) NOT NULL,
			flat_id varchar(50) NOT NULL,
			PRIMARY KEY  (id),
			KEY staff_id (staff_id),
			KEY flat_id (flat_id)
		) $charset_collate;";

		// 26. Resident-Role Mapping Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_resident_role_map (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			resident_id varchar(50) NOT NULL,
			role_id varchar(50) NOT NULL,
			PRIMARY KEY  (id),
			KEY resident_id (resident_id),
			KEY role_id (role_id)
		) $charset_collate;";

		// 27. Detailed Payments Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}Society_NestX_payments (
			id varchar(50) NOT NULL,
			invoice_id varchar(50) NOT NULL,
			amount decimal(15,2) NOT NULL,
			method varchar(20) DEFAULT 'UPI' NOT NULL,
			reference varchar(100) DEFAULT '' NOT NULL,
			date datetime DEFAULT '1970-01-01 00:00:01' NOT NULL,
			recorded_by bigint(20) DEFAULT 0 NOT NULL,
			request_id varchar(50) DEFAULT '' NOT NULL,
			metadata longtext,
			PRIMARY KEY  (id),
			KEY invoice_id (invoice_id),
			KEY date (date)
		) $charset_collate;";

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		self::seed_defaults();
	}

	/**
	 * Seed Default Notification Configurations.
	 */
	public static function seed_defaults() {
		global $wpdb;

		// 1. Channels
		$channels_table = "{$wpdb->prefix}Society_NestX_notification_channels";
		$existing_channels = $wpdb->get_var("SELECT COUNT(*) FROM $channels_table");
		
		if ($existing_channels == 0) {
			$wpdb->insert($channels_table, ['channel_slug' => 'email', 'is_active' => 1, 'config' => json_encode(['method' => 'wp_mail'])]);
			$wpdb->insert($channels_table, ['channel_slug' => 'whatsapp', 'is_active' => 0, 'config' => json_encode(['sid' => '', 'token' => '', 'monthly_budget' => 50, 'current_usage' => 0])]);
			$wpdb->insert($channels_table, ['channel_slug' => 'inapp', 'is_active' => 1, 'config' => json_encode([])]);
		}

		// 2. Events
		$events_table = "{$wpdb->prefix}Society_NestX_notification_events";
		$default_events = [
			['event_slug' => 'visitor_checkin', 'module' => 'visitors', 'default_channels' => 'inapp,whatsapp,email'],
			['event_slug' => 'invoice_generated', 'module' => 'accounts', 'default_channels' => 'email,inapp'],
			['event_slug' => 'payment_due', 'module' => 'accounts', 'default_channels' => 'email,inapp,whatsapp'],
			['event_slug' => 'sos_alert', 'module' => 'security', 'default_channels' => 'inapp,whatsapp,email'],
			['event_slug' => 'notice_published', 'module' => 'notices', 'default_channels' => 'inapp,email'],
			['event_slug' => 'request_approved', 'module' => 'residents', 'default_channels' => 'inapp,email'],
			['event_slug' => 'request_rejected', 'module' => 'residents', 'default_channels' => 'inapp,email'],
			// Rules & Regulations Events
			['event_slug' => 'rule_published', 'module' => 'rules', 'default_channels' => 'inapp,email'],
			['event_slug' => 'rule_updated', 'module' => 'rules', 'default_channels' => 'inapp,email'],
			['event_slug' => 'acknowledgment_reminder', 'module' => 'rules', 'default_channels' => 'email,whatsapp'],
			['event_slug' => 'violation_reported', 'module' => 'rules', 'default_channels' => 'inapp,email,whatsapp'],
			['event_slug' => 'violation_resolved', 'module' => 'rules', 'default_channels' => 'inapp,email'],
			['event_slug' => 'appeal_approved', 'module' => 'rules', 'default_channels' => 'inapp,email'],
			['event_slug' => 'appeal_rejected', 'module' => 'rules', 'default_channels' => 'inapp,email']
		];

		foreach ($default_events as $event) {
			$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $events_table WHERE event_slug = %s", $event['event_slug']));
			if (!$exists) {
				$wpdb->insert($events_table, $event);
			}
		}

		// 3. Templates (Default V1)
		$templates_table = "{$wpdb->prefix}Society_NestX_notification_templates";
		$default_templates = [
			// Visitor Templates
			['event_slug' => 'visitor_checkin', 'channel' => 'inapp', 'subject' => 'Visitor Arrived', 'content' => 'Visitor {visitor_name} has arrived at the gate.'],
			['event_slug' => 'visitor_checkin', 'channel' => 'email', 'subject' => 'Visitor Arrived at Gate {flat_no}', 'content' => 'Hello {resident_name},<br><br>Visitor <b>{visitor_name}</b> is waiting at the gate for Flat {flat_no}.'],
			['event_slug' => 'visitor_checkin', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SNESTX Alert: Visitor {visitor_name} is waiting for you at the gate.'],
			
			// Invoice Generated Templates
			['event_slug' => 'invoice_generated', 'channel' => 'inapp', 'subject' => 'New Invoice Generated', 'content' => 'A new invoice of {amount} has been generated for {month}.'],
			['event_slug' => 'invoice_generated', 'channel' => 'email', 'subject' => 'Maintenance Invoice - {month}', 'content' => 'Hello {resident_name},<br><br>A new invoice for <b>{month}</b> has been generated for amount <b>{amount}</b>. Please pay by {due_date}.'],
			
			// Payment Due Templates
			['event_slug' => 'payment_due', 'channel' => 'inapp', 'subject' => 'Payment Reminder', 'content' => 'Your payment of {amount} is due on {due_date}.'],
			['event_slug' => 'payment_due', 'channel' => 'email', 'subject' => 'Payment Reminder - {month}', 'content' => 'Hello {resident_name},<br><br>This is a reminder that your payment of <b>{amount}</b> for <b>{month}</b> is due tomorrow ({due_date}).'],
			['event_slug' => 'payment_due', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SNESTX Reminder: Your payment of {amount} for {month} is due on {due_date}. Please pay to avoid penalties.'],
			
			// Request Approval Templates
			['event_slug' => 'request_approved', 'channel' => 'inapp', 'subject' => 'Request Approved', 'content' => 'Your request for {request_type} was approved by {admin_name} on {time}.'],
			['event_slug' => 'request_approved', 'channel' => 'email', 'subject' => 'Request Approved: {request_type}', 'content' => 'Hello {resident_name},<br><br>Your request for <b>{request_type}</b> was approved by {admin_name} on {time}.<br><br>Details: {details}'],
			
			//Request Rejection Templates
			['event_slug' => 'request_rejected', 'channel' => 'inapp', 'subject' => 'Request Rejected', 'content' => 'Your request for {request_type} was rejected by {admin_name} on {time}.'],
			['event_slug' => 'request_rejected', 'channel' => 'email', 'subject' => 'Request Rejected: {request_type}', 'content' => 'Hello {resident_name},<br><br>Your request for <b>{request_type}</b> was rejected by {admin_name} on {time}.<br><br>Admin Note: {admin_note}'],
			
			// Rules & Regulations Templates
			['event_slug' => 'rule_published', 'channel' => 'inapp', 'subject' => 'New Rule Published', 'content' => 'A new rule "{title}" has been published. Please review and acknowledge.'],
			['event_slug' => 'rule_published', 'channel' => 'email', 'subject' => 'New Society Rule: {title}', 'content' => 'Hello {resident_name},<br><br>A new rule titled <b>{title}</b> has been published.<br><br>Please login to review and acknowledge this rule by {deadline}.'],
			
			['event_slug' => 'rule_updated', 'channel' => 'inapp', 'subject' => 'Rule Updated', 'content' => 'Rule "{title}" has been updated to version {version}. Please review.'],
			['event_slug' => 'rule_updated', 'channel' => 'email', 'subject' => 'Updated Rule: {title}', 'content' => 'Hello {resident_name},<br><br>The rule <b>{title}</b> has been updated to version {version}.<br><br>Please review the changes at your earliest convenience.'],
			
			['event_slug' => 'acknowledgment_reminder', 'channel' => 'email', 'subject' => 'Pending Rule Acknowledgments - Action Required', 'content' => 'Hello {resident_name},<br><br>You have {count} pending rule acknowledgments.<br>Deadline: {deadline}<br><br>Please login to acknowledge these rules.'],
			['event_slug' => 'acknowledgment_reminder', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SNESTX Reminder: You have {count} pending rule acknowledgments. Deadline: {deadline}. Please login to acknowledge.'],
			
			['event_slug' => 'violation_reported', 'channel' => 'inapp', 'subject' => 'Violation Reported', 'content' => 'A violation of "{rule_title}" has been reported against your flat. Fine: ₹{amount}'],
			['event_slug' => 'violation_reported', 'channel' => 'email', 'subject' => 'Rule Violation Reported - Flat {flat_no}', 'content' => 'Hello {resident_name},<br><br>A violation of the rule <b>{rule_title}</b> has been reported against Flat {flat_no}.<br><br>Violation Date: {date}<br>Fine Amount: ₹{amount}<br><br>You may appeal this violation within 7 days.'],
			['event_slug' => 'violation_reported', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SNESTX Alert: A rule violation has been reported against Flat {flat_no}. Fine: ₹{amount}. Login to view details.'],
			
			['event_slug' => 'violation_resolved', 'channel' => 'inapp', 'subject' => 'Violation {status}', 'content' => 'The violation reported on {date} has been {status}.'],
			['event_slug' => 'violation_resolved', 'channel' => 'email', 'subject' => 'Violation {status}', 'content' => 'Hello {resident_name},<br><br>The violation reported on {date} has been <b>{status}</b>.<br><br>Admin Notes: {notes}'],
			
			['event_slug' => 'appeal_approved', 'channel' => 'inapp', 'subject' => 'Appeal Approved', 'content' => 'Your appeal for violation #{id} has been approved.'],
			['event_slug' => 'appeal_approved', 'channel' => 'email', 'subject' => 'Appeal Approved - Violation #{id}', 'content' => 'Hello {resident_name},<br><br>Your appeal for violation #{id} has been approved. The fine has been waived.'],
			
			['event_slug' => 'appeal_rejected', 'channel' => 'inapp', 'subject' => 'Appeal Rejected', 'content' => 'Your appeal for violation #{id} has been rejected.'],
			['event_slug' => 'appeal_rejected', 'channel' => 'email', 'subject' => 'Appeal Rejected - Violation #{id}', 'content' => 'Hello {resident_name},<br><br>Your appeal for violation #{id} has been rejected.<br><br>Reason: {reason}<br><br>The fine remains payable.']
		];

		foreach ($default_templates as $tpl) {
			$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $templates_table WHERE event_slug = %s AND channel = %s", $tpl['event_slug'], $tpl['channel']));
			if (!$exists) {
				$tpl['is_active'] = 1;
				$tpl['created_at'] = current_time('mysql');
				$wpdb->insert($templates_table, $tpl);
			}
		}
		
		// 4. Seed Default Rule Categories
		$categories_table = "{$wpdb->prefix}Society_NestX_rule_categories";
		$existing_categories = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
		
		if ($existing_categories == 0) {
			$default_categories = [
				['id' => 'cat_general', 'name' => 'General Rules', 'slug' => 'general', 'description' => 'General society guidelines and conduct rules', 'icon' => 'bi-file-text', 'color' => '#6c757d', 'display_order' => 1],
				['id' => 'cat_parking', 'name' => 'Parking & Vehicles', 'slug' => 'parking', 'description' => 'Parking allocation and vehicle usage rules', 'icon' => 'bi-car-front', 'color' => '#0d6efd', 'display_order' => 2],
				['id' => 'cat_noise', 'name' => 'Noise & Disturbance', 'slug' => 'noise', 'description' => 'Noise control and peaceful living guidelines', 'icon' => 'bi-volume-up', 'color' => '#dc3545', 'display_order' => 3],
				['id' => 'cat_pets', 'name' => 'Pet Policy', 'slug' => 'pets', 'description' => 'Pet ownership and management rules', 'icon' => 'bi-heart', 'color' => '#198754', 'display_order' => 4],
				['id' => 'cat_facilities', 'name' => 'Facility Usage', 'slug' => 'facilities', 'description' => 'Common facility booking and usage rules', 'icon' => 'bi-building', 'color' => '#fd7e14', 'display_order' => 5],
				['id' => 'cat_payment', 'name' => 'Payment & Fees', 'slug' => 'payment', 'description' => 'Maintenance payment and fee structure', 'icon' => 'bi-cash-coin', 'color' => '#20c997', 'display_order' => 6],
				['id' => 'cat_safety', 'name' => 'Safety & Security', 'slug' => 'safety', 'description' => 'Safety protocols and security measures', 'icon' => 'bi-shield-check', 'color' => '#ffc107', 'display_order' => 7],
				['id' => 'cat_maintenance', 'name' => 'Property Maintenance', 'slug' => 'maintenance', 'description' => 'Property upkeep and maintenance responsibilities', 'icon' => 'bi-tools', 'color' => '#6610f2', 'display_order' => 8]
			];
			
			foreach ($default_categories as $cat) {
				$cat['created_at'] = current_time('mysql');
				$wpdb->insert($categories_table, $cat);
			}
		}

		// 5. Seed Default Roles
		$roles_table = "{$wpdb->prefix}Society_NestX_roles";
		$existing_roles = $wpdb->get_var("SELECT COUNT(*) FROM $roles_table");
		
		if ($existing_roles == 0) {
			$all_caps = array_keys(SNESTX51_RBAC_Manager::get_available_capabilities());
			$default_roles = [
				[
					'id'           => 'society_admin',
					'name'         => 'Society Admin',
					'capabilities' => json_encode($all_caps),
					'is_system'    => 1
				],
				[
					'id'           => 'treasurer',
					'name'         => 'Treasurer',
					'capabilities' => json_encode(['dashboard_view', 'finance_view', 'finance_manage', 'residents_view']),
					'is_system'    => 1
				],
				[
					'id'           => 'security_head',
					'name'         => 'Security Head',
					'capabilities' => json_encode(['dashboard_view', 'staff_manage', 'vehicles_manage', 'visitor_alerts']),
					'is_system'    => 1
				],
				[
					'id'           => 'resident_rep',
					'name'         => 'Resident Representative',
					'capabilities' => json_encode(['dashboard_view', 'residents_view', 'notices_manage', 'rules_manage']),
					'is_system'    => 1
				]
			];
			
			foreach ($default_roles as $role) {
				$role['created_at'] = current_time('mysql');
				$role['updated_at'] = current_time('mysql');
				$wpdb->insert($roles_table, $role);
			}
		}
	}
    
    /**
     * Clear all data from plugin tables.
     */
    /**
     * Clear all data from MySQL tables.
     */
    public static function reset_mysql() {
        global $wpdb;
        
        $tables = array(
            'Society_NestX_flats',
            'Society_NestX_residents',
            'Society_NestX_resident_history',
            'Society_NestX_expenses',
            'Society_NestX_assets',
            'Society_NestX_notices',
            'Society_NestX_invoices',
            'Society_NestX_receipts',
            'Society_NestX_polls',
            'Society_NestX_votes',
            'Society_NestX_vehicles',
            'Society_NestX_facilities',
            'Society_NestX_bookings',
            'Society_NestX_daily_help',
            'Society_NestX_documents',
            'Society_NestX_rules',
            'Society_NestX_rule_versions',
            'Society_NestX_rule_acknowledgments',
            'Society_NestX_rule_violations',
            'Society_NestX_rule_categories',
            'Society_NestX_requests',
            'Society_NestX_audit_logs',
            'Society_NestX_meta',
            'Society_NestX_notification_channels',
            'Society_NestX_notification_events',
            'Society_NestX_notification_templates',
            'Society_NestX_notification_preferences',
            'Society_NestX_notification_logs',
            'Society_NestX_inapp_notifications',
            'Society_NestX_roles',
            'Society_NestX_staff_flats',
            'Society_NestX_resident_role_map',
            'Society_NestX_payments'
        );
        
        foreach($tables as $t) {
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}{$t}");
        }
    }

    /**
     * Clear all JSON data files.
     */
    public static function reset_json() {
        $uploads = wp_upload_dir();
        $data_dir = $uploads['basedir'] . '/society-nestx/data/';
        
        if ( is_dir( $data_dir ) ) {
            $files = glob( $data_dir . '*.json' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        wp_delete_file( $file );
                    }
                }
            }
        }
    }

    /**
     * Keep for backward compatibility or full reset if needed.
     */
    public static function reset_data() {
        self::reset_mysql();
        self::reset_json();
    }
}
