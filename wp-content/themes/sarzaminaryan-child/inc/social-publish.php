<?php
/**
 * انتشار خودکار مطالب در تلگرام و اینستاگرام — داخل قالب، بدون افزونه.
 *
 * Admin path: سرزمین آریان → شبکه‌های اجتماعی
 *
 * Flow
 *  1. یک مطلب از هر نوعِ مجاز برای نخستین‌بار منتشر می‌شود →
 *  2. یک رویداد زمان‌بندی‌شدهٔ تک‌باره (wp_schedule_single_event) ثبت می‌شود →
 *  3. همان رویداد متن/تصویر را می‌سازد و برای تلگرام (Bot API) و اینستاگرام
 *     (Instagram Graph API) می‌فرستد →
 *  4. نتیجه در متای نوشته، در جعبهٔ «شبکه‌های اجتماعی» و در گزارش صفحهٔ تنظیمات
 *     ثبت می‌شود.
 *
 * Safety
 *  - هیچ چیزی هنگام انتشارِ خودکارِ وردپرس (autosave/revision) ارسال نمی‌شود؛
 *  - هر نوشته فقط یک‌بار ارسال می‌شود (متای نتیجه + قفل ۶۰ ثانیه‌ای)؛
 *  - ارسالِ دستی و «عدم ارسالِ این مطلب» در جعبهٔ کنار ویرایشگر قابل کنترل است؛
 *  - کلیدها/توکن‌ها فقط با توانایی manage_options ذخیره می‌شوند و در خروجی HTML
 *     هیچ‌وقت کامل چاپ نمی‌شوند.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * تنظیمات
 * ---------------------------------------------------------------------- */

/**
 * مقادیر پیش‌فرضِ تنظیمات.
 *
 * @return array<string,mixed>
 */
function sa_social_defaults() {
	return array(
		'telegram_enable'   => 0,
		'telegram_token'    => '',
		'telegram_chat'     => '',
		'telegram_image'    => 1,
		'telegram_template' => "<b>{title}</b>\n{excerpt}\n\n{tags}\n\n<a href=\"{link}\">ادامه مطلب در سرزمین آریان</a>",
		'instagram_enable'  => 0,
		'instagram_token'   => '',
		'instagram_user'    => '',
		'instagram_template' => "{title}\n\n{excerpt}\n\n{tags}",
		'post_types'        => array( 'post', 'attraction' ),
		'delay_minutes'     => 2,
		'hashtags'          => 1,
		'hashtag_limit'     => 12,
	);
}

/**
 * تنظیماتِ فعلی (ادغام‌شده با پیش‌فرض‌ها).
 *
 * @return array<string,mixed>
 */
function sa_social_settings() {
	$stored = get_option( 'sa_social_settings', array() );
	return wp_parse_args( is_array( $stored ) ? $stored : array(), sa_social_defaults() );
}

/**
 * انواع نوشته‌ای که می‌توانند ارسال شوند.
 *
 * @return array<int,string>
 */
function sa_social_post_types() {
	$types = array( 'post', 'attraction', 'city', 'province', 'travel_route', 'local_food', 'souvenir' );
	/**
	 * Filters the post types eligible for social auto-publishing.
	 *
	 * @param array $types Post type slugs.
	 */
	return (array) apply_filters( 'sa_social_post_types', $types );
}

/**
 * حداکثر طولِ مجازِ متن برای هر شبکه.
 *
 * @param string $network telegram|instagram
 * @param bool   $caption آیا متن به‌صورت توضیحِ تصویر فرستاده می‌شود؟
 * @return int
 */
function sa_social_max_length( $network, $caption = false ) {
	if ( 'instagram' === $network ) {
		return 2200;
	}
	return $caption ? 1000 : 3900;
}

/* -------------------------------------------------------------------------
 * کمک‌کننده‌ها
 * ---------------------------------------------------------------------- */

/**
 * نشانی‌های تصویرهای داخلِ بدنهٔ نوشته (به ترتیبِ ظاهر شدن).
 *
 * @param int $post_id Post ID.
 * @return array<int,string>
 */
function sa_social_content_images( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$html = (string) apply_filters( 'the_content', $post->post_content );
	if ( '' === trim( $html ) ) {
		$html = (string) $post->post_content;
	}
	$urls = array();
	if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m ) ) {
		foreach ( $m[1] as $src ) {
			$src = html_entity_decode( (string) $src, ENT_QUOTES, 'UTF-8' );
			if ( 0 !== strpos( $src, 'http' ) ) {
				continue;
			}
			if ( false !== strpos( $src, ' ' ) ) {
				continue;
			}
			$urls[] = esc_url_raw( $src );
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * انتخابِ تصویر مناسب برای یک شبکه.
 *
 * اینستاگرام فقط JPEG می‌پذیرد؛ تلگرام هر تصویر عمومی را قبول می‌کند.
 *
 * @param int    $post_id Post ID.
 * @param string $network telegram|instagram
 * @return string
 */
function sa_social_image_url( $post_id, $network = 'telegram' ) {
	$images = sa_social_content_images( $post_id );

	// تصویر شاخص همیشه در اولویت است (اگر وجود داشته باشد).
	$thumb = get_the_post_thumbnail_url( $post_id, 'full' );
	if ( $thumb ) {
		array_unshift( $images, $thumb );
	}

	$url = '';
	if ( 'instagram' === $network ) {
		foreach ( $images as $candidate ) {
			$path = (string) wp_parse_url( $candidate, PHP_URL_PATH );
			if ( preg_match( '/\.(jpe?g)$/i', $path ) ) {
				$url = $candidate;
				break;
			}
		}
	} elseif ( ! empty( $images ) ) {
		$url = $images[0];
	}

	/**
	 * Filters the image URL used for social publishing.
	 *
	 * @param string $url     Image URL (empty when unusable).
	 * @param int    $post_id Post ID.
	 * @param string $network telegram|instagram
	 */
	return (string) apply_filters( 'sa_social_image_url', $url, $post_id, $network );
}

/**
 * ساختِ هشتگ‌ها از ترم‌های نوشته.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function sa_social_hashtags_text( $post_id ) {
	$settings = sa_social_settings();
	if ( empty( $settings['hashtags'] ) ) {
		return '';
	}

	$names = array();
	$taxes = get_object_taxonomies( get_post_type( $post_id ), 'names' );
	foreach ( (array) $taxes as $tax ) {
		if ( in_array( $tax, array( 'post_format' ), true ) ) {
			continue;
		}
		$terms = get_the_terms( $post_id, $tax );
		if ( ! is_array( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			$names[] = $term->name;
		}
	}

	$tags = array();
	foreach ( $names as $name ) {
		$clean = trim( (string) preg_replace( '/[^\p{L}\p{N}_]+/u', '_', $name ), '_' );
		if ( '' === $clean ) {
			continue;
		}
		$tags[ '#' . $clean ] = true;
	}

	$tags = array_keys( $tags );
	$limit = max( 1, min( 30, (int) $settings['hashtag_limit'] ) );
	$tags = array_slice( $tags, 0, $limit );

	return implode( ' ', $tags );
}

/**
 * خلاصه‌ای کوتاه و تمیز از نوشته.
 *
 * @param WP_Post $post Post object.
 * @param int     $max  Max characters.
 * @return string
 */
function sa_social_excerpt_text( $post, $max = 260 ) {
	$text = '';
	if ( ! empty( $post->post_excerpt ) ) {
		$text = $post->post_excerpt;
	} else {
		$text = wp_strip_all_tags( (string) $post->post_content );
	}
	$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_shortcodes( $text ) ) );
	if ( function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > $max ) {
		$text = mb_substr( $text, 0, $max - 1, 'UTF-8' ) . '…';
	}
	return $text;
}

