<?php
/**
 * Standalone test: the official bundled logo is the single brand mark.
 *
 * The owner supplied the new wide lockup on 2026-11-12 (dotted Iran map
 * + "Sarzamin aryan" lettering, transparent background, ratio 2.398:1).
 * The theme ships a white rendering for dark surfaces (header, footer),
 * a near-black rendering for light surfaces (Organization schema) and
 * square map-only icons for the site icon/favicon, because a 2.4:1
 * lockup cannot sit in a square icon slot. Header, footer and schema
 * must not drift back to a media-library custom logo, because that
 * previously allowed different parts of the site to keep different (and
 * oversized) logos.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-brand-logo.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_URI' ) ) {
	define( 'SA_CHILD_URI', 'https://example.test/wp-content/themes/sarzaminaryan-child/' );
}

function add_action() {
	return true;
}
function add_filter() {
	return true;
}

function sa_brand_logo_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/seo.php';

$expected = array(
	'light' => array(
		960 => array( 'logo-light-960.webp', 960, 400 ),
		480 => array( 'logo-light-480.webp', 480, 200 ),
	),
	'dark'  => array(
		1200 => array( 'logo-dark-1200.webp', 1200, 500 ),
		600  => array( 'logo-dark-600.webp', 600, 250 ),
	),
	'icon'  => array(
		512 => array( 'icon-dark-512.webp', 512, 512 ),
		192 => array( 'icon-dark-192.webp', 192, 192 ),
		96  => array( 'icon-dark-96.webp', 96, 96 ),
	),
);
foreach ( $expected as $variant => $sizes ) {
	foreach ( $sizes as $size => $spec ) {
		list( $file, $w, $h ) = $spec;
		$logo = sa_brand_logo( $variant, $size );
		sa_brand_logo_assert( SA_CHILD_URI . 'assets/img/' . $file === $logo['url'], "{$file}: helper URL" );
		sa_brand_logo_assert( $w === $logo['width'] && $h === $logo['height'], "{$file}: helper dimensions" );

		$path  = $theme_dir . '/assets/img/' . $file;
		$image = file_exists( $path ) ? getimagesize( $path ) : false;
		sa_brand_logo_assert( false !== $image, "{$file}: file exists and is a valid image" );
		sa_brand_logo_assert( $w === $image[0] && $h === $image[1], "{$file}: actual dimensions are {$w}x{$h}" );
	}
}

// White map icons for dark UI chrome (admin bar) must exist too.
foreach ( array( 512, 192, 96 ) as $size ) {
	$file = "icon-light-{$size}.webp";
	$path = $theme_dir . '/assets/img/' . $file;
	$img  = file_exists( $path ) ? getimagesize( $path ) : false;
	sa_brand_logo_assert( false !== $img && $size === $img[0] && $size === $img[1], "{$file}: white map icon exists at {$size}x{$size}" );
	sa_brand_logo_assert( SA_CHILD_URI . 'assets/img/' . $file === sa_brand_icon_light_url( $size ), "{$file}: light icon URL" );
}

sa_brand_logo_assert( sa_brand_logo_url( 'light', 999 ) === sa_brand_logo_url( 'light', 960 ), 'unknown light size falls back to the largest light lockup' );
sa_brand_logo_assert( sa_brand_logo_url( 'dark', 999 ) === sa_brand_logo_url( 'dark', 1200 ), 'unknown dark size falls back to the largest dark lockup' );
sa_brand_logo_assert( sa_brand_logo_url( 'nope' ) === sa_brand_logo_url( 'light' ), 'unknown variant falls back to the light lockup' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 32, 0 ) === sa_brand_logo_url( 'icon', 96 ), 'small site icon uses the 96 map icon' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 180, 0 ) === sa_brand_logo_url( 'icon', 192 ), 'touch site icon uses the 192 map icon' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 512, 0 ) === sa_brand_logo_url( 'icon', 512 ), 'large site icon uses the 512 map icon' );

$seo    = (string) file_get_contents( $theme_dir . '/inc/seo.php' );
$header = (string) file_get_contents( $theme_dir . '/header.php' );
$footer = (string) file_get_contents( $theme_dir . '/footer.php' );
$schema = (string) file_get_contents( $theme_dir . '/inc/schema.php' );
$css    = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );

/*
 * 2026 refresh: the sticky header is a light surface with the mockup's teal
 * brand tile, so it carries the bundled white MAP ICON (never a redrawn logo),
 * and the light footer carries the bundled DARK lockup. Both still come from
 * the official asset helpers and keep an explicit box to avoid CLS.
 */
