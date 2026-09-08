<?php
/**
 * Immutable stock movement ledger.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Stock_Ledger {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_stock_movements';
	}

	public static function normalize_movement( $data ) {
		$type = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : 'adjustment';
		$source = isset( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'manual';
		return array(
			'product_id'  => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'location_id' => absint( isset( $data['location_id'] ) ? $data['location_id'] : 0 ),
			'delta'       => (float) ( isset( $data['delta'] ) ? $data['delta'] : 0 ),
			'type'        => trim( strtolower( $type ) ),
			'source'      => trim( strtolower( $source ) ),
			'source_ref'  => sanitize_text_field( isset( $data['source_ref'] ) ? $data['source_ref'] : '' ),
			'note'        => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
	}

	public static function paired_transfer_movements( $product_id, $from_location_id, $to_location_id, $quantity, $source_ref = '' ) {
		$quantity = abs( (float) $quantity );
		return array(
			self::normalize_movement( array(
				'product_id'  => $product_id,
				'location_id' => $from_location_id,
				'delta'       => -$quantity,
				'type'        => 'transfer_out',
				'source'      => 'transfer',
				'source_ref'  => $source_ref,
			) ),
			self::normalize_movement( array(
				'product_id'  => $product_id,
				'location_id' => $to_location_id,
				'delta'       => $quantity,
				'type'        => 'transfer_in',
				'source'      => 'transfer',
				'source_ref'  => $source_ref,
			) ),
		);
	}

	public static function record_movement( $data ) {
		global $wpdb;
		$movement = self::normalize_movement( $data );
		if ( ! $movement['product_id'] || ! $movement['location_id'] || 0.0 === $movement['delta'] ) {
			return new WP_Error( 'ssw_movement_invalid', __( 'Product, location and non-zero quantity are required.', 'sheet-stock-sync-woo' ) );
		}

		$before = SSW_Locations::get_balance( $movement['product_id'], $movement['location_id'] );
		$after  = $before + $movement['delta'];
		if ( $after < 0 ) {
			return new WP_Error( 'ssw_movement_negative_stock', __( 'This movement would make location stock negative.', 'sheet-stock-sync-woo' ) );
		}

		$wpdb->query( 'START TRANSACTION' );
		if ( ! SSW_Locations::set_balance( $movement['product_id'], $movement['location_id'], $after ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ssw_balance_update_failed', __( 'Could not update location balance.', 'sheet-stock-sync-woo' ) );
		}

		$inserted = $wpdb->insert( self::table(), array(
			'product_id'      => $movement['product_id'],
			'location_id'     => $movement['location_id'],
			'quantity_before' => $before,
			'delta'           => $movement['delta'],
			'quantity_after'  => $after,
			'movement_type'   => $movement['type'],
			'source'          => $movement['source'],
			'source_ref'      => $movement['source_ref'],
			'user_id'         => get_current_user_id(),
			'note'            => $movement['note'],
			'created_at'      => current_time( 'mysql' ),
		) );

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ssw_movement_insert_failed', __( 'Could not record stock movement.', 'sheet-stock-sync-woo' ) );
		}

		$wpdb->query( 'COMMIT' );
		return array(
			'movement_id' => (int) $wpdb->insert_id,
			'before'      => $before,
			'delta'       => $movement['delta'],
			'after'       => $after,
		);
	}

	public static function adjust_and_sync( $data ) {
		$result = self::record_movement( $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$sync = SSW_Locations::sync_woocommerce_aggregate( absint( $data['product_id'] ) );
		if ( is_wp_error( $sync ) ) {
			return $sync;
		}
		$result['woo_aggregate'] = $sync;
		return $result;
	}

	public static function transfer( $product_id, $from_location_id, $to_location_id, $quantity, $source_ref = '' ) {
		if ( absint( $from_location_id ) === absint( $to_location_id ) ) {
			return new WP_Error( 'ssw_transfer_same_location', __( 'Transfer locations must be different.', 'sheet-stock-sync-woo' ) );
		}
		$pair = self::paired_transfer_movements( $product_id, $from_location_id, $to_location_id, $quantity, $source_ref );
		$from = self::record_movement( $pair[0] );
		if ( is_wp_error( $from ) ) {
			return $from;
		}
		$to = self::record_movement( $pair[1] );
		if ( is_wp_error( $to ) ) {
			self::record_movement( array(
				'product_id'  => $product_id,
				'location_id' => $from_location_id,
				'delta'       => abs( (float) $quantity ),
				'type'        => 'transfer_rollback',
				'source'      => 'transfer',
				'source_ref'  => $source_ref,
				'note'        => 'Automatic rollback after failed transfer in.',
			) );
			return $to;
		}
		return array( 'out' => $from, 'in' => $to );
	}
}
