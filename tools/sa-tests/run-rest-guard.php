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

/* --------------------- §۴.۱ گزارشِ قالبِ قدیمی (۲.۱۱.۴۳) نمایش داده نمی‌شود */

// گزارشِ نسخهٔ ۲.۱۱.۴۳ «فرمت» نداشت و گزینه‌های مسیرِ هسته را هم بی‌اثر شمرده بود؛
// چنین گزارشی باید یک‌بار برای همیشه کنار گذاشته شود تا پیامِ کاذب در پیشخوان نماند.
delete_option( 'sa_system_conflicts' );
update_option(
	'sa_rest_guard_last',
	array(
		'time'    => time() - 60,
		'blocked' => array(
			array(
				'route'    => '/wp/v2/posts',
				'method'   => '',
				'kind'     => 'callback',
				'callback' => 'NULL',
				'file'     => '',
			),
		),
	),
	false
);
$GLOBALS['sa_caps'] = array( 'manage_options' => true );
ob_start();
sa_system_notices();
$stale_html = ob_get_clean();
sa_eq( 'گزارشِ قالبِ قدیمی (۲.۱۱.۴۳) نمایش داده نمی‌شود', '', $stale_html );
sa_eq( 'و خودش کنار گذاشته می‌شود', false, get_option( 'sa_rest_guard_last' ) );

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

/* ------------- §۶. ساختارِ واقعیِ هستهٔ وردپرس (۷.۱.x): گزینهٔ مسیر ≠ هندلر */

/*
 * فیلتر `rest_endpoints` آرایهٔ «ثبت‌شده» را می‌بیند، نه نرمال‌شده. در آن آرایه
 * هر مسیر دو نوع کلید دارد:
 *   - کلیدهای عددی: هندلرها (همان‌هایی که `callback` دارند)؛
 *   - کلیدهای غیرعددی: گزینه‌های مسیر مثل `allow_batch` و `schema`.
 * دو مسیرِ `/` و `/batch/v1` را خودِ WP_REST_Server در سازنده با شکلِ «تک‌هندلر»
 * ثبت می‌کند (کلیدِ `callback` در سطحِ بالا). نگهبانِ ۲.۱۱.۴۳ این گزینه‌ها را
 * «هندلرِ بی‌کالبک» می‌شمرد و ۱۴۳ هشدارِ کاذب می‌ساخت؛ این بخش همان اشکال را
 * قفل می‌کند تا برنگردد.
 */

class SA_Test_Core_Controller {
	public function get_items() {}
	public function create_item() {}
	public function get_items_permissions_check() {}
	public function create_item_permissions_check() {}
	public function get_public_item_schema() {}
	public function get_index() {}
	public function serve_batch_request_v1() {}
}

$core = new SA_Test_Core_Controller();

$core_endpoints = array(
	// شکلِ سازندهٔ WP_REST_Server.
	'/'         => array(
		'callback' => array( $core, 'get_index' ),
		'methods'  => 'GET',
		'args'     => array( 'context' => array( 'default' => 'view' ) ),
	),
	'/batch/v1' => array(
		'callback' => array( $core, 'serve_batch_request_v1' ),
		'methods'  => 'POST',
		'args'     => array( 'requests' => array( 'required' => true ) ),
	),
	// شکلِ WP_REST_Posts_Controller::register_routes().
	'/wp/v2/posts' => array(
		array(
			'methods'             => 'GET',
			'callback'            => array( $core, 'get_items' ),
			'permission_callback' => array( $core, 'get_items_permissions_check' ),
			'args'                => array( 'per_page' => array( 'default' => 10 ) ),
		),
		array(
			'methods'             => 'POST',
			'callback'            => array( $core, 'create_item' ),
			'permission_callback' => array( $core, 'create_item_permissions_check' ),
		),
		'allow_batch' => array( 'v1' => true ),
		'schema'      => array( $core, 'get_public_item_schema' ),
	),
	// شکلِ WP_REST_Search_Controller (بدونِ allow_batch).
	'/wp/v2/search' => array(
		array(
			'methods'             => 'GET',
			'callback'            => array( $core, 'get_items' ),
			'permission_callback' => '__return_true',
		),
		'schema' => array( $core, 'get_public_item_schema' ),
	),
);

$core_out = sa_rest_guard_filter( $core_endpoints );

