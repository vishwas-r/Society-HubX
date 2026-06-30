<?php
/**
 * Class: Media Manager
 * Handles local media uploads for profile pictures.
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Media_Manager {

	private $base_dir;
	private $base_url;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->base_dir = $upload_dir['basedir'] . '/society-governx/profile-pics/';
		$this->base_url = $upload_dir['baseurl'] . '/society-governx/profile-pics/';

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

		// Move file
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Custom destination filesystem path naming structure is required.
		if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return $this->base_url . $subfolder . '/' . $filename;
		}

		return new WP_Error( 'upload_failed', 'Failed to save profile photo.' );
	}
}
