<?php
/**
 * Class: Admin App
 * Handles the full-screen "App Mode" rendering for the admin panel.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Admin_App {

	/**
	 * Render a specific view in the App Wrapper.
	 * 
	 * @param string $view_name The slug of the view (e.g. 'residents', 'expenses').
     * @param array  $context   Optional data to pass to the view.
	 */
	public static function render_view( $view_name, $context = array() ) {
		// Set context variables to be available in the included file
        // $current_view is used by the wrapper
        $current_view = sanitize_key( $view_name );
        
        // Extract context array to variables if needed (optional)
        extract($context);

        // Include the wrapper. 
        // The wrapper will handle including templates/views/$view_name.php
		include SNESTX51_PLUGIN_DIR . 'templates/admin-app-wrapper.php';
		
        // Exit to prevent WP Footer/Admin Bar from rendering after our full-screen overlay
        exit;
	}
}
