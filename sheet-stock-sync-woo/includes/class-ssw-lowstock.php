<?php
/**
 * Low-stock report: on-screen list, purchase-order CSV, and the scheduled
 * "these products are running low" e-mail.
 *
 * @package SheetStockSyncWoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything that answers "what do I need to reorder?". The same row set
 * feeds the admin screen, the CSV purchase order and the scheduled e-mail,
 * so all three can never disagree with each other.
 */
class SSW_Low_Stock {

	/**
	 * Constructor — wires the cron event, the schedule keeper and the CSV
	 * download endpoint.
	 */
	public function __construct() {
		add_action( SSW_CRON_LOW_STOCK, array( $this, 'run_scheduled_report' ) );
		add_action( 'init', array( $this, 'maybe_reschedule' ) );
		add_action( 'admin_post_ssw_export_purchase_order', array( $this, 'handle_purchase_order_export' ) );
		add_action( 'admin_post_ssw_send_report_now', array( $this, 'handle_send_now' ) );
	}

	/**
	 * Products at or below their threshold, plus everything already out of
	 * stock, ordered most urgent first.
	 *
	 * @return array<int, array> Row arrays with an added 'suggested' key.
	 */
	public function get_rows() {
		$builtin = new SSW_Builtin();
		$rows    = array();

		foreach ( $builtin->get_rows() as $row ) {
			if ( 'in' === $row['level'] ) {
				continue;
			}

			$row['suggested'] = $this->suggested_quantity( $row['qty'], $row['threshold'] );

			// Suppliers sell whole boxes, so once a product has a box size
			// the order is expressed in boxes and rounded up to the next
			// full one — you cannot order 7 pieces of something that comes
			// in fives.
			$per_box = is_numeric( $row['per_box'] ) ? (int) $row['per_box'] : 0;

			if ( $per_box > 0 ) {
				$row['boxes']         = (int) ceil( $row['suggested'] / $per_box );
				$row['order_pieces']  = $row['boxes'] * $per_box;
			} else {
				$row['boxes']        = '';
				$row['order_pieces'] = $row['suggested'];
			}

			$rows[] = $row;
		}

		// Out of stock first, then the lowest quantities: that is the order
		// someone actually works through when placing an order.
		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a['level'] !== $b['level'] ) {
					return 'out' === $a['level'] ? -1 : 1;
				}
				return (float) $a['qty'] <=> (float) $b['qty'];
			}
		);

		return $rows;
	}

	/**
	 * How many units to suggest ordering: bring the product back up to
	 * threshold x multiplier, and never suggest less than one unit.
	 *
	 * @param mixed $qty       Current quantity ('' when unmanaged).
	 * @param int   $threshold Applicable threshold.
	 * @return int
	 */
	public function suggested_quantity( $qty, $threshold ) {
		$multiplier = (float) SSW_Settings::get( 'reorder_multiplier', 2 );
		$multiplier = $multiplier > 0 ? $multiplier : 2;
		$threshold  = max( 1, (int) $threshold );
		$on_hand    = is_numeric( $qty ) ? (float) $qty : 0;

		$target = (int) ceil( $threshold * $multiplier );

		return (int) max( 1, $target - $on_hand );
	}

	/**
	 * Stream the low-stock list as a CSV purchase order: the list of what
	 * to order, with an empty column to fill in the quantity actually
	 * ordered before sending it to a supplier.
	 */
	public function handle_purchase_order_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) );
		}

		check_admin_referer( 'ssw_export_purchase_order' );

		if ( ! SSW_License::is_active() ) {
			wp_die( esc_html__( 'Stock Manager is locked — enter a licence key first.', 'sheet-stock-sync-woo' ) );
		}

		$rows = $this->get_rows();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="narudzbenica-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // Excel-friendly UTF-8 marker.

		fputcsv(
			$out,
			array(
				__( 'SKU', 'sheet-stock-sync-woo' ),
				__( 'Product', 'sheet-stock-sync-woo' ),
				__( 'In stock', 'sheet-stock-sync-woo' ),
				__( 'Threshold', 'sheet-stock-sync-woo' ),
				__( 'Pieces per box', 'sheet-stock-sync-woo' ),
				__( 'Suggested order', 'sheet-stock-sync-woo' ),
				__( 'Boxes to order', 'sheet-stock-sync-woo' ),
				__( 'Pieces in total', 'sheet-stock-sync-woo' ),
				__( 'Ordered', 'sheet-stock-sync-woo' ),
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$out,
				array(
					$row['sku'],
					$row['name'],
					$row['qty'],
					$row['threshold'],
					$row['per_box'],
					$row['suggested'],
					$row['boxes'],
					$row['order_pieces'],
					'', // Left blank on purpose — filled in by hand.
				)
			);
		}

		fclose( $out );
		exit;
	}

	/**
	 * "Send the report now" button on the admin screen.
	 */
	public function handle_send_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'sheet-stock-sync-woo' ) );
		}

		check_admin_referer( 'ssw_send_report_now' );

		if ( ! SSW_License::is_active() ) {
			wp_die( esc_html__( 'Stock Manager is locked — enter a licence key first.', 'sheet-stock-sync-woo' ) );
		}

		$sent   = $this->send_report( true );
		$status = $sent ? 'sent' : 'empty';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'sheet-stock-sync-woo',
					'tab'       => 'lowstock',
					'ssw_notice' => $status,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Cron callback for the scheduled report.
	 */
	public function run_scheduled_report() {
		if ( ! SSW_License::is_active() ) {
			return; // Locked: stop sending, but touch nothing.
		}

		if ( 'yes' !== SSW_Settings::get( 'low_stock_email', 'yes' ) ) {
			return;
		}

		$this->send_report();
	}

	/**
	 * Build and send the low-stock e-mail.
	 *
	 * @param bool $force Send even when the report is empty (manual sends
	 *                    still skip empty reports, but this flag keeps the
	 *                    caller in control of that decision).
	 * @return bool Whether an e-mail was actually sent.
	 */
	public function send_report( $force = false ) {
		$rows = $this->get_rows();

		if ( empty( $rows ) ) {
			// Nothing to reorder: staying silent is the point, otherwise
			// the report becomes noise people filter out.
			$this->log( __( 'Report skipped — nothing is below its threshold.', 'sheet-stock-sync-woo' ) );
			return false;
		}

		$recipients = SSW_Settings::report_recipients();
		$subject    = sprintf(
			/* translators: 1: site name, 2: number of products needing a reorder */
			__( '[%1$s] %2$d product(s) need reordering', 'sheet-stock-sync-woo' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			count( $rows )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $recipients, $subject, $this->build_email_body( $rows ), $headers );

		$this->log(
			$sent
				/* translators: 1: number of products, 2: comma-separated recipients */
				? sprintf( __( 'Report sent: %1$d product(s) to %2$s.', 'sheet-stock-sync-woo' ), count( $rows ), implode( ', ', $recipients ) )
				: __( 'Report could not be sent — check the site\'s e-mail configuration.', 'sheet-stock-sync-woo' )
		);

		return (bool) $sent;
	}

	/**
	 * The HTML body of the report e-mail. Deliberately plain, table-based
	 * markup with inline styles: that is what survives Gmail, Outlook and
	 * the rest without a rendering library.
	 *
	 * @param array $rows Low-stock rows.
	 * @return string
	 */
	private function build_email_body( array $rows ) {
		$link = admin_url( 'admin.php?page=sheet-stock-sync-woo&tab=lowstock' );

		$out  = '<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;color:#1d2327;">';
		$out .= '<p>' . esc_html__( 'These products are at or below their low-stock threshold:', 'sheet-stock-sync-woo' ) . '</p>';
		$out .= '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%;max-width:640px;font-size:14px;">';
		$out .= '<tr style="background:#f0f0f1;text-align:left;">'
			. '<th style="border-bottom:1px solid #dcdcde;">' . esc_html__( 'Product', 'sheet-stock-sync-woo' ) . '</th>'
			. '<th style="border-bottom:1px solid #dcdcde;">' . esc_html__( 'SKU', 'sheet-stock-sync-woo' ) . '</th>'
			. '<th style="border-bottom:1px solid #dcdcde;text-align:right;">' . esc_html__( 'In stock', 'sheet-stock-sync-woo' ) . '</th>'
			. '<th style="border-bottom:1px solid #dcdcde;text-align:right;">' . esc_html__( 'Suggested order', 'sheet-stock-sync-woo' ) . '</th>'
			. '</tr>';

		foreach ( $rows as $row ) {
			$colour = 'out' === $row['level'] ? '#d03b3b' : '#b4690e';
			$label  = 'out' === $row['level']
				? __( 'out of stock', 'sheet-stock-sync-woo' )
				: __( 'running low', 'sheet-stock-sync-woo' );

			// For a boxed product the order is stated in boxes, with the
			// piece count after it, because that is what goes to a supplier.
			if ( '' !== $row['boxes'] ) {
				$order = sprintf(
					/* translators: 1: number of boxes, 2: total pieces */
					__( '%1$d box(es) = %2$d pcs', 'sheet-stock-sync-woo' ),
					$row['boxes'],
					$row['order_pieces']
				);
			} else {
				$order = $row['suggested'];
			}

			$out .= '<tr>'
				. '<td style="border-bottom:1px solid #f0f0f1;">' . esc_html( $row['name'] )
				. '<br><span style="color:' . esc_attr( $colour ) . ';font-size:12px;">' . esc_html( $label ) . '</span></td>'
				. '<td style="border-bottom:1px solid #f0f0f1;color:#646970;">' . esc_html( $row['sku'] ) . '</td>'
				. '<td style="border-bottom:1px solid #f0f0f1;text-align:right;">' . esc_html( $row['qty'] ) . '</td>'
				. '<td style="border-bottom:1px solid #f0f0f1;text-align:right;font-weight:600;">' . esc_html( $order ) . '</td>'
				. '</tr>';
		}

		$out .= '</table>';
		$out .= '<p style="margin-top:20px;"><a href="' . esc_url( $link ) . '" style="color:#2a78d6;">'
			. esc_html__( 'Open the reorder list and download a purchase order', 'sheet-stock-sync-woo' )
			. '</a></p>';
		$out .= '</div>';

		return $out;
	}

	/**
	 * Keep the scheduled event matching the saved frequency, and remove it
	 * entirely when the report is switched off.
	 */
	public function maybe_reschedule() {
		$enabled   = 'yes' === SSW_Settings::get( 'low_stock_email', 'yes' );
		$frequency = 'daily' === SSW_Settings::get( 'low_stock_frequency', 'weekly' ) ? 'daily' : 'weekly';
		$next      = wp_next_scheduled( SSW_CRON_LOW_STOCK );

		if ( ! $enabled ) {
			if ( $next ) {
				wp_unschedule_event( $next, SSW_CRON_LOW_STOCK );
				delete_option( 'ssw_report_schedule' );
			}
			return;
		}

		if ( $next && get_option( 'ssw_report_schedule' ) === $frequency ) {
			return; // Already scheduled at the right interval.
		}

		if ( $next ) {
			wp_unschedule_event( $next, SSW_CRON_LOW_STOCK );
		}

		// Start tomorrow morning rather than "one interval from now", so the
		// report lands at a predictable time of day.
		wp_schedule_event( $this->next_morning(), $frequency, SSW_CRON_LOW_STOCK );
		update_option( 'ssw_report_schedule', $frequency, false );
	}

	/**
	 * Timestamp for 08:00 tomorrow in the site's timezone.
	 *
	 * @return int
	 */
	private function next_morning() {
		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );
		$at  = $now->modify( '+1 day' )->setTime( 8, 0, 0 );

		return $at->getTimestamp();
	}

	/**
	 * Append to a short rolling log so the admin screen can show when the
	 * last report went out and to whom.
	 *
	 * @param string $message Log line.
	 */
	private function log( $message ) {
		$log = get_option( SSW_OPTION_REPORT_LOG, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array(
				'time'    => current_time( 'mysql' ),
				'message' => $message,
			)
		);

		update_option( SSW_OPTION_REPORT_LOG, array_slice( $log, 0, 10 ), false );
	}
}
