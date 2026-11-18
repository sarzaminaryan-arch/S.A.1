<?php
/**
 * سکشن «گالری تصاویر شهرستان» — v2.22.145.
 *
 * گروه: ساختاری — اسلایدر WebP؛ شرط «عکس دارد» در رجیستری است، پس بدون عکس هیچ
 * قابی چاپ نمی‌شود. تصاویر خودشان lazy-load هستند.
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
if ( ! function_exists( 'sa_photo_strip' ) ) {
	return;
}
sa_photo_strip( $sa_id );

