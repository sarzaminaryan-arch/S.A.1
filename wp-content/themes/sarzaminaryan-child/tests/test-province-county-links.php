<?php
/**
 * Standalone test: province page county-list link resolution (2.22.64).
 *
 * GSC coverage export 2026-11-13 showed 221 county pages stuck in
 * «Discovered - currently not indexed». One internal-link leak behind it:
 * the province page only linked a county when its post both had the stored
 * province relation AND a post_name identical to the registry slug. Live
 * posts whose slug differs (e.g. post «zanjan» vs registry «zanjan-city»)
 * or that lack the relation rendered as plain, unlinkable names on their
 * own province page — no internal signal at all.
 *
 * Locked rules guarded here:
 *  1. A registry county resolves to a related post by slug candidates
 *     (with/without the «-city» suffix).
 *  2. Without a relation, any *published* city post with a candidate slug
 *     still earns the province-page link; drafts never do.
 *  3. Counties with no live page stay plain text (empty URL).
 *  4. When a resolved post slug is itself a 301 source, the link points at
 *     the redirect target, not at the redirecting URL.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-province-county-links.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
$theme_dir = dirname( __DIR__ );
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', $theme_dir . '/' );
}

function sa_pcl_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post { // phpcs:ignore
	public $ID;
	public $post_type;
	public $post_name;
	public $post_title;
	public $post_status;
	public function __construct( $id, $type, $name, $title, $status = 'publish' ) {
		$this->ID         = $id;
		$this->post_type  = $type;
		$this->post_name  = $name;
		$this->post_title = $title;
		$this->post_status = $status;
	}
}

$GLOBALS['t'] = array( 'posts' => array(), 'children' => array(), 'registry' => array() );

function sanitize_key( $key ) { return strtolower( trim( (string) $key ) ); }
function get_page_by_path( $path, $output = null, $type = 'page' ) {
	unset( $output );
	foreach ( $GLOBALS['t']['posts'] as $post ) {
		if ( $post->post_name === $path && $post->post_type === $type ) {
			return $post;
		}
	}
	return null;
}
function get_permalink( $post ) {
	return 'https://example.test/' . $post->post_type . '/' . $post->post_name . '/';
}
function get_the_title( $post ) { return $post->post_title; }
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function sa_get_children( $id, $type ) {
	unset( $type );
	return isset( $GLOBALS['t']['children'][ $id ] ) ? $GLOBALS['t']['children'][ $id ] : array();
}
function sa_counties_by_province() { return $GLOBALS['t']['registry']; }
function sa_county( $slug ) {
	foreach ( $GLOBALS['t']['registry'] as $province => $rows ) {
		foreach ( $rows as $row ) {
			if ( $row['slug'] === $slug ) {
				return array( 'slug' => $row['slug'], 'name' => $row['name'], 'province' => $province );
			}
		}
	}
	return null;
}
function sa_redirect_target_for_path( $path ) {
	return '/city/oldtown/' === $path ? '/city/newtown/' : '';
}

require_once $theme_dir . '/inc/province-profile.php';

/* ---- Fixture ------------------------------------------------------------- */

$province_post = new WP_Post( 10, 'province', 'zanjan', 'استان زنجان' );
$abhar_post    = new WP_Post( 11, 'city', 'abhar', 'شهرستان ابهر' );
$oldtown_post  = new WP_Post( 13, 'city', 'oldtown', 'شهرستان قدیم' );
$zanjan_post   = new WP_Post( 12, 'city', 'zanjan', 'شهرستان زنجان' ); // live slug differs from registry, no relation.
$tarom_draft   = new WP_Post( 14, 'city', 'tarom', 'شهرستان طارم', 'draft' );

$GLOBALS['t']['posts']    = array( $province_post, $abhar_post, $oldtown_post, $zanjan_post, $tarom_draft );
$GLOBALS['t']['children'] = array( 10 => array( $abhar_post, $oldtown_post ) );
$GLOBALS['t']['registry'] = array(
	'zanjan' => array(
		array( 'slug' => 'zanjan-city', 'name' => 'زنجان', 'en' => 'Zanjan' ),
		array( 'slug' => 'abhar', 'name' => 'ابهر', 'en' => 'Abhar' ),
		array( 'slug' => 'tarom', 'name' => 'طارم', 'en' => 'Tarom' ),
	),
);

$entries = sa_province_profile_county_entries( 'zanjan', 10 );
$by_slug = array();
foreach ( $entries as $entry ) {
	$by_slug[ $entry['slug'] ] = $entry;
}

/* ---- Assertions ---------------------------------------------------------- */

sa_pcl_assert( isset( $by_slug['zanjan-city'] ), 'province links: registry county zanjan-city is listed' );
sa_pcl_assert( 'https://example.test/city/zanjan/' === $by_slug['zanjan-city']['url'], 'province links: slug candidate (zanjan-city -> zanjan) resolves without relation (2.22.64)' );
sa_pcl_assert( 'https://example.test/city/abhar/' === $by_slug['abhar']['url'], 'province links: related county keeps its direct link' );
sa_pcl_assert( isset( $by_slug['tarom'] ) && '' === $by_slug['tarom']['url'], 'province links: county with only a draft post stays unlinked' );
sa_pcl_assert( isset( $by_slug['oldtown'] ) && 'https://example.test/city/newtown/' === $by_slug['oldtown']['url'], 'province links: redirect-source slug links to the 301 target instead' );

// When both slugs exist, a stale relation on the alias must not displace the exact member.
$GLOBALS['t']['posts'][] = new WP_Post( 15, 'city', 'zanjan-city', 'شهرستان زنجان' );
$entries = array_column( sa_province_profile_county_entries( 'zanjan', 10, array( $zanjan_post ) ), null, 'slug' );
sa_pcl_assert( 15 === $entries['zanjan-city']['post_id'], 'exact registered page beats a related alias' );

echo "test-province-county-links: OK\n";
