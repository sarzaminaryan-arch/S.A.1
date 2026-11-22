<?php
/**
 * Standalone test: AI visibility bundle (2.22.48) — AI-1/AI-2/AI-4/AI-5.
 *
 * Locks the decisions recorded in docs/live-site/ai-visibility-plan.md:
 *
 *  1. robots.txt — the theme appends explicit AI-crawler groups on top of
 *     the existing rules: OAI-SearchBot and ChatGPT-User are allowed,
 *     GPTBot (training crawler) is blocked by the owner's decision of
 *     2026-11-12, and the core sitemap URL is advertised. Existing output
 *     is preserved verbatim, a non-public site keeps core's blanket
 *     Disallow, and the rules are never duplicated.
 *  2. /llms.txt is served by the theme (rewrite rule + query var +
 *     template_redirect fallback) with the site name, language, the real
 *     section archive URLs and the sitemap link.
 *  3. The Organization schema node carries alternateName "Sarzamin Aryan"
 *     and a Persian description (AI-4).
 *  4. The header brand link has an accessible name (aria-label) and is a
 *     wordmark only since v2.24.11 (the cropped teal logo tile is gone for
 *     good), so no lazy-load plugin can defer a brand image that no longer
 *     exists; the official lockup lives in the footer and the schema.
 *  5. In-text links are underlined (CSS lock) so they are distinguishable
 *     without colour alone (P5 accessibility follow-up), while buttons,
 *     chips, entity bars and TOC links keep their own affordances.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-ai-visibility.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_URI' ) ) {
	define( 'SA_CHILD_URI', 'https://sarzaminaryan.ir/wp-content/themes/sarzaminaryan-child/' );
}

$GLOBALS['sa_ai_filters'] = array();
function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['sa_ai_filters'][ $hook ][] = $callback;
	return true;
}
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) {
	if ( null !== $callback ) {
		$GLOBALS['sa_ai_filters'][ $hook ][] = $callback;
	}
	return true;
}
function home_url( $path = '' ) {
	return 'https://sarzaminaryan.ir' . $path;
}
function get_theme_mod( $name, $default = false ) {
	unset( $name );
	return $default ? $default : '';
}
function get_bloginfo( $show = '' ) {
	unset( $show );
	return 'سرزمین آریان';
}
function sa_archive_url( $type ) {
	return home_url( '/' . $type . '/' );
}

function sa_ai_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/seo.php';
require_once $theme_dir . '/inc/schema.php';

/* ---- 1. robots.txt rules ------------------------------------------------ */

$core_robots = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
$out         = sa_ai_robots_txt( $core_robots, true );

sa_ai_assert( 0 === strpos( $out, $core_robots ), 'robots: existing rules are preserved verbatim at the top' );
sa_ai_assert( false !== strpos( $out, "User-agent: OAI-SearchBot\nAllow: /\n" ), 'robots: OAI-SearchBot explicitly allowed (AI-1)' );
sa_ai_assert( false !== strpos( $out, "User-agent: ChatGPT-User\nAllow: /\n" ), 'robots: ChatGPT-User explicitly allowed (AI-1)' );
sa_ai_assert( false !== strpos( $out, "User-agent: GPTBot\nDisallow: /\n" ), 'robots: GPTBot blocked (AI-2, owner decision 2026-11-12)' );
sa_ai_assert( false !== strpos( $out, 'Sitemap: https://sarzaminaryan.ir/wp-sitemap.xml' ), 'robots: core sitemap advertised (AI-1)' );

$again = sa_ai_robots_txt( $out, true );
sa_ai_assert( 1 === substr_count( $again, 'OAI-SearchBot' ), 'robots: rules are never duplicated on re-run' );

$private = sa_ai_robots_txt( "User-agent: *\nDisallow: /\n", 0 );
sa_ai_assert( "User-agent: *\nDisallow: /\n" === $private, 'robots: non-public site keeps the blanket Disallow untouched' );
sa_ai_assert( false === strpos( $private, 'GPTBot' ), 'robots: non-public site gets no AI groups' );

sa_ai_assert( isset( $GLOBALS['sa_ai_filters']['robots_txt'] ) && in_array( 'sa_ai_robots_txt', $GLOBALS['sa_ai_filters']['robots_txt'], true ), 'robots: robots_txt filter registered' );

/* ---- 2. llms.txt -------------------------------------------------------- */

