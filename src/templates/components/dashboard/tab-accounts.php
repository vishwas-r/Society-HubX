<?php
/**
 * Component: Dashboard Accounts Tab
 * @var array $data Dashboard data.
 */

// Calculate Total Dues (re-calculated here to be self-contained if needed, though they should be in $data)
$total_dues = 0;
if ( ! empty( $data['invoices'] ) ) {
    foreach ( $data['invoices'] as $inv ) {
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
    
    <!-- Billing History Table -->
    <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
         <div class="px-4 py-3 border-bottom border-light bg-light fw-semibold text-dark">Billing History</div>
         <div class="table-responsive">
             <table class="table table-hover align-middle mb-0 text-sm">
                 <thead class="bg-light text-secondary text-uppercase small">
                     <tr><th class="ps-4">Month</th><th>Desc</th><th>Amount</th><th>Status</th><th class="text-end pe-4">Action</th></tr>
                 </thead>
                 <tbody>
                      <?php foreach ( ($data['invoices'] ?? []) as $inv ) : 
                          $is_paid = ( $inv['status'] ?? '' ) === 'paid'; 
                          $paid = 0;
                          $payments = isset($inv['payments']) ? (is_string($inv['payments']) ? json_decode($inv['payments'], true) : $inv['payments']) : [];
                          if ( is_array( $payments ) ) {
                              foreach ( $payments as $p ) $paid += floatval($p['amount'] ?? 0);
                          }
                          $outstanding = floatval($inv['amount'] ?? 0) - $paid;
                          $pending_request = null;
                          if ( ! empty( $data['pending_payment_requests'] ) ) {
                              foreach ( $data['pending_payment_requests'] as $pr ) {
                                  $p_payload = json_decode( $pr['payload'] ?? '{}', true );
                                  if ( ( $p_payload['invoice_id'] ?? '' ) === ( $inv['id'] ?? '' ) ) { $pending_request = $pr; break; }
                              }
                          }
                      ?>
                        <tr>
                            <td class="ps-4 fw-medium text-dark"><?php echo date('M Y', strtotime($inv['month'] ?? 'now')); ?></td>
                            <td class="text-truncate text-secondary" style="max-width: 150px;"><?php echo esc_html($inv['description'] ?? 'N/A'); ?></td>
                            <td class="font-monospace text-dark">₹<?php echo sgvx_in_fmt($inv['amount'] ?? 0, 0); ?></td>
                            <td>
                                <?php if($is_paid): ?><span class="badge bg-success-subtle text-success rounded-pill">Paid</span>
                                <?php elseif($pending_request): ?><span class="badge bg-info-subtle text-info rounded-pill">Pending Verification</span>
                                <?php else: ?><span class="badge bg-warning-subtle text-warning text-dark rounded-pill">Unpaid</span><?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                 <?php if($is_paid): ?>
                                    <button onclick="viewInvoiceReceipt(this)" data-invoice-id="<?php echo esc_attr($inv['id'] ?? ''); ?>" class="btn btn-sm text-success fw-bold p-0 shadow-none border-0">View Receipt</button>
                                 <?php elseif($pending_request): ?>
                                    <span class="text-muted small">Awaiting Admin</span>
                                 <?php elseif($outstanding > 0): ?>
                                    <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" data-invoice-id="<?php echo esc_attr($inv['id'] ?? ''); ?>" data-amount="<?php echo esc_attr($outstanding); ?>" class="js-btn-pay btn btn-sm text-primary fw-bold p-0 shadow-none border-0">Pay</button>
                                 <?php endif; ?>
                            </td>
                        </tr>
                      <?php endforeach; ?>
                 </tbody>
             </table>
         </div>
    </div>
</div>
