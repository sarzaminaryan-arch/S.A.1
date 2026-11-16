<?php
/**
 * Standalone test: نقشهٔ داخل بخش «کجاست؟» (2.22.87).
 *
 * Owner order (template analysis, 2026-11-16): the map is rendered BY THE
 * THEME inside the map section — a light facade that swaps to the provider
 * iframe on scroll — and is NEVER baked into post_content. This test pins:
 *   1. sa_geo_parse_coord — Persian digits, direction words, junk → float.
 *   2. sa_county_map_data — canonical → numeric legacy → display legacy;
 *      Iran bounds reject anything else (no map with bad data).
 *   3. sa_county_map_block — facade markup, خرجی links, noscript, caption.
 *   4. sa_county_map_content_filter — injects once, after the first </p> of
 *      the sa:sec:map section ONLY; nothing without the marker or the coords.
 *   5. FAQ under 3 valid rows carries no section and no schema (helpers gate).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-county-map.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array(
	'meta'   => array(),
	'posts'  => array(),
	'in_loop' => true,
	'main_q' => true,
);

function sa_cm_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_action() { return true; }
function add_filter() { return true; }
function apply_filters( $tag, $value = null ) { return $value; }
function is_admin() { return false; }
function is_singular( $type = null ) { return null === $type || 'city' === $type; }
function in_the_loop() { return $GLOBALS['t']['in_loop']; }
function is_main_query() { return $GLOBALS['t']['main_q']; }
function get_the_ID() { return 90; }
function get_post( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
}
function get_post_field( $field, $id ) {
	$p = get_post( $id );
	return $p && isset( $p->$field ) ? $p->$field : '';
}
function get_post_meta( $id, $key, $single = false ) {
	unset( $single );
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function get_option( $key, $default = false ) {
	return isset( $GLOBALS['t']['options'][ $key ] ) ? $GLOBALS['t']['options'][ $key ] : $default;
}
function get_the_title( $id = 0 ) {
	$p = get_post( $id );
	return $p ? $p->post_title : '';
}
function get_permalink( $id = 0 ) { return 'https://example.test/city/x/'; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }

// Minimal doubles for the data layer the map module leans on.
function sa_county_profile_data( $id ) {
	$row = isset( $GLOBALS['t']['profile'][ $id ] ) ? $GLOBALS['t']['profile'][ $id ] : array();
	return array_merge(
		array( 'lat' => null, 'lng' => null, 'center_fa' => '' ),
		$row
	);
}
function sa_get_coords( $id ) {
	$lat = get_post_meta( $id, 'sa_latitude', true );
	$lng = get_post_meta( $id, 'sa_longitude', true );
	if ( '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng ) ) {
		return array( (float) $lat, (float) $lng );
	}
	return null;
}
function sa_county_name( $id, $prefix = false ) {
	return $prefix ? 'شهرستان نمونه' : 'نمونه';
}

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/county-map.php';

$GLOBALS['t']['posts'][90] = (object) array( 'ID' => 90, 'post_type' => 'city', 'post_title' => 'شهرستان نمونه' );

/* ---- 1. Coordinate parser ---------------------------------------------- */

sa_cm_assert( 31.3275 === sa_geo_parse_coord( '31.3275' ), 'parse: plain number' );
sa_cm_assert( 31.3275 === sa_geo_parse_coord( '۳۱٫۳۲۷۵ شمالی' ), 'parse: persian digits + شمالی' );
sa_cm_assert( 48.69389 === sa_geo_parse_coord( '۴۸٫۶۹۳۸۹ شرقی' ), 'parse: persian digits + شرقی' );
sa_cm_assert( -29.5 === sa_geo_parse_coord( '29.5 جنوبی' ), 'parse: southern hemisphere goes negative' );
sa_cm_assert( null === sa_geo_parse_coord( 'متن بدون عدد' ), 'parse: junk is null' );
sa_cm_assert( null === sa_geo_parse_coord( '' ), 'parse: empty is null' );

/* ---- 2. Map data resolution + Iran bounds ------------------------------- */

