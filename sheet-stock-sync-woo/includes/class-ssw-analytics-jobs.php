<?php
/**
 * Bounded background jobs for analytics aggregation.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Analytics_Jobs {

	const CRON_BACKFILL = 'ssw_analytics_backfill_batch';
	const CRON_SNAPSHOT = 'ssw_inventory_daily_snapshot';
	const SNAPSHOT_CURSOR_OPTION = 'ssw_inventory_snapshot_cursor';

	public function __construct() {
		add_action( self::CRON_BACKFILL, array( $this, 'run_backfill' ) );
		add_action( self::CRON_SNAPSHOT, array( $this, 'run_snapshot' ) );
		add_action( 'admin_init', array( $this, 'ensure_scheduled' ) );
	}

	public static function normalize_batch_size( $value ) {
		return max( 10, min( 250, absint( $value ) ) );
	}

	public function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::CRON_BACKFILL ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_BACKFILL );
		}
		if ( ! wp_next_scheduled( self::CRON_SNAPSHOT ) ) {
			wp_schedule_event( strtotime( 'tomorrow 02:15' ), 'daily', self::CRON_SNAPSHOT );
		}
	}

	public function run_backfill() {
		SSW_Sales_Aggregator::process_order_batch( 100 );
	}

	public function run_snapshot() {
		self::capture_snapshot_batch( 200 );
	}

	public static function capture_snapshot_batch( $batch_size = 200 ) {
		global $wpdb;
		$limit = self::normalize_batch_size( $batch_size );
		$offset = max( 0, absint( get_option( self::SNAPSHOT_CURSOR_OPTION, 0 ) ) );
		$stock_table = $wpdb->prefix . 'ssw_location_stock';
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$sql = "SELECT s.product_id, s.location_id, s.quantity, p.cost AS unit_cost FROM {$stock_table} s LEFT JOIN {$relations} p ON p.product_id = s.product_id AND p.is_preferred = 1 ORDER BY s.product_id ASC, s.location_id ASC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit, $offset ), ARRAY_A );
		$date = current_time( 'Y-m-d' );
		$processed = 0;
		foreach ( $rows as $row ) {
			$product = wc_get_product( absint( $row['product_id'] ) );
			$retail = $product ? $product->get_price() : null;
			SSW_Sales_Aggregator::persist_inventory_snapshot( array(
				'date' => $date,
				'product_id' => $row['product_id'],
				'location_id' => $row['location_id'],
				'quantity' => $row['quantity'],
				'unit_cost' => $row['unit_cost'],
				'retail_price' => $retail,
			) );
			$processed++;
		}
		if ( $processed < $limit ) {
			update_option( self::SNAPSHOT_CURSOR_OPTION, 0, false );
			$complete = true;
		} else {
			update_option( self::SNAPSHOT_CURSOR_OPTION, $offset + $processed, false );
			$complete = false;
		}
		return array( 'processed' => $processed, 'complete' => $complete );
	}

	public static function clear_schedules() {
		wp_clear_scheduled_hook( self::CRON_BACKFILL );
		wp_clear_scheduled_hook( self::CRON_SNAPSHOT );
	}
}
