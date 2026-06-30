<?php
/**
 * Class: Setup Wizard
 * Handles the "Installation" of the society data structure.
 * 1. Defines the Schema (Columns).
 * 2. Creates Google Sheet + Headers (if connected).
 * 3. Creates Local JSON files + Headers (if offline/shadow).
 * 4. Creates Drive Folder Hierarchy.
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Setup_Wizard {

	/**
	 * Canonical Schema Definitions.
	 * All sync logic relies on these column orders.
	 */
	const SCHEMA = array(
		'meta'       => array( 'key', 'value', 'updated_at' ),
		'residents'  => array( 'id', 'flat_no', 'name', 'phone', 'email', 'type', 'members_count', 'wp_user_id' ),
		'vehicles'   => array( 'id', 'reg_no', 'type', 'sticker_id', 'flat_no', 'owner_name' ),
		'facilities' => array( 'id', 'name', 'rate_per_hour', 'max_hours', 'rules', 'status' ),
		'bookings'   => array( 'id', 'facility_id', 'resident_id', 'start_time', 'end_time', 'status', 'amount' ),
		'assets'     => array( 'id', 'name', 'purchase_date', 'amc_provider', 'amc_phone', 'warranty_expiry', 'status' ),
		'notices'    => array( 'id', 'title', 'content', 'audience', 'expiry_date', 'attachment_url', 'created_at' ),
		'expenses'   => array( 'id', 'category', 'amount', 'date', 'payee', 'description', 'receipt_url', 'added_by' ),
	);

	/**
	 * Run the Setup Process (Step-by-Step).
	 */
	public static function save_step( $step, $data ) {
		error_log("SGVX51 Debug: Saving Setup Step: $step"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
		$log = array();
		
		switch ( $step ) {
			case 'identity':
				update_option( 'sgvx51_society_name', sanitize_text_field( $data['society_name'] ) );
				update_option( 'sgvx51_society_address_line1', sanitize_text_field( $data['address_line1'] ) );
				update_option( 'sgvx51_society_address_line2', sanitize_text_field( $data['address_line2'] ) );
				update_option( 'sgvx51_society_city', sanitize_text_field( $data['city'] ) );
				update_option( 'sgvx51_society_pincode', sanitize_text_field( $data['pincode'] ) );
				update_option( 'sgvx51_society_contact', sanitize_text_field( $data['contact'] ) );
				$log[] = 'Society identity saved.';
				break;

			case 'property':
				$log = self::generate_property_structure( $data );
				break;

			case 'financials':
				update_option( 'sgvx51_maintenance_amount', floatval( $data['maintenance_amount'] ) );
				update_option( 'sgvx51_bank_name', sanitize_text_field( $data['bank_name'] ) );
				update_option( 'sgvx51_bank_account', sanitize_text_field( $data['bank_account'] ) );
				update_option( 'sgvx51_bank_ifsc', sanitize_text_field( $data['bank_ifsc'] ) );
				update_option( 'sgvx51_bank_upi', sanitize_text_field( $data['bank_upi'] ) );
				$log[] = 'Financial settings updated.';
				break;

			case 'finalize':
				self::create_frontend_pages();
				update_option( 'sgvx51_is_setup_complete', true );
				$log[] = 'Setup finalized successfully!';
				break;
		}

		return $log;
	}

	/**
	 * Generate Flats based on input.
	 */
	private static function generate_property_structure( $data ) {
		$log = array();
		$blocks = ! empty( $data['blocks'] ) ? explode( ',', strtoupper( $data['blocks'] ) ) : array( 'A' );
		$floors = intval( $data['floors'] ?? 1 );
		$flats_per_floor = intval( $data['flats_per_floor'] ?? 1 );

		$db = Society_GoVernX::get_instance()->db;
		$count = 0;

		foreach ( $blocks as $block ) {
			$block = trim( $block );
			for ( $f = 1; $f <= $floors; $f++ ) {
				for ( $i = 1; $i <= $flats_per_floor; $i++ ) {
					$flat_num = ( $f * 100 ) + $i;
					$flat_id = 'flat_' . $block . '_' . $flat_num;
					
					$flat_data = array(
						'id'          => $flat_id,
						'block'       => $block,
						'flat_number' => (string)$flat_num,
						'floor'       => (string)$f,
						'status'      => 'occupied',
						'type'        => '2BHK',
						'sq_foot'     => 1200.00,
						'created_at'  => current_time( 'mysql' )
					);

					$db->insert( 'flats', $flat_data );
					$count++;
				}
			}
		}

		$log[] = "Generated $count flats across " . count($blocks) . " blocks.";
		return $log;
	}

	/**
	 * Auto-Create Pages with Shortcodes.
	 */
	private static function create_frontend_pages() {
		$log = array();
		$pages = array(
			'Resident Dashboard' => array(
				'slug'    => 'resident-dashboard',
				'content' => '[society_governx_dashboard]',
			),
			'Society Notices' => array(
				'slug'    => 'society-notices',
				'content' => '[society_governx_notices]',
			),
			'Residents Directory' => array(
				'slug'    => 'residents-directory',
				'content' => '[society_governx_directory]',
			),
		);

		foreach ( $pages as $title => $args ) {
			// Check if exists by Slug
			$existing = get_page_by_path( $args['slug'] );
			if ( ! $existing ) {
				$page_id = wp_insert_post( array(
					'post_title'   => $title,
					'post_name'    => $args['slug'],
					'post_content' => $args['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				));
				if ( ! is_wp_error( $page_id ) ) {
					$log[] = "Created Page: $title";
				}
			} else {
				// $log[] = "Page already exists: $title";
			}
		}
		return $log;
	}

	/**
	 * Connect to Google and create the Master Sheet.
	 */
	private static function setup_google_workspace() {
		// 1. Check if Spreadsheet ID already exists.
		$sheet_id = get_option( 'sgvx51_master_sheet_id' );
		if ( $sheet_id ) {
			return 'Spreadsheet already linked: ' . $sheet_id;
		}

		// 2. Create New Spreadsheet.
		$sheet_title = 'Society_GovernX_Master_' . gmdate( 'Y-m-d' );
		$body = array(
			'properties' => array( 'title' => $sheet_title ),
		);

		// Use the Google API Handler.
		$response = SGVX51_Google_API_Handler::api_request( 'https://sheets.googleapis.com/v4/spreadsheets', 'POST', $body );

		if ( is_wp_error( $response ) ) {
			return 'Error creating Sheet: ' . $response->get_error_message();
		}

		$sheet_id = $response['spreadsheetId'];
		update_option( 'sgvx51_master_sheet_id', $sheet_id );
		update_option( 'sgvx51_master_sheet_url', $response['spreadsheetUrl'] );

		// 3. Add Tabs and Headers.
		self::initialize_sheet_headers( $sheet_id );

		// 4. Create Drive Folders.
		self::setup_drive_folders( $sheet_title );

		return 'Successfully created Master Spreadsheet and Drive Folders.';
	}

	/**
	 * Add Tabs (Sheets) and Header Rows.
	 */
	private static function initialize_sheet_headers( $sheet_id ) {
		$requests = array();
		
		// First, rename the default "Sheet1" to 'meta'.
		$requests[] = array(
			'updateSheetProperties' => array(
				'properties' => array( 'sheetId' => 0, 'title' => 'meta' ),
				'fields' => 'title',
			),
		);

		// Create other sheets.
		$index = 1;
		foreach ( self::SCHEMA as $table => $columns ) {
			if ( 'meta' === $table ) {
				continue; // already handled.
			}
			$requests[] = array(
				'addSheet' => array(
					'properties' => array( 'title' => $table ),
				),
			);
		}

		// Execute Batch Update (Create Tabs).
		$batch_url = "https://sheets.googleapis.com/v4/spreadsheets/$sheet_id:batchUpdate";
		SGVX51_Google_API_Handler::api_request( $batch_url, 'POST', array( 'requests' => $requests ) );

		// Now write Headers.
		// We do this in a separate loop for data values.
		$data = array();
		foreach ( self::SCHEMA as $table => $columns ) {
			$data[] = array(
				'range' => "$table!A1",
				'values' => array( $columns ),
			);
		}

		$values_url = "https://sheets.googleapis.com/v4/spreadsheets/$sheet_id/values:batchUpdate";
		SGVX51_Google_API_Handler::api_request( $values_url, 'POST', array(
			'valueInputOption' => 'RAW',
			'data' => $data,
		) );
	}

	/**
	 * Create basic folder structure.
	 */
	private static function setup_drive_folders( $name ) {
		// Root Folder.
		$root_meta = array(
			'name' => $name . '_Files',
			'mimeType' => 'application/vnd.google-apps.folder',
		);
		
		$root = SGVX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files', 'POST', $root_meta );
		
		if ( ! is_wp_error( $root ) && isset( $root['id'] ) ) {
			update_option( 'sgvx51_drive_root_id', $root['id'] );
			
			// Subfolders.
			$subs = array( 'Notices', 'Receipts', 'Assets', 'Resident_Docs' );
			foreach ( $subs as $sub ) {
				$sub_meta = array(
					'name' => $sub,
					'mimeType' => 'application/vnd.google-apps.folder',
					'parents' => array( $root['id'] ),
				);
				SGVX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files', 'POST', $sub_meta );
			}
		}
	}
}
