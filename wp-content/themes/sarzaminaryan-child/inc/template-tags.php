<?php
/**
 * Template tags used by the child templates.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Facts table rows for an entity: label → formatted value (empty values skipped).
 *
 * @param int $post_id Post ID.
 * @return array[] [ ['label'=>…, 'value'=>…, 'html'=>bool] ]
 */
function sa_entity_facts( $post_id ) {
	$type = get_post_type( $post_id );
	$e    = sa_entity( $type );
	if ( ! $e ) {
		return array();
	}
	$rows = array();

	// Parents first.
	$province = sa_get_parent( $post_id, 'province' );
	$city     = sa_get_parent( $post_id, 'city' );
	if ( 'province' !== $type && $province ) {
		$rows[] = array( 'label' => 'استان', 'value' => '<a href="' . esc_url( get_permalink( $province ) ) . '">' . esc_html( get_the_title( $province ) ) . '</a>', 'html' => true );
	}
	if ( $city && ! in_array( $type, array( 'province', 'city' ), true ) ) {
		$rows[] = array( 'label' => 'شهر', 'value' => '<a href="' . esc_url( get_permalink( $city ) ) . '">' . esc_html( get_the_title( $city ) ) . '</a>', 'html' => true );
	}

	// Taxonomy enums.
	foreach ( $e['enums'] as $enum ) {
		$terms = get_the_terms( $post_id, $enum['taxonomy'] );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$links = array();
			foreach ( $terms as $t ) {
				$links[] = '<a href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . '</a>';
			}
			$rows[] = array( 'label' => sa_taxonomies_config()[ $enum['taxonomy'] ]['singular'], 'value' => implode( '، ', $links ), 'html' => true );
		}
	}

	// Scalar fields.
	foreach ( $e['fields'] as $f ) {
		$raw = get_post_meta( $post_id, $f['key'], true );
		if ( '' === $raw || null === $raw ) {
			continue;
		}
		$label = preg_replace( '/\s*\(.*?\)\s*$/u', '', $f['label'] ); // strip "(هر مورد در یک خط)".
		switch ( $f['type'] ) {
			case 'integer':
			case 'number':
				$value = sa_number( $raw, 'number' === $f['type'] ? 1 : 0 );
				if ( ! empty( $f['unit'] ) ) {
					$value .= ' ' . sa_unit_label( $f['unit'] );
				}
				$rows[] = array( 'label' => $label, 'value' => $value, 'html' => false );
				break;
			case 'float':
				// lat/long are shown together as a map link below.
				break;
			case 'url':
				if ( 'google_map_url' === $f['name'] ) {
					break; // rendered as map button.
				}
				$rows[] = array( 'label' => $label, 'value' => '<a href="' . esc_url( $raw ) . '" rel="nofollow noopener" target="_blank">' . esc_html( wp_parse_url( $raw, PHP_URL_HOST ) ) . '</a>', 'html' => true );
				break;
			case 'list':
				$rows[] = array( 'label' => $label, 'value' => esc_html( implode( '، ', sa_list( $raw ) ) ), 'html' => true );
				break;
			case 'reference':
				$ref = get_post( (int) $raw );
				if ( $ref && 'publish' === $ref->post_status ) {
					$rows[] = array( 'label' => $label, 'value' => '<a href="' . esc_url( get_permalink( $ref ) ) . '">' . esc_html( get_the_title( $ref ) ) . '</a>', 'html' => true );
				}
				break;
			case 'boolean':
				$rows[] = array( 'label' => $label, 'value' => '1' === $raw ? 'بله' : 'خیر', 'html' => false );
				break;
			default:
				$rows[] = array( 'label' => $label, 'value' => sa_digits( $raw ), 'html' => false );
		}
	}
	return $rows;
}

/**
 * Persian unit label.
 *
 * @param string $unit Unit.
 * @return string
 */
function sa_unit_label( $unit ) {
	$units = array( 'km2' => 'کیلومتر مربع', 'km' => 'کیلومتر', 'm' => 'متر' );
	return isset( $units[ $unit ] ) ? $units[ $unit ] : $unit;
}

/**
 * Map link (Google Maps URL field or coordinates).
 *
 * @param int $post_id Post ID.
 * @return string URL or ''
 */
function sa_map_url( $post_id ) {
	$url = get_post_meta( $post_id, 'sa_google_map_url', true );
	if ( $url ) {
		return $url;
	}
	$c = sa_get_coords( $post_id );
	return $c ? sprintf( 'https://www.google.com/maps/search/?api=1&query=%s,%s', $c[0], $c[1] ) : '';
}

/**
 * Entity badge text (type label) for cards.
 *
 * @param string $type CPT.
 * @return string
 */
function sa_type_badge( $type ) {
	return sa_entity_label( $type );
}

