<?php
/**
 * Standalone tests for the county identity card data layer
 * (inc/county-profile.php).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-county-profile.php
 *
 * Covers the phase-1 rules: canonical keys win over legacy ones, empty
 * fields stay empty (so the template can omit their tiles), neighbours
 * link only when a county page exists, invalid coordinates produce no
 * map link, and the empty-field report lists exactly the missing keys.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

$GLOBALS['t'] = array(
	'meta'  => array(),
	'posts' => array(),
);

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function sa_t_eq( $expected, $actual, $message ) {
	sa_t_assert( $expected === $actual, $message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

/* ---- WordPress shims. ---- */
function add_action() { return true; }
function add_filter() { return true; }
function get_post_meta( $id, $key, $single = false ) {
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function get_post( $id ) {
	$id = is_object( $id ) ? $id->ID : (int) $id;
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
}
function get_post_type( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_type : null;
}
function get_the_title( $id ) {
	$post = get_post( $id );
	return $post ? $post->post_title : '';
}
function get_permalink( $id ) {
	$post = get_post( is_object( $id ) ? $id->ID : $id );
	return $post ? 'https://example.test/' . $post->post_type . '/' . $post->post_name . '/' : '';
}
function get_page_by_path( $path, $output = null, $type = 'page' ) {
	foreach ( $GLOBALS['t']['posts'] as $post ) {
		if ( $post->post_name === $path && $post->post_type === $type ) {
			return $post;
		}
	}
	return null;
}
function sanitize_title( $title ) { return trim( (string) $title ); }

/* ---- Theme helper shims (mirroring geo-counties.php semantics). ---- */
function sa_county_name( $post_or_id, $include_prefix = false ) {
	$name = get_the_title( $post_or_id );
	return $include_prefix && '' !== $name ? 'شهرستان ' . $name : $name;
}
function sa_province_name_for_post( $post_or_id ) {
	return get_the_title( $post_or_id );
}
function sa_counties() {
	return array(
		array( 'slug' => 'dorud', 'name' => 'دورود', 'province' => 'lorestan', 'status' => 'empty' ),
		array( 'slug' => 'azna', 'name' => 'ازنا', 'province' => 'lorestan', 'status' => 'empty' ),
		array( 'slug' => 'borujerd', 'name' => 'بروجرد', 'province' => 'lorestan', 'status' => 'empty' ),
	);
}
function sa_county( $slug ) {
	foreach ( sa_counties() as $row ) {
		if ( $row['slug'] === $slug ) {
			return $row;
		}
	}
	return null;
}
function sa_county_of_post( $post_id ) {
	$post = get_post( $post_id );
	return $post ? sa_county( $post->post_name ) : null;
}
function sa_county_permalink( $slug ) {
	foreach ( $GLOBALS['t']['posts'] as $post ) {
		if ( 'city' === $post->post_type && $post->post_name === $slug && 'publish' === $post->post_status ) {
			return 'https://example.test/city/' . $slug . '/';
		}
	}
	return '';
}
function sa_county_lines( $raw ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	return array_values( array_filter( array_map( 'trim', (array) $lines ) ) );
}
function sa_county_parse_neighbor( $line ) {
	$parts = preg_split( '/\s*[:：]\s*/', trim( (string) $line ), 2 );
	if ( empty( $parts[1] ) ) {
		return null;
	}
	$url = '';
	foreach ( sa_counties() as $row ) {
		if ( $row['name'] === trim( $parts[1] ) ) {
			$url = sa_county_permalink( $row['slug'] );
		}
	}
	return array( 'dir' => trim( $parts[0] ), 'label' => trim( $parts[1] ), 'url' => $url );
}
function sa_get_parent( $id, $type ) { return null; }

function sa_t_post( $id, $type, $slug, $title, $status = 'publish' ) {
	$post              = new stdClass();
	$post->ID          = $id;
	$post->post_type   = $type;
	$post->post_name   = $slug;
	$post->post_title  = $title;
	$post->post_status = $status;
	$GLOBALS['t']['posts'][ $id ] = $post;
	return $post;
}

sa_t_post( 10, 'province', 'lorestan', 'لرستان' );
sa_t_post( 20, 'city', 'dorud', 'دورود' );
sa_t_post( 21, 'city', 'azna', 'ازنا' );
sa_t_post( 22, 'city', 'kuhsorkh', 'کوهسرخ' );
sa_t_post( 23, 'city', 'borujerd', 'بروجرد' );

sa_t_assert( class_exists( 'stdClass' ), 'harness loads' );

require dirname( __DIR__ ) . '/inc/county-profile.php';

/* 1 — canonical keys + legacy fallbacks combine into one card. */
$GLOBALS['t']['meta'][20] = array(
	'county_en'         => 'Dorud',
	'province_id'       => '10',
	'center_fa'         => 'دورود',
	'sa_city_elevation' => '1457',
	'sa_city_latitude'  => '33.5270',
	'sa_city_longitude' => '49.1199',
	'neighbors_e'       => 'azna',
	'neighbors_w'       => 'بروجرد',
);
$d = sa_county_profile_data( 20 );
sa_t_eq( 'شهرستان دورود', $d['name_fa'], 'county name with prefix' );
sa_t_eq( 'Dorud', $d['name_en'], 'english name from county_en' );
sa_t_eq( 'استان لرستان', $d['province_name'], 'province name from province_id post' );
sa_t_eq( 'https://example.test/province/lorestan/', $d['province_url'], 'province link' );
sa_t_eq( '1457', $d['elevation_m'], 'legacy elevation fallback' );
sa_t_eq( 33.527, $d['lat'], 'legacy latitude fallback' );
sa_t_assert( false !== strpos( $d['map_url'], 'openstreetmap.org' ), 'map link built from coordinates' );
sa_t_eq( 'ازنا', $d['neighbors']['e']['direction'] === 'شرق' ? 'ازنا' : '', 'east direction label' );
sa_t_assert( false !== strpos( $d['neighbors']['e']['label'], 'ازنا' ), 'east neighbour by slug resolves' );
sa_t_eq( 'https://example.test/city/azna/', $d['neighbors']['e']['url'], 'neighbour with a page is linked' );
sa_t_assert( false !== strpos( $d['neighbors']['w']['label'], 'بروجرد' ), 'west neighbour by persian name resolves' );
sa_t_eq( 'https://example.test/city/borujerd/', $d['neighbors']['w']['url'], 'registry neighbour link via permalink' );
sa_t_eq( true, $d['has_data'], 'has_data true when fields exist' );

/* 2 — canonical key beats the legacy one. */
$GLOBALS['t']['meta'][20]['center_fa']    = 'دورود';
$GLOBALS['t']['meta'][20]['sa_cty_center'] = 'مقدار قدیمی';
sa_t_eq( 'دورود', sa_county_profile_data( 20 )['center_fa'], 'canonical center wins over legacy' );

/* 3 — empty county: no invented values, has_data false, empties listed. */
$GLOBALS['t']['meta'][22] = array();
$d2 = sa_county_profile_data( 22 );
sa_t_eq( false, $d2['has_data'], 'empty county has no card data' );
sa_t_eq( '', $d2['population'], 'population stays empty' );
sa_t_eq( null, $d2['lat'], 'coordinates stay null' );
sa_t_eq( '', $d2['map_url'], 'no map link without coordinates' );
$empty = sa_county_profile_empty_fields( 22 );
sa_t_assert( in_array( 'population', $empty, true ) && in_array( 'distance_to_capital_km', $empty, true ), 'empty fields reported for the pipeline' );
sa_t_assert( ! in_array( 'county_en', sa_county_profile_empty_fields( 20 ), true ), 'filled canonical key is not reported empty' );

/* 4 — invalid coordinates never produce a map link. */
$GLOBALS['t']['meta'][22] = array( 'lat' => '999', 'lng' => '49.1', 'center_fa' => 'ریوش' );
$d3 = sa_county_profile_data( 22 );
sa_t_eq( null, $d3['lat'], 'out-of-range latitude rejected' );
sa_t_eq( '', $d3['map_url'], 'no map link for invalid coordinates' );
sa_t_eq( true, $d3['has_data'], 'center alone still makes the card show its header rows' );

/* 5 — neighbour without a page keeps its name but gets no link. */
$GLOBALS['t']['meta'][22] = array( 'neighbors_n' => 'شهرستان بدون صفحه' );
$d4 = sa_county_profile_data( 22 );
sa_t_eq( 'شمال', $d4['neighbors']['n']['direction'], 'north direction kept' );
sa_t_eq( '', $d4['neighbors']['n']['url'], 'page-less neighbour has no link' );

/* 6 — legacy neighbour lines fill missing directions. */
$GLOBALS['t']['meta'][21] = array( 'sa_cty_neighbors' => "شمال: دورود\nجنوب: بروجرد" );
$d5 = sa_county_profile_data( 21 );
sa_t_assert( isset( $d5['neighbors']['n'], $d5['neighbors']['s'] ), 'legacy lines map to directions' );

echo "ALL TESTS PASSED\n";
