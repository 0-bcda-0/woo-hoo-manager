<?php
/**
 * Inventory locations and manual stock adjustment admin UI.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Location_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 21 );
		add_action( 'admin_post_ssw_save_location', array( $this, 'handle_save_location' ) );
		add_action( 'admin_post_ssw_adjust_location_stock', array( $this, 'handle_adjust_stock' ) );
		add_action( 'admin_post_ssw_transfer_location_stock', array( $this, 'handle_transfer_stock' ) );
	}

	public static function normalize_adjustment_request( $data ) {
		$delta = isset( $data['delta'] ) ? str_replace( ',', '.', trim( (string) $data['delta'] ) ) : '0';
		return array(
			'product_id'  => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'location_id' => absint( isset( $data['location_id'] ) ? $data['location_id'] : 0 ),
			'delta'       => is_numeric( $delta ) ? (float) $delta : 0.0,
			'note'        => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Locations', 'sheet-stock-sync-woo' ),
			__( 'Locations', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-locations',
			array( $this, 'render_page' )
		);
	}

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $nonce_action );
	}

	public function handle_save_location() {
		$this->guard( 'ssw_save_location' );
		$location_id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;
		$result = SSW_Locations::save( wp_unslash( $_POST ), $location_id );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'location_saved' );
	}

	public function handle_adjust_stock() {
		$this->guard( 'ssw_adjust_location_stock' );
		$data = self::normalize_adjustment_request( wp_unslash( $_POST ) );
		$product = wc_get_product( $data['product_id'] );
		if ( ! $product ) {
			$this->redirect( 'product_missing', $data['product_id'] );
		}
		SSW_Locations::seed_product_to_main( $data['product_id'] );
		$result = SSW_Stock_Ledger::adjust_and_sync( array(
			'product_id'  => $data['product_id'],
			'location_id' => $data['location_id'],
			'delta'       => $data['delta'],
			'type'        => 'adjustment',
			'source'      => 'manual',
			'note'        => $data['note'],
		) );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'stock_adjusted', $data['product_id'] );
	}

	public function handle_transfer_stock() {
		$this->guard( 'ssw_transfer_location_stock' );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$from_id = isset( $_POST['from_location_id'] ) ? absint( $_POST['from_location_id'] ) : 0;
		$to_id = isset( $_POST['to_location_id'] ) ? absint( $_POST['to_location_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? (float) str_replace( ',', '.', (string) wp_unslash( $_POST['quantity'] ) ) : 0.0;
		if ( $quantity <= 0 ) {
			$this->redirect( 'error', $product_id );
		}
		SSW_Locations::seed_product_to_main( $product_id );
		$ref = 'manual-' . wp_generate_uuid4();
		$result = SSW_Stock_Ledger::transfer( $product_id, $from_id, $to_id, $quantity, $ref );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'stock_transferred', $product_id );
	}

	private function redirect( $notice, $product_id = 0 ) {
		$args = array( 'page' => 'ssw-locations', 'ssw_notice' => $notice );
		if ( $product_id ) {
			$args['product_id'] = absint( $product_id );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}
		$locations = SSW_Locations::all();
		$edit_id = isset( $_GET['edit_location'] ) ? absint( $_GET['edit_location'] ) : 0;
		$editing = $edit_id ? SSW_Locations::get( $edit_id ) : null;
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( $product ) {
			SSW_Locations::seed_product_to_main( $product_id );
		}
		$balances = $product ? SSW_Locations::product_balances( $product_id ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Inventory Locations', 'sheet-stock-sync-woo' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php esc_html_e( 'WooCommerce stock is the aggregate of all active sellable locations. Transfers between locations do not change aggregate stock.', 'sheet-stock-sync-woo' ); ?></p>

			<h2><?php echo $editing ? esc_html__( 'Edit location', 'sheet-stock-sync-woo' ) : esc_html__( 'Add location', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_save_location">
				<input type="hidden" name="location_id" value="<?php echo esc_attr( $editing ? $editing['id'] : 0 ); ?>">
				<?php wp_nonce_field( 'ssw_save_location' ); ?>
				<table class="form-table"><tbody>
				<tr><th><?php esc_html_e( 'Name', 'sheet-stock-sync-woo' ); ?></th><td><input required class="regular-text" name="name" value="<?php echo esc_attr( $editing ? $editing['name'] : '' ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Code', 'sheet-stock-sync-woo' ); ?></th><td><input required name="code" value="<?php echo esc_attr( $editing ? $editing['code'] : '' ); ?>"><p class="description"><?php esc_html_e( 'Stable internal identifier, e.g. zagreb-store.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Availability', 'sheet-stock-sync-woo' ); ?></th><td><label><input type="checkbox" name="active" value="1" <?php checked( $editing ? $editing['active'] : 1, 1 ); ?>> <?php esc_html_e( 'Active', 'sheet-stock-sync-woo' ); ?></label><br><label><input type="checkbox" name="is_sellable" value="1" <?php checked( $editing ? $editing['is_sellable'] : 1, 1 ); ?>> <?php esc_html_e( 'Counts toward WooCommerce sellable stock', 'sheet-stock-sync-woo' ); ?></label></td></tr>
				</tbody></table>
				<?php submit_button( $editing ? __( 'Update location', 'sheet-stock-sync-woo' ) : __( 'Add location', 'sheet-stock-sync-woo' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Locations', 'sheet-stock-sync-woo' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Code', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Sellable', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Active', 'sheet-stock-sync-woo' ); ?></th><th></th></tr></thead><tbody>
			<?php foreach ( $locations as $location ) : ?><tr><td><?php echo esc_html( $location['name'] ); ?></td><td><code><?php echo esc_html( $location['code'] ); ?></code></td><td><?php echo $location['is_sellable'] ? '✓' : '—'; ?></td><td><?php echo $location['active'] ? '✓' : '—'; ?></td><td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssw-locations', 'edit_location' => $location['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Edit', 'sheet-stock-sync-woo' ); ?></a></td></tr><?php endforeach; ?>
			</tbody></table>

			<hr>
			<h2><?php esc_html_e( 'Product location stock', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="ssw-locations"><label><?php esc_html_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?> <input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"></label> <?php submit_button( __( 'Load', 'sheet-stock-sync-woo' ), 'secondary', '', false ); ?></form>
			<?php if ( $product_id && ! $product ) : ?><div class="notice notice-error inline"><p><?php esc_html_e( 'WooCommerce product or variation not found.', 'sheet-stock-sync-woo' ); ?></p></div><?php endif; ?>
			<?php if ( $product ) : $this->render_product_stock( $product, $balances, $locations ); endif; ?>
		</div>
		<?php
	}

	private function render_product_stock( $product, $balances, $locations ) {
		$product_id = $product->get_id();
		$by_location = array();
		foreach ( $balances as $balance ) { $by_location[ (int) $balance['location_id'] ] = $balance; }
		?>
		<h3><?php echo esc_html( $product->get_formatted_name() ); ?></h3>
		<p><?php esc_html_e( 'Woo aggregate:', 'sheet-stock-sync-woo' ); ?> <strong><?php echo esc_html( $product->get_stock_quantity() ); ?></strong></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Location', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Quantity', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Manual adjustment', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody>
		<?php foreach ( $locations as $location ) : $qty = isset( $by_location[ (int) $location['id'] ] ) ? $by_location[ (int) $location['id'] ]['quantity'] : 0; ?>
		<tr><td><?php echo esc_html( $location['name'] ); ?><?php if ( ! $location['is_sellable'] ) : ?> <em><?php esc_html_e( '(non-sellable)', 'sheet-stock-sync-woo' ); ?></em><?php endif; ?></td><td><?php echo esc_html( $qty ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_adjust_location_stock"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><input type="hidden" name="location_id" value="<?php echo esc_attr( $location['id'] ); ?>"><?php wp_nonce_field( 'ssw_adjust_location_stock' ); ?><input name="delta" required inputmode="decimal" size="8" placeholder="+5 / -2"> <input name="note" size="30" placeholder="<?php esc_attr_e( 'Reason / note', 'sheet-stock-sync-woo' ); ?>"> <button class="button" type="submit"><?php esc_html_e( 'Adjust', 'sheet-stock-sync-woo' ); ?></button></form></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h3><?php esc_html_e( 'Transfer stock', 'sheet-stock-sync-woo' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_transfer_location_stock"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php wp_nonce_field( 'ssw_transfer_location_stock' ); ?><select name="from_location_id" required><option value=""><?php esc_html_e( 'From…', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $locations as $location ) : if ( ! $location['active'] ) { continue; } ?><option value="<?php echo esc_attr( $location['id'] ); ?>"><?php echo esc_html( $location['name'] ); ?></option><?php endforeach; ?></select> <select name="to_location_id" required><option value=""><?php esc_html_e( 'To…', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $locations as $location ) : if ( ! $location['active'] ) { continue; } ?><option value="<?php echo esc_attr( $location['id'] ); ?>"><?php echo esc_html( $location['name'] ); ?></option><?php endforeach; ?></select> <input name="quantity" type="number" min="0.0001" step="0.0001" required placeholder="Qty"> <button class="button button-primary" type="submit"><?php esc_html_e( 'Transfer', 'sheet-stock-sync-woo' ); ?></button></form>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['ssw_notice'] ) ) { return; }
		$key = sanitize_key( wp_unslash( $_GET['ssw_notice'] ) );
		$messages = array(
			'location_saved'    => __( 'Location saved.', 'sheet-stock-sync-woo' ),
			'stock_adjusted'    => __( 'Stock adjusted and WooCommerce aggregate synchronized.', 'sheet-stock-sync-woo' ),
			'stock_transferred' => __( 'Stock transferred. Aggregate WooCommerce stock is unchanged.', 'sheet-stock-sync-woo' ),
			'product_missing'   => __( 'WooCommerce product or variation not found.', 'sheet-stock-sync-woo' ),
			'error'             => __( 'The operation could not be completed.', 'sheet-stock-sync-woo' ),
		);
		if ( isset( $messages[ $key ] ) ) {
			$class = in_array( $key, array( 'error', 'product_missing' ), true ) ? 'notice notice-error' : 'notice notice-success is-dismissible';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
