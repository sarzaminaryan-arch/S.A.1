<?php
/**
 * Rule tests for the render-time internal-link engine (`inc/internal-links.php`).
 *
 * The rules under test (owner request, 2026-10-07):
 *  ۱. متنِ لینکِ تولیدشده همیشه نامِ کاملِ موجودیت است: «شهرستان نطنز» / «شهر کرج» / «استان گیلان».
 *  ۲. واژه‌های نامبهم («بافت»، «انار»، «مهر»…) بدون قرینه لینک نمی‌شوند؛
 *     «بافت شهری فلان شهرستان» به شهرستان بافت وصل نمی‌شود.
 *  ۳. نامی که به پدیدهٔ دیگری چسبیده است («رود شاهرود»، «تالاب رامسر»)، یا تقسیمِ زیرِ شهرستان
 *     («بخش نطنز»، «دهستان نطنز») لینک نمی‌شود.
 *  ۴. نامِ خودِ صفحه در همان صفحه لینک نمی‌شود و به کاندیدِ هم‌نامِ استانِ دیگر نمی‌رود؛
 *     شهرستانِ هم‌استان فقط با قرینهٔ صریح لینک می‌شود.
 *  ۵. هر مقصد در هر صفحه یک‌بار؛ سقفِ ۳۰ لینکِ تولیدشده در صفحه.
 *
 * Run: node tools/phpwasm/exec.js tools/sa-tests/run-autolink-rules.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

define( 'SA_CHILD_DIR', '/ws/theme/' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/ws/' );
}

// رجیستری استان‌ها: شبیه‌سازِ `sa_provinces_data()` (در قالب از inc/taxonomies.php می‌آید).
$sa_provinces = (array) require SA_CHILD_DIR . 'data/provinces.php';
if ( ! function_exists( 'sa_provinces_data' ) ) {
	function sa_provinces_data() {
		return $GLOBALS['sa_provinces'];
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}
if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() { return false; }
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $t = '' ) { return true; }
}
if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $n, $d = false ) { return $d; }
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $t, $x = '' ) { return ''; }
}
if ( ! function_exists( 'sa_remember_cache_key' ) ) {
	function sa_remember_cache_key( $k ) { return true; }
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() { return isset( $GLOBALS['sa_cur'] ) ? (int) $GLOBALS['sa_cur'] : 0; }
}

require '/ws/theme/inc/geo-counties.php';
require '/ws/theme/inc/internal-links.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/* ------------------------------------------------------------------ رُستِر */

$roster = array(
	array( 'province', 'alborz', 'استان البرز' ),
	array( 'province', 'kerman', 'استان کرمان' ),
	array( 'province', 'isfahan', 'استان اصفهان' ),
	array( 'city', 'natanz', 'شهرستان نطنز' ),
	array( 'city', 'baft', 'شهرستان بافت' ),
	array( 'city', 'alborz-qazvin', 'شهرستان البرز' ),
	array( 'city', 'karaj', 'شهر کرج' ),
	array( 'city', 'kerman', 'شهرستان کرمان' ),
	array( 'city', 'anar', 'شهرستان انار' ),
	array( 'city', 'bam', 'شهرستان بم' ),
	array( 'city', 'pardis', 'شهرستان پردیس' ),
);

/* شهرستان‌های اضافی (۴۰ رکوردِ غیرمبهم از رجیستری) تا سقفِ ۳۰ لینک آزمون‌پذیر شود. */
$ambiguous = sa_autolink_ambiguous_names();
foreach ( (array) sa_counties() as $county ) {
	if ( count( $roster ) >= 50 ) {
		break;
	}
	$slug = isset( $county['slug'] ) ? (string) $county['slug'] : '';
	$name = isset( $county['name'] ) ? (string) $county['name'] : '';
	if ( '' === $slug || '' === $name || in_array( $name, $ambiguous, true ) || mb_strlen( $name, 'UTF-8' ) <= 3 ) {
		continue;
	}
	$dup = false;
	foreach ( $roster as $existing ) {
		if ( $existing[1] === $slug ) {
			$dup = true;
			break;
		}
	}
	if ( ! $dup ) {
		$roster[] = array( 'city', $slug, 'شهرستان ' . $name );
	}
}

$ids  = array();
$rows = array();
foreach ( $roster as $row ) {
	list( $type, $slug, $title ) = $row;
	$id          = sa_add_post(
		array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
		)
	);
	$ids[ $type . ':' . $slug ] = $id;
	$rows[]                        = (object) array(
		'ID'         => $id,
		'post_type'  => $type,
		'post_name'  => $slug,
		'post_title' => $title,
	);
}
$GLOBALS['wpdb']->set_results( $rows );

