<?php
/**
 * Conservative, evidence-first rule evaluators. Unknown inputs produce insufficient.
 *
 * @package IranAuditEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IAAE_Evaluators {
	/**
	 * Evaluate one rule against a read-only content context.
	 *
	 * @param array $rule Rule definition.
	 * @param array $context Post context.
	 * @return array
	 */
	public static function evaluate( $rule, $context ) {
		$method = isset( $rule['evaluator'] ) ? (string) $rule['evaluator'] : '';
		$map    = array(
			'SEO-TITLE-01'           => 'seo_title',
			'SEO-META-01'            => 'seo_meta',
			'SEO-H1-01'              => 'h1',
			'SEO-HEADING-HIER-01'    => 'heading_hierarchy',
			'SEO-SLUG-01'            => 'slug',
			'SEO-INDEX-01'           => 'indexability',
			'SEO-KEYWORD-01'         => 'focus_keyword',
			'SEO-SCHEMA-01'          => 'schema',
			'STR-WORDS-01'           => 'word_count',
			'STR-PARA-01'            => 'paragraphs',
			'STR-INTRO-OUTRO-01'     => 'intro_outro',
			'STR-HEADING-DENSITY-01' => 'heading_density',
			'FA-CHARSET-01'          => 'charset',
			'FA-HALFSPACE-01'        => 'halfspace',
			'FA-PUNCT-01'            => 'punctuation',
			'FA-REPEAT-01'           => 'repetition',
			'FA-SPELL-01'            => 'spelling',
			'FA-TONE-01'             => 'tone',
			'DUP-INTERNAL-01'        => 'similarity',
			'DUP-CANNIBAL-01'        => 'cannibalization',
			'DUP-TEMPLATE-01'        => 'template_paragraph',
			'DUP-THIN-01'            => 'thin_content',
			'LNK-INT-01'             => 'internal_links',
			'LNK-ANCHOR-01'          => 'anchor_text',
			'LNK-ORPHAN-01'          => 'orphan',
			'LNK-EXT-01'             => 'external_links',
			'LNK-REL-01'             => 'link_attributes',
			'MED-NEED-01'            => 'image_ratio',
			'MED-ALT-01'             => 'image_alt',
			'MED-FORMAT-01'          => 'image_technical',
			'GEO-CHECKLIST-01'       => 'checklist',
			'GEO-MAP-01'             => 'map',
			'GEO-NAMES-01'           => 'geo_names',
			'GEO-CLAIMS-01'          => 'claims',
			'GEO-CONSISTENCY-01'     => 'consistency',
			'GEO-EEAT-01'            => 'eeat',
			'UX-CWV-01'              => 'cwv',
			'UX-HTML-01'             => 'render_integrity',
			'UX-MOBILE-01'           => 'mobile_overflow',
			'STR-PLACEHOLDER-01'     => 'placeholders',
			'STR-EMPTY-SECTION-01'   => 'empty_sections',
		);
		if ( ! isset( $map[ isset( $rule['id'] ) ? $rule['id'] : '' ] ) ) {
			return self::insufficient( 'برای این قانون ارزیاب ثبت نشده است.' );
		}
		$callback = $map[ $rule['id'] ];
		return self::$callback( $rule, $context );
	}

	/**
	 * Passing result.
	 *
	 * @param string $message Message.
	 * @param array  $evidence Evidence items.
	 * @return array
	 */
	private static function pass( $message, $evidence = array() ) {
		return array(
			'status'              => 'verified',
			'score'               => 1.0,
			'confidence'          => 'certain',
			'message'             => $message,
			'evidence'            => $evidence,
			'needs_human_review'  => false,
		);
	}

	/**
	 * Issue result; heuristic evidence is explicitly marked for review.
	 *
	 * @param string $message Message.
	 * @param array  $rule Rule.
	 * @param array  $evidence Evidence.
	 * @param bool   $human Human review required.
	 * @param float  $score Score contribution.
	 * @return array
	 */
	private static function issue( $message, $rule, $evidence = array(), $human = false, $score = 0.0 ) {
		$confidence = $human ? 'needs_human_review' : ( isset( $rule['confidence_default'] ) ? (string) $rule['confidence_default'] : 'certain' );
		return array(
			'status'             => $human || 'probable' === $confidence ? 'warning' : 'issue',
			'score'              => $score,
			'confidence'         => $confidence,
			'message'            => $message,
			'evidence'           => $evidence,
			'needs_human_review' => $human || 'needs_human_review' === $confidence,
		);
	}

	/**
	 * Missing/unsafe-to-infer result.
	 *
	 * @param string $message Explanation.
	 * @return array
	 */
	private static function insufficient( $message ) {
		return array(
			'status'             => 'insufficient',
			'score'              => null,
			'confidence'         => 'needs_human_review',
			'message'            => $message,
			'evidence'           => array(),
			'needs_human_review' => true,
		);
	}

	/**
	 * Plain text for word-oriented checks.
	 *
	 * @param array $context Context.
	 * @return string
	 */
	private static function text( $context ) {
		$text = isset( $context['text'] ) ? (string) $context['text'] : '';
		return trim( preg_replace( '/[\p{Z}\s]+/u', ' ', str_replace( "\xE2\x80\x8C", '', $text ) ) );
	}

	/**
	 * Count Unicode letter/number tokens.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function count_words( $text ) {
		$parts = preg_split( '/[^\p{L}\p{N}]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * Return rendered HTML or an insufficient result.
	 *
	 * @param array $context Context.
	 * @return string|array
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) $text );
	}

	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
	}

	private static function contains( $haystack, $needle ) {
		if ( function_exists( 'mb_strpos' ) ) {
			return false !== mb_strpos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return false !== strpos( (string) $haystack, (string) $needle );
	}

	private static function rendered( $context ) {
		$html = isset( $context['rendered_html'] ) ? (string) $context['rendered_html'] : '';
		if ( '' === $html ) {
			return self::insufficient( isset( $context['render_error'] ) && $context['render_error'] ? (string) $context['render_error'] : 'HTML رندرشده برای این صفحه در دسترس نیست؛ خروجی head از روی post_content حدس زده نمی‌شود.' );
		}
		return $html;
	}

	/**
	 * Extract HTML attributes, independent of attribute order and quote style.
	 *
	 * @param string $tag Tag markup.
	 * @param string $attribute Attribute name.
	 * @return string
	 */
	private static function attr( $tag, $attribute ) {
		$pattern = '/\b' . preg_quote( $attribute, '/' ) . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';
		if ( ! preg_match( $pattern, (string) $tag, $match ) ) {
			return '';
		}
		foreach ( array( 1, 2, 3 ) as $index ) {
			if ( isset( $match[ $index ] ) && '' !== $match[ $index ] ) {
				return html_entity_decode( $match[ $index ], ENT_QUOTES, 'UTF-8' );
			}
		}
		return '';
	}

	/**
	 * Get rendered meta value by name/property.
	 *
	 * @param string $html Rendered HTML.
	 * @param string $attribute Attribute name.
	 * @param string $value Attribute value.
	 * @param string $wanted Requested attribute.
	 * @return string
	 */
	private static function meta_value( $html, $attribute, $value, $wanted ) {
		if ( preg_match_all( '/<meta\b[^>]*>/i', $html, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( strtolower( self::attr( $tag, $attribute ) ) === strtolower( $value ) ) {
					return self::attr( $tag, $wanted );
				}
			}
		}
		return '';
	}

	/**
	 * Find simple tag contents.
	 *
	 * @param string $html HTML.
	 * @param string $tag_name Tag name.
	 * @return string[]
	 */
	private static function tag_contents( $html, $tag_name ) {
		preg_match_all( '/<' . preg_quote( $tag_name, '/' ) . '\b[^>]*>(.*?)<\/' . preg_quote( $tag_name, '/' ) . '\s*>/isu', (string) $html, $matches );
		return isset( $matches[1] ) ? array_map( 'wp_strip_all_tags', $matches[1] ) : array();
	}

	private static function seo_title( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$title = isset( self::tag_contents( $html, 'title' )[0] ) ? trim( self::tag_contents( $html, 'title' )[0] ) : '';
		$length = self::length( $title );
		$params = isset( $rule['params'] ) ? $rule['params'] : array();
		$min = isset( $params['min_chars'] ) ? (int) $params['min_chars'] : 25;
		$max = isset( $params['max_chars'] ) ? (int) $params['max_chars'] : 65;
		if ( '' === $title ) {
			return self::issue( 'در HTML رندرشده تگ title پیدا نشد.', $rule, array( array( 'text' => '', 'location' => array( 'source' => 'rendered_head' ) ) ) );
		}
		if ( $length < $min || $length > $max ) {
			return self::issue( sprintf( 'طول عنوان %d نویسه است؛ بازهٔ سیاست سایت %d تا %d است.', $length, $min, $max ), $rule, array( array( 'text' => $title, 'location' => array( 'source' => 'title' ) ) ), false );
		}
		return self::pass( 'عنوان رندرشده در بازهٔ سیاست داخلی است.', array( array( 'text' => $title, 'location' => array( 'source' => 'title' ) ) ) );
	}

	private static function seo_meta( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$description = self::meta_value( $html, 'name', 'description', 'content' );
		$length = self::length( trim( $description ) );
		$params = isset( $rule['params'] ) ? $rule['params'] : array();
		$min = isset( $params['min_chars'] ) ? (int) $params['min_chars'] : 70;
		$max = isset( $params['max_chars'] ) ? (int) $params['max_chars'] : 160;
		if ( '' === trim( $description ) || $length < $min || $length > $max ) {
			return self::issue( 'توضیح متای رندرشده خالی است یا با بازهٔ سیاست داخلی هم‌خوانی ندارد.', $rule, array( array( 'text' => $description, 'location' => array( 'source' => 'meta_description' ) ) ), false );
		}
		return self::pass( 'توضیح متای رندرشده در بازهٔ سیاست داخلی است.', array( array( 'text' => $description, 'location' => array( 'source' => 'meta_description' ) ) ) );
	}

	private static function h1( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$count = preg_match_all( '/<h1\b[^>]*>/i', $html, $unused );
		$expected = isset( $rule['params']['exact_count'] ) ? (int) $rule['params']['exact_count'] : 1;
		if ( $expected !== $count ) {
			return self::issue( sprintf( 'در HTML رندرشده %d سرتیتر H1 پیدا شد؛ انتظار سیاست %d است.', $count, $expected ), $rule, array( array( 'text' => (string) $count, 'location' => array( 'source' => 'rendered_body' ) ) ) );
		}
		return self::pass( 'تعداد H1 در HTML رندرشده با سیاست برابر است.' );
	}

	private static function heading_hierarchy( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h[1-6]\s*>/isu', $html, $matches, PREG_SET_ORDER );
		$previous = 0;
		foreach ( $matches as $match ) {
			$current = (int) $match[1];
			if ( $previous && $current > $previous + 1 ) {
				return self::issue( 'در توالی سرتیترهای رندرشده پرش سطح دیده شد.', $rule, array( array( 'text' => wp_strip_all_tags( $match[0] ), 'location' => array( 'source' => 'rendered_heading' ) ) ), true );
			}
			$previous = $current;
		}
		return self::pass( 'در توالی سرتیترهای رندرشده پرش سطح پیدا نشد.' );
	}

	private static function slug( $rule, $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		if ( ! ( $post instanceof WP_Post ) ) {
			return self::insufficient( 'نوشته در دسترس نیست.' );
		}
		$slug = (string) $post->post_name;
		$max = isset( $rule['params']['max_chars'] ) ? (int) $rule['params']['max_chars'] : 80;
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $slug, 'UTF-8' ) : strlen( $slug );
		if ( '' === $slug || $length > $max || preg_match( '/[^\p{L}\p{N}-]/u', $slug ) ) {
			return self::issue( 'نامک خالی، بیش از حد بلند یا دارای نویسهٔ غیرمعمول است.', $rule, array( array( 'text' => $slug, 'location' => array( 'source' => 'post_name' ) ) ) );
		}
		return self::pass( 'نامک از نظر طول و نویسه‌های پایه مشکلی ندارد.', array( array( 'text' => $slug, 'location' => array( 'source' => 'post_name' ) ) ) );
	}

	private static function indexability( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$robots = strtolower( self::meta_value( $html, 'name', 'robots', 'content' ) );
		if ( preg_match( '/\bnoindex\b/', $robots ) ) {
			return self::issue( 'دستور noindex در HTML رندرشده وجود دارد.', $rule, array( array( 'text' => $robots, 'location' => array( 'source' => 'meta_robots' ) ) ) );
		}
		$canonical = '';
		if ( preg_match_all( '/<link\b[^>]*>/i', $html, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				if ( 'canonical' === strtolower( self::attr( $tag, 'rel' ) ) ) {
					$canonical = self::attr( $tag, 'href' );
					break;
				}
			}
		}
		$expected = isset( $context['post_url'] ) ? untrailingslashit( (string) $context['post_url'] ) : '';
		if ( '' !== $canonical && '' !== $expected && untrailingslashit( $canonical ) !== $expected ) {
			return self::issue( 'canonical رندرشده به نشانی متفاوتی اشاره می‌کند.', $rule, array( array( 'text' => $canonical, 'location' => array( 'source' => 'canonical' ) ) ) );
		}
		if ( '' === $canonical ) {
			return self::insufficient( 'canonical در HTML پیدا نشد؛ تنظیمات افزونهٔ SEO یا هدر پاسخ باید در staging بررسی شود.' );
		}
		return self::pass( 'noindex ناخواسته پیدا نشد و canonical با URL صفحه برابر است.', array( array( 'text' => $canonical, 'location' => array( 'source' => 'canonical' ) ) ) );
	}

	private static function focus_keyword( $rule, $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		if ( ! ( $post instanceof WP_Post ) ) {
			return self::insufficient( 'نوشته در دسترس نیست.' );
		}
		$keyword = '';
		foreach ( array( '_rank_math_focus_keyword', '_yoast_wpseo_focuskw', 'sa_focus_keyword' ) as $meta_key ) {
			$keyword = trim( (string) get_post_meta( $post->ID, $meta_key, true ) );
			if ( '' !== $keyword ) {
				break;
			}
		}
		if ( '' === $keyword ) {
			return self::insufficient( 'کلمهٔ کانونی در کلیدهای شناخته‌شده پیدا نشد؛ نبود این فیلد در خروجی سایت اثبات نشده است.' );
		}
		$text = self::lower( self::text( $context ) );
		$needle = self::lower( $keyword );
		$count = substr_count( $text, $needle );
		$words = max( 1, self::count_words( $text ) );
		$density = ( $count / $words ) * 100;
		$max = isset( $rule['params']['stuffing_density_max_percent'] ) ? (float) $rule['params']['stuffing_density_max_percent'] : 3.0;
		if ( $density > $max ) {
			return self::issue( 'تکرار عبارت کانونی از آستانهٔ هشدار داخلی بالاتر است.', $rule, array( array( 'text' => $keyword, 'count' => $count, 'density_percent' => round( $density, 2 ), 'location' => array( 'source' => 'post_content' ) ) ), true );
		}
		return self::pass( 'تراکم عبارت کانونی از آستانهٔ داخلی بالاتر نیست.', array( array( 'text' => $keyword, 'count' => $count, 'location' => array( 'source' => 'post_content' ) ) ) );
	}

	private static function schema( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		preg_match_all( '/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/isu', $html, $scripts );
		$types = array();
		$invalid = array();
		foreach ( (array) $scripts[1] as $index => $json ) {
			$data = json_decode( html_entity_decode( trim( $json ), ENT_QUOTES, 'UTF-8' ), true );
			if ( ! is_array( $data ) || JSON_ERROR_NONE !== json_last_error() ) {
				$invalid[] = (int) $index + 1;
				continue;
			}
			self::collect_schema_types( $data, $types );
		}
		$expected = isset( $rule['params']['expected_types'] ) ? (array) $rule['params']['expected_types'] : array();
		$missing = array_values( array_diff( $expected, array_unique( $types ) ) );
		if ( $invalid || $missing ) {
			return self::issue( $invalid ? 'یک یا چند بلوک JSON-LD قابل‌تجزیه نیست.' : 'نوع(های) مورد انتظار در تنظیمات قانون در JSON-LD پیدا نشد.', $rule, array( array( 'missing_types' => $missing, 'invalid_blocks' => $invalid, 'found_types' => array_values( array_unique( $types ) ), 'location' => array( 'source' => 'json_ld' ) ) ), true );
		}
		return self::pass( 'بلوک‌های JSON-LD قابل‌تجزیه‌اند و نوع‌های مورد انتظار قانون وجود دارند.', array( array( 'found_types' => array_values( array_unique( $types ) ), 'location' => array( 'source' => 'json_ld' ) ) ) );
	}

	private static function collect_schema_types( $node, &$types ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['@type'] ) ) {
			foreach ( (array) $node['@type'] as $type ) {
				$parts = explode( '/', (string) $type );
				$types[] = (string) end( $parts );
			}
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::collect_schema_types( $value, $types );
			}
		}
	}

	private static function word_count( $rule, $context ) {
		$profile = isset( $context['profile'] ) ? (string) $context['profile'] : '';
		$data = IAAE_Rules::all();
		if ( '' === $profile || empty( $data['profiles'][ $profile ]['min_words'] ) ) {
			return self::insufficient( 'نگاشت پروفایل این نوع‌نوشته تأیید نشده است؛ حداقل کلمه حدس زده نمی‌شود.' );
		}
		$count = self::count_words( self::text( $context ) );
		$min = (int) $data['profiles'][ $profile ]['min_words'];
		$evidence = array( array( 'word_count' => $count, 'min_words' => $min, 'location' => array( 'source' => 'post_content' ) ) );
		if ( $count < $min ) {
			return self::issue( sprintf( 'تعداد واژه‌ها %d است؛ آستانهٔ سیاست داخلی این پروفایل %d است.', $count, $min ), $rule, $evidence, true );
		}
		return self::pass( 'تعداد واژه‌ها به آستانهٔ داخلی پروفایل رسیده است.', $evidence );
	}

	private static function paragraphs( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		preg_match_all( '/<p\b[^>]*>(.*?)<\/p\s*>/isu', $html, $matches );
		if ( empty( $matches[1] ) ) {
			return self::insufficient( 'پاراگراف HTML از post_content استخراج نشد.' );
		}
		$params = isset( $rule['params'] ) ? $rule['params'] : array();
		$max_words = isset( $params['max_paragraph_words'] ) ? (int) $params['max_paragraph_words'] : 150;
		$max_sentence = isset( $params['max_sentence_words'] ) ? (int) $params['max_sentence_words'] : 40;
		foreach ( $matches[1] as $index => $paragraph ) {
			$plain = trim( wp_strip_all_tags( $paragraph ) );
			$words = self::count_words( $plain );
			if ( $words > $max_words ) {
				return self::issue( 'پاراگراف از سقف خوانایی داخلی بلندتر است.', $rule, array( array( 'text' => $plain, 'word_count' => $words, 'location' => array( 'paragraph' => $index + 1 ) ) ), true );
			}
			$sentences = preg_split( '/(?<=[.!؟?])\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( (array) $sentences as $sentence ) {
				if ( self::count_words( $sentence ) > $max_sentence ) {
					return self::issue( 'جمله از سقف خوانایی داخلی بلندتر است.', $rule, array( array( 'text' => $sentence, 'location' => array( 'paragraph' => $index + 1 ) ) ), true );
				}
			}
		}
		return self::pass( 'پاراگراف‌ها و جمله‌های شناسایی‌شده از آستانهٔ داخلی بلندتر نیستند.' );
	}

	private static function intro_outro( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		$paragraphs = self::tag_contents( $html, 'p' );
		$meaningful = array_values( array_filter( array_map( 'trim', $paragraphs ), static function ( $p ) { return self::count_words( $p ) >= 8; } ) );
		if ( count( $meaningful ) < 2 ) {
			return self::issue( 'مقدمه/جمع‌بندی قابل‌شناسایی در متن ذخیره‌شده کافی نیست؛ این تشخیص ساختاری تقریبی است.', $rule, array( array( 'paragraphs_with_eight_words' => count( $meaningful ), 'location' => array( 'source' => 'post_content' ) ) ), true );
		}
		return self::pass( 'برای این بررسی ساختاری، پاراگراف آغازین و پایانی قابل‌استفاده وجود دارد.' );
	}

	private static function heading_density( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		$text = isset( $context['text'] ) ? (string) $context['text'] : '';
		$max = isset( $rule['params']['max_words_between_headings'] ) ? (int) $rule['params']['max_words_between_headings'] : 300;
		$pattern = '/<(?:h[1-6]|p|ul|ol|table|blockquote)\b[^>]*>.*?<\/(?:h[1-6]|p|ul|ol|table|blockquote)\s*>/isu';
		preg_match_all( $pattern, $html, $blocks );
		if ( empty( $blocks[0] ) ) {
			return self::insufficient( 'بلوک‌های ساختاری برای محاسبهٔ فاصلهٔ سرتیترها استخراج نشد.' );
		}
		$words_since = 0;
		foreach ( $blocks[0] as $block ) {
			if ( preg_match( '/^<h[1-6]\b/i', $block ) ) {
				if ( $words_since > $max ) {
					return self::issue( 'فاصلهٔ متنی بین دو سرتیتر از آستانهٔ داخلی بیشتر است.', $rule, array( array( 'words_between_headings' => $words_since, 'max_words' => $max, 'location' => array( 'source' => 'post_content' ) ) ), true );
				}
				$words_since = 0;
			} else {
				$words_since += self::count_words( wp_strip_all_tags( $block ) );
			}
		}
		return self::pass( 'فاصلهٔ متنی شناسایی‌شده از آستانهٔ داخلی بیشتر نیست.' );
	}

	private static function charset( $rule, $context ) {
		$text = self::text( $context );
		$matches = array();
		foreach ( array( 'ي' => 'ی', 'ك' => 'ک' ) as $bad => $good ) {
			if ( self::contains( $text, $bad ) ) {
				$matches[] = array( 'text' => $bad, 'expected' => $good );
			}
		}
		$has_ascii = preg_match( '/[0-9]/', $text );
		$has_persian = preg_match( '/[۰-۹]/u', $text );
		$has_arabic = preg_match( '/[٠-٩]/u', $text );
		if ( ( $has_ascii && $has_persian ) || ( $has_arabic && ( $has_ascii || $has_persian ) ) ) {
			$matches[] = array( 'text' => 'mixed-digit-sets', 'expected' => 'یکدست‌سازی ارقام' );
		}
		if ( $matches ) {
			return self::issue( 'نویسه یا مجموعهٔ رقم ناهمسان در متن پیدا شد.', $rule, array( array( 'items' => $matches, 'location' => array( 'source' => 'post_content' ) ) ) );
		}
		return self::pass( 'ی/ک عربی یا ترکیب چند مجموعهٔ رقم پیدا نشد.' );
	}

	private static function halfspace( $rule, $context ) {
		$text = self::text( $context );
		$patterns = array(
			'prefix_mi' => '/(?<![\p{L}])می\s+[\p{L}]{2,}/u',
			'suffix_ha' => '/[\p{L}]{2,}\s+ها(?![\p{L}])/u',
			'suffix_tar' => '/[\p{L}]{2,}\s+تر(?![\p{L}])/u',
			'suffix_tarin' => '/[\p{L}]{2,}\s+ترین(?![\p{L}])/u',
		);
		foreach ( $patterns as $label => $pattern ) {
			if ( preg_match( $pattern, $text, $match, PREG_OFFSET_CAPTURE ) ) {
				return self::issue( 'الگوی فاصله‌گذاریِ قابل‌بررسی برای نیم‌فاصله پیدا شد؛ موردهای استثنا باید انسانی مرور شوند.', $rule, array( array( 'text' => $match[0][0], 'pattern' => $label, 'location' => array( 'source' => 'post_content', 'byte_offset' => (int) $match[0][1] ) ) ), true );
			}
		}
		return self::pass( 'الگوی روشنِ فاصلهٔ معمولی پیشوند/پسوند پیدا نشد.' );
	}

	private static function punctuation( $rule, $context ) {
		$text = self::text( $context );
		if ( preg_match( '/\s+[،؛؟.!,:;](?:\s|$)|[،؛؟.!,:;][\p{L}\p{N}]/u', $text, $match, PREG_OFFSET_CAPTURE ) ) {
			return self::issue( 'فاصله‌گذاری پیرامون نشانهٔ نگارشی نیازمند بازبینی است.', $rule, array( array( 'text' => $match[0][0], 'location' => array( 'source' => 'post_content', 'byte_offset' => (int) $match[0][1] ) ) ), true );
		}
		return self::pass( 'الگوی روشنِ فاصله‌گذاری نادرست پیرامون نشانه پیدا نشد.' );
	}

	private static function repetition( $rule, $context ) {
		$text = isset( $context['content_html'] ) ? (string) $context['content_html'] : self::text( $context );
		$paragraphs = preg_split( '/<\/p\s*>/i', $text );
		$max = isset( $rule['params']['max_same_word_per_paragraph'] ) ? (int) $rule['params']['max_same_word_per_paragraph'] : 5;
		foreach ( $paragraphs as $index => $paragraph ) {
			$plain = self::lower( wp_strip_all_tags( $paragraph ) );
			$words = preg_split( '/[^\p{L}\p{N}]+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! $words ) {
				continue;
			}
			$counts = array_count_values( $words );
			arsort( $counts );
			$word = key( $counts );
			$count = reset( $counts );
			if ( $count > $max && self::length( $word ) > 2 ) {
				return self::issue( 'یک واژه در یک پاراگراف بیش از آستانهٔ داخلی تکرار شده است.', $rule, array( array( 'text' => $word, 'count' => (int) $count, 'location' => array( 'paragraph' => $index + 1 ) ) ), true );
			}
		}
		return self::pass( 'تکرار واژه از آستانهٔ داخلی عبور نکرد.' );
	}

	private static function spelling( $rule, $context ) {
		$data = IAAE_Rules::support_data( 'spelling-lexicon.json' );
		if ( is_wp_error( $data ) || empty( $data['entries'] ) ) {
			return self::insufficient( 'واژه‌نامهٔ تأییدشده خالی است؛ غلط املایی از حافظه ساخته نمی‌شود.' );
		}
		$text = self::text( $context );
		$protected = isset( $data['protected_terms'] ) ? array_map( 'strval', (array) $data['protected_terms'] ) : array();
		$exceptions = isset( $data['exceptions'] ) ? array_map( 'strval', (array) $data['exceptions'] ) : array();
		foreach ( $data['entries'] as $entry ) {
			$wrong = isset( $entry['wrong'] ) ? (string) $entry['wrong'] : '';
			if ( '' === $wrong || in_array( $wrong, $protected, true ) || in_array( $wrong, $exceptions, true ) ) {
				continue;
			}
			if ( self::contains( $text, $wrong ) ) {
				return self::issue( 'یک مورد واژه‌نامه‌ای در متن پیدا شد.', $rule, array( array( 'text' => $wrong, 'suggestion' => isset( $entry['right'] ) ? (string) $entry['right'] : '', 'location' => array( 'source' => 'post_content' ) ) ), 'probable' === ( isset( $entry['confidence'] ) ? $entry['confidence'] : 'certain' ) );
			}
		}
		return self::pass( 'موردی از واژه‌نامهٔ تأییدشده پیدا نشد.' );
	}

	private static function tone( $rule, $context ) {
		$data = IAAE_Rules::support_data( 'cliches-fa.json' );
		if ( is_wp_error( $data ) || empty( $data['entries'] ) ) {
			return self::insufficient( 'فهرست کلیشهٔ تأییدشده خالی است؛ تشخیص لحن ماشینی قطعی نیست.' );
		}
		$text = self::text( $context );
		foreach ( $data['entries'] as $entry ) {
			$phrase = is_array( $entry ) && isset( $entry['phrase'] ) ? (string) $entry['phrase'] : (string) $entry;
			if ( '' !== $phrase && self::contains( $text, $phrase ) ) {
				return self::issue( 'عبارت موجود در فهرست کلیشه پیدا شد؛ فقط پیشنهاد بازبینی انسانی است.', $rule, array( array( 'text' => $phrase, 'location' => array( 'source' => 'post_content' ) ) ), true, 0.5 );
			}
		}
		return self::pass( 'عبارتی از فهرست کلیشهٔ تأییدشده پیدا نشد.' );
	}

	private static function similarity( $rule, $context ) {
		return self::insufficient( 'پیش‌محاسبهٔ شباهتِ کل مجموعه هنوز پیاده/اعتبارسنجی نشده است؛ از مقایسهٔ ناقص نتیجهٔ duplicate ساخته نمی‌شود.' );
	}

	private static function cannibalization( $rule, $context ) {
		return self::insufficient( 'دادهٔ کلمهٔ کانونی سایر صفحه‌ها و corpus کامل هنوز بررسی نشده است.' );
	}

	private static function template_paragraph( $rule, $context ) {
		return self::insufficient( 'مقایسهٔ پاراگراف با مجموعهٔ هم‌پروفایل هنوز آماده نیست.' );
	}

	private static function thin_content( $rule, $context ) {
		return self::insufficient( 'شمارش «واقعیت یکتا» نیازمند معیار و Golden Set انسانی است؛ از تعداد عدد/اسم به‌تنهایی حکم ساخته نمی‌شود.' );
	}

	private static function internal_links( $rule, $context ) {
		$links = self::links( isset( $context['content_html'] ) ? $context['content_html'] : '' );
		$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$count = 0;
		foreach ( $links as $link ) {
			$host = strtolower( (string) wp_parse_url( $link['href'], PHP_URL_HOST ) );
			if ( '' === $host || $host === $home_host ) {
				++$count;
			}
		}
		$params = isset( $rule['params'] ) ? $rule['params'] : array();
		$min = isset( $params['min_internal_links'] ) ? (int) $params['min_internal_links'] : 3;
		$max = isset( $params['max_internal_links'] ) ? (int) $params['max_internal_links'] : 40;
		if ( $count < $min || $count > $max ) {
			return self::issue( sprintf( 'تعداد لینک داخلی %d است؛ بازهٔ سیاست داخلی %d تا %d است.', $count, $min, $max ), $rule, array( array( 'count' => $count, 'location' => array( 'source' => 'post_content' ) ) ), true );
		}
		return self::pass( 'تعداد لینک داخلی در بازهٔ سیاست داخلی است.', array( array( 'count' => $count, 'location' => array( 'source' => 'post_content' ) ) ) );
	}

	private static function anchor_text( $rule, $context ) {
		$links = self::links( isset( $context['content_html'] ) ? $context['content_html'] : '' );
		$bad = isset( $rule['params']['bad_anchors'] ) ? (array) $rule['params']['bad_anchors'] : array();
		foreach ( $links as $link ) {
			$anchor = trim( wp_strip_all_tags( $link['inner'] ) );
			if ( '' === $anchor || in_array( $anchor, $bad, true ) ) {
				return self::issue( 'متن یک لینک خالی یا از عبارت‌های عمومیِ پیکربندی‌شده است.', $rule, array( array( 'text' => $anchor, 'href' => $link['href'], 'location' => array( 'source' => 'post_content' ) ) ), true );
			}
		}
		return self::pass( 'متن لینک‌های استخراج‌شده خالی یا عیناً یکی از عبارت‌های عمومی نیست.' );
	}

	private static function orphan( $rule, $context ) {
		return self::insufficient( 'برای اثبات یتیم‌بودن، ایندکس کامل و تازهٔ لینک‌های ورودی لازم است؛ هنوز موجود نیست.' );
	}

	private static function external_links( $rule, $context ) {
		return self::insufficient( 'بررسی HTTP بیرونی پیش‌فرض خاموش است و تا تأیید تست staging اجرا نمی‌شود.' );
	}

	private static function link_attributes( $rule, $context ) {
		$links = self::links( isset( $context['content_html'] ) ? $context['content_html'] : '' );
		foreach ( $links as $link ) {
			$href = trim( $link['href'] );
			$rel = strtolower( $link['rel'] );
			if ( '' === $href || '#' === $href || 0 === stripos( $href, 'javascript:' ) ) {
				return self::issue( 'یک لینک href خالی یا اجرایی/بی‌مقصد دارد.', $rule, array( array( 'href' => $href, 'location' => array( 'source' => 'post_content' ) ) ) );
			}
			if ( '_blank' === strtolower( $link['target'] ) && ! preg_match( '/\b(?:noopener|noreferrer)\b/', $rel ) ) {
				return self::issue( 'لینک target="_blank" فاقد rel noopener/noreferrer است.', $rule, array( array( 'href' => $href, 'target' => $link['target'], 'rel' => $rel, 'location' => array( 'source' => 'post_content' ) ) ) );
			}
		}
		return self::pass( 'href خالی/اجرایی و target بدون rel امن پیدا نشد.' );
	}

	private static function image_ratio( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		$image_count = preg_match_all( '/<img\b/i', $html, $unused );
		$words = self::count_words( self::text( $context ) );
		$ratio = isset( $rule['params']['min_images_per_500_words'] ) ? (float) $rule['params']['min_images_per_500_words'] : 1;
		$required = (int) floor( $words / 500 * $ratio );
		$post = isset( $context['post'] ) ? $context['post'] : null;
		$featured_required = ! empty( $rule['params']['require_featured_image'] );
		$has_featured = $post instanceof WP_Post && function_exists( 'has_post_thumbnail' ) ? has_post_thumbnail( $post->ID ) : null;
		if ( $image_count < $required || ( $featured_required && false === $has_featured ) ) {
			return self::issue( 'تعداد تصویر یا تصویر شاخص از پیشنهاد داخلی قانون کمتر است.', $rule, array( array( 'images_in_body' => $image_count, 'required_by_ratio' => $required, 'has_featured_image' => $has_featured, 'word_count' => $words, 'location' => array( 'source' => 'post_content' ) ) ), true );
		}
		return self::pass( 'نسبت پیشنهادی تصویر و وضعیت تصویر شاخص با معیار داخلی هم‌خوان است.', array( array( 'images_in_body' => $image_count, 'required_by_ratio' => $required, 'has_featured_image' => $has_featured, 'location' => array( 'source' => 'post_content' ) ) ) );
	}

	private static function image_alt( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		preg_match_all( '/<img\b[^>]*>/i', $html, $matches );
		foreach ( $matches[0] as $index => $tag ) {
			$alt = self::attr( $tag, 'alt' );
			if ( '' === trim( $alt ) ) {
				return self::issue( 'برای یک تصویرِ محتوایی alt خالی یا غایب است؛ تصویر تزئینی باید انسانی تشخیص داده شود.', $rule, array( array( 'image' => $tag, 'location' => array( 'image_index' => $index + 1 ) ) ), true );
			}
		}
		return self::pass( 'برای تصویرهای داخل متن alt غیرخالی وجود دارد.' );
	}

	private static function image_technical( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		preg_match_all( '/<img\b[^>]*>/i', $html, $matches );
		foreach ( $matches[0] as $index => $tag ) {
			$src = self::attr( $tag, 'src' );
			$width = self::attr( $tag, 'width' );
			$height = self::attr( $tag, 'height' );
			if ( '' === $src || '' === $width || '' === $height ) {
				return self::issue( 'نشانی یا ابعاد صریح یک تصویر در متن پیدا نشد.', $rule, array( array( 'src' => $src, 'width' => $width, 'height' => $height, 'location' => array( 'image_index' => $index + 1 ) ) ), true );
			}
		}
		if ( empty( $matches[0] ) ) {
			return self::insufficient( 'تصویری در post_content پیدا نشد؛ خروجی تصویر شاخص در HTML رندرشده بررسی نشده است.' );
		}
		return self::pass( 'نشانی و ابعاد صریح برای تصویرهای داخل متن وجود دارد؛ حجم فایل در این بررسی اندازه‌گیری نشده است.' );
	}

	private static function checklist( $rule, $context ) {
		$profile = isset( $context['profile'] ) ? (string) $context['profile'] : '';
		$data = IAAE_Rules::all();
		if ( '' === $profile || empty( $data['profile_checklists'][ $profile ] ) ) {
			return self::insufficient( 'نگاشت پروفایل/چک‌لیست تأیید نشده است.' );
		}
		return self::insufficient( 'چک‌لیست پروفایل برای هر بخش به نگاشت تیترهای فارسیِ تأییدشده و Golden Set نیاز دارد.' );
	}

	private static function map( $rule, $context ) {
		$post = isset( $context['post'] ) ? $context['post'] : null;
		$params = isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array();
		$accepted = isset( $params['accepted_embeds'] ) ? (array) $params['accepted_embeds'] : array();
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		$has_map_embed = false;
		foreach ( $accepted as $embed ) {
			$pattern = 'google_maps' === $embed ? '/google\.[^"\']+\/maps|maps\.google\./i' : '/' . preg_quote( (string) $embed, '/' ) . '/i';
			if ( preg_match( $pattern, $html ) ) {
				$has_map_embed = true;
				break;
			}
		}

		$config = self::coordinate_config( $rule, $post );
		$has_coordinates = false;
		$has_map_meta = false;
		if ( $post instanceof WP_Post ) {
			$latitude = '' !== $config['latitude'] ? get_post_meta( $post->ID, $config['latitude'], true ) : '';
			$longitude = '' !== $config['longitude'] ? get_post_meta( $post->ID, $config['longitude'], true ) : '';
			$has_coordinates = self::valid_coordinate_pair( $latitude, $longitude );
			if ( '' !== $config['map_url'] ) {
				$map_url = get_post_meta( $post->ID, $config['map_url'], true );
				$has_map_meta = self::accepted_map_url( $map_url, $accepted );
			}
		}
		if ( $has_coordinates || $has_map_embed || $has_map_meta ) {
			return self::pass(
				'نقشه یا جفت مختصات معتبر از HTML/متای پیکربندی‌شده پیدا شد؛ صحت جغرافیایی مختصات مستقلاً تأیید نمی‌شود.',
				array(
					array(
						'has_map_embed' => $has_map_embed,
						'has_map_meta' => $has_map_meta,
						'has_configured_coordinate' => $has_coordinates,
						'location' => array( 'source' => 'post_content/post_meta' ),
					),
				)
			);
		}
		if ( '' === $config['latitude'] || '' === $config['longitude'] ) {
			return self::insufficient( 'کلیدهای جفت مختصات این نوع‌نوشته پیکربندی نشده‌اند و نقشهٔ قابل‌قبولی هم پیدا نشد.' );
		}
		return self::issue( 'نقشه یا جفت مختصات معتبر از ورودی‌های پیکربندی‌شده پیدا نشد.', $rule, array( array( 'location' => array( 'source' => 'post_content/post_meta' ) ) ), true );
	}

	/** Resolve coordinate metadata keys from this site's schema and safe overrides. */
	private static function coordinate_config( $rule, $post ) {
		$defaults = array(
			'province' => array( 'latitude' => 'sa_province_latitude', 'longitude' => 'sa_province_longitude', 'map_url' => 'sa_google_map_url' ),
			'city' => array( 'latitude' => 'sa_city_latitude', 'longitude' => 'sa_city_longitude', 'map_url' => 'sa_google_map_url' ),
			'attraction' => array( 'latitude' => 'sa_latitude', 'longitude' => 'sa_longitude', 'map_url' => 'sa_google_map_url' ),
		);
		$post_type = $post instanceof WP_Post ? sanitize_key( (string) $post->post_type ) : '';
		$config = isset( $defaults[ $post_type ] ) ? $defaults[ $post_type ] : array();
		$settings = IAAE_Database::get_setting( 'coordinate_meta_keys', array() );
		if ( is_array( $settings ) && isset( $settings[ $post_type ] ) && is_array( $settings[ $post_type ] ) ) {
			$config = array_merge( $config, $settings[ $post_type ] );
		} elseif ( is_array( $settings ) && isset( $settings['latitude'], $settings['longitude'] ) ) {
			$config = array_merge( $config, $settings );
		} elseif ( ! empty( $settings ) && array_values( $settings ) === $settings && count( $settings ) >= 2 ) {
			$config['latitude'] = (string) $settings[0];
			$config['longitude'] = (string) $settings[1];
		}
		$rule_keys = isset( $rule['params']['coordinate_meta_keys'] ) ? (array) $rule['params']['coordinate_meta_keys'] : array();
		if ( count( $rule_keys ) >= 2 ) {
			$rule_keys = array_values( $rule_keys );
			$config['latitude'] = (string) $rule_keys[0];
			$config['longitude'] = (string) $rule_keys[1];
		}
		return array(
			'latitude' => isset( $config['latitude'] ) ? sanitize_key( (string) $config['latitude'] ) : '',
			'longitude' => isset( $config['longitude'] ) ? sanitize_key( (string) $config['longitude'] ) : '',
			'map_url' => isset( $config['map_url'] ) ? sanitize_key( (string) $config['map_url'] ) : '',
		);
	}

	/** Both coordinate fields must be numeric and within geographic bounds. */
	private static function valid_coordinate_pair( $latitude, $longitude ) {
		$translate = array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.',
		);
		$latitude = strtr( trim( (string) $latitude ), $translate );
		$longitude = strtr( trim( (string) $longitude ), $translate );
		if ( ! is_numeric( $latitude ) || ! is_numeric( $longitude ) ) {
			return false;
		}
		$latitude = (float) $latitude;
		$longitude = (float) $longitude;
		return $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180;
	}

	/** Accept only known map-provider links; never fetch the URL. */
	private static function accepted_map_url( $url, $accepted ) {
		$url = is_scalar( $url ) ? trim( (string) $url ) : '';
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
			return false;
		}
		$host_matches = static function ( $host, $domain ) {
			return $host === $domain || ( strlen( $host ) > strlen( $domain ) && '.' . $domain === substr( $host, -strlen( $domain ) - 1 ) );
		};
		foreach ( (array) $accepted as $provider ) {
			if ( 'google_maps' === $provider && ( $host_matches( $host, 'google.com' ) || preg_match( '/(^|\.)maps\.google\.[a-z.]+$/', $host ) ) ) { return true; }
			if ( 'osm' === $provider && $host_matches( $host, 'openstreetmap.org' ) ) { return true; }
			if ( 'balad' === $provider && $host_matches( $host, 'balad.ir' ) ) { return true; }
			if ( 'neshan' === $provider && $host_matches( $host, 'neshan.org' ) ) { return true; }
		}
		return false;
	}
	private static function geo_names( $rule, $context ) {
		$data = IAAE_Rules::support_data( 'iran-divisions.csv' );
		if ( is_wp_error( $data ) ) {
			return self::insufficient( 'دیتاست نسخه‌دار و موردتأیید تقسیمات کشوری در بسته موجود نیست.' );
		}
		return self::insufficient( 'فایل تقسیمات موجود است، اما parser/alias map هنوز به فرمت و منبع نسخه‌دار تأییدشده متصل نشده است.' );
	}

	private static function claims( $rule, $context ) {
		$text = self::text( $context );
		$claims = array();
		$patterns = array(
			'year'       => '/(?<!\p{N})(?:1[0-4][0-9]{2}|13[0-9]{2}|14[0-9]{2}|20[0-9]{2}|13[۰-۹]{2}|14[۰-۹]{2})(?!\p{N})/u',
			'population' => '/[\p{N},٬.٫ ]+\s*(?:نفر|میلیون\s*نفر|هزار\s*نفر)/u',
			'area'       => '/[\p{N},٬.٫ ]+\s*(?:کیلومتر\s*مربع|هکتار)/u',
			'elevation'  => '/[\p{N},٬.٫ ]+\s*(?:متر\s*(?:از\s*سطح\s*دریا)?)/u',
		);
		foreach ( $patterns as $kind => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( array_slice( $matches[0], 0, 40 ) as $match ) {
					$claims[] = array( 'kind' => $kind, 'value' => trim( $match[0] ), 'offset' => (int) $match[1] );
				}
			}
		}
		$context['extracted_claims'] = $claims;
		return self::issue( 'ادعاهای عددی/تاریخی فقط استخراج شده‌اند؛ صحت آن‌ها ارزیابی نمی‌شود و نیازمند بررسی انسانی است.', $rule, array( array( 'claims' => $claims, 'location' => array( 'source' => 'post_content' ) ) ), true, 0.5 );
	}

	private static function consistency( $rule, $context ) {
		return self::insufficient( 'تشخیص تعارض معنایی میان اعداد به استخراج موضوعی و Golden Set نیاز دارد؛ عددهای متفاوتِ بی‌زمینه با هم مقایسه نمی‌شوند.' );
	}

	private static function eeat( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$post = isset( $context['post'] ) ? $context['post'] : null;
		$has_author = $post instanceof WP_Post && '' !== (string) get_the_author_meta( 'display_name', $post->post_author );
		$has_date = (bool) preg_match( '/<time\b[^>]*datetime=/i', $html );
		$has_sources = false !== stripos( $html, 'id="sources"' ) || false !== stripos( $html, 'class="sa-sources' );
		$missing = array();
		if ( ! $has_author ) { $missing[] = 'author'; }
		if ( ! $has_date ) { $missing[] = 'date'; }
		if ( ! $has_sources ) { $missing[] = 'sources'; }
		if ( $missing ) {
			return self::issue( 'یکی از نشانه‌های نویسنده/تاریخ/منابعِ مورد انتظار قانون در رندر پیدا نشد؛ انتظار قانون باید با پروفایل صفحه تطبیق داده شود.', $rule, array( array( 'missing' => $missing, 'location' => array( 'source' => 'rendered_html' ) ) ), true );
		}
		return self::pass( 'نشانه‌های نویسنده، تاریخ و منابع در HTML رندرشده پیدا شد.' );
	}

	private static function cwv( $rule, $context ) {
		return self::insufficient( 'قانون PageSpeed غیرفعال است؛ API key یا ارسال URL به سرویس بیرونی بدون تأیید صریح انجام نمی‌شود.' );
	}

	private static function render_integrity( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		$issues = array();
		if ( preg_match( '/\[(?:gallery|caption|embed|audio|video|map)\b[^\]]*\]/i', $html, $m ) ) {
			$issues[] = array( 'text' => $m[0], 'kind' => 'unrendered_shortcode' );
		}
		if ( is_ssl() && preg_match( '/(?:src|href)=["\']http:\/\//i', $html, $m ) ) {
			$issues[] = array( 'text' => $m[0], 'kind' => 'mixed_content' );
		}
		if ( $issues ) {
			return self::issue( 'نشانهٔ قابل‌مشاهدهٔ شورت‌کد رندرنشده یا mixed content پیدا شد.', $rule, array( array( 'matches' => $issues, 'location' => array( 'source' => 'rendered_html' ) ) ) );
		}
		return self::pass( 'الگوی مشخص شورت‌کد رندرنشده یا mixed content پیدا نشد؛ اعتبارسنجی کامل DOM انجام نشده است.' );
	}

	private static function mobile_overflow( $rule, $context ) {
		$html = self::rendered( $context );
		if ( is_array( $html ) ) {
			return $html;
		}
		if ( preg_match( '/<(?:table|iframe|pre)\b[^>]*(?:width\s*=\s*["\']?\d{3,}|style=["\'][^"\']*min-width\s*:\s*\d{3,}px)/i', $html, $match ) ) {
			return self::issue( 'عنصر رندرشده عرض ثابت/بزرگ دارد؛ سرریز واقعی باید در مرورگر موبایل تأیید شود.', $rule, array( array( 'text' => $match[0], 'location' => array( 'source' => 'rendered_html' ) ) ), true );
		}
		return self::insufficient( 'رندر HTML به‌تنهایی overflow را ثابت نمی‌کند؛ CSS و viewport باید در آزمون مرورگر بررسی شوند.' );
	}

	private static function placeholders( $rule, $context ) {
		$text = self::text( $context );
		$params = isset( $rule['params'] ) ? $rule['params'] : array();
		$certain = isset( $params['patterns_certain'] ) ? (array) $params['patterns_certain'] : array();
		foreach ( $certain as $pattern ) {
			if ( '' !== (string) $pattern && preg_match( '/' . preg_quote( (string) $pattern, '/' ) . '/iu', $text, $match, PREG_OFFSET_CAPTURE ) ) {
				return self::issue( 'نشانهٔ صریح جای‌نگهدار در متن پیدا شد.', $rule, array( array( 'text' => $match[0][0], 'location' => array( 'source' => 'post_content', 'byte_offset' => (int) $match[0][1] ) ) ) );
			}
		}
		$probable = isset( $params['patterns_probable'] ) ? (array) $params['patterns_probable'] : array();
		foreach ( $probable as $pattern ) {
			$pattern = (string) $pattern;
			if ( in_array( $pattern, array( '...', '…' ), true ) ) {
				continue; // Punctuation ellipses alone are too ambiguous to flag.
			}
			if ( '' !== $pattern && self::contains( $text, $pattern ) ) {
				return self::issue( 'عبارت دووجهیِ احتمالاً ناتمام پیدا شد؛ نیازمند مرور انسانی.', $rule, array( array( 'text' => $pattern, 'location' => array( 'source' => 'post_content' ) ) ), true, 0.5 );
			}
		}
		if ( ! empty( $params['bracket_placeholder_regex'] ) && preg_match( '/' . $params['bracket_placeholder_regex'] . '/u', $text, $match, PREG_OFFSET_CAPTURE ) ) {
			return self::issue( 'متن داخل کروشه شبیه جای‌نگهدار است؛ ممکن است عبارت واقعی باشد.', $rule, array( array( 'text' => $match[0][0], 'location' => array( 'source' => 'post_content', 'byte_offset' => (int) $match[0][1] ) ) ), true, 0.5 );
		}
		return self::pass( 'الگوی جای‌نگهدار پیکربندی‌شده پیدا نشد.' );
	}

	private static function empty_sections( $rule, $context ) {
		$html = isset( $context['content_html'] ) ? (string) $context['content_html'] : '';
		preg_match_all( '/<(h[1-6])\b[^>]*>(.*?)<\/h[1-6]\s*>/isu', $html, $headings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		if ( ! $headings ) {
			return self::insufficient( 'سرتیتری در post_content پیدا نشد.' );
		}
		$min = isset( $rule['params']['min_words_under_heading'] ) ? (int) $rule['params']['min_words_under_heading'] : 40;
		foreach ( $headings as $index => $heading ) {
			$start = $heading[0][1] + strlen( $heading[0][0] );
			$end = isset( $headings[ $index + 1 ] ) ? $headings[ $index + 1 ][0][1] : strlen( $html );
			$section = substr( $html, $start, max( 0, $end - $start ) );
			$has_block = (bool) preg_match( '/<(?:ul|ol|table|figure|img|blockquote)\b/i', $section );
			$words = self::count_words( wp_strip_all_tags( $section ) );
			if ( ! $has_block && ( 0 === $words || ( $index < count( $headings ) - 1 && $words < $min ) ) ) {
				return self::issue( 'یک بخش پس از سرتیتر خالی یا کوتاه‌تر از معیار داخلی است.', $rule, array( array( 'heading' => wp_strip_all_tags( $heading[2][0] ), 'word_count' => $words, 'location' => array( 'heading_index' => $index + 1 ) ) ), true );
			}
		}
		return self::pass( 'سرتیتر بدون بدنهٔ قابل‌شناسایی پیدا نشد.' );
	}

	/**
	 * Extract simple anchor records from stored HTML.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	private static function links( $html ) {
		$links = array();
		if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a\s*>/isu', (string) $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$tag = substr( $match[0], 0, strpos( $match[0], '>' ) + 1 );
				$links[] = array(
					'href'   => self::attr( $tag, 'href' ),
					'target' => self::attr( $tag, 'target' ),
					'rel'    => self::attr( $tag, 'rel' ),
					'inner'  => $match[1],
				);
			}
		}
		return $links;
	}
}
