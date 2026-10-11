<?php
/**
 * Standalone regression tests for the Level 7 publish gate policy (v2.12.7).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-publish-gate.php
 *
 * Policy under test (owner decision 2026-10-11): the owner's approval is the only
 * publish condition. Level 7 findings are reminders by default ('soft'); the
 * legacy 'hard' mode must be opted into explicitly and never demotes a page that
 * is already published.
 *
 * The small WordPress shims below exercise the real child-theme code without a
 * WordPress install or database.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'SA_CHILD_DIR' ) ) {
	define( 'SA_CHILD_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'SA_CHILD_VERSION' ) ) {
	define( 'SA_CHILD_VERSION', '2.12.7-test' );
}
if ( ! defined( 'SA_ENABLE_ACCOMMODATION' ) ) {
	define( 'SA_ENABLE_ACCOMMODATION', false );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['sa_test_filters']    = array();
$GLOBALS['sa_test_theme_mods'] = array();
$GLOBALS['sa_test_meta']       = array();
$GLOBALS['sa_test_posts']      = array(
	201 => (object) array(
		'ID'           => 201,
		'post_type'    => 'attraction',
		'post_status'  => 'draft',
		'post_title'   => 'دیدنی آزمایشی',
		'post_content' => '<p>متن بدون لینک داخلی و بدون منبع.</p>',
	),
	202 => (object) array(
		'ID'           => 202,
		'post_type'    => 'attraction',
		'post_status'  => 'publish',
		'post_title'   => 'دیدنی منتشرشده',
		'post_content' => '<p>صفحهٔ زنده.</p>',
	),
);
$GLOBALS['sa_test_transients'] = array();

/* ---- WordPress shims ------------------------------------------------- */

function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	add_filter( $tag, $callback, $priority, $accepted_args );
}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$GLOBALS['sa_test_filters'][ $tag ][ $priority ][] = $callback;
}
function remove_all_filters( $tag ) {
	unset( $GLOBALS['sa_test_filters'][ $tag ] );
}
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	if ( empty( $GLOBALS['sa_test_filters'][ $tag ] ) ) {
		return $value;
	}
	ksort( $GLOBALS['sa_test_filters'][ $tag ] );
	foreach ( $GLOBALS['sa_test_filters'][ $tag ] as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$args[0] = call_user_func_array( $callback, $args );
		}
	}
	return $args[0];
}
function get_theme_mod( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['sa_test_theme_mods'] ) ? $GLOBALS['sa_test_theme_mods'][ $name ] : $default;
}
function set_theme_mod( $name, $value ) {
	$GLOBALS['sa_test_theme_mods'][ $name ] = $value;
}
function get_post( $post ) {
	$id = is_object( $post ) ? (int) $post->ID : (int) $post;
	return isset( $GLOBALS['sa_test_posts'][ $id ] ) ? $GLOBALS['sa_test_posts'][ $id ] : null;
}
function get_post_type( $post = 0 ) {
	$p = get_post( $post );
	return $p ? $p->post_type : false;
}
function get_post_status( $post = 0 ) {
	$p = get_post( $post );
	return $p ? $p->post_status : false;
}
function get_post_field( $field, $post_id ) {
	$p = get_post( $post_id );
	return $p && isset( $p->$field ) ? $p->$field : '';
}
function get_post_meta( $post_id, $key = '', $single = false ) {
	$id = (int) $post_id;
	if ( ! isset( $GLOBALS['sa_test_meta'][ $id ][ $key ] ) ) {
		return $single ? '' : array();
	}
	$value = $GLOBALS['sa_test_meta'][ $id ][ $key ];
	return $single ? $value : (array) $value;
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['sa_test_meta'][ (int) $post_id ][ $key ] = $value;
	return true;
}
function get_post_thumbnail_id( $post_id ) {
	return (int) get_post_meta( $post_id, '_thumbnail_id', true );
}
function get_post_type_object( $type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return null;
}
function taxonomy_exists( $taxonomy ) {
	return in_array( $taxonomy, array( 'province_tax', 'attraction_type', 'travel_season', 'travel_budget', 'travel_duration' ), true );
}
function has_term( $term, $taxonomy, $post_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return (bool) get_post_meta( $post_id, '_test_has_' . $taxonomy, true );
}
function home_url( $path = '' ) {
	return 'https://sarzaminaryan.ir' . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
}
function set_transient( $key, $value, $expiration = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	$GLOBALS['sa_test_transients'][ $key ] = $value;
	return true;
}
function get_transient( $key ) {
	return isset( $GLOBALS['sa_test_transients'][ $key ] ) ? $GLOBALS['sa_test_transients'][ $key ] : false;
}
function delete_transient( $key ) {
	unset( $GLOBALS['sa_test_transients'][ $key ] );
	return true;
}
function get_current_user_id() {
	return 1;
}
function wp_verify_nonce( $nonce, $action = -1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return false;
}
function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
}
function wp_unslash( $value ) {
	return $value;
}
function absint( $value ) {
	return abs( (int) $value );
}
function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $value ) {
	return esc_html( $value );
}
function add_meta_box() {}
function sa_jalali_date( $format, $time ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	return (string) $time;
}
function sa_count_sources( $raw ) {
	return preg_match_all( '#https?://#i', (string) $raw );
}
function sa_count_markers( $text ) {
	$n = 0;
	foreach ( sa_uncertainty_markers() as $m ) {
		$n += substr_count( (string) $text, $m );
	}
	return $n;
}

