<?php
/**
 * ربات راهنمای تلگرام — داخلیِ قالب، بدون افزونه.
 *
 * روندِ گفتگو:
 *   /start → فهرست استان‌ها → انتخاب استان (خلاصه + لینک + شهرها)
 *   → انتخاب شهر (خلاصه + لینک + نمای برترها) → انتخاب نمای برتر (خلاصه + لینک)
 *
 * وب‌هوک: POST /wp-json/sa/v1/telegram-bot/{secret}
 *
 * @package sarzaminaryan-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * تنظیمات
 * ---------------------------------------------------------------------- */

/**
 * پیش‌فرض‌های ربات.
 *
 * @return array<string,mixed>
 */
function sa_tgbot_defaults() {
	return array(
		'enable'         => 0,
		'token'          => '',
		'secret'         => '',
		'username'       => '',
		'welcome'        => "سلام 👋\nمن راهنمای «سرزمین آریان» هستم.\nیک استان را انتخاب کنید تا شهرها و دیدنی‌هایش را نشانتان بدهم.",
		'help'           => "راهنما:\n• /start — شروع و نمایش استان‌ها\n• /province — فهرست استان‌ها\n• یا نام یک استان، شهر یا دیدنی را بنویسید تا جستجو کنم.",
		'summary_length' => 240,
		'per_page'       => 8,
		'test_chat'      => '',
	);
}

/**
 * تنظیماتِ فعلی (ادغام‌شده با پیش‌فرض‌ها).
 *
 * @return array<string,mixed>
 */
function sa_tgbot_settings() {
	$stored = get_option( 'sa_telegram_bot', array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), sa_tgbot_defaults() );
}

/**
 * توکنِ ربات: اگر خالی باشد از توکنِ تلگرامِ بخشِ «شبکه‌های اجتماعی» استفاده می‌شود.
 *
 * @return string
 */
function sa_tgbot_token() {
	$settings = sa_tgbot_settings();
	$token    = trim( (string) $settings['token'] );
	if ( '' !== $token ) {
		return $token;
	}
	if ( function_exists( 'sa_social_settings' ) ) {
		$social = sa_social_settings();
		return trim( (string) $social['telegram_token'] );
	}
	return '';
}

/**
 * رشتهٔ محرمانهٔ وب‌هوک (در صورت نبود، ساخته و ذخیره می‌شود).
 *
 * @return string
 */
function sa_tgbot_secret() {
	$settings = sa_tgbot_settings();
	$secret   = trim( (string) $settings['secret'] );
	if ( preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $secret ) ) {
		return $secret;
	}
	$secret = wp_generate_password( 32, false, false );
	$secret = preg_replace( '/[^A-Za-z0-9_-]/', '', $secret );
	if ( strlen( $secret ) < 16 ) {
		$secret = substr( md5( $secret . wp_rand() . microtime() ), 0, 32 );
	}
	$settings['secret'] = $secret;
	update_option( 'sa_telegram_bot', $settings, false );
	return $secret;
}

/**
 * نشانیِ وب‌هوک.
 *
 * @return string
 */
function sa_tgbot_webhook_url() {
	return rest_url( 'sa/v1/telegram-bot/' . sa_tgbot_secret() );
}

/**
 * آیا ربات فعال و آماده است؟
 *
 * @return bool
 */
function sa_tgbot_ready() {
	$settings = sa_tgbot_settings();
	return ! empty( $settings['enable'] ) && '' !== sa_tgbot_token();
}

/* -------------------------------------------------------------------------
 * ارتباط با تلگرام
 * ---------------------------------------------------------------------- */

/**
 * فراخوانیِ یک متد از Bot API.
 *
 * @param string               $method نام متد.
 * @param array<string,mixed>  $args   پارامترها.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_api( $method, $args = array() ) {
	$token  = preg_replace( '/[^A-Za-z0-9_:-]/', '', (string) sa_tgbot_token() );
	$method = preg_replace( '/[^A-Za-z]/', '', (string) $method );
	if ( '' === $token || '' === $method ) {
		return array( 'ok' => false, 'data' => null, 'message' => 'توکن ربات تنظیم نشده است.' );
	}

	$url      = 'https://api.telegram.org/bot' . $token . '/' . $method;
	$response = wp_remote_post(
		$url,
		array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			'body'    => wp_json_encode( $args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'data' => null, 'message' => 'خطای ارتباط: ' . $response->get_error_message() );
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$code = (int) wp_remote_retrieve_response_code( $response );

	if ( is_array( $body ) && array_key_exists( 'ok', $body ) ) {
		if ( ! empty( $body['ok'] ) ) {
			return array( 'ok' => true, 'data' => $body['result'], 'message' => 'انجام شد.' );
		}
		$desc = isset( $body['description'] ) ? (string) $body['description'] : 'خطای ناشناخته';
		return array( 'ok' => false, 'data' => $body, 'message' => 'تلگرام: ' . $desc );
	}

	return array( 'ok' => false, 'data' => $body, 'message' => 'پاسخ نامعتبر از تلگرام (کد ' . $code . ').' );
}

/**
 * ارسال پیام.
 *
 * @param int|string          $chat_id      شناسهٔ گفتگو.
 * @param string              $text         متن (HTML مجاز: b, i, a, code).
 * @param array<mixed>|null   $reply_markup صفحه‌کلید.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send( $chat_id, $text, $reply_markup = null ) {
	$args = array(
		'chat_id'    => $chat_id,
		'text'       => $text,
		'parse_mode' => 'HTML',
	);
	if ( is_array( $reply_markup ) ) {
		$args['reply_markup'] = $reply_markup;
	}

	/**
	 * Filters the payload sent to Telegram by the guide bot.
	 *
	 * @param array      $args    Payload.
	 * @param int|string $chat_id Chat id.
	 */
	$args = (array) apply_filters( 'sa_tgbot_send_args', $args, $chat_id );

	return sa_tgbot_api( 'sendMessage', $args );
}

