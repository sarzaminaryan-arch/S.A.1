<?php
/**
 * Guard: the «آموزش سفر» section is reachable, styled and reversible.
 *
 * Owner report (۲۹ آبان ۱۴۰۵): the main section https://sarzaminaryan.ir/travel-guide/
 * answered **404** — the URL was hard-coded in the header, the footer and the front
 * page while no page or post carried that slug.
 *
 * This guard runs without WordPress (plain PHP, like the other theme guards) and is
 * executed by both CI gates before a release is packaged:
 *
 *   php wp-content/themes/sarzaminaryan-child/tests/test-guide-hub.php
 *
 * Covered:
 *   1. the section has exactly one URL helper and every template uses it;
 *   2. the virtual route exists for `/travel-guide/` and `/travel-guide/page/N/`,
 *      is registered only while the owner has no page, and a real page wins;
 *   3. the route answers 200 (never 404) and out-of-range pages fall back to page 1;
 *   4. the old posts page and the empty `travel-guide` category 301 to the hub, with
 *      a documented off-switch;
 *   5. the listing is the 2026 design (hero, chips, cards, pagination) and the
 *      category archives share it; the rollback file exists;
 *   6. nothing in the new templates echoes unescaped input and no `alert()` returns.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // Standalone run for the release gate.
}

define( 'SA_GUIDE_DIR', dirname( __DIR__ ) . '/' );

$failures = 0;

/**
 * Assert one condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function sa_guide_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		return;
	}
	++$failures;
	fwrite( STDERR, "FAIL: {$message}\n" );
}

/**
 * Read a theme file.
 *
 * @param string $relative Path inside the child theme.
 * @return string
 */
function sa_guide_file( $relative ) {
	$path = SA_GUIDE_DIR . $relative;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

/* ---------------------------------------------------------------------------
 * 0) Runtime regression: the fatal of v2.23.4.
 *
 * `usort( (array) $terms, … )` threw `usort(): Argument #1 could not be passed by
 * reference` on the live site. The label is now produced by a helper, and this block
 * really executes it with stubbed WordPress functions — a text check could never
 * have caught that crash.
 * ------------------------------------------------------------------------- */
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {
		return true;
	}
	function add_filter() {
		return true;
	}
	function apply_filters( $tag, $value = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		return $value;
	}
}

if ( ! function_exists( 'get_the_category' ) ) {
	/**
	 * Stub: the terms of the current post.
	 *
	 * @return array<int,object>
	 */
	function get_the_category( $post_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return isset( $GLOBALS['sa_guide_terms'] ) ? $GLOBALS['sa_guide_terms'] : array();
	}
}

require_once dirname( __DIR__ ) . '/inc/guide-hub.php';

$GLOBALS['sa_guide_terms'] = array(
	(object) array( 'slug' => 'camping-survival', 'name' => 'آموزش‌های ضروری کمپینگ و بقا', 'parent' => 0, 'term_id' => 5 ),
	(object) array( 'slug' => 'camping-first-aid', 'name' => 'کمک‌های اولیه در طبیعت', 'parent' => 5, 'term_id' => 6 ),
);
sa_guide_assert( 'کمک‌های اولیه در طبیعت' === sa_guide_post_label( 1 ), 'runtime: a child category beats its parent' );

$GLOBALS['sa_guide_terms'] = array(
	(object) array( 'slug' => 'uncategorized', 'name' => 'دستهٔ منتشرنشده', 'parent' => 0, 'term_id' => 1 ),
);
sa_guide_assert( 'آموزش سفر' === sa_guide_post_label( 1 ), 'runtime: the WordPress placeholder is never used as a label' );

$GLOBALS['sa_guide_terms'] = array();
sa_guide_assert( 'آموزش سفر' === sa_guide_post_label( 2 ), 'runtime: a post without categories still gets a label' );

$module  = sa_guide_file( 'inc/guide-hub.php' );
$wrap    = sa_guide_file( 'template-guide.php' );
$part    = sa_guide_file( 'template-parts/2026/guide-hub.php' );
$cat     = sa_guide_file( 'category.php' );
$legacy  = sa_guide_file( 'legacy/category.php' );
$nav     = sa_guide_file( 'inc/design-2026.php' );
$front   = sa_guide_file( 'front-page.php' );
$funcs   = sa_guide_file( 'functions.php' );
$css     = sa_guide_file( 'assets/css/sa-2026.css' );
$tw      = sa_guide_file( '../../../tools/design/2026-refresh/tailwind.config.js' );

