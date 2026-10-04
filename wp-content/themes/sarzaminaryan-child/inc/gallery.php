<?php
/**
 * Province/county gallery albums + automatic WebP optimisation.
 *
 * Every province page receives one gallery hub. Every county/city under the
 * province becomes a ready album even when it has no approved images yet.
 * Uploads that enter through the gallery manager or the citizen-contribution
 * form are validated, resized, watermarked, converted to WebP, and the original
 * file is removed before the attachment is registered in WordPress.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SA_GALLERY_TAXONOMY  = 'sa_gallery_album';
const SA_GALLERY_STATUS    = 'sa_gallery_status';
const SA_GALLERY_PROVINCE  = 'sa_gallery_province_id';
const SA_GALLERY_CITY      = 'sa_gallery_city_id';
const SA_GALLERY_SOURCE    = 'sa_gallery_source';
const SA_GALLERY_PLACE     = 'sa_gallery_place';
const SA_GALLERY_WATERMARK = 'sarzaminaryan';

/**
 * Register the internal album taxonomy on attachments.
 */
function sa_gallery_register_taxonomy() {
	register_taxonomy(
		SA_GALLERY_TAXONOMY,
		'attachment',
		array(
			'label'             => 'آلبوم‌های گالری',
			'labels'            => array(
				'name'          => 'آلبوم‌های گالری',
				'singular_name' => 'آلبوم گالری',
			),
			'public'            => false,
			'show_ui'           => false,
			'show_admin_column' => false,
			'hierarchical'      => true,
			'rewrite'           => false,
			'query_var'         => false,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'sa_gallery_register_taxonomy', 5 );

/**
 * Province for a city post.
 *
 * @param int $city_id City/county post ID.
 * @return int
 */
function sa_gallery_city_province_id( $city_id ) {
	$city_id = absint( $city_id );
	if ( ! $city_id || 'city' !== get_post_type( $city_id ) ) {
		return 0;
	}

	$province_id = (int) get_post_meta( $city_id, 'sa_province_id', true );
	return $province_id && 'province' === get_post_type( $province_id ) ? $province_id : 0;
}

/**
 * Album slug for province/city.
 *
 * @param int $province_id Province post ID.
 * @param int $city_id     Optional city post ID.
 * @return string
 */
function sa_gallery_album_slug( $province_id, $city_id = 0 ) {
	$province = get_post( $province_id );
	$city     = $city_id ? get_post( $city_id ) : null;
	$p_slug   = $province ? $province->post_name : 'province-' . absint( $province_id );

	if ( $city ) {
		return sanitize_title( $p_slug . '-' . $city->post_name );
	}

	return sanitize_title( 'province-' . $p_slug );
}

/**
 * Ensure taxonomy terms exist for the province and city album.
 *
 * @param int $province_id Province post ID.
 * @param int $city_id     Optional city post ID.
 * @return array{province:int,city:int}
 */
function sa_gallery_ensure_album_terms( $province_id, $city_id = 0 ) {
	$province_id = absint( $province_id );
	$city_id     = absint( $city_id );
	$result      = array( 'province' => 0, 'city' => 0 );

	if ( ! $province_id || ! taxonomy_exists( SA_GALLERY_TAXONOMY ) ) {
		return $result;
	}

	$province_name = get_the_title( $province_id );
	$province_slug = sa_gallery_album_slug( $province_id );
	$province_term = term_exists( $province_slug, SA_GALLERY_TAXONOMY );
	if ( ! $province_term ) {
		$province_term = wp_insert_term(
			$province_name ? 'گالری ' . $province_name : 'گالری استان',
			SA_GALLERY_TAXONOMY,
			array(
				'slug'        => $province_slug,
				'description' => (string) $province_id,
			)
		);
	}
	if ( ! is_wp_error( $province_term ) ) {
		$result['province'] = is_array( $province_term ) ? (int) $province_term['term_id'] : (int) $province_term;
	}

	if ( $city_id ) {
		$city_name = get_the_title( $city_id );
		$city_slug = sa_gallery_album_slug( $province_id, $city_id );
		$city_term = term_exists( $city_slug, SA_GALLERY_TAXONOMY );
		if ( ! $city_term ) {
			$city_term = wp_insert_term(
				$city_name ? 'آلبوم ' . $city_name : 'آلبوم شهرستان',
				SA_GALLERY_TAXONOMY,
				array(
					'slug'        => $city_slug,
					'parent'      => $result['province'],
					'description' => (string) $city_id,
				)
			);
		}
		if ( ! is_wp_error( $city_term ) ) {
			$result['city'] = is_array( $city_term ) ? (int) $city_term['term_id'] : (int) $city_term;
		}
	}

	return $result;
}

/**
 * Supported gallery upload mimes.
 *
 * @return string[]
 */
function sa_gallery_allowed_mimes() {
	return array( 'image/jpeg', 'image/png', 'image/webp' );
}

/**
 * Maximum incoming upload size.
 *
 * @param string $source admin|citizen.
 * @return int Bytes.
 */
function sa_gallery_max_upload_bytes( $source = 'admin' ) {
	$mb = 'citizen' === $source ? 5 : 15;
	return (int) apply_filters( 'sa_gallery_max_upload_bytes', $mb * MB_IN_BYTES, $source );
}

/**
 * Target bytes for the optimised downloadable WebP.
 *
 * @param string $source admin|citizen.
 * @return int Bytes.
 */
function sa_gallery_target_bytes( $source = 'admin' ) {
	$kb = 'citizen' === $source ? 220 : 260;
	return (int) apply_filters( 'sa_gallery_target_bytes', $kb * 1024, $source );
}

/**
 * Whether the host can perform the required gallery conversion.
 *
 * @return bool
 */
function sa_gallery_can_process_images() {
	return function_exists( 'imagewebp' ) && function_exists( 'imagecreatetruecolor' );
}

/**
 * Find a stable TTF font for the English watermark.
 *
 * @return string
 */
function sa_gallery_watermark_font() {
	if ( defined( 'SA_GALLERY_WATERMARK_FONT' ) && is_readable( SA_GALLERY_WATERMARK_FONT ) ) {
		return SA_GALLERY_WATERMARK_FONT;
	}

	$paths = array(
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-ExtraLight.ttf',
		'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
		'/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
		'/usr/share/fonts/truetype/freefont/FreeSans.ttf',
	);

	foreach ( $paths as $path ) {
		if ( is_readable( $path ) ) {
			return $path;
		}
	}

	return '';
}

/**
 * Load an image file into a GD resource.
 *
 * @param string $path File path.
 * @param string $mime Detected mime.
 * @return GdImage|resource|false
 */
function sa_gallery_gd_from_file( $path, $mime ) {
	switch ( $mime ) {
		case 'image/jpeg':
			return function_exists( 'imagecreatefromjpeg' ) ? imagecreatefromjpeg( $path ) : false;
		case 'image/png':
			return function_exists( 'imagecreatefrompng' ) ? imagecreatefrompng( $path ) : false;
		case 'image/webp':
			return function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false;
		default:
			return false;
	}
}


/**
 * Respect JPEG orientation before metadata is stripped by re-encoding.
 *
 * @param GdImage|resource $image Image resource.
 * @param string           $path  File path.
 * @param string           $mime  Mime type.
 * @return GdImage|resource
 */
function sa_gallery_normalise_orientation( $image, $path, $mime ) {
	if ( 'image/jpeg' !== $mime || ! function_exists( 'exif_read_data' ) || ! function_exists( 'imagerotate' ) ) {
		return $image;
	}

	$exif = @exif_read_data( $path );
	if ( empty( $exif['Orientation'] ) ) {
		return $image;
	}

	$orientation = (int) $exif['Orientation'];
	$rotated     = false;
	switch ( $orientation ) {
		case 3:
			$rotated = imagerotate( $image, 180, 0 );
			break;
		case 6:
			$rotated = imagerotate( $image, -90, 0 );
			break;
		case 8:
			$rotated = imagerotate( $image, 90, 0 );
			break;
	}

	if ( $rotated ) {
		imagedestroy( $image );
		return $rotated;
	}

	return $image;
}

/**
 * Letter-spaced TTF text width.
 *
 * @param string $text    Text.
 * @param int    $size    Font size.
 * @param string $font    Font path.
 * @param int    $spacing Extra pixels between letters.
 * @return int
 */
function sa_gallery_ttf_letter_width( $text, $size, $font, $spacing ) {
	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	$width = 0;
	foreach ( $chars as $char ) {
		$box = imagettfbbox( $size, 0, $font, $char );
		if ( is_array( $box ) ) {
			$width += abs( $box[2] - $box[0] );
		}
	}
	return $width + max( 0, count( $chars ) - 1 ) * $spacing;
}

/**
 * Draw thin white sarzaminaryan watermark on the lower centre of the canvas.
 *
 * @param GdImage|resource $image Image resource.
 * @param int              $width Width.
 * @param int              $height Height.
 * @return void
 */
function sa_gallery_apply_watermark( $image, $width, $height ) {
	$band_height = max( 42, min( 72, (int) round( $height * 0.075 ) ) );
	$band_top    = max( 0, $height - $band_height );

	$navy = imagecolorallocatealpha( $image, 1, 31, 61, 46 );
	imagefilledrectangle( $image, 0, $band_top, $width, $height, $navy );

	$text    = SA_GALLERY_WATERMARK;
	$font    = sa_gallery_watermark_font();
	$spacing = max( 2, (int) round( $width / 420 ) );

	if ( $font && function_exists( 'imagettftext' ) ) {
		$size = max( 14, min( 24, (int) round( $width / 58 ) ) );
		while ( $size > 11 && sa_gallery_ttf_letter_width( $text, $size, $font, $spacing ) > $width * 0.62 ) {
			$size--;
		}
		$total_width = sa_gallery_ttf_letter_width( $text, $size, $font, $spacing );
		$x           = (int) round( ( $width - $total_width ) / 2 );
		$baseline    = (int) round( $band_top + ( $band_height + $size ) / 2 - 4 );
		$shadow      = imagecolorallocatealpha( $image, 0, 0, 0, 68 );
		$white       = imagecolorallocatealpha( $image, 255, 255, 255, 0 );
		$chars       = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $chars as $char ) {
			imagettftext( $image, $size, 0, $x + 1, $baseline + 1, $shadow, $font, $char );
			imagettftext( $image, $size, 0, $x, $baseline, $white, $font, $char );
			$box = imagettfbbox( $size, 0, $font, $char );
			$x  += ( is_array( $box ) ? abs( $box[2] - $box[0] ) : $size ) + $spacing;
		}
		return;
	}

	$white = imagecolorallocate( $image, 255, 255, 255 );
	$font  = 3;
	$x     = max( 8, (int) round( ( $width - imagefontwidth( $font ) * strlen( $text ) ) / 2 ) );
	$y     = (int) round( $band_top + ( $band_height - imagefontheight( $font ) ) / 2 );
	imagestring( $image, $font, $x, $y, $text, $white );
}

/**
 * Create a resized, watermarked canvas.
 *
 * @param GdImage|resource $source Original image.
 * @param int              $src_w  Source width.
 * @param int              $src_h  Source height.
 * @param int              $edge   Max long edge.
 * @return array{0:GdImage|resource,1:int,2:int}|null
 */
function sa_gallery_resized_canvas( $source, $src_w, $src_h, $edge ) {
	$ratio = min( 1, $edge / max( $src_w, $src_h ) );
	$new_w = max( 1, (int) round( $src_w * $ratio ) );
	$new_h = max( 1, (int) round( $src_h * $ratio ) );
	$dst   = imagecreatetruecolor( $new_w, $new_h );
	if ( ! $dst ) {
		return null;
	}
	$white = imagecolorallocate( $dst, 255, 255, 255 );
	imagefilledrectangle( $dst, 0, 0, $new_w, $new_h, $white );
	imagecopyresampled( $dst, $source, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h );
	sa_gallery_apply_watermark( $dst, $new_w, $new_h );

	return array( $dst, $new_w, $new_h );
}

/**
 * Optimise a local file into one downloadable WebP with watermark.
 *
 * @param string $source_path Source uploaded path.
 * @param string $dest_path   Destination .webp path.
 * @param array  $args        Options: source.
 * @return true|WP_Error
 */
function sa_gallery_optimise_to_webp( $source_path, $dest_path, $args = array() ) {
	if ( ! sa_gallery_can_process_images() ) {
		return new WP_Error( 'sa_gallery_no_webp', 'سرور امکان تبدیل امن تصاویر به WebP را ندارد.' );
	}

	$mime = wp_get_image_mime( $source_path );
	if ( ! in_array( $mime, sa_gallery_allowed_mimes(), true ) ) {
		return new WP_Error( 'sa_gallery_bad_mime', 'نوع واقعی فایل تصویر مجاز نیست.' );
	}

	$size = @getimagesize( $source_path );
	if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
		return new WP_Error( 'sa_gallery_bad_image', 'ابعاد تصویر قابل خواندن نیست.' );
	}

	$source = sa_gallery_gd_from_file( $source_path, $mime );
	if ( ! $source ) {
		return new WP_Error( 'sa_gallery_open_failed', 'پردازشگر تصویر نتوانست فایل را باز کند.' );
	}

	$source     = sa_gallery_normalise_orientation( $source, $source_path, $mime );
	$src_w      = imagesx( $source );
	$src_h      = imagesy( $source );
	wp_mkdir_p( dirname( $dest_path ) );
	$target     = sa_gallery_target_bytes( isset( $args['source'] ) ? (string) $args['source'] : 'admin' );
	$edges      = (array) apply_filters( 'sa_gallery_optimisation_edges', array( 1600, 1440, 1280, 1200 ), $src_w, $src_h, $args );
	$qualities  = (array) apply_filters( 'sa_gallery_optimisation_qualities', array( 84, 80, 76, 72, 68 ), $args );
	$best_tmp   = '';
	$best_size  = PHP_INT_MAX;
	$best_saved = false;
	$base_tmp   = trailingslashit( dirname( $dest_path ) ) . '.sa-gallery-' . wp_generate_password( 8, false, false );

	foreach ( $edges as $edge ) {
		$canvas = sa_gallery_resized_canvas( $source, $src_w, $src_h, absint( $edge ) );
		if ( ! $canvas ) {
			continue;
		}
		foreach ( $qualities as $quality ) {
			$tmp = $base_tmp . '-' . absint( $edge ) . '-' . absint( $quality ) . '.webp';
			if ( imagewebp( $canvas[0], $tmp, absint( $quality ) ) ) {
				$bytes = filesize( $tmp );
				if ( $bytes && $bytes < $best_size ) {
					if ( $best_tmp && $best_tmp !== $tmp && file_exists( $best_tmp ) ) {
						@unlink( $best_tmp );
					}
					$best_tmp   = $tmp;
					$best_size  = $bytes;
					$best_saved = true;
				} elseif ( file_exists( $tmp ) ) {
					@unlink( $tmp );
				}
				if ( $bytes && $bytes <= $target ) {
					imagedestroy( $canvas[0] );
					break 2;
				}
			}
		}
		imagedestroy( $canvas[0] );
	}

	imagedestroy( $source );

	if ( ! $best_saved || ! $best_tmp || ! file_exists( $best_tmp ) ) {
		return new WP_Error( 'sa_gallery_save_failed', 'ذخیره نسخه WebP انجام نشد.' );
	}

	if ( ! @rename( $best_tmp, $dest_path ) ) {
		@copy( $best_tmp, $dest_path );
		@unlink( $best_tmp );
	}

	return file_exists( $dest_path ) ? true : new WP_Error( 'sa_gallery_move_failed', 'انتقال تصویر به پوشه گالری انجام نشد.' );
}

