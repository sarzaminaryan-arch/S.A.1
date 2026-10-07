<?php
/**
 * سرزمین آریان → تعمیر محتوا (v2.11.36)
 *
 * ابزار «یک‌بارمصرفِ» مدیر برای پاک‌کردن ایرادهای ساختاریِ محتوای منتشرشده.
 * نخستین و پرمصرف‌ترین مورد، ممیزیِ WXR واقعی (۱۴۰۵-۰۷-۱۵) بود:
 * ۲۶۱ صفحهٔ شهرستانِ منتشرشده `<h1>` داخل بدنه داشتند، در حالی که
 * `template-parts/entity/hero.php` هم `<h1 class="entry-title">` می‌زند؛
 * یعنی دو H1 با دو متن متفاوت روی یک صفحه.
 *
 * سیاست این ابزار:
 * - پیش‌نمایش (dry-run) پیش‌فرض است؛ اعمال فقط با nonce + `manage_options`.
 * - هر تغییر با `wp_update_post()` انجام می‌شود تا نسخهٔ بازبینی (revision)
 *   ساخته شود و بازگشت به نسخهٔ قبل از پیشخوان ممکن بماند.
 * - فقط CPTهای موجودیت لمس می‌شوند؛ برگه، نوشته، رسانه و پیش‌نویس‌ها هرگز.
 * دومین مورد (v2.11.38): «پیوندِ خودارجاع» — ۱۷۶ صفحهٔ منتشرشده در متنِ ذخیره‌شده
 * به خودشان لینک داده بودند («شهرستان X» → `/city/x/`). این هم فقط برچسبِ `<a>`
 * را برمی‌دارد و متن را نگه می‌دارد.
 * - فقط تبدیل مکانیکی `<h1>` به `<h2>` (و بازکردنِ پیوندِ خودارجاع) انجام می‌شود. هر ایراد دیگری که ممیزی
 *   پیدا می‌کند (یادداشت تحریریه، گیومهٔ خالی، متای تکراری سئو) تصمیم انسانی
 *   است و در `docs/2026-10-07-content-corpus-audit-fa.md` فهرست شده است.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تعداد نوشته‌هایی که در هر اجرا بررسی/اصلاح می‌شود.
 */
function sa_repair_batch_size() {
	return (int) apply_filters( 'sa_repair_batch_size', 200 );
}

/**
 * تنزلِ همهٔ `<h1>…</h1>`های بدنه به `<h2>` (تابع خالص، بدون اثر جانبی).
 *
 * برچسب‌های ناقص (مثل `<h1` بدون بستهٔ `</h1>`) عمداً دست‌نخورده می‌مانند؛
 * آن‌ها در گزارش «بررسی‌نشده» شمرده می‌شوند تا نویسنده خودش تصمیم بگیرد.
 *
 * @param string $content محتوای خام نوشته.
 * @param int    $changed (مرجع) تعداد سربرگ‌های تبدیل‌شده.
 * @return string محتوای تازه؛ در صورت شکست regex، ورودی دست‌نخورده.
 */
