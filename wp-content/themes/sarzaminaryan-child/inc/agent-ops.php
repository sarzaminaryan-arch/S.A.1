<?php
/**
 * v2.22.75 — عملیات نگه‌داری پل ایجنت (sa-agent/v1): دروازهٔ ایندکس، پشتیبان کامل
 * UpdraftPlus و به‌روزرسانی/بازگشت قالب از انتشار گیت‌هاب.
 *
 * روال استاندارد هر به‌روزرسانی (دستور مالک، ۱۵ نوامبر ۲۰۲۶):
 *   ۱) POST /updraft  — پشتیبان کامل UpdraftPlus (پایگاه‌داده + فایل‌ها)؛
 *   ۲) GET  /updraft?nonce=… تا jobstatus = finished و success = 1؛
 *   ۳) POST /theme-update — نصب انتشار تازه با Theme_Upgrader::upgrade() هسته
 *      (temp_backup از وردپرس ۶٫۳: اگر نصب نیمه‌کاره بماند، نسخهٔ قبلی خودکار برمی‌گردد)؛
 *   ۴) probe صفحه‌ها؛ اگر خراب بود POST /theme-update با version نسخهٔ قبلی (بازگشت)،
 *      و برای پایگاه‌داده بازگردانی همان پشتیبان UpdraftPlus.
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
 * version (اختیاری؛ برای بازگشت به نسخهٔ قدیمی‌تر)، allow_without_updraft (فقط اگر
 * UpdraftPlus نصب نیست). بدون پشتیبان کامل موفقِ کمتر از ۶ ساعت، نصب انجام نمی‌شود.
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
	if ( sa_agent_updraft() ) {
		if ( ! sa_agent_updraft_recent_ok( 6 * HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'sa_agent_need_backup', 'اول یک پشتیبان کامل و موفق UpdraftPlus بگیرید (کمتر از ۶ ساعت پیش، با پایگاه‌داده).', array( 'status' => 409 ) );
		}
	} elseif ( empty( $b['allow_without_updraft'] ) ) {
		return new WP_Error( 'sa_agent_need_backup', 'UpdraftPlus فعال نیست؛ برای ادامه allow_without_updraft: true لازم است.', array( 'status' => 409 ) );
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
		'/photos/import',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_agent_rest_photo_import',
			'permission_callback' => static function () {
				return is_user_logged_in() && current_user_can( 'edit_others_posts' ) && current_user_can( 'upload_files' );
			},
		)
	);
}
add_action( 'rest_api_init', 'sa_agent_ops_register_routes' );
