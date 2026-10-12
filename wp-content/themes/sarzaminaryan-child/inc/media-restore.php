<?php
/**
 * «بازیابی تصاویر» — شناسایی و بازگرداندن فایل‌های تصویریِ گمشده (v2.15.0).
 *
 * ماجرا: فایل‌های پوشهٔ رسانهٔ سایت (uploads) با یک اشتباه پاک شده‌اند؛ هم
 * تصویرهای داخل متن و هم تصویرهای شاخص به همان فایل‌ها اشاره می‌کنند، پس
 * صفحه‌ها تصویر شکسته نشان می‌دهند. این ماژول سه کار می‌کند:
 *
 * 1) «گزارش» — متن همهٔ نوشته‌ها و فهرست رسانه‌ها خوانده می‌شود و می‌گوید کدام
 *    فایل تصویری روی سرور نیست، در کدام نوشته‌ها استفاده شده و از کجا می‌شود
 *    برگرداند.
 * 2) «بازگردانی از منبع» — فایل با همان نام و همان مسیرِ گمشده نوشته می‌شود
 *    (پس هیچ آدرس یا srcsetی در محتوا خراب نمی‌شود) از نخستین منبعی که جواب
 *    بدهد: نسخهٔ سالمِ مانده در پوشهٔ رسانه → نسخهٔ همراه قالب (assets/img/**)
 *    → نشانی منبعِ ثبت‌شده (_sa_source_url یا رکوردهای data/ready-*.php).
 * 3) «بازگردانی از پشتیبان» — اگر نسخهٔ پشتیبان (ZIP) داشتید، همان فایل‌های
 *    گمشده از داخل آن برداشته می‌شود.
 *
 * قاعده‌های ایمنی: هر عمل دو مرحله دارد (پیش‌نمایش/اعمال) با nonce و سطح
 * دسترسی؛ روی فایل موجود هرگز نوشته نمی‌شود؛ هر بایت دریافتی پیش از ذخیره با
 * امضای فایل تصویری بررسی می‌شود تا صفحهٔ خطای HTML با نام .jpg ذخیره نشود؛
 * دامنهٔ دریافت از فهرست مجاز (منابع ثبت‌شدهٔ خودِ سایت) فراتر نمی‌رود.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * زیرمنوی «بازیابی تصاویر» زیر منوی سرزمین آریان.
 */
function sa_media_restore_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'بازیابی تصاویر',
		'— بازیابی تصاویر',
		'edit_posts',
		'sa-media-restore',
		'sa_media_restore_page'
	);
}
add_action( 'admin_menu', 'sa_media_restore_menu', 24 );

/**
 * پوشهٔ رسانه (uploads) — مسیر و نشانی.
 *
 * @return array{basedir:string,baseurl:string}
 */
function sa_media_upload_base() {
	$dir = wp_get_upload_dir();
	return array(
		'basedir' => isset( $dir['basedir'] ) ? wp_normalize_path( (string) $dir['basedir'] ) : '',
		'baseurl' => isset( $dir['baseurl'] ) ? (string) $dir['baseurl'] : '',
	);
}

/**
 * نگاشت یک نشانی داخلی به مسیر فایل روی سرور ('' = بیرونی/ناشناخته).
 *
 * نشانی‌های پوشهٔ رسانه، پوشهٔ قالب فرزند و قالب مادر پشتیبانی می‌شوند؛
 * دامنه‌ای جز دامنهٔ خودِ سایت «بیرونی» شمرده می‌شود و هرگز بازنویسی نمی‌شود.
 *
 * @param string $url نشانی.
 * @return string مسیر مطلق یا ''.
 */
function sa_media_local_path( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, '#' ) ) {
		return '';
	}
	$clean = (string) preg_replace( '/[?#].*$/', '', $url );
	if ( 0 === strpos( $clean, '//' ) ) {
		$clean = ( is_ssl() ? 'https:' : 'http:' ) . $clean;
	} elseif ( 0 === strpos( $clean, '/' ) ) {
		$clean = home_url( $clean );
	}
	$parts = wp_parse_url( $clean );
	if ( ! is_array( $parts ) || empty( $parts['path'] ) ) {
		return '';
	}
	$host      = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
	$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( '' !== $host && $host !== $site_host ) {
		return ''; // فایل بیرونی: روی این سرور قابل قضاوت نیست.
	}

	$uploads = sa_media_upload_base();
	$bases   = array(
		array(
			'basedir' => $uploads['basedir'],
			'baseurl' => $uploads['baseurl'],
		),
	);
	if ( defined( 'SA_CHILD_DIR' ) && defined( 'SA_CHILD_URI' ) ) {
		$bases[] = array(
			'basedir' => rtrim( wp_normalize_path( SA_CHILD_DIR ), '/' ),
			'baseurl' => rtrim( (string) SA_CHILD_URI, '/' ),
		);
	}
	$bases[] = array(
		'basedir' => rtrim( wp_normalize_path( (string) get_template_directory() ), '/' ),
		'baseurl' => rtrim( (string) get_template_directory_uri(), '/' ),
	);

	foreach ( $bases as $base ) {
		if ( '' === $base['basedir'] || '' === $base['baseurl'] ) {
			continue;
		}
		$base_path = (string) wp_parse_url( $base['baseurl'], PHP_URL_PATH );
		if ( '' === $base_path ) {
			continue;
		}
		$base_path = '/' . trim( $base_path, '/' );
		if ( 0 !== strpos( $parts['path'], $base_path . '/' ) ) {
			continue;
		}
		$relative = ltrim( substr( $parts['path'], strlen( $base_path ) ), '/' );
		$relative = rawurldecode( $relative );
		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			continue;
		}
		return wp_normalize_path( $base['basedir'] . '/' . $relative );
	}
	return '';
}

/**
 * نگاشت مسیر فایل داخلی به نشانی ('' = بیرون از ریشه‌های شناخته‌شده).
 *
 * @param string $path مسیر مطلق.
 * @return string
 */
function sa_media_path_url( $path ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path ) {
		return '';
	}
	$uploads = sa_media_upload_base();
	$bases   = array(
		array(
			'basedir' => rtrim( $uploads['basedir'], '/' ),
			'baseurl' => rtrim( $uploads['baseurl'], '/' ),
		),
	);
	if ( defined( 'SA_CHILD_DIR' ) && defined( 'SA_CHILD_URI' ) ) {
		$bases[] = array(
			'basedir' => rtrim( wp_normalize_path( SA_CHILD_DIR ), '/' ),
			'baseurl' => rtrim( (string) SA_CHILD_URI, '/' ),
		);
	}
	foreach ( $bases as $base ) {
		if ( '' === $base['basedir'] || '' === $base['baseurl'] ) {
			continue;
		}
		if ( 0 === strpos( $path, $base['basedir'] . '/' ) ) {
			$relative = ltrim( substr( $path, strlen( $base['basedir'] ) ), '/' );
			$parts    = array_map( 'rawurlencode', explode( '/', $relative ) );
			return $base['baseurl'] . '/' . implode( '/', $parts );
		}
	}
	return '';
}

/**
 * همهٔ نشانی‌های تصویریِ یک تکه HTML (src، data-src، srcset و background-image).
 *
 * @param string $html متن.
 * @return string[] نشانی‌های یکتا (به ترتیب ظهور).
 */
