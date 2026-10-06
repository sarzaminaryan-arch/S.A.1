<?php
/**
 * Automatic internal links for published provinces, counties and attractions.
 *
 * Link the first plain-text mention of each published entity on a singular
 * front-end page. Existing markup is tokenized and preserved; text inside links
 * and non-prose elements is never rewritten.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a Persian entity name for lookup while keeping its visible spelling.
 *
 * @param string $name Entity name.
 * @return string
 */
function sa_internal_links_normalize_name( $name ) {
	$name = html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$name = strtr( $name, array( 'ك' => 'ک', 'ي' => 'ی', 'ى' => 'ی', 'ـ' => '' ) );
	$name = preg_replace( '/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $name );
	$name = preg_replace( '/[\s\p{Z}\x{200c}\x{200d}\x{002d}\x{2010}-\x{2015}]+/u', ' ', $name );
	$name = preg_replace( '/^[\p{Z}\s،؛:|]+|[\p{Z}\s،؛:|]+$/u', '', $name );
	return trim( (string) $name );
}

/**
 * Build a flexible regex for one normalized name.
 *
 * @param string $name Normalized entity name.
 * @return string
 */
function sa_internal_links_name_pattern( $name ) {
	$words = preg_split( '/ /u', $name, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $words ) || ! $words ) {
		return '';
	}

	$word_patterns = array();
	foreach ( $words as $word ) {
		$characters = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $characters ) || ! $characters ) {
			continue;
		}
		$character_patterns = array();
		foreach ( $characters as $character ) {
			switch ( $character ) {
				case 'ی':
					$character_patterns[] = '[یيى]\\p{M}*';
					break;
				case 'ک':
					$character_patterns[] = '[کك]\\p{M}*';
					break;
				default:
					$character_patterns[] = preg_quote( $character, '/' ) . '\\p{M}*';
			}
		}
		$word_patterns[] = implode( '', $character_patterns );
	}

	if ( ! $word_patterns ) {
		return '';
	}

	$separator = '[\s\p{Z}\x{200c}\x{200d}\x{002d}\x{2010}-\x{2015}]+';
	return implode( $separator, $word_patterns );
}

/**
 * Split aliases into bounded-size regexes to avoid PCRE pattern-size limits.
 *
 * @param string[] $aliases Normalized aliases.
 * @return string[]
 */
function sa_internal_links_regex_chunks( $aliases ) {
	$aliases = array_values( array_unique( array_filter( $aliases ) ) );
	usort(
		$aliases,
		function ( $left, $right ) {
			$length = strlen( $right ) - strlen( $left );
			return $length ? $length : strcmp( $left, $right );
		}
	);

	$boundary = '[\p{L}\p{M}\p{N}\x{200c}\x{200d}]';
	$chunks   = array();
	$current  = array();
	$bytes    = 0;
	$limit    = 10000;

	foreach ( $aliases as $alias ) {
		$pattern = sa_internal_links_name_pattern( $alias );
		if ( '' === $pattern ) {
			continue;
		}
		$pattern_bytes = strlen( $pattern );
		if ( $current && $bytes + $pattern_bytes + 1 > $limit ) {
			$chunks[] = '/(?<!' . $boundary . ')(?:' . implode( '|', $current ) . ')(?!' . $boundary . ')/u';
			$current  = array();
			$bytes    = 0;
		}
		$current[] = $pattern;
		$bytes    += $pattern_bytes + 1;
	}

	if ( $current ) {
		$chunks[] = '/(?<!' . $boundary . ')(?:' . implode( '|', $current ) . ')(?!' . $boundary . ')/u';
	}

	return $chunks;
}

/**
 * Shorten attraction titles/keywords to the proper name, not a location suffix.
 *
 * Many attraction titles contain explanatory text or the host county/province
 * (for example, «میدان نقش‌جهان اصفهان»). The focus keyword and these safe title
 * variants make the shorter name linkable as well.
 *
 * @param WP_Post $post          Attraction post.
 * @param string  $display_name Display title.
 * @param string[] $place_names Published province/county aliases, longest first.
 * @return string[] Normalized aliases.
 */
