<?php
/**
 * Plugin Name: SA SEO DB Fixes (one-shot)
 * Description: عملیات یک‌بارمصرف دیتابیس برای SEO سرزمین آریان — ۱) تنظیم صفحهٔ نوشته‌ها (page_for_posts) روی برگهٔ وبلاگ، ۲) تغییر نام برگهٔ policy به editorial-policy + اصلاح لینک‌های منو. با گزارش خشک، تأیید صریح و لاگ برگشت‌پذیر.
 * Version: 1.0.0
 * Author: S.A. Agent
 * License: GPL-2.0-or-later
 *
 * @package SA_SEO_DB_Fixes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * خواندن/افزودن لاگ عملیات (برای برگشت‌پذیری).
 *
 * @param array|null $entry رکورد جدید؛ null = فقط خواندن.
 * @return array
 */
function sa_dbf_log( $entry = null ) {
	$log = (array) get_option( 'sa_db_fixes_log', array() );
	if ( is_array( $entry ) ) {
		$entry['at'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$log[]       = $entry;
		update_option( 'sa_db_fixes_log', array_slice( $log, -50 ), false );
	}
	return $log;
}

/**
 * پاک‌سازی کش LiteSpeed در صورت وجود.
 *
 * @return void
 */
function sa_dbf_purge() {
	if ( defined( 'LSCWP_V' ) || class_exists( 'LiteSpeed_Cache_API' ) || did_action( 'litespeed_init' ) ) {
		do_action( 'litespeed_purge_all' );
	}
}

/**
 * دادهٔ عملیات ۱: صفحهٔ نوشته‌ها.
 *
 * @return array
 */
function sa_dbf_op1_state() {
	$pages   = (array) get_option( 'sa_default_pages', array() );
	$current = (int) get_option( 'page_for_posts' );
	$target  = isset( $pages['blog'] ) ? (int) $pages['blog'] : 0;
	$cur_p   = $current ? get_post( $current ) : null;
	$tgt_p   = $target ? get_post( $target ) : null;
	return array(
		'current_id'     => $current,
		'current_state'  => $cur_p ? ( $cur_p->post_status . ' / ' . $cur_p->post_name ) : 'برگه وجود ندارد (حذف‌شده)',
		'target_id'      => $target,
		'target_state'   => $tgt_p ? ( $tgt_p->post_status . ' / ' . $tgt_p->post_name . ' / ' . $tgt_p->post_title ) : 'در sa_default_pages.blog یافت نشد',
		'target_valid'   => (bool) $tgt_p && 'page' === $tgt_p->post_type && 'publish' === $tgt_p->post_status,
		'already_done'   => $current === $target && $current > 0,
		'show_on_front'  => (string) get_option( 'show_on_front' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
	);
}

/**
 * اجرای عملیات ۱.
 *
 * @return array گزارش قبل/بعد.
 */
function sa_dbf_op1_apply() {
	$before = sa_dbf_op1_state();
	if ( ! $before['target_valid'] ) {
		return array( 'ok' => false, 'msg' => 'مقصد نامعتبر است؛ هیچ تغییری اعمال نشد.', 'before' => $before, 'after' => $before );
	}
	update_option( 'page_for_posts', $before['target_id'] );
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'     => 'page_for_posts',
			'before' => $before['current_id'],
			'after'  => $before['target_id'],
		)
	);
	return array( 'ok' => true, 'msg' => 'page_for_posts از ' . $before['current_id'] . ' به ' . $before['target_id'] . ' تغییر کرد. /blog/ اکنون آرشیو نوشته‌هاست.', 'before' => $before, 'after' => sa_dbf_op1_state() );
}

/**
 * دادهٔ عملیات ۲: برگهٔ سیاست تحریریه.
 *
 * @return array
 */
function sa_dbf_op2_state() {
	$pages   = (array) get_option( 'sa_default_pages', array() );
	$pid     = isset( $pages['policy'] ) ? (int) $pages['policy'] : 0;
	$post    = $pid ? get_post( $pid ) : null;
	$conflict = get_page_by_path( 'editorial-policy', OBJECT, 'page' );
	$menus   = array();
	if ( $post ) {
		$items = get_posts(
			array(
				'post_type'      => 'nav_menu_item',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'meta_query'     => array( // phpcs:ignore
					array( 'key' => '_menu_item_object_id', 'value' => $pid ),
				),
			)
		);
		foreach ( $items as $it ) {
			$menus[] = 'آیتم منو #' . $it->ID . ' (نوع: ' . get_post_meta( $it->ID, '_menu_item_type', true ) . ')';
		}
	}
	$custom = get_posts(
		array(
			'post_type'      => 'nav_menu_item',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			's'              => '/policy/',
		)
	);
	$custom_links = array();
	foreach ( $custom as $c ) {
		if ( 'custom' === get_post_meta( $c->ID, '_menu_item_type', true ) && false !== strpos( (string) $c->post_content, '/policy/' ) ) {
			$custom_links[] = $c->ID . ': ' . $c->post_content;
		}
	}
	return array(
		'page_id'        => $pid,
		'exists'         => (bool) $post,
		'slug'           => $post ? $post->post_name : '',
		'title'          => $post ? $post->post_title : '',
		'status'         => $post ? $post->post_status : '',
		'chars'          => $post ? mb_strlen( (string) $post->post_content ) : 0,
		'conflict_id'    => $conflict ? (int) $conflict->ID : 0,
		'object_items'   => $menus,
		'custom_links'   => $custom_links,
		'already_done'   => $post && 'editorial-policy' === $post->post_name,
	);
}

