<?php
/**
 * Lightweight tests for approved inventory-intelligence formulas only.
 */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
function ssw_assert_intel( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-inventory-intelligence.php';
ssw_assert_intel( file_exists( $class_file ), 'class-ssw-inventory-intelligence.php must exist.' );
require_once $class_file;
$risk = SSW_Inventory_Intelligence::revenue_at_risk( 30, 20, 5, 12.50 );
ssw_assert_intel( 5.0 === $risk['shortage_units'], 'Revenue-at-risk shortage subtracts projected stock and incoming.' );
ssw_assert_intel( 62.50 === $risk['revenue_at_risk'], 'Revenue at risk multiplies shortage by realized price.' );
ssw_assert_intel( 0.0 === SSW_Inventory_Intelligence::revenue_at_risk( 10, 20, 0, 12.50 )['shortage_units'], 'No shortage yields zero risk.' );
$lost = SSW_Inventory_Intelligence::estimated_lost_sales( 1.5, 4, 10.00 );
ssw_assert_intel( 6.0 === $lost['estimated_lost_units'], 'Lost units use baseline velocity times actual OOS duration.' );
ssw_assert_intel( 60.0 === $lost['estimated_lost_revenue'], 'Lost revenue uses estimated units times price.' );
ssw_assert_intel( true === $lost['is_estimate'], 'Lost sales are explicitly labeled estimated.' );
$health = SSW_Inventory_Intelligence::health_score( array( 'availability' => 100, 'excess_aging' => 80, 'demand' => 60 ) );
ssw_assert_intel( 82.0 === $health['score'], 'Health score uses only approved inventory components.' );
ssw_assert_intel( 'healthy' === $health['status'], '82 health score is Healthy.' );
$partial = SSW_Inventory_Intelligence::health_score( array( 'availability' => 80, 'excess_aging' => null, 'demand' => 40 ) );
ssw_assert_intel( abs( 62.8571 - $partial['score'] ) < 0.001, 'Missing component renormalizes remaining approved weights.' );
ssw_assert_intel( 'healthy' === SSW_Inventory_Intelligence::aging_bucket( 12 ), '12 days is healthy.' );
ssw_assert_intel( 'slow' === SSW_Inventory_Intelligence::aging_bucket( 45 ), '45 days is slow.' );
ssw_assert_intel( 'very_slow' === SSW_Inventory_Intelligence::aging_bucket( 75 ), '75 days is very slow.' );
ssw_assert_intel( 'dead' === SSW_Inventory_Intelligence::aging_bucket( 120 ), '120 days is dead.' );
ssw_assert_intel( 'critical_dead' === SSW_Inventory_Intelligence::aging_bucket( 300 ), '300 days is critical dead stock.' );
fwrite( STDOUT, "PASS: approved inventory intelligence\n" );
