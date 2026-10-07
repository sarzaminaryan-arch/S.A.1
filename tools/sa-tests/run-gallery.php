<?php
/**
 * تست‌های «گالری تصاویر و آلبوم‌ها» (inc/gallery.php)
 *
 * رفتارِ درخواستی: بلوکِ آلبوم فقط زمانی نمایش داده شود که دست‌کم یک تصویر
 * (افزودهٔ مدیر، یا ارسالیِ مخاطب که مدیر تأیید کرده) داشته باشد؛
 * آلبوم‌های خالی پنهان بمانند و بخشِ ارسال تصویر فعال باشد.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-gallery.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

/**
 * شبیه‌سازِ sa_fa_digits (inc/helpers.php).
 */
function sa_fa_digits( $value ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, (string) $value );
}

/**
 * شبیه‌سازِ inc/relations.php.
 */
function sa_get_children( $parent_id, $child_type ) {
	$meta_key = 'sa_' . get_post_type( $parent_id ) . '_id';
	$ids      = get_posts(
		array(
			'post_type'      => $child_type,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 500,
			'meta_query'     => array(
				array(
					'key'   => $meta_key,
					'value' => (int) $parent_id,
				),
			),
		)
	);
	if ( ! $ids ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => $child_type,
			'post__in'       => $ids,
			'posts_per_page' => count( $ids ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
}

require '/ws/theme/inc/gallery.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

/**
 * اجرای sa_gallery_render_section و گرفتنِ خروجی.
 *
 * @param int    $post_id Post ID.
 * @param string $context province|city.
 * @return string
 */
function sa_render( $post_id, $context = '' ) {
	ob_start();
	sa_gallery_render_section( (int) $post_id, $context );
	return (string) ob_get_clean();
}

/**
 * افزودن یک تصویرِ پیوست به آلبوم.
 *
 * @param int    $province_id Province.
 * @param int    $city_id     City.
 * @param string $status      approved|pending|rejected.
 * @return int Attachment ID.
 */
function sa_add_image( $province_id, $city_id, $status = 'approved' ) {
	return sa_add_post(
		array(
			'post_title'   => 'تصویر ' . $city_id,
			'post_type'    => 'attachment',
			'post_status'  => 'inherit',
			'post_name'    => 'image-' . $city_id . '-' . $GLOBALS['sa_next_id'],
			'meta'         => array(
				'sa_gallery_province_id' => $province_id,
				'sa_gallery_city_id'     => $city_id,
				'sa_gallery_status'      => $status,
			),
		)
	);
}

echo "== آماده‌سازی ==\n";

sa_reset_test_state();
do_action( 'init' ); // ثبتِ taxonomy آلبوم‌ها
sa_ok( 'taxonomy آلبوم‌ها ثبت شد', taxonomy_exists( 'sa_gallery_album' ) );

$province = sa_add_post( array( 'post_title' => 'لرستان', 'post_type' => 'province', 'post_name' => 'lorestan' ) );
$dorud    = sa_add_post( array( 'post_title' => 'دورود', 'post_type' => 'city', 'post_name' => 'dorud', 'meta' => array( 'sa_province_id' => $province ) ) );
$khoram   = sa_add_post( array( 'post_title' => 'خرم‌آباد', 'post_type' => 'city', 'post_name' => 'khorramabad', 'meta' => array( 'sa_province_id' => $province ) ) );
$borujerd = sa_add_post( array( 'post_title' => 'بروجرد', 'post_type' => 'city', 'post_name' => 'borujerd', 'meta' => array( 'sa_province_id' => $province ) ) );
$attr     = sa_add_post( array( 'post_title' => 'دریاچه گهر', 'post_type' => 'attraction', 'post_name' => 'gahar' ) );

echo "== پیش از افزودنِ تصویر: هیچ بلوکی نمایش داده نمی‌شود ==\n";

sa_ok( 'آلبومِ دورود خالی است', ! sa_gallery_album_has_images( $province, $dorud ) );
sa_ok( 'آلبومِ خرم‌آباد خالی است', ! sa_gallery_album_has_images( $province, $khoram ) );
sa_eq( 'صفحهٔ شهرِ خالی: بدون خروجی', '', sa_render( $dorud, 'city' ) );
sa_eq( 'صفحهٔ استانِ خالی: بدون خروجی', '', sa_render( $province, 'province' ) );
sa_eq( 'نوعِ نامرتبط: بدون خروجی', '', sa_render( $attr, 'attraction' ) );

echo "\n== تصویرِ تأییدنشده نمایش نمی‌آورد ==\n";

sa_add_image( $province, $dorud, 'pending' );
sa_gallery_flush_cache( $province, $dorud );
sa_ok( 'تصویرِ در انتظار: آلبوم همچنان خالی است', ! sa_gallery_album_has_images( $province, $dorud ) );
sa_eq( 'تصویرِ در انتظار: صفحهٔ شهر بدون خروجی', '', sa_render( $dorud, 'city' ) );

sa_add_image( $province, $dorud, 'rejected' );
sa_gallery_flush_cache( $province, $dorud );
sa_ok( 'تصویرِ ردشده: آلبوم همچنان خالی است', ! sa_gallery_album_has_images( $province, $dorud ) );

echo "\n== پس از تأیید: بلوک نمایش داده می‌شود ==\n";

$img1 = sa_add_image( $province, $dorud, 'approved' );
$img2 = sa_add_image( $province, $dorud, 'approved' );
sa_gallery_flush_cache( $province, $dorud );
sa_ok( 'آلبومِ دورود تصویر دارد', sa_gallery_album_has_images( $province, $dorud ) );
sa_eq( 'دو تصویرِ تأییدشده شمارش شد', 2, count( sa_gallery_image_ids( $province, $dorud ) ) );

$html = sa_render( $dorud, 'city' );
sa_has( 'بلوکِ گالری در صفحهٔ شهر ظاهر شد', '<section class="sa-gallery"', $html );
sa_has( 'عنوانِ گالری شهر', 'گالری تصاویر دورود', $html );
sa_has( 'شمارشِ تصویرها', '۲ تصویر', $html );
sa_has( 'لینکِ ارسال تصویر در کارت', '#cc-contrib', $html );
sa_has( 'دادهٔ JSON آلبوم برای لایت‌باکس', 'data-sa-gallery-album=', $html );
sa_has( 'تصویرِ آلبوم در خروجی', 'file-' . $img1, $html );

echo "\n== تصویرِ ارسالیِ مخاطب که مدیر تأیید می‌کند ==\n";

$img3 = sa_add_image( $province, $khoram, 'pending' );
sa_gallery_flush_cache( $province, $khoram );
sa_eq( 'پیش از تأیید: صفحهٔ شهر خالی است', '', sa_render( $khoram, 'city' ) );

update_post_meta( $img3, 'sa_gallery_status', 'approved' );
sa_gallery_sync_contribution_image_status( 0, 'publish' ); // بررسیِ مسیرِ همگام‌سازی بدون تصویر
sa_gallery_flush_cache( $province, $khoram );
sa_ok( 'پس از تأیید: آلبوم تصویر دارد', sa_gallery_album_has_images( $province, $khoram ) );
sa_has( 'پس از تأیید: بلوک ظاهر شد', '<section class="sa-gallery"', sa_render( $khoram, 'city' ) );

echo "\n== صفحهٔ استان: فقط آلبوم‌های دارای تصویر ==\n";

$html = sa_render( $province, 'province' );
sa_has( 'بلوکِ استان ظاهر شد', '<section class="sa-gallery"', $html );
sa_has( 'آلبومِ دورود نمایش دارد', 'دورود', $html );
sa_has( 'آلبومِ خرم‌آباد نمایش دارد', 'خرم‌آباد', $html );
sa_lacks( 'آلبومِ خالیِ بروجرد پنهان است', 'بروجرد', $html );
sa_has( 'شمارندهٔ آلبوم‌های پنهان', 'data-sa-gallery-empty="1"', $html );
sa_eq( 'تنها دو کارتِ آلبوم ساخته شد', 2, substr_count( $html, 'data-sa-gallery-album=' ) );
sa_lacks( 'کارتِ خالی ساخته نشد', 'is-empty', $html );

echo "\n== افزودنِ تصویرِ تازه، آلبومِ پنهان را نمایان می‌کند ==\n";

sa_add_image( $province, $borujerd, 'approved' );
sa_gallery_flush_cache( $province, $borujerd );
$html = sa_render( $province, 'province' );
sa_has( 'آلبومِ بروجرد اکنون نمایش دارد', 'بروجرد', $html );
sa_has( 'دیگر آلبومِ پنهانی نیست', 'data-sa-gallery-empty="0"', $html );
sa_eq( 'سه کارتِ آلبوم', 3, substr_count( $html, 'data-sa-gallery-album=' ) );

echo "\n== بخشِ ارسال تصویر همیشه در دسترس است ==\n";

$payload = sa_gallery_album_payload( $province, $borujerd );
sa_has( 'نشانیِ ارسال تصویر برای هر آلبوم ساخته می‌شود', '#cc-contrib', (string) $payload['uploadUrl'] );
$empty_payload = sa_gallery_album_payload( $province, $dorud );
sa_has( 'نشانیِ ارسال تصویر حتی برای آلبومِ دارای تصویر هست', '#cc-contrib', (string) $empty_payload['uploadUrl'] );

echo "\n== فیلترِ sa_gallery_hide_empty_albums ==\n";

$restore = function () {
	return false;
};
add_filter( 'sa_gallery_hide_empty_albums', $restore );
sa_delete_image( $province, $borujerd );
$html = sa_render( $province, 'province' );
sa_has( 'با غیرفعال‌کردنِ فیلتر، آلبومِ خالی دوباره نمایش دارد', 'بروجرد', $html );
sa_has( 'کارتِ خالی با کلاس is-empty', 'is-empty', $html );
sa_has( 'پیامِ آلبومِ آماده', 'هنوز تصویری تأیید نشده', $html );

/**
 * حذفِ همهٔ تصویرهای یک آلبوم (برای تست).
 *
 * @param int $province_id Province.
 * @param int $city_id     City.
 */
function sa_delete_image( $province_id, $city_id ) {
	foreach ( sa_gallery_image_ids( $province_id, $city_id, 100 ) as $id ) {
		unset( $GLOBALS['sa_posts'][ $id ] );
	}
	sa_gallery_flush_cache( $province_id, $city_id );
}

sa_done();
