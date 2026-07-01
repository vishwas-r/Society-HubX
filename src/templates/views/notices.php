<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View: Notices (World-Class Modernization)
 * Integrates with AJAX CRUD and Enhanced Metadata.
 */

$db = new SNESTX51_DB_Router();
$notices = $db->get( 'notices' );
$notices = array_reverse($notices); // Newest first

// Split into tabs
$published = [];
$drafts = [];
$archived = [];

foreach($notices as $n) {
    $status = $n['status'] ?? 'published';
    if($status === 'draft') $drafts[] = $n;
    elseif($status === 'archived') $archived[] = $n;
    else $published[] = $n;
}

// Sort published: Pinned first, then date
usort($published, function($a, $b) {
    if(($a['is_pinned'] ?? 0) != ($b['is_pinned'] ?? 0)) {
        return ($b['is_pinned'] ?? 0) - ($a['is_pinned'] ?? 0);
    }
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

?>

    <!-- Page Header -->
    <div class="mb-5 px-1">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-4">
            <div>
                <h1 class="h3 fw-bold text-dark m-0" style="letter-spacing: -0.02em;">Society Notice Board</h1>
                <p class="text-secondary m-0 mt-1">Broadcast official news, alerts, and schedules to the community.</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button onclick="openNoticeModal()" class="btn btn-primary px-4 fw-bold shadow-sm rounded-3 d-flex align-items-center gap-2" style="height: 48px;">
                    <i class="bi bi-plus-lg fs-5"></i>
                    <span>Create Announcement</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs Styling -->
    <div class="px-3 px-md-5 bg-white border-bottom border-light overflow-x-auto no-scrollbar mb-4 rounded-3 shadow-sm">
        <ul class="nav nav-tabs border-0 gap-3 gap-md-5 text-nowrap flex-nowrap" id="noticeTabs">
            <li class="nav-item">
                <button class="nav-link active py-3 px-0 border-0 border-bottom border-2 fw-bold text-primary border-primary notice-tab-btn" data-tab="published" style="background:none;">
                    Published <span class="badge bg-primary bg-opacity-10 text-primary ms-2 rounded-pill"><?php echo count($published); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent notice-tab-btn" data-tab="drafts" style="background:none;">
                    Drafts <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2 rounded-pill"><?php echo count($drafts); ?></span>
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link py-3 px-0 border-0 border-bottom border-2 fw-semibold text-muted border-transparent notice-tab-btn" data-tab="archived" style="background:none;">
                    Archive <span class="badge bg-secondary bg-opacity-10 text-secondary ms-2 rounded-pill"><?php echo count($archived); ?></span>
                </button>
            </li>
        </ul>
    </div>

    <!-- Toolbar -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-4 overflow-hidden">
        <div class="p-4 px-md-5 border-bottom border-light bg-white">
            <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                <div class="flex-grow-1 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" id="noticeSearch" placeholder="Search title, content or urgency..." 
                           class="form-control ps-5 bg-light border-0 shadow-none rounded-3 fw-medium" 
                           style="height: 48px; font-size: 0.95rem;">
                </div>
                <div class="d-flex gap-2">
                    <select id="urgencyFilter" class="form-select bg-light border-0 shadow-none rounded-3 fw-bold text-secondary px-4" style="height: 48px; font-size: 0.8rem; width: auto;">
                        <option value="all">All Urgency</option>
                        <option value="emergency">Emergency</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="info">Info</option>
                        <option value="normal">Normal</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Notice List -->
    <div id="notice-container">
        <!-- Published Tab -->
        <div class="notice-pane active" id="pane-published">
            <?php renderNoticeList($published, 'No Active Announcements', 'Broadcasts will appear here once published.'); ?>
        </div>
        <!-- Drafts Tab -->
        <div class="notice-pane d-none" id="pane-drafts">
            <?php renderNoticeList($drafts, 'No Drafts Found', 'Your saved drafts will be stored here.'); ?>
        </div>
        <!-- Archive Tab -->
        <div class="notice-pane d-none" id="pane-archived">
            <?php renderNoticeList($archived, 'Archive is Empty', 'Past notices will be moved here.'); ?>
        </div>
    </div>

</div>

<?php
/**
 * Helper: Render Notice Cards
 */
function renderNoticeList($list, $empty_title, $empty_msg) {
    if ( empty( $list ) ) : ?>
        <div class="py-5 text-center text-muted bg-white rounded-3 shadow-sm border border-light">
            <i class="bi bi-clipboard-x text-muted opacity-25 mb-4 d-block" style="font-size: 64px;"></i>
            <h5 class="text-dark fw-bold"><?php echo $empty_title; ?></h5>
            <p class="text-secondary m-0"><?php echo $empty_msg; ?></p>
        </div>
    <?php else : ?>
        <div class="d-flex flex-column gap-4">
            <?php foreach ( $list as $n ) : 
                $urgency = $n['urgency'] ?? 'info';
                $is_pinned = !empty($n['is_pinned']);
                $search_text = esc_attr( strtolower( ($n['title'] ?? '') . ' ' . strip_tags($n['content'] ?? '') . ' ' . $urgency ) );
                
                $border_class = 'border-light';
                $glow_class = '';
                if($urgency === 'emergency') { $border_class = 'border-danger border-opacity-50'; $glow_class = 'shadow-danger-soft'; }
                elseif($urgency === 'maintenance') { $border_class = 'border-warning border-opacity-50'; }
                elseif($urgency === 'info') { $border_class = 'border-primary border-opacity-50'; }
            ?>
                <div class="card <?php echo $border_class; ?> <?php echo $glow_class; ?> shadow-sm rounded-3 overflow-hidden bg-white snestx-notice-card" 
                     data-id="<?php echo esc_attr($n['id']); ?>" 
                     data-search="<?php echo $search_text; ?>"
                     data-urgency="<?php echo $urgency; ?>">
                    
                    <?php if($is_pinned): ?>
                        <div class="bg-primary bg-opacity-10 px-3 py-1 small fw-bold text-primary d-inline-flex align-items-center gap-2 rounded-bottom-end-3" style="font-size: 10px;">
                            <i class="bi bi-pin-angle-fill"></i> PINNED TO TOP
                        </div>
                    <?php endif; ?>

                    <div class="p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex gap-3 align-items-start">
                                <div class="notice-urgency-icon rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
                                     style="width: 48px; height: 48px; background: <?php echo getUrgencyColor($urgency, true); ?>; color: <?php echo getUrgencyColor($urgency); ?>;">
                                    <i class="bi <?php echo getUrgencyIcon($urgency); ?> fs-4"></i>
                                </div>
                                <div>
                                    <h4 class="h5 fw-bold text-dark m-0" style="letter-spacing: -0.01em;"><?php echo esc_html( $n['title'] ); ?></h4>
                                    <div class="small text-secondary opacity-75 mt-1 d-flex align-items-center gap-2">
                                        <i class="bi bi-calendar3"></i>
                                        <span><?php echo wp_date( 'd M Y, H:i', strtotime( $n['created_at'] ) ); ?></span>
                                        <span class="mx-1">•</span>
                                        <span class="text-uppercase fw-bold" style="font-size: 10px;">For: <?php echo esc_html($n['audience']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm border-0 bg-transparent text-muted" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3">
                                    <li><a class="dropdown-item py-2 fw-semibold js-edit-notice" href="#" data-id="<?php echo $n['id']; ?>"><i class="bi bi-pencil me-2"></i> Edit Notice</a></li>
                                    <li><a class="dropdown-item py-2 fw-semibold js-toggle-pin" href="#" data-id="<?php echo $n['id']; ?>" data-pinned="<?php echo $is_pinned ? '0' : '1'; ?>"><i class="bi bi-pin-angle me-2"></i> <?php echo $is_pinned ? 'Unpin' : 'Pin to Top'; ?></a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-2 fw-semibold text-danger js-delete-notice" href="#" data-id="<?php echo $n['id']; ?>"><i class="bi bi-trash me-2"></i> Delete</a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="notice-content-body text-secondary mb-2" style="line-height: 1.8;"><?php echo wp_kses_post( $n['content'] ); ?></div>
                        
                        <div class="d-flex align-items-center justify-content-between pt-4 border-top border-light">
                            <div class="d-flex gap-2">
                                <span class="badge rounded-pill px-3 py-1.5 fw-bold text-uppercase" 
                                      style="font-size: 9px; background: <?php echo getUrgencyColor($urgency, true); ?>; color: <?php echo getUrgencyColor($urgency); ?>;">
                                    <?php echo $urgency; ?>
                                </span>
                                <?php if(!empty($n['expiry_date'])): ?>
                                    <span class="badge bg-light text-muted border border-light px-3 py-1.5 rounded-pill fw-bold text-uppercase" style="font-size: 9px;">Exp: <?php echo esc_html($n['expiry_date']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! empty( $n['attachment_url'] ) ) : ?>
                                <a href="<?php echo esc_url( $n['attachment_url'] ); ?>" target="_blank" class="btn btn-sm btn-primary px-4 fw-bold rounded-pill shadow-sm" style="font-size: 11px;">
                                    <i class="bi bi-file-earmark-arrow-down me-1"></i> ATTACHMENT
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif;
}

function getUrgencyColor($urgency, $subtle = false) {
    $colors = [
        'emergency'   => '#ef4444',
        'maintenance' => '#f59e0b',
        'info'        => '#3b82f6',
        'normal'      => '#64748b'
    ];
    $base = $colors[$urgency] ?? $colors['normal'];
    return $subtle ? $base . '15' : $base;
}

function getUrgencyIcon($urgency) {
    $icons = [
        'emergency'   => 'bi-exclamation-octagon-fill',
        'maintenance' => 'bi-tools',
        'info'        => 'bi-info-circle-fill',
        'normal'      => 'bi-megaphone-fill'
    ];
    return $icons[$urgency] ?? $icons['normal'];
}

// Modals
add_action('SNESTX51_admin_modals', function() {
?>
<!-- Notice Creator/Editor Modal -->
<div class="modal fade" id="noticeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
            <div class="modal-header border-bottom-0 pb-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary">
                        <i class="bi bi-broadcast fs-5"></i>
                    </div>
                    <h5 class="fw-bold m-0 text-dark" id="noticeModalLabel">Broadcast Announcement</h5>
                </div>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="modern-notice-form" enctype="multipart/form-data">
                <div class="modal-body p-4 px-md-5">
                    <input type="hidden" name="id" id="n-id">
                    
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Announcement Title</label>
                        <input type="text" name="title" id="n-title" class="form-control bg-light border-0 shadow-none rounded-3 fw-bold p-3" style="font-size: 1.1rem;" required placeholder="e.g. Mandatory Elevator Maintenance Schedule">
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Urgency Level</label>
                            <select name="urgency" id="n-urgency" class="form-select bg-light border-0 shadow-none rounded-3 fw-semibold">
                                <option value="normal">Normal</option>
                                <option value="info" selected>Info</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Target Audience</label>
                            <select name="audience" id="n-audience" class="form-select bg-light border-0 shadow-none rounded-3 fw-semibold">
                                <option value="All">All Residents</option>
                                <option value="Owners">Owners Only</option>
                                <option value="Tenants">Tenants Only</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Expiry Date</label>
                            <input type="date" name="expiry_date" id="n-expiry" class="form-control bg-light border-0 shadow-none rounded-3 fw-semibold">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Message Content</label>
                        <div class="rich-editor-wrapper bg-light rounded-3 p-1">
                            <?php 
                                wp_editor('', 'notice_editor', [
                                    'textarea_name' => 'content',
                                    'media_buttons' => false,
                                    'textarea_rows' => 10,
                                    'teeny'         => true,
                                    'quicktags'     => false,
                                    'editor_class'  => 'modern-rich-editor border-0 shadow-none'
                                ]); 
                            ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Attachment (Optional)</label>
                            <input type="file" name="attachment" class="form-control bg-light border-0 shadow-none rounded-3">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary text-uppercase tracking-wider">Options</label>
                            <div class="d-flex gap-4 pt-2">
                                <label class="snestx-toggle">
                                    <input type="checkbox" name="is_pinned" id="n-pinned" value="1">
                                    <span class="snestx-toggle-slider"></span>
                                </label>
                                <span class="small fw-bold text-dark pt-1">Pin to Top</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light px-4 py-3 justify-content-between">
                    <div class="d-flex gap-2">
                        <select name="status" id="n-status" class="form-select border-0 bg-white shadow-sm rounded-3 fw-bold text-primary px-4" style="width: auto;">
                            <option value="published">Publish Now</option>
                            <option value="draft">Save as Draft</option>
                            <option value="archived">Archive Directly</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary px-4 fw-semibold rounded-3 border-0" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold rounded-3 shadow-custom-primary">Confirm Broadcast</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.shadow-danger-soft { box-shadow: 0 4px 15px rgba(239, 68, 68, 0.15); }
.no-scrollbar::-webkit-scrollbar { display: none; }
.notice-tab-btn { transition: all 0.25s ease; position: relative; }
.notice-tab-btn::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 0; height: 2px; background: var(--bs-primary); transition: width 0.25s ease; }
.notice-tab-btn.active::after { width: 100%; }
.notice-pane { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* TinyMCE Modal Fixes */
#wp-notice_editor-wrap { border: 0 !important; background: transparent !important; }
.mce-toolbar-grp, .mce-edit-area { border: 0 !important; }
.mce-statusbar { border: 0 !important; display: none !important; }
</style>
<?php }); ?>
