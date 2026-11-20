<?php
/**
 * 2026 design refresh — palette, assets and data helpers.
 *
 * The four approved mockups (home / province / county / attraction) are pure
 * Tailwind. Their utilities are compiled once into assets/css/sa-2026.css;
 * this file only chooses the palette for the current request and feeds the
 * templates with real site data.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the 2026 design active on this request?
 *
 * Three independent switches, cheapest first, so the owner can put the previous
 * design back within seconds without reinstalling any ZIP:
 *
 *   1. `define( 'SA_2026_DESIGN', false );` in wp-config.php (works even when
 *      the admin is unreachable because of a fatal error);
 *   2. the `sa_2026_design` option (string "0"/"1" — set from the Customizer,
 *      WP‑CLI or the dashboard);
 *   3. the `sa_2026_design_enabled` filter for one-off overrides.
 *
 * Default: enabled.
 *
 * @return bool
 */
function sa_2026_design_enabled() {
	$enabled = true;

	if ( defined( 'SA_2026_DESIGN' ) ) {
		$enabled = (bool) SA_2026_DESIGN;
	} else {
		$option = get_option( 'sa_2026_design', null );
		if ( null !== $option && '' !== $option ) {
			$enabled = ! in_array( (string) $option, array( '0', 'off', 'false', 'no' ), true );
		}
	}

	return (bool) apply_filters( 'sa_2026_design_enabled', $enabled );
}

/**
 * Render the pre-refresh template when the design is switched off.
 *
 * Every 2026 template starts with
 * `if ( sa_2026_render_legacy( '<name>' ) ) { return; }`, so switching the
 * design off restores the exact 2.22.149 templates shipped in `legacy/`.
 *
 * @param string $name Template name (header, footer, front-page, single-city…).
 * @return bool True when the legacy template was rendered (caller must return).
 */
function sa_2026_render_legacy( $name ) {
	if ( sa_2026_design_enabled() ) {
		return false;
	}

	$file = SA_CHILD_DIR . 'legacy/' . preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $name ) ) . '.php';
	if ( ! is_readable( $file ) ) {
		return false;
	}

	include $file;

	return true;
}

/**
 * Which palette the current request uses.
 *
 * @return string home|treasure|teal
 */
function sa_2026_palette() {
	$palette = 'teal';

	if ( is_front_page() ) {
		$palette = 'home';
	} elseif ( is_singular( 'attraction' ) || is_post_type_archive( 'attraction' ) ) {
		$palette = 'treasure';
	}

	return (string) apply_filters( 'sa_2026_palette', $palette );
}

/**
 * Attach the palette + design-system classes to <body>.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function sa_2026_body_class( $classes ) {
	if ( ! sa_2026_design_enabled() ) {
		return $classes;
	}

	$classes[] = 'sa-2026';
	$classes[] = 'sa-palette--' . sa_2026_palette();

	return $classes;
}
add_filter( 'body_class', 'sa_2026_body_class' );

/**
 * Load the compiled design-system stylesheet and the page interactions.
 */