function sa_repair_demote_h1( $content, &$changed = null ) {
	$changed = 0;
	if ( ! is_string( $content ) || '' === $content || false === stripos( $content, '<h1' ) ) {
		return $content;
	}

	$out = preg_replace_callback(
		'#<h1(\s[^>]*)?>(.*?)</h1>#is',
		function ( $m ) use ( &$changed ) {
			++$changed;
			$attrs = isset( $m[1] ) ? $m[1] : '';
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$content
	);

	if ( ! is_string( $out ) ) {
		$changed = 0;
		return $content;
	}

	return $out;
}

/**
 * چند سربرگ H1 در متن هست و چند تا از آن‌ها جفتِ کاملِ `</h1>` دارند؟
 *
 * @param string $content محتوای خام.
 * @return array{open:int,pairs:int}
 */
function sa_repair_h1_stats( $content ) {
	$content = (string) $content;
	return array(
		'open'  => (int) preg_match_all( '/<h1(?:[\s>])/i', $content ),
		'pairs' => (int) preg_match_all( '#<h1(?:\s[^>]*)?>.*?</h1>#is', $content ),
	);
}

/**
 * نامزدهای اصلاح: موجودیت‌هایی که در بدنهٔ ذخیره‌شده‌شان `<h1>` دارند.
 *
 * @param int $limit سقف بررسی در این اجرا.
 * @return array<int,array{id:int,stats:array{open:int,pairs:int}}>
 */
function sa_repair_h1_candidates( $limit = 0 ) {
	$types = array_values( array_filter( array_map( 'strval', (array) sa_entity_types() ) ) );
	if ( ! $types ) {
		return array();
	}
	if ( $limit <= 0 ) {
		$limit = sa_repair_batch_size();
	}

	$q = new WP_Query(
		array(
			'post_type'              => $types,
			'post_status'            => array( 'publish', 'draft', 'pending' ),
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'fields'                 => 'ids',
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		)
	);

	$out = array();
	foreach ( $q->posts as $pid ) {
		$stats = sa_repair_h1_stats( (string) get_post_field( 'post_content', $pid ) );
		if ( $stats['open'] ) {
			$out[] = array(
				'id'    => (int) $pid,
				'stats' => $stats,
			);
		}
	}

	return $out;
}

/**
 * اجرای تعمیر: پیش‌نمایش یا اعمال.
 *
 * @param string $mode  `dry` یا `apply`.
 * @param int    $limit سقف نوشته در این اجرا.
 * @return array گزارش اجرا.
 */
function sa_repair_run( $mode = 'dry', $limit = 0 ) {
	$mode   = ( 'apply' === $mode ) ? 'apply' : 'dry';
	$limit  = $limit > 0 ? (int) $limit : sa_repair_batch_size();
	$report = array(
		'mode'      => $mode,
		'time'      => time(),
		'scanned'   => 0,
		'posts'     => 0,
		'headings'  => 0,
		'unpaired'  => 0,
		'failed'    => array(),
		'unchanged' => array(),
		'ids'       => array(),
	);

	$candidates = sa_repair_h1_candidates( $limit );

	foreach ( $candidates as $candidate ) {
		$pid     = $candidate['id'];
		$content = (string) get_post_field( 'post_content', $pid );
		$out     = sa_repair_demote_h1( $content, $changed );

		++$report['scanned'];
		$report['unpaired'] += max( 0, $candidate['stats']['open'] - $candidate['stats']['pairs'] );

		if ( ! $changed || $out === $content ) {
			$report['unchanged'][] = $pid;
			continue;
		}

		if ( 'dry' === $mode ) {
			++$report['posts'];
			$report['headings'] += $changed;
			$report['ids'][]     = $pid;
			continue;
		}

		$result = wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => $out,
			),
			true
		);

		if ( is_wp_error( $result ) || ! $result ) {
			$report['failed'][] = $pid;
			continue;
		}

		++$report['posts'];
		$report['headings'] += $changed;
		$report['ids'][]     = $pid;
	}

	if ( 'apply' === $mode && $report['posts'] ) {
		delete_transient( 'sa_health_scan' );
	}

	$report['remaining'] = count( sa_repair_h1_candidates( $limit + 1 ) ) > $limit;
	$report['batch']     = $limit;
	update_option( 'sa_repair_last', $report, false );

	return $report;
}

/**
 * آخرین گزارش اجرا (یا null).
 *
 * @return array|null
 */
function sa_repair_last() {
	$last = get_option( 'sa_repair_last' );
	return is_array( $last ) ? $last : null;
}

/**
 * چند مسیر انتشاریِ موجودیت H1 بدنه دارند؟ (سبک، برای نمایش در پیشخوان)
 *
 * @return int
 */
function sa_repair_h1_count() {
	return count( sa_repair_h1_candidates( sa_repair_batch_size() ) );
}

/* -------------------------------------------------------------------------
 * تعمیر دوم: پیوندِ خودارجاع در متنِ ذخیره‌شده (۱۷۶ مورد در ممیزی ۱۴۰۵-۰۷-۱۵)
 * ---------------------------------------------------------------------- */

