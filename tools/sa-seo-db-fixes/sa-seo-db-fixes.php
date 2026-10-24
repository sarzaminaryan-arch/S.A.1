<?php
/**
 * Plugin Name: SA SEO DB Fixes (one-shot)
 * Description: عملیات یک‌بارمصرف دیتابیس برای SEO سرزمین آریان — ۱) تنظیم صفحهٔ نوشته‌ها (page_for_posts) روی برگهٔ وبلاگ، ۲) تغییر نام برگهٔ policy به editorial-policy + اصلاح لینک‌های منو، ۳+۴) تغییر نام نامک‌های پسونددار + بازنویسی پیوندها، ۵) تکمیل: جابه‌جایی مانع غیرمنتشرِ نامک پایه + بازنویسی پیوندهای باقیمانده، ۶) پاک‌سازی ارجاع‌های مردهٔ W_* (حذف href مرده، نگه‌داشتن شمارهٔ ارجاع + تبدیل توکن‌های خام جامانده). با گزارش خشک، تأیید صریح و لاگ برگشت‌پذیر.
 * Version: 1.4.0
 * Author: S.A. Agent
 * License: GPL-2.0-or-later
 *
 * @package SA_SEO_DB_Fixes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خواندن/افزودن لاگ عملیات (برای برگشت‌پذیری).
 *
 * @param array|null $entry رکورد جدید؛ null = فقط خواندن.
 * @return array
 */
function sa_dbf_log( $entry = null ) {
	$log = (array) get_option( 'sa_db_fixes_log', array() );
	if ( is_array( $entry ) ) {
		$entry['at'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$log[]       = $entry;
		update_option( 'sa_db_fixes_log', array_slice( $log, -50 ), false );
	}
	return $log;
}

/**
 * پاک‌سازی کش LiteSpeed در صورت وجود.
 *
 * @return void
 */
function sa_dbf_purge() {
	if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed_Cache_API' ) || did_action( 'litespeed_init' ) ) {
		do_action( 'litespeed_purge_all' );
	}
}

/**
 * دادهٔ عملیات ۱: صفحهٔ نوشته‌ها.
 *
 * @return array
 */
function sa_dbf_op1_state() {
	$pages   = (array) get_option( 'sa_default_pages', array() );
	$current = (int) get_option( 'page_for_posts' );
	$target  = isset( $pages['blog'] ) ? (int) $pages['blog'] : 0;
	$cur_p   = $current ? get_post( $current ) : null;
	$tgt_p   = $target ? get_post( $target ) : null;
	return array(
		'current_id'     => $current,
		'current_state'  => $cur_p ? ( $cur_p->post_status . ' / ' . $cur_p->post_name ) : 'برگه وجود ندارد (حذف‌شده)',
		'target_id'      => $target,
		'target_state'   => $tgt_p ? ( $tgt_p->post_status . ' / ' . $tgt_p->post_name . ' / ' . $tgt_p->post_title ) : 'در sa_default_pages.blog یافت نشد',
		'target_valid'   => (bool) $tgt_p && 'page' === $tgt_p->post_type && 'publish' === $tgt_p->post_status,
		'already_done'   => $current === $target && $current > 0,
		'show_on_front'  => (string) get_option( 'show_on_front' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
	);
}

/**
 * اجرای عملیات ۱.
 *
 * @return array گزارش قبل/بعد.
 */
function sa_dbf_op1_apply() {
	$before = sa_dbf_op1_state();
	if ( ! $before['target_valid'] ) {
		return array( 'ok' => false, 'msg' => 'مقصد نامعتبر است؛ هیچ تغییری اعمال نشد.', 'before' => $before, 'after' => $before );
	}
	update_option( 'page_for_posts', $before['target_id'] );
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'     => 'page_for_posts',
			'before' => $before['current_id'],
			'after'  => $before['target_id'],
		)
	);
	return array( 'ok' => true, 'msg' => 'page_for_posts از ' . $before['current_id'] . ' به ' . $before['target_id'] . ' تغییر کرد. /blog/ اکنون آرشیو نوشته‌هاست.', 'before' => $before, 'after' => sa_dbf_op1_state() );
}

/**
 * دادهٔ عملیات ۲: برگهٔ سیاست تحریریه.
 *
 * @return array
 */
function sa_dbf_op2_state() {
	$pages   = (array) get_option( 'sa_default_pages', array() );
	$pid     = isset( $pages['policy'] ) ? (int) $pages['policy'] : 0;
	$post    = $pid ? get_post( $pid ) : null;
	$conflict = get_page_by_path( 'editorial-policy', OBJECT, 'page' );
	$menus   = array();
	if ( $post ) {
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'meta_query'     => array( // phpcs:ignore
					array( 'key' => '_menu_item_object_id', 'value' => $pid ),
				),
			)
		);
		foreach ( $items as $it ) {
			$menus[] = 'آیتم منو #' . $it->ID . ' (نوع: ' . get_post_meta( $it->ID, '_menu_item_type', true ) . ')';
		}
	}
	$custom = get_posts(
		array(
			'post_type'      => 'nav_menu_item',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			's'              => '/policy/',
		)
	);
	$custom_links = array();
	foreach ( $custom as $c ) {
		if ( 'custom' === get_post_meta( $c->ID, '_menu_item_type', true ) && false !== strpos( (string) $c->post_content, '/policy/' ) ) {
			$custom_links[] = $c->ID . ': ' . $c->post_content;
		}
	}
	return array(
		'page_id'        => $pid,
		'exists'         => (bool) $post,
		'slug'           => $post ? $post->post_name : '',
		'title'          => $post ? $post->post_title : '',
		'status'         => $post ? $post->post_status : '',
		'chars'          => $post ? mb_strlen( (string) $post->post_content ) : 0,
		'conflict_id'    => $conflict ? (int) $conflict->ID : 0,
		'object_items'   => $menus,
		'custom_links'   => $custom_links,
		'already_done'   => $post && 'editorial-policy' === $post->post_name,
	);
}

