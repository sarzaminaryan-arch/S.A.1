<?php
/**
 * ارجاع‌ها و پانویس‌ها (v2.2.0 — فاز ۶: یکپارچه‌سازی منابع)
 *
 * فاز ۶، ۵ شرط:
 * ۱) «پانویس‌ها» و «منابع» یک فهرست شوند و شماره‌ی ارجاع‌های داخل متن به همین فهرست وصل شوند.
 * ۲) منبع تکراری یک بار بیاید و شماره‌ها دوباره مرتب شوند.
 * ۳) google.com و maps.google.com منبع حساب نشوند — لینک نقشه فقط در بخش نقشه بماند.
 * ۴) ارجاع شکسته (مثل «۳۱ ۴۹)» یا «2 ۴)» یا «۲ ۲») در رندر درست شود یا در گزارش بیاید.
 * ۵) اگر منبع فقط دامنه است (https://example.com بدون مسیر)، در گزارش بیاید.
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
 */
function sa_citations_take_over() {
	if ( sa_citations_enabled() ) {
		remove_filter( 'the_content', 'sa_content_external_links', 13 );
	}
}
add_action( 'wp_loaded', 'sa_citations_take_over' );

/**
 * نام خوانا برای دامنه‌ی منبع.
 *
 * @param string $host دامنه.
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
		return false;
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
 * تشخیص ارجاعِ شکستهٔ عددی در یک قطعه متن (مثل «۳۱ ۴۹)» یا «۲ ۲»).
 * فقط برای گزارش سلامت — رندر جداگانه ترمیم می‌کند.
 *
 * @param string $text قطعه متن.
 * @return bool
 */
function sa_is_broken_citation_fragment( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return false;
	}
	// حاوی پرانتزِ بسته یا دو عددِ فاصله‌دار؟
	if ( false !== strpos( $text, ')' ) ) {
		$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $text ) : array();
		if ( count( $nums ) >= 1 && preg_match( '/[0-9۰-۹]\s+[0-9۰-۹]/u', $text ) ) {
			return true;
		}
		// «۳۱ ۴۹)» → دو عدد با ) انتهایی
		if ( count( $nums ) >= 2 ) {
			return true;
		}
	}
	if ( preg_match( '/[0-9۰-۹]\s+[0-9۰-۹]/u', $text ) ) {
		$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $text ) : array();
		// «۲ ۲» — دو عددِ یکسان یا متفاوت با فاصله، بدون لینک
		if ( count( $nums ) >= 2 ) {
			return true;
		}
	}
	return false;
}

/**
 * ترمیم <sup>های شکسته مثل <sup>۳۱ ۴۹)</sup> یا <sup>۲ ۲</sup> به چند <sup> سالم.
 *
 * @param string   $content محتوا.
 * @param callable $marker سازندهٔ مارکر (int -> string).
 * @return string
 */
function sa_fix_broken_sup_citations( $content, $marker ) {
	if ( false === stripos( $content, '<sup' ) ) {
		return $content;
	}
	$out = preg_replace_callback(
		'#<sup\b([^>]*)>(.*?)</sup>#isu',
		function ( $m ) use ( $marker ) {
			$attrs = $m[1];
			$inner = $m[2];
			// اگر همین سوپ قبلاً ساختارِ سالمِ پروژه است (sa-cite + لینک)، دست نزن — مرحلهٔ بعدی خودش تبدیل می‌کند.
			if ( false !== stripos( $attrs, 'sa-cite' ) || false !== stripos( $inner, 'href=' ) ) {
				return $m[0];
			}
			$text = trim( wp_strip_all_tags( $inner ) );
			if ( '' === $text ) {
				return $m[0];
			}
			// فقط زمانی شکسته است که ۲ عدد یا بیشتر داشته باشد یا «) » انتهایی با الگوی عددی.
			$has_multi = (bool) preg_match( '/[0-9۰-۹]\s+[0-9۰-۹]/u', $text );
			$has_paren = false !== strpos( $text, ')' );
			if ( ! $has_multi && ! $has_paren ) {
				return $m[0];
			}
			$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $text ) : array();
			if ( count( $nums ) < 1 ) {
				return $m[0];
			}
			// اگر فقط یک عددِ تنها با ) مثل «)۱۲» یا «۱۲)» است، همان یکی را درست کن.
			// برای «۲ ۲» تکراری، یکتا کن.
			$unique = array();
			$seen   = array();
			foreach ( $nums as $n ) {
				$n = (int) $n;
				if ( $n <= 0 ) {
					continue;
				}
				if ( ! isset( $seen[ $n ] ) ) {
					$seen[ $n ] = true;
					$unique[]   = $n;
				}
			}
			if ( empty( $unique ) ) {
				return $m[0];
			}
			// اگر در اصل فقط یک عدد بوده و شکسته تلقی شده (مثلاً «12)»)، همان یکی بس است.
			$parts = array();
			foreach ( $unique as $num ) {
				$parts[] = $marker( $num );
			}
			return implode( ' ', $parts );
		},
		$content
	);
	return is_string( $out ) ? $out : $content;
}

