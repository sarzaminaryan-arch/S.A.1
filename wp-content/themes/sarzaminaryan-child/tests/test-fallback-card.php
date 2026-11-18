<?php
/**
 * تست کارت جایگزین موقت بی‌عکس (v2.22.144).
 *
 * قاعده‌های آزمون‌شده:
 *   ۱) شهرستان/استان هرگز کارت جایگزین نمی‌گیرند (قرارداد sa_county_featured_figure دست‌نخورده).
 *   ۲) نوشته‌ای که صریحاً شاخصش حذف شده، کارت هم نمی‌گیرد.
 *   ۳) نوشتهٔ دارای تصویر شاخص واقعی، کارت نمی‌گیرد.
 *   ۴) متن کارت یکتاست (عنوان/نوع/استان) و alt یکتا دارد.
 *   ۵) SVG ولید است و رنگ‌های پالت سایت را دارد.
 *   ۶) با تغییر عنوان، نام فایل کش عوض می‌شود.
 *
 * og:image در این نسخه دست‌نخورده است (کارت PNG موجود از ۲.۲۲.۷۴)؛ پس این
 * تست هیچ ادعایی دربارهٔ og:image ندارد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' ); // اجرای مستقیم تست، بدون وردپرس.
}

$sa_fc_fail = 0;
/**
 * @param bool   $cond شرط.
 * @param string $msg  پیام.
 */
function sa_fc_assert( $cond, $msg ) {
	global $sa_fc_fail;
	if ( ! $cond ) {
		++$sa_fc_fail;
		echo "FAIL: {$msg}\n";
	}
}

// --- حداقل‌های وردپرس برای این تست -----------------------------------------
$GLOBALS['t'] = array(
	'posts'    => array(),
	'thumb'    => array(),
	'attached' => array(),
	'meta'     => array(),
	'blogname' => 'سرزمین آریان',
);

if ( ! function_exists( 'absint' ) ) {
	function absint( $v ) {
		return abs( (int) $v );
	}
}
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $id = 0 ) {
		return isset( $GLOBALS['t']['posts'][ $id ]['type'] ) ? $GLOBALS['t']['posts'][ $id ]['type'] : '';
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return (int) ( $GLOBALS['t']['current'] ?? 0 );
	}
}
if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $id = 0 ) {
		return isset( $GLOBALS['t']['posts'][ $id ]['title'] ) ? $GLOBALS['t']['posts'][ $id ]['title'] : '';
	}
}
if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $id ) {
		if ( 'post_status' === $field ) {
			return isset( $GLOBALS['t']['posts'][ $id ]['status'] ) ? $GLOBALS['t']['posts'][ $id ]['status'] : 'publish';
		}
		if ( 'post_name' === $field ) {
			return isset( $GLOBALS['t']['posts'][ $id ]['slug'] ) ? $GLOBALS['t']['posts'][ $id ]['slug'] : '';
		}
		return '';
	}
}
if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $id = 0 ) {
		return isset( $GLOBALS['t']['thumb'][ $id ] ) ? $GLOBALS['t']['thumb'][ $id ] : 0;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id ) {
		return isset( $GLOBALS['t']['posts'][ $id ] ) ? (object) array( 'ID' => $id ) : null;
	}
}
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'sa_featured_image_suppressed' ) ) {
	function sa_featured_image_suppressed( $id ) {
		return '1' === (string) get_post_meta( (int) $id, '_sa_ready_remove_featured_image', true );
	}
}
if ( ! function_exists( 'sa_media_attachment_missing' ) ) {
	function sa_media_attachment_missing( $id ) {
		return ! empty( $GLOBALS['t']['attached'][ (int) $id ]['missing'] );
	}
}
if ( ! function_exists( 'sa_entity_label' ) ) {
	function sa_entity_label( $type ) {
		return 'attraction' === $type ? 'جاذبهٔ دیدنی' : '';
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir() {
		return array( 'basedir' => '/tmp/sa-fb-test', 'baseurl' => 'https://example.test/uploads' );
	}
}
if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $dir ) {
		return @mkdir( $dir, 0775, true ) || is_dir( $dir );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $v ) {
		return $v;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $v ) {
		return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $v ) {
		return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'sa_get_parent' ) ) {
	function sa_get_parent( $id, $type = 'province' ) {
		$pid = isset( $GLOBALS['t']['posts'][ $id ]['province'] ) ? (int) $GLOBALS['t']['posts'][ $id ]['province'] : 0;
		return $pid ? (object) array( 'ID' => $pid, 'post_title' => $GLOBALS['t']['posts'][ $pid ]['title'] ?? '' ) : null;
	}
}

$sa_fc_dir = dirname( __DIR__ );
require_once $sa_fc_dir . '/inc/county-fallback-card.php';

// --- چیدمان داده -----------------------------------------------------------
$GLOBALS['t']['posts'][900] = array( 'type' => 'province', 'title' => 'لرستان', 'status' => 'publish' );
$GLOBALS['t']['posts'][901] = array( 'type' => 'city', 'title' => 'شهرستان خرم‌آباد', 'status' => 'publish', 'province' => 900 );
$GLOBALS['t']['posts'][910] = array( 'type' => 'post', 'title' => 'شکستگی پا در طبیعت؛ تا رسیدن پزشک چه کنیم؟', 'status' => 'publish', 'slug' => 'leg-fracture-first-aid', 'province' => 900 );
$GLOBALS['t']['posts'][911] = array( 'type' => 'attraction', 'title' => 'آبشار نوژیان', 'status' => 'publish', 'slug' => 'nozhian-waterfall', 'province' => 900 );
$GLOBALS['t']['posts'][912] = array( 'type' => 'post', 'title' => 'نوشتهٔ دارای عکس', 'status' => 'publish', 'slug' => 'has-image' );
$GLOBALS['t']['posts'][913] = array( 'type' => 'post', 'title' => 'نوشتهٔ با حذف صریح شاخص', 'status' => 'publish', 'slug' => 'suppressed-one' );
$GLOBALS['t']['posts'][914] = array( 'type' => 'post', 'title' => 'نوشتهٔ پیش‌نویس', 'status' => 'draft', 'slug' => 'draft-one' );