/**
 * مسیرهای نسبیِ یک نوشته برای شناختن «پیوند به خودِ صفحه».
 *
 * @param int    $post_id شناسه.
 * @param string $url     اختیاری؛ اگر خالی باشد از `get_permalink()` می‌آید.
 * @return string[] مسیرهای نرمال‌شده (بدونِ دامنه/کوئری/فragment).
 */
function sa_repair_self_paths( $post_id, $url = '' ) {
	$url = '' !== $url ? (string) $url : (string) get_permalink( $post_id );
	if ( '' === $url ) {
		return array();
	}
	$path = (string) wp_parse_url( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ), PHP_URL_PATH );
	if ( '' === $path ) {
		return array();
	}
	$path = '/' . ltrim( $path, '/' );
	$out  = array( $path );
	if ( '/' !== substr( $path, -1 ) ) {
		$out[] = $path . '/';
	} else {
		$out[] = rtrim( $path, '/' );
	}

	return array_values( array_unique( array_filter( $out ) ) );
}

/**
 * نرمال‌کردنِ مسیرِ یک نشانی برای مقایسه.
 *
 * @param string $url نشانی (مطلق یا نسبی).
 * @return string مسیر با اسلشِ ابتدایی و بدونِ کوئری/کوئری و اسلشِ پایانیِ اضافه.
 */
function sa_repair_normalize_path( $url ) {
	$url  = trim( html_entity_decode( (string) $url, ENT_QUOTES, 'UTF-8' ) );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( '' === $path && false === strpos( $url, '://' ) && 0 !== strpos( $url, '//' ) ) {
		$path = $url; // نشانیِ نسبیِ بدونِ دامنه.
	}
	$path = '/' . ltrim( (string) $path, '/' );
	$path = ( '/' === $path ) ? '/' : rtrim( $path, '/' );

	return $path . '/';
}

/**
 * بازکردنِ پیوندهای «به خودِ صفحه» در یک قطعه متن (تابع خالص).
 *
 * برچسبِ `<a>` حذف و فقط متنِ داخلش نگه داشته می‌شود؛ ویژگی‌های متن دست‌نخورده
 * می‌مانند. پیوندهایی که دامنهٔ متفاوت/کوئری/فragment دارند دست‌نخورده می‌مانند
 * (در `$kept` شمرده می‌شوند تا شفاف باشد).
 *
 * @param string $content محتوا.
 * @param array  $paths   مسیرهای خودِ صفحه (خروجی `sa_repair_self_paths`).
 * @param int    $removed (مرجع) شمار پیوندهای بازشده.
 * @param int    $kept    (مرجع) شمار پیوندهای خودی که دست‌نخورده ماندند (fragment/کوئری).
 * @return string
 */
function sa_repair_unwrap_self_links( $content, $paths, &$removed = null, &$kept = null ) {
	$content = (string) $content;
	$removed = 0;
	$kept    = 0;
	if ( '' === $content || ! $paths ) {
		return $content;
	}

	$self = array();
	foreach ( (array) $paths as $path ) {
		$self[ sa_repair_normalize_path( $path ) ] = true;
	}

	// بلوک‌های حساس دست‌نخورده می‌مانند (کد، اسکریپت، متنِ خام).
	$parts = preg_split(
		'~(<(?:pre|code|script|style|textarea)\b.*?</(?:pre|code|script|style|textarea)>)~is',
		$content,
		-1,
		PREG_SPLIT_DELIM_CAPTURE
	);
	if ( ! is_array( $parts ) ) {
		return $content;
	}

	$out = '';
	foreach ( $parts as $i => $part ) {
		if ( 0 !== $i % 2 ) { // قطعهٔ حساس.
			$out .= $part;
			continue;
		}
		$out .= preg_replace_callback(
			'~<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*?>(.*?)</a>~isu',
			function ( $m ) use ( $self, &$removed, &$kept ) {
				$href = (string) $m[2];
				$raw  = trim( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) );
				$key  = sa_repair_normalize_path( $raw );
				if ( ! isset( $self[ $key ] ) ) {
					return $m[0];
				}
				// پیوندِ خودی با کوئری یا fragment (پرشِ داخلِ صفحه) دست‌نخورده می‌ماند.
				$has_query = false !== strpos( $raw, '?' );
				$has_hash  = false !== strpos( $raw, '#' );
				if ( $has_query || $has_hash ) {
					++$kept;
					return $m[0];
				}
				++$removed;
				return (string) $m[3];
			},
			$part
		);
	}

	return $out;
}

