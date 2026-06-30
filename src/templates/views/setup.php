<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View: Setup Wizard
 * A premium, multi-step installer for Society GoVernX.
 */
$step = isset($_GET['step']) ? intval( wp_unslash( $_GET['step'] ) ) : 1;
$total_steps = 4;
$progress = ($step / $total_steps) * 100;

// Get current options if any
$society_name = get_option('sgvx51_society_name', '');
?>
<div class="setup-wizard-wrapper d-flex align-items-center justify-content-center min-vh-100 bg-light">
    <div class="setup-card bg-white shadow-lg rounded-4 overflow-hidden" style="max-width: 800px; width: 100%;">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-4 bg-dark text-white p-5 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                            <i class="bi bi-building text-white"></i>
                        </div>
                        <h5 class="fw-bold mb-0">GoVernX</h5>
                    </div>
                    <ul class="list-unstyled setup-steps">
                        <li class="mb-4 d-flex align-items-center gap-3 <?php echo $step >= 1 ? 'text-white' : 'text-white-50'; ?>">
                            <div class="step-num rounded-circle border border-2 d-flex align-items-center justify-content-center <?php echo $step == 1 ? 'border-primary bg-primary' : 'border-secondary'; ?>" style="width: 30px; height: 30px; font-size: 12px;">1</div>
                            <span>Society Identity</span>
                        </li>
                        <li class="mb-4 d-flex align-items-center gap-3 <?php echo $step >= 2 ? 'text-white' : 'text-white-50'; ?>">
                            <div class="step-num rounded-circle border border-2 d-flex align-items-center justify-content-center <?php echo $step == 2 ? 'border-primary bg-primary' : 'border-secondary'; ?>" style="width: 30px; height: 30px; font-size: 12px;">2</div>
                            <span>Property Structure</span>
                        </li>
                        <li class="mb-4 d-flex align-items-center gap-3 <?php echo $step >= 3 ? 'text-white' : 'text-white-50'; ?>">
                            <div class="step-num rounded-circle border border-2 d-flex align-items-center justify-content-center <?php echo $step == 3 ? 'border-primary bg-primary' : 'border-secondary'; ?>" style="width: 30px; height: 30px; font-size: 12px;">3</div>
                            <span>Financial Base</span>
                        </li>
                        <li class="d-flex align-items-center gap-3 <?php echo $step >= 4 ? 'text-white' : 'text-white-50'; ?>">
                            <div class="step-num rounded-circle border border-2 d-flex align-items-center justify-content-center <?php echo $step == 4 ? 'border-primary bg-primary' : 'border-secondary'; ?>" style="width: 30px; height: 30px; font-size: 12px;">4</div>
                            <span>Launch</span>
                        </li>
                    </ul>
                </div>
                <div class="small text-white-50">
                    &copy; <?php echo wp_date('Y'); ?> Society GoVernX
                </div>
            </div>

            <!-- Content Area -->
            <div class="col-md-8 p-5">
                <form method="post" action="admin-post.php" enctype="multipart/form-data">
                    <?php wp_nonce_field('sgvx51_setup_nonce'); ?>
                    <input type="hidden" name="action" value="sgvx51_setup_action">
                    
                    <?php if($step == 1): ?>
                        <input type="hidden" name="sgvx51_setup_step" value="identity">
                        <div class="mb-4">
                            <span class="badge bg-primary-subtle text-primary mb-2">Step 1 of 4</span>
                            <h2 class="fw-bold text-dark">Society Identity</h2>
                            <p class="text-secondary">Enter the legal name and address of your society.</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Society Name</label>
                            <input type="text" name="society_name" class="form-control form-control-lg bg-light border-0 rounded-3 shadow-none" placeholder="e.g. Green Valley Apartments" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="Building No, Street" required>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">City</label>
                                <input type="text" name="city" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="e.g. Bangalore" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Pincode</label>
                                <input type="text" name="pincode" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="600001" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Contact Number</label>
                            <input type="text" name="contact" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="+91 98XXX XXXXX">
                        </div>

                    <?php elseif($step == 2): ?>
                        <input type="hidden" name="sgvx51_setup_step" value="property">
                        <div class="mb-4">
                            <span class="badge bg-primary-subtle text-primary mb-2">Step 2 of 4</span>
                            <h2 class="fw-bold text-dark">Property Structure</h2>
                            <p class="text-secondary">Tell us about your building layout to generate flats.</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Building Blocks</label>
                            <input type="text" name="blocks" class="form-control form-control-lg bg-light border-0 rounded-3 shadow-none" placeholder="e.g. A, B, C (Comma separated)" value="A" required>
                            <div class="form-text small">Enter block letters or names separated by commas.</div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Total Floors</label>
                                <input type="number" name="floors" class="form-control bg-light border-0 rounded-3 shadow-none" value="4" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Flats per Floor</label>
                                <input type="number" name="flats_per_floor" class="form-control bg-light border-0 rounded-3 shadow-none" value="6" required>
                            </div>
                        </div>
                        <div class="bg-primary-subtle p-3 rounded-3 border border-primary border-opacity-10 d-flex gap-3 align-items-center">
                            <i class="bi bi-info-circle text-primary fs-4"></i>
                            <div class="small text-primary-emphasis">
                                This will generate <strong>24 flats</strong> (e.g. A-101 to A-406). You can edit specific flat details later.
                            </div>
                        </div>

                    <?php elseif($step == 3): ?>
                        <input type="hidden" name="sgvx51_setup_step" value="financials">
                        <div class="mb-4">
                            <span class="badge bg-primary-subtle text-primary mb-2">Step 3 of 4</span>
                            <h2 class="fw-bold text-dark">Financial Base</h2>
                            <p class="text-secondary">Basic settings for maintenance collection.</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-uppercase text-muted">Monthly Maintenance Fee</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0 rounded-start-3 text-secondary">₹</span>
                                <input type="number" name="maintenance_amount" class="form-control form-control-lg bg-light border-0 rounded-end-3 shadow-none" placeholder="3000" required>
                            </div>
                        </div>
                        <hr class="my-4 border-light">
                        <h6 class="fw-bold text-dark mb-3">Bank Details for Collection</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted text-truncate">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="e.g. HDFC Bank">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">Account Number</label>
                                <input type="text" name="bank_account" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="XXXX XXXX XXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">IFSC Code</label>
                                <input type="text" name="bank_ifsc" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="HDFC000XXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small text-uppercase text-muted">UPI ID (VPA)</label>
                                <input type="text" name="bank_upi" class="form-control bg-light border-0 rounded-3 shadow-none" placeholder="society@upi">
                            </div>
                        </div>

                    <?php elseif($step == 4): ?>
                        <input type="hidden" name="sgvx51_setup_step" value="finalize">
                        <input type="hidden" name="finalize" value="1">
                        <div class="text-center py-4">
                            <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                                <i class="bi bi-check-lg fs-1"></i>
                            </div>
                            <h2 class="fw-bold text-dark">Ready for Launch!</h2>
                            <p class="text-secondary px-4">Great job! Your society is configured. In the final step we will:</p>
                            
                            <ul class="list-unstyled text-start d-inline-block mx-auto">
                                <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i> Create Resident Dashboard</li>
                                <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i> Initialize Notification System</li>
                                <li class="mb-2"><i class="bi bi-check2 text-success me-2"></i> Set up Society Data Store</li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="mt-5 d-flex justify-content-between align-items-center pt-4 border-top border-light">
                        <?php if($step > 1 && $step < 4): ?>
                            <a href="?page=sgvx51-setup&step=<?php echo $step - 1; ?>" class="btn btn-link text-secondary text-decoration-none fw-semibold">
                                <i class="bi bi-arrow-left me-2"></i> Back
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary px-5 py-2 rounded-3 fw-bold shadow-sm">
                            <?php echo ($step == 4) ? 'Finish & Go to Dashboard' : 'Continue <i class="bi bi-arrow-right ms-2"></i>'; ?>
                        </button>
                    </div>

                    <?php if($step < 4): ?>
                    <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url(add_query_arg('step', $step + 1, admin_url('admin.php?page=sgvx51-setup'))); ?>">
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.setup-wizard-wrapper { background: #f8fafc; font-family: 'Inter', sans-serif; }
.setup-card { border: none; }
.form-control:focus { background-color: #fff !important; border: 1px solid #0d6efd !important; }
.btn-primary { background: #0d6efd; border: none; }
.btn-primary:hover { background: #0b5ed7; }
.setup-steps span { font-weight: 500; font-size: 14px; }
.step-num { transition: all 0.3s ease; }
</style>
