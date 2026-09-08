<?php
/**
 * Supplier admin screen and form handlers.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Supplier_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_post_ssw_save_supplier', array( $this, 'handle_save_supplier' ) );
		add_action( 'admin_post_ssw_delete_supplier', array( $this, 'handle_delete_supplier' ) );
		add_action( 'admin_post_ssw_assign_supplier_product', array( $this, 'handle_assign_supplier_product' ) );
		add_action( 'admin_post_ssw_remove_supplier_product', array( $this, 'handle_remove_supplier_product' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Suppliers', 'sheet-stock-sync-woo' ),
			__( 'Suppliers', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-suppliers',
			array( $this, 'render_page' )
		);
	}

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $nonce_action );
	}

	public function handle_save_supplier() {
		$this->guard( 'ssw_save_supplier' );
		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		$result = SSW_Suppliers::save( wp_unslash( $_POST ), $supplier_id );
		$args = array( 'page' => 'ssw-suppliers' );
		$args['ssw_notice'] = is_wp_error( $result ) ? 'error' : 'saved';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_supplier() {
		$this->guard( 'ssw_delete_supplier' );
		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		SSW_Suppliers::delete( $supplier_id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-suppliers', 'ssw_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_assign_supplier_product() {
		$this->guard( 'ssw_assign_supplier_product' );
		$result = SSW_Suppliers::assign_product_supplier( wp_unslash( $_POST ) );
		$args = array( 'page' => 'ssw-suppliers' );
		$args['product_id'] = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$args['ssw_notice'] = is_wp_error( $result ) ? 'error' : 'assigned';
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_remove_supplier_product() {
		$this->guard( 'ssw_remove_supplier_product' );
		$product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		SSW_Suppliers::remove_product_supplier( $product_id, $supplier_id );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-suppliers', 'product_id' => $product_id, 'ssw_notice' => 'unassigned' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}
		$suppliers = SSW_Suppliers::all();
		$edit_id   = isset( $_GET['edit_supplier'] ) ? absint( $_GET['edit_supplier'] ) : 0;
		$editing   = $edit_id ? SSW_Suppliers::get( $edit_id ) : null;
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$relations  = $product_id ? SSW_Suppliers::product_suppliers( $product_id ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Suppliers', 'sheet-stock-sync-woo' ); ?></h1>
			<?php $this->render_notice(); ?>

			<h2><?php echo $editing ? esc_html__( 'Edit supplier', 'sheet-stock-sync-woo' ) : esc_html__( 'Add supplier', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_save_supplier">
				<input type="hidden" name="supplier_id" value="<?php echo esc_attr( $editing ? $editing['id'] : 0 ); ?>">
				<?php wp_nonce_field( 'ssw_save_supplier' ); ?>
				<table class="form-table"><tbody>
				<tr><th><label for="ssw-supplier-name"><?php esc_html_e( 'Name', 'sheet-stock-sync-woo' ); ?></label></th><td><input required class="regular-text" id="ssw-supplier-name" name="name" value="<?php echo esc_attr( $editing ? $editing['name'] : '' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Contact', 'sheet-stock-sync-woo' ); ?></th><td><input class="regular-text" name="contact_name" placeholder="<?php esc_attr_e( 'Contact person', 'sheet-stock-sync-woo' ); ?>" value="<?php echo esc_attr( $editing ? $editing['contact_name'] : '' ); ?>"><br><input class="regular-text" type="email" name="email" placeholder="email@example.com" value="<?php echo esc_attr( $editing ? $editing['email'] : '' ); ?>"><br><input class="regular-text" name="phone" placeholder="<?php esc_attr_e( 'Phone', 'sheet-stock-sync-woo' ); ?>" value="<?php echo esc_attr( $editing ? $editing['phone'] : '' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Website', 'sheet-stock-sync-woo' ); ?></th><td><input class="regular-text" type="url" name="website" value="<?php echo esc_attr( $editing ? $editing['website'] : '' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Defaults', 'sheet-stock-sync-woo' ); ?></th><td><input name="default_currency" size="4" maxlength="3" value="<?php echo esc_attr( $editing ? $editing['default_currency'] : 'EUR' ); ?>"> <label><?php esc_html_e( 'Lead time (days)', 'sheet-stock-sync-woo' ); ?> <input type="number" min="0" name="default_lead_time_days" value="<?php echo esc_attr( $editing ? $editing['default_lead_time_days'] : 0 ); ?>"></label></td></tr>
				<tr><th><?php esc_html_e( 'Notes', 'sheet-stock-sync-woo' ); ?></th><td><textarea class="large-text" rows="3" name="notes"><?php echo esc_textarea( $editing ? $editing['notes'] : '' ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Status', 'sheet-stock-sync-woo' ); ?></th><td><label><input type="checkbox" name="active" value="1" <?php checked( $editing ? $editing['active'] : 1, 1 ); ?>> <?php esc_html_e( 'Active', 'sheet-stock-sync-woo' ); ?></label></td></tr>
				</tbody></table>
				<?php submit_button( $editing ? __( 'Update supplier', 'sheet-stock-sync-woo' ) : __( 'Add supplier', 'sheet-stock-sync-woo' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Supplier list', 'sheet-stock-sync-woo' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Contact', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Currency', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Lead time', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Actions', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody>
			<?php if ( ! $suppliers ) : ?><tr><td colspan="5"><?php esc_html_e( 'No suppliers yet.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $suppliers as $supplier ) : ?>
			<tr><td><strong><?php echo esc_html( $supplier['name'] ); ?></strong><?php if ( ! $supplier['active'] ) : ?> — <?php esc_html_e( 'Inactive', 'sheet-stock-sync-woo' ); ?><?php endif; ?></td><td><?php echo esc_html( trim( $supplier['contact_name'] . ' ' . $supplier['email'] . ' ' . $supplier['phone'] ) ); ?></td><td><?php echo esc_html( $supplier['default_currency'] ); ?></td><td><?php echo esc_html( $supplier['default_lead_time_days'] ); ?> d</td><td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssw-suppliers', 'edit_supplier' => $supplier['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'sheet-stock-sync-woo' ); ?></a> <form style="display:inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this supplier?', 'sheet-stock-sync-woo' ) ); ?>');"><input type="hidden" name="action" value="ssw_delete_supplier"><input type="hidden" name="supplier_id" value="<?php echo esc_attr( $supplier['id'] ); ?>"><?php wp_nonce_field( 'ssw_delete_supplier' ); ?><button class="button" type="submit"><?php esc_html_e( 'Delete', 'sheet-stock-sync-woo' ); ?></button></form></td></tr>
			<?php endforeach; ?>
			</tbody></table>

			<hr>
			<h2><?php esc_html_e( 'Product purchasing data', 'sheet-stock-sync-woo' ); ?></h2>
			<p><?php esc_html_e( 'Use a WooCommerce product or variation ID. A product can have multiple suppliers, but only one preferred supplier.', 'sheet-stock-sync-woo' ); ?></p>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="ssw-suppliers"><label><?php esc_html_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?> <input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"></label> <?php submit_button( __( 'Load', 'sheet-stock-sync-woo' ), 'secondary', '', false ); ?></form>
			<?php if ( $product_id ) : ?>
				<?php $this->render_product_relations( $product_id, $relations, $suppliers ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_product_relations( $product_id, $relations, $suppliers ) {
		?>
		<table class="widefat striped" style="margin-top:16px"><thead><tr><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Supplier SKU', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Cost', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'MOQ', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Box', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Lead time', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Preferred', 'sheet-stock-sync-woo' ); ?></th><th></th></tr></thead><tbody>
		<?php if ( ! $relations ) : ?><tr><td colspan="8"><?php esc_html_e( 'No supplier relationships for this product.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $relations as $relation ) : ?><tr><td><?php echo esc_html( $relation['supplier_name'] ); ?></td><td><?php echo esc_html( $relation['supplier_sku'] ); ?></td><td><?php echo esc_html( null === $relation['cost'] ? '—' : $relation['cost'] . ' ' . $relation['currency'] ); ?></td><td><?php echo esc_html( $relation['moq'] ); ?></td><td><?php echo esc_html( $relation['units_per_box'] ); ?></td><td><?php echo esc_html( $relation['lead_time_days'] ); ?> d</td><td><?php echo $relation['is_preferred'] ? '✓' : ''; ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_remove_supplier_product"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><input type="hidden" name="supplier_id" value="<?php echo esc_attr( $relation['supplier_id'] ); ?>"><?php wp_nonce_field( 'ssw_remove_supplier_product' ); ?><button type="submit" class="button"><?php esc_html_e( 'Remove', 'sheet-stock-sync-woo' ); ?></button></form></td></tr><?php endforeach; ?>
		</tbody></table>

		<h3><?php esc_html_e( 'Add or update supplier relationship', 'sheet-stock-sync-woo' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_assign_supplier_product"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php wp_nonce_field( 'ssw_assign_supplier_product' ); ?>
		<table class="form-table"><tbody>
		<tr><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><td><select required name="supplier_id"><option value=""><?php esc_html_e( 'Choose supplier', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $suppliers as $supplier ) : if ( ! $supplier['active'] ) { continue; } ?><option value="<?php echo esc_attr( $supplier['id'] ); ?>"><?php echo esc_html( $supplier['name'] ); ?></option><?php endforeach; ?></select></td></tr>
		<tr><th><?php esc_html_e( 'Purchasing data', 'sheet-stock-sync-woo' ); ?></th><td><input name="supplier_sku" placeholder="Supplier SKU"> <input name="cost" inputmode="decimal" placeholder="Cost"> <input name="currency" maxlength="3" size="4" value="EUR"> <input type="number" min="1" name="moq" value="1" title="MOQ"> <input type="number" min="1" name="units_per_box" value="1" title="Units per box"> <input type="number" min="0" name="lead_time_days" value="0" title="Lead time days"></td></tr>
		<tr><th><?php esc_html_e( 'Preferred', 'sheet-stock-sync-woo' ); ?></th><td><label><input type="checkbox" name="is_preferred" value="1"> <?php esc_html_e( 'Make this the preferred supplier for this product', 'sheet-stock-sync-woo' ); ?></label></td></tr>
		</tbody></table><?php submit_button( __( 'Save relationship', 'sheet-stock-sync-woo' ) ); ?></form>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['ssw_notice'] ) ) {
			return;
		}
		$type = sanitize_key( wp_unslash( $_GET['ssw_notice'] ) );
		$messages = array(
			'saved'      => __( 'Supplier saved.', 'sheet-stock-sync-woo' ),
			'deleted'    => __( 'Supplier deleted.', 'sheet-stock-sync-woo' ),
			'assigned'   => __( 'Supplier relationship saved.', 'sheet-stock-sync-woo' ),
			'unassigned' => __( 'Supplier relationship removed.', 'sheet-stock-sync-woo' ),
			'error'      => __( 'The operation could not be completed.', 'sheet-stock-sync-woo' ),
		);
		if ( isset( $messages[ $type ] ) ) {
			$class = 'error' === $type ? 'notice notice-error' : 'notice notice-success is-dismissible';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $type ] ) . '</p></div>';
		}
	}
}
