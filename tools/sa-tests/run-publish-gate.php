<?php
/**
 * Regression tests for the v1.2 publication gate and county JSON-LD merge.
 *
 * Run: node tools/phpwasm/exec.js tools/sa-tests/run-publish-gate.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

define( 'SA_ENABLE_ACCOMMODATION', false );
define( 'SA_CHILD_DIR', '/ws/theme/' );
$GLOBALS['sa_current_test_post_id'] = 0;

function is_singular( $type = '' ) {
	return '' === $type || 'city' === $type;
}
function get_the_ID() {
	return (int) $GLOBALS['sa_current_test_post_id'];
}

require '/ws/theme/inc/entities-config.php';

// Minimal helpers needed by the gate/schema merge; wp-stubs.php supplies the rest.
function sa_entity( $type ) {
	$entities = sa_entities_config();
	return isset( $entities[ $type ] ) ? $entities[ $type ] : null;
}
function sa_fa_digits( $value ) {
	return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}
function sa_is_entity( $post_or_type = null ) {
	$type = is_string( $post_or_type ) ? $post_or_type : get_post_type( $post_or_type );
	return $type && in_array( $type, array_values( (array) sa_entity_types() ), true );
}
function get_current_user_id() {
	return 1;
}
function sa_count_sources( $raw ) {
	$public = preg_split( '/^\s*-{3,}.*$/mu', (string) $raw, 2 );
	return preg_match_all( '#https?://#i', $public[0] );
}
function sa_count_markers( $content ) {
	$content = (string) $content;
	return substr_count( $content, 'نیازمند بررسی' ) + substr_count( $content, 'منبع لازم' );
}
function sa_get_faq( $post_id ) {
	$rows = json_decode( (string) get_post_meta( $post_id, 'sa_faq', true ), true );
	return is_array( $rows ) ? $rows : array();
}
function has_term( $term = '', $taxonomy = '', $post = null ) {
	return true;
}
function get_theme_mod( $name, $default = false ) {
	return $default;
}
function sa_test_return_false() {
	return false;
}
function sa_fa_digits_en( $value ) {
	return (string) $value;
}

require '/ws/theme/inc/publish-gate.php';
require '/ws/theme/inc/geo-counties.php';
register_taxonomy( 'attraction_type', array( 'attraction' ) );

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

function sa_attraction_form( $content ) {
	return array(
		'post_content'         => $content,
		'sa_city_id'           => 42,
		'sa_seo_title'         => 'جاذبهٔ نمونه | سرزمین آریان',
		'sa_seo_description'   => 'راهنمای کاربردی و بررسی‌شدهٔ جاذبهٔ نمونه.',
		'sa_focus_keyword'     => 'جاذبه نمونه',
		'sa_latitude'          => '35.7',
		'sa_longitude'         => '51.4',
		'tax_input'            => array( 'attraction_type' => array( 'nature' ) ),
	);
}

echo "== مدل تولیدشده و سیاست نسخهٔ ۱٫۲ ==\n";

sa_eq( 'فیلدهای لازم SEO محدود به ورودی‌های واقعی‌اند', array( 'seo_title', 'seo_description', 'focus_keyword' ), sa_seo_required_fields() );
sa_ok( 'canonical/OG در گروه override اختیاری‌اند', in_array( 'canonical_url', sa_seo_optional_overrides(), true ) && in_array( 'og_title', sa_seo_optional_overrides(), true ) );
sa_ok( 'faq_schema منسوخ است', isset( sa_seo_deprecated_fields()[0]['name'] ) && 'faq_schema' === sa_seo_deprecated_fields()[0]['name'] );
sa_ok( 'FAQPage در فهرست publish blocker نیست', ! in_array( 'missing_faq', sa_publish_blockers(), true ) );
sa_ok( 'بهداشت محتوا blocker مدل است', in_array( 'missing_content_hygiene', sa_publish_blockers(), true ) );

$checks = sa_gate_missing( 0, 'attraction', sa_attraction_form( '<p>معرفی دقیق مقصد.</p>' ) );
list( $blocking, $advisory ) = sa_gate_split( $checks );
sa_ok( 'FAQ خالی مانع انتشار نیست', ! in_array( 'missing_faq', $checks, true ) );
sa_eq( 'کمبود لینک فقط هشدار است', array(), $blocking );
sa_ok( 'هدف لینک در پیام، توصیهٔ تحریریه است', ! empty( $advisory ) && false !== strpos( implode( ' ', $advisory ), 'هدف تحریریه' ) );

$hygiene = sa_gate_content_hygiene_issues( '<p>مقالهٔ سالم با [نیازمند بررسی] شفاف و [منبع لازم] محدود.</p>' );
sa_eq( 'نشانگرهای شفافیت مجازند', array(), $hygiene );
sa_ok( 'H1 بدنه شناسایی می‌شود', ! empty( sa_gate_content_hygiene_issues( '<h1>تیتر تکراری</h1>' ) ) );
sa_ok( 'TODO حل‌نشده شناسایی می‌شود', ! empty( sa_gate_content_hygiene_issues( '<p>TODO</p>' ) ) );
sa_ok( 'یادداشت فارسی ویراستار شناسایی می‌شود', ! empty( sa_gate_content_hygiene_issues( '<p>یادداشت برای نویسنده</p>' ) ) );
sa_ok( 'جای‌نگهدار قالبی شناسایی می‌شود', ! empty( sa_gate_content_hygiene_issues( '<p>{{county_name}}</p>' ) ) );
sa_eq( 'H1 داخل کامنت یا script اجرا نمی‌شود', array(), sa_gate_content_hygiene_issues( '<!-- <h1>old</h1> --><script>TODO</script><p>متن سالم</p>' ) );

$anchor_issues = sa_gate_content_hygiene_issues(
	'<p><a href="/empty/"></a><a href="/text/">ادامهٔ راهنما</a>' .
	'<a href="/image/"><img src="map.png" alt="نقشهٔ مسیر"></a>' .
	'<a href="/label/" aria-label="صفحهٔ مقصد"></a></p>'
);
sa_eq( 'فقط لینک بی‌نام مشکل دارد', 1, count( $anchor_issues ) );
sa_ok( 'NBSP نام دسترس‌پذیر محسوب نمی‌شود', ! empty( sa_gate_content_hygiene_issues( '<a href="/nbsp/" aria-label="&nbsp;"></a>' ) ) );
sa_ok( 'نویسهٔ نامرئی نام دسترس‌پذیر محسوب نمی‌شود', ! empty( sa_gate_content_hygiene_issues( '<a href="/zero/" aria-label="​"></a>' ) ) );
$invisible_issues = sa_gate_content_hygiene_issues( '<a href="/nbsp/" aria-label="&nbsp;"></a><a href="/zero/" aria-label="&#8203;"></a>' );
sa_eq( 'تعداد anchorهای بی‌نام به‌درستی شمرده می‌شود', 'بهداشت محتوا: ۲ پیوند بدون نام دسترس‌پذیر', isset( $invisible_issues[0] ) ? $invisible_issues[0] : '' );
sa_ok( 'بهداشت محتوا در دروازهٔ واقعی blocker است', in_array( 'missing_content_hygiene', sa_publish_blockers(), true ) && ! empty( sa_gate_split( sa_gate_missing( 0, 'attraction', sa_attraction_form( '<h1>تیتر اضافه</h1>' ) ) )[0] ) );

$clean_links = sa_gate_content_hygiene_issues( '<a href="/next/">صفحهٔ بعد</a><a href="/map/"><img alt="نقشه"></a>' );
sa_eq( 'متن لینک و alt تصویر نام دسترس‌پذیر می‌سازند', array(), $clean_links );

echo "\n== ادغام دادهٔ شهرستان در یک گرهٔ مکانی ==\n";

$registry = sa_counties();
$county   = reset( $registry );
sa_ok( 'رجیستری شهرستان برای آزمون بارگذاری شد', is_array( $county ) && ! empty( $county['slug'] ) );
if ( is_array( $county ) && ! empty( $county['slug'] ) ) {
	$county_id = sa_add_post(
		array(
			'post_title'  => 'شهرستان ' . $county['name'],
			'post_type'   => 'city',
			'post_status' => 'publish',
			'post_name'   => $county['slug'],
			'meta'        => array(
				'sa_cty_neighbors'     => 'همسایه نمونه - شمال',
				'sa_cty_poi_nature'    => 'آبشار نمونه | ۱۲ کیلومتر | مسیر طبیعی',
			),
		)
	);
	sa_eq( 'نام شهرستان از عنوان prefixed به شکل پایه نرمال می‌شود', $county['name'], sa_county_name( $county_id ) );
	sa_eq( 'پیشوند شهرستان فقط یک‌بار افزوده می‌شود', 'شهرستان ' . $county['name'], sa_county_name( $county_id, true ) );
	$duplicate_title_id = sa_add_post( array( 'post_title' => 'شهرستان شهرستان ' . $county['name'], 'post_type' => 'city', 'post_status' => 'draft' ) );
	sa_eq( 'پیشوند تکراری legacy پاک می‌شود', 'شهرستان ' . $county['name'], sa_county_name( $duplicate_title_id, true ) );
	$GLOBALS['sa_current_test_post_id'] = $county_id;
	$county_url = get_permalink( $county_id );
	$graph      = array(
		array(
			'@type'           => array( 'AdministrativeArea', 'TouristDestination' ),
			'@id'             => $county_url . '#place',
			'name'            => get_the_title( $county_id ),
			'containedInPlace' => array( '@type' => 'AdministrativeArea', 'name' => 'استان نمونه' ),
		),
	);
	$merged = sa_county_schema_graph( $graph );
	sa_eq( 'پروفایل شهرستان گرهٔ تکراری تولید نمی‌کند', 1, count( $merged ) );
	sa_eq( 'شناسهٔ اصلی صفحه حفظ شد', $county_url . '#place', $merged[0]['@id'] );
	sa_ok( 'همسایه‌ها به گرهٔ اصلی افزوده شدند', ! empty( $merged[0]['borders'] ) );
	sa_ok( 'جاذبه‌های پروفایل به گرهٔ اصلی افزوده شدند', ! empty( $merged[0]['touristAttraction'] ) );
}

echo "\n== دروازهٔ انتشار: راهنما، حفاظت از صفحهٔ زنده و گزارشِ ماندگار (v2.11.42) ==\n";

if ( ! function_exists( 'sa_jalali_date' ) ) {
	/**
	 * شبیه‌سازِ تاریخ شمسی برای رندر جعبهٔ دروازه.
	 *
	 * @param string $format قالب.
	 * @param int    $time   زمان.
	 * @return string
	 */
	function sa_jalali_date( $format, $time = 0 ) {
		return '۱۴۰۵-۰۷-۱۷ — ۱۲:۰۰';
	}
}

