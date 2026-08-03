<?php
/**
 * Module: Staff Manager
 * Handles Maintenance Staff & Daily Help.
 *
 * @package SHUBX51_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class SHUBX51_Staff_Manager implements SHUBX51_Module
{

    private $db;
    private $drive;

    public function __construct()
    {
        $this->db = new SHUBX51_DB_Router();
        $this->drive = new SHUBX51_Drive_Manager();

        add_action('admin_menu', array($this, 'register_menu'), 200);
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX
        add_action('wp_ajax_shubx51_add_staff', array($this, 'handle_add_staff'));
        add_action('wp_ajax_shubx51_edit_staff', array($this, 'handle_edit_staff'));
        add_action('wp_ajax_shubx51_delete_staff', array($this, 'handle_delete_staff'));
        add_action('wp_ajax_shubx51_restore_staff', array($this, 'handle_restore_staff'));
        add_action('wp_ajax_shubx51_mark_attendance', array($this, 'handle_mark_attendance'));
        add_action('wp_ajax_shubx51_raise_concern', array($this, 'handle_raise_concern'));
        add_action('wp_ajax_shubx51_get_attendance_report', array($this, 'handle_get_attendance_report'));

        add_action('admin_post_shubx51_add_staff', array($this, 'handle_add_staff'));
        add_action('admin_post_shubx51_edit_staff', array($this, 'handle_edit_staff'));
        add_action('admin_post_shubx51_delete_staff', array($this, 'handle_delete_staff'));
        add_action('admin_post_shubx51_restore_staff', array($this, 'handle_restore_staff'));
        add_action('admin_post_shubx51_mark_attendance', array($this, 'handle_mark_attendance'));
        add_action('admin_post_shubx51_raise_concern', array($this, 'handle_raise_concern'));

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
        add_filter('shubx51_get_module_daily_help', array($this, 'get_instance'));
    }

    public function get_instance()
    {
        return $this;
    }

    public function register_rest_routes() {
        register_rest_route('shubx51/v1', '/biometric-sync', array(
            'methods'  => 'POST',
            'callback' => array($this, 'handle_biometric_sync'),
            'permission_callback' => '__return_true', // In production, add token auth
        ));
    }

    public function handle_biometric_sync($request) {
        $params = $request->get_json_params();
        if (empty($params['staff_id']) || empty($params['status'])) {
            return new WP_Error('missing_params', 'staff_id and status are required', array('status' => 400));
        }

        $staff_id = sanitize_text_field($params['staff_id']);
        $status = sanitize_text_field($params['status']);
        $date = !empty($params['date']) ? sanitize_text_field($params['date']) : current_time('Y-m-d');
        
        $data = array(
            'staff_id' => $staff_id,
            'date' => $date,
            'status' => $status,
            'time_in' => $status === 'present' ? current_time('H:i:s') : null,
            'marked_by' => 0, // 0 indicates system/biometric
            'created_at' => current_time('mysql')
        );

        $res = $this->db->insert('staff_attendance', $data);

        if (is_wp_error($res)) {
            return new WP_Error('db_error', $res->get_error_message(), array('status' => 500));
        }

        return rest_ensure_response(array('success' => true, 'message' => 'Attendance synced successfully'));
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

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check is performed in handle_add_staff caller method; $_FILES is sanitized via sanitize_file_array.
        if (!empty($_FILES['id_proof']) && !empty($_FILES['id_proof']['name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is sanitized via sanitize_file_array.
            $clean_file = $this->sanitize_file_array( $_FILES['id_proof'] );
            $uploaded = $this->drive->upload_file('staff_docs', $clean_file);
            if (!is_wp_error($uploaded)) {
                $db_data['id_proof'] = $uploaded;
            }
        }

        // Handle Profile Photo Upload (Avatar)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check is performed in handle_add_staff caller method; $_FILES is sanitized via sanitize_file_array.
        if (!empty($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {
            $media = new SHUBX51_Media_Manager();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is sanitized via sanitize_file_array.
            $clean_photo = $this->sanitize_file_array( $_FILES['profile_photo'] );
            $photo_url = $media->upload_profile_photo($clean_photo, 'staff', $db_data['name'], 'staffs');
            if (!is_wp_error($photo_url)) {
                $db_data['profile_photo'] = $photo_url;
            }
        }

        $res = $this->db->insert('daily_help', $db_data);
        if (!is_wp_error($res) && isset($data['flats_served']) && is_array($data['flats_served'])) {
            $sanitized_flats = array_map('sanitize_text_field', $data['flats_served']);
            $this->db->save_relations('staff_flats', 'staff_id', $id, 'flat_id', $sanitized_flats);
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

        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check is performed in handle_edit_staff caller method; $_FILES is sanitized via sanitize_file_array.
        if (!empty($_FILES['id_proof']) && !empty($_FILES['id_proof']['name'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is sanitized via sanitize_file_array.
            $clean_file = $this->sanitize_file_array( $_FILES['id_proof'] );
            $uploaded = $this->drive->upload_file('staff_docs', $clean_file);
            if (!is_wp_error($uploaded)) {
                $update_data['id_proof'] = $uploaded;
            }
        }

        // Handle Profile Photo Upload (Avatar)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check is performed in handle_edit_staff caller method; $_FILES is sanitized via sanitize_file_array.
        if (!empty($_FILES['profile_photo']) && !empty($_FILES['profile_photo']['name'])) {
            $media = new SHUBX51_Media_Manager();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES is sanitized via sanitize_file_array.
            $clean_photo = $this->sanitize_file_array( $_FILES['profile_photo'] );
            $photo_url = $media->upload_profile_photo($clean_photo, 'staff', $update_data['name'], 'staffs');
            if (!is_wp_error($photo_url)) {
                $update_data['profile_photo'] = $photo_url;
            }
        }

        $res = $this->db->update('daily_help', $update_data, ['id' => $id]);
        if (!is_wp_error($res) && isset($data['flats_served']) && is_array($data['flats_served'])) {
            $sanitized_flats = array_map('sanitize_text_field', $data['flats_served']);
            $this->db->save_relations('staff_flats', 'staff_id', $id, 'flat_id', $sanitized_flats);
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
            check_ajax_referer('shubx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('shubx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $id = isset($_POST['staff_id']) ? sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) : (isset($_GET['staff_id']) ? sanitize_text_field( wp_unslash( $_GET['staff_id'] ) ) : '');

        $rbac = new SHUBX51_RBAC_Manager();
        if ($rbac->has_capability( get_current_user_id(), 'staff_manage' )) {
            $this->db->update('daily_help', array('status' => 'approved'), array('id' => $id));

            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
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

        wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=updated'));
        exit;
    }

    public function register_menu()
    {
        add_submenu_page(
            'shubx51-settings',
            'Staff & Help',
            'Staff & Help',
            'read', // Granular check inside render_page
            'shubx51-staff',
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        $rbac = new SHUBX51_RBAC_Manager();
        if ( ! $rbac->has_capability( get_current_user_id(), 'staff_view' ) ) {
            wp_die( 'You do not have permission to view staff records.' );
        }

        $rm = new SHUBX51_Request_Manager();
        $unified = $rm->get_unified_data('daily_help', 'daily_help', '', true);
        $flats = $this->db->get('flats');

        SHUBX51_Admin_App::render_view('staff', [
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
            check_ajax_referer('shubx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('shubx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $post_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
        $post_data['id'] = uniqid('staff_');

        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            $res = $this->perform_add_staff($post_data);
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
            $post_data['status'] = 'pending';
            $this->perform_add_staff($post_data);

            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $res = $rm->create_request('daily_help', 'add', $post_data, $post_data['id'], 'daily_help', $post_data['flat_no'] ?? '');
            if (wp_doing_ajax()) {
                $debug = ob_get_clean();
                if (!empty($debug))
                    error_log('SHUBX Staff Add Request Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

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

        wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=added'));
        exit;
    }

    public function handle_edit_staff()
    {
        if (wp_doing_ajax()) {
            ob_start();
            check_ajax_referer('shubx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('shubx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $post_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
        $id = isset( $post_data['staff_id'] ) ? $post_data['staff_id'] : '';

        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
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
                    wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=updated'));
                }
                exit;
            }

            $res = $this->perform_edit_staff($post_data);

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
            $rm = new SHUBX51_Request_Manager();
            $res = $rm->create_request('daily_help', 'edit', $post_data, $id, 'daily_help', $post_data['flat_no'] ?? '');
            if (wp_doing_ajax()) {
                $debug = ob_get_clean();
                if (!empty($debug))
                    error_log('SHUBX Staff Edit Debug: ' . $debug); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.

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

        wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=updated'));
        exit;
    }

    public function handle_delete_staff()
    {
        if (wp_doing_ajax()) {
            check_ajax_referer('shubx51_staff_nonce');
        }
        else {
            if (!check_admin_referer('shubx51_staff_nonce'))
                wp_die('Security check failed');
        }

        $post_data = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
        $id = isset( $post_data['staff_id'] ) ? $post_data['staff_id'] : '';

        $rbac = new SHUBX51_RBAC_Manager();
        $has_manage = $rbac->has_capability( get_current_user_id(), 'staff_manage' );

        // IF ADMIN or has staff_manage: Immediate
        if ($has_manage) {
            // 1. Synchronize with Request Manager if a pending request exists
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
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
                    wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=deleted'));
                }
                exit;
            }

            $res = $this->perform_delete_staff(['id' => $id]);
        }
        else {
            require_once SHUBX51_PLUGIN_DIR . 'includes/class-request-manager.php';
            $rm = new SHUBX51_Request_Manager();
            $res = $rm->create_request('daily_help', 'delete', ['staff_id' => $id, 'id' => $id], $id, 'daily_help', $post_data['flat_no'] ?? '');
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

        wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=deleted'));
        exit;
    }

    private function sanitize_file_array( $file ) {
        if ( empty( $file ) || ! is_array( $file ) || empty( $file['name'] ) ) {
            return $file;
        }
        return array(
            'name'     => sanitize_file_name( wp_unslash( $file['name'] ) ),
            'type'     => sanitize_text_field( $file['type'] ),
            'tmp_name' => sanitize_text_field( $file['tmp_name'] ),
            'error'    => isset( $file['error'] ) ? intval( $file['error'] ) : 0,
            'size'     => isset( $file['size'] ) ? intval( $file['size'] ) : 0,
        );
    }
    public function handle_mark_attendance() {
        if (wp_doing_ajax()) {
            check_ajax_referer('shubx51_staff_nonce');
        } else {
            if (!check_admin_referer('shubx51_staff_nonce')) wp_die('Security check failed');
        }

        $staff_id = isset($_POST['staff_id']) ? sanitize_text_field(wp_unslash($_POST['staff_id'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'present';
        $date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : current_time('Y-m-d');
        
        if (!$staff_id) {
            if (wp_doing_ajax()) wp_send_json_error(['message' => 'Staff ID is required']);
            wp_die('Staff ID is required');
        }

        $time_in = $status === 'present' ? current_time('H:i:s') : null;
        $time_out = null; // Can be updated later for check-out

        $data = array(
            'staff_id' => $staff_id,
            'date' => $date,
            'status' => $status,
            'time_in' => $time_in,
            'marked_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );

        $res = $this->db->insert('staff_attendance', $data);

        if (wp_doing_ajax()) {
            if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()]);
            wp_send_json_success(['message' => 'Attendance marked successfully']);
        } else {
            wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=attendance_marked'));
        }
        exit;
    }

    public function handle_raise_concern() {
        if (wp_doing_ajax()) {
            check_ajax_referer('shubx51_staff_nonce');
        } else {
            if (!check_admin_referer('shubx51_staff_nonce')) wp_die('Security check failed');
        }

        $staff_id = isset($_POST['staff_id']) ? sanitize_text_field(wp_unslash($_POST['staff_id'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        
        if (!$staff_id || !$description) {
            if (wp_doing_ajax()) wp_send_json_error(['message' => 'Staff ID and description are required']);
            wp_die('Staff ID and description are required');
        }

        // Fetch staff details to determine routing
        $staffs = $this->db->get('daily_help');
        $staff = null;
        foreach($staffs as $s) {
            if($s['id'] === $staff_id) {
                $staff = $s;
                break;
            }
        }

        if (!$staff) {
            if (wp_doing_ajax()) wp_send_json_error(['message' => 'Staff not found']);
            wp_die('Staff not found');
        }

        // Determine if society or flat staff
        $type = (stripos($staff['category'], 'society') !== false || empty($staff['flat_no'])) ? 'society' : 'flat';
        $flat_no = $type === 'flat' ? $staff['flat_no'] : '';

        $concern_id = uniqid('concern_');
        $data = array(
            'id' => $concern_id,
            'staff_id' => $staff_id,
            'type' => $type,
            'flat_no' => $flat_no,
            'description' => $description,
            'status' => 'open',
            'raised_by' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );

        $res = $this->db->insert('staff_concerns', $data);

        // Notify
        if (class_exists('SHUBX51_Plugin')) {
            $shubx = SHUBX51_Plugin::get_instance();
            if ($type === 'society') {
                // Alert association members
                $shubx->notifications->trigger('society_staff_concern', 0, [
                    'staff_name' => $staff['name'],
                    'description' => $description
                ], 0, 'admin'); 
            } else {
                // Alert flat members
                $residents = $this->db->get('residents');
                foreach($residents as $r) {
                    if($r['flat_no'] === $flat_no && $r['status'] === 'approved') {
                        $shubx->notifications->trigger('flat_staff_concern', $r['wp_user_id'], [
                            'staff_name' => $staff['name'],
                            'description' => $description,
                            'resident_name' => $r['name']
                        ], $r['wp_user_id']);
                    }
                }
            }
        }

        if (wp_doing_ajax()) {
            if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()]);
            wp_send_json_success(['message' => 'Concern raised successfully']);
        } else {
            wp_safe_redirect(admin_url('admin.php?page=shubx51-staff&status=concern_raised'));
        }
        exit;
    }

    public function handle_get_attendance_report() {
        if (!check_ajax_referer('shubx51_staff_nonce', false, false)) {
            wp_send_json_error(['message' => 'Security check failed']);
        }
        $month = isset($_POST['month']) ? sanitize_text_field(wp_unslash($_POST['month'])) : wp_date('Y-m');
        
        $attendance = $this->db->get('staff_attendance');
        $report_data = [];
        $staff_totals = [];

        if (!empty($attendance)) {
            foreach ($attendance as $record) {
                if (strpos($record['date'], $month) === 0) {
                    $sid = $record['staff_id'];
                    if (!isset($staff_totals[$sid])) {
                        $staff_totals[$sid] = ['present' => 0, 'absent' => 0, 'leave' => 0];
                    }
                    if ($record['status'] === 'present') {
                        $staff_totals[$sid]['present']++;
                    } elseif ($record['status'] === 'absent') {
                        $staff_totals[$sid]['absent']++;
                    } elseif ($record['status'] === 'leave') {
                        $staff_totals[$sid]['leave']++;
                    }
                }
            }
        }

        $staffs = $this->db->get('daily_help');
        if (!empty($staffs)) {
            foreach ($staffs as $staff) {
                $sid = $staff['id'];
                if (isset($staff_totals[$sid])) {
                    $report_data[] = [
                        'id' => $sid,
                        'name' => $staff['name'],
                        'role' => $staff['role'],
                        'category' => $staff['category'],
                        'present' => $staff_totals[$sid]['present'],
                        'absent' => $staff_totals[$sid]['absent'],
                        'leave' => $staff_totals[$sid]['leave']
                    ];
                }
            }
        }

        wp_send_json_success(['report' => $report_data]);
    }
}

