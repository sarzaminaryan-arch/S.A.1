<?php
/**
 * Standalone test: «شناسنامهٔ کوتاه» و بلوک «از مردم عزیز …، یک درخواست داریم»
 * از بدنهٔ مقاله‌ها حذف می‌شوند (2.22.84).
 *
 * Owner order (2026-11-15): the short identity table at the top of county
 * articles duplicates the county identity card the template renders above the
 * body, and the closing reader-request block (ارسال گزارش و تصحیح و تصویر با
 * ایمیل سایت) leaves the site completely. Both are removed at render time —
 * the database text stays untouched — and the filters run before sa_toc_filter
 * (11) so the table of contents loses the entries by itself.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-body-strip.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array( 'meta' => array(), 'is_admin' => false );

function sa_bs_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_action() { return true; }
function apply_filters( $tag, $value = null ) { return $value; }
function add_filter() { return true; }
function remove_filter() { return true; }
function is_admin() { return (bool) $GLOBALS['t']['is_admin']; }
function is_feed() { return false; }
function is_singular() { return true; }
function get_the_ID() { return 270; }
function home_url() { return 'https://example.test'; }
function get_queried_object_id() { return 270; }
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

/* ---- 1. «شناسنامهٔ کوتاه» — legacy page, hamza variant ---- */
$legacy = '<h2>آغاجاری؛ شهری که نفت ساختش</h2>'
	. '<p>متن آغازین.</p>'
	. '<h2>شناسنامهٔ کوتاه آغاجاری</h2>'
	. '<table><thead><tr><th>شاخص</th></tr></thead><tbody><tr><td>مرکز شهرستان</td></tr></tbody></table>'
	. '<h2>کجای نقشه است؟</h2>'
	. '<p>متن بخش نقشه.</p>';
$out = sa_strip_duplicate_body_short_id( $legacy );
sa_bs_assert( false === strpos( $out, 'شناسنامه' ), 'card: legacy hamza heading and its table are removed' );
sa_bs_assert( false === strpos( $out, '<table' ), 'card: the identity table under the heading is removed' );
sa_bs_assert( false !== strpos( $out, 'کجای نقشه است؟' ) && false !== strpos( $out, 'شهری که نفت ساختش' ), 'card: the sections before and after survive' );

/* ---- 2. plain-space and ZWNJ variants ---- */
$plain = '<h2>شناسنامه کوتاه آبادان</h2><p>توضیح جدول.</p><h2>مردم آبادان</h2><p>متن.</p>';
sa_bs_assert( false === strpos( sa_strip_duplicate_body_short_id( $plain ), 'شناسنامه' ), 'card: plain «شناسنامه کوتاه» variant is removed' );
$zwnj = '<h2>شناسنامه‌ی کوتاه بهبهان</h2><ul><li>جمعیت</li></ul><h2>تاریخ</h2><p>متن.</p>';
sa_bs_assert( false === strpos( sa_strip_duplicate_body_short_id( $zwnj ), 'شناسنامه' ), 'card: ZWNJ «شناسنامه‌ی کوتاه» variant is removed' );

/* ---- 3. marked page: the sa:sec:card markers go with the section ---- */
$marked = '<!-- sa:sec:intro -->' . "\n" . '<h2>آبادان؛ معرفی</h2>' . "\n" . '<p>متن.</p>' . "\n" . '<!-- /sa:sec:intro -->' . "\n\n"
	. '<!-- sa:sec:card -->' . "\n"
	. '<!-- wp:heading --><h2 class="wp-block-heading">شناسنامهٔ کوتاه آبادان</h2><!-- /wp:heading -->' . "\n"
	. '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>۲۹۸٬۰۹۰ نفر</td></tr></tbody></table></figure><!-- /wp:table -->' . "\n"
	. '<!-- /sa:sec:card -->' . "\n\n"
	. '<!-- sa:sec:map -->' . "\n" . '<h2>کجای نقشه است و همسایه‌هایش کیست‌اند؟</h2>' . "\n" . '<p>متن.</p>' . "\n" . '<!-- /sa:sec:map -->';
