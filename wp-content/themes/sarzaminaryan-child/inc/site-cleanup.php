<?php
/**
 * پاک‌سازی صفحه‌های بی‌تکلیفِ شهرستان + یکسان‌سازی نامِ «دیدنی‌ها» (v2.11.40).
 *
 * دو کارِ مکانیکی و بازگشت‌پذیر، هر دو با «پیش‌نمایش» و «اعمال» در
 * سرزمین آریان → سلامت محتوا:
 *
 *   ۱) صفحه‌های شهرستانِ خالی یا بدونِ عنوان (بقایای ساختِ اولیهٔ CPT) به سطلِ زباله
 *      می‌روند. هیچ صفحه‌ای که متن دارد حذف نمی‌شود؛ سطلِ زباله هم بازگردانی‌پذیر است.
 *   ۲) نامِ قدیمیِ «نمای برتر» در متنِ ذخیره‌شدهٔ صفحه‌های منتشرشده با «دیدنی»
 *      جایگزین می‌شود (همان نامی که در ۲.۱۱.۴۰ روی کلِ سایت نشست).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نامِ قدیمی و نامِ تازهٔ موجودیتِ جاذبه (فقط برای یکسان‌سازی متن).
 *
 * @return array<string,string>
 */
function sa_cleanup_term_map() {
	return array(
		'نمای برترها' => 'دیدنی‌ها',
		'نماهای برتر' => 'دیدنی‌ها',
		'نمای برتر'   => 'دیدنی',
	);
}

/**
 * بزرگ‌ترین دسته‌ی هر اجرا.
 *
 * @return int
 */
function sa_cleanup_batch_size() {
	$size = (int) apply_filters( 'sa_cleanup_batch_size', 100 );
	return $size > 0 ? $size : 100;
}

/**
 * ردیف‌های رجیستریِ شهرستان‌ها: slug => name.
 *
 * اول توابعِ قالب (`sa_counties`) و در نبودشان خودِ فایلِ داده خوانده می‌شود؛
 * محیطِ آزمون هم با همین تابع کار می‌کند.
 *
 * @return array<string,string>
 */
function sa_cleanup_county_index() {
	static $index = null;
	if ( null !== $index ) {
		return $index;
	}
	$index = array();
	if ( function_exists( 'sa_counties' ) ) {
		foreach ( (array) sa_counties() as $row ) {
			if ( ! empty( $row['slug'] ) ) {
				$index[ (string) $row['slug'] ] = isset( $row['name'] ) ? (string) $row['name'] : '';
			}
		}
		return $index;
	}
	$candidates = array();
	if ( defined( 'SA_CHILD_DIR' ) ) {
		$candidates[] = SA_CHILD_DIR . 'data/counties.php';
	}
	if ( function_exists( 'get_stylesheet_directory' ) ) {
		$candidates[] = trailingslashit( get_stylesheet_directory() ) . 'data/counties.php';
	}
	$candidates[] = dirname( __DIR__ ) . '/data/counties.php';
	foreach ( $candidates as $file ) {
		if ( '' === $file || ! is_readable( $file ) ) {
			continue;
		}
		$rows = (array) require $file;
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && ! empty( $row['slug'] ) ) {
				$index[ (string) $row['slug'] ] = isset( $row['name'] ) ? (string) $row['name'] : '';
			}
		}
		break;
	}
	return $index;
}

/**
 * فاصلهٔ لِوِنشتاین (با بازگشتِ دستی اگر تابع در دسترس نباشد).
 *
 * @param string $a رشتهٔ نخست.
 * @param string $b رشتهٔ دوم.
 * @return int
 */
function sa_cleanup_slug_distance( $a, $b ) {
	if ( function_exists( 'levenshtein' ) ) {
		return (int) levenshtein( (string) $a, (string) $b );
	}
	$a = (string) $a;
	$b = (string) $b;
	$prev = range( 0, strlen( $b ) );
	for ( $i = 1; $i <= strlen( $a ); $i++ ) {
		$cur = array( $i );
		for ( $j = 1; $j <= strlen( $b ); $j++ ) {
			$cur[ $j ] = min( $prev[ $j ] + 1, $cur[ $j - 1 ] + 1, $prev[ $j - 1 ] + ( $a[ $i - 1 ] === $b[ $j - 1 ] ? 0 : 1 ) );
		}
		$prev = $cur;
	}
	return (int) $prev[ count( $prev ) - 1 ];
}

