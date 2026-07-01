<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Facilities & Assets Tab (Premium Sidebar Layout)
 * @var array $data Dashboard data.
 */

$assets = $data['assets'] ?? [];
$my_bookings = $data['my_bookings'] ?? [];
?>
<!-- FACILITIES & ASSETS MAIN TAB CONTAINER -->
<div id="tab-facilities" class="d-none">
    
    <!-- Main Card Container -->
    <div class="card border-secondary-subtle shadow-sm overflow-hidden rounded-4" style="min-height: 600px;">
        <div class="row g-0 h-100 d-flex align-items-stretch">
            
            <!-- LEFT: Sidebar Navigation -->
            <div class="col-lg-3 col-xl-2 bg-light border-end" id="facilitiesLeftNav">
                <div class="d-flex flex-column h-100 py-4">
                    <h6 class="px-4 text-uppercase text-secondary small fw-bold tracking-wider mb-3">Menu</h6>
                    
                    <div class="nav flex-column nav-pills me-0" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                        
                        <!-- 1. Assets -->
                        <button class="nav-link active text-start py-3 px-4 rounded-0 border-start border-4 border-transparent fw-medium text-dark d-flex align-items-center gap-3 transition-all" 
                                id="v-pills-assets-tab" data-bs-toggle="pill" data-bs-target="#v-pills-assets" type="button" role="tab">
                            <i class="bi bi-box-seam fs-5"></i>
                            <span>Inventory</span>
                        </button>

                        <!-- 2. Amenities -->
                        <button class="nav-link text-start py-3 px-4 rounded-0 border-start border-4 border-transparent fw-medium text-dark d-flex align-items-center gap-3 transition-all" 
                                id="v-pills-explore-tab" data-bs-toggle="pill" data-bs-target="#v-pills-explore" type="button" role="tab">
                            <i class="bi bi-grid-fill fs-5"></i>
                            <span>Amenities</span>
                        </button>

                        <!-- 3. My Bookings -->
                        <button class="nav-link text-start py-3 px-4 rounded-0 border-start border-4 border-transparent fw-medium text-dark d-flex align-items-center gap-3 transition-all" 
                                id="v-pills-my-bookings-tab" data-bs-toggle="pill" data-bs-target="#v-pills-my-bookings" type="button" role="tab">
                            <i class="bi bi-calendar-check fs-5"></i>
                            <span>My Bookings</span>
                        </button>
                    </div>

                    <div class="mt-auto px-4 pt-4">
                        <div class="p-3 bg-white rounded-3 border border-light text-center">
                            <small class="text-muted d-block mb-2">Need help?</small>
                            <button class="btn btn-xs btn-outline-secondary rounded-pill px-3" onclick="document.querySelector('[data-bs-target=\'#tab-requests\']').click()">Contact Admin</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Tab Content -->
            <div class="col-lg-9 col-xl-10 bg-white">
                <div class="tab-content h-100 p-4 p-lg-5" id="v-pills-tabContent">
                    
                    <!-- 1. ASSETS TAB -->
                    <div class="tab-pane fade show active" id="v-pills-assets" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="h5 fw-bold text-dark m-0">Society Inventory</h4>
                                <p class="text-muted small m-0">List of common assets owned by the society.</p>
                            </div>
                            <!-- Search -->
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" id="asset-search" class="form-control border-start-0 shadow-none" placeholder="Search assets...">
                            </div>
                        </div>

                        <div class="border rounded-3 overflow-hidden">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="bg-light text-secondary text-uppercase small">
                                        <tr>
                                            <th class="ps-4 border-0 py-3">Asset Name</th>
                                            <th class="border-0 py-3">Category</th>
                                            <th class="border-0 py-3">Value</th>
                                            <th class="pe-4 border-0 py-3 text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="assets-table-body">
                                        <?php if (empty($assets)) : ?>
                                            <tr><td colspan="4" class="text-center py-5 text-muted small">No assets recorded.</td></tr>
                                        <?php else : ?>
                                            <?php foreach ($assets as $a) : 
                                                $search_str = strtolower($a['name'] . ' ' . ($a['category']??''));
                                            ?>
                                            <tr class="asset-row" data-search="<?php echo esc_attr($search_str); ?>">
                                                <td class="ps-4 fw-medium text-dark">
                                                    <?php echo esc_html($a['name']); ?>
                                                    <?php if(!empty($a['amc_provider'])): ?>
                                                        <div class="small text-muted" style="font-size: 10px;">AMC: <?php echo esc_html($a['amc_provider']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-secondary small"><?php echo esc_html($a['category']); ?></td>
                                                <td class="text-secondary small">₹<?php echo SNESTX_in_fmt($a['value']); ?></td>
                                                <td class="pe-4 text-end">
                                                    <span class="badge bg-light text-dark border border-light rounded-pill px-2 border-opacity-10 small fw-normal"><?php echo esc_html($a['status']); ?></span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- 2. AMENITIES TAB -->
                    <div class="tab-pane fade" id="v-pills-explore" role="tabpanel">
                         <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="h5 fw-bold text-dark m-0">Explore Amenities</h4>
                                <p class="text-muted small m-0">Book facilities or find open recreational areas.</p>
                            </div>
                            <!-- Filters -->
                             <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-sm btn-dark rounded-pill px-3 fw-medium filter-btn active" data-filter="all">All</button>
                                <button class="btn btn-sm btn-light border border-light rounded-pill px-3 fw-medium filter-btn" data-filter="free">Free</button>
                                <button class="btn btn-sm btn-light border border-light rounded-pill px-3 fw-medium filter-btn" data-filter="paid">Paid</button>
                            </div>
                        </div>

                        <?php if ( empty( $data['facilities'] ) ) : ?>
                            <div class="text-center py-5 border rounded-3 bg-light">
                                <i class="bi bi-wind fs-1 d-block opacity-25 mb-3"></i>
                                <p class="text-muted small m-0">No facilities found.</p>
                            </div>
                        <?php else : ?>
                            <div class="row g-4" id="facilities-grid">
                                <?php foreach ( $data['facilities'] as $f ) : 
                                    $rate = floatval($f['rate'] ?? 0);
                                    $is_free = ($rate === 0.0);
                                    $type = $is_free ? 'free' : 'paid';
                                    $f_json = htmlspecialchars(json_encode($f), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <div class="col-md-6 col-xl-4 facility-item" data-type="<?php echo $type; ?>">
                                        <div class="card h-100 border border-light shadow-sm rounded-3 overflow-hidden hover-lift transition-all" role="button" onclick="openResidentFacilityModal('<?php echo $f_json; ?>')">
                                            <!-- Header Image Area -->
                                            <div class="bg-gradient-primary-soft d-flex align-items-center justify-content-center position-relative" style="height: 120px; background-color: #f8f9fa;">
                                                 <i class="bi bi-building fs-1 text-primary opacity-25"></i>
                                                 <?php if($is_free): ?>
                                                    <span class="position-absolute top-0 end-0 m-3 badge bg-success bg-opacity-75 rounded-pill shadow-sm small">Free</span>
                                                 <?php else: ?>
                                                    <span class="position-absolute top-0 end-0 m-3 badge bg-primary bg-opacity-75 rounded-pill shadow-sm small">Paid</span>
                                                 <?php endif; ?>
                                            </div>
                                            
                                            <div class="card-body p-3">
                                                <h6 class="fw-bold text-dark m-0 mb-1 text-truncate"><?php echo esc_html($f['name']); ?></h6>
                                                <div class="d-flex align-items-center small text-muted mb-3">
                                                     <?php if(!$is_free): ?>
                                                        <span class="fw-bold text-primary me-2">₹<?php echo $rate; ?>/<?php echo esc_html($f['rate_unit']??'hr'); ?></span>
                                                     <?php endif; ?>
                                                     <span class="text-truncate"><i class="bi bi-clock me-1"></i>Max <?php echo esc_html($f['max_hours']); ?>h</span>
                                                </div>

                                                <?php if(!empty($f['rules'])): ?>
                                                    <p class="text-secondary small mb-0 line-clamp-2" style="font-size: 0.85rem;"><?php echo esc_html($f['rules']); ?></p>
                                                <?php else: ?>
                                                    <p class="text-muted small mb-0 fst-italic opacity-50" style="font-size: 0.85rem;">No rules listed.</p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-footer bg-white border-top-0 p-3 pt-0">
                                                <button class="btn btn-outline-primary w-100 rounded-3 shadow-none fw-medium btn-sm" style="font-size: 0.85rem;">Details & Booking</button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. MY BOOKINGS TAB -->
                    <div class="tab-pane fade" id="v-pills-my-bookings" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="h5 fw-bold text-dark m-0">My Bookings</h4>
                                <p class="text-muted small m-0">Track status of your facility reservations.</p>
                            </div>
                        </div>

                        <div class="border rounded-3 overflow-hidden">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                     <thead class="bg-light text-secondary text-uppercase small">
                                        <tr>
                                            <th class="ps-4 border-0 py-3">Facility</th>
                                            <th class="border-0 py-3">Schedule</th>
                                            <th class="pe-4 border-0 py-3 text-end">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_bookings)) : ?>
                                            <tr><td colspan="3" class="text-center py-5 text-muted small">No active bookings found.</td></tr>
                                        <?php else : ?>
                                            <?php foreach ($my_bookings as $b) : 
                                                $fac_name = 'Unknown';
                                                foreach($data['facilities'] as $fa) { if($fa['id'] == $b['facility_id']) $fac_name = $fa['name']; }
                                                
                                                $s_raw = strtolower($b['status'] ?? 'pending');
                                                $s_class = 'bg-success text-success';
                                                if ($s_raw === 'pending') $s_class = 'bg-warning text-warning';
                                                if ($s_raw === 'rejected') $s_class = 'bg-danger text-danger';
                                                if ($s_raw === 'cancelled') $s_class = 'bg-secondary text-secondary';
                                            ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark"><?php echo esc_html($fac_name); ?></td>
                                                <td class="text-secondary small">
                                                    <div><?php echo wp_date('D, M j, Y', strtotime($b['start_time'])); ?></div>
                                                    <div class="text-primary"><?php echo wp_date('h:i A', strtotime($b['start_time'])); ?> - <?php echo wp_date('h:i A', strtotime($b['end_time'])); ?></div>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <span class="badge <?php echo $s_class; ?> bg-opacity-10 border border-current border-opacity-10 rounded-pill px-2 small fw-normal"><?php echo esc_html($b['status']); ?></span>
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
        </div>
    </div>
