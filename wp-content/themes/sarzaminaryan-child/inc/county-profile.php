<?php
/**
 * County identity card (شناسنامهٔ شهرستان) — data layer.
 *
 * Canonical meta keys for the new card are the owner-specified ones below.
 * Each key falls back to the legacy county meta already used on the site
 * (sa_cty_* / sa_city_*) so existing pages keep working while the content
 * pipeline migrates to the canonical keys over time. The card is the only
 * place these numbers are shown; empty fields render nothing at all.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical county profile fields: key → label/type/legacy fallback.
 *
 * @return array<string,array{label:string,type:string,legacy:string}>
 */
function sa_county_profile_fields() {
	return array(
		'county_en'              => array( 'label' => 'نام انگلیسی شهرستان', 'type' => 'string', 'legacy' => 'sa_english_name' ),
		'province_id'            => array( 'label' => 'شناسهٔ پست استان', 'type' => 'integer', 'legacy' => 'sa_province_id' ),
		'center_fa'              => array( 'label' => 'مرکز شهرستان (فارسی)', 'type' => 'string', 'legacy' => 'sa_cty_center' ),
		'center_en'              => array( 'label' => 'مرکز شهرستان (انگلیسی)', 'type' => 'string', 'legacy' => '' ),
		'main_city'              => array( 'label' => 'مهم‌ترین شهر شهرستان (فارسی)', 'type' => 'string', 'legacy' => '' ),
		'main_city_en'           => array( 'label' => 'مهم‌ترین شهر شهرستان (انگلیسی)', 'type' => 'string', 'legacy' => '' ),
		'population'             => array( 'label' => 'جمعیت شهرستان', 'type' => 'integer', 'legacy' => 'sa_cty_population' ),
		'population_year'        => array( 'label' => 'سال سرشماری جمعیت', 'type' => 'integer', 'legacy' => 'sa_cty_census_year' ),
		'area_km2'               => array( 'label' => 'مساحت (کیلومتر مربع)', 'type' => 'number', 'legacy' => 'sa_cty_area' ),
		'elevation_m'            => array( 'label' => 'ارتفاع مرکز (متر)', 'type' => 'integer', 'legacy' => 'sa_city_elevation' ),
		'divisions_bakhsh'       => array( 'label' => 'تعداد بخش', 'type' => 'integer', 'legacy' => 'sa_cty_districts' ),
		'divisions_shahr'        => array( 'label' => 'تعداد شهر', 'type' => 'integer', 'legacy' => 'sa_cty_cities' ),
		'divisions_dehestan'     => array( 'label' => 'تعداد دهستان', 'type' => 'integer', 'legacy' => 'sa_cty_rural_districts' ),
		'divisions_rosta'        => array( 'label' => 'تعداد روستا', 'type' => 'integer', 'legacy' => 'sa_cty_villages' ),
		'history_now'            => array( 'label' => 'قدمت و امروز (متن کوتاه زیر شناسنامه)', 'type' => 'text', 'legacy' => '' ),
		'industry_main'          => array( 'label' => 'صنعت و کارخانه‌های مشهور (با سال تأسیس، کارکرد، تولیدات)', 'type' => 'text', 'legacy' => '' ),
		'agriculture_main'       => array( 'label' => 'کشاورزی و دامداری', 'type' => 'text', 'legacy' => '' ),
		'climate_short'          => array( 'label' => 'اقلیم (یک خط)', 'type' => 'string', 'legacy' => 'sa_cty_climate' ),
		'best_season'            => array( 'label' => 'بهترین فصل سفر', 'type' => 'string', 'legacy' => 'sa_cty_best_time' ),
		'neighbors_n'            => array( 'label' => 'همسایهٔ شمال (نامک یا نام)', 'type' => 'string', 'legacy' => '' ),
		'neighbors_s'            => array( 'label' => 'همسایهٔ جنوب (نامک یا نام)', 'type' => 'string', 'legacy' => '' ),
		'neighbors_e'            => array( 'label' => 'همسایهٔ شرق (نامک یا نام)', 'type' => 'string', 'legacy' => '' ),
		'neighbors_w'            => array( 'label' => 'همسایهٔ غرب (نامک یا نام)', 'type' => 'string', 'legacy' => '' ),
		'language_main'          => array( 'label' => 'زبان/گویش غالب', 'type' => 'string', 'legacy' => 'sa_cty_language' ),
		'economy_main'           => array( 'label' => 'محور اقتصاد', 'type' => 'string', 'legacy' => 'sa_cty_livelihood' ),
		'distance_to_capital_km' => array( 'label' => 'فاصله تا مرکز استان (کیلومتر)', 'type' => 'integer', 'legacy' => 'sa_cty_distance_center' ),
		'lat'                    => array( 'label' => 'عرض جغرافیایی مرکز', 'type' => 'number', 'legacy' => 'sa_city_latitude' ),
		'lng'                    => array( 'label' => 'طول جغرافیایی مرکز', 'type' => 'number', 'legacy' => 'sa_city_longitude' ),
		/* CU-1 (v2.22.49) — لایهٔ «از تجربهٔ مسافرها»: دادهٔ کاربردی‌ای که از تجربه‌های تازهٔ مردم
		 * (گوگل‌مپ، نشان، بلد، صفحه‌های اقامتگاه‌ها) درمی‌آید و متن ثابت شهرستان را زنده می‌کند.
		 * قالب خطیِ فیلدهای چندردیفی در برچسب هر فیلد آمده و خروجی خالی هیچ‌چیز رندر نمی‌کند. */
		'road_quality'           => array( 'label' => 'وضعیت جاده و دسترسی (از تجربهٔ مسافرها)', 'type' => 'string', 'legacy' => '' ),
		'mobile_internet'        => array( 'label' => 'آنتن‌دهی و اینترنت موبایل (از تجربهٔ مسافرها)', 'type' => 'string', 'legacy' => '' ),
		'access_difficulty'      => array( 'label' => 'سطح سختی دسترسی (آسان/متوسط/سخت + برای چه کسی)', 'type' => 'string', 'legacy' => '' ),
		'parking_status'         => array( 'label' => 'وضعیت پارکینگ (از تجربهٔ مسافرها)', 'type' => 'string', 'legacy' => '' ),
		'season_spring'          => array( 'label' => 'بهار: وضعیت و توصیه (یک-دو جملهٔ کاربردی)', 'type' => 'text', 'legacy' => '' ),
		'season_summer'          => array( 'label' => 'تابستان: وضعیت و توصیه (یک-دو جملهٔ کاربردی)', 'type' => 'text', 'legacy' => '' ),
		'season_autumn'          => array( 'label' => 'پاییز: وضعیت و توصیه (یک-دو جملهٔ کاربردی)', 'type' => 'text', 'legacy' => '' ),
		'season_winter'          => array( 'label' => 'زمستان: وضعیت و توصیه (یک-دو جملهٔ کاربردی)', 'type' => 'text', 'legacy' => '' ),
		'local_tips'             => array( 'label' => 'نکات بومی و هشدارها — هر خط: دسته | عنوان | متن (دسته: warning هشدار، culture فرهنگ، logistics کاربردی)', 'type' => 'text', 'legacy' => '' ),
		'extra_places'           => array( 'label' => 'جاهای دیگری که مردم می‌روند و در متن نیست — هر خط: نام | نوع | مسیر و موقعیت | نکتهٔ کاربردی | پیوند (اختیاری)', 'type' => 'text', 'legacy' => '' ),
		'people_character'       => array( 'label' => 'مردمِ این‌جا از نگاه مسافرها (فقط ویژگی مثبت و شهرت نیک؛ لحن: «از تجربهٔ کسانی که رفته‌اند…»)', 'type' => 'text', 'legacy' => '' ),
		'field_update_note'      => array( 'label' => 'یادداشت آخرین به‌روزرسانی میدانی (مثلاً: پاییز ۱۴۰۵ ـ وضعیت مسیرها و چند جای تازه)', 'type' => 'string', 'legacy' => '' ),
		'plate_code'             => array( 'label' => 'شماره پلاک خودرو (مثلاً: ایران ۱۱، ۲۲، ۳۳)', 'type' => 'string', 'legacy' => '' ),
		'phone_code'             => array( 'label' => 'پیش‌شماره تلفن ثابت (مثلاً: ۰۲۱؛ خالی یعنی کد یکپارچهٔ استان)', 'type' => 'string', 'legacy' => '' ),
	);
}

