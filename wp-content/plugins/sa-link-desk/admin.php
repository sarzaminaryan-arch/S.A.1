<?php
namespace SA\LinkDesk;
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', static function () {
	$hook = add_management_page( 'آمار پیوندها', 'آمار پیوندها', 'manage_options', 'sa-link-desk', __NAMESPACE__ . '\\screen' );
	add_action( 'load-' . $hook, static function () {
		wp_enqueue_script( 'sa-link-desk', plugins_url( 'audit.js', __FILE__ ), array(), '1.0.0', true );
		wp_localize_script( 'sa-link-desk', 'saLinkDesk', array( 'nonce' => wp_create_nonce( 'sa_link_inventory' ), 'home' => home_url( '/' ) ) );
	} );
} );
add_action( 'admin_init', static function () {
	register_setting( 'sa_link_settings', 'sa_link_desk', array( 'sanitize_callback' => static function ( $v ) {
		return array( 'geo' => ! empty( $v['geo'] ), 'wiki' => ! empty( $v['wiki'] ) );
	} ) );
} );

add_action( 'wp_ajax_sa_link_inventory', static function () {
	check_ajax_referer( 'sa_link_inventory' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( null, 403 );
	}
	$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment' ) );
	$query = new \WP_Query( array( 'post_type' => array_values( $types ), 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 50, 'paged' => max( 1, absint( $_POST['page'] ?? 1 ) ), 'orderby' => 'ID', 'order' => 'ASC', 'ignore_sticky_posts' => true ) );
	$pages = array();
	foreach ( $query->posts as $p ) {
		$item = array( 'url' => get_permalink( $p ), 'type' => $p->post_type );
		if ( 'province' === $p->post_type && function_exists( 'sa_county_link_roster' ) ) {
			$item['members'] = array_column( sa_county_link_roster( $p ), 'url' );
		}
		$pages[] = $item;
	}
	wp_send_json_success( array( 'pages' => $pages, 'last' => $query->max_num_pages ) );
} );

function screen() {
	$v = options(); ?>
	<div class="wrap" dir="rtl"><h1>آمار پیوندهای سایت</h1>
	<p>صفحه‌های عمومیِ منتشرشده و بدون رمز، از جمله نوشته‌ها، برگه‌ها و نوع‌های سفارشی؛ هر بار یک درخواست بدون کوکی. منو و پاورقی در کل صفحه حساب می‌شوند؛ آمار بخش اصلی و پیوندهای جغرافیایی جداست. آرشیوها، پیوست‌ها، لینک‌های ساخته‌شده بعد از اجرای جاوااسکریپت و بک‌لینک سایت‌های دیگر در این شمارش نیستند.</p>
	<button type="button" class="button button-primary" id="sald-start">شروع گزارش تازه</button>
	<button type="button" class="button" id="sald-stop" disabled>توقف</button>
	<button type="button" class="button" id="sald-download" disabled>دانلود گزارش JSON</button>
	<p id="sald-progress" role="status"></p><pre id="sald-summary"></pre>
	<p>تا پایان، این برگه را باز نگه دارید. گزارش در مرورگر است؛ پیش از خروج یا ذخیرهٔ تنظیمات آن را دانلود کنید. خطاهای دریافت جزو صفحه‌های بدون لینک شمرده نمی‌شوند.</p>
	<hr><h2>اصلاح در خروجی عمومی</h2><p>ابتدا گزارش و پشتیبان بگیرید. پس از ذخیره یا خاموش‌کردن گزینه‌ها، کش صفحه/CDN را پاک کنید و گزارش تازه بگیرید. متن ذخیره‌شده، اسکیما و سابقهٔ منبع عوض نمی‌شوند.</p>
	<form method="post" action="options.php"><?php settings_fields( 'sa_link_settings' ); ?>
	<label><input type="checkbox" name="sa_link_desk[geo]" value="1" <?php checked( ! empty( $v['geo'] ) ); ?>> اصلاح مقصدهای منتقل‌شدهٔ تأییدشده و برداشتن لینک به خود یا مقصد تکراری در متن استان و شهرستان (نه منو یا فهرست هم‌استانی‌ها)</label><br>
	<label><input type="checkbox" name="sa_link_desk[wiki]" value="1" <?php checked( ! empty( $v['wiki'] ) ); ?>> حذف قابلیت کلیک لینک‌های ویکی‌پدیا از صفحه‌های تکی منتشرشده؛ نام منبع و متن ارجاع می‌ماند</label>
	<?php submit_button( 'ذخیرهٔ تنظیمات' ); ?></form></div><?php
}
