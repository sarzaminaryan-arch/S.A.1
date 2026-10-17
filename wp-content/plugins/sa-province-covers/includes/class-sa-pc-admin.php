<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SA_PC_Admin {
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_sa_pc_run', array( __CLASS__, 'run' ) );
		add_action( 'admin_post_sa_pc_settings', array( __CLASS__, 'settings' ) );
	}
	public static function menu() { add_media_page( 'تصاویر ۳۱ استان', 'تصاویر ۳۱ استان', 'manage_options', 'sa-province-covers', array( __CLASS__, 'page' ) ); }
	public static function assets( $hook ) {
		if ( 'media_page_sa-province-covers' !== $hook || ! current_user_can( 'manage_options' ) ) { return; }
		wp_enqueue_style( 'sa-pc-admin', SA_PC_URL . 'assets/admin.css', array(), SA_PC_VERSION );
		wp_enqueue_script( 'sa-pc-admin', SA_PC_URL . 'assets/admin.js', array(), SA_PC_VERSION, true );
		wp_localize_script( 'sa-pc-admin', 'SAProvinceCovers', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'sa_pc_admin' ) ) );
	}

	public static function run() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { wp_send_json_error( array( 'message' => 'دسترسی ندارید.' ), 403 ); }
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! check_ajax_referer( 'sa_pc_admin', 'nonce', false ) ) { wp_send_json_error( array( 'message' => 'درخواست معتبر نیست؛ صفحه را تازه کنید.' ), 403 ); }
		$raw_slug = isset( $_POST['slug'] ) && is_string( $_POST['slug'] ) ? wp_unslash( $_POST['slug'] ) : '';
		$slug = sanitize_key( $raw_slug );
		$catalog = sa_pc_catalog();
		$id = isset( $_POST['post_id'] ) && is_string( $_POST['post_id'] ) ? filter_var( $_POST['post_id'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;
		$op = isset( $_POST['op'] ) && is_string( $_POST['op'] ) ? $_POST['op'] : '';
		if ( ! $id || $slug !== $raw_slug || ! isset( $catalog[ $slug ] ) || ! in_array( $op, array( 'apply', 'restore', 'unlock' ), true ) ) {
			wp_send_json_error( array( 'message' => 'استان، مقصد یا عملیات معتبر نیست.' ), 400 );
		}
		if ( 'restore' === $op ) {
			$journal = get_option( 'sa_pc_journal_' . $id );
			if ( is_array( $journal ) && ( $journal['slug'] ?? '' ) !== $slug ) { wp_send_json_error( array( 'message' => 'پشتیبان مقصد متعلق به استان دیگری است.' ), 409 ); }
		}
		$result = 'apply' === $op ? SA_PC_Store::apply( $slug, $id ) : ( 'restore' === $op ? SA_PC_Store::restore( $id ) : SA_PC_Store::unlock( $id ) );
		if ( is_wp_error( $result ) ) { wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 ); }
		wp_send_json_success( true === $result ? array( 'message' => 'قفل منقضی باز شد.' ) : $result );
	}

	public static function settings() {
		if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'دسترسی ندارید.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'sa_pc_settings' );
		update_option( 'sa_pc_show_in_article', ! empty( $_POST['show_in_article'] ), false );
		wp_safe_redirect( admin_url( 'upload.php?page=sa-province-covers' ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'upload_files' ) ) { wp_die( 'دسترسی ندارید.' ); }
		$posts = get_posts( array( 'post_type' => 'province', 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
		$posts = array_filter( $posts, static function ( $post ) { return current_user_can( 'edit_post', $post->ID ); } );
		?>
		<div class="wrap sa-pc-admin">
		<h1>تصاویر شاخص ۳۱ استان</h1>
		<p>ابتدا استان مقصد و تصویر جدید را بررسی کنید، سپس موارد لازم را انتخاب و جایگزین کنید. هر درخواست فقط یک استان را پردازش می‌کند؛ عملیات گروهی به‌ترتیب انجام می‌شود.</p>
		<p><strong>تصویر شاخص قبلی ابتدا از مقاله جدا می‌شود، نه از رسانه.</strong> فایل قدیمی، متن مقاله و رأی‌ها حذف نمی‌شوند. پشتیبان شناسهٔ قبلی برای بازگردانی نگه داشته می‌شود؛ نصب یا فعال‌سازی افزونه به‌تنهایی هیچ تصویری را تغییر نمی‌دهد.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_pc_settings">
			<?php wp_nonce_field( 'sa_pc_settings' ); ?>
			<label><input type="checkbox" name="show_in_article" value="1" <?php checked( get_option( 'sa_pc_show_in_article', true ) ); ?>> نمایش تصویر جایگزین‌شده فقط در متن مقالهٔ همان استان</label>
			<?php submit_button( 'ذخیرهٔ تنظیم نمایش', 'secondary', 'submit', false ); ?>
		</form>
		<p>تصاویر فقط برای استان‌های جایگزین‌شده و منتشرشده نمایش داده می‌شوند؛ کارت‌ها، صفحهٔ اول، شهرستان‌ها و آرشیوها تصویر نمی‌گیرند. تصویر اشتراک‌گذاری مستقل از کلید نمایش مقاله فعال است. کش سایت و پیش‌نمایش پیام‌رسان‌ها ممکن است پس از تغییر نیاز به تازه‌سازی داشته باشند.</p>
		<p><button type="button" class="button" data-sa-pc-all>انتخاب همهٔ مقصدهای شناسایی‌شده</button> <button type="button" class="button button-primary" data-sa-pc-batch>جایگزینی موارد انتخاب‌شده</button></p>
		<p data-sa-pc-progress role="status" aria-live="polite"></p>
		<table class="widefat striped"><thead><tr><th>انتخاب / استان</th><th>تصویر جدید / آلت فارسی</th><th>مقالهٔ مقصد / تصویر فعلی</th><th>عملیات / نتیجه</th></tr></thead><tbody>
		<?php foreach ( sa_pc_catalog() as $slug => $entry ) :
			$target = sa_pc_find_target( $entry, $posts );
			$asset = sa_pc_asset( $slug );
			?>
			<tr data-sa-pc-row data-slug="<?php echo esc_attr( $slug ); ?>">
				<td><label><input type="checkbox" data-sa-pc-select <?php disabled( is_wp_error( $asset ) ); ?>> <?php echo esc_html( $entry['name'] ); ?></label></td>
				<td><?php if ( ! is_wp_error( $asset ) ) : ?><img class="sa-pc-preview" src="<?php echo esc_url( SA_PC_URL . 'assets/images/' . $entry['file'] ); ?>" alt="<?php echo esc_attr( $entry['alt'] ); ?>" loading="lazy"><?php else : ?><p><?php echo esc_html( $asset->get_error_message() ); ?></p><?php endif; ?><p><?php echo esc_html( $entry['alt'] ); ?></p></td>
				<td><select data-sa-pc-target aria-label="<?php echo esc_attr( 'مقالهٔ مقصد ' . $entry['name'] ); ?>"><option value="0">انتخاب دستی — تطبیق مبهم یا یافت نشد</option>
				<?php foreach ( $posts as $post ) : ?><option value="<?php echo (int) $post->ID; ?>" <?php selected( $target, $post->ID ); ?>><?php echo esc_html( $post->post_title . ' — #' . $post->ID . ' — ' . $post->post_status ); ?></option><?php endforeach; ?>
				</select>
				<?php if ( $target ) { echo wp_get_attachment_image( get_post_thumbnail_id( $target ), 'thumbnail', false, array( 'class' => 'sa-pc-current' ) ); } ?>
				</td>
				<td><button type="button" class="button" data-sa-pc-op="apply" <?php disabled( is_wp_error( $asset ) ); ?>>جایگزینی</button> <button type="button" class="button" data-sa-pc-op="restore">بازگردانی</button> <button type="button" class="button-link" data-sa-pc-op="unlock">رفع قفل منقضی</button><p data-sa-pc-status role="status" aria-live="polite"></p></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php
	}
}
