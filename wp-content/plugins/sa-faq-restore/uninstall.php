<?php
/**
 * Cleanup for the one-time FAQ restore plugin.
 *
 * @package SA_FAQ_Restore
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sa_faq_restore_20261111_backup' );
delete_option( 'sa_faq_restore_20261111_report' );
delete_option( 'sa_faq_restore_20261111_rollback_report' );
