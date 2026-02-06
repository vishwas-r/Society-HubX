<?php
/**
 * View: Audit Ledger (Bootstrap Migration)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$ledger_mgr = new SGVX51_Ledger_Manager();
$current_year = date( 'Y' );
$selected_year = isset( $_GET['year'] ) ? sanitize_text_field( $_GET['year'] ) : $current_year;

$entries = $ledger_mgr->get_ledger_entries( $selected_year );

// Calculate Net
$total_credit = 0;
$total_debit = 0;
foreach($entries as $e) {
    if($e['type'] === 'Credit') $total_credit += $e['amount'];
    elseif($e['type'] === 'Debit') $total_debit += $e['amount'];
}
$net_balance = $total_credit - $total_debit;
?>

    <!-- Header Section (Premium Card) -->
    <div class="card border-0 shadow-soft rounded-2xl mb-5 bg-white p-4 px-md-5 py-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-slate-900 m-0" style="letter-spacing: -0.02em;">Audit Ledger</h1>
                <p class="text-slate-500 m-0 mt-1">Chronological financial trail of all society income and expenses.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="bg-indigo-50 px-3 py-2 rounded-xl d-flex align-items-center gap-3 border border-indigo-100">
                    <span class="small fw-bold text-indigo-600 text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Fiscal Year</span>
                    <form method="get" id="ledger-year-form" class="m-0">
                        <input type="hidden" name="page" value="sgvx51-ledger">
                        <select name="year" class="form-select form-select-sm bg-white border-0 shadow-sm fw-bold text-indigo-700" style="min-width: 100px;" onchange="this.form.submit()">
                            <?php 
                            for( $y = date('Y'); $y >= date('Y')-2; $y--) {
                                $sel = ($y == $selected_year) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            }
                            ?>
                        </select>
                    </form>
                </div>
                <button onclick="openReconcileModal()" class="btn btn-custom-primary px-4 fw-bold shadow-custom-primary" style="height: 44px;">
                    Reconcile Balances
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-soft rounded-2xl p-4 h-100 bg-white border-start border-4 border-emerald-500">
                <div class="small fw-bold text-slate-400 text-uppercase tracking-wider mb-2">Total Income</div>
                <h3 class="h3 fw-bold text-slate-900 m-0">₹<?php echo number_format($total_credit, 1); ?></h3>
                <div class="small text-emerald-600 fw-bold mt-2" style="font-size: 10px;">CREDITED TO ACCOUNTS</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-soft rounded-2xl p-4 h-100 bg-white border-start border-4 border-rose-500">
                <div class="small fw-bold text-slate-400 text-uppercase tracking-wider mb-2">Total Expenses</div>
                <h3 class="h3 fw-bold text-slate-900 m-0">₹<?php echo number_format($total_debit, 1); ?></h3>
                <div class="small text-rose-600 fw-bold mt-2" style="font-size: 10px;">DEBITED FROM ACCOUNTS</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-soft rounded-2xl p-4 h-100 bg-slate-900 text-white border-start border-4 border-slate-700">
                <div class="small fw-bold text-white text-opacity-50 text-uppercase tracking-wider mb-2">Structural Net</div>
                <h3 class="h3 fw-bold text-white m-0">₹<?php echo number_format($net_balance, 1); ?></h3>
                <div class="small text-white text-opacity-40 fw-bold mt-2" style="font-size: 10px;">BOOK BANK BALANCE</div>
            </div>
        </div>
        <div class="col-md-3">
             <?php 
                $actual_bank = floatval(get_option('sgvx51_actual_bank_' . $selected_year, 0)); 
                $actual_cash = floatval(get_option('sgvx51_actual_cash_' . $selected_year, 0)); 
                $actual_total = $actual_bank + $actual_cash;
                $total_variance = $actual_total - $net_balance;
            ?>
            <div class="card border-0 shadow-custom-primary rounded-2xl p-4 h-100 bg-indigo-600 text-white shadow-custom-primary">
                <div class="small fw-bold text-white text-opacity-75 text-uppercase tracking-wider mb-2">Physical Funds</div>
                <h3 class="h3 fw-bold m-0">₹<?php echo number_format($actual_total, 1); ?></h3>
                <div class="mt-2 d-flex align-items-center gap-1 <?php echo $total_variance == 0 ? 'text-white text-opacity-50' : 'text-warning'; ?>" style="font-size: 10px;">
                    <span class="fw-bold">Variance: ₹<?php echo number_format($total_variance, 1); ?></span>
                    <?php if($total_variance != 0) echo '<i class="bi bi-exclamation-triangle" style="font-size: 12px; line-height: 12px;"></i>'; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Audit Card -->
    <div class="card border-0 shadow-soft rounded-2xl bg-white overflow-hidden mb-4">
        <div class="p-5 border-bottom border-slate-100 bg-slate-50 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
            <h5 class="fw-bold text-slate-900 m-0">Chronological Transaction Log</h5>
            <div class="d-flex align-items-center gap-3">
                <div class="position-relative" style="min-width: 250px;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-slate-400"></i>
                    <input type="text" id="ledgerSearch" placeholder="Search description, ref, entity..." 
                           class="form-control ps-5 border-slate-200 shadow-sm rounded-xl fw-medium text-sm" 
                           style="height: 40px;">
                </div>
                <div class="bg-white border rounded-xl px-3 py-2 d-flex gap-4 d-none d-md-flex">
                    <div class="small text-slate-500 font-monospace" style="font-size: 10px;">BANK: <span class="text-indigo-600 fw-bold">₹<?php echo number_format($actual_bank); ?></span></div>
                    <div class="small text-slate-500 font-monospace" style="font-size: 10px;">CASH: <span class="text-amber-600 fw-bold">₹<?php echo number_format($actual_cash); ?></span></div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-white border-bottom border-slate-100">
                    <tr>
                        <th class="ps-5 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider">Date / Ref</th>
                        <th class="px-4 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider">Transaction Details</th>
                        <th class="px-4 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider text-end">Credit (₹)</th>
                        <th class="px-4 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider text-end">Debit (₹)</th>
                        <th class="px-4 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider text-center">Account</th>
                        <th class="pe-5 py-4 text-uppercase small text-slate-500 fw-bold border-0 tracking-wider text-end">Cumulative System Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($entries)): ?>
                         <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">No ledger entries recorded for this financial year.</td></tr>
                    <?php else: ?>
                        <?php foreach(array_reverse($entries) as $ln): ?>
                            <tr class="ledger-row border-bottom border-slate-50">
                                <td class="ps-5 py-4">
                                    <div class="text-slate-900 fw-bold small"><?php echo date('d M, Y', strtotime($ln['date'])); ?></div>
                                    <div class="text-slate-400 font-monospace" style="font-size: 8px;">REF#<?php echo esc_html($ln['ref_id']); ?></div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="fw-bold text-slate-900 small"><?php echo esc_html($ln['description']); ?></div>
                                    <div class="text-slate-500 small text-uppercase fw-bold" style="font-size: 9px; letter-spacing: 0.02em;"><?php echo esc_html($ln['entity']); ?></div>
                                </td>
                                <td class="px-4 py-4 text-end">
                                    <span class="fw-bold text-emerald-600 <?php echo $ln['type'] === 'Credit' ? '' : 'opacity-10'; ?>">
                                        ₹<?php echo $ln['type'] === 'Credit' ? number_format($ln['amount'], 2) : '0.00'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-end">
                                    <span class="fw-bold text-rose-600 <?php echo $ln['type'] === 'Debit' ? '' : 'opacity-10'; ?>">
                                        ₹<?php echo $ln['type'] === 'Debit' ? number_format($ln['amount'], 2) : '0.00'; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="badge badge-indigo-subtle px-3 py-1.5 rounded text-uppercase fw-bold" style="font-size: 8px;">
                                        <?php echo esc_html($ln['account_type'] ?? 'BANK'); ?>
                                    </span>
                                </td>
                                <td class="pe-5 py-4 text-end">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fw-bold text-indigo-700 font-monospace" style="font-size: 11px;">₹<?php echo number_format($ln['bank_balance'], 2); ?></div>
                                        <div class="fw-bold text-amber-600 font-monospace" style="font-size: 9px;">₹<?php echo number_format($ln['cash_balance'], 2); ?></div>
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


<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() use ($selected_year, $actual_bank, $actual_cash) {
?>
<!-- Reconcile Modal -->
<div class="modal fade" id="reconcileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-xl">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-slate-900">Physical Reconciliation</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="sgvx51_reconcile_balance">
                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                    <?php wp_nonce_field('sgvx51_reconcile_nonce'); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-500">Actual Bank Total (₹)</label>
                        <input type="number" step="0.01" name="actual_bank" value="<?php echo $actual_bank; ?>" class="form-control shadow-none rounded-lg font-monospace fw-bold" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-slate-500">Physical Petty Cash (₹)</label>
                        <input type="number" step="0.01" name="actual_cash" value="<?php echo $actual_cash; ?>" class="form-control shadow-none rounded-lg font-monospace fw-bold" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4 fw-medium text-slate-500" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo fw-bold px-4 shadow-sm">Update Audit Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
let reconModal = null;
let ledgerFuse = null;

function openReconcileModal() {
    if(!reconModal) reconModal = new bootstrap.Modal(document.getElementById('reconcileModal'));
    reconModal.show();
}

function applyLedgerSearch() {
    const input = document.getElementById('ledgerSearch');
    const query = input ? input.value.trim() : '';
    
    if (!ledgerFuse && window.sgvxCreateFuse) {
        ledgerFuse = window.sgvxCreateFuse('.ledger-row');
    }

    const matches = query && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(ledgerFuse, query) : null;
    
    document.querySelectorAll('.ledger-row').forEach(row => {
        const matchesSearch = !query || (matches && matches.has(row));
        row.style.display = matchesSearch ? '' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('ledgerSearch');
    if (searchInput) {
        searchInput.addEventListener('input', applyLedgerSearch);
        searchInput.addEventListener('focus', function() {
            if (window.sgvxCreateFuse) ledgerFuse = window.sgvxCreateFuse('.ledger-row');
        });
    }
});
</script>

