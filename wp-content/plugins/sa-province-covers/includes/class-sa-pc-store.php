<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit, journaled writes only. Old attachments and article content are never deleted. */
class SA_PC_Store {
	public static function authorize( $post_id ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'forbidden', 'اجازهٔ تغییر این استان را ندارید.' );
		}
		$post = get_post( $post_id );
		if ( ! $post || 'province' !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return new WP_Error( 'target', 'مقصد باید مقالهٔ استان باشد؛ شهرستان یا برگه تغییر نمی‌کند.' );
		}
		return $post;
	}

	private static function lock( $post_id ) {
		$lock = array( 'token' => wp_generate_uuid4(), 'time' => time() );
		if ( ! add_option( 'sa_pc_lock_' . $post_id, $lock, '', false ) ) {
			return new WP_Error( 'locked', 'عملیات دیگری برای این استان قفل دارد. اگر اجرا قطع شده، بعد از ۱۵ دقیقه «رفع قفل منقضی» را بزنید.' );
		}
		return $lock;
	}

	/** Compare-and-delete: a late worker must never remove somebody else's new lock. */
	private static function release( $post_id, $lock ) {
		global $wpdb;
		$key = 'sa_pc_lock_' . $post_id;
		$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $lock ) ) );
		if ( $removed ) { wp_cache_delete( $key, 'options' ); wp_cache_delete( 'notoptions', 'options' ); }
		return (bool) $removed;
	}

	public static function unlock( $post_id ) {
		$auth = self::authorize( $post_id );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$lock = get_option( 'sa_pc_lock_' . $post_id );
		if ( ! is_array( $lock ) || ! isset( $lock['time'] ) || time() - (int) $lock['time'] < 900 ) {
			return new WP_Error( 'active_lock', 'قفل فعالی وجود ندارد یا هنوز ۱۵ دقیقه از آن نگذشته است.' );
		}
		return self::release( $post_id, $lock ) ? true : new WP_Error( 'changed_lock', 'قفل تغییر کرده است؛ دوباره بررسی کنید.' );
	}

	private static function thumbnail( $post_id, $attachment_id ) {
		if ( $attachment_id ) { update_post_meta( $post_id, '_thumbnail_id', $attachment_id ); }
		else { delete_post_thumbnail( $post_id ); }
		return (int) get_post_thumbnail_id( $post_id ) === (int) $attachment_id;
	}

	private static function stage( $asset, $post_id ) {
		$upload = wp_upload_bits( 'sa-province-' . $asset['slug'] . '-' . substr( $asset['sha256'], 0, 12 ) . '.webp', null, file_get_contents( $asset['path'] ) );
		if ( ! empty( $upload['error'] ) ) { return new WP_Error( 'upload', 'ذخیرهٔ تصویر در رسانه انجام نشد: ' . $upload['error'] ); }
		$id = wp_insert_attachment( array(
			'post_title' => 'تابلوی استان ' . $asset['name'], 'post_mime_type' => 'image/webp',
			'post_status' => 'inherit', 'post_excerpt' => '', 'post_content' => '',
		), $upload['file'], $post_id, true );
		if ( is_wp_error( $id ) ) { return $id; }
		$id = (int) $id;
		update_post_meta( $id, '_wp_attachment_image_alt', $asset['alt'] );
		if ( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) !== $asset['alt'] ) {
			return new WP_Error( 'alt', 'ذخیرهٔ آلت فارسی انجام نشد؛ تصویر شاخص قبلی دست‌نخورده است.' );
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) { require_once ABSPATH . 'wp-admin/includes/image.php'; }
		$meta = wp_generate_attachment_metadata( $id, $upload['file'] );
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return new WP_Error( 'metadata', 'ساخت مشخصات رسانه انجام نشد؛ تصویر شاخص قبلی دست‌نخورده است.' );
		}
		wp_update_attachment_metadata( $id, $meta );
		$saved = get_post_meta( $id, '_wp_attachment_metadata', true );
		if ( ! is_array( $saved ) || (int) ( $saved['width'] ?? 0 ) !== (int) $meta['width']
			|| (int) ( $saved['height'] ?? 0 ) !== (int) $meta['height'] || ( $saved['file'] ?? '' ) !== ( $meta['file'] ?? '' ) ) {
			return new WP_Error( 'metadata_save', 'ذخیرهٔ مشخصات رسانه انجام نشد؛ تصویر شاخص قبلی دست‌نخورده است.' );
		}
		return $id;
	}

	public static function apply( $slug, $post_id ) {
		$post_id = (int) $post_id;
		$post = self::authorize( $post_id );
		if ( is_wp_error( $post ) ) { return $post; }
		$asset = sa_pc_asset( $slug );
		if ( is_wp_error( $asset ) ) { return $asset; }
		foreach ( sa_pc_catalog() as $entry ) {
			if ( $entry['slug'] !== $slug && ( $post->post_name === $entry['slug'] || sa_pc_normalize_name( $post->post_title ) === sa_pc_normalize_name( $entry['name'] ) ) ) {
				return new WP_Error( 'mismatch', 'نام مقاله مربوط به استان دیگری است؛ مقصد درست را انتخاب کنید.' );
			}
		}
		$lock = self::lock( $post_id );
		if ( is_wp_error( $lock ) ) { return $lock; }
		$key = 'sa_pc_journal_' . $post_id;
		try {
			$previous = get_option( $key );
			if ( $previous ) {
				if ( 'applied' === ( $previous['state'] ?? '' ) && $slug === ( $previous['slug'] ?? '' )
					&& $asset['sha256'] === ( $previous['sha256'] ?? '' ) && (int) get_post_thumbnail_id( $post_id ) === (int) $previous['new_id']
					&& is_readable( get_attached_file( $previous['new_id'] ) ) ) {
					return array( 'message' => 'این تصویر قبلاً نصب شده؛ پیوست تکراری ساخته نشد.', 'attachment_id' => (int) $previous['new_id'] );
				}
				return new WP_Error( 'journal', 'پشتیبان عملیات قبلی موجود است؛ ابتدا بازگردانی کنید تا پشتیبان از بین نرود.' );
			}
			$old_id = (int) get_post_thumbnail_id( $post_id );
			$new_id = self::stage( $asset, $post_id );
			if ( is_wp_error( $new_id ) ) { return $new_id; }
			if ( (int) get_post_thumbnail_id( $post_id ) !== $old_id ) {
				return new WP_Error( 'concurrent_edit', 'تصویر شاخص هنگام پردازش تغییر کرد؛ تغییری روی انتخاب تازهٔ ویرایشگر انجام نشد.' );
			}
			$journal = array( 'state' => 'prepared', 'slug' => $slug, 'old_id' => $old_id, 'new_id' => $new_id, 'sha256' => $asset['sha256'], 'time' => gmdate( 'c' ) );
			if ( ! add_option( $key, $journal, '', false ) ) { return new WP_Error( 'backup', 'ذخیرهٔ پشتیبان انجام نشد؛ تصویر شاخص قبلی دست‌نخورده است.' ); }
			try {
				// The old assignment is removed first, never the attachment/library file.
				delete_post_thumbnail( $post_id );
				if ( (int) get_post_thumbnail_id( $post_id ) !== 0 ) { throw new RuntimeException( 'جداکردن تصویر شاخص قبلی انجام نشد.' ); }
				set_post_thumbnail( $post_id, $new_id );
				if ( (int) get_post_thumbnail_id( $post_id ) !== $new_id ) { throw new RuntimeException( 'نصب تصویر جدید انجام نشد.' ); }
				$journal['state'] = 'applied';
				update_option( $key, $journal, false );
				if ( get_option( $key ) !== $journal ) { throw new RuntimeException( 'ثبت نتیجهٔ عملیات انجام نشد.' ); }
			} catch ( Throwable $error ) {
				$current = (int) get_post_thumbnail_id( $post_id );
				if ( in_array( $current, array( 0, $new_id, $old_id ), true ) && self::thumbnail( $post_id, $old_id ) ) {
					delete_option( $key );
					return new WP_Error( 'rolled_back', $error->getMessage() . ' تصویر شاخص قبلی بازگردانده شد.' );
				}
				return new WP_Error( 'recovery', 'عملیات کامل نشد؛ پشتیبان حفظ شده است. وضعیت مقصد را بررسی و از بازگردانی استفاده کنید.' );
			}
			return array( 'message' => 'تصویر جدید با آلت فارسی جایگزین شد؛ فایل تصویر قبلی در رسانه باقی است.', 'attachment_id' => $new_id );
		} finally {
			self::release( $post_id, $lock );
		}
	}

	public static function restore( $post_id ) {
		$post_id = (int) $post_id;
		$auth = self::authorize( $post_id );
		if ( is_wp_error( $auth ) ) { return $auth; }
		$lock = self::lock( $post_id );
		if ( is_wp_error( $lock ) ) { return $lock; }
		try {
			$key = 'sa_pc_journal_' . $post_id;
			$journal = get_option( $key );
			if ( ! is_array( $journal ) || ! isset( $journal['old_id'], $journal['new_id'] ) ) { return new WP_Error( 'backup', 'برای این مقاله پشتیبان افزونه وجود ندارد.' ); }
			$current = (int) get_post_thumbnail_id( $post_id );
			if ( ! in_array( $current, array( 0, (int) $journal['old_id'], (int) $journal['new_id'] ), true ) ) {
				return new WP_Error( 'conflict', 'تصویر شاخص بیرون افزونه تغییر کرده است؛ انتخاب دستی شما بازنویسی نشد.' );
			}
			if ( ! self::thumbnail( $post_id, (int) $journal['old_id'] ) ) { return new WP_Error( 'restore', 'بازگردانی انجام نشد؛ پشتیبان حفظ شد.' ); }
			delete_option( $key );
			if ( get_option( $key ) ) { return new WP_Error( 'backup_delete', 'تصویر قبلی بازگشت ولی پاک‌کردن رکورد عملیات انجام نشد؛ دوباره بررسی کنید.' ); }
			return array( 'message' => 'انتخاب تصویر شاخص قبلی بازگردانده شد؛ هیچ فایل رسانه‌ای حذف نشد.' );
		} finally {
			self::release( $post_id, $lock );
		}
	}
}
