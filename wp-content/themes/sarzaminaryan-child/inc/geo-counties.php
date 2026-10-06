<?php
/**
 * Geo system — the fixed 31 provinces / 483 counties skeleton.
 *
 * Why this file exists: the administrative map of Iran is stable (a couple of
 * changes per decade), so the county page is modelled as a FIXED form instead of
 * free prose. Every county article fills the same slots, which gives Google one
 * predictable structure across 483 pages (AdministrativeArea + Place schema),
 * and gives readers the same answers in the same place every time.
 *
 * Only reader-valuable slots are kept (center, census population, area, exact
 * location, neighbours, divisions incl. number of villages, climate/best season,
 * and above all where to GO: nature, waterfalls, off-the-beaten-track villages).
 * Bureaucratic trivia (governor names, statistical codes, administrative history)
 * is deliberately NOT part of the schema.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical county registry (483 rows).
 *
 * @return array[] Each row: slug, name, province, status.
 */
function sa_counties() {
	static $rows = null;
	if ( null === $rows ) {
		$file = SA_CHILD_DIR . 'data/counties.php';
		$rows = is_readable( $file ) ? (array) require $file : array();
	}
	return $rows;
}

/**
 * Registry indexed by slug.
 *
 * @return array[]
 */
function sa_counties_by_slug() {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( sa_counties() as $row ) {
			$map[ $row['slug'] ] = $row;
		}
	}
	return $map;
}

/**
 * Registry grouped by province slug.
 *
 * @return array<string,array[]>
 */
function sa_counties_by_province() {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( sa_counties() as $row ) {
			$map[ $row['province'] ][] = $row;
		}
	}
	return $map;
}

/**
 * Registry row for one county slug.
 *
 * @param string $slug County slug.
 * @return array|null
 */
function sa_county( $slug ) {
	$map = sa_counties_by_slug();
	return isset( $map[ $slug ] ) ? $map[ $slug ] : null;
}

/**
 * Registry row matching a city post (by post slug).
 *
 * @param int $post_id Post ID.
 * @return array|null
 */
function sa_county_of_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'city' !== $post->post_type ) {
		return null;
	}
	return sa_county( $post->post_name );
}

/**
 * Persian province name for a province_tax slug.
 *
 * @param string $slug Province slug.
 * @return string
 */
function sa_province_name( $slug ) {
	static $map = null;
	if ( null === $map ) {
		$map  = array();
		$file = SA_CHILD_DIR . 'data/provinces.php';
		if ( is_readable( $file ) ) {
			foreach ( (array) require $file as $p ) {
				$map[ $p['slug'] ] = $p['name'];
			}
		}
	}
	return isset( $map[ $slug ] ) ? $map[ $slug ] : $slug;
}

/* -------------------------------------------------------------------------
 * The fixed county profile schema.
 * ---------------------------------------------------------------------- */

/**
 * Fixed slots every county page has. Order here = order on the page.
 *
 * group: identity | divisions | geo | life | visit
 * type:  text | integer | number | list | poi
 *
 * @return array[]
 */
