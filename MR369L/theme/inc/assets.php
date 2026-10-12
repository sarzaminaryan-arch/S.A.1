<?php
/**
 * MR369L — بارگذاری استایل‌ها و فونت.
 *
 * اصل‌ها:
 * - صفر جاوااسکریپت اختصاصی (D-008).
 * - صفر درخواست بیرونی: فونت‌ها محلی‌اند (D-007).
 * - استایل پایه از الگوی رسمی قالب‌های بلوکی هسته پیروی می‌کند:
 *   wp_enqueue_style() + wp_style_add_data( 'path' ) (مشاهده‌شده در
 *   wp-content/themes/twentytwentyfive/functions.php).
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نسخهٔ فایل بر پایهٔ زمان تغییر (فقط در حالت توسعه).
 *
 * در محیط توسعه، کش مرورگر را بی‌اعتبار می‌کند؛ در محیط تولید از نسخهٔ قالب
 * استفاده می‌شود تا اندازهٔ HTML کوچک بماند.
 *
 * @param string $relative_path مسیر نسبی فایل داخل قالب.
 * @return string
 */
function mr369l_asset_version( $relative_path ) {
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		$full = MR369L_DIR . ltrim( $relative_path, '/' );

		if ( file_exists( $full ) ) {
			return (string) filemtime( $full );
		}
	}

	return MR369L_VERSION;
}

/**
 * بارگذاری استایل‌های قالب در بخش عمومی.
 *
 * @return void
 */
function mr369l_enqueue_assets() {
	wp_enqueue_style(
		'mr369l-style',
		get_stylesheet_uri(),
		array(),
		MR369L_VERSION
	);
	wp_style_add_data( 'mr369l-style', 'path', MR369L_DIR . 'style.css' );

	wp_enqueue_style(
		'mr369l-base',
		MR369L_URI . 'assets/css/theme.css',
		array( 'mr369l-style' ),
		mr369l_asset_version( 'assets/css/theme.css' )
	);

	if ( 'off' !== mr369l_get_option( 'motion_level' ) ) {
		wp_enqueue_style(
			'mr369l-motion',
			MR369L_URI . 'assets/css/typography-motion.css',
			array( 'mr369l-base' ),
			mr369l_asset_version( 'assets/css/typography-motion.css' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'mr369l_enqueue_assets' );

/**
 * پیش‌بارگذاری وزن Regular فونت (تنها وزن حیاتی برای متن محتوا).
 *
 * فقط وزن ۴۰۰ پیش‌بارگذاری می‌شود: هر preload اضافه، پهنای باند LCP را می‌خورد.
 * اگر فایل موجود نباشد، هیچ خروجی‌ای تولید نمی‌شود (بدون ۴۰۴).
 *
 * @return void
 */
function mr369l_preload_font() {
	if ( is_admin() ) {
		return;
	}

	$relative = 'assets/fonts/Vazirmatn-Regular.woff2';

	if ( ! file_exists( MR369L_DIR . $relative ) ) {
		return;
	}

	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
		esc_url( MR369L_URI . $relative )
	);
}
add_action( 'wp_head', 'mr369l_preload_font', 1 );
