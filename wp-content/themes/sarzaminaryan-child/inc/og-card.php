<?php
/**
 * کارت تصویر اشتراک‌گذاری (og:image) برای صفحه‌های بی‌تصویر — v2.22.74.
 *
 * ممیزی ۲۰۲۶-۱۱-۱۵: ۵۹۳ صفحه از ۶۲۷ صفحه og:image نداشتند. مالک انتخاب را به
 * «نزدیک‌ترین گزینه به استاندارد گوگل» سپرد. قاعدهٔ اجراشده:
 *
 *   ۱) اگر صفحه تصویر شاخصِ واقعی دارد، همان (مثل قبل).
 *   ۲) وگرنه یک کارت یکتای ۱۲۰۰×۶۳۰ برای همان صفحه (عنوان + نوع + استان/شهرستان)،
 *      نه لوگوی عمومی سایت (گوگل استفاده از لوگو را به‌جای تصویر صفحه توصیه نمی‌کند).
 *
 * کارت یک‌بار با GD ساخته می‌شود و در `uploads/sa-og/` می‌ماند. نام فایل از متن کارت
 * ساخته می‌شود، پس اگر عنوان عوض شود کارت تازه ساخته می‌شود. هیچ نوشته‌ای ویرایش
 * نمی‌شود و تصویر شاخص و کتابخانهٔ رسانه دست نمی‌خورد. اگر GD، قلم یا پوشهٔ قابل‌نوشتن
 * نباشد، رفتار مثل قبل است (بی‌کارت).
 *
 * GD حروف فارسی را به هم نمی‌چسباند، برای همین `sa_og_shape()` متن را به «شکل‌های
 * نمایشی» یونیکد (آغازی/میانی/پایانی/تنها) و ترتیب دیداری راست‌به‌چپ تبدیل می‌کند.
 *
 * قلم: Vazirmatn Bold (SIL OFL 1.1، متن پروانه در assets/fonts/og/OFL.txt).
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SA_OG_CARD_VERSION' ) ) {
	define( 'SA_OG_CARD_VERSION', '1' );
}

/* -------------------------------------------------------------------------
 * ۱) شکل‌دهی متن فارسی برای GD.
 * ---------------------------------------------------------------------- */

/**
 * جدول شکل‌های نمایشی: [تنها، پایانی، آغازی، میانی]؛ حروف راست‌پیوند فقط دو شکل دارند.
 *
 * @return array<int,int[]>
 */
function sa_og_forms() {
	static $f = null;
	if ( null !== $f ) {
		return $f;
	}
	$f = array(
		0x0621 => array( 0xFE80 ),
		0x0622 => array( 0xFE81, 0xFE82 ),
		0x0623 => array( 0xFE83, 0xFE84 ),
		0x0624 => array( 0xFE85, 0xFE86 ),
		0x0625 => array( 0xFE87, 0xFE88 ),
		0x0626 => array( 0xFE89, 0xFE8A, 0xFE8B, 0xFE8C ),
		0x0627 => array( 0xFE8D, 0xFE8E ),
		0x0628 => array( 0xFE8F, 0xFE90, 0xFE91, 0xFE92 ),
		0x0629 => array( 0xFE93, 0xFE94 ),
		0x062A => array( 0xFE95, 0xFE96, 0xFE97, 0xFE98 ),
		0x062B => array( 0xFE99, 0xFE9A, 0xFE9B, 0xFE9C ),
		0x062C => array( 0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0 ),
		0x062D => array( 0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4 ),
		0x062E => array( 0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8 ),
		0x062F => array( 0xFEA9, 0xFEAA ),
		0x0630 => array( 0xFEAB, 0xFEAC ),
		0x0631 => array( 0xFEAD, 0xFEAE ),
		0x0632 => array( 0xFEAF, 0xFEB0 ),
		0x0633 => array( 0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4 ),
		0x0634 => array( 0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8 ),
		0x0635 => array( 0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC ),
		0x0636 => array( 0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0 ),
		0x0637 => array( 0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4 ),
		0x0638 => array( 0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8 ),
		0x0639 => array( 0xFEC9, 0xFECA, 0xFECB, 0xFECC ),
		0x063A => array( 0xFECD, 0xFECE, 0xFECF, 0xFED0 ),
		0x0641 => array( 0xFED1, 0xFED2, 0xFED3, 0xFED4 ),
		0x0642 => array( 0xFED5, 0xFED6, 0xFED7, 0xFED8 ),
		0x0643 => array( 0xFED9, 0xFEDA, 0xFEDB, 0xFEDC ),
		0x0644 => array( 0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0 ),
		0x0645 => array( 0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4 ),
		0x0646 => array( 0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8 ),
		0x0647 => array( 0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC ),
		0x0648 => array( 0xFEED, 0xFEEE ),
		0x0649 => array( 0xFEEF, 0xFEF0 ),
		0x064A => array( 0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4 ),
		0x067E => array( 0xFB56, 0xFB57, 0xFB58, 0xFB59 ),
		0x0686 => array( 0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D ),
		0x0698 => array( 0xFB8A, 0xFB8B ),
		0x06A9 => array( 0xFB8E, 0xFB8F, 0xFB90, 0xFB91 ),
		0x06AF => array( 0xFB92, 0xFB93, 0xFB94, 0xFB95 ),
		0x06C0 => array( 0xFBA4, 0xFBA5 ),
		0x06CC => array( 0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF ),
		0x0640 => array( 0x0640, 0x0640, 0x0640, 0x0640 ),
	);
	return $f;
}

