<?php
/** Lightweight tests for approved #35 What-if simulator. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
function ssw_assert_scenario( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
require_once dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-purchasing-planner.php';
$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-what-if.php';
ssw_assert_scenario( file_exists( $class_file ), 'What-if simulator class must exist.' );
require_once $class_file;

$baseline = array(
	'as_of_date' => '2026-09-08', 'daily_velocity' => 2.0, 'on_hand' => 30, 'incoming' => 0,
	'lead_time_days' => 10, 'safety_stock_days' => 5, 'moq' => 6, 'pack_size' => 6,
	'unit_cost' => 4.0, 'currency' => 'EUR', 'avg_realized_price' => 12.0, 'horizon_days' => 90,
);
$original = $baseline;
$result = SSW_What_If::simulate_product( $baseline, array(
	'demand_percent' => 25,
	'lead_time_days_delta' => 5,
	'safety_stock_days' => 7,
	'reorder_delay_days' => 3,
	'budget_cap' => 700,
) );
ssw_assert_scenario( $baseline === $original, 'Simulation never mutates baseline input.' );
ssw_assert_scenario( 2.5 === $result['scenario']['daily_velocity'], 'Demand override changes scenario velocity only.' );
ssw_assert_scenario( 15 === $result['scenario']['lead_time_days'], 'Lead-time delta is applied to scenario only.' );
ssw_assert_scenario( 7 === $result['scenario']['safety_stock_days'], 'Scenario safety-stock days are applied.' );
ssw_assert_scenario( $result['scenario_plan']['suggested_order_quantity'] >= $result['baseline_plan']['suggested_order_quantity'], 'Higher demand/lead time cannot reduce planned quantity in this fixture.' );
ssw_assert_scenario( $result['scenario_revenue_at_risk'] >= $result['baseline_revenue_at_risk'], 'Higher demand and delay do not reduce Revenue at Risk in this fixture.' );
ssw_assert_scenario( 'over_budget' === $result['budget_pressure']['status'], 'Scenario cash above temporary cap is flagged.' );
ssw_assert_scenario( 3 === $result['reorder_delay_days'], 'Reorder delay is visible in scenario evidence.' );

fwrite( STDOUT, "PASS: approved what-if simulator\n" );
