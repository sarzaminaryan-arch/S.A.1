<?php
/**
 * تست‌های «مقالات آمادهٔ دیدنی‌ها» (data/ready-articles.php)
 *
 * ابزارِ درون‌قالبیِ درجِ پیش‌نویس، مدخل‌های این فایل را می‌خواند و مستقیم می‌نویسد؛
 * پس هر خطای ساختاری در داده، به پیش‌نویسِ ناقص یا خطای زمانِ درج تبدیل می‌شود.
 * این اجراکننده شکلِ همهٔ مدخل‌ها، یکتاییِ نامک‌ها، اعتبارِ رجیستریِ استان/شهرستان،
 * مقصدِ همهٔ پیوندهای داخلیِ بدنه، و پوششِ نامک‌های قدیمیِ لینک‌شده را بررسی می‌کند.
 *
 * اجرا: node tools/phpwasm/exec.js tools/sa-tests/run-ready-articles.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', '/ws/theme/' );
}

$articles  = require SA_CHILD_DIR . 'data/ready-articles.php';
$counties  = require SA_CHILD_DIR . 'data/counties.php';
$provinces = require SA_CHILD_DIR . 'data/provinces.php';
$redirects = require SA_CHILD_DIR . 'data/redirects.php';

sa_eq( 'فایلِ مقالات آماده آرایه برمی‌گرداند', true, is_array( $articles ) );
sa_eq( 'هیچ مدخلی خالی نیست', true, count( $articles ) >= 16 );

/* ------------------------------------------------------ §۱. ساختارِ هر مدخل */

$required = array( 'title', 'type', 'slug', 'excerpt', 'english', 'kind', 'province', 'province_name', 'city', 'city_name', 'terms', 'meta', 'faq', 'content' );
$kinds    = array( 'lake', 'waterfall', 'mountain', 'park', 'cave', 'village', 'wetland', 'valley', 'forest', 'city', 'castle', 'monument', 'desert' );

$missing_keys = array();
$slug_mismatch = array();
$bad_type      = array();
$bad_kind      = array();
$short_content = array();
$short_excerpt = array();
$empty_faq     = array();
$empty_meta    = array();
$seen_slugs    = array();
$duplicate     = array();

foreach ( $articles as $key => $article ) {
	// مدخلِ «راهنمای شهر» تنها استثناست: نوعِ city، بی‌فیلدِ city و نامکِ کوتاهِ dorud.
	$is_city_guide = isset( $article['type'] ) && 'city' === $article['type'];
	foreach ( $required as $field ) {
		if ( $is_city_guide && in_array( $field, array( 'city', 'city_name' ), true ) ) {
			continue;
		}
		if ( ! isset( $article[ $field ] ) || '' === $article[ $field ] ) {
			$missing_keys[] = (string) $key . ':' . $field;
		}
	}
	if ( isset( $article['slug'] ) && (string) $article['slug'] !== (string) $key && ! $is_city_guide ) {
		$slug_mismatch[] = (string) $key . ' → ' . $article['slug'];
	}
	if ( isset( $article['type'] ) && ! in_array( $article['type'], array( 'attraction', 'city' ), true ) ) {
		$bad_type[] = (string) $key;
	}
	if ( isset( $article['kind'] ) && ! in_array( $article['kind'], $kinds, true ) ) {
		$bad_kind[] = (string) $key . ':' . $article['kind'];
	}
	if ( isset( $article['content'] ) && strlen( (string) $article['content'] ) < 1200 ) {
		$short_content[] = (string) $key;
	}
	if ( isset( $article['excerpt'] ) && ( mb_strlen( (string) $article['excerpt'] ) < 60 || mb_strlen( (string) $article['excerpt'] ) > 400 ) ) {
		$short_excerpt[] = (string) $key;
	}
	if ( empty( $article['faq'] ) || ! is_array( $article['faq'] ) ) {
		$empty_faq[] = (string) $key;
	}
	if ( empty( $article['meta'] ) || ! is_array( $article['meta'] ) ) {
		$empty_meta[] = (string) $key;
	}
	if ( isset( $seen_slugs[ (string) $key ] ) ) {
		$duplicate[] = (string) $key;
	}
	$seen_slugs[ (string) $key ] = true;
}

