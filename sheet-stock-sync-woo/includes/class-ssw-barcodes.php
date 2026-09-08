<?php
/**
 * Barcode mapping and stock-count helpers.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Barcodes {

	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_barcodes';
	}

	public static function normalize_code( $code ) {
		$code = trim( (string) $code );
		$code = preg_replace( '/\s+/', '', $code );
		$code = preg_replace( '/[^A-Za-z0-9_\-\.]/', '', $code );
		return substr( $code, 0, 191 );
	}

	public static function count_delta( $current_quantity, $counted_quantity ) {
		return (float) $counted_quantity - (float) $current_quantity;
	}

	public static function normalize_assignment( $data ) {
		return array(
			'product_id' => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'barcode'    => self::normalize_code( isset( $data['barcode'] ) ? $data['barcode'] : '' ),
			'is_primary' => empty( $data['is_primary'] ) ? 0 : 1,
		);
	}

	public static function assign( $data ) {
		global $wpdb;
		$row = self::normalize_assignment( $data );
		if ( ! $row['product_id'] || '' === $row['barcode'] ) {
			return new WP_Error( 'ssw_barcode_required', __( 'Product and barcode are required.', 'sheet-stock-sync-woo' ) );
		}
		if ( ! wc_get_product( $row['product_id'] ) ) {
			return new WP_Error( 'ssw_barcode_product_missing', __( 'WooCommerce product or variation not found.', 'sheet-stock-sync-woo' ) );
		}
		$table = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, product_id FROM {$table} WHERE barcode = %s", $row['barcode'] ), ARRAY_A );
		if ( $existing && (int) $existing['product_id'] !== $row['product_id'] ) {
			return new WP_Error( 'ssw_barcode_in_use', __( 'This barcode is already assigned to another product.', 'sheet-stock-sync-woo' ) );
		}
		$now = current_time( 'mysql' );
		if ( $row['is_primary'] ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_primary = 0, updated_at = %s WHERE product_id = %d", $now, $row['product_id'] ) );
		}
		if ( $existing ) {
			$result = $wpdb->update( $table, array( 'is_primary' => $row['is_primary'], 'updated_at' => $now ), array( 'id' => (int) $existing['id'] ) );
			return false === $result ? new WP_Error( 'ssw_barcode_update_failed', __( 'Could not update barcode.', 'sheet-stock-sync-woo' ) ) : (int) $existing['id'];
		}
		$result = $wpdb->insert( $table, array(
			'product_id' => $row['product_id'],
			'barcode'    => $row['barcode'],
			'is_primary' => $row['is_primary'],
			'created_at' => $now,
			'updated_at' => $now,
		) );
		return false === $result ? new WP_Error( 'ssw_barcode_insert_failed', __( 'Could not assign barcode.', 'sheet-stock-sync-woo' ) ) : (int) $wpdb->insert_id;
	}

	public static function lookup( $code ) {
		global $wpdb;
		$code = self::normalize_code( $code );
		if ( '' === $code ) { return 0; }
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$table} WHERE barcode = %s", $code ) );
	}

	public static function for_product( $product_id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d ORDER BY is_primary DESC, id ASC", absint( $product_id ) ), ARRAY_A );
	}

	public static function remove( $barcode_id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => absint( $barcode_id ) ) );
	}
}
