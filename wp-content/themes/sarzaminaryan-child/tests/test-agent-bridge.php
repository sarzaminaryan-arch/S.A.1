<?php
/**
 * Standalone tests for the agent bridge (inc/agent-bridge.php, v2.22.72).
 *
 * Run with: php wp-content/themes/sarzaminaryan-child/tests/test-agent-bridge.php
 *
 * Locks in the owner's rules for direct WordPress writes:
 *   - new articles are always drafts (a "publish" status in the request is ignored);
 *   - every change to an existing post or to theme settings takes a backup first
 *     (v2.22.120: a *diff* backup holding only the fields/meta that write touched),
 *     and nothing is written if the backup fails;
 *   - published text is never overwritten without the explicit owner flag —
 *     a side draft (draft-copy) is created instead;
 *   - no DELETE route exists; protected meta, unknown terms and banned phrases
 *     are rejected; Application Passwords only for editors/admins.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['t'] = array(
	'posts'   => array(),
	'meta'    => array(),
	'terms'   => array(),
	'mods'    => array( 'sa_show_featured' => false, 'custom_logo' => 12 ),
	'next'    => 100,
	'caps'    => array( 'edit_posts' => true, 'edit_others_posts' => true, 'edit_theme_options' => true ),
	'logged'  => true,
	'routes'  => array(),
	'filters' => array(),
	'fail_insert_type' => '',
	'clock'   => 0,
);

function sa_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
}
class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	public $max_num_pages = 0;
	public function __construct( $args ) {
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
function add_filter( $hook, $cb, $prio = 10, $n = 1 ) {
	$GLOBALS['t']['filters'][ $hook ][] = $cb;
}
function add_action( $hook, $cb, $prio = 10, $n = 1 ) {
	add_filter( $hook, $cb, $prio, $n );
}
function register_post_type( $type, $args ) {
	$GLOBALS['t']['registered'][ $type ] = $args;
}
function register_rest_route( $ns, $route, $args ) {
	$GLOBALS['t']['routes'][ $ns . $route ] = $args;
}
function rest_ensure_response( $d ) {
	return $d;
}
function sa_entity_types() {
	return array( 'province', 'city', 'attraction' );
}
function user_can( $user, $cap ) {
	return ! empty( $user->caps[ $cap ] );
}
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['t']['caps'][ $cap ] );
}
function is_user_logged_in() {
	return $GLOBALS['t']['logged'];
}
function wp_get_current_user() {
	return (object) array( 'user_login' => 'agent' );
}
function wp_json_encode( $d, $f = 0 ) {
	return json_encode( $d, $f );
}
function wp_slash( $v ) {
	return $v;
}
function maybe_unserialize( $v ) {
	return $v;
}
function sanitize_title( $s ) {
	return strtolower( preg_replace( '/[^A-Za-z0-9\-]+/', '-', (string) $s ) );
}
function sanitize_text_field( $s ) {
	return trim( (string) $s );
}
function sanitize_key( $s ) {
	return strtolower( (string) $s );
}
function admin_url( $p ) {
	return 'https://example.test/wp-admin/' . $p;
}
function home_url( $p = '' ) {
	return 'https://example.test' . $p;
}
function add_query_arg( $a, $u ) {
	return $u . '?' . http_build_query( $a );
}
function get_permalink( $id ) {
	$p = isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
	if ( $p && 'post' !== $p->post_type && '' !== (string) $p->post_name ) {
		return 'https://example.test/' . $p->post_type . '/' . $p->post_name . '/';
	}
	return 'https://example.test/?p=' . $id;
}
function get_post_status( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ]->post_status : false;
}
function wp_trash_post( $id ) {
	$GLOBALS['t']['posts'][ $id ]->post_status = 'trash';
	return $GLOBALS['t']['posts'][ $id ];
}
function wp_untrash_post( $id ) {
	$GLOBALS['t']['posts'][ $id ]->post_status = 'draft';
	return $GLOBALS['t']['posts'][ $id ];
}
function wp_publish_post( $id ) {
	if ( isset( $GLOBALS['t']['posts'][ $id ] ) ) {
		$GLOBALS['t']['posts'][ $id ]->post_status       = 'publish';
		$GLOBALS['t']['posts'][ $id ]->post_modified_gmt = sa_t_tick();
	}
}
function wp_parse_url( $u, $c = -1 ) {
	return parse_url( $u, $c ); // phpcs:ignore
}
function esc_url_raw( $u ) {
	return filter_var( $u, FILTER_VALIDATE_URL ) ? $u : '';
}
function has_filter( $hook ) {
	return ! empty( $GLOBALS['t']['filters'][ $hook ] );
}
function has_action( $hook ) {
	return has_filter( $hook );
}
function apply_filters( $hook, $value ) {
	if ( 'litespeed_conf' === $hook ) {
		return isset( $GLOBALS['t']['ls'][ $value ] ) ? $GLOBALS['t']['ls'][ $value ] : null;
	}
	// v2.22.120: سیاست نگه‌داری پشتیبان در تست قابل تنظیم است.
	if ( 'sa_agent_backup_retention' === $hook && isset( $GLOBALS['t']['retention'] ) ) {
		return array_merge( (array) $value, (array) $GLOBALS['t']['retention'] );
	}
	return $value;
}
function do_action( $hook, $arg = null ) {
	if ( 'litespeed_save_conf' === $hook ) {
		foreach ( (array) $arg as $k => $v ) {
			$GLOBALS['t']['ls'][ $k ] = $v;
		}
	}
}
function sa_redirect_normalize_path( $p ) {
	return '/' . trim( (string) $p, '/' ) . '/';
}
function sa_redirect_target_for_path( $p ) {
	$map = array( '/city/isfahan/' => '/city/isfahan-city/', '/city/orphan/' => '/city/nowhere/' );
	return isset( $map[ $p ] ) ? $map[ $p ] : '';
}
function sa_redirect_target_exists( $target ) {
	return '/city/isfahan-city/' === $target;
}
function get_post_thumbnail_id( $id ) {
	return isset( $GLOBALS['t']['meta'][ $id ]['_thumbnail_id'] ) ? (int) $GLOBALS['t']['meta'][ $id ]['_thumbnail_id'] : 0;
}
function get_post( $id ) {
	return isset( $GLOBALS['t']['posts'][ $id ] ) ? $GLOBALS['t']['posts'][ $id ] : null;
}
function sa_t_tick() {
	$GLOBALS['t']['clock']++;
	return sprintf( '2026-11-14 10:00:%02d', $GLOBALS['t']['clock'] );
}
function wp_insert_post( $arr, $wp_error = false ) {
	if ( $GLOBALS['t']['fail_insert_type'] === $arr['post_type'] ) {
		return new WP_Error( 'db', 'fail' );
	}
	$id = $GLOBALS['t']['next']++;
	$GLOBALS['t']['posts'][ $id ] = (object) array(
		'ID'                => $id,
		'post_type'         => $arr['post_type'],
		'post_status'       => isset( $arr['post_status'] ) ? $arr['post_status'] : 'draft',
		'post_name'         => isset( $arr['post_name'] ) ? $arr['post_name'] : '',
		'post_title'        => isset( $arr['post_title'] ) ? $arr['post_title'] : '',
		'post_content'      => isset( $arr['post_content'] ) ? $arr['post_content'] : '',
		'post_excerpt'      => isset( $arr['post_excerpt'] ) ? $arr['post_excerpt'] : '',
		'post_parent'       => isset( $arr['post_parent'] ) ? $arr['post_parent'] : 0,
		'menu_order'        => 0,
		'post_author'       => 1,
		'post_date_gmt'     => '2026-11-14 10:00:00',
		'post_modified_gmt' => sa_t_tick(),
	);
	$GLOBALS['t']['meta'][ $id ] = array();
	return $id;
}
function wp_update_post( $arr, $wp_error = false ) {
	$p = $GLOBALS['t']['posts'][ $arr['ID'] ];
	foreach ( $arr as $k => $v ) {
		if ( 'ID' !== $k ) {
			$p->$k = $v;
		}
	}
	$p->post_modified_gmt = sa_t_tick();
	return $arr['ID'];
}
function get_post_meta( $id, $key = '', $single = false ) {
	$m = isset( $GLOBALS['t']['meta'][ $id ] ) ? $GLOBALS['t']['meta'][ $id ] : array();
	if ( '' === $key ) {
		$out = array();
		foreach ( $m as $k => $v ) {
			$out[ $k ] = array( $v );
		}
		return $out;
	}
	return isset( $m[ $key ] ) ? $m[ $key ] : '';
}
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['t']['meta'][ $id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['t']['meta'][ $id ][ $key ] );
	return true;
}
function get_object_taxonomies( $type ) {
	return 'post' === $type ? array( 'category' ) : array();
}
function wp_get_object_terms( $id, $tax, $args ) {
	return isset( $GLOBALS['t']['terms'][ $id ][ $tax ] ) ? $GLOBALS['t']['terms'][ $id ][ $tax ] : array();
}
function get_term_by( $field, $value, $tax ) {
	$known = array( 'travel' => 7, 'camping' => 8 );
	return isset( $known[ $value ] ) ? (object) array( 'term_id' => $known[ $value ] ) : false;
}
function wp_set_object_terms( $id, $ids, $tax ) {
	$names = array( 7 => 'travel', 8 => 'camping' );
	$GLOBALS['t']['terms'][ $id ][ $tax ] = array_map(
		function ( $i ) use ( $names ) {
			return $names[ $i ];
		},
		$ids
	);
}
function get_theme_mods() {
	return $GLOBALS['t']['mods'];
}
// v2.22.120: پشتیبان افتراقی + سیاست نگه‌داری، برای سنجش بدون وردپرس واقعی.
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
function get_posts( $args = array() ) {
	$ids = array();
	foreach ( $GLOBALS['t']['posts'] as $id => $p ) {
		if ( isset( $args['post_type'] ) && $p->post_type !== $args['post_type'] ) {
			continue;
		}
		if ( isset( $args['post_status'] ) && $p->post_status !== $args['post_status'] ) {
			continue;
		}
		$ids[] = (int) $id;
	}
	sort( $ids );
	return array_reverse( $ids ); // تازه‌ترین اول، مثل orderby=date DESC
}
function wp_delete_post( $id, $force = false ) {
	unset( $GLOBALS['t']['posts'][ $id ], $GLOBALS['t']['meta'][ $id ] );
	return true;
}

function set_theme_mod( $k, $v ) {
	$GLOBALS['t']['mods'][ $k ] = $v;
}
function get_stylesheet() {
	return 'sarzaminaryan-child';
}

define( 'SA_CHILD_VERSION', 'test' );
require dirname( __DIR__ ) . '/inc/agent-bridge.php';

$t = &$GLOBALS['t'];

/* 1. Application Passwords: on, but only for users who can edit others' posts. */
sa_t_assert( true === sa_agent_app_passwords_available(), '1a: Application Passwords available site-wide' );
$editor = (object) array( 'caps' => array( 'edit_others_posts' => true ) );
$author = (object) array( 'caps' => array( 'edit_posts' => true ) );
sa_t_assert( true === sa_agent_app_passwords_for_user( true, $editor ), '1b: editor/admin may create an Application Password' );
sa_t_assert( false === sa_agent_app_passwords_for_user( true, $author ), '1c: authors/subscribers may not' );
$sec = file_get_contents( dirname( __DIR__ ) . '/inc/security.php' );
sa_t_assert( false === strpos( $sec, "'wp_is_application_passwords_available', '__return_false'" ), '1d: security.php no longer switches Application Passwords off' );
$fn = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
sa_t_assert( false !== strpos( $fn, "'inc/agent-bridge.php'" ), '1e: functions.php loads the bridge' );

