<?php
/**
 * Plugin Name:       ممیزی مقالات زنده — تطبیق با مخزن (سرزمین آریان)
 * Plugin URI:        https://github.com/sarzaminaryan-arch/S.A.1
 * Update URI:        https://github.com/sarzaminaryan-arch/S.A.1
 * Description:       استخراج دقیق مقاله‌های منتشرشدهٔ سایت زنده (عنوان، متن و هش، متاها، FAQ، طبقه‌بندی‌ها، تصویر شاخص، روابط) و تطبیق فیلدبه‌فیلد با خط مبنای مخزن S.A.1 — کاملاً فقط‌خواندنی: دانلود اسنپ‌شات JSON/CSV و گزارش «یکسان / اختلاف / فقط در سایت / فقط در مخزن». هیچ نوشته‌ای تغییر نمی‌کند و هیچ مسیر REST عمومی ندارد.
 * Version:           1.1.1
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            سرزمین آریان
 * Author URI:        https://sarzaminaryan.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sa-live-article-audit
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_LAA_VERSION', '1.1.1' );
define( 'SA_LAA_FILE', __FILE__ );
define( 'SA_LAA_DIR', plugin_dir_path( __FILE__ ) );
define( 'SA_LAA_URL', plugin_dir_url( __FILE__ ) );

require_once SA_LAA_DIR . 'includes/class-sa-laa-compare.php';
require_once SA_LAA_DIR . 'includes/class-sa-laa-baseline.php';
require_once SA_LAA_DIR . 'includes/class-sa-laa-extractor.php';
require_once SA_LAA_DIR . 'includes/class-sa-laa-export.php';
require_once SA_LAA_DIR . 'includes/class-sa-laa-admin.php';

/**
 * فعال‌سازی: فقط ثبت نسخه؛ هیچ جدول یا داده‌ای ساخته نمی‌شود (ابزار فقط‌خواندنی است).
 */
function sa_laa_activate() {
	update_option( 'sa_laa_version', SA_LAA_VERSION, false );
}
register_activation_hook( __FILE__, 'sa_laa_activate' );

/**
 * هم‌گام‌سازی نسخهٔ ذخیره‌شده پس از به‌روزرسانی دستی فایل‌ها.
 */
function sa_laa_maybe_upgrade() {
	if ( SA_LAA_VERSION !== get_option( 'sa_laa_version' ) ) {
		update_option( 'sa_laa_version', SA_LAA_VERSION, false );
	}
}
add_action( 'plugins_loaded', 'sa_laa_maybe_upgrade' );

if ( is_admin() ) {
	SA_LAA_Admin::init();
}