function sa_internal_links_attraction_aliases( $post, $display_name, $place_names ) {
	$sources = array( $display_name );
	$focus   = get_post_meta( $post->ID, 'sa_focus_keyword', true );
	if ( is_string( $focus ) && '' !== trim( $focus ) ) {
		$sources[] = $focus;
	}

	$aliases = array();
	foreach ( $sources as $source ) {
		$source = sa_internal_links_normalize_name( $source );
		if ( '' === $source ) {
			continue;
		}
		$variants = array( $source );

		// Parenthetical aliases and editorial subtitles are not part of the core name.
		$without_parenthetical = preg_replace( '/\s*(?:\([^()]*\)|（[^（）]*）)\s*$/u', '', $source );
		if ( $without_parenthetical && $without_parenthetical !== $source ) {
			$variants[] = sa_internal_links_normalize_name( $without_parenthetical );
		}
		$without_subtitle = preg_split( '/\s*[؛;:|–—]\s*/u', $source, 2 );
		if ( ! empty( $without_subtitle[0] ) && $without_subtitle[0] !== $source ) {
			$variants[] = sa_internal_links_normalize_name( $without_subtitle[0] );
		}

		foreach ( array_unique( $variants ) as $variant ) {
			if ( '' === $variant ) {
				continue;
			}
			$aliases[] = $variant;
			foreach ( $place_names as $place_name ) {
				$pattern = '/\s+' . preg_quote( $place_name, '/' ) . '$/u';
				if ( ! preg_match( $pattern, $variant ) ) {
					continue;
				}
				$short_name = trim( (string) preg_replace( $pattern, '', $variant ) );
				if ( '' !== $short_name && preg_match( '/\p{L}{4,}/u', $short_name ) ) {
					$aliases[] = $short_name;
				}
			}
		}
	}

	return array_values( array_unique( $aliases ) );
}

/**
 * Convert a permalink to a comparable local path key.
 *
 * @param string $url URL.
 * @return string
 */
function sa_internal_links_path_key( $url ) {
	$url   = html_entity_decode( trim( (string) $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return '';
	}
	if ( ! empty( $parts['scheme'] ) && ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return '';
	}

	if ( ! empty( $parts['host'] ) ) {
		$host      = strtolower( preg_replace( '/^www\./i', '', $parts['host'] ) );
		$home_host = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( $host !== $home_host ) {
			return '';
		}
	}

	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	foreach ( array( 'p', 'page_id' ) as $query_key ) {
		if ( ! empty( $query[ $query_key ] ) ) {
			return '?' . $query_key . '=' . absint( $query[ $query_key ] );
		}
	}

	$path = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '';
	if ( '' === $path ) {
		return '';
	}
	$path = preg_replace( '#/+#', '/', $path );
	return '/' . trim( $path, '/' ) . '/';
}

/**
 * Build/cache the dictionary of live province, county and attraction pages.
 *
 * Draft, private and scheduled posts are deliberately excluded so generated
 * links cannot point visitors to unpublished pages or 404s.
 *
 * @return array<string,mixed>
 */
function sa_internal_links_dictionary() {
	$cache_key = 'sa_internal_links_v1';
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['by_alias'], $cached['posts'], $cached['paths'], $cached['regexes'] ) ) {
		return $cached;
	}

	$dictionary = array(
		'by_alias' => array(),
		'posts'    => array(),
		'paths'    => array(),
		'regexes'  => array(),
	);
	$types = array_values( array_intersect( array( 'province', 'city', 'attraction' ), (array) sa_entity_types() ) );
	$types = array_values( array_filter( $types, 'post_type_exists' ) );
	if ( ! $types ) {
		return $dictionary;
	}

	$posts = get_posts(
		array(
			'post_type'              => $types,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'suppress_filters'       => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);

	$place_names = array();
	foreach ( $posts as $post ) {
		if ( ! in_array( $post->post_type, array( 'province', 'city' ), true ) ) {
			continue;
		}
		$place_name = sa_internal_links_normalize_name( sa_entity_display_name( $post, '' ) );
		if ( '' !== $place_name ) {
			$place_names[ $place_name ] = true;
		}
	}
	$place_names = array_keys( $place_names );
	usort(
		$place_names,
		function ( $left, $right ) {
			return strlen( $right ) - strlen( $left );
		}
	);

	foreach ( $posts as $post ) {
		$name = sa_entity_display_name( $post, '' );
		$url  = get_permalink( $post );
		if ( '' === sa_internal_links_normalize_name( $name ) || ! $url ) {
			continue;
		}

		$id   = (int) $post->ID;
		$type = (string) $post->post_type;
		$dictionary['posts'][ $id ] = array(
			'id'   => $id,
			'type' => $type,
			'name' => $name,
			'url'  => $url,
		);

		$aliases = 'attraction' === $type
			? sa_internal_links_attraction_aliases( $post, $name, $place_names )
			: array( sa_internal_links_normalize_name( $name ) );
		foreach ( $aliases as $key ) {
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $dictionary['by_alias'][ $key ] ) ) {
				$dictionary['by_alias'][ $key ] = array();
			}
			$dictionary['by_alias'][ $key ][] = $id;
		}

		$path = sa_internal_links_path_key( $url );
		if ( '' !== $path ) {
			$dictionary['paths'][ $path ] = $id;
		}
	}

	$dictionary['regexes'] = sa_internal_links_regex_chunks( array_keys( $dictionary['by_alias'] ) );
	set_transient( $cache_key, $dictionary, 12 * HOUR_IN_SECONDS );
	return $dictionary;
}

