<?php
/**
 * Card for any post type (expects query var sa_card_post or global post).
 *
 * v2.11.24: «نمای برتر» (attraction) cards are minimal text tiles — no featured
 * image and no diagram art. The whole box is the link to the entity page.
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

if ( 'attraction' === $sa_type ) {
	$sa_english = trim( (string) get_post_meta( $sa_post->ID, 'sa_english_name', true ) );
	$sa_name    = get_the_title( $sa_post );
	$sa_alt     = '';
	// «تالاب ازگن (قارون)» → main name + smaller second name underneath.
	if ( preg_match( '/^(.+?)\s*[\(\（](.+?)[\)\）]\s*$/u', $sa_name, $sa_m ) ) {
		$sa_name = trim( $sa_m[1] );
		$sa_alt  = trim( $sa_m[2] );
	}
	// Vivid per-tile accent for the English line only. A static counter walks the
	// palette with a coprime step, so neighbouring boxes never repeat a color and
	// the same page always looks the same.
	static $sa_tile_i = 0;
	$sa_palette = array(
		'#c1121f', '#d9480f', '#e8590c', '#b5179e', '#7048e8', '#1864ab',
		'#0b7285', '#2b8a3e', '#5c940d', '#a95200', '#862e9c', '#c2255c',
	);
	$sa_accent = $sa_palette[ ( $sa_tile_i * 5 + 1 ) % count( $sa_palette ) ];
	$sa_tile_i++;
	$sa_city    = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_post->ID, 'city' ) : null;
	$sa_prov    = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_post->ID, 'province' ) : null;
	$sa_place   = array();
	if ( $sa_city ) {
		$sa_place[] = sa_entity_display_name( $sa_city, 'شهرستان' );
	}
	if ( $sa_prov ) {
		$sa_place[] = sa_entity_display_name( $sa_prov, 'استان' );
	}
	?>
	<article class="sa-card sa-card--attraction sa-tile">
		<a class="sa-tile__link" href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>" style="--sa-tile-accent:<?php echo esc_attr( $sa_accent ); ?>">
			<span class="sa-tile__badge"><?php echo esc_html( sa_entity_label( 'attraction' ) ); ?></span>
			<span class="sa-tile__title"><?php echo esc_html( $sa_name ); ?></span>
			<?php if ( $sa_alt ) : ?>
				<span class="sa-tile__alt"><?php echo esc_html( $sa_alt ); ?></span>
			<?php endif; ?>
			<?php if ( $sa_english ) : ?>
				<span class="sa-tile__english" dir="ltr" lang="en"><?php echo esc_html( $sa_english ); ?></span>
			<?php endif; ?>
			<?php if ( $sa_place ) : ?>
				<span class="sa-tile__place"><?php echo esc_html( implode( ' · ', $sa_place ) ); ?></span>
			<?php endif; ?>
		</a>
	</article>
	<?php
	set_query_var( 'sa_card_post', null );
	return;
}

$sa_meta = sa_card_meta( $sa_post->ID );
?>
<article class="sa-card sa-card--<?php echo esc_attr( $sa_type ); ?>">
	<a class="sa-card__media" href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( 'attraction' === $sa_type && function_exists( 'sa_attraction_diagram_markup' ) ) : ?>
			<?php echo sa_attraction_diagram_markup( $sa_post->ID, 'card' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php elseif ( has_post_thumbnail( $sa_post ) ) : ?>
			<?php echo get_the_post_thumbnail( $sa_post, 'sarzaminaryan-card', array( 'loading' => 'lazy' ) ); ?>
		<?php else : ?>
			<span class="sa-card__placeholder"><?php echo esc_html( mb_substr( sa_entity_display_name( $sa_post ), 0, 1 ) ); ?></span>
		<?php endif; ?>
		<span class="sa-card__badge"><?php echo esc_html( sa_type_badge( $sa_type ) ); ?></span>
	</a>
	<div class="sa-card__body">
		<h3 class="sa-card__title"><a href="<?php echo esc_url( get_permalink( $sa_post ) ); ?>"><?php echo esc_html( sa_entity_display_name( $sa_post ) ); ?></a></h3>
		<?php if ( $sa_meta ) : ?>
			<p class="sa-card__meta"><?php echo esc_html( $sa_meta ); ?></p>
		<?php endif; ?>
		<p class="sa-card__excerpt"><?php echo esc_html( sa_summary( $sa_post, 18 ) ); ?></p>
	</div>
</article>
<?php
set_query_var( 'sa_card_post', null );
