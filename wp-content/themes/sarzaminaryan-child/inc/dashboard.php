<?php
/**
 * داشبورد سایبری و نقشهٔ تعاملی شناسنامهٔ استان‌ها و شهرستان‌های ایران — اسمبل داده و بارگذاری دارایی‌ها.
 *
 * منبع داده (فقط‌خواندنی، هیچ نوشتنی در دیتابیس انجام نمی‌شود):
 *  - `data/region-facts.php` — جمعیت/مساحت/مرکز/اقلیم/همسایه‌های داخلی ۳۱ استان
 *    (پروژه‌ی Iran Map v0.8.0؛ جمعیت‌ها با مجموع رسمی سرشماری ۱۳۹۵ برابر است).
 *  - `data/province-profiles.json` — آمار رسمی تقسیمات سیاسی (شهرستان، بخش، شهر، دهستان، آبادی، خانوار).
 *  - `data/counties.php` — فهرست رسمی ۴۸۳ شهرستان (تولید ۱۴۰۵/۰۷/۱۰).
 *  - `data/attractions.php` — گزینش تحریریهٔ جاذبه‌های شاخص.
 *  - `data/borders.php` — استان‌های مرزنشین و کشورهای همسایه.
 *  - `assets/js/iran-provinces-map.js` — مرز ۳۱ استان ایران (OSM/ODbL، پروژه‌ی ایران‌مپ‌کور، پروانهٔ MIT).
 *  - `assets/js/iran-counties-map.js` — مرز ۴۷۸ شهرستان + جزایر ایران به تفکیک استان (OSM/ODbL، ایران‌مپ‌کور).
 *
 * خاموش‌کردن: define( 'SA_DASHBOARD', false ); در wp-config.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا ماژول داشبورد فعال است؟
 *
 * @return bool
 */
function sa_dashboard_enabled() {
	$on = defined( 'SA_DASHBOARD' ) ? (bool) SA_DASHBOARD : true;

	/**
	 * فعال/غیرفعال کردن داشبورد گردشگری.
	 *
	 * @param bool $on وضعیت.
	 */
	return (bool) apply_filters( 'sa_dashboard_enabled', $on );
}

/**
 * اسمبل مجموعه‌دادهٔ داشبورد سایبری (یک‌بار در هر درخواست).
 *
 * همهٔ مقادیر مشتق (تراکم، رتبه، مجموع‌ها، شمار شهرستان/جاذبه و تقسیمات سیاسی)
 * از داده‌های رسمی مخزن محاسبه می‌شوند تا هیچ عددی دستی وارد نشود.
 *
 * @return array
 */
