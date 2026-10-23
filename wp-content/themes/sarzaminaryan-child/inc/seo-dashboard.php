<?php
/**
 * داشبورد سئو در پیشخوان — وضعیت، امتیاز و رتبهٔ محتوا.
 *
 * یک صفحهٔ فقط‌خواندنی در پیشخوان که سلامت سئوی محتوای منتشرشده را با یک موتور
 * امتیازدهی ۱۰۰امتیازی می‌سنجد و به‌صورت بصری (کارت‌ها، دونات توزیع، نوار میانگین
 * دسته‌ها و جدول رتبه‌بندی) نشان می‌دهد. هیچ چیزی را تغییر نمی‌دهد؛ نتیجه یک ساعت
 * در transient کش می‌شود و با ذخیرهٔ هر نوشته باطل می‌گردد.
 *
 * @package SarzaminAryan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت صفحهٔ داشبورد سئو در منوی اصلی پیشخوان.
 *
 * @return void
 */
function sa_seo_dashboard_menu() {
	add_menu_page(
		__( 'سئو سرزمین', 'sarzaminaryan-child' ),
		__( 'سئو سرزمین', 'sarzaminaryan-child' ),
		'manage_options',
		'sa-seo-dashboard',
		'sa_seo_dashboard_render',
		'dashicons-chart-area',
		3
	);
}
add_action( 'admin_menu', 'sa_seo_dashboard_menu' );

/**
 * شمارش واژه‌های متن فارسی/انگلیسی.
 *
 * @param string $text متن.
 * @return int
 */
function sa_seo_word_count( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	if ( ! preg_match_all( '/[\p{L}\p{N}]+/u', $text, $m ) ) {
		return 0;
	}
	return count( $m[0] );
}

/**
 * موتور امتیازدهی سئو (۱۰۰ امتیاز) برای یک نوشته.
 *
 * @param WP_Post $post نوشته.
 * @return array{score:int,issues:string[],checks:array}
 */
