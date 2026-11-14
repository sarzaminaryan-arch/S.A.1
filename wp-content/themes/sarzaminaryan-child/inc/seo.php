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
 * Official bundled brand logo — the single source used by the header,
 * footer, Organization schema and the WordPress site icon.
 *
 * The files are intentionally kept inside the theme so a media-library
 * custom-logo or site-icon selection cannot leave an older logo in one
 * part of the site.
 *
 * The owner supplied the new wide lockup on 2026-11-12 ("Sarzamin
 * aryan" lettering beside the dotted Iran map). The artwork is a
 * single-colour mark on a fully transparent background, ratio 2.398:1,
 * so the theme ships two renderings of the same artwork:
 *
 *  - 'light' — white artwork for dark surfaces (header, footer)
 *  - 'dark'  — near-black artwork for light surfaces (schema)
 *
 * Square site icons cannot hold the wide lockup, so 'icon' variants use
 * the Iran map itself, cropped from the same artwork.
 *
 * @param string $variant 'light', 'dark' or 'icon'.
 * @param int    $size    Light: 960 or 480 wide. Dark: 1200 or 600
 *                        wide. Icon: 512, 192 or 96 square. Unknown
 *                        sizes fall back to the largest of the family.
 * @return array{url:string,width:int,height:int}
 */
function sa_brand_logo( $variant = 'light', $size = 0 ) {
	$logos = array(
		'light' => array(
			960 => array( 'file' => 'logo-light-960.webp', 'width' => 960, 'height' => 400 ),
			480 => array( 'file' => 'logo-light-480.webp', 'width' => 480, 'height' => 200 ),
		),
		'dark'  => array(
			1200 => array( 'file' => 'logo-dark-1200.webp', 'width' => 1200, 'height' => 500 ),
			600  => array( 'file' => 'logo-dark-600.webp', 'width' => 600, 'height' => 250 ),
		),
		'icon'  => array(
			512 => array( 'file' => 'icon-dark-512.webp', 'width' => 512, 'height' => 512 ),
			192 => array( 'file' => 'icon-dark-192.webp', 'width' => 192, 'height' => 192 ),
			96  => array( 'file' => 'icon-dark-96.webp', 'width' => 96, 'height' => 96 ),
		),
	);
	if ( ! isset( $logos[ $variant ] ) ) {
		$variant = 'light';
	}
	$set = $logos[ $variant ];
	if ( ! isset( $set[ $size ] ) ) {
		$size = max( array_keys( $set ) );
	}
	$logo = $set[ $size ];
	return array(
		'url'    => SA_CHILD_URI . 'assets/img/' . $logo['file'],
		'width'  => $logo['width'],
		'height' => $logo['height'],
	);
}

/**
 * URL of one official brand-logo variant.
 *
 * @param string $variant 'light', 'dark' or 'icon'.
 * @param int    $size    Variant size (see sa_brand_logo()).
 * @return string
 */
function sa_brand_logo_url( $variant = 'light', $size = 0 ) {
	$logo = sa_brand_logo( $variant, $size );
	return $logo['url'];
}

/**
 * White map icon for dark UI chrome (e.g. the WordPress admin bar).
 *
 * @param int $size 512, 192 or 96.
 * @return string
 */
function sa_brand_icon_light_url( $size = 192 ) {
	$files = array(
		512 => 'icon-light-512.webp',
		192 => 'icon-light-192.webp',
		96  => 'icon-light-96.webp',
	);
	if ( ! isset( $files[ $size ] ) ) {
		$size = 192;
	}
	return SA_CHILD_URI . 'assets/img/' . $files[ $size ];
}

/**
 * Always expose the bundled official logo as the site icon.
 *
 * This also covers the browser favicon, Apple touch icon, admin-bar site
 * icon and REST `site_icon_url` consumers, even when an older site icon
 * is still stored in the WordPress options. Site icons are square, so
 * they use the map-only icon cropped from the lockup artwork.
 *
 * @param string $url     Existing site-icon URL.
 * @param int    $size    Requested icon size.
 * @param int    $blog_id Blog ID (unused; the brand asset is site-wide).
 * @return string
 */
function sa_brand_site_icon_url( $url, $size = 512, $blog_id = 0 ) {
	unset( $url, $blog_id );
	if ( $size >= 256 ) {
		return sa_brand_logo_url( 'icon', 512 );
	}
	if ( $size >= 128 ) {
		return sa_brand_logo_url( 'icon', 192 );
	}
	return sa_brand_logo_url( 'icon', 96 );
}
add_filter( 'site_icon_url', 'sa_brand_site_icon_url', 20, 3 );

/**
 * Print the bundled site-icon tags when WordPress has no stored site icon.
 *
 * When a site icon is stored, core prints its own tags and the
 * site_icon_url filter above points them at the same bundled logo.
 */
function sa_brand_site_icon_fallback() {
	if ( get_option( 'site_icon' ) || ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) ) {
		return;
	}
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( sa_brand_logo_url( 'icon', 96 ) ) );
	printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( sa_brand_logo_url( 'icon', 192 ) ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( sa_brand_logo_url( 'icon', 192 ) ) );
	printf( '<meta name="msapplication-TileImage" content="%s">' . "\n", esc_url( sa_brand_logo_url( 'icon', 512 ) ) );
}
add_action( 'wp_head', 'sa_brand_site_icon_fallback', 99 );
add_action( 'admin_head', 'sa_brand_site_icon_fallback', 99 );

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
			$summary = sa_truncate_to_155( sa_summary( $id, 28 ) );
			return '' !== trim( (string) $summary ) ? $summary : sa_seo_city_fallback_description( $id );
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
 * v2.22.73 — توضیحِ متای جانشین برای شهرستانی که نه پیش‌درآمد دارد، نه توضیحِ
 * سفارشی، نه متن (مثلاً صفحه‌ای که همهٔ محتوایش از شناسنامه و «از تجربهٔ مسافرها» است).
 *
 * @param int $id City post ID.
 * @return string
 */
