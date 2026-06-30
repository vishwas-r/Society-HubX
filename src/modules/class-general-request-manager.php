<?php
/**
 * Module: General Request Manager
 * Handles generic resident requests (CCTV, Swimming Pool, Play-time, etc.).
 *
 * @package Society_GoVernX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_General_Request_Manager implements SGVX51_Module {

	private $db;

	public function __construct() {
		$this->db = new SGVX51_DB_Router();
        
        // Register Module
        add_filter( 'sgvx51_get_module_general', array( $this, 'get_instance' ) );
        
        // AJAX Handlers
        add_action( 'wp_ajax_sgvx51_submit_general_request', array( $this, 'handle_submit_request' ) );
	}

	public function get_instance() {
		return $this;
	}

	public function get_module_slug() {
		return 'general';
	}

	/**
	 * Execute approved request.
	 */
	public function execute_request( $action, $payload ) {
		return true; 
	}

	/**
	 * Handle request submission from Resident (Frontend).
	 */
	public function handle_submit_request() {
		check_ajax_referer( 'sgvx51_frontend_nonce' ); // Standard frontend nonce for this plugin
		
		$category = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '';
		$comments = isset( $_POST['comments'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comments'] ) ) : '';
		
		if ( empty( $category ) || empty( $comments ) ) {
			wp_send_json_error( ['message' => 'Please select a category and provide details.'] );
		}

		$user_id = get_current_user_id();
		$resident = $this->db->get_resident_by_wp_id( $user_id );

		$payload = [
			'category'      => $category,
			'comments'      => $comments,
			'resident_id'   => $user_id,
			'flat_no'       => $resident ? Society_GoVernX::get_instance()->db->get_flat_display_name($resident['flat_no']) : 'Unknown',
			'resident_name' => $resident ? $resident['name'] : 'Unknown'
		];

		require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SGVX51_Request_Manager();
		$res = $rm->create_request( 'general', 'general_request', $payload );

		if ( is_wp_error( $res ) ) {
			wp_send_json_error( ['message' => $res->get_error_message()] );
		}

		wp_send_json_success( ['message' => 'Your request has been submitted for review.'] );
	}
}

new SGVX51_General_Request_Manager();