function sa_2026_assets() {
	if ( ! sa_2026_design_enabled() ) {
		return;
	}

	wp_enqueue_style(
		'sa-2026',
		SA_CHILD_URI . 'assets/css/sa-2026.css',
		array( 'sarzaminaryan-child' ),
		function_exists( 'sa_child_asset_version' ) ? sa_child_asset_version( 'assets/css/sa-2026.css' ) : SA_CHILD_VERSION
	);

	wp_enqueue_script(
		'sa-2026',
		SA_CHILD_URI . 'assets/js/sa-2026.js',
		array(),
		function_exists( 'sa_child_asset_version' ) ? sa_child_asset_version( 'assets/js/sa-2026.js' ) : SA_CHILD_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'sa_2026_assets', 21 );

/**
 * Primary navigation of the new header (same four entries as every mockup).
 *
 * @return array<int,array{label:string,url:string,key:string,active:bool}>
 */
function sa_2026_nav_items() {
	$items = array(
		array(
			'label'  => 'استان‌ها',
			'url'    => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'province' ) : home_url( '/provinces/' ),
			'key'    => 'province',
			'active' => is_post_type_archive( 'province' ) || is_singular( 'province' ),
		),
		array(
			'label'  => 'شهرستان‌ها',
			'url'    => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'city' ) : home_url( '/cities/' ),
			'key'    => 'city',
			'active' => is_post_type_archive( 'city' ) || is_singular( 'city' ),
		),
		array(
			'label'  => 'گنجینه دیدنی‌ها',
			'url'    => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'attraction' ) : home_url( '/attractions/' ),
			'key'    => 'attraction',
			'active' => is_post_type_archive( 'attraction' ) || is_singular( 'attraction' ),
		),
		array(
			'label'  => 'آموزش سفر',
			'url'    => home_url( '/travel-guide/' ),
			'key'    => 'guide',
			'active' => false,
		),
	);

	return (array) apply_filters( 'sa_2026_nav_items', $items );
}

/**
 * Links of the footer "کاوش در دانشنامه" column.
 *
 * @return array<int,array{label:string,url:string}>
 */
function sa_2026_footer_explore() {
	return array(
		array(
			'label' => 'استان‌های ایران',
			'url'   => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'province' ) : home_url( '/provinces/' ),
		),
		array(
			'label' => 'فهرست شهرستان‌ها',
			'url'   => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'city' ) : home_url( '/cities/' ),
		),
		array(
			'label' => 'گنجینه دیدنی‌های ایران',
			'url'   => function_exists( 'sa_archive_url' ) ? sa_archive_url( 'attraction' ) : home_url( '/attractions/' ),
		),
		array(
			'label' => 'آموزش و راهنمای سفر',
			'url'   => home_url( '/travel-guide/' ),
		),
	);
}

/**
 * URL of the public contribution area ("شهر من").
 *
 * @return string
 */
function sa_2026_contrib_url() {
	// v2.23.3: the form module owns the canonical URL (real page first, virtual route
	// otherwise) so this call can never lead to a 404 again.
	if ( function_exists( 'sa_experience_form_url' ) ) {
		return (string) apply_filters( 'sa_2026_contrib_url', sa_experience_form_url() );
	}

	$page = get_page_by_path( 'city-contrib' );
	if ( $page instanceof WP_Post ) {
		return (string) get_permalink( $page );
	}

	return (string) apply_filters( 'sa_2026_contrib_url', home_url( '/city-contrib/' ) );
}

/**
 * Header CTA label/url for the current page ("ثبت تجربه در تبریز" on counties).
 *
 * @return array{label:string,url:string}
 */
function sa_2026_experience_cta() {
	$label = 'ثبت تجربه';

	if ( is_singular( 'city' ) ) {
		$label = 'ثبت تجربه در ' . get_the_title();
	} elseif ( is_singular( 'attraction' ) ) {
		$label = 'ثبت تجربه بازدید';
	} elseif ( is_singular( 'province' ) ) {
		$label = 'ثبت تجربه سفر';
	}

	return array( 'label' => $label, 'url' => sa_2026_contrib_url() );
}

/**
 * Published post count of a type (0 when the type is off).
 *
 * @param string $type Post type.
 * @return int
 */
function sa_2026_count( $type ) {
	$counts = wp_count_posts( $type );
	if ( ! is_object( $counts ) || ! isset( $counts->publish ) ) {
		return 0;
	}

	return (int) $counts->publish;
}

/**
 * Persian digits with thousands separators for numbers shown in the UI.
 *
 * @param int|float|string $value Number.
 * @return string
 */
function sa_2026_num( $value ) {
	if ( function_exists( 'sa_number' ) ) {
		return sa_number( $value );
	}

	return number_format_i18n( (float) $value );
}

/**
 * Cover image URL for a post (falls back to the site landscape).
 *
 * @param int    $post_id Post ID.
 * @param string $size    Registered image size.
 * @return string
 */