</div>

<!-- RESIDENT FACILITY DETAILS MODAL (Same as before) -->
<div class="modal fade" id="residentFacilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="modalFacName">Facility Details</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Details -->
                    <div class="col-lg-5 border-end border-light">
                        <div class="bg-light rounded-3 p-3 mb-4 text-center">
                            <i class="bi bi-building fs-1 text-primary opacity-50"></i>
                            <div class="small text-muted mt-2" id="modalFacMeta"></div>
                        </div>

                        <h6 class="fw-bold text-dark small text-uppercase mb-2">Guidelines & Rules</h6>
                        <div class="p-3 bg-light rounded-3 border border-light mb-4">
                            <p class="small text-secondary m-0" id="modalFacRules" style="white-space: pre-wrap;">No specific rules.</p>
                        </div>
                        
                        <div id="modalBookingFormContainer" class="d-none">
                            <h6 class="fw-bold text-dark small text-uppercase mb-2">Book This Facility</h6>
                            <form id="residentBookingForm" onsubmit="handleResidentBooking(event)">
                                <input type="hidden" name="action" value="SNESTX51_book_facility">
                                <input type="hidden" name="facility_id" id="bookingFacId">
                                <!-- Secure Nonce for Resident Booking -->
                                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('SNESTX51_facility_nonce'); ?>">
                                <input type="hidden" name="resident_id" value="<?php echo esc_attr($data['resident']['flat_no'] ?? ''); ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">Start Time</label>
                                    <input type="datetime-local" name="start_time" class="form-control shadow-none rounded-3 border-light" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-secondary">End Time</label>
                                    <input type="datetime-local" name="end_time" class="form-control shadow-none rounded-3 border-light" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 shadow-sm fw-bold rounded-3">Confirm Booking</button>
                            </form>
                        </div>
                        <div id="modalNoBookingMsg" class="alert alert-success bg-opacity-10 border-0 d-none">
                            <i class="bi bi-check-circle-fill me-2"></i> No booking required. This facility is open for all residents.
                        </div>
                    </div>

                    <!-- Right: Schedule -->
                    <div class="col-lg-7">
                        <h6 class="fw-bold text-dark small text-uppercase mb-3 d-flex justify-content-between">
                            <span>Current Schedule</span>
                            <span class="badge bg-light text-dark fw-normal border border-light">Next 7 Days</span>
                        </h6>
                        
                        <!-- Simple List View of Schedule -->
                        <div id="modalScheduleList" class="list-group list-group-flush border rounded-3 overflow-hidden">
                            <div class="text-center py-5">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span> Loading schedule...
                            </div>
                        </div>
                        <div class="form-text small mt-2"><i class="bi bi-info-circle me-1"></i> Slots shown are currently reserved by other residents.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom Styles for Sidebar Tabs */
