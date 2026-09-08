<?php
/**
 * Lightweight barcode/count domain test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); } }

function ssw_assert_barcode( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-barcodes.php';
ssw_assert_barcode( file_exists( $class_file ), 'class-ssw-barcodes.php must exist.' );
require_once $class_file;

ssw_assert_barcode( '3851234567890' === SSW_Barcodes::normalize_code( ' 385 123 456 7890 ' ), 'Numeric barcode whitespace is removed.' );
ssw_assert_barcode( 'ABC-123_X' === SSW_Barcodes::normalize_code( ' ABC-123_X ' ), 'Alphanumeric barcode is trimmed and preserved.' );
ssw_assert_barcode( '' === SSW_Barcodes::normalize_code( '***' ), 'Barcode with no supported characters is rejected.' );

ssw_assert_barcode( 4.0 === SSW_Barcodes::count_delta( 6, 10 ), 'Count delta adds missing stock.' );
ssw_assert_barcode( -3.0 === SSW_Barcodes::count_delta( 10, 7 ), 'Count delta removes excess stock.' );
ssw_assert_barcode( 0.0 === SSW_Barcodes::count_delta( 5, 5 ), 'Matching count produces zero adjustment.' );

$assignment = SSW_Barcodes::normalize_assignment( array( 'product_id' => '77', 'barcode' => ' 385 001 ', 'is_primary' => '1' ) );
ssw_assert_barcode( 77 === $assignment['product_id'], 'Barcode assignment product ID is normalized.' );
ssw_assert_barcode( '385001' === $assignment['barcode'], 'Assignment barcode is normalized.' );
ssw_assert_barcode( 1 === $assignment['is_primary'], 'Primary flag is normalized.' );

$admin_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-barcode-admin.php';
ssw_assert_barcode( file_exists( $admin_file ), 'class-ssw-barcode-admin.php must exist.' );
require_once $admin_file;

$count = SSW_Barcode_Admin::normalize_count_request( array(
	'product_id' => '77',
	'location_id' => '5',
	'counted_quantity' => '12,5',
	'note' => ' Shelf count ',
) );
ssw_assert_barcode( 77 === $count['product_id'], 'Count request normalizes product ID.' );
ssw_assert_barcode( 5 === $count['location_id'], 'Count request normalizes location ID.' );
ssw_assert_barcode( 12.5 === $count['counted_quantity'], 'Count request accepts decimal comma.' );
ssw_assert_barcode( 'Shelf count' === $count['note'], 'Count request sanitizes note.' );

$lookup = SSW_Barcode_Admin::normalize_lookup_request( array( 'barcode' => ' 385 123 456 ' ) );
ssw_assert_barcode( '385123456' === $lookup['barcode'], 'Manual/scanner lookup uses shared barcode normalization.' );

fwrite( STDOUT, "PASS: barcode and count workflow\n" );
