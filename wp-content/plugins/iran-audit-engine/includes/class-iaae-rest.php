<?php
/**
 * Version 1.1 REST surface. Dashboard must use these routes, never Engine tables.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_REST {
	const NAMESPACE = 'iran-audit/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_csv' ), 10, 4 );
	}

	public static function register_routes() {
		$view = array( 'permission_callback' => array( __CLASS__, 'can_view' ) );
		$run  = array( 'permission_callback' => array( __CLASS__, 'can_run' ) );
		$read = WP_REST_Server::READABLE;
		$create = WP_REST_Server::CREATABLE;

		register_rest_route( self::NAMESPACE, '/status', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'status' ) ) ) );
		register_rest_route( self::NAMESPACE, '/rules', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'rules' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'posts' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)/report', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'post_report' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)/history', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'post_history' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)/report/(?P<report_id>\d+)', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'specific_report' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)/diff', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'post_diff' ) ) ) );
		register_rest_route( self::NAMESPACE, '/posts/(?P<id>\d+)/audit', array_merge( $run, array( 'methods' => $create, 'callback' => array( __CLASS__, 'audit_post' ) ) ) );
		register_rest_route( self::NAMESPACE, '/queue', array_merge( $run, array( 'methods' => $create, 'callback' => array( __CLASS__, 'queue_posts' ) ) ) );
		register_rest_route( self::NAMESPACE, '/jobs/(?P<job_id>job_\d+)', array(
			array( 'methods' => $read, 'callback' => array( __CLASS__, 'get_job' ), 'permission_callback' => array( __CLASS__, 'can_view' ) ),
			array( 'methods' => 'DELETE', 'callback' => array( __CLASS__, 'cancel_job' ), 'permission_callback' => array( __CLASS__, 'can_run' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/stats/summary', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'stats_summary' ) ) ) );
		register_rest_route( self::NAMESPACE, '/stats/rankings', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'rankings' ) ) ) );
		register_rest_route( self::NAMESPACE, '/stats/top-issues', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'top_issues' ) ) ) );
		register_rest_route( self::NAMESPACE, '/stats/trend', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'trend' ) ) ) );
		register_rest_route( self::NAMESPACE, '/stats/distribution', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'distribution' ) ) ) );
		register_rest_route( self::NAMESPACE, '/stats/coverage-gaps', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'coverage_gaps' ) ) ) );
		register_rest_route( self::NAMESPACE, '/claims', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'claims' ) ) ) );
		register_rest_route( self::NAMESPACE, '/claims/(?P<id>\d+)', array_merge( $run, array( 'methods' => 'PATCH', 'callback' => array( __CLASS__, 'update_claim' ) ) ) );
		register_rest_route( self::NAMESPACE, '/export', array_merge( $view, array( 'methods' => $read, 'callback' => array( __CLASS__, 'export' ) ) ) );
	}

	public static function can_view() {
		return current_user_can( 'iaa_view' ) ? true : new WP_Error( 'iaa_forbidden', 'برای مشاهدهٔ گزارش مجوز ندارید.', array( 'status' => 403 ) );
	}

	public static function can_run() {
		return current_user_can( 'iaa_run' ) ? true : new WP_Error( 'iaa_forbidden', 'برای اجرای این عملیات مجوز ندارید.', array( 'status' => 403 ) );
	}

	public static function status() {
		global $wpdb;
		$rules = IAAE_Rules::health();
		$profile = IAAE_Profile_Mapper::health();
		$tables = IAAE_Database::tables_exist();
		$golden_files = glob( IAAE_PATH . 'tests/golden/*.json' );
		$report_table = IAAE_Database::table( 'reports' );
		$job_table = IAAE_Database::table( 'jobs' );
		$reports_count = $tables ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$report_table}" ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$active_jobs = $tables ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$job_table} WHERE status IN ('queued','running')" ) : 0; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		return rest_ensure_response(
			array(
				'engine_version' => IAAE_VERSION,
				'api_version' => IAAE_API_VERSION,
				'rules_schema_version' => 1,
				'rules_version' => $rules['rules_version'],
				'rules_valid' => $rules['valid'],
				'rule_count' => $rules['rule_count'],
				'rules_error' => $rules['error'],
				'database' => array( 'ready' => $tables, 'schema_version' => get_option( 'iaa_schema_version', '' ) ),
				'queue' => array( 'active_jobs' => $active_jobs ),
				'profile_mapping' => $profile,
				'reports_count' => $reports_count,
				'capabilities' => array( 'view' => 'iaa_view', 'run' => 'iaa_run', 'manage' => 'iaa_manage' ),
				'checks' => array(
					'rendered_html_enabled' => (bool) IAAE_Database::get_setting( 'rendered_checks', false ),
					'external_link_checks_enabled' => false,
					'geography_dataset_present' => is_readable( IAAE_PATH . 'data/iran-divisions.csv' ),
					'golden_set_present' => is_array( $golden_files ) && ! empty( $golden_files ),
				),
			)
		);
	}

	public static function rules() {
		$data = IAAE_Rules::all();
		if ( empty( $data ) ) {
			return new WP_Error( 'iaa_rules_invalid', IAAE_Rules::health()['error'], array( 'status' => 500 ) );
		}
		$public_rules = array();
		foreach ( IAAE_Rules::list_rules() as $rule ) {
			$public_rules[] = array(
				'id' => (string) $rule['id'],
				'category' => (string) $rule['category'],
				'title' => (string) $rule['title'],
				'severity' => (string) $rule['severity'],
				'weight' => (float) $rule['weight'],
				'enabled' => ! empty( $rule['enabled'] ),
				'applies_to' => (array) $rule['applies_to'],
				'basis' => isset( $rule['basis'] ) ? (string) $rule['basis'] : '',
				'source' => isset( $rule['source'] ) ? (string) $rule['source'] : '',
				'manual_review_only' => ! empty( $rule['manual_review_only'] ),
				'scientific_note' => isset( $rule['scientific_note'] ) ? (string) $rule['scientific_note'] : '',
			);
		}
		return rest_ensure_response(
			array(
				'schema_version' => (int) $data['schema_version'],
				'rules_version' => (string) $data['rules_version'],
				'category_weights' => (array) $data['category_weights'],
				'profiles' => (array) $data['profiles'],
				'profile_checklists' => (array) $data['profile_checklists'],
				'rules' => $public_rules,
			)
		);
	}

	public static function posts( $request ) {
		global $wpdb;
		$per_page = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 20 ) ) );
		$page = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$public_types = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment', 'revision', 'nav_menu_item' ) ) );
		if ( ! $public_types ) {
			$response = new WP_REST_Response( array(), 200 );
			$response->header( 'X-WP-Total', '0' );
			$response->header( 'X-WP-TotalPages', '1' );
			return $response;
		}
		$where = array( "p.post_status IN ('publish','draft','pending')", 'p.post_type IN (' . implode( ',', array_fill( 0, count( $public_types ), '%s' ) ) . ')' );
		$params = $public_types;

		$post_type = sanitize_key( (string) $request->get_param( 'post_type' ) );
		if ( '' !== $post_type ) {
			if ( ! in_array( $post_type, $public_types, true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'نوع‌نوشتهٔ عمومی نامعتبر است.', array( 'status' => 400 ) );
			}
			$where[] = 'p.post_type=%s';
			$params[] = $post_type;
		}

		$profile = sanitize_key( (string) $request->get_param( 'profile' ) );
		if ( '' !== $profile ) {
			if ( ! array_key_exists( $profile, IAAE_Profile_Mapper::profiles() ) ) {
				return new WP_Error( 'iaa_invalid_param', 'پروفایل نامعتبر است.', array( 'status' => 400 ) );
			}
			$map = IAAE_Profile_Mapper::map();
			$profile_types = array_values( array_intersect( $public_types, array_keys( array_filter( $map, static function ( $value ) use ( $profile ) { return $value === $profile; } ) ) ) );
			if ( ! $profile_types ) {
				$where[] = '1=0';
			} else {
				$where[] = 'p.post_type IN (' . implode( ',', array_fill( 0, count( $profile_types ), '%s' ) ) . ')';
				$params = array_merge( $params, $profile_types );
			}
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( '' !== $status ) {
			$allowed_statuses = array( 'verified', 'warning', 'issue', 'insufficient', 'unaudited' );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'status نامعتبر است.', array( 'status' => 400 ) );
			}
			if ( 'unaudited' === $status ) {
				$where[] = 'r.id IS NULL';
			} else {
				$where[] = 'r.status=%s';
				$params[] = $status;
			}
		}

		foreach ( array( 'score_min' => '>=', 'score_max' => '<=' ) as $key => $operator ) {
			$value = $request->get_param( $key );
			if ( null === $value || '' === $value ) { continue; }
			if ( ! is_numeric( $value ) || (float) $value < 0 || (float) $value > 100 ) {
				return new WP_Error( 'iaa_invalid_param', $key . ' باید عددی بین صفر تا ۱۰۰ باشد.', array( 'status' => 400 ) );
			}
			$where[] = 'r.score ' . $operator . ' %f';
			$params[] = (float) $value;
		}

		foreach ( array( 'province', 'county' ) as $field ) {
			$value = sanitize_text_field( (string) $request->get_param( $field ) );
			if ( '' !== $value ) {
				$where[] = 'r.' . $field . ' LIKE %s';
				$params[] = '%' . $wpdb->esc_like( $value ) . '%';
			}
		}

		$wanted_rule = sanitize_text_field( (string) $request->get_param( 'has_rule_issue' ) );
		if ( '' !== $wanted_rule ) {
			if ( strlen( $wanted_rule ) > 64 || ! IAAE_Rules::find( $wanted_rule ) ) {
				return new WP_Error( 'iaa_invalid_param', 'شناسهٔ قانون برای has_rule_issue معتبر نیست.', array( 'status' => 400 ) );
			}
			$issue_table = IAAE_Database::table( 'issues' );
			$where[] = "EXISTS (SELECT 1 FROM {$issue_table} i WHERE i.report_id=r.id AND i.rule_id=%s AND i.status IN ('issue','warning'))";
			$params[] = $wanted_rule;
		}

		$orderby = sanitize_key( (string) ( $request->get_param( 'orderby' ) ?: 'title' ) );
		$order = strtolower( (string) ( $request->get_param( 'order' ) ?: 'asc' ) );
		if ( ! in_array( $orderby, array( 'title', 'score', 'audited_at', 'issues_critical' ), true ) || ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			return new WP_Error( 'iaa_invalid_param', 'orderby یا order نامعتبر است.', array( 'status' => 400 ) );
		}
		$order_sql = strtoupper( $order );
		$sort_columns = array(
			'title' => 'p.post_title',
			'score' => 'r.score',
			'audited_at' => 'r.audited_at_gmt',
			'issues_critical' => "(SELECT COUNT(*) FROM " . IAAE_Database::table( 'issues' ) . " critical_issues WHERE critical_issues.report_id=r.id AND critical_issues.status='issue' AND critical_issues.severity='critical')",
		);
		$from = " FROM {$wpdb->posts} p LEFT JOIN " . self::latest_reports_sql() . ' r ON r.post_id=p.ID ';
		$where_sql = ' WHERE ' . implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*)' . $from . $where_sql;
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		$offset = ( $page - 1 ) * $per_page;
		$select_sql = 'SELECT p.ID AS post_id,p.post_title,p.post_type,p.post_status,r.id AS report_id,r.profile AS report_profile,r.province,r.county,r.score,r.status AS report_status,r.coverage,r.audited_at_gmt' . $from . $where_sql . ' ORDER BY ' . $sort_columns[ $orderby ] . ' ' . $order_sql . ',p.ID ASC LIMIT %d OFFSET %d';
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $select_sql, $query_params ), ARRAY_A );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			if ( ! current_user_can( 'read_post', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
				continue;
			}
			$items[] = self::post_summary_from_row( $row );
		}
		$response = new WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $response;
	}

	private static function post_summary_from_row( $row ) {
		$map = IAAE_Profile_Mapper::map();
		$has_report = ! empty( $row['report_id'] );
		$post_id = (int) $row['post_id'];
		return array(
			'post_id' => $post_id,
			'post_title' => (string) $row['post_title'],
			'post_url' => (string) get_permalink( $post_id ),
			'post_type' => (string) $row['post_type'],
			'post_status' => (string) $row['post_status'],
			'profile' => $has_report ? (string) $row['report_profile'] : ( isset( $map[ $row['post_type'] ] ) ? (string) $map[ $row['post_type'] ] : '' ),
			'province' => $has_report ? (string) $row['province'] : '',
			'county' => $has_report ? (string) $row['county'] : '',
			'score' => $has_report && null !== $row['score'] ? (float) $row['score'] : null,
			'status' => $has_report ? (string) $row['report_status'] : 'unaudited',
			'coverage' => $has_report ? (float) $row['coverage'] : 0.0,
			'audited_at' => $has_report ? self::iso( $row['audited_at_gmt'] ) : null,
		);
	}

	public static function post_report( $request ) {
		$post_id = absint( $request['id'] );
		$access = self::post_access_error( $post_id );
		if ( is_wp_error( $access ) ) { return $access; }
		$report = self::latest_report( $post_id );
		if ( ! $report ) { return new WP_Error( 'iaa_not_found', 'برای این نوشته گزارشی ثبت نشده است.', array( 'status' => 404 ) ); }
		$full_report = IAAE_Auditor::get_report( (int) $report['id'] );
		return $full_report ? rest_ensure_response( $full_report ) : new WP_Error( 'iaa_not_found', 'گزارش ذخیره‌شده دیگر در دسترس نیست.', array( 'status' => 404 ) );
	}

	public static function post_history( $request ) {
		global $wpdb;
		$post_id = absint( $request['id'] );
		$access = self::post_access_error( $post_id );
		if ( is_wp_error( $access ) ) { return $access; }
		$table = IAAE_Database::table( 'reports' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,post_id,score,status,coverage,engine_version,rules_version,trigger_type,audited_at_gmt,content_hash FROM {$table} WHERE post_id=%d ORDER BY id DESC LIMIT 50", $post_id ), ARRAY_A );
		return rest_ensure_response( array_map( array( __CLASS__, 'history_row' ), (array) $rows ) );
	}

	public static function specific_report( $request ) {
		$access = self::post_access_error( absint( $request['id'] ) );
		if ( is_wp_error( $access ) ) { return $access; }
		$report = IAAE_Auditor::get_report( absint( $request['report_id'] ) );
		if ( ! $report || (int) $report['post_id'] !== absint( $request['id'] ) ) {
			return new WP_Error( 'iaa_not_found', 'گزارش متعلق به این نوشته نیست یا پیدا نشد.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $report );
	}

	public static function post_diff( $request ) {
		$post_id = absint( $request['id'] );
		$access = self::post_access_error( $post_id );
		if ( is_wp_error( $access ) ) { return $access; }
		$a = absint( $request->get_param( 'a' ) );
		$b = absint( $request->get_param( 'b' ) );
		if ( ! $a || ! $b || $a === $b ) {
			return new WP_Error( 'iaa_invalid_param', 'دو شناسهٔ گزارش متفاوت برای a و b الزامی است.', array( 'status' => 400 ) );
		}
		$report_a = IAAE_Auditor::get_report( $a );
		$report_b = IAAE_Auditor::get_report( $b );
		if ( ! $report_a || ! $report_b || $post_id !== (int) $report_a['post_id'] || $post_id !== (int) $report_b['post_id'] ) {
			return new WP_Error( 'iaa_not_found', 'دو گزارش معتبر از همین نوشته لازم است.', array( 'status' => 404 ) );
		}
		$issues_a = self::issue_keys( $report_a['issues'] );
		$issues_b = self::issue_keys( $report_b['issues'] );
		return rest_ensure_response(
			array(
				'post_id' => $post_id,
				'report_a' => $a,
				'report_b' => $b,
				'resolved' => array_values( array_diff_key( $issues_a, $issues_b ) ),
				'new' => array_values( array_diff_key( $issues_b, $issues_a ) ),
				'unchanged' => array_values( array_intersect_key( $issues_a, $issues_b ) ),
			)
		);
	}

	public static function audit_post( $request ) {
		$access = self::post_access_error( absint( $request['id'] ) );
		if ( is_wp_error( $access ) ) { return $access; }
		$result = IAAE_Jobs::enqueue( array( absint( $request['id'] ) ), array(), get_current_user_id() );
		if ( is_wp_error( $result ) ) { return $result; }
		$response = rest_ensure_response( $result );
		$response->set_status( 202 );
		return $response;
	}

	public static function queue_posts( $request ) {
		$result = IAAE_Jobs::enqueue( (array) $request->get_param( 'post_ids' ), (array) $request->get_param( 'modules' ), get_current_user_id() );
		if ( is_wp_error( $result ) ) { return $result; }
		$response = rest_ensure_response( $result );
		$response->set_status( 202 );
		return $response;
	}

	public static function get_job( $request ) {
		$job = IAAE_Jobs::get( (string) $request['job_id'] );
		return $job ? rest_ensure_response( $job ) : new WP_Error( 'iaa_not_found', 'Job پیدا نشد.', array( 'status' => 404 ) );
	}

	public static function cancel_job( $request ) {
		return IAAE_Jobs::cancel( (string) $request['job_id'] );
	}

	public static function stats_summary() {
		global $wpdb;
		$latest = self::latest_reports_sql();
		$rows = $wpdb->get_results( "SELECT status,COUNT(*) AS total,AVG(score) AS average_score,COUNT(score) AS score_count,AVG(coverage) AS average_coverage,COUNT(coverage) AS coverage_count FROM {$latest} latest GROUP BY status", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$out = array( 'reports' => 0, 'average_score' => null, 'average_coverage' => null, 'by_status' => array( 'verified' => 0, 'warning' => 0, 'issue' => 0, 'insufficient' => 0 ) );
		$scores = array();
		$coverage = array();
		foreach ( (array) $rows as $row ) {
			$status = (string) $row['status'];
			$count = (int) $row['total'];
			$out['reports'] += $count;
			if ( isset( $out['by_status'][ $status ] ) ) { $out['by_status'][ $status ] = $count; }
			if ( null !== $row['average_score'] && (int) $row['score_count'] > 0 ) { $scores[] = array( (float) $row['average_score'], (int) $row['score_count'] ); }
			if ( null !== $row['average_coverage'] && (int) $row['coverage_count'] > 0 ) { $coverage[] = array( (float) $row['average_coverage'], (int) $row['coverage_count'] ); }
		}
		$out['average_score'] = self::weighted_average( $scores );
		$out['average_coverage'] = self::weighted_average( $coverage );
		return rest_ensure_response( $out );
	}

	public static function rankings( $request ) {
		$group_by = sanitize_key( (string) ( $request->get_param( 'group_by' ) ?: 'post' ) );
		$order_param = strtolower( (string) ( $request->get_param( 'order' ) ?: 'asc' ) );
		if ( ! in_array( $order_param, array( 'asc', 'desc' ), true ) ) {
			return new WP_Error( 'iaa_invalid_param', 'order باید asc یا desc باشد.', array( 'status' => 400 ) );
		}
		$order = strtoupper( $order_param );
		if ( ! in_array( $group_by, array( 'post', 'province', 'county' ), true ) ) {
			return new WP_Error( 'iaa_invalid_param', 'group_by باید post، province یا county باشد.', array( 'status' => 400 ) );
		}
		global $wpdb;
		$latest = self::latest_reports_sql();
		if ( 'post' === $group_by ) {
			$rows = $wpdb->get_results( "SELECT post_id,post_title,post_url,profile,province,county,score,status,coverage,audited_at_gmt FROM {$latest} latest ORDER BY score {$order},post_id ASC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			return rest_ensure_response( array_map( array( __CLASS__, 'ranking_row' ), (array) $rows ) );
		}
		$field = 'province' === $group_by ? 'province' : 'county';
		$rows = $wpdb->get_results( "SELECT {$field} AS label,COUNT(*) AS posts,AVG(score) AS score,AVG(coverage) AS coverage FROM {$latest} latest WHERE {$field}<>'' GROUP BY {$field} ORDER BY score {$order},label ASC LIMIT 500", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		return rest_ensure_response( array_map( array( __CLASS__, 'group_ranking_row' ), (array) $rows ) );
	}

	public static function top_issues( $request ) {
		global $wpdb;
		$table = IAAE_Database::table( 'issues' );
		$latest = self::latest_reports_sql();
		$limit = min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT i.rule_id,i.category,i.severity,COUNT(*) AS occurrences,COUNT(DISTINCT latest.post_id) AS reports FROM {$latest} latest INNER JOIN {$table} i ON i.report_id=latest.id WHERE i.status IN ('issue','warning') GROUP BY i.rule_id,i.category,i.severity ORDER BY occurrences DESC,i.rule_id ASC LIMIT %d", $limit ), ARRAY_A );
		return rest_ensure_response( $rows );
	}

	public static function trend( $request ) {
		global $wpdb;
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( ! $post_id ) { return new WP_Error( 'iaa_invalid_param', 'post_id الزامی است.', array( 'status' => 400 ) ); }
		$access = self::post_access_error( $post_id );
		if ( is_wp_error( $access ) ) { return $access; }
		$table = IAAE_Database::table( 'reports' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,post_id,score,status,coverage,audited_at_gmt,content_hash FROM {$table} WHERE post_id=%d ORDER BY id DESC LIMIT 200", $post_id ), ARRAY_A );
		return rest_ensure_response( array_map( array( __CLASS__, 'history_row' ), array_reverse( (array) $rows ) ) );
	}

	public static function distribution() {
		$response = self::stats_summary();
		$data = $response instanceof WP_REST_Response ? $response->get_data() : array();
		return rest_ensure_response( isset( $data['by_status'] ) && is_array( $data['by_status'] ) ? $data['by_status'] : array() );
	}

	public static function coverage_gaps() {
		global $wpdb;
		$table = IAAE_Database::table( 'issues' );
		$latest = self::latest_reports_sql();
		$rows = $wpdb->get_results( "SELECT i.rule_id,COUNT(DISTINCT latest.post_id) AS affected_reports,MIN(i.message) AS reason FROM {$latest} latest INNER JOIN {$table} i ON i.report_id=latest.id WHERE i.status='insufficient' GROUP BY i.rule_id ORDER BY affected_reports DESC,i.rule_id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$gaps = array();
		$known_rule_ids = array();
		foreach ( (array) $rows as $row ) {
			$rule_id = (string) $row['rule_id'];
			$known_rule_ids[ $rule_id ] = true;
			$gaps[] = array( 'rule_id' => $rule_id, 'affected_reports' => (int) $row['affected_reports'], 'reason' => (string) $row['reason'] );
		}
		$add_global_gap = static function ( $rule_id, $reason ) use ( &$gaps, &$known_rule_ids ) {
			if ( isset( $known_rule_ids[ $rule_id ] ) ) { return; }
			$known_rule_ids[ $rule_id ] = true;
			$gaps[] = array( 'rule_id' => (string) $rule_id, 'affected_reports' => null, 'reason' => (string) $reason );
		};

		$profile = IAAE_Profile_Mapper::health();
		if ( ! empty( $profile['unmapped'] ) ) {
			$add_global_gap( 'PROFILE-MAPPING', 'نگاشت پروفایل این نوع‌نوشته‌ها تأیید نشده است: ' . implode( '، ', wp_list_pluck( $profile['unmapped'], 'post_type' ) ) );
		}
		if ( ! IAAE_Database::get_setting( 'rendered_checks', false ) ) {
			foreach ( array( 'SEO-TITLE-01', 'SEO-META-01', 'SEO-H1-01', 'SEO-HEADING-HIER-01', 'SEO-INDEX-01', 'SEO-SCHEMA-01', 'GEO-EEAT-01', 'UX-HTML-01', 'UX-MOBILE-01' ) as $rule_id ) {
				$add_global_gap( $rule_id, 'بررسی به HTML رندرشده نیاز دارد؛ این دریافت opt-in خاموش است و فقط پس از آزمون staging باید فعال شود.' );
			}
		}
		$spell = IAAE_Rules::support_data( 'spelling-lexicon.json' );
		if ( is_wp_error( $spell ) || empty( $spell['entries'] ) ) {
			$add_global_gap( 'FA-SPELL-01', 'واژه‌نامهٔ تأییدشده خالی است؛ غلط املایی از حافظه ساخته نمی‌شود.' );
		}
		$cliches = IAAE_Rules::support_data( 'cliches-fa.json' );
		if ( is_wp_error( $cliches ) || empty( $cliches['entries'] ) ) {
			$add_global_gap( 'FA-TONE-01', 'فهرست کلیشهٔ تأییدشده خالی است؛ تشخیص لحن ماشینی انجام نمی‌شود.' );
		}
		foreach ( array(
			'DUP-INTERNAL-01' => 'محاسبهٔ شباهت به corpus کامل و نسخه‌دار نیاز دارد؛ مقایسهٔ ناقص اجرا نمی‌شود.',
			'DUP-CANNIBAL-01' => 'کلمه‌های کانونی همهٔ صفحات در دسترس نیستند؛ هم‌نوع‌خواری جست‌وجو حدس زده نمی‌شود.',
			'DUP-TEMPLATE-01' => 'مجموعهٔ هم‌پروفایل برای شناسایی پاراگراف‌های قالبی آماده نیست.',
			'DUP-THIN-01' => 'معیار محتوای یکتا و Golden Set انسانی موجود نیست.',
			'LNK-ORPHAN-01' => 'برای اثبات یتیم‌بودن، ایندکس کامل و تازهٔ لینک‌های ورودی لازم است.',
			'LNK-EXT-01' => 'بررسی HTTP لینک بیرونی در این نسخه اجرا نمی‌شود؛ درخواست خارجی ارسال نمی‌شود.',
			'GEO-MAP-01' => 'کلیدهای متای مختصات تأیید و پیکربندی نشده‌اند؛ صحت مکانی مختصات نیز بررسی نمی‌شود.',
			'GEO-CHECKLIST-01' => 'نگاشت تیترهای فارسی به چک‌لیست پروفایل با Golden Set تأیید نشده است.',
			'GEO-CONSISTENCY-01' => 'تشخیص تعارض معنایی به استخراج موضوعی و Golden Set نیاز دارد.',
		) as $rule_id => $reason ) {
			$add_global_gap( $rule_id, $reason );
		}
		if ( ! is_readable( IAAE_PATH . 'data/iran-divisions.csv' ) ) {
			$add_global_gap( 'GEO-NAMES-01', 'دیتاست موردتأیید تقسیمات کشوری در بستهٔ موتور موجود نیست.' );
		} else {
			$add_global_gap( 'GEO-NAMES-01', 'فایل تقسیمات موجود است، اما parser و alias map به منبع نسخه‌دار تأییدشده متصل نشده است.' );
		}
		$golden_files = glob( IAAE_PATH . 'tests/golden/*.json' );
		if ( ! is_array( $golden_files ) || ! $golden_files ) {
			$add_global_gap( 'GOLDEN-SET', 'مجموعهٔ طلایی با برچسب انسانی برای TP/FP/FN موجود نیست.' );
		}
		return rest_ensure_response( $gaps );
	}

	public static function claims( $request ) {
		global $wpdb;
		$table = IAAE_Database::table( 'claims' );
		$latest = self::latest_reports_sql();
		$where = array( '1=1' );
		$params = array();
		$review_state = sanitize_key( (string) $request->get_param( 'review_state' ) );
		if ( '' !== $review_state ) {
			if ( ! in_array( $review_state, array( 'pending', 'verified_by_human', 'rejected' ), true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'review_state نامعتبر است.', array( 'status' => 400 ) );
			}
			$where[] = 'c.review_state=%s';
			$params[] = $review_state;
		}
		$kind = sanitize_key( (string) $request->get_param( 'kind' ) );
		if ( '' !== $kind ) {
			if ( ! in_array( $kind, array( 'year', 'population', 'area', 'elevation', 'name', 'other' ), true ) ) {
				return new WP_Error( 'iaa_invalid_param', 'kind نامعتبر است.', array( 'status' => 400 ) );
			}
			$where[] = 'c.kind=%s';
			$params[] = $kind;
		}
		$post_param = $request->get_param( 'post_id' );
		if ( null !== $post_param && '' !== $post_param ) {
			$post_id = absint( $post_param );
			if ( ! $post_id ) { return new WP_Error( 'iaa_invalid_param', 'post_id نامعتبر است.', array( 'status' => 400 ) ); }
			$access = self::post_access_error( $post_id );
			if ( is_wp_error( $access ) ) { return $access; }
			$where[] = 'latest.post_id=%d';
			$params[] = $post_id;
		}
		$limit = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$params[] = $limit;
		$sql = "SELECT c.id,c.report_id,latest.post_id,c.kind,c.claim_value,c.has_source,c.review_state,c.reviewed_by,c.reviewed_at_gmt,c.context_json FROM {$latest} latest INNER JOIN {$table} c ON c.report_id=latest.id WHERE " . implode( ' AND ', $where ) . ' ORDER BY c.id DESC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		$rows = (array) $rows;
		foreach ( $rows as &$row ) {
			$row['id'] = (int) $row['id'];
			$row['report_id'] = (int) $row['report_id'];
			$row['post_id'] = (int) $row['post_id'];
			$row['has_source'] = (bool) $row['has_source'];
			$row['context'] = json_decode( $row['context_json'], true ) ?: array();
			$row['reviewed_by'] = null === $row['reviewed_by'] ? null : (int) $row['reviewed_by'];
			$row['reviewed_at'] = self::iso( $row['reviewed_at_gmt'] );
			unset( $row['context_json'], $row['reviewed_at_gmt'] );
		}
		unset( $row );
		return rest_ensure_response( $rows );
	}

	public static function update_claim( $request ) {
		global $wpdb;
		$state = sanitize_key( (string) $request->get_param( 'review_state' ) );
		if ( ! in_array( $state, array( 'pending', 'verified_by_human', 'rejected' ), true ) ) {
			return new WP_Error( 'iaa_invalid_param', 'review_state نامعتبر است.', array( 'status' => 400 ) );
		}
		$table = IAAE_Database::table( 'claims' );
		$id = absint( $request['id'] );
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id=%d LIMIT 1", $id ) );
		if ( ! $exists ) { return new WP_Error( 'iaa_not_found', 'ادعای موردنظر پیدا نشد.', array( 'status' => 404 ) ); }
		$values = array( 'review_state' => $state, 'reviewed_by' => 'pending' === $state ? null : get_current_user_id(), 'reviewed_at_gmt' => 'pending' === $state ? null : current_time( 'mysql', true ) );
		$updated = $wpdb->update(
			$table,
			$values,
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) { return new WP_Error( 'iaa_engine_error', 'به‌روزرسانی وضعیت claim ناموفق بود.', array( 'status' => 500 ) ); }
		return rest_ensure_response( array( 'id' => $id, 'review_state' => $state ) );
	}

	public static function export( $request ) {
		$format = sanitize_key( (string) ( $request->get_param( 'format' ) ?: 'csv' ) );
		if ( 'csv' !== $format ) { return new WP_Error( 'iaa_invalid_param', 'فقط CSV پشتیبانی می‌شود.', array( 'status' => 400 ) ); }
		$export_request = new WP_REST_Request( 'GET' );
		foreach ( array( 'search', 'post_type', 'province', 'county', 'profile', 'status', 'score_min', 'score_max', 'has_rule_issue', 'orderby', 'order' ) as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value ) { $export_request->set_param( $key, $value ); }
		}
		$export_request->set_param( 'per_page', 100 );
		$rows = array();
		$total = 0;
		$total_pages = 1;
		for ( $page = 1; $page <= 100; $page++ ) {
			$export_request->set_param( 'page', $page );
			$page_response = self::posts( $export_request );
			if ( is_wp_error( $page_response ) ) { return $page_response; }
			if ( ! $page_response instanceof WP_REST_Response ) { return new WP_Error( 'iaa_engine_error', 'ساخت صفحهٔ خروجی ناموفق بود.', array( 'status' => 500 ) ); }
			$page_rows = $page_response->get_data();
			if ( ! is_array( $page_rows ) ) { return new WP_Error( 'iaa_engine_error', 'ساختار صفحهٔ خروجی معتبر نیست.', array( 'status' => 500 ) ); }
			$page_headers = array_change_key_case( $page_response->get_headers(), CASE_LOWER );
			$total = isset( $page_headers['x-wp-total'] ) ? (int) $page_headers['x-wp-total'] : 0;
			$total_pages = isset( $page_headers['x-wp-totalpages'] ) ? max( 1, (int) $page_headers['x-wp-totalpages'] ) : 1;
			if ( $total > 10000 ) { return new WP_Error( 'iaa_invalid_param', 'خروجی CSV در هر درخواست حداکثر ۱۰٬۰۰۰ نوشته می‌پذیرد؛ از فیلترها برای کوچک‌کردن نتیجه استفاده کنید.', array( 'status' => 413 ) ); }
			$rows = array_merge( $rows, $page_rows );
			if ( $page >= $total_pages || ! $page_rows ) { break; }
		}
		$stream = fopen( 'php://temp/maxmemory:5242880', 'w+' );
		if ( false === $stream ) { return new WP_Error( 'iaa_engine_error', 'ساخت فایل موقت CSV ناموفق بود.', array( 'status' => 500 ) ); }
		$header = array( 'post_id', 'title', 'url', 'post_type', 'profile', 'province', 'city', 'score', 'status', 'coverage', 'audited_at' );
		if ( false === fputcsv( $stream, $header, ',', '"', '' ) ) { fclose( $stream ); return new WP_Error( 'iaa_engine_error', 'نوشتن سرستون CSV ناموفق بود.', array( 'status' => 500 ) ); }
		foreach ( $rows as $row ) {
			$values = array( $row['post_id'], $row['post_title'], $row['post_url'], $row['post_type'], $row['profile'], $row['province'], $row['county'], $row['score'], $row['status'], $row['coverage'], $row['audited_at'] );
			if ( false === fputcsv( $stream, array_map( array( __CLASS__, 'csv_cell' ), $values ), ',', '"', '' ) ) { fclose( $stream ); return new WP_Error( 'iaa_engine_error', 'نوشتن ردیف CSV ناموفق بود.', array( 'status' => 500 ) ); }
		}
		rewind( $stream );
		$csv = stream_get_contents( $stream );
		fclose( $stream );
		$response = new WP_REST_Response( (string) $csv, 200 );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="iran-audit-export.csv"' );
		return $response;
	}

	public static function serve_csv( $served, $result, $request, $server ) {
		$format = $request instanceof WP_REST_Request ? sanitize_key( (string) ( $request->get_param( 'format' ) ?: 'csv' ) ) : '';
		if ( ! $request instanceof WP_REST_Request || self::NAMESPACE . '/export' !== ltrim( $request->get_route(), '/' ) || 'csv' !== $format ) {
			return $served;
		}
		if ( ! $result instanceof WP_REST_Response ) { return $served; }
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="iran-audit-export.csv"' );
		}
		echo "\xEF\xBB\xBF" . (string) $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return true;
	}

	private static function csv_cell( $value ) {
		$value = (string) $value;
		return 1 === preg_match( '/^[\x00-\x20]*[=+@\-]/', $value ) ? "'" . $value : $value;
	}

	private static function latest_report( $post_id ) {
		global $wpdb;
		$table = IAAE_Database::table( 'reports' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id=%d ORDER BY id DESC LIMIT 1", (int) $post_id ), ARRAY_A );
	}

	private static function latest_reports_sql() {
		global $wpdb;
		$table = IAAE_Database::table( 'reports' );
		$public_types = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), array( 'attachment', 'revision', 'nav_menu_item' ) ) );
		if ( ! $public_types ) {
			return "(SELECT r.* FROM {$table} r WHERE 1=0)";
		}
		$quoted_types = array_map( static function ( $type ) { return "'" . esc_sql( sanitize_key( $type ) ) . "'"; }, $public_types );
		return "(SELECT r.* FROM {$table} r INNER JOIN (SELECT post_id,MAX(id) AS id FROM {$table} GROUP BY post_id) latest_ids ON latest_ids.id=r.id INNER JOIN {$wpdb->posts} current_posts ON current_posts.ID=r.post_id WHERE current_posts.post_status IN ('publish','draft','pending') AND current_posts.post_type IN (" . implode( ',', $quoted_types ) . '))';
	}

	private static function history_row( $row ) {
		return array(
			'report_id' => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'post_id' => isset( $row['post_id'] ) ? (int) $row['post_id'] : 0,
			'score' => null === $row['score'] ? null : (float) $row['score'],
			'status' => (string) $row['status'],
			'coverage' => (float) $row['coverage'],
			'engine_version' => isset( $row['engine_version'] ) ? (string) $row['engine_version'] : '',
			'rules_version' => isset( $row['rules_version'] ) ? (string) $row['rules_version'] : '',
			'trigger' => isset( $row['trigger_type'] ) ? (string) $row['trigger_type'] : '',
			'audited_at' => isset( $row['audited_at_gmt'] ) ? self::iso( $row['audited_at_gmt'] ) : '',
			'content_hash' => isset( $row['content_hash'] ) ? 'sha256:' . (string) $row['content_hash'] : '',
		);
	}

	private static function post_access_error( $post_id ) {
		$post_id = absint( $post_id );
		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return new WP_Error( 'iaa_not_found', 'نوشته پیدا نشد یا دیگر قابل‌ممیزی نیست.', array( 'status' => 404 ) );
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || empty( $type->public ) ) {
			return new WP_Error( 'iaa_not_found', 'این نوشته در دامنهٔ عمومی ممیزی نیست.', array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'read_post', $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return new WP_Error( 'iaa_forbidden', 'برای مشاهدهٔ این نوشته مجوز ندارید.', array( 'status' => 403 ) );
		}
		return $post;
	}


	private static function report_has_rule( $report_id, $rule_id ) {
		global $wpdb;
		$table = IAAE_Database::table( 'issues' );
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE report_id=%d AND rule_id=%s AND status IN ('issue','warning') LIMIT 1", (int) $report_id, (string) $rule_id ) );
	}

	private static function issue_keys( $issues ) {
		$out = array();
		foreach ( (array) $issues as $issue ) {
			if ( ! in_array( isset( $issue['status'] ) ? $issue['status'] : '', array( 'issue', 'warning' ), true ) ) {
				continue;
			}
			$evidence = isset( $issue['evidence'] ) ? wp_json_encode( $issue['evidence'] ) : '';
			$key = (string) $issue['rule_id'] . '|' . hash( 'sha256', $evidence );
			$out[ $key ] = $issue;
		}
		return $out;
	}

	private static function ranking_row( $row ) {
		$row['post_id'] = (int) $row['post_id'];
		$row['score'] = null === $row['score'] ? null : (float) $row['score'];
		$row['coverage'] = (float) $row['coverage'];
		$row['audited_at'] = self::iso( $row['audited_at_gmt'] );
		unset( $row['audited_at_gmt'] );
		return $row;
	}

	private static function group_ranking_row( $row ) {
		return array( 'group' => (string) $row['label'], 'posts' => (int) $row['posts'], 'score' => null === $row['score'] ? null : round( (float) $row['score'], 2 ), 'coverage' => round( (float) $row['coverage'], 2 ) );
	}

	private static function weighted_average( $values ) {
		$sum = 0.0;
		$weight = 0;
		foreach ( (array) $values as $pair ) { $sum += $pair[0] * $pair[1]; $weight += $pair[1]; }
		return $weight ? round( $sum / $weight, 2 ) : null;
	}

	private static function iso( $mysql_gmt ) {
		if ( ! $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) { return null; }
		$timestamp = strtotime( $mysql_gmt . ' UTC' );
		return $timestamp ? gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) : null;
	}
}
