<?php
/** Remove only this plugin's own audit-baseline metadata on explicit uninstall. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_post_meta_by_key( '_sa_audit_score' );
delete_post_meta_by_key( '_sa_audit_checked' );
delete_post_meta_by_key( '_sa_audit_previous_score' );
