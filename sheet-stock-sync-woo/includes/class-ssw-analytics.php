<?php
/**
 * Sales analytics for Sheet Stock Sync for WooCommerce.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates paid orders into the handful of numbers a shop owner actually
 * acts on: what sold this month, how that compares to last month, what is
 * sitting on the shelf unsold, and what the stock on hand is worth.
 *
 * Orders are read through wc_get_orders(), never with direct SQL, so this
 * works the same on HPOS and on legacy post storage. The whole report is
 * cached, because walking order line items is the expensive part.
 */
class SSW_Analytics {

	/**
	 * How long a built report stays cached.
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Hard cap on orders pulled in one pass, so a busy shop can never turn
	 * this screen into a timeout.
	 */
	const MAX_ORDERS = 2000;

	/**
	 * Days without a sale after which stock counts as "dead".
	 */
	const DEAD_STOCK_DAYS = 60;

	/**
	 * Get the report, building it only when there's no fresh cache.
	 *
	 * @param bool $force Rebuild even if cached.
	 * @return array
	 */
	public function get_report( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( SSW_TRANSIENT_ANALYTICS );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$report = $this->build_report();

		set_transient( SSW_TRANSIENT_ANALYTICS, $report, self::CACHE_TTL );

		return $report;
	}

	/**
	 * Throw away the cached report (used by the "Refresh" button and after
	 * a bulk stock import, which changes stock value).
	 */
	public static function clear_cache() {
		delete_transient( SSW_TRANSIENT_ANALYTICS );
	}

	/**
	 * Walk recent orders once and derive every figure from that single pass.
	 *
	 * @return array
	 */
	private function build_report() {
		$tz          = wp_timezone();
		$now         = new DateTimeImmutable( 'now', $tz );
		$month_start = $now->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$prev_start  = $month_start->modify( '-1 month' );
		$dead_since  = $now->modify( '-' . self::DEAD_STOCK_DAYS . ' days' );

		// Pull from whichever is earlier: last month's start, or the dead-stock
		// window — one query has to cover both comparisons.
		$from = $prev_start < $dead_since ? $prev_start : $dead_since;

		$orders = wc_get_orders(
			array(
				'limit'        => self::MAX_ORDERS,
				'status'       => wc_get_is_paid_statuses(),
				'date_created' => '>=' . $from->format( 'Y-m-d H:i:s' ),
				'orderby'      => 'date',
				'order'        => 'DESC',
			)
		);

		$report = array(
			'generated'      => current_time( 'mysql' ),
			'currency'       => get_woocommerce_currency_symbol(),
			// wp_date(), not date_i18n(): the month start is midnight in the
			// site's timezone, which in UTC still belongs to the previous
			// month for any positive offset — date_i18n() would then label
			// September as August.
			'month_label'    => wp_date( 'F Y', $month_start->getTimestamp() ),
			'this_month'     => $this->empty_period(),
			'last_month'     => $this->empty_period(),
			'top_products'   => array(),
			'daily'          => $this->empty_daily( $month_start, $now ),
			'sold_recently'  => array(),
			'order_cap_hit'  => count( $orders ) >= self::MAX_ORDERS,
		);

		$product_totals = array();

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$created = $order->get_date_created();

			if ( ! $created ) {
				continue;
			}

			$stamp     = $created->getTimestamp();
			$in_month  = $stamp >= $month_start->getTimestamp();
			$in_prev   = ! $in_month && $stamp >= $prev_start->getTimestamp();
			$is_recent = $stamp >= $dead_since->getTimestamp();

			if ( $in_month ) {
				$report['this_month']['revenue'] += (float) $order->get_total();
				$report['this_month']['orders']++;
			} elseif ( $in_prev ) {
				$report['last_month']['revenue'] += (float) $order->get_total();
				$report['last_month']['orders']++;
			}

			$day_key = wp_date( 'Y-m-d', $stamp );

			foreach ( $order->get_items() as $item ) {
				$qty        = (float) $item->get_quantity();
				$line_total = (float) $item->get_total();
				$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

				if ( $is_recent && $product_id ) {
					$report['sold_recently'][ $product_id ] = true;
				}

				if ( $in_month ) {
					$report['this_month']['items'] += $qty;

					if ( isset( $report['daily'][ $day_key ] ) ) {
						$report['daily'][ $day_key ] += $line_total;
					}

					if ( $product_id ) {
						if ( ! isset( $product_totals[ $product_id ] ) ) {
							$product_totals[ $product_id ] = array(
								'id'      => $product_id,
								'name'    => $item->get_name(),
								'qty'     => 0,
								'revenue' => 0,
							);
						}
						$product_totals[ $product_id ]['qty']     += $qty;
						$product_totals[ $product_id ]['revenue'] += $line_total;
					}
				} elseif ( $in_prev ) {
					$report['last_month']['items'] += $qty;
				}
			}
		}

