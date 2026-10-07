<?php
/**
 * تست‌های «تعمیر مکانیکی محتوا» (inc/content-repair.php)
 *
 * رفتارِ درخواستی: H1 داخل بدنهٔ موجودیت‌ها به H2 تنزل کند، ولی هیچ چیز دیگری
 * عوض نشود؛ پیش‌نمایش پیش‌فرض باشد؛ اعمال فقط با manage_options + nonce؛
 * برگه/نوشته/رسانه هرگز لمس نشوند.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-content-repair.php
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

require '/ws/theme/inc/entities-config.php';
require '/ws/theme/inc/content-repair.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/**
 * ثبت نوشتهٔ آزمایشی.
 *
 * @param string $type    نوع.
 * @param string $content محتوا.
 * @param string $status  وضعیت.
 * @return int
 */
function sa_seed( $type, $content, $status = 'publish' ) {
	return sa_add_post(
		array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_content' => $content,
		)
	);
}

/* ------------------------------------------------- تابع خالص: تنزل H1 ----- */

echo "\n۱) تابع خالصِ تنزل H1\n";

$changed = null;
$out     = sa_repair_demote_h1( '<h1>عنوان</h1><p>متن</p>', $changed );
sa_eq( 'یک H1 به H2 تبدیل می‌شود', '<h2>عنوان</h2><p>متن</p>', $out );
sa_eq( 'شمارش درست است', 1, $changed );

$out = sa_repair_demote_h1( '<h1 class="wp-block-heading" id="x">ت</h1>', $changed );
sa_eq( 'ویژگی‌ها و کلاس‌ها حفظ می‌شوند', '<h2 class="wp-block-heading" id="x">ت</h2>', $out );

$out = sa_repair_demote_h1( "<h1>یک</h1>\n<H1 class='b'>دو</H1>\n<h2>سه</h2>", $changed );
sa_eq( 'چند H1 (و حالت بزرگ حروف) هر دو تبدیل می‌شوند', "<h2>یک</h2>\n<h2 class='b'>دو</h2>\n<h2>سه</h2>", $out );
sa_eq( 'شمارش دو سربرگ', 2, $changed );

$out = sa_repair_demote_h1( '<p>بدون سربرگ</p>', $changed );
sa_eq( 'متن بدون H1 دست‌نخورده می‌ماند', '<p>بدون سربرگ</p>', $out );
sa_eq( 'شمارش صفر است', 0, $changed );

$out = sa_repair_demote_h1( '<h2>لینک</h2><p>متن با <a href="https://sarzaminaryan.ir/" target="_blank">h1</a> در متن</p>', $changed );
sa_eq( 'کلمهٔ h1 داخل متن/لینک دست‌نخورده می‌ماند', '<h2>لینک</h2><p>متن با <a href="https://sarzaminaryan.ir/" target="_blank">h1</a> در متن</p>', $out );
sa_eq( 'شمارش همچنان صفر است', 0, $changed );

$stats = sa_repair_h1_stats( '<h1>باز بدون بسته' );
sa_eq( 'برچسب بدون بسته در آمار «باز» شمرده می‌شود', 1, $stats['open'] );
sa_eq( 'برچسب بدون بسته در آمار «جفت» نیامده', 0, $stats['pairs'] );

/* --------------------------------------------- اجرای پیش‌نمایش و اعمال ---- */

echo "\n۲) اجرای دسته‌ای (پیش‌نمایش و اعمال)\n";

sa_reset_test_state();

$city_h1     = sa_seed( 'city', '<h1>سربرگ بدنه</h1><p>متن شهرستان</p>' );
$city_clean  = sa_seed( 'city', '<p>متن سالم</p>' );
$prov_h1     = sa_seed( 'province', '<h2>زیرعنوان</h2><h1>سربرگ دوم</h1>' );
$draft_h1    = sa_seed( 'attraction', '<h1>پیش‌نویس</h1>', 'draft' );
$page_h1     = sa_seed( 'page', '<h1>عنوان برگه</h1>' );

$report = sa_repair_run( 'dry' );
sa_eq( 'پیش‌نمایش سه موجودیت را می‌بیند', 3, $report['posts'] );
sa_eq( 'پیش‌نمایش سه سربرگ می‌شمارد', 3, $report['headings'] );
sa_eq( 'وضعیت گزارش dry است', 'dry', $report['mode'] );
sa_has( 'متن شهرستان هنوز H1 دارد', '<h1>', (string) get_post_field( 'post_content', $city_h1 ) );
sa_has( 'برگه هیچ‌وقت نامزد نمی‌شود', '<h1>عنوان برگه</h1>', (string) get_post_field( 'post_content', $page_h1 ) );

