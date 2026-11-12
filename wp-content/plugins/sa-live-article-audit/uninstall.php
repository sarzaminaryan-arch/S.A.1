<?php
/**
 * حذف افزونه: فقط گزینهٔ نسخه و گزارش‌های موقت پاک می‌شود.
 * هیچ نوشته یا دادهٔ سایت دست نمی‌خورد (افزونه از اول هم چیزی نمی‌نوشت).
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sa_laa_version' );

// transientهای گزارش ممیزی (به‌ازای هر مدیر یک کلید).
global $wpdb;
if ( isset( $wpdb ) ) {
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_sa\\_laa\\_report\\_%' OR option_name LIKE '\\_transient\\_timeout\\_sa\\_laa\\_report\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- پاک‌سازی هنگام حذف افزونه.
}
