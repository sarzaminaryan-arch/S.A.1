<?php
/**
 * Standalone regression tests for strict geographic auto-linking.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-internal-links.php
 * The small WordPress shims below exercise the real child-theme code without a
 * WordPress install or database.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', trailingslashit( dirname( __DIR__ ) ) );
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.12.5-test' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

$GLOBALS['sa_test_filters'] = array();
$GLOBALS['sa_test_posts']   = array(
	101 => (object) array(
		'ID'          => 101,
		'post_type'   => 'province',
		'post_name'   => 'tehran',
		'post_title'  => 'استان استان تهران',
		'post_status' => 'publish',
	),
	102 => (object) array(
		'ID'          => 102,
		'post_type'   => 'city',
		'post_name'   => 'dorud',
		'post_title'  => 'شهرستان شهرستان دورود',
		'post_status' => 'publish',
	),
	103 => (object) array(
		'ID'          => 103,
		'post_type'   => 'city',
		'post_name'   => 'tehran',
		'post_title'  => 'شهرستان تهران',
		'post_status' => 'publish',
	),
	104 => (object) array(
		'ID'          => 104,
		'post_type'   => 'city',
		'post_name'   => 'isfahan',
		'post_title'  => 'شهر اصفهان',
		'post_status' => 'publish',
	),
);

/** Minimal WP hook shim. */
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return true;
}

/** Minimal filter registry so title filters can be exercised. */
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sa_test_filters'][ $tag ][] = array( $callback, (int) $accepted_args );
	return true;
}

/** Apply only the filters registered by this test. */
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	$value = array_shift( $args );
	foreach ( isset( $GLOBALS['sa_test_filters'][ $tag ] ) ? $GLOBALS['sa_test_filters'][ $tag ] : array() as $filter ) {
		$call_args   = array_slice( $args, 0, max( 0, $filter[1] - 1 ) );
		$call_args[] = $value;
		$call_args   = array_merge( array( $value ), array_slice( $call_args, 0, max( 0, $filter[1] - 1 ) ) );
		$value       = call_user_func_array( $filter[0], $call_args );
	}
	return $value;
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function get_post( $post ) {
	if ( is_object( $post ) ) {
		return $post;
	}
	$id = (int) $post;
	return isset( $GLOBALS['sa_test_posts'][ $id ] ) ? $GLOBALS['sa_test_posts'][ $id ] : null;
}

function get_the_title( $post = 0 ) {
	$post_obj = get_post( $post );
	$title    = $post_obj ? (string) $post_obj->post_title : '';
	return apply_filters( 'the_title', $title, $post_obj ? (int) $post_obj->ID : 0 );
}

function get_post_field( $field, $post_id ) {
	$post = get_post( $post_id );
	return $post && isset( $post->{$field} ) ? $post->{$field} : '';
}

function get_post_type( $post = 0 ) {
	$post = get_post( $post );
	return $post && isset( $post->post_type ) ? $post->post_type : '';
}

function get_the_ID() {
	return 0;
}

function is_admin() {
	return false;
}

function is_feed() {
	return false;
}

function is_singular( $post_types = '' ) {
	return true;
}

function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}

function get_permalink( $post_id ) {
	$post = get_post( $post_id );
	return $post ? 'https://example.test/' . $post->post_type . '/' . $post->post_name . '/' : '';
}

function get_theme_mod( $name, $default = false ) {
	return $default;
}

function get_transient( $key ) {
	return false;
}

function set_transient( $key, $value, $expiration = 0 ) {
	return true;
}

function delete_transient( $key ) {
	return true;
}

function taxonomy_exists( $taxonomy ) {
	return false;
}

