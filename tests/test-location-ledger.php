<?php
/**
 * Lightweight internal stock-ledger foundation test for #17/#18/#24.
 */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); } }
function ssw_assert_location( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$base = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/';
$class_file = $base . 'class-ssw-locations.php';
$ledger_file = $base . 'class-ssw-stock-ledger.php';
ssw_assert_location( file_exists( $class_file ), 'Internal Main-location service must exist.' );
ssw_assert_location( file_exists( $ledger_file ), 'Stock ledger must exist.' );
require_once $class_file;
require_once $ledger_file;
ssw_assert_location( 'main' === SSW_Locations::MAIN_CODE, 'Internal ledger uses one stable Main location.' );
$record_method = new ReflectionMethod( 'SSW_Stock_Ledger', 'record_movement' );
ssw_assert_location( $record_method->getNumberOfParameters() >= 2, 'Ledger can participate in an externally managed transaction.' );
$movement = SSW_Stock_Ledger::normalize_movement( array( 'product_id' => '42', 'location_id' => '1', 'delta' => '-4.5', 'type' => ' adjustment ', 'source' => ' manual ', 'note' => ' Count correction ' ) );
ssw_assert_location( 42 === $movement['product_id'], 'Movement product ID is normalized.' );
ssw_assert_location( 1 === $movement['location_id'], 'Movement location ID is normalized.' );
ssw_assert_location( -4.5 === $movement['delta'], 'Movement delta preserves signed decimal quantity.' );
ssw_assert_location( 'adjustment' === $movement['type'], 'Movement type is normalized.' );
ssw_assert_location( 'manual' === $movement['source'], 'Movement source is normalized.' );
ssw_assert_location( 'Count correction' === $movement['note'], 'Movement note is sanitized.' );
fwrite( STDOUT, "PASS: stock ledger foundation\n" );
