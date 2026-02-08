<?php
/**
 * View: Dashboard (Bootstrap Migration)
 * Integrates with SGVX51_DB_Router for real statistics.
 */

$db = new SGVX51_DB_Router();

// Fetch Data
$flats     = $db->get( 'flats' );
$residents = $db->get( 'residents' );
$vehicles  = $db->get( 'vehicles' );
$notices   = $db->get( 'notices' );
$bookings  = $db->get( 'bookings' );
$expenses  = $db->get( 'expenses' ); // Use main expenses table
$assets    = $db->get( 'assets' );
$documents = $db->get( 'documents' );
$requests  = $db->get( 'requests' );
$polls     = $db->get( 'polls' );

// Calculate Stats
$total_units     = count( $flats );
$total_residents = count( $residents );
$total_vehicles  = count( $vehicles );

$total_expense = 0;
foreach($expenses as $e) { 
    if(isset($e['amount'], $e['date']) && date('Y', strtotime($e['date'])) === date('Y')) {
        $total_expense += floatval($e['amount']); 
    }
}

$pending_actions = 0;
foreach($documents as $d) { if(isset($d['status']) && $d['status'] === 'pending') $pending_actions++; }
foreach($requests as $req) { if(isset($req['status']) && $req['status'] === 'pending') $pending_actions++; }

// Recent Activity Stream
$activities = [];

// 1. Notices
foreach($notices as $n) {
    if(empty($n['created_at'])) continue;
    $activities[] = [
        'title' => 'New Notice: ' . $n['title'],
        'time'  => strtotime($n['created_at']),
        'desc'  => 'Broadcasted to all residents',
        'icon'  => 'bi-megaphone',
        'color' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10'
    ];
}

// 2. Bookings
foreach($bookings as $b) {
    if(empty($b['created_at'])) continue;
    $activities[] = [
        'title' => 'Facility Booking',
        'time'  => strtotime($b['created_at']),
        'desc'  => 'Unit ' . ($b['flat_no'] ?? 'N/A') . ' reserved ' . ($b['facility_id'] ?? 'Facility'),
        'icon'  => 'bi-calendar-check',
        'color' => 'bg-success bg-opacity-10 text-success border border-success border-opacity-10'
    ];
}

// 3. Requests (Audit Trail)
foreach($requests as $r) {
    $time = ($r['status'] !== 'pending' && !empty($r['processed_at']) && $r['processed_at'] !== '0000-00-00 00:00:00') 
            ? strtotime($r['processed_at']) 
            : strtotime($r['created_at']);
    
    if(!$time) continue;

    $action_verb = str_replace(['add','edit','delete'], ['Addition','Update','Removal'], $r['request_type']);
    $entity_name = ucfirst(str_replace('_', ' ', $r['entity_type']));
    
    if ($r['status'] === 'pending') {
        $title = "Request Recieved: $entity_name";
        $color = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10';
        $icon  = 'bi-patch-exclamation';
        $desc  = "$action_verb requested for Unit " . ($r['flat_no'] ?? 'N/A');
    } elseif ($r['status'] === 'approved') {
        $title = "Activity: $entity_name $action_verb";
        $color = 'bg-success bg-opacity-10 text-success border border-success border-opacity-10';
        $icon  = 'bi-check-circle';
        $desc  = "Admin approved changes for Unit " . ($r['flat_no'] ?? 'N/A');
    } else {
        $title = "Declined: $entity_name $action_verb";
        $color = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10';
        $icon  = 'bi-x-circle';
        $desc  = "Admin rejected request for Unit " . ($r['flat_no'] ?? 'N/A');
    }

    $activities[] = [
        'title' => $title,
        'time'  => $time,
        'desc'  => $desc,
        'icon'  => $icon,
        'color' => $color
    ];
}

// 4. Expenses
foreach($expenses as $e) {
    if(empty($e['created_at'])) continue;
    $activities[] = [
        'title' => 'Expense Recorded',
        'time'  => strtotime($e['created_at']),
        'desc'  => $e['title'] . ' (₹' . number_format($e['amount']) . ')',
        'icon'  => 'bi-cart-dash',
        'color' => 'bg-info bg-opacity-10 text-info border border-info border-opacity-10'
    ];
}

// 5. Polls
foreach($polls as $p) {
    if(empty($p['created_at'])) continue;
    $activities[] = [
        'title' => 'Democracy: New Poll',
        'time'  => strtotime($p['created_at']),
        'desc'  => $p['title'],
        'icon'  => 'bi-journal-check',
        'color' => 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10'
    ];
}

