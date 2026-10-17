<?php
/** Private, short-lived county timing snapshots. Never printed in public articles. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Capture only a logged-in administrator's own, uncached county request. */
function sa_county_performance_start() {
	if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ! is_singular( 'city' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$now = microtime( true );
	$GLOBALS['sa_county_performance'] = array(
		'owner' => (int) get_current_user_id(),
		'county_id' => (int) get_queried_object_id(),
		'version' => SA_CHILD_VERSION,
		'start' => $now,
		'request_start' => isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $now,
		'queries_start' => (int) $wpdb->num_queries,
		'timings_ms' => array(), 'calls' => array(),
	);
}
add_action( 'wp', 'sa_county_performance_start', 1 );

/** Store fixed-label durations in memory only; no public headers, HTML or SQL text. */
function sa_county_timing( $stage, $started ) {
	if ( empty( $GLOBALS['sa_county_performance'] ) ) { return; }
	$report =& $GLOBALS['sa_county_performance'];
	if ( ! isset( $report['timings_ms'][ $stage ] ) ) {
		$report['timings_ms'][ $stage ] = 0;
		$report['calls'][ $stage ] = 0;
	}
	$report['timings_ms'][ $stage ] += max( 0, ( microtime( true ) - $started ) * 1000 );
	++$report['calls'][ $stage ];
}

/** Cumulative time before the template, including WordPress/plugins/main query. */
function sa_county_performance_template() {
	if ( ! empty( $GLOBALS['sa_county_performance'] ) ) {
		sa_county_timing( 'before_template', $GLOBALS['sa_county_performance']['request_start'] );
	}
}
add_action( 'template_redirect', 'sa_county_performance_template', PHP_INT_MAX );

/** Timing filters pass content through byte-for-byte, without adding article notes. */
function sa_county_performance_content_start( $content ) {
	if ( ! empty( $GLOBALS['sa_county_performance'] ) && sa_county_page_context() ) {
		$GLOBALS['sa_county_performance_content_stack'][] = microtime( true );
	}
	return $content;
}
function sa_county_performance_content_end( $content ) {
	if ( ! empty( $GLOBALS['sa_county_performance_content_stack'] ) && sa_county_page_context() ) {
		$started = array_pop( $GLOBALS['sa_county_performance_content_stack'] );
		if ( ! $GLOBALS['sa_county_performance_content_stack'] ) {
			sa_county_timing( 'content_filters', $started );
			unset( $GLOBALS['sa_county_performance_content_stack'] );
		}
	}
	return $content;
}
add_filter( 'the_content', 'sa_county_performance_content_start', -999 );
add_filter( 'the_content', 'sa_county_performance_content_end', PHP_INT_MAX );

/** Save one small snapshot per administrator for 15 minutes, with no sensitive logs. */
function sa_county_performance_finish() {
	if ( empty( $GLOBALS['sa_county_performance'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	global $wpdb;
	$report = $GLOBALS['sa_county_performance'];
	$now = microtime( true );
	$data = array(
		'theme_version' => $report['version'], 'county_id' => $report['county_id'],
		'php_to_shutdown_ms' => round( max( 0, $now - $report['request_start'] ) * 1000, 2 ),
		'after_wp_ms' => round( max( 0, $now - $report['start'] ) * 1000, 2 ),
		'db_queries_after_wp' => max( 0, (int) $wpdb->num_queries - $report['queries_start'] ),
		'timings_ms' => array_map( static function ( $value ) { return round( $value, 2 ); }, $report['timings_ms'] ),
		'calls' => $report['calls'], 'captured_at_utc' => gmdate( 'c' ),
	);
	set_transient( 'sa_county_perf_' . $report['owner'], $data, 900 );
	unset( $GLOBALS['sa_county_performance'] );
}
add_action( 'shutdown', 'sa_county_performance_finish', 1 );

/** Private Tools page: report is scoped to the current administrator, not guests. */
function sa_county_performance_menu() {
	add_management_page( 'زمان‌سنج شهرستان', 'زمان‌سنج شهرستان', 'manage_options', 'sa-county-performance', 'sa_county_performance_page' );
}
add_action( 'admin_menu', 'sa_county_performance_menu' );
function sa_county_performance_page() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'دسترسی ندارید.', 'sarzaminaryan-child' ) ); }
	$report = get_transient( 'sa_county_perf_' . (int) get_current_user_id() );
	echo '<div class="wrap"><h1>زمان‌سنج شهرستان</h1><p>با همین حساب مدیر، مقالهٔ شهرستان را باز کنید و پس از پایان بارگذاری به این صفحه برگردید. فقط آخرین نمونهٔ خودتان تا ۱۵ دقیقه نگه داشته می‌شود؛ این گزارش در مقالهٔ عمومی نمایش داده نمی‌شود.</p>';
	echo '<p>اعداد مربوط به اجرای PHP هستند، نه زمان شبکهٔ مرورگر. بخش related_total شامل دو زیرمرحلهٔ related_relations و related_cards است؛ این اعداد را با هم جمع نکنید. پاسخ کش‌شده ممکن است نمونهٔ تازه نسازد.</p>';
	if ( is_array( $report ) ) {
		echo '<textarea readonly class="large-text code" rows="20" aria-label="گزارش زمان‌سنجی">' . esc_textarea( wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</textarea>';
	} else {
		echo '<p>هنوز نمونه‌ای از بازدید مدیر ثبت نشده یا زمان نگهداری آن گذشته است.</p>';
	}
	echo '</div>';
}
