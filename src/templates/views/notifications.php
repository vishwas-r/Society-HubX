<?php
/**
 * View: Notifications Center
 * Admin UI for managing channels, events, templates, and logs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = Society_Govern_X::get_instance()->db;
$channels  = $db->get('notification_channels');
$events    = $db->get('notification_events');
$templates = $db->get('notification_templates');
$logs      = $db->get('notification_logs');

// Ensure data is sorted
usort($logs, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
?>

<div class="sgvx-view-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h2 class="h4 fw-bold text-slate-900 m-0">Notification Center</h2>
        <p class="text-slate-500 small m-0">Manage how and when residents and admins get notified.</p>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs border-0 gap-2 mb-4" id="notificationTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active border-0 rounded-pill px-4 py-2 small fw-bold" id="channels-tab" data-bs-toggle="tab" data-bs-target="#channels" type="button">Channels</button>
    </li>
    <li class="nav-item">
        <button class="nav-link border-0 rounded-pill px-4 py-2 small fw-bold text-slate-500" id="mapping-tab" data-bs-toggle="tab" data-bs-target="#mapping" type="button">Event Mapping</button>
    </li>
    <li class="nav-item">
        <button class="nav-link border-0 rounded-pill px-4 py-2 small fw-bold text-slate-500" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button">Templates</button>
    </li>
    <li class="nav-item">
        <button class="nav-link border-0 rounded-pill px-4 py-2 small fw-bold text-slate-500" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button">Activity Logs</button>
    </li>
</ul>

<div class="tab-content" id="notificationTabsContent">
    <!-- 1. Channels Tab -->
    <div class="tab-pane fade show active" id="channels" role="tabpanel">
        <div class="row g-4">
            <?php foreach($channels as $channel): 
                $config = json_decode($channel['config'], true) ?: [];
                $slug = $channel['channel_slug'];
                $icon = 'bi-envelope';
                $color = 'primary';
                if($slug === 'whatsapp') { $icon = 'bi-whatsapp'; $color = 'success'; }
                if($slug === 'inapp') { $icon = 'bi-app-indicator'; $color = 'info'; }
            ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="p-3 bg-<?php echo $color; ?> bg-opacity-10 rounded-3">
                                <i class="bi <?php echo $icon; ?> text-<?php echo $color; ?> fs-4"></i>
                            </div>
                            <div class="form-check form-switch p-0 m-0">
                                <input class="form-check-input ms-0 sgvx-channel-toggle" type="checkbox" data-channel="<?php echo $slug; ?>" <?php checked($channel['is_active'], 1); ?>>
                            </div>
                        </div>
                        <h5 class="fw-bold text-slate-900 mb-1"><?php echo ucfirst($slug); ?></h5>
                        <p class="text-slate-500 small mb-4">
                            <?php if($slug === 'email') echo 'Send alerts via WP Mail or Gmail API.'; ?>
                            <?php if($slug === 'whatsapp') echo 'Real-time alerts via Twilio WhatsApp API.'; ?>
                            <?php if($slug === 'inapp') echo 'Display alerts directly on resident dashboards.'; ?>
                        </p>
                        
                        <?php if($slug === 'whatsapp'): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-slate-500">Monthly Budget</span>
                                <span class="fw-bold text-slate-900">$<?php echo $config['current_usage'] ?? 0; ?> / $<?php echo $config['monthly_budget'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php 
                                    $percent = ($config['monthly_budget'] > 0) ? (($config['current_usage'] ?? 0) / $config['monthly_budget']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?php echo min(100, $percent); ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button class="btn btn-slate-100 text-slate-700 fw-bold small w-100 rounded-3 py-2 sgvx-configure-channel" data-channel="<?php echo $slug; ?>">
                            <i class="bi bi-gear me-2"></i>Configure
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 2. Event Mapping Tab -->
    <div class="tab-pane fade" id="mapping" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500">Event Name</th>
                            <th class="py-3 small fw-bold text-slate-500">Module</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center">In-App</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center">Email</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center">WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $event): 
                            $enabled_channels = explode(',', $event['default_channels']);
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-slate-900"><?php echo str_replace('_', ' ', ucfirst($event['event_slug'])); ?></div>
                                <div class="text-slate-400 x-small"><?php echo $event['event_slug']; ?></div>
                            </td>
                            <td><span class="badge bg-slate-100 text-slate-600 rounded-pill px-3"><?php echo ucfirst($event['module']); ?></span></td>
                            <td class="text-center">
                                <input class="form-check-input sgvx-mapping-toggle" type="checkbox" data-event="<?php echo $event['event_slug']; ?>" data-channel="inapp" <?php checked(in_array('inapp', $enabled_channels)); ?>>
                            </td>
                            <td class="text-center">
                                <input class="form-check-input sgvx-mapping-toggle" type="checkbox" data-event="<?php echo $event['event_slug']; ?>" data-channel="email" <?php checked(in_array('email', $enabled_channels)); ?>>
                            </td>
                            <td class="text-center">
                                <input class="form-check-input sgvx-mapping-toggle" type="checkbox" data-event="<?php echo $event['event_slug']; ?>" data-channel="whatsapp" <?php checked(in_array('whatsapp', $enabled_channels)); ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 3. Templates Tab -->
    <div class="tab-pane fade" id="templates" role="tabpanel">
        <div class="row g-4">
            <?php foreach($templates as $template): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 x-small fw-bold">
                                <?php echo strtoupper($template['channel']); ?>
                            </span>
                            <span class="text-slate-400 x-small">v<?php echo $template['version']; ?></span>
                        </div>
                        <h6 class="fw-bold text-slate-900 mb-1"><?php echo str_replace('_', ' ', ucfirst($template['event_slug'])); ?></h6>
                        <?php if($template['subject']): ?>
                            <div class="small text-slate-400 mb-2">Subject: <span class="text-slate-600 font-monospace"><?php echo esc_html($template['subject']); ?></span></div>
                        <?php endif; ?>
                        <div class="bg-slate-50 rounded-3 p-3 border mb-3">
                            <p class="text-slate-600 x-small m-0 line-clamp-3"><?php echo esc_html($template['content']); ?></p>
                        </div>
                        <button class="btn btn-link text-primary p-0 x-small fw-bold text-decoration-none sgvx-edit-template" data-id="<?php echo $template['id']; ?>">
                            <i class="bi bi-pencil-square me-1"></i>Edit Template & History
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 4. Logs Tab -->
    <div class="tab-pane fade" id="logs" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500">Time</th>
                            <th class="py-3 small fw-bold text-slate-500">Recipient</th>
                            <th class="py-3 small fw-bold text-slate-500">Event</th>
                            <th class="py-3 small fw-bold text-slate-500">Channel</th>
                            <th class="py-3 small fw-bold text-slate-500 text-end pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-slate-400">No activity logged yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td class="ps-4 py-3 small text-slate-500"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                            <td>
                                <?php $user = get_userdata($log['user_id']); echo $user ? esc_html($user->display_name) : 'Unknown ID: '.$log['user_id']; ?>
                            </td>
                            <td class="small text-slate-600"><?php echo $log['event_slug']; ?></td>
                            <td>
                                <span class="badge bg-slate-100 text-slate-600 rounded-pill px-3"><?php echo $log['channel']; ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if($log['status'] === 'sent'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold x-small">SENT</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 fw-bold x-small" title="<?php echo esc_attr($log['response']); ?>">FAILED</span>
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

<!-- Modals for configuration -->
<?php add_action('sgvx51_admin_modals', function() { ?>
    <!-- Channel Config Modal -->
    <div class="modal fade" id="sgvx-channel-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <form id="sgvx-channel-form">
                    <div class="modal-header border-0 pb-0 pt-4 px-4">
                        <h5 class="fw-bold text-slate-900 m-0">Configure <span id="sgvx-modal-channel-name"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="channel_slug" id="sgvx-modal-channel-slug">
                        <div id="sgvx-channel-settings-fields">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-slate-100 text-slate-700 fw-bold px-4 py-2 rounded-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-3">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php }); ?>

<script>
// Logic to handle tab switching aesthetics
document.querySelectorAll('#notificationTabs .nav-link').forEach(tab => {
    tab.addEventListener('shown.bs.tab', (e) => {
        document.querySelectorAll('#notificationTabs .nav-link').forEach(t => {
            t.classList.remove('fw-bold', 'active');
            t.classList.add('text-slate-500');
        });
        e.target.classList.add('fw-bold', 'active');
        e.target.classList.remove('text-slate-500');
    });
});
</script>
