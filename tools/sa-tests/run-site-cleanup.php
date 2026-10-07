<?php
/**
 * تست‌های «پاک‌سازی صفحه‌های بی‌تکلیف + یکسان‌سازی نامِ دیدنی‌ها» (inc/site-cleanup.php)
 *
 * رفتارِ درخواستی: صفحه‌های شهرستانِ بدونِ متن یا بدونِ عنوان به سطلِ زباله بروند،
 * هیچ صفحهٔ دارای متن لمس نشود، نامِ قدیمی فقط در متنِ منتشرشده جایگزین شود،
 * پیش‌نمایش پیش‌فرض باشد و اعمال فقط با manage_options + nonce انجام شود.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-site-cleanup.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

if ( ! defined( 'SA_ENABLE_ACCOMMODATION' ) ) {
	define( 'SA_ENABLE_ACCOMMODATION', false );
}

/**
 * شبیه‌سازِ sa_fa_digits (inc/helpers.php).
 *
 * @param mixed $value مقدار.
 * @return string
 */
function sa_fa_digits( $value ) {
	$en = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
	$fa = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
	return str_replace( $en, $fa, (string) $value );
}

if ( ! function_exists( 'get_page_by_path' ) ) {
	/**
	 * شبیه‌سازِ کمینهٔ get_page_by_path روی نوشته‌های ثبت‌شده در stub.
	 *
	 * @param string $path      نامک.
	 * @param string $output    نوع خروجی.
	 * @param string $post_type نوعِ نوشته.
	 * @return object|null
	 */
	function get_page_by_path( $path, $output = 'OBJECT', $post_type = 'post' ) {
		$path = trim( (string) $path, '/' );
		foreach ( get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ), 'posts_per_page' => -1 ) ) as $post ) {
			if ( (string) $post->post_name === $path ) {
				return $post;
			}
		}
		return null;
	}
}

require '/ws/theme/inc/site-cleanup.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

$OLD = 'نمای برتر';
$OLD_PLURAL = 'نماهای برتر';
$NEW = 'دیدنی';
$NEW_PLURAL = 'دیدنی‌ها';

/* ------------------------------------------------- §۱. شناساییِ صفحه‌های خالی */

$draft_empty = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'کبودرآهنگ',
		'post_name'    => 'kabudarahang-city',
		'post_content' => '',
	)
);
$draft_blank = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => '',
		'post_name'    => 'galikesh',
		'post_content' => '<p>متنِ کوتاهِ بدونِ عنوان.</p>',
	)
);
$trashed_old = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان خرمدره',
		'post_name'    => 'kharadere',
		'post_content' => '<p>معرفی شهرستان خرمدره با متن کامل.</p>',
	)
);
$published_full = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان ایجرود',
		'post_name'    => 'ejrud',
		'post_content' => '<p>متن کامل دربارهٔ شهرستان ایجرود.</p>',
	)
);

$candidates = sa_city_cleanup_candidates();
$ids        = array_column( $candidates, 'ID' );
sa_eq( 'دو صفحهٔ خالی شناسایی شد', 2, count( $candidates ) );
sa_eq( 'پیش‌نویسِ بدونِ متن در فهرست است', true, in_array( $draft_empty, $ids, true ) );
sa_eq( 'صفحهٔ بدونِ عنوان در فهرست است', true, in_array( $draft_blank, $ids, true ) );
sa_eq( 'صفحهٔ دارای متن لمس نمی‌شود', false, in_array( $published_full, $ids, true ) );
sa_eq( 'صفحهٔ تکراریِ دارای متن هم لمس نمی‌شود', false, in_array( $trashed_old, $ids, true ) );

$by_id = array();
foreach ( $candidates as $row ) {
	$by_id[ (int) $row['ID'] ] = $row;
}
sa_eq( 'علتِ صفحهٔ خالی = متن', array( 'content' ), $by_id[ $draft_empty ]['reason'] );
sa_eq( 'علتِ صفحهٔ بی‌عنوان = عنوان', array( 'title' ), $by_id[ $draft_blank ]['reason'] );

/* ------------------------------------------------- §۲. جایگزینیِ نام */

