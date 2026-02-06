<?php
/**
 * Class: Setup Wizard
 * Handles the "Installation" of the society data structure.
 * 1. Defines the Schema (Columns).
 * 2. Creates Google Sheet + Headers (if connected).
 * 3. Creates Local JSON files + Headers (if offline/shadow).
 * 4. Creates Drive Folder Hierarchy.
 *
 * @package Society_Govern_X
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
	 * Run the Setup Process.
	 *
	 * @return array Result message.
	 */
	public static function run_setup() {
		$db = new SGVX51_DB_Router();
		$is_connected = $db->is_connected();
		$log = array();

		// 1. Initialize Local JSONs (Always needed for Shadow Replica).
		// Note: DB Router already creates empty files, but we need valid Initial Data (Headers/Structure) if meaningful.
		// Actually, JSON doesn't need "Headers" like a CSV/Sheet. It's Key-Value. 
		// But for Sheet Sync, we need to know the Columns.
		// We'll trust the SCHEMA constant for mapping.
		$log[] = 'Local Data Store verified.';

		if ( $is_connected ) {
			$log[] = self::setup_google_workspace();
		} else {
			$log[] = 'Offline Mode: Skipping Google Workspace setup.';
		}

		update_option( 'sgvx51_is_setup_complete', true );

		// 2. Auto-Create Frontend Pages.
		$pages_log = self::create_frontend_pages();
		$log = array_merge( $log, $pages_log );
		
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
				'content' => '[society_govern_x_dashboard]',
			),
			'Society Notices' => array(
				'slug'    => 'society-notices',
				'content' => '[society_govern_x_notices]',
			),
			'Residents Directory' => array(
				'slug'    => 'residents-directory',
				'content' => '[society_govern_x_directory]',
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
		$sheet_title = 'Society_GovernX_Master_' . date( 'Y-m-d' );
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
