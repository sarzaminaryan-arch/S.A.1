<?php
/** Offline regression: evidence moves, references do not disappear. No live HTTP. */
define( 'ABSPATH', __DIR__ . '/' );
function add_action() {}
function add_filter() {}
function apply_filters( $tag, $value ) { return $value; }
function is_admin() { return false; }
function is_feed() { return false; }
function is_singular() { return true; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_parse_url( $value, $part = -1 ) { return parse_url( $value, $part ); }
function home_url() { return 'https://fixture.invalid'; }
function get_the_ID() { return isset( $GLOBALS['sa_test_post'] ) ? $GLOBALS['sa_test_post'] : 7; }
function get_post_meta( $id, $key, $single ) { return isset( $GLOBALS['sa_test_meta'][ $id ][ $key ] ) ? $GLOBALS['sa_test_meta'][ $id ][ $key ] : ''; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return preg_match( '~^https?://~', $value ) ? esc_attr( $value ) : ''; }
function sa_fa_digits( $value ) { return strtr( (string) $value, array( '0'=>'۰', '1'=>'۱', '2'=>'۲', '3'=>'۳', '4'=>'۴', '5'=>'۵', '6'=>'۶', '7'=>'۷', '8'=>'۸', '9'=>'۹' ) ); }
function the_content() { echo $GLOBALS['sa_test_filtered_content']; }
require dirname( __DIR__ ) . '/inc/template-tags.php';
require dirname( __DIR__ ) . '/inc/citations.php';
require dirname( __DIR__ ) . '/inc/article-sections.php';
require dirname( __DIR__ ) . '/inc/article-sources.php';
function sa_sources_assert( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } }
function sa_sources_test_render( $html, $meta = '' ) {
	$GLOBALS['sa_test_meta'][ get_the_ID() ]['sa_sources'] = $meta;
	$GLOBALS['sa_test_filtered_content'] = $html;
	ob_start(); sa_article_content_with_sources(); $body = ob_get_clean();
	ob_start(); sa_article_final_sources( get_the_ID() ); $sources = ob_get_clean();
	return array( $body, $sources );
}
function sa_sources_test_links( $html, $label ) {
	$nodes = sa_article_section_nodes( $html ); $ids = array();
	foreach ( $nodes as $node ) { $id = sa_article_section_attr( $node['raw'], 'id' ); if ( '' !== $id ) { sa_sources_assert( ! isset( $ids[ $id ] ), $label . ': unique ID ' . $id ); $ids[ $id ] = true; } }
	foreach ( $nodes as $node ) {
		if ( 'a' !== $node['tag'] ) { continue; }
		$href = sa_article_section_attr( $node['raw'], 'href' );
		if ( '' !== $href && '#' === $href[0] ) { sa_sources_assert( isset( $ids[ rawurldecode( substr( $href, 1 ) ) ] ), $label . ': local target ' . $href ); }
	}
}
$input = '<h2 id="intro">معرفی</h2><p><a href="/city/existing/">پیوند داخلی</a> <a href="https://example.org/a?x=1&amp;y=2">شاهد</a></p>';
$filtered = sa_citations_filter( $input );
list( $body, $sources ) = sa_sources_test_render( $filtered, "عنوان منبع | https://example.org/a?x=1&y=2\nمنبع دوم | https://example.org/b\n---\nPRIVATE_EDITOR_NOTE" );
sa_sources_assert( false === strpos( $body, 'sa-footnotes__list' ) && false !== strpos( $body, 'href="#sa-ref-1"' ), 'generated footnotes moved, inline markers stay' );
sa_sources_assert( false !== strpos( $body, 'href="/city/existing/"' ), 'internal destination link unchanged' );
sa_sources_assert( 1 === substr_count( $sources, 'href="https://example.org/a?x=1&amp;y=2"' ), 'exact meta duplicate omitted, not the referenced entry' );
sa_sources_assert( false === strpos( $sources, 'PRIVATE_EDITOR_NOTE' ), 'private source notes never exported to page' );
sa_sources_assert( 1 === substr_count( $sources, '>منابع</h2>' ) && false === strpos( $sources, '>پانویس‌ها و ارجاع‌های متن</h2>' ), 'one sources heading' );
sa_sources_test_links( $body . $sources, 'generated' );
list( $again_body, $again_sources ) = sa_sources_test_render( $body . $sources );
sa_sources_test_links( $again_body . $again_sources, 'reprocessed final source block' );
sa_sources_assert( 1 === substr_count( $again_sources, '>منابع</h2>' ), 'reprocessing does not duplicate final heading IDs' );
$manual = '<p><a id="back" href="#old-note">ارجاع</a></p><section id="references"><h2 id="old-title">منابع و مآخذ</h2><ol><li id="old-note">کتاب چاپی <a href="#back">بازگشت</a></li></ol></section><h2 id="next">بخش دیگر</h2><p>KEEP</p>';
list( $body, $sources ) = sa_sources_test_render( $manual );
sa_sources_assert( false !== strpos( $body, 'KEEP' ) && false === strpos( $body, 'کتاب چاپی' ) && false !== strpos( $sources, 'کتاب چاپی' ), 'explicit source block moved, next section stays' );
sa_sources_test_links( $body . $sources, 'manual source and backlink' );
$flat = '<h2 id="flat-ref">منابع</h2><p id="print">چاپ فرهنگی نمونه</p><h2 id="other">پس از منابع</h2><p>KEEP</p>';
list( $body, $sources ) = sa_sources_test_render( $flat );
sa_sources_assert( '<h2 id="other">پس از منابع</h2><p>KEEP</p>' === $body && false !== strpos( $sources, 'id="flat-ref"' ), 'flat peer boundary and old heading alias' );
$wrapped_next = '<div class="gallery"><p>GALLERY_INTRO</p><h2 id="gallery">گالری</h2><p>GALLERY_KEEP</p></div>';
list( $body, $sources ) = sa_sources_test_render( '<h2 id="ref">منابع</h2><ul><li>کتاب</li></ul>' . $wrapped_next );
sa_sources_assert( $wrapped_next === $body && false === strpos( $sources, 'GALLERY_KEEP' ), 'next peer inside another wrapper is not swallowed' );
$identity = '<section class="sa-cyber-id" id="county-identity"><h2>منابع</h2><p>IDENTITY_KEEP</p></section>';
$code = '<pre><h2>منابع</h2><p>CODE_KEEP</p></pre>';
list( $body, $sources ) = sa_sources_test_render( $identity . $code );
sa_sources_assert( $identity . $code === $body && '' === $sources, 'identity/code byte-identical, no empty section' );
list( $body, $sources ) = sa_sources_test_render( '<section id="sources"><h2 id="empty-title">منابع</h2></section>' );
sa_sources_assert( false === strpos( $body . $sources, '<h2' ) && false !== strpos( $sources, 'id="empty-title"' ) && false !== strpos( $sources, 'id="sources"' ), 'empty heading removed but old targets survive' );
$toc = '<nav class="sa-toc"><span class="sa-toc__count">۳ سرفصل</span><ol class="sa-toc__list"><li><a href="#keep">باقی</a></li><li><a href="#old-title">پانویس</a></li><li><a href="#sources">منابع</a></li></ol></nav>';
list( $body, $sources ) = sa_sources_test_render( $toc . '<h2 id="keep">باقی</h2>' . $manual );
sa_sources_assert( 1 === substr_count( $body, 'href="#sources"' ) && false === strpos( $body, 'href="#old-title"' ) && false !== strpos( $body, '>۲ سرفصل</span>' ), 'theme TOC merges source entries and updates count' );
sa_sources_test_links( $body . $sources, 'reconciled TOC' );
list( $body, $sources ) = sa_sources_test_render( '<ol class="references" id="source-list"><li id="cite-note-1">نام کتاب</li></ol><p><a href="#cite-note-1">یادداشت</a></p>' );
sa_sources_assert( false !== strpos( $sources, '<ol class="references"' ), 'bare reference list keeps its list wrapper' );
sa_sources_test_links( $body . $sources, 'bare reference list' );
$many = '<p>';
for ( $i = 1; $i <= 7; ++$i ) { $many .= '<a href="https://example.org/evidence-' . $i . '">شاهد ' . $i . '</a>'; }
$many .= '</p>';
list( $body, $sources ) = sa_sources_test_render( sa_citations_filter( $many ) );
sa_sources_assert( 7 === substr_count( $sources, 'class="sa-footnotes__item"' ), 'do not silently discard cited evidence to impose the unverified five-source limit' );
sa_sources_test_links( $body . $sources, 'seven cited sources' );
$GLOBALS['sa_test_post'] = 8;
list( $other_body, $other_sources ) = sa_sources_test_render( '<p>OTHER_POST</p>' );
sa_sources_assert( '' === $other_sources, 'state cannot leak across articles' );
$GLOBALS['sa_test_post'] = 7;
for ( $i = 0; $i < 480; ++$i ) {
	list( $body, $sources ) = sa_sources_test_render( sa_citations_filter( '<h2>مشخصات کلی</h2><p>DELETE</p><h2 id="keep">بخش باقی</h2><p><a href="https://example.org/ref-' . $i . '">شاهد</a></p>' ) );
	$body = sa_remove_retired_article_sections( $body );
	sa_sources_assert( false === strpos( $body, 'DELETE' ) && false === strpos( $body, '<h2>مشخصات کلی' ), 'phase 2 remains removed' );
	sa_sources_test_links( $body . $sources, 'synthetic-' . $i );
}
$count = 0;
foreach ( array( 'ready-articles.php', 'ready-guides.php' ) as $file ) {
	$records = include dirname( __DIR__ ) . '/data/' . $file;
	foreach ( $records as $slug => $record ) {
		$raw = $record['content'];
		$filtered = sa_citations_filter( sa_remove_retired_article_sections( $raw ) );
		list( $body, $sources ) = sa_sources_test_render( $filtered, isset( $record['meta']['sa_sources'] ) ? $record['meta']['sa_sources'] : '' );
		// Check citation targets, not arbitrary pre-existing/editor/plugin fragment links.
		preg_match_all( '/href="#(sa-ref-[0-9]+)"/', $body, $matches );
		foreach ( $matches[1] as $target ) { sa_sources_assert( false !== strpos( $sources, 'id="' . $target . '"' ), $slug . ': reference target survives' ); }
		sa_sources_assert( substr_count( $sources, '>منابع</h2>' ) <= 1, $slug . ': single final heading' );
		++$count;
	}
}
foreach ( array( 'entity/single.php', 'content-single-post.php' ) as $file ) {
	$template = file_get_contents( dirname( __DIR__ ) . '/template-parts/' . $file );
	sa_sources_assert( false !== strpos( $template, 'sa_article_content_with_sources()' ), 'full article uses collector: ' . $file );
	$tail = substr( $template, strrpos( $template, 'sa_article_final_sources( $sa_id )' ) );
	sa_sources_assert( ! preg_match( '/(?:get_template_part|sa_guide_related|sa_facts_checked_note|sa_entity_navigation)\s*\(/', $tail ), 'sources after other template sections: ' . $file );
}
if ( isset( $argv[1] ) && '--fixture' === $argv[1] ) {
	$GLOBALS['sa_test_post'] = 7;
	list( $body, $sources ) = sa_sources_test_render( $toc . '<h2 id="keep">متن مقاله</h2>' . $manual, "منبع نمونه | https://example.org/one\nمنبع دیگر | https://example.org/two" );
	echo '<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><body><main class="container"><article>' . $body . '<section id="faq"><h2>سوالات متداول</h2><p>پاسخ ساختگی آزمون.</p></section><p>یادداشت بررسی</p>' . $sources . '</article></main></body></html>';
	return;
}
echo 'PASS: source consolidation, preserved IDs/backlinks, protected identity, empty headings, no invented/truncated evidence; 480 synthetic + ' . $count . ' bundled records (not live articles).' . PHP_EOL;