require_once SA_CHILD_DIR . 'inc/entities-config.php';
require_once SA_CHILD_DIR . 'inc/helpers.php';
require_once SA_CHILD_DIR . 'inc/publish-gate.php';

/* ---- helpers --------------------------------------------------------- */

function sa_test_expect( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * Simulate WordPress saving a post with wp_insert_post_data filters applied.
 *
 * @param int    $post_id Post ID.
 * @param string $status  Requested status.
 * @return array Filtered data.
 */
function sa_test_save( $post_id, $status ) {
	$post = get_post( $post_id );
	$data = array(
		'post_type'    => $post->post_type,
		'post_status'  => $status,
		'post_content' => $post->post_content,
		'post_title'   => $post->post_title,
	);
	return apply_filters( 'wp_insert_post_data', $data, array( 'ID' => $post_id ) );
}

/* ---- 1. default mode is advisory ------------------------------------ */

$GLOBALS['sa_test_theme_mods'] = array();
sa_test_expect( 'soft' === sa_gate_mode(), 'default gate mode must be soft (advisory)' );
sa_test_expect( true === sa_gate_is_advisory(), 'sa_gate_is_advisory() must be true by default' );

$GLOBALS['sa_test_theme_mods']['sa_gate_mode'] = 'hard';
sa_test_expect( 'hard' === sa_gate_mode(), 'explicit Customizer choice "hard" must still be honoured' );
sa_test_expect( false === sa_gate_is_advisory(), 'hard mode is not advisory' );

$GLOBALS['sa_test_theme_mods']['sa_gate_mode'] = 'anything-else';
sa_test_expect( 'soft' === sa_gate_mode(), 'unknown values fall back to soft' );

/* ---- 2. policy helper ------------------------------------------------ */

$GLOBALS['sa_test_theme_mods'] = array();
list( $b, $w ) = sa_gate_apply_policy( array( 'سئو: عنوان سئو' ), array( 'یادآوری' ) );
sa_test_expect( array() === $b, 'advisory mode: no blockers survive' );
sa_test_expect( array( 'سئو: عنوان سئو', 'یادآوری' ) === $w, 'advisory mode: blockers become leading reminders' );

$GLOBALS['sa_test_theme_mods']['sa_gate_mode'] = 'hard';
list( $b, $w ) = sa_gate_apply_policy( array( 'سئو: عنوان سئو' ), array( 'یادآوری' ) );
sa_test_expect( array( 'سئو: عنوان سئو' ) === $b && array( 'یادآوری' ) === $w, 'hard mode: policy helper leaves the split untouched' );

/* ---- 3. an incomplete draft publishes in advisory mode -------------- */

$GLOBALS['sa_test_theme_mods'] = array();
$GLOBALS['sa_test_meta']       = array();
$GLOBALS['sa_test_transients'] = array();

$missing = sa_gate_missing( 201, 'attraction', null );
sa_test_expect( ! empty( $missing ), 'the test draft must be incomplete (no relation, SEO, sources)' );

$data = sa_test_save( 201, 'publish' );
sa_test_expect( 'publish' === $data['post_status'], 'advisory mode: owner approval publishes an incomplete entity' );

$report = get_post_meta( 201, '_sa_gate_report', true );
sa_test_expect( is_array( $report ) && 'info' === $report['mode'], 'advisory mode: stored report is informational' );
sa_test_expect( empty( $report['blocking'] ), 'advisory mode: stored report has no blockers' );
sa_test_expect( ! empty( $report['warnings'] ), 'advisory mode: stored report keeps the Level 7 reminders' );

$found_seo = false;
foreach ( $report['warnings'] as $line ) {
	if ( 0 === strpos( $line, 'سئو:' ) ) {
		$found_seo = true;
	}
}
sa_test_expect( $found_seo, 'advisory mode: missing SEO fields are reported as reminders' );

$notice = get_transient( 'sa_gate_1' );
sa_test_expect( is_array( $notice ) && 'info' === $notice['mode'] && empty( $notice['missing'] ), 'advisory mode: admin notice is a reminder, not an error' );

/* ---- 4. legacy hard mode still demotes drafts, never live pages ----- */

$GLOBALS['sa_test_theme_mods']['sa_gate_mode'] = 'hard';
$GLOBALS['sa_test_meta']                       = array();
$GLOBALS['sa_test_transients']                 = array();

$data = sa_test_save( 201, 'publish' );
sa_test_expect( 'draft' === $data['post_status'], 'hard mode (opt-in): incomplete draft is demoted' );
$report = get_post_meta( 201, '_sa_gate_report', true );
sa_test_expect( is_array( $report ) && 'hard' === $report['mode'] && ! empty( $report['blocking'] ), 'hard mode: report lists blockers' );

$data = sa_test_save( 202, 'publish' );
sa_test_expect( 'publish' === $data['post_status'], 'hard mode: a published page is never demoted (v2.11.42)' );
$report = get_post_meta( 202, '_sa_gate_report', true );
sa_test_expect( is_array( $report ) && 'protected' === $report['mode'], 'hard mode: live page report is marked protected' );

/* ---- 5. sa_gate_evaluate() for admin tools -------------------------- */

$GLOBALS['sa_test_theme_mods'] = array();
$eval                          = sa_gate_evaluate( 201 );
sa_test_expect( array() === $eval['blocking'] && ! empty( $eval['warnings'] ), 'evaluate(): advisory mode returns reminders only' );

$GLOBALS['sa_test_theme_mods']['sa_gate_mode'] = 'hard';
$eval                                          = sa_gate_evaluate( 201 );
sa_test_expect( ! empty( $eval['blocking'] ), 'evaluate(): hard mode reports blockers for a draft' );
$eval = sa_gate_evaluate( 202 );
sa_test_expect( array() === $eval['blocking'] && ! empty( $eval['warnings'] ), 'evaluate(): hard mode never reports blockers for a live page' );

$eval = sa_gate_evaluate( 999 );
sa_test_expect( array() === $eval['blocking'] && array() === $eval['warnings'], 'evaluate(): unknown post yields an empty report' );

/* ---- 6. sa_gate_mode filter ----------------------------------------- */

$GLOBALS['sa_test_theme_mods'] = array();
add_filter(
	'sa_gate_mode',
	function () {
		return 'hard';
	}
);
sa_test_expect( 'hard' === sa_gate_mode(), 'sa_gate_mode filter can force hard mode' );
remove_all_filters( 'sa_gate_mode' );
sa_test_expect( 'soft' === sa_gate_mode(), 'removing the filter restores the advisory default' );

/* ---- 7. badge wording ------------------------------------------------ */

$GLOBALS['sa_test_theme_mods'] = array();
$badge                         = sa_gate_badge( 201 );
sa_test_expect( false !== strpos( $badge, 'قابل انتشار' ), 'advisory mode: incomplete draft badge says it can be published' );
sa_test_expect( false === strpos( $badge, 'sa-badge--warn' ), 'advisory mode: the red "مانع انتشار" badge is never rendered' );
sa_test_expect( false !== strpos( $badge, 'یادآوری' ), 'advisory mode: badge counts reminders' );

echo "PASS: publish gate policy tests (owner approval is the only publish condition)\n";