function sa_media_extract_image_urls( $html ) {
	$html = (string) $html;
	if ( '' === $html || false === stripos( $html, '<img' ) && false === stripos( $html, 'background' ) ) {
		return array();
	}
	$urls = array();
	if ( preg_match_all( '/<img\b[^>]*>/i', $html, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			foreach ( array( 'src', 'data-src', 'data-lazy-src', 'data-original' ) as $attr ) {
				if ( preg_match( '/\b' . preg_quote( $attr, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
					$urls[] = html_entity_decode( trim( $m[2] ), ENT_QUOTES, 'UTF-8' );
				}
			}
			if ( preg_match( '/\bsrcset\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ) {
				foreach ( explode( ',', $m[2] ) as $candidate ) {
					$candidate = trim( (string) preg_split( '/\s+/', trim( $candidate ) )[0] );
					if ( '' !== $candidate ) {
						$urls[] = html_entity_decode( $candidate, ENT_QUOTES, 'UTF-8' );
					}
				}
			}
		}
	}
	if ( preg_match_all( '/background(?:-image)?\s*:\s*url\(\s*(["\']?)([^)"\']+)\1\s*\)/i', $html, $bm ) ) {
		foreach ( $bm[2] as $candidate ) {
			$urls[] = html_entity_decode( trim( $candidate ), ENT_QUOTES, 'UTF-8' );
		}
	}
	$urls = array_filter(
		array_map(
			function ( $url ) {
				return trim( (string) $url );
			},
			$urls
		),
		function ( $url ) {
			return '' !== $url && 0 !== strpos( $url, 'data:' );
		}
	);
	return array_values( array_unique( $urls ) );
}

/**
 * مسیر فایلِ یک پیوست رسانه ('' = بدون مسیر ثبت‌شده).
 *
 * @param int $attachment_id شناسهٔ پیوست.
 * @return string
 */
function sa_media_attachment_path( $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return '';
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file ) {
		return '';
	}
	return wp_normalize_path( (string) $file );
}

/**
 * آیا فایلِ این پیوست (و هیچ‌یک از اندازه‌هایش) روی سرور نیست؟
 *
 * @param int $attachment_id شناسهٔ پیوست.
 * @return bool
 */
function sa_media_attachment_missing( $attachment_id ) {
	$path = sa_media_attachment_path( $attachment_id );
	if ( '' === $path ) {
		return false; // مسیری ثبت نشده؛ قضاوت ممکن نیست.
	}
	if ( file_exists( $path ) ) {
		return false;
	}
	return '' === sa_media_find_variant( $path );
}

/**
 * یک نسخهٔ سالم از همان تصویر در همان پوشه (اندازهٔ دیگر یا `-scaled`).
 *
 * @param string $path مسیر فایل اصلی.
 * @return string مسیر نسخهٔ موجود یا ''.
 */
function sa_media_find_variant( $path ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path ) {
		return '';
	}
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) ) {
		return '';
	}
	$base  = basename( $path );
	$ext   = pathinfo( $base, PATHINFO_EXTENSION );
	$stem  = pathinfo( $base, PATHINFO_FILENAME );
	$suf   = '' !== $ext ? '.' . $ext : '';
	$found = array();
	foreach ( (array) glob( $dir . '/' . $stem . '-*' . $suf ) as $candidate ) {
		if ( is_file( $candidate ) ) {
			$found[] = $candidate;
		}
	}
	if ( ! $found ) {
		return '';
	}
	$best     = '';
	$best_rank = -1;
	foreach ( array_slice( $found, 0, 40 ) as $candidate ) {
		$name = basename( $candidate );
		if ( false !== strpos( $name, '-scaled' ) ) {
			$rank = 9999999;
		} elseif ( preg_match( '/-(\d+)x(\d+)\./', $name, $m ) ) {
			$rank = (int) $m[1] * 100000 + (int) $m[2];
		} else {
			$rank = 0;
		}
		if ( $rank > $best_rank ) {
			$best_rank = $rank;
			$best      = $candidate;
		}
	}
	return wp_normalize_path( $best );
}

/**
 * جست‌وجوی نسخه‌ای از یک نام فایل در جاهای دیگر پوشهٔ رسانه (YYYY/MM و یک رده بالاتر).
 *
 * @param string $basename نام فایل.
 * @return string مسیر نسخهٔ موجود یا ''.
 */
function sa_media_find_copy( $basename ) {
	$basename = basename( (string) $basename );
	if ( '' === $basename || false !== strpos( $basename, '..' ) ) {
		return '';
	}
	$uploads = sa_media_upload_base();
	if ( '' === $uploads['basedir'] || ! is_dir( $uploads['basedir'] ) ) {
		return '';
	}
	$patterns = array(
		$uploads['basedir'] . '/*/*/' . $basename,
		$uploads['basedir'] . '/*/' . $basename,
		$uploads['basedir'] . '/' . $basename,
	);
	foreach ( $patterns as $pattern ) {
		foreach ( (array) glob( $pattern ) as $candidate ) {
			if ( is_file( $candidate ) ) {
				return wp_normalize_path( $candidate );
			}
		}
	}
	return '';
}

/**
 * نمایهٔ فایل‌های تصویریِ همراه قالب: نام فایل (کوچک) → مسیر مطلق.
 *
 * @return array<string,string>
 */
function sa_media_theme_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}
	$index = array();
	$roots = array();
	if ( defined( 'SA_CHILD_DIR' ) ) {
		$roots[] = rtrim( wp_normalize_path( SA_CHILD_DIR ), '/' ) . '/assets/img';
	}
	$roots[] = rtrim( wp_normalize_path( (string) get_template_directory() ), '/' ) . '/assets/img';
	foreach ( $roots as $root ) {
		if ( ! is_dir( $root ) ) {
			continue;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$ext = strtolower( $file->getExtension() );
			if ( ! in_array( $ext, array( 'webp', 'jpg', 'jpeg', 'png', 'gif', 'avif' ), true ) ) {
				continue;
			}
			$name = strtolower( $file->getFilename() );
			if ( ! isset( $index[ $name ] ) ) {
				$index[ $name ] = wp_normalize_path( $file->getPathname() );
			}
		}
	}
	return $index;
}

/**
 * نمایهٔ منابعِ ثبت‌شدهٔ مقاله‌های آماده: نام فایل (کوچک) → منبع بازیابی.
 *
 * هر قلم گالری در data/ready-articles.php (و راهنماها در data/ready-guides.php)
 * هم «نام فایل» دارد، هم نشانی منبع و هم در بعضی رکوردها نسخهٔ همراه قالب؛
 * پس می‌شود فایل پاک‌شده را از همان‌جا برگرداند.
 *
 * @return array<string,array<string,string>>
 */
function sa_media_source_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}
	$index = array();
	if ( ! function_exists( 'sa_ready_articles_data' ) ) {
		return $index;
	}
	foreach ( (array) sa_ready_articles_data() as $article ) {
		if ( ! is_array( $article ) ) {
			continue;
		}
		$title = isset( $article['title'] ) ? (string) $article['title'] : '';
		$items = array();
		foreach ( (array) ( isset( $article['gallery'] ) ? $article['gallery'] : array() ) as $item ) {
			if ( is_array( $item ) ) {
				$items[] = array( $item, 'gallery' );
			}
		}
		if ( ! empty( $article['cover'] ) && is_array( $article['cover'] ) ) {
			$items[] = array( $article['cover'], 'cover' );
		}
		foreach ( $items as $pair ) {
			$row = sa_media_source_row( $pair[0], $title, $pair[1] );
			if ( ! $row ) {
				continue;
			}
			foreach ( $row['keys'] as $key ) {
				if ( '' !== $key && ! isset( $index[ $key ] ) ) {
					$index[ $key ] = $row;
				}
			}
		}
	}
	return $index;
}

/**
 * تبدیل یک قلم رسانه‌ایِ رکورد آماده به سطر منبع بازیابی.
 *
 * @param array<string,mixed> $item  قلم (file/url/theme_img/…).
 * @param string              $title عنوان مقاله.
 * @param string              $kind  gallery|cover.
 * @return array<string,mixed>|null
 */
