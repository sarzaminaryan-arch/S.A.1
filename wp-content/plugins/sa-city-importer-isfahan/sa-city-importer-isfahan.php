<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز شهرستان‌های استان اصفهان
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: درون‌ریز کامل ۲۹ شهرستان استان اصفهان (اصفهان، کاشان، نطنز، سمیرم، نائین، خور و بیابانک، خمینی‌شهر، نجف‌آباد، لنجان، فلاورجان، شاهین‌شهر و میمه، شهرضا، مبارکه، برخوار، آران و بیدگل، گلپایگان، تیران و کرون، فریدن، اردستان، جرقویه، فریدون‌شهر، دهاقان، خوانسار، چادگان، ورزنه، بوئین میاندشت، کوهپایه، هرند، میمه و وزوان) — متن کامل مقاله، جدول‌ها، پرسش‌های متداول (FAQ)، منابع، فیلدهای مدل داده (sa_city_*، sa_access_*، sa_google_map_url)، اسکیمای City+TouristDestination، سئو رنک‌مث، ارتباط با برگهٔ مادر استان و آپلود تصویر شاخص وب‌پی همراه هر ۲۹ صفحه. همه‌چیز پیش‌نویس می‌ماند؛ هیچ‌چیز منتشر نمی‌شود.
 * Version:     1.0.1
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

define( 'SA_CI_ISFAHAN_VERSION', '1.0.1' );
define( 'SA_CI_ISFAHAN_VERSION_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-city-importer.php';

/**
 * Register this plugin's data batch with the city importer core.
 */
function sa_ci_isfahan_register() {
	SA_City_Province_Importer::instance()->register_batch(
		array(
			'id'          => 'isfahan',
			'dir'         => __DIR__ . '/data',
			'plugin_file' => __FILE__,
			'version'     => SA_CI_ISFAHAN_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_ci_isfahan_register', 20 );
