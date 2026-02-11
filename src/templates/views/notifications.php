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

<!-- Tabs Navigation (Resident Style) -->
<div class="px-3 px-md-5 bg-white border-bottom border-light overflow-x-auto no-scrollbar mb-4 rounded-3 shadow-sm">
    <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="notificationTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary tab-btn" id="channels-tab" data-bs-toggle="tab" data-bs-target="#channels" type="button" style="background:none;">Channels</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" id="mapping-tab" data-bs-toggle="tab" data-bs-target="#mapping" type="button" style="background:none;">Event Mapping</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" style="background:none;">Templates</button>
        </li>
        <li class="nav-item">
            <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" style="background:none;">Activity Logs</button>
        </li>
    </ul>
</div>

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
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="p-3 bg-<?php echo $color; ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="bi <?php echo $icon; ?> text-<?php echo $color; ?> fs-4"></i>
                            </div>
                            
                            <!-- Custom Toggle -->
                            <label class="sgvx-toggle" style="transform-origin: right center;">
                                <input type="checkbox" class="sgvx-channel-toggle" data-channel="<?php echo $slug; ?>" <?php checked($channel['is_active'], 1); ?>/>
                                <span class="sgvx-toggle-slider"></span>
                            </label>

                        </div>
                        <h5 class="fw-bold text-slate-900 mb-1"><?php echo ucfirst($slug); ?></h5>
                        <p class="text-slate-500 small mb-4 flex-grow-1">
                            <?php if($slug === 'email') echo 'Send alerts via WP Mail or Gmail API.'; ?>
                            <?php if($slug === 'whatsapp') echo 'Real-time alerts via Twilio WhatsApp API.'; ?>
                            <?php if($slug === 'inapp') echo 'Display alerts directly on resident dashboards.'; ?>
                        </p>
                        
                        <?php if($slug === 'whatsapp'): ?>
                        <div class="mb-3 p-3 bg-light rounded-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-slate-500 fw-medium">Monthly Budget</span>
                                <span class="fw-bold text-slate-900">$<?php echo $config['current_usage'] ?? 0; ?> / $<?php echo $config['monthly_budget'] ?? 0; ?></span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <?php 
                                    $percent = ($config['monthly_budget'] > 0) ? (($config['current_usage'] ?? 0) / $config['monthly_budget']) * 100 : 0;
                                ?>
                                <div class="progress-bar bg-success rounded-pill" style="width: <?php echo min(100, $percent); ?>%"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button class="btn btn-outline-secondary border-slate-200 text-slate-700 fw-bold small w-100 rounded-3 py-2 sgvx-configure-channel hover-bg-slate-50" data-channel="<?php echo $slug; ?>">
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
            <div class="card-header bg-white border-bottom px-4 py-3">
                <h6 class="fw-bold text-slate-800 m-0">Event Configuration</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500 text-uppercase">Event Name</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Module</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center text-uppercase">In-App</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center text-uppercase">Email</th>
                            <th class="py-3 small fw-bold text-slate-500 text-center text-uppercase">WhatsApp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($events as $event): 
                            $enabled_channels = explode(',', $event['default_channels']);
                        ?>
                        <tr>
                            <td class="ps-4 py-3">
                                <div class="fw-bold text-slate-900"><?php echo str_replace('_', ' ', ucfirst($event['event_slug'])); ?></div>
                                <div class="text-slate-400 x-small font-monospace"><?php echo $event['event_slug']; ?></div>
                            </td>
                            <td><span class="badge bg-slate-100 text-slate-600 border border-slate-200 rounded-pill px-3"><?php echo ucfirst($event['module']); ?></span></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center">
                                    <label class="sgvx-toggle sgvx-toggle-sm">
                                        <input type="checkbox" class="sgvx-mapping-toggle" data-event="<?php echo $event['event_slug']; ?>" data-channel="inapp" <?php checked(in_array('inapp', $enabled_channels)); ?>/>
                                        <span class="sgvx-toggle-slider"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center">
                                    <label class="sgvx-toggle sgvx-toggle-sm">
                                        <input type="checkbox" class="sgvx-mapping-toggle" data-event="<?php echo $event['event_slug']; ?>" data-channel="email" <?php checked(in_array('email', $enabled_channels)); ?>/>
                                        <span class="sgvx-toggle-slider"></span>
                                    </label>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center">
                                    <label class="sgvx-toggle sgvx-toggle-sm">
                                        <input type="checkbox" class="sgvx-mapping-toggle" data-event="<?php echo $event['event_slug']; ?>" data-channel="whatsapp" <?php checked(in_array('whatsapp', $enabled_channels)); ?>/>
                                        <span class="sgvx-toggle-slider"></span>
                                    </label>
                                </div>
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
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                         <h6 class="fw-bold text-slate-900 m-0 text-truncate" title="<?php echo str_replace('_', ' ', ucfirst($template['event_slug'])); ?>"><?php echo str_replace('_', ' ', ucfirst($template['event_slug'])); ?></h6>
                         <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 x-small fw-bold border border-primary-subtle">
                            <?php echo strtoupper($template['channel']); ?>
                        </span>
                    </div>
                    <div class="card-body p-4 d-flex flex-column">
                        <?php if($template['subject']): ?>
                            <div class="small text-slate-500 mb-2 fw-medium">Subject: <span class="text-slate-800"><?php echo esc_html($template['subject']); ?></span></div>
                        <?php endif; ?>
                        <div class="bg-slate-50 rounded-3 p-3 border mb-3 flex-grow-1">
                            <p class="text-slate-600 x-small m-0 line-clamp-3 font-monospace"><?php echo esc_html($template['content']); ?></p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-auto">
                            <span class="text-slate-400 x-small">v<?php echo $template['version']; ?></span>
                            <button class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3 sgvx-edit-template" data-id="<?php echo $template['id']; ?>">
                                <i class="bi bi-pencil-square me-1"></i> Edit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 4. Logs Tab -->
    <div class="tab-pane fade" id="logs" role="tabpanel">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
             <div class="card-header bg-white border-bottom px-4 py-3">
                <h6 class="fw-bold text-slate-800 m-0">Recent Activity</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-slate-50 border-bottom">
                        <tr>
                            <th class="ps-4 py-3 small fw-bold text-slate-500 text-uppercase">Time</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Recipient</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Event</th>
                            <th class="py-3 small fw-bold text-slate-500 text-uppercase">Channel</th>
                            <th class="py-3 small fw-bold text-slate-500 text-end pe-4 text-uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-slate-400">No activity logged yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td class="ps-4 py-3 small text-slate-500 font-monospace"><?php echo date('M d, H:i', strtotime($log['created_at'])); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                     <div class="rounded-circle bg-slate-100 d-flex align-items-center justify-content-center text-slate-500 fw-bold border border-slate-200" style="width: 24px; height: 24px; font-size: 10px;">
                                        <?php $user = get_userdata($log['user_id']); echo $user ? strtoupper(substr($user->display_name, 0, 1)) : '?'; ?>
                                     </div>
                                     <span class="text-slate-700 small fw-medium"><?php echo $user ? esc_html($user->display_name) : 'Unknown ID: '.$log['user_id']; ?></span>
                                </div>
                            </td>
                            <td class="small text-slate-600"><?php echo $log['event_slug']; ?></td>
                            <td>
                                <span class="badge bg-slate-100 text-slate-600 border border-slate-200 rounded-pill px-3"><?php echo $log['channel']; ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <?php if($log['status'] === 'sent'): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success rounded-pill px-3 py-1 fw-bold x-small">SENT</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-25 text-danger border border-danger rounded-pill px-3 py-1 fw-bold x-small" title="<?php echo esc_attr($log['response']); ?>">FAILED</span>
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
                        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="channel_slug" id="sgvx-modal-channel-slug">
                        <div id="sgvx-channel-settings-fields">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-light text-slate-700 fw-bold px-4 py-2 rounded-3 shadow-none border-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2 rounded-3 shadow-sm border-0">Save Settings</button>
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
            t.classList.remove('fw-bold', 'text-primary', 'border-primary');
            t.classList.add('fw-semibold', 'text-muted', 'border-transparent');
        });
        e.target.classList.remove('fw-semibold', 'text-muted', 'border-transparent');
        e.target.classList.add('fw-bold', 'text-primary', 'border-primary');
    });
});
</script>
