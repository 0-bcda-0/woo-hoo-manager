<?php
/**
 * Evidence-based bundle recommendation calculations.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Bundle_Opportunities {

	public static function evidence( $pair_orders, $orders_a, $orders_b, $total_orders ) {
		$pair  = max( 0.0, (float) $pair_orders );
		$a     = max( 0.0, (float) $orders_a );
		$b     = max( 0.0, (float) $orders_b );
		$total = max( 0.0, (float) $total_orders );

		return array(
			'pair_orders'   => $pair,
			'support'       => $total > 0 ? $pair / $total : 0.0,
			'attach_rate_a' => $a > 0 ? $pair / $a : 0.0,
			'attach_rate_b' => $b > 0 ? $pair / $b : 0.0,
		);
	}

	public static function score_candidate( $input ) {
		$evidence = self::evidence(
			isset( $input['pair_orders'] ) ? $input['pair_orders'] : 0,
			isset( $input['orders_a'] ) ? $input['orders_a'] : 0,
			isset( $input['orders_b'] ) ? $input['orders_b'] : 0,
			isset( $input['total_orders'] ) ? $input['total_orders'] : 0
		);

		if ( $evidence['pair_orders'] < 3 ) { return 0.0; }
		if ( $evidence['support'] < 0.02 ) { return 0.0; }
		if ( max( $evidence['attach_rate_a'], $evidence['attach_rate_b'] ) < 0.10 ) { return 0.0; }

		$slow_bonus = ! empty( $input['slow_product'] ) ? 0.10 : 0.0;
		$healthy_bonus = ! empty( $input['counterpart_healthy'] ) ? 0.10 : 0.0;
		$score = ( $evidence['support'] * 0.35 )
			+ ( max( $evidence['attach_rate_a'], $evidence['attach_rate_b'] ) * 0.25 )
			+ ( min( $evidence['attach_rate_a'], $evidence['attach_rate_b'] ) * 0.20 )
			+ $slow_bonus
			+ $healthy_bonus;

		return round( min( 1.0, max( 0.0, $score ) ), 6 );
	}

	public static function discount_ceiling( $combined_regular_price, $cost_a, $cost_b, $gross_margin_floor ) {
		if ( null === $cost_a || null === $cost_b || '' === $cost_a || '' === $cost_b ) { return null; }
		$regular = max( 0.0, (float) $combined_regular_price );
		$cost = max( 0.0, (float) $cost_a ) + max( 0.0, (float) $cost_b );
		$floor = max( 0.0, min( 0.95, (float) $gross_margin_floor ) );
		if ( $regular <= 0 || $cost <= 0 || $floor >= 1 ) { return null; }

		$minimum_bundle_price = $cost / ( 1.0 - $floor );
		$maximum_discount_value = max( 0.0, $regular - $minimum_bundle_price );
		$maximum_discount_percent = ( $maximum_discount_value / $regular ) * 100.0;

		return array(
			'minimum_bundle_price'  => $minimum_bundle_price,
			'max_discount_value'    => $maximum_discount_value,
			'max_discount_percent'  => $maximum_discount_percent,
			'gross_margin_floor'    => $floor,
		);
	}
}
