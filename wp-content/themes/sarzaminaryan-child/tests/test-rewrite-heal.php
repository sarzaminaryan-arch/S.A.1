<?php
/**
 * Standalone test: rewrite-rule self-heal (v2.24.23).
 *
 * Outage 2026-11-28: after the owner changed the domain in the host panel,
 * `siteurl`/`home` were correct and `query_var` routes worked —
 * `/?province=tehran` returned «استان تهران؛ راهنمای ۱۶ شهرستان» —
 * but `/province/tehran/` answered 404 for hours. Cause: stale
 * `rewrite_rules`. Fixing it used to mean a manual
 * Settings → Permalinks → Save; this module makes it automatic.
 *
 * Locked rules guarded here:
 *  1. Empty/missing rules ⇒ stale (flush needed).
 *  2. Fingerprint mismatch (theme version, permalink structure, entity bases)
 *     ⇒ stale.
 *  3. An active entity whose archive rule is absent from the stored rules
 *     ⇒ stale, even when the fingerprint matches (belt and braces).
 *  4. Fully matching rules ⇒ not stale: never flush on every request.
 *  5. Heal runs only once and records the fingerprint afterwards.
 *  6. Heal touches nothing while WordPress is installing.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-rewrite-heal.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$theme_dir = dirname( __DIR__ );
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', $theme_dir . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 ); // مثل خود وردپرس
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.24.23' );
}

register_shutdown_function(
	function () {
		$err = error_get_last();
		if ( ! $err ) {
			return;
		}
		$fatal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( ! in_array( $err['type'], $fatal, true ) ) {
			return;
		}
		$msg = str_replace( array( "\n", "\r", '::' ), ' ', $err['message'] );
		if ( getenv( 'GITHUB_ACTIONS' ) ) {
			fwrite( STDOUT, "::error title=rewrite-heal fatal::" . substr( $msg, 0, 700 ) . "\n" );
		}
		fwrite( STDERR, "FATAL: {$err['message']}\n" );
	}
);

function sa_rwh_assert( $condition, $message ) {
	if ( ! $condition ) {
		// در GitHub Actions خطای STDERR فقط در لاگ (که از این شبکه خواندنی نیست)
		// دیده می‌شود؛ annotation از API خواندنی است و همان‌جا می‌آید.
		if ( getenv( 'GITHUB_ACTIONS' ) ) {
			fwrite( STDOUT, "::error title=rewrite-heal::{message}\n" );
		}
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "ok - {$message}\n";
}

$GLOBALS['t'] = array(
	'options'    => array(),
	'entities'   => array(),
	'flushes'    => 0,
	'purges'     => 0,
	'installing' => false,
	'transients' => array(),
);

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['t']['options'] ) ? $GLOBALS['t']['options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	unset( $autoload );
	$GLOBALS['t']['options'][ $name ] = $value;
	return true;
}
function sa_entities() {
	return $GLOBALS['t']['entities'];
}
function flush_rewrite_rules( $hard = true ) {
	unset( $hard );
	$GLOBALS['t']['flushes']++;
	// شبیه‌سازی وردپرس: بازسازی واقعی قاعده‌ها از موجودیت‌های فعال.
	if ( ! empty( $GLOBALS['t']['rebuild_on_flush'] ) ) {
		$rules = array();
		foreach ( $GLOBALS['t']['entities'] as $entity ) {
			if ( empty( $entity['url_base'] ) || ( isset( $entity['status'] ) && 'active' !== $entity['status'] ) ) {
				continue;
			}
			$base                   = $entity['url_base'];
			$rules[ $base . '/?$' ] = 'index.php?post_type=' . $base;
		}
		$GLOBALS['t']['options']['rewrite_rules'] = $rules;
	}
}
function wp_installing() {
	return $GLOBALS['t']['installing'];
}
function get_transient( $name ) {
	return isset( $GLOBALS['t']['transients'][ $name ] ) ? $GLOBALS['t']['transients'][ $name ] : false;
}
function set_transient( $name, $value, $ttl = 0 ) {
	unset( $ttl );
	$GLOBALS['t']['transients'][ $name ] = $value;
	return true;
}
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	unset( $callback, $priority, $args );
	$GLOBALS['t']['hooks'][] = $hook;
	return true;
}
function do_action( $hook ) {
	if ( 'litespeed_purge_all' === $hook ) {
		$GLOBALS['t']['purges']++;
	}
}

require SA_CHILD_DIR . 'inc/rewrite-heal.php';

// سه موجودیت فعال + یکی غیرفعال که نباید قاعده اش لازم شمرده شود.
$GLOBALS['t']['entities'] = array(
	'province'      => array( 'status' => 'active', 'url_base' => 'province' ),
	'city'          => array( 'status' => 'active', 'url_base' => 'city' ),
	'attraction'    => array( 'status' => 'active', 'url_base' => 'attraction' ),
	'accommodation' => array( 'status' => 'reserved', 'url_base' => 'accommodation' ),
);

$good_rules = array(
	// وردپرس کلیدها را بدون «^» ذخیره می‌کند — همان شکل واقعی خودش.
	'province/?$'           => 'index.php?post_type=province',
	'province/([^/]+)/?$'   => 'index.php?province=$matches[1]',
	'city/?$'               => 'index.php?post_type=city',
	'city/([^/]+)/?$'       => 'index.php?city=$matches[1]',
	'attraction/?$'         => 'index.php?post_type=attraction',
	'attraction/([^/]+)/?$' => 'index.php?attraction=$matches[1]',
);

// ---- 1) rules missing ⇒ stale ----
$GLOBALS['t']['options'] = array( 'permalink_structure' => '/%postname%/' );
sa_rwh_assert( true === sa_rewrite_rules_stale(), 'بدون قاعدهٔ ذخیره‌شده، کهنه تشخیص داده می‌شود' );

// ---- 2) fingerprint mismatch ⇒ stale ----
$GLOBALS['t']['options']['rewrite_rules'] = $good_rules;
$GLOBALS['t']['options']['sa_rewrite_fingerprint'] = 'fingerprint-قدیمی';
sa_rwh_assert( true === sa_rewrite_rules_stale(), 'ناسازگاری اثر انگشت ⇒ کهنه' );

// ---- 3) matching fingerprint AND rules ⇒ not stale ----
$GLOBALS['t']['options']['sa_rewrite_fingerprint'] = sa_rewrite_fingerprint();
sa_rwh_assert( false === sa_rewrite_rules_stale(), 'اثر انگشت و قاعده‌های درست ⇒ تازه (flush بی‌دلیل نه)' );

// ---- 4) fingerprint ok but an entity archive rule vanished ⇒ stale ----
$broken = $good_rules;
unset( $broken['city/?$'] );
$GLOBALS['t']['options']['rewrite_rules'] = $broken;
sa_rwh_assert( true === sa_rewrite_rules_stale(), 'قاعدهٔ نبوده گم شود حتی با اثر انگشت درست ⇒ کهنه' );

// ---- 5) heal flushes exactly once, records the fingerprint, purges the cache ----
$GLOBALS['t']['options'] = array(
	'permalink_structure' => '/%postname%/',
	'rewrite_rules'       => $broken,
);
$GLOBALS['t']['flushes']  = 0;
$GLOBALS['t']['purges']   = 0;
$GLOBALS['t']['installing'] = false;
$GLOBALS['t']['transients'] = array();
$GLOBALS['t']['rebuild_on_flush'] = true; // flush واقعاً قاعده‌ها را می‌سازد
sa_heal_rewrite_rules();
sa_rwh_assert( 1 === $GLOBALS['t']['flushes'], 'ترمیم، قاعده‌ها را یک بار بازسازی می‌کند' );
sa_rwh_assert( 1 === $GLOBALS['t']['purges'], 'ترمیم، کش LiteSpeed را هم پاک می‌کند' );
sa_rwh_assert(
	$GLOBALS['t']['options']['sa_rewrite_fingerprint'] === sa_rewrite_fingerprint(),
	'اثر انگشت پس از ترمیم ثبت می‌شود'
);
sa_rwh_assert( false === sa_rewrite_heal_suppressed(), 'ترمیم موفق، خودش را خاموش نمی‌کند' );

// فراخوانی دوم نباید دوباره flush کند.
sa_heal_rewrite_rules();
sa_rwh_assert( 1 === $GLOBALS['t']['flushes'], 'ترمیم دوباره در درخواست بعدی تکرار نمی‌شود' );

// ---- 5b) اگر بازسازی اثر نکند: یک ساعت خاموش می‌شود، نه flush در هر درخواست ----
$GLOBALS['t']['options'] = array(
	'permalink_structure' => '/%postname%/',
	'rewrite_rules'       => $broken,
);
$GLOBALS['t']['flushes']  = 0;
$GLOBALS['t']['purges']   = 0;
$GLOBALS['t']['transients'] = array();
$GLOBALS['t']['rebuild_on_flush'] = false; // فرض: flush اثری ندارد
sa_heal_rewrite_rules();
sa_rwh_assert( 1 === $GLOBALS['t']['flushes'], 'تلاش اول برای ترمیم انجام می‌شود' );
sa_rwh_assert( true === sa_rewrite_heal_suppressed(), 'ترمیم ناموفق، خود را یک ساعت خاموش می‌کند' );
sa_heal_rewrite_rules();
sa_heal_rewrite_rules();
sa_rwh_assert( 1 === $GLOBALS['t']['flushes'], 'پس از خاموشی، درخواست‌های بعدی flush نمی‌کنند (کارایی حفظ می‌شود)' );
sa_rwh_assert( 0 === $GLOBALS['t']['purges'], 'ترمیم ناموفق، کش را بی‌دلیل پاک نمی‌کند' );

// پاک‌کردن transient، ترمیم را دوباره فعال می‌کند.
$GLOBALS['t']['transients'] = array();
$GLOBALS['t']['rebuild_on_flush'] = true;
sa_heal_rewrite_rules();
sa_rwh_assert( 2 === $GLOBALS['t']['flushes'], 'با رفع مانع، ترمیم دوباره اجرا می‌شود' );

// ---- 6) never touch a site that is still installing ----
$GLOBALS['t']['flushes'] = 0;
$GLOBALS['t']['installing'] = true;
$GLOBALS['t']['transients'] = array();
$GLOBALS['t']['options'] = array(); // کهنه
sa_heal_rewrite_rules();
sa_rwh_assert( 0 === $GLOBALS['t']['flushes'], 'در حال نصب، دست به قاعده‌ها نمی‌زنیم' );

// ---- 7) entity without url_base is ignored (no PHP notice, no false stale) ----
$GLOBALS['t']['installing'] = false;
$GLOBALS['t']['entities']['region'] = array( 'status' => 'active' );
$GLOBALS['t']['options'] = array(
	'permalink_structure' => '/%postname%/',
	'rewrite_rules'       => $good_rules,
);
$GLOBALS['t']['options']['sa_rewrite_fingerprint'] = sa_rewrite_fingerprint();
sa_rwh_assert( false === sa_rewrite_rules_stale(), 'موجودیت بدون نامک پایه، قاعده نمی‌خواهد' );

echo "\nهمهٔ آزمون‌های rewrite-heal قبول شدند.\n";
