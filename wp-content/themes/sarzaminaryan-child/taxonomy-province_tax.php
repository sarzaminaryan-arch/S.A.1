<?php
/**
 * Province term archive: hub-like page grouping every entity type of the province.
 * If a Province post exists for the term, visitors are sent to it (single source of truth).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_term = get_queried_object();
$sa_hub  = sa_province_post_for_term( $sa_term );
if ( $sa_hub && ! is_paged() ) {
	wp_safe_redirect( get_permalink( $sa_hub ), 301 );
	exit;
}

get_header();
?>
<main id="primary" class="site-main container sa-archive sa-archive--province-term">
	<header class="sa-archive__head">
		<h1 class="sa-archive__title"><?php echo esc_html( sa_normalize_place_name( $sa_term->name, 'province', 'استان' ) ); ?></h1>
		<?php if ( $sa_term->description ) : ?>
			<div class="sa-archive__desc"><?php echo wp_kses_post( wpautop( $sa_term->description ) ); ?></div>
		<?php endif; ?>
	</header>
	<?php
	$sa_any = false;
	foreach ( array( 'city', 'attraction', 'local_food', 'souvenir', 'travel_route' ) as $sa_type ) {
		$sa_items = get_posts(
			array(
				'post_type'      => $sa_type,
				'posts_per_page' => 12,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => 'province_tax',
						'terms'    => $sa_term->term_id,
					),
				),
			)
		);
		if ( $sa_items ) {
			$sa_any = true;
			sa_cards_section( $sa_items, sa_entity_label( $sa_type, true ) . ' ' . sa_normalize_place_name( $sa_term->name, 'province', 'استان' ), '', $sa_type );
		}
	}
	if ( ! $sa_any ) {
		get_template_part( 'template-parts/content', 'none' );
	}
	?>
</main>
<?php
get_footer();
