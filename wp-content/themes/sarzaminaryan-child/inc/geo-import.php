<?php
/**
 * Bulk import of county data (the output of content-templates/tools/geo/export_meta.py).
 *
 * Two doors, same engine:
 *   • پیشخان: شهرها ← پوشش ۴۸۳ شهرستان ← زبانهٔ «ورود انبوه داده» (برای میزبانی cPanel بدون SSH)
 *   • WP-CLI: wp sa-county import <file.json> [--force] [--dry-run]
 *
 * Payload shape: { "<county-slug>": { "sa_cty_center": "…", "sa_cty_population": 51000, … } }
 * Safety: only known meta keys are written, only to posts whose slug is in the official
 * registry, and by default only into EMPTY fields — a human edit is never overwritten
 * unless --force / «بازنویسی» is chosen.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta keys this importer is allowed to write.
 *
 * @return string[]
 */
function sa_county_import_keys() {
	$keys = array( 'sa_city_latitude', 'sa_city_longitude', 'sa_city_elevation', 'sa_google_map_url', 'sa_city_population' );
	foreach ( sa_county_schema() as $f ) {
		$keys[] = $f['key'];
	}
	return $keys;
}

/**
 * Find the city post for a county slug — by slug, then by title, then fuzzy.
 *
 * @param string $slug County slug from the registry.
 * @return WP_Post|null
 */
function sa_county_find_post( $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'city' );
	if ( $post ) {
		return $post;
	}
	$row  = sa_county( $slug );
	$name = $row && ! empty( $row['name'] ) ? (string) $row['name'] : '';
	if ( '' === $name ) {
		return null;
	}
	foreach ( array( 'شهرستان ' . $name, $name ) as $title ) {
		$by_title = new WP_Query(
			array(
				'post_type'              => 'city',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'title'                  => $title,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $by_title->posts ) ) {
			return $by_title->posts[0];
		}
	}
	$q = new WP_Query(
		array(
			'post_type'              => 'city',
			'post_status'            => 'any',
			'posts_per_page'         => 2,
			's'                      => $name,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $q->posts as $candidate ) {
		$title = (string) $candidate->post_title;
		if ( $title === $name || $title === 'شهرستان ' . $name ) {
			return $candidate;
		}
	}

	return null;
}

/**
 * Normalise a pasted JSON blob (code fences, BOM, RTL marks, smart quotes).
 *
 * @param string $raw Raw textarea content.
 * @return string
 */
function sa_county_clean_json( $raw ) {
	$raw = trim( $raw );
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$raw = preg_replace( '/^```[a-zA-Z]*\s*|\s*```$/', '', $raw );
	$raw = str_replace(
		array( "\xE2\x80\x8F", "\xE2\x80\x8E", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xC2\xAB", "\xC2\xBB", "\xE2\x80\x99" ),
		array( '', '', '"', '"', '"', '"', "'" ),
		$raw
	);
	$fa  = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	$ar  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
	foreach ( array( $fa, $ar ) as $set ) {
		$raw = str_replace( $set, array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), $raw );
	}

	return trim( $raw );
}

/**
 * Apply a payload.
 *
 * @param array $payload  slug => array( meta_key => value ).
 * @param bool  $force    Overwrite non-empty fields.
 * @param bool  $dry_run  Report only.
 * @return array Report: rows (per county) + totals.
 */
