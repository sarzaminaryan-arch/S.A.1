<?php
/** Display-only editorial cleanup; standalone tests, no WP/database/network required. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['admin'] = false;
$GLOBALS['meta'] = array();
$GLOBALS['hooks'] = array();
function add_filter( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
function add_action() {}
function is_admin() { return $GLOBALS['admin']; }
function is_feed() { return false; }
function get_theme_mod( $key, $default = false ) { return $GLOBALS['mods'][ $key ] ?? $default; }
function apply_filters( $tag, $value ) { return $value; }
function is_front_page() { return false; }
function is_singular() { return true; }
function get_the_ID() { return 42; }
function get_queried_object_id() { return 42; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function get_the_modified_date( $format, $id = 0 ) { return '2026-10-17'; }
function absint( $id ) { return abs( (int) $id ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $s ) { return esc_attr( $s ); }
function esc_url( $s ) { return esc_attr( $s ); }
function esc_html__( $s, $domain = '' ) { return esc_html( $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_parse_url( $s, $part = -1 ) { return parse_url( $s, $part ); }
function get_post( $post ) { return is_object( $post ) ? $post : $GLOBALS['post']; }
function has_excerpt( $post ) { return ! empty( $post->post_excerpt ); }
function strip_shortcodes( $s ) { return $s; }
function wp_trim_words( $s, $words, $more ) { $parts = preg_split( '/\s+/u', trim( $s ) ); return implode( ' ', array_slice( $parts, 0, $words ) ) . ( count( $parts ) > $words ? $more : '' ); }
function get_template_part( $slug, $name = null ) { echo $GLOBALS['article']; }
function sa_county_profile_data() { return array(); }
function current_user_can() { throw new Exception( 'Public source output must not branch on editor capability' ); }
function sa_test( $condition, $label ) { if ( ! $condition ) { throw new Exception( $label ); } }
require dirname( __DIR__ ) . '/inc/public-editorial.php';
require dirname( __DIR__ ) . '/inc/helpers.php';
require dirname( __DIR__ ) . '/inc/template-tags.php';
require dirname( __DIR__ ) . '/inc/county-page.php';
require dirname( __DIR__ ) . '/inc/seo.php';
require dirname( __DIR__ ) . '/inc/featured-images.php';
sa_test( class_exists( 'DOMDocument' ), 'DOM extension is required for structural tests' );

$notes = array(
	'✅ تأیید شده', '⚠️ نیازمند بررسی', '**نیازمند بازبینی**',
	'نیازمند بازبینی', 'نیازمند بررسی', '[منبع لازم]', '[نیاز به منبع]',
	'این متن پیش‌نویس هوش مصنوعی است.', 'منبع را اضافه کنید.', 'لینک را ثبت کنید.',
	'پیوند معتبر را درج کنید.', 'لینک به منبع معتبر افزوده شود.',
	'این داده هنوز با منبع معتبر تایید نشده است.', 'این اطلاعات تأیید شده است.',
	'راستی‌آزمایی‌شده: ۱۴۰۵/۰۷/۲۱', 'بازبینی: ۱۴۰۵/۰۷/۲۵',
	'گزارش راستی‌آزمایی: موفق', 'پس از تأیید مالک منتشر شود.',
	'منابع کافی نیست.', 'کمبود منابع وجود دارد.', 'تصویر این بخش در دست بازبینی است',
	'هنوز تصویری تأیید نشده؛',
	'AI-generated draft.', 'Editorial note: insert link here.', '[citation needed]',
	'Needs review.', 'Reviewed by the editorial team.', 'TODO: verify this text.',
);
foreach ( $notes as $note ) {
	sa_test( '' === trim( strip_tags( sa_public_editorial_html( '<p>' . $note . '</p>' ) ) ), 'remove note: ' . $note );
}
$prose = array(
	'پیش از حرکت وضعیت جاده را بررسی کنید.',
	'درد شدید نیازمند بررسی فوری پزشک است.',
	'یک منبع آب جایگزین به برنامه اضافه کنید.',
	'در نبود منبع مطمئن آب برنامه را عوض کنید.',
	'در منابع معتبر چیزی از شهر گم‌شده تأیید نشده و روایت‌های مربوط به آن، افسانه‌اند.',
	'هشدار: در زمان بارندگی خطر سیلاب وجود دارد.',
	'اگر کوله سنگین دارید، وزن و تقسیم بار را پیش از حرکت بازبینی کنید.',
	'دسترسی به خدمات را در همهٔ روزها قطعی فرض نکنید.',
	'ممکن است قیمت بلیت در فصل سفر تغییر کند.',
	'بدون مجوز ورود به منطقه ممنوع است.',
	'برای دریافت اطلاعات ایمنی با راهنمای محلی تماس بگیرید.',
);
foreach ( $prose as $text ) { sa_test( $text === sa_public_editorial_text( $text ), 'preserve safety: ' . $text ); }
$html = '<p>دورود در لرستان است [نیازمند بررسی]. <a href="https://unesco.org/a?q=[1]&amp;b=2">یونسکو</a> منبع سفر است. لینک را اضافه کنید. در بارندگی وارد رودخانه نشوید.</p>';
$clean = sa_public_editorial_html( $html );
sa_test( strpos( $clean, 'دورود در لرستان است' ) !== false && strpos( $clean, 'وارد رودخانه نشوید' ) !== false, 'mixed paragraph preserves factual and safety sentences' );
sa_test( strpos( $clean, 'نیازمند' ) === false && strpos( $clean, 'اضافه کنید' ) === false, 'mixed paragraph removes annotations and instructions' );
sa_test( strpos( rawurldecode( $clean ), 'q=[1]&amp;b=2' ) !== false && strpos( $clean, '>یونسکو</a>' ) !== false, 'valid linked source text and URL survive' );
$split = '<p>بهار فصل سفر است. این متن با <strong>هوش مصنوعی</strong> تهیه شده است. <em>در رودخانه شنا نکنید.</em></p>';
$clean = sa_public_editorial_html( $split );
sa_test( strpos( $clean, 'این متن' ) === false && strpos( $clean, 'تهیه شده' ) === false, 'entire editorial sentence removed across inline markup' );
sa_test( strpos( $clean, '<em>در رودخانه شنا نکنید.</em>' ) !== false && strpos( $clean, 'بهار فصل سفر است' ) !== false, 'adjacent prose/markup preserved' );
$credit = '<figcaption>عکس: علی رضایی — CC BY-SA 4.0</figcaption>';
sa_test( $credit === sa_public_editorial_html( $credit ), 'real photographer/licensing credits preserved byte-for-byte' );
$report = '<h2>گزارش راستی‌آزمایی</h2><p>همه چیز درست است.</p><h3>جزئیات داخلی</h3><p>جدول کنترل داخلی</p><h2>دسترسی</h2><p>با قطار سفر کنید.</p>';
$clean = sa_public_editorial_html( $report );
sa_test( strpos( $clean, 'همه چیز درست' ) === false && strpos( $clean, 'جزئیات داخلی' ) === false && strpos( $clean, 'با قطار سفر کنید' ) !== false, 'private report section removed at heading boundary' );
$fixture = '<span class="sa-pin" aria-hidden="true"></span><iframe src="https://www.google.com/maps?q=33,49"></iframe><input name="nonce" value="xyz"><p>نیازمند بررسی</p><pre><code>TODO: example</code></pre><script>const x = "AI draft";</script>';
$clean = sa_public_editorial_html( $fixture );
foreach ( array( 'class="sa-pin"', '<iframe', 'name="nonce" value="xyz"', '<code>TODO: example</code>', 'const x = "AI draft";' ) as $needle ) {
	sa_test( strpos( $clean, $needle ) !== false, 'preserve non-editorial markup/code: ' . $needle );
}
sa_test( strpos( sa_public_editorial_fallback( $fixture ), '<code>TODO: example</code>' ) !== false, 'DOM-less fallback preserves code' );
sa_test( strpos( sa_public_editorial_fallback( $fixture ), 'const x = "AI draft";' ) !== false, 'DOM-less fallback preserves scripts' );
sa_test( strpos( sa_public_editorial_fallback( $fixture ), 'نیازمند بررسی' ) === false, 'DOM-less fallback removes standalone note text' );
$safety_ui = '<span class="sa-flag sa-flag--danger">خطر سیلاب</span><p>نیازمند بررسی</p>';
sa_test( strpos( sa_public_editorial_html( $safety_ui ), 'خطر سیلاب' ) !== false, 'safety flag is not mistaken for a review badge' );
$attrs = sa_public_editorial_html( '<p title="نیازمند بازبینی" aria-label="این داده تایید نشده است">دورود</p>' );
sa_test( strpos( $attrs, 'نیازمند' ) === false && strpos( $attrs, 'تایید' ) === false && strpos( $attrs, 'دورود' ) !== false, 'tooltips and accessible labels cannot leak workflow' );
$old_ui = '<p class="sa-source-warning">سلامت HTTP لینک‌ها هنوز بررسی نشده است.</p><span class="sa-cyber-id__review">بازبینی</span><p class="sa-facts-checked">۱۴۰۴/۰۱/۰۱</p><p>راهنمای سفر</p>';
$clean = sa_public_editorial_html( $old_ui );
sa_test( strpos( $clean, 'HTTP' ) === false && strpos( $clean, 'sa-cyber-id__review' ) === false && strpos( $clean, 'راهنمای سفر' ) !== false, 'old template UI is actually removed, not CSS-hidden' );
sa_test( $clean === sa_public_editorial_html( $clean ), 'cleanup is idempotent' );

// Admin/raw storage is untouched; logged-in editors see the same public article.
$GLOBALS['admin'] = true;
sa_test( $split === sa_public_editorial_filter( $split ), 'admin content unchanged' );
$GLOBALS['admin'] = false;
sa_test( strpos( sa_public_editorial_filter( $split ), 'هوش مصنوعی' ) === false, 'public filter applies without capability bypass' );
sa_test( isset( $GLOBALS['hooks']['the_content'][8] ) && isset( $GLOBALS['hooks']['the_content'][999] ), 'cleanup precedes county extraction and TOC and covers later injected output' );
$GLOBALS['meta']['sa_faq'] = json_encode( array( array( 'q' => 'نیازمند بررسی', 'a' => 'یادداشت تحریریه' ), array( 'q' => 'چطور برویم؟', 'a' => 'با قطار سفر کنید [منبع لازم].' ) ) );
$stored = $GLOBALS['meta']['sa_faq'];
$faq = sa_get_faq( 42 );
sa_test( count( $faq ) === 1 && strpos( $faq[0]['a'], 'با قطار سفر کنید' ) !== false && strpos( $faq[0]['a'], 'منبع لازم' ) === false, 'metadata FAQ cleans notes and drops empty rows' );
sa_test( $stored === $GLOBALS['meta']['sa_faq'], 'FAQ storage unchanged' );
$GLOBALS['meta']['sa_sources'] = "منبع را اضافه کنید.\nیونسکو (راستی‌آزمایی‌شده: ۱۴۰۵/۰۷/۲۱) | https://unesco.org/a\n---\nFACT CHECK REPORT";
$sources = sa_get_sources( 42 );
sa_test( count( $sources ) === 1 && $sources[0]['url'] === 'https://unesco.org/a' && trim( $sources[0]['title'] ) === 'یونسکو', 'source URLs retained, instructions/review annotations/private appendix removed' );
$GLOBALS['sa_county_page_parts'][42] = array( 'urls' => array() );
// Avoid network probes; use URL candidates already validated by the county policy.
function esc_url_raw( $s ) { return $s; }
function wp_http_validate_url( $s ) { return filter_var( $s, FILTER_VALIDATE_URL ); }
ob_start(); sa_county_sources_section( 42 ); $source_html = ob_get_clean();
sa_test( strpos( $source_html, 'https://unesco.org/a' ) !== false && strpos( $source_html, 'ثبت کنید' ) === false && strpos( $source_html, 'warning' ) === false, 'short source lists have no notices, even for editors' );
$GLOBALS['meta']['sa_sources'] = '';
$GLOBALS['meta']['sa_faq'] = '';
ob_start(); sa_county_sources_section( 42 ); $source_html = ob_get_clean();
sa_test( '' === $source_html, 'zero sources produce no empty section or warning' );
sa_test( '' === sa_cyber_id_review_badge() && '' === sa_cyber_id_value_or_review( '[نیازمند بررسی]' ), 'no identity-review fallback badge' );
$GLOBALS['mods']['sa_missing_image_label'] = 'تصویر این بخش در دست بازبینی است';
sa_test( 'تصویر در دسترس نیست' === sa_missing_image_label(), 'old saved image placeholder cannot restore a review message' );
$GLOBALS['meta']['sa_facts_checked'] = '2020-01-01';
$GLOBALS['meta']['sa_last_verified_date'] = '2020-01-01';
ob_start(); sa_facts_checked_note( 42 ); $note = ob_get_clean();
sa_test( '' === $note, 'no positive or negative verification date note' );
ob_start(); sa_updated_badge( 42 ); $note = ob_get_clean();
sa_test( strpos( $note, 'به‌روزرسانی' ) !== false && strpos( $note, '2020' ) === false, 'ordinary modification date remains, verification date does not' );
$GLOBALS['article'] = '<article><div class="identity">نیازمند بازبینی</div><p>متن مقاله</p>' . $old_ui . '</article>';
ob_start(); sa_public_article_template( 'test' ); $article = ob_get_clean();
sa_test( strpos( $article, 'نیازمند' ) === false && strpos( $article, 'HTTP' ) === false && strpos( $article, 'متن مقاله' ) !== false, 'complete article wrapper covers metadata outside the_content' );
$GLOBALS['post'] = (object) array( 'post_excerpt' => '', 'post_content' => '<p>این متن پیش‌نویس هوش مصنوعی است.</p><p>دورود در لرستان است.</p>' );
sa_test( sa_summary( 42, 4 ) === 'دورود در لرستان است.', 'summary cleaned before word limit, no chopped editorial fragments' );
$GLOBALS['meta']['sa_seo_description'] = 'این متن پیش‌نویس است. راهنمای سفر به دورود.';
sa_test( trim( sa_seo_description() ) === 'راهنمای سفر به دورود.', 'custom SEO description follows public policy' );
$schema = sa_public_editorial_schema( array( array( 'description' => 'نیازمند بازبینی', 'dateModified' => '2026-10-17', 'url' => 'https://example.org/AI-draft' ) ) );
sa_test( '' === $schema[0]['description'] && '2026-10-17' === $schema[0]['dateModified'] && 'https://example.org/AI-draft' === $schema[0]['url'], 'schema cleans prose only, retaining dates and identifiers' );
echo "PASS: public editorial cleanup, preservation, metadata, source counts, badges, SEO/schema and no storage writes\n";
