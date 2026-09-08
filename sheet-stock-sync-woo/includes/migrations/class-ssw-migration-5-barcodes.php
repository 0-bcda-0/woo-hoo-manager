<?php
/**
 * Historical schema slot 5.
 *
 * Barcode support was removed from approved product scope. Keep this no-op
 * migration class only so existing schema-version sequences remain stable.
 * New installations do not create barcode tables.
 *
 * @package SheetStockSyncWoo
 */
defined( 'ABSPATH' ) || exit;
final class SSW_Migration_5_Barcodes {
	public static function run() { return; }
}
