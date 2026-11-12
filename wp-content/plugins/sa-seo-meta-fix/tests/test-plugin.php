<?php
define( 'ABSPATH', '/tmp/' );
error_reporting( E_ALL );
$GLOBALS['options'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['next_id'] = 100;

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
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function is_wp_error( $x ) { return false; }
function wp_update_post( $arr, $wp_error = false ) { $id = (int) $arr['ID']; if ( ! isset( $GLOBALS['posts'][ $id ] ) ) return 0; $GLOBALS['posts'][ $id ]->post_title = $arr['post_title']; return $id; }

require dirname(__DIR__) . '/sa-seo-meta-fix.php';

function assert_true( $cond, $msg ) { if ( ! $cond ) { fwrite( STDERR, "FAIL: $msg\n" ); exit( 1 ); } echo "ok - $msg\n"; }

$ref = new ReflectionMethod( 'SA_SEO_Meta_Fix', 'fixes' );
$ref->setAccessible( true );
$fixes = $ref->invoke( null );
assert_true( 6 === count( $fixes ), 'six records embedded' );
assert_true( 15 === array_sum( array_map( function( $x ) { return count( $x['fields'] ); }, $fixes ) ), 'fifteen fields embedded' );

// Fixtures exactly at the audit-observed values.
foreach ( $fixes as $fix ) {
    $id = $GLOBALS['next_id']++;
    $post = new stdClass();
    $post->ID = $id; $post->post_name = $fix['slug']; $post->post_type = 'post'; $post->post_status = 'publish';
    $post->post_title = 'TITLE'; $post->post_date = '2026-01-01 00:00:00'; $post->post_date_gmt = '2026-01-01 00:00:00'; $post->post_modified = '2026-01-02 00:00:00'; $post->post_modified_gmt = '2026-01-02 00:00:00'; $post->post_content = 'DO NOT TOUCH';
    $GLOBALS['meta'][ $id ] = array( 'untouched_meta' => 'KEEP' );
    foreach ( $fix['fields'] as $field ) {
        if ( 'post_title' === $field['type'] ) $post->post_title = $field['expected']; else $GLOBALS['meta'][ $id ][ $field['key'] ] = $field['expected'];
    }
    $GLOBALS['posts'][ $id ] = $post;
}

$report = SA_SEO_Meta_Fix::apply_changes( 'test' );
assert_true( 15 === $report['counts']['updated'], 'first run updates all 15 fields' );
assert_true( 0 === $report['counts']['failed'], 'first run has no failures' );
$backup = get_option( 'sa_seo_meta_fix_20261111_backup' );
assert_true( is_array( $backup ) && 6 === count( $backup ), 'backup captured for six records' );

foreach ( $fixes as $fix ) {
    $found = get_posts( array( 'name' => $fix['slug'], 'post_type' => 'post' ) );
    $post = $found[0];
    assert_true( 'DO NOT TOUCH' === $post->post_content, $fix['slug'] . ' content untouched' );
    assert_true( 'KEEP' === get_post_meta( $post->ID, 'untouched_meta', true ), $fix['slug'] . ' unrelated meta untouched' );
    foreach ( $fix['fields'] as $field ) {
        $value = 'post_title' === $field['type'] ? $post->post_title : get_post_meta( $post->ID, $field['key'], true );
        assert_true( $value === $field['target'], $fix['slug'] . ' target applied: ' . $field['label'] );
    }
}

$report2 = SA_SEO_Meta_Fix::apply_changes( 'test-again' );
assert_true( 15 === $report2['counts']['already_target'], 'second run is idempotent' );

// Owner edits one field after the audit: plugin must skip it.
$victim = get_posts( array( 'name' => 'diy-can-stove', 'post_type' => 'post' ) )[0];
update_post_meta( $victim->ID, 'sa_seo_description', 'مقدار تازهٔ مالک' );
$report3 = SA_SEO_Meta_Fix::apply_changes( 'test-guard' );
assert_true( 1 === $report3['counts']['skipped_changed'], 'changed-since-audit field is skipped' );
assert_true( 'مقدار تازهٔ مالک' === get_post_meta( $victim->ID, 'sa_seo_description', true ), 'owner value survives guarded run' );
// Restore it to plugin target to test rollback on all fields.
update_post_meta( $victim->ID, 'sa_seo_description', 'قوطی کنسرو را برای ساخت اجاق نسوزان؛ خطر لبهٔ تیز، پوشش ناشناخته، آتش و مونوکسیدکربن را بشناس و گزینهٔ امن‌تر کمپ را انتخاب کن.' );

$rb = SA_SEO_Meta_Fix::rollback();
assert_true( 15 === $rb['counts']['restored'], 'rollback restores all 15 fields' );
foreach ( $fixes as $fix ) {
    $post = get_posts( array( 'name' => $fix['slug'], 'post_type' => 'post' ) )[0];
    foreach ( $fix['fields'] as $field ) {
        $value = 'post_title' === $field['type'] ? $post->post_title : get_post_meta( $post->ID, $field['key'], true );
        assert_true( $value === $field['expected'], $fix['slug'] . ' rolled back: ' . $field['label'] );
    }
}

echo "PASS sa-seo-meta-fix apply/idempotent/guard/rollback\n";