/**
 * نویسهٔ شفاف (اعراب) که در پیوستن نادیده گرفته می‌شود.
 *
 * @param int $cp کد.
 * @return bool
 */
function sa_og_is_transparent( $cp ) {
	return ( $cp >= 0x064B && $cp <= 0x065F ) || 0x0670 === $cp;
}

/**
 * کد یونیکد → UTF-8.
 *
 * @param int $cp کد.
 * @return string
 */
function sa_og_chr( $cp ) {
	if ( function_exists( 'mb_chr' ) ) {
		return (string) mb_chr( $cp, 'UTF-8' );
	}
	return (string) html_entity_decode( '&#' . (int) $cp . ';', ENT_NOQUOTES, 'UTF-8' );
}

/**
 * کد یونیکد یک نویسهٔ UTF-8 (بی‌نیاز به mbstring).
 *
 * @param string $ch نویسه.
 * @return int
 */
function sa_og_ord( $ch ) {
	if ( function_exists( 'mb_ord' ) ) {
		return (int) mb_ord( $ch, 'UTF-8' );
	}
	$b = array_values( (array) unpack( 'C*', (string) $ch ) );
	$n = count( $b );
	if ( 1 === $n ) {
		return $b[0];
	}
	if ( 2 === $n ) {
		return ( ( $b[0] & 0x1F ) << 6 ) | ( $b[1] & 0x3F );
	}
	if ( 3 === $n ) {
		return ( ( $b[0] & 0x0F ) << 12 ) | ( ( $b[1] & 0x3F ) << 6 ) | ( $b[2] & 0x3F );
	}
	return ( ( $b[0] & 0x07 ) << 18 ) | ( ( $b[1] & 0x3F ) << 12 ) | ( ( $b[2] & 0x3F ) << 6 ) | ( $b[3] & 0x3F );
}

/**
 * متن منطقی فارسی → رشتهٔ شکل‌دهی‌شده (هنوز با ترتیب منطقی).
 *
 * @param string $text متن.
 * @return string
 */
