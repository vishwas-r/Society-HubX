<?php
/**
 * Class: WhatsApp Provider
 * Handles delivery via Twilio WhatsApp API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_WhatsApp_Provider implements SGVX51_Notification_Provider_Interface {
    
    public function send($user_id, $content, $args = []) {
        $config = $this->get_config();
        if (empty($config['sid']) || empty($config['token'])) {
            return new WP_Error('config_missing', 'Twilio SID or Token missing');
        }

        // WhatsApp cost check
        if (($config['current_usage'] ?? 0) >= ($config['monthly_budget'] ?? 100)) {
            return new WP_Error('budget_exceeded', 'WhatsApp monthly budget reached');
        }

        $resident = Society_GoVernX::get_instance()->db->get_resident_by_wp_id($user_id);
        if (!$resident || empty($resident['phone'])) {
            return new WP_Error('invalid_phone', 'Resident phone number not found');
        }

        $to = 'whatsapp:' . $resident['phone'];
        $from = 'whatsapp:' . ($config['from_number'] ?? '');
        $body = $content['body'];

        // Actual Twilio API Call (Mocked for now)
        // $response = wp_remote_post("https://api.twilio.com/2010-04-01/Accounts/{$config['sid']}/Messages.json", [...]);
        
        // Mock success with estimated cost
        $cost = 0.05; // Estimated $0.05 per msg

        return [
            'status' => 'success',
            'to'     => $to,
            'cost'   => $cost,
            'sid'    => 'SMXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
        ];
    }

    public function get_slug() {
        return 'whatsapp';
    }

    public function is_ready() {
        $config = $this->get_config();
        return !empty($config['is_active']) && !empty($config['sid']);
    }

    private function get_config() {
        $channels = Society_GoVernX::get_instance()->db->get('notification_channels');
        foreach ($channels as $c) {
            if ($c['channel_slug'] === 'whatsapp') {
                $settings = json_decode($c['config'], true) ?: [];
                $settings['is_active'] = $c['is_active'];
                return $settings;
            }
        }
        return [];
    }
}
