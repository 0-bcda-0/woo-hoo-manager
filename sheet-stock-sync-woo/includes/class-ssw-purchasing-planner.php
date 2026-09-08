<?php
/**
 * Deterministic purchasing/cash planner for approved scope item #34.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Purchasing_Planner {

	public static function plan_product( $input ) {
		$as_of = isset( $input['as_of_date'] ) ? (string) $input['as_of_date'] : gmdate( 'Y-m-d' );
		$velocity = max( 0.0, (float) ( isset( $input['daily_velocity'] ) ? $input['daily_velocity'] : 0 ) );
		$on_hand = max( 0.0, (float) ( isset( $input['on_hand'] ) ? $input['on_hand'] : 0 ) );
		$incoming = max( 0.0, (float) ( isset( $input['incoming'] ) ? $input['incoming'] : 0 ) );
		$lead = max( 0, (int) ( isset( $input['lead_time_days'] ) ? $input['lead_time_days'] : 0 ) );
		$safety = max( 0, (int) ( isset( $input['safety_stock_days'] ) ? $input['safety_stock_days'] : 0 ) );
		$horizon = max( 1, min( 90, (int) ( isset( $input['horizon_days'] ) ? $input['horizon_days'] : 90 ) ) );
		$moq = max( 1.0, (float) ( isset( $input['moq'] ) ? $input['moq'] : 1 ) );
		$pack = max( 1.0, (float) ( isset( $input['pack_size'] ) ? $input['pack_size'] : 1 ) );
		$unit_cost = isset( $input['unit_cost'] ) && '' !== (string) $input['unit_cost'] ? max( 0.0, (float) $input['unit_cost'] ) : null;
		$currency = strtoupper( substr( trim( (string) ( isset( $input['currency'] ) ? $input['currency'] : 'EUR' ) ), 0, 3 ) );

		$result = array(
			'suggested_order_date' => null,
			'suggested_order_quantity' => 0.0,
			'expected_cash' => null,
			'currency' => $currency,
			'daily_velocity' => $velocity,
			'lead_time_days' => $lead,
			'safety_stock_days' => $safety,
			'horizon_days' => $horizon,
			'confidence' => isset( $input['confidence'] ) ? (string) $input['confidence'] : null,
		);

		if ( $velocity <= 0 ) { return $result; }

		$available = $on_hand + $incoming;
		$reorder_cover_days = $lead + $safety;
		$current_cover_days = $available / $velocity;
		$days_until_order = max( 0, (int) floor( $current_cover_days - $reorder_cover_days ) );

		try {
			$date = new DateTimeImmutable( $as_of );
			$order_date = $date->modify( '+' . $days_until_order . ' days' )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return $result;
		}

		$inventory_at_order = max( 0.0, $available - ( $velocity * $days_until_order ) );
		$target_units = $velocity * ( $horizon + $lead + $safety );
		$raw_quantity = max( 0.0, $target_units - $inventory_at_order );
		$rounded_quantity = self::round_order_quantity( $raw_quantity, $moq, $pack );

		if ( $rounded_quantity <= 0 ) { return $result; }

		$result['suggested_order_date'] = $order_date;
		$result['suggested_order_quantity'] = $rounded_quantity;
		$result['expected_cash'] = null === $unit_cost ? null : round( $rounded_quantity * $unit_cost, 4 );
		return $result;
	}

	public static function round_order_quantity( $quantity, $moq, $pack_size ) {
		$quantity = max( 0.0, (float) $quantity );
		$moq = max( 1.0, (float) $moq );
		$pack = max( 1.0, (float) $pack_size );
		if ( $quantity <= 0 ) { return 0.0; }
		$quantity = max( $quantity, $moq );
		return ceil( $quantity / $pack ) * $pack;
	}

	public static function group_cash( $rows ) {
		$by_supplier_currency = array();
		$by_week_currency = array();
		$by_month_currency = array();
		foreach ( (array) $rows as $row ) {
			$cash = isset( $row['expected_cash'] ) && null !== $row['expected_cash'] ? (float) $row['expected_cash'] : 0.0;
			if ( $cash <= 0 ) { continue; }
			$currency = strtoupper( substr( (string) ( isset( $row['currency'] ) ? $row['currency'] : 'EUR' ), 0, 3 ) );
			$supplier_id = abs( (int) ( isset( $row['supplier_id'] ) ? $row['supplier_id'] : 0 ) );
			$date_string = isset( $row['order_date'] ) ? (string) $row['order_date'] : '';
			try { $date = new DateTimeImmutable( $date_string ); } catch ( Exception $e ) { continue; }
			$supplier_key = $supplier_id . ':' . $currency;
			$week_key = $date->format( 'o-\WW' ) . ':' . $currency;
			$month_key = $date->format( 'Y-m' ) . ':' . $currency;
			$by_supplier_currency[ $supplier_key ] = isset( $by_supplier_currency[ $supplier_key ] ) ? $by_supplier_currency[ $supplier_key ] + $cash : $cash;
			$by_week_currency[ $week_key ] = isset( $by_week_currency[ $week_key ] ) ? $by_week_currency[ $week_key ] + $cash : $cash;
			$by_month_currency[ $month_key ] = isset( $by_month_currency[ $month_key ] ) ? $by_month_currency[ $month_key ] + $cash : $cash;
		}
		return array(
			'by_supplier_currency' => $by_supplier_currency,
			'by_week_currency' => $by_week_currency,
			'by_month_currency' => $by_month_currency,
		);
	}
}
