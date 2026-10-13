<?php
/**
 * Standalone tests for the minimal province/city bars (inc/entity-bars.php, v2.13.3).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-entity-bars.php
 * Shims replace the few WordPress functions used; the real theme code renders.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $ID;
	public $post_name;
	public $post_title;
	public $post_status = 'publish';
	public $post_type;
	public function __construct( $id, $slug, $title, $type ) {
		$this->ID         = $id;
		$this->post_name  = $slug;
		$this->post_title = $title;
		$this->post_type  = $type;
	}
}

$GLOBALS['t'] = array(
	'posts' => array(),
	'meta'  => array(),
);

function add_action() {
	return true;
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return (string) $s;
}
function number_format_i18n( $n ) {
	return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
}
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['t']['posts'] as $p ) {
		if ( $p->post_type === $args['post_type'] && 'publish' === $p->post_status ) {
			$out[] = $p->ID;
		}
	}
	return $out;
}
function get_post_field( $field, $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ]->$field : '';
}
function get_permalink( $post ) {
	$p = $post instanceof WP_Post ? $post : $GLOBALS['t']['posts'][ $post ];
	return 'https://example.test/' . $p->post_type . '/' . $p->post_name . '/';
}
function get_the_title( $post ) {
	$p = $post instanceof WP_Post ? $post : ( isset( $GLOBALS['t']['posts'][ $post ] ) ? $GLOBALS['t']['posts'][ $post ] : null );
	return $p ? $p->post_title : '';
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function get_post_status( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ]->post_status : false;
}
function update_meta_cache() {
	return true;
}
function _prime_post_caches() {
	return true;
}

require_once dirname( __DIR__ ) . '/inc/entity-bars.php';

/* Fixtures: 3 published provinces (+1 draft), 3 cities. */
$GLOBALS['t']['posts'][1] = new WP_Post( 1, 'lorestan', 'لرستان', 'province' );
$GLOBALS['t']['posts'][2] = new WP_Post( 2, 'tehran', 'تهران', 'province' );
$GLOBALS['t']['posts'][3] = new WP_Post( 3, 'fars', 'فارس', 'province' );
$GLOBALS['t']['posts'][4] = new WP_Post( 4, 'yazd', 'یزد', 'province' );
$GLOBALS['t']['posts'][4]->post_status = 'draft';
$GLOBALS['t']['posts'][10] = new WP_Post( 10, 'dorud', 'دورود', 'city' );
$GLOBALS['t']['posts'][11] = new WP_Post( 11, 'shiraz', 'شیراز', 'city' );
$GLOBALS['t']['posts'][12] = new WP_Post( 12, 'ray', 'ری', 'city' );
$GLOBALS['t']['meta'][10]['sa_province_id'] = 1;
$GLOBALS['t']['meta'][11]['sa_province_id'] = 3;

/* 1) Province list is complete and unique. */
$provinces = sa_iran_provinces();
sa_t_assert( 31 === count( $provinces ), '31 provinces' );
sa_t_assert( 31 === count( array_unique( array_column( $provinces, 1 ) ) ), 'province slugs unique' );
sa_t_assert( 8 === count( sa_bar_colors() ), 'eight accent colours' );

/* 2) Province bars from the archive query: 3 links, 28 «به‌زودی», note with the count. */
$html = sa_province_bars_html( array( $GLOBALS['t']['posts'][1], $GLOBALS['t']['posts'][2], $GLOBALS['t']['posts'][3], $GLOBALS['t']['posts'][4] ) );
sa_t_assert( 31 === preg_match_all( '/class="sa-bar( sa-bar--soon)?"/', $html ), 'one bar per province' );
sa_t_assert( 3 === substr_count( $html, '<a class="sa-bar"' ), 'published provinces are links' );
sa_t_assert( 28 === substr_count( $html, 'sa-bar--soon' ), 'unpublished (and draft) provinces are placeholders' );
sa_t_assert( false !== strpos( $html, 'href="https://example.test/province/lorestan/"' ), 'link to the published province' );
sa_t_assert( false === strpos( $html, 'province/yazd' ), 'draft province is not linked' );
sa_t_assert( false !== strpos( $html, '<b>لرستان</b><em>Lorestan</em>' ), 'Persian name + English secondary label' );
sa_t_assert( false !== strpos( $html, 'به‌زودی' ) && false !== strpos( $html, '۲۸ استان' ), 'note counts the pending provinces' );
sa_t_assert( false !== strpos( $html, 'class="sa-bars sa-bars--province"' ), 'province grid class' );

