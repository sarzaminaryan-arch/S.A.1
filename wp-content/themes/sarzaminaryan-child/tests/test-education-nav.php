<?php
/**
 * Standalone tests for the «آموزش‌ها» navigation (inc/education.php, v2.13.1).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-education-nav.php
 * Small WordPress shims exercise the real theme code without a WordPress install.
 * Covered: the "never link an empty archive" rule, the customizer overrides and the
 * placement of the synthetic header-menu item right after the entity archives.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$GLOBALS['sa_test'] = array(
	'mods'    => array(),
	'term'    => null,
	'filters' => array(),
	'context' => array(),
);

/** Fail fast with a readable message. */
function sa_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Term { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $term_id;
	public $name;
	public $slug;
	public $count;
	public function __construct( $term_id, $name, $slug, $count ) {
		$this->term_id = $term_id;
		$this->name    = $name;
		$this->slug    = $slug;
		$this->count   = $count;
	}
}

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return true;
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sa_test']['filters'][ $tag ][] = array( $callback, (int) $accepted_args );
	return true;
}
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	$value = array_shift( $args );
	foreach ( isset( $GLOBALS['sa_test']['filters'][ $tag ] ) ? $GLOBALS['sa_test']['filters'][ $tag ] : array() as $filter ) {
		$value = call_user_func_array( $filter[0], array_merge( array( $value ), array_slice( $args, 0, max( 0, $filter[1] - 1 ) ) ) );
	}
	return $value;
}
function get_theme_mod( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['sa_test']['mods'] ) ? $GLOBALS['sa_test']['mods'][ $name ] : $default;
}
function sanitize_title( $title ) {
	$title = strtolower( trim( (string) $title ) );
	return trim( preg_replace( '~[^a-z0-9\-]+~', '-', $title ), '-' );
}
function get_term_by( $field, $value, $taxonomy ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$term = $GLOBALS['sa_test']['term'];
	return ( $term && 'slug' === $field && $term->slug === $value ) ? $term : false;
}
function get_term_link( $term ) {
	return 'https://example.test/category/' . $term->slug . '/';
}
function is_wp_error( $thing ) {
	return false;
}
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}
function is_category( $term = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return ! empty( $GLOBALS['sa_test']['context']['category'] );
}
function is_singular( $type = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return ! empty( $GLOBALS['sa_test']['context']['singular'] );
}
function is_tag( $tag = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return false;
}
function has_category( $term, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return ! empty( $GLOBALS['sa_test']['context']['in_category'] );
}
function get_queried_object_id() {
	return 7;
}
function sa_entity_types() {
	return array( 'province', 'city', 'attraction' );
}
function sa_archive_url( $type ) {
	return 'https://example.test/' . $type . '/';
}

require_once dirname( __DIR__ ) . '/inc/education.php';

/** Reset the static cache inside sa_education_category() by swapping the slug. */
function sa_test_reset( $term, $mods = array() ) {
	$GLOBALS['sa_test']['term'] = $term;
	$GLOBALS['sa_test']['mods'] = $mods;
	$GLOBALS['sa_test']['context'] = array();
}

/* 1) No category yet (nothing imported) → no link anywhere. */
sa_test_reset( null, array( 'sa_education_category' => 'no-such-' . mt_rand( 1000, 9999 ) ) );
sa_test_assert( null === sa_education_category(), 'missing category resolves to null' );
sa_test_assert( 0 === sa_education_count(), 'missing category has count 0' );
sa_test_assert( null === sa_education_nav_item(), 'no nav item without a category' );
sa_test_assert( '' === sa_education_url(), 'no url without a category' );

/* 2) Category exists but every guide is still a draft → still hidden. */
sa_test_reset( new WP_Term( 41, 'آموزش‌های ضروری کمپینگ و بقا', 'drafts-only', 0 ), array( 'sa_education_category' => 'drafts-only' ) );
sa_test_assert( sa_education_category() instanceof WP_Term, 'category object found' );
sa_test_assert( null === sa_education_nav_item(), 'empty archive is never linked (drafts awaiting owner approval)' );
sa_test_assert( array() === sa_education_filter_tags(), 'no branch filters for an empty category' );

/* 3) First guide published → link appears with the default label. */
$live = new WP_Term( 42, 'آموزش‌های ضروری کمپینگ و بقا', 'camping-survival', 3 );
sa_test_reset( $live );
sa_test_assert( 'camping-survival' === sa_education_category_slug(), 'default slug is camping-survival' );
$nav = sa_education_nav_item();
sa_test_assert( is_array( $nav ), 'nav item exists once a guide is published' );
sa_test_assert( 'آموزش‌ها' === $nav['label'], 'default nav label' );
sa_test_assert( 'https://example.test/category/camping-survival/' === $nav['url'], 'nav url is the category archive' );
sa_test_assert( 3 === $nav['count'], 'count follows the term count' );
sa_test_assert( false === $nav['current'], 'not current outside the section' );

