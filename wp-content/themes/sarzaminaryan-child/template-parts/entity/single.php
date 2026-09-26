<?php
/**
 * Generic entity page (province/city/attraction/route/food/souvenir).
 * Layout: hero → two columns (content + facts aside) → children/related sections → FAQ.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id   = get_the_ID();
$sa_type = get_post_type();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-entity-page' ); ?>>

	<?php get_template_part( 'template-parts/entity/hero' ); ?>

	<div class="container sa-entity__layout">
		<div class="sa-entity__main">
			<?php if ( has_excerpt() ) : ?>
				<p class="sa-entity__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>

			<div class="entry-content">
				<?php the_content(); ?>
			</div>

			<?php get_template_part( 'template-parts/entity/related', $sa_type ); ?>

			<?php get_template_part( 'template-parts/entity/faq' ); ?>

			<?php sa_facts_checked_note( $sa_id ); ?>
		</div>

		<aside class="sa-entity__aside" aria-label="اطلاعات کلیدی">
			<?php get_template_part( 'template-parts/entity/facts' ); ?>
			<?php if ( is_active_sidebar( 'sidebar-entity' ) ) : ?>
				<div class="widget-area"><?php dynamic_sidebar( 'sidebar-entity' ); ?></div>
			<?php endif; ?>
		</aside>
	</div>

</article>
