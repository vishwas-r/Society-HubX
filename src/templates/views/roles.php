<?php
/**
 * View: Roles & Permissions (RBAC)
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rbac = new SHUBX51_RBAC_Manager();
$roles = $rbac->get_all_roles();
$available_caps = SHUBX51_RBAC_Manager::get_available_capabilities();

$capability_groups = array(
    'governance' => array(
        'title' => 'Core Governance & System',
        'icon'  => 'bi-shield-shaded',
        'desc'  => 'Executive metrics, approval workflows, and system settings.',
        'caps'  => array(
            'dashboard_view'   => array('label' => 'View Executive Dashboard', 'type' => 'view'),
            'requests_view'    => array('label' => 'View Approval Requests',   'type' => 'view'),
            'requests_manage'  => array('label' => 'Manage Approval Requests', 'type' => 'manage'),
            'settings_manage'  => array('label' => 'Manage Plugin Settings',   'type' => 'manage'),
        ),
    ),
    'residents' => array(
        'title' => 'Residents, Flats & Vehicles',
        'icon'  => 'bi-people',
        'desc'  => 'Manage members, tenant records, property units, and parking.',
        'caps'  => array(
            'residents_view'   => array('label' => 'View Residents List',       'type' => 'view'),
            'residents_manage' => array('label' => 'Add/Edit/Delete Residents', 'type' => 'manage'),
            'flats_view'       => array('label' => 'View Flats & Units',        'type' => 'view'),
            'flats_manage'     => array('label' => 'Manage Flats & Units',      'type' => 'manage'),
            'vehicles_view'    => array('label' => 'View Vehicle Registry',     'type' => 'view'),
            'vehicles_manage'  => array('label' => 'Manage Vehicle Registry',   'type' => 'manage'),
        ),
    ),
    'finances' => array(
        'title' => 'Finances, Invoices & Assets',
        'icon'  => 'bi-wallet2',
        'desc'  => 'Accounting ledgers, payment tracking, bank balance, and physical assets.',
        'caps'  => array(
            'finance_view'     => array('label' => 'View Financial Reports',    'type' => 'view'),
            'finance_manage'   => array('label' => 'Manage Invoices & Payments','type' => 'manage'),
            'assets_view'      => array('label' => 'View Asset Registry',       'type' => 'view'),
            'assets_manage'    => array('label' => 'Manage Asset Registry',     'type' => 'manage'),
        ),
    ),
    'operations' => array(
        'title' => 'Community, Facilities & Staff',
        'icon'  => 'bi-building-gear',
        'desc'  => 'Clubhouse/amenity bookings, notices, bylaws, polls, and help staff.',
        'caps'  => array(
            'facilities_view'   => array('label' => 'View Facilities & Bookings',  'type' => 'view'),
            'facilities_manage' => array('label' => 'Manage Facilities & Bookings','type' => 'manage'),
            'documents_view'    => array('label' => 'View Document Vault',        'type' => 'view'),
            'documents_manage'  => array('label' => 'Manage Document Vault',      'type' => 'manage'),
            'notices_view'      => array('label' => 'View Society Notices',       'type' => 'view'),
            'notices_manage'    => array('label' => 'Manage Society Notices',     'type' => 'manage'),
            'rules_view'        => array('label' => 'View Society Rules',         'type' => 'view'),
            'rules_manage'      => array('label' => 'Manage Rules & Violations',  'type' => 'manage'),
            'staff_view'        => array('label' => 'View Support Staff',         'type' => 'view'),
            'staff_manage'      => array('label' => 'Manage Support Staff',       'type' => 'manage'),
            'polls_view'        => array('label' => 'View Society Polls',         'type' => 'view'),
            'polls_manage'      => array('label' => 'Manage Society Polls',       'type' => 'manage'),
        ),
    ),
);
?>

    <!-- Page Header -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Roles & Access Control</h1>
                <p class="text-secondary m-0 mt-1">Define granular permissions, access policies, and custom society roles.</p>
            </div>
            <button onclick="openRoleModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                <i class="bi bi-shield-plus fs-5"></i>
                <span>Create Custom Role</span>
            </button>
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="row g-4 mb-5">
        <?php if ( empty( $roles ) ) : ?>
            <div class="col-12 text-center py-5">
                <i class="bi bi-shield-slash fs-1 text-muted opacity-25 mb-3 d-block"></i>
                <h5 class="text-secondary">No roles defined yet.</h5>
                <p class="text-muted small">Create your first custom role to manage permissions.</p>
            </div>
        <?php else : ?>
            <?php foreach ( $roles as $role ) : 
                $caps = is_array( $role['capabilities'] ) ? $role['capabilities'] : ( json_decode( $role['capabilities'], true ) ?: array() );
                // Fallback: If admin role has empty stored caps, default to all available
                if ( empty( $caps ) && ( ($role['id'] ?? '') === 'admin' || stripos( $role['name'] ?? '', 'admin' ) !== false ) ) {
                    $caps = array_keys( $available_caps );
                }
                $role['capabilities'] = $caps;
                $cap_count = count( $caps );
            ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm rounded-3 bg-white h-100 p-4 hover-shadow transition-all">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                                <i class="bi bi-shield-lock fs-4"></i>
                            </div>
                            <?php if ( ! empty( $role['is_system'] ) ) : ?>
                                <span class="badge bg-light text-secondary border border-light px-2 py-1 rounded-pill fw-bold" style="font-size: 9px;">SYSTEM</span>
                            <?php endif; ?>
                        </div>
                        
                        <h5 class="fw-bold text-dark mb-1"><?php echo esc_html( $role['name'] ); ?></h5>
                        <p class="small text-secondary mb-4"><?php echo absint( $cap_count ); ?> Permissions Assigned</p>
                        
                        <div class="d-flex flex-wrap gap-1 mb-4">
                            <?php 
                            $visible_caps = array_slice( $caps, 0, 3 );
                            foreach ( $visible_caps as $cap ) : 
                            ?>
                                <span class="badge bg-light text-dark fw-medium" style="font-size: 10px;"><?php echo esc_html( $available_caps[$cap] ?? $cap ); ?></span>
                            <?php endforeach; ?>
                            <?php if ( $cap_count > 3 ) : ?>
                                <span class="badge bg-light text-muted fw-medium" style="font-size: 10px;">+<?php echo absint( $cap_count - 3 ); ?> more</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto d-flex gap-2 pt-3 border-top border-light">
                            <button onclick='editRole(<?php echo esc_attr( wp_json_encode($role) ); ?>)' class="btn btn-sm btn-light border-light flex-grow-1 fw-bold text-primary py-2 rounded-3">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <?php if ( empty( $role['is_system'] ) ) : ?>
                                <button onclick="deleteRole('<?php echo esc_html( $role['id'] ); ?>')" class="btn btn-sm btn-light border-light text-danger p-2 px-3 rounded-3">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php
add_action('shubx51_admin_modals', function() use ($capability_groups) {
?>
<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-bottom border-light px-4 py-3 bg-white">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-shield-lock-fill fs-5"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold m-0 text-dark" id="roleModalTitle">Create Custom Role</h5>
                        <p class="text-muted small m-0">Configure role identity and granular permissions</p>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="role-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="d-flex flex-column overflow-hidden mb-0">
                <div class="modal-body p-4 custom-scrollbar" style="max-height: calc(85vh - 130px); overflow-y: auto;">
                    <input type="hidden" name="action" value="shubx51_save_role">
                    <input type="hidden" name="role_id" id="role_id" value="">
                    <?php wp_nonce_field('shubx51_role_nonce'); ?>
                    
                    <!-- Role Name Input -->
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider mb-2">Role Name</label>
                        <div class="position-relative">
                            <i class="bi bi-person-badge position-absolute top-50 translate-middle-y text-muted ms-3 fs-5" style="pointer-events: none; z-index: 5;"></i>
                            <input type="text" name="name" id="role_name" class="form-control form-control-lg shadow-none rounded-3 border-light bg-light fw-bold ps-5" placeholder="e.g. Society Admin, Treasurer, Facility Manager" required>
                        </div>
                    </div>

                    <!-- Toolbar & Controls -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 p-3 bg-white rounded-3 border border-light shadow-sm">
                        <div class="d-flex align-items-center gap-2">
                            <span class="small fw-bold text-dark text-uppercase tracking-wider">Permissions:</span>
                            <span id="cap-selected-counter" class="badge bg-primary bg-opacity-10 text-primary fw-bold px-3 py-1.5 rounded-pill font-monospace" style="font-size: 12px;">0 / 26 Selected</span>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="btn-group btn-group-sm rounded-3 shadow-none" role="group">
                                <button type="button" class="btn btn-outline-primary fw-bold px-3" onclick="shubxToggleAllCaps(true)">
                                    <i class="bi bi-check-all me-1"></i> Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary px-3" onclick="shubxToggleAllCaps(false)">
                                    <i class="bi bi-x-lg me-1"></i> Clear All
                                </button>
                            </div>

                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border-light dropdown-toggle fw-semibold text-secondary px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-magic me-1"></i> Quick Presets
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-light rounded-3 py-1">
                                    <li><a class="dropdown-item py-2 fw-semibold" href="javascript:void(0)" onclick="shubxApplyPreset('admin')"><i class="bi bi-shield-fill-check text-primary me-2"></i>Full Admin (All 26)</a></li>
                                    <li><a class="dropdown-item py-2 fw-semibold" href="javascript:void(0)" onclick="shubxApplyPreset('manager')"><i class="bi bi-briefcase-fill text-info me-2"></i>Manager / Secretary</a></li>
                                    <li><a class="dropdown-item py-2 fw-semibold" href="javascript:void(0)" onclick="shubxApplyPreset('treasurer')"><i class="bi bi-wallet2 text-success me-2"></i>Treasurer / Finance</a></li>
                                    <li><a class="dropdown-item py-2 fw-semibold" href="javascript:void(0)" onclick="shubxApplyPreset('viewer')"><i class="bi bi-eye-fill text-secondary me-2"></i>Read-Only Viewer</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Grouped Capabilities Cards: Side-by-Side Flex Grid -->
                    <div class="row g-4 align-items-stretch">
                        <?php foreach ( $capability_groups as $group_key => $group ) : ?>
                            <div class="col-12 col-lg-6 d-flex flex-column">
                                <div class="card border border-light shadow-sm rounded-3 overflow-hidden h-100 d-flex flex-column" id="group-card-<?php echo esc_attr($group_key); ?>">
                                    <div class="card-header bg-light bg-opacity-50 border-bottom border-light py-3 px-4 d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi <?php echo esc_attr($group['icon']); ?> text-primary fs-5"></i>
                                            <div>
                                                <h6 class="fw-bold text-dark m-0"><?php echo esc_html($group['title']); ?></h6>
                                                <span class="text-muted" style="font-size: 11px;"><?php echo esc_html($group['desc']); ?></span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-light border border-light text-secondary rounded-2 py-1 px-2.5 fw-semibold" style="font-size: 11px;" onclick="shubxToggleGroupCaps('<?php echo esc_js($group_key); ?>')">
                                            <i class="bi bi-check2-square me-1"></i> Toggle Group
                                        </button>
                                    </div>
                                    <div class="card-body p-3 flex-grow-1">
                                        <div class="row g-2">
                                            <?php foreach ( $group['caps'] as $key => $cap_data ) : 
                                                $is_manage = ($cap_data['type'] === 'manage');
                                            ?>
                                                <div class="col-12 col-sm-6">
                                                    <div class="shubx-cap-card p-2.5 px-3 rounded-3 border border-light bg-white h-100 transition-all hover-translate-y d-flex align-items-center justify-content-between cursor-pointer" onclick="shubxCardClick(event, 'cap_<?php echo esc_attr($key); ?>')">
                                                        <div class="form-check m-0 d-flex align-items-center gap-2">
                                                            <input class="form-check-input cap-checkbox cap-group-<?php echo esc_attr($group_key); ?> shadow-none m-0 cursor-pointer" type="checkbox" name="capabilities[]" value="<?php echo esc_attr($key); ?>" id="cap_<?php echo esc_attr($key); ?>" onchange="shubxOnCapChange(this)">
                                                            <label class="form-check-label cursor-pointer text-dark fw-bold small mb-0 ms-1" for="cap_<?php echo esc_attr($key); ?>">
                                                                <?php echo esc_html($cap_data['label']); ?>
                                                            </label>
                                                        </div>
                                                        <span class="badge <?php echo $is_manage ? 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25' : 'bg-light text-secondary border border-light'; ?> fw-bold" style="font-size: 9px; letter-spacing: 0.05em;">
                                                            <?php echo $is_manage ? 'MANAGE' : 'VIEW'; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer border-top border-light bg-white px-4 py-3 d-flex align-items-center justify-content-between">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Role Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

