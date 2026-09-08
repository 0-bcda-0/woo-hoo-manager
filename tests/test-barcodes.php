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

fwrite( STDOUT, "PASS: barcode and count workflow\n" );
