<?php
/**
 * پنل «گالری تصاویر شهرستان» در صفحهٔ ویرایش نوشته (v2.22.115 → v2.22.116).
 *
 * امکانات کامل مدیریتی برای مالک روی هر شهرستان (پیش‌نویس و منتشرشده):
 * - افزودن یک یا چند تصویر از آپلود مستقیم یا کتابخانهٔ رسانه با تبدیل خودکار
 *   به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت (بدون دست‌زدن به تصویر شاخص یا مطالب دیگر).
 * - تعویض فایل هر قاب با تبدیل خودکار به WebP زیر ۱۰۰ کیلوبایت.
 * - ویرایش نام مکان (روی عکس)، توضیح کوتاه زیر قاب، برچسب، نوع، پدیدآور و مجوز.
 * - تعیین تصویر اول، مرتب‌سازی دستی (بالا/پایین یا عدد) و پنهان‌سازی موقت.
 * - برداشتن از گالری (حفظ در کتابخانه) یا انتقال برگشت‌پذیر به زباله‌دان.
 * - دکمهٔ یک‌کلیک بهینه‌سازی همهٔ عکس‌های شهرستان به WebP زیر ۱۰۰ کیلوبایت.
 *
 * @package Sarzaminaryan_Child
 * @since   2.22.115
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت جعبهٔ مدیریت گالری تصاویر روی نوشتهٔ شهرستان.
 */
function sa_photo_admin_register() {
	add_meta_box(
		'sa-county-photos',
		'گالری تصاویر شهرستان — افزودن، حذف، ترتیب و سفارشی‌سازی (WebP زیر ۱۰۰ کیلوبایت)',
		'sa_photo_admin_box',
		'city',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'sa_photo_admin_register' );

/**
 * دارایی‌های پنل (کتابخانهٔ رسانه + اسکریپت و استایل پنل) فقط در ویرایش شهرستان.
 *
 * @param string $hook صفحهٔ پیشخوان.
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
 * فهرست مرتب‌شدهٔ همهٔ عکس‌های ثبت‌شده برای یک شهرستان در پنل مدیریت (شامل پنهان و منتظر اعتبار).
 *
 * @param int $city_id شناسهٔ شهرستان.
 * @return int[]
 */
function sa_photo_admin_list_ids( $city_id ) {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 80,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array( array( 'key' => '_sa_photo_city', 'value' => absint( $city_id ) ) ),
		)
	);
	$ids = array_map( 'intval', (array) $ids );
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
			return $b <=> $a;
		}
	);
	return $ids;
}

/**
 * بدنهٔ جعبهٔ گالری در پیشخوان: افزودن، تعویض، حذف، ترتیب و بهینه‌سازی WebP.
 *
 * @param WP_Post $post نوشتهٔ شهرستان.
 */
