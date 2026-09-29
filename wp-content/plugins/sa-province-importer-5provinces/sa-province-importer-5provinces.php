<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریز ۵ استان (کردستان، کرمانشاه، کهگیلویه و بویراحمد، گلستان، گیلان)
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: درون‌ریز کامل ۵ استان برتر غرب و شمال کشور (کردستان، کرمانشاه، کهگیلویه و بویراحمد، گلستان و گیلان) به همراه ۶۴ شهرستان با نام فارسی و اسلاگ انگلیسی، فیلدهای مدل داده (sa_*)، متن کامل مقاله ۳۰ بخشی، پرسش‌های متداول (FAQ)، منابع، سئو رنک‌مث و تصویر شاخص.
 * Version:     1.0.0
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

define( 'SA_PI_PACK5_VERSION', '1.0.0' );
define( 'SA_PI_PACK5_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-sa-province-importer.php';

/**
 * Register this plugin's data batch with the shared importer core.
 */
function sa_pi_pack5_register() {
	SA_Province_Importer::instance()->register_batch(
		array(
			'id'          => 'pack5',
			'dir'         => __DIR__ . '/data',
			'plugin_file' => __FILE__,
			'version'     => SA_PI_PACK5_VERSION,
		)
	);
}
add_action( 'plugins_loaded', 'sa_pi_pack5_register', 20 );