/**
 * اجرای عملیات ۲.
 *
 * @return array گزارش قبل/بعد.
 */
function sa_dbf_op2_apply() {
	$before = sa_dbf_op2_state();
	if ( ! $before['exists'] || 'publish' !== $before['status'] ) {
		return array( 'ok' => false, 'msg' => 'برگهٔ policy یافت/منتشر نشد؛ تغییری اعمال نشد.', 'before' => $before, 'after' => $before );
	}
	if ( $before['already_done'] ) {
		return array( 'ok' => true, 'msg' => 'پیش‌تر انجام شده بود (نامک = editorial-policy).', 'before' => $before, 'after' => $before );
	}
	if ( $before['conflict_id'] ) {
		return array( 'ok' => false, 'msg' => 'تداخل: برگهٔ دیگری با نامک editorial-policy موجود است (#' . $before['conflict_id'] . '). دستی بررسی کنید.', 'before' => $before, 'after' => $before );
	}
	$new_id = wp_update_post(
		array(
			'ID'        => $before['page_id'],
			'post_name' => 'editorial-policy',
		),
		true
	);
	if ( is_wp_error( $new_id ) ) {
		return array( 'ok' => false, 'msg' => 'خطا در تغییر نام: ' . $new_id->get_error_message(), 'before' => $before, 'after' => $before );
	}
	// اصلاح لینک‌های سفارشی منوها به /policy/.
	$fixed_links = 0;
	foreach ( $before['custom_links'] as $row ) {
		$item_id = (int) explode( ':', $row, 2 )[0];
		$item    = get_post( $item_id );
		if ( $item && false !== strpos( (string) $item->post_content, '/policy/' ) ) {
			wp_update_post(
				array(
					'ID'           => $item_id,
					'post_content' => home_url( '/editorial-policy/' ),
				)
			);
			$fixed_links++;
		}
	}
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'          => 'policy_rename',
			'page_id'     => $before['page_id'],
			'before_slug' => $before['slug'],
			'after_slug'  => get_post_field( 'post_name', $before['page_id'] ),
			'menu_links'  => $fixed_links,
		)
	);
	return array(
		'ok'     => true,
		'msg'    => 'برگهٔ #' . $before['page_id'] . ' به نامک editorial-policy تغییر یافت؛ ' . $fixed_links . ' لینک سفارشی منو اصلاح شد. ۳۰۱ِ /policy/ (قالب ۲٫۲۲٫۳۵) اکنون فعال است.',
		'before' => $before,
		'after'  => sa_dbf_op2_state(),
	);
}


/**
 * فهرست برنامه‌ریزی‌شدهٔ تغییر نام‌ها (B2): نامک پسونددار => نامک پایه.
 * bushehr-county عمداً نمی‌آید: پس از تغییر نام bushehr-city، خودش مبدأ ۳۰۱ می‌ماند.
 *
 * @return array<string,string>
 */
function sa_dbf_planned_renames() {
	return array(
		'ahvaz-city'    => 'ahvaz',
		'ardabil-city'  => 'ardabil',
		'bushehr-city'  => 'bushehr',
		'dorud-city'    => 'dorud',
		'ilam-city'     => 'ilam',
		'isfahan-city'  => 'isfahan',
		'qazvin-city'   => 'qazvin',
		'qom-city'      => 'qom',
		'semnan-city'   => 'semnan',
		'tehran-city'   => 'tehran',
		'zanjan-city'   => 'zanjan',
	);
}

/**
 * آیا مقصد (با در نظر گرفتن تغییر نام‌های برنامه‌ریزی‌شده) در دسترس خواهد بود؟
 *
 * @param string $target  مسیر مقصد.
 * @param bool   $planned نظر گرفتن نامک‌های پایهٔ در صف تغییر نام.
 * @return bool
 */
function sa_dbf_target_will_exist( $target, $planned ) {
	if ( function_exists( 'sa_redirect_target_exists' ) && sa_redirect_target_exists( $target ) ) {
		return true;
	}
	if ( ! $planned ) {
		return false;
	}
	$parts = explode( '/', trim( (string) $target, '/' ) );
	if ( 2 !== count( $parts ) || 'city' !== $parts[0] ) {
		return false;
	}
	return in_array( $parts[1], array_values( sa_dbf_planned_renames() ), true );
}

/**
 * مقصد نهایی یک مسیر با نقشهٔ قالب (با در نظر گرفتن تغییر نام‌های در صف).
 *
 * @param string $path مسیر.
 * @return string
 */
function sa_dbf_final_target( $path ) {
	if ( ! function_exists( 'sa_redirect_target_for_path' ) ) {
		return '';
	}
	$target = (string) sa_redirect_target_for_path( $path );
	if ( '' === $target || $target === $path ) {
		return '';
	}
	return $target;
}