function sa_photo_admin_box( $post ) {
	wp_nonce_field( 'sa_photo_box', 'sa_photo_box_nonce' );
	$ids      = sa_photo_admin_list_ids( (int) $post->ID );
	$licenses = sa_photo_allowed_licenses();
	$kinds    = array(
		'heritage' => 'میراث تاریخی',
		'nature'   => 'طبیعی',
		'fun'      => 'تفریحی',
		'modern'   => 'مدرن',
	);
	$has_heavy = false;
	?>
	<style>
		#sa-county-photos .sa-photo-admin__bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin:8px 0 12px}
		#sa-county-photos .sa-photo-admin__table td{vertical-align:top}
		#sa-county-photos .sa-photo-admin__thumb{display:flex;flex-direction:column;gap:4px;align-items:flex-start}
		#sa-county-photos .sa-photo-admin__thumb img{width:84px;height:63px;object-fit:cover;border-radius:6px;border:1px solid #cbd5e1}
		#sa-county-photos .sa-photo-admin__pill{display:inline-block;padding:1px 6px;border-radius:4px;font-size:11px;line-height:1.6;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
		#sa-county-photos .sa-photo-admin__pill--warn,#sa-county-photos .sa-photo-admin__warn{display:inline-block;padding:1px 6px;border-radius:4px;font-size:11px;line-height:1.6;background:#fef3c7;color:#92400e;border:1px solid #fcd34d}
		#sa-county-photos .sa-photo-admin__stack{display:grid;gap:5px}
		#sa-county-photos .sa-photo-admin__order{display:flex;align-items:center;gap:4px}
		#sa-county-photos .sa-photo-admin__actions{display:flex;flex-wrap:wrap;gap:5px}
		#sa-county-photos .sa-photo-admin__status{font-weight:600;color:#0b2545;margin-inline-start:8px}
		#sa-county-photos tr.is-hidden-photo{opacity:.62}
		@media (max-width:960px){#sa-county-photos .sa-photo-admin__wrap{overflow-x:auto}}
	</style>
	<p class="sa-photo-admin__hint">
		تصاویر این فهرست در بخش <strong>«گالری تصاویر شهرستان»</strong> (اسلایدر بالای امتیاز کاربران و منابع) نمایش داده می‌شوند.
		هر تصویر جدید یا جایگزین به‌صورت خودکار به <strong>WebP با نسبت ۴:۳ و حجم زیر ۱۰۰ کیلوبایت</strong> تبدیل می‌شود.
	</p>
	<div class="sa-photo-admin__bar">
		<button type="button" class="button button-primary sa-photo-admin__add">+ افزودن تصویر به گالری (آپلود یا رسانه)</button>
		<label>
			<input type="checkbox" class="sa-photo-admin__default-credit" value="1" checked>
			ثبت پیش‌فرض اعتبار «سرزمین آریان / بایگانی سایت» روی تصاویر تازه‌آپلودشده (قابل ویرایش)
		</label>
	</div>
	<?php if ( ! $ids ) : ?>
		<p>هنوز تصویری برای گالری این شهرستان ثبت نشده است. با دکمهٔ بالا می‌توانید یک یا چند تصویر اضافه کنید.</p>
	<?php else : ?>
	<div class="sa-photo-admin__wrap">
	<table class="widefat striped sa-photo-admin__table">
		<thead>
			<tr>
				<th>پیش‌نمایش و حجم</th>
				<th>نام مکان و توضیح زیر قاب</th>
				<th>نوع و برچسب</th>
				<th>پدیدآور و مجوز</th>
				<th>ترتیب</th>
				<th>اول</th>
				<th>پنهان</th>
				<th>عملیات</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $ids as $idx => $id ) : ?>
			<?php
			$kind    = (string) get_post_meta( $id, '_sa_photo_kind', true );
			$lic_val = sa_photo_normalize_license( (string) get_post_meta( $id, SA_PHOTO_META_LICENSE, true ) );
			$publish = sa_photo_is_publishable( $id );
			$hidden  = '1' === (string) get_post_meta( $id, '_sa_photo_hide', true );
			$file    = (string) get_attached_file( $id );
			$bytes   = ( '' !== $file && is_file( $file ) ) ? (int) @filesize( $file ) : (int) get_post_meta( $id, '_sa_photo_bytes', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$mime    = (string) get_post_mime_type( $id );
			$is_ok   = ( 'image/webp' === $mime && $bytes > 0 && $bytes <= 100000 );
			if ( ! $is_ok ) {
				$has_heavy = true;
			}
			$fmt_lbl = strtoupper( str_replace( 'image/', '', $mime ?: 'img' ) );
			$kb_lbl  = $bytes > 0 ? ( round( $bytes / 1024, 1 ) . ' KB' ) : '—';
			$ord_val = (int) get_post_meta( $id, '_sa_photo_order', true );
			?>
			<tr data-sa-photo-row="<?php echo esc_attr( (string) $id ); ?>" class="<?php echo $hidden ? 'is-hidden-photo' : ''; ?>">
				<td>
					<div class="sa-photo-admin__thumb">
						<?php echo wp_get_attachment_image( (int) $id, array( 96, 72 ), true ); ?>
						<span class="<?php echo $is_ok ? 'sa-photo-admin__pill' : 'sa-photo-admin__pill--warn'; ?>" dir="ltr">
							<?php echo esc_html( $fmt_lbl . ' · ' . $kb_lbl ); ?>
						</span>
						<?php if ( ! $publish ) : ?>
							<span class="sa-photo-admin__warn">منتظر اعتبار</span>
						<?php endif; ?>
					</div>
				</td>
				<td>
					<div class="sa-photo-admin__stack">
						<input type="text" class="widefat" placeholder="نام مکان روی تصویر" name="sa_photo_row[<?php echo (int) $id; ?>][place]" value="<?php echo esc_attr( (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true ) ); ?>">
						<input type="text" class="widefat" placeholder="توضیح کوتاه زیر تصویر (اختیاری)" name="sa_photo_row[<?php echo (int) $id; ?>][highlight]" value="<?php echo esc_attr( (string) get_post_meta( $id, '_sa_photo_highlight', true ) ); ?>">
					</div>
				</td>
				<td>
					<div class="sa-photo-admin__stack">
						<select name="sa_photo_row[<?php echo (int) $id; ?>][kind]">
							<?php foreach ( $kinds as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $kind, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" class="widefat" placeholder="برچسب گوشهٔ قاب (اختیاری)" name="sa_photo_row[<?php echo (int) $id; ?>][tag]" value="<?php echo esc_attr( (string) get_post_meta( $id, '_sa_photo_tag', true ) ); ?>">
					</div>
				</td>
				<td>
					<div class="sa-photo-admin__stack">
						<input type="text" class="widefat" placeholder="نام عکاس / پدیدآور" name="sa_photo_row[<?php echo (int) $id; ?>][author]" value="<?php echo esc_attr( (string) get_post_meta( $id, SA_PHOTO_META_AUTHOR, true ) ); ?>">
						<select name="sa_photo_row[<?php echo (int) $id; ?>][license]">
							<option value="">— انتخاب مجوز —</option>
							<?php foreach ( $licenses as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $lic_val, $code ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</td>
				<td>
					<div class="sa-photo-admin__order">
						<input type="number" min="0" step="1" style="width:4.2em" class="sa-photo-admin__order-input" name="sa_photo_row[<?php echo (int) $id; ?>][order]" value="<?php echo esc_attr( $ord_val > 0 ? (string) $ord_val : (string) ( $idx + 1 ) ); ?>">
						<button type="button" class="button button-small sa-photo-admin__move" data-dir="-1" title="انتقال به بالا" aria-label="انتقال به بالا">↑</button>
						<button type="button" class="button button-small sa-photo-admin__move" data-dir="1" title="انتقال به پایین" aria-label="انتقال به پایین">↓</button>
					</div>
				</td>
				<td><input type="radio" name="sa_photo_lead" value="<?php echo (int) $id; ?>" <?php checked( '1', (string) get_post_meta( $id, '_sa_photo_lead', true ) ); ?>></td>
				<td><input type="checkbox" name="sa_photo_row[<?php echo (int) $id; ?>][hide]" value="1" <?php checked( $hidden ); ?>></td>
				<td>
					<div class="sa-photo-admin__actions">
						<button type="button" class="button button-small sa-photo-admin__swap" data-id="<?php echo (int) $id; ?>">تعویض تصویر</button>
						<button type="button" class="button button-small sa-photo-admin__detach" data-id="<?php echo (int) $id; ?>">برداشتن</button>
						<button type="button" class="button button-small button-link-delete sa-photo-admin__trash" data-id="<?php echo (int) $id; ?>">زباله‌دان</button>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php endif; ?>
	<p>
		<label>
			<input type="radio" name="sa_photo_lead" value="0" <?php checked( ! array_filter( $ids, static function ( $i ) { return '1' === (string) get_post_meta( $i, '_sa_photo_lead', true ); } ) ); ?>>
			ترتیب عادی بر اساس ستون «ترتیب» (بدون سنجاق‌کردن تصویر اول)
		</label>
	</p>
	<div class="sa-photo-admin__bar">
		<button type="button" class="button button-primary sa-photo-admin__save">ذخیرهٔ تغییرات گالری</button>
		<?php if ( $has_heavy ) : ?>
			<button type="button" class="button sa-photo-admin__optimize">تبدیل همه به WebP زیر ۱۰۰ کیلوبایت</button>
		<?php endif; ?>
		<span class="sa-photo-admin__status" role="status" aria-live="polite"></span>
	</div>
	<p class="description">
		تغییرات با دکمهٔ «به‌روزرسانی» نوشته هم ذخیره می‌شوند. «برداشتن» تصویر را فقط از گالری این شهرستان جدا می‌کند
		و فایل را در رسانه نگه می‌دارد؛ «زباله‌دان» تصویر را به‌صورت قابل بازگردانی به زباله‌دان رسانه می‌فرستد.
	</p>
	<?php
}

/**
 * ذخیرهٔ ردیف‌های پنل: مکان، توضیح، برچسب، نوع، پدیدآور، مجوز، ترتیب، تصویر اول و پنهان.
 *
 * @param int   $post_id شهرستان.
 * @param array $rows    ردیف‌های فرم (کلید = شناسهٔ پیوست).
 * @param int   $lead    شناسهٔ «تصویر اول» (صفر یعنی بر اساس ستون ترتیب).
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
		if ( isset( $row['highlight'] ) ) {
			update_post_meta( $id, '_sa_photo_highlight', wp_slash( sanitize_text_field( (string) $row['highlight'] ) ) );
		}
		if ( isset( $row['tag'] ) ) {
			update_post_meta( $id, '_sa_photo_tag', wp_slash( sanitize_text_field( (string) $row['tag'] ) ) );
		}
		if ( isset( $row['kind'] ) && in_array( (string) $row['kind'], $kinds, true ) ) {
			update_post_meta( $id, '_sa_photo_kind', wp_slash( (string) $row['kind'] ) );
		}
		update_post_meta( $id, SA_PHOTO_META_AUTHOR, wp_slash( isset( $row['author'] ) ? sanitize_text_field( (string) $row['author'] ) : '' ) );
		$license = isset( $row['license'] ) ? sa_photo_normalize_license( sanitize_text_field( (string) $row['license'] ) ) : '';
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
 * آیا پیوست در نقش تصویر شاخص یا گالری نوشتهٔ دیگری به‌کار رفته است؟
 *
 * @param int $att_id  شناسهٔ پیوست.
 * @param int $post_id شناسهٔ شهرستان جاری.
 * @return bool
 */
function sa_photo_admin_is_shared( $att_id, $post_id ) {
	$other_city = absint( get_post_meta( $att_id, '_sa_photo_city', true ) );
	if ( $other_city > 0 && $other_city !== $post_id ) {
		return true;
	}
	$thumbs = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'meta_key'       => '_thumbnail_id',
			'meta_value'     => (string) absint( $att_id ),
		)
	);
	if ( ! empty( $thumbs ) ) {
		return true;
	}
	$att = get_post( $att_id );
	return $att && (int) $att->post_parent > 0 && (int) $att->post_parent !== $post_id;
}

/**
 * ajax: افزودن پیوست‌های انتخاب‌شده (آپلود یا کتابخانه) به گالری شهرستان
 * همراه با بهینه‌سازی خودکار به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت.
 */
function sa_photo_ajax_attach() {
	$post_id        = sa_photo_admin_ajax_guard();
	$ids            = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
	$default_credit = ! empty( $_POST['default_credit'] );
	$next_order     = count( sa_photo_admin_list_ids( $post_id ) ) + 1;
	$done           = array();

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	foreach ( $ids as $id ) {
		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			continue;
		}
		$mime = (string) get_post_mime_type( $id );
		if ( ! in_array( $mime, sa_photo_admin_mimes(), true ) ) {
			continue;
		}
		$target_id = $id;
		// اگر این عکس تصویر شاخص یا متعلق به نوشتهٔ دیگری است، یک کپی سبک WebP برای گالری بسازیم.
		if ( sa_photo_admin_is_shared( $id, $post_id ) && function_exists( 'sa_photo_optimize_bytes_to_webp' ) ) {
			$src_file = (string) get_attached_file( $id );
			$raw      = ( '' !== $src_file && is_readable( $src_file ) ) ? (string) @file_get_contents( $src_file ) : ''; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			$opt      = '' !== $raw ? sa_photo_optimize_bytes_to_webp( $raw, SA_PHOTO_MAX_WEBP_BYTES ) : null;
			if ( is_array( $opt ) ) {
				$title_src = (string) get_the_title( $id );
				$upload    = wp_upload_bits( 'sa-photo-' . $post_id . '-' . substr( md5( $id . '|' . microtime( true ) ), -6 ) . '.webp', null, $opt['bytes'] );
				if ( is_array( $upload ) && empty( $upload['error'] ) && ! empty( $upload['file'] ) ) {
					$cloned = (int) wp_insert_attachment(
						array(
							'post_mime_type' => 'image/webp',
							'post_title'     => wp_slash( $title_src ),
							'post_content'   => '',
							'post_status'    => 'inherit',
						),
						$upload['file'],
						$post_id,
						true
					);
					if ( $cloned > 0 ) {
						sa_photo_store_webp_meta( $cloned, $upload['file'], $opt['width'], $opt['height'], $opt['size'], $title_src );
						$target_id = $cloned;
					}
				}
			}
		}

		update_post_meta( $target_id, '_sa_photo_city', $post_id );
		update_post_meta( $target_id, '_sa_photo_hide', '' );
		$place = trim( (string) get_post_meta( $target_id, SA_PHOTO_META_PLACE, true ) );
		if ( '' === $place ) {
			$place = trim( (string) get_the_title( $target_id ) );
			update_post_meta( $target_id, SA_PHOTO_META_PLACE, wp_slash( $place ) );
			update_post_meta( $target_id, '_wp_attachment_image_alt', wp_slash( $place ) );
		}
		if ( '' === (string) get_post_meta( $target_id, '_sa_photo_key', true ) ) {
			update_post_meta( $target_id, '_sa_photo_key', 'sa-photo:' . md5( $post_id . '|' . $place . '|' . $target_id ) );
		}
		if ( '' === (string) get_post_meta( $target_id, '_sa_photo_kind', true ) ) {
			update_post_meta( $target_id, '_sa_photo_kind', 'heritage' );
		}
		if ( 0 === (int) get_post_meta( $target_id, '_sa_photo_order', true ) ) {
			update_post_meta( $target_id, '_sa_photo_order', $next_order++ );
		}
		if ( $default_credit ) {
			if ( '' === trim( (string) get_post_meta( $target_id, SA_PHOTO_META_AUTHOR, true ) ) ) {
				update_post_meta( $target_id, SA_PHOTO_META_AUTHOR, wp_slash( 'سرزمین آریان' ) );
			}
			if ( '' === sa_photo_license_label( (string) get_post_meta( $target_id, SA_PHOTO_META_LICENSE, true ) ) ) {
				update_post_meta( $target_id, SA_PHOTO_META_LICENSE, 'sa-archive' );
			}
		}
		if ( $target_id === $id && function_exists( 'sa_photo_optimize_attachment' ) ) {
			sa_photo_optimize_attachment( $target_id, false );
		}
		$done[] = $target_id;
	}
	wp_send_json_success( array( 'attached' => $done, 'count' => count( $done ) ) );
}
add_action( 'wp_ajax_sa_photo_attach', 'sa_photo_ajax_attach' );