/**
 * پاسخ به کلیکِ دکمه‌های شیشه‌ای (برای این‌که ساعتِ لودینگ نماند).
 *
 * @param string $callback_id شناسهٔ کلیک.
 * @param string $text        پیام کوتاه.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_answer_callback( $callback_id, $text = '' ) {
	return sa_tgbot_api(
		'answerCallbackQuery',
		array(
			'callback_query_id' => (string) $callback_id,
			'text'              => (string) $text,
			'cache_time'        => 1,
		)
	);
}

/**
 * تنظیمِ وب‌هوک در تلگرام.
 *
 * @param string $url نشانی (خالی = نشانیٔ پیش‌فرضِ قالب).
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_set_webhook( $url = '' ) {
	$url = '' === $url ? sa_tgbot_webhook_url() : (string) $url;
	$res = sa_tgbot_api(
		'setWebhook',
		array(
			'url'             => $url,
			'allowed_updates' => array( 'message', 'callback_query' ),
			'drop_pending_updates' => false,
		)
	);

	sa_tgbot_log_add(
		array(
			'event'   => 'setWebhook',
			'ok'      => $res['ok'] ? 1 : 0,
			'message' => $res['message'],
		)
	);

	return $res;
}

/**
 * حذفِ وب‌هوک.
 *
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_delete_webhook() {
	return sa_tgbot_api( 'deleteWebhook', array( 'drop_pending_updates' => true ) );
}

/**
 * وضعیتِ وب‌هوک از نگاه تلگرام.
 *
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_webhook_info() {
	return sa_tgbot_api( 'getWebhookInfo' );
}

/* -------------------------------------------------------------------------
 * کمک‌کننده‌ها
 * ---------------------------------------------------------------------- */

/**
 * پاک‌سازیِ متن برای parse_mode=HTML.
 *
 * @param string $text متن خام.
 * @return string
 */
function sa_tgbot_esc( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * کوتاه‌کردنِ امنِ متن.
 *
 * @param string $text متن.
 * @param int    $max  بیشینهٔ طول.
 * @return string
 */
function sa_tgbot_trim( $text, $max ) {
	$text = (string) $text;
	$max  = max( 10, (int) $max );
	if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
		if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $max - 1, 'UTF-8' ) ) . '…';
	}
	if ( strlen( $text ) <= $max ) {
		return $text;
	}
	return rtrim( substr( $text, 0, $max - 1 ) ) . '…';
}

/**
 * خلاصهٔ کوتاهِ یک نوشته (بدون تگ و شورت‌کد).
 *
 * @param WP_Post|int $post نوشته.
 * @param int         $len  بیشینهٔ طول (۰ = پیش‌فرضِ تنظیمات).
 * @return string
 */
function sa_tgbot_summary( $post, $len = 0 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	if ( $len <= 0 ) {
		$len = (int) sa_tgbot_settings()['summary_length'];
	}
	$raw  = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	$text = function_exists( 'strip_shortcodes' ) ? strip_shortcodes( (string) $raw ) : (string) $raw;
	$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $text ) : strip_tags( $text );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	$text = str_replace( array( '&nbsp;', '&amp;', '&#8217;', '&#8220;', '&#8221;' ), array( ' ', '&', '’', '“', '”' ), $text );
	return sa_tgbot_trim( $text, $len );
}

/**
 * ساختِ صفحه‌کلید شیشه‌ای.
 *
 * @param array<int,array<int,array<string,string>>> $rows سطرها (هر سطر آرایه‌ای از دکمه‌ها).
 * @return array<string,array<int,array<int,array<string,string>>>>
 */
function sa_tgbot_keyboard( $rows ) {
	return array( 'inline_keyboard' => array_values( array_filter( (array) $rows ) ) );
}

/**
 * دکمهٔ لینکِ مستقیم به سایت.
 *
 * @param string $url   نشانی.
 * @param string $label برچسب.
 * @return array<int,array<string,string>>
 */
function sa_tgbot_link_row( $url, $label = '' ) {
	$label = '' === $label ? 'ادامهٔ مطلب در سایت' : $label;
	return array( array( 'text' => $label, 'url' => (string) $url ) );
}

/**
 * برشِ صفحه از یک فهرست.
 *
 * @param array<int,mixed> $items    همهٔ موارد.
 * @param int              $page     شمارهٔ صفحه (از ۱).
 * @param int              $per_page تعداد در صفحه.
 * @return array{items:array<int,mixed>,page:int,pages:int}
 */
function sa_tgbot_page( $items, $page, $per_page ) {
	$per_page = max( 1, (int) $per_page );
	$pages    = max( 1, (int) ceil( count( $items ) / $per_page ) );
	$page     = min( max( 1, (int) $page ), $pages );
	$slice    = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
	return array(
		'items' => $slice,
		'page'  => $page,
		'pages' => $pages,
	);
}

/**
 * ساختِ دادهٔ دکمه برای یک فرمان (بخش‌های خالی حذف می‌شود).
 *
 * @param string $action فرمان، مثلاً lp یا lc.
 * @param string $arg    شناسهٔ والد (در صورت نیاز).
 * @param int    $page   شمارهٔ صفحه.
 * @return string
 */
