<?php
/**
 * Class: Media Manager
 * Handles local media uploads for profile pictures.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Media_Manager {

	private $base_dir;
	private $base_url;

	public function __construct() {
		$upload_dir = wp_upload_dir();
		$this->base_dir = $upload_dir['basedir'] . '/society-govern-x/profile-pics/';
		$this->base_url = $upload_dir['baseurl'] . '/society-govern-x/profile-pics/';

		if ( ! file_exists( $this->base_dir ) ) {
			wp_mkdir_p( $this->base_dir );
		}
	}

	/**
	 * Upload Profile Photo with specific naming convention.
	 * 
	 * @param array  $file    $_FILES['profile_photo']
	 * @param string $flat_no Flat Number
	 * @param string $name    Resident Name
	 * @return string|WP_Error Public URL of the uploaded photo.
	 */
	public function upload_profile_photo( $file, $flat_no, $name ) {
		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'no_file', 'No file provided.' );
		}

		// Sanitize inputs for file name
		$flat_clean = sanitize_file_name( $flat_no );
		$name_clean = sanitize_file_name( $name );
		$ext = pathinfo( $file['name'], PATHINFO_EXTENSION );
		
		// Convention: flat-number-name.ext
		$filename = sprintf( '%s-%s.%s', $flat_clean, $name_clean, $ext );
		$destination = $this->base_dir . $filename;

		// Move file
		if ( move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return $this->base_url . $filename;
		}

		return new WP_Error( 'upload_failed', 'Failed to save profile photo.' );
	}
}
