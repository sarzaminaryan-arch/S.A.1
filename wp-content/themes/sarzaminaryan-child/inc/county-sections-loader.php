<?php
/**
 * بارگذار سکشن‌های صفحهٔ شهرستان (v2.22.145).
 *
 * `inc/county-sections.php` رجیستری است (کدام سکشن، با چه ترتیبی، با چه شرطی).
 * این فایل هر سکشن را به یک تابع رشته‌ای تبدیل می‌کند تا رجیستری بداند چه چیزی
 * صدا بزند. سکشن‌ها در `template-parts/county/` زندگی می‌کنند:
 *
 *   section-cover.php        تصویر شاخص (ساختاری)
 *   section-summary.php      خلاصه و اطلاعات کلیدی (ساختاری)
 *   section-toc.php          فهرست مطالب (ساختاری)
 *   section-identity.php     شناسنامه (ساختاری)
 *   section-notes.php        نکته‌های کوتاه (محتوایی)
 *   section-field-notes.php  تجربهٔ مسافرها (محتوایی)
 *   section-body.php         بدنهٔ مقاله (محتوایی)
 *   section-faq.php          سوالات متداول (محتوایی)
 *   section-photos.php       گالری تصاویر (ساختاری)
 *   section-rating.php       امتیاز کاربران (ساختاری)
 *   section-sources.php      منابع و هم‌استانی‌ها (محتوایی)
 *   section-contrib.php      مشارکت مردمی (ساختاری)
 *   section-related.php      نوشته‌های مرتبط (ساختاری)
 *   section-nav.php          ناوبری قبلی/بعدی (ساختاری)
 *
 * قاعدهٔ سرعت: هر تابع فقط `ob_start()` روی یک فایل سبک است؛ دادهٔ مشترک از
 * `sa_county_sections_context()` می‌آید که memo شده است، پس ۱۳ سکشن هیچ کوئری
 * تکراری نمی‌زنند. جای دیگری که ساختش سنگین باشد (لینک‌سازی هم‌استانی) کش
 * transient دارد: `sa_county_siblings_cached()`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * رندر یک فایل سکشن به رشته، با متغیرهای مشترک صفحه.
 *
 * @param string $name    نام فایل بدون پیشوند/پسوند (مثل `identity`).
 * @param int    $post_id شناسه.
 * @param array  $context زمینهٔ آماده‌شده.
 * @return string
 */
function sa_county_section_part( $name, $post_id, $context = array() ) {
	$file = SA_CHILD_DIR . 'template-parts/county/section-' . sanitize_file_name( (string) $name ) . '.php';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	$sa_id          = (int) $post_id;
	$sa_context     = (array) $context;
	$sa_profile_ext = isset( $sa_context['profile'] ) ? (array) $sa_context['profile'] : array();
	$sa_notes       = isset( $sa_context['notes'] ) ? (array) $sa_context['notes'] : array();
	ob_start();
	include $file;
	return (string) ob_get_clean();
}

/**
 * ۱) تصویر شاخص.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_cover_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'cover', $post_id, $context );
}

/**
 * خلاصه و اطلاعات کلیدی زیر تصویر شاخص.
 */
function sa_county_summary_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'summary', $post_id, $context );
}

/**
 * ۲) فهرست مطالب — از قبل رندر و echo می‌شود؛ این تابع فقط ثبت لنگرها را انجام
 * می‌دهد تا ترتیب سکشن‌ها حفظ شود.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_toc_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'toc', $post_id, $context );
}

/**
 * ۳) شناسنامه.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_identity_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'identity', $post_id, $context );
}

/**
 * ۴) نکته‌های کوتاه.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_notes_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'notes', $post_id, $context );
}

/**
 * ۵) تجربهٔ مسافرها.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_field_notes_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'field-notes', $post_id, $context );
}

/**
 * ۶) بدنهٔ مقاله — محتوای خودِ پست، با نشانه‌های `sa:sec:*`.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_body_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'body', $post_id, $context );
}

/**
 * ۷) سوالات متداول.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_faq_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'faq', $post_id, $context );
}

/**
 * ۸) گالری تصاویر.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_photos_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'photos', $post_id, $context );
}

/**
 * ۹) امتیاز کاربران.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_rating_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'rating', $post_id, $context );
}

/**
 * ۱۰) منابع + شهرهای هم‌استانی + یادداشت بازبینی.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_sources_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'sources', $post_id, $context );
}

/**
 * ۱۱) مشارکت مردمی.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_contrib_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'contrib', $post_id, $context );
}

/**
 * ۱۲) نوشته‌های مرتبط.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_related_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'related', $post_id, $context );
}

/**
 * ۱۳) ناوبری قبلی/بعدی.
 *
 * @param int   $post_id شناسه.
 * @param array $context زمینه.
 * @return string
 */
function sa_county_nav_html( $post_id, $context = array() ) {
	return sa_county_section_part( 'nav', $post_id, $context );
}

/**
 * شهرهای هم‌استانی با کش — لینک‌سازی داخلی، نه کوئری per-page.
 *
 * `sa_county_profile_sibling_cities()` برای هر شهرستانِ هم‌استان یک
 * `get_page_by_path()` می‌زند؛ روی استانی با ۲۰ شهرستان این می‌شود ۲۰ کوئری
 * در هر بازدید. نتیجه یک‌ساعت کش می‌شود و با ذخیره/حذف هر نوشتهٔ `city` پاک
 * می‌شود، بنابراین هرگز کهنه نمی‌ماند.
 *
 * @param int $post_id شناسهٔ شهرستان.
 * @param int $limit   بیشینهٔ تعداد.
 * @return array[]
 */
function sa_county_siblings_cached( $post_id, $limit = 12 ) {
	$post_id = (int) $post_id;
	$limit   = max( 1, (int) $limit );
	$key     = 'sa_sib_' . $post_id . '_' . $limit . '_' . (int) get_post_modified_time( 'U', true, $post_id );
	$cached  = get_transient( $key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$rows = function_exists( 'sa_county_profile_sibling_cities' )
		? (array) sa_county_profile_sibling_cities( $post_id, $limit )
		: array();
	set_transient( $key, $rows, HOUR_IN_SECONDS );
	return $rows;
}

/**
 * پاک‌کردن کش هم‌استانی‌ها وقتی فهرست شهرستان‌ها عوض می‌شود.
 *
 * @return void
 */
function sa_county_siblings_flush() {
	global $wpdb;
	if ( ! isset( $wpdb->options ) ) {
		return;
	}
	// کش transient با پیشوند کلید در options ذخیره می‌شود؛ پاک‌سازی گروهی امن است.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sa_sib_%' OR option_name LIKE '_transient_timeout_sa_sib_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
add_action( 'save_post_city', 'sa_county_siblings_flush' );
add_action( 'deleted_post', 'sa_county_siblings_flush' );
add_action( 'edited_term', 'sa_county_siblings_flush' );
