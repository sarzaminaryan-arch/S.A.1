<?php
/**
 * Cyber-Tech Light-Mode County Identity Card — «شناسنامه شهرستان».
 *
 * Dedicated Indigo-Violet technological tabular card for Iran's 483 counties (`city` CPT):
 * tiles `01 // EN_NAME` … `11 // MAIN_CITY`, the attractions table, the same-province chips
 * and the three short under-card notes. Values come from the canonical meta layer in
 * `inc/county-profile.php` (legacy `sa_cty_*` / `sa_city_*` keys as fallback, registry last).
 * Output rules: an empty field removes its tile completely, population is printed only with
 * its census year, invalid coordinates build no map link, and neighbours / the main city are
 * linked only when a published page exists. See `docs/county-identity-roadmap.md`.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sa_id = get_the_ID();
if ( 'city' !== get_post_type( $sa_id ) ) {
	return;
}

if ( function_exists( 'sa_toc_register' ) ) {
	sa_toc_register( 'شناسنامه شهرستان', 'county-identity', 5 );
}

$sa_slug        = (string) get_post_field( 'post_name', $sa_id );
$sa_reg         = function_exists( 'sa_county_of_post' ) ? sa_county_of_post( $sa_id ) : null;
$sa_county_bare = function_exists( 'sa_county_name' ) ? sa_county_name( $sa_id, false ) : get_the_title( $sa_id );
$sa_county_full = function_exists( 'sa_county_name' ) ? sa_county_name( $sa_id, true ) : ( 'شهرستان ' . $sa_county_bare );

$sa_prov_post = function_exists( 'sa_get_parent' ) ? sa_get_parent( $sa_id, 'province' ) : null;
$sa_prov_id   = ( $sa_prov_post instanceof WP_Post ) ? (int) $sa_prov_post->ID : ( is_numeric( $sa_prov_post ) ? (int) $sa_prov_post : 0 );
$sa_prov_slug = '';
$sa_prov_name = '';
if ( is_array( $sa_reg ) && ! empty( $sa_reg['province'] ) ) {
	$sa_prov_slug = (string) $sa_reg['province'];
	$sa_prov_name = function_exists( 'sa_province_name' ) ? sa_province_name( $sa_prov_slug ) : '';
}
if ( '' === $sa_prov_slug && $sa_prov_id ) {
	$sa_prov_slug = (string) get_post_field( 'post_name', $sa_prov_id );
}
if ( '' === $sa_prov_name && $sa_prov_id ) {
	$sa_prov_name = function_exists( 'sa_province_name_for_post' )
		? sa_province_name_for_post( $sa_prov_id, false )
		: (string) get_the_title( $sa_prov_id );
}
$sa_prov_en = ( '' !== $sa_prov_slug && function_exists( 'sa_cyber_id_province_en' ) )
	? sa_cyber_id_province_en( $sa_prov_slug )
	: '';

$sa_fallback = function_exists( 'sa_cyber_id_county_fallback' )
	? sa_cyber_id_county_fallback( $sa_slug, $sa_prov_slug )
	: array();

// Canonical identity-card data layer (inc/county-profile.php): every tile below
// reads the canonical keys first and the legacy sa_cty_* / sa_city_* keys second.
// An empty value never invents data — its tile is dropped before rendering.
$sa_profile_ext = function_exists( 'sa_county_profile_data' ) ? sa_county_profile_data( $sa_id ) : array();
$sa_pget        = function ( $sa_key ) use ( $sa_profile_ext ) {
	return isset( $sa_profile_ext[ $sa_key ] ) ? $sa_profile_ext[ $sa_key ] : '';
};
$sa_num = function ( $sa_value, $sa_decimals = 1 ) {
	if ( '' === $sa_value || null === $sa_value ) {
		return '';
	}
	$sa_value = function_exists( 'sa_en_digits' ) ? sa_en_digits( $sa_value ) : $sa_value;
	if ( ! is_numeric( $sa_value ) ) {
		return function_exists( 'sa_digits' ) ? sa_digits( (string) $sa_value ) : (string) $sa_value;
	}
	return function_exists( 'sa_number' ) ? sa_number( $sa_value, $sa_decimals ) : (string) $sa_value;
};

// 1) English Name.
$sa_en_raw = trim( (string) $sa_pget( 'name_en' ) );
if ( '' === $sa_en_raw ) {
	$sa_en_raw = trim( (string) get_post_meta( $sa_id, 'sa_english_name', true ) );
}
if ( '' === $sa_en_raw && function_exists( 'sa_province_profile_english_name' ) ) {
	$sa_en_raw = sa_province_profile_english_name( $sa_slug );
}
$sa_en_county = '' !== $sa_en_raw
	? ( false === stripos( $sa_en_raw, 'county' ) ? $sa_en_raw . ' County' : $sa_en_raw )
	: '';
$sa_en_short  = preg_replace( '/\s+County$/i', '', $sa_en_county );

// 2) Province (Persian + English, linked).
$sa_prov_html = '';
if ( '' !== $sa_prov_name ) {
	$sa_prov_label = 'استان ' . esc_html( $sa_prov_name );
	if ( '' !== $sa_prov_en ) {
		$sa_prov_label .= ' <span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( $sa_prov_en ) . ')</span>';
	}
	if ( $sa_prov_id && 'publish' === get_post_status( $sa_prov_id ) ) {
		$sa_prov_html = '<a class="sa-cyber-id__cell-link" href="' . esc_url( get_permalink( $sa_prov_id ) ) . '">' . $sa_prov_label . '</a>';
	} else {
		$sa_prov_html = $sa_prov_label;
	}
}

// 3) Center City (canonical center_fa / center_en — never inferred from the county name).
$sa_center    = trim( (string) $sa_pget( 'center_fa' ) );
$sa_center_en = trim( (string) $sa_pget( 'center_en' ) );
$sa_center_html = '';
if ( '' !== $sa_center ) {
	$sa_center_html = esc_html( $sa_center );
}
if ( '' !== $sa_center_en ) {
	$sa_center_html .= ( '' !== $sa_center_html ? ' ' : '' )
		. '<span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( $sa_center_en ) . ')</span>';
}

// 4) Population — shown only together with its census year (قانون خروجی شناسنامه).
$sa_pop_val  = '';
$sa_pop_num  = (int) preg_replace( '/[^\d]/', '', function_exists( 'sa_en_digits' ) ? sa_en_digits( (string) $sa_pget( 'population' ) ) : (string) $sa_pget( 'population' ) );
$sa_pop_year = (int) preg_replace( '/[^\d]/', '', function_exists( 'sa_en_digits' ) ? sa_en_digits( (string) $sa_pget( 'population_year' ) ) : (string) $sa_pget( 'population_year' ) );
if ( $sa_pop_num > 0 && $sa_pop_year > 0 ) {
	$sa_pop_val = $sa_num( $sa_pop_num, 0 ) . ' نفر (سرشماری ' . ( function_exists( 'sa_digits' ) ? sa_digits( (string) $sa_pop_year ) : sa_fa_digits( (string) $sa_pop_year ) ) . ')';
} elseif ( ! empty( $sa_fallback['population'] ) && preg_match( '/سرشماری\s*[0-9۰-۹٠-٩]{4}/u', (string) $sa_fallback['population'] ) ) {
	// Curated registry value that already carries its own census year.
	$sa_pop_val = (string) $sa_fallback['population'];
}

// 5) Elevation & Area.
$sa_elev     = trim( (string) $sa_pget( 'elevation_m' ) );
$sa_area     = trim( (string) $sa_pget( 'area_km2' ) );
$sa_elev_pts = array();
if ( '' !== $sa_elev ) {
	$sa_elev_pts[] = 'ارتفاع ' . $sa_num( $sa_elev, 0 ) . ( false === mb_strpos( $sa_elev, 'متر' ) ? ' متر' : '' );
} elseif ( ! empty( $sa_fallback['elevation'] ) ) {
	$sa_elev_pts[] = 'ارتفاع ' . (string) $sa_fallback['elevation'];
}
if ( '' !== $sa_area ) {
	$sa_elev_pts[] = 'مساحت ' . $sa_num( $sa_area, 1 ) . ' کیلومتر مربع';
} elseif ( ! empty( $sa_fallback['area'] ) ) {
	$sa_elev_pts[] = 'مساحت ' . (string) $sa_fallback['area'];
}
$sa_elev_area_val = implode( ' · ', $sa_elev_pts );

// 6) Coordinates — validated first; invalid coordinates produce no map link at all.
$sa_coord_valid = static function ( $sa_lat, $sa_lng ) {
	return '' !== $sa_lat && '' !== $sa_lng && is_numeric( $sa_lat ) && is_numeric( $sa_lng )
		&& (float) $sa_lat >= -90 && (float) $sa_lat <= 90
		&& (float) $sa_lng >= -180 && (float) $sa_lng <= 180;
};
if ( ! empty( $sa_profile_ext ) ) {
	$sa_lat = ( null !== $sa_pget( 'lat' ) ) ? (string) $sa_pget( 'lat' ) : '';
	$sa_lng = ( null !== $sa_pget( 'lng' ) ) ? (string) $sa_pget( 'lng' ) : '';
} else {
	$sa_lat = trim( (string) get_post_meta( $sa_id, 'sa_city_latitude', true ) );
	$sa_lng = trim( (string) get_post_meta( $sa_id, 'sa_city_longitude', true ) );
	if ( '' === $sa_lat && ! empty( $sa_fallback['lat'] ) ) {
		$sa_lat = (string) $sa_fallback['lat'];
	}
	if ( '' === $sa_lng && ! empty( $sa_fallback['lng'] ) ) {
		$sa_lng = (string) $sa_fallback['lng'];
	}
}
if ( ! $sa_coord_valid( $sa_lat, $sa_lng ) ) {
	$sa_lat = '';
	$sa_lng = '';
}
$sa_coords_str = ( '' !== $sa_lat && '' !== $sa_lng )
	? ( rtrim( rtrim( number_format( (float) $sa_lat, 4, '.', '' ), '0' ), '.' ) . '° N, '
		. rtrim( rtrim( number_format( (float) $sa_lng, 4, '.', '' ), '0' ), '.' ) . '° E' )
	: '';
$sa_map_url = '';
if ( '' !== $sa_lat && '' !== $sa_lng ) {
	$sa_map_url = function_exists( 'sa_map_url' ) ? sa_map_url( $sa_id ) : '';
	if ( '' === $sa_map_url ) {
		$sa_map_url = 'https://www.google.com/maps?q=' . rawurlencode( $sa_lat . ',' . $sa_lng );
	}
}

// 7) Divisions — canonical divisions_* keys first, legacy sa_cty_* counters
// second (both resolved by the data layer), curated registry text last.
$sa_div_parts = array();
foreach ( (array) $sa_pget( 'divisions' ) as $sa_dunit => $sa_dval ) {
	$sa_dval = trim( (string) $sa_dval );
	if ( '' === $sa_dval ) {
		continue;
	}
	$sa_div_parts[] = $sa_num( $sa_dval, 0 ) . ' ' . $sa_dunit;
}
if ( empty( $sa_div_parts ) ) {
	foreach ( array(
		'sa_cty_districts'       => 'بخش',
		'sa_cty_rural_districts' => 'دهستان',
		'sa_cty_cities'          => 'شهر',
		'sa_cty_villages'        => 'روستا',
	) as $sa_dkey => $sa_dunit ) {
		$sa_dval = (int) get_post_meta( $sa_id, $sa_dkey, true );
		if ( $sa_dval > 0 ) {
			$sa_div_parts[] = $sa_num( $sa_dval, 0 ) . ' ' . $sa_dunit;
		}
	}
}
$sa_div_val = ! empty( $sa_div_parts )
	? implode( ' · ', $sa_div_parts )
	: ( ! empty( $sa_fallback['divisions'] ) ? (string) $sa_fallback['divisions'] : '' );

// 8) Climate & Best Season (climate_short / best_season with legacy fallback).
$sa_climate = trim( (string) $sa_pget( 'climate' ) );
if ( '' === $sa_climate ) {
	$sa_climate = trim( (string) get_post_meta( $sa_id, 'sa_cty_climate', true ) );
}
if ( '' === $sa_climate && ! empty( $sa_fallback['climate'] ) ) {
	$sa_climate = (string) $sa_fallback['climate'];
}
$sa_best_time = trim( (string) $sa_pget( 'best_season' ) );
if ( '' === $sa_best_time ) {
	$sa_best_time = trim( (string) get_post_meta( $sa_id, 'sa_cty_best_time', true ) );
}
if ( '' === $sa_best_time ) {
	$sa_seasons = get_the_terms( $sa_id, 'travel_season' );
	if ( is_array( $sa_seasons ) && ! is_wp_error( $sa_seasons ) && ! empty( $sa_seasons ) ) {
		$sa_best_time = implode( '، ', wp_list_pluck( $sa_seasons, 'name' ) );
	} elseif ( ! empty( $sa_fallback['season'] ) ) {
		$sa_best_time = (string) $sa_fallback['season'];
	}
}
$sa_clim_season_pts = array_filter( array( $sa_climate, $sa_best_time ? 'فصل سفر: ' . $sa_best_time : '' ), 'strlen' );
$sa_clim_season_val = implode( ' | ', $sa_clim_season_pts );

// 9) Language & Livelihood (language_main / economy_main with legacy fallback).
$sa_lang = trim( (string) $sa_pget( 'language' ) );
if ( '' === $sa_lang ) {
	$sa_lang = trim( (string) get_post_meta( $sa_id, 'sa_cty_language', true ) );
}
if ( '' === $sa_lang && ! empty( $sa_fallback['language'] ) ) {
	$sa_lang = (string) $sa_fallback['language'];
}
$sa_live = trim( (string) $sa_pget( 'economy' ) );
if ( '' === $sa_live ) {
	$sa_live = trim( (string) get_post_meta( $sa_id, 'sa_cty_livelihood', true ) );
}
if ( '' === $sa_live && ! empty( $sa_fallback['livelihood'] ) ) {
	$sa_live = (string) $sa_fallback['livelihood'];
}
$sa_cult_pts = array_filter( array( $sa_lang, $sa_live ), 'strlen' );
$sa_cult_val = implode( ' — ', $sa_cult_pts );

// 10) Distance, access & neighbours. Canonical direction keys (neighbors_n/s/e/w)
// are linked only when the neighbour county really has a published page; the
// legacy free-form lines fill the gaps and the curated registry text is last.
$sa_nb_html = array();
foreach ( (array) $sa_pget( 'neighbors' ) as $sa_nb ) {
	if ( empty( $sa_nb['label'] ) ) {
		continue;
	}
	$sa_nb_lbl = esc_html( (string) $sa_nb['label'] );
	if ( ! empty( $sa_nb['direction'] ) ) {
		$sa_nb_lbl .= ' (' . esc_html( (string) $sa_nb['direction'] ) . ')';
	}
	$sa_nb_html[] = ! empty( $sa_nb['url'] )
		? '<a class="sa-cyber-id__cell-link" href="' . esc_url( (string) $sa_nb['url'] ) . '">' . $sa_nb_lbl . '</a>'
		: $sa_nb_lbl;
}
$sa_nb_str = implode( ' · ', $sa_nb_html );
if ( '' === $sa_nb_str && function_exists( 'sa_county_lines' ) && function_exists( 'sa_county_parse_neighbor' ) ) {
	$sa_nb_labels = array();
	foreach ( sa_county_lines( get_post_meta( $sa_id, 'sa_cty_neighbors', true ) ) as $sa_nb_line ) {
		$sa_nb = sa_county_parse_neighbor( $sa_nb_line );
		if ( ! empty( $sa_nb['label'] ) ) {
			$sa_nb_lbl = esc_html( (string) $sa_nb['label'] );
			if ( ! empty( $sa_nb['dir'] ) ) {
				$sa_nb_lbl .= ' (' . esc_html( (string) $sa_nb['dir'] ) . ')';
			}
			$sa_nb_labels[] = ! empty( $sa_nb['url'] )
				? '<a class="sa-cyber-id__cell-link" href="' . esc_url( (string) $sa_nb['url'] ) . '">' . $sa_nb_lbl . '</a>'
				: $sa_nb_lbl;
		}
	}
	$sa_nb_str = implode( ' · ', $sa_nb_labels );
}
if ( '' === $sa_nb_str && ! empty( $sa_fallback['neighbors'] ) ) {
	$sa_nb_str = esc_html( (string) $sa_fallback['neighbors'] );
}

$sa_acc_pts = array();
foreach ( array( 'sa_access_road' => 'جاده‌ای', 'sa_access_rail' => 'ریلی', 'sa_access_air' => 'هوایی' ) as $sa_akey => $sa_albl ) {
	$sa_aval = trim( (string) get_post_meta( $sa_id, $sa_akey, true ) );
	if ( '' !== $sa_aval ) {
		$sa_acc_pts[] = $sa_albl;
	}
}
$sa_acc_str = ! empty( $sa_acc_pts )
	? ( 'مسیرهای فعال: ' . implode( '، ', $sa_acc_pts ) )
	: ( ! empty( $sa_fallback['access'] ) ? (string) $sa_fallback['access'] : '' );

$sa_dist_raw = function_exists( 'sa_en_digits' ) ? sa_en_digits( (string) $sa_pget( 'distance_km' ) ) : (string) $sa_pget( 'distance_km' );
$sa_dist_km  = (int) preg_replace( '/[^\d]/', '', $sa_dist_raw );
$sa_dist_str = $sa_dist_km > 0 ? ( 'فاصله تا مرکز استان: ' . $sa_num( $sa_dist_km, 0 ) . ' کیلومتر' ) : '';

$sa_acc_nb_val = implode(
	' | ',
	array_filter( array( $sa_dist_str, $sa_acc_str, $sa_nb_str ? 'همسایه‌ها: ' . $sa_nb_str : '' ), 'strlen' )
);

// 11) Main city (مهم‌ترین شهر شهرستان) — canonical main_city / main_city_en,
// linked only when that city has a published page; empty meta means no tile.
$sa_main_city = function_exists( 'sa_county_profile_main_city' )
	? sa_county_profile_main_city( $sa_id )
	: array(
		'fa'  => trim( (string) get_post_meta( $sa_id, 'main_city', true ) ),
		'en'  => trim( (string) get_post_meta( $sa_id, 'main_city_en', true ) ),
		'url' => '',
	);
$sa_main_city_html = '';
if ( '' !== $sa_main_city['fa'] ) {
	$sa_main_city_html = '' !== $sa_main_city['url']
		? '<a class="sa-cyber-id__cell-link" href="' . esc_url( (string) $sa_main_city['url'] ) . '">' . esc_html( (string) $sa_main_city['fa'] ) . '</a>'
		: esc_html( (string) $sa_main_city['fa'] );
}
if ( '' !== $sa_main_city['en'] ) {
	$sa_main_city_html .= ( '' !== $sa_main_city_html ? ' ' : '' )
		. '<span class="sa-cyber-id__en-inline" dir="ltr">(' . esc_html( (string) $sa_main_city['en'] ) . ')</span>';
}

$sa_county_cells = array(
	array(
		'code'  => '01 // EN_NAME',
		'label' => 'نام انگلیسی شهرستان',
		'value' => '' !== $sa_en_county ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_en_county ) . '</span>' : '',
		'html'  => true,
		'span'  => '',
	),
	array(
		'code'  => '02 // PROVINCE',
		'label' => 'استان',
		'value' => $sa_prov_html,
		'html'  => true,
		'span'  => '',
	),
	array(
		'code'  => '03 // CENTER_CITY',
		'label' => 'مرکز شهرستان',
		'value' => $sa_center_html,
		'html'  => true,
		'span'  => '',
	),
	array(
		'code'  => '04 // POPULATION',
		'label' => 'جمعیت شهرستان',
		'value' => $sa_pop_val,
		'html'  => false,
		'span'  => '',
	),
	array(
		'code'  => '05 // ELEV_AREA',
		'label' => 'ارتفاع و مساحت',
		'value' => $sa_elev_area_val,
		'html'  => false,
		'span'  => '',
	),
	array(
		'code'  => '06 // GEO_COORDS',
		'label' => 'مختصات جغرافیایی',
		'value' => '' !== $sa_coords_str ? '<span class="sa-cyber-id__ltr" dir="ltr">' . esc_html( $sa_coords_str ) . '</span>' : '',
		'html'  => true,
		'span'  => '',
	),
	array(
		'code'  => '07 // DIVISIONS',
		'label' => 'تقسیمات کشوری',
		'value' => $sa_div_val,
		'html'  => false,
		'span'  => 'wide',
	),
	array(
		'code'  => '08 // CLIMATE_SEASON',
		'label' => 'اقلیم و بهترین فصل سفر',
		'value' => $sa_clim_season_val,
		'html'  => false,
		'span'  => 'wide',
	),
	array(
		'code'  => '09 // CULTURE_ECONOMY',
		'label' => 'زبان و معیشت غالب',
		'value' => $sa_cult_val,
		'html'  => false,
		'span'  => 'wide',
	),
	array(
		'code'  => '10 // ACCESS_NEIGHBORS',
		'label' => 'راه‌های دسترسی و همسایه‌های شهرستان',
		'value' => $sa_acc_nb_val,
		'html'  => true,
		'span'  => 'full',
	),
	array(
		'code'  => '11 // MAIN_CITY',
		'label' => 'مهم‌ترین شهر شهرستان',
		'value' => $sa_main_city_html,
		'html'  => true,
		'span'  => '',
	),
);

// قانون خروجی شناسنامه: هر فیلد خالی یعنی حذف کامل همان خانه — هیچ «نامشخص»،
// «-» یا نشان فرایندی در کارت چاپ نمی‌شود.
$sa_county_cells = array_values(
	array_filter(
		$sa_county_cells,
		function ( $sa_cell ) {
			return '' !== trim( wp_strip_all_tags( (string) $sa_cell['value'] ) );
		}
	)
);

$sa_poi_buckets = array(
	'sa_cty_poi_nature'     => 'طبیعت‌گردی (آبشار، تنگه، غار، دریاچه، جنگل)',
	'sa_cty_poi_offbeat'    => 'نقاط بکر و کمتر دیده‌شده',
	'sa_cty_poi_recreation' => 'جاهای تفریحی و خانوادگی',
	'sa_cty_poi_heritage'   => 'آثار تاریخی و زیارتی',
);
$sa_pois = array();
if ( function_exists( 'sa_county_lines' ) && function_exists( 'sa_county_parse_poi' ) ) {
	foreach ( $sa_poi_buckets as $sa_pkey => $sa_ptitle ) {
		$sa_items = array();
		foreach ( sa_county_lines( get_post_meta( $sa_id, $sa_pkey, true ) ) as $sa_pline ) {
			$sa_pitem = sa_county_parse_poi( $sa_pline );
			if ( ! empty( $sa_pitem['name'] ) ) {
				$sa_items[] = $sa_pitem;
			}
		}
		if ( ! empty( $sa_items ) ) {
			$sa_pois[ $sa_ptitle ] = $sa_items;
		}
	}
}
// Attractions table (canonical meta layer): published دیدنی posts of this
// county appear automatically and linked; sibling counties of the same
// province plus the short under-card prose come from the same data layer
// ($sa_profile_ext is built once, above, for the identity-card tiles).
$sa_attractions = function_exists( 'sa_county_profile_attractions' ) ? sa_county_profile_attractions( $sa_id ) : array();
$sa_sibling_cities = function_exists( 'sa_county_profile_sibling_cities' ) ? sa_county_profile_sibling_cities( $sa_id ) : array();
$sa_linked_names = array();
foreach ( $sa_attractions as $sa_attr_row ) {
	$sa_linked_names[ $sa_attr_row['name'] ] = true;
}
if ( $sa_linked_names && ! empty( $sa_pois ) ) {
	foreach ( $sa_pois as $sa_ptitle => $sa_plist ) {
		$sa_pois[ $sa_ptitle ] = array_values(
			array_filter(
				$sa_plist,
				function ( $sa_pitem ) use ( $sa_linked_names ) {
					return empty( $sa_linked_names[ $sa_pitem['name'] ] );
				}
			)
		);
		if ( empty( $sa_pois[ $sa_ptitle ] ) ) {
			unset( $sa_pois[ $sa_ptitle ] );
		}
	}
}
?>
<section class="sa-cyber-id sa-cyber-id--county" id="county-identity" aria-labelledby="sa-county-identity-title">
	<div class="sa-cyber-id__hud-bar">
		<div class="sa-cyber-id__badges">
			<span class="sa-cyber-id__badge">شناسنامه</span>
			<span class="sa-cyber-id__type-tag">شناسنامه شهرستان</span>
			<span class="sa-cyber-id__sys-code" dir="ltr">SYS.ID // CTY-<?php echo esc_html( strtoupper( $sa_slug ? $sa_slug : 'IR' ) ); ?></span>
		</div>
		<div class="sa-cyber-id__hud-meta">
			<?php if ( $sa_prov_id && 'publish' === get_post_status( $sa_prov_id ) ) : ?>
				<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( get_permalink( $sa_prov_id ) ); ?>">
					<span>استان <?php echo esc_html( $sa_prov_name ); ?></span>
					<?php if ( '' !== $sa_prov_en ) : ?><small dir="ltr"><?php echo esc_html( strtoupper( $sa_prov_en ) ); ?></small><?php endif; ?>
				</a>
			<?php endif; ?>
			<?php if ( '' !== $sa_map_url ) : ?>
				<a class="sa-cyber-id__hud-btn" href="<?php echo esc_url( $sa_map_url ); ?>" target="_blank" rel="noopener noreferrer">
					<span>نقشه</span>
					<small dir="ltr">MAP</small>
				</a>
			<?php endif; ?>
			<?php
			if ( function_exists( 'sa_updated_badge' ) ) {
				sa_updated_badge( $sa_id );
			}
			?>
		</div>
	</div>

	<div class="sa-cyber-id__header">
		<div class="sa-cyber-id__title-group">
			<h1 class="sa-cyber-id__title" id="sa-county-identity-title"><?php echo esc_html( $sa_county_full ); ?></h1>
			<?php if ( '' !== $sa_en_county ) : ?>
				<span class="sa-cyber-id__en-pill" lang="en" dir="ltr"><?php echo esc_html( strtoupper( $sa_en_county ) ); ?></span>
			<?php endif; ?>
		</div>
		<?php if ( has_excerpt( $sa_id ) ) : ?>
			<p class="sa-cyber-id__summary"><?php echo esc_html( get_the_excerpt( $sa_id ) ); ?></p>
		<?php endif; ?>
	</div>

	<div class="sa-cyber-id__table-wrap" role="region" aria-label="جدول شناسنامه شهرستان">
		<div class="sa-cyber-grid sa-cyber-grid--county">
			<?php foreach ( $sa_county_cells as $sa_cell ) : ?>
				<?php
				$sa_cell_cls = 'sa-cyber-grid__cell';
				if ( 'wide' === $sa_cell['span'] ) {
					$sa_cell_cls .= ' sa-cyber-grid__cell--wide';
				} elseif ( 'full' === $sa_cell['span'] ) {
					$sa_cell_cls .= ' sa-cyber-grid__cell--full';
				}
				?>
				<div class="<?php echo esc_attr( $sa_cell_cls ); ?>">
					<span class="sa-cyber-grid__code" dir="ltr"><?php echo esc_html( $sa_cell['code'] ); ?></span>
					<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_cell['label'] ); ?></span>
					<strong class="sa-cyber-grid__value">
						<?php echo sa_cyber_id_value_or_review( $sa_cell['value'], ! empty( $sa_cell['html'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</strong>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<?php if ( ! empty( $sa_pois ) ) : ?>
		<div class="sa-cyber-id__subtable" id="county-visit">
			<div class="sa-cyber-id__subtable-head">
				<span class="sa-cyber-id__subtable-title">کجا برویم؟ (مقصدهای منتخب شهرستان)</span>
				<span class="sa-cyber-id__subtable-code" dir="ltr">DESTINATIONS &amp; POIS</span>
			</div>
			<div class="sa-cyber-grid sa-cyber-grid--neighbors">
				<?php foreach ( $sa_pois as $sa_ptitle => $sa_plist ) : ?>
					<div class="sa-cyber-grid__cell sa-cyber-grid__cell--neighbor">
						<span class="sa-cyber-grid__label"><?php echo esc_html( $sa_ptitle ); ?></span>
						<ul class="sa-county__poi">
							<?php foreach ( $sa_plist as $sa_poi ) : ?>
								<li>
									<strong><?php echo esc_html( $sa_poi['name'] ); ?></strong>
									<?php if ( ! empty( $sa_poi['distance'] ) ) : ?>
										<span class="sa-county__dist"><?php echo esc_html( sa_fa_digits( $sa_poi['distance'] ) ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $sa_poi['note'] ) ) : ?>
										<p><?php echo esc_html( $sa_poi['note'] ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $sa_attractions ) ) : ?>
		<div class="sa-cyber-id__subtable" id="county-attractions">
			<div class="sa-cyber-id__subtable-head">
				<span class="sa-cyber-id__subtable-title">جاذبه‌های مهم شهرستان</span>
				<span class="sa-cyber-id__subtable-code" dir="ltr">TOP ATTRACTIONS</span>
			</div>
			<table class="sa-cyber-id__attrs">
				<thead><tr><th scope="col" dir="ltr">#</th><th scope="col">جاذبه</th><th scope="col">نوع</th><th scope="col" dir="ltr">LINK</th></tr></thead>
				<tbody>
					<?php foreach ( $sa_attractions as $sa_ai => $sa_a ) : ?>
						<tr>
							<td dir="ltr"><?php echo esc_html( str_pad( (string) ( $sa_ai + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></td>
							<td><?php if ( '' !== $sa_a['url'] ) : ?><a href="<?php echo esc_url( $sa_a['url'] ); ?>"><?php echo esc_html( $sa_a['name'] ); ?></a><?php else : ?><?php echo esc_html( $sa_a['name'] ); ?><?php endif; ?><?php if ( '' !== $sa_a['en'] ) : ?> <small dir="ltr"><?php echo esc_html( $sa_a['en'] ); ?></small><?php endif; ?></td>
							<td><?php echo esc_html( $sa_a['type'] ); ?></td>
							<td><?php if ( '' !== $sa_a['url'] ) : ?><a href="<?php echo esc_url( $sa_a['url'] ); ?>">مشاهده ←</a><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $sa_sibling_cities ) ) : ?>
		<div class="sa-cyber-id__subtable" id="county-siblings">
			<div class="sa-cyber-id__subtable-head">
				<span class="sa-cyber-id__subtable-title">شهرهای هم‌استانی</span>
				<span class="sa-cyber-id__subtable-code" dir="ltr">SAME PROVINCE</span>
			</div>
			<ul class="sa-cyber-id__chips">
				<?php foreach ( $sa_sibling_cities as $sa_sib ) : ?>
					<li><a href="<?php echo esc_url( $sa_sib['url'] ); ?>"><?php echo esc_html( $sa_sib['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>

<?php
$sa_cid_notes = array();
if ( ! empty( $sa_profile_ext['history_now'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'HISTORY & TODAY', 'title' => 'قدمت و امروز', 'text' => $sa_profile_ext['history_now'] );
}
if ( ! empty( $sa_profile_ext['industry_main'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'PEOPLE & INDUSTRY', 'title' => 'مردم، صنعت و کارخانه‌ها', 'text' => $sa_profile_ext['industry_main'] );
}
if ( ! empty( $sa_profile_ext['agriculture_main'] ) ) {
	$sa_cid_notes[] = array( 'tag' => 'AGRICULTURE', 'title' => 'کشاورزی و دامداری', 'text' => $sa_profile_ext['agriculture_main'] );
}
?>
<?php if ( $sa_cid_notes ) : ?>
	<section class="sa-cid-note" aria-label="توضیح کوتاه شهرستان">
		<?php foreach ( $sa_cid_notes as $sa_note ) : ?>
			<div class="sa-cid-note__item">
				<h3><span lang="en" dir="ltr"><?php echo esc_html( $sa_note['tag'] ); ?></span><?php echo esc_html( $sa_note['title'] ); ?></h3>
				<p><?php echo esc_html( $sa_note['text'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</section>
<?php endif; ?>
