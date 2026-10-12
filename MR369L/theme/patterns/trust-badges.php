<?php
/**
 * Title: نشان‌های اعتماد (ارسال، بازگشت، پشتیبانی)
 * Slug: mr369l/trust-badges
 * Categories: mr369l-sections
 * Keywords: اعتماد, ارسال, پشتیبانی, ضمانت
 * Description: سه کارت کوتاه دربارهٔ ارسال، بازگشت کالا و پشتیبانی. متن‌ها در ویرایشگر قابل تغییرند.
 *
 * @package MR369L
 * @since   0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$mr369l_badges = array(
	array(
		'title' => _x( 'ارسال سریع', 'trust badge title', 'mr369l' ),
		'text'  => _x( 'ارسال به سراسر ایران با انتخاب روش‌های استاندارد و سریع.', 'trust badge text', 'mr369l' ),
	),
	array(
		'title' => _x( 'بازگشت آسان', 'trust badge title', 'mr369l' ),
		'text'  => _x( 'بازگشت کالا در بازهٔ اعلام‌شدهٔ فروشگاه، بدون کاغذبازی.', 'trust badge text', 'mr369l' ),
	),
	array(
		'title' => _x( 'پشتیبانی واقعی', 'trust badge title', 'mr369l' ),
		'text'  => _x( 'پاسخ‌گویی در ساعات کاری، پیش و پس از خرید.', 'trust badge text', 'mr369l' ),
	),
);
?>
<!-- wp:group {"align":"wide","className":"mr-section","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide mr-section">

	<!-- wp:columns {"className":"mr-stagger"} -->
	<div class="wp-block-columns mr-stagger">
		<?php foreach ( $mr369l_badges as $mr369l_badge ) : ?>
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"mr-card","layout":{"type":"constrained"}} -->
			<div class="wp-block-group mr-card">
				<!-- wp:heading {"level":3,"fontSize":"large"} -->
				<h3 class="wp-block-heading has-large-font-size"><?php echo esc_html( $mr369l_badge['title'] ); ?></h3>
				<!-- /wp:heading -->

				<!-- wp:paragraph -->
				<p><?php echo esc_html( $mr369l_badge['text'] ); ?></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->
