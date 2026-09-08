<?php
/**
 * Lightweight deterministic demand forecasting test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_forecast( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-demand-forecast.php';
ssw_assert_forecast( file_exists( $class_file ), 'class-ssw-demand-forecast.php must exist.' );
require_once $class_file;

$weighted = SSW_Demand_Forecast::weighted_velocity( array( '30d' => 2.0, '90d' => 1.0, '365d' => 0.5 ) );
ssw_assert_forecast( abs( 1.55 - $weighted ) < 0.00001, 'Weighted velocity uses 60/30/10 baseline.' );

$renormalized = SSW_Demand_Forecast::weighted_velocity( array( '30d' => 2.0, '90d' => 1.0, '365d' => null ) );
ssw_assert_forecast( abs( 1.6666666667 - $renormalized ) < 0.00001, 'Missing window renormalizes remaining weights.' );

ssw_assert_forecast( 0.0 === SSW_Demand_Forecast::weighted_velocity( array( '30d' => null, '90d' => null, '365d' => null ) ), 'No history returns zero velocity.' );
ssw_assert_forecast( 10.0 === SSW_Demand_Forecast::days_of_stock( 15, 1.5 ), 'Days of stock divides available quantity by daily velocity.' );
ssw_assert_forecast( null === SSW_Demand_Forecast::days_of_stock( 15, 0 ), 'Zero velocity does not fabricate stock-cover days.' );
ssw_assert_forecast( '2026-09-18' === SSW_Demand_Forecast::projected_stockout_date( '2026-09-08', 10 ), 'Projected stockout adds stock-cover days.' );
ssw_assert_forecast( null === SSW_Demand_Forecast::projected_stockout_date( '2026-09-08', null ), 'Unknown stock cover yields unknown stockout date.' );

$stats = SSW_Demand_Forecast::demand_variability( array( 1, 1, 1, 1 ) );
ssw_assert_forecast( 1.0 === $stats['mean'], 'Demand mean is calculated.' );
ssw_assert_forecast( 0.0 === $stats['cv'], 'Constant demand has zero coefficient of variation.' );
$intermittent = SSW_Demand_Forecast::demand_variability( array( 0, 0, 4, 0 ) );
ssw_assert_forecast( $intermittent['cv'] > 1.0, 'Intermittent demand has high variability.' );

ssw_assert_forecast( 'insufficient_data' === SSW_Demand_Forecast::confidence_state( 10, 3 ), 'Short history is insufficient data.' );
ssw_assert_forecast( 'low_confidence' === SSW_Demand_Forecast::confidence_state( 45, 8 ), 'Limited history is low confidence.' );
ssw_assert_forecast( 'normal' === SSW_Demand_Forecast::confidence_state( 120, 20 ), 'Medium history is normal confidence.' );
ssw_assert_forecast( 'high_confidence' === SSW_Demand_Forecast::confidence_state( 365, 80 ), 'Long active history can be high confidence.' );

ssw_assert_forecast( 21.0 === SSW_Demand_Forecast::forecast_quantity( 1.5, 14 ), 'Forecast quantity is deterministic velocity times horizon.' );
ssw_assert_forecast( 0.0 === SSW_Demand_Forecast::forecast_quantity( -1, 14 ), 'Negative velocity is clamped to zero.' );

fwrite( STDOUT, "PASS: demand forecasting\n" );
