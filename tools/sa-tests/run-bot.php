<?php
/**
 * تست‌های «ربات راهنمای تلگرام» (inc/telegram-bot.php).
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-bot.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

/**
 * شبیه‌سازِ inc/relations.php (مسیرِ یکپارچگیِ واقعی تست می‌شود).
 */
function sa_get_children( $parent_id, $child_type ) {
	$parent_type = get_post_type( $parent_id );
	$meta_key    = 'sa_' . $parent_type . '_id';
	$ids         = get_posts(
		array(
			'post_type'   => $child_type,
			'post_status' => 'publish',
			'fields'      => 'ids',
			'posts_per_page' => 500,
			'meta_query'  => array(
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

/**
 * شبیه‌سازِ sa_get_parent.
 */
function sa_get_parent( $post_id, $target ) {
	$id   = (int) get_post_meta( $post_id, 'sa_' . $target . '_id', true );
	$post = $id ? get_post( $id ) : null;
	return ( $post && 'publish' === $post->post_status ) ? $post : null;
}

require '/ws/theme/inc/social-publish.php';
require '/ws/theme/inc/telegram-bot.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

echo "== ثبتِ قلاب‌ها ==\n";

do_action( 'rest_api_init' );
$routes = array();
foreach ( $GLOBALS['sa_rest_routes'] as $route ) {
	$routes[] = $route['route'];
}
sa_ok( 'مسیرِ رستِ ربات ثبت شد', in_array( '/telegram-bot/(?P<secret>[A-Za-z0-9_-]{16,64})', $routes, true ), implode( ',', $routes ) );

do_action( 'admin_menu' );
$slugs = array();
foreach ( $GLOBALS['sa_menu_pages'] as $page ) {
	$slugs[] = $page['slug'];
}
sa_ok( 'زیرمنوی ربات ثبت شد', in_array( 'sa-telegram-bot', $slugs, true ), implode( ',', $slugs ) );
sa_ok( 'زیرمنوی شبکه‌های اجتماعی ثبت شد', in_array( 'sa-social-publish', $slugs, true ) );

echo "== تنظیمات و توکن ==\n";

sa_reset_test_state();
$defaults = sa_tgbot_defaults();
sa_ok( 'پیش‌فرض‌ها شامل کلید enable است', array_key_exists( 'enable', $defaults ) );
sa_eq( 'پیش‌فرضِ فعال‌سازی صفر است', 0, $defaults['enable'] );
sa_eq( 'پیش‌فرضِ طول خلاصه', 240, $defaults['summary_length'] );

update_option( 'sa_social_settings', array( 'telegram_token' => '111111:AAA-soc' ) );
update_option( 'sa_telegram_bot', array( 'token' => '' ) );
sa_eq( 'توکن از تنظیماتِ شبکه‌های اجتماعی برداشته می‌شود', '111111:AAA-soc', sa_tgbot_token() );

update_option( 'sa_telegram_bot', array( 'token' => '222222:BBB-bot' ) );
sa_eq( 'توکنِ اختصاصیِ ربات اولویت دارد', '222222:BBB-bot', sa_tgbot_token() );

$secret = sa_tgbot_secret();
sa_ok( 'رازِ وب‌هوک ساخته شد', 1 === preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $secret ), 'secret=' . $secret );
sa_eq( 'راز در گزینه ذخیره شده', $secret, sa_tgbot_settings()['secret'] );
sa_eq( 'راز پایدار است', $secret, sa_tgbot_secret() );
sa_has( 'نشانی وب‌هوک شامل مسیر رست است', 'wp-json/sa/v1/telegram-bot/' . $secret, sa_tgbot_webhook_url() );

sa_ok( 'ربات پیش از فعال‌سازی آماده نیست', ! sa_tgbot_ready() );
update_option( 'sa_telegram_bot', array_merge( sa_tgbot_settings(), array( 'enable' => 1 ) ) );
sa_ok( 'ربات پس از فعال‌سازی آماده است', sa_tgbot_ready() );

echo "\n== ارتباط با تلگرام ==\n";

update_option( 'sa_telegram_bot', array_merge( sa_tgbot_settings(), array( 'token' => '' ) ) );
update_option( 'sa_social_settings', array( 'telegram_token' => '' ) );
$res = sa_tgbot_api( 'sendMessage', array( 'chat_id' => 1 ) );
sa_ok( 'بدون توکن خطا برمی‌گردد', ! $res['ok'] );
sa_has( 'پیام خطای توکن', 'توکن', $res['message'] );
update_option( 'sa_telegram_bot', array_merge( sa_tgbot_settings(), array( 'token' => '222222:BBB-bot' ) ) );

sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 77 ) ) );
$res = sa_tgbot_send( 42, 'سلام' );
sa_ok( 'ارسال موفق است', $res['ok'] );
sa_eq( 'شناسهٔ پیام برگشت داده شد', 77, $res['data']['message_id'] );
$req = sa_last_request();
sa_has( 'نشانی درخواست شامل توکن و متد است', 'api.telegram.org/bot222222:BBB-bot/sendMessage', $req['url'] );
$body = json_decode( $req['args']['body'], true );
sa_eq( 'شناسهٔ گفتگو در payload', 42, $body['chat_id'] );
sa_eq( 'حالتِ تجزیه HTML است', 'HTML', $body['parse_mode'] );

