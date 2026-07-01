<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Polls Tab
 * @var array $data Dashboard data.
 */
$poll_mgr = new SNESTX51_Poll_Manager();
$all_polls = (new SNESTX51_DB_Router())->get('polls');
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
                        <?php 
                        $user_vote = $poll_mgr->get_user_vote($p['id'], $r['flat_no'] ?? '');
                        $has_voted = !empty($user_vote);
                        $results = $poll_mgr->get_results($p['id']);
                        $total_votes = $results['total'] ?? 0;
                        ?>

                        <div id="poll-container-<?php echo esc_attr($p['id']); ?>">
                            <!-- RESULTS VIEW -->
                            <div class="js-poll-results <?php echo $has_voted ? '' : 'd-none'; ?>">
                                <div class="d-flex flex-column gap-3 mb-3">
                                    <?php 
                                    $options = is_string($p['options']) ? json_decode($p['options'], true) : $p['options'];
                                    foreach(($options ?? []) as $opt): 
                                        $count = $results['counts'][$opt] ?? 0;
                                        $percent = ($total_votes > 0) ? round(($count / $total_votes) * 100) : 0;
                                        $is_my_choice = ($has_voted && $user_vote['option'] === $opt);
                                    ?>
                                        <div class="poll-result-item">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <span class="small fw-medium <?php echo $is_my_choice ? 'text-primary' : 'text-dark'; ?>">
                                                    <?php echo esc_html($opt); ?>
                                                    <?php if($is_my_choice): ?>
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size: 8px;">Your Choice</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="small text-muted"><?php echo $percent; ?>%</span>
                                            </div>
                                            <div class="progress rounded-pill" style="height: 8px; background-color: #f0f2f5;">
                                                <div class="progress-bar <?php echo $is_my_choice ? 'bg-primary' : 'bg-secondary'; ?> opacity-75 rounded-pill" role="progressbar" style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted" style="font-size: 11px;"><i class="bi bi-people me-1"></i><?php echo $total_votes; ?> votes total</span>
                                    <?php if($p['status'] === 'open'): ?>
                                        <button class="btn btn-link btn-sm p-0 text-decoration-none fw-medium js-change-vote" style="font-size: 11px;">Change Vote</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- VOTE FORM -->
                            <div class="js-poll-form <?php echo $has_voted ? 'd-none' : ''; ?>">
                                <form action="<?php echo admin_url('admin-post.php'); ?>" method="POST" class="js-poll-vote-form" data-poll-id="<?php echo esc_attr($p['id']); ?>">
                                    <input type="hidden" name="action" value="SNESTX51_cast_vote">
                                    <input type="hidden" name="poll_id" value="<?php echo esc_attr($p['id']); ?>">
                                    <?php wp_nonce_field('SNESTX51_vote_nonce'); ?>
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        <?php 
                                        foreach(($options ?? []) as $opt): $opt_id = uniqid('opt_'); 
                                            $is_my_choice = ($has_voted && $user_vote['option'] === $opt);
                                        ?>
                                            <div class="form-check p-0 border rounded border-light hover-bg-light position-relative <?php echo $is_my_choice ? 'border-primary bg-primary-subtle bg-opacity-10' : ''; ?>">
                                                <div class="d-flex align-items-center p-2">
                                                    <input class="form-check-input ms-2 me-3 shadow-none" type="radio" name="vote_option" id="<?php echo $opt_id; ?>" value="<?php echo esc_attr($opt); ?>" <?php checked($is_my_choice); ?> required>
                                                    <label class="form-check-label w-100 stretched-link small fw-medium cursor-pointer" for="<?php echo $opt_id; ?>">
                                                        <?php echo esc_html($opt); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1 btn-sm rounded-3">Confirm Vote</button>
                                        <?php if($has_voted): ?>
                                            <button type="button" class="btn btn-light btn-sm rounded-3 border-light js-cancel-change">Cancel</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
