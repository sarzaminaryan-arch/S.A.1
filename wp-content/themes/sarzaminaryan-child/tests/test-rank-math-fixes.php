<?php
/**
 * Standalone tests for Rank Math fallback behavior.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-rank-math-fixes.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
$GLOBALS['sa_rank_math_test_context'] = array(
	'admin'        => false,
	'home'         => false,
	'front_page'   => false,
	'page'         => '',
	'queried_id'   => 0,
	'archive'      => false,
	'archive_type' => '',
	'published'    => 0,
	'post_meta'    => array(),
);

/**
 * Assert a test condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @return void
 * @throws RuntimeException When an assertion fails.
 */
function sa_rank_math_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}
/** Stub the WordPress filter registration function. */
function add_filter() {
	return true;
}
/** Stub the WordPress admin-context function. */
function is_admin() {
	return $GLOBALS['sa_rank_math_test_context']['admin'];
}
/** Stub the WordPress blog-home context function. */
function is_home() {
	return $GLOBALS['sa_rank_math_test_context']['home'];
}
/** Stub the WordPress front-page context function. */
function is_front_page() {
	return $GLOBALS['sa_rank_math_test_context']['front_page'];
}
/** Stub the WordPress page-context function.
 *
 * @param string $page Requested page slug.
 * @return bool
 */
function is_page( $page = '' ) {
	return $page === $GLOBALS['sa_rank_math_test_context']['page'];
}
/** Stub the WordPress singular-context function. */
function is_singular() {
	return '' !== $GLOBALS['sa_rank_math_test_context']['page'];
}
/** Stub the current queried post ID. */
function get_queried_object_id() {
	return $GLOBALS['sa_rank_math_test_context']['queried_id'];
}
/** Stub post-type archive context. */
function is_post_type_archive() {
	return $GLOBALS['sa_rank_math_test_context']['archive'];
}
/** Stub query-variable lookup.
 *
 * @param string $key Query variable.
 * @return string
 */
function get_query_var( $key ) {
	return 'post_type' === $key ? $GLOBALS['sa_rank_math_test_context']['archive_type'] : '';
}
/** Stub post-type key sanitization.
 *
 * @param string $key Key.
 * @return string
 */
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $key ) );
}
/** Stub published post counts.
 *
 * @param string $post_type Post type.
 * @return object
 */
function wp_count_posts( $post_type ) {
	unset( $post_type );
	return (object) array( 'publish' => $GLOBALS['sa_rank_math_test_context']['published'] );
}
/** Stub a single post-meta lookup.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param bool   $single  Return one value.
 * @return mixed
 */
function get_post_meta( $post_id, $key, $single = false ) {
	$value = isset( $GLOBALS['sa_rank_math_test_context']['post_meta'][ $key ] )
		? $GLOBALS['sa_rank_math_test_context']['post_meta'][ $key ]
		: '';
	return $single ? $value : array( $value );
}
/** Stub HTML escaping for the test runner.
 *
 * @param string $value Input.
 * @return string
 */
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

/** Stub tag stripping; the test fixtures are plain text.
 *
 * @param string $value Input.
 * @return string
 */
function wp_strip_all_tags( $value ) {
	return (string) $value;
}

require dirname( __DIR__ ) . '/inc/rank-math-fixes.php';

$GLOBALS['sa_rank_math_test_context']['page']       = 'about';
$GLOBALS['sa_rank_math_test_context']['queried_id'] = 17;
$about = sa_rank_math_description_fallback( '' );
sa_rank_math_test_assert( false !== strpos( $about, 'سرزمین آریان' ), 'blank About description uses its approved fallback' );

$GLOBALS['sa_rank_math_test_context']['post_meta']['_rank_math_description'] = 'این متن را با معرفی واقعی تیم تکمیل کنید';
$placeholder = sa_rank_math_description_fallback( '' );
sa_rank_math_test_assert( $placeholder === $about, 'known placeholder is replaced by the approved fallback' );

$GLOBALS['sa_rank_math_test_context']['post_meta']['_rank_math_description'] = 'شرح اختصاصی و معتبر';
$custom = sa_rank_math_description_fallback( 'شرح فعلی رنک‌مث' );
sa_rank_math_test_assert( 'شرح فعلی رنک‌مث' === $custom, 'non-placeholder Rank Math descriptions are preserved' );

$GLOBALS['sa_rank_math_test_context']['post_meta']['_rank_math_title'] = 'عنوان رنک‌مث';
$GLOBALS['sa_rank_math_test_context']['post_meta']['sa_seo_title']     = 'عنوان قالب';
$title_result = sa_rank_math_title_fallback( '' );
sa_rank_math_test_assert( 'عنوان رنک‌مث' === $title_result, 'Rank Math title metadata takes precedence' );

unset( $GLOBALS['sa_rank_math_test_context']['post_meta']['_rank_math_title'] );
$title_result = sa_rank_math_title_fallback( '' );
sa_rank_math_test_assert( 'عنوان قالب' === $title_result, 'sa_seo_title is used only as a title fallback' );

$GLOBALS['sa_rank_math_test_context']['archive']      = true;
$GLOBALS['sa_rank_math_test_context']['archive_type'] = 'travel_route';
$empty_robots = sa_rank_math_empty_archive_robots(
	array(
		'index'    => 'index',
		'nofollow' => 'nofollow',
	)
);
sa_rank_math_test_assert( isset( $empty_robots['noindex'], $empty_robots['follow'] ) && ! isset( $empty_robots['index'], $empty_robots['nofollow'] ), 'empty public archives become noindex,follow' );

$GLOBALS['sa_rank_math_test_context']['published'] = 1;
$populated_robots                                  = sa_rank_math_empty_archive_robots(
	array(
		'index'  => 'index',
		'follow' => 'follow',
	)
);
sa_rank_math_test_assert( isset( $populated_robots['index'] ) && ! isset( $populated_robots['noindex'] ), 'a first published record restores indexability automatically' );

echo "Rank Math fallback tests passed.\n";
