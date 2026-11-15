<?php
/**
 * Standalone test: index gate + critical SEO fixes (2.22.75).
 *
 * The noindex list (docs/live-site/noindex-cities-2026-11-15.md) showed 7 county
 * pages (group A) with ≥ 2,800 rendered words and 6–9 verified places in the
 * «جاهای دیگری که مردم می‌روند» block still answering noindex, because the gate
 * did not count extra_places. Locked rules guarded here:
 *
 *  1. The new gate is a superset of the old one: any page that had children,
 *     sa_county_profile_attractions() rows or non-empty sa_cty_poi_* stays index.
 *  2. extra_places count only for visitable kinds (تاریخی/طبیعت/تفریحی/زیارتی/
 *     مذهبی/روستا/شهری) and specific names; اقامت/خوراک/حمل‌ونقل and a bare
 *     «بوستان» do not open the gate.
 *  3. < 300 words → noindex (thin_text) even with places; 0 places → no_places.
 *  4. Manual sa_noindex always wins (reason "manual").
 *  5. All 7 group-A slugs in data/county-experiences.php open the gate.
 *  6. City meta description: first ≥ 70-char candidate (custom → lead → summary).
 *  7. robots.txt: no second «Sitemap:» line when core already printed one.
 *  8. R4 guard returns false (no HTML 500) in REST; the bridge pre-checks and
 *     answers 409 sa_agent_has_dependants; bridge meta updates flush caches.
 *  9. agent-ops: theme update refuses without owner flag / recent UpdraftPlus backup.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-index-gate.php
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

function sa_ig_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * Pull one top-level function out of a theme file and define it (no other side effects).
 */
function sa_ig_load_function( $file, $name ) {
	$src = file_get_contents( $file );
	$pos = strpos( $src, "\nfunction {$name}(" );
	sa_ig_assert( false !== $pos, "{$name} exists in " . basename( $file ) );
	$open  = strpos( $src, '{', strpos( $src, ')', $pos ) );
	$depth = 0;
	$len   = strlen( $src );
	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $src[ $i ] ) {
			$depth++;
		} elseif ( '}' === $src[ $i ] ) {
			$depth--;
			if ( 0 === $depth ) {
				break;
			}
		}
	}
	eval( substr( $src, $pos, $i - $pos + 1 ) ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
}

class SA_IG_Post { // phpcs:ignore
	public $ID;
	public $post_type;
	public $post_name;
	public $post_status;
	public $post_content;
	public $post_title;
	public function __construct( $id, $type, $name, $content = '', $status = 'publish', $title = '' ) {
		$this->ID           = $id;
		$this->post_type    = $type;
		$this->post_name    = $name;
		$this->post_content = $content;
		$this->post_status  = $status;
		$this->post_title   = $title;
	}
}

$GLOBALS['ig'] = array( 'posts' => array(), 'meta' => array(), 'children' => array(), 'atts' => array() );

function apply_filters( $hook, $value ) { return $value; } // phpcs:ignore
function add_action() {} // phpcs:ignore
function add_filter() {} // phpcs:ignore
function get_post( $id ) { // phpcs:ignore
	if ( is_object( $id ) ) {
		return $id;
	}
	return isset( $GLOBALS['ig']['posts'][ (int) $id ] ) ? $GLOBALS['ig']['posts'][ (int) $id ] : null;
}
function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; } // phpcs:ignore
function get_post_field( $field, $id ) { $p = get_post( $id ); return $p ? $p->$field : ''; } // phpcs:ignore
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore
	unset( $single );
	return isset( $GLOBALS['ig']['meta'][ (int) $id ][ $key ] ) ? $GLOBALS['ig']['meta'][ (int) $id ][ $key ] : '';
}
function get_the_title( $p ) { $p = get_post( $p ); return $p ? $p->post_title : ''; } // phpcs:ignore
function strip_shortcodes( $t ) { return $t; } // phpcs:ignore
function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); } // phpcs:ignore
function sa_get_children( $id, $type ) { // phpcs:ignore
	return isset( $GLOBALS['ig']['children'][ (int) $id ] ) ? $GLOBALS['ig']['children'][ (int) $id ] : array();
}
function sa_county_profile_attractions( $id, $limit = 8 ) { // phpcs:ignore
	unset( $limit );
	return isset( $GLOBALS['ig']['atts'][ (int) $id ] ) ? $GLOBALS['ig']['atts'][ (int) $id ] : array();
}
function sa_county_profile_meta( $id, $key ) { return trim( (string) get_post_meta( $id, $key, true ) ); } // phpcs:ignore
function sa_county_profile_bundled( $id ) { // phpcs:ignore
	static $map = null;
	if ( null === $map ) {
		$map = require SA_CHILD_DIR . 'data/county-experiences.php';
	}
	$p    = get_post( $id );
	$slug = $p ? $p->post_name : '';
	foreach ( array( $slug, preg_replace( '/-city$/', '', $slug ), $slug . '-city' ) as $k ) {
		if ( isset( $map[ $k ] ) ) {
			return $map[ $k ];
		}
	}
	return array();
}

