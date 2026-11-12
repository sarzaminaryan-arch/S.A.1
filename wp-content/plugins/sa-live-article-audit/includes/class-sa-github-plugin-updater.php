<?php
/**
 * به‌روزرسان گیت‌هاب افزونه‌های شخصی سرزمین آریان.
 *
 * همان الگوی به‌روزرسان قالب فرزند (`inc/github-updater.php`)، برای افزونه‌ها:
 * نسخهٔ تازه از GitHub Release خوانده می‌شود و در مسیر بومی وردپرس
 * (پیشخوان ← به‌روزرسانی‌ها / صفحهٔ افزونه‌ها) مثل یک آپدیت عادی ظاهر می‌شود.
 *
 * - مخزن خصوصی است؛ توکن از ثابت `SA_GITHUB_TOKEN` در wp-config.php یا از
 *   گزینهٔ ذخیره‌شدهٔ به‌روزرسان قالب خوانده می‌شود و هرگز داخل بسته نیست.
 * - دانلود دارایی خصوصی با هدر احراز هویت روی api.github.com انجام می‌شود و
 *   توکن به میزبان امضاشدهٔ دانلود فرستاده نمی‌شود.
 * - اگر دارایی Release چک‌سام SHA-256 داشته باشد، پیش از نصب راستی‌آزمایی می‌شود.
 *
 * این فایل بین افزونه‌های شخصی مشترک است؛ هر افزونه با config خودش یک نمونه
 * می‌سازد و تعریف کلاس با class_exists محافظت می‌شود.
 *
 * @package SA_GitHub_Plugin_Updater
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SA_GitHub_Plugin_Updater' ) ) :

	class SA_GitHub_Plugin_Updater {

		/** @var array<string,SA_GitHub_Plugin_Updater> نمونه‌ها به تفکیک slug. */
		private static $instances = array();

		/** @var array تنظیمات این افزونه. */
		private $config;

		/**
		 * ثبت یک افزونه برای به‌روزرسانی خودکار.
		 *
		 * @param array $config {plugin_file, slug, tag_prefix, version, name, owner, repository}
		 */
		public static function register( $config ) {
			$slug = isset( $config['slug'] ) ? (string) $config['slug'] : '';
			if ( '' === $slug || isset( self::$instances[ $slug ] ) ) {
				return;
			}
			self::$instances[ $slug ] = new self( $config );
		}

		/**
		 * @param array $config تنظیمات.
		 */
		private function __construct( $config ) {
			$this->config = array_merge(
				array(
					'owner'      => 'sarzaminaryan-arch',
					'repository' => 'S.A.1',
					'name'       => $config['slug'],
				),
				$config
			);
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_transient' ) );
			add_filter( 'plugins_api', array( $this, 'filter_plugin_information' ), 20, 3 );
			add_filter( 'upgrader_pre_download', array( $this, 'download_private_release' ), 20, 4 );
			add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );
		}

		/**
		 * کلید transient کش انتشار.
		 *
		 * @return string
		 */
		private function transient_key() {
			return 'sa_gh_rel_' . str_replace( '-', '_', $this->config['slug'] );
		}

		/**
		 * افزودن انتشار گیت‌هاب به دادهٔ بومی آپدیت افزونه‌ها.
		 *
		 * @param mixed $transient Transient آپدیت.
		 * @return mixed
		 */
		public function filter_update_transient( $transient ) {
			if ( ! is_object( $transient ) ) {
				return $transient;
			}
			$release = $this->get_latest_release();
			if ( is_wp_error( $release ) ) {
				return $transient;
			}
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$entry = (object) array(
				'id'            => 'github.com/' . $this->config['owner'] . '/' . $this->config['repository'] . '/' . $this->config['slug'],
				'slug'          => $this->config['slug'],
				'plugin'        => $this->config['plugin_file'],
				'new_version'   => $release['version'],
				'url'           => $release['html_url'],
				'package'       => $this->package_url( $release ),
				'requires'      => '6.4',
				'requires_php'  => '7.4',
				'tested'        => '7.1',
				'compatibility' => new stdClass(),
			);
			if ( version_compare( $release['version'], $this->config['version'], '>' ) ) {
				$transient->response[ $this->config['plugin_file'] ] = $entry;
				unset( $transient->no_update[ $this->config['plugin_file'] ] );
			} else {
				$transient->no_update[ $this->config['plugin_file'] ] = $entry;
				unset( $transient->response[ $this->config['plugin_file'] ] );
			}
			return $transient;
		}

		/**
		 * پنجرهٔ «مشاهدهٔ جزئیات» افزونه در پیشخوان.
		 *
		 * @param mixed  $result نتیجهٔ قبلی.
		 * @param string $action عمل درخواستی.
		 * @param object $args   آرگومان‌ها.
		 * @return mixed
		 */
		public function filter_plugin_information( $result, $action, $args ) {
			if ( 'plugin_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || $this->config['slug'] !== $args->slug ) {
				return $result;
			}
			$release = $this->get_latest_release();
			if ( is_wp_error( $release ) ) {
				return $result;
			}
			$info                 = new stdClass();
			$info->name           = $this->config['name'];
			$info->slug           = $this->config['slug'];
			$info->version        = $release['version'];
			$info->author         = 'سرزمین آریان';
			$info->homepage       = $release['html_url'];
			$info->requires       = '6.4';
			$info->requires_php   = '7.4';
			$info->tested         = '7.1';
			$info->last_updated   = $release['published_at'];
			$info->download_link  = $this->package_url( $release );
			$info->sections       = array(
				'description' => '<p>افزونهٔ شخصی سرزمین آریان؛ به‌روزرسانی امن از انتشارهای گیت‌هاب مخزن S.A.1.</p>',
				'changelog'   => '<p>' . nl2br( esc_html( $release['body'] ) ) . '</p>',
			);
			return $info;
		}

		/**
		 * تازه‌ترین انتشار سازگار این افزونه در گیت‌هاب.
		 *
		 * @return array|WP_Error
		 */
		public function get_latest_release() {
			$cached = get_site_transient( $this->transient_key() );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['asset_api_url'] ) ) {
				return $cached;
			}
			$response = wp_safe_remote_get(
				'https://api.github.com/repos/' . $this->config['owner'] . '/' . $this->config['repository'] . '/releases?per_page=100',
				array(
					'timeout' => 20,
					'headers' => $this->github_headers( 'application/vnd.github+json' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return new WP_Error( 'sa_github_api_http_error', 'API گیت‌هاب پاسخ معتبر نداد؛ توکن و دسترسی مخزن را بررسی کنید.' );
			}
			$releases = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $releases ) ) {
				return new WP_Error( 'sa_github_invalid_response', 'پاسخ نسخه‌های گیت‌هاب قابل خواندن نیست.' );
			}
			$latest = null;
			foreach ( $releases as $release ) {
				$candidate = $this->normalise_release( $release );
				if ( $candidate && ( null === $latest || version_compare( $candidate['version'], $latest['version'], '>' ) ) ) {
					$latest = $candidate;
				}
			}
			if ( null === $latest ) {
				return new WP_Error( 'sa_github_release_missing', 'هنوز انتشار سازگار این افزونه در گیت‌هاب پیدا نشد.' );
			}
			set_site_transient( $this->transient_key(), $latest, 6 * HOUR_IN_SECONDS );
			return $latest;
		}

		/**
		 * اعتبارسنجی و خلاصه‌سازی یک آبجکت انتشار گیت‌هاب.
		 *
		 * @param mixed $release دادهٔ API.
		 * @return array|null
		 */
		private function normalise_release( $release ) {
			if ( ! is_array( $release ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] )
				|| empty( $release['tag_name'] ) || 0 !== strpos( (string) $release['tag_name'], $this->config['tag_prefix'] ) ) {
				return null;
			}
			$version = substr( (string) $release['tag_name'], strlen( $this->config['tag_prefix'] ) );
			if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
				return null;
			}
			$expected = $this->config['slug'] . '-' . $version . '.zip';
			$asset    = null;
			foreach ( isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array() as $row ) {
				if ( is_array( $row ) && isset( $row['name'] ) && $expected === $row['name'] ) {
					$asset = $row;
					break;
				}
			}
			if ( ! $asset || empty( $asset['url'] ) ) {
				return null;
			}
			$checksum = '';
			if ( ! empty( $asset['digest'] ) && preg_match( '/^sha256:([a-f0-9]{64})$/i', (string) $asset['digest'], $m ) ) {
				$checksum = strtolower( $m[1] );
			}
			return array(
				'version'       => $version,
				'html_url'      => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : '',
				'asset_api_url' => esc_url_raw( $asset['url'] ),
				'asset_name'    => $expected,
				'sha256'        => $checksum,
				'published_at'  => isset( $release['published_at'] ) ? sanitize_text_field( $release['published_at'] ) : '',
				'body'          => isset( $release['body'] ) ? sanitize_textarea_field( $release['body'] ) : '',
			);
		}

		/**
		 * دانلود دارایی خصوصی انتشار، بدون نشت توکن به میزبان امضاشده.
		 *
		 * @param mixed  $reply      پاسخ قبلی.
		 * @param string $package    نشانی بسته.
		 * @param object $upgrader   آپگریدر.
		 * @param array  $hook_extra زمینهٔ آپگرید.
		 * @return mixed
		 */
		public function download_private_release( $reply, $package, $upgrader, $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
			if ( false !== $reply || ! $this->is_own_package( $package ) ) {
				return $reply;
			}
			if ( ! function_exists( 'wp_tempnam' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$checksum = $this->checksum_from_package_url( (string) $package );
			$tempfile = wp_tempnam( $this->config['slug'] . '.zip' );
			if ( ! $tempfile ) {
				return new WP_Error( 'sa_github_temp_file', 'وردپرس نتوانست فایل موقت برای بستهٔ به‌روزرسانی بسازد.' );
			}
			$response = wp_safe_remote_get(
				remove_query_arg( 'sa_sha256', (string) $package ),
				array(
					'timeout'     => 300,
					'redirection' => 0,
					'stream'      => true,
					'filename'    => $tempfile,
					'headers'     => $this->github_headers( 'application/octet-stream' ),
				)
			);
			if ( is_wp_error( $response ) ) {
				$this->delete_temp_file( $tempfile );
				return new WP_Error( 'sa_github_download_failed', 'دریافت بسته از گیت‌هاب انجام نشد: ' . $response->get_error_message() );
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( in_array( $status, array( 301, 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				$this->delete_temp_file( $tempfile );
				if ( ! is_string( $location ) || ! $this->is_safe_download_url( $location ) ) {
					return new WP_Error( 'sa_github_unsafe_redirect', 'گیت‌هاب نشانی دانلود معتبر و امنی برنگرداند.' );
				}
				$tempfile = wp_tempnam( $this->config['slug'] . '.zip' );
				if ( ! $tempfile ) {
					return new WP_Error( 'sa_github_temp_file', 'وردپرس نتوانست فایل موقت برای بستهٔ به‌روزرسانی بسازد.' );
				}
				// توکن به میزبان امضاشده فرستاده نمی‌شود.
				$response = wp_safe_remote_get(
					$location,
					array(
						'timeout'     => 300,
						'redirection' => 3,
						'stream'      => true,
						'filename'    => $tempfile,
					)
				);
				if ( is_wp_error( $response ) ) {
					$this->delete_temp_file( $tempfile );
					return new WP_Error( 'sa_github_asset_download_failed', 'دریافت فایل انتشار انجام نشد: ' . $response->get_error_message() );
				}
				$status = (int) wp_remote_retrieve_response_code( $response );
			}
			if ( 200 !== $status ) {
				$this->delete_temp_file( $tempfile );
				return new WP_Error( 'sa_github_download_http_error', 'گیت‌هاب هنگام دریافت بسته کد HTTP ' . $status . ' را برگرداند؛ توکن و دسترسی مخزن را بررسی کنید.' );
			}
			if ( ! $this->is_zip_file( $tempfile ) ) {
				$this->delete_temp_file( $tempfile );
				return new WP_Error( 'sa_github_invalid_zip', 'فایل دریافتی یک بستهٔ ZIP معتبر نیست.' );
			}
			$actual = hash_file( 'sha256', $tempfile );
			if ( $checksum && ( ! $actual || ! hash_equals( $checksum, (string) $actual ) ) ) {
				$this->delete_temp_file( $tempfile );
				return new WP_Error( 'sa_github_checksum_mismatch', 'صحت بستهٔ به‌روزرسانی تأیید نشد (SHA-256 ناسازگار است). نصب متوقف شد.' );
			}
			return $tempfile;
		}

		/**
		 * آیا این بسته متعلق به همین افزونه است؟
		 *
		 * @param string $package نشانی بسته.
		 * @return bool
		 */
		private function is_own_package( $package ) {
			$host = strtolower( (string) wp_parse_url( (string) $package, PHP_URL_HOST ) );
			$path = (string) wp_parse_url( (string) $package, PHP_URL_PATH );
			$pattern = '#^/repos/' . preg_quote( $this->config['owner'], '#' ) . '/' . preg_quote( $this->config['repository'], '#' ) . '/releases/assets/\d+$#';
			if ( 'api.github.com' !== $host || ! preg_match( $pattern, $path ) ) {
				return false;
			}
			$release = $this->get_latest_release();
			return ! is_wp_error( $release ) && remove_query_arg( 'sa_sha256', (string) $package ) === $release['asset_api_url'];
		}

		/**
		 * پاک‌کردن کش آپدیت بعد از نصب نسخهٔ تازهٔ همین افزونه.
		 *
		 * @param object $upgrader آپگریدر.
		 * @param array  $options  زمینه.
		 */
		public function clear_cache_after_update( $upgrader, $options ) {
			if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
				return;
			}
			$plugins = isset( $options['plugins'] ) ? (array) $options['plugins'] : array();
			if ( isset( $options['plugin'] ) ) {
				$plugins[] = $options['plugin'];
			}
			if ( in_array( $this->config['plugin_file'], $plugins, true ) ) {
				delete_site_transient( $this->transient_key() );
				delete_site_transient( 'update_plugins' );
			}
		}

		/**
		 * نشانی بسته با چک‌سام مورداعتماد.
		 *
		 * @param array $release انتشار.
		 * @return string
		 */
		private function package_url( $release ) {
			return ! empty( $release['sha256'] )
				? add_query_arg( 'sa_sha256', $release['sha256'], $release['asset_api_url'] )
				: $release['asset_api_url'];
		}

		/**
		 * هدرهای API گیت‌هاب.
		 *
		 * @param string $accept Accept.
		 * @return array
		 */
		private function github_headers( $accept ) {
			$headers = array(
				'Accept'               => $accept,
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'Sarzamin-Aryan-Plugin-Updater/' . $this->config['version'],
			);
			$token   = $this->get_token();
			if ( '' !== $token ) {
				$headers['Authorization'] = 'Bearer ' . $token;
			}
			return $headers;
		}

		/**
		 * توکن: ثابت wp-config، بعد گزینهٔ به‌روزرسان قالب، بعد فیلتر.
		 *
		 * @return string
		 */
		private function get_token() {
			if ( defined( 'SA_GITHUB_TOKEN' ) && is_string( SA_GITHUB_TOKEN ) && '' !== trim( SA_GITHUB_TOKEN ) ) {
				$token = trim( SA_GITHUB_TOKEN );
			} else {
				$token = trim( (string) get_option( 'sa_github_updater_token', '' ) );
			}
			return trim( (string) apply_filters( 'sa_github_updater_token', $token ) );
		}

		/**
		 * چک‌سام از کوئری بسته.
		 *
		 * @param string $url نشانی.
		 * @return string
		 */
		private function checksum_from_package_url( $url ) {
			$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
			parse_str( $query, $args );
			return ( ! empty( $args['sa_sha256'] ) && preg_match( '/^[a-f0-9]{64}$/i', (string) $args['sa_sha256'] ) )
				? strtolower( (string) $args['sa_sha256'] ) : '';
		}

		/**
		 * اعتبارسنجی امضای ZIP.
		 *
		 * @param string $filename فایل.
		 * @return bool
		 */
		private function is_zip_file( $filename ) {
			if ( ! is_file( $filename ) || filesize( $filename ) < 4 ) {
				return false;
			}
			$handle = fopen( $filename, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- خواندن امضای فایل موقت.
			if ( false === $handle ) {
				return false;
			}
			$signature = fread( $handle, 2 );
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- همان فایل موقت.
			return 'PK' === $signature;
		}

		/**
		 * حذف فایل موقت.
		 *
		 * @param string $filename فایل.
		 */
		private function delete_temp_file( $filename ) {
			if ( $filename && is_file( $filename ) ) {
				wp_delete_file( $filename );
			}
		}

		/**
		 * نشانی دانلود امضاشدهٔ گیت‌هاب امن است؟
		 *
		 * @param string $url نشانی.
		 * @return bool
		 */
		private function is_safe_download_url( $url ) {
			$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
			$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( 'https' !== $scheme || '' === $host ) {
				return false;
			}
			return 'github.com' === $host
				|| 'objects.githubusercontent.com' === $host
				|| 'release-assets.githubusercontent.com' === $host
				|| ( strlen( $host ) > 22 && '.githubusercontent.com' === substr( $host, -22 ) );
		}
	}

endif;
