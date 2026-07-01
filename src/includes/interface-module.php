<?php
/**
 * Interface: Module
 * 
 * Standard interface for all SocietyNestX modules to support
 * centralized request handling (Approval/Rejection).
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SNESTX51_Module {
    /**
     * Get the unique slug for this module.
     * Used to route requests to the correct module.
     *
     * @return string Module ID (e.g., 'vehicles', 'residents')
     */
    public function get_module_slug();

    /**
     * Execute a approved request.
     *
     * @param string $action  The action to perform (add, edit, delete).
     * @param array  $payload The data associated with the request.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function execute_request( $action, $payload );
}
