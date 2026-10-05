<?php
/**
 * Plugin Name: Sarzamin Aryan — Gahar Lake Featured View Importer
 * Description: درون‌ریز تک‌مقاله «نمای برتر دریاچه گهر دورود» برای قالب سرزمین آریان؛ پیش‌نویس/مقاله، متاهای شناسنامه، سئو، Rank Math، FAQ، منابع و تصویر شاخص کارت سفید را می‌سازد/به‌روزرسانی می‌کند.
 * Version: 1.0.1
 * Author: Sarzamin Aryan
 * License: GPLv2 or later
 * Text Domain: sa-gahar-lake-featured-view-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_Gahar_Lake_Featured_View_Importer {
	const VERSION     = '1.0.1';
	const ACTION      = 'sa_gahar_lake_featured_view_import';
	const NONCE       = 'sa_gahar_lake_featured_view_import_nonce';
	const POST_SLUG   = 'gahar-lake-dorud';
	const IMAGE_META  = '_sa_gahar_white_card_image';
	const IMAGE_FILE  = 'gahar-lake-dorud-white-card-1200x600.webp';
	const OPTION_LAST = 'sa_gahar_importer_last_post_id';

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
		array_unshift( $links, '<a href="' . esc_url( $url ) . '"><strong>درون‌ریزی مقاله دریاچه گهر</strong></a>' );
		return $links;
	}

	public static function maybe_run_import() {
		if ( ! is_admin() || ! isset( $_GET[ self::ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'برای اجرای این درون‌ریز دسترسی کافی ندارید.', 'sa-gahar-lake-featured-view-importer' ) );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		$result = self::import();
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'sa-gahar-import' => 'error',
						'sa-gahar-msg'    => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'plugins.php' )
				)
			);
			exit;
		}

		update_option( self::OPTION_LAST, absint( $result ), false );
		wp_safe_redirect( add_query_arg( 'sa-gahar-import', 'success', get_edit_post_link( $result, 'raw' ) ) );
		exit;
	}

	public static function admin_notices() {
		if ( ! isset( $_GET['sa-gahar-import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 'success' === $_GET['sa-gahar-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>مقاله «دریاچه گهر» با موفقیت ساخته/به‌روزرسانی شد. کارت سفید تصویر شاخص، سئو، Rank Math، FAQ و منابع آماده بررسی و انتشار است.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'error' === $_GET['sa-gahar-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = isset( $_GET['sa-gahar-msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sa-gahar-msg'] ) ) : 'خطای نامشخص';
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function import() {
		if ( ! post_type_exists( 'attraction' ) ) {
			return new WP_Error( 'missing_attraction_cpt', 'نوع محتوای «نمای برتر/attraction» پیدا نشد. ابتدا قالب فرزند سرزمین آریان را فعال کنید.' );
		}

		$province_id = self::ensure_province();
		if ( is_wp_error( $province_id ) ) {
			return $province_id;
		}
		$city_id = self::ensure_city( $province_id );
		if ( is_wp_error( $city_id ) ) {
			return $city_id;
		}

		$gallery_images = self::find_gallery_images();
		$post_id        = self::find_post_id( 'attraction', self::POST_SLUG, 'دریاچه گهر' );
		$status         = $post_id ? get_post_status( $post_id ) : 'draft';
		if ( ! in_array( $status, array( 'publish', 'draft', 'pending', 'future', 'private' ), true ) ) {
			$status = 'draft';
		}

		if ( $post_id ) {
			self::update_meta( $post_id, $province_id, $city_id );
			self::assign_terms( $post_id );
			self::attach_white_card_image( $post_id );
		}

		$postarr = array(
			'post_type'    => 'attraction',
			'post_status'  => $status,
			'post_name'    => self::POST_SLUG,
			'post_title'   => 'دریاچه گهر',
			'post_excerpt' => 'دریاچه گهر در جنوب شرقی دورود و دامنه اشترانکوه، یکی از شاخص‌ترین دریاچه‌های کوهستانی ایران است؛ مقصدی زیبا اما چالش‌برانگیز که برای مسیر دورود، برنامه‌ریزی، آمادگی بدنی و احترام به منطقه حفاظت‌شده می‌خواهد.',
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
		self::attach_white_card_image( $post_id );
		self::update_gallery_image_meta( $gallery_images );

		if ( function_exists( 'sa_sync_relations' ) ) {
			sa_sync_relations( $post_id, 'attraction' );
		}
		if ( function_exists( 'sa_flush_relation_cache' ) ) {
			sa_flush_relation_cache( $post_id, 'attraction' );
		}

		return $post_id;
	}

	private static function ensure_province() {
		$post_id = self::find_post_id( 'province', 'lorestan', 'لرستان' );
		if ( $post_id ) {
			return $post_id;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'province',
				'post_status' => 'draft',
				'post_name'   => 'lorestan',
				'post_title'  => 'لرستان',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		self::ensure_term( 'province_tax', 'lorestan', 'لرستان' );
		wp_set_object_terms( $post_id, 'lorestan', 'province_tax', false );
		if ( function_exists( 'sa_sync_relations' ) ) {
			sa_sync_relations( $post_id, 'province' );
		}
		return absint( $post_id );
	}

	private static function ensure_city( $province_id ) {
		$post_id = self::find_post_id( 'city', 'dorud', 'دورود' );
		if ( $post_id ) {
			update_post_meta( $post_id, 'sa_province_id', absint( $province_id ) );
			wp_set_object_terms( $post_id, 'lorestan', 'province_tax', false );
			return $post_id;
		}
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'city',
				'post_status' => 'draft',
				'post_name'   => 'dorud',
				'post_title'  => 'دورود',
				'meta_input'  => array( 'sa_province_id' => absint( $province_id ) ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		self::ensure_term( 'province_tax', 'lorestan', 'لرستان' );
		wp_set_object_terms( $post_id, 'lorestan', 'province_tax', false );
		return absint( $post_id );
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
			'sa_province_id'       => absint( $province_id ),
			'sa_city_id'           => absint( $city_id ),
			'sa_english_name'      => 'Gahar Lake',
			'sa_attraction_age'    => 'دریاچه کوهستانی شکل‌گرفته در بستر طبیعی اشترانکوه؛ برای تاریخ زمین‌شناسی دقیق، منابع تخصصی زمین‌شناسی باید بررسی شوند.',
			'sa_attraction_area'   => 'گهر بزرگ، گهر کوچک، دامنه اشترانکوه، دره گهررود/نی‌گاه و مسیر دسترسی چشمه خیه یا چشمه خرم',
			'sa_elevation'         => '2350',
			'sa_access_level'      => 'چالش‌برانگیز؛ مسیر دورود ترکیبی از جاده تا چشمه خیه/خرم و پیمایش کوهستانی ۱۳ تا ۱۸ کیلومتر است.',
			'sa_trail_note'        => 'مسیر رایج از دورود به چشمه خیه/خرم می‌رسد و سپس با عبور از گردنه‌هایی مثل پنبه‌کار تا دریاچه ادامه دارد؛ راهنمای محلی، نقشه آفلاین و آمادگی بدنی توصیه می‌شود.',
			'sa_visit_duration'    => 'حداقل ۲ روز برای رفت، کمپ/استراحت و برگشت امن',
			'sa_safety_note'       => 'مسیر برای افراد آماتور، کودکان خردسال و سالمندان مناسب نیست؛ تغییر ناگهانی هوا، قطع آنتن، انشعاب مسیر، گرما، کم‌آبی و حیات وحش را جدی بگیرید.',
			'sa_latitude'          => '33.3075',
			'sa_longitude'         => '49.2817',
			'sa_address'           => 'استان لرستان، شهرستان دورود، جنوب شرقی دورود، منطقه حفاظت‌شده اشترانکوه، مسیر چشمه خیه/چشمه خرم به دریاچه گهر',
			'sa_opening_hours'     => 'فضای طبیعی؛ زمان ورود، محدودیت‌های محیط‌زیست، وضعیت مسیر و فصل بازدید باید پیش از حرکت بررسی شود.',
			'sa_ticket_price'      => 'ورودی منطقه/پارکینگ و کرایه قاطر یا خدمات محلی ممکن است دریافت شود؛ مبلغ را در روز سفر از مبدأ محلی بررسی کنید.',
			'sa_official_website'  => 'https://www.visitiran.ir/fa/attraction/%D8%AF%D8%B1%DB%8C%D8%A7%DA%86%D9%87-%DB%8C-%DA%AF%D9%87%D8%B1',
			'sa_last_verified_date'=> gmdate( 'Y-m-d' ),
			'sa_seo_title'         => 'دریاچه گهر دورود؛ مسیر چشمه خیه و راهنمای کامل سفر',
			'sa_seo_description'   => 'راهنمای کامل دریاچه گهر دورود در لرستان؛ مسیر چشمه خیه، گردنه پنبه‌کار، زمان پیمایش، امکانات، خطرات، کمپ، بهترین فصل و نکات ایمنی.',
			'sa_focus_keyword'     => 'دریاچه گهر دورود',
			'sa_og_title'          => 'دریاچه گهر دورود؛ نگین اشترانکوه لرستان',
			'sa_og_description'    => 'مسیر دورود به دریاچه گهر زیبا اما چالش‌برانگیز است؛ این راهنما مسیر، امکانات، خطرات و برنامه پیشنهادی سفر را مرحله‌به‌مرحله توضیح می‌دهد.',
			'sa_sources'           => self::sources_text(),
			'sa_facts_checked'     => gmdate( 'Y-m-d' ),
			'rank_math_title'       => 'دریاچه گهر دورود؛ مسیر چشمه خیه و راهنمای کامل سفر',
			'rank_math_description' => 'راهنمای کامل دریاچه گهر دورود در لرستان؛ مسیر چشمه خیه، گردنه پنبه‌کار، زمان پیمایش، امکانات، خطرات، کمپ، بهترین فصل و نکات ایمنی.',
			'rank_math_focus_keyword' => 'دریاچه گهر دورود',
			'rank_math_facebook_title' => 'دریاچه گهر دورود؛ نگین اشترانکوه لرستان',
			'rank_math_facebook_description' => 'مسیر دورود به دریاچه گهر زیبا اما چالش‌برانگیز است؛ این راهنما مسیر، امکانات، خطرات و برنامه پیشنهادی سفر را مرحله‌به‌مرحله توضیح می‌دهد.',
			'rank_math_twitter_title' => 'دریاچه گهر دورود؛ مسیر چشمه خیه و راهنمای سفر',
			'rank_math_twitter_description' => 'مسیر دورود، گردنه پنبه‌کار، امکانات محدود، کمپ، ایمنی و بهترین زمان سفر به دریاچه گهر لرستان.',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$faq = array(
			array( 'q' => 'دریاچه گهر کجاست؟', 'a' => 'دریاچه گهر در استان لرستان، جنوب شرقی دورود و در منطقه حفاظت‌شده اشترانکوه قرار دارد. مسیر رایج سفر از دورود به سمت چشمه خیه/خرم و سپس پیمایش کوهستانی تا دریاچه است.' ),
			array( 'q' => 'مسیر دورود تا دریاچه گهر چقدر پیاده‌روی دارد؟', 'a' => 'بسته به نقطه شروع و شیوه اندازه‌گیری مسیر، پیمایش از چشمه خیه/خرم تا دریاچه حدود ۱۳ تا ۱۸ کیلومتر گزارش شده است. برای بیشتر مسافران، رفت بین ۴ تا ۸ ساعت زمان می‌برد.' ),
			array( 'q' => 'سخت‌ترین بخش مسیر گهر از دورود کجاست؟', 'a' => 'گردنه پنبه‌کار معمولاً سخت‌ترین بخش مسیر معرفی می‌شود؛ شیب، آفتاب، فرود و صعود مسیر در این محدوده می‌تواند برای افراد کم‌تجربه فرساینده باشد.' ),
			array( 'q' => 'آیا در اطراف دریاچه گهر فروشگاه وجود دارد؟', 'a' => 'خیر، نباید روی فروشگاه یا خرید خوراکی کنار دریاچه حساب کرد. خوراک، آب، سوخت، کیسه زباله و وسایل کمپینگ باید از دورود یا پیش از شروع مسیر تهیه شود.' ),
			array( 'q' => 'بهترین زمان سفر به دریاچه گهر چه زمانی است؟', 'a' => 'برای پیمایش و کمپ، معمولاً تابستان و به‌ویژه تیر تا شهریور انتخاب رایج‌تری است. اواخر بهار نیز از نظر طبیعت و گل‌ها زیباست، اما باید وضعیت مسیر، برفاب، مجوز و هوا بررسی شود.' ),
			array( 'q' => 'آیا مسیر دریاچه گهر برای کودکان و سالمندان مناسب است؟', 'a' => 'مسیر دورود به گهر چالش‌برانگیز است و برای کودکان خردسال، سالمندان، افراد کم‌آمادگی یا کسانی که تجربه پیمایش کوهستان ندارند توصیه نمی‌شود.' ),
			array( 'q' => 'آیا برای مسیر گهر راهنمای محلی لازم است؟', 'a' => 'برای اولین سفر، راهنمای محلی یا همراه باتجربه بسیار توصیه می‌شود؛ مسیر انشعاب دارد، در بخش‌هایی آنتن‌دهی قطع می‌شود و تغییر هوا می‌تواند تصمیم‌گیری را سخت کند.' ),
			array( 'q' => 'امکانات کنار دریاچه گهر چیست؟', 'a' => 'در فصل‌های پرتردد معمولاً امکاناتی مانند سرویس بهداشتی، کمپ محیط‌زیست، پایگاه امدادی یا انتظامی و خدمات محدود فصلی برقرار است؛ اما این امکانات دائمی و جایگزین آمادگی شخصی نیستند.' ),
			array( 'q' => 'آیا می‌توان کنار گهر کمپ زد؟', 'a' => 'کمپ‌زدن در محدوده‌های مجاز و با رعایت مقررات محیط‌زیست انجام می‌شود. چادر، کیسه‌خواب، لباس گرم، چراغ، کیسه زباله و نگهداری امن غذا دور از چادر ضروری است.' ),
			array( 'q' => 'مهم‌ترین خطرهای مسیر گهر چیست؟', 'a' => 'گرما و کم‌آبی، تغییر ناگهانی هوا، گم‌شدن در انشعاب‌ها، قطع آنتن، سنگلاخ و شیب، حیات‌وحش و برگشت زیر آفتاب از خطرهای مهم مسیر هستند.' ),
		);
		update_post_meta( $post_id, 'sa_faq', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private static function assign_terms( $post_id ) {
		self::ensure_and_set_terms( $post_id, 'attraction_type', array( 'nature' => 'طبیعی', 'mountain' => 'کوهستانی', 'ecotourism' => 'بوم‌گردی', 'adventure' => 'ماجراجویی' ) );
		self::ensure_and_set_terms( $post_id, 'travel_season', array( 'spring' => 'بهار', 'summer' => 'تابستان', 'autumn' => 'پاییز' ) );
		self::ensure_term( 'province_tax', 'lorestan', 'لرستان' );
		wp_set_object_terms( $post_id, 'lorestan', 'province_tax', false );
	}

	private static function ensure_and_set_terms( $post_id, $taxonomy, $terms ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}
		$ids = array();
		foreach ( $terms as $slug => $name ) {
			$term_id = self::ensure_term( $taxonomy, $slug, $name );
			if ( $term_id ) {
				$ids[] = $term_id;
			}
		}
		if ( $ids ) {
			wp_set_post_terms( $post_id, $ids, $taxonomy, false );
		}
	}

	private static function ensure_term( $taxonomy, $slug, $name ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return absint( $term->term_id );
		}
		$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $created ) ) {
			return 0;
		}
		return absint( $created['term_id'] );
	}

	private static function find_gallery_images() {
		$items = array(
			'lake' => array(
				'file'    => '1000101861.webp',
				'caption' => 'نمای هوایی دریاچه گهر در دامنه اشترانکوه؛ آب فیروزه‌ای، ساحل سبز و کوهستان خشک زاگرس در یک قاب.',
				'alt'     => 'نمای هوایی دریاچه گهر دورود در استان لرستان میان کوه‌های اشترانکوه و حاشیه سبز دریاچه',
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

	private static function attach_white_card_image( $post_id ) {
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
			$attachment_id = media_handle_sideload( $file_array, $post_id, 'کارت سفید تصویر شاخص دریاچه گهر دورود' );
			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return;
			}
			update_post_meta( $attachment_id, self::IMAGE_META, '1' );
		}
		wp_update_post(
			array(
				'ID'           => $attachment_id,
				'post_title'   => 'کارت سفید دریاچه گهر دورود لرستان',
				'post_excerpt' => 'دریاچه گهر | شهرستان دورود | استان لرستان / Gahar Lake | Dorud city | Lorestan',
			)
		);
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'کارت سفید دریاچه گهر شهرستان دورود استان لرستان با آیکون رنگی گوشه و نام انگلیسی Gahar Lake | Dorud city | Lorestan' );
		set_post_thumbnail( $post_id, $attachment_id );
	}

	private static function image_figure( $item ) {
		if ( ! empty( $item['id'] ) ) {
			$image = wp_get_attachment_image( absint( $item['id'] ), 'large', false, array( 'loading' => 'lazy' ) );
			if ( $image ) {
				return '<figure class="wp-block-image size-large">' . $image . '<figcaption>' . esc_html( $item['caption'] ) . '</figcaption></figure>';
			}
		}
		return '';
	}

	private static function sources_text() {
		return implode(
			"\n",
			array(
				'ویزیت ایران، دریاچه گهر | https://www.visitiran.ir/fa/attraction/%D8%AF%D8%B1%DB%8C%D8%A7%DA%86%D9%87-%DB%8C-%DA%AF%D9%87%D8%B1',
				'ویزیت ایران، دورود | https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF',
				'ویزیت ایران، غار چشمه وقت ساعت دورود | https://www.visitiran.ir/fa/attraction/%D8%BA%D8%A7%D8%B1-%DA%86%D8%B4%D9%85%D9%87-%D9%88%D9%82%D8%AA%D9%90-%D8%B3%D8%A7%D8%B9%D8%AA-%D8%AF%D9%88%D8%B1%D9%88%D8%AF',
				'اداره فرهنگ و ارشاد اسلامی دورود، دریاچه گهر | https://doroud.farhang.gov.ir/fa/tabiat/gahar',
				'فرمانداری دورود، دریاچه گهر دورود | http://dorood-gov.ir/index.php/2020-11-14-07-44-58/87-2022-01-14-12-59-01',
				'کجارو، جاهای دیدنی دورود | https://www.kojaro.com/dorud/',
				'کارناوال، دریاچه گهر دورود | https://www.karnaval.ir/things-to-do/gahar-lake-dorud',
			)
		);
	}

	private static function article_content( $images ) {
		$lake_image = self::image_figure( $images['lake'] );
		return <<<HTML
<p>دریاچه گهر یکی از زیباترین نماهای طبیعی لرستان و از شاخص‌ترین مقصدهای کوهستانی زاگرس است؛ آبی عمیق و فیروزه‌ای در دامنه اشترانکوه که برای رسیدن به آن از مسیر دورود، فقط علاقه کافی نیست: برنامه‌ریزی، آمادگی بدنی، احترام به منطقه حفاظت‌شده و تصمیم‌های ایمن لازم است. اگر از دورود حرکت کنید، مسیر معمولاً با جاده تا محدوده چشمه خیه یا چشمه خرم شروع می‌شود و بعد وارد پیمایش کوهستانی می‌شوید؛ پیمایشی که برای بسیاری از مسافران زیباترین و هم‌زمان سخت‌ترین بخش سفر است.</p>
<p>این راهنما برای کسی نوشته شده که می‌خواهد «واقعاً» از مسیر دورود به گهر برسد؛ نه فقط بداند دریاچه کجاست. در متن، اطلاعات ارسالی شما درباره مسیر، گردنه پنبه‌کار، امکانات محدود، خطرات، زمان‌بندی و توصیه‌های کمپ با داده‌های عمومی و منابع گردشگری ترکیب شده تا مقاله هم کاربردی باشد و هم استاندارد انتشار در سرزمین آریان را داشته باشد.</p>
$lake_image

<h2>شناسنامه سریع دریاچه گهر</h2>
<ul>
<li><strong>نام فارسی:</strong> دریاچه گهر</li>
<li><strong>نام انگلیسی:</strong> Gahar Lake</li>
<li><strong>استان:</strong> لرستان</li>
<li><strong>شهرستان پیشنهادی در سرزمین آریان:</strong> دورود</li>
<li><strong>موقعیت کلی:</strong> جنوب شرقی دورود، دامنه اشترانکوه، منطقه حفاظت‌شده اشترانکوه</li>
<li><strong>ارتفاع تقریبی:</strong> حدود ۲۳۵۰ تا ۲۴۰۰ متر از سطح دریا</li>
<li><strong>نوع تجربه:</strong> دریاچه کوهستانی، کوه‌پیمایی، کمپ، عکاسی طبیعت</li>
<li><strong>درجه سختی مسیر دورود:</strong> متوسط رو به سخت تا سخت، بسته به آمادگی بدنی و بار همراه</li>
<li><strong>مدت پیشنهادی سفر:</strong> حداقل دو روز</li>
</ul>

<h2>مسیر دورود به دریاچه گهر؛ از شهر تا شروع پیمایش</h2>
<p>مسیر رایج و شناخته‌شده برای بسیاری از مسافران، از شهرستان دورود آغاز می‌شود. ابتدا باید خودتان را با خودرو شخصی، اتوبوس یا قطار به دورود برسانید. از دورود، مسیر جاده‌ای به سمت درب آستانه، امامزاده پیر والی و چشمه خیه یا چشمه خرم ادامه پیدا می‌کند. در منابع مختلف، طول جاده آسفالته از دورود تا محدوده شروع پیمایش حدود ۱۷ تا ۲۴ کیلومتر ذکر شده است؛ این اختلاف به محل دقیق شروع، نام‌گذاری محلی و نقطه پارک خودرو برمی‌گردد.</p>
<p>پس از رسیدن به پارکینگ/ورودی مسیر، بخش اصلی سفر آغاز می‌شود: پیاده‌روی در مسیر کوهستانی. برخی منابع رسمی مسیر مالرو از چشمه خیه تا گهر را حدود ۱۸ کیلومتر نوشته‌اند؛ در تجربه‌های محلی و برخی گزارش‌های مسیر، عدد حدود ۱۲٫۵ تا ۱۳ کیلومتر هم برای پیمایش اصلی دیده می‌شود. بنابراین بهتر است برای برنامه‌ریزی محافظه‌کارانه، مسیر را کوتاه فرض نکنید و آمادگی پیمایش ۱۳ تا ۱۸ کیلومتر کوهستانی را داشته باشید.</p>

<h2>نام‌های محلی و نقاط مهم مسیر</h2>
<p>در مسیر دورود به گهر، نام‌هایی مثل چشمه خیه، چشمه خرم، گردنه پنبه‌کار، گردنه خداقوت، روستای سراوند، روستای تیتی، دره نی‌گاه یا نگار و گهررود شنیده می‌شود. همه این نام‌ها برای مسیریابی عمومی کافی نیستند، چون مسیرهای مالرو و فرعی می‌توانند انشعاب داشته باشند. اگر بار اول است که می‌روید، فقط به شنیدن نام‌ها یا دنبال‌کردن جمعیت اکتفا نکنید؛ راهنمای محلی، نقشه آفلاین و گروه آشنا با مسیر می‌تواند تفاوت یک سفر خوش و یک تجربه پرخطر باشد.</p>
<p>گردنه پنبه‌کار معمولاً سخت‌ترین بخش مسیر معرفی می‌شود. این بخش هم شیب دارد، هم زیر آفتاب می‌تواند فرساینده شود، و هم در برگشت فشار بیشتری به زانو و توان بدنی وارد می‌کند. اگر کوله سنگین دارید، کرایه قاطر یا حیوان باربر محلی برای حمل بار، مخصوصاً در سفر دو روزه، تصمیمی عاقلانه است.</p>

<h2>تحلیل صفر تا صد مسیر رفت و برگشت</h2>
<h3>مرحله اول: رسیدن به دورود و پارکینگ چشمه خیه/خرم</h3>
<p>پیشنهاد بهتر این است که یک روز قبل یا صبح خیلی زود به دورود برسید. اگر با قطار می‌آیید، زمان رسیدن قطار و هماهنگی تاکسی یا وسیله محلی تا شروع مسیر را از قبل مشخص کنید. اگر با خودرو شخصی می‌روید، وضعیت پارکینگ، ورودی منطقه و امکان بازگشت را همان روز از افراد محلی بررسی کنید. در فصل شلوغ، بهتر است دیر حرکت نکنید؛ هم گرمای مسیر کمتر اذیت می‌کند، هم در روشنایی کافی به مقصد می‌رسید.</p>

<h3>مرحله دوم: مسیر رفت تا دریاچه</h3>
<p>مسیر رفت معمولاً بین ۴ تا ۸ ساعت زمان می‌برد؛ عدد دقیق به آمادگی بدنی، وزن کوله، توقف‌ها، گرما، وضعیت مسیر و تعداد نفرات گروه بستگی دارد. مسیر با بخش‌هایی ملایم شروع می‌شود، اما بعد از رسیدن به محدوده گردنه‌ها جدی‌تر می‌شود. گردنه پنبه‌کار، فرود و صعودهای پی‌درپی، و تابش آفتاب مهم‌ترین عامل خستگی هستند.</p>
<p>اگر برای اولین بار می‌روید، عجله نکنید. هر یک تا یک‌ونیم ساعت توقف کوتاه، کنترل آب بدن، تنظیم بندهای کوله و بررسی حال اعضای گروه لازم است. دریاچه گهر مقصدی نیست که با مسابقه‌دادن به آن برسید؛ باید انرژی برگشت، برپا کردن چادر و شرایط شب را هم نگه دارید.</p>

<h3>مرحله سوم: رسیدن، کمپ و انتخاب محل استراحت</h3>
<p>وقتی به گهر می‌رسید، وسوسه طبیعی است که کنار اولین قاب زیبا چادر بزنید؛ اما محل کمپ را با دقت انتخاب کنید. نزدیک آب، مسیر عبور، محدوده‌های ممنوع، شیب، باد، فاصله از سرویس‌ها و امنیت غذایی را در نظر بگیرید. در سمت‌های بکرتر دریاچه امکانات کمتر است و همین موضوع، هم آرامش بیشتری می‌دهد و هم مسئولیت بیشتری می‌خواهد.</p>
<p>مواد غذایی را شب‌ها داخل چادر رها نکنید. منطقه حفاظت‌شده است و احتمال حضور حیات‌وحش وجود دارد. غذا، زباله و مواد بودار باید دور از محل خواب و در کیسه مناسب نگهداری شود. هر چیزی که به دریاچه می‌برید، باید برگردانده شود؛ حتی زباله‌های کوچک.</p>

<h3>مرحله چهارم: مسیر برگشت</h3>
<p>برگشت برای بسیاری از افراد سخت‌تر از رفت است، چون خستگی جمع شده، آفتاب بالا آمده و در یک‌سوم آخر مسیر شیب‌ها فرساینده می‌شوند. اگر زانو درد دارید، باتوم کوهنوردی و کفش مناسب کمک زیادی می‌کند. برگشت را به عصر دیرهنگام یا تاریکی موکول نکنید. اگر بار سنگین دارید، برای برگشت هم از حمل بار محلی استفاده کنید تا فشار مسیر کمتر شود.</p>

<h2>امکانات مسیر و اطراف دریاچه</h2>
<p>امکانات مسیر محدود است و نباید مثل مقصد شهری روی آن حساب کرد. در طول مسیر چشمه‌هایی وجود دارد، اما بهتر است آب را تصفیه کنید یا دست‌کم قرص/فیلتر همراه داشته باشید. در برخی نقاط مسیر یا نزدیک گردنه‌ها ممکن است سرویس بهداشتی یا سکوهای استراحت وجود داشته باشد، اما دائمی و قابل اتکا فرض نکنید.</p>
<p>در فصل‌های پرتردد، نزدیک دریاچه معمولاً امکاناتی مانند سرویس بهداشتی، کمپ محیط‌زیست، پاسگاه نیروی انتظامی و پایگاه هلال احمر یا امداد برقرار می‌شود. با این حال، مهم‌ترین نکته این است: اطراف دریاچه فروشگاه قابل اتکا ندارد. خوراک، آب کافی، چراغ، باتری، لباس گرم، چادر، کیسه‌خواب، داروهای شخصی، کیسه زباله و وسایل آشپزی سبک را از دورود یا پیش از شروع مسیر تهیه کنید.</p>

<h2>خطرات و نکات ایمنی حیاتی</h2>
<ul>
<li><strong>گم‌شدن:</strong> مسیرهای مالرو انشعاب دارند و آنتن‌دهی موبایل در بخش‌هایی، مخصوصاً کیلومترهای آخر، ممکن است قطع شود. نقشه آفلاین و راهنمای آشنا به مسیر ضروری است.</li>
<li><strong>هوا:</strong> هوای کوهستان سریع تغییر می‌کند. حتی در تابستان، لباس گرم، بادگیر یا بارانی سبک و پوشش مناسب شب لازم است.</li>
<li><strong>گرما و کم‌آبی:</strong> مسیر تابستانی زیر آفتاب می‌تواند بسیار گرم شود. آب کافی، الکترولیت سبک و برنامه توقف داشته باشید.</li>
<li><strong>حیات‌وحش:</strong> اشترانکوه منطقه حفاظت‌شده است. احتمال مشاهده جانوران وحشی وجود دارد؛ نزدیک‌شدن، غذا دادن، تعقیب یا عکاسی بی‌فاصله خطرناک و غیراخلاقی است.</li>
<li><strong>بار سنگین:</strong> کوله سنگین، مخصوصاً در برگشت و گردنه‌ها، آسیب‌زا می‌شود. حمل بار با قاطر محلی برای بسیاری از مسافران انتخاب ایمن‌تری است.</li>
<li><strong>حرکت شبانه:</strong> اگر مسیر را نمی‌شناسید، حرکت شبانه یا برگشت در تاریکی را برنامه‌ریزی نکنید.</li>
<li><strong>تنها رفتن:</strong> این مسیر برای سفر تنها، مخصوصاً برای بار اول، مناسب نیست.</li>
</ul>

<h2>بهترین زمان سفر به دریاچه گهر</h2>
<p>برای کمپ و پیمایش ایمن‌تر، تیر تا شهریور معمولاً انتخاب رایج‌تری است؛ هوا پایدارتر است و مسیر برای گروه‌های بیشتری قابل‌پیمایش می‌شود. با این حال، اواخر اردیبهشت و خرداد از نظر طبیعت، گل‌ها و سرسبزی مسیر بسیار زیباست؛ فقط باید وضعیت برفاب، بارندگی، مجوزها و شرایط محیط‌زیست را بررسی کنید. پاییز می‌تواند خلوت‌تر و عکاسانه‌تر باشد، اما شب‌ها سردتر می‌شود و روز کوتاه‌تر است.</p>
<p>زمستان و روزهای بارانی برای گردشگر عمومی توصیه نمی‌شود. اگر تجربه کوهستان زمستانی ندارید، مسیر گهر را در فصل سرد به برنامه‌ای تخصصی تبدیل نکنید.</p>

<h2>برنامه پیشنهادی دو روزه از دورود</h2>
<h3>روز اول</h3>
<p>صبح زود از دورود به سمت چشمه خیه/خرم حرکت کنید. بعد از ثبت ورود، آماده‌سازی کوله و تقسیم بار، پیمایش را شروع کنید. در مسیر، توقف‌های کوتاه اما منظم داشته باشید و انرژی را برای گردنه پنبه‌کار نگه دارید. عصر به دریاچه برسید، محل کمپ را با دقت انتخاب کنید، زباله‌ها را جمع‌وجور نگه دارید و قبل از تاریکی کامل، آب، غذا و چادر را آماده کنید.</p>

<h3>روز دوم</h3>
<p>صبح زود برای تماشای نور روی دریاچه و عکاسی بیدار شوید. اگر قصد رفتن به ساحل مقابل یا دیدن گهر کوچک را دارید، زمان و توان برگشت را حساب کنید. پیش از ظهر کمپ را جمع کنید و برگشت را به ساعت‌های خیلی گرم یا تاریکی نیندازید. در مسیر برگشت، زانوها و کم‌آبی را جدی بگیرید.</p>

<h2>این مسیر برای چه کسانی مناسب نیست؟</h2>
<p>مسیر دورود به گهر برای افراد آماتور بدون تجربه پیاده‌روی طولانی، کودکان خردسال، سالمندان، افراد دارای مشکل قلبی، تنفسی یا زانو، و کسانی که تجهیزات پایه ندارند مناسب نیست. اگر تجربه کوه‌پیمایی ندارید، بهتر است ابتدا مسیرهای سبک‌تر دورود مثل باباهور، بخش‌هایی از دره اسپر یا مقصدهای نزدیک‌تر را امتحان کنید و بعد برای گهر برنامه بگذارید.</p>

<h2>چک‌لیست وسایل ضروری</h2>
<ul>
<li>کفش کوه‌پیمایی مناسب و جوراب اضافه</li>
<li>کوله سبک و تنظیم‌شده</li>
<li>آب کافی، فیلتر یا قرص تصفیه آب</li>
<li>غذای سبک و پرانرژی برای دو روز</li>
<li>چادر، کیسه‌خواب و زیرانداز مناسب فصل</li>
<li>لباس گرم، بادگیر یا بارانی سبک</li>
<li>چراغ پیشانی، باتری اضافه و پاوربانک</li>
<li>نقشه آفلاین و ترجیحاً GPS</li>
<li>کلاه، عینک آفتابی، ضدآفتاب</li>
<li>کیسه زباله، داروهای شخصی و کمک‌های اولیه</li>
</ul>

<h2>نکات محیط‌زیستی</h2>
<p>گهر به خاطر سختی دسترسی، تا حدی از فشار تخریب شهری دور مانده است؛ اما همین بکر بودن با زباله، آتش، صدای بلند، ورود بی‌ضابطه به حریم آب و بی‌توجهی به حیات‌وحش از بین می‌رود. در منطقه حفاظت‌شده، شکار، آزار جانوران، رهاکردن زباله، آسیب به پوشش گیاهی و روشن‌کردن آتش بی‌احتیاط، فقط خطای فردی نیست؛ آینده مقصد را خراب می‌کند.</p>
<p>قاعده ساده است: هیچ نشانی از حضور خود باقی نگذارید، جز ردپایی که آن هم با اولین باد محو شود.</p>

<h2>لینک‌های داخلی پیشنهادی</h2>
<ul>
<li><a href="/city/dorud/">صفحه شهرستان دورود</a></li>
<li><a href="/province/lorestan/">صفحه استان لرستان</a></li>
<li><a href="/attraction/bisheh-waterfall-dorud/">آبشار بیشه دورود</a></li>
<li><a href="/attraction/nigah-valley-dorud/">دره نی‌گاه دورود</a></li>
<li><a href="/attraction/oshtorankuh/">اشترانکوه</a></li>
<li><a href="/attraction/ab-garmeh-waterfall-dorud/">آبشار آب‌گرمه دورود</a></li>
<li><a href="/attraction/babahur-forest-park-dorud/">پارک جنگلی باباهور</a></li>
<li><a href="/attraction/time-spring-cave-dorud/">غار چشمه وقت ساعت دورود</a></li>
<li><a href="/attraction/azgan-wetland-dorud/">تالاب ازگن دورود</a></li>
<li><a href="/city/azna/">شهرستان ازنا</a></li>
<li><a href="/city/aligudarz/">شهرستان الیگودرز</a></li>
<li><a href="/city/khorramabad/">خرم‌آباد</a></li>
</ul>

<h2>سوالات متداول</h2>
<h3>دریاچه گهر کجاست؟</h3>
<p>دریاچه گهر در استان لرستان، جنوب شرقی دورود و در منطقه حفاظت‌شده اشترانکوه قرار دارد. مسیر رایج سفر از دورود به سمت چشمه خیه/خرم و سپس پیمایش کوهستانی تا دریاچه است.</p>
<h3>مسیر دورود تا دریاچه گهر چقدر پیاده‌روی دارد؟</h3>
<p>بسته به نقطه شروع و شیوه اندازه‌گیری مسیر، پیمایش از چشمه خیه/خرم تا دریاچه حدود ۱۳ تا ۱۸ کیلومتر گزارش شده است. برای بیشتر مسافران، رفت بین ۴ تا ۸ ساعت زمان می‌برد.</p>
<h3>سخت‌ترین بخش مسیر گهر از دورود کجاست؟</h3>
<p>گردنه پنبه‌کار معمولاً سخت‌ترین بخش مسیر معرفی می‌شود؛ شیب، آفتاب، فرود و صعود مسیر در این محدوده می‌تواند برای افراد کم‌تجربه فرساینده باشد.</p>
<h3>آیا در اطراف دریاچه گهر فروشگاه وجود دارد؟</h3>
<p>خیر، نباید روی فروشگاه یا خرید خوراکی کنار دریاچه حساب کرد. خوراک، آب، سوخت، کیسه زباله و وسایل کمپینگ باید از دورود یا پیش از شروع مسیر تهیه شود.</p>
<h3>بهترین زمان سفر به دریاچه گهر چه زمانی است؟</h3>
<p>برای پیمایش و کمپ، معمولاً تابستان و به‌ویژه تیر تا شهریور انتخاب رایج‌تری است. اواخر بهار نیز از نظر طبیعت و گل‌ها زیباست، اما باید وضعیت مسیر، برفاب، مجوز و هوا بررسی شود.</p>
<h3>آیا مسیر دریاچه گهر برای کودکان و سالمندان مناسب است؟</h3>
<p>مسیر دورود به گهر چالش‌برانگیز است و برای کودکان خردسال، سالمندان، افراد کم‌آمادگی یا کسانی که تجربه پیمایش کوهستان ندارند توصیه نمی‌شود.</p>
<h3>آیا برای مسیر گهر راهنمای محلی لازم است؟</h3>
<p>برای اولین سفر، راهنمای محلی یا همراه باتجربه بسیار توصیه می‌شود؛ مسیر انشعاب دارد، در بخش‌هایی آنتن‌دهی قطع می‌شود و تغییر هوا می‌تواند تصمیم‌گیری را سخت کند.</p>
<h3>امکانات کنار دریاچه گهر چیست؟</h3>
<p>در فصل‌های پرتردد معمولاً امکاناتی مانند سرویس بهداشتی، کمپ محیط‌زیست، پایگاه امدادی یا انتظامی و خدمات محدود فصلی برقرار است؛ اما این امکانات دائمی و جایگزین آمادگی شخصی نیستند.</p>
<h3>آیا می‌توان کنار گهر کمپ زد؟</h3>
<p>کمپ‌زدن در محدوده‌های مجاز و با رعایت مقررات محیط‌زیست انجام می‌شود. چادر، کیسه‌خواب، لباس گرم، چراغ، کیسه زباله و نگهداری امن غذا دور از چادر ضروری است.</p>
<h3>مهم‌ترین خطرهای مسیر گهر چیست؟</h3>
<p>گرما و کم‌آبی، تغییر ناگهانی هوا، گم‌شدن در انشعاب‌ها، قطع آنتن، سنگلاخ و شیب، حیات‌وحش و برگشت زیر آفتاب از خطرهای مهم مسیر هستند.</p>

<h2>منابع</h2>
<ul>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%AF%D8%B1%DB%8C%D8%A7%DA%86%D9%87-%DB%8C-%DA%AF%D9%87%D8%B1" target="_blank" rel="noopener">ویزیت ایران: دریاچه گهر</a></li>
<li><a href="https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF" target="_blank" rel="noopener">ویزیت ایران: دورود</a></li>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%BA%D8%A7%D8%B1-%DA%86%D8%B4%D9%85%D9%87-%D9%88%D9%82%D8%AA%D9%90-%D8%B3%D8%A7%D8%B9%D8%AA-%D8%AF%D9%88%D8%B1%D9%88%D8%AF" target="_blank" rel="noopener">ویزیت ایران: غار چشمه وقت ساعت دورود</a></li>
<li><a href="https://doroud.farhang.gov.ir/fa/tabiat/gahar" target="_blank" rel="noopener">اداره فرهنگ و ارشاد اسلامی دورود: دریاچه گهر</a></li>
<li><a href="http://dorood-gov.ir/index.php/2020-11-14-07-44-58/87-2022-01-14-12-59-01" target="_blank" rel="noopener">فرمانداری دورود: دریاچه گهر دورود</a></li>
<li><a href="https://www.kojaro.com/dorud/" target="_blank" rel="noopener">کجارو: جاهای دیدنی دورود</a></li>
<li><a href="https://www.karnaval.ir/things-to-do/gahar-lake-dorud" target="_blank" rel="noopener">کارناوال: دریاچه گهر دورود</a></li>
</ul>
HTML;
	}
}

SA_Gahar_Lake_Featured_View_Importer::init();
