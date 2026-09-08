<?php
/**
 * Mobile-friendly barcode, lookup and stock-count admin workflows.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

final class SSW_Barcode_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 23 );
		add_action( 'admin_post_ssw_assign_barcode', array( $this, 'handle_assign_barcode' ) );
		add_action( 'admin_post_ssw_barcode_count', array( $this, 'handle_count' ) );
	}

	public static function normalize_count_request( $data ) {
		$counted = isset( $data['counted_quantity'] ) ? str_replace( ',', '.', trim( (string) $data['counted_quantity'] ) ) : '0';
		return array(
			'product_id'       => absint( isset( $data['product_id'] ) ? $data['product_id'] : 0 ),
			'location_id'      => absint( isset( $data['location_id'] ) ? $data['location_id'] : 0 ),
			'counted_quantity' => is_numeric( $counted ) ? (float) $counted : 0.0,
			'note'             => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
	}

	public static function normalize_lookup_request( $data ) {
		return array(
			'barcode' => SSW_Barcodes::normalize_code( isset( $data['barcode'] ) ? $data['barcode'] : '' ),
		);
	}

	public function admin_menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Barcode Scanner', 'sheet-stock-sync-woo' ),
			__( 'Barcode Scanner', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-barcodes',
			array( $this, 'render_page' )
		);
	}

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $nonce_action );
	}

	public function handle_assign_barcode() {
		$this->guard( 'ssw_assign_barcode' );
		$result = SSW_Barcodes::assign( wp_unslash( $_POST ) );
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$this->redirect( is_wp_error( $result ) ? 'error' : 'barcode_saved', $product_id );
	}

	public function handle_count() {
		$this->guard( 'ssw_barcode_count' );
		$data = self::normalize_count_request( wp_unslash( $_POST ) );
		if ( ! $data['product_id'] || ! $data['location_id'] || ! wc_get_product( $data['product_id'] ) ) {
			$this->redirect( 'error', $data['product_id'] );
		}

		SSW_Locations::seed_product_to_main( $data['product_id'] );
		$current = SSW_Locations::get_balance( $data['product_id'], $data['location_id'] );
		$delta = SSW_Barcodes::count_delta( $current, $data['counted_quantity'] );
		if ( 0.0 === $delta ) {
			$this->redirect( 'count_match', $data['product_id'] );
		}

		$result = SSW_Stock_Ledger::adjust_and_sync( array(
			'product_id'  => $data['product_id'],
			'location_id' => $data['location_id'],
			'delta'       => $delta,
			'type'        => 'stock_count',
			'source'      => 'barcode_count',
			'source_ref'  => 'count-' . wp_generate_uuid4(),
			'note'        => $data['note'],
		) );
		$this->redirect( is_wp_error( $result ) ? 'error' : 'count_posted', $data['product_id'] );
	}

	private function redirect( $notice, $product_id = 0 ) {
		$args = array( 'page' => 'ssw-barcodes', 'ssw_notice' => $notice );
		if ( $product_id ) { $args['product_id'] = absint( $product_id ); }
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}

		$lookup = self::normalize_lookup_request( wp_unslash( $_GET ) );
		$product_id = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		if ( $lookup['barcode'] ) {
			$found = SSW_Barcodes::lookup( $lookup['barcode'] );
			if ( $found ) { $product_id = $found; }
		}
		$product = $product_id ? wc_get_product( $product_id ) : null;
		$locations = SSW_Locations::all( true );
		if ( $product ) { SSW_Locations::seed_product_to_main( $product_id ); }
		?>
		<div class="wrap ssw-barcode-wrap">
			<h1><?php esc_html_e( 'Barcode Scanner & Stock Count', 'sheet-stock-sync-woo' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php esc_html_e( 'Use the camera when supported, or enter/scan a code manually. Nothing changes stock until you explicitly submit a count.', 'sheet-stock-sync-woo' ); ?></p>

			<div style="max-width:760px;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px;">
				<h2><?php esc_html_e( 'Find product', 'sheet-stock-sync-woo' ); ?></h2>
				<form id="ssw-barcode-lookup" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="ssw-barcodes">
					<input id="ssw-barcode-input" class="regular-text" name="barcode" value="<?php echo esc_attr( $lookup['barcode'] ); ?>" autocomplete="off" inputmode="text" placeholder="EAN / UPC / custom barcode">
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Find', 'sheet-stock-sync-woo' ); ?></button>
					<button class="button" type="button" id="ssw-start-camera"><?php esc_html_e( 'Use camera', 'sheet-stock-sync-woo' ); ?></button>
				</form>
				<div id="ssw-camera-box" hidden style="margin-top:12px;">
					<video id="ssw-camera-video" playsinline muted style="width:100%;max-width:520px;border-radius:8px;background:#111;"></video>
					<p id="ssw-camera-status"></p>
					<button class="button" type="button" id="ssw-stop-camera"><?php esc_html_e( 'Stop camera', 'sheet-stock-sync-woo' ); ?></button>
				</div>
			</div>

			<?php if ( $lookup['barcode'] && ! $product ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No product is assigned to that barcode. You can assign it below using a product or variation ID.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Assign barcode', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_assign_barcode">
				<?php wp_nonce_field( 'ssw_assign_barcode' ); ?>
				<label><?php esc_html_e( 'Product / variation ID', 'sheet-stock-sync-woo' ); ?> <input type="number" min="1" name="product_id" required value="<?php echo esc_attr( $product_id ); ?>"></label>
				<label><?php esc_html_e( 'Barcode', 'sheet-stock-sync-woo' ); ?> <input name="barcode" required value="<?php echo esc_attr( $lookup['barcode'] ); ?>"></label>
				<label><input type="checkbox" name="is_primary" value="1" checked> <?php esc_html_e( 'Primary', 'sheet-stock-sync-woo' ); ?></label>
				<button class="button" type="submit"><?php esc_html_e( 'Save barcode', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<?php if ( $product ) : $this->render_product_panel( $product, $locations ); endif; ?>
		</div>
		<?php $this->render_scanner_script();
	}

	private function render_product_panel( $product, $locations ) {
		$product_id = $product->get_id();
		$barcodes = SSW_Barcodes::for_product( $product_id );
		?>
		<hr>
		<h2><?php echo esc_html( $product->get_formatted_name() ); ?></h2>
		<p><strong><?php esc_html_e( 'Woo stock:', 'sheet-stock-sync-woo' ); ?></strong> <?php echo esc_html( $product->get_stock_quantity() ); ?></p>
		<?php if ( $barcodes ) : ?>
			<p><strong><?php esc_html_e( 'Barcodes:', 'sheet-stock-sync-woo' ); ?></strong> <?php foreach ( $barcodes as $row ) { echo '<code style="margin-right:6px">' . esc_html( $row['barcode'] ) . '</code>'; } ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Stock count', 'sheet-stock-sync-woo' ); ?></h3>
		<p><?php esc_html_e( 'Enter the quantity physically counted at one location. The plugin calculates the variance, records an audit movement, then synchronizes aggregate WooCommerce stock.', 'sheet-stock-sync-woo' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Post this stock count adjustment?', 'sheet-stock-sync-woo' ) ); ?>');">
			<input type="hidden" name="action" value="ssw_barcode_count">
			<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
			<?php wp_nonce_field( 'ssw_barcode_count' ); ?>
			<select name="location_id" required><option value=""><?php esc_html_e( 'Location…', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( $locations as $location ) : ?><option value="<?php echo esc_attr( $location['id'] ); ?>"><?php echo esc_html( $location['name'] ); ?></option><?php endforeach; ?></select>
			<input name="counted_quantity" type="number" min="0" step="0.0001" inputmode="decimal" required placeholder="Counted qty">
			<input name="note" class="regular-text" placeholder="<?php esc_attr_e( 'Count note / reason', 'sheet-stock-sync-woo' ); ?>">
			<button class="button button-primary" type="submit"><?php esc_html_e( 'Post count', 'sheet-stock-sync-woo' ); ?></button>
		</form>
		<?php
	}

	private function render_notice() {
		if ( empty( $_GET['ssw_notice'] ) ) { return; }
		$key = sanitize_key( wp_unslash( $_GET['ssw_notice'] ) );
		$messages = array(
			'barcode_saved' => __( 'Barcode saved.', 'sheet-stock-sync-woo' ),
			'count_posted'  => __( 'Stock count posted and WooCommerce stock synchronized.', 'sheet-stock-sync-woo' ),
			'count_match'   => __( 'Count matches the current location quantity; no stock movement was needed.', 'sheet-stock-sync-woo' ),
			'error'         => __( 'The operation could not be completed.', 'sheet-stock-sync-woo' ),
		);
		if ( isset( $messages[ $key ] ) ) {
			$class = 'error' === $key ? 'notice notice-error inline' : 'notice notice-success inline';
			echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( $messages[ $key ] ) . '</p></div>';
		}
	}

	private function render_scanner_script() {
		?>
		<script>
		(function(){
			const start = document.getElementById('ssw-start-camera');
			const stop = document.getElementById('ssw-stop-camera');
			const box = document.getElementById('ssw-camera-box');
			const video = document.getElementById('ssw-camera-video');
			const status = document.getElementById('ssw-camera-status');
			const input = document.getElementById('ssw-barcode-input');
			const form = document.getElementById('ssw-barcode-lookup');
			let stream = null;
			let timer = null;
			let detector = null;
			function stopCamera(){ if(timer){clearInterval(timer);timer=null;} if(stream){stream.getTracks().forEach(t=>t.stop());stream=null;} box.hidden=true; }
			async function startCamera(){
				if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){ status.textContent='Camera API is not available in this browser. Use manual entry.'; box.hidden=false; return; }
				if(!('BarcodeDetector' in window)){ status.textContent='Native barcode detection is not available in this browser. Use manual entry or a keyboard-style scanner.'; box.hidden=false; return; }
				try {
					detector = new BarcodeDetector({formats:['ean_13','ean_8','upc_a','upc_e','code_128','code_39','qr_code']});
					stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
					video.srcObject = stream; await video.play(); box.hidden=false; status.textContent='Point the camera at a barcode.';
					timer = setInterval(async function(){ try { const codes = await detector.detect(video); if(codes.length && codes[0].rawValue){ input.value=codes[0].rawValue; stopCamera(); form.submit(); } } catch(e){} }, 500);
				} catch(e) { box.hidden=false; status.textContent='Camera could not be started. Check browser permission or use manual entry.'; }
			}
			if(start){start.addEventListener('click', startCamera);} if(stop){stop.addEventListener('click', stopCamera);} window.addEventListener('pagehide', stopCamera);
		})();
		</script>
		<?php
	}
}
