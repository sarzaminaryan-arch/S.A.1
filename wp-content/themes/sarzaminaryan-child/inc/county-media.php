<?php
/**
 * تصاویر مجاز مکان‌ها برای صفحهٔ شهرستان (v2.22.98)
 *
 * دستور مالک: «فکری برای گذاشتن تصویر مجاز از مکان‌های جذابی که تعریفشان را
 * می‌کنیم … تصویر را در صورت مجوز نام بردن عکاس، کاملاً قانونی، در سایز و
 * فرمت مناسب بیاوریم و در سایت بگذاریم؛ یک تصویر مربع سمت راست و نوشتهٔ
 * توضیح و هشدار کنارش؛ و بخش گالری سه مکان تفریحی با کادر ثابت و هایلایت
 * توضیحات زیرش.»
 *
 * این فایل دو کار می‌کند و هیچ‌کدام بدون داده انجام نمی‌شود:
 *
 * ۱) **واردکردن تصویر با مجوز** (`/photos/import` در پل ایجنت).
 *    خودِ سایت فایل را از دامنهٔ مجاز می‌گیرد (سندباکس ایجنت به اینترنت
 *    تصویری دسترسی ندارد)، مجوز را *پیش از* ذخیره بررسی می‌کند و پرووننس
 *    (پدیدآور، مجوز، نشانی اصل، تاریخ) را روی پیوست می‌نویسد. مجوزهای
 *    مجاز: CC0/PD، CC BY، CC BY-SA. هر چیز دیگر (ND، NC، «همه حقوق
 *    محفوظ»، بدون مجوز) رد می‌شود — صفحه‌ای که عکاسش را نام نمی‌برد یا
 *    اجازه‌اش را ندارد، منتشر نمی‌شود.
 *
 * ۳) **گالری تصاویر شهرستان (v2.22.116)**: اسلایدر واکنش‌گرای همهٔ عکس‌های مجاز
 *    شهرستان (سه قاب در دسکتاپ، دو در تبلت، یکی در موبایل) با قاب ۴:۳، تیتر و
 *    شمارنده، لنگر `#county-gallery` و بازشدن لایت‌باکس با کلیک/Enter روی هر قاب.
 *    هر فایل پیش از ذخیره به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت تبدیل می‌شود
 *    (`sa_photo_optimize_bytes_to_webp`).
 *
 *    کادر پیشین «مکان در یک نگاه» (تصویر مربع sa-square) در همین نسخه کاملاً
 *    حذف شد و دیگر هیچ تابع یا استایلی از آن نمی‌ماند.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SA_PHOTO_META_AUTHOR   = '_sa_photo_author';
const SA_PHOTO_META_LICENSE  = '_sa_photo_license';
const SA_PHOTO_META_LICENSE_URL = '_sa_photo_license_url';
const SA_PHOTO_META_SOURCE   = '_sa_photo_source';
const SA_PHOTO_META_RETRIEVED = '_sa_photo_retrieved';
const SA_PHOTO_META_PLACE     = '_sa_photo_place';
const SA_PHOTO_META_NOTE      = '_sa_photo_note';
const SA_PHOTO_META_AREA      = '_sa_photo_area';
const SA_PHOTO_MAX_WEBP_BYTES = 99000;
const SA_PHOTO_TARGET_W       = 800;
const SA_PHOTO_TARGET_H       = 600;

/**
 * عادی‌سازی کد مجوز (مثلاً `CC-BY-SA-3.0` → `cc-by-sa-3`).
 *
 * @param string $code کد مجوز.
 * @return string
 */
function sa_photo_normalize_license( $code ) {
	$key = strtolower( trim( (string) $code ) );
	return (string) preg_replace( '/\.0$/', '', $key );
}

/**
 * محاسبهٔ کادر برش ۴:۳ و اندازهٔ خروجی بدون بزرگ‌نمایی تصاویر کوچک (v2.22.116).
 *
 * @param int $src_w    عرض منبع.
 * @param int $src_h    ارتفاع منبع.
 * @param int $target_w عرض هدف.
 * @param int $target_h ارتفاع هدف.
 * @return array{src_x:int,src_y:int,crop_w:int,crop_h:int,dst_w:int,dst_h:int}
 */
function sa_photo_crop_box( $src_w, $src_h, $target_w = SA_PHOTO_TARGET_W, $target_h = SA_PHOTO_TARGET_H ) {
	$src_w    = max( 1, (int) $src_w );
	$src_h    = max( 1, (int) $src_h );
	$target_w = max( 1, (int) $target_w );
	$target_h = max( 1, (int) $target_h );
	$want     = $target_w / $target_h;
	$have     = $src_w / $src_h;

	if ( $have > $want ) {
		$crop_h = $src_h;
		$crop_w = min( $src_w, max( 1, (int) round( $src_h * $want ) ) );
		$src_x  = max( 0, (int) floor( ( $src_w - $crop_w ) / 2 ) );
		$src_y  = 0;
	} else {
		$crop_w = $src_w;
		$crop_h = min( $src_h, max( 1, (int) round( $src_w / $want ) ) );
		$src_x  = 0;
		$src_y  = max( 0, (int) floor( ( $src_h - $crop_h ) * 0.40 ) );
	}

	$scale = min( 1.0, $target_w / $crop_w, $target_h / $crop_h );
	return array(
		'src_x'  => $src_x,
		'src_y'  => $src_y,
		'crop_w' => $crop_w,
		'crop_h' => $crop_h,
		'dst_w'  => max( 1, (int) round( $crop_w * $scale ) ),
		'dst_h'  => max( 1, (int) round( $crop_h * $scale ) ),
	);
}

/**
 * تشخیص نوع واقعی بایت‌های تصویر از امضای هدر فایل.
 *
 * @param string $bytes بایت‌های تصویر.
 * @return string MIME مجاز یا رشتهٔ خالی.
 */
function sa_photo_detect_mime( $bytes ) {
	if ( ! is_string( $bytes ) || strlen( $bytes ) < 12 ) {
		return '';
	}
	foreach ( sa_photo_admin_mimes() as $mime ) {
		if ( sa_photo_bytes_match_mime( $bytes, $mime ) ) {
			return $mime;
		}
	}
	return '';
}

/**
 * تبدیل هر تصویر JPEG / PNG / WebP به WebP چهار‌به‌سه با حجم تضمینی زیر ۱۰۰ کیلوبایت.
 *
 * بدون هیچ واترمارکی روی تصاویر مجوز آزاد؛ اگر ورودی از قبل WebP گالری با نسبت ۴:۳
 * و حجم زیر سقف باشد، بدون فشرده‌سازی مجدد حفظ می‌شود تا بیشترین وضوح بماند.
 *
 * @param string $bytes     بایت‌های تصویر ورودی.
 * @param int    $max_bytes سقف بایت خروجی.
 * @return array{bytes:string,width:int,height:int,size:int,quality:int,passthrough:bool}|WP_Error
 */
