<?php
/**
 * Upgrade explicitly trusted content links and harden links opening a new tab.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the explicit HTTPS migration allowlist.
 *
 * @return string[]
 */
function sa_https_link_host_allowlist() {
	return array(
		'www.iew.ir',
		'www.trjums.ac.ir',
		'lorestan.irib.ir',
		'wikipedia.org',
		'wikimedia.org',
	);
}

/**
 * Check whether an external host is explicitly trusted for HTTPS upgrade.
 *
 * @param string $host Hostname.
 * @return bool
 */
function sa_https_link_host_is_allowed( $host ) {
	$host      = strtolower( rtrim( (string) $host, '.' ) );
	$allowlist = sa_https_link_host_allowlist();
	foreach ( $allowlist as $allowed_host ) {
		if ( $host === $allowed_host || ( in_array( $allowed_host, array( 'wikipedia.org', 'wikimedia.org' ), true ) && substr( $host, -strlen( '.' . $allowed_host ) ) === '.' . $allowed_host ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Build an HTTPS URL without carrying an HTTP-only port or user credentials.
 *
 * @param array $parts Parsed URL components.
 * @return string Upgraded URL or empty string when unsafe to upgrade.
 */
function sa_https_upgrade_url( $parts ) {
	if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ( isset( $parts['port'] ) && 80 !== (int) $parts['port'] ) ) {
		return '';
	}
	$upgraded = 'https://' . $parts['host'] . ( isset( $parts['path'] ) ? $parts['path'] : '' );
	if ( isset( $parts['query'] ) ) {
		$upgraded .= '?' . $parts['query'];
	}
	if ( isset( $parts['fragment'] ) ) {
		$upgraded .= '#' . $parts['fragment'];
	}
	return $upgraded;
}

/**
 * Upgrade allowlisted HTTP anchors and add noopener to target="_blank" anchors.
 *
 * Wikipedia links are preserved; unapproved HTTP URLs are intentionally untouched.
 *
 * @param string $content Rendered post content.
 * @return string
 */
function sa_secure_content_links( $content ) {
	if ( is_admin() || false === strpos( $content, '<a' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return $content;
	}
	$processor = new WP_HTML_Tag_Processor( $content );
	while ( $processor->next_tag( 'A' ) ) {
		$href = $processor->get_attribute( 'href' );
		if ( is_string( $href ) && 0 === stripos( $href, 'http://' ) ) {
			$parts = wp_parse_url( $href );
			if ( is_array( $parts ) && ! empty( $parts['host'] ) && sa_https_link_host_is_allowed( $parts['host'] ) ) {
				$upgraded = sa_https_upgrade_url( $parts );
				if ( '' !== $upgraded ) {
					$processor->set_attribute( 'href', $upgraded );
				}
			}
		}

		$target = $processor->get_attribute( 'target' );
		if ( is_string( $target ) && '_blank' === strtolower( trim( $target ) ) ) {
			$rel    = $processor->get_attribute( 'rel' );
			$tokens = is_string( $rel ) ? preg_split( '/\s+/', trim( $rel ) ) : array();
			$tokens = array_filter( (array) $tokens );
			if ( ! in_array( 'noopener', array_map( 'strtolower', $tokens ), true ) ) {
				$tokens[] = 'noopener';
			}
			$processor->set_attribute( 'rel', implode( ' ', array_unique( $tokens ) ) );
		}
	}
	return $processor->get_updated_html();
}
add_filter( 'the_content', 'sa_secure_content_links', 20 );
