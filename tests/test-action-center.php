<?php
/**
 * Lightweight tests for approved What Changed + Today Action Center behavior.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_action( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-action-center.php';
ssw_assert_action( file_exists( $class_file ), 'Action Center class must exist.' );
require_once $class_file;

$delta = SSW_Action_Center::delta( 120, 100 );
ssw_assert_action( 20.0 === $delta['absolute'], 'Absolute delta is current minus previous.' );
ssw_assert_action( 20.0 === $delta['percent'], 'Percent delta uses previous period when non-zero.' );
$from_zero = SSW_Action_Center::delta( 10, 0 );
ssw_assert_action( null === $from_zero['percent'], 'Percent delta is unknown rather than fabricated when previous is zero.' );

$critical = SSW_Action_Center::priority_score( array( 'severity' => 'critical', 'urgency' => 1.0, 'financial_impact' => 500 ) );
$warning = SSW_Action_Center::priority_score( array( 'severity' => 'warning', 'urgency' => 0.5, 'financial_impact' => 100 ) );
ssw_assert_action( $critical > $warning, 'Critical high-impact action ranks above a routine warning.' );
ssw_assert_action( $critical <= 100.0 && $critical >= 0.0, 'Priority score stays on a 0-100 scale.' );

$action = SSW_Action_Center::from_alert( array(
	'id' => 4,
	'type' => 'predicted_stockout',
	'severity' => 'critical',
	'product_id' => 55,
	'context' => array( 'days_of_stock' => 5, 'lead_time_days' => 10, 'revenue_at_risk' => 250 ),
) );
ssw_assert_action( 'Reorder at-risk product' === $action['title'], 'Predicted stockout maps to an approved reorder action.' );
ssw_assert_action( '' !== $action['why_now'], 'Action explains why it matters now.' );
ssw_assert_action( '' !== $action['recommended_action'], 'Action tells the manager what can be done.' );
ssw_assert_action( 250.0 === $action['estimated_impact'], 'Action carries explicit estimated impact evidence.' );

fwrite( STDOUT, "PASS: approved operations cockpit\n" );
