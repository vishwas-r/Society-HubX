<?php
/**
 * Class: In-App Provider
 * Handles storage of notifications to be displayed within the user dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_InApp_Provider implements SNESTX51_Notification_Provider_Interface {
    
    public function send($user_id, $content, $args = []) {
        $db = Society_NestX::get_instance()->db;

        $inserted = $db->insert('inapp_notifications', [
            'user_id'    => $user_id,
            'event_slug' => $args['event_slug'] ?? 'general',
            'title'      => $content['subject'] ?: 'New Notification',
            'content'    => $content['body'],
            'is_read'    => 0,
            'created_at' => current_time('mysql')
        ]);

        if (is_wp_error($inserted)) {
            return $inserted;
        }

        return [
            'status' => 'success',
            'id'     => $inserted,
            'cost'   => 0
        ];
    }

    public function get_slug() {
        return 'inapp';
    }

    public function is_ready() {
        // In-App is usually always ready if the DB is up, but we respect the Admin toggle
        $channels = Society_NestX::get_instance()->db->get('notification_channels');
        foreach ($channels as $c) {
            if ($c['channel_slug'] === 'inapp') return (bool) $c['is_active'];
        }
        return true; 
    }
}