function sa_tgbot_callback_data( $action, $arg, $page ) {
	$parts = array( (string) $action );
	if ( '' !== (string) $arg ) {
		$parts[] = (string) $arg;
	}
	$parts[] = (string) max( 1, (int) $page );
	return implode( ':', $parts );
}

/**
 * ساختِ سطرهای دکمه‌های یک فهرست به همراه ناوبریِ صفحه‌ها.
 *
 * @param array<int,WP_Post> $items        نوشته‌ها.
 * @param string             $prefix       پیشوندِ callback، مثلاً P یا C یا A.
 * @param string             $list_action  نامِ فرمانِ تغییر صفحه، مثلاً lp یا lc.
 * @param string             $list_arg     شناسهٔ والد برای callbackِ صفحه‌بندی.
 * @param int                $page         صفحهٔ فعلی.
 * @param int                $pages        تعداد صفحه‌ها.
 * @param int                $per_row      تعداد دکمه در هر سطر.
 * @param bool               $home_button  افزودنِ دکمهٔ «استان‌ها».
 * @return array<int,array<int,array<string,string>>>
 */
function sa_tgbot_rows( $items, $prefix, $list_action, $list_arg, $page, $pages, $per_row = 2, $home_button = true ) {
	$rows = array();
	$row  = array();
	foreach ( $items as $item ) {
		$row[] = array(
			'text'          => sa_tgbot_trim( get_the_title( $item ), 26 ),
			'callback_data' => $prefix . ':' . (int) ( is_object( $item ) ? $item->ID : $item ),
		);
		if ( count( $row ) >= $per_row ) {
			$rows[] = $row;
			$row    = array();
		}
	}
	if ( $row ) {
		$rows[] = $row;
	}

	$nav = array();
	if ( $pages > 1 ) {
		if ( $page > 1 ) {
			$nav[] = array(
				'text'          => '◀︎ قبلی',
				'callback_data' => sa_tgbot_callback_data( $list_action, $list_arg, $page - 1 ),
			);
		}
		if ( $page < $pages ) {
			$nav[] = array(
				'text'          => 'بعدی ▶︎',
				'callback_data' => sa_tgbot_callback_data( $list_action, $list_arg, $page + 1 ),
			);
		}
	}
	if ( $home_button ) {
		$nav[] = array(
			'text'          => '🏠 استان‌ها',
			'callback_data' => 'home',
		);
	}
	if ( $nav ) {
		$rows[] = $nav;
	}

	return $rows;
}

/* -------------------------------------------------------------------------
 * داده‌ها
 * ---------------------------------------------------------------------- */

/**
 * همهٔ استان‌های منتشرشده.
 *
 * @return array<int,WP_Post>
 */
function sa_tgbot_provinces() {
	return (array) get_posts(
		array(
			'post_type'        => 'province',
			'post_status'      => 'publish',
			'posts_per_page'   => 200,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
}

/**
 * شهرهای یک استان.
 *
 * @param int $province_id شناسهٔ استان.
 * @return array<int,WP_Post>
 */
function sa_tgbot_cities( $province_id ) {
	if ( function_exists( 'sa_get_children' ) ) {
		$cities = (array) sa_get_children( (int) $province_id, 'city' );
		if ( $cities ) {
			return $cities;
		}
	}
	return (array) get_posts(
		array(
			'post_type'        => 'city',
			'post_status'      => 'publish',
			'posts_per_page'   => 500,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'sa_province_id',
					'value' => (int) $province_id,
				),
			),
		)
	);
}

/**
 * دیدنی‌های یک شهر.
 *
 * @param int $city_id شناسهٔ شهر.
 * @return array<int,WP_Post>
 */
function sa_tgbot_attractions( $city_id ) {
	if ( function_exists( 'sa_get_children' ) ) {
		$items = (array) sa_get_children( (int) $city_id, 'attraction' );
		if ( $items ) {
			return $items;
		}
	}
	return (array) get_posts(
		array(
			'post_type'        => 'attraction',
			'post_status'      => 'publish',
			'posts_per_page'   => 500,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => 'sa_city_id',
					'value' => (int) $city_id,
				),
			),
		)
	);
}

/**
 * جستجو در استان‌ها، شهرها و دیدنی‌ها.
 *
 * @param string $q     عبارت.
 * @param int    $limit بیشینهٔ نتیجه.
 * @return array<int,WP_Post>
 */
function sa_tgbot_search( $q, $limit = 6 ) {
	$q = trim( (string) $q );
	if ( '' === $q ) {
		return array();
	}
	return (array) get_posts(
		array(
			'post_type'        => array( 'province', 'city', 'attraction' ),
			'post_status'      => 'publish',
			's'                => $q,
			'posts_per_page'   => max( 1, (int) $limit ),
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		)
	);
}

/* -------------------------------------------------------------------------
 * پاسخ‌ها
 * ---------------------------------------------------------------------- */

/**
 * پیامِ خوش‌آمد + فهرست استان‌ها.
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_home( $chat_id ) {
	$settings = sa_tgbot_settings();
	$text     = sa_tgbot_esc( (string) $settings['welcome'] );
	return sa_tgbot_send( $chat_id, $text, sa_tgbot_keyboard( sa_tgbot_province_rows( 1 ) ) );
}

/**
 * سطرهای دکمه‌های استان‌ها.
 *
 * @param int $page شمارهٔ صفحه.
 * @return array<int,array<int,array<string,string>>>
 */
