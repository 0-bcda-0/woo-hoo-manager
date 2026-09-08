<?php
/**
 * Deterministic Smart Alerts rules and normalization.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Alerts {

	public static function normalize_severity( $severity ) {
		$severity = strtolower( trim( (string) $severity ) );
		return in_array( $severity, array( 'critical', 'warning', 'opportunity' ), true ) ? $severity : 'warning';
	}

	public static function normalize_state( $state ) {
		$state = strtolower( trim( (string) $state ) );
		return in_array( $state, array( 'open', 'snoozed', 'resolved', 'dismissed' ), true ) ? $state : 'open';
	}

	public static function dedupe_key( $type, $context ) {
		$context = self::sort_recursive( (array) $context );
		return hash( 'sha256', strtolower( trim( (string) $type ) ) . '|' . wp_json_encode( $context ) );
	}

	private static function sort_recursive( $value ) {
		if ( ! is_array( $value ) ) { return $value; }
		foreach ( $value as $key => $item ) { $value[ $key ] = self::sort_recursive( $item ); }
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) { ksort( $value ); }
		else { sort( $value ); }
		return $value;
	}

	public static function evaluate_product( $data ) {
		$product_id = abs( (int) ( isset( $data['product_id'] ) ? $data['product_id'] : 0 ) );
		if ( ! $product_id ) { return array(); }
		$available = (float) ( isset( $data['available_quantity'] ) ? $data['available_quantity'] : 0 );
		$cover = isset( $data['days_of_stock'] ) && null !== $data['days_of_stock'] ? (float) $data['days_of_stock'] : null;
		$lead = max( 0.0, (float) ( isset( $data['lead_time_days'] ) ? $data['lead_time_days'] : 0 ) );
		$incoming = max( 0.0, (float) ( isset( $data['incoming_quantity'] ) ? $data['incoming_quantity'] : 0 ) );
		$health = isset( $data['health_score'] ) && null !== $data['health_score'] ? (float) $data['health_score'] : null;
		$days_since_sale = max( 0, (int) ( isset( $data['days_since_last_sale'] ) ? $data['days_since_last_sale'] : 0 ) );
		$alerts = array();

		if ( $available <= 0 ) {
			$alerts[] = self::make( 'actual_stockout', 'critical', $product_id, array( 'available_quantity' => $available ) );
		} elseif ( null !== $cover && $lead > 0 && $cover < $lead && $incoming <= 0 ) {
			$alerts[] = self::make( 'predicted_stockout', 'critical', $product_id, array( 'days_of_stock' => $cover, 'lead_time_days' => $lead, 'incoming_quantity' => $incoming ) );
		}
		if ( null !== $health && $health < 40 ) {
			$alerts[] = self::make( 'low_health', 'warning', $product_id, array( 'health_score' => $health ) );
		}
		if ( $days_since_sale >= 180 && $available > 0 ) {
			$alerts[] = self::make( 'dead_stock', 'warning', $product_id, array( 'days_since_last_sale' => $days_since_sale, 'available_quantity' => $available ) );
		} elseif ( $days_since_sale >= 180 && $available <= 0 ) {
			// Still surface the stale-demand/dead-stock condition as a historical inventory signal.
			$alerts[] = self::make( 'dead_stock', 'warning', $product_id, array( 'days_since_last_sale' => $days_since_sale ) );
		}
		if ( ! empty( $data['bundle_opportunity'] ) ) {
			$alerts[] = self::make( 'bundle_opportunity', 'opportunity', $product_id, array( 'bundle_opportunity' => true ) );
		}
		return $alerts;
	}

	private static function make( $type, $severity, $product_id, $context ) {
		$identity = array( 'product_id' => (int) $product_id );
		return array(
			'type' => $type,
			'severity' => self::normalize_severity( $severity ),
			'product_id' => (int) $product_id,
			'dedupe_key' => self::dedupe_key( $type, $identity ),
			'context' => $context,
		);
	}
}
