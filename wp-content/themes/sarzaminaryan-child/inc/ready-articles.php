<?php
/**
 * «مقالات آماده» — in-theme ready-article tool (v2.11.15; guides since v2.13.0).
 *
 * Admin path: سرزمین آریان → مقالات آماده
 *
 * Rules enforced here:
 * - new articles are inserted as draft unless the owner ticks «تأیید مالک —
 *   منتشر شود» (v2.12.7 publishing policy: the owner's approval is the only
 *   publish condition; Level 7 findings are shown as reminders, never as locks);
 * - an already published article is never touched unless the operator ticks the
 *   explicit «بازنویسی نسخهٔ منتشرشده» box on that row (or in the bulk form);
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
 * Ready-article definitions.
 *
 * Two data files are merged (v2.13.0):
 * - data/ready-articles.php — attraction entities (province/city aware);
 * - data/ready-guides.php   — camping/survival guides imported as ordinary
 *   posts in the «آموزش‌های ضروری کمپینگ و بقا» category (generated from
 *   docs/camping-education/articles by tools/content-lint/build_theme_data.py).
 *
 * Every record is normalised so the importer and the admin table can rely on
 * the same keys regardless of the source file.
 *
 * @return array<string,array<string,mixed>>
 */
function sa_ready_articles_data() {
	static $data = null;
	if ( null === $data ) {
		$data = array();
		foreach ( array( 'data/ready-articles.php', 'data/ready-guides.php' ) as $rel ) {
			$file = SA_CHILD_DIR . $rel;
			$set  = file_exists( $file ) ? include $file : array();
			if ( ! is_array( $set ) ) {
				continue;
			}
			foreach ( $set as $key => $article ) {
				if ( ! is_array( $article ) || empty( $article['slug'] ) ) {
					continue;
				}
				$data[ $key ] = sa_ready_article_normalize( $article );
			}
		}
	}
	return $data;
}

/**
 * Fill the optional keys of a ready-article record with safe defaults.
 *
 * @param array<string,mixed> $article Raw record.
 * @return array<string,mixed>
 */
function sa_ready_article_normalize( $article ) {
	$defaults = array(
		'title'         => '',
		'type'          => 'attraction',
		'slug'          => '',
		'excerpt'       => '',
		'english'       => '',
		'kind'          => 'mountain',
		'province'      => '',
		'province_name' => '',
		'city'          => '',
		'city_name'     => '',
		'category'      => array(),
		'tags'          => array(),
		'terms'         => array(),
		'meta'          => array(),
		'faq'           => array(),
		'gallery'       => array(),
		'cover'                 => array(), // v2.13.2 — تصویر شاخص همراه قالب: file (نسبت به assets/img/) + alt + title.
		'remove_featured_image' => false, // حذفِ صریحِ تصویر شاخصِ همین نوشته؛ پیوست رسانه‌ای پاک نمی‌شود.
		'content'               => '',
	);
	$article  = array_merge( $defaults, $article );
	if ( 'post' === $article['type'] && '' === $article['kind'] ) {
		$article['kind'] = 'tent';
	}
	return $article;
}

/**
 * Whether a record is a plain WordPress post (guide) rather than an entity.
 *
 * @param array<string,mixed> $article Record.
 * @return bool
 */
function sa_ready_article_is_post( $article ) {
	return isset( $article['type'] ) && 'post' === $article['type'];
}

/**
 * Human label for the record's section: the city/province for attractions, the
 * category (and first tag) for guides. Used by the admin table and the cover card.
 *
 * @param array<string,mixed> $article Record.
 * @return array{0:string,1:string} [primary, secondary]
 */
function sa_ready_article_place( $article ) {
	if ( sa_ready_article_is_post( $article ) ) {
		$cat = is_array( $article['category'] ) ? array_values( $article['category'] ) : array();
		$tag = is_array( $article['tags'] ) ? array_values( $article['tags'] ) : array();
		return array( $tag ? (string) $tag[0] : '', $cat ? (string) $cat[0] : '' );
	}
	return array( (string) $article['city_name'], (string) $article['province_name'] );
}

/**
 * Menu entry under the unified «سرزمین آریان» menu.
 */
function sa_ready_articles_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'مقالات آماده (دیدنی‌ها و آموزش‌ها)',
		'مقالات آماده',
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
	if ( '' === $url || ! in_array( $host, array( 'upload.wikimedia.org', 'thumb.wikimedia.org', 'commons.wikimedia.org' ), true ) ) {
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
		$attachment_id = absint( $existing[0] );
		// The first render of a new article may import before its post ID exists.
		// Attach that cached image on the follow-up render, without moving an
		// attachment that is already associated with another post.
		if ( $post_id ) {
			$attachment_post = get_post( $attachment_id );
			if ( $attachment_post && empty( $attachment_post->post_parent ) ) {
				wp_update_post(
					array(
						'ID'          => $attachment_id,
						'post_parent' => absint( $post_id ),
					)
				);
			}
		}
		return $attachment_id;
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
 * Apply the per-article featured-image policy from its ready-guide record.
 *
 * `remove_featured_image` is explicit and scoped to this post. It unsets only
 * the thumbnail relationship; the attachment and its file remain in Media.
 * For records without the flag, the existing bundled-cover policy is retained.
 *
 * @param int                 $post_id Post ID.
 * @param array<string,mixed> $article Article record.
 * @return int|WP_Error 0 when removed/no change, or the cover attachment result.
 */
function sa_ready_articles_apply_featured_image_policy( $post_id, $article ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return 0;
	}
	if ( ! empty( $article['remove_featured_image'] ) ) {
		delete_post_thumbnail( $post_id );
		update_post_meta( $post_id, '_sa_ready_remove_featured_image', '1' );
		return 0;
	}
	delete_post_meta( $post_id, '_sa_ready_remove_featured_image' );
	return sa_ready_articles_attach_cover( $post_id, $article );
}

