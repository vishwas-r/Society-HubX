<?php
/**
 * View: Flats & Units (Bootstrap Migration)
 */

$db = new SGVX51_DB_Router();
$flats = $db->get( 'flats' );

// Sort: Block then Number
usort($flats, function($a, $b) {
    if ($a['block'] === $b['block']) {
        return strnatcmp($a['flat_number'] ?? '', $b['flat_number'] ?? '');
    }
    return strcmp($a['block'] ?? '', $b['block'] ?? '');
});

$error_msg = isset( $_GET['error'] ) ? sanitize_text_field( urldecode( $_GET['error'] ) ) : '';
$success_msg = isset( $_GET['success'] ) ? 'Society units updated successfully.' : '';
?>

    <!-- Global Messages (Outside Cards) -->

    <!-- Page Header (Outside Card) -->
    <div class="mb-4 mb-lg-5 px-1 px-sm-2">
        <h1 class="h3 fw-bold text-dark m-0 d-flex align-items-center gap-2" style="letter-spacing: -0.02em;">
            <i class="bi bi-building text-primary d-sm-none"></i>
            Society Units
        </h1>
        <p class="text-secondary m-0 mt-1 small">Management of blocks, flats, and occupancy status.</p>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative mb-2 mb-md-0">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="filter-search" placeholder="Search by Flat No, Owner Name..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <button id="addFlat" class="btn btn-primary px-3 px-sm-4 fw-bold rounded-3 d-flex align-items-center justify-content-center gap-2 shadow-sm flex-grow-1 flex-md-grow-0" style="height: 48px;" onclick="openFlatModal()">
                        <i class="bi bi-plus-circle-fill fs-5"></i>
                        <span>Add Unit</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Collapsible Filters Section -->
        <!-- <div id="filter-section" class="collapse border-bottom border-light bg-light p-4 px-5">
            <div class="row g-3 align-items-end">
                <div class="col-md-9">
                    <p class="small text-secondary mb-2 fw-bold">Advanced Search</p>
                </div>
                <div class="col-md-3 d-grid">
                    <button onclick="applyFilters()" class="btn btn-primary fw-bold rounded-3" style="height: 44px;">Apply Search</button>
                </div>
            </div>
        </div> -->

        <!-- Navigation Tabs (Integrated) -->
        <div class="border-bottom border-light px-3 px-md-5 bg-white overflow-x-auto no-scrollbar">
            <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="flatTabs">
                <li class="nav-item">
                    <button onclick="switchTab('all')" class="nav-link tab-btn active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" data-tab="all" style="background:none;">All Units</button>
                </li>
                <li class="nav-item">
                    <button onclick="switchTab('occupied')" class="nav-link tab-btn py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent hover-text-dark" data-tab="occupied" style="background:none;">Occupied</button>
                </li>
                <li class="nav-item">
                    <button onclick="switchTab('vacant')" class="nav-link tab-btn py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent hover-text-dark" data-tab="vacant" style="background:none;">Vacant</button>
                </li>
                <li class="nav-item">
                    <button onclick="switchTab('archived')" class="nav-link tab-btn py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent hover-text-dark" data-tab="archived" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>

        <div id="flatContainer" class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom border-light">
                    <tr>
                        						<th class="ps-3 ps-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Unit No</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Block</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Area (SqFt)</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Occupancy</th>
                        <th class="px-3 px-md-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Parking</th>
                        <th class="pe-3 pe-md-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Ops</th>
                    </tr>
                </thead>
                <tbody id="flatsTableBody" class="border-top-0">
                    <?php foreach ( $flats as $f ) : 
                        $status = strtolower($f['status'] ?? 'vacant');
                        $p_status = strtolower($f['parking_status'] ?? 'vacant');
						$sq_foot = isset($f['sq_foot']) ? $f['sq_foot'] : 0;
                    ?>
                    <tr class="flat-row border-bottom border-light" data-status="<?php echo esc_attr($status); ?>" data-search="<?php echo esc_attr(strtolower(($f['id']??'') . ' ' . ($f['owner_name']??''))); ?>">
                        <td class="ps-3 ps-md-5 py-4 fw-bold text-dark"><?php echo esc_html( $f['id'] ); ?></td>
                        <td class="px-4 py-4 text-secondary"><?php echo esc_html( $f['block'] ); ?></td>
                        <td class="px-4 py-4 text-center text-secondary"><?php echo esc_html( $sq_foot ); ?></td>
                        <td class="px-4 py-4 text-center">
                            <?php if($status === 'occupied'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill px-3 py-1.5 fw-bold" style="font-size: 10px;">Occupied</span>
                            <?php else: ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10 rounded-pill px-3 py-1.5 fw-bold" style="font-size: 10px;">Vacant</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <?php if(!empty($f['parking_slot'])): ?>
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <span class="small fw-bold text-dark"><?php echo esc_html($f['parking_slot']); ?></span>
                                    <span class="rounded-circle <?php echo $p_status === 'occupied' ? 'bg-success' : 'bg-secondary'; ?>" style="width: 8px; height: 8px; <?php echo $p_status !== 'occupied' ? 'opacity: 0.4;' : ''; ?>"></span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted opacity-25">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3 pe-md-5 py-4 text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-light text-primary border shadow-sm rounded-3 p-2 js-edit-flat" data-flat="<?php echo esc_attr(json_encode($f)); ?>">
                                    <i class="bi bi-pencil-square fs-6"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light text-danger border shadow-sm rounded-3 p-2 js-delete-flat" data-id="<?php echo esc_attr($f['id']); ?>">
                                    <i class="bi bi-archive fs-6"></i>
                                </button>
                                <?php if ($status === 'archived'): ?>
                                    <button onclick="restoreFlat('<?php echo esc_js($f['id']); ?>')" class="btn btn-sm btn-success px-3 fw-bold" style="font-size: 10px;">RESTORE</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() {
?>
<!-- Add/Edit Flat Modal (Bootstrap) -->
<div class="modal fade" id="flatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="flatModalTitle">Add New Unit</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="add-flat-form">
                    <div class="modal-body p-4">
                    <input type="hidden" name="action" value="sgvx51_add_flat">
                    <input type="hidden" name="flat_id" value="">
                    <?php wp_nonce_field( 'sgvx51_add_flat_nonce' ); ?>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Block <span class="text-danger">*</span></label>
                             <input type="text" name="block" class="form-control shadow-none rounded-3 border-light" placeholder="A" required>
                        </div>
                        <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Flat Number <span class="text-danger">*</span></label>
                             <input type="text" name="flat_number" class="form-control shadow-none rounded-3 border-light" placeholder="101" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Type</label>
                             <select name="type" class="form-select shadow-none rounded-3 border-light">
                                <option>2BHK</option><option>3BHK</option><option>4BHK</option><option>1BHK</option><option>Penthouse</option>
                             </select>
                        </div>
                        <div class="col-6">
                             <label class="form-label small fw-bold text-secondary">Sq. Foot</label>
                             <input type="number" step="0.01" name="sq_foot" class="form-control shadow-none rounded-3 border-light" placeholder="1200.00">
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Unit Status</label>
                            <select name="status" class="form-select shadow-none rounded-3 border-light">
                                <option value="vacant">Vacant</option>
                                <option value="occupied">Occupied</option>
                            </select>
                        </div>
                         <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Floor</label>
                            <input type="text" name="floor" class="form-control shadow-none rounded-3 border-light">
                        </div>
                    </div>

                    <div class="row g-3 mb-3 mt-1">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Parking Slot</label>
                            <input type="text" name="parking_slot" class="form-control shadow-none rounded-3 border-light" placeholder="P-101">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-secondary">Parking Status</label>
                            <select name="parking_status" class="form-select shadow-none rounded-3 border-light">
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="reserved">Reserved</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Save Unit</button>
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
                <h5 class="fw-bold text-dark mb-2 modal-title">Archive Unit?</h5>
                <p class="text-secondary small mb-0 modal-text">This unit will be moved to the archive registry.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>
