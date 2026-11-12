<?php
/**
 * نوارهای مینیمال استان‌ها و شهرستان‌ها («کادر سفید سایه‌دار») — v2.13.3.
 *
 * Until 2.13.2 the home page listed all 31 provinces as white shadowed bars;
 * the owner moved that design to the archives: the province archive now shows
 * exactly those bars (published provinces link, the rest read «به‌زودی») and the
 * city archive shows a narrower three-column variant (province name as the
 * secondary label). Both archives list everything on one page; stale
 * `/page/N/` URLs redirect to the archive.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The 31 provinces of Iran: Persian name, slug, map x, map y (hero SVG).
 *
 * @return array<int,array{0:string,1:string,2:int,3:int}>
 */
function sa_iran_provinces() {
	return array(
		array( 'آذربایجان شرقی', 'east-azerbaijan', 56, 45 ),
		array( 'آذربایجان غربی', 'west-azerbaijan', 31, 58 ),
		array( 'اردبیل', 'ardabil', 96, 41 ),
		array( 'اصفهان', 'isfahan', 180, 165 ),
		array( 'البرز', 'alborz', 146, 94 ),
		array( 'ایلام', 'ilam', 58, 150 ),
		array( 'بوشهر', 'bushehr', 152, 258 ),
		array( 'تهران', 'tehran', 162, 104 ),
		array( 'چهارمحال و بختیاری', 'chaharmahal-bakhtiari', 147, 181 ),
		array( 'خراسان جنوبی', 'south-khorasan', 318, 196 ),
		array( 'خراسان رضوی', 'razavi-khorasan', 318, 108 ),
		array( 'خراسان شمالی', 'north-khorasan', 288, 68 ),
		array( 'خوزستان', 'khuzestan', 100, 196 ),
		array( 'زنجان', 'zanjan', 92, 86 ),
		array( 'سمنان', 'semnan', 222, 104 ),
		array( 'سیستان و بلوچستان', 'sistan-baluchestan', 346, 276 ),
		array( 'فارس', 'fars', 194, 246 ),
		array( 'قزوین', 'qazvin', 126, 92 ),
		array( 'قم', 'qom', 158, 130 ),
		array( 'کردستان', 'kurdistan', 62, 110 ),
		array( 'کرمان', 'kerman', 274, 236 ),
		array( 'کرمانشاه', 'kermanshah', 54, 132 ),
		array( 'کهگیلویه و بویراحمد', 'kohgiluyeh-boyer-ahmad', 148, 220 ),
		array( 'گلستان', 'golestan', 240, 64 ),
		array( 'گیلان', 'gilan', 134, 60 ),
		array( 'لرستان', 'lorestan', 94, 160 ),
		array( 'مازندران', 'mazandaran', 186, 74 ),
		array( 'مرکزی', 'markazi', 128, 134 ),
		array( 'هرمزگان', 'hormozgan', 262, 300 ),
		array( 'همدان', 'hamadan', 92, 124 ),
		array( 'یزد', 'yazd', 236, 184 ),
	);
}

/**
 * Eight navy shades used as the bar accent (same palette as the home page).
 *
 * @return string[]
 */
function sa_bar_colors() {
	return array( '#0b2a4a', '#123f73', '#18558f', '#246da8', '#174f82', '#2b6094', '#387aaf', '#0f355f' );
}

/**
 * Published entities of a type keyed by slug (static per request).
 *
 * @param string $type Post type.
 * @return array<string,int> slug => post ID
 */
