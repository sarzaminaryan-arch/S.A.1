<?php
/**
 * Title: نوار اعلان متحرک
 * Slug: mr369l/announcement-bar
 * Categories: mr369l-sections
 * Keywords: اعلان, ارسال رایگان, نوار, متحرک
 * Description: نوار اعلان باریک با حرکت آرام متن (CSS خالص). در سطح موشن «نمایشی» حرکت می‌کند و در «ملایم» ثابت است. نسخهٔ تکراری متن برای حرکت، aria-hidden است تا صفحه‌خوان دوباره نخواند.
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mr369l_notice = _x( 'ارسال رایگان برای سفارش‌های بالای مبلغ تعیین‌شده — بسته‌بندی ایمن و ارسال سریع.', 'announcement bar text', 'mr369l' );
?>
<!-- wp:group {"className":"mr-ticker","backgroundColor":"contrast","textColor":"base","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group mr-ticker has-base-color has-contrast-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)">

	<!-- wp:group {"className":"mr-ticker__track","layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group mr-ticker__track">
		<!-- wp:paragraph {"fontSize":"small"} -->
		<p class="has-small-font-size"><?php echo esc_html( $mr369l_notice ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:html -->
		<p class="has-small-font-size" aria-hidden="true"><?php echo esc_html( $mr369l_notice ); ?></p>
		<!-- /wp:html -->
	</div>
	<!-- /wp:group -->

</div>
<!-- /wp:group -->
