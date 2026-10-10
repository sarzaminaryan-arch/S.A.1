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
 * Detect which colored icon should be used in the compact «دیدنی» card.
 *
 * @param int    $post_id Attraction post ID.
 * @param string $title   Attraction title.
 * @param string $type    Primary attraction type label.
 * @return string
 */
function sa_attraction_icon_kind( $post_id, $title, $type = '' ) {
	$haystack = $title . ' ' . $type . ' ' . get_post_meta( $post_id, 'sa_attraction_area', true );
	if ( false !== mb_strpos( $haystack, 'آبشار' ) ) {
		return 'waterfall';
	}
	if ( preg_match( '/(دریاچه|تالاب|سراب|چشمه)/u', $haystack ) ) {
		return 'lake';
	}
	if ( false !== mb_strpos( $haystack, 'غار' ) ) {
		return 'cave';
	}
	if ( preg_match( '/(روستا|دهستان|ییلاق)/u', $haystack ) ) {
		return 'village';
	}
	if ( preg_match( '/(پارک|بوستان|تفرجگاه|باغ)/u', $haystack ) ) {
		return 'park';
	}
	if ( preg_match( '/(کوه|دره|تنگه|اشترانکوه|گردنه|قله)/u', $haystack ) ) {
		return 'mountain';
	}
	return 'landscape';
}

/**
 * Colored corner icons for generated «دیدنی» cards.
 *
 * @param string $kind waterfall|lake|mountain|park|cave|village|landscape.
 * @return string SVG markup.
 */
