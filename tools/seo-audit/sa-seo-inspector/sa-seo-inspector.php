<?php
/**
 * Plugin Name: SA SEO Inspector (one-shot)
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: گزارشِ یک‌بارمصرف و فقط‌خواندنی برای ممیزی sitemap/ایندکس «سرزمین آریان». هیچ داده‌ای را تغییر نمی‌دهد؛ فقط اطلاعات سایت را به یک فایل JSON خروجی می‌دهد تا بیرون از سایت تحلیل شود. پس از گرفتن گزارش، افزونه را حذف کنید.
 * Version:     1.0.0
 * Author:      Sarzamin Aryan / Arena agent
 * License:     GPL-2.0-or-later
 * Text Domain: sa-seo-inspector
 *
 * ---------------------------------------------------------------------------
 * READ-ONLY GUARANTEE
 *   This file never calls wp_insert_post / wp_update_post / wp_delete_post,
 *   never writes options, never flushes rewrites, never purges caches and
 *   never sends mail. The only outbound requests are self-requests
 *   (wp_remote_get to this site's own URLs) used to measure the real HTTP
 *   status / headers / head markup that a crawler sees.
 * ---------------------------------------------------------------------------
 *
 * @package SA_SEO_Inspector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_INSPECTOR_VERSION', '1.0.0' );
define( 'SA_INSPECTOR_CAP', 'manage_options' );

/* =========================================================================
 * 0. Helpers
 * ====================================================================== */

/**
 * Truncate long strings so the JSON stays small.
 *
 * @param string $text  Value.
 * @param int    $limit Max characters.
 * @return string
 */
function sa_insp_cut( $text, $limit = 1200 ) {
	$text = (string) $text;
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $limit ) {
		return mb_substr( $text, 0, $limit ) . '…[truncated]';
	}
	if ( strlen( $text ) > $limit * 3 ) {
		return substr( $text, 0, $limit * 3 ) . '…[truncated]';
	}
	return $text;
}

/**
 * Is an option name safe to export? (never ship credentials)
 *
 * @param string $name Option name.
 * @return bool
 */
function sa_insp_option_is_safe( $name ) {
	$name = strtolower( (string) $name );
	$bad  = array( 'license', 'token', 'secret', 'password', 'auth', 'api_key', 'apikey', 'private', 'salt', 'cred' );
	foreach ( $bad as $needle ) {
		if ( false !== strpos( $name, $needle ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Small word counter for Persian/English mixed text.
 *
 * @param string $html Content.
 * @return int
 */
function sa_insp_word_count( $html ) {
	$text = strip_shortcodes( (string) $html );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	if ( ! preg_match_all( '/[\p{L}\p{N}]+/u', $text, $m ) ) {
		return 0;
	}
	return count( $m[0] );
}

/* =========================================================================
 * 1. Environment
 * ====================================================================== */

/**
 * Core environment facts.
 *
 * @return array
 */
function sa_insp_environment() {
	global $wp_version, $wpdb;

	$theme   = wp_get_theme();
	$parent  = $theme->parent();
	$plugins = array();

	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		$data    = get_file_data( WP_PLUGIN_DIR . '/' . $file, array( 'Name' => 'Plugin Name', 'Version' => 'Version' ) );
		$plugins[] = array(
			'file'    => $file,
			'name'    => isset( $data['Name'] ) ? $data['Name'] : '',
			'version' => isset( $data['Version'] ) ? $data['Version'] : '',
		);
	}

	$out = array(
		'generated_at'         => current_time( 'c' ),
		'inspector_version'    => SA_INSPECTOR_VERSION,
		'home'                 => home_url( '/' ),
		'siteurl'              => site_url( '/' ),
		'wp_version'           => isset( $wp_version ) ? $wp_version : get_bloginfo( 'version' ),
		'php_version'          => PHP_VERSION,
		'db_prefix'            => isset( $wpdb ) ? $wpdb->prefix : '',
		'multisite'            => is_multisite(),
		'permalink_structure'  => (string) get_option( 'permalink_structure' ),
		'blog_public'          => (int) get_option( 'blog_public', 1 ),
		'show_on_front'        => (string) get_option( 'show_on_front' ),
		'page_on_front'        => (int) get_option( 'page_on_front' ),
		'page_for_posts'       => (int) get_option( 'page_for_posts' ),
		'posts_per_page'       => (int) get_option( 'posts_per_page' ),
		'theme'                => array(
			'stylesheet'      => get_stylesheet(),
			'version'         => $theme->get( 'Version' ),
			'parent'          => $parent ? $parent->get_stylesheet() : null,
			'parent_version'  => $parent ? $parent->get( 'Version' ) : null,
			'sa_child_version'=> defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : null,
			'sa_model_version'=> defined( 'SA_MODEL_VERSION' ) ? SA_MODEL_VERSION : null,
		),
		'active_plugins'       => $plugins,
		'active_sitewide'      => is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array(),
		'object_cache_external'=> wp_using_ext_object_cache(),
		'opcache'              => function_exists( 'opcache_get_status' ),
		'memory_limit'         => ini_get( 'memory_limit' ),
		'max_execution_time'   => ini_get( 'max_execution_time' ),
		'ssl'                  => is_ssl(),
		'timezone'             => (string) get_option( 'timezone_string' ),
		'server_software'      => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sa_insp_cut( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ), 200 ) : '',
		'litespeed'            => array(
			'class_exists' => class_exists( 'LiteSpeed_Cache' ) || class_exists( 'LiteSpeed\Cache' ),
			'defined'      => defined( 'LSCWP_V' ) ? LSCWP_V : null,
		),
	);

	// Registered post types / taxonomies with their rewrite bases — this is what
	// actually decides the live URL patterns (province vs ostan vs provinces).
	$types = array();
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $t ) {
		$types[ $t->name ] = array(
			'has_archive' => $t->has_archive ? (string) $t->has_archive : false,
			'rewrite'     => $t->rewrite ? (array) $t->rewrite : false,
			'publicly_queryable' => (bool) $t->publicly_queryable,
			'labels'      => isset( $t->labels->name ) ? $t->labels->name : '',
		);
	}
	$out['post_types'] = $types;

	$taxes = array();
	foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $x ) {
		$taxes[ $x->name ] = array(
			'rewrite'            => $x->rewrite ? (array) $x->rewrite : false,
			'publicly_queryable' => (bool) $x->publicly_queryable,
			'hierarchical'       => (bool) $x->hierarchical,
			'count'              => (int) wp_count_terms( array( 'taxonomy' => $x->name, 'hide_empty' => false ) ),
		);
	}
	$out['taxonomies'] = $taxes;

	// Rewrite rules that mention the geo/season patterns (keys only + value).
	$rules    = (array) get_option( 'rewrite_rules', array() );
	$filtered = array();
	foreach ( $rules as $regex => $target ) {
		if ( preg_match( '/province|ostan|city|attraction|season|food|route|souvenir/i', (string) $regex ) ) {
			$filtered[ $regex ] = $target;
		}
	}
	$out['rewrite_rules_total']   = count( $rules );
	$out['rewrite_rules_entity']  = $filtered;

	return $out;
}

