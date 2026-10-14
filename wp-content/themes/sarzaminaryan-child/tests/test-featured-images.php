<?php
/**
 * Standalone tests for the featured-image switch and the placeholder for missing
 * in-content images (inc/featured-images.php, v2.15.0).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-featured-images.php
 *
 * Covers the owner's rules: featured images are hidden by default (and are never
 * shown when their file is not on the server), posts still render without them,
 * and an in-content image whose file is gone becomes a calm text box — while the
 * editor, the REST API and anything stored in the database stay untouched.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array(
	'mods'      => array(),
	'posts'     => array(),
	'attached'  => array(),
	'files'     => array(),
	'is_admin'  => false,
	'rest'      => false,
	'ajax'      => false,
	'tmp'       => rtrim( sys_get_temp_dir(), '/' ) . '/sa-feat-test-' . uniqid(),
	'current'   => 0,
	'ready_articles' => array(
		'camping-lighting-guide'        => array( 'slug' => 'camping-lighting-guide', 'remove_featured_image' => true ),
		'portable-water-filter-review' => array( 'slug' => 'portable-water-filter-review', 'remove_featured_image' => true ),
		'camping-stove-review'         => array( 'slug' => 'camping-stove-review', 'remove_featured_image' => true ),
		'multi-tool-knife-review'       => array( 'slug' => 'multi-tool-knife-review', 'remove_featured_image' => true ),
		'gps-navigation-tools'          => array( 'slug' => 'gps-navigation-tools', 'remove_featured_image' => true ),
		'rope-carabiner-guide'          => array( 'slug' => 'rope-carabiner-guide', 'remove_featured_image' => true ),
		'professional-rescue-kit'       => array( 'slug' => 'professional-rescue-kit', 'remove_featured_image' => true ),
		'diy-water-filter'              => array( 'slug' => 'diy-water-filter', 'remove_featured_image' => true ),
		'diy-can-stove'                 => array( 'slug' => 'diy-can-stove', 'remove_featured_image' => true ),
		'rope-alternatives-outdoor'     => array( 'slug' => 'rope-alternatives-outdoor', 'remove_featured_image' => true ),
	),
);
@mkdir( $GLOBALS['t']['tmp'] . '/2026/10', 0777, true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
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
function absint( $n ) {
	return abs( (int) $n );
}
function get_theme_mod( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['t']['mods'] ) ? $GLOBALS['t']['mods'][ $key ] : $default;
}
function is_admin() {
	return (bool) $GLOBALS['t']['is_admin'];
}
function wp_doing_ajax() {
	return (bool) $GLOBALS['t']['ajax'];
}
function is_feed() {
	return false;
}
function get_the_ID() {
	return (int) $GLOBALS['t']['current'];
}
function get_post_field( $field, $post_id ) {
	if ( 'post_name' !== $field ) {
		return '';
	}
	return isset( $GLOBALS['t']['posts'][ $post_id ]['slug'] ) ? $GLOBALS['t']['posts'][ $post_id ]['slug'] : '';
}
function sa_ready_articles_data() {
	return $GLOBALS['t']['ready_articles'];
}
function has_post_thumbnail( $post_id ) {
	return ! empty( $GLOBALS['t']['posts'][ $post_id ]['thumb'] );
}
function get_post_thumbnail_id( $post_id ) {
	return isset( $GLOBALS['t']['posts'][ $post_id ]['thumb'] ) ? (int) $GLOBALS['t']['posts'][ $post_id ]['thumb'] : 0;
}
function get_the_post_thumbnail( $post_id, $size = '', $attr = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return '<img class="thumb-' . (int) $post_id . '" src="https://example.test/uploads/photo.jpg">';
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $s ) {
	return (string) $s;
}
function wp_strip_all_tags( $s ) {
	return trim( strip_tags( (string) $s ) );
}

/* ---- Lightweight stand-ins for the media helpers of inc/media-restore.php. ---- */
function sa_media_local_path( $url ) {
	$url = (string) $url;
	$needle = 'https://example.test/wp-content/uploads/';
	if ( 0 !== strpos( $url, $needle ) ) {
		return '';
	}
	return $GLOBALS['t']['tmp'] . '/' . ltrim( substr( $url, strlen( $needle ) ), '/' );
}
function sa_media_find_variant( $path ) {
	return isset( $GLOBALS['t']['files'][ $path . '-variant' ] ) ? $path . '-variant' : '';
}
function sa_media_attachment_missing( $attachment_id ) {
	return ! empty( $GLOBALS['t']['attached'][ $attachment_id ]['missing'] );
}

