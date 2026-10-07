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
 * - فقط تبدیل مکانیکی `<h1>` به `<h2>` انجام می‌شود. هر ایراد دیگری که ممیزی
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