function sa_county_schema() {
	return array(
		// Identity.
		array(
			'key'   => 'sa_cty_center',
			'label' => 'مرکز شهرستان',
			'type'  => 'text',
			'group' => 'identity',
			'hint'  => 'نام شهرِ مرکز شهرستان؛ همان که در تقسیمات کشوری ثبت شده است.',
		),
		array(
			'key'   => 'sa_cty_population',
			'label' => 'جمعیت شهرستان',
			'type'  => 'integer',
			'group' => 'identity',
			'unit'  => 'نفر',
			'hint'  => 'جمعیت کل شهرستان بر پایهٔ آخرین سرشماری رسمی.',
		),
		array(
			'key'   => 'sa_cty_census_year',
			'label' => 'سال سرشماری',
			'type'  => 'integer',
			'group' => 'identity',
			'hint'  => 'سال شمسی سرشماری مرجع (مثلاً ۱۳۹۵ یا ۱۴۰۰). بدون سال، عدد جمعیت منتشر نمی‌شود.',
		),
		array(
			'key'   => 'sa_cty_area',
			'label' => 'مساحت',
			'type'  => 'number',
			'group' => 'identity',
			'unit'  => 'کیلومتر مربع',
		),
		// Divisions.
		array(
			'key'   => 'sa_cty_districts',
			'label' => 'تعداد بخش',
			'type'  => 'integer',
			'group' => 'divisions',
		),
		array(
			'key'   => 'sa_cty_rural_districts',
			'label' => 'تعداد دهستان',
			'type'  => 'integer',
			'group' => 'divisions',
		),
		array(
			'key'   => 'sa_cty_cities',
			'label' => 'تعداد شهر',
			'type'  => 'integer',
			'group' => 'divisions',
		),
		array(
			'key'   => 'sa_cty_villages',
			'label' => 'تعداد روستا (آبادی دارای سکنه)',
			'type'  => 'integer',
			'group' => 'divisions',
		),
		// Geography.
		array(
			'key'   => 'sa_cty_neighbors',
			'label' => 'همسایه‌های شهرستان',
			'type'  => 'list',
			'group' => 'geo',
			'hint'  => 'هر همسایه در یک خط. اگر نامک شهرستان همسایه را بنویسید (مثلاً dena) خودکار لینک می‌شود؛ جهت را با خط تیره بیاورید: dena - شمال',
		),
		array(
			'key'   => 'sa_cty_distance_center',
			'label' => 'فاصله تا مرکز استان',
			'type'  => 'integer',
			'group' => 'geo',
			'unit'  => 'کیلومتر',
		),
		array(
			'key'   => 'sa_cty_climate',
			'label' => 'اقلیم',
			'type'  => 'text',
			'group' => 'geo',
			'hint'  => 'مثلاً: سردسیری کوهستانی / گرم و خشک / معتدل خزری.',
		),
		// Life.
		array(
			'key'   => 'sa_cty_language',
			'label' => 'زبان و گویش مردم',
			'type'  => 'text',
			'group' => 'life',
		),
		array(
			'key'   => 'sa_cty_livelihood',
			'label' => 'معیشت اصلی',
			'type'  => 'text',
			'group' => 'life',
			'hint'  => 'کوتاه و واقعی: کشاورزی، دامداری عشایری، نفت و گاز، صنایع دستی…',
		),
		// Visit (the part readers actually come for).
		array(
			'key'   => 'sa_cty_best_time',
			'label' => 'بهترین زمان سفر',
			'type'  => 'text',
			'group' => 'visit',
		),
		array(
			'key'   => 'sa_cty_poi_nature',
			'label' => 'طبیعت‌گردی (آبشار، تنگ، غار، دریاچه، جنگل)',
			'type'  => 'poi',
			'group' => 'visit',
			'hint'  => 'هر مورد در یک خط: نام | فاصله از مرکز شهرستان | یک جملهٔ کوتاه چرا ارزش رفتن دارد',
		),
		array(
			'key'   => 'sa_cty_poi_offbeat',
			'label' => 'نقاط بکر و کمتر دیده‌شده (روستاهای دور، مسیرهای ناشناخته)',
			'type'  => 'poi',
			'group' => 'visit',
			'hint'  => 'همان قالب: نام | فاصله | توضیح کوتاه. فقط جاهایی که واقعاً قابل رفتن‌اند.',
		),
		array(
			'key'   => 'sa_cty_poi_recreation',
			'label' => 'جاهای تفریحی و خانوادگی (پارک، تله‌کابین، پیست، ساحل)',
			'type'  => 'poi',
			'group' => 'visit',
		),
		array(
			'key'   => 'sa_cty_poi_heritage',
			'label' => 'آثار تاریخی و زیارتی',
			'type'  => 'poi',
			'group' => 'visit',
		),
	);
}

/**
 * Group titles used in the admin box and on the front end.
 *
 * @return array<string,string>
 */
