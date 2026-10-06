<?php
/**
 * لینک‌سازی داخلی خودکارِ یکتا — استان / شهرستان / نمای برتر (v2.11.27)
 *
 * مسئله‌ای که این ماژول حل می‌کند
 * ------------------------------
 * مقاله‌های تولیدشده (به‌ویژه «نمای برتر») بارها نام استان و شهرستان را می‌آورند،
 * اما همیشه لینک داخلی ندارند. نتیجه: صفحه‌ی استان/شهرستان سیگنال داخلی نمی‌گیرد
 * و کاربر هم راهی به صفحه‌ی والد پیدا نمی‌کند. نوشتن لینک با دست در ۴۸۳ شهرستان
 * و صدها نما ممکن نیست؛ باید در زمان نمایش ساخته شود.
 *
 * قانون‌های این ماژول (ساده و قابل پیش‌بینی)
 * ------------------------------------------
 *   ۱. فقط بدنه‌ی محتوا (`the_content`) و فقط صفحه‌های تکی (`is_singular`).
 *      آرشیوها، خلاصه‌ها و خوراک دست‌نخورده می‌مانند.
 *   ۲. هر مقصد (استان، شهرستان، نمای برتر) **فقط یک‌بار در هر صفحه** لینک می‌شود؛
 *      آن هم در **نخستین** رخدادِ نامش در متن. ده استان نام برده شود، ده لینک
 *      ساخته می‌شود — اما «لرستان» فقط یکی.
 *   ۳. لینک خودبه‌خودی ممنوع: نامِ استان در صفحه‌ی همان استان، نامِ شهرستان در
 *      صفحه‌ی همان شهرستان و عنوانِ نما در صفحه‌ی همان نما لینک نمی‌شود.
 *   ۴. لینکی که از قبل در متن هست دست‌نخورده می‌ماند و دوبار شمرده نمی‌شود:
 *      اگر متن خودش لینک صفحه‌ی استان را دارد، این ماژول لینک دوم نمی‌سازد.
 *   ۵. مقصد باید واقعاً منتشر شده باشد؛ هیچ لینکی به پیش‌نویس یا ۴۰۴ ساخته نمی‌شود.
 *      استانی که صفحه‌ی نوشته ندارد، به آرشیوِ ترمِ `province_tax` لینک می‌شود.
 *   ۶. داخل تگ‌های حساس کاری انجام نمی‌شود: لینک‌های موجود، تیترها، کد،
 *      اسکریپت، استایل، فرم، دکمه و هر عنصری که `data-sa-autolink="off"`
 *      یا کلاس `sa-autolink-off` داشته باشد.
 *
 * امنیت و کارایی
 * --------------
 *   - متن هرگز به‌صورت خام بازنویسی نمی‌شود: ابتدا به تگ/متن شکسته می‌شود، فقط
 *     گره‌های متنی پردازش می‌شوند و خروجی با `esc_url` / `esc_html` / `esc_attr`
 *     بیرون می‌آید. نشانی‌ها از `get_permalink()`/`get_term_link()` می‌آیند.
 *   - تطبیق روی رشته‌ی نرمال‌شده انجام می‌شود تا نیم‌فاصله، فاصله‌ی نشکن،
 *     ی/ک عربی و آ/أ/إ تفاوتی ایجاد نکند: «بستان‌آباد»، «بستان آباد» و
 *     «بستانآباد» همه یک نام‌اند.
 *   - نام‌های خیلی کوتاه (۳ حرف یا کمتر) فقط وقتی لینک می‌شوند که پیش از آن‌ها
 *     «شهرستان/شهر/استان/بخش/دهستان» آمده باشد؛ وگرنه «دیر» در «دیر یا زود»
 *     به شهرستان دیر لینک می‌خورد.
 *   - واژه‌نامه یک‌بار ساخته و ۱۲ ساعت در ترانزینت کش می‌شود و با هر انتشار،
 *     ویرایش یا حذفِ موجودیت پاک می‌شود.
 *
 * خاموش‌کردن
 * ----------
 *   define( 'SA_AUTOLINK', false );                       در wp-config.php
 *   add_filter( 'sa_autolink_enabled', '__return_false' );
 *   یا: نمایش ← تنظیمات قالب ← «لینک‌سازی داخلی خودکار».
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا لینک‌ساز داخلی فعال است؟
 *
 * @return bool
 */
function sa_autolink_enabled() {
	$on = defined( 'SA_AUTOLINK' ) ? (bool) SA_AUTOLINK : (bool) get_theme_mod( 'sa_autolink', true );

	/**
	 * فعال/غیرفعال کردن لینک‌سازی داخلی خودکار.
	 *
	 * @param bool $on وضعیت.
	 */
	return (bool) apply_filters( 'sa_autolink_enabled', $on );
}

/**
 * انواع محتوایی که می‌توانند مقصد لینک باشند.
 *
 * @return string[]
 */
function sa_autolink_target_types() {
	$types = array( 'province', 'city', 'attraction' );

	/**
	 * نوع‌های مقصد برای لینک‌سازی خودکار.
	 *
	 * @param string[] $types نوع‌های نوشته.
	 */
	return (array) apply_filters( 'sa_autolink_target_types', $types );
}