/**
 * نامِ استان/شهرِ مرتبط (برای متن پیام).
 *
 * @param int $post_id Post ID.
 * @return array{province:string,city:string}
 */
function sa_social_place_names( $post_id ) {
	$out = array( 'province' => '', 'city' => '' );
	$taxes = get_object_taxonomies( get_post_type( $post_id ), 'names' );
	foreach ( (array) $taxes as $tax ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( ! is_array( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( '' === $out['province'] && false !== strpos( $tax, 'province' ) ) {
				$out['province'] = $term->name;
			}
			if ( '' === $out['city'] && false !== strpos( $tax, 'city' ) ) {
				$out['city'] = $term->name;
			}
		}
	}
	return $out;
}

/**
 * ساختِ متن پیام از روی قالب.
 *
 * Placeholderها: {title} {excerpt} {link} {tags} {province} {city} {date}
 *
 * @param string $template Template.
 * @param int    $post_id  Post ID.
 * @param string $network  telegram|instagram
 * @param bool   $caption  Caption mode (shorter).
 * @return string
 */
function sa_social_build_text( $template, $post_id, $network = 'telegram', $caption = false ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$settings = sa_social_settings();
	$place    = sa_social_place_names( $post_id );

	$title   = get_the_title( $post_id );
	$excerpt = sa_social_excerpt_text( $post, $caption ? 200 : 320 );
	$link    = get_permalink( $post_id );
	$tags    = sa_social_hashtags_text( $post_id );
	$date    = function_exists( 'sa_jalali_date' ) ? sa_jalali_date( get_the_date( 'Y-m-d', $post_id ) ) : get_the_date( 'Y/m/d', $post_id );

	if ( 'telegram' === $network ) {
		// حالت HTMLِ تلگرام فقط این تگ‌ها را مجاز می‌داند.
		$title   = esc_html( $title );
		$excerpt = esc_html( $excerpt );
		$date    = esc_html( (string) $date );
	} else {
		$title   = wp_strip_all_tags( $title );
		$excerpt = wp_strip_all_tags( $excerpt );
	}

	$map = array(
		'{title}'    => $title,
		'{excerpt}'  => $excerpt,
		'{link}'     => esc_url( $link ),
		'{tags}'     => $tags,
		'{province}' => $place['province'],
		'{city}'     => $place['city'],
		'{date}'     => (string) $date,
	);

	$text = str_replace( array_keys( $map ), array_values( $map ), (string) $template );
	$text = trim( (string) preg_replace( "/\n{3,}/", "\n\n", $text ) );

	$max = (int) sa_social_max_length( $network, $caption );
	if ( function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > $max ) {
		$text = mb_substr( $text, 0, $max - 1, 'UTF-8' ) . '…';
	}

	/**
	 * Filters the final social message.
	 *
	 * @param string $text    Message.
	 * @param int    $post_id Post ID.
	 * @param string $network telegram|instagram
	 */
	return (string) apply_filters( 'sa_social_message', $text, $post_id, $network );
}

/* -------------------------------------------------------------------------
 * تلگرام (Bot API)
 * ---------------------------------------------------------------------- */

/**
 * فراخوانیِ Bot API.
 *
 * @param string $method Method name.
 * @param array  $args   Payload.
 * @param string $token  Optional token override.
 * @return array{ok:bool,data:mixed,message:string}
 */
function sa_social_telegram_api( $method, $args = array(), $token = '' ) {
	$settings = sa_social_settings();
	$token    = preg_replace( '/[^A-Za-z0-9_:-]/', '', '' === $token ? (string) $settings['telegram_token'] : (string) $token );
	$method   = preg_replace( '/[^A-Za-z]/', '', (string) $method );
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

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

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
 * ارسال یک نوشته به تلگرام.
 *
 * @param int $post_id Post ID.
 * @return array{ok:bool,message:string}
 */
function sa_social_telegram_send( $post_id ) {
	$settings = sa_social_settings();
	$chat     = trim( (string) $settings['telegram_chat'] );
	if ( '' === $chat ) {
		return array( 'ok' => false, 'message' => 'شناسهٔ کانال تلگرام تنظیم نشده است.' );
	}

	$image = ! empty( $settings['telegram_image'] ) ? sa_social_image_url( $post_id, 'telegram' ) : '';
	$args  = array(
		'chat_id'                  => $chat,
		'disable_web_page_preview' => false,
	);

	if ( $image ) {
		$args['photo']   = $image;
		$args['caption'] = sa_social_build_text( $settings['telegram_template'], $post_id, 'telegram', true );
		$args['parse_mode'] = 'HTML';
		$method            = 'sendPhoto';
	} else {
		$args['text']       = sa_social_build_text( $settings['telegram_template'], $post_id, 'telegram', false );
		$args['parse_mode'] = 'HTML';
		$method             = 'sendMessage';
	}

	/**
	 * Filters the Telegram API payload.
	 *
	 * @param array $args    Payload.
	 * @param int   $post_id Post ID.
	 */
	$args = (array) apply_filters( 'sa_social_telegram_args', $args, $post_id );

	$result = sa_social_telegram_api( $method, $args );

	if ( $result['ok'] ) {
		return array( 'ok' => true, 'message' => 'به تلگرام ارسال شد (' . ( $image ? 'عکس + متن' : 'متن' ) . ').' );
	}
	return array( 'ok' => false, 'message' => $result['message'] );
}

/**
 * تستِ اتصالِ تلگرام (getMe + بررسیِ کانال).
 *
 * @return array{ok:bool,message:string}
 */
function sa_social_telegram_test() {
	$me = sa_social_telegram_api( 'getMe' );
	if ( ! $me['ok'] ) {
		return array( 'ok' => false, 'message' => $me['message'] );
	}
	$username = isset( $me['data']['username'] ) ? '@' . $me['data']['username'] : '(بدون نام کاربری)';

	$settings = sa_social_settings();
	$chat     = trim( (string) $settings['telegram_chat'] );
	if ( '' === $chat ) {
		return array( 'ok' => true, 'message' => 'ربات پیدا شد: ' . $username . ' — شناسهٔ کانال هنوز خالی است.' );
	}

	$chat_test = sa_social_telegram_api( 'getChat', array( 'chat_id' => $chat ) );
	if ( ! $chat_test['ok'] ) {
		return array( 'ok' => false, 'message' => 'ربات پیدا شد (' . $username . ') اما کانال در دسترس نیست: ' . $chat_test['message'] );
	}
	$title = isset( $chat_test['data']['title'] ) ? (string) $chat_test['data']['title'] : (string) $chat;
	return array( 'ok' => true, 'message' => 'ربات ' . $username . ' به «' . $title . '» دسترسی دارد.' );
}

/**
 * آخرین پیام‌های دریافت‌شده توسط ربات (برای پیدا کردنِ chat_id).
 *
 * @return array{ok:bool,message:string}
 */
function sa_social_telegram_updates() {
	$updates = sa_social_telegram_api( 'getUpdates', array( 'limit' => 20, 'timeout' => 0 ) );
	if ( ! $updates['ok'] ) {
		return array( 'ok' => false, 'message' => $updates['message'] );
	}
	$found = array();
	foreach ( (array) $updates['data'] as $item ) {
		$chat = null;
		if ( isset( $item['channel_post']['chat'] ) ) {
			$chat = $item['channel_post']['chat'];
		} elseif ( isset( $item['message']['chat'] ) ) {
			$chat = $item['message']['chat'];
		}
		if ( ! is_array( $chat ) || ! isset( $chat['id'] ) ) {
			continue;
		}
		$label = isset( $chat['title'] ) ? $chat['title'] : ( isset( $chat['username'] ) ? '@' . $chat['username'] : '' );
		$found[ (string) $chat['id'] ] = $label;
	}
	if ( empty( $found ) ) {
		return array( 'ok' => false, 'message' => 'پیامی پیدا نشد. ابتدا در کانال یا گروه خود یک پیام بفرستید، سپس دوباره امتحان کنید.' );
	}
	$parts = array();
	foreach ( $found as $id => $label ) {
		$parts[] = $id . ( '' !== $label ? ' (' . $label . ')' : '' );
	}
	return array( 'ok' => true, 'message' => 'شناسه‌های پیداشده: ' . implode( ' — ', $parts ) );
}

/* -------------------------------------------------------------------------
 * اینستاگرام (Instagram Graph API)
 * ---------------------------------------------------------------------- */

/**
 * نشانیِ پایهٔ گراف.
 *
 * @return string
 */
function sa_social_graph_base() {
	return 'https://graph.facebook.com/v21.0';
}

/**
 * ارسال یک نوشته به اینستاگرام (دو مرحله: ساخت محفظه + انتشار).
 *
 * @param int $post_id Post ID.
 * @return array{ok:bool,message:string}
 */
function sa_social_instagram_send( $post_id ) {
	$settings = sa_social_settings();
	$token    = trim( (string) $settings['instagram_token'] );
	$user     = trim( (string) $settings['instagram_user'] );
	if ( '' === $token || '' === $user ) {
		return array( 'ok' => false, 'message' => 'توکن یا شناسهٔ حساب اینستاگرام تنظیم نشده است.' );
	}

	$image = sa_social_image_url( $post_id, 'instagram' );
	if ( '' === $image ) {
		return array( 'ok' => false, 'message' => 'اینستاگرام فقط تصویر JPEG می‌پذیرد و در این مطلب تصویر JPEG عمومی پیدا نشد.' );
	}

	$caption = sa_social_build_text( $settings['instagram_template'], $post_id, 'instagram', false );
	$args    = array(
		'image_url'     => $image,
		'caption'       => $caption,
		'access_token'  => $token,
	);

	/**
	 * Filters the Instagram container payload.
	 *
	 * @param array $args    Payload.
	 * @param int   $post_id Post ID.
	 */
	$args = (array) apply_filters( 'sa_social_instagram_args', $args, $post_id );

	$create = wp_remote_post(
		sa_social_graph_base() . '/' . rawurlencode( $user ) . '/media',
		array( 'timeout' => 25, 'body' => $args )
	);

	if ( is_wp_error( $create ) ) {
		return array( 'ok' => false, 'message' => 'خطای ارتباط با اینستاگرام: ' . $create->get_error_message() );
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $create ), true );
	if ( ! is_array( $body ) || ! isset( $body['id'] ) ) {
		$error = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'پاسخ نامعتبر';
		return array( 'ok' => false, 'message' => 'ساخت محفظه ناموفق بود: ' . $error );
	}

	$creation_id = (string) $body['id'];
	update_post_meta( $post_id, '_sa_social_instagram_creation', $creation_id );

	return sa_social_instagram_publish( $post_id, $creation_id, 0 );
}

/**
 * انتشارِ محفظهٔ ساخته‌شده (با تلاشِ دوباره در صورت آماده‌نبودن تصویر).
 *
 * @param int    $post_id     Post ID.
 * @param string $creation_id Container id.
 * @param int    $attempt     Attempt number.
 * @return array{ok:bool,message:string}
 */
function sa_social_instagram_publish( $post_id, $creation_id, $attempt = 0 ) {
	$settings = sa_social_settings();
	$token    = trim( (string) $settings['instagram_token'] );
	$user     = trim( (string) $settings['instagram_user'] );

	$response = wp_remote_post(
		sa_social_graph_base() . '/' . rawurlencode( $user ) . '/media_publish',
		array(
			'timeout' => 25,
			'body'    => array(
				'creation_id'  => $creation_id,
				'access_token' => $token,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'message' => 'خطای ارتباط: ' . $response->get_error_message() );
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( is_array( $body ) && isset( $body['id'] ) ) {
		delete_post_meta( $post_id, '_sa_social_instagram_creation' );
		return array( 'ok' => true, 'message' => 'در اینستاگرام منتشر شد (شناسهٔ رسانه: ' . (string) $body['id'] . ').' );
	}

	$error = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'پاسخ نامعتبر';
	$code  = isset( $body['error']['code'] ) ? (int) $body['error']['code'] : 0;

	// تصویر هنوز آماده نیست — یک تلاشِ زمان‌بندی‌شدهٔ دیگر.
	if ( $attempt < 3 ) {
		wp_schedule_single_event( time() + 60, 'sa_social_instagram_publish_event', array( (int) $post_id, (string) $creation_id, (int) $attempt + 1 ) );
		return array( 'ok' => false, 'message' => 'تصویر در اینستاگرام هنوز آماده نیست؛ یک دقیقهٔ دیگر دوباره تلاش می‌شود.' );
	}

	return array( 'ok' => false, 'message' => 'انتشار ناموفق بود: ' . $error . ( $code ? ' (کد ' . $code . ')' : '' ) );
}

/**
 * تستِ اتصالِ اینستاگرام.
 *
 * @return array{ok:bool,message:string}
 */
function sa_social_instagram_test() {
	$settings = sa_social_settings();
	$token    = trim( (string) $settings['instagram_token'] );
	$user     = trim( (string) $settings['instagram_user'] );
	if ( '' === $token ) {
		return array( 'ok' => false, 'message' => 'توکن اینستاگرام تنظیم نشده است.' );
	}
	if ( '' === $user ) {
		return array( 'ok' => false, 'message' => 'شناسهٔ حساب اینستاگرام تنظیم نشده است.' );
	}

	$url      = sa_social_graph_base() . '/' . rawurlencode( $user ) . '?fields=id,username,media_count&access_token=' . rawurlencode( $token );
	$response = wp_remote_get( $url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'message' => 'خطای ارتباط: ' . $response->get_error_message() );
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( is_array( $body ) && isset( $body['username'] ) ) {
		$count = isset( $body['media_count'] ) ? (int) $body['media_count'] : 0;
		return array( 'ok' => true, 'message' => 'حساب اینستاگرام پیدا شد: @' . (string) $body['username'] . ' (' . $count . ' رسانه).' );
	}
	$error = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'پاسخ نامعتبر';
	return array( 'ok' => false, 'message' => 'اینستاگرام: ' . $error );
}

/* -------------------------------------------------------------------------
 * هماهنگی و زمان‌بندی
 * ---------------------------------------------------------------------- */

/**
 * آیا این نوشته باید ارسال شود؟
 *
 * @param int    $post_id Post ID.
 * @param string $network telegram|instagram
 * @return bool
 */
function sa_social_should_send( $post_id, $network ) {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return false;
	}
	if ( ! empty( get_post_meta( $post_id, '_sa_social_skip', true ) ) ) {
		return false;
	}
	if ( ! empty( get_post_meta( $post_id, '_sa_social_' . $network . '_sent', true ) ) ) {
		return false;
	}
	if ( ! in_array( (string) $post->post_type, sa_social_post_types(), true ) ) {
		return false;
	}

	$settings = sa_social_settings();
	if ( empty( $settings[ $network . '_enable' ] ) ) {
		return false;
	}

	/**
	 * Filters whether a post should be published to a network.
	 *
	 * @param bool   $should  Whether to send.
	 * @param int    $post_id Post ID.
	 * @param string $network telegram|instagram
	 */
	return (bool) apply_filters( 'sa_social_should_send', true, $post_id, $network );
}

/**
 * زمان‌بندیِ ارسال هنگام انتشارِ نخست.
 *
 * @param string   $new_status New status.
 * @param string   $old_status Old status.
 * @param \WP_Post $post       Post object.
 */
function sa_social_transition( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	$post = get_post( $post );
	if ( ! $post ) {
		return;
	}
	if ( ! in_array( (string) $post->post_type, sa_social_post_types(), true ) ) {
		return;
	}

	$settings = sa_social_settings();
	$delay    = max( 0, min( 120, (int) $settings['delay_minutes'] ) );
	$delay    = $delay * MINUTE_IN_SECONDS;

	if ( sa_social_should_send( $post->ID, 'telegram' ) || sa_social_should_send( $post->ID, 'instagram' ) ) {
		if ( ! wp_next_scheduled( 'sa_social_publish_event', array( (int) $post->ID ) ) ) {
			wp_schedule_single_event( time() + $delay, 'sa_social_publish_event', array( (int) $post->ID ) );
		}
	}
}
add_action( 'transition_post_status', 'sa_social_transition', 20, 3 );

/**
 * اجرای ارسال از طریق کرون.
 *
 * @param int $post_id Post ID.
 */
function sa_social_cron_send( $post_id ) {
	sa_social_send_post( (int) $post_id );
}
add_action( 'sa_social_publish_event', 'sa_social_cron_send', 10, 1 );

/**
 * تلاشِ دوبارهٔ انتشار اینستاگرام.
 *
 * @param int    $post_id     Post ID.
 * @param string $creation_id Container id.
 * @param int    $attempt     Attempt number.
 */
function sa_social_cron_instagram( $post_id, $creation_id, $attempt ) {
	$result = sa_social_instagram_publish( (int) $post_id, (string) $creation_id, (int) $attempt );
	sa_social_store_result( (int) $post_id, 'instagram', $result );
}
add_action( 'sa_social_instagram_publish_event', 'sa_social_cron_instagram', 10, 3 );

/**
 * ارسالِ یک نوشته به شبکه‌های فعال.
 *
 * @param int             $post_id  Post ID.
 * @param array<int,string> $networks Optional list; defaults to all enabled.
 * @return array<string,array{ok:bool,message:string}>
 */
function sa_social_send_post( $post_id, $networks = array() ) {
	$post_id = (int) $post_id;
	$lock    = 'sa_social_lock_' . $post_id;
	if ( false !== get_transient( $lock ) ) {
		return array();
	}
	set_transient( $lock, time(), 60 );

	$networks = empty( $networks ) ? array( 'telegram', 'instagram' ) : (array) $networks;
	$results  = array();

	foreach ( $networks as $network ) {
		if ( ! sa_social_should_send( $post_id, $network ) ) {
			continue;
		}
		if ( 'telegram' === $network ) {
			$result = sa_social_telegram_send( $post_id );
		} elseif ( 'instagram' === $network ) {
			$result = sa_social_instagram_send( $post_id );
		} else {
			continue;
		}
		$results[ $network ] = $result;
		sa_social_store_result( $post_id, $network, $result );
	}

	delete_transient( $lock );
	return $results;
}

/**
 * ذخیرهٔ نتیجه در متای نوشته و در گزارش.
 *
 * @param int    $post_id Post ID.
 * @param string $network telegram|instagram
 * @param array  $result  Result array.
 */
function sa_social_store_result( $post_id, $network, $result ) {
	$ok      = ! empty( $result['ok'] );
	$message = isset( $result['message'] ) ? (string) $result['message'] : '';

	$data = array(
		'ok'      => $ok,
		'message' => $message,
		'time'    => time(),
	);
	if ( $ok ) {
		update_post_meta( $post_id, '_sa_social_' . $network . '_sent', $data );
	} else {
		update_post_meta( $post_id, '_sa_social_' . $network . '_error', $data );
	}

	sa_social_log_add(
		array(
			'post_id' => (int) $post_id,
			'title'   => get_the_title( $post_id ),
			'network' => $network,
			'ok'      => $ok,
			'message' => $message,
			'time'    => time(),
		)
	);
}

/* -------------------------------------------------------------------------
 * گزارش
 * ---------------------------------------------------------------------- */

/**
 * افزودن یک ردیف به گزارش (۲۰ مورد آخر).
 *
 * @param array $entry Entry.
 */
function sa_social_log_add( $entry ) {
	$log   = get_option( 'sa_social_log', array() );
	$log   = is_array( $log ) ? $log : array();
	array_unshift( $log, $entry );
	$log = array_slice( $log, 0, 20 );
	update_option( 'sa_social_log', $log, false );
}

/**
 * گزارشِ ارسال‌ها.
 *
 * @return array<int,array>
 */
function sa_social_logs() {
	$log = get_option( 'sa_social_log', array() );
	return is_array( $log ) ? $log : array();
}

/* -------------------------------------------------------------------------
 * ارسالِ انبوهِ مطالبِ موجود (گذشته‌نگر)
 * ---------------------------------------------------------------------- */

/**
 * وضعیتِ پیش‌فرضِ صف ارسالِ انبوه.
 *
 * @return array<string,mixed>
 */
function sa_social_bulk_defaults() {
	return array(
		'ids'      => array(),
		'total'    => 0,
		'done'     => 0,
		'ok'       => 0,
		'failed'   => 0,
		'network'  => 'telegram',
		'force'    => 0,
		'types'    => array(),
		'started'  => 0,
		'finished' => 0,
		'last'     => '',
	);
}

/**
 * وضعیتِ فعلیِ ارسالِ انبوه.
 *
 * @return array<string,mixed>
 */
function sa_social_bulk_state() {
	$state = get_option( 'sa_social_bulk', array() );
	return wp_parse_args( is_array( $state ) ? $state : array(), sa_social_bulk_defaults() );
}

/**
 * شروعِ ارسالِ انبوه.
 *
 * @param array<int,string> $types  Post types (ordered).
 * @param string            $network telegram|instagram
 * @param bool              $force  Resend already-sent posts.
 * @param int               $limit  Max posts per type (0 = unlimited).
 * @return int تعدادِ مطالبِ در صف
 */
function sa_social_bulk_start( $types, $network = 'telegram', $force = false, $limit = 0 ) {
	$allowed = sa_social_post_types();
	$types   = array_values( array_intersect( (array) $types, $allowed ) );
	if ( empty( $types ) ) {
		return 0;
	}

	$ids = array();
	foreach ( $types as $type ) {
		$args = array(
			'post_type'        => $type,
			'post_status'      => 'publish',
			'posts_per_page'   => $limit > 0 ? (int) $limit : 500,
			'fields'           => 'ids',
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		);
		$found = get_posts( $args );
		foreach ( (array) $found as $id ) {
			$id = (int) $id;
			if ( ! $force && ! empty( get_post_meta( $id, '_sa_social_' . $network . '_sent', true ) ) ) {
				continue;
			}
			$ids[] = $id;
		}
	}

	$state = sa_social_bulk_defaults();
	$state['ids']     = array_values( array_unique( $ids ) );
	$state['total']   = count( $state['ids'] );
	$state['network'] = in_array( $network, array( 'telegram', 'instagram' ), true ) ? $network : 'telegram';
	$state['force']   = $force ? 1 : 0;
	$state['types']   = $types;
	$state['started'] = time();

	update_option( 'sa_social_bulk', $state, false );

	if ( $state['total'] > 0 && ! wp_next_scheduled( 'sa_social_bulk_event' ) ) {
		wp_schedule_single_event( time() + 10, 'sa_social_bulk_event' );
	}

	return $state['total'];
}

/**
 * توقف و پاک‌کردنِ صف.
 */
function sa_social_bulk_stop() {
	delete_option( 'sa_social_bulk' );
	wp_clear_scheduled_hook( 'sa_social_bulk_event' );
}

/**
 * اجرای یک دسته از صف (۳ تا در هر بار، سپس وقفهٔ ۷۰ ثانیه‌ای).
 */
function sa_social_bulk_tick() {
	$state = sa_social_bulk_state();
	if ( empty( $state['ids'] ) ) {
		$state['finished'] = time();
		update_option( 'sa_social_bulk', $state, false );
		return;
	}

	wp_clear_scheduled_hook( 'sa_social_bulk_event' );

	$batch = array_splice( $state['ids'], 0, 3 );
	foreach ( $batch as $post_id ) {
		$post_id = (int) $post_id;
		if ( ! get_post( $post_id ) ) {
			$state['done']++;
			continue;
		}
		if ( ! empty( $state['force'] ) ) {
			delete_post_meta( $post_id, '_sa_social_' . $state['network'] . '_sent' );
			delete_post_meta( $post_id, '_sa_social_' . $state['network'] . '_error' );
		}
		$result = 'instagram' === $state['network'] ? sa_social_instagram_send( $post_id ) : sa_social_telegram_send( $post_id );
		sa_social_store_result( $post_id, $state['network'], $result );

		$state['done']++;
		if ( ! empty( $result['ok'] ) ) {
			$state['ok']++;
		} else {
			$state['failed']++;
			$state['last'] = (string) $result['message'];
		}
	}

	if ( empty( $state['ids'] ) ) {
		$state['finished'] = time();
	} else {
		wp_schedule_single_event( time() + 70, 'sa_social_bulk_event' );
	}

	update_option( 'sa_social_bulk', $state, false );
}
add_action( 'sa_social_bulk_event', 'sa_social_bulk_tick' );

/**
 * شروعِ ارسال انبوه از پیشخوان.
 */
function sa_social_bulk_start_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_social_bulk' );

	$types = isset( $_POST['bulk_types'] ) ? (array) wp_unslash( $_POST['bulk_types'] ) : array();
	$types = array_map( 'sanitize_key', $types );
	$net   = isset( $_POST['bulk_network'] ) ? sanitize_key( wp_unslash( $_POST['bulk_network'] ) ) : 'telegram';
	$force = ! empty( $_POST['bulk_force'] );
	$limit = isset( $_POST['bulk_limit'] ) ? max( 0, min( 500, (int) $_POST['bulk_limit'] ) ) : 0;

	$count = sa_social_bulk_start( $types, $net, $force, $limit );

	set_transient(
		'sa_social_notice',
		array(
			'ok'      => $count > 0,
			'message' => 0 === $count
				? 'مطلبی برای ارسال پیدا نشد (همه ارسال شده‌اند یا نوعی انتخاب نکرده‌اید).'
				: 'ارسال انبوه شروع شد: ' . $count . ' مطلب در صف (هر بار ۳ مورد با وقفهٔ ۷۰ ثانیه برای رعایت محدودیت تلگرام).',
		),
		120
	);

	wp_safe_redirect( admin_url( 'admin.php?page=sa-social-publish' ) );
	exit;
}
add_action( 'admin_post_sa_social_bulk_start', 'sa_social_bulk_start_action' );

/**
 * توقفِ ارسال انبوه.
 */
function sa_social_bulk_stop_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_social_bulk' );

	sa_social_bulk_stop();
	set_transient( 'sa_social_notice', array( 'ok' => true, 'message' => 'ارسال انبوه متوقف و صف پاک شد.' ), 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=sa-social-publish' ) );
	exit;
}
add_action( 'admin_post_sa_social_bulk_stop', 'sa_social_bulk_stop_action' );

/* -------------------------------------------------------------------------
 * مدیریت: منو، تنظیمات، تست، ارسال دستی
 * ---------------------------------------------------------------------- */

/**
 * زیرمنوی «شبکه‌های اجتماعی».
 */
function sa_social_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'شبکه‌های اجتماعی',
		'شبکه‌های اجتماعی',
		'manage_options',
		'sa-social-publish',
		'sa_social_settings_page'
	);
}
add_action( 'admin_menu', 'sa_social_menu', 21 );