/**
 * اجرای عملیات ۲.
 *
 * @return array گزارش قبل/بعد.
 */
function sa_dbf_op2_apply() {
	$before = sa_dbf_op2_state();
	if ( ! $before['exists'] || 'publish' !== $before['status'] ) {
		return array( 'ok' => false, 'msg' => 'برگهٔ policy یافت/منتشر نشد؛ تغییری اعمال نشد.', 'before' => $before, 'after' => $before );
	}
	if ( $before['already_done'] ) {
		return array( 'ok' => true, 'msg' => 'پیش‌تر انجام شده بود (نامک = editorial-policy).', 'before' => $before, 'after' => $before );
	}
	if ( $before['conflict_id'] ) {
		return array( 'ok' => false, 'msg' => 'تداخل: برگهٔ دیگری با نامک editorial-policy موجود است (#' . $before['conflict_id'] . '). دستی بررسی کنید.', 'before' => $before, 'after' => $before );
	}
	$new_id = wp_update_post(
		array(
			'ID'        => $before['page_id'],
			'post_name' => 'editorial-policy',
		),
		true
	);
	if ( is_wp_error( $new_id ) ) {
		return array( 'ok' => false, 'msg' => 'خطا در تغییر نام: ' . $new_id->get_error_message(), 'before' => $before, 'after' => $before );
	}
	// اصلاح لینک‌های سفارشی منوها به /policy/.
	$fixed_links = 0;
	foreach ( $before['custom_links'] as $row ) {
		$item_id = (int) explode( ':', $row, 2 )[0];
		$item    = get_post( $item_id );
		if ( $item && false !== strpos( (string) $item->post_content, '/policy/' ) ) {
			wp_update_post(
				array(
					'ID'           => $item_id,
					'post_content' => home_url( '/editorial-policy/' ),
				)
			);
			$fixed_links++;
		}
	}
	flush_rewrite_rules( false );
	sa_dbf_purge();
	sa_dbf_log(
		array(
			'op'          => 'policy_rename',
			'page_id'     => $before['page_id'],
			'before_slug' => $before['slug'],
			'after_slug'  => get_post_field( 'post_name', $before['page_id'] ),
			'menu_links'  => $fixed_links,
		)
	);
	return array(
		'ok'     => true,
		'msg'    => 'برگهٔ #' . $before['page_id'] . ' به نامک editorial-policy تغییر یافت؛ ' . $fixed_links . ' لینک سفارشی منو اصلاح شد. ۳۰۱ِ /policy/ (قالب ۲٫۲۲٫۳۵) اکنون فعال است.',
		'before' => $before,
		'after'  => sa_dbf_op2_state(),
	);
}

/**
 * منوی ابزارها.
 */
function sa_dbf_menu() {
	add_management_page( 'صفحه‌ها و تنظیمات SEO', 'صفحه‌ها و تنظیمات SEO', 'manage_options', 'sa-seo-db-fixes', 'sa_dbf_render' );
}
add_action( 'admin_menu', 'sa_dbf_menu' );

/**
 * رندر صفحه.
 */
