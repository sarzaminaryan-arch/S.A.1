<?php
/**
 * Guard: «آموزش سفر» is a real content type, and moving to it is safe.
 *
 * Owner report (۳۰ آبان ۱۴۰۵): «نقص جدی، این بخش مقالات در قسمت نوشته‌های اصلی وردپرس
 * هستند» — the 67 guides were ordinary posts, so the hub was only ever a filtered view
 * of everything else the site publishes.
 *
 * This guard runs without WordPress (plain PHP, like the other theme guards):
 *
 *   php wp-content/themes/sarzaminaryan-child/tests/test-guide-cpt.php
 *
 * Covered:
 *   1. the type exists, is named `guide`, keeps the `travel-guide` URL base, and has its
 *      own place in the admin menu (not «نوشته‌ها»);
 *   2. the section's editorial tooling follows the type: SEO box, table of contents,
 *      article schema, Open Graph, breadcrumbs, comments, related guides, education bar;
 *   3. the hub archive renders the 2026 listing and singles render the same article as
 *      before the move — no redesign, no lost field;
 *   4. old `/<slug>/` addresses answer 301, so nothing indexed can turn into a 404;
 *   5. the migration only runs by hand, only after a backup, and can be rolled back.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // Standalone run for the release gate.
}

define( 'SA_CPT_DIR', dirname( __DIR__ ) . '/' );

$failures = 0;

/**
 * Assert one condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function sa_cpt_assert( $condition, $message ) {
	global $failures;
	if ( $condition ) {
		return;
	}
	++$failures;
	fwrite( STDERR, "FAIL: {$message}\n" );
	// Also surface the message as a GitHub annotation, so a red build says *what* broke
	// even when the raw step log is not reachable.
	echo "::error::{$message}\n";
}

/**
 * Read a theme file ('' when it does not ship).
 *
 * @param string $relative Path inside the child theme.
 * @return string
 */
