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
 * @return string
 */
function sa_entity_bar_html( $title, $sub, $url, $color, $sub_fa = false ) {
	$style = ' style="--c:' . esc_attr( $color ) . '"';
	$em    = '' !== $sub ? '<em' . ( $sub_fa ? ' class="fa"' : '' ) . '>' . esc_html( $sub ) . '</em>' : '';
	if ( '' === $url ) {
		return '<span class="sa-bar sa-bar--soon"' . $style . '><b>' . esc_html( $title ) . '</b><em>به‌زودی</em></span>';
	}
	return '<a class="sa-bar" href="' . esc_url( $url ) . '"' . $style . '><b>' . esc_html( $title ) . '</b>' . $em . '</a>';
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
 * City bars: the given published cities (archive order), province as secondary label.
 *
 * @param WP_Post[] $posts City posts.
 * @return string
 */
function sa_city_bars_html( $posts ) {
	$colors = sa_bar_colors();
	$ids    = array();
	foreach ( (array) $posts as $p ) {
		if ( $p instanceof WP_Post ) {
			$ids[] = (int) $p->ID;
		}
	}
	if ( ! $ids ) {
		return '';
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
	$out = '<div class="sa-bars sa-bars--city" id="sa-bars">';
	$i   = 0;
	foreach ( (array) $posts as $p ) {
		if ( ! $p instanceof WP_Post ) {
			continue;
		}
		$pid  = (int) get_post_meta( $p->ID, 'sa_province_id', true );
		$prov = $pid ? (string) get_the_title( $pid ) : '';
		$out .= sa_entity_bar_html( get_the_title( $p ), $prov, (string) get_permalink( $p ), $colors[ $i % 8 ], true );
		$i++;
	}
	$out .= '</div>';
	return $out;
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