function sa_tgbot_province_rows( $page = 1 ) {
	$per_page = (int) sa_tgbot_settings()['per_page'];
	$paged    = sa_tgbot_page( sa_tgbot_provinces(), $page, $per_page );
	if ( empty( $paged['items'] ) ) {
		return array();
	}
	return sa_tgbot_rows( $paged['items'], 'P', 'lp', '', $paged['page'], $paged['pages'], 2, false );
}

/**
 * نمایش فهرست استان‌ها.
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @param int        $page    شمارهٔ صفحه.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_provinces( $chat_id, $page = 1 ) {
	$rows = sa_tgbot_province_rows( $page );
	if ( empty( $rows ) ) {
		return sa_tgbot_send( $chat_id, 'هنوز استانی در سایت منتشر نشده است.' );
	}
	return sa_tgbot_send( $chat_id, '🏔 یکی از استان‌ها را انتخاب کنید:', sa_tgbot_keyboard( $rows ) );
}

/**
 * نمایشِ یک استان: خلاصه + لینک + فهرستِ شهرها.
 *
 * @param int|string $chat_id      شناسهٔ گفتگو.
 * @param int        $province_id  شناسهٔ استان.
 * @param int        $page         صفحهٔ فهرستِ شهرها.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_province( $chat_id, $province_id, $page = 1 ) {
	$province = get_post( (int) $province_id );
	if ( ! $province || 'province' !== $province->post_type ) {
		return sa_tgbot_send( $chat_id, 'این استان در دسترس نیست.' );
	}

	$cities   = sa_tgbot_cities( (int) $province_id );
	$per_page = (int) sa_tgbot_settings()['per_page'];
	$paged    = sa_tgbot_page( $cities, $page, $per_page );

	$text  = '🏔 <b>' . sa_tgbot_esc( get_the_title( $province ) ) . '</b>' . "\n";
	$text .= sa_tgbot_esc( sa_tgbot_summary( $province ) ) . "\n\n";
	$text .= '🏙 <b>شهرها</b> (' . count( $cities ) . ' مورد):';

	$rows = array( sa_tgbot_link_row( get_permalink( $province ), 'مشاهدهٔ صفحهٔ استان در سایت' ) );
	foreach ( sa_tgbot_rows( $paged['items'], 'C', 'lc', (int) $province_id, $paged['page'], $paged['pages'], 2, true ) as $row ) {
		$rows[] = $row;
	}
	if ( empty( $cities ) ) {
		$text .= "\n" . 'برای این استان هنوز شهری ثبت نشده است.';
	}

	return sa_tgbot_send( $chat_id, $text, sa_tgbot_keyboard( $rows ) );
}

/**
 * نمایشِ یک شهر: خلاصه + لینک + فهرستِ دیدنی‌ها.
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @param int        $city_id شناسهٔ شهر.
 * @param int        $page    صفحهٔ فهرستِ دیدنی‌ها.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_city( $chat_id, $city_id, $page = 1 ) {
	$city = get_post( (int) $city_id );
	if ( ! $city || 'city' !== $city->post_type ) {
		return sa_tgbot_send( $chat_id, 'این شهر در دسترس نیست.' );
	}

	$items    = sa_tgbot_attractions( (int) $city_id );
	$per_page = (int) sa_tgbot_settings()['per_page'];
	$paged    = sa_tgbot_page( $items, $page, $per_page );

	$province = function_exists( 'sa_get_parent' ) ? sa_get_parent( (int) $city_id, 'province' ) : null;

	$text  = '🏙 <b>' . sa_tgbot_esc( get_the_title( $city ) ) . '</b>' . "\n";
	$text .= sa_tgbot_esc( sa_tgbot_summary( $city ) ) . "\n\n";
	$text .= '📍 <b>دیدنی‌ها</b> (' . count( $items ) . ' مورد):';

	$rows = array( sa_tgbot_link_row( get_permalink( $city ), 'مشاهدهٔ صفحهٔ شهر در سایت' ) );
	foreach ( sa_tgbot_rows( $paged['items'], 'A', 'la', (int) $city_id, $paged['page'], $paged['pages'], 1, true ) as $row ) {
		$rows[] = $row;
	}
	if ( $province ) {
		$rows[] = array(
			array(
				'text'          => '↩︎ شهرهای ' . sa_tgbot_trim( get_the_title( $province ), 20 ),
				'callback_data' => 'P:' . (int) $province->ID,
			),
		);
	}
	if ( empty( $items ) ) {
		$text .= "\n" . 'برای این شهر هنوز دیدنی‌ای ثبت نشده است.';
	}

	return sa_tgbot_send( $chat_id, $text, sa_tgbot_keyboard( $rows ) );
}

/**
 * نمایشِ یک نوشته (دیدنی/مقاله): خلاصه + لینک مستقیم.
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @param int        $post_id شناسهٔ نوشته.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_post_card( $chat_id, $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return sa_tgbot_send( $chat_id, 'این مطلب در دسترس نیست.' );
	}

	$city = function_exists( 'sa_get_parent' ) ? sa_get_parent( (int) $post_id, 'city' ) : null;

	$text  = '📍 <b>' . sa_tgbot_esc( get_the_title( $post ) ) . '</b>' . "\n";
	if ( $city ) {
		$text .= sa_tgbot_esc( get_the_title( $city ) ) . "\n";
	}
	$text .= "\n" . sa_tgbot_esc( sa_tgbot_summary( $post ) ) . "\n\n";
	$text .= '<a href="' . esc_url( get_permalink( $post ) ) . '">ادامهٔ مطلب در سرزمین آریان</a>';

	$rows = array( sa_tgbot_link_row( get_permalink( $post ), 'مشاهدهٔ کامل در سایت' ) );
	if ( $city ) {
		$rows[] = array(
			array(
				'text'          => '↩︎ دیگر دیدنی‌های ' . sa_tgbot_trim( get_the_title( $city ), 20 ),
				'callback_data' => 'C:' . (int) $city->ID,
			),
		);
	}
	$rows[] = array(
		array(
			'text'          => '🏠 استان‌ها',
			'callback_data' => 'home',
		),
	);

	return sa_tgbot_send( $chat_id, $text, sa_tgbot_keyboard( $rows ) );
}

/**
 * پاسخ به یک عبارتِ آزاد (جستجو).
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @param string     $q       عبارت.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_tgbot_send_search( $chat_id, $q ) {
	$found = sa_tgbot_search( $q );
	if ( empty( $found ) ) {
		return sa_tgbot_send(
			$chat_id,
			'چیزی با این عبارت پیدا نشد. استان مورد نظر را از فهرست انتخاب کنید:',
			sa_tgbot_keyboard( sa_tgbot_province_rows( 1 ) )
		);
	}

	$rows = array();
	foreach ( $found as $item ) {
		$prefix = 'attraction' === $item->post_type ? 'A' : ( 'city' === $item->post_type ? 'C' : 'P' );
		$rows[] = array(
			array(
				'text'          => sa_tgbot_trim( get_the_title( $item ), 40 ),
				'callback_data' => $prefix . ':' . (int) $item->ID,
			),
		);
	}
	$rows[] = array(
		array(
			'text'          => '🏠 استان‌ها',
			'callback_data' => 'home',
		),
	);

	return sa_tgbot_send( $chat_id, 'نتیجهٔ جستجو برای «' . sa_tgbot_esc( $q ) . '»:', sa_tgbot_keyboard( $rows ) );
}

/* -------------------------------------------------------------------------
 * دریافتِ به‌روزرسانی‌ها
 * ---------------------------------------------------------------------- */

