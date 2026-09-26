<?php
/**
 * Security hardening (reference 02): replaces the usual "security plugin" basics.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1. XML-RPC off (brute force / pingback DDoS surface) + no X-Pingback header.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);
add_filter( 'pings_open', '__return_false' );

// 2. Head cleanup: version, RSD, WLW, shortlink, extra feeds, oEmbed discovery.
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
add_filter( 'the_generator', '__return_empty_string' );

// 3. No file editing from wp-admin (defence in depth; wp-config may set it too).
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// 4. User enumeration: block ?author=N redirects and anonymous REST /users.
add_action(
	'template_redirect',
	function () {
		if ( is_author() && ! is_user_logged_in() && isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
);
add_filter(
	'rest_authentication_errors',
	function ( $result ) {
		return $result; // Public REST stays available (comments, oEmbed); only user routes are removed above.
	}
);

// 5. Generic login error (do not reveal whether the username exists).
add_filter(
	'login_errors',
	function () {
		return 'نام کاربری یا رمز عبور اشتباه است.';
	}
);

// 6. Security headers (front end). HSTS is left to the server (cPanel → SSL) on purpose.
add_action(
	'send_headers',
	function () {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()' );
	}
);

// 7. Comments: no HTML tags from visitors, links nofollow (core default), disable comment author URL field.
add_filter(
	'pre_comment_content',
	function ( $content ) {
		return wp_kses( $content, array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array() ) );
	}
);
add_filter(
	'comment_form_default_fields',
	function ( $fields ) {
		unset( $fields['url'] );
		return $fields;
	}
);

// 8. Uploads: block dangerous MIME types even for editors; sanitize filenames (Persian → ASCII).
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		unset( $mimes['exe'], $mimes['swf'], $mimes['htm|html'], $mimes['svg'] );
		return $mimes;
	}
);
add_filter(
	'sanitize_file_name',
	function ( $filename ) {
		$filename = remove_accents( $filename );
		$ext      = pathinfo( $filename, PATHINFO_EXTENSION );
		$name     = pathinfo( $filename, PATHINFO_FILENAME );
		$name     = preg_replace( '/[^a-zA-Z0-9\-_]+/', '-', $name );
		$name     = trim( $name, '-' );
		if ( '' === $name ) {
			$name = 'file-' . wp_generate_password( 6, false );
		}
		return strtolower( $name . ( $ext ? '.' . $ext : '' ) );
	}
);

// 9. Application passwords off unless explicitly needed (no external API clients planned).
add_filter( 'wp_is_application_passwords_available', '__return_false' );

// 10. Slow down brute force a little: 2s delay after a failed login.
add_action(
	'wp_login_failed',
	function () {
		sleep( 2 );
	}
);
