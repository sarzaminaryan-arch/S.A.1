<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز شهرستان‌های استان چهارمحال و بختیاری
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: درون‌ریز شهرستان‌های استان چهارمحال و بختیاری — هر ۱۰ شهرستان دستور کار (شهرکرد، بروجن، بن، سامان، فارسان، کوهرنگ، اردل، کیار، لردگان، خانمیرزا؛ فرخ‌شهر و فلارد در دستور کار نیستند) — متن کامل مقاله، جدول‌ها، پرسش‌های متداول (FAQ)، منابع، فیلدهای مدل داده (sa_city_*، sa_access_*، sa_google_map_url)، اسکیمای City+TouristDestination، سئو رنک‌مث، ارتباط با برگهٔ مادر استان و آپلود تصویر شاخص وب‌پی همراه صفحه (۱۰ از ۱۰ شهرستان تصویر دارند). همه‌چیز پیش‌نویس می‌ماند؛ هیچ‌چیز منتشر نمی‌شود.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      سرزمین آریان
 * Author URI:  https://sarzaminaryan.ir
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sa-province-importer
 *
 * @package Sarzaminaryan_City_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_CI_CHB_VERSION', '1.0.0' );
define( 'SA_CI_CHB_VERSION_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-city-importer.php';

/**
 * Register this plugin's data batch with the city importer core.
 */
function sa_ci_chb_register() {
	SA_City_Province_Importer::instance()->register_batch(
		array(
			'id'          => 'chaharmahal-bakhtiari',
			'dir'         => __DIR__ . '/data',
			'plugin_file' => __FILE__,
			'version'     => SA_CI_CHB_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_ci_chb_register', 20 );