/**
 * تگ‌هایی که محتوای آن‌ها هرگز لینک‌سازی نمی‌شود.
 *
 * `a` در این فهرست است چون لینک تو لینک، هم HTML غلط است و هم تجربه‌ی کاربر
 * را خراب می‌کند. تیترها هم حذف شدند تا عنوان بخش‌ها به مقصدِ عجیب وصل نشود.
 *
 * @return string[]
 */
function sa_autolink_skip_tags() {
	$tags = array(
		'a', 'script', 'style', 'code', 'pre', 'kbd', 'samp', 'var',
		'textarea', 'select', 'option', 'button', 'label', 'noscript',
		'template', 'svg', 'math', 'iframe', 'object',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
	);

	/**
	 * تگ‌های مستثنی از لینک‌سازی خودکار.
	 *
	 * @param string[] $tags نام تگ‌ها (کوچک).
	 */
	return (array) apply_filters( 'sa_autolink_skip_tags', $tags );
}

/**
 * تگ‌های سطح بلوک: دیدنِ بازشدنِ آن‌ها یعنی عنصر قبلی ناخواسته باز مانده است.
 *
 * @return string[]
 */
function sa_autolink_block_tags() {
	return array(
		'address', 'article', 'aside', 'blockquote', 'dd', 'details', 'div',
		'dl', 'dt', 'fieldset', 'figcaption', 'figure', 'footer', 'form',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main',
		'nav', 'ol', 'p', 'section', 'summary', 'table', 'tbody', 'td',
		'tfoot', 'th', 'thead', 'tr', 'ul',
	);
}

/* -------------------------------------------------------------------------
 * نرمال‌سازیِ متن فارسی
 * ---------------------------------------------------------------------- */

/**
 * نگاشت نویسه‌های هم‌ارزِ فارسی/عربی.
 *
 * هر نویسه به یک نویسه نگاشته می‌شود (یا به رشته‌ی تهی برای حذف) تا بتوان
 * مکانِ هر نویسه را در رشته‌ی اصلی نگه داشت — برای ساختن لینک لازم است
 * بدانیم هر تطبیق در متن اصلی کجاست.
 *
 * @return array<string,string>
 */
function sa_autolink_char_map() {
	static $map = null;
	if ( null === $map ) {
		$map = array(
			// فاصله‌ها، نیم‌فاصله و نشانه‌های نامرئی.
			"\xE2\x80\x8C" => ' ', // نیم‌فاصله (ZWNJ).
			"\xE2\x80\x8D" => '',  // ZWJ.
			"\xE2\x80\x8B" => '',  // ZWSP.
			"\xC2\xA0"     => ' ', // فاصله‌ی نشکن.
			"\xD9\x80"     => '',  // تطویل/کشیده.
			// یکسان‌سازی حروف عربی/فارسی.
			"\xD9\x8A"     => "\xDB\x8C", // ي → ی
			"\xD9\x89"     => "\xDB\x8C", // ى → ی
			"\xD9\xA6"     => "\xDB\x8C", // ئ → ی
			"\xD9\x83"     => "\xDA\xA9", // ك → ک
			"\xD8\xA9"     => "\xD9\x87", // ة → ه
			"\xD8\xA3"     => "\xD8\xA7", // أ → ا
			"\xD8\xA5"     => "\xD8\xA7", // إ → ا
			"\xD8\xA2"     => "\xD8\xA7", // آ → ا
			"\xD8\xA4"     => "\xD9\x88", // ؤ → و
			"\xD8\xA1"     => '',         // ء
			// حرکت‌گذاری‌ها.
			"\xD9\x8B"     => '',
			"\xD9\x8C"     => '',
			"\xD9\x8D"     => '',
			"\xD9\x8E"     => '',
			"\xD9\x8F"     => '',
			"\xD9\x90"     => '',
			"\xD9\x91"     => '',
			"\xD9\x92"     => '',
			"\xD9\xB0"     => '',
		);
	}

	/**
	 * نگاشت نویسه‌ها برای تطبیق نام‌ها.
	 *
	 * @param array<string,string> $map نگاشت.
	 */
	return (array) apply_filters( 'sa_autolink_char_map', $map );
}

/**
 * نرمال‌سازی متن برای تطبیق.
 *
 * خروجی دو بخش دارد: متن نرمال‌شده و نگاشتِ «مکانِ بایتی در متن نرمال‌شده به
 * مکانِ بایتی در متن اصلی». با این نگاشت می‌توان متنِ لینک را دقیقاً از روی
 * متن اصلی بُرید، بی‌آن‌که چیزی تغییر کند.
 *
 * @param string $text متن.
 * @return array{text:string,map:array<int,int>}
 */
