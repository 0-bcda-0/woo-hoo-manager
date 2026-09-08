<?php
/**
 * Approved #35 Forecast vs Actual admin view.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Forecast_Accuracy_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 27 );
	}

	public function menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Forecast vs Actual', 'sheet-stock-sync-woo' ),
			__( 'Forecast vs Actual', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-forecast-accuracy',
			array( $this, 'render' )
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}

		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$horizon = isset( $_GET['horizon'] ) ? max( 7, min( 90, absint( $_GET['horizon'] ) ) ) : 30;
		$rows = $product_id ? SSW_Forecast_Accuracy::product_rows( $product_id, $horizon, 24 ) : array();
		$simple_rows = array();
		foreach ( $rows as $row ) {
			$simple_rows[] = array(
				'predicted' => $row['predicted'],
				'actual' => $row['actual'],
			);
		}
		$metrics = SSW_Forecast_Accuracy::metrics( $simple_rows );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Forecast vs Actual', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Compare saved forecasts with realized WooCommerce unit sales. Accuracy is not shown when there is no realized demand.', 'sheet-stock-sync-woo' ); ?></p>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="ssw-forecast-accuracy">
				<input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" required>
				<select name="horizon">
					<?php foreach ( array( 7, 14, 30, 60, 90 ) as $value ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $horizon, $value ); ?>><?php echo esc_html( $value ); ?> <?php esc_html_e( 'days', 'sheet-stock-sync-woo' ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Compare', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<?php if ( $product_id ) : ?>
				<h2>
					<?php esc_html_e( 'Accuracy', 'sheet-stock-sync-woo' ); ?>:
					<?php echo null === $metrics['accuracy_percent'] ? '—' : esc_html( number_format_i18n( $metrics['accuracy_percent'], 1 ) . '%' ); ?>
				</h2>
				<p>
					<?php
					$wape = null === $metrics['wape'] ? '—' : number_format_i18n( $metrics['wape'] * 100, 1 ) . '%';
					echo esc_html( 'Predicted ' . $metrics['predicted_units'] . ' / Actual ' . $metrics['actual_units'] . ' / WAPE ' . $wape );
					?>
				</p>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Generated', 'sheet-stock-sync-woo' ); ?></th>
						<th><?php esc_html_e( 'Horizon', 'sheet-stock-sync-woo' ); ?></th>
						<th><?php esc_html_e( 'Predicted', 'sheet-stock-sync-woo' ); ?></th>
						<th><?php esc_html_e( 'Actual', 'sheet-stock-sync-woo' ); ?></th>
						<th><?php esc_html_e( 'Confidence', 'sheet-stock-sync-woo' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['generated_for_date'] ); ?></td>
							<td><?php echo esc_html( $row['horizon_date'] ); ?></td>
							<td><?php echo esc_html( $row['predicted'] ); ?></td>
							<td><?php echo esc_html( $row['actual'] ); ?></td>
							<td><?php echo esc_html( $row['confidence_state'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
