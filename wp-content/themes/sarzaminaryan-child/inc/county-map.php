<?php
/**
 * County map block — the in-page map of the «کجاست؟» section (2.22.87).
 *
 * Owner order (template analysis, 2026-11-16): the county page gets a real map
 * inside the body's map section, rendered BY THE THEME — never baked into
 * post_content — so switching providers never touches 485 articles.
 *
 * How it works:
 *  - `sa_county_map_data()` resolves the coordinates (canonical lat/lng first,
 *    numeric legacy metas second, display-format legacy like «۳۱٫۳۲۷۵ شمالی»
 *    third) and validates them against Iran's bounding box. No valid
 *    coordinates → no map at all (the «no data = no block» rule).
 *  - `sa_county_map_block()` renders an original, clearly schematic SVG preview
 *    (not a navigable or provider map), plus the always-visible خرجی links
 *    (نشان · بلد · گوگل‌مپ) and a <noscript> fallback. On explicit activation,
 *    assets/js/county-map.js can swap the preview for the interactive OSM/Google
 *    iframe. The SVG remains visible without WebGL and makes no tile requests.
 *  - `sa_county_map_content_filter()` (the_content, priority 20 — after the
 *    reader-request strips at 8/9 and the citations stash at 13) injects the
 *    block after the first paragraph of the `<!-- sa:sec:map -->` section.
 *
 * Provider: option `sa_map_provider` — 'osm' (default, keyless, official
 * embed), 'google' (embed). 'neshan' behaves as 'osm' until a web key exists
 * in option `sa_neshan_map_key`. The three خرجی link formats are filterable
 * via `sa_county_map_links` so a provider change is one option, not a release.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse one coordinate from any stored format into a float.
 *
 * Handles plain numbers ('31.3275'), Persian digits ('۳۱٫۳۲۷۵'), direction
 * suffixes («شمالی/جنوبی/شرقی/غربی», N/S/E/W) and degree signs.
 *
 * @param string $raw Raw meta value.
 * @return float|null Null when no number is readable.
 */
function sa_geo_parse_coord( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}
	// Persian digits + decimal/thousands separators → ASCII, then keep the first number.
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٫', '٬', '°' );
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '', '' );
	$raw = str_replace( $fa, $en, $raw );
	if ( ! preg_match( '/(-?\d+(?:\.\d+)?)/', $raw, $m ) ) {
		return null;
	}
	$value = (float) $m[1];
	if ( preg_match( '/جنوبی|غربی|[SWsw]/u', $raw ) && $value > 0 ) {
		$value = -$value;
	}
	return $value;
}

/**
 * Resolve + validate the county map data (center-city pin).
 *
 * @param int $post_id County post ID.
 * @return array|null {lat, lng, zoom, label, center} or null when unusable.
 */
function sa_county_map_data( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post || 'city' !== $post->post_type ) {
		return null;
	}

	$lat     = null;
	$lng     = null;
	$profile = function_exists( 'sa_county_profile_data' ) ? sa_county_profile_data( $post_id ) : array();

	// 1) Canonical profile keys (already float-validated by the data layer).
	if ( ! empty( $profile['lat'] ) && ! empty( $profile['lng'] ) ) {
		$lat = (float) $profile['lat'];
		$lng = (float) $profile['lng'];
	}
	// 2) Numeric legacy metas via the shared helper.
	if ( null === $lat && function_exists( 'sa_get_coords' ) ) {
		$c = sa_get_coords( $post_id );
		if ( is_array( $c ) ) {
			$lat = (float) $c[0];
			$lng = (float) $c[1];
		}
	}
	// 3) Display-format legacy («۳۱٫۳۲۷۵ شمالی») — parse, never display raw.
	if ( null === $lat ) {
		$lat = sa_geo_parse_coord( (string) get_post_meta( $post_id, 'sa_city_latitude', true ) );
		$lng = sa_geo_parse_coord( (string) get_post_meta( $post_id, 'sa_city_longitude', true ) );
	}

	// Iran bounding box — anything outside is a data error, not a map.
	if ( null === $lat || null === $lng || $lat < 25 || $lat > 40 || $lng < 44 || $lng > 63.8 ) {
		return null;
	}

	$zoom = (int) get_post_meta( $post_id, 'sa_map_zoom', true );
	if ( $zoom < 8 || $zoom > 13 ) {
		$zoom = 10;
	}

	// Pin label = the center city (what the pin actually marks), county name as fallback.
	$label = isset( $profile['center_fa'] ) ? trim( (string) $profile['center_fa'] ) : '';
	if ( '' === $label && function_exists( 'sa_county_name' ) ) {
		$label = trim( (string) sa_county_name( $post_id, false ) );
	}
	if ( '' === $label ) {
		$label = trim( (string) get_the_title( $post_id ) );
	}

	return array(
		'lat'   => $lat,
		'lng'   => $lng,
		'zoom'  => $zoom,
		'label' => $label,
	);
}

