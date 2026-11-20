<?php
/**
 * «راهنمای سفر» — the section's own content type (v2.24.0).
 *
 * Owner report (۳۰ آبان ۱۴۰۵): «نقص جدی، این بخش مقالات در قسمت نوشته‌های اصلی
 * وردپرس هستند» — the 67 travel guides lived in WordPress' ordinary `post` type, so
 * the hub could only ever be a filtered view of everything else the site publishes,
 * and every new news item was one forgotten category away from showing up as a guide.
 *
 * This module gives the section a real home:
 *
 *   1. `guide` — a public post type whose archive *is* `/travel-guide/` (same URL the
 *      section has always used) and whose singles live at `/travel-guide/<slug>/`.
 *   2. It has its own admin menu entry («راهنماهای سفر»), its own list table, its own
 *      editor screens — separate from «نوشته‌ها».
 *   3. It keeps the editorial tools the guides already relied on: categories, tags,
 *      excerpt, featured image, revisions, author, comments, custom fields, the SEO
 *      meta box, the table of contents and the article schema.
 *   4. Until the owner runs the migration (`inc/guide-migrate.php`), the archive keeps
 *      listing the guides that are still ordinary posts, so `/travel-guide/` can never
 *      go empty. Once guides exist, only guides are listed.
 *   5. Old addresses are never lost: `/<slug>/` (a migrated guide) 301s to
 *      `/travel-guide/<slug>/` through `sa_guide_legacy_redirect()`.
 *
 * The type is filterable end-to-end (`sa_guide_type`, `sa_guide_slug`,
 * `sa_guide_types`) so the whole section can be re-pointed without touching templates.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Machine name of the section's post type.
 *
 * @return string
 */
function sa_guide_type() {
	return (string) apply_filters( 'sa_guide_type', 'guide' );
}

/**
 * URL base (and archive slug) of the section.
 *
 * Kept as `travel-guide` on purpose: the address is in the header, the footer, the
 * front page and 67 indexed links.
 *
 * @return string
 */
function sa_guide_slug() {
	return (string) apply_filters( 'sa_guide_slug', 'travel-guide' );
}

/**
 * Post types that make up the section's listing.
 *
 * One element once the migration is done; templates still ask instead of hard-coding
 * so the type can be renamed.
 *
 * @return array<int,string>
 */
function sa_guide_types() {
	return array_values( array_filter( (array) apply_filters( 'sa_guide_types', array( sa_guide_type() ) ) ) );
}

/**
 * Is a post type part of the section?
 *
 * @param string $type Post type name.
 * @return bool
 */
function sa_is_guide_type( $type ) {
	return in_array( (string) $type, sa_guide_types(), true );
}

/**
 * Register the `guide` post type.
 *
 * @return void
 */
function sa_register_guide_type() {
	$type = sa_guide_type();
	$slug = sa_guide_slug();

	$labels = array(
		'name'                  => 'راهنماهای سفر',
		'singular_name'         => 'راهنمای سفر',
		'menu_name'             => 'راهنماهای سفر',
		'name_admin_bar'        => 'راهنمای سفر',
		'add_new'               => 'افزودن راهنما',
		'add_new_item'          => 'افزودن راهنمای سفر جدید',
		'new_item'              => 'راهنمای سفر جدید',
		'edit_item'             => 'ویرایش راهنمای سفر',
		'view_item'             => 'مشاهدهٔ راهنمای سفر',
		'view_items'            => 'مشاهدهٔ راهنماهای سفر',
		'all_items'             => 'همهٔ راهنماهای سفر',
		'search_items'          => 'جست‌وجوی راهنماهای سفر',
		'not_found'             => 'راهنمایی یافت نشد.',
		'not_found_in_trash'    => 'راهنمایی در زباله‌دان نیست.',
		'archives'              => 'آرشیو راهنماهای سفر',
		'attributes'            => 'ویژگی‌های راهنما',
		'insert_into_item'      => 'درج در راهنما',
		'uploaded_to_this_item' => 'بارگذاری‌شده برای این راهنما',
		'featured_image'        => 'تصویر شاخص راهنما',
		'set_featured_image'    => 'انتخاب تصویر شاخص',
		'remove_featured_image' => 'حذف تصویر شاخص',
		'use_featured_image'    => 'استفاده به‌عنوان تصویر شاخص',
		'item_published'        => 'راهنمای سفر منتشر شد.',
		'item_updated'          => 'راهنمای سفر به‌روزرسانی شد.',
		'items_list'            => 'فهرست راهنماهای سفر',
		'filter_items_list'     => 'فیلتر راهنماهای سفر',
	);

	register_post_type(
		$type,
		array(
			'labels'              => $labels,
			'description'         => 'راهنماهای آموزشی «آموزش سفر» — نوع‌محتوای مستقل، جدا از نوشته‌های وردپرس.',
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'sarzaminaryan',
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => false,
			'exclude_from_search' => false,
			'has_archive'         => $slug,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-book-alt',
			'menu_position'       => 6,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'comments', 'custom-fields' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'rewrite'             => array(
				'slug'       => $slug,
				'with_front' => false,
				'feeds'      => true,
				'pages'      => true,
			),
			'query_var'           => true,
			'delete_with_user'    => false,
		)
	);

	// Categories and tags are registered for `post` only in inc/taxonomies.php, so the
	// new type has to be attached to the same terms explicitly — the guides keep their
	// existing categories, they do not get a parallel taxonomy.
	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		if ( taxonomy_exists( $taxonomy ) ) {
			register_taxonomy_for_object_type( $taxonomy, $type );
		}
	}
}
add_action( 'init', 'sa_register_guide_type', 9 );

