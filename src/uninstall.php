<?php
/**
 * Namma Society Uninstall
 *
 * Triggered when the plugin is uninstalled.
 * Cleans up options and custom database tables.
 *
 * @package NAMMASOCIETY51_Plugin
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variables in uninstall context.
$nammasociety51_options = array(
	'nammasociety51_society_name',
	'nammasociety51_society_address_line1',
	'nammasociety51_society_address_line2',
	'nammasociety51_society_city',
	'nammasociety51_society_contact',
	'nammasociety51_approval_family',
	'nammasociety51_approval_help',
	'nammasociety51_db_version',
	'nammasociety51_google_refresh_token',
	'nammasociety51_drive_root_id',
);

foreach ( $nammasociety51_options as $nammasociety51_option ) {
	delete_option( $nammasociety51_option );
}

// Drop custom database tables.
global $wpdb;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local uninstall variables.
$nammasociety51_tables = array(
	'nammasociety51_flats',
	'nammasociety51_residents',
	'nammasociety51_resident_history',
	'nammasociety51_resident_role_map',
	'nammasociety51_daily_help',
	'nammasociety51_notices',
	'nammasociety51_documents',
	'nammasociety51_bookings',
	'nammasociety51_facilities',
	'nammasociety51_assets',
	'nammasociety51_expenses',
	'nammasociety51_invoices',
	'nammasociety51_ledger',
	'nammasociety51_payments',
	'nammasociety51_rules',
	'nammasociety51_rule_versions',
	'nammasociety51_rule_acknowledgments',
	'nammasociety51_rule_violations',
	'nammasociety51_rule_categories',
	'nammasociety51_requests',
	'nammasociety51_activity_logs',
);

foreach ( $nammasociety51_tables as $nammasociety51_table ) {
	$nammasociety51_table_name = $wpdb->prefix . $nammasociety51_table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional DROP TABLE on uninstall; no caching needed.
	$wpdb->query( "DROP TABLE IF EXISTS {$nammasociety51_table_name}" );
}
