<?php
/**
 * Level 7 publish gate: an entity cannot be published while a blocker is missing.
 *
 * Blockers (data model): missing_relation, missing_seo_fields, missing_faq,
 * missing_featured_image, missing_primary_taxonomy.
 * Mode (Customizer): 'hard' = demote to draft + notice (default), 'soft' = publish + warning.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required belongs_to relations per type.
 *
 * @param string $type CPT.
 * @return string[] meta keys
 */
function sa_gate_required_relations( $type ) {
	switch ( $type ) {
		case 'city':
			return array( 'sa_province_id' );
		case 'attraction':
		case 'local_food':
		case 'souvenir':
		case 'accommodation':
			return array( 'sa_city_id' );
		case 'travel_route':
			return array( 'sa_city_ids' );
		default:
			return array();
	}
}

/**
 * Evaluate blockers for a post from either the submitted form or stored meta.
 *
 * @param int         $post_id Post ID (0 for new).
 * @param string      $type    CPT.
 * @param array|null  $form    $_POST-like array when saving via the edit screen, null to read meta.
 * @return string[] Human-readable list of missing items (empty = passes).
 */
function sa_gate_missing( $post_id, $type, $form = null ) {
	$e = sa_entity( $type );
	if ( ! $e ) {
		return array();
	}
	$missing = array();

	$get = function ( $key ) use ( $post_id, $form ) {
		if ( null !== $form ) {
			return isset( $form[ $key ] ) ? $form[ $key ] : '';
		}
		return get_post_meta( $post_id, $key, true );
	};

	// missing_relation.
	foreach ( sa_gate_required_relations( $type ) as $key ) {
		$val = $get( $key );
		if ( is_array( $val ) ) {
			$val = array_filter( array_map( 'absint', $val ) );
		}
		if ( 'sa_city_ids' === $key && null === $form ) {
			$val = sa_meta_ids( $post_id, $key );
		}
		if ( empty( $val ) ) {
			$target    = 'sa_city_ids' === $key ? 'شهرهای مسیر' : sa_entity_label( str_replace( array( 'sa_', '_id' ), '', $key ) );
			$missing[] = 'رابطه: ' . $target . ' انتخاب نشده است';
		}
	}

	// missing_seo_fields.
	foreach ( array( 'sa_seo_title' => 'عنوان سئو', 'sa_seo_description' => 'توضیحات متا', 'sa_focus_keyword' => 'کلیدواژه‌ی کانونی' ) as $key => $label ) {
		if ( '' === trim( (string) $get( $key ) ) ) {
			$missing[] = 'سئو: ' . $label;
		}
	}

	// missing_faq (≥1 complete pair; 3 recommended).
	if ( null !== $form ) {
		$qs    = isset( $form['sa_faq_q'] ) ? (array) $form['sa_faq_q'] : array();
		$as    = isset( $form['sa_faq_a'] ) ? (array) $form['sa_faq_a'] : array();
		$pairs = 0;
		foreach ( $qs as $i => $q ) {
			if ( '' !== trim( (string) $q ) && isset( $as[ $i ] ) && '' !== trim( (string) $as[ $i ] ) ) {
				$pairs++;
			}
		}
	} else {
		$pairs = count( sa_get_faq( $post_id ) );
	}
	if ( $pairs < 1 ) {
		$missing[] = 'سوالات متداول: دست‌کم یک پرسش و پاسخ';
	}

	// missing_featured_image.
	$thumb = null !== $form ? ( isset( $form['_thumbnail_id'] ) ? (int) $form['_thumbnail_id'] : 0 ) : (int) get_post_thumbnail_id( $post_id );
	if ( $thumb <= 0 ) {
		$missing[] = 'تصویر شاخص';
	}

	// missing_primary_taxonomy.
	$tax = $e['primary_taxonomy'];
	if ( taxonomy_exists( $tax ) ) {
		$has_term = false;
		if ( null !== $form ) {
			$input = isset( $form['tax_input'][ $tax ] ) ? (array) $form['tax_input'][ $tax ] : array();
			$input = array_filter( array_map( 'trim', array_map( 'strval', $input ) ), 'strlen' );
			$input = array_diff( $input, array( '0' ) );
			$has_term = ! empty( $input );
			// province_tax is auto-assigned from the relation for non-province entities.
			if ( ! $has_term && 'province_tax' === $tax && 'province' !== $type ) {
				$has_term = ! empty( $form['sa_city_id'] ) || ! empty( $form['sa_province_id'] ) || ! empty( $form['sa_city_ids'] );
			}
		} else {
			$has_term = has_term( '', $tax, $post_id );
		}
		if ( ! $has_term ) {
			$missing[] = 'طبقه‌بندی اصلی: ' . sa_taxonomies_config()[ $tax ]['singular'];
		}
	}

	return $missing;
}

