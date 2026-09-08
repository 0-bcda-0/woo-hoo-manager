<?php
/**
 * Versioned database migration foundation for Woo Hoo Manager.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SSW_DB_SCHEMA_VERSION' ) ) {
	define( 'SSW_DB_SCHEMA_VERSION', 2 );
}

/**
 * Owns plugin schema upgrades.
 */
final class SSW_DB {

	const VERSION_OPTION = 'ssw_db_schema_version';

	public static function maybe_upgrade() {
		$current = (int) get_option( self::VERSION_OPTION, 0 );
		$target  = (int) SSW_DB_SCHEMA_VERSION;

		if ( $current >= $target ) {
			return;
		}

		for ( $version = $current + 1; $version <= $target; $version++ ) {
			self::run_migration( $version );
			update_option( self::VERSION_OPTION, (string) $version, false );
		}
	}

	private static function run_migration( $version ) {
		switch ( (int) $version ) {
			case 1:
				self::migration_1();
				break;
			case 2:
				self::migration_2();
				break;
		}
	}

	private static function ensure_dbdelta() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
	}

	private static function migration_1() {
		global $wpdb;
		self::ensure_dbdelta();

		$table_name      = $wpdb->prefix . 'ssw_schema_meta';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (\n"
			. "meta_key varchar(191) NOT NULL,\n"
			. "meta_value longtext NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (meta_key)\n"
			. ") {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Supplier master data and product/variation purchasing relationships.
	 */
	private static function migration_2() {
		global $wpdb;
		self::ensure_dbdelta();
		$charset_collate = $wpdb->get_charset_collate();

		$suppliers = $wpdb->prefix . 'ssw_suppliers';
		$sql       = "CREATE TABLE {$suppliers} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "name varchar(191) NOT NULL,\n"
			. "contact_name varchar(191) NULL,\n"
			. "email varchar(191) NULL,\n"
			. "phone varchar(100) NULL,\n"
			. "website varchar(255) NULL,\n"
			. "default_currency char(3) NOT NULL DEFAULT 'EUR',\n"
			. "default_lead_time_days int(10) unsigned NOT NULL DEFAULT 0,\n"
			. "notes text NULL,\n"
			. "active tinyint(1) NOT NULL DEFAULT 1,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "KEY name (name),\n"
			. "KEY active (active)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$sql       = "CREATE TABLE {$relations} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "supplier_id bigint(20) unsigned NOT NULL,\n"
			. "supplier_sku varchar(191) NULL,\n"
			. "cost decimal(19,4) NULL,\n"
			. "currency char(3) NOT NULL DEFAULT 'EUR',\n"
			. "moq int(10) unsigned NOT NULL DEFAULT 1,\n"
			. "units_per_box int(10) unsigned NOT NULL DEFAULT 1,\n"
			. "lead_time_days int(10) unsigned NOT NULL DEFAULT 0,\n"
			. "is_preferred tinyint(1) NOT NULL DEFAULT 0,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY product_supplier (product_id,supplier_id),\n"
			. "KEY product_id (product_id),\n"
			. "KEY supplier_id (supplier_id),\n"
			. "KEY preferred_product (product_id,is_preferred)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
