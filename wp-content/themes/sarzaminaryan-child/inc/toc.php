<?php
/**
 * «فهرست مطالب» ابتدای مقاله‌ها (v2.18.17)
 *
 * درخواست مالک: «کل مقالات سایت فهرست مطالب داشته باشند» — فهرستی شماره‌دار در
 * ابتدای هر مقاله که خواننده با یک نگاه ساختار متن را ببیند و با یک کلیک به همان
 * سرفصل برود.
 *
 * کار این ماژول:
 *   ۱. سرفصل‌های خودِ بدنه (H1 پیش از تنزل تا H4) خوانده می‌شوند، هر کدام یک
 *      شناسهٔ پایدار می‌گیرد (سرفصل‌های دارای شناسه دست‌نخورده می‌مانند) و فهرست
 *      در ابتدای بدنه می‌آید.
 *   ۲. سرفصل‌هایی که قالب بیرون از بدنه می‌سازد هم به همان فهرست اضافه می‌شوند
 *      (اطلاعات کلیدی، تقسیمات/همسایه‌ها/کجا برویم/مردم، سوالات متداول، منابع) تا
 *      فهرست دقیقاً همان چیزی باشد که خواننده در صفحه می‌بیند؛ شرطِ افزودنِ هر
 *      بخش همان شرطی است که خودِ آن بخش را چاپ می‌کند، پس لینک مرده ساخته نمی‌شود.
 *   ۳. برای جای دلخواه، نشان «{{TOC}}» را در متن بگذارید؛ فهرست همان‌جا می‌آید.
 *
 * کلید خاموش/روشن: سفارشی‌ساز → نمایش فارسی → «نمایش «فهرست مطالب» در ابتدای
 * مقاله‌ها» (پیش‌فرض روشن). برای خاموش‌کردنِ برنامه‌نویسی:
 *     add_filter( 'sa_toc_enabled', '__return_false' );
 * و برای کنار گذاشتنِ یک نوشتهٔ خاص: متای `_sa_no_toc` = ۱.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا فهرست مطالب فعال است؟
 *
 * @return bool
 */
function sa_toc_enabled() {
	$on = (bool) get_theme_mod( 'sa_show_toc', true );

	/**
	 * خاموش/روشن‌کردنِ «فهرست مطالب».
	 *
	 * @param bool $on وضعیت.
	 */
	return (bool) apply_filters( 'sa_toc_enabled', $on );
}

/**
 * کمینهٔ سرفصل‌ها برای نمایش فهرست.
 *
 * @return int
 */
function sa_toc_min_items() {
	$min = (int) apply_filters( 'sa_toc_min_items', (int) get_theme_mod( 'sa_toc_min', 2 ) );
	return max( 1, $min );
}

/**
 * نوع‌هایی که فهرست مطالب می‌گیرند (همهٔ مقاله‌ها و موجودیت‌ها؛ برگه‌ها نه).
 *
 * @return string[]
 */
function sa_toc_post_types() {
	$types = array_merge( array( 'post' ), function_exists( 'sa_entity_types' ) ? sa_entity_types() : array() );

	/**
	 * نوع‌های محتوایی که «فهرست مطالب» می‌گیرند.
	 *
	 * @param string[] $types نامک نوع‌ها.
	 */
	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'sa_toc_post_types', $types ) ) ) );
}

/**
 * عنوان کادر فهرست.
 *
 * @return string
 */
function sa_toc_title() {
	/**
	 * عنوان «فهرست مطالب».
	 *
	 * @param string $title عنوان.
	 */
	return (string) apply_filters( 'sa_toc_title', 'فهرست مطالب' );
}

/* -------------------------------------------------------------------------
 * دفترچهٔ سرفصل‌ها (قالب + بدنه)
 * ---------------------------------------------------------------------- */

/**
 * پاک‌کردن دفترچه (برای پیش‌نمایش‌ها و آزمون‌ها).
 *
 * @return void
 */
function sa_toc_reset() {
	$GLOBALS['sa_toc_registry'] = array();
	$GLOBALS['sa_toc_done']     = array();
}