/**
 * صفح‌های تکراریِ شناخته‌شدهٔ سایت (فهرستِ مستندِ ۱۴۰۵-۰۷-۱۵).
 *
 * این شش مورد همان‌هایی‌اند که در «شهرستان‌ها» بی‌متن یا در سطلِ زباله دیده شدند؛
 * تصمیم برای هرکدام بر پایهٔ رجیستریِ رسمی ثبت شده است.
 *
 * @return array<string,array{to:string,why:string}>
 */
function sa_cleanup_known_duplicates() {
	return array(
		'isfahan'           => array( 'to' => 'isfahan-city', 'why' => 'همان اصفهان؛ صفحهٔ منتشرشده با نامکِ کامل' ),
		'galikesh'          => array( 'to' => 'galikash', 'why' => 'آوانگاریِ دیگرِ گالیکش؛ صفحهٔ منتشرشده' ),
		'kabudarahang-city' => array( 'to' => 'kabutarahang', 'why' => 'همان کبودرآهنگ؛ صفحهٔ منتشرشده' ),
		'shahdad'           => array( 'to' => '', 'why' => 'شهداد شهرستان نیست (شهرِ شهرستان کرمان) — بدونِ جانشین' ),
		'bam-safiabad'      => array( 'to' => 'bam-and-safiabad', 'why' => 'نامکِ قدیمیِ «بام و صفی‌آباد» (خراسان شمالی)' ),
		'maneh-samalqan'    => array( 'to' => 'samalqan', 'why' => 'نامکِ قدیمیِ «سملقان» (رجیستریِ ۲.۱۱.۳۹ هم‌راستا شد)' ),
	);
}

/**
 * جانشینِ رجیستری برای نامکِ یک صفحهٔ شهرستان.
 *
 * نردبانِ تشخیص (بدونِ حدس): ۱) فهرستِ مستندِ تکراری‌ها. ۲) ردیفِ رجیستری با همان نامک.
 * ۳) «<slug>-city» ردیفِ رجیستری است. ۴) حذفِ «-city» از نامک ردیفِ رجیستری می‌شود.
 * ۵) فاصلهٔ لِوِنشتاینِ ۱ با یک ردیف (غلطِ آوانگاری). حدِ ۱ عمدی است تا «shahdad» به
 * «shahrud» وصل نشود.
 *
 * @param string $slug نامکِ صفحه.
 * @return array{slug:string,name:string,via:string}
 */
function sa_city_cleanup_twin( $slug ) {
	$slug  = strtolower( trim( (string) $slug ) );
	$index = sa_cleanup_county_index();
	$known = sa_cleanup_known_duplicates();
	if ( '' === $slug ) {
		return array( 'slug' => '', 'name' => '', 'via' => 'none' );
	}
	if ( isset( $known[ $slug ] ) ) {
		$to = (string) $known[ $slug ]['to'];
		return array( 'slug' => $to, 'name' => isset( $index[ $to ] ) ? $index[ $to ] : '', 'via' => ( '' === $to ) ? 'none' : 'known' );
	}
	if ( isset( $index[ $slug ] ) ) {
		return array( 'slug' => $slug, 'name' => $index[ $slug ], 'via' => 'self' );
	}
	if ( isset( $index[ $slug . '-city' ] ) ) {
		return array( 'slug' => $slug . '-city', 'name' => $index[ $slug . '-city' ], 'via' => 'city-suffix' );
	}
	if ( '-city' === substr( $slug, -5 ) ) {
		$bare = substr( $slug, 0, -5 );
		if ( isset( $index[ $bare ] ) ) {
			return array( 'slug' => $bare, 'name' => $index[ $bare ], 'via' => 'city-suffix' );
		}
	}
	foreach ( $index as $row_slug => $row_name ) {
		if ( sa_cleanup_slug_distance( $slug, $row_slug ) <= 1 ) {
			return array( 'slug' => $row_slug, 'name' => $row_name, 'via' => 'typo' );
		}
	}
	return array( 'slug' => '', 'name' => '', 'via' => 'none' );
}

/**
 * وضعیتِ انتشارِ صفحهٔ جانشین (برای اطمینان از کارکردِ ۳۰۱).
 *
 * @param string $slug نامکِ جانشین.
 * @return string publish|draft|missing|unknown
 */