$llms = sa_llms_txt_content();
sa_ai_assert( 0 === strpos( $llms, '# سرزمین آریان (Sarzamin Aryan)' ), 'llms: starts with the brand heading' );
sa_ai_assert( false !== strpos( $llms, 'fa-IR' ), 'llms: declares the site language' );
foreach ( array( 'province', 'city', 'attraction' ) as $section ) {
	sa_ai_assert( false !== strpos( $llms, 'https://sarzaminaryan.ir/' . $section . '/' ), "llms: links the {$section} archive" );
}
sa_ai_assert( false !== strpos( $llms, 'https://sarzaminaryan.ir/wp-sitemap.xml' ), 'llms: links the XML sitemap' );
sa_ai_assert( isset( $GLOBALS['sa_ai_filters']['query_vars'] ) && in_array( 'sa_llms_txt_query_vars', $GLOBALS['sa_ai_filters']['query_vars'], true ), 'llms: query var filter registered' );
sa_ai_assert( array( 'sa_llms_txt' ) === array_values( array_diff( sa_llms_txt_query_vars( array() ), array() ) ), 'llms: query var added' );
sa_ai_assert( isset( $GLOBALS['sa_ai_filters']['template_redirect'] ) && in_array( 'sa_llms_txt_serve', $GLOBALS['sa_ai_filters']['template_redirect'], true ), 'llms: template_redirect serving registered' );

$seo_src = (string) file_get_contents( $theme_dir . '/inc/seo.php' );
sa_ai_assert( false !== strpos( $seo_src, "add_rewrite_rule( '^llms\\.txt$', 'index.php?sa_llms_txt=1', 'top' );" ), 'llms: top rewrite rule for llms.txt registered' );
sa_ai_assert( false !== strpos( $seo_src, "'Content-Type: text/plain; charset=utf-8'" ), 'llms: served as plain text' );

/* ---- 3. Organization schema (AI-4) -------------------------------------- */

$org = sa_schema_organization();
sa_ai_assert( 'Organization' === $org['@type'], 'schema: Organization node type' );
sa_ai_assert( 'Sarzamin Aryan' === $org['alternateName'], 'schema: alternateName is the Latin brand (AI-4)' );
sa_ai_assert( isset( $org['description'] ) && '' !== trim( (string) $org['description'] ) && false !== strpos( $org['description'], 'ایران' ), 'schema: Persian description present (AI-4)' );
sa_ai_assert( isset( $org['logo']['url'] ) && false !== strpos( $org['logo']['url'], 'logo-dark-1200.webp' ), 'schema: bundled dark logo kept' );

/* ---- 4. Header brand link + logo ---------------------------------------- */

$header = (string) file_get_contents( $theme_dir . '/header.php' );
sa_ai_assert( false !== strpos( $header, 'class="sa-hf-brand" href="<?php echo esc_url( home_url( \'/\' ) ); ?>" rel="home" aria-label="' ), 'header: brand link carries an aria-label (discernible name without visible text)' );
sa_ai_assert( false === strpos( $header, '<img' ), 'header: wordmark only — no logo image left to lazy-load (v2.24.11)' );
sa_ai_assert( false !== strpos( $header, '<span class="sa-hf-brand__text' ), 'header: the brand text itself carries the name' );

/* ---- 5. In-text link underline (P5 accessibility follow-up) ------------- */

$css = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );
$pos = strpos( $css, 'v2.22.48 — تشخیص‌پذیری لینک‌های درونِ متن' );
sa_ai_assert( false !== $pos, 'css: v2.22.48 link-distinguishability block exists' );
$block = substr( $css, $pos, 1600 );
sa_ai_assert( false !== strpos( $block, '.sa-archive__seo-index a' ), 'css: archive text-review links are covered' );
sa_ai_assert( false !== strpos( $block, '.sa-card__excerpt a' ), 'css: card excerpt links are covered' );
sa_ai_assert( false !== strpos( $block, '.site-main p a:not(.sa-btn):not(.sa-chip):not(.sa-bar):not(.sa-toc__link)' ), 'css: prose links underlined; buttons/chips/bars/TOC excluded' );
sa_ai_assert( false !== strpos( $block, 'text-decoration: underline;' ), 'css: in-text links get a non-colour cue (underline)' );
sa_ai_assert( false !== strpos( $block, 'text-underline-offset: 3px;' ), 'css: underline offset set for readability' );

echo "PASS: AI visibility bundle (robots GPTBot blocked + AI search allowed, llms.txt, Organization alternateName/description, header aria-label/no-lazy, in-text link underline)\n";
