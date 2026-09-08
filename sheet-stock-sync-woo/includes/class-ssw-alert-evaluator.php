<?php
/**
 * Bounded evaluator for approved Smart Alerts.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Alert_Evaluator {
	const PRODUCT_CURSOR_OPTION = 'ssw_alert_product_cursor';

	public static function evaluate_batch( $batch_size = 100 ) {
		global $wpdb;
		$limit = max( 10, min( 250, absint( $batch_size ) ) );
		$last_id = max( 0, absint( get_option( self::PRODUCT_CURSOR_OPTION, 0 ) ) );
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$metric_date = $wpdb->get_var( "SELECT MAX(metric_date) FROM {$metrics}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$processed = 0;
		$cursor = $last_id;

		if ( $metric_date ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id,available_quantity,days_of_stock,last_sale_date FROM {$metrics} WHERE metric_date=%s AND product_id>%d ORDER BY product_id ASC LIMIT %d", $metric_date, $last_id, $limit ), ARRAY_A );
			foreach ( $rows as $row ) {
				$product_id = absint( $row['product_id'] );
				$lead = (int) $wpdb->get_var( $wpdb->prepare( "SELECT lead_time_days FROM {$relations} WHERE product_id=%d AND is_preferred=1 LIMIT 1", $product_id ) );
				$incoming = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')", $product_id ) );
				$days_since_sale = $row['last_sale_date'] ? max( 0, (int) floor( ( strtotime( $metric_date ) - strtotime( $row['last_sale_date'] ) ) / DAY_IN_SECONDS ) ) : 9999;
				$alerts = SSW_Alerts::evaluate_product( array(
					'product_id' => $product_id,
					'available_quantity' => (float) $row['available_quantity'],
					'days_of_stock' => null === $row['days_of_stock'] ? null : (float) $row['days_of_stock'],
					'lead_time_days' => $lead,
					'incoming_quantity' => $incoming,
					'health_score' => null,
					'days_since_last_sale' => $days_since_sale,
					'bundle_opportunity' => false,
				) );
				foreach ( $alerts as $alert ) { SSW_Alerts::persist( $alert ); }
				$cursor = $product_id;
				$processed++;
			}
			$complete = $processed < $limit;
			update_option( self::PRODUCT_CURSOR_OPTION, $complete ? 0 : $cursor, false );
		} else {
			$complete = true;
		}

		self::evaluate_late_purchase_orders();
		return array( 'processed' => $processed, 'complete' => $complete, 'cursor' => $complete ? 0 : $cursor );
	}

	public static function evaluate_late_purchase_orders() {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_purchase_orders';
		$today = current_time( 'Y-m-d' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,status,expected_at FROM {$table} WHERE status IN ('ordered','shipped','partially_received') AND expected_at IS NOT NULL AND DATE(expected_at)<%s ORDER BY expected_at ASC LIMIT 100", $today ), ARRAY_A );
		$count = 0;
		foreach ( $rows as $row ) {
			$alerts = SSW_Alerts::evaluate_purchase_order( array( 'purchase_order_id' => $row['id'], 'status' => $row['status'], 'expected_arrival' => $row['expected_at'], 'today' => $today ) );
			foreach ( $alerts as $alert ) { if ( SSW_Alerts::persist( $alert ) ) { $count++; } }
		}
		return $count;
	}
}
