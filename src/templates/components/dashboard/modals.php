<?php
/**
 * Component: Dashboard Modals
 * @var array $data Dashboard data.
 */
$profile_resident = $data['resident'] ?? [];

// Retrieve bank details from settings
$bank_name = get_option('sgvx51_bank_name', 'Society Bank');
$acct_no   = get_option('sgvx51_bank_account', 'Not Set');
$ifsc      = get_option('sgvx51_bank_ifsc', 'Not Set');
$upi       = get_option('sgvx51_bank_upi', 'Not Set');
$qr_url    = get_option('sgvx51_bank_qr');
?>

<!-- 1. Family Member Modal (Add/Edit) -->
<div class="modal fade" id="familyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="sgvx51_add_family">
      <?php wp_nonce_field( 'sgvx51_add_family_nonce' ); ?>
      <input type="hidden" name="_wpnonce_add_family" value="<?php echo wp_create_nonce('sgvx51_add_family_nonce'); ?>">
      <?php wp_nonce_field( 'sgvx51_edit_family_nonce', '_wpnonce_edit_family', false ); ?>
      <input type="hidden" name="member_id" value="">
      
      <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
        <h5 class="fw-bold m-0" id="familyModalLabel">Add Family Member</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
          <?php 
          $args = [
              'context'  => 'frontend_family',
              'resident' => [] 
          ];
          // Ensure resident-form.php variables are set for empty form
          $resident = []; 
          include SGVX51_PLUGIN_DIR . 'templates/components/resident-form.php'; 
          ?>
      </div>
      <div class="modal-footer border-top-0 bg-light px-4 py-3">
        <button type="button" class="btn btn-light fw-semibold text-secondary px-4 rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary px-4 fw-bold rounded-3 shadow-sm">Save Family Member</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. View Family Member Modal -->
<div class="modal fade" id="viewFamilyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0">Family Member Details</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center overflow-hidden border shadow-sm" style="width: 100px; height: 100px;">
                        <img id="view-family-photo" src="" class="w-100 h-100 object-fit-cover d-none">
                        <i id="view-family-placeholder" class="bi bi-person-fill text-secondary fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mt-3 mb-1" id="view-family-name"></h5>
                    <div class="badge bg-primary-subtle text-primary rounded-pill px-3 py-1 fw-normal" id="view-family-relation"></div>
                </div>
                
                <div class="bg-light rounded-3 p-3">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="small text-secondary fw-bold text-uppercase d-block mb-1">Date of Birth</label>
                            <span class="fw-medium text-dark" id="view-family-dob">-</span>
                        </div>
                        <div class="col-6">
                            <label class="small text-secondary fw-bold text-uppercase d-block mb-1">Blood Group</label>
                            <span class="fw-medium text-dark" id="view-family-blood">-</span>
                        </div>
                        <div class="col-6">
                            <label class="small text-secondary fw-bold text-uppercase d-block mb-1">Phone</label>
                            <span class="fw-medium text-dark" id="view-family-phone">-</span>
                        </div>
                        <div class="col-6">
                            <label class="small text-secondary fw-bold text-uppercase d-block mb-1">Email</label>
                            <span class="fw-medium text-dark" id="view-family-email">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light px-4 py-3">
                <button type="button" class="btn btn-light fw-semibold text-secondary px-4 rounded-3 border-0 w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- 3. Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Daily Help Details</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <input type="hidden" name="action" value="sgvx51_add_daily_help">
         <input type="hidden" name="help_id" value="">
         <input type="hidden" name="document_url" value="">
          <?php wp_nonce_field('sgvx51_add_help_nonce', '_wpnonce_add_help'); ?>
          <?php wp_nonce_field('sgvx51_edit_help_nonce', '_wpnonce_edit_help'); ?>
          <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sgvx51_add_help_nonce')); ?>">
         
         <div class="row g-3 mb-3">
             <div class="col-md-7">
                 <label class="form-label small fw-bold text-secondary text-uppercase">FullName <span class="text-danger">*</span></label>
                 <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
             </div>
             <div class="col-md-5">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Staff Type <span class="text-danger">*</span></label>
                 <select name="category" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Support Staff">Support Staff</option>
                     <option value="Management">Management</option>
                 </select>
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Role <span class="text-danger">*</span></label>
                 <select name="role" class="form-select rounded-3 border-light shadow-none" required>
                     <option value="Maid">Maid</option><option value="Cook">Cook</option>
                     <option value="Driver">Driver</option><option value="Nanny">Nanny</option>
                     <option value="Guard">Security Guard</option><option value="Cleaner">Cleaner</option>
                     <option value="Gardener">Gardener</option><option value="Manager">Manager</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone </label>
                 <input type="text" name="phone" class="form-control rounded-3 border-light shadow-none">
             </div>
         </div>

         <div class="row g-3 mb-3">
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Gender </label>
                 <select name="sex" class="form-select rounded-3 border-light shadow-none">
                     <option value="Male">Male</option>
                     <option value="Female">Female</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
             <div class="col-md-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Visiting Hours</label>
                 <input type="text" name="visiting_hours" class="form-control rounded-3 border-light shadow-none" placeholder="e.g. 7 AM - 10 AM">
             </div>
         </div>

         <div class="mb-0">
             <label class="form-label small fw-bold text-secondary text-uppercase">ID Proof (Photo/Document)</label>
             <input type="file" name="doc_file" accept="image/*" capture="environment" class="form-control shadow-none rounded-3 border-light">
             <div id="current-help-doc-preview" class="mt-2 d-none">
                 <a href="#" target="_blank" class="small text-primary fw-bold"><i class="bi bi-file-earmark-check me-1"></i>View Current Proof</a>
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Save Help</button>
      </div>
    </form>
  </div>
