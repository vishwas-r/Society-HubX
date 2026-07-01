<?php
/**
 * SocietyNestX Uninstall
 *
 * Triggered when the plugin is uninstalled.
 * Cleans up options and custom database tables.
 *
 * @package Society_NestX
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
$options = array(
	'SNESTX51_society_name',
	'SNESTX51_society_address_line1',
	'SNESTX51_society_address_line2',
	'SNESTX51_society_city',
	'SNESTX51_society_contact',
	'SNESTX51_approval_family',
	'SNESTX51_approval_help',
	'SNESTX51_db_version',
	'SNESTX51_google_refresh_token',
	'SNESTX51_drive_root_id',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Drop custom database tables.
global $wpdb;

$tables = array(
	'society_nestx_flats',
	'society_nestx_residents',
	'society_nestx_resident_history',
	'society_nestx_resident_role_map',
	'society_nestx_daily_help',
	'society_nestx_notices',
	'society_nestx_documents',
	'society_nestx_bookings',
	'society_nestx_facilities',
	'society_nestx_assets',
	'society_nestx_expenses',
	'society_nestx_invoices',
	'society_nestx_ledger',
	'society_nestx_payments',
	'society_nestx_rules',
	'society_nestx_rule_versions',
	'society_nestx_rule_acknowledgments',
	'society_nestx_rule_violations',
	'society_nestx_rule_categories',
	'society_nestx_requests',
	'society_nestx_activity_logs',
);

foreach ( $tables as $table ) {
	$table_name = $wpdb->prefix . $table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe and hardcoded.
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
}
