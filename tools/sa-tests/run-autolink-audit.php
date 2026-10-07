<?php
/**
 * ممیزیِ «لینک‌سازی خودکار» روی محتوای واقعی — شبیه‌سازیِ رندر با موتورِ خودِ قالب.
 *
 * چرا لازم است: لینک‌های داخلیِ این قالب در زمان نمایش ساخته می‌شوند
 * (`inc/internal-links.php`)، پس شمارشِ لینک در فایل WXR آن‌ها را نشان نمی‌دهد.
 * این اسکریپت واژه‌نامهٔ واقعی (۳۱ استان + شهرستان‌های رجیستری + نماهای منتشرشده)
 * را می‌سازد و بدنهٔ هر صفحه را از همان موتور عبور می‌دهد.
 *
 * ورودی (فایل JSON، مسیر از محیط):
 *   SA_AUTOLINK_JOB=/ws/.tmp-autolink/job.json
 *   {
 *     "roster":   [ {"type":"city","slug":"karaj","title":"شهرستان کرج"}, … ],
 *     "subjects": [ {"type":"province","slug":"alborz","title":"استان البرز","content":"…"} ]
 *   }
 *
 * خروجی: JSON روی stdout — برای هر صفحه شمار لینک‌های تولیدشده به تفکیک نوع و
 * مقصدها (فقط مسیر و برچسبِ لینک؛ متن مقاله چاپ نمی‌شود). لینک‌های بیرونی فقط شمرده می‌شوند.
 *
 * محدودیت: این اجرا «شبیه‌سازی» است، نه صفحهٔ زنده؛ ترم‌های تاکسونومی، صفحه‌بندی،
 * افزونه‌های نصب‌شده و کوکی/کش را مدل نمی‌کند.
 *
 * @package Sarzaminaryan_Child
 */

require __DIR__ . '/wp-stubs.php';

$sa_child_dir = rtrim( (string) getenv( 'SA_CHILD_DIR' ), '/' ) . '/';
if ( ! is_dir( $sa_child_dir ) ) {
	echo "SA_CHILD_DIR is not set or missing\n";
	exit( 2 );
}

