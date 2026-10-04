<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Media {
	/**
	 * Handle citizen image uploads. Since v2.11.7 the same gallery optimiser is
	 * used here: real mime validation, WebP conversion, watermark, metadata strip
	 * via re-encoding, raw-file deletion, and pending gallery status.
	 *
	 * @param array  $file    Uploaded file array.
	 * @param int    $city_id City post ID.
	 * @param string $caption Optional caption from contribution text.
	 * @return int|WP_Error
	 */
	public static function handle( $file, $city_id = 0, $caption = '' ) {
		if ( empty( $file ) || ( isset( $file['error'] ) && UPLOAD_ERR_NO_FILE === (int) $file['error'] ) ) {
			return 0;
		}

		if ( function_exists( 'sa_gallery_handle_upload' ) ) {
			return sa_gallery_handle_upload(
				$file,
				array(
					'city_id' => absint( $city_id ),
					'caption' => wp_trim_words( wp_strip_all_tags( (string) $caption ), 28, '…' ),
					'place'   => $city_id ? get_the_title( $city_id ) : '',
					'source'  => 'citizen',
					'status'  => 'pending',
				)
			);
		}

		if ( ! empty( $file['error'] ) || ! empty( $file['size'] ) && (int) $file['size'] > 5 * MB_IN_BYTES ) {
			return new WP_Error( 'invalid_image', 'تصویر باید JPG، PNG یا WebP و حداکثر ۵ مگابایت باشد', array( 'status' => 400 ) );
		}

		$mime = wp_get_image_mime( $file['tmp_name'] );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error( 'invalid_mime', 'نوع واقعی تصویر مجاز نیست', array( 'status' => 400 ) );
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
			return new WP_Error( 'upload_failed', $upload['error'], array( 'status' => 400 ) );
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( ! is_wp_error( $id ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
			return (int) $id;
		}

		return $id;
	}
}
