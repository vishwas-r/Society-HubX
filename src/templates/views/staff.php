<?php
/**
 * View: Staff & Help Management (Bootstrap Migration)
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

defined( 'ABSPATH' ) || exit;

// Messages
$toast = null;
if ( isset( $_GET['status'] ) ) {
    switch ( $_GET['status'] ) {
        case 'added': $toast = ['type' => 'success', 'msg' => 'Staff added successfully.']; break;
        case 'updated': $toast = ['type' => 'success', 'msg' => 'Staff updated successfully.']; break;
        case 'deleted': $toast = ['type' => 'success', 'msg' => 'Staff deleted successfully.']; break;
    }
}

// Data passed from SNESTX51_Staff_Manager::render_page via context
// $staff, $pending, $history, $flats are available.

if (!isset($staff)) $staff = array();
if (!isset($pending)) $pending = array();
if (!isset($history)) $history = array();
if (!isset($flats)) $flats = array();

$all_flats = $flats;
?>

<div class="snestx-staff-v2">

    <!-- Global Messages -->
    <?php if ( $toast ) : ?>
        <div class="alert alert-<?php echo $toast['type'] === 'success' ? 'success' : 'warning'; ?> alert-dismissible fade show border-0 shadow-soft rounded-xl mb-4 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi <?php echo $toast['type'] === 'success' ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning'; ?> fs-4"></i>
                <div>
                    <div class="fw-bold"><?php echo $toast['type'] === 'success' ? 'Success!' : 'Notice'; ?></div>
                    <div class="small opacity-75"><?php echo esc_html( $toast['msg'] ); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header (Outside Card) -->
    <div class="mb-4 mb-lg-5 px-1 px-sm-2">
        <h1 class="h3 fw-bold text-dark m-0 d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-shield-shaded text-primary d-sm-none"></i>
            Staff & Daily Help
        </h1>
        <p class="text-secondary m-0 mt-1 small">Directory of security, domestic help, and maintenance personnel.</p>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative mb-2 mb-md-0">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="staff-search-input" placeholder="Search staff members..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <div class="dropdown snestx-bulk-actions d-none">
                        <button class="btn btn-outline-secondary dropdown-toggle px-3 rounded-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="height: 48px;">
                            Bulk Actions (<span id="selected-count">0</span>)
                        </button>
                        <ul class="dropdown-menu shadow-sm border-0 mt-1">
                            <li><a class="dropdown-item fw-bold text-success" href="#" onclick="SNESTXBulkProcess('approve')"><i class="bi bi-check-circle me-2"></i>Approve Selected</a></li>
                            <li><a class="dropdown-item fw-bold text-danger" href="#" onclick="SNESTXBulkProcess('reject')"><i class="bi bi-x-circle me-2"></i>Reject Selected</a></li>
                        </ul>
                    </div>
                    <button class="js-toggle-staff-filters btn btn-light px-3 px-sm-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center justify-content-center gap-2" style="height: 48px;">
                        <i class="bi bi-funnel"></i>
                        <span class="d-none d-sm-inline">Filters</span>
                    </button>
                    <button id="addStaff" class="btn btn-primary px-3 px-sm-4 fw-bold shadow-sm rounded-3 d-flex align-items-center justify-content-center gap-2 flex-grow-1 flex-md-grow-0" style="height: 48px;" onclick="openStaffModal()">
                        <i class="bi bi-person-plus-fill fs-5"></i>
                        <span>Add Staff</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsible Filters Section -->
        <div class="collapse" id="staff-filter-section">
            <div class="p-4 px-md-5 bg-light border-bottom border-light">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Staff Category</label>
                        <select id="filter-staff-category" class="form-select shadow-none rounded-3 border-light">
                            <option value="all">All Categories</option>
                            <option value="Support Staff">Support Staff</option>
                            <option value="Management">Management</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Status</label>
                        <select id="filter-staff-status" class="form-select shadow-none rounded-3 border-light">
                            <option value="all">All Statuses</option>
                            <option value="approved">Active Only</option>
                            <option value="pending">Pending</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button class="js-apply-staff-filters btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Apply Filters</button>
                        <button class="js-clear-staff-filters btn btn-light px-4 fw-semibold text-secondary rounded-3 border-light">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-bottom border-light px-3 px-md-5 bg-white overflow-x-auto no-scrollbar">
            <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="staffTabs">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary tab-btn" data-tab="all" style="background:none;">All Staff</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="pending" style="background:none;">Pending Requests</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="approved" style="background:none;">Active Only</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="archived" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>
        <div id="staffContainer" class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom border-light">
                    <tr>
                        <th class="ps-5 py-4 border-0" style="width: 40px;">
                            <input type="checkbox" id="bulk-select-all" class="form-check-input shadow-none">
                        </th>
                        <th class="ps-3 ps-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Staff Details</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Type</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Role</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Status</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Contact</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Flats Served</th>
                        <th class="pe-3 pe-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                    </tr>
                </thead>
                <tbody id="staff-table-body">
                    <?php 
                    // 1. Index active/archived staff from daily_help table
                    $rows_by_entity = array();
                    if ( ! empty( $staff ) ) {
                        foreach ( $staff as $s ) {
                            $s_id = $s['id'] ?? '';
                            if (!$s_id) continue;
                            
                            $s_status = $s['status'] ?? 'approved';
                            $s['is_request'] = in_array($s_status, ['pending', 'rejected']);
                            $s['request_id'] = ''; // No active request ID yet
                            $rows_by_entity[$s_id] = $s;
                        }
                    }

                    // 2. Merge Pending Requests (from requests table) to deduplicate
                    if ( ! empty( $pending ) ) {
                        foreach ( $pending as $p ) {
                            $payload = is_array($p['payload'] ?? null) ? $p['payload'] : json_decode($p['payload'], true);
                            if ( ! is_array($payload) ) $payload = [];
                            $entity_id = $p['entity_id'] ?? '';
                            $request_id = $p['id'];
                            
                            if ( $entity_id && isset($rows_by_entity[$entity_id]) ) {
                                // OVERLAY: This is an edit/delete for an existing record
                                if ($p['request_type'] === 'delete') {
                                    $rows_by_entity[$entity_id]['status'] = 'deletion_pending';
                                } else {
                                    // Merge payload fields
                                    $rows_by_entity[$entity_id] = array_merge($rows_by_entity[$entity_id], $payload);
                                    $rows_by_entity[$entity_id]['status'] = 'pending'; // Mark as pending update
                                }
                                $rows_by_entity[$entity_id]['is_request'] = true;
                                $rows_by_entity[$entity_id]['request_id'] = $request_id;
                            } else {
                                // NEW: This is likely a new staff member addition
                                $payload['id'] = $entity_id ? $entity_id : $request_id; // Priority to entity_id if exists
                                $payload['status'] = 'pending';
                                $payload['is_request'] = true;
                                $payload['request_id'] = $request_id;
                                
                                $rows_by_entity[$request_id] = $payload;
                            }
                        }
                    }

                    $all_rows = array_values($rows_by_entity);

                    if ( empty( $all_rows ) ) : ?>
                         <tr>
                            <td colspan="8" class="px-5 py-5 text-center text-muted">
                                <div class="py-5">
                                    <i class="bi bi-person-badge fs-1 mb-3 d-block opacity-25"></i>
                                    <p class="m-0">No staff members found in the records.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $all_rows as $s ) : 
                            $status = strtolower($s['status'] ?? 'approved');
                            $is_request = $s['is_request'] ?? false;
                            $category = $s['category'] ?? 'Support Staff';
                        ?>
                            <tr class="staff-row border-bottom border-light" 
                                data-status="<?php echo esc_attr($status); ?>" 
                                data-category="<?php echo esc_attr($category); ?>"
                                data-search="<?php echo esc_attr(strtolower(($s['name']??'') . ' ' . ($s['role']??'') . ' ' . ($s['phone']??''))); ?>">
                                <td class="ps-5 py-4">
                                    <input type="checkbox" value="<?php echo esc_attr(!empty($s['request_id']) ? $s['request_id'] : $s['id']); ?>" class="form-check-input snestx-bulk-checkbox shadow-none">
                                </td>
                                <td class="ps-3 ps-md-5 py-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if(!empty($s['profile_photo'])): ?>
                                            <div class="flex-shrink-0 rounded-3 overflow-hidden shadow-sm" style="width: 44px; height: 44px;">
                                                <img src="<?php echo esc_url($s['profile_photo']); ?>" class="w-100 h-100 object-fit-cover" alt="<?php echo esc_attr($s['name']); ?>">
                                            </div>
                                        <?php else: ?>
                                            <div class="flex-shrink-0 <?php echo $status === 'pending' ? 'bg-warning' : ($status === 'rejected' ? 'bg-danger' : ($status === 'archived' ? 'bg-secondary' : 'bg-primary')); ?> bg-opacity-10 <?php echo $status === 'pending' ? 'text-warning' : ($status === 'rejected' ? 'text-danger' : ($status === 'archived' ? 'text-secondary' : 'text-primary')); ?> rounded-3 d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                                <?php echo substr($s['name'] ?? 'U', 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <div class="fw-bold text-dark">
                                                <?php echo esc_html( $s['name'] ); ?>
                                                <?php if(!empty($s['id_proof'])): ?>
                                                    <a href="<?php echo esc_url($s['id_proof']); ?>" target="_blank" class="text-primary ms-1" title="View Verification Document">
                                                        <i class="bi bi-file-earmark-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-primary font-monospace" style="font-size: 11px;"><?php echo esc_html($s['sex'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 px-md-4 py-4">
                                    <span class="badge <?php echo ($s['category'] ?? '') === 'Management' ? 'bg-dark' : 'bg-secondary'; ?> bg-opacity-10 <?php echo ($s['category'] ?? '') === 'Management' ? 'text-dark' : 'text-secondary'; ?> border border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px; letter-spacing: 0.05em;">
                                        <?php echo esc_html( $s['category'] ?? 'Support Staff' ); ?>
                                    </span>
                                </td>
                                <td class="px-3 px-md-4 py-4">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px; letter-spacing: 0.05em;">
                                        <?php echo esc_html( $s['role'] ); ?>
                                    </span>
                                </td>
                                <td class="px-3 px-md-4 py-4">
                                    <?php 
                                    if ($status === 'deletion_pending') {
                                        echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 px-3 py-1.5 rounded-pill fw-bold" style="font-size: 9px;">DELETION PENDING</span>';
                                    } else {
                                        echo SNESTX51_Admin_UI::render_status_badge( $status ); 
                                    }
                                    ?>
                                </td>
                                <td class="px-3 px-md-4 py-4">
                                    <div class="text-dark fw-bold small"><?php echo esc_html( SNESTX51_Privacy_Manager::mask_data( $s['phone'] ) ); ?></div>
                                </td>
                                <td class="px-3 px-md-4 py-4">
                                    <?php 
                                        $served_flats = $s['flats_served'] ?? [];
                                        if(empty($served_flats) && !empty($s['flat_no'])) $served_flats = [$s['flat_no']];
                                        
                                        if(empty($served_flats)): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 px-2 py-1 rounded-1 fw-bold" style="font-size: 10px;">Society Dedicated</span>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach($served_flats as $f_id): ?>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 10px;"><?php echo esc_html($f_id); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                </td>
                                <td class="pe-3 pe-md-5 py-4 text-end">
                                    <div class="d-flex justify-content-end gap-2 text-nowrap">
                                        <?php if ($is_request && !empty($s['request_id'])): ?>
                                            <?php echo SNESTX51_Admin_UI::render_inline_actions( 'pending', $s['request_id'], 'daily_help' ); ?>
                                        <?php elseif ($status === 'rejected'): ?>
                                            <button type="button" class="btn btn-sm btn-light text-primary border shadow-sm rounded-3 p-2 js-edit-staff" data-staff="<?php echo esc_attr(json_encode($s)); ?>" title="Edit">
                                                <i class="bi bi-pencil-square fs-6"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light text-danger border shadow-sm rounded-3 p-2 js-delete-staff" data-id="<?php echo esc_attr($s['id']); ?>" title="Archive">
                                                <i class="bi bi-archive fs-6"></i>
                                            </button>
                                        <?php elseif ( $status === 'archived' ): ?>
                                            <button onclick="restoreStaff('<?php echo esc_js($s['id']); ?>')" class="btn btn-sm btn-success px-3 fw-bold shadow-none rounded-3" style="font-size: 10px;">RESTORE</button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-light text-primary border shadow-sm rounded-3 p-2 js-edit-staff" data-staff="<?php echo esc_attr(json_encode($s)); ?>" title="Edit">
                                                <i class="bi bi-pencil-square fs-6"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-light text-danger border shadow-sm rounded-3 p-2 js-delete-staff" data-id="<?php echo esc_attr($s['id']); ?>" title="Archive">
                                                <i class="bi bi-archive fs-6"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Add/Edit Staff Modal -->

<?php
// Collect Modals to be printed outside the main root
add_action('SNESTX51_admin_modals', function() use ($all_flats) {
?>
<!-- Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="staffModalTitle">Add Staff Member</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="add-staff-form" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="SNESTX51_add_staff">
                <input type="hidden" name="staff_id" value="">
                <input type="hidden" name="profile_photo" value="">
                <?php wp_nonce_field( 'SNESTX51_staff_nonce' ); ?>

                <div class="modal-body p-4">
                    <!-- Profile Photo Selection -->
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-block">
                            <div class="rounded-circle bg-light border border-2 border-white shadow-sm overflow-hidden d-flex align-items-center justify-content-center" 
                                 style="width: 100px; height: 100px;">
                                <i class="bi bi-person-fill text-secondary fs-1" id="staff-icon"></i>
                                <img src="" id="staff-preview" class="w-100 h-100 object-fit-cover d-none" alt="Preview">
                            </div>
                            <label for="staff-photo-input" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow-sm cursor-pointer hover-scale" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-camera-fill small"></i>
                            </label>
                            <input type="file" name="profile_photo" id="staff-photo-input" class="d-none" accept="image/*" onchange="previewStaffImage(this)">
                        </div>
                        <div class="text-muted small mt-2">Upload Profile Photo</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-secondary">FullName <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="staff-name" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary">Primary Role <span class="text-danger">*</span></label>
                            <select name="role" id="staff-role" class="form-select shadow-none rounded-3 border-light" required>
                                <option value="Maid">Maid</option><option value="Cook">Cook</option>
                                <option value="Driver">Driver</option><option value="Nanny">Nanny</option>
                                <option value="Guard">Security Guard</option><option value="Cleaner">Cleaner</option>
                                <option value="Gardener">Gardener</option><option value="Manager">Manager</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-secondary">Staff Type / Category</label>
                            <select name="category" id="staff-category" class="form-select shadow-none rounded-3 border-light">
                                <option value="Support Staff">Support Staff</option>
                                <option value="Management">Management</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="staff-phone" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Assigned Flats (Multiple)</label>
                            <select name="flats_served[]" id="staff-flat" class="form-select shadow-none rounded-3 border-light" multiple style="height: 100px;">
                                <?php if(!empty($all_flats)): ?>
                                    <?php foreach($all_flats as $f): 
                                        $val = !empty($f['flat_number']) ? $f['flat_number'] : $f['id'];
                                        $label = !empty($f['flat_number']) ? $f['flat_number'] : $f['id'];
                                        if(!empty($f['block'])) $label = $f['block'] . ' - ' . $label;
                                    ?>
                                        <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <div class="form-text small">Hold Ctrl/Cmd to select multiple flats.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-secondary">Gender</label>
                            <select name="sex" id="staff-sex" class="form-select shadow-none rounded-3 border-light">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Daily Visiting Hours</label>
                        <input type="text" name="visiting_hours" id="staff-hours" placeholder="e.g. 7 AM - 10 AM, 4 PM - 7 PM" class="form-control shadow-none rounded-3 border-light">
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">ID Proof (Photo/Document)</label>
                        <div class="input-group">
                            <input type="file" name="id_proof" id="staff-document" accept="image/*,application/pdf" class="form-control shadow-none rounded-3 border-light">
                        </div>
                        <div id="current-doc-preview" class="mt-2 d-none">
                            <a href="#" target="_blank" class="small text-primary fw-bold"><i class="bi bi-eye me-1"></i>View Current Document</a>
                        </div>
                        <div class="form-text small">Take a photo or upload Aadhar, Voter ID, etc.</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Save Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-body p-4 text-center">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 64px; height: 64px;">
                    <i class="bi bi-trash3 fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Archive Staff Member?</h5>
                <p class="text-secondary small mb-0">This record will be moved to the archive registry.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>
<?php 
/* Redundant inline script removed in favor of snestx-staff.js */
?>
