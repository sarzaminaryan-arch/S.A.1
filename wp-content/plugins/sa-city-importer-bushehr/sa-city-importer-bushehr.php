<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز شهرها و شهرستان‌های استان بوشهر
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: درون‌ریز کامل ۱۲ مقالهٔ شهر/شهرستان استان بوشهر (بندر بوشهر، برازجان، بندر گناوه، خورموج، بندر کنگان، بندر عسلویه، جم، اهرم، بندر دیر، بندر دیلم، شهرستان بوشهر، شهرستان دشتستان) — متن کامل مقاله، جدول‌ها، پرسش‌های متداول (FAQ)، منابع، فیلدهای مدل داده (sa_city_*، sa_access_*، sa_google_map_url)، اسکیمای City+TouristDestination، سئو رنک‌مث، ارتباط با برگهٔ مادر استان و آپلود تصویر شاخص وب‌پی همراه هر ۱۲ صفحه. همه‌چیز پیش‌نویس می‌ماند؛ هیچ‌چیز منتشر نمی‌شود.
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

define( 'SA_CI_BUSHEHR_VERSION', '1.0.0' );
define( 'SA_CI_BUSHEHR_VERSION_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-city-importer.php';

/**
 * Register this plugin's data batch with the city importer core.
 */
function sa_ci_bushehr_register() {
	SA_City_Province_Importer::instance()->register_batch(
		array(
			'id'          => 'bushehr',
			'dir'         => __DIR__ . '/data',
			'plugin_file' => __FILE__,
			'version'     => SA_CI_BUSHEHR_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_ci_bushehr_register', 20 );
