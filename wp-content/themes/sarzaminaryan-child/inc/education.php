<?php
/**
 * بخش «آموزش‌ها» — دستهٔ «آموزش‌های ضروری کمپینگ و بقا» کنار استان‌ها، شهرستان‌ها و دیدنی‌ها.
 *
 * v2.13.1: the educational guides (ordinary posts in one category, imported by
 * the ready-articles tool) get a first-class place in the site's navigation:
 * hero chips and a dedicated section on the home page, the header menu (saved
 * menus and the fallback menu), the footer «کاوش» column and the archive filter
 * row. Everything keys off one category slug (default `camping-survival`,
 * changeable in the customizer) and follows the v2.3.0 rule «never link to an
 * empty archive»: the links appear automatically once the first guide is
 * published and disappear if none is public.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slug of the education hub category.
 *
 * @return string
 */
function sa_education_category_slug() {
	$slug = sanitize_title( (string) get_theme_mod( 'sa_education_category', 'camping-survival' ) );
	/**
	 * نامک دستهٔ هاب آموزش‌ها.
	 *
	 * @param string $slug نامک.
	 */
	$slug = (string) apply_filters( 'sa_education_category_slug', '' !== $slug ? $slug : 'camping-survival' );
	return '' !== $slug ? $slug : 'camping-survival';
}

/**
 * The education category term (null when it does not exist yet).
 *
 * @return WP_Term|null
 */
function sa_education_category() {
	static $cache = array();
	$slug = sa_education_category_slug();
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	// A miss is not cached: the ready-articles tool creates the category mid-request.
	$term = get_term_by( 'slug', $slug, 'category' );
	if ( $term instanceof WP_Term ) {
		$cache[ $slug ] = $term;
		return $term;
	}
	return null;
}

/**
 * Number of published guides (term count only counts published posts).
 *
 * @return int
 */
function sa_education_count() {
	$term = sa_education_category();
	return $term ? max( 0, (int) $term->count ) : 0;
}

/**
 * Navigation label («آموزش‌ها» by default; customizer: sa_education_nav_label).
 *
 * @return string
 */
function sa_education_label() {
	$label = trim( (string) get_theme_mod( 'sa_education_nav_label', 'آموزش‌ها' ) );
	return '' !== $label ? $label : 'آموزش‌ها';
}

/**
 * Full descriptive label for the 4th pillar of the site: «آموزش (طبیعت‌گردی و بقا)».
 *
 * @return string
 */
function sa_education_full_label() {
	$custom = trim( (string) get_theme_mod( 'sa_education_nav_label', '' ) );
	if ( '' !== $custom && 'آموزش‌ها' !== $custom ) {
		return $custom;
	}
	return 'آموزش (طبیعت‌گردی و بقا)';
}

/**
 * Archive URL of the education category ('' when the category is missing).
 *
 * @return string
 */
function sa_education_url() {
	$term = sa_education_category();
	if ( ! $term ) {
		return '';
	}
	$link = get_term_link( $term );
	return is_wp_error( $link ) ? '' : (string) $link;
}

/**
 * Whether the current request belongs to the education section (archive, one
 * of its posts, or a tag archive whose posts live in the category).
 *
 * @return bool
 */