/**
 * Invalidate the entity dictionary after a relevant post changes.
 *
 * @param int $post_id Post ID.
 */
function sa_internal_links_invalidate_cache( $post_id ) {
	$post = get_post( $post_id );
	if ( $post && in_array( $post->post_type, array( 'province', 'city', 'attraction' ), true ) ) {
		delete_transient( 'sa_internal_links_v1' );
	}
}
add_action( 'save_post', 'sa_internal_links_invalidate_cache', 20, 1 );

/**
 * Invalidate after an entity is deleted (the post object may already be gone).
 *
 * @param int          $post_id Post ID.
 * @param WP_Post|null $post    Deleted post.
 */
function sa_internal_links_deleted_post( $post_id, $post = null ) {
	if ( $post && in_array( $post->post_type, array( 'province', 'city', 'attraction' ), true ) ) {
		delete_transient( 'sa_internal_links_v1' );
	}
}
add_action( 'deleted_post', 'sa_internal_links_deleted_post', 10, 2 );

/**
 * Invalidate when an entity becomes (un)published.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Previous status.
 * @param WP_Post $post       Post.
 */
function sa_internal_links_status_changed( $new_status, $old_status, $post ) {
	if ( $post && in_array( $post->post_type, array( 'province', 'city', 'attraction' ), true ) ) {
		delete_transient( 'sa_internal_links_v1' );
	}
}
add_action( 'transition_post_status', 'sa_internal_links_status_changed', 10, 3 );

/**
 * Split HTML into tags and text while preserving all original markup.
 *
 * @param string $html HTML.
 * @return string[]
 */
