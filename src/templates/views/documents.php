<?php
/**
 * View: Documents (Bootstrap Migration)
 * Integrates with SGVX51_Drive_Manager and SGVX51_DB_Router.
 */

$db = new SGVX51_DB_Router();
$drive = new SGVX51_Drive_Manager();
$residents = $db->get( 'residents' );
$all_docs = $db->get( 'documents' );

// Filter Pending Uploads
$pending_uploads = array_filter( $all_docs, function($d) { return isset($d['status']) && $d['status'] === 'pending'; } );
// Filter Deletion Requests
$pending_deletions = array_filter( $all_docs, function($d) { return isset($d['status']) && $d['status'] === 'deletion_pending'; } );

// Selected Flat Logic
$selected_flat = isset( $_GET['flat'] ) ? sanitize_text_field( $_GET['flat'] ) : '';
$files = array(); 

if ( $selected_flat ) {
    foreach($all_docs as $d) {
        $d_flat = $d['flat_no'] ?? '';
        if($d_flat === $selected_flat && ($d['status']??'') !== 'deleted') {
            $files[] = array(
                'id' => $d['id'],
                'name' => $d['title'] ?? ($d['name'] ?? 'Unnamed'), 
                'url' => $d['file_path'] ?? ($d['url'] ?? '#'),
                'status' => $d['status'] ?? 'approved',
                'created_at' => $d['created_at'] ?? '',
                'type' => 'db'
            );
        }
    }

    $folder_id = $drive->ensure_flat_folder( $selected_flat );
    if ( ! is_wp_error( $folder_id ) ) {
        $phys_files = $drive->list_files( $folder_id );
        foreach($phys_files as $pf) {
            $is_known = false;
            foreach($files as $dbf) {
                if(urldecode($dbf['url']) === urldecode($pf['url'])) {
                    $is_known = true; break;
                }
            }
            if(!$is_known) {
                $files[] = array(
                    'id' => $pf['id'],
                    'name' => $pf['name'],
                    'url' => $pf['url'],
                    'status' => 'approved',
                    'created_at' => '',
                    'type' => 'physical'
                );
            }
        }
    }
}