#v-pills-tab .nav-link {
    color: #495057;
    position: relative;
    border-bottom: 1px solid #f8f9fa; /* Subtle separator */
}
#v-pills-tab .nav-link:hover {
    background-color: #f8f9fa;
    color: #212529;
}
#v-pills-tab .nav-link.active {
    background-color: #e9ecef !important;
    color: #0d6efd !important;
    border-left-color: #0d6efd !important;
    font-weight: 600 !important;
}
/* Ensure Card height matches content or min-height */
#facilitiesLeftNav {
    min-height: 600px;
}
</style>

<script>
let facilityModal = null;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Filter Logic
    const filters = document.querySelectorAll('.filter-btn');
    const items = document.querySelectorAll('.facility-item');
    
    // Check if elements exist to avoid errors on other pages
    if(filters.length > 0 && items.length > 0) {
        filters.forEach(btn => {
            btn.addEventListener('click', () => {
                // UI Toggle
                filters.forEach(b => {
                    b.classList.remove('btn-dark', 'active');
                    b.classList.add('btn-light');
                });
                btn.classList.remove('btn-light');
                btn.classList.add('btn-dark', 'active');
                
                const filter = btn.dataset.filter;
                items.forEach(item => {
                    if (filter === 'all' || item.dataset.type === filter) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });
            });
        });
    }

    // 2. Asset Search
    const assetSearch = document.getElementById('asset-search');
    if(assetSearch) {
        assetSearch.addEventListener('keyup', function() {
            const val = this.value.toLowerCase();
            document.querySelectorAll('.asset-row').forEach(row => {
                row.style.display = row.dataset.search.includes(val) ? '' : 'none';
            });
        });
    }
});

