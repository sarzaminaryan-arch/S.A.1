<?php
/**
 * Plugin Name:       سرزمین آریان | ممیزی هوشمند محتوا
 * Description:       تحلیل تحریریه‌ای و فنی محتوا برای نوشته‌ها و موجودیت‌های گردشگری؛ همراه با گزارش کلی و تحلیل در صفحهٔ ویرایش.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            سرزمین آریان
 * Text Domain:       sa-content-audit
 *
 * @package SA_Content_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_AUDIT_VERSION', '1.0.0' );
define( 'SA_AUDIT_FILE', __FILE__ );
define( 'SA_AUDIT_DIR', plugin_dir_path( __FILE__ ) );
define( 'SA_AUDIT_URL', plugin_dir_url( __FILE__ ) );

add_action( 'admin_menu', 'sa_audit_admin_menu' );
add_action( 'add_meta_boxes', 'sa_audit_register_metabox' );
add_action( 'admin_enqueue_scripts', 'sa_audit_admin_assets' );
add_action( 'wp_ajax_sa_audit_analyze', 'sa_audit_ajax_analyze' );
add_action( 'wp_ajax_sa_audit_batch', 'sa_audit_ajax_batch' );
add_action( 'save_post', 'sa_audit_store_saved_score', 99, 3 );

/** Register the whole-site report page. */
function sa_audit_admin_menu() {
	add_management_page( 'ممیزی محتوا', 'ممیزی محتوا', 'edit_posts', 'sa-content-audit', 'sa_audit_dashboard' );
}

/** Add a sidebar box on all public, editable post types with an editor. */
function sa_audit_register_metabox() {
	foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
		if ( 'attachment' === $type->name || empty( $type->show_ui ) || ! post_type_supports( $type->name, 'editor' ) ) {
			continue;
		}
		add_meta_box( 'sa-content-audit', 'ممیزی کیفیت محتوا', 'sa_audit_render_metabox', $type->name, 'side', 'high' );
	}
}

/** Print the editor interface. */
function sa_audit_render_metabox( $post ) {
	$previous = get_post_meta( $post->ID, '_sa_audit_score', true );
	$checked  = get_post_meta( $post->ID, '_sa_audit_checked', true );
	echo '<div class="sa-audit-editor" dir="rtl" data-post-id="' . esc_attr( $post->ID ) . '">';
	echo '<p class="sa-audit-intro">پیش از انتشار یا پس از هر ویرایش، تحلیل را اجرا کنید. نتیجه بر اساس متن فعلی و ذخیره‌نشدهٔ ویرایشگر محاسبه می‌شود.</p>';
	echo '<button type="button" class="button button-primary sa-audit-run">تحلیل / آزمون دوباره</button>';
	echo '<span class="sa-audit-loading" aria-live="polite"></span>';
	if ( '' !== $previous ) {
		echo '<p class="sa-audit-previous">آخرین امتیاز ذخیره‌شده: <strong>' . esc_html( $previous ) . '/100</strong>';
		$saved_before = get_post_meta( $post->ID, '_sa_audit_previous_score', true );
		if ( '' !== $saved_before ) {
			$change = (int) $previous - (int) $saved_before;
			echo '<br><small>تغییر نسبت به ذخیرهٔ قبلی: ' . ( $change > 0 ? '+' : '' ) . esc_html( $change ) . '</small>';
		}
		if ( $checked ) {
			echo '<br><small>زمان بررسی: ' . esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $checked ), 'Y-m-d H:i' ) ) . '</small>';
		}
		echo '</p>';
	}
	echo '<div class="sa-audit-result" aria-live="polite"></div>';
	echo '<details class="sa-audit-limits"><summary>حدود دقت این ابزار</summary><p>گوگل امتیاز سئوی رسمی یا تعداد کلمهٔ ایده‌آل اعلام نکرده است. این درصد یک شاخص تحریریه‌ایِ شفاف است، نه نمرهٔ گوگل. تشخیص قطعی متن هوش مصنوعی یا سرقت ادبی ممکن نیست؛ این افزونه چنین ادعایی نمی‌کند.</p></details>';
	echo '</div>';
}

/** Load bundled RTL styles/scripts only on our screens. */
function sa_audit_admin_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_ours = ( 'tools_page_sa-content-audit' === $hook ) || ( $screen && in_array( $screen->base, array( 'post', 'post-new' ), true ) && $screen->post_type && post_type_supports( $screen->post_type, 'editor' ) );
	if ( ! $is_ours ) {
		return;
	}
	wp_enqueue_style( 'sa-content-audit', SA_AUDIT_URL . 'assets/admin.css', array(), SA_AUDIT_VERSION );
	wp_enqueue_script( 'sa-content-audit', SA_AUDIT_URL . 'assets/admin.js', array( 'jquery' ), SA_AUDIT_VERSION, true );
	wp_localize_script(
		'sa-content-audit',
		'SAAudit',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'sa_content_audit' ),
			'editorNonce' => wp_create_nonce( 'sa_content_audit_editor' ),
			'labels'    => array(
				'working' => 'در حال تحلیل…',
				'error'   => 'تحلیل انجام نشد. صفحه را تازه کنید و دوباره تلاش کنید.',
				'empty'   => 'موردی برای نمایش وجود ندارد.',
			),
		)
	);
}

/** Normalize Persian/Arabic letter variants, digits, and whitespace. */
function sa_audit_strlen( $text ) {
	if ( function_exists( 'mb_strlen' ) ) {
		return mb_strlen( (string) $text, 'UTF-8' );
	}
	if ( function_exists( 'iconv_strlen' ) ) {
		$length = iconv_strlen( (string) $text, 'UTF-8' );
		return false === $length ? strlen( (string) $text ) : $length;
	}
	return strlen( (string) $text );
}

/** UTF-8 case-insensitive substring search, with a safe core-PHP fallback. */
function sa_audit_stripos( $haystack, $needle ) {
	if ( function_exists( 'mb_stripos' ) ) {
		return mb_stripos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
	}
	return stripos( (string) $haystack, (string) $needle );
}

/** Lowercase where possible (Persian has no case; fallback is enough for Latin suggestions). */
function sa_audit_lower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
}