function sa_media_source_row( $item, $title = '', $kind = 'gallery' ) {
	if ( ! is_array( $item ) ) {
		return null;
	}
	$file  = isset( $item['file'] ) ? ltrim( (string) $item['file'], '/' ) : '';
	$url   = isset( $item['url'] ) ? (string) $item['url'] : '';
	$theme = '';
	if ( 'cover' === $kind ) {
		$theme = $file;
	} elseif ( ! empty( $item['theme_img'] ) ) {
		$theme = 'articles/' . ltrim( (string) $item['theme_img'], '/' );
	}
	$keys = array();
	if ( '' !== $file ) {
		$keys[] = strtolower( basename( $file ) );
	}
	if ( '' !== $theme ) {
		$keys[] = strtolower( basename( $theme ) );
	}
	if ( '' !== $url ) {
		$url_path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name     = rawurldecode( basename( $url_path ) );
		if ( '' !== $name ) {
			$keys[] = strtolower( $name );
			// نامی که وردپرس هنگام ورود از نشانی می‌سازد (media_sideload_image).
			$keys[] = strtolower( sanitize_file_name( $name ) );
		}
	}
	$keys = array_values( array_unique( array_filter( $keys ) ) );
	if ( ! $keys ) {
		return null;
	}
	return array(
		'keys'  => $keys,
		'file'  => $file,
		'url'   => $url,
		'theme' => $theme,
		'title' => $title,
		'kind'  => $kind,
	);
}

/**
 * فهرست دامنه‌هایی که مجاز به دریافت از آن‌ها هستیم (ثبت‌شده در داده‌های خود سایت).
 *
 * @return string[]
 */
function sa_media_source_hosts() {
	$hosts = array( 'upload.wikimedia.org', 'thumb.wikimedia.org', 'commons.wikimedia.org' );
	foreach ( sa_media_source_index() as $row ) {
		if ( empty( $row['url'] ) ) {
			continue;
		}
		$host = strtolower( (string) wp_parse_url( (string) $row['url'], PHP_URL_HOST ) );
		if ( '' !== $host ) {
			$hosts[] = $host;
		}
	}
	return array_values( array_unique( array_filter( $hosts ) ) );
}

/**
 * بهترین منبع بازیابی برای یک مسیر گمشده.
 *
 * @param string $path          مسیر مورد انتظار.
 * @param int    $attachment_id شناسهٔ پیوست (۰ = نامعلوم).
 * @return array{type:string,value:string,label:string}
 */
function sa_media_suggest_source( $path, $attachment_id = 0 ) {
	$path     = wp_normalize_path( (string) $path );
	$basename = strtolower( basename( $path ) );
	$uploads  = sa_media_upload_base();

	// ۱) نسخهٔ سالمِ مانده در همان پوشه یا پوشه‌های دیگر رسانه.
	$variant = sa_media_find_variant( $path );
	if ( '' !== $variant ) {
		return array(
			'type'  => 'local',
			'value' => $variant,
			'label' => 'اندازهٔ دیگرِ همان تصویر در پوشهٔ رسانه',
		);
	}
	$copy = sa_media_find_copy( $basename );
	if ( '' !== $copy ) {
		return array(
			'type'  => 'local',
			'value' => $copy,
			'label' => 'نسخهٔ موجود در پوشهٔ رسانه',
		);
	}

	// ۲) نسخهٔ همراه قالب (اینفوگرافیک‌های مالک و تصویرهای داخل متنِ همراه قالب).
	if ( '' !== $basename && $uploads['basedir'] !== '' ) {
		$theme = sa_media_theme_index();
		if ( isset( $theme[ $basename ] ) ) {
			return array(
				'type'  => 'theme',
				'value' => $theme[ $basename ],
				'label' => 'فایل همراه قالب',
			);
		}
	}

	// ۳) نشانی منبعِ ثبت‌شده روی پیوست یا در رکوردهای مقالهٔ آماده.
	$source = sa_media_source_index();
	if ( $attachment_id ) {
		$recorded = trim( (string) get_post_meta( absint( $attachment_id ), '_sa_source_url', true ) );
		if ( '' !== $recorded ) {
			return array(
				'type'  => 'url',
				'value' => $recorded,
				'label' => 'نشانی منبع ثبت‌شده روی پیوست',
			);
		}
	}
	if ( isset( $source[ $basename ]['url'] ) && '' !== $source[ $basename ]['url'] ) {
		return array(
			'type'  => 'url',
			'value' => (string) $source[ $basename ]['url'],
			'label' => 'نشانی منبع در رکورد «' . ( isset( $source[ $basename ]['title'] ) ? (string) $source[ $basename ]['title'] : $basename ) . '»',
		);
	}
	return array(
		'type'  => 'none',
		'value' => '',
		'label' => 'منبع خودکار ندارد — نیازمند پشتیبان',
	);
}

/**
 * پاک‌کردن گزارشِ ذخیره‌شده (پس از هر تغییر محتوا/رسانه).
 */
function sa_media_flush_scan() {
	delete_transient( 'sa_media_scan' );
}
add_action( 'deleted_post', 'sa_media_flush_scan' );
add_action( 'add_attachment', 'sa_media_flush_scan' );
add_action( 'edit_attachment', 'sa_media_flush_scan' );

/**
 * گزارشِ فایل‌های گمشده (با کش ۱۵ دقیقه‌ای).
 *
 * @param bool $force نادیده‌گرفتن کش.
 * @return array<string,mixed>
 */
