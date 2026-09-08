<?php
/**
 * Lightweight test for approved evidence-based bundle recommendations.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_bundle( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-bundle-opportunities.php';
ssw_assert_bundle( file_exists( $class_file ), 'Bundle opportunity class must exist.' );
require_once $class_file;

$pairs = SSW_Bundle_Opportunities::product_pairs( array( 3, 2, 2, 1 ) );
ssw_assert_bundle( array( array( 1, 2 ), array( 1, 3 ), array( 2, 3 ) ) === $pairs, 'One order yields unique normalized product pairs without self-pairs.' );

$evidence = SSW_Bundle_Opportunities::evidence( 12, 40, 30, 100 );
ssw_assert_bundle( abs( 0.12 - $evidence['support'] ) < 0.00001, 'Support is pair orders divided by total analysed orders.' );
ssw_assert_bundle( abs( 0.30 - $evidence['attach_rate_a'] ) < 0.00001, 'Attach rate A is pair orders divided by A orders.' );
ssw_assert_bundle( abs( 0.40 - $evidence['attach_rate_b'] ) < 0.00001, 'Attach rate B is pair orders divided by B orders.' );

$score = SSW_Bundle_Opportunities::score_candidate( array(
	'pair_orders' => 12,
	'orders_a' => 40,
	'orders_b' => 30,
	'total_orders' => 100,
	'slow_product' => true,
	'counterpart_healthy' => true,
) );
ssw_assert_bundle( $score > 0, 'Slow/dead + healthy counterpart with evidence receives a positive score.' );

$rejected = SSW_Bundle_Opportunities::score_candidate( array(
	'pair_orders' => 1,
	'orders_a' => 40,
	'orders_b' => 30,
	'total_orders' => 100,
	'slow_product' => true,
	'counterpart_healthy' => true,
) );
ssw_assert_bundle( 0.0 === $rejected, 'Weak one-off co-purchase evidence is rejected.' );

$ceiling = SSW_Bundle_Opportunities::discount_ceiling( 40.00, 10.00, 8.00, 0.35 );
ssw_assert_bundle( abs( 30.7692307692 - $ceiling['max_discount_percent'] ) < 0.0001, 'Discount ceiling preserves configured gross-margin floor.' );
ssw_assert_bundle( null === SSW_Bundle_Opportunities::discount_ceiling( 40.00, null, 8.00, 0.35 ), 'Missing cost suppresses discount advice.' );

fwrite( STDOUT, "PASS: approved bundle recommendations\n" );
