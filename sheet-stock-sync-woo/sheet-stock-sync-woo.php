<?php
/**
 * Plugin Name:       Stock Manager for WooCommerce
 * Plugin URI:        https://example.com/sheet-stock-sync-woo
 * Description:       Inventory forecasting, replenishment, stock operations and purchasing intelligence for WooCommerce.
 * Version:           3.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * License:           GPLv2 or later
 * Text Domain:       sheet-stock-sync-woo
 * Domain Path:       /languages
 * @package SheetStockSyncWoo
 */
defined('ABSPATH')||exit;
define('SSW_VERSION','3.3.0');define('SSW_PLUGIN_FILE',__FILE__);define('SSW_PLUGIN_DIR',plugin_dir_path(__FILE__));define('SSW_PLUGIN_URL',plugin_dir_url(__FILE__));define('SSW_PLUGIN_BASENAME',plugin_basename(__FILE__));define('SSW_OPTION_SETTINGS','ssw_settings');define('SSW_OPTION_LOG','ssw_last_log');define('SSW_OPTION_REPORT_LOG','ssw_report_log');define('SSW_CRON_LOW_STOCK','ssw_low_stock_report');define('SSW_TRANSIENT_ANALYTICS','ssw_analytics_report');define('SSW_OPTION_LICENSE','ssw_license_key');define('SSW_OPTION_INSTALLED_AT','ssw_installed_at');define('SSW_OPTION_INSTALL_ID','ssw_install_id');define('SSW_OPTION_LICENSE_CHECK','ssw_license_check');define('SSW_CRON_LICENSE','ssw_license_check_event');
require_once SSW_PLUGIN_DIR.'includes/class-ssw-settings.php';require_once SSW_PLUGIN_DIR.'includes/class-ssw-db.php';
final class Sheet_Stock_Sync_Woo{
 private static $instance=null;public static function instance(){if(null===self::$instance){self::$instance=new self();}return self::$instance;}
 private function __construct(){add_action('plugins_loaded',array($this,'init'));add_action('init',array($this,'load_textdomain'));add_action('before_woocommerce_init',array($this,'declare_compatibility'));add_filter('plugin_locale',array($this,'filter_locale'),10,2);register_activation_hook(SSW_PLUGIN_FILE,array($this,'on_activate'));register_deactivation_hook(SSW_PLUGIN_FILE,array($this,'on_deactivate'));}
 public function load_textdomain(){load_plugin_textdomain('sheet-stock-sync-woo',false,dirname(SSW_PLUGIN_BASENAME).'/languages');}
 public function filter_locale($locale,$domain){if('sheet-stock-sync-woo'!==$domain){return $locale;}$chosen=SSW_Settings::get('admin_language','');return(''!==$chosen&&array_key_exists($chosen,SSW_Settings::languages()))?$chosen:$locale;}
 public function declare_compatibility(){if(class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)){\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables',SSW_PLUGIN_FILE,true);}}
 public function init(){if(!$this->is_woocommerce_active()){add_action('admin_notices',array($this,'woocommerce_missing_notice'));return;}SSW_DB::maybe_upgrade();$this->includes();SSW_Locations::ensure_main_warehouse();SSW_Bundle_Kits::register_hooks();new SSW_License();new SSW_Admin();new SSW_Supplier_Admin();new SSW_Purchasing_Plan_Admin();new SSW_What_If_Admin();new SSW_Stock_Operations_Admin();new SSW_Intelligence_Admin();new SSW_Bundle_Admin();new SSW_Bundle_Kit_Admin();new SSW_Alert_Admin();new SSW_Product_360_Admin();new SSW_Forecast_Accuracy_Admin();new SSW_Weekly_Report_Service();new SSW_Weekly_Report_Admin();new SSW_Analytics_Jobs();new SSW_Ajax();new SSW_Import_Export();new SSW_Low_Stock();}
 private function includes(){foreach(array('class-ssw-license.php','class-ssw-builtin.php','class-ssw-import-export.php','class-ssw-lowstock.php','class-ssw-analytics.php','class-ssw-chart.php','class-ssw-admin.php','class-ssw-ajax.php','class-ssw-suppliers.php','class-ssw-supplier-admin.php','class-ssw-locations.php','class-ssw-stock-ledger.php','class-ssw-stock-operations-admin.php','class-ssw-purchase-orders.php','class-ssw-purchasing-planner.php','class-ssw-purchasing-plan-admin.php','class-ssw-what-if.php','class-ssw-what-if-admin.php','class-ssw-sales-aggregator.php','class-ssw-demand-forecast.php','class-ssw-metrics-engine.php','class-ssw-inventory-intelligence.php','class-ssw-intelligence-admin.php','class-ssw-bundle-opportunities.php','class-ssw-bundle-aggregator.php','class-ssw-bundle-admin.php','class-ssw-bundle-kits.php','class-ssw-bundle-kit-admin.php','class-ssw-alerts.php','class-ssw-anomaly-detector.php','class-ssw-alert-evaluator.php','class-ssw-alert-admin.php','class-ssw-seasonality.php','class-ssw-seasonal-forecast-job.php','class-ssw-forecast-accuracy.php','class-ssw-product-360-admin.php','class-ssw-forecast-accuracy-admin.php','class-ssw-weekly-report.php','class-ssw-weekly-report-service.php','class-ssw-weekly-report-admin.php','class-ssw-analytics-jobs.php') as $f){require_once SSW_PLUGIN_DIR.'includes/'.$f;}}
 private function is_woocommerce_active(){return class_exists('WooCommerce');}
 public function woocommerce_missing_notice(){?><div class="notice notice-error"><p><?php esc_html_e('Stock Manager for WooCommerce requires WooCommerce to be installed and active.','sheet-stock-sync-woo');?></p></div><?php}
 public function on_activate(){if(!$this->is_woocommerce_active()){deactivate_plugins(SSW_PLUGIN_BASENAME);wp_die(esc_html__('Stock Manager for WooCommerce requires WooCommerce to be installed and active.','sheet-stock-sync-woo'));}SSW_DB::maybe_upgrade();require_once SSW_PLUGIN_DIR.'includes/class-ssw-locations.php';SSW_Locations::ensure_main_warehouse();if(false===get_option(SSW_OPTION_SETTINGS)){add_option(SSW_OPTION_SETTINGS,SSW_Settings::defaults());}if(!get_option(SSW_OPTION_INSTALLED_AT)){add_option(SSW_OPTION_INSTALLED_AT,time(),'',false);}}
 public function on_deactivate(){wp_clear_scheduled_hook(SSW_CRON_LOW_STOCK);wp_clear_scheduled_hook(SSW_CRON_LICENSE);if(class_exists('SSW_Analytics_Jobs')){SSW_Analytics_Jobs::clear_schedules();}if(class_exists('SSW_Weekly_Report_Service')){SSW_Weekly_Report_Service::clear_schedule();}delete_option('ssw_report_schedule');delete_transient(SSW_TRANSIENT_ANALYTICS);}
}
Sheet_Stock_Sync_Woo::instance();