/**
 * Read one canonical value (canonical key first, legacy fallback).
 *
 * @param int    $post_id Post ID.
 * @param string $key     Canonical key.
 * @return string Trimmed value, '' when empty.
 */
function sa_county_profile_meta( $post_id, $key ) {
	$fields = sa_county_profile_fields();
	if ( ! isset( $fields[ $key ] ) ) {
		return '';
	}
	$value = trim( (string) get_post_meta( $post_id, $key, true ) );
	if ( '' === $value && '' !== $fields[ $key ]['legacy'] ) {
		$value = trim( (string) get_post_meta( $post_id, $fields[ $key ]['legacy'], true ) );
	}
	return $value;
}

/**
 * Resolve the province post for a county (province_id → registry slug).
 *
 * @param int $post_id County post ID.
 * @return WP_Post|null
 */
function sa_county_profile_province_post( $post_id ) {
	$province_id = (int) sa_county_profile_meta( $post_id, 'province_id' );
	if ( $province_id && 'province' === get_post_type( $province_id ) ) {
		$post = get_post( $province_id );
		if ( $post ) {
			return $post;
		}
	}
	if ( function_exists( 'sa_county_of_post' ) ) {
		$reg = sa_county_of_post( $post_id );
		if ( $reg && ! empty( $reg['province'] ) ) {
			$post = get_page_by_path( (string) $reg['province'], OBJECT, 'province' );
			if ( $post ) {
				return $post;
			}
		}
	}
	if ( function_exists( 'sa_get_parent' ) ) {
		$parent = sa_get_parent( $post_id, 'province' );
		if ( $parent ) {
			return $parent;
		}
	}
	return null;
}

