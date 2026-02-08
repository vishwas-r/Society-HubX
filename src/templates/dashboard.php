<?php
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
        if ( ! empty( $inv['payments'] ) ) {
            // Payments might be JSON string from DB, so parse it
            $payments = $inv['payments'];
            if ( is_string( $payments ) ) {
                $payments = json_decode( $payments, true );
            }
            if ( is_array( $payments ) ) {
                foreach ( $payments as $p ) $paid += (float) $p['amount'];
            }
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
        $p_payload = json_decode($pr['payload'], true);
        if(($p_payload['invoice_id'] ?? '') === 'Total Outstanding') {
            $has_pending_total_payment = true;
            break;
        }
    }
}

// Helper for Indian Numbering Format
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
?>

<!-- Nonce for AJAX Requests -->
<script>
    var sgvx51_nonce = '<?php echo wp_create_nonce( 'sgvx51_nonce' ); ?>';
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
</script>

    <!-- Welcome Section -->
    <div class="bg-white rounded-3 shadow-sm border border-light p-4 mb-4 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">Hello, <?php echo esc_html( $r['name'] ); ?> 👋</h1>
            <p class="text-secondary d-flex align-items-center gap-2 small">
                <span class="badge bg-primary-subtle text-primary px-2 py-1 rounded fw-semibold text-uppercase tracking-wide">Flat <?php echo esc_html( $r['flat_no'] ); ?></span>
                <span class="text-muted">•</span>
                <span><?php echo esc_html( ucfirst( $r['type'] ) ); ?></span>
            </p>
        </div>
        <button data-bs-toggle="modal" data-bs-target="#editProfileModal" class="btn btn-outline-secondary border-light rounded-3 text-sm fw-medium shadow-none">
            Edit Profile
        </button>
    </div>

    <!-- Stats Overview -->
    <div class="row g-4 mb-4">
        <!-- Dues Card -->
        <div class="col-md-4">
            <?php 
            // Determine card color scheme based on state
            $card_bg = 'bg-primary'; // Default: blue for active dues
            $card_icon = 'bi-wallet2';
            $card_accent = 'rgba(255,255,255,0.1)';
            
            if($has_pending_total_payment) {
                $card_bg = 'bg-info'; // Info blue for pending
                $card_icon = 'bi-clock-history';
            } elseif($total_dues == 0) {
                $card_bg = 'bg-success'; // Green for zero balance
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
                      <?php if($has_pending_total_payment): ?>
                         <div class="d-flex align-items-center gap-2 bg-white bg-opacity-15 rounded-2 px-3 py-2">
                             <div class="spinner-grow spinner-grow-sm" role="status" style="width: 0.75rem; height: 0.75rem;">
                                 <span class="visually-hidden">Loading...</span>
                             </div>
                             <span class="small fw-medium">Under Admin Review</span>
                         </div>
                      <?php elseif($total_dues > 0): ?>
                      <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" 
                              data-amount="<?php echo esc_attr($total_dues); ?>"
                              data-invoice-id="Total Outstanding"
                              class="js-btn-pay btn w-100 py-2 border border-white border-opacity-25 rounded-3 text-sm fw-medium text-white shadow-none" style="background: rgba(255,255,255,0.1);">
                         <i class="bi bi-credit-card me-2"></i>Make Payment
                     </button>
                      <?php else: ?>
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
                        <div class="fs-2 fw-bold text-dark mt-1"><?php echo count( $data['notices'] ); ?></div>
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
                        <div class="fs-2 fw-bold text-dark mt-1"><?php echo count( $data['my_docs'] ); ?></div>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-success" style="width: 40px; height: 40px;">
                        <i class="bi bi-file-earmark-text-fill fs-4"></i>
                    </div>
                </div>
                <p class="small text-muted m-0">Securely stored files.</p>
            </div>
        </div>
    </div>

    <!-- Modern Tabs -->
    <div class="mb-4 border-bottom border-light">
        <nav class="nav-tabs-custom" aria-label="Tabs">
            <button id="btn-tab-home" data-tab-target="#tab-home" class="tab-btn active text-primary border-primary">My Home</button>
            <button id="btn-tab-community" data-tab-target="#tab-community" class="tab-btn">Community</button>
            <button id="btn-tab-notices" data-tab-target="#tab-notices" class="tab-btn">Notices</button>
            <button id="btn-tab-accounts" data-tab-target="#tab-accounts" class="tab-btn">My Accounts</button>
            <button id="btn-tab-expenses" data-tab-target="#tab-expenses" class="tab-btn">Society Finance</button>
            <button id="btn-tab-facilities" data-tab-target="#tab-facilities" class="tab-btn">Facilities</button>
            <button id="btn-tab-polls" data-tab-target="#tab-polls" class="tab-btn d-flex align-items-center gap-1">
                Polls 
                <span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-0 ms-1" style="font-size: 0.65rem;">New</span>
            </button>
        </nav>
    </div>

    <!-- Tab Contents -->
    
    <!-- 1. HOME TAB -->
    <div id="tab-home" class="tab-content d-block">
         
         <!-- Top Row: People & Vehicles -->
         <div class="row g-4 mb-4">
             
            <!-- Family Members -->
            <div class="col-md-4">
                <div id="familyContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                    <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                        <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                            <i class="bi bi-people-fill text-primary"></i> Family Members
                        </h3>
                         <button id="addFamily" data-bs-toggle="modal" data-bs-target="#familyModal" class="btn btn-sm btn-primary rounded-3 fw-medium">+ Add</button>
                    </div>
                    <div class="p-4 flex-grow-1">
                        <?php if(empty($data['family'])): ?>
                            <div class="text-center text-muted small italic py-4">No members added.</div>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                                <?php foreach($data['family'] as $fam): ?>
                                    <li class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($fam['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="fw-bold text-dark small"><?php echo esc_html($fam['name']); ?></div>
                                                    <?php if(isset($fam['status']) && $fam['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.6rem;">PENDING</span>
                                                    <?php elseif(isset($fam['status']) && $fam['status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger text-white rounded-pill" style="font-size: 0.6rem;">REJECTED</span>
                                                    <?php elseif(isset($fam['status']) && $fam['status'] === 'deletion_pending'): ?>
                                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;">DEL PENDING</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                                                    <span><?php echo esc_html($fam['relation']); ?></span>
                                                    <?php if(!empty($fam['blood_group'])): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal ms-1">
                                                            <i class="bi bi-heart-fill me-1" style="font-size: 0.6rem;"></i><?php echo esc_html($fam['blood_group']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="badge bg-light text-secondary rounded fw-normal"><?php echo esc_html($fam['age']); ?> yrs</span>
                                            <?php if(isset($fam['status']) && $fam['status'] !== 'approved'): ?>
                                            <?php else: ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light">
                                                        <li><button class="dropdown-item js-edit-family small" data-payload="<?php echo esc_attr(json_encode($fam)); ?>">Edit</button></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger small js-delete-family-frontend" 
                                                                    data-id="<?php echo esc_attr($fam['id']); ?>" 
                                                                    data-nonce="<?php echo wp_create_nonce('sgvx51_delete_family_nonce'); ?>">
                                                                Remove
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Daily Help -->
            <div class="col-md-4">
                <div id="dailyHelpContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                    <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                        <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                            <i class="bi bi-person-badge text-info"></i> Daily Help
                        </h3>
                        <button id="addDailyHelp" data-bs-toggle="modal" data-bs-target="#helpModal" class="btn btn-sm btn-primary rounded-3 fw-medium">+ Add</button>
                    </div>
                    <div class="p-4 flex-grow-1">
                        <?php if(empty($data['daily_help'])): ?>
                             <div class="text-center text-muted small italic py-4">No help details found.</div>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                                <?php foreach($data['daily_help'] as $help): ?>
                                    <li class="d-flex align-items-center gap-3">
                                        <div class="bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center text-info" style="width: 32px; height: 32px;">
                                            <i class="bi bi-person-badge"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="fw-bold text-dark small"><?php echo esc_html($help['name']); ?></div>
                                                 <?php if(isset($help['status']) && $help['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.6rem;">PENDING</span>
                                                <?php elseif(isset($help['status']) && $help['status'] === 'deletion_pending'): ?>
                                                    <span class="badge bg-danger text-white rounded-pill" style="font-size: 0.6rem;">DELETION PENDING</span>
                                                <?php elseif(isset($help['status']) && $help['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger text-white rounded-pill" style="font-size: 0.6rem;">REJECTED</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-secondary text-uppercase tracking-wide" style="font-size: 0.7rem;"><?php echo esc_html($help['role']); ?></div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if(isset($help['status']) && $help['status'] === 'approved'): ?>
                                                <a href="tel:<?php echo esc_attr($help['phone']); ?>" class="btn btn-sm btn-light rounded-circle p-1 d-flex align-items-center justify-content-center shadow-none" style="width: 32px; height: 32px; border: 1px solid #eee;">
                                                    <i class="bi bi-telephone-fill" style="font-size: 0.8rem;"></i>
                                                </a>
                                                 <div class="dropdown">
                                                    <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light">
                                                        <li><button class="dropdown-item js-edit-help small" data-payload="<?php echo esc_attr(json_encode($help)); ?>">Edit</button></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger small js-delete-help-frontend" 
                                                                    data-id="<?php echo esc_attr($help['id']); ?>" 
                                                                    data-nonce="<?php echo wp_create_nonce('sgvx51_delete_help_nonce'); ?>">
                                                                Remove
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php elseif(isset($help['status']) && $help['status'] === 'rejected'): ?>
                                                 <div class="dropdown">
                                                    <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light">
                                                        <li><button class="dropdown-item js-edit-help small" data-payload="<?php echo esc_attr(json_encode($help)); ?>">Edit / Fix</button></li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicles -->
            <div class="col-md-4">
                <div id="vehicleContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                    <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                        <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                             <i class="bi bi-car-front-fill text-primary"></i> My Vehicles
                        </h3>
                        <button id="addVehicle" data-bs-toggle="modal" data-bs-target="#vehicleModal" class="btn btn-sm btn-primary rounded-3 fw-medium">+ Add</button>
                    </div>
                    <div class="p-4 flex-grow-1">
                        <?php if(empty($data['vehicles'])): ?>
                             <div class="text-center text-muted small italic py-4">No vehicles registered.</div>
                        <?php else: ?>
                            <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                                <?php foreach($data['vehicles'] as $v): ?>
                                    <li class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 32px; height: 32px;">
                                            <?php if(strtolower($v['type']) === 'bike' || strtolower($v['type']) === '2-wheeler'): ?>
                                                <i class="bi bi-bicycle"></i>
                                            <?php else: ?>
                                                <i class="bi bi-car-front-fill"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold text-dark font-monospace tracking-wide small" style="letter-spacing: 0.05em;"><?php echo esc_html($v['number']); ?></div>
                                            <div class="small text-secondary d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                                                <?php 
                                                    $desc = $v['type'];
                                                    if(!empty($v['brand'])) $desc .= ' • ' . $v['brand'];
                                                    echo esc_html($desc); 
                                                ?>
                                                <?php if(isset($v['status']) && $v['status']==='pending'): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.6rem;">PENDING</span>
                                                <?php elseif(isset($v['status']) && $v['status']==='rejected'): ?>
                                                    <span class="badge bg-danger text-white rounded-pill ms-1" style="font-size: 0.6rem;">REJECTED</span>
                                                <?php elseif(isset($v['status']) && $v['status']==='deletion_pending'): ?>
                                                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.6rem;">DEL PENDING</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if(isset($v['status']) && $v['status'] === 'approved'): ?>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light">
                                                        <li><button class="dropdown-item js-edit-vehicle small" data-payload="<?php echo esc_attr(json_encode($v)); ?>">Edit</button></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger small js-delete-vehicle-frontend" 
                                                                    data-id="<?php echo esc_attr($v['id']); ?>" 
                                                                    data-nonce="<?php echo wp_create_nonce('sgvx51_delete_vehicle_frontend_nonce'); ?>">
                                                                Deregister
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
         </div>

         <!-- Bottom Row: Documents -->
         <div id="documentContainer" class="bg-white rounded-3 shadow-sm border border-light overflow-hidden mb-4">
            <div class="px-4 py-3 border-bottom border-light d-flex justify-content-between align-items-center bg-white">
                <div class="d-flex items-center gap-3">
                    <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 40px; height: 40px;">
                        <i class="bi bi-file-earmark-text-fill fs-4"></i>
                    </div>
                    <div>
                        <h3 class="fw-semibold text-dark m-0 fs-6">My Documents</h3>
                        <p class="text-secondary m-0" style="font-size: 0.75rem;">Manage your flat documents</p>
                    </div>
                </div>
                <button id="addDocument" data-bs-toggle="modal" data-bs-target="#uploadDocModal" class="btn btn-sm btn-dark rounded-3 fw-medium d-flex align-items-center gap-2">
                    <i class="bi bi-cloud-upload"></i>
                    Upload Document
                </button>
            </div>
             <div class="p-4">
                <?php if ( empty( $data['my_docs'] ) ) : ?>
                    <div class="text-center py-4 text-muted small">
                        <p>No documents found.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ( $data['my_docs'] as $d ) : ?>
                            <div class="col-sm-6 col-md-4 col-lg-3">
                                <div class="bg-white border border-light rounded-3 p-3 position-relative shadow-sm transition-all h-100">
                                    <?php if ( isset( $d['status'] ) && $d['status'] === 'pending' ) : ?>
                                        <span class="position-absolute top-0 end-0 m-2 badge bg-warning text-dark rounded small text-uppercase" style="font-size: 8px;">Pending</span>
                                    <?php elseif ( isset( $d['status'] ) && $d['status'] === 'rejected' ) : ?>
                                        <span class="position-absolute top-0 end-0 m-2 badge bg-danger text-white rounded small text-uppercase" style="font-size: 8px;">Rejected</span>
                                    <?php else: ?>
                                        <span class="position-absolute top-0 end-0 m-2 text-success"><i class="bi bi-check-circle-fill"></i></span>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <div class="bg-light text-muted rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                            <i class="bi bi-file-earmark-pdf fs-4"></i>
                                        </div>
                                    </div>
                                    
                                    <h4 class="fw-bold text-dark small text-truncate mb-1" title="<?php echo esc_attr( $d['name'] ); ?>"><?php echo esc_html( $d['name'] ); ?></h4>
                                    <p class="text-muted mb-3" style="font-size: 0.7rem;">Cloud Storage</p>

                                    <div class="d-flex justify-content-between align-items-center border-top border-light pt-2">
                                        <a href="<?php echo esc_url( $d['url'] ); ?>" target="_blank" class="small text-primary fw-semibold text-decoration-none">View</a>
                                        <?php if ( isset( $d['status'] ) && $d['status'] === 'pending' ) : ?>
                                             <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=sgvx51_frontend_delete_doc&doc_id='.$d['id']), 'sgvx51_delete_doc_nonce' ); ?>" 
                                               class="small text-danger fw-medium text-decoration-none"
                                               onclick="return confirm('Delete this pending document?')">Delete</a>
                                        <?php elseif(isset($d['status']) && $d['status'] === 'deletion_pending'): ?>
                                            <span class="small text-warning fw-medium">Deleting...</span>
                                        <?php else: ?>
                                             <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=sgvx51_request_delete_doc&doc_id='.$d['id']), 'sgvx51_delete_doc_nonce' ); ?>" 
                                               class="small text-secondary hover-text-danger fw-medium text-decoration-none"
                                               onclick="return confirm('Request Admin to delete this document?')">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
             </div>
        </div>
    </div>

    <!-- 2. NOTICES TAB -->
    <div id="tab-notices" class="tab-content d-none">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                 <div class="d-flex flex-column gap-3">
                    <?php if ( empty( $data['notices'] ) ) : ?>
                        <div class="text-center py-5 bg-white rounded-3 border border-light border-dashed text-muted">
                            No active notices for you.
                        </div>
                    <?php else : ?>
                        <?php foreach ( $data['notices'] as $n ) : ?>
                            <div class="bg-white rounded-3 shadow-sm border border-light p-4 position-relative ps-4 text-start">
                                 <!-- Accent Line -->
                                <div class="position-absolute start-0 top-0 bottom-0 bg-warning rounded-start" style="width: 4px;"></div>
                                
                                <div class="d-flex justify-content-between align-items-start mb-2 ps-2">
                                    <h3 class="fs-5 fw-bold text-dark m-0"><?php echo esc_html( $n['title'] ); ?></h3>
                                    <span class="badge bg-light text-muted fw-normal border border-light"><?php echo date( 'd M Y', strtotime( $n['created_at'] ) ); ?></span>
                                </div>
                                <div class="text-secondary mb-3 ps-2" style="font-size: 0.9rem;">
                                    <?php echo nl2br( esc_html( $n['content'] ) ); ?>
                                </div>
                                <?php if ( ! empty( $n['attachment_url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $n['attachment_url'] ); ?>" target="_blank" class="ms-2 d-inline-flex align-items-center gap-2 small fw-medium text-primary bg-primary-subtle px-3 py-1 rounded text-decoration-none shadow-none">
                                        <i class="bi bi-paperclip"></i> View Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- COMMUNITY TAB -->
    <div id="tab-community" class="tab-content d-none">
         <div class="d-flex flex-column gap-4">
             <!-- Search & Filters -->
            <div class="bg-white rounded-3 shadow-sm border border-light p-3">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" id="dir-search" placeholder="Search Flat, Owner, Vehicle..." class="form-control ps-5 text-sm rounded-3 border-light shadow-none">
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="d-flex gap-2 justify-content-md-end overflow-auto" id="dir-filters">
                            <button class="dir-filter-btn btn btn-sm btn-dark rounded-pill px-3 active" data-filter="all">All</button>
                            <button class="dir-filter-btn btn btn-sm btn-light text-secondary rounded-pill px-3" data-filter="vehicle">Has Vehicle</button>
                            <button class="dir-filter-btn btn btn-sm btn-light text-secondary rounded-pill px-3" data-filter="help">Has Help</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Directory Grid -->
             <?php if(empty($directory)): ?>
                <div class="text-center py-5 text-muted">
                     <p>No directory data available.</p>
                </div>
            <?php else: ?>
                <div class="row g-4" id="directory-grid">
                    <?php foreach($directory as $d): 
                        // Build a Rich Search String for this Card
                        $search_terms = [
                            $d['flat_no'],
                            $d['owner'],
                            'Block ' . $d['block'],
                            $d['block'] . '-' . $d['flat_no'], // e.g. A-101
                        ];

                        // Add all resident names
                        if (!empty($d['all_names'])) {
                            $search_terms = array_merge($search_terms, $d['all_names']);
                        }

                        // Add vehicle data
                        if (!empty($d['vehicles'])) {
                            foreach($d['vehicles'] as $v) {
                                if (!empty($v['number'])) $search_terms[] = $v['number'];
                                if (!empty($v['brand'])) $search_terms[] = $v['brand'];
                            }
                        }

                        // Add daily help names
                        if (!empty($d['help'])) {
                            foreach($d['help'] as $h) {
                                if (!empty($h['name'])) $search_terms[] = $h['name'];
                                if (!empty($h['role'])) $search_terms[] = $h['role'];
                            }
                        }

                        $search_blob = strtolower(implode(' ', array_unique(array_filter($search_terms))));
                    ?>
                        <div class="col-md-6 col-lg-4 dir-card" 
                             data-search="<?php echo esc_attr($search_blob); ?>"
                             data-json="<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>"
                             data-has-vehicle="<?php echo !empty($d['vehicles']) ? '1' : '0'; ?>"
                             data-has-help="<?php echo !empty($d['help']) ? '1' : '0'; ?>"
                             style="cursor: pointer;">
                              
                             <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden h-100 cursor-pointer transition-all card-hover">
                                <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                            <?php echo esc_html($d['flat_no']); ?>
                                        </div>
                                        <div>
                                            <h4 class="fw-semibold text-dark m-0 small"><?php echo esc_html($d['owner']); ?></h4>
                                            <span class="badge bg-light text-secondary fw-normal small border border-light">Block <?php echo esc_html($d['block']); ?></span>
                                        </div>
                                    </div>
                                    <div class="text-muted"><i class="bi bi-chevron-right"></i></div>
                                </div>
                                <div class="p-4">
                                     <div class="d-flex flex-wrap gap-2">
                                         <!-- Members -->
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal d-flex align-items-center gap-1">
                                            <i class="bi bi-people" style="font-size:12px;"></i> <?php echo esc_html($d['members']); ?>
                                        </span>
                                        <?php if(!empty($d['vehicles'])): ?>
                                             <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal d-flex align-items-center gap-1">
                                                <i class="bi bi-car-front" style="font-size:12px;"></i> <?php echo count($d['vehicles']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if(!empty($d['help'])): ?>
                                             <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal d-flex align-items-center gap-1">
                                                <i class="bi bi-person-badge" style="font-size:12px;"></i> <?php echo count($d['help']); ?>
                                            </span>
                                        <?php endif; ?>
                                     </div>
                                </div>
                             </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
         </div>
    </div>

    <!-- 3. FACILITIES TAB -->
    <div id="tab-facilities" class="tab-content d-none">
         <div class="row g-4">
             <div class="col-lg-4">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                      <h3 class="h5 fw-bold text-dark m-0">Available Facilities</h3>
                      <div class="position-relative" style="width: 150px;">
                          <input type="text" id="facility-dashboard-search" class="form-control form-control-sm rounded-pill shadow-none" placeholder="Search...">
                      </div>
                  </div>
                 <div class="d-flex flex-column gap-3">
                     <?php foreach ( $data['facilities'] as $f ) : ?>
                        <div class="facility-card bg-white rounded-3 border border-light p-3 shadow-sm d-flex justify-content-between align-items-center" data-search="<?php echo esc_attr(strtolower($f['name'])); ?>">
                            <div>
                                <div class="fw-medium text-dark"><?php echo esc_html( $f['name'] ); ?></div>
                                <div class="small text-secondary">₹<?php echo sgvx_in_fmt( $f['rate'] ?? 0 ); ?> / <?php echo esc_html( $f['rate_unit'] ?? 'hr' ); ?></div>
                            </div>
                            <button class="js-open-booking btn btn-sm btn-outline-success rounded-pill px-3 shadow-none" 
                                    data-facility-id="<?php echo esc_attr($f['id']); ?>"
                                    data-facility-name="<?php echo esc_attr($f['name']); ?>">Book</button>
                        </div>
                    <?php endforeach; ?>
                 </div>
             </div>
             <div class="col-lg-8">
                 <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
                     <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                         <span class="fw-semibold text-dark">My Bookings</span>
                         <div class="position-relative" style="width: 180px;">
                             <input type="text" id="booking-dashboard-search" class="form-control form-control-sm rounded-pill shadow-none" placeholder="Filter bookings...">
                         </div>
                     </div>
                     <div class="table-responsive">
                         <table class="table table-hover mb-0 align-middle">
                             <thead class="bg-light text-secondary text-uppercase small">
                                 <tr><th class="ps-4">Facility</th><th>Date</th><th class="pe-4">Status</th></tr>
                             </thead>
                             <tbody>
                                   <?php if ( empty( $data['my_bookings'] ) ) : ?>
  									<tr><td colspan="3" class="text-center py-4 text-muted italic">No bookings found.</td></tr>
  								<?php else : ?>
                                      <?php foreach ( $data['my_bookings'] as $b ) : 
  										$fac_name = 'Unknown';
  										foreach($data['facilities'] as $fa) { if($fa['id'] == $b['facility_id']) $fac_name = $fa['name']; }
  									?>
                                      <tr class="booking-dash-row" data-search="<?php echo esc_attr(strtolower($fac_name)); ?>">
                                          <td class="ps-4 fw-medium text-dark"><?php echo esc_html( $fac_name ); ?></td>
                                          <td class="text-secondary"><?php echo date( 'M j, H:i', strtotime( $b['start_time'] ) ); ?></td>
                                          <td class="pe-4">
                                              <?php 
                                                  $s_raw = strtolower($b['status'] ?? 'pending');
                                                  $s_class = 'bg-success-subtle text-success';
                                                  if ( $s_raw === 'pending' ) $s_class = 'bg-warning-subtle text-warning-emphasis';
                                                  if ( $s_raw === 'rejected' ) $s_class = 'bg-danger-subtle text-danger';
                                                  if ( $s_raw === 'cancelled' ) $s_class = 'bg-secondary-subtle text-secondary';
                                              ?>
                                              <span class="badge <?php echo $s_class; ?> rounded-pill text-uppercase fw-bold" style="font-size: 9px;"><?php echo esc_html( $b['status'] ); ?></span>
                                          </td>
                                      </tr>
                                      <?php endforeach; ?>
                                 <?php endif; ?>
                             </tbody>
                         </table>
                     </div>
                 </div>
             </div>
         </div>
    </div>   
    
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
                          <div class="text-white-50 small text-center">Awaiting Admin Verification</div>
                       <?php elseif($total_dues > 0): ?>
                           <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" 
                                   data-amount="<?php echo esc_attr($total_dues); ?>"
                                   class="js-btn-pay btn btn-light w-100 fw-bold text-primary shadow-sm rounded-3">Pay Now</button>
                       <?php else: ?>
                          <div class="text-white-50 small text-center">No Outstanding Dues</div>
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
                              <span class="fw-bold text-dark small"><?php echo count(array_filter($data['invoices'], function($i){return $i['status']!=='paid';})); ?></span>
                          </li>
                      </ul>
                  </div>
             </div>
        </div>

        <!-- Resident Payment History Chart (Moved Below Cards) -->
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
                          <?php foreach ( $data['invoices'] as $inv ) : 
                              $is_paid = $inv['status'] === 'paid'; 
                              $paid = 0;
                              if(!empty($inv['payments'])) {
                                  $payments = is_string($inv['payments']) ? json_decode($inv['payments'], true) : $inv['payments'];
                                  if(is_array($payments)) {
                                      foreach($payments as $p) $paid += floatval($p['amount']);
                                  }
                              }
                              $outstanding = floatval($inv['amount']) - $paid;
                              $pending_request = null;
                              if (!empty($data['pending_payment_requests'])) {
                                  foreach($data['pending_payment_requests'] as $pr) {
                                      $p_payload = json_decode($pr['payload'], true);
                                      if(($p_payload['invoice_id'] ?? '') === $inv['id']) { $pending_request = $pr; break; }
                                  }
                              }
                          ?>
                            <tr>
                                <td class="ps-4 fw-medium text-dark"><?php echo date('M Y', strtotime($inv['month'])); ?></td>
                                <td class="text-truncate text-secondary" style="max-width: 150px;"><?php echo esc_html($inv['description']); ?></td>
                                <td class="font-monospace text-dark">₹<?php echo sgvx_in_fmt($inv['amount'], 0); ?></td>
                                <td>
                                    <?php if($is_paid): ?><span class="badge bg-success-subtle text-success rounded-pill">Paid</span>
                                    <?php elseif($pending_request): ?><span class="badge bg-info-subtle text-info rounded-pill">Pending Verification</span>
                                    <?php else: ?><span class="badge bg-warning-subtle text-warning text-dark rounded-pill">Unpaid</span><?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                     <?php if($is_paid): ?>
                                        <button onclick="viewInvoiceReceipt(this)" data-invoice-id="<?php echo esc_attr($inv['id']); ?>" class="btn btn-sm text-success fw-bold p-0 shadow-none border-0">View Receipt</button>
                                     <?php elseif($pending_request): ?>
                                        <span class="text-muted small">Awaiting Admin</span>
                                     <?php elseif($outstanding > 0): ?>
                                        <button data-bs-toggle="modal" data-bs-target="#sgvx51PaymentModal" data-invoice-id="<?php echo esc_attr($inv['id']); ?>" data-amount="<?php echo esc_attr($outstanding); ?>" class="js-btn-pay btn btn-sm text-primary fw-bold p-0 shadow-none border-0">Pay</button>
                                     <?php endif; ?>
                                </td>
                            </tr>
                          <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        </div>
    </div>

        <!-- 5. SOCIETY FINANCE (EXPENSES) TAB -->
    <div id="tab-expenses" class="tab-content d-none">
          <!-- Financial Overview Row: Funds Card (5) + Chart (7) -->
          <div class="row g-4 mb-4 align-items-stretch">
              <!-- Society Funds Card -->
              <div class="col-md-5">
                  <div class="card border-0 shadow-sm text-white rounded-3 h-100" style="background: #1e293b; min-height: 250px;">
                      <div class="card-body p-4 d-flex flex-column justify-content-between">
                           <div>
                               <p class="text-secondary small fw-bold text-uppercase mb-1" style="color: #94a3b8 !important;">Total Society Funds</p>
                               <h2 class="display-6 fw-bold mb-0">₹<?php echo sgvx_in_fmt($data['current_balance']['total'] ?? 0); ?></h2>
                           </div>
                           
                           <div class="pt-3 border-top border-secondary">
                                <div class="row">
                                     <div class="col-6">
                                         <div class="small text-secondary fw-bold text-uppercase" style="color: #64748b !important; font-size: 10px;">Bank</div>
                                         <div class="fw-bold text-primary">₹<?php echo sgvx_in_fmt($data['current_balance']['bank'] ?? 0); ?></div>
                                     </div>
                                     <div class="col-6 border-start border-secondary">
                                         <div class="small text-secondary fw-bold text-uppercase" style="color: #64748b !important; font-size: 10px;">Cash</div>
                                         <div class="fw-bold text-warning">₹<?php echo sgvx_in_fmt($data['current_balance']['cash'] ?? 0); ?></div>
                                     </div>
                                </div>
                           </div>
                      </div>
                  </div>
              </div>

              <!-- Society Expense Trend Visualization -->
              <div class="col-md-7">
                  <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100" style="min-height: 250px;">
                      <h6 class="fw-bold text-dark mb-3 small text-uppercase">Monthly Expense Trend</h6>
                      <div id="expensesChart" style="height: 200px; width: 100%;"></div>
                  </div>
              </div>
          </div>

          <!-- Tabs for Expenses Sub-views -->
          <ul class="nav nav-tabs mb-3 border-light">
              <li class="nav-item">
                  <button class="nav-link active fw-bold text-primary border-0 bg-transparent" data-subtab-target="fin-maintenance">Maintenance Status</button>
              </li>
              <li class="nav-item">
                  <button class="nav-link fw-bold text-secondary border-0 bg-transparent" data-subtab-target="fin-expenses">Expenses List</button>
              </li>
          </ul>
          
          <!-- Sub-tab 1: Maintenance Status -->
          <div id="sub-tab-fin-maintenance" class="sub-tab-content d-block">
              <div class="bg-white rounded-3 shadow-sm border border-light p-4">
                  <h5 class="fw-bold mb-3 small text-uppercase text-secondary">Flat Payment Status</h5>
                  <div class="row g-2">
                       <?php foreach($data['monthly_summary'] as $s):  
                           $bg = $s['status'] === 'paid' ? 'bg-success' : ($s['status'] === 'partial' ? 'bg-warning' : 'bg-light');
                           $txt = $s['status'] === 'unpaid' ? 'text-secondary' : 'text-white';
                       ?>
                        <div class="col-auto">
                            <div class="<?php echo $bg . ' ' . $txt; ?> p-2 rounded text-center position-relative" style="min-width: 60px;">
                                <div class="fw-bold small" style="font-size: 0.7rem;"><?php echo esc_html($s['flat_no']); ?></div>
                            </div>
                        </div>
                       <?php endforeach; ?>
                  </div>
              </div>
          </div>

          <!-- Sub-tab 2: Expenses List -->
          <div id="sub-tab-fin-expenses" class="sub-tab-content d-none">
              <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
                  <div class="table-responsive">
                      <table class="table table-hover align-middle mb-0 text-sm">
                          <thead class="bg-light text-secondary text-uppercase small">
                              <tr>
                                  <th class="ps-4 py-3">Date</th>
                                  <th class="py-3">Description</th>
                                  <th class="py-3">Category</th>
                                  <th class="text-end pe-4 py-3">Amount</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php if (empty($data['detailed_expenses'])): ?>
                                  <tr><td colspan="4" class="text-center py-5 text-muted italic">No expenses recorded for this period.</td></tr>
                              <?php else: ?>
                                  <?php foreach ($data['detailed_expenses'] as $ex): ?>
                                      <tr>
                                          <td class="ps-4 text-secondary small"><?php echo date('d M, Y', strtotime($ex['date'])); ?></td>
                                          <td>
                                              <div class="fw-bold text-dark"><?php echo esc_html($ex['description']); ?></div>
                                              <div class="small text-muted" style="font-size: 10px;"><?php echo esc_html($ex['payee'] ?? 'General Vendor'); ?></div>
                                          </td>
                                          <td><span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size: 9px;"><?php echo esc_html($ex['category']); ?></span></td>
                                          <td class="text-end pe-4 fw-bold">₹<?php echo sgvx_in_fmt($ex['amount']); ?></td>
                                      </tr>
                                  <?php endforeach; ?>
                              <?php endif; ?>
                          </tbody>
                      </table>
                  </div>
              </div>
          </div>
    </div>
    
    <!-- 6. POLLS TAB -->
    <div id="tab-polls" class="tab-content d-none">
        <?php 
            $poll_mgr = new SGVX51_Poll_Manager();
            $all_polls = (new SGVX51_DB_Router())->get('polls');
        ?>
        <?php if (empty($all_polls)): ?>
            <div class="text-center py-5 text-muted border border-dashed border-light rounded-3">No active polls.</div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($all_polls as $p): 
                     $voted = $poll_mgr->has_voted($p['id'], $r['flat_no']);
                ?>
                    <div class="col-md-6">
                        <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100">
                            <div class="d-flex justify-content-between mb-2">
                                <h5 class="fw-bold text-dark m-0"><?php echo esc_html($p['title']); ?></h5>
                                <?php if($voted): ?><span class="badge bg-success-subtle text-success">Voted</span><?php endif; ?>
                            </div>
                            <p class="small text-secondary mb-3"><?php echo esc_html($p['description']); ?></p>
                            <!-- Vote Form simplified -->
                            <?php if(!$voted): ?>
                                <form action="<?php echo admin_url('admin-post.php'); ?>" method="POST">
                                    <input type="hidden" name="action" value="sgvx51_cast_vote">
                                    <input type="hidden" name="poll_id" value="<?php echo esc_attr($p['id']); ?>">
                                    <?php wp_nonce_field('sgvx51_vote_nonce'); ?>
                                    <div class="d-flex flex-column gap-2 mb-3">
                                        <?php foreach($p['options'] as $opt): $opt_id = uniqid('opt_'); ?>
                                            <div class="form-check p-0 border rounded border-light hover-bg-light position-relative">
                                                <div class="d-flex align-items-center p-2">
                                                    <input class="form-check-input ms-2 me-3" type="radio" name="vote_option" id="<?php echo $opt_id; ?>" value="<?php echo esc_attr($opt); ?>" required>
                                                    <label class="form-check-label w-100 stretched-link small fw-medium cursor-pointer" for="<?php echo $opt_id; ?>">
                                                        <?php echo esc_html($opt); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="btn btn-primary w-100 btn-sm rounded-3">Vote</button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-light border border-light small text-center m-0">Thanks for voting!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<!-- Modals are moved to footer or separate files usually, but kept inline for now with Bootstrap Modal structure -->
<!-- Replace simplified visible/hidden logic with Bootstrap Modals -->

<!-- Edit Family Modal -->
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Add Family Member</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_add_family">
         <?php wp_nonce_field('sgvx51_add_family_nonce', '_wpnonce_add_family', true, true); ?>
         <?php wp_nonce_field('sgvx51_edit_family_nonce', '_wpnonce_edit_family', true, true); ?>
         <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sgvx51_add_family_nonce')); ?>">
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Name <span class="text-danger">*</span></label>
             <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Relation <span class="text-danger">*</span></label>
                 <select name="relation" class="form-select rounded-3 border-light shadow-none" required>
                     <option>Spouse</option>
                     <option>Child</option>
                     <option>Parent</option>
                     <option>Sibling</option>
                     <option>Other</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Age <span class="text-danger">*</span></label>
                 <input type="number" name="age" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Blood Group</label>
                 <select name="blood_group" class="form-select rounded-3 border-light shadow-none">
                     <option value="">Select</option>
                     <option>A+</option>
                     <option>A-</option>
                     <option>B+</option>
                     <option>B-</option>
                     <option>AB+</option>
                     <option>AB-</option>
                     <option>O+</option>
                     <option>O-</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone</label>
                 <input type="tel" name="phone" class="form-control rounded-3 border-light shadow-none" placeholder="Optional">
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Additional modals (Help, Vehicle, Payment) should follow the same Bootstrap Modal structure. 
     For brevity, I've demonstrated the pattern. The JS triggers data-modal-target need to be updated to data-bs-toggle="modal" data-bs-target="#id" 
     OR we keep the custom JS and mapping. Since we want "exact UI", the Custom JS modals used in the original might be better to keep to avoid Bootstrap Modal visual override unless we style BS modals exactly.
     
     Actually, the requirement "retain look and feel" implies keeping the custom modal styling provided in sgvx-frontend.css (mimicked Tailwind) is safer?
     BUT I already replaced the grid/layout which affects modals content.
     I will keep the CUSTOM MODAL MARKUP from the original file for the remaining modals to ensure they look identical, but update their internal GRID classes to BS.
-->

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Daily Help Details</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <input type="hidden" name="action" value="sgvx51_add_daily_help">
         <input type="hidden" name="help_id" value="">
         <input type="hidden" name="document_url" value="">
          <?php wp_nonce_field('sgvx51_add_help_nonce', '_wpnonce_add_help'); ?>
          <?php wp_nonce_field('sgvx51_edit_help_nonce', '_wpnonce_edit_help'); ?>
          <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sgvx51_add_help_nonce')); ?>">
         
         <div class="row g-3 mb-3">
             <div class="col-md-7">
                 <label class="form-label small fw-bold text-secondary text-uppercase">FullName <span class="text-danger">*</span></label>
                 <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
             </div>
             <div class="col-md-5">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Staff Type <span class="text-danger">*</span></label>
                 <select name="category" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Support Staff">Support Staff</option>
                     <option value="Management">Management</option>
                 </select>
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Role <span class="text-danger">*</span></label>
                 <select name="role" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Maid">Maid</option><option value="Cook">Cook</option>
                     <option value="Driver">Driver</option><option value="Nanny">Nanny</option>
                     <option value="Guard">Security Guard</option><option value="Cleaner">Cleaner</option>
                     <option value="Gardener">Gardener</option><option value="Manager">Manager</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone <span class="text-danger">*</span></label>
                 <input type="text" name="phone" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Gender <span class="text-danger">*</span></label>
                 <select name="sex" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Male">Male</option>
                     <option value="Female">Female</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Visiting Hours</label>
                 <input type="text" name="visiting_hours" class="form-control rounded-3 border-light shadow-none" placeholder="e.g. 7 AM - 10 AM">
             </div>
         </div>

         <div class="mb-0">
             <label class="form-label small fw-bold text-secondary text-uppercase">ID Proof (Photo/Document)</label>
             <input type="file" name="doc_file" accept="image/*" capture="environment" class="form-control shadow-none rounded-3 border-light">
             <div id="current-help-doc-preview" class="mt-2 d-none">
                 <a href="#" target="_blank" class="small text-primary fw-bold"><i class="bi bi-file-earmark-check me-1"></i>View Current Proof</a>
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Save Help</button>
      </div>
    </form>
  </div>
</div>

<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Register Vehicle</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_add_vehicle_frontend">
         <input type="hidden" name="vehicle_id" value="">
         <?php wp_nonce_field('sgvx51_add_vehicle_frontend_nonce'); ?>
         <?php wp_nonce_field('sgvx51_edit_vehicle_action', 'sgvx51_edit_vehicle_token', true, true); ?>
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Vehicle Number <span class="text-danger">*</span></label>
             <input type="text" name="number" class="form-control rounded-3 border-light shadow-none font-monospace" placeholder="KA52AB1234" required>
         </div>
          <div class="row g-3 mb-3">
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Type</label>
                 <select name="type" class="form-select rounded-3 border-light shadow-none">
                     <option>Bike</option>
                     <option>Car</option>
                     <option>Others</option>
                 </select>
             </div>
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Brand</label>
                 <input type="text" name="brand" class="form-control rounded-3 border-light shadow-none" placeholder="Ducati">
             </div>
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Model</label>
                 <input type="text" name="model" class="form-control rounded-3 border-light shadow-none" placeholder="Monster 821">
             </div>
          </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>


<!-- Edit Family Modal -->
<div class="modal fade" id="editFamilyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Family Member</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_edit_family_frontend">
         <input type="hidden" name="member_id" value="">
         <?php wp_nonce_field('sgvx51_edit_family_nonce'); ?>
         
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Name <span class="text-danger">*</span></label>
             <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Relation <span class="text-danger">*</span></label>
                 <select name="relation" class="form-select rounded-3 border-light shadow-none" required>
                     <option>Spouse</option>
                     <option>Child</option>
                     <option>Parent</option>
                     <option>Sibling</option>
                     <option>Other</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Age <span class="text-danger">*</span></label>
                 <input type="number" name="age" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Blood Group</label>
                 <select name="blood_group" class="form-select rounded-3 border-light shadow-none">
                     <option value="">Select</option>
                     <option>A+</option>
                     <option>A-</option>
                     <option>B+</option>
                     <option>B-</option>
                     <option>AB+</option>
                     <option>AB-</option>
                     <option>O+</option>
                     <option>O-</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone</label>
                 <input type="tel" name="phone" class="form-control rounded-3 border-light shadow-none" placeholder="Optional">
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Help Modal -->
<div class="modal fade" id="editHelpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Daily Help</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <input type="hidden" name="action" value="sgvx51_edit_help_frontend">
         <input type="hidden" name="help_id" value="">
         <?php wp_nonce_field('sgvx51_edit_help_nonce'); ?>
         
         <div class="row g-3 mb-3">
             <div class="col-md-7">
                 <label class="form-label small fw-bold text-secondary text-uppercase">FullName <span class="text-danger">*</span></label>
                 <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
             </div>
             <div class="col-md-5">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Staff Type <span class="text-danger">*</span></label>
                 <select name="category" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Support Staff">Support Staff</option>
                     <option value="Management">Management</option>
                 </select>
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Role <span class="text-danger">*</span></label>
                 <select name="role" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Maid">Maid</option><option value="Cook">Cook</option>
                     <option value="Driver">Driver</option><option value="Nanny">Nanny</option>
                     <option value="Guard">Security Guard</option><option value="Cleaner">Cleaner</option>
                     <option value="Gardener">Gardener</option><option value="Manager">Manager</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone <span class="text-danger">*</span></label>
                 <input type="text" name="phone" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Gender <span class="text-danger">*</span></label>
                 <select name="sex" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Male">Male</option>
                     <option value="Female">Female</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Visiting Hours</label>
                 <input type="text" name="visiting_hours" class="form-control rounded-3 border-light shadow-none" placeholder="e.g. 7 AM - 10 AM">
             </div>
         </div>

         <div class="mb-0">
             <label class="form-label small fw-bold text-secondary text-uppercase">ID Proof (Optional Update)</label>
             <input type="file" name="doc_file" accept="image/*" class="form-control shadow-none rounded-3 border-light">
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Upload Doc Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Upload Document</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_frontend_upload_doc">
         <?php wp_nonce_field('sgvx51_upload_doc_nonce'); ?>
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Document Name <span class="text-danger">*</span></label>
             <input type="text" name="doc_name" class="form-control rounded-3 border-light shadow-none" placeholder="Maintenance Bill/Possession Letter" required>
         </div>
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">File (PDF/Image) <span class="text-danger">*</span></label>
             <input type="file" name="doc_file" class="form-control rounded-3 border-light shadow-none" required>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- Quick Pay Modal -->
<?php
// Retrieve bank details from settings
$bank_name = get_option('sgvx51_bank_name', 'Society Bank');
$acct_no   = get_option('sgvx51_bank_account', 'Not Set');
$ifsc      = get_option('sgvx51_bank_ifsc', 'Not Set');
$upi       = get_option('sgvx51_bank_upi', 'Not Set');
$qr_url    = get_option('sgvx51_bank_qr');
?>
<div class="modal fade" id="sgvx51PaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title fw-bold">Maintenance Payment</h5>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <div class="text-center mb-4">
             <i class="bi bi-qr-code fs-1 text-primary"></i>
             <p class="text-secondary mt-2 mb-0">Scan to pay via any UPI App</p>
         </div>
         
         <!-- QR Code Display -->
         <div class="bg-light p-4 rounded-3 mb-4 text-center">
              <?php if($qr_url): ?>
                  <img src="<?php echo esc_url($qr_url); ?>" class="img-fluid rounded" style="max-width: 200px; max-height: 200px; object-fit: contain;" alt="Payment QR Code">
              <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center bg-white border border-2 border-dashed rounded" style="width: 200px; height: 200px; margin: 0 auto;">
                      <div class="text-center text-muted">
                          <i class="bi bi-image fs-1 d-block mb-2"></i>
                          <small>No QR Code uploaded</small>
                      </div>
                  </div>
              <?php endif; ?>
              
              <?php if($upi && $upi !== 'Not Set'): ?>
                  <div class="mt-3 p-2 bg-primary bg-opacity-10 rounded">
                      <small class="text-primary fw-bold">UPI ID: <?php echo esc_html($upi); ?></small>
                  </div>
              <?php endif; ?>
         </div>

         <!-- Bank Details -->
         <div class="border border-light rounded-3 overflow-hidden mb-3">
             <div class="bg-light px-3 py-2 border-bottom border-light">
                 <small class="fw-bold text-uppercase text-secondary" style="font-size: 10px; letter-spacing: 0.05em;">Bank Details</small>
             </div>
             <div class="p-3">
                 <div class="row g-2 small">
                     <div class="col-12">
                         <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                             <span class="text-secondary">Bank Name</span>
                             <span class="fw-bold text-dark"><?php echo esc_html($bank_name); ?></span>
                         </div>
                     </div>
                     <div class="col-12">
                         <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                             <span class="text-secondary">Account Number</span>
                             <span class="fw-bold text-dark font-monospace"><?php echo esc_html($acct_no); ?></span>
                         </div>
                     </div>
                     <div class="col-12">
                         <div class="d-flex justify-content-between align-items-center py-2">
                             <span class="text-secondary">IFSC Code</span>
                             <span class="fw-bold text-dark font-monospace"><?php echo esc_html($ifsc); ?></span>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <div class="alert alert-info border-0 rounded-3 small mb-4">
             <i class="bi bi-info-circle me-2"></i>Please use the details above to make your payment, then fill the form below to notify the society.
         </div>

         <form id="payment-confirmation-form" class="text-start">
            <h6 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-success"></i>
                Payment Confirmation
            </h6>
            <input type="hidden" name="invoice_id" id="confirm-invoice-id">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Amount Paid (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" id="confirm-amount" class="form-control shadow-none rounded-3" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Payment Date <span class="text-danger">*</span></label>
                    <input type="date" name="date" id="confirm-date" class="form-control shadow-none rounded-3" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Method</label>
                    <select name="method" class="form-select shadow-none rounded-3">
                        <option value="UPI">UPI / GPay / PhonePe</option>
                        <option value="Bank Transfer">Bank Transfer (NEFT/IMPS)</option>
                        <option value="Cash">Cash Deposit</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-secondary">Ref / Txn ID <span class="text-danger">*</span></label>
                    <input type="text" name="reference" class="form-control shadow-none rounded-3" placeholder="UTR Number" required>
                </div>
            </div>
         </form>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-light rounded-3 px-4 fw-bold" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary rounded-3 px-4 fw-bold shadow-sm" id="btn-confirm-payment">Submit Confirmation</button>
      </div>
    </div>
  </div>
</div>

<!-- Facility Booking Modal -->
<div class="modal fade" id="residentBookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Book Facility</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <input type="hidden" name="action" value="sgvx51_book_facility">
         <input type="hidden" name="resident_id" value="<?php echo esc_attr($r['flat_no']); ?>">
         <?php wp_nonce_field('sgvx51_facility_nonce'); ?>
         
         <div class="mb-4">
             <label class="form-label small fw-bold text-secondary text-uppercase">Facility <span class="text-danger">*</span></label>
             <select name="facility_id" id="booking-facility-select" class="form-select rounded-3 border-light shadow-none" required>
                 <?php foreach ( $data['facilities'] as $f ) : ?>
                    <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['name']); ?></option>
                 <?php endforeach; ?>
             </select>
         </div>

         <div class="row g-3 mb-4">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Start Time <span class="text-danger">*</span></label>
                 <input type="datetime-local" name="start_time" class="form-control rounded-3 border-light shadow-none" required>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">End Time <span class="text-danger">*</span></label>
                 <input type="datetime-local" name="end_time" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>

         <div class="alert alert-warning border-0 rounded-3 small m-0">
             <i class="bi bi-info-circle me-2"></i>Please ensure your booking time doesn't conflict with existing ones. Charges may apply as per society rules.
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- Community Detail Modal -->
<div class="modal fade" id="communityDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
            <span id="cdm-flat" class="badge bg-primary rounded-pill"></span>
            <span id="cdm-owner"></span>
        </h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <div class="row g-3">
             <div class="col-6">
                 <label class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Family Size</label>
                 <div class="fw-bold d-flex align-items-center gap-2">
                     <i class="bi bi-people text-primary"></i> <span id="cdm-members"></span> Members
                 </div>
             </div>
             <div class="col-12 mt-4">
                 <label class="small text-muted text-uppercase fw-bold mb-2 d-block" style="font-size: 10px;">Registered Vehicles</label>
                 <div id="cdm-vehicles" class="d-flex flex-column gap-2">
                     <!-- Populated by JS -->
                 </div>
             </div>
             <div class="col-12 mt-4">
                 <label class="small text-muted text-uppercase fw-bold mb-2 d-block" style="font-size: 10px;">Daily Help / Staff</label>
                 <div id="cdm-help" class="d-flex flex-column gap-2">
                     <!-- Populated by JS -->
                 </div>
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-receipt modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0 d-flex justify-content-between align-items-start">
        <h5 class="modal-title fw-bold text-dark">Receipt</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-body-receipt p-4">
        <div id="receipt-content" class="receipt">
          <!-- Receipt will be populated by JavaScript -->
        </div>
      </div>
      <div class="modal-footer border-0 pt-3">
        <button type="button" class="btn btn-light rounded-3 shadow-none" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary rounded-3" onclick="downloadReceipt()">
          <i class="bi bi-download me-2"></i>Download Receipt
        </button>
      </div>
    </div>
  </div>
</div>


<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Profile</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editProfileForm">
          <div class="row g-3">
            <!-- Name -->
            <div class="col-md-6">
              <label for="profileName" class="form-label text-dark fw-medium">Full Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control rounded-2 border-light shadow-sm" id="profileName" placeholder="Your name" value="<?php echo esc_attr( $r['name'] ?? '' ); ?>">
            </div>

            <!-- Email -->
            <div class="col-md-6">
              <label for="profileEmail" class="form-label text-dark fw-medium">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control rounded-2 border-light shadow-sm" id="profileEmail" placeholder="your@email.com" value="<?php echo esc_attr( $r['email'] ?? '' ); ?>">
            </div>

            <!-- Phone -->
            <div class="col-md-6">
              <label for="profilePhone" class="form-label text-dark fw-medium">Phone</label>
              <input type="tel" class="form-control rounded-2 border-light shadow-sm" id="profilePhone" placeholder="+91 98765 43210" value="<?php echo esc_attr( $r['phone'] ?? '' ); ?>">
            </div>

            <!-- Flat No (Read-only) -->
            <div class="col-md-6">
              <label for="profileFlat" class="form-label text-dark fw-medium">Flat No.</label>
              <input type="text" class="form-control rounded-2 border-light shadow-sm" id="profileFlat" value="<?php echo esc_attr( $r['flat_no'] ?? '' ); ?>" disabled>
              <small class="text-muted">Cannot be changed</small>
            </div>

            <!-- Resident Type -->
            <div class="col-md-6">
              <label for="profileType" class="form-label text-dark fw-medium">Type</label>
              <select class="form-select rounded-2 border-light shadow-sm" id="profileType" disabled>
                <option selected><?php echo esc_html( ucfirst( $r['type'] ?? 'owner' ) ); ?></option>
              </select>
              <small class="text-muted">Cannot be changed</small>
            </div>

            <!-- Blood Group -->
            <div class="col-md-6">
              <label for="profileBlood" class="form-label text-dark fw-medium">Blood Group</label>
              <select class="form-select rounded-2 border-light shadow-sm" id="profileBlood">
                <option value="">-- Select --</option>
                <option value="O+" <?php echo ($r['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                <option value="O-" <?php echo ($r['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                <option value="A+" <?php echo ($r['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                <option value="A-" <?php echo ($r['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                <option value="B+" <?php echo ($r['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                <option value="B-" <?php echo ($r['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                <option value="AB+" <?php echo ($r['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                <option value="AB-" <?php echo ($r['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
              </select>
            </div>

            <!-- Age -->
            <div class="col-md-6">
              <label for="profileAge" class="form-label text-dark fw-medium">Age</label>
              <input type="number" class="form-control rounded-2 border-light shadow-sm" id="profileAge" placeholder="Your age" min="0" max="120" value="<?php echo esc_attr( $r['age'] ?? '' ); ?>">
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 pt-3">
        <button type="button" class="btn btn-light rounded-3 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary rounded-3" onclick="saveProfileChanges()">
          <i class="bi bi-check-circle me-2"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function saveProfileChanges() {
  const btn = event.target;
  const name = document.getElementById('profileName')?.value || '';
  const email = document.getElementById('profileEmail')?.value || '';
  const phone = document.getElementById('profilePhone')?.value || '';
  const blood = document.getElementById('profileBlood')?.value || '';
  const age = document.getElementById('profileAge')?.value || '';

  if (!name.trim()) {
    alert('❌ Please enter your name');
    return;
  }

  if (!email.trim()) {
    alert('❌ Please enter your email');
    return;
  }

  // Disable button and show loading state
  btn.disabled = true;
  const originalText = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  // Send AJAX request
  const formData = new FormData();
  formData.append('action', 'sgvx51_edit_resident');
  formData.append('resident_id', '<?php echo esc_js($r['id'] ?? ''); ?>');
  formData.append('name', name);
  formData.append('email', email);
  formData.append('phone', phone);
  formData.append('blood_group', blood);
  formData.append('age', age);
  formData.append('flat_no', '<?php echo esc_js($r['flat_no'] ?? ''); ?>');
  formData.append('type', '<?php echo esc_js($r['type'] ?? 'owner'); ?>');
  formData.append('_wpnonce', '<?php echo esc_js(wp_create_nonce('sgvx51_resident_nonce')); ?>');

  fetch(ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
  })
  .then(data => {
    console.log('Response:', data);
    if (data.success) {
      alert('✅ Profile updated successfully!');
      const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
      if (modal) modal.hide();
      // Reload page to show updated data
      setTimeout(() => location.reload(), 500);
    } else {
      alert('❌ Error: ' + (data.data?.message || 'Unknown error'));
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  })
  .catch(error => {
    console.error('Save error:', error);
    alert('❌ Error saving profile: ' + error.message);
    btn.disabled = false;
    btn.innerHTML = originalText;
  });
}

// Facility Booking Triggers
document.addEventListener('DOMContentLoaded', function() {
    const bookingButtons = document.querySelectorAll('.js-open-booking');
    const bookingModalEl = document.getElementById('residentBookingModal');
    if (bookingModalEl) {
        const bookingModal = new bootstrap.Modal(bookingModalEl);
        const facilitySelect = document.getElementById('booking-facility-select');

        bookingButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const facId = this.dataset.facilityId;
                if(facilitySelect) facilitySelect.value = facId;
                bookingModal.show();
            });
        });
    }

});
</script>

<?php // End of Resident Dashboard ?>
