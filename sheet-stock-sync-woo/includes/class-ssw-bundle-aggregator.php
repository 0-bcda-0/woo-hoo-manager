<?php
/**
 * Retry-safe WooCommerce co-purchase aggregation for bundle recommendations.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Bundle_Aggregator {
	const CURSOR_OPTION = 'ssw_bundle_backfill_cursor';

	public static function products_from_order( $order ) {
		$ids = array();
		if ( ! $order || ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) { return array(); }
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$product_id = absint( $product_id );
			if ( $product_id ) { $ids[ $product_id ] = true; }
		}
		$ids = array_keys( $ids );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}

	public static function process_order( $order ) {
		global $wpdb;
		if ( ! $order ) { return false; }
		$order_id = absint( $order->get_id() );
		$products = self::products_from_order( $order );
		if ( ! $order_id || ! $products ) { return false; }

		$processed = $wpdb->prefix . 'ssw_bundle_processed_orders';
		$pairs_table = $wpdb->prefix . 'ssw_bundle_pairs';
		$product_table = $wpdb->prefix . 'ssw_bundle_product_orders';
		$already = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$processed} WHERE order_id=%d", $order_id ) );
		if ( $already ) { return true; }

		$now = current_time( 'mysql' );
		$date_obj = $order->get_date_created();
		$order_date = $date_obj ? $date_obj->date( 'Y-m-d' ) : current_time( 'Y-m-d' );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$claimed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$processed} (order_id,processed_at) VALUES (%d,%s)", $order_id, $now ) );
			if ( 1 !== (int) $claimed ) { $wpdb->query( 'ROLLBACK' ); return true; }

			foreach ( $products as $product_id ) {
				$sql = "INSERT INTO {$product_table} (product_id,orders_count,updated_at) VALUES (%d,1,%s) ON DUPLICATE KEY UPDATE orders_count=orders_count+1, updated_at=VALUES(updated_at)";
				if ( false === $wpdb->query( $wpdb->prepare( $sql, $product_id, $now ) ) ) { throw new RuntimeException( 'product counter failed' ); }
			}
			foreach ( SSW_Bundle_Opportunities::product_pairs( $products ) as $pair ) {
				$sql = "INSERT INTO {$pairs_table} (product_a,product_b,pair_orders,last_order_date,updated_at) VALUES (%d,%d,1,%s,%s) ON DUPLICATE KEY UPDATE pair_orders=pair_orders+1, last_order_date=GREATEST(COALESCE(last_order_date,VALUES(last_order_date)),VALUES(last_order_date)), updated_at=VALUES(updated_at)";
				if ( false === $wpdb->query( $wpdb->prepare( $sql, $pair[0], $pair[1], $order_date, $now ) ) ) { throw new RuntimeException( 'pair counter failed' ); }
			}
			$wpdb->query( 'COMMIT' );
			return true;
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}

	public static function process_batch( $limit = 100, $page = null ) {
		$limit = max( 10, min( 250, absint( $limit ) ) );
		$page = null === $page ? max( 1, absint( get_option( self::CURSOR_OPTION, 1 ) ) ) : max( 1, absint( $page ) );
		$result = wc_get_orders( array(
			'status' => array( 'processing', 'completed' ),
			'limit' => $limit,
			'page' => $page,
			'paginate' => true,
			'orderby' => 'date',
			'order' => 'ASC',
			'return' => 'objects',
		) );
		$processed = 0;
		foreach ( $result->orders as $order ) { if ( self::process_order( $order ) ) { $processed++; } }
		$next = $page < (int) $result->max_num_pages ? $page + 1 : 1;
		update_option( self::CURSOR_OPTION, $next, false );
		return array( 'processed_orders' => $processed, 'next_page' => $next, 'complete' => $page >= (int) $result->max_num_pages );
	}
}
