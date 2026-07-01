<?php
// phpcs:disable Internal.LineEndings.Mixed
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SNESTX Database Update Utility
 * Run this file manually to ensure all tables are created and data is migrated.
 * Usage: Place in plugin folder and visit via browser, or run via WP-CLI/Command line.
 */

// Load WordPress
$snestx51_wp_load = __DIR__ . '/../../wp-load.php';
if (!file_exists($snestx51_wp_load)) {
    // Try root search if not in standard wp-content/plugins/x location
    $snestx51_wp_load = dirname(__FILE__, 4) . '/wp-load.php';
}

if (!file_exists($snestx51_wp_load)) {
    die("Error: Could not find wp-load.php. Please ensure this script is inside the 'society-nestx/src/' directory on your WordPress installation.");
}

require_once $snestx51_wp_load;

// Verify Admin or Command Line
if (!is_admin() && php_sapi_name() !== 'cli') {
    if (!current_user_can('manage_options')) {
        die("Unauthorized: You must be an administrator to run this script.");
    }
}

echo "<h1>Society NestX - Database Update Utility</h1>";
echo "<p>Starting database update and migration...</p>";

// 1. Force Include Plugin Classes
require_once dirname(__FILE__) . '/includes/class-db-schema.php';
require_once dirname(__FILE__) . '/includes/class-data-migrator.php';
require_once dirname(__FILE__) . '/includes/class-rbac-manager.php';

// 2. Trigger Table Creation
echo "<li>Updating Table Schemas (dbDelta)... ";
SNESTX51_DB_Schema::create_tables();
echo "<span style='color:green'>Done.</span></li>";

// 3. Trigger Data Migration
echo "<li>Migrating Data from JSON to Relational... ";
SNESTX51_Data_Migrator::run_all();
echo "<span style='color:green'>Done.</span></li>";

// 4. Update Version Option
update_option('snestx51_version', '1.0.2');
update_option('snestx51_storage_migrated', '1.0.2');

echo "<p style='color:green; font-weight:bold; font-size:1.2rem;'>Success: Database is now at version 1.0.2 and fully relational.</p>";
echo "<p>You can now delete this file from your server for security.</p>";
echo "<a href='" . esc_url( admin_url('admin.php?page=snestx51-settings') ) . "'>Return to Dashboard</a>";