function sa_county_groups() {
	return array(
		'identity'  => 'شناسنامهٔ شهرستان',
		'divisions' => 'تقسیمات کشوری',
		'geo'       => 'موقعیت و همسایه‌ها',
		'life'      => 'مردم و معیشت',
		'visit'     => 'کجا برویم',
	);
}

/**
 * Register the meta keys (REST-ready, so the importer can write them).
 */
function sa_county_register_meta() {
	foreach ( sa_county_schema() as $f ) {
		$type = in_array( $f['type'], array( 'integer' ), true ) ? 'integer' : ( 'number' === $f['type'] ? 'number' : 'string' );
		register_post_meta(
			'city',
			$f['key'],
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
add_action( 'init', 'sa_county_register_meta' );

/**
 * Meta box.
 */
function sa_county_add_box() {
	add_meta_box( 'sa-county-profile', 'پروفایل ثابت شهرستان (یکسان برای هر ۴۸۳ شهرستان)', 'sa_county_render_box', 'city', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'sa_county_add_box' );

/**
 * Render the fixed profile box.
 *
 * @param WP_Post $post Post.
 */
function sa_county_render_box( $post ) {
	if ( function_exists( 'sa_meta_nonce_field' ) ) {
		sa_meta_nonce_field();
	}
	wp_nonce_field( 'sa_county_save', 'sa_county_nonce' );
	$reg = sa_county_of_post( $post->ID );
	echo '<p class="description">';
	if ( $reg ) {
		echo 'این نوشته با ردیف رسمی <strong>' . esc_html( $reg['name'] ) . '</strong> از استان <strong>' . esc_html( sa_province_name( $reg['province'] ) ) . '</strong> تطبیق داده شد.';
	} else {
		echo '<strong class="sa-missing">نامک این نوشته در فهرست رسمی ۴۸۳ شهرستان پیدا نشد.</strong> نامک را با فهرست رسمی یکسان کنید تا لینک همسایه‌ها و اسکیمای مکان درست کار کند.';
	}
	echo '</p>';

	$groups = sa_county_groups();
	foreach ( $groups as $gkey => $gtitle ) {
		echo '<h4 class="sa-county-group">' . esc_html( $gtitle ) . '</h4>';
		echo '<div class="sa-box sa-grid">';
		foreach ( sa_county_schema() as $f ) {
			if ( $f['group'] !== $gkey ) {
				continue;
			}
			$value = get_post_meta( $post->ID, $f['key'], true );
			$id    = esc_attr( $f['key'] );
			$wide  = in_array( $f['type'], array( 'list', 'poi' ), true ) ? ' sa-grid__item--wide' : '';
			echo '<div class="sa-grid__item' . esc_attr( $wide ) . '">';
			echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
			if ( in_array( $f['type'], array( 'list', 'poi' ), true ) ) {
				printf( '<textarea id="%1$s" name="%1$s" rows="4" class="widefat">%2$s</textarea>', esc_attr( $id ), esc_textarea( $value ) );
			} elseif ( 'integer' === $f['type'] ) {
				printf( '<input type="number" step="1" min="0" id="%1$s" name="%1$s" value="%2$s" class="widefat" dir="ltr">', esc_attr( $id ), esc_attr( $value ) );
			} elseif ( 'number' === $f['type'] ) {
				printf( '<input type="number" step="any" min="0" id="%1$s" name="%1$s" value="%2$s" class="widefat" dir="ltr">', esc_attr( $id ), esc_attr( $value ) );
			} else {
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat">', esc_attr( $id ), esc_attr( $value ) );
			}
			if ( ! empty( $f['unit'] ) ) {
				echo '<span class="description">واحد: ' . esc_html( $f['unit'] ) . '</span>';
			}
			if ( ! empty( $f['hint'] ) ) {
				echo '<span class="description">' . esc_html( $f['hint'] ) . '</span>';
			}
			echo '</div>';
		}
		echo '</div>';
	}
	echo '<p class="description">خانه‌های خالی روی سایت نمایش داده نمی‌شوند؛ پس هر چیزی که منبع ندارد را خالی بگذارید (قانون «حدس نزن»).</p>';
}

/**
 * Save the fixed profile.
 *
 * @param int $post_id Post ID.
 */
function sa_county_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['sa_county_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sa_county_nonce'] ) ), 'sa_county_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( sa_county_schema() as $f ) {
		$key = $f['key'];
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( in_array( $f['type'], array( 'list', 'poi' ), true ) ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'integer' === $f['type'] ) {
			$value = '' === trim( (string) $raw ) ? '' : (string) absint( $raw );
		} elseif ( 'number' === $f['type'] ) {
			$value = '' === trim( (string) $raw ) ? '' : (string) abs( (float) $raw );
		} else {
			$value = sanitize_text_field( $raw );
		}
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post_city', 'sa_county_save' );

/* -------------------------------------------------------------------------
 * Front-end helpers.
 * ---------------------------------------------------------------------- */

/**
 * Split a textarea meta into trimmed lines.
 *
 * @param string $raw Raw meta.
 * @return string[]
 */
function sa_county_lines( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}
	return $out;
}

/**
 * Permalink of a county by registry slug (only if the post exists & is public).
 *
 * @param string $slug County slug.
 * @return string Empty string when not published yet.
 */
function sa_county_permalink( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'city' );
	if ( $post && 'publish' === $post->post_status ) {
		return (string) get_permalink( $post );
	}
	return '';
}

