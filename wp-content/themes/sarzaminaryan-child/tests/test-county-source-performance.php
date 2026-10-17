<?php
/** County sources must never wait for DNS/HTTP while rendering a visitor page. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['sa_dns_validator_calls'] = 0;
$GLOBALS['sa_test_sources'] = array();
$GLOBALS['sa_county_page_parts'][42] = array( 'urls' => array() );
function add_action() {}
function add_filter() {}
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function esc_url_raw( $url, $protocols = array() ) { return $url; }
function sa_get_sources( $id ) { return $GLOBALS['sa_test_sources']; }
function wp_http_validate_url( $url ) {
	// Core's validator resolves external hosts. Model its latency without networking.
	++$GLOBALS['sa_dns_validator_calls'];
	usleep( 10000 );
	return $url;
}
function sa_perf_assert( $condition, $label ) {
	if ( ! $condition ) { throw new RuntimeException( $label ); }
}
require dirname( __DIR__ ) . '/inc/county-page.php';
for ( $i = 0; $i < 40; ++$i ) {
	$GLOBALS['sa_test_sources'][] = array( 'url' => 'https://source-' . $i . '.org/article', 'title' => 'منبع ' . $i );
}
$start = microtime( true );
$sources = sa_county_sources( 42 );
$elapsed = microtime( true ) - $start;
sa_perf_assert( 0 === $GLOBALS['sa_dns_validator_calls'], 'Page rendering must not call the DNS-resolving WordPress HTTP validator' );
sa_perf_assert( 10 === count( $sources ), 'The ten-source cap still applies' );

$invalid = array(
	'javascript:alert(1)', 'file:///etc/passwd', '//example.org/a',
	'http://localhost/a', 'http://server.local/a', 'http://server.internal/a',
	'http://127.0.0.1/a', 'http://10.1.1.1/a', 'http://169.254.169.254/a',
	'http://[::1]/a', 'http://2130706433/a', 'http://0x7f000001/a',
	'https://user:password@example.org/a', 'https://example.org:25/a',
	"https://example.org/a\r\nInjected: value", 'https://bad_host.org/a',
);
foreach ( $invalid as $url ) {
	sa_perf_assert( '' === sa_county_source_url( $url ), 'Reject malformed/local/credential URL: ' . $url );
}
foreach ( array( 'https://unesco.org/a', 'https://example.org:443/a', 'http://example.org:80/a', 'https://xn--e1afmkfd.xn--p1ai/a' ) as $url ) {
	sa_perf_assert( $url === sa_county_source_url( $url ), 'Keep syntactically valid public-domain source: ' . $url );
}
$GLOBALS['sa_test_sources'] = array(
	array( 'url' => 'https://google.com/maps', 'title' => 'map' ),
	array( 'url' => 'https://maps.google.com/a', 'title' => 'map' ),
	array( 'url' => 'https://fa.wikipedia.org/wiki/Dorud', 'title' => 'wiki' ),
	array( 'url' => 'https://en.wikipedia.org/wiki/Dorud', 'title' => 'wiki2' ),
	array( 'url' => 'https://unesco.org/a#one', 'title' => 'official' ),
	array( 'url' => 'https://unesco.org/a#two', 'title' => 'official duplicate' ),
);
$sources = sa_county_sources( 42 );
sa_perf_assert( 2 === count( $sources ), 'Google exclusion, one Wikipedia and fragment deduplication preserved' );
sa_perf_assert( 'https://unesco.org/a' === $sources[0]['url'], 'Official-source ordering preserved' );
sa_perf_assert( 0 === $GLOBALS['sa_dns_validator_calls'], 'No hidden DNS validator calls on repeated render' );
printf( "PASS: county source rendering, zero DNS/HTTP validation calls; 40 candidates in %.2f ms (local synthetic fixture, not live-site timing)\n", $elapsed * 1000 );