$hits = 0;
$text = '<p>این آبشار را به یک «' . $OLD . '» کامل تبدیل می‌کند. ' . $OLD_PLURAL . ' دیگر لرستان را ببینید.</p>';
$out  = sa_cleanup_replace_terms( $text, $hits );
sa_eq( 'دو عبارت جایگزین شد', 2, $hits );
sa_eq( 'نامِ مفرد تازه', true, false !== strpos( $out, '«' . $NEW . '» کامل' ) );
sa_eq( 'نامِ جمعِ تازه', true, false !== strpos( $out, $NEW_PLURAL . ' دیگر' ) );
sa_eq( 'دیگر نامِ قدیمی نمانده', 0, substr_count( $out, $OLD ) );

$hits_none = 0;
$same      = sa_cleanup_replace_terms( 'متنِ بدونِ نامِ قدیمی', $hits_none );
sa_eq( 'متنِ بی‌ربط دست‌نخورده می‌ماند', 0, $hits_none );
sa_eq( 'متنِ بی‌ربط یکسان برمی‌گردد', 'متنِ بدونِ نامِ قدیمی', $same );

/* ------------------------------------------------- §۳. فهرستِ متن‌های دارای نامِ قدیمی */

$post_old = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان دورود',
		'post_name'    => 'dorud',
		'post_content' => '<p>ترکیب آبشار، کوه و روستا آن را به یک «' . $OLD . '» کامل تبدیل می‌کند.</p>',
	)
);
$post_draft_old = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'پیش‌نویس',
		'post_name'    => 'draft-with-old',
		'post_content' => '<p>' . $OLD . ' در پیش‌نویس</p>',
	)
);

$hits_rows = sa_cleanup_term_hits();
$hit_ids   = array_column( $hits_rows, 'ID' );
sa_eq( 'فقط متنِ منتشرشده بررسی می‌شود', 1, count( $hits_rows ) );
sa_eq( 'شناسهٔ صفحهٔ دارای نامِ قدیمی', true, in_array( $post_old, $hit_ids, true ) );
sa_eq( 'پیش‌نویس نادیده گرفته می‌شود', false, in_array( $post_draft_old, $hit_ids, true ) );
sa_eq( 'شمارِ رخدادها درست است', 1, (int) $hits_rows[0]['hits'] );

/* ------------------------------------------------- §۴. پیش‌نمایش هیچ چیزی را تغییر نمی‌دهد */

$GLOBALS['sa_trashed'] = array();
$dry                   = sa_cleanup_run( 'dry' );
sa_eq( 'حالتِ پیش‌فرض dry است', 'dry', $dry['mode'] );
sa_eq( 'پیش‌نمایش: شهرهای خالی', 2, (int) $dry['cities'] );
sa_eq( 'پیش‌نمایش: صفحه‌های دارای نامِ قدیمی', 1, (int) $dry['term_posts'] );
sa_eq( 'پیش‌نمایش: هیچ صفحه‌ای به سطل نرفت', 0, count( (array) $GLOBALS['sa_trashed'] ) );
sa_eq( 'پیش‌نمایش: متن دست‌نخورده ماند', true, false !== strpos( get_post( $post_old )->post_content, $OLD ) );

/* ------------------------------------------------- §۵. اعمال: سطلِ زباله + جایگزینیِ متن */

$apply = sa_cleanup_run( 'apply' );
sa_eq( 'اعمال: هر ۲ صفحه به سطل رفت', 2, (int) $apply['trashed'] );
sa_eq( 'اعمال: یک صفحه به‌روزرسانی شد', 1, (int) $apply['changed'] );
sa_eq( 'وضعیتِ صفحهٔ خالی حالا trash است', 'trash', get_post( $draft_empty )->post_status );
sa_eq( 'صفحهٔ دارای متن همچنان منتشرشده است', 'publish', get_post( $published_full )->post_status );
sa_eq( 'متنِ صفحه‌ی منتشرشده بازنویسی شد', 0, substr_count( get_post( $post_old )->post_content, $OLD ) );
sa_eq( 'نامِ تازه در متن نشسته است', true, false !== strpos( get_post( $post_old )->post_content, $NEW ) );
sa_eq( 'پیوندهای صفحهٔ دیگر دست‌نخورده است', true, false !== strpos( get_post( $trashed_old )->post_content, 'خرمدره' ) );