sa_eq( 'مسیرِ متای / هسته: callback دست‌نخورده', true, isset( $core_out['/']['callback'] ) );
sa_eq( 'مسیرِ متای / هسته: args دست‌نخورده', true, isset( $core_out['/']['args'] ) );
sa_eq( 'مسیرِ متای /batch/v1 هسته: callback دست‌نخورده', true, isset( $core_out['/batch/v1']['callback'] ) );
sa_eq( 'مسیرِ متای /batch/v1 هسته: args دست‌نخورده', true, isset( $core_out['/batch/v1']['args'] ) );
sa_eq( 'گزینهٔ مسیرِ allow_batch حفظ می‌شود', true, isset( $core_out['/wp/v2/posts']['allow_batch'] ) );
sa_eq( 'گزینهٔ مسیرِ schema حفظ می‌شود', true, isset( $core_out['/wp/v2/posts']['schema'] ) );
sa_eq( 'گزینهٔ مسیرِ schema در search حفظ می‌شود', true, isset( $core_out['/wp/v2/search']['schema'] ) );
sa_eq( 'هندلرهای GET/POST نوشته‌ها دست‌نخورده می‌مانند', 2, count( array_filter( array_keys( $core_out['/wp/v2/posts'] ), 'is_numeric' ) ) );
sa_eq( 'ساختارِ سالمِ هسته هیچ گزارشِ کاذبی نمی‌سازد', false, get_option( 'sa_rest_guard_last' ) );

// مسیرِ هسته‌مانند که فقط هندلرِ POST آن خراب است: باید فقط همان هندلر برود و
// گزینه‌های مسیر و هندلرِ سالم بمانند.
$half_broken = array(
	'/wp/v2/posts' => array(
		array(
			'methods'             => 'GET',
			'callback'            => array( $core, 'get_items' ),
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => 'POST',
			'callback'            => array( $core, 'create_item' ),
			'permission_callback' => array( 'SA_Test_Rest_Legacy', 'auth' ),
		),
		'allow_batch' => array( 'v1' => true ),
		'schema'      => array( $core, 'get_public_item_schema' ),
	),
);

$half_out = sa_rest_guard_filter( $half_broken );
$half     = get_option( 'sa_rest_guard_last' );

sa_eq( 'هندلرِ سالمِ GET می‌ماند', true, isset( $half_out['/wp/v2/posts'][0]['callback'] ) );
sa_eq( 'هندلرِ خرابِ POST حذف می‌شود', false, isset( $half_out['/wp/v2/posts'][1] ) );
sa_eq( 'گزینهٔ allow_batch پس از حذفِ هندلرِ خراب می‌ماند', true, isset( $half_out['/wp/v2/posts']['allow_batch'] ) );
sa_eq( 'گزینهٔ schema پس از حذفِ هندلرِ خراب می‌ماند', true, isset( $half_out['/wp/v2/posts']['schema'] ) );
sa_eq( 'برای هندلرِ خراب یک ردیف گزارش می‌شود', 1, is_array( $half ) ? count( $half['blocked'] ) : -1 );
sa_eq( 'گزارش، متدِ خراب را می‌گوید', 'POST', is_array( $half ) ? $half['blocked'][0]['method'] : '' );
sa_eq( 'گزارش، نامِ کالبکِ خصوصی را می‌گوید', 'SA_Test_Rest_Legacy::auth', is_array( $half ) ? $half['blocked'][0]['callback'] : '' );
sa_eq( 'گزارش با «فرمت» جاری ذخیره می‌شود', SA_REST_GUARD_REPORT_FORMAT, is_array( $half ) ? (int) $half['format'] : -1 );

// مسیرِ تک‌هندلر (شکلِ `/`) که کالبکش خصوصی است: کلِ مسیر باید بی‌اثر شود.
$single_broken = array(
	'/sa/v1/single' => array(
		'callback' => array( 'SA_Test_Rest_Legacy', 'auth' ),
		'methods'  => 'GET',
		'args'     => array( 'context' => array( 'default' => 'view' ) ),
	),
);

$single_out = sa_rest_guard_filter( $single_broken );
$single     = get_option( 'sa_rest_guard_last' );

sa_eq( 'مسیرِ تک‌هندلر با کالبکِ خصوصی کاملاً حذف می‌شود', false, isset( $single_out['/sa/v1/single'] ) );
sa_eq( 'و در گزارش می‌آید', 1, is_array( $single ) ? count( $single['blocked'] ) : -1 );
sa_eq( 'گزارش، متدِ همان مسیر را می‌گوید', 'GET', is_array( $single ) ? $single['blocked'][0]['method'] : '' );

sa_done();
