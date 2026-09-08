<?php
/**
 * Deterministic Weekly Executive Report for approved scope item #37.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Weekly_Report {
	public static function percent_change( $current, $previous ) {
		$current = (float) $current;
		$previous = (float) $previous;
		if ( 0.0 === $previous ) { return null; }
		return round( ( ( $current - $previous ) / abs( $previous ) ) * 100.0, 2 );
	}

	public static function build_payload( $current, $previous = array() ) {
		$current = (array) $current;
		$previous = (array) $previous;
		$get = function( $array, $key, $default = 0 ) { return array_key_exists( $key, $array ) ? $array[ $key ] : $default; };
		$current_revenue = (float) $get( $current, 'revenue' );
		$current_orders = (float) $get( $current, 'orders' );
		$current_units = (float) $get( $current, 'units' );
		$current_health = array_key_exists( 'health_average', $current ) && null !== $current['health_average'] ? (float) $current['health_average'] : null;
		$previous_health = array_key_exists( 'health_average', $previous ) && null !== $previous['health_average'] ? (float) $previous['health_average'] : null;

		return array(
			'period' => array(
				'start' => (string) $get( $current, 'period_start', '' ),
				'end' => (string) $get( $current, 'period_end', '' ),
			),
			'sales' => array(
				'revenue' => $current_revenue,
				'orders' => $current_orders,
				'units' => $current_units,
			),
			'week_over_week' => array(
				'revenue_percent' => self::percent_change( $current_revenue, (float) $get( $previous, 'revenue' ) ),
				'orders_percent' => self::percent_change( $current_orders, (float) $get( $previous, 'orders' ) ),
				'units_percent' => self::percent_change( $current_units, (float) $get( $previous, 'units' ) ),
				'health_points' => null !== $current_health && null !== $previous_health ? round( $current_health - $previous_health, 2 ) : null,
			),
			'risk' => array(
				'revenue_at_risk' => (float) $get( $current, 'revenue_at_risk' ),
				'estimated_lost_sales' => (float) $get( $current, 'estimated_lost_sales' ),
				'stockouts' => (int) $get( $current, 'stockouts' ),
				'dead_slow_count' => (int) $get( $current, 'dead_slow_count' ),
				'health_average' => $current_health,
			),
			'purchasing' => array(
				'open_po_count' => (int) $get( $current, 'open_po_count' ),
				'incoming_units' => (float) $get( $current, 'incoming_units' ),
				'cash_by_currency' => self::normalize_currency_totals( (array) $get( $current, 'purchasing_cash', array() ) ),
			),
			'operations' => array(
				'critical_alerts' => (int) $get( $current, 'critical_alerts' ),
				'warning_alerts' => (int) $get( $current, 'warning_alerts' ),
				'bundle_opportunities' => (int) $get( $current, 'bundle_opportunities' ),
			),
			'generated_at' => gmdate( 'c' ),
		);
	}

	private static function normalize_currency_totals( $rows ) {
		$result = array();
		foreach ( $rows as $currency => $amount ) {
			$currency = strtoupper( trim( (string) $currency ) );
			if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) { continue; }
			$result[ $currency ] = round( max( 0.0, (float) $amount ), 4 );
		}
		ksort( $result );
		return $result;
	}
}
