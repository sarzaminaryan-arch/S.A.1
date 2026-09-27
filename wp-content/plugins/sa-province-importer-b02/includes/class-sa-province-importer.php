<?php
/**
 * Shared importer core (identical file in every batch plugin; guarded by class_exists).
 *
 * Reads data/{slug}.json packages built by content-templates/tools/build_import_package.py and creates or
 * updates DRAFT posts of the `province` CPT with exactly the meta keys the sarzaminaryan-child theme reads:
 *   sa_province_population, sa_province_area, sa_province_latitude, sa_province_longitude, sa_province_climate,
 *   sa_seo_title, sa_seo_description, sa_focus_keyword, sa_og_title, sa_og_description,
 *   sa_faq (JSON [{q,a}]), sa_sources (text lines "title | org | url | date", private notes after ---),
 *   province_tax + travel_season terms, featured image (_thumbnail_id) with _wp_attachment_image_alt.
 * Optional: Rank Math meta (rank_math_title, rank_math_description, rank_math_focus_keyword, …).
 *
 * Nothing is ever published by this plugin. Existing published posts are skipped unless explicitly allowed.
 *
 * @package Sarzaminaryan_Province_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SA_Province_Importer' ) ) :

	/**
	 * Importer core.
	 */
	class SA_Province_Importer {

		const CORE_VERSION = '1.0.0';
		const CPT          = 'province';
		const PAGE_SLUG    = 'sa-province-importer';
		const CAP          = 'manage_options';
		const NONCE        = 'sa_province_import';

		/**
		 * Registered batches keyed by id.
		 *
		 * @var array
		 */
		private $batches = array();

		/**
		 * Singleton.
		 *
		 * @var SA_Province_Importer|null
		 */
		private static $instance = null;

		/**
		 * Instance.
		 *
		 * @return SA_Province_Importer
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Hooks (registered once).
		 */
		private function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
			add_action( 'admin_post_' . self::NONCE, array( $this, 'handle_post' ) );
			add_action( 'admin_notices', array( $this, 'theme_notice' ) );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'sa-province', array( $this, 'cli' ) );
			}
		}

		/* ------------------------------------------------------------------ batches */

		/**
		 * Register a data batch.
		 *
		 * @param array $batch id, dir, plugin_file, version.
		 */
		public function register_batch( $batch ) {
			$dir      = trailingslashit( $batch['dir'] );
			$manifest = $this->read_json( $dir . 'manifest.json' );
			if ( ! $manifest ) {
				return;
			}
			$batch['dir']      = $dir;
			$batch['manifest'] = $manifest;
			$batch['label']    = isset( $manifest['label'] ) ? $manifest['label'] : $batch['id'];
			$this->batches[ $batch['id'] ] = $batch;
			ksort( $this->batches );
		}

		/**
		 * Registered batches.
		 *
		 * @return array
		 */
		public function batches() {
			return $this->batches;
		}

		/**
		 * Read + decode a JSON file.
		 *
		 * @param string $path File.
		 * @return array|null
		 */
		private function read_json( $path ) {
			if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
				return null;
			}
			$data = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return is_array( $data ) ? $data : null;
		}

		/**
		 * Load one province package.
		 *
		 * @param array  $batch Batch.
		 * @param string $slug  Province slug.
		 * @return array|null
		 */
		private function load_package( $batch, $slug ) {
			$slug = sanitize_title( $slug );
			if ( '' === $slug ) {
				return null;
			}
			return $this->read_json( $batch['dir'] . $slug . '.json' );
		}

		/* ------------------------------------------------------------------ lookup */

		/**
		 * Existing province post for a slug (any status).
		 *
		 * @param string $slug Slug.
		 * @return WP_Post|null
		 */
		public function find_existing( $slug ) {
			$posts = get_posts(
				array(
					'post_type'      => self::CPT,
					'name'           => $slug,
					'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
					'posts_per_page' => 1,
					'no_found_rows'  => true,
				)
			);
			if ( $posts ) {
				return $posts[0];
			}
			$posts = get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'meta_key'       => '_sa_import_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			return $posts ? $posts[0] : null;
		}

		/* ------------------------------------------------------------------ import */

		/**
		 * Default options.
		 *
		 * @return array
		 */
		public function default_options() {
			return array(
				'update_drafts'       => true,
				'overwrite_published' => false,
				'images'              => true,
				'rank_math'           => defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ),
				'stubs'               => false,
			);
		}

		/**
		 * Import a list of slugs from a batch.
		 *
		 * @param string $batch_id Batch id.
		 * @param array  $slugs    Slugs (empty = all in batch).
		 * @param array  $opts     Options (see default_options()).
		 * @return array Results keyed by slug.
		 */
		public function import( $batch_id, $slugs = array(), $opts = array() ) {
			$opts    = wp_parse_args( $opts, $this->default_options() );
			$results = array();
			if ( ! isset( $this->batches[ $batch_id ] ) ) {
				return array( '_error' => 'دسته‌ی ناشناخته: ' . $batch_id );
			}
			$batch = $this->batches[ $batch_id ];
			if ( ! $slugs ) {
				foreach ( $batch['manifest']['provinces'] as $row ) {
					$slugs[] = $row['slug'];
				}
			}
			if ( ! post_type_exists( self::CPT ) ) {
				return array( '_error' => 'نوع نوشته‌ی «province» ثبت نشده است — قالب فرزند سرزمین آریان باید فعال باشد.' );
			}
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			foreach ( $slugs as $slug ) {
				$slug = sanitize_title( $slug );
				$pkg  = $this->load_package( $batch, $slug );
				if ( ! $pkg ) {
					$results[ $slug ] = array( 'action' => 'error', 'message' => 'بسته‌ی داده پیدا نشد' );
					continue;
				}
				$results[ $slug ] = $this->import_package( $pkg, $opts, $batch );
			}
			return $results;
		}

		/**
		 * Import one package.
		 *
		 * @param array $pkg   Package.
		 * @param array $opts  Options.
		 * @param array $batch Batch.
		 * @return array
		 */
		public function import_package( $pkg, $opts, $batch ) {
			$slug     = sanitize_title( $pkg['slug'] );
			$existing = $this->find_existing( $slug );
			$is_stub  = ( 'stub' === $pkg['status'] );

			if ( $existing && 'publish' === $existing->post_status && empty( $opts['overwrite_published'] ) ) {
				return array(
					'action'  => 'skipped',
					'post_id' => $existing->ID,
					'message' => 'منتشر شده — بدون تغییر (گزینه‌ی بازنویسی نوشته‌های منتشرشده خاموش است)',
				);
			}
			if ( $existing && empty( $opts['update_drafts'] ) ) {
				return array(
					'action'  => 'skipped',
					'post_id' => $existing->ID,
					'message' => 'پیش‌نویس موجود — گزینه‌ی به‌روزرسانی خاموش است',
				);
			}
			if ( $is_stub && ! $existing && empty( $opts['stubs'] ) ) {
				return array(
					'action'  => 'skipped',
					'message' => 'مقاله هنوز نوشته نشده — ساخت پیش‌نویس خالی خاموش است',
				);
			}

			$postarr = array(
				'post_type'      => self::CPT,
				'post_title'     => $pkg['post']['title'],
				'post_name'      => $slug,
				'post_status'    => $existing ? $existing->post_status : 'draft',
				'post_excerpt'   => (string) $pkg['post']['excerpt'],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);
			if ( ! $is_stub ) {
				$postarr['post_content'] = (string) $pkg['post']['content_html'];
			}
			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$post_id       = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$postarr['post_author'] = get_current_user_id();
				$post_id                = wp_insert_post( wp_slash( $postarr ), true );
			}
			if ( is_wp_error( $post_id ) ) {
				return array( 'action' => 'error', 'message' => $post_id->get_error_message() );
			}

			// Entity fields (Level 1) + SEO (Level 5) — only when the package carries them.
			if ( ! $is_stub ) {
				$this->write_meta( $post_id, isset( $pkg['meta'] ) ? $pkg['meta'] : array() );
				$this->write_meta( $post_id, isset( $pkg['seo'] ) ? $pkg['seo'] : array() );

				$faq = isset( $pkg['faq'] ) && is_array( $pkg['faq'] ) ? $pkg['faq'] : array();
				$clean = array();
				foreach ( $faq as $row ) {
					$q = isset( $row['q'] ) ? sanitize_text_field( $row['q'] ) : '';
					$a = isset( $row['a'] ) ? sanitize_textarea_field( $row['a'] ) : '';
					if ( '' !== $q && '' !== $a ) {
						$clean[] = array( 'q' => $q, 'a' => $a );
					}
				}
				if ( $clean ) {
					update_post_meta( $post_id, 'sa_faq', wp_slash( wp_json_encode( $clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
				}
				if ( ! empty( $pkg['sources'] ) ) {
					update_post_meta( $post_id, 'sa_sources', wp_slash( $this->clean_multiline( $pkg['sources'] ) ) );
				}
				if ( ! empty( $pkg['secondary_keywords'] ) ) {
					update_post_meta( $post_id, '_sa_secondary_keywords', wp_slash( wp_json_encode( array_map( 'sanitize_text_field', (array) $pkg['secondary_keywords'] ), JSON_UNESCAPED_UNICODE ) ) );
				}
				if ( ! empty( $opts['rank_math'] ) ) {
					$this->write_rank_math( $post_id, $pkg );
				}
			}

			// Taxonomies.
			$this->assign_terms( $post_id, $pkg, $slug );

			// Featured image.
			$image = array( 'image' => 'none' );
			if ( ! empty( $opts['images'] ) ) {
				$image = $this->import_image( $pkg, $post_id, $batch );
			}

			// Import bookkeeping.
			update_post_meta( $post_id, '_sa_import_slug', $slug );
			update_post_meta( $post_id, '_sa_import_batch', $batch['id'] );
			update_post_meta( $post_id, '_sa_import_status', $pkg['status'] );
			update_post_meta( $post_id, '_sa_import_version', isset( $batch['manifest']['data_version'] ) ? $batch['manifest']['data_version'] : '' );
			update_post_meta( $post_id, '_sa_import_source_sha1', isset( $pkg['source_sha1'] ) ? (string) $pkg['source_sha1'] : '' );
			update_post_meta( $post_id, '_sa_import_time', current_time( 'mysql' ) );

			// Let the theme rebuild its relation caches if it offers to.
			if ( function_exists( 'sa_sync_relations' ) ) {
				sa_sync_relations( $post_id, self::CPT );
			}
			if ( function_exists( 'sa_flush_relation_cache' ) ) {
				sa_flush_relation_cache( $post_id, self::CPT );
			}
			clean_post_cache( $post_id );

			$result = array(
				'action'  => $existing ? 'updated' : 'created',
				'post_id' => $post_id,
				'status'  => get_post_status( $post_id ),
				'message' => $existing ? 'پیش‌نویس به‌روزرسانی شد' : 'پیش‌نویس ساخته شد',
			);
			$result = array_merge( $result, $image );
			if ( function_exists( 'sa_gate_missing' ) ) {
				$result['gate_missing']  = (array) sa_gate_missing( $post_id, self::CPT, null );
				$result['gate_warnings'] = function_exists( 'sa_gate_warnings' ) ? (array) sa_gate_warnings( $post_id, self::CPT, null ) : array();
			}
			return $result;
		}

		/**
		 * Multi-line text without tags; unlike sanitize_textarea_field() it keeps percent-encoded URL octets.
		 *
		 * @param string $text Raw text.
		 * @return string
		 */
		private function clean_multiline( $text ) {
			$text = wp_check_invalid_utf8( (string) $text );
			$text = wp_strip_all_tags( $text, false );
			$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
			return trim( $text );
		}

		/**
		 * Write a key => value map as post meta (strings sanitised, empty values removed).
		 *
		 * @param int   $post_id Post.
		 * @param array $map     Meta.
		 */
		private function write_meta( $post_id, $map ) {
			foreach ( (array) $map as $key => $value ) {
				$key = sanitize_key( $key );
				if ( 0 !== strpos( $key, 'sa_' ) ) {
					continue;
				}
				$value = is_scalar( $value ) ? (string) $value : '';
				$value = ( 'sa_seo_description' === $key || 'sa_og_description' === $key || 'sa_province_climate' === $key )
					? sanitize_textarea_field( $value )
					: sanitize_text_field( $value );
				if ( '' === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, wp_slash( $value ) );
				}
			}
		}

		/**
		 * Rank Math fields (same values as the theme SEO box).
		 *
		 * @param int   $post_id Post.
		 * @param array $pkg     Package.
		 */
		private function write_rank_math( $post_id, $pkg ) {
			$seo = isset( $pkg['seo'] ) ? $pkg['seo'] : array();
			$map = array(
				'rank_math_title'                => isset( $seo['sa_seo_title'] ) ? $seo['sa_seo_title'] : '',
				'rank_math_description'          => isset( $seo['sa_seo_description'] ) ? $seo['sa_seo_description'] : '',
				'rank_math_focus_keyword'        => isset( $seo['sa_focus_keyword'] ) ? $seo['sa_focus_keyword'] : '',
				'rank_math_facebook_title'       => isset( $seo['sa_og_title'] ) ? $seo['sa_og_title'] : '',
				'rank_math_facebook_description' => isset( $seo['sa_og_description'] ) ? $seo['sa_og_description'] : '',
				'rank_math_twitter_use_facebook' => 'on',
			);
			foreach ( $map as $key => $value ) {
				$value = sanitize_textarea_field( (string) $value );
				if ( '' !== $value ) {
					update_post_meta( $post_id, $key, wp_slash( $value ) );
				}
			}
		}

		/**
		 * province_tax (mirror term of the hub post) + travel_season.
		 *
		 * @param int    $post_id Post.
		 * @param array  $pkg     Package.
		 * @param string $slug    Slug.
		 */
		private function assign_terms( $post_id, $pkg, $slug ) {
			if ( taxonomy_exists( 'province_tax' ) ) {
				$term = get_term_by( 'slug', $slug, 'province_tax' );
				if ( ! $term ) {
					$name = isset( $pkg['term_name'] ) && '' !== $pkg['term_name'] ? $pkg['term_name'] : $pkg['post']['title'];
					$ins  = wp_insert_term( $name, 'province_tax', array( 'slug' => $slug ) );
					if ( ! is_wp_error( $ins ) ) {
						$term = get_term( (int) $ins['term_id'], 'province_tax' );
					}
				}
				if ( $term && ! is_wp_error( $term ) ) {
					wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'province_tax', false );
					update_post_meta( $post_id, 'sa_province_term_id', (int) $term->term_id );
					update_term_meta( (int) $term->term_id, 'sa_province_post_id', (int) $post_id );
				}
			}
			$seasons = isset( $pkg['travel_season'] ) ? (array) $pkg['travel_season'] : array();
			if ( $seasons && taxonomy_exists( 'travel_season' ) ) {
				$names = array(
					'spring' => 'بهار',
					'summer' => 'تابستان',
					'autumn' => 'پاییز',
					'winter' => 'زمستان',
				);
				$ids = array();
				foreach ( $seasons as $s ) {
					$s = sanitize_title( $s );
					if ( ! isset( $names[ $s ] ) ) {
						continue;
					}
					$t = get_term_by( 'slug', $s, 'travel_season' );
					if ( ! $t ) {
						$ins = wp_insert_term( $names[ $s ], 'travel_season', array( 'slug' => $s ) );
						if ( ! is_wp_error( $ins ) ) {
							$t = get_term( (int) $ins['term_id'], 'travel_season' );
						}
					}
					if ( $t && ! is_wp_error( $t ) ) {
						$ids[] = (int) $t->term_id;
					}
				}
				if ( $ids ) {
					wp_set_object_terms( $post_id, $ids, 'travel_season', false );
				}
			}
		}

		/**
		 * Copy the bundled WEBP into the media library (once per slug + sha1) and set it as featured image.
		 *
		 * @param array $pkg     Package.
		 * @param int   $post_id Post.
		 * @param array $batch   Batch.
		 * @return array
		 */
		private function import_image( $pkg, $post_id, $batch ) {
			if ( empty( $pkg['image'] ) || empty( $pkg['image']['file'] ) ) {
				return array( 'image' => 'none' );
			}
			$img  = $pkg['image'];
			$path = $batch['dir'] . ltrim( str_replace( '..', '', $img['file'] ), '/' );
			if ( ! file_exists( $path ) ) {
				return array( 'image' => 'missing_file' );
			}
			$sha1   = isset( $img['sha1'] ) ? (string) $img['sha1'] : sha1_file( $path );
			$att_id = 0;
			$found  = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 5,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => '_sa_import_image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $pkg['slug'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			foreach ( $found as $id ) {
				$attached = get_attached_file( $id );
				if ( $attached && file_exists( $attached ) && get_post_meta( $id, '_sa_import_image_sha1', true ) === $sha1 ) {
					$att_id = (int) $id;
					break;
				}
			}
			if ( ! $att_id ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$bits   = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$upload = wp_upload_bits( sanitize_file_name( basename( $path ) ), null, $bits );
				if ( ! empty( $upload['error'] ) ) {
					return array( 'image' => 'upload_error', 'image_message' => $upload['error'] );
				}
				$att = array(
					'post_mime_type' => isset( $img['mime'] ) ? $img['mime'] : 'image/webp',
					'post_title'     => isset( $img['title'] ) ? sanitize_text_field( $img['title'] ) : $pkg['post']['title'],
					'post_excerpt'   => isset( $img['caption'] ) ? sanitize_textarea_field( $img['caption'] ) : '',
					'post_content'   => isset( $img['description'] ) ? sanitize_textarea_field( $img['description'] ) : '',
					'post_status'    => 'inherit',
				);
				$att_id = wp_insert_attachment( wp_slash( $att ), $upload['file'], $post_id, true );
				if ( is_wp_error( $att_id ) ) {
					return array( 'image' => 'attach_error', 'image_message' => $att_id->get_error_message() );
				}
				$meta = wp_generate_attachment_metadata( $att_id, $upload['file'] );
				if ( $meta ) {
					wp_update_attachment_metadata( $att_id, $meta );
				}
				update_post_meta( $att_id, '_sa_import_image', $pkg['slug'] );
				update_post_meta( $att_id, '_sa_import_image_sha1', $sha1 );
			}
			if ( ! empty( $img['alt'] ) ) {
				update_post_meta( $att_id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $img['alt'] ) ) );
			}
			set_post_thumbnail( $post_id, $att_id );
			return array( 'image' => 'attached', 'attachment_id' => $att_id );
		}

		/* ------------------------------------------------------------------ admin */

		/**
		 * Menu: under «استان‌ها» when the CPT exists, otherwise under Tools.
		 */
		public function admin_menu() {
			$title = 'درون‌ریزی پیش‌نویس استان‌ها';
			if ( post_type_exists( self::CPT ) ) {
				add_submenu_page( 'edit.php?post_type=' . self::CPT, $title, 'درون‌ریزی استان‌ها', self::CAP, self::PAGE_SLUG, array( $this, 'render_page' ) );
			} else {
				add_management_page( $title, 'درون‌ریزی استان‌ها', self::CAP, self::PAGE_SLUG, array( $this, 'render_page' ) );
			}
		}

		/**
		 * Warn when the theme is not active.
		 */
		public function theme_notice() {
			if ( ! current_user_can( self::CAP ) || post_type_exists( self::CPT ) ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && false === strpos( (string) $screen->id, self::PAGE_SLUG ) && 'plugins' !== $screen->id ) {
				return;
			}
			echo '<div class="notice notice-warning"><p><strong>درون‌ریز استان‌ها:</strong> نوع نوشته‌ی «استان» پیدا نشد. قالب فرزند «سرزمین آریان» (نسخه‌ی ۱.۰.۳ یا بالاتر) باید فعال باشد تا درون‌ریزی انجام شود.</p></div>';
		}

		/**
		 * Page URL.
		 *
		 * @return string
		 */
		private function page_url() {
			$base = post_type_exists( self::CPT ) ? admin_url( 'edit.php?post_type=' . self::CPT ) : admin_url( 'tools.php' );
			return add_query_arg( 'page', self::PAGE_SLUG, $base );
		}

		/**
		 * Persian digits.
		 *
		 * @param mixed $n Number/string.
		 * @return string
		 */
		private function fa( $n ) {
			if ( function_exists( 'sa_fa_digits' ) ) {
				return sa_fa_digits( $n );
			}
			return strtr( (string) $n, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		}

		/**
		 * Handle the form (admin-post.php).
		 */
		public function handle_post() {
			if ( ! current_user_can( self::CAP ) ) {
				wp_die( 'دسترسی ندارید.' );
			}
			check_admin_referer( self::NONCE );
			$batch_id = isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '';
			$slugs    = isset( $_POST['slugs'] ) ? array_map( 'sanitize_title', (array) wp_unslash( $_POST['slugs'] ) ) : array();
			$opts     = array(
				'update_drafts'       => ! empty( $_POST['opt_update_drafts'] ),
				'overwrite_published' => ! empty( $_POST['opt_overwrite_published'] ),
				'images'              => ! empty( $_POST['opt_images'] ),
				'rank_math'           => ! empty( $_POST['opt_rank_math'] ),
				'stubs'               => ! empty( $_POST['opt_stubs'] ),
			);
			$results = $slugs ? $this->import( $batch_id, $slugs, $opts ) : array( '_error' => 'هیچ استانی انتخاب نشده بود.' );
			set_transient( 'sa_pi_results_' . get_current_user_id(), array( 'batch' => $batch_id, 'results' => $results ), 300 );
			wp_safe_redirect( add_query_arg( 'done', '1', $this->page_url() ) );
			exit;
		}

		/**
		 * Admin page.
		 */
		public function render_page() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			$results = null;
			if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$results = get_transient( 'sa_pi_results_' . get_current_user_id() );
				delete_transient( 'sa_pi_results_' . get_current_user_id() );
			}
			$defaults = $this->default_options();
			echo '<div class="wrap" dir="rtl"><h1>درون‌ریزی پیش‌نویس استان‌ها</h1>';
			echo '<p>هر دسته، ده استان از فهرست ثابت <code>data/provinces.php</code> را پوشش می‌دهد. درون‌ریزی فقط <strong>پیش‌نویس</strong> می‌سازد یا پیش‌نویس موجود را به‌روز می‌کند؛ انتشار همیشه دستی و از دروازه‌ی انتشار قالب می‌گذرد. اجرای دوباره بی‌خطر است (بر اساس نامک، تکراری نمی‌سازد).</p>';

			if ( $results ) {
				$this->render_results( $results );
			}

			if ( ! $this->batches ) {
				echo '<div class="notice notice-error"><p>هیچ دسته‌ای ثبت نشده است (پوشه‌ی data/ افزونه خالی است).</p></div></div>';
				return;
			}

			foreach ( $this->batches as $batch ) {
				$m = $batch['manifest'];
				echo '<h2>' . esc_html( $batch['label'] ) . ' <small style="font-weight:normal">— نسخه‌ی داده ' . esc_html( $this->fa( isset( $m['data_version'] ) ? $m['data_version'] : '' ) ) . ' · ساخت ' . esc_html( $this->fa( isset( $m['built'] ) ? $m['built'] : '' ) ) . '</small></h2>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::NONCE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::NONCE ) . '">';
				echo '<input type="hidden" name="batch" value="' . esc_attr( $batch['id'] ) . '">';
				echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>';
				echo '<td class="check-column" style="padding:8px 10px"><input type="checkbox" title="انتخاب همه" onclick="var ck=this.checked;this.closest(\'table\').querySelectorAll(\'input[name=&quot;slugs[]&quot;]\').forEach(function(c){c.checked=ck;});"></td>';
				echo '<th>#</th><th>استان</th><th>نامک</th><th>بسته‌ی داده</th><th>تصویر شاخص</th><th>وضعیت در سایت</th><th>آخرین درون‌ریزی</th></tr></thead><tbody>';
				foreach ( $m['provinces'] as $row ) {
					$slug     = sanitize_title( $row['slug'] );
					$existing = post_type_exists( self::CPT ) ? $this->find_existing( $slug ) : null;
					$pkg_desc = 'article' === $row['status']
						? sprintf( 'مقاله‌ی کامل — %s واژه، %s پرسش، %s منبع لینک‌دار، نشان بررسی %s', $this->fa( number_format_i18n( (int) $row['word_count'] ) ), $this->fa( (int) $row['faq'] ), $this->fa( (int) $row['sources_lines'] ), $this->fa( (int) $row['markers']['review'] + (int) $row['markers']['source_needed'] ) )
						: 'هنوز مقاله ندارد (فقط عنوان + تصویر)';
					echo '<tr>';
					echo '<th class="check-column" style="padding:8px 10px"><input type="checkbox" name="slugs[]" value="' . esc_attr( $slug ) . '" ' . checked( 'article', $row['status'], false ) . '></th>';
					echo '<td>' . esc_html( $this->fa( (int) $row['order'] ) ) . '</td>';
					echo '<td><strong>' . esc_html( $row['name_fa'] ) . '</strong></td>';
					echo '<td><code>' . esc_html( $slug ) . '</code></td>';
					echo '<td>' . esc_html( $pkg_desc ) . '</td>';
					echo '<td>' . ( ! empty( $row['has_image'] ) ? '✅ همراه بسته' : '— ندارد' ) . '</td>';
					if ( $existing ) {
						$status_obj = get_post_status_object( $existing->post_status );
						echo '<td><a href="' . esc_url( get_edit_post_link( $existing->ID ) ) . '">#' . esc_html( $this->fa( $existing->ID ) ) . '</a> — ' . esc_html( $status_obj ? $status_obj->label : $existing->post_status ) . ( has_post_thumbnail( $existing->ID ) ? ' · تصویر دارد' : ' · بدون تصویر' ) . '</td>';
						$t = get_post_meta( $existing->ID, '_sa_import_time', true );
						echo '<td>' . esc_html( $t ? $this->fa( $t ) : '—' ) . '</td>';
					} else {
						echo '<td>— هنوز ساخته نشده</td><td>—</td>';
					}
					echo '</tr>';
				}
				echo '</tbody></table>';
				echo '<p style="margin-top:12px">';
				echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="opt_update_drafts" value="1" ' . checked( true, $defaults['update_drafts'], false ) . '> به‌روزرسانی پیش‌نویس‌های موجود (متن، فیلدها، FAQ، منابع، سئو از روی بسته‌ی جدید بازنویسی می‌شود)</label>';
				echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="opt_images" value="1" ' . checked( true, $defaults['images'], false ) . '> بارگذاری تصویر شاخص همراه بسته (با ALT، عنوان و زیرنویس؛ هر تصویر فقط یک بار وارد رسانه می‌شود)</label>';
				echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="opt_rank_math" value="1" ' . checked( true, $defaults['rank_math'], false ) . '> نوشتن فیلدهای Rank Math (عنوان سئو، توضیحات متا، کلمه‌ی کلیدی کانونی، OG)' . ( $defaults['rank_math'] ? ' — افزونه فعال است' : ' — افزونه فعال نیست' ) . '</label>';
				echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="opt_stubs" value="1"> ساخت پیش‌نویس خالی برای استان‌هایی که هنوز مقاله ندارند (فقط عنوان، نامک، طبقه‌بندی و تصویر شاخص)</label>';
				echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="opt_overwrite_published" value="1"> بازنویسی نوشته‌های <strong>منتشرشده</strong> (پیش‌فرض خاموش — با احتیاط)</label>';
				echo '</p>';
				submit_button( 'درون‌ریزی موارد انتخاب‌شده به‌صورت پیش‌نویس', 'primary', 'submit', false );
				echo '</form><hr>';
			}
			echo '<p class="description">پس از درون‌ریزی: هر پیش‌نویس را باز کنید، دروازه‌ی انتشار قالب (لینک داخلی ≥ ۲۰، FAQ ≥ ۱۰، منابع ≥ ۵، مختصات، تصویر شاخص) را ببینید و نشان‌های «نیازمند بررسی / منبع لازم» را پیش از انتشار برطرف کنید. WP-CLI: <code>wp sa-province import --batch=b01 --slugs=tehran,east-azerbaijan</code></p>';
			echo '</div>';
		}

		/**
		 * Results table after an import.
		 *
		 * @param array $data Transient payload.
		 */
		private function render_results( $data ) {
			$results = isset( $data['results'] ) ? $data['results'] : array();
			if ( isset( $results['_error'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $results['_error'] ) . '</p></div>';
				return;
			}
			echo '<div class="notice notice-success"><p><strong>نتیجه‌ی درون‌ریزی</strong> (دسته‌ی ' . esc_html( $this->fa( isset( $data['batch'] ) ? $data['batch'] : '' ) ) . ')</p>';
			echo '<table class="widefat" style="max-width:1100px;margin-bottom:10px"><thead><tr><th>نامک</th><th>نتیجه</th><th>نوشته</th><th>تصویر</th><th>دروازه‌ی انتشار (موارد کم)</th></tr></thead><tbody>';
			foreach ( $results as $slug => $r ) {
				$action = isset( $r['action'] ) ? $r['action'] : '';
				$labels = array(
					'created' => '✅ ساخته شد',
					'updated' => '🔄 به‌روزرسانی شد',
					'skipped' => '⏭ رد شد',
					'error'   => '❌ خطا',
				);
				echo '<tr><td><code>' . esc_html( $slug ) . '</code></td>';
				echo '<td>' . esc_html( ( isset( $labels[ $action ] ) ? $labels[ $action ] : $action ) . ( ! empty( $r['message'] ) ? ' — ' . $r['message'] : '' ) ) . '</td>';
				if ( ! empty( $r['post_id'] ) ) {
					echo '<td><a href="' . esc_url( get_edit_post_link( (int) $r['post_id'] ) ) . '">#' . esc_html( $this->fa( (int) $r['post_id'] ) ) . ' ویرایش</a> · <a href="' . esc_url( get_preview_post_link( (int) $r['post_id'] ) ) . '" target="_blank" rel="noopener">پیش‌نمایش</a></td>';
				} else {
					echo '<td>—</td>';
				}
				$img_labels = array(
					'attached'     => '✅ تصویر شاخص تنظیم شد',
					'none'         => '— تصویری همراه بسته نیست',
					'missing_file' => '❌ فایل تصویر در افزونه نیست',
					'upload_error' => '❌ خطای بارگذاری',
					'attach_error' => '❌ خطای ثبت رسانه',
				);
				$img = isset( $r['image'] ) ? $r['image'] : '';
				echo '<td>' . esc_html( ( isset( $img_labels[ $img ] ) ? $img_labels[ $img ] : $img ) . ( ! empty( $r['image_message'] ) ? ' — ' . $r['image_message'] : '' ) ) . '</td>';
				if ( isset( $r['gate_missing'] ) ) {
					$gm = $r['gate_missing'] ? implode( '؛ ', array_map( 'wp_strip_all_tags', $r['gate_missing'] ) ) : 'چیزی کم نیست (فقط نشان‌های بررسی)';
					echo '<td>' . esc_html( $gm ) . ( ! empty( $r['gate_warnings'] ) ? '<br><em>' . esc_html( implode( '؛ ', array_map( 'wp_strip_all_tags', $r['gate_warnings'] ) ) ) . '</em>' : '' ) . '</td>';
				} else {
					echo '<td>—</td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table></div>';
		}

		/* ------------------------------------------------------------------ WP-CLI */

		/**
		 * `wp sa-province import --batch=b01 [--slugs=a,b] [--stubs] [--overwrite-published] [--no-images] [--rank-math]`
		 * `wp sa-province list`
		 *
		 * @param array $args       Positional.
		 * @param array $assoc_args Flags.
		 */
		public function cli( $args, $assoc_args ) {
			$sub = isset( $args[0] ) ? $args[0] : 'list';
			if ( 'list' === $sub ) {
				foreach ( $this->batches as $b ) {
					WP_CLI::line( $b['id'] . ' — ' . $b['label'] );
					foreach ( $b['manifest']['provinces'] as $row ) {
						$ex = $this->find_existing( $row['slug'] );
						WP_CLI::line( sprintf( '  %-24s %-8s image=%s site=%s', $row['slug'], $row['status'], ! empty( $row['has_image'] ) ? 'yes' : 'no', $ex ? '#' . $ex->ID . '/' . $ex->post_status : '-' ) );
					}
				}
				return;
			}
			if ( 'import' !== $sub ) {
				WP_CLI::error( 'Unknown subcommand. Use: list | import' );
			}
			$batch_id = isset( $assoc_args['batch'] ) ? sanitize_key( $assoc_args['batch'] ) : '';
			if ( ! $batch_id && 1 === count( $this->batches ) ) {
				$batch_id = key( $this->batches );
			}
			$slugs = isset( $assoc_args['slugs'] ) ? array_filter( array_map( 'sanitize_title', explode( ',', $assoc_args['slugs'] ) ) ) : array();
			$opts  = array(
				'update_drafts'       => ! isset( $assoc_args['no-update'] ),
				'overwrite_published' => isset( $assoc_args['overwrite-published'] ),
				'images'              => ! isset( $assoc_args['no-images'] ),
				'rank_math'           => isset( $assoc_args['rank-math'] ) ? true : $this->default_options()['rank_math'],
				'stubs'               => isset( $assoc_args['stubs'] ),
			);
			$results = $this->import( $batch_id, $slugs, $opts );
			if ( isset( $results['_error'] ) ) {
				WP_CLI::error( $results['_error'] );
			}
			foreach ( $results as $slug => $r ) {
				$line = sprintf( '%-24s %-8s %s %s', $slug, $r['action'], ! empty( $r['post_id'] ) ? '#' . $r['post_id'] : '', isset( $r['message'] ) ? $r['message'] : '' );
				if ( isset( $r['image'] ) ) {
					$line .= ' image=' . $r['image'];
				}
				if ( ! empty( $r['gate_missing'] ) ) {
					$line .= ' gate: ' . implode( '; ', array_map( 'wp_strip_all_tags', $r['gate_missing'] ) );
				}
				WP_CLI::line( $line );
			}
			WP_CLI::success( 'done' );
		}
	}

endif;
