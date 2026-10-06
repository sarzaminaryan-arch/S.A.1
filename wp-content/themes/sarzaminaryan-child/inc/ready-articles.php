<?php
/**
 * «مقالات آمادهٔ نمای برتر» — in-theme ready-article tool (v2.11.15).
 *
 * Admin path: سرزمین آریان → مقالات آمادهٔ نمای برتر
 *
 * Rules enforced here:
 * - new articles are ALWAYS inserted as draft (post_status = draft);
 * - an already published article is never touched unless the operator ticks the
 *   explicit «بازنویسی نسخهٔ منتشرشده» box on that row;
 * - the white featured-image card (pure white background, one colored corner
 *   icon, centered Persian/English text, deep shadow) is rendered in the browser
 *   on a 1200×600 canvas and stored through the REST route below, so no binary
 *   asset has to live in the repository.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ready-article definitions (data/ready-articles.php).
 *
 * @return array<string,array<string,mixed>>
 */
function sa_ready_articles_data() {
	static $data = null;
	if ( null === $data ) {
		$file = SA_CHILD_DIR . 'data/ready-articles.php';
		$data = file_exists( $file ) ? include $file : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
	}
	return $data;
}

/**
 * Menu entry under the unified «سرزمین آریان» menu.
 */
function sa_ready_articles_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'مقالات آمادهٔ نمای برتر',
		'مقالات آمادهٔ نمای برتر',
		'edit_posts',
		'sa-ready-articles',
		'sa_ready_articles_page'
	);
}
add_action( 'admin_menu', 'sa_ready_articles_menu', 20 );

/**
 * Admin assets for the tool screen only.
 *
 * @param string $hook Current admin page hook.
 */
