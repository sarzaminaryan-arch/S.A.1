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
/**
 * Render a white diagram card for «نمای برتر» identity/archive visuals.
 *
 * This replaces the old large featured-image presentation for attractions: the
 * card is generated from structured data, keeps a clean white background, and
 * prints the Persian place/city/province labels inside the visual area.
 *
 * @param int    $post_id Attraction post ID.
 * @param string $context identity|card.
 * @return string
 */
function sa_attraction_diagram_markup( $post_id = 0, $context = 'identity' ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	if ( ! $post_id || 'attraction' !== get_post_type( $post_id ) ) {
		return '';
	}

	$title    = get_the_title( $post_id );
	$city     = function_exists( 'sa_get_parent' ) ? sa_get_parent( $post_id, 'city' ) : null;
	$province = function_exists( 'sa_get_parent' ) ? sa_get_parent( $post_id, 'province' ) : null;
	$city_txt = $city ? 'شهرستان ' . get_the_title( $city ) : '';
	$prov_txt = $province ? 'استان ' . get_the_title( $province ) : '';
	$terms    = get_the_terms( $post_id, 'attraction_type' );
	$type_txt     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'نمای برتر';
	$is_waterfall = false !== mb_strpos( $title . ' ' . $type_txt, 'آبشار' );
	$classes      = 'sa-attraction-diagram sa-attraction-diagram--' . sanitize_html_class( $context ) . ( $is_waterfall ? ' sa-attraction-diagram--waterfall' : ' sa-attraction-diagram--landscape' );
	$aria     = trim( 'دیاگرام ' . $title . ' ' . $city_txt . ' ' . $prov_txt );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
		<div class="sa-attraction-diagram__art" aria-hidden="true">
			<svg viewBox="0 0 220 132" focusable="false">
				<rect x="0" y="0" width="220" height="132" rx="18" class="sa-attraction-diagram__sky" />
				<path d="M23 102 C45 78 58 61 78 50 C94 41 111 43 128 27 C145 13 165 18 191 7 L211 7 L211 124 L23 124 Z" class="sa-attraction-diagram__mountain" />
				<path d="M48 106 C66 84 82 78 98 58 C111 43 125 38 137 22 C151 37 161 51 181 61 C190 66 198 78 204 96 L204 124 L48 124 Z" class="sa-attraction-diagram__ridge" />
				<path d="M128 25 C121 43 121 55 113 70 C106 84 96 93 91 119" class="sa-attraction-diagram__water sa-attraction-diagram__water--main" />
				<path d="M151 39 C145 54 147 65 139 78 C132 90 130 103 126 119" class="sa-attraction-diagram__water" />
				<path d="M105 54 C99 67 101 75 94 87 C88 98 79 106 74 121" class="sa-attraction-diagram__water" />
				<path d="M176 66 C168 78 166 91 158 103 C153 111 151 116 149 122" class="sa-attraction-diagram__water sa-attraction-diagram__water--thin" />
				<path d="M68 77 C60 87 58 96 52 106 C48 113 43 118 39 123" class="sa-attraction-diagram__water sa-attraction-diagram__water--thin" />
				<ellipse cx="112" cy="120" rx="73" ry="8" class="sa-attraction-diagram__pool" />
				<path d="M27 104 C44 98 58 99 74 104 C95 112 113 111 134 105 C160 98 178 101 198 109" class="sa-attraction-diagram__contour" />
				<circle cx="40" cy="38" r="10" class="sa-attraction-diagram__leaf" />
				<circle cx="55" cy="31" r="8" class="sa-attraction-diagram__leaf" />
				<circle cx="69" cy="39" r="9" class="sa-attraction-diagram__leaf" />
				<circle cx="185" cy="35" r="8" class="sa-attraction-diagram__leaf" />
				<circle cx="199" cy="43" r="9" class="sa-attraction-diagram__leaf" />
			</svg>
		</div>
		<div class="sa-attraction-diagram__copy">
			<span class="sa-attraction-diagram__eyebrow"><?php echo esc_html( $type_txt ); ?></span>
			<strong><?php echo esc_html( $title ); ?></strong>
			<?php if ( $city_txt || $prov_txt ) : ?>
				<span><?php echo esc_html( trim( $city_txt . ( $city_txt && $prov_txt ? '، ' : '' ) . $prov_txt ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return trim( ob_get_clean() );
}
