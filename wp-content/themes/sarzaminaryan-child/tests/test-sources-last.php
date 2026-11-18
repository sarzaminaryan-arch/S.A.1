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
// v2.22.145: for counties these blocks live in the section files. The generic
// (non-county) path in single.php still prints them, so both are checked.
$sa_src_section = (string) file_get_contents( $theme_dir . '/template-parts/county/section-sources.php' );
$sa_rel_section = (string) file_get_contents( $theme_dir . '/template-parts/county/section-related.php' );
$sa_nav_section = (string) file_get_contents( $theme_dir . '/template-parts/county/section-nav.php' );
$pos_sources = strpos( $sa_src_section, 'sa_sources_section( $sa_id )' );
$pos_related = strpos( $sa_rel_section, "sa_related_articles( \$sa_id, 'city' )" );
$pos_nav     = strpos( $sa_nav_section, "sa_entity_navigation( \$sa_id, 'city' )" );
sa_sl_assert( false !== $pos_sources, 'order: the county sources section prints the sources list' );
sa_sl_assert( false !== $pos_related && false !== $pos_nav, 'order: the related and navigation sections exist (2.22.145)' );
// Cross-file positions are not comparable, so the county order is read from the
// registry itself: faq → gallery → sources → related → nav.
$sa_reg = (string) file_get_contents( $theme_dir . '/inc/county-sections.php' );
$sa_pos = array();
foreach ( array( 'sa-county-faq', 'sa-county-gallery', 'sa-county-sources', 'sa-county-related', 'sa-county-nav' ) as $sa_okey ) {
	$sa_pos[ $sa_okey ] = strpos( $sa_reg, "'" . $sa_okey . "'" );
	sa_sl_assert( false !== $sa_pos[ $sa_okey ], 'registry: ' . $sa_okey . ' is declared (2.22.145)' );
}
sa_sl_assert( $sa_pos['sa-county-faq'] < $sa_pos['sa-county-gallery'], 'order: the FAQ comes before the gallery' );
sa_sl_assert( $sa_pos['sa-county-gallery'] < $sa_pos['sa-county-sources'], 'order: sources come after the gallery' );
sa_sl_assert( $sa_pos['sa-county-sources'] < $sa_pos['sa-county-related'], 'order: sources close the article, above the suggested-counties block' );
sa_sl_assert( $sa_pos['sa-county-related'] < $sa_pos['sa-county-nav'], 'order: previous/next navigation is the last block of the page' );

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

// Photo attribution is not an editorial source: preserve source and licence links.
$caption = '<figcaption>عکس: <span class="sa-photo-credit-latin">Photographer</span> · <a href="https://commons.wikimedia.org/wiki/File:Example.jpg">منبع تصویر</a> · <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="license noopener">مجوز</a></figcaption>';
$body3 = '<p>یک متن با <a href="https://borna.news/fa/news/1">ارجاع</a>.</p><figure>' . $caption . '</figure>';
$out3 = sa_citations_filter( $body3 );
sa_sl_assert( false !== strpos( $out3, $caption ), 'photo attribution: original caption and both links survive' );
sa_sl_assert( false === strpos( $GLOBALS['sa_sources_section_html'][213], 'creativecommons.org' ), 'photo licence does not become an editorial source' );
sa_sl_assert( false !== strpos( $out3, 'class="sa-ref"' ), 'ordinary editorial citations still work' );
$ordinary = sa_citations_filter( '<p><a href="https://commons.wikimedia.org/wiki/File:Example.jpg">منبع عادی</a></p>' );
sa_sl_assert( false === strpos( $ordinary, 'href="https://commons.wikimedia.org' ), 'forbidden editorial sources remain blocked outside photo credits' );

// v2.22.86: فهرست «شهرهای هم‌استانی» درست بالای منابع چاپ می‌شود.
$tt86 = (string) file_get_contents( $theme_dir . '/inc/template-tags.php' );
sa_sl_assert( false !== strpos( $tt86, 'sa_county_siblings_html' ), 'template-tags: sibling-cities list echoed right before the sources section (2.22.86)' );
// v2.22.145: sa_sources_section() already prints the siblings; the section file must
// not print them a second time (that put two identical accordions on the page).
$sa_src_only = (string) file_get_contents( $theme_dir . '/template-parts/county/section-sources.php' );
sa_sl_assert( false === strpos( $sa_src_only, "'sa_county_siblings_html'" ), 'sources section: the sibling accordion is printed once, by sa_sources_section (2.22.145)' );
/* v2.22.145 — بخش‌های صفحه در فایل‌های سکشن جدا شده‌اند؛ ادعاهای ترتیب روی
 * همان سکشن‌ها بررسی می‌شوند (رجیستری: گالری → امتیاز → منابع). */
$tp86 = (string) file_get_contents( $theme_dir . '/template-parts/county/section-identity.php' );
sa_sl_assert( false !== strpos( $tp86, 'sa_county_siblings_html' ), 'template part: sibling list stashed for the article end (2.22.86)' );
// v2.22.116 (owner order, 18 Nov 2026): the sibling list is a CLOSED accordion
// («شهرهای هم‌استانی» with a + sign), so it takes one line above the sources
// instead of a wall of links.
sa_sl_assert( false !== strpos( $tp86, '<details class="sa-county-accordion" id="county-siblings">' ), 'siblings: the list opens as a closed accordion (2.22.116)' );
sa_sl_assert( false === strpos( $tp86, 'id="county-siblings" open' ), 'siblings: the accordion never starts open (2.22.116)' );

// The county order the owner fixed for 2.22.116: gallery → user rating → sources.
$sa_registry = (string) file_get_contents( $theme_dir . '/inc/county-sections.php' );
$pos_gallery = strpos( $sa_registry, "'sa-county-gallery'" );
$pos_rating  = strpos( $sa_registry, "'sa-county-rating'" );
$pos_sources = strpos( $sa_registry, "'sa-county-sources'" );
sa_sl_assert( false !== $pos_gallery, 'order: the county gallery is printed in the entity template (2.22.116)' );
sa_sl_assert( false !== $pos_rating && $pos_gallery < $pos_rating, 'order: the gallery sits above the user rating box (2.22.116)' );
sa_sl_assert( $pos_rating < $pos_sources, 'order: the user rating box sits above the sources (2.22.116)' );
sa_sl_assert( false === strpos( $single, 'sa_photo_spotlight' ), 'order: «مکان در یک نگاه» is gone from the county page (2.22.116)' );

echo "PASS: sources last (list stashed during body render, printed as the last article section, prev/next navigation closes the page)\n";
