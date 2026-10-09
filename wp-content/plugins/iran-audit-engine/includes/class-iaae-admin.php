<?php
/**
 * Engine-only configuration. All saved values are stored in iaa_settings.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menus' ) );
		add_action( 'admin_post_iaae_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menus() {
		add_submenu_page(
			'tools.php',
			'تنظیمات موتور ممیزی',
			'تنظیمات موتور',
			'iaa_manage',
			'iaa-engine-settings',
			array( __CLASS__, 'settings_page' )
		);
	}

	public static function settings_page() {
		if ( ! current_user_can( 'iaa_manage' ) ) {
			wp_die( esc_html__( 'برای مدیریت موتور ممیزی مجوز ندارید.', 'iran-audit-engine' ), '', array( 'response' => 403 ) );
		}
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$map = IAAE_Profile_Mapper::map();
		$profiles = IAAE_Profile_Mapper::profiles();
		$rendered = (bool) IAAE_Database::get_setting( 'rendered_checks', false );
		$external = (bool) IAAE_Database::get_setting( 'external_link_checks', false );
		$retention = min( 100, max( 1, absint( IAAE_Database::get_setting( 'retention_reports', 10 ) ) ) );
		?>
		<div class="wrap" dir="rtl">
			<h1><?php esc_html_e( 'تنظیمات موتور ممیزی', 'iran-audit-engine' ); ?></h1>
			<?php if ( isset( $_GET['settings-updated'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات افزونه ذخیره شد.', 'iran-audit-engine' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'نگاشت نوع‌نوشته به پروفایل روی گزارش اثر دارد. موارد مبهم را خالی بگذارید تا قانون‌های وابسته «اطلاعات ناکافی» شوند؛ موتور نام نوع‌نوشته را حدس نمی‌زند.', 'iran-audit-engine' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="iaae_save_settings" />
				<?php wp_nonce_field( 'iaae_save_settings', 'iaae_nonce' ); ?>
				<h2><?php esc_html_e( 'نگاشت پروفایل', 'iran-audit-engine' ); ?></h2>
				<table class="widefat striped" style="max-width:900px">
					<thead><tr><th><?php esc_html_e( 'نوع‌نوشته', 'iran-audit-engine' ); ?></th><th><?php esc_html_e( 'پروفایل ممیزی', 'iran-audit-engine' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( (array) $types as $type => $object ) : ?>
						<?php if ( in_array( $type, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) { continue; } ?>
						<tr>
							<th scope="row"><code><?php echo esc_html( $type ); ?></code> — <?php echo esc_html( isset( $object->labels->name ) ? $object->labels->name : $type ); ?></th>
							<td>
								<?php if ( 'city' === $type ) : ?>
									<input type="hidden" name="profile_map[<?php echo esc_attr( $type ); ?>]" value="county" />
									<strong><?php esc_html_e( 'شهر (City)', 'iran-audit-engine' ); ?></strong>
									<span class="description"><?php esc_html_e( 'پروفایل قواعد county فقط alias داخلی API 1.1 است؛ موجودیت جداگانه‌ای نیست.', 'iran-audit-engine' ); ?></span>
								<?php else : ?>
									<select name="profile_map[<?php echo esc_attr( $type ); ?>]">
										<option value=""><?php esc_html_e( 'بدون نگاشت / نیازمند تصمیم', 'iran-audit-engine' ); ?></option>
										<?php foreach ( (array) $profiles as $slug => $profile ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( isset( $map[ $type ] ) ? $map[ $type ] : '', $slug ); ?>><?php echo esc_html( 'county' === $slug ? 'شهر' : ( isset( $profile['label'] ) ? $profile['label'] : $slug ) ); ?> (<?php echo esc_html( 'county' === $slug ? 'city / API 1.1' : $slug ); ?>)</option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'منابع بررسی', 'iran-audit-engine' ); ?></h2>
				<p><label><input type="checkbox" name="rendered_checks" value="1" <?php checked( $rendered ); ?> /> <?php esc_html_e( 'دریافت HTML رندرشدهٔ همان میزبان برای بررسی SEO head و Schema (خاموش به‌صورت پیش‌فرض؛ ابتدا فقط روی staging فعال شود).', 'iran-audit-engine' ); ?></label></p>
				<p><label><input type="checkbox" name="external_link_checks" value="1" <?php checked( $external ); ?> /> <?php esc_html_e( 'بررسی HTTP لینک‌های بیرونی (در نسخهٔ فعلی هنوز اجرا نمی‌شود؛ این گزینه فقط ترجیح را ذخیره می‌کند).', 'iran-audit-engine' ); ?></label></p>
				<p><label for="iaae-retention-reports"><?php esc_html_e( 'حداکثر گزارش نگهداری‌شده برای هر نوشته (۱ تا ۱۰۰؛ پیش‌فرض ۱۰):', 'iran-audit-engine' ); ?></label> <input id="iaae-retention-reports" type="number" min="1" max="100" step="1" name="retention_reports" value="<?php echo esc_attr( $retention ); ?>" /></p>
				<p class="description"><?php esc_html_e( 'PageSpeed، AI و اجرای زمان‌بندی‌شده در این نسخه فعال نیستند. ذخیرهٔ تنظیمات فقط در جدول اختصاصی افزونه انجام می‌شود.', 'iran-audit-engine' ); ?></p>
				<?php submit_button( __( 'ذخیرهٔ تنظیمات موتور', 'iran-audit-engine' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		if ( ! current_user_can( 'iaa_manage' ) ) {
			wp_die( esc_html__( 'برای ذخیرهٔ تنظیمات مجوز ندارید.', 'iran-audit-engine' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'iaae_save_settings', 'iaae_nonce' );
		$profile_map = isset( $_POST['profile_map'] ) && is_array( $_POST['profile_map'] ) ? wp_unslash( $_POST['profile_map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$retention = isset( $_POST['retention_reports'] ) ? absint( wp_unslash( $_POST['retention_reports'] ) ) : 10; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $retention < 1 || $retention > 100 ) {
			wp_die( esc_html__( 'تعداد گزارش‌های قابل نگهداری باید بین ۱ و ۱۰۰ باشد.', 'iran-audit-engine' ), '', array( 'response' => 400 ) );
		}
		$result = IAAE_Profile_Mapper::save( $profile_map );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}
		$rendered_saved = IAAE_Database::set_setting( 'rendered_checks', ! empty( $_POST['rendered_checks'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$external_saved = IAAE_Database::set_setting( 'external_link_checks', ! empty( $_POST['external_link_checks'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$retention_saved = IAAE_Database::set_setting( 'retention_reports', $retention );
		if ( ! $rendered_saved || ! $external_saved || ! $retention_saved ) {
			wp_die( esc_html__( 'ذخیرهٔ یک یا چند تنظیم ناموفق بود؛ جدول ممیزی تنظیمات و خطای پایگاه داده را بررسی کنید.', 'iran-audit-engine' ), '', array( 'response' => 500 ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'iaa-engine-settings', 'settings-updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function notices() {
		if ( ! current_user_can( 'iaa_view' ) || IAAE_Database::tables_exist() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'جدول‌های موتور ممیزی هنوز نصب نشده‌اند. افزونه را یک‌بار غیرفعال و فعال کنید یا مدیر سایت را برای اجرای مهاجرت schema آگاه کنید.', 'iran-audit-engine' ) . '</p></div>';
	}
}