require $theme_dir . '/inc/helpers.php';

$long  = str_repeat( 'واژه‌ای برای متن اصلی صفحه ', 120 ); // ~600 words.
$short = str_repeat( 'متن ', 50 );
$add   = static function ( $id, $slug, $content, $meta = array() ) {
	$GLOBALS['ig']['posts'][ $id ] = new SA_IG_Post( $id, 'city', $slug, $content );
	$GLOBALS['ig']['meta'][ $id ]  = $meta;
};

// 1. Superset of the old rule.
$add( 1, 'legacy-children', $long );
$GLOBALS['ig']['children'][1] = array( new SA_IG_Post( 100, 'attraction', 'x', '', 'publish', 'قلعه رودخان' ) );
$GLOBALS['ig']['atts'][1]     = array( array( 'name' => 'قلعه رودخان' ) );
sa_ig_assert( ! sa_city_is_thin( 1 ), 'page with an attraction child stays index' );
$add( 2, 'legacy-poi', $long, array( 'sa_cty_poi_nature' => 'بوستان' ) );
sa_ig_assert( ! sa_city_is_thin( 2 ), 'old rule counted any non-empty sa_cty_poi_*; still index (no de-indexing)' );
$add( 3, 'legacy-draft-child', $long );
$GLOBALS['ig']['children'][3] = array( new SA_IG_Post( 101, 'attraction', 'y', '', 'draft', 'دیدنی پیش‌نویس' ) );
sa_ig_assert( ! sa_city_is_thin( 3 ), 'old rule counted any child; still index' );

// 2. extra_places kinds and names.
$add( 10, 'no-bundle-a', $long, array( 'extra_places' => "هتل نمونه | اقامت | مرکز شهر\nپایانه | حمل‌ونقل | غرب\nرستوران | خوراک | مرکز\nبوستان | تفریحی | شمال" ) );
$d = sa_city_index_decision( 10 );
sa_ig_assert( false === $d['index'] && 'no_places' === $d['reason'] && 0 === $d['places'], 'lodging/transport/food/bare names do not open the gate' );
$add( 11, 'no-bundle-b', $long, array( 'extra_places' => "باغ ارم | تاریخی | خیابان ارم | ساعت بازدید را چک کنید\nدره نمونه | طبیعت | شمال شهر" ) );
$d = sa_city_index_decision( 11 );
sa_ig_assert( true === $d['index'] && 2 === $d['places'], 'visitable extra_places open the gate (got ' . $d['places'] . ')' );
sa_ig_assert( '' === sa_city_noindex_reason( 11 ), 'no reason when indexable' );

// 3. Thin text.
$add( 12, 'no-bundle-c', $short, array( 'extra_places' => 'باغ ارم | تاریخی | خیابان ارم' ) );
sa_ig_assert( 'thin_text' === sa_city_noindex_reason( 12 ), 'under 300 words → thin_text' );
$add( 13, 'no-bundle-d', $long );
sa_ig_assert( 'no_places' === sa_city_noindex_reason( 13 ), 'no places → no_places' );

// 4. Manual noindex wins.
$add( 14, 'no-bundle-e', $long, array( 'sa_noindex' => '1', 'extra_places' => 'باغ ارم | تاریخی | خیابان ارم' ) );
sa_ig_assert( 'manual' === sa_city_noindex_reason( 14 ), 'manual sa_noindex always wins' );
sa_ig_assert( '' !== sa_city_noindex_reason_label( 'no_places' ), 'reason label exists' );

