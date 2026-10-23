<?php
/**
 * Plugin Name: SA RM Cleanup (one-shot)
 * Description: پاک‌سازی یک‌بارمصرف بقایای Rank Math از سایت سرزمین آریان — گزینه‌ها، جدول‌ها، کرون‌ها، متاها و قوانین بازنویسی. ابتدا گزارش خشک (بدون تغییر)، سپس اجرای قطعی با تأیید کاربر.
 * Version: 1.0.0
 * Author: S.A. Agent
 * License: GPL-2.0-or-later
 *
 * @package SA_RM_Cleanup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * الگوهای شناسایی بقایای Rank Math.
 *
 * @return array
 */
function sa_rmc_patterns() {
	return array( 'rank_math', 'rank-math' );
}

/**
 * آیا نام/کلید داده‌شده متعلق به Rank Math است؟
 *
 * @param string $name نام گزینه، کلید متا یا هوک.
 * @return bool
 */
function sa_rmc_is_rm( $name ) {
	$name = strtolower( (string) $name );
	foreach ( sa_rmc_patterns() as $p ) {
		if ( false !== strpos( $name, $p ) ) {
			return true;
		}
	}
	return false;
}

/**
 * فهرست گزینه‌های (options) مربوط به RM.
 *
 * @return array<int,array{name:string,autoload:string,size:int}>
 */
function sa_rmc_scan_options() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_id, option_name, autoload, LENGTH(option_value) AS size FROM {$wpdb->options}", ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		if ( sa_rmc_is_rm( $r['option_name'] ) ) {
			$out[] = array(
				'name'     => (string) $r['option_name'],
				'autoload' => (string) $r['autoload'],
				'size'     => (int) $r['size'],
			);
		}
	}
	usort( $out, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
	return $out;
}

/**
 * فهرست جدول‌های RM به همراه تعداد ردیف.
 *
 * @return array<int,array{name:string,rows:int}>
 */
function sa_rmc_scan_tables() {
	global $wpdb;
	$like   = $wpdb->esc_like( $wpdb->prefix . 'rank_math' ) . '%';
	$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) ); // phpcs:ignore
	$out    = array();
	foreach ( (array) $tables as $t ) {
		$t     = (string) $t;
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" ); // phpcs:ignore
		$out[] = array( 'name' => $t, 'rows' => $count );
	}
	sort( $out );
	return $out;
}

/**
 * فهرست کرون‌های RM.
 *
 * @return array<int,string>
 */
function sa_rmc_scan_crons() {
	$cron = (array) get_option( 'cron', array() );
	$out  = array();
	foreach ( $cron as $ts => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			if ( sa_rmc_is_rm( $hook ) ) {
				$out[] = $hook . ' @ ' . gmdate( 'Y-m-d H:i', (int) $ts ) . ' UTC';
			}
		}
	}
	sort( $out );
	return $out;
}

/**
 * شمارش متاهای RM در سه جدول متا.
 *
 * @return array<string,int>
 */
function sa_rmc_scan_metas() {
	global $wpdb;
	$out = array();
	foreach ( array( 'postmeta', 'termmeta', 'usermeta', 'commentmeta' ) as $m ) {
		$table = $wpdb->$m;
		if ( empty( $table ) ) {
			continue;
		}
		$like1 = $wpdb->esc_like( 'rank_math' ) . '%';
		$like2 = $wpdb->esc_like( 'rank-math' ) . '%';
		$c     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE meta_key LIKE %s OR meta_key LIKE %s", $like1, $like2 ) ); // phpcs:ignore
		$out[ $m ] = $c;
	}
	return $out;
}

/**
 * بررسی بقایای RM در قوانین بازنویسی و فهرست افزونه‌ها.
 *
 * @return array<string,mixed>
 */
