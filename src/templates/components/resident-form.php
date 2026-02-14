<?php
/**
 * Component: Resident/Family Form
 * Reusable form for adding/editing residents and family members.
 * 
 * Arguments:
 * - $context: 'admin' | 'frontend_family' | 'frontend_profile'
 * - $flats: Array of flats (only for admin)
 * - $resident: Array of existing data (for edit mode)
 */

$context = $args['context'] ?? ($context ?? 'admin');
$is_admin = $context === 'admin';
$is_profile = $context === 'frontend_profile';
$is_family = $context === 'frontend_family';

// Robust Data Extraction
$r = [];
if ( isset( $args['resident'] ) ) {
    $r = $args['resident'];
} elseif ( isset( $resident ) ) {
    $r = $resident;
} elseif ( isset( $data['resident'] ) ) {
    $r = $data['resident'];
}

// Defaults with Null Coalescing
$name          = $r['name'] ?? '';
$phone         = $r['phone'] ?? '';
$email         = $r['email'] ?? '';
$profile_photo = $r['profile_photo'] ?? '';
$flat_no       = $r['flat_no'] ?? '';
$type          = $r['type'] ?? ($is_family ? 'family' : 'owner');
$relation      = $r['relation'] ?? '';
$dob           = $r['dob'] ?? '';
$blood_group   = $r['blood_group'] ?? '';
$role          = $r['roles'] ?? ($r['role'] ?? '');

?>

