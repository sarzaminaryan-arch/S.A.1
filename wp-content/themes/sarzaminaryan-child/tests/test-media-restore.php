<?php
/**
 * Standalone tests for the «بازیابی تصاویر» tool (inc/media-restore.php, v2.15.0).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-media-restore.php
 *
 * A real temporary uploads tree plus small WordPress shims exercise the real
 * theme code without a WordPress install: URL ↔ path mapping, image discovery in
 * post content, source suggestions (local variant → local copy → theme bundle →
 * recorded source URL), the dry/apply restore run, image-signature safety and the
 * plan split between "fixable" and "needs a backup".
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SA_CHILD_URI' ) ) {
	define( 'SA_CHILD_URI', 'https://example.test/wp-content/themes/sarzaminaryan-child/' );
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.15.0-test' );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'STDERR' ) ) {
	define( 'STDERR', fopen( 'php://stderr', 'w' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
}

$GLOBALS['t'] = array(
	'tmp'      => rtrim( sys_get_temp_dir(), '/' ) . '/sa-media-test-' . uniqid(),
	'posts'    => array(),
	'attached' => array(),
	'meta'     => array(),
	'options'  => array(),
	'next_id'  => 700,
);
$GLOBALS['t']['uploads'] = $GLOBALS['t']['tmp'] . '/uploads';
$GLOBALS['t']['themedir'] = rtrim( SA_CHILD_DIR, '/' );

/** Tiny valid images (magic bytes matter, pixels do not). */
function sa_t_image( $kind = 'jpeg', $size = 800 ) {
	$head = array(
		'jpeg' => "\xFF\xD8\xFF\xE0",
		'png'  => "\x89PNG\r\n\x1a\n",
		'webp' => 'RIFF' . pack( 'V', 100 ) . 'WEBP',
		'gif'  => 'GIF89a',
		'html' => '<!DOCTYPE html>',
	);
	return $head[ $kind ] . str_repeat( 'x', max( 80, $size ) );
}

/** Write a file inside the fake uploads tree. */
function sa_t_put( $relative, $contents ) {
	$path = $GLOBALS['t']['uploads'] . '/' . ltrim( $relative, '/' );
	@mkdir( dirname( $path ), 0777, true );
	file_put_contents( $path, $contents );
	return $path;
}

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Error { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $code;
	public $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

class WP_Query { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $posts         = array();
	public $max_num_pages = 1;
	public function __construct( $args ) {
		$page = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
		if ( 1 === $page ) {
			$this->posts = array_keys( $GLOBALS['t']['posts'] );
		}
	}
}

/* ---- WordPress shims. ---- */
function add_action() {
	return true;
}
function add_filter() {
	return true;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function absint( $n ) {
	return abs( (int) $n );
}
function is_ssl() {
	return false;
}
function home_url( $path = '' ) {
	return 'https://example.test' . ( '' !== $path ? '/' . ltrim( $path, '/' ) : '' );
}
function wp_parse_url( $url, $component = -1 ) {
	return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
}
function wp_normalize_path( $path ) {
	$path = str_replace( '\\', '/', (string) $path );
	return (string) preg_replace( '#/+#', '/', $path );
}
function get_template_directory() {
	return $GLOBALS['t']['themedir'];
}
function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/sarzaminaryan';
}
function wp_get_upload_dir() {
	return array(
		'basedir' => $GLOBALS['t']['uploads'],
		'baseurl' => 'https://example.test/wp-content/uploads',
	);
}
function esc_url_raw( $url ) {
	return trim( (string) $url );
}
function sanitize_file_name( $name ) {
	$name = preg_replace( '/[^A-Za-z0-9_\.\-]+/', '-', rawurldecode( (string) $name ) );
	return strtolower( ltrim( $name, '-' ) );
}
function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || @mkdir( $dir, 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}
function wp_delete_file( $file ) {
	if ( is_file( $file ) ) {
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
function get_attached_file( $attachment_id ) {
	return isset( $GLOBALS['t']['attached'][ $attachment_id ] )
		? $GLOBALS['t']['uploads'] . '/' . $GLOBALS['t']['attached'][ $attachment_id ]
		: false;
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['t']['meta'][ $id ][ $key ] = $value;
	return true;
}
function get_post_field( $field, $id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['posts'][ $id ]['content'] ) ? $GLOBALS['t']['posts'][ $id ]['content'] : '';
}
function get_post_mime_type( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ]['mime'] ) ? $GLOBALS['t']['posts'][ $id ]['mime'] : 'image/jpeg';
}
function get_the_title( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ]['title'] ) ? $GLOBALS['t']['posts'][ $id ]['title'] : '';
}
function _prime_post_caches() {
	return true;
}
function sa_entity_types() {
	return array( 'province', 'city', 'attraction' );
}
function get_transient( $key ) {
	return isset( $GLOBALS['t']['options'][ 'transient_' . $key ] ) ? $GLOBALS['t']['options'][ 'transient_' . $key ] : false;
}
function set_transient( $key, $value ) {
	$GLOBALS['t']['options'][ 'transient_' . $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['t']['options'][ 'transient_' . $key ] );
	return true;
}
function get_option( $key, $default = false ) {
	return isset( $GLOBALS['t']['options'][ $key ] ) ? $GLOBALS['t']['options'][ $key ] : $default;
}
function update_option( $key, $value ) {
	$GLOBALS['t']['options'][ $key ] = $value;
	return true;
}
function wp_safe_remote_get( $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( false !== strpos( $url, 'missing-file' ) ) {
		return new WP_Error( 'http_404', 'پاسخ منبع: 404' );
	}
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => sa_t_image( 'jpeg' ),
	);
}
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? (string) $response['body'] : '';
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}

