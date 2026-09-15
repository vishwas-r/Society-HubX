<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
                    <img src="<?php echo esc_url($profile_photo); ?>" id="preview-<?php echo esc_html( $context ); ?>" class="w-100 h-100 object-fit-cover" alt="Profile">
                <?php else: ?>
                    <i class="bi bi-person-fill text-secondary fs-1" id="icon-<?php echo esc_html( $context ); ?>"></i>
                    <img src="" id="preview-<?php echo esc_html( $context ); ?>" class="w-100 h-100 object-fit-cover d-none" alt="Preview">
                <?php endif; ?>
            </div>
            <label for="pic-<?php echo esc_html( $context ); ?>" class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle p-2 shadow-sm cursor-pointer hover-scale" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-camera-fill small"></i>
            </label>
            <input type="file" name="profile_photo" id="pic-<?php echo esc_html( $context ); ?>" class="d-none js-profile-upload" accept="image/*" data-preview="#preview-<?php echo esc_html( $context ); ?>" data-icon="#icon-<?php echo esc_html( $context ); ?>">
        </div>
        <div class="text-muted small mt-2">Tap to upload photo</div>
    </div>

    <!-- Row 1: Full Name & Resident Type -->
    <div class="col-md-6">
        <label class="form-label small fw-bold text-secondary text-uppercase">Full Name <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text bg-light border-light text-muted"><i class="bi bi-person"></i></span>
            <input type="text" name="name" value="<?php echo esc_attr($name); ?>" class="form-control rounded-end-3 border-light shadow-none" required placeholder="Enter full name">
        </div>
    </div>

    <?php if($is_admin): ?>
        <div class="col-md-6 text-start">
             <label class="form-label small fw-bold text-secondary text-uppercase">Resident Role / Type <span class="text-danger">*</span></label>
             <select name="type" id="resident-type-select-<?php echo esc_html( $context ); ?>" class="form-select rounded-3 border-light shadow-none js-resident-type-toggle" data-context="<?php echo esc_html( $context ); ?>" required>
                 <option value="owner" <?php selected($type, 'owner'); ?>>Homeowner</option>
                 <option value="tenant" <?php selected($type, 'tenant'); ?>>Tenant / Resident</option>
                 <option value="family" <?php selected($type, 'family'); ?>>Family Member</option>
             </select>
        </div>
    <?php elseif($is_profile): ?>
        <div class="col-md-6">
            <label class="form-label small fw-bold text-secondary text-uppercase">Resident Type</label>
            <input type="text" class="form-control rounded-3 border-light shadow-none bg-light" value="<?php echo esc_attr(ucfirst($type)); ?>" disabled>
            <input type="hidden" name="type" value="<?php echo esc_attr($type); ?>">
        </div>
    <?php else: ?>
        <input type="hidden" name="type" value="family">
    <?php endif; ?>

    <!-- Admin: Enterprise Unit Portfolio Engine (Handles 1000s of Flats across 10 Towers) -->
    <?php if($is_admin): 
        $selected_flat_ids = isset($r['flat_ids']) ? $r['flat_ids'] : ( !empty($flat_no) ? array($flat_no) : array() );
        $sorted_flats = !empty($args['flats']) ? $args['flats'] : array();
        
        // Extract Unique Towers / Blocks for Filter
        $all_blocks = array();
        if ( ! empty( $sorted_flats ) ) {
            foreach ( $sorted_flats as $fl_item ) {
                $blk = trim( preg_replace( '/^(block[\s_-]*)+/i', '', (string)( $fl_item['block'] ?? '' ) ) );
                if ( ! empty( $blk ) && ! in_array( $blk, $all_blocks, true ) ) {
                    $all_blocks[] = $blk;
                }
            }
            sort( $all_blocks, SORT_NATURAL | SORT_FLAG_CASE );
        }
    ?>
        <div class="col-12 text-start">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label small fw-bold text-secondary text-uppercase m-0 d-flex align-items-center gap-2">
                    <i class="bi bi-buildings-fill text-primary"></i>
                    <span>Allocated Property Holdings</span>
                    <span class="text-danger">*</span>
                </label>
                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold px-2 py-1 rounded-pill small" id="unit-count-badge-<?php echo esc_html( $context ); ?>">
                    0 Units Allocated
                </span>
            </div>

            <!-- 1. Allocated Units Deck (Cards Tray) -->
            <div id="unit-portfolio-tray-<?php echo esc_html( $context ); ?>" 
                 class="d-flex flex-wrap gap-2 mb-2 p-2 rounded-3 border bg-light bg-opacity-50" 
                 style="min-height: 54px; transition: all 0.2s ease;">
                <!-- Empty State Prompt -->
                <div class="text-muted small py-1 px-2 d-flex align-items-center gap-2 w-100" id="unit-tray-empty-<?php echo esc_html( $context ); ?>">
                    <i class="bi bi-info-circle text-primary"></i>
                    <span>No units assigned yet. Use the smart search bar below or open the matrix to allocate flats.</span>
                </div>
            </div>

            <!-- Hidden Inputs for Standard Form POST Sync -->
            <div id="unit-hidden-inputs-<?php echo esc_html( $context ); ?>"></div>
            <input type="hidden" name="flat_no" id="flat-no-hidden-<?php echo esc_html( $context ); ?>" value="<?php echo esc_attr($flat_no); ?>">
            <input type="hidden" name="block" id="block-hidden-<?php echo esc_html( $context ); ?>" value="<?php echo esc_attr($r['block'] ?? ''); ?>">

            <!-- 2. Smart Unit Omnibox (Fast Fuzzy Autocomplete for 1,000+ units) -->
            <div class="position-relative mb-2">
                <div class="input-group shadow-sm rounded-3 overflow-hidden border">
                    <!-- Tower Filter Pill Dropdown -->
                    <button class="btn btn-light bg-white border-0 border-end px-3 py-2 text-secondary fw-semibold dropdown-toggle d-flex align-items-center gap-2 shadow-none" 
                            type="button" 
                            id="tower-filter-btn-<?php echo esc_html( $context ); ?>" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false" 
                            style="font-size: 0.85rem;">
                        <i class="bi bi-funnel-fill text-primary small"></i>
                        <span id="selected-tower-label-<?php echo esc_html( $context ); ?>">All Towers</span>
                    </button>
                    <ul class="dropdown-menu shadow border-0 mt-1 py-1" id="tower-filter-menu-<?php echo esc_html( $context ); ?>" style="max-height: 280px; overflow-y: auto; font-size: 0.85rem;">
                        <li><a class="dropdown-item active fw-bold js-tower-filter" href="#" data-tower="all"><i class="bi bi-grid-fill me-2 text-primary"></i>All Towers (All Flats)</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <?php foreach($all_blocks as $block_name): ?>
                            <li>
                                <a class="dropdown-item js-tower-filter d-flex align-items-center justify-content-between" href="#" data-tower="<?php echo esc_attr($block_name); ?>">
                                    <span><i class="bi bi-building me-2 text-secondary"></i>Tower <?php echo esc_html($block_name); ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <!-- Instant Search Input -->
                    <input type="text" 
                           id="unit-omnibox-input-<?php echo esc_html( $context ); ?>" 
                           class="form-control border-0 py-2 ps-3 shadow-none fw-medium" 
                           placeholder="Type unit number or floor (e.g. 101, 1402, B-4, Penthouse)..." 
                           autocomplete="off" 
                           style="font-size: 0.9rem;">

                    <!-- Toggle Matrix Drawer Button -->
                    <button class="btn btn-white bg-white border-0 text-primary px-3 fw-semibold d-flex align-items-center gap-2 shadow-none hover-bg-light" 
                            type="button" 
                            id="toggle-tower-matrix-btn-<?php echo esc_html( $context ); ?>" 
                            title="Browse Tower & Unit Matrix">
                        <i class="bi bi-grid-3x3-gap-fill text-primary"></i>
                        <span class="d-none d-sm-inline small">Browse Matrix</span>
                    </button>
                </div>

                <!-- Live Floating Autocomplete Suggestions Dropdown -->
                <div id="unit-omnibox-dropdown-<?php echo esc_html( $context ); ?>" 
                     class="position-absolute w-100 bg-white rounded-3 shadow-lg border mt-1 p-2 d-none" 
                     style="z-index: 1060; max-height: 320px; overflow-y: auto;">
                     <!-- Suggestions rendered dynamically by JS -->
                </div>
            </div>

            <!-- 3. Interactive Tower Matrix Explorer (Collapsible Visual Drawer) -->
            <div id="tower-matrix-explorer-<?php echo esc_html( $context ); ?>" class="card border rounded-3 p-3 bg-white shadow-sm mb-3 d-none">
                <div class="d-flex align-items-center justify-content-between pb-2 mb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-circle p-1"><i class="bi bi-building"></i></span>
                        <span class="fw-bold small text-dark">Tower Matrix & Floor Grid</span>
                        <span class="text-muted smaller">Click a tile to toggle allocation</span>
                    </div>
                    <button type="button" class="btn-close small shadow-none" id="close-matrix-btn-<?php echo esc_html( $context ); ?>" aria-label="Close"></button>
                </div>
                
                <!-- Tower Tabs -->
                <ul class="nav nav-pills gap-1 p-1 bg-light rounded-3 mb-2 flex-wrap" id="matrix-tower-pills-<?php echo esc_html( $context ); ?>" role="tablist">
                    <!-- Dynamic Tower Pills: Tower A, Tower B, etc. -->
                </ul>

                <!-- Matrix Grid of Unit Tiles -->
                <div id="matrix-units-grid-<?php echo esc_html( $context ); ?>" class="d-flex flex-wrap gap-2 p-1" style="max-height: 180px; overflow-y: auto;">
                    <!-- Unit tiles rendered dynamically by JS -->
                </div>
            </div>

            <!-- Client-Side In-Memory Search Index & State Data -->
            <script type="application/json" id="flats-data-<?php echo esc_html( $context ); ?>">
            <?php
            $client_flats = array();
            if ( ! empty( $sorted_flats ) ) {
                foreach ( $sorted_flats as $fl_item ) {
                    $val         = (string) $fl_item['id'];
                    $f_num       = ! empty( $fl_item['flat_number'] ) ? (string) $fl_item['flat_number'] : $val;
                    $clean_block = trim( preg_replace( '/^(block[\s_-]*)+/i', '', (string)( $fl_item['block'] ?? '' ) ) );
                    $floor_val   = isset( $fl_item['floor'] ) ? (string) $fl_item['floor'] : '';
                    $full_display = NAMMASOCIETY51_DB_Router::format_flat_display( $clean_block, $f_num );

                    $client_flats[] = array(
                        'id'      => $val,
                        'number'  => $f_num,
                        'block'   => $clean_block,
                        'floor'   => $floor_val,
                        'display' => $full_display,
                        'type'    => (string)( $fl_item['type'] ?? '' ),
                    );
                }
            }
            echo wp_json_encode( $client_flats );
            ?>
            </script>
            <script type="application/json" id="initial-flats-<?php echo esc_html( $context ); ?>">
            <?php
            $init_flats = array();
            if ( ! empty( $selected_flat_ids ) ) {
                $init_flats = array_values( array_map( 'strval', (array) $selected_flat_ids ) );
            } elseif ( ! empty( $flat_no ) ) {
                $init_flats = array( (string) $flat_no );
            }
            echo wp_json_encode( array(
                'flats'   => $init_flats,
                'primary' => (string) $flat_no,
                'block'   => (string)( $r['block'] ?? '' )
            ) );
            ?>
            </script>
        </div>
    <?php elseif($is_profile): ?>
        <div class="col-md-6">
            <label class="form-label small fw-bold text-secondary text-uppercase">Flat No.</label>
            <?php 
            $display_flat = NAMMASOCIETY51_Plugin::get_instance()->db->get_flat_display_name( $flat_no, $r['block'] ?? '' );
            ?>
            <input type="text" class="form-control rounded-3 border-light shadow-none bg-light" value="<?php echo esc_attr($display_flat); ?>" disabled>
            <input type="hidden" name="flat_no" value="<?php echo esc_attr($flat_no); ?>">
            <input type="hidden" name="block" value="<?php echo esc_attr($r['block'] ?? ''); ?>">
        </div>
    <?php endif; ?>

    <!-- Relation (Frontend Family OR Admin) -->
    <div class="col-md-6" id="relation-wrapper-<?php echo esc_html( $context ); ?>" style="<?php echo ($type !== 'family') ? 'display:none;' : ''; ?>">
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

    <!-- Contact Details: Phone & Email in clean 2-column row -->
    <div class="col-md-6">
        <label class="form-label small fw-bold text-secondary text-uppercase">Phone Number</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-light text-muted"><i class="bi bi-telephone"></i></span>
            <input type="tel" name="phone" value="<?php echo esc_attr($phone); ?>" class="form-control rounded-end-3 border-light shadow-none" placeholder="10-digit mobile">
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label small fw-bold text-secondary text-uppercase">Email Address</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-light text-muted"><i class="bi bi-envelope"></i></span>
            <input type="email" name="email" value="<?php echo esc_attr($email); ?>" class="form-control rounded-end-3 border-light shadow-none" placeholder="official@email.com">
        </div>
    </div>

    <!-- Personal Info: Date of Birth & Blood Group -->
    <div class="col-md-6">
         <label class="form-label small fw-bold text-secondary text-uppercase">Date of Birth</label>
         <div class="input-group">
             <span class="input-group-text bg-light border-light text-muted"><i class="bi bi-calendar3"></i></span>
             <input type="date" name="dob" value="<?php echo esc_attr($dob); ?>" class="form-control rounded-end-3 border-light shadow-none">
         </div>
    </div>

    <div class="col-md-6 text-start">
         <label class="form-label small fw-bold text-secondary text-uppercase">Blood Group</label>
         <select name="blood_group" class="form-select rounded-3 border-light shadow-none">
             <option value="">Select Blood Group</option>
             <?php 
             $bgs = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
             foreach($bgs as $bg): 
             ?>
                <option value="<?php echo esc_html( $bg ); ?>" <?php selected($blood_group, $bg); ?>><?php echo esc_html( $bg ); ?></option>
             <?php endforeach; ?>
         </select>
    </div>

    <!-- Society Role (Admin Only) with Pixel-Perfect Checkbox Alignment -->
    <?php if($is_admin): 
       $all_rbac_roles = NAMMASOCIETY51_Plugin::get_instance()->rbac->get_all_roles();
       $selected_roles = is_array($role) ? $role : array_filter(explode(',', (string)$role));
    ?>
       <div class="col-12 text-start" id="society-role-wrapper-<?php echo esc_html( $context ); ?>" style="<?php echo ($type === 'family') ? 'display:none;' : ''; ?>">
           <label class="form-label small fw-bold text-secondary text-uppercase d-flex align-items-center gap-1">
               <i class="bi bi-shield-check text-primary"></i> Society Role Assignment
           </label>
           <div class="row g-2 px-1">
               <?php foreach($all_rbac_roles as $rbac_role): 
                   $is_checked = in_array($rbac_role['id'], $selected_roles);
               ?>
                   <div class="col-md-4 col-6">
                       <div class="form-check d-flex align-items-center gap-2 p-2 rounded-3 border bg-light bg-opacity-50 hover-bg-light cursor-pointer">
                           <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="role[]" value="<?php echo esc_attr($rbac_role['id']); ?>" id="role-<?php echo esc_html( $rbac_role['id'] ); ?>-<?php echo esc_html( $context ); ?>" <?php checked($is_checked); ?>>
                           <label class="form-check-label small m-0 fw-semibold text-dark cursor-pointer flex-grow-1" for="role-<?php echo esc_html( $rbac_role['id'] ); ?>-<?php echo esc_html( $context ); ?>">
                               <?php echo esc_html($rbac_role['name']); ?>
                           </label>
                       </div>
                   </div>
               <?php endforeach; ?>
           </div>
           <div class="text-muted smaller mt-2"><i class="bi bi-info-circle me-1"></i>Assign multiple committee or operational roles (e.g. Resident + Treasurer)</div>
       </div>
    <?php endif; ?>
</div>




