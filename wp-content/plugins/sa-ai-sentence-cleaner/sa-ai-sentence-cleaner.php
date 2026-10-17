<?php
/**
 * Plugin Name:       پاک‌سازی جمله‌های هوش مصنوعی (سرزمین آریان)
 * Plugin URI:        https://github.com/sarzaminaryan-arch/S.A.1
 * Description:       اسکن مقاله‌های منتشرشده، پیدا کردن جمله‌های پیش‌نویس و ردپای هوش مصنوعی و جمله‌های تکراری بین مقاله‌ها، و حذف کاملِ جمله‌های تأییدشده از همهٔ مقاله‌ها — فقط جمله، با پشتیبان و بازگردانی. هیچ نوشته، تیتر، جدول، تصویر، لینک یا منبع معتبری حذف نمی‌شود.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            سرزمین آریان
 * Author URI:        https://sarzaminaryan.ir
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sa-ai-sentence-cleaner
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_AI_CLEANER_VERSION', '1.0.1' );
define( 'SA_AI_CLEANER_FILE', __FILE__ );
define( 'SA_AI_CLEANER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SA_AI_CLEANER_URL', plugin_dir_url( __FILE__ ) );

require_once SA_AI_CLEANER_DIR . 'includes/patterns.php';
require_once SA_AI_CLEANER_DIR . 'includes/class-sa-ai-engine.php';
require_once SA_AI_CLEANER_DIR . 'includes/class-sa-ai-store.php';
require_once SA_AI_CLEANER_DIR . 'includes/class-sa-ai-admin.php';

/**
 * ساخت جدول‌های یافته‌ها و پشتیبان‌ها هنگام فعال‌سازی.
 */
function sa_ai_cleaner_activate() {
	SA_AI_Store::install();
}
register_activation_hook( __FILE__, 'sa_ai_cleaner_activate' );

/**
 * اگر نسخه عوض شد و فعال‌سازی دوباره زده نشد، جدول‌ها را بساز.
 */
function sa_ai_cleaner_maybe_install() {
	if ( SA_AI_CLEANER_VERSION !== get_option( 'sa_ai_cleaner_version' ) ) {
		SA_AI_Store::install();
		update_option( 'sa_ai_cleaner_version', SA_AI_CLEANER_VERSION, false );
	}
}
add_action( 'plugins_loaded', 'sa_ai_cleaner_maybe_install' );

if ( is_admin() ) {
	SA_AI_Admin::init();
}
