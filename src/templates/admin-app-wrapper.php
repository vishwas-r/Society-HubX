<?php
/**
 * Admin App Wrapper
 * This acts as a full-screen overlay on top of the standard WP Admin.
 * 
 * Vars available:
 * $current_view (string) - The active view/module name (e.g. 'residents', 'expenses')
 */

// Default view if not set
if ( ! isset( $current_view ) ) {
    $current_view = isset( $_GET['page'] ) ? str_replace( 'sgvx51-', '', $_GET['page'] ) : 'dashboard';
}

// TEMP: Force Table Creation for new Schema
if(class_exists('SGVX51_DB_Schema')) {
    SGVX51_DB_Schema::create_tables();
}

$db = new SGVX51_DB_Router();
if ( ! isset( $requests ) || empty( $requests ) ) {
    $requests = $db->get( 'requests' );
}

$pending_count = 0;
foreach ( $requests as $req ) {
    if ( isset( $req['status'] ) && $req['status'] === 'pending' ) {
        $pending_count++;
    }
}

// Navigation Menu Config
// Maps: View Name => [ Label, URL, Icon Class/SVG Path ]
$nav_items = [
    'dashboard' => ['Dashboard', admin_url('admin.php?page=sgvx51-settings'), 'bi-speedometer2'],
    'flats'     => ['Flats & Units', admin_url('admin.php?page=sgvx51-flats'), 'bi-building'],
    'residents' => ['Residents', admin_url('admin.php?page=sgvx51-residents'), 'bi-people'],
    'vehicles'  => ['Vehicles', admin_url('admin.php?page=sgvx51-vehicles'), 'bi-car-front'],
    'staff'     => ['Staff & Help', admin_url('admin.php?page=sgvx51-staff'), 'bi-shield-shaded'],
    'documents' => ['Documents', admin_url('admin.php?page=sgvx51-documents'), 'bi-file-earmark-text'],
    'accounts'  => ['Accounts', admin_url('admin.php?page=sgvx51-accounts'), 'bi-wallet2'],
    'expenses'  => ['Expenses', admin_url('admin.php?page=sgvx51-expenses'), 'bi-cart-dash'],
    'assets'    => ['Assets', admin_url('admin.php?page=sgvx51-assets'), 'bi-box-seam'],
    'facilities'=> ['Facilities', admin_url('admin.php?page=sgvx51-facilities'), 'bi-calendar-event'],
    'notices'   => ['Notices', admin_url('admin.php?page=sgvx51-notices'), 'bi-megaphone'],
    'polls'     => ['Democracy', admin_url('admin.php?page=sgvx51-polls'), 'bi-journal-check'],
    'requests'  => ['Pending Requests', admin_url('admin.php?page=sgvx51-requests'), 'bi-patch-exclamation'],
    'activity-hub' => ['Activity Hub', admin_url('admin.php?page=sgvx51-activity-hub'), 'bi-clock-history'],
    'settings'  => ['Settings', admin_url('admin.php?page=sgvx51-global-settings'), 'bi-gear'],
];