// 2a. canonical profile keys win.
$GLOBALS['t']['profile'][90] = array( 'lat' => 31.3275, 'lng' => 48.69389, 'center_fa' => 'نمونه‌شهر' );
$map = sa_county_map_data( 90 );
sa_cm_assert( is_array( $map ) && 31.3275 === $map['lat'] && 48.69389 === $map['lng'], 'data: canonical coords used' );
sa_cm_assert( 'نمونه‌شهر' === $map['label'], 'data: label is the center city' );
sa_cm_assert( 10 === $map['zoom'], 'data: default zoom 10' );

// 2b. display-format legacy («۳۱٫۳۲۷۵ شمالی») is parsed, not rejected.
$GLOBALS['t']['profile'][90] = array();
$GLOBALS['t']['meta'][90]    = array(
	'sa_city_latitude'  => '۳۱٫۳۲۷۵ شمالی',
	'sa_city_longitude' => '۴۸٫۶۹۳۸۹ شرقی',
);
$map = sa_county_map_data( 90 );
sa_cm_assert( is_array( $map ) && 31.3275 === $map['lat'] && 48.69389 === $map['lng'], 'data: display-format legacy parsed' );

// 2c. outside Iran → no map at all (the «no data = no block» rule).
$GLOBALS['t']['meta'][90] = array( 'sa_latitude' => '51.5', 'sa_longitude' => '-0.12' ); // London.
sa_cm_assert( null === sa_county_map_data( 90 ), 'data: non-Iran coordinates produce no map' );

// 2d. zoom clamps into 8..13.
$GLOBALS['t']['meta'][90] = array(
	'sa_city_latitude' => '31.3275',
	'sa_city_longitude' => '48.69389',
	'sa_map_zoom'      => '99',
);
$GLOBALS['t']['profile'][90] = array();
$map = sa_county_map_data( 90 );
sa_cm_assert( is_array( $map ) && 10 === $map['zoom'], 'data: out-of-range zoom falls back to 10' );

/* ---- 3. Block markup ----------------------------------------------------- */

$GLOBALS['t']['profile'][90] = array( 'lat' => 31.3275, 'lng' => 48.69389, 'center_fa' => 'نمونه‌شهر' );
$GLOBALS['t']['meta'][90]    = array();
$GLOBALS['t']['options']     = array();
$block = sa_county_map_block( 90 );
sa_cm_assert( false !== strpos( $block, 'id="sa-map"' ), 'block: carries the #sa-map anchor' );
sa_cm_assert( false !== strpos( $block, 'sa-map__facade' ) && false !== strpos( $block, 'data-provider="osm"' ), 'block: facade with keyless default provider' );
sa_cm_assert( false !== strpos( $block, 'data-lat="31.3275"' ) && false !== strpos( $block, 'data-lng="48.69389"' ), 'block: coordinates as ASCII data attributes' );
sa_cm_assert( false !== strpos( $block, 'نشان' ) && false !== strpos( $block, 'بلد' ) && false !== strpos( $block, 'گوگل‌مپ' ), 'block: the three خرجی links' );
sa_cm_assert( false !== strpos( $block, 'rel="noopener nofollow"' ), 'block: خرجی links are noopener nofollow' );
sa_cm_assert( false !== strpos( $block, '<noscript>' ), 'block: noscript fallback carries the links' );
sa_cm_assert( false !== strpos( $block, 'balad.ir/location?latitude=31.3275&longitude=48.69389&zoom=10' ), 'block: balad deep link built from coordinates' );
sa_cm_assert( false === strpos( $block, '۳۱' ), 'block: no persian digits inside the machine-readable parts' );

/* ---- 4. Content filter --------------------------------------------------- */

$map_section = '<!-- sa:sec:map -->' . "\n" . '<!-- wp:paragraph --><p>پاراگراف نخست بخش نقشه.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:paragraph --><p>پاراگراف دوم.</p><!-- /wp:paragraph -->' . "\n" . '<!-- /sa:sec:map -->';
$body        = '<!-- sa:sec:intro --><p>مقدمه.</p><!-- /sa:sec:intro -->' . "\n\n" . $map_section . "\n\n" . '<!-- sa:sec:history --><p>تاریخ.</p><!-- /sa:sec:history -->';

