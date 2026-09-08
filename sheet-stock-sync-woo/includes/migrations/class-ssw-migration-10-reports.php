<?php
/** Weekly executive report snapshot schema. */
defined( 'ABSPATH' ) || exit;

final class SSW_Migration_10_Reports {
	public static function run() {
		global $wpdb;
		$table = $wpdb->prefix . 'ssw_reports';
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "report_type varchar(50) NOT NULL,\n"
			. "period_start date NOT NULL,\n"
			. "period_end date NOT NULL,\n"
			. "payload_json longtext NOT NULL,\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY type_period (report_type,period_start,period_end),\n"
			. "KEY report_period (report_type,period_end)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
