<?php
/**
 * تست مستقل موتور افزونه (بدون وردپرس):
 * php wp-content/plugins/sa-ai-sentence-cleaner/tests/test-engine.php
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/patterns.php';
require_once dirname( __DIR__ ) . '/includes/class-sa-ai-engine.php';

function sa_ai_t_assert( $condition, $message ) {
	if ( ! $condition ) {
		echo "FAIL: {$message}\n";
		exit( 1 );
	}
	echo "ok - {$message}\n";
}

$patterns = sa_ai_default_patterns();

// ۱) جملهٔ معرفی حذف، واقعیت‌ها می‌مانند.
$src = '<p>شهرستان کوهسرخ با مرکزیت شهر ریوش در استان خراسان رضوی قرار دارد. این مقاله مشخصات اداری، تاریخ، فرهنگ، زبان، صنایع و جاذبه‌های شهرستان را با رویکرد مستند معرفی می‌کند. ریوش مرکز اداری شهرستان است.</p>';
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true, 'manual_threshold' => 1.0 ) );
sa_ai_t_assert( false !== strpos( $out['content'], 'شهرستان کوهسرخ با مرکزیت شهر ریوش' ), 'test1 first fact kept' );
sa_ai_t_assert( false !== strpos( $out['content'], 'ریوش مرکز اداری شهرستان است' ), 'test1 last fact kept' );
sa_ai_t_assert( false === strpos( $out['content'], 'معرفی می‌کند' ), 'test1 AI intro removed' );

// ۲) جملهٔ فرایندی کامل حذف می‌شود (متن ساده).
$src = 'داده‌های عددی پیش از انتشار نهایی باید با مرکز آمار، وزارت کشور و منابع رسمی استان تطبیق داده شوند.';
$out = SA_AI_Engine::analyze_content( $src, $patterns );
sa_ai_t_assert( ! empty( $out['findings'] ) && 'DELETE' === $out['findings'][0]['level'], 'test2 process sentence is DELETE' );

// ۳) «ي/ك» عربی و بدون نیم‌فاصله هم پیدا می‌شود.
$src = 'داده‌هاي عددي پيش از انتشار نهايي بايد با مركز آمار، وزارت كشور و منابع رسمي استان تطبيق داده شوند.';
$out = SA_AI_Engine::analyze_content( $src, $patterns );
sa_ai_t_assert( ! empty( $out['findings'] ), 'test3 arabic letters matched' );

// ۴) بدون الگو بایت‌به‌بایت یکسان.
$src = "<h2>عنوان بخش</h2>\n<p>شهرستان کوهسرخ در استان خراسان رضوی قرار دارد.</p>\n";
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $src === $out['content'], 'test4 byte identical without pattern' );

// ۵) idempotent
$once  = SA_AI_Engine::clean_content( '<p>شهرستان کوهسرخ در خراسان رضوی قرار دارد. این مقاله مشخصات شهرستان را با رویکرد مستند معرفی می‌کند. ریوش مرکز شهرستان است و جاذبه‌های طبیعی فراوانی دارد که گردشگران زیادی را جذب می‌کند و اقتصاد آن کشاورزی است.</p>', $patterns, array(), array( 'delete_by_pattern' => true, 'manual_threshold' => 1.0 ) );
$twice = SA_AI_Engine::clean_content( $once['content'], $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $once['content'] === $twice['content'] && 0 === $twice['deleted_sentences'], 'test5 idempotent' );

// ۶) منابع: google حذف، معتبرها می‌مانند، شماره‌ها و ارجاع مرتب می‌شود.
$src = '<p>متن مقاله این‌جا است [1] و این بخش هم ارجاع دارد [3].</p><h2>منابع</h2><ol>'
	. '<li><a href="https://fa.wikipedia.org/wiki/کوهسرخ">ویکی‌پدیای فارسی</a></li>'
	. '<li><a href="https://google.com/search?q=x">گوگل</a></li>'
	. '<li><a href="https://kuhsorkh.khorasan.ir/">فرمانداری کوهسرخ</a></li></ol>';
$out = SA_AI_Engine::clean_sources( $src );
sa_ai_t_assert( false === strpos( $out['content'], 'google.com' ), 'test6 bad source removed' );
sa_ai_t_assert( false !== strpos( $out['content'], 'fa.wikipedia.org' ) && false !== strpos( $out['content'], 'kuhsorkh.khorasan.ir' ), 'test6 trusted sources kept' );
sa_ai_t_assert( false !== strpos( $out['content'], '[2]' ), 'test6 citation renumbered' );

// ۷) بلوک کد، تیتر و جدول دست نمی‌خورند.
$src = '<h2>این مقاله شهرستان را با رویکرد مستند معرفی می‌کند</h2><pre>این مقاله چیزی را با رویکرد مستند معرفی می‌کند. TODO</pre><table><tr><td>این مقاله سلول جدول را با رویکرد مستند معرفی می‌کند</td></tr></table><p>متن سالم شهرستان است.</p>';
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $src === $out['content'], 'test7 heading/code/table untouched' );

// ۸) جملهٔ تکراری AI در ۴ مقاله پیدا و با تأیید مالک از هر ۴ مقاله حذف می‌شود.
$dup_sentence = 'این مقاله مشخصات اداری شهرستان را با رویکرد مستند معرفی می‌کند';
$contents     = array();
for ( $i = 1; $i <= 4; $i++ ) {
	$contents[ $i ] = '<p>شهرستان نمونه ' . $i . ' در ایران قرار دارد و مرکز آن شهر مرکزی است. ' . $dup_sentence . '. جاذبه‌های طبیعی شهرستان نمونه ' . $i . ' زیاد است و مردم آن به کشاورزی مشغول هستند و هر سال گردشگران زیادی از آن دیدن می‌کنند.</p>';
}
$map = SA_AI_Engine::collect_duplicates( $contents, $patterns, 4, 20 );
sa_ai_t_assert( 1 === count( $map ), 'test8 duplicate group found once' );
$hash = array_keys( $map )[0];
sa_ai_t_assert( 'this-article-introduces' === $map[ $hash ]['pattern_id'] || 'documented-approach' === $map[ $hash ]['pattern_id'], 'test8 duplicate is AI-patterned' );
foreach ( $contents as $content ) {
	$clean = SA_AI_Engine::clean_content( $content, $patterns, $map, array( 'duplicate_hashes' => array( $hash ), 'manual_threshold' => 1.0 ) );
	sa_ai_t_assert( false === strpos( $clean['content'], 'معرفی می‌کند' ), 'test8 duplicate removed everywhere' );
}

// ۹) جملهٔ داخل لینک خودکار حذف نمی‌شود.
$src = '<p><a href="https://example.ir/">این مقاله شهرستان را با رویکرد مستند معرفی می‌کند</a>. متن سالم شهرستان است و ادامه دارد.</p>';
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $src === $out['content'], 'test9 link sentence not auto-deleted' );

// ۱۰) بیش از ۲۰٪: manual_review و متن دست‌نخورده.
$bad = 'این مقاله مشخصات شهرستان را با رویکرد مستند معرفی می‌کند. ';
$src = '<p>' . $bad . $bad . $bad . $bad . 'ریوش مرکز شهرستان است.</p>';
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $src === $out['content'] && ! empty( $out['manual_review'] ), 'test10 manual review guard' );

// ۱۱) FLAG هرگز حذف نمی‌شود.
$src = '<p>در این مقاله به بررسی جاذبه‌های شهرستان می‌پردازیم. شهرستان کوهسرخ در خراسان رضوی است و مرکز آن ریوش است و جاذبه‌های زیادی دارد.</p>';
$out = SA_AI_Engine::clean_content( $src, $patterns, array(), array( 'delete_by_pattern' => true ) );
sa_ai_t_assert( $src === $out['content'] && ! empty( $out['findings'] ), 'test11 flag only reported' );

// ۱۲) جایگزین [نیاز به منبع] شورت‌کد نیست و دیده می‌شود.
$src = '<p>جمعیت شهرستان بر اساس سرشماری مشخص است و مرکز آن شهر مرکزی است. این داده [نیاز به منبع] دارد و هنوز در هیچ سامانه‌ای ثبت نشده است تا بررسی شود.</p>';
$out = SA_AI_Engine::analyze_content( $src, $patterns );
sa_ai_t_assert( ! empty( $out['findings'] ), 'test12 placeholder bracket detected' );

echo "ALL TESTS PASSED\n";
