<?php
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
                         data-json="<?php echo htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8'); ?>"
                         data-has-vehicle="<?php echo !empty($d['vehicles']) ? '1' : '0'; ?>"
                         data-has-help="<?php echo !empty($d['help']) ? '1' : '0'; ?>"
                         style="cursor: pointer;">
                           
                          <div class="bg-white rounded-3 shadow-sm border border-secondary border-opacity-25 overflow-hidden h-100 cursor-pointer transition-all card-hover">
                             <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center">
                                 <div class="d-flex align-items-center gap-3">
                                     <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                         <?php echo esc_html($d['flat_no']); ?>
                                     </div>
                                     <div>
                                         <h4 class="fw-semibold text-dark m-0 small"><?php echo esc_html($d['owner']); ?></h4>
                                         <span class="badge bg-light text-secondary fw-normal small border border-light">Block <?php echo esc_html($d['block']); ?></span>
                                     </div>
                                 </div>
                                 <div class="text-muted"><i class="bi bi-chevron-right"></i></div>
                             </div>
                             <div class="p-4">
                                  <div class="d-flex flex-wrap gap-2">
                                     <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal d-flex align-items-center gap-1">
                                         <i class="bi bi-people" style="font-size:12px;"></i> <?php echo esc_html($d['members']); ?>
                                     </span>
                                     <?php if(!empty($d['vehicles'])): ?>
                                          <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal d-flex align-items-center gap-1">
                                             <i class="bi bi-car-front" style="font-size:12px;"></i> <?php echo count($d['vehicles']); ?>
                                         </span>
                                     <?php endif; ?>
                                     <?php if(!empty($d['help'])): ?>
                                          <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal d-flex align-items-center gap-1">
                                             <i class="bi bi-person-badge" style="font-size:12px;"></i> <?php echo count($d['help']); ?>
                                         </span>
                                     <?php endif; ?>
                                  </div>
                             </div>
                          </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
     </div>
</div>
