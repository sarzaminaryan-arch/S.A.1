<?php
/**
 * جایگزین بصری موقت برای نوشته‌های بی‌تصویر شاخص (v2.22.144).
 *
 * چرا این ماژول (ممیزی ۱۴۰۵-۰۸-۲۸ با خواندن مستقیم سایت):
 *   • همهٔ ۴۸۴ شهرستان منتشرشده تصویر شاخص دارند → دستهٔ (ب) صفر است.
 *   • ۵۸ نوشتهٔ منتشرشده شاخص ندارند و هیچ‌کدام هم عکسی در گالری یا محتوا
 *     ندارند (۲ × دیدنی، ۵۶ × راهنمای آموزشی) → همه در دستهٔ (الف).
 *   • ۴۵ نوشته به‌طور صریح شاخص را حذف کرده‌اند (۳۵ دیدنی، ۱۰ راهنما)؛ این‌ها
 *     با `sa_featured_image_suppressed()` محافظت می‌شوند و جایگزین هم نمی‌گیرند.
 *
 * تصمیم فنی: یک **کارت SVG یکتا برای هر صفحه** که با PHP ساخته و در
 * `uploads/sa-fallback/` کش می‌شود. دلیل انتخاب SVG به‌جای PNG/GD:
 *   ۱) نوشتن مستقیم متن فارسی، نیازی به GD، قلم یا شکل‌دهی حروف ندارد
 *      (`sa_og_shape()` در og-card.php لازم نیست) — پس در هاست محدود هم کار می‌کند؛
 *   ۲) حجم کارت چند کیلوبایت است، در حالی که PNG ۱۲۰۰×۶۳۰ با GD روی همین
 *      هاست سنگین و خطاپذیر است؛
 *   ۳) متن در SVG قابل انتخاب/بزرگ‌نمایی است و کنتراست کامل دارد؛
 *   ۴) `width/height` صریح دارد، پس چیدمان صفحه را جابه‌جا نمی‌کند.
 *
 * توجه: `og:image` عمداً دست‌نخورده مانده است. کارت اشتراک‌گذاری از ۲.۲۲.۷۴
 * یک PNG واقعی ۱۲۰۰×۶۳۰ است (`inc/og-card.php`) و شبکه‌های اجتماعی SVG را
 * نمی‌پذیرند؛ عوض کردن آن با SVG پسرفت بود.
 *
 * قاعده‌های سختِ حفظ‌شده:
 *   • `sa_county_featured_figure()` برای شهرستان بی‌شاخص همچنان رشتهٔ خالی
 *     می‌دهد (قرارداد موجود و تست‌شده) — جایگزین شهرداری همهٔ انواع نیست.
 *   • هیچ تصویر شاخصِ واقعی ساخته نمی‌شود و هیچ پیوستی به نوشته وصل نمی‌گردد؛
 *     فقط خروجی نمایشی و og:image عوض می‌شود.
 *   • نوشته‌ای که شاخصش عمداً حذف شده، جایگزین هم نمی‌گیرد.
 *   • قانون «سه عکس» معلق است و این ماژول آن را تغییر نمی‌دهد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا این نوشته باید کارت جایگزین بگیرد؟
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return bool
 */
function sa_fallback_card_applies( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! function_exists( 'get_post_type' ) ) {
		return false;
	}
	if ( ! in_array( (string) get_post_type( $post_id ), array( 'post', 'attraction' ), true ) ) {
		return false;
	}
	if ( 'publish' !== (string) get_post_field( 'post_status', $post_id ) ) {
		return false;
	}
	if ( function_exists( 'sa_featured_image_suppressed' ) && sa_featured_image_suppressed( $post_id ) ) {
		return false;
	}
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb > 0 && ( ! function_exists( 'sa_media_attachment_missing' ) || ! sa_media_attachment_missing( $thumb ) ) ) {
		return false; // تصویر شاخص واقعی دارد (فقط فایلِ گم‌شده کارت می‌گیرد).
	}
	return true;
}

/**
 * پوشهٔ کش کارت‌ها (با محافظت از نوشتن بیرون از uploads).
 *
 * @return string مسیر مطلق با اسلش پایانی، یا رشتهٔ خالی.
 */
function sa_fallback_card_dir() {
	if ( ! function_exists( 'wp_upload_dir' ) ) {
		return '';
	}
	$uploads = wp_upload_dir();
	if ( empty( $uploads['basedir'] ) ) {
		return '';
	}
	$dir = trailingslashit( $uploads['basedir'] ) . 'sa-fallback';
	if ( ! is_dir( $dir ) ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
	}
	return is_writable( $dir ) ? trailingslashit( $dir ) : '';
}

/**
 * متن کارت: عنوان، گونهٔ صفحه و نام استان.
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return array{title:string,kicker:string,place:string}
 */
function sa_fallback_card_text( $post_id ) {
	$title = trim( (string) get_the_title( $post_id ) );
	if ( '' === $title ) {
		$title = 'سرزمین آریان';
	}
	$kicker = 'راهنمای سفر';
	if ( 'attraction' === get_post_type( $post_id ) && function_exists( 'sa_entity_label' ) ) {
		$label = (string) sa_entity_label( 'attraction' );
		if ( '' !== $label ) {
			$kicker = $label;
		}
	}
	$place = '';
	$prov  = function_exists( 'sa_get_parent' ) ? sa_get_parent( $post_id, 'province' ) : null;
	if ( $prov && isset( $prov->post_title ) ) {
		$place = trim( (string) $prov->post_title );
	}
	if ( '' === $place ) {
		$place = 'ایران';
	}
	return array( 'title' => $title, 'kicker' => $kicker, 'place' => $place );
}