/**
 * ثبت یک سرفصلِ بیرون از بدنه (قالب).
 *
 * شناسهٔ تکراری نادیده گرفته می‌شود تا یک بخش دو بار در فهرست نیاید (مثلاً وقتی
 * هم قالب پیش از بدنه بخش را اعلام می‌کند و هم خودِ آن بخش هنگام رندر).
 *
 * @param string $label  متن سرفصل.
 * @param string $id     شناسهٔ لنگر (باید در صفحه وجود داشته باشد).
 * @param float  $weight ترتیب (کوچک‌تر = بالاتر).
 * @return void
 */
function sa_toc_register( $label, $id, $weight = 100 ) {
	$label = trim( wp_strip_all_tags( (string) $label ) );
	$id    = trim( (string) $id );
	if ( '' === $label || '' === $id || ( function_exists( 'sa_article_section_is_retired' ) && sa_article_section_is_retired( $label ) ) ) {
		return;
	}
	if ( ! isset( $GLOBALS['sa_toc_registry'] ) || ! is_array( $GLOBALS['sa_toc_registry'] ) ) {
		$GLOBALS['sa_toc_registry'] = array();
	}
	foreach ( $GLOBALS['sa_toc_registry'] as $sa_item ) {
		if ( $sa_item['id'] === $id ) {
			return;
		}
	}
	$GLOBALS['sa_toc_registry'][] = array(
		'label'  => $label,
		'id'     => $id,
		'level'  => 1,
		'weight' => (float) $weight,
		'order'  => count( $GLOBALS['sa_toc_registry'] ),
	);
}

/**
 * سرفصل‌های ثبت‌شدهٔ قالب.
 *
 * @return array<int,array<string,mixed>>
 */
function sa_toc_registered() {
	return ( isset( $GLOBALS['sa_toc_registry'] ) && is_array( $GLOBALS['sa_toc_registry'] ) ) ? $GLOBALS['sa_toc_registry'] : array();
}

/**
 * بخش‌های پایینِ بدنه (سوالات متداول/منابع) را پیش از رندر بدنه اعلام می‌کند.
 *
 * قالب این تابع را «پیش از» بدنه صدا می‌زند تا این بخش‌ها در فهرستی که داخل بدنه
 * چاپ می‌شود حاضر باشند؛ شرط‌ها دقیقاً همان توابعی‌اند که خودِ بخش‌ها را می‌سازند
 * (`sa_get_faq` و `sa_get_sources`)، پس هرگز لنگرِ خالی در فهرست نمی‌آید.
 *
 * @param int $post_id شناسهٔ نوشته.
 * @return void
 */
function sa_toc_register_tail( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	if ( ! $post_id ) {
		return;
	}
	if ( function_exists( 'sa_get_faq' ) && sa_get_faq( $post_id ) ) {
		sa_toc_register( 'سوالات متداول', 'faq', 900 );
	}
	if ( function_exists( 'sa_get_sources' ) && sa_get_sources( $post_id ) ) {
		sa_toc_register( 'منابع', 'sources', 990 );
	}
}

/* -------------------------------------------------------------------------
 * ساخت فهرست
 * ---------------------------------------------------------------------- */

/**
 * آیا همین حالا (در همین درخواست و همین نوشته) فهرست ساخته شود؟
 *
 * @return bool
 */
function sa_toc_should_run() {
	if ( ! sa_toc_enabled() ) {
		return false;
	}
	if ( is_admin() || is_feed() || wp_doing_ajax() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
		return false;
	}
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return false;
	}
	$post_id = (int) get_the_ID();
	if ( ! $post_id ) {
		return false;
	}
	if ( get_post_meta( $post_id, '_sa_no_toc', true ) ) {
		return false;
	}
	return in_array( (string) get_post_type( $post_id ), sa_toc_post_types(), true );
}

/**
 * متن تمیزِ یک سرفصل (بدون تگ، بدون نشان‌های بازبینی).
 *
 * @param string $html محتوای سرفصل.
 * @return string
 */
function sa_toc_label( $html ) {
	$label = wp_strip_all_tags( (string) $html );
	$label = html_entity_decode( $label, ENT_QUOTES, 'UTF-8' );
	if ( function_exists( 'sa_uncertainty_markers' ) ) {
		$label = str_replace( sa_uncertainty_markers(), '', $label );
	}
	$label = preg_replace( '/\s+/u', ' ', $label );
	return trim( (string) $label );
}

