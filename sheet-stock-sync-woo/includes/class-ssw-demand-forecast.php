<?php
/**
 * Deterministic demand forecasting calculations.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Demand_Forecast {

	public static function weighted_velocity( $velocities ) {
		$weights = array( '30d' => 0.60, '90d' => 0.30, '365d' => 0.10 );
		$weighted = 0.0;
		$weight_sum = 0.0;
		foreach ( $weights as $key => $weight ) {
			if ( ! array_key_exists( $key, $velocities ) || null === $velocities[ $key ] ) { continue; }
			$value = max( 0.0, (float) $velocities[ $key ] );
			$weighted += $value * $weight;
			$weight_sum += $weight;
		}
		return $weight_sum > 0 ? $weighted / $weight_sum : 0.0;
	}

	public static function days_of_stock( $available_quantity, $daily_velocity ) {
		$velocity = (float) $daily_velocity;
		if ( $velocity <= 0 ) { return null; }
		return max( 0.0, (float) $available_quantity ) / $velocity;
	}

	public static function projected_stockout_date( $as_of_date, $days_of_stock ) {
		if ( null === $days_of_stock ) { return null; }
		try {
			$date = new DateTimeImmutable( (string) $as_of_date );
		} catch ( Exception $e ) {
			return null;
		}
		$days = max( 0, (int) ceil( (float) $days_of_stock ) );
		return $date->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
	}

	public static function demand_variability( $weekly_values ) {
		$values = array_values( array_map( 'floatval', (array) $weekly_values ) );
		$count = count( $values );
		if ( 0 === $count ) { return array( 'mean' => 0.0, 'stddev' => 0.0, 'cv' => null ); }
		$mean = array_sum( $values ) / $count;
		$variance = 0.0;
		foreach ( $values as $value ) { $variance += ( $value - $mean ) ** 2; }
		$variance /= $count;
		$stddev = sqrt( $variance );
		$cv = $mean > 0 ? $stddev / $mean : null;
		return array( 'mean' => $mean, 'stddev' => $stddev, 'cv' => $cv );
	}

	public static function confidence_state( $history_days, $active_sales_days ) {
		$history_days = max( 0, (int) $history_days );
		$active_sales_days = max( 0, (int) $active_sales_days );
		if ( $history_days < 30 || $active_sales_days < 5 ) { return 'insufficient_data'; }
		if ( $history_days < 90 || $active_sales_days < 12 ) { return 'low_confidence'; }
		if ( $history_days >= 270 && $active_sales_days >= 50 ) { return 'high_confidence'; }
		return 'normal';
	}

	public static function forecast_quantity( $daily_velocity, $horizon_days ) {
		$velocity = max( 0.0, (float) $daily_velocity );
		$days = max( 0, (int) $horizon_days );
		return $velocity * $days;
	}
}
