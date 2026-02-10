<?php
/**
 * View: Society Settings (Bootstrap Migration)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Assets for Settings (if any specific ones needed, usually handled by main helper)
?>

<div class="sgvx-settings-v2">
    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
        <div>
            <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Society Configuration</h1>
            <p class="text-secondary m-0 mt-1">Manage society profile, financial rules, and database operations.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="bg-primary bg-opacity-10 px-3 py-2 rounded-3 d-flex align-items-center gap-2 border border-primary border-opacity-10">
                <i class="bi bi-shield-check text-primary"></i>
                <span class="small fw-bold text-primary text-uppercase" style="font-size: 10px; letter-spacing: 0.05em;">Admin Control Panel</span>
            </div>
        </div>
    </div>

    <!-- Status Messages (Always outside cards for visibility) -->
    <div class="sgvx-messages-container mb-4">
        <?php if ( isset($_GET['migration_done']) ) : ?>
            <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3 p-4">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                    <div>
                        <div class="fw-bold">Sync Complete (JSON ➔ MySQL)</div>
                        <div class="small opacity-75"><?php echo esc_html(urldecode($_GET['stats'] ?? '')); ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ( isset($_GET['export_done']) ) : ?>
            <div class="alert bg-primary bg-opacity-10 text-primary border-primary border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3 p-4">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-cloud-arrow-up-fill text-primary fs-4"></i>
                    <div>
                        <div class="fw-bold">Sync Complete (MySQL ➔ JSON)</div>
                        <div class="small opacity-75"><?php echo esc_html(urldecode($_GET['stats'] ?? '')); ?></div>
                    </div>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ( isset($_GET['settings-updated']) ) : ?>
             <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3">
                 <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <span>Settings saved successfully.</span>
                 </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content Card -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
        
        <!-- Navigation Tabs (Integrated) -->
        <div class="px-4 px-md-5 bg-white border-bottom border-light">
            <ul class="nav nav-tabs border-0 gap-5" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab" style="background:none;">
                        <i class="bi bi-building me-2"></i>Society Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-secondary border-transparent" data-bs-toggle="tab" data-bs-target="#tab-bank" type="button" role="tab" style="background:none;">
                        <i class="bi bi-bank me-2"></i>Bank & Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-secondary border-transparent" data-bs-toggle="tab" data-bs-target="#tab-approval" type="button" role="tab" style="background:none;">
                        <i class="bi bi-check-all me-2"></i>Approval Workflow
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-secondary border-transparent" data-bs-toggle="tab" data-bs-target="#tab-database" type="button" role="tab" style="background:none;">
                        <i class="bi bi-database-fill me-2"></i>Sync & Database
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-secondary border-transparent" data-bs-toggle="tab" data-bs-target="#tab-portability" type="button" role="tab" style="background:none;">
                        <i class="bi bi-arrow-left-right me-2"></i>Data Portability
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-4 p-md-5">
            <div class="tab-content" id="settingsTabContent">
                
                <!-- Tab: Profile -->
                <div class="tab-pane fade show active" id="tab-profile">
                    <form method="post" action="options.php" class="sgvx-form-max">
                        <?php settings_fields( 'sgvx51_options_group' ); ?>
                        <!-- Preserve Bank Details -->
                        <input type="hidden" name="sgvx51_bank_name" value="<?php echo esc_attr( get_option('sgvx51_bank_name') ); ?>">
                        <input type="hidden" name="sgvx51_bank_account" value="<?php echo esc_attr( get_option('sgvx51_bank_account') ); ?>">
                        <input type="hidden" name="sgvx51_bank_ifsc" value="<?php echo esc_attr( get_option('sgvx51_bank_ifsc') ); ?>">
                        <input type="hidden" name="sgvx51_bank_upi" value="<?php echo esc_attr( get_option('sgvx51_bank_upi') ); ?>">
                        <input type="hidden" name="sgvx51_bank_qr" value="<?php echo esc_attr( get_option('sgvx51_bank_qr') ); ?>">
                        
                        <div class="mb-5">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Public Details</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Official Society Name</label>
                                    <input type="text" name="sgvx51_society_name" value="<?php echo esc_attr( get_option('sgvx51_society_name', 'Society Name') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Address Line 1</label>
                                    <input type="text" name="sgvx51_society_address_line1" value="<?php echo esc_attr( get_option('sgvx51_society_address_line1') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Address Line 2</label>
                                    <input type="text" name="sgvx51_society_address_line2" value="<?php echo esc_attr( get_option('sgvx51_society_address_line2') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">City</label>
                                    <input type="text" name="sgvx51_society_city" value="<?php echo esc_attr( get_option('sgvx51_society_city') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Pincode</label>
                                    <input type="text" name="sgvx51_society_pincode" value="<?php echo esc_attr( get_option('sgvx51_society_pincode') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Office Contact (Email/Phone)</label>
                                    <input type="text" name="sgvx51_society_contact" value="<?php echo esc_attr( get_option('sgvx51_society_contact') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Finance Rules</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Base Maintenance (₹)</label>
                                    <div class="input-group flex-nowrap">
                                        <span class="input-group-text bg-light border-0 text-muted rounded-start-3">₹</span>
                                        <input type="number" name="sgvx51_maintenance_amount" value="<?php echo esc_attr( get_option('sgvx51_maintenance_amount', '5000') ); ?>" class="form-control shadow-none border-0 bg-light rounded-end-3 fw-bold">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Bank Opening Balance</label>
                                    <input type="number" step="0.01" name="sgvx51_opening_bank" value="<?php echo esc_attr( get_option('sgvx51_opening_bank', '0') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Cash Opening Balance</label>
                                    <input type="number" step="0.01" name="sgvx51_opening_cash" value="<?php echo esc_attr( get_option('sgvx51_opening_cash', '0') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top border-light">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Profile Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Bank -->
                <div class="tab-pane fade" id="tab-bank">
                    <form method="post" action="options.php" class="sgvx-form-max">
                        <?php settings_fields( 'sgvx51_options_group' ); ?>
                        <!-- Preserve Society Profile Settings -->
                        <input type="hidden" name="sgvx51_society_name" value="<?php echo esc_attr( get_option('sgvx51_society_name', 'Society Name') ); ?>">
                        <input type="hidden" name="sgvx51_society_address_line1" value="<?php echo esc_attr( get_option('sgvx51_society_address_line1') ); ?>">
                        <input type="hidden" name="sgvx51_society_address_line2" value="<?php echo esc_attr( get_option('sgvx51_society_address_line2') ); ?>">
                        <input type="hidden" name="sgvx51_society_city" value="<?php echo esc_attr( get_option('sgvx51_society_city') ); ?>">
                        <input type="hidden" name="sgvx51_society_pincode" value="<?php echo esc_attr( get_option('sgvx51_society_pincode') ); ?>">
                        <input type="hidden" name="sgvx51_society_contact" value="<?php echo esc_attr( get_option('sgvx51_society_contact') ); ?>">
                        <input type="hidden" name="sgvx51_maintenance_amount" value="<?php echo esc_attr( get_option('sgvx51_maintenance_amount', '5000') ); ?>">
                        <input type="hidden" name="sgvx51_opening_bank" value="<?php echo esc_attr( get_option('sgvx51_opening_bank', '0') ); ?>">
                        <input type="hidden" name="sgvx51_opening_cash" value="<?php echo esc_attr( get_option('sgvx51_opening_cash', '0') ); ?>">
                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Beneficiary Details</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">Primary Bank Name</label>
                                    <input type="text" name="sgvx51_bank_name" value="<?php echo esc_attr( get_option('sgvx51_bank_name') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Account Number</label>
                                    <input type="text" name="sgvx51_bank_account" value="<?php echo esc_attr( get_option('sgvx51_bank_account') ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">IFSC Code</label>
                                    <input type="text" name="sgvx51_bank_ifsc" value="<?php echo esc_attr( get_option('sgvx51_bank_ifsc') ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace text-uppercase">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">UPI ID for Direct Transfers</label>
                                    <input type="text" name="sgvx51_bank_upi" value="<?php echo esc_attr( get_option('sgvx51_bank_upi') ); ?>" class="form-control shadow-none rounded-3 border-light text-primary fw-bold">
                                </div>
                                <div class="col-12 mt-4">
                                    <label class="form-label small fw-bold text-secondary">Payment QR Image</label>
                                    <div class="d-flex align-items-start gap-4 p-3 bg-light rounded-3 border border-light">
                                        <div id="qr-preview-container" class="bg-white border border-light rounded-3 shadow-sm d-flex align-items-center justify-content-center p-2" style="width: 100px; height: 100px;">
                                            <?php $qr_url = get_option('sgvx51_bank_qr'); ?>
                                            <?php if($qr_url): ?>
                                                <img src="<?php echo esc_url($qr_url); ?>" class="img-fluid rounded-3">
                                            <?php else: ?>
                                                <span class="text-muted small">NO QR</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="hidden" id="sgvx51_bank_qr" name="sgvx51_bank_qr" value="<?php echo esc_attr($qr_url); ?>">
                                            <div class="d-flex flex-column gap-2">
                                                <button type="button" id="btn-upload-qr" class="btn btn-sm btn-outline-primary fw-bold rounded-3">Select / Upload Image</button>
                                                <?php if($qr_url): ?>
                                                    <button type="button" id="btn-remove-qr" class="btn btn-sm btn-link text-danger text-decoration-none p-0 text-start small fw-bold">Remove Image</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="small text-muted mt-2">Recommended: Square image (500x500px)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-3 border-top border-light">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Bank Registry</button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Approval Workflow -->
                <div class="tab-pane fade" id="tab-approval">
                    <form method="post" action="options.php" class="sgvx-form-max">
                        <?php settings_fields( 'sgvx51_options_group' ); ?>
                        <!-- Preserve Society Profile Settings -->
                        <input type="hidden" name="sgvx51_society_name" value="<?php echo esc_attr( get_option('sgvx51_society_name', 'Society Name') ); ?>">
                        <input type="hidden" name="sgvx51_society_address_line1" value="<?php echo esc_attr( get_option('sgvx51_society_address_line1') ); ?>">
                        <input type="hidden" name="sgvx51_society_address_line2" value="<?php echo esc_attr( get_option('sgvx51_society_address_line2') ); ?>">
                        <input type="hidden" name="sgvx51_society_city" value="<?php echo esc_attr( get_option('sgvx51_society_city') ); ?>">
                        <input type="hidden" name="sgvx51_society_pincode" value="<?php echo esc_attr( get_option('sgvx51_society_pincode') ); ?>">
                        <input type="hidden" name="sgvx51_society_contact" value="<?php echo esc_attr( get_option('sgvx51_society_contact') ); ?>">
                        <input type="hidden" name="sgvx51_maintenance_amount" value="<?php echo esc_attr( get_option('sgvx51_maintenance_amount', '5000') ); ?>">
                        <input type="hidden" name="sgvx51_opening_bank" value="<?php echo esc_attr( get_option('sgvx51_opening_bank', '0') ); ?>">
                        <input type="hidden" name="sgvx51_opening_cash" value="<?php echo esc_attr( get_option('sgvx51_opening_cash', '0') ); ?>">
                        <!-- Preserve Bank Details -->
                        <input type="hidden" name="sgvx51_bank_name" value="<?php echo esc_attr( get_option('sgvx51_bank_name') ); ?>">
                        <input type="hidden" name="sgvx51_bank_account" value="<?php echo esc_attr( get_option('sgvx51_bank_account') ); ?>">
                        <input type="hidden" name="sgvx51_bank_ifsc" value="<?php echo esc_attr( get_option('sgvx51_bank_ifsc') ); ?>">
                        <input type="hidden" name="sgvx51_bank_upi" value="<?php echo esc_attr( get_option('sgvx51_bank_upi') ); ?>">
                        <input type="hidden" name="sgvx51_bank_qr" value="<?php echo esc_attr( get_option('sgvx51_bank_qr') ); ?>">
                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Verification Policies</h5>
                            <p class="small text-secondary mb-4">Control whether resident-submitted changes require admin oversight.</p>
                            
                            <div class="d-flex flex-column gap-3">
                                <?php 
                                $policies = [
                                    'sgvx51_approval_family' => 'Resident Family Members',
                                    'sgvx51_approval_help'   => 'Domestic Help & Personal Staff',
                                    'sgvx51_approval_vehicle' => 'Private Vehicles',
                                    'sgvx51_approval_facility' => 'Facility & Amenity Bookings'
                                ];
                                foreach($policies as $opt => $label): ?>
                                    <div class="card border border-light shadow-none bg-light bg-opacity-50 rounded-3">
                                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                            <div class="fw-bold text-dark small"><?php echo $label; ?></div>
                                            <select name="<?php echo $opt; ?>" class="form-select form-select-sm shadow-none w-auto rounded-pill px-4 fw-bold border-0 bg-white">
                                                <option value="manual" <?php selected(get_option($opt, 'manual'), 'manual'); ?>>Approver Insight (Manual)</option>
                                                <option value="auto" <?php selected(get_option($opt), 'auto'); ?>>Direct Entry (Auto)</option>
                                            </select>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="pt-3 border-top border-light">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Update Workflow</button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Database -->
                <div class="tab-pane fade" id="tab-database">
                    <div class="sgvx-form-max">
                        <!-- Mode Switch -->
                        <!-- <div class="card border border-primary border-opacity-10 bg-primary bg-opacity-10 rounded-3 mb-4 overflow-hidden">
                            <div class="card-body p-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <h5 class="fw-bold text-primary m-0">Primary Storage Mode</h5>
                                    <p class="small text-primary opacity-75 m-0 mt-1">MySQL is recommended for high performance societies.</p>
                                </div>
                                <form method="post" action="options.php" class="d-flex gap-2">
                                    <?php settings_fields( 'sgvx51_options_group' ); ?>
                                    <select name="sgvx51_storage_mode" class="form-select form-select-sm shadow-none w-auto rounded-3 fw-bold border-0 px-3">
                                        <option value="mysql" <?php selected(get_option('sgvx51_storage_mode', 'mysql'), 'mysql'); ?>>MySQL (Engine)</option>
                                        <option value="json" <?php selected(get_option('sgvx51_storage_mode'), 'json'); ?>>JSON (Legacy)</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3 rounded-3">Apply Mode</button>
                                </form>
                            </div>
                        </div> -->

                        <!-- <div class="row g-4 mb-5">
                            <div class="col-md-6">
                                <div class="card h-100 text-white border border-success bg-success rounded-3 border-opacity-25">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <h6 class="fw-bold text-uppercase opacity-75 small mb-3">Legacy Import</h6>
                                            <h3 class="fw-bold mb-2">JSON ➔ MySQL</h3>
                                            <p class="small opacity-75">Transfer all flat-file records into the structural relational database. Skip exists.</p>
                                        </div>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <input type="hidden" name="action" value="sgvx51_migrate_json">
                                            <?php wp_nonce_field( 'sgvx51_migrate_nonce' ); ?>
                                            <button type="submit" class="btn btn-white w-100 fw-bold border-0 py-2 rounded-3 text-success">Start Migration</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 text-white border border-warning bg-warning rounded-3 border-opacity-25">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <h6 class="fw-bold text-uppercase opacity-75 small mb-3">Backup Export</h6>
                                            <h3 class="fw-bold mb-2">MySQL ➔ JSON</h3>
                                            <p class="small opacity-75">Dump entire structural database back into portable JSON files. Overwrites files.</p>
                                        </div>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <input type="hidden" name="action" value="sgvx51_export_json">
                                            <?php wp_nonce_field( 'sgvx51_export_nonce' ); ?>
                                            <button type="submit" class="btn btn-white w-100 fw-bold border-0 py-2 rounded-3 text-warning">Start Export</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div> -->

                        <div class="card border-0 shadow-sm bg-danger bg-opacity-10 rounded-3 overflow-hidden">
                            <div class="card-body p-4 border-start border-4 border-danger">
                                <h5 class="fw-bold text-danger mb-1">Maintenance Area (Caution)</h5>
                                <p class="small text-danger opacity-75 mb-4">These actions permanently remove all societal data from selected storage.</p>
                                <div class="d-flex flex-wrap gap-2">
                                     <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                        <input type="hidden" name="action" value="sgvx51_reset_db">
                                        <input type="hidden" name="reset_type" value="mysql">
                                        <?php wp_nonce_field( 'sgvx51_reset_nonce' ); ?>
                                        <button type="submit" onclick="return confirm('Wipe entire MySQL schema content? (Non-reversible)')" class="btn btn-sm btn-danger fw-bold px-3 rounded-3 shadow-none">Purge MySQL Tables</button>
                                    </form>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                        <input type="hidden" name="action" value="sgvx51_reset_db">
                                        <input type="hidden" name="reset_type" value="json">
                                        <?php wp_nonce_field( 'sgvx51_reset_nonce' ); ?>
                                        <button type="submit" onclick="return confirm('Delete all JSON data files? (Non-reversible)')" class="btn btn-sm btn-outline-danger fw-bold px-3 rounded-3 shadow-none">Delete Storage Files</button>
                                    </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Tab: Data Portability -->
                <div class="tab-pane fade" id="tab-portability">
                    <div class="sgvx-form-max">
                        
                        <!-- Import/Export Messages -->
                        <?php if ( isset($_GET['imported']) ) : ?>
                            <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3 mb-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                    <div>
                                        <div class="fw-bold">Import Successful</div>
                                        <div class="small opacity-75">Processed <?php echo intval($_GET['imported']); ?> records. (Errors: <?php echo intval($_GET['errors'] ?? 0); ?>)</div>
                                    </div>
                                </div>
                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ( isset($_GET['error']) && $_GET['error'] === 'no_file' ) : ?>
                            <div class="alert bg-danger bg-opacity-10 text-danger border-danger border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3 mb-4">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                                    <span class="fw-bold">Error: No file selected for import.</span>
                                </div>
                                <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row g-4 mb-5">
                            <!-- Export Card -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm bg-light rounded-3 overflow-hidden">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                                                    <i class="bi bi-cloud-download-fill fs-4"></i>
                                                </div>
                                                <h5 class="fw-bold text-dark m-0">Export Data</h5>
                                            </div>
                                            <p class="text-secondary small mb-4">
                                                Download a complete archive of your society's data. 
                                                The ZIP file contains individual CSV files for each table (Residents, Vehicles, Expenses, etc.) and a full JSON dump for backup.
                                            </p>
                                        </div>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                                            <input type="hidden" name="action" value="sgvx51_export_data">
                                            <?php wp_nonce_field( 'sgvx51_export_nonce' ); ?>
                                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-sm">
                                                <i class="bi bi-file-earmark-zip me-2"></i>Download Data Archive (.zip)
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Import Card -->
                            <div class="col-md-6">
                                <div class="card h-100 border-0 shadow-sm bg-white rounded-3 overflow-hidden border">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-center gap-3 mb-3">
                                                <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                                                    <i class="bi bi-cloud-upload-fill fs-4"></i>
                                                </div>
                                                <h5 class="fw-bold text-dark m-0">Import CSV</h5>
                                            </div>
                                            <p class="text-secondary small mb-4">
                                                Bulk import records into a specific module. The CSV must have headers matching the database columns.
                                            </p>
                                        </div>
                                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="sgvx51_import_data">
                                            <?php wp_nonce_field( 'sgvx51_import_nonce' ); ?>
                                            
                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-secondary">Target Module</label>
                                                <select name="target_table" class="form-select shadow-none rounded-3 border-light bg-light">
                                                    <?php 
                                                    $tables = SGVX51_DB_Router::TABLES;
                                                    foreach($tables as $t) {
                                                        // Pretty name
                                                        $name = ucwords(str_replace('_', ' ', $t));
                                                        echo "<option value='{$t}'>{$name}</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label small fw-bold text-secondary">Select CSV File</label>
                                                <input type="file" name="import_file" accept=".csv" class="form-control shadow-none rounded-3 border-light" required>
                                            </div>

                                            <button type="submit" class="btn btn-outline-success w-100 fw-bold py-2 rounded-3">
                                                <i class="bi bi-upload me-2"></i>Import Records
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                </div>

            </div>
        </div>
    </div>
</div>


<script>
// Tab Persistence or initial setup if needed (Bootstrap Tab API is native)
document.addEventListener('DOMContentLoaded', () => {
    // Media Uploader for QR Code (Uses WordPress Media Library)
    const btnUpload = document.getElementById('btn-upload-qr');
    if(btnUpload) {
        btnUpload.addEventListener('click', (e) => {
            e.preventDefault();
            const frame = wp.media({
                title: 'Select Society Payment QR Code',
                button: { text: 'Use this Image' },
                multiple: false
            });
            frame.on('select', () => {
                const attachment = frame.state().get('selection').first().toJSON();
                document.getElementById('sgvx51_bank_qr').value = attachment.url;
                const preview = document.getElementById('qr-preview-container');
                preview.innerHTML = `<img src="${attachment.url}" class="img-fluid rounded">`;
                location.reload(); // Reload to show remove button or update state simply
            });
            frame.open();
        });
    }

    const btnRemove = document.getElementById('btn-remove-qr');
    if(btnRemove) {
        btnRemove.addEventListener('click', () => {
            if(confirm('Remove QR code image?')) {
                document.getElementById('sgvx51_bank_qr').value = '';
                location.reload();
            }
        });
    }
});
</script>
