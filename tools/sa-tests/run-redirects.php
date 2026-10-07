<?php
/**
 * تستِ ماژولِ تغییر مسیرِ ۳۰۱ صفحه‌های تکراری (`inc/redirects.php`) و اثرش بر موتورِ لینک‌سازی.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-redirects.php
 *
 * @package Sarzaminaryan_Child
 */

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/assert.php';

define( 'SA_CHILD_DIR', '/ws/theme/' );

/* ------------------------------------------------- شبیه‌سازهای لازمِ محیط */

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}
if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return false;
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		return true;
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return isset( $GLOBALS['sa_autolink_current_id'] ) ? (int) $GLOBALS['sa_autolink_current_id'] : 0;
	}
}
if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $name, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term, $taxonomy = '' ) {
		return 'https://sarzaminaryan.test/province_tax/term-' . (int) $term . '/';
	}
}
if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $text, $encoding = 'UTF-8' ) { // phpcs:ignore
		return count( preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}
}
if ( ! function_exists( 'sa_remember_cache_key' ) ) {
	function sa_remember_cache_key( $key ) {
		return true;
	}
}
if ( ! function_exists( 'is_preview' ) ) {
	function is_preview() {
		return false;
	}
}
if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * شبیه‌سازِ کمینهٔ get_page_by_path روی نوشته‌های ثبت‌شده در stub.
	 *
	 * @param string $path      نامک.
	 * @param string $output    نوع خروجی.
	 * @param string $post_type نوعِ نوشته.
	 * @return object|null
	 */
	function get_page_by_path( $path, $output = 'OBJECT', $post_type = 'post' ) {
		$path = trim( (string) $path, '/' );
		foreach ( get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'posts_per_page' => -1 ) ) as $post ) {
			if ( (string) $post->post_name === $path ) {
				return $post;
			}
		}
		return null;
	}
}

/**
 * کوئریِ جعلی برای آزمونِ بیرون‌گذاشتن از آرشیو.
 */
class SA_Redirect_Test_Query extends WP_Query {

	public $vars    = array();
	public $main    = true;
	public $archive = 'city';

	public function __construct( $main = true, $archive = 'city' ) {
		$this->main    = (bool) $main;
		$this->archive = (string) $archive;
	}

	public function is_main_query() {
		return $this->main;
	}

	public function is_post_type_archive( $type = '' ) {
		return $this->archive === (string) $type;
	}

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	public function get( $key, $default = '' ) {
		return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : $default;
	}
}

/* ------------------------------------------------- دادهٔ رجیستری (مثل اجراکنندهٔ موتور) */

$sa_counties  = require SA_CHILD_DIR . 'data/counties.php';
$sa_provinces = require SA_CHILD_DIR . 'data/provinces.php';

if ( ! function_exists( 'sa_counties' ) ) {
	function sa_counties() {
		global $sa_counties;
		return $sa_counties;
	}
}
if ( ! function_exists( 'sa_provinces_data' ) ) {
	function sa_provinces_data() {
		global $sa_provinces;
		return $sa_provinces;
	}
}

// عمداً فقط موتورِ لینک‌سازی بار می‌شود: ماژولِ تغییر مسیر باید خودش بار شود
// (اجراکننده‌های داده‌محور مثل run-autolink-audit.php هم فقط همین فایل را می‌خوانند).
require SA_CHILD_DIR . 'inc/internal-links.php';

sa_eq( 'ماژولِ تغییر مسیر خودش بار می‌شود', true, function_exists( 'sa_redirect_map' ) );
sa_eq( 'فایلِ دادهٔ نقشه پیدا می‌شود', 'data/redirects.php', basename( dirname( sa_redirect_data_file() ) ) . '/' . basename( sa_redirect_data_file() ) );

/* ------------------------------------------------------------------ §۱. نقشه */

$map = sa_redirect_map();
sa_eq( 'نقشهٔ تغییر مسیر خالی نیست', true, count( $map ) >= 1 );
sa_eq( 'مبدأ «/city/ijrud/» ثبت شده است', '/city/ejrud/', isset( $map['/city/ijrud/'] ) ? $map['/city/ijrud/'] : '' );
sa_eq( 'هیچ مبدأی مقصدِ مبدأِ دیگری نیست', array(), array_values( array_intersect( array_keys( $map ), array_values( $map ) ) ) );

