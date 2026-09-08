<?php
/**
 * Purchase-order schema migration.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Migration_4_Purchase_Orders {

	public static function run() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$orders = $wpdb->prefix . 'ssw_purchase_orders';
		$sql = "CREATE TABLE {$orders} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "po_number varchar(100) NOT NULL,\n"
			. "supplier_id bigint(20) unsigned NOT NULL,\n"
			. "location_id bigint(20) unsigned NOT NULL,\n"
			. "status varchar(30) NOT NULL DEFAULT 'draft',\n"
			. "currency char(3) NOT NULL DEFAULT 'EUR',\n"
			. "subtotal decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "ordered_at datetime NULL,\n"
			. "expected_at datetime NULL,\n"
			. "notes text NULL,\n"
			. "created_by bigint(20) unsigned NOT NULL DEFAULT 0,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY po_number (po_number),\n"
			. "KEY supplier_status (supplier_id,status),\n"
			. "KEY location_status (location_id,status)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$sql = "CREATE TABLE {$items} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "po_id bigint(20) unsigned NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "supplier_sku varchar(191) NULL,\n"
			. "description varchar(255) NULL,\n"
			. "ordered_qty decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "received_qty decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "unit_cost decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "line_total decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "KEY po_id (po_id),\n"
			. "KEY product_id (product_id)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$receipts = $wpdb->prefix . 'ssw_po_receipts';
		$sql = "CREATE TABLE {$receipts} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "po_id bigint(20) unsigned NOT NULL,\n"
			. "location_id bigint(20) unsigned NOT NULL,\n"
			. "idempotency_key char(64) NOT NULL,\n"
			. "note text NULL,\n"
			. "received_by bigint(20) unsigned NOT NULL DEFAULT 0,\n"
			. "received_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY idempotency_key (idempotency_key),\n"
			. "KEY po_received (po_id,received_at)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$receipt_items = $wpdb->prefix . 'ssw_po_receipt_items';
		$sql = "CREATE TABLE {$receipt_items} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "receipt_id bigint(20) unsigned NOT NULL,\n"
			. "po_item_id bigint(20) unsigned NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "quantity decimal(19,4) NOT NULL,\n"
			. "movement_id bigint(20) unsigned NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "KEY receipt_id (receipt_id),\n"
			. "KEY po_item_id (po_item_id),\n"
			. "KEY product_id (product_id)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
