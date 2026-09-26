<?php
/**
 * Sarzamin Aryan parent theme setup.
 *
 * Reviewed fork of Quantum Pedia 1.0.0 (see CHANGELOG.md for the audit).
 * Project features live in the child theme `sarzaminaryan-child`.
 *
 * @package Sarzaminaryan
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SARZAMINARYAN_VERSION', '1.1.0' );

/**
 * Content width (used by oEmbed / media). Theme Check requirement.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 1200; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
}

/**
 * Load core files.
 */
require_once get_template_directory() . '/inc/customizer.php';
require_once get_template_directory() . '/inc/template-tags.php';
require_once get_template_directory() . '/inc/sanitization.php';
require_once get_template_directory() . '/inc/template-functions.php';

if ( ! function_exists( 'sarzaminaryan_setup' ) ) {
	/**
	 * Theme supports, menus, image sizes.
	 */
	function sarzaminaryan_setup() {
		load_theme_textdomain( 'sarzaminaryan', get_template_directory() . '/languages' );

		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'customize-selective-refresh-widgets' );

		// Hero/featured size (single views) + card size (loops). Two distinct sizes, no duplicates.
		set_post_thumbnail_size( 1200, 675, true );
		add_image_size( 'sarzaminaryan-card', 600, 338, true );

		add_theme_support(
			'custom-logo',
			array(
				'height'      => 80,
				'width'       => 240,
				'flex-height' => true,
				'flex-width'  => true,
			)
		);

		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
				'navigation-widgets',
			)
		);

		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		add_editor_style( array( 'assets/css/fonts.css', 'assets/css/editor-style.css' ) );

		register_nav_menus(
			array(
				'primary' => esc_html__( 'Primary Menu', 'sarzaminaryan' ),
				'footer'  => esc_html__( 'Footer Menu', 'sarzaminaryan' ),
			)
		);
	}
}
add_action( 'after_setup_theme', 'sarzaminaryan_setup' );

if ( ! function_exists( 'sarzaminaryan_widgets_init' ) ) {
	/**
	 * Register the sidebar.
	 */
	function sarzaminaryan_widgets_init() {
		register_sidebar(
			array(
				'name'          => esc_html__( 'Sidebar', 'sarzaminaryan' ),
				'id'            => 'sidebar-1',
				'description'   => esc_html__( 'Add widgets here.', 'sarzaminaryan' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}
}
add_action( 'widgets_init', 'sarzaminaryan_widgets_init' );

if ( ! function_exists( 'sarzaminaryan_assets' ) ) {
	/**
	 * Front-end assets. Fonts first (local Vazirmatn), then main, then RTL, then style.css.
	 */
	function sarzaminaryan_assets() {
		$uri = get_template_directory_uri();

		wp_enqueue_style( 'sarzaminaryan-fonts', $uri . '/assets/css/fonts.css', array(), SARZAMINARYAN_VERSION );
		wp_enqueue_style( 'sarzaminaryan-main', $uri . '/assets/css/main.css', array( 'sarzaminaryan-fonts' ), SARZAMINARYAN_VERSION );
		wp_style_add_data( 'sarzaminaryan-main', 'rtl', true ); // appends main-rtl.css after main.css when is_rtl().
		wp_enqueue_style( 'sarzaminaryan-style', get_stylesheet_uri(), array( 'sarzaminaryan-main' ), SARZAMINARYAN_VERSION );

		wp_enqueue_script( 'sarzaminaryan-main', $uri . '/assets/js/main.js', array(), SARZAMINARYAN_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'sarzaminaryan_assets' );

if ( ! function_exists( 'sarzaminaryan_preload_font' ) ) {
	/**
	 * Preload the regular weight to reduce FOUT (Google CWV: LCP/CLS).
	 */
	function sarzaminaryan_preload_font() {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_template_directory_uri() . '/assets/fonts/fa/Vazirmatn-Regular.woff2' )
		);
	}
}
add_action( 'wp_head', 'sarzaminaryan_preload_font', 1 );
