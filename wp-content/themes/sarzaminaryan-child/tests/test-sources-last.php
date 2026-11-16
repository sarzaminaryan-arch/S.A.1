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
$pos_related = strpos( $single, 'sa_related_articles( $sa_id, $sa_type )' );
$pos_nav     = strpos( $single, 'sa_entity_navigation( $sa_id, $sa_type )' );
sa_sl_assert( false !== $pos_sources && false !== $pos_faq && $pos_sources > $pos_faq, 'order: entity template prints sources after the FAQ' );
sa_sl_assert( false !== $pos_related && $pos_sources < $pos_related, 'order: sources close the article, above the suggested-counties block' );
sa_sl_assert( false !== $pos_nav && $pos_nav > $pos_related, 'order: previous/next navigation is the last block of the page' );

/* v2.22.85 — شمارهٔ فهرستِ چسبیده به عنوان منبع حذف می‌شود؛ دامنهٔ بی‌برچسب دوبار چاپ نمی‌شود. */
$GLOBALS['t']['meta'][213]['sa_sources'] = "3. عنوان منبع نمونه | خبرگزاری برنا | https://borna.news/fa/news/1 | ۱۴۰۵/۰۸/۲۴\n۷۸ شیء تاریخی کشف شد | خبرگزاری مهر | https://www.mehrnews.com/news/2 | ۱۴۰۵/۰۸/۲۴\n";
$src = sa_get_sources( 213 );
sa_sl_assert( 2 === count( $src ), 'sources: two lines parsed' );
sa_sl_assert( 'عنوان منبع نمونه' === $src[0]['title'], 'sources: leading list number stripped from the title' );
sa_sl_assert( '۷۸ شیء تاریخی کشف شد' === $src[1]['title'], 'sources: a real number opening a title is kept' );

$body2  = '<p>متن <a href="https://borna.news/fa/news/1">لینک یک</a> و <a href="https://www.mehrnews.com/news/2">لینک دو</a>.</p>';
$out2   = sa_citations_filter( $body2 );
$stash2 = (string) $GLOBALS['sa_sources_section_html'][213];
sa_sl_assert( false === strpos( $stash2, '3. عنوان' ), 'sources: rendered list shows no baked-in numbering' );
sa_sl_assert( false !== strpos( $stash2, 'عنوان منبع نمونه' ), 'sources: clean title renders in the list' );
sa_sl_assert( false === strpos( $stash2, 'sa-footnotes__host" dir="ltr">borna.news<' ), 'sources: unlabeled host is not printed twice' );
sa_sl_assert( false !== strpos( $stash2, 'sa-footnotes__host" dir="ltr">mehrnews.com<' ), 'sources: mapped label still shows the latin host once' );

// v2.22.86: فهرست «شهرهای هم‌استانی» درست بالای منابع چاپ می‌شود.
$tt86 = (string) file_get_contents( $theme_dir . '/inc/template-tags.php' );
sa_sl_assert( false !== strpos( $tt86, 'sa_county_siblings_html' ), 'template-tags: sibling-cities list echoed right before the sources section (2.22.86)' );
$tp86 = (string) file_get_contents( $theme_dir . '/template-parts/entity/county-profile.php' );
sa_sl_assert( false !== strpos( $tp86, 'sa_county_siblings_html' ), 'template part: sibling list stashed for the article end (2.22.86)' );

echo "PASS: sources last (list stashed during body render, printed as the last article section, prev/next navigation closes the page)\n";
