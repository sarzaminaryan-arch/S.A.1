<?php
/**
 * Plugin Name: Iran Audit Dashboard
 * Description: داشبورد مستقلِ گزارش‌های ممیزی؛ همهٔ داده‌ها را فقط از REST موتور می‌خواند.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: iran-audit-dashboard
 * Domain Path: /languages
 *
 * @package IranAuditDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IAAD_VERSION' ) ) {
	define( 'IAAD_VERSION', '0.2.0' );
}
if ( ! defined( 'IAAD_FILE' ) ) {
	define( 'IAAD_FILE', __FILE__ );
}
if ( ! defined( 'IAAD_PATH' ) ) {
	define( 'IAAD_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'IAAD_URL' ) ) {
	define( 'IAAD_URL', plugin_dir_url( __FILE__ ) );
}

require_once IAAD_PATH . 'includes/class-iaad-dashboard.php';
add_action( 'plugins_loaded', array( 'IAAD_Dashboard', 'init' ), 20 );