function is_wp_error( $value ) {
	return false;
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function esc_url( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function home_url( $path = '/' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterfaceBeforeLastUsed
	foreach ( $GLOBALS['sa_test_posts'] as $post_obj ) {
		if ( $post_obj->post_type === $post_type && $post_obj->post_name === $slug ) {
			return $post_obj;
		}
	}
	return null;
}

function sa_redirect_is_old_slug( $slug, $post_type = 'city' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return 'isfahan' === (string) $slug;
}

/** Fake prepared-SELECT result used by the real target-builder. */
class SA_Test_WPDB {
	public $posts = 'wp_posts';

	public function prepare( $query ) {
		return $query;
	}

	public function get_results( $query ) {
		return array(
			(object) array( 'ID' => 101, 'post_type' => 'province', 'post_name' => 'tehran', 'post_title' => 'استان استان تهران' ),
			(object) array( 'ID' => 102, 'post_type' => 'city', 'post_name' => 'dorud', 'post_title' => 'شهرستان شهرستان دورود' ),
		);
	}
}
$GLOBALS['wpdb'] = new SA_Test_WPDB();

require_once SA_CHILD_DIR . 'inc/taxonomies.php';
require_once SA_CHILD_DIR . 'inc/geo-counties.php';
require_once SA_CHILD_DIR . 'inc/internal-links.php';

/** Fail fast with a useful message, independent of PHP's zend.assertions setting. */
function sa_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: " . $message . "\n" );
		exit( 1 );
	}
}

function sa_test_state( $targets ) {
	return array(
		'index'            => $targets['index'],
		'regex'            => $targets['regex'],
		'used'             => array(),
		'count'            => 0,
		'max'              => 30,
		'current_id'       => 0,
		'current_key'      => '',
		'prelinked'        => array(),
		'self_needles'     => array(),
		'needle_done'      => array(),
		'current_province' => '',
	);
}

function sa_test_link_texts( $html ) {
	preg_match_all( '~<a\b[^>]*>(.*?)</a>~us', (string) $html, $matches );
	return isset( $matches[1] ) ? $matches[1] : array();
}

$targets = sa_autolink_build_targets();
sa_test_expect( isset( $targets['index']['استان تهران'] ), 'province key must include its exact prefix' );
sa_test_expect( isset( $targets['index']['شهرستان دورود'] ), 'county key must include its exact prefix' );
sa_test_expect( ! isset( $targets['index']['تهران'] ), 'bare province names must not be geographic keys' );
sa_test_expect( ! isset( $targets['index']['دورود'] ), 'bare county names must not be geographic keys' );
sa_test_expect( ! isset( $targets['index']['استان استان تهران'] ), 'duplicate province prefixes must not enter the index' );
sa_test_expect( ! isset( $targets['index']['شهرستان شهرستان دورود'] ), 'duplicate county prefixes must not enter the index' );

sa_test_expect( 'استان تهران' === sa_autolink_label( 'province', 'استان استان تهران' ), 'province labels normalize to exactly one prefix' );
sa_test_expect( 'شهرستان دورود' === sa_autolink_label( 'city', 'شهرستان شهرستان دورود' ), 'county labels normalize to exactly one prefix' );
sa_test_expect( '' === sa_autolink_label( 'city', 'استان دورود' ), 'mismatched province prefix is rejected for a county' );
sa_test_expect( '' === sa_autolink_label( 'province', 'شهرستان تهران' ), 'mismatched county prefix is rejected for a province' );

$bare_state = sa_test_state( $targets );
$bare       = sa_autolink_fragment( 'تهران و دورود', $bare_state );
sa_test_expect( 'تهران و دورود' === $bare, 'bare geographic names must remain plain text' );
sa_test_expect( 0 === sa_autolink_count( $bare )['total'], 'bare geographic names must produce no auto-links' );

$phrased_state = sa_test_state( $targets );
$phrased       = sa_autolink_fragment( 'استان تهران و شهرستان دورود', $phrased_state );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $phrased ), 'only full prefixed phrases are linked, with source text preserved' );

$repeat_state = sa_test_state( $targets );
$repeat       = sa_autolink_fragment( 'استان تهران، استان تهران؛ شهرستان دورود، شهرستان دورود', $repeat_state );
$repeat_count = sa_autolink_count( $repeat );
sa_test_expect( 1 === $repeat_count['province'] && 1 === $repeat_count['city'], 'each geographic destination is linked at most once' );

$duplicate_state = sa_test_state( $targets );
$duplicate       = sa_autolink_fragment( 'استان استان تهران؛ شهرستان شهرستان دورود', $duplicate_state );
sa_test_expect( false === strpos( $duplicate, 'استان استان' ), 'duplicate province prefixes are collapsed' );
sa_test_expect( false === strpos( $duplicate, 'شهرستان شهرستان' ), 'duplicate county prefixes are collapsed' );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $duplicate ), 'collapsed complete phrases are linked exactly once' );

