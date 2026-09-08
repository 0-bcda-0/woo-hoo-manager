<?php
declare(strict_types=1);
if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/tmp-wordpress/');}
function ssw_assert_forecast_plus($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$season=dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-seasonality.php';$accuracy=dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-forecast-accuracy.php';
ssw_assert_forecast_plus(file_exists($season),'Seasonality service must exist.');ssw_assert_forecast_plus(file_exists($accuracy),'Forecast accuracy service must exist.');require_once $season;require_once $accuracy;
$monthly=array(1=>array(100,110),2=>array(100,100),3=>array(100,100),4=>array(100,100),5=>array(100,100),6=>array(100,100),7=>array(100,100),8=>array(100,100),9=>array(100,100),10=>array(130,140),11=>array(220,230),12=>array(400,420));
$factors=SSW_Seasonality::monthly_factors($monthly);ssw_assert_forecast_plus($factors[12]>2.5,'December receives a strong seasonal factor.');ssw_assert_forecast_plus($factors[1]<1.0,'January does not inherit the December spike.');
$jan=SSW_Seasonality::forecast_daily(4.0,1,$factors);$dec=SSW_Seasonality::forecast_daily(4.0,12,$factors);ssw_assert_forecast_plus($dec>$jan*2.5,'Seasonal December forecast is materially higher than January.');
$metrics=SSW_Forecast_Accuracy::metrics(array(array('predicted'=>100,'actual'=>110),array('predicted'=>50,'actual'=>40)));ssw_assert_forecast_plus(20.0===$metrics['absolute_error_units'],'Absolute error totals 20 units.');ssw_assert_forecast_plus(abs($metrics['wape']-0.1333333333)<0.0001,'WAPE is deterministic.');ssw_assert_forecast_plus(abs($metrics['accuracy_percent']-86.6666667)<0.001,'Accuracy is 86.67%.');
$empty=SSW_Forecast_Accuracy::metrics(array(array('predicted'=>0,'actual'=>0)));ssw_assert_forecast_plus(null===$empty['accuracy_percent'],'No actual demand returns insufficient accuracy.');
fwrite(STDOUT,"PASS: approved seasonality and forecast accuracy\n");
