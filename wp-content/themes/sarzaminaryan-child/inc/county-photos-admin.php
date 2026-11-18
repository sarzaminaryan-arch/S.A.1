<?php
/**
 * پنل «تصاویر شهرستان» در صفحهٔ ویرایش نوشته (v2.22.115).
 *
 * دستور مالک، ۲۷ آبان ۱۴۰۵: «در بخش ویرایش مقاله در پیشنویس، جایی برای آپلود
 * تصویر بگذار که اگر خواستم تصویر جدید بگذارم، حذف کنم، و در هر صورت تصویر را
 * بتوانم تغییر بدهم.»
 *
 * این فایل فقط رابط مدیریتی است: جعبهٔ metabox، دارایی‌های صفحهٔ ویرایش و
 * چهار هندلر ajax (چسباندن، تعویض فایل، برداشتن، ذخیرهٔ ردیف‌ها). سیاست عکس‌ها
 * (مجوزهای مجاز، قاعدهٔ انتشار، ذخیرهٔ فایل، اسلایدرها) در inc/county-media.php
 * می‌ماند تا یک منبع حقیقت داشته باشیم.
 *
 * قاعدهٔ اعتبار دست‌نخورده است: عکسی که نام پدیدآور یا مجوز مجاز ندارد روی صفحه
 * چاپ نمی‌شود (sa_photo_is_publishable)، ولی در پنل با برچسب «منتظر اعتبار» دیده
 * می‌شود تا مالک همان‌جا دو فیلد را پر کند.
 *
 * @package Sarzaminaryan_Child
 * @since   2.22.115
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت جعبهٔ مدیریت تصاویر روی نوشتهٔ شهرستان.
 */
