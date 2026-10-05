<?php
/**
 * داده‌ی جغرافیایی و نقشه‌ی شهرستانی (v2.2.0)
 *
 * دو دارایی از پروژه‌ی Iran Map v0.8.0 استخراج، پاک‌سازی و اینجا وصل شده‌اند:
 *
 *   ۱. `data/region-facts.php` — ۳۱ استان با مرکز، جمعیت، مساحت، اقلیم، همسایه‌ها
 *      و مختصات؛ به‌علاوه‌ی ۴۷۵ شهرستان با مختصات. داده‌ی استان‌ها ۱۰۰٪ کامل است.
 *   ۲. `assets/svg/counties/*.svg` — ۳۱ نقشه، مرز شهرستان‌های هر استان به‌صورت
 *      `<path>` جدا با `data-slug` برابر نامک وردپرسی. از ۷۶۱ مسیر اصلی،
 *      ۲۸۶ مسیر خالی حذف و ۴۷۵ مسیر سالم نگه داشته شد.
 *
 * دو کار انجام می‌دهد:
 *
 *   الف) **پرکردن فیلدهای خالی.** با فیلتر `default_post_metadata` — یعنی فقط
 *        وقتی فیلد در دیتابیس اصلاً وجود ندارد. هر مقداری که ویراستار وارد کرده
 *        باشد، اولویت دارد و دست نمی‌خورد. هیچ نوشتنی در دیتابیس انجام نمی‌شود.
 *
 *   ب) **نقشه‌ی کلیک‌پذیر شهرستان‌ها** روی صفحه‌ی هر استان. شهرستانِ منتشرشده
 *        لینک می‌شود، بقیه خاکستری و غیرقابل‌کلیک می‌مانند — با انتشار هر
 *        شهرستان، خودبه‌خود فعال می‌شود. این همان نقشی است که صفحه‌ی استان باید
 *        داشته باشد: هاب، نه مقاله.
 *
 * خاموش‌کردن: define( 'SA_REGION_MAP', false );  در wp-config.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا ماژول فعال است؟
 *
 * @return bool
 */
function sa_region_map_enabled() {
	$on = defined( 'SA_REGION_MAP' ) ? (bool) SA_REGION_MAP : true;

	/**
	 * فعال/غیرفعال کردن داده و نقشه‌ی جغرافیایی.
	 *
	 * @param bool $on وضعیت.
	 */
	return (bool) apply_filters( 'sa_region_map_enabled', $on );
}

/**
 * بارگذاری داده‌ی جغرافیایی (یک‌بار در هر درخواست).
 *
 * @return array{provinces:array,counties:array}
 */
function sa_region_facts() {
	static $data = null;
	if ( null !== $data ) {
		return $data;
	}
	$file = SA_CHILD_DIR . 'data/region-facts.php';
	$data = is_readable( $file ) ? (array) require $file : array();
	if ( ! isset( $data['provinces'] ) ) {
		$data['provinces'] = array();
	}
	if ( ! isset( $data['counties'] ) ) {
		$data['counties'] = array();
	}
	return $data;
}

/**
 * داده‌ی یک استان.
 *
 * @param string $slug نامک.
 * @return array|null
 */
function sa_region_province( $slug ) {
	$d = sa_region_facts();
	return isset( $d['provinces'][ $slug ] ) ? $d['provinces'][ $slug ] : null;
}

/**
 * داده‌ی یک شهرستان (در همه‌ی استان‌ها می‌گردد).
 *
 * @param string $slug نامک.
 * @return array|null
 */
function sa_region_county( $slug ) {
	static $flat = null;
	if ( null === $flat ) {
		$flat = array();
		foreach ( sa_region_facts()['counties'] as $province => $rows ) {
			foreach ( (array) $rows as $s => $row ) {
				$row['province'] = $province;
				$flat[ $s ]      = $row;
			}
		}
	}
	return isset( $flat[ $slug ] ) ? $flat[ $slug ] : null;
}

/*
|------------------------------------------------------------------------------
| الف) پرکردن فیلدهای خالی
|------------------------------------------------------------------------------
| `default_post_metadata` فقط وقتی صدا زده می‌شود که کلید متا در دیتابیس نباشد.
| پس هرچه ویراستار وارد کرده، دست‌نخورده می‌ماند و هیچ داده‌ای بازنویسی نمی‌شود.
*/

/**
 * نگاشت کلید متا → فیلد داده، به تفکیک نوع محتوا.
 *
 * @param string $post_type نوع محتوا.
 * @return array<string,string>
 */
function sa_region_meta_map( $post_type ) {
	if ( 'province' === $post_type ) {
		return array(
			'sa_province_population' => 'population',
			'sa_province_area'       => 'area',
			'sa_province_latitude'   => 'lat',
			'sa_province_longitude'  => 'lng',
			'sa_province_climate'    => 'climate',
		);
	}
	if ( 'city' === $post_type ) {
		return array(
			'sa_city_latitude'  => 'lat',
			'sa_city_longitude' => 'lng',
		);
	}
	return array();
}

/**
 * پرکردن متای نبوده از داده‌ی جغرافیایی.
 *
 * @param mixed  $value     مقدار پیش‌فرض.
 * @param int    $object_id شناسه‌ی نوشته.
 * @param string $meta_key  کلید.
 * @param bool   $single    تک‌مقداری؟
 * @return mixed
 */
