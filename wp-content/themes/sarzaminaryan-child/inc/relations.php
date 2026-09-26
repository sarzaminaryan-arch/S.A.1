<?php
/**
 * Relations (Level 3 & 6): R1 province denormalisation, R4 delete guard, reverse lookups with caching.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep sa_province_id + province_tax terms consistent (R1) after an entity is saved.
 *
 * @param int    $post_id Post ID.
 * @param string $type    CPT.
 */
function sa_sync_relations( $post_id, $type ) {
	$province_ids = array();

	if ( 'province' === $type ) {
		// Link the hub post to its term (by slug) and make sure the term is assigned.
		$term = get_term_by( 'slug', get_post_field( 'post_name', $post_id ), 'province_tax' );
		if ( $term ) {
			wp_set_post_terms( $post_id, array( (int) $term->term_id ), 'province_tax', false );
			update_post_meta( $post_id, 'sa_province_term_id', $term->term_id );
			update_term_meta( $term->term_id, 'sa_province_post_id', $post_id );
		} else {
			$terms = wp_get_post_terms( $post_id, 'province_tax', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				update_post_meta( $post_id, 'sa_province_term_id', $terms[0] );
				update_term_meta( $terms[0], 'sa_province_post_id', $post_id );
			}
		}
		return;
	}

	if ( 'travel_route' === $type ) {
		foreach ( sa_meta_ids( $post_id, 'sa_city_ids' ) as $city_id ) {
			$p = (int) get_post_meta( $city_id, 'sa_province_id', true );
			if ( $p ) {
				$province_ids[] = $p;
			}
		}
	} else {
		$city_id = (int) get_post_meta( $post_id, 'sa_city_id', true );
		if ( $city_id ) {
			$p = (int) get_post_meta( $city_id, 'sa_province_id', true );
			if ( $p ) {
				update_post_meta( $post_id, 'sa_province_id', $p ); // R1: never disagree with the city.
			}
		}
		$p = (int) get_post_meta( $post_id, 'sa_province_id', true );
		if ( $p ) {
			$province_ids[] = $p;
		}
	}

	// Mirror province relation into the province_tax taxonomy (archives, filters, admin column).
	$term_ids = array();
	foreach ( array_unique( $province_ids ) as $pid ) {
		$term = sa_province_term_for_post( $pid );
		if ( $term ) {
			$term_ids[] = (int) $term->term_id;
		}
	}
	if ( $term_ids ) {
		wp_set_post_terms( $post_id, $term_ids, 'province_tax', false );
	}

	// If a city changed province, its children follow (R1) — cheap loop, cities rarely move.
	if ( 'city' === $type ) {
		$p = (int) get_post_meta( $post_id, 'sa_province_id', true );
		if ( $p ) {
			foreach ( array( 'attraction', 'local_food', 'souvenir', 'accommodation' ) as $child_type ) {
				if ( ! post_type_exists( $child_type ) ) {
					continue;
				}
				foreach ( sa_get_children_ids( $post_id, $child_type, 'sa_city_id', false ) as $child ) {
					if ( (int) get_post_meta( $child, 'sa_province_id', true ) !== $p ) {
						update_post_meta( $child, 'sa_province_id', $p );
						$term = sa_province_term_for_post( $p );
						if ( $term ) {
							wp_set_post_terms( $child, array( (int) $term->term_id ), 'province_tax', false );
						}
					}
				}
			}
		}
	}
}

/**
 * IDs of $child_type posts whose $meta_key equals $parent_id (cached).
 *
 * @param int    $parent_id  Parent post ID.
 * @param string $child_type Child CPT.
 * @param string $meta_key   Meta key holding the parent ID.
 * @param bool   $published  Only published?
 * @return int[]
 */
function sa_get_children_ids( $parent_id, $child_type, $meta_key, $published = true ) {
	$cache_key = 'sa_rel_' . md5( $parent_id . '|' . $child_type . '|' . $meta_key . '|' . ( $published ? 1 : 0 ) );
	$ids       = get_transient( $cache_key );
	if ( false !== $ids ) {
		return (array) $ids;
	}
	$ids = get_posts(
		array(
			'post_type'        => $child_type,
			'post_status'      => $published ? 'publish' : array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'   => 500,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => $meta_key,
					'value' => (int) $parent_id,
				),
			),
		)
	);
	set_transient( $cache_key, $ids, 12 * HOUR_IN_SECONDS );
	sa_remember_cache_key( $cache_key );
	return $ids;
}

/**
 * Track transient keys so they can be flushed together.
 *
 * @param string $key Key.
 */
function sa_remember_cache_key( $key ) {
	$keys = get_option( 'sa_rel_cache_keys', array() );
	if ( ! in_array( $key, $keys, true ) ) {
		$keys[] = $key;
		if ( count( $keys ) > 3000 ) {
			$keys = array_slice( $keys, -3000 );
		}
		update_option( 'sa_rel_cache_keys', $keys, false );
	}
}

/**
 * Flush all relation caches (called on every entity save / delete).
 *
 * @param int    $post_id Post ID.
 * @param string $type    CPT.
 */
