<?php
/**
 * سکشن «امتیاز کاربران» — v2.22.145.
 *
 * گروه: ساختاری — فقط وقتی بخش مشارکت مردمی (CC_UI) بارگذاری شده باشد.
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
if ( ! class_exists( 'CC_UI' ) ) {
	return;
}
CC_UI::rating_block();