function sa_cpt_file( $relative ) {
	$path = SA_CPT_DIR . $relative;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

/* ---------------------------------------------------------------------------
 * 0) Runtime: the type helpers really answer, with WordPress stubbed out.
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
	function taxonomy_exists( $taxonomy = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return true;
	}
	function register_taxonomy_for_object_type() {
		return true;
	}
}

require_once dirname( __DIR__ ) . '/inc/guide-cpt.php';

sa_cpt_assert( 'guide' === sa_guide_type(), 'runtime: the section type is `guide`' );
sa_cpt_assert( 'travel-guide' === sa_guide_slug(), 'runtime: the URL base is still travel-guide' );
sa_cpt_assert( array( 'guide' ) === sa_guide_types(), 'runtime: the listing type list is the new type' );
sa_cpt_assert( true === sa_is_guide_type( 'guide' ), 'runtime: a guide is recognised' );
sa_cpt_assert( false === sa_is_guide_type( 'post' ), 'runtime: an ordinary post is NOT a guide' );
sa_cpt_assert( false === sa_is_guide_type( 'page' ), 'runtime: a page is NOT a guide' );
sa_cpt_assert( array( 'post', 'guide' ) === sa_guide_comment_types( array( 'post' ) ), 'runtime: comments follow the new type' );
sa_cpt_assert( 'website' === sa_guide_og_type( 'website', 0 ), 'runtime: a request without a post keeps its Open Graph type' );

$cpt      = sa_cpt_file( 'inc/guide-cpt.php' );
$migrate  = sa_cpt_file( 'inc/guide-migrate.php' );
$hub      = sa_cpt_file( 'inc/guide-hub.php' );
$funcs    = sa_cpt_file( 'functions.php' );
$archive  = sa_cpt_file( 'archive-guide.php' );
$single   = sa_cpt_file( 'single-guide.php' );
$wrap     = sa_cpt_file( 'template-guide.php' );
$seo      = sa_cpt_file( 'inc/seo.php' );
$schema   = sa_cpt_file( 'inc/schema.php' );
$crumbs   = sa_cpt_file( 'inc/breadcrumbs.php' );
$meta     = sa_cpt_file( 'inc/meta-fields.php' );
$toc      = sa_cpt_file( 'inc/toc.php' );
$comments = sa_cpt_file( 'inc/comments-2026.php' );
$edu      = sa_cpt_file( 'inc/education.php' );
$tt       = sa_cpt_file( 'inc/template-tags.php' );

/* 1 — a real content type, with its own menu --------------------------------- */
sa_cpt_assert( '' !== $cpt, 'inc/guide-cpt.php ships' );
sa_cpt_assert( false !== strpos( $cpt, 'register_post_type(' ), 'the type is registered' );
sa_cpt_assert( false !== strpos( $cpt, "'has_archive'         => \$slug," ), 'the archive is the section URL' );
sa_cpt_assert( false !== strpos( $cpt, "'slug'       => \$slug," ), 'singles live under the same base' );
sa_cpt_assert( false !== strpos( $cpt, "'with_front' => false," ), 'the URL base ignores the front base' );
sa_cpt_assert( false !== strpos( $cpt, "'show_in_menu'        => 'sarzaminaryan'," ), 'the type has its own admin menu entry, not «نوشته‌ها»' );
sa_cpt_assert( false === strpos( $cpt, "'show_in_menu'        => true," ), 'the type never lands inside the posts menu' );
sa_cpt_assert( false !== strpos( $cpt, "'capability_type'     => 'post'," ), 'authors keep their usual capabilities' );
sa_cpt_assert( false !== strpos( $cpt, "'custom-fields'" ), 'the guides keep their custom fields' );
sa_cpt_assert( false !== strpos( $cpt, "'comments'" ), 'the guides keep comments' );
sa_cpt_assert( false !== strpos( $cpt, "array( 'category', 'post_tag' )" ), 'the guides keep categories and tags' );
sa_cpt_assert( false !== strpos( $cpt, "register_taxonomy_for_object_type( \$taxonomy, \$type )" ), 'the shared taxonomies are attached to the new type' );
sa_cpt_assert( false !== strpos( $cpt, "add_action( 'init', 'sa_register_guide_type', 9 )" ), 'the type is registered on init' );
sa_cpt_assert( false !== strpos( $cpt, 'function sa_guide_flush_rules()' ), 'rewrite rules are flushed when the type or its base changes' );
sa_cpt_assert( false !== strpos( $funcs, "'inc/guide-cpt.php'" ), 'functions.php loads the type' );
sa_cpt_assert( false !== strpos( $funcs, "'inc/guide-migrate.php'" ), 'functions.php loads the migration tool' );

/* 2 — the editorial tooling follows the type ---------------------------------- */
sa_cpt_assert( false !== strpos( $meta, 'sa_guide_type()' ), 'SEO meta boxes cover the new type' );
sa_cpt_assert( false !== strpos( $toc, 'sa_guide_type()' ), 'the table of contents covers the new type' );
sa_cpt_assert( false !== strpos( $schema, 'sa_is_guide_type( $post->post_type )' ), 'guides keep the Article schema' );
sa_cpt_assert( false !== strpos( $seo, 'sa_is_guide_type( get_post_type( $id ) )' ), 'guides keep the article Open Graph type' );
sa_cpt_assert( false !== strpos( $seo, 'is_singular( sa_guide_types() )' ), 'guides keep article:published_time' );
sa_cpt_assert( false !== strpos( $crumbs, 'sa_is_guide_type( $type )' ), 'guide breadcrumbs start at the hub' );
sa_cpt_assert( false !== strpos( $crumbs, "array( 'name' => 'آموزش سفر', 'url' => sa_2026_guide_url() )" ), 'the breadcrumb parent is «آموزش سفر»' );
sa_cpt_assert( false !== strpos( $comments, 'function sa_comments_2026_types()' ), 'the comment block has one type list' );
sa_cpt_assert( false !== strpos( $cpt, "add_filter( 'sa_comments_2026_types', 'sa_guide_comment_types' )" ), 'the new type joins the comment list' );
sa_cpt_assert( false !== strpos( $tt, "get_post_type( (int) \$post_id )" ), 'related guides query the same type as the article' );
sa_cpt_assert( false !== strpos( $edu, 'function sa_education_post_types()' ), 'the education bar reads both types during the transition' );

/* 3 — the hub is the archive, the article is unchanged ------------------------ */
sa_cpt_assert( '' !== $archive, 'archive-guide.php ships' );
sa_cpt_assert( false !== strpos( $archive, "require __DIR__ . '/template-guide.php';" ), 'the archive prints the same hub page' );
sa_cpt_assert( '' !== $wrap && false !== strpos( $wrap, 'template-parts/2026/guide-hub' ), 'the hub still renders the 2026 listing' );
sa_cpt_assert( '' !== $single, 'single-guide.php ships' );
sa_cpt_assert( false !== strpos( $single, "get_template_part( 'template-parts/content-single', 'post' )" ), 'a guide keeps the exact article layout of a post (no redesign)' );
sa_cpt_assert( false !== strpos( $hub, 'get_post_type_archive_link( sa_guide_type() )' ), 'the section URL is the type archive' );
sa_cpt_assert( false !== strpos( $hub, '$query->set( \'sa_guide_hub\', 1 )' ), 'the archive marks itself as the hub' );
sa_cpt_assert( false !== strpos( $hub, "'post_type', sa_guide_types()" ), 'the hub search stays inside the section' );
sa_cpt_assert( false !== strpos( $hub, 'sa_guide_has_guides()' ), 'the archive falls back while the guides are still posts' );
sa_cpt_assert( false !== strpos( $hub, "'category__in'" ), 'that fallback is limited to the section categories' );
sa_cpt_assert( false === strpos( $hub, 'add_rewrite_rule(' ), 'no virtual route shadows the real archive' );

/* 4 — nothing indexed turns into a 404 --------------------------------------- */
sa_cpt_assert( false !== strpos( $cpt, 'function sa_guide_legacy_redirect()' ), 'old guide URLs are redirected' );
sa_cpt_assert( false !== strpos( $cpt, 'get_page_by_path( $slug, OBJECT, sa_guide_type() )' ), 'the redirect only matches guides' );
sa_cpt_assert( false !== strpos( $cpt, 'wp_safe_redirect( $target, 301 )' ), 'the redirect is a 301' );
sa_cpt_assert( false !== strpos( $cpt, "'publish' !== \$found->post_status" ), 'unpublished guides are never exposed' );
sa_cpt_assert( false !== strpos( $cpt, "apply_filters( 'sa_guide_legacy_redirect', true )" ), 'the redirect has an off-switch' );

/* 5 — the migration is manual, backed up and reversible ---------------------- */
sa_cpt_assert( '' !== $migrate, 'inc/guide-migrate.php ships' );
sa_cpt_assert( false !== strpos( $migrate, "add_action( 'admin_post_sa_guide_migration', 'sa_guide_migration_handle' )" ), 'the tool runs only from its own admin form' );
sa_cpt_assert( false === strpos( $migrate, "add_action( 'init'" ), 'nothing migrates on init' );
sa_cpt_assert( false === strpos( $migrate, 'register_activation_hook' ), 'nothing migrates on activation' );
sa_cpt_assert( false !== strpos( $migrate, "current_user_can( 'manage_options' )" ), 'the tool is administrator-only' );
sa_cpt_assert( false !== strpos( $migrate, "check_admin_referer( 'sa_guide_' . \$action )" ), 'every action is nonce-checked' );
sa_cpt_assert( false !== strpos( $migrate, "if ( '' === \$backup_file || ! is_file( \$backup_file ) || 0 !== strpos( \$backup_file, \$dir ) )" ), 'the migration refuses to run without its snapshot' );
sa_cpt_assert( false !== strpos( $migrate, 'function sa_guide_backup_write(' ), 'the snapshot writer exists' );
sa_cpt_assert( false !== strpos( $migrate, "'/guides-' . gmdate( 'Ymd-His' ) . '.json'" ), 'snapshots are timestamped files' );
sa_cpt_assert( false !== strpos( $migrate, "'.htaccess'" ) && false !== strpos( $migrate, "'index.php'" ), 'the snapshot folder is protected' );
sa_cpt_assert( false !== strpos( $migrate, 'hash_file( \'sha256\', $file )' ), 'the snapshot checksum is shown' );
sa_cpt_assert( false !== strpos( $migrate, 'function sa_guide_rollback(' ), 'the rollback exists' );
sa_cpt_assert( false !== strpos( $migrate, "set_post_type( \$id, 'post' )" ), 'the rollback really returns the rows to posts' );
sa_cpt_assert( false !== strpos( $migrate, "if ( ! \$scope ) {" ), 'an empty category scope means “nothing to do”' );
sa_cpt_assert( false !== strpos( $migrate, "'category__in'           => \$scope," ), 'the migration never selects every post: it is limited to the section categories' );
sa_cpt_assert( false !== strpos( $migrate, 'function sa_guide_scope_counts()' ), 'the panel counts what the plan covers' );
sa_cpt_assert( false !== strpos( $migrate, "'outside' => max( 0, \$all - \$inside )," ), 'the count includes the posts left behind' );
sa_cpt_assert( false !== strpos( $migrate, 'منتقل نمی‌شود (بیرون از دسته‌های بخش)' ), 'the panel names the posts that will not move' );

if ( $failures > 0 ) {
	fwrite( STDERR, "\n{$failures} failure(s) in the «راهنمای سفر» content-type guard.\n" );
	exit( 1 );
}

echo "OK: «راهنمای سفر» is its own content type (menu, tooling, hub, 301s, backed-up migration).\n";
exit( 0 );