/** The ready-article records the tool reads its recovery sources from. */
function sa_ready_articles_data() {
	return array(
		'ali-sadr' => array(
			'title'   => 'غار علیصدر',
			'gallery' => array(
				'hall' => array(
					'file' => 'ali-sadr-cave-hall.jpg',
					'url'  => 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/Ali-sadr_cave_cresset_hall.jpg/1280px-Ali-sadr_cave_cresset_hall.jpg',
				),
				'broken' => array(
					'file' => 'ali-sadr-cave-lake.jpg',
					'url'  => 'https://upload.wikimedia.org/wikipedia/commons/missing-file.jpg',
				),
			),
		),
		'bleeding' => array(
			'title' => 'خونریزی',
			'cover' => array( 'file' => 'guides/bleeding-wound-first-aid.webp' ),
		),
	);
}

class SA_T_WPDB { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $sql; // شبیه‌سازی: نتیجه‌ها از پیش در $GLOBALS['t']['attached'] هستند.
	}
	public function get_results( $sql ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$rows = array();
		foreach ( $GLOBALS['t']['attached'] as $id => $relative ) {
			$row         = new stdClass();
			$row->post_id    = $id;
			$row->meta_value = $relative;
			$rows[]      = $row;
		}
		return $rows;
	}
}
$GLOBALS['wpdb'] = new SA_T_WPDB();

require_once dirname( __DIR__ ) . '/inc/media-restore.php';

/* ---------------------------------------------------------------------------
 * 1) URL ↔ path mapping.
 * ------------------------------------------------------------------------- */
$base = $GLOBALS['t']['uploads'];
sa_t_assert(
	$base . '/2026/10/photo.jpg' === sa_media_local_path( 'https://example.test/wp-content/uploads/2026/10/photo.jpg' ),
	'uploads URL maps to the uploads path'
);
sa_t_assert(
	$base . '/2026/10/photo with space.jpg' === sa_media_local_path( '/wp-content/uploads/2026/10/photo%20with%20space.jpg?ver=2#frag' ),
	'relative URL, query, fragment and encoding are handled'
);
sa_t_assert(
	'' === sa_media_local_path( 'https://upload.wikimedia.org/wikipedia/commons/a/b/x.jpg' ),
	'external hosts are never treated as local files'
);
sa_t_assert(
	rtrim( SA_CHILD_DIR, '/' ) . '/assets/img/articles/a/01.webp' === sa_media_local_path( SA_CHILD_URI . 'assets/img/articles/a/01.webp' ),
	'the child theme folder is a known root as well'
);
sa_t_assert(
	'' === sa_media_local_path( 'https://example.test/wp-content/uploads/../../wp-config.php' ),
	'a path that escapes the root is rejected'
);
sa_t_assert(
	'https://example.test/wp-content/uploads/2026/10/a b.jpg' === rawurldecode( sa_media_path_url( $base . '/2026/10/a b.jpg' ) ),
	'path → URL round trip'
);

/* ---------------------------------------------------------------------------
 * 2) Image discovery in post content.
 * ------------------------------------------------------------------------- */
$content = '<p>متن</p><img src="https://example.test/wp-content/uploads/2026/10/a.jpg" alt="a">'
	. '<img data-src="/uploads-not-quite.jpg" src="https://example.test/wp-content/uploads/2026/10/a.jpg">'
	. '<img srcset="https://example.test/wp-content/uploads/2026/10/b-300.jpg 300w, https://example.test/wp-content/uploads/2026/10/b-1024.jpg 1024w" src="https://example.test/wp-content/uploads/2026/10/b.jpg">'
	. '<div style="background-image:url(\'https://example.test/wp-content/uploads/2026/10/c.jpg\')"></div>'
	. '<img src="data:image/png;base64,AAAA">';
