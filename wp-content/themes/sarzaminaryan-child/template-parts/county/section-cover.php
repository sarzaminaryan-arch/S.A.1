<?php
/**
 * سکشن «تصویر شاخص شهرستان» — v2.22.145.
 *
 * گروه: ساختاری — تغییر این فایل فوراً روی همهٔ ۴۸۴ صفحه اثر می‌گذارد.
 * تصویر از `sa_county_featured_figure()` می‌آید و خودش شرط «تصویر موجود» را دارد.
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
if ( ! function_exists( 'sa_county_featured_figure' ) ) {
	return;
}
echo sa_county_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده با توابع escape داخلی.

