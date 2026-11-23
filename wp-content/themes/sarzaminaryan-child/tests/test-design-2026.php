<?php
/**
 * Guard: the 2026 design refresh stays complete, offline-safe and reversible.
 *
 * Runs without WordPress (plain PHP, like the other theme guards) because the
 * release workflow executes it before packaging a ZIP:
 *
 *   php wp-content/themes/sarzaminaryan-child/tests/test-design-2026.php
 *
 * Covered:
 *   1. the compiled stylesheet, the script, the modal and the palette layer ship;
 *   2. nothing in the theme depends on the Tailwind CDN or Google Fonts;
 *   3. every refreshed template keeps the `sa_2026_render_legacy()` safety switch
 *      and the pre-refresh templates really exist in `legacy/`;
 *   4. owner rules from the briefs: eight attractions per county, deduplicated
 *      neighbour chips, the treasure hub filters and the POI codes.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' ); // Standalone run for the release gate.
}

define( 'SA_DESIGN_DIR', dirname( __DIR__ ) . '/' );

$failures = 0;

/**
 * Assert one condition.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @return void
 */
function sa_design_assert( $condition, $message ) {
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
function sa_design_file( $relative ) {
	$path = SA_DESIGN_DIR . $relative;
	return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
}

/* 1 — the shipped design layer ------------------------------------------------ */
$css = sa_design_file( 'assets/css/sa-2026.css' );
sa_design_assert( '' !== $css, 'compiled stylesheet exists' );
sa_design_assert( strlen( $css ) > 20000, 'compiled stylesheet is not an empty stub (' . strlen( $css ) . ' bytes)' );

foreach ( array( '--sa-brand', '--sa-accent', '--sa-canvas', '--sa-ink' ) as $token ) {
	sa_design_assert( false !== strpos( $css, $token ), "stylesheet defines the {$token} token" );
}
foreach ( array( 'sa-palette--home', 'sa-palette--treasure', 'sa-2026-prose', 'sa-pagination', 'sa-hf-brand' ) as $component ) {
	sa_design_assert( false !== strpos( $css, $component ), "stylesheet ships the {$component} component" );
}

$js = sa_design_file( 'assets/js/sa-2026.js' );
sa_design_assert( '' !== $js, 'interaction script exists' );
foreach ( array( 'data-sa-experience-modal', 'data-sa-search-input', 'data-sa-county-search', 'data-sa-attr-filter', 'data-sa-auto-submit' ) as $hook ) {
	sa_design_assert( false !== strpos( $js, $hook ), "script wires {$hook}" );
}

sa_design_assert( '' !== sa_design_file( 'template-parts/2026/experience-modal.php' ), 'shared experience dialog exists' );
sa_design_assert( '' !== sa_design_file( 'inc/design-2026.php' ), 'palette/data layer exists' );

/* 2 — no third-party runtime dependency -------------------------------------- */
$theme_php = array(
	'header.php', 'footer.php', 'front-page.php', 'page.php', 'single.php',
	'single-city.php', 'single-province.php', 'single-attraction.php',
	'archive-attraction.php', 'inc/design-2026.php',
);
foreach ( $theme_php as $relative ) {
	$source = sa_design_file( $relative );
	sa_design_assert( false === strpos( $source, 'cdn.tailwindcss.com' ), "{$relative}: no Tailwind CDN" );
	sa_design_assert( false === strpos( $source, 'fonts.googleapis.com' ), "{$relative}: no Google Fonts" );
	sa_design_assert( false === strpos( $source, 'fonts.gstatic.com' ), "{$relative}: no Google Fonts" );
}
sa_design_assert( false === strpos( $css, 'cdn.tailwindcss.com' ), 'stylesheet never references the CDN' );
sa_design_assert( false !== strpos( $css, 'body.sa-palette--home' ), 'palette block is not purged away' );

/* 3 — the rollback switch ---------------------------------------------------- */
$design_layer = sa_design_file( 'inc/design-2026.php' );
sa_design_assert( false !== strpos( $design_layer, 'function sa_2026_design_enabled(' ), 'design switch function exists' );
sa_design_assert( false !== strpos( $design_layer, 'function sa_2026_render_legacy(' ), 'legacy renderer exists' );
sa_design_assert( false !== strpos( $design_layer, "'SA_2026_DESIGN'" ) || false !== strpos( $design_layer, 'SA_2026_DESIGN' ), 'wp-config constant honoured' );
sa_design_assert( false !== strpos( $design_layer, "'sa_2026_design'" ), 'option switch honoured' );
sa_design_assert( false !== strpos( $design_layer, "'sa_2026_design_enabled'" ), 'filter switch honoured' );
sa_design_assert( false !== strpos( $design_layer, 'if ( ! sa_2026_design_enabled() ) {' ), '2026 assets are skipped when the design is off' );

foreach ( array( 'header', 'footer', 'front-page', 'page', 'single-city', 'single-province', 'single-attraction', 'archive-attraction' ) as $template ) {
	sa_design_assert( '' !== sa_design_file( 'legacy/' . $template . '.php' ), "legacy/{$template}.php ships for the rollback" );
}
foreach ( array( 'header.php', 'footer.php', 'front-page.php', 'page.php', 'single-city.php', 'single-province.php', 'single-attraction.php', 'archive-attraction.php' ) as $template ) {
	sa_design_assert(
		false !== strpos( sa_design_file( $template ), 'sa_2026_render_legacy' ),
		"{$template}: keeps the sa_2026_render_legacy() safety switch"
	);
}

/* 4 — owner rules from the three briefs -------------------------------------- */
$city = sa_design_file( 'single-city.php' );
sa_design_assert( false !== strpos( $city, 'sa_county_profile_attractions( $sa_id, 8 )' ), 'county page shows eight featured attractions' );
sa_design_assert( false !== strpos( $city, '$sa_nb_seen' ), 'county neighbours are de-duplicated' );
sa_design_assert( false !== strpos( $city, 'مبنای جمعیت' ), 'county page states the census basis' );
sa_design_assert( false !== strpos( $city, 'data-sa-attr-filter' ), 'attraction filter tabs present' );

$hub = sa_design_file( 'archive-attraction.php' );
sa_design_assert( false !== strpos( $hub, 'گنجینه دیدنی‌های ایران' ), 'treasure hub carries the owner-approved name' );
foreach ( array( 'sa_2026_treasure_url', 'sa_2026_treasure_types', 'sa_2026_treasure_provinces', 'sa_2026_treasure_filter_url', 'province_tax', 'sa_county', 'attraction_type' ) as $needle ) {
	sa_design_assert( false !== strpos( $hub, $needle ), "treasure hub uses {$needle}" );
}

$attraction = sa_design_file( 'single-attraction.php' );
sa_design_assert( false !== strpos( $attraction, 'sa_2026_poi_code' ), 'attraction page prints the POI code' );
sa_design_assert( false !== strpos( $attraction, 'گنجینه دیدنی‌های ایران' ), 'attraction breadcrumb uses the section name' );

$nav = sa_design_file( 'inc/design-2026.php' );
sa_design_assert( false !== strpos( $nav, 'گنجینه دیدنی‌ها' ), 'primary navigation names the treasure section' );

$functions = sa_design_file( 'functions.php' );
sa_design_assert( false !== strpos( $functions, "'inc/design-2026.php'" ), 'functions.php loads the 2026 layer' );

// Exercise the actual formatting path; source-text checks did not catch cast-to-zero.
function add_action() {}
function add_filter() {}
function get_theme_mod( $key, $default ) { return $default; }
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://sarzaminaryan.ir' . $path;
	}
}
require SA_DESIGN_DIR . 'inc/helpers.php';
require SA_DESIGN_DIR . 'inc/design-2026.php';
foreach ( array( '۱٬۸۶۹٬۰۰۱', '١٨٦٩٠٠١', '1,869,001', 1869001 ) as $input ) {
	$label = sa_2026_census_label( $input, '۱۳۹۵' );
	sa_design_assert( '۱٬۸۶۹٬۰۰۰' === $label['number'] && '۱۳۹۵' === $label['year'], 'localized census number and ungrouped year' );
}
foreach ( array( array( '', '۱۳۹۵' ), array( '۰', '۱۳۹۵' ), array( '۱٬۲', '۱۳۹۵' ), array( '120', '' ) ) as $invalid ) {
	sa_design_assert( null === sa_2026_census_label( $invalid[0], $invalid[1] ), 'invalid or undated population is omitted' );
}
sa_design_assert( '۳۵۰' === sa_2026_census_label( '350', '1395' )['number'], 'small counts do not round to zero' );

