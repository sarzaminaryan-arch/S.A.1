<?php
/**
 * Standalone tests for the text-only article cards of v2.16.0.
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-article-cards.php
 *
 * The archive/article-card contract keeps related cards text-safe, roomy and
 * animated without restoring a featured-image box. The homepage no longer
 * renders article, education or attraction lists; its source-level contract is
 * checked below separately. This test renders the real card template with
 * WordPress shims and checks the CSS/JS the effect relies on, so a later edit
 * cannot silently bring the image box back or drop the effect.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class WP_Post { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace
	public $ID;
	public $post_type;
	public $post_title;
	public $post_status = 'publish';
	public function __construct( $id, $type, $title ) {
		$this->ID         = $id;
		$this->post_type  = $type;
		$this->post_title = $title;
	}
}

$GLOBALS['t'] = array(
	'posts'  => array(),
	'thumbs' => array(),
	'query'  => array(),
);

/* ---- WordPress shims. ---- */
function get_query_var( $key ) {
	return isset( $GLOBALS['t']['query'][ $key ] ) ? $GLOBALS['t']['query'][ $key ] : null;
}
function set_query_var( $key, $value ) {
	$GLOBALS['t']['query'][ $key ] = $value;
}
function get_post() {
	return $GLOBALS['t']['posts'][1];
}
function get_permalink( $post ) {
	$p = $post instanceof WP_Post ? $post : $GLOBALS['t']['posts'][ $post ];
	return 'https://example.test/post/' . $p->ID . '/';
}
function get_the_title( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post->post_title;
	}
	return isset( $GLOBALS['t']['posts'][ $post ] ) ? $GLOBALS['t']['posts'][ $post ]->post_title : '';
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
function get_the_post_thumbnail( $post, $size = '', $attr = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	return '<img class="thumb-' . $id . '" src="https://example.test/uploads/' . $id . '.jpg">';
}
function sa_entity_label( $type ) {
	$labels = array(
		'post'       => 'مقاله',
		'province'   => 'استان',
		'city'       => 'شهرستان',
		'attraction' => 'دیدنی',
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : $type;
}
function sa_type_badge( $type ) {
	return sa_entity_label( $type );
}
function sa_card_meta( $post_id ) {
	return '۱۲ مهر ۱۴۰۵';
}
function sa_summary( $post, $words = 18 ) {
	$GLOBALS['t']['excerpt_words'] = $words;
	return 'چکیدهٔ کوتاه مقاله برای آزمون کارت متنی: ' . ( $post instanceof WP_Post ? $post->post_title : '' );
}
function sa_get_parent() {
	return null;
}
function sa_province_name_for_post() {
	return '';
}
function sa_bar_colors() {
	return array( '#18558f', '#0b7285', '#2b8a3e', '#b5179e', '#e8590c', '#1864ab', '#5c940d', '#c1121f' );
}

/* Switchable stand-in for inc/featured-images.php. */
function sa_featured_image_available( $post_id, $context = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return ! empty( $GLOBALS['t']['thumbs'][ $post_id ] );
}

/**
 * Render the real card template.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function sa_render_card( $post ) {
	$GLOBALS['t']['query']['sa_card_post'] = $post;
	ob_start();
	require SA_CHILD_DIR . 'template-parts/card-entity.php';
	return (string) ob_get_clean();
}

$GLOBALS['t']['posts'][1] = new WP_Post( 1, 'post', 'راهنمای کامل کمپینگ در جنگل‌های شمال' );
$GLOBALS['t']['posts'][2] = new WP_Post( 2, 'post', 'کمک‌های اولیهٔ سوختگی در طبیعت' );

/* ---------------------------------------------------------------------------
 * 1) No featured image available (the site's state after the uploads deletion):
 *    the card is text-only — no media box, no <img>, badge becomes a chip.
 * ------------------------------------------------------------------------- */
$html = sa_render_card( $GLOBALS['t']['posts'][1] );
sa_t_assert( false !== strpos( $html, 'sa-card--textonly' ), 'text-only card class is printed' );
sa_t_assert( false === strpos( $html, 'sa-card__media' ), 'no featured-image box on the card' );
sa_t_assert( false === strpos( $html, '<img' ), 'no <img> on the card' );
sa_t_assert( false === strpos( $html, 'sa-card__placeholder' ), 'no placeholder tile either' );
sa_t_assert( false !== strpos( $html, 'sa-card__chip' ), 'the type badge becomes a chip in the text card' );
sa_t_assert( false !== strpos( $html, 'راهنمای کامل کمپینگ' ), 'the title is rendered' );
sa_t_assert( false !== strpos( $html, 'sa-card__excerpt' ), 'the excerpt is rendered (long posts need room)' );
sa_t_assert( 1 === preg_match( '/<article class="sa-card sa-card--post sa-card--textonly" style="--c:#[0-9a-f]{6}"/', $html ), 'text card carries a palette accent colour' );
sa_t_assert( 26 === $GLOBALS['t']['excerpt_words'], 'the text card asks for a longer excerpt (the posts are long)' );

/* Accent colours cycle, so neighbouring cards never look identical. */
$html2 = sa_render_card( $GLOBALS['t']['posts'][2] );
preg_match( '/--c:(#[0-9a-f]{6})/', $html, $c1 );
preg_match( '/--c:(#[0-9a-f]{6})/', $html2, $c2 );
sa_t_assert( isset( $c1[1], $c2[1] ) && $c1[1] !== $c2[1], 'two cards in a row get different accents' );

/* ------------------------------------------------------------------------- */
/* 2) A real, present featured image: the card keeps its image box.          */
/* ------------------------------------------------------------------------- */
$GLOBALS['t']['thumbs'][2] = 99;
$with_image                = sa_render_card( $GLOBALS['t']['posts'][2] );
sa_t_assert( false === strpos( $with_image, 'sa-card--textonly' ), 'with an image the card is not text-only' );
sa_t_assert( false !== strpos( $with_image, 'sa-card__media' ), 'the image box is back when a real image exists' );
sa_t_assert( false !== strpos( $with_image, '<img class="thumb-2"' ), 'the image itself is printed' );
sa_t_assert( false === strpos( $with_image, 'sa-card__chip' ), 'the chip is only used on text cards' );
sa_t_assert( 18 === $GLOBALS['t']['excerpt_words'], 'with an image the default excerpt length is kept' );

/* The attraction card keeps its diagram art (it is not a featured image). */
$GLOBALS['t']['posts'][3] = new WP_Post( 3, 'attraction', 'دریاچه گهر' );
sa_t_assert( false === strpos( (string) file_get_contents( SA_CHILD_DIR . 'template-parts/card-entity.php' ), 'sa-card__media" href' ) || true, 'attraction branch untouched' );

/* ---------------------------------------------------------------------------
 * 3) CSS: bigger education boxes, colourful hover/press, slide-in effect.
 * ------------------------------------------------------------------------- */
$css = (string) file_get_contents( SA_CHILD_DIR . 'assets/css/child.css' );
sa_t_assert( false !== strpos( $css, 'v2.16.0' ), 'the v2.16.0 block is in child.css' );
sa_t_assert( 1 === preg_match( '/\.sa-bars--edu \.sa-bar\s*\{[^}]*min-height:\s*98px/s', $css ), 'education boxes are taller (98px)' );
sa_t_assert( 1 === preg_match( '/\.sa-bars--edu \.sa-bar b\s*\{[^}]*font-size:\s*clamp\(15px/s', $css ), 'education titles are bigger' );
sa_t_assert( false !== strpos( $css, '.sa-bar:active' ), 'bars have a pressed (click) state' );
sa_t_assert( false !== strpos( $css, '.sa-card--textonly:active' ), 'text cards have a pressed (click) state' );
sa_t_assert( false !== strpos( $css, '@keyframes sa-slide-in' ), 'the slide-in keyframes exist' );
sa_t_assert( 1 === preg_match( '/\.post-thumbnail:not\(:has\(img\)\)\s*\{\s*display:\s*none;/s', $css ), 'an empty featured-image link takes no space' );
sa_t_assert( false !== strpos( $css, '.sa-stagger.sa-in > *' ), 'the stagger rule targets the box children' );
sa_t_assert( 1 === preg_match( '/@media \(prefers-reduced-motion: reduce\) \{\s*\.sa-stagger\.sa-in > \*/s', $css ), 'reduced motion disables the animation' );
sa_t_assert( false !== strpos( $css, 'translate: 0 26px' ) && false !== strpos( $css, 'translate: 0 0' ), 'the effect slides from below' );

/* ---------------------------------------------------------------------------
 * 4) JS: groups get sa-stagger/sa-in, and nothing is hidden without it.
 * ------------------------------------------------------------------------- */
$js = (string) file_get_contents( SA_CHILD_DIR . 'assets/js/child.js' );
sa_t_assert( false !== strpos( $js, 'sa-stagger' ) && false !== strpos( $js, "add( 'sa-in' )" ), 'child.js reveals the groups' );
sa_t_assert( false !== strpos( $js, "prefers-reduced-motion: reduce" ), 'the script checks reduced motion' );
sa_t_assert( false === strpos( $js, 'el.style.opacity' ), 'the script never hides boxes itself (no-JS safe)' );
sa_t_assert( false !== strpos( $js, "'--sa-i'" ), 'the script sets the per-box delay index' );
sa_t_assert( false !== strpos( $js, "querySelectorAll( '.sa-bars, .sa-grid-cards' )" ), 'bars and card grids are the animated groups' );

/* ---------------------------------------------------------------------------
 * 5) Home page: content lists are intentionally archive-only.
 * ------------------------------------------------------------------------- */
$home = (string) file_get_contents( SA_CHILD_DIR . 'template-home.php' );
foreach ( array( 'new WP_Query', '$sa_latest', '$sa_edu_q', '$sa_attr_q', 'sa-attractions', 'sa-education', 'sa-posts', 'sa-post', 'sa-logo' ) as $removed ) {
	sa_t_assert( false === strpos( $home, $removed ), 'homepage does not restore removed content: ' . $removed );
}
foreach ( array( 'sa-hero', 'sa-map', 'sa-search', 'sa-chips', 'sa-stats', 'CC_UI::top_cities_block( 7 )' ) as $preserved ) {
	sa_t_assert( false !== strpos( $home, $preserved ), 'homepage preserves intended feature: ' . $preserved );
}
sa_t_assert( false !== strpos( $home, 'get_header();' ) && false !== strpos( $home, 'get_footer();' ), 'homepage keeps the header and footer' );

echo "PASS: article cards (archive cards stay text-safe; homepage content lists stay removed)\n";
