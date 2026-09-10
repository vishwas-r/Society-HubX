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
// Server-Side Cookie & Option Resolution (Zero-Flash SSR)
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$cookie_theme = isset( $_COOKIE['shubx_theme'] ) ? sanitize_key( wp_unslash( $_COOKIE['shubx_theme'] ) ) : '';
$current_theme = in_array( $cookie_theme, [ 'light', 'dark' ], true ) ? $cookie_theme : get_option( 'shubx51_default_theme', 'light' );

$valid_palettes = [ 'orange', 'indigo', 'emerald', 'ocean', 'rose' ];
$current_palette = get_option( 'shubx51_color_palette', 'orange' );
if ( ! in_array( $current_palette, $valid_palettes, true ) ) {
    $current_palette = 'orange';
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-bs-theme="<?php echo esc_attr( $current_theme ); ?>" data-shubx-palette="<?php echo esc_attr( $current_palette ); ?>">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    
</head>
<body <?php body_class('shadow-none'); ?>>

    <!-- App Header -->
    <header class="shubx-top-header d-flex align-items-center justify-content-between px-3 px-lg-5 bg-white border-bottom sticky-top shadow-sm" style="height: 72px; z-index: 1050;">
        <div class="d-flex align-items-center gap-3">
            <button id="shubx-sidebar-toggle" class="btn btn-outline-secondary border-0 p-1 d-flex align-items-center justify-content-center hover-bg-slate-50 d-lg-none" style="width: 40px; height: 40px;">
                <i class="bi bi-list fs-3"></i>
            </button>
            <div class="d-flex align-items-center gap-2">
                 <img src="<?php echo esc_url( SHUBX51_PLUGIN_URL . 'assets/images/hubx-logo-sm.png' ); ?>" alt="Society HubX" class="shadow-sm" style="width: 32px; height: 32px; border-radius: 6px; object-fit: cover;">
                 <h1 class="h6 fw-bold text-slate-900 m-0 d-none d-sm-block">Society HubX</h1>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <!-- Theme Toggle Button -->
            <button id="shubx-theme-toggle" class="btn btn-outline-secondary border-0 p-1 d-flex align-items-center justify-content-center hover-bg-slate-50 shubx-theme-toggle-btn" type="button" title="Toggle Light / Dark Mode" aria-label="Toggle theme">
                <i id="shubx-theme-icon" class="bi bi-moon-stars-fill fs-5 text-secondary"></i>
            </button>
            
            <?php 
            if ( is_user_logged_in() ) : 
                $current_user = wp_get_current_user();
                $user_avatar = get_avatar_url( $current_user->ID );
                
                // Fetch custom profile photo from resident records
                $resident = SHUBX51_Plugin::get_instance()->db->get_resident_by_wp_id( $current_user->ID );
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
                <a href="<?php echo esc_url( wp_login_url() ); ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">Login</a>
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
            &copy; <?php echo esc_html( wp_date('Y') ); ?> <?php bloginfo('name'); ?>.
        </div>
    </footer>

    <script>
    (function() {
        function shubxSetCookie(name, value) {
            document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=31536000; SameSite=Lax";
        }

        function shubxApplyTheme(theme) {
            document.documentElement.setAttribute('data-bs-theme', theme);
            shubxSetCookie('shubx_theme', theme);
            
            var icon = document.getElementById('shubx-theme-icon');
            if (icon) {
                if (theme === 'dark') {
                    icon.className = 'bi bi-sun-fill fs-5 text-warning';
                } else {
                    icon.className = 'bi bi-moon-stars-fill fs-5 text-secondary';
                }
            }
        }

        window.shubxToggleTheme = function() {
            var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            var newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            shubxApplyTheme(newTheme);
        };

        window.shubxSetPalette = function(palette) {
            document.documentElement.setAttribute('data-shubx-palette', palette);
            shubxSetCookie('shubx_palette', palette);
        };

        document.addEventListener('DOMContentLoaded', function() {
            var current = document.documentElement.getAttribute('data-bs-theme') || '<?php echo esc_js( $current_theme ); ?>';
            shubxApplyTheme(current);
            
            var btn = document.getElementById('shubx-theme-toggle');
            if (btn) {
                btn.addEventListener('click', window.shubxToggleTheme);
            }
        });
    })();
    </script>

    <?php wp_footer(); ?>
</body>
</html>
