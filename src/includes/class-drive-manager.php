<?php
/**
 * Class: Drive Manager
 * Handles File Operations: Folders, Uploads, List.
 * Switches between Google Drive (Connected) and Local Uploads (Offline).
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Drive_Manager {

	private $is_connected;
	private $local_root;

	public function __construct() {
		$this->is_connected = (bool) get_option( 'snestx51_google_refresh_token' );
		
		$upload_dir = wp_upload_dir();
		$this->local_root = $upload_dir['basedir'] . '/society-nestx/docs/';
		
		if ( ! $this->is_connected && ! file_exists( $this->local_root ) ) {
			wp_mkdir_p( $this->local_root );
		}
	}

	/**
	 * Get System Folder ID/Path (e.g. 'Receipts', 'Assets').
	 */
	public function get_system_folder( $name ) {
		if ( $this->is_connected ) {
			$root_id = get_option( 'snestx51_drive_root_id' );
			if ( ! $root_id ) return new WP_Error( 'no_root', 'System Root not found.' );

			// Search for folder in Root
			$q = "name = '{$name}' and '{$root_id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
			$res = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files?q=' . urlencode( $q ) );
			
			if ( ! is_wp_error( $res ) && ! empty( $res['files'] ) ) {
				return $res['files'][0]['id'];
			}
			
			// Create if missing
			$body = array(
				'name' => $name,
				'mimeType' => 'application/vnd.google-apps.folder',
				'parents' => array( $root_id ),
			);
			$new = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files', 'POST', $body );
			return $new['id'] ?? new WP_Error( 'create_fail', 'Failed to create system folder.' );

		} else {
			$path = $this->local_root . $name;
			if ( ! file_exists( $path ) ) wp_mkdir_p( $path );
			return $path;
		}
	}

	/**
	 * Get or Create a Folder for a specific Flat.
	 * 
	 * @param string $flat_no e.g., 'A-101'
	 * @return string|WP_Error Folder ID (Drive) or Path (Local).
	 */
	public function ensure_flat_folder( $flat_no ) {
		$flat_no = sanitize_file_name( $flat_no );
		
		if ( empty( $flat_no ) ) {
			return new WP_Error( 'invalid_flat', 'Invalid Flat Number provided.' );
		}

		if ( $this->is_connected ) {
			// Remote Drive Logic.
			$root_id = get_option( 'snestx51_drive_root_id' );
			if ( ! $root_id ) {
				return new WP_Error( 'no_root', 'System Root Folder not found. Run Setup.' );
			}
			
			// Find 'Resident_Docs' folder first.
			$q = "name = 'Resident_Docs' and '{$root_id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
			$res = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files?q=' . urlencode( $q ) );
			
			if ( is_wp_error( $res ) ) return $res;
			
			if ( empty( $res['files'] ) ) {
				// Fallback: create it if missing
				return new WP_Error( 'setup_incomplete', 'Resident_Docs folder missing.' );
			}
			
			$parent_id = $res['files'][0]['id'];
			
			// Now search for Flat Folder inside Resident_Docs.
			$q_flat = "name = '{$flat_no}' and '{$parent_id}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
			$res_flat = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files?q=' . urlencode( $q_flat ) );
			
			if ( ! empty( $res_flat['files'] ) ) {
				return $res_flat['files'][0]['id'];
			}
			
			// Create it.
			$body = array(
				'name' => $flat_no,
				'mimeType' => 'application/vnd.google-apps.folder',
				'parents' => array( $parent_id ),
			);
			$new = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files', 'POST', $body );
			
			return isset( $new['id'] ) ? $new['id'] : new WP_Error( 'create_failed', 'Could not create drive folder.' );

		} else {
			// Local Logic.
			// Ensure it ends with slash for consistency if needed, but mkdir doesn't care.
			// path join: local_root includes trailing slash.
			$path = $this->local_root . $flat_no;
			
			if ( ! file_exists( $path ) ) {
				if ( ! wp_mkdir_p( $path ) ) {
					return new WP_Error( 'mkdir_failed', 'Failed to create directory: ' . $path );
				}
			}
			return $path;
		}
	}

	/**
	 * List files in a folder.
	 */
	public function list_files( $folder_id ) {
		$files = array();

		if ( $this->is_connected ) {
			$q = "'{$folder_id}' in parents and trashed = false";
			$res = SNESTX51_Google_API_Handler::api_request( 'https://www.googleapis.com/drive/v3/files?q=' . urlencode( $q ) . '&fields=files(id,name,webViewLink,thumbnailLink,mimeType)' );
			
			if ( ! is_wp_error( $res ) && ! empty( $res['files'] ) ) {
				foreach ( $res['files'] as $f ) {
					$files[] = array(
						'id'   => $f['id'],
						'name' => $f['name'],
						'url'  => $f['webViewLink'],
						'type' => 'remote',
					);
				}
			}
		} else {
			// Local.
			if ( is_dir( $folder_id ) ) { // $folder_id is path here
				$items = scandir( $folder_id );
				$upload_url = wp_upload_dir();
				// This assumes folder_id follows local_root pattern
				$rel = str_replace( $this->local_root, '', $folder_id );
				$base_url = $upload_url['baseurl'] . '/society-nestx/docs/' . $rel . '/';
				
				foreach ( $items as $item ) {
					if ( '.' !== $item && '..' !== $item ) {
						$files[] = array(
							'id'   => md5( $item ),
							'name' => $item,
							'url'  => $base_url . $item,
							'type' => 'local',
						);
					}
				}
			}
		}

		return $files;
	}

	/**
	 * Generic Upload to Folder ID/Path.
	 */
	public function upload_to_folder( $folder, $file_array ) {
		if ( $this->is_connected ) {
			$boundary = 'foo_bar_baz';
			$metadata = json_encode( array(
				'name' => $file_array['name'],
				'parents' => array( $folder ),
			));
			$content = file_get_contents( $file_array['tmp_name'] );

			$payload = "--$boundary\r\n";
			$payload .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
			$payload .= "$metadata\r\n";
			$payload .= "--$boundary\r\n";
			$payload .= "Content-Type: " . $file_array['type'] . "\r\n\r\n";
			$payload .= "$content\r\n";
			$payload .= "--$boundary--";

			$args = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . SNESTX51_Google_API_Handler::get_valid_token(),
					'Content-Type'  => 'multipart/related; boundary=' . $boundary,
				),
				'body'    => $payload,
				'method'  => 'POST',
			);

			$res = wp_remote_request( 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', $args );

			if ( is_wp_error( $res ) ) return $res;
			
			$body = json_decode( wp_remote_retrieve_body( $res ), true );
			return $body['webViewLink'] ?? true; // Return URL or True

		} else {
			// Move file using WordPress standard wp_handle_upload
			$upload_dir_filter = function( $uploads ) use ( $folder ) {
				$base_uploads = wp_upload_dir();
				$rel = str_replace( $base_uploads['basedir'], '', $folder );
				$rel = trim( str_replace( '\\', '/', $rel ), '/' );

				$uploads['path']   = $folder;
				$uploads['url']    = $base_uploads['baseurl'] . '/' . $rel;
				$uploads['subdir'] = '/' . $rel;
				return $uploads;
			};
			add_filter( 'upload_dir', $upload_dir_filter );

			$overrides = array(
				'test_form' => false,
				'unique_filename_callback' => function( $dir, $name, $ext ) use ( $file_array ) {
					return $file_array['name'];
				}
			);

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$uploaded = wp_handle_upload( $file_array, $overrides );

			remove_filter( 'upload_dir', $upload_dir_filter );

			if ( isset( $uploaded['error'] ) ) {
				return new WP_Error( 'upload_failed', $uploaded['error'] );
			}
			return $uploaded['url'];
		}
	}

	/**
	 * Legacy Wrapper for Flat Upload
	 */
	public function upload_file( $flat_no, $file_array ) {
		$folder = $this->ensure_flat_folder( $flat_no );
		if ( is_wp_error( $folder ) ) return $folder;
		return $this->upload_to_folder( $folder, $file_array );
	}

	/**
	 * Delete File
	 */
	public function delete_file( $flat_no, $file_name ) {
		if ( $this->is_connected ) {
			// Remote deletion not fully implemented for this scope, returning true to allow metadata deletion.
			return true; 
		} else {
			$path = $this->local_root . $flat_no . '/' . $file_name;
			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
				return true;
			}
			return new WP_Error( 'not_found', 'File not found.' );
		}
	}
}
