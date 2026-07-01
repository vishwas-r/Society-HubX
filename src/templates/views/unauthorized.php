<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * View: Unauthorized
 * Shown when a user lacks capabilities for a view.
 */
?>
<div class="d-flex flex-column align-items-center justify-content-center py-10">
    <div class="bg-danger bg-opacity-10 p-4 rounded-circle mb-4">
        <i class="bi bi-shield-lock text-danger fs-1"></i>
    </div>
    <h2 class="fw-bold text-slate-900 mb-2">Access Denied</h2>
    <p class="text-slate-500 text-center mb-4">You do not have the required permissions to access this module.<br>Please contact your society administrator if you believe this is an error.</p>
    <a href="<?php echo admin_url('admin.php?page=snestx51-settings'); ?>" class="btn btn-primary d-flex align-items-center gap-2 px-4 py-2.5 rounded-xl shadow-sm">
        <i class="bi bi-arrow-left"></i>
        <span>Back to Dashboard</span>
    </a>
</div>
