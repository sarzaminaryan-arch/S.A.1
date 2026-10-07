<?php
/**
 * عیب‌یابیِ سیستم — نگهبانِ مسیرهای REST و تعارضِ نسخهٔ «مشارکت مردمی».
 *
 * چرا این ماژول هست؟
 * گزارشِ خطاهای سایت یک خطای کشندهٔ این‌شکل نشان می‌داد:
 *
 *     PHP Fatal error: Uncaught TypeError: call_user_func(): Argument #1 ($callback)
 *     must be a valid callback, cannot access private method CC_REST::auth()
 *
 * یعنی کدی بیرون از قالب، در `register_rest_route()` یک متدِ «خصوصی» (`private`)
 * را به‌عنوان کالبک یا permission_callback گذاشته است. وردپرس فقط متدهای عمومی را
 * می‌تواند صدا بزند؛ پس درخواست‌های `/cc/v1/...` با خطای ۵۰۰ می‌افتند.
 *
 * در کدِ همین قالب هیچ متدِ خصوصی‌ای به‌عنوان کالبکِ REST استفاده نشده است (همه
 * `public static` یا `__return_true` یا Closure هستند)؛ پس منبعِ خطا یک افزونه یا
 * نسخهٔ قدیمیِ جداگانه است. با این حال، تا وقتی آن منبع پیدا شود، سایت نباید کرش
 * کند:
 *
 *   ۱) فیلترِ `rest_endpoints` هر کالبکِ نامعتبر را پیش از اجرا حذف می‌کند؛
 *      نتیجه: پاسخِ تمیزِ `rest_no_route` به‌جای خطای ۵۰۰.
 *      نکتهٔ مهم (اصلاحِ ۲.۱۱.۴۴): این فیلتر آرایهٔ **ثبت‌شده** را می‌بیند، نه آرایهٔ
 *      نرمال‌شده. هر مسیر دو نوع کلید دارد: کلیدهای **عددی** (هندلرها، همان‌هایی که
 *      کالبک دارند) و کلیدهای **غیرعددی** (گزینه‌های مسیر مثل `schema`، `allow_batch`
 *      و `namespace`) که هندلر نیستند. نسخهٔ ۲.۱۱.۴۳ گزینه‌های مسیر را هم «هندلرِ
 *      بی‌کالبک» فرض می‌کرد و ۱۴۳ هشدارِ کاذب می‌ساخت و همان گزینه‌ها را از مسیرهای
 *      هسته (از جمله `/` و `/batch/v1`) برمی‌داشت. از ۲.۱۱.۴۴ فقط هندلرها بررسی
 *      می‌شوند و گزینه‌های مسیر دست‌نخورده می‌مانند.
 *   ۲) مسیر و **فایلِ تعریف‌کنندهٔ** همان کالبک در پیشخوان اعلام می‌شود تا مالک
 *      بداند کدام افزونه را غیرفعال کند.
 *   ۳) اگر ماژولِ «شهر من» از نسخهٔ دیگری (افزونه) بارگذاری شده باشد، فایلِ آن
 *      نسخه هم همین‌جا گزارش می‌شود.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * قالبِ گزارشِ نگهبان. با هر تغییرِ معنایی در گزارش بالا می‌رود تا گزارشِ
 * قالبِ قدیمی (که ممکن است هشدارِ کاذب داشته باشد) کنار گذاشته شود.
 */
if ( ! defined( 'SA_REST_GUARD_REPORT_FORMAT' ) ) {
	define( 'SA_REST_GUARD_REPORT_FORMAT', 2 );
}

/**
 * توضیحِ خوانا از یک کالبک (برای گزارشِ پیشخوان).
 *
 * @param mixed $callback کالبک.
 * @return string
 */
function sa_rest_guard_describe( $callback ) {
	if ( is_string( $callback ) ) {
		return '' !== $callback ? $callback : '(خالی)';
	}
	if ( is_array( $callback ) && 2 === count( $callback ) ) {
		$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
		return $class . '::' . (string) $callback[1];
	}
	if ( $callback instanceof Closure ) {
		return 'Closure';
	}
	if ( is_object( $callback ) ) {
		return get_class( $callback );
	}
	return gettype( $callback );
}

/**
 * فایل و خطِ تعریفِ کالبک — برای این‌که مالک بداند کدام فایل مسئول است.
 *
 * @param mixed $callback کالبک.
 * @return string
 */