/**
 * شناسهٔ نوشته‌ها/آیتم‌های منوی در scope بازنویسی پیوند.
 *
 * @param array|string $statuses وضعیت‌های مجاز.
 * @return int[]
 */
function sa_dbf_rewrite_ids( $statuses = array( 'publish' ) ) {
	$ids = get_posts(
		array(
			'post_type'      => array( 'post', 'page', 'city', 'province', 'attraction' ),
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	return array_merge(
		$ids,
		get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'post_status'    => $statuses,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		)
	);
}

/**
 * پویش پیوندهای درون‌متنی قابل بازنویسی.
 *
 * @param bool         $planned  نظر گرفتن تغییر نام‌های در صف برای اعتبار مقصد.
 * @param array|string $statuses وضعیت‌های پست برای پویش.
 * @return array{paths:array<string,int>, posts:int, total:int}
 */
function sa_dbf_scan_links( $planned, $statuses = array( 'publish' ) ) {
	$found = array();
	$posts = sa_dbf_rewrite_ids( $statuses );
	$total = 0;
	foreach ( $posts as $pid ) {
		$content = (string) get_post_field( 'post_content', $pid );
		if ( '' === $content || false === strpos( $content, 'href="/' ) ) {
			continue;
		}
		if ( ! preg_match_all( '~href="(/[^"#?]+)"~', $content, $mm ) ) {
			continue;
		}
		foreach ( array_unique( $mm[1] ) as $path ) {
			$target = sa_dbf_final_target( $path );
			if ( '' === $target || ! sa_dbf_target_will_exist( $target, $planned ) ) {
				continue;
			}
			$n           = substr_count( $content, 'href="' . $path . '"' );
			$found[ $path ] = ( $found[ $path ] ?? 0 ) + $n;
			$total      += $n;
		}
	}
	return array( 'paths' => $found, 'posts' => count( $posts ), 'total' => $total );
}

/**
 * گزارش خشک عملیات ۳+۴.
 *
 * @return array
 */
function sa_dbf_op34_state() {
	$renames = array();
	foreach ( sa_dbf_planned_renames() as $from => $to ) {
		$src_post  = get_page_by_path( $from, OBJECT, 'city' );
		$dst_post  = get_page_by_path( $to, OBJECT, 'city' );
		$renames[] = array(
			'from'      => $from,
			'to'        => $to,
			'src_id'    => $src_post ? (int) $src_post->ID : 0,
			'src_ok'    => (bool) $src_post && 'publish' === $src_post->post_status,
			'dst_free'  => ! $dst_post,
		);
	}
	$scan = sa_dbf_scan_links( true );
	return array(
		'renames'   => $renames,
		'scan'      => $scan,
		'theme_ok'  => function_exists( 'sa_redirect_target_for_path' ) && function_exists( 'sa_redirect_target_exists' ),
	);
}

/**
 * اجرای اتمی عملیات ۳+۴: نخست تغییر نام‌ها، سپس بازنویسی پیوندها، سپس کش.
 *
 * @return array گزارش.
 */
function sa_dbf_op34_apply() {
	$state = sa_dbf_op34_state();
	if ( ! $state['theme_ok'] ) {
		return array( 'ok' => false, 'msg' => 'توابع نقشهٔ قالب در دسترس نیست؛ قالب ۲٫۲۲٫۳۵+ فعال است؟', 'detail' => array() );
	}
	$detail = array();
	// گام ۱: تغییر نام‌ها.
	$renamed = 0;
	foreach ( $state['renames'] as $r ) {
		if ( ! $r['src_ok'] || ! $r['dst_free'] ) {
			$detail[] = 'SKIP rename ' . $r['from'] . ' (src_ok=' . var_export( $r['src_ok'], true ) . ', dst_free=' . var_export( $r['dst_free'], true ) . ')';
			continue;
		}
		wp_update_post( array( 'ID' => $r['src_id'], 'post_name' => $r['to'] ), true );
		$now = (string) get_post_field( 'post_name', $r['src_id'] );
		if ( $now === $r['to'] ) {
			$renamed++;
			$detail[] = 'RENAMED #' . $r['src_id'] . ' ' . $r['from'] . ' -> ' . $r['to'];
		} else {
			$detail[] = 'FAIL rename #' . $r['src_id'] . ' ' . $r['from'] . ' (slug فعلی: ' . $now . ')';
		}
	}
	// گام ۲: بازنویسی پیوندها (اکنون همهٔ مقصدها وجود دارند).
	$scan    = sa_dbf_scan_links( false );
	$changed = 0;
	$repl    = 0;
	$all_ids = sa_dbf_rewrite_ids();
	foreach ( $scan['paths'] as $path => $n ) {
		$target = sa_dbf_final_target( $path );
		if ( '' === $target || ! sa_redirect_target_exists( $target ) ) {
			continue;
		}
		$needle = 'href="' . $path . '"';
		foreach ( $all_ids as $pid ) {
			$content = (string) get_post_field( 'post_content', $pid );
			if ( false === strpos( $content, $needle ) ) {
				continue;
			}
			$new = str_replace( $needle, 'href="' . $target . '"', $content, $c );
			wp_update_post( array( 'ID' => $pid, 'post_content' => $new ) );
			$changed++;
			$repl   += $c;
		}
		$detail[] = 'LINKS ' . $path . ' -> ' . $target . ' (' . $n . ' مورد)';
	}
	// گام ۳: کش و لاگ.
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'      => 'rename_and_links',
			'renamed' => $renamed,
			'posts'   => $changed,
			'links'   => $repl,
		)
	);
	return array(
		'ok'     => true,
		'msg'    => $renamed . ' نامک تغییر یافت؛ ' . $repl . ' پیوند در ' . $changed . ' نوشته/آیتم منو بازنویسی شد.',
		'detail' => $detail,
	);
}

