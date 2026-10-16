<?php
/**
 * Single template for all 6 entity CPTs.
 *
 * v2.19.1:
 * - Attraction featured images are hidden by default.
 * - Each of the 3 primary entities (`province`, `city`, `attraction`) displays its own
 *   Cyber-Tech Light-Mode Tabular Identity Card (`شناسنامه`) at the top of the page.
 * - Duplicate lead paragraphs and duplicate `اطلاعات کلیدی` (`facts.php`) boxes are
 *   omitted on `province`, `city`, and `attraction` so information inside the `شناسنامه`
 *   is never repeated outside it.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_type = get_post_type( $sa_id );
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-entity sa-entity--' . esc_attr( $sa_type ) ); ?>>
	<?php get_template_part( 'template-parts/entity/hero' ); ?>

	<div class="sa-container sa-entity__layout">
		<div class="sa-entity__main">
			<?php
			if ( 'province' === $sa_type ) {
				get_template_part( 'template-parts/entity/province-identity' );
			} elseif ( 'city' === $sa_type ) {
				get_template_part( 'template-parts/entity/county-profile' );
			} elseif ( 'attraction' !== $sa_type ) {
				if ( has_excerpt() ) {
					echo '<p class="sa-entity__lead">' . esc_html( get_the_excerpt() ) . '</p>';
				}
				get_template_part( 'template-parts/entity/facts' );
			}
			?>

			<?php if ( 'attraction' === $sa_type && function_exists( 'sa_attraction_featured_figure' ) && function_exists( 'sa_attraction_images_visible' ) && sa_attraction_images_visible() ) : ?>
				<?php echo sa_attraction_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<div class="sa-entity__content entry-content">
				<?php the_content(); ?>
			</div>

			<?php sa_faq_section( $sa_id ); ?>

			<?php get_template_part( 'template-parts/entity/siblings' ); ?>

			<?php sa_related_articles_section( $sa_id ); ?>

			<?php sa_sources_section( $sa_id ); ?>
		</div>
	</div>
</article>
