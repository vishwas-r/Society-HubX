<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * View: Approval Requests
 * Consolidated view for all pending module requests.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Filter requests
$pending_statuses = array( 'pending', 'pending_secretary', 'pending_treasurer' );
$pending = array_filter( $requests, function($r) use ($pending_statuses) { 
    return in_array( $r['status'] ?? '', $pending_statuses ); 
} );
$history = array_filter( $requests, function($r) use ($pending_statuses) { 
    return ! in_array( $r['status'] ?? '', $pending_statuses ); 
} );

// Sort by date desc
usort($pending, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
usort($history, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });

?>

<div class="mb-5 px-1">
    <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Approval Workflow</h1>
    <p class="text-secondary m-0 mt-1">Review and process maintenance, registry, and data change requests.</p>
</div>

<!-- Stats Bar -->
<div class="row g-4 mb-5">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.05em; font-size: 10px;">Pending Action</div>
                    <div class="h3 fw-bold m-0"><?php echo count($pending); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-check-all fs-4"></i>
                </div>
                <div>
                    <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: 0.05em; font-size: 10px;">Processed Today</div>
                    <div class="h3 fw-bold m-0"><?php 
                        $today = wp_date('Y-m-d');
                        $processed_today = array_filter($history, function($r) use ($today) {
                            return strpos($r['processed_at'] ?? '', $today) === 0;
                        });
                        echo count($processed_today);
                    ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
    
    <!-- Toolbar -->
    <div class="p-4 px-md-5">
        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <h5 class="m-0 fw-bold">Active Requests</h5>
                <div class="dropdown shubx-bulk-actions d-none">
                    <button class="btn btn-outline-secondary dropdown-toggle btn-sm px-3 rounded-pill" type="button" data-bs-toggle="dropdown">
                        Bulk Actions (<span id="selected-count">0</span>)
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li><a class="dropdown-item fw-bold text-success" href="#" onclick="SHUBXBulkProcess('approve')">Approve Selected</a></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="#" onclick="SHUBXBulkProcess('reject')">Reject Selected</a></li>
                    </ul>
                </div>
            </div>
            <div class="d-flex gap-2">
                <input type="text" id="req-search" placeholder="Filter requests..." class="form-control form-control-sm rounded-pill px-3 border-light shadow-none" style="width: 200px;">
                <select id="req-filter-module" class="form-select form-select-sm rounded-pill border-light shadow-none" style="width: 150px;">
                    <option value="all">All Modules</option>
                    <option value="residents">Residents</option>
                    <option value="vehicles">Vehicles</option>
                    <option value="daily_help">Staff</option>
                    <option value="accounts">Finance / Payments</option>
                    <option value="documents">Documents</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr class="bg-light">
                    <th class="ps-5 py-4 border-0" style="width: 50px;">
                        <input type="checkbox" id="bulk-select-all" class="form-check-input bg-light border-slate-200 shadow-none">
                    </th>
                    <th class="ps-2 py-4 text-uppercase small text-muted fw-bold border-0" style="font-size: 10px;">Module</th>
                    <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0" style="font-size: 10px;">Request Type</th>
                    <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0" style="font-size: 10px;">Payload Details</th>
                    <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0" style="font-size: 10px;">Requested By</th>
                    <th class="pe-5 py-4 text-uppercase small text-muted fw-bold border-0 text-end" style="font-size: 10px;">Actions</th>
                </tr>
            </thead>
            <tbody id="request-table-body">
                <?php if ( empty( $pending ) ) : ?>
                    <tr>
                        <td colspan="6" class="px-5 py-5 text-center text-secondary opacity-50">
                            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                            <p class="m-0">No pending approval requests.</p>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $pending as $req ) : 
                        $module = $req['module'] ?: ($req['entity_type'] ?? 'unknown');
                        $action = $req['request_type'];
                        $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'], true);
                        $user = get_userdata($req['created_by']);
                        $name = $user ? $user->display_name : 'Unknown';
                    ?>
                        <tr class="request-row border-bottom border-light" data-module="<?php echo esc_attr($module); ?>">
                            <td class="ps-5 py-4">
                                <input type="checkbox" value="<?php echo esc_attr($req['id']); ?>" class="form-check-input shubx-bulk-checkbox bg-light border-slate-200 shadow-none">
                            </td>
                            <td class="ps-2 py-4">
                                <span class="badge bg-light text-dark text-capitalize px-3 py-1.5 rounded-pill border" style="font-size: 10px;">
                                    <?php echo esc_html(str_replace('_', ' ', $module)); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-<?php echo $action==='add'?'success':($action==='delete'?'danger':'info'); ?> bg-opacity-10 rounded-circle" style="width: 8px; height: 8px;"></div>
                                    <span class="fw-bold text-dark text-uppercase small" style="font-size: 10px;"><?php echo esc_html($action); ?></span>
                                    <?php if ( $req['status'] === 'pending_secretary' ) : ?>
                                        <span class="badge bg-info bg-opacity-10 text-info border-info border-opacity-25 ms-1" style="font-size: 8px;">SEC. REVIEW</span>
                                    <?php elseif ( $req['status'] === 'pending_treasurer' ) : ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border-primary border-opacity-25 ms-1" style="font-size: 8px;">TRES. REVIEW</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-secondary small text-truncate" style="max-width: 300px;">
                                    <?php 
                                        // Dynamic detail extraction
                                        if($module === 'finance' || $module === 'accounts') {
                                            echo "<strong>₹" . number_format($payload['amount'] ?? 0) . "</strong> | " . esc_html($payload['method'] ?? '') . " | <span class='text-primary'>" . esc_html($payload['reference'] ?? '') . "</span>";
                                        } else {
                                            if(isset($payload['category'])) echo "<strong>" . esc_html($payload['category']) . "</strong>";
                                            if(isset($payload['comments'])) echo " | <span class='text-muted'>" . esc_html($payload['comments']) . "</span>";
                                        }
                                        
                                        if(isset($payload['flat_no'])) echo " | Flat: " . esc_html($payload['flat_no']);
                                    ?>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-pill d-flex align-items-center justify-content-center fw-bold" style="width: 24px; height: 24px; font-size: 10px;">
                                        <?php echo substr($name, 0, 1); ?>
                                    </div>
                                    <span class="small fw-medium text-dark"><?php echo esc_html($name); ?></span>
                                </div>
                                <div class="text-muted small" style="font-size: 10px;"><?php echo wp_date('d M, h:i A', strtotime($req['created_at'])); ?></div>
                            </td>
                            <td class="pe-5 py-4 text-end">
                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-light border shadow-sm rounded-pill px-3 js-view-request-detail" 
                                            data-id="<?php echo esc_attr($req['id']); ?>"
                                            data-module="<?php echo esc_attr($module); ?>"
                                            data-request-type="<?php echo esc_attr($action); ?>"
                                            data-payload='<?php echo esc_attr( is_array($req['payload']) ? wp_json_encode($req['payload']) : ($req['payload'] ?: '{}') ); ?>'
                                            data-original='<?php echo esc_attr(isset($req['original_data']) ? ( is_array($req['original_data']) ? wp_json_encode($req['original_data']) : $req['original_data'] ) : "{}"); ?>'
                                            data-requester="<?php echo esc_attr($name); ?>"
                                            data-date="<?php echo esc_attr(wp_date('d M Y, h:i A', strtotime($req['created_at']))); ?>">
                                        <i class="bi bi-eye me-1"></i> VIEW
                                    </button>
                                    <?php echo SHUBX51_Admin_UI::render_approval_buttons( $req['id'], $module ); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- History Header -->
    <div class="p-4 px-md-5 bg-light border-top border-light mt-5 rounded-top-4">
        <h6 class="m-0 fw-bold text-primary text-uppercase" style="font-size: 11px; letter-spacing: 0.05em;">Recently Processed</h6>
    </div>

    <!-- History Table -->
    <div class="table-responsive opacity-75 rounded-0">
        <table class="table table-sm align-middle mb-0">
            <tbody class="border-top-0">
                <?php foreach ( array_slice($history, 0, 10) as $req ) : 
                    $status = $req['status'];
                    $user = get_userdata($req['processed_by']);
                    $module = $req['module'] ?: ($req['entity_type'] ?? 'unknown');
                    $payload = is_array($req['payload'] ?? null) ? $req['payload'] : json_decode($req['payload'], true);
                ?>
                    <tr class="bg-white border-bottom border-light">
                        <td class="ps-5 py-3">
                            <span class="text-secondary small fw-bold text-uppercase" style="font-size: 10px;"><?php echo esc_html(str_replace('_', ' ', $module)); ?></span>
                        </td>
                        <td class="px-2 py-3">
                            <div class="text-dark small text-truncate" style="max-width: 250px;">
                                <?php 
                                    // Dynamic detail extraction
                                    if($module === 'finance' || $module === 'accounts') {
                                        echo "<strong>₹" . number_format($payload['amount'] ?? 0) . "</strong>";
                                        echo " <span class='text-muted mx-1'>•</span> " . esc_html($payload['method'] ?? '');
                                    } else {
                                        if(isset($payload['name'])) echo esc_html($payload['name']);
                                        elseif(isset($payload['number'])) echo esc_html($payload['number']);
                                        else echo ucfirst($req['request_type']);
                                    }
                                    
                                    if(isset($payload['flat_no'])) echo " <span class='text-muted mx-1'>•</span> " . esc_html($payload['flat_no']);
                                ?>
                            </div>
                        </td>
                        <td class="px-2 py-3">
                            <span class="badge <?php echo $status==='approved'?'bg-success':'bg-danger'; ?> bg-opacity-10 <?php echo $status==='approved'?'text-success':'text-danger'; ?> rounded-pill px-2 py-1" style="font-size: 9px;">
                                <?php echo strtoupper($status); ?>
                            </span>
                        </td>
                        <td class="px-2 py-3">
                            <div class="text-muted small" style="font-size: 10px;">
                                <?php echo $user ? $user->display_name : 'System'; ?>
                                <span class="mx-1">•</span>
                                <?php echo wp_date('d M', strtotime($req['processed_at'])); ?>
                            </div>
                        </td>
                        <td class="pe-5 py-3 text-end">
                            <?php if(!empty($req['admin_note'])): ?>
                                <i class="bi bi-info-circle text-muted" title="<?php echo esc_attr($req['admin_note']); ?>"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<!-- Centralized Request Detail Modal -->
<div class="modal fade" id="requestDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <div id="rd-icon-wrapper" class="rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i id="rd-icon" class="bi fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold m-0" id="rd-title">Request Details</h5>
                        <p class="text-secondary small m-0" id="rd-subtitle"></p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 pt-4">
                <!-- Request Summary Section (Integrated Comparison) -->
                <div id="rd-summary-section" class="mb-4">
                    <h6 class="text-uppercase small fw-bold text-muted mb-3" style="letter-spacing: 0.05em;">Information Summary</h6>
                    <div id="rd-summary-grid" class="row g-3">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top-0 bg-light px-4 py-3 gap-2">
                <button type="button" class="btn btn-light fw-semibold text-secondary px-4 rounded-3 border-0" data-bs-dismiss="modal">Close</button>
                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger fw-bold px-4 rounded-pill js-reject-inline" id="rd-reject-btn">REJECT</button>
                    <button type="button" class="btn btn-success fw-bold px-4 rounded-pill js-approve-inline" id="rd-approve-btn">APPROVE</button>
                </div>
            </div>
        </div>
    </div>
</div>
