<?php
/**
 * What Changed? + Today Action Center admin screen.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Operations_Dashboard {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 8 );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Operations Dashboard', 'sheet-stock-sync-woo' ),
			__( 'Operations Dashboard', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-operations-dashboard',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$changed = $this->what_changed();
		$actions = $this->actions();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Operations Dashboard', 'sheet-stock-sync-woo' ); ?></h1>
			<h2><?php esc_html_e( 'What Changed?', 'sheet-stock-sync-woo' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'Latest comparable data: %1$s vs %2$s', 'sheet-stock-sync-woo' ), $changed['current_date'] ? $changed['current_date'] : '—', $changed['previous_date'] ? $changed['previous_date'] : '—' ) ); ?></p>
			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 24px">
				<?php $this->metric_card( __( 'Revenue', 'sheet-stock-sync-woo' ), wc_price( $changed['revenue']['current'] ), $changed['revenue'] ); ?>
				<?php $this->metric_card( __( 'Units', 'sheet-stock-sync-woo' ), number_format_i18n( $changed['units']['current'], 2 ), $changed['units'] ); ?>
				<?php $this->metric_card( __( 'Orders', 'sheet-stock-sync-woo' ), number_format_i18n( $changed['orders']['current'], 0 ), $changed['orders'] ); ?>
				<div class="card" style="min-width:180px"><strong><?php esc_html_e( 'New stockouts', 'sheet-stock-sync-woo' ); ?></strong><br><span style="font-size:24px"><?php echo esc_html( $changed['new_stockouts'] ); ?></span></div>
				<div class="card" style="min-width:180px"><strong><?php esc_html_e( 'Active alerts', 'sheet-stock-sync-woo' ); ?></strong><br><span style="font-size:24px"><?php echo esc_html( $changed['active_alerts'] ); ?></span></div>
				<div class="card" style="min-width:180px"><strong><?php esc_html_e( 'Late POs', 'sheet-stock-sync-woo' ); ?></strong><br><span style="font-size:24px"><?php echo esc_html( $changed['late_pos'] ); ?></span></div>
			</div>

			<h2><?php esc_html_e( 'Today Action Center', 'sheet-stock-sync-woo' ); ?></h2>
			<p><?php esc_html_e( 'Prioritized from active approved Smart Alerts. Each card explains why it matters and what you can do.', 'sheet-stock-sync-woo' ); ?></p>
			<?php if ( ! $actions ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'No active operational actions right now.', 'sheet-stock-sync-woo' ); ?></p></div><?php endif; ?>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
			<?php foreach ( $actions as $action ) : ?>
				<div class="card" style="max-width:none">
					<div><strong><?php echo esc_html( $action['title'] ); ?></strong> <span style="float:right"><?php echo esc_html( number_format_i18n( $action['priority_score'], 0 ) ); ?>/100</span></div>
					<p><strong><?php esc_html_e( 'Why now:', 'sheet-stock-sync-woo' ); ?></strong> <?php echo esc_html( $action['why_now'] ); ?></p>
					<p><strong><?php esc_html_e( 'Estimated impact:', 'sheet-stock-sync-woo' ); ?></strong> <?php echo $action['estimated_impact'] > 0 ? wp_kses_post( wc_price( $action['estimated_impact'] ) ) : esc_html__( 'Not reliably quantified yet', 'sheet-stock-sync-woo' ); ?></p>
					<p><strong><?php esc_html_e( 'Action:', 'sheet-stock-sync-woo' ); ?></strong> <?php echo esc_html( $action['recommended_action'] ); ?></p>
					<a class="button button-primary" href="<?php echo esc_url( $this->action_url( $action ) ); ?>"><?php esc_html_e( 'Open', 'sheet-stock-sync-woo' ); ?></a>
				</div>
			<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private function what_changed() {
		global $wpdb;
		$sales = $wpdb->prefix . 'ssw_sales_daily';
		$snapshots = $wpdb->prefix . 'ssw_inventory_snapshots_daily';
		$alerts_table = $wpdb->prefix . 'ssw_alerts';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$dates = $wpdb->get_col( "SELECT DISTINCT fact_date FROM {$sales} ORDER BY fact_date DESC LIMIT 2" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$current_date = isset( $dates[0] ) ? $dates[0] : null;
		$previous_date = isset( $dates[1] ) ? $dates[1] : null;
		$current = $current_date ? $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(revenue),0) revenue, COALESCE(SUM(units),0) units FROM {$sales} WHERE fact_date=%s", $current_date ), ARRAY_A ) : array( 'revenue' => 0, 'units' => 0 );
		$previous = $previous_date ? $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(revenue),0) revenue, COALESCE(SUM(units),0) units FROM {$sales} WHERE fact_date=%s", $previous_date ), ARRAY_A ) : array( 'revenue' => 0, 'units' => 0 );
		$current_orders = $current_date ? $this->woocommerce_order_count( $current_date ) : 0;
		$previous_orders = $previous_date ? $this->woocommerce_order_count( $previous_date ) : 0;
		$new_stockouts = 0;
		if ( $current_date && $previous_date ) {
			$sql = "SELECT COUNT(*) FROM (SELECT c.product_id FROM (SELECT product_id,SUM(quantity) qty FROM {$snapshots} WHERE snapshot_date=%s GROUP BY product_id) c LEFT JOIN (SELECT product_id,SUM(quantity) qty FROM {$snapshots} WHERE snapshot_date=%s GROUP BY product_id) p ON p.product_id=c.product_id WHERE c.qty<=0 AND COALESCE(p.qty,0)>0) x";
			$new_stockouts = (int) $wpdb->get_var( $wpdb->prepare( $sql, $current_date, $previous_date ) );
		}
		$now = current_time( 'mysql' );
		$active_alerts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$alerts_table} WHERE state='open' OR (state='snoozed' AND (snoozed_until IS NULL OR snoozed_until<=%s))", $now ) );
		$today = current_time( 'Y-m-d' );
		$late_pos = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$po} WHERE status IN ('ordered','shipped','partially_received') AND expected_at IS NOT NULL AND DATE(expected_at)<%s", $today ) );
		return array(
			'current_date' => $current_date,
			'previous_date' => $previous_date,
			'revenue' => SSW_Action_Center::delta( $current['revenue'], $previous['revenue'] ),
			'units' => SSW_Action_Center::delta( $current['units'], $previous['units'] ),
			'orders' => SSW_Action_Center::delta( $current_orders, $previous_orders ),
			'new_stockouts' => $new_stockouts,
			'active_alerts' => $active_alerts,
			'late_pos' => $late_pos,
		);
	}

	private function woocommerce_order_count( $date ) {
		$result = wc_get_orders( array( 'status' => array( 'processing', 'completed' ), 'date_created' => $date . ' 00:00:00...' . $date . ' 23:59:59', 'limit' => 1, 'paginate' => true, 'return' => 'ids' ) );
		return is_object( $result ) && isset( $result->total ) ? (int) $result->total : 0;
	}

	private function actions() {
		$alerts = SSW_Alerts::list_active( 100 );
		$result = array();
		foreach ( $alerts as $alert ) {
			$alert['context'] = json_decode( (string) $alert['context_json'], true );
			$result[] = SSW_Action_Center::from_alert( $alert );
		}
		usort( $result, static function ( $a, $b ) { return $a['priority_score'] === $b['priority_score'] ? 0 : ( $a['priority_score'] > $b['priority_score'] ? -1 : 1 ); } );
		return array_slice( $result, 0, 20 );
	}

	private function action_url( $action ) {
		switch ( $action['type'] ) {
			case 'late_purchase_order': return admin_url( 'admin.php?page=ssw-purchase-orders&po_id=' . absint( $action['purchase_order_id'] ) );
			case 'bundle_opportunity': return admin_url( 'admin.php?page=ssw-bundle-recommendations' );
			case 'dead_stock':
			case 'low_health':
			case 'predicted_stockout': return admin_url( 'admin.php?page=ssw-inventory-intelligence' );
			default: return admin_url( 'admin.php?page=sheet-stock-sync-woo' );
		}
	}

	private function metric_card( $label, $value_html, $delta ) {
		?><div class="card" style="min-width:180px"><strong><?php echo esc_html( $label ); ?></strong><br><span style="font-size:24px"><?php echo wp_kses_post( $value_html ); ?></span><br><small><?php if ( null === $delta['percent'] ) { esc_html_e( 'No comparable percentage', 'sheet-stock-sync-woo' ); } else { echo esc_html( sprintf( '%+.1f%%', $delta['percent'] ) ); } ?></small></div><?php
	}
}
