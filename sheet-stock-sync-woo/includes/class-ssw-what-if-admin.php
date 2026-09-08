<?php
/** Admin UI for approved #35 What-if simulator. */
defined( 'ABSPATH' ) || exit;

final class SSW_What_If_Admin {
	public function __construct() { add_action( 'admin_menu', array( $this, 'admin_menu' ), 21 ); }
	public function admin_menu() {
		add_submenu_page( 'sheet-stock-sync-woo', __( 'What-if Simulator', 'sheet-stock-sync-woo' ), __( 'What-if Simulator', 'sheet-stock-sync-woo' ), 'manage_woocommerce', 'ssw-what-if', array( $this, 'render_page' ) );
	}
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$result = null;
		if ( $product_id && isset( $_POST['ssw_what_if_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ssw_what_if_nonce'] ) ), 'ssw_what_if' ) ) {
			$baseline = $this->baseline_for_product( $product_id );
			if ( $baseline ) {
				$result = SSW_What_If::simulate_product( $baseline, array(
					'demand_percent' => isset( $_POST['demand_percent'] ) ? (float) $_POST['demand_percent'] : 0,
					'lead_time_days_delta' => isset( $_POST['lead_time_days_delta'] ) ? (int) $_POST['lead_time_days_delta'] : 0,
					'safety_stock_days' => isset( $_POST['safety_stock_days'] ) ? absint( $_POST['safety_stock_days'] ) : 5,
					'reorder_delay_days' => isset( $_POST['reorder_delay_days'] ) ? absint( $_POST['reorder_delay_days'] ) : 0,
					'budget_cap' => isset( $_POST['budget_cap'] ) && '' !== trim( (string) $_POST['budget_cap'] ) ? (float) $_POST['budget_cap'] : null,
				) );
			}
		}
		?>
		<div class="wrap"><h1><?php esc_html_e( 'What-if Simulator', 'sheet-stock-sync-woo' ); ?></h1>
		<p><?php esc_html_e( 'Temporary scenario only. Nothing entered here is saved and no stock, settings or purchase orders are changed.', 'sheet-stock-sync-woo' ); ?></p>
		<form method="post" style="background:#fff;border:1px solid #dcdcde;padding:16px;max-width:900px">
		<?php wp_nonce_field( 'ssw_what_if', 'ssw_what_if_nonce' ); ?>
		<table class="form-table"><tbody>
		<tr><th><label><?php esc_html_e( 'Product ID / variation ID', 'sheet-stock-sync-woo' ); ?></label></th><td><input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" required></td></tr>
		<tr><th><?php esc_html_e( 'Demand change %', 'sheet-stock-sync-woo' ); ?></th><td><input type="number" step="1" min="-100" max="500" name="demand_percent" value="<?php echo esc_attr( isset( $_POST['demand_percent'] ) ? $_POST['demand_percent'] : 0 ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Lead-time change (days)', 'sheet-stock-sync-woo' ); ?></th><td><input type="number" min="-365" max="365" name="lead_time_days_delta" value="<?php echo esc_attr( isset( $_POST['lead_time_days_delta'] ) ? $_POST['lead_time_days_delta'] : 0 ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Safety-stock days', 'sheet-stock-sync-woo' ); ?></th><td><input type="number" min="0" max="365" name="safety_stock_days" value="<?php echo esc_attr( isset( $_POST['safety_stock_days'] ) ? $_POST['safety_stock_days'] : 5 ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Reorder delay (days)', 'sheet-stock-sync-woo' ); ?></th><td><input type="number" min="0" max="365" name="reorder_delay_days" value="<?php echo esc_attr( isset( $_POST['reorder_delay_days'] ) ? $_POST['reorder_delay_days'] : 0 ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Temporary budget cap', 'sheet-stock-sync-woo' ); ?></th><td><input type="number" min="0" step="0.01" name="budget_cap" value="<?php echo esc_attr( isset( $_POST['budget_cap'] ) ? $_POST['budget_cap'] : '' ); ?>"></td></tr>
		</tbody></table><?php submit_button( __( 'Run scenario', 'sheet-stock-sync-woo' ) ); ?></form>
		<?php if ( $product_id && ! $result ) : ?><div class="notice notice-warning"><p><?php esc_html_e( 'Not enough preferred-supplier/metrics data for this product.', 'sheet-stock-sync-woo' ); ?></p></div><?php endif; ?>
		<?php if ( $result ) : $this->render_result( $result ); endif; ?>
		</div><?php
	}
	private function baseline_for_product( $product_id ) {
		global $wpdb;
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily'; $relations = $wpdb->prefix . 'ssw_supplier_products'; $po = $wpdb->prefix . 'ssw_purchase_orders'; $items = $wpdb->prefix . 'ssw_purchase_order_items';
		$sql = "SELECT m.weighted_velocity,m.available_quantity,m.avg_realized_price,m.confidence_state,r.cost,r.currency,r.moq,r.units_per_box,r.lead_time_days FROM {$metrics} m INNER JOIN {$relations} r ON r.product_id=m.product_id AND r.is_preferred=1 WHERE m.product_id=%d ORDER BY m.metric_date DESC LIMIT 1";
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $product_id ), ARRAY_A ); if ( ! $row ) { return null; }
		$incoming_sql = "SELECT COALESCE(SUM(i.ordered_qty-i.received_qty),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('ordered','shipped','partially_received')";
		$incoming = (float) $wpdb->get_var( $wpdb->prepare( $incoming_sql, $product_id ) );
		return array( 'as_of_date' => current_time( 'Y-m-d' ), 'daily_velocity' => (float) $row['weighted_velocity'], 'on_hand' => (float) $row['available_quantity'], 'incoming' => $incoming, 'lead_time_days' => absint( $row['lead_time_days'] ), 'safety_stock_days' => 5, 'moq' => max( 1, absint( $row['moq'] ) ), 'pack_size' => max( 1, absint( $row['units_per_box'] ) ), 'unit_cost' => null !== $row['cost'] ? (float) $row['cost'] : null, 'currency' => $row['currency'], 'avg_realized_price' => (float) $row['avg_realized_price'], 'confidence' => $row['confidence_state'], 'horizon_days' => 90 );
	}
	private function render_result( $result ) {
		$b = $result['baseline_plan']; $s = $result['scenario_plan'];
		?><h2 style="margin-top:28px"><?php esc_html_e( 'Baseline vs scenario', 'sheet-stock-sync-woo' ); ?></h2><table class="widefat striped" style="max-width:900px"><thead><tr><th><?php esc_html_e( 'Metric', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Baseline', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Scenario', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody>
		<tr><td><?php esc_html_e( 'Daily demand', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( number_format_i18n( $result['baseline']['daily_velocity'], 3 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $result['scenario']['daily_velocity'], 3 ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Lead time', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( $result['baseline']['lead_time_days'] ); ?></td><td><?php echo esc_html( $result['scenario']['lead_time_days'] ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Suggested order date', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( $b['suggested_order_date'] ?: '—' ); ?></td><td><?php echo esc_html( $s['suggested_order_date'] ?: '—' ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Suggested quantity', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( number_format_i18n( $b['suggested_order_quantity'], 2 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $s['suggested_order_quantity'], 2 ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Purchasing cash', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( null === $b['expected_cash'] ? '—' : $b['currency'] . ' ' . number_format_i18n( $b['expected_cash'], 2 ) ); ?></td><td><?php echo esc_html( null === $s['expected_cash'] ? '—' : $s['currency'] . ' ' . number_format_i18n( $s['expected_cash'], 2 ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Revenue at Risk', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( number_format_i18n( $result['baseline_revenue_at_risk'], 2 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $result['scenario_revenue_at_risk'], 2 ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Temporary budget', 'sheet-stock-sync-woo' ); ?></td><td>—</td><td><?php echo esc_html( $result['budget_pressure']['status'] ); ?></td></tr>
		</tbody></table><p><strong><?php esc_html_e( 'No production data was changed.', 'sheet-stock-sync-woo' ); ?></strong></p><?php
	}
}