/**
 * Permalink of a city/county page, but only when that page is really published.
 *
 * Accepts a post ID, a registry slug, a post slug or a Persian name, so the
 * canonical keys can be filled with whatever the source document used.
 *
 * @param string|int $raw Raw value.
 * @return string Permalink, '' when no published page exists.
 */
function sa_county_profile_city_url( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( is_numeric( $raw ) ) {
		$post = get_post( (int) $raw );
		return ( $post && 'city' === $post->post_type && 'publish' === $post->post_status ) ? (string) get_permalink( $post ) : '';
	}
	if ( function_exists( 'sa_county_permalink' ) ) {
		$url = sa_county_permalink( $raw );
		if ( '' !== $url ) {
			return $url;
		}
	}
	if ( function_exists( 'sa_counties' ) ) {
		foreach ( sa_counties() as $row ) {
			if ( ! isset( $row['slug'], $row['name'] ) ) {
				continue;
			}
			if ( $row['name'] === $raw || $row['slug'] === $raw ) {
				$url = function_exists( 'sa_county_permalink' ) ? sa_county_permalink( $row['slug'] ) : '';
				if ( '' !== $url ) {
					return $url;
				}
			}
		}
	}
	$post = get_page_by_path( sanitize_title( $raw ), OBJECT, 'city' );
	return ( $post && 'publish' === $post->post_status ) ? (string) get_permalink( $post ) : '';
}

/**
 * «مهم‌ترین شهر» of the county: name (fa/en) plus a link only when a
 * published page exists for it. Empty meta stays empty — the card then omits
 * the whole MAIN_CITY tile.
 *
 * @param int $post_id County post ID.
 * @return array{fa:string,en:string,url:string}
 */
function sa_county_profile_main_city( $post_id ) {
	$fa = sa_county_profile_meta( $post_id, 'main_city' );
	$en = sa_county_profile_meta( $post_id, 'main_city_en' );
	return array(
		'fa'  => $fa,
		'en'  => $en,
		'url' => '' !== $fa ? sa_county_profile_city_url( $fa ) : '',
	);
}

/**
 * Resolve a neighbour value (post ID, registry slug, post slug or Persian name)
 * to a display chip. Only published county pages get a link.
 *
 * @param string $raw Raw neighbour meta value.
 * @return array{label:string,url:string}|null
 */
function sa_county_profile_resolve_neighbor( $raw ) {
	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		return null;
	}
	if ( is_numeric( $raw ) ) {
		$post = get_post( (int) $raw );
		if ( $post && 'city' === $post->post_type ) {
			return array(
				'label' => function_exists( 'sa_county_name' ) ? sa_county_name( $post, true ) : get_the_title( $post ),
				'url'   => 'publish' === $post->post_status ? (string) get_permalink( $post ) : '',
			);
		}
		return null;
	}
	if ( function_exists( 'sa_county' ) ) {
		$row = sa_county( $raw );
		if ( $row ) {
			return array(
				'label' => 'شهرستان ' . $row['name'],
				'url'   => function_exists( 'sa_county_permalink' ) ? sa_county_permalink( $row['slug'] ) : '',
			);
		}
		foreach ( sa_counties() as $row ) {
			if ( isset( $row['name'] ) && $row['name'] === $raw ) {
				return array(
					'label' => 'شهرستان ' . $row['name'],
					'url'   => function_exists( 'sa_county_permalink' ) ? sa_county_permalink( $row['slug'] ) : '',
				);
			}
		}
	}
	$post = get_page_by_path( sanitize_title( $raw ), OBJECT, 'city' );
	if ( $post ) {
		return array(
			'label' => function_exists( 'sa_county_name' ) ? sa_county_name( $post, true ) : get_the_title( $post ),
			'url'   => 'publish' === $post->post_status ? (string) get_permalink( $post ) : '',
		);
	}
	return array( 'label' => $raw, 'url' => '' );
}

