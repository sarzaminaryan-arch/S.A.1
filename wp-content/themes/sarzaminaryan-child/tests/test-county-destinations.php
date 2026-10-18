<?php
/** Synthetic rendering regression; no live WordPress/database connection. */
ob_start();
require __DIR__ . '/test-county-profile.php';
ob_end_clean();
function get_the_ID() { return isset( $GLOBALS['sa_render_id'] ) ? $GLOBALS['sa_render_id'] : 20; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $value ) { return esc_html( $value ); }
function esc_url( $url ) { return preg_match( '~^https?://~', $url ) ? esc_attr( $url ) : ''; }
function sa_toc_register( $title, $id, $priority ) { $GLOBALS['sa_dest_toc'][ $id ] = $title; }
function sa_test_destination_render() {
	ob_start();
	include dirname( __DIR__ ) . '/template-parts/entity/county-destinations.php';
	return ob_get_clean();
}
$GLOBALS['t']['meta'][20] = array(
	'province_id' => '10',
	'sa_cty_poi_nature' => "دریاچه گهر | ۳۰ کیلومتر | یادداشت موجود\nبدون صفحه | فاصلهٔ موجود | متن بدون لینک",
	'sa_cty_poi_offbeat' => 'دریاچه گهر | ۳۰ کیلومتر | یادداشت موجود',
	'sa_cty_poi_heritage' => "بنای نمونه | ۱۰ کیلومتر | توضیح تاریخی موجود\n<script>alert(1)</script> | | <img src=x onerror=alert(1)>",
);
$GLOBALS['t']['children'][20]['attraction'] = array();
for ( $sa_i = 0; $sa_i < 11; ++$sa_i ) {
	$sa_post = sa_t_post( 100 + $sa_i, 'attraction', 'synthetic-' . $sa_i, 0 === $sa_i ? 'دریاچه گهر' : 'جاذبهٔ ساختگی با نام بلند ' . $sa_i );
	$GLOBALS['t']['children'][20]['attraction'][] = $sa_post;
}
$GLOBALS['t']['children'][20]['attraction'][] = sa_t_post( 200, 'attraction', 'draft-poi', 'DRAFT_NEVER_SHOW', 'draft' );
$GLOBALS['t']['meta'][100] = array( 'sa_english_name' => 'Gahar Lake' );
$GLOBALS['t']['posts'][23]->post_status = 'draft';
$sa_html = sa_test_destination_render();
sa_t_assert( 1 === substr_count( $sa_html, '<h2 id="sa-destinations-title">' ), 'one merged attractions heading' );
sa_t_assert( false === strpos( $sa_html, 'آثار تاریخی و زیارتی' ) && false === strpos( $sa_html, 'بکر و کمتر' ) && false === strpos( $sa_html, '>نوع<' ), 'no type labels or second heritage heading' );
sa_t_assert( 1 === substr_count( $sa_html, '<strong>دریاچه گهر</strong>' ), 'deduplicate legacy and related attraction' );
sa_t_assert( 1 === substr_count( $sa_html, 'یادداشت موجود' ), 'deduplicate details without losing them' );
sa_t_assert( false !== strpos( $sa_html, '۳۰ کیلومتر' ) && false !== strpos( $sa_html, 'توضیح تاریخی موجود' ), 'legacy distance and historical notes retained' );
sa_t_assert( 12 === substr_count( $sa_html, 'class="sa-destination-card"' ), 'eleven linked attractions plus one published sibling; no eight-item display cap' );
sa_t_assert( false === strpos( $sa_html, 'DRAFT_NEVER_SHOW' ) && false === strpos( $sa_html, 'city/borujerd/' ), 'draft destination/sibling absent' );
sa_t_assert( false === strpos( $sa_html, 'href="https://example.test/city/dorud/' ), 'current county is not its own sibling' );
sa_t_assert( false !== strpos( $sa_html, '<strong>بدون صفحه</strong> — فاصلهٔ موجود — متن بدون لینک' ), 'page-less places retained as plain text, no invented URLs' );
sa_t_assert( false === strpos( $sa_html, '<script>' ) && false === strpos( $sa_html, '<img' ), 'all user text escaped' );
sa_t_assert( false !== strpos( $sa_html, '&lt;script&gt;' ), 'escaped text retained' );
sa_t_assert( false !== strpos( $sa_html, 'id="county-visit"' ) && false !== strpos( $sa_html, 'id="county-attractions"' ), 'legacy anchors remain valid' );
sa_t_assert( isset( $GLOBALS['sa_dest_toc']['county-attractions'], $GLOBALS['sa_dest_toc']['county-siblings'] ), 'TOC links registered' );
// Repeat rendering cannot mutate data or change the result.
$sa_before = serialize( $GLOBALS['t'] );
sa_t_assert( $sa_html === sa_test_destination_render() && $sa_before === serialize( $GLOBALS['t'] ), 'read-only repeatable rendering' );
if ( isset( $argv[1] ) && '--fixture' === $argv[1] ) {
	echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body><main class="profile-page"><div class="container sa-entity__layout">' . $sa_html . '</div></main></body></html>';
	return;
}
$GLOBALS['t']['children'][20]['attraction'] = array();
$GLOBALS['t']['meta'][20] = array();
$GLOBALS['t']['posts'][21]->post_status = 'draft';
sa_t_assert( '' === trim( sa_test_destination_render() ), 'empty data produces no empty sections' );
$GLOBALS['sa_render_id'] = 10;
sa_t_assert( '' === trim( sa_test_destination_render() ), 'not rendered for non-county types' );
echo "ALL DESTINATION TESTS PASSED\n";
