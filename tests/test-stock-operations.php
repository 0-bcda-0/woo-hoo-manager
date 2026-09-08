<?php
/**
 * Lightweight tests for approved #17 Stock Adjustments and #18 Stock Count.
 */
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $v ) ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); } }
function ssw_assert_stock_ops( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } }
$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-stock-operations-admin.php';
ssw_assert_stock_ops( file_exists( $class_file ), 'Stock Operations admin class must exist.' );
require_once $class_file;
$adjust = SSW_Stock_Operations_Admin::normalize_adjustment( array( 'product_id' => '42', 'delta' => '+24', 'reason' => 'damaged', 'note' => '  box damaged  ' ) );
ssw_assert_stock_ops( 42 === $adjust['product_id'], 'Adjustment normalizes product ID.' );
ssw_assert_stock_ops( 24.0 === $adjust['delta'], 'Positive adjustment is preserved.' );
ssw_assert_stock_ops( 'damaged' === $adjust['reason'], 'Approved reason is preserved.' );
$adjust_bad = SSW_Stock_Operations_Admin::normalize_adjustment( array( 'product_id' => '42', 'delta' => '-3', 'reason' => 'made_up_reason' ) );
ssw_assert_stock_ops( -3.0 === $adjust_bad['delta'], 'Negative adjustment is preserved.' );
ssw_assert_stock_ops( 'other' === $adjust_bad['reason'], 'Unknown reason falls back to other.' );
$count = SSW_Stock_Operations_Admin::normalize_count( array( 'product_id' => 9, 'counted_quantity' => '17,5' ) );
ssw_assert_stock_ops( 9 === $count['product_id'], 'Count normalizes product ID.' );
ssw_assert_stock_ops( 17.5 === $count['counted_quantity'], 'Count supports decimal comma.' );
$count_negative = SSW_Stock_Operations_Admin::normalize_count( array( 'product_id' => 9, 'counted_quantity' => '-5' ) );
ssw_assert_stock_ops( 0.0 === $count_negative['counted_quantity'], 'Physical count cannot be negative.' );
fwrite( STDOUT, "PASS: approved stock operations\n" );