/**
 * How many guides are published.
 *
 * @return int
 */
function sa_guide_published_count() {
	$counts = wp_count_posts( sa_guide_type() );

	return isset( $counts->publish ) ? (int) $counts->publish : 0;
}

/**
 * Are there real guides yet (i.e. has the migration run)?
 *
 * @return bool
 */
function sa_guide_has_guides() {
	return sa_guide_published_count() > 0;
}

/**
 * Size of the section: guides once migrated, the still-unmigrated guides (ordinary
 * posts of the section's categories) before that.
 *
 * @return int
 */
function sa_guide_count() {
	$guides = sa_guide_published_count();
	if ( $guides > 0 ) {
		return $guides;
	}

	$posts = wp_count_posts( 'post' );
	$total = isset( $posts->publish ) ? (int) $posts->publish : 0;

	return $total;
}

/**
 * Old `/<slug>/` addresses of migrated guides answer 301 instead of 404.
 *
 * Only single-segment requests are considered (a feed URL such as `/<slug>/feed/` is
 * left alone), and only when the found post really is a guide, so nothing else on the
 * site changes behaviour.
 *
 * Off-switch: `add_filter( 'sa_guide_legacy_redirect', '__return_false' );`
 *
 * @return void
 */
function sa_guide_legacy_redirect() {
	if ( is_admin() || ! is_404() ) {
		return;
	}
	if ( ! apply_filters( 'sa_guide_legacy_redirect', true ) ) {
		return;
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	$path  = trim( (string) $path, '/' );
	$parts = array_values( array_filter( explode( '/', $path ) ) );

	// `/<slug>/` and `/<slug>/feed/` only: nothing else is a guide address.
	$tail = '';
	if ( 2 === count( $parts ) && 'feed' === $parts[1] ) {
		$tail  = 'feed/';
		$parts = array( $parts[0] );
	}
	if ( 1 !== count( $parts ) ) {
		return;
	}

	$slug = sanitize_title( urldecode( (string) $parts[0] ) );
	if ( '' === $slug ) {
		return;
	}

	$found = get_page_by_path( $slug, OBJECT, sa_guide_type() );
	if ( ! $found instanceof WP_Post || 'publish' !== $found->post_status ) {
		return;
	}

	$target = get_permalink( $found );
	if ( ! $target ) {
		return;
	}
	if ( '' !== $tail ) {
		$target = trailingslashit( $target ) . $tail;
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'sa_guide_legacy_redirect', 2 );

/**
 * Comments stay open on guides exactly like on posts.
 *
 * @param array<int,string> $types Comment-enabled post types.
 * @return array<int,string>
 */
function sa_guide_comment_types( $types ) {
	$types = (array) $types;
	foreach ( sa_guide_types() as $type ) {
		$types[] = $type;
	}

	return array_values( array_unique( $types ) );
}
add_filter( 'sa_comments_2026_types', 'sa_guide_comment_types' );

/**
 * Guides are articles: same Open Graph type as ordinary posts.
 *
 * @param string $type Open Graph type.
 * @param int    $id   Post ID.
 * @return string
 */
function sa_guide_og_type( $type, $id = 0 ) {
	if ( $id && sa_is_guide_type( (string) get_post_type( $id ) ) ) {
		return 'article';
	}

	return $type;
}
add_filter( 'sa_og_type', 'sa_guide_og_type', 10, 2 );

/**
 * Flush rewrite rules once when the type (or its slug) changes.
 *
 * Without this, `/travel-guide/` and `/travel-guide/<slug>/` would 404 on an existing
 * install until the owner re-saves the permalinks by hand.
 *
 * @return void
 */
function sa_guide_flush_rules() {
	$flag = 'sa_guide_type_version';
	$want = SA_CHILD_VERSION . '|' . sa_guide_slug();
	if ( get_option( $flag ) === $want ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( $flag, $want, false );
}
add_action( 'init', 'sa_guide_flush_rules', 21 );
