<?php
/**
 * الگوهای پیش‌فرض پاک‌سازی — همان نمونه‌های پرامپت مالک.
 *
 * DELETE یعنی جملهٔ دارای الگو نامزد حذف خودکار است؛ FLAG فقط گزارش می‌شود.
 * جملهٔ تکراری (duplicate-sentence) regex ندارد و از تکرار بین مقاله‌ها ساخته می‌شود.
 *
 * @package SA_AI_Sentence_Cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<int,array{id:string,level:string,regex:string,description:string}>
 */
function sa_ai_default_patterns() {
	return array(
		array( 'id' => 'human-review-phrase', 'level' => 'DELETE', 'regex' => 'برای بازبینی و ویرایش انسانی', 'description' => 'برچسب بازبینی و ویرایش انسانی روی پیش‌نویس' ),
		array( 'id' => 'human-edit-or-review', 'level' => 'DELETE', 'regex' => '(ویرایش انسانی|بازبینی انسانی)', 'description' => 'اشاره به ویرایش/بازبینی انسانی' ),
		array( 'id' => 'before-final-publish', 'level' => 'DELETE', 'regex' => 'پیش از (انتشار|ویرایش) نهایی', 'description' => 'جملهٔ فرایندی پیش از انتشار/ویرایش نهایی' ),
		array( 'id' => 'must-match-official-sources', 'level' => 'DELETE', 'regex' => 'باید با .{0,60}(مرکز آمار|وزارت کشور|منابع رسمی).{0,60} تطبیق داده شو(د|ند)', 'description' => 'دستور تطبیق با مرکز آمار/وزارت کشور/منابع رسمی' ),
		array( 'id' => 'needs-check-or-source', 'level' => 'DELETE', 'regex' => '(نیاز به|نیازمند) (بررسی|تأیید|بازبینی|منبع)', 'description' => 'نیاز به بررسی/تأیید/بازبینی/منبع' ),
		array( 'id' => 'this-article-introduces', 'level' => 'DELETE', 'regex' => 'این مقاله .{0,120} را .{0,40}معرفی می‌کند', 'description' => 'جملهٔ متای «این مقاله … را معرفی می‌کند»' ),
		array( 'id' => 'documented-approach', 'level' => 'DELETE', 'regex' => 'با رویکرد مستند', 'description' => 'عبارت قالبی «با رویکرد مستند»' ),
		array( 'id' => 'as-ai-language-model', 'level' => 'DELETE', 'regex' => 'به عنوان (یک )?(مدل زبانی|هوش مصنوعی)', 'description' => 'خودمعرفی مدل زبانی/هوش مصنوعی' ),
		array( 'id' => 'placeholder-fa', 'level' => 'DELETE', 'regex' => '(\[نیاز به منبع\]|\[منبع\]|\bTODO\b|\bTBD\b|\bXXX\b|\{\{[^}]+\}\})', 'description' => 'جایگزین‌ها: [نیاز به منبع]، [منبع]، TODO، TBD، XXX، {{...}}' ),
		array( 'id' => 'english-ai-traces', 'level' => 'DELETE', 'regex' => '(as an AI|language model|I hope this helps|placeholder|lorem ipsum)', 'description' => 'ردپای انگلیسی هوش مصنوعی' ),
		array( 'id' => 'in-this-article-we-review', 'level' => 'FLAG', 'regex' => 'در این مقاله به بررسی .{0,60} می‌پردازیم', 'description' => '«در این مقاله به بررسی … می‌پردازیم» — فقط گزارش' ),
		array( 'id' => 'hope-this-article', 'level' => 'FLAG', 'regex' => 'امیدوارم این (مقاله|مطلب)', 'description' => '«امیدوارم این مقاله/مطلب …» — فقط گزارش' ),
		array( 'id' => 'generic-filler', 'level' => 'FLAG', 'regex' => '(لازم به ذکر است|به طور کلی|در پایان می‌توان گفت)', 'description' => 'پیونددهنده‌های کلی — فقط گزارش' ),
		array( 'id' => 'duplicate-sentence', 'level' => 'FLAG', 'regex' => '', 'description' => 'جمله‌ای که عیناً در بیش از ۳ مقاله تکرار شده — فقط گزارش، مگر مالک همان جمله را برای حذف همه‌جا تأیید کند' ),
		array( 'id' => 'duplicate-ai-sentence', 'level' => 'DELETE', 'regex' => '', 'description' => 'جملهٔ تکراری‌ای که خودش الگوی DELETE هوش مصنوعی هم دارد؛ از صفحهٔ «جملات تکراری» در همهٔ مقاله‌ها حذف می‌شود' ),
		array( 'id' => 'bad-source-only', 'level' => 'DELETE', 'regex' => '', 'description' => 'منبعی که فقط google.com یا chatgpt.com یا chat.openai.com است' ),
		array( 'id' => 'utm-source-chatgpt', 'level' => 'FLAG', 'regex' => '', 'description' => 'پارامتر utm_source=chatgpt.com از لینک پاک می‌شود و خود لینک می‌ماند' ),
	);
}
