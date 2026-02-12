<?php
/**
 * Component: Dashboard Facilities & Assets Tab
 * @var array $data Dashboard data.
 */

// Segregate Facilities
$bookable = [];
$open_amenities = [];
foreach ($data['facilities'] as $f) {
    if (isset($f['booking_required']) && ($f['booking_required'] == 0 || $f['booking_required'] === '0')) {
        $open_amenities[] = $f;
    } else {
        $bookable[] = $f;
    }
}
$assets = $data['assets'] ?? [];
?>
<!-- 3. AMENITIES TAB -->
<div id="tab-facilities" class="tab-content d-none">
    
    <!-- Inner Tabs Navigation -->
    <ul class="nav nav-pills mb-4 gap-2" id="amenities-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active rounded-pill px-4 fw-medium small" id="pills-bookable-tab" data-bs-toggle="pill" data-bs-target="#pills-bookable" type="button" role="tab" aria-selected="true">
                <i class="bi bi-calendar-check me-2"></i>Bookable Facilities
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-medium small" id="pills-open-tab" data-bs-toggle="pill" data-bs-target="#pills-open" type="button" role="tab" aria-selected="false">
                <i class="bi bi-tree me-2"></i>Open Amenities
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link rounded-pill px-4 fw-medium small" id="pills-assets-tab" data-bs-toggle="pill" data-bs-target="#pills-assets" type="button" role="tab" aria-selected="false">
                <i class="bi bi-box-seam me-2"></i>Society Assets
            </button>
        </li>
    </ul>

    <div class="tab-content" id="amenities-tabContent">
        
        <!-- 1. Bookable Facilities -->
        <div class="tab-pane fade show active" id="pills-bookable" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 fw-bold text-dark m-0 text-uppercase tracking-wider">Reserve a Spot</h3>
                    </div>
                    <?php if (empty($bookable)) : ?>
                        <div class="text-center py-5 border rounded-3 bg-light">
                            <p class="text-muted small m-0">No bookable facilities available.</p>
                        </div>
                    <?php else : ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($bookable as $f) : ?>
                                <div class="facility-card bg-white rounded-3 border border-light p-3 shadow-sm d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo esc_html($f['name']); ?></div>
                                        <div class="small text-secondary">
                                            ₹<?php echo sgvx_in_fmt($f['rate'] ?? 0); ?> / <?php echo esc_html($f['rate_unit'] ?? 'hr'); ?>
                                            <span class="mx-1">•</span> <i class="bi bi-clock"></i> Max <?php echo esc_html($f['max_hours']); ?>h
                                        </div>
                                    </div>
                                    <button class="js-open-booking btn btn-sm btn-primary rounded-pill px-3 shadow-sm fw-medium" 
                                            data-facility-id="<?php echo esc_attr($f['id']); ?>"
                                            data-facility-name="<?php echo esc_attr($f['name']); ?>">Book</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8">
                    <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden h-100">
                        <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                            <span class="fw-semibold text-dark small text-uppercase">My Bookings</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light text-secondary text-uppercase small">
                                    <tr><th class="ps-4 border-0">Facility</th><th class="border-0">Date</th><th class="pe-4 border-0">Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($data['my_bookings'])) : ?>
                                        <tr><td colspan="3" class="text-center py-5 text-muted small">No booking history relative to your unit.</td></tr>
                                    <?php else : ?>
                                        <?php foreach ($data['my_bookings'] as $b) : 
                                            $fac_name = 'Unknown';
                                            foreach($data['facilities'] as $fa) { if($fa['id'] == $b['facility_id']) $fac_name = $fa['name']; }
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-medium text-dark"><?php echo esc_html($fac_name); ?></td>
                                            <td class="text-secondary small"><?php echo date('D, M j, H:i', strtotime($b['start_time'])); ?></td>
                                            <td class="pe-4">
                                                <?php 
                                                    $s_raw = strtolower($b['status'] ?? 'pending');
                                                    $s_class = 'bg-success text-success';
                                                    if ($s_raw === 'pending') $s_class = 'bg-warning text-warning';
                                                    if ($s_raw === 'rejected') $s_class = 'bg-danger text-danger';
                                                    if ($s_raw === 'cancelled') $s_class = 'bg-secondary text-secondary';
                                                ?>
                                                <span class="badge <?php echo $s_class; ?> bg-opacity-10 border border-current border-opacity-10 rounded-pill px-2" style="font-size: 10px;"><?php echo esc_html($b['status']); ?></span>
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

        <!-- 2. Open Amenities -->
        <div class="tab-pane fade" id="pills-open" role="tabpanel">
            <h3 class="h6 fw-bold text-dark mb-4 text-uppercase tracking-wider">Open for Everyone</h3>
            <?php if (empty($open_amenities)) : ?>
                <div class="text-center py-5 border rounded-3 bg-light">
                    <p class="text-muted small m-0">No open amenities listed.</p>
                </div>
            <?php else : ?>
                <div class="row g-4">
                    <?php foreach ($open_amenities as $oz) : ?>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100 rounded-3 overflow-hidden">
                                <div class="card-body p-4 text-center">
                                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 50px; height: 50px;">
                                        <i class="bi bi-tree fs-4"></i>
                                    </div>
                                    <h5 class="fw-bold text-dark mb-2"><?php echo esc_html($oz['name']); ?></h5>
                                    <?php if(!empty($oz['rules'])): ?>
                                        <p class="text-secondary small mb-3 text-truncate" title="<?php echo esc_attr($oz['rules']); ?>"><?php echo esc_html($oz['rules']); ?></p>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-secondary border border-light rounded-pill px-3 py-2 fw-normal small">
                                        <i class="bi bi-clock me-1"></i> Open Access
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Assets -->
        <div class="tab-pane fade" id="pills-assets" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                 <h3 class="h6 fw-bold text-dark m-0 text-uppercase tracking-wider">Society Inventory</h3>
                 <div class="input-group input-group-sm" style="width: 250px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="asset-search" class="form-control border-start-0 shadow-none" placeholder="Search assets...">
                 </div>
            </div>

            <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light text-secondary text-uppercase small">
                            <tr>
                                <th class="ps-4 border-0 py-3">Asset Name</th>
                                <th class="border-0 py-3">Category</th>
                                <th class="border-0 py-3">Value</th>
                                <th class="pe-4 border-0 py-3">Status</th>
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
                                    <td class="text-secondary small">₹<?php echo sgvx_in_fmt($a['value']); ?></td>
                                    <td class="pe-4">
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

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple Search for Assets
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
</script>
