<?php
/**
 * Supplier and product purchasing data service.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Suppliers {

	public static function normalize_relation( $data ) {
		$cost = isset( $data['cost'] ) ? trim( (string) $data['cost'] ) : '';
		$cost = str_replace( ',', '.', $cost );
		if ( '' !== $cost && is_numeric( $cost ) ) {
			$cost = number_format( (float) $cost, 2, '.', '' );
		} else {
			$cost = '';
		}

		$currency = isset( $data['currency'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $data['currency'] ) ) : 'EUR';
		$currency = substr( $currency, 0, 3 );
		if ( 3 !== strlen( $currency ) ) {
			$currency = 'EUR';
		}

		return array(
			'product_id'     => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'supplier_id'    => absint( isset( $data['supplier_id'] ) ? $data['supplier_id'] : 0 ),
			'supplier_sku'   => trim( sanitize_text_field( isset( $data['supplier_sku'] ) ? $data['supplier_sku'] : '' ) ),
			'cost'           => $cost,
			'currency'       => $currency,
			'moq'            => max( 1, absint( isset( $data['moq'] ) ? $data['moq'] : 1 ) ),
			'units_per_box'  => max( 1, absint( isset( $data['units_per_box'] ) ? $data['units_per_box'] : 1 ) ),
			'lead_time_days' => absint( isset( $data['lead_time_days'] ) ? $data['lead_time_days'] : 0 ),
			'is_preferred'   => empty( $data['is_preferred'] ) ? 0 : 1,
		);
	}

	public static function enforce_single_preferred( $relations, $preferred_supplier_id ) {
		$preferred_supplier_id = absint( $preferred_supplier_id );
		foreach ( $relations as &$relation ) {
			$relation['is_preferred'] = ( absint( $relation['supplier_id'] ) === $preferred_supplier_id ) ? 1 : 0;
		}
		unset( $relation );
		return $relations;
	}

	private static function suppliers_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_suppliers';
	}

	private static function relations_table() {
		global $wpdb;
		return $wpdb->prefix . 'ssw_supplier_products';
	}

	public static function all( $active_only = false ) {
		global $wpdb;
		$table = self::suppliers_table();
		$sql   = "SELECT * FROM {$table}";
		if ( $active_only ) {
			$sql .= ' WHERE active = 1';
		}
		$sql .= ' ORDER BY name ASC';
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get( $supplier_id ) {
		global $wpdb;
		$table = self::suppliers_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $supplier_id ) ), ARRAY_A );
	}

	public static function save( $data, $supplier_id = 0 ) {
		global $wpdb;
		$table = self::suppliers_table();
		$now   = current_time( 'mysql' );
		$row   = array(
			'name'                   => sanitize_text_field( isset( $data['name'] ) ? $data['name'] : '' ),
			'contact_name'           => sanitize_text_field( isset( $data['contact_name'] ) ? $data['contact_name'] : '' ),
			'email'                  => sanitize_email( isset( $data['email'] ) ? $data['email'] : '' ),
			'phone'                  => sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' ),
			'website'                => esc_url_raw( isset( $data['website'] ) ? $data['website'] : '' ),
			'default_currency'       => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', isset( $data['default_currency'] ) ? $data['default_currency'] : 'EUR' ), 0, 3 ) ),
			'default_lead_time_days' => absint( isset( $data['default_lead_time_days'] ) ? $data['default_lead_time_days'] : 0 ),
			'notes'                  => sanitize_textarea_field( isset( $data['notes'] ) ? $data['notes'] : '' ),
			'active'                 => empty( $data['active'] ) ? 0 : 1,
			'updated_at'             => $now,
		);
		if ( '' === $row['name'] ) {
			return new WP_Error( 'ssw_supplier_name_required', __( 'Supplier name is required.', 'sheet-stock-sync-woo' ) );
		}
		if ( 3 !== strlen( $row['default_currency'] ) ) {
			$row['default_currency'] = 'EUR';
		}
		$supplier_id = absint( $supplier_id );
		if ( $supplier_id ) {
			$result = $wpdb->update( $table, $row, array( 'id' => $supplier_id ) );
			return false === $result ? new WP_Error( 'ssw_supplier_update_failed', __( 'Could not update supplier.', 'sheet-stock-sync-woo' ) ) : $supplier_id;
		}
		$row['created_at'] = $now;
		$result            = $wpdb->insert( $table, $row );
		return false === $result ? new WP_Error( 'ssw_supplier_insert_failed', __( 'Could not create supplier.', 'sheet-stock-sync-woo' ) ) : (int) $wpdb->insert_id;
	}

	public static function delete( $supplier_id ) {
		global $wpdb;
		$supplier_id = absint( $supplier_id );
		if ( ! $supplier_id ) {
			return false;
		}
		$wpdb->delete( self::relations_table(), array( 'supplier_id' => $supplier_id ) );
		return false !== $wpdb->delete( self::suppliers_table(), array( 'id' => $supplier_id ) );
	}

	public static function product_suppliers( $product_id ) {
		global $wpdb;
		$relations = self::relations_table();
		$suppliers = self::suppliers_table();
		$sql = "SELECT r.*, s.name AS supplier_name FROM {$relations} r INNER JOIN {$suppliers} s ON s.id = r.supplier_id WHERE r.product_id = %d ORDER BY r.is_preferred DESC, s.name ASC";
		return $wpdb->get_results( $wpdb->prepare( $sql, absint( $product_id ) ), ARRAY_A );
	}

	public static function assign_product_supplier( $data ) {
		global $wpdb;
		$row = self::normalize_relation( $data );
		if ( ! $row['product_id'] || ! $row['supplier_id'] ) {
			return new WP_Error( 'ssw_supplier_relation_ids', __( 'Product and supplier are required.', 'sheet-stock-sync-woo' ) );
		}
		$table = self::relations_table();
		$now   = current_time( 'mysql' );
		if ( $row['is_preferred'] ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET is_preferred = 0, updated_at = %s WHERE product_id = %d", $now, $row['product_id'] ) );
		}
		$existing_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d AND supplier_id = %d", $row['product_id'], $row['supplier_id'] ) );
		$dbrow = $row;
		$dbrow['updated_at'] = $now;
		if ( '' === $dbrow['cost'] ) {
			$dbrow['cost'] = null;
		}
		if ( $existing_id ) {
			$result = $wpdb->update( $table, $dbrow, array( 'id' => $existing_id ) );
			return false === $result ? new WP_Error( 'ssw_supplier_relation_update', __( 'Could not update supplier relationship.', 'sheet-stock-sync-woo' ) ) : $existing_id;
		}
		$dbrow['created_at'] = $now;
		$result = $wpdb->insert( $table, $dbrow );
		return false === $result ? new WP_Error( 'ssw_supplier_relation_insert', __( 'Could not create supplier relationship.', 'sheet-stock-sync-woo' ) ) : (int) $wpdb->insert_id;
	}

	public static function remove_product_supplier( $product_id, $supplier_id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::relations_table(), array(
			'product_id'  => absint( $product_id ),
			'supplier_id' => absint( $supplier_id ),
		) );
	}
}
