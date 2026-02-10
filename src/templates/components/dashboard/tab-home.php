<?php
/**
 * Component: Dashboard Home Tab
 * @var array $data Dashboard data.
 */
?>
<!-- 1. HOME TAB -->
<div id="tab-home" class="tab-content d-block">
     
     <!-- Top Row: People & Vehicles -->
     <div class="row g-4 mb-4">
         
        <!-- Family Members (Card List) -->
        <div class="col-md-4">
            <div id="familyContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                    <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                        <i class="bi bi-people-fill text-primary"></i> Family Members
                    </h3>
                     <button id="addFamily" data-bs-toggle="modal" data-bs-target="#familyModal" class="btn btn-sm btn-primary rounded-3 fw-medium">+ Add</button>
                </div>
                <div class="p-4 flex-grow-1" style="overflow: visible;">
                    <?php if(empty($data['family'])): ?>
                        <div class="text-center text-muted small italic py-4">No members added.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                            <?php foreach($data['family'] as $fam): ?>
                                <li class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php echo SGVX51_Admin_UI::render_avatar($fam['name'], $fam['email'] ?? '', $fam['profile_photo'] ?? '', 40); ?>
                                        <div>
                                            <div class="fw-bold text-dark small lh-sm"><?php echo esc_html($fam['name']); ?></div>
                                            <div class="small text-secondary d-flex align-items-center gap-1 mt-1" style="font-size: 0.75rem;">
                                                <span class="fw-medium text-dark"><?php echo esc_html($fam['relation']); ?></span>
                                                <?php if(!empty($fam['dob'])): ?>
                                                    <span class="text-muted">• <?php echo date('M d, Y', strtotime($fam['dob'])); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if(isset($fam['status']) && $fam['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.6rem;">PENDING</span>
                                                <?php elseif(isset($fam['status']) && $fam['status'] === 'deletion_pending'): ?>
                                                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.6rem;">DEL PENDING</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Actions -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light rounded-3">
                                            <li>
                                                <a class="dropdown-item small d-flex align-items-center gap-2 js-view-family" href="#"
                                                   data-bs-toggle="modal" data-bs-target="#viewFamilyModal"
                                                   data-name="<?php echo esc_attr($fam['name']); ?>"
                                                   data-relation="<?php echo esc_attr($fam['relation']); ?>"
                                                   data-dob="<?php echo esc_attr($fam['dob'] ?? ''); ?>"
                                                   data-blood="<?php echo esc_attr($fam['blood_group'] ?? ''); ?>"
                                                   data-phone="<?php echo esc_attr($fam['phone'] ?? ''); ?>"
                                                   data-email="<?php echo esc_attr($fam['email'] ?? ''); ?>" 
                                                   data-photo="<?php echo esc_attr($fam['profile_photo'] ?? ''); ?>"
                                                >
                                                    <i class="bi bi-eye text-info"></i> View
                                                </a>
                                            </li>
                                            <?php if(isset($fam['status']) && $fam['status'] !== 'approved'): ?>
                                                <!-- No edit/delete for pending -->
                                            <?php else: ?>
                                            <li>
                                                <a class="dropdown-item small d-flex align-items-center gap-2 js-edit-family" href="#"
                                                   data-id="<?php echo esc_attr($fam['id'] ?? ''); ?>"
                                                   data-name="<?php echo esc_attr($fam['name']); ?>"
                                                   data-relation="<?php echo esc_attr($fam['relation']); ?>"
                                                   data-dob="<?php echo esc_attr($fam['dob'] ?? ''); ?>"
                                                   data-blood="<?php echo esc_attr($fam['blood_group'] ?? ''); ?>"
                                                   data-phone="<?php echo esc_attr($fam['phone'] ?? ''); ?>"
                                                   data-email="<?php echo esc_attr($fam['email'] ?? ''); ?>" 
                                                   data-photo="<?php echo esc_attr($fam['profile_photo'] ?? ''); ?>"
                                                >
                                                    <i class="bi bi-pencil-square text-primary"></i> Edit
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider my-1"></li>
                                            <li>
                                                <a class="dropdown-item small d-flex align-items-center gap-2 text-danger js-delete-family-frontend" href="#" 
                                                   data-id="<?php echo esc_attr($fam['id']); ?>" 
                                                   data-nonce="<?php echo wp_create_nonce('sgvx51_delete_family_nonce'); ?>">
                                                    <i class="bi bi-trash3"></i> Remove
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Daily Help -->
        <div class="col-md-4">
            <div id="dailyHelpContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                    <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                        <i class="bi bi-person-badge-fill text-info"></i> Daily Help
                    </h3>
                    <button id="addHelp" data-bs-toggle="modal" data-bs-target="#helpModal" class="btn btn-sm btn-info text-white rounded-3 fw-medium">+ Add</button>
                </div>
                <div class="p-4 flex-grow-1">
                    <?php if(empty($data['daily_help'])): ?>
                         <div class="text-center text-muted small italic py-4">No daily help added.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                            <?php foreach($data['daily_help'] as $help): ?>
                                <li class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                         <?php echo SGVX51_Admin_UI::render_avatar($help['name'], '', $help['profile_photo'] ?? '', 40); ?>
                                         <div>
                                             <div class="fw-bold text-dark small"><?php echo esc_html($help['name']); ?></div>
                                             <div class="d-flex align-items-center gap-2">
                                                 <div class="small text-muted"><?php echo esc_html($help['role']); ?></div>
                                                 <?php if(isset($help['status']) && $help['status'] === 'pending'): ?>
                                                     <span class="badge bg-warning text-dark rounded-pill" style="font-size: 0.6rem;">PENDING</span>
                                                 <?php elseif(isset($help['status']) && $help['status'] === 'deletion_pending'): ?>
                                                     <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem;">DEL PENDING</span>
                                                 <?php endif; ?>
                                             </div>
                                         </div>
                                    </div>
                                    
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if(isset($help['status']) && $help['status'] === 'approved'): ?>
                                            <a href="tel:<?php echo esc_attr($help['phone']); ?>" class="btn btn-sm btn-light rounded-circle shadow-none border border-light d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-telephone-fill small text-dark"></i>
                                            </a>
                                        <?php endif; ?>

                                        <div class="dropdown">
                                            <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light rounded-3">
                                                <li><button class="dropdown-item js-edit-help small" data-payload="<?php echo esc_attr(json_encode($help)); ?>"><i class="bi bi-pencil-square text-primary me-2"></i> Edit</button></li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                     <button class="dropdown-item text-danger small js-delete-help-frontend" 
                                                        data-id="<?php echo esc_attr($help['id']); ?>"
                                                        data-nonce="<?php echo wp_create_nonce('sgvx51_delete_help_nonce'); ?>">
                                                        <i class="bi bi-trash3 me-2"></i> Remove
                                                     </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- My Vehicles -->
        <div class="col-md-4">
            <div id="vehicleContainer" class="bg-white rounded-3 shadow-sm border border-light h-100 d-flex flex-column">
                <div class="px-4 py-3 border-bottom border-light bg-light d-flex justify-content-between align-items-center rounded-top-3">
                    <h3 class="fw-semibold text-dark d-flex align-items-center gap-2 m-0 fs-6">
                         <i class="bi bi-car-front-fill text-primary"></i> My Vehicles
                    </h3>
                    <button id="addVehicle" data-bs-toggle="modal" data-bs-target="#vehicleModal" class="btn btn-sm btn-primary rounded-3 fw-medium">+ Add</button>
                </div>
                <div class="p-4 flex-grow-1">
                    <?php if(empty($data['vehicles'])): ?>
                         <div class="text-center text-muted small italic py-4">No vehicles registered.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-3">
                            <?php foreach($data['vehicles'] as $v): ?>
                                <li class="d-flex align-items-center gap-3">
                                    <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 32px; height: 32px;">
                                        <?php if(strtolower($v['type']) === 'bike' || strtolower($v['type']) === '2-wheeler'): ?>
                                            <i class="bi bi-bicycle"></i>
                                        <?php else: ?>
                                            <i class="bi bi-car-front-fill"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark font-monospace tracking-wide small" style="letter-spacing: 0.05em;"><?php echo esc_html($v['number']); ?></div>
                                        <div class="small text-secondary d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                                            <?php 
                                                $desc = $v['type'];
                                                if(!empty($v['brand'])) $desc .= ' • ' . $v['brand'];
                                                echo esc_html($desc); 
                                            ?>
                                            <?php if(isset($v['status']) && $v['status']==='pending'): ?>
                                                <span class="badge bg-warning text-dark rounded-pill ms-1" style="font-size: 0.6rem;">PENDING</span>
                                            <?php elseif(isset($v['status']) && $v['status']==='rejected'): ?>
                                                <span class="badge bg-danger text-white rounded-pill ms-1" style="font-size: 0.6rem;">REJECTED</span>
                                            <?php elseif(isset($v['status']) && $v['status']==='deletion_pending'): ?>
                                                <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.6rem;">DEL PENDING</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if(isset($v['status']) && $v['status'] === 'approved'): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm text-muted p-0 shadow-none border-0" type="button" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light">
                                                    <li><button class="dropdown-item js-edit-vehicle small" data-payload="<?php echo esc_attr(json_encode($v)); ?>">Edit</button></li>
                                                    <li>
                                                        <button class="dropdown-item text-danger small js-delete-vehicle-frontend" 
                                                                data-id="<?php echo esc_attr($v['id']); ?>" 
                                                                data-nonce="<?php echo wp_create_nonce('sgvx51_delete_vehicle_frontend_nonce'); ?>">
                                                            Deregister
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
     </div>

     <!-- Bottom Row: Documents -->
     <div id="documentContainer" class="bg-white rounded-3 shadow-sm border border-light overflow-hidden mb-4">
        <div class="px-4 py-3 border-bottom border-light d-flex justify-content-between align-items-center bg-white">
            <div class="d-flex items-center gap-3">
                <div class="bg-primary bg-opacity-10 rounded-3 d-flex align-items-center justify-content-center text-primary" style="width: 40px; height: 40px;">
                    <i class="bi bi-file-earmark-text-fill fs-4"></i>
                </div>
                <div>
                    <h3 class="fw-semibold text-dark m-0 fs-6">My Documents</h3>
                    <p class="text-secondary m-0" style="font-size: 0.75rem;">Manage your flat documents</p>
                </div>
            </div>
            <button id="addDocument" data-bs-toggle="modal" data-bs-target="#uploadDocModal" class="btn btn-sm btn-dark rounded-3 fw-medium d-flex align-items-center gap-2">
                <i class="bi bi-cloud-upload"></i>
                Upload Document
            </button>
        </div>
         <div class="p-4">
            <?php if ( empty( $data['my_docs'] ) ) : ?>
                <div class="text-center py-4 text-muted small">
                    <p>No documents found.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ( $data['my_docs'] as $d ) : ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="bg-white border border-light rounded-3 p-3 position-relative shadow-sm transition-all h-100">
                                <?php if ( isset( $d['status'] ) && $d['status'] === 'pending' ) : ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-warning text-dark rounded small text-uppercase" style="font-size: 8px;">Pending</span>
                                <?php elseif ( isset( $d['status'] ) && $d['status'] === 'rejected' ) : ?>
                                    <span class="position-absolute top-0 end-0 m-2 badge bg-danger text-white rounded small text-uppercase" style="font-size: 8px;">Rejected</span>
                                <?php else: ?>
                                    <span class="position-absolute top-0 end-0 m-2 text-success"><i class="bi bi-check-circle-fill"></i></span>
                                <?php endif; ?>
                                
                                <div class="mb-2">
                                    <div class="bg-light text-muted rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        <i class="bi bi-file-earmark-pdf fs-4"></i>
                                    </div>
                                </div>
                                
                                <h4 class="fw-bold text-dark small text-truncate mb-1" title="<?php echo esc_attr( $d['name'] ); ?>"><?php echo esc_html( $d['name'] ); ?></h4>
                                <p class="text-muted mb-3" style="font-size: 0.7rem;">Cloud Storage</p>

                                <div class="d-flex justify-content-between align-items-center border-top border-light pt-2">
                                    <a href="<?php echo esc_url( $d['url'] ); ?>" target="_blank" class="small text-primary fw-semibold text-decoration-none">View</a>
                                    <?php if ( isset( $d['status'] ) && $d['status'] === 'pending' ) : ?>
                                         <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=sgvx51_frontend_delete_doc&doc_id='.$d['id']), 'sgvx51_delete_doc_nonce' ); ?>" 
                                           class="small text-danger fw-medium text-decoration-none"
                                           onclick="return confirm('Delete this pending document?')">Delete</a>
                                    <?php elseif(isset($d['status']) && $d['status'] === 'deletion_pending'): ?>
                                        <span class="small text-warning fw-medium">Deleting...</span>
                                    <?php else: ?>
                                         <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=sgvx51_request_delete_doc&doc_id='.$d['id']), 'sgvx51_delete_doc_nonce' ); ?>" 
                                           class="small text-secondary hover-text-danger fw-medium text-decoration-none"
                                           onclick="return confirm('Request Admin to delete this document?')">Delete</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
         </div>
    </div>
</div>
