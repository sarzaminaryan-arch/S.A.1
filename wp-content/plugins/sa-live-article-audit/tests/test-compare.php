<?php
/**
 * تست مستقل موتور تطبیق و نگاشت خط مبنا (بدون وردپرس):
 * php wp-content/plugins/sa-live-article-audit/tests/test-compare.php
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_LAA_DIR' ) ) {
	define( 'SA_LAA_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SA_LAA_VERSION' ) ) {
	define( 'SA_LAA_VERSION', 'test' );
}

require_once dirname( __DIR__ ) . '/includes/class-sa-laa-compare.php';
require_once dirname( __DIR__ ) . '/includes/class-sa-laa-baseline.php';

function sa_laa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		echo "FAIL: {$message}\n";
		exit( 1 );
	}
	echo "ok - {$message}\n";
}

function sa_laa_t_base_record() {
	return array(
		'post_type' => 'post',
		'slug'      => 'sample-guide',
		'title'     => 'راهنمای نمونه',
		'excerpt'   => 'چکیدهٔ نمونه',
		'content'   => "<p>متن نمونهٔ مقاله</p>\n",
		'meta'      => array(
			'sa_seo_title' => 'عنوان سئو',
			'sa_empty'     => '',
		),
		'faq'       => array(
			array( 'q' => 'پرسش؟', 'a' => 'پاسخ.' ),
		),
		'terms'     => array(
			'category' => array( 'camping' => 'کمپینگ' ),
		),
		'cover'     => array( 'file' => 'sample.webp', 'alt' => 'متن جایگزین' ),
		'relations' => array(),
	);
}

function sa_laa_t_live_record() {
	return array(
		'post_type'      => 'post',
		'slug'           => 'sample-guide',
		'title'          => 'راهنمای نمونه',
		'excerpt'        => 'چکیدهٔ نمونه',
		'content'        => "<p>متن نمونهٔ مقاله</p>\n",
		'meta'           => array(
			'sa_seo_title' => 'عنوان سئو',
			'sa_faq'       => '[{"q":"پرسش؟","a":"پاسخ."}]',
			'sa_extra'     => 'متای اضافی زنده نباید اختلاف بسازد',
		),
		'terms'          => array(
			'category' => array( 'camping' => 'کمپینگ' ),
		),
		'featured_image' => array( 'url' => 'https://example.ir/wp-content/uploads/sample.webp', 'alt' => 'متن جایگزین' ),
		'content_images' => array(),
		'relations'      => array(),
		'wp_id'          => 42,
		'permalink'      => 'https://example.ir/sample-guide/',
	);
}

// ۱) رکورد یکسان: متای اضافی زنده و متای خالی خط مبنا اختلاف نیستند.
$report = SA_LAA_Compare::compare( array( sa_laa_t_base_record() ), array( sa_laa_t_live_record() ) );
sa_laa_t_assert( 1 === $report['summary']['identical'], 'test1 identical record' );
sa_laa_t_assert( 0 === $report['summary']['different'], 'test1 no different' );

// ۲) تفاوت فقط در HTML (متن ساده یکسان) اختلاف واقعی نیست.
$live           = sa_laa_t_live_record();
$live['content'] = '<div><p>متن نمونهٔ مقاله</p></div>';
$report = SA_LAA_Compare::compare( array( sa_laa_t_base_record() ), array( $live ) );
sa_laa_t_assert( 'identical' === $report['items'][0]['status'], 'test2 html-only change stays identical' );
sa_laa_t_assert( ! empty( $report['items'][0]['differences'] ) && 'info' === $report['items'][0]['differences'][0]['severity'], 'test2 info note recorded' );

// ۳) تغییر واقعی متن و عنوان: different.
$live            = sa_laa_t_live_record();
$live['title']   = 'عنوان عوض‌شده';
$live['content'] = '<p>متن کاملاً دیگری نوشته شده است.</p>';
$report = SA_LAA_Compare::compare( array( sa_laa_t_base_record() ), array( $live ) );
sa_laa_t_assert( 'different' === $report['items'][0]['status'], 'test3 real change is different' );
$fields = array_column( $report['items'][0]['differences'], 'field' );
sa_laa_t_assert( in_array( 'title', $fields, true ) && in_array( 'content', $fields, true ), 'test3 title+content fields' );

// ۴) جای‌نگهدار ایمپورت {{...}} در خط مبنا: فقط info.
$base            = sa_laa_t_base_record();
$base['content'] = '<p>متن نمونهٔ مقاله</p>{{FAQ}}';
$report = SA_LAA_Compare::compare( array( $base ), array( sa_laa_t_live_record() ) );
sa_laa_t_assert( 'identical' === $report['items'][0]['status'], 'test4 placeholder is info only' );

// ۵) متای گمشده + FAQ متفاوت + ترم متفاوت.
$live = sa_laa_t_live_record();
unset( $live['meta']['sa_seo_title'] );
$live['meta']['sa_faq'] = '[{"q":"پرسش دیگر؟","a":"پاسخ دیگر."}]';
$live['terms']           = array( 'category' => array( 'other' => 'دیگر' ) );
$report = SA_LAA_Compare::compare( array( sa_laa_t_base_record() ), array( $live ) );
$fields = array_column( $report['items'][0]['differences'], 'field' );
sa_laa_t_assert( in_array( 'meta:sa_seo_title', $fields, true ), 'test5 missing meta flagged' );
sa_laa_t_assert( in_array( 'faq', $fields, true ), 'test5 faq diff flagged' );
sa_laa_t_assert( in_array( 'terms:category', $fields, true ), 'test5 terms diff flagged' );

// ۶) فقط در یک طرف + کلید تکراری.
$live_only = sa_laa_t_live_record();
$live_only['slug'] = 'live-only-post';
$base_only = sa_laa_t_base_record();
$base_only['slug'] = 'repo-only-post';
$dup_live = sa_laa_t_live_record();
$report = SA_LAA_Compare::compare(
	array( sa_laa_t_base_record(), $base_only ),
	array( sa_laa_t_live_record(), $live_only, $dup_live )
);
sa_laa_t_assert( 1 === $report['summary']['live_only'], 'test6 live_only counted' );
sa_laa_t_assert( 1 === $report['summary']['baseline_only'], 'test6 baseline_only counted' );
sa_laa_t_assert( 1 === $report['summary']['duplicate_live'], 'test6 duplicate live counted' );
sa_laa_t_assert( 'post:live-only-post' === $report['live_only'][0]['key'], 'test6 live_only key' );

// ۷) نگاشت دادهٔ قالب فرزند: راهنما (post) و دیدنی (attraction).
$theme = array(
	'guide-key' => array(
		'title'    => 'راهنما',
		'type'     => 'post',
		'slug'     => 'guide-slug',
		'excerpt'  => 'چکیده',
		'category' => array( 'camping-survival' => 'آموزش‌های ضروری کمپینگ و بقا' ),
		'tags'     => array( 'fire' => 'آتش' ),
		'meta'     => array( 'sa_faq_note' => 'x' ),
		'faq'      => array( array( 'q' => '؟', 'a' => '!' ) ),
		'cover'    => array( 'file' => 'guide.webp', 'alt' => 'جایگزین' ),
		'content'  => '<p>متن</p>',
	),
	'attr-key' => array(
		'title'         => 'دیدنی',
		'type'          => 'attraction',
		'slug'          => 'attr-slug',
		'province'      => 'lorestan',
		'province_name' => 'لرستان',
		'city'          => 'dorud',
		'gallery'       => array( 'lake' => array( 'file' => 'lake.webp' ) ),
		'meta'          => array(),
		'faq'           => array(),
		'content'       => '<p>متن</p>{{GALLERY:lake}}',
	),
);
$records = SA_LAA_Baseline::records_from_theme_data( $theme );
sa_laa_t_assert( 2 === count( $records ), 'test7 theme records mapped' );
sa_laa_t_assert( 'category' === array_keys( $records[0]['terms'] )[0] && isset( $records[0]['terms']['post_tag'] ), 'test7 guide category/tags mapped' );
sa_laa_t_assert( 'lorestan' === $records[1]['relations']['province'] && 'dorud' === $records[1]['relations']['city'], 'test7 attraction relations mapped' );
sa_laa_t_assert( array( 'lake.webp' ) === $records[1]['gallery_files'], 'test7 gallery files mapped' );
sa_laa_t_assert( isset( $records[1]['terms']['province_tax']['lorestan'] ), 'test7 province_tax mapped' );

// ۸) اسنپ‌شات: کلید records و type به‌جای post_type.
$snap = SA_LAA_Baseline::records_from_snapshot(
	array(
		'records' => array(
			array( 'type' => 'city', 'slug' => 'dorud', 'title' => 'دورود' ),
			array( 'title' => 'بدون نامک' ),
		),
	)
);
sa_laa_t_assert( 1 === count( $snap ) && 'city' === $snap[0]['post_type'], 'test8 snapshot records normalized' );

// ۹) روابط: نامک استان زنده متفاوت با مخزن => اختلاف.
$base = sa_laa_t_base_record();
$base['post_type'] = 'attraction';
$base['relations'] = array( 'province' => 'lorestan', 'city' => 'dorud' );
$live = sa_laa_t_live_record();
$live['post_type'] = 'attraction';
$live['relations'] = array( 'province' => 'tehran', 'city' => 'dorud' );
$report = SA_LAA_Compare::compare( array( $base ), array( $live ) );
$fields = array_column( $report['items'][0]['differences'], 'field' );
sa_laa_t_assert( in_array( 'relation:province', $fields, true ), 'test9 relation diff flagged' );

// ۱۰) تصویر شاخص: نام فایل متفاوت => اختلاف؛ remove_featured_image => فقط info.
$live = sa_laa_t_live_record();
$live['featured_image'] = array( 'url' => 'https://example.ir/uploads/other.webp', 'alt' => 'متن جایگزین' );
$report = SA_LAA_Compare::compare( array( sa_laa_t_base_record() ), array( $live ) );
$fields = array_column( $report['items'][0]['differences'], 'field' );
sa_laa_t_assert( in_array( 'featured_image', $fields, true ), 'test10 featured file diff flagged' );

$base = sa_laa_t_base_record();
$base['remove_featured_image'] = true;
$report = SA_LAA_Compare::compare( array( $base ), array( sa_laa_t_live_record() ) );
sa_laa_t_assert( 'identical' === $report['items'][0]['status'], 'test10 remove featured is info only' );

// ۱۱) مهاجرت راهنماهای سفر: post:slug در مخزن با guide:slug در سایت زنده تطبیق داده می‌شود (بدون baseline_only/live_only کاذب).
$guide_base              = sa_laa_t_base_record();
$guide_base['post_type'] = 'post';
$guide_base['slug']      = 'camping-checklist';
$guide_live              = sa_laa_t_live_record();
$guide_live['post_type'] = 'guide';
$guide_live['slug']      = 'camping-checklist';
$report                  = SA_LAA_Compare::compare( array( $guide_base ), array( $guide_live ) );
sa_laa_t_assert( 1 === $report['summary']['matched'] && 1 === $report['summary']['identical'], 'test11 post<->guide migration matches cleanly as identical' );
sa_laa_t_assert( 0 === $report['summary']['baseline_only'] && 0 === $report['summary']['live_only'], 'test11 migrated guide does not appear in baseline_only or live_only' );

// ۱۲) تطبیق نامک شهرستان مرکز استان (city:tehran-city <-> city:tehran).
$cap_base              = sa_laa_t_base_record();
$cap_base['post_type'] = 'city';
$cap_base['slug']      = 'tehran-city';
$cap_live              = sa_laa_t_live_record();
$cap_live['post_type'] = 'city';
$cap_live['slug']      = 'tehran';
$report                = SA_LAA_Compare::compare( array( $cap_base ), array( $cap_live ) );
sa_laa_t_assert( 1 === $report['summary']['matched'] && 0 === $report['summary']['baseline_only'] && 0 === $report['summary']['live_only'], 'test12 capital county -city slug alias matches bare live slug' );

// ۱۳) مقایسهٔ عددی مختصات جغرافیایی (29.6360 == 29.636؛ اما 29.7000 != 29.636).
$coord_base                         = sa_laa_t_base_record();
$coord_base['meta']['sa_latitude']  = '29.6360';
$coord_base['meta']['sa_longitude'] = '52.5260';
$coord_live                         = sa_laa_t_live_record();
$coord_live['meta']['sa_latitude']  = '29.636';
$coord_live['meta']['sa_longitude'] = '52.526';
$report                             = SA_LAA_Compare::compare( array( $coord_base ), array( $coord_live ) );
sa_laa_t_assert( 'identical' === $report['items'][0]['status'], 'test13 trailing zeros in coordinates do not trigger false diff' );

$coord_live['meta']['sa_latitude'] = '29.7000';
$report                            = SA_LAA_Compare::compare( array( $coord_base ), array( $coord_live ) );
sa_laa_t_assert( 'different' === $report['items'][0]['status'], 'test13 real coordinate change still triggers diff' );

// ۱۴) یکسان‌سازی CRLF و LF در متاهای چندخطی (مانند sa_sources در ویرایشگر ویندوز/مرورگر).
$crlf_base                       = sa_laa_t_base_record();
$crlf_base['meta']['sa_sources'] = "منبع یک | https://example.org/1\nمنبع دو | https://example.org/2";
$crlf_live                       = sa_laa_t_live_record();
$crlf_live['meta']['sa_sources'] = "منبع یک | https://example.org/1\r\nمنبع دو | https://example.org/2\r\n";
$report                          = SA_LAA_Compare::compare( array( $crlf_base ), array( $crlf_live ) );
sa_laa_t_assert( 'identical' === $report['items'][0]['status'], 'test14 CRLF vs LF in sa_sources does not trigger false diff' );

echo "ALL TESTS PASSED\n";