/**
 * ثبت تنظیمات.
 */
function sa_social_register_settings() {
	register_setting(
		'sa_social_settings_group',
		'sa_social_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sa_social_sanitize',
			'default'           => sa_social_defaults(),
		)
	);
}
add_action( 'admin_init', 'sa_social_register_settings' );

/**
 * پاک‌سازیِ ورودی‌های تنظیمات.
 *
 * @param mixed $input Raw input.
 * @return array<string,mixed>
 */
function sa_social_sanitize( $input ) {
	$old  = sa_social_settings();
	$out  = sa_social_defaults();
	$data = is_array( $input ) ? $input : array();

	$out['telegram_enable']    = empty( $data['telegram_enable'] ) ? 0 : 1;
	$out['telegram_image']     = empty( $data['telegram_image'] ) ? 0 : 1;
	$out['instagram_enable']   = empty( $data['instagram_enable'] ) ? 0 : 1;
	$out['hashtags']           = empty( $data['hashtags'] ) ? 0 : 1;
	$telegram_token            = isset( $data['telegram_token'] ) ? trim( sanitize_text_field( wp_unslash( $data['telegram_token'] ) ) ) : '';
	$out['telegram_token']     = '' === $telegram_token ? $old['telegram_token'] : $telegram_token;
	$out['telegram_chat']      = isset( $data['telegram_chat'] ) ? sanitize_text_field( wp_unslash( $data['telegram_chat'] ) ) : '';
	$instagram_token           = isset( $data['instagram_token'] ) ? trim( sanitize_text_field( wp_unslash( $data['instagram_token'] ) ) ) : '';
	$out['instagram_token']    = '' === $instagram_token ? $old['instagram_token'] : $instagram_token;
	$out['instagram_user']     = isset( $data['instagram_user'] ) ? sanitize_text_field( wp_unslash( $data['instagram_user'] ) ) : '';
	$out['telegram_template']  = isset( $data['telegram_template'] ) ? wp_kses_post( wp_unslash( $data['telegram_template'] ) ) : $old['telegram_template'];
	$out['instagram_template'] = isset( $data['instagram_template'] ) ? sanitize_textarea_field( wp_unslash( $data['instagram_template'] ) ) : $old['instagram_template'];

	$out['delay_minutes'] = isset( $data['delay_minutes'] ) ? max( 0, min( 120, (int) $data['delay_minutes'] ) ) : $old['delay_minutes'];
	$out['hashtag_limit'] = isset( $data['hashtag_limit'] ) ? max( 1, min( 30, (int) $data['hashtag_limit'] ) ) : $old['hashtag_limit'];

	$types = array();
	if ( ! empty( $data['post_types'] ) && is_array( $data['post_types'] ) ) {
		foreach ( $data['post_types'] as $type ) {
			$type = sanitize_key( (string) $type );
			if ( in_array( $type, sa_social_post_types(), true ) ) {
				$types[] = $type;
			}
		}
	}
	$out['post_types'] = $types;

	return $out;
}

