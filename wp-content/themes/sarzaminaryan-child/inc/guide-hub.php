<?php
/**
 * «آموزش سفر» — the fourth main section of the site.
 *
 * Owner report (۲۹ آبان ۱۴۰۵): the menu entry «آموزش سفر» pointed at
 * https://sarzaminaryan.ir/travel-guide/ and that address answered **404** — there
 * was no page and no post with that slug, while the section's URL was hard-coded in
 * three places (header nav, footer column, front-page pillar).
 *
 * What this module does, without writing anything to the database:
 *
 *   1. One canonical URL for the section (`sa_2026_guide_url()`), used everywhere.
 *   2. A virtual route at `/travel-guide/` (and `/travel-guide/page/2/`) that renders
 *      the real list of published guides with HTTP **200**, styled by the 2026 design.
 *   3. The old posts page (`/blog/`) and the empty `travel-guide` category are sent to
 *      the hub with a 301, so two identical listings never compete — both redirects
 *      stop automatically as soon as the owner creates a real page with that slug or
 *      puts posts inside the category.
 *   4. The category archives of the guides (`/category/…`) get the same 2026 listing
 *      (see `category.php`), so the section looks like the rest of the site.
 *
 * Nothing here is public storage: no page, no term, no option with content.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The owner's own page for the hub, when it exists and is published.
 *
 * @return WP_Post|false
 */
function sa_guide_page() {
	static $page = null;

	if ( null !== $page ) {
		return $page;
	}

	$found = get_page_by_path( 'travel-guide' );
	$page  = ( $found instanceof WP_Post && 'publish' === $found->post_status ) ? $found : false;

	return $page;
}

/**
 * Canonical URL of the «آموزش سفر» section.
 *
 * @return string
 */
function sa_2026_guide_url() {
	$page = sa_guide_page();
	$url  = $page ? (string) get_permalink( $page ) : home_url( '/travel-guide/' );

	return (string) apply_filters( 'sa_2026_guide_url', $url );
}

/**
 * Hub URL with query arguments.
 *
 * @param array<string,string|int> $args Query arguments.
 * @return string
 */
function sa_guide_url( array $args = array() ) {
	$url = sa_2026_guide_url();
	$args = array_filter(
		$args,
		static function ( $value ) {
			return '' !== $value && null !== $value;
		}
	);

	return $args ? (string) add_query_arg( $args, $url ) : $url;
}

/**
 * Is the current request the virtual hub?
 *
 * @return bool
 */
function sa_guide_is_hub() {
	return (bool) get_query_var( 'sa_guide_hub' );
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

	return is_singular( 'post' ) || ( is_home() && ! is_front_page() );
}

/**
 * Virtual route for /travel-guide/ and /travel-guide/page/N/.
 *
 * Registered only while the owner has no real page with that slug: their page must
 * always win.
 *
 * @return void
 */
function sa_guide_register_route() {
	if ( sa_guide_page() ) {
		return;
	}

	add_rewrite_rule( '^travel-guide/page/([0-9]{1,4})/?$', 'index.php?sa_guide_hub=1&sa_guide_page=$matches[1]', 'top' );
	add_rewrite_rule( '^travel-guide/?$', 'index.php?sa_guide_hub=1', 'top' );
}
add_action( 'init', 'sa_guide_register_route', 8 );

/**
 * Query vars of the route.
 *
 * @param array<int,string> $vars Existing vars.
 * @return array<int,string>
 */
function sa_guide_query_vars( $vars ) {
	$vars[] = 'sa_guide_hub';
	$vars[] = 'sa_guide_page';

	return $vars;
}
add_filter( 'query_vars', 'sa_guide_query_vars' );

/**
 * Flush the rewrite rules once per release (the route is new).
 *
 * @return void
 */
function sa_guide_maybe_flush() {
	$flag = 'sa_guide_route_version';
	if ( get_option( $flag ) === SA_CHILD_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( $flag, SA_CHILD_VERSION, false );
}
add_action( 'init', 'sa_guide_maybe_flush', 21 );

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
	if ( ! $query->get( 'sa_guide_hub' ) ) {
		return;
	}

	$query->set( 'post_type', 'post' );
	$query->set( 'post_status', 'publish' );
	$query->set( 'posts_per_page', (int) apply_filters( 'sa_guide_per_page', 12 ) );
	$query->set( 'ignore_sticky_posts', true );

	$paged = max( 1, (int) $query->get( 'sa_guide_page' ) );
	if ( $paged > 1 ) {
		$query->set( 'paged', $paged );
	}

	// The route is a listing, not the blog home: other code must not treat it as one.
	$query->is_home             = false;
	$query->is_archive          = true;
	$query->is_post_type_archive = false;
}
add_action( 'pre_get_posts', 'sa_guide_pre_get_posts' );

/**
 * Render the hub with its own template.
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
 * The virtual route answers 200 (core marks every empty query as a 404) and an
 * out-of-range page number goes back to the first page instead of a dead end.
 *
 * @return void
 */
function sa_guide_template_redirect() {
	if ( ! sa_guide_is_hub() ) {
		return;
	}

	// A real page always wins over the virtual route, even if an old rewrite rule is
	// still cached in the options table.
	$page = sa_guide_page();
	if ( $page ) {
		wp_safe_redirect( (string) get_permalink( $page ), 301 );
		exit;
	}

	global $wp_query;
	if ( $wp_query instanceof WP_Query ) {
		$wp_query->is_404 = false; // phpcs:ignore WordPress.WP.GlobalVariablesOverride

		$max   = (int) $wp_query->max_num_pages;
		$paged = max( 1, (int) get_query_var( 'sa_guide_page' ) );
		if ( $max > 0 && $paged > $max ) {
			wp_safe_redirect( sa_guide_url(), 301 );
			exit;
		}
	}

	status_header( 200 );
	nocache_headers();
}
add_action( 'template_redirect', 'sa_guide_template_redirect', 1 );

/**
 * The old posts page (`/blog/`) lists exactly the same guides: one canonical index.
 *
 * Turn it off with `add_filter( 'sa_guide_redirect_posts_page', '__return_false' );`
 * or by creating a real page with the `travel-guide` slug.
 *
 * @return void
 */
function sa_guide_redirect_posts_page() {
	if ( is_admin() || sa_guide_is_hub() || sa_guide_page() ) {
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
 * Document title of the hub.
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

	$terms = get_categories(
		array(
			'hide_empty' => true,
			'exclude'    => $hidden,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);

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
	$total    = (int) wp_count_posts( 'post' )->publish;
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