/* 3) Fallback without posts uses the published lookup (draft excluded). */
$html2 = sa_province_bars_html();
sa_t_assert( 3 === substr_count( $html2, '<a class="sa-bar"' ), 'lookup path links the 3 published provinces' );

/* 4) City bars: every given city linked, province as RTL secondary label, no «به‌زودی». */
$cities = array( $GLOBALS['t']['posts'][10], $GLOBALS['t']['posts'][11], $GLOBALS['t']['posts'][12] );
$chtml  = sa_city_bars_html( $cities );
sa_t_assert( 3 === substr_count( $chtml, '<a class="sa-bar"' ), 'three city links' );
sa_t_assert( false !== strpos( $chtml, '<b>دورود</b><em class="fa">لرستان</em>' ), 'city shows its province' );
sa_t_assert( false !== strpos( $chtml, '<b>ری</b></a>' ), 'city without province meta has no secondary label' );
sa_t_assert( false === strpos( $chtml, 'sa-bar--soon' ), 'no placeholders on the city archive' );
sa_t_assert( false !== strpos( $chtml, 'class="sa-bars sa-bars--city"' ), 'city grid class' );
sa_t_assert( '' === sa_city_bars_html( array() ), 'empty city list renders nothing' );

/* 5) Attraction bars (v2.13.5): city label first, province fallback, drafts never named. */
$GLOBALS['t']['posts'][20] = new WP_Post( 20, 'gahar-lake', 'دریاچه گهر', 'attraction' );
$GLOBALS['t']['posts'][21] = new WP_Post( 21, 'bisheh', 'آبشار بیشه', 'attraction' );
$GLOBALS['t']['posts'][22] = new WP_Post( 22, 'alisadr', 'غار علیصدر', 'attraction' );
$GLOBALS['t']['meta'][20]  = array( 'sa_city_id' => 10, 'sa_province_id' => 1 ); // دورود، لرستان
$GLOBALS['t']['meta'][21]  = array( 'sa_province_id' => 1 ); // فقط استان
$GLOBALS['t']['meta'][22]  = array( 'sa_city_id' => 99, 'sa_province_id' => 4 ); // شهر ناموجود، استان پیش‌نویس
$ahtml = sa_attraction_bars_html( array( $GLOBALS['t']['posts'][20], $GLOBALS['t']['posts'][21], $GLOBALS['t']['posts'][22] ) );
sa_t_assert( 3 === substr_count( $ahtml, '<a class="sa-bar"' ), 'three attraction links' );
sa_t_assert( false !== strpos( $ahtml, '<b>دریاچه گهر</b><em class="fa">دورود</em>' ), 'attraction shows its city' );
sa_t_assert( false !== strpos( $ahtml, '<b>آبشار بیشه</b><em class="fa">لرستان</em>' ), 'province fallback when no city' );
sa_t_assert( false !== strpos( $ahtml, '<b>غار علیصدر</b></a>' ), 'missing city + draft province → no secondary label' );
sa_t_assert( false !== strpos( $ahtml, 'class="sa-bars sa-bars--attraction"' ), 'attraction grid class' );
sa_t_assert( false === strpos( $ahtml, 'sa-bar--soon' ), 'no placeholders on the attraction archive' );
sa_t_assert( '' === sa_attraction_bars_html( array() ), 'empty attraction list renders nothing' );

/* 6) Escaping. */
$GLOBALS['t']['posts'][13] = new WP_Post( 13, 'x', 'شهر <b>x</b>', 'city' );
$xss                       = sa_city_bars_html( array( $GLOBALS['t']['posts'][13] ) );
sa_t_assert( false === strpos( $xss, '<b>x</b>' ) && false !== strpos( $xss, '&lt;b&gt;x&lt;/b&gt;' ), 'titles are escaped' );

echo "PASS: entity bars (31 provinces, published-only links, city bars with province label, attraction bars with city/province label)\n";