/**
 * ماسک‌کردنِ توکن برای نمایش.
 *
 * @param string $token Token.
 * @return string
 */
function sa_social_mask( $token ) {
	$token = trim( (string) $token );
	if ( '' === $token ) {
		return '—';
	}
	$len = strlen( $token );
	if ( $len <= 8 ) {
		return str_repeat( '•', $len );
	}
	return substr( $token, 0, 4 ) . str_repeat( '•', min( 12, $len - 8 ) ) . substr( $token, -4 );
}

/**
 * صفحهٔ تنظیمات.
 */
function sa_social_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	$settings = sa_social_settings();
	$logs     = sa_social_logs();
	$types    = sa_social_post_types();
	$notice   = get_transient( 'sa_social_notice' );
	delete_transient( 'sa_social_notice' );
	?>
	<div class="wrap sa-social-wrap" dir="rtl">
		<h1>شبکه‌های اجتماعی (تلگرام و اینستاگرام)</h1>
		<p class="description">
			انتشار خودکارِ مطالبِ منتشرشده در کانال تلگرام و حساب اینستاگرام — بدون افزونه و مستقیماً از داخل قالب.
			راهنمای کامل در <code>docs/social-auto-publish.md</code> است.
		</p>

		<?php if ( is_array( $notice ) && ! empty( $notice['message'] ) ) : ?>
			<div class="notice notice-<?php echo empty( $notice['ok'] ) ? 'error' : 'success'; ?> is-dismissible">
				<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'sa_social_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">ارسال خودکار</th>
					<td>
						<label><input type="checkbox" name="sa_social_settings[telegram_enable]" value="1" <?php checked( 1, (int) $settings['telegram_enable'] ); ?> /> تلگرام</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="sa_social_settings[instagram_enable]" value="1" <?php checked( 1, (int) $settings['instagram_enable'] ); ?> /> اینستاگرام</label>
					</td>
				</tr>
				<tr>
					<th scope="row">انواع محتوا</th>
					<td>
						<?php foreach ( $types as $type ) : ?>
							<label style="display:inline-block;margin-left:12px;">
								<input type="checkbox" name="sa_social_settings[post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, (array) $settings['post_types'], true ) ); ?> />
								<?php echo esc_html( $type ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-social-delay">تأخیر پس از انتشار (دقیقه)</label></th>
					<td>
						<input type="number" min="0" max="120" id="sa-social-delay" name="sa_social_settings[delay_minutes]" value="<?php echo esc_attr( (string) $settings['delay_minutes'] ); ?>" />
						<p class="description">فرصت می‌دهد پیش از ارسال، مطلب را ویرایش یا لغو کنید.</p>
					</td>
				</tr>

				<tr><th colspan="2"><h2 style="margin:8px 0 0;">تلگرام</h2></th></tr>
				<tr>
					<th scope="row"><label for="sa-social-tg-token">توکن ربات</label></th>
					<td>
						<input type="password" class="regular-text" id="sa-social-tg-token" name="sa_social_settings[telegram_token]" value="" autocomplete="new-password" placeholder="فقط برای تغییر پر کنید" />
						<p class="description">ذخیره‌شده: <code><?php echo esc_html( sa_social_mask( $settings['telegram_token'] ) ); ?></code> — از @BotFather بگیرید. — برای تغییر ندادن خالی بگذارید.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-social-tg-chat">شناسهٔ کانال</label></th>
					<td>
						<input type="text" class="regular-text" dir="ltr" id="sa-social-tg-chat" name="sa_social_settings[telegram_chat]" value="<?php echo esc_attr( (string) $settings['telegram_chat'] ); ?>" placeholder="@sarzaminaryan یا 1001234567890-" />
						<p class="description">ربات را در کانال «ادمین» کنید تا اجازهٔ ارسال داشته باشد.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">تصویر</th>
					<td><label><input type="checkbox" name="sa_social_settings[telegram_image]" value="1" <?php checked( 1, (int) $settings['telegram_image'] ); ?> /> اگر مطلب تصویر دارد، به‌صورت «عکس + توضیح» بفرست</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-social-tg-tpl">قالب پیام تلگرام</label></th>
					<td>
						<textarea id="sa-social-tg-tpl" name="sa_social_settings[telegram_template]" rows="6" class="large-text code" dir="rtl"><?php echo esc_textarea( (string) $settings['telegram_template'] ); ?></textarea>
						<p class="description">متغیرها: <code>{title}</code> <code>{excerpt}</code> <code>{link}</code> <code>{tags}</code> <code>{province}</code> <code>{city}</code> <code>{date}</code> — تگ‌های مجاز: b و i و a.</p>
					</td>
				</tr>

				<tr><th colspan="2"><h2 style="margin:8px 0 0;">اینستاگرام</h2></th></tr>
				<tr>
					<th scope="row"><label for="sa-social-ig-token">توکن دسترسی</label></th>
					<td>
						<input type="password" class="regular-text" id="sa-social-ig-token" name="sa_social_settings[instagram_token]" value="" autocomplete="new-password" placeholder="فقط برای تغییر پر کنید" />
						<p class="description">ذخیره‌شده: <code><?php echo esc_html( sa_social_mask( $settings['instagram_token'] ) ); ?></code> — توکنِ بلندمدت (۶۰ روزه) از گراف فیسبوک. — برای تغییر ندادن خالی بگذارید.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-social-ig-user">شناسهٔ حساب (IG User ID)</label></th>
					<td>
						<input type="text" class="regular-text" dir="ltr" id="sa-social-ig-user" name="sa_social_settings[instagram_user]" value="<?php echo esc_attr( (string) $settings['instagram_user'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sa-social-ig-tpl">قالب توضیح اینستاگرام</label></th>
					<td>
						<textarea id="sa-social-ig-tpl" name="sa_social_settings[instagram_template]" rows="6" class="large-text code" dir="rtl"><?php echo esc_textarea( (string) $settings['instagram_template'] ); ?></textarea>
						<p class="description">در اینستاگرام لینک‌ها قابل کلیک نیستند؛ لینک را در «بیو» بگذارید. فقط تصویر JPEG پذیرفته می‌شود.</p>
					</td>
				</tr>

				<tr><th colspan="2"><h2 style="margin:8px 0 0;">هشتگ‌ها</h2></th></tr>
				<tr>
					<th scope="row">هشتگ خودکار</th>
					<td>
						<label><input type="checkbox" name="sa_social_settings[hashtags]" value="1" <?php checked( 1, (int) $settings['hashtags'] ); ?> /> از طبقه‌بندی‌های مطلب هشتگ بساز</label>
						&nbsp;
						<label>حداکثر تعداد: <input type="number" min="1" max="30" name="sa_social_settings[hashtag_limit]" value="<?php echo esc_attr( (string) $settings['hashtag_limit'] ); ?>" style="width:70px;" /></label>
					</td>
				</tr>
			</table>
			<?php submit_button( 'ذخیرهٔ تنظیمات' ); ?>
		</form>

		<hr />
		<h2>تست اتصال</h2>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_social_test&network=telegram' ), 'sa_social_test' ) ); ?>">تست تلگرام</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_social_test&network=telegram_chat' ), 'sa_social_test' ) ); ?>">پیدا کردنِ شناسهٔ کانال</a>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_social_test&network=instagram' ), 'sa_social_test' ) ); ?>">تست اینستاگرام</a>
		</p>

		<hr />
		<h2>ارسالِ انبوهِ مطالبِ موجود</h2>
		<p class="description">
			همهٔ مطالبِ از پیش منتشرشده (مثلاً صفحه‌های استان‌ها و شهرستان‌ها) را یکی‌یکی به کانال می‌فرستد؛
			هر بار ۳ مطلب و سپس ۷۰ ثانیه وقفه، تا محدودیتِ تلگرام (حدود ۲۰ پیام در دقیقه برای هر کانال) رعایت شود.
			پیش‌فرض فقط مطالبی فرستاده می‌شوند که تاکنون ارسال نشده‌اند.
		</p>
		<?php $bulk = sa_social_bulk_state(); ?>
		<?php if ( ! empty( $bulk['total'] ) ) : ?>
			<div class="notice notice-info inline">
				<p>
					<strong>وضعیت:</strong>
					<?php if ( empty( $bulk['finished'] ) && ! empty( $bulk['ids'] ) ) : ?>
						در حال اجرا — <?php echo (int) $bulk['done']; ?> از <?php echo (int) $bulk['total']; ?>
						(موفق: <?php echo (int) $bulk['ok']; ?>، ناموفق: <?php echo (int) $bulk['failed']; ?>)
						— بعدی تا کمتر از یک دقیقهٔ دیگر.
					<?php else : ?>
						پایان یافته — <?php echo (int) $bulk['done']; ?> از <?php echo (int) $bulk['total']; ?>
						(موفق: <?php echo (int) $bulk['ok']; ?>، ناموفق: <?php echo (int) $bulk['failed']; ?>)
						<?php echo $bulk['finished'] ? 'در ' . esc_html( date_i18n( 'Y/m/d H:i', (int) $bulk['finished'] ) ) : ''; ?>
					<?php endif; ?>
					<?php if ( ! empty( $bulk['last'] ) ) : ?>
						<br /><small>آخرین خطا: <?php echo esc_html( (string) $bulk['last'] ); ?></small>
					<?php endif; ?>
				</p>
			</div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_social_bulk_start" />
			<?php wp_nonce_field( 'sa_social_bulk' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">شبکه</th>
					<td>
						<label><input type="radio" name="bulk_network" value="telegram" checked="checked" /> تلگرام</label>
						&nbsp;&nbsp;
						<label><input type="radio" name="bulk_network" value="instagram" /> اینستاگرام</label>
					</td>
				</tr>
				<tr>
					<th scope="row">انواع محتوا</th>
					<td>
						<?php
						$preferred = array( 'province' => 'استان', 'city' => 'شهرستان', 'attraction' => 'نمای برتر', 'post' => 'نوشته' );
						foreach ( $preferred as $slug => $label ) :
							if ( ! in_array( $slug, sa_social_post_types(), true ) ) {
								continue;
							}
							?>
							<label style="display:inline-block;margin-left:12px;">
								<input type="checkbox" name="bulk_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, array( 'province', 'city' ), true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">ترتیبِ ارسال: استان‌ها، سپس شهرستان‌ها، سپس نماهای برتر و در آخر نوشته‌ها.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">محدودیت و تکرار</th>
					<td>
						<label>حداکثر برای هر نوع: <input type="number" min="0" max="500" name="bulk_limit" value="0" style="width:80px;" /> <span class="description">(۰ یعنی همه)</span></label>
						<br />
						<label><input type="checkbox" name="bulk_force" value="1" /> ارسالِ دوبارهٔ مطالبی که قبلاً فرستاده شده‌اند</label>
					</td>
				</tr>
			</table>
			<?php submit_button( 'شروعِ ارسالِ انبوه', 'primary', 'sa_social_bulk_go' ); ?>
		</form>
		<p>
			<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_social_bulk_stop' ), 'sa_social_bulk' ) ); ?>">توقف و پاک‌کردنِ صف</a>
		</p>

		<h2>گزارشِ ارسال‌ها (۲۰ مورد آخر)</h2>
		<?php if ( empty( $logs ) ) : ?>
			<p>هنوز ارسالی ثبت نشده است.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th>زمان</th>
						<th>شبکه</th>
						<th>مطلب</th>
						<th>وضعیت</th>
						<th>پیام</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'Y/m/d H:i', (int) $row['time'] ) ); ?></td>
						<td><?php echo esc_html( 'telegram' === $row['network'] ? 'تلگرام' : 'اینستاگرام' ); ?></td>
						<td><?php echo esc_html( (string) $row['title'] ); ?></td>
						<td><?php echo ! empty( $row['ok'] ) ? '<span style="color:#0f5132;">موفق</span>' : '<span style="color:#b02a37;">ناموفق</span>'; ?></td>
						<td><?php echo esc_html( (string) $row['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * اجرای تستِ اتصال.
 */
function sa_social_test_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_social_test' );

	$network = isset( $_GET['network'] ) ? sanitize_key( wp_unslash( $_GET['network'] ) ) : '';
	if ( 'telegram' === $network ) {
		$result = sa_social_telegram_test();
	} elseif ( 'telegram_chat' === $network ) {
		$result = sa_social_telegram_updates();
	} elseif ( 'instagram' === $network ) {
		$result = sa_social_instagram_test();
	} else {
		$result = array( 'ok' => false, 'message' => 'شبکه نامعتبر است.' );
	}

	set_transient( 'sa_social_notice', $result, 120 );
	wp_safe_redirect( admin_url( 'admin.php?page=sa-social-publish' ) );
	exit;
}
add_action( 'admin_post_sa_social_test', 'sa_social_test_action' );

/**
 * ارسالِ دستیِ یک مطلب.
 */
function sa_social_send_now_action() {
	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'publish_posts' ) ) {
		wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sarzaminaryan-child' ) );
	}
	check_admin_referer( 'sa_social_send_' . $post_id );

	$network = isset( $_GET['network'] ) ? sanitize_key( wp_unslash( $_GET['network'] ) ) : '';
	$network = in_array( $network, array( 'telegram', 'instagram' ), true ) ? $network : '';

	if ( '' === $network ) {
		$results = sa_social_send_post( $post_id );
	} else {
		// ارسالِ دستی حتی اگر قبلاً ارسال شده باشد.
		delete_post_meta( $post_id, '_sa_social_' . $network . '_sent' );
		delete_post_meta( $post_id, '_sa_social_' . $network . '_error' );
		$result  = 'telegram' === $network ? sa_social_telegram_send( $post_id ) : sa_social_instagram_send( $post_id );
		sa_social_store_result( $post_id, $network, $result );
		$results = array( $network => $result );
	}

	$messages = array();
	foreach ( (array) $results as $net => $res ) {
		$label      = 'telegram' === $net ? 'تلگرام' : 'اینستاگرام';
		$messages[] = $label . ': ' . (string) $res['message'];
	}
	if ( empty( $messages ) ) {
		$messages[] = 'ارسالی انجام نشد؛ شبکه فعال نیست یا این مطلب از قبل ارسال شده است.';
	}

	set_transient( 'sa_social_notice_post_' . $post_id, implode( ' | ', $messages ), 120 );
	wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
	exit;
}
add_action( 'admin_post_sa_social_send', 'sa_social_send_now_action' );

