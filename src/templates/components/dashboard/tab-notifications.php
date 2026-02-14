<?php
/**
 * Component: Dashboard Notifications Tab
 * @var array $data Dashboard data.
 */
$notifications = $data['notifications'] ?? [];
?>
<!-- NOTIFICATIONS TAB -->
<div id="tab-notifications" class="tab-content d-none">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-primary fw-bold">
            <i class="bi bi-bell me-2"></i>Notifications
        </h4>
        <?php if(!empty($notifications)): ?>
            <span class="badge bg-primary rounded-pill"><?php echo count($notifications); ?></span>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
        <?php if ( empty( $notifications ) ) : ?>
            <div class="text-center py-5">
                <i class="bi bi-bell-slash text-muted opacity-25" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No notifications yet.</p>
            </div>
        <?php else : ?>
            <div class="list-group list-group-flush">
                <?php foreach ( $notifications as $notif ) : ?>
                    <?php 
                        $is_unread = isset($notif['is_read']) ? (int)$notif['is_read'] === 0 : true;
                        $event = $notif['event_slug'] ?? 'general';
                        
                        $icon = 'bi-info-circle text-primary';
                        if(strpos($event, 'alert') !== false || strpos($event, 'due') !== false) $icon = 'bi-exclamation-triangle text-danger';
                        if(strpos($event, 'approved') !== false || strpos($event, 'success') !== false) $icon = 'bi-check-circle text-success';
                        if(strpos($event, 'rejected') !== false) $icon = 'bi-x-circle text-danger';
                    ?>
                    <div class="list-group-item p-4 d-flex gap-3 align-items-start <?php echo $is_unread ? 'bg-light' : ''; ?>">
                        <div class="fs-4 mt-1">
                            <i class="bi <?php echo $icon; ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 fw-bold text-dark"><?php echo esc_html($notif['title'] ?? 'Notification'); ?></h6>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <?php echo human_time_diff( strtotime($notif['created_at']), current_time('timestamp') ) . ' ago'; ?>
                                </small>
                            </div>
                            <p class="mb-0 text-secondary small" style="line-height: 1.5;">
                                <?php echo nl2br( $notif['content'] ?? '' ); ?> <!-- Corrected key from message to content -->
                            </p>
                        </div>
                        <?php if($is_unread): ?>
                            <span class="position-absolute top-50 end-0 translate-middle-y me-3 p-1 bg-primary border border-light rounded-circle">
                                <span class="visually-hidden">New alerts</span>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
