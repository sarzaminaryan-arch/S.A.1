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
	/* v2.11.27 — پنج تنظیم مرده حذف شدند: `sa_hero_title`، `sa_hero_subtitle`،
	   `sa_hero_image`، `sa_show_stats` و `sa_home_sections` از طرح خانه‌ی نسخه‌ی ۱
	   باقی مانده بودند و هیچ‌جای قالب خوانده نمی‌شدند (خانه‌ی واقعی
	   `template-home.php` است که از بخش «صفحه اصلی (طرح v2)» می‌خواند).
	   این تنظیم‌ها در پیشخوان دیده می‌شدند و ویرایشگر با تغییرشان هیچ اثری
	   نمی‌دید. تنها تنظیم زنده‌ی این بخش `sa_home_description` است که
	   `inc/seo.php` برای توضیحات متای صفحه‌ی اول می‌خواند. */
	$wp_customize->add_section( 'sa_home', array( 'title' => 'سئوی صفحه‌ی اول', 'panel' => 'sa_panel' ) );
	$add( 'sa_home', 'sa_home_description', 'توضیحات متای صفحه‌ی اول (۷۰ تا ۱۵۵ کاراکتر)', 'textarea', sa_default_home_description(), array( 'description' => 'خالی = همان متن پیش‌فرض قالب. متن‌های هیرو و شعار در بخش «صفحه اصلی (طرح v2)» هستند.' ) );

	// صفحه اصلی (طرح v2) — قالب برگه «صفحه اصلی سرزمین آریان (طرح v2)».
	$wp_customize->add_section( 'sa_home_v2', array( 'title' => 'صفحه اصلی (طرح v2)', 'panel' => 'sa_panel', 'description' => 'تنظیمات دستی صفحه‌ی خانه (برگه‌ی «خانه» با قالب «صفحه اصلی سرزمین آریان (طرح v2)»). لوگو از بخش «هویت سایت → لوگو» قابل تغییر است؛ اگر لوگویی انتخاب نشود لوگوی اصلی سایت استفاده می‌شود.' ) );
	$add( 'sa_home_v2', 'sa_home_slogan', 'شعار سایت (حماسی — زیر نام سایت، هدر و فوتر)', 'text', 'چو ایران نباشد، تن من مباد' );
	$add( 'sa_home_v2', 'sa_hero_text', 'متن معرفی زیر شعار', 'textarea', 'ایران را استان به استان بشناسید؛ ۳۱ استان، صدها شهر و نمای برتر طبیعت ایران.' );
	$add( 'sa_home_v2', 'sa_search_placeholder', 'متن جایگزین جعبه‌ی جست‌وجو', 'text', 'استان، شهر یا نمای برتر…' );
	/* v2.11.27 — پیش‌فرض آمارها خالی شد، یعنی «خودکار».
	   پیش‌فرض‌های ثبت‌شده `31` و `419+` با آنچه قالب واقعاً نشان می‌دهد در تضاد
	   بودند: `template-home.php` این مقدارها را با تعداد واقعی منتشرشده از
	   `sa_entity_counts()` جایگزین می‌کند و `data/counties.php` از ۴۸۳ شهرستان
	   فقط ۱۴۴ را منتشرشده ثبت کرده است. چون در نمای پیش‌نمایشِ سفارشی‌سازی
	   مقدار ثبت‌شده تزریق می‌شود، پیشخوان عدد ۴۱۹+ و سایت زنده عدد واقعی را
	   نشان می‌داد. خانه‌ی خالی در هر دو نما یکسان «خودکار» معنا می‌شود. */
	$add( 'sa_home_v2', 'sa_stat1_num', 'آمار ۱ — عدد', 'text', '', array( 'description' => 'خالی = تعداد واقعی استان‌های منتشرشده. برای عدد دستی فقط رقم انگلیسی بنویسید.' ) );
	$add( 'sa_home_v2', 'sa_stat1_label', 'آمار ۱ — برچسب', 'text', 'استان' );
	$add( 'sa_home_v2', 'sa_stat2_num', 'آمار ۲ — عدد (انگلیسی برای شمارنده)', 'text', '', array( 'description' => 'خالی = تعداد واقعی شهرستان‌های منتشرشده. ادعای عددی بزرگ‌تر از محتوای منتشرشده هم کاربر و هم ارزیاب کیفیت گوگل را از دست می‌دهد.' ) );
	$add( 'sa_home_v2', 'sa_stat2_suffix', 'آمار ۲ — پسوند (+ یا خالی)', 'text', '', array( 'description' => 'خالی = بدون پسوند. وقتی عدد از شمارش واقعی می‌آید، «+» گمراه‌کننده است.' ) );
	$add( 'sa_home_v2', 'sa_stat2_label', 'آمار ۲ — برچسب', 'text', 'شهرستان' );
	$add( 'sa_home_v2', 'sa_sec_prov_title', 'عنوان بخش استان‌ها', 'text', 'استان‌های ایران' );
	$add( 'sa_home_v2', 'sa_sec_latest_title', 'عنوان بخش آخرین مقالات', 'text', 'آخرین مقالات' );
	$add( 'sa_home_v2', 'sa_sec_pop_title', 'عنوان بخش پست‌های معروف', 'text', 'پست‌های معروف' );
	$add( 'sa_home_v2', 'sa_pop_ids', 'شناسه‌ی «پست‌های معروف» (با کاما)', 'text', '', array( 'description' => 'خالی = نوشته‌های چسبانده‌شده (Sticky) نمایش داده می‌شوند. مثال: ۱۲,۳۴,۵۶ با اعداد انگلیسی.' ) );

	// Footer.
	$wp_customize->add_section( 'sa_footer', array( 'title' => 'پابرگ', 'panel' => 'sa_panel' ) );
	$add( 'sa_footer', 'sa_footer_about', 'متن «درباره» در پابرگ', 'textarea', sa_default_footer_about(), array( 'description' => 'پیش‌فرض در `sa_default_footer_about()` (inc/helpers.php) تعریف شده تا پیشخوان و پابرگ هرگز واگرا نشوند.' ) );
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