function sa_autolink_normalize( $text ) {
	$text = (string) $text;
	$map  = sa_autolink_char_map();
	$out  = '';
	$imap = array();
	$len  = strlen( $text );
	$i    = 0;
	$gap  = false; // آیا نویسه‌ی قبلی فاصله بوده است؟

	while ( $i < $len ) {
		$byte = ord( $text[ $i ] );
		if ( $byte < 0xC0 ) {
			$clen = 1;
		} elseif ( $byte >= 0xF0 ) {
			$clen = 4;
		} elseif ( $byte >= 0xE0 ) {
			$clen = 3;
		} else {
			$clen = 2;
		}
		$char  = substr( $text, $i, $clen );
		$start = $i;
		$i    += $clen;

		if ( isset( $map[ $char ] ) ) {
			$char = $map[ $char ];
			if ( '' === $char ) {
				continue;
			}
		}

		if ( ' ' === $char || "\n" === $char || "\r" === $char || "\t" === $char ) {
			if ( $gap ) {
				continue; // فاصله‌های پشت‌هم یکی می‌شوند.
			}
			$gap  = true;
			$char = ' ';
		} else {
			$gap = false;
		}

		$imap[ strlen( $out ) ] = $start;
		$out                   .= $char;
	}

	return array(
		'text' => $out,
		'map'  => $imap,
	);
}

/**
 * نام‌های عمومی که نباید لینک شوند («آبشار»، «تالاب»، «روستا»…).
 *
 * @return string[] نام‌های نرمال‌شده.
 */
function sa_autolink_generic_names() {
	static $names = null;
	if ( null === $names ) {
		$raw   = array(
			'آبشار', 'دریاچه', 'تالاب', 'چشمه', 'سراب', 'آبگرم', 'غار', 'کوه',
			'قله', 'دشت', 'جنگل', 'دره', 'تنگه', 'رود', 'رودخانه', 'روستا',
			'پارک', 'بوستان', 'باغ', 'سد', 'پل', 'مسجد', 'امامزاده', 'قلعه',
			'کاخ', 'برج', 'موزه', 'حمام', 'بازار', 'کاروانسرا', 'آرامگاه',
			'میدان', 'خیابان', 'ساحل', 'جزیره', 'تپه', 'گردنه', 'منطقه',
			'طبیعت', 'گردشگری', 'نمای برتر', 'نماهای برتر', 'جاذبه',
		);
		$names = array();
		foreach ( $raw as $name ) {
			$norm           = sa_autolink_normalize( $name );
			$names[]        = $norm['text'];
		}
	}

	/**
	 * نام‌های عمومی که هرگز لینک نمی‌شوند.
	 *
	 * @param string[] $names نام‌های نرمال‌شده.
	 */
	return (array) apply_filters( 'sa_autolink_generic_names', $names );
}

/**
 * آیا این نام ارزشِ لینک‌شدن دارد؟
 *
 * @param string $name نام.
 * @param string $type province|city|attraction.
 * @return bool
 */
function sa_autolink_name_ok( $name, $type = 'attraction' ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return false;
	}
	if ( preg_match( '~[<>#\[\]{}|\\\\*]~u', $name ) ) {
		return false;
	}

	$norm    = sa_autolink_normalize( $name );
	$letters = preg_replace( '~\s+~u', '', $norm['text'] );
	$len     = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $letters, 'UTF-8' ) : strlen( (string) $letters );
	if ( $len < 2 ) {
		return false;
	}
	if ( 'attraction' !== $type ) {
		return true;
	}
	// نام‌های خیلی کوتاهِ نما («گهر») و نام‌های عمومی («آبشار») رد می‌شوند.
	if ( $len < 5 ) {
		return false;
	}
	return ! in_array( $norm['text'], sa_autolink_generic_names(), true );
}

/**
 * برچسبِ قابل‌نمایش برای یک مقصد (برای صفت title).
 *
 * @param string $type نوع مقصد.
 * @param string $name نام.
 * @return string
 */
function sa_autolink_label( $type, $name ) {
	$name   = trim( (string) $name );
	$prefix = 'province' === $type ? 'استان' : ( 'city' === $type ? 'شهرستان' : '' );
	if ( '' === $prefix || '' === $name ) {
		return $name;
	}
	if ( 0 === strpos( $name, $prefix ) ) {
		return $name;
	}
	return $prefix . ' ' . $name;
}

/* -------------------------------------------------------------------------
 * واژه‌نامه‌ی مقصدها
 * ---------------------------------------------------------------------- */

/**
 * واژه‌نامه‌ی آماده (کشِ ۱۲ ساعته).
 *
 * ساختار:
 *   array(
 *     'index' => array( نرمال‌شده => array( کاندید، … ) ),
 *     'regex' => الگوی آماده‌ی preg،
 *   )
 * هر کاندید: array( type, id, term, label, prio, ctx, name ).
 *
 * @return array{index:array,regex:string}
 */
function sa_autolink_targets() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cached = get_transient( 'sa_autolink_targets' );
	if ( is_array( $cached ) && isset( $cached['index'], $cached['regex'] ) ) {
		$cache = $cached;
		return $cache;
	}

	$cache = sa_autolink_build_targets();
	set_transient( 'sa_autolink_targets', $cache, 12 * HOUR_IN_SECONDS );
	if ( function_exists( 'sa_remember_cache_key' ) ) {
		sa_remember_cache_key( 'sa_autolink_targets' );
	}
	return $cache;
}

/**
 * پاک‌کردن کشِ واژه‌نامه.
 *
 * @return void
 */
