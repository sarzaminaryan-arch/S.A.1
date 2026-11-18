<?php
/**
 * Standalone tests for licensed place photos (inc/county-media.php, v2.22.98).
 *
 * The owner's rule: a photo may only appear when its licence allows reuse
 * *and* the photographer is named. These tests pin the policy, not the UI:
 * which licences pass, which URLs the site may fetch, and that the credit
 * line always carries the author.
 *
 * Since 2.22.116 they pin the owner's hand on the photos: the «مکان در یک نگاه»
 * box is gone and the county gallery is the only photo block (a responsive slider),
 * every photo is stored as a 4:3 WebP under 100 KB, and the owner can hide /
 * re-order / replace / trash a photo from the county edit screen — plus a file he
 * sends himself (not hosted by Wikimedia) is accepted only with real bytes and a
 * named author.
 *
 * Run: php wp-content/themes/sarzaminaryan-child/tests/test-county-media.php
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'MB_IN_BYTES' ) ) {
	define( 'MB_IN_BYTES', 1048576 );
}

$GLOBALS['t'] = array( 'meta' => array(), 'attachments' => array() );

function sa_t_assert( $c, $m ) {
	if ( ! $c ) {
		fwrite( STDERR, "FAIL: {$m}\n" );
		exit( 1 );
	}
}
function absint( $v ) {
	return abs( (int) $v );
}
function esc_html( $v ) {
	return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $v ) {
	return esc_html( $v );
}
function esc_url( $v ) {
	return (string) $v;
}
function esc_url_raw( $v ) {
	return (string) $v;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function get_post_meta( $id, $key, $single = true ) {
	return $GLOBALS['t']['meta'][ (int) $id ][ $key ] ?? '';
}
function get_the_ID() {
	return 0;
}
function get_post_type( $id = 0 ) {
	return 'city';
}
function get_the_title( $id = 0 ) {
	return 'بجنورد';
}
function wp_strip_all_tags( $v ) {
	return trim( strip_tags( (string) $v ) );
}
function wp_get_attachment_image( $id, $size, $icon, $attr ) {
	return '<img src="x.jpg" alt="' . esc_attr( $attr['class'] ?? '' ) . '">';
}
function wp_get_attachment_image_src( $id, $size ) {
	return array( 'x.jpg', 480, 480 );
}
function wp_get_attachment_url( $id ) {
	return 'x.jpg';
}

require_once dirname( __DIR__ ) . '/inc/county-media.php';

/* 1 — only reuse-friendly licences are accepted. */
$allowed = sa_photo_allowed_licenses();
foreach ( array( 'cc0', 'pd', 'cc-by-4', 'cc-by-sa-4' ) as $ok ) {
	sa_t_assert( isset( $allowed[ $ok ] ), "licence {$ok} must be allowed" );
}
sa_t_assert( '' === sa_photo_license_label( 'cc-by-nd-4' ), 'ND licences are refused (no derivatives)' );
sa_t_assert( '' === sa_photo_license_label( 'cc-by-nc-4' ), 'NC licences are refused (no commercial use)' );
sa_t_assert( '' === sa_photo_license_label( 'all-rights-reserved' ), 'all-rights-reserved is refused' );
sa_t_assert( '' === sa_photo_license_label( '' ), 'an empty licence is refused' );
sa_t_assert( 'CC BY 4.0' === sa_photo_license_label( 'CC-BY-4' ), 'licence codes are matched case-insensitively' );
sa_t_assert( 'CC BY-SA 3.0' === sa_photo_license_label( 'cc-by-sa-3.0' ), 'the 3.0/4.0 spelling Commons returns is accepted' );

