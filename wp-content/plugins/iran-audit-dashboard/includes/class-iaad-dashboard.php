<?php
/**
 * Admin-only client for the Engine REST API. This plugin has no database access.
 *
 * @package IranAuditDashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAD_Dashboard {
	const PAGE_SLUG = 'iran-audit-dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function menu() {
		add_menu_page(
			'داشبورد ممیزی',
			'ممیزی سایت',
			'edit_others_posts',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-search',
			58
		);
	}

	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		$script_path = IAAD_PATH . 'assets/dashboard.js';
		$style_path = IAAD_PATH . 'assets/dashboard.css';
		wp_enqueue_style( 'iaad-dashboard', IAAD_URL . 'assets/dashboard.css', array(), file_exists( $style_path ) ? (string) filemtime( $style_path ) : IAAD_VERSION );
		wp_enqueue_script( 'iaad-dashboard', IAAD_URL . 'assets/dashboard.js', array(), file_exists( $script_path ) ? (string) filemtime( $script_path ) : IAAD_VERSION, true );
		$config = array(
			'apiRoot' => wp_make_link_relative( rest_url( 'iran-audit/v1/' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'pageUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'i18n'    => array(
				'loading' => 'در حال بارگذاری…',
				'engineMissing' => 'موتور ممیزی (Iran Audit Engine) فعال نیست یا API نسخهٔ ۱٫۲ در دسترس نیست. برای مشاهدهٔ داده‌ها ابتدا Engine نسخهٔ 0.2.0 را نصب و فعال کنید.',
				'error' => 'دریافت اطلاعات از موتور ممیزی ناموفق بود.',
				'noData' => 'داده‌ای برای نمایش وجود ندارد.',
				'auditQueued' => 'درخواست ممیزی ثبت شد.',
				'confirmAudit' => 'برای آغاز ممیزی نوشته‌های انتخاب‌شده مطمئن هستید؟',
				'networkError' => 'ارتباط با API برقرار نشد. وضعیت فعال‌بودن افزونهٔ Engine را بررسی کنید.',
			),
		);
		wp_add_inline_script( 'iaad-dashboard', 'window.IAAD_CONFIG = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ) . ';', 'before' );
	}

	public static function render() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'برای مشاهدهٔ داشبورد ممیزی مجوز ندارید.', 'iran-audit-dashboard' ) );
		}
		?>
		<div class="wrap iaad-wrap" dir="rtl">
			<h1><?php esc_html_e( 'داشبورد ممیزی ساختار و SEO', 'iran-audit-dashboard' ); ?></h1>
			<p class="iaad-lead"><?php esc_html_e( 'این داشبورد فقط از API افزونهٔ Engine استفاده می‌کند و در پایگاه دادهٔ سایت چیزی نمی‌نویسد.', 'iran-audit-dashboard' ); ?></p>
			<div id="iaad-app" aria-live="polite">
				<div class="iaad-loading"><span class="spinner is-active"></span><span><?php esc_html_e( 'در حال اتصال به موتور ممیزی…', 'iran-audit-dashboard' ); ?></span></div>
				<noscript><?php esc_html_e( 'برای استفاده از داشبورد، JavaScript را فعال کنید.', 'iran-audit-dashboard' ); ?></noscript>
			</div>
		</div>
		<?php
	}
}
