<?php
/**
 * Sarzamin Aryan Child — bootstrap.
 *
 * Everything project-specific lives in this child theme (no plugins):
 * entity CPTs from the Master Data Model, taxonomies, meta boxes, publish gate,
 * SEO + JSON-LD, breadcrumbs, Jalali dates, security & performance hardening.
 *
 * @package Sarzaminaryan_Child
 * @author  محمدرضا لک
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_CHILD_VERSION', '2.22.16' );
define( 'SA_CHILD_DIR', trailingslashit( get_stylesheet_directory() ) );
define( 'SA_CHILD_URI', trailingslashit( get_stylesheet_directory_uri() ) );
define( 'SA_MODEL_VERSION', '1.3' );

// Accommodation entity is RESERVED in the data model (v1.0). Flip to true when v1.1 activates it.
if ( ! defined( 'SA_ENABLE_ACCOMMODATION' ) ) {
	define( 'SA_ENABLE_ACCOMMODATION', false );
}

$sa_child_includes = array(
	'inc/entities-config.php',   // generated from data-model.yaml
	'inc/helpers.php',
	'inc/system-diagnostics.php', // نگهبانِ REST (جلوگیری از خطای ۵۰۰ کالبکِ خصوصی) + گزارشِ تعارضِ نسخه
	'inc/setup.php',
	'inc/post-types.php',
	'inc/taxonomies.php',
	'inc/meta-fields.php',
	'inc/relations.php',
	'inc/publish-gate.php',
	'inc/jalali.php',
	'inc/seo.php',
	'inc/citations.php',
	'inc/redirects.php', // تغییر مسیر ۳۰۱ صفحه‌های تکراری (ادغام‌های تأییدشدهٔ مالک)
	'inc/internal-links.php', // لینک‌سازی داخلی خودکارِ یکتا: استان / شهرستان / دیدنی
	'inc/region-map.php',
	'inc/schema.php',
	'inc/breadcrumbs.php',
	'inc/template-tags.php',
	'inc/featured-images.php', // v2.15.0: کلید «نمایش تصویر شاخص» + جای‌گزین تصویرهای گمشدهٔ داخل متن
	'inc/toc.php', // v2.18.17: «فهرست مطالب» ابتدای همهٔ مقاله‌ها (سرفصل‌های بدنه + بخش‌های قالب)
	'inc/education.php', // بخش «آموزش‌ها» (دستهٔ کمپینگ و بقا) کنار استان/شهرستان/دیدنی در منو، صفحهٔ اول و فوتر
	'inc/entity-bars.php', // v2.13.3: فهرست ۳۱ استان + نوارهای مینیمال استان/شهرستان برای آرشیوها (و نقشهٔ هیرو)
	'inc/gallery.php', // گالری آلبومی استان/شهرستان + تبدیل خودکار WebP
	'inc/geo-counties.php',
	'inc/county-profile.php', // شناسنامهٔ شهرستان: کلیدهای استاندارد + کارت county-identity
	'inc/province-profile.php',
	'inc/geo-import.php',
	'inc/security.php',
	'inc/performance.php',
	'inc/customizer.php',
	'inc/admin.php',
	'inc/content-health.php',
	'inc/content-repair.php', // تعمیر مکانیکیِ محتوای منتشرشده (H1 بدنه → H2) — پیش‌نمایش + اعمال با nonce
	'inc/site-cleanup.php', // پاک‌سازی صفحه‌های خالیِ شهرستان + یکسان‌سازی نامِ «دیدنی‌ها» در متن
	'inc/dashboard.php', // داشبورد جامع گردشگری ایران (قالب صفحه‌ی جدا)
	'inc/ready-articles.php', // مقالات آمادهٔ دیدنی‌ها — درج فقط پیش‌نویس + کارت سفید تصویر شاخص
	'inc/media-restore.php', // v2.15.0: گزارش و بازگردانی فایل‌های تصویریِ گمشدهٔ پوشهٔ رسانه (منبع/قالب/پشتیبان)
	'inc/city-contrib.php', // مشارکت مردمی «شهر من» — داخلی قالب، بدون نیاز به افزونه
	'inc/contact-form.php', // فرم تماس داخلی قالب (ارسال به ایمیل تماس سایت)
	'inc/social-publish.php', // انتشار خودکار در تلگرام و اینستاگرام — داخلی قالب، بدون افزونه
	'inc/telegram-bot.php',   // ربات راهنمای تلگرام (استان ← شهر ← دیدنی) — داخلی قالب، بدون افزونه
	'inc/github-updater.php',
	'inc/activation.php',
);

foreach ( $sa_child_includes as $sa_child_file ) {
	require_once SA_CHILD_DIR . $sa_child_file;
}
unset( $sa_child_includes, $sa_child_file );

/**
 * Add the default Phase 1 background texture class to <body>.
 *
 * The variant filter accepts only: dots, grid or lines. The default is dots;
 * a project-specific setting can switch it without touching the CSS:
 *
 * add_filter( 'sa_child_background_variant', function () {
 *     return 'grid';
 * } );
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function sa_child_background_class( $classes ) {
	$classes = array_diff( $classes, array( 'bg-dots', 'bg-grid', 'bg-lines' ) );

	$variant = apply_filters( 'sa_child_background_variant', 'dots' );
	$variant = is_string( $variant ) ? sanitize_key( $variant ) : 'dots';
	$variant = preg_replace( '/^bg-/', '', $variant );

	if ( ! in_array( $variant, array( 'dots', 'grid', 'lines' ), true ) ) {
		$variant = 'dots';
	}

	$classes[] = 'bg-' . $variant;
	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'sa_child_background_class', 20 );

/**
 * Return a cache-busting version for a child-theme asset.
 *
 * @param string $relative_path Path relative to the child-theme directory.
 * @return int|string
 */