/**
 * The three خرجی deep links (نشان · بلد · گوگل‌مپ), filterable.
 *
 * @param array $map Map data from sa_county_map_data().
 * @return array[] Each row: label, url.
 */
function sa_county_map_links( $map ) {
	$links = array(
		array(
			'label' => 'نشان',
			'url'   => sprintf( 'https://neshan.com/maps/@%s,%s,%sz', $map['lat'], $map['lng'], $map['zoom'] ),
		),
		array(
			'label' => 'بلد',
			'url'   => sprintf( 'https://balad.ir/location?latitude=%s&longitude=%s&zoom=%s', $map['lat'], $map['lng'], $map['zoom'] ),
		),
		array(
			'label' => 'گوگل‌مپ',
			'url'   => sprintf( 'https://www.google.com/maps?q=%s,%s&z=%s', $map['lat'], $map['lng'], $map['zoom'] ),
		),
	);
	return apply_filters( 'sa_county_map_links', $links, $map );
}

/**
 * Render the map block (facade + خرجی links + noscript). Never stored in the DB.
 *
 * @param int $post_id County post ID.
 * @return string '' when there is no valid map data.
 */
function sa_county_map_block( $post_id ) {
	$map = sa_county_map_data( $post_id );
	if ( null === $map ) {
		return '';
	}

	$provider = get_option( 'sa_map_provider', 'osm' );
	if ( ! in_array( $provider, array( 'osm', 'google', 'neshan' ), true ) ) {
		$provider = 'osm';
	}
	if ( 'neshan' === $provider && '' === (string) get_option( 'sa_neshan_map_key', '' ) ) {
		$provider = 'osm'; // Neshan web SDK needs a key; until then the keyless embed.
	}

	$links = sa_county_map_links( $map );
	$coord = $map['lat'] . ', ' . $map['lng'];

	$actions = '<span class="sa-map__actions-label">بازکردن در:</span><ul class="sa-map__actions-list">';
	$nos     = '<ul class="sa-map__noscript-list">';
	foreach ( $links as $link ) {
		$actions .= sprintf(
			'<li><a href="%s" target="_blank" rel="noopener nofollow">%s</a></li>',
			esc_url( $link['url'] ),
			esc_html( $link['label'] )
		);
		$nos .= sprintf(
			'<li><a href="%s" rel="noopener nofollow">%s</a></li>',
			esc_url( $link['url'] ),
			esc_html( $link['label'] )
		);
	}
	$actions .= '</ul>';
	$nos     .= '</ul>';

	$county_full = function_exists( 'sa_county_name' ) ? sa_county_name( $post_id, true ) : get_the_title( $post_id );

	return sprintf(
		'<figure class="sa-map" id="sa-map" dir="ltr">' .
		'<div class="sa-map__facade" role="button" tabindex="0" aria-label="نمای شماتیک موقعیت %5$s؛ برای بارگذاری نقشهٔ تعاملی انتخاب کنید" data-provider="%1$s" data-lat="%2$s" data-lng="%3$s" data-zoom="%4$s" data-label="%5$s">' .
		'<svg class="sa-map__schematic" viewBox="0 0 960 480" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' .
		'<rect width="960" height="480" rx="22" fill="#eaf2e7"/>' .
		'<path d="M-30 90 C130 20 180 180 330 110 S540 20 680 110 870 190 1000 90 M-30 370 C100 300 210 430 350 350 S590 290 700 370 880 440 1000 330" fill="none" stroke="#c6dfd2" stroke-width="32"/>' .
		'<path d="M-10 260 C120 200 230 280 350 220 S580 140 720 230 860 280 980 210" fill="none" stroke="#79c2d0" stroke-width="18" stroke-linecap="round" opacity=".85"/>' .
		'<path d="M70 10 L230 490 M360 -20 L430 500 M660 -20 L590 500 M870 -20 L740 500 M-20 160 L980 310 M-20 430 L980 70" fill="none" stroke="#fff" stroke-width="15" stroke-linecap="round"/>' .
		'<path d="M70 10 L230 490 M360 -20 L430 500 M660 -20 L590 500 M870 -20 L740 500 M-20 160 L980 310 M-20 430 L980 70" fill="none" stroke="#d5b77b" stroke-width="3" stroke-dasharray="12 12" stroke-linecap="round"/>' .
		'<circle cx="480" cy="240" r="46" fill="#123f73" opacity=".13"/><circle cx="480" cy="240" r="23" fill="#fff"/><circle cx="480" cy="240" r="14" fill="#dc2626" stroke="#fff" stroke-width="5"/>' .
		'</svg>' .
		'<span class="sa-map__pin" aria-hidden="true">%6$s</span>' .
		'<span class="sa-map__placeholder">نمای شماتیک موقعیت %5$s</span>' .
		'<span class="sa-map__load">بارگذاری نقشهٔ تعاملی</span>' .
		'</div>' .
		'<noscript>%7$s</noscript>' .
		'<figcaption class="sa-map__caption" dir="rtl">موقعیت شهر %5$s، مرکز %8$s <bdi dir="ltr">%9$s</bdi></figcaption>' .
		'<div class="sa-map__actions" dir="rtl">%10$s</div>' .
		'</figure>',
		esc_attr( $provider ),
		esc_attr( (string) $map['lat'] ),
		esc_attr( (string) $map['lng'] ),
		esc_attr( (string) $map['zoom'] ),
		esc_attr( $map['label'] ),
		'<svg viewBox="0 0 24 24" width="34" height="34" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2C8.1 2 5 5.1 5 9c0 5.2 7 13 7 13s7-7.8 7-13c0-3.9-3.1-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>',
		$nos,
		esc_html( $county_full ),
		esc_html( $coord ),
		$actions
	);
}

