<?php
/**
 * نمایش تصاویر — کلید سراسریِ «تصویر شاخص» و جای‌گزینِ تصویرهای گمشده (v2.15.0).
 *
 * پس از پاک‌شدن فایل‌های پوشهٔ رسانه، هر جای سایت که تصویر شاخص را نشان
 * می‌داد یک تصویر شکسته بود. دو کار انجام می‌شود:
 *
 * 1) کلید «نمایش تصویر شاخص» در سفارشی‌ساز (پیش‌فرض: خاموش). تا وقتی خاموش
 *    است، هیچ‌کدام از محل‌های نمایش تصویر شاخص — کارت‌های صفحهٔ اول،
 *    آرشیوها، هیروی استان/شهرستان/دیدنی، صفحه‌های برگه و مقاله — تصویر
 *    نشان نمی‌دهند؛ حتی اگر بعداً روشن شود، تصویری که فایلش روی سرور نیست
 *    نمایش داده نمی‌شود (قالب به فایل زندهٔ روی سرور نگاه می‌کند، نه فقط به
 *    شناسهٔ پیوست).
 * 2) کلید «تصویرهای گمشدهٔ داخل متن» (پیش‌فرض: روشن). تصویرهای داخل متن که
 *    فایلشان روی سرور نیست، به کادر متنی ساده تبدیل می‌شوند تا بازدیدکننده
 *    به‌جای آیکون تصویر شکسته، یک کادر آرام ببیند. فایل هرگز دست‌کاری
 *    نمی‌شود؛ فقط خروجیِ نمایش در سایت عوض می‌شود و در پیشخوان/ویرایشگر و
 *    REST چیزی تغییر نمی‌کند (وگرنه محتوای ذخیره‌شده خراب می‌شد).
 *
 * بازگرداندن فایل‌ها کار ابزار «سرزمین آریان → بازیابی تصاویر» است
 * (inc/media-restore.php).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا تصویر شاخص در سایت نمایش داده شود؟
 *
 * @param string $context محل نمایش (برای فیلترها؛ مثلاً guide|hero|card).
 * @return bool
 */
function sa_featured_images_visible( $context = '' ) {
	$show = (bool) get_theme_mod( 'sa_show_featured_images', false );
	/**
	 * نمایش تصویر شاخص در سایت.
	 *
	 * @param bool   $show    وضعیت.
	 * @param string $context محل.
	 */
	return (bool) apply_filters( 'sa_featured_images_visible', $show, $context );
}

/**
 * آیا رکورد همین مقاله به‌طور صریح تصویر شاخص را حذف کرده است؟
 *
 * رکورد قالب، حذف را حتی پیش از اجرای ابزار در پیشخوان اعمال می‌کند؛ متای
 * نوشته نیز پس از import همان تصمیم را روی خود نوشته پایدار نگه می‌دارد.
 * هیچ قاعده‌ای بر اساس دسته یا همهٔ مقاله‌ها اعمال نمی‌شود.
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return bool
 */
function sa_featured_image_suppressed( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return false;
	}
	$is_ready_guide_post = ! function_exists( 'get_post_type' ) || 'post' === get_post_type( $post_id );
	if ( $is_ready_guide_post && function_exists( 'sa_ready_articles_data' ) && function_exists( 'get_post_field' ) ) {
		$slug = (string) get_post_field( 'post_name', $post_id );
		if ( '' !== $slug ) {
			static $suppressed_slugs = null;
			if ( null === $suppressed_slugs ) {
				$suppressed_slugs = array();
				$records          = sa_ready_articles_data();
				if ( is_array( $records ) ) {
					foreach ( $records as $item ) {
						if ( is_array( $item ) && ! empty( $item['remove_featured_image'] ) && ! empty( $item['slug'] ) ) {
							$suppressed_slugs[ (string) $item['slug'] ] = true;
						}
					}
				}
			}
			if ( isset( $suppressed_slugs[ $slug ] ) ) {
				return true;
			}
		}
	}
	return function_exists( 'get_post_meta' ) && '1' === (string) get_post_meta( $post_id, '_sa_ready_remove_featured_image', true );
}

/**
 * آیا برای این نوشته تصویر شاخصِ قابل نمایش وجود دارد؟
 *
 * سه شرط: کلید نمایش روشن باشد، نوشته تصویر شاخص داشته باشد، و فایل آن
 * تصویر واقعاً روی سرور باشد (چیزی که پس از پاک‌شدن فایل‌ها دیگر صادق نیست).
 *
 * @param int    $post_id شناسهٔ نوشته (۰ = نوشتهٔ جاری).
 * @param string $context محل نمایش.
 * @return bool
 */
function sa_featured_image_available( $post_id = 0, $context = '' ) {
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( ! $post_id || sa_featured_image_suppressed( $post_id ) || ! sa_featured_images_visible( $context ) ) {
		return false;
	}
	if ( ! has_post_thumbnail( $post_id ) ) {
		return false;
	}
	$attachment_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $attachment_id ) {
		return false;
	}
	if ( function_exists( 'sa_media_attachment_missing' ) && sa_media_attachment_missing( $attachment_id ) ) {
		return false; // فایل روی سرور نیست.
	}
	return true;
}

