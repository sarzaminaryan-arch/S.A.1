<?php
define( 'ABSPATH', '/tmp/' );
error_reporting( E_ALL );
$GLOBALS['options'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['next_id'] = 200;

function register_activation_hook( $file, $cb ) {}
function add_action( $hook, $cb ) {}
function current_time( $type ) { return '2026-11-11 12:00:00'; }
function get_current_user_id() { return 1; }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['options'] ) ? $GLOBALS['options'][ $key ] : $default; }
function add_option( $key, $value, $deprecated = '', $autoload = 'yes' ) { if ( array_key_exists( $key, $GLOBALS['options'] ) ) return false; $GLOBALS['options'][ $key ] = $value; return true; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function get_posts( $args ) { foreach ( $GLOBALS['posts'] as $p ) { if ( $p->post_name === $args['name'] && $p->post_type === $args['post_type'] ) return array( clone $p ); } return array(); }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null; }
function get_post_meta( $id, $key, $single = false ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function metadata_exists( $type, $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ); }
function wp_slash( $value ) { return is_string( $value ) ? addslashes( $value ) : $value; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = is_string( $value ) ? stripslashes( $value ) : $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }

require dirname(__DIR__) . '/sa-faq-restore.php';

function assert_true( $cond, $msg ) { if ( ! $cond ) { fwrite( STDERR, "FAIL: $msg\n" ); exit( 1 ); } echo "ok - $msg\n"; }

$ref = new ReflectionMethod( 'SA_FAQ_Restore', 'records' );
$ref->setAccessible( true );
$records = $ref->invoke( null );
assert_true( 8 === count( $records ), 'eight records embedded' );
assert_true( 40 === array_sum( array_map( function( $x ) { return count( $x['faq'] ); }, $records ) ), 'forty FAQ rows embedded' );

foreach ( $records as $rec ) {
    $id = $GLOBALS['next_id']++;
    $post = new stdClass();
    $post->ID = $id; $post->post_name = $rec['slug']; $post->post_type = 'post'; $post->post_status = 'publish'; $post->post_title = 'T'; $post->post_content = 'DO NOT TOUCH';
    $GLOBALS['posts'][ $id ] = $post;
    $GLOBALS['meta'][ $id ] = array( 'untouched_meta' => 'KEEP' );
}

$report = SA_FAQ_Restore::apply_changes( 'test' );
assert_true( 8 === $report['counts']['updated'], 'first run updates all eight' );
assert_true( 0 === $report['counts']['failed'], 'no failures' );
foreach ( $records as $rec ) {
    $post = get_posts( array( 'name' => $rec['slug'], 'post_type' => 'post' ) )[0];
    $faq = json_decode( get_post_meta( $post->ID, 'sa_faq', true ), true );
    assert_true( $faq === $rec['faq'], $rec['slug'] . ' FAQ written exactly' );
    assert_true( 'KEEP' === get_post_meta( $post->ID, 'untouched_meta', true ), $rec['slug'] . ' other meta untouched' );
    assert_true( 'DO NOT TOUCH' === $post->post_content, $rec['slug'] . ' content untouched' );
}

$report2 = SA_FAQ_Restore::apply_changes( 'test-again' );
assert_true( 8 === $report2['counts']['already_target'], 'second run idempotent' );

// Simulate the exact v1.0.0 defect: WordPress unslashing removed the
// backslash before quotes inside HTML links, leaving invalid JSON.
$broken_post = get_posts( array( 'name' => 'diy-hammock-trees', 'post_type' => 'post' ) )[0];
$broken_target = null;
foreach ( $records as $rec ) { if ( 'diy-hammock-trees' === $rec['slug'] ) $broken_target = $rec['faq']; }
$GLOBALS['meta'][ $broken_post->ID ]['sa_faq'] = str_replace( '\\"', '"', wp_json_encode( $broken_target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
assert_true( null === json_decode( get_post_meta( $broken_post->ID, 'sa_faq', true ), true ), 'broken v1.0.0 fixture is invalid JSON' );
$report_repair = SA_FAQ_Restore::apply_changes( 'test-repair' );
assert_true( 1 === $report_repair['counts']['repaired_broken'], 'broken v1.0.0 value is repaired' );
assert_true( json_decode( get_post_meta( $broken_post->ID, 'sa_faq', true ), true ) === $broken_target, 'repaired FAQ decodes exactly' );

// Owner FAQ must never be overwritten.
$victim = get_posts( array( 'name' => 'leg-fracture-first-aid', 'post_type' => 'post' ) )[0];
update_post_meta( $victim->ID, 'sa_faq', wp_json_encode( array( array( 'q' => 'پرسش مالک؟', 'a' => 'پاسخ مالک.' ) ), JSON_UNESCAPED_UNICODE ) );
$report3 = SA_FAQ_Restore::apply_changes( 'test-guard' );
assert_true( 1 === $report3['counts']['skipped_nonempty'], 'non-empty owner FAQ skipped' );
$vf = json_decode( get_post_meta( $victim->ID, 'sa_faq', true ), true );
assert_true( 'پرسش مالک؟' === $vf[0]['q'], 'owner FAQ survives' );

// Put the plugin target back, then rollback everything.
$target = null;
foreach ( $records as $rec ) { if ( 'leg-fracture-first-aid' === $rec['slug'] ) $target = $rec['faq']; }
update_post_meta( $victim->ID, 'sa_faq', wp_slash( wp_json_encode( $target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
$rb = SA_FAQ_Restore::rollback();
assert_true( 8 === $rb['counts']['restored'], 'rollback restores all eight' );
foreach ( $records as $rec ) {
    $post = get_posts( array( 'name' => $rec['slug'], 'post_type' => 'post' ) )[0];
    assert_true( '' === get_post_meta( $post->ID, 'sa_faq', true ), $rec['slug'] . ' FAQ removed by rollback' );
}

echo "PASS sa-faq-restore apply/idempotent/guard/rollback\n";