		// Best sellers this month, by units sold.
		usort(
			$product_totals,
			function ( $a, $b ) {
				return $b['qty'] <=> $a['qty'];
			}
		);
		$report['top_products'] = array_slice( array_values( $product_totals ), 0, 8 );

		$report['this_month']['average'] = $report['this_month']['orders']
			? $report['this_month']['revenue'] / $report['this_month']['orders']
			: 0;
		$report['last_month']['average'] = $report['last_month']['orders']
			? $report['last_month']['revenue'] / $report['last_month']['orders']
			: 0;

		$stock = $this->stock_figures( $report['sold_recently'] );

		$report['stock_value'] = $stock['value'];
		$report['dead_stock']  = $stock['dead'];
		$report['levels']      = $stock['levels'];

		unset( $report['sold_recently'] ); // Only needed while building.

		return $report;
	}

	/**
	 * Stock-side figures: what the shelf is worth, how products split
	 * across the three levels, and which stocked products haven't sold
	 * within the dead-stock window.
	 *
	 * @param array $sold_recently Map of product_id => true.
	 * @return array
	 */
	private function stock_figures( array $sold_recently ) {
		$builtin = new SSW_Builtin();
		$value   = 0;
		$dead    = array();
		$levels  = array(
			'in'  => 0,
			'low' => 0,
			'out' => 0,
		);

		foreach ( $builtin->get_rows() as $row ) {
			$qty = is_numeric( $row['qty'] ) ? (float) $row['qty'] : 0;

			if ( isset( $levels[ $row['level'] ] ) ) {
				$levels[ $row['level'] ]++;
			}

			if ( $qty > 0 ) {
				$value += $qty * (float) $row['price'];

				if ( ! isset( $sold_recently[ $row['id'] ] ) ) {
					$dead[] = array(
						'id'    => $row['id'],
						'name'  => $row['name'],
						'sku'   => $row['sku'],
						'qty'   => $qty,
						'value' => $qty * (float) $row['price'],
					);
				}
			}
		}

		// Most money tied up first — that's the one worth discounting.
		usort(
			$dead,
			function ( $a, $b ) {
				return $b['value'] <=> $a['value'];
			}
		);

		return array(
			'value'  => $value,
			'dead'   => array_slice( $dead, 0, 10 ),
			'levels' => $levels,
		);
	}

	/**
	 * Percentage change between two figures, guarding the divide-by-zero
	 * case that a first month of trading always produces.
	 *
	 * @param float $current  This period.
	 * @param float $previous Previous period.
	 * @return float|null Null when there's no basis for comparison.
	 */
	public static function change( $current, $previous ) {
		if ( ! $previous ) {
			return null;
		}

		return ( ( $current - $previous ) / $previous ) * 100;
	}

	/**
	 * Empty totals for one period.
	 *
	 * @return array
	 */
	private function empty_period() {
		return array(
			'revenue' => 0,
			'orders'  => 0,
			'items'   => 0,
			'average' => 0,
		);
	}

	/**
	 * A zero-filled day => revenue map for the current month up to today,
	 * so the chart has a bar for every day rather than only days with sales.
	 *
	 * @param DateTimeImmutable $start Month start.
	 * @param DateTimeImmutable $now   Now.
	 * @return array<string, float>
	 */
	private function empty_daily( DateTimeImmutable $start, DateTimeImmutable $now ) {
		$days   = array();
		$cursor = $start;

		while ( $cursor->getTimestamp() <= $now->getTimestamp() ) {
			$days[ $cursor->format( 'Y-m-d' ) ] = 0;
			$cursor                             = $cursor->modify( '+1 day' );
		}

		return $days;
	}
}
