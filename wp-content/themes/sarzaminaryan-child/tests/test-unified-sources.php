<?php
/**
 * Regression test for extracting inline citations and links as unified sources.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-unified-sources.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Minimal WordPress hook shims for loading template-tags.php standalone. */
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return true;
}

function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return true;
}

function get_post_meta( $post_id, $key, $single = false ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return '';
}

function wp_parse_url( $url, $component = -1 ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return parse_url( $url, $component );
}

require_once dirname( __DIR__ ) . '/inc/template-tags.php';

$content = <<<'HTML'
<sup class="sa-cite"><a href="https://example.org/double-quoted-citation">1</a></sup>
<sup class='sa-cite'><a href='https://example.org/single-quoted-citation'>2</a></sup>
<a href="https://example.net/double-quoted-link">three</a>
<a href='https://example.net/single-quoted-link'>four</a>
HTML;

$sources = sa_build_unified_sources( 1, $content );
$actual  = array_map(
	static function ( $source ) {
		return $source['url'];
	},
	$sources
);
$expected = array(
	'https://example.org/double-quoted-citation',
	'https://example.org/single-quoted-citation',
	'https://example.net/double-quoted-link',
	'https://example.net/single-quoted-link',
);

if ( $expected !== $actual ) {
	fwrite( STDERR, "FAIL: unified-source extraction did not preserve all single/double-quoted URLs in order.\n" );
	fwrite( STDERR, 'Expected: ' . implode( ', ', $expected ) . "\n" );
	fwrite( STDERR, 'Actual:   ' . implode( ', ', $actual ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "PASS: unified-source extraction handles single- and double-quoted citation and anchor attributes\n" );
