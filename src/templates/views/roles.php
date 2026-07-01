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
?>

    <!-- Page Header -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Roles & Access Control</h1>
                <p class="text-secondary m-0 mt-1">Define granular permissions and custom society roles.</p>
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
                $caps = json_decode( $role['capabilities'], true ) ?: array();
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
                            <button onclick='editRole(<?php echo json_encode($role); ?>)' class="btn btn-sm btn-light border-light flex-grow-1 fw-bold text-primary py-2 rounded-3">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <?php if ( empty( $role['is_system'] ) ) : ?>
                                <button onclick="deleteRole('<?php echo $role['id']; ?>')" class="btn btn-sm btn-light border-light text-danger p-2 px-3 rounded-3">
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
add_action('shubx51_admin_modals', function() use ($available_caps) {
?>
<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark" id="roleModalTitle">Create Custom Role</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="role-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="shubx51_save_role">
                    <input type="hidden" name="role_id" id="role_id" value="">
                    <?php wp_nonce_field('shubx51_role_nonce'); ?>
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Role Name</label>
                        <input type="text" name="name" id="role_name" class="form-control form-control-lg shadow-none rounded-3 border-light bg-light fw-bold" placeholder="e.g. Finance Head, Secretary Buddy" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Capabilities & Permissions</label>
                        <div class="row g-3">
                            <?php foreach ( $available_caps as $key => $label ) : ?>
                                <div class="col-md-6">
                                    <div class="p-3 rounded-3 border border-light bg-light bg-opacity-50 h-100 transition-all hover-bg-light">
                                        <div class="form-check m-0">
                                            <input class="form-check-input cap-checkbox shadow-none" type="checkbox" name="capabilities[]" value="<?php echo esc_attr($key); ?>" id="cap_<?php echo esc_attr($key); ?>">
                                            <label class="form-check-label ms-2" for="cap_<?php echo esc_attr($key); ?>">
                                                <div class="fw-bold text-dark small"><?php echo esc_html($label); ?></div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Save Role Permissions</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRoleModal() {
    document.getElementById('role-form').reset();
    document.getElementById('role_id').value = '';
    document.getElementById('roleModalTitle').innerText = 'Create Custom Role';
    document.querySelectorAll('.cap-checkbox').forEach(cb => cb.checked = false);
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function editRole(role) {
    document.getElementById('role_id').value = role.id;
    document.getElementById('role_name').value = role.name;
    document.getElementById('roleModalTitle').innerText = 'Edit Role: ' + role.name;
    
    document.querySelectorAll('.cap-checkbox').forEach(cb => cb.checked = false);
    const caps = JSON.parse(role.capabilities || '[]');
    caps.forEach(cap => {
        const cb = document.getElementById('cap_' + cap);
        if(cb) cb.checked = true;
    });
    
    new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function deleteRole(roleId) {
    if(!confirm('Are you sure you want to delete this role? This cannot be undone.')) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo admin_url('admin-post.php'); ?>';
    
    const fields = {
        action: 'shubx51_delete_role',
        role_id: roleId,
        _wpnonce: '<?php echo esc_js( wp_create_nonce('shubx51_role_nonce') ); ?>'
    };
    
    for(const key in fields) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php }); ?>
