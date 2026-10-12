<?php
/**
 * MR369L — bootstrap.
 *
 * قالب فروشگاهی فارسی/RTL بر پایهٔ قالب بلوکی (FSE).
 * این فایل فقط «چسب کارکرد» است: ثابت‌ها، فهرست فایل‌های inc/ و راه‌اندازی.
 * هیچ منطق نمایشی، هیچ CPT، هیچ شورت‌کد و هیچ بلوک سفارشی اینجا نیست (قاعدهٔ D-006).
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MR369L_VERSION', '0.1.0' );
define( 'MR369L_DIR', trailingslashit( get_template_directory() ) );
define( 'MR369L_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * فهرست ماژول‌های قالب (به ترتیب بارگذاری).
 *
 * هر ماژول باید در ابتدای خود نگهبان ABSPATH داشته باشد.
 *
 * @var string[]
 */
$mr369l_includes = array(
	'inc/setup.php',   // راه‌اندازی قالب: پشتیبانی‌ها، لینک پرش، کلاس‌های body.
	'inc/assets.php',  // بارگذاری شرطی استایل‌ها و فونت.
	'inc/options.php', // صفحهٔ تنظیمات مالک (Settings API).
);

foreach ( $mr369l_includes as $mr369l_file ) {
	require_once MR369L_DIR . $mr369l_file;
}

unset( $mr369l_includes, $mr369l_file );
