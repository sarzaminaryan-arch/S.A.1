<?php
/**
 * حذف افزونه: فقط داده‌های خود افزونه (تنظیمات، اسکن، پشتیبان‌ها و جدول‌ها) پاک می‌شود.
 * هیچ نوشته‌ای دست نمی‌خورد؛ بازگردانی پشتیبان‌ها دیگر ممکن نیست، پس قبل از حذف
 * اگر لازم است از صفحهٔ «پشتیبان‌ها» بازگردانی کنید.
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sa_ai_findings' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'sa_ai_backups' );

foreach ( array( 'sa_ai_cleaner_settings', 'sa_ai_cleaner_patterns', 'sa_ai_cleaner_scan', 'sa_ai_cleaner_dup_map', 'sa_ai_cleaner_logs', 'sa_ai_cleaner_version' ) as $option ) {
	delete_option( $option );
}
