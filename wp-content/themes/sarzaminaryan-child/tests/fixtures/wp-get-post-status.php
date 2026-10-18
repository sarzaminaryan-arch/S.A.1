<?php
/**
 * Unmodified WordPress core function used ONLY by standalone regression tests.
 * Source: https://github.com/WordPress/WordPress/blob/97a9c802eccee214a5884e380446c5cd25d2dd77/wp-includes/post.php
 * Copyright: WordPress contributors. License: GPL-2.0-or-later.
 * This fixture is not loaded by the child-theme bootstrap.
 */
function get_post_status( $post = null ) {
	// Normalize the post object if necessary, skip normalization if called from get_sample_permalink().
	if ( ! $post instanceof WP_Post || ! isset( $post->filter ) || 'sample' !== $post->filter ) {
		$post = get_post( $post );
	}

	if ( ! is_object( $post ) ) {
		return false;
	}

	$post_status = $post->post_status;

	if (
		'attachment' === $post->post_type &&
		'inherit' === $post_status
	) {
		if (
			0 === $post->post_parent ||
			! get_post( $post->post_parent ) ||
			$post->ID === $post->post_parent
		) {
			// Unattached attachments with inherit status are assumed to be published.
			$post_status = 'publish';
		} elseif ( 'trash' === get_post_status( $post->post_parent ) ) {
			// Get parent status prior to trashing.
			$post_status = get_post_meta( $post->post_parent, '_wp_trash_meta_status', true );

			if ( ! $post_status ) {
				// Assume publish as above.
				$post_status = 'publish';
			}
		} else {
			$post_status = get_post_status( $post->post_parent );
		}
	} elseif (
		'attachment' === $post->post_type &&
		! in_array( $post_status, array( 'private', 'trash', 'auto-draft' ), true )
	) {
		/*
		 * Ensure uninherited attachments have a permitted status either 'private', 'trash', 'auto-draft'.
		 * This is to match the logic in wp_insert_post().
		 *
		 * Note: 'inherit' is excluded from this check as it is resolved to the parent post's
		 * status in the logic block above.
		 */
		$post_status = 'publish';
	}

	/**
	 * Filters the post status.
	 *
	 * @since 4.4.0
	 * @since 5.7.0 The attachment post type is now passed through this filter.
	 *
	 * @param string  $post_status The post status.
	 * @param WP_Post $post        The post object.
	 */
	return apply_filters( 'get_post_status', $post_status, $post );
}