$GLOBALS['t']['thumb'][912] = 5000;
$GLOBALS['t']['attached'][5000] = array( 'missing' => false );
$GLOBALS['t']['meta'][913]['_sa_ready_remove_featured_image'] = '1';

// --- ۱) دامنهٔ اعمال ------------------------------------------------------
sa_fc_assert( true === sa_fallback_card_applies( 910 ), 'a published guide without a cover gets the card' );
sa_fc_assert( true === sa_fallback_card_applies( 911 ), 'a published attraction without a cover gets the card' );
sa_fc_assert( false === sa_fallback_card_applies( 901 ), 'a county never gets the fallback card' );
sa_fc_assert( false === sa_fallback_card_applies( 900 ), 'a province never gets the fallback card' );
sa_fc_assert( false === sa_fallback_card_applies( 912 ), 'a post with a real cover gets no card' );
sa_fc_assert( false === sa_fallback_card_applies( 913 ), 'a post whose cover was deliberately removed gets no card' );
sa_fc_assert( false === sa_fallback_card_applies( 914 ), 'a draft gets no card' );
sa_fc_assert( false === sa_fallback_card_applies( 0 ), 'post id 0 gets no card' );

// --- ۲) متن یکتا ----------------------------------------------------------
$sa_fc_a = sa_fallback_card_text( 910 );
$sa_fc_b = sa_fallback_card_text( 911 );
sa_fc_assert( false !== strpos( $sa_fc_a['title'], 'شکستگی' ), 'the card title comes from the post title' );
sa_fc_assert( 'لرستان' === $sa_fc_a['place'], 'the card names the province' );
sa_fc_assert( $sa_fc_a['kicker'] !== $sa_fc_b['kicker'], 'kind label differs between a guide and an attraction' );
sa_fc_assert(
	sa_fallback_card_filename( $sa_fc_a ) !== sa_fallback_card_filename( $sa_fc_b ),
	'two different posts get two different cache files'
);

// --- ۳) SVG ---------------------------------------------------------------
$sa_fc_svg = sa_fallback_card_svg( $sa_fc_a );
sa_fc_assert( 0 === strpos( $sa_fc_svg, '<?xml' ), 'the card starts with an XML declaration' );
sa_fc_assert( false !== strpos( $sa_fc_svg, '<svg' ) && false !== strpos( $sa_fc_svg, '</svg>' ), 'the card is an SVG document' );
sa_fc_assert( false !== strpos( $sa_fc_svg, 'width="1200"' ) && false !== strpos( $sa_fc_svg, 'height="630"' ), 'the card is 1200x630 (og:image size)' );
sa_fc_assert( false !== strpos( $sa_fc_svg, '#0b2a4a' ) || false !== strpos( $sa_fc_svg, '#123f73' ), 'the card uses the site navy' );
sa_fc_assert( false !== strpos( $sa_fc_svg, '#f6c453' ), 'the card uses the site amber accent' );
sa_fc_assert( false !== strpos( $sa_fc_svg, 'direction="rtl"' ), 'the card sets right-to-left direction' );
sa_fc_assert( false !== strpos( $sa_fc_svg, 'aria-label' ), 'the card carries an aria-label' );

// --- ۴) شکستن سطر ---------------------------------------------------------
$sa_fc_lines = sa_fallback_card_lines( 'یک دو سه چهار پنج شش هفت هشت نه ده', 10, 2 );
sa_fc_assert( count( $sa_fc_lines ) <= 2, 'the title is wrapped into at most the allowed lines' );
sa_fc_assert( false !== strpos( implode( ' ', $sa_fc_lines ), '…' ), 'a shortened title ends with an ellipsis' );

// --- ۵) ساختن و کش --------------------------------------------------------
$sa_fc_url = sa_fallback_card_url( 910 );
sa_fc_assert( '' !== $sa_fc_url && false !== strpos( $sa_fc_url, 'sa-fallback' ), 'the card is written under uploads/sa-fallback' );
sa_fc_assert( '' === sa_fallback_card_url( 901 ), 'a county still gets no card url' );
$sa_fc_html = sa_fallback_card_html( 910 );
sa_fc_assert( false !== strpos( $sa_fc_html, '<figure class="sa-fallback-card">' ), 'the figure markup is produced' );
sa_fc_assert( false !== strpos( $sa_fc_html, 'alt="شکستگی پا' ), 'the alt text names the page (not a generic alt)' );
sa_fc_assert( false !== strpos( $sa_fc_html, 'تصویر موقت' ), 'the caption says the image is temporary' );
sa_fc_assert( '' === sa_fallback_card_html( 912 ), 'a post with a real cover prints nothing' );

if ( 0 === $sa_fc_fail ) {
	echo "PASS: fallback card (scope, uniqueness, svg, cache)\n";
} else {
	echo "FAILED: {$sa_fc_fail}\n";
	exit( 1 );
}
