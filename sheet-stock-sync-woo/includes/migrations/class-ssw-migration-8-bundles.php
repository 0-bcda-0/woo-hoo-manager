<?php
/**
 * Bundle co-purchase aggregation schema.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Migration_8_Bundles {
	public static function run() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$pairs = $wpdb->prefix . 'ssw_bundle_pairs';
		dbDelta( "CREATE TABLE {$pairs} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nproduct_a bigint(20) unsigned NOT NULL,\nproduct_b bigint(20) unsigned NOT NULL,\npair_orders int(10) unsigned NOT NULL DEFAULT 0,\nlast_order_date date NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY product_pair (product_a,product_b),\nKEY pair_orders (pair_orders)\n) {$charset_collate};" );

		$products = $wpdb->prefix . 'ssw_bundle_product_orders';
		dbDelta( "CREATE TABLE {$products} (\nproduct_id bigint(20) unsigned NOT NULL,\norders_count int(10) unsigned NOT NULL DEFAULT 0,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (product_id),\nKEY orders_count (orders_count)\n) {$charset_collate};" );

		$processed = $wpdb->prefix . 'ssw_bundle_processed_orders';
		dbDelta( "CREATE TABLE {$processed} (\norder_id bigint(20) unsigned NOT NULL,\nprocessed_at datetime NOT NULL,\nPRIMARY KEY  (order_id),\nKEY processed_at (processed_at)\n) {$charset_collate};" );
	}
}