/**
 * Parse one neighbour line: "dena - شمال" or "شهرستان دنا - شمال".
 *
 * @param string $line Raw line.
 * @return array{label:string,dir:string,url:string}
 */
function sa_county_parse_neighbor( $line ) {
	$dir   = '';
	$parts = preg_split( '/\s[-–—]\s/u', $line, 2 );
	$name  = trim( $parts[0] );
	if ( isset( $parts[1] ) ) {
		$dir = trim( $parts[1] );
	}
	$row = sa_county( $name );
	if ( $row ) {
		return array(
			'label' => $row['name'],
			'dir'   => $dir,
			'url'   => sa_county_permalink( $row['slug'] ),
		);
	}
	return array(
		'label' => $name,
		'dir'   => $dir,
		'url'   => '',
	);
}

/**
 * Parse a POI line: "نام | فاصله | توضیح".
 *
 * @param string $line Raw line.
 * @return array{name:string,distance:string,note:string}
 */
function sa_county_parse_poi( $line ) {
	$bits = array_map( 'trim', explode( '|', $line ) );
	return array(
		'name'     => isset( $bits[0] ) ? $bits[0] : '',
		'distance' => isset( $bits[1] ) ? $bits[1] : '',
		'note'     => isset( $bits[2] ) ? $bits[2] : '',
	);
}

/**
 * Does this post have any county profile data?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function sa_county_has_profile( $post_id ) {
	foreach ( sa_county_schema() as $f ) {
		if ( '' !== (string) get_post_meta( $post_id, $f['key'], true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Completeness of the fixed profile (for the editor + coverage screen).
 *
 * @param int $post_id Post ID.
 * @return array{filled:int,total:int,percent:int}
 */
function sa_county_completeness( $post_id ) {
	$schema = sa_county_schema();
	$filled = 0;
	foreach ( $schema as $f ) {
		if ( '' !== (string) get_post_meta( $post_id, $f['key'], true ) ) {
			++$filled;
		}
	}
	$total = count( $schema );
	return array(
		'filled'  => $filled,
		'total'   => $total,
		'percent' => $total ? (int) round( 100 * $filled / $total ) : 0,
	);
}

/**
 * Push the county slots into the shared "key facts" card.
 *
 * @param array $rows    Fact rows.
 * @param int   $post_id Post ID.
 * @return array
 */
