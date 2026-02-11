<?php
/**
 * Component: Dashboard Notices Tab
 * @var array $data Dashboard data.
 */
?>
<!-- 2. NOTICES TAB -->
<div id="tab-notices" class="tab-content d-none">
    <div class="row justify-content-center">
        <div class="col-lg-10">
             <div class="d-flex flex-column gap-3">
                <?php if ( empty( $data['notices'] ) ) : ?>
                    <div class="text-center py-5 bg-white rounded-3 border border-light border-dashed text-muted">
                        No active notices for you.
                    </div>
                <?php else : ?>
                    <?php foreach ( $data['notices'] as $n ) : ?>
                        <div class="bg-white rounded-3 shadow-sm border border-light p-4 position-relative ps-4 text-start">
                             <!-- Accent Line -->
                            <div class="position-absolute start-0 top-0 bottom-0 bg-warning rounded-start" style="width: 4px;"></div>
                            
                            <div class="d-flex justify-content-between align-items-start mb-2 ps-2">
                                <h3 class="fs-5 fw-bold text-dark m-0"><?php echo esc_html( $n['title'] ); ?></h3>
                                <span class="badge bg-light text-muted fw-normal border border-light"><?php echo date( 'd M Y', strtotime( $n['created_at'] ) ); ?></span>
                            </div>
                            <div class="notice-content-body text-secondary mb-3 ps-2" style="font-size: 0.9rem;">
                                <?php echo wp_kses_post( $n['content'] ); ?>
                            </div>
                            <?php if ( ! empty( $n['attachment_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $n['attachment_url'] ); ?>" target="_blank" class="ms-2 d-inline-flex align-items-center gap-2 small fw-medium text-primary bg-primary-subtle px-3 py-1 rounded text-decoration-none shadow-none">
                                    <i class="bi bi-paperclip"></i> View Attachment
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