function sa_rmc_scan_misc() {
	$rw   = (array) get_option( 'rewrite_rules', array() );
	$hits = array();
	foreach ( array_keys( $rw ) as $rule ) {
		if ( sa_rmc_is_rm( $rule ) || false !== strpos( (string) $rule, 'sitemap_index' ) || false !== strpos( (string) $rule, 'sitemap' ) ) {
			$hits[] = (string) $rule . ' => ' . (string) $rw[ $rule ];
		}
	}
	$active  = (array) get_option( 'active_plugins', array() );
	$rm_left = array_values( array_filter( $active, 'sa_rmc_is_rm' ) );
	return array(
		'rewrite_sitemap_rules' => array_slice( $hits, 0, 40 ),
		'rm_in_active_plugins'  => $rm_left,
		'rm_plugin_dir_exists'  => is_dir( WP_PLUGIN_DIR . '/seo-by-rank-math' ),
	);
}

/**
 * گزارش کامل وضعیت (برای dry-run و بعد از اجرا).
 *
 * @return array
 */
function sa_rmc_snapshot() {
	return array(
		'options' => sa_rmc_scan_options(),
		'tables'  => sa_rmc_scan_tables(),
		'crons'   => sa_rmc_scan_crons(),
		'metas'   => sa_rmc_scan_metas(),
		'misc'    => sa_rmc_scan_misc(),
	);
}

/**
 * اجرای پاک‌سازی قطعی.
 *
 * @return array گزارش قبل/بعد + فهرست کارهای انجام‌شده.
 */
function sa_rmc_apply() {
	global $wpdb;
	$before = sa_rmc_snapshot();
	$done   = array();

	// ۱) گزینه‌ها.
	$deleted_options = 0;
	foreach ( $before['options'] as $o ) {
		if ( false !== delete_option( $o['name'] ) ) {
			$deleted_options++;
		}
	}
	$done[] = sprintf( '%d گزینهٔ rank_math* حذف شد.', $deleted_options );

	// ۲) جدول‌ها.
	$dropped = 0;
	foreach ( $before['tables'] as $t ) {
		$name = str_replace( '`', '', $t['name'] );
		// phpcs:ignore WordPress.DB
		$wpdb->query( "DROP TABLE IF EXISTS `{$name}`" );
		$dropped++;
	}
	$done[] = sprintf( '%d جدول IR_rank_math_* حذف شد.', $dropped );

	// ۳) کرون‌ها.
	$cron        = (array) get_option( 'cron', array() );
	$unscheduled = 0;
	foreach ( $cron as $ts => $hooks ) {
		if ( ! is_array( $hooks ) ) {
			continue;
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			if ( sa_rmc_is_rm( $hook ) ) {
				wp_unschedule_event( (int) $ts, $hook );
				$unscheduled++;
			}
		}
	}
	$done[] = sprintf( '%d کرون RM حذف زمان‌بندی شد.', $unscheduled );

	// ۴) متاها.
	$metas_deleted = 0;
	foreach ( array( 'postmeta', 'termmeta', 'usermeta', 'commentmeta' ) as $m ) {
		$table = $wpdb->$m;
		if ( empty( $table ) ) {
			continue;
		}
		$like1 = $wpdb->esc_like( 'rank_math' ) . '%';
		$like2 = $wpdb->esc_like( 'rank-math' ) . '%';
		// phpcs:ignore WordPress.DB
		$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE meta_key LIKE %s OR meta_key LIKE %s", $like1, $like2 ) );
		if ( $n > 0 ) {
			$metas_deleted += $n;
			$done[]        = sprintf( '%s: %d ردیف متا حذف شد.', $m, $n );
		}
	}
	if ( 0 === $metas_deleted ) {
		$done[] = 'متایی با کلید rank_math* یافت نشد.';
	}

	// ۵) بازنویسی قوانین permalinks — حذف rewrite های مردهٔ سایت‌مپ RM.
	flush_rewrite_rules( false );
	$done[] = 'قوانین بازنویسی (rewrite_rules) بازسازی شد — /wp-sitemap.xml باید از این پس سایت‌مپ هسته باشد.';

	// ۶) پاک‌سازی کامل کش LiteSpeed (در صورت وجود).
	if ( did_action( 'litespeed_init' ) || class_exists( 'LiteSpeed_Cache_API' ) || defined( 'LSCWP_V' ) ) {
		do_action( 'litespeed_purge_all' );
		$done[] = 'رویداد litespeed_purge_all ارسال شد (پاک‌سازی کامل کش).';
	} else {
		$done[] = 'LiteSpeed تشخیص داده نشد؛ purge انجام نشد.';
	}

	$after = sa_rmc_snapshot();
	return array(
		'before' => $before,
		'after'  => $after,
		'done'   => $done,
		'at'     => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
	);
}

