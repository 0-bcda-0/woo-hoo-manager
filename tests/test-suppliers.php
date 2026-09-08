<?php
/**
 * Lightweight supplier-domain test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' );
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

function ssw_assert_supplier( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-suppliers.php';
ssw_assert_supplier( file_exists( $class_file ), 'class-ssw-suppliers.php must exist.' );
require_once $class_file;

ssw_assert_supplier( class_exists( 'SSW_Suppliers' ), 'SSW_Suppliers class must exist.' );

$normalized = SSW_Suppliers::normalize_relation( array(
	'product_id'     => '123',
	'supplier_id'    => '7',
	'supplier_sku'   => ' SUP-001 ',
	'cost'           => '12,50',
	'currency'       => 'eur',
	'moq'            => '6',
	'units_per_box'  => '12',
	'lead_time_days' => '9',
	'is_preferred'   => '1',
) );

ssw_assert_supplier( 123 === $normalized['product_id'], 'Product ID is normalized to int.' );
ssw_assert_supplier( 7 === $normalized['supplier_id'], 'Supplier ID is normalized to int.' );
ssw_assert_supplier( 'SUP-001' === $normalized['supplier_sku'], 'Supplier SKU is trimmed.' );
ssw_assert_supplier( '12.50' === $normalized['cost'], 'Cost uses decimal dot and two decimals.' );
ssw_assert_supplier( 'EUR' === $normalized['currency'], 'Currency is normalized uppercase.' );
ssw_assert_supplier( 6 === $normalized['moq'], 'MOQ is normalized to positive integer.' );
ssw_assert_supplier( 12 === $normalized['units_per_box'], 'Units per box is normalized.' );
ssw_assert_supplier( 9 === $normalized['lead_time_days'], 'Lead time is normalized.' );
ssw_assert_supplier( 1 === $normalized['is_preferred'], 'Preferred flag is normalized.' );

$relations = array(
	array( 'supplier_id' => 2, 'is_preferred' => 1 ),
	array( 'supplier_id' => 7, 'is_preferred' => 1 ),
	array( 'supplier_id' => 9, 'is_preferred' => 0 ),
);
$resolved = SSW_Suppliers::enforce_single_preferred( $relations, 7 );
ssw_assert_supplier( 0 === $resolved[0]['is_preferred'], 'Old preferred supplier is cleared.' );
ssw_assert_supplier( 1 === $resolved[1]['is_preferred'], 'Requested supplier remains preferred.' );
ssw_assert_supplier( 0 === $resolved[2]['is_preferred'], 'Unrelated supplier stays non-preferred.' );

fwrite( STDOUT, "PASS: supplier purchasing behavior\n" );
