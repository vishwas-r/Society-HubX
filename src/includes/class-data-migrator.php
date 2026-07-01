<?php
/**
 * Class: Data Migrator
 * Handles migration from JSON blobs to relational MySQL tables.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Data migration routines require direct database table reads and writes.


class SNESTX51_Data_Migrator {

	/**
	 * Run all migrations.
	 */
	public static function run_all() {
		self::migrate_resident_roles();
		self::migrate_staff_flats();
		self::migrate_payments();
		
		update_option( 'snestx51_storage_migrated', '1.0.2' );
	}

	/**
	 * Migrate Residents Roles.
	 */
	public static function migrate_resident_roles() {
		global $wpdb;
		$residents = $wpdb->get_results( "SELECT id, roles FROM {$wpdb->prefix}society_nestx_residents", ARRAY_A );
		$map_table = "{$wpdb->prefix}society_nestx_resident_role_map";

		foreach ( $residents as $res ) {
			if ( empty( $res['roles'] ) ) continue;
			$roles = json_decode( $res['roles'], true );
			if ( ! is_array( $roles ) ) continue;

			foreach ( $roles as $role_id ) {
				// Check if already exists to avoid duplicates
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $map_table WHERE resident_id = %s AND role_id = %s", $res['id'], $role_id ) );
				if ( ! $exists ) {
					$wpdb->insert( $map_table, array( 'resident_id' => $res['id'], 'role_id' => $role_id ) );
				}
			}
		}
	}

	/**
	 * Migrate Staff Flats Served.
	 */
	public static function migrate_staff_flats() {
		global $wpdb;
		$staff = $wpdb->get_results( "SELECT id, flats_served FROM {$wpdb->prefix}society_nestx_daily_help", ARRAY_A );
		$map_table = "{$wpdb->prefix}society_nestx_staff_flats";

		foreach ( $staff as $s ) {
			if ( empty( $s['flats_served'] ) ) continue;
			$flats = json_decode( $s['flats_served'], true );
			if ( ! is_array( $flats ) ) continue;

			foreach ( $flats as $flat_id ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $map_table WHERE staff_id = %s AND flat_id = %s", $s['id'], $flat_id ) );
				if ( ! $exists ) {
					$wpdb->insert( $map_table, array( 'staff_id' => $s['id'], 'flat_id' => $flat_id ) );
				}
			}
		}
	}

	public static function migrate_payments() {
		global $wpdb;
		$invoices = $wpdb->get_results( "SELECT id, payments FROM {$wpdb->prefix}society_nestx_invoices", ARRAY_A );
		$payments_table = "{$wpdb->prefix}society_nestx_payments";
        $invoices_table = "{$wpdb->prefix}society_nestx_invoices";

		foreach ( $invoices as $inv ) {
			if ( empty( $inv['payments'] ) ) continue;
			$payments = json_decode( $inv['payments'], true );
			if ( ! is_array( $payments ) ) continue;

            $total_paid = 0;

			foreach ( $payments as $p ) {
                $amount = floatval($p['amount'] ?? 0);
                $total_paid += $amount;
				$txn_id = $p['id'] ?? uniqid( 'txn_' );
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $payments_table WHERE id = %s", $txn_id ) );
				if ( ! $exists ) {
					$wpdb->insert( $payments_table, array(
						'id'          => $txn_id,
						'invoice_id'  => $inv['id'],
						'amount'      => $amount,
						'method'      => $p['method'] ?? 'UPI',
						'reference'   => $p['reference'] ?? '',
						'date'        => $p['date'] ?? current_time( 'mysql' ),
						'recorded_by' => $p['recorded_by'] ?? 0,
						'metadata'    => json_encode( $p )
					) );
				}
			}

            // Sync total_paid for backward compatibility with the new column
            $wpdb->update( $invoices_table, ['total_paid' => $total_paid], ['id' => $inv['id']] );
		}
	}
}