function sa_photo_admin_register() {
	add_meta_box( 'sa-county-photos', 'تصاویر شهرستان — کادر «مکان در یک نگاه» و گالری', 'sa_photo_admin_box', 'city', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'sa_photo_admin_register' );

/**
 * دارایی‌های پنل (کتابخانهٔ رسانه + اسکریپت پنل) فقط در ویرایش شهرستان.
 *
 * @param string $hook صفحهٔ 관리자.
 */
function sa_photo_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'city' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script(
		'sa-county-photos',
		SA_CHILD_URI . 'assets/js/county-photos-admin.js',
		array(),
		sa_child_asset_version( 'assets/js/county-photos-admin.js' ),
		true
	);
	wp_localize_script(
		'sa-county-photos',
		'saCountyPhotos',
		array(
			'ajax'   => admin_url( 'admin-ajax.php' ),
			'nonce'  => wp_create_nonce( 'sa_photo_admin' ),
			'postId' => (int) get_the_ID(),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'sa_photo_admin_assets' );

/**
 * بدنهٔ جعبه: فهرست عکس‌های شهرستان + افزودن/تعویض/حذف + ترتیب دستی.
 *
 * @param WP_Post $post نوشتهٔ شهرستان.
 */
function sa_photo_admin_box( $post ) {
	wp_nonce_field( 'sa_photo_box', 'sa_photo_box_nonce' );
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 60,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array( array( 'key' => '_sa_photo_city', 'value' => (int) $post->ID ) ),
		)
	);
	$licenses = sa_photo_allowed_licenses();
	$kinds    = array(
		'heritage' => 'میراث تاریخی',
		'fun'      => 'تفریحی',
		'nature'   => 'طبیعی',
		'modern'   => 'مدرن',
	);
	?>
	<p class="sa-photo-admin__hint">
		تصویر تازه از دکمهٔ «افزودن تصویر» (آپلود یا کتابخانهٔ رسانه) اضافه می‌شود؛ تا وقتی
		<strong>نام پدیدآور</strong> و <strong>مجوز</strong> هر ردیف پر نشود، آن تصویر روی صفحه چاپ نمی‌شود.
		ترتیب اسلایدر از ستون «ترتیب» می‌آید؛ «نمای اول» قاب اول کادر «مکان در یک نگاه» است و «پنهان»
		تصویر را از صفحه برمی‌دارد بدون اینکه از کتابخانه حذف شود.
	</p>
	<p>
		<button type="button" class="button button-primary sa-photo-admin__add">افزودن تصویر (آپلود یا کتابخانهٔ رسانه)</button>
	</p>
	<?php if ( ! $ids ) : ?>
		<p>هنوز تصویری برای این شهرستان ثبت نشده است.</p>
	<?php else : ?>
	<table class="widefat striped sa-photo-admin__table">
		<thead>
			<tr>
				<th>تصویر</th>
				<th>نام مکان (روی عکس)</th>
				<th>نوع</th>
				<th>پدیدآور</th>
				<th>مجوز</th>
				<th>ترتیب</th>
				<th>نمای اول</th>
				<th>پنهان</th>
				<th>تعویض / حذف</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $ids as $id ) : ?>
			<?php
			$kind    = (string) get_post_meta( $id, '_sa_photo_kind', true );
			$publish = sa_photo_is_publishable( $id );
			?>
			<tr data-sa-photo-row="<?php echo esc_attr( (string) $id ); ?>">
				<td>
					<?php echo wp_get_attachment_image( (int) $id, array( 72, 72 ), true ); ?>
					<?php if ( ! $publish ) : ?>
						<span class="sa-photo-admin__warn">منتظر اعتبار</span>
					<?php endif; ?>
				</td>
				<td><input type="text" class="widefat" name="sa_photo_row[<?php echo (int) $id; ?>][place]" value="<?php echo esc_attr( (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true ) ); ?>"></td>
				<td>
					<select name="sa_photo_row[<?php echo (int) $id; ?>][kind]">
						<?php foreach ( $kinds as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $kind, $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="text" class="widefat" name="sa_photo_row[<?php echo (int) $id; ?>][author]" value="<?php echo esc_attr( (string) get_post_meta( $id, SA_PHOTO_META_AUTHOR, true ) ); ?>"></td>
				<td>
					<select name="sa_photo_row[<?php echo (int) $id; ?>][license]">
						<option value="">— انتخاب کنید —</option>
						<?php foreach ( $licenses as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (string) get_post_meta( $id, SA_PHOTO_META_LICENSE, true ), $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
				<td><input type="number" min="0" step="1" style="width:4.5em" name="sa_photo_row[<?php echo (int) $id; ?>][order]" value="<?php echo esc_attr( (string) get_post_meta( $id, '_sa_photo_order', true ) ); ?>"></td>
				<td><input type="radio" name="sa_photo_lead" value="<?php echo (int) $id; ?>" <?php checked( '1', (string) get_post_meta( $id, '_sa_photo_lead', true ) ); ?>></td>
				<td><input type="checkbox" name="sa_photo_row[<?php echo (int) $id; ?>][hide]" value="1" <?php checked( '1', (string) get_post_meta( $id, '_sa_photo_hide', true ) ); ?>></td>
				<td>
					<button type="button" class="button sa-photo-admin__swap" data-id="<?php echo (int) $id; ?>">تعویض فایل</button>
					<button type="button" class="button button-link-delete sa-photo-admin__detach" data-id="<?php echo (int) $id; ?>">حذف از شهرستان</button>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
	<p>
		<label>
			<input type="radio" name="sa_photo_lead" value="0" <?php checked( ! array_filter( $ids, static function ( $i ) { return '1' === (string) get_post_meta( $i, '_sa_photo_lead', true ); } ) ); ?>>
			بدون «نمای اول» دستی (اولین عکس واجد شرایط قاب اول می‌شود)
		</label>
	</p>
	<p>
		<button type="button" class="button button-primary sa-photo-admin__save">ذخیرهٔ تغییرات تصاویر</button>
		<span class="sa-photo-admin__status" role="status" aria-live="polite"></span>
	</p>
	<p class="description">
		این ردیف‌ها با دکمهٔ «به‌روزرسانی» خودِ نوشته هم ذخیره می‌شوند؛ دکمهٔ بالا برای وقتی است
		که فقط تصاویر را دست زده‌اید. افزودن، تعویض و حذف فایل فوراً ذخیره می‌شود.
	</p>
	<?php
}

/**
 * ذخیرهٔ ردیف‌های پنل: اعتبار، نوع، ترتیب، «نمای اول» و پنهان.
 *
 * @param int   $post_id شهرستان.
 * @param array $rows    ردیف‌های فرم (کلید = شناسهٔ پیوست).
 * @param int   $lead    شناسهٔ «نمای اول» (صفر یعنی دستی نباشد).
 * @return int شمار ردیف‌های ذخیره‌شده.
 */
function sa_photo_save_rows( $post_id, $rows, $lead = 0 ) {
	$post_id  = absint( $post_id );
	$lead     = absint( $lead );
	$licenses = sa_photo_allowed_licenses();
	$kinds    = array( 'heritage', 'fun', 'nature', 'modern' );
	$saved    = 0;
	foreach ( (array) $rows as $id => $row ) {
		$id = absint( $id );
		if ( ! $id || (int) get_post_meta( $id, '_sa_photo_city', true ) !== $post_id ) {
			continue;
		}
		$row   = (array) $row;
		$place = isset( $row['place'] ) ? sanitize_text_field( (string) $row['place'] ) : '';
		update_post_meta( $id, SA_PHOTO_META_PLACE, wp_slash( $place ) );
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( '' !== $place ? $place : (string) get_the_title( $id ) ) );
		if ( isset( $row['kind'] ) && in_array( (string) $row['kind'], $kinds, true ) ) {
			update_post_meta( $id, '_sa_photo_kind', wp_slash( (string) $row['kind'] ) );
		}
		update_post_meta( $id, SA_PHOTO_META_AUTHOR, wp_slash( isset( $row['author'] ) ? sanitize_text_field( (string) $row['author'] ) : '' ) );
		$license = isset( $row['license'] ) ? strtolower( sanitize_text_field( (string) $row['license'] ) ) : '';
		update_post_meta( $id, SA_PHOTO_META_LICENSE, wp_slash( isset( $licenses[ $license ] ) ? $license : '' ) );
		update_post_meta( $id, '_sa_photo_order', isset( $row['order'] ) ? max( 0, absint( $row['order'] ) ) : 0 );
		update_post_meta( $id, '_sa_photo_hide', empty( $row['hide'] ) ? '' : '1' );
		update_post_meta( $id, '_sa_photo_lead', '' );
		++$saved;
	}
	if ( $lead && (int) get_post_meta( $lead, '_sa_photo_city', true ) === $post_id ) {
		update_post_meta( $lead, '_sa_photo_lead', '1' );
	}
	return $saved;
}

