<?php
/**
 * Built-in SEO (Level 5) + Phase 7 county rules: document title, meta description, canonical, robots, Open Graph / Twitter.
 * Steps aside automatically if a dedicated SEO plugin is ever activated.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand name — single source of truth for Phase 7 §6.
 *
 * @return string
 */
function sa_brand_name() {
	return 'سرزمین آریان';
}

/**
 * Ensure site title (blogname) and logo alt use the unified brand.
 * Filters option and bloginfo display.
 */
function sa_brand_filter_blogname( $value ) {
	// Only enforce when value is the old variant or empty; otherwise return unified brand
	// to guarantee consistency across title, logo alt, and Organization schema.
	if ( is_string( $value ) && '' !== trim( $value ) ) {
		// Normalize both old and new variants to canonical.
		$norm = trim( $value );
		// If value contains old brand with "ها", replace.
		if ( false !== mb_strpos( $norm, 'سرزمین آریایی' ) ) {
			return sa_brand_name();
		}
	}
	return $value;
}
add_filter( 'pre_option_blogname', 'sa_brand_filter_blogname' );
add_filter( 'option_blogname', 'sa_brand_filter_blogname' );

/**
 * Another SEO plugin active?
 *
 * @return bool
 */
function sa_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || class_exists( 'The_SEO_Framework\\Load' );
}

/**
 * Compute the SEO title for the current view.
 *
 * @return string
 */
