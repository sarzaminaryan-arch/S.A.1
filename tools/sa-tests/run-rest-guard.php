<?php
/**
 * آزمونِ نگهبانِ REST و گزارشِ تعارضِ نسخه (v2.11.43).
 *
 * پیشینه: گزارشِ خطای سایت یک خطای کشنده نشان می‌داد —
 *   call_user_func(): Argument #1 ($callback) must be a valid callback,
 *   cannot access private method CC_REST::auth()
 * یعنی کدی بیرون از قالب، متدِ «خصوصی» را کالبکِ REST گذاشته بود. این آزمون
 * بررسی می‌کند که (۱) قالب چنین چیزی ندارد و (۲) نگهبان، مسیرِ نامعتبر را
 * پیش از اجرا حذف می‌کند تا سایت کرش نکند و فایلِ مسئول را گزارش می‌دهد.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-rest-guard.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

define( 'SA_CHILD_DIR', '/ws/theme/' );

require '/ws/theme/inc/system-diagnostics.php';

echo "== نگهبانِ مسیرهای REST ==\n\n";

/* ------------------------------------------------- §۱. کالبک‌های نامعتبر */

class SA_Test_Rest_Legacy {
	private static function auth() {
		return true;
	}
	private function secret() {
		return true;
	}
	public static function allowed() {
		return true;
	}
}

$endpoints = array(
	'/cc/v1/cities/(?P<id>\d+)/rating' => array(
		array(
			'methods'             => 'GET',
			'callback'            => array( 'SA_Test_Rest_Legacy', 'allowed' ),
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => 'POST',
			'callback'            => function () {
				return true;
			},
			'permission_callback' => array( 'SA_Test_Rest_Legacy', 'auth' ),
		),
	),
	'/cc/v1/my-submissions'            => array(
		array(
			'methods'             => 'GET',
			'callback'            => array( 'SA_Test_Rest_Legacy', 'secret' ),
			'permission_callback' => '__return_true',
		),
	),
	'/cc/v1/ping'                      => array(
		array(
			'methods'             => 'GET',
			'callback'            => '__return_true',
			'permission_callback' => '__return_true',
		),
	),
	'/sa/v1/no-callback'               => array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
		),
	),
);

sa_eq( 'کالبکِ عمومی معتبر است', true, sa_rest_guard_callable( array( 'SA_Test_Rest_Legacy', 'allowed' ) ) );
sa_eq( 'کالبکِ خصوصی نامعتبر است', false, sa_rest_guard_callable( array( 'SA_Test_Rest_Legacy', 'auth' ) ) );
sa_eq( 'متدِ خصوصیِ نمونه‌ای نامعتبر است', false, sa_rest_guard_callable( array( new SA_Test_Rest_Legacy(), 'secret' ) ) );
sa_eq( 'Closure معتبر است', true, sa_rest_guard_callable( function () {} ) );
sa_eq( 'تابعِ هستهٔ وردپرس معتبر است', true, sa_rest_guard_callable( '__return_true' ) );
sa_eq( 'تابعِ ناموجود نامعتبر است', false, sa_rest_guard_callable( 'sa_this_function_does_not_exist' ) );
sa_eq( 'کالبکِ خالی وقتی اجباری نیست پذیرفته می‌شود', true, sa_rest_guard_callable( null, false ) );
sa_eq( 'کالبکِ خالی وقتی اجباری است رد می‌شود', false, sa_rest_guard_callable( null, true ) );

$filtered = sa_rest_guard_filter( $endpoints );