function sa_media_scan( $force = false ) {
	if ( ! $force ) {
		$cached = get_transient( 'sa_media_scan' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$missing = array(); // مسیر → سطر.
	$totals  = array(
		'posts'              => 0,
		'images'             => 0,
		'present'            => 0,
		'external'           => 0,
		'missing'            => 0,
		'attachments'        => 0,
		'attachment_missing' => 0,
		'fixable'            => 0,
		'needs_backup'       => 0,
		'pages'              => 0,
		'per_page'           => 200,
		'truncated'          => false,
	);
	$cap   = (int) apply_filters( 'sa_media_scan_cap', 500 );
	$cap   = $cap > 0 ? $cap : 500;
	$paths = array(); // مسیر → شناسهٔ پیوست (نگاشت رسانه‌ها).

	// --- رسانه‌ها: کدام پیوست فایلش روی سرور نیست؟ ---
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d", '_wp_attached_file', 5000 )
	);
	// phpcs:enable
	$attachment_ids = array();
	foreach ( (array) $rows as $row ) {
		$attachment_ids[] = absint( $row->post_id );
	}
	if ( $attachment_ids ) {
		_prime_post_caches( array_values( array_unique( $attachment_ids ) ), false, false );
	}
	$uploads = sa_media_upload_base();
	foreach ( (array) $rows as $row ) {
		$attachment_id = absint( $row->post_id );
		$mime          = (string) get_post_mime_type( $attachment_id );
		if ( '' !== $mime && 0 !== strpos( $mime, 'image/' ) ) {
			continue; // فقط تصویرها موضوع این ابزارند.
		}
		$relative = ltrim( (string) $row->meta_value, '/' );
		if ( '' === $relative || '' === $uploads['basedir'] || false !== strpos( $relative, '..' ) ) {
			continue;
		}
		++$totals['attachments'];
		$path            = wp_normalize_path( $uploads['basedir'] . '/' . $relative );
		$paths[ $path ]  = $attachment_id;
		$exists          = file_exists( $path ) || '' !== sa_media_find_variant( $path );
		if ( $exists ) {
			continue;
		}
		++$totals['attachment_missing'];
		if ( ! isset( $missing[ $path ] ) ) {
			if ( count( $missing ) >= $cap ) {
				$totals['truncated'] = true;
				continue;
			}
			$missing[ $path ] = array(
				'path'       => $path,
				'url'        => sa_media_path_url( $path ),
				'basename'   => basename( $path ),
				'posts'      => array(),
				'attachment' => $attachment_id,
				'origin'     => 'attachment',
			);
		}
	}

	// --- متن نوشته‌ها: کدام تصویر داخل متن فایلش نیست؟ ---
	$types     = array_values( array_unique( array_merge( array( 'post', 'page' ), (array) sa_entity_types() ) ) );
	$per_page  = $totals['per_page'];
	$max_pages = (int) apply_filters( 'sa_media_scan_max_pages', 25 );
	$max_pages = $max_pages > 0 ? $max_pages : 25;
	$page      = 1;
	$total     = 1;
	do {
		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			)
		);
		foreach ( $query->posts as $pid ) {
			++$totals['posts'];
			$content = (string) get_post_field( 'post_content', (int) $pid );
			foreach ( sa_media_extract_image_urls( $content ) as $url ) {
				++$totals['images'];
				$path = sa_media_local_path( $url );
				if ( '' === $path ) {
					++$totals['external'];
					continue;
				}
				if ( file_exists( $path ) || '' !== sa_media_find_variant( $path ) ) {
					++$totals['present'];
					continue;
				}
				if ( ! isset( $missing[ $path ] ) ) {
					if ( count( $missing ) >= $cap ) {
						$totals['truncated'] = true;
						continue;
					}
					$missing[ $path ] = array(
						'path'       => $path,
						'url'        => $url,
						'basename'   => basename( $path ),
						'posts'      => array(),
						'attachment' => isset( $paths[ $path ] ) ? (int) $paths[ $path ] : 0,
						'origin'     => 'content',
					);
				}
				$missing[ $path ]['posts'][ (int) $pid ] = true;
			}
		}
		++$totals['pages'];
		$total = (int) $query->max_num_pages;
		++$page;
	} while ( $page <= $total && $page <= $max_pages );

	$totals['truncated'] = $totals['truncated'] || $total > $max_pages;

	// --- منبع پیشنهادی برای هر فایل گمشده ---
	foreach ( $missing as $path => $row ) {
		$source              = sa_media_suggest_source( $path, (int) $row['attachment'] );
		$missing[ $path ]['source'] = $source;
		if ( 'none' === $source['type'] ) {
			++$totals['needs_backup'];
		} else {
			++$totals['fixable'];
		}
	}

	$totals['missing'] = count( $missing );

	$out = array(
		'generated' => time(),
		'totals'    => $totals,
		'missing'   => $missing,
	);
	set_transient( 'sa_media_scan', $out, 15 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * تقسیم گزارش به «قابل بازیابی از منبع» و «نیازمند پشتیبان».
 *
 * @param array<string,mixed>|null $scan گزارش (پیش‌فرض: گزارش تازه).
 * @return array{source:array<string,array>,backup:array<string,array>}
 */
function sa_media_plan( $scan = null ) {
	$scan = is_array( $scan ) ? $scan : sa_media_scan();
	$plan = array(
		'source' => array(),
		'backup' => array(),
	);
	foreach ( (array) $scan['missing'] as $path => $row ) {
		if ( isset( $row['source']['type'] ) && 'none' !== $row['source']['type'] ) {
			$plan['source'][ $path ] = $row;
		} else {
			$plan['backup'][ $path ] = $row;
		}
	}
	return $plan;
}

/**
 * آیا این بایت‌ها یک فایل تصویری‌اند؟ (پیش از ذخیره بررسی می‌شود)
 *
 * @param string $contents محتوا.
 * @return bool
 */
function sa_media_looks_like_image( $contents ) {
	$contents = (string) $contents;
	if ( strlen( $contents ) < 64 ) {
		return false;
	}
	$sig = substr( $contents, 0, 12 );
	if ( "\xFF\xD8\xFF" === substr( $sig, 0, 3 ) ) {
		return true; // JPEG
	}
	if ( "\x89PNG\r\n\x1a\n" === substr( $sig, 0, 8 ) ) {
		return true; // PNG
	}
	if ( 'GIF8' === substr( $sig, 0, 4 ) ) {
		return true; // GIF
	}
	if ( 'RIFF' === substr( $sig, 0, 4 ) && 'WEBP' === substr( $sig, 8, 4 ) ) {
		return true; // WebP
	}
	if ( 'ftyp' === substr( $sig, 4, 4 ) ) {
		return true; // AVIF/HEIF
	}
	return false;
}

/**
 * دریافت تصویر از نشانی منبع (فقط دامنه‌های مجاز).
 *
 * @param string $url نشانی.
 * @return string|WP_Error بدنهٔ فایل یا خطا.
 */
function sa_media_fetch_url( $url ) {
	$url = esc_url_raw( (string) $url );
	if ( '' === $url ) {
		return new WP_Error( 'sa_media_url', 'نشانی منبع خالی است.' );
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	/**
	 * دامنه‌های مجاز برای دریافت تصویر.
	 *
	 * @param string[] $hosts دامنه‌ها.
	 */
	$allowed = (array) apply_filters( 'sa_media_fetch_hosts', sa_media_source_hosts() );
	if ( ! in_array( $host, array_map( 'strtolower', $allowed ), true ) ) {
		return new WP_Error( 'sa_media_host', 'دامنهٔ منبع مجاز نیست: ' . $host );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 30,
			'redirection'         => 3,
			'user-agent'          => 'SarzaminAryan/' . ( defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '1.0' ) . '; ' . home_url( '/' ),
			'limit_response_size' => 25 * MB_IN_BYTES,
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'sa_media_http', 'پاسخ منبع: ' . $code );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( strlen( $body ) < 512 ) {
		return new WP_Error( 'sa_media_empty', 'پاسخ منبع خالی یا بسیار کوچک است.' );
	}
	if ( ! sa_media_looks_like_image( $body ) ) {
		return new WP_Error( 'sa_media_type', 'پاسخ منبع یک فایل تصویری نیست.' );
	}
	return $body;
}

/**
 * نوشتن فایل در مسیر دقیق (فقط اگر فایل وجود نداشته باشد).
 *
 * @param string $path     مسیر مطلق.
 * @param string $contents محتوا.
 * @return bool
 */
function sa_media_write_file( $path, $contents ) {
	$path = wp_normalize_path( (string) $path );
	if ( '' === $path || false !== strpos( $path, '..' ) || '' === (string) $contents ) {
		return false;
	}
	if ( ! sa_media_looks_like_image( $contents ) ) {
		return false;
	}
	if ( file_exists( $path ) ) {
		return false; // روی فایل موجود نمی‌نویسیم.
	}
	$dir = dirname( $path );
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	$bytes = file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- نوشتن در مسیر دقیقِ رسانه لازم است.
	return false !== $bytes && $bytes > 0;
}

/**
 * بازسازی اندازه‌های ثبت‌شدهٔ یک پیوست پس از بازگرداندن فایلش.
 *
 * @param int    $attachment_id شناسهٔ پیوست.
 * @param string $path          مسیر فایل (پیش‌فرض: مسیر ثبت‌شده).
 * @return bool
 */
function sa_media_refresh_attachment( $attachment_id, $path = '' ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return false;
	}
	if ( '' === $path ) {
		$path = sa_media_attachment_path( $attachment_id );
	}
	if ( '' === $path || ! file_exists( $path ) ) {
		return false;
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		$includes = ABSPATH . 'wp-admin/includes/image.php';
		if ( ! is_readable( $includes ) ) {
			return false; // بیرون از وردپرس (آزمون‌ها) بازسازی اندازه‌ها انجام نمی‌شود.
		}
		require_once $includes;
	}
	$meta = wp_generate_attachment_metadata( $attachment_id, $path );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( $attachment_id, $meta );
	}
	return true;
}

