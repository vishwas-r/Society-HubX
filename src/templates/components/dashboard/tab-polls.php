<?php
/**
 * Component: Dashboard Polls Tab
 * @var array $data Dashboard data.
 */
$poll_mgr = new SGVX51_Poll_Manager();
$all_polls = (new SGVX51_DB_Router())->get('polls');
$r = $data['resident'] ?? [];
?>
<!-- 6. POLLS TAB -->
<div id="tab-polls" class="tab-content d-none">
    <?php if (empty($all_polls)): ?>
        <div class="text-center py-5 text-muted border border-dashed border-light rounded-3">No active polls.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach($all_polls as $p): 
                 $voted = $poll_mgr->has_voted($p['id'], $r['flat_no'] ?? '');
            ?>
                <div class="col-md-6">
                    <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100">
                        <div class="d-flex justify-content-between mb-2">
                            <h5 class="fw-bold text-dark m-0"><?php echo esc_html($p['title']); ?></h5>
                            <?php if($voted): ?> <span class="badge bg-success-subtle text-success">Voted</span> <?php endif; ?>
                        </div>
                        <p class="small text-secondary mb-3"><?php echo esc_html($p['description']); ?></p>
                        <!-- Vote Form simplified -->
                        <?php if(!$voted): ?>
                            <form action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
                                <input type="hidden" name="action" value="sgvx51_cast_vote">
                                <input type="hidden" name="poll_id" value="<?php echo esc_attr($p['id']); ?>">
                                <?php wp_nonce_field('sgvx51_vote_nonce'); ?>
                                <div class="d-flex flex-column gap-2 mb-3">
                                    <?php 
                                    $options = is_string($p['options']) ? json_decode($p['options'], true) : $p['options'];
                                    foreach(($options ?? []) as $opt): $opt_id = uniqid('opt_'); ?>
                                        <div class="form-check p-0 border rounded border-light hover-bg-light position-relative">
                                            <div class="d-flex align-items-center p-2">
                                                <input class="form-check-input ms-2 me-3" type="radio" name="vote_option" id="<?php echo $opt_id; ?>" value="<?php echo esc_attr($opt); ?>" required>
                                                <label class="form-check-label w-100 stretched-link small fw-medium cursor-pointer" for="<?php echo $opt_id; ?>">
                                                    <?php echo esc_html($opt); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="btn btn-primary w-100 btn-sm rounded-3">Vote</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light border border-light small text-center m-0">Thanks for voting!</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
