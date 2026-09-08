<?php
/**
 * Deterministic inventory intelligence formulas for approved Woo Hoo Manager features.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Inventory_Intelligence {
	public static function revenue_at_risk( $forecast_demand, $projected_available_stock, $incoming_quantity, $average_realized_price ) {
		$forecast_demand = max( 0.0, (float) $forecast_demand );
		$projected_available_stock = max( 0.0, (float) $projected_available_stock );
		$incoming_quantity = max( 0.0, (float) $incoming_quantity );
		$price = max( 0.0, (float) $average_realized_price );
		$shortage = max( 0.0, $forecast_demand - $projected_available_stock - $incoming_quantity );
		return array(
			'shortage_units' => $shortage,
			'revenue_at_risk' => $shortage * $price,
		);
	}

	public static function estimated_lost_sales( $baseline_daily_velocity, $out_of_stock_duration_days, $average_realized_price ) {
		$velocity = max( 0.0, (float) $baseline_daily_velocity );
		$oos_days = max( 0.0, (float) $out_of_stock_duration_days );
		$price = max( 0.0, (float) $average_realized_price );
		$units = $velocity * $oos_days;
		return array(
			'estimated_lost_units' => $units,
			'estimated_lost_revenue' => $units * $price,
			'is_estimate' => true,
		);
	}

	public static function health_score( $components ) {
		$weights = array(
			'availability' => 0.40,
			'excess_aging' => 0.30,
			'demand' => 0.30,
		);
		$weighted_sum = 0.0;
		$used_weight = 0.0;
		$normalized_components = array();
		foreach ( $weights as $key => $weight ) {
			$value = array_key_exists( $key, $components ) ? $components[ $key ] : null;
			if ( null === $value || '' === $value ) {
				$normalized_components[ $key ] = null;
				continue;
			}
			$value = max( 0.0, min( 100.0, (float) $value ) );
			$normalized_components[ $key ] = $value;
			$weighted_sum += $value * $weight;
			$used_weight += $weight;
		}
		$score = $used_weight > 0 ? $weighted_sum / $used_weight : null;
		if ( null === $score ) { $status = 'unknown'; }
		elseif ( $score >= 80 ) { $status = 'healthy'; }
		elseif ( $score >= 60 ) { $status = 'watch'; }
		elseif ( $score >= 40 ) { $status = 'at_risk'; }
		else { $status = 'critical'; }
		return array(
			'score' => null === $score ? null : round( $score, 4 ),
			'status' => $status,
			'components' => $normalized_components,
			'weight_used' => $used_weight,
		);
	}

	public static function aging_bucket( $days_since_last_sale ) {
		$days = max( 0, (int) $days_since_last_sale );
		if ( $days <= 30 ) { return 'healthy'; }
		if ( $days <= 60 ) { return 'slow'; }
		if ( $days <= 90 ) { return 'very_slow'; }
		if ( $days <= 180 ) { return 'dead'; }
		return 'critical_dead';
	}
}
