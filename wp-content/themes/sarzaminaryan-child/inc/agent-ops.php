<?php
/**
 * v2.22.75 — عملیات نگه‌داری پل ایجنت (sa-agent/v1): دروازهٔ ایندکس، پشتیبان کامل
 * UpdraftPlus و به‌روزرسانی/بازگشت قالب از انتشار گیت‌هاب.
 *
 * روال استاندارد هر به‌روزرسانی (v2.22.120، دستور مالک ۲۸ آبان ۱۴۰۵ — سبک‌سازی):
 *   ۱) POST /theme-update — نصب انتشار تازه با Theme_Upgrader::upgrade() هسته
 *      (temp_backup از وردپرس ۶٫۳: اگر نصب نیمه‌کاره بماند، نسخهٔ قبلی خودکار برمی‌گردد)؛
 *      پشتیبان کامل UpdraftPlus دیگر پیش‌شرط نیست (کد در گیت‌هاب تگ‌دار است)؛
 *   ۲) probe صفحه‌ها؛ اگر خراب بود POST /theme-update با version نسخهٔ قبلی (بازگشت)؛
 *   ۳) GET /storage — دیدن حجم واقعی پیش از هر پاک‌سازی؛
 *   ۴) POST /backups/prune — نگه‌داری پشتیبان‌های افتراقی (پیش‌نمایش، بعد حذف با پرچم).
 * UpdraftPlus فقط برای پشتیبان کاملِ زمان‌بندی‌شده (روزانه/هفتگی) می‌ماند.
 *
 * همهٔ مسیرها فقط برای مدیر کل (manage_options) و به‌روزرسانی قالب فقط با update_themes.
 * هیچ مسیری چیزی پاک نمی‌کند.
 *
 * @package Sarzaminaryan_Child
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'SA_AGENT_NS' ) ) {
	define( 'SA_AGENT_NS', 'sa-agent/v1' );
}

/* -------------------------------------------------------------------------
 * ۱) دروازهٔ ایندکس.
 * ---------------------------------------------------------------------- */

/**
 * GET /index-gate — وضعیت ایندکس صفحه‌های شهرستان، با دلیل.
 *
 * @param WP_REST_Request $r درخواست (page, per_page ≤ 100, only=noindex|index|all).
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_index_gate( $r ) {
	if ( ! function_exists( 'sa_city_index_decision' ) ) {
		return new WP_Error( 'sa_agent_no_gate', 'دروازهٔ ایندکس در این نسخهٔ قالب نیست.', array( 'status' => 500 ) );
	}
	$page     = max( 1, (int) $r->get_param( 'page' ) );
	$per_page = min( 100, max( 1, (int) ( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 50 ) ) );
	$only     = (string) $r->get_param( 'only' );
	$q        = new WP_Query(
		array(
			'post_type'      => 'city',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	$rows     = array();
	foreach ( (array) $q->posts as $pid ) {
		$d      = sa_city_index_decision( $pid );
		$reason = sa_city_noindex_reason( $pid );
		if ( ( 'noindex' === $only && '' === $reason ) || ( 'index' === $only && '' !== $reason ) ) {
			continue;
		}
		$rows[] = array(
			'id'     => (int) $pid,
			'slug'   => (string) get_post_field( 'post_name', $pid ),
			'title'  => (string) get_the_title( $pid ),
			'index'  => '' === $reason,
			'reason' => $reason,
			'label'  => sa_city_noindex_reason_label( $reason ),
			'words'  => (int) $d['words'],
			'places' => (int) $d['places'],
		);
	}
	return rest_ensure_response(
		array(
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => (int) $q->found_posts,
			'total_pages' => (int) $q->max_num_pages,
			'theme'       => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '',
			'rows'        => $rows,
		)
	);
}

/**
 * v2.22.76 — GET /relations-audit: نوشته‌های منتشرشده‌ای که والدشان (شهرستان یا استان)
 * منتشر نیست، پاک شده یا نوعش نادرست است. فقط خواندن.
 *
 * @return WP_REST_Response
 */
function sa_agent_rest_relations_audit() {
	global $wpdb;
	$checks = array(
		array( 'key' => 'sa_city_id', 'parent' => 'city', 'children' => array( 'attraction', 'local_food', 'souvenir', 'accommodation' ) ),
		array( 'key' => 'sa_province_id', 'parent' => 'province', 'children' => array( 'city', 'attraction', 'local_food', 'souvenir' ) ),
	);
	$rows   = array();
	$counts = array();
	foreach ( $checks as $chk ) {
		$types = array_values( array_filter( $chk['children'], 'post_type_exists' ) );
		if ( ! $types ) {
			continue;
		}
		$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = "SELECT p.ID, p.post_type, p.post_name, pm.meta_value AS parent_id, pp.post_status AS parent_status, pp.post_type AS parent_type
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
			LEFT JOIN {$wpdb->posts} pp ON pp.ID = CAST(pm.meta_value AS UNSIGNED)
			WHERE p.post_status = 'publish' AND p.post_type IN ($in)
			AND pm.meta_value <> '' AND pm.meta_value <> '0'
			AND ( pp.ID IS NULL OR pp.post_status <> 'publish' OR pp.post_type <> %s )
			ORDER BY p.ID LIMIT 500";
		$res = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $chk['key'] ), $types, array( $chk['parent'] ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$counts[ $chk['key'] ] = count( (array) $res );
		foreach ( (array) $res as $r ) {
			$rows[] = array(
				'id'            => (int) $r['ID'],
				'type'          => (string) $r['post_type'],
				'slug'          => (string) $r['post_name'],
				'key'           => $chk['key'],
				'parent_id'     => (int) $r['parent_id'],
				'parent_status' => null === $r['parent_status'] ? 'missing' : (string) $r['parent_status'],
				'parent_type'   => null === $r['parent_type'] ? '' : (string) $r['parent_type'],
			);
		}
	}
	return rest_ensure_response(
		array(
			'counts' => $counts,
			'rows'   => $rows,
		)
	);
}

/**
 * v2.22.77 — POST /cache-purge: پاک‌کردن کش صفحه‌های LiteSpeed (همه، یا چند نوشته) با
 * API رسمی افزونه (`litespeed_purge_all` / `litespeed_purge_post`). چیزی پاک نمی‌شود جز
 * نسخهٔ کش‌شده؛ صفحه در درخواست بعدی تازه ساخته می‌شود.
 *
 * @param WP_REST_Request $r درخواست: {"scope":"all"} یا {"ids":[123,456]}.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_cache_purge( $r ) {
	$b = (array) $r->get_json_params();
	if ( ! defined( 'LSCWP_V' ) ) {
		return new WP_Error( 'sa_agent_no_litespeed', 'افزونهٔ LiteSpeed Cache فعال نیست.', array( 'status' => 409 ) );
	}
	$ids = isset( $b['ids'] ) && is_array( $b['ids'] ) ? array_values( array_filter( array_map( 'intval', $b['ids'] ) ) ) : array();
	if ( $ids ) {
		foreach ( array_slice( $ids, 0, 200 ) as $id ) {
			do_action( 'litespeed_purge_post', $id );
		}
		return rest_ensure_response( array( 'purged' => 'posts', 'ids' => array_slice( $ids, 0, 200 ) ) );
	}
	if ( isset( $b['scope'] ) && 'all' === $b['scope'] ) {
		do_action( 'litespeed_purge_all', 'sa-agent' );
		return rest_ensure_response( array( 'purged' => 'all' ) );
	}
	return new WP_Error( 'sa_agent_nothing', 'scope: all یا ids لازم است.', array( 'status' => 400 ) );
}

/* -------------------------------------------------------------------------
 * ۲) UpdraftPlus — همان روشی که MainWP Child و دکمهٔ «Backup Now» خود افزونه به‌کار می‌برند:
 *    nonce با backup_time_nonce()، بستن اتصال مرورگر، سپس do_action رویداد backupnow.
 * ---------------------------------------------------------------------- */

/**
 * شیء UpdraftPlus یا null.
 *
 * @return object|null
 */
function sa_agent_updraft() {
	global $updraftplus;
	return ( is_object( $updraftplus ) && method_exists( $updraftplus, 'backup_time_nonce' ) ) ? $updraftplus : null;
}

/**
 * یک گزینهٔ UpdraftPlus.
 *
 * @param string $key کلید.
 * @param mixed  $fallback پیش‌فرض.
 * @return mixed
 */
function sa_agent_updraft_option( $key, $fallback = false ) {
	if ( class_exists( 'UpdraftPlus_Options' ) && method_exists( 'UpdraftPlus_Options', 'get_updraft_option' ) ) {
		return UpdraftPlus_Options::get_updraft_option( $key, $fallback );
	}
	return get_option( $key, $fallback );
}

/**
 * وضعیت پشتیبان‌های UpdraftPlus (و اگر nonce داده شد، وضعیت همان کار).
 *
 * @param string $nonce nonce کار (۱۲ نویسهٔ هگز) یا خالی.
 * @return array
 */
function sa_agent_updraft_status( $nonce = '' ) {
	$up  = sa_agent_updraft();
	$out = array(
		'active'           => (bool) $up,
		'version'          => ( $up && isset( $up->version ) ) ? (string) $up->version : '',
		'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'next_resume'      => wp_next_scheduled( 'updraft_backup_resume' ) ? gmdate( 'c', (int) wp_next_scheduled( 'updraft_backup_resume' ) ) : '',
		'last_backup'      => null,
		'history_count'    => 0,
		'history_latest'   => '',
		'job'              => null,
	);
	if ( ! $up ) {
		return $out;
	}
	// v2.22.129: تنظیمات زمان‌بندی و نگه‌داری — فقط خواندنی، برای تصمیم مستند مالک.
	$out['settings'] = array(
		'interval'            => (string) sa_agent_updraft_option( 'updraft_interval', '' ),
		'interval_increments' => (string) sa_agent_updraft_option( 'updraft_interval_increments', '' ),
		'interval_database'   => (string) sa_agent_updraft_option( 'updraft_interval_database', '' ),
		'retain_files'        => (int) sa_agent_updraft_option( 'updraft_retain', 2 ),
		'retain_db'           => (int) sa_agent_updraft_option( 'updraft_retain_db', (int) sa_agent_updraft_option( 'updraft_retain', 2 ) ),
		'delete_local'        => (int) sa_agent_updraft_option( 'updraft_delete_local', 1 ),
		'service'             => sa_agent_updraft_option( 'updraft_service', '' ),
		'dir'                 => (string) sa_agent_updraft_option( 'updraft_dir', '' ),
	);
	$last = sa_agent_updraft_option( 'updraft_last_backup', array() );
	if ( is_array( $last ) && ! empty( $last['backup_time'] ) ) {
		$out['last_backup'] = array(
			'backup_time'     => gmdate( 'c', (int) $last['backup_time'] ),
			'age_seconds'     => time() - (int) $last['backup_time'],
			'success'         => ! empty( $last['success'] ),
			'errors'          => isset( $last['errors'] ) ? count( (array) $last['errors'] ) : 0,
			'nonce'           => isset( $last['backup_nonce'] ) ? (string) $last['backup_nonce'] : '',
			'has_db'          => isset( $last['backup_array']['db'] ),
			'file_entities'   => isset( $last['backup_array'] ) ? array_values( array_diff( array_keys( (array) $last['backup_array'] ), array( 'db', 'db-size', 'checksums', 'created_by_version', 'nonce' ) ) ) : array(),
		);
	}
	$history = sa_agent_updraft_option( 'updraft_backup_history', array() );
	if ( is_array( $history ) && $history ) {
		$out['history_count']  = count( $history );
		$out['history_latest'] = gmdate( 'c', (int) max( array_keys( $history ) ) );
	}
	$nonce = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $nonce ) );
	if ( '' !== $nonce ) {
		$job         = get_site_option( 'updraft_jobdata_' . $nonce, array() );
		$in_history  = false;
		foreach ( (array) $history as $entry ) {
			if ( is_array( $entry ) && isset( $entry['nonce'] ) && $entry['nonce'] === $nonce ) {
				$in_history = true;
				break;
			}
		}
		$out['job'] = array(
			'nonce'      => $nonce,
			'found'      => is_array( $job ) && ! empty( $job ),
			'jobstatus'  => ( is_array( $job ) && isset( $job['jobstatus'] ) ) ? (string) $job['jobstatus'] : '',
			'in_history' => $in_history,
			'finished'   => $in_history && ( ! is_array( $job ) || empty( $job ) || 'finished' === ( isset( $job['jobstatus'] ) ? $job['jobstatus'] : 'finished' ) ),
		);
	}
	return $out;
}

/**
 * آیا یک پشتیبان کامل و موفق UpdraftPlus در $max_age ثانیهٔ اخیر هست؟
 *
 * @param int $max_age بیشینهٔ سن (ثانیه).
 * @return bool
 */
