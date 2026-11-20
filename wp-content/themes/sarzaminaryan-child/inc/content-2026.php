<?php
/**
 * Content presentation for the 2026 design.
 *
 * Owner report (۲۹ آبان ۱۴۰۵): «جدول‌ها تمیز نشان داده نمی‌شوند؛ در بخش آب‌وهوا و
 * پیشینه جدول‌ها حذف شوند و متن استفاده شود.» The tables live inside the article
 * body (built by the county pipeline), not in the templates, so they are converted
 * once, at render time, into a compact key/value list that reads like text and
 * wraps correctly on phones. Tables with more than two columns keep every cell:
 * the first cell becomes the term and the rest are joined into the definition.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this a table that should become prose?
 *
 * @return bool
 */
function sa_2026_tables_to_text_enabled() {
	$on = (bool) apply_filters( 'sa_2026_tables_to_text', true );

	return $on && function_exists( 'is_singular' ) && is_singular() && ! is_admin() && ! is_feed();
}

/**
 * Convert one HTML table into a compact definition list.
 *
 * @param array<int,array<int,string>> $rows Rows of already-sanitised cell HTML.
 * @return string
 */
function sa_2026_table_to_list( array $rows ) {
	$items = array();
	foreach ( $rows as $cells ) {
		$cells = array_values( array_filter( array_map( 'trim', $cells ), 'strlen' ) );
		if ( ! $cells ) {
			continue;
		}
		$term  = wp_strip_all_tags( array_shift( $cells ) );
		$value = implode( ' — ', array_map( 'wp_strip_all_tags', $cells ) );
		if ( '' === trim( $term ) && '' === trim( $value ) ) {
			continue;
		}
		if ( '' === trim( $value ) ) {
			// A single-cell row is a section label, not a fact: keep it as a heading.
			$items[] = '<div class="sa-facts-list__row sa-facts-list__row--label"><dt>' . esc_html( $term ) . '</dt><dd></dd></div>';
			continue;
		}
		$items[] = '<div class="sa-facts-list__row"><dt>' . esc_html( $term ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
	}

	if ( ! $items ) {
		return '';
	}

	return '<dl class="sa-facts-list">' . implode( '', $items ) . '</dl>';
}

/**
 * Replace every table of the body with text (never inside the editor or feeds).
 *
 * @param string $content Post content.
 * @return string
 */
function sa_2026_tables_to_text( $content ) {
	$content = (string) $content;
	if ( '' === $content || false === stripos( $content, '<table' ) || ! sa_2026_tables_to_text_enabled() ) {
		return $content;
	}

	$out = preg_replace_callback(
		'#<table\b[^>]*>(.*?)</table>#is',
		static function ( $table ) {
			$rows = array();
			if ( preg_match_all( '#<tr\b[^>]*>(.*?)</tr>#is', $table[1], $row_matches ) ) {
				foreach ( $row_matches[1] as $row_html ) {
					if ( ! preg_match_all( '#<t[dh]\b[^>]*>(.*?)</t[dh]>#is', $row_html, $cell_matches ) ) {
						continue;
					}
					$rows[] = array_map(
						static function ( $cell ) {
							return wp_kses_post( $cell );
						},
						$cell_matches[1]
					);
				}
			}

			return sa_2026_table_to_list( $rows );
		},
		$content
	);

	return null === $out ? $content : $out;
}
add_filter( 'the_content', 'sa_2026_tables_to_text', 12 );

/**
 * First usable image of the body, used when a post has no featured image.
 *
 * Owner report: every card of «گنجینه دیدنی‌ها» showed the same placeholder while
 * the articles already contain real photos. The first in-content image (block or
 * classic markup) is therefore promoted to the card/hero image.
 *
 * @param int $post_id Post ID.
 * @return array{url:string,id:int} Empty strings when nothing was found.
 */
function sa_2026_content_image( $post_id ) {
	$post_id = (int) $post_id;
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( '' === $content ) {
		return array(
			'url' => '',
			'id'  => 0,
		);
	}

	// 1) A block/classic image that points at a real attachment (best: srcset works).
	if ( preg_match( '#wp-image-(\d+)#', $content, $m ) ) {
		$attachment_id = (int) $m[1];
		$url           = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( $url ) {
			return array(
				'url' => (string) $url,
				'id'  => $attachment_id,
			);
		}
	}

	// 2) Any <img src="…"> of the body (external or legacy markup).
	if ( preg_match( '#<img\b[^>]*\bsrc\s*=\s*[\'"]([^\'"]+)[\'"]#i', $content, $m ) ) {
		$url = esc_url_raw( $m[1] );
		if ( '' !== $url ) {
			return array(
				'url' => $url,
				'id'  => (int) attachment_url_to_postid( $url ),
			);
		}
	}

	return array(
		'url' => '',
		'id'  => 0,
	);
}
