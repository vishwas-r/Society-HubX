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
        <?php echo SHUBX51_Admin_UI::render_avatar( $r['name'] ?? 'Resident', $r['email'] ?? '', $r['profile_photo'] ?? '', 60 ); ?>
        <div>
            <h1 class="h4 fw-bold text-dark mb-1">Hello, <?php echo esc_html( $r['name'] ?? 'Resident' ); ?> 👋</h1>
            <p class="text-secondary d-flex align-items-center gap-2 small">
                <?php if ( ! empty( $data['my_flats'] ) && count( $data['my_flats'] ) > 1 ) : ?>
                    <span class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle px-2.5 py-1 rounded fw-bold text-uppercase tracking-wide shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.75rem;">
                            Flat <?php echo esc_html( Society_HubX::get_instance()->db->get_flat_display_name( $data['active_flat_no'] ?? '' ) ); ?>
                        </button>
                        <ul class="dropdown-menu border-0 shadow-lg rounded-3 mt-1" style="font-size: 0.85rem;">
                            <li><h6 class="dropdown-header text-uppercase small text-muted">Switch Flat</h6></li>
                            <?php foreach ( $data['my_flats'] as $f ) : 
                                $f_label = ( ! empty( $f['block'] ) ? $f['block'] . '-' : '' ) . $f['flat_number'];
                                $is_active = ( $f['id'] === $data['active_flat_no'] );
                            ?>
                                <li>
                                    <a class="dropdown-item fw-semibold d-flex justify-content-between align-items-center py-2 js-switch-flat-btn <?php echo $is_active ? 'active bg-primary text-white' : ''; ?>" 
                                       href="#" 
                                       data-flat-id="<?php echo esc_attr( $f['id'] ); ?>">
                                        <span><?php echo esc_html( $f_label ); ?></span>
                                        <?php if ( $is_active ) : ?>
                                            <i class="bi bi-check-circle-fill text-white ms-3"></i>
                                        <?php else: ?>
                                            <i class="bi bi-chevron-right text-secondary opacity-50 ms-3"></i>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </span>
                <?php else: ?>
                    <span class="badge bg-primary-subtle text-primary px-2 py-1 rounded fw-semibold text-uppercase tracking-wide">Flat <?php echo esc_html( Society_HubX::get_instance()->db->get_flat_display_name( $data['active_flat_no'] ?? $r['flat_no'] ?? '' ) ); ?></span>
                <?php endif; ?>
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
        <button data-bs-toggle="modal" data-bs-target="#SHUBX51GeneralRequestModal" class="btn btn-primary rounded-3 text-sm fw-medium shadow-none">
            <i class="bi bi-plus-circle me-1"></i> Raise Request
        </button>
        <button data-bs-toggle="modal" data-bs-target="#editProfileModal" class="btn btn-outline-secondary border-light rounded-3 text-sm fw-medium shadow-none">
            Edit Profile
        </button>
    </div>
</div>
