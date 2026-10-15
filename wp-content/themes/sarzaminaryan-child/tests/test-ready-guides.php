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
sa_guides_expect( is_array( $guides ) && count( $guides ) >= 45, 'ready-guides.php must return at least 45 records' );

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
sa_guides_expect( isset( $covers ) && $covers >= 27, 'the 27 remaining bundled-cover records must keep their covers after ten post-scoped migrations' );

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

$stove = $guides['camping-stove-review'];
sa_guides_expect( ! empty( $stove['remove_featured_image'] ), 'camping-stove guide explicitly opts out of its featured image' );
sa_guides_expect( empty( $stove['cover'] ), 'camping-stove guide does not carry a featured-cover record' );
sa_guides_expect( 4 === substr_count( (string) $stove['content'], '<figure class="sa-guide-topic-card">' ), 'camping-stove guide has exactly four in-context topic cards' );
sa_guides_expect( false === strpos( (string) $stove['content'], 'camping-stove-review.webp' ), 'retired camping-stove infographic is not referenced in the imported body' );
foreach ( array( 'canister', 'integrated', 'multifuel', 'picnic' ) as $type ) {
	$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/camping-stove-' . $type . '.webp';
	sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "camping-stove {$type} card asset exists and is compact" );
	sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "camping-stove {$type} card asset is a valid WebP" );
	$dimensions = getimagesize( $image );
	sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1], "camping-stove {$type} card asset is 720x540" );
}

$multi_tool = $guides['multi-tool-knife-review'];
sa_guides_expect( ! empty( $multi_tool['remove_featured_image'] ), 'multi-tool guide opts out of its own featured image' );
sa_guides_expect( empty( $multi_tool['cover'] ), 'multi-tool guide has no bundled featured cover' );
sa_guides_expect( 3 === substr_count( (string) $multi_tool['content'], '<figure class="sa-guide-topic-card">' ), 'multi-tool guide has three in-context topic cards' );
sa_guides_expect( false === strpos( (string) $multi_tool['content'], 'multi-tool-knife-review.webp' ), 'retired multi-tool infographic is not referenced in the imported body' );
sa_guides_expect( ! is_file( SA_CHILD_DIR . 'assets/img/guides/multi-tool-knife-review.webp' ), 'retired multi-tool infographic is not bundled in the theme' );
foreach ( array( 'pocket', 'pliers', 'fixed-blade' ) as $tool ) {
	$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/multi-tool-' . $tool . '.webp';
	sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "multi-tool {$tool} card asset exists and is compact" );
	sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "multi-tool {$tool} card asset is a valid WebP" );
	$dimensions = getimagesize( $image );
	sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1], "multi-tool {$tool} card asset is 720x540" );
}

$gps = $guides['gps-navigation-tools'];
sa_guides_expect( ! empty( $gps['remove_featured_image'] ), 'GPS guide opts out of its own featured image' );
sa_guides_expect( empty( $gps['cover'] ), 'GPS guide has no bundled featured cover' );
sa_guides_expect( 4 === substr_count( (string) $gps['content'], '<figure class="sa-guide-topic-card">' ), 'GPS guide has four in-context topic cards' );
sa_guides_expect( false === strpos( (string) $gps['content'], 'gps-navigation-tools.webp' ), 'retired GPS infographic is not referenced in the imported body' );
sa_guides_expect( ! is_file( SA_CHILD_DIR . 'assets/img/guides/gps-navigation-tools.webp' ), 'retired GPS infographic is not bundled in the theme' );
foreach ( array( 'handheld', 'offline-map-phone', 'paper-map-compass', 'natural-landmarks' ) as $tool ) {
	$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/gps-' . $tool . '.webp';
	sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "GPS {$tool} card asset exists and is compact" );
	sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "GPS {$tool} card asset is a valid WebP" );
	$dimensions = getimagesize( $image );
	sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1], "GPS {$tool} card asset is 720x540" );
}

/* Five later education articles: hide only their own featured image and keep three local topic cards each. */
$recent_guides = array(
	'rope-carabiner-guide' => array( 'rope-types.webp', 'rope-carabiner-locked-carabiner.webp', 'rope-carabiner-utility-cord.webp' ),
	'professional-rescue-kit' => array( 'rescue-kit-first-aid.webp', 'rescue-kit-warmth.webp', 'rescue-kit-signaling.webp' ),
	'diy-water-filter' => array( 'diy-water-filter-demo.webp', 'diy-water-filter-layers.webp', 'diy-water-filter-safe-water.webp' ),
	'diy-can-stove' => array( 'diy-can-stove-unlit.webp', 'diy-can-stove-commercial-stove.webp', 'diy-can-stove-fire-safety.webp' ),
	'rope-alternatives-outdoor' => array( 'rope-alternatives-backpack-strap.webp', 'rope-alternatives-fabric-lashing.webp', 'rope-alternatives-shoelace.webp' ),
);
foreach ( $recent_guides as $slug => $cards ) {
	$guide = $guides[ $slug ];
	sa_guides_expect( ! empty( $guide['remove_featured_image'] ) && empty( $guide['cover'] ), "{$slug}: only its featured-image relation is removed" );
	sa_guides_expect( 3 === substr_count( (string) $guide['content'], '<figure class="sa-guide-topic-card">' ), "{$slug}: exactly three in-context topic cards" );
	sa_guides_expect( false === strpos( (string) $guide['content'], $slug . '.webp' ), "{$slug}: retired infographic is absent from imported content" );
	foreach ( $cards as $card ) {
		$image = SA_CHILD_DIR . 'assets/img/guides/topic-cards/' . $card;
		sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "{$slug}: {$card} exists and is compact" );
		sa_guides_expect( 'WEBP' === substr( (string) file_get_contents( $image, false, null, 8, 4 ), 0, 4 ), "{$slug}: {$card} is a valid WebP" );
		$dimensions = getimagesize( $image );
		sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1], "{$slug}: {$card} is 720x540" );
	}
}

