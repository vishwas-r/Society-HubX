<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View: Rules & Regulations
 * Manages society rules, acknowledgments, and violations
 */

$db = new SNESTX51_DB_Router();

// Get data
$rules = isset($rules) ? $rules : $db->get('rules');
$categories = isset($categories) ? $categories : $db->get('rule_categories', ['is_active' => 1]);
$violations = isset($violations) ? $violations : $db->get('rule_violations');

// Calculate stats
$total_rules = count(array_filter($rules, fn($r) => $r['status'] === 'published'));
$draft_rules = count(array_filter($rules, fn($r) => $r['status'] === 'draft'));
$total_violations = count($violations);
$pending_violations = count(array_filter($violations, fn($v) => $v['status'] === 'pending'));

global $wpdb;
$acks_table = "{$wpdb->prefix}Society_NestX_rule_acknowledgments";
$total_acks = isset($total_acknowledgments) ? $total_acknowledgments : $wpdb->get_var("SELECT COUNT(*) FROM $acks_table");
?>

<!-- Page Header -->
<div class="mb-5 px-1">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
        <div>
            <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Rules & Regulations</h1>
            <p class="text-secondary m-0 mt-1">Manage society bylaws, acknowledgments, and violations</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button onclick="openAddRuleModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                <i class="bi bi-plus-circle-fill fs-5"></i>
                <span>Add Rule</span>
            </button>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 bg-primary text-white rounded-3 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-3">
                    <i class="bi bi-file-text fs-4"></i>
                </div>
            </div>
            <p class="small opacity-75 fw-medium mb-1">Total Published Rules</p>
            <h2 class="h2 fw-bold m-0"><?php echo $total_rules; ?></h2>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 bg-warning text-dark rounded-3 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="bg-white bg-opacity-40 p-2 rounded-3">
                    <i class="bi bi-pencil-square fs-4"></i>
                </div>
            </div>
            <p class="small opacity-75 fw-medium mb-1">Draft Rules</p>
            <h2 class="h2 fw-bold m-0"><?php echo $draft_rules; ?></h2>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 bg-success text-white rounded-3 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-3">
                    <i class="bi bi-check-circle fs-4"></i>
                </div>
            </div>
            <p class="small opacity-75 fw-medium mb-1">Total Acknowledgments</p>
            <h2 class="h2 fw-bold m-0"><?php echo $total_acks; ?></h2>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="card border-0 bg-danger text-white rounded-3 shadow-sm p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="bg-white bg-opacity-20 p-2 rounded-3">
                    <i class="bi bi-exclamation-triangle fs-4"></i>
                </div>
            </div>
            <p class="small opacity-75 fw-medium mb-1">Total Violations</p>
            <h2 class="h2 fw-bold m-0"><?php echo $total_violations; ?></h2>
        </div>
    </div>
</div>

