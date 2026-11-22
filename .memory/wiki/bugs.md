# ثبت باگ‌ها و راه‌حل‌ها (Bugs & Root Causes)

> **قانون ضد تکرار:** هرگز راه‌حلی که یک بار شکست خورده را بدون دلیل جدید تکرار نکن. پیش از هر اصلاح، این فایل را چک کن.

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
