<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز شهرها و شهرستان‌های ایلام و بوشهر (بستهٔ یکپارچه)
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: بستهٔ یکپارچهٔ درون‌ریز دو استان: ۱۲ شهرستان ایلام (ایلام، مهران، دهلران، ایوان، آبدانان، دره‌شهر، چرداول، بدره، ملکشاهی، چوار، هلیلان، سیروان) و ۱۲ مقالهٔ شهر/شهرستان بوشهر (بندر بوشهر، برازجان، بندر گناوه، خورموج، بندر کنگان، بندر عسلویه، جم، اهرم، بندر دیر، بندر دیلم، شهرستان بوشهر، شهرستان دشتستان) — متن کامل مقاله، جدول‌ها، پرسش‌های متداول (FAQ)، منابع، فیلدهای مدل داده (sa_city_*، sa_access_*، sa_google_map_url)، اسکیمای City+TouristDestination، سئو رنک‌مث، ارتباط با برگهٔ مادر استان و آپلود تصویر شاخص وب‌پی همراه هر ۲۴ صفحه. همه‌چیز پیش‌نویس می‌ماند؛ هیچ‌چیز منتشر نمی‌شود.
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

define( 'SA_CI_ILAM_BUSHEHR_VERSION', '1.0.0' );
define( 'SA_CI_ILAM_BUSHEHR_VERSION_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-city-importer.php';

/**
 * Register this package's data batches (both provinces) with the shared city importer core.
 */
function sa_ci_ilam_bushehr_register() {
	$core = SA_City_Province_Importer::instance();

	$core->register_batch(
		array(
			'id'          => 'ilam',
			'dir'         => __DIR__ . '/data/ilam',
			'plugin_file' => __FILE__,
			'version'     => SA_CI_ILAM_BUSHEHR_VERSION,
		)
	);

	$core->register_batch(
		array(
			'id'          => 'bushehr',
			'dir'         => __DIR__ . '/data/bushehr',
			'plugin_file' => __FILE__,
			'version'     => SA_CI_ILAM_BUSHEHR_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_ci_ilam_bushehr_register', 20 );