sa_has( 'برای «تصویر شاخص» راهنما هست', 'کارت تصویر شاخص', sa_gate_hint( 'تصویر شاخص' ) );
sa_has( 'برای کمبودِ سئو راهنما هست', 'جعبهٔ «سئو»', sa_gate_hint( 'سئو: عنوان سئو' ) );
sa_has( 'برای رابطهٔ والد راهنما هست', 'روابط', sa_gate_hint( 'رابطه: استان انتخاب نشده است' ) );
sa_has( 'برای بهداشتِ محتوا راهنمای تعمیر هست', 'تعمیر محتوا', sa_gate_hint( 'بهداشت محتوا: یادداشت تحریریه' ) );
sa_eq( 'برای مانعِ ناشناس راهنما چاپ نمی‌شود', '', sa_gate_hint( 'یک مانعِ تازه' ) );

/**
 * ساختِ صفحهٔ شهرستانِ آزمایشی با کمبودِ آگاهانه (تصویر شاخص).
 *
 * @param string $status وضعیت.
 * @return int
 */
function sa_gate_seed_city( $status ) {
	return sa_add_post(
		array(
			'post_title'   => 'شهرستان نمونه',
			'post_type'    => 'city',
			'post_status'  => $status,
			'post_name'    => 'sample-county-' . $status,
			'post_content' => '<p>متنِ صفحه.</p>',
			'meta'         => array(
				'sa_province_id'     => 1,
				'sa_seo_title'       => 'شهرستان نمونه | سرزمین آریان',
				'sa_seo_description' => 'توضیحِ کوتاه دربارهٔ شهرستان نمونه.',
				'sa_focus_keyword'   => 'شهرستان نمونه',
				'sa_sources'         => "منبع یکم | https://example.org/a\nمنبع دوم | https://example.org/b\nمنبع سوم | https://example.org/c\nمنبع چهارم | https://example.org/d\nمنبع پنجم | https://example.org/e",
				'sa_city_latitude'   => '35.7',
				'sa_city_longitude'  => '51.4',
			),
		)
	);
}

