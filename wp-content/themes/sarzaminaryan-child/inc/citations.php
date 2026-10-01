<?php
/**
 * ارجاع‌ها و پانویس‌ها (v2.1.0)
 *
 * مسئله‌ی اندازه‌گیری‌شده روی ۱۱۳ مقاله‌ی درون‌ریزی‌شده‌ی این پروژه:
 *
 *   کل لینک بیرونی در بدنه‌ی مقاله‌ها : ۷٬۰۵۹
 *   منابع یکتا پشت آن‌ها             : ۸۸۲
 *   یعنی هر منبع به‌طور میانگین ۸ بار، و در بدترین حالت ۶۰ بار، تکرار شده
 *   میانگین لینک خروجی در هر مقاله   : ۶۲ (حدود یکی به ازای هر ۱۹ واژه)
 *   سهم بزرگ‌ترین دامنه              : ۶۱٪ (fa.wikipedia.org، ۴٬۲۷۹ بار)
 *
 * فیلتر قدیمی `sa_content_external_links` کار درستی می‌کرد (۹۳٪ این لینک‌ها
 * الان هم nofollow می‌گیرند)، اما مسئله‌ی اصلی rel نبود:
 *
 *   ۱. خوانایی — متنی که هر ۱۹ واژه یک لینک آبی دارد، خوانده نمی‌شود.
 *   ۲. بودجه‌ی خزش — ۷٬۰۵۹ یال خروجی از ۱۱۳ صفحه.
 *   ۳. سیگنال اشتقاق — تکرار ۶۰باره‌ی یک نشانی ویکی‌پدیا در یک صفحه،
 *      به‌صورت ماشین‌خوان می‌گوید متن از کجا آمده. این با nofollow عوض نمی‌شود.
 *   ۴. ۴۶۷ لینک (۶٫۶٪) هنوز dofollow بودند، چون دامنه‌شان در فهرست «رسمی» است.
 *
 * این ماژول هر ارجاع درون متن را به یک لنگر داخلی (`#sa-ref-n`) تبدیل می‌کند و
 * نشانی واقعی را فقط **یک‌بار** در بخش «پانویس‌ها» می‌آورد. سیاست `rel` قالب
 * (`sa_source_rel()`) دست‌نخورده باقی می‌ماند: یونسکو و ایرانیکا و آمار همچنان
 * dofollow می‌گیرند، بقیه nofollow. یعنی ۷٬۰۵۹ لینک به ۸۸۲ لینک تبدیل می‌شود،
 * بدون اینکه هیچ منبعی حذف یا پنهان شود.
 *
 * برای خاموش‌کردن: در wp-config.php بنویسید
 *     define( 'SA_CITATIONS', false );
 * آن‌وقت دقیقاً رفتار v2.0.1 برمی‌گردد.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * آیا پانویس‌سازی فعال است؟
 *
 * @return bool
 */
function sa_citations_enabled() {
	$on = defined( 'SA_CITATIONS' ) ? (bool) SA_CITATIONS : true;

	/**
	 * فعال/غیرفعال کردن تبدیل ارجاع‌ها به پانویس.
	 *
	 * @param bool $on وضعیت.
	 */
	return (bool) apply_filters( 'sa_citations_enabled', $on );
}

/**
 * فیلتر قدیمیِ لینک بیرونی را کنار می‌گذارد؛ این ماژول جایش را می‌گیرد.
 * اگر ماژول خاموش شود، فیلتر قدیمی سر جایش می‌ماند و رفتار عوض نمی‌شود.
 */
function sa_citations_take_over() {
	if ( sa_citations_enabled() ) {
		remove_filter( 'the_content', 'sa_content_external_links', 13 );
	}
}
add_action( 'wp_loaded', 'sa_citations_take_over' );

/**
 * نام خوانا برای دامنه‌ی منبع — تا فهرست پانویس‌ها «fa.wikipedia.org» نباشد.
 *
 * @param string $host دامنه (با یا بدون www).
 * @return string
 */