/**
 * مسیرِ رستِ وب‌هوک.
 */
function sa_tgbot_rest_route() {
	register_rest_route(
		'sa/v1',
		'/telegram-bot/(?P<secret>[A-Za-z0-9_-]{16,64})',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'sa_tgbot_webhook',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'sa_tgbot_rest_route' );

/**
 * دریافتِ به‌روزرسانی از تلگرام.
 *
 * @param WP_REST_Request $request درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_tgbot_webhook( $request ) {
	$secret = (string) $request->get_param( 'secret' );
	if ( ! hash_equals( sa_tgbot_secret(), $secret ) ) {
		return new WP_Error( 'sa_tgbot_forbidden', 'Forbidden', array( 'status' => 403 ) );
	}
	if ( ! sa_tgbot_ready() ) {
		return new WP_Error( 'sa_tgbot_disabled', 'Bot is disabled', array( 'status' => 503 ) );
	}

	$update = $request->get_json_params();
	if ( ! is_array( $update ) ) {
		$update = json_decode( (string) $request->get_body(), true );
	}
	if ( ! is_array( $update ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 200 );
	}

	sa_tgbot_handle_update( $update );

	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * پردازشِ یک به‌روزرسانی.
 *
 * @param array<string,mixed> $update آرایهٔ به‌روزرسانی تلگرام.
 */
function sa_tgbot_handle_update( $update ) {
	$update = (array) $update;

	if ( isset( $update['callback_query'] ) && is_array( $update['callback_query'] ) ) {
		$cb      = (array) $update['callback_query'];
		$cb_id   = isset( $cb['id'] ) ? (string) $cb['id'] : '';
		$data    = isset( $cb['data'] ) ? (string) $cb['data'] : '';
		$message = isset( $cb['message'] ) ? (array) $cb['message'] : array();
		$chat    = isset( $message['chat'] ) ? (array) $message['chat'] : array();
		$chat_id = isset( $chat['id'] ) ? $chat['id'] : 0;

		if ( $cb_id ) {
			sa_tgbot_answer_callback( $cb_id, '' );
		}
		if ( $chat_id ) {
			sa_tgbot_handle_callback( $chat_id, $data );
		}
		sa_tgbot_log_add(
			array(
				'event'   => 'callback',
				'ok'      => 1,
				'message' => 'chat ' . $chat_id . ' → ' . $data,
			)
		);
		return;
	}

	$message = isset( $update['message'] ) ? (array) $update['message'] : array();
	$chat    = isset( $message['chat'] ) ? (array) $message['chat'] : array();
	$chat_id = isset( $chat['id'] ) ? $chat['id'] : 0;
	$text    = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
	if ( ! $chat_id ) {
		return;
	}

	$settings = sa_tgbot_settings();

	if ( '' === $text ) {
		sa_tgbot_send( $chat_id, 'فقط پیام متنی را می‌فهمم. /start را بفرستید.' );
		return;
	}
	if ( 0 === strpos( $text, '/start' ) ) {
		sa_tgbot_send_home( $chat_id );
		return;
	}
	if ( '/help' === $text || '/help' === strtolower( $text ) || 'راهنما' === $text ) {
		sa_tgbot_send( $chat_id, sa_tgbot_esc( (string) $settings['help'] ) );
		return;
	}
	if ( '/province' === strtolower( $text ) || '/provinces' === strtolower( $text ) || 'استان‌ها' === $text ) {
		sa_tgbot_send_provinces( $chat_id );
		return;
	}
	if ( 0 === strpos( $text, '/' ) ) {
		sa_tgbot_send( $chat_id, sa_tgbot_esc( (string) $settings['help'] ) );
		return;
	}

	sa_tgbot_send_search( $chat_id, $text );

	sa_tgbot_log_add(
		array(
			'event'   => 'message',
			'ok'      => 1,
			'message' => 'chat ' . $chat_id . ': ' . sa_tgbot_trim( $text, 60 ),
		)
	);
}

/**
 * پردازشِ کلیک روی دکمه‌ها.
 *
 * @param int|string $chat_id شناسهٔ گفتگو.
 * @param string     $data    دادهٔ دکمه.
 */
function sa_tgbot_handle_callback( $chat_id, $data ) {
	$parts  = explode( ':', (string) $data );
	$action = isset( $parts[0] ) ? (string) $parts[0] : '';
	$arg1   = isset( $parts[1] ) ? (int) $parts[1] : 0;
	$arg2   = isset( $parts[2] ) ? (int) $parts[2] : 0;

	switch ( $action ) {
		case 'home':
			sa_tgbot_send_home( $chat_id );
			return;
		case 'lp': // فهرست استان‌ها، صفحه.
			sa_tgbot_send_provinces( $chat_id, max( 1, $arg1 ) );
			return;
		case 'lc': // فهرست شهرهای یک استان، صفحه.
			sa_tgbot_send_province( $chat_id, max( 1, $arg1 ), max( 1, $arg2 ) );
			return;
		case 'la': // فهرست دیدنی‌های یک شهر، صفحه.
			sa_tgbot_send_city( $chat_id, max( 1, $arg1 ), max( 1, $arg2 ) );
			return;
		case 'P':
			sa_tgbot_send_province( $chat_id, $arg1 );
			return;
		case 'C':
			sa_tgbot_send_city( $chat_id, $arg1 );
			return;
		case 'A':
			sa_tgbot_send_post_card( $chat_id, $arg1 );
			return;
		default:
			sa_tgbot_send_provinces( $chat_id );
			return;
	}
}

/* -------------------------------------------------------------------------
 * گزارش
 * ---------------------------------------------------------------------- */

/**
 * افزودن یک ردیف به گزارشِ ربات.
 *
 * @param array<string,mixed> $entry ردیف.
 */
function sa_tgbot_log_add( $entry ) {
	$logs = sa_tgbot_logs();
	array_unshift(
		$logs,
		array(
			'time'    => time(),
			'event'   => isset( $entry['event'] ) ? (string) $entry['event'] : '',
			'ok'      => ! empty( $entry['ok'] ) ? 1 : 0,
			'message' => isset( $entry['message'] ) ? sa_tgbot_trim( (string) $entry['message'], 200 ) : '',
		)
	);
	update_option( 'sa_telegram_bot_log', array_slice( $logs, 0, 15 ), false );
}

/**
 * گزارش‌های اخیر.
 *
 * @return array<int,array<string,mixed>>
 */
function sa_tgbot_logs() {
	$logs = get_option( 'sa_telegram_bot_log', array() );
	return is_array( $logs ) ? $logs : array();
}

/* -------------------------------------------------------------------------
 * مدیریت
 * ---------------------------------------------------------------------- */

/**
 * زیرمنوی «ربات تلگرام».
 */
function sa_tgbot_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'ربات تلگرام',
		'ربات تلگرام',
		'manage_options',
		'sa-telegram-bot',
		'sa_tgbot_settings_page'
	);
}
add_action( 'admin_menu', 'sa_tgbot_menu', 22 );

