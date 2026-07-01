<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * View: Digital Democracy (Polls) - Bootstrap Migration
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$db = new SHUBX51_DB_Router();
$polls = $db->get( 'polls' );
$poll_manager = new SHUBX51_Poll_Manager();

// Sort polls by created_at DESC
usort( $polls, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Digital Democracy</h1>
                <p class="text-secondary m-0 mt-1">Create and manage society polls to empower residents.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button onclick="openPollModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi- megaphone fs-5"></i>
                    <span>Launch New Poll</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-soft rounded-2xl bg-white overflow-hidden mb-5">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="shubx-poll-search" placeholder="Search by poll title, description..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-xl fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <div class="bg-primary bg-opacity-10 px-3 py-2 rounded-xl border border-light d-flex align-items-center">
                        <span class="small fw-bold text-primary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Total Polls: <?php echo count($polls); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-4 p-md-5">
            <!-- Active & Recent Polls Grid -->
            <div class="row g-4">
        <?php if ( empty( $polls ) ) : ?>
            <div class="col-12">
                <div class="py-5 text-center text-muted">
                    <i class="bi bi-megaphone text-slate-200 mb-4 d-block" style="font-size: 64px;"></i>
                    <h5 class="text-dark fw-bold">No Polls Active</h5>
                    <p class="text-secondary m-0">Launch your first community poll to gather feedback from residents.</p>
                </div>
            </div>
        <?php else : ?>
            <?php foreach ( $polls as $p ) : 
                $results = $poll_manager->get_results($p['id']);
                $is_closed = $p['status'] === 'closed';
                $is_expired = !empty($p['expiry']) && strtotime($p['expiry']) < time();
                $final_status = ($is_closed || $is_expired) ? 'Closed' : 'Active';
                $status_bg = $final_status === 'Active' ? 'bg-success bg-opacity-10 text-success border-success border-opacity-10' : 'bg-light text-secondary border-light';
                $search_text = esc_attr(strtolower(($p['title']??'') . ' ' . ($p['description']??'')));
            ?>
                <div class="col-lg-6 shubx-poll-card" data-search="<?php echo $search_text; ?>">
                    <div class="card border border-light shadow-none rounded-3 h-100 overflow-hidden bg-white hover-shadow-sm transition-all border-top border-4 <?php echo $final_status === 'Active' ? 'border-primary' : 'border-secondary opacity-50'; ?>">
                        <div class="p-4 p-md-5 flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-4">
                                <span class="badge <?php echo $status_bg; ?> border px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;">
                                    <?php echo $final_status; ?>
                                </span>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light border-0 shadow-none p-2 rounded-circle hover-bg-slate-100" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots-vertical fs-6"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end border shadow-lg rounded-xl mt-2">
                                        <?php if($final_status === 'Active'): ?>
                                            <li><a class="dropdown-item fw-bold text-warning py-2" href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=shubx51_close_poll&id='.$p['id']), 'shubx51_poll_action' ); ?>" onclick="return confirm('Close this poll?')"><i class="bi bi-lock me-2"></i>Close Poll</a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item fw-bold text-danger py-2" href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=shubx51_delete_poll&id='.$p['id']), 'shubx51_poll_action' ); ?>" onclick="return confirm('Delete this poll permanently?')"><i class="bi bi-trash me-2"></i>Delete Poll</a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <h3 class="h5 fw-bold text-dark mb-3" style="letter-spacing: -0.01em;"><?php echo esc_html($p['title']); ?></h3>
                            <p class="text-secondary small mb-5 line-clamp-2"><?php echo esc_html($p['description']); ?></p>

                            <!-- Results Section -->
                            <div class="d-flex flex-column gap-4">
                                <?php 
                                $total_votes = $results['total'];
                                foreach($p['options'] as $opt): 
                                    $v_count = $results['counts'][$opt] ?? 0;
                                    $pct = $total_votes > 0 ? round(($v_count / $total_votes) * 100) : 0;
                                ?>
                                    <div>
                                        <div class="d-flex justify-content-between small fw-bold text-dark mb-2">
                                            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.02em;"><?php echo esc_html($opt); ?></span>
                                            <span class="text-primary" style="font-size: 12px;"><?php echo $v_count; ?> votes (<?php echo $pct; ?>%)</span>
                                        </div>
                                        <div class="progress rounded-pill bg-slate-100" style="height: 8px;">
                                            <div class="progress-bar bg-primary rounded-pill transition-all" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-light border-top border-light d-flex justify-content-between align-items-center">
                            <span class="small text-muted d-flex align-items-center gap-2">
                                <i class="bi bi-calendar3"></i>
                                <span>Launched: <?php echo wp_date('M d, Y', strtotime($p['created_at'])); ?></span>
                            </span>
                            <span class="small fw-bold text-dark d-flex align-items-center gap-2">
                                <i class="bi bi-person-check-fill fs-6 text-primary"></i>
                                <span><?php echo $total_votes; ?> VOTES TOTAL</span>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('shubx51_admin_modals', function() {
?>
<!-- Create Poll Modal -->
<div class="modal fade" id="createPollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">New Society Poll</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_create_poll">
                    <?php wp_nonce_field('shubx51_poll_action'); ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Poll Title</label>
                        <input type="text" name="title" required placeholder="e.g. Annual Day Celebration Theme" class="form-control shadow-none rounded-lg">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Details / Background</label>
                        <textarea name="description" rows="3" placeholder="Explain the decision options to residents..." class="form-control shadow-none rounded-lg"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Choice Options</label>
                        <div id="optionsContainer" class="d-flex flex-column gap-2 mb-2">
                            <input type="text" name="options[]" required placeholder="Option 1" class="form-control shadow-none rounded-lg">
                            <input type="text" name="options[]" required placeholder="Option 2" class="form-control shadow-none rounded-lg">
                        </div>
                        <button type="button" onclick="addOptionField()" class="btn btn-link btn-sm p-0 text-primary fw-bold py-1 text-decoration-none">+ Add More Options</button>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Automatic Expiry (Optional)</label>
                        <input type="date" name="expiry_date" class="form-control shadow-none rounded-lg">
                    </div>
                </div>

                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary bg-primary border-0 px-4 fw-bold shadow-sm">Launch Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
function addOptionField() {
    const container = document.getElementById('optionsContainer');
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'options[]';
    input.placeholder = 'Next Option';
    input.className = 'form-control shadow-none rounded-lg mt-1';
    container.appendChild(input);
}

let pollModal = null;
function openPollModal() {
    if(!pollModal) pollModal = new bootstrap.Modal(document.getElementById('createPollModal'));
    pollModal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('shubx-poll-search');
    if(search) {
        search.addEventListener('keyup', (e) => {
            const val = e.target.value.toLowerCase();
            document.querySelectorAll('.shubx-poll-card').forEach(el => {
                const text = el.dataset.search || '';
                el.style.display = text.includes(val) ? '' : 'none';
            });
        });
    }
});
</script>