/**
 * Build a clean destination path under uploads/sa-gallery/{province}/{city}/.
 *
 * @param int    $province_id Province post ID.
 * @param int    $city_id     City post ID.
 * @param string $original    Original filename.
 * @return array{file:string,url:string,relative:string}
 */
function sa_gallery_destination( $province_id, $city_id, $original ) {
	$uploads  = wp_upload_dir();
	$province = get_post( $province_id );
	$city     = get_post( $city_id );
	$p_slug   = $province ? $province->post_name : 'province-' . absint( $province_id );
	$c_slug   = $city ? $city->post_name : 'general';
	$base     = sanitize_title( pathinfo( $original, PATHINFO_FILENAME ) );
	if ( '' === $base ) {
		$base = 'gallery';
	}
	$name = sanitize_file_name( $c_slug . '-' . gmdate( 'Ymd-His' ) . '-' . substr( wp_generate_password( 6, false, false ), 0, 6 ) . '-' . $base . '.webp' );
	$rel  = 'sa-gallery/' . sanitize_title( $p_slug ) . '/' . sanitize_title( $c_slug ) . '/' . $name;

	return array(
		'file'     => trailingslashit( $uploads['basedir'] ) . $rel,
		'url'      => trailingslashit( $uploads['baseurl'] ) . $rel,
		'relative' => $rel,
	);
}

