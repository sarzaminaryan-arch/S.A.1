<?php
/**
 * County presentation only: never rewrites stored articles or metadata.
 * Google Maps is the owner's explicit exception to the external-embed policy.
 *
 * @package Sarzaminaryan_Child
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Only transform the primary county article, not excerpts, REST, feeds or widgets. */
function sa_county_page_context() {
	return ! is_admin() && ! is_feed() && is_singular( 'city' ) && in_the_loop() && is_main_query()
		&& (int) get_the_ID() === (int) get_queried_object_id();
}

/** Exact host boundary, not a substring match (google.com.evil is not google.com). */
function sa_county_host_is( $host, $domain ) {
	return $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain;
}

/** Parse HTML without network access; fail open if DOM is unavailable. */
function sa_county_document( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return null;
	}
	$doc = new DOMDocument( '1.0', 'UTF-8' );
	$previous = libxml_use_internal_errors( true );
	$ok = $doc->loadHTML( '<?xml encoding="UTF-8"><html><body><div id="sa-county-root">' . $html . '</div></body></html>', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	return $ok ? $doc : null;
}

/** Serialize the children of a DOM node. */
function sa_county_inner_html( $node ) {
	$html = '';
	foreach ( $node->childNodes as $child ) {
		$html .= $node->ownerDocument->saveHTML( $child );
	}
	return $html;
}

/** Normalize visible text only, for conservative exact duplicate comparisons. */
function sa_county_plain( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
}

/** Hide only an entire two-column table whose every label/value matches the card. */
function sa_county_duplicate_table( $table, $post_id ) {
	$profile = sa_county_profile_data( $post_id );
	$map = array( 'مرکز شهرستان' => 'center_fa', 'مساحت' => 'area_km2', 'ارتفاع' => 'elevation_m', 'نام انگلیسی شهرستان' => 'name_en', 'مهم‌ترین شهر شهرستان' => 'main_city', 'زبان' => 'language', 'اقلیم' => 'climate', 'بهترین فصل سفر' => 'best_season' );
	$matched = 0;
	foreach ( $table->getElementsByTagName( 'tr' ) as $row ) {
		$cells = array();
		foreach ( $row->childNodes as $cell ) {
			if ( $cell instanceof DOMElement && in_array( $cell->tagName, array( 'td', 'th' ), true ) ) {
				$cells[] = sa_county_plain( $cell->textContent );
			}
		}
		if ( count( $cells ) !== 2 || ! isset( $map[ $cells[0] ] ) || ! isset( $profile[ $map[ $cells[0] ] ] )
			|| '' === $cells[1] || $cells[1] !== sa_county_plain( (string) $profile[ $map[ $cells[0] ] ] ) ) {
			return false;
		}
		++$matched;
	}
	return $matched > 0;
}

/**
 * Extract known editorial sections at heading boundaries. Preserve unrelated sections.
 * Each call is independent; the filter stores only its own post's result.
 */
