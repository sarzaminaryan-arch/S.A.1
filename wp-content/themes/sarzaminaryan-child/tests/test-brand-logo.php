<?php
/**
 * Standalone test: the official bundled logo is the single brand mark.
 *
 * The owner supplied the 512×512 Sarzamin Aryan logo on 2026-11-11. The
 * theme serves responsive 96/192-pixel derivatives in the footer/header
 * and the 512-pixel master in Organization schema and as the site icon.
 * Header, footer and schema must not drift back to a media-library custom
 * logo, because that previously allowed different parts of the site to
 * keep different (and oversized) logos.
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
	96  => 'logo-96.webp',
	192 => 'logo-192.webp',
	512 => 'logo.webp',
);
foreach ( $expected as $size => $file ) {
	$logo = sa_brand_logo( $size );
	sa_brand_logo_assert( SA_CHILD_URI . 'assets/img/' . $file === $logo['url'], "{$file}: helper URL" );
	sa_brand_logo_assert( $size === $logo['width'] && $size === $logo['height'], "{$file}: helper dimensions" );

	$path  = $theme_dir . '/assets/img/' . $file;
	$image = file_exists( $path ) ? getimagesize( $path ) : false;
	sa_brand_logo_assert( false !== $image, "{$file}: file exists and is a valid image" );
	sa_brand_logo_assert( $size === $image[0] && $size === $image[1], "{$file}: actual dimensions are {$size}x{$size}" );
}

sa_brand_logo_assert( sa_brand_logo_url( 999 ) === sa_brand_logo_url( 512 ), 'unknown logo size falls back to the 512 master' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 32, 0 ) === sa_brand_logo_url( 96 ), 'small site icon uses the 96 variant' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 180, 0 ) === sa_brand_logo_url( 192 ), 'touch site icon uses the 192 variant' );
sa_brand_logo_assert( sa_brand_site_icon_url( '', 512, 0 ) === sa_brand_logo_url( 512 ), 'large site icon uses the 512 master' );

$seo    = (string) file_get_contents( $theme_dir . '/inc/seo.php' );
$header = (string) file_get_contents( $theme_dir . '/header.php' );
$footer = (string) file_get_contents( $theme_dir . '/footer.php' );
$schema = (string) file_get_contents( $theme_dir . '/inc/schema.php' );
$css    = (string) file_get_contents( $theme_dir . '/assets/css/child.css' );

sa_brand_logo_assert( false !== strpos( $header, 'sa_brand_logo_url( 192 )' ), 'header uses the bundled 192 logo' );
sa_brand_logo_assert( false !== strpos( $header, 'srcset=' ), 'header exposes responsive logo variants' );
sa_brand_logo_assert( false !== strpos( $footer, 'sa_brand_logo_url( 96 )' ), 'footer uses the bundled 96 logo' );
sa_brand_logo_assert( false !== strpos( $schema, 'sa_brand_logo( 512 )' ), 'Organization schema uses the bundled 512 logo' );
sa_brand_logo_assert( false !== strpos( $seo, "'site_icon_url', 'sa_brand_site_icon_url'" ), 'site icon URL filter uses the bundled logo' );
sa_brand_logo_assert( false !== strpos( $seo, 'sa_brand_site_icon_fallback' ), 'site icon fallback exists when WordPress has no stored icon' );

foreach ( array( 'header' => $header, 'footer' => $footer, 'schema' => $schema ) as $label => $source ) {
	sa_brand_logo_assert( false === strpos( $source, "get_theme_mod( 'custom_logo' )" ), "{$label}: media-library custom logo must not override the official logo" );
	sa_brand_logo_assert( false === strpos( $source, 'has_custom_logo()' ), "{$label}: custom-logo branch must not override the official logo" );
}

sa_brand_logo_assert( false !== strpos( $css, 'object-fit: cover;' ), 'logo frame displays the square master without distortion' );
sa_brand_logo_assert( false !== strpos( $css, 'background: #050505;' ), 'logo frame uses the new black brand background' );

echo "PASS: official brand logo is bundled and used by header, footer, schema and site icon\n";
