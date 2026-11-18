<?php
/**
 * سکشن «بدنهٔ مقاله» — v2.22.145.
 *
 * گروه: محتوایی — متنِ هر شهرستان با نشانه‌های `<!-- sa:sec:* -->`؛ عوض‌کردن خودِ متن
 * با ابزار دسته‌جمعی انجام می‌شود، نه با این فایل.
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
$GLOBALS['sa_county_toc_capture'] = true;
$GLOBALS['sa_county_toc_html']    = '';
ob_start();
the_content();
$sa_body_html = (string) ob_get_clean();
$sa_toc_html  = (string) $GLOBALS['sa_county_toc_html'];
unset( $GLOBALS['sa_county_toc_capture'], $GLOBALS['sa_county_toc_html'] );
echo $sa_toc_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده در sa_toc_markup.
echo $sa_body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by the_content.

