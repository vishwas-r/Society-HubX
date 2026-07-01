<?php
/**
 * View: Vehicles (Bootstrap Migration)
 * Integrates directly with SNESTX51_DB_Router for data.
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Data is passed from SNESTX51_Vehicle_Manager::render_page via context
// $vehicles, $pending, $history, $flats, $residents are available.

if (!isset($vehicles)) $vehicles = array();
if (!isset($pending)) $pending = array();
if (!isset($history)) $history = array();
if (!isset($flats)) $flats = array();
if (!isset($residents)) $residents = array();

$flat_owners = [];
foreach($residents as $r) {
    if(isset($r['type']) && strtolower($r['type']) === 'owner') $flat_owners[$r['flat_no']] = $r['name'];
}

$success_msg = isset($_GET['success']) ? 'Vehicle database updated successfully.' : '';
?>

    <!-- Global Messages (Outside Cards) -->

    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Vehicle Registry</h1>
        <p class="text-secondary m-0 mt-1">Authorized society vehicles, parking assignments, and stickers.</p>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden mb-4">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="filter-search" placeholder="Search by number, owner, or flat..." 
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
                    <button id="addVehicle" class="js-open-vehicle-modal btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                        <i class="bi bi-plus-lg"></i>
                        <span>Add Vehicle</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-bottom border-light px-5 bg-white">
            <ul class="nav nav-tabs border-0 gap-5" id="vehicleTabs">
                <li class="nav-item">
                    <button onclick="switchTab('all')" class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" data-tab="all" style="background:none;">All Vehicles</button>
                </li>
                 <li class="nav-item">
                    <button onclick="switchTab('approved')" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="approved" style="background:none;">Active Only</button>
                </li>
                <li class="nav-item">
                    <button onclick="switchTab('archived')" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="archived" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>


        <!-- Table Content -->
        <div id="vehicleContainer" class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="bg-light">
                        <th class="ps-5 py-4 border-0" style="width: 40px;">
                            <input type="checkbox" id="bulk-select-all" class="form-check-input bg-light border-slate-200 shadow-none">
                        </th>
                        <th class="ps-2 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Vehicle Details</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Status</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Ownership Info</th>
                        <th class="px-4 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider">Sticker #</th>
                        <th class="pe-5 py-4 text-uppercase small text-muted fw-bold border-0 tracking-wider text-end">Operations</th>
                    </tr>
                </thead>
                <tbody class="border-top-0">
                    <?php 
                    // Prepare flat owners map for quick lookup
                    $flat_owners = array();
                    if (!empty($residents)) {
                        foreach ($residents as $res) {
                            if (($res['type'] ?? '') === 'owner') {
                                $flat_owners[$res['flat_no']] = $res['name'];
                            }
                        }
                    }

                    // 1. Index active vehicles
                    $rows_by_entity = array();
                    if ( ! empty( $vehicles ) ) {
                        foreach ( $vehicles as $v ) {
                            $v_id = $v['id'] ?? '';
                            if (!$v_id) continue;
                            
                            $v_status = $v['status'] ?? 'approved';
                            $v['is_request'] = in_array($v_status, ['pending', 'rejected']);
                            $v['request_id'] = '';
                            $rows_by_entity[$v_id] = $v;
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
                                // NEW: This is likely a new vehicle addition
                                $payload['id'] = $entity_id ? $entity_id : $request_id;
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
                            <td colspan="6" class="px-5 py-5 text-center text-slate-400">
                                <div class="py-5 text-center">
                                    <i class="bi bi-car-front fs-1 mb-3 d-block opacity-20"></i>
                                    <p class="m-0">No vehicles registered in the system.</p>
                                    <button class="btn btn-link js-open-vehicle-modal text-decoration-none p-0 mt-2">Add your first vehicle</button>
                                </div>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $all_rows as $v ) : 
                            $status = strtolower($v['status'] ?? 'approved');
                            $is_request = $v['is_request'] ?? false;
                            $v_type = strtolower($v['type'] ?? 'car');
                        ?>
                        <tr class="vehicle-row border-bottom border-light" 
                            data-status="<?php echo esc_attr($status); ?>" 
                            data-search="<?php echo esc_attr(strtolower(($v['number']??'') . ' ' . ($v['owner_name']??'') . ' ' . ($v['flat_no']??''))); ?>">
                            <td class="ps-5 py-4">
                                <input type="checkbox" value="<?php echo esc_attr(!empty($v['request_id']) ? $v['request_id'] : $v['id']); ?>" class="form-check-input snestx-bulk-checkbox bg-light border-slate-200 shadow-none">
                            </td>
                            <td class="ps-2 py-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0 <?php echo $status === 'pending' ? 'bg-warning' : ($status === 'rejected' ? 'bg-danger' : ($status === 'archived' ? 'bg-secondary' : 'bg-primary')); ?> bg-opacity-10 <?php echo $status === 'pending' ? 'text-warning' : ($status === 'rejected' ? 'text-danger' : ($status === 'archived' ? 'text-secondary' : 'text-primary')); ?> rounded-3 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                        <i class="bi <?php echo $v_type === 'bike' ? 'bi-bicycle' : 'bi-car-front'; ?> fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo esc_html( $v['number'] ); ?></div>
                                        <div class="text-secondary small" style="font-size: 11px;"><?php echo esc_html( ($v['brand'] ?? '') . ' ' . ($v['model'] ?? '') ); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                    <?php 
                                    if ($status === 'deletion_pending') {
                                        echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 px-3 py-1.5 rounded-pill fw-bold" style="font-size: 9px;">DELETION PENDING</span>';
                                    } else {
                                        echo SNESTX51_Admin_UI::render_status_badge( $status ); 
                                    }
                                    ?>
                                </td>
                            <td class="px-4 py-4">
                                <div class="fw-bold text-dark small"><?php echo esc_html( !empty($v['owner_name']) ? $v['owner_name'] : ($flat_owners[$v['flat_no']] ?? 'System Admin') ); ?></div>
                                <div class="text-secondary small" style="font-size: 11px;">Flat <?php echo esc_html( $v['flat_no'] ); ?></div>
                            </td>
                            <td class="px-4 py-4 text-center">
                                <span class="badge bg-light text-dark fw-bold px-3 py-2 border rounded-3" style="font-size: 11px;">#<?php echo esc_html( $v['sticker'] ?? '---' ); ?></span>
                            </td>
                                <td class="pe-5 py-4 text-end">
                                <div class="d-flex justify-content-end gap-2 text-nowrap">
                                    <?php if ($is_request && !empty($v['request_id'])): ?>
                                        <?php echo SNESTX51_Admin_UI::render_inline_actions( 'pending', $v['request_id'], 'vehicles' ); ?>
                                    <?php elseif ($status === 'rejected'): ?>
                                        <button class="btn btn-sm btn-light js-edit-vehicle text-primary border shadow-sm rounded-3 p-2" data-vehicle="<?php echo esc_attr(json_encode($v)); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light js-delete-vehicle text-danger border shadow-sm rounded-3 p-2" data-id="<?php echo esc_attr($v['id']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php elseif ($status === 'archived'): ?>
                                        <button onclick="restoreVehicle('<?php echo esc_js($v['id']); ?>')" class="btn btn-sm btn-success px-3 fw-bold shadow-none rounded-3" style="font-size: 10px;">RESTORE</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light js-edit-vehicle text-primary border shadow-sm rounded-3 p-2" data-vehicle="<?php echo esc_attr(json_encode($v)); ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light js-delete-vehicle text-danger border shadow-sm rounded-3 p-2" data-id="<?php echo esc_attr($v['id']); ?>">
                                            <i class="bi bi-trash"></i>
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
add_action('snestx51_admin_modals', function() use ($flats) {
?>
<!-- Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="vehicleModalTitle">Register Vehicle</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="add-vehicle-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="snestx51_add_vehicle" id="v-action">
                    <input type="hidden" name="vehicle_id" id="v-id">
                    <?php wp_nonce_field( 'snestx51_add_vehicle_nonce' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Number Plate <span class="text-danger">*</span></label>
                        <input type="text" name="number" id="v-number" placeholder="KA52AB1234" class="form-control shadow-none rounded-3 border-light" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold text-secondary">Assigned Flat/Unit <span class="text-danger">*</span></label>
                            <select name="flat_no" id="v-flat" class="form-select shadow-none rounded-3 border-light" required>
                                <option value="">Select Unit...</option>
                                <?php foreach($flats as $f): ?>
                                    <option value="<?php echo esc_attr($f['flat_number']); ?>"><?php echo esc_html($f['flat_number']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary">Vehicle Type <span class="text-danger">*</span></label>
                            <select name="type" id="v-type" class="form-select shadow-none rounded-3 border-light" required>
                                <option>Car</option><option>Bike / Scooter</option><option>Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Sticker ID</label>
                            <input type="text" name="sticker" id="v-sticker" class="form-control shadow-none rounded-3 border-light">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Owner Override</label>
                            <input type="text" name="owner_name" id="v-owner" placeholder="Defaults to Owner" class="form-control shadow-none rounded-3 border-light">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Save Registry</button>
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
                <h5 class="fw-bold text-dark mb-2">Delete Vehicle?</h5>
                <p class="text-secondary small mb-0">This vehicle will be archived and removed from active registry.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 border-0 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>

            </div>
        </div>
    </div>
</div>