sa_queue_response( array( 'ok' => false, 'description' => 'Bad Request: chat not found' ) );
$res = sa_tgbot_send( 42, 'سلام' );
sa_ok( 'خطای تلگرام شناسایی شد', ! $res['ok'] );
sa_has( 'متنِ خطای تلگرام منتقل شد', 'chat not found', $res['message'] );

sa_queue_response( '', 0, 'cURL error 7' );
$res = sa_tgbot_send( 42, 'سلام' );
sa_ok( 'خطای ارتباط شناسایی شد', ! $res['ok'] );
sa_has( 'متنِ خطای ارتباط', 'خطای ارتباط', $res['message'] );

sa_queue_response( array( 'ok' => true, 'result' => true ) );
sa_tgbot_answer_callback( 'cbq-1' );
sa_has( 'پاسخ به کلیک با متد درست', 'answerCallbackQuery', sa_last_request()['url'] );

echo "\n== متن، خلاصه و صفحه‌کلید ==\n";

sa_eq( 'کوتاه‌سازیِ متنِ کوتاه', 'سلام', sa_tgbot_trim( 'سلام', 50 ) );
$long = sa_tgbot_trim( str_repeat( 'الفب ', 40 ), 30 );
sa_ok( 'کوتاه‌سازیِ متنِ بلند', function_exists( 'mb_strlen' ) ? mb_strlen( $long, 'UTF-8' ) <= 30 : strlen( $long ) <= 40 );
sa_has( 'نشانهٔ برش', '…', $long );
sa_eq( 'پاک‌سازی برای HTML', '&lt;b&gt;&amp;', sa_tgbot_esc( '<b>&' ) );

$pid = sa_add_post(
	array(
		'post_title'   => 'آبشار شوی',
		'post_type'    => 'attraction',
		'post_content' => "<p>آبشارِ <strong>شوی</strong> یکی از بلندترین آبشارها است.</p>[gallery id=\"3\"]",
		'post_excerpt' => '',
		'post_name'    => 'shevi',
	)
);
$summary = sa_tgbot_summary( $pid );
sa_lacks( 'خلاصه بدون تگ است', '<', $summary );
sa_lacks( 'خلاصه بدون شورت‌کد است', '[gallery', $summary );
sa_has( 'خلاصه شامل متن اصلی است', 'آبشارِ شوی', $summary );
sa_ok( 'خلاصه از تنظیمِ طول پیروی می‌کند', function_exists( 'mb_strlen' ) ? mb_strlen( $summary, 'UTF-8' ) <= 240 : true );

$excerpt_post = sa_add_post(
	array(
		'post_title'   => 'دورود',
		'post_type'    => 'city',
		'post_content' => str_repeat( 'متنِ بدنه ', 200 ),
		'post_excerpt' => 'شهرِ دورود در استان لرستان است.',
		'post_name'    => 'dorud',
	)
);
sa_has( 'خلاصه از چکیده برداشته می‌شود', 'شهرِ دورود', sa_tgbot_summary( $excerpt_post ) );

