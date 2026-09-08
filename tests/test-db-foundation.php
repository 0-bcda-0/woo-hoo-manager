<?php
/**
 * Lightweight executable test for the schema-version/migration foundation.
 *
 * Run with: php tests/test-db-foundation.php
 */

declare(strict_types=1);

$ssw_test_options = array();
$ssw_test_dbdelta_calls = array();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $ssw_test_options;
		return array_key_exists( $name, $ssw_test_options ) ? $ssw_test_options[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		global $ssw_test_options;
		$ssw_test_options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) {
		global $ssw_test_dbdelta_calls;
		$ssw_test_dbdelta_calls[] = $sql;
		return array();
	}
}

final class SSW_Test_WPDB {
	public $prefix = 'wp_';
	public $charset = 'utf8mb4';
	public $collate = 'utf8mb4_unicode_ci';

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
}

$wpdb = new SSW_Test_WPDB();

function ssw_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-db.php';
ssw_assert( file_exists( $class_file ), 'class-ssw-db.php must exist.' );
require_once $class_file;

ssw_assert( class_exists( 'SSW_DB' ), 'SSW_DB class must exist.' );
ssw_assert( defined( 'SSW_DB_SCHEMA_VERSION' ), 'SSW_DB_SCHEMA_VERSION must be defined.' );
ssw_assert( '1' === (string) SSW_DB_SCHEMA_VERSION, 'Initial schema version must be 1.' );

SSW_DB::maybe_upgrade();
ssw_assert( '1' === (string) get_option( 'ssw_db_schema_version', '0' ), 'First upgrade stores schema version 1.' );
ssw_assert( 1 === count( $ssw_test_dbdelta_calls ), 'First upgrade runs the version-1 migration exactly once.' );

SSW_DB::maybe_upgrade();
ssw_assert( 1 === count( $ssw_test_dbdelta_calls ), 'Second upgrade is idempotent and does not rerun migration 1.' );

fwrite( STDOUT, "PASS: database migration foundation\n" );