function sa_cleanup_twin_status( $slug ) {
	if ( '' === (string) $slug || ! function_exists( 'get_page_by_path' ) ) {
		return 'unknown';
	}
	$post = get_page_by_path( (string) $slug, OBJECT, 'city' );
	if ( ! $post ) {
		return 'missing';
	}
	return (string) $post->post_status;
}

/**
 * صفحه‌های شهرستانِ بی‌تکلیف: متنِ خالی یا عنوانِ خالی.
 *
 * @param int $limit صفر یعنی همه.
 * @return array<int,array<string,mixed>>
 */
function sa_city_cleanup_candidates( $limit = 0 ) {
	$posts = get_posts(
		array(
			'post_type'      => 'city',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$out = array();
	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$text   = trim( wp_strip_all_tags( (string) $post->post_content ) );
		$title  = trim( (string) $post->post_title );
		$reason = array();
		if ( '' === $text ) {
			$reason[] = 'content';
		}
		if ( '' === $title ) {
			$reason[] = 'title';
		}
		if ( ! $reason ) {
			continue;
		}
		$twin = sa_city_cleanup_twin( (string) $post->post_name );
		$out[] = array(
			'ID'       => (int) $post->ID,
			'title'    => $title,
			'slug'     => (string) $post->post_name,
			'status'   => (string) $post->post_status,
			'reason'   => $reason,
			'chars'    => strlen( $text ),
			'twin'     => $twin,
			'twin_status' => sa_cleanup_twin_status( $twin['slug'] ),
			'redirect' => function_exists( 'sa_redirect_target_for_path' ) ? (string) sa_redirect_target_for_path( '/city/' . $post->post_name . '/' ) : '',
		);
	}

	return $out;
}

/**
 * صفحه‌های منتشرشده‌ای که متنشان نامِ قدیمیِ «نمای برتر» را دارد.
 *
 * @param int $limit صفر یعنی همه.
 * @return array<int,array<string,mixed>>
 */
function sa_cleanup_term_hits( $limit = 0 ) {
	$types = (array) apply_filters(
		'sa_cleanup_post_types',
		array( 'post', 'page', 'province', 'city', 'attraction', 'travel_route', 'local_food', 'souvenir' )
	);
	$posts = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => array( 'publish' ),
			'posts_per_page' => $limit > 0 ? $limit : -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);

	$map = sa_cleanup_term_map();
	$out = array();
	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}
		$content = (string) $post->post_content;
		$hits    = 0;
		foreach ( array_keys( $map ) as $needle ) {
			$hits += substr_count( $content, $needle );
		}
		if ( $hits < 1 ) {
			continue;
		}
		$out[] = array(
			'ID'    => (int) $post->ID,
			'title' => trim( (string) $post->post_title ),
			'type'  => (string) $post->post_type,
			'hits'  => $hits,
		);
	}

	return $out;
}

/**
 * جایگزینی نامِ قدیمی در یک متن (قابل‌آزمون و بی‌طرف نسبت به دیتابیس).
 *
 * @param string $content متن.
 * @param int    $hits    شمارِ جایگزینی‌ها.
 * @return string
 */
function sa_cleanup_replace_terms( $content, &$hits = null ) {
	$hits    = 0;
	$content = (string) $content;
	foreach ( sa_cleanup_term_map() as $old => $new ) {
		$count = substr_count( $content, $old );
		if ( $count > 0 ) {
			$content = str_replace( $old, $new, $content );
			$hits   += $count;
		}
	}
	return $content;
}

/**
 * اجرای پاک‌سازی.
 *
 * @param string $mode  dry|apply.
 * @param int    $limit سقفِ هر دسته.
 * @return array<string,mixed>
 */