function sa_agent_updraft_recent_ok( $max_age = 21600 ) {
	$s = sa_agent_updraft_status();
	return $s['last_backup'] && $s['last_backup']['success'] && $s['last_backup']['has_db'] && $s['last_backup']['age_seconds'] <= $max_age;
}

/**
 * POST /updraft — شروع پشتیبان. پاسخ JSON پیش از شروع کار فرستاده و اتصال بسته می‌شود.
 *
 * @param WP_REST_Request $r درخواست: owner_approved_backup:true، what=all|db|files، nocloud:bool.
 * @return WP_Error|void
 */
function sa_agent_rest_updraft_start( $r ) {
	$b  = (array) $r->get_json_params();
	$up = sa_agent_updraft();
	if ( ! $up ) {
		return new WP_Error( 'sa_agent_no_updraft', 'افزونهٔ UpdraftPlus فعال نیست.', array( 'status' => 409 ) );
	}
	if ( empty( $b['owner_approved_backup'] ) || true !== $b['owner_approved_backup'] ) {
		return new WP_Error( 'sa_agent_need_owner', 'پشتیبان UpdraftPlus فقط با owner_approved_backup: true.', array( 'status' => 403 ) );
	}
	$events = array(
		'all'   => 'updraft_backupnow_backup_all',
		'db'    => 'updraft_backupnow_backup_database',
		'files' => 'updraft_backupnow_backup',
	);
	$what = isset( $b['what'] ) && isset( $events[ $b['what'] ] ) ? (string) $b['what'] : 'all';
	$job  = sa_agent_updraft_status();
	if ( $job['last_backup'] && $job['last_backup']['age_seconds'] < 120 ) {
		return new WP_Error( 'sa_agent_backup_recent', 'کمتر از دو دقیقه پیش پشتیبانی تمام شده؛ دوباره نمی‌گیریم.', array( 'status' => 409 ) );
	}
	// v2.22.132 (سد ایمنی، شرط مالک): پشتیبانی که نسخهٔ ابری ندارد، پشتیبان نیست؛
	// خطر واقعی که در کد UpdraftPlus دیدیم: prune_file() نسخهٔ قبلی را روی دیسک
	// بی‌قید‌و‌شرط پاک می‌کند (فقط پس از آن سراغ ابر می‌رود). پس اگر خدمت ابری
	// پیکربندی شده باشد، مجاز نیستیم پشتیبانِ nocloud شروع کنیم — وگرنه پشتیبان
	// تنها روی دیسک پاک می‌شود و هیچ نسخه‌ای در ابر نیست.
	$svc = sa_agent_updraft_option( 'updraft_service', '' );
	$svc = is_array( $svc ) ? $svc : array( $svc );
	$svc = array_values( array_filter( array_map( 'strval', $svc ), static function ( $v ) {
		return '' !== $v && 'none' !== $v;
	} ) );
	if ( ! empty( $b['nocloud'] ) && $svc && empty( $b['owner_approved_nocloud'] ) ) {
		return new WP_Error(
			'sa_agent_nocloud_blocked',
			'خدمت ابری پیکربندی شده است (' . implode( ',', $svc ) . '); پشتیبان بدون ارسال به ابر اجازه ندارد چون نسخهٔ قبلی روی دیسک حذف می‌شود. برای عبور، owner_approved_nocloud:true لازم است.',
			array( 'status' => 409 )
		);
	}
	$nonce   = (string) $up->backup_time_nonce();
	$options = array(
		'nocloud'   => ! empty( $b['nocloud'] ) ? 1 : 0,
		'use_nonce' => $nonce,
	);
	$payload = wp_json_encode(
		array(
			'started' => true,
			'nonce'   => $nonce,
			'what'    => $what,
			'nocloud' => (bool) $options['nocloud'],
			'note'    => 'وضعیت را با GET /updraft?nonce=' . $nonce . ' دنبال کنید.',
		)
	);
	if ( apply_filters( 'sa_agent_updraft_dry_run', false ) ) {
		return rest_ensure_response( json_decode( $payload, true ) );
	}
	if ( ! headers_sent() ) {
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	}
	ignore_user_abort( true );
	if ( method_exists( $up, 'close_browser_connection' ) ) {
		$up->close_browser_connection( $payload );
	} else {
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
	}
	do_action( $events[ $what ], apply_filters( 'updraft_backupnow_options', $options, array() ) );
	exit;
}

/* -------------------------------------------------------------------------
 * ۳) به‌روزرسانی/بازگشت قالب از انتشار گیت‌هاب.
 * ---------------------------------------------------------------------- */

/**
 * نسخهٔ قالب روی دیسک (نه ثابتِ بارشدهٔ این درخواست).
 *
 * @return string
 */
function sa_agent_theme_disk_version() {
	$file = get_theme_root( 'sarzaminaryan-child' ) . '/sarzaminaryan-child/style.css';
	if ( ! is_readable( $file ) ) {
		return '';
	}
	$data = get_file_data( $file, array( 'Version' => 'Version' ) );
	return isset( $data['Version'] ) ? (string) $data['Version'] : '';
}

/**
 * POST /theme-update — نصب تازه‌ترین انتشار یا نسخهٔ مشخص (بازگشت).
 *
 * بدنه: owner_approved_theme_update:true، expected_version (نسخه‌ای که باید نصب شود)،
 * version (اختیاری؛ برای بازگشت به نسخهٔ قدیمی‌تر). از ۲.۲۲.۱۲۰ پشتیبان کامل لازم
 * نیست (کد قالب در گیت‌هاب تگ‌دار است و بازگشت با نصب همان نسخه انجام می‌شود)؛
 * فقط اگر بدنه require_full_backup:true بفرستد، همان دروازهٔ ۶ ساعتِ قدیمی برمی‌گردد.
 * allow_without_updraft/allow_without_backup برای سازگاری با کارهای قدیمی پذیرفته می‌شوند.
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_theme_update( $r ) {
	$b = (array) $r->get_json_params();
	if ( empty( $b['owner_approved_theme_update'] ) || true !== $b['owner_approved_theme_update'] ) {
		return new WP_Error( 'sa_agent_need_owner', 'به‌روزرسانی قالب فقط با owner_approved_theme_update: true.', array( 'status' => 403 ) );
	}
	$expected = isset( $b['expected_version'] ) ? trim( (string) $b['expected_version'] ) : '';
	if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $expected ) ) {
		return new WP_Error( 'sa_agent_bad_version', 'expected_version لازم است (مثلاً 2.22.75).', array( 'status' => 400 ) );
	}
	/*
	 * v2.22.120 (دستور مالک، ۲۸ آبان ۱۴۰۵ — سبک‌سازی بک‌آپ):
	 * نصب قالب دیگر «پشتیبان کامل کمتر از ۶ ساعت» نمی‌خواهد. دلیل: کد قالب در گیت‌هاب
	 * تگ و نسخه دارد و بازگشت با نصب همان نسخهٔ قبلی از انتشار گیت‌هاب انجام می‌شود
	 * (`rollback_to`) و پشتیبان فایل‌های هسته/رسانه را دست نمی‌زند. پس تنها شرطِ
	 * لازم، وجود همان انتشار و نسخهٔ مشخص است — که پایین‌تر سنجیده می‌شود.
	 * UpdraftPlus کامل فقط برای بازگردانی کاملِ سایت (زمان‌بندی‌شده) می‌ماند.
	 */
	$gate = array(
		'require_full_backup' => ! empty( $b['require_full_backup'] ),
		'backup_fresh'        => sa_agent_updraft() ? sa_agent_updraft_recent_ok( 6 * HOUR_IN_SECONDS ) : false,
	);
	if ( $gate['require_full_backup'] && ! $gate['backup_fresh'] ) {
		return new WP_Error( 'sa_agent_need_backup', 'این نصب با درخواست صریح پشتیبان کامل آمده؛ پشتیبان موفق کمتر از ۶ ساعت با پایگاه‌داده لازم است.', array( 'status' => 409, 'gate' => $gate ) );
	}
	if ( ! class_exists( 'SA_GitHub_Theme_Updater' ) ) {
		return new WP_Error( 'sa_agent_no_updater', 'به‌روزرسان گیت‌هاب قالب بار نشده.', array( 'status' => 500 ) );
	}
	$want = isset( $b['version'] ) ? trim( (string) $b['version'] ) : '';
	if ( '' !== $want && $want !== $expected ) {
		return new WP_Error( 'sa_agent_bad_version', 'version و expected_version باید یکی باشند.', array( 'status' => 400 ) );
	}
	$release = SA_GitHub_Theme_Updater::init()->agent_release_package( $want );
	if ( is_wp_error( $release ) ) {
		$release->add_data( array( 'status' => 502 ) );
		return $release;
	}
	if ( $release['version'] !== $expected ) {
		return new WP_Error( 'sa_agent_version_mismatch', 'انتشار پیداشده ' . $release['version'] . ' است، نه ' . $expected . '.', array( 'status' => 409 ) );
	}
	$before = sa_agent_theme_disk_version();
	if ( $before === $expected ) {
		return rest_ensure_response( array( 'updated' => false, 'reason' => 'already_installed', 'version' => $before ) );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$slug   = 'sarzaminaryan-child';
	$inject = static function ( $value ) use ( $slug, $release ) {
		if ( ! is_object( $value ) ) {
			$value = new stdClass();
		}
		if ( ! isset( $value->response ) || ! is_array( $value->response ) ) {
			$value->response = array();
		}
		$value->response[ $slug ] = array(
			'theme'       => $slug,
			'new_version' => $release['version'],
			'url'         => $release['html_url'],
			'package'     => $release['package'],
		);
		return $value;
	};
	add_filter( 'site_transient_update_themes', $inject, 999 );
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Theme_Upgrader( $skin );
	$result   = $upgrader->upgrade( $slug ); // temp_backup + خودکار برگشت در صورت شکست (هسته ≥ ۶٫۳).
	remove_filter( 'site_transient_update_themes', $inject, 999 );

	clearstatcache();
	if ( function_exists( 'wp_clean_themes_cache' ) ) {
		wp_clean_themes_cache();
	}
	$after  = sa_agent_theme_disk_version();
	$errors = array();
	if ( is_wp_error( $result ) ) {
		$errors[] = $result->get_error_message();
	}
	if ( method_exists( $skin, 'get_errors' ) && $skin->get_errors()->has_errors() ) {
		$errors = array_merge( $errors, $skin->get_errors()->get_error_messages() );
	}
	$log   = (array) get_option( 'sa_agent_theme_update_log', array() );
	$log[] = array(
		'time_gmt' => gmdate( 'c' ),
		'from'     => $before,
		'to'       => $after,
		'wanted'   => $expected,
		'ok'       => $after === $expected,
		'errors'   => $errors,
	);
	update_option( 'sa_agent_theme_update_log', array_slice( $log, -20 ), false );

	if ( $after !== $expected ) {
		return new WP_Error(
			'sa_agent_theme_update_failed',
			'نصب انجام نشد؛ نسخهٔ روی دیسک ' . $after . ' است. ' . implode( ' | ', $errors ),
			array( 'status' => 500, 'before' => $before, 'after' => $after )
		);
	}
	return rest_ensure_response(
		array(
			'updated'  => true,
			'from'     => $before,
			'to'       => $after,
			'release'  => $release['html_url'],
			'messages' => method_exists( $skin, 'get_upgrade_messages' ) ? array_slice( (array) $skin->get_upgrade_messages(), -8 ) : array(),
		)
	);
}

/**
 * v2.22.90 — تشخیص واقعی فرمت تصویر از روی امضای بایت‌ها (برای جلوگیری از ذخیرهٔ بایت غیرتصویری).
 *
 * @param string $bytes محتوای خام فایل.
 * @return string '' | 'image/webp' | 'image/jpeg' | 'image/png'.
 */
