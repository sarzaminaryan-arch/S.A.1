<?php
/**
 * Source-aware, modular identity card for all published province pages.
 *
 * Modules can be removed or reordered with the `sa_province_identity_modules`
 * filter. Each section lives in its own template part so later edits stay local.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id                = get_the_ID();
$sa_slug              = sanitize_key( get_post_field( 'post_name', $sa_id ) );
$sa_profile           = sa_province_profile( $sa_slug );
$sa_meta              = sa_province_profile_metadata();
$sa_stats             = isset( $sa_profile['stats'] ) && is_array( $sa_profile['stats'] ) ? $sa_profile['stats'] : array();
$sa_counties          = sa_province_profile_county_posts( $sa_id );
$sa_catalog           = sa_province_profile_catalog();
$sa_center            = isset( $sa_catalog['by_slug'][ $sa_slug ]['center'] ) ? (string) $sa_catalog['by_slug'][ $sa_slug ]['center'] : '';
$sa_center_post       = $sa_center ? sa_province_profile_center_post( $sa_slug, $sa_counties ) : null;
$sa_geo_baseline      = function_exists( 'sa_region_province' ) ? sa_region_province( $sa_slug ) : null;
$sa_geo               = $sa_geo_baseline;
if ( function_exists( 'sa_region_map_enabled' ) && ! sa_region_map_enabled() ) {
	$sa_geo = null;
}
$sa_page_area         = get_post_meta( $sa_id, 'sa_province_area', true );
$sa_page_climate      = get_post_meta( $sa_id, 'sa_province_climate', true );
$sa_neighbors_diff    = sa_province_profile_neighbor_differences( $sa_slug, $sa_profile );
$sa_page_population   = get_post_meta( $sa_id, 'sa_province_population', true );
$sa_legacy_population = isset( $sa_stats['population'] ) && is_numeric( $sa_page_population ) && (int) $sa_page_population !== (int) $sa_stats['population']
	? (int) $sa_page_population
	: null;

if ( ! $sa_profile ) {
	return;
}

$sa_context = array(
	'post_id'             => $sa_id,
	'slug'                => $sa_slug,
	'profile'             => $sa_profile,
	'metadata'            => $sa_meta,
	'stats'               => $sa_stats,
	'county_posts'        => $sa_counties,
	'center_name'         => $sa_center,
	'center_post'         => $sa_center_post,
	'geo'                 => $sa_geo,
	'geo_baseline'        => $sa_geo_baseline,
	'page_area'           => $sa_page_area,
	'page_climate'        => $sa_page_climate,
	'neighbor_differences' => $sa_neighbors_diff,
	'legacy_population'   => $sa_legacy_population,
);
$sa_default_modules = array( 'stats', 'geography', 'county-links', 'sources' );
$sa_modules         = apply_filters( 'sa_province_identity_modules', $sa_default_modules, $sa_id, $sa_profile );
?>
<section class="sa-province-profile" id="province-profile" aria-labelledby="sa-province-profile-title">
	<header class="sa-province-profile__header">
		<div>
			<p class="sa-province-profile__eyebrow"><?php esc_html_e( 'نمای کلی جغرافیایی و آماری', 'sarzaminaryan-child' ); ?></p>
			<h2 class="sa-province-profile__title" id="sa-province-profile-title"><?php esc_html_e( 'شناسنامهٔ استان', 'sarzaminaryan-child' ); ?></h2>
			<p class="sa-province-profile__intro"><?php esc_html_e( 'آمار تقسیماتی، اطلاعات پایه و پیوندهای شهرستانی در یک بخش مستقل و قابل‌به‌روزرسانی.', 'sarzaminaryan-child' ); ?></p>
		</div>
		<span class="sa-province-profile__status"><?php esc_html_e( 'منبع ورودی در انتظار بازبینی', 'sarzaminaryan-child' ); ?></span>
	</header>

	<?php
	foreach ( (array) $sa_modules as $sa_module ) {
		$sa_module = sanitize_key( $sa_module );
		if ( ! in_array( $sa_module, $sa_default_modules, true ) ) {
			continue;
		}
		get_template_part( 'template-parts/entity/province-profile/' . $sa_module, null, $sa_context );
	}
	?>
</section>
