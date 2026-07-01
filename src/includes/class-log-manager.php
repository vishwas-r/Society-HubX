<?php
/**
 * Class: Log Manager
 * Handles log governance, purging, and automated maintenance.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Log_Manager {

	private $db;

	public function __construct($db = null) {
		$this->db = $db ?: Society_NestX::get_instance()->db;
        
        // Hook into Action Scheduler for daily cleanup
		add_action( 'SNESTX51_daily_log_purge', array( $this, 'purge_old_logs' ) );
	}

	/**
	 * Purge logs older than the retention period.
	 */
	public function purge_old_logs() {
		$retention_days = (int) get_option( 'SNESTX51_log_retention', 30 );
		
		if ( $retention_days <= 0 ) {
			return; // Unlimited retention
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-$retention_days days" ) );
        
        global $wpdb;
        
        // 1. Purge Audit Logs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance cron delete query.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}society_nestx_audit_logs WHERE created_at < %s",
            $cutoff_date
        ));

        // 2. Purge Notification Logs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Maintenance cron delete query.
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}society_nestx_notification_logs WHERE created_at < %s",
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
