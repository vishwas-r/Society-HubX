<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Welcome Section
 * @var array $data Dashboard data containing resident profile.
 */
$r = $data['resident'] ?? [];
?>
<!-- Welcome Section (Simple) -->
<div class="bg-white rounded-3 shadow-sm border border-light p-4 mb-4 d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-3">
        <?php echo SNESTX51_Admin_UI::render_avatar( $r['name'] ?? 'Resident', $r['email'] ?? '', $r['profile_photo'] ?? '', 60 ); ?>
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">Hello, <?php echo esc_html( $r['name'] ?? 'Resident' ); ?> 👋</h1>
            <p class="text-secondary d-flex align-items-center gap-2 small">
                <span class="badge bg-primary-subtle text-primary px-2 py-1 rounded fw-semibold text-uppercase tracking-wide">Flat <?php echo esc_html( Society_NestX::get_instance()->db->get_flat_display_name( $r['flat_no'] ?? '' ) ); ?></span>
                <span class="text-muted">•</span>
                <span><?php echo esc_html( ucfirst( $r['type'] ?? 'Resident' ) ); ?></span>
                <?php if(!empty($r['email'])): ?>
                    <span class="text-muted">•</span>
                    <span class="text-secondary"><?php echo esc_html($r['email']); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button data-bs-toggle="modal" data-bs-target="#SNESTX51GeneralRequestModal" class="btn btn-primary rounded-3 text-sm fw-medium shadow-none">
            <i class="bi bi-plus-circle me-1"></i> Raise Request
        </button>
        <button data-bs-toggle="modal" data-bs-target="#editProfileModal" class="btn btn-outline-secondary border-light rounded-3 text-sm fw-medium shadow-none">
            Edit Profile
        </button>
    </div>
</div>
