<?php
/**
 * Admin screen for approved #34 90-day purchasing/cash forecast.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Purchasing_Plan_Admin {
	const BUDGET_OPTION = 'ssw_purchasing_budgets';
	const SAFETY_DAYS_OPTION = 'ssw_purchasing_safety_days';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_post_ssw_save_purchasing_plan_settings', array( $this, 'save_settings' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( '90-day Purchasing', 'sheet-stock-sync-woo' ),
			__( '90-day Purchasing', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-purchasing-plan',
			array( $this, 'render_page' )
		);
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_save_purchasing_plan_settings' );
		$budget_text = isset( $_POST['budgets'] ) ? sanitize_textarea_field( wp_unslash( $_POST['budgets'] ) ) : '';
		$safety_days = isset( $_POST['safety_days'] ) ? max( 0, min( 90, absint( $_POST['safety_days'] ) ) ) : 5;
		update_option( self::BUDGET_OPTION, $budget_text, false );
		update_option( self::SAFETY_DAYS_OPTION, $safety_days, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-purchasing-plan', 'settings-updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$budget_text = (string) get_option( self::BUDGET_OPTION, '' );
		$safety_days = max( 0, min( 90, absint( get_option( self::SAFETY_DAYS_OPTION, 5 ) ) ) );
		$budgets = SSW_Purchasing_Planner::parse_budget_lines( $budget_text );
		$rows = $this->build_plan_rows( $safety_days );
		$cash_rows = array();
		$totals_by_currency = array();
		foreach ( $rows as $row ) {
			if ( null !== $row['expected_cash'] && $row['suggested_order_date'] ) {
				$cash_rows[] = array(
					'supplier_id' => $row['supplier_id'],
					'order_date' => $row['suggested_order_date'],
					'expected_cash' => $row['expected_cash'],
					'currency' => $row['currency'],
				);
				$totals_by_currency[ $row['currency'] ] = isset( $totals_by_currency[ $row['currency'] ] ) ? $totals_by_currency[ $row['currency'] ] + $row['expected_cash'] : $row['expected_cash'];
			}
		}
		$grouped = SSW_Purchasing_Planner::group_cash( $cash_rows );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '90-day Purchasing & Cash Forecast', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Advisory plan based on current stock, incoming open POs, supplier lead time, MOQ/pack size, purchase cost and deterministic demand velocity. It never places orders automatically.', 'sheet-stock-sync-woo' ); ?></p>

			<?php if ( isset( $_GET['settings-updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Planning settings saved.', 'sheet-stock-sync-woo' ); ?></p></div><?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:760px;margin:18px 0 24px;padding:16px;background:#fff;border:1px solid #dcdcde">
				<input type="hidden" name="action" value="ssw_save_purchasing_plan_settings">
				<?php wp_nonce_field( 'ssw_save_purchasing_plan_settings' ); ?>
				<table class="form-table" role="presentation"><tbody>
				<tr><th><label for="ssw-safety-days"><?php esc_html_e( 'Safety stock days', 'sheet-stock-sync-woo' ); ?></label></th><td><input id="ssw-safety-days" name="safety_days" type="number" min="0" max="90" value="<?php echo esc_attr( $safety_days ); ?>" class="small-text"></td></tr>
				<tr><th><label for="ssw-budgets"><?php esc_html_e( '90-day budgets by currency', 'sheet-stock-sync-woo' ); ?></label></th><td><textarea id="ssw-budgets" name="budgets" rows="4" cols="40" placeholder="EUR=5000&#10;USD=1000"><?php echo esc_textarea( $budget_text ); ?></textarea><p class="description"><?php esc_html_e( 'One currency per line. Different currencies are never added together.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
				</tbody></table>
				<?php submit_button( __( 'Save planning settings', 'sheet-stock-sync-woo' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Cash outlook by currency', 'sheet-stock-sync-woo' ); ?></h2>
			<div style="display:flex;gap:12px;flex-wrap:wrap;margin:12px 0 24px">
			<?php if ( ! $totals_by_currency ) : ?><p><?php esc_html_e( 'No quantified purchases are currently suggested.', 'sheet-stock-sync-woo' ); ?></p><?php endif; ?>
			<?php foreach ( $totals_by_currency as $currency => $planned ) : $pressure = SSW_Purchasing_Planner::budget_pressure( $planned, isset( $budgets[ $currency ] ) ? $budgets[ $currency ] : null ); ?>
				<div class="card" style="min-width:220px"><strong><?php echo esc_html( $currency ); ?></strong><br><span style="font-size:24px"><?php echo esc_html( number_format_i18n( $planned, 2 ) ); ?></span><br>
				<?php if ( 'over_budget' === $pressure['status'] ) : ?><span style="color:#b32d2e"><?php echo esc_html( sprintf( __( 'Over budget by %s', 'sheet-stock-sync-woo' ), number_format_i18n( $pressure['over_by'], 2 ) ) ); ?></span><?php elseif ( 'within_budget' === $pressure['status'] ) : ?><span><?php echo esc_html( sprintf( __( 'Budget usage: %s%%', 'sheet-stock-sync-woo' ), number_format_i18n( $pressure['utilization_percent'], 1 ) ) ); ?></span><?php else : ?><span><?php esc_html_e( 'Budget not configured', 'sheet-stock-sync-woo' ); ?></span><?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>

			<h2><?php esc_html_e( 'Suggested purchases', 'sheet-stock-sync-woo' ); ?></h2>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Velocity/day', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'On hand', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Incoming', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Order date', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Qty', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Cash', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Confidence', 'sheet-stock-sync-woo' ); ?></th>
			</tr></thead><tbody>
			<?php if ( ! $rows ) : ?><tr><td colspan="9"><?php esc_html_e( 'No products have enough purchasing data yet.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $rows as $row ) : ?>
			<tr><td><a href="<?php echo esc_url( get_edit_post_link( $row['product_id'] ) ); ?>"><?php echo esc_html( $row['product_name'] ); ?></a></td><td><?php echo esc_html( $row['supplier_name'] ); ?></td><td><?php echo esc_html( number_format_i18n( $row['daily_velocity'], 3 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['on_hand'], 2 ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row['incoming'], 2 ) ); ?></td><td><?php echo esc_html( $row['suggested_order_date'] ? $row['suggested_order_date'] : '—' ); ?></td><td><?php echo esc_html( number_format_i18n( $row['suggested_order_quantity'], 2 ) ); ?></td><td><?php echo null !== $row['expected_cash'] ? esc_html( $row['currency'] . ' ' . number_format_i18n( $row['expected_cash'], 2 ) ) : esc_html__( 'Cost missing', 'sheet-stock-sync-woo' ); ?></td><td><?php echo esc_html( $row['confidence'] ? $row['confidence'] : '—' ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<?php $this->render_group_table( __( 'Cash by week', 'sheet-stock-sync-woo' ), $grouped['by_week_currency'] ); ?>
			<?php $this->render_group_table( __( 'Cash by supplier', 'sheet-stock-sync-woo' ), $grouped['by_supplier_currency'] ); ?>
		</div>
		<?php
	}

	private function build_plan_rows( $safety_days ) {
		global $wpdb;
		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$relations = $wpdb->prefix . 'ssw_supplier_products';
		$suppliers = $wpdb->prefix . 'ssw_suppliers';
		$po = $wpdb->prefix . 'ssw_purchase_orders';
		$items = $wpdb->prefix . 'ssw_purchase_order_items';
		$sql = "SELECT m.product_id,m.weighted_velocity,m.available_quantity,m.confidence_state,r.supplier_id,r.cost,r.currency,r.moq,r.units_per_box,r.lead_time_days,s.name supplier_name FROM {$metrics} m INNER JOIN (SELECT product_id,MAX(metric_date) metric_date FROM {$metrics} GROUP BY product_id) latest ON latest.product_id=m.product_id AND latest.metric_date=m.metric_date INNER JOIN {$relations} r ON r.product_id=m.product_id AND r.is_preferred=1 LEFT JOIN {$suppliers} s ON s.id=r.supplier_id WHERE m.weighted_velocity>0 ORDER BY m.weighted_velocity DESC LIMIT 500";
		$base_rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$today = current_time( 'Y-m-d' );
		$result = array();
		foreach ( $base_rows as $base ) {
			$product_id = absint( $base['product_id'] );
			$incoming_sql = "SELECT COALESCE(SUM(i.ordered_qty-i.received_qty),0) FROM {$items} i INNER JOIN {$po} p ON p.id=i.po_id WHERE i.product_id=%d AND p.status IN ('ordered','shipped','partially_received')";
			$incoming = (float) $wpdb->get_var( $wpdb->prepare( $incoming_sql, $product_id ) );
			$plan = SSW_Purchasing_Planner::plan_product( array(
				'as_of_date' => $today,
				'daily_velocity' => $base['weighted_velocity'],
				'on_hand' => $base['available_quantity'],
				'incoming' => $incoming,
				'lead_time_days' => $base['lead_time_days'],
				'safety_stock_days' => $safety_days,
				'moq' => $base['moq'],
				'pack_size' => $base['units_per_box'],
				'unit_cost' => $base['cost'],
				'currency' => $base['currency'],
				'confidence' => $base['confidence_state'],
				'horizon_days' => 90,
			) );
			$product = wc_get_product( $product_id );
			$result[] = array_merge( $plan, array(
				'product_id' => $product_id,
				'product_name' => $product ? $product->get_name() : '#' . $product_id,
				'supplier_id' => absint( $base['supplier_id'] ),
				'supplier_name' => $base['supplier_name'] ? $base['supplier_name'] : '#' . absint( $base['supplier_id'] ),
				'on_hand' => (float) $base['available_quantity'],
				'incoming' => $incoming,
			) );
		}
		usort( $result, function( $a, $b ) {
			if ( null === $a['suggested_order_date'] ) { return 1; }
			if ( null === $b['suggested_order_date'] ) { return -1; }
			return strcmp( $a['suggested_order_date'], $b['suggested_order_date'] );
		} );
		return $result;
	}

	private function render_group_table( $title, $rows ) {
		if ( ! $rows ) { return; }
		?><h2 style="margin-top:28px"><?php echo esc_html( $title ); ?></h2><table class="widefat striped" style="max-width:650px"><thead><tr><th><?php esc_html_e( 'Group', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Planned cash', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody><?php foreach ( $rows as $key => $amount ) : ?><tr><td><?php echo esc_html( $key ); ?></td><td><?php echo esc_html( number_format_i18n( $amount, 2 ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php
	}
}