sa_eq( 'همهٔ مدخل‌ها فیلدهای لازم را دارند', array(), $missing_keys );
sa_eq( 'نامکِ هر مدخل با کلیدش یکی است', array(), $slug_mismatch );
sa_eq( 'نوعِ مدخل‌ها attraction است (تنها dorud-city راهنمای شهر است)', array(), $bad_type );
sa_eq( 'kind همهٔ مدخل‌ها در فهرست مجاز است', array(), $bad_kind );
sa_eq( 'بدنه هیچ مدخلی کوتاه‌تر از حداقل نیست', array(), $short_content );
sa_eq( 'چکیدهٔ همهٔ مدخل‌ها در بازهٔ ۶۰ تا ۴۰۰ نویسه است', array(), $short_excerpt );
sa_eq( 'همهٔ مدخل‌ها FAQ دارند', array(), $empty_faq );
sa_eq( 'همهٔ مدخل‌ها جعبهٔ متا دارند', array(), $empty_meta );
sa_eq( 'نامک هیچ مدخلی تکراری نیست', array(), $duplicate );

/* ------------------------------------------- §۲. اعتبارِ استان/شهرستان (رجیستری) */

$province_index = array();
foreach ( $provinces as $row ) {
	$province_index[ (string) $row['slug'] ] = (string) $row['name'];
}
$county_index = array();
foreach ( $counties as $row ) {
	$county_index[ (string) $row['slug'] ] = array( 'name' => (string) $row['name'], 'province' => (string) $row['province'] );
}

$unknown_province = array();
$unknown_county   = array();
$county_province  = array();
$name_mismatch    = array();

foreach ( $articles as $key => $article ) {
	if ( isset( $article['type'] ) && 'city' === $article['type'] ) {
		continue; // راهنمای شهر، فیلدِ شهرستان ندارد.
	}
	$pslug = (string) $article['province'];
	if ( ! isset( $province_index[ $pslug ] ) ) {
		$unknown_province[] = (string) $key;
	} elseif ( isset( $article['province_name'] ) && $article['province_name'] !== $province_index[ $pslug ] ) {
		$name_mismatch[] = (string) $key . ':province';
	}
	$cslug = (string) $article['city'];
	if ( ! isset( $county_index[ $cslug ] ) ) {
		$unknown_county[] = (string) $key;
	} else {
		if ( $county_index[ $cslug ]['province'] !== $pslug ) {
			$county_province[] = (string) $key;
		}
		if ( isset( $article['city_name'] ) && $article['city_name'] !== $county_index[ $cslug ]['name'] ) {
			$name_mismatch[] = (string) $key . ':city';
		}
	}
}

sa_eq( 'استانِ همهٔ مدخل‌ها در رجیستری استان‌ها هست', array(), $unknown_province );
sa_eq( 'شهرستانِ همهٔ مدخل‌ها در رجیستری شهرستان‌ها هست', array(), $unknown_county );
sa_eq( 'شهرستانِ هر مدخل به همان استانِ مدخل تعلق دارد', array(), $county_province );
sa_eq( 'نامِ استان/شهرستان با رجیستری یکی است', array(), $name_mismatch );

// راهنمای dorud خودش صفحهٔ شهرستان است؛ نامکش باید ردیفِ رجیستری داشته باشد.
sa_eq( 'راهنمای dorud-city نامکِ رجیستری دارد', true, isset( $county_index['dorud'] ) );
sa_eq( 'راهنمای dorud-city با استانِ رجیستری یکی است', 'lorestan', isset( $articles['dorud-city'] ) ? $articles['dorud-city']['province'] : '' );

/* ---------------------------------------------------- §۳. پیوندهای داخلیِ بدنه */