function sa_dbf_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$result = null;
	$error  = '';
	if ( isset( $_POST['sa_dbf_op'] ) ) {
		$op = sanitize_key( (string) $_POST['sa_dbf_op'] );
		if ( ! isset( $_POST['sa_dbf_nonce'] ) || ! wp_verify_nonce( sanitize_key( (string) $_POST['sa_dbf_nonce'] ), 'sa_dbf_' . $op ) ) {
			$error = 'کلید اعتبارسنجی نامعتبر بود؛ هیچ تغییری اعمال نشد.';
		} elseif ( empty( $_POST['sa_dbf_confirm'] ) ) {
			$error = 'جعبهٔ تأیید علامت نخورد؛ هیچ تغییری اعمال نشد.';
		} elseif ( 'op1' === $op ) {
			$result = sa_dbf_op1_apply();
		} elseif ( 'op2' === $op ) {
			$result = sa_dbf_op2_apply();
		} else {
			$error = 'عملیات ناشناخته.';
		}
	}
	$s1 = sa_dbf_op1_state();
	$s2 = sa_dbf_op2_state();
	?>
	<div class="wrap" dir="rtl" style="max-width:960px">
		<h1>صفحه‌ها و تنظیمات SEO — SA SEO DB Fixes 1.0.0</h1>
		<p>دو عملیات یک‌بارمصرف با گزارش قبل/بعد و لاگ برگشت‌پذیر (گزینهٔ <code>sa_db_fixes_log</code>). پیش از اجرا، پشتیبان UpdraftPlus تازه بگیرید. پس از اتمام هر دو، همین افزونه را حذف کنید.</p>

		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<?php if ( $result ) : ?>
			<div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?>"><p><strong><?php echo esc_html( $result['msg'] ); ?></strong></p></div>
		<?php endif; ?>

		<h2>عملیات ۱ — صفحهٔ نوشته‌ها (page_for_posts)</h2>
		<table class="widefat striped" style="max-width:760px">
			<tr><th>وضعیت فعلی</th><td>شناسهٔ <?php echo (int) $s1['current_id']; ?> — <?php echo esc_html( $s1['current_state'] ); ?></td></tr>
			<tr><th>مقصد (sa_default_pages.blog)</th><td>شناسهٔ <?php echo (int) $s1['target_id']; ?> — <?php echo esc_html( $s1['target_state'] ); ?></td></tr>
			<tr><th>show_on_front / page_on_front</th><td><?php echo esc_html( $s1['show_on_front'] ); ?> / <?php echo (int) $s1['page_on_front']; ?></td></tr>
		</table>
		<?php if ( $s1['already_done'] ) : ?>
			<p class="description">✔ پیش‌تر انجام شده — نیازی به اجرا نیست.</p>
		<?php elseif ( $s1['target_valid'] ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'sa_dbf_op1', 'sa_dbf_nonce' ); ?>
				<input type="hidden" name="sa_dbf_op" value="op1">
				<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان دارم و می‌دانم page_for_posts تغییر می‌کند (قابل بازگردانی با لاگ زیر).</label>
				<?php submit_button( 'اجرای عملیات ۱', 'primary', 'submit', true ); ?>
			</form>
		<?php else : ?>
			<p class="description">مقصد معتبر نیست — ابتدا برگهٔ وبلاگ را بررسی کنید.</p>
		<?php endif; ?>

		<h2>عملیات ۲ — برگهٔ سیاست تحریریه (policy → editorial-policy)</h2>
		<table class="widefat striped" style="max-width:760px">
			<tr><th>برگه</th><td>#<?php echo (int) $s2['page_id']; ?> — «<?php echo esc_html( $s2['title'] ); ?>» — نامک فعلی: <code><?php echo esc_html( $s2['slug'] ); ?></code> — وضعیت: <?php echo esc_html( $s2['status'] ); ?> — محتوا: <?php echo (int) $s2['chars']; ?> نویسه</td></tr>
			<tr><th>تداخل نامک</th><td><?php echo $s2['conflict_id'] ? '⚠ برگهٔ #' . (int) $s2['conflict_id'] . ' با نامک editorial-policy وجود دارد!' : 'ندارد ✓'; ?></td></tr>
			<tr><th>آیتم‌های منو (object)</th><td><?php echo $s2['object_items'] ? esc_html( implode( ' | ', $s2['object_items'] ) ) . ' — خودکار با تغییر نام، پیوندشان به‌روز می‌شود' : 'یافت نشد'; ?></td></tr>
			<tr><th>لینک‌های سفارشی منو به /policy/</th><td><?php echo $s2['custom_links'] ? esc_html( implode( ' | ', $s2['custom_links'] ) ) : 'یافت نشد ✓'; ?></td></tr>
		</table>
		<?php if ( $s2['already_done'] ) : ?>
			<p class="description">✔ پیش‌تر انجام شده — نامک همین حالا editorial-policy است.</p>
		<?php elseif ( $s2['exists'] && ! $s2['conflict_id'] ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'sa_dbf_op2', 'sa_dbf_nonce' ); ?>
				<input type="hidden" name="sa_dbf_op" value="op2">
				<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_dbf_confirm" value="1"> تأیید: پشتیبان دارم و می‌دانم نامک برگه عوض می‌شود (۳۰۱ِ /policy/ با قالب ۲٫۲۲٫۳۵ برقرار می‌شود).</label>
				<?php submit_button( 'اجرای عملیات ۲', 'primary', 'submit', true ); ?>
			</form>
		<?php endif; ?>

		<h2>لاگ عملیات (برگشت‌پذیری)</h2>
		<?php $log = sa_dbf_log(); ?>
		<?php if ( $log ) : ?>
			<table class="widefat striped"><tr><th>زمان</th><th>جزئیات</th></tr>
			<?php foreach ( array_reverse( $log ) as $row ) : ?>
				<tr><td><?php echo esc_html( (string) $row['at'] ); ?></td><td><code><?php echo esc_html( wp_json_encode( $row, JSON_UNESCAPED_UNICODE ) ); ?></code></td></tr>
			<?php endforeach; ?></table>
		<?php else : ?>
			<p class="description">هنوز عملیاتی اجرا نشده.</p>
		<?php endif; ?>
	</div>
	<?php
}
