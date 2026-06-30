<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * Template: Resident Dashboard (Bootstrap Migration)
 * Available Variables: $data (array)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r = $data['resident'];
$directory = $data['directory'] ?? [];

// Calculate Total Dues
$total_dues = 0;
if ( ! empty( $data['invoices'] ) ) {
    foreach ( $data['invoices'] as $inv ) {
        $paid = 0;
        if ( ! empty( $inv['payments'] ) && is_array( $inv['payments'] ) ) {
            foreach ( $inv['payments'] as $p ) $paid += (float) $p['amount'];
        }
        $balance = (float) $inv['amount'] - $paid;
        if ( $balance > 0 ) {
            $total_dues += $balance;
        }
    }
}


// Check if there's a pending payment request for "Total Outstanding"
$has_pending_total_payment = false;
if (!empty($data['pending_payment_requests'])) {
    foreach($data['pending_payment_requests'] as $pr) {
        $p_payload = is_array($pr['payload'] ?? null) ? $pr['payload'] : json_decode($pr['payload'] ?? '{}', true);
        if($p_payload && ($p_payload['invoice_id'] ?? '') === 'Total Outstanding') {
            $has_pending_total_payment = true;
            break;
        }
    }
}

// Helper for Indian Numbering Format
if ( ! function_exists( 'sgvx_in_fmt' ) ) {
    function sgvx_in_fmt($num, $decimals = 2) {
        $num = (float)$num;
        if (class_exists('NumberFormatter')) {
            $fmt = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
            $fmt->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $fmt->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $res = $fmt->format($num);
            if ($res !== false) return $res;
        }

        // Manual Fallback for Indian Numbering System
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
}
?>

<!-- Nonce for AJAX Requests -->
<script>
    var sgvx51_nonce = '<?php echo wp_create_nonce( 'sgvx51_frontend_nonce' ); ?>';
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
</script>

    <?php include 'components/dashboard/welcome.php'; ?>
    <?php include 'components/dashboard/stats.php'; ?>
    <?php include 'components/dashboard/navigation.php'; ?>


    <!-- Tab Contents -->
    <?php include 'components/dashboard/tab-home.php'; ?>
    <?php include 'components/dashboard/tab-notices.php'; ?>
    <?php include 'components/dashboard/tab-requests.php'; ?>
    <?php include 'components/dashboard/tab-notifications.php'; ?>
    <?php include 'components/dashboard/tab-community.php'; ?>
    <?php include 'components/dashboard/tab-accounts.php'; ?>
    <?php include 'components/dashboard/tab-expenses.php'; ?>
    <?php include 'components/dashboard/tab-facilities.php'; ?>
    <?php include 'components/dashboard/tab-polls.php'; ?>
    <?php include 'components/dashboard/tab-rules.php'; ?>


<!-- Modals are moved to footer or separate files usually, but kept inline for now with Bootstrap Modal structure -->
<!-- Replace simplified visible/hidden logic with Bootstrap Modals -->

<!-- Modals & Scripts -->
<?php include SGVX51_PLUGIN_DIR . 'templates/components/dashboard/modals.php'; ?>

<!-- Global Toasts Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-4" style="z-index: 100070;">
    <div id="sgvx-global-toast" class="toast align-items-center border-0 rounded-2xl shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-3 py-3 px-4">
                <i id="sgvx-toast-icon" class="bi fs-4"></i>
                <div id="sgvx-toast-message" class="fw-bold"></div>
            </div>
            <button type="button" class="btn-close btn-close-white me-3 m-auto opacity-50" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestForm = document.getElementById('sgvx51GeneralRequestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = 'Submitting...';

            const formData = new FormData(this);
            formData.append('action', 'sgvx51_submit_general_request');
            formData.append('_wpnonce', '<?php echo wp_create_nonce("sgvx51_frontend_nonce"); ?>');

            SGVX.ajax({
                action: 'sgvx51_submit_general_request',
                data: formData,
                loadingButton: btn,
                reload: true
            });
        });
    }
});
</script>

<?php // End of Resident Dashboard ?>
