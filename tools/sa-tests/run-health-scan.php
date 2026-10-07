<?php
/**
 * تست‌های «گزارش سلامت محتوا» (inc/content-health.php)
 *
 * دلیلِ وجود این تست: ممیزی WXR واقعی نشان داد سایت ۵۴۱ موجودیت دارد در حالی
 * که اسکن‌گر روی ۵۰۰ نوشته قفل بود؛ یعنی گزارش سلامت محتوا بخشی از سایت را
 * بی‌سروصدا نمی‌دید. این تست ثابت می‌کند اسکن همهٔ موجودیت‌ها را در چند صفحه
 * می‌بیند و سقف ایمنی هم شفاف گزارش می‌شود.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-health-scan.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

if ( ! defined( 'SA_ENABLE_ACCOMMODATION' ) ) {
	define( 'SA_ENABLE_ACCOMMODATION', false );
}

/**
 * شبیه‌سازِ sa_fa_digits (inc/helpers.php).
 */
function sa_fa_digits( $value ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, (string) $value );
}

/**
 * شبیه‌سازهای سبکِ ماژول‌های بیرونی (citations / publish-gate).
 */
function sa_citations_enabled() {
	return true;
}
function sa_citation_is_external( $url ) {
	return 0 === strpos( (string) $url, 'http' ) && false === strpos( (string) $url, 'sarzaminaryan' );
}
function sa_gate_missing( $post_id, $type, $context = null ) {
	return array();
}
function sa_gate_split( $missing ) {
	return array( array(), array() );
}

require '/ws/theme/inc/entities-config.php';
require '/ws/theme/inc/content-health.php';
require '/ws/theme/inc/content-repair.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/* ------------------------------------------------------------- داده‌ها ---- */

$total_cities = 520;  // بیشتر از سقفِ پیشینِ ۵۰۰
$h1_every     = 10;   // هر ۱۰ شهر یک H1 بدنه.

for ( $i = 1; $i <= $total_cities; $i++ ) {
	$content = ( 0 === $i % $h1_every ) ? '<h1>سربرگ</h1><p>متن</p>' : '<p>متن</p>';
	sa_add_post(
		array(
			'post_type'    => 'city',
			'post_status'  => 'publish',
			'post_name'    => 'city-' . $i,
			'post_content' => $content,
		)
	);
}
for ( $i = 1; $i <= 20; $i++ ) {
	sa_add_post(
		array(
			'post_type'   => 'province',
			'post_status' => 'publish',
			'post_name'   => 'province-' . $i,
			'post_content' => '<p>متن استان</p>',
		)
	);
}
sa_add_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'about',
		'post_content' => '<h1>درباره ما</h1>', // برگه نباید در اسکن بیاید.
	)
);

/* ------------------------------------------------------------------ اسکن -- */

echo "\n۱) اسکن کامل (بدون سقفِ ۵۰۰)\n";

$scan = sa_health_scan( true );
$t    = $scan['totals'];

sa_eq( 'همهٔ ۵۴۰ موجودیت اسکن می‌شوند', 540, $t['posts'] );
sa_ok( 'شمارش از سقفِ پیشینِ ۵۰۰ گذشت', $t['posts'] > 500 );
sa_eq( 'اسکن در سه صفحه انجام شد', 3, $t['pages'] );
sa_eq( 'سقف ایمنی فعال نشد', false, $t['truncated'] );
sa_eq( 'H1های بدنه شمرده شدند', 52, $t['h1'] );
sa_eq( 'برگهٔ غیرموجودیت اسکن نشد', 540, count( $scan['rows'] ) );

/* ------------------------------------------------------------ سقف ایمنی --- */

echo "\n۲) سقف ایمنی (فیلتردار)\n";

delete_transient( 'sa_health_scan' );
add_filter(
	'sa_health_max_pages',
	function () {
		return 1;
	}
);

$capped = sa_health_scan( true );
sa_eq( 'با سقف یک صفحه، فقط ۲۰۰ نوشته اسکن می‌شود', 200, $capped['totals']['posts'] );
sa_eq( 'و صفحه‌بندی یک صفحه گزارش می‌شود', 1, $capped['totals']['pages'] );
sa_eq( 'سقف ایمنی شفاف علامت می‌خورد', true, $capped['totals']['truncated'] );

/* -------------------------------------------------- رندر صفحه/کارت‌ها ----- */

echo "\n۳) رندر صفحهٔ گزارش\n";

delete_transient( 'sa_health_scan' );
add_filter(
	'sa_health_max_pages',
	function () {
		return 25;
	}
);

$GLOBALS['sa_caps'] = array( 'edit_posts' => true, 'manage_options' => true );
ob_start();
sa_health_page();
$html = (string) ob_get_clean();

sa_has( 'تیتر صفحه', 'سلامت محتوا', $html );
sa_has( 'کارت H1 داخل بدنه', 'H1 داخل بدنه', $html );
sa_has( 'خط «اسکن‌شده» با صفحه‌بندی', 'صفحه (۲۰۰تایی، صفحه‌بندی‌شده)', $html );
sa_lacks( 'در حالت کامل، هشدار سقف چاپ نمی‌شود', 'سقف ایمنی متوقف شد', $html );

delete_transient( 'sa_health_scan' );
add_filter(
	'sa_health_max_pages',
	function () {
		return 1;
	}
);
ob_start();
sa_health_page();
$html = (string) ob_get_clean();
sa_has( 'در حالت بریده، هشدار سقف چاپ می‌شود', 'سقف ایمنی متوقف شد', $html );
sa_has( 'بخش تعمیر مکانیکی در همان صفحه هست', 'تعمیر مکانیکی: H1 داخل بدنه', $html );

sa_done();