function sa_agent_detect_image_mime( $bytes ) {
	if ( ! is_string( $bytes ) || strlen( $bytes ) < 16 ) {
		return '';
	}
	if ( 'RIFF' === substr( $bytes, 0, 4 ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
		return 'image/webp';
	}
	if ( "\xFF\xD8\xFF" === substr( $bytes, 0, 3 ) ) {
		return 'image/jpeg';
	}
	if ( "\x89PNG\r\n\x1a\n" === substr( $bytes, 0, 8 ) ) {
		return 'image/png';
	}
	return '';
}

/**
 * v2.22.90 — POST /media: بارگذاری امن تصویر شاخص (WebP/JPEG/PNG تا ۳ مگابایت) از راه پل ایجنت.
 *
 * اگر `cover_key` فرستاده شود و از قبل پیوست سالمی با همان کلید در کتابخانهٔ رسانه باشد،
 * همان شناسه بدون ساختن فایل تکراری برگردانده می‌شود (اجرای دوبارهٔ امن).
 * هیچ فایلی بازنویسی یا پاک نمی‌شود.
 *
 * @param WP_REST_Request $r درخواست JSON: {filename, data_base64, title, alt, post_parent, cover_key, force_new}.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_media_upload( $r ) {
	$b          = (array) $r->get_json_params();
	$raw_name   = isset( $b['filename'] ) ? trim( (string) $b['filename'] ) : '';
	$b64        = isset( $b['data_base64'] ) ? trim( (string) $b['data_base64'] ) : '';
	$title      = isset( $b['title'] ) ? sanitize_text_field( (string) $b['title'] ) : '';
	$alt        = isset( $b['alt'] ) ? sanitize_text_field( (string) $b['alt'] ) : '';
	$parent_id  = isset( $b['post_parent'] ) ? absint( $b['post_parent'] ) : 0;
	$cover_key  = isset( $b['cover_key'] ) ? sanitize_text_field( (string) $b['cover_key'] ) : '';
	$force_new  = ! empty( $b['force_new'] );

	if ( '' === $raw_name || '' === $b64 ) {
		return new WP_Error( 'sa_agent_bad_media', 'filename و data_base64 الزامی است.', array( 'status' => 400 ) );
	}
	if ( strlen( $b64 ) > 4200000 ) {
		return new WP_Error( 'sa_agent_media_too_large', 'حجم تصویر بیش از سقف مجاز (۳ مگابایت) است.', array( 'status' => 413 ) );
	}
	$bytes = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $bytes || strlen( $bytes ) < 100 || strlen( $bytes ) > 3145728 ) {
		return new WP_Error( 'sa_agent_bad_media_bytes', 'رمزگشایی تصویر ناموفق بود یا اندازهٔ بایت‌ها معتبر نیست.', array( 'status' => 400 ) );
	}
	$mime = sa_agent_detect_image_mime( $bytes );
	if ( '' === $mime ) {
		return new WP_Error( 'sa_agent_bad_media_type', 'فقط تصویر واقعی WebP، JPEG یا PNG مجاز است.', array( 'status' => 400 ) );
	}
	$ext_map = array(
		'image/webp' => 'webp',
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
	);
	$ext     = $ext_map[ $mime ];
	$stem    = strtolower( (string) pathinfo( $raw_name, PATHINFO_FILENAME ) );
	$stem    = trim( (string) preg_replace( '/[^a-z0-9_-]+/', '-', $stem ), '-_' );
	if ( '' === $stem ) {
		$stem = 'sa-cover';
	}
	$filename = $stem . '.' . $ext;

	// جلوگیری از ساخت پیوست تکراری در اجراهای چندباره.
	if ( '' !== $cover_key && ! $force_new && class_exists( 'WP_Query' ) ) {
		$existing = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_sa_cover_key',
						'value' => $cover_key,
					),
				),
			)
		);
		foreach ( (array) $existing->posts as $att_id ) {
			$att_id  = (int) $att_id;
			$missing = function_exists( 'sa_media_attachment_missing' ) ? sa_media_attachment_missing( $att_id ) : false;
			if ( $att_id > 0 && ! $missing ) {
				// پیوست سالمِ موجود بدون تغییر بازاستفاده می‌شود؛ این شاخه بدون پشتیبان هم چیزی را نمی‌نویسد.
				return rest_ensure_response(
					array(
						'id'     => $att_id,
						'url'    => (string) wp_get_attachment_url( $att_id ),
						'mime'   => $mime,
						'reused' => true,
					)
				);
			}
		}
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$upload = wp_upload_bits( $filename, null, $bytes );
	if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
		$err_msg = ( is_array( $upload ) && ! empty( $upload['error'] ) ) ? (string) $upload['error'] : 'خطای ناشناخته در ذخیرهٔ فایل رسانه.';
		return new WP_Error( 'sa_agent_upload_failed', $err_msg, array( 'status' => 500 ) );
	}

	$att_title = '' !== $title ? $title : $stem;
	$att_id    = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => wp_slash( $att_title ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$parent_id,
		true
	);
	if ( is_wp_error( $att_id ) || ! $att_id ) {
		return new WP_Error( 'sa_agent_attachment_failed', 'ثبت پیوست در کتابخانهٔ رسانه انجام نشد.', array( 'status' => 500 ) );
	}
	$meta = wp_generate_attachment_metadata( (int) $att_id, $upload['file'] );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( (int) $att_id, $meta );
	}
	if ( '' !== $alt ) {
		update_post_meta( (int) $att_id, '_wp_attachment_image_alt', wp_slash( $alt ) );
	}
	if ( '' !== $cover_key ) {
		update_post_meta( (int) $att_id, '_sa_cover_key', wp_slash( $cover_key ) );
	}
	return rest_ensure_response(
		array(
			'id'     => (int) $att_id,
			'url'    => isset( $upload['url'] ) ? (string) $upload['url'] : (string) wp_get_attachment_url( (int) $att_id ),
			'mime'   => $mime,
			'bytes'  => strlen( $bytes ),
			'reused' => false,
		)
	);
}

/* -------------------------------------------------------------------------
 * ۱-ب) وضعیت حجم (v2.22.120) — پیش از هر پاک‌سازی، عدد واقعی.
 * ---------------------------------------------------------------------- */

/**
 * GET /storage — حجم واقعی چیزهایی که هاست را پر می‌کنند، فقط خواندنی.
 *
 * چرا (دستور مالک، ۲۸ آبان ۱۴۰۵): هاست سقف حجم دارد و باید بدانیم کدام بخش
 * واقعاً جا می‌گیرد: پشتیبان‌های ایجنت، نسخه‌های UpdraftPlus، رسانه‌ها، یا
 * فایل‌های موقت. این مسیر هیچ چیزی را تغییر نمی‌دهد و هیچ فایلی را حذف نمی‌کند.
 *
 * @return WP_REST_Response
 */
/**
 * GET /featured-audit — ممیزی تصویر شاخص شهرستان‌ها (فقط خواندنی، v2.22.141).
 *
 * برای هر شهرستان منتشرشده که تصویر شاخص ندارد، سه زیر-دسته را از هم جدا می‌کند:
 *   (ب) در محتوا/گالری عکس هست            → با تنظیم خودکار قابل رفع است
 *   (الف) هیچ عکسی در هیچ‌جا نیست           → فقط جایگزین بصری موقت
 *   (ج) استثنا (فایل شاخص هست ولی گم‌شده)  → نیاز به تصمیم مالک
 * همچنین نوشته‌هایی که به‌طور صریح شاخص را حذف کرده‌اند جدا شمرده می‌شوند.
 *
 * @return WP_REST_Response
 */
function sa_agent_rest_featured_audit() {
	$ids = get_posts(
		array(
			'post_type'      => 'city',
			'post_status'    => 'publish',
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	$out = array(
		'total_city_published' => count( (array) $ids ),
		'has_featured'         => 0,
		'suppressed'           => 0,
		'no_featured'          => 0,
		'group_a_no_image'     => array(),
		'group_b_has_image'    => array(),
		'group_c_unexpected'   => array(),
		'gallery_images_total' => 0,
		'thumb_from_filter'    => 0,
	);
	foreach ( (array) $ids as $pid ) {
		$pid   = (int) $pid;
		$thumb = (int) get_post_thumbnail_id( $pid );
		// v2.22.142: کدام منبع؟ raw متای _thumbnail_id یا فیلترهای get_post_thumbnail_id
		// (که ممکن است جانشین برگردانند و «همه شاخص دارند» کاذب بسازند).
		$raw_thumb = (int) get_post_meta( $pid, '_thumbnail_id', true );
		if ( $thumb > 0 && 0 === $raw_thumb ) {
			++$out['thumb_from_filter'];
		}
		if ( $thumb > 0 ) {
			$missing = function_exists( 'sa_media_attachment_missing' ) ? (bool) sa_media_attachment_missing( $thumb ) : false;
			if ( $missing ) {
				$out['group_c_unexpected'][] = array( 'id' => $pid, 'slug' => get_post_field( 'post_name', $pid ), 'reason' => 'thumb_file_missing', 'thumb' => $thumb );
				continue;
			}
			++$out['has_featured'];
			continue;
		}
		if ( function_exists( 'sa_featured_image_suppressed' ) && sa_featured_image_suppressed( $pid ) ) {
			++$out['suppressed'];
			continue;
		}
		++$out['no_featured'];
		$content  = (string) get_post_field( 'post_content', $pid );
		$province = function_exists( 'sa_gallery_city_province_id' ) ? (int) sa_gallery_city_province_id( $pid ) : 0;
		$gallery  = ( $province && function_exists( 'sa_gallery_image_ids' ) ) ? (array) sa_gallery_image_ids( $province, $pid, 5 ) : array();
		$inline   = array();
		if ( preg_match_all( '/wp-image-(\d+)/', $content, $mm ) ) {
			$inline = array_values( array_unique( array_map( 'intval', $mm[1] ) ) );
		}
		$first = 0;
		foreach ( $gallery as $gid ) {
			$first = (int) $gid;
			break;
		}
		if ( ! $first && $inline ) {
			$first = (int) $inline[0];
		}
		$row = array(
			'id'        => $pid,
			'slug'      => (string) get_post_field( 'post_name', $pid ),
			'title'     => (string) get_post_field( 'post_title', $pid ),
			'province'  => $province,
			'gallery'   => array_map( 'intval', $gallery ),
			'inline'    => $inline,
			'candidate' => $first,
		);
		if ( $first ) {
			$out['group_b_has_image'][] = $row;
			$out['gallery_images_total'] += count( $gallery );
		} else {
			$out['group_a_no_image'][] = $row;
		}
	}
	$out['group_counts'] = array(
		'a' => count( $out['group_a_no_image'] ),
		'b' => count( $out['group_b_has_image'] ),
		'c' => count( $out['group_c_unexpected'] ),
	);
	$out['marker'] = 'featured-audit-v141';
	return rest_ensure_response( $out );
}

function sa_agent_rest_storage() {
	$out = array(
		'disk'    => array( 'free_bytes' => null, 'total_bytes' => null ),
		'agent'   => array( 'records' => 0, 'bytes' => 0, 'oldest' => '', 'newest' => '', 'by_kind' => array() ),
		'updraft' => array( 'active' => false, 'history' => 0, 'sets' => array(), 'dir' => '', 'bytes' => 0 ),
		'uploads' => array( 'dir' => '', 'files' => 0, 'bytes' => 0, 'images_over_200kb' => 0 ),
		'backup_plugin_dirs' => array(),
		'notes'   => array(),
	);
	if ( function_exists( 'disk_free_space' ) && function_exists( 'ABSPATH' ) ) {
		$free              = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$total             = @disk_total_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$out['disk']['free_bytes']  = false === $free ? null : (int) $free;
		$out['disk']['total_bytes'] = false === $total ? null : (int) $total;
	}

	$ids = get_posts(
		array(
			'post_type'      => SA_AGENT_BACKUP_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	foreach ( (array) $ids as $bid ) {
		$bytes = sa_agent_backup_bytes( $bid );
		$kind  = (string) get_post_meta( $bid, '_sa_backup_kind', true );
		$out['agent']['records']++;
		$out['agent']['bytes'] += $bytes;
		if ( ! isset( $out['agent']['by_kind'][ $kind ] ) ) {
			$out['agent']['by_kind'][ $kind ] = array( 'records' => 0, 'bytes' => 0 );
		}
		$out['agent']['by_kind'][ $kind ]['records']++;
		$out['agent']['by_kind'][ $kind ]['bytes'] += $bytes;
	}
	if ( $ids ) {
		$out['agent']['oldest'] = (string) get_post_field( 'post_date_gmt', (int) $ids[0] );
		$out['agent']['newest'] = (string) get_post_field( 'post_date_gmt', (int) end( $ids ) );
	}

	$up = function_exists( 'sa_agent_updraft' ) ? sa_agent_updraft() : null;
	$out['updraft']['active'] = (bool) $up;
	if ( $up ) {
		$history = function_exists( 'sa_agent_updraft_option' ) ? (array) sa_agent_updraft_option( 'updraft_backup_history', array() ) : array();
		$out['updraft']['history'] = count( $history );
		foreach ( $history as $time => $entry ) {
			$files = 0;
			foreach ( array( 'db', 'db-size', 'plugins', 'themes', 'uploads', 'others', 'mu-plugins' ) as $key ) {
				if ( isset( $entry[ $key ] ) || isset( $entry[ $key . '-size' ] ) ) {
					$files += isset( $entry[ $key . '-size' ] ) ? (int) $entry[ $key . '-size' ] : 0;
				}
			}
			$live = array();
			foreach ( (array) $entry as $k => $v ) {
				if ( '-size' === substr( (string) $k, -5 ) || 'checksums' === $k ) {
					continue;
				}
				if ( in_array( $k, array( 'db', 'plugins', 'themes', 'uploads', 'others', 'mu-plugins' ), true ) ) {
					$live[ $k ] = isset( $entry[ $k . '-size' ] ) ? (int) $entry[ $k . '-size' ] : null;
				}
			}
			$out['updraft']['sets'][] = array(
				'utc'      => gmdate( 'c', (int) $time ),
				'bytes'    => $files,
				'entities' => array_values( array_intersect( array_keys( (array) $entry ), array( 'db', 'plugins', 'themes', 'uploads', 'others', 'mu-plugins' ) ) ),
				'sizes'    => $live,
			);
			$out['updraft']['bytes'] += $files;
		}
		// پوشهٔ پشتیبان UpdraftPlus (اگر روی همین هاست باشد) — با تحمل نبود دسترسی.
		$dir = defined( 'UPDRAFTPLUS_DIR' ) ? UPDRAFTPLUS_DIR : WP_CONTENT_DIR . '/updraft';
		if ( function_exists( 'wp_upload_dir' ) ) {
			$up_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'updraft';
			if ( is_dir( $up_dir ) ) {
				$dir = $up_dir;
			}
		}
		$out['updraft']['dir'] = $dir;
		// دو عدد جدا: حجم «نسخه‌های ثبت‌شده در تاریخچه» و حجم «فایل‌های واقعی روی دیسک».
		// max گرفتنِ این دو، عدد گمراه‌کننده می‌داد (تاریخچه نسخه‌هایی را می‌شمرد که
		// فایلشان قبلاً پاک شده بود).
		$out['updraft']['set_bytes'] = (int) $out['updraft']['bytes'];
		$sum = sa_agent_storage_dir_bytes( $dir );
		$out['updraft']['dir_bytes'] = null === $sum ? null : (int) $sum['bytes'];
		if ( null !== $sum ) {
			$out['updraft']['bytes'] = (int) $sum['bytes'];
			$out['updraft']['files'] = $sum['files'];
		}
		$out['updraft']['file_list'] = sa_agent_storage_list_files( $dir, 40 );
		$out['updraft']['sets_detail'] = sa_agent_updraft_set_files( (array) sa_agent_updraft_option( 'updraft_backup_history', array() ) );
		$orphans                      = sa_agent_updraft_orphan_files();
		$out['updraft']['orphans']    = array(
			'files' => $orphans['files'],
			'bytes' => $orphans['bytes'],
			'mb'    => round( $orphans['bytes'] / 1048576, 1 ),
		);
	}

	$uploads = wp_upload_dir();
	$out['uploads']['dir'] = (string) $uploads['basedir'];
	$up_sum = sa_agent_storage_dir_bytes( (string) $uploads['basedir'], true );
	if ( null !== $up_sum ) {
		$out['uploads']['files'] = $up_sum['files'];
		$out['uploads']['bytes'] = $up_sum['bytes'];
		$out['uploads']['images_over_200kb'] = $up_sum['big'];
	}

	foreach ( array( 'updraft', 'backups', 'backup', 'ai1wm-backups', 'wpvividbackups' ) as $name ) {
		$path = trailingslashit( WP_CONTENT_DIR ) . $name;
		if ( ! is_dir( $path ) ) {
			continue;
		}
		$sum = sa_agent_storage_dir_bytes( $path );
		if ( null !== $sum ) {
			$out['backup_plugin_dirs'][] = array(
				'path'      => $path,
				'files'     => $sum['files'],
				'bytes'     => $sum['bytes'],
				'file_list' => sa_agent_storage_list_files( $path, 25 ),
			);
		}
	}
	$out['notes'][] = 'agent: پشتیبان‌های افتراقی پل (رکورد خصوصی در پایگاه‌داده).';
	$out['notes'][] = 'updraft: پشتیبان‌های کاملِ زمان‌بندی‌شده (فقط برای بازگردانی کامل سایت).';
	$out['notes'][] = 'uploads: رسانه‌های سایت؛ عکس‌های شهرستان باید WebP زیر ۱۰۰ کیلوبایت باشند.';
	return rest_ensure_response( $out );
}

/**
 * حجم و شمار فایل‌های یک پوشه (پیمایش تخت، بدون symlink) — فقط خواندنی.
 *
 * @param string $dir     مسیر.
 * @param bool   $audit   آیا تصاویر بزرگ هم شمرده شوند (برای گزارش رسانه).
 * @return array{files:int,bytes:int,big:int}|null
 */
function sa_agent_storage_dir_bytes( $dir, $audit = false ) {
	$dir = (string) $dir;
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return null;
	}
	$files = 0;
	$bytes = 0;
	$big   = 0;
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}
			++$files;
			$size   = (int) $file->getSize();
			$bytes += $size;
			if ( $audit && $size > 200 * 1024 && preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $file->getFilename() ) ) {
				++$big;
			}
			if ( $files > 60000 ) {
				break; // سقف ایمنی برای هاست‌های کوچک و تایم‌اوت PHP.
			}
		}
	} catch ( Exception $e ) {
		return null;
	}
	return array( 'files' => $files, 'bytes' => $bytes, 'big' => $big );
}

