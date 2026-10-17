<?php
/** Administrator-only snapshots must never alter public content or leak logs. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_CHILD_VERSION', 'test-version' );
$GLOBALS['admin'] = false;
$GLOBALS['cap'] = false;
$GLOBALS['city'] = true;
$GLOBALS['feed'] = false;
$GLOBALS['saved'] = array();
$GLOBALS['wpdb'] = (object) array( 'num_queries' => 10 );
function add_action() {}
function add_filter() {}
function is_admin() { return $GLOBALS['admin']; }
function is_feed() { return $GLOBALS['feed']; }
function is_singular( $type ) { return 'city' === $type && $GLOBALS['city']; }
function current_user_can( $cap ) { return 'manage_options' === $cap && $GLOBALS['cap']; }
function get_current_user_id() { return 7; }
function get_queried_object_id() { return 42; }
function sa_county_page_context() { return $GLOBALS['city']; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['saved'][ $key ] = array( $value, $ttl ); }
function get_transient( $key ) { return $GLOBALS['saved'][ $key ][0] ?? false; }
function esc_html__( $s, $domain ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_textarea( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function wp_die( $s ) { throw new RuntimeException( 'permission denied' ); }
function wp_json_encode( $data, $flags ) { return json_encode( $data, $flags ); }
function add_management_page( ...$args ) { $GLOBALS['menu'] = $args; }
function sa_perf_assert( $ok, $label ) { if ( ! $ok ) { throw new RuntimeException( $label ); } }
require dirname( __DIR__ ) . '/inc/county-performance.php';

ob_start();
sa_county_performance_start();
sa_county_performance_template();
sa_county_timing( 'related_total', microtime( true ) - 0.01 );
$content = '<article><h1>تبریز</h1>متن بدون هیچ یادداشت مدیریتی</article>';
sa_perf_assert( $content === sa_county_performance_content_end( sa_county_performance_content_start( $content ) ), 'Guest content passes unchanged' );
sa_county_performance_finish();
sa_perf_assert( '' === ob_get_clean() && empty( $GLOBALS['saved'] ) && empty( $GLOBALS['sa_county_performance'] ), 'Anonymous requests have no output, data recording or transient writes' );
$denied = false;
try { sa_county_performance_page(); } catch ( RuntimeException $e ) { $denied = true; }
sa_perf_assert( $denied, 'Direct report callback refuses guests/non-admin roles' );
sa_county_performance_menu();
sa_perf_assert( 'manage_options' === $GLOBALS['menu'][2], 'Tools menu is capability-protected' );

$GLOBALS['cap'] = true;
foreach ( array( 'admin', 'feed' ) as $flag ) {
	$GLOBALS[ $flag ] = true;
	sa_county_performance_start();
	sa_perf_assert( empty( $GLOBALS['sa_county_performance'] ), 'No snapshot for ' . $flag );
	$GLOBALS[ $flag ] = false;
}
$GLOBALS['city'] = false;
sa_county_performance_start();
sa_perf_assert( empty( $GLOBALS['sa_county_performance'] ), 'Province/other pages do not get recorded' );
$GLOBALS['city'] = true;
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 0.1;
sa_county_performance_start();
sa_county_performance_template();
sa_county_timing( 'related_total', microtime( true ) - 0.01 );
sa_perf_assert( $content === sa_county_performance_content_end( sa_county_performance_content_start( $content ) ), 'Administrator content passes unchanged too' );
// Recursive the_content calls must not truncate the outer content measurement.
sa_county_performance_content_start( $content );
sa_county_performance_content_start( $content );
sa_county_performance_content_end( $content );
sa_perf_assert( count( $GLOBALS['sa_county_performance_content_stack'] ) === 1, 'Nested filters keep the outer timing open' );
sa_county_performance_content_end( $content );
sa_perf_assert( ! isset( $GLOBALS['sa_county_performance_content_stack'] ), 'Nested timing stack is released' );
$GLOBALS['wpdb']->num_queries = 22;
ob_start();
sa_county_performance_finish();
sa_perf_assert( '' === ob_get_clean(), 'Snapshot persistence does not echo into an article' );
sa_perf_assert( array( 'sa_county_perf_7' ) === array_keys( $GLOBALS['saved'] ), 'Snapshot belongs only to the current administrator' );
list( $report, $ttl ) = $GLOBALS['saved']['sa_county_perf_7'];
sa_perf_assert( 900 === $ttl && 42 === $report['county_id'] && 'test-version' === $report['theme_version'], 'Short TTL, county and actual loaded theme version' );
sa_perf_assert( $report['php_to_shutdown_ms'] >= 100 && $report['db_queries_after_wp'] === 12, 'Request timing and query count deltas recorded' );
sa_perf_assert( 2 === $report['calls']['content_filters'] && $report['timings_ms']['related_total'] >= 10, 'Stage timings captured privately' );
sa_perf_assert( ! isset( $report['sql'] ) && ! isset( $report['cookies'] ) && ! isset( $report['request_uri'] ), 'No query text, cookies, nonces, request parameters or credentials' );
$GLOBALS['saved']['sa_county_perf_7'][0]['theme_version'] = '<script>alert(1)</script>';
ob_start(); sa_county_performance_page(); $html = ob_get_clean();
sa_perf_assert( false === strpos( $html, '<script>' ) && false !== strpos( $html, '&lt;script&gt;' ), 'Report output is escaped' );
sa_perf_assert( false !== strpos( $html, 'readonly' ), 'Private report is read-only' );
sa_perf_assert( empty( $GLOBALS['sa_county_performance'] ), 'Request buffer released after snapshot' );
define( 'REST_REQUEST', true );
sa_county_performance_start();
sa_perf_assert( empty( $GLOBALS['sa_county_performance'] ), 'No REST timing snapshots' );
echo "PASS: county performance privacy, capability gates, TTL, stage timing and unchanged article output\n";
