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
			content text NOT NULL,
			priority varchar(20) DEFAULT 'normal' NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id)
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

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
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
            'society_governx_documents'
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
