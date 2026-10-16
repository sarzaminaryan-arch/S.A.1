<?php
/**
 * Standalone tests for safe W_* citation rendering.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-w-citations.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
$GLOBALS['sa_w_test_context'] = array(
	'admin'    => false,
	'feed'     => false,
	'singular' => true,
);

function sa_w_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_filter() {
	return true;
}
function is_admin() {
	return $GLOBALS['sa_w_test_context']['admin'];
}
function is_feed() {
	return $GLOBALS['sa_w_test_context']['feed'];
}
function is_singular() {
	return $GLOBALS['sa_w_test_context']['singular'];
}
function esc_url( $url ) {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/inc/w-citations.php';

$matched = sa_filter_w_citation_links( '<p id="W_GOL">متن <sup><a href="W_GOL">۱</a></sup></p>' );
sa_w_test_assert( false !== strpos( $matched, 'href="#W_GOL"' ), 'unique in-page id becomes a fragment href' );

$missing = sa_filter_w_citation_links( '<p><sup><a href="W_HAR">۲</a></sup></p>' );
sa_w_test_assert( false === strpos( $missing, '<a' ) && false !== strpos( $missing, '۲' ), 'unresolved href is removed while visible text remains' );

$duplicate = sa_filter_w_citation_links( '<i id="W_VAR"></i><b id="W_VAR"></b><a href="W_VAR">۳</a>' );
sa_w_test_assert( false === strpos( $duplicate, '<a' ) && false !== strpos( $duplicate, '۳' ), 'duplicate ids do not create an ambiguous fragment link' );

$external = '<a href="https://example.org/source">منبع</a>';
sa_w_test_assert( $external === sa_filter_w_citation_links( $external ), 'non-W links are untouched' );

$GLOBALS['sa_w_test_context']['admin'] = true;
$admin = '<a href="W_KHA">۴</a>';
sa_w_test_assert( $admin === sa_filter_w_citation_links( $admin ), 'admin content is untouched' );

echo "W citation rendering tests passed.\n";
