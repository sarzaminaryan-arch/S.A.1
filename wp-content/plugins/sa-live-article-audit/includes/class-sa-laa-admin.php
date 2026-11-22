<?php
/**
 * پیشخوان «ممیزی مقالات زنده» — فقط‌خواندنی.
 *
 * مسیر: ابزارها ← ممیزی مقالات زنده (قابلیت manage_options).
 * هیچ نوشته، متا، ترم یا تنظیم سایت تغییر نمی‌کند؛ تنها خروجی، دانلود
 * اسنپ‌شات/گزارش و یک transient موقت برای دانلود گزارش همان مدیر است.
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_LAA_Admin {

	const PAGE = 'sa-live-article-audit';

	/** @var array|null نتیجهٔ تطبیق همین درخواست. */
	private static $report = null;

	/** @var array اطلاعات منبع خط مبنا. */
	private static $baseline_info = array();

	/** @var string پیام خطا برای نمایش. */
	private static $error = '';

	/** @var array تنظیمات آخرین فرم. */
	private static $settings = array();

	/**
	 * ثبت هوک‌ها.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form' ) );
		add_action( 'admin_post_sa_laa_report_json', array( __CLASS__, 'download_report_json' ) );
		add_action( 'admin_post_sa_laa_report_csv', array( __CLASS__, 'download_report_csv' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * کلید transient گزارش آخرین تطبیق مدیر جاری.
	 *
	 * @return string
	 */
	private static function transient_key() {
		return 'sa_laa_report_' . get_current_user_id();
	}

	/**
	 * منوی ابزارها.
	 */
	public static function menu() {
		add_management_page(
			'ممیزی مقالات زنده — تطبیق با مخزن',
			'ممیزی مقالات زنده',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * CSS/JS فقط در صفحهٔ خود افزونه.
	 *
	 * @param string $hook هوک صفحه.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'sa-laa-admin', SA_LAA_URL . 'assets/admin.css', array(), SA_LAA_VERSION );
		wp_enqueue_script( 'sa-laa-admin', SA_LAA_URL . 'assets/admin.js', array(), SA_LAA_VERSION, true );
	}

	/**
	 * تنظیمات پیش‌فرض فرم.
	 *
	 * @return array
	 */
	private static function default_settings() {
		$preferred = array( 'guide', 'post', 'attraction', 'city', 'province', 'travel_route', 'local_food', 'souvenir' );
		$types     = array();
		foreach ( $preferred as $type ) {
			if ( post_type_exists( $type ) ) {
				$types[] = $type;
			}
		}
		if ( ! $types ) {
			$types = array( 'post' );
		}
		return array(
			'post_types' => $types,
			'all_meta'   => false,
			'source'     => SA_LAA_Baseline::theme_available() ? 'theme' : 'bundled',
		);
	}

	/**
	 * خواندن و پاک‌سازی تنظیمات فرم.
	 *
	 * @return array
	 */
	private static function request_settings() {
		$settings = self::default_settings();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- نانس در handle_form پیش از این تابع بررسی می‌شود.
		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$types = array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) );
			$types = array_values( array_filter( $types, 'post_type_exists' ) );
			if ( $types ) {
				$settings['post_types'] = $types;
			}
		}
		$settings['all_meta'] = ! empty( $_POST['all_meta'] );
		if ( isset( $_POST['baseline_source'] ) ) {
			$source = sanitize_key( wp_unslash( $_POST['baseline_source'] ) );
			if ( in_array( $source, array( 'theme', 'bundled', 'upload' ), true ) ) {
				$settings['source'] = $source;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $settings;
	}

	/**
	 * پردازش فرم اصلی (تطبیق یا دانلود اسنپ‌شات) در admin_init.
	 */
	public static function handle_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- در خط بعد بررسی می‌شود.
		if ( ! isset( $_POST['sa_laa_action'] ) || ! isset( $_GET['page'] ) || self::PAGE !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'برای این کار دسترسی کافی ندارید.', 'sa-live-article-audit' ) );
		}
		check_admin_referer( 'sa_laa_run' );

		$action           = sanitize_key( wp_unslash( $_POST['sa_laa_action'] ) );
		self::$settings   = self::request_settings();
		$settings         = self::$settings;

		if ( 'snapshot_json' === $action || 'snapshot_csv' === $action ) {
			$snapshot = SA_LAA_Extractor::snapshot( $settings['post_types'], $settings['all_meta'] );
			$stamp    = gmdate( 'Ymd-His' );
			if ( 'snapshot_json' === $action ) {
				self::send_download( SA_LAA_Export::json( $snapshot ), 'live-articles-' . $stamp . '.json', 'application/json; charset=utf-8' );
			}
			self::send_download( SA_LAA_Export::csv( SA_LAA_Export::snapshot_rows( $snapshot ) ), 'live-articles-' . $stamp . '.csv', 'text/csv; charset=utf-8' );
		}

		if ( 'compare' !== $action ) {
			return;
		}

		$uploaded = null;
		if ( 'upload' === $settings['source'] ) {
			$uploaded = self::read_uploaded_baseline();
			if ( is_wp_error( $uploaded ) ) {
				self::$error = $uploaded->get_error_message();
				return;
			}
		}

		$baseline = SA_LAA_Baseline::load( $settings['source'], $uploaded );
		if ( is_wp_error( $baseline ) ) {
			self::$error = $baseline->get_error_message();
			return;
		}

		$snapshot              = SA_LAA_Extractor::snapshot( $settings['post_types'], $settings['all_meta'] );
		self::$report          = SA_LAA_Compare::compare( $baseline['records'], $snapshot['records'] );
		self::$baseline_info   = $baseline['info'];
		self::$report['meta']  = array(
			'generated_at'  => gmdate( 'c' ),
			'site'          => $snapshot['site'],
			'baseline'      => $baseline['info'],
			'post_types'    => $settings['post_types'],
			'live_snapshot' => array(
				'count'        => $snapshot['count'],
				'generated_at' => $snapshot['generated_at'],
			),
		);
		set_transient(
			self::transient_key(),
			array(
				'report'        => self::$report,
				'baseline_info' => self::$baseline_info,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * خواندن فایل JSON خط مبنای بارگذاری‌شده (حداکثر ۱۰ مگابایت).
	 *
	 * @return array|WP_Error
	 */
	private static function read_uploaded_baseline() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- نانس در handle_form بررسی شده؛ فایل فقط خوانده و JSON می‌شود.
		if ( ! isset( $_FILES['baseline_file'] ) || ! is_array( $_FILES['baseline_file'] ) || UPLOAD_ERR_OK !== (int) $_FILES['baseline_file']['error'] ) {
			return new WP_Error( 'sa_laa_upload', 'فایل خط مبنا بارگذاری نشد.' );
		}
		$file = $_FILES['baseline_file'];
		if ( (int) $file['size'] > 10 * MB_IN_BYTES ) {
			return new WP_Error( 'sa_laa_upload_size', 'فایل خط مبنا بزرگ‌تر از ۱۰ مگابایت است.' );
		}
		$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		if ( 'json' !== $ext ) {
			return new WP_Error( 'sa_laa_upload_ext', 'فقط فایل JSON پذیرفته می‌شود.' );
		}
		$raw = file_get_contents( (string) $file['tmp_name'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'sa_laa_upload_json', 'فایل بارگذاری‌شده JSON معتبر نیست.' );
		}
		return $decoded;
	}

	/**
	 * ارسال فایل دانلود و پایان درخواست.
	 *
	 * @param string $body        بدنه.
	 * @param string $filename    نام فایل.
	 * @param string $content_type نوع محتوا.
	 */
	private static function send_download( $body, $filename, $content_type ) {
		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . strlen( (string) $body ) );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- دانلود فایل.
		exit;
	}

	/**
	 * دانلود JSON گزارش آخرین تطبیق.
	 */
	public static function download_report_json() {
		$report = self::stored_report();
		self::send_download( SA_LAA_Export::json( $report ), 'audit-report-' . gmdate( 'Ymd-His' ) . '.json', 'application/json; charset=utf-8' );
	}

	/**
	 * دانلود CSV گزارش آخرین تطبیق.
	 */
	public static function download_report_csv() {
		$report = self::stored_report();
		self::send_download( SA_LAA_Export::csv( SA_LAA_Export::report_rows( $report ) ), 'audit-report-' . gmdate( 'Ymd-His' ) . '.csv', 'text/csv; charset=utf-8' );
	}

	/**
	 * گزارش ذخیره‌شده در transient را با بررسی دسترسی/نانس برمی‌گرداند.
	 *
	 * @return array
	 */
	private static function stored_report() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'برای این کار دسترسی کافی ندارید.', 'sa-live-article-audit' ) );
		}
		check_admin_referer( 'sa_laa_download_report' );
		$stored = get_transient( self::transient_key() );
		if ( ! is_array( $stored ) || empty( $stored['report'] ) || ! is_array( $stored['report'] ) ) {
			wp_die( esc_html__( 'گزارشی برای دانلود نیست؛ اول «اجرای تطبیق» را بزنید.', 'sa-live-article-audit' ) );
		}
		return $stored['report'];
	}

	/**
	 * نمایش صفحه.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'برای این کار دسترسی کافی ندارید.', 'sa-live-article-audit' ) );
		}
		$settings = self::$settings ? self::$settings : self::default_settings();
		$types    = SA_LAA_Extractor::public_post_types();
		?>
		<div class="wrap sa-laa" dir="rtl">
			<h1>ممیزی مقالات زنده — تطبیق با مخزن S.A.1</h1>
			<p class="sa-laa-intro">
				این ابزار <strong>فقط می‌خواند</strong>: مقاله‌های منتشرشدهٔ سایت را دقیق استخراج می‌کند و با خط مبنای مخزن
				(دادهٔ «مقالات آماده»ی قالب یا اسنپ‌شات همراه افزونه) تطبیق می‌دهد. هیچ نوشته‌ای تغییر نمی‌کند.
				تطبیق بر اساس «نوع محتوا + نامک» است و اختلاف‌ها فیلدبه‌فیلد گزارش می‌شوند:
				عنوان، چکیده، محتوا، متاها، FAQ، طبقه‌بندی‌ها، تصویر شاخص و روابط استان/شهرستان.
			</p>

			<?php if ( self::$error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( self::$error ); ?></p></div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>" class="sa-laa-form">
				<?php wp_nonce_field( 'sa_laa_run' ); ?>
				<h2>۱) چه چیزی از سایت زنده خوانده شود؟</h2>
				<fieldset class="sa-laa-types">
					<?php foreach ( $types as $slug => $label ) : ?>
						<label>
							<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['post_types'], true ) ); ?>>
							<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<label class="sa-laa-check">
					<input type="checkbox" name="all_meta" value="1" <?php checked( $settings['all_meta'] ); ?>>
					همهٔ متاها خوانده شود (پیش‌فرض: <code>sa_*</code>، <code>rank_math_*</code> و کلیدهای کانونی شناسنامهٔ شهرستان)
				</label>

				<h2>۲) خط مبنای مخزن از کجا بیاید؟</h2>
				<fieldset class="sa-laa-sources">
					<label>
						<input type="radio" name="baseline_source" value="theme" <?php checked( 'theme' === $settings['source'] ); ?> <?php disabled( ! SA_LAA_Baseline::theme_available() ); ?>>
						قالب فرزند — <code>sa_ready_articles_data()</code>
						<?php if ( ! SA_LAA_Baseline::theme_available() ) : ?><em>(قالب سرزمین آریان فعال نیست)</em><?php endif; ?>
					</label>
					<label>
						<input type="radio" name="baseline_source" value="bundled" <?php checked( 'bundled' === $settings['source'] ); ?> <?php disabled( ! file_exists( SA_LAA_Baseline::bundled_path() ) ); ?>>
						اسنپ‌شات مخزن همراه افزونه — <code>data/baseline.json</code>
						<?php if ( ! file_exists( SA_LAA_Baseline::bundled_path() ) ) : ?><em>(در این بسته نیست)</em><?php endif; ?>
					</label>
					<label>
						<input type="radio" name="baseline_source" value="upload" <?php checked( 'upload' === $settings['source'] ); ?>>
						بارگذاری فایل JSON خط مبنا/اسنپ‌شات
						<input type="file" name="baseline_file" accept=".json,application/json">
					</label>
				</fieldset>

				<p class="sa-laa-actions">
					<button type="submit" name="sa_laa_action" value="compare" class="button button-primary">اجرای تطبیق</button>
					<button type="submit" name="sa_laa_action" value="snapshot_json" class="button">دانلود اسنپ‌شات زنده (JSON)</button>
					<button type="submit" name="sa_laa_action" value="snapshot_csv" class="button">دانلود اسنپ‌شات زنده (CSV)</button>
				</p>
				<p class="description">
					اسنپ‌شات JSON شامل متن کامل، هش‌ها، تیترها، لینک‌ها، تصاویر، طبقه‌بندی‌ها، متاها و تصویر شاخص هر مقالهٔ منتشرشده است؛
					فایل را نزد خود نگه دارید و در صورت نیاز همین‌جا به‌عنوان خط مبنا بارگذاری کنید تا دو مقطع زمانی هم مقایسه شوند.
				</p>
			</form>

			<?php if ( is_array( self::$report ) ) : ?>
				<?php self::render_report( self::$report ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * نمایش گزارش تطبیق.
	 *
	 * @param array $report گزارش.
	 */
	private static function render_report( $report ) {
		$s = $report['summary'];
		?>
		<section class="sa-laa-report">
			<h2>۳) نتیجهٔ تطبیق</h2>
			<?php if ( ! empty( self::$baseline_info['label'] ) ) : ?>
				<p>خط مبنا: <strong><?php echo esc_html( self::$baseline_info['label'] ); ?></strong>
				<?php if ( ! empty( self::$baseline_info['generated_at'] ) ) : ?>
					— ساخته‌شده در <?php echo esc_html( self::$baseline_info['generated_at'] ); ?>
				<?php endif; ?>
				</p>
			<?php endif; ?>

			<div class="sa-laa-cards">
				<div class="sa-laa-card"><span><?php echo esc_html( number_format_i18n( $s['baseline_count'] ) ); ?></span>رکورد در مخزن</div>
				<div class="sa-laa-card"><span><?php echo esc_html( number_format_i18n( $s['live_count'] ) ); ?></span>مقالهٔ منتشرشدهٔ زنده</div>
				<div class="sa-laa-card sa-laa-ok"><span><?php echo esc_html( number_format_i18n( $s['identical'] ) ); ?></span>یکسان</div>
				<div class="sa-laa-card sa-laa-bad"><span><?php echo esc_html( number_format_i18n( $s['different'] ) ); ?></span>دارای اختلاف</div>
				<div class="sa-laa-card"><span><?php echo esc_html( number_format_i18n( $s['live_only'] ) ); ?></span>فقط در سایت</div>
				<div class="sa-laa-card"><span><?php echo esc_html( number_format_i18n( $s['baseline_only'] ) ); ?></span>فقط در مخزن</div>
			</div>

			<p class="sa-laa-actions">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_laa_report_json' ), 'sa_laa_download_report' ) ); ?>">دانلود گزارش (JSON)</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=sa_laa_report_csv' ), 'sa_laa_download_report' ) ); ?>">دانلود گزارش (CSV)</a>
			</p>

			<?php if ( ! empty( $s['field_differences'] ) ) : ?>
				<h3>اختلاف‌ها به تفکیک فیلد</h3>
				<ul class="sa-laa-fields">
					<?php foreach ( $s['field_differences'] as $field => $count ) : ?>
						<li><code><?php echo esc_html( $field ); ?></code> — <?php echo esc_html( number_format_i18n( $count ) ); ?> مورد</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="sa-laa-filter">
				<input type="search" id="sa-laa-search" placeholder="جست‌وجو در عنوان یا نامک…">
				<select id="sa-laa-status">
					<option value="">همه</option>
					<option value="different">فقط دارای اختلاف</option>
					<option value="identical">فقط یکسان</option>
				</select>
			</div>

			<h3>مقاله‌های تطبیق‌خورده (<?php echo esc_html( number_format_i18n( $s['matched'] ) ); ?>)</h3>
			<table class="widefat striped sa-laa-table" id="sa-laa-matched">
				<thead><tr><th>وضعیت</th><th>مقاله</th><th>کلید</th><th>اختلاف‌ها</th></tr></thead>
				<tbody>
				<?php foreach ( $report['items'] as $item ) : ?>
					<tr data-status="<?php echo esc_attr( $item['status'] ); ?>" data-search="<?php echo esc_attr( $item['title'] . ' ' . $item['slug'] . ' ' . $item['key'] ); ?>">
						<td>
							<?php if ( 'identical' === $item['status'] ) : ?>
								<span class="sa-laa-chip sa-laa-chip-ok">یکسان</span>
							<?php else : ?>
								<span class="sa-laa-chip sa-laa-chip-bad">اختلاف</span>
							<?php endif; ?>
						</td>
						<td>
							<strong><?php echo esc_html( $item['title'] ); ?></strong>
							<div class="row-actions visible">
								<?php if ( $item['permalink'] ) : ?><a href="<?php echo esc_url( $item['permalink'] ); ?>" target="_blank" rel="noopener">مشاهده</a><?php endif; ?>
								<?php if ( $item['edit_link'] ) : ?> | <a href="<?php echo esc_url( $item['edit_link'] ); ?>">ویرایش</a><?php endif; ?>
							</div>
						</td>
						<td><code><?php echo esc_html( $item['key'] ); ?></code></td>
						<td>
							<?php if ( empty( $item['differences'] ) ) : ?>
								—
							<?php else : ?>
								<details>
									<summary><?php echo esc_html( number_format_i18n( count( $item['differences'] ) ) ); ?> مورد</summary>
									<table class="sa-laa-diffs">
										<thead><tr><th>فیلد</th><th>شدت</th><th>مخزن</th><th>زنده</th><th>یادداشت</th></tr></thead>
										<tbody>
										<?php foreach ( $item['differences'] as $diff ) : ?>
											<tr>
												<td><code><?php echo esc_html( $diff['field'] ); ?></code></td>
												<td><?php echo esc_html( $diff['severity'] ); ?></td>
												<td><?php echo esc_html( $diff['baseline'] ); ?></td>
												<td><?php echo esc_html( $diff['live'] ); ?></td>
												<td><?php echo esc_html( $diff['note'] ); ?></td>
											</tr>
										<?php endforeach; ?>
										</tbody>
									</table>
								</details>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php self::render_only_table( 'فقط در سایت زنده (در مخزن نیست)', $report['live_only'], true ); ?>
			<?php self::render_only_table( 'فقط در مخزن (هنوز روی سایت منتشر نشده)', $report['baseline_only'], false ); ?>

			<?php if ( ! empty( $report['duplicates']['live'] ) || ! empty( $report['duplicates']['baseline'] ) ) : ?>
				<h3>کلیدهای تکراری</h3>
				<?php foreach ( (array) $report['duplicates']['live'] as $key ) : ?>
					<p><span class="sa-laa-chip sa-laa-chip-bad">زنده</span> <code><?php echo esc_html( $key ); ?></code></p>
				<?php endforeach; ?>
				<?php foreach ( (array) $report['duplicates']['baseline'] as $key ) : ?>
					<p><span class="sa-laa-chip sa-laa-chip-bad">مخزن</span> <code><?php echo esc_html( $key ); ?></code></p>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="sa-laa-notes">
				<h3>چطور نتیجه را بخوانیم؟</h3>
				<ul>
					<li><strong>یکسان</strong> یعنی هیچ اختلاف واقعی (شدت medium/high) پیدا نشد؛ یادداشت‌های <code>info</code> مثل «فقط HTML فرق دارد» یا «متن مخزن جای‌نگهدار ایمپورت {{…}} دارد» اختلاف واقعی حساب نمی‌شوند.</li>
					<li><strong>محتوا</strong> اول با هش متن نرمال‌شده مقایسه می‌شود؛ اگر فقط قالب‌بندی فرق داشته باشد، با هش «متن ساده» مشخص می‌شود.</li>
					<li><strong>متاها</strong> فقط برای کلیدهایی گزارش می‌شوند که در مخزن مقدار دارند؛ متای خالی مخزن به‌معنی «نباید روی سایت باشد» نیست.</li>
					<li>«فقط در مخزن» معمولاً یعنی مقاله آماده است ولی هنوز ایمپورت/منتشر نشده؛ «فقط در سایت» یعنی روی سایت هست ولی در دادهٔ آمادهٔ مخزن تعریف نشده است.</li>
				</ul>
			</div>
		</section>
		<?php
	}

	/**
	 * جدول «فقط در یک طرف».
	 *
	 * @param string $heading تیتر.
	 * @param array  $items   آیتم‌ها.
	 * @param bool   $live    طرف زنده است؟
	 */
	private static function render_only_table( $heading, $items, $live ) {
		if ( empty( $items ) ) {
			return;
		}
		?>
		<h3><?php echo esc_html( $heading ); ?> (<?php echo esc_html( number_format_i18n( count( $items ) ) ); ?>)</h3>
		<table class="widefat striped sa-laa-table">
			<thead><tr><th>مقاله</th><th>کلید</th><?php echo $live ? '<th>پیوند</th>' : ''; ?></tr></thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item['title'] ); ?></td>
					<td><code><?php echo esc_html( $item['key'] ); ?></code></td>
					<?php if ( $live ) : ?>
						<td><?php if ( ! empty( $item['permalink'] ) ) : ?><a href="<?php echo esc_url( $item['permalink'] ); ?>" target="_blank" rel="noopener">مشاهده</a><?php endif; ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
