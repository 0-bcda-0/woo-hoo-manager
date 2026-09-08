<?php
/**
 * Approved #21 Bundles / Kits behavior.
 */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
function ssw_assert_bundle_kit( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-bundle-kits.php';
ssw_assert_bundle_kit( file_exists( $file ), 'Bundle Kits service must exist.' );
require_once $file;
$components = SSW_Bundle_Kits::normalize_components( array(
	array( 'product_id' => '11', 'quantity' => '2' ),
	array( 'product_id' => '22', 'quantity' => '1' ),
	array( 'product_id' => '33', 'quantity' => '1' ),
) );
ssw_assert_bundle_kit( 3 === count( $components ), 'Three valid components remain.' );
ssw_assert_bundle_kit( 2.0 === $components[0]['quantity'], 'Component quantity is normalized.' );
$available = SSW_Bundle_Kits::available_units( $components, array( 11 => 20, 22 => 8, 33 => 12 ) );
ssw_assert_bundle_kit( 8 === $available, 'Bundle availability is limited by the scarcest component.' );
$deltas = SSW_Bundle_Kits::component_deltas( $components, 3 );
ssw_assert_bundle_kit( -6.0 === $deltas[11], 'Selling three bundles consumes six units of a 2x component.' );
ssw_assert_bundle_kit( -3.0 === $deltas[22], 'Selling three bundles consumes three units of a 1x component.' );
$restock = SSW_Bundle_Kits::component_deltas( $components, -2 );
ssw_assert_bundle_kit( 4.0 === $restock[11], 'Restoring two bundles restores four units of 2x component.' );
fwrite( STDOUT, "PASS: approved bundle kits\n" );
