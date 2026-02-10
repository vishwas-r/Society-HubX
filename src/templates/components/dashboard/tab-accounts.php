<?php
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
        $payments = isset($inv['payments']) ? (is_string($inv['payments']) ? json_decode($inv['payments'], true) : $inv['payments']) : [];
        if ( is_array( $payments ) ) {
            foreach ( $payments as $p ) $paid += (float)($p['amount'] ?? 0);
        }
        $balance = (float)($inv['amount'] ?? 0) - $paid;
        if ( $balance > 0 ) $total_dues += $balance;
    }
}

$has_pending_total_payment = false;
if ( ! empty( $data['pending_payment_requests'] ) ) {
    foreach ( $data['pending_payment_requests'] as $pr ) {
        $p_payload = json_decode( $pr['payload'] ?? '{}', true );
        if ( ( $p_payload['invoice_id'] ?? '' ) === 'Total Outstanding' ) {
            $has_pending_total_payment = true;
            break;
        }
    }
}
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
                              <h2 class="display-6 fw-bold m-0">₹<?php echo sgvx_in_fmt($total_dues, 0); ?></h2>
                          </div>
                          <div class="p-2 bg-white bg-opacity-25 rounded-3">
                           <i class="bi bi-exclamation-triangle-fill fs-3 text-white"></i>
                       </div>
                   </div>
                   <?php if($has_pending_total_payment): ?>
                      <div class="text-white-50 small text-center mt-3">Awaiting Admin Verification</div>
                   <?php elseif($total_dues > 0): ?>
                       <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" 
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
                          <span class="text-secondary small">Unpaid Invoices</span>
                          <span class="fw-bold text-dark small"><?php echo count(array_filter($data['invoices'] ?? [], function($i){return ($i['status'] ?? '')!=='paid';})); ?></span>
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
                        $all_payments = [];
                        if(!empty($data['invoices'])) {
                            foreach($data['invoices'] as $inv) {
                                // Strictly use Flat Columns as requested
                                if(strtolower(trim($inv['status'] ?? '')) === 'paid') {
                                    $pay_date = !empty($inv['payment_date']) && $inv['payment_date'] !== '0000-00-00 00:00:00' 
                                                ? date('Y-m-d', strtotime($inv['payment_date'])) 
                                                : ($inv['created_at'] ? date('Y-m-d', strtotime($inv['created_at'])) : date('Y-m-d'));
                                    
                                    $desc = $inv['description'] ?? 'Maintenance';
                                    $month_label = date('M Y', strtotime($inv['month']));
                                    if(strpos($desc, $month_label) === false) $desc = "$month_label - $desc";

                                    $all_payments[] = [
                                        'date' => $pay_date,
                                        'amount' => $inv['amount'],
                                        'method' => $inv['payment_mode'] ?? 'Recorded',
                                        'ref' => $inv['payment_ref'] ?? '-',
                                        'desc' => $desc,
                                        'inv_id' => $inv['id'],
                                        'inv_obj' => $inv
                                    ];
                                }
                            }
                        }
                        
                        // Sort by Date DESC
                        usort($all_payments, function($a, $b) {
                            return strtotime($b['date']) - strtotime($a['date']);
                        });
                      ?>
                      
                      <?php if(empty($all_payments)): ?>
                          <tr><td colspan="5" class="text-center py-5 text-muted small">No payments recorded yet.</td></tr>
                      <?php else: ?>
                          <?php foreach ( $all_payments as $pay ) : ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?php echo date('d M, Y', strtotime($pay['date'])); ?></td>
                                <td>
                                    <div class="fw-medium text-dark"><?php echo esc_html($pay['desc']); ?></div>
                                    <div class="small text-muted font-monospace">INV: #<?php echo substr($pay['inv_id'], -6); ?></div>
                                </td>
                                <td class="font-monospace text-success fw-bold">₹<?php echo sgvx_in_fmt($pay['amount'], 0); ?></td>
                                <td>
                                    <div class="small text-dark"><?php echo esc_html($pay['method']); ?></div>
                                    <div class="text-muted font-monospace small" style="font-size: 10px;"><?php echo esc_html($pay['ref']); ?></div>
                                </td>
                                <td class="text-end pe-4">
                                     <button onclick="viewInvoiceReceipt(this)" data-invoice-id="<?php echo esc_attr($pay['inv_id']); ?>" class="btn btn-sm btn-light border border-secondary border-opacity-25 text-secondary fw-bold p-1 px-3 shadow-sm" title="Download Receipt">
                                        <i class="bi bi-download me-1"></i> Receipt
                                     </button>
                                </td>
                            </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>
                 </tbody>
             </table>
         </div>
    </div>
</div>
