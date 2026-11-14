<?php
/**
 * Standalone test: og:image share cards (2.22.74).
 *
 * Audit 2026-11-15: 593 of 627 pages had no og:image. The owner left the
 * choice to "whatever is closest to Google's standard": a real featured photo
 * when there is one, otherwise a unique 1200×630 card for that page (title +
 * type + province), never the bare site logo.
 *
 * Locked rules guarded here:
 *  1. Persian shaping: letters get the right contextual presentation forms
 *     (initial/medial/final/isolated, lam-alef ligature, ZWNJ breaks joining)
 *     and the visual order is RTL with Latin/digit runs kept LTR.
 *  2. Card text: city → «راهنمای سفر شهرستان» + «استان X»; attraction →
 *     «دیدنی» + city + province; unpublished posts get no card.
 *  3. A 1200×630 JPEG is rendered once into uploads/sa-og/ and reused; the
 *     filename changes when the text changes.
 *  4. Head output: real featured image first, card otherwise, with
 *     og:image:width/height/alt; customizer switch sa_og_cards can turn it off.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-og-card.php
 * Optional: SA_OG_TEST_OUT=/some/dir keeps the rendered cards for viewing.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
$theme_dir = dirname( __DIR__ );
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', $theme_dir . '/' );
}

function sa_og_t( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$out_dir = getenv( 'SA_OG_TEST_OUT' );
$keep    = is_string( $out_dir ) && '' !== $out_dir;
$base    = $keep ? rtrim( $out_dir, '/' ) : sys_get_temp_dir() . '/sa-og-test-' . getmypid();
if ( ! is_dir( $base ) ) {
	mkdir( $base, 0777, true );
}

class SA_OG_Post { // phpcs:ignore
	public $ID;
	public $post_type;
	public $post_title;
	public $post_status;
	public function __construct( $id, $type, $title, $status = 'publish' ) {
		$this->ID          = $id;
		$this->post_type   = $type;
		$this->post_title  = $title;
		$this->post_status = $status;
	}
}

$GLOBALS['og'] = array(
	'posts' => array(
		1  => new SA_OG_Post( 1, 'province', 'استان اصفهان' ),
		2  => new SA_OG_Post( 2, 'city', 'شهرستان خمینی‌شهر' ),
		3  => new SA_OG_Post( 3, 'attraction', 'کلیسای وانک (کلیسای آمنا پرکیچ)' ),
		4  => new SA_OG_Post( 4, 'city', 'شهرستان تازه', 'draft' ),
		5  => new SA_OG_Post( 5, 'post', 'راهنمای کامل انتخاب کیسه‌خواب برای کمپینگ زمستانی در ارتفاعات زاگرس و البرز با بودجهٔ ۲ میلیون تومان' ),
	),
	'meta'  => array(
		2 => array( 'sa_province_id' => 1 ),
		3 => array( 'sa_city_id' => 6 ),
		6 => array( 'sa_province_id' => 1 ),
	),
	'mods'  => array(),
);
$GLOBALS['og']['posts'][6] = new SA_OG_Post( 6, 'city', 'شهرستان اصفهان' );

function get_post( $id ) { return isset( $GLOBALS['og']['posts'][ (int) $id ] ) ? $GLOBALS['og']['posts'][ (int) $id ] : null; }
function get_post_status( $id ) { $p = get_post( $id ); return $p ? $p->post_status : false; }
function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
function get_post_meta( $id, $key, $single = false ) { unset( $single ); return isset( $GLOBALS['og']['meta'][ $id ][ $key ] ) ? $GLOBALS['og']['meta'][ $id ][ $key ] : ''; }
function get_the_category() { return array( (object) array( 'name' => 'آموزش‌های ضروری کمپینگ و بقا' ) ); }
function is_wp_error( $x ) { unset( $x ); return false; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function sa_province_name_for_post( $id ) { return 1 === (int) $id ? 'اصفهان' : ''; }
function sa_brand_name() { return 'سرزمین آریان'; }
function get_theme_mod( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['og']['mods'] ) ? $GLOBALS['og']['mods'][ $k ] : $d; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function sanitize_file_name( $s ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $s ); }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['og_base'], 'baseurl' => 'https://example.test/wp-content/uploads', 'error' => false ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function wp_is_writable( $d ) { return is_writable( $d ); }
function home_url( $p = '' ) { return 'https://sarzaminaryan.ir' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); } // phpcs:ignore
$GLOBALS['og_base'] = $base;

require_once $theme_dir . '/inc/og-card.php';

/* ---- 1. shaping ---------------------------------------------------------- */