// Messages
$success_msg = '';
$error_msg = '';
if ( isset( $_GET['success'] ) ) $success_msg = 'Document uploaded successfully.';
if ( isset( $_GET['updated'] ) ) $success_msg = 'Document status updated.';
if ( isset( $_GET['error'] ) ) $error_msg = sanitize_text_field( urldecode( $_GET['error'] ) );
?>

    <!-- Global Messages (Outside Cards) -->
    <?php if ( $success_msg ) : ?>
        <div class="alert bg-success bg-opacity-10 text-success border-success border-opacity-25 alert-dismissible fade show border shadow-sm mb-5 rounded-3 p-4" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-check-circle-fill fs-4"></i>
                <div>
                    <div class="fw-bold">Vault Updated</div>
                    <div class="small opacity-75"><?php echo esc_html( $success_msg ); ?></div>
                </div>
            </div>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Page Header (Outside Card) -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Document Vault</h1>
                <p class="text-secondary m-0 mt-1">Securely store and manage resident files.</p>
            </div>
        </div>
    </div>

    <!-- Main Content Card (Unified Sidebar & Content) -->
    <div class="card border-0 shadow-sm rounded-3 bg-white overflow-hidden flex-grow-1 min-h-0">
        
        <!-- Consolidated Toolbar -->
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <!-- Smart Search -->
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="residentSearch" placeholder="Search by flat or name..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                
                <!-- Action Group -->
                <div class="d-flex gap-2">
                    <?php if($selected_flat): ?>
                        <button id="addDocument" onclick="openUploadModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                            <i class="bi bi-cloud-upload fs-5"></i>
                            <span>Upload to <?php echo esc_html($selected_flat); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-0 h-100">
            <!-- Sidebar: Residents List -->
            <div class="col-md-3 border-end border-light flex-column d-flex h-100">
                <div class="flex-grow-1 overflow-y-auto" id="residentList" style="max-height: 600px;">
                    <?php 
                    $unique_flats = array();
                    foreach($residents as $r) {
                        if(!isset($unique_flats[$r['flat_no']])) $unique_flats[$r['flat_no']] = $r;
                    }
                    ksort($unique_flats, SORT_NATURAL);
                    
                    foreach ( $unique_flats as $flat_no => $res ) : 
                        $is_active = $selected_flat === $flat_no;
                    ?>
                         <a href="?page=sgvx51-documents&flat=<?php echo urlencode( $flat_no ); ?>" 
                            class="resident-item d-block px-4 py-3 border-bottom border-light text-decoration-none transition-all <?php echo $is_active ? 'bg-primary bg-opacity-10 border-start border-4 border-primary' : 'text-dark hover-bg-light'; ?>">
                             <div class="d-flex justify-content-between align-items-center">
                                 <span class="fw-bold small resident-flat <?php echo $is_active ? 'text-primary' : 'text-dark'; ?>"><?php echo esc_html( $flat_no ); ?></span>
                                 <i class="bi bi-chevron-right <?php echo $is_active ? 'text-primary' : 'text-muted opacity-25'; ?>" style="font-size: 12px;"></i>
                             </div>
                             <div class="small <?php echo $is_active ? 'text-primary' : 'text-secondary'; ?> text-truncate resident-name" style="<?php echo $is_active ? 'opacity: 0.8;' : ''; ?>"><?php echo esc_html( $res['name'] ); ?></div>
                         </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Content Area: File Explorer -->
            <div class="col-md-9 d-flex flex-column h-100 bg-white">
                <?php if ( $selected_flat ) : ?>
                    <div class="p-4 border-bottom border-light d-flex justify-content-between align-items-center bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                                <i class="bi bi-folder2-open fs-5"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark m-0">Unit Folder: <?php echo esc_html( $selected_flat ); ?></h5>
                                <p class="small text-secondary m-0"><?php echo count($files); ?> items stored on cloud</p>
                            </div>
                        </div>
                    </div>

                    <div id="documentContainer" class="p-5 flex-grow-1 overflow-y-auto">
                        <!-- Critical Alerts inside context -->
                        <?php if ( ! empty( $pending_uploads ) && array_filter($pending_uploads, function($d) use($selected_flat){ return $d['flat_no'] === $selected_flat; }) ) : ?>
                            <div class="alert badge-orange-subtle border-0 shadow-sm rounded-xl py-3 px-4 mb-4 small d-flex align-items-center gap-3">
                                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                                <div class="fw-bold">Some uploads are awaiting your moderation approval before they are visible to residents.</div>
                            </div>
                        <?php endif; ?>

                        <?php if ( empty( $files ) ) : ?>
                            <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted py-5">
                                <div class="bg-light p-5 rounded-circle mb-4">
                                    <i class="bi bi-folder-x text-muted opacity-25" style="font-size: 80px; line-height: 1;"></i>
                                </div>
                                <h5 class="text-dark fw-bold">Empty Vault</h5>
                                <p class="small text-center">No documents have been uploaded for this unit yet.</p>
                                <button onclick="openUploadModal()" class="btn btn-outline-primary px-4 py-2 fw-bold mt-2 rounded-3 shadow-none">Upload First File</button>
                            </div>
                        <?php else : ?>
                            <div class="row g-4">
                                <?php foreach ( $files as $file ) : 
                                    $status = $file['status'];
                                    $is_pending = $status === 'pending';
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card h-100 border border-light shadow-sm transition-all rounded-3 p-4 position-relative overflow-hidden">
                                            <?php if($is_pending): ?>
                                                <div class="position-absolute top-0 end-0 bg-warning text-dark px-3 py-1 fw-bold" style="font-size: 9px; border-bottom-left-radius: 12px; letter-spacing: 0.05em;">PENDING</div>
                                            <?php endif; ?>

                                            <div class="d-flex align-items-start gap-3 mb-4">
                                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                                                    <i class="bi bi-file-earmark-pdf fs-4"></i>
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="fw-bold text-dark small text-truncate d-block" title="<?php echo esc_attr($file['name']); ?>"><?php echo esc_html($file['name']); ?></div>
                                                    <div class="text-secondary d-flex align-items-center gap-2 mt-1" style="font-size: 10px;">
                                                        <i class="bi bi-cloud-check"></i>
                                                        <span>Google Drive Document</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex flex-column gap-2 mt-auto">
                                                <a href="<?php echo esc_url( $file['url'] ); ?>" target="_blank" class="btn btn-outline-primary border-primary-subtle w-100 fw-bold py-2 rounded-3 px-2 shadow-none" style="font-size: 11px; letter-spacing: 0.02em;">VIEW DOCUMENT</a>
                                                
                                                <div class="d-flex gap-2">
                                                    <?php if($is_pending && isset($file['id'])): ?>
                                                        <button type="button" class="btn bg-success bg-opacity-10 text-success border-0 flex-grow-1 py-1.5 fw-bold js-approve-doc rounded-3 px-2 shadow-none" data-id="<?php echo esc_attr($file['id']); ?>" style="font-size: 10px;">APPROVE</button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn bg-danger bg-opacity-10 text-danger border-0 <?php echo $is_pending ? '' : 'w-100'; ?> flex-grow-1 py-1.5 fw-bold js-delete-doc rounded-3 px-2 shadow-none" data-id="<?php echo esc_attr($file['id'] ?? ''); ?>" data-flat="<?php echo esc_attr($selected_flat); ?>" data-name="<?php echo esc_attr($file['name']); ?>" data-type="<?php echo esc_attr($file['type']); ?>" style="font-size: 10px;">DELETE</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="h-100 d-flex flex-column align-items-center justify-content-center text-muted p-5 bg-light bg-opacity-50">
                        <div class="bg-white p-5 rounded-circle shadow-sm border border-light mb-4 text-primary">
                            <i class="bi bi-arrow-left-circle" style="font-size: 48px; line-height: 1;"></i>
                        </div>
                        <h4 class="text-dark fw-bold m-0">Select a Resident</h4>
                        <p class="text-secondary text-center mt-2" style="max-width: 320px;">Choose a unit from the sidebar to explore and manage their stored cloud documents.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<?php