function sa_audit_normalize( $text ) {
	$text = (string) $text;
	$text = strtr( $text, array( 'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ۀ' => 'هٔ', 'ة' => 'ه', 'ـ' => '' ) );
	$text = strtr( $text, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9' ) );
	$text = preg_replace( '/[\\x{200d}\\x{00a0}]+/u', ' ', $text );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/** Tokenize Unicode words. */
function sa_audit_tokens( $text ) {
	$text = sa_audit_normalize( $text );
	preg_match_all( '/[\p{L}\p{N}]+(?:[\x{200c}\x{200d}][\p{L}\p{N}]+)*/u', $text, $matches );
	return isset( $matches[0] ) ? $matches[0] : array();
}

/** Collect exact long-sentence overlaps with a small recent sample from the same post type. */
function sa_audit_find_overlap( $post_id, $plain ) {
	if ( ! $post_id ) {
		return array();
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$sentences = preg_split( '/(?<=[.!؟?؛])\s+/u', sa_audit_normalize( $plain ), -1, PREG_SPLIT_NO_EMPTY );
	$long = array();
	foreach ( $sentences as $sentence ) {
		$tokens = sa_audit_tokens( $sentence );
		if ( count( $tokens ) >= 12 ) {
			$long[ md5( implode( ' ', $tokens ) ) ] = wp_trim_words( $sentence, 12, '…' );
		}
	}
	if ( ! $long ) {
		return array();
	}
	$candidates = get_posts(
		array(
			'post_type' => $post->post_type,
			'post_status' => 'publish',
			'posts_per_page' => 35,
			'post__not_in' => array( (int) $post_id ),
			'orderby' => 'modified',
			'order' => 'DESC',
			'no_found_rows' => true,
			'suppress_filters' => true,
			'fields' => 'ids',
		)
	);
	$found = array();
	foreach ( $candidates as $candidate_id ) {
		$other = wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $candidate_id ) ) );
		$other_sentences = preg_split( '/(?<=[.!؟?؛])\s+/u', sa_audit_normalize( $other ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $other_sentences as $sentence ) {
			$tokens = sa_audit_tokens( $sentence );
			if ( count( $tokens ) >= 12 ) {
				$key = md5( implode( ' ', $tokens ) );
				if ( isset( $long[ $key ] ) ) {
					$found[] = array( 'sentence' => $long[ $key ], 'title' => get_the_title( $candidate_id ) );
					unset( $long[ $key ] );
					if ( count( $found ) >= 3 ) {
						return $found;
					}
				}
			}
		}
	}
	return $found;
}

/** Return the configured focus keyword, including this site's metadata convention. */
function sa_audit_focus_keyword( $post_id ) {
	$keys = array( 'sa_focus_keyword', '_yoast_wpseo_focuskw', 'rank_math_focus_keyword', '_aioseo_focus_keyphrase' );
	foreach ( $keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return trim( $value );
		}
	}
	return '';
}

/** Suggest useful two/three-word phrases from the article itself (not search-volume research). */
function sa_audit_keyword_suggestions( $tokens, $title ) {
	$stop = array_flip( array( 'برای', 'این', 'آن', 'های', 'هایی', 'است', 'بود', 'شد', 'شود', 'می', 'از', 'به', 'در', 'با', 'که', 'را', 'و', 'یا', 'تا', 'بر', 'یک', 'هم', 'هر', 'چه', 'چگونه', 'درباره', 'ایران', 'خود', 'اما', 'اگر', 'نیز', 'بسیار', 'کرد', 'کردن', 'شده', 'می‌شود', 'می‌کند', 'همچنین', 'وجود' ) );
	$clean = array_map( 'sa_audit_lower', $tokens );
	$counts = array();
	for ( $n = 2; $n <= 3; $n++ ) {
		for ( $i = 0; $i <= count( $clean ) - $n; $i++ ) {
			$parts = array_slice( $clean, $i, $n );
			$valid = true;
			foreach ( $parts as $part ) {
				if ( sa_audit_strlen( $part ) <= 2 || isset( $stop[ $part ] ) ) {
					$valid = false;
					break;
				}
			}
			if ( ! $valid ) {
				continue;
			}
			$phrase = implode( ' ', $parts );
			$counts[ $phrase ] = isset( $counts[ $phrase ] ) ? $counts[ $phrase ] + 1 : 1;
		}
	}
	uksort( $counts, function ( $a, $b ) use ( $counts ) { return $counts[ $b ] - $counts[ $a ]; } );
	$avoid = sa_audit_normalize( $title );
	$out = array();
	foreach ( $counts as $phrase => $count ) {
		if ( $count < 2 || false !== sa_audit_stripos( $avoid, $phrase ) ) {
			continue;
		}
		$out[] = $phrase;
		if ( count( $out ) >= 5 ) {
			break;
		}
	}
	return $out;
}

/** Add a scored recommendation. */
function sa_audit_issue( &$issues, $level, $area, $title, $detail ) {
	$issues[] = array( 'level' => $level, 'area' => $area, 'title' => $title, 'detail' => $detail );
}

/**
 * Suggest a few actually related, published internal pages from the site's relation metadata.
 * No new links are inserted; the editor decides whether each target is relevant.
 */
