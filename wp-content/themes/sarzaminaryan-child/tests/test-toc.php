<?php
/**
 * Standalone tests for the automatic table of contents (inc/toc.php, v2.18.17).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-toc.php
 *
 * Covers the owner's rule: «همهٔ مقاله‌های سایت فهرست مطالب داشته باشند» —
 * the ordered list is built from the article's own headings (H2–H4), every
 * heading gets a stable anchor, the page sections the template renders outside
 * the body (key facts, county blocks, FAQ, sources) join the same list in page
 * order, and nothing is printed in the editor, feeds, REST, archives or on
 * post types the theme does not cover.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array(
	'mods'        => array(),
	'singular'    => true,
	'in_loop'     => true,
	'main_query'  => true,
	'is_admin'    => false,
	'is_feed'     => false,
	'ajax'        => false,
	'current'     => 7,
	'post_type'   => 'post',
	'meta'        => array(),
);

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/* ---- WordPress shims. ---- */
function add_action() {
	return true;
}
function add_filter() {
	return true;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function get_theme_mod( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['t']['mods'] ) ? $GLOBALS['t']['mods'][ $key ] : $default;
}
function is_admin() {
	return (bool) $GLOBALS['t']['is_admin'];
}
function is_feed() {
	return (bool) $GLOBALS['t']['is_feed'];
}
function wp_doing_ajax() {
	return (bool) $GLOBALS['t']['ajax'];
}
function is_singular( $type = '' ) {
	if ( ! $GLOBALS['t']['singular'] ) {
		return false;
	}
	return '' === $type ? true : ( $type === $GLOBALS['t']['post_type'] );
}
function in_the_loop() {
	return (bool) $GLOBALS['t']['in_loop'];
}
function is_main_query() {
	return (bool) $GLOBALS['t']['main_query'];
}
function get_the_ID() {
	return (int) $GLOBALS['t']['current'];
}
function get_post_type( $post_id = 0 ) {
	return (string) $GLOBALS['t']['post_type'];
}
function get_post_meta( $post_id, $key, $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['meta'][ $post_id ][ $key ] ) ? $GLOBALS['t']['meta'][ $post_id ][ $key ] : '';
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function sa_fa_digits( $value ) {
	return str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), (string) $value );
}

/* ---- Theme stand-ins. ---- */
function sa_entity_types() {
	return array( 'province', 'city', 'attraction' );
}
function sa_uncertainty_markers() {
	return array( '[نیازمند منبع]' );
}
function sa_get_faq( $post_id ) {
	return ! empty( $GLOBALS['t']['faq'][ $post_id ] ) ? $GLOBALS['t']['faq'][ $post_id ] : array();
}
function sa_get_sources( $post_id ) {
	return ! empty( $GLOBALS['t']['sources'][ $post_id ] ) ? $GLOBALS['t']['sources'][ $post_id ] : array();
}

require_once dirname( __DIR__ ) . '/inc/toc.php';

$article = '<p>سرآغاز مقاله.</p>'
	. '<h2>شناسنامه سریع</h2><p>متن.</p>'
	. '<h3>موقعیت و ارتفاع</h3><p>متن.</p>'
	. '<h2>مسیر دسترسی و مقررات</h2><p>متن.</p>'
	. '<h2 id="already">سرفصل با شناسهٔ موجود</h2><p>متن.</p>';

/* ---------------------------------------------------------------------------
 * 1) Switch and guards.
 * ------------------------------------------------------------------------- */
$GLOBALS['t']['mods']['sa_show_toc'] = false;
sa_t_assert( false === sa_toc_enabled(), 'the customizer key is read' );
sa_t_assert( $article === sa_toc_filter( $article ), 'off switch leaves the body untouched' );
$GLOBALS['t']['mods']['sa_show_toc'] = true;

sa_t_assert( true === sa_toc_should_run(), 'a published article in the main loop is covered' );
$GLOBALS['t']['is_admin'] = true;
sa_t_assert( false === sa_toc_should_run(), 'the editor never gets a table of contents' );
$GLOBALS['t']['is_admin'] = false;
$GLOBALS['t']['is_feed']  = true;
sa_t_assert( false === sa_toc_should_run(), 'feeds never get a table of contents' );
$GLOBALS['t']['is_feed'] = false;
$GLOBALS['t']['singular'] = false;
sa_t_assert( false === sa_toc_should_run(), 'archives never get a table of contents' );
$GLOBALS['t']['singular'] = true;
$GLOBALS['t']['main_query'] = false;
sa_t_assert( false === sa_toc_should_run(), 'side queries (related cards…) never get one' );
$GLOBALS['t']['main_query'] = true;
$GLOBALS['t']['post_type'] = 'page';
sa_t_assert( false === sa_toc_should_run(), 'plain pages are out of scope' );
$GLOBALS['t']['post_type'] = 'attraction';
sa_t_assert( true === sa_toc_should_run(), 'attractions are covered' );
$GLOBALS['t']['post_type'] = 'post';

