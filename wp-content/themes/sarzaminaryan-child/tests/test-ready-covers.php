<?php
/**
 * Standalone tests for the bundled featured images of the ready-articles tool
 * (sa_ready_articles_attach_cover(), v2.13.2).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-ready-covers.php
 * An in-memory fake of posts/meta/uploads exercises the real theme code without
 * WordPress: fill a missing thumbnail, replace a generated white card, keep the
 * owner's own picture, reuse the same upload on re-import, refresh on a new file.
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
	define( 'SA_CHILD_VERSION', '2.13.2-test' );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$GLOBALS['t'] = array(
	'posts'   => array(),
	'meta'    => array(),
	'uploads' => array(),
	'next_id' => 500,
);

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

/* ---- WordPress shims (only what the tested code path touches). ---- */
function add_action() {
	return true;
}
function add_filter() {
	return true;
}
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function absint( $n ) {
	return abs( (int) $n );
}
function sanitize_text_field( $s ) {
	return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) );
}
function wp_basename( $path ) {
	return basename( $path );
}
function get_post_meta( $id, $key, $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['meta'][ $id ][ $key ] ) ? $GLOBALS['t']['meta'][ $id ][ $key ] : '';
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['t']['meta'][ $id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $id, $key ) {
	$exists = isset( $GLOBALS['t']['meta'][ $id ][ $key ] );
	unset( $GLOBALS['t']['meta'][ $id ][ $key ] );
	return $exists;
}
function get_post_thumbnail_id( $post_id ) {
	return (int) get_post_meta( $post_id, '_thumbnail_id', true );
}
function set_post_thumbnail( $post_id, $attachment_id ) {
	return update_post_meta( $post_id, '_thumbnail_id', (int) $attachment_id );
}
function delete_post_thumbnail( $post_id ) {
	return delete_post_meta( $post_id, '_thumbnail_id' );
}
function get_post( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
}
function wp_update_post( $arr ) {
	$id = (int) $arr['ID'];
	foreach ( $arr as $k => $v ) {
		$GLOBALS['t']['posts'][ $id ]->$k = $v;
	}
	return $id;
}
function get_posts( $args ) {
	$out = array();
	foreach ( $GLOBALS['t']['posts'] as $id => $p ) {
		if ( $p->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['meta_key'] ) && get_post_meta( $id, $args['meta_key'], true ) !== $args['meta_value'] ) {
			continue;
		}
		$out[] = $id;
	}
	return $out;
}
function wp_upload_bits( $name, $deprecated, $bits ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$file                         = '/uploads/2026/10/' . $name;
	$GLOBALS['t']['uploads'][]    = array( $file, strlen( $bits ) );
	return array(
		'file'  => $file,
		'url'   => 'https://example.test' . $file,
		'error' => false,
	);
}
function wp_check_filetype( $file ) {
	$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$map = array( 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png' );
	return array( 'ext' => $ext, 'type' => isset( $map[ $ext ] ) ? $map[ $ext ] : false );
}
function wp_insert_attachment( $args, $file, $parent, $wp_error = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$id                        = $GLOBALS['t']['next_id']++;
	$GLOBALS['t']['posts'][ $id ] = (object) array_merge(
		$args,
		array(
			'ID'          => $id,
			'post_type'   => 'attachment',
			'post_parent' => (int) $parent,
			'file'        => $file,
		)
	);
	return $id;
}
function wp_generate_attachment_metadata( $id, $file ) {
	return array( 'file' => $file, 'id' => $id );
}
function wp_update_attachment_metadata( $id, $data ) {
	return update_post_meta( $id, '_wp_attachment_metadata', $data );
}

/* The real ABSPATH . 'wp-admin/includes/image.php' is required by the code; provide an empty stand-in. */
if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) {
	mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
}
if ( ! file_exists( ABSPATH . 'wp-admin/includes/image.php' ) ) {
	file_put_contents( ABSPATH . 'wp-admin/includes/image.php', "<?php\n" );
}
register_shutdown_function(
	function () {
		@unlink( ABSPATH . 'wp-admin/includes/image.php' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( ABSPATH . 'wp-admin/includes' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( ABSPATH . 'wp-admin' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
);

require_once SA_CHILD_DIR . 'inc/ready-articles.php';

$guides  = include SA_CHILD_DIR . 'data/ready-guides.php';
$article = sa_ready_article_normalize( $guides['how-to-choose-camping-tent'] );
sa_t_assert( ! empty( $article['cover']['file'] ) && 'guides/how-to-choose-camping-tent.webp' === $article['cover']['file'], 'tent guide carries its bundled cover' );
$expected_hash = md5_file( SA_CHILD_DIR . 'assets/img/' . $article['cover']['file'] );

$GLOBALS['t']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'post', 'post_parent' => 0 );

/* 1) No thumbnail → the bundled file is uploaded once and attached, alt = keyword alt. */
$id = sa_ready_articles_attach_cover( 10, $article );
sa_t_assert( is_int( $id ) && $id > 0, 'attach returns the new attachment id' );
sa_t_assert( 10 === (int) $GLOBALS['t']['posts'][ $id ]->post_parent, 'attachment belongs to the post' );
sa_t_assert( $id === get_post_thumbnail_id( 10 ), 'attachment became the featured image' );
sa_t_assert( get_post_meta( $id, '_wp_attachment_image_alt', true ) === $article['cover']['alt'], 'alt text written from the record' );
sa_t_assert( false !== mb_strpos( get_post_meta( $id, '_wp_attachment_image_alt', true ), $article['meta']['sa_focus_keyword'] ), 'alt text contains the focus keyword' );
sa_t_assert( 'image/webp' === $GLOBALS['t']['posts'][ $id ]->post_mime_type, 'webp mime type' );
sa_t_assert( get_post_meta( $id, '_sa_ready_cover_hash', true ) === $expected_hash, 'fingerprint stored' );
sa_t_assert( 1 === count( $GLOBALS['t']['uploads'] ) && $GLOBALS['t']['uploads'][0][1] > 10000, 'one real upload' );
sa_t_assert( array( 'how-to-choose-camping-tent' => $id ) === sa_ready_articles_cover_log(), 'cover log records the slug' );

/* 2) Re-import with the same file → nothing re-uploaded, alt kept in sync. */
update_post_meta( $id, '_wp_attachment_image_alt', 'old alt' );
sa_t_assert( 0 === sa_ready_articles_attach_cover( 10, $article ), 'same picture in place → no change' );
sa_t_assert( get_post_meta( $id, '_wp_attachment_image_alt', true ) === $article['cover']['alt'], 'alt re-synced' );
sa_t_assert( 1 === count( $GLOBALS['t']['uploads'] ), 'no duplicate upload' );

/* 3) A generated white card is replaced (the upload from step 1 is reused via its hash). */
$GLOBALS['t']['posts'][11] = (object) array( 'ID' => 11, 'post_type' => 'post', 'post_parent' => 0 );
$card                      = wp_insert_attachment( array( 'post_mime_type' => 'image/png' ), '/uploads/card.png', 11 );
update_post_meta( $card, '_sa_ready_cover_article', 'how-to-choose-camping-tent' );
set_post_thumbnail( 11, $card );
$again = sa_ready_articles_attach_cover( 11, $article );
sa_t_assert( $again === $id, 'white card replaced by the already uploaded bundled image' );
sa_t_assert( $id === get_post_thumbnail_id( 11 ), 'post 11 now uses the bundled cover' );
sa_t_assert( 1 === count( $GLOBALS['t']['uploads'] ), 'still a single upload' );

/* 4) An image the owner chose by hand is never replaced. */
$GLOBALS['t']['posts'][12] = (object) array( 'ID' => 12, 'post_type' => 'post', 'post_parent' => 0 );
$own                       = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg' ), '/uploads/own.jpg', 12 );
set_post_thumbnail( 12, $own );
sa_t_assert( 0 === sa_ready_articles_attach_cover( 12, $article ), 'owner image left alone' );
sa_t_assert( $own === get_post_thumbnail_id( 12 ), 'owner image still the thumbnail' );

/* 5) A new version of the bundled file (different hash) refreshes the picture. */
update_post_meta( $id, '_sa_ready_cover_hash', 'stale-hash' );
$fresh = sa_ready_articles_attach_cover( 10, $article );
sa_t_assert( is_int( $fresh ) && $fresh > 0 && $fresh !== $id, 'changed file → new upload' );
sa_t_assert( $fresh === get_post_thumbnail_id( 10 ), 'new upload is the thumbnail' );
sa_t_assert( 2 === count( $GLOBALS['t']['uploads'] ), 'second upload for the new version' );

/* 6) Records without a cover, bad paths and missing files. */
$none = $article;
unset( $none['cover'] );
sa_t_assert( 0 === sa_ready_articles_attach_cover( 10, sa_ready_article_normalize( $none ) ), 'no cover → no-op' );
$bad          = $article;
$bad['cover'] = array( 'file' => '../../wp-config.php', 'alt' => 'x' );
sa_t_assert( 0 === sa_ready_articles_attach_cover( 10, $bad ), 'path traversal rejected' );
$missing          = $article;
$missing['cover'] = array( 'file' => 'guides/does-not-exist.webp', 'alt' => 'x' );
sa_t_assert( is_wp_error( sa_ready_articles_attach_cover( 10, $missing ) ), 'missing file reported as error' );

/* 7) An explicit no-cover policy unsets only the thumbnail relation, not the attachment. */
$lighting = sa_ready_article_normalize( $guides['camping-lighting-guide'] );
sa_t_assert( ! empty( $lighting['remove_featured_image'] ) && empty( $lighting['cover'] ), 'lighting record requests removal and has no bundled cover' );
$current = get_post_thumbnail_id( 10 );
$uploads_before_removal = count( $GLOBALS['t']['uploads'] );
sa_t_assert( 0 === sa_ready_articles_apply_featured_image_policy( 10, $lighting ), 'explicit removal returns without uploading' );
sa_t_assert( 0 === get_post_thumbnail_id( 10 ), 'explicit removal unsets the post thumbnail' );
sa_t_assert( '1' === get_post_meta( 10, '_sa_ready_remove_featured_image', true ), 'explicit removal persists a post-scoped marker' );
sa_t_assert( isset( $GLOBALS['t']['posts'][ $current ] ), 'the image attachment itself is preserved' );
sa_t_assert( $uploads_before_removal === count( $GLOBALS['t']['uploads'] ), 'removal does not create an upload' );
$restored = sa_ready_articles_apply_featured_image_policy( 10, $article );
sa_t_assert( is_int( $restored ) && get_post_thumbnail_id( 10 ) === $restored, 'an ordinary cover record keeps its existing import behavior' );
sa_t_assert( '' === get_post_meta( 10, '_sa_ready_remove_featured_image', true ), 'ordinary cover policy clears the per-post removal marker' );

/* 8) Every active bundled cover in the data file is readable and valid WebP. */
$n = 0;
foreach ( $guides as $key => $g ) {
	if ( empty( $g['cover']['file'] ) ) {
		continue;
	}
	$abs = SA_CHILD_DIR . 'assets/img/' . $g['cover']['file'];
	sa_t_assert( is_readable( $abs ), "{$key}: cover file readable" );
	$n++;
}
sa_t_assert( $n >= 36, '36 remaining bundled covers are readable after the one-article migration' );

echo "PASS: ready-articles bundled covers ({$n} files; attach/replace/keep/reuse/remove rules)\n";
