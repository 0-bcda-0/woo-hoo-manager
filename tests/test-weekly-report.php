<?php
declare(strict_types=1);
if(!defined('ABSPATH')){define('ABSPATH',__DIR__.'/tmp-wordpress/');}
function ssw_assert_weekly($c,$m){if(!$c){fwrite(STDERR,"FAIL: {$m}\n");exit(1);}}
$file=dirname(__DIR__).'/sheet-stock-sync-woo/includes/class-ssw-weekly-report.php';
ssw_assert_weekly(file_exists($file),'Weekly report class must exist.');
require_once $file;
ssw_assert_weekly(25.0===SSW_Weekly_Report::percent_change(125,100),'Percent change is deterministic.');
ssw_assert_weekly(null===SSW_Weekly_Report::percent_change(10,0),'Zero comparison baseline yields unknown percent change.');
$p=SSW_Weekly_Report::build_payload(array('period_start'=>'2026-09-02','period_end'=>'2026-09-08','revenue'=>1000,'orders'=>20,'units'=>50,'revenue_at_risk'=>120,'estimated_lost_sales'=>40,'stockouts'=>2,'dead_slow_count'=>5,'health_average'=>71,'critical_alerts'=>3,'warning_alerts'=>8,'bundle_opportunities'=>2,'purchasing_cash'=>array('EUR'=>500)),array('revenue'=>800,'orders'=>16,'units'=>40,'health_average'=>68));
ssw_assert_weekly(25.0===$p['week_over_week']['revenue_percent'],'Weekly report compares revenue week over week.');
ssw_assert_weekly(120.0===$p['inventory']['revenue_at_risk'],'Weekly report contains Revenue at Risk.');
ssw_assert_weekly(40.0===$p['inventory']['estimated_lost_sales'],'Weekly report contains Estimated Lost Sales.');
ssw_assert_weekly(500.0===$p['purchasing']['cash_by_currency']['EUR'],'Weekly report contains approved purchasing cash outlook.');
ssw_assert_weekly(!isset($p['gmroi'])&&!isset($p['pareto'])&&!isset($p['action_center']),'Weekly report does not reintroduce removed features.');
fwrite(STDOUT,"PASS: weekly executive report\n");