/**
 * ذخیرهٔ ردیف‌ها همراه خودِ نوشته (مسیر استاندارد متاباکس).
 *
 * @param int $post_id شناسهٔ شهرستان.
 */
function sa_photo_admin_save_with_post( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['sa_photo_box_nonce'] ) || ! isset( $_POST['sa_photo_row'] ) ) {
		return;
	}
	check_admin_referer( 'sa_photo_box', 'sa_photo_box_nonce' );
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر فیلد در sa_photo_save_rows پاک می‌شود.
	$rows = (array) wp_unslash( $_POST['sa_photo_row'] );
	$lead = isset( $_POST['sa_photo_lead'] ) ? absint( wp_unslash( $_POST['sa_photo_lead'] ) ) : 0;
	sa_photo_save_rows( $post_id, $rows, $lead );
}
add_action( 'save_post_city', 'sa_photo_admin_save_with_post', 20 );

/**
 * ajax: ذخیرهٔ ردیف‌ها بدون لمس بدنهٔ نوشته.
 */
function sa_photo_ajax_save() {
	$post_id = sa_photo_admin_ajax_guard();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر فیلد در sa_photo_save_rows پاک می‌شود.
	$rows = isset( $_POST['sa_photo_row'] ) ? (array) wp_unslash( $_POST['sa_photo_row'] ) : array();
	$lead = isset( $_POST['sa_photo_lead'] ) ? absint( wp_unslash( $_POST['sa_photo_lead'] ) ) : 0;
	$done = sa_photo_save_rows( $post_id, $rows, $lead );
	wp_send_json_success( array( 'saved' => $done ) );
}
add_action( 'wp_ajax_sa_photo_save', 'sa_photo_ajax_save' );

