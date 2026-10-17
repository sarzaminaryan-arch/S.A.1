<?php
/** Standalone county presentation / sources / five-star regression tests (DOM required). */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['context'] = true;
$GLOBALS['meta_sources'] = array();
function add_filter() {}
function add_action() {}
function is_admin() { return false; }
function is_feed() { return false; }
function is_singular( $type = '' ) { return $GLOBALS['context']; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_the_ID() { return 42; }
function get_queried_object_id() { return 42; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_parse_url( $url, $part = -1 ) { return parse_url( $url, $part ); }
function home_url() { return 'https://sarzaminaryan.ir'; }
function get_post_field() { return ''; }
function sa_get_sources() { return $GLOBALS['meta_sources']; }
function sa_county_profile_data() { return array( 'center_fa' => 'دورود', 'lat' => 33.49, 'lng' => 49.06, 'province_name' => 'لرستان' ); }
function sa_county_profile_province_post() { return (object) array( 'ID' => 9 ); }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function wp_http_validate_url( $url ) {
	$host = parse_url( $url, PHP_URL_HOST );
	return in_array( parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) && ! filter_var( $host, FILTER_VALIDATE_IP ) && ! in_array( $host, array( 'localhost', '' ), true );
}
function __( $s ) { return $s; }
function sa_cards_section( $posts, $heading ) { $GLOBALS['cards_heading'] = $heading; }
class WP_Query { public $posts = array(); public function __construct( $args ) { $GLOBALS['query_args'] = $args; } }
require dirname( __DIR__ ) . '/inc/citations.php';
require dirname( __DIR__ ) . '/inc/county-page.php';
require dirname( __DIR__ ) . '/inc/city-contrib/includes/class-cc-rating.php';
function sa_check( $condition, $label ) { if ( ! $condition ) { throw new Exception( $label ); } }
sa_check( class_exists( 'DOMDocument' ), 'DOM extension required for this test' );
$html = '<p>متن [1] و [۲]؛ توان <sup>2</sup><code>[3]</code><a href="/x?q=[4]">پیوند</a></p>'
	. '<h2>مشخصات کلی شهرستان دورود</h2><table><tr><td>مرکز شهرستان</td><td>دورود</td></tr></table>'
	. '<h2>بخش دیگر</h2><p>نباید حذف شود.</p>'
	. '<h2>حقایق جالب</h2><p>حقیقت [۵]</p><h2>سوالات متداول</h2><h3>چگونه برویم؟</h3><p>پاسخ</p>'
	. '<h2>از مردم عزیز شهرستان یک درخواست داریم</h2><p>درخواست تکراری</p>'
	. '<h2>منابع</h2><ul><li><a href="https://fa.wikipedia.org/wiki/Dorud">ویکی</a></li></ul>'
	. '<h2>پایان مستقل</h2><p>باقی بماند</p><sup class="sa-cite"><a href="https://unesco.org/a">۱</a></sup>';
$parts = sa_county_split_content( $html );
sa_check( strpos( $parts['general'], '<table>' ) !== false, 'general info preserved for profile' );
sa_check( strpos( $parts['content'], 'مشخصات کلی' ) === false, 'general heading removed from body' );
sa_check( strpos( $parts['content'], 'نباید حذف شود' ) !== false && strpos( $parts['content'], 'باقی بماند' ) !== false, 'unrelated sections survive' );
sa_check( strpos( $parts['content'], 'درخواست تکراری' ) === false, 'solicitation removed without removing following sources' );
sa_check( strpos( $parts['facts'], 'حقیقت' ) !== false && strpos( $parts['faq'], 'چگونه' ) !== false, 'facts and FAQ moved' );
sa_check( strpos( $parts['facts'], '[۵]' ) === false, 'moved citations removed' );
sa_check( strpos( $parts['content'], '[1]' ) === false && strpos( $parts['content'], '[۲]' ) === false, 'numeric text references removed' );
sa_check( strpos( $parts['content'], '<sup>2</sup>' ) !== false && strpos( $parts['content'], '<code>[3]</code>' ) !== false && strpos( rawurldecode( $parts['content'] ), 'q=[4]' ) !== false, 'exponents, code and attributes preserved' );
sa_check( count( $parts['urls'] ) === 2, 'all URLs collected before removal' );
$duplicate = sa_county_split_content( '<table><tr><td>مرکز شهرستان</td><td>دورود</td></tr></table><table><tr><td>مرکز شهرستان</td><td>بروجرد</td></tr></table>' );
sa_check( substr_count( $duplicate['content'], '<table>' ) === 1 && strpos( $duplicate['content'], 'بروجرد' ) !== false, 'only proven exact duplicate tables removed' );
$GLOBALS['sa_county_page_parts'][42] = $parts;
$GLOBALS['meta_sources'] = array(
	array( 'url' => 'https://google.com/maps', 'title' => 'google' ),
	array( 'url' => 'https://maps.google.com/', 'title' => 'google subdomain' ),
	array( 'url' => 'javascript:alert(1)', 'title' => 'invalid' ),
	array( 'url' => 'http://127.0.0.1/x', 'title' => 'private' ),
	array( 'url' => 'https://en.wikipedia.org/wiki/Dorud', 'title' => 'wiki2' ),
	array( 'url' => 'https://unesco.org/a#fragment', 'title' => 'official' ),
);
for ( $i = 0; $i < 15; ++$i ) { $GLOBALS['meta_sources'][] = array( 'url' => 'https://example.org/' . $i, 'title' => 'real candidate' ); }
$sources = sa_county_sources( 42 );
sa_check( count( $sources ) === 10, 'maximum ten sources' );
sa_check( $sources[0]['url'] === 'https://unesco.org/a', 'official source first and fragment deduplicated' );
sa_check( count( array_filter( $sources, function ( $s ) { return strpos( $s['url'], 'wikipedia.org' ) !== false; } ) ) <= 1, 'at most one Wikipedia' );
sa_check( ! array_filter( $sources, function ( $s ) { return strpos( $s['url'], 'google.com' ) !== false; } ), 'Google excluded' );
$GLOBALS['meta_sources'] = array();
$GLOBALS['sa_county_page_parts'][42]['urls'] = array( array( 'url' => 'https://unesco.org/only', 'title' => 'only real source' ) );
sa_check( count( sa_county_sources( 42 ) ) === 1, 'no invented sources to meet minimum' );
$GLOBALS['context'] = false;
sa_check( sa_county_content_filter( $html ) === $html, 'non-county context untouched' );
$GLOBALS['context'] = true;
sa_check( sa_citations_filter( 'county prose' ) === 'county prose', 'no duplicate automatic footnote section' );
for ( $i = 1; $i <= 5; ++$i ) { sa_check( CC_Rating::display_stars( CC_Rating::storage_stars( $i ) ) === $i, 'five-star round trip ' . $i ); }
foreach ( range( 1, 7 ) as $legacy ) { sa_check( CC_Rating::display_stars( $legacy ) >= 1 && CC_Rating::display_stars( $legacy ) <= 5, 'legacy rating remains bounded' ); }
sa_county_related_articles( 42 );
sa_check( $GLOBALS['query_args']['orderby'] === 'rand' && $GLOBALS['query_args']['meta_query'][0][0]['value'] === 9 && $GLOBALS['query_args']['meta_query'][1][1]['compare'] === '!=', 'random query excludes current province' );
sa_check( $GLOBALS['cards_heading'] === 'مقالات مرتبط', 'exact related heading' );
$template = file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/single.php' );
$city_tail = substr( $template, strpos( $template, '$sa_county_parts =' ), strpos( $template, '<?php else : ?>', strpos( $template, '$sa_county_parts =' ) ) - strpos( $template, '$sa_county_parts =' ) );
sa_check( strpos( $city_tail, "['facts']" ) < strpos( $city_tail, "['faq']" ) && strpos( $city_tail, 'CC_UI::contrib_block' ) < strpos( $city_tail, 'sa_county_related_articles' ) && strpos( $city_tail, 'sa_county_related_articles' ) < strpos( $city_tail, 'sa_entity_navigation' ) && strpos( $city_tail, 'sa_entity_navigation' ) < strpos( $city_tail, 'sa_county_sources_section' ), 'county tail order' );
echo "PASS: county extraction, references, sources, duplicate tables, scope, rating scale, related query and tail order\n";

/* Exercise aggregate read and REST write with a fake DB; no WP/database required. */
function get_site_transient() { return array( 42 ); }
function set_site_transient() {}
function delete_site_transient() {}
function wp_count_posts() { return (object) array( 'publish' => 1 ); }
function get_post_type() { return 'city'; }
function get_post_status() { return $GLOBALS['target_status'] ?? 'publish'; }
function absint( $value ) { return abs( (int) $value ); }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function current_time() { return date( 'Y-m-d H:i:s' ); }
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function wp_unslash( $s ) { return stripslashes( $s ); }
function wp_verify_nonce( $s ) { return 'valid' === $s; }
class WP_Error { public $code; public function __construct( $code, $message, $data ) { $this->code = $code; } }
class CC_DB { public static function table() { return 'IR_cc_ratings'; } public static function recalc() {} }
class SA_Test_DB {
	public $last_error = '';
	public $inserted;
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_results() { return array( (object) array( 'stars' => 7, 'n' => 2 ), (object) array( 'stars' => 1, 'n' => 1 ) ); }
	public function get_row() { return null; }
	public function insert( $table, $values, $formats ) { $this->inserted = $values; return 1; }
}
$GLOBALS['wpdb'] = new SA_Test_DB();
$rating = CC_Rating::get( 42 );
sa_check( $rating['average'] === 3.7 && $rating['sum'] === 11 && $rating['count'] === 3 && count( $rating['distribution'] ) === 5, 'legacy aggregate normalized per vote, not stale seven-point meta' );
require dirname( __DIR__ ) . '/inc/city-contrib/includes/class-cc-rest.php';
class SA_Test_Request extends ArrayObject { public function get_param( $key ) { return $this[$key] ?? null; } }
$_SERVER['HTTP_X_WP_NONCE'] = 'invalid';
sa_check( ! CC_REST::rating_permission(), 'invalid rating nonce rejected' );
$_SERVER['HTTP_X_WP_NONCE'] = 'valid';
sa_check( CC_REST::rating_permission(), 'valid nonce accepted' );
$bad = CC_REST::post_rating( new SA_Test_Request( array( 'id' => 42, 'stars' => 6 ) ) );
sa_check( $bad instanceof WP_Error && $bad->code === 'invalid_stars', 'six-star input rejected' );
$bad = CC_REST::post_rating( new SA_Test_Request( array( 'id' => 42, 'stars' => array( 5 ) ) ) );
sa_check( $bad instanceof WP_Error && $bad->code === 'invalid_stars', 'array rating rejected' );
$good = CC_REST::post_rating( new SA_Test_Request( array( 'id' => 42, 'stars' => 5 ) ) );
sa_check( is_array( $good ) && $good['success'] && $GLOBALS['wpdb']->inserted['stars'] === 7, 'five-star input stored with reversible encoding' );
$GLOBALS['target_status'] = 'draft';
$bad = CC_REST::post_rating( new SA_Test_Request( array( 'id' => 42, 'stars' => 5 ) ) );
sa_check( $bad instanceof WP_Error && $bad->code === 'invalid_city', 'draft county cannot be rated' );
echo "PASS: five-star aggregation, REST bounds, nonce and published-target permission\n";
