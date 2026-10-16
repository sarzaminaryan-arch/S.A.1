<?php
/**
 * Plugin Name: SA Site Audit Exporter
 * Description: خروجی فقط‌خواندنی از صفحات عمومی، پیوندهای داخلی و متادیتای SEO برای ممیزی سرزمین آریان.
 * Version: 1.2.0
 * Author: Sarzamin Aryan
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: sa-site-audit-exporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public post meta explicitly safe/useful for the audit export.
 * Never export arbitrary post meta, users, options, drafts or private content.
 *
 * @return string[]
 */
function sa_audit_exporter_meta_allowlist() {
	return array(
		'sa_seo_title',
		'sa_seo_description',
		'sa_focus_keyword',
		'sa_canonical_url',
		'sa_noindex',
		'sa_city_id',
		'sa_province_id',
		'sa_city_ids',
		'sa_province_term_id',
		'sa_related_attraction_ids',
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_robots',
	);
}

/**
 * Add the read-only exporter under Tools.
 */
function sa_audit_exporter_add_page() {
	add_management_page(
		'خروجی ممیزی سایت',
		'خروجی ممیزی سایت',
		'manage_options',
		'sa-site-audit-exporter',
		'sa_audit_exporter_render_page'
	);
}
add_action( 'admin_menu', 'sa_audit_exporter_add_page' );

/**
 * Render the Persian admin screen.
 */
function sa_audit_exporter_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی مجاز نیست.', 'sa-site-audit-exporter' ) );
	}
	?>
	<div class="wrap" dir="rtl">
		<h1>خروجی فقط‌خواندنی برای ممیزی سایت</h1>
		<p>این ابزار محتوای <strong>منتشرشده و عمومی</strong> را برای تحلیل ساختار سایت جمع می‌کند. هیچ پستی را تغییر نمی‌دهد، چیزی را منتشر یا حذف نمی‌کند و هیچ اطلاعاتی را به سرور خارجی نمی‌فرستد.</p>
		<h2>چه چیزهایی در فایل می‌آید؟</h2>
		<ul>
			<li>فهرست صفحات عمومی منتشرشده: نوع محتوا، عنوان، نشانی، نامک، تاریخ‌ها، excerpt و متن عمومی صفحه.</li>
			<li>خلاصهٔ ساختار: شمار هدینگ‌ها، تعداد واژهٔ تقریبی، taxonomyهای عمومی و پیوندهای موجود در متن.</li>
			<li>فقط فیلدهای مشخص SEO و رابطه‌های استان/شهرستان از فهرست محدود و ازپیش‌تعریف‌شده.</li>
			<li>نسخهٔ WordPress/PHP، قالب فعال و شمار محتوای منتشرشده به تفکیک نوع.</li>
		</ul>
		<h2>چه چیزهایی جمع نمی‌شود؟</h2>
		<p>پیش‌نویس و محتوای خصوصی، حساب‌های کاربری، ایمیل/رمز/تنظیمات، دیدگاه‌ها، سفارش‌ها، فرم‌ها، همهٔ metaها و داده‌های Analytics جمع نمی‌شوند. هر عملیات، یک فایل JSON را فقط برای دانلود مدیر سایت می‌سازد.</p>
		<p><strong>نکته:</strong> متن HTML ذخیره‌شده در نوشته‌ها صادر می‌شود؛ خروجیِ نهاییِ ساخته‌شده توسط قالب (مثلاً شناسنامه‌های داینامیک یا HTML واقعی پس از اجرا) مستقیماً در این فایل نیست و باید در گام بعد با crawl زنده بررسی شود.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sa_audit_exporter_download">
			<?php wp_nonce_field( 'sa_audit_exporter_download', 'sa_audit_exporter_nonce' ); ?>
			<?php submit_button( '۱) ساخت و دریافت فهرست محتوا (JSON)', 'primary', 'submit', false ); ?>
		</form>
		<hr>
		<h2>۲) نمونه‌برداری از HTML زنده</h2>
		<p>این گزینه در مرورگر شما و فقط روی دامنهٔ همین سایت کار می‌کند؛ همهٔ URLهای پست‌های منتشرشده در همهٔ post typeهای عمومی، آرشیوها، taxonomyهای عمومیِ دارای محتوا، `robots.txt` و مسیرهای sitemap را تا سقف ۵۰۰۰ پست/۱۰٬۰۰۰ URL بررسی می‌کند. خروجی ساختاریافته شامل SEO meta، هدینگ‌ها، schema، لینک‌های داخلی/خارجی و تصویرهاست؛ HTML کامل یا دادهٔ فرم‌ها را ذخیره نمی‌کند. درخواست HTML صفحات عمومی بدون cookie مدیر اجرا می‌شود، لینک‌های پیشخوان/ورود و لینک‌های queryدار از نمونه‌های crawl حذف می‌شوند و redirect دنبال نمی‌شود. درخواست داخلی ساخت manifest همچنان با نشست مدیر و nonce انجام می‌شود. اجرا ممکن است برای سایت بزرگ چند دقیقه یا بیشتر طول بکشد؛ تعداد پیشرفت نمایش داده می‌شود.</p>
		<p><button type="button" class="button" id="sa-audit-crawl-start">شروع ممیزی کامل صفحات عمومی</button> <button type="button" class="button" id="sa-audit-crawl-cancel" disabled>توقف</button></p>
		<div id="sa-audit-crawl-status" role="status" aria-live="polite"></div>
		<hr>
		<p><strong>ارسال برای تحلیل:</strong> هر دو فایل JSON (فهرست محتوا و نمونهٔ HTML زنده) را در همین گفت‌وگو پیوست کنید. ابزار چیزی را به بیرون ارسال نمی‌کند؛ دانلود فقط روی رایانهٔ شما انجام می‌شود. پیش از ارسال، اگر صفحه‌ای دادهٔ عمومی دارد که نمی‌خواهید به اشتراک گذاشته شود، فایل را بازبینی و آن رکورد را حذف کنید.</p>
	</div>
	<?php
}