function sa_seo_score_post( $post ) {
	$score  = 0;
	$issues = array();

	$title = (string) get_the_title( $post );
	$tlen  = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
	if ( $tlen >= 30 && $tlen <= 60 ) {
		$score += 10;
	} elseif ( $tlen >= 20 && $tlen <= 80 ) {
		$score += 5;
		$issues[] = 'طول عنوان مرزی است (۳۰ تا ۶۰ نویسه ایده‌آل).';
	} else {
		$issues[] = 'طول عنوان نامناسب است (' . $tlen . ' نویسه).';
	}

	$desc = (string) ( has_excerpt( $post ) ? get_the_excerpt( $post ) : '' );
	if ( '' === $desc ) {
		$desc = (string) wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 30, '' );
	}
	$dlen = function_exists( 'mb_strlen' ) ? mb_strlen( $desc ) : strlen( $desc );
	if ( $dlen >= 70 && $dlen <= 160 ) {
		$score += 10;
	} elseif ( $dlen > 0 ) {
		$score += 5;
		$issues[] = 'توضیح متا خارج از بازهٔ ۷۰ تا ۱۶۰ نویسه است.';
	} else {
		$issues[] = 'توضیح متا خالی است.';
	}

	$slug = (string) $post->post_name;
	if ( '' !== $slug && strlen( $slug ) <= 60 && preg_match( '#^[a-z0-9\-]+$#', $slug ) ) {
		$score += 5;
	} else {
		$issues[] = 'نامک بلند یا غیراستاندارد است.';
	}

	$words = sa_seo_word_count( $post->post_content );
	if ( $words >= 600 ) {
		$score += 15;
	} elseif ( $words >= 300 ) {
		$score += 8;
		$issues[] = 'متن کوتاه است (' . $words . ' واژه)؛ زیر ۶۰۰ واژه رقابت‌پذیر نیست.';
	} else {
		$issues[] = 'متن بسیار کوتاه است (' . $words . ' واژه).';
	}

	$h1 = preg_match_all( '~<h1[\s>]~i', (string) $post->post_content );
	if ( 1 === (int) $h1 ) {
		$score += 5;
	} elseif ( 0 === (int) $h1 ) {
		$issues[] = 'سرتیتر H1 در محتوا نیست.';
	} else {
		$issues[] = 'بیش از یک H1 در محتوا هست.';
	}

	$imgs = preg_match_all( '~<img\b[^>]*>~i', (string) $post->post_content );
	$alts = preg_match_all( '~<img\b[^>]*alt=(["\'])[^\1]+?\1[^>]*>~i', (string) $post->post_content );
	if ( $imgs > 0 ) {
		$score += 5;
		if ( $alts >= $imgs ) {
			$score += 5;
		} else {
			$issues[] = 'تصاویر بدون متن جایگزین (alt) دارند.';
		}
	} else {
		$issues[] = 'تصویری در محتوا نیست.';
	}

	$internal = preg_match_all( '~<a\b[^>]*href=(["\'])' . preg_quote( home_url( '/' ), '~' ) . '~i', (string) $post->post_content )
		+ preg_match_all( '~<a\b[^>]*href=(["\'])/~i', (string) $post->post_content );
	if ( $internal >= 3 ) {
		$score += 10;
	} elseif ( $internal >= 1 ) {
		$score += 5;
		$issues[] = 'پیوند داخلی کم است (کمتر از ۳).';
	} else {
		$issues[] = 'هیچ پیوند داخلی ندارد.';
	}

	$external = preg_match_all( '~<a\b[^>]*href=(["\'])https?://(?!' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '~' ) . ')~i', (string) $post->post_content );
	if ( $external >= 1 ) {
		$score += 5;
	} else {
		$issues[] = 'پیوند بیرونی (منبع) ندارد.';
	}

	if ( ! preg_match( '~href=(["\'])(?!(?:https?://|/|\#|\?|mailto:|tel:))~i', (string) $post->post_content ) ) {
		$score += 5;
	} else {
		$issues[] = 'پیوند نسبیِ مرده (مانند W_*) در محتوا هست.';
	}

	if ( (int) get_post_thumbnail_id( $post ) > 0 ) {
		$score += 5;
	} else {
		$issues[] = 'تصویر شاخص ندارد.';
	}

	$taxes = get_object_taxonomies( $post->post_type, 'objects' );
	$has_term = false;
	foreach ( $taxes as $tax ) {
		if ( ! $tax->public ) {
			continue;
		}
		if ( wp_get_object_terms( $post->ID, $tax->name, array( 'fields' => 'ids' ) ) ) {
			$has_term = true;
			break;
		}
	}
	if ( $has_term || 'page' === $post->post_type ) {
		$score += 5;
	} else {
		$issues[] = 'به هیچ دسته/برچسبی وصل نیست.';
	}

	if ( '1' !== get_post_meta( $post->ID, 'sa_noindex', true ) ) {
		$score += 5;
	} else {
		$issues[] = 'با برچسب noindex منتشر شده است.';
	}

	if ( substr_count( (string) $post->post_content, '</p>' ) >= 5 ) {
		$score += 5;
	} else {
		$issues[] = 'ساختار پاراگرافی ضعیف است (کمتر از ۵ پاراگراف).';
	}

	$score = max( 0, min( 100, $score ) );
	return array( 'score' => $score, 'issues' => $issues, 'words' => $words );
}

/**
 * دادهٔ تجمیعی داشبورد (با کش یک‌ساعته).
 *
 * @return array
 */
