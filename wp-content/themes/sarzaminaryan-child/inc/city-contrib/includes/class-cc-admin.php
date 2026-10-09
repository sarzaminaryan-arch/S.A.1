<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CC_Admin {
	public static function init() {
		add_action(
			'admin_init',
			function () {
				register_setting( 'cc_settings', 'cc_sms_endpoint', array( 'sanitize_callback' => 'esc_url_raw' ) );
				register_setting( 'cc_settings', 'cc_sms_key', array( 'sanitize_callback' => 'sanitize_text_field' ) );
				register_setting( 'cc_settings', 'cc_sms_sender', array( 'sanitize_callback' => 'sanitize_text_field' ) );
				register_setting( 'cc_settings', 'cc_upload_requires_login', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ), 'default' => 0 ) );
				register_setting( 'cc_settings', 'cc_upload_max_files', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_max_files' ), 'default' => 3 ) );
				register_setting( 'cc_settings', 'cc_upload_max_mb', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_max_mb' ), 'default' => 2 ) );
				register_setting( 'cc_settings', 'cc_upload_daily_limit', array( 'sanitize_callback' => array( __CLASS__, 'sanitize_daily_limit' ), 'default' => 3 ) );
			}
		);
		add_filter( 'manage_cc_submission_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_cc_submission_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_cc_submission', array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_cc_submission', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'sa_gallery_max_upload_bytes', array( __CLASS__, 'gallery_max_upload_bytes' ), 10, 2 );
	}

	public static function gallery_max_upload_bytes( $bytes, $source = 'admin' ) {
		return 'citizen' === $source ? self::upload_max_bytes() : $bytes;
	}

	public static function sanitize_bool( $value ) {
		return '1' === (string) $value ? 1 : 0;
	}

	public static function sanitize_max_files( $value ) {
		$value = absint( $value );
		return max( 1, min( 20, $value ? $value : 3 ) );
	}

	public static function sanitize_max_mb( $value ) {
		$value = (float) str_replace( ',', '.', (string) $value );
		if ( $value <= 0 ) {
			$value = 2;
		}
		return max( 0.5, min( 50, $value ) );
	}

	public static function sanitize_daily_limit( $value ) {
		$value = absint( $value );
		return max( 1, min( 100, $value ? $value : 3 ) );
	}

	public static function upload_requires_login() {
		return (bool) get_option( 'cc_upload_requires_login', 0 );
	}

	public static function upload_max_files() {
		return self::sanitize_max_files( get_option( 'cc_upload_max_files', 3 ) );
	}

	public static function upload_max_mb() {
		return self::sanitize_max_mb( get_option( 'cc_upload_max_mb', 2 ) );
	}

	public static function upload_max_bytes() {
		return (int) round( self::upload_max_mb() * MB_IN_BYTES );
	}

	public static function upload_daily_limit() {
		return self::sanitize_daily_limit( get_option( 'cc_upload_daily_limit', 3 ) );
	}

	public static function columns( $cols ) {
		return array(
			'cb'        => '<input type="checkbox">',
			'title'     => 'عنوان',
			'cc_place'  => 'نام مکان',
			'cc_city'   => 'شهرستان/استان',
			'cc_type'   => 'نوع',
			'cc_image'  => 'تصویر',
			'cc_status' => 'وضعیت',
			'date'      => 'تاریخ',
		);
	}

	public static function column( $col, $id ) {
		if ( 'cc_place' === $col ) {
			echo esc_html( get_post_meta( $id, 'cc_place_name', true ) ?: '—' );
		} elseif ( 'cc_city' === $col ) {
			$city     = (int) get_post_meta( $id, 'cc_city_id', true );
			$province = (int) get_post_meta( $id, 'cc_province_id', true );
			echo esc_html( ( $city ? get_the_title( $city ) : '—' ) . ( $province ? '، ' . get_the_title( $province ) : '' ) );
		} elseif ( 'cc_type' === $col ) {
			echo esc_html( get_post_meta( $id, 'cc_type', true ) );
		} elseif ( 'cc_image' === $col ) {
			$img = (int) get_post_meta( $id, 'cc_image_id', true );
			echo $img ? '<span class="cc-chip cc-chip--img">تصویر دارد</span>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'cc_status' === $col ) {
			$s      = get_post_status( $id );
			$labels = array( 'pending' => 'در انتظار بررسی', 'publish' => 'منتشر شده', 'rejected' => 'رد شده', 'needs_edit' => 'نیاز به اصلاح' );
			$cls    = array( 'pending' => 'warn', 'publish' => 'ok', 'rejected' => 'bad', 'needs_edit' => 'edit' );
			echo '<span class="cc-chip cc-chip--' . esc_attr( $cls[ $s ] ?? '' ) . '">' . esc_html( $labels[ $s ] ?? $s ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public static function meta_box() {
		add_meta_box( 'cc_gallery_meta', 'اطلاعات قابل ویرایش تصویر گالری', array( __CLASS__, 'render_meta_box' ), 'cc_submission', 'normal', 'high' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'cc_gallery_meta', 'cc_gallery_meta_nonce' );
		$city     = (int) get_post_meta( $post->ID, 'cc_city_id', true );
		$province = (int) get_post_meta( $post->ID, 'cc_province_id', true );
		$image_id = (int) get_post_meta( $post->ID, 'cc_image_id', true );
		?>
		<p>این فیلدها زیر تصویر گالری نمایش داده می‌شوند و هنگام تأیید، با attachment تصویر همگام می‌شوند.</p>
		<table class="form-table" role="presentation"><tbody>
			<tr><th><label for="cc_place_name">نام مکان</label></th><td><input class="regular-text" id="cc_place_name" name="cc_place_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'cc_place_name', true ) ); ?>" required></td></tr>
			<tr><th>شهرستان</th><td><strong><?php echo esc_html( $city ? get_the_title( $city ) : '—' ); ?></strong><input type="hidden" name="cc_city_id" value="<?php echo esc_attr( $city ); ?>"></td></tr>
			<tr><th>استان</th><td><strong><?php echo esc_html( $province ? get_the_title( $province ) : '—' ); ?></strong><input type="hidden" name="cc_province_id" value="<?php echo esc_attr( $province ); ?>"></td></tr>
			<tr><th><label for="cc_contributor_name">نام فرستنده/عکاس</label></th><td><input class="regular-text" id="cc_contributor_name" name="cc_contributor_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'cc_contributor_name', true ) ); ?>" placeholder="اختیاری"></td></tr>
			<tr><th>یادداشت خام فرستنده</th><td><textarea class="large-text" rows="3" readonly><?php echo esc_textarea( get_post_meta( $post->ID, 'cc_sender_note', true ) ?: '—' ); ?></textarea><p class="description">این متن به‌صورت پیش‌فرض زیر تصویر منتشر نمی‌شود؛ اگر مفید و منطقی بود، مدیر می‌تواند خلاصهٔ تمیز آن را در فیلد بعدی بنویسد.</p></td></tr>
			<tr><th><label for="cc_gallery_caption">توضیح کوتاه زیر تصویر</label></th><td><textarea class="large-text" rows="3" id="cc_gallery_caption" name="cc_gallery_caption"><?php echo esc_textarea( $post->post_content ); ?></textarea></td></tr>
			<?php if ( $image_id ) : ?>
				<tr><th>تصویر</th><td><?php echo wp_get_attachment_image( $image_id, 'medium' ); ?></td></tr>
			<?php endif; ?>
		</tbody></table>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['cc_gallery_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cc_gallery_meta_nonce'] ) ), 'cc_gallery_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$place       = isset( $_POST['cc_place_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_place_name'] ) ) : '';
		$contributor = isset( $_POST['cc_contributor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_contributor_name'] ) ) : '';
		$caption     = isset( $_POST['cc_gallery_caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['cc_gallery_caption'] ) ) : '';
		update_post_meta( $post_id, 'cc_place_name', $place );
		update_post_meta( $post_id, 'cc_contributor_name', $contributor );

		remove_action( 'save_post_cc_submission', array( __CLASS__, 'save_meta' ), 10 );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $place ? $place . ' — ' . get_the_title( get_post_meta( $post_id, 'cc_city_id', true ) ) : $post->post_title, 'post_content' => $caption ) );
		add_action( 'save_post_cc_submission', array( __CLASS__, 'save_meta' ), 10, 2 );

		self::sync_image_meta( $post_id );
	}

	public static function sync_image_meta( $submission_id ) {
		$image_id = (int) get_post_meta( $submission_id, 'cc_image_id', true );
		if ( ! $image_id ) {
			return;
		}
		$place       = get_post_meta( $submission_id, 'cc_place_name', true );
		$contributor = get_post_meta( $submission_id, 'cc_contributor_name', true );
		$city_id     = (int) get_post_meta( $submission_id, 'cc_city_id', true );
		$province_id = (int) get_post_meta( $submission_id, 'cc_province_id', true );
		$caption     = get_post_field( 'post_content', $submission_id );

		if ( defined( 'SA_GALLERY_PLACE' ) ) {
			update_post_meta( $image_id, SA_GALLERY_PLACE, $place );
		}
		if ( defined( 'SA_GALLERY_CONTRIBUTOR' ) ) {
			update_post_meta( $image_id, SA_GALLERY_CONTRIBUTOR, $contributor );
		}
		wp_update_post( array( 'ID' => $image_id, 'post_title' => $place ? $place : get_the_title( $city_id ), 'post_excerpt' => $caption ) );
		update_post_meta( $image_id, '_wp_attachment_image_alt', sprintf( 'تصویر %1$s در شهرستان %2$s، استان %3$s - سرزمین آریان', $place ? $place : 'دیدنی', $city_id ? get_the_title( $city_id ) : '', $province_id ? get_the_title( $province_id ) : '' ) );
	}
}
