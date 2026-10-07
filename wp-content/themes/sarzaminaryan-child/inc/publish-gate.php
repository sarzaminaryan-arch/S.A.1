<?php
/**
 * Level 7 publish gate: an entity cannot be published while a blocker is missing.
 *
 * Blockers (data model v1.2): missing_relation, missing_seo_fields, missing_featured_image,
 * missing_primary_taxonomy, conditional missing_sources, and missing_content_hygiene.
 * FAQ/link counts and coordinates are advisory, never publication locks. Warnings:
 * uncertainty markers and stale last_verified_date.
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

	// FAQ is optional. The visible accordion is editorial content, not a publish requirement.
	$min = sa_content_minimums( $type );
	$content = null !== $form
		? ( isset( $form['post_content'] ) ? (string) $form['post_content'] : (string) get_post_field( 'post_content', $post_id ) )
		: (string) get_post_field( 'post_content', $post_id );

	// missing_featured_image.
	// v2.11.11: attraction/«دیدنی» pages use a generated white diagram card
	// instead of the old large featured-image model, so a thumbnail is optional.
	if ( 'attraction' !== $type ) {
		$thumb = null !== $form ? ( isset( $form['_thumbnail_id'] ) ? (int) $form['_thumbnail_id'] : 0 ) : (int) get_post_thumbnail_id( $post_id );
		if ( $thumb <= 0 ) {
			$missing[] = 'تصویر شاخص';
		}
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

	// v1.1 — missing_coordinates.
	if ( ! empty( $min['coordinates'] ) ) {
		$lat_key = $lng_key = '';
		foreach ( $e['fields'] as $f ) {
			if ( substr( $f['name'], -8 ) === 'latitude' ) {
				$lat_key = $f['key'];
			} elseif ( substr( $f['name'], -9 ) === 'longitude' ) {
				$lng_key = $f['key'];
			}
		}
		if ( $lat_key && ( '' === trim( (string) $get( $lat_key ) ) || '' === trim( (string) $get( $lng_key ) ) ) ) {
			$missing[] = 'مختصات جغرافیایی (عرض و طول)';
		}
	}

	// v1.1 — missing_sources.
	$src_min = (int) $min['sources'];
	if ( $src_min > 0 ) {
		$src_count = sa_count_sources( (string) $get( 'sa_sources' ) );
		if ( $src_count < $src_min ) {
			$missing[] = 'منابع: ' . sa_fa_digits( $src_count ) . ' از حداقل ' . sa_fa_digits( $src_min ) . ' منبع دارای لینک';
		}
	}

	// v1.2 — link volume is diagnostic only; sa_gate_split() keeps it advisory.
	$link_min = (int) $min['internal_links'];
	if ( $link_min > 0 ) {
		$links = sa_count_internal_links( $content );
		if ( $links < $link_min ) {
			$missing[] = 'لینک داخلی: ' . sa_fa_digits( $links ) . ' از هدف تحریریهٔ ' . sa_fa_digits( $link_min ) . ' لینک؛ فقط اگر برای خواننده مفید است اضافه کنید';
		}
	}

	// v1.2 — content hygiene is a hard blocker (distinct from volume/source advisories).
	foreach ( sa_gate_content_hygiene_issues( $content ) as $issue ) {
		$missing[] = $issue;
	}

	return $missing;
}

/**
 * Find content-hygiene errors that must be fixed before publishing an entity.
 *
 * The transparency markers [نیازمند بررسی] and [منبع لازم] are deliberately not
 * treated as unresolved placeholders: they are rendered visibly for readers.
 *
 * @param string $content Stored editor HTML.
 * @return string[]
 */