// پیش‌نویس: همان قاعدهٔ سختِ پیشین — انتشار متوقف و نوشته پیش‌نویس می‌ماند.
$gate_draft = sa_gate_seed_city( 'draft' );
$data       = array(
	'post_status' => 'publish',
	'post_type'   => 'city',
	'post_content' => '<p>متنِ صفحه.</p>',
);
$_POST      = array();
$filtered   = sa_gate_filter( $data, array( 'ID' => $gate_draft ) );
sa_eq( 'پیش‌نویسِ ناقص همچنان پیش‌نویس می‌ماند', 'draft', $filtered['post_status'] );
$transient = get_transient( 'sa_gate_' . get_current_user_id() );
sa_eq( 'حالتِ گزارش برای پیش‌نویس hard است', 'hard', isset( $transient['mode'] ) ? $transient['mode'] : '' );

// صفحهٔ منتشرشده: هرگز پایین نمی‌آید؛ مانع‌ها یادآوری می‌شوند.
$gate_live  = sa_gate_seed_city( 'publish' );
$filtered2  = sa_gate_filter( $data, array( 'ID' => $gate_live ) );
sa_eq( 'صفحهٔ منتشرشده منتشر می‌ماند', 'publish', $filtered2['post_status'] );
$transient2 = get_transient( 'sa_gate_' . get_current_user_id() );
sa_eq( 'حالتِ گزارش برای صفحهٔ زنده protected است', 'protected', isset( $transient2['mode'] ) ? $transient2['mode'] : '' );
sa_eq( 'مانعِ صفحهٔ زنده به یادآوری تبدیل می‌شود', array(), isset( $transient2['missing'] ) ? $transient2['missing'] : array( 'x' ) );
sa_has( 'یادآوری شاملِ همان کمبود است', 'تصویر شاخص', implode( ' · ', isset( $transient2['warnings'] ) ? (array) $transient2['warnings'] : array() ) );