function sa_seo_dashboard_data() {
	$cached = get_transient( 'sa_seo_dashboard_v1' );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$types  = array( 'post', 'province', 'city', 'attraction' );
	$labels = array(
		'post'      => 'مقالات',
		'province'  => 'استان‌ها',
		'city'      => 'شهرستان‌ها',
		'attraction' => 'دیدنی‌ها',
	);

	$rows   = array();
	$counts = array();
	foreach ( $types as $type ) {
		$q = new WP_Query(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1000,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => false,
			)
		);
		$counts[ $type ] = (int) $q->found_posts;
		foreach ( $q->posts as $p ) {
			$res       = sa_seo_score_post( $p );
			$rows[]    = array(
				'id'     => (int) $p->ID,
				'type'   => $type,
				'title'  => get_the_title( $p ),
				'slug'   => $p->post_name,
				'score'  => $res['score'],
				'words'  => $res['words'],
				'issues' => $res['issues'],
				'date'   => mysql2date( 'Y-m-d', $p->post_date ),
			);
		}
		wp_reset_postdata();
	}

	// رتبه: مرتب‌سازی نزولی بر پایهٔ امتیاز.
	usort(
		$rows,
		function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		}
	);
	foreach ( $rows as $i => $row ) {
		$rows[ $i ]['rank'] = $i + 1;
	}

	$bands = array( 'عالی' => 0, 'خوب' => 0, 'متوسط' => 0, 'ضعیف' => 0 );
	foreach ( $rows as $row ) {
		$bands[ sa_seo_score_band( $row['score'] ) ]++;
	}

	// میانگین امتیاز هر دستهٔ مقالات.
	$cat_avg = array();
	$cats    = get_categories( array( 'hide_empty' => true ) );
	foreach ( (array) $cats as $cat ) {
		$ids = get_objects_in_term( $cat->term_id, 'category' );
		$sub = array();
		foreach ( $rows as $row ) {
			if ( 'post' === $row['type'] && in_array( $row['id'], (array) $ids, true ) ) {
				$sub[] = $row['score'];
			}
		}
		if ( $sub ) {
			$cat_avg[] = array(
				'name'  => $cat->name,
				'count' => count( $sub ),
				'avg'   => (int) round( array_sum( $sub ) / count( $sub ) ),
			);
		}
	}
	usort(
		$cat_avg,
		function ( $a, $b ) {
			return $b['avg'] <=> $a['avg'];
		}
	);

	$all_scores = array_column( $rows, 'score' );
	$data       = array(
		'generated' => time(),
		'counts'    => $counts,
		'labels'    => $labels,
		'totals'    => array(
			'pages'      => (int) wp_count_posts( 'page' )->publish,
			'noindex'    => (int) count( get_posts( array( 'post_type' => $types, 'post_status' => 'publish', 'numberposts' => 2000, 'fields' => 'ids', 'meta_key' => 'sa_noindex', 'meta_value' => '1' ) ) ),
			'avg'        => $all_scores ? (int) round( array_sum( $all_scores ) / count( $all_scores ) ) : 0,
		),
		'bands'     => $bands,
		'cat_avg'   => $cat_avg,
		'rows'      => $rows,
	);

	set_transient( 'sa_seo_dashboard_v1', $data, HOUR_IN_SECONDS );
	return $data;
}
add_action( 'save_post', 'sa_seo_dashboard_flush' );
add_action( 'deleted_post', 'sa_seo_dashboard_flush' );

/**
 * باطل‌کردن کش داشبورد.
 *
 * @return void
 */
function sa_seo_dashboard_flush() {
	delete_transient( 'sa_seo_dashboard_v1' );
}

/**
 * نام بازهٔ امتیاز.
 *
 * @param int $score امتیاز.
 * @return string
 */
function sa_seo_score_band( $score ) {
	$score = (int) $score;
	if ( $score >= 80 ) {
		return 'عالی';
	}
	if ( $score >= 60 ) {
		return 'خوب';
	}
	if ( $score >= 40 ) {
		return 'متوسط';
	}
	return 'ضعیف';
}

/**
 * رنگ بازه.
 *
 * @param string $band بازه.
 * @return string
 */
function sa_seo_band_color( $band ) {
	$map = array(
		'عالی'   => '#1a7f37',
		'خوب'    => '#2da44e',
		'متوسط'  => '#bf8700',
		'ضعیف'   => '#cf222e',
	);
	return isset( $map[ $band ] ) ? $map[ $band ] : '#57606a';
}