function sa_county_facts_rows( $rows, $post_id ) {
	if ( 'city' !== get_post_type( $post_id ) ) {
		return $rows;
	}
	$pop    = get_post_meta( $post_id, 'sa_cty_population', true );
	$year   = get_post_meta( $post_id, 'sa_cty_census_year', true );
	$center = get_post_meta( $post_id, 'sa_cty_center', true );
	$area   = get_post_meta( $post_id, 'sa_cty_area', true );
	$dist   = get_post_meta( $post_id, 'sa_cty_distance_center', true );
	$clim   = get_post_meta( $post_id, 'sa_cty_climate', true );

	$add = array();
	if ( $center ) {
		$add[] = array(
			'label' => 'مرکز شهرستان',
			'value' => $center,
			'html'  => false,
		);
	}
	if ( $pop && $year ) {
		$add[] = array(
			'label' => 'جمعیت (سرشماری ' . sa_fa_digits( $year ) . ')',
			'value' => sa_number( $pop ) . ' نفر',
			'html'  => false,
		);
	}
	if ( $area ) {
		$add[] = array(
			'label' => 'مساحت',
			'value' => sa_number( $area, 0 ) . ' کیلومتر مربع',
			'html'  => false,
		);
	}
	if ( $dist ) {
		$add[] = array(
			'label' => 'فاصله تا مرکز استان',
			'value' => sa_number( $dist, 0 ) . ' کیلومتر',
			'html'  => false,
		);
	}
	if ( $clim ) {
		$add[] = array(
			'label' => 'اقلیم',
			'value' => $clim,
			'html'  => false,
		);
	}
	return array_merge( $rows, $add );
}
add_filter( 'sa_entity_facts', 'sa_county_facts_rows', 10, 2 );

/**
 * Enrich the JSON-LD graph: a county is an AdministrativeArea with neighbours.
 *
 * @param array $graph Schema graph.
 * @return array
 */
