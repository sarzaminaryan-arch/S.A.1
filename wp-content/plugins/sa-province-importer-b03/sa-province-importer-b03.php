<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز استان‌ها (دسته‌ی ۳: استان‌های ۲۱ تا ۳۱)
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: پیش‌نویس استان‌های ۲۱ تا ۳۱ فهرست ثابت (کرمان تا یزد) را در بخش «استان‌ها» می‌سازد: متن کامل مقاله (بلوک‌های گوتنبرگ)، فیلدهای مدل داده (sa_*)، FAQ، منابع، سئو (قالب + Rank Math) و تصویر شاخص با ALT. هیچ نوشته‌ای منتشر نمی‌کند؛ همه‌چیز پیش‌نویس می‌ماند. نیازمند قالب فرزند سرزمین آریان ≥ ۱.۰.۳.
 * Version:     1.0.5
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      سرزمین آریان
 * Author URI:  https://sarzaminaryan.ir
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sa-province-importer
 *
 * @package Sarzaminaryan_Province_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_PI_B03_VERSION', '1.0.4' );
define( 'SA_PI_B03_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-province-importer.php';

/**
 * Register this plugin's data batch with the shared importer core.
 * Several batch plugins (b01, b02, b03) can be active at once: the first one loaded defines the core class,
 * every one of them registers its own data/ directory.
 */
function sa_pi_b03_register() {
	SA_Province_Importer::instance()->register_batch(
		array(
			'id'          => 'b03',
			'dir'         => __DIR__ . '/data',
			'plugin_file' => __FILE__,
			'version'     => SA_PI_B03_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_pi_b03_register', 5 );
