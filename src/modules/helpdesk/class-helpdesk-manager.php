<?php
/**
 * Module: Helpdesk Manager
 * Handles Complaints, Service Requests, SLAs, Technician Assignments & Field Operations.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class NAMMASOCIETY51_Helpdesk_Manager implements NAMMASOCIETY51_Module {

    private $db;
    private $drive;

    public function __construct() {
        $this->db = new NAMMASOCIETY51_DB_Router();
        $this->drive = new NAMMASOCIETY51_Drive_Manager();

        add_action('admin_menu', array($this, 'register_menu'), 210);

        // AJAX Handlers
        add_action('wp_ajax_nammasociety51_helpdesk_create', array($this, 'handle_create_ticket'));
        add_action('wp_ajax_nammasociety51_helpdesk_assign', array($this, 'handle_assign_technician'));
        add_action('wp_ajax_nammasociety51_helpdesk_update_status', array($this, 'handle_update_status'));
        add_action('wp_ajax_nammasociety51_helpdesk_verify_otp', array($this, 'handle_verify_otp'));
        add_action('wp_ajax_nammasociety51_helpdesk_add_reply', array($this, 'handle_add_reply'));
        add_action('wp_ajax_nammasociety51_helpdesk_get_details', array($this, 'handle_get_ticket_details'));
        add_action('wp_ajax_nammasociety51_helpdesk_delete_ticket', array($this, 'handle_delete_ticket'));

        // Self-Heal Schema
        if (is_admin()) {
            $this->db->verify_column('helpdesk_tickets', 'closure_otp', 'varchar(10) DEFAULT "" NOT NULL');
            $this->db->verify_column('helpdesk_tickets', 'sla_due_date', 'datetime DEFAULT NULL');
            $this->db->verify_column('helpdesk_tickets', 'resolved_at', 'datetime DEFAULT NULL');
            $this->db->verify_column('helpdesk_tickets', 'rating', 'int(2) DEFAULT 0 NOT NULL');
            $this->db->verify_column('helpdesk_tickets', 'feedback', 'text NOT NULL');
            $this->db->verify_column('ticket_replies', 'is_internal_note', 'tinyint(1) DEFAULT 0 NOT NULL');
        }

        // Register Module
        add_filter('nammasociety51_get_module_helpdesk', array($this, 'get_instance'));
    }

    public function get_instance() {
        return $this;
    }

    public function get_module_slug() {
        return 'helpdesk';
    }

    public function execute_request($action, $payload) {
        return true;
    }

    public function register_menu() {
        add_submenu_page(
            'nammasociety51-settings',
            'Helpdesk & Tickets',
            'Helpdesk & Tickets',
            'read',
            'nammasociety51-helpdesk',
            array($this, 'render_page')
        );
    }

    public function render_page() {
        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if (!$rbac->has_capability(get_current_user_id(), 'helpdesk_view')) {
            wp_die('You do not have permission to view helpdesk tickets.');
        }

        $tickets = $this->db->get('helpdesk_tickets');
        if (!is_array($tickets)) {
            $tickets = [];
        }

        // Sort descending by created_at
        usort($tickets, function($a, $b) {
            return strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0);
        });

        // Technicians / Staff list for assignment dropdown
        $staff = $this->db->get('daily_help');
        $active_staff = array_filter($staff ?: [], function($s) {
            return ($s['status'] ?? '') === 'approved';
        });

        $flats = $this->db->get('flats');
        $residents = $this->db->get('residents');

        // Calculate KPI Metrics
        $total_count = count($tickets);
        $open_count = 0;
        $in_progress_count = 0;
        $resolved_count = 0;
        $closed_count = 0;
        $sla_breached_count = 0;

        $now_ts = strtotime(current_time('mysql'));

        foreach ($tickets as $t) {
            $st = strtolower($t['status'] ?? 'open');
            if ($st === 'open' || $st === 'assigned') {
                $open_count++;
            } elseif ($st === 'in_progress') {
                $in_progress_count++;
            } elseif ($st === 'resolved') {
                $resolved_count++;
            } elseif ($st === 'closed') {
                $closed_count++;
            }

            // Check SLA breach (if not resolved or closed)
            if (!in_array($st, ['resolved', 'closed', 'rejected'], true) && !empty($t['sla_due_date'])) {
                $due_ts = strtotime($t['sla_due_date']);
                if ($due_ts && $due_ts < $now_ts) {
                    $sla_breached_count++;
                }
            }
        }

        NAMMASOCIETY51_Admin_App::render_view('helpdesk', [
            'tickets'            => $tickets,
            'technicians'        => $active_staff,
            'flats'              => $flats ?: [],
            'residents'          => $residents ?: [],
            'kpis'               => [
                'total'        => $total_count,
                'open'         => $open_count,
                'in_progress'  => $in_progress_count,
                'resolved'     => $resolved_count,
                'closed'       => $closed_count,
                'sla_breached' => $sla_breached_count,
            ]
        ]);
    }

    /**
     * AJAX: Create Ticket
     */
    public function handle_create_ticket() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if (!$rbac->has_capability(get_current_user_id(), 'helpdesk_manage') && !$rbac->has_capability(get_current_user_id(), 'helpdesk_view')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? 'General'));
        $priority = sanitize_text_field(wp_unslash($_POST['priority'] ?? 'medium'));
        $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
        $flat_no = sanitize_text_field(wp_unslash($_POST['flat_no'] ?? ''));
        $block = sanitize_text_field(wp_unslash($_POST['block'] ?? ''));
        $block = trim( preg_replace( '/^(block[\s_-]*)+/i', '', $block ) );
        if ( empty( $block ) && ! empty( $flat_no ) && $flat_no !== 'Common Area' ) {
            $matched_flats = $this->db->get( 'flats', array( 'where' => array( 'id' => $flat_no ) ) );
            if ( ! empty( $matched_flats[0]['block'] ) ) {
                $block = trim( preg_replace( '/^(block[\s_-]*)+/i', '', (string)$matched_flats[0]['block'] ) );
            }
        }
        $resident_name = sanitize_text_field(wp_unslash($_POST['resident_name'] ?? ''));
        $assigned_to = sanitize_text_field(wp_unslash($_POST['assigned_to'] ?? '0'));

        if (empty($subject)) {
            wp_send_json_error(['message' => 'Subject is required']);
        }

        // Calculate SLA due date
        $sla_hours = 24;
        if ($priority === 'urgent') {
            $sla_hours = 4;
        } elseif ($priority === 'high') {
            $sla_hours = 12;
        } elseif ($priority === 'low') {
            $sla_hours = 48;
        }
        $sla_due_date = date('Y-m-d H:i:s', strtotime("+{$sla_hours} hours", strtotime(current_time('mysql'))));

        // 4-digit closure OTP
        $closure_otp = sprintf("%04d", wp_rand(1000, 9999));
        $ticket_id = uniqid('tkt_');
        $ticket_number = 'TKT-' . date('Ym') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 4));

        $photos = [];
        if (!empty($_FILES['photos'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            // Handle multiple photo uploads
            $files = $_FILES['photos'];
            if (is_array($files['name'])) {
                foreach ($files['name'] as $i => $name) {
                    if (empty($name)) continue;
                    $single_file = [
                        'name'     => sanitize_file_name($files['name'][$i]),
                        'type'     => sanitize_text_field($files['type'][$i]),
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $uploaded = $this->drive->upload_file('helpdesk_docs', $single_file);
                    if (!is_wp_error($uploaded)) {
                        $photos[] = $uploaded;
                    }
                }
            } else {
                $single_file = [
                    'name'     => sanitize_file_name($files['name']),
                    'type'     => sanitize_text_field($files['type']),
                    'tmp_name' => $files['tmp_name'],
                    'error'    => $files['error'],
                    'size'     => $files['size'],
                ];
                $uploaded = $this->drive->upload_file('helpdesk_docs', $single_file);
                if (!is_wp_error($uploaded)) {
                    $photos[] = $uploaded;
                }
            }
        }

        $status = !empty($assigned_to) ? 'assigned' : 'open';

        $ticket_data = array(
            'id'            => $ticket_id,
            'ticket_number' => $ticket_number,
            'block'         => $block,
            'flat_no'       => $flat_no,
            'resident_id'   => $resident_name,
            'category'      => $category,
            'subcategory'   => '',
            'priority'      => $priority,
            'status'        => $status,
            'subject'       => $subject,
            'description'   => $description,
            'photos'        => wp_json_encode($photos),
            'assigned_to'   => $assigned_to,
            'sla_due_date'  => $sla_due_date,
            'closure_otp'   => $closure_otp,
            'rating'        => 0,
            'feedback'      => '',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        );

        $res = $this->db->insert('helpdesk_tickets', $ticket_data);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        // Notify if assigned immediately
        if (!empty($assigned_to) && class_exists('NAMMASOCIETY51_Plugin')) {
            $nammasociety = NAMMASOCIETY51_Plugin::get_instance();
            // Try to find flat resident
            $residents = $this->db->get('residents');
            foreach ($residents as $r) {
                if ($r['flat_no'] === $flat_no && !empty($r['wp_user_id'])) {
                    $nammasociety->notifications->trigger('ticket_assigned', $r['wp_user_id'], [
                        'ticket_number'   => $ticket_number,
                        'technician_name' => $this->get_staff_name($assigned_to),
                        'title'           => $subject,
                        'resident_name'   => $r['name']
                    ], false);
                }
            }
        }

        wp_send_json_success([
            'message'       => "Ticket {$ticket_number} created successfully.",
            'ticket_number' => $ticket_number
        ]);
    }

    /**
     * AJAX: Assign Technician & Update SLA
     */
    public function handle_assign_technician() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if (!$rbac->has_capability(get_current_user_id(), 'helpdesk_manage')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        $technician_id = sanitize_text_field(wp_unslash($_POST['technician_id'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? 'assigned'));
        $custom_sla = sanitize_text_field(wp_unslash($_POST['custom_sla'] ?? ''));

        if (!$ticket_id || !$technician_id) {
            wp_send_json_error(['message' => 'Ticket ID and Technician are required']);
        }

        $ticket_rows = $this->db->get('helpdesk_tickets', array('where' => array('id' => $ticket_id)));
        if (empty($ticket_rows)) {
            wp_send_json_error(['message' => 'Ticket not found']);
        }
        $ticket = $ticket_rows[0];

        $update_data = [
            'assigned_to' => $technician_id,
            'status'      => $status,
            'updated_at'  => current_time('mysql'),
        ];

        if (!empty($custom_sla)) {
            $update_data['sla_due_date'] = date('Y-m-d H:i:s', strtotime($custom_sla));
        }

        $this->db->update('helpdesk_tickets', $update_data, ['id' => $ticket_id]);

        $technician_name = $this->get_staff_name($technician_id);

        // Record automated reply in conversation timeline
        $this->db->insert('ticket_replies', [
            'ticket_id'         => $ticket_id,
            'user_id'           => get_current_user_id(),
            'message'           => "Assigned to technician {$technician_name}. Status changed to " . strtoupper($status) . ".",
            'attachments'       => '[]',
            'is_internal_note'  => 0,
            'created_at'        => current_time('mysql'),
        ]);

        // Dispatch notification
        if (class_exists('NAMMASOCIETY51_Plugin')) {
            $nammasociety = NAMMASOCIETY51_Plugin::get_instance();
            $residents = $this->db->get('residents');
            foreach ($residents as $r) {
                if ($r['flat_no'] === $ticket['flat_no'] && !empty($r['wp_user_id'])) {
                    $nammasociety->notifications->trigger('ticket_assigned', $r['wp_user_id'], [
                        'ticket_number'   => $ticket['ticket_number'],
                        'technician_name' => $technician_name,
                        'title'           => $ticket['subject'],
                        'resident_name'   => $r['name']
                    ], false);
                }
            }
        }

        wp_send_json_success([
            'message' => "Ticket {$ticket['ticket_number']} assigned to {$technician_name}."
        ]);
    }

    /**
     * AJAX: Update Ticket Status (Open, In Progress, Resolved, Closed, Rejected)
     */
    public function handle_update_status() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if (!$rbac->has_capability(get_current_user_id(), 'helpdesk_manage')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));

        if (!$ticket_id || !$status) {
            wp_send_json_error(['message' => 'Ticket ID and Status are required']);
        }

        $ticket_rows = $this->db->get('helpdesk_tickets', array('where' => array('id' => $ticket_id)));
        if (empty($ticket_rows)) {
            wp_send_json_error(['message' => 'Ticket not found']);
        }
        $ticket = $ticket_rows[0];

        $update_data = [
            'status'     => $status,
            'updated_at' => current_time('mysql'),
        ];

        $otp_generated = '';

        if ($status === 'resolved') {
            $otp_generated = !empty($ticket['closure_otp']) ? $ticket['closure_otp'] : sprintf("%04d", wp_rand(1000, 9999));
            $update_data['closure_otp'] = $otp_generated;
            $update_data['resolved_at'] = current_time('mysql');

            // Add timeline entry
            $this->db->insert('ticket_replies', [
                'ticket_id'         => $ticket_id,
                'user_id'           => get_current_user_id(),
                'message'           => "Job marked as RESOLVED. 4-digit closure OTP dispatched to resident for completion sign-off.",
                'attachments'       => '[]',
                'is_internal_note'  => 0,
                'created_at'        => current_time('mysql'),
            ]);

            // Notify Resident with Closure OTP
            if (class_exists('NAMMASOCIETY51_Plugin')) {
                $nammasociety = NAMMASOCIETY51_Plugin::get_instance();
                $residents = $this->db->get('residents');
                foreach ($residents as $r) {
                    if ($r['flat_no'] === $ticket['flat_no'] && !empty($r['wp_user_id'])) {
                        $nammasociety->notifications->trigger('ticket_resolved', $r['wp_user_id'], [
                            'ticket_number' => $ticket['ticket_number'],
                            'closure_otp'   => $otp_generated,
                            'title'         => $ticket['subject'],
                            'resident_name' => $r['name']
                        ], false);
                    }
                }
            }
        } elseif ($status === 'closed') {
            $update_data['resolved_at'] = !empty($ticket['resolved_at']) ? $ticket['resolved_at'] : current_time('mysql');
            
            $this->db->insert('ticket_replies', [
                'ticket_id'         => $ticket_id,
                'user_id'           => get_current_user_id(),
                'message'           => "Ticket closed by administrator/management.",
                'attachments'       => '[]',
                'is_internal_note'  => 0,
                'created_at'        => current_time('mysql'),
            ]);
        }

        $this->db->update('helpdesk_tickets', $update_data, ['id' => $ticket_id]);

        wp_send_json_success([
            'message'     => "Ticket status updated to " . strtoupper($status) . ".",
            'closure_otp' => $otp_generated
        ]);
    }

    /**
     * AJAX: Verify 4-Digit Closure OTP (ADDA/NoBrokerHood Parity)
     */
    public function handle_verify_otp() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        $entered_otp = sanitize_text_field(wp_unslash($_POST['otp'] ?? ''));

        if (!$ticket_id || empty($entered_otp)) {
            wp_send_json_error(['message' => 'Ticket ID and 4-digit OTP are required']);
        }

        $ticket_rows = $this->db->get('helpdesk_tickets', array('where' => array('id' => $ticket_id)));
        if (empty($ticket_rows)) {
            wp_send_json_error(['message' => 'Ticket not found']);
        }
        $ticket = $ticket_rows[0];

        if (empty($ticket['closure_otp']) || !hash_equals((string) $ticket['closure_otp'], (string) $entered_otp)) {
            wp_send_json_error(['message' => 'Invalid closure OTP. Please ask resident for the 4-digit code.']);
        }

        // Mark Ticket as Closed
        $now = current_time('mysql');
        $this->db->update('helpdesk_tickets', [
            'status'      => 'closed',
            'resolved_at' => !empty($ticket['resolved_at']) ? $ticket['resolved_at'] : $now,
            'updated_at'  => $now,
        ], ['id' => $ticket_id]);

        // Record in timeline
        $this->db->insert('ticket_replies', [
            'ticket_id'         => $ticket_id,
            'user_id'           => get_current_user_id(),
            'message'           => "Verified closure OTP ({$entered_otp}) successfully. Ticket is now CLOSED.",
            'attachments'       => '[]',
            'is_internal_note'  => 0,
            'created_at'        => $now,
        ]);

        // Trigger notification: ticket_closed
        if (class_exists('NAMMASOCIETY51_Plugin')) {
            $nammasociety = NAMMASOCIETY51_Plugin::get_instance();
            $residents = $this->db->get('residents');
            foreach ($residents as $r) {
                if ($r['flat_no'] === $ticket['flat_no'] && !empty($r['wp_user_id'])) {
                    $nammasociety->notifications->trigger('ticket_closed', $r['wp_user_id'], [
                        'ticket_number' => $ticket['ticket_number'],
                        'title'         => $ticket['subject'],
                        'resident_name' => $r['name']
                    ], false);
                }
            }
        }

        wp_send_json_success([
            'message' => "OTP verified! Ticket {$ticket['ticket_number']} is officially CLOSED."
        ]);
    }

    /**
     * AJAX: Add Reply or Internal Note
     */
    public function handle_add_reply() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
        $is_internal = !empty($_POST['is_internal_note']) ? 1 : 0;

        if (!$ticket_id || empty($message)) {
            wp_send_json_error(['message' => 'Ticket ID and Message are required']);
        }

        $user_id = get_current_user_id();

        $res = $this->db->insert('ticket_replies', [
            'ticket_id'        => $ticket_id,
            'user_id'          => $user_id,
            'message'          => $message,
            'attachments'      => '[]',
            'is_internal_note' => $is_internal,
            'created_at'       => current_time('mysql'),
        ]);

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()]);
        }

        $this->db->update('helpdesk_tickets', ['updated_at' => current_time('mysql')], ['id' => $ticket_id]);

        wp_send_json_success(['message' => 'Reply posted successfully']);
    }

    /**
     * AJAX: Get Ticket Details & Timeline
     */
    public function handle_get_ticket_details() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        if (!$ticket_id) {
            wp_send_json_error(['message' => 'Ticket ID is required']);
        }

        $ticket_rows = $this->db->get('helpdesk_tickets', array('where' => array('id' => $ticket_id)));
        if (empty($ticket_rows)) {
            wp_send_json_error(['message' => 'Ticket not found']);
        }
        $ticket = $ticket_rows[0];

        // Format photos
        $photos = [];
        if (!empty($ticket['photos'])) {
            $decoded = json_decode($ticket['photos'], true);
            if (is_array($decoded)) {
                $photos = $decoded;
            } elseif (is_string($ticket['photos'])) {
                $photos = [$ticket['photos']];
            }
        }
        $ticket['photos_list'] = $photos;

        // Technician details
        $ticket['technician_name'] = $this->get_staff_name($ticket['assigned_to']);

        // Timeline Replies
        $replies = $this->db->get('ticket_replies', array('where' => array('ticket_id' => $ticket_id)));
        if (!is_array($replies)) $replies = [];

        usort($replies, function($a, $b) {
            return strtotime($a['created_at'] ?? 0) - strtotime($b['created_at'] ?? 0);
        });

        foreach ($replies as &$rep) {
            $u = get_userdata($rep['user_id']);
            $rep['author_name'] = $u ? $u->display_name : 'System / Staff';
            $rep['formatted_time'] = date('d M Y, h:i A', strtotime($rep['created_at']));
        }
        unset($rep);

        // SLA remaining text
        $now_ts = strtotime(current_time('mysql'));
        $due_ts = !empty($ticket['sla_due_date']) ? strtotime($ticket['sla_due_date']) : 0;
        $is_breached = false;
        $sla_text = 'No SLA Set';

        if ($due_ts) {
            $diff = $due_ts - $now_ts;
            if ($diff < 0) {
                $is_breached = true;
                $diff_abs = abs($diff);
                $hrs = floor($diff_abs / 3600);
                $mins = floor(($diff_abs % 3600) / 60);
                $sla_text = "Breached by {$hrs}h {$mins}m";
            } else {
                $hrs = floor($diff / 3600);
                $mins = floor(($diff % 3600) / 60);
                $sla_text = "{$hrs}h {$mins}m remaining";
            }
        }

        $ticket['sla_text'] = $sla_text;
        $ticket['sla_breached'] = $is_breached;

        wp_send_json_success([
            'ticket'  => $ticket,
            'replies' => $replies
        ]);
    }

    /**
     * AJAX: Delete / Reject Ticket
     */
    public function handle_delete_ticket() {
        check_ajax_referer('nammasociety51_helpdesk_nonce');

        $rbac = new NAMMASOCIETY51_RBAC_Manager();
        if (!$rbac->has_capability(get_current_user_id(), 'helpdesk_manage')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $ticket_id = sanitize_text_field(wp_unslash($_POST['ticket_id'] ?? ''));
        if (!$ticket_id) {
            wp_send_json_error(['message' => 'Ticket ID is required']);
        }

        $this->db->delete('helpdesk_tickets', ['id' => $ticket_id]);
        $this->db->delete('ticket_replies', ['ticket_id' => $ticket_id]);

        wp_send_json_success(['message' => 'Ticket deleted successfully']);
    }

    private function get_staff_name($assigned_to) {
        if (empty($assigned_to) || $assigned_to === '0') {
            return 'Unassigned';
        }
        $staff = $this->db->get('daily_help', array('where' => array('id' => $assigned_to)));
        if (!empty($staff)) {
            $role = !empty($staff[0]['role']) ? " ({$staff[0]['role']})" : '';
            return $staff[0]['name'] . $role;
        }
        $u = get_userdata(intval($assigned_to));
        if ($u) {
            return $u->display_name . ' (Admin)';
        }
        return 'Staff #' . $assigned_to;
    }
}