/**
 * Neighbours from canonical direction keys, falling back to the legacy
 * free-form neighbour lines (sa_cty_neighbors) matched by direction word.
 * A legacy line that names several directions («غرب و جنوب») yields ONE
 * merged chip (v2.22.85), not one chip per direction word.
 *
 * @param int $post_id County post ID.
 * @return array<string,array{label:string,url:string}> Direction key → chip.
 */
function sa_county_profile_neighbors( $post_id ) {
	$directions = array(
		'n' => 'شمال',
		's' => 'جنوب',
		'e' => 'شرق',
		'w' => 'غرب',
	);
	$out = array();
	foreach ( $directions as $key => $label ) {
		$chip = sa_county_profile_resolve_neighbor( sa_county_profile_meta( $post_id, 'neighbors_' . $key ) );
		if ( $chip ) {
			$out[ $key ] = array_merge( array( 'direction' => $label ), $chip );
		}
	}
	if ( $out || ! function_exists( 'sa_county_lines' ) ) {
		return $out;
	}
	foreach ( sa_county_lines( get_post_meta( $post_id, 'sa_cty_neighbors', true ) ) as $line ) {
		$parsed = function_exists( 'sa_county_parse_neighbor' ) ? sa_county_parse_neighbor( $line ) : null;
		if ( ! $parsed || empty( $parsed['dir'] ) ) {
			continue;
		}
		// v2.22.85: جهت‌های آزادِ این خط جمع می‌شوند — یک شهرستانِ چندجهته («غرب و جنوب»،
		// «شمال غربی») باید یک چیپ بسازد، نه برای هر جهت یک چیپ تکراری از همان شهرستان.
		$free = array();
		$used = 0;
		foreach ( $directions as $key => $label ) {
			if ( false !== strpos( (string) $parsed['dir'], $label ) ) {
				$used++;
				if ( ! isset( $out[ $key ] ) ) {
					$free[] = $key;
				}
			}
		}
		if ( empty( $free ) ) {
			continue;
		}
		if ( 1 === $used ) {
			$out[ $free[0] ] = array(
				'direction' => $directions[ $free[0] ],
				'label'     => 'شهرستان ' . $parsed['label'],
				'url'       => isset( $parsed['url'] ) ? (string) $parsed['url'] : '',
			);
			continue;
		}
		// جهتِ نمایش: متنِ خودِ خط وقتی همهٔ جهت‌هایش آزادند («غرب و جنوب»)، وگرنه فقط
		// جهت‌های آزاد به ترتیب n/s/e/w — تا جهتِ گرفته‌شده دوباره ادعا نشود.
		$dir_label = ( count( $free ) === $used )
			? (string) $parsed['dir']
			: implode( ' و ', array_intersect_key( $directions, array_flip( $free ) ) );
		$key = implode( '-', $free );
		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array(
				'direction' => $dir_label,
				'label'     => 'شهرستان ' . $parsed['label'],
				'url'       => isset( $parsed['url'] ) ? (string) $parsed['url'] : '',
			);
		}
	}
	return $out;
}

/**
 * All data the identity card needs. Any empty value stays '' and the
 * template omits that tile/row entirely.
 *
 * @param int $post_id County post ID.
 * @return array
 */
/**
 * Load one bundled data map (slug => value) shipped with the theme.
 *
 * @param string $rel Path relative to the theme root.
 * @return array
 */
function sa_county_profile_bundled_map( $rel ) {
	static $cache = array();
	if ( isset( $cache[ $rel ] ) ) {
		return $cache[ $rel ];
	}
	$base          = defined( 'SA_CHILD_DIR' ) ? SA_CHILD_DIR : dirname( __DIR__ ) . '/';
	$file          = $base . $rel;
	$cache[ $rel ] = is_readable( $file ) ? (array) require $file : array();
	return $cache[ $rel ];
}

/**
 * Candidate slugs for bundled-data lookups: the post slug, its «-city»
 * twin, and the canonical registry slug.
 *
 * @param int $post_id County post ID.
 * @return string[]
 */
function sa_county_profile_slug_candidates( $post_id ) {
	$keys = array();
	$post = get_post( $post_id );
	$slug = $post ? (string) $post->post_name : '';
	if ( '' !== $slug ) {
		$keys[] = $slug;
		$keys[] = ( '-city' === substr( $slug, -5 ) ) ? substr( $slug, 0, -5 ) : $slug . '-city';
	}
	if ( function_exists( 'sa_county_of_post' ) ) {
		$reg = sa_county_of_post( $post_id );
		if ( $reg && ! empty( $reg['slug'] ) ) {
			$keys[] = (string) $reg['slug'];
		}
	}
	return array_values( array_unique( $keys ) );
}

/**
 * Bundled «از تجربهٔ مسافرها» seed for one county (data/county-experiences.php).
 * Post meta always wins; this only fills fields the editor left empty.
 *
 * @param int $post_id County post ID.
 * @return array<string,string>
 */
