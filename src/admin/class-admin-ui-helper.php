<?php
/**
 * Class: Admin UI Helper
 * Enqueues Bootstrap and custom styles for Admin Backend.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Admin_UI {

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

    public static function render_inline_actions( $status, $id, $module = '' ) {
        $html = '<div class="d-flex justify-content-end gap-1 sgvx-inline-actions">';
        
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
		// Assets are now centrally handled in society-govern-x.php with priority 999 
        // to ensure correct loading order and Bootstrap overrides.
        return;
        
		// Only load on our plugin pages
		if ( strpos( $hook, 'sgvx51' ) === false ) {
			return;
		}

		// 1. Google Fonts (Inter) - Local
		wp_enqueue_style( 'sgvx51-fonts', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/lib/inter-fonts.css', array(), '1.0' );

		// 2. Bootstrap 5 - Local
		wp_enqueue_style( 'sgvx51-bootstrap', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/lib/bootstrap.min.css', array(), '5.3.0' );
		wp_enqueue_style( 'sgvx51-bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array(), '1.11.3' );
		wp_enqueue_script( 'sgvx51-bootstrap-js', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/lib/bootstrap.bundle.min.js', array('jquery'), '5.3.0', false );

        // 3. Admin Premium Theme
		wp_enqueue_style( 'sgvx51-admin-layout', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-layout.css', array('sgvx51-bootstrap'), '1.0.0' );
		wp_enqueue_style( 'sgvx51-admin-premium', plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-premium.css', array('sgvx51-bootstrap', 'sgvx51-admin-layout'), '1.0.1' );
	}
}

SGVX51_Admin_UI::init();