function sa_county_split_content( $html ) {
	$result = array( 'content' => $html, 'general' => '', 'facts' => '', 'faq' => '', 'urls' => array() );
	$doc = sa_county_document( $html );
	if ( ! $doc ) {
		return $result;
	}
	$xpath = new DOMXPath( $doc );
	$root = $doc->getElementById( 'sa-county-root' );
	if ( ! $root ) {
		return $result;
	}
	// Collect before removing source sections, superscripts or solicitation blocks.
	foreach ( $xpath->query( './/a[@href]', $root ) as $link ) {
		$url = html_entity_decode( $link->getAttribute( 'href' ), ENT_QUOTES, 'UTF-8' );
		if ( sa_citation_is_external( $url ) ) {
			$title = sa_county_plain( $link->textContent );
			$result['urls'][] = array( 'url' => $url, 'title' => preg_match( '/^[\[\]0-9۰-۹٠-٩\s]+$/u', $title ) ? '' : $title );
		}
	}
	$headings = iterator_to_array( $xpath->query( './/h2 | .//h3', $root ) );
	foreach ( $headings as $heading ) {
		if ( ! $heading->parentNode ) {
			continue;
		}
		$text = sa_county_plain( $heading->textContent );
		$key = '';
		if ( preg_match( '/^مشخصات کلی شهرستان/u', $text ) ) {
			$key = 'general';
		} elseif ( preg_match( '/^(?:حقایق|دانستنی‌های) جالب/u', $text ) ) {
			$key = 'facts';
		} elseif ( preg_match( '/^(?:سوالات|سؤالات|پرسش‌های) (?:متداول|رایج)/u', $text ) ) {
			$key = 'faq';
		} elseif ( preg_match( '/^(?:منابع(?:\s|$)|پانویس|ارجاع)/u', $text ) ) {
			$key = 'sources';
		} elseif ( preg_match( '/^از مردم.*(?:درخواست|دیار)/u', $text ) ) {
			$key = 'community';
		}
		if ( ! $key ) {
			continue;
		}
		$nodes = array( $heading );
		$next = $heading->nextSibling;
		$body = '';
		while ( $next ) {
			if ( $next instanceof DOMElement && preg_match( '/^h[1-6]$/', $next->tagName ) && (int) substr( $next->tagName, 1 ) <= (int) substr( $heading->tagName, 1 ) ) {
				break;
			}
			$nodes[] = $next;
			$body .= $doc->saveHTML( $next );
			$next = $next->nextSibling;
		}
		if ( isset( $result[ $key ] ) ) {
			$result[ $key ] .= $body;
		}
		foreach ( $nodes as $node ) {
			$node->parentNode->removeChild( $node );
		}
	}
	foreach ( iterator_to_array( $xpath->query( './/aside[contains(concat(" ",normalize-space(@class)," ")," sa-community ")]', $root ) ) as $node ) {
		$node->parentNode->removeChild( $node );
	}
	// Only numeric reference superscripts, not exponents or scientific units.
	foreach ( iterator_to_array( $xpath->query( './/sup', $root ) ) as $node ) {
		if ( preg_match( '/^(?:\[\s*)?[0-9۰-۹٠-٩]+(?:\s*\])?$/u', trim( $node->textContent ) ) && ( preg_match( '/(?:sa-cite|sa-ref|reference)/', $node->getAttribute( 'class' ) ) || $node->getElementsByTagName( 'a' )->length > 0 ) ) {
			$node->parentNode->removeChild( $node );
		}
	}
	foreach ( iterator_to_array( $xpath->query( './/a[@href]', $root ) ) as $node ) {
		if ( preg_match( '/^\[\s*[0-9۰-۹٠-٩]+\s*\]$/u', trim( $node->textContent ) ) ) {
			$node->parentNode->removeChild( $node );
		}
	}
	// Regex on text nodes only: never mutate hrefs, HTML attributes, code or scripts.
	foreach ( $xpath->query( './/text()[not(ancestor::code or ancestor::pre or ancestor::script or ancestor::style)]', $root ) as $text ) {
		$text->nodeValue = preg_replace( '/\[\s*[0-9۰-۹٠-٩]+\s*\]/u', '', $text->nodeValue );
	}
	// Remove old map frames in county prose; a controlled Google embed replaces them.
	foreach ( iterator_to_array( $xpath->query( './/iframe[@src]', $root ) ) as $node ) {
		$host = strtolower( (string) wp_parse_url( $node->getAttribute( 'src' ), PHP_URL_HOST ) );
		if ( sa_county_host_is( $host, 'google.com' ) || sa_county_host_is( $host, 'openstreetmap.org' ) ) {
			$node->parentNode->removeChild( $node );
		}
	}
	// Conservative deduplication; differing values/units or unknown rows remain visible.
	foreach ( iterator_to_array( $xpath->query( './/table', $root ) ) as $table ) {
		if ( sa_county_duplicate_table( $table, get_the_ID() ) ) {
			$table->parentNode->removeChild( $table );
		}
	}
	$result['content'] = sa_county_inner_html( $root );
	foreach ( array( 'general', 'facts', 'faq' ) as $key ) {
		$result[ $key ] = sa_county_clean_references( $result[ $key ] );
	}
	return $result;
}