$report = get_post_meta( $gate_live, '_sa_gate_report', true );
sa_eq( 'گزارشِ ماندگار روی نوشته ذخیره می‌شود', true, is_array( $report ) && ! empty( $report['time'] ) );
sa_eq( 'گزارش حالت را نگه می‌دارد', 'protected', isset( $report['mode'] ) ? $report['mode'] : '' );
sa_has( 'گزارش یادآوری‌ها را فهرست می‌کند', 'تصویر شاخص', implode( ' · ', isset( $report['warnings'] ) ? (array) $report['warnings'] : array() ) );

// جعبهٔ پیشخوان: مانع‌ها + راهنمای رفع.
sa_gate_register_box();
$screens = array();
foreach ( (array) $GLOBALS['sa_meta_boxes'] as $box ) {
	$screens[] = $box['screen'];
}
sa_ok( 'جعبهٔ دروازه برای نوعِ شهرستان ثبت می‌شود', in_array( 'city', $screens, true ) );

$box_live = null;
foreach ( (array) $GLOBALS['sa_meta_boxes'] as $box ) {
	if ( 'sa_gate_report' === $box['id'] ) {
		$box_live = $box['title'];
	}
}
sa_has( 'عنوانِ جعبه «چه چیزی مانع است؟» را می‌پرسد', 'چه چیزی مانع است؟', (string) $box_live );

