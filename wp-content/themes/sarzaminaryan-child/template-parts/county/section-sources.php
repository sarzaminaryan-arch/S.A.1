<?php
/**
 * سکشن «منابع، شهرهای هم‌استانی و یادداشت بازبینی» — v2.22.145.
 *
 * گروه: محتوایی — لینک‌سازی هم‌استانی از `sa_county_siblings_cached()` می‌آید (transient)،
 * پس این سکشن کوئری per-page اضافه نمی‌زند.
 *
 * ورودی: $sa_id (int)، $sa_context (array)، $sa_profile_ext (array).
 * خروجی: HTML به‌صورت رشته، از طریق لایهٔ `inc/county-sections-loader.php`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id          = isset( $sa_id ) ? (int) $sa_id : (int) get_the_ID();
$sa_context     = isset( $sa_context ) ? (array) $sa_context : array();
$sa_profile_ext = isset( $sa_profile_ext ) ? (array) $sa_profile_ext : array();
// مهم: «شهرهای هم‌استانی» را خودِ sa_sources_section() چاپ می‌کند (v2.22.86)؛
// این‌جا تکرار نمی‌شود وگرنه آکاردئون دوبار روی صفحه می‌آید (رگرسیون ۲.۲۲.۱۴۵).
if ( function_exists( 'sa_sources_section' ) ) {
	sa_sources_section( $sa_id );
}
if ( function_exists( 'sa_facts_checked_note' ) ) {
	sa_facts_checked_note( $sa_id );
}