/**
 * نگهبان ajax پنل: nonce + دسترسی ویرایش همان شهرستان.
 *
 * @return int شناسهٔ شهرستان.
 */
function sa_photo_admin_ajax_guard() {
	check_ajax_referer( 'sa_photo_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || 'city' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
	}
	return $post_id;
}

/**
 * ajax: چسباندن پیوست‌های انتخاب‌شده (آپلود یا کتابخانه) به شهرستان.
 */
function sa_photo_ajax_attach() {
	$post_id = sa_photo_admin_ajax_guard();
	$ids     = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
	$done    = array();
	foreach ( $ids as $id ) {
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			continue;
		}
		$mime = (string) get_post_mime_type( $id );
		if ( ! in_array( $mime, sa_photo_admin_mimes(), true ) ) {
			continue;
		}
		update_post_meta( $id, '_sa_photo_city', $post_id );
		if ( '' === (string) get_post_meta( $id, '_sa_photo_key', true ) ) {
			$place = (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true );
			if ( '' === $place ) {
				$place = (string) get_the_title( $id );
				update_post_meta( $id, SA_PHOTO_META_PLACE, wp_slash( $place ) );
			}
			update_post_meta( $id, '_sa_photo_key', 'sa-photo:' . md5( $post_id . '|' . $place . '|' . $id ) );
		}
		if ( '' === (string) get_post_meta( $id, '_sa_photo_kind', true ) ) {
			update_post_meta( $id, '_sa_photo_kind', 'heritage' );
		}
		$done[] = $id;
	}
	wp_send_json_success( array( 'attached' => $done, 'count' => count( $done ) ) );
}
add_action( 'wp_ajax_sa_photo_attach', 'sa_photo_ajax_attach' );

/**
 * ajax: تعویض فایل یک عکس شهرستان با فایلی از کتابخانهٔ رسانه.
 */
function sa_photo_ajax_swap() {
	$post_id = sa_photo_admin_ajax_guard();
	$att_id  = isset( $_POST['att_id'] ) ? absint( wp_unslash( $_POST['att_id'] ) ) : 0;
	$src_id  = isset( $_POST['src_id'] ) ? absint( wp_unslash( $_POST['src_id'] ) ) : 0;
	if ( ! $att_id || (int) get_post_meta( $att_id, '_sa_photo_city', true ) !== $post_id ) {
		wp_send_json_error( array( 'message' => 'این تصویر به این شهرستان نیست.' ), 400 );
	}
	$done = sa_photo_replace_with_attachment( $att_id, $src_id );
	if ( is_wp_error( $done ) ) {
		wp_send_json_error( array( 'message' => $done->get_error_message() ), (int) ( $done->get_error_data()['status'] ?? 500 ) );
	}
	wp_send_json_success( array( 'swapped' => $att_id ) );
}
add_action( 'wp_ajax_sa_photo_swap', 'sa_photo_ajax_swap' );

/**
 * ajax: برداشتن یک عکس از شهرستان (پیوست در کتابخانه می‌ماند).
 */
function sa_photo_ajax_detach() {
	$post_id = sa_photo_admin_ajax_guard();
	$att_id  = isset( $_POST['att_id'] ) ? absint( wp_unslash( $_POST['att_id'] ) ) : 0;
	if ( ! $att_id || (int) get_post_meta( $att_id, '_sa_photo_city', true ) !== $post_id ) {
		wp_send_json_error( array( 'message' => 'این تصویر به این شهرستان نیست.' ), 400 );
	}
	foreach ( array( '_sa_photo_city', '_sa_photo_key', '_sa_photo_lead', '_sa_photo_order', '_sa_photo_hide' ) as $key ) {
		delete_post_meta( $att_id, $key );
	}
	wp_send_json_success( array( 'detached' => $att_id ) );
}
add_action( 'wp_ajax_sa_photo_detach', 'sa_photo_ajax_detach' );