function sa_autolink_flush() {
	delete_transient( 'sa_autolink_targets' );
}
add_action( 'deleted_post', 'sa_autolink_flush' );
add_action( 'saved_province_tax', 'sa_autolink_flush' );
add_action( 'deleted_term', 'sa_autolink_flush' );
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( $new !== $old && $post && in_array( $post->post_type, sa_autolink_target_types(), true ) ) {
			sa_autolink_flush();
		}
	},
	10,
	3
);
add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( $post && ! wp_is_post_revision( $post_id ) && in_array( $post->post_type, sa_autolink_target_types(), true ) ) {
			sa_autolink_flush();
		}
	},
	10,
	2
);

/**
 * فهرستِ نوشته‌های منتشرشده‌ی مقصد، برچسب‌گذاری‌شده با نوع و نامک.
 *
 * @return array<string,array<string,array{id:int,title:string}>>
 */
function sa_autolink_published_posts() {
	global $wpdb;

	$types = array_values( array_unique( array_filter( array_map( 'strval', sa_autolink_target_types() ) ) ) );
	$out   = array();
	foreach ( $types as $type ) {
		$out[ $type ] = array();
	}
	if ( ! $types ) {
		return $out;
	}

	$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_type, post_name, post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_password = '' AND post_name <> '' AND post_type IN ({$placeholders})",
			$types
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	foreach ( (array) $rows as $row ) {
		if ( ! isset( $out[ $row->post_type ] ) ) {
			continue;
		}
		$out[ $row->post_type ][ $row->post_name ] = array(
			'id'    => (int) $row->ID,
			'title' => (string) $row->post_title,
		);
	}
	return $out;
}

/**
 * ساخت واژه‌نامه از سه منبع: ۳۱ استان، ۴۸۳ شهرستان، نماهای برتر منتشرشده.
 *
 * @return array{index:array,regex:string}
 */
