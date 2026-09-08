<?php
/**
 * Uninstall cleanup for Stock Manager for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
	'ssw_settings',
	'ssw_last_log',
	'ssw_report_log',
	'ssw_report_schedule',
	'ssw_license_key',
	'ssw_installed_at',
	'ssw_install_id',
	'ssw_license_check',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

delete_transient( 'ssw_analytics_report' );
wp_clear_scheduled_hook( 'ssw_low_stock_report' );
wp_clear_scheduled_hook( 'ssw_license_check_event' );

// Per-product metadata added by this plugin.
$products = wc_get_products(
	array(
		'limit'  => -1,
		'return' => 'ids',
		'status' => array( 'publish', 'draft', 'private', 'pending' ),
	)
);

foreach ( $products as $product_id ) {
	delete_post_meta( $product_id, '_ssw_units_per_box' );
}
