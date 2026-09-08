<?php
/**
 * Lightweight tests for approved Smart Alerts behavior.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $value ) { return json_encode( $value ); } }

function ssw_assert_alert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-alerts.php';
ssw_assert_alert( file_exists( $class_file ), 'Smart Alerts class must exist.' );
require_once $class_file;

$key1 = SSW_Alerts::dedupe_key( 'predicted_stockout', array( 'product_id' => 55, 'location_id' => 2 ) );
$key2 = SSW_Alerts::dedupe_key( 'predicted_stockout', array( 'location_id' => 2, 'product_id' => 55 ) );
ssw_assert_alert( $key1 === $key2, 'Dedupe key is stable regardless of context key ordering.' );
ssw_assert_alert( 64 === strlen( $key1 ), 'Dedupe key is a SHA-256 hash.' );

ssw_assert_alert( 'critical' === SSW_Alerts::normalize_severity( 'CRITICAL' ), 'Critical severity is normalized.' );
ssw_assert_alert( 'warning' === SSW_Alerts::normalize_severity( 'unexpected' ), 'Unknown severity falls back to warning.' );
ssw_assert_alert( 'open' === SSW_Alerts::normalize_state( 'OPEN' ), 'Open state is normalized.' );
ssw_assert_alert( 'open' === SSW_Alerts::normalize_state( 'garbage' ), 'Unknown state falls back to open.' );

$stockout = SSW_Alerts::evaluate_product( array(
	'product_id' => 55,
	'available_quantity' => 4,
	'days_of_stock' => 5,
	'lead_time_days' => 10,
	'incoming_quantity' => 0,
	'health_score' => 35,
	'days_since_last_sale' => 20,
	'bundle_opportunity' => false,
) );
ssw_assert_alert( in_array( 'predicted_stockout', array_column( $stockout, 'type' ), true ), 'Predicted stockout alert fires when cover is shorter than replenishment lead time.' );
ssw_assert_alert( in_array( 'low_health', array_column( $stockout, 'type' ), true ), 'Low health alert uses approved Inventory Health Score.' );

$oos = SSW_Alerts::evaluate_product( array(
	'product_id' => 77,
	'available_quantity' => 0,
	'days_of_stock' => 0,
	'lead_time_days' => 0,
	'incoming_quantity' => 0,
	'health_score' => 70,
	'days_since_last_sale' => 200,
	'bundle_opportunity' => true,
) );
$types = array_column( $oos, 'type' );
ssw_assert_alert( in_array( 'actual_stockout', $types, true ), 'Actual stockout is a critical alert.' );
ssw_assert_alert( in_array( 'dead_stock', $types, true ), 'Dead-stock escalation uses approved dead/slow stock intelligence.' );
ssw_assert_alert( in_array( 'bundle_opportunity', $types, true ), 'Approved bundle opportunity can surface as an Opportunity alert.' );

fwrite( STDOUT, "PASS: approved smart alerts\n" );