<!-- Main Content Card with Tabs -->
<div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
    
    <!-- Tabs Navigation -->
    <div class="px-5 bg-white border-bottom border-light">
        <ul class="nav nav-tabs border-0 gap-5" id="rulesTabs">
            <li class="nav-item">
                <button onclick="switchRulesTab('all-rules')" id="tab-btn-all-rules" class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" style="background:none;">All Rules</button>
            </li>
            <li class="nav-item">
                <button onclick="switchRulesTab('categories')" id="tab-btn-categories" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" style="background:none;">Categories</button>
            </li>
            <li class="nav-item">
                <button onclick="switchRulesTab('violations')" id="tab-btn-violations" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent d-flex align-items-center gap-2" style="background:none;">
                    Violations
                    <?php if($pending_violations > 0): ?>
                        <span class="badge rounded-pill bg-danger px-2"><?php echo $pending_violations; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item">
                <button onclick="switchRulesTab('acknowledgments')" id="tab-btn-acknowledgments" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" style="background:none;">Acknowledgments</button>
            </li>
             <li class="nav-item">
                <button onclick="switchRulesTab('reports')" id="tab-btn-reports" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" style="background:none;">Reports</button>
            </li>
        </ul>
    </div>

    <!-- Tab: All Rules -->
    <div id="view-all-rules" class="tab-pane">
        <div class="p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="flex-grow-1">
                    <input type="text" id="rulesSearch" placeholder="Search rules by title, category, or tags..." class="form-control bg-light border-0 rounded-3" style="max-width: 500px;">
                </div>
                <div class="d-flex gap-2">
                    <select id="filterStatus" class="form-select bg-light border-0 rounded-3" style="width: auto;">
                        <option value="">All Status</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                    <select id="filterCategory" class="form-select bg-light border-0 rounded-3" style="width: auto;">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat['slug']); ?>"><?php echo esc_html($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold">Title</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Category</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Priority</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Status</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Effective Date</th>
                        <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($rules)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-file-text fs-1 d-block mb-3 opacity-25"></i>
                                <p>No rules found. Click "Add Rule" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($rules as $rule): ?>
                            <tr class="rule-row border-bottom" 
                                data-status="<?php echo esc_attr($rule['status']); ?>" 
                                data-category="<?php echo esc_attr($rule['category']); ?>"
                                data-search="<?php echo esc_attr(strtolower($rule['title'] . ' ' . $rule['category'] . ' ' . $rule['tags'])); ?>">
                                <td class="ps-5 py-4">
                                    <div class="fw-bold text-dark"><?php echo esc_html($rule['title']); ?></div>
                                    <div class="small text-muted">v<?php echo $rule['version']; ?> • <?php echo $rule['requires_acknowledgment'] ? 'Requires Acknowledgment' : 'No Acknowledgment'; ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $cat = array_values(array_filter($categories, fn($c) => $c['slug'] === $rule['category']));
                                    $cat_icon = !empty($cat) ? $cat[0]['icon'] : 'bi-file-text';
                                    $cat_color = !empty($cat) ? $cat[0]['color'] : '#6c757d';
                                    $cat_name = !empty($cat) ? $cat[0]['name'] : ucfirst($rule['category']);
                                    ?>
                                    <span class="badge px-3 py-2 rounded-pill fw-bold" style="background-color: <?php echo $cat_color; ?>20; color: <?php echo $cat_color; ?>; border: 1px solid <?php echo $cat_color; ?>40;">
                                        <i class="<?php echo $cat_icon; ?> me-1"></i>
                                        <?php echo esc_html($cat_name); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $priority_badges = [
                                        'low' => ['class' => 'bg-secondary', 'text' => 'Low'],
                                        'medium' => ['class' => 'bg-info', 'text' => 'Medium'],
                                        'high' => ['class' => 'bg-warning', 'text' => 'High'],
                                        'critical' => ['class' => 'bg-danger', 'text' => 'Critical']
                                    ];
                                    $p = $priority_badges[$rule['priority']] ?? $priority_badges['medium'];
                                    ?>
                                    <span class="badge <?php echo $p['class']; ?> text-white px-3 py-1 rounded-pill"><?php echo $p['text']; ?></span>
                                </td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $status_badges = [
                                        'draft' => ['class' => 'bg-secondary', 'icon' => 'bi-pencil'],
                                        'published' => ['class' => 'bg-success', 'icon' => 'bi-check-circle'],
                                        'archived' => ['class' => 'bg-dark', 'icon' => 'bi-archive']
                                    ];
                                    $s = $status_badges[$rule['status']] ?? $status_badges['draft'];
                                    ?>
                                    <span class="badge <?php echo $s['class']; ?> text-white px-3 py-1 rounded-pill">
                                        <i class="<?php echo $s['icon']; ?> me-1"></i>
                                        <?php echo ucfirst($rule['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 small text-secondary">
                                    <?php echo $rule['effective_date'] ? wp_date('M d, Y', strtotime($rule['effective_date'])) : 'Not set'; ?>
                                </td>
                                <td class="pe-5 py-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button onclick="editRule(<?php echo esc_attr(json_encode($rule)); ?>)" class="btn btn-sm btn-light border px-3 py-2 rounded-3" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button onclick="viewVersionHistory('<?php echo esc_attr($rule['id']); ?>')" class="btn btn-sm btn-light border px-3 py-2 rounded-3" title="Version History">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <?php if($rule['status'] === 'draft'): ?>
                                            <button onclick="publishRule('<?php echo esc_attr($rule['id']); ?>')" class="btn btn-sm btn-success px-3 py-2 rounded-3" title="Publish">
                                                <i class="bi bi-send"></i> Publish
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteRule('<?php echo esc_attr($rule['id']); ?>')" class="btn btn-sm btn-light border text-danger px-3 py-2 rounded-3" title="Archive">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab: Categories -->
    <div id="view-categories" class="tab-pane d-none">
        <div class="p-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Rule Categories</h5>
                <button onclick="openAddCategoryModal()" class="btn btn-sm btn-primary px-4 rounded-3">
                    <i class="bi bi-plus-circle me-2"></i>Add Category
                </button>
            </div>

            <div class="row g-4">
                <?php foreach($categories as $cat): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card border rounded-3 h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="p-3 rounded-3" style="background-color: <?php echo $cat['color']; ?>20;">
                                        <i class="<?php echo $cat['icon']; ?> fs-2" style="color: <?php echo $cat['color']; ?>;"></i>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border-0" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="editCategory(<?php echo esc_attr(json_encode($cat)); ?>); return false;">
                                                <i class="bi bi-pencil me-2"></i>Edit
                                            </a></li>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteCategory('<?php echo esc_attr($cat['id']); ?>'); return false;">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>
                                <h6 class="fw-bold mb-2"><?php echo esc_html($cat['name']); ?></h6>
                                <p class="small text-muted mb-3"><?php echo esc_html($cat['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="text-secondary">
                                        <?php 
                                        $count = count(array_filter($rules, fn($r) => $r['category'] === $cat['slug']));
                                        echo $count . ' ' . ($count === 1 ? 'rule' : 'rules');
                                        ?>
                                    </span>
                                    <span class="badge rounded-pill px-3 py-1" style="background-color: <?php echo $cat['color']; ?>; color: white;">
                                        <?php echo esc_html($cat['slug']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Violations -->
    <div id="view-violations" class="tab-pane d-none">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom">
                    <tr>
                        <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold">Flat</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Rule</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Date</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Fine</th>
                        <th class="px-4 py-4 text-uppercase small text-secondary fw-bold">Status</th>
                        <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($violations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-shield-check fs-1 d-block mb-3 opacity-25"></i>
                                <p>No violations reported yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach(array_reverse($violations) as $v): ?>
                            <tr class="border-bottom">
                                <td class="ps-5 py-4">
                                    <div class="fw-bold"><?php echo esc_html($v['flat_no']); ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $rule_data = array_filter($rules, fn($r) => $r['id'] === $v['rule_id']);
                                    $rule_title = !empty($rule_data) ? array_values($rule_data)[0]['title'] : 'Unknown Rule';
                                    ?>
                                    <div class="fw-bold text-dark"><?php echo esc_html($rule_title); ?></div>
                                    <div class="small text-muted"><?php echo esc_html($v['description']); ?></div>
                                </td>
                                <td class="px-4 py-4 small text-secondary">
                                    <?php echo wp_date('M d, Y', strtotime($v['violation_date'])); ?>
                                </td>
                                <td class="px-4 py-4 fw-bold">₹<?php echo number_format($v['fine_amount'], 2); ?></td>
                                <td class="px-4 py-4">
                                    <?php 
                                    $vio_status = [
                                        'pending' => ['class' => 'bg-warning', 'text' => 'Pending'],
                                        'confirmed' => ['class' => 'bg-danger', 'text' => 'Confirmed'],
                                        'appealed' => ['class' => 'bg-info', 'text' => 'Appealed'],
                                        'dismissed' => ['class' => 'bg-secondary', 'text' => 'Dismissed'],
                                        'resolved' => ['class' => 'bg-success', 'text' => 'Resolved']
                                    ];
                                    $vs = $vio_status[$v['status']] ?? $vio_status['pending'];
                                    ?>
                                    <span class="badge <?php echo $vs['class']; ?> text-white px-3 py-1 rounded-pill"><?php echo $vs['text']; ?></span>
                                </td>
                                <td class="pe-5 py-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button onclick="viewViolation(<?php echo esc_attr(json_encode($v)); ?>)" class="btn btn-sm btn-light border px-3 py-2 rounded-3">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if($v['status'] === 'pending' || $v['status'] === 'appealed'): ?>
                                            <button onclick="resolveViolation('<?php echo esc_attr($v['id']); ?>')" class="btn btn-sm btn-success px-3 py-2 rounded-3">
                                                <i class="bi bi-check-circle"></i> Resolve
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab: Acknowledgments -->
    <div id="view-acknowledgments" class="tab-pane d-none">
        <div class="p-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0">Acknowledgment Dashboard</h5>
                <button onclick="sendReminders()" class="btn btn-sm btn-primary px-4 rounded-3">
                    <i class="bi bi-bell me-2"></i>Send Reminders
                </button>
            </div>

            <?php
            // Get acknowledgment stats per rule
            $rules_table = "{$wpdb->prefix}Society_NestX_rules";
            $residents_table = "{$wpdb->prefix}Society_NestX_residents";
            $ack_stats = $wpdb->get_results("
                SELECT r.id, r.title, r.requires_acknowledgment, r.acknowledgment_deadline,
                       COUNT(DISTINCT a.resident_id) as ack_count,
                       (SELECT COUNT(*) FROM $residents_table WHERE status = 'approved') as total_residents
                FROM $rules_table r
                LEFT JOIN $acks_table a ON r.id = a.rule_id AND r.version = a.rule_version
                WHERE r.status = 'published' AND r.requires_acknowledgment = 1
                GROUP BY r.id
                ORDER BY r.created_at DESC
            ", ARRAY_A);
            ?>

            <div  class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Rule Title</th>
                            <th class="px-4 py-3">Deadline</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3">Compliance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($ack_stats)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">No rules requiring acknowledgment.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($ack_stats as $stat): ?>
                                <?php 
                                $total_res = intval($stat['total_residents']);
                                $ack_count = intval($stat['ack_count']);
                                $compliance = $total_res > 0 ? round(($ack_count / $total_res) * 100) : 0;
                                ?>
                                <tr>
                                    <td class="ps-4 py-3 fw-bold"><?php echo esc_html($stat['title']); ?></td>
                                    <td class="px-4 py-3 small">
                                        <?php echo $stat['acknowledgment_deadline'] ? wp_date('M d, Y', strtotime($stat['acknowledgment_deadline'])) : 'No deadline'; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="progress flex-grow-1" style="height: 8px;">
                                                <div class="progress-bar <?php echo $compliance >= 80 ? 'bg-success' : ($compliance >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                     style="width: <?php echo $compliance; ?>%"></div>
                                            </div>
                                            <span class="small fw-bold"><?php echo $ack_count; ?>/<?php echo $total_res; ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="badge <?php echo $compliance >= 80 ? 'bg-success' : ($compliance >= 50 ? 'bg-warning' : 'bg-danger'); ?> text-white px-3 py-1 rounded-pill">
                                            <?php echo $compliance; ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab: Reports -->
    <div id="view-reports" class="tab-pane d-none">
        <div class="p-5">
            <h5 class="fw-bold mb-4">Rules & Violations Reports</h5>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border rounded-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Violations by Category</h6>
                            <?php
                            $violation_by_category = [];
                            foreach($violations as $v) {
                                $rule_match = array_filter($rules, fn($r) => $r['id'] === $v['rule_id']);
                                if(!empty($rule_match)) {
                                    $cat = array_values($rule_match)[0]['category'];
                                    $violation_by_category[$cat] = ($violation_by_category[$cat] ?? 0) + 1;
                                }
                            }
                            arsort($violation_by_category);
                            ?>
                            <?php if(empty($violation_by_category)): ?>
                                <p class="text-muted small">No violation data available.</p>
                            <?php else: ?>
                                <?php foreach($violation_by_category as $cat => $count): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span><?php echo ucfirst($cat); ?></span>
                                            <span class="fw-bold"><?php echo $count; ?> violations</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo (count($violations) > 0 ? ($count/count($violations))*100 : 0); ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border rounded-3">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">Fine Collection Summary</h6>
                            <?php
                            $total_fines = array_sum(array_column($violations, 'fine_amount'));
                            $paid_fines = array_sum(array_column(array_filter($violations, fn($v) => $v['payment_status'] === 'paid'), 'fine_amount'));
                            $unpaid_fines = $total_fines - $paid_fines;
                            $collection_rate = $total_fines > 0 ? round(($paid_fines / $total_fines) * 100) : 0;
                            ?>
                            <div class="mb-4">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="small text-secondary">Collection Rate</span>
                                    <span class="fw-bold"><?php echo $collection_rate; ?>%</span>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $collection_rate; ?>%"></div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-light">
                                        <div class="small text-secondary mb-1">Total Fines</div>
                                        <div class="h5 fw-bold m-0">₹<?php echo number_format($total_fines, 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-light">
                                        <div class="small text-secondary mb-1">Collected</div>
                                        <div class="h5 fw-bold m-0 text-success">₹<?php echo number_format($paid_fines, 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-light">
                                        <div class="small text-secondary mb-1">Pending</div>
                                        <div class="h5 fw-bold m-0 text-danger">₹<?php echo number_format($unpaid_fines, 2); ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 rounded-3 bg-light">
                                        <div class="small text-secondary mb-1">Total Cases</div>
                                        <div class="h5 fw-bold m-0"><?php echo count($violations); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
// Modals
add_action('SNESTX51_admin_modals', function() use ($categories) {
    $nonce = wp_create_nonce('SNESTX51_rule_nonce');
?>

<!-- Add/Edit Rule Modal -->
<div class="modal fade" id="ruleModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0" id="ruleModalTitle">Add New Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="ruleForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="rule_id" id="rule_id">
                    <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="rule_title" class="form-control rounded-3 shadow-none" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                            <select name="category" id="rule_category" class="form-select rounded-3 shadow-none" required>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat['slug']); ?>"><?php echo esc_html($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="rule_priority" class="form-select rounded-3 shadow-none" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Rule Content <span class="text-danger">*</span></label>
                        <textarea name="content" id="rule_content" class="form-control rounded-3 shadow-none" rows="6" required></textarea>
                        <small class="text-muted">You can use HTML for formatting</small>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Effective Date</label>
                            <input type="date" name="effective_date" id="rule_effective_date" class="form-control rounded-3 shadow-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Expiry Date</label>
                            <input type="date" name="expiry_date" id="rule_expiry_date" class="form-control rounded-3 shadow-none">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="requires_acknowledgment" id="rule_requires_ack" class="form-check-input" value="1" checked>
                            <label class="form-check-label fw-bold small" for="rule_requires_ack">
                                Requires Resident Acknowledgment
                            </label>
                        </div>
                    </div>

                    <div class="row g-3 mb-3" id="ackFieldsContainer">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Acknowledgment Deadline</label>
                            <input type="date" name="acknowledgment_deadline" id="rule_ack_deadline" class="form-control rounded-3 shadow-none">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Default Fine Amount (₹)</label>
                            <input type="number" step="0.01" name="fine_amount" id="rule_fine_amount" class="form-control rounded-3 shadow-none" value="0" min="0">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Tags</label>
                        <input type="text" name="tags" id="rule_tags" class="form-control rounded-3 shadow-none" placeholder="Comma-separated tags">
                        <small class="text-muted">e.g. parking, noise, pets</small>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="status" id="rule_status" class="form-select rounded-3 shadow-none">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0" id="categoryModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="categoryForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="category_id" id="category_id">
                    <input type="hidden" name="_wpnonce" value="<?php echo $nonce; ?>">
                    <input type="hidden" name="category_action" id="category_action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="category_name" class="form-control rounded-3 shadow-none" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Description</label>
                        <textarea name="description" id="category_description" class="form-control rounded-3 shadow-none" rows="2"></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Icon (Bootstrap Icon class)</label>
                            <input type="text" name="icon" id="category_icon" class="form-control rounded-3 shadow-none" value="bi-file-text" placeholder="e.g. bi-car-front">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Color</label>
                            <input type="color" name="color" id="category_color" class="form-control form-control-color rounded-3 shadow-none" value="#6c757d">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Violation Modal -->
<div class="modal fade" id="violationModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0">Violation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="violationDetails">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer border-0 bg-light px-4 py-3">
                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Version History Modal -->
<div class="modal fade" id="versionHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0">Version History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="versionHistoryContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php }); ?>

<script>
// Global nonce for AJAX requests
const rulesNonce = '<?php echo wp_create_nonce('SNESTX51_rule_nonce'); ?>';

let ruleModal, categoryModal, violationModal, versionHistoryModal;

document.addEventListener('DOMContentLoaded', () => {
    ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
    categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    violationModal = new bootstrap.Modal(document.getElementById('violationModal'));
    versionHistoryModal = new bootstrap.Modal(document.getElementById('versionHistoryModal'));
    
    // Toggle acknowledgment fields
    document.getElementById('rule_requires_ack').addEventListener('change', function() {
        document.getElementById('ackFieldsContainer').style.display = this.checked ? '' : 'none';
    });

    // Rules search
    const rulesSearch = document.getElementById('rulesSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterCategory = document.getElementById('filterCategory');

    [rulesSearch, filterStatus, filterCategory].forEach(el => {
        if(el) el.addEventListener('input', filterRules);
    });

    // Rule form submit
    const ruleForm = document.getElementById('ruleForm');
    if(ruleForm) {
        ruleForm.addEventListener('submit', handleRuleSubmit);
    }

    // Category form submit
    const categoryForm = document.getElementById('categoryForm');
    if(categoryForm) {
        categoryForm.addEventListener('submit', handleCategorySubmit);
    }
});

function filterRules() {
    const search = document.getElementById('rulesSearch').value.toLowerCase();
    const status = document.getElementById('filterStatus').value;
    const category = document.getElementById('filterCategory').value;

    document.querySelectorAll('.rule-row').forEach(row => {
        const searchText = row.dataset.search || '';
        const rowStatus = row.dataset.status || '';
        const rowCategory = row.dataset.category || '';

        const matchesSearch = !search || searchText.includes(search);
        const matchesStatus = !status || rowStatus === status;
        const matchesCategory = !category || rowCategory === category;

        row.style.display = (matchesSearch && matchesStatus && matchesCategory) ? '' : 'none';
    });
}

function switchRulesTab(tab) {
    // Hide all tabs
    ['all-rules', 'categories', 'violations', 'acknowledgments', 'reports'].forEach(t => {
        const view = document.getElementById('view-' + t);
        const btn = document.getElementById('tab-btn-' + t);
        if(view) view.classList.add('d-none');
        if(btn) {
            btn.classList.remove('active', 'border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-muted');
        }
    });

    // Show selected tab
    const view = document.getElementById('view-' + tab);
    const btn = document.getElementById('tab-btn-' + tab);
    if(view) view.classList.remove('d-none');
    if(btn) {
        btn.classList.add('active', 'border-primary', 'text-primary');
        btn.classList.remove('border-transparent', 'text-muted');
    }
}

function openAddRuleModal() {
    document.getElementById('ruleForm').reset();
    document.getElementById('rule_id').value = '';
    document.getElementById('ruleModalTitle').textContent = 'Add New Rule';
    ruleModal.show();
}

function editRule(rule) {
    document.getElementById('rule_id').value = rule.id;
    document.getElementById('rule_title').value = rule.title;
    document.getElementById('rule_category').value = rule.category;
    document.getElementById('rule_priority').value = rule.priority;
    document.getElementById('rule_content').value = rule.content;
    document.getElementById('rule_effective_date').value = rule.effective_date || '';
    document.getElementById('rule_expiry_date').value = rule.expiry_date || '';
    document.getElementById('rule_requires_ack').checked = rule.requires_acknowledgment == 1;
    document.getElementById('rule_ack_deadline').value = rule.acknowledgment_deadline || '';
    document.getElementById('rule_fine_amount').value = rule.fine_amount;
    document.getElementById('rule_tags').value = rule.tags || '';
    document.getElementById('rule_status').value = rule.status;
    document.getElementById('ruleModalTitle').textContent = 'Edit Rule';
    document.getElementById('ackFieldsContainer').style.display = rule.requires_acknowledgment == 1 ? '' : 'none';
    ruleModal.show();
}

function handleRuleSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const isEdit = formData.get('rule_id') !== '';

    SNESTX.ajax({
        action: isEdit ? 'SNESTX51_edit_rule' : 'SNESTX51_add_rule',
        data: Object.fromEntries(formData),
        loadingButton: jQuery(e.target).find('button[type="submit"]'),
        successMessage: 'Rule saved successfully!',
        reload: true,
        onSuccess: function() {
            ruleModal.hide();
        }
    });
}

function publishRule(ruleId) {
    if(!confirm('Publish this rule? Residents will be notified.')) return;

    SNESTX.ajax({
        action: 'SNESTX51_publish_rule',
        data: {
            rule_id: ruleId,
            _wpnonce: rulesNonce
        },
        successMessage: 'Rule published successfully!',
        reload: true
    });
}

function deleteRule(ruleId) {
    if(!confirm('Archive this rule? It will be hidden from residents.')) return;

    SNESTX.ajax({
        action: 'SNESTX51_delete_rule',
        data: {
            rule_id: ruleId,
            _wpnonce: rulesNonce
        },
        successMessage: 'Rule archived successfully!',
        reload: true
    });
}

function viewVersionHistory(ruleId) {
    document.getElementById('versionHistoryContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
    versionHistoryModal.show();

    SNESTX.ajax({
        action: 'SNESTX51_get_version_history',
        data: {
            rule_id: ruleId,
            _wpnonce: rulesNonce
        },
        onSuccess: function(data) {
            if(data.versions) {
                let html = '<div class="timeline">';
                data.versions.forEach(v => {
                    html += `
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-primary">v${v.version}</span>
                                    <strong class="ms-2">${v.title}</strong>
                                </div>
                                <small class="text-muted">${v.changed_at}</small>
                            </div>
                            <p class="small text-muted mb-0">${v.change_summary}</p>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('versionHistoryContent').innerHTML = html;
            }
        }
    });
}

function openAddCategoryModal() {
    document.getElementById('categoryForm').reset();
    document.getElementById('category_id').value = '';
    document.getElementById('category_action').value = 'add';
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    categoryModal.show();
}

function editCategory(cat) {
    document.getElementById('category_id').value = cat.id;
    document.getElementById('category_name').value = cat.name;
    document.getElementById('category_description').value = cat.description;
    document.getElementById('category_icon').value = cat.icon;
    document.getElementById('category_color').value = cat.color;
    document.getElementById('category_action').value = 'edit';
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    categoryModal.show();
}

function handleCategorySubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    SNESTX.ajax({
        action: 'SNESTX51_manage_category',
        data: Object.fromEntries(formData),
        loadingButton: jQuery(e.target).find('button[type="submit"]'),
        successMessage: 'Category saved successfully!',
        reload: true,
        onSuccess: function() {
            categoryModal.hide();
        }
    });
}

function deleteCategory(catId) {
    if(!confirm('Are you sure you want to delete this category? This cannot be undone.')) return;
    
    SNESTX.ajax({
        action: 'SNESTX51_manage_category',
        data: {
            category_action: 'delete',
            category_id: catId,
            _wpnonce: rulesNonce
        },
        successMessage: 'Category deleted successfully!',
        reload: true
    });
}

function viewViolation(violation) {
    const html = `
        <div class="row g-3">
            <div class="col-md-6">
                <strong>Flat Number:</strong><br>${violation.flat_no}
            </div>
            <div class="col-md-6">
                <strong>Violation Date:</strong><br>${new wp_date(violation.violation_date).toLocaleDateString()}
            </div>
            <div class="col-12">
                <strong>Description:</strong><br>${violation.description}
            </div>
            <div class="col-md-6">
                <strong>Fine Amount:</strong><br>₹${parseFloat(violation.fine_amount).toFixed(2)}
            </div>
            <div class="col-md-6">
                <strong>Payment Status:</strong><br>
                <span class="badge bg-${violation.payment_status === 'paid' ? 'success' : 'warning'}">${violation.payment_status}</span>
            </div>
            ${violation.appeal_reason ? `<div class="col-12"><strong>Appeal Reason:</strong><br>${violation.appeal_reason}</div>` : ''}
            ${violation.admin_notes ? `<div class="col-12"><strong>Admin Notes:</strong><br>${violation.admin_notes}</div>` : ''}
        </div>
    `;
    document.getElementById('violationDetails').innerHTML = html;
    violationModal.show();
}

function resolveViolation(violationId) {
    const notes = prompt('Enter resolution notes (optional):');
    if(notes === null) return;

    SNESTX.ajax({
        action: 'SNESTX51_resolve_violation',
        data: {
            violation_id: violationId,
            status: 'resolved',
            admin_notes: notes,
            _wpnonce: rulesNonce
        },
        successMessage: 'Violation resolved successfully!',
        reload: true
    });
}

function sendReminders() {
    if(!confirm('Send acknowledgment reminders to all residents with pending acknowledgments?')) return;

    SNESTX.ajax({
        action: 'SNESTX51_send_acknowledgment_reminders',
        data: {
            _wpnonce: rulesNonce
        },
        successMessage: 'Reminders sent successfully!',
        reload: true
    });
}
</script>
