<?php
/**
 * AJAX handlers for Sheet Stock Sync for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the two admin AJAX actions: saving one cell of the stock table,
 * and importing a bulk CSV/XLSX file.
 */
class SSW_Ajax {

	/**
	 * Constructor — wires the AJAX actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ssw_builtin_update', array( $this, 'handle_builtin_update' ) );
		add_action( 'wp_ajax_ssw_import_stock', array( $this, 'handle_import_stock' ) );
	}

	/**
	 * Save one edited cell — either a quantity or a low-stock threshold —
	 * and hand back the recalculated row so the level indicator can update
	 * without a page reload.
	 */
	public function handle_builtin_update() {
		check_ajax_referer( 'ssw_builtin_update', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ),
				403
			);
		}

		if ( ! SSW_License::is_active() ) {
			wp_send_json_error( array( 'message' => SSW_License::status()['message'] ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : 'quantity';
		$value      = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing product.', 'sheet-stock-sync-woo' ) ) );
		}

		$builtin = new SSW_Builtin();

		switch ( $field ) {
			case 'threshold':
				$result = $builtin->update_threshold( $product_id, $value );
				break;
			case 'per_box':
				$result = $builtin->update_units_per_box( $product_id, $value );
				break;
			default:
				$result = $builtin->update_quantity( $product_id, $value );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Stock changed, so the cached analytics figures (stock value, level
		// counts) are stale.
		SSW_Analytics::clear_cache();

		$result['level_html'] = SSW_Admin::level_pill( $result['level'] );

		wp_send_json_success( $result );
	}

	/**
	 * Handle a bulk stock update from an uploaded CSV/XLSX file.
	 */
	public function handle_import_stock() {
		check_ajax_referer( 'ssw_import_stock', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ),
				403
			);
		}

		if ( ! SSW_License::is_active() ) {
			wp_send_json_error( array( 'message' => SSW_License::status()['message'] ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'sheet-stock-sync-woo' ) ) );
		}

		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( ! empty( $file['error'] ) ) {
			wp_send_json_error( array( 'message' => __( 'The file failed to upload. Try again.', 'sheet-stock-sync-woo' ) ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload.', 'sheet-stock-sync-woo' ) ) );
		}

		$importer = new SSW_Import_Export();
		$report   = $importer->import( $file );

		update_option( SSW_OPTION_LOG, $report, false );
		SSW_Analytics::clear_cache();

		if ( empty( $report['success'] ) ) {
			wp_send_json_error( $report );
		}

		wp_send_json_success( $report );
	}
}
