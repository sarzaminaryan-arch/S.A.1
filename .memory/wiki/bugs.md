# ثبت باگ‌ها و راه‌حل‌ها (Bugs & Root Causes)

> **قانون ضد تکرار:** هرگز راه‌حلی که یک بار شکست خورده را بدون دلیل جدید تکرار نکن. پیش از هر اصلاح، این فایل را چک کن.

---

[BUG-20261127-01] قطع کامل سایت زنده پس از انتقال دامنه به حالت Addon Domain (vhost/SSL ندارد)

· وضعیت: باز — تشخیص انجام شد، اصلاح در انتظار دسترسی مالک به cPanel/هاست
· علائم: `https://sarzaminaryan.ir/` هیچ صفحه‌ای نمی‌دهد؛ پورت ۸۰ پاسخ `SORRY! … Default Web Site Page` (`/cgi-sys/defaultwebpage.cgi`) و پورت ۴۴۳ هنگام دست‌دادن TLS با SNI دامنه با EOF قطع می‌شود (هیچ گواهی‌ای ارائه نمی‌شود)؛ `/wp-json/` هم از بیرون در دسترس نیست.
· علت ریشه‌ای (Root Cause): DNS سالم است (`A → 88.135.68.12`، `www` هم CNAME همان، NS و MX هاست دست‌نخورده و ایمیل سایت برقرار)، اما روی سرور Apache برای این دامنه **vhost/سند درست** و **گواهی SSL** وجود ندارد؛ درخواست به «سایت پیش‌فرض» سرور می‌افتد. آخرین گواهی ثبت‌شده (Let's Encrypt، ۷ اکتبر ۲۰۲۶) نشان می‌دهد تا پیش از تغییر دامنه، vhost و SSL برقرار بوده است.
· مسیرهایی که امتحان شد و شکست خورد: — (این نشست فقط‌خواندنی بود؛ هیچ تغییری روی سایت/هاست انجام نشد)
· راه‌حل نهایی (دستور کار مالک در cPanel): ۱) پشتیبان کامل؛ ۲) پیدا کردن مسیر واقعی `wp-config.php` در File Manager؛ ۳) `Domains → Document Root` دامنه را به همان مسیر اصلاح یا دامنه را دوباره اضافه کردن؛ ۴) `SSL/TLS Status → Run AutoSSL`؛ ۵) در صورت ریدایرکت به دامنهٔ قبلی، اصلاح `home`/`siteurl` در `wp_options` و `WP_HOME`/`WP_SITEURL`؛ ۶) Purge All کش. سپس `python3 tools/ops/site_health.py` باید `ok` بدهد.
· فایل/خط درگیر: `tools/ops/site_health.py` (ابزار سنجش)، `docs/ops/DOMAIN-DOWN-2026-11-27.md` (سند تشخیص و دستور کار)
· منبع (گیت‌هاب/داک): سنجش زندهٔ ۲۰۲۶-۱۱-۲۷ و پاسخ بیرونی `http://sarzaminaryan.ir/cgi-sys/defaultwebpage.cgi`
· تاریخ: ۲۰۲۶-۱۱-۲۷

---

[BUG-20261124-01] شکست `test_county_run.py` در `Build gates` روی `main` پس از افزودن فایل‌های بریف دزفول

