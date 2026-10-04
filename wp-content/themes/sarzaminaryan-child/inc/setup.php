<?php
/**
 * Theme setup: assets, supports, menus, widget areas, search scope.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Text domain + supports.
 */
function sa_child_setup() {
	load_child_theme_textdomain( 'sarzaminaryan-child', SA_CHILD_DIR . 'languages' );

	add_theme_support( 'post-thumbnails', array_merge( array( 'post', 'page' ), sa_entity_types() ) );
	add_image_size( 'sa-hero', 1600, 700, array( 'center', 'top' ) ); // top-anchored: poster titles sit in the upper band (v1.0.3).
	add_image_size( 'sa-square', 480, 480, true );
	add_image_size( 'sa-gallery-large', 1600, 1600, false );
	add_image_size( 'sa-gallery-card', 720, 450, true );
	add_image_size( 'sa-gallery-thumb', 360, 225, true );

	register_nav_menus(
		array(
			'primary'   => 'منوی اصلی',
			'footer'    => 'منوی پابرگ',
			'secondary' => 'منوی موجودیت‌ها (زیر هدر)',
		)
	);

	// Custom logo for site (used in header, footer and Organization schema).
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 90,
			'width'       => 300,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
}
add_action( 'after_setup_theme', 'sa_child_setup', 11 );

/**
 * Widget areas.
 */
function sa_child_widgets() {
	register_sidebar(
		array(
			'name'          => 'کنار صفحه‌ی موجودیت‌ها',
			'id'            => 'sidebar-entity',
			'description'   => 'زیر جعبه‌ی اطلاعات کلیدی در صفحه‌ی استان/شهر/جاذبه/… نمایش داده می‌شود (مناسب تبلیغ یا بنر).',
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		)
	);
	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar(
			array(
				'name'          => sprintf( 'ستون %s پابرگ', sa_fa_digits( $i ) ),
				'id'            => 'footer-' . $i,
				'description'   => 'در صورت خالی بودن، محتوای پیش‌فرض پابرگ (درباره، لینک‌های سریع، شبکه‌ها) نمایش داده می‌شود.',
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
}
add_action( 'widgets_init', 'sa_child_widgets', 11 );

/**
 * Enqueue child assets after parent.
 */
function sa_child_assets() {
	wp_enqueue_style( 'sarzaminaryan-child', SA_CHILD_URI . 'assets/css/child.css', array( 'sarzaminaryan-style' ), SA_CHILD_VERSION );
	wp_enqueue_script( 'sarzaminaryan-child', SA_CHILD_URI . 'assets/js/child.js', array( 'sarzaminaryan-main' ), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular( array( 'province', 'city' ) ) ) {
		wp_enqueue_style( 'sarzaminaryan-gallery', SA_CHILD_URI . 'assets/css/gallery.css', array( 'sarzaminaryan-child' ), SA_CHILD_VERSION );
		wp_enqueue_script( 'sarzaminaryan-gallery', SA_CHILD_URI . 'assets/js/gallery.js', array(), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'sa_child_assets', 20 );

/**
 * Body classes: layout hints.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function sa_child_body_class( $classes ) {
	if ( is_front_page() || is_404() || is_search() || sa_is_entity() || is_post_type_archive( sa_entity_types() ) || is_tax( array_keys( sa_taxonomies_config() ) ) ) {
		$classes[] = 'no-sidebar';
	}
	if ( sa_is_entity() ) {
		$classes[] = 'sa-entity sa-entity--' . get_post_type();
	}
	return $classes;
}
add_filter( 'body_class', 'sa_child_body_class' );

/**
 * Search covers posts, pages and all entities; archives ordered by title.
 *
 * @param WP_Query $query Query.
 */
function sa_child_pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_search() ) {
		$query->set( 'post_type', array_merge( array( 'post', 'page' ), sa_entity_types() ) );
	}
	if ( $query->is_post_type_archive( sa_entity_types() ) || $query->is_tax( 'province_tax' ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
		$query->set( 'posts_per_page', 24 );
	}
}
add_action( 'pre_get_posts', 'sa_child_pre_get_posts' );

/**
 * Clean archive titles ("دسته: X" → "X") — SEO finding F16.
 *
 * @return string
 */
function sa_child_archive_title_prefix() {
	return '';
}
add_filter( 'get_the_archive_title_prefix', 'sa_child_archive_title_prefix' );

/**
 * Excerpt tweaks.
 */
add_filter(
	'excerpt_more',
	function () {
		return '…';
	}
);
add_filter(
	'excerpt_length',
	function () {
		return 32;
	},
	999
);
