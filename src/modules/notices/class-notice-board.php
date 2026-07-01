<?php
/**
 * Module: Notice Board
 * Handles Public/Private Notices with modern AJAX Support.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Notice_Board {

	private $db;
	private $drive;

	public function __construct() {
		$this->db = new SNESTX51_DB_Router();
		$this->drive = new SNESTX51_Drive_Manager();
		
		add_action( 'admin_menu', array( $this, 'register_menu' ) );

        // AJAX Handlers
        add_action( 'wp_ajax_snestx51_add_notice', array( $this, 'ajax_save_notice' ) );
        add_action( 'wp_ajax_snestx51_update_notice', array( $this, 'ajax_save_notice' ) );
        add_action( 'wp_ajax_snestx51_delete_notice', array( $this, 'ajax_delete_notice' ) );
        add_action( 'wp_ajax_snestx51_toggle_pin', array( $this, 'ajax_toggle_pin' ) );
        add_action( 'wp_ajax_snestx51_get_notice', array( $this, 'ajax_get_notice' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'snestx51-settings',
			'Notice Board',
			'Notices',
			'read', // Granular check inside render_page
			'snestx51-notices',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'notices_view' ) ) {
            wp_die( 'You do not have permission to view notices.' );
        }
		SNESTX51_Admin_App::render_view('notices');
	}

    /**
     * AJAX: Get Single Notice.
     */
    public function ajax_get_notice() {
        check_ajax_referer('snestx51_notice_nonce', '_wpnonce');
        
        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $notices = $this->db->get('notices');
        
        foreach($notices as $n) {
            if($n['id'] === $id) {
                wp_send_json_success($n);
            }
        }
        
        wp_send_json_error(['message' => 'Notice not found']);
    }

    /**
     * AJAX: Save/Update Notice.
     */
    public function ajax_save_notice() {
        check_ajax_referer('snestx51_notice_nonce', '_wpnonce');

        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'notices_manage' ) ) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $id = !empty($_POST['id']) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : uniqid('ntc_');
        $is_update = !empty($_POST['id']);
        $new_status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

        $data = array(
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'content' => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '', 
			'audience' => isset( $_POST['audience'] ) ? sanitize_text_field( wp_unslash( $_POST['audience'] ) ) : '',
			'urgency' => isset( $_POST['urgency'] ) ? sanitize_text_field( wp_unslash( $_POST['urgency'] ) ) : '',
            'status'         => $new_status,
            'is_pinned'      => !empty($_POST['is_pinned']) ? 1 : 0,
			'expiry_date'    => !empty($_POST['expiry_date']) ? sanitize_text_field( wp_unslash( $_POST['expiry_date'] ) ) : null,
		);

        if (!$is_update) {
            $data['id'] = $id;
            $data['created_at'] = current_time( 'mysql' );
        }

		// Handle Attachment
		if ( isset( $_FILES['attachment']['size'] ) && $_FILES['attachment']['size'] > 0 ) {
			$folder = $this->drive->get_system_folder( 'Notices' );
			if ( ! is_wp_error( $folder ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated and processed securely.
				$url = $this->drive->upload_to_folder( $folder, $_FILES['attachment'] );
				if ( ! is_wp_error( $url ) ) {
					$data['attachment_url'] = is_string( $url ) ? $url : 'Uploaded';
				}
			}
		}

        if ($is_update) {
            $this->db->update('notices', $data, ['id' => $id]);
            $msg = 'Notice updated successfully.';
        } else {
            $this->db->insert( 'notices', $data );
            $msg = 'Notice published successfully.';
        }

        // Trigger Notifications if status changed to 'published' (or if new and published)
        if ($new_status === 'published') {
            $this->broadcast_notice(array_merge($data, ['id' => $id]));
        }

        wp_send_json_success(['message' => $msg, 'id' => $id]);
    }

    /**
     * AJAX: Delete Notice.
     */
    public function ajax_delete_notice() {
        check_ajax_referer('snestx51_delete_notice_nonce', '_wpnonce');
        
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'notices_manage' ) ) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $this->db->delete('notices', ['id' => $id]);
        
        wp_send_json_success(['message' => 'Notice deleted permanently.']);
    }

    /**
     * AJAX: Toggle Pin Status.
     */
    public function ajax_toggle_pin() {
        check_ajax_referer('snestx51_notice_nonce', '_wpnonce');
        
        $rbac = new SNESTX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'notices_manage' ) ) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
        $pinned = !empty($_POST['pinned']) ? 1 : 0;

        $this->db->update('notices', ['is_pinned' => $pinned], ['id' => $id]);
        
        wp_send_json_success(['message' => $pinned ? 'Notice pinned.' : 'Notice unpinned.']);
    }

    /**
     * Broadcast Notice to Audience.
     */
    private function broadcast_notice($notice) {
        $residents = $this->db->get('residents');
        $audience = $notice['audience'];
        $dispatcher = Society_NestX::get_instance()->notifications;

        if (!$dispatcher) return;

        foreach ($residents as $r) {
            // More robust match for audience
            $resident_type = strtolower($r['type'] ?? 'owner');
            if ($audience === 'Owners' && $resident_type !== 'owner') continue;
            if ($audience === 'Tenants' && $resident_type !== 'tenant') continue;

            if (empty($r['wp_user_id'])) continue;

            $dispatcher->trigger('notice_published', $r['wp_user_id'], [
                'title'   => $notice['title'],
                'content' => wp_trim_words(wp_strip_all_tags($notice['content']), 20),
                'urgency' => ucfirst($notice['urgency']),
                'time'    => current_time('mysql')
            ]);
        }
    }
}
