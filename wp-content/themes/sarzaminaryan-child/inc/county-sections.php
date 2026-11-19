<?php
/**
 * رجیستری واحد سکشن‌های صفحهٔ شهرستان (v2.22.145).
 *
 * چرا این فایل: پیش از این، ترتیب و شرط نمایش هر بخش در جاهای مختلف پخش بود —
 * بخشی در `template-parts/entity/single.php` (تصویر شاخص، گالری، امتیاز، منابع…)،
 * بخشی داخل `template-parts/entity/county-profile.php` که ۶۸۶ خط است و
 * شناسنامه + نکته‌ها + «تجربهٔ مسافرها» را با هم می‌سازد. نتیجه: برای عوض‌کردن
 * ترتیب، باید فایل بزرگ را دست می‌زدیم و هیچ‌جا نمی‌شد دید کل صفحه از چه
 * ساخته شده است.
 *
 * حالا **همهٔ** سکشن‌های یک صفحهٔ شهرستان در همین یک آرایه ثبت می‌شوند. قالب
 * فقط این آرایه را می‌خواند و به همان ترتیب رندر می‌کند. تغییر ترتیب، خاموش‌کردن
 * یک بخش یا عوض‌کردن شرط نمایشش = یک سطر در همین فایل؛ هیچ‌کدام از ۴۸۴ نوشتهٔ
 * `city` در دیتابیس دست نمی‌خورد (چون رندر زمان‌محور است، نه ذخیره‌شده در پست).
 *
 * هر سکشن:
 *   key      — شناسهٔ یکتا (همان‌جا که لازم بود، مثل `sa:sec:*` یا id لنگر).
 *   label    — نام فارسی برای گزارش و ابزار بازبینی.
 *   group    — `structure` (چیدمان/HTML/CSS: تغییر فایل = اثر فوری روی همه)
 *              یا `content` (متنِ مخصوص هر شهرستان: با ابزار دسته‌جمعی عوض می‌شود).
 *   render   — نام تابع رندر؛ همان امضای همه: (int $post_id): string.
 *   enabled  — خاموش‌کردن موقت بدون حذف.
 *   when     — شرط نمایش: هرگز داخل رجیستری کوئری نمی‌زند (شرط سبک فقط).
 *   filter   — فیلتر وردپرس برای شرط‌های سفارشی (اختیاری).
 *   cache    — کلید کش خروجی (اختیاری)؛ برای بخش‌هایی که ساختشان سنگین است.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * رجیستری سکشن‌ها — منبع واحد ترتیب و شرط نمایش.
 *
 * ترتیب آرایه همان ترتیب چاپ روی صفحه است.
 *
 * @return array[]
 */
