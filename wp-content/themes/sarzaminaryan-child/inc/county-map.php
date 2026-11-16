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
 *  - `sa_county_map_block()` renders the INTERACTIVE map as the first thing the
 *    reader sees (owner order, 2026-11-16: «بخش مپ رو جوری بذار که نیاز به دکمه
 *    بارگذاری نقشه تعاملی نباشه، صفحه نقشه تعاملی باز باشه همون صفحه اول»).
 *    No facade, no «بارگذاری نقشهٔ تعاملی» button: a lazily loaded provider
 *    iframe (keyless OSM embed by default, Google embed by option) sits inside a
 *    reserved-ratio frame, so there is no layout shift. The frame is a <noscript>
 *    target too: without JavaScript the reader still gets the three خرجی links
 *    (نشان · بلد · گوگل‌مپ) and a plain text position line.
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
 * Render the map block (live interactive map + خرجی links + noscript). Never stored in the DB.
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
	$src   = sa_county_map_embed_url( $map, $provider );

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
		'<div class="sa-map__embed">' .
		'<iframe class="sa-map__frame" src="%1$s" title="نقشهٔ موقعیت %2$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen data-provider="%3$s" data-lat="%4$s" data-lng="%5$s" data-zoom="%6$s"></iframe>' .
		'</div>' .
		'<noscript><p class="sa-map__noscript">موقعیت %2$s: <bdi dir="ltr">%7$s</bdi></p>%8$s</noscript>' .
		'<figcaption class="sa-map__caption" dir="rtl">موقعیت شهر %2$s، مرکز %9$s <bdi dir="ltr">%7$s</bdi></figcaption>' .
		'<div class="sa-map__actions" dir="rtl">%10$s</div>' .
		'</figure>',
		esc_url( $src ),
		esc_html( $map['label'] ),
		esc_attr( $provider ),
		esc_attr( (string) $map['lat'] ),
		esc_attr( (string) $map['lng'] ),
		esc_attr( (string) $map['zoom'] ),
		esc_html( $coord ),
		$nos,
		esc_html( $county_full ),
		$actions
	);
}

/**
 * The provider embed URL for a map — the interactive map itself, built from
 * the coordinates (never stored in post_content). Filterable so switching
 * providers never needs a theme release.
 *
 * @param array  $map      Map data from sa_county_map_data().
 * @param string $provider 'osm' (keyless), 'google' or 'neshan' (as osm).
 * @return string
 */
function sa_county_map_embed_url( $map, $provider = 'osm' ) {
	$lat  = (float) $map['lat'];
	$lng  = (float) $map['lng'];
	$zoom = (int) $map['zoom'];

	if ( 'google' === $provider ) {
		$url = sprintf( 'https://maps.google.com/maps?q=%s,%s&z=%s&output=embed&hl=fa', $lat, $lng, $zoom );
	} else {
		// Keyless official OSM embed: a zoom-sized bbox around the pin.
		$h   = 0.09 * pow( 2, 10 - $zoom );
		$v   = $h * 0.62;
		$url = sprintf(
			'https://www.openstreetmap.org/export/embed.html?bbox=%s,%s,%s,%s&layer=mapnik&marker=%s,%s',
			round( $lng - $h, 5 ),
			round( $lat - $v, 5 ),
			round( $lng + $h, 5 ),
			round( $lat + $v, 5 ),
			$lat,
			$lng
		);
	}

	return (string) apply_filters( 'sa_county_map_embed_url', $url, $map, $provider );
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
