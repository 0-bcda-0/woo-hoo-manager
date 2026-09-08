<?php
/**
 * WordPress admin UI for Sheet Stock Sync for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin's single top-level admin page and its tabs. The class is
 * deliberately view-heavy: the actual product/order calculations live in
 * SSW_Builtin, SSW_Low_Stock and SSW_Analytics so they can also be reused by
 * e-mail/export/AJAX code.
 */
class SSW_Admin {

	/**
	 * Constructor — register menu, assets, settings save and screen actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_ssw_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_ssw_save_license', array( $this, 'handle_save_license' ) );
		add_action( 'admin_post_ssw_refresh_analytics', array( $this, 'handle_refresh_analytics' ) );
	}

	/**
	 * Register the top-level WooCommerce stock-manager page.
	 */
	public function admin_menu() {
		add_menu_page(
			__( 'Stock Manager', 'sheet-stock-sync-woo' ),
			__( 'Stock Manager', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'sheet-stock-sync-woo',
			array( $this, 'render_page' ),
			'dashicons-chart-line',
			56
		);
	}

	/**
	 * Load assets only on this plugin's screen.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_sheet-stock-sync-woo' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ssw-admin', SSW_PLUGIN_URL . 'assets/css/admin.css', array(), SSW_VERSION );
		wp_enqueue_script( 'ssw-admin', SSW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SSW_VERSION, true );

		wp_localize_script(
			'ssw-admin',
			'SSWAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'builtinNonce' => wp_create_nonce( 'ssw_builtin_update' ),
				'importNonce'  => wp_create_nonce( 'ssw_import_stock' ),
				'i18n'         => array(
					'saving'    => __( 'Saving…', 'sheet-stock-sync-woo' ),
					'saved'     => __( 'Saved', 'sheet-stock-sync-woo' ),
					'saveFail'  => __( 'Could not save. Try again.', 'sheet-stock-sync-woo' ),
					'pickFile'  => __( 'Choose a .csv or .xlsx file first.', 'sheet-stock-sync-woo' ),
					'importing' => __( 'Importing…', 'sheet-stock-sync-woo' ),
					'importBtn' => __( 'Import stock', 'sheet-stock-sync-woo' ),
					'importFail'=> __( 'Import failed. Try again.', 'sheet-stock-sync-woo' ),
					'showing'   => __( 'Showing %1$d of %2$d products', 'sheet-stock-sync-woo' ),
				),
			)
		);
	}

	/**
	 * Page shell and navigation tabs.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'stock';

		$tabs = array(
			'stock'     => __( 'Stock', 'sheet-stock-sync-woo' ),
			'lowstock'  => __( 'Running low', 'sheet-stock-sync-woo' ),
			'analytics' => __( 'Analytics', 'sheet-stock-sync-woo' ),
			'settings'  => __( 'Settings', 'sheet-stock-sync-woo' ),
			'license'   => __( 'Licence', 'sheet-stock-sync-woo' ),
		);

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'stock';
		}

		?>
		<div class="wrap ssw-wrap">
			<h1><?php esc_html_e( 'Stock Manager for WooCommerce', 'sheet-stock-sync-woo' ); ?></h1>

			<nav class="nav-tab-wrapper ssw-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'page' => 'sheet-stock-sync-woo', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php $this->render_license_notice(); ?>
			<?php $this->render_tab( $tab ); ?>
		</div>
		<?php
	}

	/**
	 * Dispatch to the selected tab renderer.
	 *
	 * @param string $tab Current tab.
	 */
	private function render_tab( $tab ) {
		switch ( $tab ) {
			case 'lowstock':
				$this->render_lowstock_tab();
				break;
			case 'analytics':
				$this->render_analytics_tab();
				break;
			case 'settings':
				$this->render_settings_tab();
				break;
			case 'license':
				$this->render_license_tab();
				break;
			case 'stock':
			default:
				$this->render_stock_tab();
		}
	}

	private function render_stock_tab() {
		$builtin = new SSW_Builtin();
		$rows    = $builtin->get_rows();

		$categories = array();
		foreach ( $rows as $row ) {
			foreach ( $row['categories'] as $cat ) {
				$categories[ $cat ] = true;
			}
		}
		$categories = array_keys( $categories );
		sort( $categories, SORT_NATURAL | SORT_FLAG_CASE );

		?>
		<div class="ssw-toolbar">
			<input type="search" id="ssw-search" placeholder="<?php esc_attr_e( 'Search product or SKU…', 'sheet-stock-sync-woo' ); ?>">
			<select id="ssw-filter-category">
				<option value=""><?php esc_html_e( 'All categories', 'sheet-stock-sync-woo' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( strtolower( $cat ) ); ?>"><?php echo esc_html( $cat ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="ssw-filter-level">
				<option value=""><?php esc_html_e( 'All stock levels', 'sheet-stock-sync-woo' ); ?></option>
				<option value="out"><?php esc_html_e( 'Out of stock', 'sheet-stock-sync-woo' ); ?></option>
				<option value="low"><?php esc_html_e( 'Running low', 'sheet-stock-sync-woo' ); ?></option>
				<option value="in"><?php esc_html_e( 'In stock', 'sheet-stock-sync-woo' ); ?></option>
			</select>
			<span id="ssw-count" class="ssw-muted"></span>
		</div>

		<div class="ssw-table-wrap">
		<table class="widefat striped ssw-table" id="ssw-stock-table">
			<thead><tr>
				<th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'SKU', 'sheet-stock-sync-woo' ); ?></th>
				<th class="ssw-num"><?php esc_html_e( 'Quantity', 'sheet-stock-sync-woo' ); ?></th>
				<th class="ssw-num"><?php esc_html_e( 'Low-stock threshold', 'sheet-stock-sync-woo' ); ?></th>
				<th class="ssw-num"><?php esc_html_e( 'Pieces per box', 'sheet-stock-sync-woo' ); ?></th>
				<th><?php esc_html_e( 'Level', 'sheet-stock-sync-woo' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $row ) :
				$search = strtolower( $row['name'] . ' ' . $row['sku'] );
				$cats   = strtolower( implode( '|', $row['categories'] ) );
				?>
				<tr data-search="<?php echo esc_attr( $search ); ?>" data-cats="<?php echo esc_attr( $cats ); ?>" data-level="<?php echo esc_attr( $row['level'] ); ?>">
					<td><strong><?php echo esc_html( $row['name'] ); ?></strong></td>
					<td><code><?php echo esc_html( $row['sku'] ); ?></code></td>
					<td class="ssw-num"><input type="number" step="1" class="small-text" data-field="quantity" data-product-id="<?php echo esc_attr( $row['id'] ); ?>" value="<?php echo esc_attr( $row['qty'] ); ?>"></td>
					<td class="ssw-num"><input type="number" min="0" step="1" class="small-text" data-field="threshold" data-product-id="<?php echo esc_attr( $row['id'] ); ?>" value="<?php echo esc_attr( $row['threshold'] ); ?>"></td>
					<td class="ssw-num"><input type="number" min="1" step="1" class="small-text" data-field="per_box" data-product-id="<?php echo esc_attr( $row['id'] ); ?>" value="<?php echo esc_attr( $row['per_box'] ); ?>" placeholder="—"></td>
					<td><?php echo wp_kses_post( self::level_pill( $row['level'] ) ); ?></td>
					<td><span class="ssw-rowmsg" aria-live="polite"></span></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>

		<div class="ssw-import-card">
			<h2><?php esc_html_e( 'Bulk stock update', 'sheet-stock-sync-woo' ); ?></h2>
			<p><?php esc_html_e( 'Upload a CSV or Excel (.xlsx) file containing SKU and quantity columns. Only the SKUs present in the file are changed.', 'sheet-stock-sync-woo' ); ?></p>
			<input type="file" id="ssw-import-file" accept=".csv,.xlsx,.xlsm">
			<button type="button" class="button button-primary" id="ssw-import-button"><?php esc_html_e( 'Import stock', 'sheet-stock-sync-woo' ); ?></button>
			<span class="spinner" id="ssw-import-spinner"></span>
			<div id="ssw-import-result"></div>
			<?php
			$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=ssw_export_csv' ), 'ssw_export_csv' );
			?>
			<p><a href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export current stock as CSV', 'sheet-stock-sync-woo' ); ?></a></p>
		</div>
		<?php
	}

	private function render_lowstock_tab() {
		$lowstock = new SSW_Low_Stock();
		$rows     = $lowstock->get_rows();

		if ( isset( $_GET['ssw_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['ssw_notice'] ) );
			if ( 'sent' === $notice ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Low-stock report sent.', 'sheet-stock-sync-woo' ) . '</p></div>';
			} elseif ( 'empty' === $notice ) {
				echo '<div class="notice notice-info"><p>' . esc_html__( 'Nothing is currently below its threshold, so no report was sent.', 'sheet-stock-sync-woo' ) . '</p></div>';
			}
		}

		$po_url   = wp_nonce_url( admin_url( 'admin-post.php?action=ssw_export_purchase_order' ), 'ssw_export_purchase_order' );
		$send_url = wp_nonce_url( admin_url( 'admin-post.php?action=ssw_send_report_now' ), 'ssw_send_report_now' );
		?>
		<div class="ssw-section-head">
			<div>
				<h2><?php esc_html_e( 'Products that need attention', 'sheet-stock-sync-woo' ); ?></h2>
				<p><?php esc_html_e( 'Out-of-stock products and products at or below their own low-stock threshold.', 'sheet-stock-sync-woo' ); ?></p>
			</div>
			<div class="ssw-actions">
				<a class="button" href="<?php echo esc_url( $send_url ); ?>"><?php esc_html_e( 'Send report now', 'sheet-stock-sync-woo' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( $po_url ); ?>"><?php esc_html_e( 'Download purchase order CSV', 'sheet-stock-sync-woo' ); ?></a>
			</div>
		</div>

		<?php if ( empty( $rows ) ) : ?>
			<div class="ssw-empty-state"><span class="dashicons dashicons-yes-alt"></span><h3><?php esc_html_e( 'Everything looks stocked.', 'sheet-stock-sync-woo' ); ?></h3><p><?php esc_html_e( 'No products are currently at or below their threshold.', 'sheet-stock-sync-woo' ); ?></p></div>
		<?php else : ?>
			<div class="ssw-table-wrap"><table class="widefat striped ssw-table"><thead><tr>
				<th><?php esc_html_e( 'Product', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'SKU', 'sheet-stock-sync-woo' ); ?></th><th><?php esc_html_e( 'Level', 'sheet-stock-sync-woo' ); ?></th><th class="ssw-num"><?php esc_html_e( 'In stock', 'sheet-stock-sync-woo' ); ?></th><th class="ssw-num"><?php esc_html_e( 'Threshold', 'sheet-stock-sync-woo' ); ?></th><th class="ssw-num"><?php esc_html_e( 'Pieces / box', 'sheet-stock-sync-woo' ); ?></th><th class="ssw-num"><?php esc_html_e( 'Order', 'sheet-stock-sync-woo' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $rows as $row ) : ?>
			<tr><td><strong><?php echo esc_html( $row['name'] ); ?></strong></td><td><code><?php echo esc_html( $row['sku'] ); ?></code></td><td><?php echo wp_kses_post( self::level_pill( $row['level'] ) ); ?></td><td class="ssw-num"><?php echo esc_html( $row['qty'] ); ?></td><td class="ssw-num"><?php echo esc_html( $row['threshold'] ); ?></td><td class="ssw-num"><?php echo '' === $row['per_box'] ? '—' : esc_html( $row['per_box'] ); ?></td><td class="ssw-num"><strong><?php echo esc_html( '' !== $row['boxes'] ? sprintf( __( '%1$d box(es) / %2$d pcs', 'sheet-stock-sync-woo' ), $row['boxes'], $row['order_pieces'] ) : $row['suggested'] ); ?></strong></td></tr>
			<?php endforeach; ?>
			</tbody></table></div>
		<?php endif; ?>

		<?php $log = get_option( SSW_OPTION_REPORT_LOG, array() ); if ( ! empty( $log ) && is_array( $log ) ) : ?>
			<h3><?php esc_html_e( 'Recent report activity', 'sheet-stock-sync-woo' ); ?></h3>
			<ul class="ssw-log"><?php foreach ( array_slice( $log, 0, 5 ) as $entry ) : ?><li><code><?php echo esc_html( $entry['time'] ); ?></code> — <?php echo esc_html( $entry['message'] ); ?></li><?php endforeach; ?></ul>
		<?php endif; ?>
		<?php
	}

	private function render_analytics_tab() {
		$analytics = new SSW_Analytics();
		$report    = $analytics->get_report();
		$this_m    = $report['this_month'];
		$last_m    = $report['last_month'];
		$currency  = $report['currency'];
		$refresh   = wp_nonce_url( admin_url( 'admin-post.php?action=ssw_refresh_analytics' ), 'ssw_refresh_analytics' );
		?>
		<div class="ssw-section-head"><div><h2><?php echo esc_html( $report['month_label'] ); ?></h2><p><?php esc_html_e( 'Sales and stock signals from WooCommerce orders.', 'sheet-stock-sync-woo' ); ?></p></div><a class="button" href="<?php echo esc_url( $refresh ); ?>"><?php esc_html_e( 'Refresh analytics', 'sheet-stock-sync-woo' ); ?></a></div>
		<div class="ssw-kpis">
			<?php echo wp_kses_post( $this->kpi_card( __( 'Revenue', 'sheet-stock-sync-woo' ), $currency . ' ' . number_format_i18n( $this_m['revenue'], 2 ), SSW_Analytics::change( $this_m['revenue'], $last_m['revenue'] ) ) ); ?>
			<?php echo wp_kses_post( $this->kpi_card( __( 'Orders', 'sheet-stock-sync-woo' ), number_format_i18n( $this_m['orders'] ), SSW_Analytics::change( $this_m['orders'], $last_m['orders'] ) ) ); ?>
			<?php echo wp_kses_post( $this->kpi_card( __( 'Units sold', 'sheet-stock-sync-woo' ), number_format_i18n( $this_m['items'] ), SSW_Analytics::change( $this_m['items'], $last_m['items'] ) ) ); ?>
			<?php echo wp_kses_post( $this->kpi_card( __( 'Average order', 'sheet-stock-sync-woo' ), $currency . ' ' . number_format_i18n( $this_m['average'], 2 ), SSW_Analytics::change( $this_m['average'], $last_m['average'] ) ) ); ?>
		</div>
		<div class="ssw-grid-2">
			<div class="ssw-card"><h3><?php esc_html_e( 'Revenue by day', 'sheet-stock-sync-woo' ); ?></h3><?php echo SSW_Chart::daily_revenue( $report['daily'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div class="ssw-card"><h3><?php esc_html_e( 'Best sellers this month', 'sheet-stock-sync-woo' ); ?></h3><?php echo SSW_Chart::top_products( $report['top_products'], $currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="ssw-grid-2">
			<div class="ssw-card"><h3><?php esc_html_e( 'Stock overview', 'sheet-stock-sync-woo' ); ?></h3><p class="ssw-big-number"><?php echo esc_html( $currency . ' ' . number_format_i18n( $report['stock_value'], 2 ) ); ?></p><p><?php esc_html_e( 'Retail value of managed stock currently on hand.', 'sheet-stock-sync-woo' ); ?></p><div class="ssw-level-summary"><span><?php echo wp_kses_post( self::level_pill( 'in' ) ); ?> <?php echo esc_html( $report['levels']['in'] ); ?></span><span><?php echo wp_kses_post( self::level_pill( 'low' ) ); ?> <?php echo esc_html( $report['levels']['low'] ); ?></span><span><?php echo wp_kses_post( self::level_pill( 'out' ) ); ?> <?php echo esc_html( $report['levels']['out'] ); ?></span></div></div>
			<div class="ssw-card"><h3><?php printf( esc_html__( 'Stock not sold in %d days', 'sheet-stock-sync-woo' ), SSW_Analytics::DEAD_STOCK_DAYS ); ?></h3><?php if ( empty( $report['dead_stock'] ) ) : ?><p><?php esc_html_e( 'No stocked products fall into this list.', 'sheet-stock-sync-woo' ); ?></p><?php else : ?><table class="widefat"><tbody><?php foreach ( $report['dead_stock'] as $dead ) : ?><tr><td><?php echo esc_html( $dead['name'] ); ?><br><code><?php echo esc_html( $dead['sku'] ); ?></code></td><td class="ssw-num"><?php echo esc_html( number_format_i18n( $dead['qty'] ) ); ?> ×</td><td class="ssw-num"><?php echo esc_html( $currency . ' ' . number_format_i18n( $dead['value'], 2 ) ); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
		</div>
		<?php if ( ! empty( $report['order_cap_hit'] ) ) : ?><p class="description"><?php printf( esc_html__( 'Analytics are based on the most recent %d paid orders.', 'sheet-stock-sync-woo' ), SSW_Analytics::MAX_ORDERS ); ?></p><?php endif; ?>
		<?php
	}

	private function render_settings_tab() {
		$settings = SSW_Settings::get_all();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ssw-settings-form">
			<input type="hidden" name="action" value="ssw_save_settings"><?php wp_nonce_field( 'ssw_save_settings' ); ?>
			<table class="form-table"><tbody>
			<tr><th scope="row"><label for="ssw-sku-column"><?php esc_html_e( 'SKU column name', 'sheet-stock-sync-woo' ); ?></label></th><td><input name="sku_column" id="ssw-sku-column" type="text" class="regular-text" value="<?php echo esc_attr( $settings['sku_column'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="ssw-qty-column"><?php esc_html_e( 'Quantity column name', 'sheet-stock-sync-woo' ); ?></label></th><td><input name="quantity_column" id="ssw-qty-column" type="text" class="regular-text" value="<?php echo esc_attr( $settings['quantity_column'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="ssw-threshold"><?php esc_html_e( 'Default low-stock threshold', 'sheet-stock-sync-woo' ); ?></label></th><td><input name="low_stock_threshold" id="ssw-threshold" type="number" min="0" value="<?php echo esc_attr( $settings['low_stock_threshold'] ); ?>"><p class="description"><?php esc_html_e( 'Used when a product has no WooCommerce per-product threshold.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
			<tr><th scope="row"><label for="ssw-multiplier"><?php esc_html_e( 'Reorder multiplier', 'sheet-stock-sync-woo' ); ?></label></th><td><input name="reorder_multiplier" id="ssw-multiplier" type="number" min="0.1" step="0.1" value="<?php echo esc_attr( $settings['reorder_multiplier'] ); ?>"><p class="description"><?php esc_html_e( 'Suggested reorder aims for threshold × this number.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Update stock status automatically', 'sheet-stock-sync-woo' ); ?></th><td><label><input type="checkbox" name="update_stock_status" value="yes" <?php checked( 'yes', $settings['update_stock_status'] ); ?>> <?php esc_html_e( 'Set In stock / Out of stock when quantities change', 'sheet-stock-sync-woo' ); ?></label></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Low-stock e-mail', 'sheet-stock-sync-woo' ); ?></th><td><label><input type="checkbox" name="low_stock_email" value="yes" <?php checked( 'yes', $settings['low_stock_email'] ); ?>> <?php esc_html_e( 'Send the reorder report automatically', 'sheet-stock-sync-woo' ); ?></label></td></tr>
			<tr><th scope="row"><label for="ssw-recipients"><?php esc_html_e( 'Report recipients', 'sheet-stock-sync-woo' ); ?></label></th><td><input name="low_stock_recipients" id="ssw-recipients" type="text" class="regular-text" value="<?php echo esc_attr( $settings['low_stock_recipients'] ); ?>"><p class="description"><?php esc_html_e( 'Comma-separated e-mail addresses. Leave blank for the WordPress admin e-mail.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
			<tr><th scope="row"><label for="ssw-frequency"><?php esc_html_e( 'Report frequency', 'sheet-stock-sync-woo' ); ?></label></th><td><select name="low_stock_frequency" id="ssw-frequency"><option value="daily" <?php selected( 'daily', $settings['low_stock_frequency'] ); ?>><?php esc_html_e( 'Daily', 'sheet-stock-sync-woo' ); ?></option><option value="weekly" <?php selected( 'weekly', $settings['low_stock_frequency'] ); ?>><?php esc_html_e( 'Weekly', 'sheet-stock-sync-woo' ); ?></option></select></td></tr>
			<tr><th scope="row"><label for="ssw-language"><?php esc_html_e( 'Plugin language', 'sheet-stock-sync-woo' ); ?></label></th><td><select name="admin_language" id="ssw-language"><option value=""><?php esc_html_e( 'Follow WordPress language', 'sheet-stock-sync-woo' ); ?></option><?php foreach ( SSW_Settings::languages() as $locale => $name ) : ?><option value="<?php echo esc_attr( $locale ); ?>" <?php selected( $locale, $settings['admin_language'] ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select><p class="description"><?php esc_html_e( 'Changes only Stock Manager screens and e-mails.', 'sheet-stock-sync-woo' ); ?></p></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function render_license_tab() {
		$status = SSW_License::status();
		$key    = (string) get_option( SSW_OPTION_LICENSE, '' );
		?>
		<div class="ssw-card ssw-license-card"><h2><?php esc_html_e( 'Licence', 'sheet-stock-sync-woo' ); ?></h2><p><?php echo esc_html( $status['message'] ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_save_license"><?php wp_nonce_field( 'ssw_save_license' ); ?><input type="text" name="license_key" class="regular-text" value="<?php echo esc_attr( $key ); ?>" placeholder="SSW-XXXX-XXXX-XXXX"> <?php submit_button( __( 'Save licence', 'sheet-stock-sync-woo' ), 'primary', 'submit', false ); ?></form></div>
		<?php
	}

	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_save_settings' );
		$current = SSW_Settings::get_all();
		$settings = array(
			'sku_column'           => isset( $_POST['sku_column'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_column'] ) ) : $current['sku_column'],
			'quantity_column'      => isset( $_POST['quantity_column'] ) ? sanitize_text_field( wp_unslash( $_POST['quantity_column'] ) ) : $current['quantity_column'],
			'update_stock_status'  => isset( $_POST['update_stock_status'] ) ? 'yes' : 'no',
			'low_stock_threshold'  => isset( $_POST['low_stock_threshold'] ) ? max( 0, absint( $_POST['low_stock_threshold'] ) ) : $current['low_stock_threshold'],
			'low_stock_email'      => isset( $_POST['low_stock_email'] ) ? 'yes' : 'no',
			'low_stock_recipients' => isset( $_POST['low_stock_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['low_stock_recipients'] ) ) : '',
			'low_stock_frequency'  => isset( $_POST['low_stock_frequency'] ) && 'daily' === sanitize_key( wp_unslash( $_POST['low_stock_frequency'] ) ) ? 'daily' : 'weekly',
			'reorder_multiplier'   => isset( $_POST['reorder_multiplier'] ) ? max( 0.1, (float) $_POST['reorder_multiplier'] ) : $current['reorder_multiplier'],
			'admin_language'       => isset( $_POST['admin_language'] ) ? sanitize_text_field( wp_unslash( $_POST['admin_language'] ) ) : '',
		);
		if ( ! array_key_exists( $settings['admin_language'], SSW_Settings::languages() ) ) { $settings['admin_language'] = ''; }
		SSW_Settings::update( $settings );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sheet-stock-sync-woo', 'tab' => 'settings', 'updated' => 'true' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function handle_save_license() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_save_license' );
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		update_option( SSW_OPTION_LICENSE, trim( $key ), false );
		SSW_License::clear_cached_check();
		wp_safe_redirect( add_query_arg( array( 'page' => 'sheet-stock-sync-woo', 'tab' => 'license' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function handle_refresh_analytics() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_refresh_analytics' ); SSW_Analytics::clear_cache();
		wp_safe_redirect( add_query_arg( array( 'page' => 'sheet-stock-sync-woo', 'tab' => 'analytics' ), admin_url( 'admin.php' ) ) ); exit;
	}

	private function render_license_notice() {
		$status = SSW_License::status();
		if ( $status['active'] ) { return; }
		echo '<div class="notice notice-warning"><p>' . esc_html( $status['message'] ) . ' <a href="' . esc_url( add_query_arg( array( 'page' => 'sheet-stock-sync-woo', 'tab' => 'license' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open licence settings', 'sheet-stock-sync-woo' ) . '</a></p></div>';
	}

	private function kpi_card( $label, $value, $change ) {
		$delta = '';
		if ( null !== $change ) { $class = $change >= 0 ? 'is-up' : 'is-down'; $delta = '<span class="ssw-kpi-change ' . $class . '">' . esc_html( sprintf( '%+.1f%%', $change ) ) . '</span>'; }
		return '<div class="ssw-kpi"><span class="ssw-kpi-label">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>' . $delta . '</div>';
	}

	public static function level_pill( $level ) {
		$labels = array( 'in' => __( 'In stock', 'sheet-stock-sync-woo' ), 'low' => __( 'Running low', 'sheet-stock-sync-woo' ), 'out' => __( 'Out of stock', 'sheet-stock-sync-woo' ) );
		$label  = isset( $labels[ $level ] ) ? $labels[ $level ] : $level;
		return '<span class="ssw-pill ssw-level-' . esc_attr( $level ) . '"><span class="ssw-dot"></span>' . esc_html( $label ) . '</span>';
	}
}