$redirect_target = array();
foreach ( $redirects as $from => $to ) {
	$from = trim( (string) $from, '/' );
	$redirect_target[ $from ] = trim( (string) $to, '/' );
}

$link_shape  = array();
$broken_city = array();
$broken_prov = array();
$broken_attr = array();
$link_total  = 0;

foreach ( $articles as $key => $article ) {
	if ( ! preg_match_all( '~href="(/[^"]+)"~', (string) $article['content'], $m ) ) {
		continue;
	}
	foreach ( $m[1] as $href ) {
		++$link_total;
		if ( ! preg_match( '~^/(city|province|attraction)/[^/]+/$~u', $href ) ) {
			$link_shape[] = (string) $key . ':' . $href;
			continue;
		}
		$parts = explode( '/', trim( $href, '/' ) );
		$type  = $parts[0];
		$slug  = $parts[1];
		if ( 'city' === $type && ! isset( $county_index[ $slug ] ) ) {
			$broken_city[] = (string) $key . ':' . $slug;
		}
		if ( 'province' === $type && ! isset( $province_index[ $slug ] ) ) {
			$broken_prov[] = (string) $key . ':' . $slug;
		}
		if ( 'attraction' === $type && ! isset( $articles[ $slug ] ) && ! isset( $redirect_target[ 'attraction/' . $slug ] ) ) {
			$broken_attr[] = (string) $key . ':' . $slug;
		}
	}
}

sa_eq( 'شکلِ همهٔ پیوندهای بدنه استاندارد است', array(), $link_shape );
sa_eq( 'همهٔ پیوندهای /city/ به شهرستانِ رجیستری می‌روند', array(), $broken_city );
sa_eq( 'همهٔ پیوندهای /province/ به استانِ رجیستری می‌روند', array(), $broken_prov );
sa_eq( 'مقاله‌ها پیوند داخلی دارند', true, $link_total >= 40 );
// پیوند به مقاله‌ای که هنوز نوشته نشده، خطای امروز نیست؛ نقشهٔ کارِ آینده است (§۶).
echo "\n  i پیوندهای /attraction/ بدونِ مدخلِ آماده: " . count( $broken_attr ) . "\n";

/* ------------------------------------- §۴. مدخل‌های بستهٔ ۲.۱۱.۴۰ (رفعِ ۴۰۴) */

$bundle = array(
	'6976-ارامگاه-قاسم-انوار'         => array( 'torbatjam', 'razavi-khorasan', 'monument' ),
	'26950-کویر-ریگ-جن'             => array( 'garmsar', 'semnan', 'desert' ),
	'23328-sar-agha-seyed-village'   => array( 'kuhrang', 'chaharmahal-bakhtiari', 'village' ),
	'9235-tang-e-sayad-national-park' => array( 'sharekurd', 'chaharmahal-bakhtiari', 'park' ),
);

$absent = array();
foreach ( $bundle as $slug => $expect ) {
	if ( ! isset( $articles[ $slug ] ) ) {
		$absent[] = $slug;
		continue;
	}
	$a = $articles[ $slug ];
	if ( $a['city'] !== $expect[0] || $a['province'] !== $expect[1] || $a['kind'] !== $expect[2] ) {
		$absent[] = $slug . ':ناسازگار';
	}
}
sa_eq( 'چهار مدخلِ رفعِ ۴۰۴ با شهرستان/استان/نوعِ درست ثبت شده‌اند', array(), $absent );

