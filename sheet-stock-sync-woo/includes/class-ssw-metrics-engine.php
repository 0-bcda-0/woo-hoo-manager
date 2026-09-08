<?php
/**
 * Product metrics and deterministic forecast persistence engine.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Metrics_Engine {

	const MODEL_VERSION = 'weighted_velocity_v1';

	private static function metrics_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_product_metrics_daily';
	}

	private static function forecasts_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_forecasts';
	}

	private static function sales_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_sales_daily';
	}

	private static function json_encode_stable( $value ) {
		return function_exists( 'wp_json_encode' ) ? wp_json_encode( $value ) : json_encode( $value );
	}

	public static function calculate_windows( $daily_rows, $as_of_date, $history_days ) {
		$history_days = max( 0, (int) $history_days );
		$windows = array( 7, 30, 90, 365 );
		$result = array();
		try { $as_of = new DateTimeImmutable( $as_of_date ); } catch ( Exception $e ) { $as_of = new DateTimeImmutable(); }
		foreach ( $windows as $days ) {
			$key = $days . 'd';
			if ( $history_days < $days ) {
				$result[ 'units_' . $key ] = null;
				$result[ 'velocity_' . $key ] = null;
				continue;
			}
			$start = $as_of->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
			$end = $as_of->format( 'Y-m-d' );
			$units = 0.0;
			foreach ( $daily_rows as $row ) {
				$date = isset( $row['date'] ) ? (string) $row['date'] : ( isset( $row['fact_date'] ) ? (string) $row['fact_date'] : '' );
				if ( $date >= $start && $date <= $end ) { $units += (float) ( isset( $row['units'] ) ? $row['units'] : 0 ); }
			}
			$result[ 'units_' . $key ] = $units;
			$result[ 'velocity_' . $key ] = $units / $days;
		}
		return $result;
	}

	public static function weekly_series( $daily_rows, $as_of_date, $weeks = 12 ) {
		$weeks = max( 1, (int) $weeks );
		try { $as_of = new DateTimeImmutable( $as_of_date ); } catch ( Exception $e ) { $as_of = new DateTimeImmutable(); }
		$series = array();
		for ( $bucket = $weeks - 1; $bucket >= 0; $bucket-- ) {
			$end = $as_of->modify( '-' . ( $bucket * 7 ) . ' days' );
			$start = $end->modify( '-6 days' );
			$total = 0.0;
			foreach ( $daily_rows as $row ) {
				$date = isset( $row['date'] ) ? (string) $row['date'] : ( isset( $row['fact_date'] ) ? (string) $row['fact_date'] : '' );
				if ( $date >= $start->format( 'Y-m-d' ) && $date <= $end->format( 'Y-m-d' ) ) { $total += (float) ( isset( $row['units'] ) ? $row['units'] : 0 ); }
			}
			$series[] = $total;
		}
		return $series;
	}

	public static function build_metric( $input ) {
		$as_of_date = isset( $input['as_of_date'] ) ? (string) $input['as_of_date'] : gmdate( 'Y-m-d' );
		$history_days = max( 0, (int) ( isset( $input['history_days'] ) ? $input['history_days'] : 0 ) );
		$active_sales_days = max( 0, (int) ( isset( $input['active_sales_days'] ) ? $input['active_sales_days'] : 0 ) );
		$rows = isset( $input['daily_rows'] ) ? (array) $input['daily_rows'] : array();
		$windows = self::calculate_windows( $rows, $as_of_date, $history_days );
		$weighted = SSW_Demand_Forecast::weighted_velocity( array(
			'30d' => $windows['velocity_30d'],
			'90d' => $windows['velocity_90d'],
			'365d' => $windows['velocity_365d'],
		) );
		$available = (float) ( isset( $input['available_quantity'] ) ? $input['available_quantity'] : 0 );
		$cover = SSW_Demand_Forecast::days_of_stock( $available, $weighted );
		$weekly = self::weekly_series( $rows, $as_of_date, min( 12, max( 1, (int) floor( max( 7, $history_days ) / 7 ) ) ) );
		$variability = SSW_Demand_Forecast::demand_variability( $weekly );

		$revenue = 0.0;
		$units = 0.0;
		foreach ( $rows as $row ) {
			$row_units = (float) ( isset( $row['units'] ) ? $row['units'] : 0 );
			$row_revenue = (float) ( isset( $row['revenue'] ) ? $row['revenue'] : 0 );
			if ( $row_units > 0 ) { $units += $row_units; $revenue += max( 0, $row_revenue ); }
		}
		$avg_price = $units > 0 ? $revenue / $units : null;

		return array_merge( $windows, array(
			'metric_date' => $as_of_date,
			'weighted_velocity' => $weighted,
			'available_quantity' => $available,
			'days_of_stock' => $cover,
			'projected_stockout_date' => SSW_Demand_Forecast::projected_stockout_date( $as_of_date, $cover ),
			'demand_mean' => $variability['mean'],
			'demand_stddev' => $variability['stddev'],
			'demand_cv' => $variability['cv'],
			'avg_realized_price' => $avg_price,
			'last_sale_date' => isset( $input['last_sale_date'] ) ? $input['last_sale_date'] : null,
			'history_days' => $history_days,
			'active_sales_days' => $active_sales_days,
			'confidence_state' => SSW_Demand_Forecast::confidence_state( $history_days, $active_sales_days ),
		) );
	}

	public static function build_forecasts( $product_id, $metric, $horizons = array( 7, 14, 30, 60, 90 ) ) {
		$rows = array();
		foreach ( $horizons as $horizon ) {
			$horizon = max( 1, (int) $horizon );
			$payload = array(
				'product_id' => (int) $product_id,
				'generated_for_date' => $metric['metric_date'],
				'weighted_velocity' => (float) $metric['weighted_velocity'],
				'horizon_days' => $horizon,
				'confidence_state' => $metric['confidence_state'],
				'model_version' => self::MODEL_VERSION,
			);
			$rows[] = array(
				'generated_for_date' => $metric['metric_date'],
				'product_id' => (int) $product_id,
				'horizon_days' => $horizon,
				'horizon_date' => SSW_Demand_Forecast::projected_stockout_date( $metric['metric_date'], $horizon ),
				'forecast_qty' => SSW_Demand_Forecast::forecast_quantity( $metric['weighted_velocity'], $horizon ),
				'model_version' => self::MODEL_VERSION,
				'inputs_hash' => hash( 'sha256', self::json_encode_stable( $payload ) ),
				'confidence_state' => $metric['confidence_state'],
			);
		}
		return $rows;
	}

	public static function load_daily_rows( $product_id, $as_of_date, $days = 365 ) {
		global $wpdb;
		$table = self::sales_table();
		$days = max( 1, min( 730, (int) $days ) );
		try { $as_of = new DateTimeImmutable( $as_of_date ); } catch ( Exception $e ) { $as_of = new DateTimeImmutable(); }
		$start = $as_of->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
		$sql = "SELECT fact_date AS date, units, revenue FROM {$table} WHERE product_id = %d AND fact_date BETWEEN %s AND %s ORDER BY fact_date ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, (int) $product_id, $start, $as_of->format( 'Y-m-d' ) ), ARRAY_A );
	}

	public static function calculate_product( $product_id, $as_of_date = null ) {
		global $wpdb;
		$product_id = absint( $product_id );
		$as_of_date = $as_of_date ? (string) $as_of_date : current_time( 'Y-m-d' );
		$rows = self::load_daily_rows( $product_id, $as_of_date, 365 );
		$sales_table = self::sales_table();
		$first = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(fact_date) FROM {$sales_table} WHERE product_id = %d AND fact_date <= %s", $product_id, $as_of_date ) );
		$active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$sales_table} WHERE product_id = %d AND fact_date <= %s AND units > 0", $product_id, $as_of_date ) );
		$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(fact_date) FROM {$sales_table} WHERE product_id = %d AND fact_date <= %s AND units > 0", $product_id, $as_of_date ) );
		$history_days = 0;
		if ( $first ) {
			$day_seconds = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
			$history_days = (int) floor( ( strtotime( $as_of_date ) - strtotime( $first ) ) / $day_seconds ) + 1;
		}
		$balances = SSW_Locations::product_balances( $product_id );
		$available = SSW_Locations::aggregate_sellable( $balances );
		$metric = self::build_metric( array(
			'as_of_date' => $as_of_date,
			'daily_rows' => $rows,
			'available_quantity' => $available,
			'history_days' => $history_days,
			'active_sales_days' => $active,
			'last_sale_date' => $last,
		) );
		self::persist_metric( $product_id, $metric );
		self::persist_forecasts( self::build_forecasts( $product_id, $metric ) );
		return $metric;
	}

	public static function persist_metric( $product_id, $metric ) {
		global $wpdb;
		$table = self::metrics_table();
		$now = current_time( 'mysql' );
		$fields = array( 'units_7d','units_30d','units_90d','units_365d','velocity_7d','velocity_30d','velocity_90d','velocity_365d','weighted_velocity','available_quantity','days_of_stock','projected_stockout_date','demand_mean','demand_stddev','demand_cv','avg_realized_price','last_sale_date','history_days','active_sales_days','confidence_state' );
		$columns = array( 'metric_date', 'product_id' );
		$values = array( $metric['metric_date'], (int) $product_id );
		$placeholders = array( '%s', '%d' );
		foreach ( $fields as $field ) { $columns[] = $field; $values[] = array_key_exists( $field, $metric ) ? $metric[ $field ] : null; $placeholders[] = '%s'; }
		$columns[] = 'created_at'; $values[] = $now; $placeholders[] = '%s';
		$columns[] = 'updated_at'; $values[] = $now; $placeholders[] = '%s';
		$updates = array();
		foreach ( $fields as $field ) { $updates[] = $field . '=VALUES(' . $field . ')'; }
		$updates[] = 'updated_at=VALUES(updated_at)';
		$sql = "INSERT INTO {$table} (" . implode( ',', $columns ) . ') VALUES (' . implode( ',', $placeholders ) . ') ON DUPLICATE KEY UPDATE ' . implode( ',', $updates );
		return false !== $wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	public static function persist_forecasts( $forecasts ) {
		global $wpdb;
		$table = self::forecasts_table();
		$now = current_time( 'mysql' );
		foreach ( $forecasts as $row ) {
			$sql = "INSERT INTO {$table} (generated_for_date,product_id,horizon_days,horizon_date,forecast_qty,model_version,inputs_hash,confidence_state,created_at) VALUES (%s,%d,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE horizon_date=VALUES(horizon_date), forecast_qty=VALUES(forecast_qty), inputs_hash=VALUES(inputs_hash), confidence_state=VALUES(confidence_state)";
			if ( false === $wpdb->query( $wpdb->prepare( $sql, $row['generated_for_date'], $row['product_id'], $row['horizon_days'], $row['horizon_date'], (string) $row['forecast_qty'], $row['model_version'], $row['inputs_hash'], $row['confidence_state'], $now ) ) ) { return false; }
		}
		return true;
	}
}
