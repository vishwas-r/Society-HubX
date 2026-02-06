<?php
/**
 * Template Name: Society App (Full Width)
 * Description: A full-width, SaaS-style template for Society GoVernX pages.
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
    <header class="bg-white border-bottom border-light sticky-top shadow-sm" style="z-index: 1050;">
        <div class="container-fluid" style="max-width: 1280px;">
            <div class="d-flex justify-content-between align-items-center" style="height: 4rem;">
                <!-- Logo / Title -->
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded bg-primary d-flex align-items-center justify-content-center text-white fw-bold" style="width: 2rem; height: 2rem;">
                        S
                    </div>
                    <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.02em;">Society GoVernX</span>
                </div>

                <!-- User Profile / Nav -->
                <div class="d-flex align-items-center gap-4">
                    <?php if ( is_user_logged_in() ) : 
                        $current_user = wp_get_current_user();
                    ?>
                        <div class="d-none d-md-flex flex-column align-items-end lh-1">
                            <span class="small fw-semibold text-dark"><?php echo esc_html( $current_user->display_name ); ?></span>
                            <span style="font-size: 0.75rem;" class="text-secondary">Resident</span>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold small" style="width: 2.5rem; height: 2.5rem;">
                            <?php echo strtoupper( substr( $current_user->display_name, 0, 1 ) ); ?>
                        </div>
                        <a href="<?php echo wp_logout_url( home_url() ); ?>" class="text-secondary" title="Logout">
                            <i class="bi bi-box-arrow-right fs-5"></i>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo wp_login_url(); ?>" class="small fw-bold text-primary text-decoration-none">Login</a>
                    <?php endif; ?>
                </div>
            </div>
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
            &copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Powered by Society GoVernX.
        </div>
    </footer>

    <?php wp_footer(); ?>
</body>
</html>