</div>

<!-- 4. Vehicle Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Register Vehicle</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_add_vehicle_frontend">
         <input type="hidden" name="vehicle_id" value="">
         <?php wp_nonce_field('sgvx51_add_vehicle_frontend_nonce', '_wpnonce_add_vehicle_frontend'); ?>
         <?php wp_nonce_field('sgvx51_edit_vehicle_action', 'sgvx51_edit_vehicle_token', true, true); ?>
         <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('sgvx51_add_vehicle_frontend_nonce')); ?>">
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Vehicle Number <span class="text-danger">*</span></label>
             <input type="text" name="number" class="form-control rounded-3 border-light shadow-none font-monospace" placeholder="KA52AB1234" required>
         </div>
          <div class="row g-3 mb-3">
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Type</label>
                 <select name="type" class="form-select rounded-3 border-light shadow-none">
                     <option>Bike</option>
                     <option>Car</option>
                     <option>Others</option>
                 </select>
             </div>
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Brand</label>
                 <input type="text" name="brand" class="form-control rounded-3 border-light shadow-none" placeholder="Ducati">
             </div>
             <div class="col-md-4">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Model</label>
                 <input type="text" name="model" class="form-control rounded-3 border-light shadow-none" placeholder="Monster 821">
             </div>
          </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- 5. Edit Family Modal (Redundant or Legacy) -->
<div class="modal fade" id="editFamilyModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Family Member</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_edit_family_frontend">
         <input type="hidden" name="member_id" value="">
         <?php wp_nonce_field('sgvx51_edit_family_nonce'); ?>
         
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Name <span class="text-danger">*</span></label>
             <input type="text" name="name" class="form-control rounded-3 border-light shadow-none" required>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Relation <span class="text-danger">*</span></label>
                 <select name="relation" class="form-select rounded-3 border-light shadow-none" required>
                     <option>Spouse</option>
                     <option>Child</option>
                     <option>Parent</option>
                     <option>Sibling</option>
                     <option>Other</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Date of Birth <span class="text-danger">*</span></label>
                 <input type="date" name="dob" class="form-control rounded-3 border-light shadow-none" required>
             </div>
         </div>
         <div class="row g-3 mb-3">
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Blood Group</label>
                 <select name="blood_group" class="form-select rounded-3 border-light shadow-none">
                     <option value="">Select</option>
                     <option>A+</option><option>A-</option>
                     <option>B+</option><option>B-</option>
                     <option>AB+</option><option>AB-</option>
                     <option>O+</option><option>O-</option>
                 </select>
             </div>
             <div class="col-6">
                 <label class="form-label small fw-bold text-secondary text-uppercase">Phone</label>
                 <input type="tel" name="phone" class="form-control rounded-3 border-light shadow-none" placeholder="Optional">
             </div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. Upload Doc Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST" enctype="multipart/form-data">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Upload Document</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <input type="hidden" name="action" value="sgvx51_frontend_upload_doc">
         <?php wp_nonce_field('sgvx51_upload_doc_nonce'); ?>
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">Document Name <span class="text-danger">*</span></label>
             <input type="text" name="doc_name" class="form-control rounded-3 border-light shadow-none" placeholder="Maintenance Bill/Possession Letter" required>
         </div>
         <div class="mb-3">
             <label class="form-label small fw-bold text-secondary text-uppercase">File (PDF/Image) <span class="text-danger">*</span></label>
             <input type="file" name="doc_file" class="form-control rounded-3 border-light shadow-none" required>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-light text-secondary rounded-3 px-4 shadow-none" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Upload</button>
      </div>
    </form>
  </div>