/**
 * گزارش خشک عملیات ۵: مانع‌های غیرمنتشرِ نامک پایه + پیوندهای باقیمانده.
 *
 * @return array
 */
function sa_dbf_op5_state() {
	$blockers = array();
	foreach ( sa_dbf_planned_renames() as $from => $to ) {
		$src = get_page_by_path( $from, OBJECT, 'city' );
		$dst = get_page_by_path( $to, OBJECT, 'city' );
		if ( ! $src || 'publish' !== $src->post_status || ! $dst ) {
			continue;
		}
		$blockers[] = array(
			'from'       => $from,
			'to'         => $to,
			'src_id'     => (int) $src->ID,
			'blk_id'     => (int) $dst->ID,
			'blk_status' => (string) $dst->post_status,
			'blk_title'  => (string) get_the_title( $dst ),
			'blk_chars'  => (int) mb_strlen( (string) $dst->post_content ),
			'safe'       => 'publish' !== $dst->post_status,
		);
	}
	$scan = sa_dbf_scan_links( true, array( 'publish', 'draft', 'pending', 'private' ) );
	return array(
		'blockers' => $blockers,
		'scan'     => $scan,
		'theme_ok' => function_exists( 'sa_redirect_target_for_path' ) && function_exists( 'sa_redirect_target_exists' ),
	);
}

/**
 * اجرای اتمی عملیات ۵: جابه‌جایی مانع غیرمنتشر ← تغییر نام ← بازنویسی پیوندهای باقیمانده ← کش.
 *
 * مانع منتشر هرگز جابه‌جا نمی‌شود (نیاز به تصمیم انسانی)؛ فقط draft/pending/private/trash.
 *
 * @return array گزارش.
 */
function sa_dbf_op5_apply() {
	$state = sa_dbf_op5_state();
	if ( ! $state['theme_ok'] ) {
		return array( 'ok' => false, 'msg' => 'توابع نقشهٔ قالب در دسترس نیست؛ قالب ۲٫۲۲٫۳۵+ فعال است؟', 'detail' => array() );
	}
	$detail = array();
	$moved  = 0;
	foreach ( $state['blockers'] as $b ) {
		if ( ! $b['safe'] ) {
			$detail[] = 'SKIP blocker #' . $b['blk_id'] . ' (منتشر است؛ نیاز به تصمیم انسانی)';
			continue;
		}
		$new_slug = $b['to'] . '-blocked-' . $b['blk_id'];
		wp_update_post( array( 'ID' => $b['blk_id'], 'post_name' => $new_slug ), true );
		clean_post_cache( $b['blk_id'] );
		$now = (string) get_post_field( 'post_name', $b['blk_id'] );
		if ( $now === $new_slug ) {
			$moved++;
			$detail[] = 'BLOCKER-MOVED #' . $b['blk_id'] . ' ' . $b['to'] . ' -> ' . $new_slug . ' (وضعیت: ' . $b['blk_status'] . '، ' . $b['blk_chars'] . ' نویسه)';
		} else {
			$detail[] = 'FAIL blocker #' . $b['blk_id'] . ' (نامک فعلی: ' . $now . ')';
		}
	}
	$renamed = 0;
	foreach ( sa_dbf_planned_renames() as $from => $to ) {
		$src = get_page_by_path( $from, OBJECT, 'city' );
		$dst = get_page_by_path( $to, OBJECT, 'city' );
		if ( ! $src || 'publish' !== $src->post_status ) {
			continue;
		}
		if ( $dst ) {
			$detail[] = 'SKIP rename ' . $from . ' (مقصد هنوز اشغال است: #' . (int) $dst->ID . ')';
			continue;
		}
		wp_update_post( array( 'ID' => (int) $src->ID, 'post_name' => $to ), true );
		clean_post_cache( (int) $src->ID );
		$now = (string) get_post_field( 'post_name', (int) $src->ID );
		if ( $now === $to ) {
			$renamed++;
			$detail[] = 'RENAMED #' . $src->ID . ' ' . $from . ' -> ' . $to;
		} else {
			$detail[] = 'FAIL rename #' . $src->ID . ' ' . $from . ' (نامک فعلی: ' . $now . ')';
		}
	}
	$statuses = array( 'publish', 'draft', 'pending', 'private' );
	$scan     = sa_dbf_scan_links( false, $statuses );
	$all_ids  = sa_dbf_rewrite_ids( $statuses );
	$changed  = 0;
	$repl     = 0;
	foreach ( $scan['paths'] as $path => $n ) {
		$target = sa_dbf_final_target( $path );
		if ( '' === $target || ! sa_redirect_target_exists( $target ) ) {
			continue;
		}
		$needle = 'href="' . $path . '"';
		foreach ( $all_ids as $pid ) {
			$content = (string) get_post_field( 'post_content', $pid );
			if ( false === strpos( $content, $needle ) ) {
				continue;
			}
			$new = str_replace( $needle, 'href="' . $target . '"', $content, $c );
			wp_update_post( array( 'ID' => $pid, 'post_content' => $new ) );
			$changed++;
			$repl   += $c;
		}
		$detail[] = 'LINKS ' . $path . ' -> ' . $target . ' (' . $n . ' مورد)';
	}
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'             => 'complete_renames',
			'blockers_moved' => $moved,
			'renamed'        => $renamed,
			'posts'          => $changed,
			'links'          => $repl,
		)
	);
	return array(
		'ok'     => true,
		'msg'    => $moved . ' مانع جابه‌جا شد؛ ' . $renamed . ' تغییر نام؛ ' . $repl . ' پیوند در ' . $changed . ' نوشته بازنویسی شد.',
		'detail' => $detail,
	);
}