function sa_citation_source_label( $host ) {
	$host = preg_replace( '/^www\./', '', strtolower( (string) $host ) );
	$map  = array(
		'fa.wikipedia.org'  => 'ویکی‌پدیای فارسی',
		'en.wikipedia.org'  => 'ویکی‌پدیای انگلیسی',
		'wikidata.org'      => 'ویکی‌داده',
		'maps.google.com'   => 'گوگل مپ',
		'goo.gl'            => 'گوگل مپ',
		'whc.unesco.org'    => 'یونسکو — میراث جهانی',
		'ich.unesco.org'    => 'یونسکو — میراث ناملموس',
		'irunesco.org'      => 'کمیسیون ملی یونسکو ایران',
		'unesco.org'        => 'یونسکو',
		'amar.org.ir'       => 'مرکز آمار ایران',
		'irna.ir'           => 'خبرگزاری ایرنا',
		'mehrnews.com'      => 'خبرگزاری مهر',
		'isna.ir'           => 'خبرگزاری ایسنا',
		'tasnimnews.com'    => 'خبرگزاری تسنیم',
		'tasnimnews.ir'     => 'خبرگزاری تسنیم',
		'yjc.ir'            => 'باشگاه خبرنگاران جوان',
		'iranicaonline.org' => 'دانشنامه‌ی ایرانیکا',
		'britannica.com'    => 'بریتانیکا',
		'citypopulation.de' => 'CityPopulation',
		'visitiran.ir'      => 'وزارت میراث‌فرهنگی، گردشگری و صنایع‌دستی',
		'mcth.ir'           => 'وزارت میراث‌فرهنگی، گردشگری و صنایع‌دستی',
		'doe.ir'            => 'سازمان حفاظت محیط زیست',
		'gsi.ir'            => 'سازمان زمین‌شناسی کشور',
		'kojaro.com'        => 'کجارو',
		'alibaba.ir'        => 'علی‌بابا',
		'flytoday.ir'       => 'فلای‌تودی',
		'lastsecond.ir'     => 'لست‌سکند',
		'web.archive.org'   => 'آرشیو اینترنت',
	);
	if ( isset( $map[ $host ] ) ) {
		return $map[ $host ];
	}
	foreach ( $map as $key => $label ) {
		if ( strlen( $host ) > strlen( $key ) && substr( $host, -strlen( $key ) - 1 ) === '.' . $key ) {
			return $label;
		}
	}
	return $host;
}

/**
 * آیا این نشانی بیرونی است؟
 *
 * @param string $url نشانی.
 * @return bool
 */
function sa_citation_is_external( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || '#' === $url[0] || '/' === $url[0] ) {
		return false;
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return false; // mailto:، tel:، نسبی.
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( ! $host ) {
		return false;
	}
	if ( $host === $home || substr( $host, -strlen( '.' . $home ) ) === '.' . $home ) {
		return false;
	}
	return true;
}

/**
 * تبدیل ارجاع‌های بیرونیِ بدنه به پانویس شماره‌دار + ساخت فهرست پانویس‌ها.
 *
 * @param string $content محتوای مقاله (پس از do_blocks و wpautop).
 * @return string
 */
function sa_citations_filter( $content ) {
	$content = (string) $content;

	if ( is_admin() || is_feed() || ! sa_citations_enabled() ) {
		return $content;
	}
	if ( false === stripos( $content, '<a' ) ) {
		return $content;
	}
	// فقط صفحه‌ی تکیِ موجودیت‌ها و نوشته‌ها؛ آرشیوها و خلاصه‌ها دست‌نخورده.
	if ( ! is_singular() ) {
		return $content;
	}

	$input = $content;
	$refs  = array(); // url => n.
	$order = array(); // n   => url.
	$n     = 0;

	$register = function ( $url ) use ( &$refs, &$order, &$n ) {
		if ( ! isset( $refs[ $url ] ) ) {
			++$n;
			$refs[ $url ] = $n;
			$order[ $n ]  = $url;
		}
		return $refs[ $url ];
	};

	$marker = function ( $i ) {
		return '<sup class="sa-ref"><a href="#sa-ref-' . (int) $i . '" aria-label="پانویس ' . (int) $i . '">'
			. esc_html( sa_fa_digits( (string) (int) $i ) ) . '</a></sup>';
	};

	// گام ۱ — الگوی خود پروژه: <sup class="sa-cite"><a href="URL">۱</a></sup>.
	$content = preg_replace_callback(
		'#<sup\b[^>]*class=(["\'])[^"\']*\bsa-cite\b[^"\']*\1[^>]*>\s*<a\b[^>]*href=(["\'])([^"\']+)\2[^>]*>.*?</a>\s*</sup>#isu',
		function ( $m ) use ( $register, $marker ) {
			$url = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
			if ( ! sa_citation_is_external( $url ) ) {
				return $m[0];
			}
			return $marker( $register( $url ) );
		},
		$content
	);

	// گام ۲ — هر لینک بیرونی آزادِ باقی‌مانده: متن لنگر می‌ماند، لینک به پانویس تبدیل می‌شود.
	$content = preg_replace_callback(
		'#<a\b[^>]*href=(["\'])([^"\']+)\1[^>]*>(.*?)</a>#isu',
		function ( $m ) use ( $register, $marker ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( ! sa_citation_is_external( $url ) ) {
				return $m[0];
			}
			return $m[3] . $marker( $register( $url ) );
		},
		$content
	);

	// preg_* روی UTF-8 نامعتبر null برمی‌گرداند — در آن حالت چیزی را خراب نکن.
	if ( ! is_string( $content ) || '' === $content ) {
		return $input;
	}
	if ( ! $order ) {
		return $content;
	}

	$items = '';
	foreach ( $order as $i => $url ) {
		$host   = preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$rel    = function_exists( 'sa_source_rel' ) ? sa_source_rel( $url ) : 'nofollow noopener external';
		$items .= '<li id="sa-ref-' . (int) $i . '" class="sa-footnotes__item">'
			. '<span class="sa-footnotes__n">' . esc_html( sa_fa_digits( (string) (int) $i ) ) . '</span> '
			. '<a href="' . esc_url( $url ) . '" rel="' . esc_attr( $rel ) . '" target="_blank">'
			. esc_html( sa_citation_source_label( $host ) ) . '</a> '
			. '<span class="sa-footnotes__host">' . esc_html( $host ) . '</span></li>';
	}

	// عنوان عمداً «پانویس‌ها» است نه «منابع»: قالب خودش یک بخش «منابع» از فیلد
	// متا می‌سازد (sa_sources_section) و دو <h2>منابع</h2> در یک صفحه غلط است.
	return $content
		. '<section class="sa-footnotes" id="footnotes" aria-labelledby="sa-footnotes-title">'
		. '<h2 id="sa-footnotes-title">پانویس‌ها و ارجاع‌های متن</h2>'
		. '<ol class="sa-footnotes__list">' . $items . '</ol></section>';
}
add_filter( 'the_content', 'sa_citations_filter', 13 );