// 5. Group A slugs from the bundle (meta empty → bundled extra_places).
$group_a = array( 'mashhad', 'neyshabur', 'sabzevar', 'shahriar', 'qods', 'khomeyni-shahr', 'bandar-e-abbas' );
$id      = 200;
foreach ( $group_a as $slug ) {
	$add( ++$id, $slug, $long );
	$d = sa_city_index_decision( $id );
	sa_ig_assert( $d['index'] && $d['places'] >= 3, "group A {$slug} opens the gate (places {$d['places']})" );
}
// Meta beats bundle: a non-visitable meta list replaces the bundle.
$add( 300, 'mashhad', $long, array( 'extra_places' => 'هتل | اقامت | مرکز' ) );
sa_ig_assert( 'no_places' === sa_city_noindex_reason( 300 ), 'post meta extra_places wins over the bundle' );
// Not a city → never thin.
$GLOBALS['ig']['posts'][400] = new SA_IG_Post( 400, 'post', 'blog', $short );
sa_ig_assert( ! sa_city_is_thin( 400 ), 'non-city never gated' );

// 6. Description picker.
if ( ! function_exists( 'mb_strlen' ) ) {
	fwrite( STDERR, "SKIP mb tests\n" );
} else {
	sa_ig_load_function( $theme_dir . '/inc/seo.php', 'sa_truncate_to_155' );
	sa_ig_load_function( $theme_dir . '/inc/seo.php', 'sa_seo_has_placeholder' );
	sa_ig_load_function( $theme_dir . '/inc/seo.php', 'sa_seo_city_pick_description' );
	$custom = 'شهرستان اصفهان با میدان نقش جهان، سی‌وسه‌پل، محله جلفا و باغ‌های تاریخی؛ راهنمای رفت‌وآمد، فصل سفر و جاهای دیدنی.';
	$lead   = 'شهرستان اصفهان؛ پرجمعیت‌ترین شهرستان استان اصفهان و مرکز آن.';
	sa_ig_assert( sa_seo_city_pick_description( array( $custom, $lead, '' ) ) === $custom, 'full custom description beats a 60-char excerpt' );
	sa_ig_assert( sa_seo_city_pick_description( array( 'کوتاه', $custom, '' ) ) === $custom, 'stale short custom does not beat a full lead' );
	sa_ig_assert( sa_seo_city_pick_description( array( '', $lead, '' ) ) === $lead, 'falls back to first non-empty' );
	sa_ig_assert( mb_strlen( sa_seo_city_pick_description( array( str_repeat( 'واژه ', 80 ) ) ) ) <= 155, 'truncated to 155' );
	sa_ig_assert( '' === sa_seo_city_pick_description( array( '', '  ' ) ), 'empty when nothing' );
	// 6b. v2.22.76: template descriptions with an empty-data dash are skipped.
	$bad = 'راهنمای شهرستان شهریار؛ جمعیت ۷۴۴٬۲۱۰ نفر (۱۳۹۵)، مساحت — کیلومتر مربع، سال تأسیس ۱۳۵۲ و موقعیت آن در استان تهران.';
	sa_ig_assert( sa_seo_has_placeholder( $bad ), 'placeholder «مساحت — کیلومتر» detected' );
	sa_ig_assert( sa_seo_has_placeholder( 'جمعیت — نفر' ), 'placeholder «جمعیت —» detected' );
	sa_ig_assert( ! sa_seo_has_placeholder( 'یخ در بهشت و بستنی سنتی بکنی — راهنمای خوراکی‌های شیراز' ), 'normal dash punctuation is fine' );
	sa_ig_assert( ! sa_seo_has_placeholder( $custom ), 'full description has no placeholder' );
	sa_ig_assert( sa_seo_city_pick_description( array( $bad, $custom ) ) === $custom, 'placeholder custom falls through to the next candidate' );
	sa_ig_assert( sa_seo_city_pick_description( array( $bad ) ) === '', 'placeholder-only → empty (fallback description takes over)' );
	// 6c. Tagline whitespace.
	sa_ig_load_function( $theme_dir . '/inc/seo.php', 'sa_normalize_blogdescription' );
	sa_ig_assert( 'راهنمای سفر ایران' === sa_normalize_blogdescription( '  راهنمای  سفر   ایران ' ), 'blogdescription double spaces collapsed' );
	sa_ig_assert( "نیم\u{200C}فاصله" === sa_normalize_blogdescription( "نیم\u{200C}فاصله" ), 'ZWNJ untouched' );
}