/**
 * ترمیم ارجاع‌های شکستهٔ متنی خارج از تگ مثل « ۳۱ ۴۹) » که به‌صورت متنِ ساده جا مانده.
 *
 * @param string   $content محتوا.
 * @param callable $marker سازندهٔ مارکر.
 * @return string
 */
function sa_fix_broken_text_citations( $content, $marker ) {
	// برای اینکه تگ‌ها را خراب نکنیم، محتوا را به قطعاتِ تگ/متن می‌شکنیم.
	$parts = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return $content;
	}
	$out = '';
	foreach ( $parts as $idx => $seg ) {
		// تگ‌ها (فرد) را دست‌نخورده برگردان.
		if ( $idx % 2 === 1 ) {
			$out .= $seg;
			continue;
		}
		// فقط متنِ بیرونِ تگ‌ها — الگوی «عدد فاصله عدد )» را بگیر.
		// مثال: «۳۱ ۴۹)» یا «2 ۴)»  — حداقل ۲ عدد با فاصله و ) انتهایی.
		$fixed = preg_replace_callback(
			'/([0-9۰-۹]+(?:\s+[0-9۰-۹]+)+)\)/u',
			function ( $m ) use ( $marker ) {
				$seq = $m[1];
				$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $seq ) : array();
				if ( count( $nums ) < 2 ) {
					return $m[0];
				}
				$unique = array();
				$seen   = array();
				foreach ( $nums as $n ) {
					$n = (int) $n;
					if ( $n <= 0 ) {
						continue;
					}
					if ( ! isset( $seen[ $n ] ) ) {
						$seen[ $n ] = true;
						$unique[]   = $n;
					}
				}
				if ( empty( $unique ) ) {
					return $m[0];
				}
				$reps = array();
				foreach ( $unique as $num ) {
					$reps[] = $marker( $num );
				}
				return implode( ' ', $reps );
			},
			$seg
		);
		$out .= is_string( $fixed ) ? $fixed : $seg;
	}
	return $out;
}

/**
 * استخراجِ گزارشِ ارجاع‌های شکسته از محتوا (برای صفحهٔ سلامت).
 *
 * @param string   $content محتوا.
 * @param int|null $total_sources تعداد کل منابع (برای تشخیصِ عددِ خارج از بازه).
 * @return array[] هر مورد: [ 'snippet'=>…, 'numbers'=>int[] ]
 */