$urls = sa_media_extract_image_urls( $content );
sa_t_assert( in_array( 'https://example.test/wp-content/uploads/2026/10/a.jpg', $urls, true ), 'src is collected' );
sa_t_assert( in_array( 'https://example.test/wp-content/uploads/2026/10/b-300.jpg', $urls, true ), 'srcset candidates are collected' );
sa_t_assert( in_array( 'https://example.test/wp-content/uploads/2026/10/c.jpg', $urls, true ), 'background-image is collected' );
sa_t_assert( 1 === count( array_keys( $urls, 'https://example.test/wp-content/uploads/2026/10/a.jpg', true ) ), 'duplicates collapse' );
foreach ( $urls as $u ) {
	sa_t_assert( 0 !== strpos( $u, 'data:' ), 'data URIs are ignored' );
}
sa_t_assert( array() === sa_media_extract_image_urls( '<p>بدون تصویر</p>' ), 'plain text returns nothing' );

/* ---------------------------------------------------------------------------
 * 3) Image signature and safe writing.
 * ------------------------------------------------------------------------- */
sa_t_assert( sa_media_looks_like_image( sa_t_image( 'jpeg' ) ), 'JPEG signature' );
sa_t_assert( sa_media_looks_like_image( sa_t_image( 'png' ) ), 'PNG signature' );
sa_t_assert( sa_media_looks_like_image( sa_t_image( 'webp' ) ), 'WebP signature' );
sa_t_assert( sa_media_looks_like_image( sa_t_image( 'gif' ) ), 'GIF signature' );
sa_t_assert( ! sa_media_looks_like_image( sa_t_image( 'html' ) ), 'an HTML error page is not an image' );
sa_t_assert( ! sa_media_looks_like_image( 'tiny' ), 'too small payloads are rejected' );

$target = $base . '/2026/10/new.jpg';
sa_t_assert( ! sa_media_write_file( $target, sa_t_image( 'html' ) ), 'a non-image is never written' );
sa_t_assert( ! file_exists( $target ), 'nothing was written for the non-image' );
sa_t_assert( sa_media_write_file( $target, sa_t_image( 'jpeg' ) ), 'a real image is written' );
sa_t_assert( file_exists( $target ), 'the written file exists' );
sa_t_assert( ! sa_media_write_file( $target, sa_t_image( 'png' ) ), 'an existing file is never overwritten' );
sa_t_assert( sa_t_image( 'jpeg' ) === file_get_contents( $target ), 'the file keeps its original bytes' );

/* ---------------------------------------------------------------------------
 * 4) Local recovery sources: variant, copy, theme bundle.
 * ------------------------------------------------------------------------- */
sa_t_put( '2026/10/shot-1024x683.jpg', sa_t_image( 'jpeg', 1400 ) );
sa_t_put( '2026/10/shot-300x200.jpg', sa_t_image( 'jpeg', 900 ) );
$variant = sa_media_find_variant( $base . '/2026/10/shot.jpg' );
sa_t_assert( $base . '/2026/10/shot-1024x683.jpg' === $variant, 'the biggest size variant wins' );

sa_t_put( '2026/09/moved-elsewhere.jpg', sa_t_image( 'jpeg' ) );
sa_t_assert(
	$base . '/2026/09/moved-elsewhere.jpg' === sa_media_find_copy( 'moved-elsewhere.jpg' ),
	'a copy that lives in another month folder is found'
);
sa_t_assert( '' === sa_media_find_copy( 'does-not-exist-anywhere.jpg' ), 'unknown basenames have no local copy' );

$theme_index = sa_media_theme_index();
sa_t_assert( isset( $theme_index['bleeding-wound-first-aid.webp'] ), 'theme covers are indexed by basename' );
sa_t_assert( is_readable( $theme_index['bleeding-wound-first-aid.webp'] ), 'the indexed theme file is readable' );

/* ---------------------------------------------------------------------------
 * 5) Source suggestions, order of fallbacks.
 * ------------------------------------------------------------------------- */
$suggestion = sa_media_suggest_source( $base . '/2026/10/shot.jpg' );
sa_t_assert( 'local' === $suggestion['type'], 'a surviving size variant is preferred' );

$suggestion = sa_media_suggest_source( $base . '/2026/10/moved-elsewhere.jpg' );
sa_t_assert( 'local' === $suggestion['type'], 'a copy elsewhere in uploads is still local' );