/** Clean moved fragments using text nodes too (never URLs or attributes). */
function sa_county_clean_references( $html ) {
	$doc = sa_county_document( $html );
	if ( ! $doc ) { return $html; }
	$root = $doc->getElementById( 'sa-county-root' );
	$xpath = new DOMXPath( $doc );
	foreach ( iterator_to_array( $xpath->query( './/sup', $root ) ) as $node ) {
		if ( preg_match( '/(?:sa-cite|sa-ref|reference)/', $node->getAttribute( 'class' ) ) ) {
			$node->parentNode->removeChild( $node );
		}
	}
	foreach ( $xpath->query( './/text()[not(ancestor::code or ancestor::pre or ancestor::script or ancestor::style)]', $root ) as $text ) {
		$text->nodeValue = preg_replace( '/\[\s*[0-9۰-۹٠-٩]+\s*\]/u', '', $text->nodeValue );
	}
	return sa_county_inner_html( $root );
}

/** Merge legacy general information into the card, omitting only exact known rows. */
function sa_county_general_information( $post_id ) {
	$parts = sa_county_split_content( get_post_field( 'post_content', $post_id ) );
	$doc = sa_county_document( $parts['general'] );
	if ( ! $doc ) { return $parts['general']; }
	$root = $doc->getElementById( 'sa-county-root' );
	$xpath = new DOMXPath( $doc );
	foreach ( iterator_to_array( $xpath->query( './/table', $root ) ) as $table ) {
		foreach ( iterator_to_array( $table->getElementsByTagName( 'tr' ) ) as $row ) {
			$probe = $doc->createElement( 'table' );
			$probe->appendChild( $row->cloneNode( true ) );
			if ( '' === sa_county_plain( $row->textContent ) || sa_county_duplicate_table( $probe, $post_id ) ) {
				$row->parentNode->removeChild( $row );
			}
		}
		if ( ! $table->getElementsByTagName( 'tr' )->length ) { $table->parentNode->removeChild( $table ); }
	}
	return sa_county_inner_html( $root );
}

/** Before TOC/citation filters: move known sections without editing the database. */
function sa_county_content_filter( $content ) {
	if ( ! sa_county_page_context() ) {
		return $content;
	}
	$parts = sa_county_split_content( $content );
	$GLOBALS['sa_county_page_parts'][ get_the_ID() ] = $parts;
	if ( function_exists( 'sa_toc_register' ) ) {
		if ( $parts['facts'] ) { sa_toc_register( 'حقایق جالب', 'county-fun-facts', 880 ); }
		if ( $parts['faq'] ) { sa_toc_register( 'سوالات متداول', 'faq', 900 ); }
		if ( $parts['urls'] ) { sa_toc_register( 'منابع و پانویس‌ها', 'sources', 990 ); }
	}
	return $parts['content'];
}
add_filter( 'the_content', 'sa_county_content_filter', 10 );

/** Public HTTP(S) sources only; no HTTP probes on a visitor request (SSRF/latency). */
function sa_county_sources( $post_id ) {
	$parts = isset( $GLOBALS['sa_county_page_parts'][ $post_id ] ) ? $GLOBALS['sa_county_page_parts'][ $post_id ] : sa_county_split_content( get_post_field( 'post_content', $post_id ) );
	$rows = array_merge( sa_get_sources( $post_id ), $parts['urls'] );
	if ( function_exists( 'sa_get_faq' ) ) {
		foreach ( sa_get_faq( $post_id ) as $faq ) {
			$faq_parts = sa_county_split_content( $faq['a'] );
			$rows = array_merge( $rows, $faq_parts['urls'] );
		}
	}
	$valid = array();
	foreach ( $rows as $row ) {
		$url = esc_url_raw( $row['url'], array( 'http', 'https' ) );
		$host = rtrim( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), '.' );
		if ( ! $url || ! wp_http_validate_url( $url ) || sa_county_host_is( $host, 'google.com' ) ) {
			continue;
		}
		$url = preg_replace( '/#.*$/', '', $url );
		$wiki = sa_county_host_is( $host, 'wikipedia.org' );
		$key = $wiki ? 'wikipedia' : $url;
		$score = ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? 1 : 0 );
		foreach ( array( 'gov.ir', 'unesco.org', 'amar.org.ir', 'mcth.ir', 'doe.ir', 'iranicaonline.org', 'britannica.com' ) as $official ) {
			if ( sa_county_host_is( $host, $official ) ) {
				$score += 10;
				break;
			}
		}
		$title = function_exists( 'sa_public_editorial_text' ) ? sa_public_editorial_text( $row['title'] ) : $row['title'];
		$row = array( 'url' => $url, 'title' => trim( $title ), 'score' => $score );
		if ( ! isset( $valid[ $key ] ) || $score > $valid[ $key ]['score'] ) {
			$valid[ $key ] = $row;
		}
	}
	usort( $valid, static function ( $a, $b ) { return $b['score'] <=> $a['score']; } );
	return array_slice( $valid, 0, 10 );
}