function sa_ready_articles_assets( $hook ) {
	if ( 'sarzaminaryan_page_sa-ready-articles' !== $hook ) {
		return;
	}

	// The Persian webfont lives in the parent theme and is front-end only by default.
	wp_enqueue_style(
		'sa-ready-fonts',
		get_template_directory_uri() . '/assets/css/fonts.css',
		array(),
		SA_CHILD_VERSION
	);
	wp_enqueue_style(
		'sa-ready-articles',
		SA_CHILD_URI . 'assets/css/ready-articles.css',
		array( 'sa-ready-fonts' ),
		SA_CHILD_VERSION
	);
	wp_enqueue_script(
		'sa-ready-articles',
		SA_CHILD_URI . 'assets/js/ready-articles.js',
		array(),
		SA_CHILD_VERSION,
		true
	);
	wp_localize_script(
		'sa-ready-articles',
		'saReadyArticles',
		array(
			'restUrl' => esc_url_raw( rest_url( 'sa/v1/ready-articles/cover' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'size'    => array( 'w' => 1200, 'h' => 600 ),
			'palette' => array(
				'navy'  => '#011f3d',
				'green' => '#0f5132',
				'gold'  => '#9b6a16',
				'mint'  => '#e8f6ee',
			),
			'i18n'    => array(
				'working'  => 'در حال ساخت کارت ۱۲۰۰×۶۰۰…',
				'saving'   => 'ذخیرهٔ تصویر شاخص…',
				'done'     => 'کارت سفید ساخته و به‌عنوان تصویر شاخص ثبت شد.',
				'failed'   => 'ساخت یا ذخیرهٔ کارت انجام نشد.',
				'nocanvas' => 'مرورگر شما از ساخت تصویر پشتیبانی نمی‌کند؛ از Chrome، Edge یا Firefox جدید استفاده کنید.',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'sa_ready_articles_assets' );

/**
 * Find an existing post of a ready article.
 *
 * @param array<string,mixed> $article Article definition.
 * @return int Post ID or 0.
 */
function sa_ready_articles_find_post( $article ) {
	$found = get_posts(
		array(
			'post_type'        => $article['type'],
			'name'             => $article['slug'],
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'suppress_filters' => false,
		)
	);
	if ( $found ) {
		return absint( $found[0] );
	}
	$found = get_posts(
		array(
			'post_type'      => $article['type'],
			'post_status'    => 'any',
			'title'          => $article['title'],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return $found ? absint( $found[0] ) : 0;
}

/**
 * Persian label + CSS class for a post status.
 *
 * @param string $status Post status.
 * @return array{0:string,1:string} label, class
 */
function sa_ready_articles_status_label( $status ) {
	switch ( $status ) {
		case 'publish':
			return array( 'منتشرشده', 'published' );
		case 'pending':
			return array( 'در انتظار بررسی', 'pending' );
		case 'private':
			return array( 'خصوصی', 'pending' );
		case 'future':
			return array( 'زمان‌بندی‌شده', 'pending' );
		default:
			return array( 'پیش‌نویس', 'draft' );
	}
}

/**
 * Ensure a province/city entity exists (draft when created).
 *
 * @param string $type          province|city.
 * @param string $slug          Slug.
 * @param string $title         Persian title.
 * @param string $province_slug Province term slug (province_tax).
 * @param int    $parent_id     Province post ID for cities.
 * @return int|WP_Error
 */
function sa_ready_articles_ensure_entity( $type, $slug, $title, $province_slug = '', $parent_id = 0 ) {
	if ( ! post_type_exists( $type ) ) {
		return new WP_Error( 'missing_cpt', 'نوع محتوای «' . $title . '» در این سایت پیدا نشد.' );
	}

	$existing = get_posts(
		array(
			'post_type'      => $type,
			'name'           => $slug,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$post_id = $existing ? absint( $existing[0] ) : 0;

	if ( ! $post_id ) {
		$by_title = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'title'          => $title,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		$post_id = $by_title ? absint( $by_title[0] ) : 0;
	}

	if ( ! $post_id ) {
		$args = array(
			'post_type'   => $type,
			'post_status' => 'draft', // never publish from here.
			'post_name'   => $slug,
			'post_title'  => $title,
		);
		if ( 'city' === $type && $parent_id ) {
			$args['meta_input'] = array( 'sa_province_id' => absint( $parent_id ) );
		}
		$created = wp_insert_post( $args, true );
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$post_id = absint( $created );
	}

	if ( $parent_id ) {
		update_post_meta( $post_id, 'sa_province_id', absint( $parent_id ) );
	}
	if ( taxonomy_exists( 'province_tax' ) && $province_slug ) {
		wp_set_object_terms( $post_id, $province_slug, 'province_tax', false );
	}
	if ( function_exists( 'sa_sync_relations' ) ) {
		sa_sync_relations( $post_id, $type );
	}

	return $post_id;
}

/**
 * Find an existing attachment whose stored file name contains the given name.
 *
 * @param string $file File name fragment.
 * @return int Attachment ID or 0.
 */
function sa_ready_articles_find_attachment( $file ) {
	if ( '' === trim( (string) $file ) ) {
		return 0;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $file );
	$id   = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
			$like
		)
	);
	return $id ? absint( $id ) : 0;
}

/**
 * Import a whitelisted source image into the media library exactly once.
 *
 * Articles may carry a `url` next to `file`; when the owner has not uploaded a
 * local copy yet, the picture is copied once into the site's own media library
 * so published pages never hotlink an external host.  A `_sa_source_url` meta
 * marker prevents duplicate imports on repeated runs.
 *
 * @param string $url     Image URL (must be Wikimedia-hosted).
 * @param int    $post_id Parent post ID (0 for unattached).
 * @return int Attachment ID or 0 on failure.
 */
function sa_ready_articles_sideload( $url, $post_id = 0 ) {
	$url  = esc_url_raw( (string) $url );
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	// Wikimedia now serves Commons thumbnails from this separate CDN host.
	$allowed_hosts = array( 'upload.wikimedia.org', 'thumb.wikimedia.org', 'commons.wikimedia.org' );
	if ( '' === $url || ! in_array( $host, $allowed_hosts, true ) ) {
		return 0;
	}

	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_sa_source_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
			'posts_per_page' => 1,
		)
	);
	if ( ! empty( $existing ) ) {
		return absint( $existing[0] );
	}

	if ( ! function_exists( 'media_sideload_image' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$attachment = media_sideload_image( $url, absint( $post_id ), null, 'id' );
	if ( is_wp_error( $attachment ) || ! $attachment ) {
		return 0;
	}
	$attachment = absint( $attachment );
	update_post_meta( $attachment, '_sa_source_url', $url );
	return $attachment;
}

/**
 * Build a gallery figure for the article body when the attachment exists.
 *
 * @param array<string,string> $item file/caption/alt.
 * @return string
 */
function sa_ready_articles_figure( $item, $post_id = 0 ) {
	if ( empty( $item['file'] ) && empty( $item['url'] ) ) {
		return '';
	}
	$id = 0;
	if ( ! empty( $item['file'] ) ) {
		$id = sa_ready_articles_find_attachment( $item['file'] );
	}
	if ( ! $id && ! empty( $item['url'] ) ) {
		$id = sa_ready_articles_sideload( $item['url'], $post_id );
	}
	if ( ! $id ) {
		return '';
	}
	$image = wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) );
	if ( ! $image ) {
		return '';
	}
	if ( ! empty( $item['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $item['alt'] );
	}
	if ( ! empty( $item['caption'] ) ) {
		wp_update_post(
			array(
				'ID'           => $id,
				'post_excerpt' => $item['caption'],
			)
		);
	}
	$caption = empty( $item['caption'] ) ? '' : '<figcaption>' . esc_html( $item['caption'] ) . '</figcaption>';
	return '<figure class="wp-block-image size-large">' . $image . $caption . '</figure>';
}

/**
 * Replace {{GALLERY:key}} placeholders in the stored body.
 *
 * @param array<string,mixed> $article Article definition.
 * @return string
 */
function sa_ready_articles_content( $article, $post_id = 0 ) {
	$content = (string) $article['content'];
	if ( ! empty( $article['gallery'] ) && is_array( $article['gallery'] ) ) {
		foreach ( $article['gallery'] as $key => $item ) {
			$content = str_replace( '{{GALLERY:' . $key . '}}', sa_ready_articles_figure( $item, $post_id ), $content );
		}
	}
	return trim( preg_replace( '/\{\{GALLERY:[a-zA-Z0-9_-]+\}\}/', '', $content ) );
}

/**
 * Insert or update one ready article.
 *
 * @param string $key                 Article key.
 * @param bool   $overwrite_published Allow updating an already published article.
 * @return int|WP_Error Post ID or error.
 */
function sa_ready_article_import( $key, $overwrite_published = false ) {
	$data = sa_ready_articles_data();
	if ( empty( $data[ $key ] ) ) {
		return new WP_Error( 'unknown_article', 'این مقالهٔ آماده در قالب پیدا نشد.' );
	}
	$article = $data[ $key ];

	if ( ! post_type_exists( $article['type'] ) ) {
		return new WP_Error( 'missing_cpt', 'نوع محتوای موردنیاز فعال نیست؛ ابتدا قالب فرزند «سرزمین آریان» را فعال کنید.' );
	}

	$province = get_posts(
		array(
			'post_type'      => 'province',
			'name'           => $article['province'],
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$province_id = $province ? absint( $province[0] ) : 0;
	if ( ! $province_id ) {
		$province_id = sa_ready_articles_ensure_entity(
			'province',
			$article['province'],
			$article['province_name'],
			$article['province']
		);
		if ( is_wp_error( $province_id ) ) {
			return $province_id;
		}
	}

	$city_id = 0;
	if ( ! empty( $article['city'] ) ) {
		$city_id = sa_ready_articles_ensure_entity(
			'city',
			$article['city'],
			$article['city_name'],
			$article['province'],
			$province_id
		);
		if ( is_wp_error( $city_id ) ) {
			return $city_id;
		}
	}

	$post_id  = sa_ready_articles_find_post( $article );
	$status   = $post_id ? get_post_status( $post_id ) : '';
	$is_draft = ( '' === $status || in_array( $status, array( 'draft', 'auto-draft' ), true ) );

	if ( $post_id && ! $is_draft && ! $overwrite_published ) {
		return new WP_Error(
			'published_locked',
			'این مقاله وضعیت «' . $status . '» دارد. برای به‌روزرسانی، گزینهٔ «بازنویسی نسخهٔ منتشرشده» را تیک بزنید.'
		);
	}

	// The publish gate blocks non-attraction entities without a featured image;
	// ask for the white card first so an update cannot demote a published page.
	if ( $post_id && ! $is_draft && 'attraction' !== $article['type'] && ! has_post_thumbnail( $post_id ) ) {
		return new WP_Error(
			'missing_thumbnail',
			'این مقاله منتشر شده اما تصویر شاخص ندارد. ابتدا «ساخت کارت تصویر شاخص» را بزنید و سپس به‌روزرسانی کنید.'
		);
	}

	$postarr = array(
		'post_type'    => $article['type'],
		'post_status'  => $post_id ? $status : 'draft', // new articles are always drafts.
		'post_name'    => $article['slug'],
		'post_title'   => $article['title'],
		'post_excerpt' => $article['excerpt'],
		'post_content' => sa_ready_articles_content( $article, $post_id ),
	);

	if ( $post_id ) {
		// Level-7 metadata first: an already published article must never be
		// demoted by the publish gate while its fields are still empty.
		sa_ready_articles_write_meta( $post_id, $article, $province_id, $city_id );
		$postarr['ID'] = $post_id;
		$saved         = wp_update_post( $postarr, true );
	} else {
		$saved = wp_insert_post( $postarr, true );
	}
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	$post_id = absint( $saved );

	if ( ! isset( $postarr['ID'] ) ) {
		sa_ready_articles_write_meta( $post_id, $article, $province_id, $city_id );
	}

	// Gallery items that point at a remote source need the post ID first, so the
	// imported attachments belong to the article; re-render the body afterwards.
	if ( ! empty( $article['gallery'] ) && is_array( $article['gallery'] ) ) {
		foreach ( $article['gallery'] as $item ) {
			if ( ! empty( $item['url'] ) ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => sa_ready_articles_content( $article, $post_id ),
					)
				);
				break;
			}
		}
	}

	if ( function_exists( 'sa_sync_relations' ) ) {
		sa_sync_relations( $post_id, $article['type'] );
	}
	if ( function_exists( 'sa_flush_relation_cache' ) ) {
		sa_flush_relation_cache( $post_id, $article['type'] );
	}

	return $post_id;
}

/**
 * Write structured meta, SEO values, FAQ rows and taxonomy terms.
 *
 * @param int                 $post_id     Post ID.
 * @param array<string,mixed> $article     Article definition.
 * @param int                 $province_id Province post ID.
 * @param int                 $city_id     City post ID (0 when not applicable).
 */
function sa_ready_articles_write_meta( $post_id, $article, $province_id, $city_id ) {
	$meta = isset( $article['meta'] ) && is_array( $article['meta'] ) ? $article['meta'] : array();

	$meta['sa_province_id'] = absint( $province_id );
	if ( $city_id ) {
		$meta['sa_city_id'] = absint( $city_id );
	}
	foreach ( $meta as $meta_key => $meta_value ) {
		update_post_meta( $post_id, $meta_key, $meta_value );
	}

	if ( ! empty( $article['faq'] ) && is_array( $article['faq'] ) ) {
		update_post_meta(
			$post_id,
			'sa_faq',
			wp_json_encode( array_values( $article['faq'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	if ( ! empty( $article['terms'] ) && is_array( $article['terms'] ) ) {
		foreach ( $article['terms'] as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$ids = array();
			foreach ( $terms as $term_slug => $term_name ) {
				$term = get_term_by( 'slug', $term_slug, $taxonomy );
				if ( ! $term ) {
					$created = wp_insert_term( $term_name, $taxonomy, array( 'slug' => $term_slug ) );
					if ( ! is_wp_error( $created ) ) {
						$ids[] = absint( $created['term_id'] );
					}
				} else {
					$ids[] = absint( $term->term_id );
				}
			}
			if ( $ids ) {
				wp_set_object_terms( $post_id, $ids, $taxonomy, false );
			}
		}
	}
	if ( taxonomy_exists( 'province_tax' ) ) {
		wp_set_object_terms( $post_id, $article['province'], 'province_tax', false );
	}
}

/**
 * admin-post handler: import one ready article.
 */
function sa_ready_articles_handle_import() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای این کار دسترسی کافی ندارید.' ) );
	}
	$key = isset( $_POST['sa_article'] ) ? sanitize_key( wp_unslash( $_POST['sa_article'] ) ) : '';
	check_admin_referer( 'sa_ready_import_' . $key, 'sa_ready_nonce' );

	$overwrite = ! empty( $_POST['sa_overwrite_published'] );
	$result    = sa_ready_article_import( $key, $overwrite );

	$args = array( 'page' => 'sa-ready-articles' );
	if ( is_wp_error( $result ) ) {
		$args['sa-ready'] = 'error';
		$args['sa-msg']   = rawurlencode( $result->get_error_message() );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	$was_draft = 'draft' === get_post_status( $result );
	$args['sa-ready']  = $was_draft ? 'draft' : 'updated';
	$args['sa-post']   = absint( $result );
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sa_ready_article', 'sa_ready_articles_handle_import' );

/**
 * Render the tool screen.
 */
function sa_ready_articles_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای دیدن این صفحه دسترسی کافی ندارید.' ) );
	}
	$data = sa_ready_articles_data();
	?>
	<div class="wrap sa-ready">
		<h1>مقالات آمادهٔ نمای برتر</h1>
		<p class="sa-ready__lead">
			مقاله‌های آمادهٔ قالب، <strong>فقط به‌صورت پیش‌نویس</strong> وارد سایت می‌شوند؛ انتشار نهایی همیشه با مدیر است.
			برای هر مقاله می‌توانید کارت سفید تصویر شاخص (۱۲۰۰×۶۰۰) را بسازید: زمینهٔ کاملاً سفید، یک آیکون رنگی گوشه‌ای، نوشته‌های مرکزچین با رنگ‌های آبی ناوی، سبز تیره و طلایی تیره و سایهٔ عمیق دور کاور.
		</p>

		<?php if ( isset( $_GET['sa-ready'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php
			$ok    = in_array( wp_unslash( $_GET['sa-ready'] ), array( 'draft', 'updated' ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$class = $ok ? 'notice-success' : 'notice-error';
			if ( 'draft' === wp_unslash( $_GET['sa-ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$text = 'مقاله به‌صورت پیش‌نویس ساخته شد. بازبینی کنید و انتشار را خودتان انجام دهید.';
			} elseif ( 'updated' === wp_unslash( $_GET['sa-ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$text = 'مقاله به‌روزرسانی شد و وضعیت انتشار آن دست‌نخورده ماند.';
			} else {
				$text = isset( $_GET['sa-msg'] ) ? rawurldecode( wp_unslash( $_GET['sa-msg'] ) ) : 'خطای نامشخص.'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			$edit = isset( $_GET['sa-post'] ) ? absint( $_GET['sa-post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p>
				<?php echo esc_html( $text ); ?>
				<?php if ( $ok && $edit ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $edit ) ); ?>">ویرایش همین حالا</a>
				<?php endif; ?>
			</p></div>
		<?php endif; ?>

		<p class="sa-ready__legend">
			<span class="sa-ready__dot sa-ready__dot--draft"></span> پیش‌نویس
			<span class="sa-ready__dot sa-ready__dot--published"></span> منتشرشده
			<span class="sa-ready__dot sa-ready__dot--none"></span> وارد نشده
		</p>

		<table class="widefat striped sa-ready__table">
			<thead>
				<tr>
					<th>مقاله</th>
					<th>نوع</th>
					<th>موقعیت</th>
					<th>وضعیت</th>
					<th>تصویر شاخص (کارت سفید)</th>
					<th>اقدام‌ها</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $data as $key => $article ) : ?>
				<?php
				$post_id = sa_ready_articles_find_post( $article );
				$status  = $post_id ? get_post_status( $post_id ) : '';
				list( $status_label, $status_class ) = $post_id
					? sa_ready_articles_status_label( $status )
					: array( 'وارد نشده', 'none' );
				$has_cover = $post_id ? has_post_thumbnail( $post_id ) : false;
				$cover_id  = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
				$generated = $cover_id ? (bool) get_post_meta( $cover_id, '_sa_ready_cover_article', true ) : false;
				$city_name     = sa_normalize_place_name( $article['city_name'], 'city', '' );
				$province_name = sa_normalize_place_name( $article['province_name'], 'province', '' );
				$place         = implode( ' — ', array_filter( array( $city_name, $province_name ) ) );
				$city_line     = 'شهرستان ' . $city_name;
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $article['title'] ); ?></strong>
						<code dir="ltr"><?php echo esc_html( $article['slug'] ); ?></code>
					</td>
					<td><?php echo esc_html( 'attraction' === $article['type'] ? 'نمای برتر' : 'شهرستان' ); ?></td>
					<td><?php echo esc_html( $place ); ?></td>
					<td>
						<span class="sa-ready__badge sa-ready__badge--<?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_label ); ?>
						</span>
						<?php if ( $post_id ) : ?>
							<a class="sa-ready__mini" href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>">ویرایش</a>
							<?php if ( 'publish' === $status ) : ?>
								<a class="sa-ready__mini" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>">مشاهده</a>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $has_cover ) : ?>
							<span class="sa-ready__badge sa-ready__badge--cover">
								<?php echo esc_html( $generated ? 'کارت سفید قالب' : 'تصویر ثبت‌شده' ); ?>
							</span>
						<?php else : ?>
							<span class="sa-ready__badge sa-ready__badge--none">ندارد</span>
						<?php endif; ?>
					</td>
					<td class="sa-ready__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="sa_ready_article">
							<input type="hidden" name="sa_article" value="<?php echo esc_attr( $key ); ?>">
							<?php wp_nonce_field( 'sa_ready_import_' . $key, 'sa_ready_nonce' ); ?>
							<button type="submit" class="button <?php echo $post_id ? '' : 'button-primary'; ?>">
								<?php echo esc_html( $post_id ? 'به‌روزرسانی از منبع قالب' : 'درج پیش‌نویس' ); ?>
							</button>
							<label class="sa-ready__check">
								<input type="checkbox" name="sa_overwrite_published" value="1">
								بازنویسی نسخهٔ منتشرشده
							</label>
						</form>

						<button type="button"
							class="button sa-ready__cover"
							data-sa-cover
							data-post="<?php echo absint( $post_id ); ?>"
							data-article="<?php echo esc_attr( $key ); ?>"
							data-title="<?php echo esc_attr( $article['title'] ); ?>"
							data-english="<?php echo esc_attr( $article['english'] ); ?>"
							data-kind="<?php echo esc_attr( $article['kind'] ); ?>"
							data-city="<?php echo esc_attr( $city_line ); ?>"
							data-province="<?php echo esc_attr( 'استان ' . $province_name ); ?>"
							<?php echo $post_id ? '' : 'disabled'; ?>>
							ساخت کارت تصویر شاخص
						</button>
						<span class="sa-ready__hint" data-sa-status></span>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<p class="sa-ready__foot">
			نکته‌ها: درج جدید همیشه پیش‌نویس است؛ اگر مقاله‌ای از قبل منتشر شده باشد تنها با تیک «بازنویسی نسخهٔ منتشرشده» به‌روزرسانی می‌شود.
			کارت تصویر شاخص به‌صورت WebP (و در صورت پشتیبانی‌نشدن مرورگر، PNG) ذخیره و بلافاصله تصویر شاخصِ مقاله می‌شود.
		</p>
	</div>
	<?php
}

/**
 * REST route: store the browser-rendered white card as the featured image.
 */
function sa_ready_articles_register_rest() {
	register_rest_route(
		'sa/v1',
		'/ready-articles/cover',
		array(
			'methods'             => 'POST',
			'callback'            => 'sa_ready_articles_cover_endpoint',
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' );
			},
			'args'                => array(
				'article' => array( 'required' => true ),
				'image'   => array( 'required' => true ),
			),
		)
	);
}
add_action( 'rest_api_init', 'sa_ready_articles_register_rest' );

/**
 * Save a data-URL card image as attachment + featured image.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function sa_ready_articles_cover_endpoint( WP_REST_Request $request ) {
	$key  = sanitize_key( (string) $request->get_param( 'article' ) );
	$data = sa_ready_articles_data();
	if ( empty( $data[ $key ] ) ) {
		return new WP_Error( 'sa_unknown_article', 'مقالهٔ آماده پیدا نشد.', array( 'status' => 404 ) );
	}
	$article = $data[ $key ];

	$post_id = sa_ready_articles_find_post( $article );
	if ( ! $post_id ) {
		return new WP_Error( 'sa_no_post', 'ابتدا مقاله را به‌صورت پیش‌نویس درج کنید.', array( 'status' => 400 ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'sa_forbidden_post', 'برای ویرایش این مقاله دسترسی ندارید.', array( 'status' => 403 ) );
	}

	$raw = (string) $request->get_param( 'image' );
	if ( strlen( $raw ) > 4 * MB_IN_BYTES ) {
		return new WP_Error( 'sa_too_large', 'حجم تصویر ارسالی بیش از حد مجاز است.', array( 'status' => 413 ) );
	}
	if ( ! preg_match( '#^data:image/(png|webp);base64,#', $raw, $matches ) ) {
		return new WP_Error( 'sa_bad_type', 'فقط تصویر PNG یا WebP پذیرفته می‌شود.', array( 'status' => 415 ) );
	}
	$extension = 'jpg';
	$mime      = '';
	if ( 'png' === $matches[1] ) {
		$extension = 'png';
		$mime      = 'image/png';
	} elseif ( 'webp' === $matches[1] ) {
		$extension = 'webp';
		$mime      = 'image/webp';
	}
	$binary = base64_decode( substr( $raw, strlen( $matches[0] ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( false === $binary || strlen( $binary ) < 1024 ) {
		return new WP_Error( 'sa_bad_image', 'داده‌های تصویر معتبر نیست.', array( 'status' => 400 ) );
	}
	if ( 'image/png' === $mime && "\x89PNG\r\n\x1a\n" !== substr( $binary, 0, 8 ) ) {
		return new WP_Error( 'sa_bad_png', 'فایل PNG معتبر نیست.', array( 'status' => 400 ) );
	}
	if ( 'image/webp' === $mime && 'WEBP' !== substr( $binary, 8, 4 ) ) {
		return new WP_Error( 'sa_bad_webp', 'فایل WebP معتبر نیست.', array( 'status' => 400 ) );
	}

	$filename = $article['slug'] . '-white-card-1200x600.' . $extension;
	$upload   = wp_upload_bits( $filename, null, $binary );
	if ( ! empty( $upload['error'] ) ) {
		return new WP_Error( 'sa_upload_failed', (string) $upload['error'], array( 'status' => 500 ) );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => 'کارت سفید ' . $article['title'] . ' | شهرستان دورود | استان لرستان',
			'post_excerpt'   => $article['title'] . ' | شهرستان دورود | استان لرستان / ' . $article['english'] . ' | Dorud city | Lorestan',
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file'],
		$post_id
	);
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}
	$attachment_id = absint( $attachment_id );

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta(
		$attachment_id,
		'_wp_attachment_image_alt',
		'کارت سفید ' . $article['title'] . ' با آیکون رنگی گوشه‌ای و نوشته‌های مرکزچین — ' . $article['english'] . ' | Dorud city | Lorestan'
	);
	update_post_meta( $attachment_id, '_sa_ready_cover_article', $key );

	// Drop a previously generated card for this same article (it is regenerable).
	$old = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'meta_key'       => '_sa_ready_cover_article', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	foreach ( $old as $old_id ) {
		if ( absint( $old_id ) !== $attachment_id ) {
			wp_delete_attachment( absint( $old_id ), true );
		}
	}

	set_post_thumbnail( $post_id, $attachment_id );

	return new WP_REST_Response(
		array(
			'ok'         => true,
			'attachment' => $attachment_id,
			'url'        => wp_get_attachment_url( $attachment_id ),
			'post'       => $post_id,
			'edit'       => (string) get_edit_post_link( $post_id ),
		),
		200
	);
}
