<?php
/**
 * Small, dependency-free chart rendering for the analytics screen.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the two charts the analytics screen needs, as inline SVG/HTML
 * with no charting library: one bar per day of the current month, and a
 * ranked bar list of best sellers.
 *
 * Both are single-series magnitude charts, so they use one hue rather than
 * a categorical palette — colour here carries no meaning beyond "this is a
 * data mark", and every value is also readable as text.
 */
class SSW_Chart {

	/**
	 * Bar chart of revenue per day for the current month.
	 *
	 * @param array  $daily    Map of Y-m-d => revenue.
	 * @param string $currency Currency symbol.
	 * @return string SVG markup.
	 */
	public static function daily_revenue( array $daily, $currency ) {
		if ( empty( $daily ) ) {
			return '<p class="ssw-empty">' . esc_html__( 'No days to show yet.', 'sheet-stock-sync-woo' ) . '</p>';
		}

		$width   = 720;
		$height  = 190;
		$pad_l   = 8;
		$pad_r   = 8;
		$pad_t   = 16;
		$pad_b   = 26;
		$plot_w  = $width - $pad_l - $pad_r;
		$plot_h  = $height - $pad_t - $pad_b;
		$count   = count( $daily );
		$max     = max( array_map( 'floatval', array_values( $daily ) ) );
		$max     = $max > 0 ? $max : 1;
		$step    = $plot_w / $count;
		$bar_w   = max( 3, min( 26, $step - 2 ) ); // 2px surface gap between bars.
		$baseline = $pad_t + $plot_h;

		$svg  = '<svg class="ssw-chart" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="' . $height . '" role="img" preserveAspectRatio="xMidYMid meet" aria-label="'
			. esc_attr__( 'Revenue per day this month', 'sheet-stock-sync-woo' ) . '">';

		foreach ( array( 0.5, 1 ) as $fraction ) {
			$y    = $baseline - ( $plot_h * $fraction );
			$svg .= '<line x1="' . $pad_l . '" y1="' . round( $y, 1 ) . '" x2="' . ( $width - $pad_r ) . '" y2="' . round( $y, 1 )
				. '" stroke="var(--ssw-grid)" stroke-width="1" />';
		}

		$svg .= '<text x="' . $pad_l . '" y="' . ( $pad_t - 4 ) . '" fill="var(--ssw-axis)" font-size="11" font-family="inherit">'
			. esc_html( $currency . ' ' . number_format_i18n( $max, 0 ) ) . '</text>';

		$i = 0;
		foreach ( $daily as $day => $value ) {
			$value  = (float) $value;
			$x      = $pad_l + ( $i * $step ) + ( ( $step - $bar_w ) / 2 );
			$bar_h  = $max > 0 ? ( $value / $max ) * $plot_h : 0;
			$bar_h  = $value > 0 ? max( 2, $bar_h ) : 0;
			$y      = $baseline - $bar_h;
			$stamp  = strtotime( $day . ' 12:00:00' );
			$label  = sprintf(
				/* translators: 1: date, 2: revenue */
				__( '%1$s — %2$s', 'sheet-stock-sync-woo' ),
				wp_date( 'j. M', $stamp ),
				$currency . ' ' . number_format_i18n( $value, 2 )
			);

			if ( $bar_h > 0 ) {
				$svg .= '<path class="ssw-bar" d="' . esc_attr( self::rounded_bar_path( $x, $y, $bar_w, $bar_h, 4 ) ) . '" fill="var(--ssw-series)">'
					. '<title>' . esc_html( $label ) . '</title></path>';
			} else {
				$svg .= '<rect x="' . round( $x, 1 ) . '" y="' . ( $baseline - 2 ) . '" width="' . round( $bar_w, 1 ) . '" height="2" fill="var(--ssw-grid)">'
					. '<title>' . esc_html( $label ) . '</title></rect>';
			}

			$day_number = (int) substr( $day, 8, 2 );
			if ( 1 === $day_number % 5 || $i === $count - 1 ) {
				$svg .= '<text x="' . round( $x + ( $bar_w / 2 ), 1 ) . '" y="' . ( $height - 8 ) . '" fill="var(--ssw-axis)" font-size="11" font-family="inherit" text-anchor="middle">'
					. esc_html( $day_number ) . '</text>';
			}

			$i++;
		}

		$svg .= '<line x1="' . $pad_l . '" y1="' . $baseline . '" x2="' . ( $width - $pad_r ) . '" y2="' . $baseline
			. '" stroke="var(--ssw-baseline)" stroke-width="1" />';
		$svg .= '</svg>';

		return $svg;
	}

	public static function top_products( array $items, $currency ) {
		if ( empty( $items ) ) {
			return '<p class="ssw-empty">' . esc_html__( 'No sales recorded this month yet.', 'sheet-stock-sync-woo' ) . '</p>';
		}

		$max = 0;
		foreach ( $items as $item ) {
			$max = max( $max, (float) $item['qty'] );
		}
		$max = $max > 0 ? $max : 1;

		$out = '<ol class="ssw-rank">';

		foreach ( $items as $item ) {
			$share = ( (float) $item['qty'] / $max ) * 100;

			$out .= '<li class="ssw-rank-row">'
				. '<span class="ssw-rank-name">' . esc_html( $item['name'] ) . '</span>'
				. '<span class="ssw-rank-track"><span class="ssw-rank-bar" style="width:' . round( $share, 2 ) . '%"></span></span>'
				. '<span class="ssw-rank-qty">' . esc_html( number_format_i18n( $item['qty'] ) ) . ' ' . esc_html__( 'pcs', 'sheet-stock-sync-woo' ) . '</span>'
				. '<span class="ssw-rank-rev">' . esc_html( $currency . ' ' . number_format_i18n( $item['revenue'], 2 ) ) . '</span>'
				. '</li>';
		}

		$out .= '</ol>';

		return $out;
	}

	private static function rounded_bar_path( $x, $y, $w, $h, $radius ) {
		$r = min( $radius, $w / 2, $h );

		$x = round( $x, 2 );
		$y = round( $y, 2 );
		$w = round( $w, 2 );
		$h = round( $h, 2 );
		$r = round( $r, 2 );

		return "M{$x},"
			. ( $y + $h )
			. " L{$x}," . ( $y + $r )
			. " Q{$x},{$y} " . ( $x + $r ) . ",{$y}"
			. ' L' . ( $x + $w - $r ) . ",{$y}"
			. ' Q' . ( $x + $w ) . ",{$y} " . ( $x + $w ) . ',' . ( $y + $r )
			. ' L' . ( $x + $w ) . ',' . ( $y + $h )
			. ' Z';
	}
}