/* ---------------------------------------------------------------------------
 * 2) The list itself: anchors, order, nesting, Persian numerals.
 * ------------------------------------------------------------------------- */
sa_toc_reset();
$out = sa_toc_filter( $article );
sa_t_assert( 0 === strpos( $out, '<nav class="sa-toc"' ), 'the list is the first thing in the body' );
sa_t_assert( false !== strpos( $out, '>فهرست مطالب</h2>' ), 'the box is titled «فهرست مطالب»' );
sa_t_assert( false !== strpos( $out, '>۴ سرفصل<' ), 'the count line uses Persian digits' );
sa_t_assert( false !== strpos( $out, 'href="#sa-sec-شناسنامه-سریع"' ), 'a Persian, readable anchor is generated' );
sa_t_assert( false !== strpos( $out, '<h2 id="sa-sec-شناسنامه-سریع">شناسنامه سریع</h2>' ), 'the heading itself carries the same anchor' );
sa_t_assert( false !== strpos( $out, 'href="#already"' ), 'an existing heading id is reused, not replaced' );
sa_t_assert( false !== strpos( $out, '<h2 id="already">' ), 'an existing heading id stays on the tag' );
sa_t_assert( false !== strpos( $out, 'sa-toc__item--l2' ), 'H3 headings nest one level deeper' );
sa_t_assert( 1 === substr_count( $out, 'class="sa-toc"' ), 'the box is printed once' );
sa_t_assert( $out === sa_toc_filter( $out ), 'a second run for the same post does not stack another box' );

/* Hand-written list (owner’s own markup) wins. */
sa_toc_reset();
$manual = '<nav class="sa-toc"><ol class="sa-toc__list"></ol></nav><h2>یک</h2><h2>دو</h2>';
sa_t_assert( $manual === sa_toc_filter( $manual ), 'an existing manual list stops the automatic one' );

/* ---------------------------------------------------------------------------
 * 3) Minimum headings + the {{TOC}} marker.
 * ------------------------------------------------------------------------- */
sa_toc_reset();
$GLOBALS['t']['mods']['sa_toc_min'] = 6;
sa_t_assert( false === strpos( sa_toc_filter( $article ), 'class="sa-toc"' ), 'too few headings → no box' );
$GLOBALS['t']['mods']['sa_toc_min'] = 2;

sa_toc_reset();
$marked = '<p>پیش‌گفتار.</p>{{TOC}}<h2>الف</h2><h2>ب</h2>';
$with_marker = sa_toc_filter( $marked );
sa_t_assert( false === strpos( $with_marker, '{{TOC}}' ), 'the marker is consumed' );
sa_t_assert( false !== strpos( $with_marker, 'پیش‌گفتار.</p><nav class="sa-toc"' ), 'the box lands where the marker was' );

sa_toc_reset();
$GLOBALS['t']['mods']['sa_toc_min'] = 6;
sa_t_assert( false === strpos( sa_toc_filter( $marked ), '{{TOC}}' ), 'an unused marker is cleaned up when there is no box' );
$GLOBALS['t']['mods']['sa_toc_min'] = 2;

/* ---------------------------------------------------------------------------
 * 4) Template sections (key facts · county · FAQ · sources) join the list in page order.
 * ------------------------------------------------------------------------- */
