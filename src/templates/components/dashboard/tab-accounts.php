<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Accounts Tab
 * @var array $data Dashboard data.
 */

// Calculate Total Dues (re-calculated here to be self-contained if needed, though they should be in $data)
$total_dues = 0;
if ( ! empty( $data['invoices'] ) ) {
    foreach ( $data['invoices'] as $inv ) {
        // If status is explicitly PAID, then balance is 0 (handles imported data with empty JSON)
        if ( (strtolower(trim($inv['status'] ?? '')) === 'paid') ) {
             continue; // No dues for this invoice
        }

        $paid = 0;
        $payments = $inv['payments'] ?? [];
        if ( is_array( $payments ) ) {
            foreach ( $payments as $p ) $paid += (float)($p['amount'] ?? 0);
        }
        $balance = (float)($inv['amount'] ?? 0) - $paid;
        if ( $balance > 0 ) $total_dues += $balance;
    }
}

$has_pending_total_payment = false;
$pending_payment_total = 0;
if ( ! empty( $data['pending_payment_requests'] ) ) {
    foreach ( $data['pending_payment_requests'] as $pr ) {
        $p_payload = is_array($pr['payload'] ?? null) ? $pr['payload'] : json_decode( $pr['payload'] ?? '{}', true );
        if ( ! $p_payload ) continue;
        $pending_payment_total += (float)($p_payload['amount'] ?? 0);
        if ( ( $p_payload['invoice_id'] ?? '' ) === 'Total Outstanding' ) {
            $has_pending_total_payment = true;
        }
    }
}
$display_dues = max(0, $total_dues - $pending_payment_total);
?>
<!-- 4. ACCOUNTS TAB -->
<div id="tab-accounts" class="tab-content d-none">
    <!-- Reordered: Cards First -->
    <div class="row g-4 mb-4">
         <div class="col-md-6">
              <div class="bg-primary text-white rounded-3 shadow-sm p-4 position-relative overflow-hidden h-100">
                  <div class="position-relative z-10">
                      <div class="d-flex justify-content-between align-items-start">
                          <div>
                              <p class="opacity-75 small fw-bold text-uppercase mb-1">Your Pending Dues</p>
                              <h2 class="display-6 fw-bold m-0">₹<?php echo SHUBX_in_fmt($display_dues, 0); ?></h2>
                          </div>
                          <div class="p-2 bg-white bg-opacity-25 rounded-3">
                           <i class="bi bi-exclamation-triangle-fill fs-3 text-white"></i>
                       </div>
                   </div>
                   <?php if($has_pending_total_payment || $pending_payment_total > 0): ?>
                      <div class="text-white-50 small text-center mt-3">₹<?php echo SHUBX_in_fmt($pending_payment_total, 0); ?> Awaiting Admin Verification</div>
                   <?php elseif($total_dues > 0): ?>
                       <button data-bs-toggle="modal" data-bs-target="#SHUBX51PaymentModal" 
                               data-amount="<?php echo esc_attr($total_dues); ?>"
                               data-invoice-id="Total Outstanding"
                               class="js-btn-pay btn btn-light w-100 fw-bold text-primary shadow-sm rounded-3 mt-3">Pay Now</button>
                   <?php else: ?>
                      <div class="text-white-50 small text-center mt-3">No Outstanding Dues</div>
                   <?php endif; ?>
                  </div>
              </div>
         </div>
         <div class="col-md-6">
              <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100">
                  <h4 class="fw-bold text-dark mb-3 small d-flex align-items-center gap-2">
                     <span class="rounded-circle bg-primary" style="width: 8px; height: 8px;"></span> Account Summary
                  </h4>
                  <ul class="list-unstyled m-0 d-flex flex-column gap-3">
                       <li class="d-flex justify-content-between border-bottom border-light pb-2">
                           <span class="text-secondary small">Total Unpaid Invoices</span>
                           <span class="fw-bold text-dark small">₹<?php echo SHUBX_in_fmt($total_dues, 0); ?></span>
                       </li>
                       <li class="d-flex justify-content-between border-bottom border-light pb-2">
                           <span class="text-secondary small">Pending Verification</span>
                           <span class="fw-bold text-info small">- ₹<?php echo SHUBX_in_fmt($pending_payment_total, 0); ?></span>
                       </li>
                       <li class="d-flex justify-content-between">
                           <span class="text-dark small fw-bold">Current Outstanding</span>
                           <span class="fw-bold text-primary small">₹<?php echo SHUBX_in_fmt($display_dues, 0); ?></span>
                       </li>
                   </ul>
              </div>
         </div>
    </div>

    <!-- Resident Payment History Chart -->
    <div class="bg-white rounded-3 shadow-sm border border-light p-4 mb-4">
        <div id="paymentHistoryChart" style="height: 300px; width: 100%;"></div>
    </div>
    
    <!-- Payment History (Consolidated View) -->
    <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
         <div class="px-4 py-3 border-bottom border-light bg-light fw-semibold text-dark d-flex justify-content-between align-items-center">
             <span>Payment History</span>
             <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10">Consolidated Statement</span>
         </div>
         <div class="table-responsive" style="max-height: 500px;">
             <table class="table table-hover align-middle mb-0 text-sm">
                 <thead class="bg-light text-secondary text-uppercase small sticky-top">
                     <tr>
                         <th class="ps-4" style="width: 15%;">Date</th>
                         <th style="width: 35%;">Particulars</th>
                         <th style="width: 15%;">Amount</th>
                         <th style="width: 15%;">Method</th>
                         <th class="text-end pe-4" style="width: 20%;">Receipt</th>
                     </tr>
                 </thead>
                 <tbody>
                      <?php 
                        $all_payments = [];
                        if(!empty($data['invoices'])) {
                            foreach($data['invoices'] as $inv) {
                                $desc = $inv['description'] ?? 'Maintenance';
                                $month_label = !empty($inv['month']) ? wp_date('M Y', strtotime($inv['month'])) : '';
                                if($month_label && strpos($desc, $month_label) === false) $desc = "$month_label - $desc";

                                 // A. Check explicit payments relation
                                 $payments = $inv['payments'] ?? [];
                                 if ( is_array( $payments ) && !empty($payments) ) {
                                     foreach($payments as $p) {
                                        $all_payments[] = [
                                            'date' => $p['date'] ?? wp_date('Y-m-d'),
                                            'amount' => $p['amount'],
                                            'method' => $p['method'] ?? 'Recorded',
                                            'ref' => $p['reference'] ?? $p['id'] ?? '-',
                                            'desc' => $desc,
                                            'inv_id' => $inv['id'],
                                            'status' => 'paid'
                                        ];
                                    }
                                } 
                                // B. Fallback for legacy "Paid" status without explicit payment rows
                                elseif (strtolower(trim($inv['status'] ?? '')) === 'paid') {
                                    $pay_date = !empty($inv['payment_date']) && $inv['payment_date'] !== '0000-00-00 00:00:00' 
                                                ? wp_date('Y-m-d', strtotime($inv['payment_date'])) 
                                                : ($inv['created_at'] ? wp_date('Y-m-d', strtotime($inv['created_at'])) : wp_date('Y-m-d'));
                                    
                                    $all_payments[] = [
                                        'date' => $pay_date,
                                        'amount' => $inv['amount'],
                                        'method' => $inv['payment_mode'] ?? 'Recorded',
                                        'ref' => $inv['payment_ref'] ?? '-',
                                        'desc' => $desc,
                                        'inv_id' => $inv['id'],
                                        'status' => 'paid'
                                    ];
                                }
                            }
                        }

                        // C. Include Pending Payment Requests from Requests Table
                        if (!empty($data['pending_payment_requests'])) {
                            foreach ($data['pending_payment_requests'] as $pr) {
                                $p_payload = is_array($pr['payload'] ?? null) ? $pr['payload'] : json_decode($pr['payload'] ?? '{}', true);
                                if ( ! $p_payload ) continue;
                                
                                $status = $pr['status'] ?? 'pending';
                                $is_approved = ($status === 'approved');

                                $all_payments[] = [
                                    'date'   => $p_payload['date'] ?? wp_date('Y-m-d'),
                                    'amount' => $p_payload['amount'] ?? 0,
                                    'method' => $p_payload['method'] ?? 'UPI',
                                    'ref'    => $p_payload['reference'] ?? '-',
                                    'desc'   => ($is_approved ? 'Verified Payment: ' : 'Awaiting Verification: ') . ($p_payload['invoice_id'] ?? 'Dues'),
                                    'inv_id' => '-',
                                    'status' => ($is_approved ? 'paid' : 'pending')
                                ];
                            }
                        }
                        
                        // Sort by Date DESC
                        usort($all_payments, function($a, $b) {
                            $time_a = strtotime($a['date']);
                            $time_b = strtotime($b['date']);
                            if ($time_a == $time_b) {
                                // If same date, pending on top
                                if ($a['status'] === 'pending' && $b['status'] !== 'pending') return -1;
                                if ($b['status'] === 'pending' && $a['status'] !== 'pending') return 1;
                            }
                            return $time_b - $time_a;
                        });
                      ?>
                      
                      <?php if(empty($all_payments)): ?>
                          <tr><td colspan="5" class="text-center py-5 text-muted small">No payments recorded yet.</td></tr>
                      <?php else: ?>
                          <?php foreach ( $all_payments as $pay ) : ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?php echo wp_date('d M, Y', strtotime($pay['date'])); ?></td>
                                <td>
                                    <div class="fw-medium text-dark <?php echo ($pay['status'] === 'pending') ? 'fst-italic text-opacity-75' : ''; ?>">
                                        <?php echo esc_html($pay['desc']); ?>
                                        <?php if($pay['status'] === 'pending'): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10 ms-1" style="font-size: 8px;">PENDING</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted font-monospace">Ref: #<?php echo ($pay['inv_id'] !== '-') ? substr($pay['inv_id'], -6) : 'VERIFY'; ?></div>
                                </td>
                                <td class="font-monospace <?php echo ($pay['status'] === 'pending') ? 'text-secondary' : 'text-success'; ?> fw-bold">₹<?php echo SHUBX_in_fmt($pay['amount'], 0); ?></td>
                                <td>
                                    <div class="small text-dark"><?php echo esc_html($pay['method']); ?></div>
                                    <div class="text-muted font-monospace small" style="font-size: 10px;"><?php echo esc_html($pay['ref']); ?></div>
                                </td>
                                <td class="text-end pe-4">
                                     <?php if($pay['status'] !== 'pending' && $pay['inv_id'] !== '-'): ?>
                                         <button onclick="viewInvoiceReceipt(this)" data-invoice-id="<?php echo esc_attr($pay['inv_id']); ?>" class="btn btn-sm btn-light border border-secondary border-opacity-25 text-secondary fw-bold p-1 px-3 shadow-sm" title="Download Receipt">
                                            <i class="bi bi-download me-1"></i> Receipt
                                         </button>
                                     <?php else: ?>
                                         <span class="text-muted small">N/A</span>
                                     <?php endif; ?>
                                </td>
                            </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>
                 </tbody>
             </table>
         </div>
    </div>
</div>
