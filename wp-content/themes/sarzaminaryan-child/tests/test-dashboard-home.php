<?php
/**
 * Standalone test: برگهٔ داشبورد سر جایش می‌ماند + ترتیب و آکاردئون صفحهٔ نخست (2.22.54).
 *
 * The owner reported the cyber dashboard page had «پریده» — /dashboard
 * rendered the article list instead of the dashboard. Root cause class:
 * the page loses its `_wp_page_template` meta (or gets picked as the posts
 * page), so WordPress falls back to a default template. Guards added:
 *  1. sa_dashboard_template_include() always binds template-dashboard.php
 *     to the `dashboard` slug.
 *  2. sa_dashboard_assets() also accepts the slug when the meta is gone.
 *  3. sa_dashboard_page_url() accepts the published `dashboard` slug page.
 *  4. sa_dashboard_repair() re-binds meta + publish state on theme upgrade.
 * Also: the home page now shows the four counters above the top-7 cities
 * block, and that block is an accordion (<details>/<summary>).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-dashboard-home.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SA_CHILD_URI' ) ) {
	define( 'SA_CHILD_URI', 'https://example.test/wp-content/themes/sarzaminaryan-child/' );
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.22.54' );
}

$GLOBALS['t'] = array(
	'posts'     => array(),
	'meta'      => array(),
	'options'   => array( 'sa_default_pages' => array(), 'page_for_posts' => 0, 'page_on_front' => 0 ),
	'is_page'   => '',
	'enqueued'  => array(),
	'updated'   => array(),
	'page_path' => null,
);

function sa_dh_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}
function add_action() { return true; }
function add_filter() { return true; }
function apply_filters( $tag, $value = null ) { return $value; }
function is_page( $slug = '' ) { return '' !== $slug && $slug === $GLOBALS['t']['is_page']; }
function is_page_template( $tpl = '' ) { return false; }
function locate_template( $names ) { return '/child/template-dashboard.php'; }
function get_posts( $args ) { return array(); }
function get_option( $key, $default = false ) { return isset( $GLOBALS['t']['options'][ $key ] ) ? $GLOBALS['t']['options'][ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['t']['options'][ $key ] = $value; $GLOBALS['t']['updated'][ $key ] = $value; return true; }
function get_post_status( $id ) {
	if ( is_object( $id ) ) {
		$id = $id->ID;
	}
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ]['status'] : false;
}
function get_post( $id ) {
	if ( ! isset( $GLOBALS['t']['posts'][ $id ] ) ) {
		return null;
	}
	$p = new stdClass();
	$p->ID         = (int) $id;
	$p->post_type  = $GLOBALS['t']['posts'][ $id ]['type'];
	$p->post_name  = $GLOBALS['t']['posts'][ $id ]['slug'];
	$p->post_status = $GLOBALS['t']['posts'][ $id ]['status'];
	return $p;
}
function get_page_by_path( $path ) { return 'dashboard' === $path ? $GLOBALS['t']['page_path'] : null; }
function get_page_template_slug( $id ) { return isset( $GLOBALS['t']['meta'][ $id ]['_wp_page_template'] ) ? $GLOBALS['t']['meta'][ $id ]['_wp_page_template'] : ''; }
function get_permalink( $id ) { return 'https://example.test/dashboard/'; }
function get_the_title( $id = 0 ) { return 'داشبورد'; }
function wp_enqueue_style( $handle ) { $GLOBALS['t']['enqueued'][] = $handle; return true; }
function wp_enqueue_script( $handle ) { $GLOBALS['t']['enqueued'][] = $handle; return true; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['t']['meta'][ $id ][ $key ] = $value; $GLOBALS['t']['updated'][ 'meta:' . $id . ':' . $key ] = $value; return true; }
function wp_update_post( $args ) { if ( isset( $args['ID'], $args['post_status'] ) ) { $GLOBALS['t']['posts'][ $args['ID'] ]['status'] = $args['post_status']; } return $args['ID']; }
function wp_insert_post( $args ) { $id = 900; $GLOBALS['t']['posts'][ $id ] = array( 'type' => $args['post_type'], 'slug' => $args['post_name'], 'status' => $args['post_status'] ); return $id; }
function is_wp_error( $thing ) { return false; }

require_once SA_CHILD_DIR . 'inc/dashboard.php';
require_once SA_CHILD_DIR . 'inc/activation.php';

/* 1) template_include: داشبورد برای اسلاگ dashboard همیشه برمی‌گردد. */
$GLOBALS['t']['is_page'] = 'dashboard';
sa_dh_assert( '/child/template-dashboard.php' === sa_dashboard_template_include( '/parent/page.php' ), 'template: dashboard slug always renders the dashboard template' );
$GLOBALS['t']['is_page'] = 'about';
sa_dh_assert( '/parent/page.php' === sa_dashboard_template_include( '/parent/page.php' ), 'template: other pages keep their template' );