function sa_county_sections_registry() {
	$sections = array(
		// ۱) تصویر شاخص شهرستان — ساختاری، بالای همه چیز (v2.22.90).
		'sa-county-cover'    => array(
			'label'  => 'تصویر شاخص شهرستان',
			'group'  => 'structure',
			'render' => 'sa_county_cover_html',
			'when'   => 'always',
		),
		'sa-county-summary'  => array(
			'label'  => 'خلاصه و اطلاعات کلیدی',
			'group'  => 'structure',
			'render' => 'sa_county_summary_html',
			'when'   => 'always',
		),
		// ۲) فهرست مطالب آکاردئونی — ساختاری؛ بالای شناسنامه (v2.22.116).
		'sa-county-toc'      => array(
			'label'  => 'فهرست مطالب',
			'group'  => 'structure',
			'render' => 'sa_county_toc_html',
			'when'   => 'always',
			'cache'  => 'sa_toc',
		),
		// ۳) شناسنامه — ساختاری؛ ظرفِ کادرهای ۰۱…۱۱ (v2.22.86).
		'sa-county-identity' => array(
			'label'  => 'شناسنامهٔ شهرستان',
			'group'  => 'structure',
			'render' => 'sa_county_identity_html',
			'when'   => 'always',
		),
		// ۴) نکته‌های کوتاه زیر شناسنامه — محتوایی (متن هر شهرستان).
		'sa-county-notes'    => array(
			'label'  => 'نکته‌های کوتاه (قدمت، صنعت، کشاورزی)',
			'group'  => 'content',
			'render' => 'sa_county_notes_html',
			'when'   => 'has_facts',
		),
		// ۵) تجربهٔ مسافرها — محتوایی؛ داده از متای `sa_county_profile_data()`.
		'sa-county-field'    => array(
			'label'  => 'تجربهٔ مسافرها',
			'group'  => 'content',
			'render' => 'sa_county_field_notes_html',
			'when'   => 'has_insights',
		),
		// ۶) بدنهٔ مقاله — محتوایی؛ هفت سکشن درون‌متنی با نشانه‌های sa:sec:*.
		'sa-county-body'     => array(
			'label'  => 'بدنهٔ مقاله (سکشن‌های درون‌متنی)',
			'group'  => 'content',
			'render' => 'sa_county_body_html',
			'when'   => 'always',
		),
		// ۷) سوالات متداول — محتوایی؛ فقط اگر پرسش معتبر داشته باشد.
		'sa-county-faq'      => array(
			'label'  => 'سوالات متداول',
			'group'  => 'content',
			'render' => 'sa_county_faq_html',
			'when'   => 'has_faq',
		),
		// ۸) گالری تصاویر شهرستان — محتوایی از نظر عکس، ساختاری از نظر قالب؛
		//    فقط اگر عکس تأییدشده داشته باشد (شرط نمایش خواستهٔ مالک).
		'sa-county-gallery'  => array(
			'label'  => 'گالری تصاویر شهرستان',
			'group'  => 'structure',
			'render' => 'sa_county_photos_html',
			'when'   => 'has_photos',
		),
		// ۹) امتیاز کاربران — ساختاری.
		'sa-county-rating'   => array(
			'label'  => 'امتیاز کاربران',
			'group'  => 'structure',
			'render' => 'sa_county_rating_html',
			'when'   => 'cc_ui',
		),
		// ۱۰) منابع + شهرهای هم‌استانی + یادداشت بازبینی — محتوایی/ساختاری آمیخته.
		'sa-county-sources'  => array(
			'label'  => 'منابع و شهرهای هم‌استانی',
			'group'  => 'content',
			'render' => 'sa_county_sources_html',
			'when'   => 'always',
		),
		// ۱۱) مشارکت مردمی — ساختاری.
		'sa-county-contrib'  => array(
			'label'  => 'مشارکت مردمی («شهر من»)',
			'group'  => 'structure',
			'render' => 'sa_county_contrib_html',
			'when'   => 'cc_ui',
		),
		// ۱۲) نوشته‌های مرتبط — ساختاری.
		'sa-county-related'  => array(
			'label'  => 'نوشته‌های مرتبط',
			'group'  => 'structure',
			'render' => 'sa_county_related_html',
			'when'   => 'always',
		),
		// ۱۳) ناوبری قبلی/بعدی — ساختاری؛ آخرین بخش صفحه (v2.22.52).
		'sa-county-nav'      => array(
			'label'  => 'ناوبری مقالهٔ قبلی/بعدی',
			'group'  => 'structure',
			'render' => 'sa_county_nav_html',
			'when'   => 'always',
		),
	);

	/**
	 * فیلتر رجیستری: افزودن/حذف/جابه‌جایی سکشن بدون دست‌زدن به فایل قالب.
	 *
	 * @param array[] $sections رجیستری.
	 */
	return (array) apply_filters( 'sa_county_sections', $sections );
}

/**
 * ترتیب نهایی سکشن‌ها (کلید → تعریف) با اعمال `order` و `enabled`.
 *
 * هر تعریف می‌تواند `order` (عدد، پیش‌فرض ۱۰۰) و `enabled` (bool، پیش‌فرض true)
 * داشته باشد. عدد کوچک‌تر = بالاتر. ترتیب پایدار است (usort پایدار نیست، پس
 * شمارهٔ ترتیب آرایه به‌عنوان کلید ثانویه به کار می‌رود).
 *
 * @return array[]
 */
function sa_county_sections_ordered() {
	$registry = sa_county_sections_registry();
	$rows     = array();
	$i        = 0;
	foreach ( $registry as $key => $def ) {
		++$i;
		$def           = (array) $def;
		$def['key']    = (string) $key;
		$def['order']  = isset( $def['order'] ) ? (int) $def['order'] : ( $i * 10 );
		$def['enabled'] = ! isset( $def['enabled'] ) || (bool) $def['enabled'];
		$def['_seq']   = $i;
		$rows[]        = $def;
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			if ( $a['order'] === $b['order'] ) {
				return $a['_seq'] <=> $b['_seq'];
			}
			return $a['order'] <=> $b['order'];
		}
	);
	return $rows;
}