/**
 * Read only explicitly allowed public post meta and reject objects/resources.
 *
 * @param int $post_id Post ID.
 * @return array<string,mixed>
 */
function sa_audit_exporter_public_meta( $post_id ) {
	$out = array();
	foreach ( sa_audit_exporter_meta_allowlist() as $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( '' === $value || null === $value || false === $value ) {
			continue;
		}
		if ( is_scalar( $value ) ) {
			$out[ $key ] = (string) $value;
		} elseif ( is_array( $value ) ) {
			$out[ $key ] = array_values( array_filter( $value, 'is_scalar' ) );
		}
	}
	return $out;
}

/**
 * Count headings, approximate words, and links in stored post content.
 *
 * @param string $content Stored post content.
 * @return array<string,mixed>
 */
function sa_audit_exporter_content_summary( $content ) {
	$content = (string) $content;
	$heading_counts = array_fill_keys( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 0 );
	if ( preg_match_all( '/<h([1-6])\b/i', $content, $matches ) ) {
		foreach ( $matches[1] as $level ) {
			$key = 'h' . (int) $level;
			++$heading_counts[ $key ];
		}
	}

	$plain = wp_strip_all_tags( strip_shortcodes( $content ) );
	$plain = html_entity_decode( $plain, ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
	$words = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
	$links = array();
	if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', $content, $link_matches, PREG_SET_ORDER ) ) {
		foreach ( $link_matches as $match ) {
			$url = html_entity_decode( trim( $match[2] ), ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' );
			if ( '' === $url || preg_match( '/^(?:javascript|data|vbscript):/i', $url ) ) {
				continue;
			}
			$url = preg_replace( '/[?#].*$/', '', $url );
			$links[] = array(
				'url'    => esc_url_raw( $url ),
				'anchor' => trim( wp_strip_all_tags( $match[3] ) ),
			);
		}
	}

	return array(
		'approx_word_count' => is_array( $words ) ? count( $words ) : 0,
		'heading_counts'    => $heading_counts,
		'link_count'        => count( $links ),
		'links'             => $links,
	);
}

/**
 * Public, non-sensitive WordPress environment summary.
 *
 * @param array $post_types Public post type objects.
 * @return array<string,mixed>
 */
function sa_audit_exporter_site_summary( $post_types ) {
	$counts = array();
	$public_types = array();
	foreach ( $post_types as $type => $object ) {
		if ( 'attachment' === $type ) {
			continue;
		}
		$public_types[] = array(
			'slug'       => $type,
			'label'      => wp_strip_all_tags( $object->labels->name ),
			'has_archive'=> (bool) $object->has_archive,
		);
		$status_counts = wp_count_posts( $type );
		$counts[ $type ] = array(
			'published' => isset( $status_counts->publish ) ? (int) $status_counts->publish : 0,
		);
	}

	$theme = wp_get_theme();
	return array(
		'home_url'            => home_url( '/' ),
		'site_name'           => wp_strip_all_tags( get_bloginfo( 'name' ) ),
		'wordpress_version'   => get_bloginfo( 'version' ),
		'php_version'         => PHP_VERSION,
		'locale'              => get_locale(),
		'timezone'            => wp_timezone_string(),
		'active_theme'        => array(
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'template'=> $theme->get_template(),
		),
		'public_post_types'   => $public_types,
		'counts_by_post_type' => $counts,
	);
}

/**
 * Collect and download a read-only JSON snapshot.
 */
function sa_audit_exporter_download() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'دسترسی مجاز نیست.', 'sa-site-audit-exporter' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'sa_audit_exporter_download', 'sa_audit_exporter_nonce' );

	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $post_types['attachment'] );
	$public_type_names = array_keys( $post_types );
	$items = array();
	$max_items = 5000;
	$truncated = false;

	foreach ( $public_type_names as $post_type ) {
		$remaining = $max_items - count( $items );
		if ( $remaining <= 0 ) {
			$probe = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			if ( $probe ) {
				$truncated = true;
				break;
			}
			continue;
		}
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $remaining + 1,
				'orderby'                => array( 'post_type' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);
		if ( count( $posts ) > $remaining ) {
			$truncated = true;
			$posts = array_slice( $posts, 0, $remaining );
		}

		foreach ( $posts as $post ) {
			$content = (string) $post->post_content;
			$taxonomies = array();
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy => $taxonomy_object ) {
				if ( empty( $taxonomy_object->public ) ) {
					continue;
				}
				$terms = get_the_terms( $post, $taxonomy );
				if ( is_array( $terms ) ) {
					$taxonomies[ $taxonomy ] = array_map(
						function ( $term ) {
							$url = get_term_link( $term );
							return array(
								'name' => $term->name,
								'slug' => $term->slug,
								'url'  => is_wp_error( $url ) ? '' : $url,
							);
						},
						$terms
					);
				}
			}

			$items[] = array(
				'id'              => (int) $post->ID,
				'post_type'       => $post_type,
				'post_status'     => 'publish',
				'title'           => wp_strip_all_tags( get_the_title( $post ) ),
				'slug'            => $post->post_name,
				'url'             => get_permalink( $post ),
				'published_gmt'   => $post->post_date_gmt,
				'modified_gmt'    => $post->post_modified_gmt,
				'excerpt'         => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'content'         => $content,
				'content_summary' => sa_audit_exporter_content_summary( $content ),
				'taxonomies'      => $taxonomies,
				'selected_meta'   => sa_audit_exporter_public_meta( $post->ID ),
			);
		}
	}

	$payload = array(
		'export_format' => 'sa-site-audit-v2',
		'generated_utc' => gmdate( 'c' ),
		'notice'        => 'Published public content only. No drafts, private data, comments, users, credentials, arbitrary meta or analytics are included. Stored post content only; theme-rendered HTML is not captured.',
		'site'          => sa_audit_exporter_site_summary( $post_types ),
		'limits'        => array(
			'max_items'      => $max_items,
			'item_count'     => count( $items ),
			'truncated'      => $truncated,
			'content_source' => 'stored post_content; not rendered theme output',
		),
		'pages'         => $items,
	);

	$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	if ( false === $json ) {
		wp_die( esc_html__( 'ساخت JSON ناموفق بود. حافظه یا حجم محتوا را بررسی کنید.', 'sa-site-audit-exporter' ), '', array( 'response' => 500 ) );
	}

	$filename = 'sa-site-audit-' . gmdate( 'Y-m-d' ) . '.json';
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded by wp_json_encode.
	exit;
}
add_action( 'admin_post_sa_audit_exporter_download', 'sa_audit_exporter_download' );