/**
 * v2.13.2 — Attach the featured image that ships with the theme (assets/img/<file>).
 *
 * The owner's infographics are bundled as `assets/img/guides/<slug>.webp` and
 * referenced from the article record (`cover` => file/alt/title, generated from
 * the Markdown SEO block). On import the file is copied once into the media
 * library (fingerprinted by MD5 so repeated runs reuse the same attachment),
 * the alt text — which carries the article's focus keyword — is written to
 * `_wp_attachment_image_alt`, and the attachment becomes the post thumbnail.
 *
 * Replacement rules: a missing thumbnail is filled; a generated white card
 * (`_sa_ready_cover_article`) or an older copy of this same bundled file is
 * replaced; an image the owner picked by hand is never touched. This step is
 * additive (no content change), so it also runs for published posts.
 *
 * @param int                 $post_id Post ID.
 * @param array<string,mixed> $article Article record.
 * @return int|WP_Error Attachment ID when (re)attached, 0 when nothing changed.
 */
function sa_ready_articles_attach_cover( $post_id, $article ) {
	$post_id = absint( $post_id );
	$cover   = isset( $article['cover'] ) && is_array( $article['cover'] ) ? $article['cover'] : array();
	$file    = isset( $cover['file'] ) ? ltrim( (string) $cover['file'], '/' ) : '';
	if ( ! $post_id || '' === $file || false !== strpos( $file, '..' ) || ! preg_match( '#^[a-z0-9][a-z0-9/_-]*\.(webp|jpe?g|png)$#', $file ) ) {
		return 0;
	}
	$path = SA_CHILD_DIR . 'assets/img/' . $file;
	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'cover_missing', 'فایل تصویر شاخص در قالب پیدا نشد: assets/img/' . $file );
	}
	$hash = (string) md5_file( $path );
	$alt  = isset( $cover['alt'] ) ? sanitize_text_field( (string) $cover['alt'] ) : '';
	if ( '' === $alt ) {
		$keyword = isset( $article['meta']['sa_focus_keyword'] ) ? (string) $article['meta']['sa_focus_keyword'] : '';
		$alt     = trim( 'تصویر شاخص ' . ( '' !== $keyword ? $keyword : (string) $article['title'] ) );
	}
	$title = isset( $cover['title'] ) && '' !== trim( (string) $cover['title'] ) ? sanitize_text_field( (string) $cover['title'] ) : $alt;

	$current_id = (int) get_post_thumbnail_id( $post_id );
	if ( $current_id ) {
		$is_ours      = (string) get_post_meta( $current_id, '_sa_ready_cover_file', true ) === $file;
		$is_generated = '' !== (string) get_post_meta( $current_id, '_sa_ready_cover_article', true );
		if ( $is_ours && (string) get_post_meta( $current_id, '_sa_ready_cover_hash', true ) === $hash ) {
			// Same picture already in place — only keep alt/title in sync.
			// v2.15.0: فایلِ پاک‌شدهٔ همین تصویر شاخص را از نسخهٔ همراه قالب برمی‌گردانیم.
			if ( function_exists( 'sa_media_attachment_missing' ) && sa_media_attachment_missing( $current_id ) ) {
				sa_media_restore_from_file( $current_id, $path );
			}
			update_post_meta( $current_id, '_wp_attachment_image_alt', $alt );
			return 0;
		}
		if ( ! $is_ours && ! $is_generated ) {
			return 0; // تصویری که مالک خودش انتخاب کرده هرگز جایگزین نمی‌شود.
		}
	}

	// Reuse the attachment created from this exact file earlier (e.g. the post was re-imported).
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_sa_ready_cover_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $hash, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$attachment_id = $existing ? absint( $existing[0] ) : 0;

	if ( ! $attachment_id ) {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- theme-bundled asset.
		if ( false === $contents || strlen( $contents ) < 512 ) {
			return new WP_Error( 'cover_unreadable', 'تصویر شاخص قابل خواندن نیست: assets/img/' . $file );
		}
		$upload = wp_upload_bits( wp_basename( $file ), null, $contents );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'cover_upload', (string) $upload['error'] );
		}
		$filetype = wp_check_filetype( $upload['file'] );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => ! empty( $filetype['type'] ) ? $filetype['type'] : 'image/webp',
				'post_title'     => $title,
				'post_excerpt'   => '',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$post_id,
			true
		);
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		$attachment_id = absint( $attachment_id );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_sa_ready_cover_file', $file );
		update_post_meta( $attachment_id, '_sa_ready_cover_hash', $hash );
	} else {
		$attachment = get_post( $attachment_id );
		if ( $attachment && empty( $attachment->post_parent ) ) {
			wp_update_post(
				array(
					'ID'          => $attachment_id,
					'post_parent' => $post_id,
				)
			);
		}
	}

	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	set_post_thumbnail( $post_id, $attachment_id );
	sa_ready_articles_cover_log( (string) $article['slug'], $attachment_id );
	return $attachment_id;
}

