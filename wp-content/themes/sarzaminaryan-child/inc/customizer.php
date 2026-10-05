<?php
/**
 * Customizer: hero, home, footer, socials, contact, display switches, publish-gate mode.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register panel/sections/settings.
 *
 * @param WP_Customize_Manager $wp_customize Manager.
 */
function sa_customize_register( $wp_customize ) {
	$wp_customize->add_panel(
		'sa_panel',
		array(
			'title'    => 'سرزمین آریان',
			'priority' => 10,
		)
	);

	$add = function ( $section, $id, $label, $type = 'text', $default = '', $extra = array() ) use ( $wp_customize ) {
		$sanitize = array(
			'text'     => 'sanitize_text_field',
			'textarea' => 'sanitize_textarea_field',
			'url'      => 'esc_url_raw',
			'email'    => 'sanitize_email',
			'checkbox' => 'sa_sanitize_checkbox',
			'select'   => 'sanitize_key',
			'image'    => 'esc_url_raw',
		);
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => $sanitize[ $type ],
				'transport'         => 'refresh',
			)
		);
		if ( 'image' === $type ) {
			$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, $id, array_merge( array( 'label' => $label, 'section' => $section ), $extra ) ) );
		} else {
			$wp_customize->add_control( $id, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $type ), $extra ) );
		}
	};

	// Home / hero.
	$wp_customize->add_section( 'sa_home', array( 'title' => 'صفحه‌ی اول', 'panel' => 'sa_panel' ) );
	$add( 'sa_home', 'sa_hero_title', 'عنوان بزرگ هیرو', 'text', 'ایران را استان به استان بشناسید' );
	$add( 'sa_home', 'sa_hero_subtitle', 'زیرعنوان هیرو', 'textarea', '۳۱ استان، صدها شهر و نمای برتر طبیعت و دیدنی‌های ایران — با اطلاعات دقیق و به‌روز.' );
	$add( 'sa_home', 'sa_hero_image', 'تصویر پس‌زمینه‌ی هیرو (اختیاری)', 'image' );
	$add( 'sa_home', 'sa_home_description', 'توضیحات متای صفحه‌ی اول (۷۰ تا ۱۵۵ کاراکتر)', 'textarea', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و نمای برتر طبیعت‌های بکر و دیدنی‌های ایران با اطلاعات دقیق و به‌روز.' );
	$add( 'sa_home', 'sa_show_stats', 'نمایش آمار (تعداد استان/شهر/نمای برتر…) ', 'checkbox', true );
	$add( 'sa_home', 'sa_home_sections', 'بخش‌های صفحه‌ی اول (به ترتیب، با ویرگول)', 'text', 'provinces,attractions,routes,foods,souvenirs,posts', array( 'description' => 'گزینه‌ها: provinces, cities, attractions, routes, foods, souvenirs, posts' ) );

	// صفحه اصلی (طرح v2) — قالب برگه «صفحه اصلی سرزمین آریان (طرح v2)».
	$wp_customize->add_section( 'sa_home_v2', array( 'title' => 'صفحه اصلی (طرح v2)', 'panel' => 'sa_panel', 'description' => 'تنظیمات دستی صفحه‌ی خانه (برگه‌ی «خانه» با قالب «صفحه اصلی سرزمین آریان (طرح v2)»). لوگو از بخش «هویت سایت → لوگو» قابل تغییر است؛ اگر لوگویی انتخاب نشود لوگوی اصلی سایت استفاده می‌شود.' ) );
	$add( 'sa_home_v2', 'sa_home_slogan', 'شعار سایت (حماسی — زیر نام سایت، هدر و فوتر)', 'text', 'چو ایران نباشد، تن من مباد' );
	$add( 'sa_home_v2', 'sa_hero_text', 'متن معرفی زیر شعار', 'textarea', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و نمای برتر طبیعت ایران.' );
	$add( 'sa_home_v2', 'sa_search_placeholder', 'متن جایگزین جعبه‌ی جست‌وجو', 'text', 'استان، شهر یا نمای برتر…' );
	$add( 'sa_home_v2', 'sa_stat1_num', 'آمار ۱ — عدد', 'text', '31' );
	$add( 'sa_home_v2', 'sa_stat1_label', 'آمار ۱ — برچسب', 'text', 'استان' );
	$add( 'sa_home_v2', 'sa_stat2_num', 'آمار ۲ — عدد (انگلیسی برای شمارنده)', 'text', '419' );
	$add( 'sa_home_v2', 'sa_stat2_suffix', 'آمار ۲ — پسوند (+ یا خالی)', 'text', '+' );
	$add( 'sa_home_v2', 'sa_stat2_label', 'آمار ۲ — برچسب', 'text', 'شهرستان' );
	$add( 'sa_home_v2', 'sa_sec_prov_title', 'عنوان بخش استان‌ها', 'text', 'استان‌های ایران' );
	$add( 'sa_home_v2', 'sa_sec_latest_title', 'عنوان بخش آخرین مقالات', 'text', 'آخرین مقالات' );
	$add( 'sa_home_v2', 'sa_sec_pop_title', 'عنوان بخش پست‌های معروف', 'text', 'پست‌های معروف' );
	$add( 'sa_home_v2', 'sa_pop_ids', 'شناسه‌ی «پست‌های معروف» (با کاما)', 'text', '', array( 'description' => 'خالی = نوشته‌های چسبانده‌شده (Sticky) نمایش داده می‌شوند. مثال: ۱۲,۳۴,۵۶ با اعداد انگلیسی.' ) );

	// Footer.
	$wp_customize->add_section( 'sa_footer', array( 'title' => 'پابرگ', 'panel' => 'sa_panel' ) );
	$add( 'sa_footer', 'sa_footer_about', 'متن «درباره» در پابرگ', 'textarea', 'سرزمین آریان دانشنامه‌ی سفر ایران است؛ اطلاعات دقیق و به‌روز درباره‌ی استان‌ها، شهرها و نمای برتر ایران.' );
	$add( 'sa_footer', 'sa_footer_copyright', 'متن حق نشر (خالی = خودکار)', 'text', '' );
	$add( 'sa_footer', 'sa_footer_credit', 'نمایش «طراحی و توسعه: محمدرضا لک»', 'checkbox', true );

	// Socials & contact (also used in Organization schema).
	$wp_customize->add_section( 'sa_social', array( 'title' => 'شبکه‌های اجتماعی و تماس', 'panel' => 'sa_panel' ) );
	foreach ( array( 'instagram' => 'اینستاگرام', 'telegram' => 'تلگرام', 'x' => 'ایکس (توییتر)', 'youtube' => 'یوتیوب', 'aparat' => 'آپارات', 'linkedin' => 'لینکدین' ) as $slug => $label ) {
		$add( 'sa_social', 'sa_social_' . $slug, $label, 'url' );
	}
	$add( 'sa_social', 'sa_contact_email', 'ایمیل تماس', 'email' );
	$add( 'sa_social', 'sa_default_og_image', 'تصویر پیش‌فرض اشتراک‌گذاری (۱۲۰۰×۶۳۰)', 'image' );

	// Display.
	$wp_customize->add_section( 'sa_display', array( 'title' => 'نمایش فارسی', 'panel' => 'sa_panel' ) );
	$add( 'sa_display', 'sa_jalali', 'تاریخ‌ها شمسی نمایش داده شوند', 'checkbox', true );
	$add( 'sa_display', 'sa_fa_digits', 'اعداد فارسی در تاریخ‌ها و آمار', 'checkbox', true );
	$add( 'sa_display', 'sa_fa_digits_content', 'اعداد داخل متن نوشته‌ها هم فارسی شوند (آزمایشی)', 'checkbox', false );

	// Editorial.
	$wp_customize->add_section( 'sa_editorial', array( 'title' => 'قوانین انتشار (مدل داده)', 'panel' => 'sa_panel' ) );
	$add(
		'sa_editorial',
		'sa_gate_mode',
		'دروازه‌ی انتشار سطح ۷',
		'select',
		'hard',
		array(
			'choices'     => array(
				'hard' => 'سخت‌گیر: موجودیت ناقص منتشر نمی‌شود (پیش‌فرض)',
				'soft' => 'هشدار: منتشر می‌شود اما اخطار نشان داده می‌شود',
			),
			'description' => 'الزامات: رابطه‌ی والد، عنوان/توضیح/کلیدواژه‌ی سئو، FAQ، تصویر شاخص، طبقه‌بندی اصلی.',
		)
	);
}
add_action( 'customize_register', 'sa_customize_register' );

/**
 * Checkbox sanitizer.
 *
 * @param mixed $value Value.
 * @return bool
 */
function sa_sanitize_checkbox( $value ) {
	return (bool) $value;
}

/**
 * Optional: Persian digits inside post content text nodes (never inside tags/attributes).
 *
 * @param string $content Content.
 * @return string
 */
function sa_content_fa_digits( $content ) {
	if ( is_admin() || is_feed() || ! get_theme_mod( 'sa_fa_digits_content', false ) ) {
		return $content;
	}
	return preg_replace_callback(
		'/(<(?:code|pre|script|style|kbd|samp)\b[^>]*>.*?<\/(?:code|pre|script|style|kbd|samp)>)|(<[^>]+>)|([^<]+)/isu',
		function ( $m ) {
			if ( ! empty( $m[1] ) || ! empty( $m[2] ) ) {
				return $m[0];
			}
			return sa_fa_digits( $m[3] );
		},
		$content
	);
}
add_filter( 'the_content', 'sa_content_fa_digits', 99 );