/**
 * تلاش برای بازگرداندن فایلِ یک پیوست از منابع در دسترس (قالب → نشانی منبع).
 *
 * در جریان معمولِ ساخت محتوا (مقالات آماده، گالری) صدا زده می‌شود تا اگر فایلِ
 * یک پیوست پاک شده باشد، پیش از تولید خروجی برگردد.
 *
 * @param int                 $attachment_id شناسهٔ پیوست.
 * @param array<string,mixed> $item          قلم رسانه (theme_img/file/url)؛ اختیاری.
 * @return bool
 */
function sa_media_try_restore_attachment( $attachment_id, $item = array() ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id || ! sa_media_attachment_missing( $attachment_id ) ) {
		return false;
	}
	$item = is_array( $item ) ? $item : array();
	$path = sa_media_attachment_path( $attachment_id );
	$theme = sa_media_theme_index();
	$candidates = array();
	if ( ! empty( $item['theme_img'] ) ) {
		$relative     = ltrim( (string) $item['theme_img'], '/' );
		$candidates[] = SA_CHILD_DIR . 'assets/img/articles/' . $relative;
		$candidates[] = SA_CHILD_DIR . 'assets/img/' . $relative;
	}
	if ( '' !== $path ) {
		$name = strtolower( basename( $path ) );
		if ( isset( $theme[ $name ] ) ) {
			$candidates[] = $theme[ $name ];
		}
	}
	if ( ! empty( $item['file'] ) ) {
		$name = strtolower( basename( (string) $item['file'] ) );
		if ( isset( $theme[ $name ] ) ) {
			$candidates[] = $theme[ $name ];
		}
	}
	foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
		if ( is_readable( (string) $candidate ) && sa_media_restore_from_file( $attachment_id, (string) $candidate ) ) {
			return true;
		}
	}

	$url = trim( (string) get_post_meta( $attachment_id, '_sa_source_url', true ) );
	if ( '' === $url && ! empty( $item['url'] ) ) {
		$url = (string) $item['url'];
	}
	if ( '' === $url || '' === $path || file_exists( $path ) ) {
		return false;
	}
	$body = sa_media_fetch_url( $url );
	if ( is_wp_error( $body ) ) {
		return false;
	}
	if ( ! sa_media_write_file( $path, (string) $body ) ) {
		return false;
	}
	sa_media_refresh_attachment( $attachment_id, $path );
	sa_media_flush_scan();
	return true;
}

/**
 * بازگرداندن یک مسیرِ گمشده از یک فایل سالم (نسخهٔ قالب یا پشتیبان).
 *
 * @param array<string,mixed> $row         سطر گزارش (path + attachment).
 * @param string              $source_path مسیر فایل سالم.
 * @return bool
 */
function sa_media_restore_path_from_file( $row, $source_path ) {
	$path        = isset( $row['path'] ) ? wp_normalize_path( (string) $row['path'] ) : '';
	$source_path = (string) $source_path;
	if ( '' === $path || '' === $source_path || ! is_readable( $source_path ) ) {
		return false;
	}
	if ( file_exists( $path ) ) {
		return true; // چیزی برای بازگرداندن نیست.
	}
	$contents = file_get_contents( $source_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- فایل همراه قالب/پشتیبان.
	if ( false === $contents || ! sa_media_looks_like_image( $contents ) ) {
		return false;
	}
	if ( ! sa_media_write_file( $path, $contents ) ) {
		return false;
	}
	if ( ! empty( $row['attachment'] ) ) {
		sa_media_refresh_attachment( (int) $row['attachment'], $path );
	}
	sa_media_flush_scan();
	return true;
}

/**
 * بازگرداندن فایلِ یک پیوست از یک فایل سالم (نسخهٔ قالب/پشتیبان).
 *
 * برای ابزار «مقالات آماده» هم استفاده می‌شود: اگر تصویر شاخص همراه قالب
 * قبلاً متصل شده باشد ولی فایلش از سرور پاک شده باشد، همین‌جا برمی‌گردد.
 *
 * @param int    $attachment_id شناسهٔ پیوست.
 * @param string $source_path   مسیر فایل سالم.
 * @return bool
 */
function sa_media_restore_from_file( $attachment_id, $source_path ) {
	$attachment_id = absint( $attachment_id );
	if ( ! $attachment_id ) {
		return false;
	}
	$path = sa_media_attachment_path( $attachment_id );
	if ( '' === $path ) {
		return false;
	}
	return sa_media_restore_path_from_file(
		array(
			'path'       => $path,
			'attachment' => $attachment_id,
		),
		$source_path
	);
}

/**
 * بازگرداندن یک فایلِ گمشده (پیش‌نمایش یا اعمال).
 *
 * @param array<string,mixed> $row سطر گزارش.
 * @param bool                $dry پیش‌نمایش.
 * @return array<string,string> نتیجهٔ سطر.
 */
function sa_media_restore_item( $row, $dry = true ) {
	$path   = isset( $row['path'] ) ? wp_normalize_path( (string) $row['path'] ) : '';
	$source = isset( $row['source'] ) && is_array( $row['source'] ) ? $row['source'] : array(
		'type'  => 'none',
		'value' => '',
		'label' => '',
	);
	$type   = isset( $source['type'] ) ? (string) $source['type'] : 'none';
	$value  = isset( $source['value'] ) ? (string) $source['value'] : '';
	$label  = isset( $source['label'] ) ? (string) $source['label'] : '';
	$out    = array(
		'path'    => $path,
		'basename' => basename( $path ),
		'type'    => $type,
		'source'  => $value,
		'status'  => 'skip',
		'message' => '',
	);

	if ( '' === $path ) {
		$out['message'] = 'مسیر نامعتبر.';
		return $out;
	}
	if ( file_exists( $path ) ) {
		$out['status']  = 'exists';
		$out['message'] = 'فایل از قبل موجود است.';
		return $out;
	}
	if ( 'none' === $type ) {
		$out['status']  = 'backup';
		$out['message'] = 'منبع خودکار ندارد؛ از پشتیبان برگردانید.';
		return $out;
	}
	if ( $dry ) {
		$out['status']  = 'planned';
		$out['message'] = $label;
		return $out;
	}

	$contents = '';
	if ( 'local' === $type || 'theme' === $type ) {
		if ( ! is_readable( $value ) ) {
			$out['status']  = 'fail';
			$out['message'] = 'فایل منبع خوانده نشد.';
			return $out;
		}
		if ( ! sa_media_restore_path_from_file( $row, $value ) ) {
			$out['status']  = 'fail';
			$out['message'] = 'نوشتن فایل ممکن نشد (مجوز پوشه یا محتوای نامعتبر).';
			return $out;
		}
		$out['status']  = 'restored';
		$out['message'] = 'بازگردانده شد (' . $label . ').';
		return $out;
	}
	if ( 'url' === $type ) {
		$fetched = sa_media_fetch_url( $value );
		if ( is_wp_error( $fetched ) ) {
			$out['status']  = 'fail';
			$out['message'] = $fetched->get_error_message();
			return $out;
		}
		$contents = (string) $fetched;
	} else {
		$out['status']  = 'skip';
		$out['message'] = 'نوع منبع ناشناخته.';
		return $out;
	}

	if ( ! sa_media_write_file( $path, $contents ) ) {
		$out['status']  = 'fail';
		$out['message'] = 'نوشتن فایل ممکن نشد (مجوز پوشه یا محتوای نامعتبر).';
		return $out;
	}
	if ( ! empty( $row['attachment'] ) ) {
		sa_media_refresh_attachment( (int) $row['attachment'], $path );
	}
	$out['status']  = 'restored';
	$out['message'] = 'بازگردانده شد (' . $label . ').';
	return $out;
}

/**
 * اجرای بازیابی روی فهرستِ برنامه (پیش‌نمایش یا اعمال) و ثبت گزارش ماندگار.
 *
 * @param string $mode  dry|apply.
 * @param int    $limit سقف فایل‌ها در هر اجرا.
 * @return array<string,mixed> گزارش.
 */
function sa_media_restore_run( $mode = 'dry', $limit = 50 ) {
	$limit = max( 1, min( 500, (int) $limit ) );
	$plan  = sa_media_plan();
	$items = array();
	$dry   = 'apply' !== $mode;
	$count = 0;
	foreach ( $plan['source'] as $row ) {
		if ( $count >= $limit ) {
			break;
		}
		++$count;
		$items[] = sa_media_restore_item( $row, $dry );
	}
	$report = array(
		'mode'    => $dry ? 'dry' : 'apply',
		'time'    => time(),
		'total'   => count( $plan['source'] ),
		'ran'     => count( $items ),
		'backup'  => count( $plan['backup'] ),
		'items'   => $items,
	);
	update_option( 'sa_media_restore_report', $report, false );
	if ( ! $dry ) {
		sa_media_flush_scan();
	}
	return $report;
}

/**
 * خواندن آخرین گزارش اجرا.
 *
 * @return array<string,mixed>
 */
function sa_media_restore_last() {
	$report = get_option( 'sa_media_restore_report', array() );
	return is_array( $report ) ? $report : array();
}

/**
 * پیام موقت پیشخوان.
 *
 * @param string $type    success|warning|error.
 * @param string $message متن.
 */
function sa_media_notice( $type, $message ) {
	set_transient(
		'sa_media_notice',
		array(
			'type'    => in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'warning',
			'message' => (string) $message,
		),
		5 * MINUTE_IN_SECONDS
	);
}

/**
 * نمایش پیام موقت (یک‌بار).
 */
function sa_media_restore_notice() {
	$notice = get_transient( 'sa_media_notice' );
	if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
		return;
	}
	delete_transient( 'sa_media_notice' );
	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( (string) $notice['type'] ),
		esc_html( (string) $notice['message'] )
	);
}
add_action( 'admin_notices', 'sa_media_restore_notice' );

