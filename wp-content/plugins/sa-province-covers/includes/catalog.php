<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Fixed human-reviewed map; no filename guessing or fuzzy city matching. */
function sa_pc_catalog() {
	static $catalog;
	if ( null === $catalog ) {
		$rows = json_decode( (string) file_get_contents( SA_PC_DIR . 'data/provinces.json' ), true );
		$catalog = array();
		foreach ( (array) $rows as $row ) { $catalog[ $row['slug'] ] = $row; }
	}
	return $catalog;
}

function sa_pc_normalize_name( $name ) {
	$name = str_replace( array( 'ي', 'ك', "\xE2\x80\x8C", "\xC2\xA0" ), array( 'ی', 'ک', ' ', ' ' ), trim( $name ) );
	$name = preg_replace( '/^استان\s+/u', '', $name );
	return preg_replace( '/\s+/u', ' ', $name );
}

/** Prefer a unique prior assignment, then exact canonical slug, then exact Persian name. */
function sa_pc_find_target( $entry, $posts ) {
	$managed = $slugs = $names = array();
	foreach ( $posts as $post ) {
		if ( 'province' !== $post->post_type ) { continue; }
		$journal = get_option( 'sa_pc_journal_' . (int) $post->ID, array() );
		if ( isset( $journal['slug'] ) && $entry['slug'] === $journal['slug'] ) { $managed[] = (int) $post->ID; }
		if ( $entry['slug'] === $post->post_name ) { $slugs[] = (int) $post->ID; }
		if ( sa_pc_normalize_name( $entry['name'] ) === sa_pc_normalize_name( $post->post_title ) ) { $names[] = (int) $post->ID; }
	}
	foreach ( array( $managed, $slugs, $names ) as $matches ) {
		if ( count( $matches ) > 1 ) { return 0; }
		if ( 1 === count( $matches ) ) { return $matches[0]; }
	}
	return 0;
}

/** Validate the release's local image, expected dimensions, MIME and checksum before writes. */
function sa_pc_asset( $slug ) {
	$catalog = sa_pc_catalog();
	if ( ! isset( $catalog[ $slug ] ) ) { return new WP_Error( 'unknown', 'استان معتبر نیست.' ); }
	$root = SA_PC_DIR . 'assets/images/';
	if ( ! is_readable( $root . 'manifest.json' ) ) { return new WP_Error( 'assets', 'بستهٔ تصاویر نصب نیست؛ ZIP کامل انتشار را نصب کنید.' ); }
	$manifest = json_decode( (string) file_get_contents( $root . 'manifest.json' ), true );
	$asset = isset( $manifest[ $slug ] ) ? $manifest[ $slug ] : array();
	$file = $root . $catalog[ $slug ]['file'];
	if ( ( $asset['file'] ?? '' ) !== $catalog[ $slug ]['file'] || ! is_readable( $file )
		|| ! preg_match( '/^[a-f0-9]{64}$/D', $asset['sha256'] ?? '' )
		|| ! hash_equals( $asset['sha256'], hash_file( 'sha256', $file ) )
		|| (int) ( $asset['bytes'] ?? 0 ) !== filesize( $file ) ) {
		return new WP_Error( 'integrity', 'فایل تصویر با مشخصات بسته مطابقت ندارد؛ جایگزینی انجام نشد.' );
	}
	$size = getimagesize( $file );
	if ( ! $size || IMAGETYPE_WEBP !== $size[2] || (int) $asset['width'] !== $size[0] || (int) $asset['height'] !== $size[1] ) {
		return new WP_Error( 'image', 'نوع یا ابعاد تصویر معتبر نیست.' );
	}
	return array_merge( $catalog[ $slug ], $asset, array( 'path' => $file ) );
}