/**
 * نام فایل کش از متن کارت — با تغییر عنوان، کارت تازه ساخته می‌شود.
 *
 * @param array $parts خروجی sa_fallback_card_text.
 * @return string
 */
function sa_fallback_card_filename( array $parts ) {
	return 'card-' . substr( md5( wp_json_encode( $parts ) ), 0, 20 ) . '.svg';
}

/**
 * شکستن عنوان به حداکثر سه سطر کوتاه (بدون GD و بدون اندازه‌گیری قلم).
 *
 * @param string $text  متن.
 * @param int    $limit بیشینهٔ نویسه در هر سطر.
 * @param int    $lines بیشینهٔ سطر.
 * @return string[]
 */
function sa_fallback_card_lines( $text, $limit = 34, $lines = 3 ) {
	$words = preg_split( '/\s+/u', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $words ) {
		return array( 'سرزمین آریان' );
	}
	$out   = array();
	$line  = '';
	foreach ( $words as $word ) {
		$try = '' === $line ? $word : $line . ' ' . $word;
		if ( mb_strlen( $try, 'UTF-8' ) > $limit && '' !== $line ) {
			$out[] = $line;
			$line  = $word;
			if ( count( $out ) >= $lines ) {
				break;
			}
			continue;
		}
		$line = $try;
	}
	if ( count( $out ) < $lines && '' !== $line ) {
		$out[] = $line;
	}
	if ( mb_strlen( implode( ' ', $out ), 'UTF-8' ) < mb_strlen( trim( (string) $text ), 'UTF-8' ) ) {
		$last = array_pop( $out );
		$out[] = rtrim( (string) $last, " \t\n\r\0\x0B" ) . '…';
	}
	return $out;
}

/**
 * ساخت متن SVG کارت (رنگ‌های نیوی + کهربایی سایت).
 *
 * @param array $parts خروجی sa_fallback_card_text.
 * @return string
 */
function sa_fallback_card_svg( array $parts ) {
	$lines  = sa_fallback_card_lines( $parts['title'] );
	$esc    = static function ( $v ) {
		return htmlspecialchars( (string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	};
	$height = 630;
	$npml   = 63;
	$start  = 250;
	$text   = '';
	foreach ( $lines as $i => $line ) {
		$y     = $start + ( $i * $npml );
		$size  = ( 0 === $i ) ? 56 : 48;
		$text .= '<text x="1110" y="' . $y . '" text-anchor="end" font-size="' . $size . '" font-weight="700" fill="#f7fbff">' . $esc( $line ) . '</text>' . "\n";
	}
	$svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="' . $height . '" viewBox="0 0 1200 ' . $height . '" role="img" aria-label="' . $esc( $parts['title'] ) . '">' . "\n"
		. '<defs><linearGradient id="saBg" x1="0" y1="0" x2="1" y2="1">'
		. '<stop offset="0" stop-color="#0b2a4a"/><stop offset="0.55" stop-color="#123f73"/><stop offset="1" stop-color="#18558f"/>'
		. '</linearGradient></defs>' . "\n"
		. '<rect width="1200" height="' . $height . '" rx="0" fill="url(#saBg)"/>' . "\n"
		. '<g font-family="Vazirmatn, Tahoma, DejaVu Sans, sans-serif" direction="rtl">' . "\n"
		. '<rect x="1110" y="150" width="90" height="8" rx="4" fill="#f6c453"/>' . "\n"
		. '<text x="1110" y="205" text-anchor="end" font-size="34" fill="#f6c453">' . $esc( $parts['kicker'] ) . '</text>' . "\n"
		. $text
		. '<text x="1110" y="560" text-anchor="end" font-size="32" fill="#bdd8f1">' . $esc( $parts['place'] ) . '</text>' . "\n"
		. '<text x="1110" y="600" text-anchor="end" font-size="26" fill="#8eb9e3">سرزمین آریان</text>' . "\n"
		. '</g>' . "\n"
		. '</svg>' . "\n";
	return $svg;
}

/**
 * مسیر/URL کارت؛ یک‌بار ساخته و کش می‌شود.
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return string URL مطلق یا رشتهٔ خالی.
 */
function sa_fallback_card_url( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! sa_fallback_card_applies( $post_id ) ) {
		return '';
	}
	$dir = sa_fallback_card_dir();
	if ( '' === $dir ) {
		return '';
	}
	$uploads = wp_upload_dir();
	$parts   = sa_fallback_card_text( $post_id );
	$name    = sa_fallback_card_filename( $parts );
	$path    = $dir . $name;
	if ( ! is_file( $path ) ) {
		$svg = sa_fallback_card_svg( $parts );
		if ( false === @file_put_contents( $path, $svg ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return '';
		}
	}
	return trailingslashit( (string) $uploads['baseurl'] ) . 'sa-fallback/' . $name;
}

/**
 * نشانه‌گذاری کارت بالای صفحه (فقط برای همان نوشتهٔ بی‌شاخص).
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return string HTML یا رشتهٔ خالی.
 */
function sa_fallback_card_html( $post_id ) {
	$post_id = absint( $post_id );
	$url     = sa_fallback_card_url( $post_id );
	if ( '' === $url ) {
		return '';
	}
	$parts = sa_fallback_card_text( $post_id );
	$alt   = $parts['title'] . ' — ' . $parts['kicker'] . '، ' . $parts['place'];
	return '<figure class="sa-fallback-card">'
		. '<img class="sa-fallback-card__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"'
		. ' width="1200" height="630" loading="eager" fetchpriority="low" decoding="async" />'
		. '<figcaption class="sa-fallback-card__caption">' . esc_html( 'تصویر موقت: برای این صفحه هنوز عکس ثبت نشده است.' ) . '</figcaption>'
		. '</figure>';
}