/**
 * رندرِ محتوا در بسترِ یک صفحهٔ مشخص.
 *
 * @param string $key     کلیدِ رُستِر (type:slug).
 * @param string $content محتوا.
 * @return string
 */
function sa_render( $key, $content ) {
	if ( ! isset( $GLOBALS['sa_ids'][ $key ] ) ) {
		throw new RuntimeException( 'unknown subject: ' . $key );
	}
	$GLOBALS['sa_cur'] = (int) $GLOBALS['sa_ids'][ $key ];

	return sa_autolink_content( $content );
}

/**
 * شمارشِ لینک‌های یک مقصد در خروجی.
 *
 * @param string $html خروجی.
 * @param string $path مسیر (مثلاً /city/natanz/).
 * @return int
 */
function sa_count_links( $html, $path ) {
	// هم نشانیِ مطلقِ موتور و هم نشانیِ نسبیِ متنِ ذخیره‌شده شمرده می‌شود.
	return (int) preg_match_all( '~href="(?:https?://[^"/]+)?' . preg_quote( $path, '~' ) . '"~u', (string) $html );
}

$GLOBALS['sa_ids'] = $ids;

/* ------------------------------------------------------------------- ادعاها */

echo "\n== ۱. متنِ لینک = نامِ کاملِ موجودیت ==\n";
$out = sa_render( 'province:kerman', '<p>نطنز شهری در استان اصفهان است.</p>' );
sa_eq( 'نامِ بدونِ قرینه با متنِ کامل لینک می‌شود', 1, sa_count_links( $out, '/city/natanz/' ) );
sa_has( 'متنِ لینک «شهرستان نطنز» است', '>شهرستان نطنز</a>', $out );
sa_lacks( 'متنِ لینک واژهٔ برهنه نیست', '>نطنز</a>', $out );

$out = sa_render( 'province:kerman', '<p>شهرستان نطنز در فهرست نیست.</p>' );
sa_has( 'قرینهٔ «شهرستان» دوباره اضافه نمی‌شود', '>شهرستان نطنز</a>', $out );
sa_lacks( 'پیشوندِ تکراری ساخته نمی‌شود', 'شهرستان شهرستان', $out );

$out = sa_render( 'province:kerman', '<p>شهر کرج مرکز استان البرز است.</p>' );
sa_has( '«شهر کرج» دست‌نخورده لینک می‌شود', '>شهر کرج</a>', $out );
sa_lacks( '«شهرستان شهر کرج» ساخته نمی‌شود', 'شهرستان شهر کرج', $out );

/* ------------------------------------------------------- واژه‌های نامبهم */

echo "\n== ۲. واژه‌های نامبهم بدون قرینه ==\n";
$out = sa_render( 'province:kerman', '<p>بافت شهری فلان شهرستان، بافت تاریخی آن و بافت کهن بازار.</p>' );
sa_eq( '«بافت» در «بافت شهری/تاریخی/کهن» لینک نمی‌شود', 0, sa_count_links( $out, '/city/baft/' ) );

$out = sa_render( 'province:kerman', '<p>انار و بم و مهر واژه‌های رایج‌اند.</p>' );
sa_eq( '«انار» بدون قرینه لینک نمی‌شود', 0, sa_count_links( $out, '/city/anar/' ) );
sa_eq( '«بم» بدون قرینه لینک نمی‌شود', 0, sa_count_links( $out, '/city/bam/' ) );

$out = sa_render( 'province:kerman', '<p>شهرستان بافت و شهرستان انار در استان کرمان‌اند.</p>' );
sa_eq( '«شهرستان بافت» با قرینه لینک می‌شود', 1, sa_count_links( $out, '/city/baft/' ) );
sa_eq( '«شهرستان انار» با قرینه لینک می‌شود', 1, sa_count_links( $out, '/city/anar/' ) );

/* -------------------------------------------- پدیده‌ها و تقسیماتِ زیرین */

echo "\n== ۳. پدیده‌ها و تقسیماتِ زیرِ شهرستان ==\n";
$out = sa_render( 'province:kerman', '<p>رود شاهرود و تالاب رامسر و رشته‌کوه البرز و حوضهٔ کرج.</p>' );
sa_eq( 'نامِ چسبیده به پدیده لینک نمی‌شود', 0, sa_count_links( $out, '/city/karaj/' ) );
$out = sa_render( 'province:kerman', '<p>بخش نطنز و دهستان نطنز زیرمجموعه‌اند.</p>' );
sa_eq( '«بخش/دهستان X» لینک نمی‌شود', 0, sa_count_links( $out, '/city/natanz/' ) );
$out = sa_render( 'province:kerman', '<p>کلان‌شهر کرمان بزرگ است.</p>' );
sa_eq( '«کلان‌شهر کرمان» پیوند نمی‌خورد', 0, sa_count_links( $out, '/city/kerman/' ) );