/**
 * شرط نمایش یک سکشن — عمداً بدون کوئری سنگین.
 *
 * شرط‌ها از قبل محاسبه‌شده‌اند (context) تا هر سکشن خودش کوئری تازه نزند.
 *
 * @param string $when    نام شرط.
 * @param array  $context داده‌های آمادهٔ صفحه.
 * @return bool
 */
function sa_county_section_when( $when, array $context ) {
	switch ( (string) $when ) {
		case 'always':
			return true;
		case 'has_facts':
			return ! empty( $context['notes'] );
		case 'has_insights':
			return ! empty( $context['has_insights'] );
		case 'has_faq':
			return ! empty( $context['faq'] );
		case 'has_photos':
			return ! empty( $context['photo_ids'] );
		case 'cc_ui':
			return ! empty( $context['cc_ui'] );
		default:
			/**
			 * شرط سفارشی.
			 *
			 * @param bool   $show    پیش‌فرض false برای شرط ناشناس.
			 * @param array  $context داده‌های صفحه.
			 */
			return (bool) apply_filters( 'sa_county_section_when_' . $when, false, $context );
	}
}

/**
 * داده‌های مشترک صفحه که همهٔ شرط‌ها و رندرها از آن استفاده می‌کنند.
 *
 * این تابع تنها جایی است که «کوئری» می‌زند و کش می‌شود، پس رندر ۱۳ سکشن
 * مجموعاً همان چند فراخوانی قبلی را دارد، نه ۱۳ کوئری اضافه.
 *
 * @param int $post_id شناسهٔ شهرستان.
 * @return array
 */
function sa_county_sections_context( $post_id ) {
	$post_id = (int) $post_id;
	static $memo = array();
	if ( isset( $memo[ $post_id ] ) ) {
		return $memo[ $post_id ];
	}
	$profile = function_exists( 'sa_county_profile_data' ) ? (array) sa_county_profile_data( $post_id ) : array();
	$notes   = array();
	if ( ! empty( $profile['history_now'] ) ) {
		$notes[] = array( 'tag' => 'HISTORY & TODAY', 'title' => 'قدمت و امروز', 'text' => $profile['history_now'] );
	}
	if ( ! empty( $profile['industry_main'] ) ) {
		$notes[] = array( 'tag' => 'PEOPLE & INDUSTRY', 'title' => 'مردم، صنعت و کارخانه‌ها', 'text' => $profile['industry_main'] );
	}
	if ( ! empty( $profile['agriculture_main'] ) ) {
		$notes[] = array( 'tag' => 'AGRICULTURE', 'title' => 'کشاورزی و دامداری', 'text' => $profile['agriculture_main'] );
	}
	$memo[ $post_id ] = array(
		'post_id'      => $post_id,
		'profile'      => $profile,
		'notes'        => $notes,
		'has_insights' => ! empty( $profile['has_insights'] ),
		'faq'          => function_exists( 'sa_get_valid_faq' ) ? (bool) sa_get_valid_faq( $post_id ) : false,
		'photo_ids'    => function_exists( 'sa_photo_county_ids' ) ? (array) sa_photo_county_ids( $post_id, 24 ) : array(),
		'cc_ui'        => class_exists( 'CC_UI' ),
	);
	return $memo[ $post_id ];
}

/**
 * رندر کل صفحهٔ شهرستان از روی رجیستری.
 *
 * @param int $post_id شناسهٔ شهرستان.
 * @return string HTML.
 */
function sa_county_render_sections( $post_id ) {
	$post_id = (int) $post_id;
	if ( 'city' !== get_post_type( $post_id ) ) {
		return '';
	}
	$context = sa_county_sections_context( $post_id );
	$html    = '';
	foreach ( sa_county_sections_ordered() as $def ) {
		if ( empty( $def['enabled'] ) ) {
			continue;
		}
		if ( ! sa_county_section_when( isset( $def['when'] ) ? $def['when'] : 'always', $context ) ) {
			continue;
		}
		$render = isset( $def['render'] ) ? (string) $def['render'] : '';
		if ( '' === $render || ! function_exists( $render ) ) {
			continue;
		}
		$html .= (string) call_user_func( $render, $post_id, $context );
	}
	return $html;
}
