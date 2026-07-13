<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * Template: Payment Receipt (Print Friendly)
 * Variables: $inv (Invoice Array)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$society_name = get_option('shubx51_society_name', get_bloginfo('name'));
$total_paid = 0;
foreach ( $inv['payments'] as $p ) $total_paid += $p['amount'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo esc_html( $inv['id'] ); ?></title>
    <?php
    wp_enqueue_style( 'shubx51_receipt_css', SHUBX51_PLUGIN_URL . 'assets/css/receipt.css', array(), SHUBX51_VERSION );
    wp_print_styles( 'shubx51_receipt_css' );
    ?>
</head>
<body class="shubx51-print-receipt-body">
    <div class="no-print-area">
        <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        <button class="btn" onclick="window.close()" style="margin-left:8px;">Close</button>
    </div>

    <div class="receipt-box">
        <div class="top-stripe"></div>
        
        <div class="header">
            <div class="header-left">
                <h1><?php echo esc_html( $society_name ); ?></h1>
                <div class="address">
                    <?php 
                        $addr1 = get_option('shubx51_society_address_line1');
                        $city  = get_option('shubx51_society_city');
                        $pin   = get_option('shubx51_society_pincode');
                        $parts = array_filter([$addr1, $city . ($pin ? " - $pin" : "")]);
                        echo implode(', ', array_map('esc_html', $parts));
                    ?>
                </div>
            </div>
            <div class="header-right">
                <h2 class="receipt-title">Receipt</h2>
                <div style="margin-top: 10px;">
                    <span class="section-label">NO:</span> <span style="font-family:monospace; color:#4f46e5; font-weight:700;">#<?php echo esc_html( substr($inv['id'], -6) ); ?></span>
                </div>
                <div style="margin-top: 4px;">
                    <span class="section-label">Date:</span> <span class="section-val" style="font-size:12px;"><?php echo esc_html( wp_date('d M Y') ); ?></span>
                </div>
            </div>
        </div>

        <div class="meta-grid">
            <div>
                <div class="section-label">Received From</div>
                <div class="section-val"><?php echo esc_html( $inv['resident_name'] ); ?></div>
                <div class="section-val-sub">Flat #<?php echo esc_html( $inv['flat_no'] ); ?></div>
            </div>
            <div style="text-align: right;">
                 <div class="section-label">Description</div>
                 <div class="section-val"><?php echo esc_html( $inv['description'] ); ?></div>
                 <div class="section-val-sub"><?php echo esc_html( wp_date('F Y', strtotime($inv['month'])) ); ?></div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th width="120">Date</th>
                    <th>Payment Method / Reference</th>
                    <th class="amt" width="150">Amount Received</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($inv['payments'] as $p): ?>
                <tr>
                    <td><?php echo esc_html( wp_date('d M Y', strtotime($p['date'])) ); ?></td>
                    <td>
                        <div style="font-weight: 600;"><?php echo esc_html($p['method']); ?></div>
                        <div style="font-size: 11px; color: #94a3b8;"><?php echo esc_html($p['reference']); ?></div>
                    </td>
                    <td class="amt">₹<?php echo number_format($p['amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <div style="font-size: 10px; color: #94a3b8; max-width: 300px;">
                <strong>Terms:</strong> This is an official acknowledgement of payment realization. Subject to realization of instruments.
            </div>
            <div class="total-box">
                <div class="total-row">
                    <span class="total-label">Total Paid (INR)</span>
                    <span class="total-val">₹<?php echo number_format($total_paid, 2); ?></span>
                </div>
            </div>
        </div>

        <div class="disclaimer">
            <span class="disclaimer-badge">
                This is a computer generated receipt. No signature required.
            </span>
        </div>
    </div>
</body>
</html>
