<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * Template: Payment Receipt (Print Friendly)
 * Variables: $inv (Invoice Array)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$society_name = get_option('sgvx51_society_name', get_bloginfo('name'));
$total_paid = 0;
foreach ( $inv['payments'] as $p ) $total_paid += $p['amount'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo esc_html( $inv['id'] ); ?></title>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; color: #1e293b; background: #f8fafc; line-height: 1.5; }
        .receipt-box { background: white; border: 1px solid #e2e8f0; padding: 40px; max-width: 750px; margin: 0 auto; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); position: relative; }
        .top-stripe { position: absolute; top: 0; left: 0; right: 0; height: 4px; background: #4f46e5; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 30px; }
        .header-left h1 { margin: 0; font-size: 20px; color: #0f172a; }
        .address { font-size: 11px; color: #64748b; max-width: 250px; margin-top: 5px; }
        .header-right { text-align: right; }
        .receipt-title { font-size: 28px; font-weight: 200; color: #cbd5e1; text-transform: uppercase; letter-spacing: 4px; margin: 0; }
        
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 30px; }
        .section-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .section-val { font-size: 14px; font-weight: 600; color: #1e293b; }
        .section-val-sub { font-size: 13px; color: #475569; }

        .table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        .table th { text-align: left; font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; padding: 12px 0; border-bottom: 2px solid #f1f5f9; }
        .table td { padding: 16px 0; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .table .amt { text-align: right; font-family: ui-monospace, monospace; font-weight: 600; }

        .footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; }
        .total-box { border-top: 1px solid #1e293b; padding-top: 10px; }
        .total-row { display: flex; justify-content: space-between; width: 220px; gap: 20px; }
        .total-label { font-weight: 700; font-size: 14px; color: #0f172a; }
        .total-val { font-weight: 700; font-size: 18px; color: #4f46e5; }

        .disclaimer { margin-top: 50px; text-align: center; }
        .disclaimer-badge { display: inline-block; background: #f8fafc; color: #64748b; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 8px 20px; border-radius: 99px; border: 1px solid #f1f5f9; }

        .no-print-area { position: fixed; top: 20px; right: 20px; }
        .btn { padding: 8px 16px; border-radius: 6px; border: 1px solid #d1d5db; background: white; cursor: pointer; font-size: 13px; font-weight: 500; }
        .btn-primary { background: #4f46e5; color: white; border: none; }

        @media print {
            body { background: white; padding: 0; }
            .receipt-box { border: none; box-shadow: none; max-width: 100%; padding: 0; }
            .no-print-area { display: none; }
        }
    </style>
</head>
<body>
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
                        $addr1 = get_option('sgvx51_society_address_line1');
                        $city  = get_option('sgvx51_society_city');
                        $pin   = get_option('sgvx51_society_pincode');
                        $parts = array_filter([$addr1, $city . ($pin ? " - $pin" : "")]);
                        echo implode(', ', array_map('esc_html', $parts));
                    ?>
                </div>
            </div>
            <div class="header-right">
                <h2 class="receipt-title">Receipt</h2>
                <div style="margin-top: 10px;">
                    <span class="section-label">NO:</span> <span style="font-family:monospace; color:#4f46e5; font-weight:700;">#<?php echo substr($inv['id'], -6); ?></span>
                </div>
                <div style="margin-top: 4px;">
                    <span class="section-label">Date:</span> <span class="section-val" style="font-size:12px;"><?php echo wp_date('d M Y'); ?></span>
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
                 <div class="section-val-sub"><?php echo wp_date('F Y', strtotime($inv['month'])); ?></div>
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
                    <td><?php echo wp_date('d M Y', strtotime($p['date'])); ?></td>
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
