<?php
/**
 * Supporting replenishment-input UI for approved #6/#7/#36.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Supplier_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_post_ssw_save_supplier', array( $this, 'save_supplier' ) );
		add_action( 'admin_post_ssw_assign_supplier_product', array( $this, 'assign' ) );
		add_action( 'admin_post_ssw_remove_supplier_product', array( $this, 'remove' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Replenishment Inputs', 'sheet-stock-sync-woo' ),
			__( 'Replenishment Inputs', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-suppliers',
			array( $this, 'render' )
		);
	}

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $nonce_action );
	}

	public function save_supplier() {
		$this->guard( 'ssw_save_supplier' );
		$data = wp_unslash( $_POST );
		$data['active'] = 1;
		$result = SSW_Suppliers::save( $data, 0 );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ssw-suppliers',
					'ssw_notice' => is_wp_error( $result ) ? 'error' : 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function assign() {
		$this->guard( 'ssw_assign_supplier_product' );
		$result = SSW_Suppliers::assign_product_supplier( wp_unslash( $_POST ) );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ssw-suppliers',
					'product_id' => $product_id,
					'ssw_notice' => is_wp_error( $result ) ? 'error' : 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function remove() {
		$this->guard( 'ssw_remove_supplier_product' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		SSW_Suppliers::remove_product_supplier( $product_id, $supplier_id );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ssw-suppliers',
					'product_id' => $product_id,
					'ssw_notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		$suppliers = SSW_Suppliers::all( true );
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$relations = $product_id ? SSW_Suppliers::product_suppliers( $product_id ) : array();
		$notice = ! empty( $_GET['ssw_notice'] ) ? sanitize_key( wp_unslash( $_GET['ssw_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Replenishment Inputs', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Supporting inputs only for Smart Replenishment, Stockout Prediction and the 90-day Purchasing/Cash Forecast.', 'sheet-stock-sync-woo' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice <?php echo 'error' === $notice ? 'notice-error' : 'notice-success'; ?> inline"><p>
					<?php echo 'error' === $notice ? esc_html__( 'The input could not be saved.', 'sheet-stock-sync-woo' ) : esc_html__( 'Saved.', 'sheet-stock-sync-woo' ); ?>
				</p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Add supplier reference', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_save_supplier">
				<?php wp_nonce_field( 'ssw_save_supplier' ); ?>
				<input required name="name" placeholder="<?php esc_attr_e( 'Supplier name', 'sheet-stock-sync-woo' ); ?>">
				<input name="default_currency" maxlength="3" size="4" value="EUR">
				<input type="number" min="0" name="default_lead_time_days" value="0" placeholder="<?php esc_attr_e( 'Lead days', 'sheet-stock-sync-woo' ); ?>">
				<button class="button"><?php esc_html_e( 'Add', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Product purchasing inputs', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="ssw-suppliers">
				<input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" placeholder="<?php esc_attr_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?>">
				<button class="button"><?php esc_html_e( 'Load', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<?php if ( $product_id ) : ?>
				<table class="widefat striped" style="margin-top:16px">
					<thead><tr><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><th>SKU</th><th><?php esc_html_e( 'Cost', 'sheet-stock-sync-woo' ); ?></th><th>MOQ</th><th><?php esc_html_e( 'Pack', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Lead time', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Preferred', 'sheet-stock-sync-woo' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $relations as $relation ) : ?>
						<tr>
							<td><?php echo esc_html( $relation['supplier_name'] ); ?></td>
							<td><?php echo esc_html( $relation['supplier_sku'] ); ?></td>
							<td><?php echo null === $relation['cost'] ? '—' : esc_html( $relation['cost'] . ' ' . $relation['currency'] ); ?></td>
							<td><?php echo esc_html( $relation['moq'] ); ?></td>
							<td><?php echo esc_html( $relation['units_per_box'] ); ?></td>
							<td><?php echo esc_html( $relation['lead_time_days'] ); ?> d</td>
							<td><?php echo $relation['is_preferred'] ? '✓' : '—'; ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="ssw_remove_supplier_product">
									<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
									<input type="hidden" name="supplier_id" value="<?php echo esc_attr( $relation['supplier_id'] ); ?>">
									<?php wp_nonce_field( 'ssw_remove_supplier_product' ); ?>
									<button class="button"><?php esc_html_e( 'Remove', 'sheet-stock-sync-woo' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Add or update input', 'sheet-stock-sync-woo' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ssw_assign_supplier_product">
					<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
					<?php wp_nonce_field( 'ssw_assign_supplier_product' ); ?>
					<select required name="supplier_id">
						<option value=""><?php esc_html_e( 'Supplier…', 'sheet-stock-sync-woo' ); ?></option>
						<?php foreach ( $suppliers as $supplier ) : ?>
							<option value="<?php echo esc_attr( $supplier['id'] ); ?>"><?php echo esc_html( $supplier['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<input name="supplier_sku" placeholder="<?php esc_attr_e( 'Supplier SKU', 'sheet-stock-sync-woo' ); ?>">
					<input name="cost" inputmode="decimal" placeholder="<?php esc_attr_e( 'Cost', 'sheet-stock-sync-woo' ); ?>">
					<input name="currency" maxlength="3" size="4" value="EUR">
					<input type="number" min="1" name="moq" value="1" title="MOQ">
					<input type="number" min="1" name="units_per_box" value="1" title="<?php esc_attr_e( 'Pack size', 'sheet-stock-sync-woo' ); ?>">
					<input type="number" min="0" name="lead_time_days" value="0" title="<?php esc_attr_e( 'Lead time days', 'sheet-stock-sync-woo' ); ?>">
					<label><input type="checkbox" name="is_preferred" value="1" checked> <?php esc_html_e( 'Preferred', 'sheet-stock-sync-woo' ); ?></label>
					<button class="button button-primary"><?php esc_html_e( 'Save input', 'sheet-stock-sync-woo' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