$cp = function ( $s ) {
	$o = array();
	foreach ( preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY ) as $c ) {
		$o[] = sprintf( '%04X', mb_ord( $c, 'UTF-8' ) );
	}
	return implode( ' ', $o );
};
sa_og_t( 'FEB3 FEFC FEE1' === $cp( sa_og_join( 'سلام' ) ), '1a: «سلام» = seen-initial + lam-alef-final + meem-isolated (' . $cp( sa_og_join( 'سلام' ) ) . ')' );
sa_og_t( 'FEE1 FEFC FEB3' === $cp( sa_og_shape( 'سلام' ) ), '1b: visual order is reversed for RTL' );
sa_og_t( 'FB90 FEE4 FEE8 FEAA' === $cp( sa_og_join( 'کمند' ) ), '1c: keheh-initial, meem-medial, noon-medial, dal-final (' . $cp( sa_og_join( 'کمند' ) ) . ')' );
// ZWNJ: «می‌رود» → meem-initial, yeh-final(FBFD) [ZWNJ breaks], reh-isolated, waw-isolated, dal-isolated.
sa_og_t( 'FEE3 FBFD FEAD FEED FEA9' === $cp( sa_og_join( 'می‌رود' ) ), '1d: ZWNJ breaks the join and is dropped (' . $cp( sa_og_join( 'می‌رود' ) ) . ')' );
sa_og_t( 'FB94 FB95 FEE6' === $cp( sa_og_join( 'گگن' ) ), '1e: gaf initial + medial, noon final (' . $cp( sa_og_join( 'گگن' ) ) . ')' );
$mixed = sa_og_shape( 'سفر ۲ روزه با Sarzamin Aryan' );
sa_og_t( 0 === strpos( $mixed, 'Sarzamin Aryan' ), '1f: Latin run keeps its own order and sits at the visual left' );
sa_og_t( false !== strpos( sa_og_shape( 'آب (شیرین)' ), '(' ) && false !== strpos( sa_og_shape( 'آب (شیرین)' ), ')' ), '1g: parentheses survive (mirrored)' );
$p = sa_og_shape( 'آب (شیرین)' );
sa_og_t( strpos( $p, '(' ) < strpos( $p, ')' ), '1h: mirrored parentheses read correctly left-to-right in visual order' );

/* ---- 2. card text -------------------------------------------------------- */

$t = sa_og_card_text( 2 );
sa_og_t( 'راهنمای سفر شهرستان' === $t['kicker'] && 'استان اصفهان' === $t['sub'] && 'شهرستان خمینی‌شهر' === $t['title'], '2a: city card text' );
$t = sa_og_card_text( 3 );
sa_og_t( 'دیدنی' === $t['kicker'] && 'شهرستان اصفهان، استان اصفهان' === $t['sub'], '2b: attraction card text (city + province): ' . $t['sub'] );
sa_og_t( null === sa_og_card_text( 4 ), '2c: unpublished post gets no card' );
$t = sa_og_card_text( 1 );
sa_og_t( 'راهنمای سفر استان' === $t['kicker'], '2d: province card text' );

/* ---- 3. rendering -------------------------------------------------------- */

