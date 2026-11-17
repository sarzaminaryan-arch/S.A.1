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
 * این فایل سه کار می‌کند و هیچ‌کدام بدون داده انجام نمی‌شود:
 *
 * ۱) **واردکردن تصویر با مجوز** (`/photos/import` در پل ایجنت).
 *    خودِ سایت فایل را از دامنهٔ مجاز می‌گیرد (سندباکس ایجنت به اینترنت
 *    تصویری دسترسی ندارد)، مجوز را *پیش از* ذخیره بررسی می‌کند و پرووننس
 *    (پدیدآور، مجوز، نشانی اصل، تاریخ) را روی پیوست می‌نویسد. مجوزهای
 *    مجاز: CC0/PD، CC BY، CC BY-SA. هر چیز دیگر (ND، NC، «همه حقوق
 *    محفوظ»، بدون مجوز) رد می‌شود — صفحه‌ای که عکاسش را نام نمی‌برد یا
 *    اجازه‌اش را ندارد، منتشر نمی‌شود.
 *
 * ۲) **کادر «مکان در یک نگاه»**: تصویر مربع (sa-square) + متن راهنما و
 *    هشدار، با ردیف اعتبار عکاس زیر تصویر.
 *
 * ۳) **نوار گالری سه‌کادرِ ثابت**: سه مکان تفریحی، هر کدام یک قاب با
 *    نسبت ثابت و زیرش هایلایت توضیح (نام مکان، یک خط، و لینک/برچسب).
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
const SA_PHOTO_META_PLACE    = '_sa_photo_place';
const SA_PHOTO_META_NOTE     = '_sa_photo_note';
const SA_PHOTO_META_AREA     = '_sa_photo_area';

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
	$key = strtolower( trim( (string) $code ) );
	// ویکی‌مدیا همین مجوز را با نسخه می‌دهد (`cc-by-sa-3.0`)؛ نسخهٔ ریز تفاوتی در
	// حق استفاده نمی‌سازد، پس یک شکل پذیرفته می‌شود.
	$key = (string) preg_replace( '/\.0$/', '', $key );
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
	$labels        = sa_photo_allowed_licenses();
	$code          = strtolower( (string) get_post_meta( $attachment_id, SA_PHOTO_META_LICENSE, true ) );
	return array(
		'author'        => (string) get_post_meta( $attachment_id, SA_PHOTO_META_AUTHOR, true ),
		'license'       => $code,
		'license_label' => isset( $labels[ $code ] ) ? $labels[ $code ] : '',
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
			// عکس‌هایی است که از قبل در کتابخانه هست. آلبوم قدیمی گالری جدا می‌ماند.
			'post_mime_type' => array( 'image/jpeg', 'image/webp' ),
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
 * فهرست عکس‌های «تفریحی» (kind=fun) برای نوار سه‌کادره.
 *
 * @param int $city_id شناسه.
 * @param int $limit   سقف.
 * @return int[]
 */
function sa_photo_fun_ids( $city_id, $limit = 3 ) {
	$ids = array();
	foreach ( sa_photo_place_ids( $city_id, 24 ) as $id ) {
		if ( 'fun' === (string) get_post_meta( $id, '_sa_photo_kind', true ) ) {
			$ids[] = $id;
		}
		if ( count( $ids ) >= $limit ) {
			break;
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
	if ( '' !== $label ) {
		// نام مکان روی تصویر، با سایه و لایهٔ تیرهٔ پایین قاب تا روی هر عکسی خوانا بماند.
		$out .= '<figcaption class="sa-photo__caption">';
		$out .= '<span class="sa-photo__place">' . esc_html( $label ) . '</span>';
		if ( '' !== $area ) {
			$out .= '<span class="sa-photo__area">' . esc_html( $area ) . '</span>';
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
 * کادر «مکان در یک نگاه» — تصویر مربع + راهنما و هشدار.
 *
 * @param int $city_id شناسهٔ شهرستان.
 * @return void
 */
function sa_photo_spotlight( $city_id = 0 ) {
	$city_id = $city_id ? absint( $city_id ) : get_the_ID();
	if ( 'city' !== get_post_type( $city_id ) ) {
		return;
	}
	$ids = sa_photo_place_ids( $city_id, 12 );
	$lead = 0;
	$kind = '';
	foreach ( $ids as $id ) {
		if ( 'fun' !== (string) get_post_meta( $id, '_sa_photo_kind', true ) ) {
			$lead = $id;
			$kind = (string) get_post_meta( $id, '_sa_photo_kind', true );
			break;
		}
	}
	if ( ! $lead ) {
		return;
	}
	$place = (string) get_post_meta( $lead, SA_PHOTO_META_PLACE, true );
	$note  = (string) get_post_meta( $lead, SA_PHOTO_META_NOTE, true );
	$map   = array(
		'heritage' => 'میراث تاریخی',
		'fun'      => 'تفریحی',
		'nature'   => 'طبیعی',
		'modern'   => 'مدرن',
	);
	?>
	<section class="sa-spotlight" aria-label="<?php echo esc_attr( 'مکان در یک نگاه: ' . ( $place ? $place : get_the_title( $city_id ) ) ); ?>">
		<div class="sa-spotlight__grid">
			<div class="sa-spotlight__media">
				<?php
				// ۱:۱ با برش مرکزی. اندازهٔ srct محدود است تا فایل بزرگ مربعی دانلود نشود؛
				// برش از وردپرس (sa-square 480×480) می‌آید، نه از CSS.
				// نام مکان روی خود عکس نوشته می‌شود و خط اعتبار کوچک زیر قاب می‌آید.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ساخته‌شده با توابع escape داخلی.
				echo sa_photo_frame( $lead, 'sa-square', 'sa-spotlight__frame' );
				?>
				<?php if ( '' !== $place ) : ?>
					<span class="sa-spotlight__badge"><?php echo esc_html( isset( $map[ $kind ] ) ? $map[ $kind ] : 'دیدنی' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="sa-spotlight__body">
				<span class="sa-spotlight__eyebrow">مکان در یک نگاه</span>
				<h2 class="sa-spotlight__title"><?php echo esc_html( '' !== $place ? $place : get_the_title( $city_id ) ); ?></h2>
				<?php if ( '' !== $note ) : ?>
					<p class="sa-spotlight__note"><?php echo esc_html( $note ); ?></p>
				<?php endif; ?>
				<ul class="sa-spotlight__points">
					<?php
					$points = array_filter( array_map( 'trim', explode( '|', (string) get_post_meta( $lead, '_sa_photo_points', true ) ) ) );
					foreach ( $points as $point ) :
						?>
						<li><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
	</section>
	<?php
}

/**
 * نوار گالری سه مکان تفریحی — قاب ثابت + هایلایت زیرش.
 *
 * @param int $city_id شناسهٔ شهرستان.
 * @return void
 */
function sa_photo_strip( $city_id = 0 ) {
	$city_id = $city_id ? absint( $city_id ) : get_the_ID();
	if ( 'city' !== get_post_type( $city_id ) ) {
		return;
	}
	$ids = sa_photo_fun_ids( $city_id, 3 );
	if ( count( $ids ) < 3 ) {
		return;
	}
	?>
	<section class="sa-strip" aria-label="سه مکان تفریحی">
		<div class="sa-strip__head">
			<h2 class="sa-strip__title">سه مکان تفریحی <?php echo esc_html( get_the_title( $city_id ) ); ?></h2>
			<p class="sa-strip__sub">هر قاب یک مکان؛ زیر هر تصویر، هایلایت همان مکان.</p>
		</div>
		<div class="sa-strip__grid">
			<?php
			foreach ( $ids as $id ) :
				$line  = (string) get_post_meta( $id, '_sa_photo_highlight', true );
				$tag   = (string) get_post_meta( $id, '_sa_photo_tag', true );
				$label = sa_photo_overlay_label( $id );
				$area  = sa_photo_area( $id );
				?>
				<article class="sa-strip__tile">
					<div class="sa-strip__frame">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						// قاب ثابت ۴:۳؛ اندازهٔ srct همان کارت ۷۲۰×۴۵۰ است.
						// نام مکان روی عکس می‌نشیند (منبع حقیقت: فراداده)، پس متن دوباره زیرش تکرار نمی‌شود.
						echo sa_photo_frame( $id, 'sa-gallery-card', 'sa-strip__photo', false );
						?>
						<?php if ( '' !== $tag ) : ?>
							<span class="sa-strip__tag"><?php echo esc_html( $tag ); ?></span>
						<?php endif; ?>
					</div>
					<div class="sa-strip__highlight">
						<?php if ( '' !== $line ) : ?>
							<p class="sa-strip__line"><?php echo esc_html( $line ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $area && false === strpos( $label, $area ) ) : ?>
							<p class="sa-strip__area"><?php echo esc_html( $area ); ?></p>
						<?php endif; ?>
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo sa_photo_credit_line( $id, 'sa-strip__credit' );
						?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * ذخیرهٔ فایل تازه روی یک پیوست موجود (همان مکان، نشانی تازه).
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
			'redirection'         => 0,
			'limit_response_size' => 3 * MB_IN_BYTES,
			'user-agent'          => 'SarzaminaryanBot/1.0 (https://sarzaminaryan.ir; licensed photo import)',
		)
	);
	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'sa_photo_fetch', 'دریافت تصویر ناموفق بود: ' . $response->get_error_message(), array( 'status' => 502 ) );
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'sa_photo_fetch_status', 'سرور مبدأ فایل تازه را نداد.', array( 'status' => 502 ) );
	}
	$bytes = (string) wp_remote_retrieve_body( $response );
	if ( "\xFF\xD8\xFF" !== substr( $bytes, 0, 3 ) || strlen( $bytes ) < 2000 || strlen( $bytes ) > 3 * MB_IN_BYTES ) {
		return new WP_Error( 'sa_photo_mime', 'فایل تازه JPEG سالم نیست.', array( 'status' => 415 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$slug     = sanitize_title( $place ? $place : 'photo' );
	$filename = 'sa-photo-' . ( '' !== $slug ? $slug : 'photo' ) . '-' . substr( md5( $url ), -6 ) . '.jpg';
	$upload   = wp_upload_bits( $filename, null, $bytes );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_upload', 'ذخیرهٔ فایل تازه انجام نشد.', array( 'status' => 500 ) );
	}
	update_attached_file( absint( $att_id ), $upload['file'] );
	wp_update_post(
		array(
			'ID'             => absint( $att_id ),
			'post_mime_type' => 'image/jpeg',
			'post_title'     => wp_slash( '' !== $place ? $place : $filename ),
			'post_status'    => 'inherit',
		)
	);
	$meta = wp_generate_attachment_metadata( absint( $att_id ), $upload['file'] );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( absint( $att_id ), $meta );
	}
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
	$city_id = absint( $r->get_param( 'city_id' ) );
	$args    = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/webp' ),
		'posts_per_page' => 100,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array( array( 'key' => '_sa_photo_key', 'compare' => 'EXISTS' ) ),
	);
	if ( $city_id ) {
		$args['meta_query'][] = array( 'key' => '_sa_photo_city', 'value' => $city_id );
	}
	$rows = array();
	foreach ( (array) get_posts( $args ) as $id ) {
		$credit = sa_photo_credit( $id );
		$rows[] = array(
			'id'            => (int) $id,
			'city_id'       => (int) get_post_meta( $id, '_sa_photo_city', true ),
			'place'         => (string) get_post_meta( $id, SA_PHOTO_META_PLACE, true ),
			'kind'          => (string) get_post_meta( $id, '_sa_photo_kind', true ),
			'author'        => $credit['author'],
			'license'       => $credit['license'],
			'license_label' => $credit['license_label'],
			'source'        => $credit['source'],
			'retrieved'     => $credit['retrieved'],
			'publishable'   => sa_photo_is_publishable( $id ),
		);
	}
	$per_city = array();
	foreach ( $rows as $row ) {
		if ( $row['publishable'] ) {
			$per_city[ $row['city_id'] ] = isset( $per_city[ $row['city_id'] ] ) ? $per_city[ $row['city_id'] ] + 1 : 1;
		}
	}
	return rest_ensure_response( array( 'count' => count( $rows ), 'per_city' => $per_city, 'photos' => $rows ) );
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
		}
		sa_photo_write_meta( $att_id, $b, $city_id, $place, $kind, $key, $url, $author, $license );
		return rest_ensure_response(
			array(
				'id'       => $att_id,
				'reused'   => true,
				'replaced' => $replaced,
				'url'      => wp_get_attachment_url( $att_id ),
			)
		);
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 45,
			'redirection'         => 0,
			'limit_response_size' => 3 * MB_IN_BYTES,
			'user-agent'          => 'SarzaminaryanBot/1.0 (https://sarzaminaryan.ir; photo import with attribution)',
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
	$mime  = function_exists( 'sa_agent_detect_image_mime' ) ? (string) sa_agent_detect_image_mime( $bytes ) : '';
	if ( '' === $mime ) {
		$mime = "\xFF\xD8\xFF" === substr( $bytes, 0, 3 ) ? 'image/jpeg' : '';
	}
	if ( '' === $mime || 'image/jpeg' !== $mime ) {
		return new WP_Error( 'sa_photo_mime', 'فقط تصویر واقعی JPEG پذیرفته می‌شود.', array( 'status' => 415 ) );
	}
	if ( strlen( $bytes ) < 2000 || strlen( $bytes ) > 3 * MB_IN_BYTES ) {
		return new WP_Error( 'sa_photo_size', 'حجم فایل مناسب نیست.', array( 'status' => 413 ) );
	}

	$slug = sanitize_title( $place ? $place : 'photo' );
	if ( '' === $slug ) {
		$slug = 'photo';
	}
	$filename = 'sa-photo-' . $slug . '-' . substr( $key, -6 ) . '.jpg';

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload = wp_upload_bits( $filename, null, $bytes );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		return new WP_Error( 'sa_photo_upload', 'ذخیرهٔ فایل انجام نشد.', array( 'status' => 500 ) );
	}

	$att_id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => wp_slash( '' !== $place ? $place : $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$city_id,
		true
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return new WP_Error( 'sa_photo_attachment', 'ثبت پیوست انجام نشد.', array( 'status' => 500 ) );
	}
	$meta = wp_generate_attachment_metadata( (int) $att_id, $upload['file'] );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( (int) $att_id, $meta );
	}

	sa_photo_write_meta( (int) $att_id, $b, $city_id, $place, $kind, $key, $url, $author, $license );

	// آلبوم گالری موجود (که فقط image/webp را می‌خواند) دست‌نخورده می‌ماند؛
	// این عکس‌ها مسیر مستقل خودشان را دارند.
	return rest_ensure_response(
		array(
			'id'      => (int) $att_id,
			'reused'  => false,
			'url'     => (string) ( $upload['url'] ?? wp_get_attachment_url( (int) $att_id ) ),
			'bytes'   => strlen( $bytes ),
			'license' => sa_photo_license_label( $license ),
		)
	);
}