/**
 * شناسهٔ تازه برای یک سرفصل بی‌شناسه.
 *
 * @param string            $label متن سرفصل.
 * @param array<string,bool> $used  شناسه‌های اشغال‌شده.
 * @return string
 */
function sa_toc_heading_id( $label, $used ) {
	$slug = html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' );
	$slug = str_replace(
		array( "\xE2\x80\x8C", '،', '؛', '؟', '«', '»', '(', ')', '[', ']', '{', '}', ':', '!', '?', '.', ',', 'ـ', '|', '/', '\\', '+', '*', '#', '&', "'", '"', '’', '‘', '“', '”', '…' ),
		' ',
		$slug
	);
	$slug = preg_replace( '/\s+/u', '-', $slug );
	$slug = preg_replace( '/[^\p{L}\p{N}\-]+/u', '', (string) $slug );
	$slug = trim( (string) preg_replace( '/-+/u', '-', (string) $slug ), '-' );
	if ( function_exists( 'mb_substr' ) ) {
		$slug = mb_substr( $slug, 0, 60, 'UTF-8' );
	} else {
		$slug = substr( $slug, 0, 120 );
	}
	if ( '' === $slug ) {
		$slug = 'section';
	}
	$base = 'sa-sec-' . $slug;
	$id   = $base;
	$i    = 2;
	while ( isset( $used[ $id ] ) ) {
		$id = $base . '-' . $i;
		++$i;
	}
	return $id;
}

/**
 * سطحِ سرفصل (۱ = H2، ۲ = H3، ۳ = H4). H1 همان سطح ۱ است.
 *
 * @param string $tag نام تگ.
 * @return int
 */
function sa_toc_level( $tag ) {
	$n = (int) substr( (string) $tag, 1 );
	$n = $n > 1 ? $n - 1 : 1;
	return max( 1, min( 3, $n ) );
}

/**
 * شناسه‌های موجود در متن (تا شناسهٔ تازه با آن‌ها تصادم نکند).
 *
 * @param string $content متن.
 * @return array<string,bool>
 */
function sa_toc_used_ids( $content ) {
	$used = array();
	if ( preg_match_all( '/\sid\s*=\s*(["\'])([^"\']+)\1/i', (string) $content, $m ) ) {
		foreach ( $m[2] as $id ) {
			$used[ $id ] = true;
		}
	}
	return $used;
}

/**
 * سرفصل‌های بدنه را شناسه‌دار می‌کند و به دفترچهٔ فهرست می‌افزاید.
 *
 * @param string              $content متن.
 * @param array<string,bool>  $used    شناسه‌های اشغال‌شده (به‌روزرسانی می‌شود).
 * @param array<int,array<string,mixed>> $items سرفصل‌های یافته‌شده (به‌روزرسانی می‌شود).
 * @return string متن با شناسه‌های تازه.
 */