$kb = sa_tgbot_keyboard( array( array( array( 'text' => 'a', 'callback_data' => 'a' ) ) ) );
sa_ok( 'ساختارِ صفحه‌کلید', isset( $kb['inline_keyboard'] ) && 1 === count( $kb['inline_keyboard'] ) );
$link = sa_tgbot_link_row( 'https://x.test/p/', 'رفتن' );
sa_eq( 'دکمهٔ لینک از نوع url است', 'https://x.test/p/', $link[0]['url'] );
sa_eq( 'برچسبِ دکمهٔ لینک', 'رفتن', $link[0]['text'] );

$paged = sa_tgbot_page( array( 1, 2, 3, 4, 5 ), 2, 2 );
sa_eq( 'صفحه‌بندی: آیتم‌ها', array( 3, 4 ), $paged['items'] );
sa_eq( 'صفحه‌بندی: شماره صفحه', 2, $paged['page'] );
sa_eq( 'صفحه‌بندی: تعداد صفحات', 3, $paged['pages'] );
$paged = sa_tgbot_page( array( 1, 2, 3, 4, 5 ), 99, 2 );
sa_eq( 'صفحه‌بندی: صفحهٔ بیش از اندازه به آخرین صفحه محدود می‌شود', 3, $paged['page'] );

echo "\n== داده‌های استان / شهر / دیدنی ==\n";

sa_reset_test_state();
update_option( 'sa_telegram_bot', array( 'enable' => 1, 'token' => '222222:BBB-bot', 'per_page' => 2 ) );

$lorestan = sa_add_post( array( 'post_title' => 'لرستان', 'post_type' => 'province', 'post_name' => 'lorestan', 'post_excerpt' => 'استانی در غرب ایران.' ) );
$esfahan  = sa_add_post( array( 'post_title' => 'اصفهان', 'post_type' => 'province', 'post_name' => 'esfahan' ) );
$yazd     = sa_add_post( array( 'post_title' => 'یزد', 'post_type' => 'province', 'post_name' => 'yazd' ) );

$dorud = sa_add_post(
	array(
		'post_title'   => 'دورود',
		'post_type'    => 'city',
		'post_name'    => 'dorud',
		'post_excerpt' => 'شهرِ دورود در استان لرستان است.',
		'meta'         => array( 'sa_province_id' => $lorestan ),
	)
);
$khoram = sa_add_post( array( 'post_title' => 'خرم‌آباد', 'post_type' => 'city', 'post_name' => 'khorramabad', 'meta' => array( 'sa_province_id' => $lorestan ) ) );
$kashan = sa_add_post( array( 'post_title' => 'کاشان', 'post_type' => 'city', 'post_name' => 'kashan', 'meta' => array( 'sa_province_id' => $esfahan ) ) );

$shevi = sa_add_post(
	array(
		'post_title'   => 'آبشار شوی',
		'post_type'    => 'attraction',
		'post_name'    => 'shevi',
		'post_excerpt' => 'آبشار شوی در بخشِ مرکزیِ دورود است.',
		'meta'         => array( 'sa_city_id' => $dorud, 'sa_province_id' => $lorestan ),
	)
);
$babahur = sa_add_post( array( 'post_title' => 'پارک جنگلی باباهر', 'post_type' => 'attraction', 'post_name' => 'babahur', 'meta' => array( 'sa_city_id' => $dorud ) ) );

sa_eq( 'تعداد استان‌ها', 3, count( sa_tgbot_provinces() ) );
sa_eq( 'شهرهای لرستان', 2, count( sa_tgbot_cities( $lorestan ) ) );
sa_eq( 'شهرهای اصفهان', 1, count( sa_tgbot_cities( $esfahan ) ) );
sa_eq( 'دیدنی‌های دورود', 2, count( sa_tgbot_attractions( $dorud ) ) );
sa_eq( 'دیدنی‌های کاشان', 0, count( sa_tgbot_attractions( $kashan ) ) );

