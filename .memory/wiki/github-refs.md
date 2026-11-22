# مخازن و فایل‌های مرجع گیت‌هاب (GitHub Refs)

> **پروتکل:** پیش از هر جستجوی جدید در گیت‌هاب، اول این فایل را بخوان. هر منبع مفید تازه را با همین قالب ثبت کن.

[benchmarkstudios/barebones]
· لینک: https://github.com/benchmarkstudios/barebones
· مسیر فایل کلیدی: `includes/setup.php`, `includes/loaders.php`
· چه چیزی از آن یاد گرفتیم: پاک‌سازی مینیمال `wp_head` (حذف emoji، `rsd_link`, `wlwmanifest_link`, `wp_shortlink_wp_head`, `wp_generator` در `after_setup_theme`) و الگوی سادهٔ `bb_get_asset_data()` برای cache-busting با `filemtime()` همراه بارگذاری اسکریپت با `strategy => 'defer'` و `in_footer => true`.
· مربوط به کدام بخش سایت است: `functions.php` (`sa_child_asset_version`), `inc/performance.php`
· تاریخ کشف: ۲۰۲۶-۱۱-۲۲

[gregsullivan/_tw]
· لینک: https://github.com/gregsullivan/_tw
· مسیر فایل کلیدی: `tailwind/tailwind-typography.css`, `tailwind/custom/fonts.css`
· چه چیزی از آن یاد گرفتیم: تنظیم ساختار تایپوگرافی مقاله (`prose`) روی متغیرهای CSS پویای پروژه بدون بارگذاری Tailwind runtime در زمان اجرا، و مدیریت وزن‌های محدود فونت (به‌جای بارگذاری همهٔ وزن‌های ۴۰۰ تا ۹۰۰ در صورت استفاده از فونت‌های غیرمتغیر).
· مربوط به کدام بخش سایت است: `assets/css/sa-2026.css`, `assets/css/child.css` (قواعد `.sa-2026-prose`)
· تاریخ کشف: ۲۰۲۶-۱۱-۲۲

[rastikerdar/vazirmatn]
· لینک: https://github.com/rastikerdar/vazirmatn
· مسیر فایل کلیدی: `Vazirmatn-font-face.css`, `fonts/webfonts/`
· چه چیزی از آن یاد گرفتیم: تعریف استاندارد `@font-face` محلی فقط با فرمت فشردهٔ `woff2` و `font-display: swap` برای تایپوگرافی استاندارد فارسی بدون نیاز به درخواست شبکه به CDNهای خارجی.
· مربوط به کدام بخش سایت است: `wp-content/themes/sarzaminaryan/assets/css/fonts.css`, `functions.php` (`sa_child_preload_bold_font()`), `assets/css/child.css` (`Vazirmatn Fallback`)
· تاریخ کشف: ۲۰۲۶-۱۱-۲۲

[carlosuhart/seo-audit-skills]
· لینک: https://github.com/carlosuhart/seo-audit-skills
· مسیر فایل کلیدی: `skills/core-web-vitals/SKILL.md`, `skills/canonical/SKILL.md`, `skills/cache-headers/SKILL.md`
· چه چیزی از آن یاد گرفتیم: چارچوب تشخیص فنی Core Web Vitals برای وردپرس بر مبنای داده‌های میدانی CrUX (آستانه‌های LCP ≤ 2.5s، CLS ≤ 0.1، INP ≤ 200ms، TTFB ≤ 800ms) و ممیزی تگ‌های canonical و هدرهای کش.
· مربوط به کدام بخش سایت است: `inc/performance.php`, `inc/seo.php`, `inc/schema.php`, `tools/wp-agent/wp_agent.py` (`probe`)
· تاریخ کشف: ۲۰۲۶-۱۱-۲۲

[flyntwp/flynt]
· لینک: https://github.com/flyntwp/flynt
· مسیر فایل کلیدی: `inc/cleanHead.php`, `inc/assets.php`, `Components/`
· چه چیزی از آن یاد گرفتیم: معماری کامپوننت‌محور در قالب‌های PHP مدرن وردپرس (`stars: 808`, `topic:wordpress` + `language:PHP`)، پاک‌سازی کامل تگ‌های اضافی `wp_head` از جمله لینک‌های oEmbed و REST و استایل‌های هاردکد وردپرس برای کاهش حجم HTML اولیه.
· مربوط به کدام بخش سایت است: `inc/performance.php`, `inc/county-sections.php`, `template-parts/`
· تاریخ کشف: ۲۰۲۶-۱۱-۲۲
