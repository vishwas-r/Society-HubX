<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * View: Society Settings (Bootstrap Migration)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Assets for Settings (if any specific ones needed, usually handled by main helper)
// Data for Communication Tab
$db = SHUBX51_Plugin::get_instance()->db;
$channels  = $db->get('notification_channels');
$events    = $db->get('notification_events');
$templates = $db->get('notification_templates');

?>


<div class="shubx-settings-v2">
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
    <div class="shubx-messages-container mb-4">
        <?php if ( isset($_GET['migration_done']) ) : ?>
            <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-10 alert-dismissible shadow-sm border-0 rounded-3 p-4">
                <div class="d-flex align-items-center gap-3">
                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                    <div>
                        <div class="fw-bold">Sync Complete (JSON ➔ MySQL)</div>
                        <div class="small opacity-75"><?php echo esc_html( sanitize_text_field( urldecode( $_GET['stats'] ?? '' ) ) ); ?></div>
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
                        <div class="small opacity-75"><?php echo esc_html( sanitize_text_field( urldecode( $_GET['stats'] ?? '' ) ) ); ?></div>
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
        <div class="px-2 bg-white border-bottom border-light overflow-x-auto no-scrollbar">
            <ul class="nav nav-tabs border-0 gap-5 text-nowrap flex-nowrap" id="shubx-settings-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-profile" class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary" onclick="switchSettingsTab('profile')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-building me-2"></i>Society Profile
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-bank" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('bank')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-bank me-2"></i>Bank & Payments
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-approval" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('approval')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-check-all me-2"></i>Approval Workflow
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-modules" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('modules')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-grid-fill me-2"></i>Modules & Features
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-communication" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('communication')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-chat-left-dots me-2"></i>Communication
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-maintenance" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('maintenance')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-tools me-2"></i>Data & Maintenance
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button id="tab-btn-push" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('push')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-bell-fill me-2"></i>Push Notifications
                    </button>
                </li>
                <li class="nav-item d-none" role="presentation">
                    <button id="tab-btn-privacy" class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent" onclick="switchSettingsTab('privacy')" type="button" role="tab" style="background:none;">
                        <i class="bi bi-shield-lock me-2"></i>Privacy & DPDP
                    </button>
                </li>
                <?php do_action( 'shubx51_settings_tabs' ); ?>
            </ul>
        </div>
        
        <div class="card-body p-4 p-md-5">
            <div class="tab-content" id="settingsTabContent">
                
                <!-- Tab: Profile -->
                <div class="settings-tab-pane" id="tab-content-profile">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'shubx51_options_group' ); ?>
                        <!-- Preserve Bank Details -->
                        <input type="hidden" name="shubx51_bank_name" value="<?php echo esc_attr( get_option('shubx51_bank_name') ); ?>">
                        <input type="hidden" name="shubx51_bank_account" value="<?php echo esc_attr( get_option('shubx51_bank_account') ); ?>">
                        <input type="hidden" name="shubx51_bank_ifsc" value="<?php echo esc_attr( get_option('shubx51_bank_ifsc') ); ?>">
                        <input type="hidden" name="shubx51_bank_upi" value="<?php echo esc_attr( get_option('shubx51_bank_upi') ); ?>">
                        <input type="hidden" name="shubx51_bank_qr" value="<?php echo esc_attr( get_option('shubx51_bank_qr') ); ?>">
                        
                        <div class="mb-5">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Public Details</h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Official Society Name</label>
                                    <input type="text" name="shubx51_society_name" value="<?php echo esc_attr( get_option('shubx51_society_name', 'Society Name') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Address Line 1</label>
                                    <input type="text" name="shubx51_society_address_line1" value="<?php echo esc_attr( get_option('shubx51_society_address_line1') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Address Line 2</label>
                                    <input type="text" name="shubx51_society_address_line2" value="<?php echo esc_attr( get_option('shubx51_society_address_line2') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">City</label>
                                    <input type="text" name="shubx51_society_city" value="<?php echo esc_attr( get_option('shubx51_society_city') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Pincode</label>
                                    <input type="text" name="shubx51_society_pincode" value="<?php echo esc_attr( get_option('shubx51_society_pincode') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Office Contact (Email/Phone)</label>
                                    <input type="text" name="shubx51_society_contact" value="<?php echo esc_attr( get_option('shubx51_society_contact') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <h5 class="fw-bold text-primary mb-2 border-bottom border-light pb-2">Branding & Color Theme</h5>
                            <p class="small text-muted mb-4">Select your society's primary brand palette and default appearance. The selected palette dynamically styles buttons, active tabs, highlights, and badges across both Light and Dark themes.</p>
                            
                            <?php
                                $current_palette = get_option('shubx51_color_palette', 'orange');
                                $palettes = [
                                    'orange'  => ['name' => 'Sunset Orange', 'color' => '#ea580c', 'dark_color' => '#f97316', 'desc' => 'Warm, High-Energy, Modern'],
                                    'indigo'  => ['name' => 'Royal Indigo',  'color' => '#4f46e5', 'dark_color' => '#6366f1', 'desc' => 'Tech, Premium SaaS, Sleek'],
                                    'emerald' => ['name' => 'Emerald Forest','color' => '#059669', 'dark_color' => '#10b981', 'desc' => 'Fresh, Natural, Sustainable'],
                                    'ocean'   => ['name' => 'Ocean Blue',    'color' => '#0284c7', 'dark_color' => '#38bdf8', 'desc' => 'Classic, Professional, Crisp'],
                                    'rose'    => ['name' => 'Crimson Rose',  'color' => '#e11d48', 'dark_color' => '#fb7185', 'desc' => 'Bold, Elegant, Contemporary'],
                                ];
                                $default_theme = get_option('shubx51_default_theme', 'light');
                            ?>
                            <div class="row g-3 mb-4">
                                <?php foreach ( $palettes as $key => $p ): ?>
                                    <div class="col-6 col-md-4 col-xl-2.4">
                                        <label class="shubx-palette-card card h-100 p-3 rounded-3 border <?php echo $current_palette === $key ? 'border-2 border-primary shadow-sm' : 'border-light'; ?> cursor-pointer transition-all hover-translate-y" style="cursor: pointer;" onclick="shubxSelectPalette('<?php echo esc_js($key); ?>')">
                                            <input type="radio" name="shubx51_color_palette" value="<?php echo esc_attr($key); ?>" <?php checked($current_palette, $key); ?> class="d-none shubx-palette-radio" id="palette-radio-<?php echo esc_attr($key); ?>">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex gap-1">
                                                    <span class="rounded-circle d-inline-block shadow-sm" style="width: 22px; height: 22px; background-color: <?php echo esc_attr($p['color']); ?>;" title="Light mode tone"></span>
                                                    <span class="rounded-circle d-inline-block shadow-sm" style="width: 22px; height: 22px; background-color: <?php echo esc_attr($p['dark_color']); ?>;" title="Dark mode tone"></span>
                                                </div>
                                                <i class="bi bi-check-circle-fill text-primary fs-5 <?php echo $current_palette === $key ? '' : 'd-none'; ?> shubx-palette-check"></i>
                                            </div>
                                            <div class="fw-bold small text-dark mb-1"><?php echo esc_html($p['name']); ?></div>
                                            <div class="text-muted" style="font-size: 11px; line-height: 1.3;"><?php echo esc_html($p['desc']); ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Default Theme Mode</label>
                                    <select name="shubx51_default_theme" class="form-select shadow-none rounded-3 border-light" onchange="shubxSelectDefaultTheme(this.value)">
                                        <option value="light" <?php selected($default_theme, 'light'); ?>>☀️ Light Mode (Default)</option>
                                        <option value="dark" <?php selected($default_theme, 'dark'); ?>>🌙 Dark Mode</option>
                                    </select>
                                    <div class="form-text small text-muted">New visitors or users without preference cookies will default to this appearance.</div>
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
                                        <input type="number" name="shubx51_maintenance_amount" value="<?php echo esc_attr( get_option('shubx51_maintenance_amount', '5000') ); ?>" class="form-control shadow-none border-0 bg-light rounded-end-3 fw-bold">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Bank Opening Balance</label>
                                    <input type="number" step="0.01" name="shubx51_opening_bank" value="<?php echo esc_attr( get_option('shubx51_opening_bank', '0') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Cash Opening Balance</label>
                                    <input type="number" step="0.01" name="shubx51_opening_cash" value="<?php echo esc_attr( get_option('shubx51_opening_cash', '0') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-3 border-bottom border-light pb-2">Maintenance Billing Formula & Auto-Invoicing</h5>
                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Billing Calculation Mode</label>
                                    <select name="shubx51_billing_calc_type" class="form-select shadow-none border-light rounded-3">
                                        <option value="fixed" <?php selected( get_option('shubx51_billing_calc_type', 'fixed'), 'fixed' ); ?>>Fixed Flat Amount</option>
                                        <option value="sqft" <?php selected( get_option('shubx51_billing_calc_type', 'fixed'), 'sqft' ); ?>>Formula (Sq. Ft + Components)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Rate Per Sq. Ft (₹)</label>
                                    <input type="number" step="0.01" name="shubx51_billing_rate_per_sqft" value="<?php echo esc_attr( get_option('shubx51_billing_rate_per_sqft', '3.50') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Fixed Service Base (₹)</label>
                                    <input type="number" step="0.01" name="shubx51_billing_fixed_base" value="<?php echo esc_attr( get_option('shubx51_billing_fixed_base', '500') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Sinking Fund Component (₹)</label>
                                    <input type="number" step="0.01" name="shubx51_billing_sinking_fund" value="<?php echo esc_attr( get_option('shubx51_billing_sinking_fund', '250') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Utility / Water Component (₹)</label>
                                    <input type="number" step="0.01" name="shubx51_billing_utility_charge" value="<?php echo esc_attr( get_option('shubx51_billing_utility_charge', '200') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">Recurring Auto-Invoicing (1st of Month)</label>
                                    <select name="shubx51_auto_invoicing_enabled" class="form-select shadow-none border-light rounded-3">
                                        <option value="0" <?php selected( get_option('shubx51_auto_invoicing_enabled', '0'), '0' ); ?>>Disabled (Manual Generation Only)</option>
                                        <option value="1" <?php selected( get_option('shubx51_auto_invoicing_enabled', '0'), '1' ); ?>>Enabled (Auto-Generate on 1st)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 p-3 bg-light rounded-3 border border-light">
                                <div class="col-md-4">
                                    <div class="form-check form-switch pt-2">
                                        <input class="form-check-input" type="checkbox" name="shubx51_gst_enabled" value="1" id="gstSwitch" <?php checked( get_option('shubx51_gst_enabled', '0'), '1' ); ?>>
                                        <label class="form-check-label fw-bold text-dark small" for="gstSwitch">Enable GST Billing</label>
                                    </div>
                                    <div class="form-text small">Applies statutory GST if bill exceeds exemption limit.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">GST Rate (%)</label>
                                    <input type="number" step="0.1" name="shubx51_gst_rate" value="<?php echo esc_attr( get_option('shubx51_gst_rate', '18') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary">GST Exemption Threshold (₹)</label>
                                    <input type="number" step="1" name="shubx51_gst_threshold" value="<?php echo esc_attr( get_option('shubx51_gst_threshold', '7500') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top border-light">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Profile Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Bank -->
                <div class="settings-tab-pane hidden" id="tab-content-bank">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'shubx51_options_group' ); ?>
                        <!-- Preserve Society Profile Settings -->
                        <input type="hidden" name="shubx51_society_name" value="<?php echo esc_attr( get_option('shubx51_society_name', 'Society Name') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line1" value="<?php echo esc_attr( get_option('shubx51_society_address_line1') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line2" value="<?php echo esc_attr( get_option('shubx51_society_address_line2') ); ?>">
                        <input type="hidden" name="shubx51_society_city" value="<?php echo esc_attr( get_option('shubx51_society_city') ); ?>">
                        <input type="hidden" name="shubx51_society_pincode" value="<?php echo esc_attr( get_option('shubx51_society_pincode') ); ?>">
                        <input type="hidden" name="shubx51_society_contact" value="<?php echo esc_attr( get_option('shubx51_society_contact') ); ?>">
                        <input type="hidden" name="shubx51_maintenance_amount" value="<?php echo esc_attr( get_option('shubx51_maintenance_amount', '5000') ); ?>">
                        <input type="hidden" name="shubx51_opening_bank" value="<?php echo esc_attr( get_option('shubx51_opening_bank', '0') ); ?>">
                        <input type="hidden" name="shubx51_opening_cash" value="<?php echo esc_attr( get_option('shubx51_opening_cash', '0') ); ?>">
                        <input type="hidden" name="shubx51_color_palette" value="<?php echo esc_attr( get_option('shubx51_color_palette', 'orange') ); ?>">
                        <input type="hidden" name="shubx51_default_theme" value="<?php echo esc_attr( get_option('shubx51_default_theme', 'light') ); ?>">
                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Beneficiary Details</h5>
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">Primary Bank Name</label>
                                    <input type="text" name="shubx51_bank_name" value="<?php echo esc_attr( get_option('shubx51_bank_name') ); ?>" class="form-control shadow-none rounded-3 border-light">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Account Number</label>
                                    <input type="text" name="shubx51_bank_account" value="<?php echo esc_attr( get_option('shubx51_bank_account') ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">IFSC Code</label>
                                    <input type="text" name="shubx51_bank_ifsc" value="<?php echo esc_attr( get_option('shubx51_bank_ifsc') ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace text-uppercase">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-secondary">UPI ID for Direct Transfers</label>
                                    <input type="text" name="shubx51_bank_upi" value="<?php echo esc_attr( get_option('shubx51_bank_upi') ); ?>" class="form-control shadow-none rounded-3 border-light text-primary fw-bold">
                                </div>
                                <div class="col-12 mt-4">
                                    <label class="form-label small fw-bold text-secondary">Payment QR Image</label>
                                    <div class="d-flex align-items-start gap-4 p-3 bg-light rounded-3 border border-light">
                                        <div id="qr-preview-container" class="bg-white border border-light rounded-3 shadow-sm d-flex align-items-center justify-content-center p-2" style="width: 100px; height: 100px;">
                                            <?php $qr_url = get_option('shubx51_bank_qr'); ?>
                                            <?php if($qr_url): ?>
                                                <img src="<?php echo esc_url($qr_url); ?>" class="img-fluid rounded-3">
                                            <?php else: ?>
                                                <span class="text-muted small">NO QR</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <input type="hidden" id="shubx51_bank_qr" name="shubx51_bank_qr" value="<?php echo esc_attr($qr_url); ?>">
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

                <!-- Tab: Communication (Migrated from Notifications) -->
                <div class="settings-tab-pane hidden" id="tab-content-communication">
                    <div class="accordion accordion-flush" id="communicationAccordion">
                        
                        <!-- 1. Delivery Channels -->
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button fw-bold py-4 px-4 bg-white text-dark shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseChannels">
                                    <i class="bi bi-broadcast-pin text-primary me-3 fs-5"></i> Delivery Channels
                                </button>
                            </h2>
                            <div id="collapseChannels" class="accordion-collapse collapse show" data-bs-parent="#communicationAccordion">
                                <div class="accordion-body p-4 bg-slate-50">
                                    <div class="row g-4">
                                        <?php foreach($channels as $channel): 
                                            $config = json_decode($channel['config'], true) ?: [];
                                            $slug = $channel['channel_slug'];
                                            $icon = 'bi-envelope';
                                            $color = 'primary';
                                            if($slug === 'whatsapp') { $icon = 'bi-whatsapp'; $color = 'success'; }
                                            if($slug === 'inapp') { $icon = 'bi-app-indicator'; $color = 'info'; }
                                        ?>
                                        <div class="col-md-4">
                                            <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden">
                                                <div class="card-body p-4 d-flex flex-column">
                                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                                        <div class="p-3 bg-<?php echo esc_html( $color ); ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                            <i class="bi <?php echo esc_html( $icon ); ?> text-<?php echo esc_html( $color ); ?> fs-4"></i>
                                                        </div>
                                                        <label class="shubx-premium-toggle">
                                                            <input type="checkbox" class="shubx-channel-toggle" data-channel="<?php echo esc_html( $slug ); ?>" <?php checked($channel['is_active'], 1); ?>/>
                                                            <span class="slider"></span>
                                                        </label>
                                                    </div>
                                                    <h6 class="fw-bold text-slate-900 mb-1"><?php echo esc_html( ucfirst($slug) ); ?></h6>
                                                    <p class="text-slate-500 x-small mb-4 flex-grow-1">
                                                        <?php if($slug === 'email') echo 'Send alerts via WP Mail or Gmail API.'; ?>
                                                        <?php if($slug === 'whatsapp') echo 'Real-time alerts via Twilio WhatsApp API.'; ?>
                                                        <?php if($slug === 'inapp') echo 'Display alerts directly on resident dashboards.'; ?>
                                                    </p>
                                                    <button class="btn btn-outline-secondary border-slate-200 text-slate-700 fw-bold small w-100 rounded-3 py-2 shubx-configure-channel" data-channel="<?php echo esc_html( $slug ); ?>">
                                                        <i class="bi bi-gear me-2"></i>Configure
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Event Mapping -->
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold py-4 px-4 bg-white text-dark shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMapping">
                                    <i class="bi bi-signpost-split text-primary me-3 fs-5"></i> Automated Trigger Mapping
                                </button>
                            </h2>
                            <div id="collapseMapping" class="accordion-collapse collapse" data-bs-parent="#communicationAccordion">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0 small">
                                            <thead class="bg-light bg-opacity-50 border-bottom">
                                                <tr>
                                                    <th class="ps-4 py-3 fw-bold text-slate-500 text-uppercase">Event</th>
                                                    <th class="py-3 fw-bold text-slate-500 text-uppercase">Module</th>
                                                    <th class="py-3 fw-bold text-slate-500 text-center text-uppercase">In-App</th>
                                                    <th class="py-3 fw-bold text-slate-500 text-center text-uppercase">Email</th>
                                                    <th class="py-3 fw-bold text-slate-500 text-center text-uppercase">WhatsApp</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($events as $event): 
                                                    $enabled_channels = explode(',', $event['default_channels']);
                                                ?>
                                                <tr>
                                                    <td class="ps-4 py-3">
                                                        <div class="fw-bold text-slate-900"><?php echo esc_html( str_replace('_', ' ', ucfirst($event['event_slug'])) ); ?></div>
                                                        <div class="text-slate-400 x-small font-monospace"><?php echo esc_html( $event['event_slug'] ); ?></div>
                                                    </td>
                                                    <td><span class="badge bg-slate-100 text-slate-600 border border-slate-200 rounded-pill px-2"><?php echo esc_html( ucfirst($event['module']) ); ?></span></td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center">
                                                            <label class="shubx-premium-toggle">
                                                                <input type="checkbox" class="shubx-mapping-toggle" data-event="<?php echo esc_html( $event['event_slug'] ); ?>" data-channel="inapp" <?php checked(in_array('inapp', $enabled_channels)); ?>/>
                                                                <span class="slider"></span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center">
                                                            <label class="shubx-premium-toggle">
                                                                <input type="checkbox" class="shubx-mapping-toggle" data-event="<?php echo esc_html( $event['event_slug'] ); ?>" data-channel="email" <?php checked(in_array('email', $enabled_channels)); ?>/>
                                                                <span class="slider"></span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center">
                                                            <label class="shubx-premium-toggle">
                                                                <input type="checkbox" class="shubx-mapping-toggle" data-event="<?php echo esc_html( $event['event_slug'] ); ?>" data-channel="whatsapp" <?php checked(in_array('whatsapp', $enabled_channels)); ?>/>
                                                                <span class="slider"></span>
                                                            </label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Message Templates -->
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold py-4 px-4 bg-white text-dark shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTemplates">
                                    <i class="bi bi-file-earmark-diff text-primary me-3 fs-5"></i> Content Templates
                                </button>
                            </h2>
                            <div id="collapseTemplates" class="accordion-collapse collapse" data-bs-parent="#communicationAccordion">
                                <div class="accordion-body p-4 bg-slate-50">
                                    <div class="row g-3">
                                        <?php foreach($templates as $template): ?>
                                        <div class="col-md-6">
                                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                                <div class="card-header bg-white border-bottom px-4 py-3 d-flex align-items-center justify-content-between">
                                                    <h6 class="fw-bold text-slate-900 m-0 small text-truncate"><?php echo esc_html( str_replace('_', ' ', ucfirst($template['event_slug'])) ); ?></h6>
                                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2 py-1 x-small fw-bold border border-primary-subtle">
                                                        <?php echo esc_html( strtoupper($template['channel']) ); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body p-4">
                                                    <div class="bg-light rounded-4 p-3 border mb-3">
                                                        <p class="text-slate-600 x-small m-0 line-clamp-3 font-monospace"><?php echo esc_html($template['content']); ?></p>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-slate-400 x-small fw-medium">Version <?php echo esc_html( $template['version'] ); ?></span>
                                                        <button class="btn btn-sm btn-link text-primary fw-bold p-0 x-small text-decoration-none shubx-edit-template" data-id="<?php echo esc_html( $template['id'] ); ?>">
                                                            <i class="bi bi-pencil-square me-1"></i> Edit Content
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Log Governance -->
                        <div class="accordion-item border-0 mb-3 shadow-sm rounded-4 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-bold py-4 px-4 bg-white text-dark shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#collapseGovernance">
                                    <i class="bi bi-shield-lock text-primary me-3 fs-5"></i> Data Governance
                                </button>
                            </h2>
                            <div id="collapseGovernance" class="accordion-collapse collapse" data-bs-parent="#communicationAccordion">
                                <div class="accordion-body p-4">
                                    <form method="post" action="options.php">
                                        <?php settings_fields( 'shubx51_options_group' ); ?>
                                        <div class="row g-4">
                                            <div class="col-md-6">
                                                <div class="p-3 bg-light rounded-4 border border-light d-flex align-items-center justify-content-between">
                                                    <div>
                                                        <label class="fw-bold text-dark small mb-0">System Activity Audit</label>
                                                        <p class="x-small text-muted m-0">Record admin/resident actions.</p>
                                                    </div>
                                                    <label class="shubx-premium-toggle">
                                                        <input type="checkbox" name="shubx51_enable_audit" value="1" <?php checked(get_option('shubx51_enable_audit', 1), 1); ?>/>
                                                        <span class="slider"></span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-6 text-end d-flex align-items-center gap-3">
                                                <div class="flex-grow-1 text-start">
                                                    <label class="form-label small fw-bold text-dark mb-1">Retention Period</label>
                                                    <select name="shubx51_log_retention" class="form-select shadow-none rounded-3 border-light fw-bold">
                                                        <option value="30" <?php selected(get_option('shubx51_log_retention', 30), 30); ?>>30 Days</option>
                                                        <option value="60" <?php selected(get_option('shubx51_log_retention'), 60); ?>>60 Days</option>
                                                        <option value="90" <?php selected(get_option('shubx51_log_retention'), 90); ?>>90 Days</option>
                                                        <option value="0" <?php selected(get_option('shubx51_log_retention'), 0); ?>>Unlimited</option>
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-primary fw-bold rounded-3 px-4 shadow-sm" style="height: 48px; margin-top: 24px;">Save Policy</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Approval Workflow -->
                <div class="settings-tab-pane hidden" id="tab-content-approval">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'shubx51_options_group' ); ?>
                        <!-- Preserve Society Profile Settings -->
                        <input type="hidden" name="shubx51_society_name" value="<?php echo esc_attr( get_option('shubx51_society_name', 'Society Name') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line1" value="<?php echo esc_attr( get_option('shubx51_society_address_line1') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line2" value="<?php echo esc_attr( get_option('shubx51_society_address_line2') ); ?>">
                        <input type="hidden" name="shubx51_society_city" value="<?php echo esc_attr( get_option('shubx51_society_city') ); ?>">
                        <input type="hidden" name="shubx51_society_pincode" value="<?php echo esc_attr( get_option('shubx51_society_pincode') ); ?>">
                        <input type="hidden" name="shubx51_society_contact" value="<?php echo esc_attr( get_option('shubx51_society_contact') ); ?>">
                        <input type="hidden" name="shubx51_maintenance_amount" value="<?php echo esc_attr( get_option('shubx51_maintenance_amount', '5000') ); ?>">
                        <input type="hidden" name="shubx51_opening_bank" value="<?php echo esc_attr( get_option('shubx51_opening_bank', '0') ); ?>">
                        <input type="hidden" name="shubx51_opening_cash" value="<?php echo esc_attr( get_option('shubx51_opening_cash', '0') ); ?>">
                        <!-- Preserve Bank Details -->
                        <input type="hidden" name="shubx51_bank_name" value="<?php echo esc_attr( get_option('shubx51_bank_name') ); ?>">
                        <input type="hidden" name="shubx51_bank_account" value="<?php echo esc_attr( get_option('shubx51_bank_account') ); ?>">
                        <input type="hidden" name="shubx51_bank_ifsc" value="<?php echo esc_attr( get_option('shubx51_bank_ifsc') ); ?>">
                        <input type="hidden" name="shubx51_bank_upi" value="<?php echo esc_attr( get_option('shubx51_bank_upi') ); ?>">
                        <input type="hidden" name="shubx51_bank_qr" value="<?php echo esc_attr( get_option('shubx51_bank_qr') ); ?>">
                        <input type="hidden" name="shubx51_color_palette" value="<?php echo esc_attr( get_option('shubx51_color_palette', 'orange') ); ?>">
                        <input type="hidden" name="shubx51_default_theme" value="<?php echo esc_attr( get_option('shubx51_default_theme', 'light') ); ?>">
                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">Verification Policies</h5>
                            <p class="small text-secondary mb-4">Control whether resident-submitted changes require admin oversight.</p>
                            
                            <div class="d-flex flex-column gap-3">
                                <?php 
                                $policies = [
                                    'shubx51_approval_family' => 'Resident Family Members',
                                    'shubx51_approval_help'   => 'Domestic Help & Personal Staff',
                                    'shubx51_approval_vehicle' => 'Private Vehicles',
                                    'shubx51_approval_facility' => 'Facility & Amenity Bookings'
                                ];
                                foreach($policies as $opt => $label): ?>
                                    <div class="card border border-light shadow-none bg-light bg-opacity-50 rounded-3">
                                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                            <div class="fw-bold text-dark small"><?php echo esc_html( $label ); ?></div>
                                            <select name="<?php echo esc_html( $opt ); ?>" class="form-select form-select-sm shadow-none w-auto rounded-pill px-4 fw-bold border-0 bg-white">
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

                <!-- Tab: Modules & Features -->
                <div class="settings-tab-pane hidden" id="tab-content-modules">
                    <div class="mb-4 pb-2 border-bottom border-light">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                            <div>
                                <h5 class="fw-bold text-primary m-0">Society Modules & Feature Toggles</h5>
                                <p class="small text-secondary m-0 mt-1">Enable or disable modules to tailor Society HubX to your community. Disabled modules are immediately hidden from all menus, resident dashboards, and APIs.</p>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill px-3 py-2 small fw-bold">
                                <i class="bi bi-toggles me-1"></i> Live Feature Control
                            </span>
                        </div>
                    </div>

                    <div id="module-toggle-alert" class="alert d-none mb-4 rounded-3 p-3 small"></div>

                    <input type="hidden" id="shubx51_module_toggle_nonce" value="<?php echo esc_attr( wp_create_nonce( 'shubx51_module_toggle_nonce' ) ); ?>">

                    <div class="row g-4">
                        <?php 
                        $all_modules = class_exists( 'SHUBX51_Module_Registry' ) ? SHUBX51_Module_Registry::get_all_modules() : array();
                        $category_colors = array(
                            'core'       => 'primary',
                            'security'   => 'danger',
                            'operations' => 'success',
                            'governance' => 'warning',
                            'community'  => 'info',
                        );

                        foreach ( $all_modules as $mod_slug => $mod_data ) : 
                            $is_active = class_exists( 'SHUBX51_Module_Registry' ) ? SHUBX51_Module_Registry::is_enabled( $mod_slug ) : true;
                            $cat_color = $category_colors[ $mod_data['category'] ?? 'operations' ] ?? 'primary';
                        ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card h-100 border border-light shadow-sm rounded-4 overflow-hidden position-relative bg-white">
                                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex align-items-start justify-content-between mb-3">
                                                <div class="bg-<?php echo esc_attr( $cat_color ); ?> bg-opacity-10 text-<?php echo esc_attr( $cat_color ); ?> rounded-3 p-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                                    <i class="bi <?php echo esc_attr( $mod_data['icon'] ); ?> fs-4"></i>
                                                </div>
                                                <span class="badge bg-light text-secondary border rounded-pill small text-uppercase px-2 py-1" style="font-size: 10px; letter-spacing: 0.04em;">
                                                    <?php echo esc_html( ucfirst( $mod_data['category'] ) ); ?>
                                                </span>
                                            </div>

                                            <h6 class="fw-bold text-dark mb-2"><?php echo esc_html( $mod_data['name'] ); ?></h6>
                                            <p class="text-secondary small mb-4" style="line-height: 1.5; min-height: 42px;">
                                                <?php echo esc_html( $mod_data['description'] ); ?>
                                            </p>
                                        </div>

                                        <div class="pt-3 border-top border-light d-flex align-items-center justify-content-between">
                                            <?php if ( ! empty( $mod_data['locked'] ) ) : ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-3 py-1 small">
                                                    <i class="bi bi-lock-fill me-1"></i>Permanent Core
                                                </span>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" checked disabled style="cursor: not-allowed; width: 2.4em; height: 1.25em;">
                                                </div>
                                            <?php else : ?>
                                                <span class="badge status-badge-<?php echo esc_attr( $mod_slug ); ?> <?php echo $is_active ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-muted'; ?> rounded-pill px-3 py-1 small fw-semibold">
                                                    <?php echo $is_active ? esc_html__( 'Active', 'society-hubx' ) : esc_html__( 'Disabled', 'society-hubx' ); ?>
                                                </span>
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input module-toggle-switch shadow-none" 
                                                           type="checkbox" 
                                                           role="switch"
                                                           id="switch-<?php echo esc_attr( $mod_slug ); ?>" 
                                                           data-module="<?php echo esc_attr( $mod_slug ); ?>"
                                                           <?php checked( $is_active ); ?>
                                                           style="cursor: pointer; width: 2.4em; height: 1.25em;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tab: Data & Maintenance (Combined) -->
                <div class="settings-tab-pane hidden" id="tab-content-maintenance">
                    <div>
                        
                        <!-- 1. Data Portability -->
                        <div class="mb-5">
                            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-light pb-2">
                                <h5 class="fw-bold text-primary m-0">Data Portability</h5>
                                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1 small fw-medium">CSV & JSON Tools</span>
                            </div>

                            <!-- Import/Export Messages -->
                            <?php if ( isset($_GET['imported']) ) : ?>
                                <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-10 alert-dismissible shadow-sm border-0 rounded-4 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                        <div>
                                            <div class="fw-bold">Import Successful</div>
                                            <div class="small opacity-75">Processed <?php echo intval( wp_unslash( $_GET['imported'] ) ); ?> records.</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <div class="row g-4">
                                <!-- Export Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 border-0 shadow-sm bg-light rounded-4 overflow-hidden border border-light">
                                        <div class="card-body p-4 d-flex flex-column">
                                            <div class="mb-auto">
                                                <div class="d-flex align-items-center gap-3 mb-3">
                                                    <div class="bg-primary bg-opacity-10 p-3 rounded-circle text-primary" style="width: 54px; height: 54px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="bi bi-cloud-download-fill fs-4"></i>
                                                    </div>
                                                    <h5 class="fw-bold text-dark m-0">Export Archive</h5>
                                                </div>
                                                <p class="text-secondary small mb-4">
                                                    Download a complete backup of your society records (CSV + JSON) in a single ZIP file.
                                                </p>
                                            </div>
                                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="mt-4">
                                                <input type="hidden" name="action" value="shubx51_export_data">
                                                <?php wp_nonce_field( 'shubx51_export_nonce' ); ?>
                                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 rounded-3 shadow-none">
                                                    <i class="bi bi-file-earmark-zip me-2"></i>Download .zip
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Import Card -->
                                <div class="col-md-6">
                                    <div class="card h-100 border-0 shadow-sm bg-white rounded-4 overflow-hidden border border-light">
                                        <div class="card-body p-4 d-flex flex-column">
                                            <div class="mb-auto">
                                                <div class="d-flex align-items-center gap-3 mb-3">
                                                    <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success" style="width: 54px; height: 54px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="bi bi-cloud-upload-fill fs-4"></i>
                                                    </div>
                                                    <h5 class="fw-bold text-dark m-0">Bulk Import</h5>
                                                </div>
                                                <p class="text-secondary small mb-4">
                                                    Upload CSV records for a specific module. Headers must match your database columns.
                                                </p>
                                            </div>
                                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="mt-4">
                                                <input type="hidden" name="action" value="shubx51_import_data">
                                                <?php wp_nonce_field( 'shubx51_import_nonce' ); ?>
                                                
                                                <div class="mb-3">
                                                    <select name="target_table" class="form-select shadow-none border-light bg-light small fw-bold rounded-3">
                                                        <?php 
                                                        $tables = SHUBX51_DB_Router::TABLES;
                                                        foreach($tables as $t) {
                                                            echo "<option value='{$t}'>Module: ".ucwords(str_replace('_', ' ', $t))."</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <input type="file" name="import_file" accept=".csv" class="form-control shadow-none border-light small rounded-3" required>
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

                        <!-- 2. System Maintenance -->
                        <div class="mt-5">
                            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-light pb-2">
                                <h5 class="fw-bold text-danger m-0">System Maintenance</h5>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger-subtle rounded-pill px-3 py-1 small fw-bold">Critical Actions</span>
                            </div>
                            
                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden border border-danger border-opacity-10 mb-4" style="background-color: #fff9f9;">
                                <div class="card-body p-4">
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-magic text-danger fs-5"></i>
                                                <h6 class="fw-bold text-danger m-0">Relaunch Setup Wizard</h6>
                                            </div>
                                            <p class="small text-danger opacity-75 mb-0" style="max-width: 500px;">
                                                Restart the initial configuration process. This will let you re-configure society details and property structure.
                                            </p>
                                        </div>
                                        <div>
                                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                                <input type="hidden" name="action" value="shubx51_relaunch_wizard">
                                                <?php wp_nonce_field( 'shubx51_relaunch_nonce' ); ?>
                                                <button type="submit" class="btn btn-danger fw-bold px-4 py-2 rounded-3 shadow-sm w-100">
                                                    Start Wizard
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm rounded-4 overflow-hidden border border-danger border-opacity-10" style="background-color: #fff9f9;">
                                <div class="card-body p-4">
                                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-exclamation-octagon-fill text-danger fs-5"></i>
                                                <h6 class="fw-bold text-danger m-0">Purge Societal Data</h6>
                                            </div>
                                            <p class="small text-danger opacity-75 mb-0" style="max-width: 500px;">
                                                Permanently remove all records from the selected storage. 
                                                This action is <strong>completely non-reversible</strong>. Please backup your data first.
                                            </p>
                                        </div>
                                        <div class="d-flex flex-column flex-sm-row gap-2">
                                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                                <input type="hidden" name="action" value="shubx51_reset_db">
                                                <input type="hidden" name="reset_type" value="mysql">
                                                <?php wp_nonce_field( 'shubx51_reset_nonce' ); ?>
                                                <button type="submit" onclick="return confirm('Wipe entire MySQL schema content? (Non-reversible)')" class="btn btn-danger fw-bold px-4 py-2 rounded-3 shadow-sm w-100">
                                                    Purge MySQL DB
                                                </button>
                                            </form>
                                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                                                <input type="hidden" name="action" value="shubx51_reset_db">
                                                <input type="hidden" name="reset_type" value="json">
                                                <?php wp_nonce_field( 'shubx51_reset_nonce' ); ?>
                                                <button type="submit" onclick="return confirm('Delete all JSON data files? (Non-reversible)')" class="btn btn-outline-danger fw-bold px-4 py-2 rounded-3 shadow-none w-100">
                                                    Purge JSON Files
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Tab: Privacy & DPDP -->
                <div class="tab-pane settings-tab-pane hidden" id="tab-content-privacy">
                    <form method="post" action="options.php">
                        <?php settings_fields( 'shubx51_options_group' ); ?>
                        <!-- Preserve Society Profile Settings -->
                        <input type="hidden" name="shubx51_society_name" value="<?php echo esc_attr( get_option('shubx51_society_name', 'Society Name') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line1" value="<?php echo esc_attr( get_option('shubx51_society_address_line1') ); ?>">
                        <input type="hidden" name="shubx51_society_address_line2" value="<?php echo esc_attr( get_option('shubx51_society_address_line2') ); ?>">
                        <input type="hidden" name="shubx51_society_city" value="<?php echo esc_attr( get_option('shubx51_society_city') ); ?>">
                        <input type="hidden" name="shubx51_society_pincode" value="<?php echo esc_attr( get_option('shubx51_society_pincode') ); ?>">
                        <input type="hidden" name="shubx51_society_contact" value="<?php echo esc_attr( get_option('shubx51_society_contact') ); ?>">
                        <input type="hidden" name="shubx51_maintenance_amount" value="<?php echo esc_attr( get_option('shubx51_maintenance_amount', '5000') ); ?>">
                        <input type="hidden" name="shubx51_opening_bank" value="<?php echo esc_attr( get_option('shubx51_opening_bank', '0') ); ?>">
                        <input type="hidden" name="shubx51_opening_cash" value="<?php echo esc_attr( get_option('shubx51_opening_cash', '0') ); ?>">
                        <!-- Preserve Bank Details -->
                        <input type="hidden" name="shubx51_bank_name" value="<?php echo esc_attr( get_option('shubx51_bank_name') ); ?>">
                        <input type="hidden" name="shubx51_bank_account" value="<?php echo esc_attr( get_option('shubx51_bank_account') ); ?>">
                        <input type="hidden" name="shubx51_bank_ifsc" value="<?php echo esc_attr( get_option('shubx51_bank_ifsc') ); ?>">
                        <input type="hidden" name="shubx51_bank_upi" value="<?php echo esc_attr( get_option('shubx51_bank_upi') ); ?>">
                        <input type="hidden" name="shubx51_bank_qr" value="<?php echo esc_attr( get_option('shubx51_bank_qr') ); ?>">
                        <input type="hidden" name="shubx51_color_palette" value="<?php echo esc_attr( get_option('shubx51_color_palette', 'orange') ); ?>">
                        <input type="hidden" name="shubx51_default_theme" value="<?php echo esc_attr( get_option('shubx51_default_theme', 'light') ); ?>">
                        
                        <div class="mb-4">
                            <h5 class="fw-bold text-primary mb-4 border-bottom border-light pb-2">DPDP Compliance & Data Privacy</h5>
                            <p class="small text-secondary mb-4">Configure compliance settings in accordance with the Digital Personal Data Protection (DPDP) Act.</p>
                            
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="p-4 bg-light rounded-4 border border-light d-flex align-items-center justify-content-between">
                                        <div class="pe-3">
                                            <label class="fw-bold text-dark small mb-1">Mask Resident Contact Information</label>
                                            <p class="x-small text-muted m-0">Enable phone/email obfuscation for unauthorized admin/staff viewers to protect personal identifiable info (PII).</p>
                                        </div>
                                        <label class="shubx-premium-toggle">
                                            <input type="checkbox" name="shubx51_privacy_masking" value="1" <?php checked(get_option('shubx51_privacy_masking', 1), 1); ?>/>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">Personal Data Export Consent Notice</label>
                                    <textarea name="shubx51_privacy_export_notice" rows="4" class="form-control shadow-none rounded-3 border-light small font-monospace" placeholder="Consent text shown to residents when downloading personal archives..."><?php echo esc_html( esc_textarea( get_option('shubx51_privacy_export_notice', 'I consent to the processing and export of my societal personal data for audit purposes.') ) ); ?></textarea>
                                    <div class="x-small text-muted mt-1">This text is displayed during personal data exports.</div>
                                </div>
                            </div>
                        </div>
                        <div class="pt-3 border-top border-light">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Privacy Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Tab: Push Notifications (Firebase / FCM) -->
                <div class="settings-tab-pane hidden" id="tab-content-push">
                    <?php
                        $fcm_enabled    = (bool) get_option( 'shubx51_fcm_enabled', '0' );
                        $fcm_project_id = get_option( 'shubx51_fcm_project_id', '' );
                        $fcm_client_email = get_option( 'shubx51_fcm_client_email', '' );
                        $fcm_private_key = get_option( 'shubx51_fcm_private_key', '' );
                        $fcm_sender_id  = get_option( 'shubx51_fcm_sender_id', '' );
                        $is_configured  = ( ! empty( $fcm_project_id ) && ! empty( $fcm_private_key ) );
                    ?>
                    
                    <div class="mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-light">
                            <div>
                                <h5 class="fw-bold text-primary m-0 mb-1">
                                    <i class="bi bi-bell-fill me-2"></i>Firebase Cloud Messaging (FCM)
                                </h5>
                                <p class="small text-muted m-0">Enable 100% free, decentralized native push notifications for your apartment society. Your society connects directly to Google FCM with zero central servers.</p>
                            </div>
                            <div>
                                <?php if ( $is_configured && $fcm_enabled ) : ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                                        <i class="bi bi-check-circle-fill me-1"></i>FCM Active & Connected
                                    </span>
                                <?php elseif ( $is_configured ) : ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                                        <i class="bi bi-pause-circle-fill me-1"></i>Configured (Disabled)
                                    </span>
                                <?php else : ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                                        <i class="bi bi-dash-circle-fill me-1"></i>Not Configured
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 1. Drag & Drop JSON Uploader Card -->
                        <div class="card border border-2 border-dashed border-primary border-opacity-25 bg-light bg-opacity-50 rounded-4 p-4 mb-4 text-center">
                            <div class="d-flex flex-column align-items-center justify-content-center py-2">
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle mb-3">
                                    <i class="bi bi-cloud-arrow-up fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Upload Firebase Service Account JSON</h6>
                                <p class="small text-muted mb-3" style="max-width: 540px;">
                                    Drop your downloaded <code>firebase-adminsdk-*.json</code> file here to instantly extract and save your Project ID, Client Email, and Private Key.
                                </p>
                                <div class="d-flex gap-2">
                                    <input type="file" id="shubx-fcm-file-input" accept=".json,application/json" class="d-none">
                                    <button type="button" class="btn btn-primary px-4 fw-bold rounded-3 shadow-sm" onclick="document.getElementById('shubx-fcm-file-input').click()">
                                        <i class="bi bi-file-earmark-code me-2"></i>Select JSON Key File
                                    </button>
                                </div>
                                <div id="shubx-fcm-upload-status" class="small mt-3"></div>
                            </div>
                        </div>

                        <!-- 2. Manual Configuration Form -->
                        <form method="post" action="options.php" id="shubx-fcm-settings-form">
                            <?php settings_fields( 'shubx51_options_group' ); ?>
                            <input type="hidden" name="shubx51_society_name" value="<?php echo esc_attr( get_option('shubx51_society_name') ); ?>">

                            <div class="row g-4 mb-4">
                                <div class="col-12">
                                    <div class="p-4 bg-light rounded-4 border border-light d-flex align-items-center justify-content-between">
                                        <div class="pe-3">
                                            <label class="fw-bold text-dark small mb-1">Enable Push Notifications</label>
                                            <p class="x-small text-muted m-0">When enabled, alerts for Emergency SOS, Visitor Check-ins, and Support Tickets are dispatched to resident devices.</p>
                                        </div>
                                        <label class="shubx-premium-toggle">
                                            <input type="checkbox" name="shubx51_fcm_enabled" value="1" <?php checked( $fcm_enabled, true ); ?>/>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Firebase Project ID</label>
                                    <input type="text" name="shubx51_fcm_project_id" id="shubx-fcm-project-id" value="<?php echo esc_attr( $fcm_project_id ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace" placeholder="e.g. greenvalley-society-1234">
                                    <div class="x-small text-muted mt-1">Found in your Firebase Console project settings.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">Client Email (Service Account)</label>
                                    <input type="email" name="shubx51_fcm_client_email" id="shubx-fcm-client-email" value="<?php echo esc_attr( $fcm_client_email ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace" placeholder="firebase-adminsdk-...@...iam.gserviceaccount.com">
                                    <div class="x-small text-muted mt-1">Google service account email with Firebase Cloud Messaging API access.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-secondary">FCM Sender ID (Project Number)</label>
                                    <input type="text" name="shubx51_fcm_sender_id" id="shubx-fcm-sender-id" value="<?php echo esc_attr( $fcm_sender_id ); ?>" class="form-control shadow-none rounded-3 border-light font-monospace" placeholder="e.g. 847291039482">
                                    <div class="x-small text-muted mt-1">Sent to resident mobile apps for device token registration.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold text-secondary">RSA Private Key</label>
                                    <textarea name="shubx51_fcm_private_key" id="shubx-fcm-private-key" rows="4" class="form-control shadow-none rounded-3 border-light font-monospace small" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea( $fcm_private_key ); ?></textarea>
                                    <div class="x-small text-muted mt-1">Private key from the Service Account JSON. Used exclusively on this server for OAuth2 JWT signing.</div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pt-3 border-top border-light">
                                <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-3">Save Push Settings</button>
                                
                                <?php if ( $is_configured ) : ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" id="shubx-test-push-flat" placeholder="Flat No (optional)" class="form-control form-control-sm border-light shadow-none" style="width: 140px;">
                                        <button type="button" class="btn btn-outline-primary px-3 fw-bold rounded-3 btn-sm" id="btn-send-test-push">
                                            <i class="bi bi-send-fill me-1"></i>Send Test Push Alert
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- 3. Setup Guide Accordion -->
                        <div class="mt-5 pt-4 border-top border-light">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i>How to get your free Firebase credentials (3-Minute Setup):</h6>
                            <div class="row g-3 small text-muted">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 h-100 border border-light">
                                        <div class="fw-bold text-dark mb-1">1. Create Project</div>
                                        <div>Go to <a href="https://console.firebase.google.com" target="_blank" class="text-primary fw-semibold">Firebase Console</a>, click <strong>"Add Project"</strong>, and enter a name for your apartment society.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 h-100 border border-light">
                                        <div class="fw-bold text-dark mb-1">2. Generate Key</div>
                                        <div>Click the gear icon (⚙️) ➔ <strong>Project Settings</strong> ➔ <strong>Service Accounts</strong> tab. Click <strong>"Generate new private key"</strong> to download your JSON file.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded-3 h-100 border border-light">
                                        <div class="fw-bold text-dark mb-1">3. Upload & Activate</div>
                                        <div>Click <strong>"Select JSON Key File"</strong> above to upload the file. Toggle <strong>"Enable Push Notifications"</strong> and click Save!</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php do_action( 'shubx51_settings_tab_content' ); ?>
            </div>
        </div>
    </div>
</div><?php
// Hook modals into shubx51_admin_modals
add_action('shubx51_admin_modals', function() {
?>
<!-- Channel Configuration Modal -->
<div class="modal fade" id="shubx-channel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0"><span id="shubx-modal-channel-name">Channel</span> Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shubx-channel-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="channel_slug" id="shubx-modal-channel-slug">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_html( wp_create_nonce('shubx51_request_action') ); ?>">
                    <div id="shubx-channel-settings-fields">
                        <!-- Fields dynamically rendered by JS -->
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template Editing Modal -->
<div class="modal fade" id="shubx-template-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0">Edit Template: <span id="shubx-template-event-name" class="text-primary text-capitalize">Event</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shubx-template-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="id" id="shubx-template-id">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_html( wp_create_nonce('shubx51_request_action') ); ?>">
                    
                    <div class="mb-3 subject-field">
                        <label class="form-label small fw-bold text-slate-700">Subject</label>
                        <input type="text" class="form-control rounded-3" name="subject" id="shubx-template-subject" placeholder="Enter message subject">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-slate-700">Content</label>
                        <textarea class="form-control rounded-3" name="content" id="shubx-template-content" rows="6" placeholder="Enter template body text" required></textarea>
                        <div class="form-text small text-muted">
                            Supported placeholders: <code>{resident_name}</code>, <code>{title}</code>, <code>{deadline}</code>, <code>{flat_no}</code>, <code>{amount}</code>, <code>{date}</code>, <code>{status}</code>, <code>{notes}</code> (depending on the event).
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
});
?>

