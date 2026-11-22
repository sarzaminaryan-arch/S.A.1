<?php
/**
 * حذف افزونه: فقط گزینه‌های خود افزونه (پشتیبان‌ها، گزارش‌ها و وضعیت) پاک می‌شود.
 * هیچ نوشته‌ای دست نمی‌خورد؛ پس از حذف، بازگردانی پشتیبان‌ها دیگر ممکن نیست.
 *
 * @package SA_Editorial_Fix
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

foreach ( array( 'sa_ef_report_last', 'sa_ef_rollback_last' ) as $opt ) {
	delete_option( $opt );
}
$names = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sa\_ef\_backup\_%' OR option_name LIKE 'sa\_ef\_report\_%'"
);
foreach ( $names as $name ) {
	delete_option( $name );
}