/* 2) assets: با متای پاک‌شده هم استایل/اسکریپت داشبورد لود می‌شود. */
$GLOBALS['t']['is_page'] = 'dashboard';
sa_dashboard_assets();
sa_dh_assert( in_array( 'sa-dashboard', $GLOBALS['t']['enqueued'], true ), 'assets: dashboard assets enqueue on the dashboard slug without meta' );

/* 3) نشانی داشبورد: برگهٔ منتشرشدهٔ اسلاگ dashboard حتی بدون متای قالب پیدا می‌شود. */
$GLOBALS['t']['posts'][77] = array( 'type' => 'page', 'slug' => 'dashboard', 'status' => 'publish' );
$GLOBALS['t']['page_path']  = get_post( 77 );
$GLOBALS['t']['meta'][77]['_wp_page_template'] = '';
$url = sa_dashboard_page_url();
sa_dh_assert( 'https://example.test/dashboard/' === $url, 'url: published dashboard slug resolves even when template meta is lost' );

/* 4) تعمیر هنگام ارتقا: متا + انتشار + اصلاح برگهٔ نوشته‌ها. */
$GLOBALS['t']['posts'][55] = array( 'type' => 'page', 'slug' => 'dashboard', 'status' => 'draft' );
$GLOBALS['t']['page_path']  = get_post( 55 );
$GLOBALS['t']['options']['sa_default_pages'] = array( 'blog' => 9, 'dashboard' => 0 );
$GLOBALS['t']['options']['page_for_posts'] = 55;
sa_dashboard_repair();
sa_dh_assert( 'template-dashboard.php' === $GLOBALS['t']['meta'][55]['_wp_page_template'], 'repair: template meta re-bound to the dashboard page' );
sa_dh_assert( 'publish' === $GLOBALS['t']['posts'][55]['status'], 'repair: dashboard page re-published' );
sa_dh_assert( 55 === $GLOBALS['t']['options']['sa_default_pages']['dashboard'], 'repair: canonical page id synced to the option' );
sa_dh_assert( 9 === (int) $GLOBALS['t']['options']['page_for_posts'], 'repair: dashboard is no longer the posts page' );

/* 5) صفحهٔ نخست تازه و مسیر داشبورد فقط در صورت وجود برگهٔ منتشرشده. */
$home = (string) file_get_contents( SA_CHILD_DIR . 'template-home.php' );
$front = (string) file_get_contents( SA_CHILD_DIR . 'front-page.php' );
/*
 * 2026 refresh: the front page is its own template now (front-page.php renders
 * the approved 2026 design) and must not fall back to the legacy landing
 * template any more. The legacy template stays available for pages only.
 */
sa_dh_assert( false === strpos( $front, "'/template-home.php'" ), 'front page no longer renders the legacy landing template' );
sa_dh_assert( false !== strpos( $front, 'hero-heading' ) && false !== strpos( $front, 'data-sa-search-input' ), 'front page renders the 2026 hero with its instant search' );
sa_dh_assert( false !== strpos( $home, 'sa_dashboard_page_url()' ) && false !== strpos( $home, 'if ( $sa_home_dashboard )' ), 'landing links to the dashboard only when available' );
sa_dh_assert( false !== strpos( $home, 'role="search"' ) && false !== strpos( $home, 'name="s"' ), 'landing keeps WordPress search' );

/* 6) v2.24.13 — the 2026 home page carries the cyber dashboard block again. */
sa_dh_assert( false !== strpos( $front, 'sa_dashboard_page_url()' ), 'front page reads the real dashboard URL' );
sa_dh_assert( false !== strpos( $front, 'id="dashboard-section"' ), 'front page renders the dashboard section' );
sa_dh_assert( false !== strpos( $front, "if ( '' !== \$sa_dash )" ), 'the dashboard section is skipped when no published page exists' );
sa_dh_assert( false !== strpos( $front, 'ورود به داشبورد تعاملی' ), 'the section links into the dedicated dashboard page' );
$module = (string) file_get_contents( SA_CHILD_DIR . 'inc/dashboard.php' );
sa_dh_assert( false !== strpos( $module, 'function sa_dashboard_ensure_page()' ), 'a missing dashboard page is restored for administrators' );
sa_dh_assert( false !== strpos( $module, "add_action( 'admin_init', 'sa_dashboard_ensure_page'" ), 'that repair runs in wp-admin only' );

echo "PASS: dashboard self-heal and landing page navigation\n";
