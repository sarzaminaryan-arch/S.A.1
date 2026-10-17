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
			$sa_stage_started = microtime( true );
			get_template_part( 'template-parts/entity/county-profile' );
			sa_county_timing( 'identity', $sa_stage_started );
		} elseif ( 'attraction' !== $sa_type ) {
			if ( has_excerpt() ) {
				echo '<p class="sa-entity__lead">' . esc_html( get_the_excerpt() ) . '</p>';
			}
			get_template_part( 'template-parts/entity/facts' );
		}
		?>

		<?php if ( 'city' === $sa_type && class_exists( 'CC_UI' ) ) : ?>
			<?php
			$sa_stage_started = microtime( true );
			CC_UI::rating_block();
			sa_county_timing( 'rating', $sa_stage_started );
			?>
		<?php endif; ?>

		<?php if ( 'attraction' === $sa_type && function_exists( 'sa_attraction_featured_figure' ) && function_exists( 'sa_attraction_images_visible' ) && sa_attraction_images_visible() ) : ?>
			<?php echo sa_attraction_featured_figure( $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>

		<?php
		if ( function_exists( 'sa_toc_register_tail' ) ) {
			sa_toc_register_tail( $sa_id );
		}
		?>

		<?php if ( 'city' === $sa_type ) {
			$sa_stage_started = microtime( true );
			sa_county_google_map( $sa_id );
			get_template_part( 'template-parts/entity/gallery' );
			sa_county_timing( 'map_gallery', $sa_stage_started );
		} ?>

		<div class="sa-entity__content entry-content">
			<?php the_content(); ?>
		</div>

		<?php if ( 'city' === $sa_type ) : ?>
			<?php
			$sa_county_parts = isset( $GLOBALS['sa_county_page_parts'][ $sa_id ] ) ? $GLOBALS['sa_county_page_parts'][ $sa_id ] : array();
			if ( ! empty( $sa_county_parts['facts'] ) ) {
				echo '<section class="sa-county-fun-facts" id="county-fun-facts"><h2>' . esc_html__( 'حقایق جالب', 'sarzaminaryan-child' ) . '</h2>' . wp_kses_post( $sa_county_parts['facts'] ) . '</section>';
			}
			if ( ! empty( $sa_county_parts['faq'] ) ) {
				echo '<section class="sa-faq-section" id="faq"><h2>' . esc_html__( 'سوالات متداول', 'sarzaminaryan-child' ) . '</h2>' . wp_kses_post( $sa_county_parts['faq'] ) . '</section>';
			} else {
				ob_start();
				get_template_part( 'template-parts/entity/faq' );
				echo wp_kses_post( sa_county_clean_references( ob_get_clean() ) );
			}
			if ( class_exists( 'CC_UI' ) ) {
				CC_UI::contrib_block();
			}
			sa_county_related_articles( $sa_id );
			$sa_stage_started = microtime( true );
			sa_entity_navigation( $sa_id, $sa_type );
			sa_county_timing( 'navigation', $sa_stage_started );
			$sa_stage_started = microtime( true );
			sa_county_sources_section( $sa_id );
			sa_county_timing( 'sources', $sa_stage_started );
			?>
		<?php else : ?>
		<?php get_template_part( 'template-parts/entity/faq' ); ?>

		<?php get_template_part( 'template-parts/entity/county-siblings' ); ?>

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
		if ( function_exists( 'sa_sources_section' ) ) {
			sa_sources_section( $sa_id );
		}
		if ( function_exists( 'sa_facts_checked_note' ) ) {
			sa_facts_checked_note( $sa_id );
		}
		?>
		<?php endif; ?>
	</div>

</article>