function sa_region_default_meta( $value, $object_id, $meta_key, $single ) {
	if ( ! sa_region_map_enabled() || '' === (string) $meta_key ) {
		return $value;
	}
	$type = get_post_type( $object_id );
	$map  = sa_region_meta_map( $type );
	if ( ! isset( $map[ $meta_key ] ) ) {
		return $value;
	}
	$slug = get_post_field( 'post_name', $object_id );
	if ( ! $slug ) {
		return $value;
	}
	$row = 'province' === $type ? sa_region_province( $slug ) : sa_region_county( $slug );
	if ( ! $row || ! isset( $row[ $map[ $meta_key ] ] ) ) {
		return $value;
	}
	$out = $row[ $map[ $meta_key ] ];
	return $single ? $out : array( $out );
}
add_filter( 'default_post_metadata', 'sa_region_default_meta', 10, 4 );

/*
|------------------------------------------------------------------------------
| ب) نقشه‌ی کلیک‌پذیر شهرستان‌ها
|------------------------------------------------------------------------------
*/

/**
 * نامک → نشانی، برای شهرهای منتشرشده (کش ۱۲ ساعته).
 *
 * @return array<string,string>
 */
function sa_region_published_cities() {
	$map = get_transient( 'sa_region_cities' );
	if ( is_array( $map ) ) {
		return $map;
	}
	$map = array();
	$ids = get_posts(
		array(
			'post_type'              => 'city',
			'post_status'            => 'publish',
			'posts_per_page'         => 1000,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $ids as $id ) {
		$name = get_post_field( 'post_name', $id );
		if ( $name ) {
			$map[ $name ] = array( get_permalink( $id ), get_the_title( $id ) );
		}
	}
	set_transient( 'sa_region_cities', $map, 12 * HOUR_IN_SECONDS );
	return $map;
}

/**
 * پاک‌کردن کش با هر تغییر وضعیت.
 */
function sa_region_flush() {
	delete_transient( 'sa_region_cities' );
}
add_action( 'transition_post_status', 'sa_region_flush' );
add_action( 'deleted_post', 'sa_region_flush' );

/**
 * ساخت نقشه‌ی SVG شهرستان‌های یک استان.
 *
 * @param string $province نامک استان.
 * @return string HTML (خالی اگر نقشه نباشد).
 */
function sa_county_map( $province ) {
	if ( ! sa_region_map_enabled() ) {
		return '';
	}
	$province = sanitize_key( $province );
	$file     = SA_CHILD_DIR . 'assets/svg/counties/' . $province . '.svg';
	if ( ! $province || ! is_readable( $file ) ) {
		return '';
	}

	$svg = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! $svg ) {
		return '';
	}

	// ابعاد ثابت حذف می‌شوند تا نقشه واکنش‌گرا شود؛ viewBox می‌ماند.
	$svg = preg_replace( '/\s(?:width|height)="[^"]*"/i', '', $svg, 2 );
	$svg = preg_replace( '/<svg\b/i', '<svg class="sa-cmap__svg" role="img" focusable="false"', $svg, 1 );

	$cities = sa_region_published_cities();
	$facts  = sa_region_facts();
	$names  = isset( $facts['counties'][ $province ] ) ? $facts['counties'][ $province ] : array();
	$linked = 0;
	$total  = 0;

	$svg = preg_replace_callback(
		'#<path\b([^>]*?)data-slug="([^"]+)"([^>]*?)/>#i',
		function ( $m ) use ( $cities, $names, &$linked, &$total ) {
			++$total;
			$slug  = $m[2];
			$label = isset( $names[ $slug ]['name'] ) ? $names[ $slug ]['name'] : $slug;
			$path  = '<path' . $m[1] . 'data-slug="' . esc_attr( $slug ) . '"' . $m[3] . '/>';

			if ( isset( $cities[ $slug ] ) ) {
				++$linked;
				list( $url, $title ) = $cities[ $slug ];
				return '<a class="sa-cmap__on" href="' . esc_url( $url ) . '"><title>' . esc_html( $title ) . '</title>' . $path . '</a>';
			}
			return '<g class="sa-cmap__off" aria-hidden="true"><title>' . esc_html( $label . ' — به‌زودی' ) . '</title>' . $path . '</g>';
		},
		$svg
	);

	if ( ! $total ) {
		return '';
	}

	$caption = sprintf(
		'%s شهرستان روی نقشه؛ %s صفحه‌ی آماده. روی هر شهرستان کلیک کنید.',
		esc_html( sa_fa_digits( (string) $total ) ),
		esc_html( sa_fa_digits( (string) $linked ) )
	);

	return '<section class="sa-cmap" id="county-map" aria-labelledby="sa-cmap-title">'
		. '<h2 id="sa-cmap-title">نقشه‌ی شهرستان‌ها</h2>'
		. '<div class="sa-cmap__frame">' . $svg . '</div>'
		. '<p class="sa-cmap__cap">' . $caption . '</p>'
		. '</section>';
}

/**
 * کد کوتاه: [sa_county_map province="isfahan"]
 *
 * @param array $atts ویژگی‌ها.
 * @return string
 */
function sa_county_map_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'province' => '' ), $atts, 'sa_county_map' );
	$slug = $atts['province'];
	if ( ! $slug && is_singular( 'province' ) ) {
		$slug = get_post_field( 'post_name', get_queried_object_id() );
	}
	return $slug ? sa_county_map( $slug ) : '';
}
add_shortcode( 'sa_county_map', 'sa_county_map_shortcode' );

/**
 * آیا ماژول نقشه شهرستان‌ها روی صفحه‌ی استان نمایش داده شود؟
 * Module 3: نمایش خودکار نقشه شهرستان‌ها حذف شد.
 * برای فعال‌سازی مجدد، فیلتر sa_county_map_auto_display را true کنید.
 *
 * @return bool
 */
function sa_county_map_auto_display() {
	return (bool) apply_filters( 'sa_county_map_auto_display', false );
}
