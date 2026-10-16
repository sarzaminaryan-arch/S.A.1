<?php
/**
 * Cyber-Tech Light-Mode Attraction Identity Card — «شناسنامه دیدنی» (v2.19.1).
 *
 * Dedicated Emerald-Teal technological 2-tier telemetry card for attractions (`attraction` CPT).
 * Renders 12 fixed vital slots in a custom bordered cyber grid (Tier A: 4-col identity strip,
 * Tier B: 3x2 technical telemetry, Tier C: full-width route & safety protocol rows), preserving
 * a 2-column tabular grid on mobile screens. Any unpopulated slot displays a tiny
 * «نیازمند بازبینی» badge.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id   = get_the_ID();
$sa_slug = (string) get_post_field( 'post_name', $sa_id );

if ( function_exists( 'sa_toc_register' ) ) {
	sa_toc_register( 'شناسنامه دیدنی', 'attraction-identity', 5 );
}

$sa_fb      = function_exists( 'sa_cyber_id_attraction_fallback' ) ? sa_cyber_id_attraction_fallback( $sa_id ) : array();
$sa_fb_meta = isset( $sa_fb['meta'] ) && is_array( $sa_fb['meta'] ) ? $sa_fb['meta'] : array();

$sa_get_meta = function ( $key ) use ( $sa_id, $sa_fb_meta ) {
	$val = trim( (string) get_post_meta( $sa_id, $key, true ) );
	if ( '' === $val && isset( $sa_fb_meta[ $key ] ) ) {
		$val = trim( (string) $sa_fb_meta[ $key ] );
	}
	return $val;
};

// 1) English Name.
$sa_en = $sa_get_meta( 'sa_english_name' );
if ( '' === $sa_en && ! empty( $sa_fb['english'] ) ) {
	$sa_en = trim( (string) $sa_fb['english'] );
}
if ( '' === $sa_en && ! empty( $sa_fb['short_en'] ) ) {
	$sa_en = trim( (string) $sa_fb['short_en'] );
}
if ( '' === $sa_en && function_exists( 'sa_province_profile_english_name' ) ) {
	$sa_en = sa_province_profile_english_name( $sa_slug );
}

// 2) Province & County (Persian + English, linked).
$sa_province_id = function_exists( 'sa_get_attraction_province' ) ? (int) sa_get_attraction_province( $sa_id ) : 0;
$sa_city_id     = function_exists( 'sa_get_attraction_city' ) ? (int) sa_get_attraction_city( $sa_id ) : 0;

$sa_prov_slug = $sa_province_id ? (string) get_post_field( 'post_name', $sa_province_id ) : ( ! empty( $sa_fb['province'] ) ? (string) $sa_fb['province'] : '' );
$sa_prov_name = $sa_province_id
	? ( function_exists( 'sa_province_name_for_post' ) ? sa_province_name_for_post( $sa_province_id, false ) : get_the_title( $sa_province_id ) )
	: ( ! empty( $sa_fb['province_name'] ) ? (string) $sa_fb['province_name'] : '' );
$sa_prov_en   = ! empty( $sa_fb['province_en'] )
	? (string) $sa_fb['province_en']
	: ( '' !== $sa_prov_slug && function_exists( 'sa_cyber_id_province_en' ) ? sa_cyber_id_province_en( $sa_prov_slug ) : '' );
$sa_prov_url  = ( $sa_province_id && 'publish' === get_post_status( $sa_province_id ) ) ? get_permalink( $sa_province_id ) : '';

$sa_city_slug = $sa_city_id ? (string) get_post_field( 'post_name', $sa_city_id ) : ( ! empty( $sa_fb['city'] ) ? (string) $sa_fb['city'] : '' );
$sa_city_name = $sa_city_id
	? ( function_exists( 'sa_county_name' ) ? sa_county_name( $sa_city_id, false ) : get_the_title( $sa_city_id ) )
	: ( ! empty( $sa_fb['city_name'] ) ? (string) $sa_fb['city_name'] : '' );
$sa_city_en   = ! empty( $sa_fb['city_en'] )
	? (string) $sa_fb['city_en']
	: ( '' !== $sa_city_slug && function_exists( 'sa_province_profile_english_name' ) ? sa_province_profile_english_name( $sa_city_slug ) : '' );
$sa_city_url  = ( $sa_city_id && 'publish' === get_post_status( $sa_city_id ) ) ? get_permalink( $sa_city_id ) : '';

$sa_loc_parts = array();
if ( '' !== $sa_prov_name ) {
	$sa_p_lbl = 'استان ' . esc_html( $sa_prov_name ) . ( '' !== $sa_prov_en ? ' <span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( $sa_prov_en ) . ')</span>' : '' );
	$sa_loc_parts[] = $sa_prov_url ? '<a class="sa-cyber-id__cell-link" href="' . esc_url( $sa_prov_url ) . '">' . $sa_p_lbl . '</a>' : $sa_p_lbl;
}
if ( '' !== $sa_city_name ) {
	$sa_c_lbl = 'شهرستان ' . esc_html( $sa_city_name ) . ( '' !== $sa_city_en ? ' <span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( $sa_city_en ) . ')</span>' : '' );
	$sa_loc_parts[] = $sa_city_url ? '<a class="sa-cyber-id__cell-link" href="' . esc_url( $sa_city_url ) . '">' . $sa_c_lbl . '</a>' : $sa_c_lbl;
}
$sa_loc_html = implode( ' · ', $sa_loc_parts );

// 3) Attraction Type & 4) Best Season.
$sa_term_names = function ( $taxonomy ) use ( $sa_id, $sa_fb ) {
	$terms = get_the_terms( $sa_id, $taxonomy );
	if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		return implode( '، ', wp_list_pluck( $terms, 'name' ) );
	}
	if ( ! empty( $sa_fb['terms'][ $taxonomy ] ) && is_array( $sa_fb['terms'][ $taxonomy ] ) ) {
		return implode( '، ', array_values( $sa_fb['terms'][ $taxonomy ] ) );
	}
	return '';
};
$sa_kind_val   = $sa_term_names( 'attraction_type' );
$sa_season_val = $sa_term_names( 'travel_season' );

// 5-12) Technical Telemetry Fields.
$sa_age_val      = $sa_get_meta( 'sa_attraction_age' );
$sa_area_val     = $sa_get_meta( 'sa_attraction_area' );
$sa_elev_raw     = $sa_get_meta( 'sa_elevation' );
$sa_elev_val     = '' !== $sa_elev_raw ? ( sa_fa_digits( $sa_elev_raw ) . ( false === mb_strpos( $sa_elev_raw, 'متر' ) ? ' متر' : '' ) ) : '';
$sa_duration_val = $sa_get_meta( 'sa_visit_duration' );
$sa_access_val   = $sa_get_meta( 'sa_access_level' );
$sa_trail_val    = $sa_get_meta( 'sa_trail_note' );
$sa_safety_val   = $sa_get_meta( 'sa_safety_note' );

$sa_lat        = $sa_get_meta( 'sa_latitude' );
$sa_lng        = $sa_get_meta( 'sa_longitude' );
$sa_coords_str = ( '' !== $sa_lat && '' !== $sa_lng ) ? ( $sa_lat . '° N, ' . $sa_lng . '° E' ) : '';
$sa_map_url    = $sa_get_meta( 'sa_google_map_url' );
if ( '' === $sa_map_url && '' !== $sa_lat && '' !== $sa_lng ) {
	$sa_map_url = 'https://www.google.com/maps?q=' . rawurlencode( $sa_lat . ',' . $sa_lng );
}

$sa_tier_a_cells = array(
	array(
		'code'  => '01 // EN_NAME',
		'label' => 'نام انگلیسی مکان',
		'value' => '' !== $sa_en ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_en ) . '</span>' : '',
		'html'  => true,
	),
	array(
		'code'  => '02 // LOCATION',
		'label' => 'استان و شهرستان',
		'value' => $sa_loc_html,
		'html'  => true,
	),
	array(
		'code'  => '03 // CATEGORY',
		'label' => 'نوع جاذبه',
		'value' => $sa_kind_val,
		'html'  => false,
	),
	array(
		'code'  => '04 // BEST_SEASON',
		'label' => 'بهترین فصل بازدید',
		'value' => $sa_season_val,
		'html'  => false,
	),
);

$sa_tier_b_cells = array(
	array(
		'code'  => '05 // ERA_AGE',
		'label' => 'قدمت / دوره',
		'value' => $sa_age_val,
		'html'  => false,
	),
	array(
		'code'  => '06 // AREA_SCALE',
		'label' => 'مساحت / گستره',
		'value' => $sa_area_val,
		'html'  => false,
	),
	array(
		'code'  => '07 // ELEVATION',
		'label' => 'ارتفاع از سطح دریا',
		'value' => $sa_elev_val,
		'html'  => false,
	),
	array(
		'code'  => '08 // DURATION',
		'label' => 'مدت زمان بازدید',
		'value' => $sa_duration_val,
		'html'  => false,
	),
	array(
		'code'  => '09 // ACCESS_LEVEL',
		'label' => 'سطح دشواری و دسترسی',
		'value' => $sa_access_val,
		'html'  => false,
	),
	array(
		'code'  => '10 // GPS_COORDS',
		'label' => 'مختصات جغرافیایی (GPS)',
		'value' => '' !== $sa_coords_str ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_coords_str ) . '</span>' : '',
		'html'  => true,
	),
);

$sa_en_loc_ribbon = implode(
	' // ',
	array_filter(
		array(
			'' !== $sa_city_en ? strtoupper( $sa_city_en ) . ' COUNTY' : '',
			'' !== $sa_prov_en ? strtoupper( $sa_prov_en ) . ' PROVINCE' : '',
		),
		'strlen'
	)
);
?>
<header class="sa-id-card-wrap">
	<div class="sa-id-card__crumbs sa-id-card__crumbs--attraction">
		<div class="sa-container">
			<?php sa_breadcrumbs(); ?>
		</div>
	</div>

	<div class="sa-container">
		<section class="sa-cyber-id sa-cyber-id--attraction" id="attraction-identity" aria-labelledby="sa-attraction-identity-title">
			<div class="sa-cyber-id__hud-bar">
				<div class="sa-cyber-id__badges">
					<span class="sa-cyber-id__badge">شناسنامه</span>
					<span class="sa-cyber-id__type-tag">شناسنامه دیدنی</span>
					<span class="sa-cyber-id__sys-code" dir="ltr">SYS.ID // ATR-<?php echo esc_html( strtoupper( $sa_slug ? $sa_slug : 'IR' ) ); ?></span>
				</div>
				<div class="sa-cyber-id__hud-meta">
					<?php if ( '' !== $sa_city_url ) : ?>
						<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_city_url ); ?>">
							<span>شهرستان <?php echo esc_html( $sa_city_name ); ?></span>
							<?php if ( '' !== $sa_city_en ) : ?><small dir="ltr"><?php echo esc_html( strtoupper( $sa_city_en ) ); ?></small><?php endif; ?>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $sa_prov_url ) : ?>
						<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_prov_url ); ?>">
							<span>استان <?php echo esc_html( $sa_prov_name ); ?></span>
							<?php if ( '' !== $sa_prov_en ) : ?><small dir="ltr"><?php echo esc_html( strtoupper( $sa_prov_en ) ); ?></small><?php endif; ?>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $sa_map_url ) : ?>
						<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener noreferrer">
							<span>نقشه گوگل</span>
							<small dir="ltr">GPS</small>
						</a>
					<?php endif; ?>
					<?php sa_updated_badge( $sa_id ); ?>
				</div>
			</div>

			<div class="sa-cyber-id__header">
				<div class="sa-cyber-id__title-group">
					<h1 class="sa-cyber-id__title" id="sa-attraction-identity-title"><?php the_title(); ?></h1>
					<?php if ( '' !== $sa_en ) : ?>
						<span class="sa-cyber-id__en-pill" lang="en" dir="ltr"><?php echo esc_html( strtoupper( $sa_en ) ); ?></span>
					<?php endif; ?>
					<?php if ( '' !== $sa_en_loc_ribbon ) : ?>
						<span class="sa-cyber-id__loc-ribbon" lang="en" dir="ltr"><?php echo esc_html( $sa_en_loc_ribbon ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( has_excerpt( $sa_id ) ) : ?>
					<p class="sa-cyber-id__summary"><?php echo esc_html( get_the_excerpt( $sa_id ) ); ?></p>
				<?php endif; ?>
			</div>

			<div class="sa-cyber-id__table-wrap" role="region" aria-label="جدول شناسنامه دیدنی">
				<div class="sa-cyber-grid sa-cyber-grid--attraction-top">
					<?php foreach ( $sa_tier_a_cells as $sa_cell ) : ?>
						<div class="sa-cyber-grid__cell">
							<span class="sa-cyber-grid__code" dir="ltr"><?php echo esc_html( $sa_cell['code'] ); ?></span>
							<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_cell['label'] ); ?></span>
							<strong class="sa-cyber-grid__value">
								<?php echo sa_cyber_id_value_or_review( $sa_cell['value'], ! empty( $sa_cell['html'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</strong>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="sa-cyber-grid sa-cyber-grid--attraction-specs">
					<?php foreach ( $sa_tier_b_cells as $sa_cell ) : ?>
						<div class="sa-cyber-grid__cell">
							<span class="sa-cyber-grid__code" dir="ltr"><?php echo esc_html( $sa_cell['code'] ); ?></span>
							<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_cell['label'] ); ?></span>
							<strong class="sa-cyber-grid__value">
								<?php echo sa_cyber_id_value_or_review( $sa_cell['value'], ! empty( $sa_cell['html'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</strong>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="sa-cyber-grid sa-cyber-grid--attraction-footer">
					<div class="sa-cyber-grid__cell sa-cyber-grid__cell--route">
						<span class="sa-cyber-grid__code" dir="ltr">11 // ROUTE_SPEC</span>
						<span class="sa-cyber-grid__label">مسیر و نحوهٔ دسترسی</span>
						<strong class="sa-cyber-grid__value">
							<?php echo sa_cyber_id_value_or_review( $sa_trail_val, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</strong>
					</div>
					<div class="sa-cyber-grid__cell sa-cyber-grid__cell--safety">
						<span class="sa-cyber-grid__code" dir="ltr">12 // SAFETY_PROTOCOL</span>
						<span class="sa-cyber-grid__label">نکتهٔ حیاتی و ایمنی بازدید</span>
						<strong class="sa-cyber-grid__value">
							<?php echo sa_cyber_id_value_or_review( $sa_safety_val, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</strong>
					</div>
				</div>
			</div>
		</section>
	</div>
</header>