sa_toc_reset();
$GLOBALS['t']['post_type'] = 'attraction';
$GLOBALS['t']['faq'][7]     = array( array( 'q' => 'پرسش', 'a' => 'پاسخ' ) );
$GLOBALS['t']['sources'][7] = array( array( 'title' => 'منبع' ) );
sa_toc_register( 'اطلاعات کلیدی', 'facts', 10 );
sa_toc_register( 'اطلاعات کلیدی', 'facts', 10 ); // دوباره: نباید تکرار شود.
sa_toc_register( 'سوالات متداول', 'faq', 900 );
sa_toc_register_tail( 7 );
sa_toc_register( 'منابع', 'sources', 990 );
$entity_body = '<h2>مسیر دسترسی</h2><p>متن.</p><h2>نکات ایمنی</h2><p>متن.</p>';
$entity_out  = sa_toc_filter( $entity_body );
$order = array();
foreach ( array( '#facts', '#sa-sec-مسیر-دسترسی', '#sa-sec-نکات-ایمنی', '#faq', '#sources' ) as $needle ) {
	$order[ $needle ] = strpos( $entity_out, 'href="' . $needle . '"' );
}
sa_t_assert( false !== $order['#facts'], '«اطلاعات کلیدی» is listed' );
sa_t_assert( false !== $order['#faq'], '«سوالات متداول» is listed when the FAQ exists' );
sa_t_assert( false !== $order['#sources'], '«منابع» is listed when sources exist' );
sa_t_assert( $order['#facts'] < $order['#sa-sec-مسیر-دسترسی'], 'key facts come before body headings' );
sa_t_assert( $order['#sa-sec-مسیر-دسترسی'] < $order['#faq'], 'FAQ comes after body headings' );
sa_t_assert( $order['#faq'] < $order['#sources'], 'sources are last' );
sa_t_assert( 1 === substr_count( $entity_out, 'href="#facts"' ), 'a section is never listed twice' );

/* No FAQ + no sources → those entries disappear (no dead anchors). */
sa_toc_reset();
$GLOBALS['t']['faq'][7]     = array();
$GLOBALS['t']['sources'][7] = array();
sa_toc_register_tail( 7 );
sa_t_assert( false === strpos( sa_toc_filter( '<h2>یک</h2><h2>دو</h2>' ), 'href="#faq"' ), 'a missing FAQ leaves no dead anchor' );
$GLOBALS['t']['post_type'] = 'post';

/* ---------------------------------------------------------------------------
 * 5) Opting one post out, and empty bodies.
 * ------------------------------------------------------------------------- */
sa_toc_reset();
$GLOBALS['t']['meta'][7]['_sa_no_toc'] = '1';
sa_t_assert( false === strpos( sa_toc_filter( $article ), 'class="sa-toc"' ), 'the per-post meta flag opts an article out' );
$GLOBALS['t']['meta'][7]['_sa_no_toc'] = '';
sa_toc_reset();
sa_t_assert( '' === sa_toc_filter( '' ), 'empty bodies stay empty' );
sa_toc_reset();
sa_t_assert( '<p>بدون سرفصل.</p>' === sa_toc_filter( '<p>بدون سرفصل.</p>' ), 'a body without headings gets no box' );

/* ---------------------------------------------------------------------------
 * 6) Every bundled article really gets a list (the owner's rule: «همهٔ مقاله‌ها»).
 * ------------------------------------------------------------------------- */
$records = array();
foreach ( array( 'data/ready-articles.php', 'data/ready-guides.php' ) as $rel ) {
	$file = dirname( __DIR__ ) . '/' . $rel;
	if ( file_exists( $file ) ) {
		$set = include $file;
		if ( is_array( $set ) ) {
			$records = array_merge( $records, $set );
		}
	}
}
sa_t_assert( isset( $records['gahar-lake-dorud'] ), 'the bundled attraction data is readable by this test' );

$checked = 0;
foreach ( $records as $key => $record ) {
	$body = isset( $record['content'] ) ? (string) $record['content'] : '';
	if ( '' === $body ) {
		continue;
	}
	sa_toc_reset();
	$used    = sa_toc_used_ids( $body );
	$items   = array();
	$scanned = sa_toc_scan_headings( $body, $used, $items );
	$toc     = sa_toc_markup( $items );

	sa_t_assert( '' !== $toc, $key . ': every bundled article gets a «فهرست مطالب»' );
	sa_t_assert( count( $items ) >= 2, $key . ': the list has at least two headings' );

	$ids = array();
	foreach ( $items as $item ) {
		$ids[] = $item['id'];
		sa_t_assert( false !== strpos( $scanned, 'id="' . $item['id'] . '"' ), $key . ': anchor ' . $item['id'] . ' really exists in the body' );
	}
	sa_t_assert( count( $ids ) === count( array_unique( $ids ) ), $key . ': anchors are unique' );
	sa_t_assert( count( $ids ) === substr_count( $toc, 'class="sa-toc__item' ), $key . ': every heading is listed once' );
	++$checked;
}
sa_t_assert( $checked >= 95, 'all bundled articles were checked (' . $checked . ')' );

echo "PASS: table of contents (all " . $checked . " bundled articles, stable anchors, template sections in page order, no editor/feed/archive output)\n";
