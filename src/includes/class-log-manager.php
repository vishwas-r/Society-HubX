<?php
/**
 * Class: Log Manager
 * Handles log governance, purging, and automated maintenance.
 *
 * @package Society_Govern_X
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SGVX51_Log_Manager {

	private $db;

	public function __construct($db = null) {
		$this->db = $db ?: Society_Govern_X::get_instance()->db;
        
        // Hook into Action Scheduler for daily cleanup
		add_action( 'sgvx51_daily_log_purge', array( $this, 'purge_old_logs' ) );
	}

	/**
	 * Purge logs older than the retention period.
	 */
	public function purge_old_logs() {
		$retention_days = (int) get_option( 'sgvx51_log_retention', 30 );
		
		if ( $retention_days <= 0 ) {
			return; // Unlimited retention
		}

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-$retention_days days" ) );
        
        global $wpdb;
        
        // 1. Purge Audit Logs
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}society_governx_audit_logs WHERE created_at < %s",
            $cutoff_date
        ));

        // 2. Purge Notification Logs
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}society_governx_notification_logs WHERE created_at < %s",
            $cutoff_date
        ));

        // Log the maintenance action
        $this->db->insert('audit_logs', [
            'user_id'     => 0, // System
            'action'      => 'system_log_purge',
            'entity_type' => 'system',
            'entity_id'   => 'maintenance',
            'details'     => "Automated purge completed. Logs older than $retention_days days removed.",
            'created_at'  => current_time('mysql')
        ]);
	}
}