function sa_photo_optimize_bytes_to_webp( $bytes, $max_bytes = SA_PHOTO_MAX_WEBP_BYTES ) {
	$max_bytes = max( 15000, min( SA_PHOTO_MAX_WEBP_BYTES, (int) $max_bytes ) );
	$mime      = sa_photo_detect_mime( $bytes );
	if ( '' === $mime ) {
		return new WP_Error( 'sa_photo_mime', 'فقط تصویر واقعی jpeg، webp یا png پذیرفته می‌شود.', array( 'status' => 415 ) );
	}

	$len = strlen( $bytes );
	if ( 'image/webp' === $mime && $len >= 1200 && $len <= $max_bytes && function_exists( 'getimagesizefromstring' ) ) {
		$info = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $info ) && ! empty( $info[0] ) && ! empty( $info[1] ) ) {
			$w     = (int) $info[0];
			$h     = (int) $info[1];
			$ratio = $w / max( 1, $h );
			if ( $w <= 960 && $h <= 760 && $ratio >= 1.22 && $ratio <= 1.48 ) {
				return array(
					'bytes'       => $bytes,
					'width'       => $w,
					'height'      => $h,
					'size'        => $len,
					'quality'     => 0,
					'passthrough' => true,
				);
			}
		}
	}

	if ( ! function_exists( 'imagewebp' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
		return new WP_Error( 'sa_photo_no_webp', 'سرور از تبدیل WebP پشتیبانی نمی‌کند.', array( 'status' => 500 ) );
	}
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	if ( function_exists( 'getimagesizefromstring' ) ) {
		$pre = @getimagesizefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $pre ) && ( (int) $pre[0] > 7000 || (int) $pre[1] > 7000 || ( (int) $pre[0] * (int) $pre[1] ) > 28000000 ) ) {
			return new WP_Error( 'sa_photo_dimensions', 'ابعاد تصویر منبع بیش از حد بزرگ است؛ نسخهٔ کوچک‌تر بدهید.', array( 'status' => 413 ) );
		}
	}

	$src = false;
	if ( 'image/jpeg' === $mime && function_exists( 'sa_gallery_normalise_orientation' ) && function_exists( 'wp_tempnam' ) && function_exists( 'imagecreatefromjpeg' ) ) {
		$tmp = wp_tempnam( 'sa-photo-in' );
		if ( $tmp && false !== @file_put_contents( $tmp, $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$src = @imagecreatefromjpeg( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $src ) {
				$src = sa_gallery_normalise_orientation( $src, $tmp );
			}
		}
		if ( $tmp && is_file( $tmp ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
	if ( ! $src && function_exists( 'imagecreatefromstring' ) ) {
		$src = @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	if ( ! $src ) {
		return new WP_Error( 'sa_photo_decode', 'خواندن تصویر منبع ممکن نشد.', array( 'status' => 415 ) );
	}

	$src_w = imagesx( $src );
	$src_h = imagesy( $src );
	if ( $src_w < 80 || $src_h < 60 ) {
		if ( PHP_VERSION_ID < 80000 && is_resource( $src ) ) {
			imagedestroy( $src );
		}
		return new WP_Error( 'sa_photo_small', 'ابعاد تصویر برای گالری شهرستان بسیار کوچک است.', array( 'status' => 415 ) );
	}

	$plans = array(
		array( SA_PHOTO_TARGET_W, SA_PHOTO_TARGET_H, array( 84, 78, 72, 66, 60 ) ),
		array( 720, 540, array( 82, 76, 70, 64, 58 ) ),
		array( 640, 480, array( 78, 72, 65, 58, 50 ) ),
		array( 560, 420, array( 72, 62, 52, 44 ) ),
		array( 480, 360, array( 64, 52, 40, 32 ) ),
	);

	$tried = array();
	foreach ( $plans as $plan ) {
		$box = sa_photo_crop_box( $src_w, $src_h, (int) $plan[0], (int) $plan[1] );
		$dk  = $box['dst_w'] . 'x' . $box['dst_h'];
		if ( isset( $tried[ $dk ] ) ) {
			continue;
		}
		$tried[ $dk ] = true;

		$canvas = imagecreatetruecolor( $box['dst_w'], $box['dst_h'] );
		if ( ! $canvas ) {
			continue;
		}
		$bg = imagecolorallocate( $canvas, 248, 250, 252 );
		imagefilledrectangle( $canvas, 0, 0, $box['dst_w'], $box['dst_h'], $bg );
		imagealphablending( $canvas, true );
		imagecopyresampled( $canvas, $src, 0, 0, $box['src_x'], $box['src_y'], $box['dst_w'], $box['dst_h'], $box['crop_w'], $box['crop_h'] );

		foreach ( $plan[2] as $q ) {
			ob_start();
			$ok      = @imagewebp( $canvas, null, (int) $q ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$encoded = (string) ob_get_clean();
			$enc_len = strlen( $encoded );
			if ( $ok && $enc_len >= 400 && $enc_len <= $max_bytes && sa_photo_bytes_match_mime( $encoded, 'image/webp' ) ) {
				if ( PHP_VERSION_ID < 80000 ) {
					if ( is_resource( $canvas ) ) {
						imagedestroy( $canvas );
					}
					if ( is_resource( $src ) ) {
						imagedestroy( $src );
					}
				}
				return array(
					'bytes'       => $encoded,
					'width'       => $box['dst_w'],
					'height'      => $box['dst_h'],
					'size'        => $enc_len,
					'quality'     => (int) $q,
					'passthrough' => false,
				);
			}
		}
		if ( PHP_VERSION_ID < 80000 && is_resource( $canvas ) ) {
			imagedestroy( $canvas );
		}
	}

	if ( PHP_VERSION_ID < 80000 && is_resource( $src ) ) {
		imagedestroy( $src );
	}
	return new WP_Error( 'sa_photo_over_budget', 'کاهش حجم تصویر به زیر ۱۰۰ کیلوبایت ممکن نشد.', array( 'status' => 422 ) );
}

/**
 * ثبت متادیتای تک‌فایل WebP بدون ساخت زیراندازه‌های اضافی روی دیسک هاست.
 *
 * @param int    $att_id شناسهٔ پیوست.
 * @param string $file   مسیر کامل فایل WebP.
 * @param int    $width  عرض.
 * @param int    $height ارتفاع.
 * @param int    $bytes  حجم به بایت.
 * @param string $title  عنوان اختیاری.
 */
function sa_photo_store_webp_meta( $att_id, $file, $width, $height, $bytes, $title = '' ) {
	$att_id = absint( $att_id );
	$rel    = function_exists( '_wp_relative_upload_path' ) ? (string) _wp_relative_upload_path( $file ) : basename( $file );
	update_attached_file( $att_id, $file );
	$post_upd = array(
		'ID'             => $att_id,
		'post_mime_type' => 'image/webp',
		'post_status'    => 'inherit',
	);
	if ( '' !== $title ) {
		$post_upd['post_title'] = wp_slash( $title );
	}
	wp_update_post( $post_upd );
	wp_update_attachment_metadata(
		$att_id,
		array(
			'width'    => max( 1, (int) $width ),
			'height'   => max( 1, (int) $height ),
			'file'     => $rel,
			'filesize' => max( 0, (int) $bytes ),
			'sizes'    => array(),
		)
	);
	update_post_meta( $att_id, '_sa_photo_bytes', max( 0, (int) $bytes ) );
}

/**
 * پاک‌سازی امن فایل خام پیشین و زیراندازه‌های اضافی یک پیوست پس از تبدیل به WebP.
 * اگر نام فایلی در متن شهرستان ارجاع مستقیم داشته باشد حفظ می‌شود تا ۴۰۴ ندهد.
 *
 * @param string              $old_file فایل قبلی.
 * @param string              $keep     فایل WebP جدید.
 * @param array<string,mixed> $old_meta متادیتای قبلی.
 * @param int                 $city_id  شناسهٔ شهرستان.
 * @return array{freed_bytes:int,removed_files:int,kept_inline:bool}
 */
function sa_photo_cleanup_old_files( $old_file, $keep, $old_meta = array(), $city_id = 0 ) {
	$uploads = wp_upload_dir();
	$basedir = isset( $uploads['basedir'] ) ? wp_normalize_path( (string) $uploads['basedir'] ) : '';
	if ( '' === $basedir ) {
		return array( 'freed_bytes' => 0, 'removed_files' => 0, 'kept_inline' => false );
	}
	$body = '';
	if ( $city_id > 0 ) {
		$city_post = get_post( (int) $city_id );
		$body      = $city_post ? (string) $city_post->post_content : '';
	}
	$keep_norm  = wp_normalize_path( (string) $keep );
	$dir        = dirname( wp_normalize_path( (string) $old_file ) );
	$candidates = array();
	if ( '' !== (string) $old_file ) {
		$candidates[] = wp_normalize_path( (string) $old_file );
	}
	if ( is_array( $old_meta ) ) {
		if ( ! empty( $old_meta['original_image'] ) ) {
			$candidates[] = wp_normalize_path( $dir . '/' . basename( (string) $old_meta['original_image'] ) );
		}
		if ( ! empty( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
			foreach ( $old_meta['sizes'] as $sz_info ) {
				if ( is_array( $sz_info ) && ! empty( $sz_info['file'] ) ) {
					$candidates[] = wp_normalize_path( $dir . '/' . basename( (string) $sz_info['file'] ) );
				}
			}
		}
	}
	$freed       = 0;
	$removed     = 0;
	$kept_inline = false;
	foreach ( array_unique( $candidates ) as $path ) {
		if ( '' === $path || $path === $keep_norm || 0 !== strpos( $path, trailingslashit( $basedir ) ) || ! is_file( $path ) ) {
			continue;
		}
		if ( '' !== $body && false !== strpos( $body, basename( $path ) ) ) {
			$kept_inline = true;
			continue;
		}
		$sz = (int) @filesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$freed += max( 0, $sz );
			++$removed;
		}
	}
	return array(
		'freed_bytes'   => $freed,
		'removed_files' => $removed,
		'kept_inline'   => $kept_inline,
	);
}

/**
 * تبدیل درجا و امن یک پیوست گالری شهرستان به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت.
 *
 * @param int  $att_id شناسهٔ پیوست.
 * @param bool $force  تبدیل دوباره حتی در صورت بهینه‌بودن.
 * @return array<string,mixed>|WP_Error
 */
function sa_photo_optimize_attachment( $att_id, $force = false ) {
	$att_id  = absint( $att_id );
	$city_id = absint( get_post_meta( $att_id, '_sa_photo_city', true ) );
	$file    = (string) get_attached_file( $att_id );
	if ( ! $att_id || '' === $file || ! is_file( $file ) || ! is_readable( $file ) ) {
		return new WP_Error( 'sa_photo_missing_file', 'فایل پیوست روی سرور یافت نشد.', array( 'status' => 404 ) );
	}
	clearstatcache( true, $file );
	$before_bytes = (int) filesize( $file );
	$before_mime  = (string) get_post_mime_type( $att_id );
	$old_meta     = wp_get_attachment_metadata( $att_id );
	$old_meta     = is_array( $old_meta ) ? $old_meta : array();
	$old_w        = isset( $old_meta['width'] ) ? (int) $old_meta['width'] : 0;
	$old_h        = isset( $old_meta['height'] ) ? (int) $old_meta['height'] : 0;
	$old_ratio    = $old_h > 0 ? ( $old_w / $old_h ) : 0.0;
	$is_webp_ext  = 'webp' === strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );

	if ( ! $force && 'image/webp' === $before_mime && $is_webp_ext && $before_bytes > 0 && $before_bytes <= SA_PHOTO_MAX_WEBP_BYTES && $old_w > 0 && $old_w <= 960 && $old_ratio >= 1.22 && $old_ratio <= 1.48 ) {
		$clean = sa_photo_cleanup_old_files( $file, $file, $old_meta, $city_id );
		if ( ! empty( $old_meta['sizes'] ) || ! empty( $old_meta['original_image'] ) || (int) get_post_meta( $att_id, '_sa_photo_bytes', true ) !== $before_bytes ) {
			sa_photo_store_webp_meta( $att_id, $file, $old_w, $old_h, $before_bytes );
		}
		return array(
			'id'            => $att_id,
			'city_id'       => $city_id,
			'status'        => $clean['removed_files'] > 0 ? 'pruned_subsizes' : 'already_optimal',
			'before_mime'   => $before_mime,
			'before_bytes'  => $before_bytes,
			'after_bytes'   => $before_bytes,
			'saved_bytes'   => (int) $clean['freed_bytes'],
			'removed_files' => (int) $clean['removed_files'],
			'kept_inline'   => (bool) $clean['kept_inline'],
			'width'         => $old_w,
			'height'        => $old_h,
			'url'           => (string) wp_get_attachment_url( $att_id ),
		);
	}

	$raw = (string) @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	if ( '' === $raw ) {
		return new WP_Error( 'sa_photo_read_fail', 'خواندن فایل پیوست ممکن نشد.', array( 'status' => 500 ) );
	}
	$opt = sa_photo_optimize_bytes_to_webp( $raw, SA_PHOTO_MAX_WEBP_BYTES );
	if ( is_wp_error( $opt ) ) {
		return $opt;
	}

	$dir  = dirname( $file );
	$stem = sanitize_file_name( (string) pathinfo( $file, PATHINFO_FILENAME ) );
	$stem = '' !== $stem ? $stem : ( 'sa-photo-' . $att_id );
	if ( $is_webp_ext ) {
		$dest = $file;
	} else {
		$cand = $dir . '/' . $stem . '.webp';
		$dest = is_file( $cand ) ? $dir . '/' . wp_unique_filename( $dir, $stem . '.webp' ) : $cand;
	}

	$tmp_out = $dest . '.tmp-' . wp_generate_password( 6, false, false );
	if ( false === @file_put_contents( $tmp_out, $opt['bytes'] ) || ! @rename( $tmp_out, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( is_file( $tmp_out ) ) {
			@unlink( $tmp_out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return new WP_Error( 'sa_photo_write_fail', 'ذخیرهٔ فایل WebP بهینه‌شده انجام نشد.', array( 'status' => 500 ) );
	}

	sa_photo_store_webp_meta( $att_id, $dest, $opt['width'], $opt['height'], $opt['size'] );
	$clean = sa_photo_cleanup_old_files( $file, $dest, $old_meta, $city_id );
	$saved = $dest === $file
		? max( 0, $before_bytes - (int) $opt['size'] ) + (int) $clean['freed_bytes']
		: (int) $clean['freed_bytes'] - (int) $opt['size'];

	return array(
		'id'            => $att_id,
		'city_id'       => $city_id,
		'status'        => 'optimized',
		'before_mime'   => $before_mime,
		'before_bytes'  => $before_bytes,
		'after_bytes'   => (int) $opt['size'],
		'saved_bytes'   => $saved,
		'removed_files' => (int) $clean['removed_files'],
		'kept_inline'   => (bool) $clean['kept_inline'],
		'width'         => (int) $opt['width'],
		'height'        => (int) $opt['height'],
		'quality'       => (int) $opt['quality'],
		'url'           => (string) wp_get_attachment_url( $att_id ),
	);
}

/**
 * مجوزهایی که اجازهٔ انتشار تجاری با نام‌بردن از عکاس را می‌دهند.
 *
 * @return array<string,string> کد => برچسب فارسی
 */
function sa_photo_allowed_licenses() {
	return array(
		'cc0'      => 'CC0 1.0 (مالکیت عمومی)',
		'pd'       => 'مالکیت عمومی (Public Domain)',
		'cc-by-4'  => 'CC BY 4.0',
		'cc-by-3'  => 'CC BY 3.0',
		'cc-by-2'  => 'CC BY 2.0',
		'cc-by-sa-4' => 'CC BY-SA 4.0',
		'cc-by-sa-3' => 'CC BY-SA 3.0',
		'cc-by-sa-2' => 'CC BY-SA 2.0',
		// v2.22.115 (دستور مالک، ۲۷ آبان ۱۴۰۵): پرونده‌هایی که خود مالک برای سایت
		// می‌فرستد نه مجوز کریتیوکامنز دارند نه میزبان ویکی‌مدیا؛ این کد فقط برای همان
		// پرونده‌هاست (مسیر /photos/upload و پنل تصاویر در صفحهٔ ویرایش) و مسیر واردات
		// کامانز همان فهرست پیشین را می‌پذیرد.
		'sa-archive' => 'بایگانی سرزمین آریان (ارسالی مالک)',
	);
}

/**
 * دامنه‌هایی که سایت اجازه دارد از آن‌ها فایل بگیرد.
 *
 * @return string[]
 */
function sa_photo_allowed_hosts() {
	// از ۲۰۲۵ نسخهٔ بندانگشتی از thumb.wikimedia.org سرو می‌شود و فایل اصلی
	// از upload.wikimedia.org؛ هر دو فقط میزبان رسانهٔ ویکی‌مدیا هستند.
	return array( 'upload.wikimedia.org', 'thumb.wikimedia.org' );
}

/**
 * آیا این نشانی برای گرفتن تصویر مجاز است؟
 *
 * @param string $url نشانی.
 * @return bool
 */
function sa_photo_url_allowed( $url ) {
	$parts = wp_parse_url( (string) $url );
	if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}
	if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
		return false;
	}
	$host = strtolower( (string) $parts['host'] );
	foreach ( sa_photo_allowed_hosts() as $allowed ) {
		if ( $host === $allowed || substr( $host, -strlen( '.' . $allowed ) ) === '.' . $allowed ) {
			return true;
		}
	}
	return false;
}

/**
 * نام فارسی مجوز از کد.
 *
 * @param string $code کد.
 * @return string
 */
function sa_photo_license_label( $code ) {
	$all = sa_photo_allowed_licenses();
	$key = sa_photo_normalize_license( $code );
	return isset( $all[ $key ] ) ? $all[ $key ] : '';
}

/**
 * اعتبار تصویر برای چاپ زیر آن.
 *
 * @param int $attachment_id شناسهٔ پیوست.
 * @return array{author:string,license:string,license_label:string,license_url:string,source:string,retrieved:string}
 */
function sa_photo_credit( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$code          = sa_photo_normalize_license( (string) get_post_meta( $attachment_id, SA_PHOTO_META_LICENSE, true ) );
	return array(
		'author'        => trim( (string) get_post_meta( $attachment_id, SA_PHOTO_META_AUTHOR, true ) ),
		'license'       => $code,
		'license_label' => sa_photo_license_label( $code ),
		'license_url'   => (string) get_post_meta( $attachment_id, SA_PHOTO_META_LICENSE_URL, true ),
		'source'        => (string) get_post_meta( $attachment_id, SA_PHOTO_META_SOURCE, true ),
		'retrieved'     => (string) get_post_meta( $attachment_id, SA_PHOTO_META_RETRIEVED, true ),
	);
}

/**
 * آیا این پیوست اجازهٔ نمایش دارد؟ (مجوز مجاز + نام پدیدآور)
 *
 * @param int $attachment_id شناسه.
 * @return bool
 */
function sa_photo_is_publishable( $attachment_id ) {
	$credit = sa_photo_credit( $attachment_id );
	return ( '' !== $credit['author'] && '' !== $credit['license_label'] );
}

/**
 * فهرست شناسهٔ عکس‌های یک شهرستان که مکانشان ثبت شده است.
 *
 * @param int $city_id  شناسهٔ شهرستان.
 * @param int $limit    سقف.
 * @return int[]
 */
function sa_photo_place_ids( $city_id, $limit = 24 ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			// تصاویر مجاز به‌صورت JPEG ذخیره می‌شوند (مسیر /photos/import)؛ webp برای
			// عکس‌هایی است که از قبل در کتابخانه هست و png از ۲.۲۲.۱۱۵ برای پروندهٔ
			// ارسالی مالک. آلبوم قدیمی گالری جدا می‌ماند.
			'post_mime_type' => array( 'image/jpeg', 'image/webp', 'image/png' ),
			'posts_per_page' => max( 1, min( 60, (int) $limit ) ),
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array( 'key' => '_sa_photo_city', 'value' => absint( $city_id ) ),
			),
		)
	);
	$ids = array();
	foreach ( (array) $q->posts as $id ) {
		if ( sa_photo_is_publishable( $id ) && ! ( function_exists( 'sa_media_attachment_missing' ) && sa_media_attachment_missing( $id ) ) ) {
			$ids[] = (int) $id;
		}
	}
	return $ids;
}


/**
 * نام مکان برای نوشتن روی خود تصویر.
 *
 * عکسی که پیش از این نسخه‌ها وارد شده `_sa_photo_place` دارد و عکس تازه
 * `_sa_photo_area` (نام کوتاه روی تصویر) هم می‌گیرد؛ اگر نبود، همان نام مکان
 * کوتاه‌شده به کار می‌رود. عنوان شهرستان اینجا نمی‌آید.
 *
 * @param int $attachment_id شناسهٔ پیوست.
 * @return string
 */
function sa_photo_overlay_label( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$overlay = trim( (string) get_post_meta( $attachment_id, '_sa_photo_overlay', true ) );
	if ( '' !== $overlay ) {
		return $overlay;
	}
	$area = trim( (string) get_post_meta( $attachment_id, SA_PHOTO_META_AREA, true ) );
	if ( '' !== $area ) {
		return $area;
	}
	$place = trim( (string) get_post_meta( $attachment_id, SA_PHOTO_META_PLACE, true ) );
	if ( '' === $place ) {
		return '';
	}
	// «آیینه‌خانهٔ مفخم (بجنورد)» → «آیینه‌خانهٔ مفخم»
	$place = (string) preg_replace( '/\s*[\(\（][^\)\）]*[\)\）]\s*$/u', '', $place );
	return trim( $place );
}

/**
 * ناحیه‌ای که تصویر در آن نوشته می‌شود (نشانی، محله، روستا…).
 *
 * @param int $attachment_id شناسهٔ پیوست.
 * @return string
 */
function sa_photo_area( $attachment_id ) {
	return trim( (string) get_post_meta( absint( $attachment_id ), SA_PHOTO_META_AREA, true ) );
}

/**
 * خط اعتبار تصویر — کوچک و کم‌رنگ زیر قاب.
 *
 * دلخواه مالک (۱۷ نوامبر ۲۰۲۶): تیترِ «عکس:» و نام مجوز دیگر جلوی چشم نباشد؛
 * فقط نام عکاس و پیوند «منبع» (که همان مجوز و اصل فایل را نشان می‌دهد) می‌ماند.
 * اگر نشانی مجوز ثبت شده باشد، روی همان نام عکاس می‌نشیند تا تعهد حقوقی
 * (انتساب + پیوند مجوز) حفظ شود، ولی متن اضافه‌ای دیده نشود.
 *
 * @param int    $attachment_id شناسهٔ پیوست.
 * @param string $class         کلاس پاراگراف.
 * @return string
 */
function sa_photo_credit_line( $attachment_id, $class = 'sa-photo__credit' ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return '';
	}
	$credit = sa_photo_credit( $attachment_id );
	if ( '' === $credit['author'] ) {
		return '';
	}
	$href  = '' !== $credit['license_url'] ? $credit['license_url'] : $credit['source'];
	$rel   = '' !== $credit['license_url'] ? 'license nofollow noopener' : 'nofollow noopener';
	$title = trim( 'عکس: ' . $credit['author'] . ( '' !== $credit['license_label'] ? ' · ' . $credit['license_label'] : '' ) );
	$out   = '<p class="' . esc_attr( $class ) . '">';
	$out  .= '' !== $href
		? '<a href="' . esc_url( $href ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" title="' . esc_attr( $title ) . '">' . esc_html( $credit['author'] ) . '</a>'
		: esc_html( $credit['author'] );
	$out .= '</p>';
	return $out;
}

/**
 * چاپ یک قاب تصویر: نام مکان روی خود عکس + خط اعتبار کوچک زیرش.
 *
 * @param int    $attachment_id شناسه.
 * @param string $size          اندازهٔ تصویر.
 * @param string $class         کلاس کادر.
 * @param bool   $with_credit   خط اعتبار زیر تصویر چاپ شود؟
 * @return string
 */
function sa_photo_frame( $attachment_id, $size = 'sa-gallery-card', $class = 'sa-photo__frame', $with_credit = true ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || ! sa_photo_is_publishable( $attachment_id ) ) {
		return '';
	}
	$img = wp_get_attachment_image(
		$attachment_id,
		$size,
		false,
		array(
			'class'    => 'sa-photo__img',
			'loading'  => 'lazy',
			'decoding' => 'async',
		)
	);
	if ( '' === $img ) {
		return '';
	}
	$label = sa_photo_overlay_label( $attachment_id );
	$area  = sa_photo_area( $attachment_id );
	$out   = '<figure class="' . esc_attr( $class ) . '">' . $img;
	// اگر برچسبِ روی عکس خودش همان نشانی باشد (area)، خط دوم تکرارش نمی‌کند؛
	// پیش از این همان جمله دو بار روی عکس می‌آمد. (۲.۲۲.۱۱۷)
	$sub = ( '' !== $area && $area !== $label ) ? $area : '';
	if ( '' !== $label ) {
		// نام مکان روی تصویر، با سایه و لایهٔ تیرهٔ پایین قاب تا روی هر عکسی خوانا بماند.
		$out .= '<figcaption class="sa-photo__caption">';
		$out .= '<span class="sa-photo__place">' . esc_html( $label ) . '</span>';
		if ( '' !== $sub ) {
			$out .= '<span class="sa-photo__area">' . esc_html( $sub ) . '</span>';
		}
		$out .= '</figcaption>';
	}
	$out .= '</figure>';
	if ( $with_credit ) {
		$out .= sa_photo_credit_line( $attachment_id );
	}
	return $out;
}

/**
 * ترتیب نمایش عکس‌های یک شهرستان: نخست عکس lead، بعد ترتیب دستی مالک
 * (`_sa_photo_order`) و در پایان تازه‌ترین. عکس‌های پنهان‌شده (`_sa_photo_hide`)
 * هرگز روی صفحه نمی‌آیند ولی در پنل ویرایش می‌مانند (v2.22.115).
 *
 * @param int $city_id شناسهٔ شهرستان.
 * @param int $limit   سقف.
 * @return int[]
 */
function sa_photo_county_ids( $city_id, $limit = 12 ) {
	$ids = array();
	foreach ( sa_photo_place_ids( $city_id, 60 ) as $id ) {
		if ( '1' === (string) get_post_meta( $id, '_sa_photo_hide', true ) ) {
			continue;
		}
		$ids[] = (int) $id;
	}
	usort(
		$ids,
		static function ( $a, $b ) {
			$la = '1' === (string) get_post_meta( $a, '_sa_photo_lead', true ) ? 0 : 1;
			$lb = '1' === (string) get_post_meta( $b, '_sa_photo_lead', true ) ? 0 : 1;
			if ( $la !== $lb ) {
				return $la <=> $lb;
			}
			$oa = (int) get_post_meta( $a, '_sa_photo_order', true );
			$ob = (int) get_post_meta( $b, '_sa_photo_order', true );
			$oa = $oa > 0 ? $oa : PHP_INT_MAX;
			$ob = $ob > 0 ? $ob : PHP_INT_MAX;
			if ( $oa !== $ob ) {
				return $oa <=> $ob;
			}
			return strcmp( (string) get_post_field( 'post_date', $b ), (string) get_post_field( 'post_date', $a ) );
		}
	);
	return array_slice( $ids, 0, max( 1, min( 24, (int) $limit ) ) );
}

/**
 * پوستهٔ اسلایدر مشترک: 트랙ِ snap دار + دکمهٔ قبلی/بعدی + نقطه‌ها.
 * دکمه‌ها و نقطه‌ها با gallery.js پر می‌شوند؛ بدون JS هم 트랙 با کشیدن کار می‌کند.
 *
 * @param string $slides_html قاب‌های داخل ترک.
 * @param string $label       برچسب دسترس‌پذیر ترک.
 * @return string
 */
function sa_photo_slider( $slides_html, $label ) {
	// صحنه (stage) فقط ترک و دو دکمه را در بر می‌گیرد تا دکمه‌ها دقیقاً روی میانهٔ
	// قاب بنشینند؛ نقطه‌ها و شمارنده زیر صحنه می‌مانند و جای قاب را تنگ نمی‌کنند.
	$out  = '<div class="sa-slider" data-sa-slider>';
	$out .= '<div class="sa-slider__stage">';
	$out .= '<div class="sa-slider__track" tabindex="0" aria-label="' . esc_attr( $label ) . '">';
	$out .= $slides_html;
	$out .= '</div>';
	$out .= '<button type="button" class="sa-slider__nav sa-slider__nav--prev" aria-label="نمای قبلی">&#8250;</button>';
	$out .= '<button type="button" class="sa-slider__nav sa-slider__nav--next" aria-label="نمای بعدی">&#8249;</button>';
	$out .= '</div>';
	$out .= '<div class="sa-slider__dots"></div>';
	$out .= '<span class="sa-slider__count" aria-live="polite"></span>';
	$out .= '</div>';
	return $out;
}

/**
 * گالری تصاویر شهرستان (v2.22.116) — اسلایدر واکنش‌گرا با قاب یکدست ۴:۳ و WebP زیر ۱۰۰ کیلوبایت.
 *
 * بخش «مکان در یک نگاه» به دستور مالک به‌طور کامل حذف شد و جای آن همین گالری
 * تصاویر شهرستان بالای امتیاز کاربران و منابع قرار گرفت: در دسکتاپ سه قاب کنار هم،
 * در تبلت دو قاب و در موبایل یک قاب با کشیدن انگشت؛ چیدمان، افزودن، حذف، تعویض و
 * اعتبار تصاویر کاملاً از صفحهٔ ویرایش شهرستان در پیشخوان قابل سفارشی‌سازی است.
 *
 * @param int $city_id شناسهٔ شهرستان.
 * @return void
 */
function sa_photo_strip( $city_id = 0 ) {
	$city_id = $city_id ? absint( $city_id ) : get_the_ID();
	if ( 'city' !== get_post_type( $city_id ) ) {
		return;
	}
	$ids = sa_photo_county_ids( $city_id, 24 );
	if ( ! $ids ) {
		return;
	}
	$track = '';
	$shown = 0;
	foreach ( $ids as $id ) {
		$line      = trim( (string) get_post_meta( $id, '_sa_photo_highlight', true ) );
		$tag       = trim( (string) get_post_meta( $id, '_sa_photo_tag', true ) );
		$label     = sa_photo_overlay_label( $id );
		$area      = sa_photo_area( $id );
		$mime      = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $id ) : '';
		$bytes     = (int) get_post_meta( $id, '_sa_photo_bytes', true );
		$size_name = ( 'image/webp' === $mime && ( 0 === $bytes || $bytes <= 102400 ) ) ? 'full' : 'sa-gallery-card';
		$frame     = sa_photo_frame( $id, $size_name, 'sa-strip__photo', false );
		if ( '' === $frame ) {
			continue;
		}
		++$shown;
		$full_url = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $id ) : '';
		$credit   = sa_photo_credit( $id );
		$track   .= '<div class="sa-slider__slide sa-strip__slide">';
		$track   .= '<article class="sa-strip__tile">';
		$track   .= '<div class="sa-strip__frame"'
			. ( '' !== $full_url ? ' data-sa-zoom="' . esc_url( $full_url ) . '" data-sa-title="' . esc_attr( $label ) . '" data-sa-note="' . esc_attr( $line ) . '" data-sa-author="' . esc_attr( $credit['author'] ) . '" tabindex="0" role="button" aria-label="' . esc_attr( 'بزرگ‌نمایی تصویر ' . ( '' !== $label ? $label : get_the_title( $city_id ) ) ) . '"' : '' )
			. '>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده با توابع escape داخلی.
		$track .= $frame;
		if ( '' !== $tag ) {
			$track .= '<span class="sa-strip__tag">' . esc_html( $tag ) . '</span>';
		}
		$track .= '</div>';
		$track .= '<div class="sa-strip__highlight">';
		if ( '' !== $line ) {
			$track .= '<p class="sa-strip__line">' . esc_html( $line ) . '</p>';
		}
		if ( '' !== $area && false === strpos( $label, $area ) ) {
			$track .= '<p class="sa-strip__area">' . esc_html( $area ) . '</p>';
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده با توابع escape داخلی.
		$track .= sa_photo_credit_line( $id, 'sa-strip__credit' );
		$track .= '</div></article></div>';
	}
	if ( ! $shown ) {
		return;
	}
	$title_txt = (string) get_the_title( $city_id );
	$count_lbl = function_exists( 'sa_fa_digits' ) ? sa_fa_digits( (string) $shown ) . ' تصویر' : ( $shown . ' تصویر' );
	?>
	<section class="sa-strip sa-county-gallery" id="county-gallery" aria-label="<?php echo esc_attr( 'گالری تصاویر ' . $title_txt ); ?>">
		<div class="sa-strip__head">
			<div class="sa-strip__title-row">
				<h2 class="sa-strip__title">گالری تصاویر <?php echo esc_html( $title_txt ); ?></h2>
				<span class="sa-strip__badge"><?php echo esc_html( $count_lbl ); ?></span>
			</div>
		</div>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ترک با توابع escape داخلی ساخته شده.
		echo sa_photo_slider( $track, 'گالری تصاویر ' . $title_txt );
		?>
	</section>
	<?php
}

