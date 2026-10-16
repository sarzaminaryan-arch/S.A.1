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
		$rows[] = array( 'label' => 'استان', 'value' => '<a href="' . esc_url( get_permalink( $province ) ) . '">' . esc_html( sa_province_name_for_post( $province ) ) . '</a>', 'html' => true );
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
				$rel    = 'official_website' === $f['name'] ? 'noopener external' : sa_source_rel( $raw );
				$rows[] = array( 'label' => $label, 'value' => '<a href="' . esc_url( $raw ) . '" rel="' . esc_attr( $rel ) . '" target="_blank">' . esc_html( wp_parse_url( $raw, PHP_URL_HOST ) ) . '</a>', 'html' => true );
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
			case 'date':
				if ( 'last_verified_date' === $f['name'] ) {
					break; // shown by sa_facts_checked_note().
				}
				$ts = strtotime( $raw );
				if ( $ts ) {
					$rows[] = array( 'label' => $label, 'value' => '<time datetime="' . esc_attr( $raw ) . '">' . esc_html( wp_date( 'j F Y', $ts ) ) . '</time>', 'html' => true );
				}
				break;
			default:
				$rows[] = array( 'label' => $label, 'value' => sa_digits( $raw ), 'html' => false );
		}
	}

	/**
	 * Filter the key-facts rows of an entity.
	 *
	 * @param array $rows    Rows ( label / value / html ).
	 * @param int   $post_id Post ID.
	 */
	return (array) apply_filters( 'sa_entity_facts', $rows, $post_id );
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
		return $p ? 'استان ' . sa_province_name_for_post( $p ) : '';
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
	return $p ? 'استان ' . sa_province_name_for_post( $p ) : '';
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
	$parts = array();
	$date  = get_post_meta( $post_id, 'sa_facts_checked', true );
	$ts    = $date ? strtotime( $date ) : false;
	if ( $ts ) {
		$parts[] = 'آخرین بازبینی اطلاعات: <time datetime="' . esc_attr( $date ) . '">' . esc_html( wp_date( 'j F Y', $ts ) ) . '</time>';
	}
	$verified = get_post_meta( $post_id, 'sa_last_verified_date', true ); // v1.1 (attraction).
	$vts      = $verified ? strtotime( $verified ) : false;
	if ( $vts ) {
		$stale   = ( time() - $vts ) > sa_stale_after_days() * DAY_IN_SECONDS;
		$parts[] = 'آخرین راستی‌آزمایی ساعات بازدید، قیمت و دسترسی: <time datetime="' . esc_attr( $verified ) . '">' . esc_html( wp_date( 'j F Y', $vts ) ) . '</time>' . ( $stale ? ' <span class="sa-flag sa-flag--stale">ممکن است تغییر کرده باشد</span>' : '' );
	}
	if ( $parts ) {
		echo '<p class="sa-facts-checked">' . implode( ' · ', $parts ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Parse the sources box: one source per line "title | organisation | URL | access date".
 * Lines after a "---" separator are private editor notes (FACT CHECK REPORT) and are never output.
 *
 * @param int $post_id Post ID.
 * @return array[] [ title, org, url, date ]
 */
function sa_get_sources( $post_id ) {
	$raw = (string) get_post_meta( $post_id, 'sa_sources', true );
	if ( '' === trim( $raw ) ) {
		return array();
	}
	$public = preg_split( '/^\s*-{3,}.*$/mu', $raw, 2 );
	$lines  = preg_split( '/\r\n|\r|\n/', $public[0] );
	$out    = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$cols = array_map( 'trim', explode( '|', $line ) );
		$url  = '';
		foreach ( $cols as $i => $c ) {
			if ( preg_match( '#^https?://#i', $c ) ) {
				$url = $c;
				unset( $cols[ $i ] );
				break;
			}
		}
		if ( ! $url && preg_match( '#https?://\S+#i', $line, $m ) ) {
			$url     = $m[0];
			$cols[0] = trim( str_replace( $url, '', $cols[0] ) );
		}
		$cols  = array_values( $cols );
		$out[] = array(
			'title' => isset( $cols[0] ) ? $cols[0] : $url,
			'org'   => isset( $cols[1] ) ? $cols[1] : '',
			'url'   => $url,
			'date'  => isset( $cols[2] ) ? $cols[2] : '',
		);
	}
	return $out;
}

/**
 * Number of public source lines that carry a URL (publish gate).
 *
 * @param string $raw Raw sources text.
 * @return int
 */
function sa_count_sources( $raw ) {
	$public = preg_split( '/^\s*-{3,}.*$/mu', (string) $raw, 2 );
	return preg_match_all( '#https?://#i', $public[0] );
}

/**
 * Official domains that get a followed link; everything else is nofollow.
 *
 * @param string $url URL.
 * @return string rel attribute value
 */
function sa_source_rel( $url ) {
	$host     = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$official = array( '.gov.ir', 'unesco.org', 'amar.org.ir', 'mcth.ir', 'doe.ir', 'moi.ir', 'ichto.ir', 'un.org', 'britannica.com', 'iranicaonline.org' );
	foreach ( $official as $d ) {
		if ( $host === ltrim( $d, '.' ) || substr( $host, -strlen( $d ) ) === $d ) {
			return 'noopener external';
		}
	}
	return 'nofollow noopener external';
}

/**
 * Visible sources list (E-E-A-T).
 *
 * @param int $post_id Post ID.
 */
function sa_sources_section( $post_id ) {
	$sources = sa_get_sources( $post_id );
	if ( ! $sources ) {
		return;
	}
	echo '<section class="sa-sources" id="sources"><h2>منابع</h2><ol class="sa-sources__list">';
	foreach ( $sources as $src ) {
		echo '<li>';
		if ( $src['url'] ) {
			$host = strtolower( (string) wp_parse_url( $src['url'], PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', $host );
			echo '<a href="' . esc_url( $src['url'] ) . '" rel="' . esc_attr( sa_source_rel( $src['url'] ) ) . '" target="_blank">' . esc_html( $host ) . '</a>';
		} else {
			echo esc_html( $src['title'] );
		}
		echo '</li>';
	}
	echo '</ol></section>';
}

/**
 * Sources list for guides/posts (v2.13.0): title + linked host, so a reader can
 * tell «CDC — تصفیهٔ آب» from «EPA — ضدعفونی اضطراری» without opening the link.
 *
 * @param int $post_id Post ID.
 */
function sa_guide_sources_section( $post_id ) {
	$sources = sa_get_sources( $post_id );
	if ( ! $sources ) {
		return;
	}
	echo '<section class="sa-sources sa-sources--guide" id="sources"><h2>منابع</h2><ol class="sa-sources__list">';
	foreach ( $sources as $src ) {
		$title = trim( (string) $src['title'] );
		echo '<li>';
		if ( $src['url'] ) {
			$host = strtolower( (string) wp_parse_url( $src['url'], PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', $host );
			if ( '' !== $title && $title !== $src['url'] ) {
				echo esc_html( $title ) . ' <span class="sa-sources__sep" aria-hidden="true">—</span> ';
			}
			echo '<a href="' . esc_url( $src['url'] ) . '" rel="' . esc_attr( sa_source_rel( $src['url'] ) ) . '" target="_blank" dir="ltr">' . esc_html( $host ) . '</a>';
		} else {
			echo esc_html( $title );
		}
		echo '</li>';
	}
	echo '</ol></section>';
}

/**
 * Related guides: up to 3 other published posts from the same category (v2.13.0).
 *
 * @param int $post_id Current post ID.
 */
function sa_guide_related( $post_id ) {
	$cats = wp_get_post_categories( $post_id, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $cats ) || ! $cats ) {
		return;
	}
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'orderby'        => 'rand',
			'post__not_in'   => array( (int) $post_id ),
			'category__in'   => array_map( 'intval', $cats ),
		)
	);
	if ( ! $posts ) {
		return;
	}
	$cat  = get_category( (int) $cats[0] );
	$more = $cat && ! is_wp_error( $cat ) ? get_category_link( $cat ) : '';
	sa_cards_section( $posts, 'آموزش‌های مرتبط', $more, 'related-guides' );
}

/**
 * Related articles from other provinces (Module 3).
 * Shows 3 random entities of the same type from a different province (v2.7.1).
 *
 * @param int    $post_id   Current post ID.
 * @param string $post_type Current post type.
 */
function sa_related_articles( $post_id, $post_type ) {
	$entity = sa_entity( $post_type );
	if ( ! $entity ) {
		return;
	}

	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'orderby'        => 'rand',
		'post__not_in'   => array( $post_id ),
	);

	if ( 'province' !== $post_type ) {
		$province = sa_get_parent( $post_id, 'province' );
		if ( $province ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'sa_province_id',
					'value'   => $province->ID,
					'compare' => '!=',
				),
			);
		}
	}

	$posts = get_posts( $args );
	if ( ! $posts ) {
		return;
	}

	$label = sa_entity_label( $post_type, true ) . ' پیشنهادی از استان‌های دیگر';
	sa_cards_section( $posts, $label, '', 'related-articles' );
}

