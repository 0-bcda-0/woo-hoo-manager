<?php
/**
 * Read-only admin screen for evidence-backed bundle recommendations.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Bundle_Admin {
	public function __construct() { add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 ); }

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Bundle Recommendations', 'sheet-stock-sync-woo' ),
			__( 'Bundle Recommendations', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-bundle-recommendations',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$rows = $this->recommendations( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bundle Recommendations', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Recommendations come only from actual WooCommerce co-purchases. Nothing is created automatically.', 'sheet-stock-sync-woo' ); ?></p>
			<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Slow / dead product', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Recommended partner', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Evidence', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Score', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Discount ceiling', 'sheet-stock-sync-woo' ); ?></th>
			</tr></thead><tbody>
			<?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No bundle recommendation has enough evidence yet. The background order backfill may still be building co-purchase history.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $row['slow_name'] ); ?></strong><br><code><?php echo esc_html( $row['slow_sku'] ); ?></code></td>
				<td><strong><?php echo esc_html( $row['partner_name'] ); ?></strong><br><code><?php echo esc_html( $row['partner_sku'] ); ?></code></td>
				<td><?php echo esc_html( $row['pair_orders'] ); ?> <?php esc_html_e( 'orders together', 'sheet-stock-sync-woo' ); ?><br><small><?php echo esc_html( number_format_i18n( $row['support'] * 100, 1 ) ); ?>% <?php esc_html_e( 'support', 'sheet-stock-sync-woo' ); ?> · <?php echo esc_html( number_format_i18n( $row['attach_rate_slow'] * 100, 1 ) ); ?>% <?php esc_html_e( 'attach rate', 'sheet-stock-sync-woo' ); ?></small></td>
				<td><?php echo esc_html( number_format_i18n( $row['score'] * 100, 1 ) ); ?>/100</td>
				<td><?php if ( null === $row['discount'] ) : ?>—<br><small><?php esc_html_e( 'Add reliable supplier cost to calculate a safe discount.', 'sheet-stock-sync-woo' ); ?></small><?php else : ?><strong><?php echo esc_html( number_format_i18n( $row['discount']['max_discount_percent'], 1 ) ); ?>%</strong><br><small><?php esc_html_e( 'max at 35% gross-margin floor', 'sheet-stock-sync-woo' ); ?></small><?php endif; ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function recommendations( $limit ) {
		global $wpdb;
		$pairs = $wpdb->prefix . 'ssw_bundle_pairs';
		$product_orders = $wpdb->prefix . 'ssw_bundle_product_orders';
		$processed = $wpdb->prefix . 'ssw_bundle_processed_orders';
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$supplier_products = $wpdb->prefix . 'ssw_supplier_products';
		$total_orders = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$processed}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( $total_orders < 3 ) { return array(); }
		$metric_date = $wpdb->get_var( "SELECT MAX(metric_date) FROM {$metrics}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $metric_date ) { return array(); }
		$sql = "SELECT bp.product_a,bp.product_b,bp.pair_orders,pa.orders_count orders_a,pb.orders_count orders_b,ma.weighted_velocity velocity_a,ma.days_of_stock cover_a,ma.last_sale_date last_a,mb.weighted_velocity velocity_b,mb.days_of_stock cover_b,mb.last_sale_date last_b FROM {$pairs} bp INNER JOIN {$product_orders} pa ON pa.product_id=bp.product_a INNER JOIN {$product_orders} pb ON pb.product_id=bp.product_b LEFT JOIN {$metrics} ma ON ma.product_id=bp.product_a AND ma.metric_date=%s LEFT JOIN {$metrics} mb ON mb.product_id=bp.product_b AND mb.metric_date=%s WHERE bp.pair_orders>=3 ORDER BY bp.pair_orders DESC LIMIT %d";
		$pairs_rows = $wpdb->get_results( $wpdb->prepare( $sql, $metric_date, $metric_date, min( 300, max( 1, (int) $limit * 3 ) ) ), ARRAY_A );
		$result = array();
		foreach ( $pairs_rows as $pair ) {
			$a_slow = $this->is_slow( $pair['velocity_a'], $pair['last_a'], $metric_date );
			$b_slow = $this->is_slow( $pair['velocity_b'], $pair['last_b'], $metric_date );
			$a_healthy = $this->is_healthy_partner( $pair['velocity_a'], $pair['cover_a'] );
			$b_healthy = $this->is_healthy_partner( $pair['velocity_b'], $pair['cover_b'] );
			if ( $a_slow && $b_healthy ) { $slow_id = (int) $pair['product_a']; $partner_id = (int) $pair['product_b']; $slow_orders = (int) $pair['orders_a']; $partner_orders = (int) $pair['orders_b']; }
			elseif ( $b_slow && $a_healthy ) { $slow_id = (int) $pair['product_b']; $partner_id = (int) $pair['product_a']; $slow_orders = (int) $pair['orders_b']; $partner_orders = (int) $pair['orders_a']; }
			else { continue; }
			$score = SSW_Bundle_Opportunities::score_candidate( array( 'pair_orders' => $pair['pair_orders'], 'orders_a' => $slow_orders, 'orders_b' => $partner_orders, 'total_orders' => $total_orders, 'slow_product' => true, 'counterpart_healthy' => true ) );
			if ( $score <= 0 ) { continue; }
			$slow = wc_get_product( $slow_id );
			$partner = wc_get_product( $partner_id );
			if ( ! $slow || ! $partner ) { continue; }
			$cost_slow = $wpdb->get_var( $wpdb->prepare( "SELECT cost FROM {$supplier_products} WHERE product_id=%d AND is_preferred=1 LIMIT 1", $slow_id ) );
			$cost_partner = $wpdb->get_var( $wpdb->prepare( "SELECT cost FROM {$supplier_products} WHERE product_id=%d AND is_preferred=1 LIMIT 1", $partner_id ) );
			$regular = (float) $slow->get_regular_price() + (float) $partner->get_regular_price();
			$discount = SSW_Bundle_Opportunities::discount_ceiling( $regular, null === $cost_slow ? null : $cost_slow, null === $cost_partner ? null : $cost_partner, 0.35 );
			$evidence = SSW_Bundle_Opportunities::evidence( $pair['pair_orders'], $slow_orders, $partner_orders, $total_orders );
			$result[] = array( 'slow_name' => $slow->get_name(), 'slow_sku' => $slow->get_sku(), 'partner_name' => $partner->get_name(), 'partner_sku' => $partner->get_sku(), 'pair_orders' => (int) $pair['pair_orders'], 'support' => $evidence['support'], 'attach_rate_slow' => $evidence['attach_rate_a'], 'score' => $score, 'discount' => $discount );
			if ( count( $result ) >= $limit ) { break; }
		}
		usort( $result, static function ( $a, $b ) { return $a['score'] === $b['score'] ? 0 : ( $a['score'] > $b['score'] ? -1 : 1 ); } );
		return $result;
	}

	private function is_slow( $velocity, $last_sale_date, $metric_date ) {
		if ( null === $velocity ) { return false; }
		if ( (float) $velocity <= 0.05 ) { return true; }
		if ( ! $last_sale_date ) { return true; }
		return ( strtotime( $metric_date ) - strtotime( $last_sale_date ) ) >= ( 60 * DAY_IN_SECONDS );
	}

	private function is_healthy_partner( $velocity, $cover ) {
		return null !== $velocity && (float) $velocity > 0.05 && null !== $cover && (float) $cover >= 14;
	}
}