sa_brand_logo_assert(
	1 === preg_match( '~if \( ! is_front_page\(\) \) : \?>\s*<span class="sa-hf-brand__tile[^<]*.*?sa_brand_icon_light_url\( 96 \).*?</span>\s*<\?php endif; \?>\s*<span class="sa-hf-brand__text~s', $header ),
	'front page omits the entire map tile but keeps the linked brand text'
);
sa_brand_logo_assert( false !== strpos( $header, 'sa_brand_icon_light_url( 96 )' ), 'header uses the bundled white map icon' );
sa_brand_logo_assert( false !== strpos( $header, 'width="24" height="24"' ), 'header icon declares its 24x24 box (no CLS)' );
sa_brand_logo_assert( false !== strpos( $header, 'class="sa-hf-brand"' ), 'header brand link keeps the sa-hf-brand hook' );
sa_brand_logo_assert( false !== strpos( $footer, "sa_brand_logo_url( 'dark', 480 )" ), 'footer uses the bundled dark lockup on its light surface' );
sa_brand_logo_assert( false !== strpos( $footer, 'width="72" height="30"' ), 'footer logo declares the wide 72x30 box (no CLS)' );
sa_brand_logo_assert( false !== strpos( $schema, "sa_brand_logo( 'dark', 1200 )" ), 'Organization schema uses the bundled dark lockup' );
sa_brand_logo_assert( false !== strpos( $seo, "'site_icon_url', 'sa_brand_site_icon_url'" ), 'site icon URL filter uses the bundled logo' );
sa_brand_logo_assert( false !== strpos( $seo, 'sa_brand_site_icon_fallback' ), 'site icon fallback exists when WordPress has no stored icon' );

foreach ( array( 'header' => $header, 'footer' => $footer, 'schema' => $schema ) as $label => $source ) {
	sa_brand_logo_assert( false === strpos( $source, "get_theme_mod( 'custom_logo' )" ), "{$label}: media-library custom logo must not override the official logo" );
	sa_brand_logo_assert( false === strpos( $source, 'has_custom_logo()' ), "{$label}: custom-logo branch must not override the official logo" );
}

// The previous square logo files must be gone everywhere.
foreach ( array( 'logo.webp', 'logo-192.webp', 'logo-96.webp' ) as $old ) {
	sa_brand_logo_assert( ! file_exists( $theme_dir . '/assets/img/' . $old ), "old square logo {$old} is removed" );
	foreach ( array( 'header' => $header, 'footer' => $footer, 'schema' => $schema, 'seo' => $seo ) as $label => $source ) {
		sa_brand_logo_assert( false === strpos( $source, $old ), "{$label}: no reference to removed {$old}" );
	}
}

// The wide transparent lockup must not be squeezed into a square card again.
sa_brand_logo_assert( false === strpos( $css, 'background: #050505;' ), 'old black logo card background is removed' );
sa_brand_logo_assert( false === strpos( $css, 'object-fit: cover;' ) || false !== strpos( $css, '.sa-hf-logo img' ), 'logo image rule exists' );
sa_brand_logo_assert( false !== strpos( $css, 'height: 44px;' ), 'header lockup renders 44px tall' );
sa_brand_logo_assert( false !== strpos( $css, 'width: 72px;' ), 'footer mini lockup renders 72x30' );

echo "PASS: wide transparent brand logo is bundled and used by header, footer, schema and site icon\n";
