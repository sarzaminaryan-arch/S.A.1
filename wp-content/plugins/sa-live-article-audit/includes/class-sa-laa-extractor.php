<?php
/**
 * استخراج دقیق مقاله‌های منتشرشدهٔ سایت زنده (فقط خواندنی).
 *
 * برای هر نوشتهٔ منتشرشده از نوع‌های انتخابی، یک رکورد کامل می‌سازد:
 * شناسه‌ها، عنوان/نامک/وضعیت/تاریخ‌ها/نویسنده، متن خام + هش‌ها، تیترها،
 * لینک‌ها، تصاویر داخل متن، طبقه‌بندی‌ها، متاها، تصویر شاخص و روابط
 * استان/شهرستان. هیچ کوئریِ نوشتنی اجرا نمی‌شود.
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_LAA_Extractor {

	/**
	 * پیشوندهای متایی که به‌صورت پیش‌فرض در اسنپ‌شات می‌آیند.
	 * (متاهای داخلی وردپرس با «_» شروع می‌شوند و جز _thumbnail_id کنار گذاشته می‌شوند.)
	 *
	 * @return string[]
	 */
	public static function default_meta_prefixes() {
		return array( 'sa_', 'rank_math_' );
	}

	/**
	 * کلیدهای کانونی شناسنامهٔ شهرستان (v2.22.90+) که بدون پیشوند sa_ ذخیره می‌شوند.
	 *
	 * @return string[]
	 */
	public static function default_county_meta_keys() {
		if ( function_exists( 'sa_county_profile_fields' ) ) {
			return array_keys( (array) sa_county_profile_fields() );
		}
		return array(
			'county_en',
			'province_id',
			'center_fa',
			'center_en',
			'districts_count',
			'cities_count',
			'rural_districts_count',
			'villages_count',
			'population',
			'population_year',
			'area_km2',
			'elevation_m',
			'lat',
			'lng',
			'neighbors',
			'distance_to_capital_km',
			'plate_code',
			'phone_code',
			'climate_short',
			'language_fa',
			'economy_fa',
			'best_season',
			'road_quality',
			'rail_access',
			'air_access',
			'sea_access',
			'mobile_internet',
			'fuel_stations',
			'seasons_detail',
			'local_tips',
			'extra_places',
		);
	}

	/**
	 * نوع‌های محتوای عمومی سایت برای فهرست انتخاب در پیشخوان.
	 *
	 * @return array<string,string> slug => label
	 */
	public static function public_post_types() {
		$out   = array();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $types as $slug => $obj ) {
			if ( 'attachment' === $slug ) {
				continue;
			}
			$out[ $slug ] = isset( $obj->labels->name ) ? (string) $obj->labels->name : $slug;
		}
		return $out;
	}

	/**
	 * ساخت اسنپ‌شات کامل از نوشته‌های منتشرشده.
	 *
	 * @param string[] $post_types   نوع‌های محتوا.
	 * @param bool     $all_meta     همهٔ متاها بیاید (پیش‌فرض: فقط پیشوندهای استاندارد سایت).
	 * @param int      $per_page     اندازهٔ صفحهٔ کوئری (حافظه).
	 * @return array اسنپ‌شات.
	 */
	public static function snapshot( $post_types, $all_meta = false, $per_page = 100 ) {
		$post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $post_types ) ) );
		$records    = array();
		foreach ( $post_types as $type ) {
			if ( ! post_type_exists( $type ) ) {
				continue;
			}
			$paged = 1;
			do {
				$query = new WP_Query(
					array(
						'post_type'              => $type,
						'post_status'            => 'publish',
						'posts_per_page'         => max( 10, (int) $per_page ),
						'paged'                  => $paged,
						'orderby'                => 'ID',
						'order'                  => 'ASC',
						'no_found_rows'          => false,
						'update_post_meta_cache' => true,
						'update_post_term_cache' => true,
					)
				);
				foreach ( $query->posts as $post ) {
					$records[] = self::record( $post, $all_meta );
				}
				$paged++;
			} while ( $paged <= (int) $query->max_num_pages );
		}

		global $wp_version;
		return array(
			'tool'         => 'sa-live-article-audit',
			'plugin_version' => SA_LAA_VERSION,
			'generated_at' => gmdate( 'c' ),
			'site'         => array(
				'home_url'   => home_url( '/' ),
				'site_name'  => get_bloginfo( 'name' ),
				'wp_version' => (string) $wp_version,
				'locale'     => get_locale(),
				'theme'      => get_stylesheet(),
			),
			'post_types'   => $post_types,
			'status'       => 'publish',
			'count'        => count( $records ),
			'records'      => $records,
		);
	}

	/**
	 * رکورد کامل یک نوشته.
	 *
	 * @param WP_Post $post     نوشته.
	 * @param bool    $all_meta همهٔ متاها.
	 * @return array
	 */
	public static function record( $post, $all_meta = false ) {
		$post_id  = (int) $post->ID;
		$type     = (string) $post->post_type;
		$password = '' !== (string) $post->post_password;
		$content  = $password ? '' : (string) $post->post_content;

		$record = array(
			'key'               => $type . ':' . (string) $post->post_name,
			'source'            => 'live',
			'wp_id'             => $post_id,
			'post_type'         => $type,
			'slug'              => (string) $post->post_name,
			'title'             => (string) $post->post_title,
			'status'            => (string) $post->post_status,
			'date_published'    => (string) $post->post_date,
			'date_modified'     => (string) $post->post_modified,
			'author'            => self::author( $post ),
			'permalink'         => (string) get_permalink( $post ),
			'edit_link'         => (string) get_edit_post_link( $post, 'raw' ),
			'excerpt'           => (string) $post->post_excerpt,
			'password_protected' => $password,
			'content'           => $content,
			'content_raw_hash'  => SA_LAA_Compare::hash_text( (string) $post->post_content ),
			'content_hash'      => SA_LAA_Compare::hash_text( SA_LAA_Compare::normalize_text( $content ) ),
			'content_text_hash' => SA_LAA_Compare::hash_text( SA_LAA_Compare::plain_text( $content ) ),
			'word_count'        => self::word_count( $content ),
			'headings'          => self::headings( $content ),
			'links'             => self::links( $content ),
			'content_images'    => self::content_images( $content ),
			'taxonomies'        => self::taxonomies( $post ),
			'terms'             => self::terms_map( $post ),
			'meta'              => self::meta( $post_id, $all_meta ),
			'featured_image'    => self::featured_image( $post_id ),
			'menu_order'        => (int) $post->menu_order,
			'comment_count'     => (int) $post->comment_count,
		);

		// FAQ آماده برای تطبیق (متای sa_faq قالب فرزند JSON است).
		if ( isset( $record['meta']['sa_faq'] ) && is_string( $record['meta']['sa_faq'] ) ) {
			$faq = json_decode( $record['meta']['sa_faq'], true );
			if ( is_array( $faq ) ) {
				$record['faq'] = $faq;
			}
		}

		$record['relations'] = self::relations( $record );
		return $record;
	}

	/**
	 * نویسندهٔ نوشته.
	 *
	 * @param WP_Post $post نوشته.
	 * @return array
	 */
	private static function author( $post ) {
		$user_id = (int) $post->post_author;
		$user    = $user_id ? get_userdata( $user_id ) : false;
		return array(
			'id'           => $user_id,
			'display_name' => $user ? (string) $user->display_name : '',
			'login'        => $user ? (string) $user->user_login : '',
		);
	}

	/**
	 * شمارش واژه‌های متن ساده (سازگار با فارسی: جداسازی با فاصله).
	 *
	 * @param string $content محتوا.
	 * @return int
	 */
	private static function word_count( $content ) {
		$text = SA_LAA_Compare::plain_text( $content );
		if ( '' === $text ) {
			return 0;
		}
		$parts = preg_split( '/\s+/u', $text );
		return is_array( $parts ) ? count( $parts ) : 0;
	}

	/**
	 * تیترهای h1 تا h6 داخل محتوا.
	 *
	 * @param string $content محتوا.
	 * @return array<int,array{level:int,text:string}>
	 */
	private static function headings( $content ) {
		$out = array();
		if ( preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', (string) $content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$out[] = array(
					'level' => (int) $row[1],
					'text'  => SA_LAA_Compare::normalize_text( html_entity_decode( wp_strip_all_tags( $row[2] ), ENT_QUOTES, 'UTF-8' ) ),
				);
			}
		}
		return $out;
	}

	/**
	 * لینک‌های داخل محتوا با تفکیک داخلی/خارجی.
	 *
	 * @param string $content محتوا.
	 * @return array<int,array{url:string,text:string,internal:bool}>
	 */
	private static function links( $content ) {
		$out       = array();
		$home_host = (string) parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( preg_match_all( '/<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', (string) $content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $row ) {
				$url  = html_entity_decode( (string) $row[2], ENT_QUOTES, 'UTF-8' );
				$host = (string) parse_url( $url, PHP_URL_HOST );
				$out[] = array(
					'url'      => $url,
					'text'     => SA_LAA_Compare::normalize_text( html_entity_decode( wp_strip_all_tags( $row[3] ), ENT_QUOTES, 'UTF-8' ) ),
					'internal' => ( '' === $host || $host === $home_host ),
				);
			}
		}
		return $out;
	}

	/**
	 * تصاویر داخل محتوا.
	 *
	 * @param string $content محتوا.
	 * @return array<int,array{src:string,file:string,alt:string}>
	 */
	private static function content_images( $content ) {
		$out = array();
		if ( preg_match_all( '/<img\b[^>]*>/is', (string) $content, $tags ) ) {
			foreach ( $tags[0] as $tag ) {
				$src = '';
				$alt = '';
				if ( preg_match( '/\bsrc\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ) {
					$src = html_entity_decode( (string) $m[2], ENT_QUOTES, 'UTF-8' );
				}
				if ( preg_match( '/\balt\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ) {
					$alt = html_entity_decode( (string) $m[2], ENT_QUOTES, 'UTF-8' );
				}
				$path = (string) parse_url( $src, PHP_URL_PATH );
				$out[] = array(
					'src'  => $src,
					'file' => basename( $path ),
					'alt'  => $alt,
				);
			}
		}
		return $out;
	}

	/**
	 * همهٔ طبقه‌بندی‌های نوشته با جزئیات ترم‌ها.
	 *
	 * @param WP_Post $post نوشته.
	 * @return array<string,array<int,array>>
	 */
	private static function taxonomies( $post ) {
		$out = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$rows = array();
			foreach ( $terms as $term ) {
				$rows[] = array(
					'term_id' => (int) $term->term_id,
					'slug'    => (string) $term->slug,
					'name'    => (string) $term->name,
					'parent'  => (int) $term->parent,
					'link'    => (string) get_term_link( $term ),
				);
			}
			$out[ $taxonomy ] = $rows;
		}
		return $out;
	}

	/**
	 * نقشهٔ slug => name ترم‌ها (قرارداد تطبیق).
	 *
	 * @param WP_Post $post نوشته.
	 * @return array<string,array<string,string>>
	 */
	private static function terms_map( $post ) {
		$out = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'all' ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$map = array();
			foreach ( $terms as $term ) {
				$map[ (string) $term->slug ] = (string) $term->name;
			}
			ksort( $map );
			$out[ $taxonomy ] = $map;
		}
		return $out;
	}

	/**
	 * متاهای نوشته (بدون متاهای داخلی وردپرس، جز شناسهٔ تصویر شاخص که جدا گزارش می‌شود).
	 *
	 * @param int  $post_id  شناسه.
	 * @param bool $all_meta همهٔ متاها.
	 * @return array<string,mixed>
	 */
	private static function meta( $post_id, $all_meta = false ) {
		$out         = array();
		$prefixes    = self::default_meta_prefixes();
		$county_keys = array_flip( self::default_county_meta_keys() );
		foreach ( (array) get_post_meta( $post_id ) as $key => $values ) {
			$key = (string) $key;
			if ( '_thumbnail_id' === $key ) {
				continue; // در featured_image گزارش می‌شود.
			}
			if ( ! $all_meta ) {
				if ( 0 === strpos( $key, '_' ) ) {
					continue;
				}
				$allowed = isset( $county_keys[ $key ] );
				if ( ! $allowed ) {
					foreach ( $prefixes as $prefix ) {
						if ( 0 === strpos( $key, $prefix ) ) {
							$allowed = true;
							break;
						}
					}
				}
				if ( ! $allowed ) {
					continue;
				}
			}
			$values = array_map( 'maybe_unserialize', (array) $values );
			$out[ $key ] = ( 1 === count( $values ) ) ? $values[0] : $values;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * تصویر شاخص و پیوست آن.
	 *
	 * @param int $post_id شناسه.
	 * @return array خالی اگر تصویری نیست.
	 */
	private static function featured_image( $post_id ) {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );
		if ( ! $thumb_id ) {
			return array();
		}
		$src  = wp_get_attachment_image_src( $thumb_id, 'full' );
		$meta = wp_get_attachment_metadata( $thumb_id );
		$file = ( is_array( $meta ) && ! empty( $meta['file'] ) ) ? (string) $meta['file'] : '';
		if ( '' === $file && $src ) {
			$file = (string) parse_url( (string) $src[0], PHP_URL_PATH );
		}
		$attachment = get_post( $thumb_id );
		return array(
			'attachment_id' => $thumb_id,
			'url'           => $src ? (string) $src[0] : '',
			'file'          => basename( $file ),
			'width'         => ( is_array( $meta ) && isset( $meta['width'] ) ) ? (int) $meta['width'] : ( $src ? (int) $src[1] : 0 ),
			'height'        => ( is_array( $meta ) && isset( $meta['height'] ) ) ? (int) $meta['height'] : ( $src ? (int) $src[2] : 0 ),
			'mime'          => (string) get_post_mime_type( $thumb_id ),
			'alt'           => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			'title'         => $attachment ? (string) $attachment->post_title : '',
			'caption'       => $attachment ? (string) $attachment->post_excerpt : '',
		);
	}

	/**
	 * روابط موجودیتی (استان/شهرستان) به شکل نامک، برای تطبیق با خط مبنا.
	 *
	 * منبع اصلی قالب فرزند: sa_province_id و sa_city_id؛ جایگزین: province_tax.
	 *
	 * @param array $record رکورد در حال ساخت (meta و terms آماده‌اند).
	 * @return array<string,string>
	 */
	private static function relations( $record ) {
		$out  = array();
		$meta = isset( $record['meta'] ) && is_array( $record['meta'] ) ? $record['meta'] : array();
		$prov_meta_id = ! empty( $meta['province_id'] ) ? (int) $meta['province_id'] : ( ! empty( $meta['sa_province_id'] ) ? (int) $meta['sa_province_id'] : 0 );
		if ( $prov_meta_id ) {
			$target = get_post( $prov_meta_id );
			if ( $target ) {
				$out['province']       = (string) $target->post_name;
				$out['province_id']    = (int) $target->ID;
				$out['province_title'] = (string) $target->post_title;
			}
		}
		if ( ! empty( $meta['sa_city_id'] ) ) {
			$target = get_post( (int) $meta['sa_city_id'] );
			if ( $target ) {
				$out['city']       = (string) $target->post_name;
				$out['city_id']    = (int) $target->ID;
				$out['city_title'] = (string) $target->post_title;
			}
		}
		if ( empty( $out['province'] ) && ! empty( $record['terms']['province_tax'] ) && is_array( $record['terms']['province_tax'] ) ) {
			$slugs = array_keys( $record['terms']['province_tax'] );
			if ( $slugs ) {
				$out['province'] = (string) $slugs[0];
			}
		}
		return $out;
	}
}
