<?php
declare(strict_types=1);
$ssw_test_options=array();$ssw_test_dbdelta_calls=array();
if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/tmp-wordpress/');}
if(!function_exists('get_option')){function get_option($n,$d=false){global $ssw_test_options;return array_key_exists($n,$ssw_test_options)?$ssw_test_options[$n]:$d;}}
if(!function_exists('update_option')){function update_option($n,$v,$a=null){global $ssw_test_options;$ssw_test_options[$n]=$v;return true;}}
if(!function_exists('dbDelta')){function dbDelta($sql){global $ssw_test_dbdelta_calls;$ssw_test_dbdelta_calls[]=$sql;return array();}}
final class SSW_Test_WPDB{public $prefix='wp_';public $charset='utf8mb4';public $collate='utf8mb4_unicode_ci';public function get_charset_collate(){return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';}}
$wpdb=new SSW_Test_WPDB();function ssw_assert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$file=dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-db.php';ssw_assert(file_exists($file),'class-ssw-db.php must exist.');require_once $file;
ssw_assert('11'===(string)SSW_DB_SCHEMA_VERSION,'Weekly reports raise schema version to 11.');SSW_DB::maybe_upgrade();
ssw_assert('11'===(string)get_option('ssw_db_schema_version','0'),'Upgrade stores schema version 11.');ssw_assert(22===count($ssw_test_dbdelta_calls),'Schema v11 creates 22 table definitions.');
$need=array('ssw_suppliers','ssw_supplier_products','ssw_locations','ssw_location_stock','ssw_stock_movements','ssw_purchase_orders','ssw_purchase_order_items','ssw_po_receipts','ssw_po_receipt_items','ssw_barcodes','ssw_sales_daily','ssw_inventory_snapshots_daily','ssw_product_metrics_daily','ssw_forecasts','ssw_bundle_pairs','ssw_bundle_product_orders','ssw_bundle_processed_orders','ssw_alerts','ssw_bundles','ssw_bundle_components','ssw_reports');
foreach($need as $name){$found=false;foreach($ssw_test_dbdelta_calls as $sql){if(false!==strpos($sql,$name)){$found=true;break;}}ssw_assert($found,"Migration contains {$name}.");}
SSW_DB::maybe_upgrade();ssw_assert(22===count($ssw_test_dbdelta_calls),'Second upgrade is idempotent.');fwrite(STDOUT,"PASS: database migration foundation\n");