// Sort by time desc
usort($activities, function($a, $b) {
    return $b['time'] - $a['time'];
});
$activities = array_slice($activities, 0, 10);
?>

    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
        <div>
            <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Dashboard Overview</h1>
            <p class="text-secondary m-0 mt-1">Welcome back! Quick glance at your society metrics and pending tasks.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="bg-success bg-opacity-10 px-3 py-2 rounded-3 d-flex align-items-center gap-2 border border-success border-opacity-10">
                <div class="spinner-grow spinner-grow-sm text-success" role="status"></div>
                <span class="small fw-bold text-success text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">System Online</span>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center border border-primary border-opacity-10" style="width: 52px; height: 52px;">
                        <i class="bi bi-people-fill text-primary fs-4"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-secondary text-uppercase mb-1" style="font-size: 10px; letter-spacing: 0.05em;">Total Residents</div>
                        <h2 class="fw-bold text-dark m-0" style="font-size: 1.5rem; letter-spacing: -0.01em;"><?php echo esc_html($total_residents); ?></h2>
                        <div class="text-muted mt-1" style="font-size: 10px;">In <?php echo esc_html($total_units); ?> Units</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center border border-success border-opacity-10" style="width: 52px; height: 52px;">
                        <i class="bi bi-car-front-fill text-success fs-4"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-secondary text-uppercase mb-1" style="font-size: 10px; letter-spacing: 0.05em;">Vehicles</div>
                        <h2 class="fw-bold text-dark m-0" style="font-size: 1.5rem; letter-spacing: -0.01em;"><?php echo esc_html($total_vehicles); ?></h2>
                        <div class="text-muted mt-1" style="font-size: 10px;">Registered</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-lg-3">
            <a href="?page=sgvx51-requests" class="text-decoration-none h-100 d-block">
                <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white transition-all hover-translate-y">
                    <div class="d-flex align-items-center gap-3">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center border border-warning border-opacity-10" style="width: 52px; height: 52px;">
                            <i class="bi bi-patch-exclamation-fill text-warning fs-4"></i>
                        </div>
                        <div>
                            <div class="small fw-bold text-secondary text-uppercase mb-1" style="font-size: 10px; letter-spacing: 0.05em;">Pending Actions</div>
                            <h2 class="fw-bold text-dark m-0" style="font-size: 1.5rem; letter-spacing: -0.01em;"><?php echo esc_html($pending_actions); ?></h2>
                            <div class="text-muted mt-1" style="font-size: 10px;">Approvals needed</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 p-4 h-100 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="flex-shrink-0 bg-info bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center border border-info border-opacity-10" style="width: 52px; height: 52px;">
                        <i class="bi bi-wallet2 text-info fs-4"></i>
                    </div>
                    <div>
                        <div class="small fw-bold text-secondary text-uppercase mb-1" style="font-size: 10px; letter-spacing: 0.05em;">YTD Expenses</div>
                        <h2 class="fw-bold text-dark m-0" style="font-size: 1.5rem; letter-spacing: -0.01em;">₹<?php echo number_format($total_expense); ?></h2>
                        <div class="text-muted mt-1" style="font-size: 10px;">FY <?php echo date('Y'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity and Actions Row -->
    <div class="row g-5">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-white overflow-hidden">
                <div class="card-header bg-white border-bottom border-light py-4 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark m-0">Recent Activities</h5>
                </div>
                <div class="card-body p-4">
                    <?php if(empty($activities)): ?>
                         <div class="text-center text-secondary py-5">
                            <i class="bi bi-inbox fs-1 mb-2 d-block opacity-25"></i>
                            <p class="m-0 small">No recent activities found.</p>
                         </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach($activities as $act): ?>
                                <div class="d-flex gap-3 align-items-center p-3 rounded-3 transition-all hover-bg-light">
                                    <div class="flex-shrink-0 <?php echo $act['color']; ?> rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="bi <?php echo $act['icon']; ?>" style="font-size: 18px;"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold text-dark m-0 small"><?php echo esc_html($act['title']); ?></h6>
                                        <p class="text-secondary m-0 mt-1" style="font-size: 12px;"><?php echo esc_html($act['desc']); ?></p>
                                    </div>
                                    <div class="text-muted small d-none d-md-block" style="font-size: 11px;">
                                        <?php echo human_time_diff($act['time'], current_time('timestamp')); ?> ago
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-10 overflow-hidden">
                <div class="p-4 p-md-5 d-flex flex-column h-100">
                    <h5 class="fw-bold text-primary mb-4" style="letter-spacing: -0.01em;">Quick Actions</h5>
                    <div class="d-flex flex-column gap-3">
                        <a href="?page=sgvx51-notices" class="btn btn-white text-start py-3 px-4 d-flex align-items-center gap-3 rounded-3 border-0 shadow-sm transition-all hover-translate-y">
                            <i class="bi bi-megaphone text-primary fs-5"></i>
                            <span class="fw-bold text-dark small">Publish Notice</span>
                        </a>
                        <a href="?page=sgvx51-residents" class="btn btn-white text-start py-3 px-4 d-flex align-items-center gap-3 rounded-3 border-0 shadow-sm transition-all hover-translate-y">
                            <i class="bi bi-person-plus text-primary fs-5"></i>
                            <span class="fw-bold text-dark small">Add Resident</span>
                        </a>
                        <a href="?page=sgvx51-expenses" class="btn btn-white text-start py-3 px-4 d-flex align-items-center gap-3 rounded-3 border-0 shadow-sm transition-all hover-translate-y">
                            <i class="bi bi-plus-square text-primary fs-5"></i>
                            <span class="fw-bold text-dark small">Record Expense</span>
                        </a>
                        <a href="?page=sgvx51-facilities" class="btn btn-white text-start py-3 px-4 d-flex align-items-center gap-3 rounded-3 border-0 shadow-sm transition-all hover-translate-y">
                            <i class="bi bi-calendar-check text-primary fs-5"></i>
                            <span class="fw-bold text-dark small">Book Facility</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