function sa_2026_cover( $post_id, $size = 'large' ) {
	$post_id = (int) $post_id;
	$url     = get_the_post_thumbnail_url( $post_id, $size );

	if ( ! $url ) {
		/*
		 * Owner report: every treasure card showed the same placeholder while the
		 * articles already carry photos. Promote the first in-content image before
		 * any placeholder is considered.
		 */
		if ( function_exists( 'sa_2026_content_image' ) ) {
			$found = sa_2026_content_image( $post_id );
			if ( ! empty( $found['url'] ) ) {
				return (string) $found['url'];
			}
		}
	}

	// Keep the pre-existing size fallback: a thumbnail that only exists at full size
	// still beats a stock placeholder.
	if ( ! $url && function_exists( 'sa_cover_image_size' ) ) {
		$url = get_the_post_thumbnail_url( $post_id, 'full' );
	}

	if ( ! $url ) {
		$url = SA_CHILD_URI . 'assets/img/home-iran-landscape.jpg';
	}

	return (string) $url;
}

/**
 * Real photo or an honest gradient tile (never a shared stock image on cards).
 *
 * @param int    $post_id Post ID.
 * @param string $classes Classes of the <img>.
 * @param string $label   Short label printed on the gradient tile.
 * @return string HTML.
 */
function sa_2026_cover_markup( $post_id, $classes = 'w-full h-full object-cover', $label = '' ) {
	$post_id = (int) $post_id;
	$thumb   = get_the_post_thumbnail_url( $post_id, 'large' );
	$found   = ( ! $thumb && function_exists( 'sa_2026_content_image' ) ) ? sa_2026_content_image( $post_id ) : array( 'url' => '' );
	$url     = $thumb ? $thumb : (string) ( isset( $found['url'] ) ? $found['url'] : '' );

	if ( $url ) {
		return '<img src="' . esc_url( $url ) . '" alt="" class="' . esc_attr( $classes ) . '" loading="lazy" decoding="async">';
	}

	return '<span class="w-full h-full flex items-center justify-center bg-brand-forestSoft text-brand-forest text-[11px] font-bold px-3 text-center">'
		. esc_html( $label ? $label : 'بدون عکس' )
		. '</span>';
}

/**
 * Provinces for the home grid, ordered by name, with county counts.
 *
 * @param int $limit Max rows (0 = all).
 * @return array<int,array{id:int,title:string,url:string,cover:string,counties:int}>
 */