/* Shelter/bedding batch: keep exactly three cards beside the matching numbered steps. */
$shelter_cards = array(
	'build-shelter-branches' => array( 'branch-frame', 'leaf-roof', 'ground-insulation' ),
	'newspaper-insulation-bed' => array( 'waterproof-base', 'crumpled-paper', 'sack-pillow' ),
	'how-to-choose-camping-tent' => array( 'packed-tent', 'floor-space', 'mesh-ventilation' ),
);
foreach ( $shelter_cards as $slug => $topics ) {
	sa_guides_expect( isset( $guides[ $slug ] ), "{$slug}: shelter/bedding guide exists" );
	$content = (string) $guides[ $slug ]['content'];
	sa_guides_expect( 3 === substr_count( $content, '<figure class="sa-guide-topic-card">' ), "{$slug}: exactly three topic cards" );
	foreach ( $topics as $topic ) {
		$file = 'guides/topic-cards/' . $slug . '-' . $topic . '.webp';
		$image = SA_CHILD_DIR . 'assets/img/' . $file;
		sa_guides_expect( 1 === substr_count( $content, $file ), "{$slug}: {$topic} is rendered once" );
		sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "{$slug}: {$topic} asset exists and is compact" );
		$dimensions = getimagesize( $image );
		sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1] && 'image/webp' === $dimensions['mime'], "{$slug}: {$topic} is a 720x540 WebP" );
	}
}

/* Batch 8: every image belongs to its specific rendered step, not a stack at the end. */
$batch_eight = array(
	'sleeping-bag-guide' => array( 2 => 'insulated-pad', 3 => 'bag-shapes', 4 => 'fit-check' ),
	'can-cookware-diy' => array( 1 => 'clean-can', 2 => 'rim-pliers', 3 => 'cold-handle-test' ),
	'diy-bottle-lantern' => array( 1 => 'filled-bottle', 2 => 'inward-headlamp', 4 => 'reflector' ),
);
foreach ( $batch_eight as $slug => $topics ) {
	$content = (string) $guides[ $slug ]['content'];
	sa_guides_expect( 3 === substr_count( $content, '<figure class="sa-guide-topic-card">' ), "{$slug}: exactly three batch-8 cards" );
	preg_match_all( '/<h3 class="sa-step"><span class="sa-step__n">([^<]+)<\/span>.*?(?=<h3 class="sa-step">|<h2>|$)/su', $content, $matches, PREG_SET_ORDER );
	$steps = array();
	foreach ( $matches as $match ) {
	 $steps[ (int) strtr( $match[1], array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5' ) ) ] = $match[0];
	}
	foreach ( $topics as $number => $topic ) {
	 $file = 'guides/topic-cards/' . $slug . '-' . $topic . '.webp';
	 $image = SA_CHILD_DIR . 'assets/img/' . $file;
	 sa_guides_expect( isset( $steps[ $number ] ) && 1 === substr_count( $steps[ $number ], $file ), "{$slug}: {$topic} in its exact step {$number}" );
	 sa_guides_expect( 1 === substr_count( $content, $file ), "{$slug}: {$topic} is rendered once" );
	 sa_guides_expect( is_file( $image ) && filesize( $image ) > 10 * 1024 && filesize( $image ) < 200 * 1024, "{$slug}: {$topic} asset size" );
	 $dimensions = getimagesize( $image );
	 sa_guides_expect( is_array( $dimensions ) && 720 === $dimensions[0] && 540 === $dimensions[1] && 'image/webp' === $dimensions['mime'], "{$slug}: {$topic} is 720x540 WebP" );
	}
}
sa_guides_expect( false !== strpos( $guides['can-cookware-diy']['content'], 'قوطی بسته را هرگز روی آتش نگذار' ), 'closed can must never be heated' );
sa_guides_expect( false !== strpos( $guides['can-cookware-diy']['content'], 'پوشش داخلی یا بیرونی را نسوزان' ), 'can coatings must not be burned off' );
sa_guides_expect( false !== strpos( $guides['diy-bottle-lantern']['content'], 'بطری پرآب را از سقف یا تیرک چادر آویزان نکن' ), 'no heavy bottle on tent roof' );
sa_guides_expect( false !== strpos( $guides['sleeping-bag-guide']['faq'][4]['a'], 'کنار آتش، اجاق یا منبع حرارت مستقیم نگذار' ), 'wet sleeping bag must not be dried by fire' );

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