$again = sa_cleanup_run( 'apply' );
sa_eq( 'اجرای دوباره: صفحه‌ای برای سطل نیست', 0, (int) $again['trashed'] );
sa_eq( 'اجرای دوباره: صفحه‌ای برای تغییر نیست', 0, (int) $again['changed'] );

/* ------------------------------------------------- §۶. بخش‌های پیشخوان (§ ۱۵ سلامت محتوا) */

$last = sa_cleanup_last();
sa_eq( 'گزارشِ آخرین اجرا ذخیره می‌شود', true, is_array( $last ) && isset( $last['trashed'] ) );

sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'شهداد',
		'post_name'    => 'shahdad',
		'post_content' => '',
	)
);
sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان تفت',
		'post_name'    => 'taft',
		'post_content' => '<p>متن با ' . $OLD . ' در آن.</p>',
	)
);
ob_start();
sa_city_cleanup_section();
sa_term_rename_section();
$html = ob_get_clean();
sa_eq( 'بخشِ شهرستان رندر می‌شود', true, false !== strpos( $html, 'صفحه‌های بی‌تکلیفِ شهرستان' ) );
sa_eq( 'بخشِ نامِ دیدنی‌ها رندر می‌شود', true, false !== strpos( $html, 'یکسان‌سازی نام: «دیدنی‌ها»' ) );
sa_eq( 'دکمهٔ پیش‌نمایش هست', true, false !== strpos( $html, 'value="dry"' ) );
sa_eq( 'دکمهٔ اعمال هست', true, false !== strpos( $html, 'value="apply"' ) );

/* ------------------------------------------------- §۷. نامِ نمایشیِ تازه در رجیستری موجودیت‌ها */

require '/ws/theme/inc/entities-config.php';
$config = sa_entities_config();
sa_eq( 'نامِ مفردِ موجودیت', 'دیدنی', $config['attraction']['singular'] );
sa_eq( 'نامِ جمعِ موجودیت', 'دیدنی‌ها', $config['attraction']['plural'] );
sa_eq( 'نامکِ فنی دست‌نخورده مانده', 'attraction', $config['attraction']['url_base'] );

/* ------------------------------------------------- §۸. جانشینِ تکراری‌ها (تعیین تکلیف) */

$index = sa_cleanup_county_index();
sa_eq( 'رجیستریِ شهرستان‌ها خوانده می‌شود', true, count( $index ) >= 480 );

$t_is = sa_city_cleanup_twin( 'isfahan' );
sa_eq( 'پیش‌نویسِ isfahan جانشینش isfahan-city است', array( 'slug' => 'isfahan-city', 'name' => 'اصفهان', 'via' => 'known' ), $t_is );
$t_ga = sa_city_cleanup_twin( 'galikesh' );
sa_eq( 'پیش‌نویسِ galikesh جانشینش galikash است', 'galikash', $t_ga['slug'] );
$t_ka = sa_city_cleanup_twin( 'kabudarahang-city' );
sa_eq( 'پیش‌نویسِ kabudarahang-city جانشینش kabutarahang است', 'kabutarahang', $t_ka['slug'] );
sa_eq( 'پیش‌نویسِ shahdad جانشینش شهرستان کرمان است (شهداد شهرستان نیست)', 'kerman', sa_city_cleanup_twin( 'shahdad' )['slug'] );
sa_eq( 'shahdad به شاهرود وصل نمی‌شود (حدِ فاصله ۱)', false, 'shahrud' === sa_city_cleanup_twin( 'shahdad' )['slug'] );
sa_eq( 'bam-safiabad جانشینش bam-and-safiabad است', 'bam-and-safiabad', sa_city_cleanup_twin( 'bam-safiabad' )['slug'] );
sa_eq( 'maneh-samalqan جانشینش samalqan است', 'samalqan', sa_city_cleanup_twin( 'maneh-samalqan' )['slug'] );
sa_eq( 'ردیفِ رجیستری، خودش جانشین است', 'self', sa_city_cleanup_twin( 'tabriz' )['via'] );
sa_eq( 'نامکِ طویل («tabriz-city») به ردیفِ رجیستری می‌رسد', 'tabriz', sa_city_cleanup_twin( 'tabriz-city' )['slug'] );
sa_eq( 'غلطِ املاییِ یک‌حرفی شناسایی می‌شود', 'tabriz', sa_city_cleanup_twin( 'tabrizz' )['slug'] );
sa_eq( 'نامکِ بی‌ربط جانشین ندارد', '', sa_city_cleanup_twin( 'zzz-unknown' )['slug'] );