function sa_rest_guard_file( $callback ) {
	try {
		$reflection = null;
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$reflection = new ReflectionMethod( $callback[0], (string) $callback[1] );
		} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
			list( $class, $method ) = explode( '::', $callback, 2 );
			$reflection = new ReflectionMethod( $class, $method );
		} elseif ( is_object( $callback ) && ! ( $callback instanceof Closure ) ) {
			$reflection = new ReflectionMethod( $callback, '__invoke' );
		} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
			$reflection = new ReflectionFunction( $callback );
		} elseif ( $callback instanceof Closure ) {
			$reflection = new ReflectionFunction( $callback );
		}

		if ( $reflection ) {
			$file = (string) $reflection->getFileName();
			if ( '' !== $file ) {
				// مسیرهای داخلِ همین سایت را کوتاه کن تا گزارش خوانا بماند.
				$file = str_replace( ABSPATH, '', $file );
				return $file . ':' . (int) $reflection->getStartLine();
			}
		}
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// بازتاب (reflection) نباید خودش خطا بسازد؛ در این حالت فایل را نمی‌دانیم.
	}
	return '';
}

/**
 * فایلِ تعریف‌کنندهٔ یک کلاس (برای تعارضِ نسخه‌ها).
 *
 * @param string $class نام کلاس.
 * @return string
 */
function sa_system_defining_file( $class ) {
	if ( ! class_exists( $class ) ) {
		return '';
	}
	try {
		$reflection = new ReflectionClass( $class );
		$file       = (string) $reflection->getFileName();
		if ( '' !== $file ) {
			return str_replace( ABSPATH, '', $file );
		}
	} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		// عمداً بی‌صدا.
	}
	return '';
}

/**
 * آیا کالبکِ یک مسیر قابلِ فراخوانی است؟
 *
 * @param mixed $callback کالبک.
 * @param bool  $required اگر خالی‌بودن کالبک هم ایراد است.
 * @return bool
 */
function sa_rest_guard_callable( $callback, $required = true ) {
	if ( null === $callback || '' === $callback || array() === $callback ) {
		return ! $required;
	}
	return is_callable( $callback );
}

/**
 * نامِ متدهای یک هندلر به شکلِ خوانا (`GET, POST`).
 *
 * @param array<string,mixed> $handler هندلر.
 * @return string
 */
function sa_rest_guard_handler_methods( $handler ) {
	if ( ! isset( $handler['methods'] ) ) {
		return '';
	}

	$methods = array();
	foreach ( (array) $handler['methods'] as $key => $value ) {
		if ( is_string( $value ) ) {
			// هسته می‌تواند مقدارهای چندمتدیِ جداشده با کاما (مثلِ WP_REST_Server::EDITABLE) بدهد.
			foreach ( explode( ',', $value ) as $method ) {
				$methods[] = $method;
			}
		} elseif ( true === $value && is_string( $key ) ) {
			// شکلِ پس از نرمال‌سازیِ هسته: array( 'GET' => true ).
			$methods[] = $key;
		}
	}

	$methods = array_filter( array_map( 'trim', $methods ) );
	return implode( ', ', array_values( array_unique( array_map( 'strtoupper', $methods ) ) ) );
}

/**
 * ایرادهای یک هندلر (کالبکِ نامعتبر).
 *
 * @param array<string,mixed> $handler هندلر.
 * @return array<int,array<string,mixed>> فهرستِ ایرادها؛ خالی یعنی هندلر سالم است.
 */
function sa_rest_guard_handler_problems( $handler ) {
	$problems = array();

	$callback = isset( $handler['callback'] ) ? $handler['callback'] : null;
	if ( ! sa_rest_guard_callable( $callback, true ) ) {
		$problems[] = array(
			'kind'  => 'callback',
			'value' => $callback,
		);
	}

	if ( isset( $handler['permission_callback'] ) && ! sa_rest_guard_callable( $handler['permission_callback'], false ) ) {
		$problems[] = array(
			'kind'  => 'permission_callback',
			'value' => $handler['permission_callback'],
		);
	}

	return $problems;
}

/**
 * افزودنِ ایرادهای یک هندلر به گزارش (با نامِ متد و فایلِ تعریف‌کننده).
 *
 * @param array<int,array<string,string>> $blocked گزارشِ در حالِ ساخت.
 * @param string                          $route   مسیر.
 * @param array<string,mixed>             $handler هندلر.
 * @param array<int,array<string,mixed>>  $problems ایرادها.
 * @return void
 */
function sa_rest_guard_collect( &$blocked, $route, $handler, $problems ) {
	$methods = sa_rest_guard_handler_methods( $handler );

	foreach ( $problems as $problem ) {
		$blocked[] = array(
			'route'    => (string) $route,
			'method'   => $methods,
			'kind'     => $problem['kind'],
			'callback' => sa_rest_guard_describe( $problem['value'] ),
			'file'     => sa_rest_guard_file( $problem['value'] ),
		);
	}
}