/* 5 — relation link unwrapping and province heading/underline guards (v2.24.16) */
$rel_keys = array_fill_keys(
	array_filter(
		array_map(
			'sa_2026_relation_key',
			array(
				'https://sarzaminaryan.ir/province/lorestan/',
				'https://sarzaminaryan.ir/city/dorud/',
				'https://sarzaminaryan.ir/province/kermanshah/',
			)
		)
	),
	true
);
$rel_sample = '<p>در <a href="/province/lorestan/">لرستان</a>، '
	. '<a class="sa-autolink sa-autolink--province" href="https://sarzaminaryan.ir/province/kermanshah">استان کرمانشاه</a> و '
	. '<a data-href="/attraction/x/" href="/city/dorud/"><strong>دورود</strong></a>؛ اما '
	. '<a class="sa-autolink sa-autolink--province">استان همدان</a>، '
	. '<a href="/city/dorud/?preview=1">پیش‌نمایش</a>، '
	. '<a href="/city/dorud/"><img src="d.jpg" alt="دورود"></a> و '
	. '<a href="/attraction/bisheh/">آبشار بیشه</a>.</p>'
	. '<pre><code><a href="/city/dorud/">code</a></code></pre>';
$rel_out = sa_2026_unwrap_relation_links( $rel_sample, $rel_keys );
sa_design_assert( false === strpos( $rel_out, 'href="/province/lorestan/"' ) && false === strpos( $rel_out, '>لرستان</a>' ), 'self relation anchor is completely unwrapped' );
sa_design_assert( false === strpos( $rel_out, 'sa-autolink--province' ) && false !== strpos( $rel_out, 'استان کرمانشاه' ) && false !== strpos( $rel_out, 'استان همدان' ), 'autolinked and dead relation shells unwrap back to plain text' );
sa_design_assert( false !== strpos( $rel_out, '<strong>دورود</strong>؛' ) && false === strpos( $rel_out, 'data-href="/attraction/x/"' ) && false === strpos( $rel_out, '<strong>دورود</strong></a>' ), 'inline markup inside unwrapped relation anchor stays intact' );
sa_design_assert( false !== strpos( $rel_out, '<a href="/city/dorud/?preview=1">پیش‌نمایش</a>' ), 'query-string link stays untouched' );
sa_design_assert( false !== strpos( $rel_out, '<a href="/city/dorud/"><img src="d.jpg" alt="دورود"></a>' ), 'linked image anchor stays untouched' );
sa_design_assert( false !== strpos( $rel_out, '<a href="/attraction/bisheh/">آبشار بیشه</a>' ), 'non-relation attraction link stays untouched' );
sa_design_assert( false !== strpos( $rel_out, '<pre><code><a href="/city/dorud/">code</a></code></pre>' ), 'protected code block stays untouched' );

