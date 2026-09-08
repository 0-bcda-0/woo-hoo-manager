<?php
/**
 * Lightweight location/ledger domain test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' );
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		$value = strtolower( (string) $value );
		return preg_replace( '/[^a-z0-9_\-]/', '', $value );
	}
}

function ssw_assert_location( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/';
$class_file = $base . 'class-ssw-locations.php';
$ledger_file = $base . 'class-ssw-stock-ledger.php';
$admin_file = $base . 'class-ssw-location-admin.php';
ssw_assert_location( file_exists( $class_file ), 'class-ssw-locations.php must exist.' );
ssw_assert_location( file_exists( $ledger_file ), 'class-ssw-stock-ledger.php must exist.' );
require_once $class_file;
require_once $ledger_file;

$location = SSW_Locations::normalize_location( array(
	'name'        => '  Zagreb Store  ',
	'code'        => ' Zagreb Store #1 ',
	'is_sellable' => '1',
	'active'      => '1',
) );
ssw_assert_location( 'Zagreb Store' === $location['name'], 'Location name is trimmed and sanitized.' );
ssw_assert_location( 'zagreb-store-1' === $location['code'], 'Location code is normalized to a stable key.' );
ssw_assert_location( 1 === $location['is_sellable'], 'Sellable flag is normalized.' );
ssw_assert_location( 1 === $location['active'], 'Active flag is normalized.' );

ssw_assert_location( 12.0 === SSW_Locations::aggregate_sellable( array(
	array( 'quantity' => 5, 'is_sellable' => 1, 'active' => 1 ),
	array( 'quantity' => 7, 'is_sellable' => 1, 'active' => 1 ),
	array( 'quantity' => 99, 'is_sellable' => 0, 'active' => 1 ),
	array( 'quantity' => 50, 'is_sellable' => 1, 'active' => 0 ),
) ), 'Only active sellable locations contribute to Woo aggregate.' );

$movement = SSW_Stock_Ledger::normalize_movement( array(
	'product_id'  => '42',
	'location_id' => '3',
	'delta'       => '-4.5',
	'type'        => ' adjustment ',
	'source'      => ' manual ',
	'note'        => ' Count correction ',
) );
ssw_assert_location( 42 === $movement['product_id'], 'Movement product ID is normalized.' );
ssw_assert_location( 3 === $movement['location_id'], 'Movement location ID is normalized.' );
ssw_assert_location( -4.5 === $movement['delta'], 'Movement delta preserves signed decimal quantity.' );
ssw_assert_location( 'adjustment' === $movement['type'], 'Movement type is normalized.' );
ssw_assert_location( 'manual' === $movement['source'], 'Movement source is normalized.' );
ssw_assert_location( 'Count correction' === $movement['note'], 'Movement note is sanitized.' );

$transfer = SSW_Stock_Ledger::paired_transfer_movements( 42, 2, 5, 6, 'test' );
ssw_assert_location( -6.0 === $transfer[0]['delta'], 'Transfer out is negative.' );
ssw_assert_location( 6.0 === $transfer[1]['delta'], 'Transfer in is positive.' );
ssw_assert_location( 0.0 === $transfer[0]['delta'] + $transfer[1]['delta'], 'Transfer does not change aggregate stock.' );

ssw_assert_location( file_exists( $admin_file ), 'class-ssw-location-admin.php must exist.' );
require_once $admin_file;
$adjustment = SSW_Location_Admin::normalize_adjustment_request( array(
	'product_id' => '42',
	'location_id' => '3',
	'delta' => '-2,5',
	'note' => ' Cycle count ',
) );
ssw_assert_location( 42 === $adjustment['product_id'], 'Admin adjustment product ID is normalized.' );
ssw_assert_location( 3 === $adjustment['location_id'], 'Admin adjustment location ID is normalized.' );
ssw_assert_location( -2.5 === $adjustment['delta'], 'Admin adjustment accepts decimal comma.' );
ssw_assert_location( 'Cycle count' === $adjustment['note'], 'Admin adjustment note is sanitized.' );

fwrite( STDOUT, "PASS: location ledger behavior\n" );
