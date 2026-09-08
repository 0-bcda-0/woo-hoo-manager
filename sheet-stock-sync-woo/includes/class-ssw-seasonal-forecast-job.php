<?php
/**
 * Approved #34 bounded seasonal forecast persistence.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Seasonal_Forecast_Job {
	const CURSOR_OPTION = 'ssw_seasonal_forecast_cursor';
	const MODEL_VERSION = 'seasonal_month_v1';

	public static function run_batch( $batch_size = 50 ) {
		global $wpdb;
		$limit = max( 10, min( 200, absint( $batch_size ) ) );
		$cursor = max( 0, absint( get_option( self::CURSOR_OPTION, 0 ) ) );
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$metric_date = $wpdb->get_var( "SELECT MAX(metric_date) FROM {$metrics}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $metric_date ) { return array( 'processed' => 0, 'complete' => true, 'cursor' => 0 ); }

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT product_id,weighted_velocity FROM {$metrics} WHERE metric_date=%s AND product_id>%d ORDER BY product_id ASC LIMIT %d",
			$metric_date,
			$cursor,
			$limit
		), ARRAY_A );
		$processed = 0;
		$last = $cursor;
		foreach ( $rows as $row ) {
			$product_id = absint( $row['product_id'] );
			$history = SSW_Seasonality::product_monthly_history( $product_id, 36 );
			self::persist_product( $product_id, $metric_date, (float) $row['weighted_velocity'], $history );
			$last = $product_id;
			$processed++;
		}
		$complete = $processed < $limit;
		update_option( self::CURSOR_OPTION, $complete ? 0 : $last, false );
		return array( 'processed' => $processed, 'complete' => $complete, 'cursor' => $complete ? 0 : $last );
	}

	public static function persist_product( $product_id, $generated_for_date, $base_velocity, $history ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_forecasts';
		$factors = isset( $history['factors'] ) ? (array) $history['factors'] : array();
		$confidence = isset( $history['confidence'] ) ? sanitize_key( $history['confidence'] ) : 'insufficient_data';
		foreach ( array( 7, 14, 30, 60, 90 ) as $horizon ) {
			$target = gmdate( 'Y-m-d', strtotime( $generated_for_date . ' +' . $horizon . ' days' ) );
			$month = (int) gmdate( 'n', strtotime( $target ) );
			$daily = SSW_Seasonality::forecast_daily( $base_velocity, $month, $factors );
			$qty = $daily * $horizon;
			$payload = array( 'base_velocity' => $base_velocity, 'month' => $month, 'factor' => isset( $factors[ $month ] ) ? $factors[ $month ] : 1.0, 'observations' => isset( $history['observations'] ) ? (int) $history['observations'] : 0 );
			$hash = hash( 'sha256', function_exists( 'wp_json_encode' ) ? wp_json_encode( $payload ) : json_encode( $payload ) );
			$sql = "INSERT INTO {$table} (generated_for_date,product_id,horizon_days,horizon_date,forecast_qty,model_version,inputs_hash,confidence_state,created_at) VALUES (%s,%d,%d,%s,%s,%s,%s,%s,%s) ON DUPLICATE KEY UPDATE horizon_date=VALUES(horizon_date),forecast_qty=VALUES(forecast_qty),inputs_hash=VALUES(inputs_hash),confidence_state=VALUES(confidence_state)";
			$wpdb->query( $wpdb->prepare( $sql, $generated_for_date, absint( $product_id ), $horizon, $target, (string) $qty, self::MODEL_VERSION, $hash, $confidence, current_time( 'mysql' ) ) );
		}
	}
}