function sa_cleanup_run( $mode = 'dry', $limit = 0 ) {
	$mode   = ( 'apply' === $mode ) ? 'apply' : 'dry';
	$limit  = $limit > 0 ? (int) $limit : sa_cleanup_batch_size();
	$cities = array_slice( sa_city_cleanup_candidates(), 0, $limit );
	$terms  = array_slice( sa_cleanup_term_hits(), 0, $limit );

	$report = array(
		'mode'        => $mode,
		'cities'      => count( $cities ),
		'trashed'     => 0,
		'term_posts'  => count( $terms ),
		'term_hits'   => 0,
		'changed'     => 0,
		'city_sample' => array(),
		'term_sample' => array(),
		'time'        => time(),
	);

	foreach ( array_slice( $cities, 0, 5 ) as $row ) {
		$report['city_sample'][] = ( '' !== $row['title'] ? $row['title'] : '—' ) . ' (' . $row['slug'] . ')';
	}
	foreach ( $terms as $row ) {
		$report['term_hits'] += (int) $row['hits'];
	}
	foreach ( array_slice( $terms, 0, 5 ) as $row ) {
		$report['term_sample'][] = ( '' !== $row['title'] ? $row['title'] : '—' ) . ' (' . $row['type'] . ')';
	}

	if ( 'apply' === $mode ) {
		foreach ( $cities as $row ) {
			if ( function_exists( 'wp_trash_post' ) && wp_trash_post( $row['ID'] ) ) {
				$report['trashed']++;
			}
		}
		foreach ( $terms as $row ) {
			$post = get_post( $row['ID'] );
			if ( ! $post ) {
				continue;
			}
			$hits = 0;
			$new  = sa_cleanup_replace_terms( (string) $post->post_content, $hits );
			if ( $hits > 0 && $new !== (string) $post->post_content ) {
				wp_update_post( array( 'ID' => (int) $row['ID'], 'post_content' => $new ) );
				$report['changed']++;
			}
		}
	}

	update_option( 'sa_cleanup_last', $report, false );
	return $report;
}

/**
 * آخرین گزارشِ اجرا.
 *
 * @return array<string,mixed>|null
 */
function sa_cleanup_last() {
	$last = get_option( 'sa_cleanup_last' );
	return is_array( $last ) ? $last : null;
}

/**
 * هندلرِ فرم‌های پاک‌سازی (پیش‌نمایش/اعمال).
 *
 * @param string $action نامِ کنش (sa_cleanup_city|sa_cleanup_term).
 * @return array<string,mixed>|null
 */
function sa_cleanup_action( $action = 'sa_cleanup_city' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما اجازه‌ی این کار را ندارید.', 'sarzaminaryan-child' ) );
		return null;
	}
	check_admin_referer( 'sa_cleanup_run' );

	$mode   = ( isset( $_POST['sa_cleanup_mode'] ) && 'apply' === $_POST['sa_cleanup_mode'] ) ? 'apply' : 'dry';
	$action = in_array( $action, array( 'sa_cleanup_city', 'sa_cleanup_term' ), true ) ? $action : 'sa_cleanup_city';

	if ( 'sa_cleanup_city' === $action ) {
		$limit = (int) apply_filters( 'sa_cleanup_city_limit', 200 );
		return sa_cleanup_city_run( $mode, $limit > 0 ? $limit : 200 );
	}
	$limit = (int) apply_filters( 'sa_cleanup_term_limit', 200 );
	return sa_cleanup_term_run( $mode, $limit > 0 ? $limit : 200 );
}

/**
 * اجرای بخشِ اول (سطلِ زباله برای صفحه‌های خالی).
 *
 * @param string $mode  dry|apply.
 * @param int    $limit سقف.
 * @return array<string,mixed>
 */
function sa_cleanup_city_run( $mode = 'dry', $limit = 200 ) {
	$cities = array_slice( sa_city_cleanup_candidates(), 0, $limit );
	$report = array(
		'mode'        => ( 'apply' === $mode ) ? 'apply' : 'dry',
		'cities'      => count( $cities ),
		'trashed'     => 0,
		'duplicates'  => 0,
		'city_sample' => array(),
		'time'        => time(),
	);
	foreach ( $cities as $row ) {
		if ( ! empty( $row['twin']['slug'] ) && $row['slug'] !== $row['twin']['slug'] ) {
			$report['duplicates']++;
		}
	}
	foreach ( array_slice( $cities, 0, 10 ) as $row ) {
		$line = ( '' !== $row['title'] ? $row['title'] : '—' ) . ' (' . $row['slug'] . ')';
		if ( ! empty( $row['twin']['slug'] ) && $row['slug'] !== $row['twin']['slug'] ) {
			$line .= ' → ' . $row['twin']['slug'];
		}
		$report['city_sample'][] = $line;
	}
	if ( 'apply' === $report['mode'] ) {
		foreach ( $cities as $row ) {
			if ( function_exists( 'wp_trash_post' ) && wp_trash_post( $row['ID'] ) ) {
				$report['trashed']++;
			}
		}
	}
	update_option( 'sa_cleanup_city_last', $report, false );
	return $report;
}