/**
 * هندلر «بازیابی از منبع» (admin-post).
 */
function sa_media_restore_handle() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای این کار دسترسی کافی ندارید.' ) );
	}
	check_admin_referer( 'sa_media_restore' );
	$mode  = isset( $_POST['sa_mode'] ) && 'apply' === $_POST['sa_mode'] ? 'apply' : 'dry';
	$limit = isset( $_POST['sa_limit'] ) ? absint( $_POST['sa_limit'] ) : 50;
	$report = sa_media_restore_run( $mode, $limit );

	$restored = 0;
	$failed   = 0;
	foreach ( $report['items'] as $item ) {
		if ( 'restored' === $item['status'] ) {
			++$restored;
		} elseif ( 'fail' === $item['status'] ) {
			++$failed;
		}
	}
	if ( 'dry' === $report['mode'] ) {
		sa_media_notice( 'warning', sprintf( 'پیش‌نمایش: %s فایل از %s فایلِ گمشده از منبع قابل بازگردانی است. برای نوشتن فایل‌ها «اعمال» را بزنید.', sa_fa_digits( (string) $report['ran'] ), sa_fa_digits( (string) $report['total'] ) ) );
	} else {
		sa_media_notice(
			$failed ? 'warning' : 'success',
			sprintf(
				'بازیابی انجام شد: %s فایل برگشت، %s خطا، %s فایل بدون منبع خودکار (نیازمند پشتیبان).',
				sa_fa_digits( (string) $restored ),
				sa_fa_digits( (string) $failed ),
				sa_fa_digits( (string) $report['backup'] )
			)
		);
	}
	wp_safe_redirect( admin_url( 'admin.php?page=sa-media-restore' ) );
	exit;
}
add_action( 'admin_post_sa_media_restore', 'sa_media_restore_handle' );

/**
 * پاک‌کردن بازگشتی یک پوشه (برای پوشهٔ موقتِ بازکردن پشتیبان).
 *
 * @param string $dir پوشه.
 * @return bool
 */
function sa_media_delete_dir( $dir ) {
	$dir = (string) $dir;
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return false;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			@rmdir( $item->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- پوشهٔ موقتِ خودِ ابزار.
		} else {
			wp_delete_file( $item->getPathname() );
		}
	}
	return @rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

/**
 * هندلر «بازگردانی از پشتیبان ZIP» (admin-post).
 *
 * فایل‌های داخل پشتیبان فقط برای همان فایل‌های گمشده‌ای استفاده می‌شوند که
 * نام (و در صورت امکان مسیرِ پایانی) آن‌ها یکسان است؛ فایل موجود هرگز
 * بازنویسی نمی‌شود.
 */