/**
 * ثبت منوی ابزارها.
 */
function sa_rmc_menu() {
	add_management_page( 'پاک‌سازی Rank Math', 'پاک‌سازی RM', 'manage_options', 'sa-rm-cleanup', 'sa_rmc_render' );
}
add_action( 'admin_menu', 'sa_rmc_menu' );

/**
 * رندر صفحهٔ ابزار: dry-run خودکار، اجرای قطعی با nonce و تأیید.
 */
function sa_rmc_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$applied = null;
	$error   = '';
	if ( isset( $_POST['sa_rmc_apply'] ) ) {
		if ( ! isset( $_POST['sa_rmc_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sa_rmc_nonce'] ), 'sa_rmc_apply' ) ) {
			$error = 'کلید اعتبارسنجی نامعتبر بود؛ هیچ تغییری اعمال نشد.';
		} elseif ( empty( $_POST['sa_rmc_confirm'] ) ) {
			$error = 'جعبهٔ تأیید علامت نخورد؛ هیچ تغییری اعمال نشد.';
		} else {
			$applied = sa_rmc_apply();
		}
	}
	$snap = $applied ? $applied['after'] : sa_rmc_snapshot();
	?>
	<div class="wrap" dir="rtl" style="max-width:1000px">
		<h1>پاک‌سازی بقایای Rank Math — SA RM Cleanup 1.0.0</h1>
		<p>این ابزار <strong>فقط</strong> داده‌های با نام/کلید <code>rank_math*</code> یا <code>rank-math*</code> را لمس می‌کند: گزینه‌ها، جدول‌های <code><?php echo esc_html( $GLOBALS['wpdb']->prefix . 'rank_math_*' ); ?></code>، کرون‌ها، متاها و بازسازی قوانین بازنویسی. <strong>هیچ نوشته، برگه یا محتوایی حذف نمی‌شود.</strong> پس از اتمام، همین افزونه را حذف کنید.</p>

		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( $applied ) : ?>
			<div class="notice notice-success"><p><strong>پاک‌سازی در <?php echo esc_html( $applied['at'] ); ?> اجرا شد.</strong></p>
				<ul style="list-style:disc inside"><?php foreach ( $applied['done'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?></ul>
			</div>
			<h2>مقایسهٔ قبل / بعد</h2>
			<table class="widefat striped" style="max-width:700px">
				<tr><th>بخش</th><th>قبل</th><th>بعد</th></tr>
				<tr><td>گزینه‌ها</td><td><?php echo count( $applied['before']['options'] ); ?></td><td><?php echo count( $applied['after']['options'] ); ?></td></tr>
				<tr><td>جدول‌ها</td><td><?php echo count( $applied['before']['tables'] ); ?></td><td><?php echo count( $applied['after']['tables'] ); ?></td></tr>
				<tr><td>کرون‌ها</td><td><?php echo count( $applied['before']['crons'] ); ?></td><td><?php echo count( $applied['after']['crons'] ); ?></td></tr>
				<tr><td>متاها (post/term/user/comment)</td><td><?php echo esc_html( implode( '/', $applied['before']['metas'] ) ); ?></td><td><?php echo esc_html( implode( '/', $applied['after']['metas'] ) ); ?></td></tr>
				<tr><td>قواعد بازنویسی سایت‌مپ</td><td><?php echo count( $applied['before']['misc']['rewrite_sitemap_rules'] ); ?></td><td><?php echo count( $applied['after']['misc']['rewrite_sitemap_rules'] ); ?></td></tr>
			</table>
			<p>اگر «بعد» در همهٔ ردیف‌ها صفر است، پاک‌سازی کامل بوده. اکنون: ۱) در LiteSpeed Cache یک Purge All دستی هم بزنید (احتیاط)، ۲) بازرس SA SEO Inspector را با گزینهٔ «دانلود بدون کاوش» اجرا و JSON را ارسال کنید، ۳) سپس سراغ GSC بروید.</p>
		<?php endif; ?>

		<h2>گزارش وضعیت فعلی (خشک — بدون هیچ تغییر)</h2>
		<h3>۱) گزینه‌ها (<?php echo count( $snap['options'] ); ?>)</h3>
		<details><summary>نمایش فهرست</summary>
		<table class="widefat striped"><tr><th>نام</th><th>autoload</th><th>حجم (بایت)</th></tr>
		<?php foreach ( array_slice( $snap['options'], 0, 200 ) as $o ) : ?>
			<tr><td><code><?php echo esc_html( $o['name'] ); ?></code></td><td><?php echo esc_html( $o['autoload'] ); ?></td><td><?php echo esc_html( number_format_i18n( $o['size'] ) ); ?></td></tr>
		<?php endforeach; ?></table></details>

		<h3>۲) جدول‌ها (<?php echo count( $snap['tables'] ); ?>)</h3>
		<table class="widefat striped" style="max-width:600px"><tr><th>جدول</th><th>ردیف</th></tr>
		<?php foreach ( $snap['tables'] as $t ) : ?>
			<tr><td><code><?php echo esc_html( $t['name'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $t['rows'] ) ); ?></td></tr>
		<?php endforeach; ?></table>

		<h3>۳) کرون‌ها (<?php echo count( $snap['crons'] ); ?>)</h3>
		<ul><?php foreach ( $snap['crons'] as $c ) : ?><li><code><?php echo esc_html( $c ); ?></code></li><?php endforeach; ?></ul>

		<h3>۴) متاها</h3>
		<table class="widefat striped" style="max-width:600px"><tr><th>جدول</th><th>ردیف rank_math*</th></tr>
		<?php foreach ( $snap['metas'] as $k => $v ) : ?><tr><td><code><?php echo esc_html( $k ); ?></code></td><td><?php echo esc_html( number_format_i18n( $v ) ); ?></td></tr><?php endforeach; ?></table>

		<h3>۵) بازنویسی و افزونه</h3>
		<p>قواعد سایت‌مپ در rewrite_rules: <strong><?php echo count( $snap['misc']['rewrite_sitemap_rules'] ); ?></strong>
			— RM در active_plugins: <strong><?php echo count( $snap['misc']['rm_in_active_plugins'] ); ?></strong>
			— پوشهٔ افزونهٔ RM روی دیسک: <strong><?php echo $snap['misc']['rm_plugin_dir_exists'] ? 'بله (حذف نشده!)' : 'خیر'; ?></strong></p>
		<details><summary>نمایش قواعد بازنویسی سایت‌مپ</summary><ul>
		<?php foreach ( $snap['misc']['rewrite_sitemap_rules'] as $r ) : ?><li><code><?php echo esc_html( $r ); ?></code></li><?php endforeach; ?></ul></details>

		<?php if ( ! $applied ) : ?>
			<h2>اجرای قطعی</h2>
			<form method="post">
				<?php wp_nonce_field( 'sa_rmc_apply', 'sa_rmc_nonce' ); ?>
				<label style="display:block;margin:10px 0"><input type="checkbox" name="sa_rmc_confirm" value="1"> تأیید می‌کنم پشتیبان کامل (UpdraftPlus) گرفته‌ام و می‌دانم این عمل <strong>برگشت‌ناپذیر</strong> است (حذف گزینه‌ها/جدول‌ها/متاهای RM).</label>
				<?php submit_button( 'اجرای پاک‌سازی RM', 'delete', 'sa_rmc_apply', true ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
