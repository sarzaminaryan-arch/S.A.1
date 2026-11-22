<?php
/**
 * Plugin Name:       ترمیم یک‌بارهٔ یادداشت‌های ویرایشی (سرزمین آریان)
 * Plugin URI:        https://github.com/sarzaminaryan-arch/S.A.1
 * Description:       افزونهٔ یک‌بارمصرف برای حذف ایمن یادداشت‌های تولیدی/فرایندی از متن منتشرشدهٔ صفحه‌ها. همان قواعد موتور مرجع `tools/wp-agent/content_fix.py` را اجرا می‌کند: حذف جمله/بند یادداشتی، حذف مورد فهرستی یادداشتی، حذف جملهٔ تکراری درون صفحه، کوتاه‌سازی مورد خلاصه، پاک‌سازی جدول و تیتر خالی. هر تغییر با پشتیبان کامل + گزارش + بازگردانی یک‌کلیکی است؛ بیش از ۸٪ افت حجم یا عبور از واژه‌های ممنوع ⇒ صفحه دست نمی‌خورد و به صف بازنویسی می‌رود. هیچ متن تازه‌ای افزوده نمی‌شود.
 * Version:           1.0.0
 * Author:            Sarzaminaryan
 * Author URI:        https://sarzaminaryan.ir/
 * Text Domain:       sa-editorial-fix
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package SA_Editorial_Fix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SA_EF_VERSION', '1.0.0' );
define( 'SA_EF_MAX_DROP_PCT', 8.0 );
define( 'SA_EF_KEEP_RUNS', 3 );

/**
 * موتور پاک‌سازی + لایهٔ اجرا/پشتیبان/گزارش.
 *
 * پورت وفادارانهٔ `tools/wp-agent/content_fix.py` (نسخهٔ مرجع مخزن).
 * فیکسچرهای `tests/fixtures.json` با همان موتور پایتون تولید و در CI با
 * `tests/test-engine.php` برابری موتور PHP را می‌سنجند.
 */
final class SA_Editorial_Fix {

	/** بدنهٔ الگوی یادداشت (بدون مهارکننده) — پورت NOTE_RE پایتون. */
	const NOTE_BODY = 'پیش از انتشار|نسخهٔ انتشار|نسخه انتشار|بازبینی|نیازمند پژوهش|این فهرست اولیه است|از تعمیم یک رسم'
		. '|تابع مقررات است'
		. '|باید[^.؛]{0,90}?(?:کنترل|بررسی|تطبیق|تکمیل|مستند|به[‌ ]روز|نقل|دریافت|استناد|معرفی)\s*(?:شود|شوند|باشد|باشند|کرد|گردد)'
		. '|باید[^.؛]{0,60}?(?:از منابع|از مرجع|از دستگاه|با منبع|با سال مرجع)'
		. '|(?:منبع|گزارش رسمی|تطبیق|بررسی|کنترل|تأیید|سند)[^.؛]{0,60}?لازم است'
		. '|نیازمند\s+(?:تطبیق|بررسی|دادهٔ|داده|پژوهش|منبع|منابع|سند|به[‌ ]روزرسانی|کنترل|تأیید)'
		. '|به منبع نیاز دار'
		. '|(?:معتبر|منبع|سند|زندگی[‌ ]?نامه)[^.؛]{0,40}ضروری است'
		. '|باید[^.؛]{0,90}?(?:مستند|منابع معتبر|با منبع|از منبع|گزارش رسمی|پرونده|استعلام|کلی[‌ ]?گویی|تعمیم|ادعا)';

	const NOTE_RE = '/' . self::NOTE_BODY . '/u';

	/** بدنهٔ الگوی یادداشت قطعی — پورت STRONG_NOTE_RE پایتون. */
	const STRONG_NOTE_BODY = 'پیش از انتشار|نسخهٔ انتشار|نسخه انتشار|بازبینی|این فهرست اولیه است|از تعمیم یک رسم|دادهٔ پایه|دادهٔ میراثی'
		. '|اعداد بدون منبع|نسخهٔ نهایی|نسخه نهایی|پیش[‌ ]?نویس پژوهشی|این پیش[‌ ]?نویس|متادیتا|دادهٔ خام'
		. '|مصوبهٔ رسمی ملاک است'
		. '|نام[^.؛]{0,40}(?:مستند|سند|تأیید|اثبات)|نیازمند سند|نیازمند مدرک|انتساب حدسی';

	const STRONG_NOTE_RE = '/' . self::STRONG_NOTE_BODY . '/u';

	const ADVICE_RE = '/پیش از (?:سفر|حرکت)|گزارش همان روز|وضعیت راه|آب[‌ ]?و[‌ ]?هوا|بهتر معرفی شود|راهنمای محلی|مسافر|گردشگر|بازدیدکننده/u';

	const BANNED_OUTPUT = array( 'راستش', 'هوش مصنوعی', 'بازبینی انسانی', 'ویرایش انسانی', 'مدل زبانی', 'به عنوان یک مدل', 'as an AI', 'language model' );

	const TARGET_TYPES = array( 'post', 'page', 'province', 'city', 'attraction', 'travel_route', 'local_food', 'souvenir', 'guide' );