function sa_county_profile_bundled( $post_id ) {
	$map = sa_county_profile_bundled_map( 'data/county-experiences.php' );
	foreach ( sa_county_profile_slug_candidates( $post_id ) as $key ) {
		if ( isset( $map[ $key ] ) && is_array( $map[ $key ] ) ) {
			return $map[ $key ];
		}
	}
	return array();
}

function sa_county_profile_data( $post_id ) {
	$post_id = (int) $post_id;
	$bundled = sa_county_profile_bundled( $post_id );
	$get     = function ( $key ) use ( $post_id, $bundled ) {
		$value = sa_county_profile_meta( $post_id, $key );
		if ( '' === $value && isset( $bundled[ $key ] ) ) {
			$value = (string) $bundled[ $key ];
		}
		return $value;
	};
	$province_post = sa_county_profile_province_post( $post_id );
	$lat           = $get( 'lat' );
	$lng           = $get( 'lng' );
	$has_coords    = '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng )
		&& (float) $lat >= -90 && (float) $lat <= 90 && (float) $lng >= -180 && (float) $lng <= 180;

	$data = array(
		'post_id'       => $post_id,
		'name_fa'       => function_exists( 'sa_county_name' ) ? sa_county_name( $post_id, true ) : get_the_title( $post_id ),
		'name_en'       => $get( 'county_en' ),
		'province_post' => $province_post,
		'province_name' => $province_post ? ( function_exists( 'sa_province_name_for_post' ) ? 'استان ' . sa_province_name_for_post( $province_post ) : get_the_title( $province_post ) ) : '',
		'province_url'  => $province_post ? (string) get_permalink( $province_post ) : '',
		'center_fa'     => $get( 'center_fa' ),
		'center_en'     => $get( 'center_en' ),
		'main_city'     => $get( 'main_city' ),
		'main_city_en'  => $get( 'main_city_en' ),
		'main_city_url' => sa_county_profile_city_url( $get( 'main_city' ) ),
		'population'    => $get( 'population' ),
		'population_year' => $get( 'population_year' ),
		/* جمعیت بدون سال سرشماری نمایش داده نمی‌شود (قانون خروجی شناسنامه). */
		'population_shown' => ( '' !== $get( 'population' ) && '' !== $get( 'population_year' ) ),
		'area_km2'      => $get( 'area_km2' ),
		'elevation_m'   => $get( 'elevation_m' ),
		'divisions'     => array_filter(
			array(
				'بخش'     => $get( 'divisions_bakhsh' ),
				'شهر'     => $get( 'divisions_shahr' ),
				'دهستان'  => $get( 'divisions_dehestan' ),
				'روستا'   => $get( 'divisions_rosta' ),
			),
			function ( $v ) {
				return '' !== (string) $v;
			}
		),
		'history_now'   => $get( 'history_now' ),
		'industry_main' => $get( 'industry_main' ),
		'agriculture_main' => $get( 'agriculture_main' ),
		'province_slug' => $province_post ? (string) $province_post->post_name : '',
		'climate'       => $get( 'climate_short' ),
		'best_season'   => $get( 'best_season' ),
		'neighbors'     => sa_county_profile_neighbors( $post_id ),
		'language'      => $get( 'language_main' ),
		'economy'       => $get( 'economy_main' ),
		'distance_km'   => $get( 'distance_to_capital_km' ),
		'lat'           => $has_coords ? (float) $lat : null,
		'lng'           => $has_coords ? (float) $lng : null,
		'map_url'       => $has_coords ? 'https://www.openstreetmap.org/?mlat=' . rawurlencode( (string) $lat ) . '&mlon=' . rawurlencode( (string) $lng ) . '#map=10/' . rawurlencode( (string) $lat ) . '/' . rawurlencode( (string) $lng ) : '',
	);
	/* CU-1 — لایهٔ تجربهٔ مسافرها: فقط وقتی دادهٔ واقعی هست رندر می‌شود. */
	$data['road_quality']      = $get( 'road_quality' );
	$data['mobile_internet']   = $get( 'mobile_internet' );
	$data['access_difficulty'] = $get( 'access_difficulty' );
	$data['parking_status']    = $get( 'parking_status' );
	$data['access_facts']      = array_filter(
		array(
			'وضعیت جاده'       => $data['road_quality'],
			'آنتن و اینترنت'   => $data['mobile_internet'],
			'سختی دسترسی'      => $data['access_difficulty'],
			'پارکینگ'          => $data['parking_status'],
		),
		function ( $v ) {
			return '' !== (string) $v;
		}
	);
	$data['seasons'] = array_filter(
		array(
			'بهار'    => $get( 'season_spring' ),
			'تابستان' => $get( 'season_summer' ),
			'پاییز'   => $get( 'season_autumn' ),
			'زمستان'  => $get( 'season_winter' ),
		),
		function ( $v ) {
			return '' !== (string) $v;
		}
	);
	$data['local_tips']        = sa_county_profile_parse_tips( $get( 'local_tips' ) );
	$data['extra_places']      = sa_county_profile_parse_places( $get( 'extra_places' ) );
	$data['plate_code']        = $get( 'plate_code' );
	if ( '' === $data['plate_code'] ) {
		// پلاک از جدول همراه قالب (data/county-plates.php)؛ متا اولویت دارد.
		$plates = sa_county_profile_bundled_map( 'data/county-plates.php' );
		foreach ( sa_county_profile_slug_candidates( $post_id ) as $key ) {
			if ( ! empty( $plates[ $key ] ) ) {
				$data['plate_code'] = (string) $plates[ $key ];
				break;
			}
		}
	}
	$data['phone_code'] = $get( 'phone_code' );
	if ( '' === $data['phone_code'] && function_exists( 'sa_province_phone_code' ) ) {
		// هم‌کدسازی: پیش‌شمارهٔ شهرستان همان کد یکپارچهٔ استان است.
		$data['phone_code'] = (string) sa_province_phone_code( (string) $data['province_slug'] );
	}
	$data['people_character']  = $get( 'people_character' );
	$data['field_update_note'] = $get( 'field_update_note' );
	// v2.22.59 — تاریخ واقعی آخرین تغییر دادهٔ تجربهٔ این شهر در قالب (ISO)،
	// برای این‌که تازگی محتوا در dateModified اسکیما هم ثبت شود.
	$data['insight_updated_at'] = $get( 'updated_at' );
	$data['has_insights']      = ! empty( $data['access_facts'] ) || ! empty( $data['seasons'] )
		|| ! empty( $data['local_tips'] ) || ! empty( $data['extra_places'] ) || '' !== $data['people_character'];

	$data['has_data'] = '' !== $data['center_fa'] || '' !== $data['main_city'] || '' !== $data['population'] || '' !== $data['area_km2']
		|| '' !== $data['elevation_m'] || ! empty( $data['divisions'] ) || '' !== $data['climate']
		|| '' !== $data['best_season'] || ! empty( $data['neighbors'] ) || '' !== $data['language']
		|| '' !== $data['economy'] || '' !== $data['distance_km'] || null !== $data['lat']
		|| '' !== $data['history_now'] || '' !== $data['industry_main'] || '' !== $data['agriculture_main'];
	return $data;
}