/**
 * Inject the map block after the first paragraph of the map section.
 *
 * Runs late (priority 20) so the request-block strip (8/9), TOC (11) and the
 * citations stash (13) have already done their work. The block is render-only:
 * `get` on the raw post_content never sees it.
 *
 * @param string $content Post content.
 * @return string
 */
function sa_county_map_content_filter( $content ) {
	if ( ! is_singular( 'city' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! empty( $GLOBALS['sa_county_map_injected'] ) ) {
		return $content;
	}

	$open = '<!-- sa:sec:map -->';
	$pos  = strpos( $content, $open );
	if ( false === $pos ) {
		return $content;
	}

	$block = sa_county_map_block( get_the_ID() );
	if ( '' === $block ) {
		return $content;
	}

	$close = strpos( $content, '<!-- /sa:sec:map -->', $pos );
	$p_end = strpos( $content, '</p>', $pos );
	if ( false !== $p_end && ( false === $close || $p_end < $close ) ) {
		$at = $p_end + strlen( '</p>' );
	} else {
		$at = $pos + strlen( $open );
	}

	$GLOBALS['sa_county_map_injected'] = true;
	$GLOBALS['sa_county_map_printed']   = true;

	return substr( $content, 0, $at ) . "\n\n" . $block . "\n\n" . substr( $content, $at );
}
add_filter( 'the_content', 'sa_county_map_content_filter', 20, 1 );

/**
 * Print the facade loader only on pages that actually carry the map block.
 *
 * @return void
 */
function sa_county_map_footer_script() {
	if ( ! is_singular( 'city' ) || empty( $GLOBALS['sa_county_map_printed'] ) ) {
		return;
	}
	$url = trailingslashit( get_stylesheet_directory_uri() ) . 'assets/js/county-map.js';
	$ver = function_exists( 'sa_child_asset_version' ) ? sa_child_asset_version( 'assets/js/county-map.js' ) : SA_CHILD_VERSION;
	echo '<script src="' . esc_url( $url ) . '?v=' . esc_attr( $ver ) . '" defer></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
}
add_action( 'wp_footer', 'sa_county_map_footer_script' );