function sa_detect_broken_citations( $content, $total_sources = null ) {
	$issues = array();
	$content = (string) $content;
	if ( '' === $content ) {
		return $issues;
	}
	// ۱) <sup>های شکسته.
	if ( preg_match_all( '#<sup\b[^>]*>(.*?)</sup>#isu', $content, $mm ) ) {
		foreach ( $mm[0] as $idx => $full ) {
			$inner = $mm[1][ $idx ];
			if ( false !== stripos( $full, 'sa-cite' ) || false !== stripos( $inner, 'href=' ) ) {
				continue;
			}
			$text = trim( wp_strip_all_tags( $inner ) );
			if ( '' === $text ) {
				continue;
			}
			if ( sa_is_broken_citation_fragment( $text ) ) {
				$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $text ) : array();
				$issues[] = array(
					'snippet' => mb_substr( $full, 0, 120 ),
					'numbers' => $nums,
					'kind'    => 'sup',
				);
			}
		}
	}
	// ۲) متنِ سادهٔ «۳۱ ۴۹)» یا الگوهای مشابه خارج از تگ.
	$text_only = wp_strip_all_tags( $content );
	if ( preg_match_all( '/[0-9۰-۹]+(?:\s+[0-9۰-۹]+)+\)/u', $text_only, $mt ) ) {
		foreach ( $mt[0] as $hit ) {
			$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $hit ) : array();
			if ( count( $nums ) >= 2 ) {
				$issues[] = array(
					'snippet' => mb_substr( $hit, 0, 120 ),
					'numbers' => $nums,
					'kind'    => 'text',
				);
			}
		}
	}
	// ۲-ب) «۲ ۲» بدونِ پرانتز — دو عددِ کوچکِ فاصله‌دار (نمونهٔ صریحِ شرطِ ۴).
	if ( preg_match_all( '/(?<![0-9۰-۹])([0-9۰-۹]{1,2})\\s+([0-9۰-۹]{1,2})(?![0-9۰-۹])/u', $text_only, $mp ) ) {
		foreach ( $mp[0] as $hit ) {
			$nums = function_exists( 'sa_extract_citation_numbers' ) ? sa_extract_citation_numbers( $hit ) : array();
			if ( count( $nums ) === 2 ) {
				$n1 = (int) $nums[0];
				$n2 = (int) $nums[1];
				if ( $n1 >= 1 && $n1 <= 60 && $n2 >= 1 && $n2 <= 60 ) {
					$already = false;
					foreach ( $issues as $prev ) {
						if ( isset( $prev['snippet'] ) && false !== strpos( $prev['snippet'], $hit ) ) {
							$already = true;
							break;
						}
					}
					if ( ! $already ) {
						$issues[] = array(
							'snippet' => mb_substr( $hit, 0, 120 ),
							'numbers' => $nums,
							'kind'    => 'text-dup',
						);
					}
				}
			}
		}
	}
	// ۳) اعدادِ خارج از بازه (اگر total داده شده).
	if ( null !== $total_sources ) {
		foreach ( $issues as &$iss ) {
			$out_of_range = array();
			foreach ( (array) $iss['numbers'] as $n ) {
				if ( (int) $n > (int) $total_sources || (int) $n <= 0 ) {
					$out_of_range[] = (int) $n;
				}
			}
			$iss['out_of_range'] = $out_of_range;
		}
		unset( $iss );
	}
	return $issues;
}

/**
 * تبدیل ارجاع‌های بیرونیِ بدنه به پانویس شماره‌دار + ساخت فهرست یکپارچه.
 *
 * @param string $content محتوای مقاله.
 * @return string
 */