/**
 * شمارِ پیوندهای خودارجاع در یک متن.
 *
 * @param string $content محتوا.
 * @param array  $paths   مسیرهای خودِ صفحه.
 * @return int
 */
function sa_repair_selflink_count( $content, $paths ) {
	$removed = 0;
	$kept    = 0;
	sa_repair_unwrap_self_links( $content, $paths, $removed, $kept );

	return (int) $removed + (int) $kept;
}

/**
 * نامزدهای اصلاح: موجودیت‌هایی که در متنِ ذخیره‌شده به خودشان پیوند داده‌اند.
 *
 * @param int $limit سقف بررسی در این اجرا.
 * @return array<int,array{id:int,links:int,paths:string[]}>
 */
function sa_repair_selflink_candidates( $limit = 0 ) {
	$types = array_values( array_filter( array_map( 'strval', (array) sa_entity_types() ) ) );
	if ( ! $types ) {
		return array();
	}
	if ( $limit <= 0 ) {
		$limit = sa_repair_batch_size();
	}

	$q = new WP_Query(
		array(
			'post_type'              => $types,
			'post_status'            => array( 'publish', 'draft', 'pending' ),
			'posts_per_page'         => $limit,
			'no_found_rows'          => true,
			'fields'                 => 'ids',
			'update_post_term_cache' => false,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
		)
	);

	$out = array();
	foreach ( $q->posts as $pid ) {
		$paths = sa_repair_self_paths( $pid );
		if ( ! $paths ) {
			continue;
		}
		$content = (string) get_post_field( 'post_content', $pid );
		if ( '' === $content ) {
			continue;
		}
		$needle = str_replace( '\/', '/', $paths[0] );
		if ( false === strpos( $content, $needle ) && false === strpos( $content, rtrim( $needle, '/' ) ) ) {
			continue; // پیش‌بررسیِ ارزان.
		}
		$links = sa_repair_selflink_count( $content, $paths );
		if ( $links ) {
			$out[] = array(
				'id'    => (int) $pid,
				'links' => $links,
				'paths' => $paths,
			);
		}
	}

	return $out;
}

/**
 * نمونه‌های قابل‌نمایش: متنِ برچسبِ پیوندهای خودارجاع (بدونِ چاپِ کلِ مقاله).
 *
 * @param string $content محتوا.
 * @param array  $paths   مسیرهای خودِ صفحه.
 * @param int    $max     سقف نمونه.
 * @return string[]
 */
function sa_repair_selflink_samples( $content, $paths, $max = 3 ) {
	$self = array();
	foreach ( (array) $paths as $path ) {
		$self[ sa_repair_normalize_path( $path ) ] = true;
	}
	$out = array();
	if ( preg_match_all( '~<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*?>(.*?)</a>~isu', (string) $content, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $match ) {
			$key = sa_repair_normalize_path( $match[2] );
			if ( ! isset( $self[ $key ] ) ) {
				continue;
			}
			$out[] = trim( wp_strip_all_tags( (string) $match[3] ) );
			if ( count( $out ) >= $max ) {
				break;
			}
		}
	}

	return $out;
}

/**
 * اجرای تعمیرِ پیوندهای خودارجاع: پیش‌نمایش یا اعمال.
 *
 * @param string $mode  `dry` یا `apply`.
 * @param int    $limit سقف نوشته در این اجرا.
 * @return array گزارش اجرا.
 */
