<?php
/**
 * Small shared helpers (entity lookups, digits, numbers, lists).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Active entity CPT slugs (reserved ones excluded unless enabled).
 *
 * @return string[]
 */
function sa_entity_types() {
	$types = array();
	foreach ( sa_entities_config() as $slug => $entity ) {
		if ( 'active' === $entity['status'] || ( 'reserved' === $entity['status'] && SA_ENABLE_ACCOMMODATION ) ) {
			$types[] = $slug;
		}
	}
	return $types;
}

/**
 * Config for one entity or null.
 *
 * @param string $type CPT slug.
 * @return array|null
 */
function sa_entity( $type ) {
	$config = sa_entities_config();
	return isset( $config[ $type ] ) && in_array( $type, sa_entity_types(), true ) ? $config[ $type ] : null;
}

/**
 * Is this post type one of our entities?
 *
 * @param string|int|WP_Post|null $post_or_type Post type slug or post.
 * @return bool
 */
function sa_is_entity( $post_or_type = null ) {
	$type = is_string( $post_or_type ) ? $post_or_type : get_post_type( $post_or_type );
	return $type && in_array( $type, sa_entity_types(), true );
}

/**
 * Persian singular/plural label for a CPT.
 *
 * @param string $type   CPT slug.
 * @param bool   $plural Plural?
 * @return string
 */
function sa_entity_label( $type, $plural = false ) {
	$config = sa_entities_config();
	if ( isset( $config[ $type ] ) ) {
		return $plural ? $config[ $type ]['plural'] : $config[ $type ]['singular'];
	}
	$obj = get_post_type_object( $type );
	return $obj ? ( $plural ? $obj->labels->name : $obj->labels->singular_name ) : $type;
}

/**
 * Convert ASCII digits (and Arabic-Indic) to Persian digits.
 *
 * @param string|int|float $value Value.
 * @return string
 */
function sa_fa_digits( $value ) {
	$value = (string) $value;
	$value = str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), $value );
	return str_replace( array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), $value );
}

/**
 * Convert Persian/Arabic digits to ASCII (for saving numbers).
 *
 * @param string $value Value.
 * @return string
 */
function sa_en_digits( $value ) {
	$value = str_replace( array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), range( 0, 9 ), (string) $value );
	return str_replace( array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٫', '٬' ), array_merge( range( 0, 9 ), array( '.', '' ) ), $value );
}

/**
 * Format a number for display (thousand separators + Persian digits when enabled).
 *
 * @param int|float|string $number   Number.
 * @param int              $decimals Decimals.
 * @return string
 */
function sa_number( $number, $decimals = 0 ) {
	if ( '' === $number || null === $number ) {
		return '';
	}
	$float = (float) $number;
	if ( $decimals > 0 && floor( $float ) === $float ) {
		$decimals = 0; // 107018.0 → 107,018
	}
	$formatted = number_format( $float, $decimals, '.', ',' );
	if ( ! sa_digits_enabled() ) {
		return $formatted;
	}
	return sa_fa_digits( str_replace( array( ',', '.' ), array( '٬', '٫' ), $formatted ) );
}

/**
 * Persian digits enabled in Customizer?
 *
 * @return bool
 */
function sa_digits_enabled() {
	return (bool) get_theme_mod( 'sa_fa_digits', true );
}

/**
 * Apply Persian digits to arbitrary display text if enabled.
 *
 * @param string $text Text.
 * @return string
 */
function sa_digits( $text ) {
	return sa_digits_enabled() ? sa_fa_digits( $text ) : (string) $text;
}

/**
 * Split a "one item per line" list meta into an array.
 *
 * @param string $raw Raw meta.
 * @return string[]
 */
function sa_list( $raw ) {
	$items = preg_split( '/\r\n|\r|\n|،|,/u', (string) $raw );
	$items = array_map( 'trim', (array) $items );
	return array_values( array_filter( $items, 'strlen' ) );
}

/**
 * Read an entity meta value with the sa_ prefix applied automatically.
 *
 * @param int    $post_id Post ID.
 * @param string $field   Field name (without prefix) or full key starting with sa_.
 * @return mixed
 */
function sa_meta( $post_id, $field ) {
	$key = 0 === strpos( $field, 'sa_' ) ? $field : 'sa_' . $field;
	return get_post_meta( $post_id, $key, true );
}

/**
 * Multi-value meta (stored as multiple rows) → int[].
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return int[]
 */
function sa_meta_ids( $post_id, $key ) {
	$ids = get_post_meta( $post_id, $key, false );
	$ids = array_map( 'absint', (array) $ids );
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Decode the FAQ meta (JSON list of {q,a}).
 *
 * @param int $post_id Post ID.
 * @return array[]
 */
function sa_get_faq( $post_id ) {
	$raw = get_post_meta( $post_id, 'sa_faq', true );
	$faq = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
	if ( ! is_array( $faq ) ) {
		return array();
	}
	$clean = array();
	foreach ( $faq as $row ) {
		if ( ! empty( $row['q'] ) && ! empty( $row['a'] ) ) {
			$clean[] = array(
				'q' => (string) $row['q'],
				'a' => (string) $row['a'],
			);
		}
	}
	return $clean;
}

/**
 * Site-wide archive URL for an entity type.
 *
 * @param string $type CPT slug.
 * @return string
 */
function sa_archive_url( $type ) {
	$url = get_post_type_archive_link( $type );
	return $url ? $url : home_url( '/' );
}

/**
 * Coordinates for a post (works for province/city/attraction/accommodation field naming).
 *
 * @param int $post_id Post ID.
 * @return array|null [lat, lng]
 */
function sa_get_coords( $post_id ) {
	$type = get_post_type( $post_id );
	$candidates = array(
		array( 'sa_latitude', 'sa_longitude' ),
		array( 'sa_' . $type . '_latitude', 'sa_' . $type . '_longitude' ),
	);
	foreach ( $candidates as $pair ) {
		$lat = get_post_meta( $post_id, $pair[0], true );
		$lng = get_post_meta( $post_id, $pair[1], true );
		if ( '' !== $lat && '' !== $lng && is_numeric( $lat ) && is_numeric( $lng ) ) {
			return array( (float) $lat, (float) $lng );
		}
	}
	return null;
}

/**
 * Get the excerpt/summary for an entity safely (no auto-generated "[…]" noise).
 *
 * @param int|WP_Post $post  Post.
 * @param int         $words Word count.
 * @return string
 */
function sa_summary( $post, $words = 30 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$text = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	return wp_trim_words( $text, $words, '…' );
}
