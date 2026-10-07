<?php
/**
 * Identity-card hero for «نمای برتر» pages.
 *
 * v2.11.25: the featured/diagram card was removed; the identity card now fills
 * the full width and is fully responsive on its own.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id       = get_the_ID();
$sa_city     = sa_get_parent( $sa_id, 'city' );
$sa_province = sa_get_parent( $sa_id, 'province' );
$sa_city_name = $sa_city ? ( function_exists( 'sa_county_name' ) ? sa_county_name( $sa_city ) : get_the_title( $sa_city ) ) : '';
$sa_english  = trim( (string) get_post_meta( $sa_id, 'sa_english_name', true ) );
if ( '' === $sa_english ) {
	$sa_english = strtoupper( str_replace( '-', ' ', get_post_field( 'post_name', $sa_id ) ) );
}
$sa_types   = get_the_terms( $sa_id, 'attraction_type' );
$sa_seasons = get_the_terms( $sa_id, 'travel_season' );
$sa_type    = ( $sa_types && ! is_wp_error( $sa_types ) ) ? $sa_types[0]->name : 'نمای طبیعی';
$sa_season  = ( $sa_seasons && ! is_wp_error( $sa_seasons ) ) ? $sa_seasons[0]->name : '';
$sa_fields  = array(
	'نام انگلیسی'     => $sa_english,
	'استان'           => $sa_province ? get_the_title( $sa_province ) : '',
	'شهرستان'         => $sa_city_name,
	'نوع نما'          => $sa_type,
	'بهترین زمان'      => $sa_season,
	'قدمت'             => get_post_meta( $sa_id, 'sa_attraction_age', true ),
	'مساحت/گستره'      => get_post_meta( $sa_id, 'sa_attraction_area', true ),
	'ارتفاع'           => get_post_meta( $sa_id, 'sa_elevation', true ) ? sa_number( get_post_meta( $sa_id, 'sa_elevation', true ) ) . ' متر' : '',
	'سطح دسترسی'       => get_post_meta( $sa_id, 'sa_access_level', true ),
	'مسیر پیاده‌روی'   => get_post_meta( $sa_id, 'sa_trail_note', true ),
	'مدت بازدید'       => get_post_meta( $sa_id, 'sa_visit_duration', true ),
);
$sa_map_url = sa_map_url( $sa_id );
?>
<header class="sa-attraction-id" aria-label="<?php echo esc_attr( 'شناسنامه نمای برتر ' . get_the_title() ); ?>">
	<div class="container">
		<div class="sa-attraction-id__card">
			<div class="sa-attraction-id__body">
				<p class="sa-attraction-id__eyebrow"><span></span><?php esc_html_e( 'شناسنامه نمای برتر', 'sarzaminaryan-child' ); ?></p>
				<h1 class="entry-title sa-attraction-id__title"><?php the_title(); ?></h1>
				<?php if ( $sa_english ) : ?>
					<p class="sa-attraction-id__latin" dir="ltr"><?php echo esc_html( $sa_english ); ?></p>
				<?php endif; ?>
				<?php if ( has_excerpt() ) : ?>
					<p class="sa-attraction-id__summary"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
				<div class="sa-attraction-id__facts">
					<?php foreach ( $sa_fields as $sa_label => $sa_value ) : ?>
						<?php if ( '' === trim( (string) $sa_value ) ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<div class="sa-attraction-id__fact">
							<span><?php echo esc_html( $sa_label ); ?></span>
							<strong><?php echo esc_html( sa_digits( $sa_value ) ); ?></strong>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="sa-attraction-id__actions">
					<?php if ( $sa_city ) : ?>
						<a class="sa-attraction-id__btn" href="<?php echo esc_url( get_permalink( $sa_city ) ); ?>"><?php echo esc_html( 'صفحه شهرستان ' . $sa_city_name ); ?></a>
					<?php endif; ?>
					<?php if ( $sa_province ) : ?>
						<a class="sa-attraction-id__btn sa-attraction-id__btn--ghost" href="<?php echo esc_url( get_permalink( $sa_province ) ); ?>"><?php echo esc_html( 'صفحه استان ' . get_the_title( $sa_province ) ); ?></a>
					<?php endif; ?>
					<?php if ( $sa_map_url ) : ?>
						<a class="sa-attraction-id__btn sa-attraction-id__btn--ghost" href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener">مشاهده روی نقشه</a>
					<?php endif; ?>
				</div>
				<?php $sa_safety_note = trim( (string) get_post_meta( $sa_id, 'sa_safety_note', true ) ); ?>
				<?php if ( $sa_safety_note ) : ?>
					<p class="sa-attraction-id__safety"><b>نکته ایمنی:</b> <?php echo esc_html( $sa_safety_note ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
</header>