function sa_flush_relation_cache( $post_id = 0, $type = '' ) {
	if ( $type && ! sa_is_entity( $type ) ) {
		return;
	}
	foreach ( (array) get_option( 'sa_rel_cache_keys', array() ) as $key ) {
		delete_transient( $key );
	}
	delete_option( 'sa_rel_cache_keys' );
	delete_transient( 'sa_entity_counts' );
}
add_action( 'deleted_post', 'sa_flush_relation_cache', 10, 1 );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( $new !== $old && sa_is_entity( $post ) ) {
		sa_flush_relation_cache( $post->ID, $post->post_type );
	}
}, 10, 3 );

/**
 * Children of an entity by type, e.g. sa_get_children( $province_id, 'city' ).
 *
 * @param int    $parent_id  Parent.
 * @param string $child_type Child CPT.
 * @return WP_Post[]
 */
function sa_get_children( $parent_id, $child_type ) {
	$parent_type = get_post_type( $parent_id );
	$meta_key    = 'sa_' . $parent_type . '_id';
	if ( 'travel_route' === $child_type ) {
		$meta_key = 'city' === $parent_type ? 'sa_city_ids' : ( 'attraction' === $parent_type ? 'sa_attraction_ids' : '' );
		if ( 'province' === $parent_type ) {
			// Routes touching any city of the province → via province_tax term.
			$term = sa_province_term_for_post( $parent_id );
			if ( ! $term ) {
				return array();
			}
			return get_posts(
				array(
					'post_type'      => 'travel_route',
					'posts_per_page' => 50,
					'no_found_rows'  => true,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => 'province_tax',
							'terms'    => $term->term_id,
						),
					),
				)
			);
		}
	}
	if ( ! $meta_key ) {
		return array();
	}
	$ids = sa_get_children_ids( $parent_id, $child_type, $meta_key );
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => $child_type,
			'post__in'       => $ids,
			'posts_per_page' => count( $ids ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
}

/**
 * Explicit multi relations (routes → cities/attractions, attraction → related).
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key (sa_city_ids …).
 * @return WP_Post[]
 */
function sa_get_related( $post_id, $key ) {
	$ids = sa_meta_ids( $post_id, $key );
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'any',
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => count( $ids ),
			'no_found_rows'  => true,
		)
	);
}

/**
 * Parent entity (belongs_to) as post or null.
 *
 * @param int    $post_id Post ID.
 * @param string $target  'city' | 'province'.
 * @return WP_Post|null
 */
function sa_get_parent( $post_id, $target ) {
	$id = (int) get_post_meta( $post_id, 'sa_' . $target . '_id', true );
	if ( ! $id ) {
		return null;
	}
	$post = get_post( $id );
	return ( $post && 'publish' === $post->post_status ) ? $post : null;
}

/**
 * Similar attractions: explicit related_many first, then same type in same city (fills to $limit).
 *
 * @param int $post_id Attraction ID.
 * @param int $limit   Max items.
 * @return WP_Post[]
 */
function sa_get_similar_attractions( $post_id, $limit = 6 ) {
	$items = sa_get_related( $post_id, 'sa_related_attraction_ids' );
	if ( count( $items ) >= $limit ) {
		return array_slice( $items, 0, $limit );
	}
	$exclude = array_merge( array( $post_id ), wp_list_pluck( $items, 'ID' ) );
	$terms   = wp_get_post_terms( $post_id, 'attraction_type', array( 'fields' => 'ids' ) );
	$city    = (int) get_post_meta( $post_id, 'sa_city_id', true );
	$args    = array(
		'post_type'      => 'attraction',
		'posts_per_page' => $limit - count( $items ),
		'post__not_in'   => $exclude,
		'no_found_rows'  => true,
		'orderby'        => 'rand',
	);
	if ( $city ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => 'sa_city_id',
				'value' => $city,
			),
		);
	} elseif ( ! is_wp_error( $terms ) && $terms ) {
		$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'attraction_type',
				'terms'    => $terms,
			),
		);
	}
	return array_merge( $items, get_posts( $args ) );
}

/**
 * R4 — block deleting a City (or Province) that still has dependants.
 *
 * @param bool|null $check   Short-circuit value.
 * @param WP_Post   $post    Post.
 * @param bool      $force   Force delete.
 * @return bool|null
 */
function sa_guard_delete( $check, $post, $force ) {
	if ( ! in_array( $post->post_type, array( 'city', 'province' ), true ) ) {
		return $check;
	}
	$children = array( 'city' => array( 'attraction', 'local_food', 'souvenir', 'accommodation' ), 'province' => array( 'city' ) );
	foreach ( $children[ $post->post_type ] as $child_type ) {
		if ( ! post_type_exists( $child_type ) ) {
			continue;
		}
		if ( sa_get_children_ids( $post->ID, $child_type, 'sa_' . $post->post_type . '_id', false ) ) {
			wp_die(
				sprintf(
					'حذف «%s» ممکن نیست: هنوز %s به آن وابسته است (قانون R4 مدل داده). ابتدا وابسته‌ها را به %s دیگری منتقل کنید.',
					esc_html( get_the_title( $post ) ),
					esc_html( sa_entity_label( $child_type, true ) ),
					esc_html( sa_entity_label( $post->post_type ) )
				),
				'حذف مسدود شد',
				array( 'back_link' => true )
			);
		}
	}
	return $check;
}
add_filter( 'pre_delete_post', 'sa_guard_delete', 10, 3 );
add_filter( 'pre_trash_post', 'sa_guard_delete', 10, 3 );
