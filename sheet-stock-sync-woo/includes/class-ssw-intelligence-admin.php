<?php
/**
 * Read-only admin view for approved inventory intelligence features.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Intelligence_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 24 );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Inventory Intelligence', 'sheet-stock-sync-woo' ),
			__( 'Inventory Intelligence', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-inventory-intelligence',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}

		$rows = $this->load_rows( 200 );
		$pareto_input = array();
		foreach ( $rows as $row ) {
			$pareto_input[ $row['product_id'] ] = $row['revenue_90d'];
		}
		$pareto = SSW_Inventory_Intelligence::pareto( $pareto_input );
		$products_to_80 = 0;
		foreach ( $pareto as $index => $item ) {
			$products_to_80 = $index + 1;
			if ( $item['cumulative_share'] >= 0.80 ) { break; }
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Inventory Intelligence', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Deterministic inventory decisions from your WooCommerce sales, stock, supplier costs and incoming purchase orders. Estimates are explicitly marked.', 'sheet-stock-sync-woo' ); ?></p>

			<?php if ( ! $rows ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No precomputed product metrics are available yet. Background analytics jobs need to build history first.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php else : ?>
				<div style="display:flex;gap:16px;flex-wrap:wrap;margin:18px 0">
					<div class="card"><strong><?php esc_html_e( 'Products analysed', 'sheet-stock-sync-woo' ); ?></strong><br><?php echo esc_html( count( $rows ) ); ?></div>
					<div class="card"><strong><?php esc_html_e( 'Pareto concentration', 'sheet-stock-sync-woo' ); ?></strong><br><?php echo esc_html( $products_to_80 ); ?> / <?php echo esc_html( count( $rows ) ); ?> <?php esc_html_e( 'products generate about 80% of analysed revenue', 'sheet-stock-sync-woo' ); ?></div>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Stock / cover', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Revenue at Risk', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Estimated Lost Sales (30d OOS)', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Health', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Dead / slow aging', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'GMROI (90d)', 'sheet-stock-sync-woo' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?><tr><td colspan="7"><?php esc_html_e( 'No data yet.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><code><?php echo esc_html( $row['sku'] ); ?></code><br><small><?php echo esc_html( $row['confidence_state'] ); ?></small></td>
						<td><?php echo esc_html( $this->number( $row['available_quantity'] ) ); ?> / <?php echo null === $row['days_of_stock'] ? '—' : esc_html( $this->number( $row['days_of_stock'] ) . ' d' ); ?><br><small><?php echo esc_html( $row['projected_stockout_date'] ? $row['projected_stockout_date'] : '—' ); ?></small></td>
						<td><strong><?php echo esc_html( wc_price( $row['revenue_at_risk'] ) ); ?></strong><br><small><?php echo esc_html( $this->number( $row['shortage_units'] ) ); ?> <?php esc_html_e( 'units shortage', 'sheet-stock-sync-woo' ); ?> · <?php echo esc_html( $this->number( $row['incoming_quantity'] ) ); ?> <?php esc_html_e( 'incoming', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><strong><?php echo esc_html( wc_price( $row['estimated_lost_revenue'] ) ); ?></strong><br><small><?php esc_html_e( 'Estimated', 'sheet-stock-sync-woo' ); ?> · <?php echo esc_html( $this->number( $row['oos_days_30'] ) ); ?> <?php esc_html_e( 'actual OOS days', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><strong><?php echo null === $row['health']['score'] ? '—' : esc_html( $this->number( $row['health']['score'] ) . '/100' ); ?></strong><br><small><?php echo esc_html( $row['health']['status'] ); ?></small></td>
						<td><?php echo esc_html( $row['aging_bucket'] ); ?><br><small><?php echo esc_html( $row['days_since_last_sale'] ); ?> <?php esc_html_e( 'days since sale', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><?php echo null === $row['gmroi'] ? '—' : esc_html( $this->number( $row['gmroi'] ) ); ?><br><small><?php echo esc_html( $row['gmroi_provisional'] ? __( 'Provisional', 'sheet-stock-sync-woo' ) : __( '90-day history', 'sheet-stock-sync-woo' ) ); ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function load_rows( $limit ) {
		global $wpdb;
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$sales = $wpdb->prefix . 'ssw_sales_daily';
		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$supplier_products = $wpdb->prefix . 'ssw_supplier_products';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$po_items = $wpdb->prefix . 'ssw_purchase_order_items';
		$forecasts = $wpdb->prefix . 'ssw_forecasts';

		$metric_date = $wpdb->get_var( "SELECT MAX(metric_date) FROM {$metrics}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $metric_date ) { return array(); }
		$metric_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$metrics} WHERE metric_date = %s ORDER BY weighted_velocity DESC LIMIT %d", $metric_date, min( 200, max( 1, (int) $limit ) ) ), ARRAY_A );
		$result = array();

		foreach ( $metric_rows as $metric ) {
			$product_id = absint( $metric['product_id'] );
			$product = wc_get_product( $product_id );
			if ( ! $product ) { continue; }

			$relation = $wpdb->get_row( $wpdb->prepare( "SELECT cost, lead_time_days FROM {$supplier_products} WHERE product_id = %d AND is_preferred = 1 LIMIT 1", $product_id ), ARRAY_A );
			$cost = $relation && null !== $relation['cost'] ? (float) $relation['cost'] : null;
			$lead_time = $relation ? max( 0, (int) $relation['lead_time_days'] ) : 0;
			$incoming = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$po_items} i INNER JOIN {$po} p ON p.id=i.purchase_order_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')", $product_id ) );
			$forecast_30 = $wpdb->get_var( $wpdb->prepare( "SELECT forecast_qty FROM {$forecasts} WHERE product_id=%d AND horizon_days=30 ORDER BY generated_for_date DESC LIMIT 1", $product_id ) );
			$forecast_30 = null === $forecast_30 ? ( (float) $metric['weighted_velocity'] * 30 ) : (float) $forecast_30;
			$risk = SSW_Inventory_Intelligence::revenue_at_risk( $forecast_30, (float) $metric['available_quantity'], $incoming, (float) $metric['avg_realized_price'] );

			$oos_days = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT snapshot_date, SUM(quantity) qty FROM {$snapshots} WHERE product_id=%d AND snapshot_date >= DATE_SUB(%s, INTERVAL 29 DAY) AND snapshot_date <= %s GROUP BY snapshot_date HAVING qty <= 0) oos", $product_id, $metric_date, $metric_date ) );
			$lost = SSW_Inventory_Intelligence::estimated_lost_sales( (float) $metric['weighted_velocity'], $oos_days, (float) $metric['avg_realized_price'] );

			$days_since_last_sale = $metric['last_sale_date'] ? max( 0, (int) floor( ( strtotime( $metric_date ) - strtotime( $metric['last_sale_date'] ) ) / DAY_IN_SECONDS ) ) : 9999;
			$aging_bucket = SSW_Inventory_Intelligence::aging_bucket( $days_since_last_sale );
			$availability_score = $this->availability_score( $metric );
			$aging_score = $this->aging_score( $days_since_last_sale );
			$demand_score = $this->demand_score( $metric );

			$sales90 = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(units),0) units, COALESCE(SUM(revenue),0) revenue FROM {$sales} WHERE product_id=%d AND fact_date >= DATE_SUB(%s, INTERVAL 89 DAY) AND fact_date <= %s", $product_id, $metric_date, $metric_date ), ARRAY_A );
			$gross_margin = null;
			if ( null !== $cost ) { $gross_margin = (float) $sales90['revenue'] - ( (float) $sales90['units'] * $cost ); }
			$avg_inventory_cost = null;
			if ( null !== $cost ) {
				$avg_inventory_cost = $wpdb->get_var( $wpdb->prepare( "SELECT AVG(day_cost) FROM (SELECT snapshot_date, SUM(quantity * COALESCE(unit_cost,%s)) day_cost FROM {$snapshots} WHERE product_id=%d AND snapshot_date >= DATE_SUB(%s, INTERVAL 89 DAY) AND snapshot_date <= %s GROUP BY snapshot_date) inv", (string) $cost, $product_id, $metric_date, $metric_date ) );
				$avg_inventory_cost = null === $avg_inventory_cost ? null : (float) $avg_inventory_cost;
			}
			$gmroi = null === $gross_margin || null === $avg_inventory_cost ? null : SSW_Inventory_Intelligence::gmroi( $gross_margin, $avg_inventory_cost );
			$margin_score = null === $gmroi ? null : min( 100.0, max( 0.0, $gmroi * 50.0 ) );
			$health = SSW_Inventory_Intelligence::health_score( array( 'availability' => $availability_score, 'excess_aging' => $aging_score, 'demand' => $demand_score, 'margin' => $margin_score ) );

			$result[] = array(
				'product_id' => $product_id,
				'name' => $product->get_name(),
				'sku' => $product->get_sku(),
				'available_quantity' => (float) $metric['available_quantity'],
				'days_of_stock' => null === $metric['days_of_stock'] ? null : (float) $metric['days_of_stock'],
				'projected_stockout_date' => $metric['projected_stockout_date'],
				'confidence_state' => $metric['confidence_state'],
				'incoming_quantity' => $incoming,
				'lead_time_days' => $lead_time,
				'shortage_units' => $risk['shortage_units'],
				'revenue_at_risk' => $risk['revenue_at_risk'],
				'oos_days_30' => $oos_days,
				'estimated_lost_revenue' => $lost['estimated_lost_revenue'],
				'health' => $health,
				'days_since_last_sale' => $days_since_last_sale,
				'aging_bucket' => $aging_bucket,
				'gmroi' => $gmroi,
				'gmroi_provisional' => (int) $metric['history_days'] < 90,
				'revenue_90d' => (float) $sales90['revenue'],
			);
		}
		return $result;
	}

	private function availability_score( $metric ) {
		if ( (float) $metric['available_quantity'] <= 0 ) { return 0.0; }
		if ( null === $metric['days_of_stock'] ) { return 70.0; }
		$days = (float) $metric['days_of_stock'];
		if ( $days < 7 ) { return 20.0; }
		if ( $days < 14 ) { return 45.0; }
		if ( $days < 30 ) { return 75.0; }
		return 100.0;
	}

	private function aging_score( $days ) {
		if ( $days <= 30 ) { return 100.0; }
		if ( $days <= 60 ) { return 80.0; }
		if ( $days <= 90 ) { return 60.0; }
		if ( $days <= 180 ) { return 35.0; }
		return 10.0;
	}

	private function demand_score( $metric ) {
		if ( 'insufficient_data' === $metric['confidence_state'] ) { return 40.0; }
		if ( (float) $metric['weighted_velocity'] <= 0 ) { return 20.0; }
		return 'low_confidence' === $metric['confidence_state'] ? 70.0 : 100.0;
	}

	private function number( $value ) {
		return number_format_i18n( (float) $value, 2 );
	}
}