/**
 * Gate mode.
 *
 * @return string hard|soft
 */
function sa_gate_mode() {
	return 'soft' === get_theme_mod( 'sa_gate_mode', 'hard' ) ? 'soft' : 'hard';
}

/**
 * Enforce on publish (edit screen only — quick edit / REST / CLI evaluate stored meta).
 *
 * @param array $data    Slashed data.
 * @param array $postarr Post array.
 * @return array
 */
function sa_gate_filter( $data, $postarr ) {
	if ( 'publish' !== $data['post_status'] || ! sa_is_entity( $data['post_type'] ) ) {
		return $data;
	}
	$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

	// Form submission from the edit screen?
	$from_form = isset( $_POST['sa_meta_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['sa_meta_nonce'] ), 'sa_save_meta' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $from_form ) {
		$missing = sa_gate_missing( $post_id, $data['post_type'], wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	} elseif ( $post_id ) {
		$missing = sa_gate_missing( $post_id, $data['post_type'], null );
	} else {
		return $data;
	}

	if ( empty( $missing ) ) {
		return $data;
	}

	$user = get_current_user_id();
	set_transient( 'sa_gate_' . $user, array( 'post' => $post_id, 'missing' => $missing, 'mode' => sa_gate_mode() ), 120 );

	if ( 'hard' === sa_gate_mode() ) {
		$data['post_status'] = 'draft';
	}
	return $data;
}
add_filter( 'wp_insert_post_data', 'sa_gate_filter', 20, 2 );

/**
 * Keep the message visible after redirect.
 *
 * @param string $location Redirect URL.
 * @return string
 */
function sa_gate_redirect( $location ) {
	if ( get_transient( 'sa_gate_' . get_current_user_id() ) ) {
		$location = add_query_arg( 'sa_gate', '1', remove_query_arg( 'message', $location ) );
	}
	return $location;
}
add_filter( 'redirect_post_location', 'sa_gate_redirect' );

/**
 * Admin notice.
 */
function sa_gate_notice() {
	$data = get_transient( 'sa_gate_' . get_current_user_id() );
	if ( ! $data ) {
		return;
	}
	delete_transient( 'sa_gate_' . get_current_user_id() );
	$class = 'hard' === $data['mode'] ? 'notice-error' : 'notice-warning';
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>';
	echo 'hard' === $data['mode'] ? 'انتشار متوقف شد و نوشته به‌صورت پیش‌نویس ذخیره شد.' : 'منتشر شد، اما برای رعایت استاندارد این موارد باید تکمیل شوند:';
	echo '</strong> (دروازه‌ی انتشار — مدل داده سطح ۷)</p><ul style="list-style:disc;margin-inline-start:1.5em">';
	foreach ( $data['missing'] as $m ) {
		echo '<li>' . esc_html( $m ) . '</li>';
	}
	echo '</ul></div>';
}
add_action( 'admin_notices', 'sa_gate_notice' );

/**
 * Status badge for admin columns / dashboard.
 *
 * @param int $post_id Post ID.
 * @return string HTML
 */
function sa_gate_badge( $post_id ) {
	$missing = sa_gate_missing( $post_id, get_post_type( $post_id ), null );
	if ( empty( $missing ) ) {
		return '<span class="sa-badge sa-badge--ok" title="همه‌ی الزامات سطح ۷ کامل است">کامل ✓</span>';
	}
	return '<span class="sa-badge sa-badge--warn" title="' . esc_attr( implode( ' · ', $missing ) ) . '">' . esc_html( sa_fa_digits( count( $missing ) ) ) . ' مورد ناقص</span>';
}
