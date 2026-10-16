<?php
/**
 * Standalone tests for static-page JSON-LD corrections.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-schema-static-pages.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
$GLOBALS['sa_schema_test_page'] = false;
$GLOBALS['sa_schema_test_post'] = (object) array(
	'post_type' => 'page',
	'post_name' => 'about',
);

/** Stub WordPress action registration. */
function add_action() {
	return true;
}
/** Stub WordPress filter registration. */
function add_filter() {
	return true;
}
/** Stub page context. */
function is_page() {
	return $GLOBALS['sa_schema_test_page'];
}
/** Stub the queried post. */
function get_queried_object() {
	return $GLOBALS['sa_schema_test_post'];
}
/** Stub permalink rendering.
 *
 * @param WP_Post $post Queried post.
 * @return string
 */
function get_permalink( $post ) {
	return 'https://sarzaminaryan.ir/' . $post->post_name . '/';
}
/** Stub title rendering.
 *
 * @param WP_Post $post Queried post.
 * @return string
 */
function get_the_title( $post ) {
	return 'صفحهٔ ' . $post->post_name;
}
/** Stub home URL.
 *
 * @param string $path Requested URL path.
 * @return string
 */
function home_url( $path = '/' ) {
	return 'https://sarzaminaryan.ir' . $path;
}

require dirname( __DIR__ ) . '/inc/schema.php';

$about = sa_schema_static_page_type( $GLOBALS['sa_schema_test_post'] );
if ( 'AboutPage' !== $about ) {
	exit( 1 );
}
$GLOBALS['sa_schema_test_post']->post_name = 'contact';
if ( 'ContactPage' !== sa_schema_static_page_type( $GLOBALS['sa_schema_test_post'] ) ) {
	exit( 1 );
}
$GLOBALS['sa_schema_test_post']->post_name = 'ordinary';
if ( 'WebPage' !== sa_schema_static_page_type( $GLOBALS['sa_schema_test_post'] ) ) {
	exit( 1 );
}

$GLOBALS['sa_schema_test_post']->post_name = 'about';
$GLOBALS['sa_schema_test_page']            = true;
$data                                      = array(
	'WebPage'      => array(
		'@type' => 'WebPage',
		'@id'   => 'https://sarzaminaryan.ir/about/#webpage',
	),
	'Duplicate'    => array(
		'@type' => 'WebPage',
		'@id'   => 'https://sarzaminaryan.ir/about/#webpage',
	),
	'Organization' => array(
		'@type' => 'Organization',
		'@id'   => 'https://sarzaminaryan.ir/#organization',
	),
);
$result                                    = sa_rank_math_static_page_schema( $data );
if ( ! isset( $result['WebPage']['@type'] ) || 'AboutPage' !== $result['WebPage']['@type'] || isset( $result['Duplicate'] ) ) {
	exit( 1 );
}

$GLOBALS['sa_schema_test_page'] = false;
$post_schema                    = array(
	'Article' => array(
		'@type' => 'Article',
		'@id'   => 'https://sarzaminaryan.ir/post/#article',
	),
);
if ( sa_rank_math_static_page_schema( $post_schema ) !== $post_schema ) {
	exit( 1 );
}

echo "Static-page schema tests passed.\n";