/**
 * v2.13.2 — Per-request log of covers attached by sa_ready_articles_attach_cover().
 *
 * @param string|null $slug          Record slug to log; null to read the log.
 * @param int         $attachment_id Attachment ID.
 * @return array<string,int>
 */
function sa_ready_articles_cover_log( $slug = null, $attachment_id = 0 ) {
	static $log = array();
	if ( null !== $slug ) {
		$log[ (string) $slug ] = absint( $attachment_id );
	}
	return $log;
}

/**
 * Build an escaped caption and optional linked image attribution.
 *
 * @param array<string,string> $item Image description and attribution fields.
 * @return string
 */
function sa_ready_articles_caption( $item ) {
	$description = isset( $item['caption'] ) ? trim( (string) $item['caption'] ) : '';
	$attribution = array();

	if ( ! empty( $item['credit'] ) ) {
		$attribution[] = 'عکس: ' . esc_html( (string) $item['credit'] );
	}
	if ( ! empty( $item['source_url'] ) ) {
		$source_url = esc_url( (string) $item['source_url'] );
		if ( $source_url ) {
			$attribution[] = '<a href="' . $source_url . '" target="_blank" rel="noopener noreferrer">صفحهٔ منبع</a>';
		}
	}
	if ( ! empty( $item['license_name'] ) ) {
		$license_name = esc_html( (string) $item['license_name'] );
		$license_url  = empty( $item['license_url'] ) ? '' : esc_url( (string) $item['license_url'] );
		if ( $license_url ) {
			$attribution[] = 'مجوز: <a href="' . $license_url . '" target="_blank" rel="license noopener noreferrer">' . $license_name . '</a>';
		} else {
			$attribution[] = 'مجوز: ' . $license_name;
		}
	}
	if ( ! empty( $item['edit_note'] ) ) {
		$attribution[] = 'تغییر: ' . esc_html( (string) $item['edit_note'] );
	}

	if ( '' === $description && empty( $attribution ) ) {
		return '';
	}

	$html = '<figcaption>';
	if ( '' !== $description ) {
		$html .= '<span class="sa-article-image-caption">' . esc_html( $description ) . '</span>';
	}
	if ( ! empty( $attribution ) ) {
		$html .= '<small class="sa-article-image-attribution">' . implode( ' <span aria-hidden="true">·</span> ', $attribution ) . '</small>';
	}
	return $html . '</figcaption>';
}

/**
 * Build a gallery figure for the article body when the attachment exists.
 *
 * @param array<string,string> $item file/caption/alt/theme_img and optional attribution.
 * @return string
 */
function sa_ready_articles_figure( $item, $post_id = 0 ) {
	// تصویر بهینه‌شدهٔ همراه قالب (WebP پردازش‌شده در assets/img/articles/<slug>/).
	// انتساب، صفحهٔ منبع، مجوز و یادداشت ویرایش در صورت وجود در زیرنویس دیده می‌شوند.
	if ( ! empty( $item['theme_img'] ) ) {
		$url     = SA_CHILD_URI . 'assets/img/articles/' . ltrim( (string) $item['theme_img'], '/' );
		$alt     = empty( $item['alt'] ) ? '' : esc_attr( $item['alt'] );
		$caption = sa_ready_articles_caption( $item );
		return '<figure class="wp-block-image size-large"><img loading="lazy" decoding="async" src="' . esc_url( $url ) . '" alt="' . $alt . '" />' . $caption . '</figure>';
	}
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
	// v2.15.0: اگر فایلِ پیوست از سرور پاک شده باشد، پیش از تولید خروجی از
	// نسخهٔ همراه قالب یا نشانی منبع همان قلم برمی‌گردد؛ اگر نشد تصویر شکسته
	// منتشر نمی‌شود (نوار جای‌گزین در inc/featured-images.php نمایش داده می‌شود).
	if ( function_exists( 'sa_media_attachment_missing' ) && sa_media_attachment_missing( $id ) ) {
		sa_media_try_restore_attachment( $id, $item );
		if ( sa_media_attachment_missing( $id ) ) {
			return '';
		}
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
	$caption = sa_ready_articles_caption( $item );
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
	if ( false !== strpos( $content, '{{FAQ}}' ) ) {
		$faq_html = '';
		if ( ! empty( $article['faq'] ) && is_array( $article['faq'] ) ) {
			foreach ( $article['faq'] as $row ) {
				$faq_html .= '<h3>' . esc_html( (string) $row['q'] ) . '</h3>' . "\n" . '<p>' . esc_html( (string) $row['a'] ) . '</p>' . "\n";
			}
		}
		$content = str_replace( '{{FAQ}}', trim( $faq_html ), $content );
	}
	if ( false !== strpos( $content, '{{SOURCES}}' ) ) {
		$src_html = '';
		$sources  = isset( $article['meta']['sa_sources'] ) ? (string) $article['meta']['sa_sources'] : '';
		if ( '' !== $sources ) {
			$src_html = '<ul>' . "\n";
			foreach ( explode( "\n", $sources ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$src_html .= '<li>' . esc_html( $line ) . '</li>' . "\n";
			}
			$src_html .= '</ul>';
		}
		$content = str_replace( '{{SOURCES}}', $src_html, $content );
	}
	if ( sa_ready_article_is_post( $article ) && ! empty( $article['category'] ) && is_array( $article['category'] ) ) {
		// Guides link to their hub as /category/<slug>/; honour a custom category base.
		foreach ( array_keys( $article['category'] ) as $cat_slug ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $cat_slug ), 'category' );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$relative = wp_make_link_relative( $link );
			$default  = '/category/' . $cat_slug . '/';
			if ( $relative && $relative !== $default ) {
				$content = str_replace( 'href="' . $default . '"', 'href="' . esc_url( $relative ) . '"', $content );
			}
		}
	}
	return trim( preg_replace( '/\{\{GALLERY:[a-zA-Z0-9_-]+\}\}/', '', $content ) );
}