function openResidentFacilityModal(jsonStr) {
    if(!facilityModal) facilityModal = new bootstrap.Modal(document.getElementById('residentFacilityModal'));
    
    // Parse
    let f;
    try {
        f = JSON.parse(jsonStr);
    } catch(e) { console.error('JSON Parse Error', e); return; }
    
    // Populate Info
    document.getElementById('modalFacName').textContent = f.name;
    document.getElementById('modalFacRules').textContent = f.rules || 'No specific guidelines provided.';
    
    // Meta
    const rate = parseFloat(f.rate || 0);
    const costText = (rate === 0) ? 'Free Access' : `₹${rate}/${f.rate_unit||'hr'}`;
    document.getElementById('modalFacMeta').innerHTML = `
        <span class="fw-bold text-dark">${costText}</span> • Max ${f.max_hours}hrs • ${f.booking_required == 1 ? 'Booking Required' : 'Open Access'}
    `;

    // Booking Logic
    const formContainer = document.getElementById('modalBookingFormContainer');
    const noBookingMsg = document.getElementById('modalNoBookingMsg');
    const bookingFacId = document.getElementById('bookingFacId');
    
    bookingFacId.value = f.id;
    
    if(f.booking_required != 0) { // Check for loose equality or specific value
        formContainer.classList.remove('d-none');
        noBookingMsg.classList.add('d-none');
    } else {
        formContainer.classList.add('d-none');
        noBookingMsg.classList.remove('d-none');
    }

    // Clear Previous Schedule & Fetch New
    const scheduleList = document.getElementById('modalScheduleList');
    scheduleList.innerHTML = '<div class="text-center py-5 text-muted small"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Loading availability...</div>';
    
    facilityModal.show();
    
    fetchFacilitySchedule(f.id);
}

// Fetch Schedule
async function fetchFacilitySchedule(facId) {
    const list = document.getElementById('modalScheduleList');
    try {
        const response = await fetch(`${ajaxurl}?action=SNESTX51_get_facility_bookings&facility_id=${facId}`);
        const res = await response.json();
        
        if(res.success) {
            const events = res.data;
            if(events.length === 0) {
                list.innerHTML = '<div class="list-group-item text-center py-4 text-muted small">No upcoming bookings. Entire schedule available.</div>';
                return;
            }
            
            // Sort by start time
            events.sort((a,b) => new wp_date(a.start) - new wp_date(b.start));
            
            let html = '';
            events.forEach(e => {
                const startDate = new wp_date(e.start);
                const endDate = new wp_date(e.end);
                
                // Format: Mon, 12 Feb | 10:00 AM - 12:00 PM
                const day = startDate.toLocaleDateString('en-US', {weekday:'short', day:'numeric', month:'short'});
                const timeStr = `${startDate.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})} - ${endDate.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}`;
                
                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark small">${day}</div>
                            <div class="text-secondary" style="font-size: 11px;">${timeStr}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark border border-light rounded-pill px-2 border-opacity-10 small fw-normal">Reserved</span>
                            <div class="small text-muted" style="font-size: 10px;">${e.title}</div>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        } else {
            list.innerHTML = `<div class="p-3 text-danger small">Failed to load schedule.</div>`;
        }
    } catch(e) {
        console.error(e);
        list.innerHTML = `<div class="p-3 text-danger small">Error loading schedule.</div>`;
    }
}

// Handle Booking
async function handleResidentBooking(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const originalText = btn.textContent;
    btn.textContent = 'Processing...';
    btn.disabled = true;

    const fd = new FormData(e.target);
    
    try {
        const res = await window.SNESTXApiRequest('SNESTX51_book_facility', fd);
        // On Success (SNESTXApiRequest throws on error)
        btn.textContent = 'Success!';
        setTimeout(() => {
            facilityModal.hide();
            e.target.reset();
            btn.textContent = originalText;
            btn.disabled = false;
            // Optional: location.reload() or refresh bookings list
            window.location.reload(); 
        }, 1000);
    } catch(err) {
        // SNESTXShowToast already handled the alert
        btn.textContent = originalText;
        btn.disabled = false;
    }
}
</script>
