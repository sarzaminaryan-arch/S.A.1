<?php
/**
 * Generic entity page (province/city/attraction/route/food/souvenir).
 * Province pages begin with the source-aware identity card; other entities use the
 * shared key-facts card. Remaining order: hero → lead → rating (city) → content →
 * FAQ → county blocks → public contribution (city) → related → nav → sources (last).
 *
 * v2.11.0: بلوک امتیاز کاربران به بالای «اطلاعات کلیدی» آمد؛ بلوک مشارکت مردمی
 * از فوتر به داخل صفحه (پیش از منابع) منتقل شد و «منابع» آخرین بخش صفحه است.
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
		<?php
		$sa_profile = 'province' === $sa_type ? sa_province_profile( get_post_field( 'post_name', $sa_id ) ) : null;
		if ( $sa_profile ) {
			get_template_part( 'template-parts/entity/province-identity' );
		}
		?>

		<?php if ( has_excerpt() ) : ?>
			<p class="sa-entity__lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
		<?php endif; ?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::rating_block(); ?>
		<?php endif; ?>

		<?php if ( ! $sa_profile ) : ?>
			<?php get_template_part( 'template-parts/entity/facts' ); ?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/entity/county-profile' ); ?>

		<div class="entry-content">
			<?php the_content(); ?>
		</div>

		<?php get_template_part( 'template-parts/entity/faq' ); ?>

		<?php get_template_part( 'template-parts/entity/county-siblings' ); ?>


		<?php get_template_part( 'template-parts/entity/gallery' ); ?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::contrib_block(); ?>
		<?php endif; ?>

		<?php sa_related_articles( $sa_id, $sa_type ); ?>

		<?php sa_entity_navigation( $sa_id, $sa_type ); ?>

		<?php sa_sources_section( $sa_id ); ?>

		<?php sa_facts_checked_note( $sa_id ); ?>
	</div>

</article>
