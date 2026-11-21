<?php
/**
 * خط مبنای مخزن: «مقاله‌ها همان‌طور که در مخزن S.A.1 تعریف شده‌اند».
 *
 * سه منبع، به ترتیب پیشنهاد:
 * ۱) تابع قالب فرزند `sa_ready_articles_data()` — وقتی قالب «سرزمین آریان»
 *    فعال است، دقیقاً همان داده‌ای است که ایمپورتر «مقالات آماده» می‌خواند.
 * ۲) فایل همراه افزونه `data/baseline.json` — اسنپ‌شات مخزن در زمان ساخت ZIP
 *    (در CI از `data/ready-articles.php` و `data/ready-guides.php` ساخته می‌شود).
 * ۳) فایل JSON بارگذاری‌شده توسط مدیر (همان قالب اسنپ‌شات/خط مبنا).
 *
 * این کلاس هیچ تغییری در سایت نمی‌دهد.
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_LAA_Baseline {

	/**
	 * مسیر فایل خط مبنای همراه افزونه.
	 *
	 * @return string
	 */
	public static function bundled_path() {
		return SA_LAA_DIR . 'data/baseline.json';
	}

	/**
	 * آیا قالب فرزند دادهٔ «مقالات آماده» را در دسترس گذاشته است؟
	 *
	 * @return bool
	 */
	public static function theme_available() {
		return function_exists( 'sa_ready_articles_data' );
	}

	/**
	 * خواندن خط مبنا از یک منبع.
	 *
	 * @param string     $source   'theme' | 'bundled' | 'upload'
	 * @param array|null $uploaded دادهٔ دیکودشدهٔ فایل بارگذاری‌شده (برای 'upload').
	 * @return array{records:array,info:array}|WP_Error
	 */
	public static function load( $source, $uploaded = null ) {
		if ( 'theme' === $source ) {
			if ( ! self::theme_available() ) {
				return new WP_Error( 'sa_laa_no_theme', 'قالب فرزند «سرزمین آریان» فعال نیست یا دادهٔ مقالات آماده در دسترس نیست.' );
			}
			$data = sa_ready_articles_data( 'all' );
			return array(
				'records' => self::records_from_theme_data( is_array( $data ) ? $data : array() ),
				'info'    => array(
					'source' => 'theme',
					'label'  => 'قالب فرزند — sa_ready_articles_data()',
				),
			);
		}

		if ( 'upload' === $source ) {
			if ( ! is_array( $uploaded ) ) {
				return new WP_Error( 'sa_laa_no_upload', 'فایل خط مبنا خوانده نشد؛ یک فایل JSON معتبر انتخاب کنید.' );
			}
			return array(
				'records' => self::records_from_snapshot( $uploaded ),
				'info'    => array(
					'source' => 'upload',
					'label'  => 'فایل بارگذاری‌شده',
				),
			);
		}

		// پیش‌فرض: فایل همراه افزونه.
		$path = self::bundled_path();
		if ( ! file_exists( $path ) ) {
			if ( self::theme_available() ) {
				return self::load( 'theme' );
			}
			return new WP_Error( 'sa_laa_no_baseline', 'فایل data/baseline.json همراه افزونه نیست و قالب فرزند هم دادهٔ مقالات آماده ندارد. یک فایل خط مبنا بارگذاری کنید.' );
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- خواندن فایل محلی خود افزونه.
		$decoded = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'sa_laa_bad_baseline', 'فایل data/baseline.json معتبر نیست (JSON خراب).' );
		}
		$info = array(
			'source' => 'bundled',
			'label'  => 'اسنپ‌شات مخزن همراه افزونه (data/baseline.json)',
		);
		if ( ! empty( $decoded['generated_at'] ) ) {
			$info['generated_at'] = (string) $decoded['generated_at'];
		}
		if ( ! empty( $decoded['repository'] ) && is_array( $decoded['repository'] ) ) {
			$info['repository'] = $decoded['repository'];
		}
		return array(
			'records' => self::records_from_snapshot( $decoded ),
			'info'    => $info,
		);
	}

	/**
	 * رکوردهای یک اسنپ‌شات/خط مبنا (کلید records یا فهرست مستقیم) را به قرارداد تطبیق می‌برد.
	 *
	 * @param array $data دادهٔ دیکودشده.
	 * @return array
	 */
	public static function records_from_snapshot( $data ) {
		$records = array();
		if ( isset( $data['records'] ) && is_array( $data['records'] ) ) {
			$records = $data['records'];
		} elseif ( isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$records = $data['items'];
		} elseif ( array_values( $data ) === $data ) {
			$records = $data;
		}
		$out = array();
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || empty( $record['slug'] ) ) {
				continue;
			}
			$record['post_type'] = isset( $record['post_type'] ) ? (string) $record['post_type'] : ( isset( $record['type'] ) ? (string) $record['type'] : 'post' );
			$record['source']    = isset( $record['source'] ) ? (string) $record['source'] : 'repository';
			$out[]               = $record;
		}
		return $out;
	}

	/**
	 * رکوردهای خام قالب (`sa_ready_articles_data()`) را به قرارداد تطبیق می‌برد.
	 *
	 * نگاشت ایمپورتر (inc/ready-articles.php) رعایت می‌شود:
	 * - type/slug/title/excerpt/content همان فیلدهای نوشته می‌شوند؛
	 * - meta عیناً در متاها نوشته می‌شود و faq جدا در sa_faq می‌رود؛
	 * - برای راهنماها (type=post) ‏category→category و tags→post_tag می‌شود؛
	 * - province/city نامکِ موجودیت‌اند و در زنده از sa_province_id/sa_city_id resolve می‌شوند.
	 *
	 * @param array $data خروجی sa_ready_articles_data().
	 * @return array
	 */
	public static function records_from_theme_data( $data ) {
		$out = array();
		foreach ( (array) $data as $article ) {
			if ( ! is_array( $article ) || empty( $article['slug'] ) ) {
				continue;
			}
			$type  = isset( $article['type'] ) ? (string) $article['type'] : 'attraction';
			$terms = isset( $article['terms'] ) && is_array( $article['terms'] ) ? $article['terms'] : array();
			if ( in_array( $type, array( 'post', 'guide' ), true ) ) {
				if ( ! empty( $article['category'] ) && is_array( $article['category'] ) ) {
					$terms['category'] = $article['category'];
				}
				if ( ! empty( $article['tags'] ) && is_array( $article['tags'] ) ) {
					$terms['post_tag'] = $article['tags'];
				}
			} elseif ( ! empty( $article['province'] ) ) {
				$terms['province_tax'] = array( (string) $article['province'] => (string) ( isset( $article['province_name'] ) ? $article['province_name'] : $article['province'] ) );
			}

			$gallery_files = array();
			if ( ! empty( $article['gallery'] ) && is_array( $article['gallery'] ) ) {
				foreach ( $article['gallery'] as $item ) {
					if ( is_array( $item ) && ! empty( $item['file'] ) ) {
						$gallery_files[] = basename( (string) $item['file'] );
					}
				}
			}

			$relations = array();
			if ( ! empty( $article['province'] ) ) {
				$relations['province'] = (string) $article['province'];
			}
			if ( ! empty( $article['city'] ) ) {
				$relations['city'] = (string) $article['city'];
			}

			$out[] = array(
				'post_type'             => $type,
				'slug'                  => (string) $article['slug'],
				'title'                 => isset( $article['title'] ) ? (string) $article['title'] : '',
				'excerpt'               => isset( $article['excerpt'] ) ? (string) $article['excerpt'] : '',
				'content'               => isset( $article['content'] ) ? (string) $article['content'] : '',
				'meta'                  => isset( $article['meta'] ) && is_array( $article['meta'] ) ? $article['meta'] : array(),
				'faq'                   => isset( $article['faq'] ) && is_array( $article['faq'] ) ? $article['faq'] : array(),
				'terms'                 => $terms,
				'cover'                 => isset( $article['cover'] ) && is_array( $article['cover'] ) ? $article['cover'] : array(),
				'gallery_files'         => $gallery_files,
				'relations'             => $relations,
				'remove_featured_image' => ! empty( $article['remove_featured_image'] ),
				'source'                => 'repository-theme',
			);
		}
		return $out;
	}
}
