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
<div class="snestx-login-wrapper d-flex align-items-center justify-content-center min-vh-100">
    <!-- Animated Background -->
    <div class="snestx-login-bg"></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <!-- 1. Intro Card -->
                <div id="intro-card" class="snestx-login-card p-4 p-md-5 text-center">
                    <div class="snestx-brand-icon mb-4 mx-auto">
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
                        <p class="small text-secondary m-0">Powered by <strong>Society NestX</strong></p>
                    </div>
                </div>

                <!-- 2. Login Card (Initially Hidden) -->
                <div id="login-card" class="snestx-login-card p-4 p-md-5 d-none">
                    <!-- Logo / Brand -->
                    <div class="text-center mb-4">
                        <div class="snestx-brand-icon mb-3 mx-auto">
                            <i class="bi bi-shield-lock-fill text-white fs-2"></i>
                        </div>
                        <h2 class="h4 fw-bold text-dark m-0">Member Login</h2>
                        <p class="text-secondary small">Sign in to your society account</p>
                    </div>

                    <!-- Alert for Errors -->
                    <div id="login-error" class="alert alert-danger d-none small py-2 text-center" role="alert"></div>

                    <!-- Login Form -->
                    <form id="resident-login-form" method="post">
                        <?php wp_nonce_field( 'SNESTX51_login_nonce', 'login_nonce' ); ?>
                        
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

<style>
/* Dashboard Font (Inter is preferred) */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

.snestx-login-wrapper {
    font-family: 'Inter', sans-serif;
    position: relative;
    background-color: #f8fafc;
    overflow: hidden;
}

.snestx-login-bg {
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, rgba(248, 250, 252, 0) 70%);
    animation: pulseBg 10s infinite alternate;
    z-index: 0;
}

@keyframes pulseBg {
    0% { transform: scale(1) translate(0, 0); }
    100% { transform: scale(1.1) translate(2%, 2%); }
}

.snestx-login-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.1);
    position: relative;
    z-index: 1;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.snestx-login-card.fade-out {
    opacity: 0;
    transform: scale(0.95) translateY(10px);
}

.snestx-brand-icon {
    width: 64px;
    height: 64px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
}

.form-floating > .form-control:focus, 
.form-floating > .form-control:not(:placeholder-shown) {
    padding-top: 1.625rem;
    padding-bottom: 0.625rem;
}

.form-control {
    border-color: #e2e8f0;
    border-radius: 12px;
    padding: 0.75rem 1rem;
    background-color: rgba(255, 255, 255, 0.5);
    transition: all 0.2s ease;
}

.form-control:focus {
    background-color: #fff;
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
}

.btn-primary:active {
    transform: translateY(0);
}
</style>

<script>
window.showLoginForm = function() {
    const intro = document.getElementById('intro-card');
    const login = document.getElementById('login-card');
    intro.classList.add('fade-out');
    setTimeout(() => {
        intro.classList.add('d-none');
        login.classList.remove('d-none');
        setTimeout(() => login.classList.remove('fade-out'), 10);
    }, 400);
};

window.showIntro = function() {
    const intro = document.getElementById('intro-card');
    const login = document.getElementById('login-card');
    login.classList.add('fade-out');
    setTimeout(() => {
        login.classList.add('d-none');
        intro.classList.remove('d-none');
        setTimeout(() => intro.classList.remove('fade-out'), 10);
    }, 400);
};

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('resident-login-form');
    const errorDiv = document.getElementById('login-error');
    const loginBtn = document.getElementById('login-btn');
    const btnText = loginBtn.querySelector('.btn-text');
    const spinner = loginBtn.querySelector('.spinner-border');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // UI State
            errorDiv.classList.add('d-none');
            loginBtn.disabled = true;
            btnText.classList.add('d-none');
            spinner.classList.remove('d-none');

            const formData = new FormData(loginForm);
            formData.append('action', 'SNESTX51_resident_login');

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.data.redirect_url) {
                        window.location.href = data.data.redirect_url;
                    } else {
                        window.location.reload();
                    }
                } else {
                    errorDiv.textContent = data.data.message || 'Login failed. Please try again.';
                    errorDiv.classList.remove('d-none');
                    loginBtn.disabled = false;
                    btnText.classList.remove('d-none');
                    spinner.classList.add('d-none');
                }
            })
            .catch(err => {
                console.error('Login Error:', err);
                errorDiv.textContent = 'A network error occurred.';
                errorDiv.classList.remove('d-none');
                loginBtn.disabled = false;
                btnText.classList.remove('d-none');
                spinner.classList.add('d-none');
            });
        });
    }
});
</script>