function sa_county_schema_graph( $graph ) {
	if ( ! is_singular( 'city' ) ) {
		return $graph;
	}
	$id  = get_the_ID();
	$reg = sa_county_of_post( $id );
	if ( ! $reg ) {
		return $graph;
	}
	$node = array(
		'@type' => 'AdministrativeArea',
		'@id'   => get_permalink( $id ) . '#county',
		'name'  => sa_entity_display_name( $id ),
		'url'   => get_permalink( $id ),
	);
	$lat = get_post_meta( $id, 'sa_city_latitude', true );
	$lng = get_post_meta( $id, 'sa_city_longitude', true );
	if ( $lat && $lng ) {
		$node['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		);
	}
	$node['containedInPlace'] = array(
		'@type' => 'AdministrativeArea',
		'name'  => 'استان ' . sa_province_name( $reg['province'] ),
	);
	$borders = array();
	foreach ( sa_county_lines( get_post_meta( $id, 'sa_cty_neighbors', true ) ) as $line ) {
		$n = sa_county_parse_neighbor( $line );
		if ( $n['label'] ) {
			$borders[] = array(
				'@type' => 'AdministrativeArea',
				'name'  => $n['label'],
			);
		}
	}
	if ( $borders ) {
		$node['borders'] = $borders;
	}
	$attractions = array();
	foreach ( array( 'sa_cty_poi_nature', 'sa_cty_poi_offbeat', 'sa_cty_poi_recreation', 'sa_cty_poi_heritage' ) as $key ) {
		foreach ( sa_county_lines( get_post_meta( $id, $key, true ) ) as $line ) {
			$poi = sa_county_parse_poi( $line );
			if ( $poi['name'] ) {
				$attractions[] = array(
					'@type'       => 'TouristAttraction',
					'name'        => $poi['name'],
					'description' => $poi['note'],
				);
			}
		}
	}
	if ( $attractions ) {
		$node['touristAttraction'] = $attractions;
	}
	$graph[] = $node;
	return $graph;
}
add_filter( 'sa_schema_graph', 'sa_county_schema_graph' );

/* -------------------------------------------------------------------------
 * Coverage screen — the 483-piece puzzle.
 * ---------------------------------------------------------------------- */

/**
 * Current coverage snapshot, computed from real posts (not from the registry status).
 *
 * @return array
 */
function sa_county_coverage() {
	$posts = get_posts(
		array(
			'post_type'        => 'city',
			'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);
	$by_slug = array();
	foreach ( $posts as $pid ) {
		$by_slug[ get_post_field( 'post_name', $pid ) ] = $pid;
	}
	$out = array();
	foreach ( sa_counties_by_province() as $prov => $rows ) {
		$items = array();
		foreach ( $rows as $row ) {
			$pid    = isset( $by_slug[ $row['slug'] ] ) ? $by_slug[ $row['slug'] ] : 0;
			$state  = 'missing';
			$filled = 0;
			if ( $pid ) {
				$state  = ( 'publish' === get_post_status( $pid ) ) ? 'published' : 'draft';
				$c      = sa_county_completeness( $pid );
				$filled = $c['percent'];
			}
			$items[] = array(
				'slug'    => $row['slug'],
				'name'    => $row['name'],
				'post_id' => $pid,
				'state'   => $state,
				'profile' => $filled,
			);
		}
		$out[ $prov ] = array(
			'name'      => sa_province_name( $prov ),
			'items'     => $items,
			'total'     => count( $items ),
			'published' => count( array_filter( $items, function ( $i ) {
				return 'published' === $i['state'];
			} ) ),
			'draft'     => count( array_filter( $items, function ( $i ) {
				return 'draft' === $i['state'];
			} ) ),
			'missing'   => count( array_filter( $items, function ( $i ) {
				return 'missing' === $i['state'];
			} ) ),
			'data'      => $items ? (int) round( array_sum( wp_list_pluck( $items, 'profile' ) ) / count( $items ) ) : 0,
		);
	}
	return $out;
}

/**
 * Admin menu entry.
 *
 * All entity CPTs are registered with show_in_menu => 'sarzaminaryan'
 * (see inc/post-types.php), so `edit.php?post_type=city` is NOT a real menu
 * slug and a submenu hung on it is never displayed. The coverage screen lives
 * next to «سلامت محتوا» under the «سرزمین آریان» top-level menu instead.
 */
function sa_county_admin_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'پوشش ۴۸۳ شهرستان',
		'— پوشش ۴۸۳ شهرستان',
		'edit_posts',
		'sa-county-coverage',
		'sa_county_coverage_screen'
	);
}
add_action( 'admin_menu', 'sa_county_admin_menu', 20 );

/**
 * Create missing county drafts for one province (skeleton posts with the right slug + province term).
 *
 * @param string $province Province slug.
 * @return int Number created.
 */
function sa_county_create_missing( $province ) {
	$created  = 0;
	$coverage = sa_county_coverage();
	if ( ! isset( $coverage[ $province ] ) ) {
		return 0;
	}
	foreach ( $coverage[ $province ]['items'] as $item ) {
		if ( 'missing' !== $item['state'] ) {
			continue;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'city',
				'post_status' => 'draft',
				'post_title'  => 'شهرستان ' . $item['name'],
				'post_name'   => $item['slug'],
			)
		);
		if ( $post_id && ! is_wp_error( $post_id ) ) {
			wp_set_object_terms( $post_id, $province, 'province_tax', false );
			++$created;
		}
	}
	return $created;
}

/**
 * Coverage screen.
 */