</div>

<!-- 7. Quick Pay Modal -->
<div class="modal fade" id="sgvx51PaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="modal-title fw-bold">Maintenance Payment</h5>
        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <div class="text-center mb-4">
             <i class="bi bi-qr-code fs-1 text-primary"></i>
             <p class="text-secondary mt-2 mb-0">Scan to pay via any UPI App</p>
         </div>
         
         <div class="bg-light p-4 rounded-3 mb-4 text-center">
              <?php if($qr_url): ?>
                  <img src="<?php echo esc_url($qr_url); ?>" class="img-fluid rounded" style="max-width: 200px; max-height: 200px; object-fit: contain;">
              <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center bg-white border border-2 border-dashed rounded" style="width: 200px; height: 200px; margin: 0 auto;">
                      <div class="text-center text-muted">
                          <i class="bi bi-image fs-1 d-block mb-2"></i>
                          <small>No QR Code</small>
                      </div>
                  </div>
              <?php endif; ?>
              
              <?php if($upi && $upi !== 'Not Set'): ?>
                  <div class="mt-3 p-2 bg-primary bg-opacity-10 rounded">
                      <small class="text-primary fw-bold">UPI ID: <?php echo esc_html($upi); ?></small>
                  </div>
              <?php endif; ?>
         </div>

         <div class="border border-light rounded-3 overflow-hidden mb-3">
             <div class="p-3">
                 <div class="row g-2 small">
                     <div class="col-12 py-2 border-bottom border-light d-flex justify-content-between">
                         <span class="text-secondary">Bank</span><span class="fw-bold"><?php echo esc_html($bank_name); ?></span>
                     </div>
                     <div class="col-12 py-2 border-bottom border-light d-flex justify-content-between">
                         <span class="text-secondary">A/C</span><span class="fw-bold font-monospace"><?php echo esc_html($acct_no); ?></span>
                     </div>
                     <div class="col-12 py-2 d-flex justify-content-between">
                         <span class="text-secondary">IFSC</span><span class="fw-bold font-monospace"><?php echo esc_html($ifsc); ?></span>
                     </div>
                 </div>
             </div>
         </div>

         <form id="payment-confirmation-form" class="text-start">
            <input type="hidden" name="invoice_id" id="confirm-invoice-id">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Amount (₹) *</label><input type="number" name="amount" id="confirm-amount" class="form-control rounded-3" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Date *</label><input type="date" name="date" id="confirm-date" class="form-control rounded-3" value="<?php echo date('Y-m-d'); ?>" required></div>
                <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Method</label><select name="method" class="form-select rounded-3"><option value="UPI">UPI</option><option value="Bank Transfer">Bank Transfer</option><option value="Cash">Cash</option></select></div>
                <div class="col-md-6"><label class="form-label small fw-bold text-secondary">Ref / Txn ID *</label><input type="text" name="reference" class="form-control rounded-3" placeholder="UTR/Ref" required></div>
            </div>
         </form>
      </div>
      <div class="modal-footer border-0 p-4 pt-0">
        <button type="button" class="btn btn-primary w-100 rounded-3 fw-bold" id="btn-confirm-payment">Submit Confirmation</button>
      </div>
    </div>
  </div>
</div>

<!-- 8. Facility Booking Modal -->
<div class="modal fade" id="residentBookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content border-0 shadow-lg rounded-3" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Book Facility</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
         <input type="hidden" name="action" value="sgvx51_book_facility">
         <input type="hidden" name="resident_id" value="<?php echo esc_attr($r['flat_no'] ?? ''); ?>">
         <?php wp_nonce_field('sgvx51_facility_nonce'); ?>
         
         <div class="mb-4">
             <label class="form-label small fw-bold text-secondary text-uppercase">Facility <span class="text-danger">*</span></label>
             <select name="facility_id" id="booking-facility-select" class="form-select rounded-3 border-light shadow-none" required>
                 <?php foreach ( ($data['facilities'] ?? []) as $f ) : ?>
                    <option value="<?php echo esc_attr($f['id']); ?>"><?php echo esc_html($f['name']); ?></option>
                 <?php endforeach; ?>
             </select>
         </div>
         <div class="row g-3 mb-4">
             <div class="col-md-6"><label class="form-label small fw-bold text-secondary text-uppercase">Start <span class="text-danger">*</span></label><input type="datetime-local" name="start_time" class="form-control rounded-3 border-light" required></div>
             <div class="col-md-6"><label class="form-label small fw-bold text-secondary text-uppercase">End <span class="text-danger">*</span></label><input type="datetime-local" name="end_time" class="form-control rounded-3 border-light" required></div>
         </div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="submit" class="btn btn-primary rounded-3 px-4 fw-bold">Confirm Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- 9. Community Detail Modal -->
