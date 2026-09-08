<?php
/**
 * Approved #27 Product 360° admin view.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Product_360_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 26 );
	}

	public function menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Product 360°', 'sheet-stock-sync-woo' ),
			__( 'Product 360°', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-product-360',
			array( $this, 'render' )
		);
	}

	private function data( $product_id ) {
		global $wpdb;
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$supplier_products = $wpdb->prefix . 'ssw_supplier_products';
		$suppliers = $wpdb->prefix . 'ssw_suppliers';
		$purchase_orders = $wpdb->prefix . 'ssw_purchase_orders';
		$purchase_items = $wpdb->prefix . 'ssw_purchase_order_items';
		$sales = $wpdb->prefix . 'ssw_sales_daily';
		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$forecasts = $wpdb->prefix . 'ssw_forecasts';

		$metric = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$metrics} WHERE product_id=%d ORDER BY metric_date DESC LIMIT 1", $product_id ),
			ARRAY_A
		);
		if ( ! $metric ) {
			return array( 'product' => $product, 'metric' => null );
		}

		$supplier = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT sp.*,s.name supplier_name FROM {$supplier_products} sp LEFT JOIN {$suppliers} s ON s.id=sp.supplier_id WHERE sp.product_id=%d AND sp.is_preferred=1 LIMIT 1",
				$product_id
			),
			ARRAY_A
		);
		$incoming = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(GREATEST(i.ordered_qty-i.received_qty,0)),0) FROM {$purchase_items} i INNER JOIN {$purchase_orders} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('approved','ordered','shipped','partially_received')",
				$product_id
			)
		);
		$next_eta = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(p.expected_at) FROM {$purchase_items} i INNER JOIN {$purchase_orders} p ON p.id=i.po_id WHERE i.product_id=%d AND i.ordered_qty>i.received_qty AND p.status IN ('approved','ordered','shipped','partially_received')",
				$product_id
			)
		);
		$sales_30 = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(units),0) units,COALESCE(SUM(revenue),0) revenue FROM {$sales} WHERE product_id=%d AND fact_date>=DATE_SUB(%s,INTERVAL 29 DAY) AND fact_date<=%s",
				$product_id,
				$metric['metric_date'],
				$metric['metric_date']
			),
			ARRAY_A
		);

		$forecast_30 = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT forecast_qty FROM {$forecasts} WHERE product_id=%d AND horizon_days=30 ORDER BY generated_for_date DESC, FIELD(model_version,'seasonal_month_v1','weighted_velocity_v1') ASC LIMIT 1",
				$product_id
			)
		);
		$forecast_30 = null === $forecast_30 ? (float) $metric['weighted_velocity'] * 30 : (float) $forecast_30;
		$risk = SSW_Inventory_Intelligence::revenue_at_risk(
			$forecast_30,
			(float) $metric['available_quantity'],
			$incoming,
			(float) $metric['avg_realized_price']
		);

		$oos_days = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (SELECT snapshot_date,SUM(quantity) q FROM {$snapshots} WHERE product_id=%d AND snapshot_date>=DATE_SUB(%s,INTERVAL 29 DAY) AND snapshot_date<=%s GROUP BY snapshot_date HAVING q<=0) x",
				$product_id,
				$metric['metric_date'],
				$metric['metric_date']
			)
		);
		$lost = SSW_Inventory_Intelligence::estimated_lost_sales(
			(float) $metric['weighted_velocity'],
			$oos_days,
			(float) $metric['avg_realized_price']
		);
		$days_since_sale = $metric['last_sale_date']
			? max( 0, (int) floor( ( strtotime( $metric['metric_date'] ) - strtotime( $metric['last_sale_date'] ) ) / DAY_IN_SECONDS ) )
			: 9999;
		$health = SSW_Inventory_Intelligence::health_score(
			array(
				'availability' => (float) $metric['available_quantity'] <= 0 ? 0 : ( null !== $metric['days_of_stock'] && (float) $metric['days_of_stock'] < 14 ? 45 : 100 ),
				'excess_aging' => $days_since_sale > 180 ? 5 : ( $days_since_sale > 90 ? 25 : ( $days_since_sale > 60 ? 50 : 100 ) ),
				'demand' => (float) $metric['weighted_velocity'] > 0 ? 100 : 20,
				'margin' => null,
			)
		);

		$recommendation = 'healthy';
		if ( (float) $metric['available_quantity'] <= 0 ) {
			$recommendation = 'stockout';
		} elseif ( null !== $metric['days_of_stock'] && $supplier && (float) $metric['days_of_stock'] < (float) $supplier['lead_time_days'] && $incoming <= 0 ) {
			$recommendation = 'order_now';
		} elseif ( $days_since_sale > 90 ) {
			$recommendation = 'reduce_or_stop_reorder';
		}

		return array(
			'product' => $product,
			'metric' => $metric,
			'supplier' => $supplier,
			'incoming' => $incoming,
			'next_eta' => $next_eta,
			'sales_30' => $sales_30,
			'risk' => $risk,
			'lost' => $lost,
			'oos_days' => $oos_days,
			'days_since_sale' => $days_since_sale,
			'health' => $health,
			'recommendation' => $recommendation,
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$data = $product_id ? $this->data( $product_id ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Product 360°', 'sheet-stock-sync-woo' ); ?></h1>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="ssw-product-360">
				<input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" required>
				<button class="button"><?php esc_html_e( 'Open product', 'sheet-stock-sync-woo' ); ?></button>
			</form>
			<?php if ( $product_id && ! $data ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Product not found.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php elseif ( $data && empty( $data['metric'] ) ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Product exists but analytics history is not ready yet.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php elseif ( $data ) : ?>
				<?php $product = $data['product']; $metric = $data['metric']; ?>
				<h2><?php echo esc_html( $product->get_name() ); ?> <code><?php echo esc_html( $product->get_sku() ); ?></code></h2>
				<table class="widefat striped" style="max-width:900px"><tbody>
				<tr><th><?php esc_html_e( 'Price', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></td><th><?php esc_html_e( 'Stock', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $metric['available_quantity'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Velocity', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( number_format_i18n( $metric['weighted_velocity'], 2 ) ); ?>/day</td><th><?php esc_html_e( 'Stock cover', 'sheet-stock-sync-woo' ); ?></th><td><?php echo null === $metric['days_of_stock'] ? '—' : esc_html( number_format_i18n( $metric['days_of_stock'], 1 ) . ' days' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Projected stockout', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $metric['projected_stockout_date'] ? $metric['projected_stockout_date'] : '—' ); ?></td><th><?php esc_html_e( 'Incoming / next ETA', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $data['incoming'] . ' / ' . ( $data['next_eta'] ? $data['next_eta'] : '—' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( '30d revenue / units', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $data['sales_30']['revenue'] ) ); ?> / <?php echo esc_html( $data['sales_30']['units'] ); ?></td><th><?php esc_html_e( 'Revenue at Risk', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $data['risk']['revenue_at_risk'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Estimated Lost Sales', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $data['lost']['estimated_lost_revenue'] ) ); ?> (<?php echo esc_html( $data['oos_days'] ); ?> OOS days)</td><th><?php esc_html_e( 'Health', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( number_format_i18n( $data['health']['score'], 1 ) . '/100 ' . $data['health']['status'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Supplier inputs', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $data['supplier'] ? $data['supplier']['supplier_name'] : '—' ); ?></td><th><?php esc_html_e( 'Cost / MOQ / lead time', 'sheet-stock-sync-woo' ); ?></th><td><?php echo $data['supplier'] ? esc_html( $data['supplier']['cost'] . ' ' . $data['supplier']['currency'] . ' / ' . $data['supplier']['moq'] . ' / ' . $data['supplier']['lead_time_days'] . 'd' ) : '—'; ?></td></tr>
				<tr><th><?php esc_html_e( 'Slow / dead', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( SSW_Inventory_Intelligence::aging_bucket( $data['days_since_sale'] ) ); ?></td><th><?php esc_html_e( 'Recommendation', 'sheet-stock-sync-woo' ); ?></th><td><strong><?php echo esc_html( $data['recommendation'] ); ?></strong></td></tr>
				</tbody></table>
			<?php endif; ?>
		</div>
		<?php
	}
}