$GLOBALS['sa_county_map_injected'] = false; // each scenario starts clean
$out = sa_county_map_content_filter( $body );
sa_cm_assert( false !== strpos( $out, 'id="sa-map"' ), 'filter: map injected' );
sa_cm_assert( ! empty( $GLOBALS['sa_county_map_printed'] ), 'filter: footer-script flag set' );
$pos_map    = strpos( $out, 'id="sa-map"' );
$pos_first  = strpos( $out, 'پاراگراف نخست بخش نقشه' );
$pos_second = strpos( $out, 'پاراگراف دوم' );
sa_cm_assert( $pos_first < $pos_map && $pos_map < $pos_second, 'filter: injected after the FIRST paragraph of the map section' );
$pos_history = strpos( $out, 'sa:sec:history' );
sa_cm_assert( $pos_map < $pos_history, 'filter: stays inside the map section' );
sa_cm_assert( 1 === substr_count( $out, 'id="sa-map"' ), 'filter: injected exactly once' );
sa_cm_assert( false === strpos( $body, 'sa-map' ), 'filter: the stored post_content is never touched' );

// 4b. no marker → no map (legacy pages keep their text untouched).
$GLOBALS['sa_county_map_injected'] = false;
$GLOBALS['sa_county_map_printed']  = false;
sa_cm_assert( false === strpos( sa_county_map_content_filter( '<p>بدون نشانگر.</p>' ), 'sa-map' ), 'filter: no marker, no map' );
sa_cm_assert( empty( $GLOBALS['sa_county_map_printed'] ), 'filter: no footer flag without a map' );

// 4c. no valid coordinates → no map, even with the marker.
$GLOBALS['t']['profile'][90] = array();
$GLOBALS['t']['meta'][90]    = array( 'sa_city_latitude' => 'بدون عدد' );
$GLOBALS['sa_county_map_injected'] = false;
$GLOBALS['sa_county_map_printed']  = false;
sa_cm_assert( false === strpos( sa_county_map_content_filter( $body ), 'sa-map' ), 'filter: no usable coordinates, no map' );
sa_cm_assert( empty( $GLOBALS['sa_county_map_printed'] ), 'filter: no footer flag without coordinates' );

// 4d. outside the loop → untouched (no double injection on excerpts etc.).
$GLOBALS['t']['profile'][90] = array( 'lat' => 31.3275, 'lng' => 48.69389 );
$GLOBALS['t']['meta'][90]    = array();
$GLOBALS['t']['in_loop']     = false;
$GLOBALS['sa_county_map_injected'] = false;
sa_cm_assert( false === strpos( sa_county_map_content_filter( $body ), 'sa-map' ), 'filter: outside the loop, no map' );
$GLOBALS['t']['in_loop'] = true;
$GLOBALS['t']['in_loop'] = true;

/* ---- 5. FAQ threshold is presentation AND schema ------------------------- */

// Static source check (same pattern as the other template tests): the ≥3 gate
// lives inside sa_get_valid_faq, so the visible FAQ section (faq.php) and the
// FAQPage node (schema.php) can never disagree.
$helpers_src = (string) file_get_contents( $theme_dir . '/inc/helpers.php' );
sa_cm_assert( false !== strpos( $helpers_src, 'count( $out ) < 3' ), 'faq: fewer than 3 valid rows means NO section and NO FAQPage (2.22.87)' );

// And the identity-card MAP button becomes the in-page anchor when the map renders.
$card_src = (string) file_get_contents( $theme_dir . '/template-parts/entity/county-profile.php' );
sa_cm_assert( false !== strpos( $card_src, 'href="#sa-map"' ), 'card: map button anchors to the in-page map (2.22.87)' );
sa_cm_assert( false !== strpos( $card_src, "sa_county_map_data( \$sa_id )" ), 'card: anchor only when coordinates are valid' );

echo "PASS: county map (facade block, provider links, parse + Iran bounds, one injection per map section, never stored)\n";
