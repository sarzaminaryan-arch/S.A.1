<?php
/**
 * GitHub release updater for the Sarzamin Aryan child theme.
 *
 * The repository is private, so administrators can either define
 * SA_GITHUB_TOKEN in wp-config.php or save a read-only token from the
 * Appearance > GitHub Updater screen. The token is never bundled in a release.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects the child theme to its GitHub releases.
 */
final class SA_GitHub_Theme_Updater {

	const OWNER             = 'sarzaminaryan-arch';
	const REPOSITORY        = 'S.A.1';
	const THEME_SLUG        = 'sarzaminaryan-child';
	const TAG_PREFIX        = 'sarzaminaryan-child-v';
	const TOKEN_OPTION      = 'sa_github_updater_token';
	const RELEASE_TRANSIENT = 'sa_github_theme_release';
	const SETTINGS_SLUG     = 'sa-github-updater';

	/**
	 * Singleton instance.
	 *
	 * @var SA_GitHub_Theme_Updater|null
	 */
	private static $instance = null;

	/**
	 * Register updater hooks once.
	 *
	 * @return SA_GitHub_Theme_Updater
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 */
	private function __construct() {
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'filter_update_transient' ) );
		add_filter( 'themes_api', array( $this, 'filter_theme_information' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'download_private_release' ), 20, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_after_update' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
			add_action( 'admin_post_sa_save_github_updater', array( $this, 'save_settings' ) );
			add_action( 'admin_post_sa_check_github_updates', array( $this, 'force_update_check' ) );
		}
	}

	/**
	 * Add a GitHub release to WordPress' native theme update data.
	 *
	 * @param mixed $transient Theme update transient.
	 * @return mixed
	 */
	public function filter_update_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$theme = wp_get_theme( self::THEME_SLUG );
		if ( ! $theme->exists() ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( is_wp_error( $release ) ) {
			return $transient;
		}

		$current_version = (string) $theme->get( 'Version' );
		$update_data     = array(
			'theme'        => self::THEME_SLUG,
			'new_version'  => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $this->package_url( $release ),
			'requires'     => '6.4',
			'requires_php' => '7.4',
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		if ( version_compare( $release['version'], $current_version, '>' ) ) {
			$transient->response[ self::THEME_SLUG ] = $update_data;
			unset( $transient->no_update[ self::THEME_SLUG ] );
		} else {
			$transient->no_update[ self::THEME_SLUG ] = $update_data;
			unset( $transient->response[ self::THEME_SLUG ] );
		}

		return $transient;
	}

	/**
	 * Populate the native theme-information modal for this theme.
	 *
	 * @param mixed  $result Existing result.
	 * @param string $action Requested API action.
	 * @param object $args   API arguments.
	 * @return mixed
	 */
	public function filter_theme_information( $result, $action, $args ) {
		if ( 'theme_information' !== $action || ! is_object( $args ) || empty( $args->slug ) || self::THEME_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}

		$information               = new stdClass();
		$information->name         = 'Sarzamin Aryan Child';
		$information->slug         = self::THEME_SLUG;
		$information->version      = $release['version'];
		$information->author       = 'محمدرضا لک';
		$information->homepage     = $release['html_url'];
		$information->requires     = '6.4';
		$information->requires_php = '7.4';
		$information->last_updated = $release['published_at'];
		$information->download_link = $this->package_url( $release );
		$information->sections     = array(
			'description' => '<p>' . esc_html__( 'قالب فرزند سرزمین آریان؛ دریافت به‌روزرسانی امن از انتشارهای گیت‌هاب.', 'sarzaminaryan-child' ) . '</p>',
			'changelog'   => '<p>' . nl2br( esc_html( $release['body'] ) ) . '</p>',
		);

		return $information;
	}

	/**
	 * Download a private GitHub release asset without exposing the token in its URL.
	 *
	 * Authentication is sent only to api.github.com. If GitHub redirects to its
	 * signed asset host, the second request deliberately contains no token.
	 *
	 * @param mixed  $reply      Existing pre-download result.
	 * @param string $package    Package URL.
	 * @param object $upgrader   WordPress upgrader instance.
	 * @param array  $hook_extra Upgrade context.
	 * @return mixed
	 */
	public function download_private_release( $reply, $package, $upgrader, $hook_extra ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( false !== $reply || ! $this->is_repository_asset_url( $package ) ) {
			return $reply;
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$checksum = $this->checksum_from_package_url( $package );
		$api_url  = remove_query_arg( 'sa_sha256', $package );
		$tempfile = wp_tempnam( self::THEME_SLUG . '.zip' );

		if ( ! $tempfile ) {
			return new WP_Error(
				'sa_github_temp_file',
				esc_html__( 'وردپرس نتوانست فایل موقت برای بستهٔ به‌روزرسانی بسازد.', 'sarzaminaryan-child' )
			);
		}

		$response = wp_safe_remote_get(
			$api_url,
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
			return new WP_Error(
				'sa_github_download_failed',
				sprintf(
					/* translators: %s: HTTP error message. */
					esc_html__( 'دریافت بسته از گیت‌هاب انجام نشد: %s', 'sarzaminaryan-child' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			$this->delete_temp_file( $tempfile );

			if ( ! is_string( $location ) || ! $this->is_safe_github_download_url( $location ) ) {
				return new WP_Error(
					'sa_github_unsafe_redirect',
					esc_html__( 'گیت‌هاب نشانی دانلود معتبر و امنی برنگرداند.', 'sarzaminaryan-child' )
				);
			}

			$tempfile = wp_tempnam( self::THEME_SLUG . '.zip' );
			if ( ! $tempfile ) {
				return new WP_Error(
					'sa_github_temp_file',
					esc_html__( 'وردپرس نتوانست فایل موقت برای بستهٔ به‌روزرسانی بسازد.', 'sarzaminaryan-child' )
				);
			}

			// Never forward the GitHub token to the signed download host.
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
				return new WP_Error(
					'sa_github_asset_download_failed',
					sprintf(
						/* translators: %s: HTTP error message. */
						esc_html__( 'دریافت فایل انتشار انجام نشد: %s', 'sarzaminaryan-child' ),
						$response->get_error_message()
					)
				);
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );
		}

		if ( 200 !== $status_code ) {
			$this->delete_temp_file( $tempfile );
			return new WP_Error(
				'sa_github_download_http_error',
				sprintf(
					/* translators: %d: HTTP status code. */
					esc_html__( 'گیت‌هاب هنگام دریافت بسته کد HTTP %d را برگرداند. توکن و دسترسی مخزن را بررسی کنید.', 'sarzaminaryan-child' ),
					$status_code
				)
			);
		}

		if ( ! $this->is_zip_file( $tempfile ) ) {
			$this->delete_temp_file( $tempfile );
			return new WP_Error(
				'sa_github_invalid_zip',
				esc_html__( 'فایل دریافتی یک بستهٔ ZIP معتبر نیست؛ توکن گیت‌هاب یا دسترسی مخزن را بررسی کنید.', 'sarzaminaryan-child' )
			);
		}

		$actual_checksum = hash_file( 'sha256', $tempfile );
		if ( $checksum && ( ! $actual_checksum || ! hash_equals( $checksum, $actual_checksum ) ) ) {
			$this->delete_temp_file( $tempfile );
			return new WP_Error(
				'sa_github_checksum_mismatch',
				esc_html__( 'صحت بستهٔ به‌روزرسانی تأیید نشد (SHA-256 ناسازگار است). نصب متوقف شد.', 'sarzaminaryan-child' )
			);
		}

		return $tempfile;
	}

	/**
	 * Clear WordPress' update cache after this theme is upgraded.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $options  Upgrade context.
	 */
	public function clear_cache_after_update( $upgrader, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		if ( empty( $options['type'] ) || 'theme' !== $options['type'] ) {
			return;
		}

		$themes = isset( $options['themes'] ) ? (array) $options['themes'] : array();
		if ( isset( $options['theme'] ) ) {
			$themes[] = $options['theme'];
		}

		if ( in_array( self::THEME_SLUG, $themes, true ) ) {
			delete_site_transient( 'update_themes' );
		}
	}

	/**
	 * Register Appearance > GitHub Updater.
	 */
	public function register_settings_page() {
		add_theme_page(
			esc_html__( 'به‌روزرسان گیت‌هاب', 'sarzaminaryan-child' ),
			esc_html__( 'به‌روزرسان گیت‌هاب', 'sarzaminaryan-child' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render updater settings and connection status.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'sarzaminaryan-child' ) );
		}

		$theme           = wp_get_theme( self::THEME_SLUG );
		$release         = $this->get_latest_release();
		$constant_token  = defined( 'SA_GITHUB_TOKEN' ) && is_string( SA_GITHUB_TOKEN ) && '' !== trim( SA_GITHUB_TOKEN );
		$database_token  = '' !== (string) get_option( self::TOKEN_OPTION, '' );
		$token_available = '' !== $this->get_token();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'به‌روزرسان خودکار قالب از گیت‌هاب', 'sarzaminaryan-child' ); ?></h1>

			<?php if ( isset( $_GET['sa-updater-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'تنظیمات ذخیره شد و حافظهٔ بررسی نسخه پاک شد.', 'sarzaminaryan-child' ); ?></p></div>
			<?php endif; ?>

			<p><?php echo esc_html__( 'این قالب انتشارهای رسمی مخزن خصوصی قالب را بررسی می‌کند و نسخهٔ جدید را در «پیشخوان ← به‌روزرسانی‌ها» نشان می‌دهد.', 'sarzaminaryan-child' ); ?></p>

			<table class="widefat striped" style="max-width: 900px">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'نسخهٔ نصب‌شده', 'sarzaminaryan-child' ); ?></th>
						<td><code><?php echo esc_html( (string) $theme->get( 'Version' ) ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'مخزن', 'sarzaminaryan-child' ); ?></th>
						<td><a href="<?php echo esc_url( $this->repository_url() ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $this->owner() . '/' . $this->repository() ); ?></code></a> — <?php echo esc_html__( 'خصوصی', 'sarzaminaryan-child' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'توکن دسترسی', 'sarzaminaryan-child' ); ?></th>
						<td>
							<?php
							if ( $constant_token ) {
								echo esc_html__( 'از ثابت SA_GITHUB_TOKEN در wp-config.php خوانده شد (روش پیشنهادی).', 'sarzaminaryan-child' );
							} elseif ( $database_token ) {
								echo esc_html__( 'در تنظیمات وردپرس ذخیره شده است.', 'sarzaminaryan-child' );
							} else {
								echo esc_html__( 'تنظیم نشده است.', 'sarzaminaryan-child' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'ارتباط و آخرین انتشار', 'sarzaminaryan-child' ); ?></th>
						<td>
							<?php if ( is_wp_error( $release ) ) : ?>
								<strong style="color:#b32d2e"><?php echo esc_html( $release->get_error_message() ); ?></strong>
							<?php else : ?>
								<strong style="color:#008a20"><?php echo esc_html__( 'ارتباط برقرار است.', 'sarzaminaryan-child' ); ?></strong>
								<?php echo esc_html__( ' آخرین نسخه: ', 'sarzaminaryan-child' ); ?><a href="<?php echo esc_url( $release['html_url'] ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $release['version'] ); ?></code></a>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'توکن مخزن خصوصی', 'sarzaminaryan-child' ); ?></h2>
			<p><?php echo esc_html__( 'یک Fine-grained personal access token فقط برای همین مخزن و با مجوز Repository contents: Read-only بسازید. توکن را در گیت‌هاب یا گفت‌وگو منتشر نکنید.', 'sarzaminaryan-child' ); ?></p>
			<p><?php echo esc_html__( 'روش امن‌تر: خط زیر را پیش از عبارت «That’s all» در wp-config.php بگذارید:', 'sarzaminaryan-child' ); ?></p>
			<pre style="direction:ltr;text-align:left;max-width:900px;background:#fff;border:1px solid #ccd0d4;padding:12px"><code>define( 'SA_GITHUB_TOKEN', 'github_pat_xxxxxxxxxxxx' );</code></pre>

			<?php if ( ! $constant_token ) : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="max-width:900px">
					<input type="hidden" name="action" value="sa_save_github_updater">
					<?php wp_nonce_field( 'sa_save_github_updater' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="sa-github-token"><?php echo esc_html__( 'GitHub Token', 'sarzaminaryan-child' ); ?></label></th>
							<td>
								<input id="sa-github-token" name="sa_github_token" type="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo $database_token ? esc_attr__( 'توکن ذخیره شده؛ برای حفظ آن خالی بگذارید', 'sarzaminaryan-child' ) : esc_attr( 'github_pat_…' ); ?>">
								<?php if ( $database_token ) : ?>
									<p><label><input name="sa_remove_github_token" type="checkbox" value="1"> <?php echo esc_html__( 'توکن ذخیره‌شده حذف شود', 'sarzaminaryan-child' ); ?></label></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button( esc_html__( 'ذخیرهٔ تنظیمات', 'sarzaminaryan-child' ) ); ?>
				</form>
			<?php endif; ?>

			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="sa_check_github_updates">
				<?php wp_nonce_field( 'sa_check_github_updates' ); ?>
				<?php submit_button( esc_html__( 'بررسی هم‌اکنون', 'sarzaminaryan-child' ), 'secondary', 'submit', false, $token_available ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save an optional database token. A blank field preserves the current one.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ انجام این کار را ندارید.', 'sarzaminaryan-child' ) );
		}
		check_admin_referer( 'sa_save_github_updater' );

		$remove_token = isset( $_POST['sa_remove_github_token'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['sa_remove_github_token'] ) );
		$new_token    = isset( $_POST['sa_github_token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['sa_github_token'] ) ) ) : '';

		if ( $remove_token ) {
			delete_option( self::TOKEN_OPTION );
		} elseif ( '' !== $new_token ) {
			update_option( self::TOKEN_OPTION, $new_token, false );
		}

		$this->clear_release_cache();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => self::SETTINGS_SLUG,
					'sa-updater-updated' => '1',
				),
				admin_url( 'themes.php' )
			)
		);
		exit;
	}

	/**
	 * Clear caches and open WordPress' native forced update check.
	 */
	public function force_update_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ انجام این کار را ندارید.', 'sarzaminaryan-child' ) );
		}
		check_admin_referer( 'sa_check_github_updates' );

		$this->clear_release_cache();
		wp_safe_redirect( wp_nonce_url( admin_url( 'update-core.php?force-check=1' ), 'force-check' ) );
		exit;
	}

	/**
	 * Return the newest matching GitHub release and ZIP asset.
	 *
	 * @return array|WP_Error
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::RELEASE_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['asset_api_url'] ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			$this->releases_api_url(),
			array(
				'timeout' => 20,
				'headers' => $this->github_headers( 'application/vnd.github+json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'sa_github_api_unavailable',
				sprintf(
					/* translators: %s: HTTP error message. */
					esc_html__( 'ارتباط با API گیت‌هاب برقرار نشد: %s', 'sarzaminaryan-child' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			if ( 404 === $status_code && '' === $this->get_token() ) {
				$message = esc_html__( 'این مخزن خصوصی است. برای بررسی نسخه‌ها، توکن فقط‌خواندنی گیت‌هاب را تنظیم کنید.', 'sarzaminaryan-child' );
			} elseif ( in_array( $status_code, array( 401, 403, 404 ), true ) ) {
				$message = esc_html__( 'گیت‌هاب دسترسی را نپذیرفت. اعتبار توکن و دسترسی آن به همین مخزن را بررسی کنید.', 'sarzaminaryan-child' );
			} else {
				$message = sprintf(
					/* translators: %d: HTTP status code. */
					esc_html__( 'API گیت‌هاب کد HTTP %d را برگرداند.', 'sarzaminaryan-child' ),
					$status_code
				);
			}
			return new WP_Error( 'sa_github_api_http_error', $message );
		}

		$releases = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $releases ) ) {
			return new WP_Error(
				'sa_github_invalid_response',
				esc_html__( 'پاسخ نسخه‌های گیت‌هاب قابل خواندن نیست.', 'sarzaminaryan-child' )
			);
		}

		$latest = null;
		foreach ( $releases as $release ) {
			$candidate = $this->normalise_release( $release );
			if ( ! $candidate ) {
				continue;
			}

			if ( null === $latest || version_compare( $candidate['version'], $latest['version'], '>' ) ) {
				$latest = $candidate;
			}
		}

		if ( null === $latest ) {
			return new WP_Error(
				'sa_github_release_missing',
				esc_html__( 'هنوز انتشار سازگار قالب در گیت‌هاب پیدا نشد.', 'sarzaminaryan-child' )
			);
		}

		set_site_transient( self::RELEASE_TRANSIENT, $latest, 6 * HOUR_IN_SECONDS );
		return $latest;
	}

	/**
	 * Validate and reduce one GitHub release object.
	 *
	 * @param mixed $release GitHub API release data.
	 * @return array|null
	 */
	private function normalise_release( $release ) {
		if (
			! is_array( $release ) ||
			! empty( $release['draft'] ) ||
			! empty( $release['prerelease'] ) ||
			empty( $release['tag_name'] ) ||
			0 !== strpos( $release['tag_name'], self::TAG_PREFIX )
		) {
			return null;
		}

		$version = substr( $release['tag_name'], strlen( self::TAG_PREFIX ) );
		if ( ! preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			return null;
		}

		$expected_asset = self::THEME_SLUG . '-v' . $version . '.zip';
		$selected_asset = null;
		foreach ( isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array() as $asset ) {
			if ( is_array( $asset ) && isset( $asset['name'] ) && $expected_asset === $asset['name'] ) {
				$selected_asset = $asset;
				break;
			}
		}

		if ( ! $selected_asset || empty( $selected_asset['url'] ) ) {
			return null;
		}

		$checksum = '';
		if ( ! empty( $selected_asset['digest'] ) && preg_match( '/^sha256:([a-f0-9]{64})$/i', $selected_asset['digest'], $matches ) ) {
			$checksum = strtolower( $matches[1] );
		}

		return array(
			'version'         => $version,
			'html_url'        => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : $this->repository_url(),
			'asset_api_url'   => esc_url_raw( $selected_asset['url'] ),
			'asset_name'      => $expected_asset,
			'sha256'          => $checksum,
			'published_at'    => isset( $release['published_at'] ) ? sanitize_text_field( $release['published_at'] ) : '',
			'body'            => isset( $release['body'] ) ? sanitize_textarea_field( $release['body'] ) : '',
		);
	}

	/**
	 * Build headers for GitHub API requests.
	 *
	 * @param string $accept Accept header.
	 * @return array
	 */
	private function github_headers( $accept ) {
		$headers = array(
			'Accept'               => $accept,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'Sarzamin-Aryan-Child-Updater/' . SA_CHILD_VERSION,
		);
		$token   = $this->get_token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		return $headers;
	}

	/**
	 * Read token from wp-config.php first, then from the non-autoloaded option.
	 *
	 * @return string
	 */
	private function get_token() {
		if ( defined( 'SA_GITHUB_TOKEN' ) && is_string( SA_GITHUB_TOKEN ) && '' !== trim( SA_GITHUB_TOKEN ) ) {
			$token = trim( SA_GITHUB_TOKEN );
		} else {
			$token = trim( (string) get_option( self::TOKEN_OPTION, '' ) );
		}

		/**
		 * Filter the token without saving it in the theme or WordPress database.
		 *
		 * @param string $token GitHub token.
		 */
		return trim( (string) apply_filters( 'sa_github_updater_token', $token ) );
	}

	/**
	 * Add the trusted API checksum to the package URL for the downloader.
	 *
	 * @param array $release Normalised release.
	 * @return string
	 */
	private function package_url( $release ) {
		if ( ! empty( $release['sha256'] ) ) {
			return add_query_arg( 'sa_sha256', $release['sha256'], $release['asset_api_url'] );
		}

		return $release['asset_api_url'];
	}

	/**
	 * Ensure the pre-download hook only handles this repository's assets.
	 *
	 * @param string $url Package URL.
	 * @return bool
	 */
	private function is_repository_asset_url( $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );

		$expected = '#^/repos/' . preg_quote( $this->owner(), '#' ) . '/' . preg_quote( $this->repository(), '#' ) . '/releases/assets/\d+$#';

		return 'api.github.com' === $host && (bool) preg_match( $expected, $path );
	}

	/**
	 * Validate GitHub's unauthenticated signed asset redirect.
	 *
	 * @param string $url Redirect URL.
	 * @return bool
	 */
	private function is_safe_github_download_url( $url ) {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		if ( 'https' !== $scheme || '' === $host ) {
			return false;
		}

		return 'github.com' === $host ||
			'objects.githubusercontent.com' === $host ||
			'release-assets.githubusercontent.com' === $host ||
			( strlen( $host ) > 22 && '.githubusercontent.com' === substr( $host, -22 ) );
	}

	/**
	 * Read a trusted SHA-256 value from the package query string.
	 *
	 * @param string $url Package URL.
	 * @return string
	 */
	private function checksum_from_package_url( $url ) {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $arguments );

		if ( ! empty( $arguments['sa_sha256'] ) && preg_match( '/^[a-f0-9]{64}$/i', $arguments['sa_sha256'] ) ) {
			return strtolower( $arguments['sa_sha256'] );
		}

		return '';
	}

	/**
	 * Basic ZIP signature and size validation.
	 *
	 * @param string $filename Temporary filename.
	 * @return bool
	 */
	private function is_zip_file( $filename ) {
		if ( ! is_file( $filename ) || filesize( $filename ) < 4 ) {
			return false;
		}

		$handle = fopen( $filename, 'rb' );
		if ( false === $handle ) {
			return false;
		}
		$signature = fread( $handle, 2 );
		fclose( $handle );

		return 'PK' === $signature;
	}

	/**
	 * Delete a temporary file through WordPress when possible.
	 *
	 * @param string $filename Temporary filename.
	 */
	private function delete_temp_file( $filename ) {
		if ( $filename && is_file( $filename ) ) {
			wp_delete_file( $filename );
		}
	}

	/**
	 * Clear custom and native theme update caches.
	 */
	private function clear_release_cache() {
		delete_site_transient( self::RELEASE_TRANSIENT );
		delete_site_transient( 'update_themes' );
	}


	/**
	 * Repository owner. Override with SA_GITHUB_OWNER in wp-config.php or the
	 * `sa_github_updater_owner` filter — releases moved repositories once already.
	 *
	 * @return string
	 */
	private function owner() {
		$owner = defined( 'SA_GITHUB_OWNER' ) && SA_GITHUB_OWNER ? SA_GITHUB_OWNER : self::OWNER;

		return (string) apply_filters( 'sa_github_updater_owner', $owner );
	}

	/**
	 * Repository name. Override with SA_GITHUB_REPO in wp-config.php or the
	 * `sa_github_updater_repository` filter.
	 *
	 * @return string
	 */
	private function repository() {
		$repo = defined( 'SA_GITHUB_REPO' ) && SA_GITHUB_REPO ? SA_GITHUB_REPO : self::REPOSITORY;

		return (string) apply_filters( 'sa_github_updater_repository', $repo );
	}

	/**
	 * GitHub releases API URL.
	 *
	 * @return string
	 */
	private function releases_api_url() {
		return sprintf(
			'https://api.github.com/repos/%s/%s/releases?per_page=100',
			rawurlencode( $this->owner() ),
			rawurlencode( $this->repository() )
		);
	}

	/**
	 * Repository browser URL.
	 *
	 * @return string
	 */
	private function repository_url() {
		return 'https://github.com/' . $this->owner() . '/' . $this->repository();
	}
}

SA_GitHub_Theme_Updater::init();