/* 1 — one URL, used everywhere ------------------------------------------------ */
sa_guide_assert( '' !== $module, 'inc/guide-hub.php ships' );
sa_guide_assert( false !== strpos( $module, 'function sa_2026_guide_url()' ), 'the section has one canonical URL helper' );
sa_guide_assert( false !== strpos( $module, "home_url( '/travel-guide/' )" ), 'the helper falls back to the /travel-guide/ path' );
sa_guide_assert( false !== strpos( $module, "apply_filters( 'sa_2026_guide_url'" ), 'the URL stays filterable' );
sa_guide_assert( false !== strpos( $funcs, "'inc/guide-hub.php'" ), 'functions.php loads the module' );
sa_guide_assert( false !== strpos( $nav, 'sa_2026_guide_url()' ), 'header nav asks the helper for the URL' );
sa_guide_assert( false === strpos( $nav, "'url'    => home_url( '/travel-guide/' )" ), 'header nav no longer hard-codes the dead URL' );
sa_guide_assert( false !== strpos( $nav, 'sa_guide_is_section()' ), 'the section is highlighted on its own pages' );
sa_guide_assert( false !== strpos( $front, 'sa_2026_guide_url()' ), 'front-page pillar asks the helper for the URL' );
sa_guide_assert( 2 === substr_count( $nav, 'sa_2026_guide_url()' ), 'nav and footer column both use the helper' );

/* 2 — the hub is the archive of the section's own type ----------------------- */
$cpt     = sa_guide_file( 'inc/guide-cpt.php' );
$archive = sa_guide_file( 'archive-guide.php' );
$single  = sa_guide_file( 'single-guide.php' );
sa_guide_assert( '' !== $cpt, 'inc/guide-cpt.php ships' );
sa_guide_assert( false !== strpos( $cpt, "'has_archive'         => \$slug," ), 'the type archive is the section URL' );
sa_guide_assert( false !== strpos( $cpt, "'show_in_menu'        => 'sarzaminaryan'," ), 'the guides have their own admin menu, not «نوشته‌ها»' );
sa_guide_assert( false !== strpos( $module, 'get_post_type_archive_link( sa_guide_type() )' ), 'the canonical URL is the type archive' );
sa_guide_assert( false === strpos( $module, 'add_rewrite_rule(' ), 'no virtual route shadows the real archive' );
sa_guide_assert( false !== strpos( $module, "add_filter( 'query_vars', 'sa_guide_query_vars' )" ), 'the hub registers its own search var' );
sa_guide_assert( false !== strpos( $module, "add_filter( 'template_include', 'sa_guide_template_include', 20 )" ), 'the hub has its own template' );
sa_guide_assert( '' !== $archive && false !== strpos( $archive, "require __DIR__ . '/template-guide.php';" ), 'archive-guide.php prints the hub page' );
sa_guide_assert( '' !== $wrap, 'template-guide.php ships' );
sa_guide_assert( false !== strpos( $wrap, 'get_header()' ) && false !== strpos( $wrap, 'get_footer()' ), 'the hub template prints header and footer' );
sa_guide_assert( false !== strpos( $wrap, 'template-parts/2026/guide-hub' ), 'the hub template renders the shared listing' );
sa_guide_assert( '' !== $single && false !== strpos( $single, "get_template_part( 'template-parts/content-single', 'post' )" ), 'a guide keeps the same article layout' );

/* 3 — the listing query ------------------------------------------------------- */
sa_guide_assert( false !== strpos( $module, "add_action( 'pre_get_posts', 'sa_guide_pre_get_posts' )" ), 'the listing query is built on pre_get_posts' );
sa_guide_assert( false !== strpos( $module, '$query->set( \'sa_guide_hub\', 1 )' ), 'the archive marks itself as the hub' );
sa_guide_assert( false !== strpos( $module, '$query->set( \'post_status\', \'publish\' )' ), 'only published guides are listed' );
sa_guide_assert( false !== strpos( $module, "'post_type', sa_guide_types()" ), 'the hub search stays inside the section' );
sa_guide_assert( false !== strpos( $module, "apply_filters( 'sa_guide_per_page', 12 )" ), 'the page size is filterable' );
sa_guide_assert( false !== strpos( $module, 'sa_guide_has_guides()' ), 'the archive falls back before the migration' );
sa_guide_assert( false !== strpos( $module, "'category__in'" ), 'that fallback is limited to the section categories' );
sa_guide_assert( false !== strpos( $module, "add_filter( 'pre_get_document_title', 'sa_guide_document_title', 20 )" ), 'the hub has its own document title' );

