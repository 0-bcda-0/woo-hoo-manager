<?php
/**
 * Demand metrics and forecast schema migration.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;

final class SSW_Migration_7_Forecasting {

	public static function run() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$metrics = $wpdb->prefix . 'ssw_product_metrics_daily';
		$sql = "CREATE TABLE {$metrics} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "metric_date date NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "units_7d decimal(19,4) NULL,\n"
			. "units_30d decimal(19,4) NULL,\n"
			. "units_90d decimal(19,4) NULL,\n"
			. "units_365d decimal(19,4) NULL,\n"
			. "velocity_7d decimal(19,6) NULL,\n"
			. "velocity_30d decimal(19,6) NULL,\n"
			. "velocity_90d decimal(19,6) NULL,\n"
			. "velocity_365d decimal(19,6) NULL,\n"
			. "weighted_velocity decimal(19,6) NOT NULL DEFAULT 0,\n"
			. "available_quantity decimal(19,4) NULL,\n"
			. "days_of_stock decimal(19,4) NULL,\n"
			. "projected_stockout_date date NULL,\n"
			. "demand_mean decimal(19,6) NULL,\n"
			. "demand_stddev decimal(19,6) NULL,\n"
			. "demand_cv decimal(19,6) NULL,\n"
			. "avg_realized_price decimal(19,4) NULL,\n"
			. "last_sale_date date NULL,\n"
			. "history_days int(10) unsigned NOT NULL DEFAULT 0,\n"
			. "active_sales_days int(10) unsigned NOT NULL DEFAULT 0,\n"
			. "confidence_state varchar(30) NOT NULL DEFAULT 'insufficient_data',\n"
			. "created_at datetime NOT NULL,\n"
			. "updated_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY date_product (metric_date,product_id),\n"
			. "KEY product_date (product_id,metric_date),\n"
			. "KEY stockout_date (projected_stockout_date),\n"
			. "KEY confidence_state (confidence_state)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );

		$forecasts = $wpdb->prefix . 'ssw_forecasts';
		$sql = "CREATE TABLE {$forecasts} (\n"
			. "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n"
			. "generated_for_date date NOT NULL,\n"
			. "product_id bigint(20) unsigned NOT NULL,\n"
			. "horizon_days int(10) unsigned NOT NULL,\n"
			. "horizon_date date NOT NULL,\n"
			. "forecast_qty decimal(19,4) NOT NULL DEFAULT 0,\n"
			. "model_version varchar(50) NOT NULL,\n"
			. "inputs_hash char(64) NOT NULL,\n"
			. "confidence_state varchar(30) NOT NULL,\n"
			. "created_at datetime NOT NULL,\n"
			. "PRIMARY KEY  (id),\n"
			. "UNIQUE KEY date_product_horizon_model (generated_for_date,product_id,horizon_days,model_version),\n"
			. "KEY product_horizon (product_id,horizon_date),\n"
			. "KEY generated_for_date (generated_for_date)\n"
			. ") {$charset_collate};";
		dbDelta( $sql );
	}
}