require_once dirname( __DIR__ ) . '/inc/featured-images.php';

/* ---------------------------------------------------------------------------
 * 1) The switch.
 * ------------------------------------------------------------------------- */
sa_t_assert( false === sa_featured_images_visible(), 'featured images are hidden by default' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = true;
sa_t_assert( true === sa_featured_images_visible(), 'the customizer switch turns them back on' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = false;

/* ---------------------------------------------------------------------------
 * 2) Availability: switch + thumbnail + a file that really exists.
 * ------------------------------------------------------------------------- */
$GLOBALS['t']['posts'][5] = array( 'thumb' => 50 );
$GLOBALS['t']['attached'][50] = array( 'missing' => false );
file_put_contents( $GLOBALS['t']['tmp'] . '/2026/10/photo.jpg', 'x' );
$GLOBALS['t']['files'][ $GLOBALS['t']['tmp'] . '/2026/10/photo.jpg' ] = true;

sa_t_assert( false === sa_featured_image_available( 5 ), 'off switch hides even a healthy thumbnail' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = true;
sa_t_assert( true === sa_featured_image_available( 5 ), 'on switch + existing file shows the thumbnail' );
sa_t_assert( false === sa_featured_image_available( 6 ), 'a post without a thumbnail never shows one' );

// Per-post flags hide only migrated guides' featured images; the global switch stays on.
$GLOBALS['t']['posts'][7] = array( 'thumb' => 70, 'slug' => 'camping-lighting-guide' );
$GLOBALS['t']['attached'][70] = array( 'missing' => false );
sa_t_assert( true === sa_featured_image_suppressed( 7 ), 'the lighting record suppresses only its own featured image' );
sa_t_assert( false === sa_featured_image_available( 7 ), 'the lighting image stays hidden with the global switch on' );
$GLOBALS['t']['posts'][8] = array( 'thumb' => 80, 'slug' => 'portable-water-filter-review' );
$GLOBALS['t']['attached'][80] = array( 'missing' => false );
sa_t_assert( true === sa_featured_image_suppressed( 8 ), 'the water-filter record suppresses only its own featured image' );
$GLOBALS['t']['posts'][9] = array( 'thumb' => 90, 'slug' => 'camping-stove-review' );
$GLOBALS['t']['attached'][90] = array( 'missing' => false );
sa_t_assert( true === sa_featured_image_suppressed( 9 ), 'the stove record suppresses only its own featured image' );
sa_t_assert( false === sa_featured_image_available( 9 ), 'the stove thumbnail stays hidden with the global switch on' );
$GLOBALS['t']['posts'][10] = array( 'thumb' => 100, 'slug' => 'multi-tool-knife-review' );
$GLOBALS['t']['attached'][100] = array( 'missing' => false );
sa_t_assert( true === sa_featured_image_suppressed( 10 ), 'the multi-tool record suppresses only its own featured image' );
sa_t_assert( false === sa_featured_image_available( 10 ), 'the multi-tool thumbnail stays hidden with the global switch on' );
$GLOBALS['t']['posts'][11] = array( 'thumb' => 110, 'slug' => 'gps-navigation-tools' );
$GLOBALS['t']['attached'][110] = array( 'missing' => false );
sa_t_assert( true === sa_featured_image_suppressed( 11 ), 'the GPS record suppresses only its own featured image' );
sa_t_assert( false === sa_featured_image_available( 11 ), 'the GPS thumbnail stays hidden with the global switch on' );

$new_slugs = array( 'rope-carabiner-guide', 'professional-rescue-kit', 'diy-water-filter', 'diy-can-stove', 'rope-alternatives-outdoor' );
foreach ( $new_slugs as $index => $slug ) {
	$post_id = 12 + $index;
	$GLOBALS['t']['posts'][ $post_id ] = array( 'thumb' => 100 + $post_id, 'slug' => $slug );
	$GLOBALS['t']['attached'][ 100 + $post_id ] = array( 'missing' => false );
	sa_t_assert( true === sa_featured_image_suppressed( $post_id ), $slug . ': only its own featured image is suppressed' );
	sa_t_assert( false === sa_featured_image_available( $post_id ), $slug . ': thumbnail stays hidden while the global switch is on' );
}

foreach ( array_merge( array( 7, 9, 10, 11 ), range( 12, 16 ) ) as $post_id ) {
	$GLOBALS['t']['current'] = $post_id;
	sa_t_assert( '' === sa_featured_image_guard( '<img>' ), "post {$post_id}: the current migrated article thumbnail is hidden" );
	sa_t_assert( '<img>' === sa_featured_image_guard( '<img>', 5 ), "post {$post_id}: a nested image for another article is untouched" );
}
$GLOBALS['t']['current'] = 8;
sa_t_assert( '' === sa_featured_image_guard( '<img>' ), 'the water-filter article hides its own current thumbnail' );
sa_t_assert( '<img>' === sa_featured_image_guard( '<img>', 5 ), 'the water-filter flag does not hide another post thumbnail' );
$GLOBALS['t']['current'] = 0;
sa_t_assert( true === sa_featured_image_available( 5 ), 'all other articles remain visible under the same global setting' );

$GLOBALS['t']['attached'][50]['missing'] = true;
sa_t_assert( false === sa_featured_image_available( 5 ), 'a thumbnail whose file was deleted is not shown' );
$GLOBALS['t']['attached'][50]['missing'] = false;

/* The guard filter mirrors the same rules outside the admin. */
$GLOBALS['t']['mods']['sa_show_featured_images'] = false;
sa_t_assert( '' === sa_featured_image_guard( '<img>' ), 'the guard removes the image while the switch is off' );
$GLOBALS['t']['is_admin'] = true;
sa_t_assert( '<img>' === sa_featured_image_guard( '<img>' ), 'the admin keeps its previews' );
$GLOBALS['t']['is_admin'] = false;
$GLOBALS['t']['mods']['sa_show_featured_images'] = true;
$GLOBALS['t']['current'] = 5;
$GLOBALS['t']['attached'][50]['missing'] = true;
sa_t_assert( '' === sa_featured_image_guard( '<img>' ), 'the guard drops a missing-file thumbnail' );
$GLOBALS['t']['attached'][50]['missing'] = false;
sa_t_assert( '<img>' === sa_featured_image_guard( '<img>' ), 'healthy thumbnails pass the guard' );
$GLOBALS['t']['current'] = 0;

/* ---------------------------------------------------------------------------
 * 3) The helper used by the templates.
 * ------------------------------------------------------------------------- */
sa_t_assert( '' !== sa_get_featured_image( 'sa-hero', array(), 5 ), 'the helper prints the image when everything checks out' );
sa_t_assert( '' === sa_get_featured_image( 'sa-hero', array(), 6 ), 'the helper prints nothing for a post without a thumbnail' );

/* ---------------------------------------------------------------------------
 * 4) Missing in-content images.
 * ------------------------------------------------------------------------- */
file_put_contents( $GLOBALS['t']['tmp'] . '/2026/10/gone.jpg', 'x' );
unlink( $GLOBALS['t']['tmp'] . '/2026/10/gone.jpg' );

sa_t_assert( false === sa_content_image_missing( 'https://example.test/wp-content/uploads/2026/10/photo.jpg' ), 'an existing file is not missing' );
sa_t_assert( true === sa_content_image_missing( 'https://example.test/wp-content/uploads/2026/10/gone.jpg' ), 'a deleted file is missing' );
sa_t_assert( false === sa_content_image_missing( 'https://upload.wikimedia.org/a.jpg' ), 'external images are not judged' );
sa_t_assert( false === sa_content_image_missing( 'data:image/png;base64,AAA' ), 'inline data images are not judged' );

sa_t_assert( true === sa_hide_missing_images(), 'the placeholder is on by default' );
$GLOBALS['t']['mods']['sa_hide_missing_images'] = false;
sa_t_assert( false === sa_hide_missing_images(), 'the customizer can turn the placeholder off' );
$GLOBALS['t']['mods']['sa_hide_missing_images'] = true;

sa_t_assert( 'تصویر این بخش در دست بازبینی است' === sa_missing_image_label(), 'the default label is the owner-facing text' );
$GLOBALS['t']['mods']['sa_missing_image_label'] = 'عکس به‌زودی';
sa_t_assert( 'عکس به‌زودی' === sa_missing_image_label(), 'the label is editable' );
unset( $GLOBALS['t']['mods']['sa_missing_image_label'] );

$broken = '<figure class="wp-block-image"><img src="https://example.test/wp-content/uploads/2026/10/gone.jpg" alt="نمای گمشده" class="wp-image-9"><figcaption>زیرنویس می‌ماند</figcaption></figure>';
$fixed  = sa_hide_missing_content_images( $broken );
sa_t_assert( false === strpos( $fixed, '<img' ), 'the broken image tag is gone' );
sa_t_assert( false !== strpos( $fixed, 'class="sa-media-missing"' ), 'the placeholder box is printed instead' );
sa_t_assert( false !== strpos( $fixed, 'تصویر این بخش در دست بازبینی است' ), 'the placeholder carries the label' );
sa_t_assert( false !== strpos( $fixed, 'زیرنویس می‌ماند' ), 'the caption survives' );
sa_t_assert( false !== strpos( $fixed, 'title="نمای گمشده"' ), 'the alt text is kept as a tooltip' );

$healthy = '<img src="https://example.test/wp-content/uploads/2026/10/photo.jpg" alt="سالم">';
sa_t_assert( $healthy === sa_hide_missing_content_images( $healthy ), 'healthy images are untouched' );
$external = '<img src="https://upload.wikimedia.org/a.jpg">';
sa_t_assert( $external === sa_hide_missing_content_images( $external ), 'external images are untouched' );
$inline = '<img src="data:image/png;base64,AAA">';
sa_t_assert( $inline === sa_hide_missing_content_images( $inline ), 'inline data images are untouched' );

/* The editor and the REST API must always receive the stored content. */
$GLOBALS['t']['is_admin'] = true;
sa_t_assert( $broken === sa_hide_missing_content_images( $broken ), 'the admin screens keep the real markup' );
$GLOBALS['t']['is_admin'] = false;
define( 'REST_REQUEST', true );
sa_t_assert( $broken === sa_hide_missing_content_images( $broken ), 'the block editor (REST) keeps the real markup' );

/* A surviving size variant means the image is not missing. */
define( 'SA_FEAT_ALT_URL', 'https://example.test/wp-content/uploads/2026/10/variant-only.jpg' );
$GLOBALS['t']['files'][ $GLOBALS['t']['tmp'] . '/2026/10/variant-only.jpg-variant' ] = true;
sa_t_assert( false === sa_content_image_missing( SA_FEAT_ALT_URL ), 'a surviving size variant counts as present' );

/* Clean up. */
@unlink( $GLOBALS['t']['tmp'] . '/2026/10/photo.jpg' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $GLOBALS['t']['tmp'] . '/2026/10' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $GLOBALS['t']['tmp'] . '/2026' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
@rmdir( $GLOBALS['t']['tmp'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

echo "PASS: featured images (switch off by default, missing files never shown, in-content placeholder)\n";
