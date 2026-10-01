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
			echo '<a href="' . esc_url( $src['url'] ) . '" rel="' . esc_attr( sa_source_rel( $src['url'] ) ) . '" target="_blank">' . esc_html( $src['title'] ) . '</a>';
		} else {
			echo esc_html( $src['title'] );
		}
		if ( $src['org'] ) {
			echo ' <span class="sa-sources__org">— ' . esc_html( $src['org'] ) . '</span>';
		}
		if ( $src['date'] ) {
			echo ' <span class="sa-sources__date">(دسترسی: ' . esc_html( sa_digits( $src['date'] ) ) . ')</span>';
		}
		echo '</li>';
	}
	echo '</ol></section>';
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
 * v2.3.0 — فقط نوع‌هایی که واقعاً محتوای منتشرشده دارند.
 *
 * چهار موجودیت (جاذبه، غذا، سوغات، مسیر سفر) هنوز صفر صفحه دارند. لینک‌دادن
 * به آرشیو خالی در هدر و فوتر *هر* صفحه، همان اشتباه لینک ۴۰۴ صفحه‌ی اصلی است
 * با شکل دیگر: خزنده و کاربر به بن‌بست می‌رسند. با انتشار اولین محتوای هر نوع،
 * خودبه‌خود به منو برمی‌گردد.
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
	$blog = (int) get_option( 'page_for_posts' );
	if ( $blog ) {
		echo '<li class="menu-item"><a href="' . esc_url( get_permalink( $blog ) ) . '">' . esc_html( get_the_title( $blog ) ) . '</a></li>';
	}
	echo '</ul>';
}
