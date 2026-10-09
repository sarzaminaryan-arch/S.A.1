<?php
/**
 * Action-plan generator: turns stored rule results into a prioritized do-this list.
 * Read-only: consumes report arrays and never writes to WordPress or engine tables.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Actions {
	/**
	 * Build a prioritized action plan for one full report array.
	 *
	 * @param array $report Report from IAAE_Auditor::get_report().
	 * @return array
	 */
	public static function for_report( $report ) {
		$report  = is_array( $report ) ? $report : array();
		$issues  = isset( $report['issues'] ) && is_array( $report['issues'] ) ? $report['issues'] : array();
		$actions = array();
		$blocked_rendered = array();
		$blocked_scope    = array();

		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$status = isset( $issue['status'] ) ? (string) $issue['status'] : '';
			if ( 'issue' === $status || 'warning' === $status ) {
				$actions[] = self::action_from_issue( $issue, $report );
			} elseif ( 'insufficient' === $status ) {
				$rule_id = isset( $issue['rule_id'] ) ? (string) $issue['rule_id'] : '';
				if ( self::needs_rendered_html( $rule_id, $issue ) ) {
					$blocked_rendered[] = $rule_id;
				} else {
					$blocked_scope[] = $rule_id;
				}
			}
		}

		usort(
			$actions,
			static function ( $a, $b ) {
				$rank = array( 'critical' => 0, 'major' => 1, 'minor' => 2, 'info' => 3 );
				$ra   = isset( $rank[ $a['severity'] ] ) ? $rank[ $a['severity'] ] : 4;
				$rb   = isset( $rank[ $b['severity'] ] ) ? $rank[ $b['severity'] ] : 4;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				return strcmp( (string) $a['rule_id'], (string) $b['rule_id'] );
			}
		);
		foreach ( $actions as $index => $action ) {
			$actions[ $index ]['priority'] = $index + 1;
		}

		$by_severity = array( 'critical' => 0, 'major' => 0, 'minor' => 0, 'info' => 0 );
		foreach ( $actions as $action ) {
			if ( isset( $by_severity[ $action['severity'] ] ) ) {
				$by_severity[ $action['severity'] ]++;
			}
		}
		$blocked_rendered = array_values( array_unique( $blocked_rendered ) );
		$blocked_scope    = array_values( array_unique( $blocked_scope ) );
		$blocked          = array();
		if ( $blocked_rendered ) {
			$blocked[] = array(
				'kind'  => 'rendered_checks',
				'title' => 'نیازمند فعال‌سازی HTML رندرشده',
				'hint'  => 'در «ابزارها ← تنظیمات موتور» گزینهٔ «دریافت HTML رندرشده» را فقط پس از آزمون staging فعال کن و دوباره ممیزی بگیر.',
				'rules' => $blocked_rendered,
			);
		}
		if ( $blocked_scope ) {
			$blocked[] = array(
				'kind'  => 'out_of_scope',
				'title' => 'خارج از پوشش خودکار موتور',
				'hint'  => 'این قوانین در نسخهٔ فعلی خودکار سنجیده نمی‌شوند؛ روی امتیاز اثر نمی‌گذارند و اقدام دستی لازم نیست.',
				'rules' => $blocked_scope,
			);
		}

		$overall = isset( $report['overall'] ) && is_array( $report['overall'] ) ? $report['overall'] : array();
		return array(
			'post_id'       => isset( $report['post_id'] ) ? (int) $report['post_id'] : 0,
			'post_title'    => isset( $report['post_title'] ) ? (string) $report['post_title'] : '',
			'post_url'      => isset( $report['post_url'] ) ? (string) $report['post_url'] : '',
			'profile'       => isset( $report['profile'] ) ? (string) $report['profile'] : '',
			'profile_label' => self::profile_label( isset( $report['profile'] ) ? (string) $report['profile'] : '' ),
			'province'      => isset( $report['province'] ) ? (string) $report['province'] : '',
			'county'        => isset( $report['county'] ) ? (string) $report['county'] : '',
			'report_id'     => isset( $report['report_id'] ) ? (int) $report['report_id'] : 0,
			'score'         => isset( $overall['score'] ) && null !== $overall['score'] ? (float) $overall['score'] : null,
			'status'        => isset( $overall['status'] ) ? (string) $overall['status'] : '',
			'coverage'      => isset( $overall['coverage'] ) ? (float) $overall['coverage'] : 0.0,
			'rules_version' => isset( $report['rules_version'] ) ? (string) $report['rules_version'] : '',
			'summary'       => array(
				'total'       => count( $actions ),
				'by_severity' => $by_severity,
				'blocked'     => count( $blocked_rendered ) + count( $blocked_scope ),
				'headline'    => self::headline( count( $actions ), $by_severity, count( $blocked_rendered ) + count( $blocked_scope ) ),
			),
			'actions'       => $actions,
			'blocked'       => $blocked,
		);
	}

	/**
	 * Aggregate top actions across latest reports, optionally filtered.
	 *
	 * @param string $province Province name filter (partial match).
	 * @param string $profile  Profile filter (exact match).
	 * @param int    $limit    Maximum rule groups.
	 * @return array
	 */
	public static function aggregate( $province = '', $profile = '', $limit = 20 ) {
		global $wpdb;
		$latest = IAAE_REST::latest_reports_sql();
		$table  = IAAE_Database::table( 'issues' );
		$where  = array( "i.status IN ('issue','warning')" );
		$params = array();
		if ( '' !== $province ) {
			$where[]  = 'latest.province LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $province ) . '%';
		}
		if ( '' !== $profile ) {
			$where[]  = 'latest.profile=%s';
			$params[] = $profile;
		}
		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$select_sql = "SELECT i.rule_id,i.category,i.severity,latest.post_id,latest.post_title,latest.score FROM {$latest} latest INNER JOIN {$table} i ON i.report_id=latest.id {$where_sql} ORDER BY i.rule_id ASC,latest.score ASC,latest.post_id ASC LIMIT 5000";
		$rows      = $params ? $wpdb->get_results( $wpdb->prepare( $select_sql, $params ), ARRAY_A ) : $wpdb->get_results( $select_sql, ARRAY_A );
		$groups = array();
		foreach ( (array) $rows as $row ) {
			$rule_id = (string) $row['rule_id'];
			if ( ! isset( $groups[ $rule_id ] ) ) {
				$groups[ $rule_id ] = array(
					'rule_id'     => $rule_id,
					'category'    => (string) $row['category'],
					'severity'    => (string) $row['severity'],
					'occurrences' => 0,
					'posts'       => array(),
					'post_ids'    => array(),
				);
			}
			$groups[ $rule_id ]['occurrences']++;
			$post_id = (int) $row['post_id'];
			if ( ! isset( $groups[ $rule_id ]['post_ids'][ $post_id ] ) ) {
				$groups[ $rule_id ]['post_ids'][ $post_id ] = true;
				if ( count( $groups[ $rule_id ]['posts'] ) < 20 ) {
					$groups[ $rule_id ]['posts'][] = array(
						'post_id'    => $post_id,
						'post_title' => (string) $row['post_title'],
						'score'      => null === $row['score'] ? null : (float) $row['score'],
					);
				}
			}
		}

		$rank = array( 'critical' => 0, 'major' => 1, 'minor' => 2, 'info' => 3 );
		$list = array_values( $groups );
		usort(
			$list,
			static function ( $a, $b ) use ( $rank ) {
				$ra = isset( $rank[ $a['severity'] ] ) ? $rank[ $a['severity'] ] : 4;
				$rb = isset( $rank[ $b['severity'] ] ) ? $rank[ $b['severity'] ] : 4;
				if ( $ra !== $rb ) {
					return $ra - $rb;
				}
				if ( (int) $a['occurrences'] !== (int) $b['occurrences'] ) {
					return (int) $b['occurrences'] - (int) $a['occurrences'];
				}
				return strcmp( (string) $a['rule_id'], (string) $b['rule_id'] );
			}
		);
		$limit = min( 100, max( 1, (int) $limit ) );
		$list  = array_slice( $list, 0, $limit );

		$rules_data     = IAAE_Rules::all();
		$severity_fa    = isset( $rules_data['severity_labels'] ) ? (array) $rules_data['severity_labels'] : array();
		$total          = 0;
		$by_severity    = array( 'critical' => 0, 'major' => 0, 'minor' => 0, 'info' => 0 );
		$count_sql       = "SELECT COUNT(*) FROM {$latest} latest {$where_sql}";
		$reports_scanned = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );
		foreach ( $list as $index => $group ) {
			$rule      = IAAE_Rules::find( $group['rule_id'] );
			$howto     = self::howto( $group['rule_id'], array(), '' );
			$list[ $index ]['priority']       = $index + 1;
			$list[ $index ]['rule_title']     = $rule && isset( $rule['title'] ) ? (string) $rule['title'] : $group['rule_id'];
			$list[ $index ]['category_label'] = self::category_label( $group['category'] );
			$list[ $index ]['severity_label'] = isset( $severity_fa[ $group['severity'] ] ) ? (string) $severity_fa[ $group['severity'] ] : $group['severity'];
			$list[ $index ]['action_title']   = $howto[0];
			$list[ $index ]['action_detail']  = $howto[1];
			$list[ $index ]['effort']         = self::effort( $group['rule_id'] );
			$list[ $index ]['suggestion']     = $rule && isset( $rule['suggestion'] ) ? (string) $rule['suggestion'] : '';
			$list[ $index ]['reports']        = count( $group['post_ids'] );
			unset( $list[ $index ]['post_ids'] );
			$total += (int) $group['occurrences'];
			if ( isset( $by_severity[ $group['severity'] ] ) ) {
				$by_severity[ $group['severity'] ] += (int) $group['occurrences'];
			}
		}
		return array(
			'filters'         => array( 'province' => (string) $province, 'profile' => (string) $profile ),
			'reports_scanned' => $reports_scanned,
			'total_actions'   => $total,
			'by_severity'     => $by_severity,
			'groups'          => $list,
		);
	}

	/**
	 * Convert one stored issue into one concrete action.
	 *
	 * @param array $issue  Issue row.
	 * @param array $report Parent report.
	 * @return array
	 */
	private static function action_from_issue( $issue, $report ) {
		$rule_id  = isset( $issue['rule_id'] ) ? (string) $issue['rule_id'] : '';
		$rule     = IAAE_Rules::find( $rule_id );
		$rules_data = IAAE_Rules::all();
		$severity_labels = isset( $rules_data['severity_labels'] ) ? (array) $rules_data['severity_labels'] : array();
		$severity = isset( $issue['severity'] ) ? (string) $issue['severity'] : 'info';
		$evidence = isset( $issue['evidence'] ) && is_array( $issue['evidence'] ) && isset( $issue['evidence'][0] ) && is_array( $issue['evidence'][0] ) ? $issue['evidence'][0] : array();
		$message  = isset( $issue['message'] ) ? (string) $issue['message'] : '';
		$howto    = self::howto( $rule_id, $evidence, $message );
		return array(
			'rule_id'        => $rule_id,
			'rule_title'     => $rule && isset( $rule['title'] ) ? (string) $rule['title'] : $rule_id,
			'category'       => isset( $issue['category'] ) ? (string) $issue['category'] : '',
			'category_label' => self::category_label( isset( $issue['category'] ) ? (string) $issue['category'] : '' ),
			'severity'       => $severity,
			'severity_label' => isset( $severity_labels[ $severity ] ) ? (string) $severity_labels[ $severity ] : $severity,
			'status'         => isset( $issue['status'] ) ? (string) $issue['status'] : '',
			'title'          => $howto[0],
			'detail'         => $howto[1],
			'suggestion'     => isset( $issue['suggestion'] ) ? (string) $issue['suggestion'] : '',
			'effort'         => self::effort( $rule_id ),
		);
	}

	/**
	 * Imperative title + concrete how-to for a rule, enriched with evidence numbers.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $evidence First evidence item (may be empty for aggregates).
	 * @param string $message  Stored issue message.
	 * @return array{0:string,1:string}
	 */
	private static function howto( $rule_id, $evidence, $message = '' ) {
		$prefix = '' !== $message ? $message . ' ' : '';
		switch ( $rule_id ) {
			case 'SEO-TITLE-01':
				return array( 'عنوان سئو را بازنویسی کن', $prefix . 'عنوان باید توصیفی، یکتا و شامل نام مکان باشد؛ طول را در بازهٔ ۲۵ تا ۶۵ نویسه نگه دار.' );
			case 'SEO-META-01':
				return array( 'متا دیسکریپشن بنویس', $prefix . 'یک خلاصهٔ یکتا و جذاب ۷۰ تا ۱۶۰ نویسه‌ای از محتوای همین صفحه بنویس.' );
			case 'SEO-H1-01':
				return array( 'سرتیتر H1 را یکتا کن', $prefix . 'در رندر نهایی فقط یک H1 بماند و دقیقاً موضوع صفحه (نام مکان) باشد.' );
			case 'SEO-HEADING-HIER-01':
				return array( 'پرش سطح سرتیتر را برطرف کن', $prefix . 'ترتیب سرتیترها را پیوسته کن: بعد از H2 فقط H3 بیاید، نه H4.' );
			case 'SEO-SLUG-01':
				$slug = self::short( $evidence, 'text' );
				return array( 'نامک را کوتاه و توصیفی کن', $prefix . ( '' !== $slug ? 'نامک فعلی «' . $slug . '» است؛ ' : '' ) . 'از حروف یکدست و خط تیره استفاده کن و اگر صفحه منتشر شده، حتماً ریدایرکت ۳۰۱ از نامک قبلی بگذار.' );
			case 'SEO-INDEX-01':
				return array( 'مشکل ایندکس را فوری برطرف کن', $prefix . 'noindex ناخواسته را بردار و canonical را دقیقاً برابر نشانی همین صفحه کن؛ تا رفع نشود صفحه در گوگل نمی‌نشیند.' );
			case 'SEO-KEYWORD-01':
				$keyword = self::short( $evidence, 'text' );
				return array( 'کلمهٔ کانونی را طبیعی پخش کن', $prefix . ( '' !== $keyword ? 'عبارت «' . $keyword . '» را ' : 'عبارت کانونی را ' ) . 'در تیتر، مقدمه و یکی دو سرتیتر به‌صورت طبیعی بیاور و تکرار مصنوعی را کم کن.' );
			case 'SEO-SCHEMA-01':
				$missing = isset( $evidence['missing_types'] ) && is_array( $evidence['missing_types'] ) ? implode( '، ', array_map( 'strval', $evidence['missing_types'] ) ) : '';
				$invalid = isset( $evidence['invalid_blocks'] ) && is_array( $evidence['invalid_blocks'] ) && $evidence['invalid_blocks'] ? 'بلوک‌های شمارهٔ ' . implode( '، ', array_map( 'strval', $evidence['invalid_blocks'] ) ) . ' خراب‌اند؛ ' : '';
				return array( 'اسکیمای JSON-LD را کامل کن', $prefix . $invalid . ( '' !== $missing ? 'نوع‌های گمشده (' . $missing . ') را اضافه کن.' : 'بلوک JSON-LD معتبر و همخوان با محتوا اضافه یا اصلاح کن.' ) );
			case 'STR-WORDS-01':
				$count = self::number( $evidence, 'word_count' );
				$min   = self::number( $evidence, 'min_words' );
				$gap   = ( null !== $count && null !== $min && $min > $count ) ? 'متن ' . $count . ' کلمه است و حداقل پروفایل ' . $min . ' است؛ ' . ( $min - $count ) . ' کلمه کم داری. ' : $prefix;
				return array( 'حجم متن را به حد پروفایل برسان', $gap . 'بخش‌های ناقص چک‌لیست پروفایل را کامل کن، نه حجم‌افزایی خشک.' . self::completion_tail() );
			case 'STR-PARA-01':
				$words = self::number( $evidence, 'word_count' );
				$para  = isset( $evidence['location'] ) && is_array( $evidence['location'] ) && isset( $evidence['location']['paragraph'] ) ? (int) $evidence['location']['paragraph'] : null;
				return array( 'پاراگراف یا جملهٔ بلند را بشکن', $prefix . ( null !== $para ? 'پاراگراف شمارهٔ ' . $para . ( null !== $words ? ' (' . $words . ' کلمه)' : '' ) . ' را ' : 'مورد بلند را ' ) . 'به دو سه پاراگراف کوتاه‌تر یا لیست تبدیل کن.' );
			case 'STR-INTRO-OUTRO-01':
				return array( 'مقدمه و جمع‌بندی اضافه کن', $prefix . 'یک مقدمهٔ کوتاه (موضوع + چرا مهم است) در ابتدا و یک جمع‌بندی (نکته‌های کلیدی) در انتها بنویس.' );
			case 'STR-HEADING-DENSITY-01':
				$between = self::number( $evidence, 'words_between_headings' );
				return array( 'سرتیتر میانی اضافه کن', $prefix . ( null !== $between ? $between . ' کلمه متن بدون سرتیتر داری؛ ' : '' ) . 'یک سرتیتر توصیفی وسط بخش بلند بگذار.' );
			case 'FA-CHARSET-01':
				return array( 'ی/ک و ارقام را یکدست کن', $prefix . 'ی و ک عربی را به فارسی تبدیل کن و همهٔ ارقام متن را یکدست (ترجیحاً فارسی) کن.' );
			case 'FA-HALFSPACE-01':
				$sample = self::short( $evidence, 'text' );
				return array( 'نیم‌فاصله‌ها را اصلاح کن', $prefix . ( '' !== $sample ? 'در «' . $sample . '» و موارد مشابه، ' : '' ) . 'فاصله را با نیم‌فاصله جایگزین کن (می‌شود، کتاب‌ها).' );
			case 'FA-PUNCT-01':
				$sample = self::short( $evidence, 'text' );
				return array( 'فاصلهٔ علائم نگارشی را اصلاح کن', $prefix . 'علائم (، ؛ ؟ ! : .) به کلمهٔ قبل می‌چسبند و بعدشان یک فاصله می‌آید' . ( '' !== $sample ? '؛ مورد «' . $sample . '» را اصلاح کن.' : '.' ) );
			case 'FA-REPEAT-01':
				$word  = self::short( $evidence, 'text' );
				$count = self::number( $evidence, 'count' );
				$para  = isset( $evidence['location'] ) && is_array( $evidence['location'] ) && isset( $evidence['location']['paragraph'] ) ? (int) $evidence['location']['paragraph'] : null;
				return array( 'تکرار واژه را کم کن', $prefix . ( '' !== $word ? 'واژهٔ «' . $word . '»' . ( null !== $count ? ' ' . $count . ' بار' : '' ) . ( null !== $para ? ' در پاراگراف ' . $para : '' ) . ' تکرار شده؛ ' : '' ) . 'با مترادف یا بازنویسی جمله متنوعش کن.' );
			case 'FA-SPELL-01':
				$wrong = self::short( $evidence, 'text' );
				$right = self::short( $evidence, 'suggestion' );
				return array( 'غلط واژه‌نامه‌ای را اصلاح کن', $prefix . ( '' !== $wrong ? '«' . $wrong . '» را' . ( '' !== $right ? ' به «' . $right . '»' : '' ) . ' اصلاح کن.' : 'املای صحیح طبق واژه‌نامه را جایگزین کن.' ) );
			case 'FA-TONE-01':
				$phrase = self::short( $evidence, 'text' );
				return array( 'جملهٔ کلیشه‌ای را بازنویسی کن', $prefix . ( '' !== $phrase ? 'عبارت «' . $phrase . '» کلیشه‌ای است؛ ' : '' ) . 'با جزئیات واقعی همین مکان و لحن طبیعی بازنویسیش کن.' );
			case 'DUP-INTERNAL-01':
				return array( 'بخش مشابه با مقالهٔ دیگر را بازنویسی کن', $prefix . 'بخش‌های مشابه را با اطلاعات اختصاصی همین مکان بازنویسی کن تا دو مقاله رقیب هم نشوند.' );
			case 'DUP-CANNIBAL-01':
				return array( 'هم‌نوع‌خواری جست‌وجو را رفع کن', $prefix . 'مقاله‌ها را ادغام کن یا هدف جست‌وجوی هر کدام را با تیتر و مقدمهٔ متمایز جدا کن.' );
			case 'DUP-TEMPLATE-01':
				return array( 'پاراگراف قالبی را اختصاصی کن', $prefix . 'متن قالبی مشترک بین شهرها را با اطلاعات اختصاصی همین شهر جایگزین کن.' );
			case 'DUP-THIN-01':
				return array( 'به متن عمق بده', $prefix . 'جزئیات مشخص و تجربی (نشانی، ساعت، هزینه، تجربهٔ بازدید) اضافه کن و پرگویی کلی را حذف کن.' . self::completion_tail() );
			case 'LNK-INT-01':
				$count = self::number( $evidence, 'count' );
				return array( 'لینک داخلی بده', $prefix . ( null !== $count ? 'فقط ' . $count . ' لینک داخلی داری؛ ' : '' ) . 'به استان، شهرستان و جاذبه‌های اطراف لینک مرتبط بده (بازهٔ داخلی ۳ تا ۴۰).' );
			case 'LNK-ANCHOR-01':
				$anchor = self::short( $evidence, 'text' );
				return array( 'متن لینک را توصیفی کن', $prefix . ( '' !== $anchor ? 'به‌جای «' . $anchor . '» ' : '' ) . 'از نام مقصد لینک استفاده کن.' );
			case 'LNK-ORPHAN-01':
				return array( 'برای صفحه لینک ورودی بساز', $prefix . 'از صفحهٔ استان، شهرستان یا مقالات مرتبط به این صفحه لینک بده تا یتیم نماند.' );
			case 'LNK-EXT-01':
				return array( 'لینک بیرونی شکسته را جایگزین کن', $prefix . 'لینک شکسته را با منبع معتبر جایگزین یا حذف کن.' );
			case 'LNK-REL-01':
				$href = self::short( $evidence, 'href' );
				return array( 'لینک خراب یا ناامن را اصلاح کن', $prefix . ( '' !== $href ? 'لینک «' . $href . '»: ' : '' ) . 'href خالی یا اجرایی را اصلاح کن و به لینک‌های _blank مقدار rel="noopener" بده.' );
			case 'MED-NEED-01':
				$images   = self::number( $evidence, 'images_in_body' );
				$required = self::number( $evidence, 'required_by_ratio' );
				$words    = self::number( $evidence, 'word_count' );
				return array( 'تصویر مرتبط اضافه کن', $prefix . ( null !== $words && null !== $images ? 'متن ' . $words . ' کلمه با ' . $images . ' تصویر است' . ( null !== $required ? ' (نیاز نسبی: ' . $required . ')' : '' ) . '؛ ' : '' ) . 'در بخش‌های طولانی بدون تصویر، تصویر مرتبط بگذار و تصویر شاخص را هم تنظیم کن.' );
			case 'MED-ALT-01':
				$index = isset( $evidence['location'] ) && is_array( $evidence['location'] ) && isset( $evidence['location']['image_index'] ) ? (int) $evidence['location']['image_index'] : null;
				return array( 'متن جایگزین تصاویر را بنویس', $prefix . ( null !== $index ? 'برای تصویر شمارهٔ ' . $index . ' ' : '' ) . 'یک توضیح کوتاه و دقیق (نام مکان + سوژه) بنویس.' );
			case 'MED-FORMAT-01':
				$index = isset( $evidence['location'] ) && is_array( $evidence['location'] ) && isset( $evidence['location']['image_index'] ) ? (int) $evidence['location']['image_index'] : null;
				return array( 'ابعاد و سلامت تصویر را اصلاح کن', $prefix . ( null !== $index ? 'تصویر شمارهٔ ' . $index . ': ' : '' ) . 'ابعاد width/height بده، حجم فایل را بهینه کن و اگر شکسته است جایگزینش کن.' );
			case 'GEO-CHECKLIST-01':
				return array( 'بخش‌های ضروری پروفایل را کامل کن', $prefix . 'بخش‌های ناقص چک‌لیست پروفایل (موقعیت، تاریخچه، دسترسی، بهترین زمان، اقامت، غذا و سوغات، جاذبه‌ها، سوالات) را تکمیل کن.' . self::completion_tail() );
			case 'GEO-MAP-01':
				return array( 'نقشه یا مختصات اضافه کن', $prefix . 'نقشهٔ جاسازی‌شده (گوگل، نشان یا بلد) یا جفت مختصات معتبر در فیلدهای متای صفحه بگذار.' );
			case 'GEO-NAMES-01':
				return array( 'نام استان و شهرستان را استاندارد کن', $prefix . 'نام را مطابق شکل استاندارد تقسیمات کشوری بنویس.' );
			case 'GEO-CONSISTENCY-01':
				return array( 'اعداد ناهماهنگ را یکدست کن', $prefix . 'مقادیر متفاوت یک واقعیت در متن را بررسی و در سراسر مقاله یکدست کن.' );
			case 'UX-CWV-01':
				return array( 'سرعت صفحه را بهینه کن', $prefix . 'تصاویر را فشرده و ابعاددار کن و اسکریپت/فونت مسدودکننده را کم کن.' );
			case 'UX-HTML-01':
				return array( 'شورت‌کد یا محتوای مختلط را اصلاح کن', $prefix . 'شورت‌کد رندرنشده را اصلاح و نشانی‌های http داخل صفحهٔ https را به https تبدیل کن.' );
			case 'UX-MOBILE-01':
				return array( 'سرریز موبایل را برطرف کن', $prefix . 'جدول و آی‌فریم را در ظرف قابل‌اسکرول افقی بگذار و عرض ثابت را حذف کن؛ در گوشی واقعی هم بررسی کن.' );
			case 'STR-PLACEHOLDER-01':
				$sample = self::short( $evidence, 'text' );
				return array( 'جای‌نگهدار را با اطلاعات واقعی پر کن', $prefix . ( '' !== $sample ? '«' . $sample . '» را ' : '' ) . 'با اطلاعات واقعی جایگزین کن.' . self::completion_tail() );
			case 'STR-EMPTY-SECTION-01':
				$heading = self::short( $evidence, 'heading' );
				$words   = self::number( $evidence, 'word_count' );
				return array( 'زیر سرتیتر خالی محتوا بنویس', $prefix . ( '' !== $heading ? 'سرتیتر «' . $heading . '»' . ( null !== $words ? ' فقط ' . $words . ' کلمه متن دارد' : ' خالی است' ) . '؛ ' : '' ) . 'زیرش محتوای کافی بنویس یا سرتیتر را حذف کن.' . self::completion_tail() );
			default:
				return array( 'اقدام: ' . $rule_id, $prefix . 'راهنمای قانون را در بخش قواعد ببین.' );
		}
	}

	/**
	 * Owner-ordered completion doctrine tail for content-completeness actions.
	 *
	 * @return string
	 */
	private static function completion_tail() {
		return ' اگر برای بخشی منبع معتبر در دسترس نبود، با منبع محلی (اهالی، کسبه، شبکه‌های اجتماعی) و لحن غیرقطعی بنویس؛ هیچ بخشی را خالی نگذار.';
	}

	/**
	 * Rough fix effort for prioritization display.
	 *
	 * @param string $rule_id Rule ID.
	 * @return string
	 */
	private static function effort( $rule_id ) {
		$low = array( 'FA-CHARSET-01', 'FA-HALFSPACE-01', 'FA-PUNCT-01', 'FA-REPEAT-01', 'FA-SPELL-01', 'SEO-SLUG-01', 'SEO-HEADING-HIER-01', 'SEO-META-01', 'SEO-KEYWORD-01', 'LNK-ANCHOR-01', 'LNK-REL-01', 'MED-ALT-01', 'MED-FORMAT-01', 'STR-INTRO-OUTRO-01' );
		if ( in_array( $rule_id, $low, true ) ) {
			return 'کم';
		}
		$high = array( 'STR-WORDS-01', 'GEO-CHECKLIST-01', 'GEO-MAP-01', 'GEO-NAMES-01', 'GEO-CONSISTENCY-01', 'DUP-INTERNAL-01', 'DUP-CANNIBAL-01', 'DUP-TEMPLATE-01', 'DUP-THIN-01', 'SEO-INDEX-01', 'STR-PLACEHOLDER-01', 'UX-CWV-01' );
		if ( in_array( $rule_id, $high, true ) ) {
			return 'زیاد';
		}
		return 'متوسط';
	}

	/**
	 * Whether an insufficient result is blocked only on rendered HTML checks.
	 *
	 * @param string $rule_id Rule ID.
	 * @param array  $issue   Issue row.
	 * @return bool
	 */
	private static function needs_rendered_html( $rule_id, $issue ) {
		$rendered_rules = array( 'SEO-TITLE-01', 'SEO-META-01', 'SEO-H1-01', 'SEO-HEADING-HIER-01', 'SEO-INDEX-01', 'SEO-SCHEMA-01', 'UX-HTML-01', 'UX-MOBILE-01' );
		if ( in_array( $rule_id, $rendered_rules, true ) ) {
			return true;
		}
		$message = isset( $issue['message'] ) ? (string) $issue['message'] : '';
		return false !== strpos( $message, 'رندرشده' );
	}

	/**
	 * One-line Persian headline for a plan.
	 *
	 * @param int   $total       Action count.
	 * @param array $by_severity Counts by severity.
	 * @param int   $blocked     Blocked rule count.
	 * @return string
	 */
	private static function headline( $total, $by_severity, $blocked ) {
		if ( 0 === (int) $total && 0 === (int) $blocked ) {
			return 'این نوشته اقدام بازی ندارد؛ همهٔ قوانین سنجیده‌شده پاس شده‌اند.';
		}
		$parts = array();
		if ( ! empty( $by_severity['critical'] ) ) {
			$parts[] = $by_severity['critical'] . ' بحرانی';
		}
		if ( ! empty( $by_severity['major'] ) ) {
			$parts[] = $by_severity['major'] . ' مهم';
		}
		if ( ! empty( $by_severity['minor'] ) ) {
			$parts[] = $by_severity['minor'] . ' جزئی';
		}
		if ( ! empty( $by_severity['info'] ) ) {
			$parts[] = $by_severity['info'] . ' اطلاع‌رسانی';
		}
		if ( 0 === (int) $total ) {
			$text = 'اقدام بازی ثبت نشده است';
		} else {
			$text = (int) $total . ' اقدام (' . implode( '، ', $parts ) . ')';
		}
		if ( (int) $blocked > 0 ) {
			$text .= '؛ ' . (int) $blocked . ' قانون خارج از پوشش خودکار';
		}
		return $text . '.';
	}

	/**
	 * Persian category label.
	 *
	 * @param string $category Category slug.
	 * @return string
	 */
	private static function category_label( $category ) {
		$labels = array(
			'technical_seo'  => 'سئوی فنی',
			'structure'      => 'ساختار',
			'persian_writing' => 'نگارش فارسی',
			'duplicate'      => 'هم‌پوشانی',
			'links'          => 'لینک‌ها',
			'media'          => 'رسانه',
			'tourism_geo'    => 'گردشگری و جغرافیا',
			'ux_technical'   => 'فنی و تجربهٔ کاربری',
		);
		return isset( $labels[ $category ] ) ? $labels[ $category ] : (string) $category;
	}

	/**
	 * Persian profile label.
	 *
	 * @param string $profile Profile slug.
	 * @return string
	 */
	private static function profile_label( $profile ) {
		if ( 'county' === $profile ) {
			return 'شهر';
		}
		$data     = IAAE_Rules::all();
		$profiles = isset( $data['profiles'] ) && is_array( $data['profiles'] ) ? $data['profiles'] : array();
		if ( isset( $profiles[ $profile ]['label'] ) ) {
			return (string) $profiles[ $profile ]['label'];
		}
		return (string) $profile;
	}

	/**
	 * Numeric evidence value or null.
	 *
	 * @param array  $evidence Evidence item.
	 * @param string $key      Key.
	 * @return int|null
	 */
	private static function number( $evidence, $key ) {
		if ( isset( $evidence[ $key ] ) && is_numeric( $evidence[ $key ] ) ) {
			return (int) $evidence[ $key ];
		}
		return null;
	}

	/**
	 * Short, single-line evidence string for display.
	 *
	 * @param array  $evidence Evidence item.
	 * @param string $key      Key.
	 * @return string
	 */
	private static function short( $evidence, $key ) {
		if ( ! isset( $evidence[ $key ] ) || ! is_scalar( $evidence[ $key ] ) ) {
			return '';
		}
		$value = trim( (string) preg_replace( '/\s+/u', ' ', (string) $evidence[ $key ] ) );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $value, 'UTF-8' ) > 80 ? mb_substr( $value, 0, 80, 'UTF-8' ) . '…' : $value;
		}
		return strlen( $value ) > 120 ? substr( $value, 0, 120 ) . '…' : $value;
	}
}