function sa_og_join( $text ) {
	$forms = sa_og_forms();
	$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	$cps   = array();
	foreach ( (array) $chars as $ch ) {
		$cps[] = sa_og_ord( $ch );
	}
	$n   = count( $cps );
	$out = '';
	// همسایهٔ غیرشفاف.
	$neighbor = function ( $i, $dir ) use ( $cps, $n ) {
		for ( $j = $i + $dir; $j >= 0 && $j < $n; $j += $dir ) {
			if ( ! sa_og_is_transparent( $cps[ $j ] ) ) {
				return $j;
			}
		}
		return -1;
	};
	$dual    = function ( $cp ) use ( $forms ) {
		return isset( $forms[ $cp ] ) && 4 === count( $forms[ $cp ] );
	};
	$joining = function ( $cp ) use ( $forms ) {
		return isset( $forms[ $cp ] ) && count( $forms[ $cp ] ) >= 2;
	};
	$lam_alef = array(
		0x0622 => array( 0xFEF5, 0xFEF6 ),
		0x0623 => array( 0xFEF7, 0xFEF8 ),
		0x0625 => array( 0xFEF9, 0xFEFA ),
		0x0627 => array( 0xFEFB, 0xFEFC ),
	);
	for ( $i = 0; $i < $n; $i++ ) {
		$cp = $cps[ $i ];
		if ( 0x200C === $cp ) {
			continue; // نیم‌فاصله: فقط پیوند را می‌شکند (در خودِ جدول پیوستن لحاظ شده).
		}
		if ( ! isset( $forms[ $cp ] ) ) {
			$out .= sa_og_chr( $cp );
			continue;
		}
		$p        = $neighbor( $i, -1 );
		$prev_j   = $p >= 0 && $dual( $cps[ $p ] );
		$nx       = $neighbor( $i, 1 );
		$next_cp  = $nx >= 0 ? $cps[ $nx ] : 0;
		if ( 0x0644 === $cp && isset( $lam_alef[ $next_cp ] ) ) {
			$out .= sa_og_chr( $lam_alef[ $next_cp ][ $prev_j ? 1 : 0 ] );
			// اعراب میان لام و الف (نادر) کنار گذاشته می‌شود.
			$i = $nx;
			continue;
		}
		$f      = $forms[ $cp ];
		$next_j = $dual( $cp ) && $nx >= 0 && $joining( $next_cp );
		if ( 1 === count( $f ) ) {
			$out .= sa_og_chr( $f[0] );
		} elseif ( 2 === count( $f ) ) {
			$out .= sa_og_chr( $f[ $prev_j ? 1 : 0 ] );
		} elseif ( $prev_j && $next_j ) {
			$out .= sa_og_chr( $f[3] );
		} elseif ( $prev_j ) {
			$out .= sa_og_chr( $f[1] );
		} elseif ( $next_j ) {
			$out .= sa_og_chr( $f[2] );
		} else {
			$out .= sa_og_chr( $f[0] );
		}
	}
	return $out;
}

/**
 * ترتیب دیداری برای پاراگراف راست‌به‌چپ: ترتیب تکه‌ها برعکس می‌شود، تکهٔ فارسی
 * نویسه‌به‌نویسه برعکس می‌شود (با قرینه‌سازی پرانتز و گیومه)، تکهٔ لاتین/عدد
 * ترتیب خودش را نگه می‌دارد.
 *
 * @param string $shaped متن شکل‌دهی‌شده.
 * @return string
 */