$gd_ok = function_exists( 'imagettfbbox' ) && is_array( @imagettfbbox( 20, 0, sa_og_font_file(), 'ab' ) ); // phpcs:ignore
if ( ! $gd_ok || getenv( 'SA_OG_REQUIRE_RENDER' ) === '0' ) {
	// بی‌FreeType: کارت ساخته نمی‌شود و هیچ نشانی‌ای برنمی‌گردد (رفتار مثل قبل).
	sa_og_t( ! sa_og_card_supported(), '3a: no FreeType/JPEG → cards disabled' );
	sa_og_t( '' === sa_og_card_url( 2 ), '3a: no FreeType/JPEG → no card URL' );
	sa_og_t( '1' !== getenv( 'SA_OG_REQUIRE_RENDER' ), '3a: rendering required (SA_OG_REQUIRE_RENDER=1) but GD has no FreeType' );
	fwrite( STDERR, "NOTE: GD without FreeType/JPEG here — rendering checks skipped.\n" );
	echo "PASS: og cards (shaping + card text; rendering skipped: no FreeType)\n";
	exit( 0 );
}
sa_og_t( sa_og_card_supported(), '3a: GD + bundled font available' );
$urls = array();
foreach ( array( 1, 2, 3, 5 ) as $id ) {
	$urls[ $id ] = sa_og_card_url( $id );
	sa_og_t( '' !== $urls[ $id ], "3b: card URL for post $id" );
	$file = $base . '/sa-og/' . basename( $urls[ $id ] );
	sa_og_t( file_exists( $file ), "3c: card file exists for post $id" );
	$info = getimagesize( $file );
	sa_og_t( $info && 1200 === $info[0] && 630 === $info[1] && 'image/jpeg' === $info['mime'], "3d: card $id is a 1200×630 JPEG" );
	sa_og_t( filesize( $file ) < 300 * 1024, "3e: card $id stays under 300 KB (messenger previews)" );
}
$site = sa_og_site_card_url();
sa_og_t( '' !== $site && 0 === strpos( basename( $site ), 'site-' ), '3f: site card for home/archives' );
$mtime = filemtime( $base . '/sa-og/' . basename( $urls[2] ) );
sa_og_t( $urls[2] === sa_og_card_url( 2 ) && filemtime( $base . '/sa-og/' . basename( $urls[2] ) ) === $mtime, '3g: second call reuses the file' );
$GLOBALS['og']['posts'][2]->post_title = 'شهرستان خمینی‌شهر (لنجان سفلی)';
sa_og_t( $urls[2] !== sa_og_card_url( 2 ), '3h: a new title produces a new card file' );
sa_og_t( '' === sa_og_card_url( 4 ), '3i: no card for drafts' );
$GLOBALS['og']['mods']['sa_og_cards'] = false;
sa_og_t( '' === sa_og_card_url( 1 ), '3j: customizer switch turns cards off' );
$GLOBALS['og']['mods'] = array();

/* ---- 4. head wiring ------------------------------------------------------ */

$seo = (string) file_get_contents( $theme_dir . '/inc/seo.php' );
sa_og_t( false !== strpos( $seo, 'sa_og_card_url( $id )' ), '4a: singular pages without a featured image use their own card' );
sa_og_t( false !== strpos( $seo, 'sa_og_site_card_url()' ), '4b: home/archives fall back to the site card' );
sa_og_t( false !== strpos( $seo, 'og:image:width' ) && false !== strpos( $seo, 'og:image:alt' ), '4c: og:image width/height/alt printed' );
sa_og_t( strpos( $seo, 'wp_get_attachment_image_src( $sa_thumb_id' ) < strpos( $seo, 'sa_og_card_url( $id )' ), '4d: real featured image wins over the card' );
$fn = (string) file_get_contents( $theme_dir . '/functions.php' );
sa_og_t( false !== strpos( $fn, "'inc/og-card.php'" ), '4e: module loaded' );
sa_og_t( is_readable( $theme_dir . '/assets/fonts/og/OFL.txt' ), '4f: font licence bundled' );

if ( ! $keep ) {
	foreach ( (array) glob( $base . '/sa-og/*' ) as $f ) {
		unlink( $f );
	}
	@rmdir( $base . '/sa-og' ); // phpcs:ignore
	@rmdir( $base ); // phpcs:ignore
}

echo "PASS: og cards (Persian shaping + RTL order, per-page 1200x630 JPEG for pages without a featured image, cached by text hash, drafts skipped, customizer switch, width/height/alt in head)\n";
