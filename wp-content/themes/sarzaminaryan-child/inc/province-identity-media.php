<?php
/** Named media inside the province identity card. Read-only Alborz/Tehran pilot. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical lowercase filenames are independent of attachment titles. */
function sa_province_identity_media_filename( $slug ) {
	$catalog = sa_province_profile_catalog();
	return is_string( $slug ) && isset( $catalog['by_slug'][ $slug ] ) && preg_match( '/^[a-z]+(?:-[a-z]+)*$/D', $slug )
		? $slug . '.webp' : '';
}

/** Only the two requested published, non-password province articles participate. */
function sa_province_identity_media_enabled( $post_id ) {
	return ! is_admin() && ! is_feed() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		&& is_singular( 'province' ) && (int) get_queried_object_id() === (int) $post_id
		&& 'province' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) && ! post_password_required( $post_id )
		&& in_array( (string) get_post_field( 'post_name', $post_id ), array( 'alborz', 'tehran' ), true );
}

/**
 * Resolve the exact original basename in Media, not a guessed URL or title.
 * Newest valid upload wins when the same filename exists in multiple months.
 * No persistent miss cache: the next request sees new uploads/deletions.
 */
function sa_province_identity_media_attachment( $post_id ) {
	if ( ! sa_province_identity_media_enabled( $post_id ) ) { return 0; }
	$name = sa_province_identity_media_filename( (string) get_post_field( 'post_name', $post_id ) );
	if ( '' === $name ) { return 0; }
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT p.ID, p.post_date FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
		WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_password = '' AND p.post_mime_type = 'image/webp'
		AND pm.meta_key = '_wp_attached_file' AND (pm.meta_value = %s OR pm.meta_value LIKE %s)
		ORDER BY p.post_date DESC, p.ID DESC LIMIT 20",
		$name, '%/' . $wpdb->esc_like( $name )
	) );
	foreach ( (array) $ids as $id ) {
		$id = (int) $id;
		// get_post_status() resolves inherit to publish/parent status for attachments.
		// Validate the stored field instead; keep the separate parent privacy checks.
		if ( 'attachment' !== get_post_type( $id ) || 'inherit' !== get_post_field( 'post_status', $id, 'raw' ) || post_password_required( $id ) || 'image/webp' !== get_post_mime_type( $id ) ) { continue; }
		$parent = (int) wp_get_post_parent_id( $id );
		if ( $parent && ( 'publish' !== get_post_status( $parent ) || post_password_required( $parent ) ) ) { continue; }
		$relative = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( wp_basename( $relative ) !== $name ) { continue; }
		$file = get_attached_file( $id );
		if ( ! is_string( $file ) || ! is_file( $file ) || ! is_readable( $file ) || ! wp_get_attachment_url( $id ) ) { continue; }
		return $id;
	}
	return 0;
}

/** A reserved, silent 2:1 slot; no broken img, warning, or fabricated fallback. */
function sa_province_identity_media_html( $post_id ) {
	if ( ! sa_province_identity_media_enabled( $post_id ) ) { return ''; }
	$id = sa_province_identity_media_attachment( $post_id );
	$image = '';
	if ( $id ) {
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) { $alt = 'تصویر ' . sa_province_name_for_post( $post_id, true ); }
		$image = wp_get_attachment_image( $id, 'full', false, array(
			'class' => 'sa-province-identity-media__image', 'alt' => $alt,
			'loading' => 'eager', 'decoding' => 'async',
			'sizes' => '(max-width: 600px) calc(100vw - 64px), 320px',
		) );
	}
	return '<div class="sa-province-identity-media"' . ( $image ? '' : ' aria-hidden="true"' ) . '>' . $image . '</div>';
}

/**
 * Compatibility with installed sa-province-covers 1.0.0: move its display out
 * of the article, not its attachment or social metadata. Runs after its priority
 * 9 content callback and before wpautop. Never removes ordinary article images.
 */
function sa_province_identity_remove_legacy_cover( $content ) {
	if ( ! is_singular( 'province' ) || ! in_the_loop() || ! is_main_query()
		|| (int) get_the_ID() !== (int) get_queried_object_id() || ! sa_province_identity_media_enabled( get_the_ID() ) ) { return $content; }
	return preg_replace( '~\A\s*<figure class="sa-province-cover" data-sa-province-cover="[0-9]+">[\s\S]*?</figure>\s*~', '', $content, 1 );
}
add_filter( 'the_content', 'sa_province_identity_remove_legacy_cover', 9 );
