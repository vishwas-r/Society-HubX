<?php
/**
 * View: Facilities (Bootstrap Migration)
 * Integrates with SGVX51_DB_Router.
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = new SGVX51_DB_Router();
$facilities = $db->get( 'facilities' );
$bookings   = $db->get( 'bookings' );
$residents  = $db->get( 'residents' );

// Filter bookings (sort descending)
$bookings = array_reverse($bookings);

$success_msg = isset($_GET['success']) ? 'Operation completed successfully.' : '';
$error_msg = isset($_GET['error']) ? sanitize_text_field(urldecode($_GET['error'])) : '';
?>

    <!-- Global Messages (Outside Cards) -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Facilities Dashboard Updated</div>
                    <div class="small opacity-75"><?php echo esc_html( $success_msg ); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Facility Management</h1>
                <p class="text-secondary m-0 mt-1">Configure society amenities and manage resident reservations.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button id="addBooking" onclick="openBookingModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi-calendar-plus fs-5"></i>
                    <span>New Booking</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-5 mb-4">
        <!-- Configuration Column -->
        <div class="col-lg-4">
            <!-- Add/Edit Facility Form -->
            <div class="card border-0 shadow-sm rounded-3 mb-5 overflow-hidden bg-white p-0">
                <div class="p-4 bg-primary bg-opacity-10 border-0 text-primary d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0" id="form-title">Add New Amenity</h5>
                    <button type="button" onclick="resetFacilityForm()" id="cancel-edit-btn" class="btn btn-link btn-sm text-danger fw-bold p-0 text-decoration-none d-none">Reset</button>
                </div>
                <div class="p-4">
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="facility-form">
                        <input type="hidden" name="action" value="sgvx51_add_facility">
                        <input type="hidden" name="facility_id" value="">
                        <?php wp_nonce_field( 'sgvx51_facility_nonce' ); ?>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Facility Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control bg-light border-0 shadow-none rounded-3" style="height: 44px;" placeholder="e.g. Community Clubhouse" required>
                        </div>

                        <!-- Pricing Model Toggle -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Pricing Model</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input shadow-none" type="radio" name="is_free" id="pricePaid" value="0" checked onchange="togglePricing(this.value)">
                                    <label class="form-check-label small fw-medium text-dark" for="pricePaid">
                                        Paid Facility (Rentable)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input shadow-none" type="radio" name="is_free" id="priceFree" value="1" onchange="togglePricing(this.value)">
                                    <label class="form-check-label small fw-medium text-dark" for="priceFree">
                                        Free (Complimentary)
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4" id="pricing-fields">
                            <div class="col-7">
                                <label class="form-label small fw-bold text-secondary text-nowrap">Usage Rate (₹)</label>
                                <div class="input-group flex-nowrap shadow-none border-light rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-light border-0 text-muted fw-bold">₹</span>
                                    <input type="number" name="rate" class="form-control bg-light border-0 shadow-none" style="height: 44px;" value="0">
                                </div>
                            </div>
                            <div class="col-5">
                                <label class="form-label small fw-bold text-secondary">Per/Unit</label>
                                <select name="rate_unit" class="form-select bg-light border-0 shadow-none rounded-3" style="height: 44px;">
                                    <option value="Hour">Per Hour</option>
                                    <option value="Day">Per Day</option>
                                    <option value="Event">Per Event</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                             <label class="form-label small fw-bold text-secondary">Max Booking Session (Hours)</label>
                             <input type="number" name="max_hours" class="form-control bg-light border-0 shadow-none rounded-3" style="height: 44px;" value="4">
                        </div>

                        <div class="mb-4 form-check bg-light p-3 rounded-3">
                            <input class="form-check-input shadow-none" type="checkbox" name="booking_required" value="1" id="bookingRequired" checked>
                            <label class="form-check-label fw-bold text-dark small" for="bookingRequired">
                                Requires Booking Approved?
                            </label>
                            <div class="form-text small mt-1">Uncheck for open amenities like Swimming Pool or Park.</div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label small fw-bold text-secondary">Booking Rules & Guidelines</label>
                            <textarea name="rules" class="form-control bg-light border-0 shadow-none rounded-3" rows="3" placeholder="Policies regarding cancellations or usage..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-3 shadow-none rounded-3" id="submit-btn" style="height: 50px;">Save Configuration</button>
                    </form>
                </div>
            </div>

            <!-- List of Facilities -->
            <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden p-0">
                <div class="p-4 bg-primary bg-opacity-10 border-0 text-primary">
                    <span class="small fw-bold text-uppercase tracking-wider">Active Resource List</span>
                </div>
                <div class="p-3 px-4 border-bottom border-light">
                    <div class="input-group flex-nowrap bg-light rounded-3 overflow-hidden">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
                        <input id="facility-list-search" type="text" class="form-control shadow-none border-0 bg-transparent" placeholder="Search facilities...">
                    </div>
                </div>
                <div id="facilityContainer" class="list-group list-group-flush">
                    <?php if(empty($facilities)): ?>
                        <div class="list-group-item text-center py-5 text-muted">
                            <i class="bi bi-wind fs-2 d-block opacity-25 mb-2"></i>
                            <p class="small m-0">No resources configured.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ( $facilities as $f ) : 
                            $rate_unit = $f['rate_unit'] ?? 'Hour';
                            $rate = $f['rate'] ?? ($f['rate_per_hour'] ?? 0);
                            $payload = esc_attr(json_encode(array_merge($f, ['rate' => $rate, 'rate_unit' => $rate_unit])));
                            $search_text = esc_attr(strtolower(($f['name'] ?? '') . ' ' . ($f['rate'] ?? '') . ' ' . ($f['max_hours'] ?? '')));
                        ?>
                            <div class="list-group-item px-4 py-4 border-light d-flex justify-content-between align-items-center group" data-search="<?php echo $search_text; ?>">
                                <div class="overflow-hidden">
                                    <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                        <div class="bg-primary bg-opacity-10 text-primary p-1.5 rounded-3" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-house-door"></i>
                                        </div>
                                        <span><?php echo esc_html($f['name']); ?></span>
                                    </div>
                                    <div class="mt-1 d-flex gap-3">
                                        <div class="text-primary fw-bold small">₹<?php echo esc_html($rate); ?>/<?php echo esc_html($rate_unit); ?></div>
                                        <div class="text-muted small"><i class="bi bi-clock"></i> Max <?php echo esc_html($f['max_hours'] ?? 0); ?>h</div>
                                        <?php if(empty($f['booking_required'])): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill" style="font-size: 10px;">Open for All</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                 <div class="d-flex gap-2">
                                    <button data-facility='<?php echo $payload; ?>' type="button" class="btn btn-sm btn-light border border-light p-2 js-edit-facility rounded-3 shadow-none">
                                        <i class="bi bi-pencil-square text-primary"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light border border-light p-2 text-danger js-delete-facility rounded-3 shadow-none" data-id="<?php echo esc_attr($f['id']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <!-- Bookings Column -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 d-flex flex-column overflow-hidden">
                
                <!-- Consolidated Toolbar -->
                <div class="p-4 px-md-5 border-bottom border-light bg-white">
                    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                        <!-- Smart Search -->
                        <div class="flex-grow-1 position-relative">
                            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                            <input type="text" id="bookingSearch" placeholder="Search by facility, resident..." 
                                   class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                                   style="height: 48px; font-size: 0.95rem;">
                        </div>
                        
                        <!-- Action Group -->
                        <div class="d-flex gap-2">
                             <div class="bg-primary bg-opacity-10 px-3 py-2 rounded-3 border border-primary border-opacity-10 d-flex align-items-center">
                                <span class="small fw-bold text-primary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Total Bookings: <?php echo count($bookings); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="bookingContainer" class="table-responsive flex-grow-1">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light border-bottom border-light">
                            <tr>
                                <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Facility Asset</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Resident Identity</th>
                                <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Schedule Detail</th>
                                <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Booking State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $bookings ) ) : ?>
                                <tr>
                                    <td colspan="4" class="px-5 py-5 text-center text-muted">
                                        <div class="py-5">
                                            <i class="bi bi-calendar-x fs-1 d-block opacity-25 mb-3"></i>
                                            <p class="m-0">No active bookings recorded in the system.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $bookings as $b ) : 
                                    $fac_name = 'Unknown Resource';
                                    foreach($facilities as $f) { if($f['id'] == $b['facility_id']) $fac_name = $f['name']; }
                                    $search_text = esc_attr(strtolower($fac_name . ' ' . ($b['resident_id']??'')));
                                ?>
                                    <tr class="booking-row border-bottom border-light" data-search="<?php echo $search_text; ?>">
                                        <td class="ps-5 py-4">
                                            <div class="fw-bold text-dark"><?php echo esc_html( $fac_name ); ?></div>
                                            <div class="small text-muted d-flex align-items-center gap-1" style="font-size: 10px;">
                                                <i class="bi bi-geo-alt"></i> Society Premise
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="small fw-bold text-primary"><?php echo esc_html( $b['resident_id'] ); ?></div>
                                            <div class="text-secondary small" style="font-size: 11px;">Identity Verified</div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-dark small fw-bold"><?php echo wp_date( 'D, d M, Y', strtotime( $b['start_time'] ) ); ?></div>
                                            <div class="text-primary fw-medium" style="font-size: 11px;"><?php echo wp_date( 'H:i', strtotime( $b['start_time'] ) ); ?> to <?php echo wp_date( 'H:i', strtotime( $b['end_time'] ) ); ?></div>
                                        </td>
                                        <td class="pe-5 py-4 text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                <?php 
                                                    $status_raw = strtolower($b['status'] ?? 'pending');
                                                    $status_class = 'bg-success text-success';
                                                    if ( $status_raw === 'pending' ) $status_class = 'bg-warning text-warning';
                                                    if ( $status_raw === 'rejected' ) $status_class = 'bg-danger text-danger';
                                                    if ( $status_raw === 'cancelled' ) $status_class = 'bg-secondary text-secondary';
                                                ?>
                                                <span class="badge <?php echo $status_class; ?> bg-opacity-10 border border-current border-opacity-10 px-3 py-1.5 rounded-pill text-uppercase fw-bold" style="font-size: 9px; --bs-border-color: currentColor;">
                                                    <?php echo esc_html( $b['status'] ); ?>
                                                </span>
                                                <button data-booking='<?php echo esc_attr(json_encode($b)); ?>' type="button" class="btn btn-sm btn-light border border-light p-2 js-edit-booking rounded-3 shadow-none">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-light border border-light p-2 text-danger js-delete-booking rounded-3 shadow-none" data-id="<?php echo esc_attr($b['id']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() use ($facilities, $residents) {
?>
<!-- Booking Modal -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Facility Booking</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="booking-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="sgvx51_book_facility">
                    <input type="hidden" name="booking_id" value="">
                    <?php wp_nonce_field( 'sgvx51_facility_nonce' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Pick Amenity <span class="text-danger">*</span></label>
                        <select name="facility_id" class="form-select shadow-none rounded-3 border-light" required>
                            <?php foreach ( $facilities as $f ) : ?>
                                <option value="<?php echo esc_attr( $f['id'] ); ?>"><?php echo esc_html( $f['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Select Resident <span class="text-danger">*</span></label>
                        <select name="resident_id" class="form-select shadow-none rounded-3 border-light" required>
                            <?php foreach ( $residents as $r ) : ?>
                                <option value="<?php echo esc_attr( $r['flat_no'] ); ?>"><?php echo esc_html( $r['flat_no'] . ' - ' . $r['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">From <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_time" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">To <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="end_time" class="form-control shadow-none rounded-3 border-light" required>
                        </div>
                    </div>

                    <div id="booking-status-container" class="d-none">
                        <label class="form-label small fw-bold text-secondary">Booking Status</label>
                        <select name="status" class="form-select shadow-none rounded-3 border-light">
                            <option value="pending">Pending Approval</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Booking Confirmation Modal -->
<div class="modal fade" id="deleteBookingConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-body p-4 text-center">
                <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4" style="width: 64px; height: 64px;">
                    <i class="bi bi-calendar-x fs-2"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Delete Booking?</h5>
                <p class="text-secondary small mb-0">This reservation will be permanently removed from the schedule.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-booking-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
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
                <h5 class="fw-bold text-dark mb-2">Delete Facility?</h5>
                <p class="text-secondary small mb-0">This amenitiy will be permanently removed from the system.</p>
            </div>
            <div class="modal-footer border-0 p-4 pt-0 gap-2">
                <button type="button" class="btn btn-light flex-grow-1 fw-semibold text-secondary rounded-3 py-2.5 shadow-none" data-bs-dismiss="modal">No, Keep</button>
                <button type="button" id="confirm-delete-btn" class="btn btn-danger flex-grow-1 fw-bold rounded-3 py-2.5 shadow-none">Confirm Delete</button>
            </div>
        </div>
    </div>
</div>
<?php }); ?>



<script>
// Booking Modal Instance
let bookingModalInstance = null;
let deleteModalInstance = null;
let facilityToDelete = null;

function openBookingModal() {
    if(!bookingModalInstance) bookingModalInstance = new bootstrap.Modal(document.getElementById('bookingModal'));
    resetBookingForm();
    bookingModalInstance.show();
}

function resetBookingForm() {
    const form = document.getElementById('booking-form');
    form.reset();
    form.querySelector('[name="action"]').value = 'sgvx51_book_facility';
    form.querySelector('[name="booking_id"]').value = '';
    form.closest('.modal-content').querySelector('.modal-title')?.textContent || (form.closest('.modal-content').querySelector('h5').textContent = 'Facility Booking');
    document.getElementById('booking-status-container').classList.add('d-none');
}

function resetFacilityForm() {
    const form = document.getElementById('facility-form');
    form.reset();
    form.querySelector('[name="action"]').value = 'sgvx51_add_facility';
    form.querySelector('[name="facility_id"]').value = '';
    form.querySelector('[name="booking_required"]').checked = true;
    document.getElementById('form-title').textContent = 'Define New Amenity';
    document.getElementById('submit-btn').textContent = 'Save Configuration';
    document.getElementById('cancel-edit-btn').classList.add('d-none');
    
    // Reset Pricing
    document.getElementById('pricePaid').checked = true;
    togglePricing('0');
}

function togglePricing(isFree) {
    const fields = document.getElementById('pricing-fields');
    if (isFree == '1') {
        fields.classList.add('d-none');
    } else {
        fields.classList.remove('d-none');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Search functionality is now handled by sgvx-search-init.js (Fuse.js)

    // 3. Edit Facility
    document.querySelectorAll('.js-edit-facility').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.facility);
            const form = document.getElementById('facility-form');
            
            form.querySelector('[name="action"]').value = 'sgvx51_edit_facility';
            form.querySelector('[name="facility_id"]').value = data.id;
            form.querySelector('[name="name"]').value = data.name;
            form.querySelector('[name="name"]').value = data.name;
            
            // Pricing Logic
            let rate = parseFloat(data.rate || 0);
            if (rate === 0) {
                document.getElementById('priceFree').checked = true;
                togglePricing('1');
                form.querySelector('[name="rate"]').value = 0;
            } else {
                document.getElementById('pricePaid').checked = true;
                togglePricing('0');
                form.querySelector('[name="rate"]').value = rate;
            }

            form.querySelector('[name="rate_unit"]').value = data.rate_unit || 'Hour';
            form.querySelector('[name="max_hours"]').value = data.max_hours;
            form.querySelector('[name="booking_required"]').checked = (data.booking_required != 0); // Handle string '0' or int 0
            form.querySelector('[name="rules"]').value = data.rules || '';
            
            document.getElementById('form-title').textContent = 'Modify Amenity Settings';
            document.getElementById('submit-btn').textContent = 'Update Policy';
            document.getElementById('cancel-edit-btn').classList.remove('d-none');
            
            form.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // 4. Delete Facility
    document.querySelectorAll('.js-delete-facility').forEach(btn => {
        btn.addEventListener('click', function() {
            facilityToDelete = this.dataset.id;
            if(!deleteModalInstance) deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModalInstance.show();
        });
    });

    // 5. Edit Booking
    document.querySelectorAll('.js-edit-booking').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.booking);
            const form = document.getElementById('booking-form');
            
            form.querySelector('[name="action"]').value = 'sgvx51_edit_booking';
            form.querySelector('[name="booking_id"]').value = data.id;
            form.querySelector('[name="facility_id"]').value = data.facility_id;
            form.querySelector('[name="resident_id"]').value = data.resident_id;
            form.querySelector('[name="start_time"]').value = data.start_time.replace(' ', 'T').substring(0, 16);
            form.querySelector('[name="end_time"]').value = data.end_time.replace(' ', 'T').substring(0, 16);
            form.querySelector('[name="status"]').value = data.status || 'confirmed';
            
            form.closest('.modal-content').querySelector('h5').textContent = 'Modify Reservation';
            document.getElementById('booking-status-container').classList.remove('d-none');
            
            if(!bookingModalInstance) bookingModalInstance = new bootstrap.Modal(document.getElementById('bookingModal'));
            bookingModalInstance.show();
        });
    });

    // 6. Delete Booking
    let bookingToDelete = null;
    let deleteBookingModalInstance = null;
    document.querySelectorAll('.js-delete-booking').forEach(btn => {
        btn.addEventListener('click', function() {
            bookingToDelete = this.dataset.id;
            if(!deleteBookingModalInstance) deleteBookingModalInstance = new bootstrap.Modal(document.getElementById('deleteBookingConfirmModal'));
            deleteBookingModalInstance.show();
        });
    });

    document.getElementById('confirm-delete-booking-btn')?.addEventListener('click', function() {
        if(!bookingToDelete) return;
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo admin_url("admin-post.php"); ?>';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'sgvx51_delete_booking';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = bookingToDelete;
        
        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = '_wpnonce';
        nonceInput.value = '<?php echo esc_js( wp_create_nonce("sgvx51_facility_nonce") ); ?>';
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        form.appendChild(nonceInput);
        document.body.appendChild(form);
        form.submit();
    });
});
</script>