/* 1f. Authorization fallback header (hosts that strip Authorization). */
$s = sa_agent_fill_basic_auth( array( 'REQUEST_URI' => '/wp-json/sa-agent/v1/ping', 'HTTP_X_SA_AUTHORIZATION' => 'Basic ' . base64_encode( 'agent:abcd efgh' ) ) );
sa_t_assert( 'agent' === $s['PHP_AUTH_USER'] && 'abcd efgh' === $s['PHP_AUTH_PW'], '1f: X-SA-Authorization fills PHP_AUTH_*' );
$s = sa_agent_fill_basic_auth( array( 'REQUEST_URI' => '/some-page/', 'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode( 'a:b' ) ) );
sa_t_assert( empty( $s['PHP_AUTH_USER'] ), '1g: only REST requests are touched' );

/* 2. Routes: the expected set, every one with a permission callback, and no DELETE. */
$expected = array( '/ping', '/posts', '/post/(?P<id>\d+)', '/post/(?P<id>\d+)/draft-copy', '/post/(?P<id>\d+)/trash', '/draft', '/backups', '/backup/(?P<id>\d+)', '/restore/(?P<id>\d+)', '/theme-mods', '/litespeed' );
sa_agent_register_routes();
foreach ( $expected as $r ) {
	sa_t_assert( isset( $t['routes'][ 'sa-agent/v1' . $r ] ), "2a: route $r registered" );
}
foreach ( $t['routes'] as $name => $def ) {
	$handlers = isset( $def['methods'] ) ? array( $def ) : $def;
	foreach ( $handlers as $h ) {
		sa_t_assert( ! empty( $h['permission_callback'] ) && '__return_true' !== $h['permission_callback'], "2b: $name has a real permission callback" );
		sa_t_assert( false === stripos( (string) $h['methods'], 'DELETE' ), "2c: $name has no DELETE" );
	}
}
$t['logged'] = false;
sa_t_assert( ! sa_agent_can_read() && ! sa_agent_can_write() && ! sa_agent_can_theme(), '2d: logged-out visitors get nothing' );
$t['logged'] = true;
$t['caps']   = array( 'edit_posts' => true );
sa_t_assert( sa_agent_can_read() && ! sa_agent_can_write(), '2e: an author can read but not write' );
$t['caps'] = array( 'edit_posts' => true, 'edit_others_posts' => true, 'edit_theme_options' => true );

/* 3. New articles are always drafts. */
$r = sa_agent_create_draft( array( 'type' => 'post', 'title' => 'سفر به کاشان', 'content' => '<p>متن</p>', 'status' => 'publish', 'post_status' => 'publish', 'terms' => array( 'category' => array( 'travel' ) ), 'meta' => array( 'sa_source' => 'x' ) ) );
sa_t_assert( is_array( $r ) && 'draft' === $r['status'], '3a: create returns a draft' );
sa_t_assert( 'draft' === $t['posts'][ $r['id'] ]->post_status, '3b: stored status is draft even when publish was requested' );
sa_t_assert( array( 'travel' ) === $t['terms'][ $r['id'] ]['category'], '3c: existing terms are attached' );
sa_t_assert( 'x' === $t['meta'][ $r['id'] ]['sa_source'], '3d: meta saved' );
$e = sa_agent_create_draft( array( 'type' => 'post', 'title' => 'تست', 'content' => 'راستش را بخواهید' ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_banned_phrase' === $e->code, '3e: banned phrase rejected' );
$e = sa_agent_create_draft( array( 'type' => 'post', 'title' => 'تست', 'content' => 'این متن با هوش مصنوعی نوشته شد' ) );
sa_t_assert( is_wp_error( $e ), '3f: process terms rejected' );
$e = sa_agent_create_draft( array( 'type' => 'post', 'title' => 'تست', 'terms' => array( 'category' => array( 'brand-new' ) ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_unknown_term' === $e->code, '3g: unknown term rejected (no new terms)' );
$e = sa_agent_create_draft( array( 'type' => 'post', 'title' => 'تست', 'meta' => array( '_wp_page_template' => 'x' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_protected_meta' === $e->code, '3h: protected meta rejected' );
$e = sa_agent_create_draft( array( 'type' => 'attachment', 'title' => 'تست' ) );
sa_t_assert( is_wp_error( $e ), '3i: unknown post type rejected' );
$r2 = sa_agent_create_draft( array( 'type' => 'city', 'title' => 'شهرستان تست' ) );
sa_t_assert( is_array( $r2 ) && 'city' === $t['posts'][ $r2['id'] ]->post_type, '3j: entity CPTs (show_in_rest=false) are writable as drafts' );

/* 4. Updating an existing published post: backup first, live text protected. */
$live = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'اصل', 'post_content' => 'متن اصلی' ) );
update_post_meta( $live, 'sa_note', 'old' );
$snap = sa_agent_snapshot_post( $live );
sa_t_assert( 'publish' === $snap['status'] && 'old' === $snap['meta']['sa_note'], '4a: snapshot reads status and meta' );
$e = sa_agent_update_post( $live, array( 'fields' => array( 'content' => 'تازه' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_need_expected' === $e->code, '4b: expected_modified_gmt required' );
$e = sa_agent_update_post( $live, array( 'expected_modified_gmt' => '1999-01-01 00:00:00', 'meta' => array( 'sa_note' => 'new' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_stale' === $e->code, '4c: stale read rejected' );
$e = sa_agent_update_post( $live, array( 'expected_modified_gmt' => $snap['modified_gmt'], 'fields' => array( 'content' => 'تازه' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_live_content' === $e->code, '4d: published text not overwritten without owner flag' );
sa_t_assert( 'متن اصلی' === $t['posts'][ $live ]->post_content, '4e: live content untouched' );
$before_count = count( $t['posts'] );
$u = sa_agent_update_post( $live, array( 'expected_modified_gmt' => $snap['modified_gmt'], 'meta' => array( 'sa_note' => 'new' ), 'reason' => 'اصلاح متا' ) );
sa_t_assert( is_array( $u ) && $u['backup_id'] > 0, '4f: meta update returns a backup id' );
sa_t_assert( 'new' === $t['meta'][ $live ]['sa_note'], '4g: meta changed' );
$b = sa_agent_read_backup( $u['backup_id'] );
// v2.22.120: پشتیبان افتراقی است — فقط همان متایی که عوض شد، نه کل رکورد ۱٬۱۰۰ واژه‌ای.
sa_t_assert( true === $b['data']['diff'] && 'old' === $b['data']['meta']['sa_note'], '4h: diff backup holds the prior value of the changed meta' );
sa_t_assert( ! isset( $b['data']['fields']['content'] ) && ! isset( $b['data']['content'] ), '4h-b: unchanged content is not copied into the backup' );
sa_t_assert( 'private' === $t['posts'][ $u['backup_id'] ]->post_status, '4h-c: backup record is private' );
sa_t_assert( (int) get_post_meta( $u['backup_id'], '_sa_backup_bytes', true ) > 0 && strlen( (string) $t['meta'][ $u['backup_id'] ]['_sa_backup_json'] ) < 600, '4h-d: diff backup stays small (under 600 bytes)' );
sa_t_assert( 'publish' === $t['posts'][ $live ]->post_status, '4i: status never changed by an update' );
$e = sa_agent_update_post( $live, array( 'expected_modified_gmt' => $t['posts'][ $live ]->post_modified_gmt, 'fields' => array( 'status' => 'trash', 'slug' => 'x' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_nothing' === $e->code, '4j: status/slug are not editable fields' );

/* 5. Backup failure blocks the write. */
$t['fail_insert_type'] = SA_AGENT_BACKUP_TYPE;
$e = sa_agent_update_post( $live, array( 'expected_modified_gmt' => $t['posts'][ $live ]->post_modified_gmt, 'meta' => array( 'sa_note' => 'blocked' ) ) );
sa_t_assert( is_wp_error( $e ) && 'new' === $t['meta'][ $live ]['sa_note'], '5a: no backup → no write' );
$t['fail_insert_type'] = '';

/* 6. draft-copy leaves the original alone. */
$c = sa_agent_draft_copy( $live, array( 'fields' => array( 'content' => 'نسخهٔ بازنویسی' ) ) );
sa_t_assert( is_array( $c ) && 'draft' === $t['posts'][ $c['id'] ]->post_status, '6a: copy is a draft' );
sa_t_assert( $live === (int) $t['meta'][ $c['id'] ]['_sa_agent_draft_of'], '6b: copy points to the original' );
sa_t_assert( 'متن اصلی' === $t['posts'][ $live ]->post_content && 'نسخهٔ بازنویسی' === $t['posts'][ $c['id'] ]->post_content, '6c: original unchanged, copy has new text' );

/* 7. Owner-approved live edit, then restore (which also backs up first). */
$u2 = sa_agent_update_post( $live, array( 'expected_modified_gmt' => $t['posts'][ $live ]->post_modified_gmt, 'fields' => array( 'content' => 'متن تأییدشده' ), 'meta' => array( 'sa_added' => '1' ), 'owner_approved_live_edit' => true ) );
sa_t_assert( is_array( $u2 ) && 'متن تأییدشده' === $t['posts'][ $live ]->post_content, '7a: owner-approved edit applied' );
$res = sa_agent_restore( $u2['backup_id'] );
sa_t_assert( is_array( $res ) && 'متن اصلی' === $t['posts'][ $live ]->post_content, '7b: restore brings back the prior text' );
sa_t_assert( ! isset( $t['meta'][ $live ]['sa_added'] ) && 'new' === $t['meta'][ $live ]['sa_note'], '7c: restore brings back prior meta' );
$pre = sa_agent_read_backup( $res['backup_id'] );
sa_t_assert( true === $pre['data']['diff'] && 'متن تأییدشده' === $pre['data']['fields']['content'], '7d: restore itself backed up the exact state it replaced (diff)' );

/* 8. Theme settings: backup first, only sa_* or existing keys, scalar values. */
$m = sa_agent_theme_mods_snapshot();
$e = sa_agent_set_theme_mods( array( 'expected_hash' => 'nope', 'set' => array( 'sa_show_featured' => true ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_stale' === $e->code, '8a: stale hash rejected' );
$e = sa_agent_set_theme_mods( array( 'expected_hash' => $m['hash'], 'set' => array( 'nav_menu_locations' => 1 ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_bad_mod' === $e->code, '8b: unknown non-sa key rejected' );
$e = sa_agent_set_theme_mods( array( 'expected_hash' => $m['hash'], 'set' => array( 'sa_x' => array( 1 ) ) ) );
sa_t_assert( is_wp_error( $e ), '8c: non-scalar value rejected' );
$ok = sa_agent_set_theme_mods( array( 'expected_hash' => $m['hash'], 'set' => array( 'sa_show_featured' => true ) ) );
sa_t_assert( is_array( $ok ) && true === $t['mods']['sa_show_featured'], '8d: theme mod changed' );
$tb = sa_agent_read_backup( $ok['backup_id'] );
sa_t_assert( 'theme_mods' === $tb['kind'] && false === $tb['data']['mods']['sa_show_featured'], '8e: theme-mod backup holds the prior value' );
sa_agent_restore( $ok['backup_id'] );
sa_t_assert( false === $t['mods']['sa_show_featured'], '8f: theme-mod restore works' );

/* 9. Backup post type is private and admin-only. */
sa_agent_register_backup_type();
$reg = $t['registered'][ SA_AGENT_BACKUP_TYPE ];
sa_t_assert( false === $reg['public'] && false === $reg['show_in_rest'] && false === $reg['rewrite'] && 'manage_options' === $reg['capabilities']['read_post'], '9a: backups are private, admin-only, no URL' );

sa_t_assert( false === $reg['show_in_menu'] && true === $reg['show_ui'], 'backups stay accessible for recovery without a sidebar entry' );

/* 10. Direct post_status => publish or force_delete never appears; trash and draft-publish exist only inside their guarded functions. */
$src = file_get_contents( dirname( __DIR__ ) . '/inc/agent-bridge.php' );
foreach ( array( 'force_delete', "'post_status'  => 'publish'", "'post_status' => 'publish'" ) as $needle ) {
	sa_t_assert( false === strpos( $src, $needle ), "10: bridge source never contains $needle" );
}
// v2.24.20: انتشار پیش‌نویس فقط در sa_agent_publish_draft و با پرچم صریح مالک و پشتیبان پیشین مجاز است.
sa_t_assert( 1 === substr_count( $src, 'wp_publish_post(' ), '10-p1: wp_publish_post is called exactly once' );
$pub_start = strpos( $src, 'function sa_agent_publish_draft(' );
$pub_end   = strpos( $src, "\n}\n", $pub_start );
$pub_at    = strpos( $src, 'wp_publish_post(' );
sa_t_assert( false !== $pub_start && $pub_at > $pub_start && $pub_at < $pub_end, '10-p2: …and only inside sa_agent_publish_draft' );
// v2.22.120: حذف فقط برای رکورد پشتیبان کهنه، در یک تابع نگهبان با بررسی نوع.
sa_t_assert( 1 === substr_count( $src, 'wp_delete_post(' ), '10-a: wp_delete_post is called exactly once (backup retention)' );
$ret_start = strpos( $src, 'function sa_agent_delete_backup_record(' );
$ret_end   = strpos( $src, "\n}\n", $ret_start );
$del_at    = strpos( $src, 'wp_delete_post(' );
sa_t_assert( false !== $ret_start && $del_at > $ret_start && $del_at < $ret_end, '10-b: …only inside sa_agent_delete_backup_record' );
sa_t_assert( false !== strpos( substr( $src, $ret_start, $ret_end - $ret_start ), 'SA_AGENT_BACKUP_TYPE !== $post->post_type' ), '10-c: …and it refuses anything that is not a backup record' );
sa_t_assert( 1 === substr_count( $src, 'wp_trash_post(' ), '10b: wp_trash_post is called exactly once' );
$fn_start = strpos( $src, 'function sa_agent_trash_redirect_source(' );
$fn_end   = strpos( $src, "\n}\n", $fn_start );
$trash_at = strpos( $src, 'wp_trash_post(' );
sa_t_assert( false !== $fn_start && $trash_at > $fn_start && $trash_at < $fn_end, '10c: …and only inside sa_agent_trash_redirect_source' );

/* 11. Trash: owner flag + fresh read + redirect source with a published target + backup first (v2.22.74). */
$dup  = wp_insert_post( array( 'post_type' => 'city', 'post_status' => 'publish', 'post_name' => 'isfahan', 'post_title' => 'اصفهان (تکراری)', 'post_content' => 'متن قدیمی' ) );
$orph = wp_insert_post( array( 'post_type' => 'city', 'post_status' => 'publish', 'post_name' => 'orphan', 'post_title' => 'بی‌مقصد' ) );
$norm = wp_insert_post( array( 'post_type' => 'city', 'post_status' => 'publish', 'post_name' => 'kashan', 'post_title' => 'کاشان' ) );
$gmt  = function ( $id ) use ( &$t ) {
	return $t['posts'][ $id ]->post_modified_gmt;
};
$e = sa_agent_trash_redirect_source( $dup, array( 'expected_modified_gmt' => $gmt( $dup ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_need_owner' === $e->code, '11a: owner flag required' );
$e = sa_agent_trash_redirect_source( $dup, array( 'owner_approved_trash' => 'yes', 'expected_modified_gmt' => $gmt( $dup ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_need_owner' === $e->code, '11b: flag must be literally true' );
$e = sa_agent_trash_redirect_source( $dup, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => '1999-01-01 00:00:00' ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_stale' === $e->code, '11c: stale read rejected' );
$e = sa_agent_trash_redirect_source( $norm, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => $gmt( $norm ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_not_redirect_source' === $e->code && 'publish' === $t['posts'][ $norm ]->post_status, '11d: a normal page (not a redirect source) is never trashed' );
$e = sa_agent_trash_redirect_source( $orph, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => $gmt( $orph ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_target_missing' === $e->code && 'publish' === $t['posts'][ $orph ]->post_status, '11e: redirect source with an unpublished target is kept' );
$t['fail_insert_type'] = SA_AGENT_BACKUP_TYPE;
$e = sa_agent_trash_redirect_source( $dup, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => $gmt( $dup ) ) );
sa_t_assert( is_wp_error( $e ) && 'publish' === $t['posts'][ $dup ]->post_status, '11f: no backup → no trash' );
$t['fail_insert_type'] = '';
$tr = sa_agent_trash_redirect_source( $dup, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => $gmt( $dup ) ) );
sa_t_assert( is_array( $tr ) && 'trash' === $tr['status'] && 'trash' === $t['posts'][ $dup ]->post_status, '11g: duplicate behind a 301 goes to trash' );
sa_t_assert( '/city/isfahan/' === $tr['redirect']['from'] && '/city/isfahan-city/' === $tr['redirect']['to'], '11h: result reports the redirect that keeps visitors safe' );
$tb2 = sa_agent_read_backup( $tr['backup_id'] );
sa_t_assert( 'publish' === $tb2['data']['status'] && 'متن قدیمی' === $tb2['data']['content'], '11i: backup holds the published state and text' );
$e = sa_agent_trash_redirect_source( $dup, array( 'owner_approved_trash' => true, 'expected_modified_gmt' => $gmt( $dup ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_already_trashed' === $e->code, '11j: already trashed' );
sa_agent_restore( $tr['backup_id'] );
sa_t_assert( 'draft' === $t['posts'][ $dup ]->post_status && 'متن قدیمی' === $t['posts'][ $dup ]->post_content, '11k: restore brings it out of trash as a draft' );

/* 11-p. Guarded cluster draft publish (v2.24.20): owner flag + fresh read + parent relation + clean text + backup first + reversible restore. */
$draft_att = wp_insert_post( array( 'post_type' => 'attraction', 'post_status' => 'draft', 'post_name' => 'milad-tower', 'post_title' => 'برج میلاد تهران', 'post_content' => '<p>راهنمای کامل بازدید از برج میلاد تهران.</p>' ) );
$e = sa_agent_publish_draft( $draft_att, array( 'expected_modified_gmt' => $gmt( $draft_att ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_need_owner' === $e->code, '11p-1: owner_approved_publish required' );
$e = sa_agent_publish_draft( $draft_att, array( 'owner_approved_publish' => 'yes', 'expected_modified_gmt' => $gmt( $draft_att ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_need_owner' === $e->code, '11p-2: owner_approved_publish must be boolean true' );
$e = sa_agent_publish_draft( $draft_att, array( 'owner_approved_publish' => true, 'expected_modified_gmt' => '1999-01-01 00:00:00' ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_stale' === $e->code, '11p-3: stale read rejected' );
$e = sa_agent_publish_draft( $norm, array( 'owner_approved_publish' => true, 'expected_modified_gmt' => $gmt( $norm ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_not_draft' === $e->code, '11p-4: already-published post rejected' );
$e = sa_agent_publish_draft( $draft_att, array( 'owner_approved_publish' => true, 'expected_modified_gmt' => $gmt( $draft_att ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_missing_parent' === $e->code, '11p-5: orphan attraction without sa_city_id rejected' );
update_post_meta( $draft_att, 'sa_city_id', 100 );
$t['fail_insert_type'] = SA_AGENT_BACKUP_TYPE;
$e = sa_agent_publish_draft( $draft_att, array( 'owner_approved_publish' => true, 'expected_modified_gmt' => $gmt( $draft_att ) ) );
sa_t_assert( is_wp_error( $e ) && 'draft' === $t['posts'][ $draft_att ]->post_status, '11p-6: no backup → no publish' );
$t['fail_insert_type'] = '';
$pub = sa_agent_publish_draft( $draft_att, array( 'owner_approved_publish' => true, 'expected_modified_gmt' => $gmt( $draft_att ), 'reason' => 'انتشار خوشه تهران' ) );
sa_t_assert( is_array( $pub ) && 'publish' === $pub['status'] && 'publish' === $t['posts'][ $draft_att ]->post_status, '11p-7: valid cluster draft promoted to publish' );
sa_agent_restore( $pub['backup_id'] );
sa_t_assert( 'draft' === $t['posts'][ $draft_att ]->post_status, '11p-8: restore reverts cluster-published post back to draft' );

/* 12. LiteSpeed: read-only unless active; only crawler keys writable; backup first; restore. */
$ls = sa_agent_litespeed_snapshot();
sa_t_assert( false === $ls['active'], '12a: inactive without the plugin' );
$e = sa_agent_set_litespeed( array( 'expected_hash' => $ls['hash'], 'set' => array( 'crawler' => true ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_no_litespeed' === $e->code, '12b: no plugin → no write' );
define( 'LSCWP_V', '7.9.3' );
$t['filters']['litespeed_conf'][]      = 'x';
$t['filters']['litespeed_save_conf'][] = 'x';
$t['ls'] = array( 'cache' => true, 'crawler' => false, 'crawler-crawl_interval' => 302400, 'crawler-load_limit' => 1, 'crawler-sitemap' => '', 'guest' => false );
$ls = sa_agent_litespeed_snapshot();
sa_t_assert( true === $ls['active'] && true === $ls['conf']['cache'] && true === $ls['server_allows_crawler'], '12c: snapshot reads cache/crawler settings' );
$e = sa_agent_set_litespeed( array( 'expected_hash' => 'nope', 'set' => array( 'crawler' => true ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_stale' === $e->code, '12d: stale hash rejected' );
$e = sa_agent_set_litespeed( array( 'expected_hash' => $ls['hash'], 'set' => array( 'cache' => false ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_bad_ls_key' === $e->code && true === $t['ls']['cache'], '12e: cache settings are read-only' );
$e = sa_agent_set_litespeed( array( 'expected_hash' => $ls['hash'], 'set' => array( 'crawler-sitemap' => 'https://evil.example/sitemap.xml' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_bad_ls_value' === $e->code, '12f: foreign sitemap rejected' );
$e = sa_agent_set_litespeed( array( 'expected_hash' => $ls['hash'], 'set' => array( 'crawler-crawl_interval' => 60 ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_bad_ls_value' === $e->code, '12g: crawl interval under a day rejected (server load)' );
$ok = sa_agent_set_litespeed( array( 'expected_hash' => $ls['hash'], 'set' => array( 'crawler' => true, 'crawler-sitemap' => 'https://example.test/wp-sitemap.xml' ), 'reason' => 'گرم‌کردن کش' ) );
sa_t_assert( is_array( $ok ) && true === $t['ls']['crawler'] && 'https://example.test/wp-sitemap.xml' === $t['ls']['crawler-sitemap'], '12h: crawler enabled with the site sitemap' );
$lb = sa_agent_read_backup( $ok['backup_id'] );
sa_t_assert( 'litespeed' === $lb['kind'] && false === $lb['data']['conf']['crawler'], '12i: LiteSpeed backup holds the prior values' );
sa_agent_restore( $ok['backup_id'] );
sa_t_assert( false === $t['ls']['crawler'] && '' === $t['ls']['crawler-sitemap'], '12j: LiteSpeed restore works' );
$t['caps'] = array( 'edit_posts' => true, 'edit_others_posts' => true, 'edit_theme_options' => true );
sa_t_assert( ! sa_agent_can_admin(), '12k: LiteSpeed routes need manage_options' );

/* 13. v2.22.120 — پشتیبان افتراقی و سیاست نگه‌داری (دستور مالک: هاست پر نشود). */

/* 13a. تنها فیلد عوض‌شده در پشتیبان می‌آید؛ متن ۱٬۱۰۰ واژه‌ای کپی نمی‌شود. */
$long = str_repeat( 'متن بلند شهرستان. ', 60 ); // ~۱٬۱۰۰ نویسه
$dp   = wp_insert_post( array( 'post_type' => 'city', 'post_status' => 'publish', 'post_title' => 'دزپارت', 'post_content' => $long ) );
update_post_meta( $dp, 'sa_province_id', 71 );
update_post_meta( $dp, 'sa_source_1', 'قدیم' );
$snap = sa_agent_snapshot_post( $dp );
$res  = sa_agent_update_post( $dp, array( 'expected_modified_gmt' => $snap['modified_gmt'], 'meta' => array( 'sa_source_1' => 'تازه' ), 'owner_approved_live_edit' => true, 'reason' => 'اصلاح منبع' ) );
sa_t_assert( is_array( $res ) && $res['backup_id'] > 0, '13a-1: a meta-only write still gets a backup' );
$bd = sa_agent_read_backup( $res['backup_id'] );
sa_t_assert( 'قدیم' === $bd['data']['meta']['sa_source_1'] && ! isset( $bd['data']['meta']['sa_province_id'] ), '13a-2: only the changed meta is kept' );
sa_t_assert( ! isset( $bd['data']['fields']['content'] ) && ! isset( $bd['data']['content'] ), '13a-3: the untouched 1,100-character body is not copied' );
$bytes = (int) $t['meta'][ $res['backup_id'] ]['_sa_backup_bytes'];
sa_t_assert( $bytes > 0 && $bytes < 800, '13a-4: diff backup is under 800 bytes (was the whole record before)' );
sa_t_assert( $bytes < strlen( $long ) / 2, '13a-5: the backup is far smaller than the body it protects (' . $bytes . ' bytes)' );

/* 13b. کلید تازه‌ای که آن نوشتن ساخته بود، در بازگردانی برداشته می‌شود. */
$snap = sa_agent_snapshot_post( $dp );
$res2 = sa_agent_update_post( $dp, array( 'expected_modified_gmt' => $snap['modified_gmt'], 'meta' => array( 'sa_new_key' => 'x' ) ) );
$bd2  = sa_agent_read_backup( $res2['backup_id'] );
sa_t_assert( in_array( 'sa_new_key', (array) $bd2['data']['missing_meta'], true ), '13b-1: a key that did not exist is recorded as missing' );
sa_agent_restore( $res2['backup_id'] );
sa_t_assert( ! isset( $t['meta'][ $dp ]['sa_new_key'] ), '13b-2: restore removes the key it had created' );

/* 13c. بدون تفاوت واقعی، نه پشتیبان و نه نوشتن. */
$snap = sa_agent_snapshot_post( $dp );
$before_posts = count( $t['posts'] );
$e = sa_agent_update_post( $dp, array( 'expected_modified_gmt' => $snap['modified_gmt'], 'meta' => array( 'sa_source_1' => 'تازه' ) ) );
sa_t_assert( is_wp_error( $e ) && 'sa_agent_nothing' === $e->code && count( $t['posts'] ) === $before_posts, '13c: an identical write creates no backup and no change' );

/* 13d. سیاست نگه‌داری: از هر هدف فقط N نسخهٔ آخر می‌ماند، و سقف کل. */
$t['retention'] = array( 'per_target' => 2, 'total' => 1000 );
$rp = wp_insert_post( array( 'post_type' => 'city', 'post_status' => 'publish', 'post_title' => 'شهر تست نگه‌داری' ) );
for ( $i = 1; $i <= 5; $i++ ) {
	$sn = sa_agent_snapshot_post( $rp );
	sa_agent_update_post( $rp, array( 'expected_modified_gmt' => $sn['modified_gmt'], 'meta' => array( 'sa_seq' => (string) $i ) ) );
}
$kept = 0;
foreach ( $t['posts'] as $pid => $pp ) {
	if ( SA_AGENT_BACKUP_TYPE === $pp->post_type && (int) $t['meta'][ $pid ]['_sa_backup_target'] === $rp ) {
		++$kept;
	}
}
sa_t_assert( 2 === $kept, '13d-1: only the last 2 backups of one target are kept (found ' . $kept . ')' );
$t['retention'] = array( 'per_target' => 3, 'total' => 4 );
$summary = sa_agent_backup_enforce_retention( 'meta' );
$total   = 0;
foreach ( $t['posts'] as $pid => $pp ) {
	if ( SA_AGENT_BACKUP_TYPE === $pp->post_type ) {
		++$total;
	}
}
sa_t_assert( $total <= 4 && isset( $summary['total_deleted'] ), '13d-2: the global ceiling is enforced (' . $total . ' kept)' );
$t['retention'] = array();
sa_t_assert( false === sa_agent_delete_backup_record( $dp ), '13d-3: the retention deleter refuses a normal post' );

/* 14. v2.24.10 — نوع محتوای «guide» و همگام‌سازی مقالات آماده با پشتیبان تفاضلی. */
function sa_ready_articles_data( $scope = 'all' ) {
	unset( $scope );
	return array(
		'diy-water-filter' => array(
			'type'    => 'post',
			'slug'    => 'diy-water-filter',
			'title'   => 'ساخت فیلتر آب اضطراری',
			'excerpt' => 'چکیدهٔ جدید مخزن.',
			'content' => '<p>متن به‌روزشدهٔ مخزن.</p>',
			'meta'    => array(
				'sa_focus_keyword' => 'فیلتر آب اضطراری',
				'sa_sources'       => "منبع یک | https://example.org/a\r\nمنبع دو | https://example.org/b",
			),
			'faq'     => array(
				array( 'q' => 'آیا ویروس را می‌گیرد؟', 'a' => 'خیر؛ جوشاندن لازم است.' ),
			),
			'cover'   => array(
				'file' => 'guides/diy-water-filter.webp',
				'alt'  => 'اینفوگرافیک فیلتر آب اضطراری',
			),
		),
	);
}
function sa_ready_article_target_type( $article ) {
	return isset( $article['type'] ) && 'post' === $article['type'] ? 'guide' : (string) $article['type'];
}
function sa_ready_articles_content( $article, $post_id = 0 ) {
	unset( $post_id );
	return (string) $article['content'];
}
function sa_ready_articles_attach_cover( $post_id, $article, $set_thumbnail = true ) {
	unset( $article );
	if ( $set_thumbnail ) {
		update_post_meta( $post_id, '_thumbnail_id', '777' );
	}
	return 777;
}

sa_t_assert( in_array( 'guide', sa_agent_post_types(), true ), '14a-1: guide CPT is exposed to agent bridge' );
$gp = wp_insert_post(
	array(
		'post_type'    => 'guide',
		'post_status'  => 'publish',
		'post_name'    => 'diy-water-filter',
		'post_title'   => 'ساخت فیلتر آب اضطراری',
		'post_excerpt' => 'چکیدهٔ قدیم.',
		'post_content' => '<p>متن قدیم.</p>',
	)
);
$gsnap = sa_agent_snapshot_post( $gp );
sa_t_assert( is_array( $gsnap ) && 'guide' === $gsnap['type'], '14a-2: snapshot reads guide CPT' );
$ge = sa_agent_update_post(
	$gp,
	array(
		'expected_modified_gmt' => $gsnap['modified_gmt'],
		'sync_ready_article'    => true,
	)
);
sa_t_assert( is_wp_error( $ge ) && 'sa_agent_live_content' === $ge->code, '14b-1: sync_ready_article still requires owner_approved_live_edit when body differs' );
$gres = sa_agent_update_post(
	$gp,
	array(
		'expected_modified_gmt'    => $gsnap['modified_gmt'],
		'sync_ready_article'       => true,
		'owner_approved_live_edit' => true,
		'reason'                   => 'همگام‌سازی راهنمای آماده',
	)
);
sa_t_assert( is_array( $gres ) && $gres['backup_id'] > 0, '14b-2: sync_ready_article creates a diff backup' );
sa_t_assert( '<p>متن به‌روزشدهٔ مخزن.</p>' === $t['posts'][ $gp ]->post_content, '14b-3: guide content synced' );
sa_t_assert( '777' === (string) $t['meta'][ $gp ]['_thumbnail_id'], '14b-4: bundled cover attached through diff-backed _thumbnail_id' );
sa_t_assert( "منبع یک | https://example.org/a\nمنبع دو | https://example.org/b" === $t['meta'][ $gp ]['sa_sources'], '14b-5: CRLF normalized to LF in synced meta' );
sa_t_assert( is_array( json_decode( $t['meta'][ $gp ]['sa_faq'], true ) ), '14b-6: synced sa_faq is valid JSON' );
sa_agent_restore( $gres['backup_id'] );
sa_t_assert( '<p>متن قدیم.</p>' === $t['posts'][ $gp ]->post_content && ! isset( $t['meta'][ $gp ]['_thumbnail_id'] ), '14b-7: restore reverts guide body and removes attached cover' );

echo "PASS: agent bridge (drafts only, backup before every change, live text protected, restore, theme-mod backups, editors-only Application Passwords, no DELETE, owner-approved trash only for 301 duplicates, LiteSpeed crawler keys only)\n";
