<?php
/**
 * Plugin Name: Iran Audit Engine
 * Description: موتور مستقلِ ممیزی ساختار و سئوی سایت؛ فقط‌خواندنی نسبت به محتوای WordPress.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: iran-audit-engine
 * Domain Path: /languages
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IAAE_VERSION' ) ) {
	define( 'IAAE_VERSION', '0.1.0' );
}
if ( ! defined( 'IAAE_API_VERSION' ) ) {
	define( 'IAAE_API_VERSION', '1.1' );
}
if ( ! defined( 'IAAE_FILE' ) ) {
	define( 'IAAE_FILE', __FILE__ );
}
if ( ! defined( 'IAAE_PATH' ) ) {
	define( 'IAAE_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'IAAE_URL' ) ) {
	define( 'IAAE_URL', plugin_dir_url( __FILE__ ) );
}

require_once IAAE_PATH . 'includes/class-iaae-database.php';
require_once IAAE_PATH . 'includes/class-iaae-rules.php';
require_once IAAE_PATH . 'includes/class-iaae-profile-mapper.php';
require_once IAAE_PATH . 'includes/class-iaae-evaluators.php';
require_once IAAE_PATH . 'includes/class-iaae-auditor.php';
require_once IAAE_PATH . 'includes/class-iaae-actions.php';
require_once IAAE_PATH . 'includes/class-iaae-jobs.php';
require_once IAAE_PATH . 'includes/class-iaae-rest.php';
require_once IAAE_PATH . 'includes/class-iaae-admin.php';
require_once IAAE_PATH . 'includes/class-iaae-plugin.php';

register_activation_hook( IAAE_FILE, array( 'IAAE_Database', 'install' ) );
register_deactivation_hook( IAAE_FILE, array( 'IAAE_Jobs', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		IAAE_Plugin::init();
	},
	20
);