// فیکسچرِ تکراری: پیش‌نویسِ خالیِ isfahan + صفحهٔ منتشرشدهٔ isfahan-city
sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'اصفهان',
		'post_name'    => 'isfahan',
		'post_content' => '',
	)
);
sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان اصفهان',
		'post_name'    => 'isfahan-city',
		'post_content' => '<p>صفحهٔ منتشرشدهٔ اصفهان.</p>',
	)
);
$rows = sa_city_cleanup_candidates();
$dup  = array();
foreach ( $rows as $row ) {
	if ( 'isfahan' === $row['slug'] ) {
		$dup = $row;
	}
}
sa_eq( 'ردیفِ تکراری در فهرستِ کاندیدها هست', true, ! empty( $dup ) );
sa_eq( 'ستونِ جانشین برای isfahan پر می‌شود', 'isfahan-city', isset( $dup['twin']['slug'] ) ? $dup['twin']['slug'] : '' );
sa_eq( 'وضعیتِ صفحهٔ جانشین گزارش می‌شود', 'publish', isset( $dup['twin_status'] ) ? $dup['twin_status'] : '' );

$dry2 = sa_cleanup_city_run( 'dry' );
sa_eq( 'گزارشِ اجرا شمارِ تکراری‌ها را می‌دهد', true, (int) $dry2['duplicates'] >= 1 );
sa_eq( 'نمونهٔ گزارش «نامک ← جانشین» را نشان می‌دهد', true, false !== strpos( implode( ' | ', $dry2['city_sample'] ), 'isfahan' ) );

// سطلِ زباله: تصمیمِ مستند و راهِ حذفِ همیشگی
sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'trash',
		'post_title'   => '',
		'post_name'    => 'bam-safiabad__trashed',
		'post_content' => '',
	)
);
ob_start();
sa_city_cleanup_section();
$html2 = ob_get_clean();
sa_eq( 'فهرستِ سطلِ زباله رندر می‌شود', true, false !== strpos( $html2, 'در سطلِ زباله' ) );
sa_eq( 'تصمیمِ حذفِ همیشگی نوشته می‌شود', true, false !== strpos( $html2, 'حذفِ همیشگی از سطلِ زباله' ) );
sa_eq( 'جانشینِ موردِ سطل‌شده نشان داده می‌شود', true, false !== strpos( $html2, 'bam-and-safiabad' ) );
sa_eq( 'ستونِ جانشین در جدولِ کاندیدها هست', true, false !== strpos( $html2, '>جانشین<' ) );

/* ------------------------------------------------- §۹. حذفِ قطعیِ صفحه‌های بی‌متن (v2.11.42) */

// صفحات آزمایشی با همان الگوی چهار موردِ واقعی سایت (پیش‌نویسِ بی‌متن + جانشینِ منتشرشده).
$purge_ok  = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'گالیکش',
		'post_name'    => 'galikesh',
		'post_content' => '',
	)
);
$purge_ok2 = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'کبودرآهنگ',
		'post_name'    => 'kabudarahang-city',
		'post_content' => '',
	)
);
$purge_pub = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'شهرستان منتشرشده',
		'post_name'    => 'published-county',
		'post_content' => '',
	)
);
$purge_txt = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'پیش‌نویسِ دارای متن',
		'post_name'    => 'draft-with-text',
		'post_content' => '<p>متنِ واقعی</p>',
	)
);
$purge_reg = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'اصفهان',
		'post_name'    => 'isfahan-city',
		'post_content' => '',
	)
);
$purge_pg  = sa_add_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_name'    => 'plain-page',
		'post_content' => '',
	)
);