$core_meta = array( 'sa_english_name', 'sa_attraction_age', 'sa_attraction_area', 'sa_access_level', 'sa_trail_note', 'sa_visit_duration', 'sa_safety_note', 'sa_address', 'sa_opening_hours', 'sa_ticket_price', 'sa_last_verified_date', 'sa_seo_title', 'sa_seo_description', 'sa_focus_keyword', 'sa_og_title', 'sa_og_description', 'sa_sources', 'sa_facts_checked', 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword', 'rank_math_facebook_title', 'rank_math_facebook_description' );
$incomplete = array();
$long_title = array();
$long_desc  = array();
$bad_date   = array();
$short_faq  = array();

foreach ( array_keys( $bundle ) as $slug ) {
	if ( ! isset( $articles[ $slug ] ) ) {
		continue;
	}
	$a = $articles[ $slug ];
	foreach ( $core_meta as $meta_key ) {
		if ( empty( $a['meta'][ $meta_key ] ) ) {
			$incomplete[] = $slug . ':' . $meta_key;
		}
	}
	if ( mb_strlen( (string) $a['meta']['sa_seo_title'] ) > 75 ) {
		$long_title[] = $slug;
	}
	if ( mb_strlen( (string) $a['meta']['sa_seo_description'] ) > 175 ) {
		$long_desc[] = $slug;
	}
	if ( ! preg_match( '~^\d{4}-\d{2}-\d{2}$~', (string) $a['meta']['sa_last_verified_date'] ) ) {
		$bad_date[] = $slug;
	}
	if ( count( $a['faq'] ) < 5 ) {
		$short_faq[] = $slug;
	}
	$bad_faq_rows = array();
	foreach ( $a['faq'] as $row ) {
		if ( empty( $row['q'] ) || empty( $row['a'] ) ) {
			$bad_faq_rows[] = $slug;
		}
	}
	sa_eq( 'پرسش‌های «' . $a['title'] . '» پرسش و پاسخ دارند', array(), $bad_faq_rows );
}

sa_eq( 'جعبهٔ متای چهار مدخلِ تازه کامل است', array(), $incomplete );
sa_eq( 'عنوان سئوی چهار مدخلِ تازه ≤ ۷۵ نویسه است', array(), $long_title );
sa_eq( 'توضیح متای چهار مدخلِ تازه ≤ ۱۷۵ نویسه است', array(), $long_desc );
sa_eq( 'تاریخ راستی‌آزماییِ چهار مدخلِ تازه درست است', array(), $bad_date );
sa_eq( 'هر مدخلِ تازه دست‌کم ۵ پرسش دارد', array(), $short_faq );

/* --------------------------------------- §۵. متنِ پاک (قواعدِ موتورِ لینک‌سازی) */

$banned = array();
foreach ( $articles as $key => $article ) {
	$text = (string) $article['content'];
	if ( false !== mb_strpos( $text, 'نمای برتر' ) || false !== mb_strpos( (string) $article['title'], 'نمای برتر' ) ) {
		$banned[] = (string) $key . ':نمای برتر';
	}
	if ( false !== strpos( $text, '<script' ) || false !== strpos( $text, 'style=' ) ) {
		$banned[] = (string) $key . ':نشانهٔ ناامن';
	}
	if ( false === mb_strpos( $text, '<h2' ) ) {
		$banned[] = (string) $key . ':بدون h2';
	}
}
sa_eq( 'نامِ قدیمی «نمای برتر» و نشانه‌های ناامن در بدنه نیست', array(), $banned );

/* --------------------------------- §۶. مقصدهای لینک‌شدهٔ بی‌مقاله (اطلاع‌رسانی) */

$pending = array();
foreach ( $articles as $key => $article ) {
	if ( ! preg_match_all( '~href="/attraction/([^"]+)/"~u', (string) $article['content'], $m ) ) {
		continue;
	}
	foreach ( $m[1] as $slug ) {
		if ( ! isset( $articles[ $slug ] ) && ! isset( $pending[ $slug ] ) ) {
			$pending[ $slug ] = 0;
		}
		if ( ! isset( $articles[ $slug ] ) ) {
			++$pending[ $slug ];
		}
	}
}
ksort( $pending );
echo "\n  i مقصدهای لینک‌شدهٔ بدونِ مقاله (از بدنهٔ همین مدخل‌ها): " . count( $pending ) . "\n";
foreach ( $pending as $slug => $hits ) {
	echo '    - ' . $slug . ' (' . $hits . " پیوند)\n";
}

sa_done();
