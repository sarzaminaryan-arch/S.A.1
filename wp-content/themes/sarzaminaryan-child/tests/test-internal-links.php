<?php
/**
 * Standalone regression tests for strict geographic auto-linking.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-internal-links.php
 * The small WordPress shims below exercise the real child-theme code without a
 * WordPress install or database.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', trailingslashit( dirname( __DIR__ ) ) );
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.12.4-test' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

$GLOBALS['sa_test_filters'] = array();
$GLOBALS['sa_test_posts']   = array(
	101 => (object) array(
		'ID'          => 101,
		'post_type'   => 'province',
		'post_name'   => 'tehran',
		'post_title'  => 'استان استان تهران',
		'post_status' => 'publish',
	),
	102 => (object) array(
		'ID'          => 102,
		'post_type'   => 'city',
		'post_name'   => 'dorud',
		'post_title'  => 'شهرستان شهرستان دورود',
		'post_status' => 'publish',
	),
);

/** Minimal WP hook shim. */
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return true;
}

/** Minimal filter registry so title filters can be exercised. */
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sa_test_filters'][ $tag ][] = array( $callback, (int) $accepted_args );
	return true;
}

/** Apply only the filters registered by this test. */
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	$value = array_shift( $args );
	foreach ( isset( $GLOBALS['sa_test_filters'][ $tag ] ) ? $GLOBALS['sa_test_filters'][ $tag ] : array() as $filter ) {
		$call_args   = array_slice( $args, 0, max( 0, $filter[1] - 1 ) );
		$call_args[] = $value;
		$call_args   = array_merge( array( $value ), array_slice( $call_args, 0, max( 0, $filter[1] - 1 ) ) );
		$value       = call_user_func_array( $filter[0], $call_args );
	}
	return $value;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function get_post( $post ) {
	if ( is_object( $post ) ) {
		return $post;
	}
	$id = (int) $post;
	return isset( $GLOBALS['sa_test_posts'][ $id ] ) ? $GLOBALS['sa_test_posts'][ $id ] : null;
}

function get_the_title( $post = 0 ) {
	$post_obj = get_post( $post );
	$title    = $post_obj ? (string) $post_obj->post_title : '';
	return apply_filters( 'the_title', $title, $post_obj ? (int) $post_obj->ID : 0 );
}

function get_post_field( $field, $post_id ) {
	$post = get_post( $post_id );
	return $post && isset( $post->{$field} ) ? $post->{$field} : '';
}

function get_post_type( $post = 0 ) {
	$post = get_post( $post );
	return $post && isset( $post->post_type ) ? $post->post_type : '';
}

function get_the_ID() {
	return 0;
}

function is_admin() {
	return false;
}

function is_feed() {
	return false;
}

function is_singular( $post_types = '' ) {
	return true;
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function get_permalink( $post_id ) {
	$post = get_post( $post_id );
	return $post ? 'https://example.test/' . $post->post_type . '/' . $post->post_name . '/' : '';
}

function get_theme_mod( $name, $default = false ) {
	return $default;
}

function get_transient( $key ) {
	return false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	return true;
}

function delete_transient( $key ) {
	return true;
}

function taxonomy_exists( $taxonomy ) {
	return false;
}

function is_wp_error( $value ) {
	return false;
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_url( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function sa_redirect_is_old_slug( $slug ) {
	return false;
}

/** Fake prepared-SELECT result used by the real target-builder. */
class SA_Test_WPDB {
	public $posts = 'wp_posts';

	public function prepare( $query ) {
		return $query;
	}

	public function get_results( $query ) {
		return array(
			(object) array( 'ID' => 101, 'post_type' => 'province', 'post_name' => 'tehran', 'post_title' => 'استان استان تهران' ),
			(object) array( 'ID' => 102, 'post_type' => 'city', 'post_name' => 'dorud', 'post_title' => 'شهرستان شهرستان دورود' ),
		);
	}
}
$GLOBALS['wpdb'] = new SA_Test_WPDB();

require_once SA_CHILD_DIR . 'inc/taxonomies.php';
require_once SA_CHILD_DIR . 'inc/geo-counties.php';
require_once SA_CHILD_DIR . 'inc/internal-links.php';

/** Fail fast with a useful message, independent of PHP's zend.assertions setting. */
function sa_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: " . $message . "\n" );
		exit( 1 );
	}
}

function sa_test_state( $targets ) {
	return array(
		'index'            => $targets['index'],
		'regex'            => $targets['regex'],
		'used'             => array(),
		'count'            => 0,
		'max'              => 30,
		'current_id'       => 0,
		'current_key'      => '',
		'prelinked'        => array(),
		'self_needles'     => array(),
		'needle_done'      => array(),
		'current_province' => '',
	);
}

function sa_test_link_texts( $html ) {
	preg_match_all( '~<a\b[^>]*>(.*?)</a>~us', (string) $html, $matches );
	return isset( $matches[1] ) ? $matches[1] : array();
}

$targets = sa_autolink_build_targets();
sa_test_expect( isset( $targets['index']['استان تهران'] ), 'province key must include its exact prefix' );
sa_test_expect( isset( $targets['index']['شهرستان دورود'] ), 'county key must include its exact prefix' );
sa_test_expect( ! isset( $targets['index']['تهران'] ), 'bare province names must not be geographic keys' );
sa_test_expect( ! isset( $targets['index']['دورود'] ), 'bare county names must not be geographic keys' );
sa_test_expect( ! isset( $targets['index']['استان استان تهران'] ), 'duplicate province prefixes must not enter the index' );
sa_test_expect( ! isset( $targets['index']['شهرستان شهرستان دورود'] ), 'duplicate county prefixes must not enter the index' );

sa_test_expect( 'استان تهران' === sa_autolink_label( 'province', 'استان استان تهران' ), 'province labels normalize to exactly one prefix' );
sa_test_expect( 'شهرستان دورود' === sa_autolink_label( 'city', 'شهرستان شهرستان دورود' ), 'county labels normalize to exactly one prefix' );
sa_test_expect( '' === sa_autolink_label( 'city', 'استان دورود' ), 'mismatched province prefix is rejected for a county' );
sa_test_expect( '' === sa_autolink_label( 'province', 'شهرستان تهران' ), 'mismatched county prefix is rejected for a province' );

$bare_state = sa_test_state( $targets );
$bare       = sa_autolink_fragment( 'تهران و دورود', $bare_state );
sa_test_expect( 'تهران و دورود' === $bare, 'bare geographic names must remain plain text' );
sa_test_expect( 0 === sa_autolink_count( $bare )['total'], 'bare geographic names must produce no auto-links' );

$phrased_state = sa_test_state( $targets );
$phrased       = sa_autolink_fragment( 'استان تهران و شهرستان دورود', $phrased_state );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $phrased ), 'only full prefixed phrases are linked, with source text preserved' );