<div class="row g-3">
    <!-- Profile Picture Section -->
    <div class="col-12 text-center mb-2">
        <div class="position-relative d-inline-block">
            <div class="rounded-circle bg-light border border-2 border-white shadow-sm overflow-hidden d-flex align-items-center justify-content-center" 
                 style="width: 100px; height: 100px;">
                <?php if($profile_photo): ?>
                    <img src="<?php echo esc_url($profile_photo); ?>" id="preview-<?php echo $context; ?>" class="w-100 h-100 object-fit-cover" alt="Profile">
                <?php else: ?>
                    <i class="bi bi-person-fill text-secondary fs-1" id="icon-<?php echo $context; ?>"></i>
                    <img src="" id="preview-<?php echo $context; ?>" class="w-100 h-100 object-fit-cover d-none" alt="Preview">
                <?php endif; ?>
            </div>
            <label for="pic-<?php echo $context; ?>" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow-sm cursor-pointer hover-scale" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-camera-fill small"></i>
            </label>
            <input type="file" name="profile_photo" id="pic-<?php echo $context; ?>" class="d-none js-profile-upload" accept="image/*" data-preview="#preview-<?php echo $context; ?>" data-icon="#icon-<?php echo $context; ?>">
        </div>
        <div class="text-muted small mt-2">Tap to upload photo</div>
    </div>

    <!-- Full Name -->
    <div class="col-md-<?php echo ($is_admin || $is_profile) ? '6' : '12'; ?>">
        <label class="form-label small fw-bold text-secondary text-uppercase">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="form-control rounded-3 border-light shadow-none" required placeholder="Enter full name">
    </div>

    <!-- Admin: Flat & Type -->
    <?php if($is_admin): ?>
        <div class="col-md-6 text-start">
             <label class="form-label small fw-bold text-secondary text-uppercase">Flat / Unit <span class="text-danger">*</span></label>
             <select name="flat_no" class="form-select rounded-3 border-light shadow-none" required>
                <option value="">Select Flat</option>
                <?php if(!empty($args['flats'])): ?>
                    <?php foreach($args['flats'] as $f): 
                        $val = $f['id']; 
                        $f_num = !empty($f['flat_number']) ? $f['flat_number'] : $f['id'];
                        $label = $f_num;
                        $is_sel = ($flat_no == $val || $flat_no == $f_num);
                    ?>
                        <option value="<?php echo esc_attr($val); ?>" 
                                data-number="<?php echo esc_attr($f_num); ?>"
                                data-id="<?php echo esc_attr($val); ?>"
                                <?php echo $is_sel ? 'selected' : ''; ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
             </select>
        </div>
        <div class="col-md-6 text-start">
             <label class="form-label small fw-bold text-secondary text-uppercase">Type <span class="text-danger">*</span></label>
             <select name="type" id="resident-type-select-<?php echo $context; ?>" class="form-select rounded-3 border-light shadow-none js-resident-type-toggle" data-context="<?php echo $context; ?>" required>
                 <option value="owner" <?php selected($type, 'owner'); ?>>Owner</option>
                 <option value="tenant" <?php selected($type, 'tenant'); ?>>Tenant</option>
                 <option value="family" <?php selected($type, 'family'); ?>>Family Member</option>
             </select>
        </div>
    <?php elseif($is_profile): ?>
        <div class="col-md-6">
            <label class="form-label small fw-bold text-secondary text-uppercase">Flat No.</label>
            <?php 
            $display_flat = Society_GoVernX::get_instance()->db->get_flat_display_name( $flat_no );
            ?>
            <input type="text" class="form-control rounded-3 border-light shadow-none bg-light" value="<?php echo esc_attr($display_flat); ?>" disabled>
            <input type="hidden" name="flat_no" value="<?php echo esc_attr($flat_no); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-bold text-secondary text-uppercase">Type</label>
            <input type="text" class="form-control rounded-3 border-light shadow-none bg-light" value="<?php echo esc_attr(ucfirst($type)); ?>" disabled>
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
        </div>
    <?php else: ?>
        <!-- Hidden Type for Frontend Family -->
        <input type="hidden" name="type" value="family">
    <?php endif; ?>

    <!-- Relation (Frontend Family OR Admin) -->
    <div class="col-md-6" id="relation-wrapper-<?php echo $context; ?>" style="<?php echo ($type !== 'family') ? 'display:none;' : ''; ?>">
         <label class="form-label small fw-bold text-secondary text-uppercase">Relation <span class="text-danger">*</span></label>
         <select name="relation" class="form-select rounded-3 border-light shadow-none" <?php echo ($type === 'family') ? 'required' : ''; ?>>
             <option value="">Select Relation</option>
             <option value="Spouse" <?php selected($relation, 'Spouse'); ?>>Spouse</option>
             <option value="Child" <?php selected($relation, 'Child'); ?>>Child</option>
             <option value="Parent" <?php selected($relation, 'Parent'); ?>>Parent</option>
             <option value="Sibling" <?php selected($relation, 'Sibling'); ?>>Sibling</option>
             <option value="Relative" <?php selected($relation, 'Relative'); ?>>Other Relative</option>
             <option value="Other" <?php selected($relation, 'Other'); ?>>Other</option>
         </select>
    </div>

    <!-- Personal Details (DOB, Blood Group) -->
    <div class="col-md-6">
         <label class="form-label small fw-bold text-secondary text-uppercase">Date of Birth </label>
         <input type="date" name="dob" value="<?php echo esc_attr($dob); ?>" class="form-control rounded-3 border-light shadow-none">
    </div>

    <!-- Contact Info -->
    <div class="col-md-6">
        <label class="form-label small fw-bold text-secondary text-uppercase">Phone Number</label>
        <input type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" class="form-control rounded-3 border-light shadow-none" placeholder="10-digit mobile">
    </div>

    <!-- Email (Optional for all) -->
    <div class="col-md-6">
        <label class="form-label small fw-bold text-secondary text-uppercase">Email Address</label>
        <input type="email" name="email" value="<?php echo esc_attr($email); ?>" class="form-control rounded-3 border-light shadow-none" placeholder="official@email.com">
    </div>

    <div class="col-md-6 text-start">
         <label class="form-label small fw-bold text-secondary text-uppercase">Blood Group</label>
         <select name="blood_group" class="form-select rounded-3 border-light shadow-none">
             <option value="">Select</option>
             <?php 
             $bgs = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
             foreach($bgs as $bg): 
             ?>
                <option value="<?php echo $bg; ?>" <?php selected($blood_group, $bg); ?>><?php echo $bg; ?></option>
             <?php endforeach; ?>
         </select>
    </div>

     <!-- Society Role (Admin Only) -->
     <?php if($is_admin): ?>
        <div class="col-12 text-start" id="society-role-wrapper-<?php echo $context; ?>" style="<?php echo ($type === 'family') ? 'display:none;' : ''; ?>">
            <label class="form-label small fw-bold text-secondary text-uppercase">Society Role</label>
            <select name="role" class="form-select rounded-3 border-light shadow-none">
                <option value="">None / Resident</option>
                <option value="President" <?php selected($role, 'President'); ?>>President</option>
                <option value="Vice-President" <?php selected($role, 'Vice-President'); ?>>Vice-President</option>
                <option value="Secretary" <?php selected($role, 'Secretary'); ?>>Secretary</option>
                <option value="Treasurer" <?php selected($role, 'Treasurer'); ?>>Treasurer</option>
                <option value="Committee Member" <?php selected($role, 'Committee Member'); ?>>Committee Member</option>
                <option value="Management" <?php selected($role, 'Management'); ?>>Management</option>
            </select>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    // 1. Image Preview Logic
    const fileInputs = document.querySelectorAll('.js-profile-upload');
    fileInputs.forEach(input => {
        if (input.dataset.handled) return;
        input.dataset.handled = "true";

        input.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const previewSelector = this.dataset.preview;
                const iconSelector = this.dataset.icon;
                
                reader.onload = function(e) {
                    const preview = document.querySelector(previewSelector);
                    const icon = document.querySelector(iconSelector);
                    if(preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                    }
                    if(icon) icon.classList.add('d-none');
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // 2. Relationship/Role Toggle Logic
    const typeToggles = document.querySelectorAll('.js-resident-type-toggle');
    typeToggles.forEach(toggle => {
        if (toggle.dataset.toggleHandled) return;
        toggle.dataset.toggleHandled = "true";

        toggle.addEventListener('change', function() {
            const context = this.dataset.context;
            const container = this.closest('.row'); // Form row scope
            const relWrapper = container.querySelector(`#relation-wrapper-${context}`);
            const roleWrapper = container.querySelector(`#society-role-wrapper-${context}`);
            const relSelect = relWrapper ? relWrapper.querySelector('select') : null;

            if (this.value === 'family') {
                if (relWrapper) relWrapper.style.display = 'block';
                if (roleWrapper) roleWrapper.style.display = 'none';
                if (relSelect) relSelect.setAttribute('required', 'required');
            } else {
                if (relWrapper) relWrapper.style.display = 'none';
                if (roleWrapper) roleWrapper.style.display = 'block';
                if (relSelect) relSelect.removeAttribute('required');
            }
        });
    });
})();
</script>

