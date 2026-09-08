<?php
/**
 * Barcode schema migration.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Migration_5_Barcodes {

	public static function run() {
		global $wpdb;
		$table           = $wpdb->prefix . 'ssw_barcodes';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "barcode varchar(191) NOT NULL,\n"
			. "is_primary tinyint(1) NOT NULL DEFAULT 0,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY barcode (barcode),\n"
			. "KEY product_id (product_id),\n"
			. "KEY product_primary (product_id,is_primary)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