/**
 * ذخیرهٔ فایل تازه روی یک پیوست موجود به صورت WebP بهینه‌شده زیر ۱۰۰ کیلوبایت.
 *
 * @param int    $att_id پیوست.
 * @param string $url    نشانی تازه.
 * @param string $place  مکان.
 * @param string $alt    alt تصویر.
 * @return true|WP_Error
 */
function sa_photo_replace_file( $att_id, $url, $place, $alt ) {
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 45,
			'redirection'         => 3,
			'limit_response_size' => 10 * MB_IN_BYTES,
			'user-agent'          => 'SarzaminaryanBot/1.1 (https://sarzaminaryan.ir; licensed photo import)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sa_photo_fetch', 'دریافت تصویر ناموفق بود: ' . $response->get_error_message(), array( 'status' => 502 ) );
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'sa_photo_fetch_status', 'سرور مبدأ فایل تازه را نداد.', array( 'status' => 502 ) );
	}
	$bytes = (string) wp_remote_retrieve_body( $response );
	if ( strlen( $bytes ) < 1200 || strlen( $bytes ) > 10 * MB_IN_BYTES || '' === sa_photo_detect_mime( $bytes ) ) {
		return new WP_Error( 'sa_photo_mime', 'فایل تازه تصویر معتبر (jpeg/webp/png) نیست.', array( 'status' => 415 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$opt = sa_photo_optimize_bytes_to_webp( $bytes, SA_PHOTO_MAX_WEBP_BYTES );
	if ( is_wp_error( $opt ) ) {
		return $opt;
	}
	$old_file = (string) get_attached_file( absint( $att_id ) );
	$old_meta = wp_get_attachment_metadata( absint( $att_id ) );
	$city_id  = absint( get_post_meta( absint( $att_id ), '_sa_photo_city', true ) );
	$slug     = sanitize_title( $place ? $place : 'photo' );
	$filename = 'sa-photo-' . ( '' !== $slug ? $slug : 'photo' ) . '-' . substr( md5( $url ), -6 ) . '.webp';
	$upload   = wp_upload_bits( $filename, null, $opt['bytes'] );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_upload', 'ذخیرهٔ فایل WebP تازه انجام نشد.', array( 'status' => 500 ) );
	}
	sa_photo_store_webp_meta( absint( $att_id ), $upload['file'], $opt['width'], $opt['height'], $opt['size'], '' !== $place ? $place : $filename );
	sa_photo_cleanup_old_files( $old_file, $upload['file'], is_array( $old_meta ) ? $old_meta : array(), $city_id );
	if ( '' !== $alt ) {
		update_post_meta( absint( $att_id ), '_wp_attachment_image_alt', wp_slash( $alt ) );
	}
	return true;
}

/**
 * نوشتن پرووننس و متن نمایشی روی پیوست.
 *
 * @param int    $att_id  پیوست.
 * @param array  $b       بدنهٔ درخواست.
 * @param int    $city_id شهرستان.
 * @param string $place   مکان.
 * @param string $kind    heritage|fun|nature|modern.
 * @param string $key     کلید یکتا.
 * @param string $url     نشانی فایل.
 * @param string $author  عکاس.
 * @param string $license کد مجوز.
 * @return void
 */
function sa_photo_write_meta( $att_id, $b, $city_id, $place, $kind, $key, $url, $author, $license ) {
	$att_id = absint( $att_id );
	$alt    = isset( $b['alt'] ) ? sanitize_text_field( (string) $b['alt'] ) : '';
	$license = sa_photo_normalize_license( $license );
	update_post_meta( $att_id, '_wp_attachment_image_alt', wp_slash( '' !== $alt ? $alt : $place ) );
	update_post_meta( $att_id, SA_PHOTO_META_AUTHOR, wp_slash( $author ) );
	update_post_meta( $att_id, SA_PHOTO_META_LICENSE, wp_slash( $license ) );
	update_post_meta( $att_id, SA_PHOTO_META_LICENSE_URL, esc_url_raw( (string) ( $b['license_url'] ?? '' ) ) );
	update_post_meta( $att_id, SA_PHOTO_META_SOURCE, esc_url_raw( (string) ( $b['source_page'] ?? '' ) ) );
	update_post_meta( $att_id, SA_PHOTO_META_RETRIEVED, gmdate( 'Y-m-d' ) );
	update_post_meta( $att_id, SA_PHOTO_META_PLACE, wp_slash( $place ) );
	// نام کوتاه روی خود تصویر و ناحیه (نشانی/محله) — تازه در ۲.۲۲.۱۰۲؛ اگر
	// دستهٔ عکس این‌ها را نداشته باشد، نام مکان بدون پرانتز روی تصویر می‌آید.
	update_post_meta( $att_id, SA_PHOTO_META_AREA, wp_slash( sanitize_text_field( (string) ( $b['area'] ?? '' ) ) ) );
	update_post_meta( $att_id, '_sa_photo_overlay', wp_slash( sanitize_text_field( (string) ( $b['overlay'] ?? '' ) ) ) );
	update_post_meta( $att_id, SA_PHOTO_META_NOTE, wp_slash( sanitize_textarea_field( (string) ( $b['note'] ?? '' ) ) ) );
	update_post_meta( $att_id, '_sa_photo_points', wp_slash( sanitize_text_field( (string) ( $b['points'] ?? '' ) ) ) );
	update_post_meta( $att_id, '_sa_photo_highlight', wp_slash( sanitize_text_field( (string) ( $b['highlight'] ?? '' ) ) ) );
	update_post_meta( $att_id, '_sa_photo_tag', wp_slash( sanitize_text_field( (string) ( $b['tag'] ?? '' ) ) ) );
	update_post_meta( $att_id, '_sa_photo_kind', wp_slash( $kind ) );
	update_post_meta( $att_id, '_sa_photo_city', absint( $city_id ) );
	update_post_meta( $att_id, '_sa_photo_key', wp_slash( $key ) );
	update_post_meta( $att_id, '_sa_photo_origin', esc_url_raw( $url ) );
}

/**
 * RETR: GET /photos
 *
 * فهرست عکس‌های مجاز هر شهرستان با پرووننس — برای بازبینی مالک و اطمینان از
 * اینکه هر عکس روی صفحه نام عکاس و مجوز دارد. شمارش هر شهرستان از همین‌جا
 * در دفتر تصاویر (docs/live-site/photo-credits.md) ثبت می‌شود.
 *
 * @param WP_REST_Request $r درخواست (`?city_id=` اختیاری).
 * @return WP_REST_Response
 */
function sa_agent_rest_photos_inventory( $r ) {
	$city_id  = absint( $r->get_param( 'city_id' ) );
	$page     = max( 1, absint( $r->get_param( 'page' ) ?: 1 ) );
	$per_page = max( 1, min( 500, absint( $r->get_param( 'per_page' ) ?: 500 ) ) );
	$mq       = $city_id
		? array( array( 'key' => '_sa_photo_city', 'value' => $city_id ) )
		: array( array( 'key' => '_sa_photo_city', 'compare' => 'EXISTS' ) );
	$q        = new WP_Query(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => sa_photo_admin_mimes(),
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => $mq,
		)
	);
	$rows       = array();
	$per_city   = array();
	$over_100kb = 0;
	$non_webp   = 0;
	foreach ( (array) $q->posts as $id ) {
		$id     = (int) $id;
		$credit = sa_photo_credit( $id );
		$hidden = '1' === (string) get_post_meta( $id, '_sa_photo_hide', true );
		$pub    = sa_photo_is_publishable( $id );
		$shown  = $pub && ! $hidden;
		$file   = (string) get_attached_file( $id );
		$bytes  = ( '' !== $file && is_file( $file ) ) ? (int) @filesize( $file ) : (int) get_post_meta( $id, '_sa_photo_bytes', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$mime   = (string) get_post_mime_type( $id );
		$meta   = wp_get_attachment_metadata( $id );
		$w      = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h      = is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$cid    = (int) get_post_meta( $id, '_sa_photo_city', true );
		if ( $shown ) {
			$per_city[ $cid ] = isset( $per_city[ $cid ] ) ? $per_city[ $cid ] + 1 : 1;
			if ( $bytes > 100000 ) {
				++$over_100kb;
			}
			if ( 'image/webp' !== $mime ) {
				++$non_webp;
			}
		}
		$rows[] = array(
			'id'            => $id,
			'city_id'       => $cid,
			'place'         => (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true ),
			'overlay'       => sa_photo_overlay_label( $id ),
			'kind'          => (string) get_post_meta( $id, '_sa_photo_kind', true ),
			'author'        => $credit['author'],
			'license'       => $credit['license'],
			'license_label' => $credit['license_label'],
			'source'        => $credit['source'],
			'retrieved'     => $credit['retrieved'],
			'mime'          => $mime,
			'bytes'         => $bytes,
			'width'         => $w,
			'height'        => $h,
			'lead'          => '1' === (string) get_post_meta( $id, '_sa_photo_lead', true ),
			'hidden'        => $hidden,
			'order'         => (int) get_post_meta( $id, '_sa_photo_order', true ),
			'publishable'   => sa_photo_is_publishable( $id ),
			'visible'       => $shown,
			'url'           => (string) wp_get_attachment_url( $id ),
		);
	}
	return rest_ensure_response(
		array(
			'count'      => count( $rows ),
			'total'      => (int) $q->found_posts,
			'page'       => $page,
			'pages'      => max( 1, (int) $q->max_num_pages ),
			'max_bytes'  => SA_PHOTO_MAX_WEBP_BYTES,
			'over_100kb' => $over_100kb,
			'non_webp'   => $non_webp,
			'per_city'   => $per_city,
			'photos'     => $rows,
		)
	);
}

/**
 * REST: POST /photos/prune
 *
 * چرا این مسیر لازم شد: هر بار که یک مکان با نشانی تازه وارد می‌شود (اصلاح نشانی، فایل
 * بهتر) و نسخهٔ قبلی کلید مکان نداشته، یک نسخهٔ اضافه در کتابخانه می‌ماند. پاک‌کردن
 * دستی از راه پل ممکن نیست (مسیر /post برای پیوست‌ها کار نمی‌کند؛ کار ۱۲۴ با ۴۰۴ رد شد).
 *
 * این مسیر فقط «نسخه‌های اضافیِ همان مکان» را به زباله‌دان می‌برد — نه حذف همیشگی:
 * برای هر (شهرستان، مکان) **جدیدترین** پیوست می‌ماند و بقیه به زباله‌دان می‌روند
 * (۳۰ روز قابل بازگردانی). پرچم صریح مالک و پیش‌نمایش اجباری است.
 *
 * بدنه: {city_id, owner_approved: true, dry_run?: bool}
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_photos_prune( $r ) {
	$b       = (array) $r->get_json_params();
	$city_id = isset( $b['city_id'] ) ? absint( $b['city_id'] ) : 0;
	if ( empty( $b['owner_approved'] ) || true !== $b['owner_approved'] ) {
		return new WP_Error( 'sa_photo_need_owner', 'پاک‌سازی فقط با دستور صریح مالک (owner_approved: true).', array( 'status' => 403 ) );
	}
	if ( ! $city_id || 'city' !== get_post_type( $city_id ) ) {
		return new WP_Error( 'sa_photo_city', 'شهرستان مقصد معتبر نیست.', array( 'status' => 400 ) );
	}

	// گروه‌بندی بر اساس «شهرستان + مکان»؛ کلید یکتا از قبل روی هر پیوست ثبت شده است.
	$groups = array();
	foreach ( (array) sa_photo_place_ids( $city_id, 60 ) as $id ) {
		$place = (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true );
		$key   = '' !== $place ? $place : '#' . (int) $id;
		$groups[ $key ][] = (int) $id;
	}

	$dry    = ! empty( $b['dry_run'] );
	$keep   = array();
	$pruned = array();
	foreach ( $groups as $place => $ids ) {
		usort(
			$ids,
			static function ( $a, $bb ) {
				return (int) get_post_field( 'post_date', $bb ) <=> (int) get_post_field( 'post_date', $a );
			}
		);
		$keep[] = $ids[0];
		foreach ( array_slice( $ids, 1 ) as $extra ) {
			if ( $dry ) {
				$pruned[] = array( 'id' => $extra, 'place' => $place, 'action' => 'would trash' );
				continue;
			}
			if ( wp_trash_post( $extra ) ) {
				$pruned[] = array( 'id' => $extra, 'place' => $place, 'action' => 'trashed' );
			} else {
				$pruned[] = array( 'id' => $extra, 'place' => $place, 'action' => 'failed' );
			}
		}
	}

	return rest_ensure_response(
		array(
			'city_id' => $city_id,
			'dry_run' => $dry,
			'kept'    => $keep,
			'pruned'  => $pruned,
		)
	);
}

/**
 * REST: POST /photos/import
 *
 * بدنه: {url, place, kind, note, points, highlight, tag, alt, author, license,
 *        license_url, source_page, city_id}
 *
 * مجوز *باید* از فهرست مجاز باشد و author پر باشد، وگرنه ۴۲۲ برمی‌گردد.
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_photo_import( $r ) {
	$b        = (array) $r->get_json_params();
	$url      = isset( $b['url'] ) ? trim( (string) $b['url'] ) : '';
	$author   = isset( $b['author'] ) ? sanitize_text_field( (string) $b['author'] ) : '';
	$license  = isset( $b['license'] ) ? strtolower( sanitize_text_field( (string) $b['license'] ) ) : '';
	$city_id  = isset( $b['city_id'] ) ? absint( $b['city_id'] ) : 0;
	$place    = isset( $b['place'] ) ? sanitize_text_field( (string) $b['place'] ) : '';
	$kind     = isset( $b['kind'] ) ? sanitize_text_field( (string) $b['kind'] ) : 'heritage';
	$alt      = isset( $b['alt'] ) ? sanitize_text_field( (string) $b['alt'] ) : '';
	// کلید بر اساس *مکان* است، نه نشانی فایل: اگر همان مکان با نشانی تازه برسد،
	// همین پیوست به‌روز می‌شود و نسخهٔ تکراری در کتابخانه ساخته نمی‌شود.
	$key      = 'sa-photo:' . md5( absint( $city_id ) . '|' . $place );

	if ( '' === $url || '' === $author || '' === $license ) {
		return new WP_Error( 'sa_photo_missing', 'url، author و license الزامی است.', array( 'status' => 400 ) );
	}
	if ( '' === sa_photo_license_label( $license ) ) {
		return new WP_Error( 'sa_photo_license', 'این مجوز برای انتشار مجاز نیست؛ فقط CC0/PD/CC BY/CC BY-SA.', array( 'status' => 422 ) );
	}
	if ( ! sa_photo_url_allowed( $url ) ) {
		return new WP_Error( 'sa_photo_host', 'این دامنه برای دریافت تصویر مجاز نیست.', array( 'status' => 422 ) );
	}
	if ( ! $city_id || 'city' !== get_post_type( $city_id ) ) {
		return new WP_Error( 'sa_photo_city', 'شهرستان مقصد معتبر نیست.', array( 'status' => 400 ) );
	}

	// اجرای دوباره: پیوست سالم با همان کلید بازاستفاده می‌شود (بدون تغییر).
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array( array( 'key' => '_sa_photo_key', 'value' => $key ) ),
		)
	);
	foreach ( $existing as $att_id ) {
		$att_id  = (int) $att_id;
		$missing = function_exists( 'sa_media_attachment_missing' ) ? sa_media_attachment_missing( $att_id ) : false;
		if ( $missing ) {
			continue;
		}
		$old_file = (string) get_post_meta( $att_id, '_sa_photo_origin', true );
		$replaced = false;
		if ( $old_file !== $url ) {
			$swapped = sa_photo_replace_file( $att_id, $url, $place, $alt );
			if ( is_wp_error( $swapped ) ) {
				return $swapped;
			}
			$replaced = true;
		} else {
			sa_photo_optimize_attachment( $att_id, false );
		}
		sa_photo_write_meta( $att_id, $b, $city_id, $place, $kind, $key, $url, $author, $license );
		return rest_ensure_response(
			array(
				'id'       => $att_id,
				'reused'   => true,
				'replaced' => $replaced,
				'overlay'  => sa_photo_overlay_label( $att_id ),
				'area'     => sa_photo_area( $att_id ),
				'bytes'    => (int) get_post_meta( $att_id, '_sa_photo_bytes', true ),
				'mime'     => (string) get_post_mime_type( $att_id ),
				'url'      => wp_get_attachment_url( $att_id ),
			)
		);
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 45,
			'redirection'         => 3,
			'limit_response_size' => 10 * MB_IN_BYTES,
			'user-agent'          => 'SarzaminaryanBot/1.1 (https://sarzaminaryan.ir; photo import with attribution)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sa_photo_fetch', 'دریافت تصویر ناموفق بود: ' . $response->get_error_message(), array( 'status' => 502 ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'sa_photo_fetch_status', 'سرور مبدأ کد ' . $code . ' برگرداند.', array( 'status' => 502 ) );
	}
	$bytes = (string) wp_remote_retrieve_body( $response );
	$mime  = sa_photo_detect_mime( $bytes );
	if ( '' === $mime ) {
		return new WP_Error( 'sa_photo_mime', 'فقط تصویر واقعی jpeg/webp/png پذیرفته می‌شود.', array( 'status' => 415 ) );
	}
	if ( strlen( $bytes ) < 1200 || strlen( $bytes ) > 10 * MB_IN_BYTES ) {
		return new WP_Error( 'sa_photo_size', 'حجم فایل منبع مناسب نیست.', array( 'status' => 413 ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	// v2.22.116: تبدیل خودکار هر عکس وارداتی به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت.
	$opt = sa_photo_optimize_bytes_to_webp( $bytes, SA_PHOTO_MAX_WEBP_BYTES );
	if ( is_wp_error( $opt ) ) {
		return $opt;
	}

	$slug = sanitize_title( $place ? $place : 'photo' );
	if ( '' === $slug ) {
		$slug = 'photo';
	}
	$filename = 'sa-photo-' . $slug . '-' . substr( $key, -6 ) . '.webp';

	$upload = wp_upload_bits( $filename, null, $opt['bytes'] );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_upload', 'ذخیرهٔ فایل WebP انجام نشد.', array( 'status' => 500 ) );
	}

	$att_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => wp_slash( '' !== $place ? $place : $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$city_id,
		true
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		@unlink( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return new WP_Error( 'sa_photo_attachment', 'ثبت پیوست انجام نشد.', array( 'status' => 500 ) );
	}
	sa_photo_store_webp_meta( (int) $att_id, $upload['file'], $opt['width'], $opt['height'], $opt['size'] );
	sa_photo_write_meta( (int) $att_id, $b, $city_id, $place, $kind, $key, $url, $author, $license );
	if ( isset( $b['order'] ) ) {
		update_post_meta( (int) $att_id, '_sa_photo_order', max( 0, absint( $b['order'] ) ) );
	}
	if ( ! empty( $b['lead'] ) ) {
		foreach ( sa_photo_place_ids( $city_id, 60 ) as $other ) {
			if ( (int) $other !== (int) $att_id ) {
				update_post_meta( (int) $other, '_sa_photo_lead', '' );
			}
		}
		update_post_meta( (int) $att_id, '_sa_photo_lead', '1' );
	}

	return rest_ensure_response(
		array(
			'id'      => (int) $att_id,
			'reused'  => false,
			'url'     => (string) ( $upload['url'] ?? wp_get_attachment_url( (int) $att_id ) ),
			'overlay' => sa_photo_overlay_label( (int) $att_id ),
			'area'    => sa_photo_area( (int) $att_id ),
			'mime'    => 'image/webp',
			'bytes'   => (int) $opt['size'],
			'width'   => (int) $opt['width'],
			'height'  => (int) $opt['height'],
			'license' => sa_photo_license_label( $license ),
		)
	);
}

/* -------------------------------------------------------------------------
 * v2.22.115 — ویرایش دستی تصاویر شهرستان (دستور مالک، ۲۷ آبان ۱۴۰۵)
 *
 * دستور مالک: «در بخش ویرایش مقاله در پیشنویس، جایی برای آپلود تصویر بگذار که اگر
 * خواستم تصویر جدید بگذارم، حذف کنم، و در هر صورت تصویر را بتوانم تغییر بدهم.»
 * پس مالک هم می‌تواند فایل یک قاب را عوض کند، هم عکس بی‌ارزش (مثلاً نمای نزدیک
 * دیوار) را از صفحه بردارد. این‌جا سه ابزار ذخیره‌سازی آن کار است؛ خودِ پنل و
 * هندلرهای ajax در inc/county-photos-admin.php نشسته‌اند.
 *
 * ترتیب اسلایدرها هم دستی است: _sa_photo_order (عدد)، _sa_photo_lead (نمای اول
 * کادر «مکان در یک نگاه») و _sa_photo_hide (برداشتن از صفحه بدون حذف از کتابخانه).
 * ---------------------------------------------------------------------- */

/**
 * mime هایی که پنل و مسیر بارگذاری مالک می‌پذیرند.
 *
 * @return string[]
 */
function sa_photo_admin_mimes() {
	return array( 'image/jpeg', 'image/webp', 'image/png' );
}

/**
 * آیا این بایت‌ها با mime اعلام‌شده یکی است؟ (جلوگیری از پوشیدن قالب فایل)
 *
 * @param string $bytes بایت‌های فایل.
 * @param string $mime  mime اعلام‌شده.
 * @return bool
 */
function sa_photo_bytes_match_mime( $bytes, $mime ) {
	if ( 'image/jpeg' === $mime ) {
		return "\xFF\xD8\xFF" === substr( $bytes, 0, 3 );
	}
	if ( 'image/webp' === $mime ) {
		return 'RIFF' === substr( $bytes, 0, 4 ) && 'WEBP' === substr( $bytes, 8, 4 );
	}
	if ( 'image/png' === $mime ) {
		return "\x89PNG" === substr( $bytes, 0, 4 );
	}
	return false;
}

/**
 * جایگزینی فایل یک پیوست با فایل پیوستی دیگر از کتابخانهٔ رسانه (تعویض دستی مالک).
 *
 * @param int $att_id پیوست مقصد (همان مکان، فایل تازه).
 * @param int $src_id پیوست منبع در کتابخانه.
 * @return true|WP_Error
 */
function sa_photo_replace_with_attachment( $att_id, $src_id ) {
	$att_id = absint( $att_id );
	$src_id = absint( $src_id );
	if ( ! $att_id || ! $src_id || 'attachment' !== get_post_type( $att_id ) || 'attachment' !== get_post_type( $src_id ) ) {
		return new WP_Error( 'sa_photo_swap_ids', 'پیوست مقصد یا منبع معتبر نیست.', array( 'status' => 400 ) );
	}
	$mime = (string) get_post_mime_type( $src_id );
	if ( ! in_array( $mime, sa_photo_admin_mimes(), true ) ) {
		return new WP_Error( 'sa_photo_swap_mime', 'فایل منبع jpeg/webp/png نیست.', array( 'status' => 415 ) );
	}
	$src_file = (string) get_attached_file( $src_id );
	if ( '' === $src_file || ! is_readable( $src_file ) ) {
		return new WP_Error( 'sa_photo_swap_file', 'فایل منبع روی سرور خوانده نمی‌شود.', array( 'status' => 404 ) );
	}
	$bytes = (string) file_get_contents( $src_file ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	if ( strlen( $bytes ) < 1200 || strlen( $bytes ) > 10 * MB_IN_BYTES || ! sa_photo_bytes_match_mime( $bytes, $mime ) ) {
		return new WP_Error( 'sa_photo_swap_bytes', 'فایل منبع سالم یا در سقف ۱۰ مگابایت نیست.', array( 'status' => 415 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$opt = sa_photo_optimize_bytes_to_webp( $bytes, SA_PHOTO_MAX_WEBP_BYTES );
	if ( is_wp_error( $opt ) ) {
		return $opt;
	}
	$old_file = (string) get_attached_file( $att_id );
	$old_meta = wp_get_attachment_metadata( $att_id );
	$city_id  = absint( get_post_meta( $att_id, '_sa_photo_city', true ) );
	$place    = (string) get_post_meta( $att_id, SA_PHOTO_META_PLACE, true );
	$filename = 'sa-photo-' . sanitize_title( '' !== $place ? $place : 'photo' ) . '-' . substr( md5( $src_file . microtime( true ) ), -6 ) . '.webp';
	$upload   = wp_upload_bits( $filename, null, $opt['bytes'] );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_swap_save', 'ذخیرهٔ فایل WebP تازه انجام نشد.', array( 'status' => 500 ) );
	}
	sa_photo_store_webp_meta( $att_id, $upload['file'], $opt['width'], $opt['height'], $opt['size'] );
	sa_photo_cleanup_old_files( $old_file, $upload['file'], is_array( $old_meta ) ? $old_meta : array(), $city_id );
	return true;
}


/**
 * REST: POST /photos/upload (v2.22.115)
 *
 * بدنه: {file_base64, filename, mime, city_id, place, kind, author, license,
 *        license_url, source_page, alt, note, points, highlight, tag, area,
 *        overlay, lead, order}
 *
 * برای پرونده‌هایی که خود مالک می‌فرستد و روی میزبان ویکی‌مدیا نیستند؛ سندباکس
 * ایجنت به سایت دسترسی ندارد، پس رانر گیت‌هاب بایت‌های پروندهٔ داخل مخزن را
 * base64 می‌فرستد. همهٔ قاعده‌های اعتبار (پدیدآور + مجوز مجاز + سقف ۳ مگابایت +
 * تطبیق بایت با mime) همین‌جا اجرا می‌شود؛ ارسال دوبارهٔ همان مکان، فایل همان
 * پیوست را عوض می‌کند (کلید بر اساس مکان، نه نشانی).
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_photo_upload( $r ) {
	$b        = (array) $r->get_json_params();
	$author   = isset( $b['author'] ) ? sanitize_text_field( (string) $b['author'] ) : '';
	$license  = isset( $b['license'] ) ? strtolower( sanitize_text_field( (string) $b['license'] ) ) : '';
	$city_id  = isset( $b['city_id'] ) ? absint( $b['city_id'] ) : 0;
	$place    = isset( $b['place'] ) ? sanitize_text_field( (string) $b['place'] ) : '';
	$kind     = isset( $b['kind'] ) ? sanitize_text_field( (string) $b['kind'] ) : 'heritage';
	$mime     = isset( $b['mime'] ) ? strtolower( sanitize_text_field( (string) $b['mime'] ) ) : '';
	$filename = isset( $b['filename'] ) ? sanitize_file_name( (string) $b['filename'] ) : 'owner-photo';
	$payload  = isset( $b['file_base64'] ) ? (string) $b['file_base64'] : '';

	if ( '' === $payload || '' === $author || '' === $license || '' === $place ) {
		return new WP_Error( 'sa_photo_missing', 'file_base64، author، license و place الزامی است.', array( 'status' => 400 ) );
	}
	if ( '' === sa_photo_license_label( $license ) ) {
		return new WP_Error( 'sa_photo_license', 'این مجوز برای انتشار مجاز نیست.', array( 'status' => 422 ) );
	}
	if ( ! in_array( $mime, sa_photo_admin_mimes(), true ) ) {
		return new WP_Error( 'sa_photo_mime', 'فقط فایل jpeg/webp/png پذیرفته می‌شود.', array( 'status' => 415 ) );
	}
	if ( ! $city_id || 'city' !== get_post_type( $city_id ) ) {
		return new WP_Error( 'sa_photo_city', 'شهرستان مقصد معتبر نیست.', array( 'status' => 400 ) );
	}
	$bytes = base64_decode( $payload, true );
	if ( false === $bytes || strlen( $bytes ) < 1200 || strlen( $bytes ) > 8 * MB_IN_BYTES ) {
		return new WP_Error( 'sa_photo_bytes', 'فایل خوانده نشد یا بیرون سقف ۸ مگابایت است.', array( 'status' => 415 ) );
	}
	if ( ! sa_photo_bytes_match_mime( $bytes, $mime ) ) {
		return new WP_Error( 'sa_photo_mime', 'محتوای فایل با نوع اعلام‌شده یکی نیست.', array( 'status' => 415 ) );
	}
	if ( ! in_array( $kind, array( 'heritage', 'fun', 'nature', 'modern' ), true ) ) {
		$kind = 'heritage';
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$opt = sa_photo_optimize_bytes_to_webp( $bytes, SA_PHOTO_MAX_WEBP_BYTES );
	if ( is_wp_error( $opt ) ) {
		return $opt;
	}
	$slug = sanitize_title( $place );
	$base = 'sa-photo-' . ( '' !== $slug ? $slug : 'photo' ) . '-' . substr( md5( $city_id . '|' . $place ), -6 );
	$key  = 'sa-photo:' . md5( $city_id . '|' . $place );

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array( array( 'key' => '_sa_photo_key', 'value' => $key ) ),
		)
	);
	$reused   = (bool) $existing;
	$att_id   = $reused ? (int) $existing[0] : 0;
	$old_file = $reused ? (string) get_attached_file( $att_id ) : '';
	$old_meta = $reused ? wp_get_attachment_metadata( $att_id ) : array();
	$upload   = wp_upload_bits( $base . ( $reused ? '-r' . gmdate( 'YmdHis' ) : '' ) . '.webp', null, $opt['bytes'] );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_upload', 'ذخیرهٔ فایل WebP انجام نشد.', array( 'status' => 500 ) );
	}
	if ( ! $reused ) {
		$att_id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/webp',
				'post_title'     => wp_slash( $place ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$city_id,
			true
		);
		if ( ! $att_id ) {
			@unlink( $upload['file'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new WP_Error( 'sa_photo_attachment', 'ثبت پیوست انجام نشد.', array( 'status' => 500 ) );
		}
	}
	sa_photo_store_webp_meta( $att_id, $upload['file'], $opt['width'], $opt['height'], $opt['size'], $place );
	if ( $reused && '' !== $old_file ) {
		sa_photo_cleanup_old_files( $old_file, $upload['file'], is_array( $old_meta ) ? $old_meta : array(), $city_id );
	}

	$origin = isset( $b['origin_url'] ) && '' !== trim( (string) $b['origin_url'] )
		? esc_url_raw( trim( (string) $b['origin_url'] ) )
		: ( 'owner-upload:' . $filename );
	sa_photo_write_meta( $att_id, $b, $city_id, $place, $kind, $key, '', $author, $license );
	update_post_meta( $att_id, '_sa_photo_origin', $origin );
	if ( isset( $b['order'] ) ) {
		update_post_meta( $att_id, '_sa_photo_order', max( 0, absint( $b['order'] ) ) );
	}
	if ( ! empty( $b['lead'] ) ) {
		foreach ( sa_photo_place_ids( $city_id, 60 ) as $other ) {
			if ( (int) $other !== $att_id ) {
				update_post_meta( (int) $other, '_sa_photo_lead', '' );
			}
		}
		update_post_meta( $att_id, '_sa_photo_lead', '1' );
	}

	return rest_ensure_response(
		array(
			'id'          => $att_id,
			'reused'      => $reused,
			'url'         => (string) wp_get_attachment_url( $att_id ),
			'overlay'     => sa_photo_overlay_label( $att_id ),
			'area'        => sa_photo_area( $att_id ),
			'mime'        => 'image/webp',
			'bytes'       => (int) $opt['size'],
			'width'       => (int) $opt['width'],
			'height'      => (int) $opt['height'],
			'passthrough' => (bool) $opt['passthrough'],
			'license'     => sa_photo_license_label( $license ),
		)
	);
}

/**
 * REST: POST /photos/optimize (v2.22.116)
 *
 * تبدیل دسته‌ای تصاویر موجود شهرستان‌ها به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت و
 * آزادسازی فضای زیراندازه‌ها و فایل‌های JPEG خام قدیمی (با پرچم صریح مالک).
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_photos_optimize( $r ) {
	$b = (array) $r->get_json_params();
	if ( empty( $b['owner_approved'] ) || true !== $b['owner_approved'] ) {
		return new WP_Error( 'sa_photo_need_owner', 'بهینه‌سازی تصاویر فقط با تأیید صریح مالک (owner_approved: true).', array( 'status' => 403 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$city_id = isset( $b['city_id'] ) ? absint( $b['city_id'] ) : 0;
	$limit   = isset( $b['limit'] ) ? max( 1, min( 60, absint( $b['limit'] ) ) ) : 30;
	$force   = ! empty( $b['force'] );
	$mq      = $city_id
		? array( array( 'key' => '_sa_photo_city', 'value' => $city_id ) )
		: array( array( 'key' => '_sa_photo_city', 'compare' => 'EXISTS' ) );

	$all_ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => sa_photo_admin_mimes(),
			'posts_per_page' => 2000,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => $mq,
		)
	);

	$only = array();
	foreach ( (array) ( $b['photo_ids'] ?? array() ) as $pid ) {
		if ( absint( $pid ) ) {
			$only[] = absint( $pid );
		}
	}
	if ( $only ) {
		$all_ids = array_values( array_intersect( array_map( 'intval', (array) $all_ids ), $only ) );
	}

	$pending = array();
	foreach ( array_map( 'intval', (array) $all_ids ) as $id ) {
		$file = (string) get_attached_file( $id );
		if ( '' === $file || ! is_file( $file ) ) {
			continue;
		}
		if ( $force ) {
			$pending[] = $id;
			continue;
		}
		$mime      = (string) get_post_mime_type( $id );
		$sz        = (int) @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$meta      = wp_get_attachment_metadata( $id );
		$w         = is_array( $meta ) && isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h         = is_array( $meta ) && isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		$rat       = $h > 0 ? ( $w / $h ) : 0.0;
		$has_sizes = is_array( $meta ) && ( ! empty( $meta['sizes'] ) || ! empty( $meta['original_image'] ) );
		if ( 'image/webp' !== $mime || $sz <= 0 || $sz > SA_PHOTO_MAX_WEBP_BYTES || $w > 960 || $rat < 1.22 || $rat > 1.48 || $has_sizes ) {
			$pending[] = $id;
		}
	}

	$batch       = array_slice( $pending, 0, $limit );
	$items       = array();
	$errors      = array();
	$saved_bytes = 0;
	foreach ( $batch as $id ) {
		$res = sa_photo_optimize_attachment( $id, $force );
		if ( is_wp_error( $res ) ) {
			$errors[] = array( 'id' => $id, 'error' => $res->get_error_message() );
			continue;
		}
		$saved_bytes += (int) ( $res['saved_bytes'] ?? 0 );
		$items[]      = $res;
	}

	return rest_ensure_response(
		array(
			'total'       => count( $all_ids ),
			'processed'   => count( $items ),
			'remaining'   => max( 0, count( $pending ) - count( $batch ) ),
			'saved_bytes' => $saved_bytes,
			'saved_kb'    => round( $saved_bytes / 1024, 1 ),
			'max_bytes'   => SA_PHOTO_MAX_WEBP_BYTES,
			'items'       => $items,
			'errors'      => $errors,
		)
	);
}

/**
 * REST: POST /photos/manage (v2.22.116)
 *
 * مدیریت شناسنامه، ترتیب، تصویر اول، پنهان‌کردن، جداکردن یا انتقال به زباله‌دان
 * (برگشت‌پذیر؛ هرگز حذف دائمی انجام نمی‌شود).
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_photos_manage( $r ) {
	$b = (array) $r->get_json_params();
	if ( empty( $b['owner_approved'] ) || true !== $b['owner_approved'] ) {
		return new WP_Error( 'sa_photo_need_owner', 'مدیریت تصاویر فقط با تأیید صریح مالک (owner_approved: true).', array( 'status' => 403 ) );
	}
	$att_id  = isset( $b['id'] ) ? absint( $b['id'] ) : 0;
	$city_id = $att_id ? absint( get_post_meta( $att_id, '_sa_photo_city', true ) ) : 0;
	if ( ! $att_id || ! $city_id ) {
		return new WP_Error( 'sa_photo_not_found', 'پیوست تصویر شهرستان یافت نشد.', array( 'status' => 404 ) );
	}
	$action = isset( $b['action'] ) ? sanitize_key( (string) $b['action'] ) : 'update';
	if ( 'hide' === $action ) {
		update_post_meta( $att_id, '_sa_photo_hide', '1' );
	} elseif ( 'unhide' === $action ) {
		update_post_meta( $att_id, '_sa_photo_hide', '' );
	} elseif ( 'lead' === $action ) {
		foreach ( sa_photo_place_ids( $city_id, 60 ) as $other ) {
			update_post_meta( (int) $other, '_sa_photo_lead', (int) $other === $att_id ? '1' : '' );
		}
	} elseif ( 'detach' === $action ) {
		foreach ( array( '_sa_photo_city', '_sa_photo_key', '_sa_photo_lead', '_sa_photo_order', '_sa_photo_hide' ) as $mk ) {
			delete_post_meta( $att_id, $mk );
		}
	} elseif ( 'trash' === $action ) {
		if ( ! current_user_can( 'delete_post', $att_id ) ) {
			return new WP_Error( 'sa_photo_cap', 'اجازهٔ انتقال این پیوست به زباله‌دان را ندارید.', array( 'status' => 403 ) );
		}
		delete_post_meta( $att_id, '_sa_photo_lead' );
		wp_trash_post( $att_id );
	} elseif ( 'update' === $action ) {
		foreach ( array(
			'place'     => SA_PHOTO_META_PLACE,
			'overlay'   => '_sa_photo_overlay',
			'area'      => SA_PHOTO_META_AREA,
			'highlight' => '_sa_photo_highlight',
			'tag'       => '_sa_photo_tag',
			'author'    => SA_PHOTO_META_AUTHOR,
		) as $field => $mk ) {
			if ( array_key_exists( $field, $b ) ) {
				update_post_meta( $att_id, $mk, wp_slash( sanitize_text_field( (string) $b[ $field ] ) ) );
			}
		}
		if ( array_key_exists( 'order', $b ) ) {
			update_post_meta( $att_id, '_sa_photo_order', max( 0, absint( $b['order'] ) ) );
		}
		if ( array_key_exists( 'hidden', $b ) ) {
			update_post_meta( $att_id, '_sa_photo_hide', ! empty( $b['hidden'] ) ? '1' : '' );
		}
		if ( ! empty( $b['lead'] ) ) {
			foreach ( sa_photo_place_ids( $city_id, 60 ) as $other ) {
				update_post_meta( (int) $other, '_sa_photo_lead', (int) $other === $att_id ? '1' : '' );
			}
		}
	} else {
		return new WP_Error( 'sa_photo_action', 'عملیات نامعتبر است (hide, unhide, lead, detach, trash, update).', array( 'status' => 400 ) );
	}
	return rest_ensure_response(
		array(
			'ok'      => true,
			'id'      => $att_id,
			'city_id' => $city_id,
			'action'  => $action,
		)
	);
}