$mismatch_state = sa_test_state( $targets );
$mismatch       = sa_autolink_fragment( 'شهر تهران، استان دورود، شهرستان تهران', $mismatch_state );
sa_test_expect( 'شهر تهران، استان دورود، شهرستان تهران' === $mismatch, 'bare or mismatched prefixes must not link to geography' );

$rendered = sa_autolink_content( '<h2>استان استان تهران</h2><p>تهران، استان تهران و شهرستان دورود؛ شهرستان دورود</p>' );
$rendered_counts = sa_autolink_count( $rendered );
sa_test_expect( 1 === $rendered_counts['province'] && 1 === $rendered_counts['city'], 'the full content filter links each destination once' );
sa_test_expect( false !== strpos( $rendered, '<h2>استان تهران</h2>' ), 'the full content filter repairs duplicate prefixes in headings' );
sa_test_expect( array( 'استان تهران', 'شهرستان دورود' ) === sa_test_link_texts( $rendered ), 'the full content filter preserves prefixed source phrases and ignores bare names' );

$html = '<h2>استان استان تهران</h2><p data-example="استان استان">شهرستان شهرستان دورود</p><pre>استان استان تهران</pre>';
$clean_html = sa_autolink_normalize_content_prefixes( $html );
sa_test_expect( false !== strpos( $clean_html, '<h2>استان تهران</h2>' ), 'duplicate prefixes in article headings are normalized' );
sa_test_expect( false !== strpos( $clean_html, 'data-example="استان استان"' ), 'HTML attributes are not rewritten' );
sa_test_expect( false !== strpos( $clean_html, '<p data-example="استان استان">شهرستان دورود</p>' ), 'visible body text is normalized without changing attributes' );
sa_test_expect( false !== strpos( $clean_html, '<pre>استان استان تهران</pre>' ), 'code/pre blocks remain untouched' );

sa_test_expect( 'تهران' === sa_province_name_for_post( 101 ), 'province display helper returns the canonical bare name' );
sa_test_expect( 'استان تهران' === sa_province_name_for_post( 101, true ), 'province display helper adds at most one requested prefix' );
sa_test_expect( 'دورود' === sa_county_name( 102 ), 'county display helper strips duplicate stored prefixes' );
sa_test_expect( 'شهرستان دورود' === sa_county_name( 102, true ), 'county display helper adds at most one requested prefix' );

$ready_articles = require SA_CHILD_DIR . 'data/ready-articles.php';
$article_links  = array(
	'fin-garden' => array(
		'/city/kashan/'                    => 'شهرستان کاشان',
		'/province/isfahan/'               => 'استان اصفهان',
		'/attraction/bagh-golshan-tabas/'    => 'باغ گلشن طبس',
	),
	'masouleh' => array(
		'/province/gilan/'                 => 'استان گیلان',
		'/attraction/sar-agha-seyed-village/' => 'روستای سرآقا سید',
	),
	'persepolis-takht-jamshid' => array(
		'/province/fars/'                  => 'استان فارس',
		'/attraction/naqsh-e-jahan-square-isfahan/' => 'میدان نقش جهان',
	),
	'naqsh-e-jahan-square-isfahan' => array(
		'/province/isfahan/'               => 'استان اصفهان',
		'/city/isfahan-city/'              => 'شهرستان اصفهان',
		'/attraction/persepolis-takht-jamshid/' => 'تخت جمشید',
	),
	'shahdad-kaluts-lut-desert' => array(
		'/province/kerman/'                => 'استان کرمان',
		'/city/kerman/'                    => 'شهرستان کرمان',
		'/city/tabas/'                    => 'شهرستان طبس',
	),
);
foreach ( $article_links as $article_key => $links ) {
	sa_test_expect( isset( $ready_articles[ $article_key ]['content'] ), 'rewritten article must exist: ' . $article_key );
	foreach ( $links as $href => $label ) {
		$anchor = '<a href="' . $href . '">' . $label . '</a>';
		sa_test_expect( 1 === substr_count( $ready_articles[ $article_key ]['content'], $anchor ), 'expected one exact internal link in ' . $article_key . ': ' . $href );
	}
}

/**
 * v2.18.2 — نگهبانِ پیوندهای مقالات دیدنی‌ها:
 *  ۱) کلیدِ هر رکورد باید همان نامک باشد (قاعدهٔ آزمون راهنماها)؛
 *  ۲) هیچ پیوند داخلیِ دیدنی در بدنه نباید به مقصدی برود که رکورد آمادهٔ آن وجود ندارد
 *     (پیوندهای ۴۰۴ پیشین در ۲٫۱۸٫۲ به متن ساده تبدیل شدند).
 */