function sa_autolink_build_targets() {
	$index = array(); // نرمال‌شده => فهرست کاندیدها.

	/**
	 * افزودن یک مقصد به واژه‌نامه.
	 *
	 * @param string $name   نام فارسی.
	 * @param string $type   province|city|attraction.
	 * @param int    $id     شناسه‌ی نوشته.
	 * @param int    $prio   اولویت در صورت تداخلِ نام (بزرگ‌تر مقدم‌تر).
	 * @param int    $term   شناسه‌ی ترم (برای استانی که صفحه ندارد).
	 */
	$add = function ( $name, $type, $id, $prio, $term = 0 ) use ( &$index ) {
		$name = trim( (string) $name );
		$id   = (int) $id;
		$term = (int) $term;
		if ( '' === $name || ( ! $id && ! $term ) ) {
			return;
		}

		$norm    = sa_autolink_normalize( $name );
		$needles = array( $norm['text'] );
		// «بستان‌آباد» گاهی «بستانآباد» نوشته می‌شود؛ هر دو شکل را بپذیر.
		if ( false !== strpos( $name, "\xE2\x80\x8C" ) ) {
			$joined = str_replace( ' ', '', $norm['text'] );
			if ( '' !== $joined && $joined !== $norm['text'] ) {
				$needles[] = $joined;
			}
		}

		$letters = preg_replace( '~\s+~u', '', $norm['text'] );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $letters, 'UTF-8' ) : strlen( (string) $letters );
		// نام‌های خیلی کوتاه («دیر»، «ری»، «جم») فقط با قرینه‌ی «شهرستان/شهر/استان»
		// لینک می‌شوند. نام استان‌ها مستثنی است: «قم» و «یزد» همیشه همان استان‌اند.
		$short   = ( $length <= 3 && 'province' !== $type );

		$candidate = array(
			'type'  => (string) $type,
			'id'    => $id,
			'term'  => $term,
			'label' => sa_autolink_label( $type, $name ),
			'prio'  => (int) $prio,
			'ctx'   => $short,
			'name'  => $name,
		);

		foreach ( array_unique( array_filter( $needles ) ) as $needle ) {
			if ( ! isset( $index[ $needle ] ) ) {
				$index[ $needle ] = array();
			}
			foreach ( $index[ $needle ] as $existing ) {
				if ( $existing['type'] === $candidate['type'] && $existing['id'] === $candidate['id'] && $existing['term'] === $candidate['term'] ) {
					continue 2; // تکراری.
				}
			}
			$index[ $needle ][] = $candidate;
		}
	};

	$posts = sa_autolink_published_posts();
	// نوع‌ها با فیلتر قابل تغییرند؛ هر کلیدی ممکن است نباشد.
	$city_posts = isset( $posts['city'] ) ? $posts['city'] : array();
	$attr_posts = isset( $posts['attraction'] ) ? $posts['attraction'] : array();

	// ۱) استان‌ها — نام ثابتِ ۳۱ استان، مقصد: صفحه‌ی استان یا آرشیوِ ترمِ آن.
	$province_posts = isset( $posts['province'] ) ? $posts['province'] : array();
	if ( function_exists( 'sa_provinces_data' ) && $province_posts ) {
		foreach ( sa_provinces_data() as $province ) {
			$slug = isset( $province['slug'] ) ? (string) $province['slug'] : '';
			$name = isset( $province['name'] ) ? (string) $province['name'] : '';
			if ( '' === $slug || '' === $name ) {
				continue;
			}
			if ( ! empty( $posts['province'][ $slug ] ) ) {
				$add( $name, 'province', $posts['province'][ $slug ]['id'], 3 );
				continue;
			}
			// صفحه‌ی استان هنوز منتشر نشده؛ آرشیوِ ترم مقصدِ معتبر بعدی است.
			$term = taxonomy_exists( 'province_tax' ) ? get_term_by( 'slug', $slug, 'province_tax' ) : false;
			if ( $term && ! is_wp_error( $term ) ) {
				$add( $name, 'province', 0, 3, (int) $term->term_id );
			}
		}
	}

	// ۲) شهرستان‌ها — فقط آن‌هایی که صفحه‌ی منتشرشده دارند.
	$known_city_ids = array();
	if ( function_exists( 'sa_counties' ) ) {
		foreach ( sa_counties() as $county ) {
			if ( empty( $county['slug'] ) || empty( $county['name'] ) ) {
				continue;
			}
			if ( empty( $posts['city'][ $county['slug'] ] ) ) {
				continue;
			}
			$add( $county['name'], 'city', $posts['city'][ $county['slug'] ]['id'], 2 );
			$known_city_ids[ (int) $posts['city'][ $county['slug'] ]['id'] ] = true;

			// اگر عنوان صفحه با نامِ ثبت‌شده فرق دارد، آن هم مقصد باشد.
			$title = trim( (string) $posts['city'][ $county['slug'] ]['title'] );
			if ( '' !== $title && ! preg_match( '~(?:استان|شهرستان)\s~u', $title ) && sa_autolink_name_ok( $title, 'city' ) ) {
				$add( $title, 'city', $posts['city'][ $county['slug'] ]['id'], 2 );
			}
		}
	}
	// شهرستان‌هایی که بیرون از فهرست ثابت ساخته شده‌اند.
	foreach ( $city_posts as $city ) {
		if ( isset( $known_city_ids[ $city['id'] ] ) ) {
			continue;
		}
		if ( sa_autolink_name_ok( $city['title'], 'city' ) ) {
			$add( $city['title'], 'city', $city['id'], 2 );
		}
	}

	// ۳) نماهای برتر — عنوانِ نوشته، بدون پرانتزِ نام دوم.
	foreach ( $attr_posts as $attraction ) {
		$title = trim( (string) $attraction['title'] );
		if ( '' === $title ) {
			continue;
		}
		$base = trim( (string) preg_replace( '~\s*[(\[«][^)\]»]*[)\]»]\s*~u', ' ', $title ) );
		if ( '' === $base ) {
			$base = $title;
		}
		if ( sa_autolink_name_ok( $base, 'attraction' ) ) {
			$add( $base, 'attraction', $attraction['id'], 1 );
		}
		if ( $base !== $title && sa_autolink_name_ok( $title, 'attraction' ) ) {
			$add( $title, 'attraction', $attraction['id'], 1 );
		}
	}

	// تداخلِ نام (مثلاً «اصفهان» هم استان است هم شهرستان): اولویت با بالاترین prio.
	foreach ( array_keys( $index ) as $needle ) {
		usort(
			$index[ $needle ],
			function ( $a, $b ) {
				return ( (int) $b['prio'] ) - ( (int) $a['prio'] );
			}
		);
	}

	$needles = array_keys( $index );
	if ( ! $needles ) {
		return array(
			'index' => array(),
			'regex' => '',
		);
	}

	// بلندترین نام اول: «رودبار جنوب» پیش از «رودبار» برنده می‌شود.
	usort(
		$needles,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	$parts = array();
	foreach ( $needles as $needle ) {
		$parts[] = preg_quote( $needle, '~' );
	}

	// مرز واژه: نویسه‌ی پیش/پس از نام نباید حرف، رقم یا نیم‌فاصله باشد.
	$regex = '~(?<![\p{L}\p{N}\p{M}\x{200C}])(?:' . implode( '|', $parts ) . ')(?![\p{L}\p{N}\p{M}\x{200C}])~u';

	return array(
		'index' => $index,
		'regex' => $regex,
	);
}

/**
 * نشانی یک مقصد.
 *
 * @param array $candidate کاندید.
 * @return string نشانی یا رشته‌ی تهی.
 */
function sa_autolink_target_url( $candidate ) {
	if ( ! empty( $candidate['term'] ) ) {
		$link = get_term_link( (int) $candidate['term'], 'province_tax' );
		return ( ! is_wp_error( $link ) && $link ) ? (string) $link : '';
	}
	if ( empty( $candidate['id'] ) ) {
		return '';
	}
	$link = get_permalink( (int) $candidate['id'] );
	return $link ? (string) $link : '';
}

/**
 * کلید یکتا برای یک مقصد.
 *
 * @param array $candidate کاندید.
 * @return string
 */