/**
 * رندر صفحهٔ داشبورد.
 *
 * @return void
 */
function sa_seo_dashboard_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$data   = sa_seo_dashboard_data();
	$band_q = isset( $_GET['band'] ) ? sanitize_text_field( wp_unslash( $_GET['band'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$type_q = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$paged  = max( 1, isset( $_GET['pg'] ) ? (int) $_GET['pg'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
	$per    = 25;

	$rows = $data['rows'];
	if ( '' !== $band_q ) {
		$rows = array_values( array_filter( $rows, function ( $r ) use ( $band_q ) { return sa_seo_score_band( $r['score'] ) === $band_q; } ) );
	}
	if ( '' !== $type_q ) {
		$rows = array_values( array_filter( $rows, function ( $r ) use ( $type_q ) { return $r['type'] === $type_q; } ) );
	}
	$total = count( $rows );
	$pages = max( 1, (int) ceil( $total / $per ) );
	$paged = min( $paged, $pages );
	$rows  = array_slice( $rows, ( $paged - 1 ) * $per, $per );

	$band_total = array_sum( $data['bands'] ) ? array_sum( $data['bands'] ) : 1;
	$stop       = 0;
	$grad       = array();
	foreach ( $data['bands'] as $band => $n ) {
		$pct    = round( $n / $band_total * 100, 2 );
		$grad[] = sa_seo_band_color( $band ) . ' ' . $stop . '% ' . ( $stop + $pct ) . '%';
		$stop  += $pct;
	}
	$donut = $grad ? 'conic-gradient(' . implode( ',', $grad ) . ')' : '#eee';
	?>
	<div class="wrap sa-seo-dash" dir="rtl">
		<h1>داشبورد سئو سرزمین آریان
			<span style="font-size:13px;color:#666">به‌روزرسانی: <?php echo esc_html( wp_date( 'Y/m/d H:i', $data['generated'] ) ); ?> · کش یک‌ساعته</span>
		</h1>

		<div class="sa-cards">
			<?php foreach ( $data['counts'] as $type => $n ) : ?>
				<div class="sa-card"><div class="sa-num"><?php echo (int) $n; ?></div><div class="sa-lbl"><?php echo esc_html( $data['labels'][ $type ] ); ?></div></div>
			<?php endforeach; ?>
			<div class="sa-card"><div class="sa-num"><?php echo (int) $data['totals']['pages']; ?></div><div class="sa-lbl">برگه‌های منتشر</div></div>
			<div class="sa-card"><div class="sa-num"><?php echo (int) $data['totals']['avg']; ?></div><div class="sa-lbl">میانگین امتیاز سئو</div></div>
			<div class="sa-card"><div class="sa-num" style="color:#cf222e"><?php echo (int) $data['totals']['noindex']; ?></div><div class="sa-lbl">noindex شده</div></div>
		</div>

		<div class="sa-grid">
			<div class="sa-box">
				<h2>توزیع امتیازها</h2>
				<div class="sa-donut" style="background:<?php echo esc_attr( $donut ); ?>">
					<div class="sa-donut-hole"><span><?php echo (int) count( $data['rows'] ); ?></span>محتوا</div>
				</div>
				<ul class="sa-legend">
					<?php foreach ( $data['bands'] as $band => $n ) : ?>
						<li><a href="<?php echo esc_url( add_query_arg( 'band', $band, admin_url( 'admin.php?page=sa-seo-dashboard' ) ) ); ?>"><i style="background:<?php echo esc_attr( sa_seo_band_color( $band ) ); ?>"></i><?php echo esc_html( $band ); ?>: <?php echo (int) $n; ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="sa-box">
				<h2>میانگین امتیاز هر دسته</h2>
				<?php foreach ( $data['cat_avg'] as $c ) : ?>
					<div class="sa-bar-row">
						<span class="sa-bar-lbl"><?php echo esc_html( $c['name'] ); ?> (<?php echo (int) $c['count']; ?>)</span>
						<span class="sa-bar-track"><span class="sa-bar-fill" style="width:<?php echo (int) $c['avg']; ?>%;background:<?php echo esc_attr( sa_seo_band_color( sa_seo_score_band( $c['avg'] ) ) ); ?>"></span></span>
						<b><?php echo (int) $c['avg']; ?></b>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<h2>رتبه‌بندی محتوا
			<?php if ( '' !== $band_q || '' !== $type_q ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sa-seo-dashboard' ) ); ?>">حذف فیلترها</a>
			<?php endif; ?>
		</h2>
		<table class="widefat striped sa-table">
			<thead><tr><th>رتبه</th><th>امتیاز</th><th>بازه</th><th>نوع</th><th>عنوان</th><th>واژه</th><th>ایرادها</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $r ) : $band = sa_seo_score_band( $r['score'] ); ?>
				<tr>
					<td><?php echo (int) $r['rank']; ?></td>
					<td><span class="sa-score" style="--c:<?php echo esc_attr( sa_seo_band_color( $band ) ); ?>"><?php echo (int) $r['score']; ?></span></td>
					<td><span class="sa-pill" style="background:<?php echo esc_attr( sa_seo_band_color( $band ) ); ?>"><?php echo esc_html( $band ); ?></span></td>
					<td><?php echo esc_html( $data['labels'][ $r['type'] ] ?? $r['type'] ); ?></td>
					<td><a href="<?php echo esc_url( get_edit_post_link( $r['id'] ) ); ?>"><?php echo esc_html( $r['title'] ); ?></a><code style="display:block;font-size:11px">/<?php echo esc_html( $r['slug'] ); ?>/</code></td>
					<td><?php echo (int) $r['words']; ?></td>
					<td style="font-size:12px"><?php echo esc_html( implode( ' · ', array_slice( $r['issues'], 0, 3 ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="tablenav">
			<?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
				<a class="button <?php echo $i === $paged ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'pg' => $i, 'band' => $band_q, 'type' => $type_q ), admin_url( 'admin.php?page=sa-seo-dashboard' ) ) ); ?>"><?php echo $i; ?></a>
			<?php endfor; ?>
			<span style="margin-right:12px"><?php echo (int) $total; ?> ردیف</span>
		</p>
	</div>
	<style>
		.sa-cards{display:flex;flex-wrap:wrap;gap:12px;margin:18px 0}
		.sa-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:14px 22px;min-width:120px;text-align:center;box-shadow:0 1px 2px rgba(0,0,0,.05)}
		.sa-num{font-size:26px;font-weight:700}
		.sa-lbl{color:#555;font-size:12px;margin-top:4px}
		.sa-grid{display:grid;grid-template-columns:340px 1fr;gap:16px}
		@media(max-width:960px){.sa-grid{grid-template-columns:1fr}}
		.sa-box{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:16px}
		.sa-donut{width:190px;height:190px;border-radius:50%;margin:8px auto;position:relative}
		.sa-donut-hole{position:absolute;inset:34px;background:#fff;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:12px;color:#666}
		.sa-donut-hole span{font-size:24px;font-weight:700;color:#111}
		.sa-legend{list-style:none;margin:10px 0 0;text-align:center}
		.sa-legend li{display:inline-block;margin:0 8px}
		.sa-legend i{display:inline-block;width:10px;height:10px;border-radius:2px;margin-left:4px}
		.sa-bar-row{display:flex;align-items:center;gap:8px;margin:8px 0}
		.sa-bar-lbl{width:190px;font-size:12px}
		.sa-bar-track{flex:1;background:#eee;border-radius:6px;height:12px;overflow:hidden}
		.sa-bar-fill{display:block;height:100%}
		.sa-score{display:inline-block;min-width:34px;text-align:center;padding:2px 6px;border-radius:8px;background:color-mix(in srgb,var(--c) 15%,#fff);color:var(--c);font-weight:700;border:1px solid var(--c)}
		.sa-pill{color:#fff;border-radius:10px;padding:2px 8px;font-size:11px}
		.sa-table code{color:#777}
	</style>
	<?php
}
