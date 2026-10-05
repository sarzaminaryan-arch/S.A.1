<?php
/**
 * Plugin Name: Sarzamin Aryan — Bisheh Waterfall Featured View Importer
 * Description: درون‌ریز تک‌مقاله «نمای برتر آبشار بیشه دورود» برای قالب سرزمین آریان؛ پیش‌نویس مقاله، متاهای شناسنامه، سئو، FAQ و تصویر نمادین کارت را می‌سازد/به‌روزرسانی می‌کند.
 * Version: 1.0.0
 * Author: Sarzamin Aryan
 * License: GPLv2 or later
 * Text Domain: sa-bisheh-featured-view-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_Bisheh_Featured_View_Importer {
	const VERSION     = '1.0.0';
	const ACTION      = 'sa_bisheh_featured_view_import';
	const NONCE       = 'sa_bisheh_featured_view_import_nonce';
	const POST_SLUG   = 'bisheh-waterfall-dorud';
	const IMAGE_META  = '_sa_bisheh_identity_image';
	const IMAGE_FILE  = 'bisheh-waterfall-dorud-identity-1200x600.webp';
	const OPTION_LAST = 'sa_bisheh_importer_last_post_id';

	public static function init() {
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_run_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	public static function plugin_action_links( $links ) {
		$url = wp_nonce_url(
			add_query_arg( self::ACTION, '1', admin_url( 'plugins.php' ) ),
			self::ACTION,
			self::NONCE
		);
		array_unshift( $links, '<a href="' . esc_url( $url ) . '"><strong>درون‌ریزی مقاله آبشار بیشه</strong></a>' );
		return $links;
	}

	public static function maybe_run_import() {
		if ( ! is_admin() || ! isset( $_GET[ self::ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'برای اجرای این درون‌ریز دسترسی کافی ندارید.', 'sa-bisheh-featured-view-importer' ) );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		$result = self::import();
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'sa-bisheh-import' => 'error',
						'sa-bisheh-msg'    => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'plugins.php' )
				)
			);
			exit;
		}

		update_option( self::OPTION_LAST, absint( $result ), false );
		wp_safe_redirect( add_query_arg( 'sa-bisheh-import', 'success', get_edit_post_link( $result, 'raw' ) ) );
		exit;
	}

	public static function admin_notices() {
		if ( ! isset( $_GET['sa-bisheh-import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 'success' === $_GET['sa-bisheh-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>مقاله «آبشار بیشه» با موفقیت به‌صورت پیش‌نویس ساخته/به‌روزرسانی شد. تصاویر آلبومی در متن با نام فایل جست‌وجو می‌شوند؛ اگر در رسانه موجود باشند داخل مقاله می‌آیند.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'error' === $_GET['sa-bisheh-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = isset( $_GET['sa-bisheh-msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sa-bisheh-msg'] ) ) : 'خطای نامشخص';
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function import() {
		if ( ! post_type_exists( 'attraction' ) ) {
			return new WP_Error( 'missing_attraction_cpt', 'نوع محتوای «نمای برتر/attraction» پیدا نشد. ابتدا قالب فرزند سرزمین آریان را فعال کنید.' );
		}

		$province_id = self::find_post_id( 'province', 'lorestan', 'لرستان' );
		$city_id     = self::find_post_id( 'city', 'dorud', 'دورود' );
		if ( ! $province_id || ! $city_id ) {
			return new WP_Error( 'missing_location', 'نوشته استان لرستان یا شهرستان دورود پیدا نشد. ابتدا داده‌های پایه استان/شهرستان را در قالب بسازید.' );
		}

		$gallery_images = self::find_gallery_images();
		$post_id        = self::find_post_id( 'attraction', self::POST_SLUG, 'آبشار بیشه' );
		$postarr        = array(
			'post_type'    => 'attraction',
			'post_status'  => 'draft',
			'post_name'    => self::POST_SLUG,
			'post_title'   => 'آبشار بیشه',
			'post_excerpt' => 'آبشار بیشه در نزدیکی دورود، کنار روستای بیشه و ایستگاه راه‌آهن، یکی از نماهای شاخص لرستان است؛ ترکیبی از آبشار چندشاخه، جنگل‌های بلوط، چشمه‌های بالادست و مسیر ریلی زاگرس.',
			'post_content' => self::article_content( $gallery_images ),
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$ok            = wp_update_post( $postarr, true );
		} else {
			$ok = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$post_id = absint( $ok );

		self::update_meta( $post_id, $province_id, $city_id );
		self::assign_terms( $post_id );
		self::attach_identity_image( $post_id );
		self::update_gallery_image_meta( $gallery_images );

		if ( function_exists( 'sa_sync_relations' ) ) {
			sa_sync_relations( $post_id, 'attraction' );
		}
		if ( function_exists( 'sa_flush_relation_cache' ) ) {
			sa_flush_relation_cache( $post_id, 'attraction' );
		}

		return $post_id;
	}

	private static function find_post_id( $post_type, $slug, $title = '' ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'name'           => $slug,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $posts ) {
			return absint( $posts[0] );
		}
		if ( '' !== $title ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'title'          => $title,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $posts ) {
				return absint( $posts[0] );
			}
		}
		return 0;
	}

	private static function update_meta( $post_id, $province_id, $city_id ) {
		$meta = array(
			'sa_province_id'       => $province_id,
			'sa_city_id'           => $city_id,
			'sa_english_name'      => 'Bisheh Waterfall',
			'sa_attraction_age'    => 'سازند آهکی نوزیستی؛ در منابع گردشگری نزدیک به ۶۵ میلیون سال ذکر شده است.',
			'sa_attraction_area'   => 'محدوده آبشار، چشمه‌های بالادست، ایستگاه راه‌آهن بیشه، روستای بیشه و حاشیه رودخانه سزار',
			'sa_access_level'      => 'آسان با قطار؛ متوسط با خودرو در مسیر کوهستانی',
			'sa_trail_note'        => 'پیاده‌روی کوتاه از ایستگاه/محوطه دسترسی تا دید اصلی آبشار؛ در فصل بارش مسیر و سنگ‌ها لغزنده‌اند.',
			'sa_visit_duration'    => '۱٫۵ تا ۳ ساعت',
			'sa_safety_note'       => 'نزدیک لبه‌ها، سنگ‌های خیس، پل و حاشیه رودخانه سزار احتیاط کنید؛ برای آب‌تنی یا ورود به جریان رودخانه تصمیم هیجانی نگیرید.',
			'sa_address'           => 'استان لرستان، مسیر دورود به بیشه، نزدیک روستای بیشه و ایستگاه راه‌آهن بیشه',
			'sa_opening_hours'     => 'فضای طبیعی؛ پیش از حرکت وضعیت مسیر، قطار و شرایط آب‌وهوا بررسی شود.',
			'sa_ticket_price'      => 'در روز بازدید از منبع محلی/مدیریت محوطه بررسی شود.',
			'sa_last_verified_date'=> gmdate( 'Y-m-d' ),
			'sa_seo_title'         => 'آبشار بیشه دورود؛ مسیر قطار و راهنمای بازدید',
			'sa_seo_description'   => 'راهنمای آبشار بیشه دورود در لرستان؛ مسیر قطار و خودرو، بهترین زمان بازدید، نکات ایمنی، عکس‌ها و شناسنامه کامل این نمای برتر.',
			'sa_focus_keyword'     => 'آبشار بیشه دورود',
			'sa_og_title'          => 'آبشار بیشه دورود؛ نمای برتر لرستان',
			'sa_og_description'    => 'آبشار بیشه با مسیر ریلی دیدنی، چشمه‌های بالادست و چشم‌انداز زاگرس یکی از مهم‌ترین نماهای طبیعی دورود و لرستان است.',
			'sa_sources'           => "ویزیت ایران، آبشار بیشه | https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87\nویزیت ایران، دورود | https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF\nوبلاگ اسنپ‌تریپ، آبشار بیشه کجاست؟ | https://www.snapptrip.com/blog/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87-%DA%A9%D8%AC%D8%A7%D8%B3%D8%AA/",
			'sa_facts_checked'     => gmdate( 'Y-m-d' ),
			'rank_math_title'       => 'آبشار بیشه دورود؛ مسیر قطار و راهنمای بازدید',
			'rank_math_description' => 'راهنمای آبشار بیشه دورود در لرستان؛ مسیر قطار و خودرو، بهترین زمان بازدید، نکات ایمنی، عکس‌ها و شناسنامه کامل این نمای برتر.',
			'rank_math_focus_keyword' => 'آبشار بیشه دورود',
			'rank_math_facebook_title' => 'آبشار بیشه دورود؛ نمای برتر لرستان',
			'rank_math_facebook_description' => 'آبشار بیشه با مسیر ریلی دیدنی، چشمه‌های بالادست و چشم‌انداز زاگرس یکی از مهم‌ترین نماهای طبیعی دورود و لرستان است.',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$faq = array(
			array( 'q' => 'آبشار بیشه کجاست؟', 'a' => 'آبشار بیشه در استان لرستان، نزدیک روستای بیشه و ایستگاه راه‌آهن بیشه قرار دارد. در منابع گردشگری، فاصله آن حدود ۳۰ کیلومتر از دورود و حدود ۶۵ کیلومتر از خرم‌آباد ذکر شده است.' ),
			array( 'q' => 'بهترین راه رسیدن به آبشار بیشه چیست؟', 'a' => 'برای بسیاری از مسافران، قطار بهترین و جذاب‌ترین راه است؛ چون ایستگاه بیشه در نزدیکی آبشار قرار دارد و مسیر ریلی دورود به بیشه خود بخشی از تجربه سفر است.' ),
			array( 'q' => 'آیا آبشار بیشه برای خانواده مناسب است؟', 'a' => 'بله، اما باید مراقب کودکان، مسیرهای لغزنده، پل، سنگ‌های خیس و حاشیه رودخانه باشید. در روزهای شلوغ یا بارانی، احتیاط بیشتری لازم است.' ),
			array( 'q' => 'بهترین فصل بازدید از آبشار بیشه چه زمانی است؟', 'a' => 'بهار و اوایل تابستان برای آب بیشتر و هوای خنک‌تر مناسب است. پاییز هم برای عکاسی و رنگ‌های طبیعی ارزشمند است.' ),
			array( 'q' => 'ارتفاع آبشار بیشه چقدر است؟', 'a' => 'منابع گردشگری اعداد نزدیک اما کمی متفاوتی نوشته‌اند. ویزیت ایران ارتفاع آبشار را ۵۸ متر و پهنای تاج را ۲۰ متر آورده است؛ برخی منابع دیگر ریزش اصلی را حدود ۴۸ متر و ادامه جریان تا رودخانه را حدود ۱۰ متر توضیح می‌دهند.' ),
		);
		update_post_meta( $post_id, 'sa_faq', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private static function assign_terms( $post_id ) {
		self::ensure_and_set_terms( $post_id, 'attraction_type', array( 'nature' => 'طبیعی', 'ecotourism' => 'بوم‌گردی' ) );
		self::ensure_and_set_terms( $post_id, 'travel_season', array( 'spring' => 'بهار', 'summer' => 'تابستان', 'autumn' => 'پاییز' ) );
	}

	private static function ensure_and_set_terms( $post_id, $taxonomy, $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$ids = array();
		foreach ( $terms as $slug => $name ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				if ( ! is_wp_error( $created ) && ! empty( $created['term_id'] ) ) {
					$ids[] = absint( $created['term_id'] );
				}
			} else {
				$ids[] = absint( $term->term_id );
			}
		}
		if ( $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
	}

	private static function find_gallery_images() {
		$items = array(
			'top' => array(
				'file'    => 'dorud-20261004-223817-wqywd0-1000101860.webp',
				'caption' => 'نمای بالادست آبشار بیشه؛ جایی که شاخه‌های متعدد آب از میان درختان و دیواره‌های خزه‌بسته زاگرس پایین می‌ریزند.',
				'alt'     => 'نمای بالای آبشار بیشه دورود در استان لرستان با شاخه‌های متعدد آب میان درختان و صخره‌های سبز زاگرس',
			),
			'bottom' => array(
				'file'    => '1000101810.webp',
				'caption' => 'نمای پایین آبشار بیشه؛ ریزش آب از دیواره‌های سبز و خزه‌بسته به حوضه آرام پایین‌دست.',
				'alt'     => 'نمای پایین آبشار بیشه لرستان از کنار حوضه آب با ریزش چندشاخه آب روی صخره‌های سبز نزدیک دورود',
			),
		);
		foreach ( $items as $key => $item ) {
			$items[ $key ]['id'] = self::find_attachment_by_filename( $item['file'] );
		}
		return $items;
	}

	private static function find_attachment_by_filename( $filename ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $filename );
		$id   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
				$like
			)
		);
		return $id ? absint( $id ) : 0;
	}

	private static function update_gallery_image_meta( $gallery_images ) {
		foreach ( $gallery_images as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			update_post_meta( $item['id'], '_wp_attachment_image_alt', $item['alt'] );
			wp_update_post(
				array(
					'ID'           => absint( $item['id'] ),
					'post_excerpt' => $item['caption'],
				)
			);
		}
	}

	private static function attach_identity_image( $post_id ) {
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::IMAGE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$attachment_id = $existing ? absint( $existing[0] ) : 0;
		if ( ! $attachment_id ) {
			$source = plugin_dir_path( __FILE__ ) . 'assets/' . self::IMAGE_FILE;
			if ( ! file_exists( $source ) ) {
				return;
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';

			$tmp = wp_tempnam( self::IMAGE_FILE );
			if ( ! $tmp || ! copy( $source, $tmp ) ) {
				return;
			}
			$file_array = array(
				'name'     => self::IMAGE_FILE,
				'tmp_name' => $tmp,
				'type'     => 'image/webp',
				'error'    => 0,
				'size'     => filesize( $tmp ),
			);
			$attachment_id = media_handle_sideload( $file_array, $post_id, 'تصویر نمادین آبشار بیشه دورود' );
			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return;
			}
			update_post_meta( $attachment_id, self::IMAGE_META, '1' );
		}
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'تصویر نمادین آبشار بیشه دورود در میان کوهستان سبز زاگرس برای شناسنامه نمای برتر' );
		set_post_thumbnail( $post_id, $attachment_id );
	}

	private static function image_figure( $item, $fallback_label ) {
		if ( ! empty( $item['id'] ) ) {
			$image = wp_get_attachment_image( absint( $item['id'] ), 'large', false, array( 'loading' => 'lazy' ) );
			if ( $image ) {
				return '<figure class="wp-block-image size-large">' . $image . '<figcaption>' . esc_html( $item['caption'] ) . '</figcaption></figure>';
			}
		}
		return '<p><strong>' . esc_html( $fallback_label ) . ':</strong> فایل <code>' . esc_html( $item['file'] ) . '</code> در رسانه پیدا نشد. آن را از آلبوم انتخاب کنید و با کپشن زیر در همین بخش بگذارید: «' . esc_html( $item['caption'] ) . '»</p>';
	}

	private static function article_content( $images ) {
		$top_image    = self::image_figure( $images['top'], 'جای تصویر نمای بالادست آبشار' );
		$bottom_image = self::image_figure( $images['bottom'], 'جای تصویر نمای پایین آبشار' );
		return <<<HTML
<p>آبشار بیشه یکی از شناخته‌شده‌ترین نماهای طبیعی لرستان است؛ آبشاری در دل زاگرس که کنار روستای بیشه، ایستگاه راه‌آهن و جنگل‌های بلوط دیده می‌شود. این آبشار در منابع گردشگری رسمی در فاصله حدود ۳۰ کیلومتری دورود و ۶۵ کیلومتری خرم‌آباد معرفی شده و به‌دلیل دسترسی ریلی، برای سفرهای کوتاه طبیعت‌گردی بسیار محبوب است. بیشه برای سرزمین آریان فقط یک «جاذبه» نیست؛ ترکیب آبشار، چشمه، ریل، کوه، روستا و رودخانه سزار آن را به یک «نمای برتر» کامل برای شهرستان دورود تبدیل می‌کند.</p>

<h2>چرا آبشار بیشه نمای برتر دورود است؟</h2>
<p>آبشار بیشه چند ویژگی را هم‌زمان دارد: منظره باز زاگرس، صدای آب، چشمه‌های بالادست، نزدیکی به ایستگاه راه‌آهن و دسترسی نسبتاً ساده برای مسافرانی که نمی‌خواهند وارد مسیرهای سنگین کوهنوردی شوند. منابع رسمی گردشگری، بیشه را در کنار روستایی به همین نام، ایستگاه راه‌آهن و پل فلزی معرفی می‌کنند و از ثبت آن در فهرست میراث طبیعی ملی ایران یاد می‌کنند.</p>
<p>در روایت سفر، مهم‌ترین امتیاز بیشه این است که مسیر رسیدن به آن هم بخشی از تجربه است. قطار از میان کوهستان عبور می‌کند، مسافر در ایستگاه بیشه پیاده می‌شود و با پیاده‌روی کوتاه به چشم‌انداز اصلی می‌رسد. همین پیوند میان راه‌آهن، آبشار و روستا باعث شده بیشه در ذهن بسیاری از مسافران با نام «آبشار بیشه دورود» شناخته شود.</p>
$top_image

<h2>موقعیت آبشار بیشه و نسبت آن با دورود</h2>
<p>آبشار بیشه در مجاورت روستای بیشه و در محدوده سپیددشت/بخش پاپی معرفی شده است، اما از نظر مسیر گردشگری، ایستگاه راه‌آهن و ذهنیت سفر، ارتباط آن با دورود بسیار پررنگ است. ویزیت ایران فاصله آن را حدود ۳۰ کیلومتر از دورود و حدود ۶۵ کیلومتر از خرم‌آباد ذکر کرده است. صفحه رسمی دورود نیز آبشار بیشه را در فهرست جاذبه‌های گردشگری مرتبط با دورود آورده و خود دورود را شهری در شرق لرستان، دامنه زاگرس و نزدیک اشترانکوه معرفی می‌کند.</p>
<p>برای انتشار در سرزمین آریان، بهتر است در شناسنامه، شهرستان «دورود» ثبت شود و در متن توضیح داده شود که برخی منابع، محدوده اداری آبشار را با بخش پاپی/سپیددشت نیز معرفی می‌کنند. این کار هم با تجربه رایج مسافران هماهنگ است و هم از خطای اداری یا ادعای قطعی بی‌منبع جلوگیری می‌کند.</p>

<h2>مسیر دسترسی به آبشار بیشه</h2>
<h3>مسیر ریلی؛ بهترین انتخاب برای تجربه آرام و دیدنی</h3>
<p>راحت‌ترین و خاطره‌انگیزترین راه رسیدن به بیشه، قطار است. در منابع سفر، مسیر ریلی دورود به ایستگاه بیشه حدود ۳۰ تا ۳۵ دقیقه ذکر شده و ایستگاه بیشه در نزدیکی آبشار قرار دارد. اگر برنامه شما با ساعت حرکت قطار هماهنگ باشد، این مسیر هم امن‌تر است و هم خود سفر را به یک تجربه دیدنی تبدیل می‌کند.</p>
<p>برای انتشار نهایی، ساعت حرکت قطارها را همان روز از سامانه راه‌آهن یا منابع محلی چک کنید؛ چون برنامه قطار، توقف‌ها و ظرفیت‌ها ممکن است تغییر کند. در روزهای شلوغ، مخصوصاً تعطیلات بهار و تابستان، بهتر است رفت‌وبرگشت را از قبل قطعی کنید.</p>
<h3>مسیر خودرویی؛ مناسب با احتیاط و بررسی محلی</h3>
<p>دسترسی خودرویی به بیشه ممکن است، اما باید کوهستانی‌بودن مسیر، وضعیت فصل، بارندگی، مه و شلوغی را جدی گرفت. ویزیت ایران مسیر آسفالته از سمت خرم‌آباد را حدود ۶۰ کیلومتر معرفی کرده است. منابع سفر نیز چند مسیر خودرویی از محورهای دورود/خرم‌آباد به سمت فرعی بیشه را توضیح داده‌اند.</p>
<p>اگر با خودرو می‌روید، قبل از حرکت از مردم محلی، اقامتگاه، راهدارخانه یا پلیس راه درباره وضعیت مسیر بپرسید. در شب، بارندگی، برف یا مه غلیظ، بهتر است بدون آشنایی محلی وارد جاده نشوید.</p>

<h2>بهترین زمان بازدید از آبشار بیشه</h2>
<p>بهار و اوایل تابستان، آبشار پرآب‌تر، هوا خنک‌تر و پوشش سبز منطقه چشمگیرتر است. پاییز هم برای عکاسی عالی است، مخصوصاً اگر هدف شما رنگ‌های گرم، هوای آرام‌تر و جمعیت کمتر باشد. در تابستان، بهتر است صبح زود یا نزدیک عصر بروید تا هم نور عکاسی بهتر باشد و هم گرمای مسیر کمتر اذیت کند.</p>
<p>در زمستان یا روزهای بارانی، زیبایی منطقه کم نمی‌شود، اما ریسک لغزندگی، سرمای هوا و سختی مسیر بیشتر است. اگر تجربه طبیعت‌گردی زمستانی ندارید، بازدید را به روزهای پایدارتر موکول کنید.</p>

<h2>مناسب چه کسانی است؟</h2>
<p>آبشار بیشه برای خانواده‌ها، عکاسان، مسافران ریلی، طبیعت‌گردهای سبک و کسانی که می‌خواهند در یک روز کوتاه بخشی از طبیعت زاگرس را ببینند مناسب است. مسیر ریلی، آن را برای سفرهای کم‌دردسر جذاب‌تر می‌کند. با این حال، برای سالمندان، کودکان و افراد با محدودیت حرکتی، باید وضعیت پله‌ها، پل، شیب‌ها و شلوغی محوطه را قبل از حرکت بررسی کرد.</p>
<p>این مقصد برای کسانی که دنبال سکوت مطلق طبیعت هستند، در روزهای تعطیل ممکن است شلوغ باشد. اگر آرامش و عکاسی بدون ازدحام می‌خواهید، روزهای میانی هفته و ساعت‌های اولیه صبح انتخاب بهتری است.</p>

<h2>نکات ایمنی و محیط‌زیستی</h2>
<p>آبشار بیشه به‌ظاهر مقصدی آسان است، اما آب، سنگ خیس، شیب، پل و رودخانه همیشه نیاز به احتیاط دارند. نزدیک لبه آبشار یا حاشیه پرتگاه عکس نگیرید. روی سنگ‌های خیس ندوید. اگر جریان رودخانه تند است، وارد آب نشوید و مخصوصاً مراقب کودکان باشید.</p>
<p>لطفاً زباله را حتی اگر کوچک است با خودتان برگردانید. روشن‌کردن آتش در نزدیکی درختان، رهاکردن پلاستیک، شکستن شاخه‌ها یا ورود به باغ و حریم خانه‌های محلی، چهره این مقصد را خراب می‌کند. «نمای برتر» زمانی می‌ماند که رفتار گردشگر هم برتر باشد.</p>
$bottom_image

<h2>پیشنهاد عکاسی و تصاویر لازم</h2>
<p>برای این مقاله، تصویر نمادین کارت شناسنامه در بالا کافی است و دو تصویر واقعی آلبوم، ستون اصلی روایت تصویری را می‌سازند: یک تصویر از بالادست که چندشاخه بودن آبشار را نشان می‌دهد و یک تصویر از پایین که عظمت ریزش آب و دیواره‌های سبز را منتقل می‌کند. اگر بعداً تصویر ایستگاه راه‌آهن بیشه یا مسیر پیاده‌روی هم اضافه شد، آن را بعد از بخش مسیر دسترسی قرار دهید.</p>

<h2>برنامه پیشنهادی بازدید کوتاه</h2>
<p>اگر با قطار می‌روید، برنامه را با ساعت رفت‌وبرگشت تنظیم کنید. پس از رسیدن به ایستگاه بیشه، ابتدا مسیر پیاده‌روی تا نمای اصلی آبشار را بروید، چند دقیقه فقط منظره را تماشا کنید، سپس عکس‌های نمای بالا و پایین را بگیرید. اگر زمان داشتید، کوتاه در اطراف روستا یا بازارچه محلی قدم بزنید و قبل از تاریکی به ایستگاه یا محل پارک خودرو برگردید.</p>
<p>برای سفر خانوادگی، عجله نکنید؛ بیشه مقصدی برای توقف، نفس‌کشیدن و شنیدن صدای آب است، نه فقط گرفتن چند عکس سریع.</p>

<h2>نمای‌های نزدیک و لینک‌های داخلی پیشنهادی</h2>
<ul>
<li>صفحه شهرستان دورود</li>
<li>صفحه استان لرستان</li>
<li>گالری تصاویر شهرستان دورود</li>
<li>دریاچه گهر</li>
<li>اشترانکوه</li>
<li>رودخانه سزار</li>
<li>آبشار وقت ساعت، اگر مقاله آن منتشر شد</li>
</ul>

<h2>سوالات متداول</h2>
<h3>آبشار بیشه کجاست؟</h3>
<p>آبشار بیشه در استان لرستان، نزدیک روستای بیشه و ایستگاه راه‌آهن بیشه قرار دارد. در منابع گردشگری، فاصله آن حدود ۳۰ کیلومتر از دورود و حدود ۶۵ کیلومتر از خرم‌آباد ذکر شده است.</p>
<h3>بهترین راه رسیدن به آبشار بیشه چیست؟</h3>
<p>برای بسیاری از مسافران، قطار بهترین و جذاب‌ترین راه است؛ چون ایستگاه بیشه در نزدیکی آبشار قرار دارد و مسیر ریلی دورود به بیشه خود بخشی از تجربه سفر است.</p>
<h3>آیا آبشار بیشه برای خانواده مناسب است؟</h3>
<p>بله، اما باید مراقب کودکان، مسیرهای لغزنده، پل، سنگ‌های خیس و حاشیه رودخانه باشید. در روزهای شلوغ یا بارانی، احتیاط بیشتری لازم است.</p>
<h3>بهترین فصل بازدید از آبشار بیشه چه زمانی است؟</h3>
<p>بهار و اوایل تابستان برای آب بیشتر و هوای خنک‌تر مناسب است. پاییز هم برای عکاسی و رنگ‌های طبیعی ارزشمند است.</p>
<h3>ارتفاع آبشار بیشه چقدر است؟</h3>
<p>منابع گردشگری اعداد نزدیک اما کمی متفاوتی نوشته‌اند. ویزیت ایران ارتفاع آبشار را ۵۸ متر و پهنای تاج را ۲۰ متر آورده است؛ برخی منابع دیگر ریزش اصلی را حدود ۴۸ متر و ادامه جریان تا رودخانه را حدود ۱۰ متر توضیح می‌دهند.</p>
<h3>آیا شنا یا آب‌تنی در بیشه توصیه می‌شود؟</h3>
<p>تصمیم به ورود به آب باید با احتیاط کامل باشد. جریان، عمق، سنگ‌های لغزنده و تغییرات فصلی خطرسازند؛ برای خانواده و کودکان، تماشای آبشار و عکاسی امن‌تر از ورود به آب است.</p>

<h2>منابع</h2>
<ul>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87" target="_blank" rel="noopener">ویزیت ایران: آبشار بیشه</a></li>
<li><a href="https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF" target="_blank" rel="noopener">ویزیت ایران: دورود</a></li>
<li><a href="https://www.snapptrip.com/blog/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87-%DA%A9%D8%AC%D8%A7%D8%B3%D8%AA/" target="_blank" rel="noopener">وبلاگ اسنپ‌تریپ: آبشار بیشه کجاست؟</a></li>
</ul>
HTML;
	}
}

SA_Bisheh_Featured_View_Importer::init();