function sa_is_education_context() {
	$term = sa_education_category();
	if ( ! $term ) {
		return false;
	}
	if ( is_category( $term->term_id ) ) {
		return true;
	}
	if ( is_singular( 'post' ) ) {
		return has_category( $term->term_id, get_queried_object_id() );
	}
	if ( is_tag() ) {
		$tag = get_queried_object();
		foreach ( sa_education_filter_tags() as $t ) {
			if ( $tag && (int) $t->term_id === (int) $tag->term_id ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Navigation item for the hub, or null while nothing is published.
 *
 * @return array{url:string,label:string,current:bool,count:int}|null
 */
function sa_education_nav_item() {
	if ( sa_education_count() < 1 ) {
		return null;
	}
	$url = sa_education_url();
	if ( '' === $url ) {
		return null;
	}
	return array(
		'url'     => $url,
		'label'   => sa_education_label(),
		'current' => sa_is_education_context(),
		'count'   => sa_education_count(),
	);
}

/**
 * Tags (branches) that have at least one published guide in the category.
 *
 * @return WP_Term[]
 */
function sa_education_filter_tags() {
	$term = sa_education_category();
	if ( ! $term || (int) $term->count < 1 ) {
		return array();
	}
	$cached = get_transient( 'sa_edu_tags_' . $term->term_id );
	if ( is_array( $cached ) ) {
		$out = array();
		foreach ( $cached as $id ) {
			$t = get_term( (int) $id, 'post_tag' );
			if ( $t instanceof WP_Term ) {
				$out[] = $t;
			}
		}
		return $out;
	}
	$ids = get_posts(
		array(
			'post_type'              => 'post',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'cat'                    => (int) $term->term_id,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
		)
	);
	$tags = $ids ? wp_get_object_terms( $ids, 'post_tag', array( 'orderby' => 'name' ) ) : array();
	if ( is_wp_error( $tags ) ) {
		$tags = array();
	}
	$unique = array();
	foreach ( $tags as $t ) {
		$unique[ $t->term_id ] = $t;
	}
	set_transient( 'sa_edu_tags_' . $term->term_id, array_keys( $unique ), 10 * MINUTE_IN_SECONDS );
	return array_values( $unique );
}

/**
 * Drop the cached branch list whenever a post changes state or terms.
 */
function sa_education_flush_cache() {
	$term = sa_education_category();
	if ( $term ) {
		delete_transient( 'sa_edu_tags_' . $term->term_id );
	}
}
add_action( 'transition_post_status', 'sa_education_flush_cache' );
add_action( 'set_object_terms', 'sa_education_flush_cache' );
add_action( 'deleted_post', 'sa_education_flush_cache' );

/**
 * Latest published guides for the home page.
 *
 * @param int $limit How many.
 * @return WP_Query|null
 */
function sa_education_latest_query( $limit = 6 ) {
	$term = sa_education_category();
	if ( ! $term || (int) $term->count < 1 ) {
		return null;
	}
	return new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, (int) $limit ),
			'cat'                 => (int) $term->term_id,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Append the hub to a saved header menu (location «primary») when the menu does
 * not already link to it; placed right after the last entity archive item so it
 * sits next to استان‌ها / شهرستان‌ها / دیدنی‌ها.
 *
 * @param WP_Post[] $items Sorted menu items.
 * @param stdClass  $args  Menu args.
 * @return WP_Post[]
 */
function sa_education_menu_item( $items, $args ) {
	$location = isset( $args->theme_location ) ? (string) $args->theme_location : '';
	if ( 'primary' !== $location || ! is_array( $items ) ) {
		return $items;
	}
	$nav = sa_education_nav_item();
	if ( ! $nav ) {
		return $items;
	}
	$target = untrailingslashit( $nav['url'] );
	$after  = -1;
	$order  = 0;
	$types  = function_exists( 'sa_entity_types' ) ? (array) sa_entity_types() : array();
	$urls   = array();
	foreach ( $types as $type ) {
		$urls[] = untrailingslashit( (string) sa_archive_url( $type ) ); // custom links to the archives count too.
	}
	foreach ( $items as $i => $item ) {
		$url = untrailingslashit( (string) $item->url );
		if ( $url === $target ) {
			return $items; // the owner already placed it manually.
		}
		$order = max( $order, (int) $item->menu_order );
		if ( empty( $item->menu_item_parent ) && ( in_array( (string) $item->object, $types, true ) || in_array( $url, $urls, true ) ) ) {
			$after = $i;
		}
	}

	$term = sa_education_category();
	$item = (object) array(
		'ID'                    => 'sa-education',
		'db_id'                 => 0,
		'menu_item_parent'      => 0,
		'object_id'             => $term ? (int) $term->term_id : 0,
		'object'                => 'category',
		'type'                  => 'taxonomy',
		'type_label'            => 'دسته',
		'title'                 => $nav['label'],
		'post_title'            => $nav['label'],
		'post_name'             => 'sa-education',
		'post_type'             => 'nav_menu_item',
		'post_status'           => 'publish',
		'post_parent'           => 0,
		'url'                   => $nav['url'],
		'target'                => '',
		'attr_title'            => '',
		'description'           => '',
		'xfn'                   => '',
		'classes'               => array( 'menu-item', 'menu-item-type-taxonomy', 'menu-item-object-category', 'sa-menu-item--education' ),
		'current'               => $nav['current'],
		'current_item_ancestor' => false,
		'current_item_parent'   => false,
		'menu_order'            => $order + 1,
	);
	if ( $nav['current'] ) {
		$item->classes[] = 'current-menu-item';
	}

	if ( $after >= 0 ) {
		// Skip the children of that archive item so the hub lands after the whole group.
		$insert_at = $after + 1;
		$parent_id = isset( $items[ $after ]->ID ) ? (int) $items[ $after ]->ID : 0;
		while ( isset( $items[ $insert_at ] ) && $parent_id && (int) $items[ $insert_at ]->menu_item_parent === $parent_id ) {
			$insert_at++;
		}
		array_splice( $items, $insert_at, 0, array( $item ) );
	} else {
		$items[] = $item;
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'sa_education_menu_item', 20, 2 );

/**
 * v2.15.0 — شمار آموزش‌های بخش صفحهٔ اول (۰ = همهٔ آموزش‌ها).
 *
 * پیش‌فرض ۱۰ است؛ با فیلتر `sa_education_home_count` یا از سفارشی‌ساز
 * («سرزمین آریان → صفحه اصلی (طرح v2) → تعداد آموزش‌های صفحهٔ اول») عوض می‌شود.
 *
 * @return int
 */
function sa_education_home_count() {
	$count = (int) get_theme_mod( 'sa_sec_edu_count', 10 );
	/**
	 * شمار کارت/نوار آموزش در صفحهٔ اول.
	 *
	 * @param int $count شمار (۰ = همه).
	 */
	$count = (int) apply_filters( 'sa_education_home_count', $count );
	return max( 0, $count );
}

/**
 * v2.15.0 — نوارهای «آموزش‌ها» به سبک فهرست استان‌ها/شهرستان‌ها.
 *
 * همان طراحی محبوب مالک: کادرهای کوچک سفید با سایهٔ عمیق رنگی، تیتر روی خود
 * کادر و برچسب دومِ کوچک (شاخهٔ آموزش = نخستین برچسب نوشته). بدون تصویر
 * زمینه. روی صفحهٔ اول و روی آرشیو دستهٔ آموزش‌ها (و برچسب‌هایش) استفاده می‌شود.
 *
 * @param WP_Post[]|null $posts نوشته‌ها (خالی = تازه‌ترین آموزش‌ها).
 * @param int            $limit سقف نوارها (۰ = بی‌سقف).
 * @return string HTML ('' = چیزی برای نمایش نیست).
 */
function sa_education_bars_html( $posts = null, $limit = 0 ) {
	$colors = function_exists( 'sa_bar_colors' ) ? (array) sa_bar_colors() : array( '#18558f' );
	if ( ! $colors ) {
		$colors = array( '#18558f' );
	}
	if ( null === $posts ) {
		$query = sa_education_latest_query( $limit > 0 ? $limit : 200 );
		$posts = $query ? $query->posts : array();
	}
	$posts = array_slice( (array) $posts, 0, $limit > 0 ? (int) $limit : null );
	$out   = '<div class="sa-bars sa-bars--edu" id="sa-edu-bars">';
	$count = 0;
	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$tags  = get_the_tags( $post->ID );
		$sub   = ( $tags && ! is_wp_error( $tags ) ) ? (string) $tags[0]->name : '';
		$title = get_the_title( $post );
		$url   = (string) get_permalink( $post );
		if ( function_exists( 'sa_entity_bar_html' ) ) {
			$out .= sa_entity_bar_html( $title, $sub, $url, $colors[ $count % count( $colors ) ], true );
		} else {
			$out .= '<a class="sa-bar" href="' . esc_url( $url ) . '"><b>' . esc_html( $title ) . '</b>'
				. ( '' !== $sub ? '<em class="fa">' . esc_html( $sub ) . '</em>' : '' ) . '</a>';
		}
		++$count;
	}
	$out .= '</div>';
	return $count > 0 ? $out : '';
}
