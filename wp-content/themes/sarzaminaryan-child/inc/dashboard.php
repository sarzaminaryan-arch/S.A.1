<?php
/**
 * داشبورد جامع گردشگری ایران — اسمبل داده و بارگذاری دارایی‌ها.
 *
 * منبع داده (فقط‌خواندنی، هیچ نوشتنی در دیتابیس انجام نمی‌شود):
 *  - `data/region-facts.php` — جمعیت/مساحت/مرکز/اقلیم/همسایه‌های داخلی ۳۱ استان
 *    (پروژه‌ی Iran Map v0.8.0؛ جمعیت‌ها با مجموع رسمی سرشماری ۱۳۹۵ برابر است).
 *  - `data/counties.php` — فهرست رسمی ۴۸۳ شهرستان (تولید ۱۴۰۵/۰۷/۱۰).
 *  - `data/attractions.php` — گزینش تحریریهٔ جاذبه‌های شاخص (بدون امتیاز محبوبیت).
 *  - `data/borders.php` — استان‌های مرزنشین و کشورهای همسایه (تحریریه‌ای؛ نیازمند بازبینی رسمی).
 *  - `assets/js/iran-provinces-map.js` — مرز استان‌ها (Osm/ODbL، پروژه‌ی ایران‌مپ‌کور، پروانهٔ MIT).
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
 * اسمبل مجموعه‌دادهٔ داشبورد (یک‌بار در هر درخواست).
 *
 * همهٔ مقادیر مشتق (تراکم، رتبه، مجموع‌ها، شمار شهرستان/جاذبه) از همین داده‌ها
 * محاسبه می‌شوند تا هیچ عددی «دستی» وارد صفحه نشود.
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

	// شمار شهرستان از فهرست رسمی ۴۸۳تایی (نه از ۴۷۵ رکورد مختصات‌دار).
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

	$provinces = array();
	$pop_total = 0;
	$area_total = 0;
	foreach ( (array) $region['provinces'] as $slug => $p ) {
		$population = isset( $p['population'] ) ? (int) $p['population'] : 0;
		$area       = isset( $p['area'] ) ? (int) $p['area'] : 0;
		$pop_total += $population;
		$area_total += $area;

		$provinces[] = array(
			'slug'        => (string) $slug,
			'name'        => isset( $p['name'] ) ? $p['name'] : '',
			'en'          => isset( $en_map[ $slug ] ) ? $en_map[ $slug ] : '',
			'capital'     => isset( $p['capital'] ) ? $p['capital'] : '',
			'population'  => $population,
			'area'        => $area,
			'density'     => $area > 0 ? round( $population / $area, 1 ) : null,
			'counties'    => isset( $county_counts[ $slug ] ) ? $county_counts[ $slug ] : 0,
			'climate'     => isset( $p['climate'] ) ? $p['climate'] : '',
			'neighbors'   => isset( $p['neighbors'] ) ? $p['neighbors'] : '',
			'border'      => ! empty( $borders['provinces'][ $slug ] ),
			'countries'   => isset( $borders['provinces'][ $slug ] ) ? array_values( $borders['provinces'][ $slug ] ) : array(),
			'attractions' => isset( $attr_counts[ $slug ] ) ? $attr_counts[ $slug ] : 0,
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
			'counties_note'       => 'شمار شهرستان‌ها از فهرست رسمی ۴۸۳تایی مخزن گرفته شده، نه از رکوردهای مختصات‌دار.',
			'attractions_note'    => isset( $attr['note'] ) ? $attr['note'] : '',
			'borders_note'        => isset( $borders['note'] ) ? $borders['note'] : '',
			'borders_verified'    => ! empty( $borders['verified'] ),
			'borders_coastal'     => isset( $borders['coastal'] ) ? $borders['coastal'] : array(),
			'map_attribution'     => 'مرزهای نقشه: © مشارکت‌کنندگان OpenStreetMap (پروانهٔ ODbL)، ساده‌شده از پروژهٔ ایران‌مپ‌کور (پروانهٔ MIT). نقشه برای مقیاس‌سنجی دقیق نیست.',
		),
		'kpis'        => array(
			'provinces'   => count( $provinces ),
			'counties'    => is_array( $registry ) ? count( $registry ) : 0,
			'attractions' => is_array( $attr['items'] ) ? count( $attr['items'] ) : 0,
			'area'        => $area_total,
			'population'  => $pop_total,
		),
		'countries'   => isset( $borders['countries'] ) ? array_values( $borders['countries'] ) : array(),
		'provinces'   => $provinces,
		'attractions' => array_values( (array) $attr['items'] ),
	);

	/**
	 * مجموعه‌دادهٔ داشبورد پیش از تحویل به قالب.
	 *
	 * @param array $data مجموعه‌داده.
	 */
	return apply_filters( 'sa_dashboard_data', $cache );
}

/**
 * بارگذاری دارایی‌های داشبورد فقط روی صفحه‌ای که قالب آن را دارد.
 */
function sa_dashboard_assets() {
	if ( ! sa_dashboard_enabled() || ! is_page_template( 'template-dashboard.php' ) ) {
		return;
	}
	wp_enqueue_style( 'sa-dashboard', SA_CHILD_URI . 'assets/css/dashboard.css', array( 'sarzaminaryan-child' ), SA_CHILD_VERSION );
	wp_enqueue_script( 'sa-map-shapes', SA_CHILD_URI . 'assets/js/iran-provinces-map.js', array(), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_enqueue_script( 'sa-dashboard', SA_CHILD_URI . 'assets/js/dashboard.js', array( 'sa-map-shapes' ), SA_CHILD_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
}
add_action( 'wp_enqueue_scripts', 'sa_dashboard_assets', 25 );
