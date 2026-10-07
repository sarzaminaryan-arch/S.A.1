<?php
/**
 * بازکردنِ پیوندهای خودارجاع روی محتوای واقعی (اجراکنندهٔ داده‌محور).
 *
 * ورودی: `SA_SELFLINK_JOB` → JSON به شکل
 *   { "pages": [ { "type": "city", "slug": "baft", "content": "…" }, … ] }
 *
 * برای هر صفحه، همان تابعِ ابزارِ پیشخوان (`sa_repair_unwrap_self_links`) اجرا می‌شود
 * و سه چیز بررسی می‌شود:
 *   ۱. متنِ بیرونِ پیوندها بایت‌به‌بایت دست‌نخورده است.
 *   ۲. هیچ پیوندِ دیگری (بیرونی/شهرستانِ دیگر) تغییر نکرده است.
 *   ۳. شمارِ پیوندهای بازشده با انتظار می‌خواند.
 *
 * Run: SA_SELFLINK_JOB=/ws/.tmp-selflink/job.json \
 *        node tools/phpwasm/exec.js tools/sa-tests/run-selflink-repair.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';

define( 'SA_CHILD_DIR', '/ws/theme/' );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/ws/' );
}
require '/ws/theme/inc/entities-config.php';
require '/ws/theme/inc/content-repair.php';

$job_path = getenv( 'SA_SELFLINK_JOB' );
if ( ! $job_path || ! is_readable( $job_path ) ) {
	echo "SA_SELFLINK_JOB is missing\n";
	exit( 2 );
}
$job = json_decode( (string) file_get_contents( $job_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
if ( ! is_array( $job ) || empty( $job['pages'] ) ) {
	echo "job JSON is invalid\n";
	exit( 2 );
}

/**
 * متنِ دیدنی (بدونِ تگ) — برای اثباتِ اینکه فقط برچسبِ `<a>` حذف شده و متن سالم است.
 *
 * @param string $html محتوا.
 * @return string
 */
function sa_selflink_text_only( $html ) {
	return trim( (string) preg_replace( '~\s+~u', ' ', wp_strip_all_tags( (string) $html ) ) );
}

/**
 * فهرستِ پیوندهای غیرِخودی (هر پیوندی که به خودِ صفحه نیست).
 *
 * @param string $html  محتوا.
 * @param array  $paths مسیرهای خودِ صفحه.
 * @return string[]
 */
function sa_selflink_foreign_anchors( $html, $paths ) {
	$self = array();
	foreach ( (array) $paths as $path ) {
		$self[ sa_repair_normalize_path( $path ) ] = true;
	}
	$out = array();
	if ( preg_match_all( '~<a\b[^>]*?>.*?</a>~isu', (string) $html, $m ) ) {
		foreach ( $m[0] as $anchor ) {
			$href = '';
			if ( preg_match( '~\bhref\s*=\s*(["\'])(.*?)\1~isu', $anchor, $h ) ) {
				$href = (string) $h[2];
			}
			if ( ! isset( $self[ sa_repair_normalize_path( $href ) ] ) ) {
				$out[] = $anchor;
			}
		}
	}

	return $out;
}

$result = array(
	'pages'             => 0,
	'pages_changed'     => 0,
	'links_removed'     => 0,
	'kept_fragment'     => 0,
	'text_mismatch'     => array(),
	'foreign_mismatch'  => array(),
	'empty_path'        => array(),
	'left_after'        => array(),
);

foreach ( $job['pages'] as $page ) {
	$type    = isset( $page['type'] ) ? (string) $page['type'] : 'city';
	$slug    = isset( $page['slug'] ) ? (string) $page['slug'] : '';
	$content = isset( $page['content'] ) ? (string) $page['content'] : '';
	if ( '' === $slug || '' === $content ) {
		continue;
	}

	$id = sa_add_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);
	$paths = sa_repair_self_paths( $id );
	if ( ! $paths ) {
		$result['empty_path'][] = $slug;
		continue;
	}

	++$result['pages'];
	$removed = 0;
	$kept    = 0;
	$out     = sa_repair_unwrap_self_links( $content, $paths, $removed, $kept );

	$result['links_removed'] += $removed;
	$result['kept_fragment'] += $kept;
	if ( $out !== $content ) {
		++$result['pages_changed'];
	}

	if ( sa_selflink_text_only( $content ) !== sa_selflink_text_only( $out ) ) {
		$result['text_mismatch'][] = $slug;
	}
	if ( sa_selflink_foreign_anchors( $content, $paths ) !== sa_selflink_foreign_anchors( $out, $paths ) ) {
		$result['foreign_mismatch'][] = $slug;
	}
	if ( sa_repair_selflink_count( $out, $paths ) > 0 ) {
		$result['left_after'][] = $slug;
	}
}

$result['expect_no_self_anchor_left'] = 0;
echo wp_json_encode( $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