?>
<!-- Admin App Wrapper Output -->
<div id="sgvx51-app-root" class="d-flex w-100 overflow-hidden">
    <!-- CSS Overrides -->
    <style>
        /* Isolate from WP Admin Styles */
        #sgvx51-app-root * { box-sizing: border-box; }
        
        /* Hide Default WP Elements if leaks */
        #wpadminbar, #adminmenumain, #wpfooter { display: none !important; }
        #wpcontent, #wpfooter { margin-left: 0 !important; }
        html.wp-toolbar { padding-top: 0 !important; }
        
        /* Typography - Import Outfit & Inter */
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap');
        
        #sgvx51-app-root { 
            font-family: 'Outfit', 'Inter', system-ui, -apple-system, sans-serif; 
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Helper for centered layout */
        .sgvx-content-scroll > .container {
            max-width: 1200px;
        }

        .dropdown-toggle-no-caret::after {
            display: none !important;
        }

        @media (min-width: 992px) {
            .sgvx-sidebar {
                width: 280px !important;
                min-width: 280px !important;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
        }
        .sgvx-sidebar.collapsed {
            width: 80px !important;
            min-width: 80px !important;
        }
        .sgvx-sidebar.collapsed .fw-bold, 
        .sgvx-sidebar.collapsed .text-nowrap,
        .sgvx-sidebar.collapsed hr,
        .sgvx-sidebar.collapsed .sgvx-sidebar-footer-text {
            display: none !important;
        }
        .sgvx-sidebar.collapsed .nav-link {
            justify-content: center;
            padding-left: 0;
            padding-right: 0;
        }
        .sgvx-sidebar.collapsed .nav-link i {
            margin: 0 !important;
        }
    </style>

    <!-- Sidebar Backdrop for Mobile -->
    <div id="sgvx-sidebar-backdrop" class="position-fixed top-0 start-0 w-100 h-100 bg-dark opacity-50 d-lg-none d-none" style="z-index: 1040;"></div>

    <!-- Sidebar -->
    <aside id="sgvx-sidebar" class="d-flex flex-column flex-shrink-0 pt-4 bg-white border-end sgvx-sidebar overflow-x-hidden transition-all" style="z-index: 1050;">
        <div class="d-flex align-items-center justify-content-between px-3 mb-2">
            <a href="<?php echo admin_url('admin.php?page=sgvx51-settings'); ?>" class="d-flex align-items-center text-custom-primary text-decoration-none gap-2 gap-sm-3">
                <div class="bg-primary rounded-xl d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; min-width: 32px;">
                    <i class="bi bi-building-fill text-white" style="font-size: 1rem;"></i>
                </div>
                <span class="fs-5 fw-bold tracking-tight d-inline-block" style="letter-spacing: -0.02em;">Society GoVernX</span>
            </a>
            <button id="sgvx-sidebar-close" class="btn btn-link text-dark d-lg-none p-2 rounded-circle hover-bg-slate-100">
                <i class="bi bi-x-lg fs-5"></i>
            </button>
        </div>
        <hr class="opacity-5">
        <div class="flex-grow-1 overflow-y-auto custom-scrollbar-sidebar">
            <ul class="nav nav-pills flex-column mb-auto px-1">
                <?php foreach($nav_items as $key => $nav): ?>
                    <li class="nav-item">
                        <a href="<?php echo esc_url($nav[1]); ?>" 
                           class="nav-link d-flex align-items-center gap-3 mb-2 py-2.5 px-3 transition-all <?php echo $current_view === $key ? 'active shadow-sm' : 'text-slate-500 hover-bg-slate-50'; ?>"
                           <?php echo $current_view === $key ? 'aria-current="page"' : ''; ?>>
                            <i class="bi <?php echo $nav[2]; ?> <?php echo $current_view === $key ? 'text-custom-primary' : 'text-slate-400'; ?>" style="font-size: 1.25rem;"></i>
                            <span class="small fw-semibold text-nowrap"><?php echo esc_html($nav[0]); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <hr class="mt-4 mb-4 opacity-5">
        <div class="mb-2 px-1">
            <a href="<?php echo admin_url(); ?>" class="d-flex align-items-center text-slate-400 text-decoration-none hover-indigo transition-all small fw-bold px-3">
                <i class="bi bi-arrow-left-short fs-4 me-1"></i>
                <span class="sgvx-sidebar-footer-text">Exit to WordPress</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="sgvx-main flex-grow-1 d-flex flex-column">
        <!-- Top Header -->
        <header class="sgvx-top-header d-flex align-items-center justify-content-between px-3 px-lg-5 bg-white border-bottom" style="height: 72px;">
            <div class="d-flex align-items-center gap-3">
                <button id="sgvx-sidebar-toggle" class="btn btn-outline-secondary border-0 p-1 d-flex align-items-center justify-content-center hover-bg-slate-50" style="width: 40px; height: 40px;">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <h1 class="h6 fw-bold text-slate-900 m-0 d-none d-sm-block"><?php echo esc_html( ucfirst( isset($nav_items[$current_view]) ? $nav_items[$current_view][0] : $current_view ) ); ?></h1>
            </div>
            <div class="d-flex align-items-center gap-4">
                <?php if ($pending_count > 0): ?>
                    <a href="<?php echo admin_url('admin.php?page=sgvx51-requests'); ?>" class="position-relative text-decoration-none bg-warning bg-opacity-10 p-2 rounded-circle border border-warning border-opacity-10 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="Pending Requests">
                        <i class="bi bi-patch-exclamation-fill text-warning"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white" style="font-size: 10px; padding: 0.35em 0.5em;">
                            <?php echo $pending_count; ?>
                        </span>
                    </a>
                <?php else: ?>
                    <div class="bg-light p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="No Pending Requests">
                        <i class="bi bi-patch-exclamation text-secondary opacity-50"></i>
                    </div>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="d-flex align-items-center gap-3 border-0 bg-transparent p-0 dropdown-toggle-no-caret shadow-none px-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="text-end d-none d-lg-block border-start ps-3">
                            <div class="small fw-bold text-dark text-nowrap">Welcome, <?php $user = wp_get_current_user(); echo esc_html($user->display_name); ?></div>
                            <div class="small text-secondary" style="font-size: 10px;">Administrator</div>
                        </div>
                        <div class="sgvx-user-avatar border shadow-sm rounded-circle overflow-hidden" style="width: 36px; height: 36px;">
                            <?php echo get_avatar( get_current_user_id(), 36, '', '', ['class' => 'w-100 h-100 object-fit-cover'] ); ?>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2 py-2 px-2" style="min-width: 180px;">
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2" href="<?php echo admin_url('profile.php'); ?>">
                                <i class="bi bi-person-circle text-primary"></i>
                                <span class="small fw-bold text-dark">Edit Profile</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider opacity-5 my-1"></li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2 text-danger" href="<?php echo wp_logout_url( admin_url() ); ?>">
                                <i class="bi bi-box-arrow-right"></i>
                                <span class="small fw-bold">Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- View Content Scroll -->
        <div class="sgvx-content-scroll flex-grow-1 overflow-y-auto p-3 p-lg-5 bg-slate-50">
            <div class="container p-0">
                <?php 
                    $view_path = SGVX51_PLUGIN_DIR . 'templates/views/' . $current_view . '.php';
                    if ( file_exists( $view_path ) ) {
                        include $view_path; 
                    } else {
                        echo '<div class="p-5 text-center text-muted">View not found: ' . esc_html($current_view) . '</div>';
                    }
                ?>
            </div>
        </div>
    </main>
</div>

<!-- Global Toasts Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-4" style="z-index: 100070;">
    <div id="sgvx-global-toast" class="toast align-items-center border-0 rounded-2xl shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-3 py-3 px-4">
                <i id="sgvx-toast-icon" class="bi fs-4"></i>
                <div id="sgvx-toast-message" class="fw-bold"></div>
            </div>
            <button type="button" class="btn-close btn-close-white me-3 m-auto opacity-50" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Global Modals Container (Outside Root for z-index) -->
<div id="sgvx51-modals-root">
    <?php do_action('sgvx51_admin_modals'); ?>
</div>

<?php 
/**
 * Manual Footer printing for the custom App Mode.
 * Since we exit early to prevent WP chrome from showing, we must manually 
 * trigger the hooks that print scripts and media templates.
 */
// 1. Media Modal Templates (Required for wp.media)
if ( function_exists( 'wp_print_media_templates' ) ) {
    wp_print_media_templates();
}

// 2. Footer Scripts (Prints all enqueued scripts like admin-app.js, admin-settings.js etc)
do_action( 'admin_print_footer_scripts' );
?>

<style>
    /* Reset some WP Default Styles that might bleed in if not fully isolated via iframe */
    html { margin-top: 0 !important; }
    /* Hide default WP Footer elements just in case they were printed */
    #wpadminbar, #adminmenumain, #wpfooter { display: none !important; }
</style>
