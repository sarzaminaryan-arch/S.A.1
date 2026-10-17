<?php
/**
 * Plugin Name: تصاویر شاخص ۳۱ استان سرزمین آریان
 * Description: جایگزینی کنترل‌شده و قابل بازگردانی تصاویر استان‌ها؛ نمایش فقط در مقالهٔ استان و اشتراک‌گذاری.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: Sarzamin Aryan
 * License: GPL-2.0-or-later
 * Text Domain: sa-province-covers
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'SA_PC_VERSION', '1.0.0' );
define( 'SA_PC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SA_PC_URL', plugin_dir_url( __FILE__ ) );
require_once SA_PC_DIR . 'includes/catalog.php';
require_once SA_PC_DIR . 'includes/class-sa-pc-store.php';
require_once SA_PC_DIR . 'includes/class-sa-pc-public.php';
require_once SA_PC_DIR . 'includes/class-sa-pc-admin.php';
SA_PC_Public::register();
SA_PC_Admin::register();
// Activation/deactivation never imports, deletes media or changes the theme's global switch.
