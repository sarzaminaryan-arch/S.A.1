<?php
/**
 * Published attraction links for county pages.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render published attractions related to a city through sa_city_id.
 *
 * @param int $city_id City post ID.
 * @return string Safe HTML, or an empty string when no published attractions exist.
 */
function sa_city_attractions_html( $city_id ) {
	$city_id = absint( $city_id );
	$city    = get_post( $city_id );
	if ( ! $city || 'city' !== $city->post_type || 'publish' !== $city->post_status ) {
		return '';
	}

	$cache_key = 'sa_city_attractions_' . $city_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_string( $cached ) ? $cached : '';
	}

	$query = new WP_Query(
		array(
			'post_type'              => 'attraction',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'sa_city_id',
					'value'   => $city_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
				),
			),
		)
	);

	$items = array();
	foreach ( (array) $query->posts as $attraction_id ) {
		$attraction_id = absint( $attraction_id );
		$permalink     = get_permalink( $attraction_id );
		$title         = get_the_title( $attraction_id );
		if ( ! $attraction_id || ! $permalink || '' === trim( (string) $title ) ) {
			continue;
		}
		$items[] = '<li><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></li>';
	}

	$html = '';
	if ( $items ) {
		$city_name = function_exists( 'sa_county_name' )
			? sa_county_name( $city_id, false )
			: get_the_title( $city_id );
		$title_id  = 'sa-city-attractions-title-' . $city_id;
		$html      = '<section class="sa-city-attractions" aria-labelledby="' . esc_attr( $title_id ) . '">'
			. '<h2 id="' . esc_attr( $title_id ) . '">' . esc_html( 'جاهای دیدنی ' . $city_name ) . '</h2>'
			. '<ul>' . implode( '', $items ) . '</ul></section>';
	}

	wp_reset_postdata();
	set_transient( $cache_key, $html, 10 * MINUTE_IN_SECONDS );
	$keys = (array) get_option( 'sa_city_attractions_cache_keys', array() );
	if ( ! in_array( $cache_key, $keys, true ) ) {
		$keys[] = $cache_key;
		update_option( 'sa_city_attractions_cache_keys', array_slice( $keys, -1000 ), false );
	}

	return $html;
}

/**
 * Flush cached city attraction sections after city/attraction saves or deletes.
 *
 * @param int          $post_id Post ID.
 * @param WP_Post|null $post    Post object.
 * @return void
 */
function sa_city_attractions_flush_cache( $post_id, $post = null ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! $post ) {
		$post = get_post( $post_id );
	}
	if ( ! $post || ! in_array( $post->post_type, array( 'city', 'attraction' ), true ) ) {
		return;
	}

	foreach ( (array) get_option( 'sa_city_attractions_cache_keys', array() ) as $cache_key ) {
		delete_transient( sanitize_key( $cache_key ) );
	}
	delete_option( 'sa_city_attractions_cache_keys' );
}
add_action( 'save_post', 'sa_city_attractions_flush_cache', 100, 3 );
add_action( 'save_post_city', 'sa_city_attractions_flush_cache', 999, 3 );
add_action( 'save_post_attraction', 'sa_city_attractions_flush_cache', 999, 3 );
add_action( 'deleted_post', 'sa_city_attractions_flush_cache', 10, 2 );

/**
 * Render parent links on attraction pages.
 *
 * @param int $attraction_id Attraction post ID.
 * @return string Safe HTML, or an empty string when no published parent is known.
 */
function sa_attraction_parent_links_html( $attraction_id ) {
	$attraction_id = absint( $attraction_id );
	$city          = function_exists( 'sa_get_parent' ) ? sa_get_parent( $attraction_id, 'city' ) : null;
	$province      = function_exists( 'sa_get_parent' ) ? sa_get_parent( $attraction_id, 'province' ) : null;
	if ( ! $province && $city && function_exists( 'sa_get_parent' ) ) {
		$province = sa_get_parent( $city->ID, 'province' );
	}

	$links = array();
	if ( $city instanceof WP_Post && 'publish' === $city->post_status ) {
		$links[] = '<li><a href="' . esc_url( get_permalink( $city ) ) . '">' . esc_html( 'بازگشت به شهرستان ' . get_the_title( $city ) ) . '</a></li>';
	}
	if ( $province instanceof WP_Post && 'publish' === $province->post_status ) {
		$links[] = '<li><a href="' . esc_url( get_permalink( $province ) ) . '">' . esc_html( 'بازگشت به استان ' . get_the_title( $province ) ) . '</a></li>';
	}
	if ( ! $links ) {
		return '';
	}

	return '<nav class="sa-attraction-parents" aria-label="بازگشت به شهرستان و استان"><ul>' . implode( '', $links ) . '</ul></nav>';
}
