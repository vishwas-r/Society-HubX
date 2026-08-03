<?php
/**
 * Society HubX Uninstall
 *
 * Triggered when the plugin is uninstalled.
 * Cleans up options and custom database tables.
 *
 * @package SHUBX51_Plugin
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variables in uninstall context.
$shubx51_options = array(
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

foreach ( $shubx51_options as $shubx51_option ) {
	delete_option( $shubx51_option );
}

// Drop custom database tables.
global $wpdb;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local uninstall variables.
$shubx51_tables = array(
	'shubx51_flats',
	'shubx51_residents',
	'shubx51_resident_history',
	'shubx51_resident_role_map',
	'shubx51_daily_help',
	'shubx51_notices',
	'shubx51_documents',
	'shubx51_bookings',
	'shubx51_facilities',
	'shubx51_assets',
	'shubx51_expenses',
	'shubx51_invoices',
	'shubx51_ledger',
	'shubx51_payments',
	'shubx51_rules',
	'shubx51_rule_versions',
	'shubx51_rule_acknowledgments',
	'shubx51_rule_violations',
	'shubx51_rule_categories',
	'shubx51_requests',
	'shubx51_activity_logs',
);

foreach ( $shubx51_tables as $shubx51_table ) {
	$shubx51_table_name = $wpdb->prefix . $shubx51_table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE on uninstall; no caching needed.
	$wpdb->query( "DROP TABLE IF EXISTS {$shubx51_table_name}" );
}