/**
 * فایل‌های هر نسخهٔ UpdraftPlus از تاریخچهٔ خودش (فقط خواندن).
 *
 * چرا: پوشهٔ `wp-content/updraft` فایل‌های نسخه‌های قدیمی را نگه می‌دارد که دیگر در
 * تاریخچه نیستند؛ برای تصمیم حذف باید دانست هر فایل به کدام نسخه تعلق دارد.
 *
 * @param array $history تاریخچهٔ `updraft_backup_history`.
 * @return array[] هر ردیف: nonce، utc، files[] و bytes.
 */
function sa_agent_updraft_set_files( array $history ) {
	$out  = array();
	$dirs = sa_agent_updraft_dirs();
	// همهٔ فایل‌های پشتیبانِ روی دیسک، یک‌بار خوانده می‌شوند؛ بعد به نسخه نسبت داده می‌شوند.
	$disk = array();
	foreach ( $dirs as $dir ) {
		foreach ( (array) glob( trailingslashit( $dir ) . 'backup_*' ) as $path ) {
			if ( is_file( $path ) ) {
				$disk[] = array( 'path' => $path, 'name' => basename( $path ), 'bytes' => (int) filesize( $path ) );
			}
		}
	}
	foreach ( $history as $time => $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}
		$nonce = isset( $entry['nonce'] ) ? (string) $entry['nonce'] : '';
		$files = array();
		$bytes = 0;
		foreach ( $disk as $f ) {
			// نام فایل‌های UpdraftPlus شناسهٔ نسخه (nonce) را در خود دارد.
			if ( '' === $nonce || false === strpos( $f['name'], $nonce ) ) {
				continue;
			}
			$bytes  += $f['bytes'];
			$files[] = array( 'name' => $f['name'], 'bytes' => $f['bytes'], 'exists' => true );
		}
		$out[] = array(
			'nonce'    => $nonce,
			'utc'      => gmdate( 'c', (int) $time ),
			'entities' => array_values( array_intersect( array_keys( (array) $entry ), array( 'db', 'plugins', 'themes', 'uploads', 'uploads2', 'others', 'mu-plugins' ) ) ),
			'files'    => $files,
			'bytes'    => $bytes,
			'on_disk'  => (bool) $files,
		);
	}
	return $out;
}

/**
 * پوشه‌های ممکن پشتیبان UpdraftPlus (هر دو حالت افزونه و پیش‌فرض وردپرس).
 *
 * @return string[]
 */
function sa_agent_updraft_dirs() {
	$dirs = array();
	if ( function_exists( 'wp_upload_dir' ) ) {
		$dirs[] = trailingslashit( wp_upload_dir()['basedir'] ) . 'updraft';
	}
	$dirs[] = WP_CONTENT_DIR . '/updraft';
	return array_values( array_filter( $dirs, 'is_dir' ) );
}

/**
 * فایل‌های پشتیبانی که در هیچ نسخه‌ای از تاریخچه نیستند (یتیم) — فقط خواندن.
 *
 * @return array{files:array[],bytes:int}
 */
/**
 * v2.22.129: بررسی مستند وضعیت آپلود یک نسخهٔ UpdraftPlus روی فضای ابری.
 *
 * چرا این‌طور: UpdraftPlus مسیر فایل‌ها را در تاریخچه ذخیره نمی‌کند و «اتصال برقرار
 * است» هیچ چیزی دربارهٔ موفقیت آپلود نمی‌گوید. بنابراین سه شاهد خوانده می‌شود:
 *   ۱) فهرست واقعی فایل‌های همان نسخه روی فضای ابری (متد listfiles خود افزونه)،
 *   ۲) خط «OK: Archive … uploaded to …» در فایل لاگ همان نسخه،
 *   ۳) وضعیت و خطاهای آخرین پشتیبان (updraft_last_backup).
 *
 * @param string $nonce شناسهٔ نسخه.
 * @return array
 */
/**
 * v2.22.130: آیا این خط لاگ UpdraftPlus خطای واقعی است؟
 *
 * نسخهٔ اول هر خطی با «ERROR/failed» را خطا می‌شمرد و دو خط بی‌گناه را هم برداشته بود:
 * «SQL compatibility mode is now: ERROR_FOR_DIVISION_BY_ZERO» و «There were no errors…».
 * حالا فقط نشانه‌های واقعی خطا پذیرفته می‌شوند.
 *
 * @param string $line خط لاگ.
 * @return bool
 */
function sa_agent_updraft_log_is_error( $line ) {
	$line = (string) $line;
	if ( '' === trim( $line ) ) {
		return false;
	}
	if ( false !== strpos( $line, 'no errors' ) || false !== strpos( $line, 'ERROR_FOR_DIVISION_BY_ZERO' ) ) {
		return false;
	}
	// خطاهای واقعی UpdraftPlus با نشانگر «(error)» در انتهای خط لاگ می‌آیند.
	if ( preg_match( '/((\*\*\*)?\s*\(?error\)?\s*$)/i', $line ) && false === strpos( $line, 'no errors' ) ) {
		return true;
	}
	return (bool) preg_match( '/(\bERROR:\s|Failed to upload|failed to open|will not be able to|Could not |unable to)/i', $line );
}

/**
 * v2.22.133: تست واقعی اتصال Google Drive — نه «تنظیمات چه می‌گوید».
 *
 * مسیر رسمی خود افزونه: دریافت access_token با refresh_token، سپس صدا زدن
 * listfiles('backup_') که به Drive واقعی وصل می‌شود. اگر توکن منقضی/باطل باشد،
 * خطای دقیق برگردانده می‌شود تا مالک بداند چه کاری در پیشخوان لازم است.
 *
 * @return array
 */
/**
 * v2.22.137: آیا جدول‌های *options خود افزونه (نه wp_options) توکن نگه می‌دارند؟
 *
 * نسخهٔ نصب‌شده (۱.۲۶.۱۶) حساب Google Drive را در ساختار instance نگه می‌دارد و
 * ممکن است مقادیر محرمانه در جدول‌های جدای افزونه باشند، نه در wp_options. برای
 * اینکه «تنظیم نشده» را به‌غلط گزارش نکنیم، وجود و شمارش ردیف‌ها و کلیدها بررسی
 * می‌شود — هرگز مقدارها خوانده یا برگردانده نمی‌شوند.
 *
 * @return array
 */
