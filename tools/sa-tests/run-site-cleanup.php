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
sa_eq( 'پیش‌نویسِ shahdad جانشینی در رجیستری ندارد', '', sa_city_cleanup_twin( 'shahdad' )['slug'] );
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

sa_done();
