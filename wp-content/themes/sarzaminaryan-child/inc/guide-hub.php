<?php
/**
 * «آموزش سفر» — the fourth main section of the site.
 *
 * History: the menu entry pointed at https://sarzaminaryan.ir/travel-guide/ and that
 * address answered **404** (v2.23.3 fixed it with a virtual route), then the route
 * crashed on a `usort()` call (v2.23.4 → hotfix v2.23.5).
 *
 * v2.24.0 finishes the move the owner asked for («این بخش مقالات در قسمت نوشته‌های
 * اصلی وردپرس هستند»): the section is now its own content type (`inc/guide-cpt.php`)
 * whose **archive is** `/travel-guide/`, so this module keeps only what is left to do
 * around it:
 *
 *   1. one canonical URL for the section (`sa_2026_guide_url()`), used everywhere;
 *   2. the listing query of the archive — scoped to the section, never the whole site;
 *   3. the hub's own search, which stays inside the section instead of turning into a
 *      site-wide search page;
 *   4. the old posts page (`/blog/`) and the empty `travel-guide` category are sent to
 *      the hub with a 301, so two identical listings never compete;
 *   5. the category archives of the guides (`/category/…`) get the same 2026 listing
 *      (see `category.php`), so the section looks like the rest of the site.
 *
 * Before the owner runs the migration the archive lists the guides that are still
 * ordinary posts — it can never go empty, and no database row is written here.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical URL of the «آموزش سفر» section (the archive of the `guide` type).
 *
 * @return string
 */
function sa_2026_guide_url() {
	$url = '';
	if ( function_exists( 'sa_guide_type' ) && function_exists( 'get_post_type_archive_link' ) ) {
		$url = (string) get_post_type_archive_link( sa_guide_type() );
	}
	if ( '' === $url ) {
		$url = home_url( '/travel-guide/' );
	}

	return (string) apply_filters( 'sa_2026_guide_url', $url );
}

/**
 * Hub URL with query arguments.
 *
 * @param array<string,string|int> $args Query arguments.
 * @return string
 */
function sa_guide_url( array $args = array() ) {
	$url  = sa_2026_guide_url();
	$args = array_filter(
		$args,
		static function ( $value ) {
			return '' !== $value && null !== $value;
		}
	);

	return $args ? (string) add_query_arg( $args, $url ) : $url;
}

/**
 * Is the current request the hub — the archive itself or its on-hub search?
 *
 * The archive sets `sa_guide_hub` in `pre_get_posts()` (see below), so the answer stays
 * true even while the archive is still listing ordinary posts before the migration.
 *
 * @return bool
 */
function sa_guide_is_hub() {
	if ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query && $GLOBALS['wp_query']->get( 'sa_guide_hub' ) ) {
		return true;
	}

	return (bool) get_query_var( 'sa_guide_search' );
}

/**
 * Is the current request part of the «آموزش سفر» section?
 *
 * Used by the header nav to highlight the section on every guide page.
 *
 * @return bool
 */
function sa_guide_is_section() {
	if ( sa_guide_is_hub() || is_category() ) {
		return true;
	}
	if ( function_exists( 'sa_guide_types' ) && is_singular( sa_guide_types() ) ) {
		return true;
	}

	return is_singular( 'post' ) || ( is_home() && ! is_front_page() );
}

/**
 * Query var of the hub's own search.
 *
 * @param array<int,string> $vars Existing vars.
 * @return array<int,string>
 */
function sa_guide_query_vars( $vars ) {
	$vars[] = 'sa_guide_search';

	return $vars;
}
add_filter( 'query_vars', 'sa_guide_query_vars' );

/**
 * Build the listing query of the hub.
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function sa_guide_pre_get_posts( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	$requested = $query->get( 'post_type' );
	$requested = is_array( $requested ) ? (string) reset( $requested ) : (string) $requested;
	$archive   = ! empty( $query->is_post_type_archive ) && function_exists( 'sa_guide_type' ) && sa_guide_type() === $requested;
	$on_hub  = (bool) $query->get( 'sa_guide_search' );

	if ( ! $archive && ! $on_hub ) {
		return;
	}

	if ( $archive ) {
		// Mark the request as the hub: helpers and the template must not have to guess
		// it from `post_type`, which is switched below while the guides are still posts.
		$query->set( 'sa_guide_hub', 1 );
	}

	$query->set( 'post_status', 'publish' );
	$query->set( 'posts_per_page', (int) apply_filters( 'sa_guide_per_page', 12 ) );
	$query->set( 'ignore_sticky_posts', true );

	if ( ! function_exists( 'sa_guide_has_guides' ) || ! sa_guide_has_guides() ) {
		// Not migrated yet: the section's guides are ordinary posts, so list those —
		// limited to the section's categories — instead of an empty archive.
		$query->set( 'post_type', 'post' );
		$scoped = function_exists( 'sa_guide_category_ids' ) ? sa_guide_category_ids() : array();
		if ( $scoped ) {
			$query->set( 'category__in', $scoped );
		}

		return;
	}

	if ( $on_hub ) {
		// Search inside the hub: the section's own types, never the whole site.
		$query->set( 'post_type', sa_guide_types() );
	}
}
add_action( 'pre_get_posts', 'sa_guide_pre_get_posts' );

/**
 * Render the hub (archive or on-hub search) with its own template.
 *
 * @param string $template Template path.
 * @return string
 */