/**
 * ثبتِ تنظیمات.
 */
function sa_tgbot_register_settings() {
	register_setting(
		'sa_telegram_bot_group',
		'sa_telegram_bot',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sa_tgbot_sanitize',
			'default'           => sa_tgbot_defaults(),
		)
	);
}
add_action( 'admin_init', 'sa_tgbot_register_settings' );

/**
 * پاک‌سازیِ ورودی‌های تنظیمات.
 *
 * @param mixed $input ورودی خام.
 * @return array<string,mixed>
 */
function sa_tgbot_sanitize( $input ) {
	$old    = sa_tgbot_settings();
	$input  = is_array( $input ) ? $input : array();
	$clean  = $old;

	$clean['enable']         = empty( $input['enable'] ) ? 0 : 1;
	$token                   = isset( $input['token'] ) ? trim( sanitize_text_field( wp_unslash( (string) $input['token'] ) ) ) : '';
	$clean['token']          = '' === $token ? $old['token'] : $token;
	$clean['username']       = isset( $input['username'] ) ? ltrim( trim( sanitize_text_field( wp_unslash( (string) $input['username'] ) ) ), '@' ) : $old['username'];
	$clean['welcome']        = isset( $input['welcome'] ) ? sanitize_textarea_field( wp_unslash( (string) $input['welcome'] ) ) : $old['welcome'];
	$clean['help']           = isset( $input['help'] ) ? sanitize_textarea_field( wp_unslash( (string) $input['help'] ) ) : $old['help'];
	$clean['summary_length'] = isset( $input['summary_length'] ) ? max( 60, min( 900, (int) $input['summary_length'] ) ) : $old['summary_length'];
	$clean['per_page']       = isset( $input['per_page'] ) ? max( 3, min( 20, (int) $input['per_page'] ) ) : $old['per_page'];
	$clean['test_chat']      = isset( $input['test_chat'] ) ? trim( sanitize_text_field( wp_unslash( (string) $input['test_chat'] ) ) ) : $old['test_chat'];
	$clean['secret']         = sa_tgbot_secret();

	return $clean;
}