/**
 * Previous / next entity navigation (Module 3).
 * Navigates within the same province taxonomy term.
 *
 * @param int    $post_id   Current post ID.
 * @param string $post_type Current post type.
 */
function sa_entity_navigation( $post_id, $post_type ) {
	$entity = sa_entity( $post_type );
	if ( ! $entity ) {
		return;
	}

	$prev = get_previous_post( true, '', 'province_tax' );
	$next = get_next_post( true, '', 'province_tax' );

	if ( ! $prev && ! $next ) {
		return;
	}

	echo '<nav class="sa-entity-nav" aria-label="ناوبری مقالات"><div class="sa-entity-nav__links">';

	if ( $prev ) {
		echo '<a class="sa-entity-nav__link sa-entity-nav__prev" href="' . esc_url( get_permalink( $prev ) ) . '">';
		echo '<span class="sa-entity-nav__label">مقاله قبلی</span>';
		echo '<span class="sa-entity-nav__title">' . esc_html( get_the_title( $prev ) ) . '</span>';
		echo '</a>';
	} else {
		echo '<span class="sa-entity-nav__link sa-entity-nav__prev sa-entity-nav__empty"></span>';
	}

	if ( $next ) {
		echo '<a class="sa-entity-nav__link sa-entity-nav__next" href="' . esc_url( get_permalink( $next ) ) . '">';
		echo '<span class="sa-entity-nav__label">مقاله بعدی</span>';
		echo '<span class="sa-entity-nav__title">' . esc_html( get_the_title( $next ) ) . '</span>';
		echo '</a>';
	} else {
		echo '<span class="sa-entity-nav__link sa-entity-nav__next sa-entity-nav__empty"></span>';
	}

	echo '</div></nav>';
}

/**
 * Count uncertainty markers in a text (v1.1).
 *
 * @param string $text Text/HTML.
 * @return int
 */
function sa_count_markers( $text ) {
	$n = 0;
	foreach ( sa_uncertainty_markers() as $m ) {
		$n += substr_count( (string) $text, $m );
	}
	return $n;
}

/**
 * Render uncertainty markers as visible badges (transparency instead of guessing).
 *
 * @param string $html Content.
 * @return string
 */
function sa_render_markers( $html ) {
	if ( false === strpos( $html, '[' ) ) {
		return $html;
	}
	foreach ( sa_uncertainty_markers() as $m ) {
		$label = trim( $m, '[]' );
		$html  = str_replace( $m, '<mark class="sa-flag sa-flag--review" title="این داده هنوز با منبع معتبر تایید نشده است">' . esc_html( $label ) . '</mark>', $html );
	}
	return $html;
}

/**
 * Apply marker rendering to post content on the front end.
 *
 * @param string $content Content.
 * @return string
 */
function sa_content_markers_filter( $content ) {
	if ( is_admin() || is_feed() ) {
		return $content;
	}
	return sa_render_markers( $content );
}
add_filter( 'the_content', 'sa_content_markers_filter', 12 );

/**
 * External links inside post content get the same rel policy as the sources box (v1.0.2):
 * official domains followed, everything else `nofollow`; all open in a new tab. Existing
 * rel/target attributes are respected. The production prompts write inline citations as
 * `<sup>[n](URL)</sup>` (n = row in the sources box), which rely on this filter.
 *
 * @param string $content Content.
 * @return string
 */
