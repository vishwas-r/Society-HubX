<?php
/**
 * View: Accounts (Invoices & Income) - Bootstrap Migration
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = new SHUBX51_DB_Router();
$invoices = $db->get( 'invoices', array( 'load_relations' => true ) );
$residents = $db->get( 'residents', array( 'load_relations' => true ) );

$ledger_mgr = new SHUBX51_Ledger_Manager();
$selected_year = isset( $_GET['year'] ) ? sanitize_text_field( wp_unslash( $_GET['year'] ) ) : wp_date('Y');
$ledger_entries = $ledger_mgr->get_ledger_entries( $selected_year );

// Fetch Relevant Payment Requests (Any Stage)
$relevant_reqs = array_filter($db->get('requests'), function($r) use ($selected_year) {
    if ( ( ($r['module'] ?? '') !== 'accounts' && ($r['entity_type'] ?? '') !== 'accounts' ) ) return false;
    $status = $r['status'] ?? '';
    if ( in_array($status, ['pending', 'pending_secretary', 'pending_treasurer']) ) return true;
    if ( $status === 'approved' && wp_date('Y', strtotime($r['processed_at'] ?? $r['created_at'])) == $selected_year ) return true;
    return false;
});

// Inject "Total Outstanding" requests as dummy invoices so they appear in the table UI
foreach($relevant_reqs as $pr) {
    if ( is_string($pr['payload']) ) {
        $p_payload = json_decode($pr['payload'], true) ?: [];
    } else {
        $p_payload = $pr['payload'] ?: [];
    }

    if( ($p_payload['invoice_id'] ?? '') === 'Total Outstanding' ) {
        $resident_name = 'Resident';
        $resident_block = $p_payload['block'] ?? '';
        $resident_flat = $p_payload['flat_no'] ?? '';

        foreach($residents as $res) {
            if((string)($res['flat_no'] ?? '') === (string)$resident_flat && (string)($res['block'] ?? '') === (string)$resident_block) {
                $resident_name = $res['name'] ?? 'Resident'; break;
            }
        }
        $invoices[] = [
            'id'            => 'req_' . $pr['id'],
            'block'         => $resident_block,
            'flat_no'       => $resident_flat,
            'resident_name' => $resident_name,
            'month'         => wp_date('Y-m-d', strtotime($pr['created_at'])),
            'description'   => 'Payment towards Total Outstanding',
            'amount'        => $p_payload['amount'] ?? 0,
            'status'        => 'pending_total',
            'created_at'    => $pr['created_at'],
            'payments'      => []
        ];
    }
}

// Helper for Indian Numbering Format
function SHUBX_in_fmt($num, $decimals = 2) {
    $num = (float)$num;
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        $res = $fmt->format($num);
        if ($res !== false) return $res;
    }

    // Manual Fallback for Indian Numbering System (Lakhs/Crores)
    $negative = $num < 0;
    $num = abs($num);
    $explated = explode('.', (string)number_format($num, $decimals, '.', ''));
    $int = $explated[0];
    $dec = isset($explated[1]) ? '.' . $explated[1] : '';

    $last_three = substr($int, -3);
    $rest = substr($int, 0, -3);
    if ($rest != '') {
        $rest = preg_replace("/\B(?=(\d{2})+(?!\d))/", ",", $rest) . ",";
    }
    $formatted = $rest . $last_three . $dec;
    return ($negative ? '-' : '') . $formatted;
}

// New Consolidated Stats
$total_credit = 0; $total_debit = 0;
$opening_bank = floatval(get_option('shubx51_opening_bank_' . $selected_year, 0));
$opening_cash = floatval(get_option('shubx51_opening_cash_' . $selected_year, 0));

foreach($ledger_entries as $e) {
    if(($e['type'] ?? '') === 'Credit') $total_credit += $e['amount'];
    elseif(($e['type'] ?? '') === 'Debit') $total_debit += $e['amount'];
}

$last_entry = end($ledger_entries);
$net_balance = ($last_entry['bank_balance'] ?? 0) + ($last_entry['cash_balance'] ?? 0);

$actual_bank = floatval(get_option('shubx51_actual_bank_' . $selected_year, 0));
$actual_cash = floatval(get_option('shubx51_actual_cash_' . $selected_year, 0));
$actual_total = $actual_bank + $actual_cash;
$variance = $actual_total - $net_balance;

// Collection Stats
$total_demand = 0;
$total_collected = 0;
foreach($invoices as $inv) {
    // Skip dummy invoices injected for pending UI representations
    if ( ($inv['status'] ?? '') === 'pending_total' ) continue;

    if (wp_date('Y', strtotime($inv['month'])) == $selected_year) {
        $total_demand += $inv['amount'];
        
        $collected_this_inv = 0;
        if(!empty($inv['payments'])) {
            $payments = $inv['payments'];
            if(is_array($payments) && !empty($payments)) {
                foreach($payments as $p) {
                   if(wp_date('Y', strtotime($p['date'])) == $selected_year) $collected_this_inv += $p['amount'];
                }
            }
        }
        
        // Fallback: If status is PAID but no payments found in JSON (Imported Data)
        if($collected_this_inv == 0 && (strtolower($inv['status'] ?? '') === 'paid')) {
            $collected_this_inv = $inv['amount'];
        }
        
        $total_collected += $collected_this_inv;
    }
}
$collection_pct = ($total_demand > 0) ? round(($total_collected / $total_demand) * 100) : 0;

// ==== CHART DATA PREPARATION ====
// 1. Monthly Cash Flow Data
$monthly_data = [];
foreach($ledger_entries as $entry) {
    $month = wp_date('M Y', strtotime($entry['date']));
    if(!isset($monthly_data[$month])) {
        $monthly_data[$month] = ['income' => 0, 'expense' => 0, 'net' => 0];
    }
    if($entry['type'] === 'Credit') {
        $monthly_data[$month]['income'] += $entry['amount'];
    } else if($entry['type'] === 'Debit') {
        $monthly_data[$month]['expense'] += $entry['amount'];
    }
}
// Calculate net and limit to last 12 months
$monthly_data = array_slice($monthly_data, -12, null, true);
foreach($monthly_data as $month => &$data) {
    $data['net'] = $data['income'] - $data['expense'];
}
unset($data);

// 2. Collection Rate Breakdown (for doughnut)
$paid_count = 0;
$unpaid_count = 0;
$partial_count = 0;
foreach($invoices as $inv) {
    // Skip dummy invoices injected for pending UI representations
    if ( ($inv['status'] ?? '') === 'pending_total' ) continue;

    if (wp_date('Y', strtotime($inv['month'])) == $selected_year) {
        $inv_status = $inv['status'] ?? 'unpaid';
        if($inv_status === 'paid') $paid_count++;
        else if($inv_status === 'partial') $partial_count++;
        else $unpaid_count++;
    }
}

// 3. Expense Category Data (for pie)
$category_data = [];
foreach($ledger_entries as $e) {
    if($e['type'] === 'Debit') {
        $cat = $e['category'] ?? 'Others';
        if(!isset($category_data[$cat])) $category_data[$cat] = 0;
        $category_data[$cat] += $e['amount'];
    }
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'invoices';
$success_msg = '';
if ( isset( $_GET['success'] ) ) {
    if ( $_GET['success'] === 'generated' ) $success_msg = 'Invoices generated successfully.';
    if ( $_GET['success'] === 'payment_recorded' ) $success_msg = 'Payment recorded successfully.';
    if ( $_GET['success'] === 'reconciled' ) $success_msg = 'Balances reconciled successfully.';
}
?>

<!-- Nonce for AJAX Requests -->
<script>
    var SHUBX51AdminNonce = '<?php echo wp_create_nonce( 'shubx51_nonce' ); ?>';
    var shubx51RequestNonce = '<?php echo wp_create_nonce( 'shubx51_request_action' ); ?>';
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
    
    // Chart Data
    var SHUBXAccountsChartData = {
        monthlyData: <?php echo json_encode($monthly_data); ?>,
        collectionData: {
            paid: <?php echo $paid_count; ?>,
            unpaid: <?php echo $unpaid_count; ?>,
            partial: <?php echo $partial_count; ?>
        },
        categoryData: <?php echo json_encode($category_data); ?>
    };
</script>

    <!-- Global Messages (Outside Cards) -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Accounting Update</div>
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
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Accounts & Financials</h1>
                <p class="text-secondary m-0 mt-1">Manage society maintenance collections, invoicing, and ledger audits.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white px-3 py-2 rounded-3 d-flex align-items-center gap-3 border border-light shadow-sm">
                    <span class="small fw-bold text-secondary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Fiscal Period</span>
                    <form method="get" class="m-0">
                        <input type="hidden" name="page" value="shubx51-accounts">
                        <select name="year" onchange="this.form.submit()" class="form-select form-select-sm bg-light border-0 shadow-none fw-bold text-dark" style="min-width: 100px;">
                             <?php for($y = (int)wp_date('Y'); $y >= (int)wp_date('Y')-2; $y--) {
                                $sel = ($y == (int)sanitize_text_field($selected_year)) ? 'selected' : '';
                                echo "<option value='$y' $sel>$y</option>";
                            } ?>
                        </select>
                    </form>
                </div>
                <button onclick="openGenerateModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi-file-earmark-plus fs-5"></i>
                    <span>Generate Maintenance</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Background Processing Status -->
    <?php
    $all_options = wp_load_alloptions();
    $running_jobs = array();
    foreach ( $all_options as $key => $val ) {
        if ( strpos( $key, 'shubx51_job_bulk_invoice_' ) === 0 ) {
            $job = maybe_unserialize( $val );
            if ( $job && $job['status'] === 'running' ) {
                $running_jobs[] = $job;
            }
        }
    }
    ?>
    <?php if ( ! empty( $running_jobs ) ) : ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white p-4 mb-5 border-start border-5 border-info">
            <h6 class="fw-bold text-dark mb-3">Background Tasks in Progress</h6>
            <?php foreach ( $running_jobs as $job ) : 
                $pct = ( $job['total'] > 0 ) ? round( ( $job['processed'] / $job['total'] ) * 100 ) : 0;
            ?>
                <div class="mb-3 last-child-mb-0">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary">Generating <?php echo esc_html( ucfirst( $job['type'] ) ); ?> Invoices for <?php echo esc_html( wp_date( 'F Y', strtotime( $job['month'] ) ) ); ?></span>
                        <span class="badge bg-info text-white fw-bold" style="font-size: 10px;"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: <?php echo $pct; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Global Stats Grid -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white border-start border-5 border-success">
                <div class="d-flex justify-content-between mb-2">
                    <p class="small fw-bold text-secondary text-uppercase tracking-wider m-0">Revenue (Inflow)</p>
                    <i class="bi bi-graph-up-arrow text-success fs-5"></i>
                </div>
                <h3 class="h2 fw-bold text-dark m-0">₹<?php echo SHUBX_in_fmt($total_credit, 0); ?></h3>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $collection_pct; ?>%"></div>
                </div>
                <div class="small text-muted mt-2" style="font-size: 10px;">
                    COLLECTED: ₹<?php echo SHUBX_in_fmt($total_collected); ?> / DEMAND: ₹<?php echo SHUBX_in_fmt($total_demand); ?> (<?php echo $collection_pct; ?>%)
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white border-start border-5 border-danger">
                <div class="d-flex justify-content-between mb-2">
                    <p class="small fw-bold text-secondary text-uppercase tracking-wider m-0">Expenses (Outflow)</p>
                    <i class="bi bi-graph-down-arrow text-danger fs-5"></i>
                </div>
                <h3 class="h2 fw-bold text-dark m-0">₹<?php echo SHUBX_in_fmt($total_debit, 0); ?></h3>
                <div class="small text-danger fw-bold mt-2" style="font-size: 10px;">TOTAL APPROVED COSTS</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-dark text-white border-start border-5 border-secondary">
                <div class="d-flex justify-content-between mb-2 text-white-50">
                    <p class="small fw-bold text-uppercase tracking-wider m-0">System Balance</p>
                    <i class="bi bi-calculator fs-5"></i>
                </div>
                <!-- Yearly Balance -->
                <h3 class="h2 fw-bold m-0">₹<?php echo SHUBX_in_fmt($net_balance, 0); ?></h3>
                <div class="small text-white-50 fw-bold mt-2" style="font-size: 10px;">YEAR END POSITION</div>
                
                <!-- Overall Live Balance (Added) -->
                <?php 
                    $live_bal = $ledger_mgr->get_current_balance(); 
                    $is_current_year = ($selected_year == wp_date('Y'));
                ?>
                <?php if(!$is_current_year): ?>
                    <div class="mt-3 pt-3 border-top border-white-50">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small fw-bold text-white-50" style="font-size: 10px;">LIVE BALANCE</span>
                            <span class="fw-bold text-white">₹<?php echo SHUBX_in_fmt($live_bal['total'] ?? 0, 0); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 <?php echo abs($variance) < 10 ? 'bg-primary' : 'bg-warning'; ?> text-white border-start border-5 border-white-50 shadow-sm">
                <div class="d-flex justify-content-between mb-2 text-white-50">
                    <p class="small fw-bold text-uppercase tracking-wider m-0">Physical Funds</p>
                    <i class="bi bi-safe2 fs-5"></i>
                </div>
                <h3 class="h2 fw-bold m-0">₹<?php echo SHUBX_in_fmt($actual_total, 0); ?></h3>
                <div class="d-flex gap-2 mt-2">
                    <span class="badge bg-white text-dark fw-bold" style="font-size: 9px; opacity: 0.9;">BANK: ₹<?php echo SHUBX_in_fmt($actual_bank); ?></span>
                    <span class="badge bg-white text-dark fw-bold" style="font-size: 9px; opacity: 0.9;">CASH: ₹<?php echo SHUBX_in_fmt($actual_cash); ?></span>
                </div>
                <?php if(abs($variance) > 1): ?>
                    <div class="mt-2 small fw-bold text-white" style="font-size: 10px;">
                        <i class="bi bi-exclamation-triangle-fill"></i> VARIANCE: ₹<?php echo SHUBX_in_fmt($variance); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- Financial Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Cash Flow Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-dark m-0">Monthly Cash Flow</h5>
                    <!-- <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-outline-primary active" id="btn-chart-view-monthly" onclick="switchChartView('monthly')">Monthly</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-chart-view-yearly" onclick="switchChartView('yearly')">Yearly</button>
                    </div> -->
                </div>
                <div id="cashFlowChart" style="height: 350px; width: 100%;"></div>
            </div>
        </div>
        
        <!-- Collection Efficiency Doughnut -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
                <h5 class="fw-bold text-dark mb-3">Collection Efficiency</h5>
                <div id="collectionChart" style="height: 350px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="account-filter-search" placeholder="Search by name, reference, details..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <?php if ($active_tab === 'invoices'): ?>
                        <button onclick="openAdhocModal()" class="btn btn-light px-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center gap-2 hover-bg-light shadow-none" style="height: 48px;">
                            <i class="bi bi-plus-square"></i>
                            <span>Ad-hoc Invoice</span>
                        </button>
                    <?php else: ?>
                        <button onclick="openReconcileModal()" class="btn btn-light px-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center gap-2 hover-bg-light shadow-none" style="height: 48px;">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Reconcile Funds</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs (Integrated) -->
        <div class="px-5 bg-white border-bottom border-light">
            <ul class="nav nav-tabs border-0 gap-5" id="accountTabs">
                <li class="nav-item">
                    <a href="?page=shubx51-accounts&tab=invoices" class="nav-link py-3 px-0 border-0 border-bottom border-2 <?php echo $active_tab === 'invoices' ? 'active fw-bold text-primary border-primary' : 'text-muted fw-semibold border-transparent hover-text-dark'; ?>" style="background:none;">Invoices & Maintenance</a>
                </li>
                <li class="nav-item">
                    <a href="?page=shubx51-accounts&tab=ledger" class="nav-link py-3 px-0 border-0 border-bottom border-2 <?php echo $active_tab === 'ledger' ? 'active fw-bold text-primary border-primary' : 'text-muted fw-semibold border-transparent hover-text-dark'; ?>" style="background:none;">Money Flow Ledger</a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <?php if ($active_tab === 'invoices'): ?>
                <!-- INVOICES TAB CONTENT -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light border-bottom border-light">
                            <tr>
                                <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Invoice / Date</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Unit & Resident</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Description</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Amount Due</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Status</th>
                                <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $invoices ) ) : ?>
                                <tr><td colspan="6" class="px-5 py-5 text-center text-slate-400">No invoices recorded yet.</td></tr>
                            <?php else : ?>
                                    <?php foreach ( array_reverse( $invoices ) as $inv ) : 
                                        $paid = 0;
                                        $payments = $inv['payments'] ?? [];
                                        if(is_array($payments)) {
                                            foreach($payments as $p) $paid += floatval($p['amount']);
                                        }

                                        // Fallback for Imported Data
                                        if($paid == 0 && (strtolower($inv['status'] ?? '') === 'paid')) {
                                            $paid = floatval($inv['amount']);
                                        }

                                        // Check for pending request
                                        $pending_request = null;
                                        foreach($pending_reqs as $pr) {
                                            $p_payload = is_array($pr['payload'] ?? null) ? $pr['payload'] : json_decode($pr['payload'], true);
                                            // Match exact invoice ID, OR match the injected "Total Outstanding" dummy invoice
                                            if(($p_payload['invoice_id'] ?? '') === $inv['id'] || $inv['id'] === 'req_' . $pr['id']) {
                                                $pending_request = $pr;
                                                break;
                                            }
                                        }
                                    ?>
                                    <?php 
                                        $search_text = strtolower(implode(' ', [
                                            '#INV-' . substr($inv['id'], -6),
                                            $inv['flat_no'],
                                            $inv['resident_name'] ?? '',
                                            wp_date('M Y', strtotime($inv['month'])),
                                            $inv['description'] ?? ''
                                        ]));
                                    ?>
                                    <tr class="border-bottom border-light invoice-row" data-search="<?php echo esc_attr($search_text); ?>">
                                        <td class="ps-5 py-4">
                                            <div class="font-monospace fw-bold text-primary" style="font-size: 11px;">#INV-<?php echo substr($inv['id'], -6); ?></div>
                                            <div class="small text-muted font-monospace" style="font-size: 10px;"><?php echo esc_html($inv['date'] ?? $inv['created_at']); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="fw-bold text-dark"><?php echo esc_html($inv['flat_no']); ?></div>
                                            <div class="small text-secondary fw-bold" style="font-size: 11px;"><?php echo esc_html($inv['resident_name'] ?? 'Unknown'); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-dark fw-medium"><?php echo esc_html(wp_date('M Y', strtotime($inv['month']))); ?> Maintenance</div>
                                            <div class="small text-muted text-truncate" style="max-width: 150px; font-size: 10px;"><?php echo esc_html($inv['description']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 text-end">
                                            <div class="fw-bold text-dark">₹<?php echo SHUBX_in_fmt($inv['amount']); ?></div>
                                            <?php if($paid > 0 && $paid < $inv['amount']): ?>
                                                <div class="text-success fw-bold" style="font-size: 9px;">Paid: ₹<?php echo SHUBX_in_fmt($paid); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <?php if ( ($inv['status'] ?? '') === 'paid' ) : ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold" style="font-size: 9px;">FULL PAID</span>
                                            <?php elseif ( $pending_request ) : 
                                                $p_payload = is_array($pending_request['payload'] ?? null) ? $pending_request['payload'] : json_decode($pending_request['payload'], true);
                                            ?>
                                                <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold mb-1" style="font-size: 9px;">VERIFICATION PENDING</span>
                                                <div class="text-info fw-bold" style="font-size: 9px;">₹<?php echo SHUBX_in_fmt($p_payload['amount'] ?? 0); ?> (<?php echo $p_payload['method'] ?? 'UPI'; ?>)</div>
                                            <?php elseif ( ($inv['status'] ?? '') === 'partial' ) : ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold" style="font-size: 9px;">PARTIAL</span>
                                            <?php else : ?>
                                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold" style="font-size: 9px;">UNPAID</span>
                                            <?php endif; ?>
                                        </td>
                                         <td class="pe-5 py-4 text-end">
                                             <div class="d-flex justify-content-end gap-2 align-items-center">
                                                 <?php if ( $pending_request ) : ?>
                                                     <div class="d-flex gap-1 me-2">
                                                         <button type="button" class="btn btn-sm btn-success p-1 js-approve-payment rounded-circle shadow-sm" data-id="<?php echo esc_attr($pending_request['id']); ?>" title="Approve Payment Notification">
                                                             <i class="bi bi-check-lg" style="font-size: 14px;"></i>
                                                         </button>
                                                         <button type="button" class="btn btn-sm btn-danger p-1 js-reject-payment rounded-circle shadow-sm" data-id="<?php echo esc_attr($pending_request['id']); ?>" title="Reject Notification">
                                                             <i class="bi bi-x-lg" style="font-size: 14px;"></i>
                                                         </button>
                                                     </div>
                                                 <?php elseif ( ($inv['status'] ?? '') !== 'paid' ) : ?>
                                                     <button type="button" class="btn btn-sm btn-primary px-3 fw-bold js-record-payment rounded-pill shadow-sm" data-invoice="<?php echo esc_attr(json_encode($inv)); ?>" style="font-size: 10px;">RECORD PAYMENT</button>
                                                 <?php endif; ?>
                                                 
                                                 <div class="d-flex gap-1">
                                                     <?php if ( $paid == 0 && ($inv['status'] ?? '') !== 'pending_total' ) : ?>
                                                         <button type="button" class="btn btn-sm btn-light border border-light p-2 js-edit-invoice rounded-3 shadow-none" data-invoice="<?php echo esc_attr(json_encode($inv)); ?>" title="Edit">
                                                             <i class="bi bi-pencil-square fs-6 text-muted"></i>
                                                         </button>
                                                     <?php endif; ?>
                                                      <?php if( $paid > 0 && !$pending_request && ($inv['status'] ?? '') !== 'pending_total' ): ?>
                                                          <button type="button" class="btn btn-sm btn-light border border-light p-2 js-open-receipt rounded-3 shadow-none" data-invoice="<?php echo esc_attr(json_encode($inv)); ?>" title="View Receipt">
                                                              <i class="bi bi-file-earmark-medical fs-6 text-muted"></i>
                                                          </button>
                                                      <?php endif; ?>
                                                      <?php if( ($inv['status'] ?? '') !== 'pending_total' ): ?>
                                                         <button type="button" class="btn btn-sm btn-light border border-light p-2 text-danger js-delete-invoice rounded-3 shadow-none" data-id="<?php echo esc_attr($inv['id']); ?>" title="Delete">
                                                             <i class="bi bi-trash fs-6"></i>
                                                         </button>
                                                      <?php endif; ?>
                                                 </div>
                                             </div>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($active_tab === 'ledger'): ?>
                <!-- LEDGER TAB CONTENT -->
                <div class="p-4 border-bottom border-light bg-light bg-opacity-10">
                    <div class="row align-items-center">
                        <div class="col-md-5 border-end border-light">
                            <h6 class="fw-bold text-dark mb-3 text-uppercase small tracking-wider">Expense Breakdown by Category</h6>
                            <div id="expenseCategoryChart" style="height: 250px; width: 100%;"></div>
                        </div>
                        <div class="col-md-7 ps-md-5">
                             <div class="alert bg-white border-light shadow-sm mb-0">
                                 <div class="d-flex align-items-center gap-3">
                                     <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                         <i class="bi bi-info-circle fs-4"></i>
                                     </div>
                                     <div>
                                         <div class="fw-bold text-dark">Monthly Analysis</div>
                                         <p class="small text-muted m-0">The chart on the left illustrates the distribution of society expenses across various categories for the selected period.</p>
                                     </div>
                                 </div>
                             </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light border-bottom border-light">
                            <tr>
                                <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Entry Date / Ref</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Entity & Details</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Inflow (₹)</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Outflow (₹)</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Account</th>
                                <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Cumulative Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php if(empty($ledger_entries)): ?>
                                 <tr><td colspan="6" class="px-5 py-5 text-center text-muted">No ledger transactions found for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach(array_reverse($ledger_entries) as $ln): ?>
                                    <?php 
                                        $search_text = strtolower(implode(' ', [
                                            wp_date('d M Y', strtotime($ln['date'])),
                                            $ln['ref_id'] ?? '',
                                            $ln['description'] ?? '',
                                            $ln['entity'] ?? '',
                                            $ln['type'] ?? ''
                                        ]));
                                    ?>
                                    <tr class="border-bottom border-light ledger-row" data-search="<?php echo esc_attr($search_text); ?>">
                                        <td class="ps-5 py-4">
                                            <div class="text-dark fw-bold small"><?php echo esc_html(wp_date('d M, Y', strtotime($ln['date']))); ?></div>
                                            <div class="text-muted font-monospace" style="font-size: 8px;"><?php echo esc_html($ln['ref_id']); ?></div>
                                        </td>
                                         <td class="px-4 py-4">
                                            <div class="fw-bold text-dark small <?php echo !empty($ln['is_pending']) ? 'text-opacity-50 fst-italic' : ''; ?>">
                                                <?php echo esc_html($ln['description']); ?>
                                                <?php if(!empty($ln['is_pending'])): ?> <span class="badge bg-info bg-opacity-10 text-info ms-1" style="font-size: 8px;">PENDING</span> <?php endif; ?>
                                            </div>
                                            <div class="small font-monospace text-secondary fw-bold text-uppercase" style="font-size: 9px; letter-spacing: 0.02em;"><?php echo esc_html($ln['entity']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 text-end">
                                            <span class="fw-bold text-success <?php echo ($ln['type'] ?? '') === 'Credit' ? '' : 'opacity-10'; ?>">
                                                ₹<?php echo ($ln['type'] ?? '') === 'Credit' ? SHUBX_in_fmt($ln['amount']) : '0.00'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-end">
                                            <span class="fw-bold text-danger <?php echo ($ln['type'] ?? '') === 'Debit' ? '' : 'opacity-10'; ?>">
                                                ₹<?php echo ($ln['type'] ?? '') === 'Debit' ? SHUBX_in_fmt($ln['amount']) : '0.00'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold" style="font-size: 8px;">
                                                <?php echo esc_html($ln['account_type'] ?? 'BANK'); ?>
                                            </span>
                                        </td>
                                        <td class="pe-5 py-4 text-end">
                                            <div class="d-flex flex-column align-items-end">
                                                <span class="small text-muted fw-bold" style="font-size: 10px;">BANK: <span class="text-primary font-monospace">₹<?php echo SHUBX_in_fmt($ln['bank_balance']); ?></span></span>
                                                <span class="small text-muted fw-bold" style="font-size: 10px;">CASH: <span class="text-warning font-monospace">₹<?php echo SHUBX_in_fmt($ln['cash_balance']); ?></span></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('shubx51_admin_modals', function() use ($selected_year, $actual_bank, $actual_cash, $opening_bank, $opening_cash) {
?>
<!-- Reconcile Modal -->
<div class="modal fade" id="reconcileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Reconcile Funds</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_reconcile_balance">
                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                    <?php wp_nonce_field('shubx51_reconcile_nonce'); ?>
                    
                    <h6 class="fw-bold text-primary small text-uppercase mb-3">Actual Physical Funds (Now)</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Bank Balance (₹)</label>
                        <input type="number" step="0.01" name="actual_bank" value="<?php echo $actual_bank; ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Cash Balance (₹)</label>
                        <input type="number" step="0.01" name="actual_cash" value="<?php echo $actual_cash; ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>

                    <h6 class="fw-bold text-primary small text-uppercase mb-3">Opening Balances (Jan 1st)</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Opening Bank (₹)</label>
                        <input type="number" step="0.01" name="opening_bank" value="<?php echo $opening_bank; ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Opening Cash (₹)</label>
                        <input type="number" step="0.01" name="opening_cash" value="<?php echo $opening_cash; ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Generate Monthly Maintenance Modal -->
<div class="modal fade" id="generateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Generate Maintenance</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_generate_invoices">
                    <input type="hidden" name="type" value="maintenance">
                    <?php wp_nonce_field( 'shubx51_account_action' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Billing Month</label>
                        <input type="month" name="month" value="<?php echo wp_date('Y-m'); ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Description</label>
                        <input type="text" name="description" value="Monthly Maintenance - <?php echo wp_date('F Y'); ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Amount (₹)</label>
                            <input type="number" name="amount" value="<?php echo esc_attr(get_option('shubx51_maintenance_amount', '5000')); ?>" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Due Date</label>
                            <input type="date" name="due_date" value="<?php echo wp_date('Y-m-d', strtotime('+10 days')); ?>" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                    </div>
                    
                    <div class="alert alert-info border-0 rounded-3 small mb-0 m-0 py-2">
                        Generates invoices for <strong>All Active Residents</strong>.
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ad-hoc Modal -->
<div class="modal fade" id="adhocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Create Ad-hoc Collection</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_generate_invoices">
                    <input type="hidden" name="type" value="adhoc">
                    <?php wp_nonce_field( 'shubx51_account_action' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Title / Reason</label>
                        <input type="text" name="description" placeholder="e.g. Festival Fund, Emergency Repairs" class="form-control shadow-none rounded-3 border-light" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Billing Month</label>
                        <input type="month" name="month" value="<?php echo wp_date('Y-m'); ?>" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Amount Per Flat (₹)</label>
                            <input type="number" name="amount" placeholder="0.00" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Due Date</label>
                            <input type="date" name="due_date" value="<?php echo wp_date('Y-m-d', strtotime('+7 days')); ?>" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Invoice Modal -->
<div class="modal fade" id="editInvoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="editInvTitle">Edit Invoice</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="edit-invoice-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_edit_invoice">
                    <input type="hidden" name="invoice_id" value="">
                    <?php wp_nonce_field( 'shubx51_account_action' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Description</label>
                        <input type="text" name="description" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Amount (₹)</label>
                            <input type="number" step="0.01" name="amount" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Due Date</label>
                            <input type="date" name="due_date" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label small fw-bold text-secondary mb-2">Payment History</label>
                        <div class="bg-light rounded-3 border border-light overflow-hidden">
                            <table class="table table-sm table-borderless align-middle mb-0" style="font-size: 11px;">
                                <thead class="bg-white bg-opacity-50">
                                    <tr>
                                        <th class="px-3">Date</th>
                                        <th class="px-3">Amount</th>
                                        <th class="px-3 text-end">X</th>
                                    </tr>
                                </thead>
                                <tbody id="edit-invoice-payments"></tbody>
                            </table>
                            <div id="no-payments-msg" class="p-3 text-center text-muted small d-none">No payments recorded.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="paymentModalTitle">Record Payment</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="payment-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_record_payment">
                    <input type="hidden" name="invoice_id" value="">
                    <?php wp_nonce_field( 'shubx51_account_action' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Amount Received (₹)</label>
                        <input type="number" name="amount" id="pay-amount" class="form-control shadow-none rounded-3 border-light" required>
                    </div>
                    
                    <div class="row g-3 mb-3">
                         <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Date</label>
                            <input type="date" name="date" value="<?php echo wp_date('Y-m-d'); ?>" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Method</label>
                            <select name="method" class="form-select shadow-none rounded-3 border-light">
                                <option value="UPI">UPI</option><option value="Cash">Cash</option>
                                <option value="Cheque">Cheque</option><option value="Bank Transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    
                     <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Reference / Note</label>
                        <input type="text" name="reference" class="form-control shadow-none rounded-3 border-light" placeholder="Transaction ID or Receipt No.">
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success border-0 px-4 fw-bold shadow-sm rounded-3">Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-receipt modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-start">
                <h5 class="modal-title fw-bold text-dark">Receipt</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body modal-body-receipt p-4">
                <div id="receipt-content" class="receipt d-flex align-items-center justify-content-center" style="min-height: 200px;">
                    <div class="text-center text-muted">
                        <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                        <p class="small mb-0">Loading receipt details...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-3">
                <button type="button" class="btn btn-light rounded-3 shadow-none" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary rounded-3" onclick="downloadReceipt(event)">
                    <i class="bi bi-download me-2"></i>Download Receipt
                </button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>


<!-- Accounts page JS moved to `assets/js/shubx-accounts.js` -->

<script>
// Receipt Functions for Admin
window.openAdminReceipt = function (btn) {
    let invoiceId;
    try {
        const inv = JSON.parse(btn.getAttribute('data-invoice'));
        invoiceId = inv.id;
    } catch (e) {
        invoiceId = btn.getAttribute('data-invoice-id');
    }

    if (!invoiceId) {
        alert('Invoice ID not found');
        return;
    }

    // Prepare modal (clear old content and show loading)
    const receiptContent = document.getElementById('receipt-content');
    if (receiptContent) {
        receiptContent.innerHTML = `
            <div class="text-center py-5 w-100">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="text-muted">Fetching receipt details...</div>
            </div>
        `;
    }

    // Show modal first (it will show the spinner)
    if (window.bootstrap && window.bootstrap.Modal) {
        const modalEl = document.getElementById('receiptModal');
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) modal = new bootstrap.Modal(modalEl);
        modal.show();
    }

    // Make AJAX request to fetch receipt data
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=shubx51_get_receipt&invoice_id=' + encodeURIComponent(invoiceId) + '&nonce=' + SHUBX51AdminNonce
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateReceiptModal(data.data);
            } else {
                if (receiptContent) {
                    receiptContent.innerHTML = `<div class="alert alert-danger mx-4 mt-4">${data.data.message || 'Error loading receipt'}</div>`;
                }
            }
        })
        .catch(error => {
            console.error('Receipt fetch error:', error);
            if (receiptContent) {
                receiptContent.innerHTML = `<div class="alert alert-danger mx-4 mt-4">Failed to fetch receipt data. Please check your connection.</div>`;
            }
        });
};

function populateReceiptModal(receiptData) {
    const receiptContent = document.getElementById('receipt-content');
    if (!receiptContent) return;

    // Reset flex classes and padding for A4
    receiptContent.className = 'receipt';
    receiptContent.style.minHeight = 'auto';

    // Calculate payment details
    let paymentRows = '';

    if (receiptData.payments && receiptData.payments.length > 0) {
        receiptData.payments.forEach(p => {
            const ref = p.reference || p.ref || '-';
            paymentRows += `
                <tr>
                    <td>${p.method || 'Payment'} <br><small class="text-muted">${p.date || ''}</small></td>
                    <td class="text-end fw-bold">₹${parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                    <td class="text-muted small">${ref}</td>
                </tr>
            `;
        });
    }

    const invoiceAmount = parseFloat(receiptData.invoice_amount || 0);
    const totalPaid = parseFloat(receiptData.total_paid || 0);
    const balanceDue = parseFloat(receiptData.balance_due || 0);

    const statusClass = receiptData.status === 'paid' ? 'bg-success text-white' : (receiptData.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger text-white');
    const statusText = receiptData.status === 'paid' ? 'FULLY PAID' : (receiptData.status === 'partial' ? 'PARTIALLY PAID' : 'UNPAID');

    receiptContent.innerHTML = `
        <!-- Header -->
        <div class="receipt-header-standard">
            <h2 class="fw-bold text-primary mb-1">${receiptData.society_name || 'Society Name'}</h2>
            <p class="text-muted mb-0">Payment Receipt <strong class="receipt-no">#${receiptData.receipt_number}</strong></p>
        </div>

        <!-- Info Grid -->
        <div class="receipt-grid">
            <div>
                <span class="receipt-label">Resident Name</span>
                <div class="receipt-value">${receiptData.resident_name}</div>
            </div>
            <div>
                <span class="receipt-label">Flat / Unit No.</span>
                <div class="receipt-value">${receiptData.flat_no}</div>
            </div>
            <div>
                <span class="receipt-label">Billing Period</span>
                <div class="receipt-value">${new wp_date(receiptData.invoice_month + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</div>
            </div>
            <div>
                <span class="receipt-label">Purpose</span>
                <div class="receipt-value">${receiptData.description || 'Society Maintenance'}</div>
            </div>
        </div>

        <!-- Payment Table -->
        <h5 class="fw-bold mb-3 mt-4">Transaction Details</h5>
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Method / Date</th>
                    <th class="text-end">Amount Paid</th>
                    <th>Reference ID</th>
                </tr>
            </thead>
            <tbody>
                ${paymentRows || '<tr><td colspan="3" class="py-4 text-center text-muted">No payments recorded</td></tr>'}
            </tbody>
        </table>

        <!-- Summary -->
        <div class="receipt-summary">
            <div class="summary-row">
                <span>Invoice Total</span>
                <span>₹${invoiceAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
            <div class="summary-row text-success fw-bold">
                <span>Total Received</span>
                <span>₹${totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
            <div class="summary-row grand-total">
                <span>Balance Due</span>
                <span>₹${balanceDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
            </div>
        </div>

        <!-- Status -->
        <div class="receipt-status-wrap">
            <span class="receipt-badge ${statusClass}">${statusText}</span>
        </div>

        <!-- Footer -->
        <div class="receipt-footer-standard">
            <p class="mb-1">This is a computer-generated document. It does not require a physical signature.</p>
            <p class="mb-0">Society HubX - Empowering Communities</p>
        </div>
    `;
}


window.downloadReceipt = function(event) {
    const e = event || window.event;
    const receiptElement = document.getElementById('receipt-content');
    if (!receiptElement || receiptElement.querySelector('.spinner-border')) {
        alert('Please wait for receipt to load fully.');
        return;
    }

    if (typeof html2canvas === 'undefined') {
        alert('Image generation library not loaded. Please try again in a few seconds.');
        return;
    }

    // Show loading state
    const btn = e ? e.target.closest('button') : null;
    const originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    }

    // Use html2canvas to convert to image
    html2canvas(receiptElement, {
        scale: 2,
        logging: false,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        // Create download link
        const link = document.createElement('a');
        const receiptNumber = receiptElement.querySelector('.receipt-no')?.textContent || 'Receipt';
        link.href = canvas.toDataURL('image/png');
        link.download = `${receiptNumber.replace('#', '')}.png`;
        link.click();

        // Restore button
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }).catch(error => {
        console.error('Download error:', error);
        alert('Error generating receipt image. Please try again.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });
};

</script>

<script>
       // Invoice & Ledger Search Implementation
    let invoiceFuse = null;
    let ledgerFuse = null;

    function applyAccountSearch() {
        const searchInput = document.getElementById('account-filter-search');
        const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
        
        // Determine which tab is active
        const invoiceTab = document.querySelector('[href="?page=shubx51-accounts&tab=invoices"]');
        const isInvoicesTab = invoiceTab && invoiceTab.classList.contains('active');
        
        if (isInvoicesTab) {
            // Search invoices
            if (!invoiceFuse && window.SHUBXCreateFuse) {
                invoiceFuse = window.SHUBXCreateFuse('.invoice-row');
            }

            const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(invoiceFuse, searchVal) : null;

            document.querySelectorAll('.invoice-row').forEach(row => {
                const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

                if (matchSearch) {
                    row.classList.remove('d-none');
                    row.style.display = '';
                } else {
                    row.classList.add('d-none');
                }
            });

            if (searchVal && fuzzyMatches) {
                console.log(`Invoice Search: Found ${fuzzyMatches.size} matches for "${searchVal}"`);
            }
        } else {
            // Search ledger
            if (!ledgerFuse && window.SHUBXCreateFuse) {
                ledgerFuse = window.SHUBXCreateFuse('.ledger-row');
            }

            const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(ledgerFuse, searchVal) : null;

            document.querySelectorAll('.ledger-row').forEach(row => {
                const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

                if (matchSearch) {
                    row.classList.remove('d-none');
                    row.style.display = '';
                } else {
                    row.classList.add('d-none');
                }
            });

            if (searchVal && fuzzyMatches) {
                console.log(`Ledger Search: Found ${fuzzyMatches.size} matches for "${searchVal}"`);
            }
        }
    }

    const searchInput = document.getElementById('account-filter-search');
    if (searchInput) {
        searchInput.addEventListener('input', applyAccountSearch);
        searchInput.addEventListener('focus', function () {
            // Re-index on focus based on active tab
            const invoiceTab = document.querySelector('[href="?page=shubx51-accounts&tab=invoices"]');
            const isInvoicesTab = invoiceTab && invoiceTab.classList.contains('active');
            
            if (window.SHUBXCreateFuse) {
                if (isInvoicesTab) {
                    invoiceFuse = window.SHUBXCreateFuse('.invoice-row');
                } else {
                    ledgerFuse = window.SHUBXCreateFuse('.ledger-row');
                }
            }
        });
        
        // Clear search when switching tabs
        searchInput.value = '';
    }

    
// ===== CHART RENDERING =====
let cashFlowChart = null;
let categoryChart = null;
let collectionChart = null;
let currentChartView = 'monthly';

function initCharts() {
    if (!window.Chart || !window.SHUBXAccountsChartData) {
        console.log('Chart.js or chart data not available');
        return;
    }

    renderCashFlowChart();
    renderCollectionChart();
    renderCategoryChart();
}

function renderCashFlowChart() {
    const container = document.getElementById("cashFlowChart");
    if (!container) return;

    const chartData = window.SHUBXAccountsChartData.monthlyData;
    if (!chartData) return;

    const labels = [];
    const incomeData = [];
    const expenseData = [];

    // Process each month - show both income and expense as separate entries
    for (const [month, data] of Object.entries(chartData)) {
        labels.push(month);
        incomeData.push(data.income || 0);
        expenseData.push(data.expense || 0);
    }

    if (cashFlowChart) {
        cashFlowChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    cashFlowChart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Income (₹)',
                    data: incomeData,
                    backgroundColor: '#10b981',
                    borderRadius: 4
                },
                {
                    label: 'Expense (₹)',
                    data: expenseData,
                    backgroundColor: '#ef4444',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Society Cash Flow (Income vs Expense)',
                    font: {
                        size: 16,
                        family: 'Inter, sans-serif'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function renderCollectionChart() {
    const container = document.getElementById("collectionChart");
    if (!container) return;

    const data = window.SHUBXAccountsChartData.collectionData;
    if (!data) return;

    const total = data.paid + data.unpaid + data.partial;
    if (total === 0) return;

    if (collectionChart) {
        collectionChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    collectionChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Partial', 'Unpaid'],
            datasets: [{
                data: [data.paid, data.partial, data.unpaid],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = Math.round((val / total) * 100);
                            return context.label + ': ' + val + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

function renderCategoryChart() {
    const container = document.getElementById("expenseCategoryChart");
    if (!container) return;

    const data = window.SHUBXAccountsChartData.categoryData;
    if (!data || Object.keys(data).length === 0) return;

    const labels = [];
    const values = [];

    for (const [cat, val] of Object.entries(data)) {
        labels.push(cat);
        values.push(val);
    }

    if (categoryChart) {
        categoryChart.destroy();
    }

    container.innerHTML = '';
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);

    categoryChart = new Chart(canvas, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: values,
                backgroundColor: [
                    '#6366f1', '#10b981', '#f59e0b', '#ef4444', 
                    '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ₹' + context.raw.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

function switchChartView(view) {
    currentChartView = view;

    const btnMonthly = document.getElementById('btn-chart-view-monthly');
    const btnYearly = document.getElementById('btn-chart-view-yearly');

    if (view === 'monthly') {
        if(btnMonthly) btnMonthly.classList.add('active');
        if(btnYearly) btnYearly.classList.remove('active');
    } else {
        if(btnYearly) btnYearly.classList.add('active');
        if(btnMonthly) btnMonthly.classList.remove('active');
    }

    renderCashFlowChart();
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function () {
    if (window.Chart) {
        initCharts();
    } else {
        console.error('Chart.js not loaded');
    }
    
    // --- Real-time Admin Sync (Optimistic UI) ---
    initAdminPaymentSync();
});

function initAdminPaymentSync() {
    let currentHash = null;
    let isPolling = false;
    
    async function pollState() {
        if (isPolling) return;
        isPolling = true;
        
        try {
            const formData = new URLSearchParams();
            formData.append('action', 'shubx51_poll_state_hash');
            formData.append('_wpnonce', window.shubx51_admin_nonce);
            
            const req = await fetch(window.ajaxurl, {
                method: 'POST',
                body: formData
            });
            const res = await req.json();
            
            if (res.success && res.data && res.data.hash) {
                if (currentHash === null) {
                    currentHash = res.data.hash;
                } else if (currentHash !== res.data.hash) {
                    console.log('SHUBX Admin: State Hash changed. Syncing UI...');
                    currentHash = res.data.hash;
                    await refreshAdminDashboard();
                }
            }
        } catch(e) {
            console.error('SHUBX Admin Sync Error:', e);
        }
        
        isPolling = false;
        setTimeout(pollState, 4000); // 4 Seconds
    }
    
    async function refreshAdminDashboard() {
        try {
            const req = await fetch(window.location.href);
            if (!req.ok) return;
            const html = await req.text();
            const doc = new DOMParser().parseFromString(html, 'text/html');
            
            const currentContent = document.querySelector('.mb-5.px-1')?.parentNode;
            const newContent = doc.querySelector('.mb-5.px-1')?.parentNode;
            
            if (currentContent && newContent) {
                // Destroy old charts to prevent memory leak before replacing content
                if (cashFlowChart) cashFlowChart.destroy();
                if (categoryChart) categoryChart.destroy();
                if (collectionChart) collectionChart.destroy();
                
                // Replace HTML
                currentContent.innerHTML = newContent.innerHTML;
                
                // Update chart data from the new script block
                const scripts = doc.querySelectorAll('script');
                scripts.forEach(s => {
                    if (s.textContent.includes('SHUBXAccountsChartData')) {
                        try {
                            eval(s.textContent); 
                            initCharts(); 
                        } catch(err) {}
                    }
                });
                
                if (window.SHUBX && window.SHUBX.toast) {
                    SHUBX.toast.success('Live Update: Financials synced in real-time.', { icon: 'check-circle' });
                }
            }
        } catch(e) {
            console.error('SHUBX Admin Refresh Error:', e);
        }
    }
    
    setTimeout(pollState, 2000);
}

</script>