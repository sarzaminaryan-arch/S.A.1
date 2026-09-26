<?php
/**
 * Performance (Core Web Vitals) — replaces the usual "optimisation plugin" basics.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable emoji scripts/styles (F17).
 */
function sa_perf_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	add_filter(
		'tiny_mce_plugins',
		function ( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
		}
	);
}
add_action( 'init', 'sa_perf_disable_emojis' );

/**
 * Front-end asset diet.
 */
function sa_perf_dequeue() {
	if ( is_admin() ) {
		return;
	}
	// Dashicons only for logged-in users (admin bar).
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
	// Classic-themes block CSS: keep the block library only where block content can appear.
	if ( sa_is_entity() || is_post_type_archive( sa_entity_types() ) || is_tax( array_keys( sa_taxonomies_config() ) ) || is_404() || is_search() ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'classic-theme-styles' );
		wp_dequeue_style( 'global-styles' );
	}
}
add_action( 'wp_enqueue_scripts', 'sa_perf_dequeue', 100 );

/**
 * Remove the embed script unless a post actually needs it (oEmbed of other WP sites is rare here).
 */
add_action(
	'wp_footer',
	function () {
		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			wp_deregister_script( 'wp-embed' );
		}
	},
	5
);

/**
 * Image hints: async decoding everywhere; lazy loading is core default; first image eager (core handles via fetchpriority).
 *
 * @param array $attr Attributes.
 * @return array
 */
add_filter(
	'wp_get_attachment_image_attributes',
	function ( $attr ) {
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}
);

/**
 * Bigger JPEG quality trade-off + WebP for generated sizes when the server supports it.
 */
add_filter( 'jpeg_quality', function () { return 82; } );
add_filter(
	'image_editor_output_format',
	function ( $formats ) {
		if ( function_exists( 'imagewebp' ) || ( class_exists( 'Imagick' ) && in_array( 'WEBP', Imagick::queryFormats(), true ) ) ) {
			$formats['image/jpeg'] = 'image/webp';
			$formats['image/png']  = 'image/webp';
		}
		return $formats;
	}
);
// Do not create the huge 2560px "scaled" copies for uploads larger than needed.
add_filter( 'big_image_size_threshold', function () { return 2000; } );

/**
 * Cached entity counts for the home page stats.
 *
 * @return int[]
 */
function sa_entity_counts() {
	$counts = get_transient( 'sa_entity_counts' );
	if ( false === $counts ) {
		$counts = array();
		foreach ( sa_entity_types() as $type ) {
			$c              = wp_count_posts( $type );
			$counts[ $type ] = isset( $c->publish ) ? (int) $c->publish : 0;
		}
		set_transient( 'sa_entity_counts', $counts, HOUR_IN_SECONDS );
	}
	return $counts;
}

/**
 * Heartbeat: slower in admin lists, off on the front end.
 */
add_filter(
	'heartbeat_settings',
	function ( $settings ) {
		$settings['interval'] = 60;
		return $settings;
	}
);
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_user_logged_in() ) {
			wp_deregister_script( 'heartbeat' );
		}
	},
	1
);

/**
 * Limit post revisions kept per post (DB weight) unless wp-config already decided.
 */
add_filter(
	'wp_revisions_to_keep',
	function ( $num ) {
		return defined( 'WP_POST_REVISIONS' ) ? $num : 10;
	}
);

/**
 * Resource hints: preload the child stylesheet's critical companion (fonts are preloaded by the parent).
 */
add_filter(
	'wp_resource_hints',
	function ( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			// No third parties are used; drop the default s.w.org hint.
			$urls = array_filter(
				$urls,
				function ( $u ) {
					return false === strpos( is_array( $u ) ? $u['href'] : $u, 's.w.org' );
				}
			);
		}
		return $urls;
	},
	10,
	2
);