sa_eq( 'پیش‌نویسِ بی‌متن مجاز به حذفِ قطعی است', '', sa_cleanup_purge_guard( $purge_ok ) );
sa_eq( 'پیش‌نویسِ تکراریِ دیگری هم مجاز است', '', sa_cleanup_purge_guard( $purge_ok2 ) );
sa_ok( 'صفحهٔ منتشرشده هرگز مجاز نیست', false !== strpos( sa_cleanup_purge_guard( $purge_pub ), 'منتشرشده' ) );
sa_ok( 'صفحهٔ دارای متن مجاز نیست', false !== strpos( sa_cleanup_purge_guard( $purge_txt ), 'متن دارد' ) );
sa_ok( 'نوعِ غیرِ شهرستان مجاز نیست', false !== strpos( sa_cleanup_purge_guard( $purge_pg ), 'شهرستان' ) );
sa_ok( 'ردیفِ رجیستری محافظت می‌شود (حذف نمی‌شود)', false !== strpos( sa_cleanup_purge_guard( $purge_reg ), 'رجیستری' ) );

// ارجاع از یک دیدنی به صفحهٔ بی‌متن: تا اصلاحِ رابطه، حذف متوقف می‌شود.
$purge_ref = sa_add_post(
	array(
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'دیدنیِ ارجاع‌دهنده',
		'post_name'    => 'ref-attraction',
		'post_content' => '<p>متن</p>',
		'meta'         => array( 'sa_city_id' => $purge_ok ),
	)
);
sa_eq( 'شمارِ ارجاع‌ها درست شمرده می‌شود', 1, sa_cleanup_reference_count( $purge_ok ) );
sa_ok( 'ارجاعِ نوشتهٔ دیگر مانعِ حذف می‌شود', false !== strpos( sa_cleanup_purge_guard( $purge_ok ), 'ارجاع' ) );

$dry_purge = sa_cleanup_purge_run( 'dry', array( $purge_ok, $purge_ok2, $purge_pub, $purge_reg ) );
sa_eq( 'پیش‌نمایش: هیچ صفحه‌ای حذف نمی‌شود', 0, (int) $dry_purge['deleted'] );
sa_eq( 'پیش‌نمایش: فقط صفحهٔ مجاز در فهرست است', 1, count( $dry_purge['items'] ) );
sa_eq( 'پیش‌نمایش: ردشده‌ها گزارش می‌شوند', 3, count( $dry_purge['blocked'] ) );
sa_eq( 'پیش‌نمایش: صفحهٔ ارجاع‌دار دست‌نخورده می‌ماند', 'draft', get_post( $purge_ok )->post_status );

// با برداشتنِ ارجاع، همان شناسه‌ها مجاز می‌شوند.
wp_delete_post( $purge_ref, true );
$apply_purge = sa_cleanup_purge_run( 'apply', array( $purge_ok, $purge_ok2, $purge_pub, $purge_reg ) );
sa_eq( 'اعمال: دو صفحهٔ مجاز حذف شد', 2, (int) $apply_purge['deleted'] );
sa_eq( 'صفحهٔ حذف‌شده دیگر در سایت نیست', null, get_post( $purge_ok ) );
sa_eq( 'صفحهٔ منتشرشده دست‌نخورده مانده است', 'publish', get_post( $purge_pub )->post_status );
sa_eq( 'صفحهٔ سئوی رجیستری دست‌نخورده مانده است', 'draft', get_post( $purge_reg )->post_status );

$purge_log = get_option( 'sa_cleanup_purged_log' );
sa_eq( 'سیاههٔ حذفِ قطعی ثبت می‌شود', 2, count( (array) $purge_log ) );
sa_eq( 'سیاهه نامکِ صفحه‌های پاک‌شده را نگه می‌دارد', array( 'galikesh', 'kabudarahang-city' ), array_column( (array) $purge_log, 'slug' ) );

