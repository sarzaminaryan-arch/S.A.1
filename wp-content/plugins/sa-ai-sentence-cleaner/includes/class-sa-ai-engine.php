<?php
/**
 * موتور پاک‌سازی جمله‌های هوش مصنوعی — خالص و بدون وابستگی به وردپرس
 * تا در تست مستقل هم اجرا شود.
 *
 * قانون‌ها:
 * - واحد حذف «کل جمله» است؛ متن تازه‌ای ساخته نمی‌شود.
 * - تیترها (<h1> تا <h6>)، جدول‌ها، کدها، اسکریپت/استایل، فیگورها، کامنت‌های
 *   HTML و بلوک‌های گوتنبرگ، شورت‌کدها و نشانی لینک‌ها هرگز دست نمی‌خورند.
 * - جملهٔ داخل متن لینک (<a>…</a>) خودکار حذف نمی‌شود؛ فقط گزارش می‌شود.
 * - اگر بیش از آستانه (پیش‌فرض ۲۰٪) متن یک نوشته حذف شود، خروجی دست‌نخورده
 *   برمی‌گردد و manual_review=true می‌شود.
 * - اگر هیچ حذفی نباشد، محتوا بایت‌به‌بایت همان ورودی است.
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_AI_Engine {

	private static $compiled = array();

	/* ---------------- نرمال‌سازی (فقط برای پیدا کردن) ---------------- */

	public static function normalize( $text ) {
		$text = (string) $text;
		$map  = array(
			'ي' => 'ی',
			'ك' => 'ک',
			'ى' => 'ی',
			'ة' => 'ه',
			'ؤ' => 'و',
			'إ' => 'ا',
			'أ' => 'ا',
			'آ' => 'ا',
			'ٱ' => 'ا',
			"\u{0640}" => '',
			"\u{200C}" => '',
			"\u{200D}" => '',
			"\u{00A0}" => ' ',
		);
		$text = strtr( $text, $map );
		$text = preg_replace( '/[\x{064B}-\x{0652}\x{0670}]/u', '', $text );
		$text = self::digits_to_en( (string) $text );
		return preg_replace( '/[ \t]+/u', ' ', (string) $text );
	}

	public static function digits_to_en( $text ) {
		$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		return str_replace( $ar, $en, str_replace( $fa, $en, (string) $text ) );
	}

	public static function en_digits_to_sample( $number, $sample ) {
		$s      = (string) (int) $number;
		$first  = function_exists( 'mb_substr' ) ? mb_substr( (string) $sample, 0, 1, 'UTF-8' ) : substr( (string) $sample, 0, 3 );
		$fa     = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
		$ar     = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
		$en     = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		if ( in_array( $first, $fa, true ) ) {
			return str_replace( $en, $fa, $s );
		}
		if ( in_array( $first, $ar, true ) ) {
			return str_replace( $en, $ar, $s );
		}
		return $s;
	}

	public static function sentence_key( $sentence ) {
		$key = self::normalize( (string) $sentence );
		// trim() با ماسک فارسی بایت‌های UTF-8 را خراب می‌کند؛ با regex یونیکد برش می‌زنیم.
		$key = preg_replace( '/^[\s.!?؟؛،:]+|[\s.!?؟؛،:]+$/u', '', (string) $key );
		$key = preg_replace( '/\s+/u', ' ', (string) $key );
		return (string) $key;
	}

	public static function key_hash( $sentence_or_key ) {
		$key = self::sentence_key( $sentence_or_key );
		return hash( 'sha256', $key );
	}

	/* ---------------- الگوها ---------------- */

	public static function compile_pattern( $regex ) {
		$regex = trim( (string) $regex );
		if ( '' === $regex ) {
			return null;
		}
		$cache_key = md5( $regex );
		if ( isset( self::$compiled[ $cache_key ] ) ) {
			return self::$compiled[ $cache_key ];
		}
		$normalized = self::normalize( $regex );
		$flexible   = str_replace( ' ', '(?:[\s\x{200C}]*)', $normalized );
		$compiled   = '#(?:' . $flexible . ')#iu';
		if ( false === @preg_match( $compiled, '' ) ) {
			self::$compiled[ $cache_key ] = null;
			return null;
		}
		self::$compiled[ $cache_key ] = $compiled;
		return $compiled;
	}

	/**
	 * @return array|null الگوی هم‌سطحی که خورد، یا null
	 */
	public static function match_pattern( $normalized_sentence, $patterns, $level ) {
		foreach ( (array) $patterns as $pattern ) {
			if ( ! isset( $pattern['level'] ) || $level !== $pattern['level'] || empty( $pattern['regex'] ) ) {
				continue;
			}
			$compiled = self::compile_pattern( $pattern['regex'] );
			if ( $compiled && preg_match( $compiled, (string) $normalized_sentence ) ) {
				return $pattern;
			}
		}
		return null;
	}

	/* ---------------- برش محافظت‌شده ---------------- */

	private static function block_pattern() {
		return '/(<!--\s*\/?wp:[^>]*-->|<!--.*?-->|<pre\b[^>]*>.*?<\/pre\s*>|<code\b[^>]*>.*?<\/code\s*>|<script\b[^>]*>.*?<\/script\s*>|<style\b[^>]*>.*?<\/style\s*>|<table\b[^>]*>.*?<\/table\s*>|<h[1-6]\b[^>]*>.*?<\/h[1-6]\s*>|<figure\b[^>]*>.*?<\/figure\s*>)/isu';
	}

	private static function is_protected_block( $chunk ) {
		return (bool) preg_match( '/^(?:<!--|<pre\b|<code\b|<script\b|<style\b|<table\b|<h[1-6]\b|<figure\b)/i', (string) $chunk );
	}

	private static function split_blocks( $content ) {
		$parts = preg_split( self::block_pattern(), (string) $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		return is_array( $parts ) ? $parts : array( (string) $content );
	}

	private static function split_inline( $segment ) {
		// فقط شورت‌کد واقعی (نام لاتین) محافظت می‌شود؛ [نیاز به منبع] و ارجاع [۱] متن‌اند و باید دیده شوند.
		$parts = preg_split( '/(\[[a-zA-Z\/][^\]\n]{0,300}\]|<[^>\n]+>)/u', (string) $segment, -1, PREG_SPLIT_DELIM_CAPTURE );
		return is_array( $parts ) ? $parts : array( (string) $segment );
	}

	private static function is_inline_token( $chunk ) {
		return '' !== $chunk && ( '<' === $chunk[0] || ( '[' === $chunk[0] && isset( $chunk[1] ) && preg_match( '/[a-zA-Z\/]/', $chunk[1] ) ) );
	}

	public static function visible_text( $content ) {
		$text = '';
		foreach ( self::split_blocks( $content ) as $block ) {
			if ( self::is_protected_block( $block ) ) {
				continue;
			}
			foreach ( self::split_inline( $block ) as $chunk ) {
				if ( ! self::is_inline_token( $chunk ) ) {
					$text .= $chunk;
				}
			}
		}
		return $text;
	}

	/**
	 * همهٔ جمله‌های متنِ قابل مشاهده (برای ساخت نقشهٔ جمله‌های تکراری).
	 *
	 * @return string[]
	 */
	public static function extract_sentences( $content ) {
		$sentences = array();
		$in_anchor = false;
		foreach ( self::split_blocks( $content ) as $block ) {
			if ( self::is_protected_block( $block ) ) {
				continue;
			}
			foreach ( self::split_inline( $block ) as $chunk ) {
				if ( self::is_inline_token( $chunk ) ) {
					if ( preg_match( '/^<a\b/i', $chunk ) ) {
						$in_anchor = true;
					} elseif ( preg_match( '/^<\/a\s*>/i', $chunk ) ) {
						$in_anchor = false;
					}
					continue;
				}
				if ( ! preg_match_all( '/[^.!?؟؛\n]+(?:[.!?؟؛]+)?/u', $chunk, $m ) ) {
					continue;
				}
				foreach ( $m[0] as $sentence ) {
					if ( '' !== trim( $sentence ) ) {
						$sentences[] = $sentence;
					}
				}
			}
		}
		return $sentences;
	}

	/**
	 * نقشهٔ جمله‌های تکراری بین مقاله‌ها.
	 *
	 * @param array $contents_by_id شناسهٔ نوشته => محتوا
	 * @return array hash => array{sentence:string,key:string,count:int,post_ids:int[],pattern_id:string}
	 */
	public static function collect_duplicates( $contents_by_id, $patterns, $min_posts = 4, $min_length = 20 ) {
		$seen = array();
		foreach ( (array) $contents_by_id as $post_id => $content ) {
			$per_post = array();
			foreach ( self::extract_sentences( $content ) as $sentence ) {
				$key = self::sentence_key( $sentence );
				if ( mb_strlen( $key, 'UTF-8' ) < (int) $min_length ) {
					continue;
				}
				$hash = hash( 'sha256', $key );
				if ( ! isset( $seen[ $hash ] ) ) {
					$seen[ $hash ] = array(
						'sentence'   => trim( $sentence ),
						'key'        => $key,
						'posts'      => array(),
						'pattern_id' => '',
					);
					$hit           = self::match_pattern( self::normalize( $sentence ), $patterns, 'DELETE' );
					if ( $hit ) {
						$seen[ $hash ]['pattern_id'] = $hit['id'];
					}
				}
				$per_post[ $hash ] = true;
			}
			foreach ( array_keys( $per_post ) as $hash ) {
				$seen[ $hash ]['posts'][ (int) $post_id ] = true;
			}
		}
		$out = array();
		foreach ( $seen as $hash => $row ) {
			$post_ids = array_keys( $row['posts'] );
			if ( count( $post_ids ) >= (int) $min_posts ) {
				$out[ $hash ] = array(
					'sentence'   => $row['sentence'],
					'key'        => $row['key'],
					'count'      => count( $post_ids ),
					'post_ids'   => $post_ids,
					'pattern_id' => $row['pattern_id'],
				);
			}
		}
		return $out;
	}

	/* ---------------- پردازش اصلی ---------------- */

	private static function add_finding( &$map, $pattern_id, $level, $sentence, $action, $is_duplicate ) {
		$hash = self::key_hash( $sentence );
		$key  = $pattern_id . '|' . $hash;
		if ( isset( $map[ $key ] ) ) {
			return;
		}
		$map[ $key ] = array(
			'pattern_id'   => $pattern_id,
			'level'        => $level,
			'sentence'     => trim( (string) $sentence ),
			'sentence_key' => self::sentence_key( $sentence ),
			'key_hash'     => $hash,
			'action'       => $action,
			'is_duplicate' => $is_duplicate ? 1 : 0,
		);
	}

	/**
	 * تحلیل بدون تغییر (برای اسکن).
	 */
	public static function analyze_content( $content, $patterns, $duplicate_map = array(), $options = array() ) {
		$options['apply'] = false;
		return self::process_content( $content, $patterns, $duplicate_map, $options );
	}

	/**
	 * پاک‌سازی واقعی. گزینه‌ها:
	 * delete_by_pattern bool، duplicate_hashes string[] (تأیید مالک برای حذف همه‌جا)،
	 * apply_sources bool، manual_threshold float (پیش‌فرض 0.20)
	 */
	public static function clean_content( $content, $patterns, $duplicate_map = array(), $options = array() ) {
		$options['apply'] = true;
		return self::process_content( $content, $patterns, $duplicate_map, $options );
	}

	private static function process_content( $content, $patterns, $duplicate_map, $options ) {
		$content         = (string) $content;
		$apply           = ! empty( $options['apply'] );
		$delete_patterns = $apply && ! empty( $options['delete_by_pattern'] );
		$apply_sources   = ! isset( $options['apply_sources'] ) || ! empty( $options['apply_sources'] );
		$threshold       = isset( $options['manual_threshold'] ) ? (float) $options['manual_threshold'] : 0.20;
		$approved        = array();
		foreach ( (array) ( isset( $options['duplicate_hashes'] ) ? $options['duplicate_hashes'] : array() ) as $hash ) {
			$approved[ (string) $hash ] = true;
		}

		$findings_map   = array();
		$work           = $content;
		$source_removed = 0;
		if ( $apply_sources ) {
			$src = self::clean_sources( $work );
			foreach ( $src['findings'] as $f ) {
				self::add_finding( $findings_map, $f['pattern_id'], $f['level'], $f['sentence'], $apply ? $f['action'] : 'would-' . $f['action'], false );
			}
			$source_removed = (int) $src['removed_chars'];
			if ( $apply ) {
				$work = $src['content'];
			}
		}

		$total_chars   = mb_strlen( self::visible_text( $content ), 'UTF-8' );
		$deleted_chars = 0;
		$deleted_count = 0;
		$in_anchor     = false;
		$out_blocks    = array();

		foreach ( self::split_blocks( $work ) as $block ) {
			if ( self::is_protected_block( $block ) ) {
				$out_blocks[] = $block;
				continue;
			}
			$out_chunks = array();
			foreach ( self::split_inline( $block ) as $chunk ) {
				if ( self::is_inline_token( $chunk ) ) {
					if ( preg_match( '/^<a\b/i', $chunk ) ) {
						$in_anchor = true;
					} elseif ( preg_match( '/^<\/a\s*>/i', $chunk ) ) {
						$in_anchor = false;
					}
					$out_chunks[] = $chunk;
					continue;
				}
				if ( '' === $chunk || ! preg_match_all( '/[^.!?؟؛\n]+(?:[.!?؟؛]+)?/u', $chunk, $matches, PREG_OFFSET_CAPTURE ) ) {
					$out_chunks[] = $chunk;
					continue;
				}
				$deletions = array();
				foreach ( $matches[0] as $match ) {
					$raw      = $match[0];
					$offset   = (int) $match[1];
					$sentence = trim( $raw );
					if ( '' === $sentence ) {
						continue;
					}
					$normalized = self::normalize( $sentence );
					$hash       = self::key_hash( $sentence );
					$is_dup     = isset( $duplicate_map[ $hash ] );
					$del_pat    = self::match_pattern( $normalized, $patterns, 'DELETE' );

					if ( $in_anchor ) {
						if ( $del_pat ) {
							self::add_finding( $findings_map, $del_pat['id'], 'FLAG', $sentence, 'flagged-protected-link', $is_dup );
						} elseif ( $is_dup ) {
							self::add_finding( $findings_map, 'duplicate-sentence', 'FLAG', $sentence, 'flagged', true );
						}
						continue;
					}

					$delete_reason = null;
					if ( $apply && isset( $approved[ $hash ] ) ) {
						$delete_reason = $is_dup && ! empty( $duplicate_map[ $hash ]['pattern_id'] ) ? 'duplicate-ai-sentence' : 'duplicate-sentence';
					} elseif ( $delete_patterns && $del_pat ) {
						$delete_reason = $del_pat['id'];
					}

					if ( null !== $delete_reason ) {
						self::add_finding( $findings_map, $delete_reason, 'DELETE', $sentence, $apply ? 'deleted' : 'would-delete', $is_dup );
						if ( $apply ) {
							$deletions[]    = array( $offset, strlen( $raw ) );
							$deleted_chars += mb_strlen( $sentence, 'UTF-8' );
							++$deleted_count;
						}
						continue;
					}

					if ( $del_pat ) {
						self::add_finding( $findings_map, $del_pat['id'], 'DELETE', $sentence, 'would-delete', $is_dup );
						continue;
					}
					$flag_pat = self::match_pattern( $normalized, $patterns, 'FLAG' );
					if ( $flag_pat ) {
						self::add_finding( $findings_map, $flag_pat['id'], 'FLAG', $sentence, 'flagged', $is_dup );
					} elseif ( $is_dup ) {
						self::add_finding( $findings_map, 'duplicate-sentence', 'FLAG', $sentence, 'flagged', true );
					}
				}

				if ( $apply && $deletions ) {
					$rebuilt = '';
					$pos     = 0;
					foreach ( $deletions as $del ) {
						$rebuilt .= substr( $chunk, $pos, $del[0] - $pos );
						$pos      = $del[0] + $del[1];
					}
					$rebuilt     .= substr( $chunk, $pos );
					$rebuilt      = preg_replace( '/[ \t]{2,}/u', ' ', (string) $rebuilt );
					$out_chunks[] = $rebuilt;
				} else {
					$out_chunks[] = $chunk;
				}
			}
			$out_blocks[] = implode( '', $out_chunks );
		}

		$findings = array_values( $findings_map );
		$result   = array(
			'content'           => $content,
			'findings'          => $findings,
			'deleted_sentences' => 0,
			'deleted_chars'     => $deleted_chars + $source_removed,
			'total_chars'       => $total_chars,
			'manual_review'     => false,
			'changed'           => false,
		);
		if ( ! $apply ) {
			return $result;
		}

		$new_content = implode( '', $out_blocks );
		if ( $apply_sources && isset( $src ) && $src['content'] !== $content && 0 === $deleted_count ) {
			// فقط تغییر منبع/لینک بوده؛ جمله‌ای حذف نشده ولی محتوا عوض شده است.
			$new_content = $new_content;
		}
		if ( $new_content === $content ) {
			return $result;
		}

		$ratio = $total_chars > 0 ? ( $deleted_chars + $source_removed ) / $total_chars : 0;
		if ( $ratio > $threshold || '' === trim( self::visible_text( $new_content ) ) ) {
			foreach ( $result['findings'] as $i => $f ) {
				if ( 'deleted' === $f['action'] || 'source-deleted' === $f['action'] ) {
					$result['findings'][ $i ]['action'] = 'manual-review';
					$result['findings'][ $i ]['level']  = 'FLAG';
				}
			}
			$result['manual_review'] = true;
			return $result;
		}

		$new_content = self::tidy_empty_elements( $new_content );
		$result['content']           = $new_content;
		$result['deleted_sentences'] = $deleted_count;
		$result['changed']           = $new_content !== $content;
		return $result;
	}

	private static function tidy_empty_elements( $content ) {
		$prev = null;
		$out  = (string) $content;
		$guard = 0;
		while ( $prev !== $out && $guard < 5 ) {
			++$guard;
			$prev = $out;
			$out  = preg_replace( '#<(strong|b|em|i|span|mark|u|s)(?:\s[^>]*)?>\s*</\1>#iu', '', $out );
			$out  = preg_replace( '#<(p|li|blockquote)\b(?:\s[^>]*)?>(?:\s|&nbsp;|<br\s*/?>)*</\1>#iu', '', (string) $out );
			$out  = preg_replace( '#<!--\s*wp:(paragraph|list-item|heading)[^>]*-->\s*<!--\s*/wp:\1\s*-->#u', '', (string) $out );
		}
		$out = preg_replace( "/\n{3,}/u", "\n\n", (string) $out );
		return (string) $out;
	}

	/* ---------------- منابع ---------------- */

	private static function ends_with( $haystack, $needle ) {
		return '' === $needle || substr( $haystack, -strlen( $needle ) ) === $needle;
	}

	private static function hosts_in( $text ) {
		$hosts = array();
		if ( preg_match_all( '/https?:\/\/([^\s\/)\]"\'<>]+)/i', (string) $text, $m ) ) {
			$hosts = array_map( 'strtolower', $m[1] );
		}
		if ( ! $hosts && preg_match_all( '/(?<![\w.-])((?:www\.)?[a-z0-9-]+\.(?:com|org|net|ir|io|info|gov|edu)(?:\.[a-z]{2})?)/i', (string) $text, $m ) ) {
			$hosts = array_map( 'strtolower', $m[1] );
		}
		return $hosts;
	}

	private static function is_trusted_host( $host ) {
		return self::ends_with( $host, '.ir' ) || 'fa.wikipedia.org' === $host || self::ends_with( $host, '.wikipedia.org' ) || self::ends_with( $host, '.gov' );
	}

	private static function is_bad_only( $hosts ) {
		if ( ! $hosts ) {
			return false;
		}
		$bad = array( 'google.com', 'www.google.com', 'chatgpt.com', 'www.chatgpt.com', 'chat.openai.com', 'openai.com' );
		foreach ( $hosts as $host ) {
			if ( self::is_trusted_host( $host ) || ! in_array( $host, $bad, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function clean_utm( $content, &$count ) {
		$count = 0;
		$out   = preg_replace_callback(
			'/https?:\/\/[^\s"\'<>\]]+/u',
			function ( $m ) use ( &$count ) {
				$url    = $m[0];
				$before = $url;
				$url    = preg_replace( '/([?&])utm_source=chatgpt\.com&/i', '$1', $url );
				$url    = preg_replace( '/&utm_source=chatgpt\.com/i', '', (string) $url );
				$url    = preg_replace( '/\?utm_source=chatgpt\.com(?=$|[&#])/i', '', (string) $url );
				$url    = preg_replace( '/\?utm_source=chatgpt\.com/i', '', (string) $url );
				if ( $url !== $before ) {
					++$count;
				}
				return (string) $url;
			},
			(string) $content
		);
		return (string) $out;
	}

	private static function fix_citations( $html, $old_to_new, $removed ) {
		$parts = preg_split( '/(<[^>]+>)/u', (string) $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return (string) $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( '' === $part || '<' === $part[0] ) {
				continue;
			}
			$parts[ $i ] = preg_replace_callback(
				'/\[([0-9۰-۹٠-٩][0-9۰-۹٠-٩\s,،]*)\]/u',
				function ( $m ) use ( $old_to_new, $removed ) {
					$tokens = preg_split( '/([,،])/u', $m[1], -1, PREG_SPLIT_DELIM_CAPTURE );
					$out    = array();
					foreach ( (array) $tokens as $token ) {
						$trim = trim( $token );
						$num  = ( '' !== $trim && ',' !== $trim && '،' !== $trim ) ? (int) SA_AI_Engine::digits_to_en( $trim ) : null;
						if ( null === $num || (string) $num !== SA_AI_Engine::digits_to_en( $trim ) ) {
							$out[] = $token;
							continue;
						}
						if ( isset( $removed[ $num ] ) ) {
							continue;
						}
						$out[] = isset( $old_to_new[ $num ] ) ? SA_AI_Engine::en_digits_to_sample( $old_to_new[ $num ], $trim ) : $token;
					}
					$joined = trim( implode( '', $out ), " \t,،" );
					return '' === $joined ? '' : '[' . $joined . ']';
				},
				$part
			);
		}
		return implode( '', $parts );
	}

	private static function renumber_li( $li, $new_number ) {
		$out = preg_replace_callback(
			'/^(<li\b[^>]*>\s*(?:<p\b[^>]*>\s*)?(?:\[)?)([0-9۰-۹٠-٩]+)/u',
			function ( $m ) use ( $new_number ) {
				return $m[1] . SA_AI_Engine::en_digits_to_sample( $new_number, $m[2] );
			},
			(string) $li,
			1
		);
		return (string) $out;
	}

	public static function clean_sources( $content ) {
		$content  = (string) $content;
		$findings = array();
		$removed  = 0;
		$utm      = 0;
		$work     = self::clean_utm( $content, $utm );
		if ( $utm > 0 ) {
			$findings[] = array( 'pattern_id' => 'utm-source-chatgpt', 'level' => 'FLAG', 'sentence' => 'utm_source=chatgpt.com', 'action' => 'utm-cleaned' );
		}

		if ( ! preg_match_all( '#<h[2-6]\b[^>]*>(.*?)</h[2-6]\s*>#isu', $work, $heads, PREG_OFFSET_CAPTURE ) ) {
			return array( 'content' => $work, 'findings' => $findings, 'removed_chars' => 0 );
		}
		foreach ( $heads[0] as $idx => $head_match ) {
			$head_text = trim( self::normalize( preg_replace( '/<[^>]*>/u', '', $heads[1][ $idx ][0] ) ) );
			if ( ! preg_match( '/^(منابع|مراجع)/u', $head_text ) && ! preg_match( '/^(sources|references)$/i', $head_text ) ) {
				continue;
			}
			$after_head = $head_match[1] + strlen( $head_match[0] );
			$rest       = substr( $work, $after_head );
			if ( preg_match( '#<h[1-6]\b#i', $rest, $nh, PREG_OFFSET_CAPTURE ) ) {
				$rest = substr( $rest, 0, (int) $nh[0][1] );
			}
			if ( ! preg_match( '#<(ol|ul)\b[^>]*>(.*?)</\1\s*>#isu', $rest, $list, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			$list_full   = $list[0][0];
			$list_start  = $after_head + (int) $list[0][1];
			$inner       = $list[2][0];
			if ( ! preg_match_all( '#<li\b[^>]*>.*?</li\s*>#isu', $inner, $items ) ) {
				continue;
			}
			$kept       = array();
			$old_to_new = array();
			$removed_no = array();
			$next       = 1;
			$changed    = false;
			foreach ( $items[0] as $pos => $li ) {
				$old_no = $pos + 1;
				if ( self::is_bad_only( self::hosts_in( $li ) ) ) {
					$findings[]   = array( 'pattern_id' => 'bad-source-only', 'level' => 'DELETE', 'sentence' => trim( preg_replace( '/<[^>]*>/u', ' ', $li ) ), 'action' => 'source-deleted' );
					$removed     += mb_strlen( trim( preg_replace( '/<[^>]*>/u', ' ', $li ) ), 'UTF-8' );
					$removed_no[ $old_no ] = true;
					$changed      = true;
					continue;
				}
				$old_to_new[ $old_no ] = $next;
				$kept[]                = self::renumber_li( $li, $next );
				++$next;
			}
			if ( ! $changed ) {
				break;
			}
			$open_tag = preg_match( '#^<(?:ol|ul)\b[^>]*>#i', $list_full, $om ) ? $om[0] : '<ul>';
			$tag      = ( false !== stripos( $open_tag, '<ol' ) ) ? 'ol' : 'ul';
			$new_list = $open_tag . "\n" . implode( "\n", $kept ) . "\n</" . $tag . '>';
			$before   = substr( $work, 0, $list_start );
			$after    = substr( $work, $list_start + strlen( $list_full ) );
			$work     = self::fix_citations( $before, $old_to_new, $removed_no ) . $new_list . self::fix_citations( $after, $old_to_new, $removed_no );
			break;
		}
		return array( 'content' => $work, 'findings' => $findings, 'removed_chars' => $removed );
	}
}
