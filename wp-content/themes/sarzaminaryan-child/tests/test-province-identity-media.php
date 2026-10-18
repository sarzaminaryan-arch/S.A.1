<?php
/** Read-only filename lookup, scope, silent missing images, and actual template rendering. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['pid'] = 1;
$GLOBALS['context'] = array( 'admin' => false, 'feed' => false, 'ajax' => false, 'singular' => true, 'loop' => true, 'main' => true );
$GLOBALS['posts'] = array(
 1 => array( 'post_type' => 'province', 'post_status' => 'publish', 'post_name' => 'alborz' ),
 2 => array( 'post_type' => 'province', 'post_status' => 'publish', 'post_name' => 'tehran' ),
 3 => array( 'post_type' => 'province', 'post_status' => 'publish', 'post_name' => 'fars' ),
 4 => array( 'post_type' => 'city', 'post_status' => 'publish', 'post_name' => 'alborz' ),
);
$GLOBALS['candidates'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['files'] = array();
$GLOBALS['queries'] = 0;
function check( $ok, $why ) { if ( ! $ok ) { throw new RuntimeException( $why ); } }
function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['filters'][ $hook ] = array( $callback, $priority ); }
function is_admin() { return $GLOBALS['context']['admin']; }
function is_feed() { return $GLOBALS['context']['feed']; }
function wp_doing_ajax() { return $GLOBALS['context']['ajax']; }
function is_singular( $type ) { return $GLOBALS['context']['singular']; }
function in_the_loop() { return $GLOBALS['context']['loop']; }
function is_main_query() { return $GLOBALS['context']['main']; }
function get_the_ID() { return $GLOBALS['pid']; }
function get_queried_object_id() { return $GLOBALS['pid']; }
function get_post_field( $key, $id ) { return $GLOBALS['posts'][ $id ][ $key ] ?? ''; }
function get_post_type( $id ) { return get_post_field( 'post_type', $id ); }
function get_post_status( $id ) { return get_post_field( 'post_status', $id ); }
function wp_get_post_parent_id( $id ) { return get_post_field( 'post_parent', $id ); }
function get_post_mime_type( $id ) { return get_post_field( 'post_mime_type', $id ); }
function post_password_required( $id ) { return ! empty( $GLOBALS['posts'][ $id ]['password'] ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function get_attached_file( $id ) { return $GLOBALS['files'][ $id ] ?? ''; }
function wp_get_attachment_url( $id ) { return $GLOBALS['urls'][ $id ] ?? ''; }
function wp_basename( $name ) { return basename( $name ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_attr( $s ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) ); }
function sa_province_profile_catalog() {
 $rows = require dirname( __DIR__ ) . '/data/provinces.php';
 return array( 'by_slug' => array_column( $rows, null, 'slug' ) );
}
function sa_province_name_for_post( $id, $prefix ) { return 'استان ' . ( 1 === $id ? 'البرز' : 'تهران' ); }
function wp_get_attachment_image( $id, $size, $icon, $attrs ) {
 $html = '<img src="' . esc_url( wp_get_attachment_url( $id ) ) . '" width="960" height="480"';
 foreach ( $attrs as $key => $value ) { $html .= ' ' . $key . '="' . esc_attr( $value ) . '"'; }
 return $html . '>';
}
class MediaDB {
 public $posts = 'wp_posts';
 public $postmeta = 'wp_postmeta';
 public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
 public function prepare( $sql, ...$args ) {
  check( array( 'alborz.webp', '%/alborz.webp' ) === $args || array( 'tehran.webp', '%/tehran.webp' ) === $args, 'Only exact, prepared canonical filename arguments' );
  check( false !== strpos( $sql, 'LIMIT 20' ) && false !== strpos( $sql, "post_status = 'inherit'" ) && false !== strpos( $sql, "post_mime_type = 'image/webp'" ), 'Bounded, read-only image attachment lookup' );
  return $sql;
 }
 public function get_col( $sql ) { ++$GLOBALS['queries']; return $GLOBALS['candidates']; }
}
$GLOBALS['wpdb'] = new MediaDB();
require dirname( __DIR__ ) . '/inc/province-identity-media.php';
check( array( 'sa_province_identity_remove_legacy_cover', 9 ) === $GLOBALS['filters']['the_content'], 'Legacy removal after installed plugin, before wpautop' );
check( 'alborz.webp' === sa_province_identity_media_filename( 'alborz' ) && '' === sa_province_identity_media_filename( '../alborz' ) && '' === sa_province_identity_media_filename( 'Alborz' ), 'Filename whitelist and lowercase' );
check( 31 === count( sa_province_profile_catalog()['by_slug'] ), 'Full filename catalog' );
check( '<div class="sa-province-identity-media" aria-hidden="true"></div>' === sa_province_identity_media_html( 1 ), 'Missing attachment reserves an entirely silent, image-free slot' );
foreach ( array( 3, 4 ) as $id ) { $GLOBALS['pid'] = $id; check( '' === sa_province_identity_media_html( $id ), 'No non-pilot or county slot' ); }
$GLOBALS['pid'] = 1;
foreach ( array( 'admin', 'feed', 'ajax' ) as $key ) { $GLOBALS['context'][ $key ] = true; check( ! sa_province_identity_media_enabled( 1 ), 'No admin/feed/AJAX' ); $GLOBALS['context'][ $key ] = false; }
$GLOBALS['context']['singular'] = false; check( ! sa_province_identity_media_enabled( 1 ), 'No archive cards' ); $GLOBALS['context']['singular'] = true;
$GLOBALS['posts'][1]['password'] = true; check( ! sa_province_identity_media_enabled(1), 'No protected province image' ); unset( $GLOBALS['posts'][1]['password'] );
$GLOBALS['posts'][1]['post_status'] = 'draft'; check( ! sa_province_identity_media_enabled(1), 'No draft image' ); $GLOBALS['posts'][1]['post_status'] = 'publish';
$file = tempnam( sys_get_temp_dir(), 'sa-media-test-' ); file_put_contents( $file, 'readable attachment fixture' );
foreach ( array( 20 => '2026/10/alborz.webp', 21 => '2026/09/alborz.webp', 22 => '2026/10/not-alborz.webp', 23 => '2026/10/alborz-1.webp', 24 => '2026/10/Alborz.webp' ) as $id => $name ) {
 $GLOBALS['posts'][$id] = array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/webp' );
 $GLOBALS['meta'][$id]['_wp_attached_file'] = $name;
 $GLOBALS['files'][$id] = $file; $GLOBALS['urls'][$id] = '/uploads/' . $name;
}
$GLOBALS['candidates'] = array(22,23,24,20,21);
check( 20 === sa_province_identity_media_attachment(1), 'Reject prefixes, -1 and uppercase; newest exact name wins' );
$GLOBALS['files'][20] = $file . '-missing';
check( 21 === sa_province_identity_media_attachment(1), 'Missing local file skipped, previous valid duplicate used' );
$GLOBALS['posts'][21]['post_parent'] = 3; $GLOBALS['posts'][3]['post_status'] = 'private';
check(0 === sa_province_identity_media_attachment(1), 'Never borrow media from a private parent');
$GLOBALS['posts'][3]['post_status'] = 'publish'; $GLOBALS['posts'][3]['password'] = true;
check(0 === sa_province_identity_media_attachment(1), 'Never borrow media from a password-protected parent');
unset($GLOBALS['posts'][3]['password']); $GLOBALS['posts'][21]['post_parent'] = 0;
$GLOBALS['posts'][21]['post_mime_type'] = 'image/jpeg'; check(0 === sa_province_identity_media_attachment(1), 'Reject non-WebP record');
$GLOBALS['posts'][21]['post_mime_type'] = 'image/webp';
$GLOBALS['meta'][21]['_wp_attachment_image_alt'] = '"><script>bad()</script>';
$html = sa_province_identity_media_html(1);
check( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Attachment alt is escaped' );
$cover = '<figure class="sa-province-cover" data-sa-province-cover="21"><img src="/old.webp"></figure>';
$article = '<p>متن اصلی</p><img src="/article.webp">';
check( $article === sa_province_identity_remove_legacy_cover( $cover . $article ), 'Only plugin cover removed; normal text/images preserved' );
function get_option( $name, $default = false ) { return 'sa_pc_journal_1' === $name ? array('state'=>'applied','new_id'=>21,'slug'=>'alborz') : $default; }
function get_post_thumbnail_id( $id ) { return 21; }
function sa_pc_catalog() { return array('alborz'=>array('alt'=>'تابلوی گرافیکی استان البرز')); }
function wp_get_attachment_metadata( $id ) { return array('width'=>960,'height'=>480); }
$plugin_public = dirname(__DIR__,3).'/plugins/sa-province-covers/includes/class-sa-pc-public.php';
if (is_readable($plugin_public)) {
 require $plugin_public;
 $injected = SA_PC_Public::content($article);
 check(false !== strpos($injected,'data-sa-province-cover='), 'Actual installed plugin produces its article cover');
 check($article === sa_province_identity_remove_legacy_cover($injected), 'Actual plugin plus theme renders no duplicate large cover');
}

check( $article === sa_province_identity_remove_legacy_cover( $article ), 'Article without cover untouched' );
foreach ( array( 'loop', 'main' ) as $key ) { $GLOBALS['context'][$key] = false; check( $cover.$article === sa_province_identity_remove_legacy_cover( $cover.$article ), 'Secondary loops unchanged' ); $GLOBALS['context'][$key] = true; }
$GLOBALS['pid'] = 3; check( $cover.$article === sa_province_identity_remove_legacy_cover($cover.$article), 'Other provinces keep previous display' );
// Render the actual identity template; no substitute markup in the preview.
function sa_province_profile( $slug ) { $data = json_decode(file_get_contents(dirname(__DIR__).'/data/province-profiles.json'),true); return $data['provinces'][$slug]; }
function sa_region_province( $slug ) { return array(); }
function get_the_title( $id ) { return sa_province_name_for_post($id,true); }
function has_excerpt( $id ) { return true; }
function get_the_excerpt( $id ) { return 'اطلاعات ' . sa_province_name_for_post($id,true) . ' در کنار تصویر و جدول زیر آن قرار دارد.'; }
function sa_fa_digits( $v ) { return $v; }
function sa_cyber_id_value_or_review( $value, $html ) { return $html ? $value : esc_html($value); }
$GLOBALS['pid'] = 1;
$GLOBALS['meta'][21]['_wp_attachment_image_alt'] = 'تابلوی گرافیکی استان البرز';
ob_start(); include dirname(__DIR__).'/template-parts/entity/province-identity.php'; $preview = ob_get_clean();
check( 1 === substr_count($preview,'class="sa-province-identity-media"') && 8 === substr_count($preview,'class="sa-cyber-grid__code"'), 'One image and all eight information cells remain inside real card' );
check( strpos($preview,'sa-province-identity-media') < strpos($preview,'sa-cyber-id__header') && strpos($preview,'sa-cyber-id__header') < strpos($preview,'sa-cyber-id__table-wrap'), 'Image, adjacent header, and full-width facts order' );
$GLOBALS['pid'] = 2;
$GLOBALS['meta'][21]['_wp_attached_file'] = '2026/10/tehran.webp'; $GLOBALS['urls'][21] = '/uploads/2026/10/tehran.webp';
check(21 === sa_province_identity_media_attachment(2), 'Tehran uses its own exact filename');
$GLOBALS['meta'][21]['_wp_attachment_image_alt'] = 'تابلوی گرافیکی استان تهران';
ob_start(); include dirname(__DIR__).'/template-parts/entity/province-identity.php'; $tehran_preview = ob_get_clean();
check(1 === substr_count($tehran_preview,'class="sa-province-identity-media"') && false !== strpos($tehran_preview,'استان تهران'), 'Tehran renders the same pilot layout');
$GLOBALS['candidates'] = array(); check(0 === sa_province_identity_media_attachment(2), 'Deleted attachment immediately becomes empty; no stale miss/hit cache');
unlink($file);
if (defined('SA_PROVINCE_MEDIA_PREVIEW')) { echo $preview . $tehran_preview; } else { echo "PASS: province identity media exact filenames, pilot scope, duplicates, silent misses, escaping, no duplicate cover and actual template\n"; }