/**
 * حذفِ مسیرهای REST با کالبکِ نامعتبر (به‌جای خطای کشندهٔ ۵۰۰).
 *
 * ساختارِ آرایهٔ مسیرها در وردپرس دو شکل دارد و نگهبان باید همان تفکیکِ هسته را
 * رعایت کند، وگرنه هشدارِ کاذب می‌سازد و مسیرهای سالمِ هسته را خراب می‌کند:
 *
 *   ۱) «تک‌هندلر»: کلیدِ `callback` در سطحِ بالای آرایهٔ مسیر است؛ مثلِ `/` و
 *      `/batch/v1` که خودِ WP_REST_Server در سازنده ثبت می‌کند. هسته هم با
 *      `isset( $handlers['callback'] )` همین شکل را تشخیص می‌دهد.
 *   ۲) فهرستِ هندلرها: کلیدهای **عددی** هندلرند و کلیدهای **غیرعددی** گزینهٔ مسیر
 *      (`schema`، `allow_batch`، `namespace` و…) که هندلر نیستند و دست‌نخورده می‌مانند.
 *
 * @param array<string,mixed> $endpoints فهرستِ مسیرها.
 * @return array<string,mixed>
 */
function sa_rest_guard_filter( $endpoints ) {
	if ( ! is_array( $endpoints ) ) {
		return $endpoints;
	}

	$blocked = array();
	foreach ( $endpoints as $route => $handlers ) {
		if ( ! is_array( $handlers ) ) {
			continue;
		}

		// شکلِ ۱: خودِ آرایه یک هندلر است.
		if ( isset( $handlers['callback'] ) ) {
			$problems = sa_rest_guard_handler_problems( $handlers );
			if ( $problems ) {
				sa_rest_guard_collect( $blocked, $route, $handlers, $problems );
				unset( $endpoints[ $route ] );
			}
			continue;
		}

		// شکلِ ۲: فقط کلیدهای عددی هندلرند.
		$removed   = 0;
		$remaining = 0;
		foreach ( $handlers as $key => $handler ) {
			if ( ! is_numeric( $key ) || ! is_array( $handler ) ) {
				continue; // گزینهٔ مسیر یا مقدارِ نامعتبر — هندلر نیست.
			}

			$problems = sa_rest_guard_handler_problems( $handler );
			if ( ! $problems ) {
				$remaining++;
				continue;
			}

			sa_rest_guard_collect( $blocked, $route, $handler, $problems );
			unset( $endpoints[ $route ][ $key ] );
			$removed++;
		}

		// اگر هیچ هندلرِ سالمی نماند، خودِ مسیر هم برداشته می‌شود.
		if ( $removed > 0 && 0 === $remaining ) {
			unset( $endpoints[ $route ] );
		}
	}

	sa_rest_guard_store( $blocked );
	return $endpoints;
}
add_filter( 'rest_endpoints', 'sa_rest_guard_filter', 999 );

/**
 * ثبتِ گزارشِ مسیرهای بی‌اثرشده (و پاک‌کردنش وقتی مشکلی نماند).
 *
 * @param array<int,array<string,string>> $blocked مواردِ حذف‌شده.
 * @return void
 */
