<?php
/**
 * سرزمین آریان → سلامت محتوا (v2.1.0)
 *
 * گزارش زنده‌ی چیزهایی که یک‌بار پروژه را زمین زدند، تا دوباره بی‌سروصدا برنگردند:
 * لینک داخلی به صفحه‌ی منتشرنشده، تراکم لینک بیرونی، تمرکز روی یک منبع،
 * H1 داخل بدنه، و موجودیت‌های آماده‌ی انتشار که هنوز پیش‌نویس‌اند.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت زیرمنو.
 */
function sa_health_menu() {
	add_submenu_page(
		'sarzaminaryan',
		'سلامت محتوا',
		'— سلامت محتوا',
		'edit_posts',
		'sa-content-health',
		'sa_health_page'
	);
}
add_action( 'admin_menu', 'sa_health_menu', 20 );

/**
 * نگاشت پیشوند نشانی → نوع محتوا.
 *
 * @return array<string,string>
 */
function sa_health_slug_bases() {
	$map = array();
	foreach ( sa_entity_types() as $type ) {
		$obj = get_post_type_object( $type );
		if ( ! $obj ) {
			continue;
		}
		$base = isset( $obj->rewrite['slug'] ) && $obj->rewrite['slug'] ? $obj->rewrite['slug'] : $type;
		$map[ $base ] = $type;
	}
	return $map;
}

/**
 * نامک‌های منتشرشده‌ی هر نوع محتوا (با کش ۱۲ ساعته).
 *
 * @return array<string,array<string,true>>
 */
function sa_health_published_map() {
	$map = get_transient( 'sa_health_pubmap' );
	if ( is_array( $map ) ) {
		return $map;
	}

	$types = array_values( array_filter( array_map( 'strval', (array) sa_entity_types() ) ) );
	$map   = array();
	foreach ( $types as $t ) {
		$map[ $t ] = array();
	}
	if ( ! $types ) {
		return $map;
	}

	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_type, post_name FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_name <> '' AND post_type IN ({$placeholders})",
			$types
		)
	);
	// phpcs:enable
	foreach ( (array) $rows as $r ) {
		if ( isset( $map[ $r->post_type ] ) ) {
			$map[ $r->post_type ][ $r->post_name ] = true;
		}
	}

	set_transient( 'sa_health_pubmap', $map, 12 * HOUR_IN_SECONDS );
	return $map;
}

/**
 * پاک‌کردن کش با هر تغییر وضعیت.
 */
function sa_health_flush() {
	delete_transient( 'sa_health_pubmap' );
}
add_action( 'transition_post_status', 'sa_health_flush' );
add_action( 'deleted_post', 'sa_health_flush' );

/**
 * تحلیل یک نوشته برای گزارش سلامت محتوا (فقط‌خواندنی).
 *
 * @param int                               $pid     شناسهٔ نوشته.
 * @param string                            $brx     الگوی regex پیشوندهای مسیر.
 * @param array<string,string>              $bases   نگاشت پیشوند → نوع محتوا.
 * @param array<string,array<string,true>>  $map     نامک‌های منتشرشده به تفکیک نوع.
 * @param string                            $home    میزبان سایت.
 * @param array<string,int>                 $dead    (مرجع) شمارش لینک مرده.
 * @param array<string,int>                 $domains (مرجع) شمارش دامنه‌های بیرونی.
 * @return array<string,mixed>
 */
function sa_health_scan_post( $pid, $brx, $bases, $map, $home, &$dead, &$domains ) {
	$content = (string) get_post_field( 'post_content', $pid );
	$ext     = 0;
	$int     = 0;
	$seen    = array();
	if ( preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $m ) ) {
		foreach ( $m[1] as $href ) {
			if ( function_exists( 'sa_citation_is_external' ) && sa_citation_is_external( $href ) ) {
				++$ext;
				$h             = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) ) );
				$domains[ $h ] = isset( $domains[ $h ] ) ? $domains[ $h ] + 1 : 1;
				$seen[ $href ] = true;
			} elseif ( '' !== $href && '#' !== $href[0] ) {
				++$int;
			}
		}
	}

	$post_dead = 0;
	if ( $brx && preg_match_all( '#href=["\'](?:https?://' . preg_quote( $home, '#' ) . ')?/(' . $brx . ')/([^/"\'?\#]+)/?["\']#i', $content, $mm, PREG_SET_ORDER ) ) {
		foreach ( $mm as $hit ) {
			$cpt  = isset( $bases[ $hit[1] ] ) ? $bases[ $hit[1] ] : '';
			$slug = rawurldecode( $hit[2] );
			if ( $cpt && empty( $map[ $cpt ][ $slug ] ) ) {
				++$post_dead;
				$k          = $hit[1] . '/' . $slug;
				$dead[ $k ] = isset( $dead[ $k ] ) ? $dead[ $k ] + 1 : 1;
			}
		}
	}

	return array(
		'id'     => (int) $pid,
		'ext'    => $ext,
		'int'    => $int,
		'uniq'   => count( $seen ),
		'h1'     => (bool) preg_match( '/<h1[\s>]/i', $content ),
		'dead'   => $post_dead,
		'status' => get_post_status( $pid ),
	);
}

