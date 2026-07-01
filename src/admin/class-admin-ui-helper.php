<?php
/**
 * Class: Admin UI Helper
 * Enqueues Bootstrap and custom styles for Admin Backend.
 *
 * @package Society_HubX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Admin_UI {

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

    public static function render_status_badge( $status ) {
        $status = strtolower( $status );
        $classes = 'badge border-0 px-3 py-1.5 rounded-pill fw-bold text-uppercase';
        $style = 'font-size: 9px; letter-spacing: 0.05em;';
        
        switch ( $status ) {
            case 'active':
            case 'approved':
                $classes .= ' bg-success bg-opacity-10 text-success border border-success border-opacity-10';
                $label = 'Active';
                break;
            case 'pending':
                $classes .= ' bg-warning bg-opacity-10 text-warning border border-warning border-opacity-10';
                $label = 'Pending';
                break;
            case 'archived':
                $classes .= ' bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10';
                $label = 'Archived';
                break;
            case 'rejected':
                $classes .= ' bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10';
                $label = 'Rejected';
                break;
            default:
                $classes .= ' bg-light text-dark';
                $label = ucfirst( $status );
        }

        return sprintf( '<span class="%s" style="%s">%s</span>', esc_attr( $classes ), esc_attr( $style ), esc_html( $label ) );
    }

    /**
     * Render a consistent avatar/profile picture.
     * Fallback order: Profile URL -> Gravatar -> Stylish Initials.
     */
    public static function render_avatar( $name, $email = '', $photo_url = '', $size = 44 ) {
        $radius = 8; // rounded-3 equivalent
        $style = sprintf('width: %dpx; height: %dpx; font-size: %frem; flex-shrink: 0;', $size, $size, ($size / 40));
        
        // 0. Path Correction (Legacy/Absolute Path Fix)
        if ( ! empty( $photo_url ) && ( strpos( $photo_url, ':\\' ) !== false || strpos( $photo_url, ':/' ) !== false ) ) {
            $upload_dir = wp_upload_dir();
            $base_dir = str_replace( '\\', '/', $upload_dir['basedir'] );
            $base_url = $upload_dir['baseurl'];
            $photo_url = str_replace( '\\', '/', $photo_url );
            $photo_url = str_replace( $base_dir, $base_url, $photo_url );
        }

        // 1. Profile Photo Case
        if ( ! empty( $photo_url ) ) {
            return sprintf(
                '<div class="bg-light rounded-3 overflow-hidden d-flex align-items-center justify-content-center border shadow-sm" style="%s font-size: 0px;">
                    <img src="%s" class="w-100 h-100 object-fit-cover" alt="%s" onerror="this.parentElement.innerHTML=\'%s\'">
                </div>',
                $style,
                esc_url( $photo_url ),
                esc_attr( $name ),
                $email ? get_avatar( $email, $size, '', '', ['class' => 'w-100 h-100']) : '<div class=\"w-100 h-100 d-flex align-items-center justify-content-center bg-secondary text-white fw-bold\">' . strtoupper(substr($name ?? 'U', 0, 1)) . '</div>'
            );
        }
        
        // 2. Gravatar Fallback (if email exists)
        if ( ! empty( $email ) ) {
            $gravatar = get_avatar_url( $email, ['size' => $size] );
            if ( strpos($gravatar, 'd=mm') === false && strpos($gravatar, 'd=mp') === false && strpos($gravatar, 'd=blank') === false ) {
                 return sprintf(
                    '<div class="bg-light rounded-3 overflow-hidden d-flex align-items-center justify-content-center border shadow-sm" style="%s font-size: 0px;">
                        <img src="%s" class="w-100 h-100 object-fit-cover" alt="%s">
                    </div>',
                    $style,
                    esc_url( $gravatar ),
                    esc_attr( $name )
                );
            }
        }

        // 3. Initial Fallback
        $initial = strtoupper( substr( $name ?? 'U', 0, 1 ) );
        $bg_colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#6f42c1', '#fd7e14', '#20c997'];
        $bg_color = $bg_colors[ord($initial) % count($bg_colors)];

        return sprintf(
            '<div class="rounded-3 d-flex align-items-center justify-content-center fw-bold text-white shadow-sm" style="background-color: %s; %s">
                %s
            </div>',
            $bg_color,
            $style,
            $initial
        );
    }

    public static function render_inline_actions( $status, $id, $module = '' ) {
        $html = '<div class="d-flex justify-content-end gap-1 shubx-inline-actions">';
        
        if ( $status === 'pending' ) {
            $html .= sprintf(
                '<button type="button" class="btn btn-sm btn-light text-success border shadow-sm rounded-3 p-1 js-approve-inline" data-id="%s" data-module="%s" title="Approve">
                    <i class="bi bi-check-lg" style="font-size: 1.1rem;"></i>
                </button>',
                esc_attr( $id ),
                esc_attr( $module )
            );
            $html .= sprintf(
                '<button type="button" class="btn btn-sm btn-light text-danger border shadow-sm rounded-3 p-1 js-reject-inline" data-id="%s" data-module="%s" title="Reject">
                    <i class="bi bi-x-lg" style="font-size: 1.1rem;"></i>
                </button>',
                esc_attr( $id ),
                esc_attr( $module )
            );
        }

        $html .= '</div>';
        return $html;
    }

    public static function render_approval_buttons( $id, $module = '' ) {
        return sprintf(
            '<div class="d-flex gap-2 justify-content-end">
                <button type="button" class="btn btn-sm btn-success fw-bold px-3 rounded-pill js-approve-inline" data-id="%s" data-module="%s">APPROVE</button>
                <button type="button" class="btn btn-sm btn-outline-danger fw-bold px-3 rounded-pill js-reject-inline" data-id="%s" data-module="%s">REJECT</button>
            </div>',
            esc_attr( $id ), esc_attr( $module ),
            esc_attr( $id ), esc_attr( $module )
        );
    }

	public static function enqueue_admin_assets( $hook ) {
		// Assets are now centrally handled in society-hubx.php with priority 999 
        // to ensure correct loading order and Bootstrap overrides.
        return;
        
		// Only load on our plugin pages
		if ( strpos( $hook, 'SHUBX51' ) === false ) {
			return;
		}

		// 1. Google Fonts (Inter) - Local
		wp_enqueue_style( 'shubx-fonts', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 2. Bootstrap 5 - Local
		wp_enqueue_style( 'shubx-bootstrap', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_style( 'shubx-bootstrap-icons', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/lib/bootstrap-icons.min.css', array(), '1.11.3' );
		wp_enqueue_script( 'shubx-bootstrap-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/lib/bootstrap.bundle.min.js', array('jquery'), '5.3.0', false );

        // 3. Admin Premium Theme
		wp_enqueue_style( 'shubx51-admin-layout', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-layout.css', array('shubx51-bootstrap'), '1.0.0' );
		wp_enqueue_style( 'shubx51-admin-premium', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-premium.css', array('shubx51-bootstrap', 'shubx51-admin-layout'), '1.0.1' );
	}
}

SHUBX51_Admin_UI::init();