sa_eq( 'مسیرِ سالم (ping) دست‌نخورده می‌ماند', true, isset( $filtered['/cc/v1/ping'] ) );
sa_eq( 'درخواستِ GET با کالبکِ عمومی می‌ماند', true, isset( $filtered['/cc/v1/cities/(?P<id>\d+)/rating'][0] ) );
sa_eq( 'درخواستِ POST با permission خصوصی حذف می‌شود', false, isset( $filtered['/cc/v1/cities/(?P<id>\d+)/rating'][1] ) );
sa_eq( 'مسیر با کالبکِ خصوصی کاملاً حذف می‌شود', false, isset( $filtered['/cc/v1/my-submissions'] ) );
sa_eq( 'مسیرِ بی‌کالبک هم حذف می‌شود', false, isset( $filtered['/sa/v1/no-callback'] ) );

/* ------------------------------------------------- §۲. گزارش و پیامِ پیشخوان */

$report = get_option( 'sa_rest_guard_last' );
sa_eq( 'گزارشِ نگهبان ذخیره می‌شود', true, is_array( $report ) && ! empty( $report['blocked'] ) );
sa_eq( 'شمارِ مواردِ مسدودشده', 3, is_array( $report ) ? count( $report['blocked'] ) : -1 );

$kinds = array();
$files = array();
foreach ( (array) $report['blocked'] as $row ) {
	$kinds[] = $row['kind'] . '=' . $row['callback'];
	$files[] = $row['file'];
}
sa_eq( 'نامِ متدِ خصوصی در گزارش می‌آید', true, in_array( 'permission_callback=SA_Test_Rest_Legacy::auth', $kinds, true ) );
sa_eq( 'فایلِ مسئول در گزارش می‌آید', true, count( array_filter( $files ) ) >= 2 );
$private_files = array();
foreach ( (array) $report['blocked'] as $row ) {
	if ( 'permission_callback=SA_Test_Rest_Legacy::auth' === $row['kind'] . '=' . $row['callback'] ) {
		$private_files[] = $row['file'];
	}
}
sa_eq( 'فایلِ متدِ خصوصی شناسایی می‌شود', true, isset( $private_files[0] ) && false !== strpos( $private_files[0], '.php' ) );

sa_eq( 'لاگِ ساعتی برای مسیر ثبت می‌شود', 1, (int) get_transient( 'sa_rest_guard_log_' . md5( '/cc/v1/my-submissions|callback|SA_Test_Rest_Legacy::secret' ) ) );

$GLOBALS['sa_caps'] = array( 'manage_options' => true );
ob_start();
sa_system_notices();
$notice = ob_get_clean();
sa_has( 'پیامِ خطا در پیشخوان دیده می‌شود', 'بی‌اثر', $notice );
sa_has( 'پیام، نامِ متدِ مشکل‌دار را می‌گوید', 'SA_Test_Rest_Legacy::auth', $notice );
sa_has( 'پیام، فایلِ مسئول را می‌گوید', '.php', $notice );
sa_has( 'پیام، علت (کالبک نامعتبر) را توضیح می‌دهد', 'کالبک', $notice );

$GLOBALS['sa_caps'] = array();
ob_start();
sa_system_notices();
sa_eq( 'کاربرِ بی‌دسترسی پیام نمی‌بیند', '', ob_get_clean() );

/* ------------------------------------------------- §۳. پاک‌شدن گزارش با رفعِ مشکل */

sa_rest_guard_store( array() );
sa_eq( 'با نبودِ مشکل، گزارش پاک می‌شود', false, get_option( 'sa_rest_guard_last' ) );

// مسیرهای معتبر، دست‌نخورده می‌مانند و چیزی ثبت نمی‌شود.
$clean   = array(
	'/sa/v1/ok' => array(
		array(
			'methods'             => 'GET',
			'callback'            => '__return_true',
			'permission_callback' => function () {
				return true;
			},
		),
	),
);
$kept    = sa_rest_guard_filter( $clean );
sa_eq( 'مسیرِ معتبر پس از فیلتر می‌ماند', 1, count( $kept['/sa/v1/ok'] ) );
sa_eq( 'و گزارشِ نامعتبر ساخته نمی‌شود', false, get_option( 'sa_rest_guard_last' ) );