function sa_media_zip_handle() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای این کار دسترسی کافی ندارید.' ) );
	}
	check_admin_referer( 'sa_media_zip' );
	$redirect = admin_url( 'admin.php?page=sa-media-restore' );

	if ( empty( $_FILES['sa_media_backup'] ) || ! empty( $_FILES['sa_media_backup']['error'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- بررسی خطای بارگذاری.
		sa_media_notice( 'error', 'فایلی بارگذاری نشد یا بارگذاری ناقص ماند.' );
		wp_safe_redirect( $redirect );
		exit;
	}

	$uploaded = wp_handle_upload(
		$_FILES['sa_media_backup'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- وردپرس خودش پاک‌سازی می‌کند.
		array(
			'test_form' => false,
			'mimes'     => array( 'zip' => 'application/zip' ),
		)
	);
	if ( ! is_array( $uploaded ) || isset( $uploaded['error'] ) ) {
		sa_media_notice( 'error', 'بارگذاری پشتیبان ممکن نشد: ' . ( is_array( $uploaded ) && isset( $uploaded['error'] ) ? (string) $uploaded['error'] : 'خطای نامشخص' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	$uploads  = sa_media_upload_base();
	$unzip_to = '' !== $uploads['basedir'] ? trailingslashit( $uploads['basedir'] ) . 'sa-restore-tmp' : '';
	if ( '' === $unzip_to ) {
		sa_media_notice( 'error', 'پوشهٔ رسانه در دسترس نیست.' );
		wp_safe_redirect( $redirect );
		exit;
	}
	sa_media_delete_dir( $unzip_to );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$result = unzip_file( (string) $uploaded['file'], $unzip_to );
	wp_delete_file( (string) $uploaded['file'] );
	if ( is_wp_error( $result ) ) {
		sa_media_delete_dir( $unzip_to );
		sa_media_notice( 'error', 'بازکردن پشتیبان ممکن نشد: ' . $result->get_error_message() );
		wp_safe_redirect( $redirect );
		exit;
	}

	$scan = sa_media_scan( true );
	$plan = sa_media_plan( $scan );

	// نمایهٔ مسیرهای مورد انتظار: نام فایل و دو بخش پایانی مسیر.
	$wanted = array();
	foreach ( $plan['backup'] as $path => $row ) {
		$name = strtolower( basename( $path ) );
		$tail = sa_media_path_tail( $path, 3 );
		$wanted[ $name ][] = $path;
		if ( '' !== $tail ) {
			$wanted[ $tail ][] = $path;
		}
	}

	$restored = 0;
	$skipped  = 0;
	$limit    = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $unzip_to, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() || $limit > 20000 ) {
			continue;
		}
		++$limit;
		$name = strtolower( $file->getFilename() );
		$tail = sa_media_path_tail( wp_normalize_path( $file->getPathname() ), 3, wp_normalize_path( $unzip_to ) );
		$hit  = '';
		if ( '' !== $tail && ! empty( $wanted[ $tail ] ) && 1 === count( $wanted[ $tail ] ) ) {
			$hit = $wanted[ $tail ][0];
		} elseif ( ! empty( $wanted[ $name ] ) && 1 === count( $wanted[ $name ] ) ) {
			$hit = $wanted[ $name ][0];
		}
		if ( '' === $hit || ! isset( $plan['backup'][ $hit ] ) ) {
			++$skipped;
			continue;
		}
		$row = $plan['backup'][ $hit ];
		if ( sa_media_restore_path_from_file( $row, $file->getPathname() ) ) {
			++$restored;
		} else {
			++$skipped;
		}
	}
	sa_media_delete_dir( $unzip_to );

	sa_media_notice(
		$restored ? 'success' : 'warning',
		sprintf(
			'از پشتیبان %s فایل بازگردانده شد%s.',
			sa_fa_digits( (string) $restored ),
			$skipped ? ' و ' . sa_fa_digits( (string) $skipped ) . ' فایل نادیده ماند (نام در فهرست گمشده‌ها نبود یا نامش یکتا نبود)' : ''
		)
	);
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_sa_media_zip', 'sa_media_zip_handle' );

/**
 * چند بخش پایانیِ یک مسیر (برای تطبیق فایل‌های پشتیبان).
 *
 * @param string $path   مسیر.
 * @param int    $parts  تعداد بخش.
 * @param string $prefix پیشوند قابل‌حذف.
 * @return string مثل `2026/10/photo.jpg`
 */
function sa_media_path_tail( $path, $parts = 3, $prefix = '' ) {
	$path   = wp_normalize_path( (string) $path );
	$prefix = '' !== $prefix ? rtrim( wp_normalize_path( $prefix ), '/' ) . '/' : '';
	if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
		$path = substr( $path, strlen( $prefix ) );
	}
	$chunks = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
	$tail   = array_slice( $chunks, -1 * max( 1, (int) $parts ) );
	return strtolower( implode( '/', $tail ) );
}

/**
 * هندلر خروجی CSV از فهرست فایل‌های گمشده (admin-post).
 */
function sa_media_export_handle() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای این کار دسترسی کافی ندارید.' ) );
	}
	check_admin_referer( 'sa_media_export' );
	$scan = sa_media_scan();
	$plan = sa_media_plan( $scan );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="sa-missing-images-' . gmdate( 'Y-m-d' ) . '.csv"' );
	echo "\xEF\xBB\xBF"; // BOM برای اکسل فارسی.
	$out = fopen( 'php://output', 'w' );
	if ( ! $out ) {
		exit;
	}
	fputcsv( $out, array( 'ردیف', 'نام فایل', 'مسیر مورد انتظار (نسبت به uploads)', 'وضعیت', 'منبع پیشنهادی', 'نشانی منبع', 'نوشته‌های درگیر' ) );
	$i = 0;
	foreach ( $plan['source'] + $plan['backup'] as $row ) {
		++$i;
		$relative = $row['path'];
		$uploads  = sa_media_upload_base();
		if ( '' !== $uploads['basedir'] && 0 === strpos( $relative, $uploads['basedir'] . '/' ) ) {
			$relative = substr( $relative, strlen( $uploads['basedir'] ) + 1 );
		}
		$titles = array();
		foreach ( array_keys( (array) $row['posts'] ) as $pid ) {
			$titles[] = '#' . $pid . ' ' . (string) get_the_title( (int) $pid );
		}
		fputcsv(
			$out,
			array(
				$i,
				$row['basename'],
				$relative,
				'none' === $row['source']['type'] ? 'نیازمند پشتیبان' : 'قابل بازیابی از منبع',
				$row['source']['label'],
				$row['source']['value'],
				implode( ' | ', $titles ),
			)
		);
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_sa_media_export', 'sa_media_export_handle' );

/**
 * صفحهٔ «بازیابی تصاویر» در پیشخوان.
 */
function sa_media_restore_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای دیدن این صفحه دسترسی کافی ندارید.' ) );
	}
	$force = isset( $_GET['refresh'] ) && check_admin_referer( 'sa_media_refresh' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$scan  = sa_media_scan( (bool) $force );
	$plan  = sa_media_plan( $scan );
	$t     = $scan['totals'];
	$last  = sa_media_restore_last();
	$fa    = 'sa_fa_digits';

	echo '<div class="wrap sa-media" dir="rtl">';
	echo '<h1>بازیابی تصاویر</h1>';
	echo '<p>این ابزار فایل‌های تصویریِ <strong>گمشده</strong> را پیدا می‌کند: تصویرهای داخل متن نوشته‌ها و فایل‌های فهرست رسانه که روی سرور نیستند. هر فایل با <strong>همان نام و همان مسیر</strong> بازگردانده می‌شود، پس آدرس‌ها و <code>srcset</code>های موجود در محتوا دست‌نخورده می‌مانند. روی فایل موجود هرگز نوشته نمی‌شود.</p>';

	/* --- کارت‌های خلاصه --- */
	$cards = array(
		array( 'تصویر داخل متن (بررسی‌شده)', $fa( (string) $t['images'] ), '' ),
		array( 'تصویر سالم روی سرور', $fa( (string) $t['present'] ), 'ok' ),
		array( 'فایل گمشده', $fa( (string) $t['missing'] ), $t['missing'] ? 'bad' : 'ok' ),
		array( 'قابل بازیابی از منبع', $fa( (string) $t['fixable'] ), $t['fixable'] ? 'warn' : 'ok' ),
		array( 'نیازمند پشتیبان', $fa( (string) $t['needs_backup'] ), $t['needs_backup'] ? 'warn' : 'ok' ),
		array( 'پیوست رسانهٔ بی‌فایل', $fa( (string) $t['attachment_missing'] ), $t['attachment_missing'] ? 'bad' : 'ok' ),
	);
	echo '<div class="sa-media__cards">';
	foreach ( $cards as $card ) {
		echo '<div class="sa-media__card sa-media__card--' . esc_attr( $card[2] ? $card[2] : 'neutral' ) . '">'
			. '<b>' . esc_html( $card[1] ) . '</b><span>' . esc_html( $card[0] ) . '</span></div>';
	}
	echo '</div>';
	echo '<p class="description">آخرین محاسبه: ' . esc_html( $fa( (string) human_time_diff( (int) $scan['generated'] ) ) ) . ' پیش — '
		. esc_html( $fa( (string) $t['posts'] ) ) . ' نوشته و ' . esc_html( $fa( (string) $t['attachments'] ) ) . ' پیوست رسانه بررسی شد. '
		. '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=sa-media-restore&refresh=1' ), 'sa_media_refresh' ) ) . '">به‌روزرسانی گزارش</a></p>';
	if ( ! empty( $t['truncated'] ) ) {
		echo '<div class="notice notice-warning inline"><p>گزارش روی سقف ایمنی متوقف شد؛ ممکن است بخشی از فایل‌ها در این فهرست نباشد (سقف‌ها با فیلترهای <code>sa_media_scan_cap</code> و <code>sa_media_scan_max_pages</code> بالا می‌روند).</p></div>';
	}
	if ( ! empty( $t['external'] ) ) {
		echo '<p class="description">' . esc_html( $fa( (string) $t['external'] ) ) . ' تصویرِ بیرونی (روی دامنهٔ دیگر) در متن‌ها هست؛ آن‌ها موضوع این ابزار نیستند.</p>';
	}

	/* --- بازیابی از منبع --- */
	echo '<h2>۱) بازگردانی از منبع</h2>';
	echo '<p>به ترتیب: نسخهٔ سالمِ مانده در پوشهٔ رسانه → نسخهٔ همراه قالب (<code>assets/img/**</code>) → نشانی منبعِ ثبت‌شده روی پیوست یا در رکوردهای <code>data/ready-*.php</code>. اگر نشانی منبع روی سرور در دسترس نباشد (فیلتر شبکه یا حذف فایل در منبع)، همان سطر «خطا» ثبت می‌شود و بقیه ادامه پیدا می‌کنند.</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
	echo '<input type="hidden" name="action" value="sa_media_restore">';
	wp_nonce_field( 'sa_media_restore' );
	echo '<label>سقف فایل در هر اجرا: <input type="number" name="sa_limit" value="50" min="1" max="500" style="width:90px"></label> ';
	echo '<button type="submit" class="button" name="sa_mode" value="dry">پیش‌نمایش</button> ';
	echo '<button type="submit" class="button button-primary" name="sa_mode" value="apply">اعمال بازگردانی</button>';
	echo '</form>';

	/* --- پشتیبان --- */
	echo '<h2>۲) بازگردانی از پشتیبان (ZIP)</h2>';
	echo '<p>اگر نسخهٔ پشتیبان پوشهٔ رسانه را دارید، فایل ZIP را همین‌جا بارگذاری کنید. فقط فایل‌هایی برداشته می‌شوند که <strong>همین حالا گمشده‌اند</strong> و نامشان با فهرست بالا یکی است؛ بقیهٔ محتوای پشتیبان نادیده می‌ماند. ساختار پوشه‌ها مهم نیست (<code>uploads/2026/10/x.jpg</code>، <code>wp-content/uploads/2026/10/x.jpg</code> یا فقط <code>2026/10/x.jpg</code>).</p>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
	echo '<input type="hidden" name="action" value="sa_media_zip">';
	wp_nonce_field( 'sa_media_zip' );
	echo '<input type="file" name="sa_media_backup" accept=".zip,application/zip" required> ';
	echo '<button type="submit" class="button button-primary">بازگردانی از پشتیبان</button>';
	echo '</form>';
	if ( $t['needs_backup'] > 0 ) {
		echo '<p class="description">' . esc_html( $fa( (string) $t['needs_backup'] ) ) . ' فایل هیچ منبع خودکاری ندارد (نه نسخهٔ مانده، نه فایل قالب، نه نشانی منبع)؛ آن‌ها فقط با پشتیبان برمی‌گردند.</p>';
	}

	/* --- خروجی CSV --- */
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_media_export' ), 'sa_media_export' ) ) . '">دریافت فهرست فایل‌های گمشده (CSV)</a> '
		. '<span class="description">برای تحویل به میزبان یا جست‌وجو در پشتیبان‌ها.</span></p>';

	/* --- فهرست گمشده‌ها --- */
	echo '<h2>۳) فایل‌های گمشده</h2>';
	if ( empty( $scan['missing'] ) ) {
		echo '<p>هیچ فایل تصویری گمشده‌ای نیست. ✓</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>فایل</th><th>مسیر مورد انتظار</th><th>استفاده در</th><th>منبع پیشنهادی</th><th>وضعیت</th></tr></thead><tbody>';
		$shown = 0;
		foreach ( $plan['source'] + $plan['backup'] as $row ) {
			if ( ++$shown > 150 ) {
				echo '<tr><td colspan="5">… و ' . esc_html( $fa( (string) ( $t['missing'] - 150 ) ) ) . ' فایل دیگر (همه در CSV).</td></tr>';
				break;
			}
			$names = array();
			foreach ( array_keys( (array) $row['posts'] ) as $pid ) {
				$names[] = '<a href="' . esc_url( (string) get_edit_post_link( (int) $pid ) ) . '">' . esc_html( wp_trim_words( (string) get_the_title( (int) $pid ), 7, '…' ) ) . '</a>';
			}
			if ( $row['attachment'] && empty( $row['posts'] ) ) {
				$names[] = '<a href="' . esc_url( (string) get_edit_post_link( (int) $row['attachment'] ) ) . '">پیوست رسانه</a>';
			}
			$uploads   = sa_media_upload_base();
			$relative  = (string) $row['path'];
			if ( '' !== $uploads['basedir'] && 0 === strpos( $relative, $uploads['basedir'] . '/' ) ) {
				$relative = substr( $relative, strlen( $uploads['basedir'] ) + 1 );
			}
			$is_backup = 'none' === $row['source']['type'];
			echo '<tr><td><code>' . esc_html( (string) $row['basename'] ) . '</code></td>'
				. '<td><code>' . esc_html( $relative ) . '</code></td>'
				. '<td>' . ( $names ? wp_kses_post( implode( '، ', $names ) ) : '—' ) . '</td>'
				. '<td>' . esc_html( (string) $row['source']['label'] ) . ( '' !== $row['source']['value'] ? '<br><small>' . esc_html( wp_trim_words( (string) $row['source']['value'], 12, '…' ) ) . '</small>' : '' ) . '</td>'
				. '<td>' . ( $is_backup ? '<span style="color:#b32d2e">نیازمند پشتیبان</span>' : '<span style="color:#065f46">قابل بازیابی</span>' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* --- گزارش آخرین اجرا --- */
	echo '<h2>۴) آخرین اجرا</h2>';
	if ( empty( $last['items'] ) ) {
		echo '<p>هنوز اجرایی ثبت نشده است.</p>';
	} else {
		echo '<p>حالت: <strong>' . esc_html( 'dry' === $last['mode'] ? 'پیش‌نمایش' : 'اعمال' ) . '</strong> — '
			. esc_html( $fa( (string) $last['ran'] ) ) . ' از ' . esc_html( $fa( (string) $last['total'] ) ) . ' فایلِ دارای منبع، '
			. esc_html( $fa( (string) human_time_diff( (int) $last['time'] ) ) ) . ' پیش. '
			. 'بدون منبع خودکار: ' . esc_html( $fa( (string) $last['backup'] ) ) . ' فایل.</p>';
		echo '<table class="widefat striped"><thead><tr><th>فایل</th><th>منبع</th><th>وضعیت</th></tr></thead><tbody>';
		foreach ( array_slice( array_reverse( $last['items'] ), 0, 200 ) as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : '';
			$labels = array(
				'restored' => 'بازگردانده شد',
				'planned'  => 'در برنامه',
				'exists'   => 'از قبل موجود',
				'fail'     => 'خطا',
				'backup'   => 'نیازمند پشتیبان',
				'skip'     => 'رد شد',
			);
			echo '<tr><td><code>' . esc_html( (string) $item['basename'] ) . '</code></td>'
				. '<td><small>' . esc_html( (string) $item['source'] ) . '</small></td>'
				. '<td>' . esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ) . ' — ' . esc_html( (string) $item['message'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	echo '<style>
	.sa-media__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:18px 0 14px}
	.sa-media__card{background:#fff;border:1px solid #dcdcde;border-inline-start-width:4px;border-radius:6px;padding:14px 16px}
	.sa-media__card b{display:block;font-size:24px;line-height:1.3}
	.sa-media__card span{color:#646970;font-size:12px}
	.sa-media__card--ok{border-inline-start-color:#00a32a}
	.sa-media__card--warn{border-inline-start-color:#dba617}
	.sa-media__card--bad{border-inline-start-color:#d63638}
	.sa-media__card--neutral{border-inline-start-color:#2271b1}
	.sa-media code{direction:ltr;display:inline-block}
	</style>';
	echo '</div>';
}
