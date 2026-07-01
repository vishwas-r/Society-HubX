<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View: Assets (Bootstrap Migration)
 * Integrates directly with SHUBX51_DB_Router.
 */

$db = new SHUBX51_DB_Router();
$assets = $db->get( 'assets' );

$success_msg = isset( $_GET['success'] ) ? 'Asset registry updated.' : '';
?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Asset Management</h1>
        <p class="text-secondary m-0 mt-1">Track society inventory, machine warranties, and AMC contracts.</p>
    </div>

    <!-- Alert Message -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Asset Registry Updated</div>
                    <div class="small opacity-75"><?php echo esc_html( $success_msg ); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="assetSearch" placeholder="Search by asset name, provider..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <!-- <button class="js-toggle-filters btn btn-light px-4 fw-semibold border-0 bg-light text-secondary rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                        <i class="bi bi-funnel"></i>
                        <span>Filters</span>
                    </button> -->
                    <button onclick="openAddAssetModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                        <i class="bi bi-plus-circle fs-5"></i>
                        <span>Add Asset</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs (Integrated) -->
        <div class="px-5 bg-white border-bottom border-light">
            <ul class="nav nav-tabs border-0 gap-5" id="assetTabs">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" data-tab="all" onclick="switchTab('all')" style="background:none;">All Assets</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="active" onclick="switchTab('active')" style="background:none;">Operational</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" data-tab="archived" onclick="switchTab('archived')" style="background:none;">Archive</button>
                </li>
            </ul>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light border-bottom border-light">
                    <tr>
                         <th class="ps-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Asset Name & Purchase</th>
                         <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">Warranty Status</th>
                         <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider">AMC Details</th>
                         <th class="px-4 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-center">Status</th>
                         <th class="pe-5 py-4 text-uppercase small text-secondary fw-bold border-0 tracking-wider text-end">Operations</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $assets ) ) : ?>
                         <tr><td colspan="5" class="px-4 py-12 text-center text-muted">No assets found in the registry.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $assets as $a ) : 
                            $status = $a['status'] ?? 'Active';
                        ?>
                            <tr class="asset-row" data-status="<?php echo esc_attr(strtolower($status)); ?>" data-search="<?php echo esc_attr(strtolower(($a['name'] ?? '') . ' ' . ($a['amc_provider'] ?? ''))); ?>">
                                <td class="ps-5 py-4">
                                    <div class="fw-bold text-dark small"><?php echo esc_html( $a['name'] ); ?></div>
                                    <div class="text-muted" style="font-size: 10px;">Purchased: <?php echo esc_html( $a['purchase_date'] ?: 'N/A' ); ?></div>
                                </td>
                                <td class="px-4 py-4">
                                         <?php if ( !empty($a['warranty_expiry']) ) : ?>
                                        <?php if ( wp_date( 'Y-m-d' ) > $a['warranty_expiry'] ) : ?>
                                            <span class="text-danger small fw-bold d-flex align-items-center gap-1">
                                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 14px;"></i> EXPIRED (<?php echo wp_date('M Y', strtotime($a['warranty_expiry'])); ?>)
                                            </span>
                                        <?php else : ?>
                                            <span class="text-success small fw-bold d-flex align-items-center gap-1">
                                                <i class="bi bi-check-circle-fill" style="font-size: 14px;"></i> VALID UNTIL <?php echo wp_date('M Y', strtotime($a['warranty_expiry'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted opacity-50 small">NO DATA</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <?php if(!empty($a['amc_provider'])): ?>
                                        <div class="small fw-bold text-secondary"><?php echo esc_html( $a['amc_provider'] ); ?></div>
                                        <div class="text-muted" style="font-size: 10px;"><?php echo esc_html( $a['amc_phone'] ); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted opacity-50 small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php 
                                    $status_cls = 'bg-secondary bg-opacity-10 text-secondary';
                                    if(strtolower($status) === 'active') $status_cls = 'bg-success bg-opacity-10 text-success';
                                    if(strtolower($status) === 'under repair') $status_cls = 'bg-warning bg-opacity-10 text-dark';
                                    if(strtolower($status) === 'scrapped') $status_cls = 'bg-danger bg-opacity-10 text-danger';
                                    ?>
                                    <span class="badge <?php echo $status_cls; ?> px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;"><?php echo esc_html( $status ); ?></span>
                                </td>
                                <td class="pe-5 py-4 text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <?php if (strtolower($status) !== 'archived'): ?>
                                            <button onclick='openEditAssetModal(<?php echo esc_attr( wp_json_encode($a) ); ?>)' class="btn btn-sm btn-light border border-light p-2 rounded-3 shadow-none">
                                                <i class="bi bi-pencil-square fs-6 text-muted"></i>
                                            </button>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=shubx51_delete_asset&id=' . $a['id']), 'shubx51_delete_asset_nonce' ); ?>" 
                                            onclick="return confirm('Archive this asset registry entry?');"
                                            class="btn btn-sm btn-light border border-light p-2 text-danger rounded-3 shadow-none">
                                                <i class="bi bi-archive-fill fs-6"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=shubx51_restore_asset&id=' . $a['id']), 'shubx51_restore_asset_nonce' ); ?>" 
                                            class="btn btn-sm btn-outline-success px-3 fw-bold rounded-pill shadow-none" style="font-size: 10px;">
                                                RESTORE
                                            </a>
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
add_action('shubx51_admin_modals', function() {
?>
<!-- Asset Form Modal (Add/Edit) -->
<div class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="assetModalTitle">Register Asset</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="asset-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" id="asset-form-action" value="shubx51_add_asset">
                    <input type="hidden" name="asset_id" id="asset-id" value="">
                    <?php wp_nonce_field( 'shubx51_asset_action' ); ?>
                    <input type="hidden" name="_wp_http_referer" value="<?php echo admin_url( 'admin.php?page=shubx51-assets' ); ?>">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Asset Name / ID</label>
                            <input type="text" name="name" id="asset-name" class="form-control shadow-none rounded-3" placeholder="e.g. Lift Block B Core" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Category</label>
                            <select name="category" id="asset-category" class="form-select shadow-none rounded-3">
                                <option value="Machinery">Machinery</option>
                                <option value="Furniture">Furniture</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Sports/Gym">Sports/Gym</option>
                                <option value="Safety">Safety</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Purchase Date</label>
                            <input type="date" name="purchase_date" id="asset-purchase-date" class="form-control shadow-none rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Warranty Expiry</label>
                            <input type="date" name="warranty_expiry" id="asset-warranty-expiry" class="form-control shadow-none rounded-3">
                        </div>

                        <div class="col-12">
                             <label class="form-label small fw-bold text-secondary text-uppercase">Service Provider / AMC Company</label>
                             <input type="text" name="amc_provider" id="asset-amc-provider" class="form-control shadow-none rounded-3" placeholder="e.g. Otis Elevators">
                        </div>
                         
                         <div class="col-md-4">
                             <label class="form-label small fw-bold text-secondary text-uppercase">Service Contact No.</label>
                             <input type="text" name="amc_phone" id="asset-amc-phone" class="form-control shadow-none rounded-3">
                         </div>
                         <div class="col-md-4">
                             <label class="form-label small fw-bold text-secondary text-uppercase">Purchase Value (₹)</label>
                             <input type="number" name="value" id="asset-value" class="form-control shadow-none rounded-3" step="0.01">
                         </div>
                         <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase">Operational Status</label>
                            <select name="status" id="asset-status" class="form-select shadow-none rounded-3">
                                <option value="Active">Operational (Active)</option>
                                <option value="Under Repair">Maintenance Mode</option>
                                <option value="Scrapped">Decommissioned</option>
                            </select>
                         </div>
                         <div class="col-12">
                             <label class="form-label small fw-bold text-secondary text-uppercase">Notes / Description</label>
                             <textarea name="description" id="asset-description" class="form-control shadow-none rounded-3" rows="2"></textarea>
                         </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3" id="asset-submit-btn">Register Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
let assetModalInstance = null;
let currentTab = 'all';
let fuse = null;

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.nav-link').forEach(btn => {
        if (btn.dataset.tab === tab) {
            btn.classList.add('active', 'border-primary', 'text-primary');
            btn.classList.remove('border-transparent', 'text-muted');
        } else {
            btn.classList.remove('active', 'border-primary', 'text-primary');
            btn.classList.add('border-transparent', 'text-muted');
        }
    });
    applyFilters();
}

function applyFilters() {
    const searchInput = document.getElementById('assetSearch');
    const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';
    
    if (!fuse && window.SHUBXCreateFuse) {
        fuse = window.SHUBXCreateFuse('.asset-row');
    }

    const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(fuse, searchVal) : null;
    
    console.log('[ASSET DEBUG] searchVal:', searchVal);
    console.log('[ASSET DEBUG] fuzzyMatches type:', fuzzyMatches ? fuzzyMatches.constructor.name : 'null');
    console.log('[ASSET DEBUG] fuzzyMatches size:', fuzzyMatches ? fuzzyMatches.size : 'N/A');
    
    if (fuzzyMatches && fuzzyMatches.size > 0) {
        console.log('[ASSET DEBUG] First item in Set:', Array.from(fuzzyMatches)[0]);
    }

    document.querySelectorAll('.asset-row').forEach(row => {
        const status = row.dataset.status;
        let matchTab = false;
        if (currentTab === 'archived') {
            matchTab = (status === 'archived');
        } else if (currentTab === 'active') {
            matchTab = (status === 'active' || status === 'operational');
        } else if (currentTab === 'all') {
            matchTab = (status !== 'archived');
        } else {
            matchTab = (status === currentTab);
        }
        
        const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));
        
        if (searchVal) {
            console.log('[ASSET DEBUG] Row:', row, 'matchTab:', matchTab, 'matchSearch:', matchSearch, 'inSet:', fuzzyMatches ? fuzzyMatches.has(row) : 'N/A');
        }
        
        // Use Bootstrap d-none class for consistency
        if (matchTab && matchSearch) {
            row.classList.remove('d-none');
            row.style.display = ''; // Clear any inline styles
        } else {
            row.classList.add('d-none');
        }
    });
    
    if (searchVal && fuzzyMatches) {
        console.log(`Asset Search: Found ${fuzzyMatches.size} matches for "${searchVal}"`);
    }
}

