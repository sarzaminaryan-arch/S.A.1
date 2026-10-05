<?php
/**
 * Plugin Name: Sarzamin Aryan — Dorud City Article Importer
 * Description: درون‌ریز مقاله شهرستان دورود لرستان برای قالب سرزمین آریان؛ متن بازنویسی‌شده، فیلدهای شناسنامه، سئو، Rank Math، FAQ، منابع و تصویر شاخص را می‌سازد/به‌روزرسانی می‌کند.
 * Version: 1.0.0
 * Author: Sarzamin Aryan
 * License: GPLv2 or later
 * Text Domain: sa-dorud-city-importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_Dorud_City_Importer {
	const VERSION     = '1.0.0';
	const ACTION      = 'sa_dorud_city_import';
	const NONCE       = 'sa_dorud_city_import_nonce';
	const CITY_SLUG   = 'dorud';
	const PROV_SLUG   = 'lorestan';
	const IMAGE_META  = '_sa_dorud_featured_image';
	const IMAGE_FILE  = 'dorud-lorestan-featured-1200x600.webp';
	const OPTION_LAST = 'sa_dorud_city_importer_last_post_id';

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
		array_unshift( $links, '<a href="' . esc_url( $url ) . '"><strong>درون‌ریزی مقاله دورود</strong></a>' );
		return $links;
	}

	public static function maybe_run_import() {
		if ( ! is_admin() || ! isset( $_GET[ self::ACTION ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'برای اجرای این درون‌ریز دسترسی کافی ندارید.', 'sa-dorud-city-importer' ) );
		}
		check_admin_referer( self::ACTION, self::NONCE );

		$result = self::import();
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'sa-dorud-import' => 'error',
						'sa-dorud-msg'    => rawurlencode( $result->get_error_message() ),
					),
					admin_url( 'plugins.php' )
				)
			);
			exit;
		}

		update_option( self::OPTION_LAST, absint( $result ), false );
		wp_safe_redirect( add_query_arg( 'sa-dorud-import', 'success', get_edit_post_link( $result, 'raw' ) ) );
		exit;
	}

	public static function admin_notices() {
		if ( ! isset( $_GET['sa-dorud-import'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( 'success' === $_GET['sa-dorud-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>مقاله شهرستان دورود با موفقیت ساخته/به‌روزرسانی شد. متن، سئو، Rank Math، FAQ، منابع و تصویر شاخص آماده بررسی و انتشار است.</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'error' === $_GET['sa-dorud-import'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$msg = isset( $_GET['sa-dorud-msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sa-dorud-msg'] ) ) : 'خطای نامشخص';
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function import() {
		if ( ! post_type_exists( 'city' ) || ! post_type_exists( 'province' ) ) {
			return new WP_Error( 'missing_cpts', 'نوع محتوای شهر/استان پیدا نشد. ابتدا قالب فرزند سرزمین آریان را فعال کنید.' );
		}

		$province_id = self::ensure_province();
		if ( is_wp_error( $province_id ) ) {
			return $province_id;
		}

		$city_id = self::ensure_city( $province_id );
		if ( is_wp_error( $city_id ) ) {
			return $city_id;
		}

		// Fill blocking metadata before updating a published post, so the publish gate does not demote it.
		self::update_meta( $city_id, $province_id );
		self::assign_terms( $city_id, $province_id );
		$image_id = self::attach_featured_image( $city_id );
		if ( is_wp_error( $image_id ) ) {
			return $image_id;
		}

		$postarr = array(
			'ID'           => $city_id,
			'post_type'    => 'city',
			'post_name'    => self::CITY_SLUG,
			'post_title'   => 'دورود',
			'post_excerpt' => 'دورود در شرق لرستان و دامنه زاگرس، با دریاچه گهر، اشترانکوه، رود سزار، آبشار بیشه، تله‌زنگ، آب‌گرمه، دره نی‌گاه و غارهای کوهستانی، یکی از فشرده‌ترین مقصدهای طبیعت‌گردی ایران است.',
			'post_content' => self::article_content(),
		);
		$ok      = wp_update_post( $postarr, true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}

		if ( function_exists( 'sa_sync_relations' ) ) {
			sa_sync_relations( $city_id, 'city' );
		}
		if ( function_exists( 'sa_flush_relation_cache' ) ) {
			sa_flush_relation_cache( $city_id, 'city' );
		}

		return $city_id;
	}

	private static function ensure_province() {
		$post_id = self::find_post_id( 'province', self::PROV_SLUG, 'لرستان' );
		if ( $post_id ) {
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'province',
				'post_status'  => 'draft',
				'post_name'    => self::PROV_SLUG,
				'post_title'   => 'لرستان',
				'post_excerpt' => 'استان لرستان در غرب ایران و در پهنه زاگرس قرار دارد.',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		self::ensure_term( 'province_tax', self::PROV_SLUG, 'لرستان' );
		wp_set_object_terms( $post_id, self::PROV_SLUG, 'province_tax', false );
		if ( function_exists( 'sa_sync_relations' ) ) {
			sa_sync_relations( $post_id, 'province' );
		}
		return absint( $post_id );
	}

	private static function ensure_city( $province_id ) {
		$post_id = self::find_post_id( 'city', self::CITY_SLUG, 'دورود' );
		if ( ! $post_id ) {
			$post_id = self::find_post_id( 'city', self::CITY_SLUG, 'شهرستان دورود' );
		}
		if ( $post_id ) {
			return $post_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'city',
				'post_status' => 'draft',
				'post_name'   => self::CITY_SLUG,
				'post_title'  => 'دورود',
				'meta_input'  => array(
					'sa_province_id' => absint( $province_id ),
				),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
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

	private static function update_meta( $post_id, $province_id ) {
		$meta = array(
			'sa_province_id'       => absint( $province_id ),
			'sa_city_population'   => '174507',
			'sa_city_elevation'    => '1457',
			'sa_city_latitude'     => '33.5270',
			'sa_city_longitude'    => '49.1199',
			'sa_access_air'        => 'دورود فرودگاه مسافری فعال ندارد؛ برای سفر هوایی معمولاً باید از فرودگاه‌های نزدیک‌تر مانند خرم‌آباد یا شهرهای بزرگ اطراف استفاده و ادامه مسیر را زمینی طی کرد.',
			'sa_access_rail'       => 'راه‌آهن سراسری تهران ـ خوزستان از دورود می‌گذرد. قطار برای مسیرهای کوهستانی و دیدنی اطراف، به‌ویژه بیشه و تله‌زنگ، بخش مهمی از تجربه سفر است؛ زمان حرکت قطارها باید همان روز بررسی شود.',
			'sa_access_road'       => 'دورود در شرق لرستان و در ارتباط جاده‌ای با محورهای خرم‌آباد، بروجرد، ازنا و الیگودرز قرار دارد. مسیرهای فرعی کوهستانی، به‌خصوص برای آبشارها و روستاهای دورتر، در بارندگی و زمستان نیازمند استعلام محلی‌اند.',
			'sa_google_map_url'    => 'https://www.google.com/maps/search/?api=1&query=Dorud%2C%20Lorestan%2C%20Iran',
			'sa_seo_title'         => 'دورود لرستان؛ راهنمای جاهای دیدنی، دریاچه گهر و آبشارها',
			'sa_seo_description'   => 'راهنمای کامل شهرستان دورود لرستان؛ معرفی دریاچه گهر، آبشار بیشه، شوی، آب‌گرمه، دره نی‌گاه، غارها، پارک‌ها، مسیر دسترسی، فصل سفر و نکات ایمنی.',
			'sa_focus_keyword'     => 'جاهای دیدنی دورود',
			'sa_og_title'          => 'دورود لرستان؛ پایتخت طبیعت آبشارها و دریاچه گهر',
			'sa_og_description'    => 'از دریاچه گهر و اشترانکوه تا آبشارهای بیشه، تله‌زنگ، آب‌گرمه و دره نی‌گاه؛ راهنمای بازنویسی‌شده سفر به دورود در سرزمین آریان.',
			'sa_sources'           => self::sources_text(),
			'sa_facts_checked'     => gmdate( 'Y-m-d' ),
			'rank_math_title'       => 'دورود لرستان؛ راهنمای جاهای دیدنی، دریاچه گهر و آبشارها',
			'rank_math_description' => 'راهنمای کامل شهرستان دورود لرستان؛ معرفی دریاچه گهر، آبشار بیشه، شوی، آب‌گرمه، دره نی‌گاه، غارها، پارک‌ها، مسیر دسترسی، فصل سفر و نکات ایمنی.',
			'rank_math_focus_keyword' => 'جاهای دیدنی دورود',
			'rank_math_facebook_title' => 'دورود لرستان؛ پایتخت طبیعت آبشارها و دریاچه گهر',
			'rank_math_facebook_description' => 'از دریاچه گهر و اشترانکوه تا آبشارهای بیشه، تله‌زنگ، آب‌گرمه و دره نی‌گاه؛ راهنمای سفر به دورود در سرزمین آریان.',
			'rank_math_twitter_title' => 'دورود لرستان؛ راهنمای جاهای دیدنی و طبیعت‌گردی',
			'rank_math_twitter_description' => 'راهنمای بازنویسی‌شده دورود با فهرست آبشارها، دریاچه گهر، دره‌ها، غارها، پارک‌ها، مسیر دسترسی و نکات ایمنی.',
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		$faq = array(
			array( 'q' => 'دورود کجاست؟', 'a' => 'دورود در شرق استان لرستان، در دامنه زاگرس و نزدیک اشترانکوه قرار دارد و از مسیر راه‌آهن سراسری و جاده‌های لرستان قابل دسترسی است.' ),
			array( 'q' => 'معروف‌ترین جاذبه‌های دورود کدام‌اند؟', 'a' => 'دریاچه گهر، آبشار بیشه، آبشار شوی یا تله‌زنگ، آبشار آب‌گرمه، دره نی‌گاه، دره اسپر، تالاب ازگن، کوه قارون، غار وقت ساعت و پارک جنگلی باباهور از نام‌های شاخص دورود هستند.' ),
			array( 'q' => 'بهترین فصل سفر به دورود چه زمانی است؟', 'a' => 'بهار و اوایل تابستان برای آبشارها، دره‌ها و طبیعت سرسبز مناسب‌تر است. پاییز برای عکاسی و سفر آرام‌تر جذاب است و زمستان بیشتر برای طبیعت‌گردهای مجهز توصیه می‌شود.' ),
			array( 'q' => 'آیا مسیر دریاچه گهر آسان است؟', 'a' => 'مسیر رایج گهر از دورود تا چشمه خیه جاده دارد و ادامه مسیر پیاده‌روی/مالرو است؛ بنابراین برای سفر خانوادگی باید توان پیاده‌روی، فصل، مجوزها و وضعیت محیط‌زیست بررسی شود.' ),
			array( 'q' => 'آیا آبشار شوی در شهرستان دورود است؟', 'a' => 'شوی یا تله‌زنگ از نظر گردشگری با مسیر ریلی و جنوب دورود شناخته می‌شود، اما در منابع رسمی نسبت اداری آن با شمال دزفول/مرز لرستان هم مطرح است؛ پیش از تولید محتوای مستقل باید موقعیت اداری دقیق بررسی شود.' ),
			array( 'q' => 'کدام دیدنی‌های دورود برای سفر کوتاه مناسب‌ترند؟', 'a' => 'برای سفر کوتاه، پارک جنگلی باباهور، مسیرهای نزدیک شهر، آبشار بیشه با قطار و برخی چشم‌اندازهای اطراف سیلاخور کم‌دردسرترند. مقصدهایی مثل گهر، نی‌گاه و شوی زمان و آمادگی بیشتری می‌خواهند.' ),
			array( 'q' => 'آیا بازدید از غارهای دورود نیاز به راهنما دارد؟', 'a' => 'بله. غارهایی مثل منو، وقت ساعت و مرده‌ها/مردگان بهتر است با راهنمای محلی، چراغ، کفش مناسب و آمادگی فنی بازدید شوند.' ),
			array( 'q' => 'برای عکاسی در دورود کجا بهتر است؟', 'a' => 'دریاچه گهر، آبشار بیشه، دره نی‌گاه، دره اسپر، کوه قارون، تالاب ازگن در فصل پرآبی و پارک جنگلی باباهور از سوژه‌های مناسب عکاسی طبیعت‌اند.' ),
			array( 'q' => 'دورود برای خانواده‌ها مناسب است؟', 'a' => 'بله، اما باید مقصد را درست انتخاب کرد. پارک‌ها و مسیرهای نزدیک شهر خانوادگی‌ترند؛ آبشارهای دورافتاده، غارها و مسیرهای دره‌ای برای کودکان یا سالمندان نیازمند احتیاط بیشتری هستند.' ),
			array( 'q' => 'قبل از رفتن به آبشارها و دره‌های دورود چه چیزهایی را بررسی کنیم؟', 'a' => 'وضعیت آب‌وهوا، بارندگی، مسیر دسترسی، آنتن‌دهی، ساعت قطار، امکان بازگشت قبل از تاریکی، نیاز به راهنمای محلی و تجهیزات کفش و لباس را حتماً بررسی کنید.' ),
		);
		update_post_meta( $post_id, 'sa_faq', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}

	private static function assign_terms( $post_id, $province_id ) {
		self::ensure_term( 'province_tax', self::PROV_SLUG, 'لرستان' );
		wp_set_object_terms( $post_id, self::PROV_SLUG, 'province_tax', false );
		self::ensure_and_set_terms( $post_id, 'travel_season', array( 'spring' => 'بهار', 'summer' => 'تابستان', 'autumn' => 'پاییز' ) );
		update_post_meta( $post_id, 'sa_province_id', absint( $province_id ) );
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

	private static function attach_featured_image( $post_id ) {
		$existing = absint( get_post_meta( $post_id, self::IMAGE_META, true ) );
		if ( $existing && get_post( $existing ) ) {
			set_post_thumbnail( $post_id, $existing );
			return $existing;
		}

		$source = plugin_dir_path( __FILE__ ) . 'assets/' . self::IMAGE_FILE;
		if ( ! file_exists( $source ) ) {
			return new WP_Error( 'missing_image_asset', 'فایل تصویر شاخص دورود داخل افزونه پیدا نشد.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( self::IMAGE_FILE );
		if ( ! $tmp || ! copy( $source, $tmp ) ) {
			return new WP_Error( 'image_copy_failed', 'کپی تصویر شاخص دورود برای بارگذاری انجام نشد.' );
		}

		$file_array = array(
			'name'     => self::IMAGE_FILE,
			'tmp_name' => $tmp,
		);
		$attach_id  = media_handle_sideload( $file_array, $post_id, 'تصویر شاخص نمادین دورود لرستان' );
		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attach_id;
		}

		wp_update_post(
			array(
				'ID'           => $attach_id,
				'post_title'   => 'تصویر شاخص دورود لرستان',
				'post_excerpt' => 'طرح نمادین کوه، رود و مسیر ریلی برای مقاله شهرستان دورود لرستان.',
			)
		);
		update_post_meta( $attach_id, '_wp_attachment_image_alt', 'طرح نمادین شهرستان دورود لرستان با کوهستان زاگرس، رودخانه و مسیر ریلی' );
		update_post_meta( $post_id, self::IMAGE_META, $attach_id );
		set_post_thumbnail( $post_id, $attach_id );
		return absint( $attach_id );
	}

	private static function sources_text() {
		return implode(
			"\n",
			array(
				'ویزیت ایران، دورود | https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF',
				'ویزیت ایران، دریاچه گهر | https://www.visitiran.ir/fa/attraction/%D8%AF%D8%B1%DB%8C%D8%A7%DA%86%D9%87-%DB%8C-%DA%AF%D9%87%D8%B1',
				'ویزیت ایران، آبشار بیشه | https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87',
				'ویزیت ایران، آبشار شوی (تله زنگ) | https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%B4%D9%88%DB%8C-%D8%AA%D9%84%D9%87-%D8%B2%D9%86%DA%AF',
				'ویزیت ایران، غار چشمه وقت ساعت دورود | https://www.visitiran.ir/fa/attraction/%D8%BA%D8%A7%D8%B1-%DA%86%D8%B4%D9%85%D9%87-%D9%88%D9%82%D8%AA%D9%90-%D8%B3%D8%A7%D8%B9%D8%AA-%D8%AF%D9%88%D8%B1%D9%88%D8%AF',
				'ویزیت ایران، استان لرستان | https://www.visitiran.ir/fa/province/%D8%A7%D8%B3%D8%AA%D8%A7%D9%86-%D9%84%D8%B1%D8%B3%D8%AA%D8%A7%D9%86',
				'کجارو، جاهای دیدنی دورود | https://www.kojaro.com/dorud/',
			)
		);
	}

	private static function article_content() {
		return <<<'HTML'
<p>دورود در شرق لرستان، جایی میان دشت سیلاخور، دامنه‌های اشترانکوه، رودخانه سزار و مسیر تاریخی راه‌آهن، یکی از فشرده‌ترین مقصدهای طبیعت‌گردی زاگرس است. نام دورود برای بسیاری از مسافران با <a href="/attraction/gahar-lake-dorud/">دریاچه گهر</a>، <a href="/attraction/bisheh-waterfall-dorud/">آبشار بیشه</a>، مسیر قطار، دره‌های سبز و آبشارهای پرآب گره خورده است. این مقاله، فهرست جاذبه‌های دورود را بازنویسی و مرتب می‌کند تا خواننده فقط با یک فهرست خام روبه‌رو نباشد؛ بداند کجا برای سفر خانوادگی مناسب‌تر است، کدام مقصد راهنما می‌خواهد، و کدام نقطه هنوز نیازمند پرس‌وجوی محلی قبل از حرکت است.</p>

<h2>دورود چرا مهم است؟</h2>
<p>در کمتر شهرستانی می‌توان دریاچه کوهستانی، آبشارهای بزرگ، دره‌های عمیق، غار، تالاب فصلی، پارک جنگلی، روستاهای ییلاقی و مسیر ریلی کوهستان را کنار هم دید. دورود همین تنوع را دارد. از یک طرف، شهر با راه‌آهن و جاده به محورهای <a href="/province/lorestan/">لرستان</a> وصل است؛ از طرف دیگر، با کمی فاصله گرفتن از شهر، چشم‌انداز کاملاً عوض می‌شود: کوه‌های بلند، رود، بلوط، آبشار و مسیرهای پیاده‌روی.</p>
<p>به همین دلیل، دورود را نباید فقط با یک مقصد تعریف کرد. اگر وقت کم دارید، می‌توانید سراغ بیشه، باباهور یا مسیرهای نزدیک شهر بروید. اگر اهل طبیعت‌گردی جدی هستید، گهر، نی‌گاه، شوی و غارها برنامه‌ای جداگانه می‌خواهند. اگر هدف شما عکاسی است، بهار، اوایل تابستان و پاییز بهترین قاب‌ها را می‌سازند.</p>

<h2>شناخت سریع جاذبه‌های دورود</h2>
<ul>
<li><strong>شاخص‌ترین مقصد کوهستانی:</strong> دریاچه گهر در منطقه حفاظت‌شده اشترانکوه.</li>
<li><strong>شاخص‌ترین آبشارهای سفر:</strong> بیشه، شوی/تله‌زنگ، آب‌گرمه، ازنادر، حشوید/هشوید و عزیزآباد.</li>
<li><strong>بهترین مسیرهای دره‌ای:</strong> دره نی‌گاه، دره اسپر و دره شوی.</li>
<li><strong>دیدنی‌های غاری و کوهستانی:</strong> غار منو، غار وقت ساعت، غار مرده‌ها/مردگان، کوه قارون و تالاب ازگن.</li>
<li><strong>گزینه‌های نزدیک‌تر برای خانواده:</strong> پارک دانشجو، پارک جنگلی باباهور، بلندی باباهور و مسیرهای سبک اطراف شهر.</li>
</ul>

<h2>دریاچه گهر؛ نگین فیروزه‌ای اشترانکوه</h2>
<p>دریاچه گهر مشهورترین تصویر طبیعی دورود است؛ دریاچه‌ای کوهستانی در قلب منطقه حفاظت‌شده اشترانکوه که در منابع گردشگری با فاصله حدود ۳۵ کیلومتر از جنوب شرقی دورود معرفی می‌شود. ارتفاع دریاچه حدود ۲۳۵۰ متر است و وسعت آن در منابع مختلف نزدیک به ۸۸ تا ۱۰۰ هکتار آمده است. این اختلاف عددی برای کاربر نهایی مهم‌تر از اصل موضوع نیست: گهر یک مقصد جدی طبیعت‌گردی است، نه یک توقف کوتاه کنار جاده.</p>
<p>مسیر رایج دسترسی، از دورود به سمت درب آستانه و چشمه خیه است و بعد از آن پیاده‌روی/مسیر مالرو آغاز می‌شود. در فصل‌های شلوغ، باید وضعیت محیط‌زیست، امکان کمپ، حمل بار، آب‌وهوا و ظرفیت منطقه بررسی شود. برای خانواده‌ها، سفر به گهر زمانی لذت‌بخش است که برنامه رفت‌وبرگشت، کفش مناسب، لباس گرم و توان پیاده‌روی در نظر گرفته شده باشد.</p>

<h2>آبشارهای دورود؛ از بیشه تا مسیرهای بکرتر</h2>
<h3>آبشار بیشه یا پوران</h3>
<p>آبشار بیشه با مسیر ریلی، روستای بیشه، پل و جنگل‌های بلوط یکی از خواناترین مقصدهای لرستان است. در منابع رسمی، فاصله آن حدود ۳۰ کیلومتر از دورود و حدود ۶۵ کیلومتر از خرم‌آباد ذکر شده و ارتفاع آبشار حدود ۵۸ متر آمده است. از نظر روایت سفر، بیشه برای دورود اهمیت ویژه دارد، چون مسیر رسیدن به آن با قطار و ایستگاه راه‌آهن، خودش بخشی از تجربه گردشگری است.</p>

<h3>آبشار شوی یا تله‌زنگ</h3>
<p>شوی، که با نام تله‌زنگ هم شناخته می‌شود، از بزرگ‌ترین و مشهورترین آبشارهای ایران است. در روایت محلی و گردشگری دورود، دسترسی ریلی از محدوده تله‌زنگ و جنوب دورود پررنگ است؛ در عین حال برخی منابع رسمی، موقعیت اداری آن را در شمال دزفول و نزدیک مرز لرستان توضیح می‌دهند. بنابراین در مقاله دورود بهتر است شوی به‌عنوان مقصد مهم مسیر گردشگری دورود معرفی شود، اما برای صفحه مستقل «نمای برتر»، موقعیت اداری آن با دقت بیشتری ثبت شود.</p>

<h3>آبشار آب‌گرمه یا چم‌چیت</h3>
<p>آبشار آب‌گرمه، که با چم‌چیت هم شناخته می‌شود، آبشاری کمتر شلوغ و نزدیک مسیر رود سزار است. در فهرست محلی، فاصله آن حدود ۲۳ کیلومتر در جنوب غربی دورود آمده و ارتفاع آن بیش از ۲۰ متر و عرض تاج حدود ۱۰ متر ذکر شده است. بهترین زمان بازدید معمولاً از فروردین تا آبان است، اما در روزهای بارانی یا مسیرهای لغزنده باید احتیاط کرد.</p>

<h3>آبشار ازنادر/اذنادر و دره اسپر</h3>
<p>آبشار ازنادر در نزدیکی روستای گردشگری دره اسپر قرار می‌گیرد و معمولاً به‌عنوان آبشاری فصلی و مناسب طبیعت‌گردی سبک‌تر معرفی می‌شود. دره اسپر حدود ۷ کیلومتر با دورود فاصله دارد و به دلیل چشمه‌های دائمی و فصلی، روستا، چشم‌انداز سبز و مسیرهای کوتاه پیاده‌روی، برای کسانی که می‌خواهند از شهر خیلی دور نشوند انتخاب خوبی است.</p>

<h3>آبشار حشوید/هشوید، دوش خرسان و عزیزآباد</h3>
<p>حشوید یا هشوید از آبشارهای بکرتر دورود است و در نزدیکی روستای حشوید و دشت سیلاخور معرفی می‌شود. دوش خرسان در محدوده دره خرس و مسیرهای دره نی‌گاه قرار دارد و برای طبیعت‌گردی با سختی متوسط مناسب‌تر است. آبشار و تنگه عزیزآباد نیز در نزدیکی چالانچولان و حاشیه دشت سیلاخور، با چشمه‌ها و آبشارهای متعدد شناخته می‌شوند. برای این نقاط، راهنمای محلی و بررسی مسیر، مخصوصاً بعد از بارندگی، ضروری است.</p>

<h2>دره‌ها، روستاها و مسیرهای بکر</h2>
<p>دره نی‌گاه، که گاهی با املای نگار هم دیده می‌شود، از مهم‌ترین مسیرهای بکر دورود است. این دره با صخره‌های بلند، آب زلال و ارتباط طبیعی با جریان‌های سرچشمه‌گرفته از گهر، برای طبیعت‌گردی جدی‌تر مناسب است. طول مسیر در برخی روایت‌ها حدود ۲۰ کیلومتر ذکر می‌شود و ورود بی‌برنامه به آن توصیه نمی‌شود.</p>
<p>دره شوی، روستای شوی و محدوده اطراف آن برای شناخت پیوند راه‌آهن، رودخانه، آبشار و زیست بومی زاگرس اهمیت دارد. روستای اندیکان، بیشه لنج‌آباد و منطقه/روستای رنگیه نیز در فهرست‌های محلی به‌عنوان نقاط طبیعی و کم‌تراکم آمده‌اند؛ اما برای آن‌ها بهتر است پیش از تولید صفحه مستقل، اطلاعات میدانی، مسیر دقیق و وضعیت خدمات بررسی شود.</p>

<h2>کوه‌ها، تالاب‌ها و غارهای دورود</h2>
<p>کوه قارون یکی از نشانه‌های طبیعی جنوب دورود است؛ کوهی منفرد و دیدنی که در فهرست محلی با ارتفاع حدود ۲۵۵۰ متر و بهترین زمان صعود در نیمه فروردین معرفی شده است. تالاب ازگن یا قارون نیز تالابی فصلی در دامنه کوه پریز و کوه قارون است که معمولاً تا اواخر بهار آب دارد و سپس خشک می‌شود.</p>
<p>غار منو نزدیک روستای گورکش، غار وقت ساعت در دامنه جنوبی کوه پریز و نزدیک ایستگاه راه‌آهن چم‌چید، و غار مرده‌ها یا مردگان نزدیک روستای دریژان از غارهای مطرح دورود هستند. نکته مشترک همه این غارها این است که نباید مثل یک مقصد تفریحی ساده با آن‌ها برخورد کرد. چراغ، کفش مناسب، همراه باتجربه، راهنمای محلی و پرهیز از ورود در بارندگی یا تاریکی، حداقل احتیاط لازم است.</p>

<h2>فضاهای شهری و خانوادگی</h2>
<p>همه سفرهای دورود قرار نیست سنگین و کوهستانی باشند. پارک دانشجو در مرکز شهر، با فضای سبز خطی، مسیرهای پیاده‌روی و دسترسی ساده، برای توقف کوتاه، خانواده و استراحت شهری مناسب است. پارک جنگلی باباهور و بلندی باباهور نیز با درختان بلوط، مسیر پیاده‌روی و چشم‌انداز دامنه‌ای، گزینه‌ای نزدیک‌تر برای گردش سبک به‌شمار می‌آیند. سراب رودک، در نزدیکی ایستگاه راه‌آهن رودک، هم می‌تواند در برنامه‌های کوتاه‌تر بررسی شود.</p>

<h2>فهرست مرتب جاذبه‌های دورود</h2>
<table>
<thead><tr><th>نام</th><th>نوع تجربه</th><th>نکته کاربردی</th></tr></thead>
<tbody>
<tr><td>دریاچه گهر</td><td>دریاچه کوهستانی</td><td>نیازمند برنامه‌ریزی، پیاده‌روی و بررسی وضعیت محیط‌زیست</td></tr>
<tr><td>آبشار شوی/تله‌زنگ</td><td>آبشار بزرگ و مسیر ریلی</td><td>مسیر سخت‌تر؛ موقعیت اداری و مسیر دسترسی پیش از حرکت بررسی شود</td></tr>
<tr><td>آبشار آب‌گرمه/چم‌چیت</td><td>آبشار کمترشناخته‌شده</td><td>نزدیک رود سزار؛ بهترین زمان بهار تا پاییز</td></tr>
<tr><td>آبشار ازنادر/اذنادر</td><td>آبشار فصلی</td><td>نزدیک دره اسپر؛ مناسب بازدید سبک‌تر با پرس‌وجوی محلی</td></tr>
<tr><td>آبشار حشوید/هشوید</td><td>آبشار بکر</td><td>نزدیک دشت سیلاخور؛ بهار، مخصوصاً فروردین و اردیبهشت، مناسب‌تر است</td></tr>
<tr><td>دره نی‌گاه/نگار</td><td>دره‌نوردی و طبیعت بکر</td><td>مسیر طولانی‌تر و جدی‌تر؛ راهنما و آمادگی لازم دارد</td></tr>
<tr><td>دره اسپر</td><td>روستا و چشمه</td><td>نزدیک دورود؛ مناسب طبیعت‌گردی کوتاه‌تر</td></tr>
<tr><td>تالاب ازگن/قارون</td><td>تالاب فصلی</td><td>بیشتر تا اواخر بهار آب دارد</td></tr>
<tr><td>کوه قارون</td><td>کوه‌پیمایی</td><td>برای صعود، فصل و توان جسمی را جدی بگیرید</td></tr>
<tr><td>غار منو</td><td>غار طبیعی</td><td>نیازمند راهنما، چراغ و احتیاط فنی</td></tr>
<tr><td>غار وقت ساعت</td><td>غار و چشمه شگفت‌انگیز</td><td>قطع و وصل آب پدیده اصلی آن است؛ مسیر دشوارتر است</td></tr>
<tr><td>غار مرده‌ها/مردگان</td><td>غار تاریخی/طبیعی</td><td>نزدیک دریژان؛ پیاده‌روی و راهنمای محلی توصیه می‌شود</td></tr>
<tr><td>بیشه لنج‌آباد</td><td>روستا/طبیعت کم‌تراکم</td><td>اطلاعات خدمات گردشگری نیازمند بررسی محلی است</td></tr>
<tr><td>آبشار دوش خرسان</td><td>آبشار و مسیر دره‌ای</td><td>سختی متوسط؛ مناسب طبیعت‌گردهای آماده‌تر</td></tr>
<tr><td>آبشار و تنگه عزیزآباد</td><td>تنگه، چشمه و آبشار</td><td>نزدیک چالانچولان و حاشیه دشت سیلاخور</td></tr>
<tr><td>باباهور و پارک جنگلی باباهور</td><td>گردش سبک و خانوادگی</td><td>نزدیک‌تر به شهر؛ مناسب توقف و پیاده‌روی کوتاه</td></tr>
<tr><td>سراب رودک</td><td>سراب و مسیر ریلی</td><td>نزدیک ایستگاه راه‌آهن رودک</td></tr>
<tr><td>آبشار چکان</td><td>آبشار و غار/دیواره</td><td>دسترسی آن با مسیر ریلی و سپس مسیر محلی مطرح می‌شود؛ قبل از حرکت بررسی شود</td></tr>
<tr><td>دره شوی</td><td>دره، روستا و آبشار</td><td>برای مسیرهای طولانی‌تر، زمان بازگشت و راهنما مهم است</td></tr>
<tr><td>روستای اندیکان</td><td>روستای بکر</td><td>برای عکاسی و طبیعت‌گردی سبک، اما با خدمات محدود</td></tr>
<tr><td>پارک دانشجو</td><td>پارک شهری</td><td>مناسب خانواده، پیاده‌روی و توقف کوتاه در شهر</td></tr>
<tr><td>رنگیه</td><td>منطقه/روستای ییلاقی</td><td>برای مسیر دقیق و خدمات، پرس‌وجوی محلی لازم است</td></tr>
</tbody>
</table>

<h2>برنامه پیشنهادی سفر</h2>
<h3>یک روزه</h3>
<p>اگر فقط یک روز وقت دارید، از گزینه‌های نزدیک‌تر شروع کنید: صبح پارک جنگلی باباهور یا مسیرهای اطراف شهر، سپس بسته به فصل و توان، یک مقصد آبی نزدیک‌تر مثل بیشه یا مسیرهای سبک‌تر را انتخاب کنید. در سفر یک‌روزه سراغ چند مقصد دورافتاده نروید؛ زمان بازگشت در کوهستان مهم‌تر از تعداد عکس‌هاست.</p>
<h3>دو روزه</h3>
<p>روز اول را به آبشار بیشه، مسیر ریلی و توقف‌های نزدیک اختصاص دهید. روز دوم، بسته به فصل، بین دره اسپر، آب‌گرمه، تالاب ازگن یا باباهور انتخاب کنید. اگر قصد دارید به نی‌گاه یا شوی بروید، برنامه را سنگین‌تر و با راهنمای محلی ببندید.</p>
<h3>سه روزه یا بیشتر</h3>
<p>برای گهر، نی‌گاه، شوی یا غارها بهتر است سفر سه‌روزه یا بیشتر در نظر بگیرید. این برنامه نیازمند آمادگی بدنی، تجهیزات، بررسی وضعیت محیط‌زیست، قطار، راه و آب‌وهواست. در این حالت، دورود فقط مقصد نیست؛ پایگاه سفر به چند نمای طبیعی زاگرس است.</p>

<h2>نکات ایمنی و مسئولانه</h2>
<p>آبشارهای دورود زیبا هستند، اما سنگ خیس، جریان آب، پرتگاه، تاریکی، مه و نبود آنتن تلفن می‌تواند خطرساز شود. برای غارها و دره‌ها تنها حرکت نکنید. پیش از ورود به مسیرهای بکر، به یک نفر در شهر مقصد و زمان بازگشت را اطلاع دهید. زباله را برگردانید، وارد حریم خانه‌ها و باغ‌ها نشوید و اگر مسیر ظرفیت ندارد، مقصد جایگزین انتخاب کنید.</p>
<p>برخی نام‌ها با املای متفاوت ثبت شده‌اند: ازنادر/اذنادر، حشوید/هشوید، نی‌گاه/نگار و وقت ساعت/وقت و ساعت. در سرزمین آریان، برای جلوگیری از پراکندگی، بهتر است در عنوان اصلی هر صفحه یک نام معیار انتخاب شود و املای دیگر در متن و کلیدواژه‌ها بیاید.</p>

<h2>لینک‌های داخلی پیشنهادی</h2>
<ul>
<li><a href="/province/lorestan/">استان لرستان</a></li>
<li><a href="/city/khorramabad/">خرم‌آباد</a></li>
<li><a href="/city/azna/">ازنا</a></li>
<li><a href="/city/borujerd/">بروجرد</a></li>
<li><a href="/city/aligudarz/">الیگودرز</a></li>
<li><a href="/attraction/bisheh-waterfall-dorud/">آبشار بیشه دورود</a></li>
<li><a href="/attraction/gahar-lake-dorud/">دریاچه گهر دورود</a></li>
<li><a href="/attraction/shevi-waterfall-tele-zang/">آبشار شوی تله‌زنگ</a></li>
<li><a href="/attraction/oshtorankuh/">اشترانکوه</a></li>
<li><a href="/attraction/nigah-valley-dorud/">دره نی‌گاه دورود</a></li>
<li><a href="/attraction/babahur-forest-park-dorud/">پارک جنگلی باباهور</a></li>
<li><a href="/attraction/azgan-wetland-dorud/">تالاب ازگن</a></li>
</ul>

<h2>سوالات متداول</h2>
<h3>دورود کجاست؟</h3>
<p>دورود در شرق استان لرستان، در دامنه زاگرس و نزدیک اشترانکوه قرار دارد و از مسیر راه‌آهن سراسری و جاده‌های لرستان قابل دسترسی است.</p>
<h3>معروف‌ترین جاذبه‌های دورود کدام‌اند؟</h3>
<p>دریاچه گهر، آبشار بیشه، آبشار شوی یا تله‌زنگ، آبشار آب‌گرمه، دره نی‌گاه، دره اسپر، تالاب ازگن، کوه قارون، غار وقت ساعت و پارک جنگلی باباهور از نام‌های شاخص دورود هستند.</p>
<h3>بهترین فصل سفر به دورود چه زمانی است؟</h3>
<p>بهار و اوایل تابستان برای آبشارها، دره‌ها و طبیعت سرسبز مناسب‌تر است. پاییز برای عکاسی و سفر آرام‌تر جذاب است و زمستان بیشتر برای طبیعت‌گردهای مجهز توصیه می‌شود.</p>
<h3>آیا مسیر دریاچه گهر آسان است؟</h3>
<p>مسیر رایج گهر از دورود تا چشمه خیه جاده دارد و ادامه مسیر پیاده‌روی/مالرو است؛ بنابراین برای سفر خانوادگی باید توان پیاده‌روی، فصل، مجوزها و وضعیت محیط‌زیست بررسی شود.</p>
<h3>آیا آبشار شوی در شهرستان دورود است؟</h3>
<p>شوی یا تله‌زنگ از نظر گردشگری با مسیر ریلی و جنوب دورود شناخته می‌شود، اما در منابع رسمی نسبت اداری آن با شمال دزفول/مرز لرستان هم مطرح است؛ پیش از تولید محتوای مستقل باید موقعیت اداری دقیق بررسی شود.</p>
<h3>کدام دیدنی‌های دورود برای سفر کوتاه مناسب‌ترند؟</h3>
<p>برای سفر کوتاه، پارک جنگلی باباهور، مسیرهای نزدیک شهر، آبشار بیشه با قطار و برخی چشم‌اندازهای اطراف سیلاخور کم‌دردسرترند. مقصدهایی مثل گهر، نی‌گاه و شوی زمان و آمادگی بیشتری می‌خواهند.</p>
<h3>آیا بازدید از غارهای دورود نیاز به راهنما دارد؟</h3>
<p>بله. غارهایی مثل منو، وقت ساعت و مرده‌ها/مردگان بهتر است با راهنمای محلی، چراغ، کفش مناسب و آمادگی فنی بازدید شوند.</p>
<h3>برای عکاسی در دورود کجا بهتر است؟</h3>
<p>دریاچه گهر، آبشار بیشه، دره نی‌گاه، دره اسپر، کوه قارون، تالاب ازگن در فصل پرآبی و پارک جنگلی باباهور از سوژه‌های مناسب عکاسی طبیعت‌اند.</p>
<h3>دورود برای خانواده‌ها مناسب است؟</h3>
<p>بله، اما باید مقصد را درست انتخاب کرد. پارک‌ها و مسیرهای نزدیک شهر خانوادگی‌ترند؛ آبشارهای دورافتاده، غارها و مسیرهای دره‌ای برای کودکان یا سالمندان نیازمند احتیاط بیشتری هستند.</p>
<h3>قبل از رفتن به آبشارها و دره‌های دورود چه چیزهایی را بررسی کنیم؟</h3>
<p>وضعیت آب‌وهوا، بارندگی، مسیر دسترسی، آنتن‌دهی، ساعت قطار، امکان بازگشت قبل از تاریکی، نیاز به راهنمای محلی و تجهیزات کفش و لباس را حتماً بررسی کنید.</p>

<h2>منابع</h2>
<ul>
<li><a href="https://www.visitiran.ir/fa/destination/%D8%AF%D9%88%D8%B1%D9%88%D8%AF" target="_blank" rel="noopener">ویزیت ایران: دورود</a></li>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%AF%D8%B1%DB%8C%D8%A7%DA%86%D9%87-%DB%8C-%DA%AF%D9%87%D8%B1" target="_blank" rel="noopener">ویزیت ایران: دریاچه گهر</a></li>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%A8%DB%8C%D8%B4%D9%87" target="_blank" rel="noopener">ویزیت ایران: آبشار بیشه</a></li>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%A2%D8%A8%D8%B4%D8%A7%D8%B1-%D8%B4%D9%88%DB%8C-%D8%AA%D9%84%D9%87-%D8%B2%D9%86%DA%AF" target="_blank" rel="noopener">ویزیت ایران: آبشار شوی/تله‌زنگ</a></li>
<li><a href="https://www.visitiran.ir/fa/attraction/%D8%BA%D8%A7%D8%B1-%DA%86%D8%B4%D9%85%D9%87-%D9%88%D9%82%D8%AA%D9%90-%D8%B3%D8%A7%D8%B9%D8%AA-%D8%AF%D9%88%D8%B1%D9%88%D8%AF" target="_blank" rel="noopener">ویزیت ایران: غار چشمه وقت ساعت دورود</a></li>
<li><a href="https://www.visitiran.ir/fa/province/%D8%A7%D8%B3%D8%AA%D8%A7%D9%86-%D9%84%D8%B1%D8%B3%D8%AA%D8%A7%D9%86" target="_blank" rel="noopener">ویزیت ایران: استان لرستان</a></li>
<li><a href="https://www.kojaro.com/dorud/" target="_blank" rel="noopener">کجارو: جاهای دیدنی دورود</a></li>
</ul>
HTML;
	}
}

SA_Dorud_City_Importer::init();
