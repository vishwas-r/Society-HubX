<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Community Directory Tab
 * @var array $data Dashboard data.
 */
$directory = $data['directory'] ?? [];
?>
<!-- COMMUNITY TAB -->
<div id="tab-community" class="tab-content d-none">
     <div class="d-flex flex-column gap-4">
         <!-- Search & Filters -->
        <div class="bg-white rounded-3 shadow-sm border border-light p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="position-relative">
                        <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" id="dir-search" placeholder="Search Flat, Owner, Vehicle..." class="form-control ps-5 text-sm rounded-3 border-light shadow-none">
                    </div>
                </div>
                <div class="col-md-6">
                     <div class="d-flex gap-2 justify-content-md-end overflow-auto" id="dir-filters">
                        <button class="dir-filter-btn btn btn-sm btn-dark rounded-pill px-3 active" data-filter="all">All</button>
                        <button class="dir-filter-btn btn btn-sm btn-light text-secondary rounded-pill px-3" data-filter="vehicle">Has Vehicle</button>
                        <button class="dir-filter-btn btn btn-sm btn-light text-secondary rounded-pill px-3" data-filter="help">Has Help</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Directory Grid -->
         <?php if(empty($directory)): ?>
            <div class="text-center py-5 text-muted">
                 <p>No directory data available.</p>
            </div>
        <?php else: ?>
            <div class="row g-4" id="directory-grid">
                <?php foreach($directory as $d): 
                    // Build Search Blob
                    $search_terms = [ $d['flat_no'], $d['owner'], 'Block ' . $d['block'], $d['block'] . '-' . $d['flat_no'] ];
                    if (!empty($d['all_names'])) $search_terms = array_merge($search_terms, $d['all_names']);
                    if (!empty($d['vehicles'])) { foreach($d['vehicles'] as $v) { if (!empty($v['number'])) $search_terms[] = $v['number']; if (!empty($v['brand'])) $search_terms[] = $v['brand']; } }
                    if (!empty($d['help'])) { foreach($d['help'] as $h) { if (!empty($h['name'])) $search_terms[] = $h['name']; if (!empty($h['role'])) $search_terms[] = $h['role']; } }
                    $search_blob = strtolower(implode(' ', array_unique(array_filter($search_terms))));
                ?>
                    <div class="col-md-6 col-lg-4 dir-card" 
                         data-search="<?php echo esc_attr($search_blob); ?>"
                         data-json="<?php echo htmlspecialchars(wp_json_encode($d), ENT_QUOTES, 'UTF-8'); ?>"
                         data-has-vehicle="<?php echo !empty($d['vehicles']) ? '1' : '0'; ?>"
                         data-has-help="<?php echo !empty($d['help']) ? '1' : '0'; ?>"
                         style="cursor: pointer;">
                           
                          <div class="bg-white rounded-3 shadow-sm border border-secondary border-opacity-25 overflow-hidden h-100 cursor-pointer transition-all card-hover">
                             <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                                 <div class="d-flex align-items-center gap-3">
                                     <div class="position-relative">
                                         <!-- Resident Display Picture -->
                                         <?php 
                                            // Handle potential empty owner_photo and fallback to initials
                                            $dp_url = !empty($d['owner_photo']) ? $d['owner_photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($d['owner']) . '&background=random&color=fff';
                                         ?>
                                         <img src="<?php echo esc_url($dp_url); ?>" class="rounded-circle border border-white shadow-sm" style="width: 48px; height: 48px; object-fit: cover;">
                                         
                                         <!-- Flat Number Badge (Floating) with Finance Status Color -->
                                         <?php 
                                            $status_class = 'bg-danger'; // Unpaid
                                            $dot_title = 'Payment Pending';
                                            if (isset($d['finance_status'])) {
                                                if ($d['finance_status'] === 'paid') { $status_class = 'bg-success'; $dot_title = 'Paid'; }
                                                elseif ($d['finance_status'] === 'partial') { $status_class = 'bg-warning'; $dot_title = 'Partially Paid'; }
                                            }
                                         ?>
                                         <span class="position-absolute top-100 start-50 translate-middle badge rounded-pill <?php echo esc_attr($status_class); ?> border border-white shadow-sm" 
                                               style="font-size: 0.65rem; margin-top: -3px; min-width: 32px;"
                                               data-bs-toggle="tooltip"
                                               title="Status: <?php echo esc_attr($dot_title); ?>">
                                             <?php echo esc_html($d['flat_no']); ?>
                                         </span>
                                     </div>
                                     <div class="ms-1">
                                         <h4 class="fw-bold text-dark m-0" style="font-size: 0.95rem;"><?php echo esc_html($d['owner']); ?></h4>
                                         <span class="badge bg-light text-secondary fw-normal small border border-light" style="font-size: 0.7rem;">Block <?php echo esc_html($d['block']); ?></span>
                                     </div>
                                 </div>
                                 <div class="text-muted"><i class="bi bi-chevron-right"></i></div>
                             </div>
                             <div class="p-4">
                                  <div class="d-flex justify-content-between align-items-center">
                                      <div class="d-flex flex-wrap gap-2">
                                         <?php if(!empty($d['members'])): ?>
                                             <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal d-flex align-items-center gap-1">
                                                 <i class="bi bi-people" style="font-size:12px;"></i> <?php echo esc_html($d['members']); ?>
                                             </span>
                                         <?php endif; ?>
                                         <?php if(!empty($d['vehicles'])): ?>
                                              <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal d-flex align-items-center gap-1"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="bottom"
                                                    title="<?php 
                                                        $v_list = array_map(function($v){ return $v['number'] . ' (' . ($v['brand'] ?: 'Vehicle') . ')'; }, $d['vehicles']);
                                                        echo esc_attr(implode(', ', $v_list));
                                                    ?>">
                                                 <i class="bi bi-car-front" style="font-size:12px;"></i> <?php echo count($d['vehicles']); ?>
                                              </span>
                                         <?php endif; ?>
                                         <?php if(!empty($d['help'])): ?>
                                              <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal d-flex align-items-center gap-1"
                                                    data-bs-toggle="tooltip"
                                                    data-bs-placement="bottom"
                                                    title="<?php 
                                                        $staff_details = [];
                                                        foreach($d['help'] as $h) {
                                                            $staff_details[] = esc_attr($h['name']) . ' (' . esc_attr($h['role']) . ')';
                                                        }
                                                        echo implode(', ', $staff_details);
                                                    ?>">
                                                 <i class="bi bi-person-badge" style="font-size:12px;"></i> <?php echo count($d['help']); ?>
                                              </span>
                                         <?php endif; ?>
                                      </div>
                                  </div>
                             </div>
                          </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
     </div>
</div>