function sa_published_entity_ids( $type ) {
	static $cache = array();
	if ( isset( $cache[ $type ] ) ) {
		return $cache[ $type ];
	}
	$ids = get_posts(
		array(
			'post_type'              => $type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$map = array();
	foreach ( $ids as $id ) {
		$name = (string) get_post_field( 'post_name', $id );
		if ( '' !== $name ) {
			$map[ $name ] = (int) $id;
		}
	}
	$cache[ $type ] = $map;
	return $map;
}

/**
 * One bar.
 *
 * @param string $title  Main label.
 * @param string $sub    Secondary label (English name or province); may be empty.
 * @param string $url    Link; empty = «به‌زودی» placeholder.
 * @param string $color  Accent colour.
 * @param bool   $sub_fa Secondary label is Persian (keeps RTL direction).
 * @param string $extra_class Extra class on the bar (e.g. sa-bar--capital).
 * @return string
 */
function sa_entity_bar_html( $title, $sub, $url, $color, $sub_fa = false, $extra_class = '' ) {
	$style = ' style="--c:' . esc_attr( $color ) . '"';
	$class = 'sa-bar' . ( '' !== $extra_class ? ' ' . $extra_class : '' );
	$em    = '' !== $sub ? '<em' . ( $sub_fa ? ' class="fa"' : '' ) . '>' . esc_html( $sub ) . '</em>' : '';
	if ( '' === $url ) {
		return '<span class="' . esc_attr( $class . ' sa-bar--soon' ) . '"' . $style . '><b>' . esc_html( $title ) . '</b><em>به‌زودی</em></span>';
	}
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . $style . '><b>' . esc_html( $title ) . '</b>' . $em . '</a>';
}

/**
 * Province bars: all 31 provinces, published ones linked (home-page design, 2.1.0 rule:
 * never link an unpublished province).
 *
 * @param WP_Post[]|null $posts Published province posts (defaults to a fresh lookup).
 * @return string
 */
function sa_province_bars_html( $posts = null ) {
	$published = array();
	if ( is_array( $posts ) ) {
		foreach ( $posts as $p ) {
			if ( $p instanceof WP_Post && 'publish' === $p->post_status && '' !== $p->post_name ) {
				$published[ $p->post_name ] = (int) $p->ID;
			}
		}
	} else {
		$published = sa_published_entity_ids( 'province' );
	}
	$colors = sa_bar_colors();
	$soon   = 0;
	$out    = '<div class="sa-bars sa-bars--province" id="sa-bars">';
	foreach ( sa_iran_provinces() as $i => $p ) {
		$url = isset( $published[ $p[1] ] ) ? (string) get_permalink( $published[ $p[1] ] ) : '';
		if ( '' === $url ) {
			$soon++;
		}
		$out .= sa_entity_bar_html( $p[0], ucwords( str_replace( '-', ' ', $p[1] ) ), $url, $colors[ $i % 8 ] );
	}
	$out .= '</div>';
	if ( $soon > 0 ) {
		$out .= '<p class="sa-bars__note">' . esc_html( sprintf( '%s استان با برچسب «به‌زودی» هنوز منتشر نشده‌اند و با انتشار، خودکار لینک می‌شوند.', number_format_i18n( $soon ) ) ) . '</p>';
	}
	return $out;
}

/**
 * City bars for the /city/ archive: grouped by province (v2.22.53).
 *
 * Until 2.22.52 the bars followed the flat archive order and a separate
 * grouped text directory («فهرست شهرستان‌ها به تفکیک استان») repeated every
 * link below them. The owner asked for one list only: the bars themselves
 * now carry the grouping — each province group is led by its capital
 * (مرکز استان: full-width navy bar with gold text), followed by the
 * province's counties in registry order. Groups follow the canonical
 * 31-province order; cities without a province go last.
 *
 * @param WP_Post[]|null $posts City posts (defaults to a fresh lookup).
 * @return string
 */
function sa_city_bars_html( $posts = null ) {
	if ( null === $posts ) {
		$found = get_posts(
			array(
				'post_type'      => 'city',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$posts = array();
		foreach ( (array) $found as $item ) {
			if ( $item instanceof WP_Post ) {
				$posts[] = $item;
			} elseif ( is_numeric( $item ) && function_exists( 'get_post' ) ) {
				$p = get_post( (int) $item );
				if ( $p instanceof WP_Post ) {
					$posts[] = $p;
				}
			} elseif ( is_numeric( $item ) && isset( $GLOBALS['t']['posts'][ (int) $item ] ) ) {
				$posts[] = $GLOBALS['t']['posts'][ (int) $item ];
			}
		}
	}
	$colors = sa_bar_colors();
	$valid  = array();
	foreach ( (array) $posts as $p ) {
		if ( ! $p instanceof WP_Post ) {
			continue;
		}
		if ( function_exists( 'sa_redirect_is_old_slug' ) && sa_redirect_is_old_slug( (string) $p->post_name, 'city' ) ) {
			continue;
		}
		$valid[] = $p;
	}
	if ( ! $valid ) {
		return '';
	}
	$ids = array();
	foreach ( $valid as $p ) {
		$ids[] = (int) $p->ID;
	}
	update_meta_cache( 'post', $ids ); // one query for sa_province_id of every city.
	$province_ids = array();
	foreach ( $ids as $id ) {
		$pid = (int) get_post_meta( $id, 'sa_province_id', true );
		if ( $pid ) {
			$province_ids[] = $pid;
		}
	}
	if ( $province_ids ) {
		_prime_post_caches( array_values( array_unique( $province_ids ) ), false, false );
	}

	// Group rank: canonical 31-province order; registry rank inside a province.
	$prov_rank = array();
	foreach ( sa_iran_provinces() as $i => $row ) {
		$prov_rank[ $row[1] ] = $i;
	}
	$reg_rank = array();
	if ( function_exists( 'sa_counties_by_province' ) ) {
		foreach ( sa_counties_by_province() as $pslug => $rows ) {
			foreach ( (array) $rows as $i => $row ) {
				if ( isset( $row['slug'] ) ) {
					$reg_rank[ $pslug ][ $row['slug'] ] = $i;
				}
			}
		}
	}
	$normalize = static function ( $name ) {
		$name = trim( (string) $name );
		$name = (string) preg_replace( '/^(?:شهرستان[\s\p{Z}\x{200C}]+)+/u', '', $name );
		return (string) preg_replace( '/[\s\p{Z}\x{200C}]+/u', '', $name );
	};

	$groups = array();
	foreach ( $valid as $p ) {
		$pid   = (int) get_post_meta( $p->ID, 'sa_province_id', true );
		$pslug = $pid ? (string) get_post_field( 'post_name', $pid ) : '';
		$key   = $pid ? 'p' . $pid : 'other';
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array(
				'slug'    => $pslug,
				'label'   => $pid ? (string) get_the_title( $pid ) : '',
				'capital' => ( '' !== $pslug && function_exists( 'sa_province_capital_name' ) ) ? (string) sa_province_capital_name( $pslug ) : '',
				'items'   => array(),
			);
		}
		$title    = (string) get_the_title( $p );
		$is_cap   = '' !== $groups[ $key ]['capital'] && '' !== $title && $normalize( $title ) === $normalize( $groups[ $key ]['capital'] );
		$rank     = isset( $reg_rank[ $pslug ][ $p->post_name ] ) ? $reg_rank[ $pslug ][ $p->post_name ] : PHP_INT_MAX;
		$groups[ $key ]['items'][] = array(
			'post'    => $p,
			'title'   => $title,
			'capital' => $is_cap,
			'rank'    => $rank,
		);
	}

	$group_list = array_values( $groups );
	usort(
		$group_list,
		static function ( $a, $b ) use ( $prov_rank ) {
			$ra = isset( $prov_rank[ $a['slug'] ] ) ? $prov_rank[ $a['slug'] ] : ( '' === $a['slug'] ? PHP_INT_MAX : 900 );
			$rb = isset( $prov_rank[ $b['slug'] ] ) ? $prov_rank[ $b['slug'] ] : ( '' === $b['slug'] ? PHP_INT_MAX : 900 );
			if ( $ra !== $rb ) {
				return $ra <=> $rb;
			}
			return strcmp( (string) $a['label'], (string) $b['label'] );
		}
	);

	$out = '<div class="sa-bars sa-bars--city" id="sa-bars">';
	$i   = 0;
	foreach ( $group_list as $group ) {
		$items = $group['items'];
		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['capital'] !== $b['capital'] ) {
					return $a['capital'] ? -1 : 1;
				}
				if ( $a['rank'] !== $b['rank'] ) {
					return $a['rank'] <=> $b['rank'];
				}
				return strcmp( (string) $a['title'], (string) $b['title'] );
			}
		);
		$prov_label = (string) preg_replace( '/^استان\s+/u', '', (string) $group['label'] );
		foreach ( $items as $item ) {
			$p = $item['post'];
			if ( ! empty( $item['capital'] ) ) {
				$sub = 'مرکز استان' . ( '' !== $prov_label ? ' ' . $prov_label : '' );
				$out .= sa_entity_bar_html( $item['title'], $sub, (string) get_permalink( $p ), $colors[ $i % 8 ], true, 'sa-bar--capital' );
			} else {
				$out .= sa_entity_bar_html( $item['title'], $group['label'], (string) get_permalink( $p ), $colors[ $i % 8 ], true );
			}
			$i++;
		}
	}
	$out .= '</div>';
	return $out;
}

