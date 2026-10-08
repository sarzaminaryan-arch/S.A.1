<?php
/**
 * Orchestrates a read-only audit and writes only engine-owned report data.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Auditor {
	/**
	 * Audit one WordPress post without updating it.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $trigger manual|queue|auto.
	 * @param array  $modules Optional category/module allowlist.
	 * @return array|WP_Error
	 */
	public static function run( $post_id, $trigger = 'manual', $modules = array() ) {
		$post = get_post( (int) $post_id );
		if ( ! ( $post instanceof WP_Post ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return new WP_Error( 'iaa_not_found', 'نوشتهٔ قابل‌سنجش پیدا نشد.', array( 'status' => 404 ) );
		}
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object || empty( $post_type_object->public ) ) {
			return new WP_Error( 'iaa_forbidden', 'این نوع‌نوشته در دامنهٔ عمومی ممیزی نیست.', array( 'status' => 403 ) );
		}
		if ( ! IAAE_Database::tables_exist() ) {
			return new WP_Error( 'iaa_engine_error', 'جدول‌های موتور نصب نشده‌اند.', array( 'status' => 500 ) );
		}

		$rules_health = IAAE_Rules::health();
		if ( empty( $rules_health['valid'] ) ) {
			return new WP_Error( 'iaa_rules_invalid', $rules_health['error'], array( 'status' => 500 ) );
		}
		$profile = IAAE_Profile_Mapper::for_post( $post );
		$url = get_permalink( $post );
		$content = (string) $post->post_content;
		$text = wp_strip_all_tags( strip_shortcodes( $content ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ? get_bloginfo( 'charset' ) : 'UTF-8' );
		$render = self::rendered_page( $url );
		$context = array(
			'post'          => $post,
			'content_html'  => $content,
			'text'          => $text,
			'profile'       => $profile,
			'post_url'      => (string) $url,
			'rendered_html' => isset( $render['html'] ) ? $render['html'] : '',
			'render_error' => isset( $render['error'] ) ? $render['error'] : '',
		);
		$selected_modules = self::normalize_modules( $modules );
		$results = array();
		$evidence_issues = array();
		$claims = array();

		foreach ( IAAE_Rules::list_rules( true ) as $rule ) {
			$category = isset( $rule['category'] ) ? (string) $rule['category'] : '';
			$applies = isset( $rule['applies_to'] ) ? (array) $rule['applies_to'] : array();
			$profile_scoped = ! in_array( '*', $applies, true );
			if ( $profile_scoped && '' !== $profile && ! in_array( $profile, $applies, true ) ) {
				continue;
			}
			if ( $selected_modules && ! in_array( $category, $selected_modules, true ) ) {
				$result = array(
					'status' => 'insufficient',
					'score' => null,
					'confidence' => 'needs_human_review',
					'message' => 'این دسته در درخواست فعلی انتخاب نشده است؛ نتیجهٔ این گزارش جزئی را نباید ممیزی کامل دانست.',
					'evidence' => array(),
					'needs_human_review' => true,
					'rule_id' => (string) $rule['id'],
					'category' => $category,
					'severity' => isset( $rule['severity'] ) ? (string) $rule['severity'] : 'info',
					'weight' => isset( $rule['weight'] ) ? (float) $rule['weight'] : 1.0,
					'title' => isset( $rule['title'] ) ? (string) $rule['title'] : (string) $rule['id'],
					'suggestion' => isset( $rule['suggestion'] ) ? (string) $rule['suggestion'] : '',
				);
				$results[] = $result;
				$evidence_issues[] = $result;
				continue;
			}
			if ( $profile_scoped && '' === $profile ) {
				$result = array(
					'status'             => 'insufficient',
					'score'              => null,
					'confidence'         => 'needs_human_review',
					'message'            => 'پروفایل این نوع‌نوشته نگاشت نشده است؛ قانون پروفایل‌محور اجرا نشد.',
					'evidence'           => array(),
					'needs_human_review' => true,
				);
			} else {
				$result = IAAE_Evaluators::evaluate( $rule, $context );
			}
			$result['rule_id'] = (string) $rule['id'];
			$result['category'] = $category;
			$result['severity'] = isset( $rule['severity'] ) ? (string) $rule['severity'] : 'info';
			$result['weight'] = isset( $rule['weight'] ) ? (float) $rule['weight'] : 1.0;
			$result['title'] = isset( $rule['title'] ) ? (string) $rule['title'] : (string) $rule['id'];
			$result['suggestion'] = isset( $rule['suggestion'] ) ? (string) $rule['suggestion'] : '';
			$results[] = $result;
			if ( in_array( $result['status'], array( 'issue', 'warning', 'insufficient' ), true ) ) {
				$evidence_issues[] = $result;
			}
			if ( 'GEO-CLAIMS-01' === $rule['id'] && ! empty( $result['evidence'][0]['claims'] ) ) {
				$claims = $result['evidence'][0]['claims'];
			}
		}

		$scores = self::score( $results, $selected_modules );
		$now = current_time( 'mysql', true );
		$hash = hash( 'sha256', (string) $post->ID . "\0" . $post->post_title . "\0" . $content . "\0" . $post->post_modified_gmt );
		$summary = array(
			'overall' => array(
				'score'    => $scores['score'],
				'status'   => $scores['status'],
				'coverage' => $scores['coverage'],
			),
			'categories' => $scores['categories'],
			'counts'     => $scores['counts'],
			'todo'       => self::todo( $results ),
			'profile_mapping' => array(
				'profile'  => $profile,
				'mapped'   => '' !== $profile,
				'post_type' => (string) $post->post_type,
			),
		);
		$report_table = IAAE_Database::table( 'reports' );
		$location = IAAE_Profile_Mapper::location_fields( $post, $profile );
		$province = $location['province'];
		$county = $location['county'];
		global $wpdb;
		$transaction = false !== $wpdb->query( 'START TRANSACTION' );
		$inserted = self::db_insert(
			$report_table,
			array(
				'post_id'           => (int) $post->ID,
				'post_title'        => (string) $post->post_title,
				'post_url'          => (string) $url,
				'post_modified_gmt' => '0000-00-00 00:00:00' === $post->post_modified_gmt ? null : (string) $post->post_modified_gmt,
				'content_hash'      => $hash,
				'profile'           => (string) $profile,
				'province'          => $province,
				'county'            => $county,
				'score'             => null === $scores['score'] ? null : (float) $scores['score'],
				'status'            => (string) $scores['status'],
				'coverage'          => (float) $scores['coverage'],
				'engine_version'    => IAAE_VERSION,
				'rules_version'     => (string) $rules_health['rules_version'],
				'trigger_type'      => in_array( $trigger, array( 'manual', 'queue', 'auto' ), true ) ? $trigger : 'manual',
				'audited_at_gmt'    => $now,
				'audited_at_jalali' => self::jalali_datetime( $now ),
				'summary_json'      => wp_json_encode( $summary ),
			)
		);
		if ( ! $inserted ) {
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return new WP_Error( 'iaa_engine_error', 'ذخیرهٔ گزارش در جدول موتور ناموفق بود.', array( 'status' => 500 ) );
		}
		$report_id = (int) $inserted;
		$stored = self::store_scores( $report_id, $scores['categories'] )
			&& self::store_issues( $report_id, $evidence_issues )
			&& self::store_claims( $report_id, $claims );
		if ( ! $stored ) {
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			self::delete_report( $report_id );
			return new WP_Error( 'iaa_engine_error', 'ذخیرهٔ جزئیات گزارش در جدول‌های موتور ناموفق بود؛ گزارش ناقص حذف شد.', array( 'status' => 500 ) );
		}
		if ( $transaction && false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::delete_report( $report_id );
			return new WP_Error( 'iaa_engine_error', 'ثبت نهایی گزارش ناموفق بود.', array( 'status' => 500 ) );
		}

		self::prune_old_reports( (int) $post->ID );
		return self::get_report( $report_id );
	}

	/**
	 * Normalize category list.
	 *
	 * @param array $modules Modules.
	 * @return string[]
	 */
	private static function normalize_modules( $modules ) {
		$data = IAAE_Rules::all();
		$valid = isset( $data['category_weights'] ) ? array_keys( $data['category_weights'] ) : array();
		$modules = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $modules ) ) ) );
		return array_values( array_intersect( $modules, $valid ) );
	}

	/**
	 * Conservative same-host HTML fetch, disabled until explicitly enabled on staging.
	 *
	 * @param string $url Post permalink.
	 * @return array
	 */
	private static function rendered_page( $url ) {
		if ( ! IAAE_Database::get_setting( 'rendered_checks', false ) ) {
			return array( 'html' => '', 'error' => 'بررسی HTML رندرشده خاموش است؛ فقط post_content قابل بررسی است.' );
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		if ( '' === $host || '' === $home_host || $host !== $home_host ) {
			return array( 'html' => '', 'error' => 'URL رندرشده متعلق به میزبان سایت نیست؛ درخواست شبکه انجام نشد.' );
		}
		if ( ! function_exists( 'wp_safe_remote_get' ) ) {
			return array( 'html' => '', 'error' => 'HTTP API ایمن WordPress در دسترس نیست.' );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 8,
				'redirection'         => 0,
				'limit_response_size'  => 1048576,
				'reject_unsafe_urls'  => true,
				'headers'              => array( 'Accept' => 'text/html', 'User-Agent' => 'Iran-Audit-Engine/' . IAAE_VERSION ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'html' => '', 'error' => 'دریافت HTML ناموفق بود: ' . $response->get_error_code() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 300 <= $code && $code < 400 ) {
			return array( 'html' => '', 'error' => 'صفحه به نشانی دیگری redirect می‌شود؛ redirect بدون بازبینی میزبان دنبال نشد.' );
		}
		if ( 200 !== $code ) {
			return array( 'html' => '', 'error' => sprintf( 'HTML رندرشده پاسخ HTTP %d داد.', $code ) );
		}
		$content_type = strtolower( trim( (string) wp_remote_retrieve_header( $response, 'content-type' ) ) );
		$content_type = trim( explode( ';', $content_type )[0] );
		if ( ! in_array( $content_type, array( 'text/html', 'application/xhtml+xml' ), true ) ) {
			return array( 'html' => '', 'error' => 'پاسخ همان میزبان، نوع محتوای HTML ندارد.' );
		}
		$body = (string) wp_remote_retrieve_body( $response );
		if ( '' === $body || strlen( $body ) > 1048576 ) {
			return array( 'html' => '', 'error' => 'پاسخ HTML خالی یا بزرگ‌تر از سقف 1 MiB است.' );
		}
		return array( 'html' => $body, 'error' => '' );
	}

	/**
	 * Reproducible category/overall scoring per contract.
	 *
	 * @param array $results Rule results.
	 * @param array $selected_modules Requested categories; a partial selection cannot produce a verified article.
	 * @return array
	 */
	private static function score( $results, $selected_modules = array() ) {
		$rules_data       = IAAE_Rules::all();
		$category_weights = isset( $rules_data['category_weights'] ) ? (array) $rules_data['category_weights'] : array();
		$by_category      = array();
		$counts           = array( 'critical' => 0, 'major' => 0, 'minor' => 0, 'info' => 0, 'insufficient' => 0, 'needs_human_review' => 0 );

		foreach ( $results as $result ) {
			$category = isset( $result['category'] ) ? (string) $result['category'] : '';
			if ( '' === $category ) {
				continue;
			}
			if ( ! isset( $by_category[ $category ] ) ) {
				$by_category[ $category ] = array(
					'weighted_score' => 0.0,
					'weight_scored'  => 0.0,
					'weight_total'   => 0.0,
					'failed'         => 0,
					'passed'         => 0,
					'insufficient'   => 0,
					'counts'         => array( 'critical' => 0, 'major' => 0, 'minor' => 0, 'info' => 0 ),
				);
			}
			$weight = isset( $result['weight'] ) ? max( 0.0, (float) $result['weight'] ) : 1.0;
			// Every applicable rule belongs in the coverage denominator, even when the
			// evaluator cannot assess it because an input is missing.
			$by_category[ $category ]['weight_total'] += $weight;
			$status = isset( $result['status'] ) ? (string) $result['status'] : 'insufficient';
			if ( 'insufficient' === $status ) {
				$by_category[ $category ]['insufficient']++;
				$counts['insufficient']++;
			} elseif ( in_array( $status, array( 'verified', 'issue', 'warning' ), true ) ) {
				$score = isset( $result['score'] ) && is_numeric( $result['score'] ) ? min( 1.0, max( 0.0, (float) $result['score'] ) ) : 0.0;
				$by_category[ $category ]['weight_scored'] += $weight;
				$by_category[ $category ]['weighted_score'] += $weight * $score;
				if ( 'verified' === $status ) {
					$by_category[ $category ]['passed']++;
				} else {
					$by_category[ $category ]['failed']++;
					$severity = isset( $result['severity'] ) && isset( $counts[ $result['severity'] ] ) ? (string) $result['severity'] : 'info';
					$by_category[ $category ]['counts'][ $severity ]++;
					$counts[ $severity ]++;
				}
			} else {
				$by_category[ $category ]['insufficient']++;
				$counts['insufficient']++;
			}
			if ( ! empty( $result['needs_human_review'] ) ) {
				$counts['needs_human_review']++;
			}
		}

		$category_scores = array();
		$total_weighted  = 0.0;
		$total_weight    = 0.0;
		$coverage_weight = 0.0;
		$all_weight      = 0.0;
		$critical        = 0;
		$major           = 0;
		foreach ( $category_weights as $category => $category_weight ) {
			$category_weight = max( 0.0, (float) $category_weight );
			$stats = isset( $by_category[ $category ] ) ? $by_category[ $category ] : array(
				'weighted_score' => 0.0,
				'weight_scored'  => 0.0,
				'weight_total'   => 0.0,
				'failed'         => 0,
				'passed'         => 0,
				'insufficient'   => 0,
				'counts'         => array( 'critical' => 0, 'major' => 0, 'minor' => 0, 'info' => 0 ),
			);
			$category_score    = $stats['weight_scored'] > 0 ? 100 * $stats['weighted_score'] / $stats['weight_scored'] : null;
			$category_coverage = $stats['weight_total'] > 0 ? 100 * $stats['weight_scored'] / $stats['weight_total'] : 0.0;
			$critical_count    = (int) $stats['counts']['critical'];
			$major_count       = (int) $stats['counts']['major'];
			if ( $category_coverage < 60 || null === $category_score ) {
				$category_status = 'insufficient';
			} elseif ( $critical_count > 0 || $category_score < 70 ) {
				$category_status = 'issue';
			} elseif ( $major_count > 0 || $category_score < 90 ) {
				$category_status = 'warning';
			} else {
				$category_status = 'verified';
			}
			if ( $stats['weight_total'] > 0 ) {
				$category_scores[] = array(
					'id'                 => (string) $category,
					'score'              => null === $category_score ? null : round( $category_score, 2 ),
					'status'             => $category_status,
					'coverage'           => round( $category_coverage, 2 ),
					'counts'             => $stats['counts'],
					'rules_passed'       => (int) $stats['passed'],
					'rules_failed'       => (int) $stats['failed'],
					'rules_insufficient' => (int) $stats['insufficient'],
				);
				$all_weight += $category_weight;
				$coverage_weight += $category_weight * min( 100.0, $category_coverage );
			}
			if ( null !== $category_score ) {
				$total_weighted += $category_weight * $category_score;
				$total_weight += $category_weight;
			}
			$critical += $critical_count;
			$major += $major_count;
		}

		$score = $total_weight > 0 ? round( $total_weighted / $total_weight, 2 ) : null;
		$coverage = $all_weight > 0 ? round( $coverage_weight / $all_weight, 2 ) : 0.0;
		$selected_modules = array_values( array_intersect( (array) $selected_modules, array_keys( $category_weights ) ) );
		$partial_scope = false;
		if ( $selected_modules ) {
			foreach ( $results as $result ) {
				$category = isset( $result['category'] ) ? (string) $result['category'] : '';
				if ( '' !== $category && ! in_array( $category, $selected_modules, true ) ) {
					$partial_scope = true;
					break;
				}
			}
		}
		if ( $partial_scope || $coverage < 60 || null === $score ) {
			$status = 'insufficient';
		} elseif ( $critical > 0 || $score < 70 ) {
			$status = 'issue';
		} elseif ( $major > 0 || $score < 90 ) {
			$status = 'warning';
		} else {
			$status = 'verified';
		}
		return array( 'score' => $score, 'coverage' => $coverage, 'status' => $status, 'categories' => $category_scores, 'counts' => $counts );
	}

	/**
	 * Insert report child rows using fixed table names.
	 *
	 * @param string $table Table name.
	 * @param array  $data Row.
	 * @return int|false
	 */
	private static function db_insert( $table, $data ) {
		global $wpdb;
		return false === $wpdb->insert( $table, $data ) ? false : (int) $wpdb->insert_id;
	}

	/** Keep the configured number of newest reports and delete only Engine-owned children. */
	private static function prune_old_reports( $post_id ) {
		global $wpdb;
		$keep = min( 100, max( 1, absint( IAAE_Database::get_setting( 'retention_reports', 10 ) ) ) );
		$reports = IAAE_Database::table( 'reports' );
		$old_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$reports} WHERE post_id=%d AND id <= (SELECT id FROM {$reports} WHERE post_id=%d ORDER BY id DESC LIMIT 1 OFFSET %d)",
				(int) $post_id,
				(int) $post_id,
				$keep
			)
		);
		$old_ids = array_values( array_filter( array_map( 'absint', (array) $old_ids ) ) );
		if ( ! $old_ids ) { return true; }
		$placeholders = implode( ',', array_fill( 0, count( $old_ids ), '%d' ) );
		$transaction = false !== $wpdb->query( 'START TRANSACTION' );
		foreach ( array( 'issues', 'category_scores', 'claims', 'similarities' ) as $suffix ) {
			$table = IAAE_Database::table( $suffix );
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE report_id IN ({$placeholders})", $old_ids ) );
			if ( false === $deleted ) {
				if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
				return false;
			}
		}
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$reports} WHERE id IN ({$placeholders}) AND post_id=%d", array_merge( $old_ids, array( (int) $post_id ) ) ) );
		if ( false === $deleted ) {
			if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
			return false;
		}
		if ( $transaction && false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		return true;
	}

	private static function store_scores( $report_id, $categories ) {
		global $wpdb;
		$table = IAAE_Database::table( 'category_scores' );
		foreach ( $categories as $category ) {
			$counts_record = (array) $category['counts'];
			$counts_record['_rules_passed'] = isset( $category['rules_passed'] ) ? (int) $category['rules_passed'] : 0;
			$counts_record['_rules_failed'] = isset( $category['rules_failed'] ) ? (int) $category['rules_failed'] : 0;
			$counts_record['_rules_insufficient'] = isset( $category['rules_insufficient'] ) ? (int) $category['rules_insufficient'] : 0;
			$inserted = $wpdb->insert(
				$table,
				array(
					'report_id' => (int) $report_id,
					'category' => (string) $category['id'],
					'score' => null === $category['score'] ? null : (float) $category['score'],
					'status' => (string) $category['status'],
					'coverage' => (float) $category['coverage'],
					'counts_json' => wp_json_encode( $counts_record ),
				),
				array( '%d', '%s', '%f', '%s', '%f', '%s' )
			);
			if ( false === $inserted ) { return false; }
		}
		return true;
	}

	private static function store_issues( $report_id, $issues ) {
		global $wpdb;
		$table = IAAE_Database::table( 'issues' );
		foreach ( $issues as $issue ) {
			$evidence = isset( $issue['evidence'] ) && is_array( $issue['evidence'] ) ? $issue['evidence'] : array();
			if ( in_array( $issue['status'], array( 'issue', 'warning' ), true ) && empty( $evidence ) ) {
				return false;
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'report_id' => (int) $report_id,
					'rule_id' => (string) $issue['rule_id'],
					'category' => (string) $issue['category'],
					'severity' => (string) $issue['severity'],
					'confidence' => (string) $issue['confidence'],
					'status' => (string) $issue['status'],
					'message' => (string) $issue['message'],
					'evidence_json' => wp_json_encode( $evidence ),
					'suggestion' => (string) $issue['suggestion'],
					'needs_human_review' => empty( $issue['needs_human_review'] ) ? 0 : 1,
					'created_at_gmt' => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( false === $inserted ) { return false; }
		}
		return true;
	}

	private static function store_claims( $report_id, $claims ) {
		global $wpdb;
		$table = IAAE_Database::table( 'claims' );
		foreach ( (array) $claims as $claim ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'report_id' => (int) $report_id,
					'kind' => isset( $claim['kind'] ) ? (string) $claim['kind'] : 'other',
					'claim_value' => isset( $claim['value'] ) ? (string) $claim['value'] : '',
					'context_json' => wp_json_encode( $claim ),
					'has_source' => 0,
					'review_state' => 'pending',
				),
				array( '%d', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( false === $inserted ) { return false; }
		}
		return true;
	}

	private static function delete_report( $report_id ) {
		global $wpdb;
		foreach ( array( 'claims', 'issues', 'category_scores' ) as $suffix ) {
			$wpdb->delete( IAAE_Database::table( $suffix ), array( 'report_id' => (int) $report_id ), array( '%d' ) );
		}
		$wpdb->delete( IAAE_Database::table( 'reports' ), array( 'id' => (int) $report_id ), array( '%d' ) );
	}

	private static function todo( $results ) {
		$todo = array();
		foreach ( $results as $result ) {
			if ( 'verified' === $result['status'] ) {
				continue;
			}
			$todo[] = array(
				'rule_id' => $result['rule_id'],
				'status' => $result['status'],
				'message' => $result['message'],
				'severity' => $result['severity'],
				'needs_human_review' => $result['needs_human_review'],
			);
		}
		return $todo;
	}

	/**
	 * Read one report and its child rows.
	 *
	 * @param int $report_id Report ID.
	 * @return array|null
	 */
	public static function get_report( $report_id ) {
		global $wpdb;
		$table = IAAE_Database::table( 'reports' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d LIMIT 1", (int) $report_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$summary = json_decode( $row['summary_json'], true );
		$summary = is_array( $summary ) ? $summary : array();
		$summary['report_id'] = (int) $row['id'];
		$summary['post_id'] = (int) $row['post_id'];
		$summary['post_title'] = (string) $row['post_title'];
		$summary['post_url'] = (string) $row['post_url'];
		$summary['post_modified_gmt'] = self::iso_datetime( $row['post_modified_gmt'] );
		$summary['content_hash'] = 'sha256:' . (string) $row['content_hash'];
		$summary['profile'] = (string) $row['profile'];
		$summary['province'] = (string) $row['province'];
		$summary['county'] = (string) $row['county'];
		$summary['audited_at'] = self::iso_datetime( $row['audited_at_gmt'] );
		$summary['audited_at_jalali'] = (string) $row['audited_at_jalali'];
		$summary['trigger'] = (string) $row['trigger_type'];
		$summary['engine_version'] = (string) $row['engine_version'];
		$summary['rules_version'] = (string) $row['rules_version'];
		$summary['overall'] = array(
			'score' => null === $row['score'] ? null : (float) $row['score'],
			'status' => (string) $row['status'],
			'coverage' => (float) $row['coverage'],
		);
		$summary['issues'] = self::get_issues( (int) $row['id'] );
		$summary['categories'] = self::get_category_scores( (int) $row['id'] );
		return $summary;
	}

	private static function get_issues( $report_id ) {
		global $wpdb;
		$table = IAAE_Database::table( 'issues' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d ORDER BY FIELD(status,'issue','warning','insufficient'),FIELD(severity,'critical','major','minor','info'),id ASC", (int) $report_id ), ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'issue_id' => 'iss_' . (int) $row['id'],
				'rule_id' => (string) $row['rule_id'],
				'category' => (string) $row['category'],
				'severity' => (string) $row['severity'],
				'confidence' => (string) $row['confidence'],
				'status' => (string) $row['status'],
				'message' => (string) $row['message'],
				'evidence' => json_decode( $row['evidence_json'], true ) ?: array(),
				'suggestion' => (string) $row['suggestion'],
				'needs_human_review' => (bool) $row['needs_human_review'],
			);
		}
		return $out;
	}

	private static function get_category_scores( $report_id ) {
		global $wpdb;
		$table = IAAE_Database::table( 'category_scores' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id=%d ORDER BY id ASC", (int) $report_id ), ARRAY_A );
		$out = array();
		foreach ( (array) $rows as $row ) {
			$counts = json_decode( $row['counts_json'], true );
			$counts = is_array( $counts ) ? $counts : array();
			$rules_passed = isset( $counts['_rules_passed'] ) ? (int) $counts['_rules_passed'] : null;
			$rules_failed = isset( $counts['_rules_failed'] ) ? (int) $counts['_rules_failed'] : null;
			$rules_insufficient = isset( $counts['_rules_insufficient'] ) ? (int) $counts['_rules_insufficient'] : null;
			unset( $counts['_rules_passed'], $counts['_rules_failed'], $counts['_rules_insufficient'] );
			$category = array(
				'id' => (string) $row['category'],
				'score' => null === $row['score'] ? null : (float) $row['score'],
				'status' => (string) $row['status'],
				'coverage' => (float) $row['coverage'],
				'counts' => $counts,
			);
			if ( null !== $rules_passed ) { $category['rules_passed'] = $rules_passed; }
			if ( null !== $rules_failed ) { $category['rules_failed'] = $rules_failed; }
			if ( null !== $rules_insufficient ) { $category['rules_insufficient'] = $rules_insufficient; }
			$out[] = $category;
		}
		return $out;
	}

	private static function iso_datetime( $mysql_gmt ) {
		if ( ! $mysql_gmt || '0000-00-00 00:00:00' === $mysql_gmt ) {
			return null;
		}
		$timestamp = strtotime( (string) $mysql_gmt . ' UTC' );
		return false === $timestamp ? null : gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	private static function jalali_datetime( $mysql_gmt ) {
		$timestamp = strtotime( $mysql_gmt . ' UTC' );
		$value = apply_filters( 'iaae_jalali_datetime', '', $timestamp );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}
}
