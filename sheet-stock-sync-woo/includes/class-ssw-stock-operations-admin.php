<?php
/**
 * Approved #17 Stock Adjustments and #18 Stock Count workflows.
 * Uses the internal Main location only; multi-warehouse is not exposed.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Stock_Operations_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 23 );
		add_action( 'admin_post_ssw_stock_adjustment', array( $this, 'handle_adjustment' ) );
		add_action( 'admin_post_ssw_stock_count', array( $this, 'handle_count' ) );
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Stock Operations', 'sheet-stock-sync-woo' ),
			__( 'Stock Operations', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-stock-operations',
			array( $this, 'render_page' )
		);
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $action );
	}

	private function main_location_id() {
		return (int) SSW_Locations::ensure_main_warehouse();
	}

	private function redirect( $notice, $product_id = 0 ) {
		$args = array( 'page' => 'ssw-stock-operations', 'ssw_notice' => $notice );
		if ( $product_id ) { $args['product_id'] = absint( $product_id ); }
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function normalize_adjustment( $data ) {
		$delta = isset( $data['delta'] ) ? str_replace( ',', '.', trim( (string) $data['delta'] ) ) : '0';
		$allowed = array( 'stock_count', 'damaged', 'expired', 'sample', 'theft_loss', 'supplier_correction', 'return', 'other' );
		$reason = sanitize_key( isset( $data['reason'] ) ? $data['reason'] : 'other' );
		if ( ! in_array( $reason, $allowed, true ) ) { $reason = 'other'; }
		return array(
			'product_id' => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'delta'      => is_numeric( $delta ) ? (float) $delta : 0.0,
			'reason'     => $reason,
			'note'       => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
	}

	public static function normalize_count( $data ) {
		$counted = isset( $data['counted_quantity'] ) ? str_replace( ',', '.', trim( (string) $data['counted_quantity'] ) ) : '0';
		return array(
			'product_id'       => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'counted_quantity' => is_numeric( $counted ) ? max( 0.0, (float) $counted ) : 0.0,
			'note'             => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
	}

	public function handle_adjustment() {
		$this->guard( 'ssw_stock_adjustment' );
		$data = self::normalize_adjustment( wp_unslash( $_POST ) );
		$product = $data['product_id'] ? wc_get_product( $data['product_id'] ) : null;
		$location_id = $this->main_location_id();
		if ( ! $product || ! $location_id || 0.0 === $data['delta'] ) { $this->redirect( 'error', $data['product_id'] ); }
		SSW_Locations::seed_product_to_main( $data['product_id'] );
		$result = SSW_Stock_Ledger::adjust_and_sync( array(
			'product_id'  => $data['product_id'],
			'location_id' => $location_id,
			'delta'       => $data['delta'],
			'type'        => $data['reason'],
			'source'      => 'stock_adjustment',
			'source_ref'  => 'adjust-' . wp_generate_uuid4(),
			'note'        => $data['note'],
		) );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'adjusted', $data['product_id'] );
	}

	public function handle_count() {
		$this->guard( 'ssw_stock_count' );
		$data = self::normalize_count( wp_unslash( $_POST ) );
		$product = $data['product_id'] ? wc_get_product( $data['product_id'] ) : null;
		$location_id = $this->main_location_id();
		if ( ! $product || ! $location_id ) { $this->redirect( 'error', $data['product_id'] ); }
		SSW_Locations::seed_product_to_main( $data['product_id'] );
		$current = SSW_Locations::get_balance( $data['product_id'], $location_id );
		$delta = $data['counted_quantity'] - $current;
		if ( 0.0 === (float) $delta ) { $this->redirect( 'count_match', $data['product_id'] ); }
		$result = SSW_Stock_Ledger::adjust_and_sync( array(
			'product_id'  => $data['product_id'],
			'location_id' => $location_id,
			'delta'       => $delta,
			'type'        => 'stock_count',
			'source'      => 'stock_count',
			'source_ref'  => 'count-' . wp_generate_uuid4(),
			'note'        => $data['note'],
		) );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'counted', $data['product_id'] );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( $product ) { SSW_Locations::seed_product_to_main( $product_id ); }
		$location_id = $this->main_location_id();
		$current = $product && $location_id ? SSW_Locations::get_balance( $product_id, $location_id ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Stock Operations', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Approved stock adjustment and physical inventory-count workflows. Every change is audited and synchronized to WooCommerce.', 'sheet-stock-sync-woo' ); ?></p>
			<?php $this->render_notice(); ?>
			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="ssw-stock-operations">
				<label><?php esc_html_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?> <input type="number" min="1" name="product_id" value="<?php echo esc_attr( $product_id ); ?>" required></label>
				<button class="button" type="submit"><?php esc_html_e( 'Open', 'sheet-stock-sync-woo' ); ?></button>
			</form>
			<?php if ( $product ) : ?>
				<hr><h2><?php echo esc_html( $product->get_formatted_name() ); ?></h2>
				<p><strong><?php esc_html_e( 'Current stock:', 'sheet-stock-sync-woo' ); ?></strong> <?php echo esc_html( $current ); ?></p>
				<h3><?php esc_html_e( 'Adjust stock', 'sheet-stock-sync-woo' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Post this stock adjustment?', 'sheet-stock-sync-woo' ) ); ?>');">
					<input type="hidden" name="action" value="ssw_stock_adjustment"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php wp_nonce_field( 'ssw_stock_adjustment' ); ?>
					<input name="delta" type="number" step="0.0001" required placeholder="+24 / -3">
					<select name="reason"><option value="stock_count">Stock count</option><option value="damaged">Damaged</option><option value="expired">Expired</option><option value="sample">Sample</option><option value="theft_loss">Theft / loss</option><option value="supplier_correction">Supplier correction</option><option value="return">Return</option><option value="other">Other</option></select>
					<input name="note" class="regular-text" placeholder="<?php esc_attr_e( 'Note', 'sheet-stock-sync-woo' ); ?>">
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Post adjustment', 'sheet-stock-sync-woo' ); ?></button>
				</form>
				<h3><?php esc_html_e( 'Physical stock count', 'sheet-stock-sync-woo' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Apply the variance from this physical count?', 'sheet-stock-sync-woo' ) ); ?>');">
					<input type="hidden" name="action" value="ssw_stock_count"><input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>"><?php wp_nonce_field( 'ssw_stock_count' ); ?>
					<input name="counted_quantity" type="number" min="0" step="0.0001" required placeholder="Counted quantity">
					<input name="note" class="regular-text" placeholder="<?php esc_attr_e( 'Count note', 'sheet-stock-sync-woo' ); ?>">
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Apply count', 'sheet-stock-sync-woo' ); ?></button>
				</form>
			<?php endif; ?>
		</div><?php
	}

	private function render_notice() {
		$key = isset( $_GET['ssw_notice'] ) ? sanitize_key( wp_unslash( $_GET['ssw_notice'] ) ) : '';
		$messages = array(
			'adjusted'    => __( 'Stock adjustment posted.', 'sheet-stock-sync-woo' ),
			'counted'     => __( 'Physical count applied and stock synchronized.', 'sheet-stock-sync-woo' ),
			'count_match' => __( 'Count matches current stock; no adjustment was needed.', 'sheet-stock-sync-woo' ),
			'error'       => __( 'The operation could not be completed.', 'sheet-stock-sync-woo' ),
		);
		if ( isset( $messages[ $key ] ) ) {
			$class = 'error' === $key ? 'notice notice-error inline' : 'notice notice-success inline';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}
}