/**
 * v2.13.5 — Attraction bars (archive «دیدنی‌ها» and its attraction_type filters): same
 * two-column bars as the province page; the secondary label is the attraction's city
 * (sa_city_id), falling back to its province (sa_province_id).
 *
 * @param WP_Post[] $posts Attractions of the current page of the main query.
 * @return string HTML (empty when there is nothing to list).
 */
function sa_attraction_bars_html( $posts ) {
	$colors = sa_bar_colors();
	$ids    = array();
	foreach ( (array) $posts as $p ) {
		if ( $p instanceof WP_Post ) {
			if ( function_exists( 'sa_redirect_is_old_slug' ) && sa_redirect_is_old_slug( (string) $p->post_name, 'attraction' ) ) {
				continue;
			}
			$ids[] = (int) $p->ID;
		}
	}
	if ( ! $ids ) {
		return '';
	}
	update_meta_cache( 'post', $ids ); // one query for sa_city_id / sa_province_id of every attraction.
	$parent_ids = array();
	foreach ( $ids as $id ) {
		foreach ( array( 'sa_city_id', 'sa_province_id' ) as $key ) {
			$pid = (int) get_post_meta( $id, $key, true );
			if ( $pid ) {
				$parent_ids[] = $pid;
			}
		}
	}
	if ( $parent_ids ) {
		_prime_post_caches( array_values( array_unique( $parent_ids ) ), false, false );
	}
	$out = '<div class="sa-bars sa-bars--attraction" id="sa-bars">';
	$i   = 0;
	foreach ( (array) $posts as $p ) {
		if ( ! $p instanceof WP_Post ) {
			continue;
		}
		if ( function_exists( 'sa_redirect_is_old_slug' ) && sa_redirect_is_old_slug( (string) $p->post_name, 'attraction' ) ) {
			continue;
		}
		$sub = '';
		foreach ( array( 'sa_city_id', 'sa_province_id' ) as $key ) {
			$pid = (int) get_post_meta( $p->ID, $key, true );
			if ( $pid && 'publish' === get_post_status( $pid ) ) {
				$sub = (string) get_the_title( $pid );
				break;
			}
		}
		$out .= sa_entity_bar_html( get_the_title( $p ), $sub, (string) get_permalink( $p ), $colors[ $i % 8 ], true );
		$i++;
	}
	$out .= '</div>';
	return $out;
}

