<?php
/**
 * View: Expenses (Bootstrap Migration)
 * Integrates with SGVX51_DB_Router.
 */

$db = new SGVX51_DB_Router();
$current_year = date( 'Y' );
$selected_year = isset( $_GET['year'] ) ? sanitize_text_field( $_GET['year'] ) : $current_year;

$all_expenses = $db->get( 'expenses' );

// Debug: Log the raw expenses returned
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	error_log( 'SGVX51 Expenses Admin: Count=' . count( $all_expenses ) );
	if ( count( $all_expenses ) > 0 ) {
		error_log( 'SGVX51 First Expense Sample: ' . wp_json_encode( array_slice( $all_expenses[0], 0, 3 ) ) );
	} else {
		error_log( 'SGVX51 No expenses found. Check table existence or file permissions.' );
	}
}

$expenses = [];
foreach($all_expenses as $e) {
    if( date('Y', strtotime($e['date'] ?? '')) == $selected_year ) {
        $expenses[] = $e;
    }
}

$success_msg = '';
$error_msg = '';
if ( isset( $_GET['success'] ) ) $success_msg = 'Expense saved successfully.';
if ( isset( $_GET['approved'] ) ) $success_msg = 'Expense approved and verified.';
if ( isset( $_GET['error'] ) ) $error_msg = sanitize_text_field( urldecode( $_GET['error'] ) );

// Recalculate Totals based on Verified Only
$verified_total = 0;
$verified_expenses = [];
$pending_expenses = [];

