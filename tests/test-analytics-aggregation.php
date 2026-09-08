<?php
/**
 * Lightweight analytics aggregation test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }

function ssw_assert_agg( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-sales-aggregator.php';
ssw_assert_agg( file_exists( $class_file ), 'class-ssw-sales-aggregator.php must exist.' );
require_once $class_file;

$facts = SSW_Sales_Aggregator::aggregate_facts( array(
	array( 'date' => '2026-09-01', 'product_id' => 10, 'variation_id' => 0, 'units' => 2, 'revenue' => '20.00' ),
	array( 'date' => '2026-09-01', 'product_id' => 10, 'variation_id' => 0, 'units' => 1, 'revenue' => '10.00' ),
	array( 'date' => '2026-09-01', 'product_id' => 10, 'variation_id' => 22, 'units' => 3, 'revenue' => '45.00' ),
	array( 'date' => '2026-09-01', 'product_id' => 10, 'variation_id' => 22, 'units' => -1, 'revenue' => '-15.00' ),
	array( 'date' => '2026-09-02', 'product_id' => 10, 'variation_id' => 0, 'units' => 4, 'revenue' => '40.00' ),
) );

ssw_assert_agg( isset( $facts['2026-09-01:10'] ), 'Simple product daily fact exists.' );
ssw_assert_agg( 3.0 === $facts['2026-09-01:10']['units'], 'Simple product units aggregate by date/product.' );
ssw_assert_agg( '30.00' === $facts['2026-09-01:10']['revenue'], 'Simple product revenue aggregates exactly to cents.' );
ssw_assert_agg( isset( $facts['2026-09-01:22'] ), 'Variation uses variation ID as analytics product identity.' );
ssw_assert_agg( 2.0 === $facts['2026-09-01:22']['units'], 'Refund-like negative quantity reduces daily units.' );
ssw_assert_agg( '30.00' === $facts['2026-09-01:22']['revenue'], 'Refund-like negative revenue reduces daily revenue.' );
ssw_assert_agg( 4.0 === $facts['2026-09-02:10']['units'], 'Different date remains separate fact.' );

ssw_assert_agg( SSW_Sales_Aggregator::eligible_order_status( 'processing' ), 'Processing orders count as demand.' );
ssw_assert_agg( SSW_Sales_Aggregator::eligible_order_status( 'completed' ), 'Completed orders count as demand.' );
ssw_assert_agg( ! SSW_Sales_Aggregator::eligible_order_status( 'cancelled' ), 'Cancelled orders do not count as demand.' );
ssw_assert_agg( ! SSW_Sales_Aggregator::eligible_order_status( 'failed' ), 'Failed orders do not count as demand.' );

$snapshot = SSW_Sales_Aggregator::normalize_inventory_snapshot( array(
	'date' => '2026-09-08',
	'product_id' => '22',
	'location_id' => '3',
	'quantity' => '12.5',
	'unit_cost' => '4.2500',
	'retail_price' => '9.99',
) );
ssw_assert_agg( 22 === $snapshot['product_id'], 'Snapshot product ID normalized.' );
ssw_assert_agg( 3 === $snapshot['location_id'], 'Snapshot location ID normalized.' );
ssw_assert_agg( 12.5 === $snapshot['quantity'], 'Snapshot quantity normalized.' );
ssw_assert_agg( '4.2500' === $snapshot['unit_cost'], 'Snapshot cost remains decimal string.' );
ssw_assert_agg( '9.9900' === $snapshot['retail_price'], 'Snapshot retail price normalized to four decimals.' );

fwrite( STDOUT, "PASS: analytics aggregation\n" );
