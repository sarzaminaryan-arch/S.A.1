<?php
/**
 * Validate legacy W_* citation references during content rendering.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repair only W_* hrefs whose exact, unique in-page id exists; unwrap all others.
 *
 * A W_* token is not an external URL. The citation processor intentionally accepts
 * only absolute HTTP(S) external URLs, so unresolved tokens must not be promoted to URLs.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function sa_filter_w_citation_links( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! is_singular() || false === stripos( $content, '<a' ) ) {
		return $content;
	}

	preg_match_all( '/\bid\s*=\s*(["\'])(W_[A-Za-z0-9_]+)\1/i', $content, $id_matches );
	$id_counts = ! empty( $id_matches[2] ) ? array_count_values( $id_matches[2] ) : array();

	$filtered = preg_replace_callback(
		'#<a\b([^>]*)\shref=(["\'])(W_[A-Za-z0-9_]+)\2([^>]*)>(.*?)</a\s*>#isu',
		function ( $matches ) use ( $id_counts ) {
			$token = html_entity_decode( $matches[3], ENT_QUOTES, 'UTF-8' );
			if ( isset( $id_counts[ $token ] ) && 1 === (int) $id_counts[ $token ] ) {
				return '<a' . $matches[1] . ' href="' . esc_url( '#' . $token ) . '"' . $matches[4] . '>' . $matches[5] . '</a>';
			}

			// No unique local destination: preserve visible citation text, not a dead anchor.
			return $matches[5];
		},
		$content
	);

	return is_string( $filtered ) ? $filtered : $content;
}
add_filter( 'the_content', 'sa_filter_w_citation_links', 14 );