/**
 * Convert, watermark, register and album-tag one uploaded gallery image.
 *
 * @param array $file Upload array for one image.
 * @param array $args Options: city_id, province_id, status, source, caption, place.
 * @return int|WP_Error Attachment ID.
 */
function sa_gallery_handle_upload( $file, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'city_id'     => 0,
			'province_id' => 0,
			'source'      => 'admin',
			'status'      => 'approved',
			'caption'     => '',
			'place'       => '',
		)
	);

	if ( empty( $file ) || ! is_array( $file ) || ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) ) {
		return 0;
	}
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'sa_gallery_no_file', 'فایل معتبر دریافت نشد.' );
	}
	if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'sa_gallery_upload_error', 'بارگذاری تصویر با خطا روبه‌رو شد.' );
	}
	if ( ! empty( $file['size'] ) && (int) $file['size'] > sa_gallery_max_upload_bytes( $args['source'] ) ) {
		return new WP_Error( 'sa_gallery_too_large', 'حجم تصویر بیشتر از سقف مجاز گالری است.' );
	}

	$city_id = absint( $args['city_id'] );
	if ( ! $city_id || 'city' !== get_post_type( $city_id ) ) {
		return new WP_Error( 'sa_gallery_bad_city', 'شهرستان/شهر معتبر انتخاب نشده است.' );
	}
	$province_id = absint( $args['province_id'] );
	if ( ! $province_id ) {
		$province_id = sa_gallery_city_province_id( $city_id );
	}
	if ( ! $province_id ) {
		return new WP_Error( 'sa_gallery_bad_province', 'استان این آلبوم پیدا نشد.' );
	}

	$mime = wp_get_image_mime( $file['tmp_name'] );
	if ( ! in_array( $mime, sa_gallery_allowed_mimes(), true ) ) {
		return new WP_Error( 'sa_gallery_bad_mime', 'فقط JPG، PNG و WebP واقعی برای گالری پذیرفته می‌شود.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$upload = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			),
		)
	);
	if ( isset( $upload['error'] ) ) {
		return new WP_Error( 'sa_gallery_upload_failed', $upload['error'] );
	}

	$dest = sa_gallery_destination( $province_id, $city_id, isset( $file['name'] ) ? (string) $file['name'] : 'gallery.webp' );
	$ok   = sa_gallery_optimise_to_webp( $upload['file'], $dest['file'], $args );
	@unlink( $upload['file'] ); // raw JPG/PNG/WebP should not remain in uploads.
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}

	$place   = sanitize_text_field( $args['place'] );
	$caption = sanitize_textarea_field( $args['caption'] );
	$title   = $place ? $place : sprintf( 'گالری تصاویر %s', get_the_title( $city_id ) );
	$status  = in_array( $args['status'], array( 'approved', 'pending', 'rejected' ), true ) ? $args['status'] : 'pending';

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/webp',
			'post_title'     => $title,
			'post_excerpt'   => $caption,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $city_id,
		),
		$dest['file'],
		$city_id,
		true
	);
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $dest['file'] );
		return $attachment_id;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $dest['relative'] );
	update_post_meta( $attachment_id, SA_GALLERY_PROVINCE, $province_id );
	update_post_meta( $attachment_id, SA_GALLERY_CITY, $city_id );
	update_post_meta( $attachment_id, SA_GALLERY_STATUS, $status );
	update_post_meta( $attachment_id, SA_GALLERY_SOURCE, sanitize_key( $args['source'] ) );
	if ( $place ) {
		update_post_meta( $attachment_id, SA_GALLERY_PLACE, $place );
	}
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', sprintf( 'تصویر %1$s در شهرستان %2$s، استان %3$s - سرزمین آریان', $place ? $place : 'گالری', get_the_title( $city_id ), get_the_title( $province_id ) ) );

	$terms = sa_gallery_ensure_album_terms( $province_id, $city_id );
	if ( ! empty( $terms['city'] ) ) {
		wp_set_object_terms( $attachment_id, array( (int) $terms['city'] ), SA_GALLERY_TAXONOMY, false );
	}

	$metadata = wp_generate_attachment_metadata( $attachment_id, $dest['file'] );
	if ( is_array( $metadata ) ) {
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}
	sa_gallery_flush_cache( $province_id, $city_id );

	return (int) $attachment_id;
}