$bad_shape = array();
foreach ( $map as $from => $to ) {
	foreach ( array( $from, $to ) as $path ) {
		if ( ! preg_match( '~^/(city|province|attraction)/[a-z0-9\p{L}\p{N}\p{M}_-]+/$~uD', $path ) ) {
			$bad_shape[] = $path;
		}
	}
}
sa_eq( 'همهٔ مسیرهای مبدأ/مقصد شکلِ استاندارد دارند', array(), $bad_shape );

$flipped = array_flip( $map );
$registry_slugs = array();
foreach ( $sa_counties as $row ) {
	$registry_slugs[ $row['slug'] ] = true;
}
$unknown_targets = array();
foreach ( $flipped as $to => $from ) {
	$parts = explode( '/', trim( (string) $to, '/' ) );
	if ( 'city' === $parts[0] && ! isset( $registry_slugs[ $parts[1] ] ) ) {
		$unknown_targets[] = $to;
	}
}
sa_eq( 'هر مقصدِ /city/ یک ردیفِ رجیستری دارد', array(), $unknown_targets );
sa_eq( '«ijrud» در رجیستری نیست (به مقصدِ ادغام‌شده تغییر مسیر می‌دهد)', false, isset( $registry_slugs['ijrud'] ) );
sa_eq( '«ejrud» ردیفِ رجیستری دارد', true, isset( $registry_slugs['ejrud'] ) );

/* ------------------------------------------------- §۲. تشخیص و نرمال‌سازیِ مسیر */

sa_eq( 'مسیر با کوئری نرمال می‌شود', '/city/ijrud/', sa_redirect_normalize_path( 'https://sarzaminaryan.ir/city/ijrud/?utm=1' ) );
sa_eq( 'مسیر بدون اسلشِ ابتدا/انتها نرمال می‌شود', '/city/ijrud/', sa_redirect_normalize_path( 'city/ijrud' ) );
sa_eq( 'مقصدِ مسیرِ قدیمی پیدا می‌شود', '/city/ejrud/', sa_redirect_target_for_path( '/city/ijrud/' ) );
sa_eq( 'مسیرِ مقصد خودش تغییر مسیر نمی‌دهد', '', sa_redirect_target_for_path( '/city/ejrud/' ) );
sa_eq( 'مسیرهای نامرتبط تغییری نمی‌کنند', '', sa_redirect_target_for_path( '/city/natanz/' ) );
sa_eq( 'مسیر نامکِ قدیمی شناسایی می‌شود', true, sa_redirect_is_old_slug( 'ijrud' ) );
sa_eq( 'نامکِ مقصد نامکِ قدیمی نیست', false, sa_redirect_is_old_slug( 'ejrud' ) );
sa_eq( 'نامکِ قدیمی برای نوعِ دیگر بازشناسی نمی‌شود', false, sa_redirect_is_old_slug( 'ijrud', 'post' ) );

/* ------------------------------------------------- §۳. مسیرِ درخواست */

$_SERVER['REQUEST_URI'] = '/city/ijrud/?page=2';
sa_eq( 'مسیرِ درخواست از REQUEST_URI ساخته می‌شود', '/city/ijrud/', sa_redirect_request_path() );
$_SERVER['REQUEST_URI'] = '/city/ejrud/';
sa_eq( 'مسیرِ سالم دست‌نخورده می‌ماند', '/city/ejrud/', sa_redirect_request_path() );
unset( $_SERVER['REQUEST_URI'] );
sa_eq( 'بدونِ REQUEST_URI مسیر خالی است', '', sa_redirect_request_path() );

/* ------------------------------------------------- §۴. شناسه‌ها و فهرست‌ها */

$old_id = sa_add_post(
	array(
		'post_type'   => 'city',
		'post_status' => 'publish',
		'post_name'   => 'ijrud',
		'post_title'  => 'شهرستان ایجرود',
	)
);
$new_id = sa_add_post(
	array(
		'post_type'   => 'city',
		'post_status' => 'publish',
		'post_name'   => 'ejrud',
		'post_title'  => 'شهرستان ایجرود',
	)
);
sa_eq( 'شناسهٔ صفحهٔ قدیمی پیدا می‌شود', array( $old_id ), sa_redirect_source_ids( 'city' ) );
sa_eq( 'صفحهٔ مقصد در فهرستِ بیرون‌گذاشته‌شده نیست', false, in_array( $new_id, sa_redirect_source_ids( 'city' ), true ) );

$query = new SA_Redirect_Test_Query( true, 'city' );
sa_redirect_archive_exclusion( $query );
sa_eq( 'آرشیوِ شهرستان صفحهٔ قدیمی را بیرون می‌گذارد', array( $old_id ), $query->get( 'post__not_in', array() ) );