function sa_internal_links_html_parts( $html ) {
	if ( function_exists( 'wp_html_split' ) ) {
		return wp_html_split( $html );
	}
	$parts = preg_split( '/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	return is_array( $parts ) ? $parts : array( $html );
}

/**
 * Parse a tag token.
 *
 * @param string $token HTML token.
 * @return array<string,mixed>|null
 */
function sa_internal_links_tag_info( $token ) {
	if ( ! preg_match( '/^<\s*(\/?)\s*([a-z][a-z0-9:-]*)\b/i', $token, $matches ) ) {
		return null;
	}
	return array(
		'name'    => strtolower( $matches[2] ),
		'closing' => '/' === $matches[1],
		'self'    => (bool) preg_match( '/\/\s*>$/', $token ),
	);
}

/**
 * Elements whose text is code, metadata, or interactive UI rather than prose.
 *
 * @param string $tag Tag name.
 * @return bool
 */
function sa_internal_links_ignored_tag( $tag ) {
	return in_array( $tag, array( 'a', 'button', 'code', 'kbd', 'math', 'noscript', 'option', 'pre', 'samp', 'script', 'select', 'style', 'svg', 'template', 'textarea' ), true );
}

/**
 * Update the stack tracking text that must not be linked.
 *
 * @param string[]             $stack Current ignored-element stack.
 * @param array<string,mixed> $tag   Parsed tag.
 */
function sa_internal_links_update_stack( &$stack, $tag ) {
	if ( $tag['closing'] ) {
		for ( $index = count( $stack ) - 1; $index >= 0; --$index ) {
			if ( $stack[ $index ] === $tag['name'] ) {
				array_splice( $stack, $index, 1 );
				break;
			}
		}
	} elseif ( ! $tag['self'] && sa_internal_links_ignored_tag( $tag['name'] ) ) {
		$stack[] = $tag['name'];
	}
}

/**
 * Read an href from an anchor tag.
 *
 * @param string $tag HTML anchor tag.
 * @return string
 */
function sa_internal_links_href( $tag ) {
	if ( ! preg_match( '/(?:^|\s)href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $matches ) ) {
		return '';
	}
	foreach ( array( 1, 2, 3 ) as $index ) {
		if ( isset( $matches[ $index ] ) && '' !== $matches[ $index ] ) {
			return html_entity_decode( $matches[ $index ], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
	}
	return '';
}

/**
 * Resolve a same-site anchor to one of the dictionary's published entities.
 *
 * @param string              $url        Href.
 * @param array<string,mixed> $dictionary Entity dictionary.
 * @return int
 */
function sa_internal_links_anchor_target( $url, $dictionary ) {
	$path = sa_internal_links_path_key( $url );
	if ( '' !== $path && isset( $dictionary['paths'][ $path ] ) ) {
		return (int) $dictionary['paths'][ $path ];
	}

	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return 0;
	}
	if ( ! empty( $parts['host'] ) ) {
		$host      = strtolower( preg_replace( '/^www\./i', '', $parts['host'] ) );
		$home_host = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		if ( $host !== $home_host ) {
			return 0;
		}
	} else {
		$url = home_url( $url );
	}

	if ( function_exists( 'url_to_postid' ) ) {
		$id = (int) url_to_postid( $url );
		if ( $id && isset( $dictionary['posts'][ $id ] ) ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Find entity IDs already linked in the original content.
 *
 * Existing links always take precedence, including links that occur later in
 * the body, so the same entity is never linked twice after this filter runs.
 *
 * @param string              $content    Content HTML.
 * @param array<string,mixed> $dictionary Entity dictionary.
 * @return array<int,bool>
 */
function sa_internal_links_existing_ids( $content, $dictionary ) {
	$linked = array();
	$stack  = array();
	foreach ( sa_internal_links_html_parts( $content ) as $part ) {
		$tag = sa_internal_links_tag_info( $part );
		if ( ! $tag ) {
			continue;
		}
		if ( ! $stack && ! $tag['closing'] && 'a' === $tag['name'] ) {
			$target = sa_internal_links_anchor_target( sa_internal_links_href( $part ), $dictionary );
			if ( $target ) {
				$linked[ $target ] = true;
			}
		}
		sa_internal_links_update_stack( $stack, $tag );
	}
	return $linked;
}

/**
 * Detect a nearby administrative classifier to disambiguate same-name places.
 *
 * @param string $text   Text node.
 * @param int    $offset Byte offset at which the matched name begins.
 * @return string province|city|empty
 */
function sa_internal_links_context_hint( $text, $offset ) {
	$before    = substr( $text, 0, $offset );
	$separator = '[\s\p{Z}\x{200c}\x{200d}\x{002d}\x{2010}-\x{2015}]*';
	$boundary  = '(?:^|[^\p{L}\p{M}\p{N}\x{200c}\x{200d}])';
	if ( preg_match( '/' . $boundary . 'استان(?:\x{0650})?' . $separator . '$/u', $before ) ) {
		return 'province';
	}
	if ( preg_match( '/' . $boundary . '(?:شهرستان|شهر)(?:\x{0650})?' . $separator . '$/u', $before ) ) {
		return 'city';
	}
	return '';
}

/**
 * Pick an eligible target, honoring explicit qualifiers and self/duplicate rules.
 *
 * @param string              $alias      Normalized match.
 * @param string              $hint       Optional province/city qualifier.
 * @param int                 $current_id Current page ID.
 * @param array<int,bool>     $linked     Entity IDs already linked on this page.
 * @param array<string,mixed> $dictionary Entity dictionary.
 * @return int
 */
function sa_internal_links_choose_target( $alias, $hint, $current_id, &$linked, $dictionary ) {
	if ( empty( $dictionary['by_alias'][ $alias ] ) ) {
		return 0;
	}

	$candidates = array();
	foreach ( $dictionary['by_alias'][ $alias ] as $id ) {
		$id = (int) $id;
		if ( $id === (int) $current_id || isset( $linked[ $id ] ) || empty( $dictionary['posts'][ $id ] ) ) {
			continue;
		}
		$row = $dictionary['posts'][ $id ];
		if ( 'province' === $hint && 'province' !== $row['type'] ) {
			continue;
		}
		if ( 'city' === $hint && 'city' !== $row['type'] ) {
			continue;
		}
		$candidates[] = $id;
	}
	if ( ! $candidates ) {
		return 0;
	}

	// Bare names are most often a county or attraction; an explicit «استان» or
	// «شهرستان/شهر» above takes precedence where administrative names collide.
	$priority = array( 'attraction' => 0, 'city' => 1, 'province' => 2 );
	usort(
		$candidates,
		function ( $left, $right ) use ( $dictionary, $priority ) {
			$left_type  = $dictionary['posts'][ $left ]['type'];
			$right_type = $dictionary['posts'][ $right ]['type'];
			$left_rank  = isset( $priority[ $left_type ] ) ? $priority[ $left_type ] : 99;
			$right_rank = isset( $priority[ $right_type ] ) ? $priority[ $right_type ] : 99;
			return $left_rank === $right_rank ? $left - $right : $left_rank - $right_rank;
		}
	);

	return (int) $candidates[0];
}

/**
 * Link eligible names in one text node, leaving unmatched text byte-for-byte intact.
 *
 * @param string              $text        Text node.
 * @param int                 $current_id  Current page ID.
 * @param array<int,bool>     $linked      IDs already linked on this page.
 * @param array<string,mixed> $dictionary  Entity dictionary.
 * @return string
 */
function sa_internal_links_text_node( $text, $current_id, &$linked, $dictionary ) {
	if ( '' === $text || ! $dictionary['regexes'] ) {
		return $text;
	}

	$occurrences = array();
	foreach ( $dictionary['regexes'] as $regex ) {
		$count = @preg_match_all( $regex, $text, $matches, PREG_OFFSET_CAPTURE ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $count || 0 === $count ) {
			continue;
		}
		foreach ( $matches[0] as $match ) {
			$alias = sa_internal_links_normalize_name( $match[0] );
			if ( isset( $dictionary['by_alias'][ $alias ] ) ) {
				$occurrences[] = array(
					'alias'  => $alias,
					'text'   => $match[0],
					'offset' => (int) $match[1],
				);
			}
		}
	}
	if ( ! $occurrences ) {
		return $text;
	}

	usort(
		$occurrences,
		function ( $left, $right ) {
			if ( $left['offset'] !== $right['offset'] ) {
				return $left['offset'] - $right['offset'];
			}
			return strlen( $right['text'] ) - strlen( $left['text'] );
		}
	);

	$output = '';
	$cursor = 0;
	foreach ( $occurrences as $occurrence ) {
		$offset = $occurrence['offset'];
		$length = strlen( $occurrence['text'] );
		if ( $offset < $cursor ) {
			continue; // A longer entity name already consumed this overlapping span.
		}

		$output .= substr( $text, $cursor, $offset - $cursor );
		$hint    = sa_internal_links_context_hint( $text, $offset );
		$target  = sa_internal_links_choose_target( $occurrence['alias'], $hint, $current_id, $linked, $dictionary );
		if ( $target ) {
			$row      = $dictionary['posts'][ $target ];
			$type     = esc_attr( $row['type'] );
			$url      = esc_url( $row['url'] );
			$output  .= '<a class="sa-auto-link sa-auto-link--' . $type . '" href="' . $url . '">' . $occurrence['text'] . '</a>';
			$linked[ $target ] = true;
		} else {
			$output .= $occurrence['text'];
		}
		$cursor = $offset + $length;
	}

	return $output . substr( $text, $cursor );
}

/**
 * Apply automatic entity links to prose text nodes only.
 *
 * @param string $content Content HTML.
 * @return string
 */
function sa_internal_links_filter( $content ) {
	if ( is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() || ! is_string( $content ) || '' === $content ) {
		return $content;
	}

	$current_id = (int) get_queried_object_id();
	if ( ! $current_id ) {
		return $content;
	}
	$dictionary = sa_internal_links_dictionary();
	if ( ! $dictionary['by_alias'] || ! $dictionary['regexes'] ) {
		return $content;
	}

	$linked = sa_internal_links_existing_ids( $content, $dictionary );
	$stack  = array();
	$output = '';
	foreach ( sa_internal_links_html_parts( $content ) as $part ) {
		$tag = sa_internal_links_tag_info( $part );
		if ( $tag ) {
			$output .= $part;
			sa_internal_links_update_stack( $stack, $tag );
			continue;
		}
		$output .= $stack ? $part : sa_internal_links_text_node( $part, $current_id, $linked, $dictionary );
	}
	return $output;
}
add_filter( 'the_content', 'sa_internal_links_filter', 20 );