function sa_seo_city_fallback_description( $id ) {
	$name     = trim( (string) preg_replace( '~^شهرستان[\s\x{200C}]+~u', '', (string) get_the_title( $id ) ) );
	$province = '';
	$pid      = function_exists( 'sa_gallery_city_province_id' ) ? (int) sa_gallery_city_province_id( $id ) : 0;
	if ( $pid && function_exists( 'sa_province_name_for_post' ) ) {
		$province = (string) sa_province_name_for_post( $pid );
	}
	if ( '' === $name ) {
		return '';
	}
	$text = '' !== $province
		? sprintf( 'راهنمای سفر شهرستان %1$s در استان %2$s: شناسنامهٔ شهرستان، بهترین فصل سفر، وضعیت جاده و پارکینگ و جاهایی که مسافرها زیاد می‌روند.', $name, $province )
		: sprintf( 'راهنمای سفر شهرستان %s: شناسنامهٔ شهرستان، بهترین فصل سفر، وضعیت جاده و پارکینگ و جاهایی که مسافرها زیاد می‌روند.', $name );
	return sa_truncate_to_155( $text );
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
	$t = $t ? $t : $title;
	// v2.22.73 — فاصله‌های تکراری (مثلاً در «توضیح کوتاه» تنظیمات) در عنوان یکی می‌شوند.
	return trim( (string) preg_replace( '/[ \t\x{00A0}]{2,}/u', ' ', (string) $t ) );
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
	$title = trim( (string) preg_replace( '/[ \t\x{00A0}]{2,}/u', ' ', (string) $title ) );

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
	if ( is_author() && (int) get_query_var( 'paged' ) > 1 ) {
		$noindex = true;
	}
	// v2.22.50: آرشیوِ خالیِ موجودیت‌ها (سوغات/غذای محلی/مسیر سفر تا وقتی نوشته‌ای
	// منتشر نشده) ایندکس نمی‌شود؛ با اولین انتشار، خودش برمی‌گردد. این همان
	// تصمیم «بخش سوغات می‌ماند ولی پر می‌شود» است، بدون صفحهٔ خالیِ قابل ایندکس.
	if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( array( 'souvenir', 'local_food', 'travel_route' ) ) ) {
		$sa_pt = get_query_var( 'post_type' );
		$sa_pt = is_array( $sa_pt ) ? (string) reset( $sa_pt ) : (string) $sa_pt;
		if ( '' !== $sa_pt && function_exists( 'wp_count_posts' ) ) {
			$sa_counts = wp_count_posts( $sa_pt );
			if ( $sa_counts && 0 === (int) $sa_counts->publish ) {
				$noindex = true;
			}
		}
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
	$exclude = get_transient( 'sa_sitemap_thin_city' );
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
		set_transient( 'sa_sitemap_thin_city', $exclude, 12 * HOUR_IN_SECONDS );
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
	delete_transient( 'sa_sitemap_thin_city' );
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

/*
 * -------------------------------------------------------------------------
 * AI visibility (v2.22.48; AI-1 + AI-5 in docs/live-site/ai-visibility-plan.md)
 *
 * The theme is the single SEO source, so crawler policy lives here too:
 *
 *  - OAI-SearchBot (ChatGPT search) and ChatGPT-User (user-triggered fetch)
 *    are explicitly allowed, so the site can be found and cited in AI answers.
 *  - GPTBot (model-training crawler) is blocked — the owner's decision of
 *    2026-11-12 (AI-2). Search visibility does not depend on it.
 *  - The core sitemap URL (built by WordPress, pruned by this theme) is
 *    advertised explicitly.
 *
 * Like the rest of this file, everything steps aside when a dedicated SEO
 * plugin is active, so directives are never duplicated or in conflict.
 * -------------------------------------------------------------------------
 */

/**
 * Append the AI-crawler rules to the virtual robots.txt.
 *
 * Existing rules are preserved verbatim; only the extra groups and the
 * Sitemap line are appended. When the site is not public, core's blanket
 * "Disallow: /" is left untouched.
 *
 * @param string $output Existing robots.txt text.
 * @param bool   $public Whether the site is public (blog_public).
 * @return string
 */
function sa_ai_robots_txt( $output, $public ) {
	if ( ! $public || sa_seo_plugin_active() ) {
		return $output;
	}
	if ( false !== strpos( $output, 'OAI-SearchBot' ) ) {
		return $output; // Already declared (e.g. by a future core/plugin rule).
	}
	$extra = "User-agent: OAI-SearchBot\n"
		. "Allow: /\n"
		. "\n"
		. "User-agent: ChatGPT-User\n"
		. "Allow: /\n"
		. "\n"
		. "User-agent: GPTBot\n"
		. "Disallow: /\n"
		. "\n"
		. 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
	return rtrim( (string) $output ) . "\n\n" . $extra;
}
add_filter( 'robots_txt', 'sa_ai_robots_txt', 20, 2 );

/**
 * Markdown body of /llms.txt — a structured site introduction for AI tools.
 *
 * Kept deliberately small and factual: what the site is, its language, the
 * main sections with their real archive URLs, and the sitemap. Entity counts
 * are not hard-coded here because they change with every publish.
 *
 * @return string
 */
function sa_llms_txt_content() {
	$brand = sa_brand_name();
	$archive = function ( $type, $fallback ) {
		return function_exists( 'sa_archive_url' ) ? sa_archive_url( $type ) : home_url( $fallback );
	};
	$lines = array(
		'# ' . $brand . ' (Sarzamin Aryan)',
		'',
		'> دانشنامهٔ سفر ایران: راهنمای فارسیِ استان‌ها، شهرستان‌ها و دیدنی‌ها؛ همراه با شناسنامهٔ جغرافیاییِ هر شهرستان (جمعیت و سال سرشماری، مساحت، ارتفاع، همسایه‌ها و دیدنی‌ها).',
		'',
		'- زبان: فارسی (fa-IR)',
		'- نشانی: ' . home_url( '/' ),
		'',
		'## بخش‌های اصلی',
		'',
		'- [استان‌های ایران](' . $archive( 'province', '/province/' ) . '): مرور هر ۳۱ استان با نقشه، تقسیمات کشوری و دیدنی‌ها.',
		'- [شهرستان‌های ایران](' . $archive( 'city', '/city/' ) . '): شناسنامه و راهنمای سفر شهرستان‌ها.',
		'- [دیدنی‌های ایران](' . $archive( 'attraction', '/attraction/' ) . '): جاذبه‌های طبیعی، تاریخی و فرهنگی.',
	);
	// v2.22.73 — بخشی که هنوز هیچ محتوای منتشرشده‌ای ندارد معرفی نمی‌شود (پیوند به صفحهٔ خالی نه).
	foreach ( array(
		array( 'travel_route', '/route/', 'مسیرهای سفر', 'مسیرهای پیشنهادی سفر در ایران.' ),
		array( 'local_food', '/food/', 'غذاهای محلی', 'غذاهای بومی استان‌ها و شهرستان‌ها.' ),
		array( 'souvenir', '/souvenir/', 'سوغات', 'سوغات و صنایع‌دستی هر منطقه.' ),
	) as $sa_sec ) {
		if ( function_exists( 'sa_type_has_published' ) && ! sa_type_has_published( $sa_sec[0] ) ) {
			continue;
		}
		$lines[] = '- [' . $sa_sec[2] . '](' . $archive( $sa_sec[0], $sa_sec[1] ) . '): ' . $sa_sec[3];
	}
	$edu = function_exists( 'sa_education_url' ) ? (string) sa_education_url() : '';
	if ( '' !== $edu ) {
		$lines[] = '- [آموزش طبیعت‌گردی و بقا](' . $edu . '): راهنماهای کاربردی کمپینگ، بقا و سفر مسئولانه.';
	}
	$lines[] = '';
	$lines[] = '## نقشهٔ سایت';
	$lines[] = '';
	$lines[] = '- [نقشهٔ سایت XML](' . home_url( '/wp-sitemap.xml' ) . ')';
	$lines[] = '';
	return implode( "\n", $lines );
}

/**
 * Route /llms.txt to the theme (virtual file; nothing is written to disk).
 *
 * A top rewrite rule is registered on init — theme updates flush rewrite
 * rules via inc/activation.php — and the template_redirect fallback below
 * also matches the raw request path, so the URL works even before a flush.
 */
function sa_llms_txt_rewrite_rule() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?sa_llms_txt=1', 'top' );
}
add_action( 'init', 'sa_llms_txt_rewrite_rule' );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function sa_llms_txt_query_vars( $vars ) {
	$vars[] = 'sa_llms_txt';
	return $vars;
}
add_filter( 'query_vars', 'sa_llms_txt_query_vars' );

/**
 * Serve /llms.txt as plain text and stop.
 */
function sa_llms_txt_serve() {
	if ( sa_seo_plugin_active() ) {
		return; // A dedicated SEO plugin owns crawler-facing endpoints.
	}
	$serve = (bool) get_query_var( 'sa_llms_txt' );
	if ( ! $serve && isset( $_SERVER['REQUEST_URI'] ) ) {
		$path  = (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- path compared to a constant only.
		$serve = ( 'llms.txt' === trim( $path, '/' ) || 'llms.txt' === basename( $path ) );
	}
	if ( ! $serve ) {
		return;
	}
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo sa_llms_txt_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text file, built from escaped URLs and fixed strings.
	exit;
}
add_action( 'template_redirect', 'sa_llms_txt_serve', 1 );