/**
 * تحلیل کل مجموعه (با کش ۱۵ دقیقه‌ای).
 *
 * @param bool $force نادیده‌گرفتن کش.
 * @return array
 */
function sa_health_scan( $force = false ) {
	if ( ! $force ) {
		$cached = get_transient( 'sa_health_scan' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$types = (array) sa_entity_types();
	$map   = sa_health_published_map();
	$bases = sa_health_slug_bases();
	$brx   = $bases ? implode( '|', array_map( 'preg_quote', array_keys( $bases ) ) ) : '';
	$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

	// صفحه‌بندی‌شده اسکن می‌شود تا سقفِ پیشینِ ۵۰۰ نوشته (که با ۵۴۱ موجودیت
	// منتشرشده دور زده می‌شد) حذف شود؛ سقف ایمنی فقط برای جلوگیری از حلقه‌ی
	// بی‌پایان روی نصب‌های غول‌آسا است.
	$per_page    = (int) apply_filters( 'sa_health_per_page', 200 );
	$per_page    = $per_page > 0 ? $per_page : 200;
	$max_pages   = (int) apply_filters( 'sa_health_max_pages', 25 ); // سقف ایمنی: ۲۵ × ۲۰۰ = ۵۰۰۰ نوشته.
	$max_pages   = $max_pages > 0 ? $max_pages : 25;
	$page        = 1;
	$total_pages = 1;

	$rows   = array();
	$dead   = array();
	$ready  = array();
	$totals = array(
		'posts'     => 0,
		'ext'       => 0,
		'int'       => 0,
		'uniq'      => 0,
		'h1'        => 0,
		'dead'      => 0,
		'pages'     => 0,
		'per_page'  => $per_page,
		'truncated' => false,
	);
	$domains = array();

	do {
		$q = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'draft', 'pending' ),
				'posts_per_page'         => $per_page,
				'paged'                  => $page,
				'no_found_rows'          => false,
				'fields'                 => 'ids',
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
			)
		);

		foreach ( $q->posts as $pid ) {
			$row = sa_health_scan_post( (int) $pid, $brx, $bases, $map, $home, $dead, $domains );

			$totals['ext']  += $row['ext'];
			$totals['int']  += $row['int'];
			$totals['uniq'] += $row['uniq'];
			$totals['dead'] += $row['dead'];
			++$totals['posts'];
			if ( $row['h1'] ) {
				++$totals['h1'];
			}

			if ( 'publish' !== $row['status'] ) {
				list( $blocking ) = sa_gate_split( sa_gate_missing( (int) $pid, get_post_type( (int) $pid ), null ) );
				if ( empty( $blocking ) ) {
					$ready[] = (int) $pid;
				}
			}

			$rows[] = $row;
		}

		++$totals['pages'];
		$total_pages = (int) $q->max_num_pages;
		++$page;
	} while ( $page <= $total_pages && $page <= $max_pages );

	$totals['truncated'] = $total_pages > $max_pages;

	arsort( $domains );
	arsort( $dead );
	usort(
		$rows,
		function ( $a, $b ) {
			return $b['ext'] - $a['ext'];
		}
	);

	$out = array(
		'generated' => time(),
		'totals'    => $totals,
		'domains'   => $domains,
		'dead'      => $dead,
		'ready'     => $ready,
		'rows'      => $rows,
	);
	set_transient( 'sa_health_scan', $out, 15 * MINUTE_IN_SECONDS );
	return $out;
}

/**
 * صفحه‌ی گزارش.
 */