/**
 * Flush album caches after gallery changes.
 *
 * @param int $province_id Province ID.
 * @param int $city_id     City ID.
 * @return void
 */
function sa_gallery_flush_cache( $province_id = 0, $city_id = 0 ) {
	delete_transient( 'sa_gallery_album_' . absint( $province_id ) . '_' . absint( $city_id ) );
	delete_transient( 'sa_gallery_counts_' . absint( $province_id ) );
}

/**
 * Query approved images for one album.
 *
 * @param int $province_id Province ID.
 * @param int $city_id     City ID.
 * @param int $limit       Limit.
 * @return int[]
 */
function sa_gallery_image_ids( $province_id, $city_id, $limit = 40 ) {
	$province_id = absint( $province_id );
	$city_id     = absint( $city_id );
	$cache_key   = 'sa_gallery_album_' . $province_id . '_' . $city_id;
	$cached      = get_transient( $cache_key );
	if ( false !== $cached ) {
		return array_slice( array_map( 'absint', (array) $cached ), 0, $limit );
	}

	$ids = get_posts(
		array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'post_mime_type'   => 'image/webp',
			'posts_per_page'   => 120,
			'fields'           => 'ids',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => SA_GALLERY_PROVINCE,
					'value' => $province_id,
				),
				array(
					'key'   => SA_GALLERY_CITY,
					'value' => $city_id,
				),
				array(
					'key'   => SA_GALLERY_STATUS,
					'value' => 'approved',
				),
			),
		)
	);
	set_transient( $cache_key, $ids, 6 * HOUR_IN_SECONDS );

	return array_slice( array_map( 'absint', $ids ), 0, $limit );
}