<div class="modal fade" id="communityDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
            <span id="cdm-flat" class="badge bg-primary rounded-pill"></span>
            <span id="cdm-owner"></span>
        </h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <div class="row g-3">
             <div class="col-6">
                 <label class="small text-muted text-uppercase fw-bold" style="font-size: 10px;">Family Size</label>
                 <div class="fw-bold d-flex align-items-center gap-2"><i class="bi bi-people text-primary"></i> <span id="cdm-members"></span> Members</div>
             </div>
             <div class="col-12 mt-4"><label class="small text-muted text-uppercase fw-bold mb-2 d-block" style="font-size: 10px;">Vehicles</label><div id="cdm-vehicles" class="d-flex flex-column align-items-start gap-2"></div></div>
             <div class="col-12 mt-4"><label class="small text-muted text-uppercase fw-bold mb-2 d-block" style="font-size: 10px;">Daily Help</label><div id="cdm-help" class="d-flex flex-column align-items-start gap-2"></div></div>
         </div>
      </div>
    </div>
  </div>
</div>

<!-- 10. Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-receipt modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Receipt</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body modal-body-receipt p-4"><div id="receipt-content" class="receipt"></div></div>
      <div class="modal-footer border-0 pt-3">
        <button type="button" class="btn btn-primary rounded-3" onclick="downloadReceipt()"><i class="bi bi-download me-2"></i>Download Receipt</button>
      </div>
    </div>
  </div>
</div>

<!-- 11. Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-3">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Edit Profile</h5>
        <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <form id="editProfileForm" enctype="multipart/form-data">
          <?php 
          $args = [
              'context'  => 'frontend_profile',
              'resident' => $profile_resident // Pass the resident data explicitly
          ];
          // Pass variables to scope for included file (backup)
          $resident = $profile_resident;
          include SGVX51_PLUGIN_DIR . 'templates/components/resident-form.php'; 
          ?>
        </form>
      </div>
      <div class="modal-footer border-0 pt-3">
        <button type="button" class="btn btn-primary rounded-3" onclick="saveProfileChanges()"><i class="bi bi-check-circle me-2"></i>Save Changes</button>
      </div>
    </div>
  </div>
</div>

<script>
    // Data is localized via wp_localize_script in class-frontend-dashboard.php
    // Redundant window.sgvxDashboardData assignment removed to avoid overwriting localized nonce.

    function saveProfileChanges() {
      const btn = event.target;
      const form = document.getElementById('editProfileForm');
      if (!form) return;

      const name = form.querySelector('[name="name"]')?.value || '';
      const email = form.querySelector('[name="email"]')?.value || '';
      const phone = form.querySelector('[name="phone"]')?.value || '';
      const blood = form.querySelector('[name="blood_group"]')?.value || '';
      const dob = form.querySelector('[name="dob"]')?.value || '';
      const fileInput = document.getElementById('pic-frontend_profile');

      if (!name.trim() || !email.trim()) { alert('❌ Name and Email are required'); return; }

      btn.disabled = true;
      const originalText = btn.innerHTML;
      btn.innerHTML = 'Saving...';

      const formData = new FormData();
      formData.append('action', 'sgvx51_edit_resident');
      formData.append('resident_id', '<?php echo esc_js($profile_resident['id'] ?? ''); ?>');
      formData.append('name', name);
      formData.append('email', email);
      formData.append('phone', phone);
      formData.append('blood_group', blood);
      formData.append('dob', dob);
      formData.append('flat_no', '<?php echo esc_js($profile_resident['flat_no'] ?? ''); ?>');
      formData.append('type', '<?php echo esc_js($profile_resident['type'] ?? 'owner'); ?>');
      formData.append('_wpnonce', '<?php echo esc_js(wp_create_nonce('sgvx51_frontend_nonce')); ?>');
      if (fileInput && fileInput.files[0]) formData.append('profile_photo', fileInput.files[0]);

      fetch(ajaxurl, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) { 
          alert('✅ Profile updated successfully!'); 
          location.reload(); 
        } else { 
          alert('❌ ' + (data.data?.message || 'Error occurred while saving profile.')); 
          btn.disabled = false; 
          btn.innerHTML = originalText; 
        }
      })
      .catch(err => {
        console.error('Profile Save Error:', err);
        alert('❌ Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
    }

</script>
