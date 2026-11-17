<?php
/**
 * Standalone test: Bold Vazirmatn is preloaded and the pre-swap fallback is
 * metric-matched to Vazirmatn (performance track P3, 2026-11-11).
 *
 * PageSpeed report 52w4rqrdi4 showed the homepage lab CLS flipping between
 * 0 and 0.176 across runs, always blamed on the hero CTA shifting when
 * Vazirmatn-Bold swapped in: the parent theme preloads only the Regular
 * face, so Bold was discovered late through the stylesheet, and the system
 * fallback it replaced wrapped/sized text differently. The fix has two
 * halves and this test guards both:
 *
 *  1. functions.php preloads Vazirmatn-Bold.woff2 (headings render Bold on
 *     every template) via sa_child_preload_bold_font() on wp_head.
 *  2. assets/css/child.css defines a 'Vazirmatn Fallback' @font-face over
 *     local system fonts with metric overrides taken from the bundled
 *     Vazirmatn files (ascent 2100, descent -1100, line-gap 0 on a 2048
 *     upem) and size-adjust 108% (mean advance of 57 Persian letters/digits:
 *     Vazirmatn 0.625em vs Noto Naskh Arabic 0.574em). The body stack
 *     (--font-family-body override) and the homepage stack in
 *     template-home.php must both route through that fallback face.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-font-loading.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function sa_font_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$theme_dir  = dirname( __DIR__ );
$functions  = file_get_contents( $theme_dir . '/functions.php' );
$child_css  = file_get_contents( $theme_dir . '/assets/css/child.css' );
$home_tpl   = file_get_contents( $theme_dir . '/template-home.php' );
$bold_font  = dirname( $theme_dir ) . '/sarzaminaryan/assets/fonts/fa/Vazirmatn-Bold.woff2';

// 1. The Bold face that headings wait for actually ships with the parent theme.
sa_font_assert( is_string( $functions ) && is_string( $child_css ) && is_string( $home_tpl ), 'theme files are readable' );
sa_font_assert( file_exists( $bold_font ), 'parent theme bundles Vazirmatn-Bold.woff2' );

// 2. Bold preload hook (parent preloads Regular only).
sa_font_assert( false !== strpos( $functions, 'function sa_child_preload_bold_font()' ), 'Bold preload function exists' );
sa_font_assert( false !== strpos( $functions, "add_action( 'wp_head', 'sa_child_preload_bold_font', 2 )" ), 'Bold preload is hooked to wp_head' );
sa_font_assert( false !== strpos( $functions, "get_template_directory_uri() . '/assets/fonts/fa/Vazirmatn-Bold.woff2'" ), 'Bold preload points at the parent font file' );
sa_font_assert( false !== strpos( $functions, 'rel="preload"' ) && false !== strpos( $functions, 'as="font"' ), 'Bold preload uses rel=preload as=font' );

// 3. Metric-matched fallback face (values measured from the bundled fonts).
sa_font_assert( false !== strpos( $child_css, "font-family: 'Vazirmatn Fallback';" ), 'fallback @font-face is defined' );
sa_font_assert( false !== strpos( $child_css, "local( 'Tahoma' )" ) && false !== strpos( $child_css, "local( 'Noto Naskh Arabic' )" ), 'fallback uses local Tahoma/Noto Naskh' );
sa_font_assert( false !== strpos( $child_css, 'size-adjust: 108%;' ), 'fallback size-adjust matches Vazirmatn advances' );
sa_font_assert( false !== strpos( $child_css, 'ascent-override: 102.54%;' ), 'fallback ascent override matches Vazirmatn (2100/2048)' );
sa_font_assert( false !== strpos( $child_css, 'descent-override: 53.71%;' ), 'fallback descent override matches Vazirmatn (1100/2048)' );
sa_font_assert( false !== strpos( $child_css, 'line-gap-override: 0%;' ), 'fallback line-gap override matches Vazirmatn (0)' );

// 4. Both font stacks route through the fallback face.
sa_font_assert( false !== strpos( $child_css, "--font-family-body: 'Vazirmatn', 'Vazirmatn Fallback'," ), 'body stack routes through the fallback' );
sa_font_assert(
	false !== strpos( $home_tpl, "font-family:Vazirmatn,'Vazirmatn Fallback',Tahoma,sans-serif" )
		|| false !== strpos( str_replace( array( ' ', "\n", "\t" ), '', $home_tpl ), "font-family:Vazirmatn,'VazirmatnFallback',Tahoma,sans-serif" ),
	'homepage stack routes through the fallback'
);

echo "test-font-loading: OK\n";
