<?php
/**
 * Helpers for the concise, bilingual province identity profile.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read the profile dataset once per request.
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
 * Source metadata retained with the local dataset (not printed in the profile).
 *
 * @return array
 */
function sa_province_profile_metadata() {
	$data = sa_province_profile_data();
	return isset( $data['_meta'] ) && is_array( $data['_meta'] ) ? $data['_meta'] : array();
}

/**
 * Get one province's profile row.
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
 * Normalize Persian names for matching source labels to the province catalog.
 *
 * @param string $name Province or neighbour name.
 * @return string
 */
function sa_province_profile_normalize_name( $name ) {
	$name = trim( (string) $name );
	$name = preg_replace( '/[\s\p{Z}\x{200C}]+/u', ' ', $name );
	return trim( (string) $name );
}

/**
 * Province catalog indexed by stable theme slug and normalized Persian name.
 *
 * @return array
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
		$name = sa_province_profile_normalize_name( $record['name'] );
		$catalog['by_slug'][ $slug ] = $record;
		$catalog['by_name'][ $name ] = $slug;
	}

	return $catalog;
}

/**
 * Convert an ASCII county slug to a readable English name.
 *
 * The repository's county registry uses Latin slugs but has no separate English
 * label. This formats that existing transliteration; it does not invent a URL.
 *
 * @param string $slug County slug.
 * @return string
 */
function sa_province_profile_english_name( $slug ) {
	$slug = strtolower( sanitize_key( (string) $slug ) );
	$slug = str_replace( '_', '-', $slug );
	$slug = preg_replace( '/-(?:city|county)$/', '', $slug );
	$parts = array_filter( explode( '-', $slug ), 'strlen' );
	$words = array();
	$last  = count( $parts ) - 1;

	foreach ( array_values( $parts ) as $index => $part ) {
		if ( in_array( $part, array( 'e', 'eh' ), true ) && $words && $index < $last ) {
			$words[ count( $words ) - 1 ] .= '-' . $part;
			continue;
		}
		$words[] = ucfirst( $part );
	}

	return implode( ' ', $words );
}

/**
 * Published province posts indexed by their real WordPress slug.
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
 * Remove a source annotation such as "(مرز آبی)" for matching.
 *
 * @param string $place Source label.
 * @return string
 */
function sa_province_profile_clean_place( $place ) {
	$place = trim( (string) $place );
	$place = preg_replace( '/\s*\([^)]*\)\s*$/u', '', $place );
	return sa_province_profile_normalize_name( $place );
}

/**
 * Resolve a neighbouring province to a real published WordPress page.
 *
 * @param string $place Neighbour label.
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
 * English label for a neighbouring province or a named international border.
 *
 * @param string $place Persian label.
 * @return string
 */
function sa_province_profile_place_english( $place ) {
	$place   = sa_province_profile_clean_place( $place );
	$catalog = sa_province_profile_catalog();
	if ( isset( $catalog['by_name'][ $place ] ) ) {
		$slug = $catalog['by_name'][ $place ];
		return isset( $catalog['by_slug'][ $slug ]['en'] ) ? (string) $catalog['by_slug'][ $slug ]['en'] : '';
	}

	$outside = array(
		'ارمنستان'        => 'Armenia',
		'افغانستان'       => 'Afghanistan',
		'ترکمنستان'       => 'Turkmenistan',
		'ترکیه'           => 'Turkey',
		'جمهوری آذربایجان' => 'Azerbaijan',
		'خلیج فارس'       => 'Persian Gulf',
		'دریای خزر'       => 'Caspian Sea',
		'دریای عمان'      => 'Gulf of Oman',
		'دریای عمان / خلیج فارس' => 'Gulf of Oman / Persian Gulf',
		'عراق'            => 'Iraq',
		'نخجوان'          => 'Nakhchivan',
		'پاکستان'         => 'Pakistan',
	);

	return isset( $outside[ $place ] ) ? $outside[ $place ] : '';
}

/**
 * Published county pages related to a province through the stored relation.
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
 * Reader-friendly county label, preferring the canonical registry name.
 *
 * @param WP_Post $post         County page.
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
	$title = preg_replace( '/^(?:شهرستان[\s\p{Z}\x{200C}]+)+/u', '', $title );
	return trim( (string) $title );
}

/**
 * Full province county list; only real, published related pages get links.
 *
 * @param string $province_slug Province slug.
 * @param int       $province_id Province post ID.
 * @param WP_Post[] $county_posts Optional published county posts already queried.
 * @return array[]
 */