function sa_content_external_links( $content ) {
	if ( is_admin() || is_feed() || false === stripos( $content, '<a ' ) ) {
		return $content;
	}
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	return preg_replace_callback(
		'/<a\s+([^>]*?)href=(["\'])(https?:\/\/[^"\']+)\2([^>]*)>/i',
		function ( $m ) use ( $home ) {
			$url  = $m[3];
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( ! $host || $host === $home || substr( $host, -strlen( '.' . $home ) ) === '.' . $home ) {
				return $m[0];
			}
			$attrs = $m[1] . $m[4];
			$tag   = '<a ' . trim( $m[1] . 'href=' . $m[2] . $url . $m[2] . $m[4] );
			if ( false === stripos( $attrs, 'rel=' ) ) {
				$tag .= ' rel="' . esc_attr( sa_source_rel( $url ) ) . '"';
			}
			if ( false === stripos( $attrs, 'target=' ) ) {
				$tag .= ' target="_blank"';
			}
			return $tag . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'sa_content_external_links', 13 );

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
/**
 * Entity types intentionally hidden from the public header navigation for now.
 * They stay active in the CMS and URLs are not changed; only the top navigation
 * waits until those sections are ready for editorial work.
 *
 * @return string[]
 */
function sa_nav_hidden_entity_types() {
	return (array) apply_filters( 'sa_nav_hidden_entity_types', array( 'travel_route', 'local_food', 'souvenir' ) );
}

/**
 * Rename old saved menu item titles and hide deferred archive items in header menus.
 * Existing WordPress menus keep their stored labels after a theme update, so the
 * filter makes old installs match the current IA without manual menu editing.
 *
 * @param WP_Post[] $items Menu items.
 * @param stdClass  $args  Menu args.
 * @return WP_Post[]
 */
function sa_filter_header_menu_items( $items, $args ) {
	$location = isset( $args->theme_location ) ? (string) $args->theme_location : '';
	if ( ! in_array( $location, array( 'primary', 'secondary' ), true ) ) {
		return $items;
	}

	$hidden          = sa_nav_hidden_entity_types();
	$hidden_urls      = array();
	$province_url     = untrailingslashit( sa_archive_url( 'province' ) );
	$city_url         = untrailingslashit( sa_archive_url( 'city' ) );
	$attraction_url   = untrailingslashit( sa_archive_url( 'attraction' ) );
	foreach ( $hidden as $hidden_type ) {
		$hidden_urls[] = untrailingslashit( sa_archive_url( $hidden_type ) );
	}

	$out = array();
	foreach ( $items as $item ) {
		$object = isset( $item->object ) ? (string) $item->object : '';
		$url    = isset( $item->url ) ? untrailingslashit( (string) $item->url ) : '';
		if ( in_array( $object, $hidden, true ) || in_array( $url, $hidden_urls, true ) ) {
			continue;
		}
		if ( 'province' === $object || $url === $province_url ) {
			$item->title = 'استان‌ها';
		} elseif ( 'city' === $object || $url === $city_url ) {
			$item->title = 'شهرستان‌ها';
		} elseif ( 'attraction' === $object || $url === $attraction_url ) {
			$item->title = 'دیدنی‌ها';
		}
		$out[] = $item;
	}

	return $out;
}
add_filter( 'wp_nav_menu_objects', 'sa_filter_header_menu_items', 10, 2 );

/**
 * Canonical 4-pillar structure of the site:
 * ۱) استان‌ها، ۲) شهرستان‌ها، ۳) دیدنی‌ها، ۴) آموزش (طبیعت‌گردی و بقا).
 * Only pillars with published items are returned so no empty archive is linked.
 *
 * @return array<int,array{slug:string,label:string,full_label:string,url:string,current:bool}>
 */
function sa_site_pillars() {
	$pillars    = array();
	$nav_types  = sa_nav_entity_types();
	$core_order = array(
		'province'   => array( 'label' => 'استان‌ها', 'full_label' => 'استان‌ها' ),
		'city'       => array( 'label' => 'شهرستان‌ها', 'full_label' => 'شهرستان‌ها' ),
		'attraction' => array( 'label' => 'دیدنی‌ها', 'full_label' => 'دیدنی‌ها' ),
	);
	foreach ( $core_order as $type => $meta ) {
		if ( ! in_array( $type, $nav_types, true ) ) {
			continue;
		}
		$pillars[] = array(
			'slug'       => $type,
			'label'      => $meta['label'],
			'full_label' => $meta['full_label'],
			'url'        => sa_archive_url( $type ),
			'current'    => is_post_type_archive( $type ) || is_singular( $type ) || ( 'attraction' === $type && is_tax( 'attraction_type' ) ),
		);
	}
	$edu = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
	if ( $edu ) {
		$full_label = function_exists( 'sa_education_full_label' ) ? sa_education_full_label() : 'آموزش (طبیعت‌گردی و بقا)';
		$pillars[]  = array(
			'slug'       => 'education',
			'label'      => $edu['label'],
			'full_label' => $full_label,
			'url'        => $edu['url'],
			'current'    => ! empty( $edu['current'] ),
		);
	}
	return $pillars;
}

/**
 * v2.3.0 — فقط نوع‌هایی که واقعاً محتوای منتشرشده دارند.
 *
 * سه موجودیت غذا، سوغات و مسیر سفر فعلاً از منوی عمومی پنهان‌اند؛ دیدنی می‌ماند. لینک‌دادن
 * به آرشیو خالی در هدر و فوتر *هر* صفحه، همان اشتباه لینک ۴۰۴ صفحه‌ی اصلی است
 * با شکل دیگر: خزنده و کاربر به بن‌بست می‌رسند. تا زمان تصمیم بعدی کاربر در منوی عمومی نمایش داده نمی‌شوند.
 *
 * @return string[]
 */
function sa_nav_entity_types() {
	$cached = get_transient( 'sa_nav_types' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$out = array();
	foreach ( sa_entity_types() as $type ) {
		if ( in_array( $type, sa_nav_hidden_entity_types(), true ) ) {
			continue;
		}
		$c = wp_count_posts( $type );
		if ( isset( $c->publish ) && (int) $c->publish > 0 ) {
			$out[] = $type;
		}
	}
	/**
	 * نوع‌های قابل نمایش در ناوبری.
	 *
	 * @param string[] $out نوع‌ها.
	 */
	$out = (array) apply_filters( 'sa_nav_entity_types', $out );
	set_transient( 'sa_nav_types', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}
add_action( 'transition_post_status', function () { delete_transient( 'sa_nav_types' ); } );
add_action( 'deleted_post', function () { delete_transient( 'sa_nav_types' ); } );

function sa_fallback_menu() {
	echo '<ul id="primary-menu" class="menu">';
	echo '<li class="menu-item' . ( is_front_page() ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( home_url( '/' ) ) . '">خانه</a></li>';
	foreach ( sa_nav_entity_types() as $type ) {
		$current = is_post_type_archive( $type ) || is_singular( $type ) ? ' current-menu-item' : '';
		echo '<li class="menu-item' . esc_attr( $current ) . '"><a href="' . esc_url( sa_archive_url( $type ) ) . '">' . esc_html( sa_entity_label( $type, true ) ) . '</a></li>';
	}
	// v2.13.1 — «آموزش‌ها» (دستهٔ کمپینگ و بقا) کنار آرشیو موجودیت‌ها؛ فقط وقتی آموزشی منتشر شده باشد.
	$edu = function_exists( 'sa_education_nav_item' ) ? sa_education_nav_item() : null;
	if ( $edu ) {
		echo '<li class="menu-item sa-menu-item--education' . ( $edu['current'] ? ' current-menu-item' : '' ) . '"><a href="' . esc_url( $edu['url'] ) . '">' . esc_html( $edu['label'] ) . '</a></li>';
	}
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) {
		echo '<li class="menu-item"><a href="' . esc_url( get_permalink( $blog ) ) . '">' . esc_html( get_the_title( $blog ) ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Tiny review badge for any unpopulated slot in the Cyber-Tech Identity Cards (v2.19.1).
 *
 * @return string HTML span.
 */
function sa_cyber_id_review_badge() {
	return '<span class="sa-cyber-id__review" title="این مورد در نوبت تکمیل و بازبینی تحریریه است">نیازمند بازبینی</span>';
}

/**
 * Compact HUD updated/verified badge inside Cyber-Tech Identity Cards (v2.19.2).
 *
 * @param int $post_id Post ID.
 */
function sa_updated_badge( $post_id = 0 ) {
	$post_id  = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( ! $post_id ) {
		return;
	}
	$verified = trim( (string) get_post_meta( $post_id, 'sa_last_verified_date', true ) );
	if ( '' === $verified ) {
		$verified = trim( (string) get_post_meta( $post_id, 'sa_facts_checked', true ) );
	}
	$ts = $verified ? strtotime( $verified ) : false;
	if ( $ts ) {
		echo '<span class="sa-cyber-id__type-tag"><time datetime="' . esc_attr( $verified ) . '">بازبینی: ' . esc_html( wp_date( 'Y/m/d', $ts ) ) . '</time></span>';
		return;
	}
	$mod_iso = get_the_modified_date( 'Y-m-d', $post_id );
	$mod_lbl = get_the_modified_date( 'Y/m/d', $post_id );
	if ( $mod_lbl ) {
		echo '<span class="sa-cyber-id__type-tag"><time datetime="' . esc_attr( (string) $mod_iso ) . '">به‌روزرسانی: ' . esc_html( (string) $mod_lbl ) . '</time></span>';
	}
}

/**
 * Render a cell value or the tiny «نیازمند بازبینی» badge when empty (v2.19.1).
 *
 * @param string $value      Raw text or pre-escaped HTML.
 * @param bool   $allow_html Whether $value is already safe HTML.
 * @return string Safe HTML.
 */
function sa_cyber_id_value_or_review( $value, $allow_html = false ) {
	$plain = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $plain ) {
		return sa_cyber_id_review_badge();
	}
	return $allow_html ? (string) $value : esc_html( (string) $value );
}

/**
 * Official English name for a province slug from data/provinces.php (v2.19.1).
 *
 * @param string $province_slug Province ASCII slug.
 * @return string English province name (e.g. "Lorestan").
 */
function sa_cyber_id_province_en( $province_slug ) {
	static $map = null;
	if ( null === $map ) {
		$map  = array();
		$file = SA_CHILD_DIR . 'data/provinces.php';
		if ( is_readable( $file ) ) {
			foreach ( (array) include $file as $row ) {
				if ( ! empty( $row['slug'] ) && ! empty( $row['en'] ) ) {
					$map[ sanitize_key( (string) $row['slug'] ) ] = (string) $row['en'];
				}
			}
		}
	}
	$slug = sanitize_key( (string) $province_slug );
	if ( isset( $map[ $slug ] ) ) {
		return $map[ $slug ];
	}
	return function_exists( 'sa_province_profile_english_name' ) ? sa_province_profile_english_name( $slug ) : '';
}

/**
 * Fallback record for an attraction post from data/ready-articles.php or data/attractions.php (v2.19.1).
 *
 * Ensures that even before re-running the importer in WP Admin, all ready attractions
 * display 100% of their Cyber-Tech Identity Card fields.
 *
 * @param int $post_id Post ID.
 * @return array<string,mixed>
 */
function sa_cyber_id_attraction_fallback( $post_id ) {
	static $catalog = null;
	if ( null === $catalog ) {
		$catalog = array();
		$ready   = SA_CHILD_DIR . 'data/ready-articles.php';
		if ( is_readable( $ready ) ) {
			foreach ( (array) include $ready as $k => $row ) {
				if ( is_array( $row ) ) {
					$s             = ! empty( $row['slug'] ) ? (string) $row['slug'] : (string) $k;
					$catalog[ $s ] = $row;
				}
			}
		}
	}
	$slug = (string) get_post_field( 'post_name', $post_id );
	return ( '' !== $slug && isset( $catalog[ $slug ] ) ) ? $catalog[ $slug ] : array();
}

/**
 * Read attraction meta with automatic fallback to data/ready-articles.php (v2.19.2).
 *
 * @param int                  $post_id Post ID.
 * @param string               $key     Meta key.
 * @param array<string,string> $fb_meta Fallback meta array.
 * @return string
 */
function sa_cyber_id_read_meta( $post_id, $key, $fb_meta = array() ) {
	$val = trim( (string) get_post_meta( $post_id, $key, true ) );
	if ( '' === $val && is_array( $fb_meta ) && isset( $fb_meta[ $key ] ) ) {
		$val = trim( (string) $fb_meta[ $key ] );
	}
	return $val;
}

/**
 * Read attraction taxonomy terms with automatic fallback to data/ready-articles.php (v2.19.2).
 *
 * @param int                 $post_id  Post ID.
 * @param string              $taxonomy Taxonomy slug.
 * @param array<string,mixed> $fb       Fallback article array.
 * @return string
 */
function sa_cyber_id_read_terms( $post_id, $taxonomy, $fb = array() ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( is_array( $terms ) && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		return implode( '، ', wp_list_pluck( $terms, 'name' ) );
	}
	if ( is_array( $fb ) && ! empty( $fb['terms'][ $taxonomy ] ) && is_array( $fb['terms'][ $taxonomy ] ) ) {
		return implode( '، ', array_values( $fb['terms'][ $taxonomy ] ) );
	}
	return '';
}

/**
 * Fallback county telemetry from data/region-facts.php and curated county profiles (v2.19.1).
 *
 * @param string $county_slug   County slug.
 * @param string $province_slug Parent province slug.
 * @return array<string,string>
 */
function sa_cyber_id_county_fallback( $county_slug, $province_slug = '' ) {
	static $region_facts = null;
	if ( null === $region_facts ) {
		$file         = SA_CHILD_DIR . 'data/region-facts.php';
		$region_facts = is_readable( $file ) ? (array) include $file : array();
	}

	$slug  = sanitize_key( (string) $county_slug );
	$base  = preg_replace( '/-(?:city|county)$/', '', $slug );
	$pslug = sanitize_key( (string) $province_slug );

	$curated = array(
		'dorud'          => array(
			'population' => '۱۷۴٬۵۰۸ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۴۵۷ متر',
			'area'       => '۱٬۳۲۶ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۵ دهستان · ۲ شهر · ۱۳۵ آبادی',
			'climate'    => 'کوهستانی معتدل (زاگرس میانی)',
			'season'     => 'بهار (فروردین تا خرداد)، تابستان و پاییز',
			'language'   => 'لری (بختیاری، سیلاخوری و خرم‌آبادی) و فارسی',
			'livelihood' => 'کشاورزی (برنج و گندم)، صنعت سیمان و سنگ، دامداری و گردشگری طبیعی',
			'access'     => 'راه‌آهن سراسری تهران–جنوب + محور جاده‌ای بروجرد–دورود–ازنا و خرم‌آباد',
			'neighbors'  => 'بروجرد (شمال) · ازنا (شرق) · الیگودرز (جنوب‌شرق) · خرم‌آباد (غرب) · شازند (شمال‌شرق)',
		),
		'khorramabad'    => array(
			'population' => '۵۰۶٬۴۷۱ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۱۴۷ متر',
			'area'       => '۴٬۹۶۸ کیلومتر مربع',
			'divisions'  => '۴ بخش · ۱۷ دهستان · ۵ شهر · ۵۳۰ آبادی',
			'climate'    => 'معتدل کوهستانی و مدیترانه‌ای زاگرس',
			'season'     => 'بهار (فروردین و اردیبهشت) و پاییز',
			'language'   => 'لری خرم‌آبادی، لکی و فارسی',
			'livelihood' => 'خدمات شهری، کشاورزی، باغداری، دامپروری و گردشگری تاریخی–طبیعی',
			'access'     => 'آزادراه خرم‌آباد–پل‌زال و خرم‌آباد–بروجرد + فرودگاه خرم‌آباد',
			'neighbors'  => 'بروجرد و سلسله (شمال) · دورود و الیگودرز (شرق) · چگنی و کوهدشت (غرب) · اندیمشک (جنوب)',
		),
		'amol'           => array(
			'population' => '۴۰۱٬۶۳۹ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۷۶ متر (مرکز) تا ۵٬۶۱۰ متر (قله دماوند)',
			'area'       => '۳٬۰۷۴ کیلومتر مربع',
			'divisions'  => '۵ بخش · ۱۲ دهستان · ۶ شهر · ۳۶۰ آبادی',
			'climate'    => 'معتدل خزری در جلگه و سرد کوهستانی در بخش لاریجان',
			'season'     => 'بهار، پاییز و تابستان (برای ارتفاعات لاریجان و دماوند)',
			'language'   => 'مازندرانی (طبری آملی) و فارسی',
			'livelihood' => 'برنج‌کاری، مرکبات، صنایع غذایی و لبنی، گردشگری کوهستانی و جنگلی',
			'access'     => 'محور شریانی هراز (جاده ۷۷ تهران–آمل) و بزرگراه کناره خزر',
			'neighbors'  => 'محمودآباد (شمال) · بابل (شرق) · نور (غرب) · تهران و دماوند (جنوب)',
		),
		'kuhrang'        => array(
			'population' => '۴۱٬۵۲۸ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۲٬۳۶۵ متر (چلگرد) تا ۴٬۲۲۱ متر (زردکوه)',
			'area'       => '۳٬۷۹۱ کیلومتر مربع',
			'divisions'  => '۳ بخش · ۷ دهستان · ۳ شهر · ۲۰۰ آبادی',
			'climate'    => 'سرد و برف‌گیر کوهستانی (سرچشمه کارون و زاینده‌رود)',
			'season'     => 'نیمه دوم بهار و تابستان',
			'language'   => 'لری بختیاری و فارسی',
			'livelihood' => 'دامداری عشایری، زنبورداری، گیاهان دارویی و طبیعت‌گردی',
			'access'     => 'محور شهرکرد–فارسان–چلگرد و مسیر کوهستانی بازفت–مسجدسلیمان',
			'neighbors'  => 'الیگودرز و دزفول (شمال و غرب) · فارسان و اردل (شرق) · مسجدسلیمان و اندیکا (جنوب)',
		),
		'kabutarahang'   => array(
			'population' => '۱۲۶٬۰۶۲ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۶۷۰ متر (مرکز) تا ۱٬۹۸۰ متر (علیصدر)',
			'area'       => '۳٬۹۵۰ کیلومتر مربع',
			'divisions'  => '۳ بخش · ۱۰ دهستان · ۳ شهر · ۱۲۸ آبادی',
			'climate'    => 'سرد و نیمه‌خشک کوهستانی',
			'season'     => 'بهار، تابستان و اوایل پاییز',
			'language'   => 'ترکی آذربایجانی، فارسی و کردی',
			'livelihood' => 'کشاورزی، گردشگری غار علیصدر، صنایع دستی و دامپروری',
			'access'     => 'محور همدان–بیجار/زنجان و آزادراه تهران–همدان + ایستگاه راه‌آهن',
			'neighbors'  => 'خدابنده و ایجرود (شمال) · رزن و فامنین (شرق) · بهار و همدان (جنوب) · بیجار (غرب)',
		),
		'galikash'       => array(
			'population' => '۶۳٬۱۷۳ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۲۷۰ متر (مرکز) تا ۲٬۴۱۱ متر (پارک ملی گلستان)',
			'area'       => '۸۵۰ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۴ دهستان · ۲ شهر · ۶۵ آبادی',
			'climate'    => 'معتدل هیرکانی در غرب و نیمه‌خشک کوهستانی در شرق',
			'season'     => 'بهار (فروردین و اردیبهشت) و پاییز (آبان و آذر)',
			'language'   => 'فارسی، طبری، ترکمنی و ترکی',
			'livelihood' => 'کشاورزی، باغداری، پرورش ابریشم و بوم‌گردی حاشیه پارک ملی گلستان',
			'access'     => 'بزرگراه سراسری تهران–گرگان–مشهد (گذرگاه تنگراه)',
			'neighbors'  => 'کلاله (شمال) · مینودشت (غرب) · سملقان و جاجرم (شرق) · شاهرود (جنوب)',
		),
		'qeshm'          => array(
			'population' => '۱۴۸٬۹۹۳ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱۰ تا ۳۹۷ متر (بام قشم / کیش‌کوه)',
			'area'       => '۱٬۴۹۱ کیلومتر مربع (بزرگ‌ترین جزیره ایران)',
			'divisions'  => '۳ بخش · ۷ دهستان · ۴ شهر · ۶۶ آبادی',
			'climate'    => 'گرم و مرطوب ساحلی (خلیج فارس و تنگه هرمز)',
			'season'     => 'پاییز و زمستان (آبان تا اواخر فروردین)',
			'language'   => 'فارسی با گویش بندری و جزیره‌ای',
			'livelihood' => 'تجارت دریایی، صیادی، ژئوتوریسم جهانی و بوم‌گردی، صنایع دریایی',
			'access'     => 'فرودگاه بین‌المللی قشم + اسکله شهید حقانی بندرعباس و اسکله لافت/پل',
			'neighbors'  => 'بندرعباس و بندرخمیر (شمال؛ مرز آبی تنگه خوران) · خلیج فارس و تنگه هرمز',
		),
		'bandar-e-anzali' => array(
			'population' => '۱۳۹٬۰۱۶ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => 'منفی ۲۶ متر (تراز ساحلی دریای خزر)',
			'area'       => '۲۹۹ کیلومتر مربع',
			'divisions'  => '۱ بخش · ۲ دهستان · ۱ شهر · ۲۷ آبادی',
			'climate'    => 'معتدل و پربارش خزری',
			'season'     => 'بهار، تابستان (فصل لاله تالابی) و پاییز (پرنده‌نگری)',
			'language'   => 'گیلکی و فارسی',
			'livelihood' => 'بندرگاه و تجارت دریایی، شیلات، گردشگری تالاب بین‌المللی انزلی و منطقه آزاد',
			'access'     => 'بزرگراه رشت–انزلی، محور ساحلی انزلی–آستارا و فرودگاه نزدیک رشت',
			'neighbors'  => 'دریای خزر (شمال) · خمام و رشت (شرق) · صومعه‌سرا (جنوب) · رضوانشهر (غرب)',
		),
		'tabas'          => array(
			'population' => '۷۲٬۶۱۷ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۶۹۰ متر',
			'area'       => '۵۵٬۴۶۰ کیلومتر مربع (پهناورترین شهرستان ایران)',
			'divisions'  => '۳ بخش · ۸ دهستان · ۳ شهر · ۲۰۰ آبادی',
			'climate'    => 'خشک و بیابانی در دشت و معتدل در دره‌های کوهستانی',
			'season'     => 'پاییز، زمستان و اوایل بهار (آبان تا فروردین)',
			'language'   => 'فارسی با گویش طبسی و خراسانی',
			'livelihood' => 'معادن زغال‌سنگ، نخل‌داری و مرکبات، کشاورزی قنات‌پایه و کویرگردی',
			'access'     => 'فرودگاه طبس + ایستگاه راه‌آهن طبس + محورهای یزد–طبس و بیرجند–طبس–مشهد',
			'neighbors'  => 'بردسکن و بجستان (شمال) · بشرویه و سرایان (شرق) · راور و کرمان (جنوب) · خور و اردکان (غرب)',
		),
		'boshruyeh'      => array(
			'population' => '۲۶٬۰۶۴ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۸۸۰ متر',
			'area'       => '۶٬۹۱۵ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۴ دهستان · ۲ شهر · ۴۵ آبادی',
			'climate'    => 'گرم و خشک کویری',
			'season'     => 'پاییز، زمستان و اوایل بهار',
			'language'   => 'فارسی با گویش خراسانی',
			'livelihood' => 'کشاورزی (پسته، زعفران، پنبه)، دامپروری و گردشگری بافت تاریخی و کویر',
			'access'     => 'محور طبس–بشرویه–فردوس و ایستگاه راه‌آهن مسیر مشهد–بافق',
			'neighbors'  => 'بردسکن و بجستان (شمال) · فردوس و سرایان (شرق) · طبس (غرب و جنوب)',
		),
		'marvdasht'      => array(
			'population' => '۳۲۳٬۴۳۴ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۶۲۰ متر',
			'area'       => '۳٬۹۳۱ کیلومتر مربع',
			'divisions'  => '۴ بخش · ۱۴ دهستان · ۵ شهر · ۲۴۰ آبادی',
			'climate'    => 'معتدل کوهستانی در شمال و نیمه‌خشک در دشت مرودشت',
			'season'     => 'بهار (فروردین و اردیبهشت) و پاییز (مهر و آبان)',
			'language'   => 'فارسی، لری و ترکی قشقایی',
			'livelihood' => 'کشاورزی قطب گندم و برنج، صنایع پتروشیمی و غذایی، و گردشگری میراث جهانی تخت‌جمشید',
			'access'     => 'بزرگراه شیراز–مرودشت–اصفهان (۴۵ کیلومتری شمال شیراز)',
			'neighbors'  => 'پاسارگاد و اقلید (شمال) · ارسنجان (شرق) · شیراز و زرقان (جنوب) · سپیدان و بیضا (غرب)',
		),
		'isfahan'        => array(
			'population' => '۲٬۲۴۳٬۲۴۹ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۵۷۰ متر',
			'area'       => '۱۵٬۲۵۸ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۷ دهستان · ۶ شهر · ۱۵۰ آبادی',
			'climate'    => 'معتدل و خشک فلات مرکزی',
			'season'     => 'بهار (فروردین تا خرداد) و پاییز (مهر و آبان)',
			'language'   => 'فارسی با لهجه اصفهانی',
			'livelihood' => 'صنایع دستی و گردشگری جهانی، صنعت و فناوری، خدمات و کشاورزی حوضه زاینده‌رود',
			'access'     => 'فرودگاه بین‌المللی اصفهان + راه‌آهن و آزادراه‌های شمال–جنوب و شرق–غرب',
			'neighbors'  => 'برخوار و شاهین‌شهر (شمال) · کوهپایه و ورزنه (شرق) · شهرضا (جنوب) · خمینی‌شهر و فلاورجان (غرب)',
		),
		'kashan'         => array(
			'population' => '۳۶۴٬۴۸۲ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۹۸۲ متر (مرکز) تا ۳٬۸۹۵ متر (کرکس)',
			'area'       => '۴٬۳۹۲ کیلومتر مربع',
			'divisions'  => '۴ بخش · ۷ دهستان · ۷ شهر · ۷۵ آبادی',
			'climate'    => 'گرم و خشک در دشت و معتدل کوهستانی در نیاسر و قمصر',
			'season'     => 'بهار (فصل گلاب‌گیری) و پاییز',
			'language'   => 'فارسی و گویش‌های راجی در روستاهای کهن',
			'livelihood' => 'فرش ماشینی و دستباف، گلاب و عرقیات گیاهی، گردشگری باغ فین و خانه‌های تاریخی',
			'access'     => 'آزادراه تهران–قم–کاشان–اصفهان و ایستگاه راه‌آهن کاشان',
			'neighbors'  => 'قم و دلیجان (شمال و غرب) · آران و بیدگل (شرق) · نطنز و میمه (جنوب)',
		),
		'fuman'          => array(
			'population' => '۹۲٬۳۱۰ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۳۴ متر (مرکز) تا ۱٬۰۵۰ متر (شهر تاریخی ماسوله)',
			'area'       => '۱٬۰۰۲ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۶ دهستان · ۳ شهر · ۱۵۸ آبادی',
			'climate'    => 'معتدل خزری در جلگه و خنک مهتابی در ارتفاعات ماسوله و قلعه رودخان',
			'season'     => 'بهار، تابستان و پاییز هزاررنگ',
			'language'   => 'گیلکی، تالشی و فارسی',
			'livelihood' => 'برنج‌کاری، چای‌کاری، صنایع دستی و گردشگری ماسوله و قلعه رودخان',
			'access'     => 'بزرگراه رشت–فومن (۲۵ کیلومتری غرب رشت) و محورهای فومن–ماسوله و فومن–قلعه رودخان',
			'neighbors'  => 'صومعه‌سرا و ماسال (شمال) · رشت و شفت (شرق) · طارم و خلخال (جنوب و غرب)',
		),
		'kerman'         => array(
			'population' => '۷۳۸٬۷۲۴ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۷۵۵ متر (مرکز) تا ۲۰۰ متر (کلوت‌های شهداد در لوت)',
			'area'       => '۴۵٬۴۰۱ کیلومتر مربع',
			'divisions'  => '۶ بخش · ۲۰ دهستان · ۱۳ شهر · ۴۵۰ آبادی',
			'climate'    => 'نیمه‌خشک کوهستانی در کرمان و فراخشک بیابانی در بخش شهداد و لوت',
			'season'     => 'پاییز و زمستان (برای کلوت‌های شهداد) و بهار (برای شهر کرمان و ماهان)',
			'language'   => 'فارسی با لهجه کرمانی',
			'livelihood' => 'پسته‌کاری، معادن و صنایع، گردشگری جهانی بیابان لوت و باغ شازده ماهان',
			'access'     => 'فرودگاه بین‌المللی کرمان + راه‌آهن سراسری + محور کرمان–شهداد',
			'neighbors'  => 'راور و طبس (شمال) · بیرجند و زاهدان (شرق) · بم و جیرفت (جنوب) · زرند و بردسیر (غرب)',
		),
		'dezful'         => array(
			'population' => '۴۴۳٬۹۷۱ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱۴۳ متر (مرکز) تا ارتفاعات زاگرس در بخش شهیون و سالندکوه',
			'area'       => '۴٬۷۶۲ کیلومتر مربع',
			'divisions'  => '۴ بخش · ۱۱ دهستان · ۱۱ شهر · ۳۶۰ آبادی',
			'climate'    => 'نیمه‌گرمسیری در دشت و معتدل کوهستانی در بخش شهیون (آبشار شوی)',
			'season'     => 'زمستان و اوایل بهار (بهمن تا اواسط فروردین)',
			'language'   => 'فارسی با گویش دزفولی و لری بختیاری',
			'livelihood' => 'قطب کشاورزی و کشت و صنعت، باغداری مرکبات، بازرگانی و گردشگری طبیعی–تاریخی',
			'access'     => 'فرودگاه دزفول + راه‌آهن سراسری (ایستگاه اندیمشک/تله‌زنگ) + بزرگراه اندیمشک–اهواز',
			'neighbors'  => 'اندیمشک و لرستان (شمال) · لالی و مسجدسلیمان (شرق) · شوشتر و گتوند (جنوب) · شوش (غرب)',
		),
		'torbatjam'      => array(
			'population' => '۲۶۷٬۶۷۱ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۹۲۸ متر',
			'area'       => '۸٬۱۸۴ کیلومتر مربع',
			'divisions'  => '۵ بخش · ۱۳ دهستان · ۵ شهر · ۱۷۰ آبادی',
			'climate'    => 'نیمه‌خشک و معتدل دشتی',
			'season'     => 'بهار (فروردین و اردیبهشت) و پاییز',
			'language'   => 'فارسی خراسانی (گویش جامی) و ترکمنی/بلوچی در برخی آبادی‌ها',
			'livelihood' => 'کشاورزی (خربزه، زعفران، گندم، پسته)، دامپروری و گردشگری عرفانی–تاریخی',
			'access'     => 'بزرگراه مشهد–فریمان–تربت جام–تایباد (۱۶۰ کیلومتری جنوب‌شرق مشهد) و ایستگاه راه‌آهن',
			'neighbors'  => 'سرخس و مشهد (شمال) · تایباد و مرز افغانستان (شرق و جنوب) · باخرز و فریمان (غرب)',
		),
		'aran-bidgol'    => array(
			'population' => '۱۰۳٬۵۱۷ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۹۱۲ متر (مرکز) تا بند ریگ و ریگ جن در دشت کویر',
			'area'       => '۶٬۰۵۱ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۳ دهستان · ۴ شهر · ۱۵ آبادی',
			'climate'    => 'گرم و خشک کویری (همجوار پارک ملی کویر و ریگ جن)',
			'season'     => 'پاییز و زمستان (آبان تا اسفند) و اوایل بهار',
			'language'   => 'فارسی و گویش‌های کهن کویری (آرانی و بیدگلی)',
			'livelihood' => 'قطب فرش ماشینی، صنایع نساجی، پسته و کویرگردی (مرنجاب و ریگ جن)',
			'access'     => 'آزادراه تهران–قم–کاشان و جاده کاشان–آران و بیدگل (۱۰ کیلومتری شمال کاشان)',
			'neighbors'  => 'دریاچه نمک و گرمسار (شمال) · اردکان و نائین (شرق) · نطنز (جنوب) · کاشان (غرب)',
		),
		'sharekurd'      => array(
			'population' => '۳۱۵٬۹۸۰ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۲٬۰۶۶ متر (بلندترین مرکز استان ایران — بام ایران)',
			'area'       => '۲٬۰۰۶ کیلومتر مربع',
			'divisions'  => '۳ بخش · ۴ دهستان · ۷ شهر · ۴۵ آبادی',
			'climate'    => 'سرد و نیمه‌مرطوب کوهستانی',
			'season'     => 'بهار (اردیبهشت و خرداد) و تابستان',
			'language'   => 'فارسی (گویش دهکردی)، لری بختیاری و ترکی قشقایی',
			'livelihood' => 'خدمات، دانشگاه و فناوری، صنایع غذایی و گز، کشاورزی و دامپروری',
			'access'     => 'فرودگاه شهرکرد + بزرگراه اصفهان–شهرکرد و محورهای خوزستان و لرستان',
			'neighbors'  => 'بن و سامان (شمال) · فرخشهر و اصفهان (شرق) · کیار و بروجن (جنوب) · فارسان (غرب)',
		),
		'rasht'          => array(
			'population' => '۹۵۶٬۹۷۱ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => 'منفی ۷ تا مثبت ۲۰ متر',
			'area'       => '۱٬۲۱۵ کیلومتر مربع',
			'divisions'  => '۵ بخش · ۱۴ دهستان · ۵ شهر · ۲۹۰ آبادی',
			'climate'    => 'معتدل و مرطوب خزری',
			'season'     => 'بهار، پاییز و زمستان (شهر خلاق خوراک یونسکو)',
			'language'   => 'گیلکی و فارسی',
			'livelihood' => 'برنج‌کاری، صنایع غذایی، بازرگانی، خدمات و گردشگری شهری و خوراک',
			'access'     => 'فرودگاه بین‌المللی سردار جنگل رشت + راه‌آهن قزوین–رشت + آزادراه قزوین–رشت',
			'neighbors'  => 'دریای خزر و خمام (شمال) · آستانه اشرفیه و سیاهکل (شرق) · رودبار (جنوب) · شفت و فومن (غرب)',
		),
		'semnan'         => array(
			'population' => '۱۹۶٬۵۲۱ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۱۳۰ متر',
			'area'       => '۲۰٬۰۰۰ کیلومتر مربع',
			'divisions'  => '۱ بخش · ۱ دهستان · ۱ شهر · ۳۵ آبادی',
			'climate'    => 'خشک و نیمه‌خشک در کوهپایه البرز جنوبی و کویری در جنوب',
			'season'     => 'پاییز، زمستان و بهار',
			'language'   => 'فارسی و زبان سمنانی (از زبان‌های کهن ایرانی شمال‌غربی)',
			'livelihood' => 'صنعت و معدن، کشاورزی و پسته، خدمات دانشگاهی و گردشگری جاده ابریشم و کویر',
			'access'     => 'بزرگراه تهران–مشهد + راه‌آهن سراسری تهران–مشهد + فرودگاه سمنان',
			'neighbors'  => 'مهدی‌شهر و سرخه (شمال و غرب) · دامغان (شرق) · دشت کویر و آران و بیدگل (جنوب)',
		),
		'damavand'       => array(
			'population' => '۱۲۵٬۴۸۰ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۹۵۰ متر',
			'area'       => '۱٬۸۸۸ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۵ دهستان · ۵ شهر · ۱۱۵ آبادی',
			'climate'    => 'معتدل و سرد کوهستانی در دامنه جنوبی البرز',
			'season'     => 'بهار، تابستان و پاییز (فصل باغ‌های سیب)',
			'language'   => 'فارسی و گویش دماوندی / تاتی',
			'livelihood' => 'باغداری (سیب و گردو)، زنبورداری، صنایع تبدیلی و گردشگری ییلاقی',
			'access'     => 'بزرگراه تهران–جاجرود–دماوند–فیروزکوه و محور هراز (۴۵ کیلومتری شرق تهران)',
			'neighbors'  => 'آمل (شمال) · فیروزکوه (شرق) · گرمسار و ورامین (جنوب) · پردیس و شمیرانات (غرب)',
		),
		'hamedan'        => array(
			'population' => '۶۷۶٬۱۰۵ نفر (سرشماری ۱۳۹۵)',
			'elevation'  => '۱٬۸۵۰ متر (دامنه الوند)',
			'area'       => '۲٬۸۳۱ کیلومتر مربع',
			'divisions'  => '۲ بخش · ۶ دهستان · ۴ شهر · ۱۰۵ آبادی',
			'climate'    => 'سرد و کوهستانی (دامنه رشته‌کوه الوند)',
			'season'     => 'بهار و تابستان (خرداد تا شهریور)',
			'language'   => 'فارسی با لهجه همدانی و ترکی در بخش‌هایی از شهرستان',
			'livelihood' => 'گردشگری تاریخی و طبیعی (هگمتانه، بوعلی‌سینا، گنجنامه)، سفال و چرم، کشاورزی و خدمات',
			'access'     => 'آزادراه تهران–ساوه–همدان + راه‌آهن تهران–همدان + فرودگاه همدان',
			'neighbors'  => 'کبودرآهنگ و فامنین (شمال) · ملایر و کمیجان (جنوب و شرق) · تویسرکان، اسدآباد و بهار (غرب)',
		),
	);

	$out = isset( $curated[ $base ] ) ? $curated[ $base ] : ( isset( $curated[ $slug ] ) ? $curated[ $slug ] : array() );

	// Coordinates from data/region-facts.php if available.
	if ( '' !== $pslug && isset( $region_facts['counties'][ $pslug ] ) && is_array( $region_facts['counties'][ $pslug ] ) ) {
		$prov_counties = $region_facts['counties'][ $pslug ];
		$crow          = isset( $prov_counties[ $slug ] ) ? $prov_counties[ $slug ] : ( isset( $prov_counties[ $base ] ) ? $prov_counties[ $base ] : null );
		if ( is_array( $crow ) && ! empty( $crow['lat'] ) && ! empty( $crow['lng'] ) ) {
			$out['lat'] = (string) $crow['lat'];
			$out['lng'] = (string) $crow['lng'];
		}
	}
	if ( '' === ( isset( $out['climate'] ) ? $out['climate'] : '' ) && '' !== $pslug && ! empty( $region_facts['provinces'][ $pslug ]['climate'] ) ) {
		$out['climate'] = (string) $region_facts['provinces'][ $pslug ]['climate'];
	}

	return $out;
}

/**
 * Strip legacy duplicate «شناسنامه سریع ...» bullet sections from entity body content (v2.19.1).
 *
 * Runs at priority 8 (before sa_toc_filter at 11) so that even database posts imported
 * before v2.19.1 never repeat the top Cyber-Tech Identity Card in the body or TOC.
 *
 * @param string $content Post content HTML.
 * @return string Filtered HTML.
 */
function sa_strip_duplicate_body_identity( $content ) {
	if ( is_admin() || ! is_singular( array( 'province', 'city', 'attraction' ) ) ) {
		return $content;
	}
	if ( false === strpos( $content, 'شناسنامه سریع' ) ) {
		return $content;
	}
	$cleaned = preg_replace(
		'~<h2[^>]*>[^<]*شناسنامه\s+سریع[^<]*</h2>\s*<ul[^>]*>.*?</ul>~isu',
		'',
		(string) $content
	);
	return is_string( $cleaned ) ? $cleaned : $content;
}
add_filter( 'the_content', 'sa_strip_duplicate_body_identity', 8 );