$query_other = new SA_Redirect_Test_Query( true, 'province' );
sa_redirect_archive_exclusion( $query_other );
sa_eq( 'آرشیوِ استان‌ها دست‌نخورده می‌ماند', array(), $query_other->get( 'post__not_in', array() ) );

$query_secondary = new SA_Redirect_Test_Query( false, 'city' );
sa_redirect_archive_exclusion( $query_secondary );
sa_eq( 'کوئریِ فرعی دست‌نخورده می‌ماند', array(), $query_secondary->get( 'post__not_in', array() ) );

$sitemap = sa_redirect_sitemap_exclusion( array(), 'city' );
sa_eq( 'نقشهٔ سایتِ شهرستان صفحهٔ قدیمی را بیرون می‌گذارد', array( $old_id ), isset( $sitemap['post__not_in'] ) ? $sitemap['post__not_in'] : array() );
sa_eq( 'نقشهٔ سایتِ نوعِ دیگر دست‌نخورده می‌ماند', array(), sa_redirect_sitemap_exclusion( array(), 'post' ) );

/* ------------------------------------------------- §۵. موتورِ لینک‌سازی */

$roster = array(
	(object) array( 'ID' => $new_id, 'post_type' => 'city', 'post_name' => 'ejrud', 'post_title' => 'شهرستان ایجرود' ),
	(object) array( 'ID' => $old_id, 'post_type' => 'city', 'post_name' => 'ijrud', 'post_title' => 'شهرستان ایجرود' ),
);
$GLOBALS['wpdb']->set_results( $roster );

$targets = sa_autolink_build_targets();
$needles = array();
foreach ( array_keys( $targets['index'] ) as $needle ) {
	foreach ( $targets['index'][ $needle ] as $candidate ) {
		if ( (int) $candidate['id'] === (int) $old_id ) {
			$needles[] = 'page-old';
		}
		if ( (int) $candidate['id'] === (int) $new_id ) {
			$needles[] = $needle;
		}
	}
}
sa_eq( 'صفحهٔ قدیمی در واژه‌نامهٔ لینک نیست', false, in_array( 'page-old', $needles, true ) );
sa_eq( 'صفحهٔ مقصد در واژه‌نامه هست', true, in_array( 'ایجرود', $needles, true ) );

// پایان‌به‌پایان: رندرِ متن با هر دو صفحه در دیتابیس باید به مقصد لینک بزند، نه به صفحهٔ قدیمی.
$third_id  = sa_add_post(
	array(
		'post_type'   => 'city',
		'post_status' => 'publish',
		'post_name'   => 'khorramdarreh',
		'post_title'  => 'شهرستان خرمدره',
	)
);
$roster[] = (object) array( 'ID' => $third_id, 'post_type' => 'city', 'post_name' => 'khorramdarreh', 'post_title' => 'شهرستان خرمدره' );
$GLOBALS['wpdb']->set_results( $roster );

$GLOBALS['sa_autolink_current_id'] = (int) $third_id;
$html = sa_autolink_content( 'برای دیدنِ فهرستِ روستاها به شهرستان ایجرود در استان زنجان بروید.' );
sa_eq( 'رندر: یک لینک به مقصد ساخته می‌شود', 1, substr_count( $html, 'href="https://sarzaminaryan.test/city/ejrud/"' ) );
sa_eq( 'رندر: هیچ لینکی به صفحهٔ ادغام‌شده نیست', 0, substr_count( $html, '/city/ijrud/' ) );
sa_eq( 'رندر: متنِ لینک نامِ کامل است', 1, substr_count( $html, '>شهرستان ایجرود</a>' ) );

/* ------------------------------------------------- §۷. جاذبه‌های لینک‌شدهٔ بی‌صفحه */

