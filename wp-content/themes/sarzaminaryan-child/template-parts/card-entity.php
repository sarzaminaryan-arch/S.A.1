<?php
/**
 * Card for any post type (expects query var sa_card_post or global post).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sa_post = get_query_var( 'sa_card_post' );
$sa_post = $sa_post instanceof WP_Post ? $sa_post : get_post();
if ( ! $sa_post ) {
	return;
}
$sa_type = $sa_post->post_type;
$sa_meta = sa_card_meta( $sa_post->ID );
?>
<article class="sa-card sa-card--<?php echo esc_attr( $sa_type ); ?>">
	<a class="sa-card__media" href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail( $sa_post ) ) : ?>
			<?php echo get_the_post_thumbnail( $sa_post, 'sarzaminaryan-card', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="sa-card__placeholder"><?php echo esc_html( mb_substr( get_the_title( $sa_post ), 0, 1 ) ); ?></span>
		<?php endif; ?>
		<span class="sa-card__badge"><?php echo esc_html( sa_type_badge( $sa_type ) ); ?></span>
	</a>
	<div class="sa-card__body">
		<h3 class="sa-card__title"><a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>"><?php echo esc_html( get_the_title( $sa_post ) ); ?></a></h3>
		<?php if ( $sa_meta ) : ?>
			<p class="sa-card__meta"><?php echo esc_html( $sa_meta ); ?></p>
		<?php endif; ?>
		<p class="sa-card__excerpt"><?php echo esc_html( sa_summary( $sa_post, 18 ) ); ?></p>
	</div>
</article>
<?php
set_query_var( 'sa_card_post', null );
