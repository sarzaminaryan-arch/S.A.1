<?php
/**
 * Source-aware data helpers for the province identity profile.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the province profile dataset once per request.
 *
 * @return array
 */
function sa_province_profile_data() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}

	$file = SA_CHILD_DIR . 'data/province-profiles.json';
	if ( ! is_readable( $file ) ) {
		$data = array();
		return $data;
	}

	$raw  = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$read = is_string( $raw ) ? json_decode( $raw, true ) : null;
	$data = is_array( $read ) ? $read : array();

	return $data;
}

/**
 * Dataset-level provenance and review status.
 *
 * @return array
 */
function sa_province_profile_metadata() {
	$data = sa_province_profile_data();
	return isset( $data['_meta'] ) && is_array( $data['_meta'] ) ? $data['_meta'] : array();
}

/**
 * Get the statistics and neighbour record for one province slug.
 *
 * @param string $slug Province post slug.
 * @return array|null
 */
function sa_province_profile( $slug ) {
	$slug = sanitize_key( (string) $slug );
	$data = sa_province_profile_data();

	return isset( $data['provinces'][ $slug ] ) && is_array( $data['provinces'][ $slug ] )
		? $data['provinces'][ $slug ]
		: null;
}

/**
 * Province catalog indexed by the stable theme slug and Persian name.
 *
 * @return array{by_slug:array,by_name:array}
 */
function sa_province_profile_catalog() {
	static $catalog = null;
	if ( null !== $catalog ) {
		return $catalog;
	}

	$catalog = array(
		'by_slug' => array(),
		'by_name' => array(),
	);
	$file    = SA_CHILD_DIR . 'data/provinces.php';
	$records = is_readable( $file ) ? (array) require $file : array();

	foreach ( $records as $record ) {
		if ( empty( $record['slug'] ) || empty( $record['name'] ) ) {
			continue;
		}
		$slug = sanitize_key( $record['slug'] );
		$name = trim( (string) $record['name'] );
		$catalog['by_slug'][ $slug ] = $record;
		$catalog['by_name'][ $name ] = $slug;
	}

	return $catalog;
}

/**
 * Published province posts keyed by their real WordPress slug.
 *
 * A neighbour is linked only when a published post exists for its exact catalog slug.
 *
 * @return int[]
 */
