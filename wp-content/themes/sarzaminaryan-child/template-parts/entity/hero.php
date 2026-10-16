<?php
/**
 * Entity header dispatcher (v2.19.1).
 *
 * - Attractions render the full-width Cyber-Tech Attraction Identity Card (`identity-attraction.php`).
 * - Provinces and Counties render the breadcrumb bar here, followed immediately by their
 *   dedicated Cyber-Tech Identity Card (`province-identity.php` / `county-profile.php`) at the
 *   top of `.sa-entity__main` without duplicating titles or key facts.
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
	?>
	<div class="sa-id-card__crumbs sa-id-card__crumbs--<?php echo esc_attr( $sa_type ); ?>">
		<div class="sa-container">
			<?php sa_breadcrumbs(); ?>
		</div>
	</div>
	<?php
	return;
}

$sa_label = sa_entity_label( $sa_type );
$sa_en    = get_post_meta( $sa_id, 'sa_english_name', true );
$sa_thumb = sa_featured_image_available( $sa_id, 'hero' );
?>
<header class="sa-entity-hero sa-entity-hero--<?php echo esc_attr( $sa_type ); ?><?php echo $sa_thumb ? ' has-media' : ''; ?>">
	<?php if ( $sa_thumb ) : ?>
		<div class="sa-entity-hero__media" aria-hidden="true">
			<?php echo sa_get_featured_image( 'sa-hero', array( 'loading' => 'eager', 'fetchpriority' => 'high' ), $sa_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="sa-entity-hero__scrim"></span>
		</div>
	<?php endif; ?>

	<div class="sa-container sa-entity-hero__inner">
		<?php sa_breadcrumbs(); ?>

		<div class="sa-entity-hero__meta">
			<span class="sa-badge sa-badge--<?php echo esc_attr( $sa_type ); ?>"><?php echo esc_html( $sa_label ); ?></span>
			<?php sa_updated_badge( $sa_id ); ?>
		</div>

		<h1 class="sa-entity-hero__title">
			<?php the_title(); ?>
			<?php if ( $sa_en ) : ?><small class="sa-entity-hero__en" dir="ltr"><?php echo esc_html( $sa_en ); ?></small><?php endif; ?>
		</h1>
	</div>
</header>
