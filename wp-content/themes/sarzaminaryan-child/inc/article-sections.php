<?php
/**
 * Owner-requested section removal, before automatic TOC/citation generation.
 * Removes HTML, not CSS visibility. Stored content is never rewritten on upgrade.
 * Unaffected markup is copied byte-for-byte; identity cards are protected.
 *
 * @package Sarzaminaryan_Child
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Match only the five retired headings and their existing template variants. */
function sa_article_section_is_retired( $title ) {
	$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' );
	$title = str_replace( array( "\xE2\x80\x8C", "\xC2\xA0", 'ي', 'ك' ), array( ' ', ' ', 'ی', 'ک' ), $title );
	$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
	$title = (string) preg_replace( '/^[\s۰-۹٠-٩0-9.()\-–—:؛،]+|[\s:؛،.]+$/u', '', $title );
	return (bool) preg_match(
		'/^(?:(?:مشخصات کلی|موقعیت جغرافیایی|نقشه و لوکیشن)(?:$| (?:شهرستان|استان) .+$)|مردم این دیار|از مردم این دیار،? یک درخواست داریم|از مردم عزیز شهرستان .+|شهرستان ?های استان(?: .+)?|شهرستان استان .+)$/u',
		$title
	);
}

/** Read an HTML attribute without normalizing the surrounding source markup. */
function sa_article_section_attr( $tag, $name ) {
	$pattern = '~\s' . preg_quote( $name, '~' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~iu';
	if ( ! preg_match( $pattern, $tag, $match ) ) {
		return '';
	}
	$value = isset( $match[3] ) ? $match[3] : ( isset( $match[2] ) && '' !== $match[2] ? $match[2] : $match[1] );
	return html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * Index tag ranges, preserving offsets (including Gutenberg comments).
 * Raw-text/code elements are atomic, so example headings are never removed.
 * No DOM extension or HTML reserialization is required.
 */
function sa_article_section_nodes( $html ) {
	$length = strlen( $html );
	$nodes  = array( array( 'tag' => '', 'start' => 0, 'open' => 0, 'close' => $length, 'end' => $length, 'parent' => -1, 'children' => array(), 'protected' => false, 'has_protected' => false, 'raw' => '' ) );
	$stack  = array( 0 );
	$tag_re = '<\/?[a-z][a-z0-9:-]*\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>';
	preg_match_all( '~<!--[\s\S]*?-->|<(script|style|pre|code|textarea)\b[^>]*>[\s\S]*?</\1\s*>|' . $tag_re . '~i', $html, $tokens, PREG_OFFSET_CAPTURE );
	foreach ( $tokens[0] as $token ) {
		$raw   = $token[0];
		$start = $token[1];
		if ( 0 === strpos( $raw, '<!--' ) || ! preg_match( '~^<(\/?)([a-z][a-z0-9:-]*)\b~i', $raw, $tag ) ) {
			continue;
		}
		$name = strtolower( $tag[2] );
		if ( '/' === $tag[1] ) {
			for ( $i = count( $stack ) - 1; $i > 0; --$i ) {
				if ( $nodes[ $stack[ $i ] ]['tag'] === $name ) {
					while ( count( $stack ) > $i ) {
						$id = array_pop( $stack );
						$nodes[ $id ]['close'] = $start;
						$nodes[ $id ]['end']   = $start + ( $nodes[ $id ]['tag'] === $name ? strlen( $raw ) : 0 );
					}
					break;
				}
			}
			continue;
		}
		$parent = end( $stack );
		$id     = count( $nodes );
		$class  = sa_article_section_attr( $raw, 'class' );
		$anchor = sa_article_section_attr( $raw, 'id' );
		$protected = $nodes[ $parent ]['protected']
			|| in_array( $anchor, array( 'county-identity', 'province-identity', 'attraction-identity' ), true )
			|| (bool) preg_match( '/(?:^|\s)(?:sa-cyber-id|sa-attraction-id)(?:\s|$)/', $class );
		$atomic = in_array( $name, array( 'script', 'style', 'pre', 'code', 'textarea', 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ), true ) || (bool) preg_match( '~/\s*>$~', $raw );
		$nodes[] = array( 'tag' => $name, 'start' => $start, 'open' => $start + strlen( $raw ), 'close' => $atomic ? $start + strlen( $raw ) : $length, 'end' => $atomic ? $start + strlen( $raw ) : $length, 'parent' => $parent, 'children' => array(), 'protected' => $protected, 'has_protected' => $protected, 'raw' => $raw );
		$nodes[ $parent ]['children'][] = $id;
		if ( ! $atomic ) {
			$stack[] = $id;
		}
	}
	for ( $i = count( $nodes ) - 1; $i > 0; --$i ) {
		if ( $nodes[ $i ]['has_protected'] ) {
			$nodes[ $nodes[ $i ]['parent'] ]['has_protected'] = true;
		}
	}
	return $nodes;
}

/** Cut only selected byte ranges from a source segment. */
function sa_article_section_cut( $html, $start, $end, $ranges ) {
	$out = '';
	$pos = $start;
	foreach ( $ranges as $range ) {
		if ( $range[1] <= $pos || $range[0] >= $end ) {
			continue;
		}
		if ( $range[0] > $pos ) {
			$out .= substr( $html, $pos, $range[0] - $pos );
		}
		$pos = max( $pos, min( $end, $range[1] ) );
	}
	return $out . substr( $html, $pos, $end - $pos );
}

/** Rebuild affected branches only; crossing wrapper tags stay balanced. */
function sa_article_section_render( $html, $nodes, $id, $ranges ) {
	$node = $nodes[ $id ];
	if ( $node['protected'] ) {
		return substr( $html, $node['start'], $node['end'] - $node['start'] );
	}
	$inner = '';
	$pos   = $node['open'];
	foreach ( $node['children'] as $child_id ) {
		$child = $nodes[ $child_id ];
		$inner .= sa_article_section_cut( $html, $pos, $child['start'], $ranges );
		$inner .= sa_article_section_render( $html, $nodes, $child_id, $ranges );
		$pos    = $child['end'];
	}
	$inner .= sa_article_section_cut( $html, $pos, $node['close'], $ranges );
	$changed = $inner !== substr( $html, $node['open'], $node['close'] - $node['open'] );
	$covered = false;
	foreach ( $ranges as $range ) {
		if ( $range[0] <= $node['start'] && $range[1] >= $node['end'] ) {
			$covered = true;
			break;
		}
	}
	// Never discard a protected descendant, even in a surrounding removed range.
	$has_protected = $node['has_protected'];
	if ( 0 !== $id && ! $has_protected && ( $covered || ( $changed && '' === trim( (string) preg_replace( '/<!--[\s\S]*?-->/', '', $inner ) ) ) ) ) {
		return '';
	}
	return substr( $html, $node['start'], $node['open'] - $node['start'] ) . $inner . substr( $html, $node['close'], $node['end'] - $node['close'] );
}

/** Resolve a local fragment, including an absolute/relative link to this article. */
function sa_article_section_fragment( $href ) {
	$parts = explode( '#', $href, 2 );
	if ( count( $parts ) < 2 ) {
		return '';
	}
	if ( '' !== $parts[0] ) {
		if ( ! function_exists( 'get_permalink' ) || ! function_exists( 'wp_parse_url' ) ) {
			return '';
		}
		$current = get_permalink();
		$target  = wp_parse_url( $parts[0] );
		$self    = wp_parse_url( $current );
		if ( ! is_array( $target ) || ! is_array( $self )
			|| ( isset( $target['host'] ) && ( ! isset( $self['host'] ) || strtolower( $target['host'] ) !== strtolower( $self['host'] ) ) )
			|| rtrim( isset( $target['path'] ) ? $target['path'] : '', '/' ) !== rtrim( isset( $self['path'] ) ? $self['path'] : '', '/' ) ) {
			return '';
		}
	}
	return rawurldecode( $parts[1] );
}

/** Remove the five sections, their descendants, and only newly dangling links. */
function sa_remove_retired_article_sections( $html ) {
	$html     = (string) $html;
	$nodes    = sa_article_section_nodes( $html );
	$ranges   = array();
	$removed  = array();
	$headings = array();
	foreach ( $nodes as $id => $node ) {
		if ( preg_match( '/^h[1-6]$/', $node['tag'] ) && ! $node['protected'] ) {
			$headings[ $id ] = $node;
		}
	}
	foreach ( $headings as $id => $heading ) {
		$label = substr( $html, $heading['open'], $heading['close'] - $heading['open'] );
		if ( ! sa_article_section_is_retired( $label ) ) {
			continue;
		}
		$end = strlen( $html );
		// A section/aside/nav is an explicit boundary; a heading-only div is not.
		for ( $p = $heading['parent']; $p > 0; $p = $nodes[ $p ]['parent'] ) {
			if ( in_array( $nodes[ $p ]['tag'], array( 'section', 'aside', 'nav', 'article' ), true ) ) {
				$end = $nodes[ $p ]['close'];
				break;
			}
		}
		foreach ( $headings as $next ) {
			if ( $next['start'] > $heading['start'] && $next['start'] < $end && $next['tag'] <= $heading['tag'] ) {
				$end = $next['start'];
				break;
			}
		}
		// The identity card always starts an independent, protected region.
		foreach ( $nodes as $node ) {
			if ( $node['protected'] && $node['start'] > $heading['start'] && $node['start'] < $end ) {
				$end = $node['start'];
			}
		}
		$ranges[] = array( $heading['start'], $end );
		if ( function_exists( 'sa_toc_heading_id' ) ) {
			$removed[ sa_toc_heading_id( sa_toc_label( $label ), array() ) ] = true;
		}
	}
	if ( empty( $ranges ) ) {
		return $html;
	}
	usort( $ranges, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	$out       = sa_article_section_render( $html, $nodes, 0, $ranges );
	$remaining = sa_article_section_nodes( $out );
	$kept_ids  = array();
	foreach ( $remaining as $node ) {
		$kept_ids[ sa_article_section_attr( $node['raw'], 'id' ) ] = true;
	}
	foreach ( $nodes as $node ) {
		$id = sa_article_section_attr( $node['raw'], 'id' );
		if ( '' !== $id && ! isset( $kept_ids[ $id ] ) ) {
			$removed[ $id ] = true;
		}
	}
	$link_ranges = array();
	foreach ( $remaining as $id => $node ) {
		if ( 'a' !== $node['tag'] || $node['protected'] ) {
			continue;
		}
		$href     = sa_article_section_attr( $node['raw'], 'href' );
		$fragment = sa_article_section_fragment( $href );
		if ( '' === $fragment || ! isset( $removed[ $fragment ] ) || isset( $kept_ids[ $fragment ] ) ) {
			continue;
		}
		$li = 0;
		$toc = false;
		for ( $p = $node['parent']; $p > 0; $p = $remaining[ $p ]['parent'] ) {
			if ( 'li' === $remaining[ $p ]['tag'] && ! $li ) {
				$li = $p;
			}
			if ( preg_match( '/(?:^|\s)(?:sa-toc|toc|table-of-contents)(?:\s|$)/', sa_article_section_attr( $remaining[ $p ]['raw'], 'class' ) ) ) {
				$toc = true;
			}
		}
		if ( $toc && $li ) {
			$link_ranges[] = array( $remaining[ $li ]['start'], $remaining[ $li ]['end'] );
		} else {
			// Keep the link's text/formatting, remove only the dead anchor tags.
			$link_ranges[] = array( $node['start'], $node['open'] );
			$link_ranges[] = array( $node['close'], $node['end'] );
		}
	}
	usort( $link_ranges, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
	$out = sa_article_section_cut( $out, 0, strlen( $out ), $link_ranges );
	// A stored/manual TOC must not retain an orphan heading or a stale count.
	$edits = array();
	foreach ( sa_article_section_nodes( $out ) as $node ) {
		if ( $node['protected'] || ! preg_match( '/(?:^|\s)sa-toc(?:\s|$)/', sa_article_section_attr( $node['raw'], 'class' ) ) ) {
			continue;
		}
		$box   = substr( $out, $node['start'], $node['end'] - $node['start'] );
		$count = preg_match_all( '/<a\b[^>]*\bhref\s*=/i', $box );
		if ( ! $count ) {
			$edits[] = array( $node['start'], $node['end'], '' );
		} elseif ( function_exists( 'sa_fa_digits' ) ) {
			$box = preg_replace_callback( '~(<span\b[^>]*class=["\'][^"\']*\bsa-toc__count\b[^"\']*["\'][^>]*>)[\s\S]*?(</span>)~i', function ( $match ) use ( $count ) { return $match[1] . sa_fa_digits( (string) $count ) . ' سرفصل' . $match[2]; }, $box );
			$edits[] = array( $node['start'], $node['end'], $box );
		}
	}
	usort( $edits, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
	foreach ( $edits as $edit ) {
		$out = substr_replace( $out, $edit[2], $edit[0], $edit[1] - $edit[0] );
	}
	return $out;
}

/** Apply to every article type, never to admin editing or unrelated pages. */
function sa_retired_article_sections_filter( $content ) {
	if ( is_admin() || ! is_singular() || 'page' === get_post_type() ) {
		return $content;
	}
	return sa_remove_retired_article_sections( $content );
}
// After do_blocks/wpautop and before sa_toc_filter (11) and citations (13).
add_filter( 'the_content', 'sa_retired_article_sections_filter', 10 );