/**
 * بلوک تکراری پایانی («از مردم عزیز شهرستان …، یک درخواست داریم») در ۱۰۹ مقاله
 * عیناً تکرار شده است. داخل <article> بودن یعنی گوگل آن را بخشی از متن یکتای
 * صفحه می‌شمارد؛ داخل <aside> یعنی «مبلمان سایت». محتوا حذف نمی‌شود، فقط
 * برچسب معنایی‌اش درست می‌شود.
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_community_box_filter( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}
	// strpos بایتی است و برای یافتن زیررشته‌ی UTF-8 درست کار می‌کند؛ مهم این است
	// که آفست بایتی با substr بایتی جفت بماند (mb_strpos اینجا باگ می‌سازد).
	if ( false === strpos( $content, 'از مردم عزیز' ) ) {
		return $content;
	}

	$patterns = array(
		'#<h2\b[^>]*>\s*از\s+مردم\s+عزیز.*?</h2>(.*)$#isu',
		'#<h3\b[^>]*>\s*از\s+مردم\s+عزیز.*?</h3>(.*)$#isu',
	);
	foreach ( $patterns as $p ) {
		if ( preg_match( $p, $content, $m, PREG_OFFSET_CAPTURE ) ) {
			$offset = (int) $m[0][1];
			$head   = trim( substr( $content, 0, $offset ) );
			$body   = trim( (string) $m[1][0] );
			if ( '' === $head ) {
				return $content;
			}
			return $head
				. '<aside class="sa-community" role="complementary" aria-labelledby="sa-community-title">'
				. '<h2 id="sa-community-title">از مردم این دیار، یک درخواست داریم</h2>'
				. $body . '</aside>';
		}
	}
	return $content;
}
add_filter( 'the_content', 'sa_community_box_filter', 12 );

/**
 * H1 داخل بدنه‌ی مقاله به H2 تنزل می‌کند.
 *
 * هر ۸۲ مقاله‌ی شهرستانِ درون‌ریزی‌شده با `<h1 class="wp-block-heading">` شروع
 * می‌شوند، در حالی که template-parts/entity/hero.php هم `<h1 class="entry-title">`
 * می‌زند. نتیجه: دو H1 با دو متن متفاوت در هر صفحه. عنوان کوتاه در هیرو می‌ماند
 * (چون بردکرامب و کارت‌ها به آن وابسته‌اند) و عبارت بلندِ کلیدواژه‌دار به H2
 * تبدیل می‌شود — همچنان سرصفحه است و در فهرست مطالب هم می‌آید.
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_single_h1_filter( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}
	if ( false === stripos( $content, '<h1' ) ) {
		return $content;
	}
	$out = preg_replace( '#<h1\b([^>]*)>(.*?)</h1>#isu', '<h2$1 data-sa-was="h1">$2</h2>', $content );
	return ( is_string( $out ) && '' !== $out ) ? $out : $content;
}
add_filter( 'the_content', 'sa_single_h1_filter', 11 );
