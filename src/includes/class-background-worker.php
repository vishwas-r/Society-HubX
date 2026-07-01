<?php
/**
 * Class: Background Worker
 * Interfaces with Action Scheduler for asynchronous task processing.
 *
 * @package Society_NestX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SNESTX51_Background_Worker {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into Action Scheduler events
        if ( $this->is_available() ) {
            add_action( 'SNESTX51_process_bulk_invoices', array( $this, 'process_bulk_invoices' ), 10, 4 );
            add_action( 'SNESTX51_process_notification_blast', array( $this, 'process_notification_blast' ), 10, 2 );
            add_action( 'SNESTX51_process_data_purge', array( $this, 'process_data_purge' ) );
        }
	}

    /**
     * Check if background processing is available.
     */
    public function is_available() {
        return function_exists( 'as_enqueue_async_action' );
    }

	/**
	 * Schedule bulk invoice generation.
	 *
	 * @param string $month Month in YYYY-MM format.
	 * @param float  $amount Default amount.
	 * @param string $type Invoice type.
	 */
	public function schedule_bulk_invoices( $month, $amount, $type = 'maintenance' ) {
		$db = new SNESTX51_DB_Router();
		$residents = $db->get( 'residents' );
		$total = count( $residents );
		
		$job_key = "SNESTX51_job_bulk_invoice_{$month}_{$type}";
		$status = array(
			'total'      => $total,
			'processed'  => 0,
			'status'     => 'running',
			'started_at' => current_time( 'mysql' ),
			'month'      => $month,
			'type'       => $type
		);
		update_option( $job_key, $status );

		if ( ! $this->is_available() ) return false;

		as_enqueue_async_action( 'SNESTX51_process_bulk_invoices', array( 'month' => $month, 'amount' => $amount, 'type' => $type, 'offset' => 0 ) );
	}

	/**
	 * Worker: Process Bulk Invoices (Batched).
	 */
	public function process_bulk_invoices( $month, $amount, $type = 'maintenance', $offset = 0 ) {
		$db = new SNESTX51_DB_Router();
		$limit = 50;
		$residents = $db->get( 'residents', array( 'limit' => $limit, 'offset' => $offset ) );
		$invoices = $db->get( 'invoices', array( 'where' => array( 'month' => $month, 'type' => $type ) ) );
		
		$generated_count = 0;
		$prefix = str_replace( '-', '', $month ); // 2024-05 -> 202405
		
		// Optimization: Only fetch max sequence once per batch if needed, but better to use a robust ID generator
		// For now, keeping the prefix + seq logic but making it safer
		$all_invoices = $db->get( 'invoices' ); 
		$max_seq = 0;
		foreach ( $all_invoices as $inv ) {
			if ( strpos( $inv['id'], $prefix ) === 0 ) {
				$seq = intval( substr( $inv['id'], 6 ) );
				if ( $seq > $max_seq ) $max_seq = $seq;
			}
		}
		$next_seq = $max_seq + 1;

		foreach ( $residents as $r ) {
			// Skip if invoice already exists
			$exists = false;
			foreach ( $invoices as $inv ) {
				if ( (string)$inv['flat_no'] === (string)$r['flat_no'] && (string)($inv['block'] ?? '') === (string)($r['block'] ?? '') ) {
					$exists = true; break;
				}
			}
			
			if ( ! $exists ) {
				$new_id = $prefix . str_pad( $next_seq, 3, '0', STR_PAD_LEFT );
				$data = array(
					'id'            => $new_id,
					'block'         => $r['block'] ?? '',
					'flat_no'       => $r['flat_no'],
					'resident_name' => $r['name'],
					'month'         => $month,
					'amount'        => $amount,
                    'total_paid'    => 0,
					'status'        => 'unpaid',
					'type'          => $type,
					'description'   => ucfirst( $type ) . ' for ' . $month,
					'created_at'    => current_time( 'mysql' )
				);
				
				$db->insert( 'invoices', $data );
				$generated_count++;
				$next_seq++;
			}
		}

		// Update Job Status
		$job_key = "SNESTX51_job_bulk_invoice_{$month}_{$type}";
		$status_opt = get_option( $job_key );
		if ( $status_opt ) {
			$status_opt['processed'] += count( $residents );
			if ( count( $residents ) < $limit ) {
				$status_opt['status'] = 'completed';
				$status_opt['completed_at'] = current_time( 'mysql' );
			}
			update_option( $job_key, $status_opt );
		}

		// Schedule next batch or Recursively process if in synchronous mode
		if ( count( $residents ) === $limit ) {
            if ( $this->is_available() ) {
                as_enqueue_async_action( 'SNESTX51_process_bulk_invoices', array( 
                    'month'  => $month, 
                    'amount' => $amount, 
                    'type'   => $type, 
                    'offset' => $offset + $limit 
                ) );
            } else {
                // FALLBACK: Recursive process to finish the job since background worker is not available
                $this->process_bulk_invoices( $month, $amount, $type, $offset + $limit );
            }
		}
		
		error_log( "SNESTX51 Background Worker: Processed batch at offset $offset. Generated $generated_count $type invoices for $month." ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Operational/debug logging.
	}

	/**
	 * Worker: Process Notification Blast.
	 */
	public function process_notification_blast( $event_slug, $data ) {
		if ( ! class_exists( 'Society_NestX' ) ) return;
		
		$snestx = Society_NestX::get_instance();
		if ( isset( $snestx->notifications ) ) {
			// Trigger for all relevant users (e.g., all residents)
			$db = new SNESTX51_DB_Router();
			$residents = $db->get( 'residents' );
			
			foreach ( $residents as $r ) {
				if ( ! empty( $r['wp_user_id'] ) ) {
					$snestx->notifications->trigger( $event_slug, $r['wp_user_id'], $data, true );
				}
			}
		}
	}

	/**
	 * Worker: Data Purge (DPDP Compliance).
	 */
	public function process_data_purge() {
		require_once SNESTX51_PLUGIN_DIR . 'includes/class-privacy-manager.php';
		$privacy = new SNESTX51_Privacy_Manager();
		$privacy->perform_scheduled_purge();
	}
}