// 7. robots.txt.
$seo = file_get_contents( $theme_dir . '/inc/seo.php' );
sa_ig_assert( false !== strpos( $seo, "stripos( (string) \$output, 'Sitemap:' )" ), 'robots.txt adds Sitemap only when absent' );
sa_ig_assert( false !== strpos( $seo, 'sa_seo_city_pick_description(' ), 'city description uses the picker' );
sa_ig_assert( false !== strpos( $seo, "'' !== sa_city_noindex_reason( \$pid )" ), 'sitemap excludes every noindex city (manual too)' );

// 8. R4 guard and bridge.
$rel = file_get_contents( $theme_dir . '/inc/relations.php' );
sa_ig_assert( false !== strpos( $rel, 'function sa_entity_dependant_type(' ), 'dependant helper exists' );
sa_ig_assert( preg_match( '/REST_REQUEST.*?return false;/s', $rel ), 'R4 guard returns false in REST instead of wp_die' );
$bridge = file_get_contents( $theme_dir . '/inc/agent-bridge.php' );
sa_ig_assert( false !== strpos( $bridge, "'sa_agent_has_dependants'" ), 'bridge answers sa_agent_has_dependants' );
sa_ig_assert( strpos( $bridge, "'sa_agent_has_dependants'" ) < strpos( $bridge, '$res = wp_trash_post(' ), 'dependants checked before trash' );
sa_ig_assert( false !== strpos( $bridge, 'sa_flush_relation_cache();' ) && false !== strpos( $bridge, "delete_transient( 'sa_sitemap_thin_city' );" ), 'bridge meta update flushes relation + sitemap caches' );
sa_ig_assert( false !== strpos( $bridge, "\$snap['index_gate']" ), 'bridge GET exposes index_gate' );
sa_ig_assert( false === strpos( substr( $bridge, strpos( $bridge, 'function sa_agent_snapshot_post' ), 2500 ), 'index_gate' ), 'index_gate is not part of backups' );

// 9. agent-ops guards.
$ops = file_get_contents( $theme_dir . '/inc/agent-ops.php' );
foreach ( array( "'owner_approved_theme_update'", "'owner_approved_backup'", 'sa_agent_updraft_recent_ok(', '->upgrade( $slug )', "'sa_agent_need_backup'", "current_user_can( 'update_themes' )" ) as $needle ) {
	sa_ig_assert( false !== strpos( $ops, $needle ), "agent-ops contains {$needle}" );
}
sa_ig_assert( false !== strpos( $ops, "'/relations-audit'" ) && false !== strpos( $ops, 'pp.ID IS NULL OR pp.post_status <> \'publish\'' ), 'relations-audit route (read-only) exists' );
sa_ig_assert( false !== strpos( $ops, "'/cache-purge'" ) && false !== strpos( $ops, "do_action( 'litespeed_purge_all'" ) && false !== strpos( $ops, "do_action( 'litespeed_purge_post'" ), 'cache-purge uses the official LiteSpeed hooks' );
$act = file_get_contents( $theme_dir . '/inc/activation.php' );
sa_ig_assert( false !== strpos( $act, "do_action( 'litespeed_purge_all'" ) && strpos( $act, "do_action( 'litespeed_purge_all'" ) > strpos( $act, "update_option( 'sa_child_version'" ), 'theme version change purges the LiteSpeed cache' );
sa_ig_assert( false !== strpos( $seo, "add_filter( 'option_blogdescription', 'sa_normalize_blogdescription' );" ), 'tagline filter hooked' );
sa_ig_assert( false === stripos( $ops, 'delete_theme' ) && false === stripos( $ops, "'DELETE'" ), 'agent-ops never deletes' );
$functions = file_get_contents( $theme_dir . '/functions.php' );
sa_ig_assert( false !== strpos( $functions, "'inc/agent-ops.php'" ), 'agent-ops is loaded' );
sa_ig_assert( strpos( $functions, "'inc/agent-ops.php'" ) < strpos( $functions, "'inc/github-updater.php'" ), 'loaded in the include list' );

// 10. v2.22.78: /blog/ (posts page) has exactly one H1.
$home = file_get_contents( $theme_dir . '/home.php' );
sa_ig_assert( 1 === substr_count( $home, '<h1' ), 'home.php prints exactly one H1' );
sa_ig_assert( false !== strpos( $home, "get_option( 'page_for_posts' )" ), 'H1 is the posts page title' );

echo "OK: index gate + critical fixes (2.22.75–2.22.78)\n";
