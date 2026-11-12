<?php
/**
 * Cleanup for the one-time SEO metadata fix plugin.
 *
 * @package SA_SEO_Meta_Fix
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sa_seo_meta_fix_20261111_backup' );
delete_option( 'sa_seo_meta_fix_20261111_report' );
delete_option( 'sa_seo_meta_fix_20261111_rollback_report' );