function sa_health_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.' );
	}

	$force = isset( $_GET['refresh'] ) && check_admin_referer( 'sa_health_refresh' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$scan  = sa_health_scan( $force );
	$t     = $scan['totals'];
	$fa    = 'sa_fa_digits';

	echo '<div class="wrap sa-health" dir="rtl">';
	echo '<h1>سلامت محتوا</h1>';
	echo '<p>گزارش زنده از وضعیت کل محتوا. ارقام «لینک بیرونی» مربوط به متن ذخیره‌شده در دیتابیس است؛ آنچه بازدیدکننده می‌بیند، پس از پانویس‌سازی '
		. ( sa_citations_enabled() ? '<strong>فعال</strong>' : '<strong>غیرفعال</strong>' )
		. ' است.</p>';
	echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=sa-content-health&refresh=1' ), 'sa_health_refresh' ) ) . '">به‌روزرسانی گزارش</a> '
		. '<span class="description">آخرین محاسبه: ' . esc_html( sa_fa_digits( (string) human_time_diff( (int) $scan['generated'] ) ) ) . ' پیش</span></p>';

	/* --- کارت‌های خلاصه --- */
	$uniq  = max( 1, (int) $t['uniq'] );
	$cards = array(
		array( 'نوشته‌های بررسی‌شده', $fa( (string) $t['posts'] ), '' ),
		array( 'لینک بیرونی در متن', $fa( (string) $t['ext'] ), $t['ext'] > $t['int'] ? 'warn' : 'ok' ),
		array( 'منابع یکتا', $fa( (string) $t['uniq'] ), '' ),
		array( 'لینک داخلی', $fa( (string) $t['int'] ), $t['int'] < $t['ext'] ? 'warn' : 'ok' ),
		array( 'H1 داخل بدنه', $fa( (string) $t['h1'] ), $t['h1'] ? 'warn' : 'ok' ),
		array( 'لینک به صفحه‌ی منتشرنشده', $fa( (string) $t['dead'] ), $t['dead'] ? 'bad' : 'ok' ),
	);
	echo '<div class="sa-health__cards">';
	foreach ( $cards as $c ) {
		echo '<div class="sa-health__card sa-health__card--' . esc_attr( $c[2] ? $c[2] : 'neutral' ) . '">'
			. '<b>' . esc_html( $c[1] ) . '</b><span>' . esc_html( $c[0] ) . '</span></div>';
	}
	echo '</div>';

	if ( ! empty( $t['truncated'] ) ) {
		echo '<div class="notice notice-warning inline"><p>اسکن روی سقف ایمنی متوقف شد؛ '
			. 'ممکن است بخشی از محتوا در این گزارش نیامده باشد. برای مجموعه‌های بزرگ‌تر، '
			. 'با فیلتر <code>sa_health_max_pages</code> (و در صورت نیاز <code>sa_health_per_page</code>) سقف را بالا ببرید.</p></div>';
	}
	echo '<p class="description">اسکن‌شده: ' . esc_html( $fa( (string) $t['posts'] ) ) . ' نوشته در '
		. esc_html( $fa( (string) $t['pages'] ) ) . ' صفحه ('
		. esc_html( $fa( (string) ( isset( $t['per_page'] ) ? $t['per_page'] : 200 ) ) )
		. 'تایی، صفحه‌بندی‌شده).</p>';

	/* --- آماده‌ی انتشار --- */
	echo '<h2>پیش‌نویس‌هایی که هیچ مانع انتشاری ندارند</h2>';
	if ( empty( $scan['ready'] ) ) {
		echo '<p>موردی نیست.</p>';
	} else {
		echo '<p><strong>' . esc_html( $fa( (string) count( $scan['ready'] ) ) ) . '</strong> نوشته هر شش شرط انتشار را دارد و هنوز پیش‌نویس است. '
			. 'صفحه‌ی خوبِ منتشرشده از صفحه‌ی عالیِ پیش‌نویس بهتر است.</p><ul style="list-style:disc;margin-inline-start:1.5em">';
		foreach ( array_slice( $scan['ready'], 0, 40 ) as $pid ) {
			echo '<li><a href="' . esc_url( (string) get_edit_post_link( $pid ) ) . '">' . esc_html( (string) get_the_title( $pid ) ) . '</a> '
				. '<span class="description">(' . esc_html( (string) get_post_type( $pid ) ) . ')</span></li>';
		}
		echo '</ul>';
	}

	/* --- لینک مرده --- */
	echo '<h2>لینک داخلی به صفحه‌ی منتشرنشده</h2>';
	if ( empty( $scan['dead'] ) ) {
		echo '<p>موردی نیست. ✓</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>مقصد</th><th>چند بار لینک شده</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $scan['dead'] as $target => $count ) {
			if ( ++$i > 50 ) {
				break;
			}
			echo '<tr><td><code>/' . esc_html( (string) $target ) . '/</code></td><td>' . esc_html( $fa( (string) $count ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">با انتشار صفحه‌ی مقصد، این لینک‌ها خودبه‌خود سالم می‌شوند.</p>';
	}

	/* --- تمرکز منبع --- */
	echo '<h2>منابع، به ترتیب تکرار</h2>';
	if ( empty( $scan['domains'] ) ) {
		echo '<p>لینک بیرونی‌ای نیست.</p>';
	} else {
		$top   = key( $scan['domains'] );
		$share = $t['ext'] ? ( reset( $scan['domains'] ) / $t['ext'] ) * 100 : 0;
		if ( $share > 35 ) {
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html( $fa( (string) round( $share ) ) ) . '٪</strong> از ارجاع‌ها به <code>'
				. esc_html( (string) $top ) . '</code> است. بالای ۳۵٪ یعنی محتوا مشتق‌شده به نظر می‌رسد؛ هیچ دلیلی ندارد بازنویسی یک منبع، بالاتر از خودش رتبه بگیرد.</p></div>';
		}
		echo '<table class="widefat striped"><thead><tr><th>دامنه</th><th>تعداد ارجاع</th><th>سهم</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $scan['domains'] as $d => $c ) {
			if ( ++$i > 15 ) {
				break;
			}
			echo '<tr><td>' . esc_html( (string) $d ) . '</td><td>' . esc_html( $fa( (string) $c ) ) . '</td><td>'
				. esc_html( $fa( (string) round( $t['ext'] ? ( $c / $t['ext'] ) * 100 : 0, 1 ) ) ) . '٪</td></tr>';
		}
		echo '</tbody></table>';
	}

	/* --- بدترین صفحه‌ها --- */
	echo '<h2>بیشترین لینک بیرونی در متن</h2>';
	echo '<table class="widefat striped"><thead><tr><th>نوشته</th><th>بیرونی</th><th>یکتا</th><th>داخلی</th><th>H1 در بدنه</th><th>وضعیت</th></tr></thead><tbody>';
	foreach ( array_slice( $scan['rows'], 0, 25 ) as $r ) {
		echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $r['id'] ) ) . '">' . esc_html( (string) get_the_title( $r['id'] ) ) . '</a></td>'
			. '<td>' . esc_html( $fa( (string) $r['ext'] ) ) . '</td>'
			. '<td>' . esc_html( $fa( (string) $r['uniq'] ) ) . '</td>'
			. '<td>' . esc_html( $fa( (string) $r['int'] ) ) . '</td>'
			. '<td>' . ( $r['h1'] ? 'دارد' : '—' ) . '</td>'
			. '<td>' . esc_html( 'publish' === $r['status'] ? 'منتشرشده' : 'پیش‌نویس' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	/* --- تعمیر مکانیکی --- */
	sa_repair_section();
	sa_selflink_section();

	/*
	 * بخش‌های «پاک‌سازی» در فایلِ جداگانه‌اند؛ اگر قالب آن را بار نکرده باشد
	 * (اجرای مستقلِ همین فایل در آزمون‌ها)، بی‌صدا رد می‌شوند تا صفحهٔ سلامت
	 * هرگز با خطای مرگبار بالا نیاید.
	 */
	$sa_cleanup_module = defined( 'SA_CHILD_DIR' ) ? SA_CHILD_DIR . 'inc/site-cleanup.php' : '';
	if ( '' !== $sa_cleanup_module && is_readable( $sa_cleanup_module ) && ! function_exists( 'sa_city_cleanup_section' ) ) {
		require_once $sa_cleanup_module;
	}
	if ( function_exists( 'sa_city_cleanup_section' ) ) {
		sa_city_cleanup_section();
	}
	if ( function_exists( 'sa_term_rename_section' ) ) {
		sa_term_rename_section();
	}

	echo '<style>
	.sa-health__cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:18px 0 26px}
	.sa-health__card{background:#fff;border:1px solid #dcdcde;border-inline-start-width:4px;border-radius:6px;padding:14px 16px}
	.sa-health__card b{display:block;font-size:26px;line-height:1.2}
	.sa-health__card span{color:#646970;font-size:12px}
	.sa-health__card--ok{border-inline-start-color:#00a32a}
	.sa-health__card--warn{border-inline-start-color:#dba617}
	.sa-health__card--bad{border-inline-start-color:#d63638}
	.sa-health__card--neutral{border-inline-start-color:#2271b1}
	</style>';

	echo '</div>';
}