function sa_agent_updraft_secret_store() {
	global $wpdb;
	$out = array( 'tables' => array(), 'any_secret_row' => false );
	$like = $wpdb->esc_like( 'updraft' ) . '%';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( (array) $tables as $t ) {
		$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$t`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM `$t`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
		$has_secret_col = false;
		$key_hits       = array();
		foreach ( (array) $cols as $c ) {
			$lc = strtolower( (string) $c );
			if ( in_array( $lc, array( 'value', 'option_value', 'settings', 'data' ), true ) ) {
				$has_secret_col = true;
			}
		}
		$out['tables'][] = array(
			'name'   => $t,
			'rows'   => $rows,
			'cols'   => array_values( (array) $cols ),
			'has_secret_col' => $has_secret_col,
		);
		if ( $has_secret_col && $rows > 0 ) {
			$out['any_secret_row'] = true;
		}
	}
	return $out;
}

function sa_agent_updraft_drive_test() {
	$out = array(
		'configured'      => false,
		'has_client'      => false,
		'has_secret'      => false,
		'has_refresh'     => false,
		'token_ok'        => false,
		'listing_ok'      => false,
		'file_count'      => 0,
		'sample'          => array(),
		'error'           => '',
		'error_hint'      => '',
		'connected'       => false,
	);
	$raw = sa_agent_updraft_option( 'updraft_googledrive', array() );
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}
	// تشخیص: کدام کلیدهای همکار با Drive در دیتابیس هستند (فقط وجود/طول، نه مقدار).
	$out['option_keys'] = array_keys( $raw );
	$out['option_structure'] = 'flat';
	$opts = $raw;
	// v2.22.135: ساختار تازهٔ افزونه کلیدها را زیر settings نگه می‌دارد.
	if ( isset( $raw['settings'] ) && is_array( $raw['settings'] ) ) {
		$out['option_structure']    = 'nested_settings';
		$out['settings_keys']       = array_keys( $raw['settings'] );
		$out['settings_sub_keys']   = array();
		foreach ( $raw['settings'] as $sk => $sv ) {
			$out['settings_sub_keys'][ $sk ] = is_array( $sv ) ? array_keys( $sv ) : gettype( $sv );
		}
		$opts = $raw['settings'];
		// v2.22.136: نسخهٔ افزونه هر حساب را در یک «instance» (کلید s-…) نگه می‌دارد:
		// settings → s-xxxx → {clientid, secret, token, …}. حسابِ instance_enabled را برمی‌داریم.
		$instances = array();
		foreach ( $raw['settings'] as $sk => $sv ) {
			if ( 0 === strpos( (string) $sk, 's-' ) && is_array( $sv ) ) {
				$instances[ $sk ] = $sv;
			}
		}
		$out['instances'] = array();
		$chosen = null;
		foreach ( $instances as $sk => $sv ) {
			$enabled = false;
			if ( isset( $sv['instance_enabled'] ) ) {
				$enabled = ! empty( $sv['instance_enabled'] ) && '1' !== (string) $sv['instance_enabled'] ? (bool) $sv['instance_enabled'] : (bool) (int) $sv['instance_enabled'];
			}
			$out['instances'][ $sk ] = array(
				'enabled'      => $enabled,
				'has_client'   => ! empty( $sv['clientid'] ),
				'has_secret'   => ! empty( $sv['secret'] ),
				'has_token'    => ! empty( $sv['token'] ),
				'has_tmp'      => ! empty( $sv['tmp_access_token'] ),
				'owner_email'  => isset( $sv['owneremail'] ) ? (string) $sv['owneremail'] : '',
				'folder'       => isset( $sv['folder'] ) ? (string) $sv['folder'] : '',
				'token_len'    => isset( $sv['token'] ) ? strlen( (string) $sv['token'] ) : 0,
			);
			if ( null === $chosen && ( $enabled || 1 === count( $instances ) ) ) {
				$chosen = $sv;
			}
		}
		if ( is_array( $chosen ) ) {
			$opts = $chosen;
			$out['instance_used'] = $sk;
		}
	}
	$find = static function ( $arr, $keys ) {
		foreach ( $keys as $k ) {
			if ( isset( $arr[ $k ] ) && '' !== (string) $arr[ $k ] ) {
				return (string) $arr[ $k ];
			}
		}
		return '';
	};
	$clientid = $find( $opts, array( 'clientid', 'client_id', 'clientId' ) );
	$secret   = $find( $opts, array( 'secret', 'client_secret', 'clientSecret' ) );
	$refresh  = $find( $opts, array( 'token', 'refresh_token', 'refreshToken' ) );
	$out['resolved_keys'] = array(
		'clientid' => '' !== $clientid,
		'secret'   => '' !== $secret,
		'refresh'  => '' !== $refresh,
	);
	$out['stored']      = array(
		'updraft_googledrive_type'   => gettype( sa_agent_updraft_option( 'updraft_googledrive', null ) ),
		'updraft_service'            => sa_agent_updraft_option( 'updraft_service', null ),
		'googledrive_clientid_len'   => strlen( (string) sa_agent_updraft_option( 'updraft_googledrive_clientid', '' ) ),
		'googledrive_secret_len'     => strlen( (string) sa_agent_updraft_option( 'updraft_googledrive_secret', '' ) ),
		'tmp_access_token_present'   => '' !== (string) sa_agent_updraft_option( 'updraftplus_tmp_googledrive_access_token', '' ),
		'refreshtoken_option_len'    => strlen( (string) sa_agent_updraft_option( 'updraft_googledrive_refreshtoken', '' ) ),
	);
	$out['configured']  = ( '' !== $clientid || '' !== $secret || '' !== $refresh );
	$out['has_client']  = '' !== $clientid;
	$out['has_secret']  = '' !== $secret;
	$out['has_refresh'] = '' !== $refresh;
	if ( ! $out['has_client'] || ! $out['has_secret'] || ! $out['has_refresh'] ) {
		$out['secret_store'] = sa_agent_updraft_secret_store();
		$out['error']      = 'incomplete_credentials';
		$out['error_hint'] = 'در پیشخوان → تنظیمات → UpdraftPlus → «تنظیمات فضای ذخیره‌سازی»: Google Drive را باز کنید و «Connect with Google Drive» را بزنید تا توکن تازه ساخته شود.';
		return $out;
	}
	if ( ! defined( 'UPDRAFTPLUS_DIR' ) || ! is_file( UPDRAFTPLUS_DIR . '/methods/googledrive.php' ) ) {
		$out['error'] = 'plugin_dir_unknown';
		return $out;
	}
	require_once UPDRAFTPLUS_DIR . '/methods/googledrive.php';
	if ( ! class_exists( 'UpdraftPlus_BackupModule_googledrive' ) ) {
		$out['error'] = 'class_missing';
		return $out;
	}
	$obj = new UpdraftPlus_BackupModule_googledrive();
	// شاهد ۱: توکن واقعی از Google گرفته می‌شود.
	$getter = new ReflectionMethod( 'UpdraftPlus_BackupModule_googledrive', 'access_token' );
	$getter->setAccessible( true );
	$token = $getter->invoke( $obj, $refresh, $clientid, $secret );
	$out['token_ok'] = is_string( $token ) && '' !== $token;
	if ( ! $out['token_ok'] ) {
		$out['error']      = 'token_refresh_failed';
		$out['error_hint'] = 'توکن Google Drive تازه نشد (منقضی یا باطل است). در پیشخوان → UpdraftPlus → Settings → Google Drive: «Unlink» و بعد دوباره «Connect with Google Drive»؛ دسترسی را تأیید کنید.';
		return $out;
	}
	// شاهد ۲: فهرست واقعی فایل‌های همان حساب.
	$files = $obj->listfiles( 'backup_' );
	if ( is_wp_error( $files ) ) {
		$out['error']      = 'listing_failed: ' . $files->get_error_code();
		$out['error_message'] = $files->get_error_message();
		$out['error_hint'] = 'توکن گرفته شد ولی دسترسی به پوشهٔ Drive برقرار نشد؛ در پیشخوان اتصال را دوباره برقرار کنید.';
		return $out;
	}
	if ( ! is_array( $files ) ) {
		$out['error'] = 'listing_not_array';
		return $out;
	}
	$out['listing_ok'] = true;
	$out['file_count'] = count( $files );
	foreach ( array_slice( $files, 0, 10 ) as $f ) {
		$out['sample'][] = array(
			'name'  => isset( $f['name'] ) ? (string) $f['name'] : '',
			'bytes' => isset( $f['size'] ) ? (int) $f['size'] : 0,
		);
	}
	$out['connected'] = true;
	return $out;
}

function sa_agent_updraft_cloud_probe( $nonce ) {
	$nonce = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $nonce ) );
	$out   = array(
		'nonce'          => $nonce,
		'services'       => array(),
		'remote_files'   => array(),
		'remote_bytes'   => 0,
		'log_file'       => '',
		'log_upload_ok'  => array(),
		'log_errors'     => array(),
		'log_service_lines' => array(),
		'log_local_deleted' => array(),
		'no_remote_despatch' => false,
		'log_lines'      => 0,
		'last_backup'    => null,
		'complete'       => false,
		'reasons'        => array(),
	);
	if ( '' === $nonce ) {
		$out['reasons'][] = 'bad_nonce';
		return $out;
	}
	$history = (array) sa_agent_updraft_option( 'updraft_backup_history', array() );
	$entry   = null;
	foreach ( $history as $time => $row ) {
		if ( is_array( $row ) && isset( $row['nonce'] ) && (string) $row['nonce'] === $nonce ) {
			$entry = $row;
			break;
		}
	}
	if ( null === $entry ) {
		$out['reasons'][] = 'not_in_history';
		return $out;
	}
	$services = isset( $entry['service'] ) ? $entry['service'] : array();
	if ( is_string( $services ) ) {
		$services = array( $services );
	}
	$services = array_values( array_filter( array_map( 'strval', (array) $services ), static function ( $v ) {
		return '' !== $v && 'none' !== $v;
	} ) );
	// v2.22.130: تاریخچهٔ این سایت کلید service را نگه نمی‌دارد؛ در این حالت از
	// خدمتِ پیکربندی‌شده در تنظیمات استفاده می‌کنیم تا بررسی «هیچ» انجام نشود.
	$service_source = 'history';
	if ( ! $services ) {
		$conf = sa_agent_updraft_option( 'updraft_service', '' );
		if ( is_string( $conf ) ) {
			$conf = array( $conf );
		}
		$services = array_values( array_filter( array_map( 'strval', (array) $conf ), static function ( $v ) {
			return '' !== $v && 'none' !== $v;
		} ) );
		$service_source = 'settings';
	}
	$out['services']      = $services;
	$out['service_source'] = $service_source;
	$out['history_keys']  = array_keys( (array) $entry );

	// v2.22.140: چه چیزهایی باید روی ابر باشند؟ از خود تاریخچه.
	$out['expected_entities'] = array_values( array_intersect( array_keys( (array) $entry ), array( 'db', 'plugins', 'themes', 'uploads', 'others', 'mu-plugins' ) ) );
	$out['listfiles_status']  = 'not_called';
	// شاهد ۱: فهرست واقعی فایل‌های همان نسخه روی فضای ابری.
	if ( defined( 'UPDRAFTPLUS_DIR' ) ) {
		foreach ( $services as $service ) {
			$path = UPDRAFTPLUS_DIR . '/methods/' . $service . '.php';
			if ( ! is_file( $path ) ) {
				$out['reasons'][] = 'no_method_' . $service;
				continue;
			}
			require_once $path;
			$class = 'UpdraftPlus_BackupModule_' . $service;
			if ( ! class_exists( $class ) ) {
				$out['reasons'][] = 'no_class_' . $service;
				continue;
			}
			$obj = new $class();
			if ( ! method_exists( $obj, 'listfiles' ) ) {
				$out['reasons'][] = 'no_listfiles_' . $service;
				continue;
			}
			$files = $obj->listfiles( 'backup_' );
			if ( is_wp_error( $files ) ) {
				$out['listfiles_status'] = 'error:' . $files->get_error_code() . ':' . $files->get_error_message();
				$out['reasons'][] = 'listfiles_error_' . $service . ':' . $files->get_error_code();
				continue;
			}
			if ( ! is_array( $files ) ) {
				$out['listfiles_status'] = 'not_array';
				$out['reasons'][] = 'listfiles_empty_' . $service;
				continue;
			}
			$out['listfiles_status'] = 'ok:' . count( $files ) . ' remote objects seen';
			$out['remote_total_seen'] = ( $out['remote_total_seen'] ?? 0 ) + count( $files );
			foreach ( $files as $f ) {
				$name = isset( $f['name'] ) ? (string) $f['name'] : '';
				if ( '' === $name || false === strpos( $name, $nonce ) ) {
					continue;
				}
				$size = isset( $f['size'] ) ? (int) $f['size'] : 0;
				$out['remote_files'][] = array( 'service' => $service, 'name' => $name, 'bytes' => $size );
				$out['remote_bytes']  += $size;
			}
		}
	} else {
		$out['reasons'][] = 'updraftplus_dir_unknown';
	}

	// شاهد ۲: لاگ همان نسخه.
	$dirs = function_exists( 'sa_agent_updraft_dirs' ) ? sa_agent_updraft_dirs() : array();
	foreach ( $dirs as $dir ) {
		$log = trailingslashit( $dir ) . 'log.' . $nonce . '.txt';
		if ( ! is_file( $log ) ) {
			continue;
		}
		$out['log_file'] = $log;
		$body = (string) @file_get_contents( $log ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$lines = preg_split( "/\r\n|\n/", $body );
		$out['log_lines'] = count( (array) $lines );
		foreach ( (array) $lines as $line ) {
			if ( false !== strpos( $line, 'Deleting local file: ' ) ) {
				$out['log_local_deleted'][] = trim( $line );
			}
			if ( preg_match( '/(No remote despatch|Cloud backup selection|Beginning dispatch|Recording as successfully uploaded)/', $line ) ) {
				$out['log_service_lines'][] = trim( $line );
			}
			// v2.22.138: نسخه‌های تازه «uploaded in 9.54 seconds» می‌نویسند، نه
			// «uploaded to …»؛ نسخهٔ قبلی این خطوط را نمی‌دید و نتیجهٔ «آپلود نشده» می‌داد.
			if ( false !== strpos( $line, 'OK: Archive ' ) && preg_match( '/OK: Archive (.+?) uploaded (?:to|in) /', $line, $m ) ) {
				$out['log_upload_ok'][] = trim( $m[1] );
			} elseif ( sa_agent_updraft_log_is_error( $line ) ) {
				$out['log_errors'][] = trim( substr( $line, 0, 300 ) );
			}
		}
		break;
	}
	if ( '' === $out['log_file'] ) {
		$out['reasons'][] = 'no_log_file';
	}

	// شاهد ۳: آخرین پشتیبان (موفق/ناموفق + خطاها).
	$last = sa_agent_updraft_option( 'updraft_last_backup', array() );
	if ( is_array( $last ) && isset( $last['backup_nonce'] ) && (string) $last['backup_nonce'] === $nonce ) {
		$out['last_backup'] = array(
			'backup_time' => isset( $last['backup_time'] ) ? gmdate( 'c', (int) $last['backup_time'] ) : '',
			'success'     => ! empty( $last['success'] ),
			'errors'      => isset( $last['errors'] ) ? count( (array) $last['errors'] ) : 0,
		);
	}

	// داوری: هر فایلِ روی دیسک باید هم‌نامش روی فضای ابری باشد، هم در لاگ تأیید شده باشد.
	$disk = array();
	foreach ( function_exists( 'sa_agent_updraft_set_files' ) ? sa_agent_updraft_set_files( array( $nonce => $entry ) ) : array() as $set ) {
		foreach ( (array) $set['files'] as $f ) {
			$disk[] = $f['name'];
		}
	}
	$remote_names = array();
	foreach ( $out['remote_files'] as $f ) {
		$remote_names[] = $f['name'];
	}
	$missing_remote = array_values( array_diff( $disk, $remote_names ) );
	$missing_log    = array_values( array_diff( $disk, $out['log_upload_ok'] ) );
	// v2.22.138: نبودِ فایل محلی «خطا» نیست؛ با delete_local=1 فایل‌ها بلافاصله پس از
	// آپلود موفق پاک می‌شوند. داوری باید بر شاهدهای ابری و لاگ بایستد، نه وجود دیسک.
	$out['local_absent_ok'] = ( ! $disk && 1 === (int) sa_agent_updraft_option( 'updraft_delete_local', 0 ) );
	if ( ! $disk && ! $out['local_absent_ok'] ) {
		$out['reasons'][] = 'no_local_files';
	}
	if ( $missing_remote ) {
		$out['reasons'][] = 'files_missing_remote:' . count( $missing_remote );
	}
	if ( $missing_log ) {
		$out['reasons'][] = 'files_not_confirmed_in_log:' . count( $missing_log );
	}
	// v2.22.139: خطای گذرا (مثل OpenSSL SSL_ERROR_SYSCALL در یک retry) اگر همان فایل
	// بعداً با موفقیت آپلود شده باشد، مانع تأیید نیست — ولی شفاف گزارش می‌شود.
	$transient = 0;
	foreach ( $out['log_errors'] as $err_line ) {
		if ( false === strpos( $err_line, 'upload exception' ) ) {
			continue;
		}
		if ( preg_match( '/(backup_[\-0-9]+_[A-Za-z0-9]+_[0-9a-f]{12}-[a-z0-9]+\.(?:zip|gz))/', $err_line, $mm ) ) {
			if ( in_array( $mm[1], $out['log_upload_ok'], true ) ) {
				++$transient;
				continue;
			}
		}
	}
	$out['transient_errors'] = $transient;
	$out['hard_errors'] = array_values( array_filter( $out['log_errors'], static function ( $l ) {
		return false === strpos( $l, 'upload exception' );
	} ) );
	if ( $out['hard_errors'] ) {
		$out['reasons'][] = 'log_has_errors:' . count( $out['hard_errors'] );
	}
	if ( $out['last_backup'] && ! $out['last_backup']['success'] ) {
		$out['reasons'][] = 'last_backup_not_success';
	}
	// شاهد ۲ب (v2.22.131): اگر لاگ صریحاً بگوید «No remote despatch» یعنی UpdraftPlus
	// اصلاً به فضای ابری نفرستاده؛ این قوی‌ترین شاهد منفی است و باید کل بررسی را رد کند.
	$no_despatch = array_values( array_filter( $out['log_service_lines'], static function ( $l ) {
		return false !== strpos( $l, 'No remote despatch' );
	} ) );
	$out['no_remote_despatch'] = (bool) $no_despatch;
	if ( $no_despatch ) {
		$out['reasons'][] = 'log_says_no_remote_despatch';
	}
	// v2.22.140 (اصلاح باگ): اگر دیسک خالی باشد، شرط «هر فایل دیسک روی ابر هست» تهی
	// می‌شود و می‌توانست با صفر فایل ابری هم true بدهد. حالا پوشش اجزای تاریخچه
	// جداگانه سنجیده می‌شود: هر جزء (db/plugins/themes/uploads/…) باید فایل ابری
	// و خط آپلود موفق داشته باشد.
	$covered = array();
	foreach ( $out['remote_files'] as $rf ) {
		foreach ( $out['expected_entities'] as $ent ) {
			$needle = ( 'mu-plugins' === $ent ) ? '-mu-plugins.zip' : '-' . $ent;
			if ( false !== strpos( $rf['name'], $needle ) ) {
				$covered[ $ent ] = true;
			}
		}
	}
	if ( isset( $covered['others'] ) && ! isset( $covered['uploads'] ) ) {
		// «others» با نام -others.zip می‌آید؛ برای uploads نام -uploads.zip است.
	}
	$out['entities_covered'] = array_keys( $covered );
	$out['entities_missing'] = array_values( array_diff( $out['expected_entities'], array_keys( $covered ) ) );
	if ( $out['expected_entities'] && $out['entities_missing'] ) {
		$out['reasons'][] = 'entities_missing:' . implode( ',', $out['entities_missing'] );
	}
	$out['complete'] = $out['expected_entities']
		&& ! $out['entities_missing']
		&& ( $disk || $out['local_absent_ok'] )
		&& count( $out['remote_files'] ) >= count( $out['expected_entities'] )
		&& ! $missing_remote
		&& ! $missing_log
		&& ! $out['hard_errors']
		&& ! $no_despatch
		&& ( ! $out['last_backup'] || $out['last_backup']['success'] );
	return $out;
}

function sa_agent_updraft_orphan_files() {
	$history = (array) sa_agent_updraft_option( 'updraft_backup_history', array() );
	$known   = array();
	foreach ( $history as $entry ) {
		if ( is_array( $entry ) && isset( $entry['nonce'] ) ) {
			$known[] = (string) $entry['nonce'];
		}
	}
	$out   = array();
	$bytes = 0;
	foreach ( sa_agent_updraft_dirs() as $dir ) {
		foreach ( (array) glob( trailingslashit( $dir ) . 'backup_*' ) as $path ) {
			if ( ! is_file( $path ) ) {
				continue;
			}
			$keep = false;
			foreach ( $known as $nonce ) {
				if ( '' !== $nonce && false !== strpos( basename( $path ), $nonce ) ) {
					$keep = true;
					break;
				}
			}
			if ( $keep ) {
				continue;
			}
			$size   = (int) filesize( $path );
			$bytes += $size;
			$out[]  = array( 'name' => basename( $path ), 'dir' => $dir, 'bytes' => $size );
		}
	}
	return array( 'files' => $out, 'bytes' => $bytes );
}

/**
 * پاک‌کردن فایل‌های یک نسخهٔ UpdraftPlus از تاریخچه — فقط با پرچم مالک.
 *
 * چرا (دستور مالک، ۲۸ آبان ۱۴۰۵): هاست سقف حجم دارد و فایل‌های نسخه‌های قدیمی
 * در پوشهٔ `wp-content/updraft` مانده‌اند. این تنها راه حذف فایل در کل قالب است و
 * عمداً تنگ است: فقط فایل‌هایی که نامشان در تاریخچهٔ خودِ UpdraftPlus آمده، فقط
 * داخل پوشهٔ پشتیبان، و فقط با `owner_approved_prune`. هیچ فایل افزونه/رسانه/قالب
 * از این مسیر حذف نمی‌شود.
 *
 * @param string $nonce شناسهٔ نسخه.
 * @return array|WP_Error
 */
function sa_agent_updraft_prune_set( $nonce ) {
	$nonce = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $nonce ) );
	if ( '' === $nonce ) {
		return new WP_Error( 'sa_agent_bad_nonce', 'شناسهٔ نسخهٔ پشتیبان لازم است.', array( 'status' => 400 ) );
	}
	$history = (array) sa_agent_updraft_option( 'updraft_backup_history', array() );
	$sets    = sa_agent_updraft_set_files( $history );
	$target  = null;
	foreach ( $sets as $set ) {
		if ( $set['nonce'] === $nonce ) {
			$target = $set;
			break;
		}
	}
	if ( null === $target ) {
		return new WP_Error( 'sa_agent_no_set', 'این نسخه در تاریخچهٔ UpdraftPlus نیست؛ چیزی حذف نشد.', array( 'status' => 404 ) );
	}
	$uploads = function_exists( 'wp_upload_dir' ) ? trailingslashit( wp_upload_dir()['basedir'] ) . 'updraft' : '';
	$dirs    = array_filter( array( $uploads, WP_CONTENT_DIR . '/updraft' ) );
	$deleted = array();
	$freed   = 0;
	$kept    = array();
	foreach ( $target['files'] as $file ) {
		$name = basename( (string) $file['name'] );
		$done = false;
		foreach ( $dirs as $dir ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! is_file( $path ) ) {
				continue;
			}
			// فقط داخل پوشهٔ پشتیبان؛ اگر مسیر واقعی بیرون بزند، دست نمی‌زنیم.
			$real = realpath( $path );
			if ( false === $real || 0 !== strpos( $real, realpath( $dir ) ) ) {
				continue;
			}
			$size = (int) filesize( $real );
			if ( @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$deleted[] = $name;
				$freed    += $size;
				$done      = true;
			}
			break;
		}
		if ( ! $done ) {
			$kept[] = $name;
		}
	}
	if ( $deleted ) {
		// نسخه‌ای که فایل‌هایش رفته، از تاریخچه برداشته می‌شود تا افزونه نسخهٔ ناقص نشان ندهد.
		foreach ( $history as $time => $entry ) {
			if ( is_array( $entry ) && isset( $entry['nonce'] ) && (string) $entry['nonce'] === $nonce ) {
				unset( $history[ $time ] );
			}
		}
		if ( class_exists( 'UpdraftPlus_Options' ) && method_exists( 'UpdraftPlus_Options', 'update_updraft_option' ) ) {
			UpdraftPlus_Options::update_updraft_option( 'updraft_backup_history', $history );
		} else {
			update_option( 'updraft_backup_history', $history );
		}
	}
	return array(
		'nonce'   => $nonce,
		'deleted' => $deleted,
		'kept'    => $kept,
		'bytes'   => $freed,
		'mb'      => round( $freed / 1048576, 1 ),
	);
}

/**
 * فهرست بزرگ‌ترین فایل‌های یک پوشه (نام، بایت، تاریخ) — فقط خواندن، برای تصمیم حذف.
 *
 * @param string $dir   مسیر.
 * @param int    $limit حداکثر تعداد.
 * @return array[]
 */
function sa_agent_storage_list_files( $dir, $limit = 40 ) {
	$dir = (string) $dir;
	if ( '' === $dir || ! is_dir( $dir ) ) {
		return array();
	}
	$out = array();
	try {
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY,
			RecursiveIteratorIterator::CATCH_GET_CHILD
		);
		foreach ( $it as $file ) {
			if ( ! $file->isFile() || $file->isLink() ) {
				continue;
			}
			$out[] = array(
				'name'     => ltrim( str_replace( $dir, '', $file->getPathname() ), '/\\' ),
				'bytes'    => (int) $file->getSize(),
				'modified' => gmdate( 'c', (int) $file->getMTime() ),
			);
		}
	} catch ( Exception $e ) {
		return array();
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return $b['bytes'] <=> $a['bytes'];
		}
	);
	return array_slice( $out, 0, max( 1, (int) $limit ) );
}

/**
 * بایت‌های یک رکورد پشتیبان — برای رکوردهای پیش از ۲.۲۲.۱۲۰ اندازه ذخیره نشده،
 * پس همان‌جا از طول JSON سنجیده می‌شود (فقط خواندن).
 *
 * @param int $bid شناسهٔ رکورد پشتیبان.
 * @return int
 */
function sa_agent_backup_bytes( $bid ) {
	$bytes = get_post_meta( (int) $bid, '_sa_backup_bytes', true );
	if ( '' === $bytes || null === $bytes ) {
		$bytes = strlen( (string) get_post_meta( (int) $bid, '_sa_backup_json', true ) );
	}
	return (int) $bytes;
}

/**
 * POST /backups/prune — نگه‌داری پشتیبان‌ها: پیش‌نمایش، و حذف فقط با پرچم مالک.
 *
 * پیش‌فرض همیشه پیش‌نمایش است (`dry_run` نبود یعنی پیش‌نمایش). حذف فقط رکوردهای
 * پشتیبانِ پل را می‌برد و هیچ نوشته/رسانه‌ای را لمس نمی‌کند.
 *
 * بدنه: owner_approved_prune:true (برای حذف)، per_target (پیش‌فرض ۳)، total (پیش‌فرض ۴۰۰)،
 *       dry_run:false برای حذف واقعی.
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_backups_prune( $r ) {
	$b          = (array) $r->get_json_params();
	$dry_run    = ! isset( $b['dry_run'] ) || false !== $b['dry_run'];
	$per_target = max( 1, (int) ( isset( $b['per_target'] ) ? $b['per_target'] : 3 ) );
	$total      = max( $per_target, (int) ( isset( $b['total'] ) ? $b['total'] : 400 ) );
	if ( ! $dry_run && empty( $b['owner_approved_prune'] ) ) {
		return new WP_Error( 'sa_agent_need_owner', 'حذف پشتیبان‌های کهنه فقط با owner_approved_prune: true.', array( 'status' => 403 ) );
	}
	$ids = get_posts(
		array(
			'post_type'      => SA_AGENT_BACKUP_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 5000,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
	$ids   = array_map( 'intval', (array) $ids );
	$seen  = array();
	$drop  = array();
	$bytes = 0;
	// v2.22.126 (شرط مالک): انواعی که در کل سایت نسخهٔ کمی دارند (مثل تنظیمات LiteSpeed با
	// یک نسخه) هرگز حذف نمی‌شوند؛ «تنها نسخهٔ موجود از یک تغییر» نباید قربانی نگه‌داری شود.
	$kind_total = array();
	foreach ( $ids as $bid ) {
		$k = (string) get_post_meta( $bid, '_sa_backup_kind', true );
		$kind_total[ $k ] = isset( $kind_total[ $k ] ) ? $kind_total[ $k ] + 1 : 1;
	}
	$protected = array();
	foreach ( $ids as $bid ) {
		$target = (int) get_post_meta( $bid, '_sa_backup_target', true );
		$kind   = (string) get_post_meta( $bid, '_sa_backup_kind', true );
		$size   = sa_agent_backup_bytes( $bid );
		$bytes += $size;
		$key    = $kind . ':' . $target;
		$seen[ $key ] = isset( $seen[ $key ] ) ? $seen[ $key ] + 1 : 1;
		if ( $seen[ $key ] <= $per_target ) {
			continue;
		}
		// v2.22.127 (شرط مالک): فقط پشتیبانِ «نوشته» قابل حذف است. نسخهٔ تنظیمات
		// (theme_mods / litespeed) سندِ وضع ظاهر و خزنده است و «تنها نسخه»ی آن نوع؛
		// هرگز در فهرست حذف نمی‌آید — حتی اگر شمارش نوع به هر دلیلی عجیب باشد.
		if ( 'post' !== $kind ) {
			$protected[] = array( 'id' => $bid, 'kind' => $kind, 'target' => $target, 'bytes' => $size, 'reason' => 'settings_snapshot_never_pruned' );
			continue;
		}
		if ( isset( $kind_total[ $kind ] ) && $kind_total[ $kind ] <= $per_target ) {
			// نوعِ کم‌نسخه: نگه می‌داریم و در گزارش می‌گوییم چرا.
			$protected[] = array( 'id' => $bid, 'kind' => $kind, 'target' => $target, 'bytes' => $size, 'reason' => 'kind_has_few_copies' );
			continue;
		}
		$drop[] = array( 'id' => $bid, 'kind' => $kind, 'target' => $target, 'bytes' => $size, 'date_gmt' => (string) get_post_field( 'post_date_gmt', $bid ) );
	}
	$kept = count( $ids ) - count( $drop );
	if ( $kept > $total ) {
		// سقف کل: از قدیمی‌ترین‌ها می‌زنیم (فهرست از تازه به قدیم است).
		foreach ( array_slice( $ids, $total ) as $bid ) {
			foreach ( $drop as $d ) {
				if ( $d['id'] === (int) $bid ) {
					continue 2;
				}
			}
			// v2.22.129 (شرط مالک): نوعِ محافظت‌شده با سقف کل هم حذف نمی‌شود.
			if ( 'post' !== (string) get_post_meta( $bid, '_sa_backup_kind', true ) ) {
				$protected[] = array(
					'id'     => (int) $bid,
					'kind'   => (string) get_post_meta( $bid, '_sa_backup_kind', true ),
					'target' => (int) get_post_meta( $bid, '_sa_backup_target', true ),
					'bytes'  => sa_agent_backup_bytes( $bid ),
					'reason' => 'settings_snapshot_never_pruned',
				);
				continue;
			}
			$drop[] = array(
				'id'        => (int) $bid,
				'kind'      => (string) get_post_meta( $bid, '_sa_backup_kind', true ),
				'target'    => (int) get_post_meta( $bid, '_sa_backup_target', true ),
				'bytes'     => sa_agent_backup_bytes( $bid ),
				'date_gmt'  => (string) get_post_field( 'post_date_gmt', $bid ),
			);
			--$kept;
		}
	}
	$freed = 0;
	foreach ( $drop as $d ) {
		$freed += (int) $d['bytes'];
	}
	$out = array(
		'marker'        => 'prune-v129',
		'dry_run'       => $dry_run,
		'per_target'    => $per_target,
		'total_limit'   => $total,
		'records'       => count( $ids ),
		'bytes'         => $bytes,
		'candidates'    => count( $drop ),
		'candidate_bytes' => $freed,
		'protected'     => $protected,
		'prunable_kinds' => array( 'post' ),
		'kept'          => $kept,
		'list'          => $drop,
	);
	if ( ! $dry_run ) {
		$deleted = 0;
		foreach ( $drop as $d ) {
			// سد دوم: حتی اگر فهرست به هر دلیلی آلوده باشد، فقط رکورد «نوشته» حذف می‌شود.
			if ( 'post' !== (string) ( isset( $d['kind'] ) ? $d['kind'] : '' ) ) {
				continue;
			}
			if ( sa_agent_delete_backup_record( (int) $d['id'] ) ) {
				++$deleted;
			}
		}
		$out['deleted']       = $deleted;
		$out['bytes_after']   = max( 0, $bytes - $freed );
		$out['kept_after']    = max( 0, $kept );
	}
	return rest_ensure_response( $out );
}

/**
 * POST /updraft/prune — حذف فایل یک نسخهٔ UpdraftPlus (فقط با پرچم مالک).
 *
 * بدنه: owner_approved_prune:true، nonce (شناسهٔ نسخه)، dry_run (پیش‌فرض true).
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
/**
 * GET /updraft/cloud — سه شاهدِ آپلود یک نسخه روی فضای ابری (فقط خواندنی).
 *
 * @param WP_REST_Request $r درخواست: nonce.
 * @return WP_REST_Response|WP_Error
 */
/**
 * GET /updraft/log — خطوط مرتبط یک لاگ پشتیبان (فقط خواندنی، الگوهای مجاز).
 *
 * برای اینکه «حدس» نزنیم و لاگ را واقعاً ببینیم؛ فقط خطوط مربوط به آپلود/خدمت/خطا
 * برمی‌گردند و هر نشانهٔ توکن پاک می‌شود.
 *
 * @param WP_REST_Request $r درخواست: nonce، limit.
 * @return WP_REST_Response|WP_Error
 */
/**
 * GET /updraft/drive-test — تست واقعی اتصال Google Drive (فقط خواندنی).
 *
 * @return WP_REST_Response
 */
function sa_agent_rest_updraft_drive_test() {
	return rest_ensure_response( sa_agent_updraft_drive_test() );
}

/**
 * POST /updraft/settings — اصلاح زمان‌بندی و نگه‌داری، فقط کلیدهای مجاز.
 *
 * نگهبان‌ها: ۱) پرچم صریح مالک، ۲) پیش‌فرض پیش‌نمایش، ۳) فقط چهار کلید مجاز
 * (`updraft_interval`, `updraft_retain`, `updraft_retain_db`, `updraft_delete_local`)،
 * ۴) مقدارها اعتبارسنجی می‌شوند، ۵) زمان‌بندی واقعی WP-Cron با متد رسمی افزونه
 * بازسازی می‌شود تا مقدار فقط در دیتابیس نماند.
 *
 * @param WP_REST_Request $r درخواست.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_updraft_settings( $r ) {
	$b       = (array) $r->get_json_params();
	$dry_run = ! isset( $b['dry_run'] ) || false !== $b['dry_run'];
	$allowed = array( 'updraft_interval', 'updraft_retain', 'updraft_retain_db', 'updraft_delete_local' );
	$before  = array();
	foreach ( $allowed as $k ) {
		$before[ $k ] = sa_agent_updraft_option( $k, null );
	}
	$want = array();
	if ( isset( $b['updraft_interval'] ) ) {
		$v = (string) $b['updraft_interval'];
		$ok = array( 'manual', 'weekly', 'daily', 'monthly', 'hourly', 'twicedaily', 'every4hours', 'every8hours', 'every12hours' );
		if ( ! in_array( $v, $ok, true ) ) {
			return new WP_Error( 'sa_agent_bad_interval', 'مقدار مجاز نیست: ' . $v, array( 'status' => 400 ) );
		}
		$want['updraft_interval'] = $v;
	}
	foreach ( array( 'updraft_retain', 'updraft_retain_db' ) as $k ) {
		if ( isset( $b[ $k ] ) ) {
			$n = (int) $b[ $k ];
			if ( $n < 1 || $n > 30 ) {
				return new WP_Error( 'sa_agent_bad_retain', 'مقدار نگه‌داری باید بین ۱ و ۳۰ باشد: ' . $k, array( 'status' => 400 ) );
			}
			$want[ $k ] = $n;
		}
	}
	if ( isset( $b['updraft_delete_local'] ) ) {
		$want['updraft_delete_local'] = empty( $b['updraft_delete_local'] ) ? 0 : 1;
	}
	if ( ! $want ) {
		return new WP_Error( 'sa_agent_nothing', 'هیچ کلید مجازی برای تغییر فرستاده نشد.', array( 'status' => 400 ) );
	}
	$out = array(
		'dry_run'       => $dry_run,
		'before'        => $before,
		'want'          => $want,
		'allowed_keys'  => $allowed,
		'applied'       => false,
	);
	if ( $dry_run ) {
		return rest_ensure_response( $out );
	}
	if ( empty( $b['owner_approved_settings'] ) ) {
		return new WP_Error( 'sa_agent_need_owner', 'تغییر تنظیمات فقط با owner_approved_settings: true.', array( 'status' => 403 ) );
	}
	foreach ( $want as $k => $v ) {
		if ( class_exists( 'UpdraftPlus_Options' ) && method_exists( 'UpdraftPlus_Options', 'update_updraft_option' ) ) {
			UpdraftPlus_Options::update_updraft_option( $k, $v );
		} else {
			update_option( $k, $v );
		}
	}
	$after = array();
	foreach ( $allowed as $k ) {
		$after[ $k ] = sa_agent_updraft_option( $k, null );
	}
	// زمان‌بندی واقعی WP-Cron با متد رسمی افزونه بازسازی می‌شود.
	$schedule = array();
	$up       = sa_agent_updraft();
	if ( $up && method_exists( $up, 'schedule_backup' ) ) {
		$schedule['files'] = $up->schedule_backup( (string) $after['updraft_interval'] );
	}
	if ( $up && method_exists( $up, 'schedule_backup_database' ) ) {
		$schedule['db'] = $up->schedule_backup_database( (string) $after['updraft_interval'] );
	}
	$out['after']       = $after;
	$out['schedule']    = $schedule;
	$out['next_files']  = wp_next_scheduled( 'updraft_backup' ) ? gmdate( 'c', (int) wp_next_scheduled( 'updraft_backup' ) ) : '';
	$out['applied']     = ( $after != $before );
	return rest_ensure_response( $out );
}

function sa_agent_rest_updraft_log( $r ) {
	$nonce = isset( $r['nonce'] ) ? preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $r['nonce'] ) ) : '';
	if ( '' === $nonce ) {
		return new WP_Error( 'sa_agent_bad_nonce', 'nonce نسخهٔ پشتیبان لازم است.', array( 'status' => 400 ) );
	}
	$limit = isset( $r['limit'] ) ? max( 10, min( 400, (int) $r['limit'] ) ) : 200;
	$out   = array( 'nonce' => $nonce, 'file' => '', 'bytes' => 0, 'lines_total' => 0, 'matched' => array(), 'upload_ok' => array(), 'errors' => array(), 'service_lines' => array() );
	foreach ( (array) sa_agent_updraft_dirs() as $dir ) {
		$file = trailingslashit( $dir ) . 'log.' . $nonce . '.txt';
		if ( ! is_file( $file ) ) {
			continue;
		}
		$out['file'] = $file;
		$body        = (string) @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$out['bytes'] = strlen( $body );
		$lines        = preg_split( "/\r\n|\n/", $body );
		$out['lines_total'] = count( (array) $lines );
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/(Bearer|access_token|refresh_token|client_secret)/i', $line ) ) {
				$line = preg_replace( '/(Bearer\s+\S+|access_token=[^&\s]+|refresh_token=[^&\s]+|client_secret=[^&\s]+)/i', '[redacted]', $line );
			}
			if ( preg_match( '/Google Drive|googledrive|upload/i', $line ) && count( $out['matched'] ) < $limit ) {
				$out['matched'][] = $line;
			}
			if ( false !== strpos( $line, 'OK: Archive ' ) && false !== strpos( $line, 'uploaded to ' ) ) {
				$out['upload_ok'][] = $line;
			}
			if ( sa_agent_updraft_log_is_error( $line ) && count( $out['errors'] ) < 40 ) {
				$out['errors'][] = $line;
			}
			if ( preg_match( '/(service|Service|retain|prune|nonce)/', $line ) && count( $out['service_lines'] ) < 60 ) {
				$out['service_lines'][] = $line;
			}
		}
		break;
	}
	return rest_ensure_response( $out );
}

function sa_agent_rest_updraft_cloud( $r ) {
	$nonce = isset( $r['nonce'] ) ? (string) $r['nonce'] : '';
	if ( '' === $nonce ) {
		return new WP_Error( 'sa_agent_bad_nonce', 'nonce نسخهٔ پشتیبان لازم است.', array( 'status' => 400 ) );
	}
	$res = sa_agent_updraft_cloud_probe( $nonce );
	return rest_ensure_response( $res );
}

/**
 * POST /updraft/delete-local — حذف فایل‌های محلیِ یک نسخه، فقط وقتی شواهد آپلود کامل است.
 *
 * این مسیر سه شرط را با هم می‌سنجد و اگر هر کدام نباشد، هیچ فایلی پاک نمی‌شود:
 *   ۱) پرچم صریح مالک، ۲) probe با complete=true، ۳) فایل‌ها داخل پوشهٔ پشتیبان.
 *
 * @param WP_REST_Request $r درخواست: nonce، dry_run، owner_approved_prune.
 * @return WP_REST_Response|WP_Error
 */
function sa_agent_rest_updraft_delete_local( $r ) {
	$b       = (array) $r->get_json_params();
	$dry_run = ! isset( $b['dry_run'] ) || false !== $b['dry_run'];
	$nonce   = isset( $b['nonce'] ) ? (string) $b['nonce'] : '';
	if ( '' === $nonce ) {
		return new WP_Error( 'sa_agent_bad_nonce', 'nonce نسخهٔ پشتیبان لازم است.', array( 'status' => 400 ) );
	}
	$probe = sa_agent_updraft_cloud_probe( $nonce );
	$out   = array(
		'dry_run'    => $dry_run,
		'probe'      => $probe,
		'deleted'    => array(),
		'kept'       => array(),
		'bytes'      => 0,
		'mb'         => 0,
	);
	if ( empty( $probe['complete'] ) ) {
		$out['blocked'] = 'cloud_copy_not_verified';
		return rest_ensure_response( $out );
	}
	if ( $dry_run ) {
		$out['would_delete'] = array();
		foreach ( $probe['remote_files'] as $f ) {
			$out['would_delete'][] = $f['name'];
		}
		return rest_ensure_response( $out );
	}
	if ( empty( $b['owner_approved_prune'] ) ) {
		return new WP_Error( 'sa_agent_need_owner', 'حذف فایل محلی فقط با owner_approved_prune: true.', array( 'status' => 403 ) );
	}
	$nonce   = $probe['nonce'];
	$history = (array) sa_agent_updraft_option( 'updraft_backup_history', array() );
	$sets    = sa_agent_updraft_set_files( $history );
	$target  = null;
	foreach ( $sets as $set ) {
		if ( $set['nonce'] === $nonce ) {
			$target = $set;
			break;
		}
	}
	if ( null === $target ) {
		$out['blocked'] = 'set_not_found';
		return rest_ensure_response( $out );
	}
	// فقط فایل‌هایی که هم روی دیسک‌اند و هم در probe تأیید شده‌اند حذف می‌شوند.
	$verified = array();
	foreach ( $probe['remote_files'] as $f ) {
		$verified[ $f['name'] ] = true;
	}
	$dirs = function_exists( 'sa_agent_updraft_dirs' ) ? sa_agent_updraft_dirs() : array();
	foreach ( $target['files'] as $file ) {
		$name = basename( (string) $file['name'] );
		if ( empty( $verified[ $name ] ) ) {
			$out['kept'][] = $name . ' (not verified remotely)';
			continue;
		}
		$done = false;
		foreach ( $dirs as $dir ) {
			$path = trailingslashit( $dir ) . $name;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$real = realpath( $path );
			$root = realpath( $dir );
			if ( false === $real || false === $root || 0 !== strpos( $real, $root ) ) {
				continue;
			}
			$size = (int) filesize( $real );
			if ( @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$out['deleted'][] = $name;
				$out['bytes']    += $size;
				$done             = true;
			}
			break;
		}
		if ( ! $done ) {
			$out['kept'][] = $name;
		}
	}
	$out['mb'] = round( $out['bytes'] / 1048576, 1 );
	return rest_ensure_response( $out );
}

function sa_agent_rest_updraft_prune( $r ) {
	$b       = (array) $r->get_json_params();
	$dry_run = ! isset( $b['dry_run'] ) || false !== $b['dry_run'];
	$nonce   = isset( $b['nonce'] ) ? (string) $b['nonce'] : '';
	if ( '' === $nonce ) {
		return new WP_Error( 'sa_agent_bad_nonce', 'nonce نسخهٔ پشتیبان لازم است.', array( 'status' => 400 ) );
	}
	$history = (array) sa_agent_updraft_option( 'updraft_backup_history', array() );
	$sets    = sa_agent_updraft_set_files( $history );
	$target  = null;
	foreach ( $sets as $set ) {
		if ( $set['nonce'] === preg_replace( '/[^a-z0-9]/', '', strtolower( $nonce ) ) ) {
			$target = $set;
			break;
		}
	}
	if ( null === $target ) {
		return new WP_Error( 'sa_agent_no_set', 'این نسخه در تاریخچهٔ UpdraftPlus نیست؛ چیزی حذف نشد.', array( 'status' => 404 ) );
	}
	if ( $dry_run ) {
		return rest_ensure_response( array( 'dry_run' => true, 'set' => $target, 'mb' => round( (int) $target['bytes'] / 1048576, 1 ) ) );
	}
	if ( empty( $b['owner_approved_prune'] ) ) {
		return new WP_Error( 'sa_agent_need_owner', 'حذف فایل پشتیبان فقط با owner_approved_prune: true.', array( 'status' => 403 ) );
	}
	$res = sa_agent_updraft_prune_set( $nonce );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return rest_ensure_response( $res );
}

/**
 * ثبت مسیرها.
 */
function sa_agent_ops_register_routes() {
	$admin = static function () {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	};
	register_rest_route(
		SA_AGENT_NS,
		'/index-gate',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_index_gate',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_posts' );
			},
		)
	);
	// v2.22.141: ممیزی تصویر شاخص شهرستان‌ها (فقط خواندنی).
	register_rest_route(
		SA_AGENT_NS,
		'/featured-audit',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_featured_audit',
			'permission_callback' => $admin,
		)
	);
	// v2.22.120: گزارش حجم (پشتیبان افتراقی، UpdraftPlus، رسانه) — فقط خواندنی.
	register_rest_route(
		SA_AGENT_NS,
		'/storage',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_storage',
			'permission_callback' => $admin,
		)
	);
	// v2.22.120: نگه‌داری پشتیبان‌ها — پیش‌نمایش پیش‌فرض، حذف فقط با پرچم مالک.
	register_rest_route(
		SA_AGENT_NS,
		'/backups/prune',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_backups_prune',
			'permission_callback' => $admin,
		)
	);
	// v2.22.133: تست واقعی اتصال Drive (فقط خواندنی).
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/drive-test',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_updraft_drive_test',
			'permission_callback' => $admin,
		)
	);
	// v2.22.133: اصلاح زمان‌بندی/نگه‌داری — پیش‌فرض پیش‌نمایش، تغییر با پرچم مالک.
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/settings',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_updraft_settings',
			'permission_callback' => $admin,
		)
	);
	// v2.22.130: خواندن خطوط مرتبط لاگ یک نسخه (فقط خواندنی).
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/log',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_updraft_log',
			'permission_callback' => $admin,
		)
	);
	// v2.22.129: بررسی مستند آپلود نسخه روی فضای ابری (فقط خواندنی).
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/cloud',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_updraft_cloud',
			'permission_callback' => $admin,
		)
	);
	// v2.22.129: حذف فایل محلی فقط پس از تأیید کامل‌بودن نسخهٔ ابری.
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/delete-local',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_updraft_delete_local',
			'permission_callback' => $admin,
		)
	);
	// v2.22.124: حذف فایل یک نسخهٔ قدیمی UpdraftPlus — فقط با پرچم صریح مالک.
	register_rest_route(
		SA_AGENT_NS,
		'/updraft/prune',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_updraft_prune',
			'permission_callback' => $admin,
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/cache-purge',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_cache_purge',
			'permission_callback' => $admin,
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/relations-audit',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_relations_audit',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_posts' );
			},
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/updraft',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => static function ( $r ) {
					return rest_ensure_response( sa_agent_updraft_status( (string) $r->get_param( 'nonce' ) ) );
				},
				'permission_callback' => $admin,
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'sa_agent_rest_updraft_start',
				'permission_callback' => $admin,
			),
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/theme-update',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					return rest_ensure_response(
						array(
							'disk_version' => sa_agent_theme_disk_version(),
							'loaded'       => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '',
							'log'          => array_slice( (array) get_option( 'sa_agent_theme_update_log', array() ), -5 ),
							'fs_method'    => function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : '',
						)
					);
				},
				'permission_callback' => $admin,
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'sa_agent_rest_theme_update',
				'permission_callback' => static function () {
					return is_user_logged_in() && current_user_can( 'manage_options' ) && current_user_can( 'update_themes' );
				},
			),
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/media',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_media_upload',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
	// v2.22.98: واردکردن تصویر «با مجوز» از دامنهٔ مجاز (inc/county-media.php).
	// سندباکس ایجنت به دامنه‌های تصویری دسترسی ندارد، پس خودِ سایت فایل را
	// می‌گیرد؛ مجوز و نام عکاس پیش از ذخیره اجباری است.
	register_rest_route(
		SA_AGENT_NS,
		'/photos',
		array(
			'methods'             => 'GET',
			'callback'            => 'sa_agent_rest_photos_inventory',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' );
			},
		)
	);
	// v2.22.101: پاک‌سازی نسخه‌های اضافیِ «همان مکان» (زباله‌دان، با پرچم مالک و پیش‌نمایش).
	register_rest_route(
		SA_AGENT_NS,
		'/photos/prune',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photos_prune',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'delete_others_posts' );
			},
		)
	);
	register_rest_route(
		SA_AGENT_NS,
		'/photos/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photo_import',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
	// v2.22.115: پروندهٔ ارسالی مالک که روی میزبان ویکی‌مدیا نیست (مثلاً عکس هوایی که
	// خود مالک فرستاده). سندباکس ایجنت فایل را به سایت نمی‌دهد؛ رانر گیت‌هاب بایت‌های
	// پروندهٔ داخل مخزن را base64 می‌فرستد و سایت همان قاعده‌های اعتبار را اجرا می‌کند.
	register_rest_route(
		SA_AGENT_NS,
		'/photos/upload',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photo_upload',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
	// v2.22.116: تبدیل دسته‌ای تصاویر موجود شهرستان‌ها به WebP چهار‌به‌سه زیر ۱۰۰ کیلوبایت.
	register_rest_route(
		SA_AGENT_NS,
		'/photos/optimize',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photos_optimize',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
	// v2.22.116: مدیریت پنهان‌سازی، ترتیب، تصویر اول، جداسازی و زباله‌دان تصاویر شهرستان.
	register_rest_route(
		SA_AGENT_NS,
		'/photos/manage',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photos_manage',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
}
add_action( 'rest_api_init', 'sa_agent_ops_register_routes' );
