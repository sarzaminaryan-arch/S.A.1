<?php
/** Run: php wp-content/themes/sarzaminaryan-child/tests/test-article-sections.php */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['sa_test_admin'] = false;
$GLOBALS['sa_test_type']  = 'city';
function add_filter() {}
function add_action() {}
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function is_admin() { return $GLOBALS['sa_test_admin']; }
function is_singular() { return true; }
function get_post_type() { return $GLOBALS['sa_test_type']; }
function get_the_ID() { return 7; }
function get_permalink() { return 'https://example.test/city/kashmar/'; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function is_feed() { return false; }
function wp_doing_ajax() { return false; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_post_meta() { return ''; }
function get_theme_mod( $key, $default = false ) { return $default; }
function apply_filters( $tag, $value ) { return $value; }
function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function sa_fa_digits( $text ) { return strtr( (string) $text, array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) ); }
function sa_entity_types() { return array( 'city', 'province', 'attraction', 'travel_route', 'local_food', 'souvenir' ); }
require dirname( __DIR__ ) . '/inc/article-sections.php';
require dirname( __DIR__ ) . '/inc/toc.php';
function sa_test_section( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $message );
	}
}
$keep = '<h2 id="keep">تاریخ و دیدنی‌ها</h2><p data-test="a > b">متن <strong>دست‌نخورده</strong>.</p>';
$titles = array( 'مشخصات کلی', 'موقعیت جغرافیایی', 'نقشه و لوکیشن', 'مردم این دیار', 'شهرستان‌های استان', 'شهرستان های استان خراسان رضوی', '۴۸۰ شهرستان استان خراسان رضوی', 'از مردم این دیار، یک درخواست داریم', 'از مردم عزیز شهرستان کاشمر، یک درخواست داریم', '۲. موقعیت جغرافیایی شهرستان کاشمر:' );
foreach ( $titles as $title ) {
	$deleted = '<h2 id="gone"><strong>' . $title . '</strong></h2><p>DELETE_BODY</p><h3 id="child">زیرعنوان</h3><ul><li>DELETE_CHILD</li></ul>';
	$input = '<p>مقدمه</p>' . $deleted . $keep;
	$expected = '<p>مقدمه</p>' . $keep;
	$out = sa_remove_retired_article_sections( $input );
	sa_test_section( $expected === $out, 'flat section, descendants and next peer: ' . $title );
	$input = '<p>مقدمه</p><section class="retired">' . $deleted . '</section>' . $keep;
	sa_test_section( $expected === sa_remove_retired_article_sections( $input ), 'dedicated section wrapper removed: ' . $title );
	sa_test_section( $out === sa_remove_retired_article_sections( $out ), 'idempotent: ' . $title );
}
foreach ( array( 1, 2, 3, 4, 5, 6 ) as $level ) {
	$input = '<h' . $level . '>مشخصات کلی</h' . $level . '><p>DELETE</p><h' . $level . '>باقی</h' . $level . '><p>KEEP</p>';
	sa_test_section( '<h' . $level . '>باقی</h' . $level . '><p>KEEP</p>' === sa_remove_retired_article_sections( $input ), 'all heading levels: ' . $level );
}
$split = '<div class="group"><div class="heading"><h2>موقعیت جغرافیایی</h2></div><p>DELETE</p></div><div class="next">' . $keep . '</div>';
sa_test_section( '<div class="next">' . $keep . '</div>' === sa_remove_retired_article_sections( $split ), 'cross-wrapper deletion stays balanced and removes empty heading/group wrappers' );
$split = '<div><p>KEEP_INTRO</p><h2>مشخصات کلی</h2><p>DELETE</p></div><section>' . $keep . '</section>';
sa_test_section( '<div><p>KEEP_INTRO</p></div><section>' . $keep . '</section>' === sa_remove_retired_article_sections( $split ), 'partial wrapper keeps preceding text and balanced closing tag' );
$identity = '<section class="profile-box sa-cyber-id" id="county-identity"><h2>موقعیت جغرافیایی</h2><p>IDENTITY_KEEP</p><a href="#gone">KEEP_LINK</a></section>';
sa_test_section( $identity . $keep === sa_remove_retired_article_sections( '<h2 id="gone">مشخصات کلی</h2><p>DELETE</p>' . $identity . $keep ), 'identity boundary, markup and links are byte-identical' );
$unchanged = '<!-- wp:paragraph --><p>در موقعیت جغرافیایی این منطقه...</p><!-- /wp:paragraph --><table><tr><td>موقعیت جغرافیایی</td><td>KEEP</td></tr></table><h2>جغرافیا و اقلیم</h2><p>KEEP</p><pre><h2>مشخصات کلی</h2>EXAMPLE</pre><script>const x="<h2>مردم این دیار</h2>";</script>';
sa_test_section( $unchanged === sa_remove_retired_article_sections( $unchanged ), 'prose/table labels, different headings, comments, code and scripts untouched' );
$input = '<nav class="sa-toc"><ol><li><a href="#gone">مشخصات کلی</a></li><li><a href="#keep">تاریخ و دیدنی‌ها</a></li></ol></nav><p><a href="#gone"><b>ارجاع قدیمی</b></a> و <a href="https://external.test/#gone">بیرونی</a></p><h2 id="gone">مشخصات کلی</h2><p id="child">DELETE</p>' . $keep;
$out = sa_remove_retired_article_sections( $input );
sa_test_section( false === strpos( $out, 'href="#gone"' ) && false === strpos( $out, '<li>مشخصات کلی' ), 'manual TOC entries and newly broken fragments removed' );
sa_test_section( false !== strpos( $out, '<b>ارجاع قدیمی</b>' ) && false !== strpos( $out, 'href="https://external.test/#gone"' ), 'link text and unrelated external links preserved' );
sa_test_section( false !== strpos( $out, '<li><a href="#keep">تاریخ و دیدنی‌ها</a></li>' ), 'valid manual TOC entry preserved' );
$encoded = '<p><a href="#%67one">OLD</a></p><h2 id="gone">مشخصات کلی</h2><p>DELETE</p>' . $keep;
sa_test_section( '<p>OLD</p>' . $keep === sa_remove_retired_article_sections( $encoded ), 'percent-encoded fragment is cleaned' );
$counted = '<nav class="sa-toc"><span class="sa-toc__count">۲ سرفصل</span><ol><li><a href="#gone">مشخصات کلی</a></li><li><a href="#keep">تاریخ</a></li></ol></nav><h2 id="gone">مشخصات کلی</h2><p>DELETE</p>' . $keep;
sa_test_section( false !== strpos( sa_remove_retired_article_sections( $counted ), '>۱ سرفصل</span>' ), 'stored TOC count follows retained entries' );
$generated = '<nav class="sa-toc"><ol><li><a href="#sa-sec-مشخصات-کلی">مشخصات کلی</a></li><li><a href="#keep">تاریخ</a></li></ol></nav><h2><b>مشخصات کلی</b></h2><p>DELETE</p>' . $keep;
sa_test_section( false === strpos( sa_remove_retired_article_sections( $generated ), 'href="#sa-sec-مشخصات-کلی"' ), 'old generated anchor for a formatted heading is removed' );
$empty_toc = '<nav class="sa-toc"><h2>فهرست مطالب</h2><ol><li><a href="#gone">مشخصات کلی</a></li></ol></nav><h2 id="gone">مشخصات کلی</h2><p>DELETE</p>' . $keep;
sa_test_section( $keep === sa_remove_retired_article_sections( $empty_toc ), 'empty manual TOC and orphan heading removed' );
$absolute = '<p><a href="https://example.test/city/kashmar/#gone">ABS</a><a href="/city/kashmar/#gone">REL</a><a href="/city/other/#gone">OTHER</a></p><h2 id="gone">نقشه و لوکیشن</h2><p>DELETE</p>' . $keep;
$absolute_out = sa_remove_retired_article_sections( $absolute );
sa_test_section( '<p>ABSREL<a href="/city/other/#gone">OTHER</a></p>' . $keep === $absolute_out, 'absolute and relative self-fragments are unwrapped, other articles untouched' );
sa_toc_reset();
sa_toc_register( 'مشخصات کلی', 'gone', 1 );
sa_toc_register( 'شناسنامه شهرستان', 'county-identity', 5 );
sa_test_section( 1 === count( sa_toc_registered() ), 'retired template title cannot re-enter registry' );
$input = '<h2 id="gone">مشخصات کلی</h2><p>DELETE</p>' . $keep . '<h2 id="faq">پرسش‌ها</h2><p>KEEP_FAQ</p>';
$out = sa_toc_filter( sa_retired_article_sections_filter( $input ) );
sa_test_section( false === strpos( $out, 'مشخصات کلی' ) && false === strpos( $out, '#gone' ), 'removal precedes automatic TOC' );
sa_test_section( false !== strpos( $out, 'href="#keep"' ) && false !== strpos( $out, 'href="#faq"' ), 'retained automatic anchors work' );
$GLOBALS['sa_test_admin'] = true;
sa_test_section( $input === sa_retired_article_sections_filter( $input ), 'editor storage unchanged' );
$GLOBALS['sa_test_admin'] = false;
$GLOBALS['sa_test_type'] = 'page';
sa_test_section( $input === sa_retired_article_sections_filter( $input ), 'unrelated pages unchanged' );
$GLOBALS['sa_test_type'] = 'city';
// Scope regression: 480 independent synthetic article bodies, not a live-site count.
for ( $i = 1; $i <= 480; ++$i ) {
	$input = '<p>ARTICLE_' . $i . '</p>';
	foreach ( array_slice( $titles, 0, 5 ) as $title ) {
		$input .= '<h2>' . $title . '</h2><p>DELETE_' . $i . '</p>';
	}
	$expected = '<p>ARTICLE_' . $i . '</p>' . $keep;
	sa_test_section( $expected === sa_remove_retired_article_sections( $input . $keep ), 'all five removed, all other bytes preserved: synthetic article ' . $i );
}
$records = array();
foreach ( array( 'ready-articles.php', 'ready-guides.php' ) as $file ) {
	$set = require dirname( __DIR__ ) . '/data/' . $file;
	if ( isset( $set['articles'] ) ) { $set = $set['articles']; }
	foreach ( $set as $key => $record ) {
		if ( is_array( $record ) && isset( $record['content'] ) ) { $records[ $key ] = $record; }
	}
}
$changed = 0;
foreach ( $records as $key => $record ) {
	$body = $record['content'];
	$out = sa_remove_retired_article_sections( $body );
	$changed += (int) ( $out !== $body );
	sa_test_section( $out === sa_remove_retired_article_sections( $out ), 'bundled article idempotence: ' . $key );
}
ob_start();
include dirname( __DIR__ ) . '/template-parts/entity/county-siblings.php';
sa_test_section( '' === ob_get_clean(), 'legacy county template entry point emits nothing' );
$single = file_get_contents( dirname( __DIR__ ) . '/template-parts/entity/single.php' );
sa_test_section( false === strpos( $single, "get_template_part( 'template-parts/entity/county-siblings' )" ), 'county template no longer calls retired block' );
echo 'PASS: section removal, boundaries, unchanged identity, TOC, anchors, 480 synthetic articles; bundled records=' . count( $records ) . ', changed=' . $changed . PHP_EOL;