// نامک‌های قدیمیِ فارسی در نقشه، مقصدشان باید همان مقالهٔ تازه (نامکِ لاتین) باشد.
sa_eq( '«دریاچهٔ گهرِ دورود» به مقالهٔ تازه می‌رسد', '/attraction/gahar-lake-dorud/', isset( $map['/attraction/دریاچه-ی-گهر/'] ) ? $map['/attraction/دریاچه-ی-گهر/'] : '' );
sa_eq( '«آبشار بیشه» به مقالهٔ تازه می‌رسد', '/attraction/bisheh-waterfall-dorud/', isset( $map['/attraction/آبشار-بیشه/'] ) ? $map['/attraction/آبشار-بیشه/'] : '' );
sa_eq( '«آبشار شوی تله‌زنگ» به مقالهٔ تازه می‌رسد', '/attraction/shevi-waterfall-tele-zang/', isset( $map['/attraction/آبشار-شوی-تله-زنگ/'] ) ? $map['/attraction/آبشار-شوی-تله-زنگ/'] : '' );
sa_eq( '«درهٔ ستاره‌های قشم» به مقالهٔ تازه می‌رسد', '/attraction/qeshm-stars-valley-geopark/', isset( $map['/attraction/stars-valley-qeshm/'] ) ? $map['/attraction/stars-valley-qeshm/'] : '' );
sa_eq( 'هیچ مبدأی مقصدِ مبدأِ دیگری نیست (پس از افزودنِ جاذبه‌ها)', array(), array_values( array_intersect( array_keys( $map ), array_values( $map ) ) ) );

// نگهبان: ۴۰۴ به ۴۰۴ وصل نمی‌شود. مقصد باید وجود داشته و منتشرشده باشد.
sa_add_post(
	array(
		'post_type'   => 'attraction',
		'post_status' => 'publish',
		'post_name'   => 'gahar-lake-dorud',
		'post_title'  => 'دریاچهٔ گهر',
	)
);
sa_add_post(
	array(
		'post_type'   => 'attraction',
		'post_status' => 'draft',
		'post_name'   => 'bisheh-waterfall-dorud',
		'post_title'  => 'آبشار بیشه',
	)
);
sa_eq( 'مقصدِ منتشرشده معتبر است', true, sa_redirect_target_exists( '/attraction/gahar-lake-dorud/' ) );
sa_eq( 'مقصدِ منتشرنشده معتبر نیست', false, sa_redirect_target_exists( '/attraction/bisheh-waterfall-dorud/' ) );
sa_eq( 'مقصدِ ناموجود معتبر نیست', false, sa_redirect_target_exists( '/attraction/هیچ-مقصدی-نیست/' ) );
sa_eq( 'مقصدِ شهرستانِ موجود معتبر است', true, sa_redirect_target_exists( '/city/ejrud/' ) );
sa_eq( 'نامکِ غیرِ استاندارد دست‌نخورده پذیرفته می‌شود', true, sa_redirect_target_exists( '/something/else/' ) );

// پایان‌به‌پایان: با مقصدِ ناموجود نباید تغییر مسیری رخ دهد.
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI']    = '/attraction/آبشار-بیشه/';
$GLOBALS['sa_redirects']   = array();
sa_redirect_handle_request();
sa_eq( 'بدونِ مقالهٔ مقصد، تغییر مسیر انجام نمی‌شود', array(), $GLOBALS['sa_redirects'] );

// با مقالهٔ منتشرشده، همان مسیر باید به مقصدِ تازه اشاره کند. (فراخوانیِ خودِ هندلر این‌جا
// ممکن نیست: پس از wp_safe_redirect()، وردپرس exit می‌کند و اجرای آزمون را قطع می‌کند.)
sa_eq( 'مسیر قدیمی به مقالهٔ تازه اشاره می‌کند', '/attraction/gahar-lake-dorud/', sa_redirect_target_for_path( '/attraction/دریاچه-ی-گهر/' ) );
sa_eq( 'نگهبان، مقصدِ منتشرشده را تأیید می‌کند', true, sa_redirect_target_exists( sa_redirect_target_for_path( '/attraction/دریاچه-ی-گهر/' ) ) );
sa_eq( 'مسیر قدیمیِ آبشار بیشه به مقصدِ پیش‌نویس می‌رسد ولی نگهبان ردش می‌کند', false, sa_redirect_target_exists( sa_redirect_target_for_path( '/attraction/آبشار-بیشه/' ) ) );
unset( $_SERVER['REQUEST_METHOD'] );
unset( $_SERVER['REQUEST_URI'] );

/* ------------------------------------------------- §۶. بارگذاریِ مستقلِ موتور */

// اگر فقط `inc/internal-links.php` بار شود (مثل اجراکننده‌های آزمون)، باید خودش
// ماژولِ تغییر مسیر را بار کند؛ وگرنه واژه‌نامه دوباره به صفحهٔ قدیمی لینک می‌سازد.
sa_eq( 'تابعِ تشخیصِ نامکِ قدیمی در دسترس است', true, function_exists( 'sa_redirect_is_old_slug' ) );

sa_done();