// Collect Modals to be printed outside the main root
add_action('sgvx51_admin_modals', function() use ($selected_flat) {
?>
<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold m-0 text-dark">Upload Document</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="upload-form">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="sgvx51_upload_doc">
                    <input type="hidden" name="flat_no" value="<?php echo esc_attr( $selected_flat ); ?>">
                    <?php wp_nonce_field( 'sgvx51_upload_doc_nonce' ); ?>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Target Folder</label>
                        <div class="form-control bg-light border-0 small text-dark fw-bold rounded-3">Unit: <?php echo esc_html($selected_flat); ?></div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-secondary">Select Local File</label>
                        <input type="file" name="doc_file" class="form-control shadow-none rounded-3 border-light" required>
                        <p class="text-muted mt-2" style="font-size: 9px;">Accepted: PDF, JPG, PNG (Max 5MB)</p>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3">
                    <button type="button" class="btn btn-light text-secondary px-4 fw-medium shadow-none rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3">Start Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php }); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('residentSearch');
    if(search) {
        search.addEventListener('keyup', (e) => {
            const val = e.target.value.toLowerCase();
            document.querySelectorAll('#residentList .resident-item').forEach(el => {
                const flat = el.querySelector('.resident-flat').textContent.toLowerCase();
                const name = el.querySelector('.resident-name').textContent.toLowerCase();
                el.style.display = (flat.includes(val) || name.includes(val)) ? '' : 'none';
            });
        });
    }
});

let uploadModal = null;
function openUploadModal() {
    if(!uploadModal) uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
    uploadModal.show();
}
</script>