/**
 * اجرای بخشِ دوم (جایگزینی نام در متنِ منتشرشده).
 *
 * @param string $mode  dry|apply.
 * @param int    $limit سقف.
 * @return array<string,mixed>
 */
function sa_cleanup_term_run( $mode = 'dry', $limit = 200 ) {
	$terms  = array_slice( sa_cleanup_term_hits(), 0, $limit );
	$report = array(
		'mode'        => ( 'apply' === $mode ) ? 'apply' : 'dry',
		'term_posts'  => count( $terms ),
		'term_hits'   => 0,
		'changed'     => 0,
		'term_sample' => array(),
		'time'        => time(),
	);
	foreach ( $terms as $row ) {
		$report['term_hits'] += (int) $row['hits'];
	}
	foreach ( array_slice( $terms, 0, 10 ) as $row ) {
		$report['term_sample'][] = ( '' !== $row['title'] ? $row['title'] : '—' ) . ' (' . $row['type'] . ')';
	}
	if ( 'apply' === $report['mode'] ) {
		foreach ( $terms as $row ) {
			$post = get_post( $row['ID'] );
			if ( ! $post ) {
				continue;
			}
			$hits = 0;
			$new  = sa_cleanup_replace_terms( (string) $post->post_content, $hits );
			if ( $hits > 0 && $new !== (string) $post->post_content ) {
				wp_update_post( array( 'ID' => (int) $row['ID'], 'post_content' => $new ) );
				$report['changed']++;
			}
		}
	}
	update_option( 'sa_cleanup_term_last', $report, false );
	return $report;
}

/**
 * مدیریت دکمه‌های بخشِ صفحه‌های شهرستان.
 */
function sa_cleanup_city_handle() {
	sa_cleanup_action( 'sa_cleanup_city' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'sa-content-health', 'sa_cleanup' => 'city' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sa_cleanup_city', 'sa_cleanup_city_handle' );

/**
 * مدیریت دکمه‌های بخشِ یکسان‌سازی نام.
 */
function sa_cleanup_term_handle() {
	sa_cleanup_action( 'sa_cleanup_term' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'sa-content-health', 'sa_cleanup' => 'term' ), admin_url( 'admin.php' ) ) );
	exit;
}
add_action( 'admin_post_sa_cleanup_term', 'sa_cleanup_term_handle' );

/**
 * بخشِ «پاک‌سازی صفحه‌های شهرستان» در صفحهٔ سلامت محتوا.
 */