· وضعیت: حل‌شده
· علائم: اجرای `Build gates` (`45281372345`) روی `main` پس از ادغام `PR #151` در گام `test_county_run.py` با `AssertionError: ('briefs/dezful-evidence.md', ['بریف docs/live-site/briefs/dezful.md'])` شکست خورد.
· علت ریشه‌ای (Root Cause): تست `missing_witnesses("dezful")` در `tools/wp-agent/test_county_run.py` بدون ایزوله‌کردن مسیرهای `cr.BRIEFS` و `cr.OUTBOX` روی پوشهٔ واقعی مخزن اجرا می‌شد و فرض می‌کرد هیچ‌یک از سه شاهد دزفول در مخزن وجود ندارند؛ به محض افزوده‌شدن `dezful-evidence.md` و `dezful-gates.md` در `PR #151` تست شکست خورد.
· مسیرهایی که امتحان شد و شکست خورد: تکیه بر نامک واقعی دست‌نخورده در مخزن (با پیشروی پروژه دیر یا زود آن نامک هم فایل بریف می‌گیرد و دوباره CI را قرمز می‌کند).
· راه‌حل نهایی: ایزوله‌سازی کامل `cr.BRIEFS` و `cr.OUTBOX` درون پوشهٔ موقت `tempfile` هنگام آزمون `missing_witnesses` در `tools/wp-agent/test_county_run.py`.
· فایل/خط درگیر: `tools/wp-agent/test_county_run.py`
· منبع (گیت‌هاب/داک): اجرای ناموفق `Build gates` شمارهٔ `45281372345` روی `main`
· تاریخ: ۲۰۲۶-۱۱-۲۴

---

[BUG-20261123-03] خطای کاذب سلامت در گام `probe` هنگام ارسال URL فایل باینری تصویر (`image/webp`)

· وضعیت: حل‌شده (قاعدهٔ عملیاتی ثبت‌شده)
· علائم: کار `20261123-10-county-covers-repair.json` در گام `probe` متوقف شد و URLهای تصویر `county-*.webp` را با وجود پاسخ `HTTP 200` «صفحهٔ ناسالم» تشخیص داد.
· علت ریشه‌ای (Root Cause): دستور `probe` در `wp_agent.py` برای بررسی سلامت صفحات HTML طراحی شده و نبود ساختار HTML/تگ‌های صفحه در پاسخ باینری `image/webp` را به‌عنوان خرابی صفحه قضاوت می‌کند.
· مسیرهایی که امتحان شد و شکست خورد: قراردادن نشانی مستقیم فایل‌های رسانهٔ `.webp` در آرایهٔ `probe` درون جاب‌های دارای `rollback_on_fail: true`.
· راه‌حل نهایی: در گام `probe` صرفاً نشانی برگه‌ها و نوشته‌های HTML سایت قرار داده شود؛ برای ممیزی سلامت فایل‌های تصویر شاخص از زیرفرمان اختصاصی `featured-audit` استفاده گردد.
· فایل/خط درگیر: `tools/wp-agent/results/20261123-10-county-covers-repair.json`, `tools/wp-agent/results/20261123-11-featured-audit-readonly.json`
· منبع (گیت‌هاب/داک): گزارش اجرای کارهای ۱۰ و ۱۱ در `.agent/STATUS.md`
· تاریخ: ۲۰۲۶-۱۱-۲۳

---

[BUG-20261123-01] پیوند خوردن واژه‌های صفتی/مرکبِ دارای پسوند نیم‌فاصله («شهرستان کوهپایه‌ای جم») در `sa_autolink_fragment()`