/**
 * تنظیمِ وب‌هوک از پیشخوان.
 */
function sa_tgbot_webhook_set_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_tgbot_webhook' );

	$res = sa_tgbot_set_webhook();
	set_transient(
		'sa_tgbot_notice',
		array(
			'ok'      => $res['ok'],
			'message' => $res['ok'] ? 'وب‌هوک با موفقیت تنظیم شد.' : $res['message'],
		),
		120
	);

	wp_safe_redirect( admin_url( 'admin.php?page=sa-telegram-bot' ) );
	exit;
}
add_action( 'admin_post_sa_tgbot_webhook_set', 'sa_tgbot_webhook_set_action' );

/**
 * حذفِ وب‌هوک از پیشخوان.
 */
function sa_tgbot_webhook_remove_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_tgbot_webhook' );

	$res = sa_tgbot_delete_webhook();
	set_transient(
		'sa_tgbot_notice',
		array(
			'ok'      => $res['ok'],
			'message' => $res['ok'] ? 'وب‌هوک حذف شد (ربات دیگر پیامی دریافت نمی‌کند).' : $res['message'],
		),
		120
	);

	wp_safe_redirect( admin_url( 'admin.php?page=sa-telegram-bot' ) );
	exit;
}
add_action( 'admin_post_sa_tgbot_webhook_remove', 'sa_tgbot_webhook_remove_action' );

/**
 * ارسالِ پیامِ تست.
 */
function sa_tgbot_test_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_tgbot_test' );

	$settings = sa_tgbot_settings();
	$chat     = trim( (string) $settings['test_chat'] );
	if ( '' === $chat ) {
		set_transient( 'sa_tgbot_notice', array( 'ok' => false, 'message' => 'شناسهٔ گفتگو برای تست را وارد کنید.' ), 120 );
		wp_safe_redirect( admin_url( 'admin.php?page=sa-telegram-bot' ) );
		exit;
	}

	$res = sa_tgbot_send_home( $chat );
	set_transient(
		'sa_tgbot_notice',
		array(
			'ok'      => $res['ok'],
			'message' => $res['ok'] ? 'پیام تست ارسال شد.' : $res['message'],
		),
		120
	);

	wp_safe_redirect( admin_url( 'admin.php?page=sa-telegram-bot' ) );
	exit;
}
add_action( 'admin_post_sa_tgbot_test', 'sa_tgbot_test_action' );

/**
 * نقاب‌دار کردنِ توکن برای نمایش.
 *
 * @param string $token توکن.
 * @return string
 */
function sa_tgbot_mask( $token ) {
	$token = (string) $token;
	if ( '' === $token ) {
		return '—';
	}
	$len = strlen( $token );
	if ( $len <= 8 ) {
		return str_repeat( '*', $len );
	}
	return substr( $token, 0, 6 ) . str_repeat( '*', max( 3, $len - 10 ) ) . substr( $token, -4 );
}

/**
 * صفحهٔ تنظیماتِ ربات.
 */