if(!empty($expenses)) {
    foreach($expenses as $e) {
        if( isset($e['status']) && $e['status'] === 'pending' ) {
            $pending_expenses[] = $e;
        } else {
            $verified_expenses[] = $e;
            $verified_total += floatval($e['amount']);
        }
    }
}
?>

    <!-- Global Messages (Outside Cards) -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Financial Record Updated</div>
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
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Expenses & Accounts</h1>
                <p class="text-secondary m-0 mt-1">Track financial records and account balances for the society.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white px-3 py-2 rounded-3 d-flex align-items-center gap-3 border border-light shadow-sm">
                    <span class="small fw-bold text-secondary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Fiscal Period</span>
                    <form method="get" id="year-filter-form" class="m-0">
                        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                        <select name="year" class="form-select form-select-sm bg-light border-0 shadow-none fw-bold text-dark" style="min-width: 100px;" onchange="document.getElementById('year-filter-form').submit()">
                            <?php 
                            for( $y = date('Y'); $y >= date('Y')-2; $y--) {
                                $sel = ($y == $selected_year) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </form>
                </div>
                <button id="addExpense" onclick="openExpenseModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi-plus-circle-fill fs-5"></i>
                    <span>Add Expense</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4 mb-4">
        <!-- Sidebar: Stats -->
        <div class="col-lg-3">
            <div class="card border-0 bg-primary text-white rounded-3 shadow-sm p-4 mb-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="bg-white bg-opacity-20 text-primary p-2 rounded-3">
                        <i class="bi bi-wallet2 fs-4"></i>
                    </div>
                    <span class="badge bg-white bg-opacity-20 text-primary px-2 py-1 rounded-pill text-uppercase fw-bold" style="font-size: 8px; letter-spacing: 0.05em;">Audited</span>
                </div>
                <p class="small text-white opacity-75 fw-medium mb-1">Total Verified (<?php echo esc_html($selected_year); ?>)</p>
                <h2 class="h2 fw-bold m-0" style="letter-spacing: -0.03em;">₹<?php echo number_format( $verified_total, 2 ); ?></h2>
                <div class="mt-4 pt-4 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center small">
                    <span class="opacity-75"><?php echo count($verified_expenses); ?> Total entries</span>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
                <div class="p-4 border-bottom border-light bg-light">
                    <h5 class="h6 fw-bold text-dark m-0">Top Categories</h5>
                </div>
                <div class="p-4">
                     <?php
                        $categories = [];
                        foreach($verified_expenses as $e) {
                            $cat = $e['category'] ?? 'Other';
                            if(!isset($categories[$cat])) $categories[$cat] = 0;
                            $categories[$cat] += $e['amount'];
                        }
                        arsort($categories);
                        $top_cats = array_slice($categories, 0, 5);
                     ?>
                     <div class="d-flex flex-column gap-4">
                        <?php if(empty($top_cats)): ?>
                            <p class="small text-muted italic m-0">No data available for this fiscal year.</p>
                        <?php else: ?>
                            <?php foreach($top_cats as $cat => $amount): 
                                $pct = $verified_total > 0 ? ($amount / $verified_total) * 100 : 0;
                            ?>
                                <div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-secondary fw-medium"><?php echo esc_html($cat); ?></span>
                                        <span class="fw-bold text-dark">₹<?php echo number_format($amount); ?></span>
                                    </div>
                                    <div class="progress bg-light" style="height: 6px;">
                                        <div class="progress-bar bg-primary rounded-pill" role="progressbar" style="width: <?php echo $pct; ?>%" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                     </div>
                </div>
            </div>
        </div>


        <!-- Main Content Table Card -->
        <div class="col-lg-9">
            <div id="expenseContainer" class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden h-100 d-flex flex-column">
                
                <!-- Consolidated Toolbar -->
                <div class="p-4 px-md-5 border-bottom border-light bg-white">
                    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                        <!-- Smart Search -->
                        <div class="flex-grow-1 position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="expenseSearch" placeholder="Search by description, payee or category..." 
                                   class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                                   style="height: 48px; font-size: 0.95rem;">
                        </div>
                        
                        <!-- Action Group -->
                        <div class="d-flex gap-2">
                            <button class="js-toggle-filters btn btn-light px-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center gap-2 shadow-none" style="height: 48px;">
                                <i class="bi bi-funnel"></i>
                                <span>Filters</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs (Integrated) -->
                <div class="px-5 bg-white border-bottom border-light">
                    <ul class="nav nav-tabs border-0 gap-5" id="expenseTabs">
                        <li class="nav-item">
                            <button onclick="switchExpenseTab('verified')" id="tab-btn-verified" class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" style="background:none;">Verified Expenses</button>
                        </li>
                        <li class="nav-item">
                            <button onclick="switchExpenseTab('pending')" id="tab-btn-pending" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent hover-text-dark d-flex align-items-center gap-2" style="background:none;">
                                Pending Approval
                                <?php if(count($pending_expenses) > 0): ?>
                                    <span class="badge rounded-pill bg-warning bg-opacity-10 text-dark px-2" style="font-size: 10px;"><?php echo count($pending_expenses); ?></span>
                                <?php endif; ?>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="flex-grow-1">
                    <!-- Verified View -->
                    <div id="view-verified" class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light border-bottom border-light">
                                <tr>
                                    <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Date</th>
                                    <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Description</th>
                                    <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Category</th>
                                    <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Amount</th>
                                    <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                 <?php if ( empty( $verified_expenses ) ) : ?>
                                    <tr>
                                        <td colspan="5" class="px-5 py-5 text-center text-muted">
                                            <div class="py-5">
                                                <i class="bi bi-receipt fs-1 mb-3 d-block opacity-25"></i>
                                                <p class="m-0">No verified expenses recorded for this period.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ( array_reverse( $verified_expenses ) as $ex ) : ?>
                                        <tr class="expense-row border-bottom border-light" data-search="<?php echo esc_attr(strtolower(($ex['description']??'') . ' ' . ($ex['payee']??'') . ' ' . ($ex['category']??''))); ?>">
                                            <td class="ps-5 py-4 small text-secondary fw-medium"><?php echo esc_html( date( 'd M, Y', strtotime( $ex['date'] ) ) ); ?></td>
                                            <td class="px-4 py-4">
                                                <div class="fw-bold text-dark"><?php echo esc_html( $ex['description'] ); ?></div>
                                                <div class="small text-primary font-monospace" style="font-size: 11px;"><?php echo esc_html( $ex['payee'] ?? '-' ); ?></div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;"><?php echo esc_html( $ex['category'] ); ?></span>
                                            </td>
                                            <td class="px-4 py-4 fw-bold text-dark text-end">₹<?php echo number_format( floatval( $ex['amount'] ), 2 ); ?></td>
                                            <td class="pe-5 py-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <?php if ( ! empty( $ex['receipt_url'] ) ) : ?>
                                                        <a href="<?php echo esc_url( $ex['receipt_url'] ); ?>" target="_blank" class="btn btn-sm btn-light border border-light p-2 rounded-3 shadow-none" title="View Receipt">
                                                            <i class="bi bi-file-earmark-medical fs-6 text-muted"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button onclick="editExpense(this)" data-expense="<?php echo esc_attr(json_encode($ex)); ?>" class="btn btn-sm btn-light border border-light p-2 rounded-3 shadow-none">
                                                        <i class="bi bi-pencil-square fs-6 text-muted"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-light border border-light p-2 text-danger rounded-3 shadow-none js-delete-expense" data-id="<?php echo esc_attr($ex['id']); ?>" data-date="<?php echo esc_attr($ex['date']); ?>">
                                                        <i class="bi bi-trash fs-6"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pending View -->
                    <div id="view-pending" class="table-responsive d-none">
                        <div class="alert bg-warning bg-opacity-10 text-dark border-0 rounded-0 m-0 py-3 px-5 small d-flex align-items-center gap-3">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
                            <div class="fw-bold">Note: These expenses require verification from a committee member before appearing in reports.</div>
                        </div>
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light border-bottom border-light">
                                <tr>
                                    <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Date</th>
                                    <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Description</th>
                                    <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Amount</th>
                                    <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                 <?php if ( empty( $pending_expenses ) ) : ?>
                                    <tr><td colspan="6" class="px-5 py-5 text-center text-muted">No expense records found for this year.</td></tr>
                                <?php else : ?>
                                    <?php foreach ( array_reverse( $pending_expenses ) as $ex ) : ?>
                                        <tr class="expense-row border-bottom border-warning border-opacity-25" style="background-color: rgba(255, 193, 7, 0.05);" data-search="<?php echo esc_attr(strtolower(($ex['description']??'') . ' ' . ($ex['category']??''))); ?>">
                                            <td class="ps-5 py-4 small text-secondary fw-medium"><?php echo esc_html( date( 'd M, Y', strtotime( $ex['date'] ) ) ); ?></td>
                                            <td class="px-4 py-4">
                                                <div class="fw-bold text-dark"><?php echo esc_html( $ex['description'] ); ?></div>
                                                <div class="small fw-bold font-monospace" style="font-size: 11px; color: #856404;">
                                                    <?php echo esc_html( $ex['category'] ); ?>
                                                    <?php if($ex['receipt_url']): ?>
                                                        | <a href="<?php echo esc_url($ex['receipt_url']); ?>" target="_blank" class="text-primary text-decoration-none">View Receipt</a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 fw-bold text-dark text-end">₹<?php echo number_format( floatval( $ex['amount'] ), 2 ); ?></td>
                                            <td class="pe-5 py-4 text-end">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button onclick="editExpense(this)" data-expense="<?php echo esc_attr(json_encode($ex)); ?>" class="btn btn-sm btn-light border border-light px-3 py-2 fw-bold small rounded-3 shadow-none">Edit</button>
                                                    
                                                    <button type="button" class="btn btn-success px-4 py-2 fw-bold small rounded-pill shadow-none js-approve-expense" data-id="<?php echo esc_attr($ex['id']); ?>">Verify & Approve</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() {
    $add_nonce = wp_create_nonce( 'sgvx51_add_expense_nonce' );
?>
<!-- Add/Edit Expense Modal (Bootstrap) -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="expenseModalTitle">Record New Expense</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close" onclick="resetExpenseForm()"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="expense-form">
                <div class="modal-body p-4">
                    <?php 
                    $edit_nonce = wp_create_nonce( 'sgvx51_edit_expense_nonce' );
                    ?>
                    <input type="hidden" id="raw_add_nonce" value="<?php echo esc_attr($add_nonce); ?>">
                    <input type="hidden" id="raw_edit_nonce" value="<?php echo esc_attr($edit_nonce); ?>">
                    <input type="hidden" name="action" value="sgvx51_add_expense">
                    <input type="hidden" name="expense_id" value="">
                    <input type="hidden" name="existing_receipt_url" value="">
                    <input type="hidden" name="_wpnonce" id="active_nonce_field" value="<?php echo esc_attr($add_nonce); ?>">
                    <input type="hidden" name="_wp_http_referer" value="<?php echo admin_url( 'admin.php?page=sgvx51-settings&view=expenses' ); ?>">

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Date <span class="text-danger">*</span></label>
                             <input type="date" name="date" class="form-control shadow-none rounded-3 border-light" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                         <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Category <span class="text-danger">*</span></label>
                             <select name="category" class="form-select shadow-none rounded-3 border-light" required>
                                <option>Maintenance</option><option>Repairs</option><option>Electricity</option>
                                <option>Security</option><option>Water</option><option>Events</option><option>Others</option>
                             </select>
                        </div>
                    </div>

                    <div class="mb-3">
                         <label class="form-label small fw-bold text-secondary">Paid From (Account)</label>
                         <select name="account_type" class="form-select shadow-none rounded-3 border-light">
                            <option value="bank">Bank Account</option><option value="cash">Petty Cash</option>
                         </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control shadow-none rounded-3 border-light" min="0.01" required placeholder="Enter amount">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Payee / Vendor</label>
                            <input type="text" name="payee" class="form-control shadow-none rounded-3 border-light" placeholder="e.g. Services Inc.">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Description <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control shadow-none rounded-3 border-light" rows="2" required placeholder="Enter expense details..."></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Receipt (Optional)</label>
                        <input type="file" name="receipt_file" class="form-control form-control-sm shadow-none rounded-3 border-light">
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal" onclick="resetExpenseForm()">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
let expenseModal = null;

function openExpenseModal() {
    if(!expenseModal) expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
    resetExpenseForm();
    expenseModal.show();
}

let currentExpenseTab = 'verified';

function switchExpenseTab(tab) {
    currentExpenseTab = tab;
    const v = document.getElementById('view-verified');
    const p = document.getElementById('view-pending');
    const btV = document.getElementById('tab-btn-verified');
    const btP = document.getElementById('tab-btn-pending');

    if(tab === 'verified') {
        v.classList.remove('d-none');
        p.classList.add('d-none');
        btV.classList.add('active', 'border-primary', 'text-primary');
        btV.classList.remove('border-transparent', 'text-muted');
        btP.classList.remove('active', 'border-primary', 'text-primary');
        btP.classList.add('border-transparent', 'text-muted');
    } else {
        v.classList.add('d-none');
        p.classList.remove('d-none');
        btP.classList.add('active', 'border-primary', 'text-primary');
        btP.classList.remove('border-transparent', 'text-muted');
        btV.classList.remove('active', 'border-primary', 'text-primary');
        btV.classList.add('border-transparent', 'text-muted');
    }
    applyExpenseSearch();
}

function editExpense(btn) {
    if(!expenseModal) expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
    const data = JSON.parse(btn.dataset.expense);
    const form = document.getElementById('expense-form');
    
    form.querySelector('[name="date"]').value = data.date;
    form.querySelector('[name="category"]').value = data.category;
    form.querySelector('[name="amount"]').value = data.amount;
    form.querySelector('[name="payee"]').value = data.payee || '';
    form.querySelector('[name="description"]').value = data.description || '';
    form.querySelector('[name="existing_receipt_url"]').value = data.receipt_url || '';
    if(form.querySelector('[name="account_type"]')) form.querySelector('[name="account_type"]').value = data.account_type || 'bank';
    
    form.querySelector('[name="action"]').value = 'sgvx51_edit_expense';
    form.querySelector('[name="expense_id"]').value = data.id || ''; 
    document.getElementById('active_nonce_field').value = document.getElementById('raw_edit_nonce').value;
    
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    expenseModal.show();
}

function resetExpenseForm() {
    const form = document.getElementById('expense-form');
    form.reset();
    form.querySelector('[name="action"]').value = 'sgvx51_add_expense';
    form.querySelector('[name="expense_id"]').value = '';
    document.getElementById('active_nonce_field').value = document.getElementById('raw_add_nonce').value;
    document.getElementById('expenseModalTitle').textContent = 'Record New Expense';
}

let expenseFuse = null;
let currentTab = 'verified';

window.applyExpenseSearch = function() {
    const input = document.getElementById('expenseSearch');
    const query = input ? input.value.trim() : '';
    
    if (!expenseFuse && window.sgvxCreateFuse) {
        expenseFuse = window.sgvxCreateFuse('.expense-row');
    }

    const matches = query && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(expenseFuse, query) : null;
    
    document.querySelectorAll('.expense-row').forEach(row => {
        const isPending = row.closest('#view-pending') !== null;
        const belongsToActiveTab = (currentTab === 'pending' && isPending) || (currentTab === 'verified' && !isPending);
        
        const matchesSearch = !query || (matches && matches.has(row));
        row.style.display = (belongsToActiveTab && matchesSearch) ? '' : 'none';
    });
};

function switchExpenseTab(tab) {
    currentTab = tab;
    const v = document.getElementById('view-verified');
    const p = document.getElementById('view-pending');
    const btV = document.getElementById('tab-btn-verified');
    const btP = document.getElementById('tab-btn-pending');

    if(tab === 'verified') {
        v.classList.remove('d-none');
        p.classList.add('d-none');
        btV.classList.add('active', 'border-primary', 'text-primary');
        btV.classList.remove('border-transparent', 'text-muted');
        btP.classList.remove('active', 'border-primary', 'text-primary');
        btP.classList.add('border-transparent', 'text-muted');
    } else {
        v.classList.add('d-none');
        p.classList.remove('d-none');
        btP.classList.add('active', 'border-primary', 'text-primary');
        btP.classList.remove('border-transparent', 'text-muted');
        btV.classList.remove('active', 'border-primary', 'text-primary');
        btV.classList.add('border-transparent', 'text-muted');
    }
    applyExpenseSearch();
}

// Add listeners
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('expenseSearch');
    if (searchInput) {
        searchInput.addEventListener('input', applyExpenseSearch);
        searchInput.addEventListener('focus', function() {
            if (window.sgvxCreateFuse) expenseFuse = window.sgvxCreateFuse('.expense-row');
        });
    }
});
</script>

