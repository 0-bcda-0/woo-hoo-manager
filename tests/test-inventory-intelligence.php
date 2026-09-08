<?php
/**
 * Lightweight tests for approved inventory-intelligence formulas only.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }

function ssw_assert_intel( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-inventory-intelligence.php';
ssw_assert_intel( file_exists( $class_file ), 'class-ssw-inventory-intelligence.php must exist.' );
require_once $class_file;

$risk = SSW_Inventory_Intelligence::revenue_at_risk( 30, 20, 5, 12.50 );
ssw_assert_intel( 5.0 === $risk['shortage_units'], 'Revenue-at-risk shortage subtracts projected stock and incoming.' );
ssw_assert_intel( 62.50 === $risk['revenue_at_risk'], 'Revenue at risk multiplies shortage by realized price.' );
$risk_none = SSW_Inventory_Intelligence::revenue_at_risk( 10, 20, 0, 12.50 );
ssw_assert_intel( 0.0 === $risk_none['shortage_units'], 'No shortage yields zero risk.' );

$lost = SSW_Inventory_Intelligence::estimated_lost_sales( 1.5, 4, 10.00 );
ssw_assert_intel( 6.0 === $lost['estimated_lost_units'], 'Lost units use baseline velocity times actual OOS duration.' );
ssw_assert_intel( 60.0 === $lost['estimated_lost_revenue'], 'Lost revenue uses estimated units times price.' );

$health = SSW_Inventory_Intelligence::health_score( array(
	'availability' => 100,
	'excess_aging' => 80,
	'demand' => 60,
	'margin' => 40,
) );
ssw_assert_intel( 75.0 === $health['score'], 'Health score uses approved explainable components.' );
ssw_assert_intel( 'watch' === $health['status'], '75 health score is Watch.' );
$no_margin = SSW_Inventory_Intelligence::health_score( array(
	'availability' => 100,
	'excess_aging' => 80,
	'demand' => 60,
	'margin' => null,
) );
ssw_assert_intel( abs( 83.75 - $no_margin['score'] ) < 0.0001, 'Missing margin renormalizes remaining health weights.' );
ssw_assert_intel( 'healthy' === $no_margin['status'], 'Renormalized score maps to Healthy.' );

ssw_assert_intel( '0-30' === SSW_Inventory_Intelligence::aging_bucket( 12 ), '12 days maps to 0-30.' );
ssw_assert_intel( '31-60' === SSW_Inventory_Intelligence::aging_bucket( 45 ), '45 days maps to 31-60.' );
ssw_assert_intel( '61-90' === SSW_Inventory_Intelligence::aging_bucket( 75 ), '75 days maps to 61-90.' );
ssw_assert_intel( '91-180' === SSW_Inventory_Intelligence::aging_bucket( 120 ), '120 days maps to 91-180.' );
ssw_assert_intel( '180+' === SSW_Inventory_Intelligence::aging_bucket( 300 ), '300 days maps to 180+.' );

ssw_assert_intel( 2.5 === SSW_Inventory_Intelligence::gmroi( 500, 200 ), 'GMROI divides gross margin by average inventory cost.' );
ssw_assert_intel( null === SSW_Inventory_Intelligence::gmroi( 500, 0 ), 'Zero inventory cost yields unknown GMROI.' );

$pareto = SSW_Inventory_Intelligence::pareto( array( 50, 30, 20 ) );
ssw_assert_intel( 0.5 === $pareto[0]['cumulative_share'], 'Pareto first item cumulative share is actual contribution.' );
ssw_assert_intel( 0.8 === $pareto[1]['cumulative_share'], 'Pareto second item reaches actual 80%.' );
ssw_assert_intel( 1.0 === $pareto[2]['cumulative_share'], 'Pareto tail reaches 100%.' );

fwrite( STDOUT, "PASS: approved inventory intelligence\n" );