$ready_article_keys = array_keys( $ready_articles );
foreach ( $ready_articles as $article_key => $article_record ) {
	sa_test_expect( $article_key === $article_record['slug'], 'article key must equal its slug: ' . $article_key );
	sa_test_expect( ! empty( $article_record['content'] ), 'article record must ship content: ' . $article_key );
	preg_match_all( '~href="(/attraction/[^"#?]+)/"~', (string) $article_record['content'], $attraction_hrefs );
	foreach ( array_unique( $attraction_hrefs[1] ) as $attraction_href ) {
		$target_key = substr( trim( $attraction_href, '/' ), strlen( 'attraction/' ) );
		sa_test_expect(
			in_array( $target_key, $ready_article_keys, true ),
			'article ' . $article_key . ' links to an attraction without a prepared record: ' . $attraction_href
		);
	}
}

/* v2.24.9 — حذف پیوندهای داخلی تکراری در رندر بدنه (قانون ۹ AGENTS.md) */
$dup_links_html = '<p><a href="/city/dorud/">دورود</a> شهری کوهستانی است و <a href="https://example.test/city/dorud/">شهرستان دورود</a> طبیعت زیبایی دارد.'
	. '<sup class="sa-cite"><a href="#sa-ref-1">۱</a></sup> '
	. '<a href="https://fa.wikipedia.org/wiki/Dorud">ویکی‌پدیا</a> و دوباره '
	. '<sup class="sa-cite"><a href="#sa-ref-1">۱</a></sup> و '
	. '<a href="https://fa.wikipedia.org/wiki/Dorud">ویکی‌پدیا</a>.</p>'
	. '<pre><a href="/city/dorud/">کد نمونه</a></pre>';
$deduped_html = sa_autolink_content( $dup_links_html );
sa_test_expect(
	1 === substr_count( $deduped_html, '<a href="/city/dorud/">دورود</a>' ) && false === strpos( $deduped_html, 'href="https://example.test/city/dorud/"' ),
	'duplicate internal destination link in body must be unwrapped after the first occurrence'
);
sa_test_expect(
	false !== strpos( $deduped_html, 'و شهرستان دورود طبیعت زیبایی دارد' ),
	'anchor text of unwrapped duplicate internal link must remain intact'
);
sa_test_expect(
	2 === substr_count( $deduped_html, 'href="#sa-ref-1"' ),
	'repeated footnote fragment links (#sa-ref-1) must remain untouched'
);
sa_test_expect(
	2 === substr_count( $deduped_html, 'href="https://fa.wikipedia.org/wiki/Dorud"' ),
	'external reference links must remain untouched'
);
sa_test_expect(
	false !== strpos( $deduped_html, '<pre><a href="/city/dorud/">کد نمونه</a></pre>' ),
	'protected pre/code blocks must remain untouched during internal link deduplication'
);

/* v2.24.9 — تطبیق نامک شهرستان‌های مرکز استان (tehran <-> tehran-city) بدون تطبیق مبدأ تغییر مسیر ۳۰۱ (isfahan) */
$tehran_county = sa_county_of_post( 103 );
sa_test_expect( is_array( $tehran_county ) && 'tehran-city' === $tehran_county['slug'], 'bare-slug capital county post resolves its -city registry entry' );
sa_test_expect( null === sa_county_of_post( 104 ), 'redirect-source old slug isfahan must not match isfahan-city registry entry' );
sa_test_expect( 'https://example.test/city/tehran/' === sa_county_permalink( 'tehran-city' ), 'registry -city slug resolves published bare-slug capital county permalink' );
sa_test_expect( '' === sa_county_permalink( 'isfahan-city' ), 'registry isfahan-city must not fall back to redirect-source slug isfahan' );
$neighbor_bare = sa_county_parse_neighbor( 'tehran - شمال' );
sa_test_expect( 'تهران' === $neighbor_bare['label'] && 'https://example.test/city/tehran/' === $neighbor_bare['url'], 'neighbor chip with bare capital slug resolves Persian label and permalink' );

fwrite( STDOUT, "PASS: internal geographic auto-link, rewritten-article link and prepared-record link tests\n" );
