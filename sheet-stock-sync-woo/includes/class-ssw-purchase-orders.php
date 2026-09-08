<?php
/**
 * Purchase order workflow and receiving service.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Purchase_Orders {

	const STATUS_DRAFT = 'draft';
	const STATUS_APPROVED = 'approved';
	const STATUS_ORDERED = 'ordered';
	const STATUS_SHIPPED = 'shipped';
	const STATUS_PARTIALLY_RECEIVED = 'partially_received';
	const STATUS_RECEIVED = 'received';
	const STATUS_CANCELLED = 'cancelled';

	private static function po_table() { global $wpdb; return $wpdb->prefix . 'ssw_purchase_orders'; }
	private static function items_table() { global $wpdb; return $wpdb->prefix . 'ssw_purchase_order_items'; }
	private static function receipts_table() { global $wpdb; return $wpdb->prefix . 'ssw_po_receipts'; }
	private static function receipt_items_table() { global $wpdb; return $wpdb->prefix . 'ssw_po_receipt_items'; }

	public static function statuses() {
		return array(
			self::STATUS_DRAFT,
			self::STATUS_APPROVED,
			self::STATUS_ORDERED,
			self::STATUS_SHIPPED,
			self::STATUS_PARTIALLY_RECEIVED,
			self::STATUS_RECEIVED,
			self::STATUS_CANCELLED,
		);
	}

	public static function can_transition( $from, $to ) {
		$map = array(
			self::STATUS_DRAFT => array( self::STATUS_APPROVED, self::STATUS_CANCELLED ),
			self::STATUS_APPROVED => array( self::STATUS_ORDERED, self::STATUS_CANCELLED ),
			self::STATUS_ORDERED => array( self::STATUS_SHIPPED, self::STATUS_PARTIALLY_RECEIVED, self::STATUS_RECEIVED, self::STATUS_CANCELLED ),
			self::STATUS_SHIPPED => array( self::STATUS_PARTIALLY_RECEIVED, self::STATUS_RECEIVED, self::STATUS_CANCELLED ),
			self::STATUS_PARTIALLY_RECEIVED => array( self::STATUS_RECEIVED, self::STATUS_CANCELLED ),
			self::STATUS_RECEIVED => array(),
			self::STATUS_CANCELLED => array(),
		);
		return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
	}

	public static function remaining_quantity( $ordered, $received ) {
		return max( 0.0, (float) $ordered - (float) $received );
	}

	public static function derive_receipt_status( $ordered, $received ) {
		$ordered = (float) $ordered;
		$received = (float) $received;
		if ( $ordered > 0 && $received >= $ordered ) {
			return self::STATUS_RECEIVED;
		}
		if ( $received > 0 ) {
			return self::STATUS_PARTIALLY_RECEIVED;
		}
		return self::STATUS_ORDERED;
	}

	public static function receipt_key( $po_id, $request_token ) {
		return hash( 'sha256', abs( (int) $po_id ) . '|' . trim( (string) $request_token ) );
	}

	public static function calculate_totals( $items ) {
		$subtotal = 0.0;
		$units = 0.0;
		foreach ( $items as $item ) {
			$qty = max( 0.0, (float) $item['quantity'] );
			$cost = max( 0.0, (float) str_replace( ',', '.', (string) $item['unit_cost'] ) );
			$units += $qty;
			$subtotal += $qty * $cost;
		}
		return array( 'subtotal' => number_format( $subtotal, 2, '.', '' ), 'units' => $units );
	}

	public static function normalize_item( $item ) {
		$cost = isset( $item['unit_cost'] ) ? str_replace( ',', '.', trim( (string) $item['unit_cost'] ) ) : '0';
		$quantity = isset( $item['quantity'] ) ? str_replace( ',', '.', trim( (string) $item['quantity'] ) ) : '0';
		return array(
			'product_id' => absint( isset( $item['product_id'] ) ? $item['product_id'] : 0 ),
			'supplier_sku' => sanitize_text_field( isset( $item['supplier_sku'] ) ? $item['supplier_sku'] : '' ),
			'description' => sanitize_text_field( isset( $item['description'] ) ? $item['description'] : '' ),
			'ordered_qty' => is_numeric( $quantity ) ? max( 0.0, (float) $quantity ) : 0.0,
			'unit_cost' => is_numeric( $cost ) ? max( 0.0, (float) $cost ) : 0.0,
		);
	}

	public static function create( $data, $items ) {
		global $wpdb;
		$supplier_id = absint( isset( $data['supplier_id'] ) ? $data['supplier_id'] : 0 );
		$location_id = absint( isset( $data['location_id'] ) ? $data['location_id'] : 0 );
		if ( ! $supplier_id || ! $location_id ) {
			return new WP_Error( 'ssw_po_supplier_location_required', __( 'Supplier and destination location are required.', 'sheet-stock-sync-woo' ) );
		}

		$normalized = array();
		foreach ( $items as $item ) {
			$row = self::normalize_item( $item );
			if ( $row['product_id'] && $row['ordered_qty'] > 0 ) {
				$normalized[] = $row;
			}
		}
		if ( ! $normalized ) {
			return new WP_Error( 'ssw_po_items_required', __( 'At least one valid PO item is required.', 'sheet-stock-sync-woo' ) );
		}

		$calc_items = array();
		foreach ( $normalized as $item ) {
			$calc_items[] = array( 'quantity' => $item['ordered_qty'], 'unit_cost' => $item['unit_cost'] );
		}
		$totals = self::calculate_totals( $calc_items );
		$currency = strtoupper( preg_replace( '/[^A-Za-z]/', '', isset( $data['currency'] ) ? $data['currency'] : 'EUR' ) );
		if ( strlen( $currency ) !== 3 ) { $currency = 'EUR'; }
		$now = current_time( 'mysql' );
		$po_number = sanitize_text_field( isset( $data['po_number'] ) ? $data['po_number'] : '' );
		if ( '' === $po_number ) {
			$po_number = 'PO-' . gmdate( 'Ymd-His' ) . '-' . wp_rand( 100, 999 );
		}

		$wpdb->query( 'START TRANSACTION' );
		$ok = $wpdb->insert( self::po_table(), array(
			'po_number' => $po_number,
			'supplier_id' => $supplier_id,
			'location_id' => $location_id,
			'status' => self::STATUS_DRAFT,
			'currency' => $currency,
			'subtotal' => $totals['subtotal'],
			'expected_at' => ! empty( $data['expected_at'] ) ? sanitize_text_field( $data['expected_at'] ) : null,
			'notes' => sanitize_textarea_field( isset( $data['notes'] ) ? $data['notes'] : '' ),
			'created_by' => get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		) );
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ssw_po_create_failed', __( 'Could not create purchase order.', 'sheet-stock-sync-woo' ) );
		}
		$po_id = (int) $wpdb->insert_id;

		foreach ( $normalized as $item ) {
			$product = wc_get_product( $item['product_id'] );
			$description = $item['description'];
			if ( '' === $description && $product ) { $description = wp_strip_all_tags( $product->get_name() ); }
			$line_total = $item['ordered_qty'] * $item['unit_cost'];
			$ok = $wpdb->insert( self::items_table(), array(
				'po_id' => $po_id,
				'product_id' => $item['product_id'],
				'supplier_sku' => $item['supplier_sku'],
				'description' => $description,
				'ordered_qty' => $item['ordered_qty'],
				'received_qty' => 0,
				'unit_cost' => $item['unit_cost'],
				'line_total' => $line_total,
				'created_at' => $now,
				'updated_at' => $now,
			) );
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'ssw_po_item_create_failed', __( 'Could not create purchase order item.', 'sheet-stock-sync-woo' ) );
			}
		}
		$wpdb->query( 'COMMIT' );
		return $po_id;
	}

	public static function get( $po_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::po_table() . ' WHERE id = %d', absint( $po_id ) ), ARRAY_A );
	}

	public static function items( $po_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::items_table() . ' WHERE po_id = %d ORDER BY id ASC', absint( $po_id ) ), ARRAY_A );
	}

	public static function all( $limit = 100 ) {
		global $wpdb;
		$limit = max( 1, min( 500, absint( $limit ) ) );
		$po = self::po_table();
		$sup = $wpdb->prefix . 'ssw_suppliers';
		$loc = $wpdb->prefix . 'ssw_locations';
		$sql = "SELECT p.*, s.name AS supplier_name, l.name AS location_name FROM {$po} p LEFT JOIN {$sup} s ON s.id=p.supplier_id LEFT JOIN {$loc} l ON l.id=p.location_id ORDER BY p.id DESC LIMIT {$limit}";
		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function transition( $po_id, $new_status ) {
		global $wpdb;
		$po = self::get( $po_id );
		if ( ! $po ) { return new WP_Error( 'ssw_po_missing', __( 'Purchase order not found.', 'sheet-stock-sync-woo' ) ); }
		$new_status = sanitize_key( $new_status );
		if ( ! self::can_transition( $po['status'], $new_status ) ) {
			return new WP_Error( 'ssw_po_invalid_transition', __( 'Invalid purchase order status transition.', 'sheet-stock-sync-woo' ) );
		}
		$row = array( 'status' => $new_status, 'updated_at' => current_time( 'mysql' ) );
		if ( self::STATUS_ORDERED === $new_status ) { $row['ordered_at'] = current_time( 'mysql' ); }
		$result = $wpdb->update( self::po_table(), $row, array( 'id' => absint( $po_id ) ) );
		return false === $result ? new WP_Error( 'ssw_po_status_failed', __( 'Could not update purchase order status.', 'sheet-stock-sync-woo' ) ) : true;
	}

	public static function receive( $po_id, $location_id, $lines, $request_token, $note = '' ) {
		global $wpdb;
		$po_id = absint( $po_id );
		$location_id = absint( $location_id );
		$key = self::receipt_key( $po_id, $request_token );
		$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::receipts_table() . ' WHERE idempotency_key = %s', $key ) );
		if ( $existing ) {
			return array( 'receipt_id' => $existing, 'duplicate' => true );
		}
		$po = self::get( $po_id );
		if ( ! $po || in_array( $po['status'], array( self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_RECEIVED, self::STATUS_CANCELLED ), true ) ) {
			return new WP_Error( 'ssw_po_not_receivable', __( 'This purchase order cannot receive stock in its current state.', 'sheet-stock-sync-woo' ) );
		}
		$items = self::items( $po_id );
		$by_id = array();
		foreach ( $items as $item ) { $by_id[ (int) $item['id'] ] = $item; }

		$normalized = array();
		foreach ( $lines as $line ) {
			$item_id = absint( isset( $line['po_item_id'] ) ? $line['po_item_id'] : 0 );
			$qty_raw = isset( $line['quantity'] ) ? str_replace( ',', '.', (string) $line['quantity'] ) : '0';
			$qty = is_numeric( $qty_raw ) ? (float) $qty_raw : 0.0;
			if ( $qty <= 0 ) { continue; }
			if ( ! isset( $by_id[ $item_id ] ) ) { return new WP_Error( 'ssw_po_receipt_item_invalid', __( 'Receipt contains an item not belonging to this purchase order.', 'sheet-stock-sync-woo' ) ); }
			$remaining = self::remaining_quantity( $by_id[ $item_id ]['ordered_qty'], $by_id[ $item_id ]['received_qty'] );
			if ( $qty > $remaining ) { return new WP_Error( 'ssw_po_receipt_over', __( 'Received quantity exceeds the remaining ordered quantity.', 'sheet-stock-sync-woo' ) ); }
			$normalized[] = array( 'po_item_id' => $item_id, 'product_id' => (int) $by_id[ $item_id ]['product_id'], 'quantity' => $qty );
		}
		if ( ! $normalized ) { return new WP_Error( 'ssw_po_receipt_empty', __( 'Enter at least one received quantity.', 'sheet-stock-sync-woo' ) ); }

		$wpdb->query( 'START TRANSACTION' );
		$inserted = $wpdb->insert( self::receipts_table(), array(
			'po_id' => $po_id,
			'location_id' => $location_id,
			'idempotency_key' => $key,
			'note' => sanitize_textarea_field( $note ),
			'received_by' => get_current_user_id(),
			'received_at' => current_time( 'mysql' ),
		) );
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::receipts_table() . ' WHERE idempotency_key = %s', $key ) );
			return $existing ? array( 'receipt_id' => $existing, 'duplicate' => true ) : new WP_Error( 'ssw_po_receipt_create_failed', __( 'Could not create receipt.', 'sheet-stock-sync-woo' ) );
		}
		$receipt_id = (int) $wpdb->insert_id;
		$products_to_sync = array();

		foreach ( $normalized as $line ) {
			$movement = SSW_Stock_Ledger::record_movement( array(
				'product_id' => $line['product_id'],
				'location_id' => $location_id,
				'delta' => $line['quantity'],
				'type' => 'po_receipt',
				'source' => 'purchase_order',
				'source_ref' => (string) $receipt_id,
				'note' => sanitize_text_field( $note ),
			), false );
			if ( is_wp_error( $movement ) ) { $wpdb->query( 'ROLLBACK' ); return $movement; }

			$ok = $wpdb->insert( self::receipt_items_table(), array(
				'receipt_id' => $receipt_id,
				'po_item_id' => $line['po_item_id'],
				'product_id' => $line['product_id'],
				'quantity' => $line['quantity'],
				'movement_id' => $movement['movement_id'],
			) );
			if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ssw_po_receipt_item_failed', __( 'Could not save receipt item.', 'sheet-stock-sync-woo' ) ); }

			$old_received = (float) $by_id[ $line['po_item_id'] ]['received_qty'];
			$new_received = $old_received + $line['quantity'];
			$ok = $wpdb->update( self::items_table(), array( 'received_qty' => $new_received, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $line['po_item_id'] ) );
			if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ssw_po_received_qty_failed', __( 'Could not update received quantity.', 'sheet-stock-sync-woo' ) ); }
			$products_to_sync[ $line['product_id'] ] = true;
			$by_id[ $line['po_item_id'] ]['received_qty'] = $new_received;
		}

		$total_ordered = 0.0; $total_received = 0.0;
		foreach ( $by_id as $item ) { $total_ordered += (float) $item['ordered_qty']; $total_received += (float) $item['received_qty']; }
		$status = self::derive_receipt_status( $total_ordered, $total_received );
		$ok = $wpdb->update( self::po_table(), array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $po_id ) );
		if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ssw_po_receipt_status_failed', __( 'Could not update purchase order receipt status.', 'sheet-stock-sync-woo' ) ); }
		$wpdb->query( 'COMMIT' );

		foreach ( array_keys( $products_to_sync ) as $product_id ) { SSW_Locations::sync_woocommerce_aggregate( $product_id ); }
		return array( 'receipt_id' => $receipt_id, 'duplicate' => false, 'status' => $status );
	}
}