function sa_2026_provinces( $limit = 8 ) {
	$posts = get_posts(
		array(
			'post_type'              => 'province',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit > 0 ? $limit : 31,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$out = array();
	foreach ( $posts as $post ) {
		$counties = 0;
		if ( function_exists( 'sa_counties_by_province' ) ) {
			$rows = sa_counties_by_province();
			$slug = $post->post_name;
			if ( isset( $rows[ $slug ] ) && is_array( $rows[ $slug ] ) ) {
				$counties = count( $rows[ $slug ] );
			}
		}

		$out[] = array(
			'id'       => (int) $post->ID,
			'title'    => get_the_title( $post ),
			'url'      => (string) get_permalink( $post ),
			'cover'    => sa_2026_cover( $post->ID, 'medium_large' ),
			'counties' => $counties,
		);
	}

	return $out;
}

/**
 * Published counties of a province (for the province grid and county chips).
 *
 * @param int $province_id Province post ID.
 * @param int $limit       Max rows.
 * @return array<int,array{id:int,title:string,url:string,cover:string,summary:string}>
 */
function sa_2026_province_counties( $province_id, $limit = 24 ) {
	$children = function_exists( 'sa_get_children' ) ? sa_get_children( (int) $province_id, 'city' ) : array();
	$out      = array();

	foreach ( (array) $children as $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			continue;
		}

		$out[] = array(
			'id'      => (int) $post->ID,
			'title'   => get_the_title( $post ),
			'url'     => (string) get_permalink( $post ),
			'cover'   => sa_2026_cover( $post->ID, 'medium' ),
			'summary' => function_exists( 'sa_summary' ) ? sa_summary( $post, 24 ) : '',
		);

		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	return $out;
}

/**
 * Committed content types that feed the "تازه‌ترین به‌روزرسانی‌ها" rail.
 *
 * @param int $limit Max posts.
 * @return WP_Post[]
 */
function sa_2026_latest( $limit = 6 ) {
	return get_posts(
		array(
			'post_type'      => array( 'attraction', 'city', 'province', 'post' ),
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
}

/**
 * Site-wide numbers shown in the hero stat strip.
 *
 * @return array<int,array{value:string,label:string}>
 */
function sa_2026_site_stats() {
	return array(
		array(
			'value' => sa_2026_num( max( 31, sa_2026_count( 'province' ) ) ),
			'label' => 'استان',
		),
		array(
			'value' => sa_2026_num( function_exists( 'sa_counties' ) ? count( sa_counties() ) : sa_2026_count( 'city' ) ),
			'label' => 'شهرستان',
		),
		array(
			'value' => sa_2026_num( sa_2026_count( 'attraction' ) ),
			'label' => 'دیدنی',
		),
		array(
			'value' => sa_2026_num( sa_2026_count( 'travel_route' ) + sa_2026_count( 'post' ) ),
			'label' => 'راهنما و مقاله',
		),
	);
}

/**
 * Province neighbours for the province page (registry order, published only).
 *
 * @param int $province_id Current province.
 * @param int $limit       Max chips.
 * @return array<int,array{id:int,title:string,url:string}>
 */
function sa_2026_province_neighbours( $province_id, $limit = 8 ) {
	$out = array();
	$ids = function_exists( 'sa_meta_ids' ) ? sa_meta_ids( $province_id, 'sa_neighbors' ) : array();

	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			continue;
		}
		$out[] = array(
			'id'    => (int) $id,
			'title' => get_the_title( $post ),
			'url'   => (string) get_permalink( $post ),
		);
		if ( count( $out ) >= $limit ) {
			break;
		}
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * «گنجینه دیدنی‌های ایران» — hub page, filters and POI codes
 *
 * Owner brief (29 آبان ۱۴۰۵): the attractions section is named «گنجینه دیدنی‌های
 * ایران», it has a mother page filtering by province / county / type, and every
 * attraction carries a stable public code such as `POI-TBZ-001`.
 * The public URL base (`/attraction/…`) is NOT renamed yet: 38 attraction URLs
 * are already indexed, so the `/treasure/` shape is served as a 301 alias until
 * the owner signs the migration (see docs/design/2026-11-refresh/OWNER-NOTES.md).
 * ---------------------------------------------------------------------- */

/**
 * Mother-page URL of the treasure section.
 *
 * @return string
 */
function sa_2026_treasure_url() {
	$url = function_exists( 'sa_archive_url' ) ? sa_archive_url( 'attraction' ) : '';
	return $url ? $url : home_url( '/treasure/' );
}

/**
 * Three-letter code of a county, used inside POI codes (`POI-<CODE>-<NNN>`).
 *
 * Known destinations use the code the owner's brief shows (Tabriz → TBZ);
 * everything else falls back to the county's Latin slug consonants so the code
 * stays stable and readable.
 *
 * @param int $city_id County (city CPT) ID.
 * @return string
 */
function sa_2026_poi_city_code( $city_id ) {
	static $cache = array();
	$city_id      = (int) $city_id;
	if ( isset( $cache[ $city_id ] ) ) {
		return $cache[ $city_id ];
	}

	$stored = function_exists( 'sa_meta' ) ? (string) sa_meta( $city_id, 'sa_poi_city_code' ) : '';
	if ( '' !== $stored ) {
		return $cache[ $city_id ] = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $stored ), 0, 4 ) );
	}

	$slug = (string) get_post_field( 'post_name', $city_id );
	$map  = array(
		'tabriz' => 'TBZ', 'tehran' => 'THR', 'isfahan' => 'ISF', 'shiraz' => 'SYZ',
		'mashhad' => 'MHD', 'yazd' => 'YZD', 'kerman' => 'KER', 'rasht' => 'RAS',
		'ahvaz' => 'AWZ', 'kermanshah' => 'KSH', 'hamedan' => 'HMD', 'qom' => 'QOM',
		'khorramabad' => 'KHD', 'urmia' => 'OMH', 'ardabil' => 'ADU', 'bandar-abbas' => 'BND',
		'bushehr' => 'BUZ', 'zahedan' => 'ZAH', 'semnan' => 'SNX', 'qazvin' => 'QZV',
		'zanjan' => 'JWN', 'sanandaj' => 'SDG', 'gorgan' => 'GBT', 'sari' => 'SRY',
		'karaj' => 'KRJ', 'birjand' => 'XBJ', 'bojnord' => 'BJB', 'shahr-e-kord' => 'CQD',
		'asaluyeh' => 'ASA', 'kish' => 'KIH', 'qeshm' => 'GSM', 'kashan' => 'KSN',
		'dorud' => 'DRD', 'tabas' => 'TBS', 'masouleh' => 'MSL',
	);
	$key = str_replace( '_', '-', strtolower( $slug ) );
	while ( '' !== $key && ! isset( $map[ $key ] ) && false !== strpos( $key, '-' ) ) {
		$key = substr( $key, 0, (int) strrpos( $key, '-' ) );
	}
	if ( '' !== $key && isset( $map[ $key ] ) ) {
		return $cache[ $city_id ] = $map[ $key ];
	}

	$letters = strtoupper( preg_replace( '/[^a-z]/i', '', $key ? $key : $slug ) );
	$code    = substr( preg_replace( '/[AEIOU]/', '', $letters ), 0, 3 );
	if ( strlen( $code ) < 3 ) {
		$code = substr( $letters, 0, 3 );
	}
	if ( strlen( $code ) < 3 ) {
		$code = str_pad( $code, 3, 'X' );
	}

	return $cache[ $city_id ] = $code;
}

/**
 * Public, stable code of one attraction: `POI-<COUNTY>-<NNN>`.
 *
 * Accepts the stored `sa_poi_code` meta when the editorial team pins a code;
 * otherwise the code is derived from the county and the attraction's position
 * inside that county (ordered by post ID, so it never changes on re-save).
 *
 * @param int $post_id Attraction ID.
 * @return string
 */
function sa_2026_poi_code( $post_id ) {
	$post_id = (int) $post_id;
	$stored  = function_exists( 'sa_meta' ) ? (string) sa_meta( $post_id, 'sa_poi_code' ) : '';
	if ( '' !== $stored ) {
		return $stored;
	}

	if ( ! function_exists( 'sa_get_parent' ) ) {
		return 'POI-IRN-' . str_pad( (string) $post_id, 3, '0', STR_PAD_LEFT );
	}

	$city = sa_get_parent( $post_id, 'city' );
	if ( ! $city instanceof WP_Post ) {
		return 'POI-IRN-' . str_pad( (string) $post_id, 3, '0', STR_PAD_LEFT );
	}

	static $positions = array();
	$city_id          = (int) $city->ID;
	if ( ! isset( $positions[ $city_id ] ) ) {
		$ids                       = get_posts(
			array(
				'post_type'      => 'attraction',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'sa_city_id',
						'value' => $city_id,
					),
				),
			)
		);
		$positions[ $city_id ] = array_flip( array_map( 'intval', (array) $ids ) );
	}

	$index = isset( $positions[ $city_id ][ $post_id ] ) ? $positions[ $city_id ][ $post_id ] + 1 : 1;

	return 'POI-' . sa_2026_poi_city_code( $city_id ) . '-' . str_pad( (string) $index, 3, '0', STR_PAD_LEFT );
}