/* 4) Customizer overrides: label + slug. */
$alt = new WP_Term( 43, 'آموزش سفر', 'learn', 1 );
sa_test_reset( $alt, array( 'sa_education_nav_label' => '  آموزش سفر ', 'sa_education_category' => ' Learn ' ) );
$nav = sa_education_nav_item();
sa_test_assert( 'learn' === sa_education_category_slug(), 'slug mod is sanitised' );
sa_test_assert( 'آموزش سفر' === $nav['label'], 'label mod is trimmed and used' );
sa_test_assert( 'https://example.test/category/learn/' === $nav['url'], 'url follows the slug mod' );

/* 5) Context detection. */
sa_test_reset( $live );
$GLOBALS['sa_test']['context'] = array( 'category' => true );
sa_test_assert( true === sa_is_education_context(), 'category archive is education context' );
$GLOBALS['sa_test']['context'] = array( 'singular' => true, 'in_category' => true );
sa_test_assert( true === sa_education_nav_item()['current'], 'guide single marks the item current' );
$GLOBALS['sa_test']['context'] = array( 'singular' => true, 'in_category' => false );
sa_test_assert( false === sa_is_education_context(), 'other posts are not education context' );

/* 6) Saved header menu: item lands right after the entity archives (and after their sub-items). */
sa_test_reset( $live );
$menu = array(
	(object) array( 'ID' => 1, 'db_id' => 1, 'menu_item_parent' => 0, 'object' => 'page', 'url' => 'https://example.test/', 'menu_order' => 1 ),
	(object) array( 'ID' => 2, 'db_id' => 2, 'menu_item_parent' => 0, 'object' => 'province', 'url' => 'https://example.test/province/', 'menu_order' => 2 ),
	(object) array( 'ID' => 3, 'db_id' => 3, 'menu_item_parent' => 0, 'object' => 'custom', 'url' => 'https://example.test/city', 'menu_order' => 3 ),
	(object) array( 'ID' => 4, 'db_id' => 4, 'menu_item_parent' => 3, 'object' => 'city', 'url' => 'https://example.test/city/dorud/', 'menu_order' => 4 ),
	(object) array( 'ID' => 5, 'db_id' => 5, 'menu_item_parent' => 0, 'object' => 'page', 'url' => 'https://example.test/blog/', 'menu_order' => 5 ),
);
$args = (object) array( 'theme_location' => 'primary' );
$out  = sa_education_menu_item( $menu, $args );
sa_test_assert( 6 === count( $out ), 'one synthetic item appended to the primary menu' );
sa_test_assert( 'sa-education' === $out[4]->ID, 'placed after the city group (custom link + child), before the blog page' );
sa_test_assert( 'https://example.test/category/camping-survival/' === $out[4]->url, 'synthetic item links to the category' );
sa_test_assert( in_array( 'menu-item', $out[4]->classes, true ) && in_array( 'sa-menu-item--education', $out[4]->classes, true ), 'synthetic item classes' );
sa_test_assert( 6 === (int) $out[4]->menu_order, 'menu_order after the last saved item' );
sa_test_assert( 5 === $out[5]->ID, 'blog page stays last' );

/* 7) Secondary menu untouched; owner-placed link not duplicated; empty archive adds nothing. */
sa_test_assert( 5 === count( sa_education_menu_item( $menu, (object) array( 'theme_location' => 'secondary' ) ) ), 'secondary menu is left alone' );
$manual   = $menu;
$manual[] = (object) array( 'ID' => 9, 'db_id' => 9, 'menu_item_parent' => 0, 'object' => 'category', 'url' => 'https://example.test/category/camping-survival', 'menu_order' => 9 );
sa_test_assert( 6 === count( sa_education_menu_item( $manual, $args ) ), 'no duplicate when the owner already added the category' );
sa_test_reset( new WP_Term( 44, 'x', 'camping-survival-empty', 0 ), array( 'sa_education_category' => 'camping-survival-empty' ) );
sa_test_assert( sa_education_category() instanceof WP_Term && 0 === sa_education_count(), 'empty category resolved' );
sa_test_assert( 5 === count( sa_education_menu_item( $menu, $args ) ), 'nothing added while the archive is empty' );

echo "PASS: education navigation (empty-archive rule, customizer overrides, header menu placement)\n";