/**
 * Canonical keys that are currently empty for a county (for batch reports).
 *
 * @param int $post_id County post ID.
 * @return string[]
 */
function sa_county_profile_empty_fields( $post_id ) {
	$empty = array();
	foreach ( array_keys( sa_county_profile_fields() ) as $key ) {
		if ( '' === sa_county_profile_meta( $post_id, $key ) ) {
			$empty[] = $key;
		}
	}
	return $empty;
}

/**
 * Parse the «نکات بومی» textarea into structured tips (CU-1).
 *
 * One tip per line: `دسته | عنوان | متن`. The category accepts the Latin
 * keys warning/culture/logistics or the Persian هشدار/فرهنگ/کاربردی;
 * anything else falls back to logistics so a typo never breaks the card.
 * Lines without a title and text are dropped — partial data renders
 * nothing rather than half a tip.
 *
 * @param string $raw Raw textarea value.
 * @return array<int,array{category:string,cat_label:string,title:string,text:string}>
 */
function sa_county_profile_parse_tips( $raw ) {
	$out  = array();
	$cats = array(
		'warning'   => array( 'warning', 'هشدار' ),
		'culture'   => array( 'culture', 'فرهنگ و آداب' ),
		'logistics' => array( 'logistics', 'کاربردی' ),
	);
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '|', trim( (string) $line ) ) );
		if ( count( $parts ) < 3 || '' === $parts[1] || '' === $parts[2] ) {
			continue;
		}
		$cat = 'logistics';
		foreach ( $cats as $key => $pair ) {
			if ( $parts[0] === $key || $parts[0] === $pair[1] || ( 'هشدار' === $parts[0] && 'warning' === $key ) ) {
				$cat = $key;
				break;
			}
		}
		if ( 'هشدار' === $parts[0] ) {
			$cat = 'warning';
		} elseif ( 'فرهنگ' === $parts[0] ) {
			$cat = 'culture';
		} elseif ( 'کاربردی' === $parts[0] ) {
			$cat = 'logistics';
		}
		$out[] = array(
			'category'  => $cat,
			'cat_label' => $cats[ $cat ][1],
			'title'     => $parts[1],
			'text'      => $parts[2],
			// ستون پنجم (۲.۲۲.۹۴): «شاهدِ» خودِ مردم — «متن» (منبع، تاریخ).
			// خالی یعنی نکته از دادهٔ رسمی آمده، نه از تجربهٔ مسافر.
			'evidence'  => isset( $parts[3] ) ? $parts[3] : '',
		);
	}
	return $out;
}

