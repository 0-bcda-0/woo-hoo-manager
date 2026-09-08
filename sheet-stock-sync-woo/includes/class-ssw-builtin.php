<?php
/**
 * Product stock table data for Sheet Stock Sync for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists WooCommerce products/variations as simple rows (id, SKU, name,
 * quantity, threshold, level, price, categories) for the in-admin editable
 * table, and applies single-field updates. WooCommerce itself is the only
 * source of truth — there is no external sheet to drift from.
 */
class SSW_Builtin {

	/**
	 * Safety cap on how many rows the table loads at once. Plenty for the
	 * small/medium catalogs this plugin targets; larger catalogs should use
	 * the CSV/Excel bulk import instead of this screen.
	 */
	const MAX_ROWS = 500;

	/**
	 * Product meta key holding how many pieces are in one box/carton.
	 */
	const META_PER_BOX = '_ssw_units_per_box';

	/**
	 * Cached row set for this request, so the stock screen, the low-stock
	 * report and the analytics screen don't each rebuild it.
	 *
	 * @var array|null
	 */
	private static $rows_cache = null;

	/**
	 * Build the full row list: every simple product, plus one row per
	 * variation of a variable product (variations carry their own SKU and
	 * stock in WooCommerce).
	 *
	 * @param bool $force Rebuild even if this request already built it.
	 * @return array<int, array>
	 */
	public function get_rows( $force = false ) {
		if ( null !== self::$rows_cache && ! $force ) {
			return self::$rows_cache;
		}

		$rows = array();

		$products = wc_get_products(
			array(
				'limit'   => self::MAX_ROWS,
				'status'  => 'publish',
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation instanceof WC_Product ) {
						continue;
					}
					$rows[] = $this->to_row( $variation, $product );
					if ( count( $rows ) >= self::MAX_ROWS ) {
						break 2;
					}
				}
				continue;
			}