ob_start();
sa_gate_render_report_box( get_post( $gate_live ) );
$box_html = (string) ob_get_clean();
sa_has( 'جعبه مانعِ ثبت‌شده را نشان می‌دهد', 'مانع انتشار', $box_html );
sa_has( 'جعبه راهنمای رفع می‌دهد', 'کارت تصویر شاخص', $box_html );
sa_has( 'جعبه توضیحِ محافظت را می‌دهد', 'به پیش‌نویس برنمی‌گرداند', $box_html );

// با خاموش‌کردنِ حفاظت (فیلتر)، قاعدهٔ پیشین برمی‌گردد.
add_filter( 'sa_gate_protect_published', 'sa_test_return_false' );
$filtered3 = sa_gate_filter( $data, array( 'ID' => $gate_live ) );
sa_eq( 'با فیلترِ خاموش، صفحه به پیش‌نویس برمی‌گردد (رفتارِ پیشین)', 'draft', $filtered3['post_status'] );

$clean_id = sa_add_post(
	array(
		'post_title'   => 'شهرستان سالم',
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_name'    => 'clean-county',
		'post_content' => '<p>متنِ صفحه.</p>',
		'meta'         => array(
			'sa_province_id'     => 1,
			'sa_seo_title'       => 'شهرستان سالم | سرزمین آریان',
			'sa_seo_description' => 'توضیحِ کوتاه دربارهٔ شهرستان سالم.',
			'sa_focus_keyword'   => 'شهرستان سالم',
			'sa_sources'         => "منبع یکم | https://example.org/a\nمنبع دوم | https://example.org/b\nمنبع سوم | https://example.org/c\nمنبع چهارم | https://example.org/d\nمنبع پنجم | https://example.org/e",
			'sa_city_latitude'   => '35.7',
			'sa_city_longitude'  => '51.4',
			'_thumb_id'          => 99,
		),
	)
);
ob_start();
sa_gate_render_report_box( get_post( $clean_id ) );
$clean_html = (string) ob_get_clean();
sa_has( 'صفحهٔ سالم پیامِ آماده بودن می‌گیرد', 'مانعی برای انتشار نیست', $clean_html );
sa_has( 'ارزیابیِ زنده در نبود گزارش اعلام می‌شود', 'ارزیابیِ زنده', $clean_html );

// نشانِ فهرستِ پیشخوان: وضعیتِ واقعی نوشته، نه فقط مانع‌ها.
sa_has( 'نشانِ صفحهٔ منتشرشده وضعیتِ انتشار را می‌گوید', 'منتشرشده · ۱ مورد برای تکمیل', sa_gate_badge( $gate_live ) );
sa_has( 'نشانِ صفحهٔ منتشرشدهٔ کامل «منتشرشده ✓» است', 'منتشرشده ✓', sa_gate_badge( $clean_id ) );
sa_has( 'نشانِ پیش‌نویسِ ناقص همچنان «مانع انتشار» است', 'مانع انتشار', sa_gate_badge( $gate_draft ) );
sa_has( 'راهنمای همان مانع در tooltipِ نشان هست', 'تصویر شاخص', sa_gate_badge( $gate_draft ) );

sa_done();