/**
 * Parse the «جاهای دیگر» textarea into structured places (CU-1).
 *
 * One place per line: `نام | نوع | مسیر و موقعیت | نکتهٔ کاربردی | پیوند`.
 * Only the name is required; the link is optional and must be a valid URL
 * (esc_url still applies at render time). These are places travellers
 * actually go that the article body does not cover yet.
 *
 * @param string $raw Raw textarea value.
 * @return array<int,array{name:string,kind:string,access:string,note:string,url:string}>
 */
function sa_county_profile_parse_places( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '|', trim( (string) $line ) ) );
		if ( '' === ( isset( $parts[0] ) ? $parts[0] : '' ) ) {
			continue;
		}
		$out[] = array(
			'name'   => $parts[0],
			'kind'   => isset( $parts[1] ) ? $parts[1] : '',
			'access' => isset( $parts[2] ) ? $parts[2] : '',
			'note'   => isset( $parts[3] ) ? $parts[3] : '',
			'url'    => isset( $parts[4] ) ? $parts[4] : '',
		);
	}
	return $out;
}

/**
 * Important attractions for the identity-card table.
 *
 * Source of truth is the existing relation (attraction.sa_city_id → this
 * county), so anything published as a دیدنی of this county appears here
 * automatically; legacy POI lines fill in places that have no post yet.
 * Each linked attraction also knows its province through the county, so
 * county → attraction → province linking needs no extra fields.
 *
 * @param int $post_id County post ID.
 * @param int $limit   Max rows.
 * @return array<int,array{name:string,en:string,type:string,url:string}>
 */
function sa_county_profile_attractions( $post_id, $limit = 8 ) {
	$rows = array();
	$seen = array();
	$norm = static function ( $s ) {
		$s = trim( (string) preg_replace( '/\s*\(.*?\)\s*/u', ' ', (string) $s ) );
		$s = str_replace( array( 'ي', 'ك', "\xE2\x80\x8C" ), array( 'ی', 'ک', ' ' ), $s );
		return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
	};
	$county_bare = function_exists( 'sa_county_name' ) ? trim( (string) sa_county_name( (int) $post_id, false ) ) : '';
	$mark_seen   = static function ( $name ) use ( &$seen, $norm, $county_bare ) {
		$seen[ $name ]          = true;
		$n                      = $norm( $name );
		$seen[ $n ]             = true;
		if ( '' !== $county_bare ) {
			$short = trim( (string) preg_replace( '/\s+' . preg_quote( $norm( $county_bare ), '/' ) . '$/u', '', $n ) );
			if ( '' !== $short ) {
				$seen[ $short ] = true;
			}
		}
	};
	if ( function_exists( 'sa_get_children' ) ) {
		foreach ( (array) sa_get_children( (int) $post_id, 'attraction' ) as $post ) {
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$name = get_the_title( $post );
			if ( '' === $name || isset( $seen[ $name ] ) || isset( $seen[ $norm( $name ) ] ) ) {
				continue;
			}
			$mark_seen( $name );
			$type           = '';
			if ( function_exists( 'get_the_terms' ) ) {
				$terms = get_the_terms( $post->ID, 'attraction_type' );
				if ( is_array( $terms ) && $terms ) {
					$type = $terms[0]->name;
				}
			}
			$rows[] = array(
				'name' => $name,
				'en'   => trim( (string) get_post_meta( $post->ID, 'sa_english_name', true ) ),
				'type' => $type,
				'url'  => (string) get_permalink( $post ),
			);
		}
	}
	$groups = array(
		'sa_cty_poi_nature'     => 'طبیعت‌گردی',
		'sa_cty_poi_offbeat'    => 'بکر و کمتر دیده‌شده',
		'sa_cty_poi_recreation' => 'تفریحی و خانوادگی',
		'sa_cty_poi_heritage'   => 'تاریخی و زیارتی',
	);
	foreach ( $groups as $key => $label ) {
		if ( ! function_exists( 'sa_county_lines' ) || ! function_exists( 'sa_county_parse_poi' ) ) {
			break;
		}
		foreach ( sa_county_lines( get_post_meta( (int) $post_id, $key, true ) ) as $line ) {
			$poi = sa_county_parse_poi( $line );
			if ( empty( $poi['name'] ) || isset( $seen[ $poi['name'] ] ) || isset( $seen[ $norm( $poi['name'] ) ] ) ) {
				continue;
			}
			if ( 'sa_cty_poi_offbeat' === $key && preg_match( '/آبشار\s+بیشه|دریاچه(?:\s+ٔ|ی)?\s+گهر/u', (string) $poi['name'] ) ) {
				continue;
			}
			$mark_seen( $poi['name'] );
			$rows[]               = array(
				'name' => $poi['name'],
				'en'   => '',
				'type' => $label,
				'url'  => '',
			);
		}
	}
	return array_slice( $rows, 0, max( 1, (int) $limit ) );
}

