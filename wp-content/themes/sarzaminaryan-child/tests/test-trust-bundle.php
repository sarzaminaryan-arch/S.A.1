<?php
/**
 * Standalone test: trust & correctness bundle (2.22.73).
 *
 * Live-site audit 2026-11-15 (docs/live-site/site-audit-2026-11-15.md) found:
 *  - the published page /editorial-policy/ answered 404 because a redirect rule
 *    sent it to /policy/ (which does not exist); Vank Cathedral and Shah Cheragh
 *    attraction pages (published) were also redirect sources;
 *  - /city/savojbolagh/ pointed at the province instead of the county page;
 *  - the home «top counties» table printed the county name as its province;
 *  - the visitor-facing update note leaked «در انتظار بازبینی مالک»;
 *  - llms.txt and the 404 page linked empty sections (route/food/souvenir).
 *
 * Locked rules guarded here:
 *  1. No redirect source is one of the published pages found in the audit.
 *  2. Savojbolagh points at the county page (savojablogh), not the province.
 *  3. A single-segment redirect target must be a published page/post; else no 301.
 *  4. In-content links to a redirect source are rewritten to the target only
 *     when the target is published; external/protocol-relative links untouched.
 *  5. CC_Rating::province_of() resolves the county's province, not the county.
 *  6. No «بازبینی مالک» in bundled county data or raw county records.
 *  7. llms.txt and 404 hide sections with zero published posts; empty archives
 *     stay noindex; footer never links a draft policy page.
 *  8. Agent bridge list ordering is stable (ID tiebreaker) for pagination.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-trust-bundle.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
$theme_dir = dirname( __DIR__ );
$repo_dir  = dirname( dirname( dirname( $theme_dir ) ) );
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', $theme_dir . '/' );
}

function sa_tb_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class SA_TB_Post { // phpcs:ignore
	public $ID;
	public $post_type;
	public $post_name;
	public $post_status;
	public function __construct( $id, $type, $name, $status = 'publish' ) {
		$this->ID          = $id;
		$this->post_type   = $type;
		$this->post_name   = $name;
		$this->post_status = $status;
	}
}

$GLOBALS['tb'] = array( 'posts' => array(), 'meta' => array(), 'filters' => array() );

function add_action() {}
function add_filter( $hook, $cb ) { $GLOBALS['tb']['filters'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function home_url( $path = '' ) { return 'https://sarzaminaryan.ir' . $path; }
function get_page_by_path( $path, $output = null, $type = 'page' ) {
	unset( $output );
	foreach ( $GLOBALS['tb']['posts'] as $post ) {
		if ( $post->post_name === $path && $post->post_type === $type ) {
			return $post;
		}
	}
	return null;
}
function get_post( $id ) {
	foreach ( $GLOBALS['tb']['posts'] as $post ) {
		if ( (int) $post->ID === (int) $id ) {
			return $post;
		}
	}
	return null;
}
function get_post_type( $id ) {
	$p = get_post( $id );
	return $p ? $p->post_type : false;
}
function get_post_meta( $id, $key, $single = false ) {
	unset( $single );
	return isset( $GLOBALS['tb']['meta'][ $id ][ $key ] ) ? $GLOBALS['tb']['meta'][ $id ][ $key ] : '';
}
function absint( $v ) { return abs( (int) $v ); }
function sa_province_name_for_post( $id ) {
	$names = array( 900 => 'لرستان', 901 => 'اصفهان' );
	$id    = is_object( $id ) ? $id->ID : (int) $id;
	return isset( $names[ $id ] ) ? $names[ $id ] : 'شهرستان-' . $id;
}

/* ---- 1–2: redirect map --------------------------------------------------- */

