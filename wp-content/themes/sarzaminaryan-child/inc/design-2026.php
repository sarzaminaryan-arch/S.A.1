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
	$classes[] = 'sa-2026';
	$classes[] = 'sa-palette--' . sa_2026_palette();

	return $classes;
}
add_filter( 'body_class', 'sa_2026_body_class' );

/**
 * Load the compiled design-system stylesheet and the page interactions.
 */
function sa_2026_assets() {
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
			'label'  => 'دیدنی‌ها',
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
			'label' => 'دیدنی‌های ایران',
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

	if ( ! $url && function_exists( 'sa_cover_image_size' ) ) {
		$url = get_the_post_thumbnail_url( $post_id, 'full' );
	}

	if ( ! $url ) {
		$url = SA_CHILD_URI . 'assets/img/home-iran-landscape.jpg';
	}

	return (string) $url;
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
