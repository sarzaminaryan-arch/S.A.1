<?php
/**
 * موتور تطبیق خط مبنای مخزن با مقالات زنده.
 *
 * این کلاس هیچ تابع وردپرسی صدا نمی‌زند؛ ورودی و خروجی‌اش آرایهٔ ساده است تا
 * هم در پیشخوان، هم در WP-CLI و هم در تست مستقل (`tests/test-compare.php`)
 * قابل استفاده باشد. هیچ داده‌ای را تغییر نمی‌دهد.
 *
 * قرارداد رکورد (هر دو طرف):
 *   key, post_type, slug, title, excerpt, content,
 *   meta (map), faq (list of {q,a}), terms (taxonomy => slug=>name | list),
 *   cover ({file,alt,title} | خالی), relations (map), remove_featured_image
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_LAA_Compare {

	/**
	 * نرمال‌سازی متن برای مقایسهٔ غیرحساس به فاصله و خط نو.
	 *
	 * @param mixed $text متن.
	 * @return string
	 */
	public static function normalize_text( $text ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$text = preg_replace( '/[ \t]+/', ' ', $text );
		$text = preg_replace( '/[ \t]+\n/', "\n", $text );
		$text = preg_replace( '/\n{3,}/', "\n\n", $text );
		return trim( (string) $text );
	}

	/**
	 * متن سادهٔ محتوا (بدون HTML) برای تشخیص «فقط قالب‌بندی عوض شده».
	 *
	 * @param mixed $html محتوا.
	 * @return string
	 */
	public static function plain_text( $html ) {
		$text = strip_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return self::normalize_text( $text );
	}

	/**
	 * هش SHA-256 متن.
	 *
	 * @param mixed $text متن.
	 * @return string
	 */
	public static function hash_text( $text ) {
		return hash( 'sha256', (string) $text );
	}

	/**
	 * مقدار متا/داده را برای مقایسهٔ پایدار یکدست می‌کند:
	 * کلیدهای انجمنی مرتب، اسکالرها رشته، بولین «1/0».
	 *
	 * @param mixed $value مقدار.
	 * @return mixed
	 */
	public static function canonical( $value ) {
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			$out     = array();
			foreach ( $value as $k => $v ) {
				$out[ $k ] = self::canonical( $v );
			}
			if ( ! $is_list ) {
				ksort( $out );
			}
			return $out;
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( null === $value ) {
			return '';
		}
		if ( is_object( $value ) ) {
			return self::canonical( get_object_vars( $value ) );
		}
		return (string) $value;
	}

	/**
	 * نمایش کوتاه مقدار برای گزارش (بدون نشت متن کامل مقاله).
	 *
	 * @param mixed $value مقدار.
	 * @return string
	 */
	private static function preview( $value ) {
		$value = self::canonical( $value );
		if ( is_array( $value ) ) {
			$value = sa_laa_json_encode( $value );
		}
		$value = self::normalize_text( (string) $value );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $value, 'UTF-8' ) > 180 ) {
			return mb_substr( $value, 0, 180, 'UTF-8' ) . '…';
		}
		if ( strlen( $value ) > 360 ) {
			return substr( $value, 0, 360 ) . '…';
		}
		return $value;
	}

	/**
	 * ترم‌ها را از هر دو شکل (slug=>name یا فهرست slug/name) به slug=>name می‌برد.
	 *
	 * @param mixed $terms ترم‌های یک طبقه‌بندی.
	 * @return array<string,string>
	 */
	private static function normalize_terms( $terms ) {
		$out = array();
		if ( ! is_array( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $k => $v ) {
			if ( is_array( $v ) ) {
				$slug = isset( $v['slug'] ) ? (string) $v['slug'] : '';
				$name = isset( $v['name'] ) ? (string) $v['name'] : $slug;
				if ( '' !== $slug ) {
					$out[ $slug ] = $name;
				}
			} elseif ( is_string( $k ) && '' !== $k ) {
				$out[ $k ] = (string) $v;
			} elseif ( is_string( $v ) && '' !== $v ) {
				$out[ $v ] = $v;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * کلید یکتای تطبیق: نوع محتوا + نامک.
	 *
	 * @param array $record رکورد.
	 * @return string
	 */
	public static function record_key( $record ) {
		$type = isset( $record['post_type'] ) ? (string) $record['post_type'] : '';
		$slug = isset( $record['slug'] ) ? (string) $record['slug'] : '';
		return $type . ':' . $slug;
	}

	/**
	 * تطبیق کامل دو مجموعه رکورد.
	 *
	 * @param array $baseline رکوردهای مخزن.
	 * @param array $live     رکوردهای زنده.
	 * @return array{summary:array,items:array,live_only:array,baseline_only:array,duplicates:array}
	 */
	public static function compare( $baseline, $live ) {
		$base_index = array();
		$live_index = array();
		$dup_base   = array();
		$dup_live   = array();

		foreach ( (array) $baseline as $record ) {
			if ( ! is_array( $record ) || empty( $record['slug'] ) ) {
				continue;
			}
			$key = self::record_key( $record );
			if ( isset( $base_index[ $key ] ) ) {
				$dup_base[] = $key;
				continue;
			}
			$base_index[ $key ] = $record;
		}
		foreach ( (array) $live as $record ) {
			if ( ! is_array( $record ) || empty( $record['slug'] ) ) {
				continue;
			}
			$key = self::record_key( $record );
			if ( isset( $live_index[ $key ] ) ) {
				$dup_live[] = $key;
				continue;
			}
			$live_index[ $key ] = $record;
		}

		$items             = array();
		$field_diff        = array();
		$matched_base_keys = array();
		$matched_live_keys = array();
		foreach ( $base_index as $key => $base ) {
			list( $live_key, $alias_note ) = self::resolve_live_match( $base, $base_index, $live_index, $matched_live_keys );
			if ( '' === $live_key ) {
				continue;
			}
			$matched_base_keys[ $key ]      = true;
			$matched_live_keys[ $live_key ] = true;
			$item                           = self::compare_pair( $base, $live_index[ $live_key ] );
			if ( is_array( $alias_note ) ) {
				array_unshift( $item['differences'], $alias_note );
			}
			$item['key']   = $live_key;
			$items[ $key ] = $item;
			foreach ( $item['differences'] as $diff ) {
				if ( 'info' === $diff['severity'] ) {
					continue; // یادداشت‌های اطلاعاتی «اختلاف» شمرده نمی‌شوند.
				}
				$field                = (string) $diff['field'];
				$field_diff[ $field ] = isset( $field_diff[ $field ] ) ? $field_diff[ $field ] + 1 : 1;
			}
		}

		$live_only = array();
		foreach ( $live_index as $key => $record ) {
			if ( ! isset( $matched_live_keys[ $key ] ) ) {
				$live_only[] = self::summarize_record( $record, 'live_only' );
			}
		}
		$baseline_only = array();
		foreach ( $base_index as $key => $record ) {
			if ( ! isset( $matched_base_keys[ $key ] ) ) {
				$baseline_only[] = self::summarize_record( $record, 'baseline_only' );
			}
		}

		$identical = 0;
		$different = 0;
		foreach ( $items as $item ) {
			if ( 'identical' === $item['status'] ) {
				$identical++;
			} else {
				$different++;
			}
		}
		ksort( $items );
		ksort( $field_diff );

		return array(
			'summary'       => array(
				'baseline_count'     => count( $base_index ),
				'live_count'         => count( $live_index ),
				'matched'            => count( $items ),
				'identical'          => $identical,
				'different'          => $different,
				'live_only'          => count( $live_only ),
				'baseline_only'      => count( $baseline_only ),
				'duplicate_baseline' => count( $dup_base ),
				'duplicate_live'     => count( $dup_live ),
				'field_differences'  => $field_diff,
			),
			'items'         => array_values( $items ),
			'live_only'     => $live_only,
			'baseline_only' => $baseline_only,
			'duplicates'    => array(
				'baseline' => array_values( array_unique( $dup_base ) ),
				'live'     => array_values( array_unique( $dup_live ) ),
			),
		);
	}

	/**
	 * یافتن رکورد متناظر در سایت زنده (تطبیق دقیق + مهاجرت post<->guide + نامک شهرستان مرکز استان).
	 *
	 * @param array $base              رکورد مخزن.
	 * @param array $base_index        نمایهٔ مخزن.
	 * @param array $live_index        نمایهٔ زنده.
	 * @param array $matched_live_keys کلیدهای مصرف‌شدهٔ زنده.
	 * @return array{0:string,1:array|null}
	 */
	private static function resolve_live_match( $base, $base_index, $live_index, $matched_live_keys ) {
		$key = self::record_key( $base );
		if ( isset( $live_index[ $key ] ) && ! isset( $matched_live_keys[ $key ] ) ) {
			return array( $key, null );
		}

		$type = isset( $base['post_type'] ) ? (string) $base['post_type'] : '';
		$slug = isset( $base['slug'] ) ? (string) $base['slug'] : '';
		if ( '' === $slug ) {
			return array( '', null );
		}

		if ( 'post' === $type || 'guide' === $type ) {
			$alt_type = ( 'post' === $type ) ? 'guide' : 'post';
			$alt_key  = $alt_type . ':' . $slug;
			if ( isset( $live_index[ $alt_key ] ) && ! isset( $matched_live_keys[ $alt_key ] ) && ! isset( $base_index[ $alt_key ] ) ) {
				return array(
					$alt_key,
					array(
						'field'    => 'post_type',
						'severity' => 'info',
						'baseline' => $type,
						'live'     => $alt_type,
						'note'     => 'نوشته به نوع محتوای مستقل راهنمای سفر (guide) منتقل شده است.',
					),
				);
			}
		}

		if ( 'city' === $type ) {
			$alt_slug = ( '-city' === substr( $slug, -5 ) ) ? substr( $slug, 0, -5 ) : $slug . '-city';
			if ( '' !== $alt_slug && ( ! function_exists( 'sa_redirect_is_old_slug' ) || ! sa_redirect_is_old_slug( $alt_slug, 'city' ) ) ) {
				$alt_key = 'city:' . $alt_slug;
				if ( isset( $live_index[ $alt_key ] ) && ! isset( $matched_live_keys[ $alt_key ] ) && ! isset( $base_index[ $alt_key ] ) ) {
					return array(
						$alt_key,
						array(
							'field'    => 'slug',
							'severity' => 'info',
							'baseline' => $slug,
							'live'     => $alt_slug,
							'note'     => 'تطبیق نامک شهرستان مرکز استان (با/بدون پسوند -city).',
						),
					);
				}
			}
		}

		return array( '', null );
	}

	/**
	 * برابری دو مقدار متا (با مقایسهٔ عددی مختصات جغرافیایی).
	 *
	 * @param string $meta_key کلید متا.
	 * @param mixed  $a        مقدار خط مبنا.
	 * @param mixed  $b        مقدار زنده.
	 * @return bool
	 */
	private static function meta_values_equal( $meta_key, $a, $b ) {
		$coord_keys = array( 'sa_latitude', 'sa_longitude', 'sa_city_latitude', 'sa_city_longitude', 'lat', 'lng' );
		if ( in_array( (string) $meta_key, $coord_keys, true ) && is_scalar( $a ) && is_scalar( $b ) ) {
			$sa = trim( (string) $a );
			$sb = trim( (string) $b );
			if ( is_numeric( $sa ) && is_numeric( $sb ) ) {
				return abs( (float) $sa - (float) $sb ) < 1e-6;
			}
		}
		return self::canonical( $a ) === self::canonical( $b );
	}

	/**
	 * خلاصهٔ یک رکورد برای فهرست‌های «فقط یک طرف».
	 *
	 * @param array  $record رکورد.
	 * @param string $side   طرف.
	 * @return array
	 */
	private static function summarize_record( $record, $side ) {
		return array(
			'key'       => self::record_key( $record ),
			'side'      => $side,
			'post_type' => isset( $record['post_type'] ) ? (string) $record['post_type'] : '',
			'slug'      => isset( $record['slug'] ) ? (string) $record['slug'] : '',
			'title'     => isset( $record['title'] ) ? (string) $record['title'] : '',
			'wp_id'     => isset( $record['wp_id'] ) ? (int) $record['wp_id'] : 0,
			'permalink' => isset( $record['permalink'] ) ? (string) $record['permalink'] : '',
			'source'    => isset( $record['source'] ) ? (string) $record['source'] : '',
		);
	}

	/**
	 * مقایسهٔ فیلدبه‌فیلد یک جفت رکورد هم‌نامک.
	 *
	 * @param array $base رکورد مخزن.
	 * @param array $live رکورد زنده.
	 * @return array
	 */
	private static function compare_pair( $base, $live ) {
		$diffs = array();
		$add   = function ( $field, $severity, $base_value, $live_value, $note = '' ) use ( &$diffs ) {
			$diffs[] = array(
				'field'    => (string) $field,
				'severity' => (string) $severity,
				'baseline' => self::preview( $base_value ),
				'live'     => self::preview( $live_value ),
				'note'     => (string) $note,
			);
		};

		// عنوان و چکیده.
		if ( isset( $base['title'] ) && self::normalize_text( $base['title'] ) !== self::normalize_text( isset( $live['title'] ) ? $live['title'] : '' ) ) {
			$add( 'title', 'high', $base['title'], isset( $live['title'] ) ? $live['title'] : '' );
		}
		if ( isset( $base['excerpt'] ) && '' !== self::normalize_text( $base['excerpt'] )
			&& self::normalize_text( $base['excerpt'] ) !== self::normalize_text( isset( $live['excerpt'] ) ? $live['excerpt'] : '' ) ) {
			$add( 'excerpt', 'medium', $base['excerpt'], isset( $live['excerpt'] ) ? $live['excerpt'] : '' );
		}

		// محتوا: اول هش نرمال HTML، بعد هش متن ساده.
		if ( isset( $base['content'] ) && '' !== (string) $base['content'] ) {
			$base_content = (string) $base['content'];
			$live_content = isset( $live['content'] ) ? (string) $live['content'] : '';
			if ( false !== strpos( $base_content, '{{' ) ) {
				$add( 'content', 'info', 'دارای جای‌نگهدار ایمپورت ({{...}})', '—', 'متن خط مبنا قالبِ پیش از ایمپورت است؛ مقایسهٔ دقیق محتوا برای این رکورد انجام نشد.' );
			} elseif ( self::hash_text( self::normalize_text( $base_content ) ) !== self::hash_text( self::normalize_text( $live_content ) ) ) {
				if ( self::hash_text( self::plain_text( $base_content ) ) === self::hash_text( self::plain_text( $live_content ) ) ) {
					$add( 'content', 'info', 'متن یکسان', 'فقط HTML/قالب‌بندی متفاوت', 'متن ساده یکسان است؛ تفاوت در نشانه‌گذاری HTML است.' );
				} else {
					$add( 'content', 'high', self::hash_text( self::normalize_text( $base_content ) ), self::hash_text( self::normalize_text( $live_content ) ), 'هش نرمال محتوای مخزن و زنده فرق دارد.' );
				}
			}
		}

		// متاها: فقط کلیدهایی که خط مبنا دارد (کلید خالی خط مبنا = انتظار حذف/نبودن نیست).
		$base_meta = isset( $base['meta'] ) && is_array( $base['meta'] ) ? $base['meta'] : array();
		$live_meta = isset( $live['meta'] ) && is_array( $live['meta'] ) ? $live['meta'] : array();
		foreach ( $base_meta as $meta_key => $base_value ) {
			if ( '' === self::preview( $base_value ) ) {
				continue;
			}
			if ( ! array_key_exists( $meta_key, $live_meta ) ) {
				$add( 'meta:' . $meta_key, 'medium', $base_value, '—', 'این متا در نوشتهٔ زنده نیست.' );
			} elseif ( ! self::meta_values_equal( $meta_key, $base_value, $live_meta[ $meta_key ] ) ) {
				$add( 'meta:' . $meta_key, 'medium', $base_value, $live_meta[ $meta_key ] );
			}
		}

		// FAQ: خط مبنا در برابر متای sa_faq زنده (یا فیلد faq زنده، اگر استخراج‌گر داده باشد).
		$base_faq = isset( $base['faq'] ) && is_array( $base['faq'] ) ? $base['faq'] : array();
		if ( $base_faq ) {
			$live_faq = array();
			if ( isset( $live['faq'] ) && is_array( $live['faq'] ) ) {
				$live_faq = $live['faq'];
			} elseif ( isset( $live_meta['sa_faq'] ) ) {
				$decoded = is_string( $live_meta['sa_faq'] ) ? json_decode( $live_meta['sa_faq'], true ) : $live_meta['sa_faq'];
				$live_faq = is_array( $decoded ) ? $decoded : array();
			}
			if ( self::canonical( $base_faq ) !== self::canonical( $live_faq ) ) {
				$add( 'faq', 'medium', $base_faq, $live_faq, 'پرسش‌وپاسخ خط مبنا با sa_faq زنده یکی نیست.' );
			}
		}

		// طبقه‌بندی‌ها: مجموعهٔ نامک هر طبقه‌بندی.
		$base_terms = isset( $base['terms'] ) && is_array( $base['terms'] ) ? $base['terms'] : array();
		$live_terms = isset( $live['terms'] ) && is_array( $live['terms'] ) ? $live['terms'] : array();
		foreach ( $base_terms as $taxonomy => $terms ) {
			$base_slugs = array_keys( self::normalize_terms( $terms ) );
			$live_slugs = array_keys( self::normalize_terms( isset( $live_terms[ $taxonomy ] ) ? $live_terms[ $taxonomy ] : array() ) );
			sort( $base_slugs );
			sort( $live_slugs );
			if ( $base_slugs !== $live_slugs ) {
				$add( 'terms:' . $taxonomy, 'medium', implode( ',', $base_slugs ), implode( ',', $live_slugs ) );
			}
		}

		// تصویر شاخص: نام فایل و متن جایگزین.
		$base_cover = isset( $base['cover'] ) && is_array( $base['cover'] ) ? $base['cover'] : array();
		$live_cover = isset( $live['featured_image'] ) && is_array( $live['featured_image'] ) ? $live['featured_image'] : array();
		if ( ! empty( $base['remove_featured_image'] ) ) {
			if ( ! empty( $live_cover ) ) {
				$add( 'featured_image', 'info', 'حذف تصویر شاخص', isset( $live_cover['url'] ) ? $live_cover['url'] : '', 'خط مبنا برای این نوشته حذف تصویر شاخص خواسته ولی زنده تصویر دارد.' );
			}
		} elseif ( ! empty( $base_cover['file'] ) ) {
			$base_file = basename( (string) $base_cover['file'] );
			$live_file = '';
			if ( ! empty( $live_cover['url'] ) ) {
				$path    = parse_url( (string) $live_cover['url'], PHP_URL_PATH );
				$live_file = basename( (string) $path );
			}
			if ( $base_file !== $live_file ) {
				$add( 'featured_image', 'medium', $base_file, $live_file, 'نام فایل تصویر شاخص فرق دارد.' );
			}
			if ( ! empty( $base_cover['alt'] ) && isset( $live_cover['alt'] )
				&& self::normalize_text( $base_cover['alt'] ) !== self::normalize_text( $live_cover['alt'] ) ) {
				$add( 'featured_image_alt', 'info', $base_cover['alt'], $live_cover['alt'] );
			}
		}

		// گالری خط مبنا در برابر تصاویر داخل متن زنده (اطلاعاتی؛ رندر ایمپورت ممکن است نام فایل رسانه را عوض کند).
		$base_gallery = isset( $base['gallery_files'] ) && is_array( $base['gallery_files'] ) ? $base['gallery_files'] : array();
		if ( $base_gallery ) {
			$live_files = array();
			if ( isset( $live['content_images'] ) && is_array( $live['content_images'] ) ) {
				foreach ( $live['content_images'] as $img ) {
					if ( is_array( $img ) && ! empty( $img['file'] ) ) {
						$live_files[] = basename( (string) $img['file'] );
					} elseif ( is_string( $img ) && '' !== $img ) {
						$live_files[] = basename( $img );
					}
				}
			}
			$missing = array();
			foreach ( $base_gallery as $file ) {
				$file = basename( (string) $file );
				if ( '' !== $file && ! in_array( $file, $live_files, true ) ) {
					$missing[] = $file;
				}
			}
			if ( $missing ) {
				$add( 'gallery', 'info', implode( ',', $missing ), implode( ',', $live_files ), 'این فایل‌های گالریِ خط مبنا عیناً در تصاویر متن زنده پیدا نشدند (ممکن است موقع ایمپورت به پیوست رسانه‌ای با نام دیگری تبدیل شده باشند).' );
			}
		}

		// روابط (استان/شهرستان و مانند آن).
		$base_rel = isset( $base['relations'] ) && is_array( $base['relations'] ) ? $base['relations'] : array();
		$live_rel = isset( $live['relations'] ) && is_array( $live['relations'] ) ? $live['relations'] : array();
		foreach ( $base_rel as $rel_key => $base_value ) {
			if ( '' === (string) $base_value ) {
				continue;
			}
			$live_value = isset( $live_rel[ $rel_key ] ) ? $live_rel[ $rel_key ] : '';
			if ( self::canonical( $base_value ) !== self::canonical( $live_value ) ) {
				$add( 'relation:' . $rel_key, 'medium', $base_value, $live_value );
			}
		}

		$has_real_diff = false;
		foreach ( $diffs as $diff ) {
			if ( 'info' !== $diff['severity'] ) {
				$has_real_diff = true;
				break;
			}
		}

		return array(
			'post_type'   => isset( $live['post_type'] ) ? (string) $live['post_type'] : ( isset( $base['post_type'] ) ? (string) $base['post_type'] : '' ),
			'slug'        => isset( $live['slug'] ) ? (string) $live['slug'] : ( isset( $base['slug'] ) ? (string) $base['slug'] : '' ),
			'title'       => isset( $live['title'] ) ? (string) $live['title'] : '',
			'wp_id'       => isset( $live['wp_id'] ) ? (int) $live['wp_id'] : 0,
			'permalink'   => isset( $live['permalink'] ) ? (string) $live['permalink'] : '',
			'edit_link'   => isset( $live['edit_link'] ) ? (string) $live['edit_link'] : '',
			'status'      => $has_real_diff ? 'different' : 'identical',
			'differences' => $diffs,
		);
	}
}

/**
 * جایگزین wp_json_encode برای محیط تست مستقل.
 *
 * @param mixed $value مقدار.
 * @return string
 */
function sa_laa_json_encode( $value ) {
	if ( function_exists( 'wp_json_encode' ) ) {
		$out = wp_json_encode( $value );
		return is_string( $out ) ? $out : '';
	}
	$out = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	return is_string( $out ) ? $out : '';
}
