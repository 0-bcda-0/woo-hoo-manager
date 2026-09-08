<?php
/**
 * Lightweight analytics job policy test.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/tmp-wordpress/' ); }
if ( ! function_exists( 'absint' ) ) { function absint( $value ) { return abs( (int) $value ); } }

function ssw_assert_jobs( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
}

$class_file = dirname( __DIR__ ) . '/sheet-stock-sync-woo/includes/class-ssw-analytics-jobs.php';
ssw_assert_jobs( file_exists( $class_file ), 'class-ssw-analytics-jobs.php must exist.' );
require_once $class_file;

ssw_assert_jobs( 10 === SSW_Analytics_Jobs::normalize_batch_size( 1 ), 'Analytics jobs enforce a minimum batch size.' );
ssw_assert_jobs( 100 === SSW_Analytics_Jobs::normalize_batch_size( 100 ), 'Analytics jobs preserve normal batch size.' );
ssw_assert_jobs( 250 === SSW_Analytics_Jobs::normalize_batch_size( 1000 ), 'Analytics jobs enforce a maximum batch size.' );
ssw_assert_jobs( SSW_Analytics_Jobs::CRON_BACKFILL !== SSW_Analytics_Jobs::CRON_SNAPSHOT, 'Backfill and snapshot hooks are distinct.' );
ssw_assert_jobs( defined( 'SSW_Analytics_Jobs::CRON_METRICS' ), 'Analytics jobs expose a distinct daily metrics hook.' );
ssw_assert_jobs( SSW_Analytics_Jobs::CRON_METRICS !== SSW_Analytics_Jobs::CRON_SNAPSHOT, 'Metrics and snapshot hooks are distinct.' );
ssw_assert_jobs( SSW_Analytics_Jobs::CRON_METRICS !== SSW_Analytics_Jobs::CRON_BACKFILL, 'Metrics and backfill hooks are distinct.' );

fwrite( STDOUT, "PASS: analytics job policy\n" );