			$rows[] = $this->to_row( $product );

			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
		}

		self::$rows_cache = $rows;

		return $rows;
	}

	/**
	 * Convert one WC_Product (or variation) into a row for the table.
	 *
	 * @param WC_Product      $product The product or variation.
	 * @param WC_Product|null $parent  Parent product, when $product is a variation.
	 * @return array
	 */
	private function to_row( $product, $parent = null ) {
		$name       = $product->get_name();
		$cat_source = $product;

		if ( $parent instanceof WC_Product ) {
			$attributes = $product->get_attribute_summary();
			$name       = $parent->get_name() . ( $attributes ? ' — ' . $attributes : '' );
			$cat_source = $parent; // Variations inherit their parent's categories.
		}

		$managed   = (bool) $product->managing_stock();
		$qty       = $managed ? $product->get_stock_quantity() : null;
		$threshold = $this->threshold_for( $product );

		return array(
			'id'         => $product->get_id(),
			'sku'        => $product->get_sku(),
			'name'       => $name,
			'qty'        => null === $qty ? '' : $qty,
			'threshold'  => $threshold,
			'per_box'    => $this->units_per_box( $product ),
			'level'      => $this->level_for( $product, $qty, $threshold, $managed ),
			'status'     => $product->get_stock_status(),
			'managed'    => $managed,
			'price'      => (float) $product->get_price(),
			'categories' => $this->categories_for( $cat_source ),
		);
	}

	/**
	 * How many pieces come in one box/carton of this product, as stored on
	 * the product. Empty when it isn't set — plenty of products are simply
	 * ordered by the piece.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return int|string Positive integer, or '' when not set.
	 */
	public function units_per_box( $product ) {
		$value = $product->get_meta( self::META_PER_BOX, true );

		if ( '' === $value || null === $value || ! is_numeric( $value ) ) {
			return '';
		}

		$value = (int) $value;

		return $value > 0 ? $value : '';
	}

	/**
	 * The low-stock threshold that applies to one product: its own
	 * WooCommerce per-product value when set, otherwise the plugin-wide
	 * fallback from Settings.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return int
	 */
	public function threshold_for( $product ) {
		$own = $product->get_low_stock_amount();

		if ( '' !== $own && null !== $own ) {
			return (int) $own;
		}

		return (int) SSW_Settings::get( 'low_stock_threshold', 3 );
	}

	/**
	 * Classify a product into one of three levels, which is what the
	 * coloured indicator in the table shows.
	 *
	 * @param WC_Product $product   Product.
	 * @param int|null   $qty       Managed quantity, or null when not managed.
	 * @param int        $threshold Applicable threshold.
	 * @param bool       $managed   Whether stock management is on.
	 * @return string 'in' | 'low' | 'out'
	 */
	private function level_for( $product, $qty, $threshold, $managed ) {
		if ( ! $managed ) {
			return 'outofstock' === $product->get_stock_status() ? 'out' : 'in';
		}

		$qty = (float) $qty;

		if ( $qty <= 0 ) {
			return 'out';
		}

		return $qty <= $threshold ? 'low' : 'in';
	}

	/**
	 * Category names for a product, used by the category filter.
	 *
	 * @param WC_Product $product Product (parent, for variations).
	 * @return string[]
	 */
	private function categories_for( $product ) {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'name' );
	}

	public function update_quantity( $product_id, $quantity ) {
		$product = wc_get_product( (int) $product_id );

		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'ssw_no_such_product', __( 'That product no longer exists — reload the page.', 'sheet-stock-sync-woo' ) );
		}

		if ( '' === trim( (string) $quantity ) || ! is_numeric( $quantity ) ) {
			return new WP_Error( 'ssw_bad_quantity', __( 'Quantity must be a number.', 'sheet-stock-sync-woo' ) );
		}

		$settings      = SSW_Settings::get_all();
		$update_status = 'yes' === $settings['update_stock_status'];
		$amount        = wc_stock_amount( $quantity );

		$product->set_manage_stock( true );
		$product->set_stock_quantity( $amount );

		if ( $update_status ) {
			$product->set_stock_status( $amount > 0 ? 'instock' : 'outofstock' );
		}

		$product->save();

		return $this->to_row( $product, $this->parent_of( $product ) );
	}

	public function update_threshold( $product_id, $threshold ) {
		$product = wc_get_product( (int) $product_id );

		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'ssw_no_such_product', __( 'That product no longer exists — reload the page.', 'sheet-stock-sync-woo' ) );
		}

		$threshold = trim( (string) $threshold );

		if ( '' !== $threshold && ( ! is_numeric( $threshold ) || (float) $threshold < 0 ) ) {
			return new WP_Error( 'ssw_bad_threshold', __( 'Threshold must be zero or more.', 'sheet-stock-sync-woo' ) );
		}

		$product->set_low_stock_amount( '' === $threshold ? '' : wc_stock_amount( $threshold ) );
		$product->save();

		return $this->to_row( $product, $this->parent_of( $product ) );
	}

	public function update_units_per_box( $product_id, $per_box ) {
		$product = wc_get_product( (int) $product_id );

		if ( ! $product instanceof WC_Product ) {
			return new WP_Error( 'ssw_no_such_product', __( 'That product no longer exists — reload the page.', 'sheet-stock-sync-woo' ) );
		}

		$per_box = trim( (string) $per_box );

		if ( '' !== $per_box && ( ! is_numeric( $per_box ) || (int) $per_box < 1 ) ) {
			return new WP_Error( 'ssw_bad_per_box', __( 'Pieces per box must be 1 or more.', 'sheet-stock-sync-woo' ) );
		}

		if ( '' === $per_box ) {
			$product->delete_meta_data( self::META_PER_BOX );
		} else {
			$product->update_meta_data( self::META_PER_BOX, (int) $per_box );
		}

		$product->save();

		return $this->to_row( $product, $this->parent_of( $product ) );
	}

	private function parent_of( $product ) {
		$parent_id = $product->get_parent_id();

		if ( ! $parent_id ) {
			return null;
		}

		$parent = wc_get_product( $parent_id );

		return $parent instanceof WC_Product ? $parent : null;
	}
}
