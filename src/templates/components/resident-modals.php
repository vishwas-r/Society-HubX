<?php
/**
 * Resident Modals Component
 * Includes: Edit Profile Modal, Family Member Modal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$r_profile = $args['resident'] ?? $resident ?? $r ?? $data['resident'] ?? [];
$r = $r_profile; // Keep for backward compatibility in the script below if needed
?>

<!-- Edit Family Modal -->
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Add Family Member</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_add_family">
         <?php wp_nonce_field('sgvx51_add_family_nonce', '_wpnonce_add_family', true, true); ?>
         <?php wp_nonce_field('sgvx51_edit_family_nonce', '_wpnonce_edit_family', true, true); ?>
         <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sgvx51_add_family_nonce')); ?>">
         
         <?php 
            $_form_args = ['context' => 'frontend_family', 'resident' => []];
            $args = $_form_args; // For resident-form.php compatibility
            include plugin_dir_path(__FILE__) . 'resident-form.php'; 
         ?>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Add Member</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Profile</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editProfileForm" enctype="multipart/form-data">
           <?php 
               $_prof_args = ['context' => 'frontend_profile', 'resident' => $r];
               $args = $_prof_args; // For resident-form.php compatibility
               include plugin_dir_path(__FILE__) . 'resident-form.php'; 
           ?>
        </form>
      </div>
      <div class="modal-footer border-0 pt-3">
        <button type="button" class="btn btn-light rounded-3 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary rounded-3" onclick="saveProfileChanges()">
          <i class="bi bi-check-circle me-2"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function saveProfileChanges() {
  const btn = event.target;
  // Disable button and show loading state
  btn.disabled = true;
  const originalText = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

  // Send AJAX request
  const form = document.getElementById('editProfileForm');
  const formData = new FormData(form);
  formData.append('action', 'sgvx51_edit_resident');
  formData.append('resident_id', '<?php echo esc_js($r['id'] ?? ''); ?>');
  formData.append('flat_no', '<?php echo esc_js($r['flat_no'] ?? ''); ?>');
  formData.append('type', '<?php echo esc_js($r['type'] ?? 'owner'); ?>');
  formData.append('_wpnonce', '<?php echo esc_js(wp_create_nonce('sgvx51_resident_nonce')); ?>');

  fetch(ajaxurl, {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.json();
  })
  .then(data => {
    if (data.success) {
      alert('✅ Profile updated successfully!');
      const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
      if (modal) modal.hide();
      setTimeout(() => location.reload(), 500);
    } else {
      alert('❌ Error: ' + (data.data?.message || 'Unknown error'));
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  })
  .catch(error => {
    console.error('Save error:', error);
    alert('❌ Error saving profile: ' + error.message);
    btn.disabled = false;
    btn.innerHTML = originalText;
  });
}
</script>