/**
 * Card sub-line: parent name (city or province) or a key taxonomy term.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function sa_card_meta( $post_id ) {
	$type = get_post_type( $post_id );
	if ( 'post' === $type ) {
		return get_the_date( '', $post_id );
	}
	if ( 'province' === $type ) {
		$term = sa_province_term_for_post( $post_id );
		$c    = $term ? get_term_meta( $term->term_id, 'sa_center', true ) : '';
		return $c ? 'مرکز: ' . $c : '';
	}
	if ( 'city' === $type ) {
		$p = sa_get_parent( $post_id, 'province' );
		return $p ? 'استان ' . get_the_title( $p ) : '';
	}
	if ( 'travel_route' === $type ) {
		$terms = get_the_terms( $post_id, 'travel_duration' );
		return $terms && ! is_wp_error( $terms ) ? $terms[0]->name : '';
	}
	$c = sa_get_parent( $post_id, 'city' );
	if ( $c ) {
		return get_the_title( $c );
	}
	$p = sa_get_parent( $post_id, 'province' );
	return $p ? 'استان ' . get_the_title( $p ) : '';
}

/**
 * Render a grid of cards for posts.
 *
 * @param WP_Post[]|int[] $posts   Posts.
 * @param string          $heading Optional heading.
 * @param string          $more    Optional "see all" URL.
 * @param string          $id      Section id.
 */
function sa_cards_section( $posts, $heading = '', $more = '', $id = '' ) {
	if ( empty( $posts ) ) {
		return;
	}
	echo '<section class="sa-section"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '>';
	if ( $heading ) {
		echo '<div class="sa-section__head"><h2 class="sa-section__title">' . esc_html( $heading ) . '</h2>';
		if ( $more ) {
			echo '<a class="sa-section__more" href="' . esc_url( $more ) . '">مشاهده همه ←</a>';
		}
		echo '</div>';
	}
	echo '<div class="sa-grid-cards">';
	foreach ( $posts as $p ) {
		$post = get_post( $p );
		if ( $post ) {
			set_query_var( 'sa_card_post', $post );
			get_template_part( 'template-parts/card', 'entity' );
		}
	}
	echo '</div></section>';
}

/**
 * Post date/time HTML (Jalali via wp_date filter; datetime attribute stays ISO/Gregorian).
 */
function sa_posted_on() {
	printf(
		'<span class="posted-on"><time class="entry-date published" datetime="%1$s">%2$s</time></span>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
	if ( get_the_modified_time( 'U' ) - get_the_time( 'U' ) > DAY_IN_SECONDS ) {
		printf(
			' <span class="updated-on">(به‌روزرسانی: <time class="updated" datetime="%1$s">%2$s</time>)</span>',
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);
	}
}

/**
 * "Facts checked" note for entities (E-E-A-T freshness).
 *
 * @param int $post_id Post ID.
 */
function sa_facts_checked_note( $post_id ) {
	$date = get_post_meta( $post_id, 'sa_facts_checked', true );
	if ( ! $date ) {
		return;
	}
	$ts = strtotime( $date );
	if ( $ts ) {
		echo '<p class="sa-facts-checked">آخرین بازبینی اطلاعات: <time datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( 'j F Y', $ts ) ) . '</time></p>';
	}
}

/**
 * Social links from Customizer.
 *
 * @return array slug => [url,label]
 */
function sa_social_links() {
	$nets  = array(
		'instagram' => 'اینستاگرام',
		'telegram'  => 'تلگرام',
		'x'         => 'ایکس (توییتر)',
		'youtube'   => 'یوتیوب',
		'aparat'    => 'آپارات',
		'linkedin'  => 'لینکدین',
	);
	$links = array();
	foreach ( $nets as $slug => $label ) {
		$url = get_theme_mod( 'sa_social_' . $slug, '' );
		if ( $url ) {
			$links[ $slug ] = array( 'url' => $url, 'label' => $label );
		}
	}
	return $links;
}

/**
 * Fallback menu: entity archives (used when no menu is assigned).
 */
function sa_fallback_menu() {
	echo '<ul id="primary-menu" class="menu">';
	echo '<li class="menu-item' . ( is_front_page() ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">خانه</a></li>';
	foreach ( sa_entity_types() as $type ) {
		$current = is_post_type_archive( $type ) || is_singular( $type ) ? ' current-menu-item' : '';
		echo '<li class="menu-item' . esc_attr( $current ) . '"><a href="' . esc_url( sa_archive_url( $type ) ) . '">' . esc_html( sa_entity_label( $type, true ) ) . '</a></li>';
	}
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) {
		echo '<li class="menu-item"><a href="' . esc_url( get_permalink( $blog ) ) . '">' . esc_html( get_the_title( $blog ) ) . '</a></li>';
	}
	echo '</ul>';
}
