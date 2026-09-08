<?php
/**
 * Approved #39 weekly executive report admin UI.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Weekly_Report_Admin {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 28 );
		add_action( 'admin_post_ssw_weekly_report_generate', array( $this, 'generate' ) );
		add_action( 'admin_post_ssw_weekly_report_settings', array( $this, 'save_settings' ) );
	}

	public function menu() {
		add_submenu_page(
			'sheet-stock-sync-woo',
			__( 'Weekly Report', 'sheet-stock-sync-woo' ),
			__( 'Weekly Report', 'sheet-stock-sync-woo' ),
			'manage_woocommerce',
			'ssw-weekly-report',
			array( $this, 'render' )
		);
	}

	private function guard( $nonce_action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		check_admin_referer( $nonce_action );
	}

	public function generate() {
		$this->guard( 'ssw_weekly_report_generate' );
		SSW_Weekly_Report_Service::generate( null, true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-weekly-report', 'ssw_notice' => 'generated' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function save_settings() {
		$this->guard( 'ssw_weekly_report_settings' );
		$enabled = empty( $_POST['enabled'] ) ? 0 : 1;
		$email = sanitize_email( isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '' );
		update_option( 'ssw_weekly_report_email_enabled', $enabled, false );
		update_option( 'ssw_weekly_report_email', $email, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-weekly-report', 'ssw_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'sheet-stock-sync-woo' ) );
		}
		$payload = SSW_Weekly_Report_Service::latest();
		$enabled = (int) get_option( 'ssw_weekly_report_email_enabled', 0 );
		$email = get_option( 'ssw_weekly_report_email', get_option( 'admin_email' ) );
		$notice = ! empty( $_GET['ssw_notice'] ) ? sanitize_key( wp_unslash( $_GET['ssw_notice'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Weekly Executive Report', 'sheet-stock-sync-woo' ); ?></h1>
			<p><?php esc_html_e( 'Deterministic weekly summary of approved sales, inventory risk, alerts, bundle opportunities and 90-day purchasing cash outlook.', 'sheet-stock-sync-woo' ); ?></p>

			<?php if ( $notice ) : ?>
				<div class="notice notice-success inline"><p>
					<?php echo 'generated' === $notice ? esc_html__( 'Report generated.', 'sheet-stock-sync-woo' ) : esc_html__( 'Settings saved.', 'sheet-stock-sync-woo' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_weekly_report_generate">
				<?php wp_nonce_field( 'ssw_weekly_report_generate' ); ?>
				<button class="button button-primary"><?php esc_html_e( 'Generate report now', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<h2><?php esc_html_e( 'Email delivery', 'sheet-stock-sync-woo' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ssw_weekly_report_settings">
				<?php wp_nonce_field( 'ssw_weekly_report_settings' ); ?>
				<label><input type="checkbox" name="enabled" value="1" <?php checked( $enabled, 1 ); ?>> <?php esc_html_e( 'Send weekly email', 'sheet-stock-sync-woo' ); ?></label>
				<input type="email" name="email" class="regular-text" value="<?php echo esc_attr( $email ); ?>">
				<button class="button"><?php esc_html_e( 'Save', 'sheet-stock-sync-woo' ); ?></button>
			</form>

			<?php if ( ! $payload ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'No saved weekly report yet.', 'sheet-stock-sync-woo' ); ?></p></div>
			<?php else : ?>
				<h2><?php echo esc_html( $payload['period']['start'] . ' → ' . $payload['period']['end'] ); ?></h2>
				<table class="widefat striped" style="max-width:1000px"><tbody>
				<tr><th><?php esc_html_e( 'Revenue', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $payload['sales']['revenue'] ) ); ?></td><th><?php esc_html_e( 'Orders / units', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $payload['sales']['orders'] . ' / ' . $payload['sales']['units'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Revenue at Risk', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $payload['inventory']['revenue_at_risk'] ) ); ?></td><th><?php esc_html_e( 'Estimated Lost Sales', 'sheet-stock-sync-woo' ); ?></th><td><?php echo wp_kses_post( wc_price( $payload['inventory']['estimated_lost_sales'] ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Stockouts / slow-dead', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $payload['inventory']['stockouts'] . ' / ' . $payload['inventory']['dead_slow_count'] ); ?></td><th><?php esc_html_e( 'Health', 'sheet-stock-sync-woo' ); ?></th><td><?php echo null === $payload['inventory']['health_average'] ? '—' : esc_html( number_format_i18n( $payload['inventory']['health_average'], 1 ) . '/100' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Alerts', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $payload['operations']['critical_alerts'] . ' critical / ' . $payload['operations']['warning_alerts'] . ' warning' ); ?></td><th><?php esc_html_e( 'Bundle opportunities', 'sheet-stock-sync-woo' ); ?></th><td><?php echo esc_html( $payload['operations']['bundle_opportunities'] ); ?></td></tr>
				<tr><th><?php esc_html_e( '90-day purchasing cash', 'sheet-stock-sync-woo' ); ?></th><td colspan="3">
					<?php if ( empty( $payload['purchasing']['cash_by_currency'] ) ) : ?>
						—
					<?php else : ?>
						<?php foreach ( $payload['purchasing']['cash_by_currency'] as $currency => $amount ) : ?>
							<strong><?php echo esc_html( $currency ); ?></strong> <?php echo esc_html( number_format_i18n( $amount, 2 ) ); ?>&nbsp;
						<?php endforeach; ?>
					<?php endif; ?>
				</td></tr>
				</tbody></table>

				<?php if ( ! empty( $payload['data_quality'] ) ) : ?>
					<p><small><?php echo esc_html( 'Data quality: ' . $payload['data_quality']['state'] . ' · metric date ' . ( $payload['data_quality']['metric_date'] ? $payload['data_quality']['metric_date'] : '—' ) . ' · ' . $payload['data_quality']['products_analysed'] . ' products' ); ?></small></p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