/**
 * الگوی ارجاع مردهٔ W_* داخل <sup> (href نسبیِ بدون مقصد).
 *
 * @return string
 */
function sa_dbf_wstar_sup_regex() {
	return '~<sup([^>]*)>\s*<a[^>]*\bhref=(["\'])W_[A-Za-z0-9_]+\2[^>]*>(.*?)</a>\s*</sup>~s';
}

/**
 * الگوی برهنهٔ ارجاع مردهٔ W_* (بیرون از <sup>).
 *
 * @return string
 */
function sa_dbf_wstar_bare_regex() {
	return '~<a[^>]*\bhref=(["\'])W_[A-Za-z0-9_]+\1[^>]*>(.*?)</a>~s';
}

/**
 * الگوی توکن جاماندهٔ ارجاع: {c(W_CODE, "۱")} (متن خام، بدون هیچ لینکی).
 *
 * @return string
 */
function sa_dbf_wstar_token_regex() {
	return '~\{c\(\s*W_[A-Za-z0-9_]+\s*,\s*(["\'])(.*?)\1\s*\)\}~s';
}

/**
 * گزارش خشک عملیات ۶: فهرست نوشته‌های دارای ارجاع مردهٔ W_*.
 *
 * @return array
 */
function sa_dbf_op6_state() {
	$rows  = array();
	$total = 0;
	foreach ( sa_dbf_rewrite_ids( array( 'publish', 'draft', 'pending', 'private' ) ) as $pid ) {
		$content = (string) get_post_field( 'post_content', $pid );
		if ( '' === $content || ( false === strpos( $content, 'href="W_' ) && false === strpos( $content, "href='W_" ) && false === strpos( $content, '{c(W_' ) ) ) {
			continue;
		}
		$occ = substr_count( $content, 'href="W_' ) + substr_count( $content, "href='W_" );
		if ( preg_match_all( sa_dbf_wstar_token_regex(), $content, $tm ) ) {
			$occ += count( $tm[0] );
		}
		if ( $occ < 1 ) {
			continue;
		}
		$post     = get_post( $pid );
		$rows[]   = array(
			'id'     => (int) $pid,
			'type'   => (string) $post->post_type,
			'slug'   => (string) $post->post_name,
			'status' => (string) $post->post_status,
			'occ'    => $occ,
		);
		$total   += $occ;
	}
	return array( 'rows' => $rows, 'total' => $total );
}

/**
 * اجرای عملیات ۶: حذف href مردهٔ W_* و نگه‌داشتن شمارهٔ ارجاع.
 *
 * تبدیل: <sup class="sa-cite"><a href="W_X">۱</a></sup> ← <sup class="sa-cite">۱</sup>
 * و برهنه: <a href="W_X">متن</a> ← متن
 *
 * @return array گزارش.
 */
function sa_dbf_op6_apply() {
	$state   = sa_dbf_op6_state();
	$changed = 0;
	$repl    = 0;
	$detail  = array();
	foreach ( $state['rows'] as $row ) {
		$content = (string) get_post_field( 'post_content', $row['id'] );
		$new     = preg_replace( sa_dbf_wstar_sup_regex(), '<sup$1>$3</sup>', $content );
		$new     = preg_replace( sa_dbf_wstar_bare_regex(), '$2', $new );
		$new     = preg_replace( sa_dbf_wstar_token_regex(), '<sup class="sa-cite">$2</sup>', $new );
		$left    = substr_count( $new, 'href="W_' ) + substr_count( $new, "href='W_" );
		if ( preg_match_all( sa_dbf_wstar_token_regex(), $new, $lm ) ) {
			$left += count( $lm[0] );
		}
		$done    = $row['occ'] - $left;
		if ( $done < 1 || $new === $content ) {
			$detail[] = 'SKIP #' . $row['id'] . ' (بدون تغییر)';
			continue;
		}
		wp_update_post( array( 'ID' => $row['id'], 'post_content' => $new ) );
		$changed++;
		$repl   += $done;
		$detail[] = 'UNWRAP #' . $row['id'] . ' ' . $row['slug'] . ' (' . $done . ' مورد)';
	}
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'    => 'w_star_unwrap',
			'posts' => $changed,
			'occ'   => $repl,
		)
	);
	return array(
		'ok'     => true,
		'msg'    => $repl . ' ارجاع مردهٔ W_* در ' . $changed . ' نوشته پاک‌سازی شد (شمارهٔ ارجاع نگه داشته شد).',
		'detail' => $detail,
	);
}

/**
 * منوی ابزارها.
 */