/** Render the single consolidated source list at the bottom of the county page. */
function sa_county_sources_section( $post_id ) {
	$sources = sa_county_sources( $post_id );
	if ( ! $sources ) {
		return;
	}
	echo '<section class="sa-sources" id="sources"><h2>' . esc_html__( 'منابع و پانویس‌ها', 'sarzaminaryan-child' ) . '</h2><ol>';
	foreach ( $sources as $source ) {
		$host = (string) wp_parse_url( $source['url'], PHP_URL_HOST );
		echo '<li><a dir="auto" href="' . esc_url( $source['url'] ) . '" target="_blank" rel="nofollow noopener noreferrer">' . esc_html( $source['title'] ?: $host ) . '</a> <bdi>' . esc_html( $host ) . '</bdi></li>';
	}
	echo '</ol>';
	echo '</section>';
}

/** Compact controlled embed: no arbitrary iframe URL from post metadata. */
function sa_county_google_map( $post_id ) {
	$profile = sa_county_profile_data( $post_id );
	$query = null !== $profile['lat'] && null !== $profile['lng'] ? $profile['lat'] . ',' . $profile['lng'] : get_the_title( $post_id ) . '، ' . $profile['province_name'] . '، ایران';
	$url = 'https://www.google.com/maps?q=' . rawurlencode( $query ) . '&output=embed';
	echo '<section class="sa-county-map" aria-label="' . esc_attr__( 'نقشه شهرستان', 'sarzaminaryan-child' ) . '"><iframe src="' . esc_url( $url ) . '" title="' . esc_attr( get_the_title( $post_id ) ) . '" width="640" height="240" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe></section>';
}

/** Known provinces only: no same-province or unknown-province fallback. */
function sa_county_related_articles( $post_id ) {
	$province = sa_county_profile_province_post( $post_id );
	if ( ! $province ) {
		return;
	}
	$query = new WP_Query( array(
		'post_type' => 'city', 'post_status' => 'publish', 'posts_per_page' => 3,
		'orderby' => 'rand', 'post__not_in' => array( (int) $post_id ), 'no_found_rows' => true,
		'meta_query' => array(
			'relation' => 'OR',
			array( 'relation' => 'AND',
				array( 'key' => 'province_id', 'value' => (int) $province->ID, 'compare' => '!=', 'type' => 'NUMERIC' ),
				array( 'key' => 'province_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
			array( 'relation' => 'AND',
				array( 'relation' => 'OR', array( 'key' => 'province_id', 'compare' => 'NOT EXISTS' ), array( 'key' => 'province_id', 'value' => '', 'compare' => '=' ) ),
				array( 'key' => 'sa_province_id', 'value' => (int) $province->ID, 'compare' => '!=', 'type' => 'NUMERIC' ),
				array( 'key' => 'sa_province_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		),
	) );
	sa_cards_section( $query->posts, __( 'مقالات مرتبط', 'sarzaminaryan-child' ), '', 'related-articles' );
}

/** Late stylesheet, isolated from other entity types and the parent. */
function sa_county_page_assets() {
	if ( is_singular( 'city' ) ) {
		wp_enqueue_style( 'sa-county-page', SA_CHILD_URI . 'assets/css/county-page.css', array( 'sarzaminaryan-child' ), SA_CHILD_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'sa_county_page_assets', 40 );