$out = sa_strip_duplicate_body_short_id( $marked );
sa_bs_assert( false === strpos( $out, 'sa:sec:card' ), 'card: both sa:sec:card markers disappear with the section' );
sa_bs_assert( false === strpos( $out, 'شناسنامه' ) && false === strpos( $out, '<table' ), 'card: marked heading, table and stray wp:heading comment are gone' );
sa_bs_assert( false !== strpos( $out, 'sa:sec:intro' ) && false !== strpos( $out, 'sa:sec:map' ), 'card: neighbouring section markers are untouched' );
sa_bs_assert( false === strpos( $out, 'wp:heading -->' ), 'card: no orphaned Gutenberg comment is left behind' );

/* ---- 4. page without the section: unchanged; admin untouched ---- */
$clean = '<h2>آبادان؛ معرفی</h2><p>متن.</p>';
sa_bs_assert( sa_strip_duplicate_body_short_id( $clean ) === $clean, 'card: page without the section passes through unchanged' );
$GLOBALS['t']['is_admin'] = true;
sa_bs_assert( sa_strip_duplicate_body_short_id( $legacy ) === $legacy, 'card: admin/feed requests are never filtered' );
$GLOBALS['t']['is_admin'] = false;

/* ---- 5. reader-request block — legacy page ---- */
$request = '<h2>چند نکتهٔ کوچک از آغاجاری</h2>' . "\n" . '<ul><li>نکته</li></ul>' . "\n\n"
	. '<h2>از مردم عزیز شهرستان آغاجاری، یک درخواست داریم</h2>' . "\n"
	. '<p>اگر گزارشی یا تصویری از این شهرستان دارید برایمان بفرستید.</p>';
$out = sa_strip_reader_request_filter( $request );
sa_bs_assert( false === strpos( $out, 'از مردم' ) && false === strpos( $out, 'یک درخواست داریم' ), 'request: the closing block is deleted, not converted to an aside' );
sa_bs_assert( false === strpos( $out, 'sa-community' ), 'request: no aside markup is produced anymore' );
sa_bs_assert( false !== strpos( $out, 'چند نکتهٔ کوچک' ), 'request: the section above the block stays' );

/* ---- 6. reader-request block — marked page ---- */
$marked_req = '<!-- sa:sec:tips -->' . "\n" . '<h2>چند نکته</h2>' . "\n" . '<!-- /sa:sec:tips -->' . "\n\n"
	. '<!-- sa:sec:request -->' . "\n"
	. '<!-- wp:heading --><h2 class="wp-block-heading">از مردم عزیز شهرستان آبادان، یک درخواست داریم</h2><!-- /wp:heading -->' . "\n"
	. '<!-- wp:paragraph --><p>متن درخواست.</p><!-- /wp:paragraph -->' . "\n"
	. '<!-- /sa:sec:request -->';
$out = sa_strip_reader_request_filter( $marked_req );
sa_bs_assert( false === strpos( $out, 'sa:sec:request' ), 'request: both section markers are swallowed' );
sa_bs_assert( false === strpos( $out, 'از مردم' ) && false === strpos( $out, 'wp:paragraph' ), 'request: the whole block disappears' );
sa_bs_assert( false !== strpos( $out, 'sa:sec:tips' ), 'request: the tips section before it is untouched' );

/* ---- 7. no block, h3 shape, admin ---- */
sa_bs_assert( sa_strip_reader_request_filter( $clean ) === $clean, 'request: page without the block passes through unchanged' );
$h3 = '<h2>چند نکتهٔ کوچک از آزمون</h2><p>متن مقاله.</p><h3>از مردم عزیز این دیار، یک درخواست داریم</h3><p>متن درخواست.</p>';
sa_bs_assert( false === strpos( sa_strip_reader_request_filter( $h3 ), 'از مردم' ), 'request: h3 shape of the heading is removed too' );
sa_bs_assert( false !== strpos( sa_strip_reader_request_filter( $h3 ), 'متن مقاله.' ), 'request: content above an h3 block stays' );
$GLOBALS['t']['is_admin'] = true;
sa_bs_assert( sa_strip_reader_request_filter( $request ) === $request, 'request: admin/feed requests are never filtered' );
$GLOBALS['t']['is_admin'] = false;

echo "PASS: body strip (شناسنامهٔ کوتاه and the reader-request block are removed at render time, legacy and marked pages, TOC runs after)\n";
