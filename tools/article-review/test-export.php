<?php
/** Offline synthetic test only; no WordPress connection or genuine article data. */
$sa_test_mode = isset( $argv[1] ) ? $argv[1] : 'normal';
$sa_test_dir = sys_get_temp_dir() . '/sa-article-review-' . uniqid();
mkdir( $sa_test_dir, 0700 );
mkdir( $sa_test_dir . '/wordpress', 0700 );
define( 'ABSPATH', $sa_test_dir . '/wordpress/' );
if ( 'not-cli' !== $sa_test_mode ) {
	define( 'WP_CLI', true );
}
define( 'SA_CHILD_VERSION', 'fixture-only' );
$args = array( '--output=' . $sa_test_dir . '/export.jsonl', '--expected=51' );
if ( 'inside-root' === $sa_test_mode ) {
	$args[0] = '--output=' . ABSPATH . 'export.jsonl';
} elseif ( 'bad-count' === $sa_test_mode ) {
	$args[1] = '--expected=not-a-number';
} elseif ( 'mismatch' === $sa_test_mode ) {
	$args[1] = '--expected=480';
} elseif ( 'unknown-option' === $sa_test_mode ) {
	$args[] = '--write';
} elseif ( 'overwrite' === $sa_test_mode ) {
	file_put_contents( $sa_test_dir . '/export.jsonl', 'DO NOT OVERWRITE' );
}
function sa_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}
function current_user_can( $cap ) {
	global $sa_test_mode;
	sa_test_assert( 'manage_options' === $cap, 'administrator capability checked' );
	return 'denied' !== $sa_test_mode;
}
function wp_json_encode( $value, $flags ) { return json_encode( $value, $flags ); }
function home_url( $path ) { return 'https://fixture.invalid' . $path; }
function post_type_exists( $type ) { return in_array( $type, array( 'post', 'city' ), true ); }
function get_posts( $query ) {
	sa_test_assert( array( 'post', 'city' ) === $query['post_type'], 'only registered article types' );
	sa_test_assert( 'publish' === $query['post_status'], 'published only' );
	sa_test_assert( 'ID' === $query['orderby'] && 'ASC' === $query['order'], 'stable ID order' );
	return 1 === $query['paged'] ? range( 1, 50 ) : ( 2 === $query['paged'] ? array( 51 ) : array() );
}
function get_post( $id ) {
	global $sa_test_mode;
	return (object) array(
		'post_status' => 'changed-status' === $sa_test_mode && 51 === $id ? 'draft' : 'publish',
		'post_type' => 1 === $id ? 'post' : 'city', 'post_name' => 'synthetic-' . $id,
		'post_title' => 'مقالهٔ ساختگی ' . $id, 'post_modified_gmt' => '2026-10-19 00:00:00',
		'post_content' => '<h2>معرفی شهر</h2><p>این فقط دادهٔ آزمون است: ۱۲۳.</p>', 'post_excerpt' => '',
	);
}
function get_permalink( $id ) { return 'https://fixture.invalid/city/synthetic-' . $id . '/'; }
function get_post_meta( $id, $key, $single ) {
	global $sa_test_mode;
	if ( 'sa_faq' === $key ) {
		if ( 'empty-faq' === $sa_test_mode ) { return ''; }
		if ( 'malformed-faq' === $sa_test_mode ) { return '{invalid-json'; }
		if ( 'array-faq' === $sa_test_mode ) { return array( array( 'q' => 'ناقص', 'a' => '' ) ); }
		return '[{"q":"پرسش ساختگی؟","a":"فقط دادهٔ آزمون، نه واقعیت یک شهر."},{"q":"ردیف ناقص؟","a":""}]';
	}
	if ( 'sa_facts_checked' === $key ) { return ''; }
	return 'sa_sources' === $key ? 'منبع ساختگی | فقط تست | https://fixture.invalid/source | 2026-10-19' : $key . '-fixture';
}
function sa_county_profile_fields() {
	return array( 'population' => array( 'label' => 'جمعیت', 'legacy' => 'sa_cty_population' ) );
}
// Any accidental write introduced into the exporter fails the test immediately.
function wp_update_post() { throw new RuntimeException( 'FAIL: database write' ); }
function wp_insert_post() { throw new RuntimeException( 'FAIL: database write' ); }
function wp_delete_post() { throw new RuntimeException( 'FAIL: database write' ); }
function update_post_meta() { throw new RuntimeException( 'FAIL: database write' ); }
function update_option() { throw new RuntimeException( 'FAIL: database write' ); }
$sa_test_expected_error = in_array( $sa_test_mode, array( 'not-cli', 'denied', 'inside-root', 'bad-count', 'unknown-option', 'overwrite', 'changed-status' ), true );
$sa_test_caught = null;
ob_start();
try {
	include __DIR__ . '/export.php';
} catch ( Throwable $sa_test_error ) {
	$sa_test_caught = $sa_test_error;
}
$sa_test_log = ob_get_clean();
try {
	if ( $sa_test_expected_error ) {
		$sa_test_messages = array(
			'not-cli' => 'Run through WP-CLI', 'denied' => 'administrator', 'inside-root' => 'WordPress directory',
			'bad-count' => 'nonnegative integer', 'unknown-option' => 'Unknown export option',
			'overwrite' => 'Cannot create export', 'changed-status' => 'post changed during export',
		);
		sa_test_assert( $sa_test_caught instanceof Throwable, 'unsafe context rejected' );
		sa_test_assert( false !== strpos( $sa_test_caught->getMessage(), $sa_test_messages[ $sa_test_mode ] ), 'correct rejection reason' );
		if ( 'overwrite' === $sa_test_mode ) {
			sa_test_assert( 'DO NOT OVERWRITE' === file_get_contents( $sa_test_dir . '/export.jsonl' ), 'old export unchanged' );
		} elseif ( 'changed-status' === $sa_test_mode ) {
			sa_test_assert( false === strpos( file_get_contents( $sa_test_dir . '/export.jsonl' ), '"complete":true' ), 'interrupted export has no complete footer' );
		} else {
			sa_test_assert( ! file_exists( $sa_test_dir . '/export.jsonl' ), 'rejected export creates no source file' );
		}
	} else {
		if ( $sa_test_caught ) { throw $sa_test_caught; }
		$sa_test_rows = array_map( 'json_decode', file( $sa_test_dir . '/export.jsonl', FILE_IGNORE_NEW_LINES ) );
		sa_test_assert( 53 === count( $sa_test_rows ), 'header + 51 articles + summary; pagination works' );
		sa_test_assert( 'sa-article-review-v1' === $sa_test_rows[0]->schema, 'schema' );
		sa_test_assert( 'unavailable' === $sa_test_rows[1]->identity_coverage, 'do not invent non-county identity' );
		sa_test_assert( 'population-fixture' === $sa_test_rows[2]->identity_fields->population->canonical, 'canonical identity retained' );
		sa_test_assert( 'sa_cty_population-fixture' === $sa_test_rows[2]->identity_fields->population->legacy, 'legacy identity retained' );
		foreach ( array_slice( $sa_test_rows, 1, 51 ) as $sa_test_row ) {
			sa_test_assert( hash( 'sha256', $sa_test_row->content ) === $sa_test_row->content_sha256, 'unaltered UTF-8 body hash' );
			sa_test_assert( false !== strpos( $sa_test_row->sources, 'fixture.invalid/source' ), 'source exported' );
			sa_test_assert( '' === $sa_test_row->facts_checked, 'missing verification date remains empty, never auto-filled' );
			$sa_test_original_faq = get_post_meta( $sa_test_row->id, 'sa_faq', true );
			sa_test_assert( json_encode( $sa_test_original_faq ) === json_encode( $sa_test_row->faq_raw ), 'FAQ values and types preserved, including empty/malformed/incomplete data' );
		}
		sa_test_assert( 51 === $sa_test_rows[52]->articles && true === $sa_test_rows[52]->complete, 'complete footer' );
		sa_test_assert( false === $sa_test_rows[52]->consistent_database_snapshot, 'no false snapshot guarantee' );
		sa_test_assert( ( 'mismatch' !== $sa_test_mode ) === $sa_test_rows[52]->count_matches_expected, 'coverage mismatch explicit' );
		sa_test_assert( 'mismatch' !== $sa_test_mode || false !== strpos( $sa_test_log, 'WARNING' ), 'count mismatch warning' );
		sa_test_assert( 0 === ( fileperms( $sa_test_dir . '/export.jsonl' ) & 0077 ), 'export has no group/other permissions' );
	}
	echo 'PASS: ' . $sa_test_mode . PHP_EOL;
} finally {
	foreach ( array( $sa_test_dir . '/export.jsonl', ABSPATH . 'export.jsonl' ) as $sa_test_file ) {
		if ( file_exists( $sa_test_file ) ) { unlink( $sa_test_file ); }
	}
	rmdir( ABSPATH );
	rmdir( $sa_test_dir );
}