function sa_seo_title() {
	$site = sa_brand_name();
	// Fallback to filtered blogname if somehow empty
	if ( '' === $site ) {
		$site = get_bloginfo( 'name' );
	}
	if ( is_front_page() ) {
		$custom = is_singular() ? get_post_meta( get_queried_object_id(), 'sa_seo_title', true ) : '';
		if ( $custom ) {
			return $custom;
		}
		$desc = get_bloginfo( 'description' );
		return $desc ? $site . ' | ' . $desc : $site;
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$post = get_queried_object();
		// Phase 7 §3: county title is strictly formatted, not custom
		if ( $post && 'city' === $post->post_type ) {
			$county_name = function_exists( 'sa_county_name' ) ? sa_county_name( $post->ID, false ) : get_the_title( $post );
			$county_name = trim( (string) $county_name );
			if ( '' === $county_name ) {
				$county_name = get_the_title( $post );
			}
			$province = sa_get_parent( $post->ID, 'province' );
			$province_name = '';
			if ( $province ) {
				$province_name = function_exists( 'sa_province_name_for_post' ) ? sa_province_name_for_post( $province, false ) : get_the_title( $province );
				$province_name = trim( (string) $province_name );
			}
			if ( '' === $province_name ) {
				$province_name = 'ایران';
			}
			// Exact format: «شهرستان [نام]؛ راهنمای سفر، جاذبه‌ها و تاریخ [استان] | سرزمین آریان»
			return sprintf( 'شهرستان %s؛ راهنمای سفر، جاذبه‌ها و تاریخ %s | %s', $county_name, $province_name, $site );
		}
		$custom = get_post_meta( $id, 'sa_seo_title', true );
		if ( $custom ) {
			return $custom;
		}
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
 * Lead text for a city post (for meta description, Phase 7 §3).
 *
 * @param int $post_id Post ID.
 * @return string
 */
function sa_get_city_lead( $post_id ) {
	$post_id = (int) $post_id;
	// Prefer excerpt (lead paragraph in county profile)
	if ( has_excerpt( $post_id ) ) {
		$ex = get_the_excerpt( $post_id );
		$ex = wp_strip_all_tags( strip_shortcodes( $ex ) );
		$ex = trim( (string) $ex );
		if ( '' !== $ex ) {
			return $ex;
		}
	}
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( '' === trim( $content ) ) {
		return '';
	}
	$text = strip_shortcodes( $content );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	return $text;
}

/**
 * Truncate to 155 chars with word boundary (Phase 7 §3).
 *
 * @param string $text Text.
 * @return string
 */
function sa_truncate_to_155( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return '';
	}
	// Normalize whitespace
	$text = preg_replace( '/\s+/u', ' ', $text );
	if ( mb_strlen( $text ) <= 155 ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, 155 );
	// Try to cut at last space to avoid half-word
	$last_space = mb_strrpos( $cut, ' ' );
	if ( false !== $last_space && $last_space > 100 ) {
		$cut = mb_substr( $cut, 0, $last_space );
	}
	return trim( $cut );
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
			// Enforce 155 max even on front
			$custom = sa_truncate_to_155( $custom );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		return get_theme_mod( 'sa_home_description', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و دیدنی‌های طبیعت ایران با اطلاعات دقیق و به‌روز.' );
	}
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$post = get_queried_object();
		// Phase 7 §3: for city, description from lead, max 155, ignore custom that may be outdated
		if ( $post && 'city' === $post->post_type ) {
			$lead = sa_get_city_lead( $id );
			if ( '' !== $lead ) {
				return sa_truncate_to_155( $lead );
			}
			// Fallback to custom if lead empty, but still truncate
			$custom = get_post_meta( $id, 'sa_seo_description', true );
			if ( $custom ) {
				return sa_truncate_to_155( $custom );
			}
			return sa_truncate_to_155( sa_summary( $id, 28 ) );
		}
		$custom = get_post_meta( $id, 'sa_seo_description', true );
		if ( $custom ) {
			return sa_truncate_to_155( $custom );
		}
		return sa_truncate_to_155( sa_summary( $id, 28 ) );
	}
	if ( is_home() && ! is_front_page() ) {
		$blog = (int) get_option( 'page_for_posts' );
		$text = $blog ? sa_summary( $blog, 28 ) : '';
		return $text ? sa_truncate_to_155( $text ) : 'تازه‌ترین راهنماها، نکات سفر و اخبار گردشگری ایران در وبلاگ ' . sa_brand_name() . '.';
	}
	if ( is_front_page() ) {
		return get_theme_mod( 'sa_home_description', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و دیدنی‌های طبیعت ایران با اطلاعات دقیق و به‌روز.' );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && $term->description ) {
			return sa_truncate_to_155( wp_trim_words( wp_strip_all_tags( $term->description ), 28, '…' ) );
		}
		if ( is_tax( 'province_tax' ) ) {
			return sprintf( 'همه‌چیز درباره‌ی سفر به استان %s: شهرها، دیدنی‌ها، تصاویر و راهنمای به‌روز.', $term->name );
		}
	}
	if ( is_post_type_archive() ) {
		$obj   = get_queried_object();
		$label = ( $obj && isset( $obj->labels->name ) ) ? trim( (string) $obj->labels->name ) : '';
		$label = (string) preg_replace( '/ها$/u', 'های', $label );
		return sprintf( 'فهرست کامل %s ایران در سرزمین آریان، همراه با اطلاعات کلیدی و راهنمای بازدید.', $label );
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
		// v2.15.0: تصویری که فایلش روی سرور نیست در og:image نمی‌آید (اشتراک‌گذاری تصویر شکسته).
		$sa_thumb_id = get_post_thumbnail_id( $id );
		$sa_thumb_ok = $sa_thumb_id && ( ! function_exists( 'sa_media_attachment_missing' ) || ! sa_media_attachment_missing( $sa_thumb_id ) );
		if ( $sa_thumb_ok ) {
			$img      = wp_get_attachment_image_src( $sa_thumb_id, function_exists( 'sa_cover_image_size' ) ? sa_cover_image_size( $id ) : 'sa-hero' );
			$og_image = $img ? $img[0] : $og_image;
		}
	}
	echo '<meta property="og:locale" content="fa_IR">' . "\n";
	echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( sa_brand_name() ) . '">' . "\n";
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
	// Author archives never index (single-author site; GSC 2026-10 reported 7 URLs).
	if ( is_author() ) {
		$noindex = true;
	}
	// Any archive sub-page (season/category/tag/CPT/home pagination): noindex,follow ONLY.
	// Never canonical+noindex together — see docs/seo/FIX-PLAN-2026-10-23.md (T8).
	if ( ( is_home() || is_archive() ) && (int) get_query_var( 'paged' ) > 1 ) {
		$noindex = true;
	}
	// Tag archives indexable only when they gather enough posts (owner rule: <5 merge/delete).
	if ( is_tag() ) {
		$term = get_queried_object();
		if ( $term && $term->count < 5 ) {
			$noindex = true;
		}
	}
	// Empty term archives (leftover attraction_type/province_tax terms) carry no value.
	if ( ( is_tax() || is_category() ) && ! is_post_type_archive() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->count ) && 0 === (int) $term->count ) {
			$noindex = true;
		}
	}
	// Helper CPT archives are navigational hubs, not landing pages.
	if ( is_post_type_archive( array( 'local_food', 'travel_route', 'souvenir' ) ) ) {
		$noindex = true;
	}
	if ( is_singular() && '1' === get_post_meta( get_queried_object_id(), 'sa_noindex', true ) ) {
		$noindex = true;
	}
	// Phase 7 §5: auto noindex for thin county pages
	if ( is_singular( 'city' ) ) {
		$pid = get_queried_object_id();
		if ( $pid && function_exists( 'sa_city_is_thin' ) && sa_city_is_thin( $pid ) ) {
			$noindex = true;
		}
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
		// Helper taxonomies, tag archives and the two OLD province/attraction archive
		// patterns must never enter the sitemap (GSC 2026-10: 31 /ostan/ + 13
		// /attraction-type/ URLs discovered from the Rank Math sitemap).
		unset(
			$taxonomies['travel_season'],
			$taxonomies['travel_budget'],
			$taxonomies['travel_duration'],
			$taxonomies['post_tag'],
			$taxonomies['province_tax'],
			$taxonomies['attraction_type'],
			$taxonomies['post_format']
		);
		return $taxonomies;
	}
);
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['attachment'] );
		return $types;
	}
);
add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args, $post_type ) {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		// Internal/utility pages stay out of the sitemap: the public stats dashboard
		// and the posts index page.
		$skip  = array();
		$pages = (array) get_option( 'sa_default_pages', array() );
		foreach ( array( 'dashboard', 'blog' ) as $key ) {
			if ( ! empty( $pages[ $key ] ) ) {
				$skip[] = (int) $pages[ $key ];
			}
		}
		/*
		 * برگهٔ policy فقط تا زمانی از سایت‌مپ بیرون می‌ماند که نامکش هنوز «policy»
		 * است (۱۴۰۵-۰۸-۰۳): پس از تغییر نام به «editorial-policy» (افزونهٔ
		 * sa-seo-db-fixes) این برگه یک مقصد کانونیک و ایندکس‌شدنی است و باید
		 * در سایت‌مپ باشد.
		 */
		if ( ! empty( $pages['policy'] ) && 'policy' === (string) get_post_field( 'post_name', (int) $pages['policy'] ) ) {
			$skip[] = (int) $pages['policy'];
		}
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page ) {
			$skip[] = $posts_page;
		}
		$skip = array_unique( array_filter( $skip ) );
		if ( $skip ) {
			$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $skip );
		}
		return $args;
	},
	16,
	2
);
add_filter(
	'wp_sitemaps_terms_query_args',
	function ( $args ) {
		$args['hide_empty'] = true;
		return $args;
	}
);

