<?php
/**
 * Standalone tests for licensed place photos (inc/county-media.php, v2.22.98).
 *
 * The owner's rule: a photo may only appear when its licence allows reuse
 * *and* the photographer is named. These tests pin the policy, not the UI:
 * which licences pass, which URLs the site may fetch, and that the credit
 * line always carries the author.
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
$credit = sa_photo_credit( 10 );
sa_t_assert( 'حسین معمری' === $credit['author'], 'credit carries the author' );
sa_t_assert( 'CC BY 4.0' === $credit['license_label'], 'credit carries the licence label' );
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

/* 4c — the place name is written on the photo (clean, transparent) for every county. */
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_LICENSE_URL ] = 'https://creativecommons.org/licenses/by/4.0/';
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_PLACE ] = 'باغ فین (کاشان)';
$GLOBALS['t']['meta'][10][ SA_PHOTO_META_AREA ]  = 'کاشان، خیابان امیرکبیر';
$overlay = sa_photo_frame( 10, 'sa-square' );
sa_t_assert( false !== strpos( $overlay, 'class="sa-photo__caption"' ), 'the photo carries an overlay caption' );
sa_t_assert( false !== strpos( $overlay, '>باغ فین<' ), 'the overlay names the place, without the parenthetical' );
sa_t_assert( false !== strpos( $overlay, '>کاشان، خیابان امیرکبیر<' ), 'the overlay can carry the address line' );
sa_t_assert( '' === sa_photo_overlay_label( 11 ), 'a photo without a place gets no overlay text' );

/* 4d — the licence disclaimer paragraph is gone from the block (owner order). */
$mod_src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert( false === strpos( $mod_src, 'sa-spotlight__notice' ), 'the licence disclaimer paragraph is removed' );
sa_t_assert( false === strpos( $mod_src, 'بدون اجازهٔ کتبی' ), 'the «without written permission» sentence is removed' );
sa_t_assert( false !== strpos( $mod_src, "'sa-photo__credit'" ) || false !== strpos( $mod_src, 'sa-photo__credit' ), 'the quiet credit helper stays' );
sa_t_assert( false !== strpos( $mod_src, 'function sa_photo_overlay_label' ), 'the overlay label is a shared helper, not page-specific markup' );

/* 4b — imported photos are JPEG; the lookup must not filter them out. */
sa_t_assert(
	false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' ), "'post_mime_type' => 'image/webp'" ),
	'the photo lookup accepts the JPEG files the import route writes'
);

/* 5 — the template renders nothing when no licensed photo exists. */
$src = (string) file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/single.php' );
sa_t_assert( false !== strpos( $src, 'sa_photo_spotlight' ), 'the county page calls the spotlight block' );
sa_t_assert( false !== strpos( $src, 'sa_photo_strip' ), 'the county page calls the three-tile strip' );
$mod = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert( false !== strpos( $mod, 'if ( ! $lead ) {' ), 'the spotlight returns early when no photo qualifies' );
sa_t_assert( false !== strpos( $mod, 'if ( count( $ids ) < 3 ) {' ), 'the strip needs three qualifying photos, not one padded slot' );
sa_t_assert( false !== strpos( $mod, "'/photos/import'" ) || false !== strpos( (string) file_get_contents( dirname( __DIR__ ) . '/inc/agent-ops.php' ), "'/photos/import'" ), 'the guarded import route is registered' );

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
sa_t_assert( false !== strpos( $mod, "'sa-photo__caption'" ), 'the overlay caption markup is built in the helper' );
$mock_src = (string) file_get_contents( dirname( __DIR__ ) . '/inc/county-media.php' );
sa_t_assert(
	false !== strpos( $mock_src, "sa_photo_credit_line( $id, 'sa-strip__credit' )" ),
	'the three-tile strip reuses the quiet credit helper instead of printing a licence name'
);
sa_t_assert(
	false === strpos( $mock_src, "<?php echo esc_html( \$credit['license_label'] ); ?>" ),
	'no template prints the bare licence label as visible text'
);
/* the batch format carries overlay/area so a future county can ship both. */
$home_src = (string) file_get_contents( dirname( __DIR__ ) . '/template-home.php' );
sa_t_assert( false !== strpos( $home_src, "get_theme_mod( 'sa_home_slogan'" ), 'home v3 still reads the owner customizer texts' );
sa_t_assert( false === strpos( $home_src, 'sa-sparks' ), 'home v3 drops the animated spark field' );
sa_t_assert( false === strpos( $home_src, 'sa-dash-cta' ), 'home v3 drops the neon dashboard button' );

/* 10 — counters must never be zeroed by the enhancement script: the server's number
 *      stays in the markup until the animation itself finishes (job 132 showed «۰»). */
sa_t_assert( false === strpos( $home_src, 'fa(0)' ), 'home v3 never pre-zeroes the counters' );
sa_t_assert( false === strpos( $home_src, 'requestAnimationFrame' ), 'no count-up animation can show 0 on the first frame' );
sa_t_assert( false !== strpos( $home_src, 'wp_count_posts' ), 'the counters are counted on the server' );
sa_t_assert( false !== strpos( $home_src, "'—'" ), 'a genuinely empty count prints a dash, not 0' );

/* 11 — markup sanity in the home map: an unclosed <g ...> start tag would swallow
 *      every following dot (found in the first v3 render, fixed in 2.22.108). */
sa_t_assert( false === strpos( $home_src, '))\"<title>' ), 'the map dot <g> tag is closed before <title>' );
sa_t_assert( 1 === substr_count( $home_src, 'class=\"sa-dot--soon\"' ), 'the map renders one placeholder group per unpublished province' );

/* 12 — the home stylesheet must not restyle the header/footer: `.sa-wrap` is a
 *      parent-theme class too, so the v3 rules stay scoped to `.sa-home`. */
sa_t_assert( false !== strpos( $home_src, '.sa-home .sa-wrap{' ), 'home wrap styles are scoped to the homepage' );
sa_t_assert( false === strpos( $home_src, "\n.sa-wrap{" ), 'no unscoped .sa-wrap rule leaks into the header/footer' );

echo "ALL TESTS PASSED\n";