function sa_city_cleanup_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fa     = 'sa_fa_digits';
	$rows   = sa_city_cleanup_candidates( 200 );
	$count  = count( $rows );
	$last   = get_option( 'sa_cleanup_city_last' );
	$last   = is_array( $last ) ? $last : null;
	$done   = isset( $_GET['sa_cleanup'] ) ? sanitize_key( wp_unslash( $_GET['sa_cleanup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<h2>پاک‌سازی: صفحه‌های بی‌تکلیفِ شهرستان</h2>';
	echo '<p>صفحه‌هایی که <strong>متن ندارند</strong> یا <strong>عنوان‌شان خالی است</strong> (بقایای ساختِ شهرستان‌ها) نه به بازدیدکننده چیزی می‌گویند و نه باید در فهرست‌ها بمانند. '
		. 'این ابزار فقط همین‌ها را به سطلِ زباله می‌برد؛ هر صفحه‌ای که حتی یک خط متن دارد دست‌نخورده می‌ماند و هر مورد از «سطل زباله» قابل بازگردانی است.</p>';
	echo '<p>ستونِ <strong>جانشین</strong> بر پایهٔ رجیستریِ رسمیِ شهرستان‌ها پر می‌شود: اگر صفحهٔ بی‌متن نسخهٔ نامکِ قدیمیِ یک صفحهٔ منتشرشده باشد، همان صفحه این‌جا نشان داده می‌شود. '
		. 'برای سه مورد از این تکراری‌ها، تغییر مسیرِ ۳۰۱ در خودِ قالب ثبت شده است (اصفهان، گالیکش، کبودرآهنگ)؛ پس از به‌روزرسانیِ قالب، نشانیِ قدیمی به صفحهٔ درست می‌رود، حتی پیش از این‌که شما سطل‌کردن را اجرا کنید.</p>';

	if ( 'city' === $done && $last ) {
		if ( 'dry' === $last['mode'] ) {
			echo '<div class="notice notice-info inline"><p><strong>پیش‌نمایش:</strong> '
				. esc_html( $fa( (string) $last['cities'] ) ) . ' صفحهٔ خالی پیدا شد. هنوز چیزی تغییر نکرده است.</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p><strong>اعمال شد:</strong> '
				. esc_html( $fa( (string) $last['trashed'] ) ) . ' صفحه به سطلِ زباله رفت (در «برگه‌ها → سطل زباله» قابل بازگردانی است).</p></div>';
		}
		if ( ! empty( $last['city_sample'] ) ) {
			echo '<p class="description">نمونه: ' . esc_html( implode( ' | ', $last['city_sample'] ) ) . '</p>';
		}
	}

	if ( ! $count ) {
		echo '<p><strong>موردی نیست. ✓</strong></p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>صفحه</th><th>نامک</th><th>وضعیت</th><th>علت</th><th>جانشین</th></tr></thead><tbody>';
	foreach ( array_slice( $rows, 0, 50 ) as $row ) {
		$reasons = array();
		if ( in_array( 'content', $row['reason'], true ) ) {
			$reasons[] = 'متن خالی';
		}
		if ( in_array( 'title', $row['reason'], true ) ) {
			$reasons[] = 'عنوان خالی';
		}
		$twin     = isset( $row['twin'] ) && is_array( $row['twin'] ) ? $row['twin'] : array( 'slug' => '', 'name' => '', 'via' => 'none' );
		$is_dupe  = ( '' !== $twin['slug'] && $twin['slug'] !== $row['slug'] );
		echo '<tr><td>' . esc_html( '' !== $row['title'] ? $row['title'] : '—' ) . '</td><td><code>' . esc_html( $row['slug'] ) . '</code></td><td>'
			. esc_html( $row['status'] ) . '</td><td>' . esc_html( implode( '، ', $reasons ) ) . '</td><td>';
		if ( $is_dupe ) {
			$label = '' !== $twin['name'] ? $twin['name'] : $twin['slug'];
			echo '<strong>' . esc_html( $label ) . '</strong> <code>' . esc_html( $twin['slug'] ) . '</code>';
			echo '<br /><span class="description">تکراری — وضعیتِ صفحهٔ جانشین: ' . esc_html( $row['twin_status'] );
			if ( '' !== $row['redirect'] ) {
				echo ' · ۳۰۱ به <code>' . esc_html( $row['redirect'] ) . '</code>';
			} else {
				echo ' · ۳۰۱ ثبت نشده';
			}
			echo '</span>';
		} else {
			echo '<span class="description">' . esc_html( '' !== $twin['name'] ? 'ردیفِ رجیستری: ' . $twin['name'] : 'بدونِ جانشین در رجیستری — بازبینیِ دستی' ) . '</span>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	if ( $count > 50 ) {
		echo '<p class="description">و ' . esc_html( $fa( (string) ( $count - 50 ) ) ) . ' مورد دیگر.</p>';
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px">';
	wp_nonce_field( 'sa_cleanup_run' );
	echo '<input type="hidden" name="action" value="sa_cleanup_city" />';
	echo '<button class="button" name="sa_cleanup_mode" value="dry">پیش‌نمایش</button> ';
	echo '<button class="button button-primary" name="sa_cleanup_mode" value="apply" onclick="return confirm(\'این صفحه‌های خالی به سطلِ زباله می‌روند. ادامه می‌دهید؟\')">انتقال به سطلِ زباله</button>';
	echo '</form>';

	sa_cleanup_trash_section();
}

/**
 * فهرستِ صفحه‌های شهرستانِ ازپیش‌سطل‌شده با تصمیمِ هرکدام.
 *
 * حذفِ قطعی عمداً در این ابزار نیست (بازگشت‌ناپذیر است)؛ تصمیم این‌جا مستند
 * می‌شود و حذفِ همیشگی با یک کلیک از «برگه‌ها → سطل زباله» انجام می‌شود.
 */
function sa_cleanup_trash_section() {
	$posts = get_posts(
		array(
			'post_type'      => 'city',
			'post_status'    => 'trash',
			'posts_per_page' => 50,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		)
	);
	echo '<h3 style="margin-top:22px">در سطلِ زباله</h3>';
	if ( ! $posts ) {
		echo '<p class="description">صفحهٔ شهرستانی در سطلِ زباله نیست.</p>';
		return;
	}
	echo '<p class="description">این‌ها پیش‌تر سطل شده‌اند و از دیدِ بازدیدکننده پنهان‌اند. تصمیمِ هرکدام در ستونِ «تصمیم» آمده؛ حذفِ همیشگی را از «برگه‌ها → سطل زباله» انجام دهید (حذفِ قطعی از این ابزار عمداً برداشته شده است).</p>';
	echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>عنوان</th><th>نامک</th><th>جانشین</th><th>تصمیم</th></tr></thead><tbody>';
	foreach ( $posts as $post ) {
		$slug  = (string) $post->post_name;
		$clean = str_replace( '__trashed', '', $slug );
		$twin  = sa_city_cleanup_twin( $clean );
		$known = sa_cleanup_known_duplicates();
		$why   = isset( $known[ $clean ] ) ? $known[ $clean ]['why'] : 'بازبینیِ دستی لازم است';
		echo '<tr><td>' . esc_html( '' !== trim( (string) $post->post_title ) ? $post->post_title : '—' ) . '</td><td><code>' . esc_html( $slug ) . '</code></td><td>';
		if ( '' !== $twin['slug'] && $twin['slug'] !== $clean ) {
			echo '<code>' . esc_html( $twin['slug'] ) . '</code>';
		} else {
			echo '—';
		}
		echo '</td><td>' . esc_html( $why ) . ' → حذفِ همیشگی از سطلِ زباله</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * بخشِ «یکسان‌سازی نامِ دیدنی‌ها» در صفحهٔ سلامت محتوا.
 */
function sa_term_rename_section() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$fa    = 'sa_fa_digits';
	$rows  = sa_cleanup_term_hits( 200 );
	$count = count( $rows );
	$hits  = 0;
	foreach ( $rows as $row ) {
		$hits += (int) $row['hits'];
	}
	$last = get_option( 'sa_cleanup_term_last' );
	$last = is_array( $last ) ? $last : null;
	$done = isset( $_GET['sa_cleanup'] ) ? sanitize_key( wp_unslash( $_GET['sa_cleanup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	echo '<h2>یکسان‌سازی نام: «دیدنی‌ها» در متنِ صفحه‌ها</h2>';
	echo '<p>در نسخهٔ ۲.۱۱.۴۰ نامِ نمایشیِ این موجودیت از «نمای برتر» به <strong>دیدنی‌ها</strong> تغییر کرد. جای نامِ قدیمی داخلِ متنِ ذخیره‌شده‌ی صفحه‌های منتشرشده هم با همان واژهٔ تازه عوض می‌شود؛ '
		. 'فقط همین دو عبارت جابه‌جا می‌شوند و بقیهٔ متن دست‌نخورده می‌ماند. هر تغییر یک نسخهٔ بازبینی دارد.</p>';

	if ( 'term' === $done && $last ) {
		if ( 'dry' === $last['mode'] ) {
			echo '<div class="notice notice-info inline"><p><strong>پیش‌نمایش:</strong> در '
				. esc_html( $fa( (string) $last['term_posts'] ) ) . ' صفحه، ' . esc_html( $fa( (string) $last['term_hits'] ) )
				. ' نامِ قدیمی پیدا شد. هنوز چیزی تغییر نکرده است.</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p><strong>اعمال شد:</strong> '
				. esc_html( $fa( (string) $last['changed'] ) ) . ' صفحه به‌روزرسانی شد.</p></div>';
		}
		if ( ! empty( $last['term_sample'] ) ) {
			echo '<p class="description">نمونه: ' . esc_html( implode( ' | ', $last['term_sample'] ) ) . '</p>';
		}
	}

	if ( ! $count ) {
		echo '<p><strong>موردی نیست. ✓</strong></p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>صفحه</th><th>نوع</th><th>شمار</th></tr></thead><tbody>';
	foreach ( array_slice( $rows, 0, 50 ) as $row ) {
		echo '<tr><td>' . esc_html( '' !== $row['title'] ? $row['title'] : '—' ) . '</td><td>' . esc_html( $row['type'] ) . '</td><td>'
			. esc_html( $fa( (string) $row['hits'] ) ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px">';
	wp_nonce_field( 'sa_cleanup_run' );
	echo '<input type="hidden" name="action" value="sa_cleanup_term" />';
	echo '<button class="button" name="sa_cleanup_mode" value="dry">پیش‌نمایش</button> ';
	echo '<button class="button button-primary" name="sa_cleanup_mode" value="apply">جایگزینی نامِ تازه</button>';
	echo '</form>';
}