$suggestion = sa_media_suggest_source( $base . '/2026/10/bleeding-wound-first-aid.webp' );
sa_t_assert( 'theme' === $suggestion['type'], 'a bundled theme file is used when nothing is left on disk' );

$suggestion = sa_media_suggest_source( $base . '/2026/10/ali-sadr-cave-hall.jpg' );
sa_t_assert( 'url' === $suggestion['type'], 'the recorded source URL is used as the next fallback' );
sa_t_assert(
	'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b5/Ali-sadr_cave_cresset_hall.jpg/1280px-Ali-sadr_cave_cresset_hall.jpg' === $suggestion['value'],
	'the recorded URL is the gallery item URL'
);

/* The filename WordPress would create by sideloading the same URL is matched too. */
$sideloaded = sa_media_suggest_source( $base . '/2026/10/1280px-ali-sadr_cave_cresset_hall.jpg' );
sa_t_assert( 'url' === $sideloaded['type'], 'the sideloaded file name is matched to the same record' );

$suggestion = sa_media_suggest_source( $base . '/2026/10/unknown-photo.jpg' );
sa_t_assert( 'none' === $suggestion['type'], 'an unknown file needs the owner backup' );

/* The attachment marker wins over the record index. */
$GLOBALS['t']['meta'][901]['_sa_source_url'] = 'https://upload.wikimedia.org/wikipedia/commons/9/9f/own.jpg';
$suggestion = sa_media_suggest_source( $base . '/2026/10/anything.jpg', 901 );
sa_t_assert( 'url' === $suggestion['type'] && false !== strpos( $suggestion['value'], 'own.jpg' ), 'the attachment’s own source URL wins' );

/* ---------------------------------------------------------------------------
 * 6) Scan: missing content images and missing attachment files.
 * ------------------------------------------------------------------------- */
$GLOBALS['t']['posts'] = array();
$GLOBALS['t']['attached'] = array();
$GLOBALS['t']['meta'] = array();
$GLOBALS['t']['options'] = array();

sa_t_put( '2026/10/present.jpg', sa_t_image( 'jpeg' ) );
$GLOBALS['t']['posts'][11] = array(
	'title'   => 'مقاله با تصویر گمشده',
	'content' => '<img src="' . home_url( '/wp-content/uploads/2026/10/present.jpg' ) . '"><img src="' . home_url( '/wp-content/uploads/2026/10/ali-sadr-cave-hall.jpg' ) . '">',
);
$GLOBALS['t']['attached'][21] = '2026/10/1280px-ali-sadr_cave_cresset_hall.jpg';
$GLOBALS['t']['attached'][22] = '2026/10/present.jpg';
$GLOBALS['t']['posts'][21]    = array( 'title' => 'پیوست گمشده', 'mime' => 'image/jpeg' );
$GLOBALS['t']['posts'][22]    = array( 'title' => 'پیوست سالم', 'mime' => 'image/jpeg' );

$scan = sa_media_scan( true );
sa_t_assert( 2 === $scan['totals']['images'], 'both content images were seen' );
sa_t_assert( 1 === $scan['totals']['present'], 'the healthy image is counted as present' );
sa_t_assert( 2 === $scan['totals']['missing'], 'both missing files are reported once each' );
sa_t_assert( 1 === $scan['totals']['attachment_missing'], 'the missing attachment file is reported' );
sa_t_assert( isset( $scan['missing'][ $base . '/2026/10/ali-sadr-cave-hall.jpg' ] ), 'the content image joins the attachment record' );
sa_t_assert( 11 === (int) key( $scan['missing'][ $base . '/2026/10/ali-sadr-cave-hall.jpg' ]['posts'] ), 'the post that uses the image is recorded' );
sa_t_assert( 21 === (int) $scan['missing'][ $base . '/2026/10/1280px-ali-sadr_cave_cresset_hall.jpg' ]['attachment'], 'the attachment ID is recorded for a media file' );

$plan = sa_media_plan( $scan );
sa_t_assert( 2 === count( $plan['source'] ), 'both files have an automatic source' );
sa_t_assert( 0 === count( $plan['backup'] ), 'nothing needs a backup here' );

/* ---------------------------------------------------------------------------
 * 7) Restore: dry run, apply from the source URL, idempotence.
 * ------------------------------------------------------------------------- */
$row = $scan['missing'][ $base . '/2026/10/ali-sadr-cave-hall.jpg' ];
$dry = sa_media_restore_item( $row, true );
sa_t_assert( 'planned' === $dry['status'], 'preview does not touch the disk' );
sa_t_assert( ! file_exists( $base . '/2026/10/ali-sadr-cave-hall.jpg' ), 'preview wrote nothing' );

