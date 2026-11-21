<?php
// Run only against the disposable seeded WP/SQLite fixture, never a live DB.
if ( PHP_SAPI !== 'cli' || ! is_file( getcwd() . '/setup.php' ) || ! is_file( getcwd() . '/wp-content/database/.ht.sqlite' ) ) { exit( 1 ); }
require getcwd() . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
function sald_check( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } }
$r = activate_plugin( 'sa-link-desk/sa-link-desk.php' );
sald_check( ! is_wp_error( $r ), 'activation' );
$wp_the_query = $wp_query = new WP_Query( array( 'post_type' => 'city', 'p' => 323 ) );
$wp_query->the_post();
$existing = get_page_by_path( 'ejrud', OBJECT, 'city' );
$id = $existing ? $existing->ID : wp_insert_post( array( 'post_type' => 'city', 'post_title' => 'ایجرود', 'post_name' => 'ejrud', 'post_status' => 'publish' ) );
sald_check( !! $id, 'test destination' );
$html = '<a href="/city/ijrud/">old</a><a href="/city/ejrud/">again</a><a href="/city/shiraz/">self</a><a href="/city/ejrud/?x=1">query</a><a href="#source">ref</a><a href="https://other.example/city/ijrud/">external</a>';
sald_check( $html === SA\LinkDesk\repair_prose( $html ), 'audit-only default' );
update_option( 'sa_link_desk', array( 'geo' => true, 'wiki' => true ) );
$out = SA\LinkDesk\repair_prose( $html );
sald_check( strpos( $out, get_permalink( $id ) ) !== false && substr_count( $out, 'href=' ) === 4 && preg_match( '~<a\s*>again</a><a\s*>self</a>~', $out ), 'canonical + duplicate + self' );
sald_check( strpos( $out, '/?x=1' ) !== false && strpos( $out, '#source' ) !== false && strpos( $out, 'https://other.example' ) !== false, 'references and other sites untouched' );
$wiki = SA\LinkDesk\unlink_wiki( '<a href="//fa.wikipedia.org/wiki/X"><em>منبع</em></a><a href="https://wikipedia.org.example/">other</a><script type="application/ld+json">"https://wikipedia.org"</script>' );
sald_check( preg_match( '~<a\s*><em>منبع</em></a>~', $wiki ) && strpos( $wiki, 'href="https://wikipedia.org.example/' ) !== false && strpos( $wiki, '"https://wikipedia.org"' ) !== false, 'Wikipedia only; preserve source label and schema' );
echo "OK: activation, passive default, canonical and scoped duplicate repair, reference preservation, Wikipedia domain matching.\n";
