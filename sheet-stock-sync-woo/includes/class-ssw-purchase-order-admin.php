<?php
/**
 * Purchase order admin UI.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Purchase_Order_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 22 );
		add_action( 'admin_post_ssw_create_po', array( $this, 'handle_create' ) );
		add_action( 'admin_post_ssw_transition_po', array( $this, 'handle_transition' ) );
		add_action( 'admin_post_ssw_receive_po', array( $this, 'handle_receive' ) );
	}

	public static function normalize_item_rows( $raw ) {
		$product_ids = isset( $raw['product_id'] ) && is_array( $raw['product_id'] ) ? $raw['product_id'] : array();
		$quantities  = isset( $raw['quantity'] ) && is_array( $raw['quantity'] ) ? $raw['quantity'] : array();
		$costs       = isset( $raw['unit_cost'] ) && is_array( $raw['unit_cost'] ) ? $raw['unit_cost'] : array();
		$skus        = isset( $raw['supplier_sku'] ) && is_array( $raw['supplier_sku'] ) ? $raw['supplier_sku'] : array();
		$rows = array();
		$count = max( count( $product_ids ), count( $quantities ), count( $costs ), count( $skus ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$product_id = absint( isset( $product_ids[ $i ] ) ? $product_ids[ $i ] : 0 );
			if ( ! $product_id ) { continue; }
			$qty_raw = str_replace( ',', '.', trim( (string) ( isset( $quantities[ $i ] ) ? $quantities[ $i ] : '0' ) ) );
			$cost_raw = str_replace( ',', '.', trim( (string) ( isset( $costs[ $i ] ) ? $costs[ $i ] : '0' ) ) );
			$rows[] = array(
				'product_id' => $product_id,
				'quantity' => is_numeric( $qty_raw ) ? (float) $qty_raw : 0.0,
				'unit_cost' => number_format( is_numeric( $cost_raw ) ? (float) $cost_raw : 0.0, 2, '.', '' ),
				'supplier_sku' => sanitize_text_field( isset( $skus[ $i ] ) ? $skus[ $i ] : '' ),
			);
		}
		return $rows;
	}

	public static function normalize_receipt_lines( $raw ) {
		$lines = array();
		if ( ! is_array( $raw ) ) { return $lines; }
		foreach ( $raw as $item_id => $quantity ) {
			$item_id = absint( $item_id );
			$qty_raw = str_replace( ',', '.', trim( (string) $quantity ) );
			$qty = is_numeric( $qty_raw ) ? (float) $qty_raw : 0.0;
			if ( $item_id && $qty > 0 ) { $lines[] = array( 'po_item_id' => $item_id, 'quantity' => $qty ); }
		}
		return $lines;
	}

	public function admin_menu() {
		add_submenu_page( 'sheet-stock-sync-woo', __( 'Purchase Orders', 'sheet-stock-sync-woo' ), __( 'Purchase Orders', 'sheet-stock-sync-woo' ), 'manage_woocommerce', 'ssw-purchase-orders', array( $this, 'render_page' ) );
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( $action );
	}

	public function handle_create() {
		$this->guard( 'ssw_create_po' );
		$post = wp_unslash( $_POST );
		$items = self::normalize_item_rows( isset( $post['items'] ) ? $post['items'] : array() );
		$result = SSW_Purchase_Orders::create( $post, $items );
		if ( is_wp_error( $result ) ) { $this->redirect( 'error' ); }
		$this->redirect( 'created', (int) $result );
	}

	public function handle_transition() {
		$this->guard( 'ssw_transition_po' );
		$po_id = isset( $_POST['po_id'] ) ? absint( $_POST['po_id'] ) : 0;
		$status = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : '';
		$result = SSW_Purchase_Orders::transition( $po_id, $status );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'status_updated', $po_id );
	}

	public function handle_receive() {
		$this->guard( 'ssw_receive_po' );
		$post = wp_unslash( $_POST );
		$po_id = isset( $post['po_id'] ) ? absint( $post['po_id'] ) : 0;
		$location_id = isset( $post['location_id'] ) ? absint( $post['location_id'] ) : 0;
		$token = isset( $post['request_token'] ) ? sanitize_text_field( $post['request_token'] ) : '';
		$lines = self::normalize_receipt_lines( isset( $post['received'] ) ? $post['received'] : array() );
		$result = SSW_Purchase_Orders::receive( $po_id, $location_id, $lines, $token, isset( $post['note'] ) ? $post['note'] : '' );
		if ( is_wp_error( $result ) ) { $this->redirect( 'error', $po_id ); }
		$this->redirect( ! empty( $result['duplicate'] ) ? 'receipt_duplicate' : 'received', $po_id );
	}

	private function redirect( $notice, $po_id = 0 ) {
		$args = array( 'page' => 'ssw-purchase-orders', 'ssw_notice' => $notice );
		if ( $po_id ) { $args['po_id'] = absint( $po_id ); }
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$po_id = isset( $_GET['po_id'] ) ? absint( $_GET['po_id'] ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Purchase Orders', 'sheet-stock-sync-woo' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php if ( $po_id ) { $this->render_detail( $po_id ); } else { $this->render_list_and_create(); } ?>
		</div>
		<?php
	}

	private function render_list_and_create() {
		$suppliers = SSW_Suppliers::all( true );
		$locations = SSW_Locations::all( true );
		$orders = SSW_Purchase_Orders::all();
		?>
		<h2><?php esc_html_e( 'Create purchase order', 'sheet-stock-sync-woo' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ssw_create_po"><?php wp_nonce_field( 'ssw_create_po' ); ?>
			<table class="form-table"><tbody>
			<tr><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><td><select required name="supplier_id"><option value=""><?php esc_html_e( 'Choose supplier', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $suppliers as $supplier ) : ?><option value="<?php echo esc_attr( $supplier['id'] ); ?>"><?php echo esc_html( $supplier['name'] ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><?php esc_html_e( 'Destination', 'sheet-stock-sync-woo' ); ?></th><td><select required name="location_id"><option value=""><?php esc_html_e( 'Choose location', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $locations as $location ) : ?><option value="<?php echo esc_attr( $location['id'] ); ?>"><?php echo esc_html( $location['name'] ); ?></option><?php endforeach; ?></select></td></tr>
			<tr><th><?php esc_html_e( 'PO details', 'sheet-stock-sync-woo' ); ?></th><td><input name="po_number" placeholder="<?php esc_attr_e( 'Auto if blank', 'sheet-stock-sync-woo' ); ?>"> <input name="currency" maxlength="3" size="4" value="EUR"> <label><?php esc_html_e( 'Expected', 'sheet-stock-sync-woo' ); ?> <input type="date" name="expected_at"></label></td></tr>
			<tr><th><?php esc_html_e( 'Notes', 'sheet-stock-sync-woo' ); ?></th><td><textarea name="notes" class="large-text" rows="2"></textarea></td></tr>
			</tbody></table>
			<h3><?php esc_html_e( 'Items', 'sheet-stock-sync-woo' ); ?></h3>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Supplier SKU', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Quantity', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Unit cost', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody>
			<?php for ( $i = 0; $i < 8; $i++ ) : ?><tr><td><input type="number" min="1" name="items[product_id][]"></td><td><input name="items[supplier_sku][]"></td><td><input type="number" step="0.0001" min="0" name="items[quantity][]"></td><td><input type="number" step="0.0001" min="0" name="items[unit_cost][]"></td></tr><?php endfor; ?>
			</tbody></table>
			<?php submit_button( __( 'Create draft PO', 'sheet-stock-sync-woo' ) ); ?>
		</form>

		<hr><h2><?php esc_html_e( 'Purchase orders', 'sheet-stock-sync-woo' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'PO', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Supplier', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Destination', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Status', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Subtotal', 'sheet-stock-sync-woo' ); ?></th><th></th></tr></thead><tbody>
		<?php if ( ! $orders ) : ?><tr><td colspan="6"><?php esc_html_e( 'No purchase orders yet.', 'sheet-stock-sync-woo' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $orders as $order ) : ?><tr><td><strong><?php echo esc_html( $order['po_number'] ); ?></strong></td><td><?php echo esc_html( $order['supplier_name'] ); ?></td><td><?php echo esc_html( $order['location_name'] ); ?></td><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $order['status'] ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( (float) $order['subtotal'], 2 ) . ' ' . $order['currency'] ); ?></td><td><a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssw-purchase-orders', 'po_id' => $order['id'] ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open', 'sheet-stock-sync-woo' ); ?></a></td></tr><?php endforeach; ?>
		</tbody></table>
		<?php
	}

	private function render_detail( $po_id ) {
		$po = SSW_Purchase_Orders::get( $po_id );
		if ( ! $po ) { echo '<p>' . esc_html__( 'Purchase order not found.', 'sheet-stock-sync-woo' ) . '</p>'; return; }
		$items = SSW_Purchase_Orders::items( $po_id );
		$locations = SSW_Locations::all( true );
		?>
		<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'ssw-purchase-orders' ), admin_url( 'admin.php' ) ) ); ?>">&larr; <?php esc_html_e( 'All purchase orders', 'sheet-stock-sync-woo' ); ?></a></p>
		<h2><?php echo esc_html( $po['po_number'] ); ?> — <?php echo esc_html( ucwords( str_replace( '_', ' ', $po['status'] ) ) ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Supplier SKU', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Ordered', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Received', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Remaining', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Unit cost', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody>
		<?php foreach ( $items as $item ) : ?><tr><td>#<?php echo esc_html( $item['product_id'] ); ?> <?php echo esc_html( $item['description'] ); ?></td><td><?php echo esc_html( $item['supplier_sku'] ); ?></td><td><?php echo esc_html( $item['ordered_qty'] ); ?></td><td><?php echo esc_html( $item['received_qty'] ); ?></td><td><?php echo esc_html( SSW_Purchase_Orders::remaining_quantity( $item['ordered_qty'], $item['received_qty'] ) ); ?></td><td><?php echo esc_html( $item['unit_cost'] . ' ' . $po['currency'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table>

		<?php $this->render_status_actions( $po ); ?>
		<?php if ( in_array( $po['status'], array( 'ordered', 'shipped', 'partially_received' ), true ) ) : ?>
		<h3><?php esc_html_e( 'Receive stock', 'sheet-stock-sync-woo' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_receive_po"><input type="hidden" name="po_id" value="<?php echo esc_attr( $po_id ); ?>"><input type="hidden" name="request_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'ssw_receive_po' ); ?>
		<p><label><?php esc_html_e( 'Receive into', 'sheet-stock-sync-woo' ); ?> <select name="location_id" required><?php foreach ( $locations as $location ) : ?><option value="<?php echo esc_attr( $location['id'] ); ?>" <?php selected( $po['location_id'], $location['id'] ); ?>><?php echo esc_html( $location['name'] ); ?></option><?php endforeach; ?></select></label></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Item', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Remaining', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Receive now', 'sheet-stock-sync-woo' ); ?></th></tr></thead><tbody><?php foreach ( $items as $item ) : $remaining = SSW_Purchase_Orders::remaining_quantity( $item['ordered_qty'], $item['received_qty'] ); ?><tr><td>#<?php echo esc_html( $item['product_id'] . ' ' . $item['description'] ); ?></td><td><?php echo esc_html( $remaining ); ?></td><td><input type="number" min="0" max="<?php echo esc_attr( $remaining ); ?>" step="0.0001" name="received[<?php echo esc_attr( $item['id'] ); ?>]" value="0" <?php disabled( $remaining <= 0 ); ?>></td></tr><?php endforeach; ?></tbody></table>
		<p><input class="regular-text" name="note" placeholder="<?php esc_attr_e( 'Receipt note', 'sheet-stock-sync-woo' ); ?>"></p><?php submit_button( __( 'Receive selected quantities', 'sheet-stock-sync-woo' ) ); ?></form>
		<?php endif; ?>
		<?php
	}

	private function render_status_actions( $po ) {
		$possible = array( 'approved' => __( 'Approve', 'sheet-stock-sync-woo' ), 'ordered' => __( 'Mark ordered', 'sheet-stock-sync-woo' ), 'shipped' => __( 'Mark shipped', 'sheet-stock-sync-woo' ), 'cancelled' => __( 'Cancel', 'sheet-stock-sync-woo' ) );
		echo '<h3>' . esc_html__( 'Workflow', 'sheet-stock-sync-woo' ) . '</h3><p>';
		foreach ( $possible as $status => $label ) {
			if ( ! SSW_Purchase_Orders::can_transition( $po['status'], $status ) ) { continue; }
			?><form style="display:inline-block;margin-right:6px" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_transition_po"><input type="hidden" name="po_id" value="<?php echo esc_attr( $po['id'] ); ?>"><input type="hidden" name="new_status" value="<?php echo esc_attr( $status ); ?>"><?php wp_nonce_field( 'ssw_transition_po' ); ?><button class="button" type="submit"><?php echo esc_html( $label ); ?></button></form><?php
		}
		echo '</p>';
	}

	private function render_notice() {
		if ( empty( $_GET['ssw_notice'] ) ) { return; }
		$key = sanitize_key( wp_unslash( $_GET['ssw_notice'] ) );
		$messages = array(
			'created' => __( 'Purchase order created.', 'sheet-stock-sync-woo' ),
			'status_updated' => __( 'Purchase order status updated.', 'sheet-stock-sync-woo' ),
			'received' => __( 'Receipt posted and stock updated.', 'sheet-stock-sync-woo' ),
			'receipt_duplicate' => __( 'This receipt request was already processed; stock was not added twice.', 'sheet-stock-sync-woo' ),
			'error' => __( 'The operation could not be completed.', 'sheet-stock-sync-woo' ),
		);
		if ( isset( $messages[ $key ] ) ) {
			$class = 'error' === $key ? 'notice notice-error' : 'notice notice-success is-dismissible';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