/* 2 — the site may only fetch from the allow-listed image hosts, over HTTPS. */
sa_t_assert( true === sa_photo_url_allowed( 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/x.jpg/640px-x.jpg' ), 'the Commons file host is allowed' );
sa_t_assert( true === sa_photo_url_allowed( 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/ab/x.jpg/960px-x.jpg' ), 'the thumbnail host is allowed' );
sa_t_assert( false === sa_photo_url_allowed( 'https://commons.wikimedia.org/wiki/Special:FilePath/x.jpg' ), 'the wiki page host is not a file host' );
sa_t_assert( false === sa_photo_url_allowed( 'http://upload.wikimedia.org/x.jpg' ), 'plain HTTP is refused' );
sa_t_assert( false === sa_photo_url_allowed( 'https://evil.example.com/upload.wikimedia.org/x.jpg' ), 'a look-alike host is refused' );
sa_t_assert( false === sa_photo_url_allowed( 'https://kojaro.com/x.jpg' ), 'a travel portal is refused' );

/* 3 — a photo without an author or licence is never publishable. */
$GLOBALS['t']['meta'][10] = array(
	SA_PHOTO_META_AUTHOR  => 'حسین معمری',
	SA_PHOTO_META_LICENSE => 'cc-by-4',
	SA_PHOTO_META_SOURCE  => 'https://commons.wikimedia.org/wiki/File:x.jpg',
);
sa_t_assert( true === sa_photo_is_publishable( 10 ), 'author + CC BY is publishable' );

$GLOBALS['t']['meta'][11] = array( SA_PHOTO_META_LICENSE => 'cc-by-4' );
sa_t_assert( false === sa_photo_is_publishable( 11 ), 'a photo with no named author is not publishable' );

$GLOBALS['t']['meta'][12] = array( SA_PHOTO_META_AUTHOR => 'کسی', SA_PHOTO_META_LICENSE => 'cc-by-nd-4' );
sa_t_assert( false === sa_photo_is_publishable( 12 ), 'a photo with a refused licence is not publishable' );

/* 4 — the credit line names the photographer, quietly (owner order, 17 Nov 2026):
 *     no «عکس:» headline and no licence name in the visible caption; the licence AND the
 *     source page stay one tap away as links/titles, and the place name sits on the photo. */
// پروندهٔ ویکی‌مدیا همیشه نشانی مجوز را دارد؛ بدون آن، اعتبار عکس به متن ساده تبدیل
// می‌شد و «تک‌کلیک تا مجوز» از دست می‌رفت. (این خط پیش‌تر جا افتاده بود و تست را
// از کار ۲۲۲.۹۸ قرمز نگه می‌داشت.)
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_LICENSE_URL ] = 'https://creativecommons.org/licenses/by/4.0/';
$credit = sa_photo_credit( 10 );
sa_t_assert( 'حسین معمری' === $credit['author'], 'credit carries the author' );
sa_t_assert( 'CC BY 4.0' === $credit['license_label'], 'credit carries the licence label' );
sa_t_assert( 'https://creativecommons.org/licenses/by/4.0/' === $credit['license_url'], 'credit carries the licence URL' );
$html  = sa_photo_frame( 10, 'sa-square' );
$visible = wp_strip_all_tags( $html );
sa_t_assert( false !== strpos( $html, 'sa-photo__credit' ), 'the frame prints a credit line' );
sa_t_assert( false !== strpos( $html, 'حسین معمری' ), 'the credit line names the author' );
sa_t_assert( false === strpos( $visible, 'عکس:' ), 'the visible credit drops the «عکس:» headline' );
sa_t_assert( false === strpos( $visible, 'CC BY' ), 'the visible credit no longer spells out the licence name' );
sa_t_assert( false !== strpos( $html, 'creativecommons.org/licenses/by/4.0' ), 'the licence link still travels with the photo' );
sa_t_assert( false !== strpos( $html, 'rel="license nofollow noopener"' ), 'the licence link keeps rel=license (attribution stays machine-readable)' );
sa_t_assert( false !== strpos( $html, '<p class="sa-photo__credit">' ), 'the credit is a line under the frame, not a caption inside it' );
sa_t_assert( '' === sa_photo_frame( 11 ), 'no frame is built for an unpublishable photo' );

/* 4c — the place name is written on the photo (clean, transparent) for every county:
 *      نام مکان کوتاه‌شده روی عکس، آدرس کامل در خط دوم. دو قاب ساخته می‌شود چون
 *      برچسب روی عکس پیش از ترمیمِ نام ساخته و در `$overlay` نگه داشته می‌شود. */
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_PLACE ] = 'باغ فین (کاشان)';
unset( $GLOBALS['t']['meta'][10][ SA_PHOTO_META_AREA ] );
$overlay = sa_photo_frame( 10, 'sa-square' );
sa_t_assert( false !== strpos( $overlay, 'class="sa-photo__caption"' ), 'the photo carries an overlay caption' );
sa_t_assert( 'باغ فین' === sa_photo_overlay_label( 10 ), 'the overlay label trims the parenthetical off the place name' );
sa_t_assert( false !== strpos( $overlay, '>باغ فین<' ), 'the overlay names the place, without the parenthetical' );
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_AREA ] = 'کاشان، خیابان امیرکبیر';
$overlay_area = sa_photo_frame( 10, 'sa-square' );
sa_t_assert( false !== strpos( $overlay_area, '>کاشان، خیابان امیرکبیر<' ), 'the overlay can carry the address line' );
sa_t_assert( 1 === substr_count( $overlay_area, 'کاشان، خیابان امیرکبیر' ), 'the address is never printed twice on the same photo' );
sa_t_assert( '' === sa_photo_overlay_label( 11 ), 'a photo without a place gets no overlay text' );

/* 4d — the licence disclaimer paragraph is gone from the block (owner order). */
$mod_src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert( false === strpos( $mod_src, 'sa-spotlight__notice' ), 'the licence disclaimer paragraph is removed' );
sa_t_assert( false === strpos( $mod_src, 'بدون اجازهٔ کتبی' ), 'the «without written permission» sentence is removed' );
sa_t_assert( false !== strpos( $mod_src, "'sa-photo__credit'" ) || false !== strpos( $mod_src, 'sa-photo__credit' ), 'the quiet credit helper stays' );
sa_t_assert( false !== strpos( $mod_src, 'function sa_photo_overlay_label' ), 'the overlay label is a shared helper, not page-specific markup' );

/* 4b — photos arrive as JPEG, WebP or PNG; the *lookup* must never be narrowed to
 *      one format. (Writing a WebP attachment is a different code path and is fine.) */
sa_t_assert(
	false !== strpos( $mod_src, "'post_mime_type' => array( 'image/jpeg', 'image/webp', 'image/png' )" ),
	'the photo lookup accepts the JPEG files the import route writes, plus WebP and PNG'
);


/* 5 — the template renders nothing when no licensed photo exists. */
/* v2.22.145 — the county gallery is its own section; the claim follows the code. */
$src = (string) file_get_contents( dirname( __DIR__ ) . '/template-parts/county/section-photos.php' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/single.php' );
sa_t_assert( false === strpos( $src, 'sa_photo_spotlight' ), 'v2.22.116: the county page no longer calls the «مکان در یک نگاه» box' );
sa_t_assert( false !== strpos( $src, 'sa_photo_strip' ), 'the county page calls the county photo gallery' );
sa_t_assert( false === strpos( $mod_src, 'function sa_photo_spotlight' ), 'v2.22.116: the spotlight renderer is deleted, not merely unused' );
$mod = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert( false !== strpos( $mod, 'if ( ! $ids ) {' ), 'the gallery renders nothing when no photo qualifies — it never pads an empty slot' );
sa_t_assert( false !== strpos( $mod, 'function sa_photo_county_ids' ), 'one helper decides which county photos are shown, and in what order' );
sa_t_assert( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' ), "'/photos/import'" ), 'the guarded import route is registered' );

/* 6 — the inventory route exists so the owner can audit authorship, not just see photos. */
$php_all = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' );
sa_t_assert( false !== strpos( $php_all, 'sa_agent_rest_photos_inventory' ), 'the read-only photo inventory is registered' );
sa_t_assert( false !== strpos( $php_all, "'publishable'   => sa_photo_is_publishable( \$id )" ) || false !== strpos( $php_all, "'publishable'" ), 'the inventory reports whether each photo may be published' );

/* 7 — the identity of a photo is the place, not the file URL: a re-send with a corrected
 *     URL updates the same attachment instead of adding a duplicate (job 120 did exactly
 *     that on the live site: two copies of the same park). */
sa_t_assert(
	false !== strpos( $mod, 'absint( $city_id ) . ' ) && false !== strpos( $mod, "'|' . \$place" ),
	'the photo key identifies the place, so a corrected URL updates instead of duplicating'
);
sa_t_assert( false !== strpos( $mod, 'sa_photo_replace_file' ), 'a re-send with a new URL swaps the file in place' );
sa_t_assert( false !== strpos( $mod, 'sa_photo_write_meta' ), 'provenance is written by one shared helper' );

/* 8 — duplicates are cleaned only through a preview-first, owner-flagged route. */
$all_php = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' )
	. (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' );
sa_t_assert( false !== strpos( $all_php, 'sa_agent_rest_photos_prune' ), 'the duplicate-cleanup route is registered' );
sa_t_assert( false !== strpos( $all_php, "'dry_run'" ), 'the cleanup can be previewed before it touches anything' );
sa_t_assert( false !== strpos( $all_php, 'wp_trash_post' ) && false === strpos( $all_php, 'wp_delete_attachment' ), 'cleanup trashes (recoverable), it never deletes' );

/* 9 — the photo block is a template part, not a page-specific snippet: every county
 *     (past and future) gets the place name on the photo from the same helper. */
sa_t_assert( false !== strpos( $mod, "const SA_PHOTO_META_AREA" ), 'the area/address meta key is shared' );
sa_t_assert( false !== strpos( $mod, 'class="sa-photo__caption"' ), 'the overlay caption markup is built in the helper' );
$mock_src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert(
	false !== strpos( $mock_src, 'sa_photo_credit_line( $id, \'sa-strip__credit\' )' ),
	'the three-tile strip reuses the quiet credit helper instead of printing a licence name'
);
sa_t_assert(
	false === strpos( $mock_src, "<?php echo esc_html( \$credit['license_label'] ); ?>" ),
	'no template prints the bare licence label as visible text'
);
/* 10 — v2.22.116 (owner order, 18 Nov 2026): «مکان در یک نگاه» is gone; the county
 *     gallery is the only photo block and every county photo is a 4:3 WebP < 100 KB. */
sa_t_assert( false !== strpos( $mod, 'function sa_photo_slider' ), 'one shared slider shell builds the gallery' );
sa_t_assert( false !== strpos( $mod, 'sa_photo_county_ids( $city_id, 24 )' ), 'the county gallery can carry up to 24 county photos' );
sa_t_assert( false !== strpos( $mod, 'id="county-gallery"' ), 'the gallery has its own anchor for the table-of-contents tail link' );
sa_t_assert( false !== strpos( $mod, 'sa-strip__badge' ), 'the gallery header shows how many photos it carries' );
sa_t_assert( false !== strpos( $mod, 'data-sa-zoom' ), 'each frame opens the lightbox' );
sa_t_assert( false !== strpos( $mod, '_sa_photo_hide' ), 'the owner can hide a photo from the page without deleting the file' );
sa_t_assert( false !== strpos( $mod, '_sa_photo_lead' ) && false !== strpos( $mod, '_sa_photo_order' ), 'the owner picks the first view and the order by hand' );
$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/gallery.css' );
sa_t_assert( false !== strpos( $css, 'scroll-snap-type: x mandatory' ), 'swiping left/right is native scroll-snap, so it works even without JS' );
sa_t_assert( false !== strpos( $css, 'touch-action: pan-x' ), 'the track leaves the horizontal swipe to the browser' );
sa_t_assert( false === strpos( $css, '.sa-strip__grid' ), 'the old fixed three-column grid is gone from the gallery strip' );
sa_t_assert( false === strpos( $css, '.sa-spotlight' ), 'v2.22.116: the spotlight styles are deleted with the block' );
sa_t_assert( false !== strpos( $css, 'aspect-ratio: 4 / 3' ), 'every gallery frame keeps a 4:3 box' );
sa_t_assert( substr_count( $css, '.sa-strip .sa-slider__slide' ) >= 1, 'the gallery slide width is set per breakpoint' );
$gal_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/gallery.js' );
sa_t_assert( false !== strpos( $gal_js, 'data-sa-slider' ), 'the slider controls are wired in the gallery script' );
sa_t_assert( false !== strpos( $gal_js, 'ArrowLeft' ) && false !== strpos( $gal_js, 'ArrowRight' ), 'the arrow keys move between views' );
sa_t_assert( false !== strpos( $gal_js, 'saCountyOpenGallery' ), 'a gallery frame opens the lightbox at that slide' );
sa_t_assert( false !== strpos( $gal_js, 'sa-slider--fits' ), 'arrows and dots disappear when every frame already fits' );

/* 10b — every county photo is stored as a 4:3 WebP under 100 KB (owner order,
 *      18 Nov 2026), and nothing else on the site is touched by that promise. */
sa_t_assert( 99000 === SA_PHOTO_MAX_WEBP_BYTES, 'the byte ceiling is the 100 KB budget with headroom' );
sa_t_assert( 800 === SA_PHOTO_TARGET_W && 600 === SA_PHOTO_TARGET_H, 'the target frame is 4:3 (800×600)' );
$crop = sa_photo_crop_box( 1600, 1200 );
sa_t_assert( 1600 === $crop['crop_w'] && 1200 === $crop['crop_h'], 'an already 4:3 source is not cropped' );
$crop2 = sa_photo_crop_box( 1200, 1600 );
sa_t_assert( $crop2['crop_w'] === 1200 && $crop2['crop_h'] < 1600, 'a portrait source is cropped to 4:3 from the top' );
$crop3 = sa_photo_crop_box( 300, 200 );
sa_t_assert( $crop3['dst_w'] <= 300, 'a small source is never upscaled' );
sa_t_assert( false !== strpos( $mod, 'function sa_photo_optimize_attachment' ), 'the optimizer works on one attachment at a time' );
sa_t_assert( false !== strpos( $mod, 'function sa_photo_optimize_bytes_to_webp' ), 'the re-encode helper is shared by import, upload and the panel' );
sa_t_assert( false !== strpos( $mod, "'image/webp'" ) && false !== strpos( $mod, 'sa_photo_store_webp_meta' ), 'the result is stored as a single WebP (no leftover JPEG plus sizes)' );
sa_t_assert( false !== strpos( $mod, '_sa_photo_bytes' ), 'the stored byte count travels with the photo' );
sa_t_assert( false !== strpos( $mod, 'post_content' ), 'old files still referenced by a county body are never deleted' );

/* 11 — the owner edits the photos himself from the county edit screen: add, replace,
 *     remove, re-order. Removing a photo from a county never deletes the file. */
$admin = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-photos-admin.php' );
sa_t_assert( false !== strpos( $admin, "'sa-county-photos'" ), 'the county edit screen gets a photo panel' );
sa_t_assert( false !== strpos( $admin, 'wp_enqueue_media' ), 'adding a photo goes through the WordPress media library' );
foreach ( array( 'sa_photo_attach', 'sa_photo_swap', 'sa_photo_detach', 'sa_photo_save', 'sa_photo_trash', 'sa_photo_optimize' ) as $ajax ) {
	sa_t_assert( false !== strpos( $admin, "wp_ajax_{$ajax}" ), "the panel handles {$ajax}" );
}
sa_t_assert( false !== strpos( $admin, "check_ajax_referer( 'sa_photo_admin'" ), 'every panel call is nonce-checked' );
sa_t_assert( false !== strpos( $admin, "current_user_can( 'edit_post'" ), 'the panel is closed to anyone who cannot edit that county' );
sa_t_assert( false === strpos( $admin, 'wp_delete_attachment' ), 'the panel never deletes a media file' );
sa_t_assert( false !== strpos( $admin, 'wp_trash_post' ), 'the panel trashes recoverably instead of deleting' );
sa_t_assert( false !== strpos( $admin, 'save_post_city' ), 'the same rows are saved with the post itself' );
sa_t_assert( false !== strpos( $admin, 'sa_photo_optimize_attachment' ), 'the panel re-encodes added photos to WebP' );
sa_t_assert( false !== strpos( $admin, 'sa_photo_admin_is_shared' ), 'a photo used as a featured image elsewhere is copied, never touched' );
sa_t_assert( false !== strpos( $admin, 'sa_photo_normalize_license' ), 'the licence picker stores the same codes the render path reads' );
$admin_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/county-photos-admin.js' );
sa_t_assert( false === strpos( $admin_js, 'new FormData( box )' ) && false === strpos( $admin_js, 'new FormData(box)' ), 'the panel is a div, not a form: fields are collected one by one' );
sa_t_assert( false !== strpos( $admin_js, 'sa_photo_optimize' ), 'the one-click WebP button calls the optimizer' );
sa_t_assert( false !== strpos( $admin_js, 'sa_photo_trash' ), 'the trash button is wired' );
sa_t_assert( false !== strpos( $admin_js, 'data-dir' ), 'the row buttons move a photo up or down' );
$func_src = (string) file_get_contents( dirname( __DIR__ ) . '/functions.php' );
sa_t_assert( false !== strpos( $func_src, "'inc/county-photos-admin.php'" ), 'the photo panel file is loaded by the theme' );

/* 11b — v2.22.116: the same two operations exist on the agent bridge so a batch can
 *      optimize or manage a whole county without the owner opening wp-admin. */
sa_t_assert( false !== strpos( $php_all, 'sa_agent_rest_photos_optimize' ), 'the batch optimizer route exists' );
sa_t_assert( false !== strpos( $php_all, 'sa_agent_rest_photos_manage' ), 'the batch manage route exists' );
$agent_ops = (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' );
sa_t_assert( false !== strpos( $agent_ops, "'/photos/optimize'" ) && false !== strpos( $agent_ops, "'/photos/manage'" ), 'both routes are registered on the agent namespace' );
sa_t_assert( false !== strpos( $php_all, 'owner_approved' ), 'both routes demand an explicit owner approval' );
/* 11c — «مکان در یک نگاه» از قالب حذف شده و دیگر هیچ فایلی آن را صدا نمی‌زند. */
foreach ( array( 'template-parts/entity/single.php', 'template-parts/county/section-photos.php', 'inc/county-media.php', 'assets/js/gallery.js', 'assets/css/gallery.css' ) as $sa_rel ) {
	$sa_file = (string) file_get_contents( dirname( __DIR__ ) . '/' . $sa_rel );
	sa_t_assert( false === strpos( $sa_file, 'spotlight' ), $sa_rel . ' carries no leftover spotlight markup or style (v2.22.116)' );
}

/* 12 — a file the owner sends himself (his own aerial shot, no Wikimedia host) is
 *     accepted only with real bytes, an allowed licence code and a named author. */
sa_t_assert( false !== strpos( $php_all, 'sa_agent_rest_photo_upload' ), 'the owner-upload route exists' );
sa_t_assert( false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' ), "'/photos/upload'" ), 'the owner-upload route is registered' );
sa_t_assert( '' !== sa_photo_license_label( 'sa-archive' ), 'owner-sent files have their own licence code' );
sa_t_assert( '' !== sa_photo_license_label( 'cc-by-4' ), 'the Commons licences still pass' );
sa_t_assert( true === sa_photo_bytes_match_mime( "\xFF\xD8\xFF\xE0junk", 'image/jpeg' ), 'JPEG magic bytes pass' );
sa_t_assert( true === sa_photo_bytes_match_mime( 'RIFFxxxxWEBP', 'image/webp' ), 'WebP magic bytes pass' );
sa_t_assert( false === sa_photo_bytes_match_mime( 'RIFFxxxxWEBP', 'image/jpeg' ), 'a WebP file cannot pretend to be a JPEG' );
sa_t_assert( false === sa_photo_bytes_match_mime( 'GIF89a', 'image/jpeg' ), 'an unlisted format is refused' );
sa_t_assert( array( 'image/jpeg', 'image/webp', 'image/png' ) === sa_photo_admin_mimes(), 'the panel accepts the same three formats the render path reads' );

/* 13 — هر فراخوانی تابع مشترک باید امضای همان تابع را داشته باشد (۲.۲۲.۱۱۸).
 *      در ۲.۲۲.۱۱۶ فراخوانی sa_gallery_normalise_orientation() با دو آرگومان نوشته
 *      شد، در حالی که امضایش سه‌آرگومانی است؛ نتیجه یک خطای مرگبار PHP 500 روی
 *      *هر* واردات JPEG بود و کار ۱۳۵ چهار عکس مشهد را همین‌طور از دست داد، در
 *      حالی که همهٔ تست‌های متنی سبز بودند. این تست ارگومان‌ها را می‌شمارد. */
$sig_src  = (string) file_get_contents( dirname( __DIR__ ) . '/inc/gallery.php' );
$sig_pat  = '/function\s+sa_gallery_normalise_orientation\s*\(\s*([^)]*)\)/s';
sa_t_assert( 1 === preg_match( $sig_pat, $sig_src, $sig_m ), 'the orientation helper still exists' );
$sig_arity = count( array_filter( array_map( 'trim', explode( ',', $sig_m[1] ) ), 'strlen' ) );
sa_t_assert( 3 === $sig_arity, 'the orientation helper keeps its three arguments (image, path, mime)' );
$call_src = $mod_src . (string) file_get_contents( dirname( __DIR__ ) . '/inc/gallery.php' );
preg_match_all( '/sa_gallery_normalise_orientation\s*\(\s*([^)]*)\)/', $call_src, $call_m );
sa_t_assert( ! empty( $call_m[1] ), 'the orientation helper is actually used' );
foreach ( $call_m[1] as $arg_list ) {
	$argc = count( array_filter( array_map( 'trim', explode( ',', $arg_list ) ), 'strlen' ) );
	sa_t_assert( $sig_arity === $argc, 'every call passes all ' . $sig_arity . ' arguments (found ' . $argc . ': ' . trim( $arg_list ) . ')' );
}

/* Landing CSS stays scoped and is loaded only where it is needed. */
$home_src = (string) file_get_contents( dirname( __DIR__ ) . '/template-home.php' );
$home_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/landing.css' );
sa_t_assert( false !== strpos( $home_src, 'role="search"' ), 'landing page offers search' );
sa_t_assert( false !== strpos( $home_css, '.sa-landing-wrap{' ), 'landing wrap uses a dedicated class' );
sa_t_assert( false === strpos( $home_css, "\n.sa-wrap{" ), 'landing styles do not leak to the theme wrap' );

echo "ALL TESTS PASSED\n";