· وضعیت: حل‌شده (مستقر روی تولید در `2.24.18` / `2.24.19`)
· علائم: در صفحهٔ زندهٔ استان بوشهر، عبارت «در حالی که شهرستان کوهپایه‌ای جم هوایی خنک‌تر از نوار ساحلی دارد» بخش «شهرستان کوهپایه» را به `/city/kuhpayeh/` (در استان اصفهان) پیوند داده و پسوند `‌ای جم` را بیرون لینک گذاشته بود.
· علت ریشه‌ای (Root Cause): در `sa_autolink_char_map()` کاراکتر نیم‌فاصله (`\xE2\x80\x8C`) برای تطبیق نام‌های مرکب («بستان‌آباد»/«بستان آباد») به فاصله `' '` نگاشته می‌شود؛ در نتیجه هنگام اجرای `$state['regex']` روی رشتهٔ نرمال‌شده، هیچ `\x{200C}` در `$subject` نمی‌ماند و مرز واژهٔ `(?![\p{L}\p{N}\p{M}\x{200C}])` پیش از پسوندهای چسبیده با نیم‌فاصله (`کوهپایه` + ZWNJ + `ای`) به‌غلط تطبیق می‌خورد، بدون اینکه `sa_autolink_fragment()` متن اصلی `$text` را در لبه‌های `$start`/`$finish` بررسی کند.
· مسیرهایی که امتحان شد و شکست خورد: حذف تبدیل نیم‌فاصله در `sa_autolink_char_map()` نام‌های مرکبِ دارای املای با/بی نیم‌فاصله را می‌شکند؛ اصلاح صرفِ یک جمله در `bushehr-41.html` هم بقیهٔ صفحات سایت را در برابر همین الگو محافظت نمی‌کرد.
· راه‌حل نهایی: افزودن نگهبان مرز نیم‌فاصله در متن اصلی `$text` درون `sa_autolink_fragment()` بلافاصله پس از محاسبهٔ `$start` و `$finish`: رد کردن تطبیق اگر پیش از `$start` الگوی `[\p{L}\p{N}\p{M}]\x{200C}$` یا پس از `$finish` الگوی `^\x{200C}[\p{L}\p{N}\p{M}]` وجود داشته باشد، همراه حفظ نام‌های مرکب دارای نیم‌فاصلهٔ درونی («شهرستان بستان‌آباد»).
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/inc/internal-links.php`, `tests/test-internal-links.php`
· منبع (گیت‌هاب/داک): گزارش زندهٔ کاربر روی `/province/bushehr/` (`2026-11-23`)
· تاریخ: ۲۰۲۶-۱۱-۲۳

---

[BUG-20261123-02] ناهماهنگی شمار شهرستان‌های اصفهان (`۲۸ شهرستان (۲۹ راهنمای فعال)`)

· وضعیت: حل‌شده (مستقر روی تولید در `2.24.18` / `2.24.19`)
· علائم: باکس شناسنامه در صفحهٔ استان اصفهان مقدار «۲۸ شهرستان (۲۹ راهنمای فعال)» چاپ می‌کرد در حالی که ۲۹ راهنمای شهرستان منتشر و در متن معرفی شده بود.
· علت ریشه‌ای (Root Cause): `single-province.php` عدد `$sa_county_count` را از `data/province-profiles.json` (`provinces.isfahan.stats.county_count`) می‌خواند که هنوز با مقدار پیش از تفکیک شهرستان «میمه و وزوان» (`1403`) برابر `28` مانده بود و `meymeh-vazvan` نیز در `data/region-facts.php` (`counties['isfahan']`) وجود نداشت.
· مسیرهایی که امتحان شد و شکست خورد: به‌روزرسانی متن و متای `isfahan-35` به‌تنهایی کافی نبود چون کارت بالای صفحه مستقیم از `province-profiles.json` می‌خواند.
· راه‌حل نهایی: اصلاح `county_count` استان اصفهان از `28` به `29` در `data/province-profiles.json`، افزودن `meymeh-vazvan` با مختصات رسمی (`33.4200, 51.1800`) به `data/region-facts.php`، و افزودن assertion در `tests/test-province-county-links.php`.
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/data/province-profiles.json`, `data/region-facts.php`, `tests/test-province-county-links.php`
· منبع (گیت‌هاب/داک): گزارش زندهٔ کاربر روی `/province/isfahan/` و دادهٔ زندهٔ `/city/meymeh-vazvan/` (`2026-11-23`)
· تاریخ: ۲۰۲۶-۱۱-۲۳

---

[BUG-20261122-03] باقی ماندن تگ‌های مردهٔ `<a>` بدون `href` با خط زیرین در `sa_2026_relation_content()`

