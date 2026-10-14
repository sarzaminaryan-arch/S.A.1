<?php
/**
 * Standalone checks for data/ready-guides.php (camping/survival guides, v2.13.0).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-ready-guides.php
 *
 * The file is generated from docs/camping-education/articles by
 * tools/content-lint/build_theme_data.py; these assertions make sure a bad
 * regeneration (Markdown leftovers, missing FAQ/sources, foreign links, broken
 * slugs) never ships inside a theme release. No WordPress install is needed.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}

function sa_guides_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$guides = include SA_CHILD_DIR . 'data/ready-guides.php';
sa_guides_expect( is_array( $guides ) && count( $guides ) >= 37, 'ready-guides.php must return at least 37 records' );

$attractions = include SA_CHILD_DIR . 'data/ready-articles.php';
sa_guides_expect( is_array( $attractions ) && ! array_intersect_key( $guides, $attractions ), 'guide keys must not collide with attraction keys' );

$required = array( 'title', 'type', 'slug', 'excerpt', 'english', 'kind', 'category', 'tags', 'meta', 'faq', 'content' );
$slugs    = array();
foreach ( $guides as $key => $g ) {
	foreach ( $required as $k ) {
		sa_guides_expect( array_key_exists( $k, $g ), "{$key}: missing key {$k}" );
	}
	sa_guides_expect( 'post' === $g['type'], "{$key}: type must be post" );
	sa_guides_expect( $key === $g['slug'] && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $g['slug'] ), "{$key}: slug must equal the key and be kebab-case" );
	sa_guides_expect( ! isset( $slugs[ $g['slug'] ] ), "{$key}: duplicate slug" );
	$slugs[ $g['slug'] ] = true;
	sa_guides_expect( '' !== trim( $g['title'] ) && '' !== trim( $g['excerpt'] ), "{$key}: title and excerpt required" );
	sa_guides_expect( isset( $g['category']['camping-survival'] ), "{$key}: category camping-survival required" );
	sa_guides_expect( is_array( $g['tags'] ) && count( $g['tags'] ) >= 1, "{$key}: at least one tag" );
	foreach ( array( 'sa_seo_title', 'sa_seo_description', 'sa_focus_keyword', 'sa_byline', 'sa_sources' ) as $mk ) {
		sa_guides_expect( isset( $g['meta'][ $mk ] ) && '' !== trim( (string) $g['meta'][ $mk ] ), "{$key}: meta {$mk} required" );
	}
	sa_guides_expect( mb_strlen( $g['meta']['sa_seo_title'] ) <= 70, "{$key}: SEO title longer than 70 chars" );
	sa_guides_expect( mb_strlen( $g['meta']['sa_seo_description'] ) <= 170, "{$key}: meta description longer than 170 chars" );
	sa_guides_expect( is_array( $g['faq'] ) && count( $g['faq'] ) >= 3, "{$key}: at least 3 FAQ rows" );
	foreach ( $g['faq'] as $row ) {
		sa_guides_expect( ! empty( $row['q'] ) && ! empty( $row['a'] ), "{$key}: FAQ row needs q and a" );
	}

	$c = (string) $g['content'];
	sa_guides_expect( mb_strlen( wp_strip_all_tags_shim( $c ) ) >= 1500, "{$key}: body shorter than 1500 characters" );
	foreach ( array( '**', '](', "\n## ", "\n# ", '```', '{{', '<!--', '<script', '<iframe', 'style=' ) as $bad ) {
		sa_guides_expect( false === strpos( $c, $bad ), "{$key}: forbidden fragment {$bad} in content" );
	}
	sa_guides_expect( false !== strpos( $c, 'sa-callout--answer' ), "{$key}: quick-answer callout missing" );
	sa_guides_expect( false !== strpos( $c, 'sa-callout--warning' ), "{$key}: safety warning callout missing" );
	sa_guides_expect( false !== strpos( $c, '<table' ), "{$key}: decision table missing" );
	sa_guides_expect( preg_match( '/<h2>/', $c ) >= 1, "{$key}: no h2 sections" );
	sa_guides_expect( false === stripos( $c, '<h1' ), "{$key}: h1 inside content" );
	sa_guides_expect( false === strpos( $c, 'سوالات متداول</h2>' ) && false === strpos( $c, '<h2>منابع</h2>' ), "{$key}: FAQ/sources must live in meta, not in the body" );

	preg_match_all( '/href="([^"]+)"/', $c, $m );
	$internal = 0;
	foreach ( $m[1] as $href ) {
		if ( preg_match( '#^https?://#i', $href ) ) {
			sa_guides_expect( false, "{$key}: absolute link in body ({$href}); body links must be relative" );
		}
		sa_guides_expect( '/' === substr( $href, 0, 1 ), "{$key}: non-root-relative link {$href}" );
		$path = trim( strtok( $href, '#?' ), '/' );
		if ( in_array( $path, array( 'editorial-policy', 'contact', 'category/camping-survival', '' ), true ) ) {
			continue;
		}
		sa_guides_expect( isset( $guides[ $path ] ), "{$key}: link to unknown guide /{$path}/" );
		$internal++;
	}
	sa_guides_expect( $internal >= 2, "{$key}: fewer than 2 links to sibling guides" );

	// v2.13.2 — bundled featured image: file inside the theme, alt text with the focus keyword.
	if ( ! empty( $g['cover'] ) ) {
		$cover = $g['cover'];
		sa_guides_expect( is_array( $cover ) && ! empty( $cover['file'] ) && ! empty( $cover['alt'] ), "{$key}: cover needs file and alt" );
		sa_guides_expect( preg_match( '#^guides/[a-z0-9-]+\.(webp|jpe?g|png)$#', $cover['file'] ) && 'guides/' . $g['slug'] . '.' . pathinfo( $cover['file'], PATHINFO_EXTENSION ) === $cover['file'], "{$key}: cover file must be guides/<slug>.<ext>" );
		$abs = SA_CHILD_DIR . 'assets/img/' . $cover['file'];
		sa_guides_expect( is_file( $abs ) && filesize( $abs ) > 10 * 1024, "{$key}: cover file missing or too small ({$cover['file']})" );
		sa_guides_expect( filesize( $abs ) <= 400 * 1024, "{$key}: cover file larger than 400 KB — optimise before bundling" );
		sa_guides_expect( 'webp' !== pathinfo( $abs, PATHINFO_EXTENSION ) || 'WEBP' === substr( (string) file_get_contents( $abs, false, null, 8, 4 ), 0, 4 ), "{$key}: cover is not a valid WebP" );
		sa_guides_expect( false !== mb_strpos( $cover['alt'], (string) $g['meta']['sa_focus_keyword'] ), "{$key}: cover alt must contain the focus keyword" );
		sa_guides_expect( mb_strlen( $cover['alt'] ) <= 160, "{$key}: cover alt longer than 160 chars" );
		$covers = isset( $covers ) ? $covers + 1 : 1;
	}
}
sa_guides_expect( isset( $covers ) && $covers >= 35, 'the 35 non-migrated cover records must keep their bundled covers' );

$lighting = $guides['camping-lighting-guide'];
sa_guides_expect( ! empty( $lighting['remove_featured_image'] ), 'lighting guide explicitly opts out of its featured image' );
sa_guides_expect( empty( $lighting['cover'] ), 'lighting guide does not carry a featured-cover record' );
sa_guides_expect( 3 === substr_count( (string) $lighting['content'], '<figure class="sa-guide-topic-card">' ), 'lighting guide has exactly three in-context topic cards' );
sa_guides_expect( false === strpos( (string) $lighting['content'], 'camping-lighting-guide.webp' ), 'retired lighting infographic is not referenced in the imported body' );
foreach ( array( 'headlamp', 'flashlight', 'lantern' ) as $tool ) {
	$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/camping-lighting-' . $tool . '.webp';
	sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "lighting {$tool} card asset exists and is compact" );
	sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "lighting {$tool} card asset is a valid WebP" );
}

$water = $guides['portable-water-filter-review'];
sa_guides_expect( ! empty( $water['remove_featured_image'] ), 'water-filter guide explicitly opts out of its featured image' );
sa_guides_expect( empty( $water['cover'] ), 'water-filter guide does not carry a featured-cover record' );
sa_guides_expect( 4 === substr_count( (string) $water['content'], '<figure class="sa-guide-topic-card">' ), 'water-filter guide has four in-context topic cards' );
sa_guides_expect( false === strpos( (string) $water['content'], 'portable-water-filter-review.webp' ), 'retired water-filter infographic is not referenced in the imported body' );
foreach ( array( 'straw', 'pump', 'squeeze', 'uv' ) as $tool ) {
	$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/portable-water-filter-' . $tool . '.webp';
	sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "water-filter {$tool} card asset exists and is compact" );
	sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "water-filter {$tool} card asset is a valid WebP" );
}

/**
 * Minimal strip-tags shim (no WordPress here).
 *
 * @param string $html HTML.
 * @return string
 */
function wp_strip_all_tags_shim( $html ) {
	return trim( preg_replace( '/\s+/u', ' ', strip_tags( $html ) ) );
}

echo 'PASS: ready-guides data (' . count( $guides ) . ' guides, ' . (int) $covers . " bundled covers, category camping-survival)\n";
