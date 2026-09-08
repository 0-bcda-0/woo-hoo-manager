<?php
/**
 * Admin screen/lifecycle actions for approved Smart Alerts.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Alert_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 26 );
		add_action( 'admin_post_ssw_alert_state', array( $this, 'handle_state' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Smart Alerts', 'sheet-stock-sync-woo' ),
			__( 'Smart Alerts', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-smart-alerts',
			array( $this, 'render_page' )
		);
	}

	public function handle_state() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to manage alerts.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_alert_state' );
		$id = isset( $_POST['alert_id'] ) ? absint( wp_unslash( $_POST['alert_id'] ) ) : 0;
		$state = isset( $_POST['alert_state'] ) ? sanitize_key( wp_unslash( $_POST['alert_state'] ) ) : 'open';
		$snoozed_until = null;
		if ( 'snoozed' === $state ) { $snoozed_until = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ); }
		SSW_Alerts::set_state( $id, $state, $snoozed_until, get_current_user_id() );
		wp_safe_redirect( admin_url( 'admin.php?page=ssw-smart-alerts' ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$alerts = SSW_Alerts::list_active( 200 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Smart Alerts', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Deduplicated operational alerts generated only from approved inventory, purchase-order and bundle evidence.', 'sheet-stock-sync-woo' ); ?></p>
			<table class="widefat striped"><thead><tr>
				<th><?php esc_html_e( 'Severity', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Alert', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Entity', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Evidence', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Last seen', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'sheet-stock-sync-woo' ); ?></th>
			</tr></thead><tbody>
			<?php if ( ! $alerts ) : ?><tr><td colspan="6"><?php esc_html_e( 'No active alerts.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $alerts as $alert ) : $context = json_decode( (string) $alert['context_json'], true ); ?>
			<tr>
				<td><strong><?php echo esc_html( ucfirst( $alert['severity'] ) ); ?></strong></td>
				<td><?php echo esc_html( $this->label( $alert['type'] ) ); ?></td>
				<td><?php echo wp_kses_post( $this->entity_link( $alert ) ); ?></td>
				<td><code><?php echo esc_html( wp_json_encode( is_array( $context ) ? $context : array() ) ); ?></code></td>
				<td><?php echo esc_html( $alert['last_seen'] ); ?></td>
				<td><?php $this->state_buttons( (int) $alert['id'] ); ?></td>
			</tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function state_buttons( $id ) {
		foreach ( array( 'snoozed' => __( 'Snooze 24h', 'sheet-stock-sync-woo' ), 'resolved' => __( 'Resolve', 'sheet-stock-sync-woo' ), 'dismissed' => __( 'Dismiss', 'sheet-stock-sync-woo' ) ) as $state => $label ) {
			?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 4px 4px 0">
			<input type="hidden" name="action" value="ssw_alert_state">
			<input type="hidden" name="alert_id" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="alert_state" value="<?php echo esc_attr( $state ); ?>">
			<?php wp_nonce_field( 'ssw_alert_state' ); ?>
			<button class="button button-small" type="submit"><?php echo esc_html( $label ); ?></button></form><?php
		}
	}

	private function entity_link( $alert ) {
		if ( ! empty( $alert['product_id'] ) ) {
			$product = wc_get_product( absint( $alert['product_id'] ) );
			$name = $product ? $product->get_name() : '#' . absint( $alert['product_id'] );
			$url = get_edit_post_link( absint( $alert['product_id'] ), '' );
			return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name );
		}
		if ( ! empty( $alert['purchase_order_id'] ) ) {
			return '<a href="' . esc_url( admin_url( 'admin.php?page=ssw-purchase-orders&po_id=' . absint( $alert['purchase_order_id'] ) ) ) . '">PO #' . esc_html( absint( $alert['purchase_order_id'] ) ) . '</a>';
		}
		return '—';
	}

	private function label( $type ) {
		$labels = array(
			'actual_stockout' => __( 'Actual stockout', 'sheet-stock-sync-woo' ),
			'predicted_stockout' => __( 'Predicted stockout before replenishment', 'sheet-stock-sync-woo' ),
			'low_health' => __( 'Low Inventory Health', 'sheet-stock-sync-woo' ),
			'dead_stock' => __( 'Dead-stock escalation', 'sheet-stock-sync-woo' ),
			'bundle_opportunity' => __( 'Bundle opportunity', 'sheet-stock-sync-woo' ),
			'late_purchase_order' => __( 'Late purchase order', 'sheet-stock-sync-woo' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
	}
}