function sa_rest_guard_store( $blocked ) {
	if ( empty( $blocked ) ) {
		delete_option( 'sa_rest_guard_last' );
		return;
	}

	update_option(
		'sa_rest_guard_last',
		array(
			'format'  => SA_REST_GUARD_REPORT_FORMAT,
			'time'    => time(),
			'blocked' => array_values( $blocked ),
		),
		false
	);

	// یک خطِ لاگ برای هر مسیر، حداکثر یک‌بار در ساعت (تا لاگ پر نشود).
	foreach ( $blocked as $row ) {
		$key = 'sa_rest_guard_log_' . md5( $row['route'] . '|' . $row['kind'] . '|' . $row['callback'] );
		if ( get_transient( $key ) ) {
			continue;
		}
		set_transient( $key, 1, HOUR_IN_SECONDS );
		if ( function_exists( 'error_log' ) ) {
			error_log( '[SarzaminAryan] REST route blocked (invalid callback): ' . $row['route'] . ' [' . $row['method'] . '] ' . $row['kind'] . '=' . $row['callback'] . ' @ ' . $row['file'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

/**
 * ثبتِ تعارضِ نسخه (مثلاً وقتی ماژول «شهر من» از یک افزونهٔ قدیمی می‌آید).
 *
 * @param string              $key  کلیدِ تعارض.
 * @param array<string,mixed> $data اطلاعاتِ تعارض.
 * @return void
 */
function sa_system_note_conflict( $key, $data ) {
	$conflicts = get_option( 'sa_system_conflicts' );
	$conflicts = is_array( $conflicts ) ? $conflicts : array();
	$conflicts[ (string) $key ] = array(
		'title'  => isset( $data['title'] ) ? (string) $data['title'] : '',
		'file'   => isset( $data['file'] ) ? (string) $data['file'] : '',
		'extra'  => isset( $data['extra'] ) ? (string) $data['extra'] : '',
		'advice' => isset( $data['advice'] ) ? (string) $data['advice'] : '',
		'time'   => time(),
	);
	update_option( 'sa_system_conflicts', $conflicts, false );
}

/**
 * اعلامِ پیشخوان: مسیرهای بی‌اثرشده و تعارض‌ها.
 *
 * @return void
 */
function sa_system_notices() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$last      = get_option( 'sa_rest_guard_last' );
	$conflicts = get_option( 'sa_system_conflicts' );
	$conflicts = is_array( $conflicts ) ? $conflicts : array();

	/*
	 * گزارشِ قالبِ قدیمی (نسخهٔ ۲.۱۱.۴۳) ممکن است گزینه‌های مسیرِ هسته را «کالبکِ
	 * نامعتبر» شمرده باشد؛ چنین گزارشی دور ریخته می‌شود تا پیامِ کاذب در پیشخوان نماند.
	 */
	if ( is_array( $last ) && ( ! isset( $last['format'] ) || SA_REST_GUARD_REPORT_FORMAT !== (int) $last['format'] ) ) {
		delete_option( 'sa_rest_guard_last' );
		$last = false;
	}

	if ( is_array( $last ) && ! empty( $last['blocked'] ) ) {
		$fa = function_exists( 'sa_fa_digits' ) ? 'sa_fa_digits' : 'strval';
		echo '<div class="notice notice-error"><p><strong>محافظِ سیستمِ سرزمین آریان:</strong> ';
		echo esc_html( $fa( (string) count( $last['blocked'] ) ) ) . ' مسیرِ REST با کالبکِ نامعتبر شناسایی و <strong>بی‌اثر</strong> شد. ';
		echo 'همین کالبک‌ها قبلاً خطای کشندهٔ ۵۰۰ می‌ساختند («cannot access private method …»). از این پس آن مسیرها فقط پاسخِ «مسیر یافت نشد» می‌دهند و سایت کرش نمی‌کند.</p><ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( $last['blocked'] as $row ) {
			echo '<li><code>' . esc_html( $row['route'] ) . '</code>';
			if ( '' !== (string) $row['method'] ) {
				echo ' <span class="description">[' . esc_html( $row['method'] ) . ']</span>';
			}
			echo ' — ' . esc_html( $row['kind'] ) . ': <code>' . esc_html( $row['callback'] ) . '</code>';
			if ( '' !== (string) $row['file'] ) {
				echo ' <span class="description">در ' . esc_html( $row['file'] ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul><p>کدِ قالب هیچ متدِ خصوصی‌ای را به‌عنوان کالبکِ REST به کار نمی‌برد؛ منبعِ این مسیرها یک افزونه یا نسخهٔ قدیمیِ جداگانه است. ';
		echo 'همان افزونه را غیرفعال یا به‌روز کنید (نشانیِ فایل در فهرستِ بالا آمده است). ';
		echo 'تنها «هندلر»های REST بررسی می‌شوند؛ گزینه‌های مسیر مانند <code>schema</code> و <code>allow_batch</code> دست‌نخورده می‌مانند و مسیرهای هستهٔ وردپرس در این فهرست نمی‌آیند.</p></div>';
	}

	if ( $conflicts ) {
		echo '<div class="notice notice-warning"><p><strong>سرزمین آریان — تعارضِ نسخه:</strong></p><ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( $conflicts as $row ) {
			$line = (string) $row['title'];
			if ( '' !== (string) $row['extra'] ) {
				$line .= ' — ' . $row['extra'];
			}
			if ( '' !== (string) $row['file'] ) {
				$line .= ' (' . $row['file'] . ')';
			}
			echo '<li>' . esc_html( $line );
			if ( '' !== (string) $row['advice'] ) {
				echo '<br /><span class="description">' . esc_html( $row['advice'] ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}
}
add_action( 'admin_notices', 'sa_system_notices' );