function sa_repair_selflink_run( $mode = 'dry', $limit = 0 ) {
	$mode   = ( 'apply' === $mode ) ? 'apply' : 'dry';
	$limit  = $limit > 0 ? (int) $limit : sa_repair_batch_size();
	$report = array(
		'mode'     => $mode,
		'time'     => time(),
		'scanned'  => 0,
		'posts'    => 0,
		'links'    => 0,
		'samples'  => array(),
		'failed'   => array(),
		'unchanged' => array(),
		'ids'      => array(),
	);

	$candidates = sa_repair_selflink_candidates( $limit );

	foreach ( $candidates as $candidate ) {
		$pid     = (int) $candidate['id'];
		$paths   = $candidate['paths'];
		$content = (string) get_post_field( 'post_content', $pid );
		$removed = 0;
		$kept    = 0;
		$out     = sa_repair_unwrap_self_links( $content, $paths, $removed, $kept );

		++$report['scanned'];
		if ( ! $removed || $out === $content ) {
			$report['unchanged'][] = $pid;
			continue;
		}

		foreach ( sa_repair_selflink_samples( $content, $paths ) as $sample ) {
			if ( count( $report['samples'] ) < 5 ) {
				$report['samples'][] = $sample;
			}
		}

		if ( 'dry' === $mode ) {
			++$report['posts'];
			$report['links'] += $removed;
			$report['ids'][]  = $pid;
			continue;
		}

		$result = wp_update_post(
			array(
				'ID'           => $pid,
				'post_content' => $out,
			),
			true
		);
		if ( is_wp_error( $result ) || ! $result ) {
			$report['failed'][] = $pid;
			continue;
		}

		++$report['posts'];
		$report['links'] += $removed;
		$report['ids'][]  = $pid;
	}

	if ( 'apply' === $mode && $report['posts'] ) {
		delete_transient( 'sa_health_scan' );
	}

	$report['remaining'] = count( sa_repair_selflink_candidates( $limit + 1 ) ) > $limit;
	$report['batch']     = $limit;
	update_option( 'sa_selflink_last', $report, false );

	return $report;
}

/**
 * آخرین گزارش اجرای تعمیر پیوندهای خودارجاع (یا null).
 *
 * @return array|null
 */
function sa_repair_selflink_last() {
	$last = get_option( 'sa_selflink_last' );
	return is_array( $last ) ? $last : null;
}

/**
 * منطق قابل‌آزمونِ دکمه‌های تعمیر پیوند خودارجاع.
 *
 * @return array|null
 */
function sa_selflink_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازه‌ی این کار را ندارید.', 'sarzaminaryan-child' ) );
		return null;
	}
	check_admin_referer( 'sa_selflink_unwrap' );

	$mode = ( isset( $_POST['sa_selflink_mode'] ) && 'apply' === $_POST['sa_selflink_mode'] ) ? 'apply' : 'dry';
	return sa_repair_selflink_run( $mode );
}

/**
 * مدیریت دکمه‌های «پیش‌نمایش» و «اعمال» پیوندهای خودارجاع.
 */
