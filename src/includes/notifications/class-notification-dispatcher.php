<?php
/**
 * Class: Notification Dispatcher
 * The central hub for triggering and routing notifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SHUBX51_Notification_Dispatcher {
    /**
     * @var SHUBX51_Notification_Provider_Interface[]
     */
    private $providers = [];

    /**
     * @var SHUBX51_DB_Router
     */
    private $db;

    public function __construct($db = null) {
        $this->db = $db ?: Society_HubX::get_instance()->db;
        $this->load_providers();
        
        // Hook into Action Scheduler for background processing
        add_action('shubx51_async_notification', [$this, 'dispatch_now'], 10, 4);

        // Self-Heal Schema
        if ( is_admin() ) {
            $this->db->verify_column( 'notification_logs', 'payload', 'longtext' );
            $this->db->verify_column( 'notification_logs', 'actor_id', 'bigint(20) DEFAULT 0 NOT NULL' );
        }
    }

    /**
     * Register available providers.
     */
    private function load_providers() {
        require_once plugin_dir_path(__FILE__) . 'class-email-provider.php';
        require_once plugin_dir_path(__FILE__) . 'class-whatsapp-provider.php';
        require_once plugin_dir_path(__FILE__) . 'class-inapp-provider.php';

        $this->providers['email']    = new SHUBX51_Email_Provider();
        $this->providers['whatsapp'] = new SHUBX51_WhatsApp_Provider();
        $this->providers['inapp']    = new SHUBX51_InApp_Provider();
    }

    /**
     * Trigger a notification for an event.
     * 
     * @param string $event_slug e.g., 'visitor_checkin'
     * @param int $user_id Recipient WP User ID
     * @param array $data Dynamic data for templates
     * @param bool $async Whether to send immediately or via queue
     * @param int $actor_id The user ID of the admin who triggered this (optional)
     */
    public function trigger($event_slug, $user_id, $data = [], $async = true, $actor_id = 0) {
        if ($actor_id === 0 && is_user_logged_in()) {
            $actor_id = get_current_user_id();
        }

        if ($async && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('shubx51_async_notification', [$event_slug, $user_id, $data, $actor_id]);
            return true;
        }

        return $this->dispatch_now($event_slug, $user_id, $data, $actor_id);
    }

    /**
     * Process the notification delivery.
     */
    public function dispatch_now($event_slug, $user_id, $data, $actor_id = 0) {
        // 1. Get Channels enabled for this event (Admin Default)
        $event = $this->get_event_config($event_slug);
        if (!$event) return false;

        $channels = explode(',', $event['default_channels']);

        foreach ($channels as $channel_slug) {
            $channel_slug = trim($channel_slug);
            
            // 2. Check User Preference
            if (!$this->is_user_opted_in($user_id, $event_slug, $channel_slug)) {
                continue;
            }

            // 3. Get Template
            $template = $this->get_template($event_slug, $channel_slug);
            if (!$template) continue;

            // 4. Parse Template
            $content = $this->parse_template($template, $data);

            // 5. Check Provider Readiness (Global Admin Toggle)
            if (!isset($this->providers[$channel_slug]) || !$this->providers[$channel_slug]->is_ready()) {
                continue;
            }

            // 6. Send via Provider
            $response = $this->providers[$channel_slug]->send($user_id, $content, ['event_slug' => $event_slug]);

            // 7. Log result
            $this->log_notification($user_id, $event_slug, $channel_slug, $response, $content, $actor_id);
        }

        return true;
    }

    private function get_event_config($slug) {
        $events = $this->db->get('notification_events');
        foreach ($events as $event) {
            if ($event['event_slug'] === $slug) return $event;
        }
        return null;
    }

    private function is_user_opted_in($user_id, $event_slug, $channel_slug) {
        // Check if explicit preference exists
        $prefs = $this->db->get('notification_preferences');
        foreach ($prefs as $p) {
            if ($p['user_id'] == $user_id && $p['event_slug'] === $event_slug && $p['channel'] === $channel_slug) {
                return (bool) $p['is_enabled'];
            }
        }

        // Default: Opted-in for new residents (as requested by user)
        return true; 
    }

    private function get_template($event_slug, $channel_slug) {
        $templates = $this->db->get('notification_templates');
        foreach ($templates as $t) {
            if ($t['event_slug'] === $event_slug && $t['channel'] === $channel_slug && $t['is_active']) {
                return $t;
            }
        }
        return null;
    }

    private function parse_template($template, $data) {
        $subject = $template['subject'] ?? '';
        $body    = $template['content'];

        foreach ($data as $key => $value) {
            $tag = '{' . $key . '}';
            $subject = str_replace($tag, $value, $subject);
            $body    = str_replace($tag, $value, $body);
        }

        return [
            'subject' => $subject,
            'body'    => $body
        ];
    }

    private function log_notification($user_id, $event_slug, $channel_slug, $response, $content = [], $actor_id = 0) {
        $is_error = is_wp_error($response);
        $cost = (!$is_error && isset($response['cost'])) ? $response['cost'] : 0;

        $this->db->insert('notification_logs', [
            'user_id'    => $user_id,
            'actor_id'   => $actor_id,
            'event_slug' => $event_slug,
            'channel'    => $channel_slug,
            'status'     => $is_error ? 'failed' : 'sent',
            'payload'    => json_encode($content),
            'response'   => $is_error ? $response->get_error_message() : json_encode($response),
            'cost'       => $cost,
            'created_at' => current_time('mysql')
        ]);

        // Update Budget tracking if cost exists
        if ($cost > 0) {
            $this->update_budget($channel_slug, $cost);
        }
    }

    private function update_budget($channel_slug, $cost) {
        $channels = $this->db->get('notification_channels');
        foreach ($channels as $c) {
            if ($c['channel_slug'] === $channel_slug) {
                $config = json_decode($c['config'], true) ?: [];
                $config['current_usage'] = ($config['current_usage'] ?? 0) + $cost;
                
                $this->db->update('notification_channels', 
                    ['config' => json_encode($config)], 
                    ['channel_slug' => $channel_slug]
                );
                return;
            }
        }
    }
}
