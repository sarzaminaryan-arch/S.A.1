<?php
/**
 * MR369L — راه‌اندازی قالب.
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پشتیبانی‌ها و تنظیمات پایهٔ قالب.
 *
 * نکتهٔ شواهد‌محور (WordPress 7.1.3):
 * - برای قالب‌های بلوکی، هسته خودش این‌ها را اضافه می‌کند:
 *   post-thumbnails, responsive-embeds, editor-styles, html5, automatic-feed-links
 *   (منبع: wp-includes/theme.php → _add_default_theme_supports()).
 * - برچسب عنوان سند (title-tag) برای قالب بلوکی لازم نیست؛
 *   wp-includes/block-template.php آن را «صرف‌نظر از پشتیبانی قالب» رندر می‌کند.
 * پس اینجا فقط مواردی اضافه می‌شوند که خودکار نیستند.
 *
 * @return void
 */
function mr369l_setup() {
	load_theme_textdomain( 'mr369l', MR369L_DIR . 'languages' );

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	/*
	 * عرض محتوا برای oEmbed و پیش‌نمایش‌های بیرونی. معادل contentSize در theme.json (۴۴rem).
	 */
	if ( ! isset( $GLOBALS['content_width'] ) ) {
		$GLOBALS['content_width'] = 704;
	}
}
add_action( 'after_setup_theme', 'mr369l_setup' );

/**
 * افزودن لینک «پرش به محتوا» در ابتدای بدنه.
 *
 * با PHP اضافه می‌شود (نه در قالب HTML) تا قابل ترجمه، escape‌شده و وابسته به
 * هیچ الگوی ویرایشگر نباشد. هوک wp_body_open توسط قالب بلوکی هسته اجرا می‌شود
 * (wp-includes/template-canvas.php).
 *
 * @return void
 */
function mr369l_skip_link() {
	if ( is_admin() ) {
		return;
	}

	printf(
		'<a class="mr-skip-link screen-reader-text" href="#mr-main">%s</a>',
		esc_html__( 'پرش به محتوای اصلی', 'mr369l' )
	);
}
add_action( 'wp_body_open', 'mr369l_skip_link', 5 );

/**
 * ثبت دستهٔ الگوهای قالب (برای دسته‌بندی در پنجرهٔ الگوها).
 *
 * @return void
 */
function mr369l_register_pattern_categories() {
	register_block_pattern_category(
		'mr369l-sections',
		array( 'label' => __( 'بخش‌های MR369L', 'mr369l' ) )
	);
}
add_action( 'init', 'mr369l_register_pattern_categories' );

/**
 * افزودن کلاس‌های وضعیت قالب به body.
 *
 * `mr-motion-*` سطح موشن تایپوگرافی را تعیین می‌کند و توسط CSS موشن خوانده می‌شود.
 *
 * @param string[] $classes کلاس‌های فعلی body.
 * @return string[]
 */
function mr369l_body_classes( $classes ) {
	$motion = mr369l_get_option( 'motion_level' );

	if ( ! in_array( $motion, array( 'off', 'subtle', 'full' ), true ) ) {
		$motion = 'subtle';
	}

	$classes[] = 'mr-motion-' . $motion;

	if ( class_exists( 'WooCommerce' ) ) {
		$classes[] = 'mr-has-woocommerce';
	}

	return $classes;
}
add_filter( 'body_class', 'mr369l_body_classes' );