document.getElementById('assetSearch').addEventListener('input', applyFilters);
document.getElementById('assetSearch').addEventListener('focus', function() {
    if (window.SHUBXCreateFuse) fuse = window.SHUBXCreateFuse('.asset-row');
});

function openAddAssetModal() {
    const form = document.getElementById('asset-form');
    form.reset();
    document.getElementById('asset-form-action').value = 'shubx51_add_asset';
    document.getElementById('asset-id').value = '';
    document.getElementById('assetModalTitle').textContent = 'Register New Asset';
    document.getElementById('asset-submit-btn').textContent = 'Register Asset';
    
    if(!assetModalInstance) assetModalInstance = new bootstrap.Modal(document.getElementById('assetModal'));
    assetModalInstance.show();
}

function openEditAssetModal(asset) {
    const form = document.getElementById('asset-form');
    document.getElementById('asset-form-action').value = 'shubx51_edit_asset';
    document.getElementById('asset-id').value = asset.id;
    document.getElementById('asset-name').value = asset.name;
    document.getElementById('asset-purchase-date').value = asset.purchase_date;
    document.getElementById('asset-warranty-expiry').value = asset.warranty_expiry;
    document.getElementById('asset-amc-provider').value = asset.amc_provider;
    document.getElementById('asset-amc-phone').value = asset.amc_phone || '';
    document.getElementById('asset-category').value = asset.category || 'Machinery';
    document.getElementById('asset-value').value = asset.value || '';
    document.getElementById('asset-description').value = asset.description || '';
    document.getElementById('asset-status').value = asset.status;
    
    document.getElementById('assetModalTitle').textContent = 'Modify Asset Details';
    document.getElementById('asset-submit-btn').textContent = 'Save Changes';

    if(!assetModalInstance) assetModalInstance = new bootstrap.Modal(document.getElementById('assetModal'));
    assetModalInstance.show();
}
</script>

