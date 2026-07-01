<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard My Requests Tab
 * @var array $data Dashboard data.
 */
?>
<div id="tab-requests" class="tab-content d-none">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="d-flex justify-content-between align-items-center mb-4 text-start">
                <h2 class="fs-4 fw-bold m-0">My Requests</h2>
                <button class="btn btn-primary d-flex align-items-center gap-2 px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#SHUBX51GeneralRequestModal">
                    <i class="bi bi-plus-lg"></i> New Request
                </button>
            </div>

            <div class="d-flex flex-column gap-3">
                <?php if ( empty( $data['my_requests'] ) ) : ?>
                    <div class="text-center py-5 bg-white rounded-3 border border-light border-dashed text-muted">
                        <i class="bi bi-chat-left-text fs-1 opacity-25 d-block mb-3"></i>
                        You haven't raised any requests yet.
                    </div>
                <?php else : ?>
                    <?php foreach ( $data['my_requests'] as $req ) : ?>
                        <?php 
                        $status = $req['status'] ?? 'pending';
                        $status_class = 'bg-warning-subtle text-warning';
                        $status_icon = 'bi-clock-history';
                        
                        if ( $status === 'approved' ) {
                            $status_class = 'bg-success-subtle text-success';
                            $status_icon = 'bi-check-circle-fill';
                        } elseif ( $status === 'rejected' ) {
                            $status_class = 'bg-danger-subtle text-danger';
                            $status_icon = 'bi-x-circle-fill';
                        }
                        
                        $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'] ?? '{}', true);
                        if ( ! $payload ) $payload = [];
                        $category = $payload['category'] ?? ucfirst(str_replace('_', ' ', $req['request_type'] ?? $req['entity_type']));
                        if ($req['module'] === 'general') $category = $payload['category'] ?? 'General Request';
                        ?>
                        <div class="bg-white rounded-3 shadow-sm border border-light p-4 position-relative ps-4 text-start">
                             <!-- Accent Line -->
                            <div class="position-absolute start-0 top-0 bottom-0 <?php echo str_replace('bg-opacity-10', '', $status_class); ?> rounded-start" style="width: 4px;"></div>
                            
                            <div class="d-flex justify-content-between align-items-start mb-2 ps-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h3 class="fs-5 fw-bold text-dark m-0"><?php echo esc_html( $category ); ?></h3>
                                        <span class="badge rounded-pill fw-normal <?php echo $status_class; ?>" style="font-size: 0.7rem;">
                                            <i class="bi <?php echo $status_icon; ?> me-1"></i> <?php echo ucfirst($status); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted font-monospace">ID: #<?php echo esc_html(substr($req['id'], -8)); ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-muted fw-normal border border-light d-block mb-2"><?php echo wp_date( 'd M Y, h:i A', strtotime( $req['created_at'] ) ); ?></span>
                                    <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="viewResidentRequestDetail('<?php echo esc_attr($req['id']); ?>')">View Details</button>
                                </div>
                            </div>
                            
                            <?php if ( ! empty( $payload['comments'] ) ) : ?>
                                <div class="text-secondary mb-0 ps-2 text-truncate-2" style="font-size: 0.9rem;">
                                    <?php echo esc_html( $payload['comments'] ); ?>
                                </div>
                            <?php elseif ( ! empty( $req['admin_note'] ) ) : ?>
                                <div class="bg-light p-2 rounded mt-2 small text-muted italic">
                                    <strong>Admin Note:</strong> <?php echo esc_html($req['admin_note']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
