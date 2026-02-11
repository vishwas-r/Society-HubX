<?php
/**
 * Class: DB Schema
 * Defines the SQL table structures and handles creation/updates via dbDelta.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_DB_Schema {

	/**
	 * Create or update all plugin tables.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = array();

		// 1. Flats Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_flats (
			id varchar(50) NOT NULL,
			block varchar(20) DEFAULT '' NOT NULL,
			flat_number varchar(20) DEFAULT '' NOT NULL,
			floor varchar(10) DEFAULT '' NOT NULL,
			sq_foot decimal(10,2) DEFAULT 0.00 NOT NULL,
			status varchar(20) DEFAULT 'unoccupied' NOT NULL,
			parking_slot varchar(50) DEFAULT '' NOT NULL,
			parking_status varchar(20) DEFAULT '' NOT NULL,
			type varchar(20) DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 2. Residents Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_residents (
			id varchar(50) NOT NULL,
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
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 3. Resident History Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_resident_history (
			id varchar(50) NOT NULL,
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
			vacated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 4. Expenses Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_expenses (
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
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 5. Assets Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_assets (
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
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 6. Notices Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notices (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			content longtext NOT NULL,
			urgency varchar(20) DEFAULT 'info' NOT NULL,
			audience varchar(50) DEFAULT 'All' NOT NULL,
			status varchar(20) DEFAULT 'published' NOT NULL,
			is_pinned tinyint(1) DEFAULT 0 NOT NULL,
			expiry_date date DEFAULT NULL,
			attachment_url text DEFAULT '' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY urgency (urgency)
		) $charset_collate;";

		// 7. Invoices Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_invoices (
			id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			resident_name varchar(255) DEFAULT '' NOT NULL,
			amount decimal(15,2) NOT NULL,
			month varchar(20) NOT NULL,
			type varchar(50) DEFAULT 'maintenance' NOT NULL,
			status varchar(20) DEFAULT 'unpaid' NOT NULL,
			due_date date DEFAULT '0000-00-00' NOT NULL,
			payment_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			payment_ref varchar(100) DEFAULT '' NOT NULL,
			payments longtext NOT NULL, 
			description text NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 8. Receipts Table (for tracking receipt numbers)
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_receipts (
			id varchar(50) NOT NULL,
			invoice_id varchar(50) NOT NULL,
			receipt_number varchar(50) NOT NULL,
			generated_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY invoice_id (invoice_id),
			UNIQUE KEY receipt_number (receipt_number)
		) $charset_collate;";

		// 9. Polls Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_polls (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			description text NOT NULL,
			options text NOT NULL,
			status varchar(20) DEFAULT 'open' NOT NULL,
			expiry datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 10. Votes Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_votes (
			id int(11) NOT NULL AUTO_INCREMENT,
			poll_id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			user_id bigint(20) NOT NULL,
			`option` varchar(255) NOT NULL,
			voted_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY poll_id (poll_id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 11. Vehicles Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_vehicles (
			id varchar(50) NOT NULL,
			flat_no varchar(50) NOT NULL,
			type varchar(20) NOT NULL,
			plate_no varchar(20) NOT NULL,
			owner_name varchar(255) DEFAULT '' NOT NULL,
			number varchar(20) DEFAULT '' NOT NULL,
			brand varchar(50) DEFAULT '' NOT NULL,
			model varchar(50) DEFAULT '' NOT NULL,
			sticker varchar(20) DEFAULT '' NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no)
		) $charset_collate;";

		// 12. Facilities Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_facilities (
			id varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			rate decimal(10,2) DEFAULT 0 NOT NULL,
			rate_unit varchar(20) DEFAULT 'Hour' NOT NULL,
			max_hours int(5) DEFAULT 0 NOT NULL,
			rules text NOT NULL,
			status varchar(20) DEFAULT 'active' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 12. Bookings Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_bookings (
			id varchar(50) NOT NULL,
			facility_id varchar(50) NOT NULL,
			resident_id varchar(50) NOT NULL,
			start_time datetime NOT NULL,
			end_time datetime NOT NULL,
			status varchar(20) DEFAULT 'confirmed' NOT NULL,
			amount decimal(10,2) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY facility_id (facility_id),
			KEY resident_id (resident_id)
		) $charset_collate;";

		// 13. Daily Help Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_daily_help (
			id varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			role varchar(50) DEFAULT '' NOT NULL,
			category varchar(50) DEFAULT 'Support Staff' NOT NULL,
			phone varchar(20) DEFAULT '' NOT NULL,
			flats_served text NOT NULL,
			profile_photo text NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 14. Documents Table
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_documents (
			id varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			category varchar(50) DEFAULT 'other' NOT NULL,
			file_path text NOT NULL,
			profile_photo text DEFAULT '' NOT NULL,
			drive_id varchar(100) DEFAULT '' NOT NULL,
			access_level varchar(20) DEFAULT 'admin' NOT NULL,
			uploaded_by bigint(20) NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 15. Requests Table (Audit Trail)
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_requests (
			id varchar(50) NOT NULL,
			module varchar(50) DEFAULT '' NOT NULL,
			flat_no varchar(50) NOT NULL,
			entity_type varchar(50) NOT NULL,
			request_type varchar(20) NOT NULL,
			entity_id varchar(50) DEFAULT '' NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			admin_note text NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			created_by bigint(20) DEFAULT 0 NOT NULL,
			processed_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			processed_by bigint(20) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY flat_no (flat_no),
			KEY status (status),
			KEY module (module)
		) $charset_collate;";

		// 16. Audit Logs
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_audit_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			action varchar(100) NOT NULL,
			entity_type varchar(50) NOT NULL,
			entity_id varchar(50) NOT NULL,
			details text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		// 17. Meta Table (Key-Value Store)
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_meta (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			meta_key varchar(255) NOT NULL,
			meta_value longtext NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY meta_key (meta_key)
		) $charset_collate;";

		// 18. Notification Channels
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notification_channels (
			channel_slug varchar(20) NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			config longtext NOT NULL,
			PRIMARY KEY  (channel_slug)
		) $charset_collate;";

		// 19. Notification Events
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notification_events (
			event_slug varchar(50) NOT NULL,
			module varchar(20) NOT NULL,
			default_channels varchar(255) NOT NULL,
			PRIMARY KEY  (event_slug)
		) $charset_collate;";

		// 20. Notification Templates
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notification_templates (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_slug varchar(50) NOT NULL,
			channel varchar(20) NOT NULL,
			subject varchar(255) DEFAULT '',
			content longtext NOT NULL,
			version int(11) DEFAULT 1 NOT NULL,
			is_active tinyint(1) DEFAULT 1 NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY event_slug (event_slug),
			KEY channel (channel)
		) $charset_collate;";

		// 21. Notification Preferences
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notification_preferences (
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
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_notification_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			event_slug varchar(50) NOT NULL,
			channel varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			cost decimal(10,4) DEFAULT 0.0000 NOT NULL,
			response text,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY event_slug (event_slug)
		) $charset_collate;";

		// 23. In-App Notifications
		$tables[] = "CREATE TABLE {$wpdb->prefix}society_governx_inapp_notifications (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			event_slug varchar(50) NOT NULL,
			title varchar(255) NOT NULL,
			content text NOT NULL,
			is_read tinyint(1) DEFAULT 0 NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_read (is_read)
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
		$channels_table = "{$wpdb->prefix}society_governx_notification_channels";
		$existing_channels = $wpdb->get_var("SELECT COUNT(*) FROM $channels_table");
		
		if ($existing_channels == 0) {
			$wpdb->insert($channels_table, ['channel_slug' => 'email', 'is_active' => 1, 'config' => json_encode(['method' => 'wp_mail'])]);
			$wpdb->insert($channels_table, ['channel_slug' => 'whatsapp', 'is_active' => 0, 'config' => json_encode(['sid' => '', 'token' => '', 'monthly_budget' => 50, 'current_usage' => 0])]);
			$wpdb->insert($channels_table, ['channel_slug' => 'inapp', 'is_active' => 1, 'config' => json_encode([])]);
		}

		// 2. Events
		$events_table = "{$wpdb->prefix}society_governx_notification_events";
		$default_events = [
			['event_slug' => 'visitor_checkin', 'module' => 'visitors', 'default_channels' => 'inapp,whatsapp,email'],
			['event_slug' => 'invoice_generated', 'module' => 'accounts', 'default_channels' => 'email,inapp'],
			['event_slug' => 'payment_due', 'module' => 'accounts', 'default_channels' => 'email,inapp,whatsapp'],
			['event_slug' => 'sos_alert', 'module' => 'security', 'default_channels' => 'inapp,whatsapp,email'],
			['event_slug' => 'notice_published', 'module' => 'notices', 'default_channels' => 'inapp,email'],
			['event_slug' => 'request_approved', 'module' => 'residents', 'default_channels' => 'inapp,email'],
			['event_slug' => 'request_rejected', 'module' => 'residents', 'default_channels' => 'inapp,email']
		];

		foreach ($default_events as $event) {
			$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $events_table WHERE event_slug = %s", $event['event_slug']));
			if (!$exists) {
				$wpdb->insert($events_table, $event);
			}
		}

		// 3. Templates (Default V1)
		$templates_table = "{$wpdb->prefix}society_governx_notification_templates";
		$default_templates = [
			// Visitor Templates
			['event_slug' => 'visitor_checkin', 'channel' => 'inapp', 'subject' => 'Visitor Arrived', 'content' => 'Visitor {visitor_name} has arrived at the gate.'],
			['event_slug' => 'visitor_checkin', 'channel' => 'email', 'subject' => 'Visitor Arrived at Gate {flat_no}', 'content' => 'Hello {resident_name},<br><br>Visitor <b>{visitor_name}</b> is waiting at the gate for Flat {flat_no}.'],
			['event_slug' => 'visitor_checkin', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SGVX Alert: Visitor {visitor_name} is waiting for you at the gate.'],
			
			// Invoice Generated Templates
			['event_slug' => 'invoice_generated', 'channel' => 'inapp', 'subject' => 'New Invoice Generated', 'content' => 'A new invoice of {amount} has been generated for {month}.'],
			['event_slug' => 'invoice_generated', 'channel' => 'email', 'subject' => 'Maintenance Invoice - {month}', 'content' => 'Hello {resident_name},<br><br>A new invoice for <b>{month}</b> has been generated for amount <b>{amount}</b>. Please pay by {due_date}.'],
			
			// Payment Due Templates
			['event_slug' => 'payment_due', 'channel' => 'inapp', 'subject' => 'Payment Reminder', 'content' => 'Your payment of {amount} is due on {due_date}.'],
			['event_slug' => 'payment_due', 'channel' => 'email', 'subject' => 'Payment Reminder - {month}', 'content' => 'Hello {resident_name},<br><br>This is a reminder that your payment of <b>{amount}</b> for <b>{month}</b> is due tomorrow ({due_date}).'],
			['event_slug' => 'payment_due', 'channel' => 'whatsapp', 'subject' => '', 'content' => 'SGVX Reminder: Your payment of {amount} for {month} is due on {due_date}. Please pay to avoid penalties.'],
			
			// Request Approval Templates
			['event_slug' => 'request_approved', 'channel' => 'inapp', 'subject' => 'Request Approved', 'content' => 'Your request for {request_type} was approved by {admin_name} on {time}.'],
			['event_slug' => 'request_approved', 'channel' => 'email', 'subject' => 'Request Approved: {request_type}', 'content' => 'Hello {resident_name},<br><br>Your request for <b>{request_type}</b> was approved by {admin_name} on {time}.<br><br>Details: {details}'],
			
			// Request Rejection Templates
			['event_slug' => 'request_rejected', 'channel' => 'inapp', 'subject' => 'Request Rejected', 'content' => 'Your request for {request_type} was rejected by {admin_name} on {time}.'],
			['event_slug' => 'request_rejected', 'channel' => 'email', 'subject' => 'Request Rejected: {request_type}', 'content' => 'Hello {resident_name},<br><br>Your request for <b>{request_type}</b> was rejected by {admin_name} on {time}.<br><br>Admin Note: {admin_note}']
		];

		foreach ($default_templates as $tpl) {
			$exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $templates_table WHERE event_slug = %s AND channel = %s", $tpl['event_slug'], $tpl['channel']));
			if (!$exists) {
				$tpl['is_active'] = 1;
				$tpl['created_at'] = current_time('mysql');
				$wpdb->insert($templates_table, $tpl);
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
            'society_governx_flats',
            'society_governx_residents',
            'society_governx_resident_history',
            'society_governx_expenses',
            'society_governx_assets',
            'society_governx_notices',
            'society_governx_invoices',
            'society_governx_polls',
            'society_governx_votes',
            'society_governx_vehicles',
            'society_governx_facilities',
            'society_governx_bookings',
            'society_governx_daily_help',
            'society_governx_documents',
            'society_governx_notification_channels',
            'society_governx_notification_events',
            'society_governx_notification_templates',
            'society_governx_notification_preferences',
            'society_governx_notification_logs',
            'society_governx_inapp_notifications'
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
        $data_dir = $uploads['basedir'] . '/society-govern-x/data/';
        
        if ( is_dir( $data_dir ) ) {
            $files = glob( $data_dir . '*.json' );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) {
                        unlink( $file );
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
