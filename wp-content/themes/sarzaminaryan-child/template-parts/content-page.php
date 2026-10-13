<?php
/**
 * Page content.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-article' ); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>
	</header>
	<?php /* v2.15.0: تصویر شاخص برگه‌ها هم تا بازگشت فایل‌های رسانه پنهان است. */ ?>
	<?php if ( function_exists( 'sa_featured_image_available' ) && sa_featured_image_available( get_the_ID(), 'page' ) ) : ?>
		<figure class="post-thumbnail"><?php the_post_thumbnail( 'sa-hero', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?></figure>
	<?php endif; ?>
	<div class="entry-content">
		<?php
		the_content();
		wp_link_pages( array( 'before' => '<div class="page-links">صفحه‌ها:', 'after' => '</div>' ) );
		?>
	</div>
	<?php get_template_part( 'template-parts/entity/faq' ); ?>
</article>
