<?php
/**
 * View: Residents (Bootstrap Migration)
 * Integrates directly with SGVX51_DB_Router for data.
 */

// Data is passed from SGVX51_Resident_Manager::render_page via context
// $residents, $pending, $history, $flats are available.

if (!isset($residents)) $residents = array();
if (!isset($pending)) $pending = array();
if (!isset($history)) $history = array();
if (!isset($flats)) $flats = array();
?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Residents Directory</h1>
        <p class="text-secondary m-0 mt-1">Efficiently manage all society homeowners and tenants.</p>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="filter-search" placeholder="Search by name, flat, phone..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <div class="dropdown sgvx-bulk-actions d-none">
                        <button class="btn btn-outline-secondary dropdown-toggle px-3 rounded-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="height: 48px;">
                            Bulk Actions (<span id="selected-count">0</span>)
                        </button>
                        <ul class="dropdown-menu shadow-sm border-0 mt-1">
                            <li><a class="dropdown-item fw-bold text-success" href="#" onclick="sgvxBulkProcess('approve')"><i class="bi bi-check-circle me-2"></i>Approve Selected</a></li>
                            <li><a class="dropdown-item fw-bold text-danger" href="#" onclick="sgvxBulkProcess('reject')"><i class="bi bi-x-circle me-2"></i>Reject Selected</a></li>
                        </ul>
                    </div>
                    <button class="js-toggle-filters btn btn-light px-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                        <i class="bi bi-funnel"></i>
                        <span>Filters</span>
                    </button>
                    <button class="js-open-resident-modal btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                        <i class="bi bi-person-plus-fill fs-5"></i>
                        <span>Add Resident</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsible Filters Section -->
        <div class="collapse" id="filter-section">
            <div class="p-4 px-md-5 bg-light border-bottom border-light">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Resident Type</label>
                        <select id="filter-type" class="form-select shadow-none rounded-3 border-light">
                            <option value="all">All Types</option>
                            <option value="owner">Owner</option>
                            <option value="tenant">Tenant</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-secondary">Status</label>
                        <select id="filter-status" class="form-select shadow-none rounded-3 border-light">
                            <option value="all">All Statuses</option>
                            <option value="approved">Active Only</option>
                            <option value="pending">Pending</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-end gap-2">
                        <button class="js-apply-filters btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Apply Filters</button>
                        <button class="js-clear-filters btn btn-light px-4 fw-semibold text-secondary rounded-3 border-light">Clear</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs (Integrated) -->
        <div class="px-5 bg-white border-bottom border-light">
            <ul class="nav nav-tabs border-0 gap-5" id="residentsTabs">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" data-tab="all" style="background:none;">All Residents</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="owner" style="background:none;">Owners</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="tenant" style="background:none;">Tenants</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="family" style="background:none;">Family</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="archive" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>


        <!-- Table Content -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th class="ps-5 py-4 border-0" style="width: 40px;">
                            <input type="checkbox" id="bulk-select-all" class="form-check-input shadow-none">
                        </th>
                        <th class="ps-2 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider" style="font-size: 10px;">Resident Details</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider" style="font-size: 10px;">Flat / Unit</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider" style="font-size: 10px;">Status</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider" style="font-size: 10px;">Contact Info</th>
                        <th class="pe-5 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider text-end" style="font-size: 10px;">Operations</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php 
                    // Prepare all rows
                    $all_rows = array();
                    
                    // Add Residents (Active & Pending)
                    if ( ! empty( $residents ) ) {
                        foreach ( $residents as $r ) {
                            $status = $r['status'] ?? 'approved';
                            $r['is_request'] = in_array($status, ['pending', 'rejected']);
                            $r['is_archived'] = ($status === 'archived');
                            $all_rows[] = $r;
                        }
                    }

                    // Add Archived from History Table (if any)
                    if ( ! empty( $history ) ) {
                        foreach ( $history as $h ) {
                            $h['status'] = 'archived';
                            $h['is_request'] = false;
                            $h['is_archived'] = true;
                            $all_rows[] = $h;
                        }
                    }

                    if ( empty( $all_rows ) ) : ?>
                        <tr>
                            <td colspan="6" class="px-5 py-5 text-center text-slate-400">
                                <div class="py-5 text-center">
                                    <i class="bi bi-people fs-1 mb-3 d-block opacity-20"></i>
                                    <p class="m-0">No residents found in the records.</p>
                                    <button class="btn btn-link js-open-resident-modal text-decoration-none p-0 mt-2">Add your first resident</button>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $all_rows as $row ) : 
                             $is_archived = $row['is_archived'] ?? false;
                             $is_request  = $row['is_request'] ?? false;
                             $status      = $row['status'] ?? 'approved';
                             $type        = strtolower($row['type'] ?? 'owner'); 
                             $type_label  = ucfirst($type);
                        ?>
                        <tr class="resident-row border-bottom border-light" 
                            data-status="<?php echo esc_attr($status); ?>" 
                            data-type="<?php echo esc_attr($type); ?>"
                            data-search="<?php echo esc_attr(strtolower(($row['flat_no']??'') . ' ' . ($row['name']??''))); ?>">
                            <td class="ps-5 py-4">
                                <input type="checkbox" value="<?php echo esc_attr($row['id']); ?>" class="form-check-input sgvx-bulk-checkbox shadow-none" <?php echo !$is_request ? 'disabled' : ''; ?>>
                            </td>
                            <td class="ps-2 py-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0 <?php echo $is_archived ? 'bg-light text-muted' : ($status === 'rejected' ? 'bg-danger-subtle text-danger' : ($is_request ? 'bg-warning-subtle text-warning' : 'bg-primary-subtle text-primary')); ?> rounded-3 d-flex align-items-center justify-content-center fw-bold" style="width: 44px; height: 44px; font-size: 1.1rem;">
                                        <?php echo substr($row['name'] ?? 'U', 0, 1); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold <?php echo $is_archived ? 'text-muted' : 'text-dark'; ?>"><?php echo esc_html( $row['name'] ); ?></div>
                                        <div class="text-secondary small" style="font-size: 11px;"><?php echo esc_html( $row['email'] ?? '-' ); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-light text-dark border-0 px-2 py-1.5 fw-bold" style="font-size: 11px;"><?php echo esc_html( $row['flat_no'] ?? '-' ); ?></span>
                                    <span class="badge <?php echo $type === 'owner' ? 'bg-success' : ($type === 'tenant' ? 'bg-info text-dark' : 'bg-primary'); ?> rounded-pill" style="font-size: 9px;"><?php echo $type_label; ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <?php echo SGVX51_Admin_UI::render_status_badge( $status ); ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-secondary fw-bold small"><?php echo esc_html( $row['phone'] ?? '-' ); ?></div>
                            </td>
                            <td class="pe-5 py-4 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ( $status === 'pending' ) : ?>
                                        <?php echo SGVX51_Admin_UI::render_inline_actions( 'pending', $row['id'], 'residents' ); ?>
                                    <?php elseif ( $status === 'rejected' ) : ?>
                                        <button class="btn btn-sm btn-light js-edit-resident text-primary border shadow-sm rounded-3 p-2" data-resident="<?php echo esc_attr(json_encode($row)); ?>">
                                            <i class="bi bi-pencil-square fs-6"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light js-delete-resident text-danger border shadow-sm rounded-3 p-2" data-id="<?php echo $row['id']; ?>">
                                            <i class="bi bi-trash fs-6"></i>
                                        </button>
                                    <?php elseif ( ! $is_archived ) : ?>
                                        <button class="btn btn-sm btn-light js-edit-resident text-primary border shadow-sm rounded-3 p-2" data-resident="<?php echo esc_attr(json_encode($row)); ?>">
                                            <i class="bi bi-pencil-square fs-6"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light js-delete-resident text-danger border shadow-sm rounded-3 p-2" data-id="<?php echo $row['id']; ?>">
                                            <i class="bi bi-trash fs-6"></i>
                                        </button>
                                    <?php else : ?>
                                        <button class="btn btn-sm btn-light js-restore-resident text-success border shadow-sm rounded-3 p-2" data-id="<?php echo $row['id']; ?>" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise fs-6"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light js-delete-permanent text-danger border shadow-sm rounded-3 p-2" data-id="<?php echo $row['id']; ?>" title="Delete Permanently">
                                            <i class="bi bi-x-circle fs-6"></i>
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

