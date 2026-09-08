<?php
/**
 * Data collection, persistence and delivery for approved #37 Weekly Executive Report.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Weekly_Report_Service {
	const REPORT_TYPE = 'weekly_executive';

	public static function generate( $period_end = null, $persist = true ) {
		$end = self::normalize_date( $period_end ? $period_end : current_time( 'Y-m-d' ) );
		if ( ! $end ) { return false; }
		$current_start = gmdate( 'Y-m-d', strtotime( $end . ' -6 days' ) );
		$previous_end = gmdate( 'Y-m-d', strtotime( $current_start . ' -1 day' ) );
		$previous_start = gmdate( 'Y-m-d', strtotime( $previous_end . ' -6 days' ) );

		$current_sales = self::sales_summary( $current_start, $end );
		$previous_sales = self::sales_summary( $previous_start, $previous_end );
		$inventory = self::inventory_summary( $current_start, $end );
		$purchasing = self::purchasing_summary( $end );
		$operations = self::operations_summary();

		$current = array_merge( $current_sales, $inventory, $purchasing, $operations, array(
			'period_start' => $current_start,
			'period_end' => $end,
		) );
		$previous = array_merge( $previous_sales, self::health_summary_at( $previous_end ) );
		$payload = SSW_Weekly_Report::build_payload( $current, $previous );
		$payload['data_quality'] = array(
			'metric_date' => $inventory['metric_date'],
			'products_analysed' => $inventory['products_analysed'],
			'note' => $inventory['products_analysed'] > 0 ? 'deterministic' : 'insufficient_data',
		);
		if ( $persist ) { self::persist_snapshot( $payload ); }
		return $payload;
	}

	public static function persist_snapshot( $payload ) {
		global $wpdb;
		if ( empty( $payload['period']['start'] ) || empty( $payload['period']['end'] ) ) { return false; }
		$table = $wpdb->prefix . 'ssw_reports';
		$now = current_time( 'mysql' );
		$json = wp_json_encode( $payload );
		$sql = "INSERT INTO {$table} (report_type,period_start,period_end,payload_json,created_at,updated_at) VALUES (%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),updated_at=VALUES(updated_at)";
		return false !== $wpdb->query( $wpdb->prepare( $sql, self::REPORT_TYPE, $payload['period']['start'], $payload['period']['end'], $json, $now, $now ) );
	}

	public static function latest_snapshot() {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_reports';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_type=%s ORDER BY period_end DESC,id DESC LIMIT 1", self::REPORT_TYPE ), ARRAY_A );
		if ( ! $row ) { return null; }
		$payload = json_decode( $row['payload_json'], true );
		return is_array( $payload ) ? $payload : null;
	}

	private static function sales_summary( $start, $end ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_sales_daily';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(revenue),0) revenue,COALESCE(SUM(units),0) units,COALESCE(SUM(orders_count),0) orders FROM {$table} WHERE fact_date BETWEEN %s AND %s", $start, $end ), ARRAY_A );
		return array( 'revenue' => (float) $row['revenue'], 'units' => (float) $row['units'], 'orders' => (int) $row['orders'] );
	}

	private static function inventory_summary( $start, $end ) {
		global $wpdb;
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$forecasts = $wpdb->prefix . 'ssw_forecasts';
		$metric_date = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(metric_date) FROM {$metrics} WHERE metric_date<=%s", $end ) );
		if ( ! $metric_date ) {
			return array( 'revenue_at_risk' => 0.0, 'estimated_lost_sales' => 0.0, 'stockouts' => 0, 'dead_slow_count' => 0, 'health_average' => null, 'metric_date' => null, 'products_analysed' => 0 );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$metrics} WHERE metric_date=%s ORDER BY product_id ASC LIMIT 1000", $metric_date ), ARRAY_A );
		$risk_total = 0.0; $lost_total = 0.0; $stockouts = 0; $dead_slow = 0; $health_total = 0.0; $health_count = 0;
		foreach ( $rows as $metric ) {
			$product_id = absint( $metric['product_id'] );
			$relation = $wpdb->get_row( $wpdb->prepare( "SELECT cost,lead_time_days FROM {$relations} WHERE product_id=%d AND is_preferred=1 LIMIT 1", $product_id ), ARRAY_A );
			$incoming = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')", $product_id ) );
			$forecast = $wpdb->get_var( $wpdb->prepare( "SELECT forecast_qty FROM {$forecasts} WHERE product_id=%d AND horizon_days=30 AND generated_for_date<=%s ORDER BY generated_for_date DESC LIMIT 1", $product_id, $metric_date ) );
			$forecast = null === $forecast ? ( (float) $metric['weighted_velocity'] * 30.0 ) : (float) $forecast;
			$risk = SSW_Inventory_Intelligence::revenue_at_risk( $forecast, (float) $metric['available_quantity'], $incoming, (float) $metric['avg_realized_price'] );
			$risk_total += (float) $risk['revenue_at_risk'];

			$oos_days = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT snapshot_date,SUM(quantity) qty FROM {$snapshots} WHERE product_id=%d AND snapshot_date BETWEEN %s AND %s GROUP BY snapshot_date HAVING qty<=0) x", $product_id, $start, $end ) );
			$lost = SSW_Inventory_Intelligence::estimated_lost_sales( (float) $metric['weighted_velocity'], $oos_days, (float) $metric['avg_realized_price'] );
			$lost_total += (float) $lost['estimated_lost_revenue'];
			if ( (float) $metric['available_quantity'] <= 0 ) { $stockouts++; }
			$days_since_sale = $metric['last_sale_date'] ? max( 0, (int) floor( ( strtotime( $metric_date ) - strtotime( $metric['last_sale_date'] ) ) / DAY_IN_SECONDS ) ) : 9999;
			if ( $days_since_sale >= 91 && (float) $metric['available_quantity'] > 0 ) { $dead_slow++; }

			$availability = self::availability_score( $metric );
			$aging = self::aging_score( $days_since_sale );
			$demand = self::demand_score( $metric );
			$margin = null;
			if ( $relation && null !== $relation['cost'] ) {
				$cost = (float) $relation['cost'];
				$sales90 = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(units),0) units,COALESCE(SUM(revenue),0) revenue FROM {$wpdb->prefix}ssw_sales_daily WHERE product_id=%d AND fact_date BETWEEN DATE_SUB(%s,INTERVAL 89 DAY) AND %s", $product_id, $metric_date, $metric_date ), ARRAY_A );
				$gross_margin = (float) $sales90['revenue'] - ( (float) $sales90['units'] * $cost );
				$avg_cost = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(day_cost) FROM (SELECT snapshot_date,SUM(quantity*COALESCE(unit_cost,%s)) day_cost FROM {$snapshots} WHERE product_id=%d AND snapshot_date BETWEEN DATE_SUB(%s,INTERVAL 89 DAY) AND %s GROUP BY snapshot_date) inv", (string) $cost, $product_id, $metric_date, $metric_date ) );
				$gmroi = null === $avg_cost ? null : SSW_Inventory_Intelligence::gmroi( $gross_margin, (float) $avg_cost );
				$margin = null === $gmroi ? null : min( 100.0, max( 0.0, $gmroi * 50.0 ) );
			}
			$health = SSW_Inventory_Intelligence::health_score( array( 'availability' => $availability, 'excess_aging' => $aging, 'demand' => $demand, 'margin' => $margin ) );
			if ( null !== $health['score'] ) { $health_total += (float) $health['score']; $health_count++; }
		}
		return array(
			'revenue_at_risk' => round( $risk_total, 4 ), 'estimated_lost_sales' => round( $lost_total, 4 ), 'stockouts' => $stockouts,
			'dead_slow_count' => $dead_slow, 'health_average' => $health_count ? round( $health_total / $health_count, 2 ) : null,
			'metric_date' => $metric_date, 'products_analysed' => count( $rows ),
		);
	}

	private static function health_summary_at( $end ) {
		$summary = self::inventory_summary( gmdate( 'Y-m-d', strtotime( $end . ' -6 days' ) ), $end );
		return array( 'health_average' => $summary['health_average'] );
	}

	private static function purchasing_summary( $end ) {
		global $wpdb;
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$po} WHERE status IN ('approved','ordered','shipped','partially_received')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$incoming = (float) $wpdb->get_var( "SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE p.status IN ('approved','ordered','shipped','partially_received')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$plan_rows = self::planning_rows( $end );
		$cash = array();
		foreach ( $plan_rows as $row ) {
			if ( null === $row['expected_cash'] ) { continue; }
			$currency = $row['currency'];
			$cash[ $currency ] = isset( $cash[ $currency ] ) ? $cash[ $currency ] + $row['expected_cash'] : $row['expected_cash'];
		}
		return array( 'open_po_count' => $open, 'incoming_units' => $incoming, 'purchasing_cash' => $cash );
	}

	private static function planning_rows( $end ) {
		global $wpdb;
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$metric_date = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(metric_date) FROM {$metrics} WHERE metric_date<=%s", $end ) );
		if ( ! $metric_date ) { return array(); }
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.product_id,m.weighted_velocity,m.available_quantity,m.confidence_state,r.supplier_id,r.cost,r.currency,r.moq,r.units_per_box,r.lead_time_days FROM {$metrics} m INNER JOIN {$relations} r ON r.product_id=m.product_id AND r.is_preferred=1 WHERE m.metric_date=%s AND m.weighted_velocity>0 LIMIT 1000", $metric_date ), ARRAY_A );
		$result = array();
		foreach ( $rows as $row ) {
			$incoming = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')", absint( $row['product_id'] ) ) );
			$plan = SSW_Purchasing_Planner::plan_product( array( 'as_of_date' => $end, 'daily_velocity' => $row['weighted_velocity'], 'on_hand' => $row['available_quantity'], 'incoming' => $incoming, 'lead_time_days' => $row['lead_time_days'], 'safety_stock_days' => 5, 'moq' => $row['moq'], 'pack_size' => $row['units_per_box'], 'unit_cost' => $row['cost'], 'currency' => $row['currency'], 'confidence' => $row['confidence_state'], 'horizon_days' => 90 ) );
			if ( $plan['suggested_order_date'] && $plan['suggested_order_date'] <= gmdate( 'Y-m-d', strtotime( $end . ' +90 days' ) ) ) { $result[] = $plan; }
		}
		return $result;
	}

	private static function operations_summary() {
		global $wpdb;
		$alerts = $wpdb->prefix . 'ssw_alerts';
		$bundles = $wpdb->prefix . 'ssw_bundle_pairs';
		$critical = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$alerts} WHERE state='open' AND severity='critical'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$warning = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$alerts} WHERE state='open' AND severity='warning'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$bundle_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$alerts} WHERE state='open' AND type='bundle_opportunity'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'critical_alerts' => $critical, 'warning_alerts' => $warning, 'bundle_opportunities' => $bundle_count );
	}

	private static function normalize_date( $date ) {
		$date = substr( (string) $date, 0, 10 );
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date ? $date : null;
	}
	private static function availability_score( $m ) {
		if ( (float) $m['available_quantity'] <= 0 ) { return 0.0; }
		if ( null === $m['days_of_stock'] ) { return 70.0; }
		$d = (float) $m['days_of_stock']; return $d < 7 ? 20.0 : ( $d < 14 ? 45.0 : ( $d < 30 ? 75.0 : 100.0 ) );
	}
	private static function aging_score( $days ) { return $days <= 30 ? 100.0 : ( $days <= 60 ? 80.0 : ( $days <= 90 ? 60.0 : ( $days <= 180 ? 35.0 : 10.0 ) ) ); }
	private static function demand_score( $m ) { if ( 'insufficient_data' === $m['confidence_state'] ) { return 40.0; } if ( (float) $m['weighted_velocity'] <= 0 ) { return 20.0; } return 'low_confidence' === $m['confidence_state'] ? 70.0 : 100.0; }
}