function sa_audit_internal_suggestions( $post_id, $content ) {
	if ( ! $post_id ) {
		return array();
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$ids = array();
	$type = $post->post_type;
	$city_id = (int) get_post_meta( $post_id, 'sa_city_id', true );
	$province_id = (int) get_post_meta( $post_id, 'sa_province_id', true );
	if ( 'province' === $type ) {
		$center = (int) get_post_meta( $post_id, 'sa_province_center_city_id', true );
		if ( $center ) {
			$ids[] = $center;
		}
		$children = get_posts( array( 'post_type' => 'city', 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => 'sa_province_id', 'meta_value' => $post_id ) );
		$ids = array_merge( $ids, $children );
	} elseif ( 'city' === $type ) {
		if ( $province_id ) {
			$ids[] = $province_id;
		}
		$children = get_posts( array( 'post_type' => array( 'attraction', 'local_food', 'souvenir' ), 'post_status' => 'publish', 'posts_per_page' => 4, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => 'sa_city_id', 'meta_value' => $post_id ) );
		$ids = array_merge( $ids, $children );
	} elseif ( in_array( $type, array( 'attraction', 'local_food', 'souvenir' ), true ) ) {
		if ( $city_id ) {
			$ids[] = $city_id;
		}
		if ( $province_id ) {
			$ids[] = $province_id;
		}
		$related_types = array_values( array_diff( array( 'attraction', 'local_food', 'souvenir' ), array( $type ) ) );
		if ( $city_id ) {
			$siblings = get_posts( array( 'post_type' => $related_types, 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true, 'meta_key' => 'sa_city_id', 'meta_value' => $city_id ) );
			$ids = array_merge( $ids, $siblings );
		}
	} elseif ( 'travel_route' === $type ) {
		$cities = get_post_meta( $post_id, 'sa_city_ids', true );
		foreach ( (array) $cities as $city ) {
			$ids[] = absint( $city );
		}
	}
	$existing = array();
	if ( preg_match_all( '/<a\b[^>]*href\s*=\s*(["\'])(.*?)\1/is', $content, $links ) ) {
		foreach ( $links[2] as $href ) {
			$target = url_to_postid( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );
			if ( $target ) {
				$existing[] = (int) $target;
			}
		}
	}
	$out = array();
	foreach ( array_unique( array_filter( array_map( 'absint', $ids ) ) ) as $id ) {
		if ( $id === (int) $post_id || in_array( $id, $existing, true ) || 'publish' !== get_post_status( $id ) ) {
			continue;
		}
		$url = get_permalink( $id );
		if ( ! $url ) {
			continue;
		}
		$out[] = array( 'title' => get_the_title( $id ), 'url' => $url, 'type' => get_post_type( $id ) );
		if ( count( $out ) >= 5 ) {
			break;
		}
	}
	return $out;
}

/** Main rule-based content audit. The points are explicit editorial heuristics, not Google's score. */
function sa_audit_analyze( $input, $post_id = 0, $fast = false ) {
	$title = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : '' );
	$content = isset( $input['content'] ) ? (string) $input['content'] : '';
	$excerpt = isset( $input['excerpt'] ) ? (string) $input['excerpt'] : '';
	$focus = sanitize_text_field( isset( $input['focus_keyword'] ) ? $input['focus_keyword'] : '' );
	$meta_title = sanitize_text_field( isset( $input['seo_title'] ) ? $input['seo_title'] : '' );
	$meta_description = sanitize_textarea_field( isset( $input['seo_description'] ) ? $input['seo_description'] : '' );
	if ( '' === $focus && $post_id ) {
		$focus = sa_audit_focus_keyword( $post_id );
	}
	if ( '' === $meta_title && $post_id ) {
		$meta_title = (string) get_post_meta( $post_id, 'sa_seo_title', true );
	}
	if ( '' === $meta_description && $post_id ) {
		$meta_description = (string) get_post_meta( $post_id, 'sa_seo_description', true );
	}
	$plain_html = preg_replace( '/<\\/(?:p|h[1-6]|li|div|blockquote|tr|ul|ol)\\s*>|<br\\s*\\/?>/i', ' ', strip_shortcodes( $content ) );
	$plain = wp_strip_all_tags( $plain_html );
	$normal = sa_audit_normalize( $plain );
	$tokens = sa_audit_tokens( $normal );
	$word_count = count( $tokens );
	$char_count = sa_audit_strlen( $normal );
	$paragraphs = preg_split( '/<\/p\s*>|\R\s*\R/u', $content, -1, PREG_SPLIT_NO_EMPTY );
	$paragraph_count = 0;
	foreach ( $paragraphs as $paragraph ) {
		if ( count( sa_audit_tokens( wp_strip_all_tags( $paragraph ) ) ) >= 12 ) {
			$paragraph_count++;
		}
	}
	$sentences = preg_split( '/(?<=[.!؟?؛])\s+/u', $normal, -1, PREG_SPLIT_NO_EMPTY );
	$sentence_lengths = array();
	foreach ( $sentences as $sentence ) {
		$count = count( sa_audit_tokens( $sentence ) );
		if ( $count ) {
			$sentence_lengths[] = $count;
		}
	}
	$avg_sentence = $sentence_lengths ? round( array_sum( $sentence_lengths ) / count( $sentence_lengths ), 1 ) : 0;
	$h2_count = preg_match_all( '/<h2\b/i', $content );
	$h3_count = preg_match_all( '/<h3\b/i', $content );
	$h1_count = preg_match_all( '/<h1\b/i', $content );
	$title_length = sa_audit_strlen( sa_audit_normalize( $meta_title ? $meta_title : $title ) );
	$description_length = sa_audit_strlen( sa_audit_normalize( $meta_description ? $meta_description : $excerpt ) );
	$focus_norm = sa_audit_lower( sa_audit_normalize( $focus ) );
	$focus_tokens = sa_audit_tokens( $focus_norm );
	$focus_hits = 0;
	if ( $focus_norm && $focus_tokens ) {
		$focus_hits = substr_count( ' ' . sa_audit_lower( sa_audit_normalize( $normal ) ) . ' ', ' ' . $focus_norm . ' ' );
	}
	$density = ( $focus_norm && $word_count ) ? round( 100 * $focus_hits * max( 1, count( $focus_tokens ) ) / $word_count, 2 ) : 0;
	$title_has_keyword = $focus_norm && false !== sa_audit_stripos( sa_audit_normalize( $title ), $focus_norm );
	$first_chunk = implode( ' ', array_slice( $tokens, 0, max( 1, (int) ceil( $word_count * 0.1 ) ) ) );
	$intro_has_keyword = $focus_norm && false !== sa_audit_stripos( $first_chunk, $focus_norm );
	$heading_text = '';
	if ( preg_match_all( '/<h[2-6]\b[^>]*>(.*?)<\/h[2-6]>/is', $content, $heading_matches ) ) {
		$heading_text = sa_audit_normalize( wp_strip_all_tags( implode( ' ', $heading_matches[1] ) ) );
	}
	$heading_has_keyword = $focus_norm && false !== sa_audit_stripos( $heading_text, $focus_norm );

	$internal = 0;
	$external = 0;
	$broken_internal = 0;
	$external_hosts = array();
	$empty_anchors = 0;
	$anchor_count = 0;
	if ( preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', $content, $anchor_matches, PREG_SET_ORDER ) ) {
		foreach ( $anchor_matches as $anchor ) {
			if ( ! preg_match( '/href\s*=\s*(["\'])(.*?)\1/is', $anchor[1], $href_match ) ) {
				continue;
			}
			$href = html_entity_decode( trim( $href_match[2] ), ENT_QUOTES, 'UTF-8' );
			$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
			$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			if ( $host && $host !== $home_host ) {
				$external++;
				$external_hosts[ preg_replace( '/^www\./', '', $host ) ] = true;
			} elseif ( '' !== $href && '#' !== $href[0] && 0 !== strpos( $href, 'mailto:' ) && 0 !== strpos( $href, 'tel:' ) ) {
				$internal++;
				$anchor_count++;
				$anchor_text = trim( wp_strip_all_tags( $anchor[2] ) );
				$has_image_alt = preg_match( '/<img\b[^>]*\balt\s*=\s*(["\'])(.*?)\1/i', $anchor[2], $image_alt ) && '' !== trim( $image_alt[2] );
				if ( '' === $anchor_text && ! $has_image_alt ) {
					$empty_anchors++;
				}
				if ( $host && $host === $home_host && $post_id ) {
					$target_id = url_to_postid( $href );
					if ( $target_id && 'publish' !== get_post_status( $target_id ) ) {
						$broken_internal++;
					}
				}
			}
		}
	}

	$image_count = preg_match_all( '/<img\b[^>]*>/i', $content );
	$missing_alt = 0;
	if ( $image_count && preg_match_all( '/<img\b[^>]*>/i', $content, $images ) ) {
		foreach ( $images[0] as $image ) {
			if ( ! preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $image, $alt ) || '' === trim( $alt[2] ) ) {
				$missing_alt++;
			}
		}
	}
	$featured_id = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
	if ( $featured_id ) {
		$image_count++;
		if ( '' === trim( (string) get_post_meta( $featured_id, '_wp_attachment_image_alt', true ) ) ) {
			$missing_alt++;
		}
	}
	$spelling_flags = array();
	$rules = array(
		'/\s+[،؛؟!,.]/u' => 'فاصلهٔ اضافه پیش از نشانهٔ نگارشی را حذف کنید.',
		'/[،؛؟!](?=\S)/u' => 'پس از نشانهٔ نگارشی فارسی یک فاصله بگذارید.',
		'/(?:!|؟|\?|،|؛|\.){2,}/u' => 'نشانه‌های نگارشی تکراری را بازبینی کنید.',
		'/(?<![\p{L}])می\s+(?:شود|شوند|کند|کنند|رود|رسد|توان|تواند|توانند|خواهند|دهد|دهند)(?![\p{L}])/u' => 'برای فعل‌های پیشونددار، نیم‌فاصله را بررسی کنید؛ مانند «می‌شود».',
		'/(?<![\p{L}])نمی\s+(?:شود|شوند|کند|کنند|توان|تواند|توانند|رود|رسد)(?![\p{L}])/u' => 'برای «نمی‌» معمولاً نیم‌فاصله به‌کار می‌رود.',
		'/ك|ي/u' => 'حروف عربی «ك/ي» دیده شد؛ در متن فارسی «ک/ی» را به‌کار ببرید.',
		'/\s{2,}/u' => 'فاصله‌های پشت‌سرهم را به یک فاصله تبدیل کنید.',
	);
	foreach ( $rules as $pattern => $message ) {
		if ( preg_match( $pattern, $plain ) ) {
			$spelling_flags[] = $message;
		}
	}
	if ( preg_match( '/(?<![\p{L}])(?:میباشد|میباشند)(?![\p{L}])/u', $plain ) ) {
		$spelling_flags[] = '«می‌باشد» را فقط در صورت نیاز به‌کار ببرید؛ در نثر ساده اغلب «است» روان‌تر است.';
	}

	$spelling_suggestions = array(
		'/(?<![\p{L}])گزارشات(?![\p{L}])/u' => '«گزارشات» را در نثر معیار به «گزارش‌ها» تغییر دهید.',
		'/(?<![\p{L}])به عنوان(?![\p{L}])/u' => 'صورت نگارشی پیشنهادی: «به‌عنوان».',
		'/(?<![\p{L}])استفاده ی(?![\p{L}])/u' => 'صورت نگارشی پیشنهادی: «استفاده‌ی».',
	);
	foreach ( $spelling_suggestions as $pattern => $suggestion ) {
		if ( preg_match( $pattern, $plain ) ) {
			$spelling_flags[] = $suggestion;
		}
	}

	$issues = array();
	$sections = array(
		'content' => array( 'label' => 'پوشش و کیفیت متن', 'weight' => 20, 'score' => 0 ),
		'keywords' => array( 'label' => 'کلیدواژه و نیت جست‌وجو', 'weight' => 15, 'score' => 0 ),
		'structure' => array( 'label' => 'ساختار و نمایش در نتایج', 'weight' => 20, 'score' => 0 ),
		'links' => array( 'label' => 'پیوندها و ارتباط موضوعی', 'weight' => 15, 'score' => 0 ),
		'expertise' => array( 'label' => 'اعتماد، تازگی و زمینهٔ سفر', 'weight' => 15, 'score' => 0 ),
		'technical' => array( 'label' => 'قابلیت دسترسی و نمایه‌پذیری', 'weight' => 15, 'score' => 0 ),
	);

	// Content coverage (20).
	$sections['content']['score'] += $word_count >= 450 ? 8 : ( $word_count >= 300 ? 6 : ( $word_count >= 150 ? 3 : 0 ) );
	if ( $word_count < 300 ) {
		sa_audit_issue( $issues, $word_count < 150 ? 'red' : 'yellow', 'متن', 'حجم متن کم است', 'این فقط راهنمای تحریریه است؛ تعداد کلمهٔ ایده‌آل از سوی گوگل تعیین نشده. اطلاعات لازم را بر اساس موضوع اضافه کنید، نه برای پرکردن تعداد.' );
	}
	$sections['content']['score'] += $paragraph_count >= 4 ? 5 : ( $paragraph_count >= 2 ? 3 : 0 );
	if ( $paragraph_count < 4 ) {
		sa_audit_issue( $issues, 'yellow', 'متن', 'پاراگراف‌بندی را تقویت کنید', 'پاراگراف‌های معنادار و کوتاه‌تر، همراه با زیرعنوان، خوانایی را بهتر می‌کنند.' );
	}
	$sections['content']['score'] += ( $image_count > 0 && 0 === $missing_alt ) ? 4 : ( $image_count ? 2 : 0 );
	if ( ! $image_count ) {
		sa_audit_issue( $issues, 'yellow', 'رسانه', 'تصویر مرتبط بررسی شود', 'اگر تصویر برای فهم مقصد مفید است، تصویر باکیفیت و متن جایگزین توصیفی اضافه کنید؛ تصویر تزئینی الزام محتوایی نیست.' );
	} elseif ( $missing_alt ) {
		sa_audit_issue( $issues, 'yellow', 'رسانه', 'متن جایگزین تصویر ناقص است', 'برای ' . $missing_alt . ' تصویر، alt توصیفی و دقیق بنویسید؛ از تکرار کلیدواژه در alt خودداری کنید.' );
	}
	$sections['content']['score'] += ( $avg_sentence > 0 && $avg_sentence <= 28 ) ? 3 : ( $avg_sentence <= 38 && $avg_sentence > 0 ? 2 : 0 );
	if ( $avg_sentence > 38 ) {
		sa_audit_issue( $issues, 'yellow', 'نگارش', 'جمله‌ها احتمالاً طولانی‌اند', 'میانگین تقریبی ' . $avg_sentence . ' واژه در هر جمله؛ چند جمله را کوتاه‌تر کنید و معنا را حفظ کنید.' );
	}

	// Keyword checks (15): deliberately soft guidance, not a ranking formula.
	if ( $focus ) {
		$sections['keywords']['score'] += 3;
		if ( $title_has_keyword ) {
			$sections['keywords']['score'] += 4;
		} else {
			sa_audit_issue( $issues, 'yellow', 'کلیدواژه', 'ارتباط عنوان با عبارت هدف کم است', 'اگر با نیت صفحه سازگار است، عبارت اصلی یا صورت طبیعی آن را در عنوان بیاورید.' );
		}
		if ( $intro_has_keyword ) {
			$sections['keywords']['score'] += 3;
		} else {
			sa_audit_issue( $issues, 'yellow', 'کلیدواژه', 'موضوع در آغاز متن روشن نیست', 'در مقدمه، موضوع اصلی را طبیعی و بدون تکرار مصنوعی معرفی کنید.' );
		}
		if ( $heading_has_keyword ) {
			$sections['keywords']['score'] += 2;
		} else {
			sa_audit_issue( $issues, 'yellow', 'کلیدواژه', 'ارتباط زیرعنوان‌ها را بررسی کنید', 'اگر مفید است، یکی از زیرعنوان‌ها را با پرسش/موضوع واقعی کاربر هماهنگ کنید.' );
		}
		if ( $density >= 0.3 && $density <= 2.5 ) {
			$sections['keywords']['score'] += 3;
		} elseif ( $density > 2.5 ) {
			$sections['keywords']['score'] += 1;
			sa_audit_issue( $issues, 'red', 'کلیدواژه', 'احتمال تکرار بیش از اندازه', 'چگالی تقریبی ' . $density . '% است. عبارت را فقط جایی نگه دارید که به خواننده کمک می‌کند؛ چگالی هدف رسمی گوگل نیست.' );
		} else {
			sa_audit_issue( $issues, 'yellow', 'کلیدواژه', 'عبارت هدف کم یا نامشخص است', 'کلیدواژهٔ کانونی را در جعبهٔ سئو تعیین کنید یا مطمئن شوید موضوع با واژه‌های طبیعی و مترادف‌ها پوشش داده شده.' );
		}
	} else {
		sa_audit_issue( $issues, 'yellow', 'کلیدواژه', 'عبارت هدف مشخص نشده', 'عبارت کانونی را در فیلد سئوی سایت وارد کنید. پیشنهادهای پایین صرفاً از خود متن استخراج شده‌اند، نه از حجم جست‌وجو.' );
	}

	// Structure / snippet (20).
	$sections['structure']['score'] += ( $title_length >= 20 && $title_length <= 65 ) ? 7 : ( $title_length > 0 ? 4 : 0 );
	if ( $title_length < 20 || $title_length > 65 ) {
		sa_audit_issue( $issues, 'yellow', 'ساختار', 'عنوان را از نظر وضوح و طول بازبینی کنید', 'طول عنوان فعلی ' . $title_length . ' نویسه است؛ این بازه فقط راهنمای نمایشی است و گوگل طول ثابت تضمین‌شده ندارد.' );
	}
	$sections['structure']['score'] += ( $description_length >= 70 && $description_length <= 160 ) ? 7 : ( $description_length > 0 ? 4 : 0 );
	if ( ! $description_length ) {
		sa_audit_issue( $issues, 'red', 'ساختار', 'توضیحات متا/چکیده خالی است', 'چکیدهٔ توصیفی بنویسید. گوگل ممکن است بسته به جست‌وجو متن دیگری را در نتیجه نمایش دهد.' );
	} elseif ( $description_length < 70 || $description_length > 160 ) {
		sa_audit_issue( $issues, 'yellow', 'ساختار', 'توضیحات متا را بازبینی کنید', 'طول فعلی ' . $description_length . ' نویسه است؛ توصیف دقیق و مفید بر رعایت یک شمارش قطعی اولویت دارد.' );
	}
	$sections['structure']['score'] += $h2_count >= 3 ? 4 : ( $h2_count > 0 ? 2 : 0 );
	if ( $word_count >= 300 && $h2_count < 2 ) {
		sa_audit_issue( $issues, 'yellow', 'ساختار', 'زیرعنوان‌های توصیفی کم است', 'برای متن بلند، بخش‌بندی با H2های مشخص و متناسب با پرسش‌های مخاطب را در نظر بگیرید.' );
	}
	$sections['structure']['score'] += ( $h1_count <= 1 ) ? 2 : 0;
	if ( $h1_count > 1 ) {
		sa_audit_issue( $issues, 'yellow', 'ساختار', 'چند H1 در بدنه پیدا شد', 'عنوان اصلی معمولاً توسط قالب تولید می‌شود؛ H1 تکراری داخل متن را به H2 تبدیل کنید. برای وجود دقیق یک H1 در خروجی باید صفحهٔ عمومی بررسی شود.' );
	}

	// Link graph (15).
	$sections['links']['score'] += $internal >= 2 ? 8 : ( $internal === 1 ? 5 : 0 );
	if ( $internal < 2 ) {
		sa_audit_issue( $issues, 'yellow', 'پیوند داخلی', 'پیوندهای داخلی مرتبط کم است', 'به صفحات واقعاً مرتبطِ استان، شهر، مقصد یا راهنمای تکمیلی لینک دهید؛ لینک‌سازی تصنعی لازم نیست.' );
	}
	$sections['links']['score'] += 4 - min( 4, $broken_internal * 2 );
	if ( $broken_internal ) {
		sa_audit_issue( $issues, 'red', 'پیوند داخلی', 'پیوند داخلی به محتوای منتشرنشده شناسایی شد', 'حداقل ' . $broken_internal . ' مقصد داخلی منتشر نشده است؛ URL یا وضعیت مقصد را بررسی کنید.' );
	}
	$sections['links']['score'] += ( $anchor_count > 0 && 0 === $empty_anchors ) ? 3 : ( $anchor_count ? 1 : 0 );
	if ( $empty_anchors ) {
		sa_audit_issue( $issues, 'yellow', 'پیوند', 'متن بعضی پیوندها خالی است', 'متن پیوند باید مقصد را برای کاربر و فناوری‌های کمکی روشن کند.' );
	}
	if ( $external && count( $external_hosts ) < 2 ) {
		sa_audit_issue( $issues, 'yellow', 'منابع', 'تنوع منابع خارجی محدود است', 'در صورت اتکا به داده‌های بیرونی، منبع اولیه یا رسمی و تاریخ مشاهده را ذکر کنید؛ تعدد لینک به‌تنهایی اعتبار نمی‌آورد.' );
	}

	// Trust and travel-specific completeness (15).
	$trusted_domains = 0;
	foreach ( array_keys( $external_hosts ) as $host ) {
		if ( preg_match( '/(?:^|\.)(?:gov|edu|ac\.ir|gov\.ir|edu\.ir|who\.int|unesco\.org)$/i', $host ) ) {
			$trusted_domains++;
		}
	}
	$source_notes = $post_id ? trim( (string) get_post_meta( $post_id, 'sa_sources', true ) ) : '';
	$source_hosts = array();
	$source_urls = array();
	if ( $source_notes && preg_match_all( '#https?://[^\s|<>]+#i', $source_notes, $source_matches ) ) {
		foreach ( $source_matches[0] as $source_url ) {
			$source_url = rtrim( $source_url, '.,;،؛)' );
			if ( wp_http_validate_url( $source_url ) ) {
				$source_urls[] = $source_url;
				$source_host = strtolower( (string) wp_parse_url( $source_url, PHP_URL_HOST ) );
				if ( $source_host ) {
					$source_hosts[ preg_replace( '/^www\\./', '', $source_host ) ] = true;
					if ( preg_match( '/(?:^|\.)(?:gov|edu|ac\.ir|gov\.ir|edu\.ir|who\.int|unesco\.org)$/i', $source_host ) ) {
						$trusted_domains++;
					}
				}
			}
		}
	}
	$source_hosts = array_unique( array_merge( array_keys( $external_hosts ), array_keys( $source_hosts ) ) );
	$documented_source = ! empty( $source_urls );
	$sections['expertise']['score'] += $documented_source ? 6 : ( $trusted_domains ? 6 : ( $external ? 3 : 0 ) );
	if ( ! $external && ! $documented_source ) {
		sa_audit_issue( $issues, 'yellow', 'منابع', 'منبع بیرونی ثبت نشده', 'برای ادعاهای قابل‌راستی‌آزمایی مانند ساعات، قیمت یا آمار، URL منبع اولیه را در متن یا فیلد «منابع» قالب ثبت کنید.' );
	} elseif ( $source_notes && ! $documented_source ) {
		sa_audit_issue( $issues, 'yellow', 'منابع', 'نشانی قابل‌بررسی در فیلد منابع پیدا نشد', 'قالب منابع سایت برای هر منبع URL کامل با https:// می‌پذیرد؛ عنوان منبع به‌تنهایی برای ارزیابی کافی نیست.' );
	}
	if ( $external ) {
		sa_audit_issue( $issues, 'info', 'پیوندهای خارجی', 'وضعیت زندهٔ URLهای بیرونی بررسی نشد', 'این نسخه فقط تعداد، دامنه و پیوندهای داخلی شناخته‌شده را تحلیل می‌کند؛ برای تشخیص 404 بیرونی، URL را دستی بازبینی کنید.' );
	}
	$verified_date = $post_id ? get_post_meta( $post_id, 'sa_last_verified_date', true ) : '';
	if ( ! $verified_date && $post_id ) {
		$verified_date = get_post_meta( $post_id, 'sa_facts_checked', true );
	}
	$last_modified = $verified_date && strtotime( $verified_date ) ? strtotime( $verified_date ) : ( $post_id ? get_post_modified_time( 'U', true, $post_id ) : 0 );
	$age_days = $last_modified ? (int) floor( ( time() - $last_modified ) / DAY_IN_SECONDS ) : 9999;
	$sections['expertise']['score'] += $age_days <= 365 ? 3 : ( $post_id ? 1 : 0 );
	if ( $post_id && $age_days > 365 ) {
		sa_audit_issue( $issues, 'yellow', 'تازگی اطلاعات', 'اطلاعات سفر را بازبینی کنید', 'آخرین راستی‌آزمایی ثبت‌شده یا ویرایش بیش از یک سال پیش است؛ زمان بازدید، هزینه، مسیر و شرایط دسترسی ممکن است تغییر کرده باشد.' );
	}
	$sections['expertise']['score'] += ( $excerpt || $meta_description ) ? 2 : 0;
	$post_type = $post_id ? get_post_type( $post_id ) : ( isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post' );
	$travel_fields = array(
		'province' => array( 'sa_province_center_city_id', 'sa_province_climate', 'sa_province_area' ),
		'city' => array( 'sa_province_id', 'sa_city_elevation', 'sa_access_road', 'sa_access_rail', 'taxonomy:travel_season' ),
		'attraction' => array( 'sa_city_id', 'sa_address', 'sa_opening_hours', 'sa_visit_duration', 'taxonomy:travel_season' ),
		'local_food' => array( 'sa_city_id', 'sa_main_ingredients', 'sa_serving_method' ),
		'souvenir' => array( 'sa_city_id', 'sa_purchase_location' ),
		'travel_route' => array( 'sa_city_ids', 'sa_route_distance', 'taxonomy:travel_duration', 'taxonomy:travel_season' ),
	);
	$checks = isset( $travel_fields[ $post_type ] ) ? $travel_fields[ $post_type ] : array();
	$populated = 0;
	foreach ( $checks as $key ) {
		if ( 0 === strpos( $key, 'taxonomy:' ) ) {
			$terms = ( $post_id && taxonomy_exists( substr( $key, 9 ) ) ) ? wp_get_post_terms( $post_id, substr( $key, 9 ), array( 'fields' => 'ids' ) ) : array();
			$value = is_wp_error( $terms ) ? array() : $terms;
		} else {
			$value = $post_id ? get_post_meta( $post_id, $key, true ) : '';
		}
		if ( is_array( $value ) ? ! empty( $value ) : ( '' !== (string) $value && '0' !== (string) $value ) ) {
			$populated++;
		}
	}
	$sections['expertise']['score'] += $checks ? (int) round( 4 * $populated / count( $checks ) ) : 4;
	if ( $checks && $populated < count( $checks ) ) {
		sa_audit_issue( $issues, 'yellow', 'اطلاعات گردشگری', 'جزئیات کاربردی مقصد را کامل کنید', 'بر اساس نوع محتوا، اطلاعات قابل استفاده مانند دسترسی، فصل مناسب، نشانی، ساعات، مدت بازدید یا ارتباط شهر/استان را بررسی کنید.' );
	}

	// Indexing and technical state.
	$noindex = false;
	$status = $post_id ? get_post_status( $post_id ) : 'draft';
	if ( $post_id ) {
		$rank_math_robots = get_post_meta( $post_id, 'rank_math_robots', true );
		$rank_noindex = is_array( $rank_math_robots ) ? in_array( 'noindex', $rank_math_robots, true ) : ( false !== strpos( strtolower( (string) $rank_math_robots ), 'noindex' ) );
		$noindex = ( '1' === get_post_meta( $post_id, 'sa_noindex', true ) )
			|| ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) )
			|| $rank_noindex
			|| ( '1' === (string) get_post_meta( $post_id, '_aioseo_robots_noindex', true ) );
	}
	$public = (bool) get_option( 'blog_public', 1 );
	$sections['technical']['score'] += ( 'publish' === $status ) ? 5 : 0;
	$sections['technical']['score'] += ( ! $noindex && $public ) ? 5 : 0;
	$canonical = $post_id ? get_post_meta( $post_id, 'sa_canonical_url', true ) : '';
	$sections['technical']['score'] += ( ! $canonical || wp_http_validate_url( $canonical ) ) ? 5 : 2;
	if ( 'publish' !== $status ) {
		sa_audit_issue( $issues, 'yellow', 'نمایه‌پذیری', 'محتوا هنوز منتشر نشده', 'پیش‌نویس بودن به معنی خطای محتوا نیست؛ این صفحه تا انتشار برای کاربران و خزنده‌ها در دسترس عمومی نخواهد بود.' );
	}
	if ( $noindex || ! $public ) {
		sa_audit_issue( $issues, 'red', 'نمایه‌پذیری', 'تنظیم noindex یا عدم نمایش موتورهای جست‌وجو فعال است', 'تنظیم این نوشته و گزینهٔ «از موتورهای جست‌وجو درخواست نکنید» در وردپرس را بررسی کنید.' );
	}
	if ( $canonical && ! wp_http_validate_url( $canonical ) ) {
		sa_audit_issue( $issues, 'red', 'فنی', 'نشانی canonical نامعتبر است', 'نشانی canonical باید یک URL کامل و معتبر باشد و ترجیحاً به نسخهٔ اصلی همین محتوا اشاره کند.' );
	}

	$sections['content']['score'] = min( 20, $sections['content']['score'] );
	$sections['keywords']['score'] = min( 15, $sections['keywords']['score'] );
	$sections['structure']['score'] = min( 20, $sections['structure']['score'] );
	$sections['links']['score'] = max( 0, min( 15, $sections['links']['score'] ) );
	$sections['expertise']['score'] = min( 15, $sections['expertise']['score'] );
	$sections['technical']['score'] = min( 15, $sections['technical']['score'] );
	$score = 0;
	foreach ( $sections as $section ) {
		$score += (int) $section['score'];
	}

	$overlaps = $fast ? array() : sa_audit_find_overlap( $post_id, $plain );
	if ( $overlaps ) {
		sa_audit_issue( $issues, 'yellow', 'هم‌پوشانی داخلی', 'عبارت‌های مشابه با محتوای دیگر سایت', 'این ابزار فقط تطابق دقیق جمله‌های بلند را در حداکثر ۳۵ نوشتهٔ تازهٔ هم‌نوع پیدا می‌کند؛ تطابق می‌تواند نقل‌قول یا متن قالبی باشد و اثبات سرقت ادبی نیست.' );
	}
	foreach ( $spelling_flags as $flag ) {
		sa_audit_issue( $issues, 'yellow', 'نگارش فارسی', 'بازبینی نگارشی', $flag );
	}
	$suggestions = sa_audit_keyword_suggestions( $tokens, $title );
	if ( ! $focus && $suggestions ) {
		$issues[] = array( 'level' => 'info', 'area' => 'پیشنهاد عبارت', 'title' => 'عبارت‌های پیشنهادی از متن', 'detail' => implode( '، ', $suggestions ) . ' — این ابزار دادهٔ حجم جست‌وجو یا رقابت کلمات را ندارد.' );
	}
	$related_suggestions = ( $fast || $internal >= 2 ) ? array() : sa_audit_internal_suggestions( $post_id, $content );
	usort(
		$issues,
		function ( $left, $right ) {
			$rank = array( 'red' => 0, 'yellow' => 1, 'green' => 2, 'info' => 3 );
			$l = isset( $rank[ $left['level'] ] ) ? $rank[ $left['level'] ] : 4;
			$r = isset( $rank[ $right['level'] ] ) ? $rank[ $right['level'] ] : 4;
			return $l - $r;
		}
	);

	return array(
		'score' => max( 0, min( 100, $score ) ),
		'sections' => $sections,
		'metrics' => array(
			'words' => $word_count,
			'characters' => $char_count,
			'paragraphs' => $paragraph_count,
			'headings' => (int) $h2_count,
			'keyword' => $focus,
			'density' => $density,
			'internal' => $internal,
			'external' => $external,
			'broken_internal' => $broken_internal,
			'sources' => count( $source_hosts ),
			'images' => (int) $image_count,
			'missing_alt' => $missing_alt,
			'avg_sentence' => $avg_sentence,
			'meta_length' => $description_length,
			'indexability' => ( 'publish' !== $status ) ? 'منتشر نشده' : ( ( $noindex || ! $public ) ? 'noindex / نمایش خاموش' : 'قابل خزش؛ ایندکس تضمین نیست' ),
			'canonical' => $canonical ? 'سفارشی' : 'پیش‌فرض',
			'overlap_count' => count( $overlaps ),
		),
		'issues' => $issues,
		'overlaps' => $overlaps,
		'suggestions' => $suggestions,
		'internal_suggestions' => $related_suggestions,
		'previous_score' => ( $post_id && '' !== get_post_meta( $post_id, '_sa_audit_score', true ) ) ? (int) get_post_meta( $post_id, '_sa_audit_score', true ) : null,
		'saved_previous_score' => ( $post_id && '' !== get_post_meta( $post_id, '_sa_audit_previous_score', true ) ) ? (int) get_post_meta( $post_id, '_sa_audit_previous_score', true ) : null,
		'notes' => array(
			'امتیاز و وزن‌ها شاخص داخلیِ قابل‌توضیح‌اند و رتبه، ترافیک یا ایندکس‌شدن در گوگل را پیش‌بینی نمی‌کنند.',
			'حجم متن و چگالی کلیدواژه معیارهای قطعی گوگل نیستند؛ سودمندی، دقت و کامل‌بودن برای مخاطب اولویت دارد.',
			'بررسی این صفحه، حضور واقعی در فهرست گوگل یا وضعیت URL Inspection را نمی‌سنجد. برای آن Search Console لازم است.',
			'تحلیل نگارشی قاعده‌محور است و غلط‌یاب جامع زبان فارسی محسوب نمی‌شود؛ نتیجه را انسانی بازبینی کنید.',
			'تطابق متن محدود به نوشته‌های همان سایت است؛ کپی از وب و تولیدشدن با هوش مصنوعی به‌طور قابل‌اعتماد قابل‌تشخیص نیست.',
		),
	);
}

