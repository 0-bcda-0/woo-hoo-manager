<?php
/**
 * Lightweight tests for approved #34 90-day purchasing/cash forecast.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_plan( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-purchasing-planner.php';
ssw_assert_plan( file_exists( $class_file ), 'Purchasing planner class must exist.' );
require_once $class_file;

$plan = SSW_Purchasing_Planner::plan_product( array(
	'as_of_date' => '2026-09-08',
	'daily_velocity' => 2.0,
	'on_hand' => 20,
	'incoming' => 0,
	'lead_time_days' => 10,
	'safety_stock_days' => 5,
	'moq' => 6,
	'pack_size' => 6,
	'unit_cost' => 4.50,
	'currency' => 'EUR',
	'horizon_days' => 90,
) );
ssw_assert_plan( '2026-09-08' === $plan['suggested_order_date'], 'Order is due now when current cover is shorter than lead time plus safety stock.' );
ssw_assert_plan( 192.0 === $plan['suggested_order_quantity'], '90-day requirement is rounded up to MOQ/pack-size constraints.' );
ssw_assert_plan( 864.0 === $plan['expected_cash'], 'Expected cash equals suggested quantity times unit cost.' );
ssw_assert_plan( 'EUR' === $plan['currency'], 'Currency is preserved.' );

$future = SSW_Purchasing_Planner::plan_product( array(
	'as_of_date' => '2026-09-08',
	'daily_velocity' => 1.0,
	'on_hand' => 60,
	'incoming' => 0,
	'lead_time_days' => 10,
	'safety_stock_days' => 5,
	'moq' => 1,
	'pack_size' => 1,
	'unit_cost' => 3.00,
	'currency' => 'EUR',
	'horizon_days' => 90,
) );
ssw_assert_plan( '2026-10-23' === $future['suggested_order_date'], 'Suggested order date occurs when projected cover reaches lead time plus safety stock.' );
ssw_assert_plan( 90.0 === $future['suggested_order_quantity'], 'Future reorder quantity covers the 90-day planning horizon plus lead time and safety stock from the order date.' );

$none = SSW_Purchasing_Planner::plan_product( array(
	'as_of_date' => '2026-09-08',
	'daily_velocity' => 0,
	'on_hand' => 100,
	'incoming' => 0,
	'lead_time_days' => 10,
	'safety_stock_days' => 5,
	'moq' => 1,
	'pack_size' => 1,
	'unit_cost' => 3.00,
	'currency' => 'EUR',
	'horizon_days' => 90,
) );
ssw_assert_plan( null === $none['suggested_order_date'], 'No velocity means no fabricated reorder date.' );
ssw_assert_plan( 0.0 === $none['suggested_order_quantity'], 'No velocity means no fabricated purchase quantity.' );

$grouped = SSW_Purchasing_Planner::group_cash( array(
	array( 'supplier_id' => 5, 'order_date' => '2026-09-10', 'expected_cash' => 100, 'currency' => 'EUR' ),
	array( 'supplier_id' => 5, 'order_date' => '2026-09-12', 'expected_cash' => 50, 'currency' => 'EUR' ),
	array( 'supplier_id' => 5, 'order_date' => '2026-09-12', 'expected_cash' => 75, 'currency' => 'USD' ),
) );
ssw_assert_plan( 150.0 === $grouped['by_supplier_currency']['5:EUR'], 'Supplier EUR cash is grouped without mixing currencies.' );
ssw_assert_plan( 75.0 === $grouped['by_supplier_currency']['5:USD'], 'Supplier USD cash remains separate.' );

fwrite( STDOUT, "PASS: approved 90-day purchasing planner\n" );
