<?php
/**
 * Class: Admin Requests
 * Handles the "Requests" admin page and generalized approval logic.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Admin_Requests {

	private $db;

	public function __construct() {
		$this->db = new SHUBX51_DB_Router();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_shubx51_approve_request', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_shubx51_reject_request', array( $this, 'handle_reject' ) );
	}

	public function register_menu() {
		// Get pending count for badge
		$requests = $this->db->get( 'requests' );
		$pending_count = 0;
		foreach ( $requests as $req ) {
			if ( isset( $req['status'] ) && $req['status'] === 'pending' ) {
				$pending_count++;
			}
		}

		$badge = $pending_count > 0 ? " <span class='update-plugins count-$pending_count'><span class='plugin-count'>" . number_format_i18n( $pending_count ) . "</span></span>" : "";

		add_submenu_page(
			'shubx51-settings',
			'Approval Requests',
			'Requests' . $badge,
			'read', // RBAC checked in render_page and handle functions
			'shubx51-requests',
			array( $this, 'render_page' )
		);
	}

	public function handle_approve() {
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SHUBX51_RBAC_Manager();
		
		// For finance/accounts, we need specific roles. For other modules, we check the request type.
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can('manage_options') ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'shubx51_request_action' );

		$request_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$redirect   = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
		
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SHUBX51_Request_Manager();
		$result = $rm->approve_request( $request_id );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		if ( $redirect ) {
			wp_safe_redirect( $redirect );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=shubx51-requests&status=approved' ) );
		}
		exit;
	}

	public function handle_reject() {
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SHUBX51_RBAC_Manager();
		
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can('manage_options') ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'shubx51_request_action' );

		$request_id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$note       = isset( $_POST['admin_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['admin_note'] ) ) : '';
		$redirect   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
		$rm = new SHUBX51_Request_Manager();
		$result = $rm->reject_request( $request_id, $note );

		if ( $redirect ) {
			wp_safe_redirect( $redirect );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=shubx51-requests&status=rejected' ) );
		}
		exit;
	}

	public function render_page() {
		require_once SHUBX51_PLUGIN_DIR . 'includes/class-rbac-manager.php';
		$rbac = new SHUBX51_RBAC_Manager();
		if ( ! $rbac->has_capability( get_current_user_id(), 'finance_manage' ) && ! current_user_can('manage_options') ) {
			wp_die( 'You do not have permission to access the Requests page.' );
		}
        $requests = $this->db->get( 'requests' );
        
        // Decorate requests with original data for comparison view
        require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
        $rm = new SHUBX51_Request_Manager();
        
        $decorated_requests = array_map( function( $req ) use ( $rm ) {
            if ( isset($req['status']) && $req['status'] === 'pending' && isset($req['request_type']) && $req['request_type'] === 'edit' ) {
                $req['original_data'] = $rm->get_original_data( $req );
            }
            return $req;
        }, $requests );

		SHUBX51_Admin_App::render_view( 'requests', array( 'requests' => $decorated_requests ) );
	}
}