$province_tpl = sa_design_file( 'single-province.php' );
sa_design_assert( false === strpos( $province_tpl, 'لینک‌سازی افقی' ), 'single-province drops the «لینک‌سازی افقی» label above neighbor chips' );
sa_design_assert( false !== strpos( $province_tpl, 'sa-section-heading' ), 'single-province marks section headings with sa-section-heading' );
sa_design_assert( false === strpos( $province_tpl, 'id="cta-heading" class="sa-section-heading' ), 'dark CTA heading does not receive the light-green pill class' );

$child_css = sa_design_file( 'assets/css/child.css' );
sa_design_assert( false !== strpos( $child_css, 'a[href]:not([href=""]):not(.sa-source-box)' ), 'child.css scopes prose underlines strictly to real links with href' );
sa_design_assert( false !== strpos( $child_css, 'white-space: nowrap' ) && false !== strpos( $child_css, '#E7F5EC' ), 'child.css styles single-line mobile headings with light-green highlight' );

/* 6 — focused card anchor text with stretched-link hit targets (v2.24.17) */
sa_design_assert(
	false !== strpos( $province_tpl, 'class="sa-card-linked' )
		&& false !== strpos( $province_tpl, 'data-sa-relation class="sa-stretched-link"' )
		&& false === strpos( $province_tpl, '<a href="<?php echo esc_url( $sa_county[\'url\'] ); ?>" data-sa-relation' . "\n\t\t\t\t\t\t\tdata-sa-county-card" ),
	'single-province county cards place the anchor strictly on the heading title via sa-stretched-link'
);
sa_design_assert(
	false !== strpos( $city, 'class="sa-card-linked' )
		&& false !== strpos( $city, '<?php echo esc_url( $sa_sibling[\'url\'] ); ?>" data-sa-relation class="sa-stretched-link"' ),
	'single-city sibling county cards place the anchor strictly on the heading title via sa-stretched-link'
);
$front_tpl = sa_design_file( 'front-page.php' );
sa_design_assert(
	false !== strpos( $front_tpl, 'class="sa-card-linked' )
		&& 2 === substr_count( $front_tpl, 'class="sa-stretched-link"' ),
	'front-page province and latest cards place the anchor strictly on card titles via sa-stretched-link'
);
sa_design_assert(
	false !== strpos( $child_css, '.sa-card-linked' )
		&& false !== strpos( $child_css, 'a.sa-stretched-link::after' )
		&& false !== strpos( $child_css, 'inset: 0;' ),
	'child.css defines full-card click target for sa-stretched-link cards'
);

if ( $failures > 0 ) {
	fwrite( STDERR, "FAILED: {$failures} design guard(s)\n" );
	exit( 1 );
}

echo "PASS: 2026 design bundle (styles, script, dialog, palette layer, offline-only, rollback switch, owner rules)\n";