	/* ------------------------------------------------------------------ */
	/* موتور متنی (پورت content_audit/content_fix)                         */
	/* ------------------------------------------------------------------ */

	/**
	 * هم‌سازی نویسه‌ها برای سنجش برابری (همان norm پایتون).
	 *
	 * @param string $text متن خام.
	 * @return string متن هم‌سازی‌شده.
	 */
	public static function norm( $text ) {
		$t = (string) $text;
		$t = str_replace( array( 'ي', 'ك' ), array( 'ی', 'ک' ), $t );
		$t = strtr( $t, array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			'٫' => '.', '٬' => ',', "\u{200c}" => ' ',
		) );
		$t = preg_replace( '/\s+/u', ' ', $t );
		return trim( $t );
	}

	/**
	 * متن سادهٔ HTML (حذف تگ/سوپ/دیدگاه) — همان plain پایتون.
	 *
	 * @param string $content_html HTML مقاله.
	 * @return string متن ساده.
	 */
	public static function plain( $content_html ) {
		$t = preg_replace( '#<sup\b[^>]*>.*?</sup>#si', '', (string) $content_html );
		$t = preg_replace( '#</(p|li|h[1-6]|td|th|tr|div)>#i', "\n", $t );
		$t = preg_replace( '#<[^>]+>#', ' ', $t );
		$t = preg_replace( '/<!--.*?-->/s', ' ', $t );
		return html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * جایگزینی تگ‌ها و supها با نشانگر تا نقطه/«؛» درون URLها جمله نشکند.
	 *
	 * @param string $inner درون‌مایهٔ بلوک.
	 * @return array{0:string,1:array<int,string>} متن نشانه‌گذاری‌شده و انبار تگ‌ها.
	 */
	private static function mask( $inner ) {
		$store = array();
		$s     = preg_replace_callback(
			'#<sup\b[^>]*>.*?</sup>#si',
			function ( $m ) use ( &$store ) {
				$store[] = $m[0];
				return "\x02" . ( count( $store ) - 1 ) . "\x03";
			},
			$inner
		);
		$s = preg_replace_callback(
			'#<[^>]+>#',
			function ( $m ) use ( &$store ) {
				$store[] = $m[0];
				return "\x00" . ( count( $store ) - 1 ) . "\x01";
			},
			$s
		);
		return array( $s, $store );
	}

	/**
	 * بازگردانی نشانگرها به تگ‌های اصلی.
	 *
	 * @param string $s     متن نشانه‌گذاری‌شده.
	 * @param array  $store انبار تگ‌ها.
	 * @return string HTML بازسازی‌شده.
	 */
	private static function unmask( $s, $store ) {
		$s = preg_replace_callback(
			'/\x02(\d+)\x03/',
			function ( $m ) use ( $store ) {
				return $store[ (int) $m[1] ];
			},
			$s
		);
		return preg_replace_callback(
			'/\x00(\d+)\x01/',
			function ( $m ) use ( $store ) {
				return $store[ (int) $m[1] ];
			},
			$s
		);
	}

	/**
	 * متن قابل‌مشاهدهٔ متن نشانه‌گذاری‌شده.
	 *
	 * @param string $masked متن نشانه‌گذاری‌شده.
	 * @return string متن سادهٔ هم‌سازی‌شده.
	 */
	private static function visible( $masked ) {
		return self::norm( preg_replace( '/\x02\d+\x03|\x00\d+\x01/', '', $masked ) );
	}

	/**
	 * جداکردن جمله‌ها روی متن نشانه‌گذاری‌شده.
	 *
	 * @param string $masked متن نشانه‌گذاری‌شده.
	 * @return array<int,string> جمله‌ها.
	 */
	private static function sentences( $masked ) {
		$out = array();
		if ( preg_match_all( '/.*?[.!?؟](?:\s*\x02\d+\x03)*(?=\s|$)|.+$/su', $masked, $mm ) ) {
			foreach ( $mm[0] as $s ) {
				if ( trim( $s ) !== '' ) {
					$out[] = $s;
				}
			}
		}
		return $out;
	}

	/**
	 * همهٔ نشانگرهای sup یک متن نشانه‌گذاری‌شده.
	 *
	 * @param string $masked متن نشانه‌گذاری‌شده.
	 * @return string زنجیرهٔ نشانگرهای sup.
	 */
	private static function sups( $masked ) {
		if ( ! preg_match_all( '/\x02\d+\x03/', $masked, $mm ) ) {
			return '';
		}
		return implode( '', $mm[0] );
	}

	/**
	 * آیا متن یک یادداشت فرایندی/تولیدی است؟
	 *
	 * @param string $text متن ساده.
	 * @return bool بله/خیر.
	 */
	public static function is_note( $text ) {
		if ( preg_match( self::STRONG_NOTE_RE, $text ) ) {
			return true;
		}
		return (bool) preg_match( self::NOTE_RE, $text ) && ! preg_match( self:: ADVICE_RE, $text );
	}

	/**
	 * فهرست یادداشت‌های یک متن ساده (برای گزارش/ممیزی).
	 *
	 * @param string $text متن ساده.
	 * @return array<int,string> یادداشت‌ها.
	 */
	public static function find_notes( $text ) {
		$out = array();
		foreach ( preg_split( '/(?<=[.!?؟])\s+|؛/u', (string) $text ) as $piece ) {
			if ( trim( $piece ) === '' ) {
				continue;
			}
			if ( self::is_note( $piece ) ) {
				if ( preg_match( self::STRONG_NOTE_RE, $piece, $m ) || preg_match( self::NOTE_RE, $piece, $m ) ) {
					$out[] = $m[0];
				} else {
					$out[] = mb_substr( $piece, 0, 60 );
				}
			}
		}
		return $out;
	}

	/**
	 * حذف بندهای یادداشتی از یک جمله ('' = کل جمله یادداشتی).
	 *
	 * @param string $sentence جملهٔ نشانه‌گذاری‌شده.
	 * @param array  $log      گزارش تغییرات (با ارجاع).
	 * @return string جملهٔ تمیز.
	 */
	private static function strip_note_clauses( $sentence, &$log ) {
		$sups = self::sups( $sentence );
		$body = trim( preg_replace( '/\s*\x02\d+\x03/', '', $sentence ) );
		$parts = explode( '؛', $body );
		if ( self::is_note( self::visible( $parts[0] ) ) ) {
			if ( preg_match( '/(.+?(?:است|هستند|اند|\x{200c}اند|دارد))\s+و\s+(.+)$/su', $parts[0], $m )
				&& ! self::is_note( self::visible( $m[1] ) )
				&& self::is_note( self::visible( $m[2] ) ) ) {
				$log[]     = 'note tail: و ' . mb_substr( self::visible( $m[2] ), 0, 140 );
				$parts[0]  = $m[1];
				if ( count( $parts ) === 1 ) {
					return rtrim( $parts[0], ' .،' ) . '.' . $sups;
				}
			} else {
				$log[] = 'note sentence: ' . mb_substr( self::visible( $body ), 0, 140 );
				return '';
			}
		}
		$kept = array( $parts[0] );
		foreach ( array_slice( $parts, 1 ) as $part ) {
			if ( self::is_note( self::visible( $part ) ) ) {
				$log[] = 'note clause: ؛' . mb_substr( self::visible( $part ), 0, 140 );
			} else {
				$kept[] = $part;
			}
		}
		if ( count( $kept ) === count( $parts ) ) {
			return $sentence;
		}
		$out = rtrim( implode( '؛', $kept ), ' .،' );
		return $out . '.' . $sups;
	}

	/**
	 * تیترهایی که پیش از پاک‌سازی هم خالی بوده‌اند (دست‌نخورده می‌مانند).
	 *
	 * @param string $html مقاله.
	 * @return array<int,string> تیترهای خالیِ قبلی.
	 */
	private static function empty_headings( $html ) {
		$found = array();
		preg_match_all( '/<h([2-6])\b[^>]*>(.*?)<\/h\1>/s', $html, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $mm as $j => $h ) {
			$level = (int) $h[1][0];
			$start = null;
			foreach ( array_slice( $mm, $j + 1 ) as $n ) {
				if ( (int) $n[1][0] <= $level ) {
					$start = $n[0][1];
					break;
				}
			}
			$seg = ( null === $start ) ? substr( $html, $h[0][1] + strlen( $h[0][0] ) ) : substr( $html, $h[0][1] + strlen( $h[0][0] ), $start - ( $h[0][1] + strlen( $h[0][0] ) ) );
			$seg = preg_replace( '/<!--.*?-->/s', '', $seg );
			if ( self::norm( self::plain( $seg ) ) === '' ) {
				$found[] = self::norm( self::plain( $h[2][0] ) );
			}
		}
		return $found;
	}

	/**
	 * پاک‌سازی سلول‌های «توضیح/مقدار» جدول شاخص‌ها.
	 *
	 * @param string $inner درون‌مایهٔ سلول.
	 * @return string سلول تمیز.
	 */
	private static function clean_cell( $inner ) {
		$cell_note_re = '/' . self::NOTE_BODY . '|لازم است|نیازمند|^کنترل|دادهٔ پایه|داده پایه|رابطهٔ داخلی|رابطه داخلی/u';
		list( $masked, $store ) = self::mask( $inner );
		$sups = self::sups( $masked );
		$body = trim( preg_replace( '/\s*\x02\d+\x03/', '', $masked ) );
		$parts = explode( '؛', $body );
		$visible_parts = array();
		foreach ( $parts as $p ) {
			$v = self::visible( $p );
			if ( $v !== '' ) {
				$visible_parts[] = $p;
			}
		}
		$kept = array();
		foreach ( $visible_parts as $p ) {
			$v = self::visible( $p );
			if ( ! preg_match( $cell_note_re, $v ) ) {
				$kept[] = $p;
			}
		}
		if ( count( $kept ) === count( $visible_parts ) ) {
			return $inner;
		}
		$texts = array();
		foreach ( $kept as $p ) {
			$texts[] = trim( $p );
		}
		$text = implode( '؛ ', $texts );
		return self::unmask( trim( $text . ( ( $text && $sups ) ? ' ' : '' ) . $sups ), $store );
	}

	/**
	 * پاک‌سازی جدول‌های «شاخص/مقدار/توضیح».
	 *
	 * @param string $html مقاله.
	 * @param array  $log  گزارش (با ارجاع).
	 * @return string مقالهٔ تمیز.
	 */
	private static function clean_tables( $html, &$log ) {
		return preg_replace_callback(
			'#<table\b[^>]*>.*?</table>#s',
			function ( $tm ) use ( &$log ) {
				$table = $tm[0];
				if ( strpos( $table, '<th' ) === false || ! preg_match( '/<th>\s*(?:شاخص|مقدار|توضیح)\s*<\/th>/', $table ) ) {
					return $table;
				}
				return preg_replace_callback(
					'#<tr\b[^>]*>(.*?)</tr>#s',
					function ( $rm ) use ( &$log ) {
						if ( ! preg_match_all( '#(<t[dh]\b[^>]*>)(.*?)(</t[dh]>)#s', $rm[1], $cm, PREG_SET_ORDER ) ) {
							return $rm[0];
						}
						$cells = array();
						foreach ( $cm as $c ) {
							$cells[] = array( $c[1], $c[2], $c[3] );
						}
						if ( ! $cells || strpos( $cells[0][0], '<th' ) === 0 ) {
							return $rm[0];
						}
						$new_cells = array();
						foreach ( $cells as $k => $c ) {
							$new_cells[] = array( $c[0], ( $k > 0 ) ? self::clean_cell( $c[1] ) : $c[1], $c[2] );
						}
						$first_plain = self::norm( self::plain( $cells[0][1] ) );
						$generic     = (bool) preg_match( '/^\s*نام\x{200c}های تاریخی/u', $first_plain )
							|| strpos( $first_plain, 'نام های تاریخی' ) === 0;
						list( $last_masked ) = self::mask( $cells[ count( $cells ) - 1 ][1] );
						list( $last_new_masked ) = self::mask( $new_cells[ count( $new_cells ) - 1 ][1] );
						$last_emptied = count( $cells ) > 2 && self::visible( $last_masked ) !== '' && self::visible( $last_new_masked ) === '';
						if ( $generic && $last_emptied ) {
							$log[] = 'table row removed (placeholder): ' . $first_plain;
							return '';
						}
						list( $val_masked ) = self::mask( $cells[1][1] );
						list( $val_new_masked ) = self::mask( $new_cells[1][1] );
						if ( count( $new_cells ) > 1 && self::visible( $val_new_masked ) === '' && self::visible( $val_masked ) !== '' ) {
							$log[] = 'table row removed (value was a placeholder): ' . $first_plain;
							return '';
						}
						$row = $rm[0];
						foreach ( $cells as $k => $c ) {
							if ( $c[1] !== $new_cells[ $k ][1] ) {
								$log[] = 'table cell: ' . mb_substr( self::norm( self::plain( $c[1] ) ), 0, 80 ) . ' -> ' . ( mb_substr( self::norm( self::plain( $new_cells[ $k ][1] ) ), 0, 60 ) ?: '(source only)' );
								$old_c = $c[0] . $c[1] . $c[2];
								$new_c = $c[0] . $new_cells[ $k ][1] . $c[2];
								$at    = strpos( $row, $old_c );
								if ( false !== $at ) {
									$row = substr( $row, 0, $at ) . $new_c . substr( $row, $at + strlen( $old_c ) );
								}
							}
						}
						return $row;
					},
					$table
				);
			},
			$html
		);
	}

	/**
	 * پاک‌سازی کامل یک مقاله — پورت `content_fix.clean()`.
	 *
	 * @param string $html مقاله.
	 * @return array{0:string,1:array<int,string>} مقالهٔ تمیز و گزارش.
	 */
	public static function clean( $html ) {
		$log          = array();
		$empty_before = self::empty_headings( $html );
		$html         = self::clean_tables( $html, $log );
		$seen_sent    = array();
		$seen_clause  = array();
		$li_texts     = array();
		$shorten      = array();

		if ( ! preg_match_all(
			'/(?<pre>(?:<!-- wp:(?:paragraph|list-item)[^>]*-->)?\s*)'
			. '<(?<tag>p|li)(?<attrs>\b[^>]*)>(?<inner>.*?)<\/\k<tag>>'
			. '(?<post>\s*(?:<!-- \/wp:(?:paragraph|list-item) -->)?)/s',
			$html,
			$bm,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		) ) {
			$bm = array();
		}

		// pass 0: مورد فهرستی تکراری در فهرست بعدی ⇒ مورد خلاصه کوتاه می‌شود.
		foreach ( $bm as $i => $m ) {
			if ( $m['tag'][0] !== 'li' ) {
				continue;
			}
			$key = self::norm( self::plain( $m['inner'][0] ) );
			if ( isset( $li_texts[ $key ] ) && mb_strpos( $key, '؛' ) !== false ) {
				$shorten[ $li_texts[ $key ] ] = true;
			} elseif ( ! isset( $li_texts[ $key ] ) ) {
				$li_texts[ $key ] = $i;
			}
		}

		$out = array();
		$pos = 0;
		foreach ( $bm as $i => $m ) {
			$out[] = substr( $html, $pos, $m[0][1] - $pos );
			$pos   = $m[0][1] + strlen( $m[0][0] );
			$tag   = $m['tag'][0];
			$inner = $m['inner'][0];
			list( $masked, $store ) = self::mask( $inner );

			if ( 'li' === $tag ) {
				$vis = self::visible( $masked );
				if ( self::is_note( $vis ) ) {
					$log[] = 'note item: ' . mb_substr( $vis, 0, 140 );
					continue;
				}
				$vis_r = rtrim( $vis, ' .' );
				if ( isset( $seen_sent[ $vis_r . '.' ] ) || isset( $seen_sent[ $vis ] ) || isset( $seen_clause[ $vis_r ] ) ) {
					$log[] = 'list item repeats an earlier sentence: ' . mb_substr( $vis, 0, 120 );
					continue;
				}
				if ( isset( $shorten[ $i ] ) && preg_match( '/^\s*(\x00\d+\x01[^\x00]*\x00\d+\x01)/', $masked, $sm ) ) {
					$new   = $sm[1] . self::sups( $masked );
					$log[] = 'summary item shortened (full text kept below): ' . mb_substr( $vis, 0, 100 );
					$out[] = $m['pre'][0] . '<li' . $m['attrs'][0] . '>' . self::unmask( $new, $store ) . '</li>' . $m['post'][0];
					continue;
				}
				foreach ( preg_split( '/(?<=[.!?؟])\s+/u', $vis ) as $s ) {
					if ( count( preg_split( '/\s+/u', trim( $s ) ) ) >= 6 ) {
						$seen_sent[ $s ] = true;
						foreach ( explode( '؛', $s ) as $c ) {
							$seen_clause[ rtrim( trim( $c ), '.' ) ] = true;
						}
					}
				}
				$out[] = $m[0][0];
				continue;
			}

			// پاراگراف: سطح جمله/بند.
			$kept    = array();
			$changed = false;
			foreach ( self::sentences( $masked ) as $s ) {
				$s2 = self::strip_note_clauses( $s, $log );
				if ( $s2 !== $s ) {
					$changed = true;
				}
				if ( '' === $s2 ) {
					if ( $kept && self::sups( $s ) ) {
						$kept[ count( $kept ) - 1 ] = rtrim( $kept[ count( $kept ) - 1 ] ) . self::sups( $s );
					}
					continue;
				}
				$key = self::visible( $s2 );
				if ( count( preg_split( '/\s+/u', trim( $key ) ) ) >= 6 && isset( $seen_sent[ $key ] ) ) {
					$log[]   = 'duplicate sentence: ' . mb_substr( $key, 0, 140 );
					$changed = true;
					if ( $kept && self::sups( $s2 ) ) {
						$kept[ count( $kept ) - 1 ] = rtrim( $kept[ count( $kept ) - 1 ] ) . self::sups( $s2 );
					}
					continue;
				}
				if ( count( preg_split( '/\s+/u', trim( $key ) ) ) >= 6 ) {
					$seen_sent[ $key ] = true;
					foreach ( explode( '؛', $key ) as $c ) {
						if ( count( preg_split( '/\s+/u', trim( $c ) ) ) >= 5 ) {
							$seen_clause[ rtrim( trim( $c ), '.' ) ] = true;
						}
					}
				}
				$kept[] = trim( $s2 );
			}
			if ( ! $changed ) {
				$out[] = $m[0][0];
				continue;
			}
			$new_inner = trim( implode( ' ', $kept ) );
			if ( trim( self::visible( $new_inner ), ' .،:' ) === '' ) {
				$log[] = 'empty paragraph removed';
				continue;
			}
			$out[] = $m['pre'][0] . '<p' . $m['attrs'][0] . '>' . self::unmask( $new_inner, $store ) . '</p>' . $m['post'][0];
		}
		$out[] = substr( $html, $pos );
		$html2 = implode( '', $out );

		// فهرست‌های خالی.
		$html2 = preg_replace( '/(?:<!-- wp:list[^>]*-->\s*)?<(ul|ol)\b[^>]*>\s*<\/\1>(?:\s*<!-- \/wp:list -->)?/s', '', $html2 );

		// تیترهای بدون محتوا (تیتر پایانی دست‌نخورده).
		while ( true ) {
			preg_match_all( '/(?:<!-- wp:heading[^>]*-->\s*)?<h([2-6])\b[^>]*>(.*?)<\/h\1>(?:\s*<!-- \/wp:heading -->)?/s', $html2, $hm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
			$removed = false;
			foreach ( $hm as $j => $h ) {
				$level = (int) $h[1][0];
				$end   = null;
				foreach ( array_slice( $hm, $j + 1 ) as $n ) {
					if ( (int) $n[1][0] <= $level ) {
						$end = $n[0][1];
						break;
					}
				}
				if ( null === $end ) {
					continue;
				}
				$seg = substr( $html2, $h[0][1] + strlen( $h[0][0] ), $end - ( $h[0][1] + strlen( $h[0][0] ) ) );
				if ( in_array( self::norm( self::plain( $h[2][0] ) ), $empty_before, true ) ) {
					continue;
				}
				$seg_no_comment = preg_replace( '/<!--.*?-->/s', '', $seg );
				if ( self::norm( self::plain( $seg_no_comment ) ) === '' ) {
					$log[]  = 'empty heading removed: ' . mb_substr( self::norm( self::plain( $h[2][0] ) ), 0, 60 );
					$html2  = substr( $html2, 0, $h[0][1] ) . substr( $html2, $h[0][1] + strlen( $h[0][0] ) );
					$removed = true;
					break;
				}
			}
			if ( ! $removed ) {
				break;
			}
		}

		// پاراگرافی که با «؛» مانده است.
		$html2 = preg_replace( '/؛\s*((?:<sup\b[^>]*>.*?<\/sup>)*)\s*<\/p>/s', '.\\1</p>', $html2 );
		$html2 = preg_replace( '/\n{3,}/', "\n\n", $html2 );
		return array( $html2, $log );
	}

	/**
	 * شمارش واژه‌ها (همان روش پایتون: متن ساده → هم‌سازی → جداکردن با فاصله).
	 *
	 * @param string $html مقاله.
	 * @return int شمار واژه‌ها.
	 */
	public static function word_count( $html ) {
		$t = self::norm( self::plain( $html ) );
		if ( '' === $t ) {
			return 0;
		}
		return count( preg_split( '/\s+/u', $t ) );
	}

	/**
	 * آیا خروجی از واژه‌های ممنوع پل ایجنت پاک است؟
	 *
	 * @param string $html مقاله.
	 * @return array<int,string> واژه‌های ممنوعِ یافت‌شده.
	 */
	public static function banned_hits( $html ) {
		$hits = array();
		foreach ( self::BANNED_OUTPUT as $w ) {
			if ( false !== mb_strpos( $html, $w ) ) {
				$hits[] = $w;
			}
		}
		return $hits;
	}

	/* ------------------------------------------------------------------ */
	/* لایهٔ اجرا: اسکن، اعمال، پشتیبان، بازگردانی                         */
	/* ------------------------------------------------------------------ */

	/**
	 * همهٔ نوشته‌های منتشرشدهٔ هدف.
	 *
	 * @return array<int,WP_Post> نوشته‌ها.
	 */
	private static function target_posts() {
		$q = new WP_Query(
			array(
				'post_type'              => self::TARGET_TYPES,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => true,
			)
		);
		return $q->posts;
	}

	/**
	 * اسکن/اعمال روی همهٔ صفحه‌ها.
	 *
	 * @param bool $dry_run فقط گزارش؟
	 * @return array گزارش اجرا.
	 */
	public static function run( $dry_run ) {
		$report = array(
			'run_id'    => gmdate( 'Ymd-His' ),
			'plugin'    => 'sa-editorial-fix ' . SA_EF_VERSION,
			'dry_run'   => (bool) $dry_run,
			'started'   => gmdate( 'c' ),
			'summary'   => array(
				'scanned'    => 0,
				'changed'    => 0,
				'no_change'  => 0,
				'skip_drop'  => 0,
				'skip_banned' => 0,
				'notes_found' => 0,
			),
			'rows'      => array(),
			'backup_id' => null,
		);

		$backup = array(
			'run_id'  => $report['run_id'],
			'created' => gmdate( 'c' ),
			'posts'   => array(),
		);

		foreach ( self::target_posts() as $post ) {
			$report['summary']['scanned']++;
			$before   = $post->post_content;
			$log      = array();
			list( $after, $log ) = self::clean( $before );

			$row = array(
				'id'           => $post->ID,
				'type'         => $post->post_type,
				'slug'         => $post->post_name,
				'title'        => get_the_title( $post ),
				'permalink'    => get_permalink( $post ),
				'notes'        => count( self::find_notes( self::plain( $before ) ) ),
				'words_before' => self::word_count( $before ),
				'words_after'  => self::word_count( $after ),
				'log'          => $log,
			);

			if ( $after === $before ) {
				$row['status'] = 'no-change';
				$report['summary']['no_change']++;
				$report['rows'][] = $row;
				continue;
			}

			$row['notes'] = count( self::find_notes( self::plain( $before ) ) );
			$report['summary']['notes_found'] += count( $log );

			$drop_pct = ( $row['words_before'] > 0 )
				? ( 100.0 * ( $row['words_before'] - $row['words_after'] ) / $row['words_before'] )
				: 0.0;
			$row['drop_pct'] = round( $drop_pct, 2 );

			$banned = self::banned_hits( $after );
			if ( $banned ) {
				$row['status'] = 'skip-banned';
				$row['banned'] = $banned;
				$report['summary']['skip_banned']++;
				$report['rows'][] = $row;
				continue;
			}
			if ( $drop_pct > SA_EF_MAX_DROP_PCT ) {
				$row['status'] = 'skip-rewrite'; // نیازمند بازنویسی کامل (replace_src) — طبق دستور مالک.
				$report['summary']['skip_drop']++;
				$report['rows'][] = $row;
				continue;
			}

			$row['status'] = ( $dry_run ) ? 'ready' : 'changed';
			$report['summary']['changed']++;

			if ( ! $dry_run ) {
				$backup['posts'][ $post->ID ] = array(
					'title'    => $row['title'],
					'content'  => $before,
					'hash'     => md5( $before ),
					'new_hash' => md5( $after ),
					'log'      => $log,
				);
				// پشتیبان پیش از نوشتن؛ اگر ذخیره نشود، نوشتن انجام نمی‌شود.
				if ( ! self::save_backup( $backup ) ) {
					$row['status'] = 'skip-backup-failed';
					$report['summary']['changed']--;
					$report['rows'][] = $row;
					continue;
				}
				$result = wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => wp_slash( $after ),
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					$row['status'] = 'error';
					$row['error']  = $result->get_error_message();
					$report['summary']['changed']--;
				}
			}
			$report['rows'][] = $row;
		}

		if ( ! $dry_run && $backup['posts'] ) {
			self::save_backup( $backup );
			$report['backup_id'] = $report['run_id'];
		}
		$report['finished'] = gmdate( 'c' );
		self::save_report( $report );
		if ( ! $dry_run && $report['summary']['changed'] > 0 ) {
			self::purge_cache();
		}
		return $report;
	}

	/**
	 * ذخیرهٔ پشتیبان (حداکثر SA_EF_KEEP_RUNS نسخه).
	 *
	 * @param array $backup دادهٔ پشتیبان.
	 * @return bool موفقیت.
	 */
	private static function save_backup( $backup ) {
		$ok = update_option( 'sa_ef_backup_' . $backup['run_id'], $backup, false );
		self::prune_runs( 'sa_ef_backup_' );
		return $ok || get_option( 'sa_ef_backup_' . $backup['run_id'] ) !== false;
	}

	/**
	 * ذخیرهٔ گزارش.
	 *
	 * @param array $report گزارش.
	 * @return void
	 */
	private static function save_report( $report ) {
		update_option( 'sa_ef_report_' . $report['run_id'], $report, false );
		update_option( 'sa_ef_report_last', $report['run_id'], false );
		self::prune_runs( 'sa_ef_report_' );
	}

	/**
	 * نگه‌داری فقط چند نسخهٔ آخر.
	 *
	 * @param string $prefix پیشوند گزینه‌ها.
	 * @return void
	 */
	private static function prune_runs( $prefix ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name DESC",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
		foreach ( array_slice( $ids, SA_EF_KEEP_RUNS ) as $name ) {
			delete_option( $name );
		}
	}

	/**
	 * بازگردانی یک اجرا (فقط نوشته‌هایی که پس از اعمال دست نخورده‌اند).
	 *
	 * @param string $run_id شناسهٔ اجرا.
	 * @return array گزارش بازگردانی.
	 */
	public static function rollback( $run_id ) {
		$backup = get_option( 'sa_ef_backup_' . $run_id );
		$out    = array(
			'run_id'  => $run_id,
			'created' => gmdate( 'c' ),
			'rows'    => array(),
		);
		if ( ! is_array( $backup ) || empty( $backup['posts'] ) ) {
			$out['error'] = 'backup-not-found';
			return $out;
		}
		foreach ( $backup['posts'] as $pid => $b ) {
			$post = get_post( (int) $pid );
			if ( ! $post ) {
				$out['rows'][] = array( 'id' => $pid, 'status' => 'missing' );
				continue;
			}
			if ( md5( $post->post_content ) !== $b['new_hash'] ) {
				$out['rows'][] = array( 'id' => $pid, 'title' => $b['title'], 'status' => 'skipped-changed' );
				continue;
			}
			wp_update_post(
				array(
					'ID'           => (int) $pid,
					'post_content' => wp_slash( $b['content'] ),
				),
				true
			);
			$out['rows'][] = array( 'id' => $pid, 'title' => $b['title'], 'status' => 'restored' );
		}
		update_option( 'sa_ef_rollback_last', $out, false );
		self::purge_cache();
		return $out;
	}

	/**
	 * پاک‌سازی کش پس از تغییر.
	 *
	 * @return void
	 */
	private static function purge_cache() {
		wp_cache_flush();
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* رابط مدیریت                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * ثبت منوی ابزارها.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_management_page(
			'ترمیم یک‌بارهٔ یادداشت‌ها',
			'ترمیم یک‌بارهٔ یادداشت‌ها',
			'manage_options',
			'sa-editorial-fix',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * پردازش فرمان‌های POST.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( empty( $_POST['sa_ef_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'دسترسی غیرمجاز.' );
		}
		check_admin_referer( 'sa_ef_run' );
		$action = sanitize_key( wp_unslash( $_POST['sa_ef_action'] ) );

		if ( 'apply' === $action ) {
			self::run( false );
		} elseif ( 'preview' === $action ) {
			self::run( true );
		} elseif ( 'rollback' === $action && ! empty( $_POST['sa_ef_run_id'] ) ) {
			self::rollback( sanitize_key( wp_unslash( $_POST['sa_ef_run_id'] ) ) );
		} elseif ( 'self-deactivate' === $action ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=sa-editorial-fix' ) );
		exit;
	}

	/**
	 * صفحهٔ گزارش/فرمان‌ها.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$last_id = get_option( 'sa_ef_report_last' );
		$report  = $last_id ? get_option( 'sa_ef_report_' . $last_id ) : null;
		$rollback = get_option( 'sa_ef_rollback_last' );
		?>
		<div class="wrap" dir="rtl">
			<h1>ترمیم یک‌بارهٔ یادداشت‌های ویرایشی — نسخهٔ <?php echo esc_html( SA_EF_VERSION ); ?></h1>
			<p>این افزونه یک‌بارمصرف است: یادداشت‌های تولیدی/فرایندی را از متن منتشرشده حذف می‌کند،
				پیش از هر تغییر پشتیبان می‌گیرد و هر صفحه‌ای که افت حجمش بیش از
				<?php echo esc_html( SA_EF_MAX_DROP_PCT ); ?>٪ باشد را دست نمی‌زند (به صف بازنویسی می‌رود).
				هیچ متن تازه‌ای افزوده نمی‌شود. پس از پایان و راستی‌آزمایی می‌توانید افزونه را غیرفعال و حذف کنید.</p>

			<form method="post" style="display:inline-block;margin-left:8px">
				<?php wp_nonce_field( 'sa_ef_run' ); ?>
				<input type="hidden" name="sa_ef_action" value="preview" />
				<button class="button">پیش‌نمایش (بدون تغییر)</button>
			</form>
			<form method="post" style="display:inline-block;margin-left:8px" onsubmit="return confirm('همهٔ اصلاحات ایمن اعمال و از هر صفحه پشتیبان گرفته می‌شود. ادامه می‌دهید؟');">
				<?php wp_nonce_field( 'sa_ef_run' ); ?>
				<input type="hidden" name="sa_ef_action" value="apply" />
				<button class="button button-primary">اعمال اصلاح</button>
			</form>
			<?php if ( $report && ! empty( $report['backup_id'] ) ) : ?>
				<form method="post" style="display:inline-block;margin-left:8px" onsubmit="return confirm('همهٔ تغییرات این اجرا بازگردانی شود؟');">
					<?php wp_nonce_field( 'sa_ef_run' ); ?>
					<input type="hidden" name="sa_ef_action" value="rollback" />
					<input type="hidden" name="sa_ef_run_id" value="<?php echo esc_attr( $report['backup_id'] ); ?>" />
					<button class="button">بازگردانی آخرین اجرا</button>
				</form>
			<?php endif; ?>
			<form method="post" style="display:inline-block" onsubmit="return confirm('افزونه غیرفعال شود؟');">
				<?php wp_nonce_field( 'sa_ef_run' ); ?>
				<input type="hidden" name="sa_ef_action" value="self-deactivate" />
				<button class="button">پایان و غیرفعال‌سازی</button>
			</form>

			<?php if ( $report ) : ?>
				<h2>گزارش اجرای <?php echo esc_html( $report['run_id'] ); ?>
					<?php echo $report['dry_run'] ? '(پیش‌نمایش)' : '(اعمال‌شده)'; ?></h2>
				<p>
					اسکن: <?php echo (int) $report['summary']['scanned']; ?> ·
					تغییر: <?php echo (int) $report['summary']['changed']; ?> ·
					بدون تغییر: <?php echo (int) $report['summary']['no_change']; ?> ·
					ردشده (افت > <?php echo esc_html( SA_EF_MAX_DROP_PCT ); ?>٪): <?php echo (int) $report['summary']['skip_drop']; ?> ·
					ردشده (واژهٔ ممنوع): <?php echo (int) $report['summary']['skip_banned']; ?>
				</p>
				<table class="widefat striped">
					<thead><tr><th>شناسه</th><th>نوع</th><th>عنوان</th><th>یادداشت</th><th>واژه (قبل←بعد)</th><th>افت</th><th>وضعیت</th></tr></thead>
					<tbody>
					<?php foreach ( $report['rows'] as $row ) : ?>
						<?php if ( 'no-change' === $row['status'] ) { continue; } ?>
						<tr>
							<td><?php echo (int) $row['id']; ?></td>
							<td><?php echo esc_html( $row['type'] ); ?></td>
							<td><a href="<?php echo esc_url( $row['permalink'] ); ?>" target="_blank"><?php echo esc_html( $row['title'] ); ?></a></td>
							<td><?php echo (int) $row['notes']; ?></td>
							<td><?php echo (int) $row['words_before']; ?> ← <?php echo (int) $row['words_after']; ?></td>
							<td><?php echo esc_html( isset( $row['drop_pct'] ) ? $row['drop_pct'] . '٪' : '—' ); ?></td>
							<td><?php echo esc_html( $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<details><summary>گزارش کامل JSON</summary>
					<textarea rows="12" style="width:100%" readonly dir="ltr"><?php echo esc_textarea( wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></textarea>
				</details>
			<?php endif; ?>

			<?php if ( $rollback ) : ?>
				<h2>آخرین بازگردانی (<?php echo esc_html( $rollback['created'] ); ?>)</h2>
				<p><?php echo (int) count( $rollback['rows'] ); ?> مورد بررسی شد.</p>
			<?php endif; ?>
		</div>
		<?php
	}
}

add_action( 'admin_menu', array( 'SA_Editorial_Fix', 'admin_menu' ) );
add_action( 'admin_init', array( 'SA_Editorial_Fix', 'handle_actions' ) );

/**
 * هنگام فعال‌سازی: فقط پیش‌نمایش (بدون تغییر) تا مالک با یک کلیک «اعمال اصلاح» را تأیید کند.
 *
 * @return void
 */
function sa_ef_activate() {
	if ( ! get_option( 'sa_ef_report_last' ) ) {
		SA_Editorial_Fix::run( true );
	}
}
register_activation_hook( __FILE__, 'sa_ef_activate' );
