<?php
/**
 * Standalone regression test for mobile-safe wrapping of raw entity-content tables.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-responsive-tables.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['sa_responsive_table_context'] = array(
	'admin' => false,
	'feed'  => false,
	'single' => true,
	'loop'  => true,
	'main'  => true,
);

function add_action() { return true; }
function add_filter() { return true; }
function is_admin() { return $GLOBALS['sa_responsive_table_context']['admin']; }
function is_feed() { return $GLOBALS['sa_responsive_table_context']['feed']; }
function is_singular() { return $GLOBALS['sa_responsive_table_context']['single']; }
function in_the_loop() { return $GLOBALS['sa_responsive_table_context']['loop']; }
function is_main_query() { return $GLOBALS['sa_responsive_table_context']['main']; }
function sa_rt_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once __DIR__ . '/../inc/template-tags.php';

$raw_table = '<table><tbody><tr><td>نام</td><td>مقدار</td></tr></tbody></table>';
$wrapped   = sa_wrap_uncontained_content_tables( '<p>متن</p>' . $raw_table );
sa_rt_assert( 1 === substr_count( $wrapped, 'class="sa-table-scroll"' ), 'a standalone table gets exactly one scroller' );
sa_rt_assert( false !== strpos( $wrapped, $raw_table ), 'table markup remains unchanged inside the scroller' );
sa_rt_assert( false !== strpos( $wrapped, 'tabindex="0"' ), 'the horizontal scroller is keyboard-focusable' );

$gutenberg = '<figure class="wp-block-table"><table><tbody><tr><td>جدول بلوکی</td></tr></tbody></table></figure>';
sa_rt_assert( $gutenberg === sa_wrap_uncontained_content_tables( $gutenberg ), 'Gutenberg table wrappers are not nested' );

$custom = '<div class="sa-table-scroll" role="region">' . $raw_table . '</div>';
sa_rt_assert( $custom === sa_wrap_uncontained_content_tables( $custom ), 'existing responsive wrappers are not nested' );

$mixed = $raw_table . $gutenberg . $raw_table;
$mixed_wrapped = sa_wrap_uncontained_content_tables( $mixed );
sa_rt_assert( 2 === substr_count( $mixed_wrapped, 'class="sa-table-scroll"' ), 'only the two unwrapped tables in mixed content are wrapped' );

foreach ( array( 'admin', 'feed', 'single', 'loop', 'main' ) as $key ) {
	$old = $GLOBALS['sa_responsive_table_context'][ $key ];
	$GLOBALS['sa_responsive_table_context'][ $key ] = ( 'admin' === $key || 'feed' === $key ) ? true : false;
	sa_rt_assert( $raw_table === sa_wrap_uncontained_content_tables( $raw_table ), "the filter is bypassed outside the front-end main loop ({$key})" );
	$GLOBALS['sa_responsive_table_context'][ $key ] = $old;
}

echo "PASS: responsive tables (raw entity tables scroll within their wrapper; existing wrappers and non-main contexts stay untouched)\n";
