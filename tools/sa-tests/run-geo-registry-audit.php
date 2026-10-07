<?php
/**
 * Structural audit of the bundled province/county registries (not an official-source check).
 *
 * Run: node tools/phpwasm/exec.js tools/sa-tests/run-geo-registry-audit.php
 *
 * @package sa-tests
 */

require '/ws/wp-stubs.php';
require '/ws/assert.php';

define( 'SA_CHILD_DIR', '/ws/theme/' );
$provinces = (array) require '/ws/theme/data/provinces.php';
$counties  = (array) require '/ws/theme/data/counties.php';

set_error_handler(
	function ( $no, $str, $file, $line ) {
		throw new ErrorException( $str, 0, $no, $file, $line );
	}
);

$province_slugs  = array();
$province_names  = array();
$province_errors = array();
foreach ( $provinces as $row ) {
	$slug = isset( $row['slug'] ) ? (string) $row['slug'] : '';
	if ( '' === $slug || empty( $row['name'] ) || empty( $row['en'] ) || empty( $row['center'] ) ) {
		$province_errors[] = 'missing province field';
	}
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
		$province_errors[] = 'invalid province slug: ' . $slug;
	}
	$province_slugs[] = $slug;
	$province_names[] = isset( $row['name'] ) ? (string) $row['name'] : '';
}

$county_slug_counts = array();
$county_name_counts = array();
$province_counts    = array();
$status_counts      = array();
$county_errors      = array();
$known_provinces    = array_fill_keys( $province_slugs, true );
$known_statuses     = array( 'published', 'draft', 'empty', 'missing' );

foreach ( $counties as $row ) {
	$slug     = isset( $row['slug'] ) ? (string) $row['slug'] : '';
	$name     = isset( $row['name'] ) ? (string) $row['name'] : '';
	$province = isset( $row['province'] ) ? (string) $row['province'] : '';
	$status   = isset( $row['status'] ) ? (string) $row['status'] : '';

	if ( '' === $slug || '' === $name || '' === $province || '' === $status ) {
		$county_errors[] = 'missing required county field';
	}
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
		$county_errors[] = 'invalid county slug: ' . $slug;
	}
	if ( ! isset( $known_provinces[ $province ] ) ) {
		$county_errors[] = 'unknown province reference: ' . $province;
	}
	if ( ! in_array( $status, $known_statuses, true ) ) {
		$county_errors[] = 'unknown status: ' . $status;
	}

	$county_slug_counts[ $slug ] = isset( $county_slug_counts[ $slug ] ) ? $county_slug_counts[ $slug ] + 1 : 1;
	$county_name_counts[ $name ] = isset( $county_name_counts[ $name ] ) ? $county_name_counts[ $name ] + 1 : 1;
	$province_counts[ $province ] = isset( $province_counts[ $province ] ) ? $province_counts[ $province ] + 1 : 1;
	$status_counts[ $status ]     = isset( $status_counts[ $status ] ) ? $status_counts[ $status ] + 1 : 1;
}

$duplicate_slugs = array_filter( $county_slug_counts, function ( $count ) { return $count > 1; } );
$duplicate_names = array_filter( $county_name_counts, function ( $count ) { return $count > 1; } );
$provinces_without_counties = array_values( array_diff( $province_slugs, array_keys( $province_counts ) ) );
$invalid_province_counts = array_diff_key( $province_counts, $known_provinces );

sa_eq( 'رجیستری استان‌ها ۳۱ رکورد دارد', 31, count( $provinces ) );
sa_eq( 'اسلاگ استان‌ها یکتا است', count( $province_slugs ), count( array_unique( $province_slugs ) ) );
sa_eq( 'تمام فیلدهای لازم استان‌ها و الگوی slug معتبر است', array(), $province_errors );
sa_eq( 'رجیستری شهرستان‌ها ۴۸۳ رکورد دارد', 483, count( $counties ) );
sa_eq( 'slug شهرستان‌ها تکراری نیست', array(), $duplicate_slugs );
sa_eq( 'فیلدهای لازم، slug و ارجاع استان در شهرستان‌ها معتبرند', array(), $county_errors );
sa_eq( 'همهٔ استان‌ها دست‌کم یک شهرستان در رجیستری دارند', array(), $provinces_without_counties );
sa_eq( 'شهرستان بدون ارجاع معتبر به استان وجود ندارد', array(), $invalid_province_counts );

ksort( $status_counts );
ksort( $province_counts );

echo "\n== Snapshot ساختاری رجیستری (بدون راستی‌آزمایی رسمی) ==\n";
echo 'استان: ' . count( $provinces ) . ' | شهرستان: ' . count( $counties ) . "\n";
echo 'وضعیت ذخیره‌شده: ' . wp_json_encode( $status_counts, JSON_UNESCAPED_UNICODE ) . "\n";
echo 'شهرستان به تفکیک استان: ' . wp_json_encode( $province_counts, JSON_UNESCAPED_UNICODE ) . "\n";
echo 'نام شهرستان تکراری در رجیستری: ' . wp_json_encode( $duplicate_names, JSON_UNESCAPED_UNICODE ) . "\n";
echo 'محدودیت: status در فایل یک snapshot مورخ ۱۴۰۵/۰۷/۱۰ است؛ این تست ایندکس/وضعیت زنده یا صحت رسمی نام‌ها و تقسیمات را ثابت نمی‌کند.' . "\n";

sa_done();