$repeat_state = sa_test_state( $targets );
$repeat       = sa_autolink_fragment( 'استان تهران، استان تهران؛ شهرستان دورود، شهرستان دورود', $repeat_state );
$repeat_count = sa_autolink_count( $repeat );
sa_test_expect( 1 === $repeat_count['province'] && 1 === $repeat_count['city'], 'each geographic destination is linked at most once' );

$duplicate_state = sa_test_state( $targets );
$duplicate       = sa_autolink_fragment( 'استان استان تهران؛ شهرستان شهرستان دورود', $duplicate_state );
sa_test_expect( false === strpos( $duplicate, 'استان استان' ), 'duplicate province prefixes are collapsed' );
sa_test_expect( false === strpos( $duplicate, 'شهرستان شهرستان' ), 'duplicate county prefixes are collapsed' );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $duplicate ), 'collapsed complete phrases are linked exactly once' );

$mismatch_state = sa_test_state( $targets );
$mismatch       = sa_autolink_fragment( 'شهر تهران، استان دورود، شهرستان تهران', $mismatch_state );
sa_test_expect( 'شهر تهران، استان دورود، شهرستان تهران' === $mismatch, 'bare or mismatched prefixes must not link to geography' );

$rendered = sa_autolink_content( '<h2>استان استان تهران</h2><p>تهران، استان تهران و شهرستان دورود؛ شهرستان دورود</p>' );
$rendered_counts = sa_autolink_count( $rendered );
sa_test_expect( 1 === $rendered_counts['province'] && 1 === $rendered_counts['city'], 'the full content filter links each destination once' );
sa_test_expect( false !== strpos( $rendered, '<h2>استان تهران</h2>' ), 'the full content filter repairs duplicate prefixes in headings' );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $rendered ), 'the full content filter preserves prefixed source phrases and ignores bare names' );

$html = '<h2>استان استان تهران</h2><p data-example="استان استان">شهرستان شهرستان دورود</p><pre>استان استان تهران</pre>';
$clean_html = sa_autolink_normalize_content_prefixes( $html );
sa_test_expect( false !== strpos( $clean_html, '<h2>استان تهران</h2>' ), 'duplicate prefixes in article headings are normalized' );
sa_test_expect( false !== strpos( $clean_html, 'data-example="استان استان"' ), 'HTML attributes are not rewritten' );
sa_test_expect( false !== strpos( $clean_html, '<p data-example="استان استان">شهرستان دورود</p>' ), 'visible body text is normalized without changing attributes' );
sa_test_expect( false !== strpos( $clean_html, '<pre>استان استان تهران</pre>' ), 'code/pre blocks remain untouched' );

sa_test_expect( 'تهران' === sa_province_name_for_post( 101 ), 'province display helper returns the canonical bare name' );
sa_test_expect( 'استان تهران' === sa_province_name_for_post( 101, true ), 'province display helper adds at most one requested prefix' );
sa_test_expect( 'دورود' === sa_county_name( 102 ), 'county display helper strips duplicate stored prefixes' );
sa_test_expect( 'شهرستان دورود' === sa_county_name( 102, true ), 'county display helper adds at most one requested prefix' );

fwrite( STDOUT, "PASS: internal geographic auto-link regression tests\n" );