function sa_province_profile_published_provinces() {
	static $posts_by_slug = null;
	if ( null !== $posts_by_slug ) {
		return $posts_by_slug;
	}

	$posts_by_slug = array();
	$ids           = get_posts(
		array(
			'post_type'              => 'province',
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $ids as $id ) {
		$slug = get_post_field( 'post_name', $id );
		if ( $slug ) {
			$posts_by_slug[ sanitize_key( $slug ) ] = (int) $id;
		}
	}

	return $posts_by_slug;
}

/**
 * Resolve a neighbour name to a real, published province post ID.
 *
 * @param string $place Neighbour name as written in the source document.
 * @return int|null
 */
function sa_province_profile_neighbor_post_id( $place ) {
	$place   = sa_province_profile_clean_place( $place );
	$catalog = sa_province_profile_catalog();
	if ( ! isset( $catalog['by_name'][ $place ] ) ) {
		return null;
	}

	$slug  = $catalog['by_name'][ $place ];
	$pages = sa_province_profile_published_provinces();
	return isset( $pages[ $slug ] ) ? (int) $pages[ $slug ] : null;
}

/**
 * Remove a source annotation such as "(مرز آبی)" for exact province-name matching.
 *
 * The unmodified source label is still used for display when no province post matches.
 *
 * @param string $place Source label.
 * @return string
 */
function sa_province_profile_clean_place( $place ) {
	$place = trim( (string) $place );
	$place = preg_replace( '/\s*\([^)]*\)\s*$/u', '', $place );
	return trim( (string) $place );
}

/**
 * Published county pages related to this province through the site's stored relation.
 *
 * @param int $province_id Province post ID.
 * @return WP_Post[]
 */
function sa_province_profile_county_posts( $province_id ) {
	if ( ! function_exists( 'sa_get_children' ) ) {
		return array();
	}

	$posts = sa_get_children( (int) $province_id, 'city' );
	$rows  = array();
	foreach ( (array) $posts as $post ) {
		if ( $post instanceof WP_Post && 'city' === $post->post_type && 'publish' === $post->post_status ) {
			$rows[] = $post;
		}
	}
	return $rows;
}

/**
 * Reader-friendly county label, preferring a province-matched registry name.
 *
 * @param WP_Post $post         Published county page.
 * @param string  $province_slug Parent province slug.
 * @return string
 */
function sa_province_profile_county_label( $post, $province_slug ) {
	if ( ! ( $post instanceof WP_Post ) ) {
		return '';
	}

	if ( function_exists( 'sa_county' ) ) {
		$registry = sa_county( $post->post_name );
		if ( $registry && isset( $registry['province'], $registry['name'] ) && $province_slug === $registry['province'] ) {
			return (string) $registry['name'];
		}
	}

	if ( function_exists( 'sa_county_name' ) ) {
		return trim( (string) sa_county_name( $post, false ) );
	}

	$title = trim( get_the_title( $post ) );
	$title = preg_replace( '/^شهرستان(?:\s|‌)+/u', '', $title );
	return trim( (string) $title );
}

/**
 * Find the published county page matching the exact registered province-center name.
 *
 * @param string  $province_slug Province slug.
 * @param WP_Post[] $county_posts Published county posts already scoped to the province.
 * @return WP_Post|null
 */
function sa_province_profile_center_post( $province_slug, $county_posts ) {
	$catalog = sa_province_profile_catalog();
	$slug    = sanitize_key( $province_slug );
	if ( ! isset( $catalog['by_slug'][ $slug ]['center'] ) ) {
		return null;
	}
	$center = trim( (string) $catalog['by_slug'][ $slug ]['center'] );

	foreach ( (array) $county_posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

		if ( function_exists( 'sa_county' ) ) {
			$registry = sa_county( $post->post_name );
			if ( $registry && isset( $registry['province'], $registry['name'] ) && $slug === $registry['province'] && $center === $registry['name'] ) {
				return $post;
			}
		}

		$title = trim( get_the_title( $post ) );
		$title = preg_replace( '/^شهرستان(?:\s|‌)+/u', '', $title );
		if ( $center === trim( (string) $title ) ) {
			return $post;
		}
	}

	return null;
}

/**
 * Detect disagreements between the supplied neighbour list and the legacy theme list.
 *
 * @param string $province_slug Province slug.
 * @param array  $profile      Supplied profile row.
 * @return array{source_only:string[],legacy_only:string[]}
 */
function sa_province_profile_neighbor_differences( $province_slug, $profile ) {
	$legacy = function_exists( 'sa_region_province' ) ? sa_region_province( $province_slug ) : null;
	if ( ! is_array( $legacy ) || empty( $legacy['neighbors'] ) || empty( $profile['neighbor_groups'] ) ) {
		return array();
	}

	$catalog    = sa_province_profile_catalog();
	$source_set = array();
	$legacy_set = array();
	foreach ( (array) $profile['neighbor_groups'] as $group ) {
		foreach ( isset( $group['places'] ) ? (array) $group['places'] : array() as $place ) {
			$clean = sa_province_profile_clean_place( $place );
			if ( isset( $catalog['by_name'][ $clean ] ) ) {
				$source_set[ $clean ] = true;
			}
		}
	}

	$legacy_names = preg_split( '/[,،\r\n]+/u', (string) $legacy['neighbors'] );
	foreach ( (array) $legacy_names as $name ) {
		$clean = sa_province_profile_clean_place( $name );
		if ( isset( $catalog['by_name'][ $clean ] ) ) {
			$legacy_set[ $clean ] = true;
		}
	}

	$source_only = array_keys( array_diff_key( $source_set, $legacy_set ) );
	$legacy_only = array_keys( array_diff_key( $legacy_set, $source_set ) );
	sort( $source_only, SORT_STRING );
	sort( $legacy_only, SORT_STRING );

	return $source_only || $legacy_only
		? array(
			'source_only' => $source_only,
			'legacy_only' => $legacy_only,
		)
		: array();
}