$map_rows = require $theme_dir . '/data/redirects.php';
sa_tb_assert( is_array( $map_rows ), 'redirect map returns an array' );
$sources = array();
foreach ( $map_rows as $from => $to ) {
	$sources[ '/' . trim( (string) $from, '/' ) . '/' ] = '/' . trim( (string) $to, '/' ) . '/';
}
foreach ( array( '/editorial-policy/', '/attraction/vank-cathedral/', '/attraction/shah-cheragh/' ) as $live ) {
	sa_tb_assert( ! isset( $sources[ $live ] ), "redirect map: published page {$live} is never a redirect source (2.22.73)" );
}
foreach ( $sources as $from => $to ) {
	sa_tb_assert( '/policy/' !== $to, "redirect map: nothing points at the missing /policy/ ({$from})" );
}
sa_tb_assert( isset( $sources['/city/savojbolagh/'] ) && '/city/savojablogh/' === $sources['/city/savojbolagh/'], 'redirect map: savojbolagh -> county page savojablogh, not the province' );
foreach ( array( '/city/isfahan/' => '/city/isfahan-city/', '/city/borazjan/' => '/city/dashtestan/', '/attraction/tabas-attractions/' => '/city/tabas/' ) as $from => $to ) {
	sa_tb_assert( isset( $sources[ $from ] ) && $to === $sources[ $from ], "redirect map: owner-approved merge {$from} -> {$to} kept" );
}

/* ---- 3–4: target existence + link rewrite -------------------------------- */

require_once $theme_dir . '/inc/redirects.php';

$GLOBALS['tb']['posts'] = array(
	new SA_TB_Post( 9, 'page', 'editorial-policy' ),
	new SA_TB_Post( 50, 'page', 'policy', 'draft' ),
	new SA_TB_Post( 3429, 'city', 'isfahan-city' ),
	new SA_TB_Post( 212, 'city', 'dashtestan' ),
);
sa_tb_assert( true === sa_redirect_target_exists( '/editorial-policy/' ), 'target exists: published single-segment page' );
sa_tb_assert( false === sa_redirect_target_exists( '/policy/' ), 'target exists: draft single-segment page is not a 301 target (2.22.73)' );
sa_tb_assert( false === sa_redirect_target_exists( '/nothing-here/' ), 'target exists: missing single-segment page is not a 301 target (2.22.73)' );
sa_tb_assert( true === sa_redirect_target_exists( '/city/isfahan-city/' ), 'target exists: published city' );
sa_tb_assert( false === sa_redirect_target_exists( '/city/tabas/' ), 'target exists: missing city' );
sa_tb_assert( in_array( 'sa_redirect_rewrite_links', isset( $GLOBALS['tb']['filters']['the_content'] ) ? $GLOBALS['tb']['filters']['the_content'] : array(), true ), 'link rewrite hooked on the_content' );

$html = '<p><a href="/city/isfahan/">اصفهان</a> و <a href="https://sarzaminaryan.ir/city/borazjan/#x">برازجان</a>'
	. ' و <a href="/attraction/tabas-attractions/">طبس</a> و <a href="//cdn.example.com/city/isfahan/">cdn</a>'
	. ' و <a href="https://other.example/city/isfahan/">ext</a> و <a href="/city/kashan/">کاشان</a></p>';
$out  = sa_redirect_rewrite_links( $html );
sa_tb_assert( false !== strpos( $out, 'href="/city/isfahan-city/"' ), 'link rewrite: relative source link -> target' );
sa_tb_assert( false !== strpos( $out, 'href="https://sarzaminaryan.ir/city/dashtestan/#x"' ), 'link rewrite: absolute same-host link keeps host and fragment' );
sa_tb_assert( false !== strpos( $out, 'href="/attraction/tabas-attractions/"' ), 'link rewrite: unpublished target leaves the link alone' );
sa_tb_assert( false !== strpos( $out, 'href="//cdn.example.com/city/isfahan/"' ), 'link rewrite: protocol-relative external link untouched' );
sa_tb_assert( false !== strpos( $out, 'href="https://other.example/city/isfahan/"' ), 'link rewrite: other-host link untouched' );
sa_tb_assert( false !== strpos( $out, 'href="/city/kashan/"' ), 'link rewrite: non-source link untouched' );

