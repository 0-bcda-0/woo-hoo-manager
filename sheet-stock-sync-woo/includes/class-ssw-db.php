<?php
/**
 * Versioned database migration foundation for Woo Hoo Manager.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SSW_DB_SCHEMA_VERSION' ) ) {
	define( 'SSW_DB_SCHEMA_VERSION', 10 );
}

final class SSW_DB {
	const VERSION_OPTION = 'ssw_db_schema_version';

	public static function maybe_upgrade() {
		$current = (int) get_option( self::VERSION_OPTION, 0 );
		$target  = (int) SSW_DB_SCHEMA_VERSION;
		if ( $current >= $target ) { return; }
		for ( $version = $current + 1; $version <= $target; $version++ ) {
			self::run_migration( $version );
			update_option( self::VERSION_OPTION, (string) $version, false );
		}
	}

	private static function run_migration( $version ) {
		switch ( (int) $version ) {
			case 1: self::migration_1(); break;
			case 2: self::migration_2(); break;
			case 3: self::migration_3(); break;
			case 4: self::migration_4(); break;
			case 5: self::migration_5(); break;
			case 6: self::migration_6(); break;
			case 7: self::migration_7(); break;
			case 8: self::migration_8(); break;
			case 9: self::migration_9(); break;
			case 10: self::migration_10(); break;
		}
	}

	private static function ensure_dbdelta() {
		if ( ! function_exists( 'dbDelta' ) ) { require_once ABSPATH . 'wp-admin/includes/upgrade.php'; }
	}

	private static function migration_1() {
		global $wpdb;
		self::ensure_dbdelta();
		$table_name = $wpdb->prefix . 'ssw_schema_meta';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table_name} (\nmeta_key varchar(191) NOT NULL,\nmeta_value longtext NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (meta_key)\n) {$charset_collate};";
		dbDelta( $sql );
	}

	private static function migration_2() {
		global $wpdb;
		self::ensure_dbdelta();
		$charset_collate = $wpdb->get_charset_collate();
		$suppliers = $wpdb->prefix . 'ssw_suppliers';
		$sql = "CREATE TABLE {$suppliers} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nname varchar(191) NOT NULL,\ncontact_name varchar(191) NULL,\nemail varchar(191) NULL,\nphone varchar(100) NULL,\nwebsite varchar(255) NULL,\ndefault_currency char(3) NOT NULL DEFAULT 'EUR',\ndefault_lead_time_days int(10) unsigned NOT NULL DEFAULT 0,\nnotes text NULL,\nactive tinyint(1) NOT NULL DEFAULT 1,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nKEY name (name),\nKEY active (active)\n) {$charset_collate};";
		dbDelta( $sql );
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$sql = "CREATE TABLE {$relations} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nproduct_id bigint(20) unsigned NOT NULL,\nsupplier_id bigint(20) unsigned NOT NULL,\nsupplier_sku varchar(191) NULL,\ncost decimal(19,4) NULL,\ncurrency char(3) NOT NULL DEFAULT 'EUR',\nmoq int(10) unsigned NOT NULL DEFAULT 1,\nunits_per_box int(10) unsigned NOT NULL DEFAULT 1,\nlead_time_days int(10) unsigned NOT NULL DEFAULT 0,\nis_preferred tinyint(1) NOT NULL DEFAULT 0,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY product_supplier (product_id,supplier_id),\nKEY product_id (product_id),\nKEY supplier_id (supplier_id),\nKEY preferred_product (product_id,is_preferred)\n) {$charset_collate};";
		dbDelta( $sql );
	}

	private static function migration_3() {
		global $wpdb;
		self::ensure_dbdelta();
		$charset_collate = $wpdb->get_charset_collate();
		$locations = $wpdb->prefix . 'ssw_locations';
		$sql = "CREATE TABLE {$locations} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nname varchar(191) NOT NULL,\ncode varchar(100) NOT NULL,\nis_sellable tinyint(1) NOT NULL DEFAULT 1,\nactive tinyint(1) NOT NULL DEFAULT 1,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY code (code),\nKEY active_sellable (active,is_sellable)\n) {$charset_collate};";
		dbDelta( $sql );
		$stock = $wpdb->prefix . 'ssw_location_stock';
		$sql = "CREATE TABLE {$stock} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nproduct_id bigint(20) unsigned NOT NULL,\nlocation_id bigint(20) unsigned NOT NULL,\nquantity decimal(19,4) NOT NULL DEFAULT 0,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY product_location (product_id,location_id),\nKEY product_id (product_id),\nKEY location_id (location_id)\n) {$charset_collate};";
		dbDelta( $sql );
		$movements = $wpdb->prefix . 'ssw_stock_movements';
		$sql = "CREATE TABLE {$movements} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nproduct_id bigint(20) unsigned NOT NULL,\nlocation_id bigint(20) unsigned NOT NULL,\nquantity_before decimal(19,4) NOT NULL,\ndelta decimal(19,4) NOT NULL,\nquantity_after decimal(19,4) NOT NULL,\nmovement_type varchar(50) NOT NULL,\nsource varchar(100) NOT NULL,\nsource_ref varchar(191) NULL,\nuser_id bigint(20) unsigned NOT NULL DEFAULT 0,\nnote text NULL,\ncreated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nKEY product_created (product_id,created_at),\nKEY location_created (location_id,created_at),\nKEY source_ref (source,source_ref)\n) {$charset_collate};";
		dbDelta( $sql );
	}

	private static function migration_4() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-4-purchase-orders.php';
		SSW_Migration_4_Purchase_Orders::run();
	}
	private static function migration_5() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-5-barcodes.php';
		SSW_Migration_5_Barcodes::run();
	}
	private static function migration_6() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-6-analytics.php';
		SSW_Migration_6_Analytics::run();
	}
	private static function migration_7() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-7-forecasting.php';
		SSW_Migration_7_Forecasting::run();
	}
	private static function migration_8() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-8-bundles.php';
		SSW_Migration_8_Bundles::run();
	}
	private static function migration_9() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-9-alerts.php';
		SSW_Migration_9_Alerts::run();
	}
	private static function migration_10() {
		self::ensure_dbdelta();
		require_once __DIR__ . '/migrations/class-ssw-migration-10-reports.php';
		SSW_Migration_10_Reports::run();
	}
}
