window.showLoginForm = function() {
    const intro = document.getElementById('intro-card');
    const login = document.getElementById('login-card');
    if (!intro || !login) return;
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
    if (!intro || !login) return;
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
    if (!loginForm || !loginBtn || !errorDiv) return;

    const btnText = loginBtn.querySelector('.btn-text');
    const spinner = loginBtn.querySelector('.spinner-border');

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // UI State
        errorDiv.classList.add('d-none');
        loginBtn.disabled = true;
        if (btnText) btnText.classList.add('d-none');
        if (spinner) spinner.classList.remove('d-none');

        const formData = new FormData(loginForm);
        formData.append('action', 'shubx51_resident_login');

        const activeAjaxurl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '';
        fetch(activeAjaxurl, {
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
                if (btnText) btnText.classList.remove('d-none');
                if (spinner) spinner.classList.add('d-none');
            }
        })
        .catch(err => {
            console.error('Login Error:', err);
            errorDiv.textContent = 'A network error occurred.';
            errorDiv.classList.remove('d-none');
            loginBtn.disabled = false;
            if (btnText) btnText.classList.remove('d-none');
            if (spinner) spinner.classList.add('d-none');
        });
    });
});
