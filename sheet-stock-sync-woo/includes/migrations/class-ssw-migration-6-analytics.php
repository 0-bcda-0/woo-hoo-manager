<?php
/**
 * Analytics fact/snapshot schema migration.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Migration_6_Analytics {

	public static function run() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sales = $wpdb->prefix . 'ssw_sales_daily';
		$sql = "CREATE TABLE {$sales} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "fact_date date NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "units decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "revenue decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "orders_count int(10) unsigned NOT NULL DEFAULT 0,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY date_product (fact_date,product_id),\n"
			. "KEY product_date (product_id,fact_date)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$sql = "CREATE TABLE {$snapshots} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "snapshot_date date NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "location_id bigint(20) unsigned NOT NULL,\n"
			. "quantity decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "unit_cost decimal(19,4) NULL,\n"
			. "retail_price decimal(19,4) NULL,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY date_product_location (snapshot_date,product_id,location_id),\n"
			. "KEY product_date (product_id,snapshot_date),\n"
			. "KEY location_date (location_id,snapshot_date)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
