<?php
/** Lightweight tests for approved #37 Weekly Executive Report. */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
function ssw_assert_weekly( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-weekly-report.php';
ssw_assert_weekly( file_exists( $class_file ), 'Weekly report class must exist.' );
require_once $class_file;

$payload = SSW_Weekly_Report::build_payload(
	array(
		'period_start' => '2026-09-01', 'period_end' => '2026-09-07',
		'revenue' => 1200, 'orders' => 60, 'units' => 90,
		'revenue_at_risk' => 400, 'estimated_lost_sales' => 150,
		'stockouts' => 3, 'dead_slow_count' => 8, 'open_po_count' => 4,
		'incoming_units' => 250, 'health_average' => 74,
		'critical_alerts' => 2, 'warning_alerts' => 5, 'bundle_opportunities' => 3,
		'purchasing_cash' => array( 'EUR' => 3500, 'USD' => 400 ),
	),
	array( 'revenue' => 1000, 'orders' => 50, 'units' => 100, 'health_average' => 70 )
);
ssw_assert_weekly( 20.0 === $payload['week_over_week']['revenue_percent'], 'Revenue WoW is deterministic.' );
ssw_assert_weekly( 20.0 === $payload['week_over_week']['orders_percent'], 'Orders WoW is deterministic.' );
ssw_assert_weekly( -10.0 === $payload['week_over_week']['units_percent'], 'Units WoW preserves negative change.' );
ssw_assert_weekly( 4.0 === $payload['week_over_week']['health_points'], 'Health trend uses score points, not percentage.' );
ssw_assert_weekly( 400.0 === $payload['risk']['revenue_at_risk'], 'Approved Revenue at Risk is included.' );
ssw_assert_weekly( 150.0 === $payload['risk']['estimated_lost_sales'], 'Approved Estimated Lost Sales is included.' );
ssw_assert_weekly( 3500.0 === $payload['purchasing']['cash_by_currency']['EUR'], 'Purchasing cash remains separated by currency.' );
ssw_assert_weekly( ! isset( $payload['customers'] ), 'Weekly payload contains no customer PII section.' );

$zero = SSW_Weekly_Report::percent_change( 10, 0 );
ssw_assert_weekly( null === $zero, 'No fabricated percentage is returned when previous value is zero.' );

fwrite( STDOUT, "PASS: approved weekly executive report\n" );
