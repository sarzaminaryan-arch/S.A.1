<?php
/**
 * Generic entity page (province/city/attraction/route/food/souvenir).
 *
 * v2.19.2:
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
<article id="post-<?php the_ID(); ?>" <?php post_class( 'sa-entity-page sa-entity sa-entity--' . esc_attr( $sa_type ) ); ?>>

	<?php get_template_part( 'template-parts/entity/hero' ); ?>

	<div class="container sa-entity__layout">
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

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::rating_block(); ?>
		<?php endif; ?>

		<?php if ( 'attraction' === $sa_type && function_exists( 'sa_attraction_featured_figure' ) && function_exists( 'sa_attraction_images_visible' ) && sa_attraction_images_visible() ) : ?>
			<?php echo sa_attraction_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>

		<?php
		if ( function_exists( 'sa_toc_register_tail' ) ) {
			sa_toc_register_tail( $sa_id );
		}
		?>

		<div class="sa-entity__content entry-content">
			<?php sa_article_content_with_sources(); ?>
		</div>

		<?php get_template_part( 'template-parts/entity/faq' ); ?>


		<?php get_template_part( 'template-parts/entity/gallery' ); ?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php CC_UI::contrib_block(); ?>
		<?php endif; ?>

		<?php
		if ( function_exists( 'sa_related_articles' ) ) {
			sa_related_articles( $sa_id, $sa_type );
		}
		if ( function_exists( 'sa_entity_navigation' ) ) {
			sa_entity_navigation( $sa_id, $sa_type );
		}
		if ( function_exists( 'sa_facts_checked_note' ) ) {
			sa_facts_checked_note( $sa_id );
		}
		sa_article_final_sources( $sa_id );
		?>
	</div>

</article>
