<?php
/**
 * Lightweight purchase-order domain test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); } }

function ssw_assert_po( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$base = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/';
$class_file = $base . 'class-ssw-purchase-orders.php';
$admin_file = $base . 'class-ssw-purchase-order-admin.php';
ssw_assert_po( file_exists( $class_file ), 'class-ssw-purchase-orders.php must exist.' );
require_once $class_file;

ssw_assert_po( SSW_Purchase_Orders::can_transition( 'draft', 'approved' ), 'Draft can be approved.' );
ssw_assert_po( SSW_Purchase_Orders::can_transition( 'approved', 'ordered' ), 'Approved can be ordered.' );
ssw_assert_po( SSW_Purchase_Orders::can_transition( 'ordered', 'shipped' ), 'Ordered can be shipped.' );
ssw_assert_po( SSW_Purchase_Orders::can_transition( 'shipped', 'partially_received' ), 'Shipped can become partially received.' );
ssw_assert_po( SSW_Purchase_Orders::can_transition( 'partially_received', 'received' ), 'Partial receipt can become received.' );
ssw_assert_po( SSW_Purchase_Orders::can_transition( 'draft', 'cancelled' ), 'Draft can be cancelled.' );
ssw_assert_po( ! SSW_Purchase_Orders::can_transition( 'received', 'ordered' ), 'Received cannot move backwards to ordered.' );
ssw_assert_po( ! SSW_Purchase_Orders::can_transition( 'cancelled', 'approved' ), 'Cancelled is terminal.' );

ssw_assert_po( 6.0 === SSW_Purchase_Orders::remaining_quantity( 10, 4 ), 'Remaining quantity is ordered minus received.' );
ssw_assert_po( 0.0 === SSW_Purchase_Orders::remaining_quantity( 10, 12 ), 'Remaining quantity never becomes negative.' );
ssw_assert_po( 'partially_received' === SSW_Purchase_Orders::derive_receipt_status( 10, 4 ), 'Partial quantity derives partial status.' );
ssw_assert_po( 'received' === SSW_Purchase_Orders::derive_receipt_status( 10, 10 ), 'Fully received quantity derives received status.' );
ssw_assert_po( 'ordered' === SSW_Purchase_Orders::derive_receipt_status( 10, 0 ), 'No receipt leaves PO ordered.' );

$key1 = SSW_Purchase_Orders::receipt_key( 55, 'scanner-session-123' );
$key2 = SSW_Purchase_Orders::receipt_key( 55, 'scanner-session-123' );
$key3 = SSW_Purchase_Orders::receipt_key( 55, 'scanner-session-456' );
ssw_assert_po( $key1 === $key2, 'Same receipt request produces same idempotency key.' );
ssw_assert_po( $key1 !== $key3, 'Different receipt request produces different idempotency key.' );

$totals = SSW_Purchase_Orders::calculate_totals( array(
	array( 'quantity' => 12, 'unit_cost' => '3.50' ),
	array( 'quantity' => 4, 'unit_cost' => '10.00' ),
) );
ssw_assert_po( '82.00' === $totals['subtotal'], 'PO subtotal is decimal-safe for standard two-decimal costs.' );
ssw_assert_po( 16.0 === $totals['units'], 'PO total ordered units are calculated.' );

ssw_assert_po( file_exists( $admin_file ), 'class-ssw-purchase-order-admin.php must exist.' );
require_once $admin_file;
$rows = SSW_Purchase_Order_Admin::normalize_item_rows( array(
	'product_id' => array( '101', '', '202' ),
	'quantity' => array( '12', '9', '2,5' ),
	'unit_cost' => array( '3,50', '99', '10.00' ),
	'supplier_sku' => array( 'A-1', 'ignored', 'B-2' ),
) );
ssw_assert_po( 2 === count( $rows ), 'Blank product rows are ignored.' );
ssw_assert_po( 101 === $rows[0]['product_id'], 'First product ID maps correctly.' );
ssw_assert_po( 12.0 === $rows[0]['quantity'], 'First quantity maps correctly.' );
ssw_assert_po( '3.50' === $rows[0]['unit_cost'], 'Decimal comma cost is normalized.' );
ssw_assert_po( 2.5 === $rows[1]['quantity'], 'Decimal comma quantity is normalized.' );

$receipt = SSW_Purchase_Order_Admin::normalize_receipt_lines( array( '15' => '3', '16' => '0', '17' => '1,5' ) );
ssw_assert_po( 2 === count( $receipt ), 'Zero receipt quantities are ignored.' );
ssw_assert_po( 15 === $receipt[0]['po_item_id'] && 3.0 === $receipt[0]['quantity'], 'Receipt line maps item and quantity.' );
ssw_assert_po( 17 === $receipt[1]['po_item_id'] && 1.5 === $receipt[1]['quantity'], 'Receipt accepts decimal comma.' );

fwrite( STDOUT, "PASS: purchase order workflow\n" );
