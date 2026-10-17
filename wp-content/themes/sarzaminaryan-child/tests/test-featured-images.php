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
 * v2.18.16 — «دیدنی‌ها» از قاعدهٔ سراسری جدا شدند: تصویر شاخص هر دیدنی فقط
 * داخل بدنهٔ همان مقاله دیده می‌شود و در کارت‌ها، آرشیو، هیرو و صفحهٔ اصلی
 * پنهان می‌ماند، حتی اگر کلید سراسری روشن شود.
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
	'meta'      => array(),
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
function get_post_type( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) $GLOBALS['t']['current'];
	return isset( $GLOBALS['t']['posts'][ $post_id ]['type'] ) ? (string) $GLOBALS['t']['posts'][ $post_id ]['type'] : 'post';
}
function get_post_meta( $post_id, $key, $single = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return isset( $GLOBALS['t']['meta'][ $post_id ][ $key ] ) ? $GLOBALS['t']['meta'][ $post_id ][ $key ] : '';
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
 * 2b) «دیدنی‌ها»: تصاویر شاخص به‌طور کامل مخفی به صورت پیش‌فرض (v2.19.1).
 * ------------------------------------------------------------------------- */
$GLOBALS['t']['posts'][40] = array( 'thumb' => 400, 'type' => 'attraction', 'slug' => 'custom-attraction-test' );
$GLOBALS['t']['attached'][400] = array( 'missing' => false );
$GLOBALS['t']['meta'][400] = array( '_wp_attachment_image_alt' => 'کارت تصویر شاخص آبشار بیشه' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = false; // کلید سراسری خاموش است.

sa_t_assert( false === sa_attraction_images_visible(), 'attraction covers are hidden by default in v2.19.1' );
sa_t_assert( true === sa_featured_image_is_attraction( 40 ), 'the attraction post is recognised' );
sa_t_assert( false === sa_featured_image_available( 40, 'attraction-content' ), 'the attraction cover is hidden inside its own article by default' );
sa_t_assert( false === sa_featured_image_available( 40, 'card' ), 'the attraction cover never shows on list cards' );
sa_t_assert( false === sa_featured_image_available( 40, 'hero' ), 'the attraction cover never shows in the page hero/identity card' );
sa_t_assert( false === sa_featured_image_available( 40 ), 'the attraction cover is not available without the article context' );
sa_t_assert( '' === sa_attraction_featured_figure( 40 ), 'no attraction figure is printed by default' );

/* Opt-in test when sa_show_attraction_image is explicitly enabled for a custom attraction. */
$GLOBALS['t']['mods']['sa_show_attraction_image'] = true;
sa_t_assert( true === sa_attraction_images_visible(), 'the attraction key can be toggled in tests' );
sa_t_assert( true === sa_featured_image_available( 40, 'attraction-content' ), 'the attraction cover is available when explicitly enabled' );

$GLOBALS['t']['mods']['sa_show_featured_images'] = true;
sa_t_assert( false === sa_featured_image_available( 40, 'card' ), 'the global switch still keeps attraction covers off the cards' );
sa_t_assert( false === sa_featured_image_available( 40, 'hero' ), 'the global switch still keeps attraction covers out of the hero' );
sa_t_assert( true === sa_featured_image_available( 40, 'attraction-content' ), 'the article context stays the only allowed place' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = false;

$GLOBALS['t']['attached'][400]['missing'] = true;
sa_t_assert( false === sa_featured_image_available( 40, 'attraction-content' ), 'a deleted cover file is not printed inside the article either' );
$GLOBALS['t']['attached'][400]['missing'] = false;

$GLOBALS['t']['posts'][41] = array( 'thumb' => 410, 'type' => 'attraction', 'slug' => 'custom-attraction-no-alt' );
$GLOBALS['t']['attached'][410] = array( 'missing' => false );
$GLOBALS['t']['mods']['sa_show_attraction_image'] = false;
sa_t_assert( false === sa_attraction_images_visible(), 'the attraction key turns the in-article cover off' );
sa_t_assert( false === sa_featured_image_available( 40, 'attraction-content' ), 'the attraction key is respected' );
$GLOBALS['t']['mods']['sa_show_attraction_image'] = true;

/* نگهبان: بیرون از بدنهٔ مقاله پنهان، از درون بدنه مجاز (در صورت فعال‌سازی). */
$GLOBALS['t']['current'] = 40;
sa_t_assert( '' === sa_featured_image_guard( '<img>', 40 ), 'outside the article body the attraction cover is removed' );
$GLOBALS['sa_featured_image_render_context'] = 'attraction-content';
sa_t_assert( '<img>' === sa_featured_image_guard( '<img>', 40 ), 'inside the article body the attraction cover passes the guard' );
unset( $GLOBALS['sa_featured_image_render_context'] );
sa_t_assert( '' === sa_featured_image_guard( '<img>', 40 ), 'the guard hides it again once the article body is done' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = true;
sa_t_assert( '<img>' === sa_featured_image_guard( '<img>', 5 ), 'another post follows the global switch, not the attraction rule' );
sa_t_assert( '' === sa_featured_image_guard( '<img>', 40 ), 'the attraction cover stays hidden outside the article even with the global switch on' );
$GLOBALS['t']['mods']['sa_show_featured_images'] = false;

/* شکلِ نهایی: شکلِ مقالهٔ دیدنی. */
$figure = sa_attraction_featured_figure( 40 );
sa_t_assert( false !== strpos( $figure, 'class="sa-attraction-cover"' ), 'the figure wraps the cover image' );
sa_t_assert( false !== strpos( $figure, '<img' ), 'the figure prints the thumbnail' );
sa_t_assert( false !== strpos( $figure, 'کارت تصویر شاخص آبشار بیشه' ), 'the figure carries the short caption' );
sa_t_assert( '' === sa_attraction_featured_figure( 5 ), 'a non-attraction post never gets the figure' );
$figure_no_caption = sa_attraction_featured_figure( 41 );
sa_t_assert( false !== strpos( $figure_no_caption, '<img' ), 'an attraction without alt text still prints the image' );
sa_t_assert( false === strpos( $figure_no_caption, '<figcaption' ), 'no caption is printed when the alt text is empty' );
$GLOBALS['t']['mods']['sa_show_attraction_image'] = false;
sa_t_assert( '' === sa_attraction_featured_figure( 41 ), 'the attraction key removes the in-article figure' );
$GLOBALS['t']['mods']['sa_show_attraction_image'] = true;
$GLOBALS['t']['current'] = 0;
$GLOBALS['t']['mods']['sa_show_featured_images'] = true; // وضعیت پیش از این بخش، برای آزمون‌های پایین.

/* ---------------------------------------------------------------------------
 * 2c) قالب صفحهٔ موجودیت‌ها و ۳ کارت «شناسنامه» سایبری (v2.19.1):
 * استان، شهرستان و دیدنی هر کدام جدول شناسنامهٔ اختصاصی دارند و اطلاعات کلیدی تکراری ندارند.
 * ------------------------------------------------------------------------- */
$template = file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/single.php' );
sa_t_assert( false !== $template, 'the entity single template is readable' );
if ( false !== $template ) {
	$pos = array(
		'hero'    => strpos( $template, "get_template_part( 'template-parts/entity/hero' )" ),
		'profile' => strpos( $template, "get_template_part( 'template-parts/entity/province-identity' )" ),
		'county'  => strpos( $template, "get_template_part( 'template-parts/entity/county-profile' )" ),
		'facts'   => strpos( $template, "get_template_part( 'template-parts/entity/facts' )" ),
		'content' => strpos( $template, 'entry-content' ),
	);
	foreach ( $pos as $name => $position ) {
		sa_t_assert( false !== $position, 'the template still contains the ' . $name . ' reference' );
	}
	sa_t_assert( $pos['profile'] < $pos['content'], 'province identity card comes before body content' );
	sa_t_assert( $pos['county'] < $pos['content'], 'county identity card comes before body content' );

	foreach ( array(
		'province-identity.php'   => 'sa-cyber-id--province',
		'county-profile.php'      => 'sa-cyber-id--county',
		'identity-attraction.php' => 'sa-cyber-id--attraction',
	) as $id_file => $expected_class ) {
		$id_tpl = file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/' . $id_file );
		sa_t_assert( false !== $id_tpl && false !== strpos( $id_tpl, $expected_class ), $id_file . ' renders its distinct cyber-tech class ' . $expected_class );
		sa_t_assert( false !== strpos( $id_tpl, '<span class="sa-cyber-id__badge">شناسنامه</span>' ), $id_file . ' carries the «شناسنامه» badge' );
		sa_t_assert( false !== strpos( $id_tpl, 'sa_cyber_id_value_or_review' ), $id_file . ' uses the compatible identity value helper' );
	}
}

/* ---------------------------------------------------------------------------
 * 3) The helper used by the templates.
 * ------------------------------------------------------------------------- */
sa_t_assert( '' !== sa_get_featured_image( 'sa-hero', array(), 5 ), 'the helper prints the image when everything checks out' );
sa_t_assert( '' === sa_get_featured_image( 'sa-attraction-cover', array(), 40 ), 'the helper refuses an attraction cover outside the article context' );
sa_t_assert( '' !== sa_get_featured_image( 'sa-attraction-cover', array(), 40, 'attraction-content' ), 'the helper prints it for the article context' );
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

sa_t_assert( 'تصویر در دسترس نیست' === sa_missing_image_label(), 'the default label is the owner-facing text' );
$GLOBALS['t']['mods']['sa_missing_image_label'] = 'عکس به‌زودی';
sa_t_assert( 'عکس به‌زودی' === sa_missing_image_label(), 'the label is editable' );
unset( $GLOBALS['t']['mods']['sa_missing_image_label'] );

$broken = '<figure class="wp-block-image"><img src="https://example.test/wp-content/uploads/2026/10/gone.jpg" alt="نمای گمشده" class="wp-image-9"><figcaption>زیرنویس می‌ماند</figcaption></figure>';
$fixed  = sa_hide_missing_content_images( $broken );
sa_t_assert( false === strpos( $fixed, '<img' ), 'the broken image tag is gone' );
sa_t_assert( false !== strpos( $fixed, 'class="sa-media-missing"' ), 'the placeholder box is printed instead' );
sa_t_assert( false !== strpos( $fixed, 'تصویر در دسترس نیست' ), 'the placeholder carries the label' );
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

echo "PASS: featured images (hidden by default, attraction cover first in the article, missing files never shown, in-content placeholder)\n";
