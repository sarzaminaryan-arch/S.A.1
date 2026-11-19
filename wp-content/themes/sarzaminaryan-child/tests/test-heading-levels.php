<?php
/**
 * گارد سلسله‌مراتب تیترها روی صفحهٔ شهرستان (v2.22.148).
 *
 * ادعاها:
 *   ۱) «تجربهٔ مسافرها» H3 است، نه H2: بدنهٔ اصلی مقاله H2 است و این بلوک بر آن
 *      مقدم است، پس باید یک پله پایین‌تر بنشیند. زیربخش‌هایش H4 هستند.
 *   ۲) شمارهٔ زیربخش‌ها پیوسته و بر اساس همان شرط‌هایی است که کادرها را چاپ
 *      می‌کند؛ کادرهای اطلاعات (که عنوان تیتر ندارد) شماره نمی‌گیرد.
 *   ۳) در فهرست مطالب، همین بلوک یک پله پایین‌تر از بدنه ثبت می‌شود.
 *   ۴) هیچ H1 دومی در صفحه ساخته نمی‌شود و ترتیب سکشن‌ها عوض نشده است.
 *
 * اجرا: php tests/test-heading-levels.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}

$sa_hl_fail = 0;

/**
 * @param bool   $cond شرط.
 * @param string $msg  پیام.
 */
function sa_hl_assert( $cond, $msg ) {
	global $sa_hl_fail;
	if ( ! $cond ) {
		++$sa_hl_fail;
		echo "FAIL: {$msg}\n";
	}
}

$sa_hl_dir = SA_CHILD_DIR;
$sa_hl_fn  = (string) file_get_contents( $sa_hl_dir . 'template-parts/county/section-field-notes.php' );
$sa_hl_toc = (string) file_get_contents( $sa_hl_dir . 'inc/toc.php' );
$sa_hl_reg = (string) file_get_contents( $sa_hl_dir . 'inc/county-sections.php' );

// ۱) سطح تیترها.
sa_hl_assert( false !== strpos( $sa_hl_fn, '<h3>تجربهٔ مسافرها</h3>' ), 'تیتر بلوک H3 است (بدنه H2 می‌ماند)' );
sa_hl_assert( false === strpos( $sa_hl_fn, '<h2>تجربهٔ مسافرها</h2>' ), 'تیتر بلوک دیگر هم‌تراز بدنه (H2) نیست' );
sa_hl_assert( 4 === substr_count( $sa_hl_fn, '<h4>' ), 'هر چهار زیربخش یک پله زیر تیتر بلوک‌اند (H4)' );
sa_hl_assert( 0 === substr_count( $sa_hl_fn, '<h1' ) && 0 === substr_count( $sa_hl_fn, '<h2' ), 'بلوک نه H1 می‌سازد نه H2' );
sa_hl_assert( 0 === substr_count( $sa_hl_fn, '<h5' ), 'عمق تیتر از H4 پایین‌تر نمی‌رود' );

// ۲) شماره‌گذاری از همان شرط‌های رندر مشتق می‌شود.
sa_hl_assert( false !== strpos( $sa_hl_fn, '$sa_fn_sections' ), 'فهرست زیربخش‌های موجود ساخته می‌شود' );
sa_hl_assert( false !== strpos( $sa_hl_fn, "array( 'seasons', 'extra_places', 'local_tips', 'people_character' )" ), 'ترتیب شمارش با ترتیب رندر یکی است' );
foreach ( array( 'seasons', 'extra_places', 'local_tips', 'people_character' ) as $sa_hl_key ) {
	sa_hl_assert( false !== strpos( $sa_hl_fn, "\$sa_fn_num( '" . $sa_hl_key . "' )" ), "زیربخش {$sa_hl_key} شمارهٔ خودش را می‌گیرد" );
	sa_hl_assert( false !== strpos( $sa_hl_fn, "if ( ! empty( \$sa_ins['" . $sa_hl_key . "'] ) ) :" ), "شرط رندر {$sa_hl_key} دست‌نخورده است" );
}
sa_hl_assert( false === strpos( $sa_hl_fn, "\$sa_fn_sections[] = 'access';" ), 'کادرهای اطلاعات که تیتر ندارند شماره نمی‌گیرند' );
sa_hl_assert( false !== strpos( $sa_hl_fn, 'sa_digits' ), 'شماره با رقم فارسی چاپ می‌شود' );

// ۳) فهرست مطالب: یک پله پایین‌تر از بدنه.
sa_hl_assert( false !== strpos( $sa_hl_toc, 'function sa_toc_register( $label, $id, $weight = 100, $level = 1 )' ), 'ثبت سرفصل سطح می‌گیرد' );
sa_hl_assert( false !== strpos( $sa_hl_toc, "'level'  => max( 1, min( 3, (int) \$level ) )" ), 'سطح در دفترچهٔ فهرست نگه داشته می‌شود' );
sa_hl_assert( false !== strpos( $sa_hl_fn, "sa_toc_register( 'تجربهٔ مسافرها', 'county-field-notes', 6, 2 )" ), 'بلوک در فهرست یک پله زیر بدنه ثبت می‌شود' );

// ۴) رجیستری: ترتیب و شرط سکشن‌ها دست‌نخورده.
$sa_hl_keys = array();
if ( preg_match_all( "/^\\t\\t'([a-z-]+)'\\s+=> array\\(/m", $sa_hl_reg, $sa_hl_m ) ) {
	$sa_hl_keys = $sa_hl_m[1];
}
sa_hl_assert(
	array( 'sa-county-cover', 'sa-county-toc', 'sa-county-identity', 'sa-county-notes', 'sa-county-field', 'sa-county-body', 'sa-county-faq', 'sa-county-gallery', 'sa-county-rating', 'sa-county-sources', 'sa-county-contrib', 'sa-county-related', 'sa-county-nav' ) === $sa_hl_keys,
	'ترتیب سیزده سکشن رجیستری عوض نشده است'
);
sa_hl_assert( false !== strpos( $sa_hl_reg, "'render' => 'sa_county_field_notes_html'," ), 'سکشن تجربه همان رندرکنندهٔ قبلی را دارد' );

if ( $sa_hl_fail > 0 ) {
	echo "\n{$sa_hl_fail} assertion(s) failed\n";
	exit( 1 );
}
echo "heading levels (v2.22.148): all assertions passed\n";