function sa_selflink_handle() {
	$report = sa_selflink_action();
	$mode   = is_array( $report ) && isset( $report['mode'] ) ? (string) $report['mode'] : 'dry';

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'        => 'sa-content-health',
				'sa_selflink' => $mode,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_sa_selflink_unwrap', 'sa_selflink_handle' );

/**
 * بخش «پیوندهای خودارجاع» در صفحهٔ سلامت محتوا.
 */
function sa_selflink_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$fa    = 'sa_fa_digits';
	$count = count( sa_repair_selflink_candidates( sa_repair_batch_size() ) );
	$batch = sa_repair_batch_size();
	$last  = sa_repair_selflink_last();
	$done  = isset( $_GET['sa_selflink'] ) ? sanitize_key( wp_unslash( $_GET['sa_selflink'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'dry' === $done && $last ) {
		echo '<div class="notice notice-info inline"><p><strong>پیش‌نمایش:</strong> در '
			. esc_html( $fa( (string) $last['posts'] ) ) . ' صفحه، '
			. esc_html( $fa( (string) $last['links'] ) ) . ' پیوندِ خودارجاع پیدا شد. هیچ چیزی تغییر نکرده است.</p>';
		if ( ! empty( $last['samples'] ) ) {
			echo '<p class="description">نمونهٔ متنِ پیوندها: ' . esc_html( implode(' | ', $last['samples'] ) ) . '</p>';
		}
		echo '</div>';
	} elseif ( 'apply' === $done && $last ) {
		echo '<div class="notice notice-success inline"><p><strong>اعمال شد:</strong> '
			. esc_html( $fa( (string) $last['links'] ) ) . ' پیوندِ خودارجاع در '
			. esc_html( $fa( (string) $last['posts'] ) ) . ' صفحه باز شد (فقط برچسبِ پیوند حذف شد؛ متن دست‌نخورده است). '
			. 'هر تغییر یک نسخهٔ بازبینی دارد.</p></div>';
	}

	echo '<h2>تعمیر مکانیکی: پیوندِ خودارجاع در متن</h2>';
	echo '<p>پیوند دادنِ یک صفحه به خودش («شهرستان X» ← <code>/city/x/</code>) ارزش ناوبری ندارد و در ممیزیِ '
		. '۱۴۰۵-۰۷-۱۵ برای ۱۷۶ صفحهٔ منتشرشده دیده شد. این ابزار فقط <code>&lt;a&gt;</code> را برمی‌دارد و متن را نگه می‌دارد؛ '
		. 'پیوندهای دارای <code>#</code> یا کوئری (پرشِ داخلِ صفحه) و بلوک‌های کد دست‌نخورده می‌مانند.</p>';

	if ( ! $count ) {
		echo '<p><strong>موردی نیست. ✓</strong></p>';
		return;
	}

	echo '<p><strong>' . esc_html( $fa( (string) $count ) ) . '</strong> نوشته (از سقف '
		. esc_html( $fa( (string) $batch ) ) . ' موردِ بررسی‌شده) پیوندِ خودارجاع دارد.</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
	echo '<input type="hidden" name="action" value="sa_selflink_unwrap">';
	wp_nonce_field( 'sa_selflink_unwrap' );
	echo '<button type="submit" class="button" name="sa_selflink_mode" value="dry">پیش‌نمایش (بدون تغییر)</button>';
	echo '<button type="submit" class="button button-primary" name="sa_selflink_mode" value="apply" '
		. 'onclick="return confirm(\'برچسبِ همهٔ پیوندهای خودارجاع حذف شوند؟ متن دست‌نخورده می‌ماند.\');">اعمال حذفِ پیوند خودارجاع</button>';
	echo '<span class="description">هر اجرا حداکثر ' . esc_html( $fa( (string) $batch ) ) . ' نوشته؛ برای بقیه دوباره بزنید.</span>';
	echo '</form>';

	if ( $last && ! empty( $last['failed'] ) ) {
		echo '<p class="description">در آخرین اجرا ' . esc_html( $fa( (string) count( $last['failed'] ) ) ) . ' نوشته ذخیره نشد (خطای دیتابیس).</p>';
	}
}

/**
 * منطق قابل‌آزمونِ دکمه‌ها: بررسی دسترسی، nonce و اجرای تعمیر.
 *
 * (جدا از `sa_repair_handle` نگه داشته شده تا تست بتواند بدون `exit` اجرا شود.)
 *
 * @return array|null گزارش اجرا یا null در نبود دسترسی.
 */
function sa_repair_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازه‌ی این کار را ندارید.', 'sarzaminaryan-child' ) );
		return null; // در وردپرس، wp_die اجرا را پایان می‌دهد؛ این خط برای تست‌پذیری است.
	}
	check_admin_referer( 'sa_repair_h1' );

	$mode = ( isset( $_POST['sa_repair_mode'] ) && 'apply' === $_POST['sa_repair_mode'] ) ? 'apply' : 'dry';
	return sa_repair_run( $mode );
}

/**
 * مدیریت دکمه‌های «پیش‌نمایش» و «اعمال».
 */
