<?php
/** Approved #35 Forecast vs Actual. */
defined('ABSPATH')||exit;
final class SSW_Forecast_Accuracy{
 public static function metrics($rows){$pred=0.0;$actual=0.0;$abs=0.0;$count=0;foreach((array)$rows as $r){$p=max(0,(float)$r['predicted']);$a=max(0,(float)$r['actual']);$pred+=$p;$actual+=$a;$abs+=abs($a-$p);$count++;}$wape=$actual>0?$abs/$actual:null;return array('predicted_units'=>$pred,'actual_units'=>$actual,'absolute_error_units'=>$abs,'wape'=>$wape,'accuracy_percent'=>null===$wape?null:max(0,(1-$wape)*100),'samples'=>$count);}
 public static function product_rows($product_id,$horizon_days=30,$limit=12){global $wpdb;$f=$wpdb->prefix.'ssw_forecasts';$s=$wpdb->prefix.'ssw_sales_daily';$sql="SELECT f.generated_for_date,f.horizon_date,f.forecast_qty predicted,COALESCE((SELECT SUM(sd.units) FROM {$s} sd WHERE sd.product_id=f.product_id AND sd.fact_date>f.generated_for_date AND sd.fact_date<=f.horizon_date),0) actual,f.confidence_state FROM {$f} f WHERE f.product_id=%d AND f.horizon_days=%d AND f.horizon_date<=CURDATE() ORDER BY f.generated_for_date DESC LIMIT %d";return $wpdb->get_results($wpdb->prepare($sql,absint($product_id),max(1,(int)$horizon_days),min(100,max(1,(int)$limit))),ARRAY_A);}
 public static function product_metrics($product_id,$horizon_days=30,$limit=12){$rows=self::product_rows($product_id,$horizon_days,$limit);$simple=array();foreach($rows as $r){$simple[]=array('predicted'=>$r['predicted'],'actual'=>$r['actual']);}return self::metrics($simple);}
}
