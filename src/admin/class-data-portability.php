<?php
/**
 * Class: Data Portability
 * Handles Import/Export of Society Data (CSV/JSON/ZIP).
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Data_Portability {

	public function __construct() {
		// Admin POST actions for Export/Import
		add_action( 'admin_post_SNESTX51_export_data', array( $this, 'handle_export_request' ) );
		add_action( 'admin_post_SNESTX51_import_data', array( $this, 'handle_import_request' ) );
	}

	/**
	 * Handle Data Export (ZIP of CSVs + JSON).
	 */
	public function handle_export_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized Access' );
		}
		check_admin_referer( 'SNESTX51_export_nonce' );

		// 1. Prepare Environment
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/SNESTX_temp_export_' . md5( uniqid() ) . '/';
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		$db     = new SNESTX51_DB_Router();
		$tables = SNESTX51_DB_Router::TABLES;
		$full_dump = array();

		// 2. Process Tables
		foreach ( $tables as $table ) {
			// Skip meta if not needed, but good for backup
			$data = $db->get( $table );
			$full_dump[ $table ] = $data;

			if ( empty( $data ) ) {
				continue;
			}

			// Generate CSV for this table
			$csv_file = $temp_dir . $table . '.csv';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Custom temporary CSV file generation.
			$fp = fopen( $csv_file, 'w' );

			// Headers from first row keys
			$headers = array_keys( reset( $data ) );
			fputcsv( $fp, $headers );

			foreach ( $data as $row ) {
				// Ensure row has all headers (handle missing keys)
				$row_data = array();
				foreach ( $headers as $col ) {
					$val = isset( $row[ $col ] ) ? $row[ $col ] : '';
					if ( is_array( $val ) || is_object( $val ) ) {
						$val = json_encode( $val );
					}
					$row_data[] = $val;
				}
				fputcsv( $fp, $row_data );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Custom temporary CSV file generation.
			fclose( $fp );
		}

		// 3. Generate JSON Dump
		file_put_contents( $temp_dir . 'search_index_dump.json', json_encode( $full_dump, JSON_PRETTY_PRINT ) );

		// 4. Create ZIP
		$zip_filename = 'society_nestx_export_' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';
		$zip_path     = $temp_dir . $zip_filename;

		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) === true ) {
				// Add all files in temp dir to zip
				$files = new DirectoryIterator( $temp_dir );
				foreach ( $files as $file ) {
					if ( ! $file->isDot() && ! $file->isDir() && $file->getFilename() !== $zip_filename ) {
						$zip->addFile( $file->getPathname(), $file->getFilename() );
					}
				}
				$zip->close();
			} else {
				wp_die( 'Failed to create ZIP archive (ZipArchive Error).' );
			}
		} else {
			// Fallback: PclZip (WordPress Core)
			if ( ! class_exists( 'PclZip' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );
			}
			
			if ( class_exists( 'PclZip' ) ) {
				$archive = new PclZip( $zip_path );
				// Create list of files to add
				$files_to_zip = array();
				$files = new DirectoryIterator( $temp_dir );
				foreach ( $files as $file ) {
					if ( ! $file->isDot() && ! $file->isDir() && $file->getFilename() !== $zip_filename ) {
						$files_to_zip[] = $file->getPathname();
					}
				}
				
				// Create zip, removing the temp path structure
				$v_list = $archive->create( $files_to_zip, PCLZIP_OPT_REMOVE_PATH, $temp_dir );
				if ( $v_list == 0 ) {
					wp_die( 'Failed to create ZIP archive (PclZip Error): ' . esc_html( $archive->errorInfo(true) ) );
				}
			} else {
				wp_die( 'Critical Error: No ZIP library available (ZipArchive or PclZip).' );
			}
		}

		// 5. Stream Download
		if ( file_exists( $zip_path ) ) {
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $zip_filename . '"' );
			header( 'Content-Length: ' . filesize( $zip_path ) );
			header( 'Pragma: no-cache' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Direct file download streaming.
			readfile( $zip_path );

			// 6. Cleanup
			$this->recursive_rmdir( $temp_dir );
			exit;
		} else {
			wp_die( 'Export file not found.' );
		}
	}

	/**
	 * Handle CSV Import.
	 */
	public function handle_import_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized Access' );
		}
		check_admin_referer( 'SNESTX51_import_nonce' );

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=snestx51-global-settings&tab=portability&error=no_file' ) );
			exit;
		}

		$target_table = isset( $_POST['target_table'] ) ? sanitize_text_field( wp_unslash( $_POST['target_table'] ) ) : '';
		$valid_tables = SNESTX51_DB_Router::TABLES;

		if ( ! in_array( $target_table, $valid_tables ) ) {
			wp_die( 'Invalid Target Table' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary file path from $_FILES, handled safely.
		$file = isset( $_FILES['import_file']['tmp_name'] ) ? wp_unslash( $_FILES['import_file']['tmp_name'] ) : '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Custom temporary CSV file reading.
		$handle = fopen( $file, 'r' );

		if ( ! $handle ) {
			wp_die( 'Cannot open file.' );
		}

		// Read Headers
		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			wp_die( 'Empty CSV file.' );
		}

		// Normalize headers (trim, lowercase if needed, but keeping exact for now)
		$headers = array_map( 'trim', $headers );

		$db = new SNESTX51_DB_Router();
		$count = 0;
		$errors = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			// Combine headers with row data
			if ( count( $headers ) !== count( $row ) ) {
				$errors++;
				continue; // Skip malformed rows
			}

			$data = array_combine( $headers, $row );

			// Cleanup: Convert JSON strings back to arrays if needed?
			// DB Router insert handles array->json_encode, but here we have strings.
			// Depending on DB Router logic, it might expect arrays for some fields.
			// For now, we assume simple import. If a field like 'options' is json string, it stays string.
			// However, DB Router might re-encode it if it expects array.
			// Let's decode known JSON fields if they look like JSON.
			foreach ( $data as $k => $v ) {
				if ( is_string( $v ) && ( substr( $v, 0, 1 ) === '{' || substr( $v, 0, 1 ) === '[' ) ) {
					$decoded = json_decode( $v, true );
					if ( json_last_error() === JSON_ERROR_NONE ) {
						$data[ $k ] = $decoded;
					}
				}
			}

			// Insert
			$res = $db->insert( $target_table, $data );
			if ( ! is_wp_error( $res ) ) {
				$count++;
			} else {
				$errors++;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Custom temporary CSV file reading.
		fclose( $handle );

		wp_safe_redirect( admin_url( 'admin.php?page=snestx51-global-settings&tab=portability&imported=' . $count . '&errors=' . $errors ) );
		exit;
	}

	/**
	 * Recursive Directory Delete Helper.
	 */
	private function recursive_rmdir( $dir ) {
		if ( is_dir( $dir ) ) {
			$objects = scandir( $dir );
			foreach ( $objects as $object ) {
				if ( $object != '.' && $object != '..' ) {
					if ( is_dir( $dir . '/' . $object ) ) {
						$this->recursive_rmdir( $dir . '/' . $object );
					} else {
						wp_delete_file( $dir . '/' . $object );
					}
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Custom temp directory cleanup.
			rmdir( $dir );
		}
	}
}