function sa_county_import_apply( $payload, $force = false, $dry_run = false ) {
	$allowed = array_flip( sa_county_import_keys() );
	$report  = array(
		'rows'    => array(),
		'written' => 0,
		'skipped' => 0,
		'missing' => array(),
		'unknown' => array(),
		'failed'  => array(),
	);

	foreach ( (array) $payload as $slug => $fields ) {
		$slug = sanitize_title( $slug );
		if ( ! sa_county( $slug ) ) {
			$report['unknown'][] = $slug;
			continue;
		}
		$post = sa_county_find_post( $slug );
		if ( ! $post ) {
			$report['missing'][] = $slug;
			continue;
		}
		$written = array();
		$skipped = array();
		foreach ( (array) $fields as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			$current = get_post_meta( $post->ID, $key, true );
			if ( '' !== (string) $current && ! $force ) {
				$skipped[] = $key;
				continue;
			}
			$value = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;
			$value = ( false !== strpos( $value, "\n" ) ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			if ( '' === trim( $value ) ) {
				continue;
			}
			if ( ! $dry_run ) {
				update_post_meta( $post->ID, $key, $value );
				$stored = (string) get_post_meta( $post->ID, $key, true );
				if ( $stored !== (string) $value ) {
					$report['failed'][] = $slug . ' › ' . $key;
					continue;
				}
			}
			$written[] = $key;
		}
		$report['written'] += count( $written );
		$report['skipped'] += count( $skipped );
		$report['rows'][]   = array(
			'slug'    => $slug,
			'title'   => get_the_title( $post->ID ),
			'status'  => get_post_status( $post->ID ),
			'post_id' => $post->ID,
			'written' => $written,
			'skipped' => $skipped,
		);
	}
	return $report;
}


/* -------------------------------------------------------------------------
 * One-click import straight from GitHub (no copy/paste at all).
 * ---------------------------------------------------------------------- */

/**
 * Read-only GitHub token (same source the theme updater uses).
 *
 * @return string
 */
function sa_county_github_token() {
	if ( defined( 'SA_GITHUB_TOKEN' ) && is_string( SA_GITHUB_TOKEN ) && '' !== trim( SA_GITHUB_TOKEN ) ) {
		return trim( SA_GITHUB_TOKEN );
	}

	return trim( (string) get_option( 'sa_github_updater_token', '' ) );
}

/**
 * Branch that carries the generated data files.
 *
 * @return string
 */
function sa_county_data_ref() {
	$ref = defined( 'SA_GEO_DATA_REF' ) && SA_GEO_DATA_REF ? SA_GEO_DATA_REF : 'arena/01a0fcfc-agent-arena';

	return (string) apply_filters( 'sa_county_data_ref', $ref );
}

/**
 * Download one province payload from the repository.
 *
 * @param string $province Province slug.
 * @return array|WP_Error
 */
function sa_county_github_payload( $province ) {
	$token = sa_county_github_token();
	if ( '' === $token ) {
		return new WP_Error( 'sa_no_token', 'توکن گیت‌هاب تنظیم نشده است. نمایش ← به‌روزرسان گیت‌هاب.' );
	}
	$owner = defined( 'SA_GITHUB_OWNER' ) && SA_GITHUB_OWNER ? SA_GITHUB_OWNER : 'sarzaminaryan-arch';
	$repo  = defined( 'SA_GITHUB_REPO' ) && SA_GITHUB_REPO ? SA_GITHUB_REPO : 'Agent-arena';
	$url   = sprintf(
		'https://api.github.com/repos/%s/%s/contents/content/data/export/%s.meta.json?ref=%s',
		rawurlencode( $owner ),
		rawurlencode( $repo ),
		rawurlencode( $province ),
		rawurlencode( sa_county_data_ref() )
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 30,
			'headers' => array(
				'Accept'               => 'application/vnd.github.raw',
				'Authorization'        => 'Bearer ' . $token,
				'X-GitHub-Api-Version' => '2022-11-28',
				'User-Agent'           => 'sarzaminaryan-child/' . SA_CHILD_VERSION,
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		$hint = 401 === $code || 403 === $code
			? 'توکن اجازهٔ خواندن این مخزن را ندارد.'
			: ( 404 === $code ? 'فایل این استان در شاخهٔ داده پیدا نشد.' : '' );

		return new WP_Error( 'sa_http', sprintf( 'گیت‌هاب پاسخ %d داد. %s', $code, $hint ) );
	}
	$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'sa_json', 'محتوای دریافتی JSON معتبر نبود.' );
	}

	return $payload;
}

/**
 * Admin screen section (called from the coverage page).
 */
