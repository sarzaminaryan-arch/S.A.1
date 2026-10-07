<?php
/**
 * تغییر مسیرِ ۳۰۱ صفحه‌های تکراری + بیرون‌گذاشتنِ آن‌ها از فهرست‌ها.
 *
 * مالک برای صفحه‌هایی که «یک شهرستان» تشخیص داده شده‌اند یکی را به‌عنوانِ مقصد
 * تأیید کرده است (`data/redirects.php`). این ماژول:
 *
 *   ۱) درخواستِ مسیرِ قدیمی را با ۳۰۱ به مقصد می‌فرستد (بدونِ ویرایشِ محتوا)،
 *   ۲) صفحهٔ قدیمی را از آرشیوِ شهرستان‌ها و نقشهٔ سایت بیرون می‌گذارد،
 *   ۳) به موتورِ لینک‌سازی می‌گوید به مسیرهای قدیمی لینک نسازد.
 *
 * هیچ محتوایی منتشر/حذف نمی‌شود؛ حذفِ نهاییِ صفحهٔ تکراری تصمیمِ مالک است.
 *
 * @package SarzaminAryan
 */

/**
 * مسیرِ فایلِ دادهٔ نقشه (سازگار با محیط‌هایی که ثابتِ SA_CHILD_DIR را تعریف نمی‌کنند).
 *
 * @return string مسیرِ خواندنی یا رشتهٔ خالی.
 */
function sa_redirect_data_file() {
	$candidates = array();
	if ( defined( 'SA_CHILD_DIR' ) ) {
		$candidates[] = SA_CHILD_DIR . 'data/redirects.php';
	}
	if ( function_exists( 'get_stylesheet_directory' ) ) {
		$candidates[] = get_stylesheet_directory() . '/data/redirects.php';
	}
	$candidates[] = dirname( __DIR__ ) . '/data/redirects.php';

	foreach ( $candidates as $path ) {
		if ( is_readable( $path ) ) {
			return (string) $path;
		}
	}
	return '';
}

/**
 * نقشهٔ تغییر مسیر: مسیرِ نرمال‌شدهٔ قدیمی => مسیرِ نرمال‌شدهٔ مقصد.
 *
 * @return array<string,string>
 */
function sa_redirect_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}

	$file = sa_redirect_data_file();
	$rows = '' !== $file ? (array) require $file : array();
	$map  = array();

	foreach ( $rows as $from => $to ) {
		$from = sa_redirect_normalize_path( $from );
		$to   = sa_redirect_normalize_path( $to );
		if ( '' === $from || '' === $to || $from === $to ) {
			continue;
		}
		// زنجیرهٔ تغییر مسیر ممنوع: مقصد نباید خودش یک مسیرِ مبدأ باشد.
		if ( isset( $rows[ $to ] ) || isset( $rows[ $to . '/' ] ) ) {
			continue;
		}
		$map[ $from ] = $to;
	}

	/**
	 * فیلترِ نقشهٔ تغییر مسیر.
	 *
	 * @param array<string,string> $map مسیرِ قدیمی => مسیرِ مقصد.
	 */
	return $map = (array) apply_filters( 'sa_redirect_map', $map );
}

/**
 * نرمال‌سازیِ مسیر: فقط مسیر، با اسلشِ ابتدا و انتها.
 *
 * @param string $path مسیر یا URL.
 * @return string
 */
function sa_redirect_normalize_path( $path ) {
	$path = trim( (string) $path );
	if ( '' === $path ) {
		return '';
	}
	$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $path ) : parse_url( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	$clean = is_array( $parts ) && isset( $parts['path'] ) ? (string) $parts['path'] : $path;
	$clean = '/' . ltrim( $clean, '/' );
	if ( substr( $clean, -1 ) !== '/' ) {
		$clean .= '/';
	}
	return $clean;
}

/**
 * مسیرِ درخواستِ جاری (بدونِ کوئری و بدونِ پیشوندِ نصب).
 *
 * @return string
 */
function sa_redirect_request_path() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' === $uri ) {
		return '';
	}
	$path = (string) html_entity_decode( sa_redirect_normalize_path( $uri ), ENT_QUOTES, 'UTF-8' );

	// اگر وردپرس در زیرشاخه نصب است، پیشوندِ نصب را برمی‌داریم.
	$home = function_exists( 'home_url' ) ? (string) home_url( '/' ) : '';
	if ( '' !== $home ) {
		$home_path = sa_redirect_normalize_path( $home );
		if ( '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = '/' . ltrim( substr( $path, strlen( $home_path ) ), '/' );
		}
	}

	return $path;
}