function sa_attraction_diagram_icon_svg( $kind ) {
	$icons = array(
		'waterfall' => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#e8f8ff"/><path d="M33 151 C55 120 62 91 88 76 C112 62 125 70 143 48 C160 72 176 85 190 112 C199 129 203 143 204 168 Z" fill="#4a5b64"/><path d="M61 151 C76 114 91 87 110 72 C122 88 134 104 151 121 C164 134 174 150 181 169 Z" fill="#7a858a"/><path d="M92 70 L128 70 L128 154 C128 176 91 176 91 154 Z" fill="#ffffff"/><path d="M100 75 L122 75 L122 151 C122 166 100 166 100 151 Z" fill="#43c7f1"/><ellipse cx="111" cy="169" rx="70" ry="22" fill="#079bd2"/><path d="M43 163 C69 151 90 177 119 162 C148 147 165 174 192 160" fill="none" stroke="#b8f4ff" stroke-width="7" stroke-linecap="round"/><circle cx="42" cy="78" r="16" fill="#60bd25"/><circle cx="61" cy="62" r="18" fill="#4faf1f"/><circle cx="179" cy="80" r="18" fill="#57b926"/><circle cx="160" cy="65" r="16" fill="#4fad21"/></svg>
SVG,
		'lake'      => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#54c9f3"/><circle cx="58" cy="57" r="17" fill="#fff"/><circle cx="78" cy="53" r="13" fill="#fff"/><rect x="48" y="58" width="54" height="13" fill="#fff"/><circle cx="158" cy="63" r="14" fill="#fff"/><circle cx="178" cy="59" r="12" fill="#fff"/><rect x="149" y="64" width="43" height="11" fill="#fff"/><path d="M18 134 L78 55 L139 134 Z" fill="#2b8bc7"/><path d="M71 134 L136 38 L205 134 Z" fill="#1c6da8"/><path d="M78 55 L100 91 L69 86 Z M136 38 L162 86 L127 79 Z" fill="#fff"/><path d="M4 151 L61 101 L116 150 Z M107 151 L164 101 L218 151 Z" fill="#43a844"/><path d="M38 142 L52 111 L67 142 Z M55 143 L72 106 L90 143 Z M173 143 L189 110 L205 143 Z" fill="#236f38"/><ellipse cx="110" cy="154" rx="89" ry="36" fill="#0ca6d8"/><path d="M30 151 C64 140 86 164 118 151 C149 139 167 160 194 151 L194 177 C151 190 77 190 30 177 Z" fill="#7de0ee"/><path d="M48 157 C77 150 93 164 121 157 C149 150 164 162 187 157" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round"/></svg>
SVG,
		'mountain'  => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#7ed8ff"/><path d="M8 164 L76 70 L137 164 Z" fill="#2f8ec7"/><path d="M70 164 L137 42 L216 164 Z" fill="#1e70ad"/><path d="M76 70 L97 101 L65 95 Z M137 42 L165 92 L129 83 Z" fill="#fff"/><path d="M0 180 C46 143 80 149 116 176 C151 203 186 194 220 166 L220 220 L0 220 Z" fill="#52b843"/><path d="M0 193 C49 162 85 169 116 190 C151 213 187 203 220 179 L220 220 L0 220 Z" fill="#2f923b"/><path d="M88 170 C108 178 114 192 139 195 C162 198 179 206 199 214" fill="none" stroke="#38bfe6" stroke-width="10" stroke-linecap="round"/><path d="M36 178 L50 146 L66 178 Z M54 178 L72 137 L91 178 Z M21 184 L34 158 L47 184 Z" fill="#126b34"/></svg>
SVG,
		'park'      => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#dff8ff"/><path d="M23 160 C53 133 89 120 126 122 C158 124 184 137 207 158 L207 220 L23 220 Z" fill="#b9ef9b"/><rect x="48" y="118" width="14" height="52" rx="7" fill="#8a5a2b"/><circle cx="50" cy="103" r="25" fill="#5aba32"/><circle cx="75" cy="94" r="22" fill="#6ac33b"/><circle cx="81" cy="121" r="24" fill="#47a82d"/><circle cx="43" cy="128" r="21" fill="#49ac2d"/><path d="M92 142 H166 A10 10 0 0 1 176 152 V160 H82 V152 A10 10 0 0 1 92 142 Z" fill="#d8893b"/><rect x="92" y="134" width="76" height="13" rx="6" fill="#f0a74b"/><path d="M97 160 L88 187 M164 160 L174 187" stroke="#4b2b14" stroke-width="6" stroke-linecap="round"/><path d="M147 79 H159 V172 H147 Z" fill="#1d4d62"/><path d="M141 78 C148 53 161 53 168 78 Z" fill="#173763"/><rect x="135" y="78" width="39" height="9" rx="4" fill="#173763"/><path d="M28 190 C72 174 113 174 162 190" fill="none" stroke="#f6dfbb" stroke-width="16" stroke-linecap="round"/></svg>
SVG,
		'cave'      => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#fff3e6"/><path d="M31 177 C37 111 64 61 105 44 C145 27 181 62 192 131 C196 153 190 171 178 187 L42 187 C35 184 32 181 31 177 Z" fill="#8a8178"/><path d="M59 178 C62 124 81 86 111 76 C142 85 158 124 161 178 Z" fill="#1b1c21"/><path d="M82 77 L95 126 L106 78 L122 132 L137 80 L145 126 L154 92 C141 76 126 68 111 66 C99 67 89 71 82 77 Z" fill="#d6c8b7"/><ellipse cx="110" cy="184" rx="82" ry="17" fill="#42b7d0"/><path d="M43 151 C62 138 80 143 92 158 M166 151 C150 141 135 145 126 158" fill="none" stroke="#b9e06f" stroke-width="12" stroke-linecap="round"/><circle cx="52" cy="145" r="12" fill="#5fb63a"/><circle cx="168" cy="145" r="14" fill="#60b93e"/></svg>
SVG,
		'village'   => <<<'SVG'
<svg viewBox="0 0 220 220" focusable="false" aria-hidden="true"><circle cx="110" cy="110" r="98" fill="#c9f1ff"/><path d="M0 160 C41 126 82 119 118 142 C152 164 187 146 220 118 L220 220 L0 220 Z" fill="#6cc24a"/><path d="M0 179 C55 153 96 154 140 181 C169 198 192 196 220 184 L220 220 L0 220 Z" fill="#3b9f39"/><g><rect x="36" y="126" width="48" height="42" rx="4" fill="#fff4d8"/><path d="M30 128 L60 105 L91 128 Z" fill="#f27422"/><rect x="56" y="146" width="14" height="22" fill="#8b5a2b"/><rect x="41" y="137" width="12" height="10" fill="#7bc9e8"/></g><g><rect x="111" y="118" width="55" height="50" rx="4" fill="#fff1d4"/><path d="M104 120 L139 93 L174 120 Z" fill="#df5f1c"/><rect x="133" y="143" width="15" height="25" fill="#8b5a2b"/><rect x="116" y="132" width="12" height="10" fill="#7bc9e8"/></g><g><rect x="70" y="83" width="40" height="36" rx="4" fill="#fff5d9"/><path d="M64 85 L90 64 L116 85 Z" fill="#f58220"/></g><path d="M20 189 C67 159 112 160 168 197" fill="none" stroke="#f6d59e" stroke-width="16" stroke-linecap="round"/><path d="M27 165 V193 M45 160 V186 M187 153 V190 M202 145 V181" stroke="#7a4a24" stroke-width="5" stroke-linecap="round"/></svg>
SVG,
	);
	if ( ! isset( $icons[ $kind ] ) ) {
		$kind = 'mountain';
	}
	return $icons[ $kind ];
}