/**
 * Prepare one attachment for the front-end lightbox.
 *
 * @param int $attachment_id Attachment ID.
 * @return array|null
 */
function sa_gallery_image_payload( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	$post          = get_post( $attachment_id );
	if ( ! $post || 'attachment' !== $post->post_type ) {
		return null;
	}

	$full  = wp_get_attachment_image_src( $attachment_id, 'full' );
	$large = wp_get_attachment_image_src( $attachment_id, 'sa-gallery-large' );
	$thumb = wp_get_attachment_image_src( $attachment_id, 'sa-gallery-card' );
	$alt   = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	return array(
		'id'       => $attachment_id,
		'src'      => $large ? $large[0] : ( $full ? $full[0] : wp_get_attachment_url( $attachment_id ) ),
		'full'     => $full ? $full[0] : wp_get_attachment_url( $attachment_id ),
		'thumb'    => $thumb ? $thumb[0] : ( $large ? $large[0] : wp_get_attachment_url( $attachment_id ) ),
		'width'    => $large ? (int) $large[1] : 0,
		'height'   => $large ? (int) $large[2] : 0,
		'alt'      => $alt ? $alt : $post->post_title,
		'place'    => get_post_meta( $attachment_id, SA_GALLERY_PLACE, true ) ?: $post->post_title,
		'caption'  => $post->post_excerpt,
		'download' => $full ? $full[0] : wp_get_attachment_url( $attachment_id ),
	);
}