function sa_toc_scan_headings( $content, &$used, &$items ) {
	$index = 0;
	$out   = preg_replace_callback(
		'#<(h[1-4])\b([^>]*)>(.*?)</\1\s*>#isu',
		function ( $m ) use ( &$used, &$items, &$index ) {
			$tag   = strtolower( $m[1] );
			$attr  = $m[2];
			$inner = $m[3];
			$label = sa_toc_label( $inner );
			if ( '' === $label ) {
				return $m[0];
			}
			if ( preg_match( '/\sid\s*=\s*(["\'])([^"\']+)\1/i', $attr, $idm ) ) {
				$id = $idm[2];
			} else {
				$id    = sa_toc_heading_id( $label, $used );
				$attr .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			++$index;
			$items[] = array(
				'label'  => $label,
				'id'     => $id,
				'level'  => sa_toc_level( $tag ),
				'weight' => 100 + $index,
				'order'  => $index,
			);
			return '<' . $tag . $attr . '>' . $inner . '</' . $tag . '>';
		},
		(string) $content
	);

	return ( is_string( $out ) && '' !== $out ) ? $out : (string) $content;
}

/**
 * مرتب‌سازی سرفصل‌ها: وزن، سپس ترتیب ثبت.
 *
 * @param array<int,array<string,mixed>> $items سرفصل‌ها.
 * @return array<int,array<string,mixed>>
 */
function sa_toc_sort( $items ) {
	$items = array_values( (array) $items );
	usort(
		$items,
		function ( $a, $b ) {
			if ( (float) $a['weight'] === (float) $b['weight'] ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
			return ( (float) $a['weight'] < (float) $b['weight'] ) ? -1 : 1;
		}
	);
	return $items;
}

/**
 * مارک‌آپ کادر «فهرست مطالب».
 *
 * @param array<int,array<string,mixed>> $items سرفصل‌ها.
 * @return string ('' = نمایش نده).
 */
function sa_toc_markup( $items ) {
	if ( function_exists( 'sa_article_section_is_retired' ) ) {
		$items = array_filter( $items, function ( $item ) { return ! sa_article_section_is_retired( $item['label'] ); } );
	}
	$items = sa_toc_sort( $items );
	if ( count( $items ) < sa_toc_min_items() ) {
		return '';
	}
	$total = count( $items );
	$out   = '<nav class="sa-toc" id="sa-toc" aria-labelledby="sa-toc-title">';
	$out  .= '<div class="sa-toc__head"><h2 class="sa-toc__title" id="sa-toc-title">' . esc_html( sa_toc_title() ) . '</h2>';
	/* translators: %s: number of headings. */
	$out  .= '<span class="sa-toc__count">' . esc_html( sprintf( '%s سرفصل', sa_fa_digits( (string) $total ) ) ) . '</span></div>';
	$out  .= '<ol class="sa-toc__list">';
	foreach ( $items as $i => $item ) {
		$level = max( 1, min( 3, (int) $item['level'] ) );
		$out  .= '<li class="sa-toc__item sa-toc__item--l' . $level . '">'
			. '<a class="sa-toc__link" href="#' . esc_attr( $item['id'] ) . '">'
			. '<span class="sa-toc__num" aria-hidden="true">' . esc_html( sa_fa_digits( (string) ( $i + 1 ) ) ) . '</span>'
			. '<span class="sa-toc__label">' . esc_html( $item['label'] ) . '</span>'
			. '</a></li>';
	}
	$out .= '</ol></nav>';

	return $out;
}

/**
 * فهرست مطالب را به ابتدای بدنه اضافه می‌کند (یا جای نشان {{TOC}}).
 *
 * @param string $content متن مقاله.
 * @return string
 */
function sa_toc_filter( $content ) {
	$content = (string) $content;
	if ( '' === trim( $content ) || ! sa_toc_should_run() ) {
		return $content;
	}

	$post_id = (int) get_the_ID();
	if ( ! empty( $GLOBALS['sa_toc_done'][ $post_id ] ) ) {
		return $content; // هر مقاله یک‌بار.
	}
	if ( false !== strpos( $content, 'class="sa-toc' ) ) {
		$GLOBALS['sa_toc_done'][ $post_id ] = true; // فهرست دستیِ مالک.
		return $content;
	}

	$markers = array( '{{TOC}}', '<!--sa:toc-->' );
	$has_marker = false;
	foreach ( $markers as $marker ) {
		if ( false !== strpos( $content, $marker ) ) {
			$has_marker = true;
			break;
		}
	}

	$used  = sa_toc_used_ids( $content );
	$items = array();
	$body  = sa_toc_scan_headings( $content, $used, $items );
	$all   = array_merge( sa_toc_registered(), $items );
	$toc   = sa_toc_markup( $all );

	if ( '' === $toc ) {
		// سرفصل کافی نیست؛ اگر نشان گذاشته شده بود، پاک شود تا در متن نماند.
		return $has_marker ? str_replace( $markers, '', $body ) : $body;
	}

	$GLOBALS['sa_toc_done'][ $post_id ] = true;

	if ( $has_marker ) {
		return str_replace( $markers, $toc, $body );
	}
	return $toc . $body;
}
add_filter( 'the_content', 'sa_toc_filter', 11 );
