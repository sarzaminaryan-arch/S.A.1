<?php
/**
 * Comments, switched on and kept tidy (2026 design).
 *
 * Owner request (۲۹ آبان ۱۴۰۵): «بخش دیدگاه سایت فعال شود، با ظاهر و سبک جدید سایت و
 * جمع‌وجورتر». Two parts:
 *
 *  1. This module turns comments on for the content that carries visitor value —
 *     articles, attractions, counties and provinces — **without** touching every
 *     legacy row in the database. It is a filter, so it is reversible in one line.
 *  2. `comments.php` (child theme) renders the compact `.sa-comments` block.
 *
 * Moderation stays on: WordPress' default for a fresh post still decides whether the
 * first comment is held for review, and the team can always close a single page with
 * the `sa_comments_off` meta or the `sa_comments_enabled` filter.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that receive the comment block.
 *
 * @return array<int,string>
 */
function sa_comments_2026_types() {
	$types = array( 'post', 'attraction', 'city', 'province' );

	return (array) apply_filters( 'sa_comments_2026_types', $types );
}

/**
 * Master switch (option → filter), on by default.
 *
 * @return bool
 */
function sa_comments_2026_enabled() {
	$enabled = '0' !== (string) get_option( 'sa_comments_enabled', '1' );

	return (bool) apply_filters( 'sa_comments_enabled', $enabled );
}

/**
 * Should this post show and accept comments?
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function sa_comments_2026_allowed( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || ! sa_comments_2026_enabled() ) {
		return false;
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return false;
	}
	if ( ! in_array( (string) get_post_type( $post_id ), sa_comments_2026_types(), true ) ) {
		return false;
	}
	if ( '1' === (string) get_post_meta( $post_id, 'sa_comments_off', true ) ) {
		return false;
	}

	/**
	 * Per-post override for support and edge cases.
	 *
	 * @param bool $allowed Whether comments are allowed.
	 * @param int  $post_id Post ID.
	 */
	return (bool) apply_filters( 'sa_comments_2026_allowed', true, $post_id );
}

/**
 * Open comments on the allowed post types.
 *
 * Core keeps `comment_status = closed` on most of the archive's older rows; rather
 * than rewriting thousands of rows we answer the question at render time. The value
 * is memoised by core, so this costs nothing measurable.
 *
 * @param bool $open    Core answer.
 * @param int  $post_id Post ID.
 * @return bool
 */
function sa_comments_2026_open( $open, $post_id ) {
	if ( $open ) {
		return true;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $open;
	}
	if ( ! sa_comments_2026_enabled() ) {
		return false;
	}

	$post_id = (int) $post_id;
	$status  = get_post_status( $post_id );

	// Only *published* content re-opens; drafts and private rows keep core's answer.
	if ( 'publish' !== $status ) {
		return $open;
	}
	if ( '1' === (string) get_post_meta( $post_id, 'sa_comments_off', true ) ) {
		return false;
	}
	if ( ! in_array( (string) get_post_type( $post_id ), sa_comments_2026_types(), true ) ) {
		return $open;
	}

	return true;
}
add_filter( 'comments_open', 'sa_comments_2026_open', 10, 2 );

/**
 * Nothing in this design wants pingbacks or trackbacks.
 *
 * @param array<string,mixed> $data Comment data.
 * @return array<string,mixed>
 */
function sa_comments_2026_no_pings( $data ) {
	if ( isset( $data['comment_type'] ) && in_array( $data['comment_type'], array( 'pingback', 'trackback' ), true ) ) {
		$data['comment_approved'] = 0;
	}
	return $data;
}
add_filter( 'preprocess_comment', 'sa_comments_2026_no_pings' );

/**
 * Persian, neutral strings for the comment form instead of the theme's English ones.
 *
 * @param array<string,mixed> $defaults Core defaults.
 * @return array<string,mixed>
 */
function sa_comments_2026_form_defaults( $defaults ) {
	$defaults['title_reply_before'] = '<h3 id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']  = '</h3>';
	$defaults['cancel_reply_before'] = ' <small>';
	$defaults['cancel_reply_after']  = '</small>';

	return $defaults;
}
add_filter( 'comment_form_defaults', 'sa_comments_2026_form_defaults' );

/**
 * Hold visitor comments for review (the owner's editorial rule: nothing appears
 * before a human reads it). Team members with `moderate_comments` are unaffected.
 *
 * @param int|string $approved     Current approval state.
 * @param array      $commentdata  Comment data.
 * @return int|string
 */
function sa_comments_2026_moderate( $approved, $commentdata ) {
	if ( 1 !== (int) $approved ) {
		return $approved; // Spam, trash or already held.
	}
	if ( ! empty( $commentdata['user_id'] ) && user_can( (int) $commentdata['user_id'], 'moderate_comments' ) ) {
		return $approved;
	}
	$post_id = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
	if ( ! $post_id || ! in_array( (string) get_post_type( $post_id ), sa_comments_2026_types(), true ) ) {
		return $approved;
	}

	return 0;
}
add_filter( 'pre_comment_approved', 'sa_comments_2026_moderate', 10, 2 );

/**
 * Keep the field order compact: textarea first, then name and email.
 *
 * @param array<string,string> $fields Comment fields.
 * @return array<string,string>
 */
function sa_comments_2026_field_order( $fields ) {
	unset( $fields['url'], $fields['cookies'] );

	return $fields;
}
add_filter( 'comment_form_default_fields', 'sa_comments_2026_field_order', 20 );

/**
 * Show the comment block on the 2026 templates.
 *
 * A guard keeps it from printing twice if a template already called it.
 *
 * @param int|WP_Post|null $post Post or ID.
 * @return void
 */
function sa_comments_2026_render( $post = null ) {
	static $done = false;
	if ( $done ) {
		return;
	}

	$post    = get_post( $post );
	$post_id = $post ? (int) $post->ID : (int) get_the_ID();
	if ( ! $post_id ) {
		return;
	}
	if ( ! sa_comments_2026_allowed( $post_id ) ) {
		return;
	}

	$done = true;
	comments_template( '', true );
}
