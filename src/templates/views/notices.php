<?php
/**
 * View: Notices (Bootstrap Migration)
 * Integrates directly with SGVX51_DB_Router.
 */

$db = new SGVX51_DB_Router();
$notices = $db->get( 'notices' );
$notices = array_reverse($notices); // Newest first

$success_msg = isset($_GET['success']) ? 'Notice published successfully.' : '';
?>

    <!-- Global Messages (Outside Cards) -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Notice Broadcasted</div>
                    <div class="small opacity-75"><?php echo esc_html( $success_msg ); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Society Notice Board</h1>
                <p class="text-secondary m-0 mt-1">Publish announcements and official communications to residents.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button onclick="openPublishModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi-broadcast fs-5"></i>
                    <span>Publish Notice</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="noticeSearch" placeholder="Search by title, content..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <div class="bg-light px-3 py-2 rounded-3 border border-light d-flex align-items-center">
                        <span class="small fw-bold text-secondary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Total Notices: <?php echo count($notices); ?></span>
                    </div>
                </div>
            </div>
        </div>


        <div class="p-4 p-md-5">
            <?php if ( empty( $notices ) ) : ?>
                <div class="py-5 text-center text-muted">
                    <i class="bi bi-clipboard-x text-muted opacity-25 mb-4 d-block" style="font-size: 64px;"></i>
                    <h5 class="text-dark fw-bold">No Active Broadcasts</h5>
                    <p class="text-secondary m-0">The notice board is currently blank. Start by publishing one.</p>
                </div>
            <?php else : ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ( $notices as $n ) : 
                        $search_text = esc_attr( strtolower( ($n['title'] ?? '') . ' ' . ($n['content'] ?? '') ) );
                    ?>
                        <div class="card border border-light shadow-sm rounded-3 overflow-hidden bg-white sgvx-notice-card" data-id="<?php echo esc_attr($n['id']); ?>" data-search="<?php echo $search_text; ?>">
                            <div class="p-4 p-md-5">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <h4 class="h5 fw-bold text-dark m-0" style="letter-spacing: -0.01em;"><?php echo esc_html( $n['title'] ); ?></h4>
                                        <div class="small text-secondary opacity-75 mt-1 d-flex align-items-center gap-2">
                                            <i class="bi bi-clock-history"></i>
                                            <span>Published on <?php echo date( 'd M Y, H:i', strtotime( $n['created_at'] ) ); ?></span>
                                        </div>
                                    </div>
                                    <?php if ( ! empty( $n['attachment_url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $n['attachment_url'] ); ?>" target="_blank" class="btn btn-sm btn-outline-primary px-4 fw-bold rounded-pill shadow-none" style="font-size: 11px;">
                                            <i class="bi bi-file-earmark-pdf-fill me-1 fs-6"></i> VIEW PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-secondary small mb-5" style="white-space: pre-wrap; line-height: 1.7;"><?php echo esc_html( $n['content'] ); ?></div>
                                
                                <div class="d-flex align-items-center justify-content-between pt-4 border-top border-light">
                                    <div class="d-flex gap-3">
                                        <span class="badge bg-primary bg-opacity-10 text-primary border-primary border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;">FOR: <?php echo esc_html($n['audience']); ?></span>
                                        <?php if(!empty($n['expiry_date'])): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border-warning border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;">EXT: <?php echo esc_html($n['expiry_date']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-light border border-light p-2 text-danger shadow-sm js-delete-notice rounded-3" data-id="<?php echo esc_attr($n['id']); ?>" title="Delete Notice">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() {
?>
<!-- Publish Notice Modal -->
<div class="modal fade" id="publishModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Publish New Notice</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="notice-form" method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="sgvx51_add_notice">
                    <?php wp_nonce_field( 'sgvx51_add_notice_nonce' ); ?>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Notice Title</label>
                        <input type="text" name="title" class="form-control bg-light border-0 shadow-none rounded-3 fw-medium" style="height: 48px;" required placeholder="e.g. Schedule for Painting Work">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Notice Content</label>
                        <textarea name="content" class="form-control bg-light border-0 shadow-none rounded-3 fw-medium p-3" rows="5" required placeholder="Detailed message here..."></textarea>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Target Audience</label>
                            <select name="audience" class="form-select bg-light border-0 shadow-none rounded-3 fw-medium" style="height: 48px;">
                                <option value="All">Everyone</option>
                                <option value="Owners">Owners Only</option>
                                <option value="Tenants">Tenants Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Expiry (Optional)</label>
                            <input type="date" name="expiry_date" class="form-control bg-light border-0 shadow-none rounded-3 fw-medium" style="height: 48px;">
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Attachment (PDF/Image)</label>
                        <input type="file" name="attachment" class="form-control bg-light border-0 shadow-none rounded-3">
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4 fw-semibold text-secondary rounded-3 border-0 shadow-none" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Broadcast Now</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
let publishModal = null;
function openPublishModal() {
    if(!publishModal) publishModal = new bootstrap.Modal(document.getElementById('publishModal'));
    publishModal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('noticeSearch');
    if(search) {
        search.addEventListener('keyup', (e) => {
            const val = e.target.value.toLowerCase();
            document.querySelectorAll('.sgvx-notice-card').forEach(el => {
                const text = el.dataset.search || '';
                el.style.display = text.includes(val) ? '' : 'none';
            });
        });
    }
});
</script>