· وضعیت: حل‌شده (در `2.24.16`)
· علائم: نام استان‌های هم‌مرز (همدان، مرکزی، اصفهان، خوزستان، ایلام، کرمانشاه، کرمانشاه و نیز در کرمانشاه) و باکس‌های تکراری شهرستان در بدنهٔ `.sa-2026-prose` به‌صورت متن آبی خط‌زیرین غیرقابل کلیک دیده می‌شدند، در حالی که «چهارمحال و بختیاری» لینک باقی مانده بود.
· علت ریشه‌ای (Root Cause): `sa_2026_relation_content()` با `WP_HTML_Tag_Processor::remove_attribute('href')` تنها صفت `href` را حذف می‌کرد و تگ `<a>...</a>` را نگه می‌داشت که زیر قانون `.sa-2026-prose a { color: rgb(var(--sa-brand)); text-decoration: underline; }` شبیه لینک خراب رندر می‌شد؛ هم‌زمان `sa-autolink-off` روی جملهٔ استان‌های هم‌مرز نبود و لیست همسایگان `region-facts.php` لرستان شامل چهارمحال نبود.
· مسیرهایی که امتحان شد و شکست خورد: حذف کامل همهٔ لینک‌های `/province/` درون `sa_2026_relation_content()` با قرارداد `render_contract_fixture.py` (آزمون فارس انتظار دارد لینک استان منتشرشدهٔ بیرون از `#neighbors-section` باقی بماند) ناسازگار می‌شد.
· راه‌حل نهایی: جایگزینی `remove_attribute('href')` با `sa_2026_unwrap_relation_links()` برای جداسازی کامل تگ‌های `<a ...>...</a>` هم‌کلید با `$keys` (همراه حفظ بلوک‌های حساس و لینک‌های تصویری `<img>/<svg>/<picture>/<figure>`)، استفاده از `sa-autolink-off` روی فهرست متنی استان‌های هم‌مرز، و محدودسازی خط زیرین CSS به `a[href]:not([href=""]):not(.sa-source-box)`.
· فایل/خط درگیر: `inc/design-2026.php`, `assets/css/child.css`, `tests/test-design-2026.php`, `tools/wp-agent/outbox/20261122-province-lorestan-478/lorestan-478.html`
· منبع (گیت‌هاب/داک): گزارش تصویری موبایل مالک (`2026-11-22`), `render_contract_fixture.py`
· تاریخ: ۲۰۲۶-۱۱-۲۲

---

[BUG-20261122-04] تقدم بارگذاری `sa-2026.css` بر `child.css` در قالب ۱۴۰۵

· وضعیت: حل‌شده (در `2.24.16`)
· علائم: قواعد هم‌اختصاصیت (مانند `.sa-2026-prose h2` یا `.sa-2026-prose a`) اگر بدون اختصاصیت بالاتر در `child.css` نوشته شوند، توسط `sa-2026.css` بازنویسی می‌شوند.
· علت ریشه‌ای (Root Cause): در `sa_2026_assets()` (`inc/design-2026.php`), استایل `sa-2026` با وابستگی `array( 'sarzaminaryan-child' )` ثبت شده، بنابراین `sa-2026.css` پس از `child.css` بارگذاری می‌شود.
· مسیرهایی که امتحان شد و شکست خورد: تغییر بدون مطالعهٔ ترتیب enqueue و نوشتن سلکتور `(0,1,1)` هم‌تراز با `sa-2026.css`.
· راه‌حل نهایی: استفاده از پیشوند `body.sa-2026` با اختصاصیت بالاتر (`(0,2,2)` به بالا) در `child.css` برای غلبهٔ تمیز بر `sa-2026.css` و `main.css` بدون `!important`.
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/inc/design-2026.php`, `assets/css/child.css`
· منبع (گیت‌هاب/داک): بازبینی `sa_2026_assets()` (۲۰۲۶-۱۱-۲۲)
· تاریخ: ۲۰۲۶-۱۱-۲۲

---

[BUG-20261122-01] تبدیل لینک‌های باکس منابع به پانویس `#sa-ref-N` و نمایش خط‌زیر لینک متنی