<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() use ($flats) {
?>
<!-- Modal Refactor to Bootstrap structure -->
<div class="modal fade" id="residentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <form id="add-resident-form" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="POST">
                <input type="hidden" name="action" value="sgvx51_add_resident">
                <input type="hidden" name="resident_id" value="">
                <?php wp_nonce_field( 'sgvx51_resident_nonce' ); ?>
                
                <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold m-0" id="modal-title">Add New Resident</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Full Name</label>
                        <input type="text" name="name" required class="form-control shadow-none rounded-3">
                    </div>
                    <div class="row g-3">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Unit No</label>
                            <select name="flat_no" class="form-select shadow-none rounded-3">
                                <option value="">Select</option>
                                <?php foreach($flats as $f): ?>
                                    <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['id']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Type</label>
                            <select name="type" class="form-select shadow-none rounded-3">
                                <option value="owner">Owner</option>
                                <option value="tenant">Tenant</option>
                                <option value="family">Family</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Phone</label>
                            <input type="text" name="phone" class="form-control shadow-none rounded-3">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Email</label>
                            <input type="email" name="email" class="form-control shadow-none rounded-3">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light fw-semibold text-secondary px-4 rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Save Resident</button>
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
                <h5 class="fw-bold text-dark mb-2 modal-title">Delete Resident?</h5>
                <p class="text-secondary small mb-0 modal-text">This action cannot be undone. All associated data will be permanently removed.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>

