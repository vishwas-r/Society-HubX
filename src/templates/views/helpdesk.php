<?php
/**
 * View: Helpdesk & Field Operations
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

defined( 'ABSPATH' ) || exit;

// Variables passed from NAMMASOCIETY51_Helpdesk_Manager::render_page:
// $tickets, $technicians, $flats, $residents, $kpis

if (!isset($tickets)) $tickets = [];
if (!isset($technicians)) $technicians = [];
if (!isset($flats)) $flats = [];
if (!isset($residents)) $residents = [];
if (!isset($kpis)) $kpis = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0, 'sla_breached' => 0];

$all_technicians = $technicians;
$all_flats = $flats;
$now_ts = strtotime(current_time('mysql'));
?>

<div class="nammasociety-helpdesk-v2">

    <!-- Page Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 mb-lg-5 px-1 px-sm-2">
        <div>
            <h1 class="h3 fw-bold text-dark m-0 d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
                <i class="bi bi-headset text-primary"></i>
                Helpdesk & Field Operations
            </h1>
            <p class="text-secondary m-0 mt-1 small">Service ticketing, technician dispatching, visual SLAs & OTP-verified closure.</p>
        </div>
        <div>
            <button class="btn btn-primary px-4 py-2.5 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" onclick="openNewTicketModal()">
                <i class="bi bi-plus-circle-fill fs-5"></i>
                <span>Raise Ticket</span>
            </button>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="text-muted small fw-semibold text-uppercase" style="font-size: 11px;">Total Tickets</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo intval($kpis['total']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-warning border-4">
                <div class="text-warning small fw-semibold text-uppercase" style="font-size: 11px;">Open / Pending</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo intval($kpis['open']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-info border-4">
                <div class="text-info small fw-semibold text-uppercase" style="font-size: 11px;">In Progress</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo intval($kpis['in_progress']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-danger border-4">
                <div class="text-danger small fw-semibold text-uppercase d-flex align-items-center gap-1" style="font-size: 11px;">
                    <i class="bi bi-exclamation-octagon-fill"></i> SLA Breached
                </div>
                <div class="fs-3 fw-bold text-danger mt-1"><?php echo intval($kpis['sla_breached']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-primary border-4">
                <div class="text-primary small fw-semibold text-uppercase" style="font-size: 11px;">Resolved (Awaiting OTP)</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo intval($kpis['resolved']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-success border-4">
                <div class="text-success small fw-semibold text-uppercase" style="font-size: 11px;">Verified Closed</div>
                <div class="fs-3 fw-bold text-dark mt-1"><?php echo intval($kpis['closed']); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Search Input -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="helpdesk-search-input" placeholder="Search ticket #, flat, resident, subject..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>

                <!-- Filters -->
                <div class="d-flex gap-2 flex-wrap">
                    <select id="filter-category" class="form-select bg-light border-0 shadow-none rounded-3 fw-semibold text-secondary" style="height: 48px; min-width: 150px;">
                        <option value="all">All Categories</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Carpentry">Carpentry</option>
                        <option value="Housekeeping">Housekeeping</option>
                        <option value="Security">Security</option>
                        <option value="Lift">Lift & Elevator</option>
                        <option value="Civil">Civil / Structural</option>
                        <option value="General">General</option>
                    </select>

                    <select id="filter-priority" class="form-select bg-light border-0 shadow-none rounded-3 fw-semibold text-secondary" style="height: 48px; min-width: 140px;">
                        <option value="all">All Priorities</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-bottom border-light px-3 px-md-5 bg-white overflow-x-auto no-scrollbar">
            <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="helpdeskTabs">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary helpdesk-tab-btn" data-tab="all" style="background:none;">All Tickets</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent helpdesk-tab-btn" data-tab="open" style="background:none;">Open / Pending</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent helpdesk-tab-btn" data-tab="in_progress" style="background:none;">In Progress</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent helpdesk-tab-btn" data-tab="resolved" style="background:none;">Resolved (Awaiting OTP)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent helpdesk-tab-btn" data-tab="closed" style="background:none;">Closed</button>
                </li>
            </ul>
        </div>

        <!-- Tickets Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom border-light">
                    <tr>
                        <th class="ps-4 ps-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Ticket Details</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Flat & Resident</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Category & Priority</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Technician</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">SLA Target</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Status</th>
                        <th class="pe-4 pe-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                    </tr>
                </thead>
                <tbody id="helpdesk-table-body">
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-5 text-center text-muted">
                                <div class="py-5">
                                    <i class="bi bi-headset fs-1 mb-3 d-block opacity-25"></i>
                                    <p class="m-0">No helpdesk tickets found.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $t): 
                            $status = strtolower($t['status'] ?? 'open');
                            $priority = strtolower($t['priority'] ?? 'medium');
                            $category = $t['category'] ?? 'General';
                            $due_ts = !empty($t['sla_due_date']) ? strtotime($t['sla_due_date']) : 0;
                            $is_breached = false;
                            $sla_label = 'No SLA';
                            $sla_badge_class = 'bg-secondary text-secondary';

                            if ($due_ts) {
                                $diff = $due_ts - $now_ts;
                                if ($diff < 0) {
                                    $is_breached = true;
                                    $diff_abs = abs($diff);
                                    $hrs = floor($diff_abs / 3600);
                                    $mins = floor(($diff_abs % 3600) / 60);
                                    $sla_label = "Breached by {$hrs}h {$mins}m";
                                    $sla_badge_class = 'bg-danger text-danger border-danger';
                                } else {
                                    $hrs = floor($diff / 3600);
                                    $mins = floor(($diff % 3600) / 60);
                                    $sla_label = "{$hrs}h {$mins}m remaining";
                                    $sla_badge_class = $hrs < 6 ? 'bg-warning text-warning border-warning' : 'bg-success text-success border-success';
                                }
                            }

                            // If already resolved or closed, do not mark as active breach
                            if (in_array($status, ['resolved', 'closed', 'rejected'], true)) {
                                $sla_label = 'Completed';
                                $sla_badge_class = 'bg-secondary text-secondary';
                                $is_breached = false;
                            }

                            $assigned_to = $t['assigned_to'] ?? '0';
                            $tech_name = 'Unassigned';
                            if (!empty($assigned_to) && $assigned_to !== '0') {
                                foreach ($all_technicians as $tech) {
                                    if ($tech['id'] === $assigned_to) {
                                        $tech_name = $tech['name'];
                                        break;
                                    }
                                }
                            }
                        ?>
                            <tr class="ticket-row border-bottom border-light"
                                data-status="<?php echo esc_attr($status); ?>"
                                data-category="<?php echo esc_attr(strtolower($category)); ?>"
                                data-priority="<?php echo esc_attr($priority); ?>"
                                data-search="<?php echo esc_attr(strtolower(($t['ticket_number']??'') . ' ' . ($t['subject']??'') . ' ' . ($t['flat_no']??'') . ' ' . ($t['resident_id']??''))); ?>">
                                
                                <td class="ps-4 ps-md-5 py-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-3 p-2 d-flex align-items-center justify-content-center bg-light text-primary" style="width: 44px; height: 44px;">
                                            <i class="bi bi-ticket-detailed fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark font-monospace" style="font-size: 13px;">
                                                <a href="#" class="text-decoration-none text-dark hover-primary" onclick="viewTicketTimeline('<?php echo esc_attr($t['id']); ?>')">
                                                    <?php echo esc_html($t['ticket_number']); ?>
                                                </a>
                                            </div>
                                            <div class="text-dark small fw-semibold text-truncate" style="max-width: 240px;" title="<?php echo esc_attr($t['subject']); ?>">
                                                <?php echo esc_html($t['subject']); ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 11px;">
                                                <?php echo esc_html(date('d M Y, h:i A', strtotime($t['created_at']))); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 px-md-4 py-4">
                                    <?php 
                                        $t_disp = ($t['flat_no'] === 'Common Area') ? 'Common Area' : NAMMASOCIETY51_Plugin::get_instance()->db->get_flat_display_name( $t['flat_no'], $t['block'] ?? '' );
                                    ?>
                                    <div class="fw-bold text-dark"><?php echo esc_html($t_disp ?: 'Common Area'); ?></div>
                                    <div class="small text-muted"><?php echo esc_html($t['resident_id'] ?: 'Resident'); ?></div>
                                </td>

                                <td class="px-3 px-md-4 py-4">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 px-2.5 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 9px; width: fit-content;">
                                            <?php echo esc_html($category); ?>
                                        </span>
                                        <?php 
                                            $p_class = 'bg-secondary text-secondary';
                                            if ($priority === 'urgent') $p_class = 'bg-danger text-danger border-danger';
                                            elseif ($priority === 'high') $p_class = 'bg-warning text-warning border-warning';
                                            elseif ($priority === 'medium') $p_class = 'bg-primary text-primary border-primary';
                                        ?>
                                        <span class="badge <?php echo $p_class; ?> bg-opacity-10 border border-opacity-25 px-2.5 py-1 rounded-pill fw-bold text-uppercase" style="font-size: 9px; width: fit-content;">
                                            <?php if ($priority === 'urgent'): ?><span class="spinner-grow spinner-grow-sm text-danger me-1" style="width: 4px; height: 4px;"></span><?php endif; ?>
                                            <?php echo esc_html($priority); ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="px-3 px-md-4 py-4">
                                    <?php if ($tech_name === 'Unassigned'): ?>
                                        <button class="btn btn-sm btn-outline-primary border-dashed rounded-3 px-2 py-1 small fw-semibold" onclick="openAssignModal('<?php echo esc_attr($t['id']); ?>', '<?php echo esc_attr($t['ticket_number']); ?>')">
                                            <i class="bi bi-person-plus me-1"></i> Assign
                                        </button>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-semibold text-dark small"><?php echo esc_html($tech_name); ?></span>
                                            <button class="btn btn-sm btn-light p-1 text-muted border-0" onclick="openAssignModal('<?php echo esc_attr($t['id']); ?>', '<?php echo esc_attr($t['ticket_number']); ?>', '<?php echo esc_attr($assigned_to); ?>')" title="Reassign">
                                                <i class="bi bi-pencil small"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-3 px-md-4 py-4">
                                    <span class="badge <?php echo $sla_badge_class; ?> bg-opacity-10 border border-opacity-25 px-3 py-1.5 rounded-pill fw-bold" style="font-size: 10px;">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo esc_html($sla_label); ?>
                                    </span>
                                </td>

                                <td class="px-3 px-md-4 py-4">
                                    <?php 
                                        $s_class = 'bg-secondary text-secondary';
                                        $s_label = strtoupper($status);
                                        if ($status === 'open') {
                                            $s_class = 'bg-warning text-warning border-warning';
                                            $s_label = 'OPEN';
                                        } elseif ($status === 'assigned') {
                                            $s_class = 'bg-primary text-primary border-primary';
                                            $s_label = 'ASSIGNED';
                                        } elseif ($status === 'in_progress') {
                                            $s_class = 'bg-info text-info border-info';
                                            $s_label = 'IN PROGRESS';
                                        } elseif ($status === 'resolved') {
                                            $s_class = 'bg-primary text-primary border-primary';
                                            $s_label = 'RESOLVED';
                                        } elseif ($status === 'closed') {
                                            $s_class = 'bg-success text-success border-success';
                                            $s_label = 'CLOSED';
                                        }
                                    ?>
                                    <span class="badge <?php echo $s_class; ?> bg-opacity-10 border border-opacity-25 px-3 py-1.5 rounded-pill fw-bold" style="font-size: 10px;">
                                        <?php echo esc_html($s_label); ?>
                                    </span>
                                </td>

                                <td class="pe-4 pe-md-5 py-4 text-end">
                                    <div class="d-flex justify-content-end gap-2 text-nowrap">
                                        <?php if ($status === 'resolved'): ?>
                                            <button type="button" class="btn btn-sm btn-success px-3 fw-bold rounded-3 shadow-sm d-flex align-items-center gap-1.5" onclick="openVerifyOtpModal('<?php echo esc_attr($t['id']); ?>', '<?php echo esc_attr($t['ticket_number']); ?>')">
                                                <i class="bi bi-shield-check fs-6"></i>
                                                <span style="font-size: 11px;">Verify OTP</span>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="btn btn-sm btn-light text-primary border shadow-sm rounded-3 p-2" onclick="viewTicketTimeline('<?php echo esc_attr($t['id']); ?>')" title="View Timeline & Replies">
                                            <i class="bi bi-chat-dots-fill fs-6"></i>
                                        </button>

                                        <div class="dropdown d-inline-block">
                                            <button class="btn btn-sm btn-light border shadow-sm rounded-3 p-2" data-bs-toggle="dropdown" aria-expanded="false" title="Actions">
                                                <i class="bi bi-three-dots-vertical fs-6"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                                <li><h6 class="dropdown-header text-uppercase fw-bold" style="font-size: 10px;">Update Status</h6></li>
                                                <li><a class="dropdown-item small" href="#" onclick="quickStatusChange('<?php echo esc_attr($t['id']); ?>', 'in_progress')"><i class="bi bi-arrow-clockwise me-2 text-info"></i>Mark In Progress</a></li>
                                                <li><a class="dropdown-item small" href="#" onclick="quickStatusChange('<?php echo esc_attr($t['id']); ?>', 'resolved')"><i class="bi bi-check2-circle me-2 text-primary"></i>Mark Resolved (Send OTP)</a></li>
                                                <li><a class="dropdown-item small" href="#" onclick="quickStatusChange('<?php echo esc_attr($t['id']); ?>', 'closed')"><i class="bi bi-lock-fill me-2 text-success"></i>Close Ticket</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item small text-danger" href="#" onclick="deleteTicket('<?php echo esc_attr($t['id']); ?>')"><i class="bi bi-trash3 me-2"></i>Delete Ticket</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Modals Section
add_action('nammasociety51_admin_modals', function() use ($all_technicians, $all_flats) {
?>

<!-- New Ticket Modal -->
<div class="modal fade" id="newTicketModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Raise Helpdesk Ticket</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="new-ticket-form" enctype="multipart/form-data">
                <input type="hidden" name="action" value="nammasociety51_helpdesk_create">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('nammasociety51_helpdesk_nonce')); ?>">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Flat / Unit <span class="text-danger">*</span></label>
                            <select name="flat_no" id="ticket-flat" class="form-select shadow-none rounded-3 border-light" required>
                                <option value="">Select Flat...</option>
                                <option value="Common Area">Common Area</option>
                                <?php foreach ($all_flats as $f): 
                                    $val = $f['id'];
                                    $f_num = !empty($f['flat_number']) ? $f['flat_number'] : $f['id'];
                                    $clean_b = trim(preg_replace('/^(block[\s_-]*)+/i', '', (string)($f['block'] ?? '')));
                                    $lbl = NAMMASOCIETY51_DB_Router::format_flat_display($clean_b, $f_num);
                                ?>
                                    <option value="<?php echo esc_attr($val); ?>" data-block="<?php echo esc_attr($clean_b); ?>" data-number="<?php echo esc_attr($f_num); ?>"><?php echo esc_html($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="block" id="ticket-block" value="">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Resident / Caller Name</label>
                            <input type="text" name="resident_name" class="form-control shadow-none rounded-3 border-light" placeholder="e.g. Ramesh K.">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Category <span class="text-danger">*</span></label>
                            <select name="category" class="form-select shadow-none rounded-3 border-light" required>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Carpentry">Carpentry</option>
                                <option value="Housekeeping">Housekeeping</option>
                                <option value="Security">Security</option>
                                <option value="Lift">Lift & Elevator</option>
                                <option value="Civil">Civil / Structural</option>
                                <option value="General">General</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Priority <span class="text-danger">*</span></label>
                            <select name="priority" class="form-select shadow-none rounded-3 border-light" required>
                                <option value="medium">Medium (24 hrs SLA)</option>
                                <option value="urgent">Urgent (4 hrs SLA)</option>
                                <option value="high">High (12 hrs SLA)</option>
                                <option value="low">Low (48 hrs SLA)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Subject / Issue Summary <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control shadow-none rounded-3 border-light" required placeholder="e.g. Water leakage under kitchen sink">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Detailed Description</label>
                        <textarea name="description" rows="3" class="form-control shadow-none rounded-3 border-light" placeholder="Provide additional details or resident instructions..."></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Assign Technician (Optional)</label>
                            <select name="assigned_to" class="form-select shadow-none rounded-3 border-light">
                                <option value="0">Unassigned</option>
                                <?php foreach ($all_technicians as $tech): ?>
                                    <option value="<?php echo esc_attr($tech['id']); ?>">
                                        <?php echo esc_html($tech['name'] . (!empty($tech['role']) ? ' (' . $tech['role'] . ')' : '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Attach Photos</label>
                            <input type="file" name="photos" class="form-control shadow-none rounded-3 border-light" accept="image/*">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Technician Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Assign Technician</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assign-technician-form">
                <input type="hidden" name="action" value="nammasociety51_helpdesk_assign">
                <input type="hidden" name="ticket_id" id="assign-ticket-id" value="">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('nammasociety51_helpdesk_nonce')); ?>">
                <div class="modal-body p-4">
                    <div class="small text-muted mb-3">Ticket: <span id="assign-ticket-num" class="fw-bold text-dark font-monospace"></span></div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Select Technician <span class="text-danger">*</span></label>
                        <select name="technician_id" id="assign-tech-select" class="form-select shadow-none rounded-3 border-light" required>
                            <option value="">Select Technician...</option>
                            <?php foreach ($all_technicians as $tech): ?>
                                <option value="<?php echo esc_attr($tech['id']); ?>">
                                    <?php echo esc_html($tech['name'] . (!empty($tech['role']) ? ' (' . $tech['role'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Status</label>
                        <select name="status" class="form-select shadow-none rounded-3 border-light">
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-3 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verify Closure OTP Modal -->
<div class="modal fade" id="verifyOtpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Verify Closure OTP</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="verify-otp-form">
                <input type="hidden" name="action" value="nammasociety51_helpdesk_verify_otp">
                <input type="hidden" name="ticket_id" id="otp-ticket-id" value="">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('nammasociety51_helpdesk_nonce')); ?>">
                <div class="modal-body p-4 text-center">
                    <p class="small text-muted mb-3">Enter the 4-digit completion code provided by the resident for ticket <span id="otp-ticket-num" class="fw-bold text-dark font-monospace"></span>:</p>
                    <div class="mb-3">
                        <input type="text" name="otp" id="closure-otp-input" class="form-control form-control-lg text-center fw-bold letter-spacing-2 font-monospace border-2 border-primary shadow-none rounded-3" maxlength="4" placeholder="••••" required style="letter-spacing: 0.5em; font-size: 1.6rem;">
                    </div>
                    <div class="alert alert-info py-2 px-3 small border-0 bg-primary bg-opacity-10 text-primary rounded-3 text-start">
                        <i class="bi bi-shield-lock me-1"></i> OTP was sent to the resident via in-app notification & WhatsApp upon resolution.
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-3 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm rounded-3">Verify & Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ticket Detail & Timeline Drawer / Modal -->
<div class="modal fade" id="ticketDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom px-4 py-3 bg-light">
                <div class="d-flex align-items-center gap-2">
                    <span id="detail-ticket-number" class="fw-bold font-monospace text-primary fs-5">TKT-XXXX</span>
                    <span id="detail-ticket-status-badge"></span>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Summary Card -->
                <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                    <h5 id="detail-ticket-subject" class="fw-bold text-dark mb-2">Subject</h5>
                    <p id="detail-ticket-desc" class="text-secondary small mb-3"></p>
                    
                    <div class="row g-2 small">
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block" style="font-size: 11px;">FLAT / UNIT</span>
                            <span id="detail-ticket-flat" class="fw-bold text-dark"></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block" style="font-size: 11px;">CATEGORY</span>
                            <span id="detail-ticket-category" class="fw-bold text-dark"></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block" style="font-size: 11px;">TECHNICIAN</span>
                            <span id="detail-ticket-tech" class="fw-bold text-dark"></span>
                        </div>
                        <div class="col-6 col-md-3">
                            <span class="text-muted d-block" style="font-size: 11px;">SLA TARGET</span>
                            <span id="detail-ticket-sla" class="fw-bold text-dark"></span>
                        </div>
                    </div>

                    <!-- OTP Banner (If generated) -->
                    <div id="detail-otp-banner" class="mt-3 p-2 px-3 bg-white rounded-2 border border-primary border-opacity-25 d-none d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold text-primary"><i class="bi bi-key-fill me-1"></i> Closure OTP:</span>
                        <span id="detail-otp-val" class="font-monospace fw-bold fs-6 text-primary tracking-widest"></span>
                    </div>

                    <!-- Photos Gallery Container -->
                    <div id="detail-photos-container" class="mt-3 d-none">
                        <span class="text-muted d-block small mb-2" style="font-size: 11px;">ATTACHED PHOTOS:</span>
                        <div id="detail-photos-list" class="d-flex gap-2 flex-wrap"></div>
                    </div>
                </div>

                <!-- Timeline Section -->
                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-1 text-primary"></i> Activity & Conversation</h6>
                <div id="detail-timeline" class="d-flex flex-column gap-3 mb-4" style="max-height: 280px; overflow-y: auto;">
                    <!-- Injected dynamically -->
                </div>

                <!-- Add Reply Form -->
                <form id="add-reply-form">
                    <input type="hidden" name="action" value="nammasociety51_helpdesk_add_reply">
                    <input type="hidden" name="ticket_id" id="reply-ticket-id" value="">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('nammasociety51_helpdesk_nonce')); ?>">
                    
                    <div class="mb-2">
                        <textarea name="message" id="reply-message" rows="2" class="form-control shadow-none rounded-3 border-light" placeholder="Type a response or note..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check">
                            <input class="form-check-input shadow-none" type="checkbox" name="is_internal_note" id="internalNoteCheck" value="1">
                            <label class="form-check-label small fw-semibold text-secondary" for="internalNoteCheck">
                                <i class="bi bi-lock-fill text-warning me-1"></i> Internal Committee Note (Hidden from resident)
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary px-4 py-1.5 fw-bold shadow-sm rounded-3">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php }); ?>