ob_start();
sa_cleanup_purge_section();
$html3 = ob_get_clean();
sa_eq( 'بخشِ حذفِ قطعی رندر می‌شود', true, false !== strpos( $html3, 'حذفِ قطعیِ صفحه‌های بی‌متن' ) );
sa_eq( 'فرم به هندلرِ حذفِ قطعی وصل است', true, false !== strpos( $html3, 'value="sa_cleanup_purge"' ) );
sa_eq( 'هر شناسه پیش از حذف بازبینی می‌شود (برچسبِ رد)', true, false !== strpos( $html3, 'رد شد' ) );
sa_eq( 'دکمهٔ حذفِ قطعی هشدار می‌دهد', true, false !== strpos( $html3, 'بازگشت‌ناپذیر' ) );

/* ------------------------------------- §۱۰. انتقالِ ارجاع‌ها پیش از حذف (پروندهٔ شهرهای بی‌متن) */

// الگوی واقعیِ سایت: پیش‌نویسِ خالی + جانشینِ منتشرشده + مقاله‌ای که به پیش‌نویس ارجاع دارد.
// همین جفت در §۸ ساخته شده است (پیش‌نویسِ بی‌متن + صفحهٔ منتشرشده) و در سایتِ زنده هم هست.
$move_src_post = get_page_by_path( 'isfahan', OBJECT, 'city' );
$move_dst_post = get_page_by_path( 'isfahan-city', OBJECT, 'city' );
$move_src      = $move_src_post ? (int) $move_src_post->ID : 0;
$move_dst      = $move_dst_post ? (int) $move_dst_post->ID : 0;
$move_ref = sa_add_post(
	array(
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'میدان نقش جهان',
		'post_name'    => 'naqsh-e-jahan-square',
		'post_content' => '<p>متن</p>',
		'meta'         => array( 'sa_city_id' => $move_src ),
	)
);

$detail = sa_cleanup_purge_guard_detail( $move_src );
sa_eq( 'کدِ مانعِ ارجاع مشخص است', 'refs', $detail['code'] );
sa_eq( 'شمارِ ارجاع در جزئیاتِ محافظ می‌آید', 1, (int) $detail['refs'] );

$move_dry = sa_cleanup_reassign_references( $move_src, 'dry' );
sa_eq( 'پیش‌نمایشِ انتقال، جانشینِ منتشرشده را نشان می‌دهد', 'isfahan-city', $move_dry['target'] );
sa_eq( 'پیش‌نمایشِ انتقال چیزی را عوض نمی‌کند', (string) $move_src, (string) get_post_meta( $move_ref, 'sa_city_id', true ) );

$move_apply = sa_cleanup_reassign_references( $move_src, 'apply' );
sa_eq( 'انتقالِ ارجاع انجام شد', 1, count( $move_apply['moved'] ) );
sa_eq( 'ارجاعِ دیدنی به صفحهٔ منتشرشده رسید', (string) $move_dst, (string) get_post_meta( $move_ref, 'sa_city_id', true ) );
sa_eq( 'پس از انتقال، مانعی نمی‌ماند', '', sa_cleanup_purge_guard( $move_src ) );

// حذفِ قطعی با انتقالِ خودکار ارجاع‌ها — یک کلیکِ مالک.
$auto_src = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'کبودرآهنگ',
		'post_name'    => 'kabudarahang-city',
		'post_content' => '',
	)
);
$auto_dst = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'publish',
		'post_title'   => 'کبودرآهنگ',
		'post_name'    => 'kabutarahang',
		'post_content' => '<p>متنِ شهرستان</p>',
	)
);
$auto_ref = sa_add_post(
	array(
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'غار علیصدر',
		'post_name'    => 'ali-sadr-cave',
		'post_content' => '<p>متن</p>',
		'meta'         => array( 'sa_city_id' => $auto_src ),
	)
);

$auto_dry = sa_cleanup_purge_run( 'dry', array( $auto_src ), true );
sa_eq( 'پیش‌نمایش با انتقال، صفحه را قابلِ حذف می‌داند', 1, count( $auto_dry['items'] ) );
sa_eq( 'پیش‌نمایش هیچ صفحه‌ای را حذف نمی‌کند', 0, (int) $auto_dry['deleted'] );