function sa_autolink_key( $candidate ) {
	return $candidate['type'] . ':' . ( ! empty( $candidate['term'] ) ? 't' . (int) $candidate['term'] : (int) $candidate['id'] );
}

/**
 * کلید مقایسه‌ی نشانی (مسیر، بدون اسلشِ پایانی) — برای شناختن لینک‌های موجود.
 *
 * @param string $url نشانی.
 * @return string
 */
function sa_autolink_url_key( $url ) {
	$url  = (string) $url;
	$path = wp_parse_url( $url, PHP_URL_PATH );
	$key  = is_string( $path ) && '' !== $path ? $path : $url;
	$key  = (string) preg_replace( '~^https?://[^/]+~i', '', $key );
	return function_exists( 'untrailingslashit' ) ? untrailingslashit( $key ) : rtrim( $key, '/' );
}

/* -------------------------------------------------------------------------
 * موتورِ جایگزاری
 * ---------------------------------------------------------------------- */

/**
 * آیا پیش از این نام، قرینه‌ی «شهرستان/شهر/استان/بخش/دهستان» آمده است؟
 *
 * برای نام‌های سه‌حرفی و کوتاه‌تر لازم است: «دیر» در «دیر یا زود» نباید
 * به شهرستان دیر لینک شود، اما «شهرستان دیر» باید.
 *
 * @param string $subject متن نرمال‌شده.
 * @param int    $offset  مکانِ بایتیِ آغازِ تطبیق.
 * @return bool
 */
function sa_autolink_has_context( $subject, $offset ) {
	$offset = (int) $offset;
	if ( $offset <= 0 ) {
		return false;
	}
	$start = max( 0, $offset - 60 );
	$chunk = substr( $subject, $start, $offset - $start );
	// اگر از وسط یک نویسه‌ی چندبایتی بریده شده باشد، بایت‌های ادامه را بنداز.
	$chunk = (string) preg_replace( '~^[\x80-\xBF]+~', '', $chunk );
	if ( '' === $chunk ) {
		return false;
	}
	return (bool) preg_match( '~(?:شهرستان|استان|شهر|بخش|دهستان|روستا)[\s\x{200C}]+$~u', $chunk );
}

/**
 * قرینه‌ی پیش از نام: «استان/شهرستان/شهر…».
 *
 * برای حل تداخلِ نام‌های مشترکِ استان و شهرستان (مثل اصفهان) به‌کار می‌رود:
 * «استان اصفهان» یعنی صفحه‌ی استان، «شهرستان اصفهان» یعنی صفحه‌ی شهرستان.
 *
 * @param string $subject متن نرمال‌شده.
 * @param int    $offset  مکانِ بایتیِ آغازِ تطبیق.
 * @return string province|city|''
 */
function sa_autolink_context_kind( $subject, $offset ) {
	$offset = (int) $offset;
	if ( $offset <= 0 ) {
		return '';
	}
	$start = max( 0, $offset - 60 );
	$chunk = substr( $subject, $start, $offset - $start );
	// اگر از وسط یک نویسه‌ی چندبایتی بریده شده باشد، بایت‌های ادامه را بنداز.
	$chunk = (string) preg_replace( '~^[\x80-\xBF]+~', '', $chunk );
	if ( '' === $chunk ) {
		return '';
	}
	if ( preg_match( '~(?:^|\s)استان[\s\x{200C}]+$~u', $chunk ) ) {
		return 'province';
	}
	if ( preg_match( '~(?:شهرستان|شهر|بخش|دهستان)[\s\x{200C}]+$~u', $chunk ) ) {
		return 'city';
	}
	return '';
}

/**
 * لینک‌سازی داخل یک گره‌ی متنی (بدون تگ).
 *
 * @param string $text  متن.
 * @param array  $state وضعیتِ صفحه (ارجاعی: index، regex، used، count…).
 * @return string
 */