function sa_citations_filter( $content ) {
	$content = (string) $content;

	if ( is_admin() || is_feed() || ! sa_citations_enabled() ) {
		return $content;
	}
	if ( ! is_singular() ) {
		return $content;
	}
	// زود برگرد فقط اگر مطمئناً هیچ لینک و هیچ ارجاعِ شکسته‌ای نیست.
	$has_link  = false !== stripos( $content, '<a' ) || false !== stripos( $content, '<sup' );
	$has_broken = false !== strpos( $content, ')' ) && (bool) preg_match( '/[0-9۰-۹]\s+[0-9۰-۹]/u', $content );
	$has_broken = (bool) preg_match( '/[0-9۰-۹]\\s+[0-9۰-۹]/u', $content );
		return $content;
	}

	$input = $content;

	// شناسهٔ نوشته برای خواندنِ sa_sources.
	$post_id = 0;
	if ( function_exists( 'get_the_ID' ) ) {
		$post_id = (int) get_the_ID();
	}
	if ( $post_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
		$post_id = (int) get_queried_object_id();
	}
	if ( $post_id <= 0 && isset( $GLOBALS['post']->ID ) ) {
		$post_id = (int) $GLOBALS['post']->ID;
	}

	// فهرست یکپارچه: متا + لینک‌های درون متن (مرتب، بدون تکرار، بدون google).
	$unified = array();
	if ( $post_id > 0 && function_exists( 'sa_build_unified_sources' ) ) {
		$unified = sa_build_unified_sources( $post_id, $content );
	} elseif ( $post_id > 0 && function_exists( 'sa_get_sources' ) ) {
		$unified = sa_get_sources( $post_id );
	}

	// نگاشتِ نرمال → شمارهٔ ۱-پایه برای جای‌گذاریِ پانویس‌ها.
	$norm_to_idx = array();
	foreach ( $unified as $idx => $src ) {
		if ( ! empty( $src['url'] ) && function_exists( 'sa_normalize_source_url' ) ) {
			$norm = sa_normalize_source_url( $src['url'] );
			if ( ! isset( $norm_to_idx[ $norm ] ) ) {
				$norm_to_idx[ $norm ] = $idx + 1;
			}
		} elseif ( ! empty( $src['url'] ) ) {
			$norm = strtolower( trim( (string) $src['url'] ) );
			if ( ! isset( $norm_to_idx[ $norm ] ) ) {
				$norm_to_idx[ $norm ] = $idx + 1;
			}
		}
	}

	$marker = function ( $i ) {
		$n = (int) $i;
		if ( $n <= 0 ) {
			$n = 1;
		}
		return '<sup class="sa-ref"><a href="#sa-ref-' . $n . '" aria-label="پانویس ' . $n . '">'
			. esc_html( sa_fa_digits( (string) $n ) ) . '</a></sup>';
	};

	// پیش‌ترمیم: اگر unified داریم، عددهای شکسته به همان شماره‌های ۱..N اشاره دارند.
	// اگر unified خالی است، باز هم شکسته‌ها را به مارکر تبدیل می‌کنیم (حتی اگر لنگر نداشته باشند — گزارش سلامت آن‌ها را می‌گیرد).
	$content = sa_fix_broken_sup_citations( $content, $marker );
	$content = sa_fix_broken_text_citations( $content, $marker );

	// گام ۱ — الگوی خود پروژه: <sup class="sa-cite"><a href="URL">۱</a></sup>.
	$content = preg_replace_callback(
		'#<sup\b[^>]*class=([\"\'])[^\"\']*\bsa-cite\b[^\"\']*\1[^>]*>\s*<a\b[^>]*href=([\"\'])([^\"\']+)\2[^>]*>.*?</a>\s*</sup>#isu',
		function ( $m ) use ( $norm_to_idx, $marker ) {
			$url = html_entity_decode( $m[3], ENT_QUOTES, 'UTF-8' );
			if ( ! sa_citation_is_external( $url ) ) {
				return $m[0];
			}
			if ( function_exists( 'sa_is_forbidden_source_url' ) && sa_is_forbidden_source_url( $url ) ) {
				return '';
			}
			if ( function_exists( 'sa_is_google_source_url' ) && sa_is_google_source_url( $url ) ) {
				return '';
			}
			$norm = function_exists( 'sa_normalize_source_url' ) ? sa_normalize_source_url( $url ) : strtolower( trim( $url ) );
			if ( isset( $norm_to_idx[ $norm ] ) ) {
				return $marker( $norm_to_idx[ $norm ] );
			}
			// لینکِ درون متنی که در متا نبود اما خارجی و مجاز بود — اگر unified تهی بود، آن را به‌عنوان منبعِ جدید ثبت کنیم؟
			// چون unified قبلاً از همین محتوا ساخته شده، این شاخه فقط وقتی می‌افتد که unified تهی باشد.
			return $marker( 1 );
		},
		$content
	);

	// گام ۲ — هر لینک بیرونی آزادِ باقی‌مانده.
	$content = preg_replace_callback(
		'#<a\b[^>]*href=([\"\'])([^\"\']+)\1[^>]*>(.*?)</a>#isu',
		function ( $m ) use ( $norm_to_idx, $marker ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( ! sa_citation_is_external( $url ) ) {
				return $m[0];
			}
			if ( function_exists( 'sa_is_forbidden_source_url' ) && sa_is_forbidden_source_url( $url ) ) {
				return $m[3];
			}
			if ( function_exists( 'sa_is_google_source_url' ) && sa_is_google_source_url( $url ) ) {
				return $m[3];
			}
			$norm = function_exists( 'sa_normalize_source_url' ) ? sa_normalize_source_url( $url ) : strtolower( trim( $url ) );
			if ( isset( $norm_to_idx[ $norm ] ) ) {
				return $m[3] . $marker( $norm_to_idx[ $norm ] );
			}
			// لینکِ مجازِ خارج از unified — بدون افزودنِ منبعِ جدید، فقط متن را نگه دار (ایمن).
			return $m[3];
		},
		$content
	);

	if ( ! is_string( $content ) || '' === $content ) {
		return $input;
	}

	// اگر هیچ منبعی نداریم ولی محتوا دست‌خورده (مثلاً گوگل حذف شده یا شکسته ترمیم شده)، همان محتوا برگردد.
	if ( empty( $unified ) ) {
		return $content;
	}

	// ساختِ فهرستِ یکپارچه (۵ شرط: تکرار حذف، google حذف، دامنه-تنها گزارش می‌شود اما نمایش می‌ماند).
	$items = '';
	foreach ( $unified as $idx => $src ) {
		$n     = $idx + 1;
		$url   = isset( $src['url'] ) ? $src['url'] : '';
		$title = isset( $src['title'] ) ? trim( (string) $src['title'] ) : '';
		if ( $url ) {
			$host = (string) wp_parse_url( $url, PHP_URL_HOST );
			$host = (string) preg_replace( '/^www\./', '', strtolower( $host ) );
			$rel  = function_exists( 'sa_source_rel' ) ? sa_source_rel( $url ) : 'nofollow noopener external';
			// نمایش: «عنوان — host» اگر عنوان متفاوت از URL باشد، وگرنه فقط host.
			$label = '';
			if ( '' !== $title && $title !== $url && $host !== strtolower( $title ) ) {
				$label = esc_html( $title ) . ' <span class="sa-sources__sep" aria-hidden="true">—</span> ';
			}
			$items .= '<li id="sa-ref-' . (int) $n . '" class="sa-footnotes__item">'
				. '<span class="sa-footnotes__n">' . esc_html( sa_fa_digits( (string) $n ) ) . '</span> '
				. $label
				. '<a href="' . esc_url( $url ) . '" rel="' . esc_attr( $rel ) . '" target="_blank" dir="ltr">'
				. esc_html( sa_citation_source_label( $host ) ) . '</a> '
				. '<span class="sa-footnotes__host" dir="ltr">' . esc_html( $host ) . '</span></li>';
		} else {
			$items .= '<li id="sa-ref-' . (int) $n . '" class="sa-footnotes__item">'
				. '<span class="sa-footnotes__n">' . esc_html( sa_fa_digits( (string) $n ) ) . '</span> '
				. esc_html( $title ) . '</li>';
		}
	}

	// یک فهرستِ یکتا با id=sources (برای TOC و سئو) + لنگرِ مخفیِ footnotes برای سازگاریِ قدیمی.
	return $content
		. '<section class="sa-footnotes sa-sources" id="sources" aria-labelledby="sa-footnotes-title">'
		. '<h2 id="sa-footnotes-title">منابع</h2>'
		. '<ol class="sa-footnotes__list">' . $items . '</ol></section>'
		;
}
add_filter( 'the_content', 'sa_citations_filter', 13 );

