<?php
/**
 * Class: Email Provider
 * Handles email delivery via WP Mail or Gmail API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Email_Provider implements NAMMASOCIETY51_Notification_Provider_Interface {
    
    public function send($recipient_id, $content, $args = []) {
        $user = get_userdata($recipient_id);
        if (!$user) return new WP_Error('invalid_user', 'User not found');

        $to = $user->user_email;
        $subject = $content['subject'];
        $message = $content['body'];
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Logic to switch between WP Mail and Gmail API would go here
        $sent = wp_mail($to, $subject, $message, $headers);

        if (!$sent) {
            return new WP_Error('email_failed', 'WP Mail failed to send');
        }

        return [
            'status' => 'success',
            'to'     => $to,
            'cost'   => 0 // Email is usually free or fixed cost
        ];
    }

    public function get_slug() {
        return 'email';
    }

    public function is_ready() {
        $channels = NAMMASOCIETY51_Plugin::get_instance()->db->get('notification_channels');
        foreach ($channels as $c) {
            if ($c['channel_slug'] === 'email') return (bool) $c['is_active'];
        }
        return false;
    }
}

// Backward Compatibility Aliases
if ( class_exists( 'NAMMASOCIETY51_Email_Provider' ) && ! class_exists( 'SHUBX51_Email_Provider', false ) ) {
	class_alias( 'NAMMASOCIETY51_Email_Provider', 'SHUBX51_Email_Provider' );
}
