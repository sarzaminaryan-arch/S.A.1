<?php
/** Final, unified source section. No database writes or automatic credibility claims. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function sa_article_sources_heading( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	$text = str_replace( array( "\xE2\x80\x8C", 'ي', 'ك' ), array( ' ', 'ی', 'ک' ), $text );
	$text = trim( preg_replace( '/\s+/u', ' ', $text ), " \t\r\n:" );
	return in_array( $text, array( 'منابع', 'منابع و مآخذ', 'منابع و مراجع', 'پانویس ها', 'پانویس', 'پانویس ها و ارجاع های متن', 'ارجاع های متن', 'References', 'Footnotes' ), true );
}

/**
 * Extract explicit source containers or peer-heading sections only. Copy all other
 * bytes, reference IDs, URLs, backlink IDs and protected identity markup unchanged.
 * Unknown/plugin-specific layouts are not guessed at.
 */
function sa_article_sources_extract( $html ) {
	if ( 1 !== preg_match( '//u', $html ) ) { return array( 'content' => $html, 'blocks' => array() ); }
	$nodes = sa_article_section_nodes( $html );
	$ranges = array();
	$blocks = array();
	foreach ( $nodes as $id => $node ) {
		if ( ! $id || $node['has_protected'] || in_array( $node['tag'], array( 'script', 'style', 'pre', 'code', 'textarea' ), true ) ) { continue; }
		$covered = false;
		foreach ( $ranges as $range ) {
			if ( $node['start'] >= $range[0] && $node['start'] < $range[1] ) { $covered = true; break; }
		}
		if ( $covered ) { continue; }
		$anchor = sa_article_section_attr( $node['raw'], 'id' );
		$class = sa_article_section_attr( $node['raw'], 'class' );
		$container = in_array( $node['tag'], array( 'section', 'div', 'aside', 'ol', 'ul' ), true )
			&& ( in_array( $anchor, array( 'sources', 'footnotes', 'references' ), true ) || preg_match( '/(?:^|\s)(?:sa-footnotes|sa-sources|footnotes|references|reflist|mw-references-wrap)(?:\s|$)/', $class ) );
		$heading = preg_match( '/^h[2-6]$/', $node['tag'] ) && sa_article_sources_heading( substr( $html, $node['open'], $node['close'] - $node['open'] ) );
		if ( ! $container && ! $heading ) { continue; }
		$start = $node['start'];
		$end = $node['end'];
		$inner_start = $node['open'];
		$inner_end = $node['close'];
		if ( $container && in_array( $node['tag'], array( 'ol', 'ul' ), true ) ) { $inner_start = $node['start']; $inner_end = $node['end']; }
		if ( $heading ) {
			$parent = $nodes[ $node['parent'] ];
			if ( ! in_array( $parent['tag'], array( '', 'div', 'section', 'article', 'aside' ), true ) ) { continue; }
			$end = $parent['close'];
			foreach ( $nodes as $next_id => $next ) {
				if ( $next['start'] <= $start || $next['start'] >= $end || ! preg_match( '/^h[1-6]$/', $next['tag'] ) || $next['tag'] > $node['tag'] ) { continue; }
				// Stop before the next peer's whole wrapper; never steal a gallery/FAQ
				// or copy unmatched wrapper closing tags into the bibliography.
				$boundary = $next_id;
				while ( $nodes[ $boundary ]['parent'] !== $node['parent'] && $nodes[ $boundary ]['parent'] > 0 ) { $boundary = $nodes[ $boundary ]['parent']; }
				if ( $nodes[ $boundary ]['parent'] === $node['parent'] && $nodes[ $boundary ]['start'] > $start ) { $end = $nodes[ $boundary ]['start']; break; }
			}
			$inner_start = $node['end'];
			$inner_end = $end;
			// If this heading owns its wrapper, move that wrapper too, not an empty shell.
			$prefix = substr( $html, $parent['open'], $start - $parent['open'] );
			if ( $node['parent'] && in_array( $parent['tag'], array( 'section', 'div', 'aside' ), true ) && '' === trim( preg_replace( '/<!--[\s\S]*?-->/', '', $prefix ) ) && $end === $parent['close'] ) {
				$start = $parent['start']; $end = $parent['end'];
			}
		}
		$safe = true;
		foreach ( $nodes as $child ) {
			if ( $child['protected'] && $child['start'] >= $start && $child['start'] < $end ) { $safe = false; break; }
		}
		if ( ! $safe ) { continue; }
		$inner = substr( $html, $inner_start, $inner_end - $inner_start );
		$aliases = array();
		foreach ( $nodes as $child ) {
			if ( $child['start'] < $start || $child['end'] > $end ) { continue; }
			$is_heading = preg_match( '/^h[1-6]$/', $child['tag'] ) && sa_article_sources_heading( substr( $html, $child['open'], $child['close'] - $child['open'] ) );
			$child_anchor = sa_article_section_attr( $child['raw'], 'id' );
			if ( ( $is_heading || $child['start'] < $inner_start || $child['end'] > $inner_end || ( 'sources' === $child_anchor && $child['start'] === $node['start'] ) ) && '' !== $child_anchor ) { $aliases[ $child_anchor ] = true; }
			if ( $is_heading && $child['start'] >= $inner_start && $child['end'] <= $inner_end ) {
				// Same-size padding keeps byte offsets stable for subsequent heading removals.
				$inner = substr_replace( $inner, str_repeat( ' ', $child['end'] - $child['start'] ), $child['start'] - $inner_start, $child['end'] - $child['start'] );
			}
		}
		if ( in_array( $node['tag'], array( 'ol', 'ul' ), true ) && 'sources' === $anchor ) {
			$inner = preg_replace( '~\s+id\s*=\s*(?:"sources"|\'sources\'|sources(?=\s|>))~i', '', $inner, 1 );
		}
		$alias_html = '';
		foreach ( array_keys( $aliases ) as $alias ) { $alias_html .= '<span id="' . esc_attr( $alias ) . '" aria-hidden="true"></span>'; }
		$blocks[] = array( 'html' => trim( $inner ), 'aliases' => $alias_html );
		$ranges[] = array( $start, $end );
	}
	return array( 'content' => sa_article_section_cut( $html, 0, strlen( $html ), $ranges ), 'blocks' => $blocks );
}