/* ------------------------------------------------- شبیه‌سازهای لازمِ محیط نمایش */

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}
if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return false;
	}
}
if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $types = '' ) {
		return true;
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return isset( $GLOBALS['sa_autolink_current_id'] ) ? (int) $GLOBALS['sa_autolink_current_id'] : 0;
	}
}
if ( ! function_exists( 'get_theme_mod' ) ) {
	function get_theme_mod( $name, $default = false ) {
		return $default;
	}
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term, $taxonomy = '' ) {
		return 'https://sarzaminaryan.test/province_tax/term-' . (int) $term . '/';
	}
}
if ( ! function_exists( 'mb_strlen' ) ) {
	function mb_strlen( $text, $encoding = 'UTF-8' ) { // phpcs:ignore
		return count( preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}
}
if ( ! function_exists( 'sa_remember_cache_key' ) ) {
	function sa_remember_cache_key( $key ) {
		return true;
	}
}

// واژه‌نامه از همان داده‌های بستهٔ قالب ساخته می‌شود؛ ماژول‌های دیگر بار نمی‌شوند.
$sa_counties  = require $sa_child_dir . 'data/counties.php';
$sa_provinces = require $sa_child_dir . 'data/provinces.php';

if ( ! function_exists( 'sa_counties' ) ) {
	function sa_counties() {
		global $sa_counties;
		return $sa_counties;
	}
}
if ( ! function_exists( 'sa_provinces_data' ) ) {
	function sa_provinces_data() {
		global $sa_provinces;
		return $sa_provinces;
	}
}

require $sa_child_dir . 'inc/internal-links.php';

/* ------------------------------------------------------------------- ورودی */

$job_path = getenv( 'SA_AUTOLINK_JOB' );
if ( ! $job_path || ! is_readable( $job_path ) ) {
	echo "SA_AUTOLINK_JOB is missing\n";
	exit( 2 );
}
$job = json_decode( (string) file_get_contents( $job_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $job ) || empty( $job['subjects'] ) ) {
	echo "job JSON is invalid\n";
	exit( 2 );
}

$roster = isset( $job['roster'] ) && is_array( $job['roster'] ) ? $job['roster'] : array();

/* ثبتِ نوشته‌های مقصد در شبیه‌سازِ وردپرس و $wpdb (هر دو لازم است). */
$rows = array();
foreach ( $roster as $row ) {
	$type = isset( $row['type'] ) ? (string) $row['type'] : '';
	$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
	if ( '' === $type || '' === $slug ) {
		continue;
	}
	$id = sa_add_post(
		array(
			'post_type'   => $type,
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => isset( $row['title'] ) ? (string) $row['title'] : '',
		)
	);
	$rows[] = (object) array(
		'ID'         => $id,
		'post_type'  => $type,
		'post_name'  => $slug,
		'post_title' => isset( $row['title'] ) ? (string) $row['title'] : '',
	);
}
$GLOBALS['wpdb']->set_results( $rows );

/* ------------------------------------------------------------- شبیه‌سازیِ رندر */

$out = array(
	'max_links' => (int) apply_filters( 'sa_autolink_max_links', 30 ),
	'subjects'  => array(),
);

foreach ( $job['subjects'] as $subject ) {
	$type    = isset( $subject['type'] ) ? (string) $subject['type'] : 'post';
	$slug    = isset( $subject['slug'] ) ? (string) $subject['slug'] : '';
	$content = isset( $subject['content'] ) ? (string) $subject['content'] : '';
	if ( '' === $slug || '' === trim( $content ) ) {
		continue;
	}

	$subject_id = sa_add_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => isset( $subject['title'] ) ? (string) $subject['title'] : '',
			'post_content' => $content,
		)
	);
	$GLOBALS['sa_autolink_current_id'] = $subject_id;

	$rendered = sa_autolink_content( $content );

	// پیوندهای موجود در متنِ ذخیره‌شده تا «تولیدشده» از «از قبل موجود» جدا شود.
	$prelinked = array();
	if ( preg_match_all( '~<a\b[^>]*\shref=("|\')([^"\']+)\1~i', $content, $before ) ) {
		foreach ( $before[2] as $href ) {
			$prelinked[ (string) preg_replace( '~^https?://[^/]+~', '', html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) ) ] = true;
		}
	}

	$links        = array();
	$bare_anchors = 0;
	if ( preg_match_all( '~<a\b[^>]*\shref=("|\')([^"\']+)\1[^>]*>(.*?)</a>~su', (string) $rendered, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$url   = (string) $match[2];
			$label = trim( wp_strip_all_tags( (string) $match[3] ) );
			$kind  = 'other';
			if ( false !== strpos( $url, '/city/' ) ) {
				$kind = 'city';
			} elseif ( false !== strpos( $url, '/attraction/' ) ) {
				$kind = 'attraction';
			} elseif ( false !== strpos( $url, '/province/' ) ) {
				$kind = 'province';
			}
			if ( isset( $links[ $url ] ) ) {
				continue;
			}
			if ( 'other' === $kind ) {
				// لینک‌های بیرونی فقط شمرده می‌شوند؛ برای کوتاه‌ماندنِ خروجی چاپ نمی‌شوند.
				$links[ $url ] = array( 'kind' => $kind );
				continue;
			}
			$path     = (string) preg_replace( '~^https?://[^/]+~', '', $url );
			$was_there = isset( $prelinked[ $path ] );
			// قاعدهٔ موتور: متنِ لینکِ تولیدشده باید نامِ کامل باشد («شهرستان/استان X»).
			$bare = ( ! $was_there
				&& 'attraction' !== $kind
				&& ! preg_match( '~^(?:استان|شهرستان|شهر|بخش|دهستان)[\s\x{200C}]~u', $label ) );
			if ( $bare ) {
				$bare_anchors++;
			}
			$links[ $url ] = array(
				'kind'      => $kind,
				'path'      => $path,
				'label'     => $label,
				'generated' => ! $was_there,
				'bare'      => $bare,
			);
		}
	}

	$counts = array(
		'city'       => 0,
		'province'   => 0,
		'attraction' => 0,
		'other'      => 0,
	);
	foreach ( $links as $link ) {
		$counts[ $link['kind'] ] = isset( $counts[ $link['kind'] ] ) ? $counts[ $link['kind'] ] + 1 : 1;
	}

	$out['subjects'][] = array(
		'bare_anchors' => $bare_anchors,
		'slug'   => $slug,
		'type'   => $type,
		'counts' => $counts,
		'total'  => array_sum( $counts ),
		'links'  => array_values( $links ),
	);
}

echo wp_json_encode( $out ) . "\n"; // phpcs:ignore WordPress.WP.AlternativeFunctions