/**
 * Attraction-type terms shown as filter chips on the treasure hub.
 *
 * @return array<int,array{slug:string,label:string,count:int}>
 */
function sa_2026_treasure_types() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}

	$out   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => 'attraction_type',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$out[] = array(
				'slug'  => (string) $term->slug,
				'label' => (string) $term->name,
				'count' => (int) $term->count,
			);
		}
	}

	return $out;
}

/**
 * Province options of the treasure hub filter.
 *
 * @return array<int,array{slug:string,label:string,count:int}>
 */
function sa_2026_treasure_provinces() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}

	$out   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => 'province_tax',
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);
	if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$out[] = array(
				'slug'  => (string) $term->slug,
				'label' => (string) $term->name,
				'count' => (int) $term->count,
			);
		}
	}

	return $out;
}

/**
 * Currently selected filters on the treasure hub.
 *
 * @return array{province:string,county:string,type:string}
 */
function sa_2026_treasure_filters() {
	$get = function ( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	};

	return array(
		'province' => sanitize_title( $get( 'province_tax' ) ),
		'county'   => sanitize_title( $get( 'sa_county' ) ),
		'type'     => sanitize_title( $get( 'attraction_type' ) ),
	);
}

/**
 * URL of the treasure hub with the given filters applied.
 *
 * @param array<string,string> $args Filter values (empty value removes it).
 * @return string
 */
