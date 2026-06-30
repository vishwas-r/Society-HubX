<?php
/**
 * Module: Staff Manager
 * Handles Maintenance Staff & Daily Help.
 *
 * @package Society_GoVernX
 */

if (!defined('ABSPATH')) {
    exit;
}

class SGVX51_Staff_Manager implements SGVX51_Module
{

    private $db;
    private $drive;

    public function __construct()
    {
        $this->db = new SGVX51_DB_Router();
        $this->drive = new SGVX51_Drive_Manager();

        add_action('admin_menu', array($this, 'register_menu'), 200);

        // AJAX
        add_action('wp_ajax_sgvx51_add_staff', array($this, 'handle_add_staff'));
        add_action('wp_ajax_sgvx51_edit_staff', array($this, 'handle_edit_staff'));
        add_action('wp_ajax_sgvx51_delete_staff', array($this, 'handle_delete_staff'));
        add_action('wp_ajax_sgvx51_restore_staff', array($this, 'handle_restore_staff'));

        add_action('admin_post_sgvx51_add_staff', array($this, 'handle_add_staff'));
        add_action('admin_post_sgvx51_edit_staff', array($this, 'handle_edit_staff'));
        add_action('admin_post_sgvx51_delete_staff', array($this, 'handle_delete_staff'));
        add_action('admin_post_sgvx51_restore_staff', array($this, 'handle_restore_staff'));

        // Self-Heal Schema (Ensure columns exist)
        if (is_admin()) {
            $this->db->verify_column('daily_help', 'sex', 'varchar(10) DEFAULT "" NOT NULL');
            $this->db->verify_column('daily_help', 'visiting_hours', 'varchar(50) DEFAULT "" NOT NULL');
            $this->db->verify_column('daily_help', 'created_by', 'bigint(20) DEFAULT 0 NOT NULL');
            $this->db->verify_column('daily_help', 'flat_no', 'varchar(50) DEFAULT "" NOT NULL'); // Legacy flat link
            $this->db->verify_column('daily_help', 'category', 'varchar(50) DEFAULT "" NOT NULL');
            $this->db->verify_column('daily_help', 'id_proof', 'text DEFAULT NULL'); // Separate ID Proof
        }

        // Register Module
        add_filter('sgvx51_get_module_daily_help', array($this, 'get_instance'));
    }

    public function get_instance()
    {
        return $this;
    }

    public function get_module_slug()
    {
        return 'daily_help';
    }

    public function execute_request($action, $payload)
    {
        $payload = (array)$payload;
        if ($action === 'add') {
            $id = $payload['id'] ?? '';
            $all = $this->db->get('daily_help');
            $exists = false;
            foreach ($all as $s) {
                if (($s['id'] ?? '') === $id) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                return $this->db->update('daily_help', ['status' => 'approved'], ['id' => $id]);
            }
            else {
                return $this->perform_add_staff($payload);
            }
        }
        elseif ($action === 'edit') {
            return $this->perform_edit_staff($payload);
        }
        elseif ($action === 'delete') {
            return $this->perform_delete_staff($payload);
        }
        return new WP_Error('invalid_action', 'Unknown action: ' . $action);
    }

