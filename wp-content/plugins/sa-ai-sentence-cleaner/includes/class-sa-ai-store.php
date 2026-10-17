<?php
/**
 * ذخیره‌سازی افزونه: جدول یافته‌ها و پشتیبان‌ها + تنظیمات در options.
 *
 * پشتیبان قبل از اولین تغییر هر نوشته در هر اجرا گرفته می‌شود و از صفحهٔ
 * «پشتیبان‌ها» قابل بازگردانی است؛ علاوه بر آن wp_update_post خودش revision
 * وردپرس هم می‌سازد.
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_AI_Store {

	public static function findings_table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_ai_findings';
	}

	public static function backups_table() {
		global $wpdb;
		return $wpdb->prefix . 'sa_ai_backups';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$f       = self::findings_table();
		$b       = self::backups_table();
		dbDelta(
			"CREATE TABLE {$f} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id varchar(40) NOT NULL DEFAULT '',
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			pattern_id varchar(100) NOT NULL DEFAULT '',
			level varchar(20) NOT NULL DEFAULT 'FLAG',
			sentence text NOT NULL,
			sentence_key text NOT NULL,
			key_hash char(64) NOT NULL DEFAULT '',
			action varchar(40) NOT NULL DEFAULT 'pending',
			is_duplicate tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_post (scan_id, post_id),
			KEY key_hash (key_hash)
		) {$charset};"
		);
		dbDelta(
			"CREATE TABLE {$b} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			scan_id varchar(40) NOT NULL DEFAULT '',
			content_before longtext NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			restored_at datetime NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id)
		) {$charset};"
		);
		if ( false === get_option( 'sa_ai_cleaner_settings' ) ) {
			add_option( 'sa_ai_cleaner_settings', self::default_settings(), '', false );
		}
	}

	public static function default_settings() {
		return array(
			'batch_size'        => 20,
			'dup_min_posts'     => 4,
			'dup_min_length'    => 20,
			'manual_percent'    => 20,
			'post_types'        => array( 'city' ),
			'apply_sources'     => 1,
		);
	}

	public static function settings() {
		$saved = get_option( 'sa_ai_cleaner_settings', array() );
		return array_merge( self::default_settings(), is_array( $saved ) ? $saved : array() );
	}

	public static function save_settings( $settings ) {
		update_option( 'sa_ai_cleaner_settings', $settings, false );
	}

	public static function patterns() {
		$saved = get_option( 'sa_ai_cleaner_patterns', null );
		if ( is_array( $saved ) && $saved ) {
			$out = array();
			foreach ( $saved as $p ) {
				if ( empty( $p['id'] ) || empty( $p['level'] ) ) {
					continue;
				}
				$out[] = array(
					'id'          => sanitize_text_field( $p['id'] ),
					'level'       => 'DELETE' === $p['level'] ? 'DELETE' : 'FLAG',
					'regex'       => isset( $p['regex'] ) ? (string) $p['regex'] : '',
					'description' => isset( $p['description'] ) ? (string) $p['description'] : '',
				);
			}
			if ( $out ) {
				return $out;
			}
		}
		return sa_ai_default_patterns();
	}

	public static function save_patterns( $patterns ) {
		update_option( 'sa_ai_cleaner_patterns', $patterns, false );
	}

	public static function reset_patterns() {
		delete_option( 'sa_ai_cleaner_patterns' );
	}

	/* ---------------- اسکن و یافته‌ها ---------------- */

	public static function clear_scan_findings( $scan_id ) {
		global $wpdb;
		$wpdb->delete( self::findings_table(), array( 'scan_id' => $scan_id ), array( '%s' ) );
	}

	public static function save_post_findings( $scan_id, $post_id, $findings ) {
		global $wpdb;
		$table = self::findings_table();
		$wpdb->delete( $table, array( 'scan_id' => $scan_id, 'post_id' => (int) $post_id ), array( '%s', '%d' ) );
		foreach ( (array) $findings as $f ) {
			$wpdb->insert(
				$table,
				array(
					'scan_id'      => $scan_id,
					'post_id'      => (int) $post_id,
					'pattern_id'   => isset( $f['pattern_id'] ) ? $f['pattern_id'] : '',
					'level'        => isset( $f['level'] ) ? $f['level'] : 'FLAG',
					'sentence'     => isset( $f['sentence'] ) ? $f['sentence'] : '',
					'sentence_key' => isset( $f['sentence_key'] ) ? $f['sentence_key'] : '',
					'key_hash'     => isset( $f['key_hash'] ) ? $f['key_hash'] : '',
					'action'       => 'flagged' === ( isset( $f['action'] ) ? $f['action'] : '' ) ? 'flagged' : ( isset( $f['action'] ) ? $f['action'] : 'pending' ),
					'is_duplicate' => ! empty( $f['is_duplicate'] ) ? 1 : 0,
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
		}
	}

	public static function summary( $scan_id ) {
		global $wpdb;
		$table = self::findings_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(level='DELETE') AS deletes, SUM(level='FLAG') AS flags, SUM(is_duplicate=1) AS dups, COUNT(DISTINCT post_id) AS posts FROM {$table} WHERE scan_id=%s", $scan_id ), ARRAY_A );
		return is_array( $row ) ? $row : array( 'total' => 0, 'deletes' => 0, 'flags' => 0, 'dups' => 0, 'posts' => 0 );
	}

	public static function findings( $scan_id, $level = '', $page = 1, $per_page = 50 ) {
		global $wpdb;
		$table = self::findings_table();
		$where = $wpdb->prepare( 'scan_id=%s', $scan_id );
		if ( 'DELETE' === $level || 'FLAG' === $level ) {
			$where .= $wpdb->prepare( ' AND level=%s', $level );
		}
		$offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY post_id ASC, id ASC LIMIT %d OFFSET %d", (int) $per_page, $offset ), ARRAY_A );
	}

	public static function findings_count( $scan_id, $level = '' ) {
		global $wpdb;
		$table = self::findings_table();
		$where = $wpdb->prepare( 'scan_id=%s', $scan_id );
		if ( 'DELETE' === $level || 'FLAG' === $level ) {
			$where .= $wpdb->prepare( ' AND level=%s', $level );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
	}

	public static function findings_by_ids( $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$table        = self::findings_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ({$placeholders})", ...$ids ), ARRAY_A );
	}

	public static function posts_with_pending_deletes( $scan_id ) {
		global $wpdb;
		$table = self::findings_table();
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$table} WHERE scan_id=%s AND level='DELETE' AND action IN ('pending','would-delete') ORDER BY post_id ASC", $scan_id ) );
		return array_map( 'intval', (array) $rows );
	}

	public static function mark_post_findings( $scan_id, $post_id, $status, $hashes = array(), $only_delete = false ) {
		global $wpdb;
		$table = self::findings_table();
		if ( $hashes ) {
			foreach ( $hashes as $hash ) {
				$wpdb->update( $table, array( 'action' => $status ), array( 'scan_id' => $scan_id, 'post_id' => (int) $post_id, 'key_hash' => $hash ), array( '%s' ), array( '%s', '%d', '%s' ) );
			}
			return;
		}
		$where = array( 'scan_id' => $scan_id, 'post_id' => (int) $post_id );
		if ( $only_delete ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET action=%s WHERE scan_id=%s AND post_id=%d AND level='DELETE'", $status, $scan_id, (int) $post_id ) );
			return;
		}
		$wpdb->update( $table, array( 'action' => $status ), $where, array( '%s' ), array( '%s', '%d' ) );
	}

	/* ---------------- پشتیبان و گزارش اجرا ---------------- */

	public static function create_backup( $post_id, $scan_id, $content_before ) {
		global $wpdb;
		$wpdb->insert(
			self::backups_table(),
			array(
				'post_id'        => (int) $post_id,
				'scan_id'        => $scan_id,
				'content_before' => $content_before,
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function backups( $limit = 50 ) {
		global $wpdb;
		$table = self::backups_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, post_id, scan_id, created_by, created_at, restored_at FROM {$table} ORDER BY id DESC LIMIT %d", (int) $limit ), ARRAY_A );
	}

	public static function backup( $id ) {
		global $wpdb;
		$table = self::backups_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", (int) $id ), ARRAY_A );
	}

	public static function mark_restored( $id ) {
		global $wpdb;
		$wpdb->update( self::backups_table(), array( 'restored_at' => current_time( 'mysql' ) ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
	}

	public static function add_log( $message, $data = array() ) {
		$logs   = get_option( 'sa_ai_cleaner_logs', array() );
		$logs   = is_array( $logs ) ? $logs : array();
		array_unshift(
			$logs,
			array(
				'time'    => current_time( 'mysql' ),
				'user'    => get_current_user_id(),
				'message' => $message,
				'data'    => $data,
			)
		);
		update_option( 'sa_ai_cleaner_logs', array_slice( $logs, 0, 50 ), false );
	}

	public static function logs() {
		$logs = get_option( 'sa_ai_cleaner_logs', array() );
		return is_array( $logs ) ? $logs : array();
	}

	public static function scan_state() {
		$s = get_option( 'sa_ai_cleaner_scan', array() );
		return is_array( $s ) ? $s : array();
	}

	public static function save_scan_state( $state ) {
		update_option( 'sa_ai_cleaner_scan', $state, false );
	}

	public static function duplicate_map() {
		$m = get_option( 'sa_ai_cleaner_dup_map', array() );
		return is_array( $m ) ? $m : array();
	}

	public static function save_duplicate_map( $map ) {
		update_option( 'sa_ai_cleaner_dup_map', $map, false );
	}
}