/**
 * مقصدِ نرمال‌شده برای یک مسیر، یا رشتهٔ خالی.
 *
 * @param string $path مسیرِ درخواست.
 * @return string
 */
function sa_redirect_target_for_path( $path ) {
	$path = sa_redirect_normalize_path( $path );
	if ( '' === $path ) {
		return '';
	}
	$map = sa_redirect_map();
	return isset( $map[ $path ] ) ? (string) $map[ $path ] : '';
}

/**
 * آیا این نامک، نامکِ یک صفحهٔ قدیمی (مبدأِ تغییر مسیر) است؟
 *
 * @param string $slug      نامک.
 * @param string $post_type نوعِ نوشته دارایی‌ها.
 * @return bool
 */
function sa_redirect_is_old_slug( $slug, $post_type = 'city' ) {
	$slug = trim( (string) $slug );
	if ( '' === $slug ) {
		return false;
	}
	$path = '/' . trim( (string) $post_type, '/' ) . '/' . $slug . '/';
	return '' !== sa_redirect_target_for_path( $path );
}

/**
 * شناسهٔ نوشته‌های مبدأ (برای بیرون‌گذاشتن از آرشیو و نقشهٔ سایت).
 *
 * @param string $post_type نوعِ نوشته.
 * @return int[]
 */
function sa_redirect_source_ids( $post_type = 'city' ) {
	static $cache = array();
	$post_type    = (string) $post_type;
	if ( isset( $cache[ $post_type ] ) ) {
		return $cache[ $post_type ];
	}

	$ids = array();
	if ( function_exists( 'get_page_by_path' ) ) {
		foreach ( array_keys( sa_redirect_map() ) as $from ) {
			$parts = array_values( array_filter( explode( '/', (string) $from ) ) );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$slug = (string) end( $parts );
			$type = (string) reset( $parts );
			if ( $type !== $post_type ) {
				continue;
			}
			$post = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $post && isset( $post->ID ) ) {
				$ids[] = (int) $post->ID;
			}
		}
	}

	$cache[ $post_type ] = array_values( array_unique( $ids ) );
	return $cache[ $post_type ];
}

/**
 * فرستادنِ ۳۰۱ برای مسیرهای قدیمی.
 *
 * فقط درخواست‌های GET/HEAD، بیرون از پیشخوان/پیش‌نمایش، و بدونِ زنجیره.
 *
 * @return void
 */
function sa_redirect_handle_request() {
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return;
	}
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
		return;
	}
	$method = strtoupper( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	if ( function_exists( 'is_preview' ) && is_preview() ) {
		return;
	}

	$target = sa_redirect_target_for_path( sa_redirect_request_path() );
	if ( '' === $target ) {
		return;
	}

	$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' !== $query ) {
		$target .= '?' . $query;
	}

	if ( function_exists( 'wp_safe_redirect' ) ) {
		wp_safe_redirect( $target, 301, 'Sarzamin Aryan' );
		exit;
	}
}
add_action( 'template_redirect', 'sa_redirect_handle_request', 1 );

/**
 * بیرون‌گذاشتنِ صفحه‌های قدیمی از آرشیوِ نوعِ نوشته.
 *
 * @param WP_Query $query کوئریِ وردپرس.
 * @return void
 */
function sa_redirect_archive_exclusion( $query ) {
	if ( function_exists( 'is_admin' ) && is_admin() ) {
		return;
	}
	if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() ) {
		return;
	}
	if ( ! method_exists( $query, 'is_post_type_archive' ) || ! $query->is_post_type_archive( 'city' ) ) {
		return;
	}

	$ids = sa_redirect_source_ids( 'city' );
	if ( ! $ids ) {
		return;
	}
	if ( ! method_exists( $query, 'set' ) || ! method_exists( $query, 'get' ) ) {
		return;
	}

	$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in', array() ), $ids ) );
}
add_action( 'pre_get_posts', 'sa_redirect_archive_exclusion' );

/**
 * بیرون‌گذاشتنِ صفحه‌های قدیمی از نقشهٔ سایتِ وردپرس.
 *
 * @param array  $args      آرگومان‌های کوئریِ نقشهٔ سایت.
 * @param string $post_type نوعِ نوشته.
 * @return array
 */
function sa_redirect_sitemap_exclusion( $args, $post_type ) {
	if ( 'city' !== (string) $post_type ) {
		return $args;
	}
	$ids = sa_redirect_source_ids( 'city' );
	if ( ! $ids ) {
		return $args;
	}
	$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids );
	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'sa_redirect_sitemap_exclusion', 10, 2 );
