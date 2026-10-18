<?php
/**
 * Breadcrumbs (visible + feeds BreadcrumbList schema) following the Level 6 hierarchy:
 * خانه › استان‌ها › {استان} › {شهر} › {دیدنی}
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trail items for the current view. Each: ['name' => …, 'url' => … (empty for current)].
 *
 * @return array[]
 */
function sa_get_breadcrumb_items() {
	static $items = null;
	if ( null !== $items ) {
		return $items;
	}
	$items = array( array( 'name' => 'خانه', 'url' => home_url( '/' ) ) );

	if ( is_front_page() ) {
		return $items;
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		$type = $post->post_type;

		if ( sa_is_entity( $post ) ) {
			$items[] = array( 'name' => sa_entity_label( $type, true ), 'url' => sa_archive_url( $type ) );
			$province = sa_get_parent( $post->ID, 'province' );
			$city     = sa_get_parent( $post->ID, 'city' );
			if ( 'province' !== $type && $province ) {
				$items[] = array( 'name' => get_the_title( $province ), 'url' => get_permalink( $province ) );
			}
			if ( $city && ! in_array( $type, array( 'province', 'city' ), true ) ) {
				$city_name = function_exists( 'sa_county_name' ) ? sa_county_name( $city, true ) : get_the_title( $city );
				$items[]   = array( 'name' => $city_name, 'url' => get_permalink( $city ) );
			}
		} elseif ( 'post' === $type ) {
			$blog = (int) get_option( 'page_for_posts' );
			if ( $blog ) {
				$items[] = array( 'name' => get_the_title( $blog ), 'url' => get_permalink( $blog ) );
			}
			$cats = get_the_category( $post->ID );
			if ( $cats ) {
				$items[] = array( 'name' => $cats[0]->name, 'url' => get_category_link( $cats[0] ) );
			}
		} elseif ( 'page' === $type && $post->post_parent ) {
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
				$items[] = array( 'name' => get_the_title( $ancestor ), 'url' => get_permalink( $ancestor ) );
			}
		}
		$page_name = 'city' === $type && function_exists( 'sa_county_name' ) ? sa_county_name( $post, true ) : get_the_title( $post );
		$items[]   = array( 'name' => $page_name, 'url' => '' );
		return $items;
	}

	if ( is_post_type_archive() ) {
		$items[] = array( 'name' => post_type_archive_title( '', false ), 'url' => '' );
	} elseif ( is_tax( 'province_tax' ) ) {
		$term = get_queried_object();
		$items[] = array( 'name' => 'استان‌ها', 'url' => sa_archive_url( 'province' ) );
		$items[] = array( 'name' => 'استان ' . $term->name, 'url' => '' );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		$tax  = get_taxonomy( $term->taxonomy );
		if ( is_tax( 'attraction_type' ) ) {
			$items[] = array( 'name' => 'دیدنی‌ها', 'url' => sa_archive_url( 'attraction' ) );
		} elseif ( $tax && is_category() ) {
			$blog = (int) get_option( 'page_for_posts' );
			if ( $blog ) {
				$items[] = array( 'name' => get_the_title( $blog ), 'url' => get_permalink( $blog ) );
			}
		}
		$items[] = array( 'name' => $term->name, 'url' => '' );
	} elseif ( is_home() ) {
		$items[] = array( 'name' => get_the_title( (int) get_option( 'page_for_posts' ) ) ?: 'وبلاگ', 'url' => '' );
	} elseif ( is_search() ) {
		$items[] = array( 'name' => 'نتایج جست‌وجو برای «' . get_search_query() . '»', 'url' => '' );
	} elseif ( is_author() ) {
		$items[] = array( 'name' => get_the_author(), 'url' => '' );
	} elseif ( is_date() ) {
		$items[] = array( 'name' => get_the_archive_title(), 'url' => '' );
	} elseif ( is_404() ) {
		$items[] = array( 'name' => 'صفحه پیدا نشد', 'url' => '' );
	}
	return $items;
}

/**
 * Print the breadcrumb navigation.
 */
function sa_breadcrumbs() {
	$items = sa_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}
	echo '<nav class="sa-breadcrumbs" aria-label="مسیر صفحه"><ol>';
	$last = count( $items ) - 1;
	foreach ( $items as $i => $item ) {
		if ( $i === $last || empty( $item['url'] ) ) {
			echo '<li aria-current="page">' . esc_html( $item['name'] ) . '</li>';
		} else {
			echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a></li>';
		}
	}
	echo '</ol></nav>';
}
