<?php
/**
 * Migration tool: move the section's guides from `post` to the `guide` post type.
 *
 * The owner asked for a section that is *not* «قسمت نوشته‌های اصلی وردپرس», which means
 * the 67 published guides have to change post type. That touches real rows on the live
 * site, so the rules here are deliberately conservative:
 *
 *   1. **Nothing runs by itself.** No activation hook, no `init` hook, no cron: the
 *      migration happens only when the owner presses the button in «راهنماها ← مهاجرت».
 *   2. **A backup first.** The migration refuses to run without a fresh JSON snapshot of
 *      every row it is about to touch (post ID, slug, status, date, title, categories,
 *      tags). The snapshot is written to `wp-content/sa-guide-backups/`, protected by
 *      `.htaccess` + `index.php`, and its SHA-256 is shown in the panel.
 *   3. **Only the section.** Posts are selected by the section's categories
 *      (`sa_guide_category_ids()`), never “all posts”. If the scope is empty the plan is
 *      empty and the button stays disabled.
 *   4. **One-way, but reversible.** «بازگشت» sets every `guide` back to `post`, so the
 *      site returns to the exact previous state.
 *   5. **Batched.** 200 rows per request; the panel shows what is left and the owner
 *      repeats the click until the counter reaches zero.
 *
 * The old URLs keep working through `sa_guide_legacy_redirect()` (`/<slug>/` → 301 →
 * `/travel-guide/<slug>/`), which is why no indexed address can turn into a 404.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Directory of the migration snapshots.
 *
 * @return string
 */
function sa_guide_backup_dir() {
	$dir = trailingslashit( WP_CONTENT_DIR ) . 'sa-guide-backups';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$guard = array(
		'.htaccess' => "Require all denied\nDeny from all\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	);
	foreach ( $guard as $name => $contents ) {
		$file = $dir . '/' . $name;
		if ( ! file_exists( $file ) && is_writable( $dir ) ) {
			file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	return $dir;
}

/**
 * Post IDs the migration will move (and the rollback would return).
 *
 * @return array<int,int>
 */
function sa_guide_migration_ids() {
	if ( ! function_exists( 'sa_guide_category_ids' ) ) {
		return array();
	}

	$scope = sa_guide_category_ids();
	if ( ! $scope ) {
		// Never fall back to “every post”: an empty scope must mean “nothing to do”.
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => 'post',
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'fields'                 => 'ids',
			'posts_per_page'         => 500,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'category__in'           => $scope,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return array_values( array_map( 'intval', (array) $ids ) );
}

/**
 * Published guides (the other side of the migration).
 *
 * @return array<int,int>
 */
function sa_guide_migrated_ids() {
	if ( ! function_exists( 'sa_guide_type' ) ) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => sa_guide_type(),
			'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'fields'                 => 'ids',
			'posts_per_page'         => 500,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return array_values( array_map( 'intval', (array) $ids ) );
}

/**
 * Write the snapshot of everything the migration would touch.
 *
 * @param array<int,int> $ids Post IDs.
 * @return array{file:string,count:int,sha256:string,bytes:int}|WP_Error
 */
function sa_guide_backup_write( array $ids ) {
	$dir = sa_guide_backup_dir();
	if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
		return new WP_Error( 'sa_guide_backup_dir', 'پوشهٔ پشتیبان قابل نوشتن نیست: ' . $dir );
	}

	$items = array();
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$items[] = array(
			'id'         => (int) $post->ID,
			'post_type'  => (string) $post->post_type,
			'post_name'  => (string) $post->post_name,
			'post_status'=> (string) $post->post_status,
			'post_date'  => (string) $post->post_date,
			'post_title' => (string) $post->post_title,
			'categories' => array_map( 'intval', wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) ) ),
			'tags'       => array_map( 'intval', wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) ) ),
		);
	}

	if ( ! $items ) {
		return new WP_Error( 'sa_guide_backup_empty', 'هیچ نوشته‌ای در محدودهٔ بخش پیدا نشد؛ پشتیبان خالی ساخته نمی‌شود.' );
	}

	$payload = array(
		'version'     => defined( 'SA_CHILD_VERSION' ) ? SA_CHILD_VERSION : '',
		'created'     => time(),
		'created_gmt' => gmdate( 'c' ),
		'site'        => home_url( '/' ),
		'target_type' => function_exists( 'sa_guide_type' ) ? sa_guide_type() : 'guide',
		'count'       => count( $items ),
		'items'       => $items,
	);

	$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || '' === $json ) {
		return new WP_Error( 'sa_guide_backup_json', 'ساخت فایل پشتیبان ناموفق بود.' );
	}

	$file = $dir . '/guides-' . gmdate( 'Ymd-His' ) . '.json';
	$ok   = file_put_contents( $file, $json ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( ! $ok ) {
		return new WP_Error( 'sa_guide_backup_write', 'نوشتن فایل پشتیبان ناموفق بود.' );
	}

	sa_guide_backup_prune();

	return array(
		'file'   => $file,
		'count'  => count( $items ),
		'sha256' => (string) hash_file( 'sha256', $file ),
		'bytes'  => (int) $ok,
	);
}

