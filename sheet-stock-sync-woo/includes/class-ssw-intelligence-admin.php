<?php
/**
 * Approved inventory intelligence admin view.
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
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		$rows = $this->load_rows( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Inventory Intelligence', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Forecasting, stockout risk, Revenue at Risk, Estimated Lost Sales, Inventory Health and expanded Slow / Dead Stock intelligence.', 'sheet-stock-sync-woo' ); ?></p>
			<?php if ( ! $rows ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No precomputed product metrics are available yet.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php endif; ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Stock / cover', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Stockout', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Revenue at Risk', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Estimated Lost Sales', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Health', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Slow / Dead Stock', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Tied-up capital', 'sheet-stock-sync-woo' ); ?></th>
					<th><?php esc_html_e( 'Suggested action', 'sheet-stock-sync-woo' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="9"><?php esc_html_e( 'No data yet.', 'sheet-stock-sync-woo' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $row['name'] ); ?></strong><br><code><?php echo esc_html( $row['sku'] ); ?></code><br><small><?php echo esc_html( $row['confidence_state'] ); ?></small></td>
						<td><?php echo esc_html( $this->number( $row['available_quantity'] ) ); ?> / <?php echo null === $row['days_of_stock'] ? '—' : esc_html( $this->number( $row['days_of_stock'] ) . ' d' ); ?></td>
						<td><?php echo esc_html( $row['projected_stockout_date'] ? $row['projected_stockout_date'] : '—' ); ?><br><small><?php echo esc_html( $this->number( $row['incoming_quantity'] ) ); ?> <?php esc_html_e( 'incoming', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><?php echo wp_kses_post( wc_price( $row['revenue_at_risk'] ) ); ?><br><small><?php echo esc_html( $this->number( $row['shortage_units'] ) ); ?> <?php esc_html_e( 'units', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><?php echo wp_kses_post( wc_price( $row['estimated_lost_revenue'] ) ); ?><br><small><?php esc_html_e( 'Estimated', 'sheet-stock-sync-woo' ); ?> · <?php echo esc_html( $row['oos_days_30'] ); ?> <?php esc_html_e( 'OOS days', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><?php echo null === $row['health']['score'] ? '—' : esc_html( $this->number( $row['health']['score'] ) . '/100' ); ?><br><small><?php echo esc_html( $row['health']['status'] ); ?></small></td>
						<td><?php echo esc_html( $row['aging_label'] ); ?><br><small><?php echo esc_html( $row['days_since_last_sale'] ); ?> <?php esc_html_e( 'days since sale', 'sheet-stock-sync-woo' ); ?></small></td>
						<td><?php echo null === $row['tied_up_cost'] ? '—' : wp_kses_post( wc_price( $row['tied_up_cost'] ) ); ?></td>
						<td><strong><?php echo esc_html( $row['suggested_action'] ); ?></strong></td>
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
		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$purchase_orders = $wpdb->prefix . 'ssw_purchase_orders';
		$purchase_items = $wpdb->prefix . 'ssw_purchase_order_items';
		$forecasts = $wpdb->prefix . 'ssw_forecasts';
		$supplier_products = $wpdb->prefix . 'ssw_supplier_products';
		$metric_date = $wpdb->get_var( "SELECT MAX(metric_date) FROM {$metrics}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $metric_date ) {
			return array();
		}

		$metric_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$metrics} WHERE metric_date=%s ORDER BY weighted_velocity DESC LIMIT %d",
				$metric_date,
				min( 200, max( 1, (int) $limit ) )
			),
			ARRAY_A
		);
		$result = array();

		foreach ( $metric_rows as $metric ) {
			$product_id = absint( $metric['product_id'] );
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$relation = $wpdb->get_row(
				$wpdb->prepare( "SELECT cost FROM {$supplier_products} WHERE product_id=%d AND is_preferred=1 LIMIT 1", $product_id ),
				ARRAY_A
			);
			$cost = $relation && null !== $relation['cost'] ? (float) $relation['cost'] : null;

			$incoming = (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$purchase_items} i INNER JOIN {$purchase_orders} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')",
					$product_id
				)
			);

			$forecast = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT forecast_qty FROM {$forecasts} WHERE product_id=%d AND horizon_days=30 ORDER BY generated_for_date DESC, FIELD(model_version,'seasonal_month_v1','weighted_velocity_v1') ASC LIMIT 1",
					$product_id
				)
			);
			$forecast = null === $forecast ? (float) $metric['weighted_velocity'] * 30 : (float) $forecast;
			$risk = SSW_Inventory_Intelligence::revenue_at_risk( $forecast, (float) $metric['available_quantity'], $incoming, (float) $metric['avg_realized_price'] );

			$oos_days = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM (SELECT snapshot_date,SUM(quantity) qty FROM {$snapshots} WHERE product_id=%d AND snapshot_date>=DATE_SUB(%s,INTERVAL 29 DAY) AND snapshot_date<=%s GROUP BY snapshot_date HAVING qty<=0) oos",
					$product_id,
					$metric_date,
					$metric_date
				)
			);
			$lost = SSW_Inventory_Intelligence::estimated_lost_sales( (float) $metric['weighted_velocity'], $oos_days, (float) $metric['avg_realized_price'] );

			$days_since_sale = $metric['last_sale_date']
				? max( 0, (int) floor( ( strtotime( $metric_date ) - strtotime( $metric['last_sale_date'] ) ) / DAY_IN_SECONDS ) )
				: 9999;
			$health = SSW_Inventory_Intelligence::health_score(
				array(
					'availability' => $this->availability_score( $metric ),
					'excess_aging' => $this->aging_score( $days_since_sale ),
					'demand' => $this->demand_score( $metric ),
					'margin' => null,
				)
			);
			$quantity = max( 0, (float) $metric['available_quantity'] );
			$tied_up_cost = null === $cost ? null : $quantity * $cost;

			$result[] = array(
				'product_id' => $product_id,
				'name' => $product->get_name(),
				'sku' => $product->get_sku(),
				'available_quantity' => $quantity,
				'days_of_stock' => null === $metric['days_of_stock'] ? null : (float) $metric['days_of_stock'],
				'projected_stockout_date' => $metric['projected_stockout_date'],
				'confidence_state' => $metric['confidence_state'],
				'incoming_quantity' => $incoming,
				'shortage_units' => $risk['shortage_units'],
				'revenue_at_risk' => $risk['revenue_at_risk'],
				'oos_days_30' => $oos_days,
				'estimated_lost_revenue' => $lost['estimated_lost_revenue'],
				'health' => $health,
				'days_since_last_sale' => $days_since_sale,
				'aging_label' => $this->aging_label( $days_since_sale ),
				'tied_up_cost' => $tied_up_cost,
				'suggested_action' => $this->action( $days_since_sale, $quantity, (float) $metric['weighted_velocity'], $risk['shortage_units'] ),
			);
		}
		return $result;
	}

	private function action( $days, $quantity, $velocity, $shortage ) {
		if ( $shortage > 0 ) { return __( 'Replenish / review incoming stock', 'sheet-stock-sync-woo' ); }
		if ( $quantity <= 0 ) { return __( 'No excess stock', 'sheet-stock-sync-woo' ); }
		if ( $days > 180 ) { return __( 'Clearance or bundle candidate', 'sheet-stock-sync-woo' ); }
		if ( $days > 90 ) { return __( 'Stop/reduce reorder; markdown candidate', 'sheet-stock-sync-woo' ); }
		if ( $days > 60 || $velocity <= 0 ) { return __( 'Review reorder level', 'sheet-stock-sync-woo' ); }
		return __( 'Keep current policy', 'sheet-stock-sync-woo' );
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
		if ( $days <= 60 ) { return 75.0; }
		if ( $days <= 90 ) { return 50.0; }
		if ( $days <= 180 ) { return 25.0; }
		return 5.0;
	}

	private function aging_label( $days ) {
		if ( $days <= 30 ) { return __( 'Healthy (0–30 days)', 'sheet-stock-sync-woo' ); }
		if ( $days <= 60 ) { return __( 'Slow (31–60 days)', 'sheet-stock-sync-woo' ); }
		if ( $days <= 90 ) { return __( 'Very slow (61–90 days)', 'sheet-stock-sync-woo' ); }
		if ( $days <= 180 ) { return __( 'Dead (91–180 days)', 'sheet-stock-sync-woo' ); }
		return __( 'Critical dead stock (180+ days)', 'sheet-stock-sync-woo' );
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