// «فرخ‌شهر پردیس دانشگاهی دارد»: «شهر»ِ چسبیده نباید قرینهٔ «پردیس» شود.
$out = sa_render( 'province:kerman', '<p>شهر جدید فرخ‌شهر پردیس دانشگاهی دارد.</p>' );
sa_eq( 'واژهٔ چسبیده به «شهر» قرینه حساب نمی‌شود', 0, sa_count_links( $out, '/city/pardis/' ) );

/* ------------------------------------------------ نامِ خودِ صفحهٔ جاری */

echo "\n== ۴. نامِ خودِ صفحه ==\n";
$out = sa_render( 'city:baft', '<p>شهرستان بافت در استان کرمان است و بافت شهری آن کهن است.</p>' );
sa_eq( 'نامِ خودِ صفحه لینک نمی‌شود', 0, sa_count_links( $out, '/city/baft/' ) );
sa_eq( 'استانِ همان صفحه لینک می‌شود', 1, sa_count_links( $out, '/province/kerman/' ) );

$out = sa_render( 'province:alborz', '<p>البرز نامی آشناست و شهرستان البرز در قزوین است.</p>' );
sa_eq( 'نامِ خودِ استان به شهرستانِ هم‌نامِ استانِ دیگر نمی‌رود', 0, sa_count_links( $out, '/city/alborz-qazvin/' ) );

$out = sa_render( 'province:kerman', '<p>شهرستان کرمان یکی از شهرستان‌های استان کرمان است.</p>' );
sa_eq( 'شهرستانِ هم‌استان با قرینه لینک می‌شود', 1, sa_count_links( $out, '/city/kerman/' ) );

$out = sa_render( 'city:kerman', '<p>کرمان مرکز استان است و بازار آن دیدنی است.</p>' );
sa_eq( 'نامِ خودِ شهرستان در صفحهٔ خودش لینک نمی‌شود', 0, sa_count_links( $out, '/city/kerman/' ) );

/* ------------------------------------------------ یک‌بار برای هر مقصد */

echo "\n== ۵. یک مقصد در هر صفحه، سقفِ ۳۰ لینک ==\n";
$out = sa_render( 'province:kerman', '<p>نطنز و نطنز و نطنز و نطنز دیدنی است.</p>' );
sa_eq( 'هر مقصد فقط یک‌بار لینک می‌شود', 1, sa_count_links( $out, '/city/natanz/' ) );

$out = sa_render( 'province:kerman', '<p>کرج و کرج و کرج (نامِ سه‌حرفی بدون قرینه).</p>' );
sa_eq( 'نامِ سه‌حرفی بدون قرینه اصلاً لینک نمی‌شود', 0, sa_count_links( $out, '/city/karaj/' ) );

$out = sa_render( 'province:kerman', '<p><a href="/city/natanz/">نطنز</a> و سپس نطنز بار دیگر.</p>' );
sa_eq( 'مقصدِ از پیش لینک‌شده دوباره لینک نمی‌شود', 1, sa_count_links( $out, '/city/natanz/' ) );

/* ۳۴ نامِ غیرمبهم و چهارحرفی‌به‌بالا: باید فقط ۳۰ لینک ساخته شود (سقفِ موتور). */
$names = array();
foreach ( (array) sa_counties() as $county ) {
	$slug = isset( $county['slug'] ) ? (string) $county['slug'] : '';
	$name = isset( $county['name'] ) ? (string) $county['name'] : '';
	if ( '' === $name || ! isset( $ids[ 'city:' . $slug ] ) || in_array( $name, $ambiguous, true ) ) {
		continue;
	}
	if ( mb_strlen( $name, 'UTF-8' ) <= 3 || 'kerman' === $county['province'] ) {
		continue;
	}
	$names[] = $name;
	if ( count( $names ) >= 34 ) {
		break;
	}
}
$out       = sa_render( 'province:kerman', '<p>' . implode( ' و ', $names ) . ' دیدنی‌اند.</p>' );
$generated = (int) preg_match_all( '~class="sa-autolink~u', (string) $out );
sa_eq( 'سقفِ ۳۰ لینکِ تولیدشده در صفحه رعایت می‌شود', 30, $generated );

$out = sa_render( 'province:kerman', '<h2>نطنز</h2><p>توضیح.</p>' );
sa_eq( 'تیترها لینک نمی‌شوند', 0, sa_count_links( $out, '/city/natanz/' ) );

sa_done();