function sa_dashboard_dataset() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$region   = require SA_CHILD_DIR . 'data/region-facts.php';
	$registry = require SA_CHILD_DIR . 'data/counties.php';
	$en_names = require SA_CHILD_DIR . 'data/provinces.php';
	$attr     = require SA_CHILD_DIR . 'data/attractions.php';
	$borders  = require SA_CHILD_DIR . 'data/borders.php';

	$profiles_raw = array();
	$profiles_path = SA_CHILD_DIR . 'data/province-profiles.json';
	if ( file_exists( $profiles_path ) ) {
		$decoded = json_decode( (string) file_get_contents( $profiles_path ), true );
		if ( is_array( $decoded ) && isset( $decoded['provinces'] ) && is_array( $decoded['provinces'] ) ) {
			$profiles_raw = $decoded['provinces'];
		}
	}

	// شمار شهرستان از فهرست رسمی ۴۸۳تایی.
	$county_counts = array();
	foreach ( (array) $registry as $row ) {
		if ( empty( $row['province'] ) ) {
			continue;
		}
		$county_counts[ $row['province'] ] = isset( $county_counts[ $row['province'] ] ) ? $county_counts[ $row['province'] ] + 1 : 1;
	}

	// شمار جاذبه‌ها به تفکیک استان.
	$attr_counts = array();
	foreach ( (array) $attr['items'] as $item ) {
		if ( empty( $item['province'] ) ) {
			continue;
		}
		$attr_counts[ $item['province'] ] = isset( $attr_counts[ $item['province'] ] ) ? $attr_counts[ $item['province'] ] + 1 : 1;
	}

	// نام انگلیسی از رجیستری استان‌ها (سطح ۲ مدل داده).
	$en_map = array();
	foreach ( (array) $en_names as $row ) {
		$en_map[ $row['slug'] ] = $row['en'];
	}

	// نشانی صفحات منتشرشدهٔ استان‌ها و شهرستان‌ها در وردپرس.
	$province_urls = array();
	$county_urls   = array();
	if ( function_exists( 'get_posts' ) ) {
		$pub_provs = get_posts(
			array(
				'post_type'              => 'province',
				'post_status'            => 'publish',
				'posts_per_page'         => 50,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( (array) $pub_provs as $pp ) {
			if ( ! empty( $pp->post_name ) ) {
				$province_urls[ (string) $pp->post_name ] = (string) get_permalink( $pp );
			}
		}

		$pub_cities = get_posts(
			array(
				'post_type'              => 'city',
				'post_status'            => 'publish',
				'posts_per_page'         => 600,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		foreach ( (array) $pub_cities as $cp ) {
			if ( ! empty( $cp->post_name ) ) {
				$county_urls[ (string) $cp->post_name ] = (string) get_permalink( $cp );
			}
		}
	}

	$provinces  = array();
	$pop_total  = 0;
	$area_total = 0;
	foreach ( (array) $region['provinces'] as $slug => $p ) {
		$population = isset( $p['population'] ) ? (int) $p['population'] : 0;
		$area       = isset( $p['area'] ) ? (int) $p['area'] : 0;
		$pop_total += $population;
		$area_total += $area;

		$prof  = isset( $profiles_raw[ $slug ] ) && is_array( $profiles_raw[ $slug ] ) ? $profiles_raw[ $slug ] : array();
		$stats = isset( $prof['stats'] ) && is_array( $prof['stats'] ) ? $prof['stats'] : array();

		$provinces[] = array(
			'slug'                 => (string) $slug,
			'name'                 => isset( $p['name'] ) ? $p['name'] : '',
			'en'                   => isset( $en_map[ $slug ] ) ? $en_map[ $slug ] : '',
			'capital'              => isset( $p['capital'] ) ? $p['capital'] : '',
			'population'           => $population,
			'population_estimate'  => isset( $stats['population_estimate'] ) ? (int) $stats['population_estimate'] : 0,
			'household_count'      => isset( $stats['household_count'] ) ? (int) $stats['household_count'] : 0,
			'area'                 => $area,
			'density'              => $area > 0 ? round( $population / $area, 1 ) : null,
			'counties'             => isset( $county_counts[ $slug ] ) ? $county_counts[ $slug ] : 0,
			'district_count'       => isset( $stats['district_count'] ) ? (int) $stats['district_count'] : 0,
			'city_count'           => isset( $stats['city_count'] ) ? (int) $stats['city_count'] : 0,
			'rural_district_count' => isset( $stats['rural_district_count'] ) ? (int) $stats['rural_district_count'] : 0,
			'settlement_count'     => isset( $stats['settlement_count'] ) ? (int) $stats['settlement_count'] : 0,
			'climate'              => isset( $p['climate'] ) ? $p['climate'] : '',
			'neighbors'            => isset( $p['neighbors'] ) ? $p['neighbors'] : '',
			'neighbor_groups'      => isset( $prof['neighbor_groups'] ) && is_array( $prof['neighbor_groups'] ) ? $prof['neighbor_groups'] : array(),
			'border'               => ! empty( $borders['provinces'][ $slug ] ),
			'countries'            => isset( $borders['provinces'][ $slug ] ) ? array_values( $borders['provinces'][ $slug ] ) : array(),
			'attractions'          => isset( $attr_counts[ $slug ] ) ? $attr_counts[ $slug ] : 0,
			'url'                  => isset( $province_urls[ $slug ] ) ? $province_urls[ $slug ] : '',
		);
	}

	$cache = array(
		'meta'        => array(
			'site_name'           => 'سرزمین آریان',
			'site_url'            => 'https://sarzaminaryan.ir',
			'population_period'   => 'سرشماری عمومی نفوس و مسکن ۱۳۹۵ (۲۰۱۶)',
			'population_note'     => 'مجموع جمعیت ۳۱ استان با رقم رسمی سرشماری ۱۳۹۵ دقیقاً برابر است؛ رتبه‌ها و درصدها از همین داده گرفته شده‌اند.',
			'area_total_official' => 1648195,
			'area_note'           => 'مجموع مساحت داده‌های استانی کمی کمتر از رقم رسمی کشور است (اختلاف پوشش جزایر/پهنه‌های آبی در داده‌ی مبدأ).',
			'counties_date'       => '۱۴۰۵/۰۷/۱۰',
			'counties_note'       => 'شمار شهرستان‌ها از فهرست رسمی ۴۸۳تایی مخزن گرفته شده است.',
			'attractions_note'    => isset( $attr['note'] ) ? $attr['note'] : '',
			'borders_note'        => isset( $borders['note'] ) ? $borders['note'] : '',
			'borders_verified'    => ! empty( $borders['verified'] ),
			'borders_coastal'     => isset( $borders['coastal'] ) ? $borders['coastal'] : array(),
			'map_attribution'     => 'مرزهای نقشه: © مشارکت‌کنندگان OpenStreetMap (پروانهٔ ODbL)، ساده‌شده از پروژهٔ ایران‌مپ‌کور (پروانهٔ MIT).',
		),
		'kpis'        => array(
			'provinces'   => count( $provinces ),
			'counties'    => is_array( $registry ) ? count( $registry ) : 0,
			'attractions' => is_array( $attr['items'] ) ? count( $attr['items'] ) : 0,
			'article_ready' => count( array_filter( sa_dashboard_attractions_with_articles( (array) $attr['items'] ), function ( $row ) { return ! empty( $row['has_article'] ); } ) ),
			'area'        => $area_total,
			'population'  => $pop_total,
		),
		'countries'     => isset( $borders['countries'] ) ? array_values( $borders['countries'] ) : array(),
		'provinces'     => $provinces,
		'attractions'   => sa_dashboard_attractions_with_articles( (array) $attr['items'] ),
		'province_urls' => $province_urls,
		'county_urls'   => $county_urls,
	);

	/**
	 * مجموعه‌دادهٔ داشبورد پیش از تحویل به قالب.
	 *
	 * @param array $data مجموعه‌داده.
	 */
	return apply_filters( 'sa_dashboard_data', $cache );
}

/**
 * کلید تطبیق نام فارسی (حذف پرانتز، نیم‌فاصله و نویسه‌های هم‌ارز).
 *
 * @param string $text نام یا عنوان.
 * @return string
 */
function sa_dashboard_match_key( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = preg_replace( '/\([^)]*\)/u', ' ', $text );
	$text = str_replace( array( 'ۀ', 'ة', 'ي', 'ك', "\xE2\x80\x8C" ), array( 'ه', 'ه', 'ی', 'ک', ' ' ), (string) $text );
	$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $text );
	return trim( (string) preg_replace( '/\s+/u', ' ', (string) $text ) );
}

/**
 * فهرست مقالات آمادهٔ دیدنی‌ها برای تطبیق با رکوردهای داشبورد (v2.20.0).
 *
 * هر جاذبهٔ داشبورد با نام فارسی، سپس با نامک، با یکی از ۲۸ مقالهٔ آمادهٔ قالب
 * تطبیق می‌شود تا «جاذبه‌های دارای مقاله» در کارت‌ها تیک بخورند و به برگهٔ
 * منتشرشده (اگر وجود داشته باشد) پیوند داده شوند.
 *
 * @return array{by_name:array<string,string>,by_slug:array<string,string>,urls:array<string,string>}
 */
function sa_dashboard_ready_articles() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map = array(
		'by_name' => array(),
		'by_slug' => array(),
		'urls'    => array(),
	);
	if ( ! function_exists( 'sa_ready_attractions_data' ) ) {
		return $map;
	}
	foreach ( sa_ready_attractions_data() as $key => $article ) {
		if ( ! is_array( $article ) ) {
			continue;
		}
		$slug = ! empty( $article['slug'] ) ? (string) $article['slug'] : (string) $key;
		if ( '' === $slug ) {
			continue;
		}
		$map['by_slug'][ $slug ] = $slug;
		$title = isset( $article['title'] ) ? (string) $article['title'] : '';
		if ( '' !== $title ) {
			$map['by_name'][ sa_dashboard_match_key( $title ) ] = $slug;
		}
	}

	// برگه‌های منتشرشدهٔ همان نامک‌ها (اگر وارد شده باشند) یک‌جا خوانده می‌شوند.
	if ( function_exists( 'get_posts' ) && $map['by_slug'] ) {
		$posts = get_posts(
			array(
				'post_type'              => 'attraction',
				'post_status'            => 'publish',
				'post_name__in'          => array_values( $map['by_slug'] ),
				'posts_per_page'         => count( $map['by_slug'] ) + 5,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'fields'                 => 'ids',
			)
		);
		foreach ( (array) $posts as $post_id ) {
			$name = (string) get_post_field( 'post_name', $post_id );
			if ( '' === $name ) {
				continue;
			}
			$perma = get_permalink( (int) $post_id );
			if ( $perma ) {
				$map['urls'][ $name ] = (string) $perma;
			}
		}
	}
	return $map;
}

/**
 * افزودن وضعیت مقاله به هر رکورد جاذبهٔ داشبورد.
 *
 * @param array<int,array<string,mixed>> $items رکوردهای خام جاذبه.
 * @return array<int,array<string,mixed>> رکوردها با کلیدهای article_slug/article_url/has_article.
 */
function sa_dashboard_attractions_with_articles( $items ) {
	$ready = sa_dashboard_ready_articles();
	$out   = array();
	foreach ( (array) $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
		$key  = sa_dashboard_match_key( isset( $item['name'] ) ? (string) $item['name'] : '' );
		$as   = '';
		if ( '' !== $key && isset( $ready['by_name'][ $key ] ) ) {
			$as = (string) $ready['by_name'][ $key ];
		} elseif ( '' !== $slug && isset( $ready['by_slug'][ $slug ] ) ) {
			$as = (string) $ready['by_slug'][ $slug ];
		}
		$item['article_slug'] = $as;
		$item['article_url']  = ( '' !== $as && isset( $ready['urls'][ $as ] ) ) ? (string) $ready['urls'][ $as ] : '';
		$item['has_article']  = ( '' !== $as );
		$out[]                = $item;
	}
	return $out;
}

/**
 * نشانی برگه‌ی داشبورد اگر وجود، منتشر و به قالب داشبورد بسته باشد.
 *
 * اولویت با جدیدترین برگهٔ منتشرشده‌ای است که قالب `template-dashboard.php` دارد
 * (اگر مالک برگهٔ جدیدی در «برگه‌ها» ساخته باشد)، و در غیر این صورت برگهٔ پیش‌فرض `dashboard`.
 *
 * @return string نشانی یا رشته‌ی خالی.
 */
function sa_dashboard_page_url() {
	if ( ! sa_dashboard_enabled() ) {
		return '';
	}

	if ( function_exists( 'get_posts' ) ) {
		$custom_pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 5,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'meta_key'               => '_wp_page_template',
				'meta_value'             => 'template-dashboard.php',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $custom_pages[0] ) && isset( $custom_pages[0]->ID ) ) {
			return (string) get_permalink( (int) $custom_pages[0]->ID );
		}
	}

	$ids = get_option( 'sa_default_pages', array() );
	$id  = ! empty( $ids['dashboard'] ) ? (int) $ids['dashboard'] : 0;
	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		$page = get_page_by_path( 'dashboard' );
		$id   = ( $page && 'publish' === get_post_status( $page ) ) ? (int) $page->ID : 0;
	}
	if ( ! $id || 'template-dashboard.php' !== get_page_template_slug( $id ) ) {
		return '';
	}
	return (string) get_permalink( $id );
}

/**
 * بارگذاری دارایی‌های داشبورد سایبری روی صفحه‌ای که قالب داشبورد را دارد.
 */
function sa_dashboard_assets() {
	if ( ! sa_dashboard_enabled() || ! is_page_template( 'template-dashboard.php' ) ) {
		return;
	}
	wp_enqueue_style( 'sa-dashboard', SA_CHILD_URI . 'assets/css/dashboard.css', array( 'sarzaminaryan-child' ), SA_CHILD_VERSION );
	wp_enqueue_script( 'sa-map-shapes', SA_CHILD_URI . 'assets/js/iran-provinces-map.js', array(), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_enqueue_script( 'sa-county-shapes', SA_CHILD_URI . 'assets/js/iran-counties-map.js', array( 'sa-map-shapes' ), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_enqueue_script( 'sa-dashboard', SA_CHILD_URI . 'assets/js/dashboard.js', array( 'sa-map-shapes', 'sa-county-shapes' ), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'sa_dashboard_assets', 25 );