$statuses = array();
foreach ( $plan['source'] as $plan_row ) {
	$result     = sa_media_restore_item( $plan_row, false );
	$statuses[] = $result['status'];
}
sa_t_assert( ! in_array( 'fail', $statuses, true ), 'every planned row restores from its source' );
sa_t_assert( in_array( 'restored', $statuses, true ), 'at least one row reports "restored"' );
sa_t_assert( file_exists( $base . '/2026/10/ali-sadr-cave-hall.jpg' ), 'the content image is back in its exact path' );
sa_t_assert( sa_media_looks_like_image( file_get_contents( $base . '/2026/10/ali-sadr-cave-hall.jpg' ) ), 'the restored file is an image' );
sa_t_assert( file_exists( $base . '/2026/10/1280px-ali-sadr_cave_cresset_hall.jpg' ), 'the media-library file is back in its exact path too' );

$again = sa_media_restore_item( $row, false );
sa_t_assert( 'exists' === $again['status'], 'a second run leaves the file alone' );

/* A failing source is reported, not fatal; a foreign host is refused. */
$GLOBALS['t']['meta'][900]['_sa_source_url'] = 'https://upload.wikimedia.org/wikipedia/commons/missing-file.jpg';
$failing = sa_media_restore_item(
	array(
		'path'       => $base . '/2026/10/never-coming.jpg',
		'attachment' => 900,
		'source'     => sa_media_suggest_source( $base . '/2026/10/never-coming.jpg', 900 ),
	),
	false
);
sa_t_assert( 'fail' === $failing['status'], 'an unreachable source fails that row' );
sa_t_assert( false !== strpos( $failing['message'], '404' ), 'the failure message carries the reason' );

$foreign = sa_media_fetch_url( 'https://evil.example.net/pic.jpg' );
sa_t_assert( is_wp_error( $foreign ), 'foreign hosts are refused' );
$allowed = sa_media_fetch_url( 'https://upload.wikimedia.org/wikipedia/commons/ok.jpg' );
sa_t_assert( ! is_wp_error( $allowed ), 'recorded hosts are allowed' );

/* ---------------------------------------------------------------------------
 * 8) Restore from a bundled theme file (the guide covers of the media library).
 * ------------------------------------------------------------------------- */
$cover_rel = '2026/10/bleeding-wound-first-aid.webp';
$GLOBALS['t']['attached'][31] = $cover_rel;
$GLOBALS['t']['posts'][31]    = array( 'title' => 'پیوست کاور', 'mime' => 'image/webp' );
$themed_file                  = $theme_index['bleeding-wound-first-aid.webp'];
sa_t_assert(
	sa_media_restore_from_file( 31, $themed_file ),
	'the attachment file is restored from the theme bundle'
);
sa_t_assert( file_exists( $base . '/' . $cover_rel ), 'the cover is back under its own uploads path' );
sa_t_assert( file_get_contents( $themed_file ) === file_get_contents( $base . '/' . $cover_rel ), 'bytes match the theme file' );
sa_t_assert( false === sa_media_attachment_missing( 31 ), 'the attachment is no longer "missing"' );
sa_t_assert( ! sa_media_attachment_missing( 21 ), 'the file restored in the previous step counts as present' );
sa_t_assert( sa_media_attachment_missing( 900 ) === false, 'an attachment without a file path is not judged' );

/* ---------------------------------------------------------------------------
 * 9) Path tails for backup matching + cleanup of the temporary tree.
 * ------------------------------------------------------------------------- */
sa_t_assert( '2026/10/a.jpg' === sa_media_path_tail( $base . '/2026/10/a.jpg', 3 ), 'the last three segments are used for matching' );
sa_t_assert( '10/a.jpg' === sa_media_path_tail( $base . '/2026/10/a.jpg', 2 ), 'two-segment tails work' );
sa_t_assert(
	'2026/10/a.jpg' === sa_media_path_tail( $GLOBALS['t']['tmp'] . '/backup/wp-content/uploads/2026/10/a.jpg', 3, $GLOBALS['t']['tmp'] . '/backup' ),
	'the extraction root is trimmed before matching'
);
sa_t_assert( sa_media_delete_dir( $GLOBALS['t']['tmp'] ), 'the temporary tree is removed' );
sa_t_assert( ! is_dir( $GLOBALS['t']['tmp'] ), 'the temporary tree is gone' );

echo "PASS: media restore (URL↔path, discovery, source fallbacks, dry/apply, image safety, backup tails)\n";