function sa_county_coverage_screen() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$notice = '';
	if ( isset( $_POST['sa_county_create_province'] ) && check_admin_referer( 'sa_county_create' ) && current_user_can( 'publish_posts' ) ) {
		$prov   = sanitize_key( wp_unslash( $_POST['sa_county_create_province'] ) );
		$n      = sa_county_create_missing( $prov );
		$notice = sprintf( '%s پیش‌نویس تازه برای استان %s ساخته شد.', sa_fa_digits( $n ), sa_province_name( $prov ) );
	}
	$coverage = sa_county_coverage();
	$t_total  = 0;
	$t_pub    = 0;
	$t_draft  = 0;
	foreach ( $coverage as $c ) {
		$t_total += $c['total'];
		$t_pub   += $c['published'];
		$t_draft += $c['draft'];
	}
	echo '<div class="wrap sa-coverage">';
	echo '<h1>پوشش ۳۱ استان / ۴۸۳ شهرستان</h1>';
	if ( $notice ) {
		echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p class="description">ستون <strong>مقاله‌ها</strong> = چند شهرستان نوشتهٔ منتشرشده دارد. ستون <strong>دادهٔ پرشده</strong> = چند درصد خانه‌های اطلاعاتی (جمعیت، مرکز، مساحت، مختصات…) پر شده‌اند؛ ورود داده از گیت‌هاب این ستون را بالا می‌برد، نه ستون مقاله‌ها را.</p>';
	echo '<p>' . esc_html( sprintf( 'منتشرشده: %s · پیش‌نویس: %s · باقی‌مانده: %s از %s', sa_fa_digits( $t_pub ), sa_fa_digits( $t_draft ), sa_fa_digits( $t_total - $t_pub - $t_draft ), sa_fa_digits( $t_total ) ) ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>استان</th><th>کل</th><th>منتشر</th><th>پیش‌نویس</th><th>نساخته</th><th>مقاله‌ها</th><th>دادهٔ پرشده</th><th></th></tr></thead><tbody>';
	foreach ( $coverage as $slug => $c ) {
		$pct = $c['total'] ? (int) round( 100 * $c['published'] / $c['total'] ) : 0;
		echo '<tr>';
		echo '<td><strong>' . esc_html( $c['name'] ) . '</strong></td>';
		echo '<td>' . esc_html( sa_fa_digits( $c['total'] ) ) . '</td>';
		echo '<td>' . esc_html( sa_fa_digits( $c['published'] ) ) . '</td>';
		echo '<td>' . esc_html( sa_fa_digits( $c['draft'] ) ) . '</td>';
		echo '<td>' . esc_html( sa_fa_digits( $c['missing'] ) ) . '</td>';
		echo '<td><div class="sa-bar"><span style="width:' . esc_attr( $pct ) . '%"></span></div> ' . esc_html( sa_fa_digits( $pct ) ) . '٪</td>';
		$dpct = isset( $c['data'] ) ? (int) $c['data'] : 0;
		echo '<td><div class="sa-bar"><span style="width:' . esc_attr( $dpct ) . '%;background:#2271b1"></span></div> ' . esc_html( sa_fa_digits( $dpct ) ) . '٪</td>';
		echo '<td>';
		if ( $c['missing'] && current_user_can( 'publish_posts' ) ) {
			echo '<form method="post" style="margin:0">';
			wp_nonce_field( 'sa_county_create' );
			echo '<input type="hidden" name="sa_county_create_province" value="' . esc_attr( $slug ) . '">';
			echo '<button class="button button-small">ساخت ' . esc_html( sa_fa_digits( $c['missing'] ) ) . ' پیش‌نویس جاافتاده</button>';
			echo '</form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>جزئیات هر استان</h2>';
	foreach ( $coverage as $slug => $c ) {
		echo '<details><summary><strong>' . esc_html( $c['name'] ) . '</strong> — ' . esc_html( sa_fa_digits( $c['published'] ) . '/' . sa_fa_digits( $c['total'] ) ) . '</summary><p>';
		foreach ( $c['items'] as $item ) {
			$icon = 'published' === $item['state'] ? '🟩' : ( 'draft' === $item['state'] ? '🟠' : '⬜' );
			$txt  = $icon . ' ' . $item['name'];
			if ( $item['post_id'] ) {
				$txt .= ' (' . sa_fa_digits( $item['profile'] ) . '٪)';
				echo '<a href="' . esc_url( get_edit_post_link( $item['post_id'] ) ) . '">' . esc_html( $txt ) . '</a>، ';
			} else {
				echo esc_html( $txt ) . '، ';
			}
		}
		echo '</p></details>';
	}
	if ( function_exists( 'sa_county_import_screen' ) ) {
		sa_county_import_screen();
	}
	echo '<style>.sa-bar{display:inline-block;width:120px;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;vertical-align:middle}.sa-bar span{display:block;height:100%;background:#16a34a}.sa-coverage details{margin:.4rem 0}</style>';
	echo '</div>';
}