function sa_og_visual( $shaped ) {
	$parts = preg_split( '~([A-Za-z0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}](?:[A-Za-z0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669} .,:/%+_@\-]*[A-Za-z0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}])?)~u', (string) $shaped, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
	$mirror = array( '(' => ')', ')' => '(', '«' => '»', '»' => '«', '[' => ']', ']' => '[', '<' => '>', '>' => '<' );
	$out    = array();
	foreach ( (array) $parts as $part ) {
		if ( preg_match( '~^[A-Za-z0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]~u', $part ) ) {
			$out[] = $part;
			continue;
		}
		$chars = preg_split( '//u', $part, -1, PREG_SPLIT_NO_EMPTY );
		$chars = array_reverse( (array) $chars );
		foreach ( $chars as $k => $c ) {
			if ( isset( $mirror[ $c ] ) ) {
				$chars[ $k ] = $mirror[ $c ];
			}
		}
		$out[] = implode( '', $chars );
	}
	return implode( '', array_reverse( $out ) );
}

/**
 * متن فارسی → رشتهٔ آمادهٔ imagettftext.
 *
 * @param string $text متن.
 * @return string
 */
function sa_og_shape( $text ) {
	return sa_og_visual( sa_og_join( $text ) );
}

/* -------------------------------------------------------------------------
 * ۲) متن کارت.
 * ---------------------------------------------------------------------- */

/**
 * متن ساده از عنوان.
 *
 * @param string $s متن.
 * @return string
 */
function sa_og_plain( $s ) {
	$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' );
	return trim( (string) preg_replace( '/\s+/u', ' ', $s ) );
}

/**
 * متن کارت یک نوشته.
 *
 * @param int $post_id شناسه.
 * @return array{kicker:string,title:string,sub:string}|null
 */
function sa_og_card_text( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return null;
	}
	$title = sa_og_plain( $post->post_title );
	if ( '' === $title ) {
		return null;
	}
	$province = '';
	$city     = '';
	if ( 'city' === $post->post_type ) {
		$pid = (int) get_post_meta( $post->ID, 'sa_province_id', true );
		if ( $pid && function_exists( 'sa_province_name_for_post' ) ) {
			$province = (string) sa_province_name_for_post( $pid );
		}
	} elseif ( in_array( $post->post_type, array( 'attraction', 'local_food', 'souvenir', 'travel_route' ), true ) ) {
		$cid = (int) get_post_meta( $post->ID, 'sa_city_id', true );
		if ( $cid && 'publish' === get_post_status( $cid ) ) {
			$city = sa_og_plain( get_the_title( $cid ) );
			$pid  = (int) get_post_meta( $cid, 'sa_province_id', true );
			if ( $pid && function_exists( 'sa_province_name_for_post' ) ) {
				$province = (string) sa_province_name_for_post( $pid );
			}
		}
		if ( '' === $province ) {
			$pid = (int) get_post_meta( $post->ID, 'sa_province_id', true );
			if ( $pid && function_exists( 'sa_province_name_for_post' ) ) {
				$province = (string) sa_province_name_for_post( $pid );
			}
		}
	}
	switch ( $post->post_type ) {
		case 'city':
			$kicker = 'راهنمای سفر شهرستان';
			$sub    = '' !== $province ? 'استان ' . $province : '';
			break;
		case 'province':
			$kicker = 'راهنمای سفر استان';
			$sub    = 'شهرستان‌ها، دیدنی‌ها و فصل سفر';
			break;
		case 'attraction':
			$kicker = 'دیدنی';
			$sub    = trim( $city . ( '' !== $city && '' !== $province ? '، ' : '' ) . ( '' !== $province ? 'استان ' . $province : '' ) );
			break;
		case 'local_food':
			$kicker = 'غذای محلی';
			$sub    = '' !== $province ? 'استان ' . $province : '';
			break;
		case 'souvenir':
			$kicker = 'سوغات';
			$sub    = '' !== $province ? 'استان ' . $province : '';
			break;
		case 'travel_route':
			$kicker = 'مسیر سفر';
			$sub    = '';
			break;
		case 'post':
			$cats   = get_the_category( $post->ID );
			$kicker = ( $cats && ! is_wp_error( $cats ) ) ? sa_og_plain( $cats[0]->name ) : 'راهنما';
			$sub    = '';
			break;
		default:
			$kicker = function_exists( 'sa_brand_name' ) ? sa_brand_name() : '';
			$sub    = '';
	}
	return array( 'kicker' => $kicker, 'title' => $title, 'sub' => $sub );
}

/**
 * متن کارت عمومی سایت (صفحهٔ اول، آرشیوها).
 *
 * @return array{kicker:string,title:string,sub:string}
 */
function sa_og_site_text() {
	return array(
		'kicker' => function_exists( 'sa_brand_name' ) ? sa_brand_name() : '',
		'title'  => 'دانشنامهٔ سفر ایران',
		'sub'    => '۳۱ استان، شهرستان‌ها و دیدنی‌ها',
	);
}

/* -------------------------------------------------------------------------
 * ۳) ساخت و نگه‌داری فایل.
 * ---------------------------------------------------------------------- */

/**
 * آیا ساخت کارت روی این سرور ممکن است؟
 *
 * @return bool
 */