/**
 * Insert or update one ready article.
 *
 * @param string $key                 Article key.
 * @param bool   $overwrite_published Allow updating an already published article.
 * @param bool   $publish             v2.12.7 — owner approval: publish the article (new or
 *                                    draft) instead of leaving it as a draft. The caller
 *                                    must have verified the `publish_posts` capability.
 * @return int|WP_Error Post ID or error.
 */
function sa_ready_article_import( $key, $overwrite_published = false, $publish = false ) {
	$data = sa_ready_articles_data();
	if ( empty( $data[ $key ] ) ) {
		return new WP_Error( 'unknown_article', 'این مقالهٔ آماده در قالب پیدا نشد.' );
	}
	$article = $data[ $key ];

	if ( ! post_type_exists( $article['type'] ) ) {
		return new WP_Error( 'missing_cpt', 'نوع محتوای موردنیاز فعال نیست؛ ابتدا قالب فرزند «سرزمین آریان» را فعال کنید.' );
	}

	$is_post     = sa_ready_article_is_post( $article );
	$province_id = 0;
	if ( ! $is_post && ! empty( $article['province'] ) ) {
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
	}

	$city_id = 0;
	if ( ! $is_post && ! empty( $article['city'] ) ) {
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

	if ( $is_post && ! empty( $article['category'] ) && is_array( $article['category'] ) ) {
		// Create the guide category up front so the body can link to its archive.
		sa_ready_articles_term_ids( 'category', $article['category'] );
	}

	$post_id  = sa_ready_articles_find_post( $article );
	$status   = $post_id ? get_post_status( $post_id ) : '';
	$is_draft = ( '' === $status || in_array( $status, array( 'draft', 'auto-draft' ), true ) );

	if ( $post_id && ! $is_draft && ! $overwrite_published ) {
		// Featured-image changes remain scoped to this article; an explicit removal
		// unsets its thumbnail, while ordinary cover imports stay additive.
		sa_ready_articles_apply_featured_image_policy( $post_id, $article );
		return new WP_Error(
			'published_locked',
			'این مقاله وضعیت «' . $status . '» دارد. برای به‌روزرسانی، گزینهٔ «بازنویسی نسخهٔ منتشرشده» را تیک بزنید.'
		);
	}

	/*
	 * v2.12.7 — the old «published without a featured image» refusal is gone:
	 * the gate never demotes a published page (v2.11.42) and, under the
	 * owner-approval policy, a missing thumbnail is a reminder, not a lock.
	 */

	$postarr = array(
		'post_type'    => $article['type'],
		'post_status'  => $post_id ? $status : 'draft', // new articles start as drafts; owner approval publishes below.
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
		if ( $publish && 'publish' !== $status ) {
			$postarr['post_status'] = 'publish'; // تأیید مالک.
		}
		$saved = wp_update_post( $postarr, true );
	} else {
		$saved = wp_insert_post( $postarr, true );
	}
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	$post_id = absint( $saved );

	if ( ! isset( $postarr['ID'] ) ) {
		sa_ready_articles_write_meta( $post_id, $article, $province_id, $city_id );
		if ( $publish ) {
			// تأیید مالک: پس از ثبت متادیتا منتشر می‌شود تا دروازهٔ سطح ۷ یادآوری‌هایش
			// را روی همین نوشته ثبت کند (و در حالت اختیاریِ «سخت‌گیر» بتواند ارزیابی کند).
			$published = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $published ) ) {
				return $published;
			}
		}
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

	// Per-article policy: attach the bundled cover, or remove this post's thumbnail only.
	$cover = sa_ready_articles_apply_featured_image_policy( $post_id, $article );
	if ( is_wp_error( $cover ) ) {
		return $cover;
	}

	if ( ! $is_post && function_exists( 'sa_sync_relations' ) ) {
		sa_sync_relations( $post_id, $article['type'] );
	}
	if ( ! $is_post && function_exists( 'sa_flush_relation_cache' ) ) {
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
	$meta    = isset( $article['meta'] ) && is_array( $article['meta'] ) ? $article['meta'] : array();
	$is_post = sa_ready_article_is_post( $article );

	if ( ! $is_post ) {
		$meta['sa_province_id'] = absint( $province_id );
		if ( $city_id ) {
			$meta['sa_city_id'] = absint( $city_id );
		}
	}
	foreach ( $meta as $meta_key => $meta_value ) {
		if ( '' === $meta_value || null === $meta_value ) {
			delete_post_meta( $post_id, $meta_key );
			continue;
		}
		update_post_meta( $post_id, $meta_key, $meta_value );
	}

	if ( ! empty( $article['faq'] ) && is_array( $article['faq'] ) ) {
		update_post_meta(
			$post_id,
			'sa_faq',
			wp_json_encode( array_values( $article['faq'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
		);
	}

	$terms = ! empty( $article['terms'] ) && is_array( $article['terms'] ) ? $article['terms'] : array();
	if ( $is_post ) {
		// Guides: WordPress category + tags (slug => name; created on first use).
		if ( ! empty( $article['category'] ) && is_array( $article['category'] ) ) {
			$terms['category'] = $article['category'];
		}
		if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
			$terms['post_tag'] = $article['tags'];
		}
	}
	foreach ( $terms as $taxonomy => $pairs ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $pairs ) ) {
			continue;
		}
		$ids = sa_ready_articles_term_ids( $taxonomy, $pairs );
		if ( $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}
	if ( ! $is_post && taxonomy_exists( 'province_tax' ) && ! empty( $article['province'] ) ) {
		wp_set_object_terms( $post_id, $article['province'], 'province_tax', false );
	}
}

/**
 * Resolve slug => name pairs to term IDs, creating missing terms.
 *
 * @param string               $taxonomy Taxonomy.
 * @param array<string,string> $pairs    slug => Persian name.
 * @return int[]
 */
function sa_ready_articles_term_ids( $taxonomy, $pairs ) {
	$ids = array();
	foreach ( $pairs as $term_slug => $term_name ) {
		$term_slug = sanitize_title( (string) $term_slug );
		$term      = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term ) {
			$created = wp_insert_term( (string) $term_name, $taxonomy, array( 'slug' => $term_slug ) );
			if ( is_wp_error( $created ) ) {
				continue;
			}
			$ids[] = absint( $created['term_id'] );
		} else {
			$ids[] = absint( $term->term_id );
		}
	}
	return $ids;
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
	$publish   = sa_ready_articles_publish_requested();
	$result    = sa_ready_article_import( $key, $overwrite, $publish );

	$args = array( 'page' => 'sa-ready-articles' );
	if ( is_wp_error( $result ) ) {
		$args['sa-ready'] = 'error';
		$args['sa-msg']   = rawurlencode( $result->get_error_message() );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	$status = (string) get_post_status( $result );
	if ( 'publish' === $status ) {
		$args['sa-ready'] = 'published';
	} elseif ( 'draft' === $status ) {
		$args['sa-ready'] = $publish ? 'gated' : 'draft';
	} else {
		$args['sa-ready'] = 'updated';
	}
	$args['sa-post'] = absint( $result );
	if ( sa_ready_articles_cover_log() ) {
		$args['sa-cover'] = 1; // v2.13.2
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sa_ready_article', 'sa_ready_articles_handle_import' );

/**
 * v2.12.7 — Did the current request carry the owner's publish approval?
 *
 * The checkbox alone is not enough: the user must also be allowed to publish.
 *
 * @return bool
 */
function sa_ready_articles_publish_requested() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.
	return ! empty( $_POST['sa_publish_approved'] ) && current_user_can( 'publish_posts' );
}

/**
 * v2.12.7 — admin-post handler: import/update every ready article in one run.
 *
 * Published articles are skipped unless «بازنویسی نسخه‌های منتشرشده» is ticked;
 * «تأیید مالک» publishes new/draft articles. A summary is kept in a short-lived
 * transient and rendered on the tool screen after the redirect.
 */
function sa_ready_articles_handle_import_all() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای این کار دسترسی کافی ندارید.' ) );
	}
	check_admin_referer( 'sa_ready_import_all', 'sa_ready_nonce_all' );

	$overwrite = ! empty( $_POST['sa_overwrite_published'] );
	$publish   = sa_ready_articles_publish_requested();

	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may be disabled by the host.
	}

	$summary = array(
		'published' => array(),
		'updated'   => array(),
		'draft'     => array(),
		'skipped'   => array(),
		'errors'    => array(),
		'reminders' => 0,
		'covers'    => 0,
		'publish'   => $publish,
		'overwrite' => $overwrite,
	);

	foreach ( sa_ready_articles_data() as $key => $article ) {
		$title  = (string) $article['title'];
		$result = sa_ready_article_import( $key, $overwrite, $publish );
		if ( is_wp_error( $result ) ) {
			if ( 'published_locked' === $result->get_error_code() ) {
				$summary['skipped'][] = $title;
			} else {
				$summary['errors'][] = $title . ': ' . $result->get_error_message();
			}
			continue;
		}
		$status = (string) get_post_status( $result );
		if ( 'publish' === $status ) {
			$summary['published'][] = $title;
		} elseif ( 'draft' === $status ) {
			$summary['draft'][] = $title;
		} else {
			$summary['updated'][] = $title;
		}
		if ( function_exists( 'sa_gate_evaluate' ) ) {
			$report = sa_gate_evaluate( (int) $result );
			if ( ! empty( $report['warnings'] ) || ! empty( $report['blocking'] ) ) {
				++$summary['reminders'];
			}
		}
	}

	$summary['covers'] = count( sa_ready_articles_cover_log() ); // v2.13.2

	// گزارش گروهی جای نوار تک‌مقاله‌ایِ دروازه را می‌گیرد.
	delete_transient( 'sa_gate_' . get_current_user_id() );
	set_transient( 'sa_ready_bulk_' . get_current_user_id(), $summary, 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'     => 'sa-ready-articles',
				'sa-ready' => 'bulk',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_sa_ready_article_all', 'sa_ready_articles_handle_import_all' );

/**
 * v2.12.7 — Render the summary of the last bulk run (one-shot transient).
 *
 * @return void
 */
function sa_ready_articles_render_bulk_summary() {
	$key     = 'sa_ready_bulk_' . get_current_user_id();
	$summary = get_transient( $key );
	if ( ! is_array( $summary ) ) {
		echo '<div class="notice notice-info is-dismissible"><p>گزارش اجرای گروهی در دسترس نیست (منقضی شده است).</p></div>';
		return;
	}
	delete_transient( $key );

	$fa    = function ( $n ) {
		return function_exists( 'sa_fa_digits' ) ? sa_fa_digits( (string) $n ) : (string) $n;
	};
	$class = empty( $summary['errors'] ) ? 'notice-success' : 'notice-warning';

	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible sa-ready__bulk-summary">';
	echo '<p><strong>اجرای گروهی انجام شد.</strong> ';
	echo esc_html( 'منتشرشده: ' . $fa( count( $summary['published'] ) ) . ' · به‌روزرسانی (وضعیت دست‌نخورده): ' . $fa( count( $summary['updated'] ) ) . ' · پیش‌نویس: ' . $fa( count( $summary['draft'] ) ) . ' · رد شده: ' . $fa( count( $summary['skipped'] ) ) . ' · خطا: ' . $fa( count( $summary['errors'] ) ) );
	echo '</p>';
	if ( ! empty( $summary['reminders'] ) ) {
		echo '<p>' . esc_html( $fa( (int) $summary['reminders'] ) . ' مقاله یادآوری سطح ۷ دارد (مانع انتشار نیست؛ در ستون «وضعیت» و جعبهٔ «دروازهٔ انتشار» کنار ویرایشگر دیده می‌شود).' ) . '</p>';
	}
	if ( ! empty( $summary['covers'] ) ) {
		echo '<p>' . esc_html( 'تصویر شاخص همراه قالب برای ' . $fa( (int) $summary['covers'] ) . ' مقاله متصل یا به‌روز شد (با متن جایگزین حاوی کلمهٔ کلیدی).' ) . '</p>';
	}
	if ( ! empty( $summary['skipped'] ) ) {
		echo '<p>' . esc_html( 'رد شده چون منتشرشده بود و تیک «بازنویسی نسخه‌های منتشرشده» نخورده بود: ' . implode( '، ', array_map( 'strval', $summary['skipped'] ) ) ) . '</p>';
	}
	if ( ! empty( $summary['errors'] ) ) {
		echo '<ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( $summary['errors'] as $line ) {
			echo '<li>' . esc_html( (string) $line ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
}

/**
 * Render the tool screen.
 */
function sa_ready_articles_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'برای دیدن این صفحه دسترسی کافی ندارید.' ) );
	}
	$data        = sa_ready_articles_data();
	$can_publish = current_user_can( 'publish_posts' );
	$advisory    = function_exists( 'sa_gate_is_advisory' ) ? sa_gate_is_advisory() : true;
	$state       = isset( $_GET['sa-ready'] ) ? sanitize_key( wp_unslash( $_GET['sa-ready'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap sa-ready">
		<h1>مقالات آماده — دیدنی‌ها و آموزش‌های کمپینگ</h1>
		<p class="sa-ready__lead">
			<strong>دو دسته مقاله در این فهرست است:</strong> «دیدنی» (نوع محتوای جاذبه، با استان و شهرستان) و «آموزش» (نوشتهٔ معمول وردپرس در دستهٔ <strong>آموزش‌های ضروری کمپینگ و بقا</strong> با برچسب شاخه؛ از نسخهٔ ۲٫۱۳٫۰). متن آموزش‌ها با فیلتر محتوای تحریریه بررسی شده و پرسش‌های رایج و منابع آن‌ها در بخش‌های جداگانهٔ صفحه نمایش داده می‌شوند.
		</p>
		<p class="sa-ready__lead">
			<strong>سیاست انتشار (از نسخهٔ ۲٫۱۲٫۷):</strong> شرط انتشار فقط <strong>تأیید مالک</strong> است.
			درج یا به‌روزرسانی بدون تیک «تأیید مالک» مقاله را <strong>پیش‌نویس</strong> نگه می‌دارد؛ با تیک «تأیید مالک» همان‌جا منتشر می‌شود.
			کمبودهای سطح ۷ (رابطه، سئو، منابع، تصویر شاخص، طبقه‌بندی، بهداشت محتوا…) فقط به‌صورت <strong>یادآوری</strong> در ستون «وضعیت» و در جعبهٔ «دروازهٔ انتشار» کنار ویرایشگر نشان داده می‌شوند و مانع انتشار نیستند.
			برای هر مقاله می‌توانید کارت سفید تصویر شاخص (۱۲۰۰×۶۰۰) را بسازید: زمینهٔ کاملاً سفید، یک آیکون رنگی گوشه‌ای، نوشته‌های مرکزچین با رنگ‌های آبی ناوی، سبز تیره و طلایی تیره و سایهٔ عمیق دور کاور.
		</p>
		<?php if ( ! $advisory ) : ?>
			<div class="notice notice-warning"><p>دروازهٔ انتشار در سفارشی‌ساز روی حالت <strong>سخت‌گیر</strong> است؛ در این حالت مقالهٔ ناقص حتی با تیک «تأیید مالک» پیش‌نویس می‌ماند. برای اجرای سیاست «فقط تأیید مالک» حالت را به «یادآوری» برگردانید (سفارشی‌ساز → سرزمین آریان → قوانین انتشار).</p></div>
		<?php endif; ?>

		<?php if ( 'bulk' === $state ) : ?>
			<?php sa_ready_articles_render_bulk_summary(); ?>
		<?php elseif ( '' !== $state ) : ?>
			<?php
			$ok    = in_array( $state, array( 'draft', 'updated', 'published', 'gated' ), true );
			$class = $ok ? ( 'gated' === $state ? 'notice-warning' : 'notice-success' ) : 'notice-error';
			if ( 'draft' === $state ) {
				$text = 'مقاله به‌صورت پیش‌نویس ذخیره شد. برای انتشار، تیک «تأیید مالک» را بزنید یا در ویرایشگر «انتشار» را بزنید.';
			} elseif ( 'updated' === $state ) {
				$text = 'مقاله به‌روزرسانی شد و وضعیت انتشار آن دست‌نخورده ماند.';
			} elseif ( 'published' === $state ) {
				$text = 'مقاله با تأیید شما منتشر شد. یادآوری‌های سطح ۷ (در صورت وجود) در ستون «وضعیت» و جعبهٔ «دروازهٔ انتشار» دیده می‌شوند.';
			} elseif ( 'gated' === $state ) {
				$text = 'انتشار درخواست شد، اما دروازهٔ انتشار (حالت سخت‌گیر) مقاله را پیش‌نویس نگه داشت. در جعبهٔ «دروازهٔ انتشار» کنار ویرایشگر ببینید چه چیزی کم است، یا حالت دروازه را به «یادآوری» برگردانید.';
			} else {
				$text = isset( $_GET['sa-msg'] ) ? rawurldecode( wp_unslash( $_GET['sa-msg'] ) ) : 'خطای نامشخص.'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- escaped on output below.
			}
			$edit = isset( $_GET['sa-post'] ) ? absint( $_GET['sa-post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $ok && ! empty( $_GET['sa-cover'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$text .= ' تصویر شاخص همراه قالب (با متن جایگزین حاوی کلمهٔ کلیدی) متصل شد.';
			}
			?>
			<div class="notice <?php echo esc_attr( $class ); ?> is-dismissible"><p>
				<?php echo esc_html( $text ); ?>
				<?php if ( $ok && $edit ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $edit ) ); ?>">ویرایش همین حالا</a>
					<?php if ( 'published' === $state ) : ?>
						· <a href="<?php echo esc_url( (string) get_permalink( $edit ) ); ?>">مشاهده</a>
					<?php endif; ?>
				<?php endif; ?>
			</p></div>
		<?php endif; ?>

		<form class="sa-ready__bulk" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_ready_article_all">
			<?php wp_nonce_field( 'sa_ready_import_all', 'sa_ready_nonce_all' ); ?>
			<strong>اجرای گروهی روی هر <?php echo esc_html( sa_fa_digits( count( $data ) ) ); ?> مقاله:</strong>
			<button type="submit" class="button button-primary">درج/به‌روزرسانی همه از منبع قالب</button>
			<label class="sa-ready__check">
				<input type="checkbox" name="sa_overwrite_published" value="1">
				بازنویسی نسخه‌های منتشرشده
			</label>
			<?php if ( $can_publish ) : ?>
				<label class="sa-ready__check sa-ready__check--approve">
					<input type="checkbox" name="sa_publish_approved" value="1">
					تأیید مالک — مقاله‌های جدید و پیش‌نویس منتشر شوند
				</label>
			<?php endif; ?>
			<span class="description">بدون تیک «بازنویسی»، مقاله‌های منتشرشده دست‌نخورده می‌مانند و در گزارش «رد شده» شمرده می‌شوند.</span>
		</form>

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
					<th>تصویر شاخص</th>
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
				$bundled   = ! empty( $article['cover']['file'] ); // v2.13.2 — تصویر شاخص همراه قالب
				$from_pack = $cover_id ? ( (string) get_post_meta( $cover_id, '_sa_ready_cover_file', true ) === ltrim( (string) ( $bundled ? $article['cover']['file'] : '' ), '/' ) ) : false;
				$is_post   = sa_ready_article_is_post( $article );
				list( $place_main, $place_sub ) = sa_ready_article_place( $article );
				$place     = implode( ' — ', array_filter( array( $place_main, $place_sub ) ) );
				$city_line = $is_post ? $place_main : 'شهرستان ' . $place_main;
				$prov_line = $is_post ? $place_sub : 'استان ' . $place_sub;
				if ( 'attraction' === $article['type'] ) {
					$type_label = 'دیدنی';
				} elseif ( $is_post ) {
					$type_label = 'آموزش';
				} else {
					$type_label = 'شهرستان';
				}
				// v2.12.7 — یادآوری‌های سطح ۷ (هشدار، نه مانع) برای مقاله‌های واردشده.
				$gate = ( $post_id && function_exists( 'sa_gate_evaluate' ) )
					? sa_gate_evaluate( $post_id )
					: array(
						'blocking' => array(),
						'warnings' => array(),
					);
				$gate_items = array_merge( (array) $gate['blocking'], (array) $gate['warnings'] );
				?>
				<tr>
					<td>
						<strong><?php echo esc_html( $article['title'] ); ?></strong>
						<code dir="ltr"><?php echo esc_html( $article['slug'] ); ?></code>
					</td>
					<td><?php echo esc_html( $type_label ); ?></td>
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
							<?php if ( ! empty( $gate['blocking'] ) ) : ?>
								<details class="sa-ready__gate sa-ready__gate--blocking">
									<summary><?php echo esc_html( sa_fa_digits( count( $gate['blocking'] ) ) ); ?> مانع (حالت سخت‌گیر)</summary>
									<ul>
										<?php foreach ( $gate['blocking'] as $item ) : ?>
											<li><?php echo esc_html( (string) $item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php endif; ?>
							<?php if ( ! empty( $gate['warnings'] ) ) : ?>
								<details class="sa-ready__gate">
									<summary><?php echo esc_html( sa_fa_digits( count( $gate['warnings'] ) ) ); ?> یادآوری سطح ۷ (مانع انتشار نیست)</summary>
									<ul>
										<?php foreach ( $gate['warnings'] as $item ) : ?>
											<li><?php echo esc_html( (string) $item ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php elseif ( empty( $gate_items ) ) : ?>
								<span class="sa-ready__mini sa-ready__mini--ok">بدون یادآوری ✓</span>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $has_cover ) : ?>
							<span class="sa-ready__badge sa-ready__badge--cover">
								<?php echo esc_html( $from_pack ? 'تصویر همراه قالب ✓' : ( $generated ? 'کارت سفید قالب' : 'تصویر ثبت‌شده' ) ); ?>
							</span>
						<?php else : ?>
							<span class="sa-ready__badge sa-ready__badge--none">ندارد</span>
						<?php endif; ?>
						<?php if ( $bundled && ! $from_pack ) : ?>
							<span class="sa-ready__mini"><?php echo esc_html( $has_cover && ! $generated ? 'تصویر همراه قالب موجود است؛ تصویر انتخابی شما دست نمی‌خورد.' : 'با «درج/به‌روزرسانی» تصویر همراه قالب متصل می‌شود.' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="sa-ready__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="sa_ready_article">
							<input type="hidden" name="sa_article" value="<?php echo esc_attr( $key ); ?>">
							<?php wp_nonce_field( 'sa_ready_import_' . $key, 'sa_ready_nonce' ); ?>
							<button type="submit" class="button <?php echo $post_id ? '' : 'button-primary'; ?>">
								<?php echo esc_html( $post_id ? 'به‌روزرسانی از منبع قالب' : 'درج از منبع قالب' ); ?>
							</button>
							<label class="sa-ready__check">
								<input type="checkbox" name="sa_overwrite_published" value="1">
								بازنویسی نسخهٔ منتشرشده
							</label>
							<?php if ( $can_publish && 'publish' !== $status ) : ?>
								<label class="sa-ready__check sa-ready__check--approve">
									<input type="checkbox" name="sa_publish_approved" value="1">
									تأیید مالک — منتشر شود
								</label>
							<?php endif; ?>
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
							data-province="<?php echo esc_attr( $prov_line ); ?>"
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
			نکته‌ها: درج جدید بدون تیک «تأیید مالک» پیش‌نویس می‌ماند و با تیک آن منتشر می‌شود؛ اگر مقاله‌ای از قبل منتشر شده باشد تنها با تیک «بازنویسی نسخهٔ منتشرشده» به‌روزرسانی می‌شود و منتشر می‌ماند.
			یادآوری‌های سطح ۷ هشدارند، نه مانع؛ پس از انتشار هم در جعبهٔ «دروازهٔ انتشار» کنار ویرایشگر می‌مانند تا تکمیل شوند.
			کارت تصویر شاخص به‌صورت WebP (و در صورت پشتیبانی‌نشدن مرورگر، PNG) ذخیره و بلافاصله تصویر شاخصِ مقاله می‌شود.
			از نسخهٔ ۲٫۱۳٫۲ آموزش‌هایی که تصویر شاخص همراه قالب دارند (اینفوگرافیک‌های مالک در <code>assets/img/guides/</code>) هنگام درج/به‌روزرسانی — حتی برای مقالهٔ منتشرشده و بدون تیک بازنویسی — همان تصویر را با متن جایگزینِ حاوی کلمهٔ کلیدی می‌گیرند؛ کارت سفید قبلی جایگزین می‌شود ولی تصویری که خودتان انتخاب کرده باشید دست نمی‌خورد.
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

	// v2.13.0 — the caption follows the record (city/province for attractions,
	// branch/category for guides) instead of a fixed place name.
	list( $place_main, $place_sub ) = sa_ready_article_place( $article );
	$place_fa = implode( ' | ', array_filter( array( $article['title'], $place_main, $place_sub ) ) );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => 'کارت سفید ' . $place_fa,
			'post_excerpt'   => $place_fa . ( '' !== (string) $article['english'] ? ' / ' . $article['english'] : '' ),
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
		'کارت سفید ' . $place_fa . ' با آیکون رنگی گوشه‌ای و نوشته‌های مرکزچین' . ( '' !== (string) $article['english'] ? ' — ' . $article['english'] : '' )
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
