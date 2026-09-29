<?php
/**
 * Plugin Name: سرزمین آریان — درون‌ریزی یک‌جای همه‌ی ۳۱ استان (رفع باگ + بازنویسی کامل)
 * Plugin URI:  https://github.com/sarzaminaryan-arch/S.A.1
 * Description: یک دکمه‌ی واحد که هر ۳۱ استان (سه دسته‌ی b01+b02+b03) را در یک اجرا از نو درون‌ریزی می‌کند: محتوای مقاله، فیلدهای مدل داده، FAQ، منابع، سئو و تصویر شاخص هر استان کاملاً با آخرین بسته‌ی داده‌ی مخزن جایگزین می‌شود — حتی استان‌هایی که از قبل منتشر شده‌اند (مثل باگ اردبیل که با این ابزار هم رفع می‌شود). وضعیت انتشار فعلی هر استان (پیش‌نویس/منتشرشده) دست‌نخورده می‌ماند؛ تصویر شاخص موجود فقط اگر با فایل بسته یکی نباشد جایگزین می‌شود (بدون آپلود تکراری). نیاز به فعال‌بودن افزونه‌های b01/b02/b03 دارد (یا داده‌ی هر سه به‌صورت مستقل از طریق این افزونه هم قابل ثبت است).
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author:      سرزمین آریان
 * Author URI:  https://sarzaminaryan.ir
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sa-province-importer
 *
 * @package Sarzaminaryan_Province_Importer_All
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_PI_ALL_VERSION', '1.0.0' );
define( 'SA_PI_ALL_FILE', __FILE__ );

// Shared importer core (identical to sa-province-importer-b01/b02/b03 — class_exists guarded,
// so it is harmless whichever of the four plugins happens to load first).
require_once __DIR__ . '/includes/class-sa-province-importer.php';

if ( ! class_exists( 'SA_Province_Importer_All' ) ) :

	/**
	 * One-click "import + fully overwrite all 31 provinces" coordinator.
	 *
	 * Does not own any data/ directory itself: it drives the already-registered
	 * b01/b02/b03 batches on the shared SA_Province_Importer singleton, running
	 * import() on each with overwrite_published forced on by default so that
	 * already-published provinces (which are otherwise protected) are refreshed
	 * too — this is the sanctioned, safe way to push a repo-side content fix
	 * (e.g. the Ardabil truncation bug) to a province that is already live.
	 *
	 * Post status (draft/publish) is never changed by the underlying core: it
	 * always keeps $existing->post_status as-is. The featured image import is
	 * hash-based and idempotent, so an unchanged bundled image is simply
	 * re-attached (no duplicate, no visible change) rather than "overwritten".
	 */
	class SA_Province_Importer_All {

		const CAP       = 'manage_options';
		const PAGE_SLUG = 'sa-province-importer-all';
		const NONCE     = 'sa_province_import_all';

		/**
		 * Singleton.
		 *
		 * @var SA_Province_Importer_All|null
		 */
		private static $instance = null;

		/**
		 * Instance.
		 *
		 * @return SA_Province_Importer_All
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Hooks.
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 31 );
			add_action( 'admin_post_' . self::NONCE, array( $this, 'handle_post' ) );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'sa-province import-all', array( $this, 'cli' ) );
			}
		}

		/**
		 * Registered batch ids on the shared core, sorted (b01, b02, b03, …).
		 *
		 * @return array
		 */
		private function batches() {
			$batches = SA_Province_Importer::instance()->batches();
			ksort( $batches );
			return $batches;
		}

		/**
		 * Default options for a "replace everything" run: forces overwrite of
		 * already-published provinces (the whole point of this tool) while the
		 * core keeps every post's current publish status unchanged.
		 *
		 * @return array
		 */
		private function default_options() {
			return array(
				'update_drafts'       => true,
				'overwrite_published' => true,
				'images'              => true,
				'rank_math'           => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
				'stubs'               => true,
			);
		}

		/**
		 * Run the import across every registered batch.
		 *
		 * @param array $opts Options (see default_options()).
		 * @return array Results keyed by "{batch_id}/{slug}".
		 */
		public function run( $opts = array() ) {
			$opts    = wp_parse_args( $opts, $this->default_options() );
			$core    = SA_Province_Importer::instance();
			$batches = $this->batches();
			if ( ! $batches ) {
				return array( '_error' => 'هیچ دسته‌ای ثبت نشده است — افزونه‌های b01/b02/b03 باید فعال باشند.' );
			}
			$results = array();
			foreach ( $batches as $batch_id => $batch ) {
				$r = $core->import( $batch_id, array(), $opts );
				if ( isset( $r['_error'] ) ) {
					$results[ $batch_id . '/_error' ] = array( 'action' => 'error', 'message' => $r['_error'] );
					continue;
				}
				foreach ( $r as $slug => $row ) {
					$results[ $batch_id . '/' . $slug ] = $row;
				}
			}
			return $results;
		}

		/**
		 * Admin page: submenu under the existing «درون‌ریزی استان‌ها» top-level menu
		 * when it exists (b01 registers it), otherwise its own top-level entry.
		 */
		public function admin_menu() {
			if ( class_exists( 'SA_Province_Importer' ) && self::parent_menu_exists() ) {
				// b01/b02/b03 already add a top-level "sa-province-importer" page; hang a submenu off it.
				add_submenu_page(
					'sa-province-importer',
					'درون‌ریزی یک‌جای همه‌ی استان‌ها',
					'🔁 درون‌ریزی یک‌جا (۳۱ استان)',
					self::CAP,
					self::PAGE_SLUG,
					array( $this, 'render_page' )
				);
				return;
			}
			// Fallback top-level entry when no b0x plugin has created the parent menu (e.g. this plugin alone active).
			add_menu_page(
				'درون‌ریزی یک‌جای همه‌ی استان‌ها',
				'درون‌ریزی همه‌ی استان‌ها',
				self::CAP,
				self::PAGE_SLUG,
				array( $this, 'render_page' ),
				'dashicons-update',
				31
			);
		}

		/**
		 * Whether the b0x parent menu page was already registered this request.
		 *
		 * @return bool
		 */
		private static function parent_menu_exists() {
			global $menu;
			if ( ! is_array( $menu ) ) {
				return false;
			}
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && 'sa-province-importer' === $item[2] ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Persian digits (falls back to the theme helper if present).
		 *
		 * @param mixed $n Number.
		 * @return string
		 */
		private function fa( $n ) {
			if ( function_exists( 'sa_fa_digits' ) ) {
				return sa_fa_digits( $n );
			}
			return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		}

		/**
		 * Handle the confirm form.
		 */
		public function handle_post() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( 'دسترسی ندارید.' );
			}
			check_admin_referer( self::NONCE );
			if ( empty( $_POST['confirm_all'] ) ) {
				set_transient( 'sa_pi_all_results_' . get_current_user_id(), array( '_error' => 'تیک تأیید «همه‌چیز را کاملاً جایگزین کن» زده نشده بود — چیزی اجرا نشد.' ), 300 );
				wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'done' => '1' ), admin_url( 'admin.php' ) ) );
				exit;
			}
			$opts = array(
				'update_drafts'       => true,
				'overwrite_published' => ! empty( $_POST['opt_overwrite_published'] ),
				'images'              => ! empty( $_POST['opt_images'] ),
				'rank_math'           => ! empty( $_POST['opt_rank_math'] ),
				'stubs'               => true,
			);
			$results = $this->run( $opts );
			set_transient( 'sa_pi_all_results_' . get_current_user_id(), $results, 300 );
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'done' => '1' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Admin page.
		 */
		public function render_page() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			$results = null;
			if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$results = get_transient( 'sa_pi_all_results_' . get_current_user_id() );
				delete_transient( 'sa_pi_all_results_' . get_current_user_id() );
			}

			echo '<div class="wrap" dir="rtl"><h1>درون‌ریزی یک‌جای همه‌ی ۳۱ استان</h1>';
			echo '<p>این صفحه هر ۳۱ استان (سه دسته‌ی b01، b02، b03) را در یک اجرا از آخرین بسته‌ی داده‌ی هر افزونه دوباره درون‌ریزی می‌کند: متن مقاله، فیلدهای مدل داده، FAQ، منابع، سئو و تصویر شاخص <strong>کاملاً جایگزین</strong> می‌شود — از جمله استان‌هایی که از قبل <strong>منتشر شده‌اند</strong> (با گزینه‌ی «بازنویسی نوشته‌های منتشرشده» که پیش‌فرض روشن است، چون هدف همین ابزار همین است).</p>';
			echo '<p><strong>چیزی که تغییر نمی‌کند:</strong> وضعیت انتشار هر استان (منتشرشده/پیش‌نویس) دقیقاً همان‌طور که هست می‌ماند؛ تصویر شاخص موجود فقط اگر فایل بسته‌ی همراه واقعاً فرق کرده باشد جایگزین می‌شود (در غیر این صورت همان تصویر دوباره وصل می‌شود، بدون تکرار).</p>';

			if ( $results ) {
				$this->render_results( $results );
			}

			$batches = $this->batches();
			if ( ! $batches ) {
				echo '<div class="notice notice-error"><p>هیچ دسته‌ای ثبت نشده است — افزونه‌های «درون‌ریزی استان‌ها (دسته‌ی ۱/۲/۳)» باید فعال باشند.</p></div></div>';
				return;
			}

			$total = 0;
			foreach ( $batches as $batch ) {
				$total += isset( $batch['manifest']['provinces'] ) ? count( $batch['manifest']['provinces'] ) : 0;
			}

			echo '<h2>پیش‌نمایش — ' . esc_html( $this->fa( $total ) ) . ' استان در ' . esc_html( $this->fa( count( $batches ) ) ) . ' دسته</h2>';
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>دسته</th><th>#</th><th>استان</th><th>نامک</th><th>وضعیت در سایت</th><th>آخرین نسخه‌ی درون‌ریزی‌شده</th></tr></thead><tbody>';
			foreach ( $batches as $batch_id => $batch ) {
				$rows = isset( $batch['manifest']['provinces'] ) ? (array) $batch['manifest']['provinces'] : array();
				foreach ( $rows as $row ) {
					$slug     = sanitize_title( $row['slug'] );
					$existing = post_type_exists( 'province' ) ? SA_Province_Importer::instance()->find_existing( $slug ) : null;
					echo '<tr>';
					echo '<td><code>' . esc_html( $batch_id ) . '</code></td>';
					echo '<td>' . esc_html( $this->fa( (int) $row['order'] ) ) . '</td>';
					echo '<td><strong>' . esc_html( $row['name_fa'] ) . '</strong></td>';
					echo '<td><code>' . esc_html( $slug ) . '</code></td>';
					if ( $existing ) {
						$badge = 'publish' === $existing->post_status ? '🟢 منتشرشده' : '🟡 پیش‌نویس';
						echo '<td>' . esc_html( $badge ) . ' <a href="' . esc_url( get_edit_post_link( $existing->ID ) ) . '">#' . esc_html( $this->fa( $existing->ID ) ) . '</a></td>';
						echo '<td>' . esc_html( get_post_meta( $existing->ID, '_sa_import_version', true ) ?: '—' ) . '</td>';
					} else {
						echo '<td>— هنوز ساخته نشده</td><td>—</td>';
					}
					echo '</tr>';
				}
			}
			echo '</tbody></table>';

			echo '<h2 style="margin-top:24px">اجرای کامل</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'مطمئنید؟ هر ' . esc_js( $this->fa( $total ) ) . ' استان دوباره از بسته‌ی داده نوشته می‌شود، از جمله استان‌های منتشرشده.\');">';
			wp_nonce_field( self::NONCE );
			echo '<input type="hidden" name="action" value="' . esc_attr( self::NONCE ) . '">';
			echo '<p><label><input type="checkbox" name="opt_overwrite_published" value="1" checked="checked" /> <strong>بازنویسی نوشته‌های منتشرشده هم</strong> (پیش‌فرض روشن — هدف همین ابزار است؛ اگر خاموش کنید، استان‌های منتشرشده مثل همیشه دست‌نخورده می‌مانند و فقط پیش‌نویس‌ها به‌روز می‌شوند)</label></p>';
			echo '<p><label><input type="checkbox" name="opt_images" value="1" checked="checked" /> بازبینی/تنظیم تصویر شاخص (اگر همان تصویر قبلی باشد چیزی عوض نمی‌شود)</label></p>';
			echo '<p><label><input type="checkbox" name="opt_rank_math" value="1" ' . checked( true, defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ), false ) . ' /> نوشتن فیلدهای Rank Math (اگر افزونه فعال باشد)</label></p>';
			echo '<p><label><input type="checkbox" name="confirm_all" value="1" required /> <strong>تأیید می‌کنم</strong>: می‌خواهم همه‌ی ' . esc_html( $this->fa( $total ) ) . ' استان از نو و کامل بازنویسی شوند.</label></p>';
			echo '<p><button type="submit" class="button button-primary button-hero">🔁 اجرای کامل — بازنویسی هر ' . esc_html( $this->fa( $total ) ) . ' استان</button></p>';
			echo '</form></div>';
		}

		/**
		 * Render combined results grouped by batch.
		 *
		 * @param array $results Results keyed by "{batch}/{slug}".
		 */
		private function render_results( $results ) {
			if ( isset( $results['_error'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $results['_error'] ) . '</p></div>';
				return;
			}
			$counts = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0 );
			foreach ( $results as $r ) {
				$a = isset( $r['action'] ) ? $r['action'] : '';
				if ( isset( $counts[ $a ] ) ) {
					$counts[ $a ]++;
				}
			}
			echo '<div class="notice notice-success"><p><strong>نتیجه:</strong> ';
			echo esc_html( sprintf( '%s به‌روزرسانی شد · %s ساخته شد · %s رد شد · %s خطا', $this->fa( $counts['updated'] ), $this->fa( $counts['created'] ), $this->fa( $counts['skipped'] ), $this->fa( $counts['error'] ) ) );
			echo '</p>';
			echo '<table class="widefat" style="max-width:1100px;margin-bottom:10px"><thead><tr><th>دسته/نامک</th><th>نتیجه</th><th>نوشته</th><th>تصویر</th></tr></thead><tbody>';
			$labels     = array(
				'created' => '✅ ساخته شد',
				'updated' => '🔄 به‌روزرسانی شد',
				'skipped' => '⏭ رد شد',
				'error'   => '❌ خطا',
			);
			$img_labels = array(
				'attached'     => '✅ تصویر شاخص تنظیم شد',
				'none'         => '— تصویری همراه بسته نیست',
				'missing_file' => '❌ فایل تصویر در افزونه نیست',
				'upload_error' => '❌ خطای بارگذاری',
				'attach_error' => '❌ خطای ثبت رسانه',
			);
			foreach ( $results as $key => $r ) {
				$action = isset( $r['action'] ) ? $r['action'] : '';
				echo '<tr><td><code>' . esc_html( $key ) . '</code></td>';
				echo '<td>' . esc_html( ( isset( $labels[ $action ] ) ? $labels[ $action ] : $action ) . ( ! empty( $r['message'] ) ? ' — ' . $r['message'] : '' ) ) . '</td>';
				if ( ! empty( $r['post_id'] ) ) {
					echo '<td><a href="' . esc_url( get_edit_post_link( (int) $r['post_id'] ) ) . '">#' . esc_html( $this->fa( (int) $r['post_id'] ) ) . ' ویرایش</a></td>';
				} else {
					echo '<td>—</td>';
				}
				$img = isset( $r['image'] ) ? $r['image'] : '';
				echo '<td>' . esc_html( isset( $img_labels[ $img ] ) ? $img_labels[ $img ] : $img ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		/**
		 * WP-CLI: wp sa-province import-all [--no-overwrite-published] [--no-images] [--rank-math]
		 *
		 * @param array $args       Positional args (unused).
		 * @param array $assoc_args Named args.
		 */
		public function cli( $args, $assoc_args ) {
			$opts = array(
				'update_drafts'       => true,
				'overwrite_published' => ! isset( $assoc_args['no-overwrite-published'] ),
				'images'              => ! isset( $assoc_args['no-images'] ),
				'rank_math'           => isset( $assoc_args['rank-math'] ) ? true : ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ),
				'stubs'               => true,
			);
			$results = $this->run( $opts );
			if ( isset( $results['_error'] ) ) {
				WP_CLI::error( $results['_error'] );
			}
			foreach ( $results as $key => $r ) {
				$line = sprintf( '%-28s %-8s %s %s', $key, $r['action'], ! empty( $r['post_id'] ) ? '#' . $r['post_id'] : '', isset( $r['message'] ) ? $r['message'] : '' );
				if ( isset( $r['image'] ) ) {
					$line .= ' image=' . $r['image'];
				}
				WP_CLI::line( $line );
			}
			WP_CLI::success( 'done — ۳۱ استان بررسی شد.' );
		}
	}

endif;

add_action(
	'plugins_loaded',
	function () {
		SA_Province_Importer_All::instance();
	},
	20
);
