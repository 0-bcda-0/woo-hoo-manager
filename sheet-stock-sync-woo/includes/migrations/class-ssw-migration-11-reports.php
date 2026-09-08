<?php
/** Approved #39 weekly report persistence schema. */
defined('ABSPATH')||exit;
final class SSW_Migration_11_Reports{
 public static function run(){global $wpdb;$t=$wpdb->prefix.'ssw_reports';$cc=$wpdb->get_charset_collate();dbDelta("CREATE TABLE {$t} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nreport_type varchar(50) NOT NULL,\nperiod_start date NOT NULL,\nperiod_end date NOT NULL,\npayload_json longtext NOT NULL,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY type_period (report_type,period_start,period_end),\nKEY period_end (period_end)\n) {$cc};");}
}
