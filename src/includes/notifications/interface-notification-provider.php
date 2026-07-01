<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface: Notification Provider
 * Defines the structure for all notification delivery channels.
 */

interface SNESTX51_Notification_Provider_Interface {
    /**
     * Send a notification.
     * 
     * @param int|array $recipient Recipient ID or information.
     * @param array $content Notification content (subject, body, etc).
     * @param array $args Additional arguments (e.g. priority, file attachments).
     * @return array|WP_Error Status and response data or error.
     */
    public function send($recipient, $content, $args = []);

    /**
     * Get the provider's unique slug (email, whatsapp, inapp).
     */
    public function get_slug();

    /**
     * Check if the provider is correctly configured and ready to send.
     */
    public function is_ready();
}
