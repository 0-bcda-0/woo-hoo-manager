<?php
/**
 * Deterministic Smart Alerts rules, persistence and lifecycle.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Alerts {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_alerts';
	}

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
		if ( $days_since_sale >= 180 ) {
			$alerts[] = self::make( 'dead_stock', 'warning', $product_id, array( 'days_since_last_sale' => $days_since_sale, 'available_quantity' => $available ) );
		}
		if ( ! empty( $data['bundle_opportunity'] ) ) {
			$alerts[] = self::make( 'bundle_opportunity', 'opportunity', $product_id, array( 'bundle_opportunity' => true ) );
		}
		return $alerts;
	}

	public static function evaluate_purchase_order( $data ) {
		$po_id = abs( (int) ( isset( $data['purchase_order_id'] ) ? $data['purchase_order_id'] : 0 ) );
		$status = strtolower( (string) ( isset( $data['status'] ) ? $data['status'] : '' ) );
		$expected = isset( $data['expected_arrival'] ) ? substr( (string) $data['expected_arrival'], 0, 10 ) : '';
		$today = isset( $data['today'] ) ? substr( (string) $data['today'], 0, 10 ) : gmdate( 'Y-m-d' );
		if ( ! $po_id || ! $expected || ! in_array( $status, array( 'ordered', 'shipped', 'partially_received' ), true ) || $expected >= $today ) { return array(); }
		return array( array(
			'type' => 'late_purchase_order',
			'severity' => 'warning',
			'purchase_order_id' => $po_id,
			'dedupe_key' => self::dedupe_key( 'late_purchase_order', array( 'purchase_order_id' => $po_id ) ),
			'context' => array( 'expected_arrival' => $expected, 'status' => $status ),
		) );
	}

	public static function persist( $alert ) {
		global $wpdb;
		$table = self::table();
		$type = sanitize_key( isset( $alert['type'] ) ? $alert['type'] : '' );
		if ( ! $type ) { return false; }
		$product_id = ! empty( $alert['product_id'] ) ? absint( $alert['product_id'] ) : 0;
		$po_id = ! empty( $alert['purchase_order_id'] ) ? absint( $alert['purchase_order_id'] ) : 0;
		$identity = $product_id ? array( 'product_id' => $product_id ) : array( 'purchase_order_id' => $po_id );
		$key = ! empty( $alert['dedupe_key'] ) ? substr( (string) $alert['dedupe_key'], 0, 64 ) : self::dedupe_key( $type, $identity );
		$severity = self::normalize_severity( isset( $alert['severity'] ) ? $alert['severity'] : 'warning' );
		$context = wp_json_encode( isset( $alert['context'] ) ? $alert['context'] : array() );
		$now = current_time( 'mysql' );
		$sql = "INSERT INTO {$table} (type,severity,product_id,purchase_order_id,dedupe_key,state,context_json,first_seen,last_seen,updated_by) VALUES (%s,%s,%d,%d,%s,'open',%s,%s,%s,0) ON DUPLICATE KEY UPDATE severity=VALUES(severity), context_json=VALUES(context_json), last_seen=VALUES(last_seen), resolved_at=IF(state='resolved',NULL,resolved_at), state=IF(state='resolved','open',state)";
		return false !== $wpdb->query( $wpdb->prepare( $sql, $type, $severity, $product_id, $po_id, $key, $context, $now, $now ) );
	}

	public static function set_state( $id, $state, $snoozed_until = null, $user_id = 0 ) {
		global $wpdb;
		$table = self::table();
		$id = absint( $id );
		$state = self::normalize_state( $state );
		if ( ! $id ) { return false; }
		$resolved_at = 'resolved' === $state ? current_time( 'mysql' ) : null;
		return false !== $wpdb->update(
			$table,
			array( 'state' => $state, 'snoozed_until' => $snoozed_until, 'resolved_at' => $resolved_at, 'updated_by' => absint( $user_id ) ),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	public static function list_active( $limit = 100 ) {
		global $wpdb;
		$table = self::table();
		$limit = min( 250, max( 1, absint( $limit ) ) );
		$now = current_time( 'mysql' );
		$sql = "SELECT * FROM {$table} WHERE state='open' OR (state='snoozed' AND (snoozed_until IS NULL OR snoozed_until<=%s)) ORDER BY FIELD(severity,'critical','warning','opportunity'), last_seen DESC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, $now, $limit ), ARRAY_A );
	}

	private static function make( $type, $severity, $product_id, $context ) {
		return array(
			'type' => $type,
			'severity' => self::normalize_severity( $severity ),
			'product_id' => (int) $product_id,
			'dedupe_key' => self::dedupe_key( $type, array( 'product_id' => (int) $product_id ) ),
			'context' => $context,
		);
	}
}
