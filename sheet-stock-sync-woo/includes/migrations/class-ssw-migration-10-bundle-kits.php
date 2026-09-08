<?php
/** Approved #21 Bundles / Kits schema. */
defined( 'ABSPATH' ) || exit;
final class SSW_Migration_10_Bundle_Kits {
	public static function run() {
		global $wpdb;
		$cc = $wpdb->get_charset_collate();
		$bundles = $wpdb->prefix . 'ssw_bundles';
		dbDelta( "CREATE TABLE {$bundles} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nbundle_product_id bigint(20) unsigned NOT NULL,\nactive tinyint(1) NOT NULL DEFAULT 1,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY bundle_product (bundle_product_id),\nKEY active (active)\n) {$cc};" );
		$items = $wpdb->prefix . 'ssw_bundle_components';
		dbDelta( "CREATE TABLE {$items} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nbundle_id bigint(20) unsigned NOT NULL,\ncomponent_product_id bigint(20) unsigned NOT NULL,\nquantity decimal(19,4) NOT NULL DEFAULT 1,\nPRIMARY KEY  (id),\nUNIQUE KEY bundle_component (bundle_id,component_product_id),\nKEY component_product (component_product_id),\nKEY bundle_id (bundle_id)\n) {$cc};" );
	}
}