/**
 * جعبهٔ «شبکه‌های اجتماعی» در ویرایشگر.
 */
function sa_social_metabox_register() {
	$settings = sa_social_settings();
	$types    = (array) $settings['post_types'];
	if ( empty( $types ) ) {
		return;
	}
	foreach ( $types as $type ) {
		add_meta_box(
			'sa-social-publish',
			'شبکه‌های اجتماعی',
			'sa_social_metabox_render',
			$type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'sa_social_metabox_register' );

/**
 * نمایشِ جعبه.
 *
 * @param \WP_Post $post Post object.
 */
function sa_social_metabox_render( $post ) {
	wp_nonce_field( 'sa_social_metabox', 'sa_social_metabox_nonce' );
	$skip = (int) get_post_meta( $post->ID, '_sa_social_skip', true );

	$rows = array(
		'telegram'  => 'تلگرام',
		'instagram' => 'اینستاگرام',
	);

	echo '<p><label><input type="checkbox" name="sa_social_skip" value="1" ' . checked( 1, $skip, false ) . ' /> این مطلب به شبکه‌ها ارسال نشود</label></p>';

	foreach ( $rows as $key => $label ) {
		$sent  = get_post_meta( $post->ID, '_sa_social_' . $key . '_sent', true );
		$error = get_post_meta( $post->ID, '_sa_social_' . $key . '_error', true );
		echo '<p style="margin-bottom:6px;"><strong>' . esc_html( $label ) . ':</strong> ';
		if ( is_array( $sent ) && ! empty( $sent['time'] ) ) {
			echo '<span style="color:#0f5132;">ارسال شده</span> (' . esc_html( date_i18n( 'Y/m/d H:i', (int) $sent['time'] ) ) . ')';
		} elseif ( is_array( $error ) && ! empty( $error['message'] ) ) {
			echo '<span style="color:#b02a37;">ناموفق</span><br /><small>' . esc_html( (string) $error['message'] ) . '</small>';
		} else {
			echo 'ارسال نشده';
		}
		echo '</p>';
	}

	$url = wp_nonce_url( admin_url( 'admin-post.php?action=sa_social_send&post_id=' . (int) $post->ID ), 'sa_social_send_' . (int) $post->ID );
	echo '<p><a class="button" href="' . esc_url( $url ) . '">ارسالِ هم‌اکنون</a></p>';
	echo '<p class="description">ارسالِ خودکار هنگام انتشار و طبق تأخیرِ تعیین‌شده انجام می‌شود.</p>';
}

/**
 * ذخیرهٔ جعبه.
 *
 * @param int $post_id Post ID.
 */
function sa_social_metabox_save( $post_id ) {
	if ( ! isset( $_POST['sa_social_metabox_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_social_metabox_nonce'] ) ), 'sa_social_metabox' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$skip = isset( $_POST['sa_social_skip'] ) ? 1 : 0;
	if ( $skip ) {
		update_post_meta( $post_id, '_sa_social_skip', 1 );
	} else {
		delete_post_meta( $post_id, '_sa_social_skip' );
	}
}
add_action( 'save_post', 'sa_social_metabox_save', 10, 1 );

/**
 * پیامِ نتیجه در بالای صفحهٔ ویرایشگر.
 */
function sa_social_admin_notices() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->base || empty( $_GET['post'] ) ) {
		return;
	}
	$post_id = (int) $_GET['post'];
	$message = get_transient( 'sa_social_notice_post_' . $post_id );
	if ( ! $message ) {
		return;
	}
	delete_transient( 'sa_social_notice_post_' . $post_id );
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( (string) $message ) . '</p></div>';
}
add_action( 'admin_notices', 'sa_social_admin_notices' );
