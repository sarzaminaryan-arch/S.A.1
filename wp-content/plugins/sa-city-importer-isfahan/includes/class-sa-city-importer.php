<?php
/**
 * سرزمین آریان — درون‌ریز شهرستان‌ها (هستهٔ مشترک، نسخهٔ شهرستان).
 *
 * Reads data/{province}.json (key "counties", built by content-templates/tools/build_city_import_package.py)
 * and creates or updates DRAFT posts of the `city` CPT (URL /city/{slug}/) with exactly the meta keys the
 * sarzaminaryan-child theme reads:
 *   sa_city_slug, sa_city_population, sa_city_latitude, sa_city_longitude,
 *   sa_access_air, sa_access_rail, sa_access_road, sa_google_map_url,
 *   sa_city_schema_type, sa_schema_sameas, sa_schema_contained_in,
 *   sa_seo_title, sa_seo_description, sa_focus_keyword, sa_og_title, sa_og_description,
 *   sa_faq (JSON [{q,a}]), sa_sources (text lines "title | org | url | date", private notes after ---),
 *   province_tax + travel_season terms, sa_province_id relation to the province hub post.
 * Optional: Rank Math meta (rank_math_title, rank_math_description, rank_math_focus_keyword, …).
 *
 * Nothing is ever published by this plugin: every imported post stays a DRAFT.
 * Existing published posts are skipped unless explicitly allowed.
 * Packages with publish_status other than DRAFT ONLY are refused (editor gate).
 *
 * @package Sarzaminaryan_City_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SA_City_Province_Importer' ) ) :

	/**
	 * City (county) importer core.
	 */
	class SA_City_Province_Importer {

		const CORE_VERSION = '1.1.0';
		const CPT          = 'city';
		const CAP          = 'manage_options';
		const NONCE        = 'sa_city_import';

		/**
		 * Registered batches keyed by id.
		 *
		 * @var array
		 */
		private $batches = array();

		/**
		 * Singleton.
		 *
		 * @var SA_City_Province_Importer|null
		 */
		private static $instance = null;

		/**
		 * Instance.
		 *
		 * @return SA_City_Province_Importer
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
			add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'sa-city-import', array( $this, 'cli' ) );
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
			if ( ! $manifest || empty( $manifest['file'] ) ) {
				return;
			}
			$data              = $this->read_json( $dir . $manifest['file'] );
			$batch['dir']      = $dir;
			$batch['manifest'] = $manifest;
			$batch['label']    = isset( $manifest['label'] ) ? $manifest['label'] : $batch['id'];
			$batch['packages'] = ( $data && isset( $data['counties'] ) ) ? (array) $data['counties'] : array();
			$by_slug           = array();
			foreach ( $batch['packages'] as $pkg ) {
				if ( ! empty( $pkg['slug'] ) ) {
					$by_slug[ $pkg['slug'] ] = $pkg;
				}
			}
			$batch['packages_by_slug'] = $by_slug;
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
			if ( ! file_exists( $path ) ) {
				return null;
			}
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return is_array( $decoded ) ? $decoded : null;
		}

		/**
		 * Default import options.
		 *
		 * @return array
		 */
		private function default_options() {
			return array(
				'update_drafts'       => true,
				'overwrite_published' => false,
				'rank_math'           => true,
				'featured_images'     => true,
				'overwrite_featured'  => false,
			);
		}

		/**
		 * Find an existing city post by slug.
		 *
		 * @param string $slug Slug.
		 * @return WP_Post|null
		 */
		private function find_existing( $slug ) {
			$found = get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'name'           => $slug,
					'posts_per_page' => 1,
					'fields'         => 'objects',
					'no_found_rows'  => true,
				)
			);
			if ( $found ) {
				return $found[0];
			}
			// Fall back to the primary-key meta (theme contract: sa_city_slug).
			$found = get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 1,
					'fields'         => 'objects',
					'no_found_rows'  => true,
					'meta_key'       => 'sa_city_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $slug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			return $found ? $found[0] : null;
		}

		/* ------------------------------------------------------------------ import */

		/**
		 * Import county packages.
		 *
		 * @param string $batch_id Batch id.
		 * @param array  $slugs    County slugs (empty = all of the batch).
		 * @param array  $opts     Options.
		 * @return array
		 */
		public function import( $batch_id, $slugs = array(), $opts = array() ) {
			$opts    = wp_parse_args( $opts, $this->default_options() );
			$results = array();
			if ( ! isset( $this->batches[ $batch_id ] ) ) {
				return array( '_error' => 'دسته‌ی ناشناخته: ' . $batch_id );
			}
			$batch = $this->batches[ $batch_id ];
			if ( ! $slugs ) {
				$slugs = array_keys( $batch['packages_by_slug'] );
			}
			if ( ! post_type_exists( self::CPT ) ) {
				return array( '_error' => 'نوع نوشته‌ی «شهر» ثبت نشده است — قالب فرزند سرزمین آریان باید فعال باشد.' );
			}
			if ( empty( $batch['packages_by_slug'] ) ) {
				return array( '_error' => 'بسته‌ی داده‌ی شهرستان‌ها خالی است.' );
			}
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			foreach ( $slugs as $slug ) {
				$slug = sanitize_title( $slug );
				if ( ! isset( $batch['packages_by_slug'][ $slug ] ) ) {
					$results[ $slug ] = array( 'action' => 'error', 'message' => 'بسته‌ی داده پیدا نشد' );
					continue;
				}
				$results[ $slug ] = $this->import_package( $batch['packages_by_slug'][ $slug ], $opts, $batch );
			}
			return $results;
		}

		/**
		 * Import one county package (always into DRAFT).
		 *
		 * @param array $pkg   Package.
		 * @param array $opts  Options.
		 * @param array $batch Batch.
		 * @return array
		 */
		public function import_package( $pkg, $opts, $batch ) {
			$slug     = sanitize_title( $pkg['slug'] );
			$existing = $this->find_existing( $slug );

			// Editor gate: only DRAFT ONLY packages may be imported.
			if ( isset( $pkg['publish_status'] ) && 'DRAFT ONLY' !== $pkg['publish_status'] ) {
				return array(
					'action'  => 'skipped',
					'post_id' => $existing ? (int) $existing->ID : 0,
					'message' => 'وضعیت بسته «' . sanitize_text_field( (string) $pkg['publish_status'] ) . '» است — فقط بسته‌های DRAFT ONLY وارد می‌شوند.',
				);
			}
			if ( $existing && 'publish' === $existing->post_status && empty( $opts['overwrite_published'] ) ) {
				return array(
					'action'  => 'skipped',
					'post_id' => (int) $existing->ID,
					'message' => 'منتشر شده — بدون تغییر (گزینه‌ی بازنویسی نوشته‌های منتشرشده خاموش است)',
				);
			}
			if ( $existing && empty( $opts['update_drafts'] ) ) {
				return array(
					'action'  => 'skipped',
					'post_id' => (int) $existing->ID,
					'message' => 'پیش‌نویس موجود — گزینه‌ی به‌روزرسانی خاموش است',
				);
			}

			$postarr = array(
				'post_type'      => self::CPT,
				'post_title'     => (string) $pkg['post']['title'],
				'post_name'      => $slug,
				'post_status'    => $existing ? $existing->post_status : 'draft',
				'post_excerpt'   => (string) $pkg['post']['excerpt'],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_content'   => (string) $pkg['post']['content_html'],
			);
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

			// Entity fields + SEO — only when the package carries them.
			$this->write_meta( $post_id, isset( $pkg['meta'] ) ? $pkg['meta'] : array() );
			$this->write_meta( $post_id, isset( $pkg['seo'] ) ? $pkg['seo'] : array() );

			$faq   = isset( $pkg['faq'] ) && is_array( $pkg['faq'] ) ? $pkg['faq'] : array();
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

			// Taxonomies + relation to the province hub post.
			$this->assign_terms( $post_id, $pkg, $batch );
			$this->link_province( $post_id, $pkg, $batch );

			// Import bookkeeping.
			update_post_meta( $post_id, '_sa_import_slug', $slug );
			update_post_meta( $post_id, '_sa_import_batch', $batch['id'] );
			update_post_meta( $post_id, '_sa_import_status', 'article' );
			update_post_meta( $post_id, '_sa_import_version', isset( $batch['manifest']['data_version'] ) ? $batch['manifest']['data_version'] : '' );
			update_post_meta( $post_id, '_sa_import_source_sha1', isset( $pkg['source_sha1'] ) ? (string) $pkg['source_sha1'] : '' );
			update_post_meta( $post_id, '_sa_import_time', current_time( 'mysql' ) );

			if ( function_exists( 'sa_sync_relations' ) ) {
				sa_sync_relations( $post_id, self::CPT );
			}
			if ( function_exists( 'sa_flush_relation_cache' ) ) {
				sa_flush_relation_cache( $post_id, self::CPT );
			}
			clean_post_cache( $post_id );

			// Featured image (bundled webp) — uploaded together with the page.
			$img = $this->set_featured_image( $post_id, $slug, $pkg, $batch, $opts );
			update_post_meta( $post_id, '_sa_import_featured', $img );

			$result = array(
				'action'  => $existing ? 'updated' : 'created',
				'post_id' => (int) $post_id,
				'status'  => get_post_status( $post_id ),
				'message' => $existing ? 'پیش‌نویس شهرستان به‌روزرسانی شد' : 'پیش‌نویس شهرستان ساخته شد',
				'image'   => $img,
			);
			if ( function_exists( 'sa_gate_missing' ) ) {
				$result['gate_missing']  = (array) sa_gate_missing( $post_id, self::CPT, null );
				$result['gate_warnings'] = function_exists( 'sa_gate_warnings' ) ? (array) sa_gate_warnings( $post_id, self::CPT, null ) : array();
			}
			return $result;
		}

		/**
		 * Set the featured image for a county post from the plugin's bundled webp.
		 *
		 * Looks for {plugin}/assets/counties/{slug}.webp, sideloads it into the
		 * media library and sets it as the post thumbnail. Idempotent: re-imports
		 * keep the existing thumbnail unless the bundled file changed (sha1 check)
		 * or the overwrite option is on.
		 *
		 * @param int    $post_id Post ID.
		 * @param string $slug    County slug.
		 * @param array  $pkg     Package.
		 * @param array  $batch   Batch.
		 * @param array  $opts    Options.
		 * @return string set|kept|refreshed|none|off|error:...
		 */
		private function set_featured_image( $post_id, $slug, $pkg, $batch, $opts ) {
			if ( empty( $opts['featured_images'] ) ) {
				return 'off';
			}
			$file = dirname( $batch['dir'] ) . '/assets/counties/' . $slug . '.webp';
			if ( ! file_exists( $file ) ) {
				return 'none';
			}
			$bits = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $bits ) {
				return 'error:read';
			}
			$sha1 = sha1( $bits );
			$thumb_id = (int) get_post_thumbnail_id( $post_id );
			if ( $thumb_id && empty( $opts['overwrite_featured'] ) ) {
				if ( get_post_meta( $thumb_id, '_sa_featured_sha1', true ) === $sha1 ) {
					return 'kept';
				}
				if ( get_post_meta( $post_id, '_sa_import_featured_sha1', true ) === $sha1 ) {
					return 'kept';
				}
			}
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			$title = isset( $pkg['title'] ) ? (string) $pkg['title'] : $slug;
			$alt   = isset( $pkg['seo']['sa_focus_keyword'] ) && '' !== (string) $pkg['seo']['sa_focus_keyword'] ? (string) $pkg['seo']['sa_focus_keyword'] : $title;
			$upload = wp_upload_bits( $slug . '.webp', null, $bits );
			if ( ! empty( $upload['error'] ) ) {
				return 'error:upload';
			}
			$filetype = wp_check_filetype( $upload['file'] );
			$att_id   = wp_insert_attachment(
				array(
					'post_mime_type' => empty( $filetype['type'] ) ? 'image/webp' : $filetype['type'],
					'post_title'     => sanitize_text_field( $title ),
					'post_content'   => '',
					'post_excerpt'   => sanitize_text_field( $alt ),
					'post_status'    => 'inherit',
				),
				$upload['file'],
				$post_id
			);
			if ( is_wp_error( $att_id ) || ! $att_id ) {
				return 'error:attachment';
			}
			wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $upload['file'] ) );
			update_post_meta( $att_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
			update_post_meta( $att_id, '_sa_featured_sha1', $sha1 );
			update_post_meta( $att_id, '_sa_featured_source', $batch['id'] . '/assets/counties/' . $slug . '.webp' );
			set_post_thumbnail( $post_id, (int) $att_id );
			update_post_meta( $post_id, '_sa_import_featured_sha1', $sha1 );
			return $thumb_id ? 'refreshed' : 'set';
		}

		/**
		 * Multi-line text without tags; keeps percent-encoded URL octets.
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
				$value = ( 'sa_seo_description' === $key || 'sa_og_description' === $key || 'sa_access_air' === $key || 'sa_access_rail' === $key || 'sa_access_road' === $key )
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
		 * province_tax (shared province term) + travel_season.
		 *
		 * @param int    $post_id Post.
		 * @param array  $pkg     Package.
		 * @param array  $batch   Batch.
		 */
		private function assign_terms( $post_id, $pkg, $batch ) {
			if ( taxonomy_exists( 'province_tax' ) ) {
				$prov = isset( $pkg['province'] ) ? (array) $pkg['province'] : array();
				if ( empty( $prov ) && ! empty( $batch['manifest']['province'] ) ) {
					$prov = (array) $batch['manifest']['province'];
				}
				$pslug = isset( $prov['slug'] ) ? $prov['slug'] : '';
				$pname = isset( $prov['term_name'] ) && '' !== $prov['term_name'] ? $prov['term_name'] : ( isset( $prov['name_fa'] ) ? $prov['name_fa'] : '' );
				if ( $pslug ) {
					$term = get_term_by( 'slug', $pslug, 'province_tax' );
					if ( ! $term && $pname ) {
						$ins = wp_insert_term( $pname, 'province_tax', array( 'slug' => $pslug ) );
						if ( ! is_wp_error( $ins ) ) {
							$term = get_term( (int) $ins['term_id'], 'province_tax' );
						}
					}
					if ( $term && ! is_wp_error( $term ) ) {
						wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'province_tax', false );
					}
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
				$ids   = array();
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
		 * Link the city draft to its province hub post (theme relation key: sa_province_id).
		 *
		 * @param int    $post_id Post.
		 * @param array  $pkg     Package.
		 * @param array  $batch   Batch.
		 */
		private function link_province( $post_id, $pkg, $batch ) {
			$prov = isset( $pkg['province'] ) ? (array) $pkg['province'] : array();
			if ( empty( $prov ) && ! empty( $batch['manifest']['province'] ) ) {
				$prov = (array) $batch['manifest']['province'];
			}
			$pslug = isset( $prov['slug'] ) ? $prov['slug'] : '';
			if ( ! $pslug || ! post_type_exists( 'province' ) ) {
				return;
			}
			$hub = get_posts(
				array(
					'post_type'      => 'province',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => '_sa_import_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $pslug, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( ! $hub ) {
				// Fall back to the province importer's term mirror (sa_province_post_id on province_tax).
				$term = get_term_by( 'slug', $pslug, 'province_tax' );
				if ( $term && ! is_wp_error( $term ) ) {
					$pid = (int) get_term_meta( $term->term_id, 'sa_province_post_id', true );
					if ( $pid ) {
						update_post_meta( $post_id, 'sa_province_id', $pid );
					}
				}
				return;
			}
			update_post_meta( $post_id, 'sa_province_id', (int) $hub[0] );
		}

		/* ------------------------------------------------------------------ admin */

		/**
		 * Menu: top-level «درون‌ریزی شهرستان‌ها».
		 */
		public function admin_menu() {
			foreach ( $this->batches as $batch ) {
				add_menu_page(
					'درون‌ریزی پیش‌نویس شهرستان‌ها — ' . $batch['label'],
					'درون‌ریزی شهرستان‌ها',
					self::CAP,
					'sa-city-importer-' . $batch['id'],
					array( $this, 'render_page' ),
					'dashicons-location',
					31
				);
			}
		}

		/**
		 * Dashboard widget: direct import button for site administrators.
		 */
		public function dashboard_widget() {
			if ( ! current_user_can( self::CAP ) || ! $this->batches ) {
				return;
			}
			$ready = 0;
			foreach ( $this->batches as $batch ) {
				$ready += count( $batch['packages_by_slug'] );
			}
			wp_add_dashboard_widget( 'sa_city_importer', 'سرزمین آریان — درون‌ریز شهرستان‌ها', array( $this, 'render_dashboard_widget' ), null, null, 'top' );
		}

		/**
		 * Render the dashboard widget.
		 */
		public function render_dashboard_widget() {
			$batch = null;
			foreach ( $this->batches as $b ) {
				$batch = $b;
				break;
			}
			$url = add_query_arg( 'page', 'sa-city-importer-' . $batch['id'], admin_url( 'admin.php' ) );
			if ( ! post_type_exists( self::CPT ) ) {
				echo '<p class="sa-pi-widget-warn"><strong>توجه:</strong> نوع نوشته‌ی «شهر» پیدا نشد؛ برای درون‌ریزی، قالب فرزند «سرزمین آریان» باید فعال باشد.</p>';
			} else {
				$total = 0;
				foreach ( $this->batches as $b ) {
					$total += count( $b['packages_by_slug'] );
				}
				echo '<p>' . $this->fa( $total ) . ' شهرستان با مقاله‌ی کامل آماده‌ی درون‌ریزی به‌صورت پیش‌نویس است. هیچ چیزی منتشر نمی‌شود؛ همه‌چیز پیش‌نویس می‌ماند.</p>';
			}
			echo '<p><a class="button button-primary button-hero" href="' . esc_url( $url ) . '">درون‌ریزی شهرستان‌ها</a></p>';
		}

		/**
		 * Warn when the theme is not active.
		 */
		public function theme_notice() {
			if ( ! current_user_can( self::CAP ) || post_type_exists( self::CPT ) || ! $this->batches ) {
				return;
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$mine   = false;
			foreach ( $this->batches as $batch ) {
				if ( $screen && false !== strpos( (string) $screen->id, 'sa-city-importer-' . $batch['id'] ) ) {
					$mine = true;
				}
			}
			if ( ! $mine && $screen && 'plugins' !== $screen->id ) {
				return;
			}
			echo '<div class="notice notice-warning"><p><strong>درون‌ریز شهرستان‌ها:</strong> نوع نوشته‌ی «شهر» پیدا نشد. قالب فرزند «سرزمین آریان» باید فعال باشد تا درون‌ریزی انجام شود.</p></div>';
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
				'rank_math'           => ! empty( $_POST['opt_rank_math'] ),
				'featured_images'     => ! empty( $_POST['opt_featured_images'] ),
				'overwrite_featured'  => ! empty( $_POST['opt_overwrite_featured'] ),
			);
			$results  = $slugs ? $this->import( $batch_id, $slugs, $opts ) : array( '_error' => 'هیچ شهرستانی انتخاب نشده بود.' );
			set_transient( 'sa_ci_results_' . get_current_user_id(), array( 'batch' => $batch_id, 'results' => $results ), 300 );
			wp_safe_redirect( add_query_arg( 'done', '1', add_query_arg( 'page', 'sa-city-importer-' . $batch_id, admin_url( 'admin.php' ) ) ) );
			exit;
		}

		/**
		 * Render the admin page.
		 */
		public function render_page() {
			if ( ! current_user_can( self::CAP ) ) {
				return;
			}
			$batch_id = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$batch_id = preg_replace( '/^sa-city-importer-/', '', $batch_id );
			if ( ! isset( $this->batches[ $batch_id ] ) ) {
				echo '<div class="wrap"><h1>درون‌ریزی شهرستان‌ها</h1><p>دسته‌ای ثبت نشده است.</p></div>';
				return;
			}
			$batch = $this->batches[ $batch_id ];
			$done  = isset( $_GET['done'] ) ? (int) $_GET['done'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="wrap"><h1>' . esc_html( $batch['label'] ) . '</h1>';
			echo '<p>درون‌ریزی مقاله‌های کامل شهرستان‌ها به‌صورت <strong>پیش‌نویس</strong> (هیچ‌چیز منتشر نمی‌شود). نسخه‌ی داده: ' . esc_html( isset( $batch['manifest']['data_version'] ) ? $batch['manifest']['data_version'] : '—' ) . ' · ساخته: ' . esc_html( isset( $batch['manifest']['built'] ) ? $batch['manifest']['built'] : '—' ) . '</p>';
			if ( ! post_type_exists( self::CPT ) ) {
				echo '<div class="notice notice-error"><p>نوع نوشته‌ی «شهر» ثبت نشده است — قالب فرزند سرزمین آریان باید فعال باشد.</p></div></div>';
				return;
			}
			if ( $done ) {
				$data = get_transient( 'sa_ci_results_' . get_current_user_id() );
				if ( $data && isset( $data['results'] ) ) {
					$this->render_results( $data['results'] );
				}
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE ); ?>" />
				<input type="hidden" name="batch" value="<?php echo esc_attr( $batch_id ); ?>" />
				<p>
					<button type="button" class="button" onclick="jQuery('.sa-ci-county').prop('checked', true);">انتخاب همه</button>
					<button type="button" class="button" onclick="jQuery('.sa-ci-county').prop('checked', false);">لغو انتخاب</button>
				</p>
				<table class="widefat striped" style="max-width:860px">
					<thead><tr><th style="width:34px"></th><th><?php esc_html_e( 'شهرستان', 'sa-province-importer' ); ?></th><th>کلمات</th><th>پرسش‌ها</th><th>منابع</th><th>وضعیت</th></tr></thead>
					<tbody>
					<?php foreach ( $batch['manifest']['counties'] as $row ) : ?>
						<tr>
							<td><input type="checkbox" class="sa-ci-county" name="slugs[]" value="<?php echo esc_attr( $row['slug'] ); ?>" checked="checked" /></td>
							<td><strong><?php echo esc_html( $row['title'] ); ?></strong> <code><?php echo esc_html( $row['slug'] ); ?></code></td>
							<td><?php echo esc_html( $this->fa( $row['word_count'] ) ); ?></td>
							<td><?php echo esc_html( $this->fa( $row['faq'] ) ); ?></td>
							<td><?php echo esc_html( $this->fa( $row['sources_lines'] ) ); ?></td>
							<td><?php echo esc_html( 'DRAFT ONLY' === $row['publish_status'] ? 'پیش‌نویس (DRAFT ONLY)' : $row['publish_status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<label><input type="checkbox" name="opt_update_drafts" value="1" checked="checked" /> به‌روزرسانی پیش‌نویس‌های موجود</label><br />
					<label><input type="checkbox" name="opt_overwrite_published" value="1" /> بازنویسی نوشته‌های منتشرشده (پیش‌فرض: خاموش — منتشرشده‌ها دست نمی‌خورند)</label><br />
					<label><input type="checkbox" name="opt_rank_math" value="1" checked="checked" /> نوشتن فیلدهای Rank Math (اگر افزونه فعال باشد)</label><br />
					<label><input type="checkbox" name="opt_featured_images" value="1" checked="checked" /> آپلود تصویر شاخص وب‌پی همراه صفحه (از پوشهٔ assets/counties افزونه)</label><br />
					<label><input type="checkbox" name="opt_overwrite_featured" value="1" /> بازنویسی تصویر شاخص موجود (پیش‌فرض: خاموش — تصویر فعلی دست نمی‌خورد)</label>
				</p>
				<?php submit_button( 'درون‌ریزی پیش‌نویس شهرستان‌های انتخاب‌شده' ); ?>
			</form>
			<?php
			echo '</div>';
		}

		/**
		 * Render import results.
		 *
		 * @param array $data Results.
		 */
		private function render_results( $data ) {
			if ( isset( $data['_error'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $data['_error'] ) . '</p></div>';
				return;
			}
			$ok = 0;
			foreach ( (array) $data as $r ) {
				if ( isset( $r['action'] ) && in_array( $r['action'], array( 'created', 'updated' ), true ) ) {
					$ok++;
				}
			}
			echo '<div class="notice notice-success"><p>' . esc_html( $this->fa( $ok ) ) . ' شهرستان با موفقیت درون‌ریزی شد (همه پیش‌نویس).</p></div>';
			echo '<table class="widefat striped" style="max-width:860px"><thead><tr><th>شهرستان</th><th>کنش</th><th>شناسه</th><th>پیام</th></tr></thead><tbody>';
			foreach ( (array) $data as $slug => $r ) {
				$cls = ( isset( $r['action'] ) && 'error' === $r['action'] ) ? 'notice-error' : '';
				echo '<tr class="' . esc_attr( $cls ) . '"><td><code>' . esc_html( $slug ) . '</code></td><td>' . esc_html( isset( $r['action'] ) ? $r['action'] : '—' ) . '</td><td>' . esc_html( isset( $r['post_id'] ) ? $this->fa( $r['post_id'] ) : '—' ) . '</td><td>' . esc_html( isset( $r['message'] ) ? $r['message'] : '' ) . '</td></tr>';
			}
			echo '</tbody></table>';
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

		/* ------------------------------------------------------------------ cli */

		/**
		 * WP-CLI: wp sa-city-import <batch> [--slugs=…] [--no-rank-math] [--overwrite-published]
		 *
		 * @param array $args       Positional.
		 * @param array $assoc_args Associative.
		 */
		public function cli( $args, $assoc_args ) {
			$batch_id = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
			if ( ! $batch_id || ! isset( $this->batches[ $batch_id ] ) ) {
				WP_CLI::error( 'دسته‌ی نامعتبر. دسته‌های ثبت‌شده: ' . implode( ', ', array_keys( $this->batches ) ) );
			}
			$opts = array(
				'update_drafts'       => true,
				'overwrite_published' => ! empty( $assoc_args['overwrite-published'] ),
				'rank_math'           => empty( $assoc_args['no-rank-math'] ),
			);
			$slugs = array();
			if ( ! empty( $assoc_args['slugs'] ) ) {
				$slugs = array_map( 'trim', explode( ',', (string) $assoc_args['slugs'] ) );
			}
			$results = $this->import( $batch_id, $slugs, $opts );
			if ( isset( $results['_error'] ) ) {
				WP_CLI::error( $results['_error'] );
			}
			foreach ( $results as $slug => $r ) {
				WP_CLI::log( sprintf( '%-16s %-8s %s', $slug, isset( $r['action'] ) ? $r['action'] : '?', isset( $r['message'] ) ? $r['message'] : '' ) );
			}
			WP_CLI::success( 'درون‌ریزی پایان یافت (همه پیش‌نویس).' );
		}
	}

endif;
