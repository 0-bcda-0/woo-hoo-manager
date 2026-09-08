<?php
/**
 * Historical sales aggregation and inventory snapshot service.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Sales_Aggregator {

	const BACKFILL_OPTION = 'ssw_sales_backfill_cursor';

	private static function sales_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_sales_daily';
	}

	private static function snapshots_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_inventory_snapshots_daily';
	}

	public static function eligible_order_status( $status ) {
		$status = strtolower( trim( (string) $status ) );
		$status = 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
		return in_array( $status, array( 'processing', 'completed' ), true );
	}

	private static function decimal_to_scaled_int( $value, $scale = 4 ) {
		$value = trim( (string) $value );
		if ( '' === $value ) { return 0; }
		$negative = false;
		if ( '-' === substr( $value, 0, 1 ) ) { $negative = true; $value = substr( $value, 1 ); }
		$value = str_replace( ',', '.', $value );
		$parts = explode( '.', $value, 2 );
		$whole = preg_replace( '/\D/', '', $parts[0] );
		$fraction = isset( $parts[1] ) ? preg_replace( '/\D/', '', $parts[1] ) : '';
		$whole = '' === $whole ? '0' : $whole;
		$fraction = substr( str_pad( $fraction, $scale, '0' ), 0, $scale );
		$result = ( (int) $whole * ( 10 ** $scale ) ) + (int) $fraction;
		return $negative ? -$result : $result;
	}

	private static function scaled_int_to_decimal( $value, $scale = 4, $display_scale = null ) {
		$display_scale = null === $display_scale ? $scale : (int) $display_scale;
		$negative = $value < 0;
		$value = abs( (int) $value );
		$factor = 10 ** $scale;
		$whole = intdiv( $value, $factor );
		$fraction = str_pad( (string) ( $value % $factor ), $scale, '0', STR_PAD_LEFT );
		$fraction = substr( $fraction, 0, $display_scale );
		return ( $negative ? '-' : '' ) . $whole . ( $display_scale > 0 ? '.' . $fraction : '' );
	}

	public static function aggregate_facts( $rows ) {
		$facts = array();
		foreach ( $rows as $row ) {
			$date = isset( $row['date'] ) ? substr( (string) $row['date'], 0, 10 ) : '';
			$product_id = absint( ! empty( $row['variation_id'] ) ? $row['variation_id'] : ( isset( $row['product_id'] ) ? $row['product_id'] : 0 ) );
			if ( ! $product_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) { continue; }
			$key = $date . ':' . $product_id;
			if ( ! isset( $facts[ $key ] ) ) {
				$facts[ $key ] = array(
					'date' => $date,
					'product_id' => $product_id,
					'units' => 0.0,
					'revenue_scaled' => 0,
					'orders_count' => 0,
				);
			}
			$facts[ $key ]['units'] += (float) ( isset( $row['units'] ) ? $row['units'] : 0 );
			$facts[ $key ]['revenue_scaled'] += self::decimal_to_scaled_int( isset( $row['revenue'] ) ? $row['revenue'] : '0', 4 );
			$facts[ $key ]['orders_count'] += ! empty( $row['order_marker'] ) ? 1 : 0;
		}
		foreach ( $facts as $key => $fact ) {
			$facts[ $key ]['revenue'] = self::scaled_int_to_decimal( $fact['revenue_scaled'], 4, 2 );
			unset( $facts[ $key ]['revenue_scaled'] );
		}
		return $facts;
	}

	public static function normalize_inventory_snapshot( $data ) {
		return array(
			'date'         => isset( $data['date'] ) ? substr( (string) $data['date'], 0, 10 ) : gmdate( 'Y-m-d' ),
			'product_id'   => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'location_id'  => absint( isset( $data['location_id'] ) ? $data['location_id'] : 0 ),
			'quantity'     => (float) ( isset( $data['quantity'] ) ? $data['quantity'] : 0 ),
			'unit_cost'    => isset( $data['unit_cost'] ) && '' !== (string) $data['unit_cost'] ? self::scaled_int_to_decimal( self::decimal_to_scaled_int( $data['unit_cost'], 4 ), 4, 4 ) : null,
			'retail_price' => isset( $data['retail_price'] ) && '' !== (string) $data['retail_price'] ? self::scaled_int_to_decimal( self::decimal_to_scaled_int( $data['retail_price'], 4 ), 4, 4 ) : null,
		);
	}

	public static function persist_facts( $facts ) {
		global $wpdb;
		$table = self::sales_table();
		$now = current_time( 'mysql' );
		foreach ( $facts as $fact ) {
			$sql = "INSERT INTO {$table} (fact_date, product_id, units, revenue, orders_count, updated_at) VALUES (%s,%d,%s,%s,%d,%s) ON DUPLICATE KEY UPDATE units=VALUES(units), revenue=VALUES(revenue), orders_count=VALUES(orders_count), updated_at=VALUES(updated_at)";
			$result = $wpdb->query( $wpdb->prepare(
				$sql,
				$fact['date'],
				absint( $fact['product_id'] ),
				(string) $fact['units'],
				(string) $fact['revenue'],
				isset( $fact['orders_count'] ) ? absint( $fact['orders_count'] ) : 0,
				$now
			) );
			if ( false === $result ) { return false; }
		}
		return true;
	}

	public static function persist_inventory_snapshot( $data ) {
		global $wpdb;
		$row = self::normalize_inventory_snapshot( $data );
		if ( ! $row['product_id'] || ! $row['location_id'] ) { return false; }
		$table = self::snapshots_table();
		$now = current_time( 'mysql' );
		$sql = "INSERT INTO {$table} (snapshot_date,product_id,location_id,quantity,unit_cost,retail_price,created_at,updated_at) VALUES (%s,%d,%d,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), unit_cost=VALUES(unit_cost), retail_price=VALUES(retail_price), updated_at=VALUES(updated_at)";
		return false !== $wpdb->query( $wpdb->prepare(
			$sql,
			$row['date'],
			$row['product_id'],
			$row['location_id'],
			(string) $row['quantity'],
			$row['unit_cost'],
			$row['retail_price'],
			$now,
			$now
		) );
	}

	public static function rows_from_order( $order ) {
		$rows = array();
		if ( ! $order || ! self::eligible_order_status( $order->get_status() ) ) { return $rows; }
		$date = $order->get_date_created();
		$date = $date ? $date->date( 'Y-m-d' ) : current_time( 'Y-m-d' );
		$marker = 'order-' . $order->get_id();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product_id = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			$rows[] = array(
				'date' => $date,
				'product_id' => $product_id,
				'variation_id' => $variation_id,
				'units' => (float) $item->get_quantity(),
				'revenue' => wc_format_decimal( $item->get_total(), 4 ),
				'order_marker' => $marker,
			);
		}
		foreach ( $order->get_refunds() as $refund ) {
			foreach ( $refund->get_items( 'line_item' ) as $item ) {
				$product_id = $item->get_product_id();
				$variation_id = $item->get_variation_id();
				$rows[] = array(
					'date' => $date,
					'product_id' => $product_id,
					'variation_id' => $variation_id,
					'units' => -abs( (float) $item->get_quantity() ),
					'revenue' => '-' . ltrim( wc_format_decimal( abs( (float) $item->get_total() ), 4 ), '-' ),
				);
			}
		}
		return $rows;
	}

	public static function process_order_batch( $limit = 100, $page = null ) {
		$limit = max( 10, min( 250, absint( $limit ) ) );
		$page = null === $page ? max( 1, absint( get_option( self::BACKFILL_OPTION, 1 ) ) ) : max( 1, absint( $page ) );
		$result = wc_get_orders( array(
			'status' => array( 'processing', 'completed' ),
			'limit' => $limit,
			'page' => $page,
			'paginate' => true,
			'orderby' => 'date',
			'order' => 'ASC',
			'return' => 'objects',
		) );
		$rows = array();
		foreach ( $result->orders as $order ) { $rows = array_merge( $rows, self::rows_from_order( $order ) ); }
		$facts = self::aggregate_facts( $rows );
		self::persist_facts( $facts );
		$next = $page < (int) $result->max_num_pages ? $page + 1 : 1;
		update_option( self::BACKFILL_OPTION, $next, false );
		return array( 'processed_orders' => count( $result->orders ), 'facts' => count( $facts ), 'next_page' => $next, 'complete' => $page >= (int) $result->max_num_pages );
	}
}
