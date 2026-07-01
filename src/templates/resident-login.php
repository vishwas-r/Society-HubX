<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

/**
 * Template: Resident Login (SaaS Style)
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="shubx-login-wrapper d-flex align-items-center justify-content-center min-vh-100">
    <!-- Animated Background -->
    <div class="shubx-login-bg"></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <!-- 1. Intro Card -->
                <div id="intro-card" class="shubx-login-card p-4 p-md-5 text-center">
                    <div class="shubx-brand-icon mb-4 mx-auto">
                        <i class="bi bi-building-fill text-white fs-2"></i>
                    </div>
                    <h1 class="h3 fw-bold text-dark mb-2"><?php echo esc_html( $society_info['name'] ); ?></h1>
                    <?php if ( ! empty( $society_info['address1'] ) ) : ?>
                        <p class="text-secondary mb-1"><?php echo esc_html( $society_info['address1'] ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $society_info['city'] ) ) : ?>
                        <p class="text-secondary small mb-4"><?php echo esc_html( $society_info['city'] ); ?></p>
                    <?php endif; ?>

                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-primary py-3 fw-bold rounded-3 shadow-sm" onclick="showLoginForm()">
                            <i class="bi bi-door-open-fill me-2"></i> Resident Dashboard
                        </button>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top border-light">
                        <p class="small text-secondary m-0">&copy; <?php echo wp_date('Y'); ?> <?php echo esc_html( $society_info['name'] ); ?></p>
                    </div>
                </div>

                <!-- 2. Login Card (Initially Hidden) -->
                <div id="login-card" class="shubx-login-card p-4 p-md-5 d-none">
                    <!-- Logo / Brand -->
                    <div class="text-center mb-4">
                        <div class="shubx-brand-icon mb-3 mx-auto">
                            <i class="bi bi-shield-lock-fill text-white fs-2"></i>
                        </div>
                        <h2 class="h4 fw-bold text-dark m-0">Member Login</h2>
                        <p class="text-secondary small">Sign in to your society account</p>
                    </div>

                    <!-- Alert for Errors -->
                    <div id="login-error" class="alert alert-danger d-none small py-2 text-center" role="alert"></div>

                    <!-- Login Form -->
                    <form id="resident-login-form" method="post">
                        <?php wp_nonce_field( 'shubx51_login_nonce', 'login_nonce' ); ?>
                        
                        <div class="form-floating mb-3">
                            <input type="text" name="user_login" class="form-control" id="floatingInput" placeholder="Username" required>
                            <label for="floatingInput">Username or Email</label>
                        </div>
                        
                        <div class="form-floating mb-4">
                            <input type="password" name="user_pass" class="form-control" id="floatingPassword" placeholder="Password" required>
                            <label for="floatingPassword">Password</label>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="rememberMe">
                                <label class="form-check-label small text-secondary" for="rememberMe">
                                    Remember me
                                </label>
                            </div>
                            <a href="<?php echo wp_lostpassword_url(); ?>" class="small text-primary text-decoration-none fw-medium">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm" id="login-btn">
                            <span class="btn-text">Sign In</span>
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        </button>

                        <button type="button" class="btn btn-link w-100 mt-3 text-secondary text-decoration-none small" onclick="showIntro()">
                            <i class="bi bi-arrow-left me-1"></i> Back to Society Info
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

