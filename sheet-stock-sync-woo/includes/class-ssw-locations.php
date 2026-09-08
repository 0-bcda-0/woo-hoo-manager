<?php
/**
 * Inventory location service.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Locations {

	const MAIN_CODE = 'main';

	private static function locations_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_locations';
	}

	private static function stock_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_location_stock';
	}

	public static function aggregate_sellable( $balances ) {
		$total = 0.0;
		foreach ( $balances as $balance ) {
			if ( empty( $balance['active'] ) || empty( $balance['is_sellable'] ) ) {
				continue;
			}
			$total += (float) $balance['quantity'];
		}
		return $total;
	}

	public static function ensure_main_warehouse() {
		global $wpdb;
		$table = self::locations_table();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", self::MAIN_CODE ) );
		if ( $id ) {
			return $id;
		}
		$now = current_time( 'mysql' );
		$ok = $wpdb->insert( $table, array(
			'name'        => __( 'Main warehouse', 'sheet-stock-sync-woo' ),
			'code'        => self::MAIN_CODE,
			'is_sellable' => 1,
			'active'      => 1,
			'created_at'  => $now,
			'updated_at'  => $now,
		) );
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	public static function all( $active_only = false ) {
		global $wpdb;
		$table = self::locations_table();
		$sql = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY name ASC';
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get_balance( $product_id, $location_id ) {
		global $wpdb;
		$table = self::stock_table();
		$value = $wpdb->get_var( $wpdb->prepare(
			"SELECT quantity FROM {$table} WHERE product_id = %d AND location_id = %d",
			absint( $product_id ),
			absint( $location_id )
		) );
		return null === $value ? 0.0 : (float) $value;
	}

	public static function set_balance( $product_id, $location_id, $quantity ) {
		global $wpdb;
		$product_id  = absint( $product_id );
		$location_id = absint( $location_id );
		$quantity    = (float) $quantity;
		$table       = self::stock_table();
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE product_id = %d AND location_id = %d",
			$product_id,
			$location_id
		) );
		$row = array(
			'product_id'  => $product_id,
			'location_id' => $location_id,
			'quantity'    => $quantity,
			'updated_at'  => current_time( 'mysql' ),
		);
		if ( $existing_id ) {
			return false !== $wpdb->update( $table, $row, array( 'id' => $existing_id ) );
		}
		return false !== $wpdb->insert( $table, $row );
	}

	public static function product_balances( $product_id ) {
		global $wpdb;
		$stock = self::stock_table();
		$locations = self::locations_table();
		$sql = "SELECT s.quantity, l.id AS location_id, l.name, l.code, l.active, l.is_sellable FROM {$stock} s INNER JOIN {$locations} l ON l.id = s.location_id WHERE s.product_id = %d ORDER BY l.name ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $product_id ) ), ARRAY_A );
	}

	public static function sync_woocommerce_aggregate( $product_id ) {
		$product_id = absint( $product_id );
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'ssw_location_product_missing', __( 'WooCommerce product not found.', 'sheet-stock-sync-woo' ) );
		}
		$balances = self::product_balances( $product_id );
		$total = self::aggregate_sellable( $balances );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( $total );
		$product->save();
		return $total;
	}

	public static function seed_product_to_main( $product_id ) {
		$product_id = absint( $product_id );
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->managing_stock() ) {
			return false;
		}
		$main_id = self::ensure_main_warehouse();
		if ( ! $main_id ) {
			return false;
		}
		global $wpdb;
		$table = self::stock_table();
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE product_id = %d", $product_id ) );
		if ( $exists ) {
			return true;
		}
		return self::set_balance( $product_id, $main_id, (float) $product->get_stock_quantity() );
	}
}
