<?php
/**
 * پیشخوان افزونه: اسکن دسته‌ای (۲۰تایی)، گزارش، حذف انتخابی/کامل،
 * جمله‌های تکراری، پشتیبان‌ها و تنظیمات الگوها.
 *
 * همهٔ عملیات نوشتنی: nonce + manage_options + بررسی edit_post برای هر نوشته.
 * هیچ نوشته‌ای حذف یا تغییر وضعیت نمی‌شود؛ فقط post_content با wp_update_post
 * عوض می‌شود (revision وردپرس + پشتیبان افزونه).
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_AI_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'start_scan', 'continue_scan', 'apply_selected', 'apply_all_delete', 'apply_duplicates', 'apply_duplicate_ai', 'restore_backup', 'save_settings', 'save_patterns', 'reset_patterns', 'export_csv' ) as $action ) {
			add_action( 'admin_post_sa_ai_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	public static function menu() {
		add_menu_page( 'پاک‌سازی جمله‌های هوش مصنوعی', 'پاک‌سازی AI', 'manage_options', 'sa-ai-cleaner', array( __CLASS__, 'render_scan' ), 'dashicons-editor-removeformatting', 58 );
		add_submenu_page( 'sa-ai-cleaner', 'اسکن و پاک‌سازی', 'اسکن و پاک‌سازی', 'manage_options', 'sa-ai-cleaner', array( __CLASS__, 'render_scan' ) );
		add_submenu_page( 'sa-ai-cleaner', 'جمله‌های تکراری', 'جمله‌های تکراری', 'manage_options', 'sa-ai-cleaner-duplicates', array( __CLASS__, 'render_duplicates' ) );
		add_submenu_page( 'sa-ai-cleaner', 'پشتیبان‌ها', 'پشتیبان‌ها', 'manage_options', 'sa-ai-cleaner-backups', array( __CLASS__, 'render_backups' ) );
		add_submenu_page( 'sa-ai-cleaner', 'تنظیمات و الگوها', 'تنظیمات و الگوها', 'manage_options', 'sa-ai-cleaner-settings', array( __CLASS__, 'render_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'sa-ai-cleaner' ) ) {
			return;
		}
		wp_enqueue_style( 'sa-ai-cleaner-admin', SA_AI_CLEANER_URL . 'assets/admin.css', array(), SA_AI_CLEANER_VERSION );
		wp_enqueue_script( 'sa-ai-cleaner-admin', SA_AI_CLEANER_URL . 'assets/admin.js', array(), SA_AI_CLEANER_VERSION, true );
	}

	/* ---------------- ابزار مشترک ---------------- */

	private static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sa-ai-sentence-cleaner' ) );
		}
	}

	private static function notice( $type, $text ) {
		set_transient( 'sa_ai_notice_' . get_current_user_id(), array( 'type' => $type, 'text' => $text ), 120 );
	}

	private static function render_notice() {
		$key    = 'sa_ai_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( is_array( $notice ) ) {
			delete_transient( $key );
			echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . esc_html( $notice['text'] ) . '</p></div>';
		}
	}

	private static function redirect( $page = 'sa-ai-cleaner' ) {
		wp_safe_redirect( admin_url( 'admin.php?page=' . $page ) );
		exit;
	}

	private static function page_url( $page = 'sa-ai-cleaner', $extra = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $extra ), admin_url( 'admin.php' ) );
	}

	private static function selected_post_types() {
		$settings = SA_AI_Store::settings();
		$types    = isset( $settings['post_types'] ) ? (array) $settings['post_types'] : array( 'city' );
		return array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
	}

	private static function available_post_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$out[ $type->name ] = $type->labels->name;
			}
		}
		return $out;
	}

	private static function header( $title ) {
		self::require_admin();
		echo '<div class="wrap sa-ai-wrap"><h1>' . esc_html( $title ) . '</h1>';
		self::render_notice();
		echo '<p class="sa-ai-intro">فقط جمله حذف می‌شود؛ هیچ نوشته، تیتر، جدول، تصویر، لینک یا منبع معتبری حذف نمی‌شود. جملهٔ مشکوک فقط گزارش می‌شود. نوشته‌ای که بیش از ۲۰٪ متنش حذف شود، دست‌نخورده می‌ماند و «بررسی دستی» می‌خورد.</p>';
	}

	/* ---------------- اسکن ---------------- */

	public static function handle_start_scan() {
		self::require_admin();
		check_admin_referer( 'sa_ai_scan' );
		$settings  = SA_AI_Store::settings();
		$requested = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		$available = self::available_post_types();
		$types     = array_values( array_intersect( $requested, array_keys( $available ) ) );
		if ( ! $types ) {
			self::notice( 'error', 'هیچ نوع محتوای معتبری انتخاب نشده است.' );
			self::redirect();
		}

		$query = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		$ids = array_map( 'intval', (array) $query->posts );
		if ( ! $ids ) {
			self::notice( 'warning', 'هیچ مقالهٔ منتشرشده‌ای برای این نوع محتوا پیدا نشد.' );
			self::redirect();
		}

		// نقشهٔ جمله‌های تکراری از کل مقاله‌های انتخاب‌شده ساخته می‌شود (بیش از ۳ مقاله).
		$contents = array();
		foreach ( $ids as $post_id ) {
			$contents[ $post_id ] = (string) get_post_field( 'post_content', $post_id );
		}
		$map = SA_AI_Engine::collect_duplicates(
			$contents,
			SA_AI_Store::patterns(),
			isset( $settings['dup_min_posts'] ) ? (int) $settings['dup_min_posts'] : 4,
			isset( $settings['dup_min_length'] ) ? (int) $settings['dup_min_length'] : 20
		);
		SA_AI_Store::save_duplicate_map( $map );

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . SA_AI_Store::findings_table() );
		$scan_id = 'scan-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 6, false, false );
		$state   = array(
			'scan_id'    => $scan_id,
			'status'     => 'running',
			'post_types' => $types,
			'post_ids'   => $ids,
			'cursor'     => 0,
			'total'      => count( $ids ),
			'started_at' => current_time( 'mysql' ),
			'dup_groups' => count( $map ),
		);
		SA_AI_Store::save_scan_state( $state );
		self::process_scan_batch();
		$state = SA_AI_Store::scan_state();
		self::notice( 'success', sprintf( 'اسکن شروع شد: %1$d از %2$d مقاله بررسی شد. جمله‌های تکراری پیدا شده: %3$d گروه.', (int) $state['cursor'], (int) $state['total'], count( $map ) ) );
		self::redirect();
	}

	public static function handle_continue_scan() {
		self::require_admin();
		check_admin_referer( 'sa_ai_scan' );
		self::process_scan_batch();
		$state = SA_AI_Store::scan_state();
		if ( empty( $state ) ) {
			self::notice( 'warning', 'اسکن فعالی نیست؛ اول اسکن جدید شروع کنید.' );
		} elseif ( 'complete' === $state['status'] ) {
			self::notice( 'success', 'اسکن کامل شد.' );
		} else {
			self::notice( 'success', sprintf( 'تا این‌جا %1$d از %2$d مقاله بررسی شده است.', (int) $state['cursor'], (int) $state['total'] ) );
		}
		self::redirect();
	}

	private static function process_scan_batch() {
		$state = SA_AI_Store::scan_state();
		if ( empty( $state ) || empty( $state['post_ids'] ) ) {
			return;
		}
		$settings = SA_AI_Store::settings();
		$batch    = max( 1, isset( $settings['batch_size'] ) ? (int) $settings['batch_size'] : 20 );
		$ids      = array_slice( (array) $state['post_ids'], (int) $state['cursor'], $batch );
		$patterns = SA_AI_Store::patterns();
		$map      = SA_AI_Store::duplicate_map();
		foreach ( $ids as $post_id ) {
			$content = (string) get_post_field( 'post_content', (int) $post_id );
			$result  = SA_AI_Engine::analyze_content( $content, $patterns, $map, array( 'apply_sources' => ! empty( $settings['apply_sources'] ) ) );
			SA_AI_Store::save_post_findings( $state['scan_id'], (int) $post_id, $result['findings'] );
		}
		$state['cursor'] += count( $ids );
		if ( $state['cursor'] >= $state['total'] ) {
			$state['status'] = 'complete';
		}
		SA_AI_Store::save_scan_state( $state );
	}

	/* ---------------- اعمال حذف ---------------- */

	/**
	 * @param array $groups post_id => array{delete_by_pattern:bool, duplicate_hashes:string[]}
	 */
	private static function apply_groups( $groups, $scan_id, $mode ) {
		$settings  = SA_AI_Store::settings();
		$threshold = ( isset( $settings['manual_percent'] ) ? (int) $settings['manual_percent'] : 20 ) / 100;
		$map       = SA_AI_Store::duplicate_map();
		$patterns  = SA_AI_Store::patterns();
		$stats     = array( 'updated' => 0, 'sentences' => 0, 'manual' => 0, 'unchanged' => 0, 'failed' => 0 );
		foreach ( $groups as $post_id => $options ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status || ! current_user_can( 'edit_post', $post_id ) ) {
				++$stats['failed'];
				continue;
			}
			$before = (string) $post->post_content;
			$result = SA_AI_Engine::clean_content(
				$before,
				$patterns,
				$map,
				array(
					'delete_by_pattern' => ! empty( $options['delete_by_pattern'] ),
					'duplicate_hashes'  => isset( $options['duplicate_hashes'] ) ? $options['duplicate_hashes'] : array(),
					'apply_sources'     => ! empty( $settings['apply_sources'] ),
					'manual_threshold'  => $threshold,
				)
			);
			$hashes = isset( $options['duplicate_hashes'] ) ? $options['duplicate_hashes'] : array();
			if ( $result['manual_review'] ) {
				++$stats['manual'];
				SA_AI_Store::mark_post_findings( $scan_id, $post_id, 'manual-review', $hashes, ! empty( $options['delete_by_pattern'] ) && ! $hashes );
				continue;
			}
			if ( empty( $result['changed'] ) ) {
				++$stats['unchanged'];
				continue;
			}
			SA_AI_Store::create_backup( $post_id, $scan_id, $before );
			$updated = wp_update_post( array( 'ID' => $post_id, 'post_content' => $result['content'] ), true );
			if ( is_wp_error( $updated ) ) {
				++$stats['failed'];
				continue;
			}
			++$stats['updated'];
			$stats['sentences'] += (int) $result['deleted_sentences'];
			if ( $hashes ) {
				SA_AI_Store::mark_post_findings( $scan_id, $post_id, 'deleted', $hashes, false );
			} elseif ( ! empty( $options['delete_by_pattern'] ) ) {
				SA_AI_Store::mark_post_findings( $scan_id, $post_id, 'deleted', array(), true );
			}
		}
		SA_AI_Store::add_log( 'apply:' . $mode, $stats );
		return $stats;
	}

	private static function apply_stats_notice( $stats, $label ) {
		self::notice(
			$stats['failed'] ? 'warning' : 'success',
			sprintf( '%1$s: %2$d مقاله به‌روز شد، %3$d جمله حذف شد، %4$d مقاله «بررسی دستی» شد (دست نخورد)، %5$d مقاله بدون تغییر ماند.', $label, (int) $stats['updated'], (int) $stats['sentences'], (int) $stats['manual'], (int) $stats['unchanged'] )
		);
	}

	public static function handle_apply_all_delete() {
		self::require_admin();
		check_admin_referer( 'sa_ai_apply' );
		$state = SA_AI_Store::scan_state();
		if ( empty( $state['scan_id'] ) ) {
			self::notice( 'error', 'اسکنی برای اعمال وجود ندارد.' );
			self::redirect();
		}
		$groups = array();
		foreach ( SA_AI_Store::posts_with_pending_deletes( $state['scan_id'] ) as $post_id ) {
			$groups[ (int) $post_id ] = array( 'delete_by_pattern' => true, 'duplicate_hashes' => array() );
		}
		if ( ! $groups ) {
			self::notice( 'warning', 'جملهٔ DELETE در انتظاری نیست.' );
			self::redirect();
		}
		$stats = self::apply_groups( $groups, $state['scan_id'], 'all-delete' );
		self::apply_stats_notice( $stats, 'حذف همهٔ جمله‌های DELETE' );
		self::redirect();
	}

	public static function handle_apply_selected() {
		self::require_admin();
		check_admin_referer( 'sa_ai_apply' );
		$ids  = isset( $_POST['finding_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['finding_ids'] ) ) : array();
		$rows = SA_AI_Store::findings_by_ids( $ids );
		if ( ! $rows ) {
			self::notice( 'warning', 'هیچ جمله‌ای انتخاب نشده است.' );
			self::redirect();
		}
		$state  = SA_AI_Store::scan_state();
		$groups = array();
		foreach ( $rows as $row ) {
			$groups[ (int) $row['post_id'] ]['delete_by_pattern']        = false;
			$groups[ (int) $row['post_id'] ]['duplicate_hashes'][]        = $row['key_hash'];
		}
		$stats = self::apply_groups( $groups, isset( $state['scan_id'] ) ? $state['scan_id'] : '', 'selected' );
		self::apply_stats_notice( $stats, 'حذف جمله‌های انتخاب‌شده' );
		self::redirect();
	}

	public static function handle_apply_duplicates() {
		self::require_admin();
		check_admin_referer( 'sa_ai_duplicates' );
		$hashes = isset( $_POST['dup_hashes'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['dup_hashes'] ) ) : array();
		self::apply_duplicate_hashes( $hashes, 'جمله‌های تکراری انتخاب‌شده' );
	}

	public static function handle_apply_duplicate_ai() {
		self::require_admin();
		check_admin_referer( 'sa_ai_duplicates' );
		$hashes = array();
		foreach ( SA_AI_Store::duplicate_map() as $hash => $row ) {
			if ( ! empty( $row['pattern_id'] ) ) {
				$hashes[] = $hash;
			}
		}
		self::apply_duplicate_hashes( $hashes, 'جمله‌های تکراریِ دارای الگوی هوش مصنوعی' );
	}

	private static function apply_duplicate_hashes( $hashes, $label ) {
		$map = SA_AI_Store::duplicate_map();
		if ( ! $hashes ) {
			self::notice( 'warning', 'هیچ جملهٔ تکراری‌ای انتخاب نشده است. اول اسکن کامل کنید.' );
			self::redirect( 'sa-ai-cleaner-duplicates' );
		}
		$groups = array();
		foreach ( $hashes as $hash ) {
			if ( empty( $map[ $hash ] ) ) {
				continue;
			}
			foreach ( (array) $map[ $hash ]['post_ids'] as $post_id ) {
				$groups[ (int) $post_id ]['delete_by_pattern'] = false;
				$groups[ (int) $post_id ]['duplicate_hashes'][] = $hash;
			}
		}
		if ( ! $groups ) {
			self::notice( 'warning', 'برای این جمله‌ها مقالهٔ منتشرشده‌ای پیدا نشد؛ اسکن را تازه کنید.' );
			self::redirect( 'sa-ai-cleaner-duplicates' );
		}
		$state = SA_AI_Store::scan_state();
		$stats = self::apply_groups( $groups, isset( $state['scan_id'] ) ? $state['scan_id'] : '', 'duplicates' );
		self::apply_stats_notice( $stats, $label . ' (از همهٔ مقاله‌ها)' );
		self::redirect( 'sa-ai-cleaner-duplicates' );
	}

	/* ---------------- پشتیبان ---------------- */

	public static function handle_restore_backup() {
		self::require_admin();
		check_admin_referer( 'sa_ai_backups' );
		$id     = isset( $_POST['backup_id'] ) ? (int) $_POST['backup_id'] : 0;
		$backup = SA_AI_Store::backup( $id );
		if ( ! $backup || ! current_user_can( 'edit_post', (int) $backup['post_id'] ) ) {
			self::notice( 'error', 'پشتیبان پیدا نشد یا دسترسی ویرایش آن نوشته را ندارید.' );
			self::redirect( 'sa-ai-cleaner-backups' );
		}
		$updated = wp_update_post( array( 'ID' => (int) $backup['post_id'], 'post_content' => $backup['content_before'] ), true );
		if ( is_wp_error( $updated ) ) {
			self::notice( 'error', 'بازگردانی ناموفق بود: ' . $updated->get_error_message() );
		} else {
			SA_AI_Store::mark_restored( $id );
			SA_AI_Store::add_log( 'restore-backup', array( 'backup_id' => $id, 'post_id' => (int) $backup['post_id'] ) );
			self::notice( 'success', 'محتوای نوشته به نسخهٔ پشتیبان برگشت.' );
		}
		self::redirect( 'sa-ai-cleaner-backups' );
	}

	/* ---------------- تنظیمات ---------------- */

	public static function handle_save_settings() {
		self::require_admin();
		check_admin_referer( 'sa_ai_settings' );
		$available = self::available_post_types();
		$types     = isset( $_POST['post_types'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ), array_keys( $available ) ) ) : array( 'city' );
		SA_AI_Store::save_settings(
			array(
				'batch_size'     => max( 1, min( 100, isset( $_POST['batch_size'] ) ? (int) $_POST['batch_size'] : 20 ) ),
				'dup_min_posts'  => max( 2, min( 50, isset( $_POST['dup_min_posts'] ) ? (int) $_POST['dup_min_posts'] : 4 ) ),
				'dup_min_length' => max( 5, min( 200, isset( $_POST['dup_min_length'] ) ? (int) $_POST['dup_min_length'] : 20 ) ),
				'manual_percent' => max( 1, min( 90, isset( $_POST['manual_percent'] ) ? (int) $_POST['manual_percent'] : 20 ) ),
				'post_types'     => $types ? $types : array( 'city' ),
				'apply_sources'  => ! empty( $_POST['apply_sources'] ) ? 1 : 0,
			)
		);
		self::notice( 'success', 'تنظیمات ذخیره شد. برای اثرکردن آستانهٔ تکرار، اسکن تازه شروع کنید.' );
		self::redirect( 'sa-ai-cleaner-settings' );
	}

	public static function handle_save_patterns() {
		self::require_admin();
		check_admin_referer( 'sa_ai_settings' );
		$raw     = isset( $_POST['patterns_json'] ) ? wp_unslash( $_POST['patterns_json'] ) : '';
		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			self::notice( 'error', 'JSON الگوها معتبر نیست؛ چیزی ذخیره نشد.' );
			self::redirect( 'sa-ai-cleaner-settings' );
		}
		$clean = array();
		foreach ( $decoded as $p ) {
			if ( empty( $p['id'] ) || empty( $p['level'] ) ) {
				continue;
			}
			$regex = isset( $p['regex'] ) ? (string) $p['regex'] : '';
			if ( '' !== $regex && null === SA_AI_Engine::compile_pattern( $regex ) ) {
				self::notice( 'error', 'regex الگوی «' . sanitize_text_field( $p['id'] ) . '» معتبر نیست؛ چیزی ذخیره نشد.' );
				self::redirect( 'sa-ai-cleaner-settings' );
			}
			$clean[] = array(
				'id'          => sanitize_text_field( $p['id'] ),
				'level'       => 'DELETE' === $p['level'] ? 'DELETE' : 'FLAG',
				'regex'       => $regex,
				'description' => isset( $p['description'] ) ? sanitize_text_field( $p['description'] ) : '',
			);
		}
		SA_AI_Store::save_patterns( $clean );
		self::notice( 'success', 'الگوها ذخیره شدند؛ در اسکن بعدی اعمال می‌شوند.' );
		self::redirect( 'sa-ai-cleaner-settings' );
	}

	public static function handle_reset_patterns() {
		self::require_admin();
		check_admin_referer( 'sa_ai_settings' );
		SA_AI_Store::reset_patterns();
		self::notice( 'success', 'الگوهای پیش‌فرض برگشتند.' );
		self::redirect( 'sa-ai-cleaner-settings' );
	}

	public static function handle_export_csv() {
		self::require_admin();
		check_admin_referer( 'sa_ai_export' );
		$state = SA_AI_Store::scan_state();
		if ( empty( $state['scan_id'] ) ) {
			self::notice( 'warning', 'اسکنی برای خروجی وجود ندارد.' );
			self::redirect();
		}
		global $wpdb;
		$table = SA_AI_Store::findings_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, pattern_id, level, sentence, action, is_duplicate FROM {$table} WHERE scan_id=%s ORDER BY post_id, id", $state['scan_id'] ), ARRAY_A );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=sa-ai-cleaner-report.csv' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'post_id', 'post_title', 'pattern_id', 'level', 'sentence', 'action', 'is_duplicate' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv( $out, array( $row['post_id'], get_the_title( (int) $row['post_id'] ), $row['pattern_id'], $row['level'], $row['sentence'], $row['action'], $row['is_duplicate'] ) );
		}
		fclose( $out );
		exit;
	}

	/* ---------------- نماها ---------------- */

	public static function render_scan() {
		self::header( 'پاک‌سازی جمله‌های هوش مصنوعی' );
		$settings = SA_AI_Store::settings();
		$state    = SA_AI_Store::scan_state();
		$types    = self::available_post_types();
		$selected = $state['post_types'] ?? self::selected_post_types();
		?>
		<div class="sa-ai-card">
			<h2>۱) اسکن مقاله‌های منتشرشده</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sa_ai_start_scan">
				<?php wp_nonce_field( 'sa_ai_scan' ); ?>
				<p>نوع محتوا:
				<?php foreach ( $types as $name => $label ) : ?>
					<label class="sa-ai-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, (array) $selected, true ) ); ?>> <?php echo esc_html( $label . ' (' . $name . ')' ); ?></label>
				<?php endforeach; ?>
				</p>
				<p><button class="button button-primary">شروع اسکن جدید</button>
				<span class="description">اسکن فقط گزارش می‌سازد و چیزی را حذف نمی‌کند. دسته‌ها <?php echo esc_html( (string) $settings['batch_size'] ); ?>تایی‌اند تا روی هاست کند هم timeout نشود.</span></p>
			</form>
			<?php if ( ! empty( $state['post_ids'] ) ) : $pct = $state['total'] ? round( 100 * $state['cursor'] / $state['total'] ) : 100; ?>
				<p>پیشرفت اسکن: <strong><?php echo esc_html( (string) $state['cursor'] ); ?> از <?php echo esc_html( (string) $state['total'] ); ?></strong> (<?php echo esc_html( (string) $pct ); ?>٪) — وضعیت: <?php echo esc_html( 'complete' === $state['status'] ? 'کامل' : 'در حال انجام' ); ?> — گروه جملهٔ تکراری: <?php echo esc_html( (string) ( $state['dup_groups'] ?? 0 ) ); ?></p>
				<div class="sa-ai-progress"><span style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span></div>
				<?php if ( 'complete' !== $state['status'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="sa_ai_continue_scan">
					<?php wp_nonce_field( 'sa_ai_scan' ); ?>
					<p><button class="button">ادامهٔ اسکن (دستهٔ بعد)</button></p>
				</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $state['scan_id'] ) ) : $sum = SA_AI_Store::summary( $state['scan_id'] ); ?>
		<div class="sa-ai-card">
			<h2>۲) نتیجه و حذف</h2>
			<p>یافته‌ها: <strong><?php echo esc_html( (string) $sum['total'] ); ?></strong> — نامزد حذف (DELETE): <strong><?php echo esc_html( (string) ( $sum['deletes'] ?? 0 ) ); ?></strong> — فقط گزارش (FLAG): <strong><?php echo esc_html( (string) ( $sum['flags'] ?? 0 ) ); ?></strong> — جملهٔ تکراری: <strong><?php echo esc_html( (string) ( $sum['dups'] ?? 0 ) ); ?></strong> — در <strong><?php echo esc_html( (string) ( $sum['posts'] ?? 0 ) ); ?></strong> مقاله</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-ai-inline" data-confirm="جمله‌های DELETE از همهٔ مقاله‌های اسکن‌شده حذف شوند؟ قبل از هر تغییر پشتیبان گرفته می‌شود.">
				<input type="hidden" name="action" value="sa_ai_apply_all_delete">
				<?php wp_nonce_field( 'sa_ai_apply' ); ?>
				<button class="button button-primary">حذف همهٔ جمله‌های DELETE</button>
			</form>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_ai_export_csv' ), 'sa_ai_export' ) ); ?>">دریافت گزارش CSV</a>
			<a class="button" href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner-duplicates' ) ); ?>">صفحهٔ جمله‌های تکراری</a>

			<?php
			$level   = isset( $_GET['flevel'] ) ? sanitize_key( wp_unslash( $_GET['flevel'] ) ) : '';
			$fpage   = max( 1, isset( $_GET['fpage'] ) ? (int) $_GET['fpage'] : 1 );
			$total_f = SA_AI_Store::findings_count( $state['scan_id'], strtoupper( $level ) );
			$rows    = SA_AI_Store::findings( $state['scan_id'], strtoupper( $level ), $fpage, 50 );
			?>
			<p>
				<a href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner' ) ); ?>" <?php echo '' === $level ? 'class="sa-ai-active"' : ''; ?>>همه</a> |
				<a href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner', array( 'flevel' => 'delete' ) ) ); ?>" <?php echo 'delete' === $level ? 'class="sa-ai-active"' : ''; ?>>فقط DELETE</a> |
				<a href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner', array( 'flevel' => 'flag' ) ) ); ?>" <?php echo 'flag' === $level ? 'class="sa-ai-active"' : ''; ?>>فقط FLAG</a>
				<span class="description">(<?php echo esc_html( (string) $total_f ); ?> یافته)</span>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="جمله‌های انتخاب‌شده از مقاله‌هایشان حذف شوند؟ قبل از هر تغییر پشتیبان گرفته می‌شود.">
				<input type="hidden" name="action" value="sa_ai_apply_selected">
				<?php wp_nonce_field( 'sa_ai_apply' ); ?>
				<table class="widefat striped sa-ai-table">
					<thead><tr><th class="check-column"><input type="checkbox" data-select-all="finding_ids[]"></th><th>مقاله</th><th>الگو</th><th>سطح</th><th>جملهٔ کامل</th><th>وضعیت</th></tr></thead>
					<tbody>
					<?php foreach ( (array) $rows as $row ) : ?>
						<tr>
							<td><input type="checkbox" name="finding_ids[]" value="<?php echo esc_attr( (string) $row['id'] ); ?>"></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ); ?></a><br><small>#<?php echo esc_html( (string) $row['post_id'] ); ?> — <a href="<?php echo esc_url( get_permalink( (int) $row['post_id'] ) ); ?>" target="_blank" rel="noopener">مشاهده</a></small></td>
							<td><code><?php echo esc_html( $row['pattern_id'] ); ?></code><?php echo $row['is_duplicate'] ? '<br><span class="sa-ai-badge">تکراری</span>' : ''; ?></td>
							<td><span class="sa-ai-badge sa-ai-<?php echo esc_attr( strtolower( $row['level'] ) ); ?>"><?php echo esc_html( $row['level'] ); ?></span></td>
							<td class="sa-ai-sentence"><?php echo esc_html( $row['sentence'] ); ?></td>
							<td><?php echo esc_html( $row['action'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $rows ) : ?><tr><td colspan="6">یافته‌ای نیست؛ اسکن را کامل کنید یا فیلتر را عوض کنید.</td></tr><?php endif; ?>
					</tbody>
				</table>
				<p><button class="button">حذف جمله‌های انتخاب‌شده</button>
				<span class="description">انتخاب هر جمله یعنی تأیید صریح شما برای حذف همان جمله؛ حتی اگر FLAG باشد.</span></p>
			</form>
			<?php if ( $total_f > 50 ) : ?>
				<p>
				<?php if ( $fpage > 1 ) : ?><a class="button" href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner', array( 'flevel' => $level, 'fpage' => $fpage - 1 ) ) ); ?>">صفحهٔ قبل</a><?php endif; ?>
				<?php if ( $fpage * 50 < $total_f ) : ?><a class="button" href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner', array( 'flevel' => $level, 'fpage' => $fpage + 1 ) ) ); ?>">صفحهٔ بعد</a><?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div class="sa-ai-card">
			<h2>گزارش اجراها</h2>
			<ul>
			<?php foreach ( array_slice( SA_AI_Store::logs(), 0, 10 ) as $log ) : ?>
				<li><code><?php echo esc_html( $log['time'] ); ?></code> — <?php echo esc_html( $log['message'] ); ?> <small><?php echo esc_html( (string) wp_json_encode( $log['data'] ) ); ?></small></li>
			<?php endforeach; ?>
			<?php if ( ! SA_AI_Store::logs() ) : ?><li>هنوز اجرایی ثبت نشده است.</li><?php endif; ?>
			</ul>
		</div>
		<?php echo '</div>';
	}

	public static function render_duplicates() {
		self::header( 'جمله‌های تکراری بین مقاله‌ها' );
		$map = SA_AI_Store::duplicate_map();
		uasort(
			$map,
			function ( $a, $b ) {
				return ( $b['count'] ?? 0 ) <=> ( $a['count'] ?? 0 );
			}
		);
		$ai_count = 0;
		foreach ( $map as $row ) {
			if ( ! empty( $row['pattern_id'] ) ) {
				++$ai_count;
			}
		}
		$dpage = max( 1, isset( $_GET['dpage'] ) ? (int) $_GET['dpage'] : 1 );
		$rows  = array_slice( $map, ( $dpage - 1 ) * 20, 20, true );
		?>
		<div class="sa-ai-card">
			<p>جمله‌ای که عیناً در بیش از ۳ مقاله آمده این‌جا گروه‌بندی شده است. گروه‌های دارای الگوی هوش مصنوعی با برچسب مشخص‌اند و با یک دکمه از <strong>همهٔ</strong> مقاله‌ها حذف می‌شوند. گروه بدون الگو فقط وقتی حذف می‌شود که خودتان تیکش بزنید و تأیید کنید؛ جملهٔ مشکوک خودکار حذف نمی‌شود.</p>
			<p>گروه‌ها: <strong><?php echo esc_html( (string) count( $map ) ); ?></strong> — دارای الگوی AI: <strong><?php echo esc_html( (string) $ai_count ); ?></strong></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sa-ai-inline" data-confirm="همهٔ جمله‌های تکراریِ دارای الگوی هوش مصنوعی از همهٔ مقاله‌ها حذف شوند؟ قبل از هر تغییر پشتیبان گرفته می‌شود.">
				<input type="hidden" name="action" value="sa_ai_apply_duplicate_ai">
				<?php wp_nonce_field( 'sa_ai_duplicates' ); ?>
				<button class="button button-primary">حذف همهٔ تکراری‌های دارای الگوی AI از همهٔ مقاله‌ها</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="جمله‌های تکراری انتخاب‌شده از همهٔ مقاله‌هایی که در آن‌ها آمده‌اند حذف شوند؟">
				<input type="hidden" name="action" value="sa_ai_apply_duplicates">
				<?php wp_nonce_field( 'sa_ai_duplicates' ); ?>
				<table class="widefat striped sa-ai-table">
					<thead><tr><th class="check-column"><input type="checkbox" data-select-all="dup_hashes[]"></th><th>جملهٔ تکراری</th><th>تکرار در</th><th>الگوی AI</th><th>مقاله‌ها</th></tr></thead>
					<tbody>
					<?php foreach ( $rows as $hash => $row ) : ?>
						<tr>
							<td><input type="checkbox" name="dup_hashes[]" value="<?php echo esc_attr( $hash ); ?>"></td>
							<td class="sa-ai-sentence"><?php echo esc_html( $row['sentence'] ); ?></td>
							<td><strong><?php echo esc_html( (string) $row['count'] ); ?></strong> مقاله</td>
							<td><?php echo ! empty( $row['pattern_id'] ) ? '<span class="sa-ai-badge sa-ai-delete">' . esc_html( $row['pattern_id'] ) . '</span>' : '<span class="description">—</span>'; ?></td>
							<td><details><summary>فهرست</summary><ul>
								<?php foreach ( (array) $row['post_ids'] as $post_id ) : ?>
									<li><a href="<?php echo esc_url( get_edit_post_link( (int) $post_id ) ); ?>"><?php echo esc_html( get_the_title( (int) $post_id ) ); ?></a> <small>#<?php echo esc_html( (string) $post_id ); ?></small></li>
								<?php endforeach; ?>
							</ul></details></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! $rows ) : ?><tr><td colspan="5">هنوز گروهی نیست؛ اول از صفحهٔ اسکن، اسکن کامل اجرا کنید.</td></tr><?php endif; ?>
					</tbody>
				</table>
				<p><button class="button">حذف جمله‌های تکراری انتخاب‌شده از همهٔ مقاله‌ها</button></p>
			</form>
			<?php if ( count( $map ) > 20 ) : ?>
				<p>
				<?php if ( $dpage > 1 ) : ?><a class="button" href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner-duplicates', array( 'dpage' => $dpage - 1 ) ) ); ?>">صفحهٔ قبل</a><?php endif; ?>
				<?php if ( $dpage * 20 < count( $map ) ) : ?><a class="button" href="<?php echo esc_url( self::page_url( 'sa-ai-cleaner-duplicates', array( 'dpage' => $dpage + 1 ) ) ); ?>">صفحهٔ بعد</a><?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php echo '</div>';
	}

	public static function render_backups() {
		self::header( 'پشتیبان‌های قبل از حذف' );
		$rows = SA_AI_Store::backups( 100 );
		?>
		<div class="sa-ai-card">
			<p>قبل از اولین تغییر هر نوشته در هر اجرا، متن کامل قبلی این‌جا ذخیره می‌شود. بازگردانی یعنی برگرداندن همان متن؛ علاوه بر این، وردپرس خودش هم revision می‌سازد.</p>
			<table class="widefat striped">
				<thead><tr><th>مقاله</th><th>زمان</th><th>اجرا</th><th>وضعیت</th><th></th></tr></thead>
				<tbody>
				<?php foreach ( (array) $rows as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( (int) $row['post_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['post_id'] ) ); ?></a> <small>#<?php echo esc_html( (string) $row['post_id'] ); ?></small></td>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td><code><?php echo esc_html( $row['scan_id'] ); ?></code></td>
						<td><?php echo $row['restored_at'] ? 'برگردانده شده در ' . esc_html( $row['restored_at'] ) : 'فعال'; ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="محتوای فعلی این نوشته با نسخهٔ پشتیبان جایگزین شود؟">
								<input type="hidden" name="action" value="sa_ai_restore_backup">
								<input type="hidden" name="backup_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
								<?php wp_nonce_field( 'sa_ai_backups' ); ?>
								<button class="button">بازگردانی</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( ! $rows ) : ?><tr><td colspan="5">هنوز پشتیبانی گرفته نشده است.</td></tr><?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php echo '</div>';
	}

	public static function render_settings() {
		self::header( 'تنظیمات و الگوها' );
		$s        = SA_AI_Store::settings();
		$types    = self::available_post_types();
		$patterns = SA_AI_Store::patterns();
		?>
		<div class="sa-ai-card">
			<h2>تنظیمات</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sa_ai_save_settings">
				<?php wp_nonce_field( 'sa_ai_settings' ); ?>
				<table class="form-table">
					<tr><th>اندازهٔ دستهٔ اسکن</th><td><input type="number" name="batch_size" min="1" max="100" value="<?php echo esc_attr( (string) $s['batch_size'] ); ?>"> <span class="description">پیش‌فرض ۲۰</span></td></tr>
					<tr><th>جملهٔ تکراری یعنی در دست‌کم چند مقاله؟</th><td><input type="number" name="dup_min_posts" min="2" max="50" value="<?php echo esc_attr( (string) $s['dup_min_posts'] ); ?>"> <span class="description">پیش‌فرض ۴، یعنی «بیش از ۳ مقاله»</span></td></tr>
					<tr><th>کم‌ترین طول جملهٔ تکراری (نویسه)</th><td><input type="number" name="dup_min_length" min="5" max="200" value="<?php echo esc_attr( (string) $s['dup_min_length'] ); ?>"></td></tr>
					<tr><th>آستانهٔ بررسی دستی (٪)</th><td><input type="number" name="manual_percent" min="1" max="90" value="<?php echo esc_attr( (string) $s['manual_percent'] ); ?>"> <span class="description">بیش از این درصد حذف شود، مقاله دست نمی‌خورد</span></td></tr>
					<tr><th>پاک‌سازی منابع و utm</th><td><label><input type="checkbox" name="apply_sources" value="1" <?php checked( ! empty( $s['apply_sources'] ) ); ?>> منبع صرفاً google/chatgpt حذف و utm_source=chatgpt.com پاک شود</label></td></tr>
					<tr><th>نوع محتوای پیش‌فرض</th><td>
						<?php foreach ( $types as $name => $label ) : ?>
							<label class="sa-ai-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, (array) $s['post_types'], true ) ); ?>> <?php echo esc_html( $label . ' (' . $name . ')' ); ?></label>
						<?php endforeach; ?>
					</td></tr>
				</table>
				<p><button class="button button-primary">ذخیرهٔ تنظیمات</button></p>
			</form>
		</div>
		<div class="sa-ai-card">
			<h2>الگوها (JSON)</h2>
			<p>هر الگو: <code>id</code>، سطح <code>DELETE</code> یا <code>FLAG</code>، <code>regex</code> و توضیح. regex خالی یعنی الگوی ویژه (تکراری/منبع) که موتور خودش فعالش می‌کند. اگر شک دارید جمله‌ای سالم است، سطحش را FLAG بگذارید.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sa_ai_save_patterns">
				<?php wp_nonce_field( 'sa_ai_settings' ); ?>
				<textarea name="patterns_json" rows="22" class="large-text code" dir="ltr"><?php echo esc_textarea( (string) wp_json_encode( $patterns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
				<p><button class="button button-primary">ذخیرهٔ الگوها</button></p>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="الگوهای پیش‌فرض برگردند و ویرایش‌های شما پاک شود؟">
				<input type="hidden" name="action" value="sa_ai_reset_patterns">
				<?php wp_nonce_field( 'sa_ai_settings' ); ?>
				<button class="button">بازنشانی الگوهای پیش‌فرض</button>
			</form>
		</div>
		<?php echo '</div>';
	}
}
