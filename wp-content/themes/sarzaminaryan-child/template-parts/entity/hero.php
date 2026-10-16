<?php
/**
 * Entity header dispatcher (v2.19.2).
 *
 * Note: `header.php` already prints `<div class="container"><?php sa_breadcrumbs(); ?></div>`
 * on all non-front pages, so entity headers must not print a duplicate breadcrumb bar.
 *
 * - Attractions render the full-width Cyber-Tech Attraction Identity Card (`identity-attraction.php`).
 * - Provinces and Counties render their dedicated Cyber-Tech Identity Card
 *   (`province-identity.php` / `county-profile.php`) at the top of `.sa-entity__layout`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_type = get_post_type( $sa_id );

if ( 'attraction' === $sa_type ) {
	get_template_part( 'template-parts/entity/identity-attraction' );
	return;
}

if ( in_array( $sa_type, array( 'province', 'city' ), true ) ) {
	return;
}

$sa_city       = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_id, 'city' ) : null;
$sa_province   = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_id, 'province' ) : null;
$sa_title      = get_the_title( $sa_id );
$sa_kicker     = function_exists( 'sa_entity_label' ) ? sa_entity_label( $sa_type ) : '';
$sa_hero_image = function_exists( 'sa_featured_image_available' ) && sa_featured_image_available( $sa_id, 'hero' );
?>
<header class="sa-entity__hero<?php echo $sa_hero_image ? ' has-image' : ''; ?>">
	<?php if ( $sa_hero_image ) : ?>
		<figure class="sa-entity__hero-media">
			<?php the_post_thumbnail( 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'class' => 'sa-entity__hero-img' ) ); ?>
		</figure>
	<?php endif; ?>
	<div class="container sa-entity__hero-text">
		<?php if ( $sa_kicker ) : ?>
			<p class="sa-entity__kicker"><?php echo esc_html( $sa_kicker ); ?></p>
		<?php endif; ?>
		<h1 class="entry-title sa-entity__title"><?php echo esc_html( $sa_title ); ?></h1>
	</div>
</header>
