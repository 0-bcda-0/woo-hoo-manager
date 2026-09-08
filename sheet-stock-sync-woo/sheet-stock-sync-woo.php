<?php
/**
 * Plugin Name:       Stock Manager for WooCommerce
 * Plugin URI:        https://example.com/sheet-stock-sync-woo
 * Description:       Edit stock in one table, see at a glance what is running low, get a reorder e-mail with a ready purchase order, and follow what actually sells — plus CSV/Excel bulk import and export.
 * Version:           3.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:            GPLv2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sheet-stock-sync-woo
 * Domain Path:       /languages
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit; // Block direct access.

/**
 * Core plugin constants.
 */
define( 'SSW_VERSION', '3.3.0' );
define( 'SSW_PLUGIN_FILE', __FILE__ );
define( 'SSW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SSW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SSW_OPTION_SETTINGS', 'ssw_settings' );
define( 'SSW_OPTION_LOG', 'ssw_last_log' );
define( 'SSW_OPTION_REPORT_LOG', 'ssw_report_log' );
define( 'SSW_CRON_LOW_STOCK', 'ssw_low_stock_report' );
define( 'SSW_TRANSIENT_ANALYTICS', 'ssw_analytics_report' );
define( 'SSW_OPTION_LICENSE', 'ssw_license_key' );
define( 'SSW_OPTION_INSTALLED_AT', 'ssw_installed_at' );
define( 'SSW_OPTION_INSTALL_ID', 'ssw_install_id' );
define( 'SSW_OPTION_LICENSE_CHECK', 'ssw_license_check' );
define( 'SSW_CRON_LICENSE', 'ssw_license_check_event' );

/**
 * These helpers have no WooCommerce dependency, and activation hooks need
 * them before `plugins_loaded` and init() run on the activation request.
 */
require_once SSW_PLUGIN_DIR . 'includes/class-ssw-settings.php';
require_once SSW_PLUGIN_DIR . 'includes/class-ssw-db.php';

/**
 * Main plugin bootstrap class. Kept intentionally small — it just wires
 * the includes together and handles activation/deactivation.
 */
final class Sheet_Stock_Sync_Woo {

	/**
	 * Singleton instance.
	 *
	 * @var Sheet_Stock_Sync_Woo|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Sheet_Stock_Sync_Woo
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — private, use instance().
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );

		// Registered here, in the constructor, because WordPress applies it
		// while loading the translation file — by the time init() runs it
		// would already be too late.
		add_filter( 'plugin_locale', array( $this, 'filter_locale' ), 10, 2 );

		register_activation_hook( SSW_PLUGIN_FILE, array( $this, 'on_activate' ) );
		register_deactivation_hook( SSW_PLUGIN_FILE, array( $this, 'on_deactivate' ) );
	}

	/**
	 * Load this plugin's translations. Hooked to `init` rather than
	 * `plugins_loaded`: since WordPress 6.7, loading a text domain before
	 * `init` triggers a "called incorrectly" notice.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'sheet-stock-sync-woo', false, dirname( SSW_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Apply the language chosen in Settings — for this plugin's own strings
	 * only. WordPress itself, WooCommerce and every other plugin keep the
	 * site's language, so switching this never turns the rest of the admin
	 * into a language the shop owner didn't ask for.
	 *
	 * @param string $locale Locale WordPress would otherwise use.
	 * @param string $domain Text domain being loaded.
	 * @return string
	 */
	public function filter_locale( $locale, $domain ) {
		if ( 'sheet-stock-sync-woo' !== $domain ) {
			return $locale;
		}

		$chosen = SSW_Settings::get( 'admin_language', '' );

		if ( '' === $chosen || ! array_key_exists( $chosen, SSW_Settings::languages() ) ) {
			return $locale; // Follow the site/user language.
		}

		return $chosen;
	}

	/**
	 * Tell WooCommerce this plugin is safe under High-Performance Order
	 * Storage. Without this declaration WooCommerce flags the plugin as
	 * incompatible in the admin, even though all order access here goes
	 * through wc_get_orders() rather than the posts tables.
	 */
	public function declare_compatibility() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SSW_PLUGIN_FILE, true );
		}
	}

	/**
	 * Boot the plugin once all plugins are loaded, after checking
	 * WooCommerce is active.
	 */
	public function init() {
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		SSW_DB::maybe_upgrade();
		$this->includes();

		new SSW_License();
		new SSW_Admin();
		new SSW_Ajax();
		new SSW_Import_Export();
		new SSW_Low_Stock();
	}

	/**
	 * Load the plugin's class files.
	 */
	private function includes() {
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-license.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-builtin.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-import-export.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-lowstock.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-analytics.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-chart.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-admin.php';
		require_once SSW_PLUGIN_DIR . 'includes/class-ssw-ajax.php';
	}

	/**
	 * Whether WooCommerce is active on this site.
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e( 'Stock Manager for WooCommerce requires WooCommerce to be installed and active.', 'sheet-stock-sync-woo' );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Runs on plugin activation. Sets sane default options; the reorder
	 * e-mail schedule itself is (re)created by SSW_Low_Stock on the next
	 * request, based on the saved frequency.
	 */
	public function on_activate() {
		if ( ! $this->is_woocommerce_active() ) {
			deactivate_plugins( SSW_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Stock Manager for WooCommerce requires WooCommerce to be installed and active. The plugin has been deactivated.', 'sheet-stock-sync-woo' ),
				esc_html__( 'Plugin activation error', 'sheet-stock-sync-woo' ),
				array( 'back_link' => true )
			);
		}

		SSW_DB::maybe_upgrade();

		if ( false === get_option( SSW_OPTION_SETTINGS ) ) {
			add_option( SSW_OPTION_SETTINGS, SSW_Settings::defaults() );
		}

		// Start of the trial period. Set once and never reset, so
		// deactivating and reactivating does not hand out a fresh trial.
		if ( ! get_option( SSW_OPTION_INSTALLED_AT ) ) {
			add_option( SSW_OPTION_INSTALLED_AT, time(), '', false );
		}
	}

	/**
	 * Runs on deactivation: never leave a scheduled e-mail behind on a
	 * plugin the site has switched off.
	 */
	public function on_deactivate() {
		$timestamp = wp_next_scheduled( SSW_CRON_LOW_STOCK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, SSW_CRON_LOW_STOCK );
		}

		wp_clear_scheduled_hook( SSW_CRON_LOW_STOCK );

		$licence_check = wp_next_scheduled( SSW_CRON_LICENSE );
		if ( $licence_check ) {
			wp_unschedule_event( $licence_check, SSW_CRON_LICENSE );
		}
		wp_clear_scheduled_hook( SSW_CRON_LICENSE );
		delete_option( 'ssw_report_schedule' );
		delete_transient( SSW_TRANSIENT_ANALYTICS );
	}
}

Sheet_Stock_Sync_Woo::instance();