/* ------------------------------------------------- §۴. تعارضِ نسخهٔ «شهر من» */

$GLOBALS['sa_caps'] = array( 'manage_options' => true );
sa_system_note_conflict(
	'cc-module',
	array(
		'title'  => 'ماژولِ «شهر من» از یک منبعِ دیگر فعال است',
		'file'   => 'wp-content/plugins/old-city-contrib/includes/class-cc-rest.php',
		'extra'  => 'نسخهٔ اعلام‌شده: 1.4.0',
		'advice' => 'همان افزونه را غیرفعال کنید.',
	)
);
ob_start();
sa_system_notices();
$conflict_html = ob_get_clean();
sa_has( 'تعارضِ نسخه در پیشخوان گزارش می‌شود', 'منبعِ دیگر', $conflict_html );
sa_has( 'فایلِ نسخهٔ قدیمی نشان داده می‌شود', 'old-city-contrib', $conflict_html );
sa_has( 'راهنمای رفع در پیام هست', 'غیرفعال', $conflict_html );
delete_option( 'sa_system_conflicts' );

/* ------------------------------------------- §۵. کدِ خودِ قالب: هیچ کالبکِ خصوصی */

$rest_src = (string) file_get_contents( SA_CHILD_DIR . 'inc/city-contrib/includes/class-cc-rest.php' );
$otp_src  = (string) file_get_contents( SA_CHILD_DIR . 'inc/city-contrib/includes/class-cc-otp.php' );
sa_eq( 'فایلِ کلاسِ REST خوانده می‌شود', true, strlen( $rest_src ) > 500 );
sa_eq( 'فایلِ کلاسِ ورود با کد خوانده می‌شود', true, strlen( $otp_src ) > 500 );

sa_eq(
	'کلاسِ REST هیچ متدِ خصوصی ندارد (منشأ خطای «cannot access private method»)',
	0,
	preg_match_all( '/\bprivate\s+(?:static\s+)?function\s+/i', $rest_src )
);

$callbacks = array();
foreach ( array( $rest_src, $otp_src ) as $src ) {
	if ( preg_match_all( "/array\s*\(\s*__CLASS__\s*,\s*'([a-zA-Z0-9_]+)'\s*\)/", $src, $matches ) ) {
		foreach ( $matches[1] as $name ) {
			$callbacks[ $name ] = $src;
		}
	}
}
sa_eq( 'کالبک‌های کلاسیِ قالب پیدا می‌شوند', true, count( $callbacks ) >= 2 );

$not_public = array();
foreach ( $callbacks as $name => $src ) {
	if ( ! preg_match( '/public\s+static\s+function\s+' . preg_quote( $name, '/' ) . '\s*\(/', $src )
		&& ! preg_match( '/public\s+function\s+' . preg_quote( $name, '/' ) . '\s*\(/', $src ) ) {
		$not_public[] = $name;
	}
}
sa_eq( 'همهٔ کالبک‌های REST قالب «عمومی» هستند', array(), $not_public );

// هر permission_callback باید یکی از شکل‌های امن باشد: تابعِ هستهٔ وردپرس، Closure،
// یا متدِ عمومیِ همین کلاس‌ها (همان‌هایی که بالاتر بررسی شدند).
$safe_pattern = "/'permission_callback'\s*=>\s*(?:'(?:__return_true|__return_false)'|function\s*\(|array\(\s*__CLASS__\s*,\s*'[a-zA-Z0-9_]+'\s*\))/";
$total_perms  = 0;
$safe_perms   = 0;
foreach ( array( $rest_src, $otp_src ) as $src ) {
	$src = preg_replace( '/\s+/', ' ', $src );
	$total_perms += (int) substr_count( $src, "'permission_callback'" );
	$safe_perms  += (int) preg_match_all( $safe_pattern, $src );
}
sa_eq( 'همهٔ permission_callback‌ها شکلِ امن دارند', $total_perms, $safe_perms );

sa_done();