function sa_repair_handle() {
	$report = sa_repair_action();
	$mode   = is_array( $report ) && isset( $report['mode'] ) ? (string) $report['mode'] : 'dry';

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'      => 'sa-content-health',
				'sa_repair' => $mode,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_sa_repair_h1', 'sa_repair_handle' );

/**
 * بخش «تعمیر مکانیکی» در صفحهٔ سلامت محتوا.
 */
function sa_repair_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$fa        = 'sa_fa_digits';
	$count     = sa_repair_h1_count();
	$batch     = sa_repair_batch_size();
	$last      = sa_repair_last();
	$done      = isset( $_GET['sa_repair'] ) ? sanitize_key( wp_unslash( $_GET['sa_repair'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( 'dry' === $done && $last ) {
		echo '<div class="notice notice-info inline"><p><strong>پیش‌نمایش:</strong> '
			. esc_html( $fa( (string) $last['posts'] ) ) . ' صفحه و '
			. esc_html( $fa( (string) $last['headings'] ) ) . ' سربرگ داخل بدنه آماده‌ی تنزل به H2 است. هیچ چیزی تغییر نکرده است.</p></div>';
	} elseif ( 'apply' === $done && $last ) {
		echo '<div class="notice notice-success inline"><p><strong>اعمال شد:</strong> '
			. esc_html( $fa( (string) $last['posts'] ) ) . ' صفحه اصلاح و '
			. esc_html( $fa( (string) $last['headings'] ) ) . ' سربرگ به H2 تبدیل شد. '
			. 'هر تغییر یک نسخهٔ بازبینی دارد، پس از پیشخوان قابل بازگشت است.</p></div>';
	}

	echo '<h2>تعمیر مکانیکی: H1 داخل بدنه</h2>';
	echo '<p>هر موجودیت باید یک <code>H1</code> داشته باشد و آن هم از هیروی صفحه می‌آید. '
		. 'هر <code>H1</code> داخل متن ذخیره‌شده، صفحه را دو سربرگ اصلی می‌کند. این ابزار فقط برچسب را تبدیل می‌کند؛ '
		. 'متن، ویژگی‌ها و کلاس‌ها دست‌نخورده می‌مانند و دروازه‌ی انتشار هم پیش‌تر جلوی نمونه‌های تازه را می‌گیرد.</p>';

	if ( ! $count ) {
		echo '<p><strong>موردی نیست. ✓</strong></p>';
		return;
	}

	echo '<p><strong>' . esc_html( $fa( (string) $count ) ) . '</strong> نوشته (از سقف '
		. esc_html( $fa( (string) $batch ) ) . ' موردِ بررسی‌شده) سربرگ اصلی داخل بدنه دارد.</p>';

	$action = esc_url( admin_url( 'admin-post.php' ) );
	echo '<form method="post" action="' . $action . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
	echo '<input type="hidden" name="action" value="sa_repair_h1">';
	wp_nonce_field( 'sa_repair_h1' );
	echo '<button type="submit" class="button" name="sa_repair_mode" value="dry">پیش‌نمایش (بدون تغییر)</button>';
	echo '<button type="submit" class="button button-primary" name="sa_repair_mode" value="apply" '
		. 'onclick="return confirm(\'همه‌ی سربرگ‌های H1 داخل بدنه به H2 تبدیل شوند؟\');">اعمال تنزل H1 → H2</button>';
	echo '<span class="description">هر اجرا حداکثر ' . esc_html( $fa( (string) $batch ) ) . ' نوشته؛ برای بقیه دوباره بزنید.</span>';
	echo '</form>';

	if ( $last && ! empty( $last['failed'] ) ) {
		echo '<p class="description">در آخرین اجرا ' . esc_html( $fa( (string) count( $last['failed'] ) ) ) . ' نوشته ذخیره نشد (خطای دیتابیس).</p>';
	}
	if ( $last && ! empty( $last['unpaired'] ) ) {
		echo '<p class="description">' . esc_html( $fa( (string) $last['unpaired'] ) )
			. ' برچسب <code>h1</code> بدون بستهٔ <code>&lt;/h1&gt;</code> پیدا شد؛ این‌ها دستی بررسی شوند.</p>';
	}
}
