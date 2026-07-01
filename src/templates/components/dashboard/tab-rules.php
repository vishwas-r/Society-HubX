<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Rules Tab
 * View and acknowledge society rules, track violations
 * @var array $data Dashboard data
 */

$db = new SNESTX51_DB_Router();
$user_id = get_current_user_id();
$residents = $db->get( 'residents', array( 'where' => array( 'wp_user_id' => $user_id ) ) );
$resident_id = !empty($residents) ? $residents[0]['id'] : '';
$flat_no = !empty($residents) ? $residents[0]['flat_no'] : '';

// Get published rules
$all_rules = $db->get( 'rules', array( 'where' => array( 'status' => 'published' ) ) );
$categories = $db->get( 'rule_categories', array( 'where' => array( 'is_active' => 1 ) ) );

// Get pending acknowledgments
global $wpdb;
$rules_table = "{$wpdb->prefix}society_nestx_rules";
$acks_table = "{$wpdb->prefix}society_nestx_rule_acknowledgments";

$pending_rules = $wpdb->get_results($wpdb->prepare("
    SELECT r.* FROM $rules_table r
    WHERE r.status = 'published' 
    AND r.requires_acknowledgment = 1
    AND NOT EXISTS (
        SELECT 1 FROM $acks_table a 
        WHERE a.rule_id = r.id 
        AND a.rule_version = r.version 
        AND a.resident_id = %s
    )
    ORDER BY r.effective_date DESC
", $resident_id), ARRAY_A);

// Get my violations
$violations = $db->get( 'rule_violations', array( 'where' => array( 'flat_no' => $flat_no ) ) );
?>

<div id="tab-rules" class="tab-content d-none">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Pending Acknowledgments Alert -->
            <?php if(!empty($pending_rules)): ?>
                <div class="alert bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning bg-opacity-20 p-3 rounded-3">
                            <i class="bi bi-exclamation-triangle-fill fs-3 text-warning"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="fw-bold mb-1">Pending Acknowledgments</h6>
                            <p class="m-0 small">You have <strong><?php echo count($pending_rules); ?> rule(s)</strong> that require your acknowledgment.</p>
                        </div>
                        <button onclick="showPendingAcknowledgments()" class="btn btn-warning px-4 fw-bold rounded-pill">
                            View & Acknowledge
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- My Violations Summary -->
            <?php if(!empty($violations)): ?>
                <?php 
                $unpaid_violations = array_filter($violations, fn($v) => $v['payment_status'] === 'unpaid');
                $total_fines = array_sum(array_column($unpaid_violations, 'fine_amount'));
                ?>
                <?php if(!empty($unpaid_violations)): ?>
                    <div class="alert bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-danger bg-opacity-20 p-3 rounded-3">
                                    <i class="bi bi-exclamation-octagon-fill fs-3 text-danger"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-1">Outstanding Violations</h6>
                                    <p class="m-0 small">You have <strong><?php echo count($unpaid_violations); ?> unpaid violation(s)</strong> • Total: ₹<?php echo number_format($total_fines, 2); ?></p>
                                </div>
                            </div>
                            <button onclick="showMyViolations()" class="btn btn-danger px-4 fw-bold rounded-pill">
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Categories Grid -->
            <div class="bg-white rounded-3 shadow-sm border border-light p-4 mb-4">
                <h5 class="fw-bold mb-4">Browse Rules by Category</h5>
                
                <?php if(empty($categories)): ?>
                    <p class="text-muted">No categories available.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach($categories as $cat): ?>
                            <?php 
                            $cat_count = count(array_filter($all_rules, fn($r) => $r['category'] === $cat['slug']));
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="p-3 border rounded-3 hover-shadow cursor-pointer" onclick="filterByCategory('<?php echo esc_attr($cat['slug']); ?>')">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="p-2 rounded-3" style="background-color: <?php echo $cat['color']; ?>20;">
                                            <i class="<?php echo $cat['icon']; ?> fs-2" style="color: <?php echo $cat['color']; ?>;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo esc_html($cat['name']); ?></div>
                                            <div class="small text-muted"><?php echo $cat_count; ?> rules</div>
                                        </div>
                                        <i class="bi bi-chevron-right text-muted"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Search & Filter -->
            <div class="bg-white rounded-3 shadow-sm border border-light p-4 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="ruleSearchResident" placeholder="Search rules by title or tags..." class="form-control ps-5 rounded-pill border-light bg-light">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="categoryFilterResident" class="form-select rounded-pill border-light bg-light">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat['slug']); ?>"><?php echo esc_html($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Rules List -->
            <div class="d-flex flex-column gap-3" id="rulesListContainer">
                <?php if(empty($all_rules)): ?>
                    <div class="text-center py-5 bg-white rounded-3 border border-light">
                        <i class="bi bi-file-text fs-1 d-block mb-3 opacity-25"></i>
                        <p class="text-muted m-0">No rules have been published yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($all_rules as $rule): ?>
                        <?php 
                        $cat_match = array_values(array_filter($categories, fn($c) => $c['slug'] === $rule['category']));
                        $cat_icon = !empty($cat_match) ? $cat_match[0]['icon'] : 'bi-file-text';
                        $cat_color = !empty($cat_match) ? $cat_match[0]['color'] : '#6c757d';
                        $cat_name = !empty($cat_match) ? $cat_match[0]['name'] : ucfirst($rule['category']);
                        
                        // Check if acknowledged
                        $is_acked = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(*) FROM $acks_table 
                            WHERE rule_id = %s AND rule_version = %d AND resident_id = %s
                        ", $rule['id'], $rule['version'], $resident_id));
                        ?>
                        <div class="rule-item bg-white rounded-3 shadow-sm border border-light p-4" 
                             data-category="<?php echo esc_attr($rule['category']); ?>"
                             data-search="<?php echo esc_attr(strtolower($rule['title'] . ' ' . $rule['tags'])); ?>">
                            
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge px-3 py-1 rounded-pill" style="background-color: <?php echo $cat_color; ?>20; color: <?php echo $cat_color; ?>; border: 1px solid <?php echo $cat_color; ?>40;">
                                            <i class="<?php echo $cat_icon; ?> me-1"></i>
                                            <?php echo $cat_name; ?>
                                        </span>
                                        <?php if($rule['priority'] === 'critical' || $rule['priority'] === 'high'): ?>
                                            <span class="badge bg-danger text-white px-2 py-1 rounded-pill">
                                                <?php echo ucfirst($rule['priority']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if($is_acked): ?>
                                            <span class="badge bg-success text-white px-2 py-1 rounded-pill">
                                                <i class="bi bi-check-circle-fill me-1"></i> Acknowledged
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-2"><?php echo esc_html($rule['title']); ?></h5>
                                    <div class="small text-muted">
                                        Effective: <?php echo $rule['effective_date'] ? wp_date('M d, Y', strtotime($rule['effective_date'])) : 'Immediately'; ?>
                                        <?php if($rule['expiry_date']): ?>
                                            • Expires: <?php echo wp_date('M d, Y', strtotime($rule['expiry_date'])); ?>
                                        <?php endif; ?>
                                        • Version <?php echo $rule['version']; ?>
                                    </div>
                                </div>
                                <button onclick="viewRuleModal(<?php echo esc_attr(json_encode($rule)); ?>, <?php echo $is_acked ? 'true' : 'false'; ?>)" 
                                        class="btn btn-primary px-4 fw-bold rounded-pill">
                                    View Rule
                                </button>
                            </div>

                            <div class="rule-preview text-secondary mb-3" style="max-height: 100px; overflow: hidden; -webkit-mask-image: linear-gradient(180deg, #000 60%, transparent);">
                                <?php echo wp_kses_post(substr(strip_tags($rule['content']), 0, 200)); ?>...
                            </div>

                            <?php if($rule['requires_acknowledgment'] && !$is_acked): ?>
                                <div class="alert bg-warning bg-opacity-10 border-0 mb-0 py-2 px-3 small">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Acknowledgment Required</strong>
                                    <?php if($rule['acknowledgment_deadline']): ?>
                                        • Deadline: <?php echo wp_date('M d, Y', strtotime($rule['acknowledgment_deadline'])); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- My Violations Section -->
            <?php if(!empty($violations)): ?>
                <div id="myViolationsSection" class="bg-white rounded-3 shadow-sm border border-light p-4 mt-4 d-none">
                    <h5 class="fw-bold mb-4">My Violations</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rule</th>
                                    <th>Date</th>
                                    <th>Fine</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($violations as $v): ?>
                                    <?php 
                                    $v_rule = array_filter($all_rules, fn($r) => $r['id'] === $v['rule_id']);
                                    $v_rule_title = !empty($v_rule) ? array_values($v_rule)[0]['title'] : 'Unknown Rule';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo esc_html($v_rule_title); ?></div>
                                            <div class="small text-muted"><?php echo esc_html($v['description']); ?></div>
                                        </td>
                                        <td class="small"><?php echo wp_date('M d, Y', strtotime($v['violation_date'])); ?></td>
                                        <td class="fw-bold">₹<?php echo number_format($v['fine_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $v['status'] === 'resolved' ? 'success' : ($v['status'] === 'dismissed' ? 'secondary' : 'warning'); ?> text-white">
                                                <?php echo ucfirst($v['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($v['status'] === 'pending' && empty($v['appeal_status'])): ?>
                                                <button onclick="appealViolation('<?php echo esc_attr($v['id']); ?>')" class="btn btn-sm btn-outline-primary rounded-pill">
                                                    <i class="bi bi-chat-square-text me-1"></i> Appeal
                                                </button>
                                            <?php elseif($v['appeal_status'] === 'pending'): ?>
                                                <span class="badge bg-info">Appeal Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Rule View Modal -->
<div class="modal fade" id="ruleViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0" id="ruleViewTitle">Rule Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="ruleViewContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3" id="ruleViewFooter">
                <!-- Footer loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- Acknowledge Rule Modal -->
<div class="modal fade" id="acknowledgeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4 bg-primary text-white">
                <h5 class="fw-bold m-0">
                    <i class="bi bi-check-circle me-2"></i> Acknowledge Rule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="acknowledgeForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="ack_rule_id" name="rule_id">
                    
                    <div class="alert bg-warning bg-opacity-10 border-warning mb-4">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        By acknowledging this rule, you confirm that you have read and understood it, and agree to comply with its provisions.
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="ackConfirm" required>
                        <label class="form-check-label fw-bold" for="ackConfirm">
                            I have read and understood the above rule and agree to comply
                        </label>
                    </div>

                    <!-- Optional Signature Pad -->
                    <!-- <div class="mb-3">
                        <label class="form-label small fw-bold">Digital Signature (Optional)</label>
                        <div class="border rounded-3 p-2 bg-light text-center">
                            <canvas id="signaturePad" width="400" height="100"></canvas>
                            <button type="button" class="btn btn-sm btn-link" onclick="clearSignature()">Clear</button>
                        </div>
                    </div> -->
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-check-circle me-2"></i> Submit Acknowledgment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Appeal Violation Modal -->
<div class="modal fade" id="appealModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0">Appeal Violation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="appealForm">
                <div class="modal-body p-4">
                    <input type="hidden" id="appeal_violation_id" name="violation_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Appeal <span class="text-danger">*</span></label>
                        <textarea name="appeal_reason" class="form-control rounded-3" rows="4" required placeholder="Explain why you believe this violation should be reviewed..."></textarea>
                    </div>

                    <div class="alert bg-info bg-opacity-10 border-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Your appeal will be reviewed by the committee. You will be notified of the decision.
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Submit Appeal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let ruleViewModal, acknowledgeModal, appealModal;

document.addEventListener('DOMContentLoaded', () => {
    ruleViewModal = new bootstrap.Modal(document.getElementById('ruleViewModal'));
    acknowledgeModal = new bootstrap.Modal(document.getElementById('acknowledgeModal'));
    appealModal = new bootstrap.Modal(document.getElementById('appealModal'));

    // Search filter
    document.getElementById('ruleSearchResident')?.addEventListener('input', filterRulesResident);
    document.getElementById('categoryFilterResident')?.addEventListener('change', filterRulesResident);

    // Acknowledge form
    document.getElementById('acknowledgeForm')?.addEventListener('submit', handleAcknowledge);
    
    // Appeal form
    document.getElementById('appealForm')?.addEventListener('submit', handleAppeal);
});

function filterRulesResident() {
    const search = document.getElementById('ruleSearchResident').value.toLowerCase();
    const category = document.getElementById('categoryFilterResident').value;

    document.querySelectorAll('.rule-item').forEach(item => {
        const searchText = item.dataset.search || '';
        const itemCategory = item.dataset.category || '';
        
        const matchesSearch = !search || searchText.includes(search);
        const matchesCategory = !category || itemCategory === category;

        item.style.display= (matchesSearch && matchesCategory) ? '' : 'none';
    });
}

function filterByCategory(category) {
    document.getElementById('categoryFilterResident').value = category;
    filterRulesResident();
    // Scroll to rules list
    document.getElementById('rulesListContainer').scrollIntoView({ behavior: 'smooth' });
}

function viewRuleModal(rule, isAcknowledged) {
    document.getElementById('ruleViewTitle').textContent = rule.title;
    
    let content = '<div class="mb-4">' + rule.content + '</div>';
    content += '<div class="border-top pt-3 small text-muted">';
    content += 'Effective Date: ' + (rule.effective_date || 'Immediately') + '<br>';
    content += 'Version: ' + rule.version;
    if(rule.fine_amount > 0) {
        content += '<br>Violation Fine: ₹' + parseFloat(rule.fine_amount).toFixed(2);
    }
    content += '</div>';
    
    document.getElementById('ruleViewContent').innerHTML = content;
    
    let footer = '<button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>';
    if(rule.requires_acknowledgment && !isAcknowledged) {
        footer += '<button type="button" class="btn btn-primary px-4 fw-bold" onclick="openAcknowledgeModal(\'' + rule.id + '\')"><i class="bi bi-check-circle me-2"></i> Acknowledge Now</button>';
    }
    
    document.getElementById('ruleViewFooter').innerHTML = footer;
    
    ruleViewModal.show();
}

function openAcknowledgeModal(ruleId) {
    ruleViewModal.hide();
    document.getElementById('ack_rule_id').value = ruleId;
    document.getElementById('ackConfirm').checked = false;
    acknowledgeModal.show();
}

function handleAcknowledge(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'SNESTX51_acknowledge_rule',
            rule_id: formData.get('rule_id'),
            _wpnonce: '<?php echo wp_create_nonce('SNESTX51_frontend_nonce'); ?>'
        },
        success: function(response) {
            if(response.success) {
                acknowledgeModal.hide();
                SNESTXShowToast('Rule acknowledged successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                SNESTXShowToast(response.data?.message || 'Error acknowledging rule', 'error');
            }
        },
        error: function() {
            SNESTXShowToast('Error communicating with server', 'error');
        }
    });
}

function showPendingAcknowledgments() {
    // Filter to show only rules requiring acknowledgment
    document.querySelectorAll('.rule-item').forEach(item => {
        const hasAckBadge = item.querySelector('.badge.bg-success');
        item.style.display = hasAckBadge ? 'none' : '';
    });
    document.getElementById('rulesListContainer').scrollIntoView({ behavior: 'smooth' });
}

function showMyViolations() {
    const section = document.getElementById('myViolationsSection');
    if(section) {
        section.classList.remove('d-none');
        section.scrollIntoView({ behavior: 'smooth' });
    }
}

function appealViolation(violationId) {
    document.getElementById('appeal_violation_id').value = violationId;
    document.getElementById('appealForm').reset();
    appealModal.show();
}

function handleAppeal(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'SNESTX51_appeal_violation',
            violation_id: formData.get('violation_id'),
            appeal_reason: formData.get('appeal_reason'),
            _wpnonce: '<?php echo wp_create_nonce('SNESTX51_frontend_nonce'); ?>'
        },
        success: function(response) {
            if(response.success) {
                appealModal.hide();
                SNESTXShowToast('Appeal submitted successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                alert(response.data.message || 'Error submitting appeal');
            }
        }
    });
}
</script>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}
.hover-shadow:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    cursor: pointer;
}
</style>
