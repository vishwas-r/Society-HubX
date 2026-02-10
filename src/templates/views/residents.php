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
    <div class="mb-4 mb-lg-5 px-1 px-sm-2">
        <h1 class="h3 fw-bold text-dark m-0 d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-people-fill text-primary d-sm-none"></i>
            Residents Directory
        </h1>
        <p class="text-secondary m-0 mt-1 small">Efficiently manage all society homeowners and tenants.</p>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative mb-2 mb-md-0">
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
                    <button class="js-toggle-filters btn btn-light px-3 px-sm-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center justify-content-center gap-2" style="height: 48px;">
                        <i class="bi bi-funnel"></i>
                        <span class="d-none d-sm-inline">Filters</span>
                    </button>
                    <button id="addResident" class="js-open-resident-modal btn btn-primary px-3 px-sm-4 fw-bold shadow-sm rounded-3 d-flex align-items-center justify-content-center gap-2 flex-grow-1 flex-md-grow-0" style="height: 48px;">
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
        <div class="px-3 px-md-5 bg-white border-bottom border-light overflow-x-auto no-scrollbar">
            <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="residentsTabs">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary tab-btn" data-tab="all" style="background:none;">All Residents</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="pending" style="background:none;">Pending Requests</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="owner" style="background:none;">Owners</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="tenant" style="background:none;">Tenants</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="family" style="background:none;">Family</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent tab-btn" data-tab="archived" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>


        <!-- Table Content -->
        <div id="residentContainer" class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th class="ps-3 ps-md-5 py-4 border-0" style="width: 40px;">
                            <input type="checkbox" id="bulk-select-all" class="form-check-input shadow-none">
                        </th>
                        <th class="ps-0 ps-md-2 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Resident Details</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Flat / Unit</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Society Role</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Status</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Contact Info</th>
                        <th class="pe-3 pe-md-5 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider text-end">Ops</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php 
                    // 1. Index active residents
                    $rows_by_entity = array();
                    if ( ! empty( $residents ) ) {
                        foreach ( $residents as $r ) {
                            $r_id = $r['id'] ?? '';
                            if (!$r_id) continue;
                            
                            $r_status = $r['status'] ?? 'approved';
                            $r['is_request'] = in_array($r_status, ['pending', 'rejected']);
                            $r['is_archived'] = ($r_status === 'archived');
                            $r['request_id'] = '';
                            $rows_by_entity[$r_id] = $r;
                        }
                    }

                    // 2. Merge Pending Requests to deduplicate
                    if ( ! empty( $pending ) ) {
                        foreach ( $pending as $p ) {
                            $payload = json_decode($p['payload'], true) ?: [];
                            $entity_id = $p['entity_id'] ?? '';
                            $request_id = $p['id'];
                            
                            if ( $entity_id && isset($rows_by_entity[$entity_id]) ) {
                                // OVERLAY
                                if ($p['request_type'] === 'delete') {
                                    $rows_by_entity[$entity_id]['status'] = 'deletion_pending';
                                } else {
                                    $rows_by_entity[$entity_id] = array_merge($rows_by_entity[$entity_id], $payload);
                                    $rows_by_entity[$entity_id]['status'] = 'pending'; 
                                }
                                $rows_by_entity[$entity_id]['is_request'] = true;
                                $rows_by_entity[$entity_id]['request_id'] = $request_id;
                            } else {
                                // NEW ADDITION
                                $payload['id'] = $request_id;
                                $payload['status'] = 'pending';
                                $payload['is_request'] = true;
                                $payload['request_id'] = $request_id;
                                $rows_by_entity[$request_id] = $payload;
                            }
                        }
                    }

                    // 3. Add Archived from History
                    $all_rows = array_values($rows_by_entity);
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
                            <td colspan="100%" class="px-5 py-5 text-center text-slate-400">
                                <div class="py-5 text-center">
                                    <i class="bi bi-people fs-1 mb-3 d-block opacity-20"></i>
                                    <p class="m-0">No residents found in the records.</p>
                                    <button class="btn btn-link js-open-resident-modal text-decoration-none p-0 mt-2">Add your first resident</button>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php 
                        foreach ( $all_rows as $row ) : 
                             $is_archived = $row['is_archived'] ?? false;
                             $is_request  = $row['is_request'] ?? false;
                             $status      = strtolower($row['status'] ?? 'approved');
                             $type        = strtolower($row['type'] ?? 'owner'); 
                             $type_label  = ucfirst($type);
                             $request_id  = !empty($row['request_id']) ? $row['request_id'] : $row['id'];
                             
                             // Badge and class logic
                             $is_deletion_pending = ($status === 'deletion_pending');
                             $is_update_pending   = ($is_request && $status === 'pending' && !empty($row['entity_id']));
                        ?>
                        <tr class="resident-row border-bottom border-light" 
                            data-status="<?php echo esc_attr($status); ?>" 
                            data-type="<?php echo esc_attr($type); ?>"
                            data-search="<?php echo esc_attr(strtolower(($row['flat_no']??'') . ' ' . ($row['name']??''))); ?>">
                            <td class="ps-3 ps-md-5 py-4">
                                <input type="checkbox" value="<?php echo esc_attr($request_id); ?>" class="form-check-input sgvx-bulk-checkbox shadow-none">
                            </td>
                            <td class="ps-0 ps-md-2 py-4">
                                <div class="d-flex align-items-center gap-3">
                                    <?php echo SGVX51_Admin_UI::render_avatar( $row['name'], $row['email'] ?? '', $row['profile_photo'] ?? '', 44 ); ?>
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
                                <?php if(!empty($row['roles'])): ?>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;"><?php echo esc_html($row['roles']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">Resident</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4">
                                <?php 
                                if ($is_deletion_pending) {
                                    echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 px-3 py-1.5 rounded-pill fw-bold" style="font-size: 9px;">DELETION PENDING</span>';
                                } else if ($is_update_pending) {
                                    echo SGVX51_Admin_UI::render_status_badge( 'pending' );
                                    echo '<div class="small text-warning mt-1" style="font-size: 10px; font-weight: 600;">UPDATE PENDING</div>';
                                } else {
                                    echo SGVX51_Admin_UI::render_status_badge( $status ); 
                                }
                                ?>
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-secondary fw-bold small"><?php echo esc_html( $row['phone'] ?? '-' ); ?></div>
                            </td>
                            <td class="pe-3 pe-md-5 py-4 text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <?php if ( $is_request ) : ?>
                                        <?php echo SGVX51_Admin_UI::render_inline_actions( 'pending', $request_id, 'residents' ); ?>
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
            <form id="add-resident-form" action="<?php echo admin_url( 'admin-post.php' ); ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sgvx51_add_resident">
                <input type="hidden" name="resident_id" value="">
                <?php wp_nonce_field( 'sgvx51_resident_nonce' ); ?>
                
                <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold m-0" id="modal-title">Add New Resident</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php 
                    $args = [
                        'context' => 'admin',
                        'flats'   => $flats,
                        'resident' => [] // Will be populated by JS for edits
                    ];
                    include SGVX51_PLUGIN_DIR . 'templates/components/resident-form.php'; 
                    ?>
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
                <h5 class="fw-bold text-dark mb-2 modal-title">Move to Archive?</h5>
                <p class="text-secondary small mb-0 modal-text">The resident will be moved to the history log. You can restore them later.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>