function sa_2026_treasure_filter_url( array $args = array() ) {
	$base   = sa_2026_treasure_url();
	$keep   = array_filter(
		sa_2026_treasure_filters(),
		static function ( $value ) {
			return '' !== $value;
		}
	);
	$merged = array_merge( $keep, $args );
	$merged = array_filter(
		$merged,
		static function ( $value ) {
			return '' !== $value && null !== $value;
		}
	);

	return $merged ? add_query_arg( $merged, $base ) : $base;
}

/**
 * County filter of the treasure hub (`?sa_county=<city slug>`).
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function sa_2026_treasure_filter_query( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'attraction' ) ) {
		return;
	}

	$filters = sa_2026_treasure_filters();
	if ( '' === $filters['county'] ) {
		return;
	}

	$city = get_page_by_path( $filters['county'], OBJECT, 'city' );
	if ( ! $city instanceof WP_Post ) {
		return;
	}

	$query->set(
		'meta_query',
		array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => 'sa_city_id',
				'value' => (int) $city->ID,
			),
		)
	);
	$query->set( 'sa_treasure_county', (int) $city->ID );
}
add_action( 'pre_get_posts', 'sa_2026_treasure_filter_query' );

/**
 * `/treasure/…` → `/attraction/…` 301 alias.
 *
 * The owner's brief names the section «گنجینه دیدنی‌های ایران» with a
 * `/treasure/` URL shape. The live site already ranks 38 `/attraction/` URLs, so
 * the requested shape is answered with a permanent redirect instead of a base
 * migration nobody approved yet.
 *
 * @return void
 */
function sa_2026_treasure_alias_redirect() {
	if ( is_admin() || ( function_exists( 'is_preview' ) && is_preview() ) ) {
		return;
	}
	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	if ( ! is_404() ) {
		return; // A real page/entity at this path always wins.
	}

	$path = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = '/' . trim( $path, '/' );
	if ( ! preg_match( '#^/treasure(?:/([^/]+))?/?$#u', $path, $matches ) ) {
		return;
	}

	$tail = isset( $matches[1] ) ? trim( (string) $matches[1] ) : '';
	if ( '' === $tail ) {
		$target = sa_2026_treasure_url();
	} else {
		$post = get_page_by_path( $tail, OBJECT, 'attraction' );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return; // Let WordPress answer with its own 404.
		}
		$target = (string) get_permalink( $post );
	}

	if ( $target ) {
		wp_safe_redirect( $target, 301, 'Sarzamin Aryan' );
		exit;
	}
}
add_action( 'template_redirect', 'sa_2026_treasure_alias_redirect', 2 );
