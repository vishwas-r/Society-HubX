<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * Template Name: Society App (Full Width)
 * Description: A full-width, SaaS-style template for Society HubX pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    
    <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Full-page template with standalone <head>. Cannot use wp_enqueue_style post wp_head(). ?>
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body <?php body_class('bg-light text-dark shadow-none'); ?>>

    <!-- App Header -->
    <header class="shubx-top-header d-flex align-items-center justify-content-between px-3 px-lg-5 bg-white border-bottom sticky-top shadow-sm" style="height: 72px; z-index: 1050;">
        <div class="d-flex align-items-center gap-3">
            <button id="shubx-sidebar-toggle" class="btn btn-outline-secondary border-0 p-1 d-flex align-items-center justify-content-center hover-bg-slate-50 d-lg-none" style="width: 40px; height: 40px;">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                 <div class="rounded bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width: 2rem; height: 2rem;">S</div>
                 <h1 class="h6 fw-bold text-slate-900 m-0 d-none d-sm-block">Society HubX</h1>
            </div>
        </div>
        <div class="d-flex align-items-center gap-4">
            <!-- <div class="bg-light p-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" title="No Pending Requests">
                <i class="bi bi-bell text-secondary opacity-50"></i>
            </div> -->
            
            <?php 
            if ( is_user_logged_in() ) : 
                $current_user = wp_get_current_user();
                $user_avatar = get_avatar_url( $current_user->ID );
                
                // Fetch custom profile photo from resident records
                $resident = Society_HubX::get_instance()->db->get_resident_by_wp_id( $current_user->ID );
                if ( $resident && ! empty( $resident['profile_photo'] ) ) {
                    $user_avatar = $resident['profile_photo'];
                }
            ?>
            <div class="dropdown">
                <button class="d-flex align-items-center gap-3 border-0 bg-transparent p-0 dropdown-toggle-no-caret shadow-none px-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="text-end d-none d-lg-block border-start ps-3">
                        <div class="small fw-bold text-dark text-nowrap"><?php echo esc_html( $current_user->display_name ); ?></div>
                        <div class="small text-secondary" style="font-size: 10px;">Resident</div>
                    </div>
                    <div class="shubx-user-avatar border shadow-sm rounded-circle overflow-hidden" style="width: 36px; height: 36px;">
                        <img alt="" src="<?php echo esc_url( $user_avatar ); ?>" class="avatar avatar-36 photo w-100 h-100 object-fit-cover" height="36" width="36" loading="lazy">
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2 py-2 px-2" style="min-width: 180px;">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2" href="#" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="bi bi-person-circle text-primary"></i>
                            <span class="small fw-bold text-dark">Edit Profile</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider opacity-5 my-1"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 py-2 rounded-2 text-danger" href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="small fw-bold">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php else : ?>
                <a href="<?php echo wp_login_url(); ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content (Full Width) -->
    <main class="min-vh-100 pt-4 pb-4">
        <div class="container-fluid" style="max-width: 1280px;">
            <?php
            while ( have_posts() ) :
                the_post();
                // We output content directly. Shortcodes will expand here.
                the_content();
            endwhile;
            ?>
        </div>
    </main>
    
    <!-- Simple Footer -->
    <footer class="bg-white border-top border-slate-200 py-4 mt-auto">
        <div class="container text-center text-muted small">
            &copy; <?php echo wp_date('Y'); ?> <?php bloginfo('name'); ?>.
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>
</html>
