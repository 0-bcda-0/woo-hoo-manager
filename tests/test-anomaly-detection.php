<?php
declare(strict_types=1);
if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/tmp-wordpress/');}
function ssw_assert_anomaly($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$file=dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-anomaly-detector.php';ssw_assert_anomaly(file_exists($file),'Anomaly detector must exist.');require_once $file;
$spike=SSW_Anomaly_Detector::sales_velocity(3.2,1.0,0.5);ssw_assert_anomaly('sales_spike'===$spike['type'],'3.2x velocity is a sales spike.');
$drop=SSW_Anomaly_Detector::sales_velocity(0.2,1.0,0.5);ssw_assert_anomaly('sales_drop'===$drop['type'],'80% velocity drop is detected.');
ssw_assert_anomaly(null===SSW_Anomaly_Detector::sales_velocity(1.1,1.0,0.5),'Small velocity change is not anomaly.');
$movement=SSW_Anomaly_Detector::stock_movement(-48,100,'manual',20);ssw_assert_anomaly('unexplained_stock_change'===$movement['type'],'Large manual movement is anomalous.');
ssw_assert_anomaly(null===SSW_Anomaly_Detector::stock_movement(-48,100,'woocommerce_order',20),'Expected order movement is not anomaly.');
$price=SSW_Anomaly_Detector::price_change(12.90,1.29,0.30);ssw_assert_anomaly('price_change'===$price['type'],'90% price drop is anomalous.');
$oos=SSW_Anomaly_Detector::oos_spike(14,3,3.0);ssw_assert_anomaly('oos_spike'===$oos['type'],'Sudden OOS count spike is detected.');
fwrite(STDOUT,"PASS: approved anomaly detection\n");