/* ---- 5: province_of ------------------------------------------------------ */

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
$rating_src = (string) file_get_contents( $theme_dir . '/inc/city-contrib/includes/class-cc-rating.php' );
sa_tb_assert( false !== strpos( $rating_src, 'sa_province_name_for_post( $province_id )' ), 'CC_Rating::province_of passes the province ID (2.22.73)' );
sa_tb_assert( false === strpos( $rating_src, 'sa_province_name_for_post( $id )' ), 'CC_Rating::province_of never passes the county ID' );
if ( preg_match( '~public static function province_of\( \$id \) \{.*?\n\t\}\n~s', $rating_src, $m ) ) {
	$GLOBALS['tb']['posts'][] = new SA_TB_Post( 77, 'city', 'dorud' );
	$GLOBALS['tb']['posts'][] = new SA_TB_Post( 900, 'province', 'lorestan' );
	$GLOBALS['tb']['meta'][77]['sa_province_id'] = 900;
	function get_the_terms() { return false; }
	function sa_gallery_city_province_id( $city_id ) {
		$pid = (int) get_post_meta( $city_id, 'sa_province_id', true );
		return $pid && 'province' === get_post_type( $pid ) ? $pid : 0;
	}
	eval( 'class SA_TB_Rating { ' . $m[0] . ' }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	sa_tb_assert( 'لرستان' === SA_TB_Rating::province_of( 77 ), 'CC_Rating::province_of(Dorud) -> لرستان, not the county title' );
} else {
	sa_tb_assert( false, 'CC_Rating::province_of found in source' );
}

/* ---- 6: no owner-review leak --------------------------------------------- */

$banned = 'بازبینی مالک';
sa_tb_assert( false === strpos( (string) file_get_contents( $theme_dir . '/data/county-experiences.php' ), $banned ), 'county-experiences: no «بازبینی مالک» in visitor-facing data' );
foreach ( (array) glob( $repo_dir . '/tools/county-insights/data/raw/*.json' ) as $raw ) {
	$json = (string) file_get_contents( $raw );
	$data = json_decode( $json, true );
	$note = is_array( $data ) && isset( $data['field_update_note'] ) ? (string) $data['field_update_note'] : '';
	sa_tb_assert( false === strpos( $note, $banned ), 'raw county record ' . basename( $raw ) . ': field_update_note has no «بازبینی مالک»' );
}

/* ---- 7: empty sections --------------------------------------------------- */

$seo_src = (string) file_get_contents( $theme_dir . '/inc/seo.php' );
sa_tb_assert( false !== strpos( $seo_src, "sa_type_has_published( \$sa_sec[0] )" ), 'llms.txt: route/food/souvenir listed only when published' );
sa_tb_assert( false !== strpos( $seo_src, "is_post_type_archive( array( 'souvenir', 'local_food', 'travel_route' ) )" ), 'empty entity archives stay noindex' );
sa_tb_assert( false !== strpos( $seo_src, 'function sa_seo_city_fallback_description' ), 'city meta description has a non-empty fallback' );
$four = (string) file_get_contents( $theme_dir . '/404.php' );
sa_tb_assert( false !== strpos( $four, 'sa_type_has_published( $sa_type )' ), '404: chips only for sections with published posts' );
$helpers = (string) file_get_contents( $theme_dir . '/inc/helpers.php' );
sa_tb_assert( false !== strpos( $helpers, 'function sa_type_has_published' ), 'helper sa_type_has_published exists' );
$footer = (string) file_get_contents( $theme_dir . '/footer.php' );
sa_tb_assert( false !== strpos( $footer, "'publish' === get_post_status( (int) \$sa_ids[ \$slug ] )" ), 'footer: seeded page link only when published' );
sa_tb_assert( false !== strpos( $footer, "array( 'editorial-policy', 'policy' )" ), 'footer: policy link resolves the live editorial-policy page' );
$none = (string) file_get_contents( $theme_dir . '/template-parts/content-none.php' );
sa_tb_assert( false === strpos( $none, 'به‌زودی' ), 'empty state: no «به‌زودی» promise' );

/* ---- 8: bridge ordering -------------------------------------------------- */

$bridge = (string) file_get_contents( $theme_dir . '/inc/agent-bridge.php' );
sa_tb_assert( false !== strpos( $bridge, "'ID' => 'DESC'" ), 'agent bridge: list order has an ID tiebreaker (stable pagination)' );

echo "PASS: trust bundle (no live page is a redirect source, savojbolagh -> county, single-segment targets must be published, in-content links skip 301s, province_of fixed, no owner-review leak, empty sections hidden, stable bridge paging)\n";