/**
 * ajax: تعویض فایل یک عکس شهرستان با فایلی از کتابخانهٔ رسانه (WebP زیر ۱۰۰ کیلوبایت).
 */
function sa_photo_ajax_swap() {
	$post_id = sa_photo_admin_ajax_guard();
	$att_id  = isset( $_POST['att_id'] ) ? absint( wp_unslash( $_POST['att_id'] ) ) : 0;
	$src_id  = isset( $_POST['src_id'] ) ? absint( wp_unslash( $_POST['src_id'] ) ) : 0;
	if ( ! $att_id || (int) get_post_meta( $att_id, '_sa_photo_city', true ) !== $post_id ) {
		wp_send_json_error( array( 'message' => 'این تصویر متعلق به این شهرستان نیست.' ), 400 );
	}
	$done = sa_photo_replace_with_attachment( $att_id, $src_id );
	if ( is_wp_error( $done ) ) {
		wp_send_json_error( array( 'message' => $done->get_error_message() ), (int) ( $done->get_error_data()['status'] ?? 500 ) );
	}
	wp_send_json_success( array( 'swapped' => $att_id ) );
}
add_action( 'wp_ajax_sa_photo_swap', 'sa_photo_ajax_swap' );

/**
 * ajax: برداشتن یک عکس از گالری شهرستان (پیوست در کتابخانهٔ رسانه می‌ماند).
 */