/**
 * Render a white information card for «دیدنی» identity/archive visuals.
 *
 * v2.11.13: the card uses a colored corner icon (waterfall/lake/mountain/park/cave/village)
 * and rewrites the place information in the center, like a clean featured-image card.
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

	$title       = get_the_title( $post_id );
	$city        = function_exists( 'sa_get_parent' ) ? sa_get_parent( $post_id, 'city' ) : null;
	$province    = function_exists( 'sa_get_parent' ) ? sa_get_parent( $post_id, 'province' ) : null;
	$city_name   = $city ? ( function_exists( 'sa_county_name' ) ? sa_county_name( $city ) : get_the_title( $city ) ) : '';
	$city_txt    = $city_name ? 'شهرستان ' . $city_name : '';
	$prov_txt    = $province ? 'استان ' . sa_province_name_for_post( $province ) : '';
	$location = trim( $city_txt . ( $city_txt && $prov_txt ? '  |  ' : '' ) . $prov_txt );
	$terms    = get_the_terms( $post_id, 'attraction_type' );
	$type_txt = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'دیدنی';
	$kind     = sa_attraction_icon_kind( $post_id, $title, $type_txt );
	$classes  = 'sa-attraction-diagram sa-attraction-diagram--' . sanitize_html_class( $context ) . ' sa-attraction-diagram--icon-' . sanitize_html_class( $kind );
	$aria     = trim( 'کارت معرفی ' . $title . ' ' . $city_txt . ' ' . $prov_txt );

	$english_name = trim( (string) get_post_meta( $post_id, 'sa_english_name', true ) );
	$city_en      = $city ? trim( ucwords( str_replace( '-', ' ', get_post_field( 'post_name', $city ) ) ) . ' city' ) : '';
	$prov_en      = $province ? trim( ucwords( str_replace( '-', ' ', get_post_field( 'post_name', $province ) ) ) ) : '';
	$english_line = implode( '  |  ', array_filter( array( $english_name, $city_en, $prov_en ) ) );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $classes ); ?>" role="img" aria-label="<?php echo esc_attr( $aria ); ?>">
		<div class="sa-attraction-diagram__art" aria-hidden="true">
			<?php echo sa_attraction_diagram_icon_svg( $kind ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<div class="sa-attraction-diagram__copy">
			<strong><?php echo esc_html( $title ); ?></strong>
			<?php if ( $location ) : ?>
				<span class="sa-attraction-diagram__meta"><?php echo esc_html( $location ); ?></span>
			<?php endif; ?>
			<span class="sa-attraction-diagram__pin" aria-hidden="true"></span>
			<?php if ( $english_line ) : ?>
				<span class="sa-attraction-diagram__english"><?php echo esc_html( $english_line ); ?></span>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return trim( ob_get_clean() );
}