function sa_autolink_fragment( $text, &$state ) {
	$text = (string) $text;
	if ( '' === $text || empty( $state['regex'] ) ) {
		return $text;
	}

	$norm    = sa_autolink_normalize( $text );
	$subject = $norm['text'];
	if ( '' === $subject ) {
		return $text;
	}
	$map    = $norm['map'];
	$slen   = strlen( $subject );
	$tlen   = strlen( $text );
	$out    = '';
	$last   = 0;  // آخرین مکانِ بایتیِ متن اصلی که به خروجی رفته است.
	$pos    = 0;  // مکان جست‌وجو در متن نرمال‌شده.

	while ( $pos < $slen ) {
		if ( ! preg_match( $state['regex'], $subject, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
			break;
		}
		$needle = $m[0][0];
		$begin  = (int) $m[0][1];
		$end    = $begin + strlen( $needle );
		$pos    = $end; // تطبیق‌های بعدی همپوشانی ندارند.

		if ( ! isset( $state['index'][ $needle ] ) ) {
			continue;
		}

		$list = $state['index'][ $needle ];
		if ( count( $list ) > 1 ) {
			// نام مشترک (مثلاً «اصفهان» هم استان است هم شهرستان): قرینه تعیین می‌کند.
			$kind = sa_autolink_context_kind( $subject, $begin );
			if ( $kind ) {
				$ordered = array();
				foreach ( $list as $candidate ) {
					if ( $kind === $candidate['type'] ) {
						$ordered[] = $candidate;
					}
				}
				foreach ( $list as $candidate ) {
					if ( $kind !== $candidate['type'] ) {
						$ordered[] = $candidate;
					}
				}
				$list = $ordered;
			}
		}

		$chosen = null;
		foreach ( $list as $candidate ) {
			$key = sa_autolink_key( $candidate );
			if ( isset( $state['used'][ $key ] ) ) {
				continue; // هر مقصد یک‌بار در صفحه.
			}
			if ( $candidate['id'] && (int) $candidate['id'] === (int) $state['current_id'] ) {
				continue; // لینک به خودِ صفحه ممنوع.
			}
			if ( ! empty( $candidate['ctx'] ) && ! sa_autolink_has_context( $subject, $begin ) ) {
				continue; // نام کوتاه بدون قرینه.
			}
			$chosen        = $candidate;
			$chosen['key'] = $key;
			break;
		}
		if ( null === $chosen ) {
			continue;
		}

		if ( $state['count'] >= $state['max'] ) {
			break; // سقفِ تعداد لینک در هر صفحه.
		}

		$url = sa_autolink_target_url( $chosen );
		if ( '' === $url ) {
			continue;
		}
		$url_key = sa_autolink_url_key( $url );
		if ( isset( $state['prelinked'][ $url_key ] ) || ( '' !== $state['current_key'] && $url_key === $state['current_key'] ) ) {
			// این مقصد همین حالا در متن لینک شده است؛ لینک دوم نمی‌سازیم.
			$state['used'][ $chosen['key'] ] = true;
			continue;
		}

		$start  = isset( $map[ $begin ] ) ? (int) $map[ $begin ] : 0;
		$finish = isset( $map[ $end ] ) ? (int) $map[ $end ] : $tlen;
		if ( $finish <= $start ) {
			continue;
		}

		$anchor = substr( $text, $start, $finish - $start );
		if ( false !== strpos( $anchor, '&' ) ) {
			$anchor = html_entity_decode( $anchor, ENT_QUOTES, 'UTF-8' );
		}

		$out .= substr( $text, $last, $start - $last );
		$out .= '<a class="sa-autolink sa-autolink--' . esc_attr( $chosen['type'] ) . '" href="' . esc_url( $url ) . '"'
			. ' title="' . esc_attr( $chosen['label'] ) . '" data-sa-autolink="' . esc_attr( $chosen['type'] ) . '">'
			. esc_html( $anchor ) . '</a>';

		$last                            = $finish;
		$state['used'][ $chosen['key'] ] = true;
		$state['count']++;
	}

	if ( 0 === $last ) {
		return $text;
	}
	return $out . substr( $text, $last );
}

/**
 * وضعیتِ لینک‌سازی برای یک بار نمایش محتوا.
 *
 * @param string $content محتوای کامل (برای شناختن لینک‌های موجود).
 * @return array
 */
function sa_autolink_state( $content ) {
	$targets = sa_autolink_targets();
	$state   = array(
		'index'       => isset( $targets['index'] ) ? $targets['index'] : array(),
		'regex'       => isset( $targets['regex'] ) ? $targets['regex'] : '',
		'used'        => array(),
		'count'       => 0,
		'max'         => (int) apply_filters( 'sa_autolink_max_links', 30 ),
		'current_id'  => (int) get_the_ID(),
		'current_key' => '',
		'prelinked'   => array(),
	);

	$current_url = $state['current_id'] ? get_permalink( $state['current_id'] ) : '';
	if ( $current_url ) {
		$state['current_key'] = sa_autolink_url_key( $current_url );
	}

	// لینک‌هایی که از پیش در متن هستند: مقصدشان دیگر لینک خودکار نمی‌گیرد.
	if ( preg_match_all( '~<a\b[^>]*\shref=(["\'])([^"\']+)\1~i', (string) $content, $matches ) ) {
		foreach ( $matches[2] as $href ) {
			$state['prelinked'][ sa_autolink_url_key( html_entity_decode( $href, ENT_QUOTES, 'UTF-8' ) ) ] = true;
		}
	}

	return $state;
}

/**
 * فیلتر اصلی: لینک‌سازی خودکار در بدنه‌ی محتوا.
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_autolink_content( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! sa_autolink_enabled() || ! is_singular() ) {
		return $content;
	}
	if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
		return $content;
	}
	if ( false === strpos( $content, '<' ) ) {
		return sa_autolink_text_only( $content );
	}

	$state = sa_autolink_state( $content );
	if ( empty( $state['regex'] ) ) {
		return $content;
	}

	// بلوک‌هایی که محتوایشان هرگز نباید لمس شود، موقتاً با نشانه جایگزین می‌شوند
	// (اسکریپت ممکن است شامل «<» و «>» باشد و پیمایش را از ریخت بیندازد).
	$keep    = array();
	$content = sa_autolink_protect_blocks( $content, $keep );

	// شکستن به تگ/متن؛ توضیح‌های HTML هم یک تکه‌ی واحد به‌شمار می‌آیند.
	$tokens = preg_split( '~(<!--.*?-->|<!\[CDATA\[.*?\]\]>|<[^>]*>)~su', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $tokens ) ) {
		return $content; // UTF-8 نامعتبر — چیزی را خراب نمی‌کنیم.
	}

	$skip_tags  = sa_autolink_skip_tags();
	$block_tags = sa_autolink_block_tags();
	$stack      = array(); // array( array( name, skip ), … ).

	foreach ( $tokens as $i => $token ) {
		if ( '' === $token || '<' !== substr( $token, 0, 1 ) ) {
			$skip = false;
			foreach ( $stack as $open ) {
				if ( $open['skip'] ) {
					$skip = true;
					break;
				}
			}
			if ( $skip ) {
				continue;
			}
			$tokens[ $i ] = sa_autolink_fragment( $token, $state );
			continue;
		}

		if ( ! preg_match( '~^<\s*(/?)\s*([a-zA-Z][a-zA-Z0-9:.-]*)~', $token, $tag ) ) {
			continue; // توضیح، <![CDATA[ و چیزهای عجیب — نادیده.
		}
		$name = strtolower( $tag[2] );

		if ( '/' === $tag[1] ) {
			// بستن: آخرین عنصرِ باز را از پشته بردار.
			if ( $stack ) {
				array_pop( $stack );
			}
			continue;
		}

		$self_closed = (bool) preg_match( '~/\s*>$~', $token );
		if ( in_array( $name, $block_tags, true ) ) {
			$stack = array(); // بلوک تازه یعنی عنصر قبلی بسته شده است.
		}
		if ( $self_closed ) {
			continue;
		}
		$skip = in_array( $name, $skip_tags, true ) || sa_autolink_tag_disabled( $token );
		$stack[] = array(
			'name' => $name,
			'skip' => $skip,
		);
	}

	return sa_autolink_restore_blocks( implode( '', $tokens ), $keep );
}

/**
 * بیرون‌کشیدنِ بلوک‌های حساس (اسکریپت، استایل، iframe، SVG…) پیش از پیمایش.
 *
 * @param string $content محتوا.
 * @param array  $keep    نشانه => بلوک (ارجاعی).
 * @return string
 */
function sa_autolink_protect_blocks( $content, &$keep ) {
	$out = preg_replace_callback(
		'~<(script|style|iframe|noscript|template|svg|math)\b.*?</\1\s*>~isu',
		function ( $m ) use ( &$keep ) {
			$key            = "\x01sa-keep-" . count( $keep ) . "\x01";
			$keep[ $key ]   = $m[0];
			return $key;
		},
		$content
	);
	return is_string( $out ) ? $out : $content;
}

/**
 * بازگرداندنِ بلوک‌های بیرون‌کشیده‌شده.
 *
 * @param string $content محتوای پردازش‌شده.
 * @param array  $keep    نشانه => بلوک.
 * @return string
 */
function sa_autolink_restore_blocks( $content, $keep ) {
	if ( ! $keep ) {
		return $content;
	}
	return str_replace( array_keys( $keep ), array_values( $keep ), $content );
}

/**
 * محتوای بدون تگ (نادر، اما ممکن است): همان قانون‌ها بدون پیمایشِ تگ.
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_autolink_text_only( $content ) {
	$state = sa_autolink_state( $content );
	if ( empty( $state['regex'] ) ) {
		return $content;
	}
	return sa_autolink_fragment( $content, $state );
}

/**
 * آیا این تگ خودش لینک‌سازی را خاموش کرده است؟
 *
 * @param string $tag تگ کامل، مثلاً `<div class="sa-autolink-off">`.
 * @return bool
 */
function sa_autolink_tag_disabled( $tag ) {
	if ( preg_match( '~data-sa-autolink\s*=\s*(["\'])\s*off\s*\1~i', $tag ) ) {
		return true;
	}
	if ( preg_match( '~\sclass\s*=\s*(["\'])[^"\']*\bsa-autolink-off\b[^"\']*\1~i', $tag ) ) {
		return true;
	}
	return false;
}

add_filter( 'the_content', 'sa_autolink_content', 14 );

/**
 * شمارش لینک‌های خودکارِ یک محتوا — برای گزارش سلامت محتوا و تست‌ها.
 *
 * @param string $content محتوای خروجی (بعد از فیلتر).
 * @return array{city:int,province:int,attraction:int,total:int}
 */
function sa_autolink_count( $content ) {
	$counts = array(
		'city'       => 0,
		'province'   => 0,
		'attraction' => 0,
		'total'      => 0,
	);
	if ( preg_match_all( '~<a\b[^>]*class="[^"]*\bsa-autolink\b[^"]*"~i', (string) $content, $m ) ) {
		foreach ( $m[0] as $tag ) {
			if ( false !== stripos( $tag, 'sa-autolink--city' ) ) {
				$counts['city']++;
			} elseif ( false !== stripos( $tag, 'sa-autolink--province' ) ) {
				$counts['province']++;
			} elseif ( false !== stripos( $tag, 'sa-autolink--attraction' ) ) {
				$counts['attraction']++;
			}
			$counts['total']++;
		}
	}
	return $counts;
}