/**
 * Province album data for one city.
 *
 * @param int $province_id Province ID.
 * @param int $city_id     City ID.
 * @return array
 */
function sa_gallery_album_payload( $province_id, $city_id ) {
	$ids    = sa_gallery_image_ids( $province_id, $city_id, 80 );
	$images = array();
	foreach ( $ids as $id ) {
		$image = sa_gallery_image_payload( $id );
		if ( $image ) {
			$images[] = $image;
		}
	}

	$cover = $images ? $images[0]['thumb'] : '';
	if ( ! $cover && has_post_thumbnail( $city_id ) ) {
		$cover_src = wp_get_attachment_image_src( get_post_thumbnail_id( $city_id ), 'sa-gallery-card' );
		$cover     = $cover_src ? $cover_src[0] : '';
	}

	return array(
		'provinceId'    => absint( $province_id ),
		'cityId'        => absint( $city_id ),
		'title'         => 'آلبوم تصاویر ' . get_the_title( $city_id ),
		'provinceTitle' => get_the_title( $province_id ),
		'cityTitle'     => get_the_title( $city_id ),
		'count'         => count( $images ),
		'cover'         => $cover,
		'images'        => $images,
	);
}

/**
 * Render gallery section for province pages or the current city page.
 *
 * @param int    $post_id Entity post ID.
 * @param string $context province|city.
 * @return void
 */
