<?php
/**
 * Cooperative, one-post-per-tick job queue; only Engine-owned tables are mutated.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Jobs {
	const HOOK = 'iaae_process_next_job';
	const RECOVERY_HOOK = 'iaae_recover_stale_jobs';
	const LEASE_SECONDS = 300;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'process_next' ) );
		add_action( self::RECOVERY_HOOK, array( __CLASS__, 'recover' ) );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::RECOVERY_HOOK );
	}

	/**
	 * Enqueue one or more posts. Active jobs are idempotently reused per post.
	 *
	 * @param array $post_ids Post IDs.
	 * @param array $modules Optional category allowlist.
	 * @param int   $created_by User ID.
	 * @return array|WP_Error
	 */
	public static function enqueue( $post_ids, $modules = array(), $created_by = 0 ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $post_ids ) ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'iaa_invalid_param', 'فهرست شناسهٔ نوشته‌ها خالی یا نامعتبر است.', array( 'status' => 400 ) );
		}
		if ( count( $ids ) > 100 ) {
			return new WP_Error( 'iaa_invalid_param', 'هر درخواست صف حداکثر ۱۰۰ نوشته می‌پذیرد.', array( 'status' => 400 ) );
		}
		if ( ! IAAE_Database::tables_exist() ) {
			return new WP_Error( 'iaa_engine_error', 'جدول صف موتور نصب نشده است.', array( 'status' => 500 ) );
		}
		$clean_modules = self::normalize_modules( $modules );
		if ( is_wp_error( $clean_modules ) ) {
			return $clean_modules;
		}
		$created_by = absint( $created_by );
		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );
			$type = $post instanceof WP_Post ? get_post_type_object( $post->post_type ) : null;
			if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) || ! $type || empty( $type->public ) ) {
				return new WP_Error( 'iaa_not_found', 'یکی از نوشته‌های درخواستی پیدا نشد یا در دامنهٔ عمومی ممیزی نیست.', array( 'status' => 404 ) );
			}
			if ( $created_by && ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error( 'iaa_forbidden', 'برای ممیزی یکی از نوشته‌های انتخاب‌شده مجوز ویرایش ندارید.', array( 'status' => 403 ) );
			}
		}

		// Serialize enqueue requests so two concurrent REST calls cannot create duplicate jobs.
		$database_name = isset( $wpdb->dbname ) ? (string) $wpdb->dbname : '';
		$lock_name = 'iaae_enqueue_' . substr( md5( $database_name . ':' . (string) $wpdb->prefix ), 0, 32 );
		$lock = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,5)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		if ( '1' !== (string) $lock ) {
			return new WP_Error( 'iaa_job_conflict', 'صف ممیزی هم‌زمان در حال تغییر است؛ چند لحظهٔ دیگر دوباره تلاش کنید.', array( 'status' => 409 ) );
		}

		try {
			$active_by_post = self::active_jobs_for_posts( $ids );
			$new_ids = array_values( array_diff( $ids, array_keys( $active_by_post ) ) );
			$existing_job_ids = array_values( array_unique( array_map( 'intval', array_values( $active_by_post ) ) ) );
			if ( $existing_job_ids ) {
				if ( ! $new_ids && 1 === count( $existing_job_ids ) ) {
					return self::get( $existing_job_ids[0] );
				}
				return new WP_Error(
					'iaa_job_conflict',
					$new_ids ? 'برای بخشی از نوشته‌ها Job فعال وجود دارد؛ پس از پایان آن Jobها، باقی نوشته‌ها را دوباره به صف بیفزایید.' : 'برای نوشته‌های انتخاب‌شده چند Job فعال جداگانه وجود دارد.',
					array( 'status' => 409, 'job_ids' => array_map( static function ( $id ) { return 'job_' . $id; }, $existing_job_ids ) )
				);
			}

			$table = IAAE_Database::table( 'jobs' );
			$now = current_time( 'mysql', true );
			$steps = array();
			foreach ( $new_ids as $post_id ) {
				$steps[] = array( 'post_id' => (int) $post_id, 'status' => 'queued', 'report_id' => null, 'error' => '' );
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'post_ids_json' => wp_json_encode( $new_ids ),
					'modules_json' => wp_json_encode( $clean_modules ),
					'type' => 'audit',
					'status' => 'queued',
					'progress' => 0,
					'current_step' => 'waiting',
					'steps_json' => wp_json_encode( $steps ),
					'created_by' => $created_by,
					'current_index' => 0,
					'locked_until_gmt' => null,
					'started_at_gmt' => null,
					'finished_at_gmt' => null,
					'error_message' => '',
					'created_at_gmt' => $now,
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				return new WP_Error( 'iaa_engine_error', 'ساخت Job در صف موتور ناموفق بود.', array( 'status' => 500 ) );
			}
			$job_id = (int) $wpdb->insert_id;
			self::schedule();
			self::schedule_recovery();
			return self::get( $job_id );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/** Validate the requested rule categories without silently widening to a full audit. */
	private static function normalize_modules( $modules ) {
		if ( ! is_array( $modules ) ) {
			return new WP_Error( 'iaa_invalid_param', 'modules باید آرایه‌ای از دسته‌های معتبر باشد.', array( 'status' => 400 ) );
		}
		$data = IAAE_Rules::all();
		$valid = isset( $data['category_weights'] ) ? array_keys( $data['category_weights'] ) : array();
		$clean = array();
		foreach ( $modules as $module ) {
			if ( ! is_scalar( $module ) ) {
				return new WP_Error( 'iaa_invalid_param', 'یکی از دسته‌های modules معتبر نیست.', array( 'status' => 400 ) );
			}
			$module = sanitize_key( (string) $module );
			if ( '' === $module || ! in_array( $module, $valid, true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'یکی از دسته‌های modules در rules.json تعریف نشده است.', array( 'status' => 400 ) );
			}
			$clean[] = $module;
		}
		return array_values( array_unique( $clean ) );
	}

	/** Return active jobs keyed by any post ID contained in each job. */
	private static function active_jobs_for_posts( $post_ids ) {
		global $wpdb;
		$table = IAAE_Database::table( 'jobs' );
		$rows = $wpdb->get_results( "SELECT id,post_ids_json FROM {$table} WHERE status IN ('queued','running') ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$wanted = array_fill_keys( array_map( 'intval', $post_ids ), true );
		$active = array();
		foreach ( (array) $rows as $row ) {
			$ids = json_decode( $row['post_ids_json'], true );
			if ( ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( isset( $wanted[ $post_id ] ) && ! isset( $active[ $post_id ] ) ) {
					$active[ $post_id ] = (int) $row['id'];
				}
			}
		}
		return $active;
	}

	private static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 2, self::HOOK );
		}
	}

	private static function schedule_recovery() {
		if ( ! wp_next_scheduled( self::RECOVERY_HOOK ) ) {
			wp_schedule_single_event( time() + self::LEASE_SECONDS + 5, self::RECOVERY_HOOK );
		}
	}

	/** Run one recovery tick; an expired lease is claimed by process_next(). */
	public static function recover() {
		self::process_next();
		if ( self::has_active_jobs() ) {
			self::schedule_recovery();
		}
	}

	private static function has_active_jobs() {
		global $wpdb;
		$table = IAAE_Database::table( 'jobs' );
		return (bool) $wpdb->get_var( "SELECT id FROM {$table} WHERE status IN ('queued','running') LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Process one post from one job per cron invocation. */
	public static function process_next() {
		global $wpdb;
		if ( ! IAAE_Database::tables_exist() ) {
			return;
		}
		$table = IAAE_Database::table( 'jobs' );
		$now = current_time( 'mysql', true );
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status IN ('queued','running') AND (locked_until_gmt IS NULL OR locked_until_gmt < %s) ORDER BY id ASC LIMIT 1", $now ),
			ARRAY_A
		);
		if ( ! is_array( $row ) ) {
			if ( self::has_active_jobs() ) { self::schedule_recovery(); }
			return;
		}

		$lock_until = gmdate( 'Y-m-d H:i:s', time() + self::LEASE_SECONDS );
		$locked = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET locked_until_gmt=%s,status='running',started_at_gmt=IFNULL(started_at_gmt,%s) WHERE id=%d AND status IN ('queued','running') AND (locked_until_gmt IS NULL OR locked_until_gmt < %s)",
				$lock_until,
				$now,
				(int) $row['id'],
				$now
			)
		);
		if ( 1 !== (int) $locked ) {
			self::schedule();
			self::schedule_recovery();
			return;
		}
		self::schedule_recovery();

		$ids = json_decode( $row['post_ids_json'], true );
		$ids = is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
		$steps = json_decode( $row['steps_json'], true );
		$steps = is_array( $steps ) ? $steps : array();
		$index = (int) $row['current_index'];
		$modules = json_decode( $row['modules_json'], true );
		$modules = is_array( $modules ) ? $modules : array();
		$job_id = (int) $row['id'];
		if ( ! isset( $ids[ $index ] ) || ! isset( $steps[ $index ] ) ) {
			self::finish_job( $job_id, $lock_until, 'failed', 100, 'failed', $now, 'ساختار دادهٔ Job ناقص یا نامعتبر است.' );
			if ( self::has_active_jobs() ) { self::schedule(); }
			return;
		}

		$post_id = (int) $ids[ $index ];
		$steps[ $index ]['status'] = 'running';
		$claimed = $wpdb->update(
			$table,
			array( 'steps_json' => wp_json_encode( $steps ), 'current_step' => 'audit_post_' . $post_id ),
			array( 'id' => $job_id, 'status' => 'running', 'locked_until_gmt' => $lock_until ),
			array( '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
		if ( 1 !== (int) $claimed ) {
			if ( self::has_active_jobs() ) { self::schedule(); }
			return;
		}

		$result = IAAE_Auditor::run( $post_id, 'queue', $modules );
		if ( is_wp_error( $result ) || ! is_array( $result ) || empty( $result['report_id'] ) ) {
			$steps[ $index ]['status'] = 'failed';
			$steps[ $index ]['error'] = is_wp_error( $result ) ? $result->get_error_message() : 'موتور گزارش معتبری برنگرداند.';
		} else {
			$steps[ $index ]['status'] = 'completed';
			$steps[ $index ]['report_id'] = (int) $result['report_id'];
		}
		$next_index = $index + 1;
		$complete = $next_index >= count( $ids );
		$failed = false;
		$errors = array();
		foreach ( $steps as $step ) {
			if ( isset( $step['status'] ) && 'failed' === $step['status'] ) {
				$failed = true;
				if ( ! empty( $step['error'] ) ) { $errors[] = (string) $step['error']; }
			}
		}
		$status = $complete ? ( $failed ? 'failed' : 'completed' ) : 'running';
		$progress = $complete ? 100 : (int) floor( $next_index / max( 1, count( $ids ) ) * 100 );
		$wpdb->update(
			$table,
			array(
				'steps_json' => wp_json_encode( $steps ),
				'current_index' => $next_index,
				'progress' => $progress,
				'status' => $status,
				'current_step' => $complete ? $status : 'waiting_next_chunk',
				'finished_at_gmt' => $complete ? current_time( 'mysql', true ) : null,
				'locked_until_gmt' => null,
				'error_message' => $complete && $failed ? implode( '؛ ', array_unique( $errors ) ) : '',
			),
			array( 'id' => $job_id, 'status' => 'running', 'locked_until_gmt' => $lock_until ),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
		if ( ! $complete || self::has_active_jobs() ) {
			self::schedule();
		}
	}

	/** Finish a structurally invalid job only if this worker still owns its lease. */
	private static function finish_job( $job_id, $lock_until, $status, $progress, $step, $now, $error ) {
		global $wpdb;
		$table = IAAE_Database::table( 'jobs' );
		$wpdb->update(
			$table,
			array( 'status' => $status, 'progress' => (int) $progress, 'current_step' => $step, 'finished_at_gmt' => $now, 'locked_until_gmt' => null, 'error_message' => (string) $error ),
			array( 'id' => (int) $job_id, 'status' => 'running', 'locked_until_gmt' => $lock_until ),
			array( '%s', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Get one job by numeric ID or `job_N` contract ID.
	 *
	 * @param int|string $job_id Job ID.
	 * @return array|null
	 */
	public static function get( $job_id ) {
		global $wpdb;
		$id = self::numeric_id( $job_id );
		if ( ! $id ) { return null; }
		$table = IAAE_Database::table( 'jobs' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", $id ), ARRAY_A );
		if ( ! is_array( $row ) ) { return null; }
		$post_ids = json_decode( $row['post_ids_json'], true );
		$modules = json_decode( $row['modules_json'], true );
		$steps = json_decode( $row['steps_json'], true );
		return array(
			'job_id' => 'job_' . (int) $row['id'],
			'post_ids' => is_array( $post_ids ) ? array_map( 'intval', $post_ids ) : array(),
			'modules' => is_array( $modules ) ? $modules : array(),
			'type' => (string) $row['type'],
			'status' => (string) $row['status'],
			'progress' => (int) $row['progress'],
			'current_step' => (string) $row['current_step'],
			'steps' => is_array( $steps ) ? $steps : array(),
			'created_by' => (int) $row['created_by'],
			'started_at' => self::iso( $row['started_at_gmt'] ),
			'finished_at' => self::iso( $row['finished_at_gmt'] ),
			'error' => (string) $row['error_message'],
		);
	}

	/**
	 * Cancel a queued/running job. A worker's lease check prevents it overwriting cancellation.
	 *
	 * @param int|string $job_id Job ID.
	 * @return array|WP_Error
	 */
	public static function cancel( $job_id ) {
		global $wpdb;
		$job = self::get( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'iaa_not_found', 'Job پیدا نشد.', array( 'status' => 404 ) );
		}
		if ( ! in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
			return new WP_Error( 'iaa_job_conflict', 'این Job دیگر فعال نیست.', array( 'status' => 409, 'job_id' => $job['job_id'] ) );
		}
		$id = self::numeric_id( $job['job_id'] );
		$table = IAAE_Database::table( 'jobs' );
		$updated = $wpdb->update(
			$table,
			array( 'status' => 'cancelled', 'current_step' => 'cancelled', 'finished_at_gmt' => current_time( 'mysql', true ), 'locked_until_gmt' => null ),
			array( 'id' => $id, 'status' => $job['status'] ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
		if ( false === $updated ) {
			return new WP_Error( 'iaa_engine_error', 'لغو Job ناموفق بود.', array( 'status' => 500 ) );
		}
		if ( 0 === (int) $updated ) {
			return new WP_Error( 'iaa_job_conflict', 'وضعیت Job هم‌زمان تغییر کرده است؛ آن را دوباره دریافت کنید.', array( 'status' => 409, 'job_id' => $job['job_id'] ) );
		}
		return self::get( $id );
	}

	private static function numeric_id( $job_id ) {
		if ( is_int( $job_id ) || ( is_string( $job_id ) && ctype_digit( $job_id ) ) ) {
			return absint( $job_id );
		}
		if ( is_string( $job_id ) && preg_match( '/^job_([1-9][0-9]*)$/', $job_id, $match ) ) {
			return absint( $match[1] );
		}
		return 0;
	}

	private static function iso( $mysql_gmt ) {
		if ( ! $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
			return null;
		}
		$timestamp = strtotime( $mysql_gmt . ' UTC' );
		return false === $timestamp ? null : gmdate( 'Y-m-d\\TH:i:s\\Z', $timestamp );
	}
}