/* =========================================================================
 * 2. SEO plugin ownership
 * ====================================================================== */

/**
 * Which SEO plugin owns head output, and what are its robots/sitemap settings?
 *
 * @return array
 */
function sa_insp_seo_plugins() {
	$out = array(
		'detected' => array(
			'rank_math'      => defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : null,
			'rank_math_pro'  => defined( 'RANK_MATH_PRO_VERSION' ) ? RANK_MATH_PRO_VERSION : null,
			'yoast'          => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : null,
			'aioseo'         => defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : null,
			'seopress'       => defined( 'SEOPRESS_VERSION' ) ? SEOPRESS_VERSION : null,
			'tsf'            => class_exists( 'The_SEO_Framework\Load' ),
			'theme_seo_head' => function_exists( 'sa_seo_head' ),
			'theme_steps_aside' => function_exists( 'sa_seo_plugin_active' ) ? sa_seo_plugin_active() : null,
		),
	);

	// Rank Math: modules + the options that decide sitemap membership and robots.
	global $wpdb;
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'rank_math%' ORDER BY option_name" );
	$opts  = array();
	foreach ( (array) $names as $name ) {
		if ( ! sa_insp_option_is_safe( $name ) ) {
			continue;
		}
		$value = get_option( $name );
		if ( is_array( $value ) || is_object( $value ) ) {
			$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		$opts[ $name ] = sa_insp_cut( (string) $value, 3000 );
	}
	$out['rank_math_options'] = $opts;
	$out['rank_math_option_names_skipped'] = count( (array) $names ) - count( $opts );

	// Theme-side SEO option/meta footprint (so we can see the double ownership).
	$out['theme_options'] = array(
		'sa_default_pages' => get_option( 'sa_default_pages', array() ),
		'sa_home_description' => (string) get_theme_mod( 'sa_home_description', '' ),
	);

	return $out;
}

/* =========================================================================
 * 3. robots.txt, physical root files, .htaccess
 * ====================================================================== */

/**
 * robots.txt (served + physical file), stray sitemap files, .htaccess.
 *
 * @return array
 */
function sa_insp_robots_and_files() {
	$out = array();

	// What a crawler actually receives for /robots.txt.
	$res = wp_remote_get(
		home_url( '/robots.txt' ),
		array( 'timeout' => 20, 'redirection' => 0, 'sslverify' => false, 'user-agent' => 'SA-SEO-Inspector/1.0' )
	);
	$out['robots_served'] = array(
		'status' => is_wp_error( $res ) ? 'error: ' . $res->get_error_message() : (int) wp_remote_retrieve_response_code( $res ),
		'body'   => is_wp_error( $res ) ? '' : sa_insp_cut( wp_remote_retrieve_body( $res ), 4000 ),
	);

	// Physical files in the WordPress root that can override the generated ones.
	$candidates = array(
		'robots.txt', '.htaccess', 'sitemap.xml', 'sitemap.xml.gz', 'sitemap_index.xml',
		'wp-sitemap.xml', 'sitemap.txt', 'sitemap.html', 'google_sitemap.xml', 'post-sitemap.xml',
	);
	$files = array();
	foreach ( $candidates as $rel ) {
		$abs = untrailingslashit( ABSPATH ) . '/' . $rel;
		if ( file_exists( $abs ) ) {
			$files[ $rel ] = array(
				'size'      => (int) filesize( $abs ),
				'mtime'     => date( 'c', (int) filemtime( $abs ) ),
				'readable'  => (bool) is_readable( $abs ),
				'content'   => is_readable( $abs ) && filesize( $abs ) < 400000 ? sa_insp_cut( (string) file_get_contents( $abs ), 20000 ) : '[too large or unreadable]',
			);
		}
	}
	// Any other *.xml sitting in the root (a hand-written sitemap would show up here).
	$root_xml = array();
	foreach ( (array) glob( untrailingslashit( ABSPATH ) . '/*.xml' ) as $path ) {
		$root_xml[] = array( 'name' => basename( $path ), 'size' => (int) filesize( $path ), 'mtime' => date( 'c', (int) filemtime( $path ) ) );
	}
	$out['root_files']    = $files;
	$out['root_xml_glob'] = $root_xml;

	return $out;
}

/* =========================================================================
 * 4. Sitemaps — every generator, every URL
 * ====================================================================== */

/**
 * Extract <loc> values from a sitemap XML body.
 *
 * @param string $body XML.
 * @return string[]
 */
function sa_insp_extract_locs( $body ) {
	$locs = array();
	if ( preg_match_all( '~<loc>\s*(.*?)\s*</loc>~is', (string) $body, $m ) ) {
		foreach ( $m[1] as $loc ) {
			$loc = trim( html_entity_decode( $loc, ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $loc ) {
				$locs[] = $loc;
			}
		}
	}
	return $locs;
}

/**
 * Fetch a URL and report status + body.
 *
 * @param string $url URL.
 * @return array
 */
function sa_insp_fetch( $url ) {
	$res = wp_remote_get(
		$url,
		array(
			'timeout'       => 25,
			'redirection'   => 0,
			'sslverify'     => false,
			'user-agent'    => 'SA-SEO-Inspector/1.0',
			'headers'       => array( 'Cache-Control' => 'no-cache' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return array( 'url' => $url, 'status' => 0, 'error' => $res->get_error_message(), 'body' => '', 'headers' => array() );
	}
	$headers = wp_remote_retrieve_headers( $res );
	$flat    = array();
	if ( $headers instanceof ArrayAccess || is_array( $headers ) ) {
		foreach ( (array) $headers as $k => $v ) {
			$flat[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
		}
	}
	return array(
		'url'     => $url,
		'status'  => (int) wp_remote_retrieve_response_code( $res ),
		'error'   => '',
		'body'    => (string) wp_remote_retrieve_body( $res ),
		'headers' => $flat,
	);
}

/**
 * Walk every sitemap generator and dump all URLs they contain.
 *
 * @return array
 */
function sa_insp_sitemaps() {
	$home      = untrailingslashit( home_url() );
	$candidates = array(
		'/wp-sitemap.xml',      // WordPress core
		'/sitemap_index.xml',   // Rank Math / Yoast index
		'/sitemap.xml',         // physical or plugin
	);

	$out = array( 'core_sitemaps_enabled' => (bool) get_option( 'blog_public' ), 'sources' => array() );

	// Is core's sitemap provider switched off (Rank Math does that on install)?
	$out['wp_sitemaps_enabled_filter'] = apply_filters( 'wp_sitemaps_enabled', true );

	foreach ( $candidates as $path ) {
		$fetched = sa_insp_fetch( $home . $path );
		$entry   = array(
			'path'    => $path,
			'status'  => $fetched['status'],
			'error'   => $fetched['error'],
			'location' => isset( $fetched['headers']['location'] ) ? $fetched['headers']['location'] : '',
			'is_index' => false,
			'children' => array(),
			'urls'    => array(),
			'url_count' => 0,
		);

		if ( 200 !== $fetched['status'] || '' === $fetched['body'] ) {
			$out['sources'][] = $entry;
			continue;
		}

		$locs = sa_insp_extract_locs( $fetched['body'] );

		if ( false !== strpos( $fetched['body'], '<sitemapindex' ) ) {
			$entry['is_index'] = true;
			foreach ( array_slice( $locs, 0, 60 ) as $child ) {
				$sub        = sa_insp_fetch( $child );
				$child_rows = 200 === $sub['status'] ? sa_insp_extract_locs( $sub['body'] ) : array();
				$entry['children'][] = array(
					'url'       => $child,
					'status'    => $sub['status'],
					'location'  => isset( $sub['headers']['location'] ) ? $sub['headers']['location'] : '',
					'url_count' => count( $child_rows ),
					'urls'      => $child_rows,
				);
				$entry['urls'] = array_merge( $entry['urls'], $child_rows );
			}
		} else {
			$entry['urls'] = $locs;
		}

		$entry['url_count']  = count( $entry['urls'] );
		$entry['urls']       = array_values( array_unique( $entry['urls'] ) );
		$entry['url_count']  = count( $entry['urls'] );
		$out['sources'][]    = $entry;
	}

	// Rank Math-specific: which post types / taxonomies it puts in the sitemap.
	$out['rank_math_sitemap_settings'] = array(
		'rank_math_sitemap_post_types' => get_option( 'rank_math_sitemap_post_types', array() ),
		'rank_math_sitemap_taxonomies' => get_option( 'rank_math_sitemap_taxonomies', array() ),
		'rank_math_sitemap_items_per_page' => get_option( 'rank_math_sitemap_items_per_page', null ),
	);

	// Core sitemap filters the theme registers (so we can compare intent vs reality).
	$out['theme_core_sitemap_filters'] = array(
		'wp_sitemaps_taxonomies_now' => array_keys( (array) apply_filters( 'wp_sitemaps_taxonomies', get_taxonomies( array( 'public' => true ) ) ) ),
	);

	return $out;
}

/* =========================================================================
 * 5. URL probes — status, redirects, robots, canonical
 * ====================================================================== */

/**
 * Parse the <head> of an HTML document for the signals Google uses.
 *
 * @param string $body HTML.
 * @return array
 */
function sa_insp_head_signals( $body ) {
	$body = (string) $body;
	$head = $body;
	if ( preg_match( '~<head[^>]*>(.*?)</head>~is', $body, $m ) ) {
		$head = $m[1];
	}

	$canonical = '';
	if ( preg_match( '~<link[^>]+rel=["\']?canonical["\']?[^>]*>~i', $head, $m ) ) {
		if ( preg_match( '~href=["\']([^"\']+)["\']~i', $m[0], $hm ) ) {
			$canonical = html_entity_decode( $hm[1], ENT_QUOTES, 'UTF-8' );
		}
	}

	$robots = '';
	if ( preg_match( '~<meta[^>]+name=["\']?robots["\']?[^>]+content=["\']?([^"\'/>]+)~i', $head, $m ) ) {
		$robots = trim( $m[1] );
	} elseif ( preg_match( '~<meta[^>]+content=["\']?([^"\'/>]+)["\']?[^>]+name=["\']?robots["\']?~i', $head, $m ) ) {
		$robots = trim( $m[1] );
	}

	$title = '';
	if ( preg_match( '~<title[^>]*>(.*?)</title>~is', $head, $m ) ) {
		$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
	}

	$types = array();
	if ( preg_match_all( '~"@type"\s*:\s*"([^"]+)"~', $body, $m ) ) {
		$types = array_values( array_unique( $m[1] ) );
	}

	$h1 = preg_match_all( '~<h1[\s>]~i', $body );

	return array(
		'title'          => sa_insp_cut( $title, 300 ),
		'meta_robots'    => $robots,
		'canonical'      => $canonical,
		'h1_count'       => (int) $h1,
		'jsonld_types'   => $types,
		'word_count'     => sa_insp_word_count( $body ),
		'bytes'          => strlen( $body ),
	);
}

/**
 * Probe one URL like a crawler: no redirect following, capture head signals.
 *
 * @param string $url         Absolute URL.
 * @param bool   $bypass_cache Append a cache-busting query arg.
 * @return array
 */
function sa_insp_probe( $url, $bypass_cache = true ) {
	$probe_url = $url;
	if ( $bypass_cache ) {
		$probe_url = add_query_arg( 'sa_nc', '1', $url );
	}

	$res = wp_remote_get(
		$probe_url,
		array(
			'timeout'     => 25,
			'redirection' => 0,
			'sslverify'   => false,
			'user-agent'  => 'Mozilla/5.0 (compatible; SA-SEO-Inspector/1.0)',
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		)
	);

	$row = array(
		'url'        => $url,
		'probed'     => $probe_url,
		'status'     => 0,
		'error'      => '',
		'location'   => '',
		'x_robots_tag' => '',
		'content_type' => '',
		'elapsed_ms' => 0,
	);

	$start = microtime( true );
	if ( is_wp_error( $res ) ) {
		$row['error']       = $res->get_error_message();
		$row['elapsed_ms']  = (int) round( ( microtime( true ) - $start ) * 1000 );
		return $row;
	}
	$row['elapsed_ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );

	$headers = wp_remote_retrieve_headers( $res );
	$flat    = array();
	foreach ( (array) $headers as $k => $v ) {
		$flat[ strtolower( (string) $k ) ] = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
	}

	$row['status']       = (int) wp_remote_retrieve_response_code( $res );
	$row['location']     = isset( $flat['location'] ) ? $flat['location'] : '';
	$row['x_robots_tag'] = isset( $flat['x-robots-tag'] ) ? $flat['x-robots-tag'] : '';
	$row['content_type'] = isset( $flat['content-type'] ) ? sa_insp_cut( $flat['content-type'], 120 ) : '';

	if ( 200 === $row['status'] && false !== strpos( $row['content_type'], 'html' ) ) {
		$row['head'] = sa_insp_head_signals( wp_remote_retrieve_body( $res ) );
	}

	return $row;
}

/**
 * The default probe list: every URL pattern the Search Console export flagged.
 *
 * @return string[]
 */
function sa_insp_default_probe_urls() {
	$home = untrailingslashit( home_url() );

	$paths = array(
		// hubs + special pages
		'/', '/province/', '/city/', '/attraction/', '/food/', '/route/', '/souvenir/', '/blog/',
		'/dashboard/', '/policy/', '/privacy/', '/editorial-policy/', '/about/', '/contact/',
		'/page/2/', '/page/4/', '/feed/', '/wp-sitemap.xml', '/sitemap_index.xml', '/sitemap.xml',
		// WordPress leftovers reported as 404 / valid
		'/%D8%B3%D9%84%D8%A7%D9%85-%D8%AF%D9%86%DB%8C%D8%A7/', '/category/%D8%AF%D8%B3%D8%AA%D9%87%D8%A8%D9%86%D8%AF%DB%8C-%D9%86%D8%B4%D8%AF%D9%87/',
		'/?page_id=6', '/?page_id=7', '/?page_id=8', '/?page_id=9',
		// wildcard entries Google reported as 404 (must NOT be in any sitemap)
		'/*', '/wp-content/uploads/*', '/wp-content/themes/sarzaminaryan-child/*', '/wp-*.php',
		// broken relative citation links
		'/city/fereydan/W_FER', '/city/chadegan/W_CHA', '/city/kuhpayeh/W_KUH', '/city/golpayegan/W_GOL',
		// author + tag archives
		'/author/sarzaminaryan/', '/author/sarzaminaryan/page/2/',
		'/tag/campfire/', '/tag/field-skills/', '/tag/diy-outdoor/', '/tag/water-rescue/',
		'/tag/campsite-shelter/', '/tag/pro-gear/', '/tag/first-aid/', '/tag/camping-gear/', '/tag/wildlife/',
		'/tag/diy-outdoor/page/2/',
		// season taxonomy + deep pagination
		'/season/spring/', '/season/summer/', '/season/autumn/', '/season/winter/',
		'/season/spring/page/2/', '/season/spring/page/10/', '/season/spring/page/42/',
		'/season/autumn/page/2/', '/season/autumn/page/14/', '/season/winter/page/2/', '/season/winter/page/8/',
		// province patterns (CPT vs taxonomy vs third alias)
		'/province/tehran/', '/province/fars/', '/province/khuzestan/', '/province/isfahan/', '/province/markazi/',
		'/ostan/tehran/', '/ostan/fars/', '/ostan/khuzestan/', '/ostan/hamadan/', '/ostan/markazi/', '/ostan/semnan/',
		'/provinces/ardabil/', '/provinces/zanjan/', '/provinces/mazandaran/', '/provinces/tehran/',
		// duplicate city pairs
		'/city/torbat-jam/', '/city/torbatjam/', '/city/dorud/', '/city/dorud-city/',
		'/city/bushehr-city/', '/city/bushehr-county/', '/city/borazjan/', '/city/dashtestan/',
		'/city/ejrud/', '/city/ijrud/', '/city/kharadere/', '/city/khorramdarreh/',
		'/city/isfahan/', '/city/isfahan-city/', '/city/galikash/', '/city/galikesh/',
		// -city / -county suffixed slugs
		'/city/tehran-city/', '/city/ahvaz-city/', '/city/ardabil-city/', '/city/ilam-city/',
		'/city/qazvin-city/', '/city/qom-city/', '/city/semnan-city/', '/city/zanjan-city/',
		// key cities
		'/city/shiraz/', '/city/mashhad/', '/city/tabriz/', '/city/kuhsorkh/', '/city/kashmar/', '/city/tabas/',
		// numeric-slug attractions and their clean twins
		'/attraction/6976-aramgah-ghasm-anvar/', '/attraction/qasem-anvar-shrine/',
		'/attraction/6619-ghlh-dkhtr-bshrvih/', '/attraction/ghaleh-dokhtar-boshruyeh/',
		'/attraction/23328-sar-agha-seyed-village/', '/attraction/sar-agha-seyed-village/',
		'/attraction/6789-bagh-glshn-tbs/', '/attraction/bagh-golshan-tabas/',
		'/attraction/308149-attraction-tabas/', '/attraction/tabas-attractions/',
		'/attraction/9235-tang-e-sayad-national-park/', '/attraction/tang-e-sayad-national-park/',
		'/attraction/26950-kvir-rig-jn/', '/attraction/rig-e-jenn-desert/',
		'/attraction/dorud-city/', '/attraction/dorud/', '/attraction/masouleh/', '/attraction/fin-garden/',
		// attraction-type archives (13 terms seen in Search Console)
		'/attraction-type/historical/', '/attraction-type/cultural/', '/attraction-type/religious/',
		'/attraction-type/nature/', '/attraction-type/mountain/', '/attraction-type/forest/',
		'/attraction-type/desert/', '/attraction-type/beach/', '/attraction-type/island/',
		'/attraction-type/village/', '/attraction-type/ecotourism/', '/attraction-type/adventure/',
		'/attraction-type/family/',
		// YMYL / thin articles
		'/overdose-rescue-guide/', '/maintain-fire-in-rain/', '/ground-insulation-sleeping/',
	);

	$urls = array();
	foreach ( $paths as $p ) {
		$urls[] = $home . $p;
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Run all probes.
 *
 * @param array $args Options from the form.
 * @return array
 */
function sa_insp_run_probes( $args ) {
	$urls = sa_insp_default_probe_urls();
	if ( ! empty( $args['custom_urls'] ) ) {
		$extra = preg_split( '/[\r\n,]+/', (string) $args['custom_urls'] );
		foreach ( (array) $extra as $u ) {
			$u = trim( (string) $u );
			if ( '' === $u ) {
				continue;
			}
			if ( 0 !== strpos( $u, 'http' ) ) {
				$u = untrailingslashit( home_url() ) . '/' . ltrim( $u, '/' );
			}
			$urls[] = $u;
		}
	}
	$urls = array_values( array_unique( $urls ) );

	$limit = isset( $args['max_probes'] ) ? max( 1, (int) $args['max_probes'] ) : count( $urls );
	$urls = array_slice( $urls, 0, $limit );

	$rows = array();
	foreach ( $urls as $url ) {
		$rows[] = sa_insp_probe( $url, ! empty( $args['bypass_cache'] ) );
	}
	return array( 'count' => count( $rows ), 'bypass_cache' => ! empty( $args['bypass_cache'] ), 'probes' => $rows );
}

/* =========================================================================
 * 6. Database inventory (read-only)
 * ====================================================================== */

/**
 * Full inventory of the entities that make up the sitemap.
 *
 * @return array
 */
function sa_insp_inventory() {
	global $wpdb;
	$out = array();

	// 6.1 counts per post type / status
	$rows = $wpdb->get_results( "SELECT post_type, post_status, COUNT(*) AS n FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type, post_status", ARRAY_A );
	$out['counts_by_type_status'] = $rows;

	// 6.2 every static page (this is where ?page_id=6..9 lives)
	$out['pages'] = $wpdb->get_results(
		"SELECT p.ID, p.post_name, p.post_title, p.post_status, p.post_parent,
		        CHAR_LENGTH(p.post_content) AS content_chars,
		        (SELECT meta_value FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_wp_page_template' LIMIT 1) AS template
		 FROM {$wpdb->posts} p WHERE p.post_type = 'page' ORDER BY p.ID",
		ARRAY_A
	);

	// 6.3 all provinces / attractions (small sets, full dump)
	foreach ( array( 'province', 'attraction' ) as $type ) {
		$out[ $type . '_posts' ] = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_status, CHAR_LENGTH(post_content) AS content_chars, post_modified
				 FROM {$wpdb->posts} WHERE post_type = %s ORDER BY post_name",
				$type
			),
			ARRAY_A
		);
	}

	// 6.4 all city posts: slug + size + robots meta (both theme and Rank Math)
	$out['city_posts'] = $wpdb->get_results(
		"SELECT p.ID, p.post_name, p.post_title, p.post_status, CHAR_LENGTH(p.post_content) AS content_chars,
		        MAX(CASE WHEN m.meta_key = 'sa_noindex' THEN m.meta_value END) AS sa_noindex,
		        MAX(CASE WHEN m.meta_key = 'rank_math_robots' THEN m.meta_value END) AS rank_math_robots,
		        MAX(CASE WHEN m.meta_key = 'rank_math_canonical_url' THEN m.meta_value END) AS rank_math_canonical,
		        MAX(CASE WHEN m.meta_key = 'rank_math_advanced_robots' THEN m.meta_value END) AS rank_math_advanced_robots
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
		 WHERE p.post_type = 'city'
		 GROUP BY p.ID ORDER BY p.post_name",
		ARRAY_A
	);

	// 6.5 numeric-prefixed slugs anywhere (the /attraction/6976-… family)
	$out['numeric_slugs'] = $wpdb->get_results(
		"SELECT ID, post_type, post_name, post_status FROM {$wpdb->posts}
		 WHERE post_name REGEXP '^[0-9]+' AND post_type NOT IN ('revision','attachment') ORDER BY post_type, post_name",
		ARRAY_A
	);

	// 6.6 duplicate / alias slug groups inside the same post type
	$out['duplicate_slugs'] = $wpdb->get_results(
		"SELECT post_type, post_name, COUNT(*) AS n, GROUP_CONCAT(ID ORDER BY ID) AS ids,
		        GROUP_CONCAT(post_status ORDER BY ID) AS statuses
		 FROM {$wpdb->posts}
		 WHERE post_type NOT IN ('revision','attachment','nav_menu_item')
		 GROUP BY post_type, post_name HAVING n > 1 ORDER BY n DESC, post_type",
		ARRAY_A
	);

	// 6.7 terms of every public taxonomy with their real counts
	$out['terms'] = $wpdb->get_results(
		"SELECT tt.taxonomy, t.term_id, t.name, t.slug, tt.count, tt.parent
		 FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
		 WHERE tt.taxonomy IN ('province_tax','attraction_type','travel_season','travel_budget','travel_duration','post_tag','category','accommodation_type')
		 ORDER BY tt.taxonomy, t.slug",
		ARRAY_A
	);

	// 6.8 posts carrying an explicit robots / canonical override
	$out['meta_noindex'] = $wpdb->get_results(
		"SELECT p.ID, p.post_type, p.post_name, m.meta_key, m.meta_value
		 FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
		 WHERE m.meta_key IN ('sa_noindex','rank_math_robots')
		   AND ( m.meta_value LIKE '%noindex%' OR m.meta_value = '1' )
		 ORDER BY p.post_type, p.post_name LIMIT 400",
		ARRAY_A
	);
	$out['meta_canonical_count'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ('rank_math_canonical_url','sa_canonical_url') AND meta_value <> ''"
	);

	// 6.9 options that decide indexing
	$out['options'] = array(
		'blog_public'          => get_option( 'blog_public' ),
		'permalink_structure'  => get_option( 'permalink_structure' ),
		'sa_default_pages'     => get_option( 'sa_default_pages', array() ),
		'page_for_posts'       => get_option( 'page_for_posts' ),
		'show_on_front'        => get_option( 'show_on_front' ),
	);

	return $out;
}

/* =========================================================================
 * 7. Content scan — where the broken links are produced
 * ====================================================================== */

/**
 * Scan published content for the patterns that generate the reported URLs.
 *
 * @return array
 */
function sa_insp_content_scan() {
	global $wpdb;

	$patterns = array(
		'W_code_relative_link' => array( 'like' => '%W\\_%', 'regex' => '/W_[A-Za-z]{2,6}/' ),
		'editorial_policy_link' => array( 'like' => '%editorial-policy%', 'regex' => '~/editorial-policy~' ),
		'ostan_link'           => array( 'like' => '%/ostan/%', 'regex' => '~/ostan/[a-z0-9\-]+~' ),
		'provinces_link'       => array( 'like' => '%/provinces/%', 'regex' => '~/provinces/[a-z0-9\-]+~' ),
		'city_suffix_link'     => array( 'like' => '%-city/%', 'regex' => '~/city/[a-z0-9\-]*-city/~' ),
		'county_suffix_link'   => array( 'like' => '%-county/%', 'regex' => '~/city/[a-z0-9\-]*-county/~' ),
		'page_id_link'         => array( 'like' => '%page_id=%', 'regex' => '~page_id=[0-9]+~' ),
		'season_pagination_link' => array( 'like' => '%/season/%page/%', 'regex' => '~/season/[a-z]+/page/[0-9]+/~' ),
		'hello_world_link'     => array( 'like' => '%سلام-دنیا%', 'regex' => '/سلام-دنیا/' ),
		'numeric_attraction_link' => array( 'like' => '%/attraction/%', 'regex' => '~/attraction/[0-9]+-[a-z0-9\-]+~' ),
	);

	$types  = array( 'post', 'page', 'city', 'province', 'attraction', 'travel_route', 'local_food', 'souvenir' );
	$out    = array();

	foreach ( $patterns as $key => $def ) {
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql  = "SELECT ID, post_type, post_name, post_status, post_content FROM {$wpdb->posts}
		         WHERE post_type IN ($in) AND post_status IN ('publish','draft','future') AND post_content LIKE %s
		         ORDER BY post_type, ID LIMIT 400";
		$args = array_merge( $types, array( $def['like'] ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		$hits      = array();
		$total     = 0;
		$published = 0;
		foreach ( (array) $rows as $row ) {
			if ( ! preg_match_all( $def['regex'], (string) $row['post_content'], $m ) ) {
				continue;
			}
			$matches = array_values( array_unique( $m[0] ) );
			$total  += count( $m[0] );
			if ( 'publish' === $row['post_status'] ) {
				$published++;
			}
			if ( count( $hits ) < 40 ) {
				$pos     = strpos( (string) $row['post_content'], trim( $matches[0], '~/' ) );
				$context = false === $pos ? '' : sa_insp_cut( substr( (string) $row['post_content'], max( 0, $pos - 80 ), 240 ), 240 );
				$hits[]  = array(
					'id'         => (int) $row['ID'],
					'post_type'  => $row['post_type'],
					'slug'       => $row['post_name'],
					'status'     => $row['post_status'],
					'occurrences' => count( $m[0] ),
					'values'     => array_slice( $matches, 0, 12 ),
					'context'    => $context,
				);
			}
		}

		$out[ $key ] = array(
			'matched_posts'     => count( (array) $rows ),
			'posts_with_regex'  => count( $hits ),
			'published_posts'   => $published,
			'total_occurrences' => $total,
			'samples'           => $hits,
		);
	}

	// Nav menus: every item URL (menus are a common source of /ostan/ links).
	$menus = array();
	foreach ( (array) wp_get_nav_menus() as $menu ) {
		$items = array();
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
			$items[] = array(
				'id'      => (int) $item->ID,
				'title'   => $item->title,
				'url'     => $item->url,
				'type'    => $item->type,
				'object'  => $item->object,
				'object_id' => (int) $item->object_id,
				'parent'  => (int) $item->menu_item_parent,
			);
		}
		$menus[] = array( 'id' => (int) $menu->term_id, 'name' => $menu->name, 'slug' => $menu->slug, 'count' => count( $items ), 'items' => $items );
	}
	$out['nav_menus'] = $menus;

	// Text widgets (footer/sidebar HTML often holds stale URLs).
	$widgets = array();
	foreach ( array( 'widget_text', 'widget_custom_html' ) as $option_name ) {
		$instances = (array) get_option( $option_name, array() );
		foreach ( $instances as $k => $inst ) {
			if ( ! is_array( $inst ) || empty( $inst['text'] ) ) {
				continue;
			}
			$widgets[] = array( 'option' => $option_name, 'key' => (string) $k, 'text' => sa_insp_cut( (string) $inst['text'], 2000 ) );
		}
	}
	$out['text_widgets'] = $widgets;

	return $out;
}

/* =========================================================================
 * 8. Redirection sources
 * ====================================================================== */

/**
 * Every redirection source we can see: theme map, Rank Math table, plugin list.
 *
 * @return array
 */
function sa_insp_redirections() {
	global $wpdb;
	$out = array();

	// Theme's own 301 map (data/redirects.php in the child theme).
	if ( function_exists( 'sa_redirect_map' ) ) {
		$out['theme_redirect_map'] = sa_redirect_map();
		$out['theme_redirect_map_count'] = count( (array) sa_redirect_map() );
	} else {
		$out['theme_redirect_map'] = null;
		$out['theme_redirect_map_count'] = 0;
	}

	// Rank Math redirections table (only if the module created it).
	$table = $wpdb->prefix . 'rank_math_redirections';
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $exists === $table ) {
		$out['rank_math_redirections'] = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id LIMIT 300", ARRAY_A );
	} else {
		$out['rank_math_redirections'] = null;
	}

	// Any other redirect-capable plugin?
	$out['redirect_plugins'] = array_values(
		array_filter(
			array_map( 'strtolower', (array) get_option( 'active_plugins', array() ) ),
			function ( $f ) {
				return (bool) preg_match( '/redirect|301|permlink|link-fix/', (string) $f );
			}
		)
	);

	return $out;
}

/* =========================================================================
 * 9. Collect + export
 * ====================================================================== */

/**
 * Build the whole report.
 *
 * @param array $args Form options.
 * @return array
 */
function sa_inspector_collect( $args ) {
	$report = array(
		'report'        => 'sa-seo-inspector',
		'version'       => SA_INSPECTOR_VERSION,
		'generated_at'  => current_time( 'c' ),
		'args'          => $args,
		'environment'   => sa_insp_environment(),
		'seo_plugins'   => sa_insp_seo_plugins(),
		'robots_files'  => sa_insp_robots_and_files(),
		'sitemaps'      => sa_insp_sitemaps(),
		'inventory'     => sa_insp_inventory(),
		'content_scan'  => sa_insp_content_scan(),
		'redirections'  => sa_insp_redirections(),
	);

	if ( empty( $args['skip_probes'] ) ) {
		$report['probes'] = sa_insp_run_probes( $args );
	} else {
		$report['probes'] = array( 'skipped' => true );
	}

	$report['counts'] = array(
		'sitemap_urls' => 0,
		'probes'       => isset( $report['probes']['count'] ) ? $report['probes']['count'] : 0,
	);
	if ( ! empty( $report['sitemaps']['sources'] ) ) {
		foreach ( (array) $report['sitemaps']['sources'] as $src ) {
			$report['counts']['sitemap_urls'] += isset( $src['url_count'] ) ? (int) $src['url_count'] : 0;
		}
	}

	return $report;
}

/**
 * Stream the report as a JSON download.
 *
 * @return void
 */
function sa_inspector_export() {
	if ( ! current_user_can( SA_INSPECTOR_CAP ) ) {
		wp_die( esc_html__( 'اجازه ندارید.', 'sa-seo-inspector' ) );
	}
	check_admin_referer( 'sa_inspector_export' );

	@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	wp_raise_memory_limit( 'admin' );

	$args = array(
		'skip_probes'   => isset( $_GET['skip_probes'] ) ? (bool) $_GET['skip_probes'] : false,
		'bypass_cache'  => isset( $_GET['bypass_cache'] ) ? (bool) $_GET['bypass_cache'] : true,
		'max_probes'    => isset( $_GET['max_probes'] ) ? (int) $_GET['max_probes'] : 0,
		'custom_urls'   => isset( $_GET['custom_urls'] ) ? sanitize_textarea_field( wp_unslash( $_GET['custom_urls'] ) ) : '',
	);
	if ( empty( $args['max_probes'] ) ) {
		unset( $args['max_probes'] );
	}

	$report = sa_inspector_collect( $args );
	$json   = wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="sa-seo-inspector-' . gmdate( 'Ymd-His' ) . '.json"' );
	header( 'Content-Length: ' . strlen( (string) $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON payload.
	exit;
}
add_action( 'admin_post_sa_inspector_export', 'sa_inspector_export' );

/* =========================================================================
 * 10. Admin screen
 * ====================================================================== */

/**
 * Register the Tools screen.
 *
 * @return void
 */
function sa_inspector_menu() {
	add_management_page(
		__( 'SA SEO Inspector', 'sa-seo-inspector' ),
		__( 'SA SEO Inspector', 'sa-seo-inspector' ),
		SA_INSPECTOR_CAP,
		'sa-seo-inspector',
		'sa_inspector_screen'
	);
}
add_action( 'admin_menu', 'sa_inspector_menu' );

/**
 * Render the screen.
 *
 * @return void
 */
function sa_inspector_screen() {
	if ( ! current_user_can( SA_INSPECTOR_CAP ) ) {
		return;
	}
	$theme  = wp_get_theme();
	$probes = sa_insp_default_probe_urls();
	?>
	<div class="wrap">
		<h1>SA SEO Inspector <span style="font-size:60%">v<?php echo esc_html( SA_INSPECTOR_VERSION ); ?> — فقط‌خواندنی / یک‌بارمصرف</span></h1>

		<div class="notice notice-warning"><p>
			این افزونه <strong>هیچ چیزی را تغییر نمی‌دهد</strong>؛ فقط وضعیت فعلی سایت را به یک فایل JSON خروجی می‌دهد.
			پس از دانلود گزارش، افزونه را غیرفعال و حذف کنید.
		</p></div>

		<table class="widefat striped" style="max-width:900px;margin-bottom:16px">
			<tbody>
				<tr><th style="width:280px">قالب فعال</th><td><?php echo esc_html( get_stylesheet() . ' · ' . $theme->get( 'Version' ) ); ?></td></tr>
				<tr><th>SA_CHILD_VERSION</th><td><?php echo esc_html( defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '—' ); ?></td></tr>
				<tr><th>وردپرس / PHP</th><td><?php echo esc_html( get_bloginfo( 'version' ) . ' / ' . PHP_VERSION ); ?></td></tr>
				<tr><th>پیوند یکتا</th><td><code><?php echo esc_html( (string) get_option( 'permalink_structure' ) ); ?></code></td></tr>
				<tr><th>Discourage search engines (blog_public)</th><td><?php echo (int) get_option( 'blog_public', 1 ) ? '❌ خاموش (قابل دیدن برای موتور جست‌وجو)' : '🚫 روشن = سایت noindex کل است!'; ?></td></tr>
				<tr><th>افزونه‌های فعال</th><td><?php echo esc_html( implode( ', ', array_map( 'basename', (array) get_option( 'active_plugins', array() ) ) ) ); ?></td></tr>
				<tr><th>Rank Math</th><td><?php echo esc_html( defined( 'RANK_MATH_VERSION' ) ? RANK_MATH_VERSION : 'یافت نشد' ); ?><?php echo defined( 'RANK_MATH_PRO_VERSION' ) ? esc_html( ' · Pro ' . RANK_MATH_PRO_VERSION ) : ''; ?></td></tr>
				<tr><th>URLهای نمونه برای probe</th><td><?php echo (int) count( $probes ); ?></td></tr>
			</tbody>
		</table>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_inspector_export" />
			<?php wp_nonce_field( 'sa_inspector_export' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>گزینه‌ها</th>
					<td>
						<label><input type="checkbox" name="bypass_cache" value="1" checked="checked" /> دورزدن کش (افزودن <code>?sa_nc=1</code> به هر URL)</label><br/>
						<label><input type="checkbox" name="skip_probes" value="1" /> فقط موجودی/تنظیمات، بدون پیمایش URLها (سریع)</label><br/>
						<label>حداکثر URL برای پیمایش: <input type="number" name="max_probes" min="1" max="400" value="<?php echo (int) count( $probes ); ?>" style="width:90px" /></label>
					</td>
				</tr>
				<tr>
					<th>URLهای دلخواه<br/>(هر خط یکی)</th>
					<td><textarea name="custom_urls" rows="6" class="large-text code" placeholder="https://sarzaminaryan.ir/city/shiraz/"></textarea></td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary button-hero">دانلود گزارش JSON</button>
				<span class="description">پیمایش کامل <?php echo (int) count( $probes ); ?> URL ممکن است ۲ تا ۵ دقیقه طول بکشد؛ صفحه را نبندید.</span>
			</p>
		</form>

		<h2>چه چیزی در گزارش هست؟</h2>
		<ol style="max-width:900px">
			<li><strong>environment</strong> — نسخهٔ وردپرس/PHP/قالب، ساختار پیوند یکتا، <code>blog_public</code>، فهرست افزونه‌ها، CPTها و تکسونومی‌های ثبت‌شده با اسلگ rewrite (یعنی <code>/province/</code> در برابر <code>/ostan/</code>)، و قواعد rewrite مربوط.</li>
			<li><strong>seo_plugins</strong> — نسخهٔ Rank Math (و Pro)، ماژول‌ها و همهٔ تنظیمات <code>rank_math_*</code> (بدون کلید/توکن)، به‌علاوهٔ وضعیت کناررفتنِ سئوی قالب.</li>
			<li><strong>robots_files</strong> — متن robots.txt سروشده، <strong>فایل‌های فیزیکی ریشه</strong> (robots.txt، sitemap.xml، sitemap_index.xml، wp-sitemap.xml، هر <code>*.xml</code> ریشه) و محتوای .htaccess — این‌جا منبع الگوهای <code>/*</code> معلوم می‌شود.</li>
			<li><strong>sitemaps</strong> — هر سه منبع سایت‌مپ (هستهٔ وردپرس، Rank Math، فایل دستی) با وضعیت HTTP، ریدایرکت و <strong>فهرست کامل همهٔ URLهای داخلشان</strong>.</li>
			<li><strong>probes</strong> — برای هر URL: کد وضعیت، هدر <code>Location</code>، <code>X-Robots-Tag</code>، و در صورت 200: عنوان، <code>meta robots</code>، <code>canonical</code>، تعداد H1، نوع‌های JSON-LD و شمار واژه.</li>
			<li><strong>inventory</strong> — شمارش پست‌ها بر پایهٔ نوع/وضعیت، همهٔ برگه‌ها (با ID برای <code>?page_id=</code>)، همهٔ استان‌ها و جاذبه‌ها، همهٔ شهرستان‌ها با طول محتوا و متاهای noindex/canonical، اسلاگ‌های عددی، اسلاگ‌های تکراری، و ترم‌های همهٔ تکسونومی‌ها با شمارش واقعی.</li>
			<li><strong>content_scan</strong> — منشأ لینک‌های خراب: <code>W_*</code>، <code>/editorial-policy</code>، <code>/ostan/</code>، <code>/provinces/</code>، پسوند <code>-city/</code>، <code>page_id=</code>، صفحه‌بندی <code>/season/…/page/…</code>، به‌تفکیک پست + نمونهٔ متن، به‌علاوهٔ همهٔ آیتم‌های منوها و ویجت‌های متنی.</li>
			<li><strong>redirections</strong> — نقشهٔ ۳۰۱ قالب (<code>data/redirects.php</code>)، جدول redirections رنک‌مث، و افزونه‌های ریدایرکت.</li>
		</ol>
	</div>
	<?php
}
