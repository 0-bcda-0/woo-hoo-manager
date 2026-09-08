<?php
/**
 * Settings storage/helper for Sheet Stock Sync for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around the plugin's single options row. Keeping every
 * default and getter in one place avoids "magic array key" typos spread
 * across the admin, import and reporting classes.
 */
class SSW_Settings {

	/**
	 * Default settings used on activation and as a fallback if the option
	 * is ever missing or corrupted.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Column headers expected in an uploaded CSV/XLSX file when
			// bulk-updating stock (also used for exported files).
			'sku_column'            => 'SKU',
			'quantity_column'       => 'Quantity',

			// Whether importing/editing a quantity also flips the
			// WooCommerce "In stock" / "Out of stock" status automatically.
			'update_stock_status'   => 'yes',

			// Fallback low-stock threshold, used for any product that has
			// no per-product threshold of its own. At or below this number
			// a product counts as "running low".
			'low_stock_threshold'   => 3,

			// Scheduled "these products are running low" e-mail.
			'low_stock_email'       => 'yes',
			'low_stock_recipients'  => '',      // Blank = site admin e-mail.
			'low_stock_frequency'   => 'weekly', // 'daily' | 'weekly'.

			// How many units to suggest ordering: threshold x this, minus
			// what's on hand. 2 means "restock to double the threshold".
			'reorder_multiplier'    => 2,

			// Language for this plugin's own screens and e-mails. Empty
			// means "follow the site/user language", which is what most
			// sites want; anything else overrides it for this plugin only.
			'admin_language'        => '',
		);
	}

	/**
	 * The languages this plugin ships translations for. English is the
	 * source language, so it needs no translation file.
	 *
	 * @return array<string, string> locale => name in that language.
	 */
	public static function languages() {
		return array(
			'en_US' => 'English',
			'hr'    => 'Hrvatski',
			'de_DE' => 'Deutsch',
			'fr_FR' => 'Français',
			'it_IT' => 'Italiano',
			'es_ES' => 'Español',
			'pt_BR' => 'Português (Brasil)',
			'nl_NL' => 'Nederlands',
			'pl_PL' => 'Polski',
			'ru_RU' => 'Русский',
		);
	}

	/**
	 * Get all settings, merged over the defaults so new keys added in a
	 * future version never come back empty/undefined.
	 *
	 * @return array
	 */
	public static function get_all() {
		$saved = get_option( SSW_OPTION_SETTINGS, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not set.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Overwrite all settings at once (already-sanitized values expected).
	 *
	 * @param array $settings Settings to save.
	 */
	public static function update( array $settings ) {
		update_option( SSW_OPTION_SETTINGS, wp_parse_args( $settings, self::defaults() ) );
	}

	/**
	 * Who the scheduled low-stock report goes to. Falls back to the site
	 * admin address when the setting is blank.
	 *
	 * @return string[]
	 */
	public static function report_recipients() {
		$raw = trim( (string) self::get( 'low_stock_recipients' ) );

		if ( '' === $raw ) {
			return array( get_option( 'admin_email' ) );
		}

		$list = array_map( 'trim', explode( ',', $raw ) );
		$list = array_filter( $list, 'is_email' );

		return empty( $list ) ? array( get_option( 'admin_email' ) ) : array_values( $list );
	}
}