/**
 * Published counties of the same province (for the هم‌استانی chips row).
 *
 * @param int $post_id County post ID.
 * @param int $limit   Max chips.
 * @return array<int,array{label:string,url:string}>
 */
function sa_county_profile_sibling_cities( $post_id, $limit = 12 ) {
	$out  = array();
	$data = sa_county_profile_data( $post_id );
	if ( ! function_exists( 'sa_counties' ) || '' === $data['province_slug'] ) {
		return $out;
	}
	foreach ( sa_counties() as $row ) {
		if ( ! isset( $row['province'], $row['slug'], $row['name'] ) || $row['province'] !== $data['province_slug'] ) {
			continue;
		}
		$current = function_exists( 'sa_county_of_post' ) ? sa_county_of_post( $post_id ) : null;
		if ( $current && $current['slug'] === $row['slug'] ) {
			continue;
		}
		$url = function_exists( 'sa_county_permalink' ) ? sa_county_permalink( $row['slug'] ) : '';
		if ( '' === $url ) {
			continue; // Only counties that really have a published page are linked.
		}
		$out[] = array( 'label' => 'شهرستان ' . $row['name'], 'url' => $url );
		if ( count( $out ) >= max( 1, (int) $limit ) ) {
			break;
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Meta registration + editor box for the canonical keys.
 * ---------------------------------------------------------------------- */

function sa_county_profile_register_meta() {
	foreach ( sa_county_profile_fields() as $key => $field ) {
		$type = 'integer' === $field['type'] ? 'integer' : ( 'number' === $field['type'] ? 'number' : 'string' );
		register_post_meta(
			'city',
			$key,
			array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'sa_county_profile_register_meta', 7 );

function sa_county_profile_add_box() {
	add_meta_box( 'sa-county-identity', 'شناسنامهٔ شهرستان (کلیدهای استاندارد)', 'sa_county_profile_render_box', 'city', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'sa_county_profile_add_box' );

/**
 * @param WP_Post $post Post.
 */
function sa_county_profile_render_box( $post ) {
	wp_nonce_field( 'sa_county_identity', 'sa_county_identity_nonce' );
	echo '<p class="description">این کلیدها منبع یگانهٔ کارت «شناسنامهٔ شهرستان» در بالای مقاله‌اند و بر کلیدهای قدیمی (sa_cty_*) اولویت دارند. خانهٔ خالی روی سایت هیچ‌چیز نشان نمی‌دهد؛ دادهٔ بی‌منبع را خالی بگذارید.</p>';
	echo '<table class="form-table"><tbody>';
	foreach ( sa_county_profile_fields() as $key => $field ) {
		$value = get_post_meta( $post->ID, $key, true );
		if ( 'text' === $field['type'] ) {
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label><br><code>%1$s</code></th>'
				. '<td><textarea id="%1$s" name="%1$s" rows="3" class="large-text">%3$s</textarea></td></tr>',
				esc_attr( $key ),
				esc_html( $field['label'] ),
				esc_textarea( (string) $value )
			);
			continue;
		}
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label><br><code>%1$s</code></th>'
			. '<td><input type="text" id="%1$s" name="%1$s" value="%3$s" class="regular-text" dir="%4$s"></td></tr>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( (string) $value ),
			'string' === $field['type'] ? 'rtl' : 'ltr'
		);
	}
	echo '</tbody></table>';
}

function sa_county_profile_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sa_county_identity_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sa_county_identity_nonce'] ) ), 'sa_county_identity' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( sa_county_profile_fields() as $key => $field ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( 'integer' === $field['type'] ) {
			$value = '' === trim( (string) $raw ) ? '' : (string) absint( $raw );
		} elseif ( 'number' === $field['type'] ) {
			$value = '' === trim( (string) $raw ) ? '' : (string) (float) $raw;
		} elseif ( 'text' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
		// Keep the legacy relation key in sync so menus/galleries keep working.
		if ( 'province_id' === $key ) {
			if ( '' === $value ) {
				delete_post_meta( $post_id, 'sa_province_id' );
			} else {
				update_post_meta( $post_id, 'sa_province_id', $value );
			}
		}
	}
}
add_action( 'save_post_city', 'sa_county_profile_save' );
