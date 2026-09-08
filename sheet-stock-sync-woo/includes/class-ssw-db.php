<?php
/**
 * Versioned database migration foundation for Woo Hoo Manager.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SSW_DB_SCHEMA_VERSION' ) ) {
	define( 'SSW_DB_SCHEMA_VERSION', 1 );
}

/**
 * Owns plugin schema upgrades.
 *
 * Migrations are deliberately sequential and idempotent. The stored schema
 * version advances only after the corresponding migration completes.
 */
final class SSW_DB {

	/**
	 * Option that stores the last successfully applied schema version.
	 */
	const VERSION_OPTION = 'ssw_db_schema_version';

	/**
	 * Apply all outstanding schema migrations.
	 *
	 * @return void
	 */
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

	/**
	 * Dispatch one migration by version number.
	 *
	 * @param int $version Migration version.
	 * @return void
	 */
	private static function run_migration( $version ) {
		switch ( (int) $version ) {
			case 1:
				self::migration_1();
				break;
		}
	}

	/**
	 * Migration 1: create a tiny schema metadata table.
	 *
	 * The table gives future migrations a database-owned place for migration
	 * metadata while the WordPress option remains the fast current-version
	 * lookup. dbDelta makes the operation safe to repeat during recovery.
	 *
	 * @return void
	 */
	private static function migration_1() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

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
}