$rows = sa_tgbot_province_rows( 1 );
sa_eq( 'صفحهٔ ۱ استان‌ها: دو سطر (آیتم‌ها + ناوبری)', 2, count( $rows ) );
sa_eq( 'صفحهٔ ۱: دو دکمهٔ استان', 2, count( $rows[0] ) );
sa_eq( 'دکمهٔ صفحهٔ بعد', 'lp:2', $rows[1][0]['callback_data'] );
$rows = sa_tgbot_province_rows( 2 );
sa_eq( 'صفحهٔ ۲ استان‌ها: یک دکمه + ناوبری', 2, count( $rows[0] ) === 1 ? 2 : count( $rows ) );
$found_prev = false;
foreach ( $rows as $row ) {
	foreach ( $row as $button ) {
		if ( isset( $button['callback_data'] ) && 'lp:1' === $button['callback_data'] ) {
			$found_prev = true;
		}
	}
}
sa_ok( 'صفحهٔ ۲ دکمهٔ قبلی دارد', $found_prev );

echo "\n== وب‌هوک ==\n";

update_option( 'sa_telegram_bot', array_merge( sa_tgbot_settings(), array( 'enable' => 0 ) ) );
$request = new WP_REST_Request( array( 'secret' => sa_tgbot_secret() ), '{}' );
$res     = sa_tgbot_webhook( $request );
sa_ok( 'وب‌هوک در حالت غیرفعال خطای ۵۰۳ می‌دهد', $res instanceof WP_Error && 503 === $res->get_error_data()['status'] );
update_option( 'sa_telegram_bot', array_merge( sa_tgbot_settings(), array( 'enable' => 1 ) ) );

$request = new WP_REST_Request( array( 'secret' => 'wrong-secret-value-0000' ), '{}' );
$res     = sa_tgbot_webhook( $request );
sa_ok( 'رازِ اشتباه → ۴۰۳', $res instanceof WP_Error && 403 === $res->get_error_data()['status'] );

$request = new WP_REST_Request( array( 'secret' => sa_tgbot_secret() ), 'not-json' );
$res     = sa_tgbot_webhook( $request );
sa_ok( 'بدنهٔ غیرِ json خطا نمی‌سازد', $res instanceof WP_REST_Response );

// /start
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ) );
$update  = array( 'message' => array( 'chat' => array( 'id' => 987 ), 'text' => '/start' ) );
$request = new WP_REST_Request( array( 'secret' => sa_tgbot_secret() ), json_encode( $update ) );
$res     = sa_tgbot_webhook( $request );
sa_ok( 'وب‌هوک /start پاسخ ۲۰۰ می‌دهد', $res instanceof WP_REST_Response && 200 === $res->status );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'متنِ خوش‌آمد فرستاده شد', 'سرزمین آریان', $sent['text'] );
sa_eq( 'سه دکمهٔ استان در صفحهٔ اول (per_page=2 → ۲ دکمه)', 2, count( $sent['reply_markup']['inline_keyboard'][0] ) );
$labels = array();
foreach ( $sent['reply_markup']['inline_keyboard'] as $row ) {
	foreach ( $row as $button ) {
		$labels[] = isset( $button['callback_data'] ) ? $button['callback_data'] : '';
	}
}
foreach ( array( $lorestan, $esfahan ) as $province_id ) {
	$hit = false;
	foreach ( $labels as $label ) {
		if ( 'P:' . $province_id === $label ) {
			$hit = true;
		}
	}
	sa_ok( 'دکمهٔ استان ' . $province_id . ' ساخته شد', $hit );
}
sa_has( 'دکمهٔ صفحهٔ بعد در پیام /start', 'lp:2', implode( ' ', $labels ) );

// کلیک روی استان
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 2 ) ) );
sa_tgbot_handle_callback( 987, 'P:' . $lorestan );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'پیامِ استان شامل عنوان است', 'لرستان', $sent['text'] );
sa_has( 'پیامِ استان شامل خلاصه است', 'استانی در غرب ایران', $sent['text'] );
$url_row = $sent['reply_markup']['inline_keyboard'][0];
sa_has( 'دکمهٔ لینک به صفحهٔ استان', 'province/lorestan', $url_row[0]['url'] );
$labels = array();
foreach ( $sent['reply_markup']['inline_keyboard'] as $row ) {
	foreach ( $row as $button ) {
		$labels[] = isset( $button['callback_data'] ) ? $button['callback_data'] : '';
	}
}
sa_has( 'دکمهٔ شهرِ دورود', 'C:' . $dorud, implode( ' ', $labels ) );
sa_has( 'دکمهٔ شهرِ خرم‌آباد', 'C:' . $khoram, implode( ' ', $labels ) );
sa_has( 'دکمهٔ بازگشت به استان‌ها', 'home', implode( ' ', $labels ) );