/**
 * robots.txt: complete and definitive for the post-Rank-Math era.
 *
 * Images stay crawlable (uploads are explicitly allowed); search feeds, WP feeds
 * and parametric junk stay out. No wildcard Disallow lines ever (they produced
 * the 404 patterns in the GSC export), and no Disallow on URLs we noindex via
 * meta — Google must be able to crawl them to see the noindex.
 *
 * @param string $output Core robots.txt body.
 * @return string
 */
function sa_seo_robots_txt( $output ) {
	// هستهٔ وردپرس خودش خط «Sitemap:» را در انتهای robots.txt چاپ می‌کند؛
	// افزودن دوبارهٔ آن باعث تکرار خط در خروجی می‌شد (رصد بازرس ۲۰۲۶-۱۰-۲۴).
	$extra  = "Allow: /wp-content/uploads/\n";
	$extra .= "Disallow: /feed/\n";
	$extra .= "Disallow: /?s=\n";
	$extra .= "Disallow: /?page_id=\n";
	return $output . $extra;
}
add_filter( 'robots_txt', 'sa_seo_robots_txt' );
add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	},
	10,
	2
);

/**
 * Phase 7 §5: exclude thin county pages from sitemap
 *
 * @param array  $args      Query args.
 * @param string $post_type Post type.
 * @return array
 */
function sa_seo_sitemap_exclude_thin_city( $args, $post_type ) {
	if ( 'city' !== $post_type ) {
		return $args;
	}
	// Build list of thin city IDs to exclude. Query all city IDs and filter.
	// Use transient to avoid heavy query on every sitemap request.
	$exclude = get_transient( 'sa_sitemap_thin_city_v2' );
	if ( false === $exclude ) {
		$exclude = array();
		$q = new WP_Query(
			array(
				'post_type'      => 'city',
				'post_status'    => 'publish',
				'posts_per_page' => 2000,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( (array) $q->posts as $pid ) {
			if ( function_exists( 'sa_city_is_thin' ) && sa_city_is_thin( $pid ) ) {
				$exclude[] = (int) $pid;
			}
		}
		set_transient( 'sa_sitemap_thin_city_v2', $exclude, 12 * HOUR_IN_SECONDS );
	}
	if ( $exclude ) {
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $exclude );
	}
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'sa_seo_sitemap_exclude_thin_city', 15, 2 );

/**
 * Flush sitemap thin cache on city save.
 */
function sa_sitemap_thin_flush_cache() {
	delete_transient( 'sa_sitemap_thin_city_v2' );
}
add_action( 'save_post', 'sa_sitemap_thin_flush_cache' );
add_action( 'deleted_post', 'sa_sitemap_thin_flush_cache' );
add_action( 'transition_post_status', 'sa_sitemap_thin_flush_cache' );
add_action( 'edited_term', 'sa_sitemap_thin_flush_cache' );

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
