<?php
/**
 * تست‌های «انتشار خودکار در شبکه‌های اجتماعی» (inc/social-publish.php)
 * شامل ارسالِ انبوهِ مطالبِ موجود.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-social.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';
require '/ws/theme/inc/social-publish.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

echo "== تنظیمات و کمک‌کننده‌ها ==\n";

sa_reset_test_state();
$defaults = sa_social_defaults();
sa_eq( 'پیش‌فرض: تلگرام غیرفعال', 0, $defaults['telegram_enable'] );
sa_eq( 'پیش‌فرض: تأخیر ۲ دقیقه', 2, $defaults['delay_minutes'] );
sa_ok( 'انواع محتوا شامل province است', in_array( 'province', sa_social_post_types(), true ) );
sa_ok( 'انواع محتوا شامل city است', in_array( 'city', sa_social_post_types(), true ) );

update_option( 'sa_social_settings', array( 'telegram_chat' => '@sarzaminaryan' ) );
sa_eq( 'ادغامِ تنظیمات با پیش‌فرض‌ها', '@sarzaminaryan', sa_social_settings()['telegram_chat'] );
sa_eq( 'مقدارِ پیش‌فرضِ حفظ‌شده', 2, sa_social_settings()['delay_minutes'] );

sa_eq( 'حدِ طولِ تلگرام (متن)', 3900, sa_social_max_length( 'telegram', false ) );
sa_eq( 'حدِ طولِ تلگرام (توضیح)', 1000, sa_social_max_length( 'telegram', true ) );
sa_eq( 'حدِ طولِ اینستاگرام', 2200, sa_social_max_length( 'instagram' ) );

$img_post = sa_add_post(
	array(
		'post_title'   => 'دریاچه گهر',
		'post_type'    => 'attraction',
		'post_name'    => 'gahar',
		'post_excerpt' => 'دریاچه‌ای در ارتفاعِ زاگرس.',
		'post_content' => '<p>متن</p><img src="https://upload.test/a.png" /><img src="https://upload.test/b.jpg" />',
	)
);
$images = sa_social_content_images( $img_post );
sa_eq( 'دو تصویر از بدنه استخراج شد', 2, count( $images ) );
sa_eq( 'ترتیبِ تصویرها حفظ شد', 'https://upload.test/a.png', $images[0] );

$no_img = sa_add_post(
	array(
		'post_title'   => 'بدون تصویر',
		'post_type'    => 'attraction',
		'post_name'    => 'no-image',
		'post_content' => '<p>متن بدون تصویر</p>',
	)
);
sa_eq( 'بدون تصویر → آرایهٔ خالی', array(), sa_social_content_images( $no_img ) );

sa_eq( 'تلگرام: نخستین تصویر انتخاب می‌شود', 'https://upload.test/a.png', sa_social_image_url( $img_post, 'telegram' ) );
sa_eq( 'اینستاگرام: فقط JPEG', 'https://upload.test/b.jpg', sa_social_image_url( $img_post, 'instagram' ) );
sa_eq( 'اینستاگرامِ بدون JPEG → خالی', '', sa_social_image_url( $no_img, 'instagram' ) );

$thumb_post = sa_add_post(
	array(
		'post_title'   => 'تصویر شاخص',
		'post_type'    => 'attraction',
		'post_name'    => 'thumb',
		'post_content' => '<img src="https://upload.test/c.jpg" />',
		'meta'         => array( '_thumb' => 'https://upload.test/featured.jpg' ),
	)
);
sa_eq( 'تصویر شاخص اولویت دارد', 'https://upload.test/featured.jpg', sa_social_image_url( $thumb_post, 'telegram' ) );

$long_post = sa_add_post(
	array(
		'post_title'   => 'متنِ بلند',
		'post_type'    => 'attraction',
		'post_name'    => 'long',
		'post_content' => str_repeat( 'الفب ', 200 ) . '[gallery id="1"]',
	)
);
$excerpt = sa_social_excerpt_text( get_post( $long_post ), 100 );
sa_lacks( 'چکیده بدون شورت‌کد است', '[gallery', $excerpt );
sa_ok( 'چکیده به اندازهٔ max بریده شده', function_exists( 'mb_strlen' ) ? mb_strlen( $excerpt, 'UTF-8' ) <= 100 : true );

$text = sa_social_build_text( '{title} | {link} | {excerpt}', $img_post, 'telegram', false );
sa_has( 'جاگذاریِ عنوان', 'دریاچه گهر', $text );
sa_has( 'جاگذاریِ لینک', 'https://sarzaminaryan.test/attraction/gahar/', $text );
sa_has( 'جاگذاریِ چکیده', 'دریاچه‌ای در ارتفاع', $text );

$escaped = sa_social_build_text( '{title}', sa_add_post( array( 'post_title' => 'عکس <b>تست</b> &amp;', 'post_type' => 'attraction', 'post_name' => 'esc' ) ), 'telegram', false );
sa_has( 'تلگرام: عنوان HTML-escape می‌شود', '&lt;b&gt;', $escaped );

echo "\n== ارسال به تلگرام ==\n";

update_option( 'sa_social_settings', array( 'telegram_token' => '123456:ABC', 'telegram_chat' => '' ) );
$res = sa_social_telegram_send( $img_post );
sa_ok( 'بدون شناسهٔ کانال خطا می‌دهد', ! $res['ok'] );
sa_has( 'پیامِ خطای شناسهٔ کانال', 'شناسهٔ کانال', $res['message'] );

update_option( 'sa_social_settings', array( 'telegram_token' => '123456:ABC', 'telegram_chat' => '@sarzaminaryan', 'telegram_image' => 1 ) );
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 5 ) ) );
$res = sa_social_telegram_send( $img_post );
sa_ok( 'ارسال با تصویر موفق است', $res['ok'] );
$req = sa_last_request();
sa_has( 'متد sendPhoto برای مطلبِ تصویر‌دار', 'sendPhoto', $req['url'] );
$body = json_decode( $req['args']['body'], true );
sa_eq( 'توضیحِ تصویر ارسال شد', true, isset( $body['caption'] ) && '' !== $body['caption'] );
sa_eq( 'حالت HTML', 'HTML', $body['parse_mode'] );
sa_has( 'لینک در توضیح هست', 'sarzaminaryan.test', $body['caption'] );

sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 6 ) ) );
sa_social_telegram_send( $no_img );
sa_has( 'مطلبِ بدون تصویر با sendMessage می‌رود', 'sendMessage', sa_last_request()['url'] );

update_option( 'sa_social_settings', array( 'telegram_token' => '123456:ABC', 'telegram_chat' => '@sarzaminaryan', 'telegram_image' => 0 ) );
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 7 ) ) );
sa_social_telegram_send( $img_post );
$body = json_decode( sa_last_request()['args']['body'], true );
sa_ok( 'با غیرفعال‌بودنِ تصویر، sendMessage می‌رود', ! isset( $body['photo'] ) && isset( $body['text'] ) );

update_option( 'sa_social_settings', array( 'telegram_token' => '123456:ABC', 'telegram_chat' => '@sarzaminaryan' ) );
sa_queue_response( array( 'ok' => true, 'result' => array( 'id' => 1, 'username' => 'arian_bot' ) ) );
sa_queue_response( array( 'ok' => true, 'result' => array( 'title' => 'سرزمین آریان' ) ) );
$res = sa_social_telegram_test();
sa_ok( 'تستِ تلگرام موفق', $res['ok'] );
sa_has( 'نامِ ربات در پیام تست', '@arian_bot', $res['message'] );
sa_has( 'عنوانِ کانال در پیام تست', 'سرزمین آریان', $res['message'] );

sa_queue_response( array( 'ok' => true, 'result' => array( array( 'channel_post' => array( 'chat' => array( 'id' => -1001234567890, 'title' => 'کانال' ) ) ) ) ) );
$res = sa_social_telegram_updates();
sa_ok( 'پیدا کردنِ شناسهٔ کانال', $res['ok'] );
sa_has( 'شناسهٔ منفی در خروجی', '-1001234567890', $res['message'] );

echo "\n== ارسال به اینستاگرام ==\n";

update_option( 'sa_social_settings', array( 'instagram_token' => '', 'instagram_user' => '' ) );
$res = sa_social_instagram_send( $img_post );
sa_ok( 'بدون توکن خطا می‌دهد', ! $res['ok'] );
sa_has( 'پیامِ خطای تنظیمات اینستاگرام', 'اینستاگرام', $res['message'] );

update_option( 'sa_social_settings', array( 'instagram_token' => 'IGTOKEN', 'instagram_user' => '1789' ) );
$res = sa_social_instagram_send( $no_img );
sa_ok( 'بدون تصویر JPEG خطا می‌دهد', ! $res['ok'] );
sa_has( 'پیامِ خطای نبود JPEG', 'JPEG', $res['message'] );

sa_queue_response( array( 'id' => '1789_container' ) );
sa_queue_response( array( 'id' => 'media_1' ) );
$res = sa_social_instagram_send( $img_post );
sa_ok( 'انتشار در اینستاگرام موفق', $res['ok'], $res['message'] );
$reqs = sa_requests();
sa_has( 'ساخت محفظه با /media', '/media', $reqs[ count( $reqs ) - 2 ]['url'] );
sa_has( 'انتشار با /media_publish', '/media_publish', sa_last_request()['url'] );

sa_queue_response( array( 'error' => array( 'message' => 'Media is not ready', 'code' => 9007 ) ) );
$res = sa_social_instagram_publish( $img_post, 'cid', 0 );
sa_ok( 'تصویرِ آماده‌نشده خطا می‌دهد', ! $res['ok'] );
sa_eq( 'یک رویدادِ تلاشِ دوباره زمان‌بندی شد', 1, count( sa_events( 'sa_social_instagram_publish_event' ) ) );
$event = sa_events( 'sa_social_instagram_publish_event' )[0];
sa_eq( 'آرگومان‌های تلاشِ دوباره', array( $img_post, 'cid', 1 ), $event['args'] );

sa_queue_response( array( 'username' => 'sarzaminaryan', 'media_count' => 12 ) );
$res = sa_social_instagram_test();
sa_ok( 'تست اینستاگرام موفق', $res['ok'] );
sa_has( 'نام کاربری در پیام تست', '@sarzaminaryan', $res['message'] );

echo "\n== شرط‌های ارسال و زمان‌بندی ==\n";

sa_reset_test_state();
update_option( 'sa_social_settings', array( 'telegram_enable' => 1, 'telegram_token' => '123456:ABC', 'telegram_chat' => '@ch', 'delay_minutes' => 3 ) );

$province = sa_add_post( array( 'post_title' => 'لرستان', 'post_type' => 'province', 'post_name' => 'lorestan' ) );
$draft    = sa_add_post( array( 'post_title' => 'پیش‌نویس', 'post_type' => 'province', 'post_name' => 'draft', 'post_status' => 'draft' ) );
$page     = sa_add_post( array( 'post_title' => 'برگه', 'post_type' => 'page', 'post_name' => 'page' ) );

sa_ok( 'نوشتهٔ منتشرشده ارسال می‌شود', sa_social_should_send( $province, 'telegram' ) );
sa_ok( 'پیش‌نویس ارسال نمی‌شود', ! sa_social_should_send( $draft, 'telegram' ) );
sa_ok( 'نوعِ غیرمجاز ارسال نمی‌شود', ! sa_social_should_send( $page, 'telegram' ) );
sa_ok( 'شبکهٔ غیرفعال ارسال نمی‌شود', ! sa_social_should_send( $province, 'instagram' ) );

update_post_meta( $province, '_sa_social_skip', '1' );
sa_ok( 'متای «عدم ارسال» جلوی ارسال را می‌گیرد', ! sa_social_should_send( $province, 'telegram' ) );
delete_post_meta( $province, '_sa_social_skip' );

sa_social_transition( 'publish', 'draft', get_post( $province ) );
$events = sa_events( 'sa_social_publish_event' );
sa_eq( 'انتشارِ نخست یک رویداد زمان‌بندی می‌کند', 1, count( $events ) );
sa_ok( 'زمانِ رویداد با تأخیر (۳ دقیقه) است', $events[0]['time'] >= time() + 170 );

sa_social_transition( 'publish', 'publish', get_post( $province ) );
sa_eq( 'تغییرِ publish→publish رویدادِ تکراری نمی‌سازد', 1, count( sa_events( 'sa_social_publish_event' ) ) );

sa_social_transition( 'publish', 'draft', get_post( $page ) );
sa_eq( 'نوعِ غیرمجاز رویداد نمی‌سازد', 1, count( sa_events( 'sa_social_publish_event' ) ) );

sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ) );
sa_social_send_post( $province );
sa_ok( 'متای ارسال ثبت شد', ! empty( get_post_meta( $province, '_sa_social_telegram_sent', true ) ) );
sa_ok( 'پس از ارسال، دوباره ارسال نمی‌شود', ! sa_social_should_send( $province, 'telegram' ) );

$logs = sa_social_logs();
sa_ok( 'گزارش ثبت شد', count( $logs ) >= 1 );
sa_eq( 'شبکه در گزارش', 'telegram', $logs[0]['network'] );
sa_eq( 'شناسهٔ مطلب در گزارش', $province, $logs[0]['post_id'] );

echo "\n== ارسالِ انبوهِ مطالبِ موجود ==\n";

sa_reset_test_state();
update_option( 'sa_social_settings', array( 'telegram_enable' => 1, 'telegram_token' => '123456:ABC', 'telegram_chat' => '@ch' ) );

$p1 = sa_add_post( array( 'post_title' => 'استان الف', 'post_type' => 'province', 'post_name' => 'p1' ) );
$p2 = sa_add_post( array( 'post_title' => 'استان ب', 'post_type' => 'province', 'post_name' => 'p2' ) );
$c1 = sa_add_post( array( 'post_title' => 'شهر الف', 'post_type' => 'city', 'post_name' => 'c1' ) );
$c2 = sa_add_post( array( 'post_title' => 'شهر ب', 'post_type' => 'city', 'post_name' => 'c2' ) );
$c3 = sa_add_post( array( 'post_title' => 'شهر پ', 'post_type' => 'city', 'post_name' => 'c3' ) );
$a1 = sa_add_post( array( 'post_title' => 'دیدنی الف', 'post_type' => 'attraction', 'post_name' => 'a1' ) );
$draft_city = sa_add_post( array( 'post_title' => 'شهر پیش‌نویس', 'post_type' => 'city', 'post_name' => 'cd', 'post_status' => 'draft' ) );

$state = sa_social_bulk_defaults();
sa_eq( 'وضعیتِ پیش‌فرض: total صفر', 0, $state['total'] );
sa_eq( 'وضعیتِ پیش‌فرض: شبکه', 'telegram', $state['network'] );

$count = sa_social_bulk_start( array( 'province', 'city' ), 'telegram', false, 0 );
sa_eq( 'تعدادِ مطالبِ در صف', 5, $count );
$state = sa_social_bulk_state();
sa_eq( 'ترتیب: استان‌ها پیش از شهرها', array( $p1, $p2, $c1, $c2, $c3 ), $state['ids'] );
sa_eq( 'شبکه در وضعیت', 'telegram', $state['network'] );
sa_eq( 'انواع در وضعیت', array( 'province', 'city' ), $state['types'] );
sa_ok( 'زمانِ شروع ثبت شد', $state['started'] > 0 );
sa_eq( 'یک رویدادِ انبوه زمان‌بندی شد', 1, count( sa_events( 'sa_social_bulk_event' ) ) );
sa_ok( 'پیش‌نویس در صف نیست', ! in_array( $draft_city, $state['ids'], true ) );
sa_ok( 'دیدنی در صف نیست (انتخاب نشده)', ! in_array( $a1, $state['ids'], true ) );

sa_social_bulk_stop();
sa_eq( 'توقف: گزینه پاک شد', array(), sa_social_bulk_defaults()['ids'] );
sa_eq( 'توقف: رویداد پاک شد', 0, count( sa_events( 'sa_social_bulk_event' ) ) );

$count = sa_social_bulk_start( array( 'province', 'city' ), 'telegram', false, 1 );
sa_eq( 'محدودیتِ هر نوع اعمال شد (۱+۱)', 2, $count );

sa_social_bulk_stop();
$count = sa_social_bulk_start( array( 'unknown_type' ), 'telegram', false, 0 );
sa_eq( 'نوعِ نامعتبر نادیده گرفته می‌شود', 0, $count );
sa_eq( 'نوعِ نامعتبر رویداد نمی‌سازد', 0, count( sa_events( 'sa_social_bulk_event' ) ) );

// مطلبِ قبلاً ارسال‌شده رد می‌شود، مگر با force
update_post_meta( $p1, '_sa_social_telegram_sent', array( 'ok' => true ) );
$count = sa_social_bulk_start( array( 'province' ), 'telegram', false, 0 );
sa_eq( 'ارسال‌شدهٔ قبلی در صف نیست', 1, $count );
$count = sa_social_bulk_start( array( 'province' ), 'telegram', true, 0 );
sa_eq( 'با force دوباره در صف می‌آید', 2, $count );
$state = sa_social_bulk_state();
sa_eq( 'force در وضعیت ثبت شد', 1, $state['force'] );

// اجرای دسته‌ای
for ( $i = 0; $i < 3; $i++ ) {
	sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => $i ) ) );
}
sa_run_event( 'sa_social_bulk_event' );
$state = sa_social_bulk_state();
sa_eq( 'دستهٔ اول: ۲ مطلب انجام شد (صف فقط ۲ تا بود)', 2, $state['done'] );
sa_eq( 'تعدادِ موفق', 2, $state['ok'] );
sa_eq( 'تعدادِ ناموفق', 0, $state['failed'] );
sa_eq( 'صف خالی شد', 0, count( $state['ids'] ) );
sa_ok( 'زمانِ پایان ثبت شد', $state['finished'] > 0 );
sa_eq( 'پس از پایان رویدادی زمان‌بندی نشد', 0, count( sa_events( 'sa_social_bulk_event' ) ) );
sa_ok( 'متای ارسال برای مطلبِ تکراری هم ثبت شد', ! empty( get_post_meta( $p1, '_sa_social_telegram_sent', true ) ) );

// صفِ بزرگ‌تر: ۵ مطلب، هر بار ۳ تا
sa_reset_test_state();
update_option( 'sa_social_settings', array( 'telegram_enable' => 1, 'telegram_token' => '123456:ABC', 'telegram_chat' => '@ch' ) );
$p1 = sa_add_post( array( 'post_title' => 'استان الف', 'post_type' => 'province', 'post_name' => 'p1' ) );
$p2 = sa_add_post( array( 'post_title' => 'استان ب', 'post_type' => 'province', 'post_name' => 'p2' ) );
$c1 = sa_add_post( array( 'post_title' => 'شهر الف', 'post_type' => 'city', 'post_name' => 'c1' ) );
$c2 = sa_add_post( array( 'post_title' => 'شهر ب', 'post_type' => 'city', 'post_name' => 'c2' ) );
$c3 = sa_add_post( array( 'post_title' => 'شهر پ', 'post_type' => 'city', 'post_name' => 'c3' ) );
sa_social_bulk_start( array( 'province', 'city' ), 'telegram', false, 0 );

for ( $i = 0; $i < 3; $i++ ) {
	sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => $i ) ) );
}
sa_run_event( 'sa_social_bulk_event' );
$state = sa_social_bulk_state();
sa_eq( 'دستهٔ اول: ۳ مطلب انجام شد', 3, $state['done'] );
sa_eq( '۲ مطلب در صف مانده', 2, count( $state['ids'] ) );
$events = sa_events( 'sa_social_bulk_event' );
sa_eq( 'ادامه زمان‌بندی شد', 1, count( $events ) );
sa_ok( 'وقفهٔ ۷۰ ثانیه رعایت شد', $events[0]['time'] >= time() + 65 );

for ( $i = 0; $i < 2; $i++ ) {
	sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => $i ) ) );
}
sa_run_event( 'sa_social_bulk_event' );
$state = sa_social_bulk_state();
sa_eq( 'دستهٔ دوم: همه انجام شد', 5, $state['done'] );
sa_eq( 'همه موفق', 5, $state['ok'] );
sa_eq( 'صف پایان یافت', 0, count( $state['ids'] ) );
sa_ok( 'زمانِ پایان ثبت شد', $state['finished'] > 0 );

// خطا در ارسال
sa_reset_test_state();
update_option( 'sa_social_settings', array( 'telegram_enable' => 1, 'telegram_token' => '123456:ABC', 'telegram_chat' => '@ch' ) );
$f1 = sa_add_post( array( 'post_title' => 'استان خطا', 'post_type' => 'province', 'post_name' => 'f1' ) );
sa_social_bulk_start( array( 'province' ), 'telegram', false, 0 );
sa_queue_response( array( 'ok' => false, 'description' => 'Forbidden: bot was blocked' ) );
sa_run_event( 'sa_social_bulk_event' );
$state = sa_social_bulk_state();
sa_eq( 'خطا شمارش شد', 1, $state['failed'] );
sa_eq( 'موفق صفر', 0, $state['ok'] );
sa_has( 'پیامِ خطا ذخیره شد', 'blocked', $state['last'] );
sa_ok( 'متای خطا ثبت شد', ! empty( get_post_meta( $f1, '_sa_social_telegram_error', true ) ) );

echo "\n== مدیریت ==\n";

$clean = sa_social_sanitize(
	array(
		'telegram_enable'   => '1',
		'telegram_token'    => ' 999999:ZZZ ',
		'telegram_chat'     => '@sarzaminaryan',
		'instagram_enable'  => 0,
		'post_types'        => array( 'province', 'city', 'nope' ),
		'delay_minutes'     => 500,
		'hashtag_limit'     => 100,
	)
);
sa_eq( 'پاک‌سازی: فعال بودن تلگرام', 1, $clean['telegram_enable'] );
sa_eq( 'پاک‌سازی: توکن', '999999:ZZZ', $clean['telegram_token'] );
sa_eq( 'پاک‌سازی: انواع معتبر', array( 'province', 'city' ), $clean['post_types'] );
sa_eq( 'پاک‌سازی: محدود شدنِ تأخیر', 120, $clean['delay_minutes'] );
sa_eq( 'پاک‌سازی: محدود شدنِ هشتگ', 30, $clean['hashtag_limit'] );
sa_has( 'نقاب‌دار کردن توکن', '•', sa_social_mask( '123456789:ABCDEFG' ) );
sa_eq( 'توکن خالی در نقاب', '—', sa_social_mask( '' ) );

update_option( 'sa_social_settings', $clean );
update_option( 'sa_social_bulk', sa_social_bulk_start( array( 'province' ), 'telegram', false, 0 ) ? sa_social_bulk_state() : array() );
$html = '';
try {
	ob_start();
	sa_social_settings_page();
	$html = (string) ob_get_clean();
	sa_ok( 'صفحهٔ شبکه‌های اجتماعی بدون خطا اجرا شد', true );
} catch ( Exception $e ) {
	ob_end_clean();
	sa_ok( 'صفحهٔ شبکه‌های اجتماعی بدون خطا اجرا شد', false, $e->getMessage() );
}
sa_has( 'صفحه عنوانِ انبوه را دارد', 'ارسالِ انبوهِ مطالبِ موجود', $html );
sa_has( 'صفحه فیلدِ نوع محتوا برای انبوه دارد', 'bulk_types[]', $html );
sa_has( 'صفحه انتخابِ شبکه برای انبوه دارد', 'bulk_network', $html );
sa_has( 'صفحه دکمهٔ توقف دارد', 'sa_social_bulk_stop', $html );
sa_lacks( 'توکن در صفحه چاپ نشده (فقط نقاب)', '999999:ZZZ', $html );
sa_has( 'قالبِ پیام در صفحه هست', 'sa_social_settings[telegram_template]', $html );

sa_done();
