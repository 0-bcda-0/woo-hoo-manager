<?php
/** Admin UI and scheduled delivery for approved #37 Weekly Executive Report. */
defined( 'ABSPATH' ) || exit;

final class SSW_Weekly_Report_Admin {
	const CRON = 'ssw_weekly_executive_report';
	const OPTION_ENABLED = 'ssw_weekly_report_enabled';
	const OPTION_EMAIL = 'ssw_weekly_report_email';

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 26 );
		add_action( 'admin_init', array( $this, 'ensure_schedule' ) );
		add_action( self::CRON, array( $this, 'run_scheduled_report' ) );
		add_action( 'admin_post_ssw_weekly_report_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_ssw_generate_weekly_report', array( $this, 'generate_now' ) );
	}

	public function cron_schedules( $schedules ) {
		$schedules['ssw_weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once Weekly', 'sheet-stock-sync-woo' ) );
		return $schedules;
	}

	public function ensure_schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'ssw_weekly', self::CRON ); }
	}

	public static function clear_schedule() { wp_clear_scheduled_hook( self::CRON ); }

	public function admin_menu() {
		add_submenu_page( 'sheet-stock-sync-woo', __( 'Weekly Executive Report', 'sheet-stock-sync-woo' ), __( 'Weekly Report', 'sheet-stock-sync-woo' ), 'manage_woocommerce', 'ssw-weekly-report', array( $this, 'render_page' ) );
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_weekly_report_settings' );
		$enabled = ! empty( $_POST['enabled'] ) ? '1' : '0';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		update_option( self::OPTION_ENABLED, $enabled, false );
		update_option( self::OPTION_EMAIL, $email, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-weekly-report', 'settings-updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function generate_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to perform this action.', 'sheet-stock-sync-woo' ) ); }
		check_admin_referer( 'ssw_generate_weekly_report' );
		SSW_Weekly_Report_Service::generate( current_time( 'Y-m-d' ), true );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ssw-weekly-report', 'generated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function run_scheduled_report() {
		$payload = SSW_Weekly_Report_Service::generate( current_time( 'Y-m-d' ), true );
		if ( ! $payload || '1' !== (string) get_option( self::OPTION_ENABLED, '0' ) ) { return; }
		$email = sanitize_email( (string) get_option( self::OPTION_EMAIL, get_option( 'admin_email', '' ) ) );
		if ( ! $email ) { return; }
		$subject = sprintf( __( 'Woo Hoo Weekly Executive Report — %s to %s', 'sheet-stock-sync-woo' ), $payload['period']['start'], $payload['period']['end'] );
		wp_mail( $email, $subject, self::email_body( $payload ), array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	public static function email_body( $payload ) {
		$sales = $payload['sales']; $wow = $payload['week_over_week']; $risk = $payload['risk']; $purchasing = $payload['purchasing']; $ops = $payload['operations'];
		$lines = array();
		$lines[] = '<h2>' . esc_html__( 'Weekly Executive Report', 'sheet-stock-sync-woo' ) . '</h2>';
		$lines[] = '<p>' . esc_html( $payload['period']['start'] . ' — ' . $payload['period']['end'] ) . '</p>';
		$lines[] = '<h3>' . esc_html__( 'Sales', 'sheet-stock-sync-woo' ) . '</h3><p>' . esc_html( sprintf( 'Revenue: %.2f | Orders: %d | Units: %.2f', $sales['revenue'], $sales['orders'], $sales['units'] ) ) . '</p>';
		$lines[] = '<p>' . esc_html( sprintf( 'WoW revenue: %s | orders: %s | units: %s', self::pct( $wow['revenue_percent'] ), self::pct( $wow['orders_percent'] ), self::pct( $wow['units_percent'] ) ) ) . '</p>';
		$lines[] = '<h3>' . esc_html__( 'Inventory & Risk', 'sheet-stock-sync-woo' ) . '</h3><p>' . esc_html( sprintf( 'Revenue at Risk: %.2f | Estimated Lost Sales: %.2f | Stockouts: %d | Dead/slow: %d | Health: %s', $risk['revenue_at_risk'], $risk['estimated_lost_sales'], $risk['stockouts'], $risk['dead_slow_count'], null === $risk['health_average'] ? '—' : number_format( $risk['health_average'], 1 ) . '/100' ) ) . '</p>';
		$cash_parts = array(); foreach ( $purchasing['cash_by_currency'] as $currency => $amount ) { $cash_parts[] = $currency . ' ' . number_format( $amount, 2 ); }
		$lines[] = '<h3>' . esc_html__( 'Purchasing', 'sheet-stock-sync-woo' ) . '</h3><p>' . esc_html( sprintf( 'Open POs: %d | Incoming units: %.2f | 90-day cash: %s', $purchasing['open_po_count'], $purchasing['incoming_units'], $cash_parts ? implode( ', ', $cash_parts ) : '—' ) ) . '</p>';
		$lines[] = '<h3>' . esc_html__( 'Actions', 'sheet-stock-sync-woo' ) . '</h3><p>' . esc_html( sprintf( 'Critical alerts: %d | Warnings: %d | Bundle opportunities: %d', $ops['critical_alerts'], $ops['warning_alerts'], $ops['bundle_opportunities'] ) ) . '</p>';
		$lines[] = '<p><em>' . esc_html__( 'Deterministic report. No customer personal data is included.', 'sheet-stock-sync-woo' ) . '</em></p>';
		return implode( "\n", $lines );
	}

	private static function pct( $value ) { return null === $value ? '—' : ( $value >= 0 ? '+' : '' ) . number_format( $value, 1 ) . '%'; }

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'sheet-stock-sync-woo' ) ); }
		$payload = SSW_Weekly_Report_Service::latest_snapshot();
		$enabled = '1' === (string) get_option( self::OPTION_ENABLED, '0' );
		$email = (string) get_option( self::OPTION_EMAIL, get_option( 'admin_email', '' ) );
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Weekly Executive Report', 'sheet-stock-sync-woo' ); ?></h1>
		<p><?php esc_html_e( 'Deterministic weekly summary of the approved sales, inventory-risk, purchasing, alert and bundle data. No customer PII is included.', 'sheet-stock-sync-woo' ); ?></p>
		<?php if ( isset( $_GET['generated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Weekly report generated.', 'sheet-stock-sync-woo' ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;border:1px solid #dcdcde;padding:14px;max-width:760px;margin:16px 0">
		<input type="hidden" name="action" value="ssw_weekly_report_settings"><?php wp_nonce_field( 'ssw_weekly_report_settings' ); ?>
		<label><input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'Email this report weekly', 'sheet-stock-sync-woo' ); ?></label><br><br>
		<label><?php esc_html_e( 'Recipient', 'sheet-stock-sync-woo' ); ?> <input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></label><br><br><?php submit_button( __( 'Save delivery settings', 'sheet-stock-sync-woo' ), 'secondary', 'submit', false ); ?></form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="ssw_generate_weekly_report"><?php wp_nonce_field( 'ssw_generate_weekly_report' ); ?><?php submit_button( __( 'Generate current report', 'sheet-stock-sync-woo' ), 'primary', 'submit', false ); ?></form>
		<?php if ( ! $payload ) : ?><div class="notice notice-info inline"><p><?php esc_html_e( 'No report snapshot yet. Generate one to see the dashboard.', 'sheet-stock-sync-woo' ); ?></p></div><?php else : $this->render_payload( $payload ); endif; ?>
		</div><?php
	}

	private function render_payload( $p ) {
		?><h2><?php echo esc_html( $p['period']['start'] . ' — ' . $p['period']['end'] ); ?></h2><div style="display:flex;gap:12px;flex-wrap:wrap">
		<?php $cards = array( 'Revenue' => $p['sales']['revenue'], 'Orders' => $p['sales']['orders'], 'Units' => $p['sales']['units'], 'Revenue at Risk' => $p['risk']['revenue_at_risk'], 'Estimated Lost Sales' => $p['risk']['estimated_lost_sales'], 'Stockouts' => $p['risk']['stockouts'], 'Health' => null === $p['risk']['health_average'] ? '—' : number_format_i18n( $p['risk']['health_average'], 1 ) . '/100', 'Open POs' => $p['purchasing']['open_po_count'], 'Critical Alerts' => $p['operations']['critical_alerts'], 'Bundle Opportunities' => $p['operations']['bundle_opportunities'] ); foreach ( $cards as $label => $value ) : ?><div class="card" style="min-width:160px"><strong><?php echo esc_html( $label ); ?></strong><br><span style="font-size:22px"><?php echo esc_html( is_numeric( $value ) ? number_format_i18n( $value, 2 ) : $value ); ?></span></div><?php endforeach; ?></div>
		<h3><?php esc_html_e( '90-day purchasing cash', 'sheet-stock-sync-woo' ); ?></h3><ul><?php if ( ! $p['purchasing']['cash_by_currency'] ) : ?><li>—</li><?php endif; foreach ( $p['purchasing']['cash_by_currency'] as $currency => $amount ) : ?><li><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount, 2 ) ); ?></li><?php endforeach; ?></ul>
		<p><small><?php echo esc_html( sprintf( 'Metric date: %s · Products analysed: %d · %s', $p['data_quality']['metric_date'] ? $p['data_quality']['metric_date'] : '—', $p['data_quality']['products_analysed'], $p['data_quality']['note'] ) ); ?></small></p><?php
	}
}