function sa_guide_template_include( $template ) {
	if ( ! sa_guide_is_hub() ) {
		return $template;
	}

	$own = SA_CHILD_DIR . 'template-guide.php';

	return is_readable( $own ) ? $own : $template;
}
add_filter( 'template_include', 'sa_guide_template_include', 20 );

/**
 * The old posts page (`/blog/`) lists exactly the same guides: one canonical index.
 *
 * Turn it off with `add_filter( 'sa_guide_redirect_posts_page', '__return_false' );`
 *
 * @return void
 */
function sa_guide_redirect_posts_page() {
	if ( is_admin() || sa_guide_is_hub() ) {
		return;
	}
	if ( ! is_home() || is_front_page() ) {
		return;
	}
	if ( ! apply_filters( 'sa_guide_redirect_posts_page', true ) ) {
		return;
	}

	wp_safe_redirect( sa_guide_url(), 301 );
	exit;
}
add_action( 'template_redirect', 'sa_guide_redirect_posts_page', 5 );

/**
 * The `travel-guide` category is a container with no posts of its own; while it is
 * empty it points at the hub instead of printing «چیزی پیدا نشد». As soon as the
 * owner files a post under it, the normal category archive takes over.
 *
 * @return void
 */
function sa_guide_redirect_empty_category() {
	if ( is_admin() || ! is_category() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	if ( 'travel-guide' !== $term->slug || (int) $term->count > 0 ) {
		return;
	}

	wp_safe_redirect( sa_guide_url(), 301 );
	exit;
}
add_action( 'template_redirect', 'sa_guide_redirect_empty_category', 5 );

/**
 * Document title of the hub and of its search.
 *
 * @param string $title Title.
 * @return string
 */
function sa_guide_document_title( $title ) {
	if ( ! sa_guide_is_hub() ) {
		return $title;
	}

	$search = trim( (string) get_query_var( 's' ) );
	$name   = 'آموزش سفر';
	if ( '' !== $search ) {
		$name = 'جست‌وجوی «' . $search . '» در آموزش سفر';
	}

	return $name . ' | ' . get_bloginfo( 'name' );
}
add_filter( 'pre_get_document_title', 'sa_guide_document_title', 20 );

/**
 * Label of one guide: its most specific category, never the WordPress placeholder.
 *
 * Kept as a function (not inline in the template) so it can be unit-tested, and
 * without `usort()`: sorting by reference is what threw the fatal error of v2.23.4
 * (`usort(): Argument #1 could not be passed by reference`).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function sa_guide_post_label( $post_id ) {
	$terms = get_the_category( (int) $post_id );
	if ( ! is_array( $terms ) || ! $terms ) {
		return 'آموزش سفر';
	}

	$pick = null;
	$depth = -1;
	foreach ( $terms as $term ) {
		if ( ! is_object( $term ) || ! isset( $term->slug, $term->name ) ) {
			continue;
		}
		if ( 'uncategorized' === $term->slug ) {
			continue;
		}
		$level = ! empty( $term->parent ) ? 1 : 0;
		// A child category (more specific) always beats a parent one.
		if ( $level > $depth ) {
			$pick  = $term;
			$depth = $level;
		}
	}

	return $pick ? (string) $pick->name : 'آموزش سفر';
}

/**
 * Categories that belong to the «آموزش سفر» section.
 *
 * The owner's note (۲۹ آبان): the guides are ordinary WordPress posts, so the hub
 * must not become a dump of every future post (news, culture, …). The listing is
 * therefore scoped to the section's own categories; the list is filterable and
 * falls back to "all posts" when none of those categories has content, so the page
 * can never go empty after a taxonomy change.
 *
 * @return array<int,int> Category IDs (empty = no scoping).
 */
function sa_guide_category_ids() {
	$slugs = (array) apply_filters(
		'sa_guide_category_slugs',
		array( 'travel-guide', 'camping-survival' )
	);

	$ids = array();
	foreach ( $slugs as $slug ) {
		$term = get_term_by( 'slug', (string) $slug, 'category' );
		if ( ! $term instanceof WP_Term || (int) $term->count < 1 ) {
			continue;
		}
		$ids[] = (int) $term->term_id;
		foreach ( (array) get_term_children( (int) $term->term_id, 'category' ) as $child_id ) {
			$ids[] = (int) $child_id;
		}
	}

	$ids = array_values( array_unique( array_filter( $ids ) ) );

	return (array) apply_filters( 'sa_guide_category_ids', $ids );
}

/**
 * Chips of the section: the categories that really hold guides.
 *
 * @param string $current Slug of the active category ('' on the hub).
 * @return array<int,array{slug:string,label:string,count:int,url:string,active:bool}>
 */
function sa_guide_topics( $current = '' ) {
	// Only WordPress' placeholder category is hidden: the site's default category is
	// a real guide category (the posts live there), so it must stay visible.
	$hidden  = array();
	$default = get_term_by( 'slug', 'uncategorized', 'category' );
	if ( $default instanceof WP_Term ) {
		$hidden[] = (int) $default->term_id;
	}

	$section = function_exists( 'sa_guide_category_ids' ) ? sa_guide_category_ids() : array();
	$terms   = get_categories(
		array(
			'hide_empty' => true,
			'exclude'    => $hidden,
			'include'    => $section ? $section : array(),
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

	if ( ! $terms && $section ) {
		// Scoping produced nothing usable (taxonomy changed): fall back to every
		// non-empty category instead of an empty chip row.
		$terms = get_categories(
			array(
				'hide_empty' => true,
				'exclude'    => $hidden,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
	}

	$topics = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$topics[] = array(
			'slug'   => (string) $term->slug,
			'label'  => (string) $term->name,
			'count'  => (int) $term->count,
			'url'    => (string) get_category_link( $term ),
			'active' => (string) $term->slug === $current,
		);
	}

	return $topics;
}

/**
 * Everything the listing template needs, whether it is the hub, a category archive
 * or the (legacy) posts page.
 *
 * @return array<string,mixed>
 */
function sa_guide_context() {
	$is_hub   = sa_guide_is_hub();
	// Before the migration the guides are ordinary posts, after it they are `guide`s:
	// the hero's counter must not drop to zero in between.
	$total    = function_exists( 'sa_guide_count' ) ? (int) sa_guide_count() : (int) wp_count_posts( 'post' )->publish;
	$found    = (int) $GLOBALS['wp_query']->found_posts;
	$search   = trim( (string) get_query_var( 's' ) );
	$current  = '';

	$title = 'آموزش سفر';
	$lead  = 'راهنماهای کاربردی برای برنامه‌ریزی، کمپینگ ایمن، کمک‌های اولیه و مهارت‌های جاده در ایران؛ نوشته‌شده برای سفر واقعی، نه فهرست کلیشه.';
	$badge = 'بخش آموزش سفر';

	if ( is_category() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$title   = (string) $term->name;
			$current = (string) $term->slug;
			$desc    = trim( wp_strip_all_tags( (string) $term->description ) );
			$lead    = '' !== $desc ? $desc : 'همهٔ راهنماهای این دسته، با تاریخ به‌روزرسانی و خلاصهٔ کاربردی.';
			$badge   = 'دستهٔ آموزش سفر';
			$found   = (int) $term->count;
		}
	}

	return array(
		'is_hub'  => $is_hub,
		'is_guide_type' => function_exists( 'sa_guide_type' ) ? sa_guide_type() : 'post',
		'is_cat'  => is_category(),
		'title'   => $title,
		'lead'    => $lead,
		'badge'   => $badge,
		'found'   => $found,
		'total'   => $total,
		'search'  => $search,
		'current' => $current,
		'topics'  => sa_guide_topics( $current ),
		'hub_url' => sa_2026_guide_url(),
	);
}

/**
 * Reading time in minutes from the word count (Persian registers ~180 wpm).
 *
 * @param int $post_id Post ID.
 * @return int
 */
function sa_guide_reading_time( $post_id ) {
	$content = (string) get_post_field( 'post_content', (int) $post_id );
	$words   = str_word_count( wp_strip_all_tags( $content ) );
	if ( $words < 40 ) {
		$words = max( 40, (int) ( mb_strlen( wp_strip_all_tags( $content ) ) / 5.5 ) );
	}

	return max( 1, (int) ceil( $words / 180 ) );
}
