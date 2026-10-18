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
			'number'   => 'absint',
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
	$add( 'sa_home', 'sa_home_description', 'توضیحات متای صفحه‌ی اول (۷۰ تا ۱۵۵ کاراکتر)', 'textarea', 'راهنمای کامل سفر به ایران: استان‌ها، شهرها و دیدنی‌های طبیعت ایران با اطلاعات دقیق و به‌روز.' );

	// صفحه اصلی (طرح v2) — قالب برگه «صفحه اصلی سرزمین آریان (طرح v2)».
	$wp_customize->add_section( 'sa_home_v2', array( 'title' => 'صفحه اصلی (طرح v2)', 'panel' => 'sa_panel', 'description' => 'تنظیمات دستی صفحه‌ی خانه (برگه‌ی «خانه» با قالب «صفحه اصلی سرزمین آریان (طرح v2)»). لوگوی هویت سایت فقط در هدر و فوتر نمایش داده می‌شود؛ هیروی صفحهٔ اول لوگوی جداگانه ندارد.' ) );
	$add( 'sa_home_v2', 'sa_home_slogan', 'شعار سایت (حماسی — زیر نام سایت، هدر و فوتر)', 'text', 'چو ایران نباشد، تن من مباد' );
	$add( 'sa_home_v2', 'sa_hero_text', 'متن معرفی زیر شعار', 'textarea', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و دیدنی‌ها طبیعت ایران.' );
	$add( 'sa_home_v2', 'sa_search_placeholder', 'متن جایگزین جعبه‌ی جست‌وجو', 'text', 'استان، شهر یا دیدنی…' );
	$add( 'sa_home_v2', 'sa_stat1_num', 'آمار ۱ — عدد', 'text', '31' );
	$add( 'sa_home_v2', 'sa_stat1_label', 'آمار ۱ — برچسب', 'text', 'استان' );
	$add( 'sa_home_v2', 'sa_stat2_num', 'آمار ۲ — عدد (انگلیسی برای شمارنده)', 'text', '419' );
	$add( 'sa_home_v2', 'sa_stat2_suffix', 'آمار ۲ — پسوند (+ یا خالی)', 'text', '+' );
	$add( 'sa_home_v2', 'sa_stat2_label', 'آمار ۲ — برچسب', 'text', 'شهرستان' );
	$add( 'sa_home_v2', 'sa_sec_prov_title', 'عنوان برگهٔ استان‌ها (/province/؛ از ۲٫۱۳٫۳ نوارهای استان از صفحهٔ اول به آن‌جا رفته‌اند)', 'text', 'استان‌های ایران' );
	$add( 'sa_home_v2', 'sa_sec_city_title', 'عنوان برگهٔ شهرستان‌ها (/city/)', 'text', 'شهرستان‌های ایران' );
	$add( 'sa_home_v2', 'sa_stat3_label', 'آمار ۳ — برچسب آموزش‌ها (عدد خودکار = آموزش‌های منتشرشده)', 'text', 'آموزش' );

	// آموزش‌ها (v2.13.1) — دستهٔ هاب که کنار استان‌ها/شهرستان‌ها/دیدنی‌ها در منو، صفحهٔ اول و پابرگ می‌نشیند.
	$wp_customize->add_section( 'sa_education', array( 'title' => 'آموزش‌ها (کمپینگ و بقا)', 'panel' => 'sa_panel', 'description' => 'لینک «آموزش‌ها» در منوی اصلی، چیپ‌های صفحهٔ اول، آمار و پابرگ به‌طور خودکار پس از انتشار نخستین آموزش (تیک «تأیید مالک» در ابزار «مقالات آماده») ظاهر می‌شود؛ تا آن زمان به آرشیو خالی لینک داده نمی‌شود.' ) );
	$add( 'sa_education', 'sa_education_category', 'نامک دستهٔ آموزش‌ها', 'text', 'camping-survival', array( 'description' => 'پیش‌فرض: camping-survival («آموزش‌های ضروری کمپینگ و بقا»).' ) );
	$add( 'sa_education', 'sa_education_nav_label', 'برچسب لینک در منو، چیپ‌ها و پابرگ', 'text', 'آموزش‌ها' );

	// Footer.
	$wp_customize->add_section( 'sa_footer', array( 'title' => 'پابرگ', 'panel' => 'sa_panel' ) );
	$add( 'sa_footer', 'sa_footer_about', 'متن «درباره» در پابرگ', 'textarea', 'سرزمین آریان دانشنامه‌ی سفر ایران است؛ اطلاعات دقیق و به‌روز درباره‌ی استان‌ها، شهرها و دیدنی‌ها ایران.' );
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
	$add(
		'sa_display',
		'sa_autolink',
		'لینک‌سازی داخلی خودکار (استان، شهرستان، دیدنی)',
		'checkbox',
		true,
		array( 'description' => 'نخستین رخدادِ نامِ هر استان/شهرستان/دیدنی در بدنه‌ی هر مقاله، یک‌بار به صفحه‌ی خودش لینک می‌شود. لینک‌های دستیِ داخل متن دست‌نخورده می‌مانند.' )
	);

	// تصاویر (v2.15.0): پس از پاک‌شدن فایل‌های رسانه، نمایش تصویر شاخص خاموش و تصویرهای گمشدهٔ متن جای‌گزین می‌شوند.
	$add(
		'sa_display',
		'sa_show_featured_images',
		'نمایش تصویر شاخص در سایت (خاموش تا بازگشت فایل‌های رسانه)',
		'checkbox',
		false,
		array( 'description' => 'پیش‌فرض خاموش است: کارت‌ها، آرشیوها و هیروی صفحه‌ها بدون تصویر شاخص نمایش داده می‌شوند. حتی با روشن‌کردن این گزینه، تصویری که فایلش روی سرور نیست نمایش داده نمی‌شود. بازگرداندن فایل‌ها: سرزمین آریان → بازیابی تصاویر. تصویر شاخص «دیدنی‌ها» جداگانه و با کلید پایین کنترل می‌شود.' )
	);
	$add(
		'sa_display',
		'sa_show_attraction_image',
		'نمایش تصویر شاخص «دیدنی» داخل مقاله (پیش‌فرض خاموش)',
		'checkbox',
		false,
		array( 'description' => 'پیش‌فرض خاموش است تا تصاویر شاخص دیدنی‌ها در کل سایت و داخل بدنهٔ مقاله مخفی بمانند.' )
	);
	// «فهرست مطالب» (v2.18.17): درخواست مالک — همهٔ مقاله‌ها فهرست مطالب داشته باشند.
	$add(
		'sa_display',
		'sa_show_toc',
		'نمایش «فهرست مطالب» در ابتدای مقاله‌ها',
		'checkbox',
		true,
		array( 'description' => 'فهرست از سرفصل‌های خودِ مقاله (H2 تا H4) ساخته می‌شود و بخش‌های قالب (اطلاعات کلیدی، تقسیمات شهرستان، سوالات متداول، منابع) هم به آن اضافه می‌شوند؛ برای جای دلخواه، نشان {{TOC}} را در متن بگذارید.' )
	);
	$add(
		'sa_display',
		'sa_toc_min',
		'کمینهٔ سرفصل‌ها برای نمایش «فهرست مطالب»',
		'number',
		2,
		array( 'description' => 'اگر مقاله‌ای کمتر از این تعداد سرفصل داشته باشد، کادر فهرست نمایش داده نمی‌شود (پیش‌فرض: ۲).', 'input_attrs' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) )
	);
	$add(
		'sa_display',
		'sa_hide_missing_images',
		'جای‌گزینی تصویرهای گمشدهٔ داخل متن با کادر متنی',
		'checkbox',
		true,
		array( 'description' => 'تصویرهایی که فایلشان روی سرور نیست، در بخش عمومی سایت به کادر آرام «تصویر این بخش در دست بازبینی است» تبدیل می‌شوند؛ محتوای ذخیره‌شده و ویرایشگر دست‌نخورده می‌مانند.' )
	);
	$add( 'sa_display', 'sa_missing_image_label', 'متن کادر جای‌گزین تصویر گمشده', 'text', 'تصویر این بخش در دست بازبینی است' );

	// Editorial.
	$wp_customize->add_section( 'sa_editorial', array( 'title' => 'قوانین انتشار (مدل داده)', 'panel' => 'sa_panel' ) );
	$add(
		'sa_editorial',
		'sa_gate_mode',
		'دروازه‌ی انتشار سطح ۷',
		'select',
		'soft',
		array(
			'choices'     => array(
				'soft' => 'یادآوری (پیش‌فرض): منتشر می‌شود؛ کمبودهای سطح ۷ فقط هشدار داده می‌شوند',
				'hard' => 'سخت‌گیر (اختیاری): موجودیت ناقص به پیش‌نویس برمی‌گردد',
			),
			'description' => 'سیاست انتشار (۱۴۰۵/۰۷/۱۹): شرط انتشار فقط تأیید مالک است. موارد سطح ۷ — رابطه‌ی والد، عنوان/توضیح/کلیدواژه‌ی سئو، منابع، تصویر شاخص، طبقه‌بندی اصلی، بهداشت محتوا — به‌صورت یادآوری نشان داده می‌شوند و مانع انتشار نیستند.',
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