    private function perform_add_staff($data)
    {
        $id = isset($data['id']) ? $data['id'] : uniqid('staff_');
        $db_data = array(
            'name' => sanitize_text_field($data['name']),
            'role' => sanitize_text_field($data['role']),
            'category' => isset($data['category']) ? sanitize_text_field($data['category']) : 'Support Staff',
            'phone' => sanitize_text_field($data['phone']),
            'sex' => sanitize_text_field($data['sex']),
            'visiting_hours' => sanitize_text_field($data['visiting_hours']),
            'created_at' => current_time('mysql'),
            'id' => $id,
            'status' => isset($data['status']) ? $data['status'] : 'approved',
            'flat_no' => !empty($data['flats_served']) && is_array($data['flats_served']) ? sanitize_text_field($data['flats_served'][0]) : (isset($data['flat_no']) ? sanitize_text_field($data['flat_no']) : ''),
            'profile_photo' => isset($data['profile_photo']) ? esc_url_raw($data['profile_photo']) : '',
            'id_proof' => isset($data['id_proof']) ? esc_url_raw($data['id_proof']) : ''
        );

        // Handle ID Proof Upload (Document)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed in handle_add_staff caller method.
        if (!empty($_FILES['id_proof']) && !empty($_FILES['id_proof']['name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated inside upload_file.
            $uploaded = $this->drive->upload_file('staff_docs', $_FILES['id_proof']);
            if (!is_wp_error($uploaded)) {
                $db_data['id_proof'] = $uploaded;
            }
        }

        // Handle Profile Photo Upload (Avatar)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed in handle_add_staff caller method.
        if (!empty($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {
            $media = new SGVX51_Media_Manager();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated inside upload_profile_photo.
            $photo_url = $media->upload_profile_photo($_FILES['profile_photo'], 'staff', $db_data['name'], 'staffs');
            if (!is_wp_error($photo_url)) {
                $db_data['profile_photo'] = $photo_url;
            }
        }

        $res = $this->db->insert('daily_help', $db_data);
        if (!is_wp_error($res) && isset($data['flats_served'])) {
            $this->db->save_relations('staff_flats', 'staff_id', $id, 'flat_id', $data['flats_served']);
        }
        return $res;
    }

    private function perform_edit_staff($data)
    {
        $id = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : (isset($data['id']) ? $data['id'] : '');
        if (!$id)
            return new WP_Error('missing_id', 'Staff ID Missing');

        // Fetch Existing
        $existing = [];
        $all = $this->db->get('daily_help');
        foreach ($all as $s) {
            if (isset($s['id']) && $s['id'] === $id) {
                $existing = $s;
                break;
            }
        }

        if (empty($existing)) {
            return new WP_Error('not_found', 'Staff member not found for update.');
        }

        // LEGACY MIGRATION: If id_proof is empty but profile_photo exists (and we are about to potentially overwrite it),
        // assume the existing profile_photo is the ID Doc (as per previous system).
        // CRITICAL FIX: Only migrate if it looks like a legacy doc (stored in /docs/) and NOT a new avatar (/profile-pics/).
        if (empty($existing['id_proof']) && !empty($existing['profile_photo'])) {
            if (strpos($existing['profile_photo'], '/profile-pics/') === false) {
                $existing['id_proof'] = $existing['profile_photo'];
                $existing['profile_photo'] = ''; // Clear normalized slot only if we moved it
            }
        }

        $update_data = array(
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : ($existing['name'] ?? ''),
            'role' => isset($data['role']) ? sanitize_text_field($data['role']) : ($existing['role'] ?? ''),
            'category' => isset($data['category']) ? sanitize_text_field($data['category']) : ($existing['category'] ?? 'Support Staff'),
            'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : ($existing['phone'] ?? ''),
            'sex' => isset($data['sex']) ? sanitize_text_field($data['sex']) : ($existing['sex'] ?? ''),
            'visiting_hours' => isset($data['visiting_hours']) ? sanitize_text_field($data['visiting_hours']) : ($existing['visiting_hours'] ?? ''),
            // Preserve other fields
            'status' => 'approved', // Reset to approved upon edit approval or admin edit
            'created_by' => $existing['created_by'] ?? '',
            'flat_no' => !empty($data['flats_served']) && is_array($data['flats_served']) ? sanitize_text_field($data['flats_served'][0]) : (isset($data['flat_no']) ? sanitize_text_field($data['flat_no']) : ($existing['flat_no'] ?? '')),
            'profile_photo' => isset($data['profile_photo']) ? esc_url_raw($data['profile_photo']) : ($existing['profile_photo'] ?? ''),
            'id_proof' => isset($data['id_proof']) ? esc_url_raw($data['id_proof']) : ($existing['id_proof'] ?? '')
        );

        // Handle ID Proof Upload (Document)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed in handle_edit_staff caller method.
        if (!empty($_FILES['id_proof']) && !empty($_FILES['id_proof']['name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated inside upload_file.
            $uploaded = $this->drive->upload_file('staff_docs', $_FILES['id_proof']);
            if (!is_wp_error($uploaded)) {
                $update_data['id_proof'] = $uploaded;
            }
        }

        // Handle Profile Photo Upload (Avatar)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check is performed in handle_edit_staff caller method.
        if (!empty($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {
            $media = new SGVX51_Media_Manager();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is validated inside upload_profile_photo.
            $photo_url = $media->upload_profile_photo($_FILES['profile_photo'], 'staff', $update_data['name'], 'staffs');
            if (!is_wp_error($photo_url)) {
                $update_data['profile_photo'] = $photo_url;
            }
        }

        $res = $this->db->update('daily_help', $update_data, ['id' => $id]);
        if (!is_wp_error($res) && isset($data['flats_served'])) {
            $this->db->save_relations('staff_flats', 'staff_id', $id, 'flat_id', $data['flats_served']);
        }
        return $res;
    }

    private function perform_delete_staff($data)
    {
        $id = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : (isset($data['id']) ? $data['id'] : '');
        if (!$id)
            return new WP_Error('missing_id', 'Staff ID Missing');
        return $this->db->update('daily_help', ['status' => 'archived'], ['id' => $id]);
    }

    public function handle_restore_staff()
    {
        if (wp_doing_ajax()) {
            check_ajax_referer('sgvx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('sgvx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $id = isset($_POST['staff_id']) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : (isset($_GET['staff_id']) ? sanitize_text_field( wp_unslash( $_GET['staff_id'] ) ) : '');

        $rbac = new SGVX51_RBAC_Manager();
        if ($rbac->has_capability( get_current_user_id(), 'staff_manage' )) {
            $this->db->update('daily_help', array('status' => 'approved'), array('id' => $id));

            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $rm->log_audit('staff_restored', 'daily_help', $id, "Staff ID: $id");

            if (wp_doing_ajax()) {
                // Clean all buffers
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                wp_send_json_success(['message' => 'Staff member restored successfully']);
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=updated'));
        exit;
    }

    public function register_menu()
    {
        add_submenu_page(
            'sgvx51-settings',
            'Staff & Help',
            'Staff & Help',
            'read', // Granular check inside render_page
            'sgvx51-staff',
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        $rbac = new SGVX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'staff_view' ) ) {
            wp_die( 'You do not have permission to view staff records.' );
        }

        $rm = new SGVX51_Request_Manager();
        $unified = $rm->get_unified_data('daily_help', 'daily_help', '', true);
        $flats = $this->db->get('flats');

        SGVX51_Admin_App::render_view('staff', [
            'staff' => $unified['active'],
            'pending' => $unified['pending'],
            'archived' => array_filter($unified['active'], function ($s) {
            return isset($s['status']) && $s['status'] === 'archived';
        }),
            'flats' => $flats
        ]);
    }

    public function handle_add_staff()
    {
        if (wp_doing_ajax()) {
            ob_start();
            check_ajax_referer('sgvx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('sgvx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $_POST['id'] = uniqid('staff_');

        $rbac = new SGVX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            $res = $this->perform_add_staff($_POST);
            if (wp_doing_ajax()) {
                // Aggressive Clean
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                if (is_wp_error($res)) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff added successfully']);
                exit;
            }
        }
        else {
            $_POST['status'] = 'pending';
            $this->perform_add_staff($_POST);

            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
            $res = $rm->create_request('daily_help', 'add', $sanitized_post, $sanitized_post['id'], 'daily_help', $sanitized_post['flat_no'] ?? '');
            if (wp_doing_ajax()) {
                $debug = ob_get_clean();
                if (!empty($debug))
                    error_log('SGVX Staff Add Request Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

                // Aggressive Clean
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                if (is_wp_error($res)) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff added and submitted for approval']);
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=added'));
        exit;
    }

    public function handle_edit_staff()
    {
        if (wp_doing_ajax()) {
            ob_start();
            check_ajax_referer('sgvx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('sgvx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $id = isset( $_POST['staff_id'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : '';

        $rbac = new SGVX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $sync_res = $rm->approve_request($id);

            if (!is_wp_error($sync_res)) {
                if (wp_doing_ajax()) {
                    ob_get_clean();
                    // Aggressive Clean
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    wp_send_json_success(['message' => 'Staff updated and request synchronized']);
                }
                else {
                    wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=updated'));
                }
                exit;
            }

            $res = $this->perform_edit_staff($_POST);

            if (wp_doing_ajax()) {
                // Aggressive Clean
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                if (is_wp_error($res)) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Staff member updated successfully']);
                exit;
            }
        }
        else {
            $rm = new SGVX51_Request_Manager();
            $sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
            $res = $rm->create_request('daily_help', 'edit', $sanitized_post, $id, 'daily_help', $sanitized_post['flat_no'] ?? '');
            if (wp_doing_ajax()) {
                $debug = ob_get_clean();
                if (!empty($debug))
                    error_log('SGVX Staff Edit Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

                // Aggressive Clean
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                if (is_wp_error($res)) {
                    wp_send_json_error(['message' => $res->get_error_message()]);
                }
                wp_send_json_success(['message' => 'Update request submitted for approval']);
                exit;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=updated'));
        exit;
    }

    public function handle_delete_staff()
    {
        if (wp_doing_ajax()) {
            check_ajax_referer('sgvx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('sgvx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $id = isset( $_POST['staff_id'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : '';

        $rbac = new SGVX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $sync_res = $rm->approve_request($id);

            if (!is_wp_error($sync_res)) {
                if (wp_doing_ajax()) {
                    // Aggressive Clean
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    wp_send_json_success(['message' => 'Staff record archived and request synchronized']);
                }
                else {
                    wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=deleted'));
                }
                exit;
            }

            $res = $this->perform_delete_staff(['id' => $id]);
        }
        else {
            require_once SGVX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SGVX51_Request_Manager();
            $sanitized_post = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
            $res = $rm->create_request('daily_help', 'delete', ['staff_id' => $id, 'id' => $id], $id, 'daily_help', $sanitized_post['flat_no'] ?? '');
            if (wp_doing_ajax()) {
                // Aggressive Clean
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                if (is_wp_error($res))
                    wp_send_json_error(['message' => $res->get_error_message()]);
                wp_send_json_success(['message' => 'Deletion request submitted for approval']);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sgvx51-staff&status=deleted'));
        exit;
    }
}
