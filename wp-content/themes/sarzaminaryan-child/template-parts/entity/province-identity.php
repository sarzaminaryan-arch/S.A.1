<?php
/**
 * Compact, bilingual visitor-facing profile for a province page.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id      = get_the_ID();
$sa_slug    = sanitize_key( get_post_field( 'post_name', $sa_id ) );
$sa_profile = sa_province_profile( $sa_slug );
if ( ! $sa_profile ) {
	return;
}

$sa_catalog         = sa_province_profile_catalog();
$sa_province_record = isset( $sa_catalog['by_slug'][ $sa_slug ] ) ? $sa_catalog['by_slug'][ $sa_slug ] : array();
$sa_name            = isset( $sa_profile['name'] ) ? (string) $sa_profile['name'] : get_the_title( $sa_id );
$sa_name_en         = isset( $sa_province_record['en'] ) ? (string) $sa_province_record['en'] : sa_province_profile_english_name( $sa_slug );
$sa_county_posts    = sa_province_profile_county_posts( $sa_id );
$sa_county_entries  = sa_province_profile_county_entries( $sa_slug, $sa_id, $sa_county_posts );
$sa_center          = isset( $sa_province_record['center'] ) ? (string) $sa_province_record['center'] : '';
$sa_center_post     = $sa_center ? sa_province_profile_center_post( $sa_slug, $sa_county_posts ) : null;
$sa_center_en       = $sa_center ? sa_province_profile_center_english( $sa_slug, $sa_center, $sa_center_post ) : '';
$sa_geo             = function_exists( 'sa_region_province' ) ? sa_region_province( $sa_slug ) : array();
$sa_page_area       = get_post_meta( $sa_id, 'sa_province_area', true );
$sa_page_climate    = trim( (string) get_post_meta( $sa_id, 'sa_province_climate', true ) );
$sa_area            = is_numeric( $sa_page_area ) && (float) $sa_page_area > 0
	? (float) $sa_page_area
	: ( isset( $sa_geo['area'] ) && is_numeric( $sa_geo['area'] ) ? (float) $sa_geo['area'] : null );
$sa_climate         = '' !== $sa_page_climate
	? $sa_page_climate
	: ( isset( $sa_geo['climate'] ) ? trim( (string) $sa_geo['climate'] ) : '' );
$sa_stats           = isset( $sa_profile['stats'] ) && is_array( $sa_profile['stats'] ) ? $sa_profile['stats'] : array();

$sa_context = array(
	'post_id'        => $sa_id,
	'slug'           => $sa_slug,
	'profile'        => $sa_profile,
	'stats'          => $sa_stats,
	'province_name'  => $sa_name,
	'province_english' => $sa_name_en,
	'county_entries' => $sa_county_entries,
	'center_name'    => $sa_center,
	'center_english' => $sa_center_en,
	'center_post'    => $sa_center_post,
	'area'           => $sa_area,
	'climate'        => $sa_climate,
);
$sa_default_modules = array( 'stats', 'geography', 'county-links' );
$sa_modules         = apply_filters( 'sa_province_identity_modules', $sa_default_modules, $sa_id, $sa_profile );
?>
<section class="sa-province-profile" id="province-profile" aria-labelledby="sa-province-profile-title">
	<header class="sa-province-profile__header">
		<div>
			<p class="sa-province-profile__eyebrow"><?php esc_html_e( 'شناسنامهٔ استان', 'sarzaminaryan-child' ); ?> <span lang="en">PROVINCE PROFILE</span></p>
			<h2 class="sa-province-profile__title" id="sa-province-profile-title"><?php echo esc_html( $sa_name ); ?></h2>
			<p class="sa-province-profile__english" lang="en" dir="ltr"><?php echo esc_html( $sa_name_en . ' Province' ); ?></p>
		</div>
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
