<?php
declare(strict_types=1);
function ssw_scope_assert($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$file=dirname(__DIR__).'/sheet-stock-sync-woo/sheet-stock-sync-woo.php';
$src=file_get_contents($file);
ssw_scope_assert(false!==$src,'Bootstrap is readable.');
foreach(array('new SSW_Barcode_Admin','new SSW_Location_Admin','new SSW_Purchase_Order_Admin','new SSW_Operations_Dashboard','new SSW_Action_Center') as $forbidden){ssw_scope_assert(false===strpos($src,$forbidden),"Out-of-scope runtime is absent: {$forbidden}");}
foreach(array('new SSW_Stock_Operations_Admin','new SSW_Intelligence_Admin','new SSW_Product_360_Admin','new SSW_Forecast_Accuracy_Admin','new SSW_Weekly_Report_Admin') as $required){ssw_scope_assert(false!==strpos($src,$required),"Approved runtime is present: {$required}");}
$intel=file_get_contents(dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-intelligence-admin.php');
ssw_scope_assert(false===stripos($intel,'pareto'),'Pareto is not exposed in intelligence UI.');
ssw_scope_assert(false===stripos($intel,'gmroi'),'GMROI is not exposed in intelligence UI.');
fwrite(STDOUT,"PASS: runtime scope lock\n");
