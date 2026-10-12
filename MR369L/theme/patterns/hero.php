<?php
/**
 * Title: هیرو صفحهٔ نخست (موشن تایپوگرافی)
 * Slug: mr369l/hero
 * Categories: mr369l-sections, featured
 * Keywords: هیرو, قهرمان, تایپوگرافی, انیمیشن, تخفیف
 * Description: تیتر بزرگ با انیمیشن ورود کلمه‌به‌کلمه (CSS خالص) به‌همراه متن کوتاه و دو دکمهٔ کنش.
 *
 * نکته‌های پیاده‌سازی:
 * - هیچ جاوااسکریپتی درگیر نیست؛ ترتیب با متغیر CSS «--mr-i» روی هر span تعیین می‌شود.
 * - اگر سطح موشن «خاموش» باشد یا کاربر «کاهش حرکت» را فعال کرده باشد، متن بدون حرکت و کامل دیده می‌شود.
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mr369l_title_words = array(
	esc_html_x( 'خریدِ', 'hero headline word', 'mr369l' ),
	esc_html_x( 'ساده،', 'hero headline word', 'mr369l' ),
	esc_html_x( 'سریع', 'hero headline word', 'mr369l' ),
	esc_html_x( 'و', 'hero headline word', 'mr369l' ),
	esc_html_x( 'مطمئن', 'hero headline word', 'mr369l' ),
);

$mr369l_title_parts = array();
$mr369l_index       = 0;

foreach ( $mr369l_title_words as $mr369l_word ) {
	$mr369l_title_parts[] = sprintf(
		'<span class="mr-rise" style="--mr-i:%d">%s</span>',
		$mr369l_index,
		$mr369l_word
	);
	++$mr369l_index;
}

// فاصلهٔ طبیعی بین spanهای inline-block، جداکنندهٔ کلمه‌ها در فارسی است.
$mr369l_title = implode( ' ', $mr369l_title_parts );

?>
<!-- wp:group {"align":"wide","className":"mr-section mr-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mr-section mr-hero has-surface-background-color has-background" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">

	<!-- wp:heading {"level":1,"fontSize":"display","className":"mr-hero__title","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
	<h1 class="wp-block-heading mr-hero__title has-display-font-size" style="margin-bottom:var(--wp--preset--spacing--40)"><?php echo wp_kses( $mr369l_title, array( 'span' => array( 'class' => true, 'style' => true ) ) ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"fontSize":"large","className":"mr-hero__lead","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} -->
	<p class="mr-hero__lead has-large-font-size" style="margin-bottom:var(--wp--preset--spacing--50)"><?php echo esc_html_x( 'انتخاب‌های برگزیده، ارسال سریع به سراسر ایران و پشتیبانی واقعی؛ بدون شلوغی، بدون اتلاف وقت.', 'hero lead paragraph', 'mr369l' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/shop/"><?php echo esc_html_x( 'مشاهدهٔ فروشگاه', 'hero primary button', 'mr369l' ); ?></a></div>
		<!-- /wp:button -->

		<!-- wp:button {"className":"is-style-outline"} -->
		<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#mr-main"><?php echo esc_html_x( 'پیشنهادهای این هفته', 'hero secondary button', 'mr369l' ); ?></a></div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
