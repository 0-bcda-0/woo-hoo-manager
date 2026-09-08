<?php
/**
 * Deterministic What Changed and Today Action Center helpers.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Action_Center {

	public static function delta( $current, $previous ) {
		$current = (float) $current;
		$previous = (float) $previous;
		$absolute = $current - $previous;
		return array(
			'current' => $current,
			'previous' => $previous,
			'absolute' => $absolute,
			'percent' => 0.0 === $previous ? null : ( $absolute / abs( $previous ) ) * 100.0,
		);
	}

	public static function priority_score( $input ) {
		$severity = strtolower( (string) ( isset( $input['severity'] ) ? $input['severity'] : 'warning' ) );
		$severity_score = array( 'critical' => 55.0, 'warning' => 35.0, 'opportunity' => 20.0 );
		$base = isset( $severity_score[ $severity ] ) ? $severity_score[ $severity ] : 35.0;
		$urgency = max( 0.0, min( 1.0, (float) ( isset( $input['urgency'] ) ? $input['urgency'] : 0.5 ) ) );
		$impact = max( 0.0, (float) ( isset( $input['financial_impact'] ) ? $input['financial_impact'] : 0 ) );
		$impact_score = $impact <= 0 ? 0.0 : min( 25.0, log( 1.0 + $impact, 10 ) * 8.0 );
		return round( min( 100.0, max( 0.0, $base + ( $urgency * 20.0 ) + $impact_score ) ), 4 );
	}

	public static function from_alert( $alert ) {
		$type = isset( $alert['type'] ) ? (string) $alert['type'] : '';
		$severity = isset( $alert['severity'] ) ? (string) $alert['severity'] : 'warning';
		$context = isset( $alert['context'] ) && is_array( $alert['context'] ) ? $alert['context'] : array();
		$impact = isset( $context['revenue_at_risk'] ) ? max( 0.0, (float) $context['revenue_at_risk'] ) : 0.0;
		$map = array(
			'predicted_stockout' => array(
				'title' => 'Reorder at-risk product',
				'why' => 'Projected stock cover is shorter than replenishment time.',
				'action' => 'Review supplier, incoming stock and create or update a purchase order.',
				'urgency' => 1.0,
			),
			'actual_stockout' => array(
				'title' => 'Resolve out-of-stock product',
				'why' => 'The product currently has no available stock.',
				'action' => 'Review incoming purchase orders or replenish stock immediately.',
				'urgency' => 1.0,
			),
			'late_purchase_order' => array(
				'title' => 'Review late purchase order',
				'why' => 'Expected arrival date has passed while the purchase order is still open.',
				'action' => 'Contact the supplier and update the PO status or ETA.',
				'urgency' => 0.9,
			),
			'low_health' => array(
				'title' => 'Review low-health inventory',
				'why' => 'Inventory Health has fallen into the critical range.',
				'action' => 'Open Inventory Intelligence and review the failing score components.',
				'urgency' => 0.7,
			),
			'dead_stock' => array(
				'title' => 'Act on dead stock',
				'why' => 'The product has not sold for a prolonged period.',
				'action' => 'Review reorder policy, markdown, clearance or an evidence-backed bundle opportunity.',
				'urgency' => 0.45,
			),
			'bundle_opportunity' => array(
				'title' => 'Review bundle opportunity',
				'why' => 'Historical orders show an evidence-backed pairing opportunity.',
				'action' => 'Open Bundle Recommendations and review attach rate, support and margin ceiling.',
				'urgency' => 0.35,
			),
		);
		$definition = isset( $map[ $type ] ) ? $map[ $type ] : array( 'title' => 'Review operational alert', 'why' => 'An approved operational condition needs review.', 'action' => 'Open the alert and review its evidence.', 'urgency' => 0.5 );
		return array(
			'alert_id' => isset( $alert['id'] ) ? (int) $alert['id'] : 0,
			'type' => $type,
			'severity' => $severity,
			'product_id' => isset( $alert['product_id'] ) ? (int) $alert['product_id'] : 0,
			'purchase_order_id' => isset( $alert['purchase_order_id'] ) ? (int) $alert['purchase_order_id'] : 0,
			'title' => $definition['title'],
			'why_now' => $definition['why'],
			'recommended_action' => $definition['action'],
			'estimated_impact' => $impact,
			'priority_score' => self::priority_score( array( 'severity' => $severity, 'urgency' => $definition['urgency'], 'financial_impact' => $impact ) ),
			'context' => $context,
		);
	}
}