function sa_county_import_screen() {
	if ( ! current_user_can( 'publish_posts' ) ) {
		return;
	}
	$report = null;
	$force  = false;
	$dry    = false;
	if ( isset( $_POST['sa_county_payload'] ) && check_admin_referer( 'sa_county_import' ) ) {
		$raw     = sa_county_clean_json( (string) wp_unslash( $_POST['sa_county_payload'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$force   = ! empty( $_POST['sa_county_force'] );
		$dry     = ! empty( $_POST['sa_county_dry'] );
		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			echo '<div class="notice notice-error"><p>JSON معتبر نیست: ' . esc_html( json_last_error_msg() ) . '</p></div>';
		} else {
			$report = sa_county_import_apply( $payload, $force, $dry );
		}
	}
	$gh_report = null;
	$gh_dry    = false;
	$gh_prov   = '';
	if ( isset( $_POST['sa_county_gh_province'] ) && check_admin_referer( 'sa_county_github' ) ) {
		$gh_prov = sanitize_key( wp_unslash( $_POST['sa_county_gh_province'] ) );
		$gh_dry  = ! empty( $_POST['sa_county_gh_dry'] );
		$payload = sa_county_github_payload( $gh_prov );
		if ( is_wp_error( $payload ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $payload->get_error_message() ) . '</p></div>';
		} else {
			$gh_report = sa_county_import_apply( $payload, ! empty( $_POST['sa_county_gh_force'] ), $gh_dry );
		}
	}
	$provinces = array();
	foreach ( sa_counties_by_province() as $slug => $rows ) {
		$provinces[ $slug ] = sa_province_name( $slug ) . ' (' . sa_fa_digits( count( $rows ) ) . ')';
	}
	asort( $provinces );
	?>
	<h2 id="github">دریافت خودکار داده از گیت‌هاب (ساده‌ترین راه)</h2>
	<p class="description">شاخهٔ فعال داده: <code><?php echo esc_html( sa_county_data_ref() ); ?></code> — ابتدا پیش‌نمایش را بررسی کنید؛ برای حفظ ویرایش‌های قبلی، «بازنویسی خانه‌های پرشده» را خاموش نگه دارید.</p>
	<p class="description">استان را انتخاب کنید؛ داده‌ها مستقیم از مخزن خوانده و روی نوشته‌های همان استان نوشته می‌شوند. نیازی به کپی و چسباندن نیست. (از همان توکنی استفاده می‌شود که برای به‌روزرسانی قالب تنظیم کرده‌اید.)</p>
	<form method="post">
		<?php wp_nonce_field( 'sa_county_github' ); ?>
		<select name="sa_county_gh_province" style="min-width:260px">
			<?php foreach ( $provinces as $sa_slug => $sa_label ) : ?>
				<option value="<?php echo esc_attr( $sa_slug ); ?>" <?php selected( $gh_prov, $sa_slug ); ?>><?php echo esc_html( $sa_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<label style="margin-inline-start:12px"><input type="checkbox" name="sa_county_gh_dry" value="1" checked> فقط پیش‌نمایش</label>
		<label style="margin-inline-start:12px"><input type="checkbox" name="sa_county_gh_force" value="1"> بازنویسی خانه‌های پرشده</label>
		<button class="button button-primary" style="margin-inline-start:12px">دریافت و اعمال</button>
	</form>
	<?php
	if ( $gh_report ) {
		sa_county_import_report( $gh_report, $gh_dry );
	}
	?>
	<hr>
	<h2 id="import">ورود دستی (چسباندن JSON)</h2>
	<p class="description">
		خروجی <code>content/data/export/&lt;province&gt;.meta.json</code> را این‌جا بچسبانید.
		به‌صورت پیش‌فرض فقط خانه‌های <strong>خالی</strong> پر می‌شوند؛ هر چیزی که دست انسان نوشته دست‌نخورده می‌ماند.
	</p>
	<form method="post">
		<?php wp_nonce_field( 'sa_county_import' ); ?>
		<textarea name="sa_county_payload" rows="10" class="widefat" dir="ltr" placeholder='{"dena":{"sa_cty_center":"سی‌سخت","sa_cty_population":"51000"}}'></textarea>
		<p>
			<label><input type="checkbox" name="sa_county_dry" value="1" checked> فقط پیش‌نمایش (چیزی ذخیره نشود)</label>
			&nbsp;&nbsp;
			<label><input type="checkbox" name="sa_county_force" value="1"> بازنویسی خانه‌های پرشده</label>
		</p>
		<p><button class="button button-primary">اعمال</button></p>
	</form>
	<?php
	if ( $report ) {
		sa_county_import_report( $report, $dry );
	}
}

/**
 * Print an import report table.
 *
 * @param array $report Report from sa_county_import_apply().
 * @param bool  $dry    Whether it was a preview.
 */
function sa_county_import_report( $report, $dry ) {
	echo '<div class="notice notice-' . ( $dry ? 'info' : 'success' ) . '"><p>';
	printf(
		esc_html( '%1$s خانه %2$s · %3$s خانه رد شد (از قبل پر بود) · %4$s شهرستان نوشته‌ای در سایت ندارد · %5$s نامک ناشناخته' ),
		esc_html( sa_fa_digits( $report['written'] ) ),
		esc_html( $dry ? 'آمادهٔ نوشتن است' : 'ذخیره شد' ),
		esc_html( sa_fa_digits( $report['skipped'] ) ),
		esc_html( sa_fa_digits( count( $report['missing'] ) ) ),
		esc_html( sa_fa_digits( count( $report['unknown'] ) ) )
	);
	echo '</p></div>';

	if ( $dry ) {
		echo '<div class="notice notice-warning"><p><strong>این فقط پیش‌نمایش بود؛ هیچ‌چیز ذخیره نشد.</strong> تیک «فقط پیش‌نمایش» را بردارید و دوباره دکمه را بزنید.</p></div>';
	} elseif ( 0 === (int) $report['written'] && $report['skipped'] > 0 ) {
		echo '<div class="notice notice-warning"><p>هیچ خانه‌ای نوشته نشد چون همهٔ خانه‌ها <strong>از قبل پر بودند</strong>. اگر می‌خواهید مقدارهای تازه جایگزین شوند، تیک «بازنویسی خانه‌های پرشده» را بزنید.</p></div>';
	} elseif ( 0 === (int) $report['written'] ) {
		echo '<div class="notice notice-warning"><p>هیچ خانه‌ای نوشته نشد. جدول زیر و فهرست‌های بالا نشان می‌دهند چرا.</p></div>';
	}
	if ( ! empty( $report['failed'] ) ) {
		echo '<div class="notice notice-error"><p><strong>ذخیره‌نشده (پایگاه داده مقدار را نپذیرفت):</strong> ' . esc_html( implode( '، ', $report['failed'] ) ) . '</p></div>';
	}
	if ( $report['missing'] ) {
		echo '<p><strong>نوشته ندارند (' . esc_html( sa_fa_digits( count( $report['missing'] ) ) ) . '):</strong> ' . esc_html( implode( '، ', $report['missing'] ) ) . ' — با دکمهٔ «ساخت پیش‌نویس‌های جاافتاده» بسازید، بعد دوباره همین دکمه را بزنید.</p>';
	}
	if ( $report['unknown'] ) {
		echo '<p><strong>نامک ناشناخته:</strong> ' . esc_html( implode( '، ', $report['unknown'] ) ) . '</p>';
	}
	if ( empty( $report['rows'] ) ) {
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>شهرستان</th><th>وضعیت</th><th>' . ( $dry ? 'آمادهٔ نوشتن' : 'نوشته شد' ) . '</th><th>رد شد (پر بود)</th></tr></thead><tbody>';
	foreach ( $report['rows'] as $row ) {
		$title = isset( $row['title'] ) && '' !== $row['title'] ? $row['title'] : $row['slug'];
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $row['post_id'] ) ) . '">' . esc_html( $title ) . '</a> <code>' . esc_html( $row['slug'] ) . '</code></td>';
		echo '<td>' . esc_html( isset( $row['status'] ) ? $row['status'] : '' ) . '</td>';
		echo '<td>' . esc_html( $row['written'] ? implode( '، ', $row['written'] ) : '—' ) . '</td>';
		echo '<td>' . esc_html( $row['skipped'] ? implode( '، ', $row['skipped'] ) : '—' ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * WP-CLI: wp sa-county import <file> [--force] [--dry-run]
 *
 * @param array $args       Positional args.
 * @param array $assoc_args Flags.
 */
function sa_county_cli_import( $args, $assoc_args ) {
	$file = isset( $args[0] ) ? $args[0] : '';
	if ( ! $file || ! is_readable( $file ) ) {
		WP_CLI::error( 'فایل خوانده نشد: ' . $file );
	}
	$payload = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $payload ) ) {
		WP_CLI::error( 'JSON نامعتبر: ' . json_last_error_msg() );
	}
	$report = sa_county_import_apply(
		$payload,
		! empty( $assoc_args['force'] ),
		! empty( $assoc_args['dry-run'] )
	);
	foreach ( $report['rows'] as $row ) {
		WP_CLI::log( sprintf( '%-24s +%d  ~%d', $row['slug'], count( $row['written'] ), count( $row['skipped'] ) ) );
	}
	WP_CLI::success(
		sprintf(
			'%d field(s) written, %d skipped, %d county post(s) missing, %d unknown slug(s).',
			$report['written'],
			$report['skipped'],
			count( $report['missing'] ),
			count( $report['unknown'] )
		)
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'sa-county import', 'sa_county_cli_import' );
}