function sa_child_asset_version( $relative_path ) {
	$relative_path = ltrim( (string) $relative_path, '/' );
	$asset_path    = SA_CHILD_DIR . $relative_path;
	$asset_mtime   = file_exists( $asset_path ) ? filemtime( $asset_path ) : false;

	return false !== $asset_mtime ? $asset_mtime : SA_CHILD_VERSION;
}

/**
 * Enqueue the child stylesheet after the parent stylesheet and load front-end
 * scripts only where their templates need them.
 */
function sa_child_assets() {
	wp_enqueue_style(
		'sarzaminaryan-child',
		SA_CHILD_URI . 'assets/css/child.css',
		array( 'sarzaminaryan-style' ),
		sa_child_asset_version( 'assets/css/child.css' )
	);
	wp_enqueue_script(
		'sarzaminaryan-child',
		SA_CHILD_URI . 'assets/js/child.js',
		array( 'sarzaminaryan-main' ),
		sa_child_asset_version( 'assets/js/child.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);

	if ( is_front_page() ) {
		wp_enqueue_script(
			'sarzaminaryan-counter-buttons',
			SA_CHILD_URI . 'assets/js/counter-buttons.js',
			array(),
			sa_child_asset_version( 'assets/js/counter-buttons.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	if ( is_singular( array( 'province', 'city' ) ) ) {
		wp_enqueue_style(
			'sarzaminaryan-gallery',
			SA_CHILD_URI . 'assets/css/gallery.css',
			array( 'sarzaminaryan-child' ),
			sa_child_asset_version( 'assets/css/gallery.css' )
		);
		wp_enqueue_script(
			'sarzaminaryan-gallery',
			SA_CHILD_URI . 'assets/js/gallery.js',
			array(),
			sa_child_asset_version( 'assets/js/gallery.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sa_child_assets', 20 );

/**
 * Add the shared profile-page scope only to province, county and attraction
 * single templates. These are CPT singles, so is_singular() is the correct
 * conditional rather than a page-template check.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function sa_child_profile_body_class( $classes ) {
	if ( is_singular( 'province' ) || is_singular( 'city' ) || is_singular( 'attraction' ) ) {
		$classes[] = 'profile-page';
	}

	return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'sa_child_profile_body_class', 20 );
