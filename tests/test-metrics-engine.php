<?php
/**
 * Lightweight product-metrics engine test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_metrics( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$forecast_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-demand-forecast.php';
$engine_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-metrics-engine.php';
ssw_assert_metrics( file_exists( $forecast_file ), 'Demand forecast class must exist.' );
ssw_assert_metrics( file_exists( $engine_file ), 'Metrics engine class must exist.' );
require_once $forecast_file;
require_once $engine_file;

$daily = array();
for ( $i = 0; $i < 30; $i++ ) { $daily[] = array( 'date' => date( 'Y-m-d', strtotime( '2026-09-08 -' . $i . ' days' ) ), 'units' => 2.0, 'revenue' => '20.00' ); }
$windows = SSW_Metrics_Engine::calculate_windows( $daily, '2026-09-08', 30 );
ssw_assert_metrics( 14.0 === $windows['units_7d'], '7-day units include seven days ending on metric date.' );
ssw_assert_metrics( 60.0 === $windows['units_30d'], '30-day units aggregate correctly.' );
ssw_assert_metrics( null === $windows['units_90d'], '90-day window is unavailable when only 30 days of history exist.' );
ssw_assert_metrics( 2.0 === $windows['velocity_7d'], '7-day velocity is units divided by window days.' );
ssw_assert_metrics( 2.0 === $windows['velocity_30d'], '30-day velocity is units divided by window days.' );
ssw_assert_metrics( null === $windows['velocity_90d'], 'Missing 90-day history yields null velocity.' );

$weekly = SSW_Metrics_Engine::weekly_series( $daily, '2026-09-08', 4 );
ssw_assert_metrics( 4 === count( $weekly ), 'Weekly demand returns requested number of buckets.' );
ssw_assert_metrics( 14.0 === $weekly[3], 'Most recent complete 7-day bucket contains expected units.' );

$metric = SSW_Metrics_Engine::build_metric( array(
	'as_of_date' => '2026-09-08',
	'daily_rows' => $daily,
	'available_quantity' => 20,
	'history_days' => 30,
	'active_sales_days' => 30,
	'last_sale_date' => '2026-09-08',
) );
ssw_assert_metrics( abs( 2.0 - $metric['weighted_velocity'] ) < 0.00001, 'Metric renormalizes to available 30-day velocity.' );
ssw_assert_metrics( 10.0 === $metric['days_of_stock'], 'Metric computes stock cover.' );
ssw_assert_metrics( '2026-09-18' === $metric['projected_stockout_date'], 'Metric computes stockout date.' );
ssw_assert_metrics( 'low_confidence' === $metric['confidence_state'], '30-day history with active sales is low confidence.' );

$forecasts = SSW_Metrics_Engine::build_forecasts( 55, $metric, array( 7, 14, 30 ) );
ssw_assert_metrics( 3 === count( $forecasts ), 'Forecast snapshots are produced for each horizon.' );
ssw_assert_metrics( 14.0 === $forecasts[0]['forecast_qty'], '7-day forecast uses weighted velocity.' );
ssw_assert_metrics( '2026-09-22' === $forecasts[1]['horizon_date'], '14-day horizon date is deterministic.' );
ssw_assert_metrics( 'weighted_velocity_v1' === $forecasts[0]['model_version'], 'Forecast model is explicitly versioned.' );
ssw_assert_metrics( 64 === strlen( $forecasts[0]['inputs_hash'] ), 'Forecast stores SHA-256 inputs hash.' );

fwrite( STDOUT, "PASS: metrics engine\n" );
