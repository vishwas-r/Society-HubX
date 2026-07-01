<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * View: Activity Hub (Repurposed from Notifications Center)
 * Central ledger for auditing system events and communication history.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = Society_HubX::get_instance()->db;
$audit_logs = $db->get('audit_logs', ['orderby' => 'created_at', 'order' => 'DESC', 'limit' => 100]);
$notif_logs = $db->get('notification_logs', ['orderby' => 'created_at', 'order' => 'DESC', 'limit' => 100]);

// No need for usort or array_slice here anymore as DB handles it
?>

<div class="shubx-view-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <h2 class="h4 fw-bold text-slate-900 m-0">Activity Hub</h2>
        <p class="text-slate-500 small m-0">Audit system-wide administrative actions and resident communications.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-white border-slate-200 text-slate-700 fw-bold small rounded-3 shadow-sm" onclick="location.reload();">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Feed
        </button>
        <a href="<?php echo admin_url('admin.php?page=shubx51-global-settings&tab=communication'); ?>" class="btn btn-primary fw-bold small rounded-3 shadow-sm px-3">
            <i class="bi bi-gear-fill me-1"></i> Config
        </a>
    </div>
</div>

<!-- Hub Tabs -->
<div class="px-3 px-md-5 bg-white border-bottom border-light overflow-x-auto no-scrollbar mb-0 rounded-top-4">
    <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="activityTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-audit" type="button" style="background:none;">
                <i class="bi bi-shield-check me-2"></i>System Audit
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-secondary border-transparent" id="notif-tab" data-bs-toggle="tab" data-bs-target="#notif-ledger" type="button" style="background:none;">
                <i class="bi bi-mailbox2 me-2"></i>Communication Ledger
            </button>
        </li>
    </ul>
</div>

<div class="tab-content" id="activityTabsContent">
    <!-- 1. System Audit Feed -->
    <div class="tab-pane fade show active" id="system-audit" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-bottom-4 overflow-hidden mt-0">
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle">
                    <thead class="bg-light bg-opacity-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500 text-uppercase">Actor</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Entity Path & Details</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Action Taken</th>
                            <th class="py-3 small fw-bold text-slate-500 pe-4 text-end text-uppercase">Trace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($audit_logs)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-slate-400">No system events recorded yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach($audit_logs as $log): 
                            $actor = get_userdata($log['user_id']);
                            $actor_name = $actor ? $actor->display_name : 'System';
                            $module = $log['entity_type'] ?? 'system';
                            $icon = 'bi-gear-wide-connected';
                            $color = 'secondary';
                            
                            switch($module) {
                                case 'residents': $icon = 'bi-people'; $color = 'primary'; break;
                                case 'vehicles': $icon = 'bi-car-front'; $color = 'info'; break;
                                case 'finance': $icon = 'bi-cash-coin'; $color = 'success'; break;
                                case 'facilities': $icon = 'bi-calendar-event'; $color = 'warning'; break;
                                case 'documents': $icon = 'bi-file-earmark-lock'; $color = 'danger'; break;
                            }
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div class="rounded-circle bg-<?php echo $color; ?> bg-opacity-10 d-flex align-items-center justify-content-center text-<?php echo $color; ?> fw-bold border border-<?php echo $color; ?> border-opacity-10" style="width: 24px; height: 24px; font-size: 10px;">
                                        <?php echo strtoupper(substr($actor_name, 0, 1)); ?>
                                    </div>
                                    <span class="text-slate-700 small fw-bold"><?php echo esc_html($actor_name); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi <?php echo $icon; ?> text-<?php echo $color; ?> small"></i>
                                    <span class="badge bg-slate-50 text-slate-600 border border-slate-200 rounded-pill px-2 py-1 x-small fw-medium">
                                        <?php echo strtoupper($module); ?>
                                    </span>
                                    <span class="text-slate-400 small">#<?php echo $log['entity_id']; ?></span>
                                </div>
                                <div class="small text-slate-400 ms-1"><?php echo wp_date('M d, H:i:s', strtotime($log['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800 small"><?php echo esc_html($log['action']); ?></div>
                                <div class="text-slate-500 small text-truncate" style="max-width: 300px;" title="<?php echo esc_attr($log['details']); ?>">
                                    <?php echo esc_html($log['details']); ?>
                                </div>
                            </td>
                            <td class="pe-4 text-end">
                                <span class="badge bg-light text-muted x-small border rounded-1">AUT-<?php echo substr($log['id'], -4); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. Notification Ledger -->
    <div class="tab-pane fade" id="notif-ledger" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-bottom-4 overflow-hidden mt-0">
            <div class="table-responsive mt-4">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light bg-opacity-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500 text-uppercase">Time & Recipient</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Notification Content</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Triggered By</th>
                            <th class="py-3 small fw-bold text-slate-500 pe-4 text-end text-uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($notif_logs)): ?>
                            <tr><td colspan="4" class="text-center py-5 text-slate-400">No communication logs available.</td></tr>
                        <?php endif; ?>
                        <?php foreach($notif_logs as $log): 
                            $payload = isset($log['payload']) ? json_decode($log['payload'], true) : [];
                            $subject = $payload['subject'] ?? str_replace('_', ' ', ucfirst($log['event_slug']));
                            $body = $payload['body'] ?? '';
                            $recipient = get_userdata($log['user_id']);
                            $recipient_name = $recipient ? $recipient->display_name : 'User #'.$log['user_id'];
                            $actor = ($log['actor_id'] > 0) ? get_userdata($log['actor_id']) : null;
                            $actor_name = $actor ? $actor->display_name : 'Auto-trigger';
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="text-slate-700 small fw-bold"><?php echo esc_html($recipient_name); ?></span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary x-small px-2 border border-primary-subtle rounded-pill"><?php echo strtoupper($log['channel']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold text-slate-800 small mb-1"><?php echo esc_html($subject); ?></div>
                                <?php if($body): ?>
                                    <div class="text-slate-500 small text-truncate" style="max-width: 300px;" title="<?php echo esc_attr(strip_tags($body)); ?>">
                                        <?php echo esc_html(wp_trim_words(strip_tags($body), 12)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="small text-slate-400"><?php echo wp_date('M d, H:i', strtotime($log['created_at'])); ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-badge-fill text-slate-400 small"></i>
                                    <span class="text-slate-600 small fw-medium"><?php echo esc_html($actor_name); ?></span>
                                </div>
                            </td>
                            <td class="pe-4 text-end">
                                <?php if($log['status'] === 'sent'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3 py-1 fw-bold x-small">SENT</span>
                                    <?php if($log['cost'] > 0): ?>
                                        <div class="x-small text-slate-400 mt-1">₹<?php echo number_format($log['cost'], 2); ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-3 py-1 fw-bold x-small" data-bs-toggle="tooltip" title="<?php echo esc_attr($log['response']); ?>">ERROR</span>
                                    <div class="x-small text-danger mt-1" style="font-size: 9px;"><?php echo esc_html(wp_trim_words($log['response'], 5)); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