function sa_photo_ajax_detach() {
	$post_id = sa_photo_admin_ajax_guard();
	$att_id  = isset( $_POST['att_id'] ) ? absint( wp_unslash( $_POST['att_id'] ) ) : 0;
	if ( ! $att_id || (int) get_post_meta( $att_id, '_sa_photo_city', true ) !== $post_id ) {
		wp_send_json_error( array( 'message' => 'این تصویر متعلق به این شهرستان نیست.' ), 400 );
	}
	foreach ( array( '_sa_photo_city', '_sa_photo_key', '_sa_photo_lead', '_sa_photo_order', '_sa_photo_hide' ) as $key ) {
		delete_post_meta( $att_id, $key );
	}
	wp_send_json_success( array( 'detached' => $att_id ) );
}
add_action( 'wp_ajax_sa_photo_detach', 'sa_photo_ajax_detach' );

/**
 * ajax: انتقال برگشت‌پذیر یک تصویر گالری به زباله‌دان رسانه (هرگز حذف دائمی نمی‌شود).
 */
function sa_photo_ajax_trash() {
	$post_id = sa_photo_admin_ajax_guard();
	$att_id  = isset( $_POST['att_id'] ) ? absint( wp_unslash( $_POST['att_id'] ) ) : 0;
	if ( ! $att_id || (int) get_post_meta( $att_id, '_sa_photo_city', true ) !== $post_id ) {
		wp_send_json_error( array( 'message' => 'این تصویر متعلق به این شهرستان نیست.' ), 400 );
	}
	if ( ! current_user_can( 'delete_post', $att_id ) ) {
		wp_send_json_error( array( 'message' => 'اجازهٔ انتقال این تصویر به زباله‌دان را ندارید.' ), 403 );
	}
	foreach ( array( '_sa_photo_city', '_sa_photo_key', '_sa_photo_lead', '_sa_photo_order', '_sa_photo_hide' ) as $key ) {
		delete_post_meta( $att_id, $key );
	}
	if ( ! wp_trash_post( $att_id ) ) {
		wp_send_json_error( array( 'message' => 'انتقال به زباله‌دان انجام نشد.' ), 500 );
	}
	wp_send_json_success( array( 'trashed' => $att_id ) );
}
add_action( 'wp_ajax_sa_photo_trash', 'sa_photo_ajax_trash' );

/**
 * ajax: بهینه‌سازی همهٔ عکس‌های همین شهرستان به WebP زیر ۱۰۰ کیلوبایت.
 */
function sa_photo_ajax_optimize() {
	$post_id = sa_photo_admin_ajax_guard();
	if ( ! function_exists( 'sa_photo_optimize_attachment' ) ) {
		wp_send_json_error( array( 'message' => 'بهینه‌ساز WebP در دسترس نیست.' ), 500 );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$count = 0;
	foreach ( sa_photo_admin_list_ids( $post_id ) as $att_id ) {
		$res = sa_photo_optimize_attachment( $att_id, false );
		if ( ! is_wp_error( $res ) ) {
			++$count;
		}
	}
	wp_send_json_success( array( 'optimized' => $count ) );
}
add_action( 'wp_ajax_sa_photo_optimize', 'sa_photo_ajax_optimize' );
