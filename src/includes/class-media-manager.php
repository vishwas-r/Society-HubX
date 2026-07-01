<?php
/**
 * Class: Media Manager
 * Handles local media uploads for profile pictures.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Media_Manager {

	private $base_dir;
	private $base_url;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->base_dir = $upload_dir['basedir'] . '/society-hubx/profile-pics/';
		$this->base_url = $upload_dir['baseurl'] . '/society-hubx/profile-pics/';

		if ( ! file_exists( $this->base_dir ) ) {
			wp_mkdir_p( $this->base_dir );
		}
	}

	/**
	 * Upload Profile Photo with specific naming convention.
	 * 
	 * @param array  $file      $_FILES['profile_photo']
	 * @param string $flat_no   Flat Number
	 * @param string $name      Resident Name
	 * @param string $subfolder Subfolder name (default: 'residents')
	 * @return string|WP_Error Public URL of the uploaded photo.
	 */
	public function upload_profile_photo( $file, $flat_no, $name, $subfolder = 'residents' ) {
		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', 'No file provided.' );
		}

		// Ensure subfolder exists
		$target_dir = $this->base_dir . $subfolder . '/';
		if ( ! file_exists( $target_dir ) ) {
			if ( ! wp_mkdir_p( $target_dir ) ) {
				return new WP_Error( 'mkdir_failed', 'Failed to create directory: ' . $target_dir );
			}
		}

		// Sanitize inputs for file name
		$flat_clean = sanitize_file_name( $flat_no );
		$name_clean = sanitize_file_name( $name );
		$ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
		
		// Convention: flat-number-name.ext
		$filename = strtolower( sprintf( '%s-%s.%s', $flat_clean, $name_clean, $ext ) );
		$destination = $target_dir . $filename;

		// Move file using WordPress standard wp_handle_upload
		$upload_dir_filter = function( $uploads ) use ( $subfolder, $filename ) {
			$uploads['path']   = $uploads['basedir'] . '/society-hubx/profile-pics/' . $subfolder;
			$uploads['url']    = $uploads['baseurl'] . '/society-hubx/profile-pics/' . $subfolder;
			$uploads['subdir'] = '/society-hubx/profile-pics/' . $subfolder;
			return $uploads;
		};
		add_filter( 'upload_dir', $upload_dir_filter );

		$overrides = array(
			'test_form' => false,
			'unique_filename_callback' => function( $dir, $name, $ext ) use ( $filename ) {
				return $filename;
			}
		);

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$uploaded = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'upload_failed', $uploaded['error'] );
		}

		return $uploaded['url'];
	}
}