function sa_gate_content_hygiene_issues( $content ) {
	$content = (string) $content;
	if ( '' === trim( $content ) ) {
		return array();
	}

	// Ignore comments and non-rendered markup so hidden notes do not trip the gate.
	$html = preg_replace( '/<!--.*?-->/s', ' ', $content );
	$html = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1\s*>#isu', ' ', $html );
	$html = is_string( $html ) ? $html : $content;
	$checks = function_exists( 'sa_content_hygiene_checks' )
		? (array) sa_content_hygiene_checks()
		: array( 'body_h1', 'unresolved_editorial_placeholder', 'empty_or_unlabelled_anchor' );
	$issues = array();

	if ( in_array( 'body_h1', $checks, true ) && preg_match( '/<h1(?:\s|>)/iu', $html ) ) {
		$issues[] = 'بهداشت محتوا: تیتر H1 در بدنهٔ ویرایشگر وجود دارد؛ H1 صفحه از قالب می‌آید';
	}

	$visible = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	if ( in_array( 'unresolved_editorial_placeholder', $checks, true ) ) {
		$patterns = array(
			'/\b(?:TODO|FIXME|TBD|PLACEHOLDER|INSERT[_ -]?HERE|LIPSUM)\b/iu',
			'/\{\{[^{}]{1,120}\}\}/u',
			'/(?:برای انتشار نهایی|یادداشت برای نویسنده|یادداشت ویراستاری|این بخش را تکمیل کنید)/u',
			'/«\s*»/u',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $visible ) ) {
				$issues[] = 'بهداشت محتوا: یادداشت تحریریه یا جای‌نگهدار حل‌نشده در متن دیده شد';
				break;
			}
		}
	}

	if ( in_array( 'empty_or_unlabelled_anchor', $checks, true ) ) {
		$empty_anchors = 0;
		if ( preg_match_all( '/<a\b([^>]*)>(.*?)<\/a\s*>/isu', $html, $anchors, PREG_SET_ORDER ) ) {
			foreach ( $anchors as $anchor ) {
				$attributes = $anchor[1];
				if ( ! preg_match( '/\bhref\s*=/iu', $attributes ) ) {
					continue;
				}

				$has_label = false;
				if ( preg_match_all( '/\b(?:aria-label|title)\s*=\s*(["\x27])(.*?)\1/isu', $attributes, $attribute_labels, PREG_SET_ORDER ) ) {
					foreach ( $attribute_labels as $label ) {
						$label_text = html_entity_decode( $label[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
						$label_text = preg_replace( '/[\s\p{Z}\x{200B}-\x{200F}\x{FEFF}]+/u', '', $label_text );
						if ( is_string( $label_text ) && '' !== $label_text ) {
							$has_label = true;
							break;
						}
					}
				}

				$inner = $anchor[2];
				if ( ! $has_label && preg_match_all( '/<img\b[^>]*\balt\s*=\s*(["\x27])(.*?)\1/isu', $inner, $alt_matches, PREG_SET_ORDER ) ) {
					foreach ( $alt_matches as $alt ) {
						$alt_text = html_entity_decode( $alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
						$alt_text = preg_replace( '/[\s\p{Z}\x{200B}-\x{200F}\x{FEFF}]+/u', '', $alt_text );
						if ( is_string( $alt_text ) && '' !== $alt_text ) {
							$has_label = true;
							break;
						}
					}
				}

				$text = html_entity_decode( strip_tags( $inner ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$text = preg_replace( '/[\s\p{Z}\x{200B}-\x{200F}\x{FEFF}]+/u', '', $text );
				if ( ! $has_label && ( ! is_string( $text ) || '' === $text ) ) {
					++$empty_anchors;
				}
			}
		}
		if ( $empty_anchors > 0 ) {
			$issues[] = 'بهداشت محتوا: ' . sa_fa_digits( $empty_anchors ) . ' پیوند بدون نام دسترس‌پذیر';
		}
	}

	return $issues;
}

/**
 * Count links to this site inside HTML content (absolute to home_url or root-relative).
 *
 * @param string $content HTML.
 * @return int
 */
function sa_count_internal_links( $content ) {
	if ( '' === trim( (string) $content ) ) {
		return 0;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$n    = 0;
	if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/iu', $content, $m ) ) {
		foreach ( $m[1] as $href ) {
			$href = trim( $href );
			if ( '' === $href || '#' === $href[0] ) {
				continue;
			}
			if ( '/' === $href[0] && ( ! isset( $href[1] ) || '/' !== $href[1] ) ) {
				$n++;
				continue;
			}
			$h = wp_parse_url( $href, PHP_URL_HOST );
			if ( $h && strcasecmp( $h, $host ) === 0 ) {
				$n++;
			}
		}
	}
	return $n;
}

/**
 * v1.1 — non-blocking warnings: uncertainty markers and stale verification date.
 *
 * @param int        $post_id Post ID.
 * @param string     $type    CPT.
 * @param array|null $form    Submitted form or null.
 * @return string[]
 */
function sa_gate_warnings( $post_id, $type, $form = null ) {
	$warn = array();
	$get  = function ( $key ) use ( $post_id, $form ) {
		if ( null !== $form ) {
			return isset( $form[ $key ] ) ? $form[ $key ] : '';
		}
		return get_post_meta( $post_id, $key, true );
	};
	$content = null !== $form && isset( $form['post_content'] ) ? (string) $form['post_content'] : (string) get_post_field( 'post_content', $post_id );
	$markers = sa_count_markers( $content );
	if ( null !== $form ) {
		$markers += sa_count_markers( implode( ' ', (array) ( isset( $form['sa_faq_a'] ) ? $form['sa_faq_a'] : array() ) ) );
	} else {
		foreach ( sa_get_faq( $post_id ) as $row ) {
			$markers += sa_count_markers( $row['a'] );
		}
	}
	if ( $markers > 0 ) {
		$warn[] = sa_fa_digits( $markers ) . ' برچسب «نیازمند بررسی / منبع لازم» در متن — برای خواننده نمایش داده می‌شود';
	}
	if ( 'attraction' === $type ) {
		$v = (string) $get( 'sa_last_verified_date' );
		if ( '' === $v ) {
			$warn[] = 'تاریخ آخرین راستی‌آزمایی ثبت نشده است';
		} elseif ( strtotime( $v ) && ( time() - strtotime( $v ) ) > sa_stale_after_days() * DAY_IN_SECONDS ) {
			$warn[] = 'راستی‌آزمایی ساعات/قیمت/دسترسی قدیمی‌تر از ' . sa_fa_digits( sa_stale_after_days() ) . ' روز است — نیازمند بازبینی';
		}
	}
	return $warn;
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
 * v2.1.0 — تفکیک «مانع انتشار» از «هشدار».
 *
 * نسخه‌ی قبل هر کمبودی را مانع انتشار می‌دانست. چون حداقل لینک داخلیِ «استان»
 * ۲۰ بود و یک استان تا ساخته‌شدن صفحه‌های شهرستانش به ۲۰ لینک نمی‌رسد،
 * ۲۷ استانِ نوشته‌شده (~۱۵۱٬۰۰۰ واژه) در draft قفل شده بودند و سایت هیچ
 * بازخوردی از موتور جست‌وجو نمی‌گرفت.
 *
 * قاعده‌ی تازه: فقط چیزی مانع انتشار است که یک نویسنده بتواند همین حالا و
 * تنها روی همین صفحه درستش کند — تصویر شاخص، فیلدهای سئو، رابطه‌ی والد و
 * طبقه‌بندی اصلی. تعداد FAQ، تعداد لینک داخلی و مختصات به بازخورد یا به
 * صفحه‌های دیگر وابسته‌اند، پس هشدار می‌شوند نه قفل.
 *
 * @param string[] $missing فهرست کمبودها از sa_gate_missing().
 * @return array{0:string[],1:string[]} [مانع‌ها، هشدارها]
 */
function sa_gate_split( $missing ) {
	$advisory_re = '/^(سوالات متداول|لینک داخلی|مختصات)/u';
	$blocking    = array();
	$advisory    = array();
	foreach ( (array) $missing as $row ) {
		$row = (string) $row;
		if ( preg_match( $advisory_re, $row ) ) {
			$advisory[] = $row . ' — مانع انتشار نیست';
			continue;
		}
		$blocking[] = $row;
	}

	/**
	 * فهرست نهایی مانع‌های انتشار.
	 *
	 * برای انتشار بی‌قیدوشرط: add_filter( 'sa_gate_blocking', '__return_empty_array' );
	 *
	 * @param string[] $blocking مانع‌ها.
	 * @param string[] $missing  همه‌ی کمبودها.
	 */
	$blocking = (array) apply_filters( 'sa_gate_blocking', $blocking, $missing );

	return array( $blocking, $advisory );
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

	$warnings = sa_gate_warnings( $post_id, $data['post_type'], $from_form ? wp_unslash( $_POST ) : null ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	// v2.1.0 — کمبودها به دو دسته تقسیم می‌شوند.
	list( $blocking, $advisory ) = sa_gate_split( $missing );
	$warnings = array_merge( $warnings, $advisory );

	// حالت soft قالب همچنان کار می‌کند: هیچ‌چیز مانع انتشار نمی‌شود.
	if ( 'soft' === sa_gate_mode() && $blocking ) {
		$warnings = array_merge( $warnings, $blocking );
		$blocking = array();
	}

	$user = get_current_user_id();
	if ( $blocking || $warnings ) {
		set_transient(
			'sa_gate_' . $user,
			array(
				'post'     => $post_id,
				'missing'  => $blocking,
				'warnings' => $warnings,
				'mode'     => $blocking ? 'hard' : 'info',
			),
			120
		);
	}

	if ( $blocking ) {
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
	$warnings = isset( $data['warnings'] ) ? (array) $data['warnings'] : array();
	if ( ! empty( $data['missing'] ) ) {
		$class = 'hard' === $data['mode'] ? 'notice-error' : 'notice-warning';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p><strong>';
		echo 'hard' === $data['mode'] ? 'انتشار متوقف شد و نوشته به‌صورت پیش‌نویس ذخیره شد.' : 'منتشر شد، اما برای رعایت استاندارد این موارد باید تکمیل شوند:';
		echo '</strong> (دروازه‌ی انتشار — مدل داده سطح ۷)</p><ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( $data['missing'] as $m ) {
			echo '<li>' . esc_html( $m ) . '</li>';
		}
		echo '</ul></div>';
	}
	if ( $warnings ) {
		echo '<div class="notice notice-info is-dismissible"><p><strong>یادآوری‌های راستی‌آزمایی:</strong></p><ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( $warnings as $w ) {
			echo '<li>' . esc_html( $w ) . '</li>';
		}
		echo '</ul></div>';
	}
}
add_action( 'admin_notices', 'sa_gate_notice' );

/**
 * Status badge for admin columns / dashboard.
 *
 * @param int $post_id Post ID.
 * @return string HTML
 */
function sa_gate_badge( $post_id ) {
	$type     = get_post_type( $post_id );
	$missing  = sa_gate_missing( $post_id, $type, null );
	$warnings = sa_gate_warnings( $post_id, $type, null );

	// v2.1.0 — فقط مانع‌ها قرمزند؛ بقیه «قابل بهبود».
	list( $blocking, $advisory ) = sa_gate_split( $missing );
	$warnings = array_merge( $warnings, $advisory );

	$extra = $warnings ? ' <span class="sa-badge sa-badge--stale" title="' . esc_attr( implode( ' · ', $warnings ) ) . '">قابل بهبود</span>' : '';
	if ( empty( $blocking ) ) {
		return '<span class="sa-badge sa-badge--ok" title="آماده‌ی انتشار است">قابل انتشار ✓</span>' . $extra;
	}
	return '<span class="sa-badge sa-badge--warn" title="' . esc_attr( implode( ' · ', $blocking ) ) . '">' . esc_html( sa_fa_digits( count( $blocking ) ) ) . ' مانع انتشار</span>' . $extra;
}
