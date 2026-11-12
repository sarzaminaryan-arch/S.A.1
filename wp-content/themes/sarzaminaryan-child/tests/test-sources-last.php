<?php
/**
 * Standalone test: منابع همیشه آخرین بخش مقاله است (2.22.51).
 *
 * Before 2.22.51 the unified sources list was appended to the end of
 * the_content by sa_citations_filter, which placed «منابع» before the FAQ
 * and the related-sections the templates print after the body. The list is
 * now stashed while the body renders (footnote markers still point at its
 * #sa-ref-N anchors) and sa_sources_section()/sa_guide_sources_section()
 * print it at the very end of the article — after سوالات متداول and the
 * county's related sights, exactly as the owner asked.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-sources-last.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array( 'meta' => array() );

function sa_sl_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_action() { return true; }
function apply_filters( $tag, $value = null ) { return $value; }
function add_filter() { return true; }
function remove_filter() { return true; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_singular() { return true; }
function get_the_ID() { return 213; }
function home_url() { return 'https://example.test'; }
function get_queried_object_id() { return 213; }
function get_post_meta( $id, $key, $single = false ) {
	unset( $single );
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function wp_parse_url( $url, $component = -1 ) { return parse_url( (string) $url, $component ); }
function sa_fa_digits( $value ) { return strtr( (string) $value, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ); }

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/template-tags.php';
require_once $theme_dir . '/inc/citations.php';

$GLOBALS['t']['meta'][213]['sa_sources'] = 'مرکز آمار ایران | مرکز آمار ایران | https://amar.org.ir | ۱۴۰۵';

$body = '<p>جمعیت این شهرستان طبق <a href="https://amar.org.ir">مرکز آمار</a> ثبت شده است.</p>';
$out  = sa_citations_filter( $body );

sa_sl_assert( false === strpos( $out, 'id="sources"' ), 'sources: list is NOT appended to the body anymore' );
sa_sl_assert( false === strpos( $out, '<h2 id="sa-footnotes-title">منابع</h2>' ), 'sources: no منابع heading inside the body' );
sa_sl_assert( false !== strpos( $out, 'sa-ref' ), 'sources: footnote marker still links forward to the list anchor' );
sa_sl_assert( ! empty( $GLOBALS['sa_sources_section_html'][213] ), 'sources: unified list is stashed for the end of the article' );
$stash = $GLOBALS['sa_sources_section_html'][213];
sa_sl_assert( false !== strpos( $stash, 'id="sources"' ) && false !== strpos( $stash, 'amar.org.ir' ), 'sources: stash carries the section anchor and the source' );

ob_start();
sa_sources_section( 213 );
$printed = ob_get_clean();
sa_sl_assert( $stash === $printed, 'sources: sa_sources_section prints the stashed list at the end' );

ob_start();
sa_guide_sources_section( 213 );
$printed_guide = ob_get_clean();
sa_sl_assert( $stash === $printed_guide, 'sources: guide variant prints the same stashed list' );

$single = (string) file_get_contents( $theme_dir . '/template-parts/entity/single.php' );
$pos_sources = strpos( $single, 'sa_sources_section( $sa_id )' );
$pos_faq     = strpos( $single, "get_template_part( 'template-parts/entity/faq' )" );
$pos_note    = strpos( $single, 'sa_facts_checked_note( $sa_id )' );
sa_sl_assert( false !== $pos_sources && false !== $pos_faq && $pos_sources > $pos_faq, 'order: entity template prints sources after the FAQ' );
sa_sl_assert( false !== $pos_note && $pos_sources > $pos_note, 'order: sources are the last block of the entity template' );

echo "PASS: sources last (list stashed during body render, printed after FAQ/related by both section printers)\n";