// کلیک روی شهر
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 3 ) ) );
sa_tgbot_handle_callback( 987, 'C:' . $dorud );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'پیامِ شهر شامل عنوان است', 'دورود', $sent['text'] );
sa_has( 'پیامِ شهر شامل خلاصه است', 'شهرِ دورود', $sent['text'] );
$labels = array();
foreach ( $sent['reply_markup']['inline_keyboard'] as $row ) {
	foreach ( $row as $button ) {
		$labels[] = isset( $button['callback_data'] ) ? $button['callback_data'] : '';
	}
}
sa_has( 'دکمهٔ دیدنیِ آبشار شوی', 'A:' . $shevi, implode( ' ', $labels ) );
sa_has( 'دکمهٔ دیدنیِ باباهر', 'A:' . $babahur, implode( ' ', $labels ) );
sa_has( 'دکمهٔ بازگشت به استان', 'P:' . $lorestan, implode( ' ', $labels ) );

// کلیک روی دیدنی
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 4 ) ) );
sa_tgbot_handle_callback( 987, 'A:' . $shevi );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'پیامِ دیدنی شامل عنوان است', 'آبشار شوی', $sent['text'] );
sa_has( 'پیامِ دیدنی شامل نامِ شهر است', 'دورود', $sent['text'] );
sa_has( 'پیامِ دیدنی شامل لینک مستقیم است', 'attraction/shevi', $sent['text'] );
$url_row = $sent['reply_markup']['inline_keyboard'][0];
sa_has( 'دکمهٔ لینک به صفحهٔ دیدنی', 'attraction/shevi', $url_row[0]['url'] );

// جستجوی آزاد
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 5 ) ) );
$update  = array( 'message' => array( 'chat' => array( 'id' => 987 ), 'text' => 'دورود' ) );
$request = new WP_REST_Request( array( 'secret' => sa_tgbot_secret() ), json_encode( $update ) );
sa_tgbot_webhook( $request );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'جستجو عنوان را نشان می‌دهد', 'دورود', $sent['text'] );
$labels = array();
foreach ( $sent['reply_markup']['inline_keyboard'] as $row ) {
	foreach ( $row as $button ) {
		$labels[] = isset( $button['callback_data'] ) ? $button['callback_data'] : '';
	}
}
sa_has( 'نتیجهٔ جستجو شامل شهرِ دورود است', 'C:' . $dorud, implode( ' ', $labels ) );

// فرمانِ ناشناس
sa_queue_response( array( 'ok' => true, 'result' => array( 'message_id' => 6 ) ) );
$update  = array( 'message' => array( 'chat' => array( 'id' => 987 ), 'text' => '/nonsense' ) );
$request = new WP_REST_Request( array( 'secret' => sa_tgbot_secret() ), json_encode( $update ) );
sa_tgbot_webhook( $request );
$sent = json_decode( sa_last_request()['args']['body'], true );
sa_has( 'فرمانِ ناشناس راهنما می‌فرستد', 'راهنما', $sent['text'] );

echo "\n== تنظیمِ وب‌هوک و گزارش ==\n";

sa_queue_response( array( 'ok' => true, 'result' => true ) );
$res = sa_tgbot_set_webhook();
sa_ok( 'تنظیم وب‌هوک موفق', $res['ok'] );
$req = sa_last_request();
sa_has( 'متد setWebhook', 'setWebhook', $req['url'] );
$body = json_decode( $req['args']['body'], true );
sa_eq( 'نشانی ثبت‌شده همان نشانیِ قالب است', sa_tgbot_webhook_url(), $body['url'] );
sa_eq( 'فیلترِ به‌روزرسانی‌ها', array( 'message', 'callback_query' ), $body['allowed_updates'] );

sa_queue_response( array( 'ok' => true, 'result' => true ) );
$res = sa_tgbot_delete_webhook();
sa_ok( 'حذف وب‌هوک موفق', $res['ok'] );
sa_has( 'متد deleteWebhook', 'deleteWebhook', sa_last_request()['url'] );

