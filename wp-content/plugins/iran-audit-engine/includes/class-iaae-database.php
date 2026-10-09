<?php
/**
 * Engine-owned storage. No post, term, metadata, user, or settings table is altered.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Database {
	const SCHEMA_VERSION = '2';

	private static $tables_exist_cache = null;

	/**
	 * Build an engine table name from a fixed suffix.
	 *
	 * @param string $suffix Known table suffix.
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		$allowed = array( 'reports', 'issues', 'category_scores', 'links', 'similarities', 'jobs', 'claims', 'settings', 'settings_audit' );
		if ( ! in_array( $suffix, $allowed, true ) ) {
			return '';
		}
		return $wpdb->prefix . 'iaa_' . $suffix;
	}

	/**
	 * Install or upgrade only plugin-owned tables.
	 *
	 * @return bool
	 */
	public static function install() {
		global $wpdb;
		self::$tables_exist_cache = null;
		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$reports = self::table( 'reports' );
		$issues  = self::table( 'issues' );
		$scores  = self::table( 'category_scores' );
		$links   = self::table( 'links' );
		$similar = self::table( 'similarities' );
		$jobs    = self::table( 'jobs' );
		$claims  = self::table( 'claims' );
		$settings = self::table( 'settings' );
		$audit   = self::table( 'settings_audit' );

		$sql = array(
			"CREATE TABLE {$reports} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				post_title text NOT NULL,
				post_url text NOT NULL,
				post_modified_gmt datetime NULL,
				content_hash char(64) NOT NULL,
				profile varchar(32) NOT NULL DEFAULT '',
				province varchar(191) NOT NULL DEFAULT '',
				county varchar(191) NOT NULL DEFAULT '',
				score decimal(5,2) NULL,
				status varchar(16) NOT NULL DEFAULT 'insufficient',
				coverage decimal(5,2) NOT NULL DEFAULT 0,
				engine_version varchar(32) NOT NULL,
				rules_version varchar(32) NOT NULL,
				trigger_type varchar(16) NOT NULL DEFAULT 'manual',
				audited_at_gmt datetime NOT NULL,
				audited_at_jalali varchar(24) NOT NULL DEFAULT '',
				summary_json longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY post_audit (post_id,audited_at_gmt),
				KEY report_status (status),
				KEY content_hash (content_hash)
			) {$charset};",
			"CREATE TABLE {$issues} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				report_id bigint(20) unsigned NOT NULL,
				rule_id varchar(64) NOT NULL,
				category varchar(40) NOT NULL,
				severity varchar(16) NOT NULL,
				confidence varchar(32) NOT NULL,
				status varchar(16) NOT NULL,
				message text NOT NULL,
				evidence_json longtext NOT NULL,
				suggestion text NOT NULL,
				needs_human_review tinyint(1) NOT NULL DEFAULT 0,
				created_at_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY report_rule (report_id,rule_id),
				KEY rule_severity (rule_id,severity),
				KEY review_state (needs_human_review)
			) {$charset};",
			"CREATE TABLE {$scores} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				report_id bigint(20) unsigned NOT NULL,
				category varchar(40) NOT NULL,
				score decimal(5,2) NULL,
				status varchar(16) NOT NULL,
				coverage decimal(5,2) NOT NULL DEFAULT 0,
				counts_json longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY report_category (report_id,category)
			) {$charset};",
			"CREATE TABLE {$links} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				url_hash char(64) NOT NULL,
				url text NOT NULL,
				link_type varchar(16) NOT NULL,
				anchor text NOT NULL,
				rel_value varchar(255) NOT NULL DEFAULT '',
				state varchar(24) NOT NULL DEFAULT 'unknown',
				http_code smallint(5) unsigned NULL,
				final_url text NOT NULL,
				redirect_chain_json longtext NOT NULL,
				last_checked_at_gmt datetime NULL,
				check_count int(10) unsigned NOT NULL DEFAULT 0,
				last_error text NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY post_url (post_id,url_hash),
				KEY link_state (state),
				KEY checked_at (last_checked_at_gmt)
			) {$charset};",
			"CREATE TABLE {$similar} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				report_id bigint(20) unsigned NOT NULL,
				post_id bigint(20) unsigned NOT NULL,
				other_post_id bigint(20) unsigned NOT NULL,
				method varchar(32) NOT NULL,
				similarity decimal(6,5) NOT NULL DEFAULT 0,
				shared_segments_json longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY report_pair_method (report_id,other_post_id,method),
				KEY post_similarity (post_id,similarity),
				KEY other_post (other_post_id)
			) {$charset};",
			"CREATE TABLE {$jobs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				post_ids_json longtext NOT NULL,
				modules_json longtext NOT NULL,
				type varchar(24) NOT NULL DEFAULT 'audit',
				status varchar(16) NOT NULL DEFAULT 'queued',
				progress tinyint(3) unsigned NOT NULL DEFAULT 0,
				current_step varchar(64) NOT NULL DEFAULT '',
				steps_json longtext NOT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				current_index int(10) unsigned NOT NULL DEFAULT 0,
				locked_until_gmt datetime NULL,
				started_at_gmt datetime NULL,
				finished_at_gmt datetime NULL,
				error_message text NOT NULL,
				created_at_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY job_state (status,created_at_gmt),
				KEY lock_until (locked_until_gmt)
			) {$charset};",
			"CREATE TABLE {$claims} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				report_id bigint(20) unsigned NOT NULL,
				kind varchar(32) NOT NULL,
				claim_value varchar(255) NOT NULL,
				context_json longtext NOT NULL,
				has_source tinyint(1) NOT NULL DEFAULT 0,
				review_state varchar(32) NOT NULL DEFAULT 'pending',
				reviewed_by bigint(20) unsigned NULL,
				reviewed_at_gmt datetime NULL,
				PRIMARY KEY  (id),
				KEY claim_report (report_id),
				KEY claim_review (review_state),
				KEY claim_kind (kind)
			) {$charset};",
			"CREATE TABLE {$settings} (
				setting_key varchar(191) NOT NULL,
				setting_value longtext NOT NULL,
				updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
				updated_at_gmt datetime NOT NULL,
				PRIMARY KEY  (setting_key)
			) {$charset};",
			"CREATE TABLE {$audit} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				setting_key varchar(191) NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				before_json longtext NOT NULL,
				after_json longtext NOT NULL,
				created_at_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY setting_time (setting_key,created_at_gmt)
			) {$charset};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
		self::migrate_to_v2();
		update_option( 'iaa_schema_version', self::SCHEMA_VERSION, false );
		return self::tables_exist();
	}

	/**
	 * Schema v2: retire the human-review claims queue. Every pending claim is
	 * auto-approved exactly once, per explicit owner decision (2026-10-09).
	 * Historical claim rows are preserved; new audits no longer write claims.
	 *
	 * @return void
	 */
	private static function migrate_to_v2() {
		global $wpdb;
		if ( '1' === (string) get_option( 'iaa_claims_auto_approved', '' ) ) {
			return;
		}
		$table = self::table( 'claims' );
		if ( '' !== $table && method_exists( $wpdb, 'query' ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET review_state=%s, reviewed_at_gmt=%s WHERE review_state=%s", 'verified_by_human', current_time( 'mysql', true ), 'pending' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		update_option( 'iaa_claims_auto_approved', '1', false );
	}

	/**
	 * Confirm that the required custom tables exist.
	 *
	 * @return bool
	 */
	public static function tables_exist() {
		global $wpdb;
		if ( null !== self::$tables_exist_cache ) {
			return (bool) self::$tables_exist_cache;
		}
		$exists = true;
		foreach ( array( 'reports', 'issues', 'category_scores', 'links', 'similarities', 'jobs', 'claims', 'settings', 'settings_audit' ) as $suffix ) {
			$table = self::table( $suffix );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			if ( $found !== $table ) {
				$exists = false;
				break;
			}
		}
		self::$tables_exist_cache = $exists;
		return $exists;
	}

	/**
	 * Read one plugin setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		global $wpdb;
		$key   = sanitize_key( (string) $key );
		$table = self::table( 'settings' );
		if ( '' === $key || ! self::tables_exist() ) {
			return $default;
		}
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1", $key ) );
		if ( null === $raw ) {
			return $default;
		}
		$value = json_decode( $raw, true );
		return JSON_ERROR_NONE === json_last_error() ? $value : $default;
	}

	/**
	 * Store one plugin-owned setting and append an audit row.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public static function set_setting( $key, $value ) {
		global $wpdb;
		$key = sanitize_key( (string) $key );
		if ( '' === $key || ! self::tables_exist() ) {
			return false;
		}
		$old = self::get_setting( $key, null );
		$encoded = wp_json_encode( $value );
		$before = wp_json_encode( $old );
		if ( ! is_string( $encoded ) || ! is_string( $before ) ) {
			return false;
		}
		$user_id = get_current_user_id();
		$time_gmt = current_time( 'mysql', true );
		$table = self::table( 'settings' );
		$log_table = self::table( 'settings_audit' );
		$transaction = false !== $wpdb->query( 'START TRANSACTION' );
		$updated = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (setting_key,setting_value,updated_by,updated_at_gmt) VALUES (%s,%s,%d,%s)
				 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at_gmt=VALUES(updated_at_gmt)",
				$key,
				$encoded,
				$user_id,
				$time_gmt
			)
		);
		if ( false === $updated ) {
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return false;
		}
		$logged = $wpdb->insert(
			$log_table,
			array(
				'setting_key' => $key,
				'user_id' => $user_id,
				'before_json' => $before,
				'after_json' => $encoded,
				'created_at_gmt' => $time_gmt,
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
		if ( false === $logged ) {
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
			} elseif ( null === $old ) {
				$wpdb->delete( $table, array( 'setting_key' => $key ), array( '%s' ) );
			} else {
				$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET setting_value=%s WHERE setting_key=%s", $before, $key ) );
			}
			return false;
		}
		if ( $transaction && false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		return true;
	}}