/**
 * نگهبانِ سراسری: خروجیِ هر تصویر شاخص در بخش عمومی سایت، تا وقتی کلید خاموش است.
 *
 * قالب‌ها خودشان هم بررسی می‌کنند؛ این فیلتر برای قالب‌های مادر یا کدهای
 * دیگری است که مستقیم `the_post_thumbnail()` صدا می‌زنند. پیشخوان (فهرست
 * نوشته‌ها، ابزارها) دست‌نخورده می‌ماند تا مالک وضعیت تصویرها را ببیند.
 *
 * @param string $html    خروجی تصویر.
 * @param int    $post_id شناسهٔ نوشتهٔ خود تصویر (در نبودش نوشتهٔ جاری).
 * @return string
 */
function sa_featured_image_guard( $html, $post_id = 0 ) {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || is_feed() ) {
		return $html;
	}
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( $post_id && sa_featured_image_suppressed( $post_id ) ) {
		return '';
	}
	if ( ! sa_featured_images_visible( 'core' ) ) {
		return '';
	}
	if ( $post_id && function_exists( 'sa_media_attachment_missing' ) ) {
		$attachment_id = (int) get_post_thumbnail_id( $post_id );
		if ( $attachment_id && sa_media_attachment_missing( $attachment_id ) ) {
			return '';
		}
	}
	return $html;
}
add_filter( 'post_thumbnail_html', 'sa_featured_image_guard', 20, 2 );

/**
 * آیا جای‌گزینی تصویرهای گمشدهٔ داخل متن فعال است؟
 *
 * @return bool
 */
function sa_hide_missing_images() {
	$hide = (bool) get_theme_mod( 'sa_hide_missing_images', true );
	/**
	 * جای‌گزینی تصویرهای گمشدهٔ داخل متن.
	 *
	 * @param bool $hide وضعیت.
	 */
	return (bool) apply_filters( 'sa_hide_missing_images', $hide );
}

/**
 * متن کادرِ جای‌گزین تصویر گمشده.
 *
 * @return string
 */
function sa_missing_image_label() {
	$label = trim( (string) get_theme_mod( 'sa_missing_image_label', 'تصویر این بخش در دست بازبینی است' ) );
	if ( '' === $label ) {
		$label = 'تصویر این بخش در دست بازبینی است';
	}
	/**
	 * متن کادر جای‌گزین.
	 *
	 * @param string $label متن.
	 */
	return (string) apply_filters( 'sa_missing_image_label', $label );
}

/**
 * آیا فایلِ این نشانی تصویری روی سرور نیست؟ (با کش درون‌درخواستی)
 *
 * نشانی‌های بیرونی ناشناخته‌اند، نه گمشده.
 *
 * @param string $url نشانی.
 * @return bool
 */
function sa_content_image_missing( $url ) {
	static $cache = array();
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return false;
	}
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$cache[ $url ] = false;
	if ( 0 === strpos( $url, 'data:' ) ) {
		return false;
	}
	if ( ! function_exists( 'sa_media_local_path' ) ) {
		return false;
	}
	$path = sa_media_local_path( $url );
	if ( '' === $path ) {
		return false; // بیرونی.
	}
	$missing = ! file_exists( $path );
	if ( $missing && function_exists( 'sa_media_find_variant' ) ) {
		$missing = '' === sa_media_find_variant( $path );
	}
	$cache[ $url ] = $missing;
	return $missing;
}

/**
 * جای‌گزینی تصویرهای گمشدهٔ داخل متن با کادر متنی.
 *
 * @param string $content محتوای نوشته.
 * @return string
 */
function sa_hide_missing_content_images( $content ) {
	$content = (string) $content;
	if ( '' === $content || false === stripos( $content, '<img' ) ) {
		return $content;
	}
	// در پیشخوان، REST (ویرایشگر بلوک) و AJAX هرگز دخالت نمی‌کنیم؛ وگرنه
	// محتوای ذخیره‌شده دست‌کاری می‌شد.
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
		return $content;
	}
	if ( ! sa_hide_missing_images() ) {
		return $content;
	}
	$label = sa_missing_image_label();
	$html  = (string) preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $matches ) use ( $label ) {
			$tag = (string) $matches[0];
			$src = '';
			foreach ( array( 'src', 'data-src' ) as $attr ) {
				if ( preg_match( '/\b' . $attr . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
					$src = (string) $m[2];
					break;
				}
			}
			if ( '' === $src || ! sa_content_image_missing( $src ) ) {
				return $tag;
			}
			$alt = '';
			if ( preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
				$alt = (string) $m[2];
			}
			return sprintf(
				'<span class="sa-media-missing"%s><span class="sa-media-missing__text">%s</span></span>',
				'' !== $alt ? ' title="' . esc_attr( wp_strip_all_tags( $alt ) ) . '"' : '',
				esc_html( $label )
			);
		},
		$content
	);
	return $html;
}
add_filter( 'the_content', 'sa_hide_missing_content_images', 12 );

/**
 * نمایش یک تصویر شاخص با بررسی کامل (کلید + وجود فایل).
 *
 * @param string               $size اندازه.
 * @param array<string,string> $attr ویژگی‌های تصویر.
 * @param int                  $post_id شناسهٔ نوشته.
 * @return string HTML ('' = نمایش نده).
 */
function sa_get_featured_image( $size = 'post-thumbnail', $attr = array(), $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : (int) get_the_ID();
	if ( ! sa_featured_image_available( $post_id, 'helper' ) ) {
		return '';
	}
	return (string) get_the_post_thumbnail( $post_id, $size, $attr );
}
