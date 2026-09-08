<?php
/**
 * Smart Alerts persistence schema.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Migration_9_Alerts {
	public static function run() {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_alerts';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\ntype varchar(80) NOT NULL,\nseverity varchar(20) NOT NULL,\nproduct_id bigint(20) unsigned NULL,\npurchase_order_id bigint(20) unsigned NULL,\ndedupe_key char(64) NOT NULL,\nstate varchar(20) NOT NULL DEFAULT 'open',\ncontext_json longtext NULL,\nfirst_seen datetime NOT NULL,\nlast_seen datetime NOT NULL,\nsnoozed_until datetime NULL,\nresolved_at datetime NULL,\nupdated_by bigint(20) unsigned NOT NULL DEFAULT 0,\nPRIMARY KEY  (id),\nUNIQUE KEY dedupe_key (dedupe_key),\nKEY state_severity (state,severity),\nKEY product_state (product_id,state),\nKEY po_state (purchase_order_id,state),\nKEY last_seen (last_seen)\n) {$charset_collate};";
		dbDelta( $sql );
	}
}