function sa_province_profile_county_entries( $province_slug, $province_id, $county_posts = null ) {
	$province_slug = sanitize_key( (string) $province_slug );
	$posts         = is_array( $county_posts ) ? $county_posts : sa_province_profile_county_posts( $province_id );
	$posts_by_slug = array();
	foreach ( $posts as $post ) {
		$posts_by_slug[ sanitize_key( $post->post_name ) ] = $post;
	}

	$registered = function_exists( 'sa_counties_by_province' ) ? sa_counties_by_province() : array();
	$rows       = isset( $registered[ $province_slug ] ) ? (array) $registered[ $province_slug ] : array();
	$entries    = array();

	foreach ( $rows as $row ) {
		if ( empty( $row['slug'] ) ) {
			continue;
		}
		$slug = sanitize_key( $row['slug'] );
		$post = isset( $posts_by_slug[ $slug ] ) ? $posts_by_slug[ $slug ] : null;
		$name = $post ? sa_province_profile_county_label( $post, $province_slug ) : ( isset( $row['name'] ) ? trim( (string) $row['name'] ) : '' );
		if ( '' === $name ) {
			$name = sa_province_profile_english_name( $slug );
		}
		$english = ! empty( $row['en'] ) ? (string) $row['en'] : sa_province_profile_english_name( $slug );
		$entries[ $slug ] = array(
			'slug'    => $slug,
			'name'    => $name,
			'english' => $english,
			'url'     => $post ? (string) get_permalink( $post ) : '',
		);
	}

	foreach ( $posts as $post ) {
		$slug = sanitize_key( $post->post_name );
		if ( isset( $entries[ $slug ] ) ) {
			continue;
		}
		$entries[ $slug ] = array(
			'slug'    => $slug,
			'name'    => sa_province_profile_county_label( $post, $province_slug ),
			'english' => sa_province_profile_english_name( $slug ),
			'url'     => (string) get_permalink( $post ),
		);
	}

	return array_values( $entries );
}

/**
 * Find the published county page matching the registered province center.
 *
 * @param string    $province_slug Province slug.
 * @param WP_Post[] $county_posts  Related published county pages.
 * @return WP_Post|null
 */
function sa_province_profile_center_post( $province_slug, $county_posts ) {
	$catalog = sa_province_profile_catalog();
	$slug    = sanitize_key( $province_slug );
	if ( ! isset( $catalog['by_slug'][ $slug ]['center'] ) ) {
		return null;
	}
	$center = sa_province_profile_normalize_name( $catalog['by_slug'][ $slug ]['center'] );

	foreach ( (array) $county_posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$registry = function_exists( 'sa_county' ) ? sa_county( $post->post_name ) : null;
		if ( $registry && isset( $registry['province'], $registry['name'] ) && $slug === $registry['province'] && $center === sa_province_profile_normalize_name( $registry['name'] ) ) {
			return $post;
		}
		if ( $center === sa_province_profile_normalize_name( sa_province_profile_county_label( $post, $slug ) ) ) {
			return $post;
		}
	}

	return null;
}

/**
 * English name for a province center, preferring its registered county slug.
 *
 * @param string       $province_slug Province slug.
 * @param string       $center_name   Persian center name.
 * @param WP_Post|null $center_post   Published center county page, if present.
 * @return string
 */
function sa_province_profile_center_english( $province_slug, $center_name, $center_post = null ) {
	if ( $center_post instanceof WP_Post ) {
		return sa_province_profile_english_name( $center_post->post_name );
	}

	$province_slug = sanitize_key( (string) $province_slug );
	$center_name   = sa_province_profile_normalize_name( $center_name );
	$registered    = function_exists( 'sa_counties_by_province' ) ? sa_counties_by_province() : array();
	foreach ( isset( $registered[ $province_slug ] ) ? (array) $registered[ $province_slug ] : array() as $row ) {
		if ( isset( $row['name'], $row['slug'] ) && $center_name === sa_province_profile_normalize_name( $row['name'] ) ) {
			return sa_province_profile_english_name( $row['slug'] );
		}
	}

	$known_centers = array(
		'یاسوج' => 'Yasuj',
	);

	return isset( $known_centers[ $center_name ] ) ? $known_centers[ $center_name ] : '';
}

/**
 * Display a broad, reader-friendly population estimate rather than census precision.
 *
 * @param int|float|string $population Population estimate.
 * @return string
 */
function sa_province_profile_population_label( $population ) {
	$population = (int) $population;
	if ( $population < 1 ) {
		return '';
	}

	$rounded = (int) round( $population / 100000 ) * 100000;
	if ( $rounded >= 1000000 ) {
		$millions = $rounded / 1000000;
		$decimals = 0 === $rounded % 1000000 ? 0 : 1;
		return 'حدود ' . sa_number( $millions, $decimals ) . ' میلیون';
	}

	return 'حدود ' . sa_number( $rounded / 1000 ) . ' هزار';
}