/**
 * Enqueue the optional same-origin rendered-page sample crawler.
 * The browser contacts only pages on this WordPress site's own origin.
 *
 * @param string $hook Current admin page hook.
 */
function sa_audit_exporter_enqueue_crawler( $hook ) {
	if ( 'tools_page_sa-site-audit-exporter' !== $hook ) {
		return;
	}
	wp_enqueue_script(
		'sa-audit-exporter-crawler',
		plugins_url( 'assets/audit-crawler.js', __FILE__ ),
		array(),
		'1.2.0',
		true
	);
	wp_localize_script(
		'sa-audit-exporter-crawler',
		'saAuditCrawler',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sa_audit_exporter_crawl' ),
			'origin'  => untrailingslashit( home_url( '/' ) ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'sa_audit_exporter_enqueue_crawler' );

/**
 * Return a full same-site manifest of published public posts and public archives.
 * The manifest contains URLs and labels only; the browser crawler reads them later.
 */
function sa_audit_exporter_crawl_manifest() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'دسترسی مجاز نیست.' ), 403 );
	}
	check_ajax_referer( 'sa_audit_exporter_crawl', 'nonce' );

	$urls = array();
	$truncated = false;
	$max_posts = 5000;
	$max_urls = 10000;
	$published_count = 0;
	$add_url = static function ( $url, $type, $label ) use ( &$urls ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $home_host || $home_host !== $url_host ) {
			return;
		}
		$key = untrailingslashit( $url );
		$urls[ $key ] = array(
			'url'   => $url,
			'type'  => $type,
			'label' => wp_strip_all_tags( (string) $label ),
		);
	};

	$add_url( home_url( '/' ), 'front_page', get_bloginfo( 'name' ) );
	$add_url( home_url( '/robots.txt' ), 'robots', 'robots.txt' );
	foreach ( array( '/wp-sitemap.xml', '/sitemap_index.xml', '/attraction-sitemap.xml' ) as $sitemap_path ) {
		$add_url( home_url( $sitemap_path ), 'sitemap', ltrim( $sitemap_path, '/' ) );
	}

	$public_types = get_post_types( array( 'public' => true ), 'objects' );
	unset( $public_types['attachment'] );
	foreach ( $public_types as $post_type => $object ) {
		if ( ! empty( $object->has_archive ) ) {
			$archive = get_post_type_archive_link( $post_type );
			if ( $archive ) {
				$add_url( $archive, 'archive:' . $post_type, $object->labels->name );
			}
		}
		$remaining = $max_posts - $published_count;
		if ( $remaining <= 0 ) {
			$probe = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			if ( $probe ) {
				$truncated = true;
				break;
			}
			continue;
		}
		$posts = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $remaining + 1,
				'orderby'                => array( 'post_type' => 'ASC', 'ID' => 'ASC' ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( count( $posts ) > $remaining ) {
			$truncated = true;
			$posts = array_slice( $posts, 0, $remaining );
		}
		foreach ( $posts as $post ) {
			$add_url( get_permalink( $post ), $post_type, get_the_title( $post ) );
			++$published_count;
		}
	}

	$public_taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
	foreach ( $public_taxonomies as $taxonomy => $taxonomy_object ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 1001,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		if ( count( $terms ) > 1000 ) {
			$truncated = true;
			$terms = array_slice( $terms, 0, 1000 );
		}
		foreach ( $terms as $term ) {
			$term_url = get_term_link( $term );
			if ( ! is_wp_error( $term_url ) ) {
				$add_url( $term_url, 'taxonomy:' . $taxonomy, $term->name );
			}
		}
	}

	if ( count( $urls ) > $max_urls ) {
		$truncated = true;
		$urls = array_slice( $urls, 0, $max_urls, true );
	}

	wp_send_json_success(
		array(
			'origin'            => untrailingslashit( home_url( '/' ) ),
			'generated_utc'     => gmdate( 'c' ),
			'coverage_strategy' => 'front page + robots/sitemap endpoints + every published post in every public post type (excluding attachments) + public archives and non-empty public taxonomy terms',
			'limits'            => array(
				'max_published_posts' => $max_posts,
				'published_posts'     => $published_count,
				'max_urls'            => $max_urls,
				'url_count'           => count( $urls ),
				'truncated'           => $truncated,
			),
			'urls'              => array_values( $urls ),
		)
	);
}
add_action( 'wp_ajax_sa_audit_exporter_crawl_manifest', 'sa_audit_exporter_crawl_manifest' );
