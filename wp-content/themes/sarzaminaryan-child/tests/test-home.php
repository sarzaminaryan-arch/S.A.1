<?php
/** Real homepage template rendering: layout, links, counts, empty archives, escaping. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_CHILD_URI', '/wp-content/themes/sarzaminaryan-child/' );
define( 'SA_CHILD_VERSION', 'test' );
$GLOBALS['sa_home_live'] = true;
$GLOBALS['sa_home_mods'] = array();
$GLOBALS['sa_home_assets'] = array();
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_url( $v ) { return esc_html( $v ); }
function wp_enqueue_style( ...$args ) { $GLOBALS['sa_home_assets']['css'] = $args; }
function wp_enqueue_script( ...$args ) { $GLOBALS['sa_home_assets']['js'] = $args; }
function get_header() {}
function get_footer() {}
function get_theme_mod( $key, $default = '' ) { return $GLOBALS['sa_home_mods'][ $key ] ?? $default; }
function bloginfo( $key ) { echo esc_html( 'سرزمین آریان' ); }
function home_url( $path ) { return '/' . ltrim( $path, '/' ); }
function wp_rand( $min, $max ) { return $min; }
function sa_iran_provinces() { return array( array( 'آذربایجان شرقی', 'east-azerbaijan', 69, 43 ), array( 'تهران', 'tehran', 149, 95 ), array( 'ناموجود', 'missing', 300, 200 ) ); }
function sa_published_entity_ids( $type ) { return $GLOBALS['sa_home_live'] ? array( 'east-azerbaijan' => 240, 'tehran' => 241 ) : array(); }
function get_permalink( $id ) { return '/province/' . (int) $id . '/'; }
function sa_entity_counts() { return $GLOBALS['sa_home_live'] ? array( 'province' => 31, 'city' => 483, 'attraction' => 12345 ) : array(); }
function sa_nav_entity_types() { return $GLOBALS['sa_home_live'] ? array( 'province', 'city', 'attraction' ) : array(); }
function sa_archive_url( $type ) { return '/' . $type . '/'; }
function sa_education_nav_item() { return $GLOBALS['sa_home_live'] ? array( 'count' => 67, 'url' => '/category/camping-survival/', 'label' => 'آموزش‌ها' ) : null; }
function sa_dashboard_page_url() { return $GLOBALS['sa_home_live'] ? '/dashboard/' : ''; }
function sa_fa_digits( $v ) { return strtr( (string) $v, array_combine( range( 0, 9 ), array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹' ) ) ); }
class CC_Rating {
 public static function top( $limit ) {
  if ( ! $GLOBALS['sa_home_live'] ) { return array(); }
  $titles = array( 'تبریز', 'شیراز', 'اصفهان', 'رشت', 'کرمان', 'یزد', 'دورود' );
  $out = array();
  foreach ( array_slice( $titles, 0, $limit ) as $i => $title ) {
   $out[] = array( 'rank' => $i + 1, 'url' => '/city/sample-' . $i . '/', 'title' => $title, 'province' => 'استان نمونه', 'total' => 150 - $i * 10, 'avg' => 4.5 );
  }
  return $out;
 }
}
require dirname( __DIR__ ) . '/inc/city-contrib/includes/class-cc-ui.php';
function sa_home_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function sa_home_render() { ob_start(); include dirname( __DIR__ ) . '/template-home.php'; return ob_get_clean(); }
function sa_home_xpath( $html ) {
 libxml_use_internal_errors( true );
 $doc = new DOMDocument(); $doc->loadHTML( '<?xml encoding="UTF-8">' . $html ); libxml_clear_errors();
 return new DOMXPath( $doc );
}
$html = sa_home_render();
$preview = $html;
$x = sa_home_xpath( $html );
sa_home_assert( 1 === $x->query( '//main[@id="sa-home"]' )->length, 'One main landmark' );
sa_home_assert( 0 === $x->query( '//main//img' )->length, 'No logo in hero or ranking' );
sa_home_assert( 0 === strpos( $GLOBALS['sa_home_assets']['css'][1], SA_CHILD_URI ), 'Local CSS, no font/network dependency' );
sa_home_assert( array( 'sarzaminaryan-child' ) === $GLOBALS['sa_home_assets']['css'][2], 'Home CSS loads after inherited child styles' );
sa_home_assert( true === $GLOBALS['sa_home_assets']['js'][4], 'Local script enqueued in footer' );
sa_home_assert( false === strpos( $html, 'سایبری' ) && false === strpos( $html, 'CYBER MAP' ), 'Dashboard wording has no cyber label' );
sa_home_assert( strpos( $html, 'class="sa-search"' ) < strpos( $html, 'class="sa-dash-cta"' ) && strpos( $html, 'class="sa-dash-cta"' ) < strpos( $html, 'id="sa-stats"' ) && strpos( $html, 'id="sa-stats"' ) < strpos( $html, 'id="sa-topcities"' ), 'Search, dashboard, second-screen counters, final ranking order' );
sa_home_assert( 1 === $x->query( '//form[@role="search" and @method="get" and @action="/"]/input[@name="s" and @type="search"]' )->length, 'Real WordPress GET search retained' );
$links = $x->query( '//section[@id="sa-stats"]//a[@class="sa-st"]' );
sa_home_assert( 4 === $links->length, 'Four whole-circle links' );
$urls = array( '/province/', '/city/', '/attraction/', '/category/camping-survival/' );
foreach ( $links as $i => $link ) { sa_home_assert( $urls[ $i ] === $link->getAttribute( 'href' ), 'Each orb links to its own main section' ); }
sa_home_assert( 16 === $x->query( '//span[@class="sa-orbits"]//i' )->length, 'Four fine-line orbit rings per globe' );
$numbers = $x->query( '//section[@id="sa-stats"]//b' );
foreach ( array( '31', '483', '12,345', '67' ) as $i => $number ) { sa_home_assert( $number === $numbers->item( $i )->textContent, 'English server-rendered counter digits' ); }
sa_home_assert( 'sa-st__number--compact' === $numbers->item( 2 )->getAttribute( 'class' ), 'Long numbers fit narrow mobile circles' );
sa_home_assert( 7 === $x->query( '//table//tbody/tr' )->length && 7 === $x->query( '//span[@class="cc-city-beacon" and @aria-hidden="true"]' )->length, 'Only genuine ranked rows, each with decorative beacon' );
sa_home_assert( false !== strpos( $html, 'هفت اقلیم برتر ایران از نگاه مخاطبان' ), 'Exact requested heading' );
sa_home_assert( 0 === $x->query( '//section[@id="sa-attractions" or @id="sa-education"]' )->length, 'Article feed sections removed' );
$source = file_get_contents( dirname( __DIR__ ) . '/template-home.php' );
sa_home_assert( ! preg_match( '/new WP_Query|sa_education_latest_query|sa_attraction_bars_html|sa-posts/', $source ), 'Removed feeds are not queried or hidden with CSS' );
sa_home_assert( strpos( $source, 'CC_UI::top_cities_block' ) > strrpos( $source, '</section>' ), 'Ranking remains final content section' );
$css = file_get_contents( dirname( __DIR__ ) . '/assets/css/home.css' );
sa_home_assert( false !== strpos( $css, 'grid-template-columns:repeat(4,minmax(0,1fr))' ) && false === strpos( $css, 'repeat(2,' ), 'Four-column grid never collapses on mobile' );
sa_home_assert( false !== strpos( $css, 'aspect-ratio:1' ) && false !== strpos( $css, 'border-radius:50%' ), 'True circular globes' );
sa_home_assert( false !== strpos( $css, '.sa-home .sa-search{width:100%;max-width:none' ) && false !== strpos( $css, 'padding-inline:clamp(16px,3vw,36px)' ), 'Full-width search with safe side gutters' );
sa_home_assert( false !== strpos( $css, 'prefers-reduced-motion:reduce' ), 'Reduced-motion CSS fallback' );
sa_home_assert( false !== strpos( $css, 'min-height:0;' ) && false === strpos( $css, '100vh - 82px' ) && false === strpos( $css, '100svh - 82px' ), 'Desktop hero follows content, not viewport height' );
sa_home_assert( false !== strpos( $css, '@media(min-width:701px)' ) && false !== strpos( $css, '.sa-home .sa-stats{padding:16px 0 24px}' ) && false !== strpos( $css, '.sa-home .sa-topcities{padding-top:8px}' ), 'Desktop counters and ranking use compact standard gaps' );
sa_home_assert( false !== strpos( $css, '#315587 0,#172f57 26%,#0b1c38 60%,#060e22 100%' ) && false !== strpos( $css, 'linear-gradient(135deg,#142f56,#081a35)' ), 'Globe and dashboard surfaces are navy' );
sa_home_assert( false === strpos( $css, 'sa-cursor' ) && false === strpos( $css, '.sa-st b::after' ), 'No typing cursor in counters at any breakpoint' );

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/home.js' );
sa_home_assert( false !== strpos( $js, "Intl.NumberFormat('en-US'" ) && false !== strpos( $js, 'prefers-reduced-motion: reduce' ), 'English animated digits and reduced-motion handling' );
sa_home_assert( ! preg_match( '/fetch\(|XMLHttpRequest|setInterval|\.sa-hero/', $js ), 'No AJAX counter queries, timers, or hero-based pausing of lower sections' );

// Overrides remain escaped; no stored title, label or suffix can inject HTML.
$GLOBALS['sa_home_mods'] = array( 'sa_stat1_label' => '<script>bad()</script>', 'sa_search_placeholder' => '\" onfocus=\"bad()', 'sa_stat2_suffix' => '<img src=x onerror=bad()>' );
$unsafe = sa_home_render(); $ux = sa_home_xpath( $unsafe );
sa_home_assert( 0 === $ux->query( '//script|//img|//*[@onfocus]|//*[@onerror]' )->length && false !== strpos( $unsafe, '&lt;script&gt;' ), 'Customizer text and suffix are escaped' );
$GLOBALS['sa_home_mods'] = array();
$GLOBALS['sa_home_live'] = false;
$empty = sa_home_render(); $ex = sa_home_xpath( $empty );
sa_home_assert( 4 === $ex->query( '//section[@id="sa-stats"]//div[@aria-disabled="true"]' )->length, 'Four circles even when archives are empty' );
sa_home_assert( 1 === $ex->query( '//main//a|//table//tbody/tr' )->length, 'Only the scroll anchor remains: no empty archive or fabricated ranking links' );
sa_home_assert( 4 === $ex->query( '//section[@id="sa-stats"]//b[@data-n="0"]' )->length, 'No fabricated count fallback' );
if ( defined( 'SA_HOME_PREVIEW' ) ) { echo $preview; } else { echo "PASS: homepage layout, four linked globes, English counts, ranking, escaping and empty-archive guards\n"; }