· وضعیت: حل‌شده
· علائم: لینک‌های بیرونی قرارگرفته داخل `.sa-source-boxes` در بخش «منابع» صفحهٔ لرستان به‌صورت پاورقی درون‌صفحه‌ای و بدون لینک مستقیم رندر می‌شدند و قواعد `.sa-2026-prose a` روی آن‌ها `text-decoration: underline` اعمال می‌کرد.
· علت ریشه‌ای (Root Cause): فیلتر `sa_citations_filter()` در اولویت ۱۳ روی کل `the_content` اجرا می‌شود و تمام `<a>`های بیرونی را جمع‌آوری و به ارجاع شماره‌دار تبدیل می‌کند؛ همچنین سلکتور `.sa-2026-prose a` اختصاصیت بالاتری از استایل‌های عمومی کارت داشت.
· مسیرهایی که امتحان شد و شکست خورد: قرار دادن صرف لینک‌های بیرونی در بخش `منابع` بدون محافظت PHP در `inc/citations.php` و تکیه بر استایل تک‌کلاسهٔ خارج از `.sa-2026-prose`.
· راه‌حل نهایی: افزودن تابع `sa_protect_source_boxes()` در `inc/citations.php` (فراخوانی‌شده در `sa_citations_filter()` و `sa_build_unified_sources()` به صورت تک‌لایه بدون `div` تو در تو) + کلاس `.sa-autolink-off` روی کانتینر + سلکتور ترکیبی `.sa-2026-prose .sa-source-boxes a.sa-source-box, .sa-source-boxes a.sa-source-box` در `assets/css/child.css`.
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/inc/citations.php`, `inc/template-tags.php`, `assets/css/child.css`, `tests/test-sources-last.php`
· منبع (گیت‌هاب/داک): PR `#128`, انتشار `sarzaminaryan-child` `2.24.15` (`1073146`)
· تاریخ: ۲۰۲۶-۱۱-۲۲

---

[BUG-20261122-02] خطای پارسر `wp-agent` با کلیدهای نامعتبر `excerpt_file` و `probe` درون گام `update`

· وضعیت: حل‌شده
· علائم: اجرای `tools/wp-agent/test_wp_agent_jobs.py` روی فایل جاب با خطای `unrecognized arguments: --excerpt-file ... --probe ...` متوقف شد.
· علت ریشه‌ای (Root Cause): زیرفرمان `update` در `tools/wp-agent/wp_agent.py` تنها آرگومان درون‌خطی `--excerpt` را می‌پذیرد و پاک‌سازی کش یا پروب نشانی‌ها هر کدام زیرفرمان مستقل خودشان را دارند.
· مسیرهایی که امتحان شد و شکست خورد: قرار دادن `excerpt_file` و `probe` به‌عنوان فیلدهای داخل شیء گام `update` در JSON جاب.
· راه‌حل نهایی: درج مقدار متنی `excerpt` به‌صورت مستقیم درون گام `update` و تعریف گام‌های مستقل `cache-purge` (با آرایهٔ شناسهٔ پست) و `probe` پس از آن.
· فایل/خط درگیر: `tools/wp-agent/jobs/20261122-17-province-lorestan-boxes-theme-22415.json`, `tools/wp-agent/wp_agent.py`
· منبع (گیت‌هاب/داک): `tools/wp-agent/test_wp_agent_jobs.py`
· تاریخ: ۲۰۲۶-۱۱-۲۲

---

[BUG-20261121-01] شکست نشانی‌های فارسی Percent-encoded در `sa_sources` و تکرار لینک‌های داخلی