$auto_apply = sa_cleanup_purge_run( 'apply', array( $auto_src ), true );
sa_eq( 'حذفِ قطعی با انتقال انجام شد', 1, (int) $auto_apply['deleted'] );
sa_eq( 'ارجاعِ مقاله در همان اجرا منتقل شد', 1, (int) $auto_apply['moved_refs'] );
sa_eq( 'مقاله اکنون به صفحهٔ منتشرشده اشاره می‌کند', (string) $auto_dst, (string) get_post_meta( $auto_ref, 'sa_city_id', true ) );
sa_eq( 'صفحهٔ بی‌متن برای همیشه پاک شد', null, get_post( $auto_src ) );

// بدونِ اجازهٔ انتقال، محافظ سرِ جایش می‌ماند (رفتارِ محافظه‌کارانه).
$hold_src = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => 'شهداد',
		'post_name'    => 'shahdad',
		'post_content' => '',
	)
);
sa_add_post(
	array(
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'کلوت‌های شهداد',
		'post_name'    => 'shahdad-kaluts',
		'post_content' => '<p>متن</p>',
		'meta'         => array( 'sa_city_id' => $hold_src ),
	)
);
$hold = sa_cleanup_purge_run( 'apply', array( $hold_src ), false );
sa_eq( 'بدونِ اجازهٔ انتقال، حذف متوقف می‌شود', 0, (int) $hold['deleted'] );
sa_eq( 'صفحهٔ ارجاع‌دار دست‌نخورده می‌ماند', 'draft', get_post( $hold_src )->post_status );

// صفحهٔ سطلِ زباله هم در فهرستِ حذفِ قطعی دیده می‌شود.
$trash_src = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'trash',
		'post_title'   => '',
		'post_name'    => 'bam-safiabad__trashed',
		'post_content' => '',
	)
);
$trash_slug = '';
$trash_ok   = false;
foreach ( sa_cleanup_purge_targets() as $row ) {
	if ( (int) $row['ID'] === (int) $trash_src ) {
		$trash_slug = (string) $row['slug'];
		$trash_ok   = ! empty( $row['deletable'] );
	}
}
sa_eq( 'نامکِ صفحهٔ سطل از پسوندِ __trashed پاک می‌شود', 'bam-safiabad', $trash_slug );
sa_eq( 'صفحهٔ بی‌متنِ سطل مجاز به حذفِ قطعی است', true, $trash_ok );

// انتقالِ ارجاع‌ها با جانشینِ نامشخص، شکستِ شفاف می‌دهد (نه حذفِ کورکورانه).
$orphan = sa_add_post(
	array(
		'post_type'    => 'city',
		'post_status'  => 'draft',
		'post_title'   => '',
		'post_name'    => 'zzz-unknown-city',
		'post_content' => '',
	)
);
sa_add_post(
	array(
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'دیدنیِ بی‌جانشین',
		'post_name'    => 'orphan-ref',
		'post_content' => '<p>متن</p>',
		'meta'         => array( 'sa_city_id' => $orphan ),
	)
);
$orphan_move = sa_cleanup_reassign_references( $orphan, 'apply' );
sa_eq( 'جانشینِ ناشناخته گزارش می‌شود', 1, count( $orphan_move['blocked'] ) );
sa_eq( 'و هیچ ارجاعی جابه‌جا نمی‌شود', 0, count( $orphan_move['moved'] ) );
$orphan_purge = sa_cleanup_purge_run( 'apply', array( $orphan ), true );
sa_eq( 'صفحهٔ بی‌جانشین هم حذف نمی‌شود', 0, (int) $orphan_purge['deleted'] );
sa_eq( 'و دلیلش گزارش می‌شود', 1, count( $orphan_purge['blocked'] ) );

// رندرِ بخش با گزینهٔ انتقال.
ob_start();
sa_cleanup_purge_section();
$html4 = ob_get_clean();
sa_eq( 'گزینهٔ انتقالِ ارجاع‌ها رندر می‌شود', true, false !== strpos( $html4, 'sa_purge_move_refs' ) );
sa_eq( 'ستونِ ارجاع‌ها در جدول هست', true, false !== strpos( $html4, 'ارجاع‌ها' ) );

sa_done();
