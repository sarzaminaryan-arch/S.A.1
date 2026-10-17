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
 * Phase 1.5 adds: the «مهم‌ترین شهر» tile (`11 // MAIN_CITY`, keys
 * main_city / main_city_en, linked only to a published page), the rule that
 * population is never shown without its census year, and static source checks
 * that the card template drops empty tiles instead of printing a placeholder.
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
function sa_county_parse_poi( $line ) {
	$bits = array_map( 'trim', explode( '|', trim( (string) $line ) ) );
	return array( 'name' => isset( $bits[0] ) ? $bits[0] : '', 'distance' => isset( $bits[1] ) ? $bits[1] : '', 'note' => isset( $bits[2] ) ? $bits[2] : '' );
}
function sa_get_parent( $id, $type ) { return null; }
function sa_get_children( $id, $type ) {
	return isset( $GLOBALS['t']['children'][ $id ][ $type ] ) ? $GLOBALS['t']['children'][ $id ][ $type ] : array();
}
function get_the_terms( $id, $taxonomy ) {
	if ( empty( $GLOBALS['t']['terms'][ $id ] ) ) {
		return false;
	}
	$out = array();
	foreach ( $GLOBALS['t']['terms'][ $id ] as $name ) {
		$term       = new stdClass();
		$term->name = $name;
		$out[]      = $term;
	}
	return $out;
}

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

/* 7 — attractions table: relation feeds it automatically, legacy POIs fill gaps. */
$lake = sa_t_post( 30, 'attraction', 'gahar-lake', 'دریاچه گهر' );
$GLOBALS['t']['children'][20]['attraction'] = array( $lake );
$GLOBALS['t']['terms'][30]                  = array( 'طبیعی' );
$GLOBALS['t']['meta'][30]                   = array( 'sa_english_name' => 'Gahar Lake' );
$GLOBALS['t']['meta'][20]['sa_cty_poi_heritage'] = 'محوطه باستانی نمونه';
$rows = sa_county_profile_attractions( 20 );
sa_t_eq( 'دریاچه گهر', $rows[0]['name'], 'related attraction appears automatically' );
sa_t_eq( 'https://example.test/attraction/gahar-lake/', $rows[0]['url'], 'attraction links to its page' );
sa_t_eq( 'Gahar Lake', $rows[0]['en'], 'attraction english name kept' );
sa_t_eq( 'طبیعی', $rows[0]['type'], 'attraction type from taxonomy' );
sa_t_eq( '', $rows[1]['url'], 'legacy POI without a page stays unlinked' );
sa_t_eq( 'تاریخی و زیارتی', $rows[1]['type'], 'legacy POI group becomes the type' );

/* 8 — same-province cities: only published county pages, current excluded. */
$province = get_post( 20 );
$GLOBALS['t']['meta'][20]['province_id'] = '10';
$sibs = sa_county_profile_sibling_cities( 20 );
$labels = array_column( $sibs, 'label' );
sa_t_assert( in_array( 'شهرستان ازنا', $labels, true ) && in_array( 'شهرستان بروجرد', $labels, true ), 'same-province counties listed' );
sa_t_assert( ! in_array( 'شهرستان دورود', $labels, true ), 'current county excluded from siblings' );

/* 9 — villages count and the short prose block fields. */
$GLOBALS['t']['meta'][22] = array( 'sa_cty_villages' => '41', 'industry_main' => 'متن نمونه صنعت' );
$d6 = sa_county_profile_data( 22 );
sa_t_eq( '41', $d6['divisions']['روستا'], 'villages fall back to sa_cty_villages' );
sa_t_eq( 'متن نمونه صنعت', $d6['industry_main'], 'industry text carried to the under-card note' );

/* 10 — main city (مهم‌ترین شهر): canonical keys, link only to a published page. */
sa_t_post( 24, 'city', 'dezpart', 'دزپارت', 'draft' );
$GLOBALS['t']['meta'][22] = array( 'main_city' => 'ریوش', 'main_city_en' => 'Riyesh' );
$d7 = sa_county_profile_data( 22 );
sa_t_eq( 'ریوش', $d7['main_city'], 'main city comes from the canonical main_city key' );
sa_t_eq( 'Riyesh', $d7['main_city_en'], 'main city english name from main_city_en' );
sa_t_eq( '', $d7['main_city_url'], 'a main city without its own page gets no link' );
sa_t_eq( true, $d7['has_data'], 'the main city alone makes the card render' );
$GLOBALS['t']['meta'][21] = array( 'main_city' => 'ازنا' );
sa_t_eq( 'https://example.test/city/azna/', sa_county_profile_data( 21 )['main_city_url'], 'a registry name links to its published page' );
$GLOBALS['t']['meta'][20]['main_city'] = 'borujerd';
sa_t_eq( 'https://example.test/city/borujerd/', sa_county_profile_data( 20 )['main_city_url'], 'a slug links to its published page' );
$GLOBALS['t']['meta'][24] = array( 'main_city' => 'dezpart' );
sa_t_eq( '', sa_county_profile_data( 24 )['main_city_url'], 'a draft page is never linked' );
$GLOBALS['t']['meta'][23] = array();
sa_t_eq( '', sa_county_profile_data( 23 )['main_city'], 'no main city is invented when the meta is empty' );

/* 11 — population is shown only together with its census year. */
$GLOBALS['t']['meta'][20]['population'] = '174508';
sa_t_eq( false, sa_county_profile_data( 20 )['population_shown'], 'population without a census year stays hidden' );
$GLOBALS['t']['meta'][20]['population_year'] = '1395';
$d8 = sa_county_profile_data( 20 );
sa_t_eq( true, $d8['population_shown'], 'population with its census year is shown' );
sa_t_eq( '174508', $d8['population'], 'population value is kept exactly as stored' );
sa_t_eq( '1395', $d8['population_year'], 'census year is kept exactly as stored' );
$GLOBALS['t']['meta'][22] = array( 'population' => '31903' );
sa_t_eq( false, sa_county_profile_data( 22 )['population_shown'], 'a number without its year is never printed' );

/* 12 — the card template obeys the output rules (static source checks). */
$sa_tpl = (string) file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/county-profile.php' );
sa_t_assert( '' !== $sa_tpl, 'the county identity-card template is readable' );
sa_t_assert( false !== strpos( $sa_tpl, "'11 // MAIN_CITY'" ), 'the card renders the MAIN_CITY tile' );
sa_t_assert( false !== strpos( $sa_tpl, 'sa_county_profile_main_city' ), 'the MAIN_CITY tile reads the canonical keys' );
sa_t_assert( false !== strpos( $sa_tpl, 'wp_strip_all_tags' ) && false !== strpos( $sa_tpl, '$sa_county_cells = array_values(' ), 'empty tiles are removed instead of printed' );
sa_t_assert( false !== strpos( $sa_tpl, '$sa_pop_num > 0 && $sa_pop_year > 0' ), 'population is printed only with its census year' );
sa_t_assert( false === strpos( $sa_tpl, 'sa_city_population' ), 'the card never mixes a city number with the county one' );
sa_t_assert( false !== strpos( $sa_tpl, '$sa_coord_valid' ), 'coordinates are validated before any map link' );
sa_t_assert( false === strpos( $sa_tpl, '$sa_center = $sa_county_bare' ), 'the centre city is never inferred from the county name' );

echo "ALL TESTS PASSED\n";
