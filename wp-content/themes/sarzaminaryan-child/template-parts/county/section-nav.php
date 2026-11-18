<?php
/**
 * سکشن «ناوبری مقالهٔ قبلی/بعدی» — v2.22.145.
 *
 * گروه: ساختاری — آخرین بخش صفحه (v2.22.52)؛ عوض‌کردن جایش فقط با `order` در رجیستری.
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
if ( function_exists( 'sa_entity_navigation' ) ) {
	sa_entity_navigation( $sa_id, 'city' );
}

