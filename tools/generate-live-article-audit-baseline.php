<?php
/**
 * ساخت data/baseline.json افزونهٔ «ممیزی مقالات زنده» از داده‌های آمادهٔ قالب فرزند.
 *
 * اجرا (فقط CLI، در CI پیش از بسته‌بندی ZIP):
 *   php tools/generate-live-article-audit-baseline.php [خروجی.json]
 *
 * پیش‌فرض خروجی: wp-content/plugins/sa-live-article-audit/data/baseline.json
 * این فایل تولیدی است و نباید دستی ویرایش یا جداگانه کامیت شود؛ منبع حقیقت
 * همان data/ready-articles.php و data/ready-guides.php قالب فرزند است.
 * فایل‌های داده فقط آرایه برمی‌گردانند و هیچ تابع وردپرسی صدا نمی‌زنند.
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'wb' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- ابزار CLI.
}
if ( 'cli' !== PHP_SAPI && ! defined( 'SA_LAA_ALLOW_NON_CLI' ) ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}
if ( ! defined( 'SA_LAA_DIR' ) ) {
	define( 'SA_LAA_DIR', $root . '/wp-content/plugins/sa-live-article-audit/' );
}
if ( ! defined( 'SA_LAA_VERSION' ) ) {
	define( 'SA_LAA_VERSION', 'generator' );
}

require_once SA_LAA_DIR . 'includes/class-sa-laa-compare.php';
require_once SA_LAA_DIR . 'includes/class-sa-laa-baseline.php';

$sources = array(
	'attractions' => $root . '/wp-content/themes/sarzaminaryan-child/data/ready-articles.php',
	'guides'      => $root . '/wp-content/themes/sarzaminaryan-child/data/ready-guides.php',
);

$merged = array();
foreach ( $sources as $file ) {
	if ( ! file_exists( $file ) ) {
		fwrite( STDERR, "Missing data file: {$file}\n" );
		exit( 1 );
	}
	$set = include $file;
	if ( ! is_array( $set ) ) {
		fwrite( STDERR, "Data file did not return an array: {$file}\n" );
		exit( 1 );
	}
	foreach ( $set as $key => $article ) {
		$merged[ $key ] = $article;
	}
}

$records = SA_LAA_Baseline::records_from_theme_data( $merged );
foreach ( $records as $i => $record ) {
	$records[ $i ]['source'] = 'repository-baseline';
}

$out_path = isset( $argv[1] ) ? (string) $argv[1] : SA_LAA_DIR . 'data/baseline.json';
$payload  = array(
	'tool'           => 'sa-live-article-audit-baseline',
	'plugin_version' => SA_LAA_VERSION,
	'generated_at'   => gmdate( 'c' ),
	'repository'     => array(
		'sources' => array(
			'wp-content/themes/sarzaminaryan-child/data/ready-articles.php',
			'wp-content/themes/sarzaminaryan-child/data/ready-guides.php',
		),
		'note'    => 'ساخته‌شده در CI از داده‌های آمادهٔ قالب فرزند؛ دستی ویرایش نشود.',
	),
	'count'          => count( $records ),
	'records'        => $records,
);

$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $json ) ) {
	fwrite( STDERR, "json_encode failed.\n" );
	exit( 1 );
}
if ( false === file_put_contents( $out_path, $json . "\n" ) ) {
	fwrite( STDERR, "Cannot write: {$out_path}\n" );
	exit( 1 );
}
echo 'baseline: ' . $out_path . ' (' . count( $records ) . " records)\n";