function sa_gallery_render_section( $post_id = 0, $context = '' ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	$type    = $context ? $context : get_post_type( $post_id );

	if ( 'province' !== $type && 'city' !== $type ) {
		return;
	}

	if ( 'city' === $type ) {
		$province_id = sa_gallery_city_province_id( $post_id );
		$cities      = $province_id ? array( get_post( $post_id ) ) : array();
	} else {
		$province_id = $post_id;
		$cities      = sa_get_children( $province_id, 'city' );
	}

	if ( ! $province_id || ! $cities ) {
		return;
	}

	$section_id = 'sa-gallery-' . absint( $post_id );
	$albums     = array();
	foreach ( $cities as $city ) {
		if ( ! $city instanceof WP_Post ) {
			$city = get_post( $city );
		}
		if ( ! $city || 'city' !== $city->post_type ) {
			continue;
		}
		sa_gallery_ensure_album_terms( $province_id, $city->ID );
		$albums[] = sa_gallery_album_payload( $province_id, $city->ID );
	}

	if ( ! $albums ) {
		return;
	}

	$headline = 'province' === $type ? 'گالری تصاویر استان ' . get_the_title( $province_id ) : 'گالری تصاویر ' . get_the_title( $post_id );
	$intro    = 'province' === $type ? 'هر شهرستان یک آلبوم آماده دارد؛ تصاویر بهینه، سبک و دارای نشان سرزمین آریان هستند.' : 'تصاویر این شهرستان با لمس یا کشیدن آرام به تصویر بعدی می‌روند.';
	?>
	<section class="sa-gallery" id="<?php echo esc_attr( $section_id ); ?>" data-sa-gallery-section aria-label="<?php echo esc_attr( $headline ); ?>">
		<div class="sa-gallery__topbar">
			<span class="sa-gallery__pulse" aria-hidden="true"></span>
			<span class="sa-gallery__eyebrow"><?php esc_html_e( 'آلبوم تصاویر', 'sarzaminaryan-child' ); ?></span>
			<span class="sa-gallery__crumb"><?php echo esc_html( 'استان ' . get_the_title( $province_id ) ); ?></span>
		</div>
		<div class="sa-gallery__head">
			<div>
				<h2 class="sa-gallery__title"><?php echo esc_html( $headline ); ?></h2>
				<p class="sa-gallery__intro"><?php echo esc_html( $intro ); ?></p>
			</div>
			<span class="sa-gallery__hint"><?php esc_html_e( 'لمس/سوایپ برای تماشا', 'sarzaminaryan-child' ); ?></span>
		</div>
		<div class="sa-gallery__albums">
			<?php foreach ( $albums as $index => $album ) : ?>
				<?php $album_id = $section_id . '-album-' . absint( $album['cityId'] ); ?>
				<article class="sa-gallery-card<?php echo $album['count'] ? '' : ' is-empty'; ?>" data-sa-gallery-album="<?php echo esc_attr( $album_id ); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr( $album['title'] ); ?>">
					<div class="sa-gallery-card__bar"><span class="sa-gallery-card__dot" aria-hidden="true"></span><span><?php echo esc_html( 'آلبوم ' . $album['cityTitle'] ); ?></span></div>
					<div class="sa-gallery-card__media">
						<?php if ( $album['cover'] ) : ?>
							<img src="<?php echo esc_url( $album['cover'] ); ?>" alt="<?php echo esc_attr( $album['title'] ); ?>" loading="lazy" decoding="async">
						<?php else : ?>
							<div class="sa-gallery-card__placeholder" aria-hidden="true"><span><?php esc_html_e( 'آلبوم آماده', 'sarzaminaryan-child' ); ?></span></div>
						<?php endif; ?>
						<span class="sa-gallery-card__watermark">sarzaminaryan</span>
					</div>
					<div class="sa-gallery-card__body">
						<h3><?php echo esc_html( $album['cityTitle'] ); ?></h3>
						<p><?php echo $album['count'] ? esc_html( sprintf( '%s تصویر آماده نمایش', sa_fa_digits( $album['count'] ) ) ) : esc_html__( 'هنوز تصویری تأیید نشده؛ آلبوم آماده دریافت تصویر است.', 'sarzaminaryan-child' ); ?></p>
					</div>
				</article>
				<script type="application/json" id="<?php echo esc_attr( $album_id ); ?>"><?php echo wp_json_encode( $album, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?></script>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Admin menu for batch gallery uploads.
 */
function sa_gallery_admin_menu() {
	add_submenu_page(
		'upload.php',
		'گالری استان‌ها',
		'گالری استان‌ها',
		'upload_files',
		'sa-gallery',
		'sa_gallery_admin_page'
	);
}
add_action( 'admin_menu', 'sa_gallery_admin_menu' );

/**
 * Convert $_FILES multi upload to individual upload arrays.
 *
 * @param array $files Field from $_FILES.
 * @return array[]
 */
function sa_gallery_normalise_files_array( $files ) {
	$out = array();
	if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) {
		return $out;
	}
	foreach ( $files['name'] as $i => $name ) {
		$out[] = array(
			'name'     => $name,
			'type'     => isset( $files['type'][ $i ] ) ? $files['type'][ $i ] : '',
			'tmp_name' => isset( $files['tmp_name'][ $i ] ) ? $files['tmp_name'][ $i ] : '',
			'error'    => isset( $files['error'][ $i ] ) ? $files['error'][ $i ] : UPLOAD_ERR_NO_FILE,
			'size'     => isset( $files['size'][ $i ] ) ? $files['size'][ $i ] : 0,
		);
	}
	return $out;
}

/**
 * Gallery manager admin page.
 */
function sa_gallery_admin_page() {
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}

	$notice = '';
	if ( isset( $_POST['sa_gallery_upload_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_gallery_upload_nonce'] ) ), 'sa_gallery_upload' ) ) {
		$city_id     = isset( $_POST['sa_gallery_city_id'] ) ? absint( $_POST['sa_gallery_city_id'] ) : 0;
		$province_id = sa_gallery_city_province_id( $city_id );
		$place       = isset( $_POST['sa_gallery_place'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_gallery_place'] ) ) : '';
		$caption     = isset( $_POST['sa_gallery_caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sa_gallery_caption'] ) ) : '';
		$done        = 0;
		$errors      = array();

		foreach ( isset( $_FILES['sa_gallery_images'] ) ? sa_gallery_normalise_files_array( $_FILES['sa_gallery_images'] ) : array() as $file ) {
			$result = sa_gallery_handle_upload(
				$file,
				array(
					'city_id'     => $city_id,
					'province_id' => $province_id,
					'place'       => $place,
					'caption'     => $caption,
					'source'      => 'admin',
					'status'      => 'approved',
				)
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} elseif ( $result ) {
				$done++;
			}
		}

		$notice = sprintf( 'تعداد %s تصویر به آلبوم اضافه شد.', sa_fa_digits( $done ) );
		if ( $errors ) {
			$notice .= ' خطاها: ' . implode( ' | ', array_map( 'esc_html', $errors ) );
		}
	}

	$cities = get_posts(
		array(
			'post_type'      => 'city',
			'post_status'    => 'publish',
			'posts_per_page' => 800,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	?>
	<div class="wrap sa-gallery-admin" dir="rtl">
		<h1><?php esc_html_e( 'گالری استان‌ها', 'sarzaminaryan-child' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! sa_gallery_can_process_images() ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'روی این سرور GD/WebP فعال نیست؛ تا فعال نشود تبدیل خودکار گالری انجام نمی‌شود.', 'sarzaminaryan-child' ); ?></p></div>
		<?php endif; ?>
		<div class="card" style="max-width:960px;border-radius:16px;border:1px solid #d6dde7;box-shadow:0 12px 32px rgba(1,31,61,.08);">
			<h2><?php esc_html_e( 'آپلود گروهی تصویر در آلبوم شهرستان', 'sarzaminaryan-child' ); ?></h2>
			<p><?php esc_html_e( 'تصاویر JPG/PNG/WebP به‌صورت خودکار به WebP سبک، دارای نشان sarzaminaryan و آماده دانلود تبدیل می‌شوند؛ فایل خام حذف می‌شود.', 'sarzaminaryan-child' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'sa_gallery_upload', 'sa_gallery_upload_nonce' ); ?>
				<table class="form-table" role="presentation"><tbody>
					<tr><th scope="row"><label for="sa_gallery_city_id"><?php esc_html_e( 'آلبوم شهرستان', 'sarzaminaryan-child' ); ?></label></th><td>
						<select id="sa_gallery_city_id" name="sa_gallery_city_id" required style="min-width:320px;">
							<option value=""><?php esc_html_e( 'انتخاب کنید…', 'sarzaminaryan-child' ); ?></option>
							<?php foreach ( $cities as $city ) : ?>
								<?php $province_id = sa_gallery_city_province_id( $city->ID ); ?>
								<option value="<?php echo esc_attr( $city->ID ); ?>"><?php echo esc_html( get_the_title( $province_id ) . ' — ' . get_the_title( $city ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th scope="row"><label for="sa_gallery_place"><?php esc_html_e( 'نام مکان', 'sarzaminaryan-child' ); ?></label></th><td><input id="sa_gallery_place" name="sa_gallery_place" type="text" class="regular-text" placeholder="مثلاً میدان امیرچخماق"></td></tr>
					<tr><th scope="row"><label for="sa_gallery_caption"><?php esc_html_e( 'توضیح تصویر', 'sarzaminaryan-child' ); ?></label></th><td><textarea id="sa_gallery_caption" name="sa_gallery_caption" rows="4" class="large-text" placeholder="توضیح کوتاه؛ زیر تصویر در گالری نمایش داده می‌شود."></textarea></td></tr>
					<tr><th scope="row"><label for="sa_gallery_images"><?php esc_html_e( 'تصاویر', 'sarzaminaryan-child' ); ?></label></th><td><input id="sa_gallery_images" name="sa_gallery_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required><p class="description"><?php esc_html_e( 'حداکثر ۱۵ مگابایت برای هر تصویر مدیر؛ خروجی بهینه معمولاً حدود ۲۶۰KB یا کمتر هدف‌گذاری می‌شود.', 'sarzaminaryan-child' ); ?></p></td></tr>
				</tbody></table>
				<?php submit_button( 'پردازش و افزودن به آلبوم' ); ?>
			</form>
		</div>
	</div>
	<?php
}

/**
 * Keep gallery status in sync with citizen contribution review decisions.
 *
 * @param int    $submission_id Submission post ID.
 * @param string $status        New status.
 * @return void
 */
function sa_gallery_sync_contribution_image_status( $submission_id, $status ) {
	$image_id = (int) get_post_meta( $submission_id, 'cc_image_id', true );
	if ( ! $image_id ) {
		return;
	}
	if ( 'publish' === $status ) {
		update_post_meta( $image_id, SA_GALLERY_STATUS, 'approved' );
	} elseif ( 'rejected' === $status ) {
		update_post_meta( $image_id, SA_GALLERY_STATUS, 'rejected' );
	} else {
		update_post_meta( $image_id, SA_GALLERY_STATUS, 'pending' );
	}
	$province_id = (int) get_post_meta( $image_id, SA_GALLERY_PROVINCE, true );
	$city_id     = (int) get_post_meta( $image_id, SA_GALLERY_CITY, true );
	sa_gallery_flush_cache( $province_id, $city_id );
}
