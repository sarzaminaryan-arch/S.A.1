<?php
/**
 * Standalone regression test: حفظ کدگذاری درصدی (percent-encoding) نشانی‌های فارسی در sa_sources و extra_places.
 *
 * هستهٔ وردپرس در sanitize_text_field / sanitize_textarea_field همهٔ دنباله‌های «%XX»
 * را حذف می‌کند و نشانی‌هایی مانند ویکی‌پدیای فارسی را به «https://fa.wikipedia.org/wiki/___»
 * تبدیل می‌کرد. این آزمون تضمین می‌کند که sa_sanitize_field(..., 'sources') و
 * sa_sanitize_textarea_preserve_urls نشانی‌های درصدکدگذاری‌شده را سالم نگه می‌دارند و
 * هم‌زمان تگ‌های HTML و تزریق بیرون از نشانی را پاک می‌کنند.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-sources-sanitization.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function sa_ss_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function add_action() { return true; }
function add_filter() { return true; }
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function wp_check_invalid_utf8( $text ) { return (string) $text; }
function absint( $maybeint ) { return abs( (int) $maybeint ); }
function sa_en_digits( $s ) {
	return strtr( (string) $s, array( '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ) );
}

/**
 * شبیه‌سازی دقیق رفتار هستهٔ وردپرس (_sanitize_text_fields) که دنباله‌های %XX را حذف می‌کند.
 *
 * @param string $str          متن خام.
 * @param bool   $keep_newlines حفظ خط جدید.
 * @return string
 */
function sa_ss_wp_sanitize_text( $str, $keep_newlines = false ) {
	$filtered = (string) $str;
	$filtered = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $filtered );
	$filtered = strip_tags( (string) $filtered );
	if ( ! $keep_newlines ) {
		$filtered = preg_replace( '/[\r\n\t ]+/', ' ', (string) $filtered );
	}
	$filtered = trim( (string) $filtered );
	while ( preg_match( '/%[a-f0-9]{2}/i', $filtered, $match ) ) {
		$filtered = str_replace( $match[0], '', $filtered );
	}
	return trim( $filtered );
}

function sanitize_text_field( $str ) {
	return sa_ss_wp_sanitize_text( $str, false );
}

function sanitize_textarea_field( $str ) {
	return sa_ss_wp_sanitize_text( $str, true );
}

function esc_url_raw( $url ) {
	$url = trim( (string) $url );
	if ( ! preg_match( '~^https?://~i', $url ) ) {
		return '';
	}
	return (string) preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
}

$theme_dir = dirname( __DIR__ );
require_once $theme_dir . '/inc/meta-fields.php';

/* 1. اثبات بازتولید باگ قدیمی در sanitize_textarea_field */
$wiki_url = 'https://fa.wikipedia.org/wiki/%D8%A8%D8%A7%D8%BA_%D8%A7%D8%B1%D9%85';
$irna_url = 'https://www.irna.ir/news/86251945/%D8%AD%DB%8C%D8%A7%D8%AA-%D9%88%D8%AD%D8%B4';
$raw_sources = "باغ ارم شیراز | ویکی‌پدیای فارسی | {$wiki_url} | ۱۴۰۵/۰۸/۰۱\r\n"
	. "گزارش محیط زیست البرز | ایرنا | {$irna_url} | ۱۴۰۵/۰۷/۰۴\n"
	. "---\n"
	. "یادداشت خصوصی بازبینی | {$wiki_url}";

sa_ss_assert(
	false === strpos( sanitize_textarea_field( $raw_sources ), '%D8%A8' ),
	'baseline check: WP sanitize_textarea_field strips %XX octets'
);

/* 2. حفظ کامل کدگذاری درصدی در sa_sanitize_field(..., sources) */
$cleaned = sa_sanitize_field( $raw_sources, 'sources' );
sa_ss_assert(
	false !== strpos( $cleaned, $wiki_url ),
	'percent-encoded Wikipedia URL must survive sa_sanitize_field(sources)'
);
sa_ss_assert(
	false !== strpos( $cleaned, $irna_url ),
	'percent-encoded IRNA URL must survive sa_sanitize_field(sources)'
);
sa_ss_assert(
	false !== strpos( $cleaned, "\n---\nیادداشت خصوصی بازبینی | {$wiki_url}" ),
	'private editor notes after --- separator and their URLs must remain intact'
);

/* 3. پاک‌سازی تگ‌های مخرب و تزریق %XX در بیرون از نشانی */
$malicious = "<script>alert(1)</script><b>عنوان</b> %3Csvg%3E | سازمان | https://fa.wikipedia.org/wiki/%D8%AF%D9%88%D8%B1%D9%88%D8%AF\"><script>x</script> | ۱۴۰۵/۰۸/۰۱";
$safe      = sa_sanitize_field( $malicious, 'sources' );
sa_ss_assert( false === strpos( $safe, '<script>' ) && false === strpos( $safe, '<b>' ), 'HTML and script tags must be stripped' );
sa_ss_assert( false === strpos( $safe, '%3C' ) && false === strpos( $safe, '%3E' ), 'percent-encoded octets outside URLs must still be stripped' );
sa_ss_assert(
	false !== strpos( $safe, 'https://fa.wikipedia.org/wiki/%D8%AF%D9%88%D8%B1%D9%88%D8%AF' ),
	'valid percent-encoded URL before quote injection must survive cleanly'
);

/* 4. بررسی استاتیک ثبت نوع sources برای sa_sources و extra_places */
$meta_src    = (string) file_get_contents( $theme_dir . '/inc/meta-fields.php' );
$profile_src = (string) file_get_contents( $theme_dir . '/inc/county-profile.php' );
sa_ss_assert(
	(bool) preg_match( "/'sa_sources'\s*=>\s*'sources'/", $meta_src ),
	'sa_save_meta must map sa_sources to the sources sanitizer'
);
sa_ss_assert(
	false !== strpos( $profile_src, "'extra_places' === \$key" ) && false !== strpos( $profile_src, 'sa_sanitize_textarea_preserve_urls' ),
	'sa_county_profile_save must preserve percent-encoded URLs in extra_places'
);

echo "PASS: sources & multiline URL sanitization preserves percent-encoded Persian URLs safely\n";