function sa_og_card_supported() {
	if ( ! get_theme_mod( 'sa_og_cards', true ) ) {
		return false;
	}
	foreach ( array( 'imagecreatetruecolor', 'imagettftext', 'imagettfbbox', 'imagejpeg', 'gd_info' ) as $fn ) {
		if ( ! function_exists( $fn ) ) {
			return false;
		}
	}
	static $probe = null;
	if ( null === $probe ) {
		// imagettftext بی‌FreeType هم تعریف شده ولی چیزی نمی‌کشد؛ پس یک‌بار واقعاً اندازه می‌گیریم.
		$info  = gd_info();
		$probe = ! empty( $info['JPEG Support'] ) && is_readable( sa_og_font_file() ) && is_array( @imagettfbbox( 20, 0, sa_og_font_file(), 'ab' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	return $probe;
}

/**
 * مسیر قلم.
 *
 * @return string
 */
function sa_og_font_file() {
	return ( defined( 'SA_CHILD_DIR' ) ? SA_CHILD_DIR : trailingslashit( dirname( __DIR__ ) ) ) . 'assets/fonts/og/Vazirmatn-Bold.ttf';
}

/**
 * پهنای متن شکل‌دهی‌شده.
 *
 * @param string $visual متن دیداری.
 * @param float  $size   اندازه.
 * @return int
 */
function sa_og_text_width( $visual, $size ) {
	$box = imagettfbbox( $size, 0, sa_og_font_file(), $visual );
	return $box ? (int) abs( $box[2] - $box[0] ) : 0;
}

/**
 * شکستن عنوان به سطرها (بر پایهٔ واژه‌های منطقی).
 *
 * @param string $text  متن.
 * @param float  $size  اندازه.
 * @param int    $max_w بیشینهٔ پهنا.
 * @return string[] سطرهای منطقی.
 */
function sa_og_wrap( $text, $size, $max_w ) {
	$words = preg_split( '/ +/u', trim( (string) $text ) );
	$lines = array();
	$cur   = '';
	foreach ( (array) $words as $w ) {
		$try = '' === $cur ? $w : $cur . ' ' . $w;
		if ( '' !== $cur && sa_og_text_width( sa_og_shape( $try ), $size ) > $max_w ) {
			$lines[] = $cur;
			$cur     = $w;
		} else {
			$cur = $try;
		}
	}
	if ( '' !== $cur ) {
		$lines[] = $cur;
	}
	return $lines;
}

/**
 * نام فایل کارت.
 *
 * @param string $key  کلید (مثل city-153 یا site).
 * @param array  $text متن.
 * @return string
 */
function sa_og_card_filename( $key, array $text ) {
	$hash = substr( md5( SA_OG_CARD_VERSION . '|' . $text['kicker'] . '|' . $text['title'] . '|' . $text['sub'] ), 0, 10 );
	return sanitize_file_name( $key ) . '-' . $hash . '.jpg';
}

/**
 * پوشهٔ کارت‌ها.
 *
 * @return array{dir:string,url:string}|null
 */
function sa_og_card_dir() {
	$up = wp_upload_dir( null, false );
	if ( ! empty( $up['error'] ) || empty( $up['basedir'] ) ) {
		return null;
	}
	return array(
		'dir' => trailingslashit( $up['basedir'] ) . 'sa-og',
		'url' => trailingslashit( $up['baseurl'] ) . 'sa-og',
	);
}

/**
 * کشیدن کارت روی فایل.
 *
 * @param array  $text متن کارت.
 * @param string $file مسیر خروجی.
 * @return bool
 */
function sa_og_render_card( array $text, $file ) {
	$w    = 1200;
	$h    = 630;
	$font = sa_og_font_file();
	$im   = imagecreatetruecolor( $w, $h );
	if ( ! $im ) {
		return false;
	}
	// پس‌زمینهٔ سرمه‌ای با شیب ملایم (رنگ‌های برند: navy-950 → navy-800).
	$from = array( 7, 26, 48 );
	$to   = array( 18, 63, 115 );
	for ( $y = 0; $y < $h; $y++ ) {
		$t = $y / ( $h - 1 );
		$c = imagecolorallocate( $im, (int) ( $from[0] + ( $to[0] - $from[0] ) * $t ), (int) ( $from[1] + ( $to[1] - $from[1] ) * $t ), (int) ( $from[2] + ( $to[2] - $from[2] ) * $t ) );
		imageline( $im, 0, $y, $w, $y, $c );
	}
	$white  = imagecolorallocate( $im, 255, 255, 255 );
	$light  = imagecolorallocate( $im, 189, 216, 241 ); // navy-200.
	$accent = imagecolorallocate( $im, 142, 185, 227 ); // navy-300.
	$pad    = 80;
	$right  = $w - $pad;
	$max_w  = $w - 2 * $pad;

	// برچسب نوع، بالا راست، با خط تأکید.
	$y = 150;
	if ( '' !== $text['kicker'] ) {
		$k  = sa_og_shape( $text['kicker'] );
		$kw = sa_og_text_width( $k, 26 );
		imagettftext( $im, 26, 0, $right - $kw, $y, $light, $font, $k );
		imagefilledrectangle( $im, $right - 90, $y + 22, $right, $y + 28, $accent );
	}

	// عنوان: تا دو سطر؛ اگر جا نشد کوچک‌تر، و در آخر با «…».
	$lines = array();
	$size  = 60;
	foreach ( array( 60, 52, 44 ) as $size ) {
		$lines = sa_og_wrap( $text['title'], $size, $max_w );
		if ( count( $lines ) <= 2 ) {
			break;
		}
	}
	if ( count( $lines ) > 2 ) {
		$lines    = array_slice( $lines, 0, 2 );
		$lines[1] = $lines[1] . '…';
		while ( sa_og_text_width( sa_og_shape( $lines[1] ), $size ) > $max_w && mb_strlen( $lines[1] ) > 2 ) {
			$lines[1] = mb_substr( $lines[1], 0, -2 ) . '…';
		}
	}
	$line_h = (int) round( $size * 1.75 );
	$y      = 270;
	$last   = $y;
	foreach ( $lines as $line ) {
		$v  = sa_og_shape( $line );
		$lw = sa_og_text_width( $v, $size );
		imagettftext( $im, $size, 0, $right - $lw, $y, $white, $font, $v );
		$last = $y;
		$y   += $line_h;
	}

	// زیرعنوان.
	if ( '' !== $text['sub'] ) {
		$s  = sa_og_shape( $text['sub'] );
		$sz = 30;
		while ( sa_og_text_width( $s, $sz ) > $max_w && $sz > 18 ) {
			$sz -= 2;
		}
		imagettftext( $im, $sz, 0, $right - sa_og_text_width( $s, $sz ), $last + 72, $light, $font, $s );
	}

	// پایین: نشانی سایت (راست) و لوگو (چپ).
	imagefilledrectangle( $im, $pad, $h - 150, $w - $pad, $h - 148, imagecolorallocatealpha( $im, 255, 255, 255, 100 ) );
	$domain = 'sarzaminaryan.ir';
	if ( function_exists( 'home_url' ) ) {
		$host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$domain = $host ? preg_replace( '/^www\./', '', (string) $host ) : $domain;
	}
	imagettftext( $im, 24, 0, $right - sa_og_text_width( $domain, 24 ), $h - 70, $light, $font, $domain );
	$logo_file = ( defined( 'SA_CHILD_DIR' ) ? SA_CHILD_DIR : trailingslashit( dirname( __DIR__ ) ) ) . 'assets/img/og-logo-light-360.png';
	if ( function_exists( 'imagecreatefrompng' ) && is_readable( $logo_file ) ) {
		$logo = @imagecreatefrompng( $logo_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( $logo ) {
			imagealphablending( $im, true );
			$lw = imagesx( $logo );
			$lh = imagesy( $logo );
			$tw = 240;
			$th = (int) round( $lh * $tw / $lw );
			imagecopyresampled( $im, $logo, $pad, $h - 40 - $th, 0, 0, $tw, $th, $lw, $lh );
			if ( PHP_VERSION_ID < 80000 ) {
				imagedestroy( $logo );
			}
		}
	}

	$tmp = $file . '.tmp-' . getmypid();
	$ok  = imagejpeg( $im, $tmp, 86 );
	if ( PHP_VERSION_ID < 80000 ) {
		imagedestroy( $im );
	}
	if ( ! $ok || ! file_exists( $tmp ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return false;
	}
	return @rename( $tmp, $file ); // phpcs:ignore
}

/**
 * نشانی کارت (در صورت نبودن، ساخته می‌شود).
 *
 * @param string     $key  کلید.
 * @param array|null $text متن.
 * @return string نشانی یا رشتهٔ خالی.
 */
function sa_og_card_url_for( $key, $text ) {
	if ( ! is_array( $text ) || ! sa_og_card_supported() ) {
		return '';
	}
	$dir = sa_og_card_dir();
	if ( null === $dir ) {
		return '';
	}
	$name = sa_og_card_filename( $key, $text );
	$path = $dir['dir'] . '/' . $name;
	if ( ! file_exists( $path ) ) {
		if ( ! wp_mkdir_p( $dir['dir'] ) || ! wp_is_writable( $dir['dir'] ) ) {
			return '';
		}
		try {
			if ( ! sa_og_render_card( $text, $path ) ) {
				return '';
			}
		} catch ( \Throwable $e ) {
			return '';
		}
	}
	return $dir['url'] . '/' . $name;
}

/**
 * نشانی کارت یک نوشته.
 *
 * @param int $post_id شناسه.
 * @return string
 */
function sa_og_card_url( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return '';
	}
	return sa_og_card_url_for( $post->post_type . '-' . (int) $post->ID, sa_og_card_text( $post->ID ) );
}

/**
 * نشانی کارت عمومی سایت.
 *
 * @return string
 */
function sa_og_site_card_url() {
	return sa_og_card_url_for( 'site', sa_og_site_text() );
}