/**
 * بلوک تکراری پایانی («از مردم عزیز شهرستان …، یک درخواست داریم») در ۱۰۹ مقاله
 * عیناً تکرار شده است. داخل <article> بودن یعنی گوگل آن را بخشی از متن یکتای
 * صفحه می‌شمارد؛ داخل <aside> یعنی «مبلمان سایت».
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_community_box_filter( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}
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

/**
 * حذف بخش تکراری «سوالات متداول» از داخل بدنهٔ مقاله زمانی که بخش ساختاریافتهٔ FAQ
 * برای همان نوشته فعال است.
 *
 * @param string $content محتوا.
 * @return string
 */
function sa_strip_duplicate_inline_faq_filter( $content ) {
	$content = (string) $content;
	if ( is_admin() || is_feed() || ! is_singular() ) {
		return $content;
	}
	if ( false === strpos( $content, 'سوالات متداول' ) && false === strpos( $content, 'پرسش‌های متداول' ) ) {
		return $content;
	}
	$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
	if ( $post_id <= 0 || ! function_exists( 'sa_get_faq' ) ) {
		return $content;
	}
	$faq = sa_get_faq( $post_id );
	if ( empty( $faq ) ) {
		return $content;
	}
	$cleaned = preg_replace(
		'#<h2\b[^>]*>\s*(?:سوالات|سؤالات|پرسش‌های)\s+متداول[^<]*</h2>.*?(?=<h2\b|<aside\b|<section\b[^>]*class=[\"\'][^\"\']*sa-(?:footnotes|sources)|\z)#isu',
		'',
		$content
	);
	return is_string( $cleaned ) ? $cleaned : $content;
}
add_filter( 'the_content', 'sa_strip_duplicate_inline_faq_filter', 9 );