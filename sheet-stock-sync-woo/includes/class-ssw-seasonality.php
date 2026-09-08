<?php
/** Approved #34 seasonality-aware deterministic forecasting. */
defined('ABSPATH')||exit;
final class SSW_Seasonality{
 public static function monthly_factors($monthly_units,$min_observations=2){$means=array();foreach(range(1,12) as $m){$values=isset($monthly_units[$m])?(array)$monthly_units[$m]:array();$valid=array_values(array_filter(array_map('floatval',$values),function($v){return $v>=0;}));if(count($valid)>=$min_observations){$means[$m]=array_sum($valid)/count($valid);}}
  if(count($means)<6){return array_fill(1,12,1.0);} $global=array_sum($means)/count($means);if($global<=0){return array_fill(1,12,1.0);} $out=array();foreach(range(1,12) as $m){if(!isset($means[$m])){$out[$m]=1.0;continue;}$raw=$means[$m]/$global;$out[$m]=max(0.35,min(3.5,1+($raw-1)*0.85));}return $out;}
 public static function forecast_daily($base_daily_velocity,$target_month,$factors){$month=max(1,min(12,(int)$target_month));$factor=isset($factors[$month])?(float)$factors[$month]:1.0;return max(0,(float)$base_daily_velocity)*$factor;}
 public static function confidence($history_months){$n=max(0,(int)$history_months);if($n<12){return 'insufficient_data';}if($n<18){return 'low_confidence';}if($n<24){return 'normal';}return 'high_confidence';}
 public static function product_monthly_history($product_id,$months=24){global $wpdb;$table=$wpdb->prefix.'ssw_sales_daily';$rows=$wpdb->get_results($wpdb->prepare("SELECT YEAR(fact_date) y,MONTH(fact_date) m,SUM(units) units FROM {$table} WHERE product_id=%d AND fact_date>=DATE_SUB(CURDATE(),INTERVAL %d MONTH) GROUP BY YEAR(fact_date),MONTH(fact_date) ORDER BY y,m",absint($product_id),max(12,min(60,(int)$months))),ARRAY_A);$grouped=array();foreach($rows as $r){$m=(int)$r['m'];if(!isset($grouped[$m])){$grouped[$m]=array();}$grouped[$m][]=(float)$r['units'];}return array('monthly'=>$grouped,'observations'=>count($rows),'factors'=>self::monthly_factors($grouped),'confidence'=>self::confidence(count($rows)));}
}