/** Current user's editor-side analysis, including unsaved content. */
function sa_audit_ajax_analyze() {
	check_ajax_referer( 'sa_content_audit_editor', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ ویرایش این نوشته را ندارید.' ), 403 );
	}
	if ( ! $post_id && ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ کافی ندارید.' ), 403 );
	}
	$input = array(
		'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
		'content' => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
		'excerpt' => isset( $_POST['excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) ) : '',
		'focus_keyword' => isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '',
		'seo_title' => isset( $_POST['seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['seo_title'] ) ) : '',
		'seo_description' => isset( $_POST['seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['seo_description'] ) ) : '',
		'post_type' => $post_id ? get_post_type( $post_id ) : ( isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'post' ),
	);
	wp_send_json_success( sa_audit_analyze( $input, $post_id ) );
}
/** Scan a page of site content for the aggregate report. */
function sa_audit_ajax_batch() {
	check_ajax_referer( 'sa_content_audit', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ کافی ندارید.' ), 403 );
	}
	$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
	$types = get_post_types( array( 'public' => true ), 'objects' );
	$allowed = array();
	foreach ( $types as $type ) {
		if ( 'attachment' !== $type->name && ! empty( $type->show_ui ) && post_type_supports( $type->name, 'editor' ) && current_user_can( $type->cap->edit_posts ) ) {
			$allowed[] = $type->name;
		}
	}
	if ( ! $allowed ) {
		wp_send_json_success( array( 'rows' => array(), 'offset' => $offset, 'total' => 0, 'done' => true ) );
	}
	$args = array(
		'post_type' => $allowed,
		'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => 40,
		'offset' => $offset,
		'orderby' => 'ID',
		'order' => 'ASC',
		'fields' => 'ids',
		'no_found_rows' => false,
		'suppress_filters' => true,
	);
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}
	$query = new WP_Query( $args );
	$rows = array();
	foreach ( $query->posts as $post_id ) {
		$post = get_post( $post_id );
		$result = sa_audit_analyze(
			array(
				'title' => $post->post_title,
				'content' => $post->post_content,
				'excerpt' => $post->post_excerpt,
				'post_type' => $post->post_type,
			),
			$post_id,
			true
		);
		$rows[] = array(
			'id' => (int) $post_id,
			'title' => get_the_title( $post_id ),
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'type' => $post->post_type,
			'status' => $post->post_status,
			'score' => $result['score'],
			'words' => $result['metrics']['words'],
			'issues' => count( array_filter( $result['issues'], function ( $issue ) { return in_array( $issue['level'], array( 'yellow', 'red' ), true ); } ) ),
		);
	}
	wp_send_json_success( array( 'rows' => $rows, 'offset' => $offset + count( $rows ), 'total' => (int) $query->found_posts, 'done' => $offset + count( $rows ) >= (int) $query->found_posts ) );
}

/** Store a baseline when content is actually saved; editor re-runs remain non-destructive. */
function sa_audit_store_saved_score( $post_id, $post, $update ) {
	if ( ! is_object( $post ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || empty( $type->public ) || 'attachment' === $post->post_type || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$result = sa_audit_analyze(
		array( 'title' => $post->post_title, 'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'post_type' => $post->post_type ),
		$post_id,
		true
	);
	$old_score = get_post_meta( $post_id, '_sa_audit_score', true );
	if ( '' !== $old_score ) {
		update_post_meta( $post_id, '_sa_audit_previous_score', (int) $old_score );
	}
	update_post_meta( $post_id, '_sa_audit_score', (int) $result['score'] );
	update_post_meta( $post_id, '_sa_audit_checked', time() );
}

/** Aggregate dashboard. */
function sa_audit_dashboard() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'شما اجازهٔ دسترسی به این صفحه را ندارید.' );
	}
	echo '<div class="wrap sa-audit-dashboard" dir="rtl">';
	echo '<div class="sa-audit-hero"><div><span class="sa-audit-eyebrow">سرزمین آریان · راهنمای کیفیت تحریریه</span><h1>ممیزی محتوا</h1><p>تحلیل دوبارهٔ نوشته‌ها و موجودیت‌های گردشگری؛ همراه با اولویت‌بندی پیشنهادهای اصلاحی.</p></div><button type="button" class="button button-primary sa-audit-scan-all">شروع بررسی کل محتوا</button></div>';
	echo '<div class="sa-audit-caveat"><strong>توضیح مهم:</strong> درصدها امتیاز داخلی و قابل‌توضیح افزونه‌اند، نه «استاندارد امتیازدهی گوگل». گوگل تعداد کلمه یا چگالی ایده‌آل و ابزار قطعی تشخیص هوش مصنوعی/سرقت ادبی منتشر نکرده است. بررسی ایندکس واقعی نیازمند Search Console است.</div>';
	echo '<div class="sa-audit-progress" hidden><span class="sa-audit-progress-label">آماده‌سازی…</span><div class="sa-audit-progress-track"><span></span></div></div>';
	echo '<div class="sa-audit-summary" hidden></div>';
	echo '<div class="sa-audit-table-wrap"><table class="widefat striped sa-audit-table"><thead><tr><th>محتوا</th><th>نوع</th><th>وضعیت</th><th>واژه</th><th>امتیاز تحریریه</th><th>موارد نیازمند توجه</th></tr></thead><tbody><tr><td colspan="6">برای بررسی همهٔ محتوا، دکمهٔ «شروع بررسی کل محتوا» را بزنید.</td></tr></tbody></table></div>';
	echo '<p class="description sa-audit-footnote">گزارش کل سایت از هر بار اجرای بررسی ساخته می‌شود و برای دقت بیشتر می‌توانید در صفحهٔ ویرایش، تحلیلِ متن ذخیره‌نشده را هم اجرا کنید.</p>';
	echo '</div>';
}
