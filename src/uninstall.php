<?php
/**
 * Society HubX Uninstall
 *
 * Triggered when the plugin is uninstalled.
 * Cleans up options and custom database tables.
 *
 * @package Society_HubX
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
$options = array(
	'shubx51_society_name',
	'shubx51_society_address_line1',
	'shubx51_society_address_line2',
	'shubx51_society_city',
	'shubx51_society_contact',
	'shubx51_approval_family',
	'shubx51_approval_help',
	'shubx51_db_version',
	'shubx51_google_refresh_token',
	'shubx51_drive_root_id',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Drop custom database tables.
global $wpdb;

$tables = array(
	'society_hubx_flats',
	'society_hubx_residents',
	'society_hubx_resident_history',
	'society_hubx_resident_role_map',
	'society_hubx_daily_help',
	'society_hubx_notices',
	'society_hubx_documents',
	'society_hubx_bookings',
	'society_hubx_facilities',
	'society_hubx_assets',
	'society_hubx_expenses',
	'society_hubx_invoices',
	'society_hubx_ledger',
	'society_hubx_payments',
	'society_hubx_rules',
	'society_hubx_rule_versions',
	'society_hubx_rule_acknowledgments',
	'society_hubx_rule_violations',
	'society_hubx_rule_categories',
	'society_hubx_requests',
	'society_hubx_activity_logs',
);

foreach ( $tables as $table ) {
	$table_name = $wpdb->prefix . $table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe and hardcoded.
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}