· وضعیت: حل‌شده
· علائم: نشانی‌های فارسی دارای `%XX` هنگام ذخیره‌سازی/خواندن `sa_sources` و `extra_places` دچار به‌هم‌ریختگی می‌شدند و لینک‌های داخلی در بدنهٔ ۴۳۸ صفحه تکرار می‌شدند.
· علت ریشه‌ای (Root Cause): پاک‌سازی بیش‌ازحد نشانی‌ها هنگام خواندن متا و نبود یکتاسازی لینک‌های تکراری پیش‌تر ذخیره‌شده در بدنه در زمان رندر.
· مسیرهایی که امتحان شد و شکست خورد: ویرایش دستی متا بدون اصلاح منطق رندر قالب باعث بازگشت یا پوشش‌ندادن کل سایت می‌شد.
· راه‌حل نهایی: حفظ percent-encoding در `sa_sources` و حذف خودکار پیوندهای داخلی تکراری/خودارجاعی هنگام رندر بدنه در نسخهٔ `2.24.9` همراه با تست‌های محافظ `test-sources-sanitization.php` و `test-internal-links.php`.
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/inc/citations.php`, `inc/internal-links.php`, `tests/test-sources-sanitization.php`
· منبع (گیت‌هاب/داک): `CHANGELOG.md`, `.agent/STATUS.md` (نسخهٔ `2.24.9`)
· تاریخ: ۲۰۲۶-۱۱-۲۱

---

[BUG-20261120-01] خطای مرگبار PHP (`usort` با آرگومان رفرنسی) در صفحهٔ `/travel-guide/`

· وضعیت: حل‌شده
· علائم: پس از نصب نسخهٔ `2.23.4` روی سایت زنده، صفحهٔ `/travel-guide/` با `Fatal error: usort(): Argument #1 ($array) could not be passed by reference` متوقف شد.
· علت ریشه‌ای (Root Cause): ارسال خروجی `(array)` به‌صورت مستقیم به `usort()` که در PHP جدید خطای مرگبار می‌دهد و گاردهای متنی پیشین آن را نمی‌گرفتند.
· مسیرهایی که امتحان شد و شکست خورد: اتکا به گاردهای صرفاً رشته‌ای/استاتیک در PHP بدون تست اجرایی واقعی (runtime) روی مسیرهای بخش آموزش سفر.
· راه‌حل نهایی: بازنویسی منطق در `sa_guide_post_label()` بدون `usort` روی عبارت کست‌شده، و افزودن تست اجرایی با stub در `tests/test-guide-hub.php` (نسخهٔ `2.23.5`).
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/template-parts/2026/guide-hub.php`, `tests/test-guide-hub.php`
· منبع (گیت‌هاب/داک): `.agent/STATUS.md` (هات‌فیکس `2.23.5`)
· تاریخ: ۲۰۲۶-۱۱-۲۰

---

[BUG-20261119-01] مسدودبودن اجرای `run-job` روی runner میزبانی‌شده بدون state پایدار

· وضعیت: باز (محدودیت زیرساختی ثبت‌شده)
· علائم: استفاده از حالت قدیمی `run-job` بدون `SA_WP_AGENT_STATE_DIR` پایدار به‌صورت fail-closed مسدود است و اجرای هم‌زمان یا خودکار روی شاخهٔ غیر از `main` اجازه ندارد.
· علت ریشه‌ای (Root Cause): runner موقت گیت‌هاب فاقد ژورنال پایدار محلی برای حالت صف خودکار `run-job` است.
· مسیرهایی که امتحان شد و شکست خورد: اتکا به کشف خودکار فایل‌های صف (`ls tools/wp-agent/jobs/`) یا dispatch از شاخه‌های کاری غیر از `main`.
· راه‌حل نهایی: برای عملیات زنده صرفاً از `workflow_dispatch` روی `main` با تعیین صریح `job=<path>` در `.github/workflows/wp-agent.yml` استفاده شود و پیش از تکمیل بررسی/تأیید هرگز انتشار تکراری یا rollback کور انجام نگیرد.
· فایل/خط درگیر: `.github/workflows/wp-agent.yml`, `tools/wp-agent/test_workflow_safety.py`, `.agent/ROADMAP.md`
· منبع (گیت‌هاب/داک): `tools/wp-agent/test_workflow_safety.py`, `.agent/ROADMAP.md`
· تاریخ: ۲۰۲۶-۱۱-۱۹

---

[BUG-20261111-01] پرش لایه (CLS `0.176`) روی CTA هیرو در بارگذاری فونت فارسی

· وضعیت: حل‌شده
· علائم: گزارش PageSpeed `52w4rqrdi4` تغییر CLS بین `0` و `0.176` را در بخش هیرو نشان می‌داد.
· علت ریشه‌ای (Root Cause): قالب والد فقط `Vazirmatn-Regular.woff2` را preload می‌کرد؛ فونت `Bold` دیرتر از stylesheet کشف می‌شد و فونت سیستم جایگزین پیش از swap عرض/خط متفاوتی اشغال می‌کرد.
· مسیرهایی که امتحان شد و شکست خورد: اتکا به `font-display: swap` تنها بدون preload وزن `Bold` و بدون metric-override روی فونت جایگزین.
· راه‌حل نهایی: افزودن `sa_child_preload_bold_font()` در `functions.php` (اولویت ۲ `wp_head`) و تعریف `@font-face 'Vazirmatn Fallback'` با `size-adjust: 108%`, `ascent-override: 102.54%`, `descent-override: 53.71%` و قفل رگرسیون در `tests/test-font-loading.php`.
· فایل/خط درگیر: `wp-content/themes/sarzaminaryan-child/functions.php`, `assets/css/child.css`, `tests/test-font-loading.php`
· منبع (گیت‌هاب/داک): `wp-content/themes/sarzaminaryan-child/tests/test-font-loading.php`
· تاریخ: ۲۰۲۶-۱۱-۱۱

---

[BUG-20261125-01] دوشاخهشدن وضعیت خوشهها بین `.memory/` و `topic_clusters_100.json`

· وضعیت: حلشده (همگامسازی داده، بدون تغییر کد محصول)
· علائم: `.memory/wiki/roadmap.md` و `hot.md` شش خوشه و ۱۸ شاخهٔ تهران را منتشرشده نشان میدادند، در حالی که `tools/wp-agent/topic_clusters_100.json` همهٔ ۱۰۰ خوشه را `cluster_status: "todo"`، همهٔ ۲۳۹ شاخه را `status: "todo"` و `summary.completed_clusters: 0` نگه داشته بود. نتیجه: هر نشست تازهای از روی دادهٔ ماشینی، خوشهٔ #1 تهران را دوباره «انجامنشده» تشخیص میداد.
· علت ریشهای (Root Cause): قانون ثبت پیشرفت پایان نشست فقط سه فایل `.memory/wiki/{hot,roadmap,log}.md` و `.agent/*` را الزامی کرده بود؛ بهروزرسانی `topic_clusters_100.json` در عمل فراموش شد و گارد `test_memory_protocol.py` فقط ساختار (۱۰۰ خوشه / ۲۳۹ شاخه / ترتیب استانها) را میسنجد، نه محتوای وضعیتها.
· مسیرهایی که امتحان شد و شکست خورد: تکیه بر شمارندهٔ متنی حافظه بهعنوان منبع واحد (دادهٔ ماشینی همچنان کهنه ماند).
· راهحل نهایی: همگامسازی وضعیتهای شش خوشه و ۱۸ شاخهٔ منتشرشده به `done`، اسلامشهر/پاکدشت به `draft_local`، بهروزرسانی `summary` و `updated`، و درج یادداشت صریح «منتشرشده ≠ پیشنویس محلی» در `hot.md`/`roadmap.md`. پیشنهاد برای نشست بعد: افزودن یک assertion سبک به `test_memory_protocol.py` که اختلاف شمارندهٔ `roadmap.md` و `summary.completed_clusters` را گزارش کند.
· فایل/خط درگیر: `tools/wp-agent/topic_clusters_100.json`, `.memory/wiki/roadmap.md`, `tools/wp-agent/test_memory_protocol.py`
· منبع: ممیزی نشست ۲۰۲۶-۱۱-۲۵ و فایلهای نتایج `20261124-tehran-cluster-publish-v4` / `20261124-tehran-hubs-publish` / `20261124-tehran-cluster-v5b-purge-probe`
· تاریخ: ۲۰۲۶-۱۱-۲۵

---

[BUG-20261125-02] خوانش اشتباه «شهر سوم» بهجای «شوش» و حذف اصفهان #3 از سند تحویل

· وضعیت: حلشده (همگامسازی اسناد؛ بدون تغییر محصول)
· علائم: در `HANDOFF-2026-11-25.md` و سه فایل حافظه، عبارت پایانی مالک «شهر شوم» به «شهر شوش / استان خوزستان» تفسیر و بهصورت «مورد نیازمند تأیید» ثبت شد؛ همچنین سند تحویل فقط «استان ۲ خراسان رضوی» را بهعنوان عملیات بعدی نشان میداد و جایگاه **#3 اصفهان (۷ شاخه، استان ۳)** را قفل نکرده بود.
· علت ریشهای (Root Cause): تکیه بر حدس املایی بهجای خواندن مستقیم `tools/wp-agent/topic_clusters_100.json` (`clusters[0:3]` و `province_execution_order[0:3]`)؛ هیچ گارد CI ای هم ترتیب سه خوشهٔ نخست را نمیسنجید.
· مسیرهایی که امتحان شد و شکست خورد: پرسیدن «منظورتان شوش است؟» از مالک در `ask_user`؛ پاسخ بهصورت تصحیح صریح ترتیب رودمپ آمد، نه تأیید شوش.
· راهحل نهایی: حذف کامل تفسیر شوش، درج جدول سه خوشهٔ نخست در `hot.md`, `roadmap.md`, `HANDOFF-2026-11-25.md` و `topic-cluster-roadmap.md`، همگامسازی `.agent/{STATUS,ROADMAP}.md` و `session/current.md`، و افزودن `test_memory_protocol.py::test_first_three_clusters_and_province_order` به CI.
· فایل/خط درگیر: `docs/live-site/HANDOFF-2026-11-25.md` §۲/§۵, `.memory/wiki/hot.md`, `.memory/wiki/roadmap.md`, `tools/wp-agent/test_memory_protocol.py`
· منبع: تصحیح مالک (۲۰۲۶-۱۱-۲۵) + `tools/wp-agent/topic_clusters_100.json`
· تاریخ: ۲۰۲۶-۱۱-۲۵

[BUG-20261127-02] تایپوی دامنه در `home`/`siteurl` — ریشهٔ واقعی قطعی سایت
· وضعیت: **بسته شد (تشخیص) — اصلاح آماده، منتظر اجرای مالک**
· نشانه: بازدید `sarzaminyaran.ir` (NXDOMAIN)؛ vhost درست پاسخ نمی‌دهد.
· ریشه: در `IR_options` دو گزینهٔ `siteurl` و `home` = `https://sarzaminyaran.ir` (یک y اضافه). وردپرس از این مقدار آدرس کانونیک و ریدایرکت می‌سازد.
· دامنهٔ تایپی وجود ندارد (NXDOMAIN)؛ `sarzaminaryan.ir` سالم است.
· دامنهٔ مشکل: ۲ گزینهٔ `options` (بحرانی)، ۲۰۱ `post_content` (آدرس تصویر: جاذبه ۳۱، راهنما ۶۷، صفحه ۵، ناوبری ۱، پیوست ۳، بازبینی ۹۴)، ۱۰۵ `postmeta`، ۱ `users.user_url`، ۱۰۷ `iaa_reports.post_url`، ۴۶٬۷۵۴ ردیف Clarity. `guid` **پاک** است (۰ مورد) → هیچ لینک دائمی نیاز به مهاجرت ندارد.
· اصلاح: `docs/ops/sql/domain-typo-fix.sql` (پشتیبان → پیش‌شمارش → REPLACE محدود → راستی‌آزمایی). جایگزینی هم‌طول است (۱۳→۱۳) پس سریالایزها سالم می‌مانند. اعتبارسنجی روی کپی واقعی داده: هر ۵ شمارنده صفر، `siteurl`/`home` درست، `s:25` هنوز ۲۵ نویسه.
· ابزار: `tools/ops/domain_typo_fix.py` + ۷ تست `tools/ops/test_domain_typo_fix.py`.
· آموزش: پیش از هر تشخیص زیرساختی (DNS/SSL/vhost)، `SELECT option_value FROM wp_options WHERE option_name IN ('siteurl','home')` را بخوانید.