function sa_tgbot_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}

	$settings = sa_tgbot_settings();
	$logs     = sa_tgbot_logs();
	$notice   = get_transient( 'sa_tgbot_notice' );
	delete_transient( 'sa_tgbot_notice' );

	$webhook_url = sa_tgbot_webhook_url();
	$info        = sa_tgbot_ready() ? sa_tgbot_webhook_info() : array( 'ok' => false, 'message' => 'ربات غیرفعال است یا توکن تنظیم نشده.' );
	$pending     = ( $info['ok'] && is_array( $info['data'] ) && ! empty( $info['data']['pending_update_count'] ) ) ? (int) $info['data']['pending_update_count'] : 0;
	$current     = ( $info['ok'] && is_array( $info['data'] ) && isset( $info['data']['url'] ) ) ? (string) $info['data']['url'] : '';
	$bot_link    = '' !== $settings['username'] ? 'https://t.me/' . $settings['username'] : '';
	?>
	<div class="wrap sa-tgbot-wrap" dir="rtl">
		<h1>ربات راهنمای تلگرام</h1>
		<p class="description">
			رباتِ داخلیِ قالب: کاربر استان را انتخاب می‌کند، سپس شهر و در پایان دیدنی مورد نظر؛
			ربات خلاصهٔ کوتاهِ مطلب را به‌همراه لینک مستقیمِ صفحه می‌فرستد.
			راهنمای گام‌به‌گام در <code>docs/telegram-guide-bot.md</code> است.
		</p>

		<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
			<div class="notice notice-<?php echo empty( $notice['ok'] ) ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'sa_telegram_bot_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">فعال‌سازی</th>
					<td>
						<label><input type="checkbox" name="sa_telegram_bot[enable]" value="1" <?php checked( 1, (int) $settings['enable'] ); ?> /> ربات فعال باشد و به پیام‌ها پاسخ بدهد</label>
						<p class="description">تا زمانی که توکن وارد نشود، ربات پاسخ نمی‌دهد.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-token">توکن ربات</label></th>
					<td>
						<input type="password" class="regular-text" dir="ltr" id="sa-tgbot-token" name="sa_telegram_bot[token]" value="" autocomplete="new-password" placeholder="فقط برای تغییر پر کنید" />
						<p class="description">
							ذخیره‌شده: <code><?php echo esc_html( sa_tgbot_mask( (string) $settings['token'] ) ); ?></code>
							— توکن در صفحه چاپ نمی‌شود؛ برای تغییر ندادن، خالی بگذارید.
							اگر خالی بماند، از توکن تلگرامِ صفحهٔ «شبکه‌های اجتماعی» استفاده می‌شود
							(<code><?php echo esc_html( function_exists( 'sa_social_settings' ) ? sa_social_mask( sa_social_settings()['telegram_token'] ) : '—' ); ?></code>).
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-username">نام کاربری ربات</label></th>
					<td>
						<input type="text" class="regular-text" dir="ltr" id="sa-tgbot-username" name="sa_telegram_bot[username]" value="<?php echo esc_attr( (string) $settings['username'] ); ?>" placeholder="sarzaminaryan_bot" />
						<?php if ( $bot_link ) : ?>
							<p class="description"><a href="<?php echo esc_url( $bot_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $bot_link ); ?></a></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-welcome">پیام خوش‌آمد</label></th>
					<td>
						<textarea id="sa-tgbot-welcome" name="sa_telegram_bot[welcome]" rows="4" class="large-text" dir="rtl"><?php echo esc_textarea( (string) $settings['welcome'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-help">پیام راهنما</label></th>
					<td>
						<textarea id="sa-tgbot-help" name="sa_telegram_bot[help]" rows="4" class="large-text" dir="rtl"><?php echo esc_textarea( (string) $settings['help'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-summary">طول خلاصه (نویسه)</label></th>
					<td>
						<input type="number" min="60" max="900" id="sa-tgbot-summary" name="sa_telegram_bot[summary_length]" value="<?php echo esc_attr( (string) $settings['summary_length'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-perpage">تعداد در هر صفحه</label></th>
					<td>
						<input type="number" min="3" max="20" id="sa-tgbot-perpage" name="sa_telegram_bot[per_page]" value="<?php echo esc_attr( (string) $settings['per_page'] ); ?>" />
						<p class="description">تعداد استان/شهر/دیدنی در هر صفحهٔ دکمه‌ها.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-tgbot-test-chat">شناسهٔ گفتگو برای تست</label></th>
					<td>
						<input type="text" class="regular-text" dir="ltr" id="sa-tgbot-test-chat" name="sa_telegram_bot[test_chat]" value="<?php echo esc_attr( (string) $settings['test_chat'] ); ?>" placeholder="@channel یا 123456789" />
						<p class="description">شناسهٔ عددیِ خودتان را با فرستادنِ پیام به ربات و باز کردنِ <code>getUpdates</code> یا از ربات‌هایی مانند @userinfobot بگیرید.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'ذخیرهٔ تنظیمات' ); ?>
		</form>

		<h2>وب‌هوک (دریافت پیام‌ها)</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">نشانی وب‌هوک</th>
				<td>
					<code dir="ltr" style="display:inline-block;max-width:100%;word-break:break-all;"><?php echo esc_html( $webhook_url ); ?></code>
					<p class="description">حتماً باید با <code>https://</code> باشد؛ تلگرام نشانی http را نمی‌پذیرد.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">وضعیت</th>
				<td>
					<?php if ( $info['ok'] && is_array( $info['data'] ) ) : ?>
						<p>
							نشانیِ ثبت‌شده در تلگرام:
							<code dir="ltr"><?php echo '' === $current ? '— (تنظیم نشده)' : esc_html( $current ); ?></code>
						</p>
						<p>پیام‌های در انتظار: <strong><?php echo esc_html( (string) $pending ); ?></strong>
							<?php if ( ! empty( $info['data']['last_error_message'] ) ) : ?>
								<br /><span style="color:#b32d2e;">آخرین خطا: <?php echo esc_html( (string) $info['data']['last_error_message'] ); ?></span>
							<?php endif; ?>
						</p>
					<?php else : ?>
						<p style="color:#b32d2e;"><?php echo esc_html( (string) $info['message'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">عملیات</th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="sa_tgbot_webhook_set" />
						<?php wp_nonce_field( 'sa_tgbot_webhook' ); ?>
						<?php submit_button( 'تنظیم وب‌هوک', 'primary', 'submit', false ); ?>
					</form>
					&nbsp;
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="sa_tgbot_webhook_remove" />
						<?php wp_nonce_field( 'sa_tgbot_webhook' ); ?>
						<?php submit_button( 'حذف وب‌هوک', 'secondary', 'submit', false ); ?>
					</form>
					&nbsp;
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="sa_tgbot_test" />
						<?php wp_nonce_field( 'sa_tgbot_test' ); ?>
						<?php submit_button( 'ارسال پیام تست', 'secondary', 'submit', false ); ?>
					</form>
					<p class="description">پس از هر بار تغییرِ نشانیِ سایت یا جابه‌جایی، دوباره «تنظیم وب‌هوک» را بزنید.</p>
				</td>
			</tr>
		</table>

		<h2>گزارشِ آخرین درخواست‌ها</h2>
		<?php if ( empty( $logs ) ) : ?>
			<p class="description">هنوز درخواستی ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:900px;">
				<thead>
					<tr>
						<th>زمان</th>
						<th>رویداد</th>
						<th>وضعیت</th>
						<th>توضیح</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td dir="ltr"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $log['time'] ) ); ?></td>
							<td><?php echo esc_html( (string) $log['event'] ); ?></td>
							<td><?php echo empty( $log['ok'] ) ? 'ناموفق' : 'موفق'; ?></td>
							<td dir="ltr" style="text-align:right;"><?php echo esc_html( (string) $log['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