function sa_dbf_menu() {
	add_management_page( 'صفحه‌ها و تنظیمات SEO', 'صفحه‌ها و تنظیمات SEO', 'manage_options', 'sa-seo-db-fixes', 'sa_dbf_render' );
}
add_action( 'admin_menu', 'sa_dbf_menu' );

/**
 * رندر صفحه.
 */
function sa_dbf_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$result = null;
	$error  = '';
	if ( isset( $_POST['sa_dbf_op'] ) ) {
		$op = sanitize_key( (string) $_POST['sa_dbf_op'] );
		if ( ! isset( $_POST['sa_dbf_nonce'] ) || ! wp_verify_nonce( sanitize_key( (string) $_POST['sa_dbf_nonce'] ), 'sa_dbf_' . $op ) ) {
			$error = 'کلید اعتبارسنجی نامعتبر بود؛ هیچ تغییری اعمال نشد.';
		} elseif ( empty( $_POST['sa_dbf_confirm'] ) ) {
			$error = 'جعبهٔ تأیید علامت نخورد؛ هیچ تغییری اعمال نشد.';
		} elseif ( 'op1' === $op ) {
			$result = sa_dbf_op1_apply();
		} elseif ( 'op2' === $op ) {
			$result = sa_dbf_op2_apply();
	} elseif ( 'op34' === $op ) {
		$result = sa_dbf_op34_apply();
	} elseif ( 'op5' === $op ) {
		$result = sa_dbf_op5_apply();
	} elseif ( 'op6' === $op ) {
		$result = sa_dbf_op6_apply();
	} else {
			$error = 'عملیات ناشناخته.';
		}
	}
	$s1 = sa_dbf_op1_state();
	$s2 = sa_dbf_op2_state();
	?>
	<div class="wrap" dir="rtl" style="max-width:960px">
		<h1>صفحه‌ها و تنظیمات SEO — SA SEO DB Fixes 1.4.0</h1>
		<p>دو عملیات یک‌بارمصرف با گزارش قبل/بعد و لاگ برگشت‌پذیر (گزینهٔ <code>sa_db_fixes_log</code>). پیش از اجرا، پشتیبان UpdraftPlus تازه بگیرید. پس از اتمام هر دو، همین افزونه را حذف کنید.</p>

		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<?php if ( $result ) : ?>
			<div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?>"><p><strong><?php echo esc_html( $result['msg'] ); ?></strong></p>
			<?php if ( ! empty( $result['detail'] ) ) : ?><details open><summary>ریز عملیات</summary><ul><?php foreach ( (array) $result['detail'] as $line ) : ?><li><code><?php echo esc_html( $line ); ?></code></li><?php endforeach; ?></ul></details><?php endif; ?></div>
		<?php endif; ?>

		<h2>عملیات ۱ — صفحهٔ نوشته‌ها (page_for_posts)</h2>
		<table class="widefat striped" style="max-width:760px">
			<tr><th>وضعیت فعلی</th><td>شناسهٔ <?php echo (int) $s1['current_id']; ?> — <?php echo esc_html( $s1['current_state'] ); ?></td></tr>
			<tr><th>مقصد (sa_default_pages.blog)</th><td>شناسهٔ <?php echo (int) $s1['target_id']; ?> — <?php echo esc_html( $s1['target_state'] ); ?></td></tr>
			<tr><th>show_on_front / page_on_front</th><td><?php echo esc_html( $s1['show_on_front'] ); ?> / <?php echo (int) $s1['page_on_front']; ?></td></tr>
		</table>
		<?php if ( $s1['already_done'] ) : ?>
			<p class="description">✔ پیش‌تر انجام شده — نیازی به اجرا نیست.</p>
		<?php elseif ( $s1['target_valid'] ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'sa_dbf_op1', 'sa_dbf_nonce' ); ?>
				<input type="hidden" name="sa_dbf_op" value="op1">
				<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان دارم و می‌دانم page_for_posts تغییر می‌کند (قابل بازگردانی با لاگ زیر).</label>
				<?php submit_button( 'اجرای عملیات ۱', 'primary', 'submit', true ); ?>
			</form>
		<?php else : ?>
			<p class="description">مقصد معتبر نیست — ابتدا برگهٔ وبلاگ را بررسی کنید.</p>
		<?php endif; ?>

		<h2>عملیات ۲ — برگهٔ سیاست تحریریه (policy → editorial-policy)</h2>
		<table class="widefat striped" style="max-width:760px">
			<tr><th>برگه</th><td>#<?php echo (int) $s2['page_id']; ?> — «<?php echo esc_html( $s2['title'] ); ?>» — نامک فعلی: <code><?php echo esc_html( $s2['slug'] ); ?></code> — وضعیت: <?php echo esc_html( $s2['status'] ); ?> — محتوا: <?php echo (int) $s2['chars']; ?> نویسه</td></tr>
			<tr><th>تداخل نامک</th><td><?php echo $s2['conflict_id'] ? '⚠ برگهٔ #' . (int) $s2['conflict_id'] . ' با نامک editorial-policy وجود دارد!' : 'ندارد ✓'; ?></td></tr>
			<tr><th>آیتم‌های منو (object)</th><td><?php echo $s2['object_items'] ? esc_html( implode( ' | ', $s2['object_items'] ) ) . ' — خودکار با تغییر نام، پیوندشان به‌روز می‌شود' : 'یافت نشد'; ?></td></tr>
			<tr><th>لینک‌های سفارشی منو به /policy/</th><td><?php echo $s2['custom_links'] ? esc_html( implode( ' | ', $s2['custom_links'] ) ) : 'یافت نشد ✓'; ?></td></tr>
		</table>
		<?php if ( $s2['already_done'] ) : ?>
			<p class="description">✔ پیش‌تر انجام شده — نامک همین حالا editorial-policy است.</p>
		<?php elseif ( $s2['exists'] && ! $s2['conflict_id'] ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'sa_dbf_op2', 'sa_dbf_nonce' ); ?>
				<input type="hidden" name="sa_dbf_op" value="op2">
				<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان دارم و می‌دانم نامک برگه عوض می‌شود (۳۰۱ِ /policy/ با قالب ۲٫۲۲٫۳۵ برقرار می‌شود).</label>
				<?php submit_button( 'اجرای عملیات ۲', 'primary', 'submit', true ); ?>
			</form>
		<?php endif; ?>


		<h2>عملیات ۳+۴ (اتمیک) — تغییر نام ۱۱ نامک پسونددار + بازنویسی پیوندهای قدیمی</h2>
		<?php $s34 = sa_dbf_op34_state(); ?>
		<?php if ( ! $s34['theme_ok'] ) : ?>
			<p class="description">⚠ توابع نقشهٔ قالب یافت نشد — ابتدا قالب ۲٫۲۲٫۳۵+ را فعال کنید.</p>
		<?php else : ?>
		<table class="widefat striped" style="max-width:820px">
			<tr><th>تغییر نام</th><th>شناسه</th><th>مبدأ منتشر</th><th>نامک پایه آزاد</th></tr>
			<?php foreach ( $s34['renames'] as $r ) : ?>
				<tr><td><code><?php echo esc_html( $r['from'] ); ?></code> ← <code><?php echo esc_html( $r['to'] ); ?></code></td><td>#<?php echo (int) $r['src_id']; ?></td><td><?php echo 0 === (int) $r['src_id'] ? '—' : ( $r['src_ok'] ? '✓' : '✗' ); ?></td><td><?php echo 0 === (int) $r['src_id'] ? '✓ انجام‌شد' : ( $r['dst_free'] ? '✓' : '✗ تداخل!' ); ?></td></tr>
			<?php endforeach; ?>
		</table>
		<p>پیوندهای قابل بازنویسی (با در نظر گرفتن تغییر نام‌ها): <strong><?php echo (int) $s34['scan']['total']; ?></strong> پیوند در مسیرهای زیر:</p>
		<details><summary>فهرست مسیرها</summary><ul>
		<?php foreach ( $s34['scan']['paths'] as $path => $n ) : ?><li><code><?php echo esc_html( $path ); ?></code> × <?php echo (int) $n; ?> → <code><?php echo esc_html( sa_dbf_final_target( $path ) ); ?></code></li><?php endforeach; ?>
		</ul></details>
		<p class="description">bushehr-county تغییر نام نمی‌گیرد و پس از این عملیات، مبدأ ۳۰۱ به /city/bushehr/ می‌ماند. ترتیب اتمی: تغییر نام‌ها ← بازنویسی پیوندها ← بازسازی rewrite و purge کش؛ بنابراین هیچ پنجره‌ای بدون پیوند نهایی وجود ندارد.</p>
		<form method="post">
			<?php wp_nonce_field( 'sa_dbf_op34', 'sa_dbf_nonce' ); ?>
			<input type="hidden" name="sa_dbf_op" value="op34">
			<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان تازه دارم و می‌دانم ۱۱ نامک شهری و تا <?php echo (int) $s34['scan']['total']; ?> پیوند درون‌متنی تغییر می‌کنند (لاگ برگشت‌پذیر ثبت می‌شود).</label>
			<?php submit_button( 'اجرای عملیات ۳+۴ (اتمیک)', 'delete', 'submit', true ); ?>
		</form>
		<?php endif; ?>

		<h2>عملیات ۵ — تکمیل تغییر نام‌ها (جابه‌جایی مانع غیرمنتشرِ نامک پایه)</h2>
		<?php $s5 = sa_dbf_op5_state(); ?>
		<?php if ( ! $s5['theme_ok'] ) : ?>
			<p class="description">⚠ توابع نقشهٔ قالب یافت نشد — ابتدا قالب ۲٫۲۲٫۳۵+ را فعال کنید.</p>
		<?php elseif ( ! $s5['blockers'] && 0 === (int) $s5['scan']['total'] ) : ?>
			<p class="description">✔ چیزی برای تکمیل نیست — هیچ مانعی نیست و هیچ پیوند قابل بازنویسی نمانده.</p>
		<?php else : ?>
		<table class="widefat striped" style="max-width:880px">
			<tr><th>تغییر نامِ مانع‌دار</th><th>مانع</th><th>وضعیت مانع</th><th>عنوان مانع</th><th>محتوا</th><th>جابه‌جایی خودکار</th></tr>
			<?php foreach ( $s5['blockers'] as $b ) : ?>
				<tr>
					<td><code><?php echo esc_html( $b['from'] ); ?></code> ← <code><?php echo esc_html( $b['to'] ); ?></code> (#<?php echo (int) $b['src_id']; ?>)</td>
					<td>#<?php echo (int) $b['blk_id']; ?></td>
					<td><?php echo esc_html( $b['blk_status'] ); ?></td>
					<td><?php echo esc_html( $b['blk_title'] ); ?></td>
					<td><?php echo (int) $b['blk_chars']; ?> نویسه</td>
					<td><?php echo $b['safe'] ? '✓ بله — نامکش می‌شود ' . esc_html( $b['to'] . '-blocked-' . $b['blk_id'] ) : '✗ خیر — منتشر است و نیاز به تصمیم انسانی دارد'; ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<p>پیوندهای نسبیِ باقیماندهٔ قابل بازنویسی (شامل پیش‌نویس‌ها): <strong><?php echo (int) $s5['scan']['total']; ?></strong></p>
		<details><summary>فهرست مسیرها</summary><ul>
		<?php foreach ( $s5['scan']['paths'] as $path => $n ) : ?><li><code><?php echo esc_html( $path ); ?></code> × <?php echo (int) $n; ?> → <code><?php echo esc_html( sa_dbf_final_target( $path ) ); ?></code></li><?php endforeach; ?>
		</ul></details>
		<p class="description">ترتیب اتمی: جابه‌جایی مانع‌های غیرمنتشر (فقط draft/pending/private/trash — مانع منتشر هرگز دست نمی‌خورد) ← تغییر نام ← بازنویسی پیوندها ← flush/purge. پیوندهای برون‌سایتی (ارجاع‌های kojaro/baniboom) دست‌نخورده می‌مانند.</p>
		<form method="post">
			<?php wp_nonce_field( 'sa_dbf_op5', 'sa_dbf_nonce' ); ?>
			<input type="hidden" name="sa_dbf_op" value="op5">
			<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان تازه دارم و می‌دانم نامک مانعِ غیرمنتشر + تغییر نام باقیمانده + بازنویسی پیوندهای باقیمانده انجام می‌شود (لاگ برگشت‌پذیر ثبت می‌شود).</label>
			<?php submit_button( 'اجرای عملیات ۵ (تکمیل)', 'delete', 'submit', true ); ?>
		</form>
		<?php endif; ?>

		<h2>عملیات ۶ — پاک‌سازی ارجاع‌های مردهٔ W_* (حذف href مرده، نگه‌داشتن شماره)</h2>
		<?php $s6 = sa_dbf_op6_state(); ?>
		<?php if ( 0 === (int) $s6['total'] ) : ?>
			<p class="description">✔ هیچ ارجاع مردهٔ W_* در محتوا نمانده است.</p>
		<?php else : ?>
		<p>جمع: <strong><?php echo (int) $s6['total']; ?></strong> ارجاع مرده در <strong><?php echo count( $s6['rows'] ); ?></strong> نوشته. تبدیل: <code>&lt;sup class="sa-cite"&gt;&lt;a href="W_X"&gt;۱&lt;/a&gt;&lt;/sup&gt;</code> ← <code>&lt;sup class="sa-cite"&gt;۱&lt;/sup&gt;</code> و توکن خامِ جامانده <code>{c(W_CODE, "۱")}</code> (که همین حالا متنِ قابل دیدن صفحه است) ← <code>&lt;sup class="sa-cite"&gt;۱&lt;/sup&gt;</code>؛ شمارهٔ ارجاع می‌ماند و href/توکن مرده حذف می‌شود.</p>
		<details open><summary>فهرست نوشته‌ها</summary>
		<table class="widefat striped" style="max-width:760px">
			<tr><th>شناسه</th><th>نوع</th><th>نامک</th><th>وضعیت</th><th>تعداد</th></tr>
			<?php foreach ( $s6['rows'] as $row ) : ?>
				<tr><td>#<?php echo (int) $row['id']; ?></td><td><?php echo esc_html( $row['type'] ); ?></td><td><code><?php echo esc_html( $row['slug'] ); ?></code></td><td><?php echo esc_html( $row['status'] ); ?></td><td><?php echo (int) $row['occ']; ?></td></tr>
			<?php endforeach; ?>
		</table></details>
		<form method="post">
			<?php wp_nonce_field( 'sa_dbf_op6', 'sa_dbf_nonce' ); ?>
			<input type="hidden" name="sa_dbf_op" value="op6">
			<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان تازه دارم و می‌دانم <?php echo (int) $s6['total']; ?> href مردهٔ W_* از محتوای <?php echo count( $s6['rows'] ); ?> نوشته حذف می‌شود (شمارهٔ ارجاع نگه داشته می‌شود؛ لاگ برگشت‌پذیر ثبت می‌شود).</label>
			<?php submit_button( 'اجرای عملیات ۶ (پاک‌سازی W_*)', 'delete', 'submit', true ); ?>
		</form>
		<?php endif; ?>

		<h2>لاگ عملیات (برگشت‌پذیری)</h2>
		<?php $log = sa_dbf_log(); ?>
		<?php if ( $log ) : ?>
			<table class="widefat striped"><tr><th>زمان</th><th>جزئیات</th></tr>
			<?php foreach ( array_reverse( $log ) as $row ) : ?>
				<tr><td><?php echo esc_html( (string) $row['at'] ); ?></td><td><code><?php echo esc_html( wp_json_encode( $row, JSON_UNESCAPED_UNICODE ) ); ?></code></td></tr>
			<?php endforeach; ?></table>
		<?php else : ?>
			<p class="description">هنوز عملیاتی اجرا نشده.</p>
		<?php endif; ?>
	</div>
	<?php
}
