<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Stats Overview
 * @var array $data Dashboard data.
 */

$total_dues = 0;
if ( ! empty( $data['invoices'] ) ) {
    foreach ( $data['invoices'] as $inv ) {
        // Simple Dues Logic: Trust the Status
        if ( strtolower(trim($inv['status'] ?? '')) === 'paid' ) {
             continue; // No Dues
        }

        // Calculate actual balance considering partial payments
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
        $p_payload = $pr['payload_decoded'] ?? (is_string($pr['payload'] ?? '') ? json_decode($pr['payload'], true) : ($pr['payload'] ?? []));
        if ( ! $p_payload ) continue;

        $pending_payment_total += (float)($p_payload['amount'] ?? 0);
        
        if ( ( $p_payload['invoice_id'] ?? '' ) === 'Total Outstanding' ) {
            $has_pending_total_payment = true; // Use this to show specific message if needed
        }
    }
}

// Net Dues to Display (Total - Pending)
$display_dues = max(0, $total_dues - $pending_payment_total);
?>
<!-- Stats Overview -->
<div class="row g-4 mb-5">
    <!-- Dues Card -->
    <div class="col-md-4">
        <?php 
        $status_class = 'primary'; 
        $status_icon = 'bi-wallet2';
        
        if ( $display_dues == 0 && $total_dues > 0 ) {
            $status_class = 'info'; 
            $status_icon = 'bi-clock-history';
        } elseif ( $display_dues == 0 ) {
            $status_class = 'success'; 
            $status_icon = 'bi-check-circle-fill';
        }
        ?>
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden h-100 transition-all hover-translate-y bg-<?php echo $status_class; ?> bg-opacity-10 border-start border-<?php echo $status_class; ?> border-4">
            <div class="card-body p-4 position-relative z-10">
                 <div class="d-flex justify-content-between align-items-center mb-3">
                     <span class="text-secondary small fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Total Maintenance Dues</span>
                     <div class="bg-<?php echo $status_class; ?> bg-opacity-20 rounded-3 p-2 d-flex align-items-center justify-content-center text-primary">
                        <i class="bi <?php echo $status_icon; ?> fs-5"></i>
                     </div>
                 </div>
                 <div class="h2 fw-bold text-dark m-0">₹<?php echo SNESTX_in_fmt($display_dues, 0); ?></div>
                 
                  <?php if ( $pending_payment_total > 0 && $display_dues == 0 ) : ?>
                     <div class="mt-3 d-flex align-items-center gap-2 bg-info bg-opacity-10 text-info rounded-2 px-3 py-2 small fw-bold border border-info border-opacity-10">
                         <div class="spinner-grow spinner-grow-sm" role="status"></div>
                         <span>Verifying: ₹<?php echo SNESTX_in_fmt($pending_payment_total, 0); ?></span>
                     </div>
                  <?php elseif ( $display_dues > 0 ) : ?>
                  <button data-bs-toggle="modal" data-bs-target="#SNESTX51PaymentModal" 
                          data-amount="<?php echo esc_attr($display_dues); ?>"
                          data-invoice-id="Total Outstanding"
                          class="js-btn-pay btn btn-<?php echo $status_class; ?> w-100 mt-3 py-2 rounded-3 fw-bold shadow-sm transition-all hover-translate-y">
                     <i class="bi bi-credit-card me-2"></i>Pay Outstanding
                 </button>
                  <?php else : ?>
                     <div class="mt-3 d-flex align-items-center justify-content-center gap-2 bg-success bg-opacity-10 text-success rounded-2 px-3 py-2 small fw-bold border border-success border-opacity-10">
                         <i class="bi bi-patch-check-fill"></i>
                         <span>Accounts Fully Cleared</span>
                     </div>
                  <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notices Card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white transition-all hover-translate-y">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Community Updates</span>
                <div class="bg-warning bg-opacity-10 rounded-3 p-2 d-flex align-items-center justify-content-center text-warning" style="width: 40px; height: 40px;">
                    <i class="bi bi-megaphone-fill fs-5"></i>
                </div>
            </div>
            <div class="h2 fw-bold text-dark m-0"><?php echo count( $data['notices'] ?? [] ); ?></div>
            <p class="text-muted small m-0 mt-2">Active broadcasted notices</p>
        </div>
    </div>

    <!-- Documents Card -->
     <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white transition-all hover-translate-y">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-secondary small fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Personal Vault</span>
                <div class="bg-primary bg-opacity-10 rounded-3 p-2 d-flex align-items-center justify-content-center text-primary" style="width: 40px; height: 40px;">
                    <i class="bi bi-shield-lock-fill fs-5"></i>
                </div>
            </div>
            <div class="h2 fw-bold text-dark m-0"><?php echo count( $data['my_docs'] ?? [] ); ?></div>
            <p class="text-muted small m-0 mt-2">Files securely archived</p>
        </div>
    </div>
</div>
