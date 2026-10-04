<?php
/**
 * Built-in SEO (Level 5): document title, meta description, canonical, robots, Open Graph / Twitter.
 * Steps aside automatically if a dedicated SEO plugin is ever activated.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Another SEO plugin active?
 *
 * @return bool
 */
function sa_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\Load' );
}

/**
 * Compute the SEO title for the current view.
 *
 * @return string
 */
function sa_seo_title() {
	$site = get_bloginfo( 'name' );
	if ( is_front_page() ) {
		$custom = is_singular() ? get_post_meta( get_queried_object_id(), 'sa_seo_title', true ) : '';
		if ( $custom ) {
			return $custom;
		}
		$desc = get_bloginfo( 'description' );
		return $desc ? $site . ' | ' . $desc : $site;
	}
	if ( is_singular() ) {
		$custom = get_post_meta( get_queried_object_id(), 'sa_seo_title', true );
		if ( $custom ) {
			return $custom;
		}
		$post = get_queried_object();
		$name = get_the_title( $post );
		if ( sa_is_entity( $post ) ) {
			$parent = null;
			if ( 'city' === $post->post_type ) {
				$parent = sa_get_parent( $post->ID, 'province' );
			} elseif ( in_array( $post->post_type, array( 'attraction', 'local_food', 'souvenir', 'accommodation' ), true ) ) {
				$parent = sa_get_parent( $post->ID, 'city' );
			}
			$type_word = array(
				'province'     => 'راهنمای سفر به استان',
				'city'         => 'راهنمای سفر به',
				'attraction'   => '',
				'travel_route' => 'مسیر سفر',
				'local_food'   => 'غذای محلی',
				'souvenir'     => 'سوغات',
			);
			$prefix    = isset( $type_word[ $post->post_type ] ) ? $type_word[ $post->post_type ] : '';
			$title     = trim( $prefix . ' ' . $name );
			if ( $parent ) {
				$title .= ' | ' . get_the_title( $parent );
			}
			return $title . ' | ' . $site;
		}
		return $name . ' | ' . $site;
	}
	if ( is_home() ) {
		$blog = (int) get_option( 'page_for_posts' );
		return ( $blog ? get_the_title( $blog ) : 'وبلاگ' ) . ' | ' . $site;
	}
	if ( is_post_type_archive() ) {
		return post_type_archive_title( '', false ) . ' ایران | ' . $site;
	}
	if ( is_tax( 'province_tax' ) ) {
		return 'راهنمای سفر به استان ' . single_term_title( '', false ) . ' | ' . $site;
	}
	if ( is_archive() ) {
		return wp_strip_all_tags( get_the_archive_title() ) . ' | ' . $site;
	}
	if ( is_search() ) {
		return 'جست‌وجو: ' . get_search_query() . ' | ' . $site;
	}
	if ( is_404() ) {
		return 'صفحه پیدا نشد | ' . $site;
	}
	if ( is_author() ) {
		return 'نوشته‌های ' . get_the_author() . ' | ' . $site;
	}
	if ( is_date() ) {
		return wp_strip_all_tags( get_the_archive_title() ) . ' | ' . $site;
	}
	return ''; // unknown view → let core build the title (must NOT call wp_get_document_title() here).
}

/**
 * Meta description for the current view.
 *
 * @return string
 */