$report = sa_repair_run( 'apply' );
sa_eq( 'اعمال، سه نوشته را تغییر می‌دهد', 3, $report['posts'] );
sa_eq( 'مجموع سربرگ‌های تبدیل‌شده', 3, $report['headings'] );
sa_lacks( 'H1 در شهرستان تمام شد', '<h1>', (string) get_post_field( 'post_content', $city_h1 ) );
sa_has( 'و H2 جایش آمد', '<h2>سربرگ بدنه</h2>', (string) get_post_field( 'post_content', $city_h1 ) );
sa_eq( 'متن تازه دقیق است', '<h2>سربرگ بدنه</h2><p>متن شهرستان</p>', (string) get_post_field( 'post_content', $city_h1 ) );
sa_lacks( 'استان هم پاک شد', '<h1>', (string) get_post_field( 'post_content', $prov_h1 ) );
sa_has( 'زیرعنوان استان دست‌نخورده ماند', '<h2>زیرعنوان</h2>', (string) get_post_field( 'post_content', $prov_h1 ) );
sa_has( 'پیش‌نویس هم اصلاح شد (موجودیت است)', '<h2>پیش‌نویس</h2>', (string) get_post_field( 'post_content', $draft_h1 ) );
sa_eq( 'برگه دست‌نخورده ماند', '<h1>عنوان برگه</h1>', (string) get_post_field( 'post_content', $page_h1 ) );
sa_eq( 'متن سالم تغییری نکرد', '<p>متن سالم</p>', (string) get_post_field( 'post_content', $city_clean ) );

$last = sa_repair_last();
sa_eq( 'گزارش آخرین اجرا ذخیره شده است', 'apply', $last['mode'] );

$after = sa_repair_run( 'dry' );
sa_eq( 'اجرای دوباره چیزی برای اصلاح پیدا نمی‌کند', 0, $after['posts'] );

/* --------------------------------------------------- سقف دسته و کنترل‌ها -- */

echo "\n۳) سقف دسته، دسترسی و nonce\n";

sa_reset_test_state();
sa_seed( 'city', '<h1>الف</h1>' );
sa_seed( 'city', '<h1>ب</h1>' );
sa_seed( 'city', '<h1>ج</h1>' );

$limited = sa_repair_run( 'dry', 2 );
sa_eq( 'با سقف ۲، فقط دو نامزد بررسی می‌شود', 2, $limited['scanned'] );
sa_eq( 'و دو مورد پیشنهاد می‌شود', 2, $limited['posts'] );
sa_ok( 'اعلام می‌کند که کار باقی مانده', ! empty( $limited['remaining'] ) );

sa_set_caps( array( 'manage_options' => false ) );
$_POST = array( 'action' => 'sa_repair_h1', 'sa_repair_mode' => 'apply' );
$died = '';
try {
	sa_repair_action();
} catch ( Exception $e ) {
	$died = $e->getMessage();
}
sa_has( 'بدون دسترسی، wp_die اجرا می‌شود', 'wp_die', $died );
sa_has( 'متن H1 بی‌دسترسی دست‌نخورده است', '<h1>الف</h1>', (string) get_post_field( 'post_content', 1 ) );

sa_set_caps( array( 'manage_options' => true ) );
$report = sa_repair_action();
sa_eq( 'با دسترسی، nonce بررسی و اعمال انجام می‌شود', 'apply', $report['mode'] );
sa_eq( 'هر سه مورد اصلاح شد', 3, $report['posts'] );

/* ------------------------------------------------------ نمایش پیشخوان ----- */

echo "\n۴) رندر بخش پیشخوان\n";

sa_reset_test_state();
ob_start();
sa_repair_section();
$html = (string) ob_get_clean();
sa_has( 'در نبود مورد، پیام «موردی نیست» می‌آید', 'موردی نیست', $html );

sa_seed( 'city', '<h1>سربرگ</h1>' );
ob_start();
sa_repair_section();
$html = (string) ob_get_clean();
sa_has( 'تیتر بخش', 'تعمیر مکانیکی: H1 داخل بدنه', $html );
sa_has( 'دکمهٔ پیش‌نمایش', 'پیش‌نمایش (بدون تغییر)', $html );
sa_has( 'دکمهٔ اعمال', 'اعمال تنزل H1 → H2', $html );
sa_has( 'اکشن admin-post', 'action" value="sa_repair_h1"', $html );
sa_has( 'فیلد nonce', 'name="_wpnonce"', $html );

sa_set_caps( array( 'manage_options' => false ) );
ob_start();
sa_repair_section();
$html = (string) ob_get_clean();
sa_eq( 'برای کاربر کم‌دسترسی چیزی چاپ نمی‌شود', '', $html );

sa_done();