/**
 * Semantic, crawlable prose + grouped index for /province/ and /city/ archives so
 * search engines and Readability/Markdown extractors never treat the directory
 * as a pure-link navigation widget.
 *
 * @param string         $type  province|city|attraction.
 * @param WP_Post[]|null $posts Posts from the main archive query.
 * @return string HTML.
 */
/**
 * Published provinces as slug => permalink for the crawlable directory.
 *
 * Accepts the archive query posts; falls back to a fresh lookup when they
 * are missing (same contract as sa_province_bars_html()). Added in 2.22.46:
 * sa_archive_crawlable_directory_html() already called this helper, and its
 * absence fatalled /province/ right after the bars.
 *
 * @param WP_Post[]|null $posts Published province posts.
 * @return array<string,string> slug => permalink.
 */
function sa_published_provinces_by_slug( $posts = null ) {
	$urls = array();
	if ( is_array( $posts ) ) {
		foreach ( $posts as $p ) {
			if ( $p instanceof WP_Post && 'publish' === $p->post_status && '' !== $p->post_name ) {
				$url = (string) get_permalink( $p );
				if ( '' !== $url ) {
					$urls[ $p->post_name ] = $url;
				}
			}
		}
		if ( $urls ) {
			return $urls;
		}
	}
	foreach ( sa_published_entity_ids( 'province' ) as $slug => $id ) {
		$url = (string) get_permalink( $id );
		if ( '' !== $url ) {
			$urls[ $slug ] = $url;
		}
	}
	return $urls;
}

