<?php
/**
 * Lightweight executable test for the schema-version/migration foundation.
 */

declare(strict_types=1);

$ssw_test_options = array();
$ssw_test_dbdelta_calls = array();

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { global $ssw_test_options; return array_key_exists( $name, $ssw_test_options ) ? $ssw_test_options[ $name ] : $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { global $ssw_test_options; $ssw_test_options[ $name ] = $value; return true; }
}
if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $sql ) { global $ssw_test_dbdelta_calls; $ssw_test_dbdelta_calls[] = $sql; return array(); }
}

final class SSW_Test_WPDB {
	public $prefix = 'wp_';
	public $charset = 'utf8mb4';
	public $collate = 'utf8mb4_unicode_ci';
	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
}
$wpdb = new SSW_Test_WPDB();

function ssw_assert( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-db.php';
ssw_assert( file_exists( $class_file ), 'class-ssw-db.php must exist.' );
require_once $class_file;
ssw_assert( class_exists( 'SSW_DB' ), 'SSW_DB class must exist.' );
ssw_assert( defined( 'SSW_DB_SCHEMA_VERSION' ), 'SSW_DB_SCHEMA_VERSION must be defined.' );
ssw_assert( '8' === (string) SSW_DB_SCHEMA_VERSION, 'Bundle aggregation raises schema version to 8.' );

SSW_DB::maybe_upgrade();
ssw_assert( '8' === (string) get_option( 'ssw_db_schema_version', '0' ), 'First upgrade stores schema version 8.' );
ssw_assert( 18 === count( $ssw_test_dbdelta_calls ), 'First upgrade creates all approved foundation and bundle aggregation tables.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[1], 'ssw_suppliers' ), 'Supplier table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[2], 'ssw_supplier_products' ), 'Supplier-product table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[3], 'ssw_locations' ), 'Locations table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[4], 'ssw_location_stock' ), 'Location stock table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[5], 'ssw_stock_movements' ), 'Stock movement table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[6], 'ssw_purchase_orders' ), 'PO table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[7], 'ssw_purchase_order_items' ), 'PO items migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[8], 'ssw_po_receipts' ), 'PO receipts migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[9], 'ssw_po_receipt_items' ), 'PO receipt items migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[10], 'ssw_barcodes' ), 'Barcode table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[11], 'ssw_sales_daily' ), 'Daily sales fact migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[12], 'ssw_inventory_snapshots_daily' ), 'Inventory snapshot migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[13], 'ssw_product_metrics_daily' ), 'Product metrics migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[14], 'ssw_forecasts' ), 'Forecast snapshot migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[15], 'ssw_bundle_pairs' ), 'Bundle pair table migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[16], 'ssw_bundle_product_orders' ), 'Bundle product order counters migration is present.' );
ssw_assert( false !== strpos( $ssw_test_dbdelta_calls[17], 'ssw_bundle_processed_orders' ), 'Bundle processed-order guard migration is present.' );

SSW_DB::maybe_upgrade();
ssw_assert( 18 === count( $ssw_test_dbdelta_calls ), 'Second upgrade is idempotent and does not rerun migrations.' );
fwrite( STDOUT, "PASS: database migration foundation\n" );