/** Called instead of the_content only by the two full-article templates. */
function sa_article_content_with_sources() {
	$post_id = (int) get_the_ID();
	ob_start();
	try { the_content(); $html = ob_get_contents(); } finally { ob_end_clean(); }
	$result = sa_article_sources_extract( $html );
	$GLOBALS['sa_article_source_blocks'][ $post_id ] = $result['blocks'];
	echo sa_article_sources_toc( $result['content'], $result['blocks'], sa_get_sources( $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- original filtered content.
}

/**
 * Keep cited evidence until a human verifies claim coverage. Never truncate to five,
 * invent a third source, or equate an official domain with claim verification.
 */
function sa_article_final_sources( $post_id ) {
	$blocks = isset( $GLOBALS['sa_article_source_blocks'][ $post_id ] ) ? $GLOBALS['sa_article_source_blocks'][ $post_id ] : array();
	$body = '';
	$aliases = '';
	$seen = array();
	foreach ( $blocks as $block ) {
		$aliases .= $block['aliases'];
		if ( '' !== trim( wp_strip_all_tags( $block['html'] ) ) || preg_match( '/<(?:img|audio|video)\b/i', $block['html'] ) ) {
			$body .= '<div class="sa-footnotes">' . $block['html'] . '</div>';
		} else { $aliases .= $block['html']; }
		foreach ( sa_article_section_nodes( $block['html'] ) as $node ) {
			if ( 'a' === $node['tag'] ) { $seen[ sa_article_section_attr( $node['raw'], 'href' ) ] = true; }
		}
	}
	$items = '';
	foreach ( sa_get_sources( $post_id ) as $source ) {
		$url = trim( $source['url'] );
		$title = trim( $source['title'] );
		$key = '' !== $url ? $url : 'title:' . $title;
		if ( isset( $seen[ $key ] ) || ( '' === $url && '' === $title ) ) { continue; }
		$seen[ $key ] = true;
		$label = '' !== $title ? $title : $url;
		if ( '' !== $url && '' !== esc_url( $url ) ) {
			$items .= '<li><a href="' . esc_url( $url ) . '" rel="' . esc_attr( sa_source_rel( $url ) ) . '" target="_blank">' . esc_html( $label ) . '</a></li>';
		} else { $items .= '<li>' . esc_html( $label ) . '</li>'; }
	}
	if ( '' === $body && '' === $items ) {
		echo $aliases; // Existing targets survive even when an empty heading is removed.
		return;
	}
	echo '<section class="sa-sources" id="sources" aria-labelledby="sa-final-sources-title"><h2 id="sa-final-sources-title">منابع</h2>';
	echo str_replace( array( '<span id="sources" aria-hidden="true"></span>', '<span id="sa-final-sources-title" aria-hidden="true"></span>' ), '', $aliases ) . $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- unchanged filtered article markup + escaped aliases.
	if ( '' !== $items ) { echo '<ul class="sa-sources__list">' . $items . '</ul>'; }
	echo '</section>';
}

/** Reconcile the theme TOC after moving headings; aliases remain for other links. */
function sa_article_sources_toc( $html, $blocks, $meta ) {
	$moved = array( 'sources' => true );
	$has_sources = false;
	foreach ( $blocks as $block ) {
		if ( '' !== trim( wp_strip_all_tags( $block['html'] ) ) || preg_match( '/<(?:img|audio|video)\b/i', $block['html'] ) ) { $has_sources = true; }
		foreach ( sa_article_section_nodes( $block['aliases'] ) as $node ) {
			$id = sa_article_section_attr( $node['raw'], 'id' );
			if ( '' !== $id ) { $moved[ $id ] = true; }
		}
	}
	foreach ( $meta as $source ) { if ( '' !== trim( $source['url'] . $source['title'] ) ) { $has_sources = true; } }
	$edits = array();
	foreach ( sa_article_section_nodes( $html ) as $nav ) {
		if ( $nav['has_protected'] || ! preg_match( '/(?:^|\s)sa-toc(?:\s|$)/', sa_article_section_attr( $nav['raw'], 'class' ) ) ) { continue; }
		$box = substr( $html, $nav['start'], $nav['end'] - $nav['start'] );
		$nodes = sa_article_section_nodes( $box ); $ranges = array();
		foreach ( $nodes as $node ) {
			if ( 'li' !== $node['tag'] ) { continue; }
			$found = false; $only_moved = true;
			foreach ( $nodes as $link ) {
				if ( 'a' !== $link['tag'] || $link['start'] < $node['open'] || $link['end'] > $node['close'] ) { continue; }
				$href = sa_article_section_attr( $link['raw'], 'href' );
				if ( '' !== $href && '#' === $href[0] && isset( $moved[ rawurldecode( substr( $href, 1 ) ) ] ) ) {
					$found = true;
				} else { $only_moved = false; }
			}
			if ( $found && $only_moved ) { $ranges[] = array( $node['start'], $node['end'] ); }
		}
		usort( $ranges, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
		$box = sa_article_section_cut( $box, 0, strlen( $box ), $ranges );
		if ( $has_sources ) {
			foreach ( sa_article_section_nodes( $box ) as $node ) {
				if ( in_array( $node['tag'], array( 'ol', 'ul' ), true ) && preg_match( '/(?:^|\s)sa-toc__list(?:\s|$)/', sa_article_section_attr( $node['raw'], 'class' ) ) ) {
					$item = '<li class="sa-toc__item sa-toc__item--l2"><a class="sa-toc__link" href="#sources"><span class="sa-toc__num" aria-hidden="true"></span><span class="sa-toc__label">منابع</span></a></li>';
					$box = substr_replace( $box, $item, $node['close'], 0 ); break;
				}
			}
		}
		$count = preg_match_all( '/<a\b[^>]*\bhref\s*=/i', $box );
		if ( ! $count ) { $box = ''; }
		else {
			$number = 0;
			$box = preg_replace_callback( '~(<span\b[^>]*class=["\'][^"\']*\bsa-toc__num\b[^"\']*["\'][^>]*>)[\s\S]*?(</span>)~i', function ( $match ) use ( &$number ) { return $match[1] . esc_html( sa_fa_digits( (string) ++$number ) ) . $match[2]; }, $box );
			$box = preg_replace_callback( '~(<span\b[^>]*class=["\'][^"\']*\bsa-toc__count\b[^"\']*["\'][^>]*>)[\s\S]*?(</span>)~i', function ( $match ) use ( $count ) { return $match[1] . esc_html( sa_fa_digits( (string) $count ) . ' سرفصل' ) . $match[2]; }, $box );
		}
		$edits[] = array( $nav['start'], $nav['end'], $box );
	}
	usort( $edits, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
	foreach ( $edits as $edit ) { $html = substr_replace( $html, $edit[2], $edit[0], $edit[1] - $edit[0] ); }
	return $html;
}