sa_queue_response( array( 'ok' => true, 'result' => array( 'url' => 'https://x.test/hook', 'pending_update_count' => 0 ) ) );
$info = sa_tgbot_webhook_info();
sa_ok( 'دریافتِ اطلاعات وب‌هوک', $info['ok'] && 'https://x.test/hook' === $info['data']['url'] );

$logs = sa_tgbot_logs();
sa_ok( 'گزارش ثبت شده است', count( $logs ) >= 2 );
$events = array();
foreach ( $logs as $log ) {
	$events[] = $log['event'];
}
sa_ok( 'رویدادِ setWebhook در گزارش است', in_array( 'setWebhook', $events, true ), implode( ',', $events ) );
sa_ok( 'رویدادِ پیام در گزارش است', in_array( 'message', $events, true ), implode( ',', $events ) );

echo "\n== مدیریت ==\n";

$clean = sa_tgbot_sanitize(
	array(
		'enable'         => '1',
		'token'          => '  333333:CCC-new  ',
		'username'       => '@ArianGuide_bot',
		'welcome'        => 'خوش آمدید',
		'help'           => 'راهنما',
		'summary_length' => 5000,
		'per_page'       => 100,
		'test_chat'      => ' 12345 ',
	)
);
sa_eq( 'پاک‌سازی: فعال‌سازی', 1, $clean['enable'] );
sa_eq( 'پاک‌سازی: توکن', '333333:CCC-new', $clean['token'] );
sa_eq( 'پاک‌سازی: نام کاربری بدون @', 'ArianGuide_bot', $clean['username'] );
sa_eq( 'پاک‌سازی: محدود شدنِ طول خلاصه', 900, $clean['summary_length'] );
sa_eq( 'پاک‌سازی: محدود شدنِ تعداد در صفحه', 20, $clean['per_page'] );
sa_eq( 'پاک‌سازی: شناسهٔ تست', '12345', $clean['test_chat'] );
sa_ok( 'پاک‌سازی: راز حفظ می‌شود', 1 === preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $clean['secret'] ) );
sa_ok( 'نقاب‌دار کردن توکن', false !== strpos( sa_tgbot_mask( '1234567890abcdefgh' ), '*' ) );
sa_eq( 'توکن خالی در نقاب', '—', sa_tgbot_mask( '' ) );

// رندرِ صفحهٔ مدیریت بدون هیچ هشدار/خطای PHP
update_option( 'sa_telegram_bot', $clean );
$kept = sa_tgbot_sanitize( array( 'enable' => 1, 'token' => '' ) );
sa_eq( 'خالی گذاشتنِ توکن، توکنِ قبلی را حفظ می‌کند', '333333:CCC-new', $kept['token'] );
sa_queue_response( array( 'ok' => true, 'result' => array( 'url' => sa_tgbot_webhook_url(), 'pending_update_count' => 2 ) ) );
$html = '';
try {
	ob_start();
	sa_tgbot_settings_page();
	$html = (string) ob_get_clean();
	sa_ok( 'صفحهٔ مدیریت ربات بدون خطا اجرا شد', true );
} catch ( Exception $e ) {
	ob_end_clean();
	sa_ok( 'صفحهٔ مدیریت ربات بدون خطا اجرا شد', false, $e->getMessage() );
}
sa_has( 'صفحه شامل عنوان است', 'ربات راهنمای تلگرام', $html );
sa_has( 'صفحه نشانی وب‌هوک را نشان می‌دهد', 'telegram-bot/' . sa_tgbot_secret(), $html );
sa_has( 'صفحه دکمهٔ تنظیم وب‌هوک دارد', 'sa_tgbot_webhook_set', $html );
sa_has( 'صفحه دکمهٔ ارسال تست دارد', 'sa_tgbot_test', $html );
sa_has( 'صفحه نام کاربری ربات را نشان می‌دهد', 't.me/ArianGuide_bot', $html );
sa_has( 'صفحه پیشوندِ نقاب‌دار توکن را نشان می‌دهد', '333333*', $html );
sa_lacks( 'توکنِ کامل در صفحه چاپ نشده', '333333:CCC-new', $html );
sa_has( 'گزارش در صفحه نمایش داده می‌شود', 'setWebhook', $html );

sa_done();
