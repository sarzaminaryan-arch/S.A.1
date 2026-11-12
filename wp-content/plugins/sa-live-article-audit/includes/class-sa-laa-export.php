<?php
/**
 * خروجی‌های قابل دانلود: اسنپ‌شات JSON/CSV و گزارش تطبیق JSON/CSV.
 *
 * فقط رشته می‌سازد؛ ارسال هدر و دانلود در کلاس مدیریت انجام می‌شود.
 * CSV با BOM یونیکد ساخته می‌شود تا در Excel فارسی درست باز شود.
 *
 * @package SA_Live_Article_Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SA_LAA_Export {

	/**
	 * JSON خوانا (فارسی بدون اسلش و یونیکد خوانا).
	 *
	 * @param mixed $data داده.
	 * @return string
	 */
	public static function json( $data ) {
		$out = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return is_string( $out ) ? $out . "\n" : "{}\n";
	}

	/**
	 * CSV از ردیف‌های انجمنی.
	 *
	 * @param array $rows ردیف‌ها.
	 * @return string
	 */
	public static function csv( $rows ) {
		$handle = fopen( 'php://temp', 'r+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- حافظهٔ موقت برای ساخت CSV.
		if ( false === $handle ) {
			return '';
		}
		fwrite( $handle, "\xEF\xBB\xBF" ); // BOM برای Excel.
		foreach ( (array) $rows as $row ) {
			$clean = array();
			foreach ( (array) $row as $value ) {
				if ( is_array( $value ) ) {
					$value = sa_laa_json_encode( $value );
				}
				$clean[] = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
			}
			fputcsv( $handle, $clean, ',', '"', '\\' );
		}
		rewind( $handle );
		$out = stream_get_contents( $handle );
		fclose( $handle );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * ردیف‌های CSV اسنپ‌شات زنده.
	 *
	 * @param array $snapshot اسنپ‌شات استخراج‌گر.
	 * @return array
	 */
	public static function snapshot_rows( $snapshot ) {
		$rows = array(
			array( 'کلید', 'شناسه وردپرس', 'نوع', 'نامک', 'عنوان', 'وضعیت', 'انتشار', 'آخرین ویرایش', 'نویسنده', 'پیوند', 'هش محتوا (نرمال)', 'هش متن ساده', 'واژه', 'تصویر شاخص', 'طبقه‌بندی‌ها', 'تعداد متا' ),
		);
		foreach ( (array) ( isset( $snapshot['records'] ) ? $snapshot['records'] : array() ) as $r ) {
			$terms = array();
			foreach ( (array) ( isset( $r['terms'] ) ? $r['terms'] : array() ) as $taxonomy => $map ) {
				$terms[] = $taxonomy . ':' . implode( '|', array_keys( (array) $map ) );
			}
			$rows[] = array(
				isset( $r['key'] ) ? $r['key'] : '',
				isset( $r['wp_id'] ) ? $r['wp_id'] : '',
				isset( $r['post_type'] ) ? $r['post_type'] : '',
				isset( $r['slug'] ) ? $r['slug'] : '',
				isset( $r['title'] ) ? $r['title'] : '',
				isset( $r['status'] ) ? $r['status'] : '',
				isset( $r['date_published'] ) ? $r['date_published'] : '',
				isset( $r['date_modified'] ) ? $r['date_modified'] : '',
				isset( $r['author']['display_name'] ) ? $r['author']['display_name'] : '',
				isset( $r['permalink'] ) ? $r['permalink'] : '',
				isset( $r['content_hash'] ) ? $r['content_hash'] : '',
				isset( $r['content_text_hash'] ) ? $r['content_text_hash'] : '',
				isset( $r['word_count'] ) ? $r['word_count'] : '',
				isset( $r['featured_image']['file'] ) ? $r['featured_image']['file'] : '',
				implode( ' ; ', $terms ),
				isset( $r['meta'] ) && is_array( $r['meta'] ) ? count( $r['meta'] ) : 0,
			);
		}
		return $rows;
	}

	/**
	 * ردیف‌های CSV گزارش تطبیق.
	 *
	 * @param array $report گزارش SA_LAA_Compare::compare().
	 * @return array
	 */
	public static function report_rows( $report ) {
		$rows = array(
			array( 'بخش', 'کلید', 'عنوان', 'فیلد', 'شدت', 'مخزن/خط مبنا', 'سایت زنده', 'یادداشت' ),
		);
		foreach ( (array) ( isset( $report['items'] ) ? $report['items'] : array() ) as $item ) {
			foreach ( (array) $item['differences'] as $diff ) {
				$rows[] = array(
					'matched',
					$item['key'],
					$item['title'],
					$diff['field'],
					$diff['severity'],
					$diff['baseline'],
					$diff['live'],
					$diff['note'],
				);
			}
			if ( empty( $item['differences'] ) ) {
				$rows[] = array( 'matched', $item['key'], $item['title'], '', '', '', '', 'یکسان' );
			}
		}
		foreach ( (array) ( isset( $report['live_only'] ) ? $report['live_only'] : array() ) as $item ) {
			$rows[] = array( 'live_only', $item['key'], $item['title'], '', '', '', $item['permalink'], 'فقط در سایت زنده است' );
		}
		foreach ( (array) ( isset( $report['baseline_only'] ) ? $report['baseline_only'] : array() ) as $item ) {
			$rows[] = array( 'baseline_only', $item['key'], $item['title'], '', '', '', '', 'فقط در مخزن است (هنوز روی سایت نیست)' );
		}
		foreach ( (array) ( isset( $report['duplicates']['live'] ) ? $report['duplicates']['live'] : array() ) as $key ) {
			$rows[] = array( 'duplicate_live', $key, '', 'key', 'high', '', '', 'کلید تکراری در سایت زنده' );
		}
		foreach ( (array) ( isset( $report['duplicates']['baseline'] ) ? $report['duplicates']['baseline'] : array() ) as $key ) {
			$rows[] = array( 'duplicate_baseline', $key, '', 'key', 'high', '', '', 'کلید تکراری در خط مبنا' );
		}
		return $rows;
	}
}
