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
		foreach ( $directions as $key => $label ) {
			if ( false !== strpos( (string) $parsed['dir'], $label ) && ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'direction' => $label,
					'label'     => 'شهرستان ' . $parsed['label'],
					'url'       => isset( $parsed['url'] ) ? (string) $parsed['url'] : '',
				);
			}
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
function sa_county_profile_data( $post_id ) {
	$post_id = (int) $post_id;
	$get     = function ( $key ) use ( $post_id ) {
		return sa_county_profile_meta( $post_id, $key );
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
	if ( function_exists( 'sa_get_children' ) ) {
		foreach ( (array) sa_get_children( (int) $post_id, 'attraction' ) as $post ) {
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$name = get_the_title( $post );
			if ( '' === $name || isset( $seen[ $name ] ) ) {
				continue;
			}
			$seen[ $name ] = true;
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
			if ( empty( $poi['name'] ) || isset( $seen[ $poi['name'] ] ) ) {
				continue;
			}
			$seen[ $poi['name'] ] = true;
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
