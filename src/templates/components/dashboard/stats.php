<?php
/**
 * Component: Dashboard Stats Overview
 * @var array $data Dashboard data.
 */

$total_dues = 0;
if ( ! empty( $data['invoices'] ) ) {
    foreach ( $data['invoices'] as $inv ) {
        $paid = 0;
        $payments = $inv['payments'] ?? [];
        if ( is_string( $payments ) ) {
            $payments = json_decode( $payments, true );
        }
        if ( is_array( $payments ) ) {
            foreach ( $payments as $p ) $paid += (float) ( $p['amount'] ?? 0 );
        }
        $balance = (float) ( $inv['amount'] ?? 0 ) - $paid;
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
<!-- Stats Overview -->
<div class="row g-4 mb-4">
    <!-- Dues Card -->
    <div class="col-md-4">
        <?php 
        $card_bg = 'bg-primary'; 
        $card_icon = 'bi-wallet2';
        if ( $has_pending_total_payment ) {
            $card_bg = 'bg-info'; 
            $card_icon = 'bi-clock-history';
        } elseif ( $total_dues == 0 ) {
            $card_bg = 'bg-success'; 
            $card_icon = 'bi-check-circle';
        }
        ?>
        <div class="<?php echo $card_bg; ?> text-white rounded-3 shadow-sm p-4 position-relative overflow-hidden h-100">
            <div class="position-relative z-10">
                 <div class="d-flex justify-content-between align-items-start mb-2">
                     <h3 class="opacity-75 small fw-medium mb-0">Total Dues</h3>
                     <i class="<?php echo $card_icon; ?> fs-4 opacity-50"></i>
                 </div>
                 <div class="fs-2 fw-bold mb-3">₹<?php echo sgvx_in_fmt($total_dues, 0); ?></div>
                  <?php if ( $has_pending_total_payment ) : ?>
                     <div class="d-flex align-items-center gap-2 bg-white bg-opacity-15 rounded-2 px-3 py-2">
                         <div class="spinner-grow spinner-grow-sm" role="status" style="width: 0.75rem; height: 0.75rem;">
                             <span class="visually-hidden">Loading...</span>
                         </div>
                         <span class="small fw-medium">Under Admin Review</span>
                     </div>
                  <?php elseif ( $total_dues > 0 ) : ?>
                  <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" 
                          data-amount="<?php echo esc_attr($total_dues); ?>"
                          data-invoice-id="Total Outstanding"
                          class="js-btn-pay btn w-100 py-2 border border-white border-opacity-25 rounded-3 text-sm fw-medium text-white shadow-none" style="background: rgba(255,255,255,0.1);">
                     <i class="bi bi-credit-card me-2"></i>Make Payment
                 </button>
                  <?php else : ?>
                     <div class="d-flex align-items-center justify-content-center gap-2 bg-white bg-opacity-95 rounded-2 px-3 py-2">
                         <i class="bi bi-check-circle-fill text-success"></i>
                         <span class="small fw-bold text-success">All Cleared!</span>
                     </div>
                  <?php endif; ?>
            </div>
            <div class="position-absolute bg-white bg-opacity-10 rounded-circle" style="width: 151px; height: 151px; bottom: -40px; right: -40px;"></div>
        </div>
    </div>

    <!-- Notices Card -->
    <div class="col-md-4">
        <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100 d-flex flex-column justify-content-between">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h3 class="text-secondary small fw-medium">Active Notices</h3>
                    <div class="fs-2 fw-bold text-dark mt-1"><?php echo count( $data['notices'] ?? [] ); ?></div>
                </div>
                <div class="bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-warning" style="width: 40px; height: 40px;">
                    <i class="bi bi-bell-fill fs-4"></i>
                </div>
            </div>
            <p class="small text-muted m-0">Latest updates from the society.</p>
        </div>
    </div>

    <!-- Documents Card -->
     <div class="col-md-4">
         <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100 d-flex flex-column justify-content-between">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h3 class="text-secondary small fw-medium">My Documents</h3>
                    <div class="fs-2 fw-bold text-dark mt-1"><?php echo count( $data['my_docs'] ?? [] ); ?></div>
                </div>
                <div class="bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-success" style="width: 40px; height: 40px;">
                    <i class="bi bi-file-earmark-text-fill fs-4"></i>
                </div>
            </div>
            <p class="small text-muted m-0">Securely stored files.</p>
        </div>
    </div>
</div>
