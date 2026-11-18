<?php
/**
 * سکشن «فهرست مطالب» — v2.22.145.
 *
 * گروه: ساختاری — لنگرها را ثبت می‌کند؛ چاپ فهرست در جریان بدنه انجام می‌شود
 * (v2.22.116) تا فهرست دقیقاً بالای شناسنامه بنشیند.
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
if ( function_exists( 'sa_toc_register_tail' ) ) {
	sa_toc_register_tail( $sa_id );
}

