<?php
/**
 * Non-persistent deterministic What-if simulator for approved scope item #35.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_What_If {

	public static function simulate_product( $baseline, $overrides ) {
		$base = (array) $baseline;
		$scenario = $base;
		$demand_percent = self::clamp( isset( $overrides['demand_percent'] ) ? $overrides['demand_percent'] : 0, -100, 500 );
		$lead_delta = (int) self::clamp( isset( $overrides['lead_time_days_delta'] ) ? $overrides['lead_time_days_delta'] : 0, -365, 365 );
		$delay = (int) self::clamp( isset( $overrides['reorder_delay_days'] ) ? $overrides['reorder_delay_days'] : 0, 0, 365 );

		$base_velocity = max( 0.0, (float) ( isset( $base['daily_velocity'] ) ? $base['daily_velocity'] : 0 ) );
		$scenario['daily_velocity'] = max( 0.0, $base_velocity * ( 1.0 + ( $demand_percent / 100.0 ) ) );
		$scenario['lead_time_days'] = max( 0, (int) ( isset( $base['lead_time_days'] ) ? $base['lead_time_days'] : 0 ) + $lead_delta );
		if ( array_key_exists( 'safety_stock_days', $overrides ) ) {
			$scenario['safety_stock_days'] = max( 0, min( 365, (int) $overrides['safety_stock_days'] ) );
		}

		$baseline_plan = SSW_Purchasing_Planner::plan_product( $base );
		$scenario_plan = SSW_Purchasing_Planner::plan_product( $scenario );

		$baseline_risk = self::revenue_at_risk( $base, 0 );
		$scenario_risk = self::revenue_at_risk( $scenario, $delay );
		$budget_cap = array_key_exists( 'budget_cap', $overrides ) && '' !== (string) $overrides['budget_cap'] ? max( 0.0, (float) $overrides['budget_cap'] ) : null;
		$scenario_cash = null === $scenario_plan['expected_cash'] ? 0.0 : (float) $scenario_plan['expected_cash'];

		return array(
			'baseline' => $base,
			'scenario' => $scenario,
			'baseline_plan' => $baseline_plan,
			'scenario_plan' => $scenario_plan,
			'baseline_revenue_at_risk' => $baseline_risk,
			'scenario_revenue_at_risk' => $scenario_risk,
			'revenue_at_risk_delta' => $scenario_risk - $baseline_risk,
			'cash_delta' => ( null === $scenario_plan['expected_cash'] ? 0.0 : (float) $scenario_plan['expected_cash'] ) - ( null === $baseline_plan['expected_cash'] ? 0.0 : (float) $baseline_plan['expected_cash'] ),
			'quantity_delta' => (float) $scenario_plan['suggested_order_quantity'] - (float) $baseline_plan['suggested_order_quantity'],
			'reorder_delay_days' => $delay,
			'budget_pressure' => SSW_Purchasing_Planner::budget_pressure( $scenario_cash, $budget_cap ),
		);
	}

	private static function revenue_at_risk( $data, $extra_delay_days ) {
		$velocity = max( 0.0, (float) ( isset( $data['daily_velocity'] ) ? $data['daily_velocity'] : 0 ) );
		$available = max( 0.0, (float) ( isset( $data['on_hand'] ) ? $data['on_hand'] : 0 ) ) + max( 0.0, (float) ( isset( $data['incoming'] ) ? $data['incoming'] : 0 ) );
		$lead = max( 0, (int) ( isset( $data['lead_time_days'] ) ? $data['lead_time_days'] : 0 ) );
		$safety = max( 0, (int) ( isset( $data['safety_stock_days'] ) ? $data['safety_stock_days'] : 0 ) );
		$price = max( 0.0, (float) ( isset( $data['avg_realized_price'] ) ? $data['avg_realized_price'] : 0 ) );
		$demand_until_replenishment = $velocity * ( $lead + $safety + max( 0, (int) $extra_delay_days ) );
		$shortage = max( 0.0, $demand_until_replenishment - $available );
		return round( $shortage * $price, 4 );
	}

	private static function clamp( $value, $minimum, $maximum ) {
		return max( $minimum, min( $maximum, (float) $value ) );
	}
}
