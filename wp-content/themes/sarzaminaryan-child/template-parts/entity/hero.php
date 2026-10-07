<?php
/**
 * Entity hero: featured image + title + parent line.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_id       = get_the_ID();
$sa_type     = get_post_type();
if ( 'attraction' === $sa_type ) {
	get_template_part( 'template-parts/entity/identity-attraction' );
	return;
}
$sa_city     = sa_get_parent( $sa_id, 'city' );
$sa_province = sa_get_parent( $sa_id, 'province' );
$sa_title    = 'city' === $sa_type && function_exists( 'sa_county_name' ) ? sa_county_name( $sa_id, true ) : get_the_title( $sa_id );
$sa_kicker   = sa_entity_label( $sa_type );
if ( 'city' === $sa_type && $sa_province ) {
	$sa_kicker .= 'ی در استان ' . get_the_title( $sa_province );
} elseif ( $sa_city && ! in_array( $sa_type, array( 'province', 'city' ), true ) ) {
	$sa_kicker .= ' در ' . get_the_title( $sa_city ) . ( $sa_province ? '، استان ' . get_the_title( $sa_province ) : '' );
} elseif ( 'province' === $sa_type ) {
	$sa_kicker = 'راهنمای سفر به استان';
}
?>
<header class="sa-entity__hero<?php echo has_post_thumbnail() ? ' has-image' : ''; ?>">
	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="sa-entity__hero-media">
			<?php the_post_thumbnail( 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'sa-entity__hero-img' ) ); ?>
		</figure>
	<?php endif; ?>
	<?php if ( 'province' === $sa_type ) : ?>
		<?php $sa_province_cities = sa_get_children( $sa_id, 'city' ); ?>
		<?php if ( $sa_province_cities ) : ?>
			<nav class="sa-province-city-pills" aria-label="<?php echo esc_attr( 'شهرستان‌های ' . get_the_title() ); ?>">
				<div class="container sa-province-city-pills__inner">
					<span class="sa-province-city-pills__label"><?php esc_html_e( 'شهرستان‌ها', 'sarzaminaryan-child' ); ?></span>
					<?php foreach ( $sa_province_cities as $sa_city_item ) : ?>
						<a class="sa-province-city-pills__link" href="<?php echo esc_url( get_permalink( $sa_city_item ) ); ?>"><?php echo esc_html( function_exists( 'sa_county_name' ) ? sa_county_name( $sa_city_item, true ) : get_the_title( $sa_city_item ) ); ?></a>
					<?php endforeach; ?>
				</div>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
	<div class="container sa-entity__hero-text">
		<p class="sa-entity__kicker"><?php echo esc_html( $sa_kicker ); ?></p>
		<h1 class="entry-title sa-entity__title"><?php echo esc_html( $sa_title ); ?></h1>
		<?php
		$sa_terms_html = array();
		if ( 'city' === $sa_type && $sa_province ) {
			$sa_terms_html[] = '<a class="sa-chip" href="' . esc_url( get_permalink( $sa_province ) ) . '">' . esc_html( 'استان ' . get_the_title( $sa_province ) ) . '</a>';
		}
		foreach ( sa_entity( $sa_type )['taxonomies'] as $sa_tax ) {
			if ( 'province_tax' === $sa_tax && 'province' !== $sa_type ) {
				continue; // shown via parent line.
			}
			$sa_terms = get_the_terms( $sa_id, $sa_tax );
			if ( $sa_terms && ! is_wp_error( $sa_terms ) ) {
				foreach ( $sa_terms as $sa_t ) {
					$sa_terms_html[] = '<a class="sa-chip" href="' . esc_url( get_term_link( $sa_t ) ) . '">' . esc_html( $sa_t->name ) . '</a>';
				}
			}
		}
		if ( $sa_terms_html ) {
			echo '<p class="sa-entity__chips">' . implode( ' ', $sa_terms_html ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
	</div>
</header>
