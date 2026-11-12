<?php
/**
 * Standalone test: SEO metadata has exactly one source of truth — the theme.
 *
 * Phase 3 of docs/live-site/ROADMAP.md (2026-11-11) verified on the live site
 * that the theme (inc/seo.php) produces <title>/description/canonical/OG and
 * no SEO plugin is active. The ready-article data therefore must NOT carry
 * rank_math_* mirror fields anymore: they were inert, drifted out of sync on
 * 7 records, and made every audit reconciliation report phantom differences.
 *
 * This test guards the decision:
 *  1. No record in data/ready-articles.php or data/ready-guides.php may carry
 *     any meta key starting with "rank_math_".
 *  2. Every record must carry a non-empty sa_seo_title and sa_seo_description
 *     (the fields the theme actually outputs).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-seo-single-source.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

function sa_seo_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$theme_dir = dirname( __DIR__ );
$files     = array(
	'attractions' => $theme_dir . '/data/ready-articles.php',
	'guides'      => $theme_dir . '/data/ready-guides.php',
);

$total = 0;
foreach ( $files as $label => $file ) {
	$records = include $file;
	sa_seo_assert( is_array( $records ) && $records, "{$label}: data file returns records" );
	foreach ( $records as $key => $record ) {
		++$total;
		$meta = isset( $record['meta'] ) && is_array( $record['meta'] ) ? $record['meta'] : array();
		foreach ( array_keys( $meta ) as $meta_key ) {
			sa_seo_assert(
				0 !== strpos( (string) $meta_key, 'rank_math_' ),
				"{$label}/{$key}: rank_math_* mirror field '{$meta_key}' must not exist (theme is the single SEO source)"
			);
		}
		sa_seo_assert( '' !== trim( (string) ( isset( $meta['sa_seo_title'] ) ? $meta['sa_seo_title'] : '' ) ), "{$label}/{$key}: sa_seo_title must not be empty" );
		sa_seo_assert( '' !== trim( (string) ( isset( $meta['sa_seo_description'] ) ? $meta['sa_seo_description'] : '' ) ), "{$label}/{$key}: sa_seo_description must not be empty" );
	}
}

echo "PASS: SEO single source of truth ({$total} ready records; no rank_math_* mirrors; sa_seo_title/description present)\n";