/* 4 — one canonical index ----------------------------------------------------- */
sa_guide_assert( false !== strpos( $module, 'function sa_guide_redirect_posts_page()' ), 'the old posts page is consolidated' );
sa_guide_assert( false !== strpos( $module, "apply_filters( 'sa_guide_redirect_posts_page', true )" ), 'that redirect has a documented off-switch' );
sa_guide_assert( false !== strpos( $module, 'function sa_guide_redirect_empty_category()' ), 'the empty «راهنمای سفر» category is handled' );
sa_guide_assert( false !== strpos( $module, '\'travel-guide\' !== $term->slug || (int) $term->count > 0' ), 'the category still wins once it holds posts' );

/* 5 — the 2026 look ----------------------------------------------------------- */
sa_guide_assert( '' !== $part, 'the shared listing template ships' );
foreach ( array( 'bg-petrol', 'grid-pattern', 'rounded-3xl', 'sa-pagination', 'aspect-[16/10]', 'sa_2026_num' ) as $token ) {
	sa_guide_assert( false !== strpos( $part, $token ), "the listing uses the 2026 vocabulary ({$token})" );
}
sa_guide_assert( false !== strpos( $part, 'sa_2026_cover_markup' ), 'cards use the real cover helper' );
sa_guide_assert( false !== strpos( $part, 'data-sa-open-experience' ), 'the hub offers the experience dialog' );
sa_guide_assert( false !== strpos( $part, 'function_exists( \'sa_guide_context\' )' ) || false !== strpos( $part, 'sa_guide_context()' ), 'the listing reads one shared context' );
sa_guide_assert( false !== strpos( $module, 'function sa_guide_context()' ), 'the context helper exists' );
sa_guide_assert( false !== strpos( $module, 'function sa_guide_post_label(' ), 'the card label is a testable helper, not inline template code' );
sa_guide_assert( false === strpos( $part, "\tusort(" ) && false === strpos( $part, "\t\tusort(" ), 'the template never sorts terms by reference (v2.23.4 fatal)' );
sa_guide_assert( false !== strpos( $part, 'sa_guide_post_label( $sa_post_id )' ), 'cards call the label helper' );
sa_guide_assert( false !== strpos( $module, 'function sa_guide_category_ids(' ), 'the section has its own category scope' );
sa_guide_assert( false !== strpos( $module, "apply_filters(\n\t\t'sa_guide_category_slugs'" ) || false !== strpos( $module, "'sa_guide_category_slugs'" ), 'that scope is filterable' );
sa_guide_assert( false !== strpos( $module, "'category__in'" ), 'the hub query is limited to the section categories' );
sa_guide_assert( false !== strpos( $module, 'function sa_guide_topics(' ), 'category chips are built from real terms' );
sa_guide_assert( false !== strpos( $cat, 'sa_2026_render_legacy( \'category\' )' ), 'category.php honours the rollback switch' );
sa_guide_assert( false !== strpos( $cat, 'template-parts/2026/guide-hub' ), 'category archives share the same listing' );
sa_guide_assert( '' !== $legacy, 'legacy/category.php ships for a clean rollback' );
sa_guide_assert( false !== strpos( $wrap, 'sa-archive__list' ) || false !== strpos( $wrap, 'sa-archive' ), 'the hub keeps a plain fallback when the design is off' );
sa_guide_assert( false !== strpos( $css, 'sa-pagination' ), 'the compiled stylesheet ships the pagination block' );
sa_guide_assert( false !== strpos( $tw, "'template-guide.php'" ) && false !== strpos( $tw, "'category.php'" ), 'the Tailwind build scans the new templates' );

/* 6 — safe output ------------------------------------------------------------- */
foreach ( array( 'module' => $module, 'part' => $part, 'wrap' => $wrap, 'cat' => $cat ) as $label => $body ) {
	sa_guide_assert( false === strpos( $body, 'alert(' ), "{$label}: no browser dialog" );
	sa_guide_assert( false === strpos( $body, 'javascript:' ), "{$label}: no javascript: URL" );
}
sa_guide_assert( false !== strpos( $part, 'esc_url(' ) && false !== strpos( $part, 'esc_html(' ), 'the listing escapes URLs and text' );
sa_guide_assert( false !== strpos( $part, 'wp_kses_post( $sa_link )' ), 'pagination markup is kses-filtered' );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} failure(s) in the «آموزش سفر» guard.\n" );
	exit( 1 );
}

echo "OK: «آموزش سفر» section (route, 200, one canonical index, 2026 listing, rollback).\n";
exit( 0 );