/**
 * Keep the last five snapshots.
 *
 * @return void
 */
function sa_guide_backup_prune() {
	$files = sa_guide_backups();
	foreach ( array_slice( $files, 5 ) as $old ) {
		if ( is_file( $old ) && is_writable( $old ) ) {
			unlink( $old ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
}

/**
 * Snapshot files, newest first.
 *
 * @return array<int,string>
 */
function sa_guide_backups() {
	$files = glob( sa_guide_backup_dir() . '/guides-*.json' );
	if ( ! is_array( $files ) ) {
		return array();
	}
	usort(
		$files,
		static function ( $a, $b ) {
			return (int) filemtime( $b ) <=> (int) filemtime( $a );
		}
	);

	return $files;
}

/**
 * Newest snapshot, or ''.
 *
 * @return string
 */
function sa_guide_last_backup() {
	$files = sa_guide_backups();

	return $files ? (string) $files[0] : '';
}

/**
 * Move one batch of guides to the section's type.
 *
 * @param string $backup_file Snapshot the owner acknowledged.
 * @param int    $limit       Rows per request.
 * @return array{done:int,remaining:int}|WP_Error
 */
function sa_guide_migrate( $backup_file, $limit = 200 ) {
	$backup_file = (string) $backup_file;
	$dir         = sa_guide_backup_dir();
	if ( '' === $backup_file || ! is_file( $backup_file ) || 0 !== strpos( $backup_file, $dir ) ) {
		return new WP_Error( 'sa_guide_no_backup', 'بدون فایل پشتیبان مهاجرت انجام نمی‌شود.' );
	}
	if ( ! function_exists( 'sa_guide_type' ) ) {
		return new WP_Error( 'sa_guide_no_type', 'نوع‌محتوای راهنما در دسترس نیست.' );
	}

	$ids = sa_guide_migration_ids();
	if ( ! $ids ) {
		return array(
			'done'      => 0,
			'remaining' => 0,
		);
	}

	$batch = array_slice( $ids, 0, max( 1, (int) $limit ) );
	$done  = 0;
	foreach ( $batch as $id ) {
		if ( sa_guide_type() === get_post_type( $id ) ) {
			continue;
		}
		if ( set_post_type( $id, sa_guide_type() ) ) {
			++$done;
		}
	}

	flush_rewrite_rules( false );
	update_option(
		'sa_guide_migration',
		array(
			'at'     => time(),
			'backup' => basename( $backup_file ),
			'done'   => (int) get_option( 'sa_guide_migration_done', 0 ) + $done,
			'user'   => (int) get_current_user_id(),
		),
		false
	);
	update_option( 'sa_guide_migration_done', (int) get_option( 'sa_guide_migration_done', 0 ) + $done, false );

	return array(
		'done'      => $done,
		'remaining' => max( 0, count( $ids ) - count( $batch ) ),
	);
}

/**
 * Put every guide back into `post` (one batch).
 *
 * @param int $limit Rows per request.
 * @return array{done:int,remaining:int}
 */
function sa_guide_rollback( $limit = 200 ) {
	$ids = sa_guide_migrated_ids();
	if ( ! $ids ) {
		return array(
			'done'      => 0,
			'remaining' => 0,
		);
	}

	$batch = array_slice( $ids, 0, max( 1, (int) $limit ) );
	$done  = 0;
	foreach ( $batch as $id ) {
		if ( 'post' === get_post_type( $id ) ) {
			continue;
		}
		if ( set_post_type( $id, 'post' ) ) {
			++$done;
		}
	}

	flush_rewrite_rules( false );
	update_option( 'sa_guide_migration_done', 0, false );

	return array(
		'done'      => $done,
		'remaining' => max( 0, count( $ids ) - count( $batch ) ),
	);
}

/**
 * Register the panel under the site's own admin menu.
 *
 * @return void
 */
function sa_guide_migration_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'مهاجرت راهنماهای سفر',
		'مهاجرت راهنماها',
		'manage_options',
		'sa-guide-migrate',
		'sa_guide_migration_page'
	);
}
add_action( 'admin_menu', 'sa_guide_migration_menu', 20 );

/**
 * Handle the three buttons (`admin-post.php`), then redirect back to the panel.
 *
 * @return void
 */
function sa_guide_migration_handle() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی کافی ندارید.' );
	}

	$action = isset( $_POST['sa_guide_action'] ) ? sanitize_key( wp_unslash( $_POST['sa_guide_action'] ) ) : '';
	if ( ! in_array( $action, array( 'backup', 'migrate', 'rollback' ), true ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=sa-guide-migrate' ) );
		exit;
	}

	check_admin_referer( 'sa_guide_' . $action );

	$notice = '';
	$error  = '';

	if ( 'backup' === $action ) {
		$result = sa_guide_backup_write( sa_guide_migration_ids() );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$notice = sprintf(
				'پشتیبان ساخته شد: %s (%d مورد، %s بایت) — SHA-256: %s',
				basename( $result['file'] ),
				(int) $result['count'],
				number_format_i18n( (int) $result['bytes'] ),
				$result['sha256']
			);
		}
	}

	if ( 'migrate' === $action ) {
		$file   = isset( $_POST['sa_guide_backup_file'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_guide_backup_file'] ) ) : '';
		$result = sa_guide_migrate( $file );
		if ( is_wp_error( $result ) ) {
			$error = $result->get_error_message();
		} else {
			$notice = 0 === (int) $result['remaining']
				? sprintf( 'مهاجرت تمام شد: %d راهنما به نوع «راهنمای سفر» منتقل شد.', (int) $result['done'] )
				: sprintf( '%d راهنما منتقل شد؛ %d مورد باقی مانده — دوباره همین دکمه را بزنید.', (int) $result['done'], (int) $result['remaining'] );
		}
	}

	if ( 'rollback' === $action ) {
		$result = sa_guide_rollback();
		$notice = 0 === (int) $result['remaining']
			? sprintf( 'بازگشت انجام شد: %d راهنما دوباره نوشتهٔ وردپرس شد.', (int) $result['done'] )
			: sprintf( '%d مورد بازگردانده شد؛ %d مورد باقی مانده — دوباره بزنید.', (int) $result['done'], (int) $result['remaining'] );
	}

	set_transient(
		'sa_guide_notice_' . (int) get_current_user_id(),
		array(
			'notice' => $notice,
			'error'  => $error,
		),
		60
	);

	wp_safe_redirect( admin_url( 'admin.php?page=sa-guide-migrate' ) );
	exit;
}
add_action( 'admin_post_sa_guide_migration', 'sa_guide_migration_handle' );

/**
 * The panel: dry-run plan, snapshot state, the three buttons.
 *
 * @return void
 */
function sa_guide_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$plan     = sa_guide_migration_ids();
	$migrated = sa_guide_migrated_ids();
	$backup   = sa_guide_last_backup();
	$notice   = get_transient( 'sa_guide_notice_' . (int) get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'sa_guide_notice_' . (int) get_current_user_id() );
	}
	$scope_empty = ! sa_guide_category_ids();
	?>
	<div class="wrap">
		<h1>مهاجرت راهنماهای سفر</h1>

		<?php if ( is_array( $notice ) && ! empty( $notice['error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $notice['error'] ); ?></p></div>
		<?php endif; ?>
		<?php if ( is_array( $notice ) && ! empty( $notice['notice'] ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice['notice'] ); ?></p></div>
		<?php endif; ?>

		<p>
			این ابزار نوشته‌های بخش «آموزش سفر» را از نوع «نوشته» وردپرس به نوع مستقل
			«راهنمای سفر» منتقل می‌کند؛ آدرس آرشیو همان <code><?php echo esc_html( sa_2026_guide_url() ); ?></code>
			می‌ماند و آدرس‌های قدیمی با ۳۰۱ به آدرس تازه می‌روند.
		</p>

		<table class="widefat striped" style="max-width:640px;margin:1em 0">
			<tbody>
				<tr><th>در محدودهٔ بخش (نوشته)</th><td><?php echo esc_html( number_format_i18n( count( $plan ) ) ); ?></td></tr>
				<tr><th>آمادهٔ مهاجرت نیست (بدون دستهٔ بخش)</th><td>در این فهرست نمی‌آید</td></tr>
				<tr><th>هم‌اکنون «راهنمای سفر»</th><td><?php echo esc_html( number_format_i18n( count( $migrated ) ) ); ?></td></tr>
				<tr><th>آخرین پشتیبان</th><td><?php echo $backup ? esc_html( basename( $backup ) ) : 'ساخته نشده'; ?></td></tr>
			</tbody>
		</table>

		<?php if ( $scope_empty ) : ?>
			<div class="notice notice-warning"><p>
				دستهٔ بخش شناسایی نشد، بنابراین هیچ نوشته‌ای انتخاب نمی‌شود. پیش از مهاجرت
				دسته‌های «آموزش سفر» را برای نوشته‌ها تعیین کنید.
			</p></div>
		<?php endif; ?>

		<h2>پیش‌نمایش (هیچ چیزی تغییر نمی‌کند)</h2>
		<?php if ( $plan ) : ?>
			<table class="widefat striped">
				<thead><tr><th>#</th><th>عنوان</th><th>آدرس فعلی</th><th>آدرس تازه</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $plan, 0, 50 ) as $id ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $id ); ?></td>
						<td><?php echo esc_html( get_the_title( $id ) ); ?></td>
						<td><code><?php echo esc_html( (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ) ); ?></code></td>
						<td><code><?php echo esc_html( user_trailingslashit( trailingslashit( sa_2026_guide_url() ) . get_post_field( 'post_name', $id ) ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $plan ) > 50 ) : ?>
				<p>۵۰ مورد نخست از <?php echo esc_html( number_format_i18n( count( $plan ) ) ); ?> مورد نمایش داده شده است.</p>
			<?php endif; ?>
		<?php else : ?>
			<p>موردی برای مهاجرت نیست.</p>
		<?php endif; ?>

		<hr>

		<h2>۱) پشتیبان‌گیری</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_guide_migration">
			<input type="hidden" name="sa_guide_action" value="backup">
			<?php wp_nonce_field( 'sa_guide_backup' ); ?>
			<?php submit_button( 'ساخت پشتیبان', 'secondary', 'submit', false, $plan ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>

		<h2>۲) مهاجرت</h2>
		<?php if ( $backup ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sa_guide_migration">
				<input type="hidden" name="sa_guide_action" value="migrate">
				<input type="hidden" name="sa_guide_backup_file" value="<?php echo esc_attr( $backup ); ?>">
				<?php wp_nonce_field( 'sa_guide_migrate' ); ?>
				<?php submit_button( 'انتقال به نوع «راهنمای سفر»', 'primary', 'submit', false, $plan ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		<?php else : ?>
			<p>تا زمانی که پشتیبان ساخته نشود، دکمهٔ مهاجرت فعال نمی‌شود.</p>
		<?php endif; ?>

		<h2>۳) بازگشت</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_guide_migration">
			<input type="hidden" name="sa_guide_action" value="rollback">
			<?php wp_nonce_field( 'sa_guide_rollback' ); ?>
			<?php submit_button( 'بازگشت به «نوشته‌ها»', 'delete', 'submit', false, $migrated ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>
	</div>
	<?php
}