function sa_archive_crawlable_directory_html( $type, $posts = null ) {
	if ( 'province' === $type ) {
		$provinces = sa_iran_provinces();
		$published = sa_published_provinces_by_slug( $posts );
		$links     = array();
		foreach ( $provinces as $row ) {
			$fa   = $row[0];
			$slug = $row[1];
			// English label derives from the slug, exactly like the bars;
			// $row[2]/$row[3] are hero-map coordinates, not a name.
			$en = ucwords( str_replace( '-', ' ', $slug ) );
			if ( isset( $published[ $slug ] ) ) {
				$links[] = '<a href="' . esc_url( $published[ $slug ] ) . '">استان ' . esc_html( $fa ) . '</a> (' . esc_html( $en ) . ')';
			} else {
				$links[] = 'استان ' . esc_html( $fa ) . ' (' . esc_html( $en ) . ')';
			}
		}
		$out  = '<section class="sa-archive__seo-index" aria-label="راهنمای متنی استان‌های ایران">';
		$out .= '<h2>مرور متنی ۳۱ استان ایران</h2>';
		$out .= '<p>در اطلس جغرافیایی سرزمین آریان، مشخصات، نقشه، تقسیمات کشوری، جمعیت سرشماری، همسایه‌ها و دیدنی‌های هر ۳۱ استان کشور گردآوری شده است: ' . implode( '، ', $links ) . '.</p>';
		$out .= '</section>';
		return $out;
	}

	if ( 'city' === $type ) {
		$grouped = array();
		foreach ( (array) $posts as $p ) {
			if ( ! ( $p instanceof WP_Post ) || 'publish' !== $p->post_status ) {
				continue;
			}
			if ( function_exists( 'sa_redirect_is_old_slug' ) && sa_redirect_is_old_slug( (string) $p->post_name, 'city' ) ) {
				continue;
			}
			$pid       = (int) get_post_meta( $p->ID, 'sa_province_id', true );
			$prov_name = ( $pid > 0 && 'publish' === get_post_status( $pid ) ) ? (string) get_the_title( $pid ) : 'سایر شهرستان‌ها';
			$prov_name = trim( (string) preg_replace( '/^استان\s+/u', '', $prov_name ) );
			if ( '' === $prov_name ) {
				$prov_name = 'سایر شهرستان‌ها';
			}
			$title = function_exists( 'sa_county_name' ) ? (string) sa_county_name( $p->ID, true ) : 'شهرستان ' . trim( (string) preg_replace( '/^(?:(?:شهرستان|شهر)\s+)+/u', '', (string) get_the_title( $p ) ) );
			$grouped[ $prov_name ][] = '<a href="' . esc_url( (string) get_permalink( $p ) ) . '">' . esc_html( $title ) . '</a>';
		}
		if ( empty( $grouped ) ) {
			return '';
		}
		ksort( $grouped, SORT_LOCALE_STRING );
		$total_cnt = 0;
		foreach ( $grouped as $items ) {
			$total_cnt += count( $items );
		}
		$out  = '<section class="sa-archive__seo-index" aria-label="فهرست شهرستان‌های ایران به تفکیک استان">';
		$out .= '<h2>فهرست شهرستان‌های ایران به تفکیک استان (' . esc_html( number_format_i18n( $total_cnt ) ) . ' شهرستان)</h2>';
		$out .= '<p>برای دسترسی سریع و نمایه‌سازی کامل، فهرست همهٔ شهرستان‌های منتشرشدهٔ ایران در ادامه به تفکیک استان آمده است و هر پیوند به شناسنامهٔ رسمی همان شهرستان متصل می‌شود:</p>';
		foreach ( $grouped as $prov => $links ) {
			$heading = ( 'سایر شهرستان‌ها' === $prov ) ? $prov : 'شهرستان‌های استان ' . $prov;
			$out    .= '<h3>' . esc_html( $heading ) . '</h3>';
			$out    .= '<p>شامل ' . esc_html( number_format_i18n( count( $links ) ) ) . ' شهرستان: ' . implode( '، ', $links ) . '.</p>';
		}
		$out .= '</section>';
		return $out;
	}

	return '';
}

/**
 * Province and city archives list everything on one page (the bars are a directory).
 *
 * @param WP_Query $query Main query.
 */
function sa_entity_bars_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->is_post_type_archive( array( 'province', 'city' ) ) ) {
		$query->set( 'posts_per_page', -1 );
		$query->set( 'no_found_rows', true );
	} elseif ( $query->is_post_type_archive( 'attraction' ) || $query->is_tax( 'attraction_type' ) ) {
		// v2.13.5: bars are compact — 40 per page (two columns × 20), title order, pagination kept.
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
		$query->set( 'posts_per_page', 40 );
	}
}
add_action( 'pre_get_posts', 'sa_entity_bars_query', 20 );

/**
 * Old paginated URLs of those archives (/province/page/2/) → the archive itself.
 */
function sa_entity_bars_redirect_paged() {
	if ( is_paged() && is_post_type_archive( array( 'province', 'city' ) ) ) {
		$type = get_query_var( 'post_type' );
		$type = is_array( $type ) ? reset( $type ) : $type;
		$url  = get_post_type_archive_link( (string) $type );
		if ( $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', 'sa_entity_bars_redirect_paged', 5 );
