<?php
/**
 * سازگاری عقب‌رو — «شناسنامه + نکته‌ها + تجربهٔ مسافرها» (v2.22.145).
 *
 * از ۲.۲۲.۱۴۵ این فایل دیگر ۶۸۶ خط یک‌تکه نیست: هر بخش فایل خودش را در
 * `template-parts/county/` دارد و ترتیب/شرط نمایش در رجیستری واحد
 * `inc/county-sections.php` ثبت است.
 *
 * این فایل فقط برای کدها و قالب‌های قدیمی نگه داشته شده: خروجی شناسنامه،
 * نکته‌های کوتاه و تجربهٔ مسافرها را به همان ترتیب تاریخی چاپ می‌کند. قالب
 * اصلی (`single.php`) دیگر این فایل را صدا نمی‌زند و از رجیستری می‌خواند.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id = get_the_ID();
if ( 'city' !== get_post_type( $sa_id ) ) {
	return;
}

$sa_context = function_exists( 'sa_county_sections_context' ) ? (array) sa_county_sections_context( $sa_id ) : array();

echo sa_county_identity_html( $sa_id, $sa_context );    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo sa_county_notes_html( $sa_id, $sa_context );       // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo sa_county_field_notes_html( $sa_id, $sa_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