function sa_seo_description() {
	if ( is_front_page() ) {
		$custom = is_singular() ? get_post_meta( get_queried_object_id(), 'sa_seo_description', true ) : '';
		if ( $custom ) {
			return $custom;
		}
		return get_theme_mod( 'sa_home_description', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و نمای برتر طبیعت‌های بکر و دیدنی‌های ایران با اطلاعات دقیق و به‌روز.' );
	}
	if ( is_singular() ) {
		$id     = get_queried_object_id();
		$custom = get_post_meta( $id, 'sa_seo_description', true );
		if ( $custom ) {
			return $custom;
		}
		return sa_summary( $id, 28 );
	}
	if ( is_home() && ! is_front_page() ) {
		$blog = (int) get_option( 'page_for_posts' );
		$text = $blog ? sa_summary( $blog, 28 ) : '';
		return $text ? $text : 'تازه‌ترین راهنماها، نکات سفر و اخبار گردشگری ایران در وبلاگ ' . get_bloginfo( 'name' ) . '.';
	}
	if ( is_front_page() ) {
		return get_theme_mod( 'sa_home_description', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و نمای برتر طبیعت‌های بکر و دیدنی‌های ایران با اطلاعات دقیق و به‌روز.' );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && $term->description ) {
			return wp_trim_words( wp_strip_all_tags( $term->description ), 28, '…' );
		}
		if ( is_tax( 'province_tax' ) ) {
			return sprintf( 'همه‌چیز درباره‌ی سفر به استان %s: شهرها، نمای برتر، تصاویر و راهنمای به‌روز.', $term->name );
		}
	}
	if ( is_post_type_archive() ) {
		$obj = get_queried_object();
		return sprintf( 'فهرست کامل %s ایران در سرزمین آریان، همراه با اطلاعات کلیدی و راهنمای بازدید.', $obj->labels->name );
	}
	return '';
}

/**
 * Canonical URL.
 *
 * @return string
 */
function sa_seo_canonical() {
	if ( is_singular() ) {
		$custom = get_post_meta( get_queried_object_id(), 'sa_canonical_url', true );
		if ( $custom ) {
			return $custom;
		}
		return get_permalink( get_queried_object_id() );
	}
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_home() ) {
		$blog = (int) get_option( 'page_for_posts' );
		$url  = $blog ? get_permalink( $blog ) : home_url( '/' );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( get_query_var( 'post_type' ) );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$url = get_term_link( get_queried_object() );
	} else {
		return '';
	}
	if ( is_wp_error( $url ) ) {
		return '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	return $paged > 1 ? trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged, 'paged' ) : $url;
}

/**
 * Print the title.
 *
 * @param string $title Title.
 * @return string
 */
function sa_seo_document_title( $title ) {
	if ( sa_seo_plugin_active() || is_admin() ) {
		return $title;
	}
	$t = sa_seo_title();
	return $t ? $t : $title;
}
add_filter( 'pre_get_document_title', 'sa_seo_document_title', 20 );

/**
 * Head output.
 */
function sa_seo_head() {
	if ( sa_seo_plugin_active() ) {
		return;
	}
	$desc  = sa_seo_description();
	$canon = sa_seo_canonical();
	$title = sa_seo_title();
	if ( '' === $title ) {
		$title = wp_get_document_title();
	}

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( wp_strip_all_tags( $desc ) ) . '">' . "\n";
	}
	if ( $canon ) {
		echo '<link rel="canonical" href="' . esc_url( $canon ) . '">' . "\n";
	}

	// Open Graph / Twitter.
	$og_title = $title;
	$og_desc  = $desc;
	$og_image = get_theme_mod( 'sa_default_og_image', '' );
	$og_type  = 'website';
	if ( is_singular() ) {
		$id       = get_queried_object_id();
		$og_title = get_post_meta( $id, 'sa_og_title', true ) ? get_post_meta( $id, 'sa_og_title', true ) : $og_title;
		$og_desc  = get_post_meta( $id, 'sa_og_description', true ) ? get_post_meta( $id, 'sa_og_description', true ) : $og_desc;
		$og_type  = 'post' === get_post_type( $id ) ? 'article' : ( sa_is_entity( $id ) ? 'place' : 'website' );
		if ( has_post_thumbnail( $id ) ) {
			$img      = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'sa-hero' );
			$og_image = $img ? $img[0] : $og_image;
		}
	}
	echo '<meta property="og:locale" content="fa_IR">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
	if ( $og_desc ) {
		echo '<meta property="og:description" content="' . esc_attr( wp_strip_all_tags( $og_desc ) ) . '">' . "\n";
	}
	if ( $canon ) {
		echo '<meta property="og:url" content="' . esc_url( $canon ) . '">' . "\n";
	}
	if ( $og_image ) {
		echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	} else {
		echo '<meta name="twitter:card" content="summary">' . "\n";
	}
	echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
	if ( is_singular( 'post' ) ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
	}
	echo '<meta name="theme-color" content="#0b2a4a">' . "\n";
}
add_action( 'wp_head', 'sa_seo_head', 2 );

/**
 * Remove core canonical (we print our own) and keep head lean.
 */
function sa_seo_head_cleanup() {
	if ( ! sa_seo_plugin_active() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}
add_action( 'init', 'sa_seo_head_cleanup' );

/**
 * Robots directives.
 *
 * @param array $robots Robots.
 * @return array
 */
function sa_seo_robots( $robots ) {
	if ( sa_seo_plugin_active() ) {
		return $robots;
	}
	$noindex = false;
	if ( is_search() || is_attachment() || is_date() || is_404() ) {
		$noindex = true;
	}
	if ( is_author() && (int) get_query_var( 'paged' ) > 1 ) {
		$noindex = true;
	}
	if ( is_tag() ) {
		$term = get_queried_object();
		if ( $term && $term->count < 3 ) {
			$noindex = true;
		}
	}
	if ( is_singular() && '1' === get_post_meta( get_queried_object_id(), 'sa_noindex', true ) ) {
		$noindex = true;
	}
	if ( $noindex ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	} else {
		$robots['index']             = true;
		$robots['follow']            = true;
		$robots['max-image-preview'] = 'large';
		$robots['max-snippet']       = '-1';
		$robots['max-video-preview'] = '-1';
	}
	return $robots;
}
add_filter( 'wp_robots', 'sa_seo_robots', 20 );

/**
 * Sitemaps: drop thin helper taxonomies and users.
 */
add_filter(
	'wp_sitemaps_taxonomies',
	function ( $taxonomies ) {
		unset( $taxonomies['travel_season'], $taxonomies['travel_budget'], $taxonomies['travel_duration'], $taxonomies['post_tag'] );
		return $taxonomies;
	}
);
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

/**
 * Image alt fallback: use the post title when an editor forgot the alt text (never empty alt on content images).
 *
 * @param array $attr Attributes.
 * @param WP_Post $attachment Attachment.
 * @return array
 */
function sa_seo_thumbnail_alt( $attr, $attachment ) {
	if ( empty( $attr['alt'] ) && in_the_loop() ) {
		$attr['alt'] = get_the_title();
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'sa_seo_thumbnail_alt', 10, 2 );
